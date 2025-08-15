<?php
/**
 * Data Validator
 *
 * Validates data integrity during migration and sync operations.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Migration
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Migration;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;
use BRAGBookGallery\Includes\Core\Database;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Validator Class
 *
 * Provides validation methods for ensuring data integrity.
 *
 * @since 3.0.0
 */
class Data_Validator {

	/**
	 * Database manager instance
	 *
	 * @since 3.0.0
	 * @var Database
	 */
	private $database;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->database = new Database();
	}

	/**
	 * Validate post data
	 *
	 * @since 3.0.0
	 * @param array $data Post data to validate.
	 * @return bool True if data is valid.
	 */
	public function validate_post_data( array $data ): bool {
		// Check required fields
		$required_fields = array( 'post_title', 'post_type' );
		
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return false;
			}
		}

		// Validate post type
		if ( $data['post_type'] !== Gallery_Post_Type::POST_TYPE ) {
			return false;
		}

		// Validate post status
		$valid_statuses = array( 'publish', 'draft', 'private' );
		if ( isset( $data['post_status'] ) && ! in_array( $data['post_status'], $valid_statuses, true ) ) {
			return false;
		}

		// Validate meta data if present
		if ( isset( $data['meta_input'] ) && ! $this->validate_post_meta( $data['meta_input'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate taxonomy data
	 *
	 * @since 3.0.0
	 * @param array $data Taxonomy data to validate.
	 * @return bool True if data is valid.
	 */
	public function validate_taxonomy_data( array $data ): bool {
		// Check required fields
		if ( empty( $data['name'] ) || empty( $data['taxonomy'] ) ) {
			return false;
		}

		// Validate taxonomy
		$valid_taxonomies = array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		if ( ! in_array( $data['taxonomy'], $valid_taxonomies, true ) ) {
			return false;
		}

		// Validate slug
		if ( isset( $data['slug'] ) ) {
			$slug = sanitize_title( $data['slug'] );
			if ( $slug !== $data['slug'] || empty( $slug ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate migration integrity
	 *
	 * @since 3.0.0
	 * @param string $target_mode Target mode after migration.
	 * @return array Validation result with status and errors.
	 */
	public function validate_migration( string $target_mode ): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
			'stats' => array(),
		);

		switch ( $target_mode ) {
			case 'local':
				$result = $this->validate_local_mode_migration();
				break;
			case 'javascript':
				$result = $this->validate_javascript_mode_migration();
				break;
			default:
				$result['valid'] = false;
				$result['errors'][] = 'Invalid target mode';
		}

		return $result;
	}

	/**
	 * Check data integrity
	 *
	 * @since 3.0.0
	 * @return array Integrity check results.
	 */
	public function check_data_integrity(): array {
		$results = array(
			'posts' => $this->check_post_integrity(),
			'taxonomies' => $this->check_taxonomy_integrity(),
			'meta' => $this->check_meta_integrity(),
			'images' => $this->check_image_integrity(),
			'sync' => $this->check_sync_integrity(),
		);

		// Calculate overall status
		$overall_valid = true;
		$total_errors = 0;
		$total_warnings = 0;

		foreach ( $results as $check ) {
			if ( ! $check['valid'] ) {
				$overall_valid = false;
			}
			$total_errors += count( $check['errors'] );
			$total_warnings += count( $check['warnings'] );
		}

		return array(
			'overall_valid' => $overall_valid,
			'total_errors' => $total_errors,
			'total_warnings' => $total_warnings,
			'checks' => $results,
		);
	}

	/**
	 * Validate Local mode migration
	 *
	 * @since 3.0.0
	 * @return array Validation results.
	 */
	private function validate_local_mode_migration(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
			'stats' => array(),
		);

		// Check if posts were created
		$post_count = wp_count_posts( Gallery_Post_Type::POST_TYPE );
		$total_posts = $post_count->publish + $post_count->draft + $post_count->private;

		if ( $total_posts === 0 ) {
			$result['valid'] = false;
			$result['errors'][] = 'No gallery posts found after migration';
		} else {
			$result['stats']['total_posts'] = $total_posts;
			$result['stats']['published_posts'] = $post_count->publish;
		}

		// Check taxonomy terms
		$category_count = wp_count_terms( Gallery_Taxonomies::CATEGORY_TAXONOMY );
		$procedure_count = wp_count_terms( Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		if ( is_wp_error( $category_count ) || is_wp_error( $procedure_count ) ) {
			$result['errors'][] = 'Error counting taxonomy terms';
		} else {
			$result['stats']['categories'] = $category_count;
			$result['stats']['procedures'] = $procedure_count;

			if ( $category_count === 0 && $procedure_count === 0 ) {
				$result['warnings'][] = 'No taxonomy terms found';
			}
		}

		// Check sync data
		$sync_stats = $this->database->get_sync_stats();
		$result['stats']['sync'] = $sync_stats;

		if ( $sync_stats['total_syncs'] === 0 ) {
			$result['warnings'][] = 'No sync operations recorded';
		}

		// Check for posts with missing required meta
		$posts_with_issues = $this->check_posts_missing_meta();
		if ( ! empty( $posts_with_issues ) ) {
			$result['warnings'][] = sprintf( 
				'%d posts are missing required metadata', 
				count( $posts_with_issues ) 
			);
			$result['stats']['posts_missing_meta'] = count( $posts_with_issues );
		}

		// Check for broken images
		$broken_images = $this->check_broken_images();
		if ( ! empty( $broken_images ) ) {
			$result['warnings'][] = sprintf( 
				'%d posts have broken or missing images', 
				count( $broken_images ) 
			);
			$result['stats']['posts_with_broken_images'] = count( $broken_images );
		}

		return $result;
	}

	/**
	 * Validate JavaScript mode migration
	 *
	 * @since 3.0.0
	 * @return array Validation results.
	 */
	private function validate_javascript_mode_migration(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
			'stats' => array(),
		);

		// Check API settings
		$api_url = get_option( 'brag_book_gallery_api_url', '' );
		$api_token = get_option( 'brag_book_gallery_api_token', '' );

		if ( empty( $api_url ) || empty( $api_token ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'API settings are not configured';
		}

		// Test API connectivity
		if ( ! empty( $api_url ) && ! empty( $api_token ) ) {
			$api_test = $this->test_api_connection( $api_url, $api_token );
			if ( ! $api_test ) {
				$result['valid'] = false;
				$result['errors'][] = 'Cannot connect to API';
			}
		}

		// Check if local posts are properly handled
		$post_count = wp_count_posts( Gallery_Post_Type::POST_TYPE );
		$published_posts = $post_count->publish;

		$result['stats']['remaining_published_posts'] = $published_posts;

		// If posts are still published in JavaScript mode, that might be a problem
		if ( $published_posts > 0 ) {
			$result['warnings'][] = sprintf( 
				'%d gallery posts are still published (consider archiving them)', 
				$published_posts 
			);
		}

		return $result;
	}

	/**
	 * Check post integrity
	 *
	 * @since 3.0.0
	 * @return array Post integrity results.
	 */
	private function check_post_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		// Get all gallery posts
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		if ( empty( $posts ) ) {
			return $result; // No posts to validate
		}

		foreach ( $posts as $post ) {
			// Check for empty titles
			if ( empty( $post->post_title ) || trim( $post->post_title ) === '' ) {
				$result['errors'][] = "Post {$post->ID} has empty title";
				$result['valid'] = false;
			}

			// Check for duplicate slugs
			$duplicate_slugs = get_posts( array(
				'post_type' => Gallery_Post_Type::POST_TYPE,
				'name' => $post->post_name,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields' => 'ids',
				'post__not_in' => array( $post->ID ),
			) );

			if ( ! empty( $duplicate_slugs ) ) {
				$result['warnings'][] = "Post {$post->ID} has duplicate slug: {$post->post_name}";
			}
		}

		return $result;
	}

	/**
	 * Check taxonomy integrity
	 *
	 * @since 3.0.0
	 * @return array Taxonomy integrity results.
	 */
	private function check_taxonomy_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		$taxonomies = array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			) );

			if ( is_wp_error( $terms ) ) {
				$result['errors'][] = "Error retrieving terms for {$taxonomy}: " . $terms->get_error_message();
				$result['valid'] = false;
				continue;
			}

			foreach ( $terms as $term ) {
				// Check for empty names
				if ( empty( $term->name ) || trim( $term->name ) === '' ) {
					$result['errors'][] = "Term {$term->term_id} in {$taxonomy} has empty name";
					$result['valid'] = false;
				}

				// Check for duplicate slugs within taxonomy
				$duplicate_slugs = get_terms( array(
					'taxonomy' => $taxonomy,
					'slug' => $term->slug,
					'hide_empty' => false,
					'exclude' => array( $term->term_id ),
				) );

				if ( ! empty( $duplicate_slugs ) && ! is_wp_error( $duplicate_slugs ) ) {
					$result['warnings'][] = "Term {$term->term_id} in {$taxonomy} has duplicate slug: {$term->slug}";
				}

				// Check parent relationships for categories
				if ( $taxonomy === Gallery_Taxonomies::CATEGORY_TAXONOMY && $term->parent > 0 ) {
					$parent = get_term( $term->parent, $taxonomy );
					if ( ! $parent || is_wp_error( $parent ) ) {
						$result['errors'][] = "Term {$term->term_id} has invalid parent: {$term->parent}";
						$result['valid'] = false;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Check meta integrity
	 *
	 * @since 3.0.0
	 * @return array Meta integrity results.
	 */
	private function check_meta_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		foreach ( $posts as $post_id ) {
			// Check for invalid JSON in meta fields
			$json_meta_fields = array(
				'_brag_patient_info',
				'_brag_procedure_details',
				'_brag_seo_data',
				'_brag_before_images',
				'_brag_after_images',
			);

			foreach ( $json_meta_fields as $meta_key ) {
				$meta_value = get_post_meta( $post_id, $meta_key, true );
				
				if ( ! empty( $meta_value ) && is_string( $meta_value ) ) {
					$decoded = json_decode( $meta_value, true );
					
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						$result['errors'][] = "Post {$post_id} has invalid JSON in {$meta_key}";
						$result['valid'] = false;
					}
				}
			}

			// Check for missing required sync meta in local mode
			$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
			if ( $mode_manager->is_local_mode() ) {
				$case_id = get_post_meta( $post_id, '_brag_case_id', true );
				if ( empty( $case_id ) ) {
					$result['warnings'][] = "Post {$post_id} is missing case ID metadata";
				}
			}
		}

		return $result;
	}

	/**
	 * Check image integrity
	 *
	 * @since 3.0.0
	 * @return array Image integrity results.
	 */
	private function check_image_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		foreach ( $posts as $post_id ) {
			// Check featured image
			if ( has_post_thumbnail( $post_id ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				$attachment = get_post( $thumbnail_id );
				
				if ( ! $attachment ) {
					$result['errors'][] = "Post {$post_id} has invalid featured image reference";
					$result['valid'] = false;
				} else {
					$file_path = get_attached_file( $thumbnail_id );
					if ( ! file_exists( $file_path ) ) {
						$result['errors'][] = "Post {$post_id} featured image file missing: {$file_path}";
						$result['valid'] = false;
					}
				}
			}

			// Check gallery images
			$before_image_ids = get_post_meta( $post_id, '_brag_before_image_ids', true );
			$after_image_ids = get_post_meta( $post_id, '_brag_after_image_ids', true );

			$all_image_ids = array_merge(
				is_array( $before_image_ids ) ? $before_image_ids : array(),
				is_array( $after_image_ids ) ? $after_image_ids : array()
			);

			foreach ( $all_image_ids as $attachment_id ) {
				if ( ! is_numeric( $attachment_id ) ) {
					continue;
				}

				$attachment = get_post( $attachment_id );
				if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
					$result['errors'][] = "Post {$post_id} has invalid image reference: {$attachment_id}";
					$result['valid'] = false;
					continue;
				}

				$file_path = get_attached_file( $attachment_id );
				if ( ! file_exists( $file_path ) ) {
					$result['errors'][] = "Post {$post_id} image file missing: {$file_path}";
					$result['valid'] = false;
				}
			}
		}

		return $result;
	}

	/**
	 * Check sync integrity
	 *
	 * @since 3.0.0
	 * @return array Sync integrity results.
	 */
	private function check_sync_integrity(): array {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		// Check sync tables exist
		$sync_log_table = $this->database->get_sync_log_table();
		$case_map_table = $this->database->get_case_map_table();

		global $wpdb;

		// Check if tables exist
		$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$sync_log_table}'" ) === $sync_log_table;
		if ( ! $tables_exist ) {
			$result['warnings'][] = 'Sync log table does not exist';
		}

		$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$case_map_table}'" ) === $case_map_table;
		if ( ! $tables_exist ) {
			$result['warnings'][] = 'Case map table does not exist';
		}

		// Check for orphaned mappings
		if ( $tables_exist ) {
			$orphaned_mappings = $wpdb->get_var( 
				"SELECT COUNT(*) FROM {$case_map_table} cm 
				 LEFT JOIN {$wpdb->posts} p ON cm.post_id = p.ID 
				 WHERE p.ID IS NULL"
			);

			if ( $orphaned_mappings > 0 ) {
				$result['warnings'][] = "{$orphaned_mappings} orphaned case mappings found";
			}
		}

		return $result;
	}

	/**
	 * Validate post meta data
	 *
	 * @since 3.0.0
	 * @param array $meta_data Meta data to validate.
	 * @return bool True if valid.
	 */
	private function validate_post_meta( array $meta_data ): bool {
		// Check for required meta fields in local mode
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		
		if ( $mode_manager->is_local_mode() ) {
			$required_meta = array( '_brag_case_id' );
			
			foreach ( $required_meta as $meta_key ) {
				if ( ! isset( $meta_data[ $meta_key ] ) || empty( $meta_data[ $meta_key ] ) ) {
					return false;
				}
			}
		}

		// Validate JSON fields
		$json_fields = array(
			'_brag_patient_info',
			'_brag_procedure_details',
			'_brag_seo_data',
			'_brag_before_images',
			'_brag_after_images',
		);

		foreach ( $json_fields as $field ) {
			if ( isset( $meta_data[ $field ] ) && ! empty( $meta_data[ $field ] ) ) {
				$decoded = json_decode( $meta_data[ $field ], true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check posts missing required meta
	 *
	 * @since 3.0.0
	 * @return array Post IDs missing meta.
	 */
	private function check_posts_missing_meta(): array {
		global $wpdb;

		$posts_with_issues = $wpdb->get_col( 
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p 
				 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_brag_case_id'
				 WHERE p.post_type = %s 
				 AND p.post_status = 'publish'
				 AND pm.meta_value IS NULL",
				Gallery_Post_Type::POST_TYPE
			)
		);

		return $posts_with_issues ?: array();
	}

	/**
	 * Check for broken images
	 *
	 * @since 3.0.0
	 * @return array Post IDs with broken images.
	 */
	private function check_broken_images(): array {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		$posts_with_broken_images = array();

		foreach ( $posts as $post_id ) {
			$has_broken_images = false;

			// Check featured image
			if ( has_post_thumbnail( $post_id ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post_id );
				$file_path = get_attached_file( $thumbnail_id );
				
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$has_broken_images = true;
				}
			}

			// Check gallery images
			$before_images = get_post_meta( $post_id, '_brag_before_image_ids', true );
			$after_images = get_post_meta( $post_id, '_brag_after_image_ids', true );

			$all_images = array_merge(
				is_array( $before_images ) ? $before_images : array(),
				is_array( $after_images ) ? $after_images : array()
			);

			foreach ( $all_images as $attachment_id ) {
				if ( ! is_numeric( $attachment_id ) ) {
					continue;
				}

				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$has_broken_images = true;
					break;
				}
			}

			if ( $has_broken_images ) {
				$posts_with_broken_images[] = $post_id;
			}
		}

		return $posts_with_broken_images;
	}

	/**
	 * Test API connection
	 *
	 * @since 3.0.0
	 * @param string $api_url API URL.
	 * @param string $api_token API token.
	 * @return bool True if connection successful.
	 */
	private function test_api_connection( string $api_url, string $api_token ): bool {
		$response = wp_remote_get( $api_url . '/test', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Content-Type' => 'application/json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return $response_code === 200;
	}

	/**
	 * Fix common data issues
	 *
	 * @since 3.0.0
	 * @return array Results of fix attempts.
	 */
	public function fix_data_issues(): array {
		$results = array(
			'fixed' => 0,
			'failed' => 0,
			'messages' => array(),
		);

		// Fix missing case IDs
		$posts_missing_case_id = $this->check_posts_missing_meta();
		foreach ( $posts_missing_case_id as $post_id ) {
			// Generate a temporary case ID
			$temp_case_id = 'temp_' . $post_id . '_' . time();
			update_post_meta( $post_id, '_brag_case_id', $temp_case_id );
			
			$results['fixed']++;
			$results['messages'][] = "Added temporary case ID for post {$post_id}";
		}

		// Fix duplicate slugs
		$this->fix_duplicate_slugs( $results );

		// Fix broken JSON in meta fields
		$this->fix_broken_json_meta( $results );

		return $results;
	}

	/**
	 * Fix duplicate slugs
	 *
	 * @since 3.0.0
	 * @param array &$results Results array to update.
	 * @return void
	 */
	private function fix_duplicate_slugs( array &$results ): void {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		$slugs_seen = array();

		foreach ( $posts as $post ) {
			if ( in_array( $post->post_name, $slugs_seen, true ) ) {
				// Generate new unique slug
				$new_slug = wp_unique_post_slug( $post->post_name . '-' . $post->ID, $post->ID, $post->post_status, Gallery_Post_Type::POST_TYPE, $post->post_parent );
				
				wp_update_post( array(
					'ID' => $post->ID,
					'post_name' => $new_slug,
				) );

				$results['fixed']++;
				$results['messages'][] = "Fixed duplicate slug for post {$post->ID}: {$post->post_name} -> {$new_slug}";
			} else {
				$slugs_seen[] = $post->post_name;
			}
		}
	}

	/**
	 * Fix broken JSON in meta fields
	 *
	 * @since 3.0.0
	 * @param array &$results Results array to update.
	 * @return void
	 */
	private function fix_broken_json_meta( array &$results ): void {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		$json_meta_fields = array(
			'_brag_patient_info',
			'_brag_procedure_details',
			'_brag_seo_data',
			'_brag_before_images',
			'_brag_after_images',
		);

		foreach ( $posts as $post_id ) {
			foreach ( $json_meta_fields as $meta_key ) {
				$meta_value = get_post_meta( $post_id, $meta_key, true );
				
				if ( ! empty( $meta_value ) && is_string( $meta_value ) ) {
					$decoded = json_decode( $meta_value, true );
					
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						// Try to fix or remove broken JSON
						delete_post_meta( $post_id, $meta_key );
						
						$results['fixed']++;
						$results['messages'][] = "Removed broken JSON in post {$post_id} meta field: {$meta_key}";
					}
				}
			}
		}
	}

	/**
	 * Get validation report
	 *
	 * @since 3.0.0
	 * @return array Comprehensive validation report.
	 */
	public function get_validation_report(): array {
		$report = array(
			'timestamp' => current_time( 'mysql' ),
			'mode' => get_option( 'brag_book_gallery_mode', 'javascript' ),
			'integrity_check' => $this->check_data_integrity(),
		);

		// Add mode-specific validations
		if ( $report['mode'] === 'local' ) {
			$report['migration_validation'] = $this->validate_migration( 'local' );
		} else {
			$report['migration_validation'] = $this->validate_migration( 'javascript' );
		}

		return $report;
	}
}