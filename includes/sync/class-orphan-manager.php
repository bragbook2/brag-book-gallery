<?php
/**
 * Orphan Manager
 *
 * Detects and removes WordPress items (posts and terms) that no longer
 * have corresponding entries in the BRAGBook API. Provides HIPAA-compliant
 * audit logging for all deletion operations.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      4.3.3
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Data\Database;
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
	 * Detect orphaned items
	 *
	 * Finds registry entries whose last_sync_session doesn't match the
	 * current session, indicating they were not seen during the latest sync.
	 * Enriches results with WordPress item names for preview display.
	 *
	 * @since 4.3.3
	 *
	 * @param string      $sync_session_id Current sync session ID.
	 * @param string      $api_token       API token for tenant isolation.
	 * @param string|null $item_type       Optional: filter by item type.
	 *
	 * @return array Array of orphaned items with enriched data.
	 */
	public function detect_orphans( string $sync_session_id, string $api_token, ?string $item_type = null ): array {
		$raw_orphans = $this->database->find_orphans_by_session( $sync_session_id, $api_token, $item_type );

		if ( empty( $raw_orphans ) ) {
			return [];
		}

		$enriched = [];
		foreach ( $raw_orphans as $orphan ) {
			$name = $this->get_wordpress_item_name( (int) $orphan->wordpress_id, $orphan->wordpress_type );

			$enriched[] = [
				'registry_id'      => (int) $orphan->id,
				'item_type'        => $orphan->item_type,
				'api_id'           => (int) $orphan->api_id,
				'wordpress_id'     => (int) $orphan->wordpress_id,
				'wordpress_type'   => $orphan->wordpress_type,
				'procedure_api_id' => (int) $orphan->procedure_api_id,
				'last_synced'      => $orphan->last_synced,
				'last_session'     => $orphan->last_sync_session,
				'name'             => $name,
			];
		}

		return $enriched;
	}

	/**
	 * Delete orphaned items
	 *
	 * Iterates through orphaned items, deletes the corresponding WordPress
	 * objects (posts or terms), removes registry entries, and logs each
	 * deletion for HIPAA audit compliance. Logs contain no PHI.
	 *
	 * @since 4.3.3
	 *
	 * @param array  $orphans         Array of orphan data from detect_orphans().
	 * @param string $sync_session_id Session ID for audit logging.
	 *
	 * @return array Results: { deleted: int, errors: array, items: array }
	 */
	public function delete_orphaned_items( array $orphans, string $sync_session_id ): array {
		$deleted = 0;
		$errors  = [];
		$items   = [];

		foreach ( $orphans as $orphan ) {
			$wordpress_id   = (int) ( $orphan['wordpress_id'] ?? 0 );
			$wordpress_type = $orphan['wordpress_type'] ?? '';
			$item_type      = $orphan['item_type'] ?? '';
			$registry_id    = (int) ( $orphan['registry_id'] ?? 0 );

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
					// Remove registry entry
					if ( $registry_id > 0 ) {
						$this->database->delete_registry_items( [ $registry_id ] );
					}

					// HIPAA audit log: no PHI, only IDs and types
					$this->log_deletion( $item_type, (int) ( $orphan['api_id'] ?? 0 ), $wordpress_id, $wordpress_type, $sync_session_id );

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
				'doctor'    => [ 'count' => 0, 'items' => [] ],
			],
		];

		foreach ( $orphans as $orphan ) {
			$type = $orphan['item_type'] ?? 'unknown';
			if ( ! isset( $report['by_type'][ $type ] ) ) {
				continue;
			}

			$report['by_type'][ $type ]['count']++;
			$report['by_type'][ $type ]['items'][] = [
				'registry_id'  => $orphan['registry_id'] ?? 0,
				'api_id'       => $orphan['api_id'] ?? 0,
				'wordpress_id' => $orphan['wordpress_id'] ?? 0,
				'name'         => $orphan['name'] ?? 'Unknown',
			];
		}

		return $report;
	}

	/**
	 * Get WordPress item name for display
	 *
	 * @since 4.3.3
	 *
	 * @param int    $wordpress_id   WordPress ID.
	 * @param string $wordpress_type 'post' or 'term'.
	 *
	 * @return string Item name or fallback string.
	 */
	private function get_wordpress_item_name( int $wordpress_id, string $wordpress_type ): string {
		if ( $wordpress_type === 'post' ) {
			$post = get_post( $wordpress_id );
			return $post ? $post->post_title : "(Post #{$wordpress_id} - not found)";
		}

		if ( $wordpress_type === 'term' ) {
			$term = get_term( $wordpress_id );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
			return "(Term #{$wordpress_id} - not found)";
		}

		return "(Unknown type #{$wordpress_id})";
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
	 * Log a deletion event for HIPAA audit trail
	 *
	 * Logs to the sync_log table with operation details.
	 * Contains NO PHI - only item types, IDs, and session info.
	 *
	 * @since 4.3.3
	 *
	 * @param string $item_type      Item type deleted.
	 * @param int    $api_id         API-side ID.
	 * @param int    $wordpress_id   WordPress ID deleted.
	 * @param string $wordpress_type 'post' or 'term'.
	 * @param string $session_id     Sync session ID.
	 *
	 * @return void
	 */
	private function log_deletion( string $item_type, int $api_id, int $wordpress_id, string $wordpress_type, string $session_id ): void {
		$message = sprintf(
			'Orphan deleted: type=%s, api_id=%d, wp_id=%d, wp_type=%s, session=%s',
			$item_type,
			$api_id,
			$wordpress_id,
			$wordpress_type,
			$session_id
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
