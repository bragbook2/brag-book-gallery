<?php
/**
 * Sync Manager
 *
 * Manages synchronization of API data to local WordPress content.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Core\Database;
use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync Manager Class
 *
 * Coordinates the synchronization of API data to local WordPress content.
 *
 * @since 3.0.0
 */
class Sync_Manager {

	/**
	 * Database manager instance
	 *
	 * @since 3.0.0
	 * @var Database
	 */
	private $database;

	/**
	 * Data mapper instance
	 *
	 * @since 3.0.0
	 * @var Data_Mapper
	 */
	private $data_mapper;

	/**
	 * Image sync instance
	 *
	 * @since 3.0.0
	 * @var Image_Sync
	 */
	private $image_sync;

	/**
	 * Current sync log ID
	 *
	 * @since 3.0.0
	 * @var int|null
	 */
	private $current_sync_log_id = null;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->database = new Database();
		$this->data_mapper = new Data_Mapper();
		$this->image_sync = new Image_Sync();
		
		$this->init();
	}

	/**
	 * Initialize sync manager
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Add AJAX handlers
		add_action( 'wp_ajax_brag_book_gallery_sync_now', array( $this, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_brag_book_gallery_sync_status', array( $this, 'ajax_sync_status' ) );
		add_action( 'wp_ajax_brag_book_gallery_cancel_sync', array( $this, 'ajax_cancel_sync' ) );

		// Add WP-Cron hooks for scheduled syncing
		add_action( 'brag_book_gallery_scheduled_sync', array( $this, 'run_scheduled_sync' ) );
		
		// Add REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Sync all gallery data
	 *
	 * @since 3.0.0
	 * @param bool $force Force sync even if already running.
	 * @return bool Success status.
	 */
	public function sync_all( bool $force = false ): bool {
		// Check if sync is already running
		if ( ! $force && $this->is_sync_running() ) {
			return false;
		}

		// Start sync logging
		$this->current_sync_log_id = $this->database->log_sync_operation( 'full', 'started' );
		
		// Set sync status
		set_transient( 'brag_book_gallery_sync_status', 'running', HOUR_IN_SECONDS );
		
		try {
			$items_processed = 0;
			$items_failed = 0;
			$errors = array();

			// Step 1: Sync categories and procedures
			$taxonomy_result = $this->sync_taxonomies();
			if ( ! $taxonomy_result['success'] ) {
				$errors[] = 'Taxonomy sync failed: ' . $taxonomy_result['message'];
				$items_failed++;
			} else {
				$items_processed++;
			}

			// Step 2: Sync gallery cases
			$cases_result = $this->sync_cases();
			$items_processed += $cases_result['processed'];
			$items_failed += $cases_result['failed'];
			
			if ( ! empty( $cases_result['errors'] ) ) {
				$errors = array_merge( $errors, $cases_result['errors'] );
			}

			// Update sync log
			$final_status = $items_failed === 0 ? 'completed' : 'failed';
			$this->database->update_sync_log(
				$this->current_sync_log_id,
				$final_status,
				$items_processed,
				$items_failed,
				implode( "\n", $errors )
			);

			// Clear sync status
			delete_transient( 'brag_book_gallery_sync_status' );
			
			// Update last sync time
			update_option( 'brag_book_gallery_last_sync', current_time( 'mysql' ) );

			return $final_status === 'completed';

		} catch ( \Exception $e ) {
			// Log error and cleanup
			$this->database->update_sync_log(
				$this->current_sync_log_id,
				'failed',
				0,
				1,
				'Exception: ' . $e->getMessage()
			);

			delete_transient( 'brag_book_gallery_sync_status' );
			
			return false;
		}
	}

	/**
	 * Sync single case by ID
	 *
	 * @since 3.0.0
	 * @param int $case_id Case ID to sync.
	 * @return bool Success status.
	 */
	public function sync_case( int $case_id ): bool {
		// Start sync logging
		$log_id = $this->database->log_sync_operation( 'single', 'started' );
		
		try {
			// Get API client
			$api_client = $this->get_api_client();
			if ( ! $api_client ) {
				$this->database->update_sync_log( $log_id, 'failed', 0, 1, 'API client unavailable' );
				return false;
			}

			// Fetch case data
			$case_data = $api_client->get_case_by_id( $case_id );
			if ( ! $case_data ) {
				$this->database->update_sync_log( $log_id, 'failed', 0, 1, 'Case not found' );
				return false;
			}

			// Process single case
			$result = $this->process_single_case( $case_data );
			
			$final_status = $result ? 'completed' : 'failed';
			$this->database->update_sync_log(
				$log_id,
				$final_status,
				$result ? 1 : 0,
				$result ? 0 : 1,
				$result ? '' : 'Failed to process case'
			);

			return $result;

		} catch ( \Exception $e ) {
			$this->database->update_sync_log( $log_id, 'failed', 0, 1, 'Exception: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Sync categories and procedures
	 *
	 * @since 3.0.0
	 * @return array Result with success status and message.
	 */
	public function sync_taxonomies(): array {
		try {
			$api_client = $this->get_api_client();
			if ( ! $api_client ) {
				return array( 'success' => false, 'message' => 'API client unavailable' );
			}

			// Sync categories
			$categories = $api_client->get_categories();
			if ( $categories ) {
				foreach ( $categories as $category_data ) {
					$this->sync_category( $category_data );
				}
			}

			// Sync procedures
			$procedures = $api_client->get_procedures();
			if ( $procedures ) {
				foreach ( $procedures as $procedure_data ) {
					$this->sync_procedure( $procedure_data );
				}
			}

			return array( 'success' => true, 'message' => 'Taxonomies synced successfully' );

		} catch ( \Exception $e ) {
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	/**
	 * Sync gallery cases
	 *
	 * @since 3.0.0
	 * @return array Result with processed/failed counts and errors.
	 */
	private function sync_cases(): array {
		$api_client = $this->get_api_client();
		if ( ! $api_client ) {
			return array( 
				'processed' => 0, 
				'failed' => 1, 
				'errors' => array( 'API client unavailable' )
			);
		}

		$processed = 0;
		$failed = 0;
		$errors = array();

		// Get mode manager settings
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$settings = $mode_manager->get_mode_settings();
		$batch_size = $settings['batch_size'] ?? 20;

		try {
			// Get total case count first
			$total_cases = $api_client->get_total_cases();
			$pages = ceil( $total_cases / $batch_size );

			for ( $page = 1; $page <= $pages; $page++ ) {
				$cases = $api_client->get_cases_by_pagination( $page, $batch_size );
				
				if ( ! $cases ) {
					continue;
				}

				foreach ( $cases as $case_data ) {
					try {
						if ( $this->process_single_case( $case_data ) ) {
							$processed++;
						} else {
							$failed++;
							$errors[] = "Failed to process case ID: " . ( $case_data['id'] ?? 'unknown' );
						}
					} catch ( \Exception $e ) {
						$failed++;
						$errors[] = "Exception processing case: " . $e->getMessage();
					}
				}

				// Small delay to prevent API rate limiting
				usleep( 100000 ); // 0.1 second
			}

		} catch ( \Exception $e ) {
			$errors[] = "Exception during case sync: " . $e->getMessage();
			$failed++;
		}

		return array(
			'processed' => $processed,
			'failed' => $failed,
			'errors' => $errors
		);
	}

	/**
	 * Process a single case
	 *
	 * @since 3.0.0
	 * @param array $case_data Case data from API.
	 * @return bool Success status.
	 */
	private function process_single_case( array $case_data ): bool {
		if ( empty( $case_data['id'] ) ) {
			return false;
		}

		$api_case_id = (int) $case_data['id'];
		$api_token = $this->get_current_api_token();
		$property_id = $this->get_current_property_id();

		// Generate sync hash for change detection
		$sync_hash = md5( serialize( $case_data ) );

		// Check if case needs updating
		$existing_hash = $this->database->get_sync_hash( $api_case_id, $api_token );
		if ( $existing_hash === $sync_hash ) {
			// No changes, skip
			return true;
		}

		// Get existing post or create new one
		$post_id = $this->database->get_post_by_case_id( $api_case_id, $api_token );
		
		if ( $post_id ) {
			// Update existing post
			$mapped_data = $this->data_mapper->api_to_post_update( $case_data, $post_id );
		} else {
			// Create new post
			$mapped_data = $this->data_mapper->api_to_post( $case_data );
		}

		if ( ! $mapped_data ) {
			return false;
		}

		// Insert or update post
		if ( $post_id ) {
			$mapped_data['ID'] = $post_id;
			$result = wp_update_post( $mapped_data );
		} else {
			$result = wp_insert_post( $mapped_data );
			$post_id = $result;
		}

		if ( is_wp_error( $result ) || ! $result ) {
			return false;
		}

		// Update post meta
		$this->update_post_meta( $post_id, $case_data );

		// Assign taxonomies
		$this->assign_taxonomies( $post_id, $case_data );

		// Handle images
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$settings = $mode_manager->get_mode_settings();
		
		if ( $settings['import_images'] ?? true ) {
			$this->image_sync->import_case_images( $post_id, $case_data );
		}

		// Update case mapping
		$this->database->map_case_to_post( $api_case_id, $post_id, $api_token, $property_id, $sync_hash );

		return true;
	}

	/**
	 * Update post meta data
	 *
	 * @since 3.0.0
	 * @param int   $post_id Post ID.
	 * @param array $case_data Case data from API.
	 * @return void
	 */
	private function update_post_meta( int $post_id, array $case_data ): void {
		$meta_mappings = array(
			'_brag_case_id' => $case_data['id'],
			'_brag_api_token' => $this->get_current_api_token(),
			'_brag_property_id' => $this->get_current_property_id(),
			'_brag_patient_info' => wp_json_encode( $case_data['patient'] ?? array() ),
			'_brag_procedure_details' => wp_json_encode( $case_data['procedure'] ?? array() ),
			'_brag_seo_data' => wp_json_encode( $case_data['seo'] ?? array() ),
			'_brag_last_synced' => current_time( 'mysql' ),
		);

		// Handle image data
		if ( ! empty( $case_data['photoSets'] ) ) {
			$before_images = array();
			$after_images = array();

			foreach ( $case_data['photoSets'] as $photo_set ) {
				if ( $photo_set['type'] === 'before' ) {
					$before_images = $photo_set['photos'] ?? array();
				} elseif ( $photo_set['type'] === 'after' ) {
					$after_images = $photo_set['photos'] ?? array();
				}
			}

			$meta_mappings['_brag_before_images'] = wp_json_encode( $before_images );
			$meta_mappings['_brag_after_images'] = wp_json_encode( $after_images );
		}

		foreach ( $meta_mappings as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
	}

	/**
	 * Assign taxonomies to post
	 *
	 * @since 3.0.0
	 * @param int   $post_id Post ID.
	 * @param array $case_data Case data from API.
	 * @return void
	 */
	private function assign_taxonomies( int $post_id, array $case_data ): void {
		// Assign procedures
		if ( ! empty( $case_data['procedureIds'] ) ) {
			$procedure_ids = array();
			
			foreach ( $case_data['procedureIds'] as $api_procedure_id ) {
				$term = get_terms( array(
					'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
					'meta_key' => 'brag_procedure_api_id',
					'meta_value' => $api_procedure_id,
					'hide_empty' => false,
				) );

				if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
					$procedure_ids[] = $term[0]->term_id;
				}
			}

			if ( ! empty( $procedure_ids ) ) {
				wp_set_object_terms( $post_id, $procedure_ids, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
			}
		}

		// Assign categories based on procedures
		$this->assign_categories_from_procedures( $post_id );
	}

	/**
	 * Assign categories based on assigned procedures
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function assign_categories_from_procedures( int $post_id ): void {
		$procedures = wp_get_object_terms( $post_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		
		if ( empty( $procedures ) || is_wp_error( $procedures ) ) {
			return;
		}

		$category_mapping = $this->get_procedure_category_mapping();
		$categories_to_assign = array();

		foreach ( $procedures as $procedure ) {
			$procedure_slug = $procedure->slug;
			
			foreach ( $category_mapping as $category_slug => $procedure_patterns ) {
				foreach ( $procedure_patterns as $pattern ) {
					if ( strpos( $procedure_slug, $pattern ) !== false ) {
						$category_term = get_term_by( 'slug', $category_slug, Gallery_Taxonomies::CATEGORY_TAXONOMY );
						if ( $category_term && ! is_wp_error( $category_term ) ) {
							$categories_to_assign[] = $category_term->term_id;
						}
						break 2;
					}
				}
			}
		}

		if ( ! empty( $categories_to_assign ) ) {
			wp_set_object_terms( $post_id, array_unique( $categories_to_assign ), Gallery_Taxonomies::CATEGORY_TAXONOMY );
		}
	}

	/**
	 * Get procedure to category mapping
	 *
	 * @since 3.0.0
	 * @return array Mapping of category slugs to procedure patterns.
	 */
	private function get_procedure_category_mapping(): array {
		return array(
			'breast-procedures' => array( 'breast', 'augmentation', 'reduction', 'lift', 'mastopexy' ),
			'body-contouring' => array( 'tummy', 'abdominoplasty', 'liposuction', 'body-lift', 'arm-lift' ),
			'facial-procedures' => array( 'facelift', 'rhinoplasty', 'eyelid', 'brow-lift', 'neck-lift' ),
			'non-surgical' => array( 'botox', 'filler', 'laser', 'coolsculpting', 'injections' ),
			'reconstructive' => array( 'reconstruction', 'repair', 'scar', 'revision' ),
		);
	}

	/**
	 * Sync single category
	 *
	 * @since 3.0.0
	 * @param array $category_data Category data from API.
	 * @return bool Success status.
	 */
	private function sync_category( array $category_data ): bool {
		if ( empty( $category_data['id'] ) || empty( $category_data['name'] ) ) {
			return false;
		}

		$api_id = (int) $category_data['id'];
		$name = sanitize_text_field( $category_data['name'] );
		$slug = sanitize_title( $name );

		// Check if term already exists
		$existing_term = get_terms( array(
			'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY,
			'meta_key' => 'brag_category_api_id',
			'meta_value' => $api_id,
			'hide_empty' => false,
		) );

		if ( ! empty( $existing_term ) && ! is_wp_error( $existing_term ) ) {
			// Update existing term
			$term_id = $existing_term[0]->term_id;
			wp_update_term( $term_id, Gallery_Taxonomies::CATEGORY_TAXONOMY, array(
				'name' => $name,
				'slug' => $slug,
				'description' => $category_data['description'] ?? '',
			) );
		} else {
			// Create new term
			$result = wp_insert_term( $name, Gallery_Taxonomies::CATEGORY_TAXONOMY, array(
				'slug' => $slug,
				'description' => $category_data['description'] ?? '',
			) );

			if ( is_wp_error( $result ) ) {
				return false;
			}

			$term_id = $result['term_id'];
		}

		// Update term meta
		update_term_meta( $term_id, 'brag_category_api_id', $api_id );
		update_term_meta( $term_id, 'brag_category_order', $category_data['order'] ?? 0 );

		return true;
	}

	/**
	 * Sync single procedure
	 *
	 * @since 3.0.0
	 * @param array $procedure_data Procedure data from API.
	 * @return bool Success status.
	 */
	private function sync_procedure( array $procedure_data ): bool {
		if ( empty( $procedure_data['id'] ) || empty( $procedure_data['name'] ) ) {
			return false;
		}

		$api_id = (int) $procedure_data['id'];
		$name = sanitize_text_field( $procedure_data['name'] );
		$slug = sanitize_title( $procedure_data['slugName'] ?? $name );

		// Check if term already exists
		$existing_term = get_terms( array(
			'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
			'meta_key' => 'brag_procedure_api_id',
			'meta_value' => $api_id,
			'hide_empty' => false,
		) );

		if ( ! empty( $existing_term ) && ! is_wp_error( $existing_term ) ) {
			// Update existing term
			$term_id = $existing_term[0]->term_id;
			wp_update_term( $term_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY, array(
				'name' => $name,
				'slug' => $slug,
				'description' => $procedure_data['description'] ?? '',
			) );
		} else {
			// Create new term
			$result = wp_insert_term( $name, Gallery_Taxonomies::PROCEDURE_TAXONOMY, array(
				'slug' => $slug,
				'description' => $procedure_data['description'] ?? '',
			) );

			if ( is_wp_error( $result ) ) {
				return false;
			}

			$term_id = $result['term_id'];
		}

		// Update term meta
		update_term_meta( $term_id, 'brag_procedure_api_id', $api_id );
		update_term_meta( $term_id, 'brag_procedure_slug_name', $procedure_data['slugName'] ?? '' );
		update_term_meta( $term_id, 'brag_procedure_nudity', ! empty( $procedure_data['nudity'] ) );
		update_term_meta( $term_id, 'brag_procedure_case_count', $procedure_data['caseCount'] ?? 0 );

		return true;
	}

	/**
	 * Schedule sync based on settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function schedule_sync(): void {
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		
		if ( ! $mode_manager->is_local_mode() ) {
			return;
		}

		$settings = $mode_manager->get_mode_settings();
		$frequency = $settings['sync_frequency'] ?? 'manual';

		// Clear existing schedule
		wp_clear_scheduled_hook( 'brag_book_gallery_scheduled_sync' );

		if ( $frequency === 'manual' || ! ( $settings['auto_sync'] ?? false ) ) {
			return;
		}

		$schedules = array(
			'hourly' => 'hourly',
			'daily' => 'daily',
			'weekly' => 'weekly',
		);

		if ( isset( $schedules[ $frequency ] ) ) {
			wp_schedule_event( time(), $schedules[ $frequency ], 'brag_book_gallery_scheduled_sync' );
		}
	}

	/**
	 * Run scheduled sync
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function run_scheduled_sync(): void {
		$this->sync_all();
	}

	/**
	 * Check if sync is currently running
	 *
	 * @since 3.0.0
	 * @return bool True if sync is running.
	 */
	public function is_sync_running(): bool {
		return get_transient( 'brag_book_gallery_sync_status' ) === 'running';
	}

	/**
	 * Get sync status
	 *
	 * @since 3.0.0
	 * @return array Sync status information.
	 */
	public function get_sync_status(): array {
		$is_running = $this->is_sync_running();
		$last_sync = get_option( 'brag_book_gallery_last_sync', '' );
		$stats = $this->database->get_sync_stats();

		return array(
			'is_running' => $is_running,
			'last_sync' => $last_sync,
			'stats' => $stats,
		);
	}

	/**
	 * Cancel running sync
	 *
	 * @since 3.0.0
	 * @return bool Success status.
	 */
	public function cancel_sync(): bool {
		delete_transient( 'brag_book_gallery_sync_status' );
		
		if ( $this->current_sync_log_id ) {
			$this->database->update_sync_log( $this->current_sync_log_id, 'failed', 0, 0, 'Cancelled by user' );
		}

		return true;
	}

	/**
	 * Get API client instance
	 *
	 * @since 3.0.0
	 * @return object|null API client or null if unavailable.
	 */
	private function get_api_client(): ?object {
		// This would normally integrate with the existing API client
		// For now, return a mock that implements the required methods
		return new class {
			public function get_categories(): array {
				// Mock implementation - replace with actual API call
				return array();
			}

			public function get_procedures(): array {
				// Mock implementation - replace with actual API call
				return array();
			}

			public function get_total_cases(): int {
				// Mock implementation - replace with actual API call
				return 0;
			}

			public function get_cases_by_pagination( int $page, int $per_page ): array {
				// Mock implementation - replace with actual API call
				return array();
			}

			public function get_case_by_id( int $case_id ): ?array {
				// Mock implementation - replace with actual API call
				return null;
			}
		};
	}

	/**
	 * Get current API token
	 *
	 * @since 3.0.0
	 * @return string Current API token.
	 */
	private function get_current_api_token(): string {
		// Get from plugin settings
		return get_option( 'brag_book_gallery_api_token', '' );
	}

	/**
	 * Get current property ID
	 *
	 * @since 3.0.0
	 * @return int Current property ID.
	 */
	private function get_current_property_id(): int {
		// Get from plugin settings
		return (int) get_option( 'brag_book_gallery_property_id', 0 );
	}

	/**
	 * AJAX handler for sync now
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_sync_now(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$result = $this->sync_all();
		
		if ( $result ) {
			wp_send_json_success( 'Sync completed successfully.' );
		} else {
			wp_send_json_error( 'Sync failed. Check logs for details.' );
		}
	}

	/**
	 * AJAX handler for sync status
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_sync_status(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_sync_status', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		$status = $this->get_sync_status();
		wp_send_json_success( $status );
	}

	/**
	 * AJAX handler for cancel sync
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_cancel_sync(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_sync_cancel', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$result = $this->cancel_sync();
		
		if ( $result ) {
			wp_send_json_success( 'Sync cancelled successfully.' );
		} else {
			wp_send_json_error( 'Failed to cancel sync.' );
		}
	}

	/**
	 * Register REST API routes
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route( 'brag-book-gallery/v1', '/sync', array(
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_sync_all' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			),
		) );

		register_rest_route( 'brag-book-gallery/v1', '/sync/status', array(
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'rest_get_sync_status' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			),
		) );

		register_rest_route( 'brag-book-gallery/v1', '/sync/cancel', array(
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_cancel_sync' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			),
		) );
	}

	/**
	 * REST API: Sync all
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_sync_all( $request ): \WP_REST_Response {
		$result = $this->sync_all();
		
		if ( $result ) {
			return new \WP_REST_Response( array( 'success' => true ), 200 );
		} else {
			return new \WP_REST_Response( array( 'success' => false ), 400 );
		}
	}

	/**
	 * REST API: Get sync status
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_sync_status( $request ): \WP_REST_Response {
		$status = $this->get_sync_status();
		return new \WP_REST_Response( $status, 200 );
	}

	/**
	 * REST API: Cancel sync
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_cancel_sync( $request ): \WP_REST_Response {
		$result = $this->cancel_sync();
		
		if ( $result ) {
			return new \WP_REST_Response( array( 'success' => true ), 200 );
		} else {
			return new \WP_REST_Response( array( 'success' => false ), 400 );
		}
	}

	/**
	 * REST API: Permission check
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function rest_permission_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}
}