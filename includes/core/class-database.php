<?php
/**
 * Database Manager
 *
 * Manages database setup and maintenance for the BragBook Gallery plugin.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Manager Class
 *
 * Handles database table creation, updates, and maintenance.
 *
 * @since 3.0.0
 */
class Database {

	/**
	 * Database version option key
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const DB_VERSION_OPTION = 'brag_book_gallery_db_version';

	/**
	 * Current database version
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CURRENT_DB_VERSION = '1.0.0';

	/**
	 * WordPress database object
	 *
	 * @since 3.0.0
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Table prefix
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private $table_prefix;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_prefix = $wpdb->prefix . 'brag_';
		
		$this->init();
	}

	/**
	 * Initialize database manager
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		add_action( 'plugins_loaded', array( $this, 'check_database_version' ) );
		// Note: activation hook should be registered in main plugin file, not here
	}

	/**
	 * Check if database needs to be updated
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function check_database_version(): void {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		
		if ( version_compare( $installed_version, self::CURRENT_DB_VERSION, '<' ) ) {
			$this->create_tables();
		}
	}

	/**
	 * Create or update database tables
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function create_tables(): void {
		$this->create_sync_log_table();
		$this->create_case_map_table();
		
		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
	}

	/**
	 * Create sync log table
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function create_sync_log_table(): void {
		$table_name = $this->table_prefix . 'sync_log';
		
		$charset_collate = $this->wpdb->get_charset_collate();

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

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Create case mapping table
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function create_case_map_table(): void {
		$table_name = $this->table_prefix . 'case_map';
		
		$charset_collate = $this->wpdb->get_charset_collate();

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

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Get sync log table name
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_sync_log_table(): string {
		return $this->table_prefix . 'sync_log';
	}

	/**
	 * Get case map table name
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_case_map_table(): string {
		return $this->table_prefix . 'case_map';
	}

	/**
	 * Log sync operation
	 *
	 * @since 3.0.0
	 * @param string $sync_type Type of sync operation.
	 * @param string $sync_status Status of sync operation.
	 * @param int    $items_processed Number of items processed.
	 * @param int    $items_failed Number of items failed.
	 * @param string $error_messages Error messages if any.
	 * @return int|false Insert ID or false on failure.
	 */
	public function log_sync_operation( 
		string $sync_type, 
		string $sync_status, 
		int $items_processed = 0, 
		int $items_failed = 0, 
		string $error_messages = '' 
	) {
		$table_name = $this->get_sync_log_table();
		
		$data = array(
			'sync_type' => $sync_type,
			'sync_status' => $sync_status,
			'items_processed' => $items_processed,
			'items_failed' => $items_failed,
			'error_messages' => $error_messages,
			'started_at' => current_time( 'mysql' ),
		);

		if ( $sync_status === 'completed' || $sync_status === 'failed' ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		$result = $this->wpdb->insert( $table_name, $data );
		
		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update sync log entry
	 *
	 * @since 3.0.0
	 * @param int    $log_id Log entry ID.
	 * @param string $sync_status New status.
	 * @param int    $items_processed Number of items processed.
	 * @param int    $items_failed Number of items failed.
	 * @param string $error_messages Error messages if any.
	 * @return bool Success status.
	 */
	public function update_sync_log( 
		int $log_id, 
		string $sync_status, 
		int $items_processed = 0, 
		int $items_failed = 0, 
		string $error_messages = '' 
	): bool {
		$table_name = $this->get_sync_log_table();
		
		$data = array(
			'sync_status' => $sync_status,
			'items_processed' => $items_processed,
			'items_failed' => $items_failed,
			'error_messages' => $error_messages,
		);

		if ( $sync_status === 'completed' || $sync_status === 'failed' ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		$result = $this->wpdb->update( 
			$table_name, 
			$data, 
			array( 'id' => $log_id ),
			array( '%s', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get recent sync logs
	 *
	 * @since 3.0.0
	 * @param int $limit Number of logs to retrieve.
	 * @return array Array of sync log objects.
	 */
	public function get_recent_sync_logs( int $limit = 10 ): array {
		$table_name = $this->get_sync_log_table();
		
		$sql = $this->wpdb->prepare( 
			"SELECT * FROM {$table_name} ORDER BY started_at DESC LIMIT %d",
			$limit 
		);

		$results = $this->wpdb->get_results( $sql );
		
		return $results ?: array();
	}

	/**
	 * Map API case to WordPress post
	 *
	 * @since 3.0.0
	 * @param int    $api_case_id API case ID.
	 * @param int    $post_id WordPress post ID.
	 * @param string $api_token API token.
	 * @param int    $property_id Property ID.
	 * @param string $sync_hash Sync hash for change detection.
	 * @return bool Success status.
	 */
	public function map_case_to_post( 
		int $api_case_id, 
		int $post_id, 
		string $api_token, 
		int $property_id, 
		string $sync_hash = '' 
	): bool {
		$table_name = $this->get_case_map_table();
		
		$data = array(
			'api_case_id' => $api_case_id,
			'post_id' => $post_id,
			'api_token' => $api_token,
			'property_id' => $property_id,
			'last_synced' => current_time( 'mysql' ),
			'sync_hash' => $sync_hash,
		);

		// Check if mapping already exists
		$existing = $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE api_case_id = %d AND api_token = %s",
			$api_case_id,
			$api_token
		) );

		if ( $existing ) {
			// Update existing mapping
			$result = $this->wpdb->update( 
				$table_name, 
				$data, 
				array( 
					'api_case_id' => $api_case_id, 
					'api_token' => $api_token 
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s' ),
				array( '%d', '%s' )
			);
		} else {
			// Insert new mapping
			$result = $this->wpdb->insert( $table_name, $data );
		}

		return $result !== false;
	}

	/**
	 * Get post ID by API case ID
	 *
	 * @since 3.0.0
	 * @param int    $api_case_id API case ID.
	 * @param string $api_token API token.
	 * @return int|null WordPress post ID or null if not found.
	 */
	public function get_post_by_case_id( int $api_case_id, string $api_token ): ?int {
		$table_name = $this->get_case_map_table();
		
		$post_id = $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT post_id FROM {$table_name} WHERE api_case_id = %d AND api_token = %s",
			$api_case_id,
			$api_token
		) );

		return $post_id ? (int) $post_id : null;
	}

	/**
	 * Get sync hash for case
	 *
	 * @since 3.0.0
	 * @param int    $api_case_id API case ID.
	 * @param string $api_token API token.
	 * @return string|null Sync hash or null if not found.
	 */
	public function get_sync_hash( int $api_case_id, string $api_token ): ?string {
		$table_name = $this->get_case_map_table();
		
		$sync_hash = $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT sync_hash FROM {$table_name} WHERE api_case_id = %d AND api_token = %s",
			$api_case_id,
			$api_token
		) );

		return $sync_hash ?: null;
	}

	/**
	 * Remove case mapping
	 *
	 * @since 3.0.0
	 * @param int    $api_case_id API case ID.
	 * @param string $api_token API token.
	 * @return bool Success status.
	 */
	public function remove_case_mapping( int $api_case_id, string $api_token ): bool {
		$table_name = $this->get_case_map_table();
		
		$result = $this->wpdb->delete( 
			$table_name,
			array( 
				'api_case_id' => $api_case_id,
				'api_token' => $api_token 
			),
			array( '%d', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Get sync statistics
	 *
	 * @since 3.0.0
	 * @return array Array of sync statistics.
	 */
	public function get_sync_stats(): array {
		$sync_log_table = $this->get_sync_log_table();
		$case_map_table = $this->get_case_map_table();

		// Get total syncs
		$total_syncs = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$sync_log_table}" );
		
		// Get successful syncs
		$successful_syncs = (int) $this->wpdb->get_var( 
			"SELECT COUNT(*) FROM {$sync_log_table} WHERE sync_status = 'completed'"
		);

		// Get failed syncs
		$failed_syncs = (int) $this->wpdb->get_var( 
			"SELECT COUNT(*) FROM {$sync_log_table} WHERE sync_status = 'failed'"
		);

		// Get total mapped cases
		$total_mapped_cases = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$case_map_table}" );

		// Get last sync time
		$last_sync = $this->wpdb->get_var( 
			"SELECT MAX(started_at) FROM {$sync_log_table}"
		);

		return array(
			'total_syncs' => $total_syncs,
			'successful_syncs' => $successful_syncs,
			'failed_syncs' => $failed_syncs,
			'total_mapped_cases' => $total_mapped_cases,
			'last_sync' => $last_sync,
		);
	}

	/**
	 * Clean up old sync logs
	 *
	 * @since 3.0.0
	 * @param int $days_to_keep Number of days to keep logs.
	 * @return int Number of logs deleted.
	 */
	public function cleanup_old_sync_logs( int $days_to_keep = 30 ): int {
		$table_name = $this->get_sync_log_table();
		
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );
		
		$result = $this->wpdb->query( $this->wpdb->prepare(
			"DELETE FROM {$table_name} WHERE started_at < %s",
			$cutoff_date
		) );

		return (int) $result;
	}

	/**
	 * Drop all plugin tables
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function drop_tables(): void {
		$sync_log_table = $this->get_sync_log_table();
		$case_map_table = $this->get_case_map_table();

		$this->wpdb->query( "DROP TABLE IF EXISTS {$sync_log_table}" );
		$this->wpdb->query( "DROP TABLE IF EXISTS {$case_map_table}" );

		delete_option( self::DB_VERSION_OPTION );
	}
}