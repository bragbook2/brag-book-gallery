<?php
declare( strict_types=1 );

/**
 * Database Manager
 *
 * Enterprise-grade database management for the BRAG book Gallery plugin.
 * Implements secure schema management, sync operations tracking, and
 * comprehensive caching strategies following WordPress VIP standards.
 *
 * Features:
 * - Custom table creation with dbDelta safety
 * - Sync operation logging and tracking
 * - API case to WordPress post mapping
 * - Performance-optimized queries with caching
 * - Comprehensive data validation and sanitization
 * - Automatic cleanup and maintenance routines
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Data;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Manager Class
 *
 * Handles database table creation, updates, and maintenance.
 * Follows WordPress VIP standards for database operations.
 *
 * @since 3.0.0
 */
class Database {

	/**
	 * Database version option key.
	 *
	 * @since 3.0.0
	 * @var string Option name for version tracking.
	 */
	private const DB_VERSION_OPTION = 'brag_book_gallery_db_version';

	/**
	 * Current database schema version.
	 *
	 * @since 3.0.0
	 * @var string Semantic version string.
	 */
	private const CURRENT_DB_VERSION = '1.3.0';

	/**
	 * Cache group identifier for database operations.
	 *
	 * @since 3.0.0
	 * @var string Cache group name.
	 */
	private const CACHE_GROUP = 'brag_book_gallery_db';

	/**
	 * Cache TTL in seconds.
	 *
	 * @since 3.0.0
	 * @var int Cache expiration (1 hour).
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Valid sync operation types.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Allowed sync types.
	 */
	private const VALID_SYNC_TYPES = [ 'full', 'partial', 'single', 'stage_1', 'stage_2', 'stage_3' ];

	/**
	 * Valid sync operation statuses.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Allowed sync statuses.
	 */
	private const VALID_SYNC_STATUSES = [ 'started', 'completed', 'failed' ];

	/**
	 * Valid sync sources.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Allowed sync sources.
	 */
	private const VALID_SYNC_SOURCES = [ 'manual', 'automatic', 'cron', 'rest_api' ];

	/**
	 * Maximum error message length for TEXT field.
	 *
	 * @since 3.0.0
	 * @var int Character limit for error messages.
	 */
	private const MAX_ERROR_MESSAGE_LENGTH = 65535;

	/**
	 * WordPress database instance.
	 *
	 * @since 3.0.0
	 * @var \wpdb Global WordPress database object.
	 */
	private \wpdb $wpdb;

	/**
	 * Table prefix for plugin-specific tables.
	 *
	 * @since 3.0.0
	 * @var string Prefixed table identifier.
	 */
	private string $table_prefix;

	/**
	 * Cache for expensive operations.
	 *
	 * @since 3.0.0
	 * @var array<string, mixed> Operation results cache.
	 */
	private array $cache = [];

	/**
	 * Constructor - Initialize database manager.
	 *
	 * Sets up database connection, table prefixes, and initialization hooks.
	 * Implements defensive programming practices for reliability.
	 *
	 * @since 3.0.0
	 * @throws \RuntimeException If database initialization fails.
	 */
	public function __construct() {
		try {
			global $wpdb;

			if ( ! $wpdb instanceof \wpdb ) {
				throw new \RuntimeException( 'WordPress database not available' );
			}

			$this->wpdb = $wpdb;

			// Ensure valid table prefix with fallback
			$prefix = $wpdb->prefix ?: 'wp_';
			$this->table_prefix = $prefix . 'brag_';

			$this->init();

			do_action( 'qm/debug', 'Database manager initialized successfully' );
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Database initialization failed: %s', $e->getMessage() ) );
			throw new \RuntimeException( 'Database manager initialization failed', 0, $e );
		}
	}

	/**
	 * Initialize database manager.
	 *
	 * Registers hooks for version checking and maintenance operations.
	 * Uses modern PHP 8.2 array syntax for improved readability.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Check database version after all plugins are loaded
		add_action( 'plugins_loaded', [ $this, 'check_database_version' ] );

		// Schedule cleanup operations
		if ( ! wp_next_scheduled( 'brag_book_gallery_db_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'brag_book_gallery_db_cleanup' );
		}

		add_action( 'brag_book_gallery_db_cleanup', [ $this, 'daily_cleanup' ] );
	}

	/**
	 * Check if database needs to be updated.
	 *
	 * Smart version comparison with automatic migration and cache invalidation.
	 * Implements comprehensive error handling and logging.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function check_database_version(): void {
		try {
			$installed_version = $this->get_installed_version();

			// Check if upgrade needed using semantic version comparison
			if ( version_compare( $installed_version, self::CURRENT_DB_VERSION, '<' ) ) {
				do_action( 'qm/debug', sprintf(
					'Database upgrade needed: %s -> %s',
					$installed_version,
					self::CURRENT_DB_VERSION
				) );

				// Run version-specific migrations
				if ( version_compare( $installed_version, '1.2.0', '<' ) ) {
					$this->migrate_to_1_2_0();
				}

				if ( version_compare( $installed_version, '1.3.0', '<' ) ) {
					$this->migrate_to_1_3_0();
				}

				$this->create_tables();
				$this->invalidate_all_caches();
			}
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Database version check failed: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Create or update database tables
	 *
	 * Creates all plugin database tables using dbDelta for
	 * safe schema updates.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function create_tables(): void {
		// Create individual tables.
		$this->create_sync_log_table();
		$this->create_sync_registry_table();

		// Update database version.
		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
	}

	/**
	 * Migration to version 1.2.0
	 *
	 * Adds new sync types (stage_1, stage_2, stage_3) and sources (cron, rest_api)
	 * to the sync_log table ENUM columns.
	 *
	 * @since 3.3.0
	 * @return void
	 */
	private function migrate_to_1_2_0(): void {
		$table_name = $this->get_sync_log_table();

		// Check if table exists
		if ( ! $this->table_exists( $table_name ) ) {
			do_action( 'qm/debug', 'Migration 1.2.0: Table does not exist, will be created fresh' );
			return;
		}

		// Get existing columns
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $this->wpdb->get_col( "DESCRIBE {$table_name}" );

		// Only modify sync_type if it exists
		if ( in_array( 'sync_type', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query(
				"ALTER TABLE {$table_name}
				MODIFY COLUMN sync_type ENUM('full', 'partial', 'single', 'stage_1', 'stage_2', 'stage_3') NOT NULL"
			);
			do_action( 'qm/debug', 'Migration 1.2.0: Updated sync_type column' );
		}

		// Only modify sync_source if it exists
		if ( in_array( 'sync_source', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query(
				"ALTER TABLE {$table_name}
				MODIFY COLUMN sync_source ENUM('manual', 'automatic', 'cron', 'rest_api') NOT NULL DEFAULT 'manual'"
			);
			do_action( 'qm/debug', 'Migration 1.2.0: Updated sync_source column' );
		}

		do_action( 'qm/debug', 'Database migrated to version 1.2.0: Added new sync types and sources' );
	}

	/**
	 * Migration to version 1.3.0
	 *
	 * Replaces wp_brag_case_map table with wp_brag_sync_registry
	 * for unified sync tracking across procedures, cases, and doctors.
	 *
	 * @since 4.3.3
	 * @return void
	 */
	private function migrate_to_1_3_0(): void {
		$old_table = $this->table_prefix . 'case_map';
		$new_table = $this->get_sync_registry_table();

		// Create the new sync registry table first
		$this->create_sync_registry_table();

		// Migrate existing case_map rows into sync_registry
		if ( $this->table_exists( $old_table ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_rows = $this->wpdb->get_results(
				"SELECT * FROM {$old_table}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( ! empty( $existing_rows ) ) {
				foreach ( $existing_rows as $row ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$this->wpdb->insert(
						$new_table,
						[
							'item_type'         => 'case',
							'api_id'            => absint( $row->api_case_id ),
							'wordpress_id'      => absint( $row->post_id ),
							'wordpress_type'    => 'post',
							'api_token'         => $row->api_token,
							'property_id'       => absint( $row->property_id ),
							'procedure_api_id'  => 0,
							'sync_hash'         => $row->sync_hash ?? '',
							'last_synced'       => $row->last_synced,
							'last_sync_session' => 'migrated_from_case_map',
						],
						[ '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
					);
				}
				do_action( 'qm/debug', sprintf( 'Migration 1.3.0: Migrated %d rows from case_map to sync_registry', count( $existing_rows ) ) );
			}

			// Drop old table
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$old_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			do_action( 'qm/debug', 'Migration 1.3.0: Dropped old case_map table' );
		}

		do_action( 'qm/debug', 'Database migrated to version 1.3.0: Unified sync registry created' );
	}

	/**
	 * Create sync log table
	 *
	 * Creates table for tracking API sync operations and their status.
	 * Uses dbDelta for safe table creation/updates.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function create_sync_log_table(): void {
		$table_name = $this->table_prefix . 'sync_log';
		$charset_collate = $this->wpdb->get_charset_collate();

		// Define table schema.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			sync_type ENUM('full', 'partial', 'single', 'stage_1', 'stage_2', 'stage_3') NOT NULL,
			sync_status ENUM('started', 'completed', 'failed') NOT NULL,
			sync_source ENUM('manual', 'automatic', 'cron', 'rest_api') NOT NULL DEFAULT 'manual',
			items_processed INT UNSIGNED DEFAULT 0,
			items_failed INT UNSIGNED DEFAULT 0,
			error_messages TEXT,
			started_at DATETIME NOT NULL,
			completed_at DATETIME,
			INDEX idx_sync_status (sync_status),
			INDEX idx_started_at (started_at),
			INDEX idx_sync_source (sync_source)
		) {$charset_collate};";

		// Use dbDelta for safe table creation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Log any database errors using VIP-compliant debugging
		if ( ! empty( $this->wpdb->last_error ) ) {
			do_action( 'qm/debug', sprintf(
				'Error creating sync_log table: %s',
				$this->wpdb->last_error
			) );
		}
	}

	/**
	 * Create sync registry table
	 *
	 * Creates unified table for tracking API-to-WordPress item mappings
	 * across procedures, cases, and doctors.
	 *
	 * @since 4.3.3
	 * @return void
	 */
	private function create_sync_registry_table(): void {
		$table_name      = $this->get_sync_registry_table();
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			item_type VARCHAR(20) NOT NULL,
			api_id BIGINT UNSIGNED NOT NULL,
			wordpress_id BIGINT UNSIGNED NOT NULL,
			wordpress_type VARCHAR(20) NOT NULL,
			api_token VARCHAR(255) NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			procedure_api_id BIGINT UNSIGNED DEFAULT 0,
			sync_hash VARCHAR(32) DEFAULT '',
			last_synced DATETIME NOT NULL,
			last_sync_session VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY idx_item_unique (item_type, api_id, api_token, procedure_api_id),
			KEY idx_wordpress (wordpress_id, wordpress_type),
			KEY idx_sync_session (last_sync_session),
			KEY idx_item_type (item_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! empty( $this->wpdb->last_error ) ) {
			do_action( 'qm/debug', sprintf(
				'Error creating sync_registry table: %s',
				$this->wpdb->last_error
			) );
		}
	}

	/**
	 * Get sync log table name
	 *
	 * Returns the full table name including WordPress prefix.
	 * Uses caching to avoid repeated string concatenation.
	 *
	 * @since 3.0.0
	 * @return string Full table name.
	 */
	public function get_sync_log_table(): string {
		return $this->table_prefix . 'sync_log';
	}

	/**
	 * Get case map table name
	 *
	 * Returns the full table name including WordPress prefix.
	 * Uses caching to avoid repeated string concatenation.
	 *
	 * @since      3.0.0
	 * @deprecated 4.3.3 Use get_sync_registry_table() instead.
	 * @return string Full table name.
	 */
	public function get_case_map_table(): string {
		return $this->table_prefix . 'case_map';
	}

	/**
	 * Get sync registry table name
	 *
	 * @since 4.3.3
	 * @return string Full table name.
	 */
	public function get_sync_registry_table(): string {
		return $this->table_prefix . 'sync_registry';
	}

	/**
	 * Log sync operation.
	 *
	 * Records comprehensive sync operation data with validation and caching.
	 * Uses prepared statements and error handling per VIP standards.
	 *
	 * @since 3.0.0
	 * @param string $sync_type       Operation type (full|partial|single).
	 * @param string $sync_status     Operation status (started|completed|failed).
	 * @param int    $items_processed Number of successfully processed items.
	 * @param int    $items_failed    Number of failed items.
	 * @param string $error_messages  Aggregated error messages.
	 * @return int|false Insert ID on success, false on failure.
	 *
	 * @example
	 * ```php
	 * $log_id = $db->log_sync_operation( 'full', 'started' );
	 * // ... perform sync operations ...
	 * $db->update_sync_log( $log_id, 'completed', 150, 2 );
	 * ```
	 */
	public function log_sync_operation(
		string $sync_type,
		string $sync_status,
		int $items_processed = 0,
		int $items_failed = 0,
		string $error_messages = '',
		string $sync_source = 'manual'
	): false|int {
		$table_name = $this->get_sync_log_table();

		try {
			// Validate input parameters using predefined constants
			if ( ! $this->is_valid_sync_type( $sync_type ) ||
				 ! $this->is_valid_sync_status( $sync_status ) ||
				 ! $this->is_valid_sync_source( $sync_source ) ) {
				return false;
			}

			// Prepare sanitized data for insertion (matching actual table schema)
			$data = [
				'sync_type'       => $sync_type,
				'sync_status'     => $sync_status,
				'sync_source'     => $sync_source,
				'items_processed' => max( 0, $items_processed ),
				'items_failed'    => max( 0, $items_failed ),
				'error_messages'  => substr( $error_messages, 0, self::MAX_ERROR_MESSAGE_LENGTH ),
				'started_at'      => current_time( 'mysql' ),
			];

			// Set completion time for finished operations
			if ( in_array( $sync_status, [ 'completed', 'failed' ], true ) ) {
				$data['completed_at'] = current_time( 'mysql' );
			}

			// Remove sync_source if column doesn't exist (old schema compatibility)
			$columns = $this->wpdb->get_col( "DESCRIBE {$table_name}" );
			if ( ! in_array( 'sync_source', $columns, true ) ) {
				unset( $data['sync_source'] );
			}

			// Dynamic format specifiers - adjust based on what columns we're inserting
			$formats = [];
			foreach ( $data as $key => $value ) {
				if ( in_array( $key, [ 'items_processed', 'items_failed' ], true ) ) {
					$formats[] = '%d';
				} else {
					$formats[] = '%s';
				}
			}

			// Insert the log entry
			$result = $this->wpdb->insert( $table_name, $data, $formats );

			// Clear related caches on successful insertion
			if ( false !== $result ) {
				$insert_id = $this->wpdb->insert_id;
				error_log( 'BRAGBook Database: Successfully logged sync operation with ID: ' . $insert_id . ', type: ' . $sync_type . ', source: ' . $sync_source );
				$this->clear_sync_caches();
				return $insert_id;
			}

			// Log insert failure
			$error_msg = 'Failed to insert sync log: ' . $this->wpdb->last_error;
			error_log( 'BRAGBook Database ERROR: ' . $error_msg );
			error_log( 'BRAGBook Database: Data attempted: ' . print_r( $data, true ) );
			do_action( 'qm/debug', $error_msg );

			return false;
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Sync logging error: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Update sync log entry.
	 *
	 * Updates existing sync operation with new status and metrics.
	 * Implements comprehensive validation and error handling.
	 *
	 * @since 3.0.0
	 * @param int    $log_id          Target log entry ID.
	 * @param string $sync_status     Updated operation status.
	 * @param int    $items_processed Updated processed count.
	 * @param int    $items_failed    Updated failure count.
	 * @param string $error_messages  Updated error messages.
	 * @return bool True on success, false on failure.
	 */
	public function update_sync_log(
		int $log_id,
		string $sync_status,
		int $items_processed = 0,
		int $items_failed = 0,
		string $error_messages = ''
	): bool {
		try {
			$table_name = $this->get_sync_log_table();

			// Validate inputs
			if ( $log_id <= 0 || ! $this->is_valid_sync_status( $sync_status ) ) {
				return false;
			}

			// Prepare sanitized update data (matching actual table schema)
			$data = [
				'sync_status'     => $sync_status,
				'items_processed' => max( 0, $items_processed ),
				'items_failed'    => max( 0, $items_failed ),
				'error_messages'  => substr( $error_messages, 0, self::MAX_ERROR_MESSAGE_LENGTH ),
			];

			// Set completion timestamp for finished operations
			if ( in_array( $sync_status, [ 'completed', 'failed' ], true ) ) {
				$data['completed_at'] = current_time( 'mysql' );
			}

			// Update with proper format specifiers
			$formats = [ '%s', '%d', '%d', '%s' ];
			if ( isset( $data['completed_at'] ) ) {
				$formats[] = '%s';
			}

			$result = $this->wpdb->update(
				$table_name,
				$data,
				[ 'id' => $log_id ],
				$formats,
				[ '%d' ]
			);

			// Clear caches on successful update
			if ( false !== $result ) {
				$this->clear_sync_caches();
			}

			return false !== $result;
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Sync log update error: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Get recent sync logs
	 *
	 * Retrieves recent sync log entries with caching for performance.
	 * Uses prepared statements for security.
	 *
	 * @since 3.0.0
	 *
	 * @param int $limit Number of logs to retrieve (max 100).
	 *
	 * @return array Array of sync log objects.
	 */
	public function get_recent_sync_logs( int $limit = 10 ): array {
		// Sanitize and limit the input.
		$limit = min( absint( $limit ), 100 );

		// Caching disabled

		$table_name = $this->get_sync_log_table();

		// Prepare and execute query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY started_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);

		// Ensure we return an array.
		$results = is_array( $results ) ? $results : array();

		// Caching disabled

		return $results;
	}

	/**
	 * Map API case to WordPress post
	 *
	 * Creates or updates a mapping between an API case and a WordPress post.
	 * Uses upsert pattern for efficiency.
	 *
	 * @since      3.0.0
	 * @deprecated 4.3.3 Use upsert_registry_item() instead.
	 *
	 * @param int    $api_case_id API case ID from external system.
	 * @param int    $post_id     WordPress post ID.
	 * @param string $api_token   API token for authentication.
	 * @param int    $property_id Property ID from external system.
	 * @param string $sync_hash   Hash for change detection (optional).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function map_case_to_post(
		int $api_case_id,
		int $post_id,
		string $api_token,
		int $property_id,
		string $sync_hash = ''
	): bool {
		return $this->upsert_registry_item(
			'case',
			$api_case_id,
			$post_id,
			'post',
			$api_token,
			$property_id,
			'legacy_map',
			null,
			$sync_hash
		);
	}

	/**
	 * Get post ID by API case ID
	 *
	 * Retrieves the WordPress post ID for a given API case with caching.
	 *
	 * @since      3.0.0
	 * @deprecated 4.3.3 Use get_registry_item() instead.
	 *
	 * @param int    $api_case_id API case ID to look up.
	 * @param string $api_token   API token for authentication.
	 *
	 * @return int|null WordPress post ID or null if not found.
	 */
	public function get_post_by_case_id( int $api_case_id, string $api_token ): ?int {
		// Validate inputs.
		if ( empty( $api_token ) || $api_case_id <= 0 ) {
			return null;
		}

		// Caching disabled

		$table_name = $this->get_sync_registry_table();

		// Query for post ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$post_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT wordpress_id FROM {$table_name} WHERE item_type = 'case' AND api_id = %d AND api_token = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$api_case_id,
				$api_token
			)
		);

		if ( $post_id ) {
			// Caching disabled
			return (int) $post_id;
		}

		return null;
	}

	/**
	 * Get sync hash for case
	 *
	 * Retrieves the sync hash for change detection with caching.
	 *
	 * @since      3.0.0
	 * @deprecated 4.3.3 Use get_registry_item() instead.
	 *
	 * @param int    $api_case_id API case ID to look up.
	 * @param string $api_token   API token for authentication.
	 *
	 * @return string|null Sync hash or null if not found.
	 */
	public function get_sync_hash( int $api_case_id, string $api_token ): ?string {
		// Validate inputs.
		if ( empty( $api_token ) || $api_case_id <= 0 ) {
			return null;
		}

		// Caching disabled

		$table_name = $this->get_sync_registry_table();

		// Query for sync hash.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$sync_hash = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT sync_hash FROM {$table_name} WHERE item_type = 'case' AND api_id = %d AND api_token = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$api_case_id,
				$api_token
			)
		);

		if ( $sync_hash ) {
			// Caching disabled
			return (string) $sync_hash;
		}

		return null;
	}

	/**
	 * Upsert a sync registry item
	 *
	 * Creates or updates a mapping in the sync registry using
	 * INSERT ... ON DUPLICATE KEY UPDATE for atomic operation.
	 *
	 * @since 4.3.3
	 *
	 * @param string   $item_type        Item type: 'case', 'procedure', or 'doctor'.
	 * @param int      $api_id           API-side ID.
	 * @param int      $wordpress_id     WordPress post ID or term ID.
	 * @param string   $wordpress_type   'post' or 'term'.
	 * @param string   $api_token        API token for multi-tenant isolation.
	 * @param int      $property_id      Website property ID.
	 * @param string   $sync_session_id  Current sync session identifier.
	 * @param int|null $procedure_api_id For cases: procedure-specific API ID.
	 * @param string   $sync_hash        MD5 hash for change detection.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function upsert_registry_item(
		string $item_type,
		int $api_id,
		int $wordpress_id,
		string $wordpress_type,
		string $api_token,
		int $property_id,
		string $sync_session_id,
		?int $procedure_api_id = null,
		string $sync_hash = ''
	): bool {
		$table_name = $this->get_sync_registry_table();

		if ( empty( $api_token ) || $api_id <= 0 || $wordpress_id <= 0 ) {
			return false;
		}

		$procedure_id_val = $procedure_api_id ?? 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$table_name} (item_type, api_id, wordpress_id, wordpress_type, api_token, property_id, procedure_api_id, sync_hash, last_synced, last_sync_session)
				VALUES (%s, %d, %d, %s, %s, %d, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
					wordpress_id = VALUES(wordpress_id),
					wordpress_type = VALUES(wordpress_type),
					property_id = VALUES(property_id),
					sync_hash = VALUES(sync_hash),
					last_synced = VALUES(last_synced),
					last_sync_session = VALUES(last_sync_session)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$item_type,
				$api_id,
				$wordpress_id,
				$wordpress_type,
				$api_token,
				$property_id,
				$procedure_id_val,
				substr( $sync_hash, 0, 32 ),
				current_time( 'mysql' ),
				$sync_session_id
			)
		);

		return false !== $result;
	}

	/**
	 * Find orphaned items by sync session
	 *
	 * Returns registry rows where the last_sync_session does not match
	 * the current session, indicating items no longer present in the API.
	 *
	 * @since 4.3.3
	 *
	 * @param string      $current_session Current sync session ID.
	 * @param string      $api_token       API token for tenant isolation.
	 * @param string|null $item_type       Optional: filter by item type.
	 *
	 * @return array Array of orphaned registry row objects.
	 */
	public function find_orphans_by_session( string $current_session, string $api_token, ?string $item_type = null ): array {
		$table_name = $this->get_sync_registry_table();

		if ( empty( $current_session ) || empty( $api_token ) ) {
			return [];
		}

		$sql = "SELECT * FROM {$table_name} WHERE last_sync_session != %s AND api_token = %s"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params = [ $current_session, $api_token ];

		if ( $item_type ) {
			$sql .= ' AND item_type = %s';
			$params[] = $item_type;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Delete registry items by ID
	 *
	 * Bulk deletes registry rows by their primary key IDs.
	 *
	 * @since 4.3.3
	 *
	 * @param array $registry_ids Array of registry row IDs to delete.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_registry_items( array $registry_ids ): int {
		if ( empty( $registry_ids ) ) {
			return 0;
		}

		$table_name = $this->get_sync_registry_table();
		$ids        = array_map( 'absint', $registry_ids );
		$ids        = array_filter( $ids );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$table_name} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$ids
			)
		);

		return absint( $deleted );
	}

	/**
	 * Remove registry entry by WordPress ID
	 *
	 * Cleanup method for when a WordPress item is manually deleted.
	 *
	 * @since 4.3.3
	 *
	 * @param int    $wordpress_id   WordPress post or term ID.
	 * @param string $wordpress_type 'post' or 'term'.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_registry_by_wordpress_id( int $wordpress_id, string $wordpress_type ): bool {
		if ( $wordpress_id <= 0 || empty( $wordpress_type ) ) {
			return false;
		}

		$table_name = $this->get_sync_registry_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$table_name,
			[
				'wordpress_id'   => $wordpress_id,
				'wordpress_type' => $wordpress_type,
			],
			[ '%d', '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Get a single registry item
	 *
	 * @since 4.3.3
	 *
	 * @param string   $item_type        Item type.
	 * @param int      $api_id           API-side ID.
	 * @param string   $api_token        API token.
	 * @param int|null $procedure_api_id Optional procedure API ID.
	 *
	 * @return object|null Registry row or null if not found.
	 */
	public function get_registry_item( string $item_type, int $api_id, string $api_token, ?int $procedure_api_id = null ): ?object {
		$table_name       = $this->get_sync_registry_table();
		$procedure_id_val = $procedure_api_id ?? 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE item_type = %s AND api_id = %d AND api_token = %s AND procedure_api_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$item_type,
				$api_id,
				$api_token,
				$procedure_id_val
			)
		);

		return $result ?: null;
	}

	/**
	 * Get registry statistics grouped by item type
	 *
	 * @since 4.3.3
	 *
	 * @return array Counts keyed by item_type.
	 */
	public function get_registry_stats(): array {
		$table_name = $this->get_sync_registry_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			"SELECT item_type, COUNT(*) as count FROM {$table_name} GROUP BY item_type" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$stats = [
			'case'      => 0,
			'procedure' => 0,
			'doctor'    => 0,
			'total'     => 0,
		];

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$stats[ $row->item_type ] = absint( $row->count );
				$stats['total']          += absint( $row->count );
			}
		}

		return $stats;
	}

	/**
	 * Remove case mapping
	 *
	 * Deletes a case-to-post mapping from the database.
	 *
	 * @since      3.0.0
	 * @deprecated 4.3.3 Use delete_registry_items() or remove_registry_by_wordpress_id() instead.
	 *
	 * @param int    $api_case_id API case ID to remove.
	 * @param string $api_token   API token for authentication.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function remove_case_mapping( int $api_case_id, string $api_token ): bool {
		// Validate inputs.
		if ( empty( $api_token ) || $api_case_id <= 0 ) {
			return false;
		}

		$table_name = $this->get_sync_registry_table();

		// Delete the mapping from the sync registry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$table_name} WHERE item_type = 'case' AND api_id = %d AND api_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$api_case_id,
				$api_token
			)
		);

		// Clear related caches.
		$this->clear_case_cache( $api_case_id, $api_token );

		return false !== $result;
	}

	/**
	 * Get sync statistics
	 *
	 * Retrieves aggregated statistics about sync operations with caching.
	 *
	 * @since 3.0.0
	 *
	 * @return array Array containing sync statistics.
	 */
	public function get_sync_stats(): array {
		// Caching disabled

		$sync_log_table      = $this->get_sync_log_table();
		$sync_registry_table = $this->get_sync_registry_table();

		// Default stats structure.
		$default_stats = array(
			'total_syncs'        => 0,
			'successful_syncs'   => 0,
			'failed_syncs'       => 0,
			'total_mapped_cases' => 0,
			'last_sync'          => null,
		);

		// Get total syncs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query
		$total_syncs = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_log_table}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Get successful syncs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query
		$successful_syncs = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$sync_log_table} WHERE sync_status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'completed'
			)
		);

		// Get failed syncs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query
		$failed_syncs = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$sync_log_table} WHERE sync_status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'failed'
			)
		);

		// Get total mapped cases.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query
		$total_mapped_cases = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$sync_registry_table} WHERE item_type = 'case'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Get last sync time.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregation query
		$last_sync = $this->wpdb->get_var(
			"SELECT MAX(started_at) FROM {$sync_log_table}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Build stats array.
		$stats = array(
			'total_syncs'        => absint( $total_syncs ),
			'successful_syncs'   => absint( $successful_syncs ),
			'failed_syncs'       => absint( $failed_syncs ),
			'total_mapped_cases' => absint( $total_mapped_cases ),
			'last_sync'          => $last_sync,
		);

		// Caching disabled

		return $stats;
	}

	/**
	 * Clean up old sync logs
	 *
	 * Removes sync log entries older than specified days.
	 * Helps maintain database performance by preventing unlimited growth.
	 *
	 * @since 3.0.0
	 *
	 * @param int $days_to_keep Number of days to keep logs (1-365).
	 *
	 * @return int Number of logs deleted.
	 */
	public function cleanup_old_sync_logs( int $days_to_keep = 30 ): int {
		// Validate and sanitize input.
		$days_to_keep = min( max( absint( $days_to_keep ), 1 ), 365 );

		$table_name = $this->get_sync_log_table();

		// Calculate cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );

		// Delete old logs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance query
		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$table_name} WHERE started_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff_date
			)
		);

		// Caching disabled

		return absint( $deleted );
	}

	/**
	 * Clear case-related caches
	 *
	 * Helper method to clear all caches related to a specific case.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $api_case_id API case ID.
	 * @param string $api_token   API token.
	 *
	 * @return void
	 */
	private function clear_case_cache( int $api_case_id, string $api_token ): void {
		if ( empty( $api_token ) || $api_case_id <= 0 ) {
			return;
		}

		$token_hash = md5( $api_token );

		// Caching disabled
	}

	/**
	 * Drop all plugin tables
	 *
	 * Removes all database tables created by this plugin.
	 * WARNING: This will permanently delete all data!
	 * Should only be called during plugin uninstall.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function drop_tables(): void {
		// Only allow during uninstall or by admin users.
		if ( ! current_user_can( 'manage_options' ) &&
			 ! ( defined( 'WP_UNINSTALL_PLUGIN' ) && WP_UNINSTALL_PLUGIN ) ) {
			return;
		}

		$sync_log_table      = $this->get_sync_log_table();
		$case_map_table      = $this->get_case_map_table();
		$sync_registry_table = $this->get_sync_registry_table();

		// Drop tables using proper escaping.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall routine
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$sync_log_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall routine
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$case_map_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall routine
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$sync_registry_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Remove database version option.
		delete_option( self::DB_VERSION_OPTION );

		// Clear all caches.
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Verify table exists
	 *
	 * Checks if a specific table exists in the database.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table_name Table name to check.
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists( string $table_name ): bool {
		if ( empty( $table_name ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return ! empty( $result );
	}

	/**
	 * Get table charset and collation.
	 *
	 * @since 3.0.0
	 * @return string Charset and collation string.
	 */
	public function get_charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Get installed database version.
	 *
	 * @since 3.0.0
	 * @return string Installed version string.
	 */
	private function get_installed_version(): string {
		$version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		return is_string( $version ) ? sanitize_text_field( $version ) : '0.0.0';
	}

	/**
	 * Invalidate all database-related caches.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function invalidate_all_caches(): void {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			// Fallback cache clearing
			$this->clear_sync_caches();
		}
	}

	/**
	 * Clear sync-related caches.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function clear_sync_caches(): void {
		$cache_keys = [
			'recent_sync_logs',
			'sync_stats',
			'recent_sync_logs_10',
			'recent_sync_logs_20',
			'recent_sync_logs_50',
		];

		// Caching disabled
	}

	/**
	 * Validate sync type.
	 *
	 * @since 3.0.0
	 * @param string $sync_type Sync type to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_sync_type( string $sync_type ): bool {
		return in_array( $sync_type, self::VALID_SYNC_TYPES, true );
	}

	/**
	 * Validate sync status.
	 *
	 * @since 3.0.0
	 * @param string $sync_status Sync status to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_sync_status( string $sync_status ): bool {
		return in_array( $sync_status, self::VALID_SYNC_STATUSES, true );
	}

	/**
	 * Validate sync source.
	 *
	 * @since 3.0.0
	 * @param string $sync_source Sync source to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_sync_source( string $sync_source ): bool {
		return in_array( $sync_source, self::VALID_SYNC_SOURCES, true );
	}

	/**
	 * Daily cleanup routine.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function daily_cleanup(): void {
		try {
			// Clean up old sync logs (keep 30 days)
			$deleted = $this->cleanup_old_sync_logs( 30 );

			if ( $deleted > 0 ) {
				do_action( 'qm/debug', sprintf( 'Cleaned up %d old sync logs', $deleted ) );
			}
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Daily cleanup failed: %s', $e->getMessage() ) );
		}
	}
}
