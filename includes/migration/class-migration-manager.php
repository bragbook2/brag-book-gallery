<?php
/**
 * Migration Manager
 *
 * Handles migration between JavaScript and Local modes.
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

use BRAGBookGallery\Includes\Core\Database;
use BRAGBookGallery\Includes\Sync\Sync_Manager;
use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration Manager Class
 *
 * Manages the migration process between different operational modes.
 *
 * @since 3.0.0
 */
class Migration_Manager {

	/**
	 * Database manager instance
	 *
	 * @since 3.0.0
	 * @var Database
	 */
	private $database;

	/**
	 * Sync manager instance
	 *
	 * @since 3.0.0
	 * @var Sync_Manager
	 */
	private $sync_manager;

	/**
	 * Data validator instance
	 *
	 * @since 3.0.0
	 * @var Data_Validator
	 */
	private $data_validator;

	/**
	 * Migration option key
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const MIGRATION_STATUS_OPTION = 'brag_book_gallery_migration_status';

	/**
	 * Backup option key
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const BACKUP_OPTION = 'brag_book_gallery_migration_backup';

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->database = new Database();
		$this->sync_manager = new Sync_Manager();
		$this->data_validator = new Data_Validator();

		$this->init();
	}

	/**
	 * Initialize migration manager
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Add AJAX handlers
		add_action( 'wp_ajax_brag_book_gallery_migrate_to_local', array( $this, 'ajax_migrate_to_local' ) );
		add_action( 'wp_ajax_brag_book_gallery_migrate_to_javascript', array( $this, 'ajax_migrate_to_javascript' ) );
		add_action( 'wp_ajax_brag_book_gallery_migration_status', array( $this, 'ajax_migration_status' ) );
		add_action( 'wp_ajax_brag_book_gallery_rollback', array( $this, 'ajax_rollback' ) );

		// Add REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add hooks for pre and post migration
		add_action( 'brag_book_gallery_pre_migrate_to_local', array( $this, 'create_backup' ) );
		add_action( 'brag_book_gallery_pre_migrate_to_javascript', array( $this, 'create_backup' ) );
	}

	/**
	 * Migrate from JavaScript to Local mode
	 *
	 * @since 3.0.0
	 * @param array $options Migration options.
	 * @return bool Success status.
	 */
	public function migrate_to_local( array $options = array() ): bool {
		$defaults = array(
			'preserve_settings' => true,
			'import_images' => true,
			'cleanup_after' => false,
			'batch_size' => 20,
		);

		$options = wp_parse_args( $options, $defaults );

		// Set migration status
		$this->set_migration_status( 'javascript_to_local', 'running' );

		try {
			// Fire pre-migration hooks
			do_action( 'brag_book_gallery_pre_migrate_to_local', $options );

			// Step 1: Pre-flight checks
			if ( ! $this->pre_flight_checks( 'local' ) ) {
				throw new \Exception( 'Pre-flight checks failed' );
			}

			// Step 2: Create database tables if needed
			$this->database->create_tables();

			// Step 3: Sync all data from API
			$sync_result = $this->sync_manager->sync_all();
			if ( ! $sync_result ) {
				throw new \Exception( 'Failed to sync data from API' );
			}

			// Step 4: Validate migrated data
			$validation_result = $this->data_validator->validate_migration( 'local' );
			if ( ! $validation_result['valid'] ) {
				throw new \Exception( 'Data validation failed: ' . implode( ', ', $validation_result['errors'] ) );
			}

			// Step 5: Update settings
			if ( $options['preserve_settings'] ) {
				$this->preserve_javascript_mode_settings();
			}

			// Step 6: Cleanup if requested
			if ( $options['cleanup_after'] ) {
				$this->cleanup_javascript_mode_data();
			}

			// Update migration status
			$this->set_migration_status( 'javascript_to_local', 'completed' );

			// Fire post-migration hooks
			do_action( 'brag_book_gallery_post_migrate_to_local', $options );

			return true;

		} catch ( \Exception $e ) {
			$this->set_migration_status( 'javascript_to_local', 'failed', $e->getMessage() );
			error_log( 'BRAG book Gallery Migration Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Migrate from Local to JavaScript mode
	 *
	 * @since 3.0.0
	 * @param array $options Migration options.
	 * @return bool Success status.
	 */
	public function migrate_to_javascript( array $options = array() ): bool {
		$defaults = array(
			'preserve_data' => true,
			'archive_posts' => false,
			'keep_images' => true,
		);

		$options = wp_parse_args( $options, $defaults );

		// Set migration status
		$this->set_migration_status( 'local_to_javascript', 'running' );

		try {
			// Fire pre-migration hooks
			do_action( 'brag_book_gallery_pre_migrate_to_javascript', $options );

			// Step 1: Pre-flight checks
			if ( ! $this->pre_flight_checks( 'javascript' ) ) {
				throw new \Exception( 'Pre-flight checks failed' );
			}

			// Step 2: Handle existing data based on options
			if ( $options['preserve_data'] ) {
				if ( $options['archive_posts'] ) {
					$this->archive_local_mode_posts();
				} else {
					// Just hide posts but keep them
					$this->hide_local_mode_posts();
				}
			} else {
				// User chose to delete local data
				$this->cleanup_local_mode_data( $options['keep_images'] );
			}

			// Step 3: Clear sync schedules
			wp_clear_scheduled_hook( 'brag_book_gallery_scheduled_sync' );

			// Step 4: Update settings
			$this->preserve_local_mode_settings();

			// Update migration status
			$this->set_migration_status( 'local_to_javascript', 'completed' );

			// Fire post-migration hooks
			do_action( 'brag_book_gallery_post_migrate_to_javascript', $options );

			return true;

		} catch ( \Exception $e ) {
			$this->set_migration_status( 'local_to_javascript', 'failed', $e->getMessage() );
			error_log( 'BRAG book Gallery Migration Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Rollback migration
	 *
	 * @since 3.0.0
	 * @return bool Success status.
	 */
	public function rollback(): bool {
		$backup = get_option( self::BACKUP_OPTION, array() );

		if ( empty( $backup ) ) {
			return false;
		}

		try {
			// Restore settings
			if ( ! empty( $backup['settings'] ) ) {
				foreach ( $backup['settings'] as $option_name => $option_value ) {
					update_option( $option_name, $option_value );
				}
			}

			// Restore post statuses if needed
			if ( ! empty( $backup['posts'] ) ) {
				foreach ( $backup['posts'] as $post_id => $post_data ) {
					wp_update_post( array(
						'ID' => $post_id,
						'post_status' => $post_data['status'],
					) );
				}
			}

			// Clear backup
			delete_option( self::BACKUP_OPTION );

			// Clear migration status
			delete_option( self::MIGRATION_STATUS_OPTION );

			return true;

		} catch ( \Exception $e ) {
			error_log( 'BRAG book Gallery Rollback Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Export data for backup or transfer
	 *
	 * @since 3.0.0
	 * @return array Exported data.
	 */
	public function export_data(): array {
		$export_data = array(
			'version' => '3.0.0',
			'timestamp' => current_time( 'mysql' ),
			'settings' => $this->export_settings(),
			'posts' => $this->export_posts(),
			'taxonomies' => $this->export_taxonomies(),
			'sync_data' => $this->export_sync_data(),
		);

		return $export_data;
	}

	/**
	 * Import data from backup or transfer
	 *
	 * @since 3.0.0
	 * @param array $data Data to import.
	 * @return bool Success status.
	 */
	public function import_data( array $data ): bool {
		try {
			// Validate import data
			if ( ! $this->validate_import_data( $data ) ) {
				throw new \Exception( 'Invalid import data format' );
			}

			// Import settings
			if ( ! empty( $data['settings'] ) ) {
				$this->import_settings( $data['settings'] );
			}

			// Import taxonomies first
			if ( ! empty( $data['taxonomies'] ) ) {
				$this->import_taxonomies( $data['taxonomies'] );
			}

			// Import posts
			if ( ! empty( $data['posts'] ) ) {
				$this->import_posts( $data['posts'] );
			}

			// Import sync data
			if ( ! empty( $data['sync_data'] ) ) {
				$this->import_sync_data( $data['sync_data'] );
			}

			return true;

		} catch ( \Exception $e ) {
			error_log( 'BRAG book Gallery Import Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Perform pre-flight checks before migration
	 *
	 * @since 3.0.0
	 * @param string $target_mode Target mode for migration.
	 * @return bool True if checks pass.
	 */
	private function pre_flight_checks( string $target_mode ): bool {
		$checks = array();

		// Check WordPress version
		$checks['wp_version'] = version_compare( get_bloginfo( 'version' ), '5.0', '>=' );

		// Check PHP version
		$checks['php_version'] = version_compare( PHP_VERSION, '8.0', '>=' );

		// Check database connectivity
		$checks['database'] = $this->test_database_connection();

		// Check write permissions
		$checks['file_permissions'] = $this->check_file_permissions();

		// Mode-specific checks
		if ( $target_mode === 'local' ) {
			// Check API connectivity
			$checks['api_connectivity'] = $this->test_api_connectivity();

			// Check available storage
			$checks['storage_space'] = $this->check_available_storage();
		}

		// Check if any checks failed
		foreach ( $checks as $check => $result ) {
			if ( ! $result ) {
				error_log( "BRAG book Gallery Pre-flight check failed: {$check}" );
				return false;
			}
		}

		return true;
	}

	/**
	 * Test database connection
	 *
	 * @since 3.0.0
	 * @return bool True if database is accessible.
	 */
	private function test_database_connection(): bool {
		global $wpdb;

		$result = $wpdb->get_var( "SELECT 1" );
		return $result === '1';
	}

	/**
	 * Check file permissions
	 *
	 * @since 3.0.0
	 * @return bool True if permissions are adequate.
	 */
	private function check_file_permissions(): bool {
		$upload_dir = wp_upload_dir();
		return is_writable( $upload_dir['basedir'] );
	}

	/**
	 * Test API connectivity
	 *
	 * @since 3.0.0
	 * @return bool True if API is accessible.
	 */
	private function test_api_connectivity(): bool {
		$api_url = get_option( 'brag_book_gallery_api_url', '' );
		$api_token = get_option( 'brag_book_gallery_api_token', '' );

		if ( empty( $api_url ) || empty( $api_token ) ) {
			return false;
		}

		$response = wp_remote_get( $api_url . '/test', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
			),
			'timeout' => 10,
		) );

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Check available storage space
	 *
	 * @since 3.0.0
	 * @return bool True if sufficient space is available.
	 */
	private function check_available_storage(): bool {
		$upload_dir = wp_upload_dir();
		$free_bytes = disk_free_space( $upload_dir['basedir'] );

		// Require at least 1GB free space
		$required_bytes = 1073741824; // 1GB

		return $free_bytes > $required_bytes;
	}

	/**
	 * Create backup before migration
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function create_backup(): void {
		$backup_data = array(
			'timestamp' => current_time( 'mysql' ),
			'settings' => $this->get_current_settings(),
			'posts' => $this->get_current_post_statuses(),
		);

		update_option( self::BACKUP_OPTION, $backup_data );
	}

	/**
	 * Get current plugin settings
	 *
	 * @since 3.0.0
	 * @return array Current settings.
	 */
	private function get_current_settings(): array {
		$settings = array();

		$option_keys = array(
			'brag_book_gallery_mode',
			'brag_book_gallery_mode_settings',
			'brag_book_gallery_api_url',
			'brag_book_gallery_api_token',
			'brag_book_gallery_property_id',
		);

		foreach ( $option_keys as $key ) {
			$settings[ $key ] = get_option( $key );
		}

		return $settings;
	}

	/**
	 * Get current post statuses
	 *
	 * @since 3.0.0
	 * @return array Post statuses.
	 */
	private function get_current_post_statuses(): array {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		$statuses = array();

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			$statuses[ $post_id ] = array(
				'status' => $post->post_status,
			);
		}

		return $statuses;
	}

	/**
	 * Set migration status
	 *
	 * @since 3.0.0
	 * @param string $type Migration type.
	 * @param string $status Migration status.
	 * @param string $message Optional status message.
	 * @return void
	 */
	private function set_migration_status( string $type, string $status, string $message = '' ): void {
		$status_data = array(
			'type' => $type,
			'status' => $status,
			'message' => $message,
			'timestamp' => current_time( 'mysql' ),
		);

		update_option( self::MIGRATION_STATUS_OPTION, $status_data );

		// Set transient for real-time status checking
		set_transient( 'brag_book_gallery_migration_status', $status, HOUR_IN_SECONDS );
	}

	/**
	 * Get migration status
	 *
	 * @since 3.0.0
	 * @return array Migration status data.
	 */
	public function get_migration_status(): array {
		return get_option( self::MIGRATION_STATUS_OPTION, array(
			'type' => '',
			'status' => 'idle',
			'message' => '',
			'timestamp' => '',
		) );
	}

	/**
	 * Preserve JavaScript mode settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function preserve_javascript_mode_settings(): void {
		$js_settings = array(
			'api_url' => get_option( 'brag_book_gallery_api_url', '' ),
			'api_token' => get_option( 'brag_book_gallery_api_token', '' ),
			'property_id' => get_option( 'brag_book_gallery_property_id', 0 ),
			'cache_duration' => get_option( 'brag_book_gallery_cache_duration', 300 ),
		);

		update_option( 'brag_book_gallery_preserved_js_settings', $js_settings );
	}

	/**
	 * Preserve Local mode settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function preserve_local_mode_settings(): void {
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$local_settings = $mode_manager->get_mode_settings( 'local' );

		update_option( 'brag_book_gallery_preserved_local_settings', $local_settings );
	}

	/**
	 * Archive local mode posts
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function archive_local_mode_posts(): void {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
		) );

		foreach ( $posts as $post ) {
			wp_update_post( array(
				'ID' => $post->ID,
				'post_status' => 'draft',
			) );

			// Add meta to indicate this was archived during migration
			update_post_meta( $post->ID, '_brag_migration_archived', current_time( 'mysql' ) );
		}
	}

	/**
	 * Hide local mode posts
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function hide_local_mode_posts(): void {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
		) );

		foreach ( $posts as $post ) {
			// Add meta to hide from queries but keep published
			update_post_meta( $post->ID, '_brag_hidden_for_js_mode', '1' );
		}
	}

	/**
	 * Cleanup JavaScript mode data
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function cleanup_javascript_mode_data(): void {
		// Clear transients and caches
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_brag_book_gallery_%'
			 OR option_name LIKE '_transient_timeout_brag_book_gallery_%'"
		);

		// Clear object cache
		wp_cache_flush();
	}

	/**
	 * Cleanup local mode data
	 *
	 * @since 3.0.0
	 * @param bool $keep_images Whether to keep imported images.
	 * @return void
	 */
	private function cleanup_local_mode_data( bool $keep_images = true ): void {
		// Get all gallery posts
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		// Handle images first if we're keeping them
		if ( $keep_images ) {
			foreach ( $posts as $post ) {
				// Detach images instead of deleting
				$attachments = get_attached_media( 'image', $post->ID );
				foreach ( $attachments as $attachment ) {
					wp_update_post( array(
						'ID' => $attachment->ID,
						'post_parent' => 0,
					) );
				}
			}
		}

		// Delete posts
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Clean up taxonomy terms that have no posts
		$this->cleanup_empty_terms();

		// Clean up sync data
		$this->database->drop_tables();
	}

	/**
	 * Cleanup empty taxonomy terms
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function cleanup_empty_terms(): void {
		$taxonomies = array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			) );

			foreach ( $terms as $term ) {
				if ( $term->count === 0 ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
			}
		}
	}

	/**
	 * Export plugin settings
	 *
	 * @since 3.0.0
	 * @return array Settings data.
	 */
	private function export_settings(): array {
		return $this->get_current_settings();
	}

	/**
	 * Export posts data
	 *
	 * @since 3.0.0
	 * @return array Posts data.
	 */
	private function export_posts(): array {
		$posts = get_posts( array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
		) );

		$export_posts = array();

		foreach ( $posts as $post ) {
			$post_data = array(
				'ID' => $post->ID,
				'title' => $post->post_title,
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
				'status' => $post->post_status,
				'slug' => $post->post_name,
				'date' => $post->post_date,
				'meta' => get_post_meta( $post->ID ),
				'terms' => array(
					'categories' => wp_get_object_terms( $post->ID, Gallery_Taxonomies::CATEGORY_TAXONOMY, array( 'fields' => 'ids' ) ),
					'procedures' => wp_get_object_terms( $post->ID, Gallery_Taxonomies::PROCEDURE_TAXONOMY, array( 'fields' => 'ids' ) ),
				),
			);

			$export_posts[] = $post_data;
		}

		return $export_posts;
	}

	/**
	 * Export taxonomies data
	 *
	 * @since 3.0.0
	 * @return array Taxonomies data.
	 */
	private function export_taxonomies(): array {
		$taxonomies = array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		$export_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			) );

			$export_terms = array();

			foreach ( $terms as $term ) {
				$term_data = array(
					'term_id' => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
					'description' => $term->description,
					'parent' => $term->parent,
					'meta' => get_term_meta( $term->term_id ),
				);

				$export_terms[] = $term_data;
			}

			$export_taxonomies[ $taxonomy ] = $export_terms;
		}

		return $export_taxonomies;
	}

	/**
	 * Export sync data
	 *
	 * @since 3.0.0
	 * @return array Sync data.
	 */
	private function export_sync_data(): array {
		return array(
			'sync_logs' => $this->database->get_recent_sync_logs( 100 ),
			'sync_stats' => $this->database->get_sync_stats(),
		);
	}

	/**
	 * Import settings
	 *
	 * @since 3.0.0
	 * @param array $settings Settings to import.
	 * @return void
	 */
	private function import_settings( array $settings ): void {
		foreach ( $settings as $option_name => $option_value ) {
			update_option( $option_name, $option_value );
		}
	}

	/**
	 * Import posts
	 *
	 * @since 3.0.0
	 * @param array $posts Posts to import.
	 * @return void
	 */
	private function import_posts( array $posts ): void {
		foreach ( $posts as $post_data ) {
			$post_args = array(
				'post_type' => Gallery_Post_Type::POST_TYPE,
				'post_title' => $post_data['title'],
				'post_content' => $post_data['content'],
				'post_excerpt' => $post_data['excerpt'],
				'post_status' => $post_data['status'],
				'post_name' => $post_data['slug'],
				'post_date' => $post_data['date'],
			);

			$post_id = wp_insert_post( $post_args );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Import meta
				if ( ! empty( $post_data['meta'] ) ) {
					foreach ( $post_data['meta'] as $meta_key => $meta_values ) {
						foreach ( $meta_values as $meta_value ) {
							add_post_meta( $post_id, $meta_key, $meta_value );
						}
					}
				}

				// Import terms
				if ( ! empty( $post_data['terms']['categories'] ) ) {
					wp_set_object_terms( $post_id, $post_data['terms']['categories'], Gallery_Taxonomies::CATEGORY_TAXONOMY );
				}

				if ( ! empty( $post_data['terms']['procedures'] ) ) {
					wp_set_object_terms( $post_id, $post_data['terms']['procedures'], Gallery_Taxonomies::PROCEDURE_TAXONOMY );
				}
			}
		}
	}

	/**
	 * Import taxonomies
	 *
	 * @since 3.0.0
	 * @param array $taxonomies Taxonomies to import.
	 * @return void
	 */
	private function import_taxonomies( array $taxonomies ): void {
		foreach ( $taxonomies as $taxonomy => $terms ) {
			foreach ( $terms as $term_data ) {
				$term_args = array(
					'slug' => $term_data['slug'],
					'description' => $term_data['description'],
					'parent' => $term_data['parent'],
				);

				$result = wp_insert_term( $term_data['name'], $taxonomy, $term_args );

				if ( ! is_wp_error( $result ) ) {
					$term_id = $result['term_id'];

					// Import term meta
					if ( ! empty( $term_data['meta'] ) ) {
						foreach ( $term_data['meta'] as $meta_key => $meta_values ) {
							foreach ( $meta_values as $meta_value ) {
								add_term_meta( $term_id, $meta_key, $meta_value );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Import sync data
	 *
	 * @since 3.0.0
	 * @param array $sync_data Sync data to import.
	 * @return void
	 */
	private function import_sync_data( array $sync_data ): void {
		// This is primarily for reference/debugging
		// The actual sync data will be rebuilt during the next sync
		if ( ! empty( $sync_data['sync_stats'] ) ) {
			update_option( 'brag_book_gallery_imported_sync_stats', $sync_data['sync_stats'] );
		}
	}

	/**
	 * Validate import data format
	 *
	 * @since 3.0.0
	 * @param array $data Import data to validate.
	 * @return bool True if data is valid.
	 */
	private function validate_import_data( array $data ): bool {
		$required_keys = array( 'version', 'timestamp' );

		foreach ( $required_keys as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				return false;
			}
		}

		// Check version compatibility
		if ( version_compare( $data['version'], '3.0.0', '>' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * AJAX handler for migrate to local
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_migrate_to_local(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_migration', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$options = array(
			'preserve_settings' => ! empty( $_POST['preserve_settings'] ),
			'import_images' => ! empty( $_POST['import_images'] ),
			'cleanup_after' => ! empty( $_POST['cleanup_after'] ),
		);

		$result = $this->migrate_to_local( $options );

		if ( $result ) {
			wp_send_json_success( 'Migration to Local mode completed successfully.' );
		} else {
			wp_send_json_error( 'Migration failed. Check logs for details.' );
		}
	}

	/**
	 * AJAX handler for migrate to javascript
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_migrate_to_javascript(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_migration', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$options = array(
			'preserve_data' => ! empty( $_POST['preserve_data'] ),
			'archive_posts' => ! empty( $_POST['archive_posts'] ),
			'keep_images' => ! empty( $_POST['keep_images'] ),
		);

		$result = $this->migrate_to_javascript( $options );

		if ( $result ) {
			wp_send_json_success( 'Migration to JavaScript mode completed successfully.' );
		} else {
			wp_send_json_error( 'Migration failed. Check logs for details.' );
		}
	}

	/**
	 * AJAX handler for migration status
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_migration_status(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_migration_status', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		$status = $this->get_migration_status();
		wp_send_json_success( $status );
	}

	/**
	 * AJAX handler for rollback
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_rollback(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_rollback', 'nonce', false ) ) {
			wp_send_json_error( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$result = $this->rollback();

		if ( $result ) {
			wp_send_json_success( 'Rollback completed successfully.' );
		} else {
			wp_send_json_error( 'Rollback failed or no backup available.' );
		}
	}

	/**
	 * Register REST API routes
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route( 'brag-book-gallery/v1', '/migrate/to-local', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_migrate_to_local' ),
			'permission_callback' => array( $this, 'rest_permission_check' ),
		) );

		register_rest_route( 'brag-book-gallery/v1', '/migrate/to-javascript', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_migrate_to_javascript' ),
			'permission_callback' => array( $this, 'rest_permission_check' ),
		) );

		register_rest_route( 'brag-book-gallery/v1', '/migrate/status', array(
			'methods' => 'GET',
			'callback' => array( $this, 'rest_get_migration_status' ),
			'permission_callback' => array( $this, 'rest_permission_check' ),
		) );
	}

	/**
	 * REST API: Migrate to local
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_migrate_to_local( $request ): \WP_REST_Response {
		$options = $request->get_json_params() ?: array();
		$result = $this->migrate_to_local( $options );

		if ( $result ) {
			return new \WP_REST_Response( array( 'success' => true ), 200 );
		} else {
			return new \WP_REST_Response( array( 'success' => false ), 400 );
		}
	}

	/**
	 * REST API: Migrate to javascript
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_migrate_to_javascript( $request ): \WP_REST_Response {
		$options = $request->get_json_params() ?: array();
		$result = $this->migrate_to_javascript( $options );

		if ( $result ) {
			return new \WP_REST_Response( array( 'success' => true ), 200 );
		} else {
			return new \WP_REST_Response( array( 'success' => false ), 400 );
		}
	}

	/**
	 * REST API: Get migration status
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_migration_status( $request ): \WP_REST_Response {
		$status = $this->get_migration_status();
		return new \WP_REST_Response( $status, 200 );
	}

	/**
	 * REST API: Permission check
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function rest_permission_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}
}
