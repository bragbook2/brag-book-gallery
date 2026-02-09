<?php
/**
 * Orphan Manager
 *
 * Detects and removes WordPress items (posts and terms) that no longer
 * have corresponding entries in the BRAGBook API. Compares WordPress
 * procedure terms and case posts against the current API data to find
 * items that should be cleaned up.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      4.3.3
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Data\Database;
use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Orphan Manager Class
 *
 * Handles detection and deletion of orphaned WordPress items
 * that are no longer present in the BRAGBook API.
 *
 * @since 4.3.3
 */
class Orphan_Manager {

	/**
	 * Database instance
	 *
	 * @since 4.3.3
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor
	 *
	 * @since 4.3.3
	 * @param Database $database Database dependency.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Detect orphaned items by comparing WordPress data against API data
	 *
	 * Procedures: checks if each WP term's procedure_id exists in the API terms.
	 * Cases: checks if each WP case post's procedure_api_id still exists in the API.
	 *
	 * @since 4.3.3
	 *
	 * @param array $valid_procedure_ids All procedure IDs from the API terms endpoint.
	 * @param array $valid_case_map      Manifest: { procedure_api_id => [case_id, ...] }.
	 *
	 * @return array Array of orphaned items with enriched data.
	 */
	public function detect_orphans( array $valid_procedure_ids, array $valid_case_map = [] ): array {
		$orphans = [];

		// Detect orphaned procedures
		$orphans = array_merge( $orphans, $this->detect_procedure_orphans( $valid_procedure_ids ) );

		// Detect orphaned cases
		if ( ! empty( $valid_case_map ) ) {
			$orphans = array_merge( $orphans, $this->detect_case_orphans( $valid_procedure_ids, $valid_case_map ) );
		}

		return $orphans;
	}

	/**
	 * Detect orphaned procedure terms
	 *
	 * Finds WordPress procedure terms whose procedure_id meta value
	 * does not exist in the current API terms response.
	 *
	 * @since 4.3.3
	 *
	 * @param array $valid_procedure_ids All procedure IDs from the API.
	 *
	 * @return array Orphaned procedure items.
	 */
	private function detect_procedure_orphans( array $valid_procedure_ids ): array {
		$all_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
		] );

		if ( empty( $all_terms ) || is_wp_error( $all_terms ) ) {
			return [];
		}

		$orphans = [];
		foreach ( $all_terms as $term ) {
			$api_id = get_term_meta( $term->term_id, 'procedure_id', true );

			// Skip terms without an API ID (manually created, not from sync)
			if ( empty( $api_id ) ) {
				continue;
			}

			// If this term's API ID is NOT in the current API data, it's an orphan
			if ( ! in_array( (int) $api_id, $valid_procedure_ids, true ) ) {
				$orphans[] = [
					'item_type'    => 'procedure',
					'api_id'       => (int) $api_id,
					'wordpress_id' => $term->term_id,
					'wordpress_type' => 'term',
					'name'         => $term->name,
				];
			}
		}

		return $orphans;
	}

	/**
	 * Detect orphaned case posts
	 *
	 * Finds WordPress case posts where the procedure_api_id no longer
	 * exists in the API, or the case is no longer in the manifest.
	 *
	 * @since 4.3.3
	 *
	 * @param array $valid_procedure_ids All procedure IDs from the API.
	 * @param array $valid_case_map      Manifest: { procedure_api_id => [case_id, ...] }.
	 *
	 * @return array Orphaned case items.
	 */
	private function detect_case_orphans( array $valid_procedure_ids, array $valid_case_map ): array {
		// Flatten all valid case IDs from the manifest
		$valid_case_ids = [];
		foreach ( $valid_case_map as $case_ids ) {
			if ( is_array( $case_ids ) ) {
				foreach ( $case_ids as $id ) {
					$valid_case_ids[] = (int) $id;
				}
			}
		}
		$valid_case_ids = array_unique( $valid_case_ids );

		// Get all case posts
		$posts = get_posts( [
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		if ( empty( $posts ) ) {
			return [];
		}

		$orphans = [];
		foreach ( $posts as $post_id ) {
			$procedure_api_id = (int) get_post_meta( $post_id, '_procedure_id', true );
			$case_id          = (int) get_post_meta( $post_id, 'brag_book_gallery_original_case_id', true );

			// Skip posts without sync metadata
			if ( empty( $procedure_api_id ) && empty( $case_id ) ) {
				continue;
			}

			$is_orphan = false;

			// Orphan if the procedure no longer exists in the API
			if ( ! empty( $procedure_api_id ) && ! in_array( $procedure_api_id, $valid_procedure_ids, true ) ) {
				$is_orphan = true;
			}

			// Orphan if the case ID is no longer in the manifest
			if ( ! $is_orphan && ! empty( $case_id ) && ! in_array( $case_id, $valid_case_ids, true ) ) {
				$is_orphan = true;
			}

			if ( $is_orphan ) {
				$post = get_post( $post_id );
				$orphans[] = [
					'item_type'        => 'case',
					'api_id'           => $case_id,
					'wordpress_id'     => $post_id,
					'wordpress_type'   => 'post',
					'procedure_api_id' => $procedure_api_id,
					'name'             => $post ? $post->post_title : "(Post #{$post_id} - not found)",
				];
			}
		}

		return $orphans;
	}

	/**
	 * Delete orphaned items
	 *
	 * Iterates through orphaned items, deletes the corresponding WordPress
	 * objects (posts or terms), and logs each deletion for audit compliance.
	 *
	 * @since 4.3.3
	 *
	 * @param array $orphans Array of orphan data from detect_orphans().
	 *
	 * @return array Results: { deleted: int, errors: array, items: array }
	 */
	public function delete_orphaned_items( array $orphans ): array {
		$deleted = 0;
		$errors  = [];
		$items   = [];

		foreach ( $orphans as $orphan ) {
			$wordpress_id   = (int) ( $orphan['wordpress_id'] ?? 0 );
			$wordpress_type = $orphan['wordpress_type'] ?? '';
			$item_type      = $orphan['item_type'] ?? '';

			if ( $wordpress_id <= 0 || empty( $wordpress_type ) ) {
				$errors[] = "Invalid orphan data: missing wordpress_id or type";
				continue;
			}

			$delete_result = false;
			$item_name     = $orphan['name'] ?? 'Unknown';

			try {
				if ( $wordpress_type === 'post' ) {
					$delete_result = wp_delete_post( $wordpress_id, true );
					$delete_result = false !== $delete_result && null !== $delete_result;
				} elseif ( $wordpress_type === 'term' ) {
					$taxonomy = $this->get_taxonomy_for_item_type( $item_type );
					if ( $taxonomy ) {
						$result        = wp_delete_term( $wordpress_id, $taxonomy );
						$delete_result = ! is_wp_error( $result ) && $result !== false;
					} else {
						$errors[] = "Unknown taxonomy for item type: {$item_type}";
						continue;
					}
				}

				if ( $delete_result ) {
					// Log deletion for audit trail
					$this->log_deletion( $item_type, (int) ( $orphan['api_id'] ?? 0 ), $wordpress_id, $wordpress_type );

					$deleted++;
					$items[] = [
						'item_type'    => $item_type,
						'api_id'       => $orphan['api_id'] ?? 0,
						'wordpress_id' => $wordpress_id,
						'name'         => $item_name,
						'status'       => 'deleted',
					];
				} else {
					$errors[] = "Failed to delete {$wordpress_type} ID {$wordpress_id} ({$item_type})";
				}
			} catch ( \Exception $e ) {
				$errors[] = "Error deleting {$wordpress_type} ID {$wordpress_id}: " . $e->getMessage();
			}
		}

		return [
			'deleted' => $deleted,
			'errors'  => $errors,
			'items'   => $items,
		];
	}

	/**
	 * Generate orphan report grouped by item type
	 *
	 * @since 4.3.3
	 *
	 * @param array $orphans Array of orphan data from detect_orphans().
	 *
	 * @return array Grouped report with counts and item details.
	 */
	public function generate_orphan_report( array $orphans ): array {
		$report = [
			'total'   => count( $orphans ),
			'by_type' => [
				'case'      => [ 'count' => 0, 'items' => [] ],
				'procedure' => [ 'count' => 0, 'items' => [] ],
			],
		];

		foreach ( $orphans as $orphan ) {
			$type = $orphan['item_type'] ?? 'unknown';
			if ( ! isset( $report['by_type'][ $type ] ) ) {
				continue;
			}

			$report['by_type'][ $type ]['count']++;
			$report['by_type'][ $type ]['items'][] = [
				'api_id'       => $orphan['api_id'] ?? 0,
				'wordpress_id' => $orphan['wordpress_id'] ?? 0,
				'name'         => $orphan['name'] ?? 'Unknown',
			];
		}

		return $report;
	}

	/**
	 * Extract all procedure IDs from API terms data
	 *
	 * Extracts IDs from both parent categories and their nested procedures.
	 *
	 * @since 4.3.3
	 *
	 * @param array $sync_data The full API terms response data.
	 *
	 * @return array<int> All procedure IDs found in the data.
	 */
	public static function extract_procedure_ids_from_sync_data( array $sync_data ): array {
		$ids   = [];
		$terms = $sync_data['data']['terms'] ?? [];

		foreach ( $terms as $category ) {
			if ( ! empty( $category['id'] ) ) {
				$ids[] = (int) $category['id'];
			}

			if ( ! empty( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( ! empty( $procedure['id'] ) ) {
						$ids[] = (int) $procedure['id'];
					}
				}
			}
		}

		return $ids;
	}

	/**
	 * Get taxonomy name for a given item type
	 *
	 * @since 4.3.3
	 *
	 * @param string $item_type Registry item type.
	 *
	 * @return string|null Taxonomy name or null.
	 */
	private function get_taxonomy_for_item_type( string $item_type ): ?string {
		return match ( $item_type ) {
			'procedure' => Taxonomies::TAXONOMY_PROCEDURES,
			'doctor'    => Taxonomies::TAXONOMY_DOCTORS,
			default     => null,
		};
	}

	/**
	 * Log a deletion event for audit trail
	 *
	 * @since 4.3.3
	 *
	 * @param string $item_type      Item type deleted.
	 * @param int    $api_id         API-side ID.
	 * @param int    $wordpress_id   WordPress ID deleted.
	 * @param string $wordpress_type 'post' or 'term'.
	 *
	 * @return void
	 */
	private function log_deletion( string $item_type, int $api_id, int $wordpress_id, string $wordpress_type ): void {
		$message = sprintf(
			'Orphan deleted: type=%s, api_id=%d, wp_id=%d, wp_type=%s',
			$item_type,
			$api_id,
			$wordpress_id,
			$wordpress_type
		);

		$this->database->log_sync_operation(
			'full',
			'completed',
			0,
			0,
			$message,
			'manual'
		);

		error_log( 'BRAGBook Orphan Manager: ' . $message );
	}
}
