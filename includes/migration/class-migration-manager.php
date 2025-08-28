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
 * Enterprise-grade migration system for transitioning between JavaScript and Local operational modes
 * within the BRAG Book Gallery plugin ecosystem. Provides comprehensive data validation, backup
 * capabilities, pre-flight checks, and rollback functionality for safe mode transitions.
 *
 * ## Key Features
 * - **Bidirectional Migration**: Seamlessly migrate between JavaScript and Local modes
 * - **Data Integrity**: Comprehensive validation and backup systems
 * - **Pre-flight Checks**: System compatibility and resource availability validation
 * - **Rollback Support**: Complete rollback capabilities with backup restoration
 * - **Import/Export**: Full data export and import functionality for transfers
 * - **AJAX & REST APIs**: Multiple integration endpoints for frontend/backend operations
 * - **WordPress VIP Compliant**: Follows WordPress VIP coding standards and logging practices
 *
 * ## Migration Types
 * - **JavaScript to Local**: Migrates from API-driven to database-driven architecture
 * - **Local to JavaScript**: Transitions from database storage back to API-driven mode
 *
 * ## Architecture
 * - Utilizes match expressions for efficient conditional logic (PHP 8.2+)
 * - Employs typed properties for enhanced type safety
 * - Implements comprehensive error handling with structured logging
 * - Supports batch operations for performance optimization
 * - Integrates with WordPress transient system for status management
 *
 * ## Usage Example
 * ```php
 * $migration_manager = new Migration_Manager();
 * 
 * // Migrate to Local mode with custom options
 * $success = $migration_manager->migrate_to_local([
 *     'preserve_settings' => true,
 *     'import_images' => true,
 *     'cleanup_after' => false,
 *     'batch_size' => 50,
 * ]);
 * 
 * if (!$success) {
 *     $status = $migration_manager->get_migration_status();
 *     error_log('Migration failed: ' . $status['message']);
 * }
 * ```
 *
 * ## Dependencies
 * - Database: Core database operations and table management
 * - Sync_Manager: API synchronization and data fetching
 * - Data_Validator: Migration data validation and integrity checks
 * - Gallery_Post_Type: Custom post type definitions
 * - Gallery_Taxonomies: Custom taxonomy definitions
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Migration
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */
class Migration_Manager {

	/**
	 * Database manager instance
	 *
	 * @since 3.0.0
	 */
	private Database $database;

	/**
	 * Sync manager instance
	 *
	 * @since 3.0.0
	 */
	private Sync_Manager $sync_manager;

	/**
	 * Data validator instance
	 *
	 * @since 3.0.0
	 */
	private Data_Validator $data_validator;

	/**
	 * Migration configuration constants
	 *
	 * @since 3.0.0
	 */
	private const MIGRATION_STATUS_OPTION = 'brag_book_gallery_migration_status';
	private const BACKUP_OPTION = 'brag_book_gallery_migration_backup';

	/**
	 * Migration type constants
	 *
	 * @since 3.0.0
	 */
	private const MIGRATION_TYPES = [
		'JAVASCRIPT_TO_LOCAL' => 'javascript_to_local',
		'LOCAL_TO_JAVASCRIPT' => 'local_to_javascript',
	];

	/**
	 * Migration status constants
	 *
	 * @since 3.0.0
	 */
	private const MIGRATION_STATUSES = [
		'IDLE' => 'idle',
		'RUNNING' => 'running',
		'COMPLETED' => 'completed',
		'FAILED' => 'failed',
	];

	/**
	 * Default migration options
	 *
	 * @since 3.0.0
	 */
	private const DEFAULT_LOCAL_OPTIONS = [
		'preserve_settings' => true,
		'import_images' => true,
		'cleanup_after' => false,
		'batch_size' => 20,
	];

	private const DEFAULT_JAVASCRIPT_OPTIONS = [
		'preserve_data' => true,
		'archive_posts' => false,
		'keep_images' => true,
	];

	/**
	 * Pre-flight check constants
	 *
	 * @since 3.0.0
	 */
	private const MIN_WORDPRESS_VERSION = '5.0';
	private const MIN_PHP_VERSION = '8.0';
	private const MIN_STORAGE_BYTES = 1073741824; // 1GB

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
	 * Performs a comprehensive migration from API-driven JavaScript mode to database-driven Local mode.
	 * This process includes data synchronization, validation, settings preservation, and optional cleanup.
	 *
	 * @since 3.0.0
	 * @param array{preserve_settings?: bool, import_images?: bool, cleanup_after?: bool, batch_size?: int} $options Migration configuration options
	 * @return bool True on successful migration, false on failure (check logs for details)
	 *
	 * @throws \Exception When pre-flight checks fail, sync operations fail, or data validation fails
	 */
	public function migrate_to_local( array $options = [] ): bool {
		$options = wp_parse_args( $options, self::DEFAULT_LOCAL_OPTIONS );

		// Validate migration options
		$options_validation = $this->validate_local_migration_options( $options );
		if ( ! $options_validation['valid'] ) {
			$this->log_migration_error( 'invalid_migration_options', 'Invalid migration options provided', [
				'errors' => $options_validation['errors'],
				'options' => $options,
			] );
			return false;
		}

		// Validate system preconditions
		$precondition_validation = $this->validate_migration_preconditions( self::MIGRATION_TYPES['JAVASCRIPT_TO_LOCAL'] );
		if ( ! $precondition_validation['valid'] ) {
			$this->log_migration_error( 'migration_preconditions_failed', 'Migration preconditions not met', [
				'errors' => $precondition_validation['errors'],
			] );
			return false;
		}

		// Set migration status
		$this->set_migration_status( self::MIGRATION_TYPES['JAVASCRIPT_TO_LOCAL'], self::MIGRATION_STATUSES['RUNNING'] );

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
			$this->set_migration_status( self::MIGRATION_TYPES['JAVASCRIPT_TO_LOCAL'], self::MIGRATION_STATUSES['COMPLETED'] );

			// Fire post-migration hooks
			do_action( 'brag_book_gallery_post_migrate_to_local', $options );

			return true;

		} catch ( \Exception $e ) {
			$this->set_migration_status( self::MIGRATION_TYPES['JAVASCRIPT_TO_LOCAL'], self::MIGRATION_STATUSES['FAILED'], $e->getMessage() );
			$this->handle_migration_exception( $e, 'migrate_to_local', [
				'migration_type' => self::MIGRATION_TYPES['JAVASCRIPT_TO_LOCAL'],
				'options' => $options,
			] );
			return false;
		}
	}

	/**
	 * Migrate from Local to JavaScript mode
	 *
	 * Transitions from database-driven Local mode back to API-driven JavaScript mode.
	 * Handles data preservation, archival, or cleanup based on configuration options.
	 *
	 * @since 3.0.0
	 * @param array{preserve_data?: bool, archive_posts?: bool, keep_images?: bool} $options Migration configuration options
	 * @return bool True on successful migration, false on failure (check logs for details)
	 *
	 * @throws \Exception When pre-flight checks fail or data handling operations fail
	 */
	public function migrate_to_javascript( array $options = [] ): bool {
		$options = wp_parse_args( $options, self::DEFAULT_JAVASCRIPT_OPTIONS );

		// Validate migration options
		$options_validation = $this->validate_javascript_migration_options( $options );
		if ( ! $options_validation['valid'] ) {
			$this->log_migration_error( 'invalid_migration_options', 'Invalid migration options provided', [
				'errors' => $options_validation['errors'],
				'options' => $options,
			] );
			return false;
		}

		// Validate system preconditions
		$precondition_validation = $this->validate_migration_preconditions( self::MIGRATION_TYPES['LOCAL_TO_JAVASCRIPT'] );
		if ( ! $precondition_validation['valid'] ) {
			$this->log_migration_error( 'migration_preconditions_failed', 'Migration preconditions not met', [
				'errors' => $precondition_validation['errors'],
			] );
			return false;
		}

		// Set migration status
		$this->set_migration_status( self::MIGRATION_TYPES['LOCAL_TO_JAVASCRIPT'], self::MIGRATION_STATUSES['RUNNING'] );

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
			$this->set_migration_status( self::MIGRATION_TYPES['LOCAL_TO_JAVASCRIPT'], self::MIGRATION_STATUSES['COMPLETED'] );

			// Fire post-migration hooks
			do_action( 'brag_book_gallery_post_migrate_to_javascript', $options );

			return true;

		} catch ( \Exception $e ) {
			$this->set_migration_status( self::MIGRATION_TYPES['LOCAL_TO_JAVASCRIPT'], self::MIGRATION_STATUSES['FAILED'], $e->getMessage() );
			$this->handle_migration_exception( $e, 'migrate_to_javascript', [
				'migration_type' => self::MIGRATION_TYPES['LOCAL_TO_JAVASCRIPT'],
				'options' => $options,
			] );
			return false;
		}
	}

	/**
	 * Rollback migration to previous state
	 *
	 * Restores plugin settings and post statuses from backup data created during migration.
	 * This operation is only available if a backup exists from a previous migration.
	 *
	 * @since 3.0.0
	 * @return bool True if rollback succeeded, false if no backup available or rollback failed
	 *
	 * @throws \Exception When backup restoration operations fail
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
	 * Export comprehensive plugin data for backup or transfer
	 *
	 * Creates a complete data export including settings, posts, taxonomies, and sync data.
	 * Useful for creating backups before migrations or transferring data between installations.
	 *
	 * @since 3.0.0
	 * @return array{version: string, timestamp: string, settings: array<string, mixed>, posts: array<int, array>, taxonomies: array<string, array>, sync_data: array<string, mixed>} Complete plugin data export
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
	 * Import data from backup or transfer file
	 *
	 * Restores plugin data from a previously exported backup. Validates data format
	 * and version compatibility before proceeding with import operations.
	 *
	 * @since 3.0.0
	 * @param array{version: string, timestamp: string, settings?: array, posts?: array, taxonomies?: array, sync_data?: array} $data Exported data to import
	 * @return bool True if import succeeded, false if validation failed or import errors occurred
	 *
	 * @throws \Exception When data validation fails or import operations encounter errors
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
		$checks = [];

		// Check WordPress version
		$checks['wp_version'] = version_compare( get_bloginfo( 'version' ), self::MIN_WORDPRESS_VERSION, '>=' );

		// Check PHP version
		$checks['php_version'] = version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );

		// Check database connectivity
		$checks['database'] = $this->test_database_connection();

		// Check write permissions
		$checks['file_permissions'] = $this->check_file_permissions();

		// Mode-specific checks using match expression
		$additional_checks = match ( $target_mode ) {
			'local' => [
				'api_connectivity' => $this->test_api_connectivity(),
				'storage_space' => $this->check_available_storage(),
			],
			'javascript' => [],
			default => [],
		};

		$checks = array_merge( $checks, $additional_checks );

		// Check if any checks failed
		foreach ( $checks as $check => $result ) {
			if ( ! $result ) {
				$this->log_migration_error( 'preflight_check_failed', "Pre-flight check failed: {$check}", [
					'operation' => 'pre_flight_checks',
					'failed_check' => $check,
					'target_mode' => $target_mode,
				] );
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
		$backup_data = [
			'timestamp' => current_time( 'mysql' ),
			'settings' => $this->get_current_settings(),
			'posts' => $this->get_current_post_statuses(),
		];

		update_option( self::BACKUP_OPTION, $backup_data );
	}

	/**
	 * Get current plugin settings
	 *
	 * @since 3.0.0
	 * @return array Current settings.
	 */
	private function get_current_settings(): array {
		$settings = [];

		$option_keys = [
			'brag_book_gallery_mode',
			'brag_book_gallery_mode_settings',
			'brag_book_gallery_api_url',
			'brag_book_gallery_api_token',
			'brag_book_gallery_property_id',
		];

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
		$posts = get_posts( [
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		] );

		$statuses = [];

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
		$status_data = [
			'type' => $type,
			'status' => $status,
			'message' => $message,
			'timestamp' => current_time( 'mysql' ),
		];

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
		return get_option( self::MIGRATION_STATUS_OPTION, [
			'type' => '',
			'status' => self::MIGRATION_STATUSES['IDLE'],
			'message' => '',
			'timestamp' => '',
		] );
	}

	/**
	 * Preserve JavaScript mode settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function preserve_javascript_mode_settings(): void {
		$js_settings = [
			'api_url' => get_option( 'brag_book_gallery_api_url', '' ),
			'api_token' => get_option( 'brag_book_gallery_api_token', '' ),
			'property_id' => get_option( 'brag_book_gallery_property_id', 0 ),
			'cache_duration' => get_option( 'brag_book_gallery_cache_duration', 300 ),
		];

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
		$posts = get_posts( [
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $posts as $post ) {
			wp_update_post( [
				'ID' => $post->ID,
				'post_status' => 'draft',
			] );

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
		$taxonomies = [ Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY ];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( [
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			] );

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
			$terms = get_terms( [
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
			] );

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

	/**
	 * Log migration errors using WordPress VIP compliant logging
	 *
	 * @since 3.0.0
	 * @param string $error_code Unique error code for tracking
	 * @param string $message Error message
	 * @param array<string, mixed> $context Additional context for debugging
	 * @return void
	 */
	private function log_migration_error( string $error_code, string $message, array $context = [] ): void {
		do_action( 'qm/debug', 'BRAG Book Gallery Migration Error: ' . $message, [
			'error_code' => $error_code,
			'message' => $message,
			'context' => $context,
			'timestamp' => current_time( 'mysql' ),
			'class' => __CLASS__,
		] );
	}

	/**
	 * Validate migration options for JavaScript to Local migration
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $options Options to validate
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_local_migration_options( array $options ): array {
		$errors = [];

		// Validate preserve_settings
		if ( isset( $options['preserve_settings'] ) && ! is_bool( $options['preserve_settings'] ) ) {
			$errors[] = 'preserve_settings must be a boolean value';
		}

		// Validate import_images
		if ( isset( $options['import_images'] ) && ! is_bool( $options['import_images'] ) ) {
			$errors[] = 'import_images must be a boolean value';
		}

		// Validate cleanup_after
		if ( isset( $options['cleanup_after'] ) && ! is_bool( $options['cleanup_after'] ) ) {
			$errors[] = 'cleanup_after must be a boolean value';
		}

		// Validate batch_size
		if ( isset( $options['batch_size'] ) ) {
			if ( ! is_int( $options['batch_size'] ) || $options['batch_size'] < 1 || $options['batch_size'] > 1000 ) {
				$errors[] = 'batch_size must be an integer between 1 and 1000';
			}
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Validate migration options for Local to JavaScript migration
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $options Options to validate
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_javascript_migration_options( array $options ): array {
		$errors = [];

		// Validate preserve_data
		if ( isset( $options['preserve_data'] ) && ! is_bool( $options['preserve_data'] ) ) {
			$errors[] = 'preserve_data must be a boolean value';
		}

		// Validate archive_posts
		if ( isset( $options['archive_posts'] ) && ! is_bool( $options['archive_posts'] ) ) {
			$errors[] = 'archive_posts must be a boolean value';
		}

		// Validate keep_images
		if ( isset( $options['keep_images'] ) && ! is_bool( $options['keep_images'] ) ) {
			$errors[] = 'keep_images must be a boolean value';
		}

		// Validate conflicting options
		if ( isset( $options['preserve_data'] ) && $options['preserve_data'] === false && 
			 isset( $options['archive_posts'] ) && $options['archive_posts'] === true ) {
			$errors[] = 'archive_posts cannot be true when preserve_data is false';
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Validate system requirements and pre-conditions
	 *
	 * @since 3.0.0
	 * @param string $migration_type Type of migration being performed
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_migration_preconditions( string $migration_type ): array {
		$errors = [];

		// Check if another migration is currently running
		$current_status = $this->get_migration_status();
		if ( $current_status['status'] === self::MIGRATION_STATUSES['RUNNING'] ) {
			$errors[] = 'Another migration is currently in progress';
		}

		// Validate migration type
		if ( ! in_array( $migration_type, self::MIGRATION_TYPES, true ) ) {
			$errors[] = 'Invalid migration type specified';
		}

		// Check WordPress memory limit
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$min_memory = 128 * 1024 * 1024; // 128MB
		if ( $memory_limit < $min_memory ) {
			$errors[] = 'Insufficient memory limit. Minimum 128MB required.';
		}

		// Check PHP execution time limit for long operations
		$max_execution_time = intval( ini_get( 'max_execution_time' ) );
		if ( $max_execution_time > 0 && $max_execution_time < 300 ) {
			$errors[] = 'PHP max_execution_time should be at least 300 seconds for migrations';
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Enhanced exception handling with context preservation
	 *
	 * @since 3.0.0
	 * @param \Exception $exception The caught exception
	 * @param string $operation The operation that failed
	 * @param array<string, mixed> $context Additional context
	 * @return void
	 */
	private function handle_migration_exception( \Exception $exception, string $operation, array $context = [] ): void {
		$error_context = array_merge( $context, [
			'operation' => $operation,
			'exception_type' => get_class( $exception ),
			'exception_code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		] );

		$this->log_migration_error(
			strtolower( str_replace( ' ', '_', $operation ) ) . '_exception',
			$exception->getMessage(),
			$error_context
		);
	}

	/**
	 * Sanitize migration options for security
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $options Raw options to sanitize
	 * @param array<string> $allowed_keys List of allowed option keys
	 * @return array<string, mixed> Sanitized options
	 */
	private function sanitize_migration_options( array $options, array $allowed_keys ): array {
		$sanitized = [];

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $options[ $key ] ) ) {
				continue;
			}

			$value = $options[ $key ];

			// Sanitize based on expected type and key
			switch ( $key ) {
				case 'preserve_settings':
				case 'import_images':
				case 'cleanup_after':
				case 'preserve_data':
				case 'archive_posts':
				case 'keep_images':
					$sanitized[ $key ] = (bool) $value;
					break;

				case 'batch_size':
					$sanitized[ $key ] = max( 1, min( 1000, intval( $value ) ) );
					break;

				default:
					// For unknown keys, apply basic sanitization
					if ( is_string( $value ) ) {
						$sanitized[ $key ] = sanitize_text_field( $value );
					} elseif ( is_numeric( $value ) ) {
						$sanitized[ $key ] = floatval( $value );
					} else {
						$sanitized[ $key ] = $value;
					}
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate nonce and user permissions for AJAX requests
	 *
	 * @since 3.0.0
	 * @param string $nonce_action Nonce action to verify
	 * @param string $capability Required user capability
	 * @return array{valid: bool, error?: string} Validation result
	 */
	private function validate_ajax_security( string $nonce_action, string $capability = 'manage_options' ): array {
		// Check nonce
		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			return [
				'valid' => false,
				'error' => 'Security check failed - invalid nonce.',
			];
		}

		// Check user capability
		if ( ! current_user_can( $capability ) ) {
			return [
				'valid' => false,
				'error' => 'Insufficient permissions.',
			];
		}

		return [ 'valid' => true ];
	}

	/**
	 * Rate limiting for migration operations
	 *
	 * @since 3.0.0
	 * @param string $operation Operation type
	 * @param int $limit Maximum attempts within time window
	 * @param int $window Time window in seconds
	 * @return bool True if within limits, false if rate limited
	 */
	private function check_rate_limit( string $operation, int $limit = 3, int $window = 300 ): bool {
		$transient_key = 'brag_book_migration_rate_limit_' . md5( $operation . get_current_user_id() );
		$attempts = get_transient( $transient_key );

		if ( $attempts === false ) {
			// First attempt within window
			set_transient( $transient_key, 1, $window );
			return true;
		}

		if ( $attempts >= $limit ) {
			$this->log_migration_error( 'rate_limit_exceeded', 'Rate limit exceeded for operation: ' . $operation, [
				'operation' => $operation,
				'attempts' => $attempts,
				'limit' => $limit,
				'user_id' => get_current_user_id(),
			] );
			return false;
		}

		// Increment attempt counter
		set_transient( $transient_key, $attempts + 1, $window );
		return true;
	}

	/**
	 * Validate import data structure and prevent malicious content
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $data Import data to validate
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_import_data_security( array $data ): array {
		$errors = [];

		// Check data size limits
		$serialized_size = strlen( maybe_serialize( $data ) );
		$max_size = 50 * 1024 * 1024; // 50MB
		if ( $serialized_size > $max_size ) {
			$errors[] = 'Import data exceeds maximum allowed size of 50MB';
		}

		// Validate required structure
		if ( ! isset( $data['version'] ) || ! is_string( $data['version'] ) ) {
			$errors[] = 'Missing or invalid version information';
		}

		// Check for suspicious patterns in data
		$suspicious_patterns = [
			'/(<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>)/mi',
			'/javascript:/i',
			'/vbscript:/i',
			'/data:.*base64/i',
		];

		$data_string = maybe_serialize( $data );
		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $data_string ) ) {
				$errors[] = 'Import data contains potentially malicious content';
				break;
			}
		}

		// Validate nested array depth to prevent memory exhaustion
		if ( $this->get_array_depth( $data ) > 10 ) {
			$errors[] = 'Import data structure is too deeply nested';
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Get maximum depth of nested arrays
	 *
	 * @since 3.0.0
	 * @param mixed $array Data to check
	 * @param int $current_depth Current recursion depth
	 * @return int Maximum depth found
	 */
	private function get_array_depth( $array, int $current_depth = 0 ): int {
		if ( ! is_array( $array ) ) {
			return $current_depth;
		}

		$max_depth = $current_depth;
		foreach ( $array as $value ) {
			if ( is_array( $value ) ) {
				$depth = $this->get_array_depth( $value, $current_depth + 1 );
				$max_depth = max( $max_depth, $depth );
			}
		}

		return $max_depth;
	}

	/**
	 * Cache migration status and intermediate results
	 *
	 * @since 3.0.0
	 */
	private array $migration_cache = [];

	/**
	 * Cache configuration constants
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;  // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800; // 30 minutes
	private const CACHE_TTL_LONG = 3600;   // 1 hour

	/**
	 * Get cached migration data
	 *
	 * @since 3.0.0
	 * @param string $key Cache key
	 * @param callable|null $callback Function to generate data if cache miss
	 * @param int $ttl Cache TTL in seconds
	 * @return mixed Cached data or callback result
	 */
	private function get_cached_migration_data( string $key, ?callable $callback = null, int $ttl = self::CACHE_TTL_MEDIUM ) {
		// Check memory cache first
		if ( isset( $this->migration_cache[ $key ] ) ) {
			return $this->migration_cache[ $key ];
		}

		// Check WordPress transient cache
		$transient_key = 'brag_book_migration_' . md5( $key );
		$cached_data = get_transient( $transient_key );

		if ( $cached_data !== false ) {
			// Store in memory cache for subsequent requests
			$this->migration_cache[ $key ] = $cached_data;
			return $cached_data;
		}

		// Cache miss - generate data if callback provided
		if ( $callback !== null ) {
			$data = $callback();
			$this->set_migration_cache( $key, $data, $ttl );
			return $data;
		}

		return null;
	}

	/**
	 * Set migration data in cache
	 *
	 * @since 3.0.0
	 * @param string $key Cache key
	 * @param mixed $data Data to cache
	 * @param int $ttl Cache TTL in seconds
	 * @return void
	 */
	private function set_migration_cache( string $key, $data, int $ttl = self::CACHE_TTL_MEDIUM ): void {
		// Store in memory cache
		$this->migration_cache[ $key ] = $data;

		// Store in WordPress transient cache
		$transient_key = 'brag_book_migration_' . md5( $key );
		set_transient( $transient_key, $data, $ttl );
	}

	/**
	 * Clear migration caches
	 *
	 * @since 3.0.0
	 * @param string|null $pattern Optional pattern to match cache keys
	 * @return void
	 */
	private function clear_migration_cache( ?string $pattern = null ): void {
		// Clear memory cache
		if ( $pattern === null ) {
			$this->migration_cache = [];
		} else {
			foreach ( array_keys( $this->migration_cache ) as $key ) {
				if ( strpos( $key, $pattern ) !== false ) {
					unset( $this->migration_cache[ $key ] );
				}
			}
		}

		// Clear transient cache
		global $wpdb;
		if ( $pattern === null ) {
			$wpdb->query(
				"DELETE FROM {$wpdb->options} 
				 WHERE option_name LIKE '_transient_brag_book_migration_%' 
				 OR option_name LIKE '_transient_timeout_brag_book_migration_%'"
			);
		} else {
			$pattern_md5 = md5( $pattern );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				 WHERE option_name LIKE %s 
				 OR option_name LIKE %s",
				'_transient_brag_book_migration_%' . $pattern_md5 . '%',
				'_transient_timeout_brag_book_migration_%' . $pattern_md5 . '%'
			) );
		}
	}

	/**
	 * Batch process posts for improved performance
	 *
	 * @since 3.0.0
	 * @param array<int> $post_ids Post IDs to process
	 * @param callable $processor Function to process each batch
	 * @param int $batch_size Number of posts per batch
	 * @return array{processed: int, errors: array<string>} Processing results
	 */
	private function batch_process_posts( array $post_ids, callable $processor, int $batch_size = 20 ): array {
		$processed = 0;
		$errors = [];
		$batches = array_chunk( $post_ids, $batch_size );

		foreach ( $batches as $batch_index => $batch ) {
			try {
				$result = $processor( $batch, $batch_index );
				if ( $result === true || is_int( $result ) ) {
					$processed += is_int( $result ) ? $result : count( $batch );
				} elseif ( is_array( $result ) && isset( $result['errors'] ) ) {
					$errors = array_merge( $errors, $result['errors'] );
				}

				// Allow WordPress to process other tasks between batches
				if ( function_exists( 'wp_cache_flush_group' ) ) {
					wp_cache_flush_group( 'posts' );
				}

				// Brief pause to prevent overwhelming the system
				usleep( 10000 ); // 10ms

			} catch ( \Exception $e ) {
				$errors[] = "Batch {$batch_index} failed: " . $e->getMessage();
				$this->log_migration_error( 'batch_processing_error', $e->getMessage(), [
					'batch_index' => $batch_index,
					'batch_size' => count( $batch ),
					'post_ids' => $batch,
				] );
			}
		}

		return [
			'processed' => $processed,
			'errors' => $errors,
		];
	}

	/**
	 * Optimize database queries during migration
	 *
	 * @since 3.0.0
	 * @param callable $operation Database operation to perform
	 * @return mixed Operation result
	 */
	private function optimize_database_operation( callable $operation ) {
		global $wpdb;

		// Disable object cache updates during bulk operations
		wp_suspend_cache_invalidation( true );

		// Increase memory limit for large operations
		$original_memory_limit = ini_get( 'memory_limit' );
		if ( wp_convert_hr_to_bytes( $original_memory_limit ) < 256 * 1024 * 1024 ) {
			ini_set( 'memory_limit', '256M' );
		}

		// Optimize database settings for bulk operations
		$wpdb->query( 'SET autocommit = 0' );
		$wpdb->query( 'SET unique_checks = 0' );
		$wpdb->query( 'SET foreign_key_checks = 0' );

		try {
			$result = $operation();
			
			// Commit all changes
			$wpdb->query( 'COMMIT' );

		} catch ( \Exception $e ) {
			// Rollback on error
			$wpdb->query( 'ROLLBACK' );
			throw $e;

		} finally {
			// Restore database settings
			$wpdb->query( 'SET autocommit = 1' );
			$wpdb->query( 'SET unique_checks = 1' );
			$wpdb->query( 'SET foreign_key_checks = 1' );

			// Restore original memory limit
			ini_set( 'memory_limit', $original_memory_limit );

			// Re-enable cache invalidation
			wp_suspend_cache_invalidation( false );

			// Clear any accumulated cache
			wp_cache_flush();
		}

		return $result;
	}

	/**
	 * Monitor migration performance and resource usage
	 *
	 * @since 3.0.0
	 * @param string $operation Operation being monitored
	 * @return array{memory_peak: string, execution_time: float, queries: int} Performance metrics
	 */
	private function monitor_performance( string $operation ): array {
		static $start_time = null;
		static $start_queries = null;

		if ( $start_time === null ) {
			$start_time = microtime( true );
			$start_queries = get_num_queries();
			return [];
		}

		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;
		$memory_peak = size_format( memory_get_peak_usage( true ) );
		$queries = get_num_queries() - $start_queries;

		$performance_data = [
			'memory_peak' => $memory_peak,
			'execution_time' => round( $execution_time, 2 ),
			'queries' => $queries,
		];

		// Log performance metrics
		do_action( 'qm/debug', "Migration performance for {$operation}", $performance_data );

		// Reset for next operation
		$start_time = null;
		$start_queries = null;

		return $performance_data;
	}
}
