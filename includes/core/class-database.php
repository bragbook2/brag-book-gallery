<?php
declare( strict_types=1 );

/**
 * Database Manager
 *
 * Enterprise-grade database management for the BRAG Book Gallery plugin.
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

namespace BRAGBookGallery\Includes\Core;

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
	private const CURRENT_DB_VERSION = '1.0.0';

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
	private const VALID_SYNC_TYPES = [ 'full', 'partial', 'single' ];

	/**
	 * Valid sync operation statuses.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Allowed sync statuses.
	 */
	private const VALID_SYNC_STATUSES = [ 'started', 'completed', 'failed' ];

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
		$this->create_case_map_table();

		// Update database version.
		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
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
			sync_type ENUM('full', 'partial', 'single') NOT NULL,
			sync_status ENUM('started', 'completed', 'failed') NOT NULL,
			items_processed INT UNSIGNED DEFAULT 0,
			items_failed INT UNSIGNED DEFAULT 0,
			error_messages TEXT,
			started_at DATETIME NOT NULL,
			completed_at DATETIME,
			INDEX idx_sync_status (sync_status),
			INDEX idx_started_at (started_at)
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
	 * Create case mapping table
	 *
	 * Creates table for mapping API cases to WordPress posts.
	 * Includes indexes for optimal query performance.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function create_case_map_table(): void {
		$table_name = $this->table_prefix . 'case_map';
		$charset_collate = $this->wpdb->get_charset_collate();

		// Define table schema with proper indexes.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			api_case_id BIGINT UNSIGNED NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			api_token VARCHAR(255) NOT NULL,
			property_id BIGINT UNSIGNED NOT NULL,
			last_synced DATETIME NOT NULL,
			sync_hash VARCHAR(32),
			UNIQUE KEY idx_api_case (api_case_id, api_token),
			INDEX idx_post_id (post_id),
			INDEX idx_api_token (api_token),
			INDEX idx_property_id (property_id)
		) {$charset_collate};";

		// Use dbDelta for safe table creation.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Log any database errors using VIP-compliant debugging
		if ( ! empty( $this->wpdb->last_error ) ) {
			do_action( 'qm/debug', sprintf( 
				'Error creating case_map table: %s',
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
	 * @since 3.0.0
	 * @return string Full table name.
	 */
	public function get_case_map_table(): string {
		return $this->table_prefix . 'case_map';
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
		string $error_messages = ''
	): false|int {
		$table_name = $this->get_sync_log_table();

		try {
			// Validate input parameters using predefined constants
			if ( ! $this->is_valid_sync_type( $sync_type ) || 
				 ! $this->is_valid_sync_status( $sync_status ) ) {
				return false;
			}

			// Prepare sanitized data for insertion
			$data = [
				'sync_type'       => $sync_type,
				'sync_status'     => $sync_status,
				'items_processed' => max( 0, $items_processed ),
				'items_failed'    => max( 0, $items_failed ),
				'error_messages'  => substr( $error_messages, 0, self::MAX_ERROR_MESSAGE_LENGTH ),
				'started_at'      => current_time( 'mysql' ),
			];

			// Set completion time for finished operations
			if ( in_array( $sync_status, [ 'completed', 'failed' ], true ) ) {
				$data['completed_at'] = current_time( 'mysql' );
			}

			// Dynamic format specifiers
			$formats = [ '%s', '%s', '%d', '%d', '%s', '%s' ];
			if ( isset( $data['completed_at'] ) ) {
				$formats[] = '%s';
			}

			// Insert the log entry
			$result = $this->wpdb->insert( $table_name, $data, $formats );

			// Clear related caches on successful insertion
			if ( false !== $result ) {
				$this->clear_sync_caches();
				return $this->wpdb->insert_id;
			}

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

			// Prepare sanitized update data
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

		// Try to get from cache first.
		$cache_key = 'recent_sync_logs_' . $limit;
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

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

		// Cache the results.
		wp_cache_set( $cache_key, $results, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $results;
	}

	/**
	 * Map API case to WordPress post
	 *
	 * Creates or updates a mapping between an API case and a WordPress post.
	 * Uses upsert pattern for efficiency.
	 *
	 * @since 3.0.0
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
		$table_name = $this->get_case_map_table();

		// Validate inputs.
		if ( empty( $api_token ) || $api_case_id <= 0 || $post_id <= 0 ) {
			return false;
		}

		// Prepare data for insertion/update.
		$data = array(
			'api_case_id' => absint( $api_case_id ),
			'post_id'     => absint( $post_id ),
			'api_token'   => sanitize_text_field( $api_token ),
			'property_id' => absint( $property_id ),
			'last_synced' => current_time( 'mysql' ),
			'sync_hash'   => substr( sanitize_text_field( $sync_hash ), 0, 32 ),
		);

		// Check if mapping already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$existing_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE api_case_id = %d AND api_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$api_case_id,
				$api_token
			)
		);

		if ( $existing_id ) {
			// Update existing mapping.
			$result = $this->wpdb->update(
				$table_name,
				$data,
				array(
					'api_case_id' => $api_case_id,
					'api_token'   => $api_token,
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s' ),
				array( '%d', '%s' )
			);
		} else {
			// Insert new mapping.
			$result = $this->wpdb->insert(
				$table_name,
				$data,
				array( '%d', '%d', '%s', '%d', '%s', '%s' )
			);
		}

		// Clear related caches.
		$this->clear_case_cache( $api_case_id, $api_token );

		return false !== $result;
	}

	/**
	 * Get post ID by API case ID
	 *
	 * Retrieves the WordPress post ID for a given API case with caching.
	 *
	 * @since 3.0.0
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

		// Try cache first.
		$cache_key = 'post_id_' . $api_case_id . '_' . md5( $api_token );
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table_name = $this->get_case_map_table();

		// Query for post ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$post_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT post_id FROM {$table_name} WHERE api_case_id = %d AND api_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$api_case_id,
				$api_token
			)
		);

		if ( $post_id ) {
			// Cache the result.
			wp_cache_set( $cache_key, $post_id, self::CACHE_GROUP, self::CACHE_EXPIRATION );
			return (int) $post_id;
		}

		return null;
	}

	/**
	 * Get sync hash for case
	 *
	 * Retrieves the sync hash for change detection with caching.
	 *
	 * @since 3.0.0
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

		// Try cache first.
		$cache_key = 'sync_hash_' . $api_case_id . '_' . md5( $api_token );
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (string) $cached;
		}

		$table_name = $this->get_case_map_table();

		// Query for sync hash.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$sync_hash = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT sync_hash FROM {$table_name} WHERE api_case_id = %d AND api_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$api_case_id,
				$api_token
			)
		);

		if ( $sync_hash ) {
			// Cache the result.
			wp_cache_set( $cache_key, $sync_hash, self::CACHE_GROUP, self::CACHE_EXPIRATION );
			return (string) $sync_hash;
		}

		return null;
	}

	/**
	 * Remove case mapping
	 *
	 * Deletes a case-to-post mapping from the database.
	 *
	 * @since 3.0.0
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

		$table_name = $this->get_case_map_table();

		// Delete the mapping.
		$result = $this->wpdb->delete(
			$table_name,
			array(
				'api_case_id' => absint( $api_case_id ),
				'api_token'   => sanitize_text_field( $api_token ),
			),
			array( '%d', '%s' )
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
		// Try cache first.
		$cached = wp_cache_get( 'sync_stats', self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		$sync_log_table = $this->get_sync_log_table();
		$case_map_table = $this->get_case_map_table();

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
			"SELECT COUNT(*) FROM {$case_map_table}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// Cache the results.
		wp_cache_set( 'sync_stats', $stats, self::CACHE_GROUP, self::CACHE_EXPIRATION );

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

		// Clear caches after cleanup.
		wp_cache_delete( 'recent_sync_logs', self::CACHE_GROUP );
		wp_cache_delete( 'sync_stats', self::CACHE_GROUP );

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

		// Clear specific case caches.
		wp_cache_delete( 'post_id_' . $api_case_id . '_' . $token_hash, self::CACHE_GROUP );
		wp_cache_delete( 'sync_hash_' . $api_case_id . '_' . $token_hash, self::CACHE_GROUP );

		// Clear aggregated stats.
		wp_cache_delete( 'sync_stats', self::CACHE_GROUP );
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

		$sync_log_table = $this->get_sync_log_table();
		$case_map_table = $this->get_case_map_table();

		// Drop tables using proper escaping.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall routine
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$sync_log_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall routine
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$case_map_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

		foreach ( $cache_keys as $key ) {
			wp_cache_delete( $key, self::CACHE_GROUP );
		}
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
