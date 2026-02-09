<?php
/**
 * Data Sync Class
 *
 * Handles synchronization of data from the BRAGBook API to WordPress.
 * Manages procedures, cases, and their relationships with full logging.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Core\Trait_Api;
use BRAGBookGallery\Includes\Data\Database;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use Exception;
use Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Data Sync Class
 *
 * Manages the synchronization of data from the BRAGBook API.
 * Handles procedures, cases, and their relationships.
 *
 * @since 3.0.0
 */
class Data_Sync {
	use Trait_Api;

	/**
	 * Sync log table name
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $log_table;

	/**
	 * Current sync session ID
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $sync_session_id;

	/**
	 * Path to sync status file
	 *
	 * @since 3.3.2
	 * @var string
	 */
	private string $sync_status_file;

	/**
	 * Maximum execution time in seconds (50s for 60s timeout environments)
	 *
	 * @since 3.3.3
	 * @var int
	 */
	private int $max_execution_time = 50;

	/**
	 * Start time of sync execution
	 *
	 * @since 3.3.3
	 * @var int
	 */
	private int $sync_start_time;

	/**
	 * Maximum memory usage percentage before stopping (85% to be safe)
	 *
	 * @since 3.3.3
	 * @var int
	 */
	private int $max_memory_percent = 85;

	/**
	 * Option name for storing sync state
	 *
	 * @since 3.3.3
	 * @var string
	 */
	private string $sync_state_option = 'brag_book_gallery_sync_state';

	/**
	 * Sync API instance for registration and reporting
	 *
	 * @since 4.0.2
	 * @var Sync_Api|null
	 */
	private ?Sync_Api $sync_api = null;

	/**
	 * Database instance for sync registry
	 *
	 * @since 4.3.3
	 * @var Database|null
	 */
	private ?Database $database = null;

	/**
	 * Whether to register/report syncs to BRAG book API
	 *
	 * @since 4.0.2
	 * @var bool
	 */
	private bool $enable_sync_reporting = true;

	/**
	 * Sync type for registration (AUTO or MANUAL)
	 *
	 * @since 4.0.2
	 * @var string
	 */
	private string $sync_type = 'MANUAL';

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		try {
			global $wpdb;
			$this->log_table = $wpdb->prefix . 'brag_book_sync_log';
			$this->sync_session_id = uniqid( 'sync_', true );
			$this->init_sync_status_file();
			$this->maybe_create_sync_table();
			$this->init_sync_api();

			// Initialize database for registry
			try {
				$setup = Setup::get_instance();
				$db    = $setup->get_service( 'database' );
				if ( $db instanceof Database ) {
					$this->database = $db;
				}
			} catch ( \Exception $e ) {
				error_log( 'BRAG book Gallery Sync: Could not initialize database for registry: ' . $e->getMessage() );
			}
		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Constructor failed with exception: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Exception trace: ' . $e->getTraceAsString() );
			throw $e;
		} catch ( Error $e ) {
			error_log( 'BRAG book Gallery Sync: Constructor failed with error: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Error trace: ' . $e->getTraceAsString() );
			throw $e;
		}
	}

	/**
	 * Get API token for registry operations
	 *
	 * @since 4.3.3
	 * @return string
	 */
	private function get_registry_api_token(): string {
		$tokens = get_option( 'brag_book_gallery_api_token', [] );
		return is_array( $tokens ) ? ( $tokens[0] ?? '' ) : (string) $tokens;
	}

	/**
	 * Get website property ID for registry operations
	 *
	 * @since 4.3.3
	 * @return int
	 */
	private function get_registry_property_id(): int {
		$ids = get_option( 'brag_book_gallery_website_property_id', [] );
		return is_array( $ids ) ? absint( $ids[0] ?? 0 ) : absint( $ids );
	}

	/**
	 * Get the current sync session ID
	 *
	 * @since 4.3.3
	 * @return string
	 */
	public function get_sync_session_id(): string {
		return $this->sync_session_id;
	}

	/**
	 * Sync procedures from API (Stage 1 only - for backwards compatibility)
	 *
	 * @return array Sync results
	 * @throws Exception If sync fails
	 * @since 3.0.0
	 */
	public function sync_procedures(): array {
		return $this->run_two_stage_sync( false ); // Only stage 1
	}

	/**
	 * Run complete two-stage sync: procedures + cases
	 *
	 * @param bool $include_cases Whether to include case synchronization (stage 2)
	 *
	 * @return array Sync results
	 * @throws Exception If sync fails
	 * @since 3.0.0
	 */
	public function run_two_stage_sync( bool $include_cases = true ): array {
		// Clean up memory before starting sync
		$this->aggressive_memory_cleanup();
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_flush();

		// Log initial memory state with both reserved and real usage
		$initial_memory = memory_get_usage( true );
		$initial_real   = memory_get_usage( false );
		$memory_limit   = $this->get_memory_limit_bytes();

		// Record start time for execution limit checking
		$this->sync_start_time = time();

		// Store sync start time in transient for monitoring
		set_transient( 'brag_book_gallery_sync_start_time', $this->sync_start_time, 3600 );

		// Check if there's an existing state to resume from
		$existing_state = get_option( $this->sync_state_option, null );
		if ( $existing_state && ! empty( $existing_state['session_id'] ) ) {
			error_log( 'BRAG book Gallery Sync: Found existing sync state, attempting to resume...' );

			return $this->resume_sync( $existing_state );
		}

		$this->log_sync_start();

		// Register sync with BRAG book API
		$registration_result = $this->register_sync_with_api();
		if ( $registration_result ) {
			error_log( 'BRAG book Gallery Sync: Registered with BRAG book API - Job ID: ' . ( $registration_result['job_id'] ?? 'unknown' ) );
		}

		// Report sync as IN_PROGRESS
		$this->report_sync_status(
			Sync_Api::STATUS_IN_PROGRESS,
			0,
			'Starting two-stage sync'
		);

		// Get current limits (we can't change them on WP Engine)
		$original_time_limit   = ini_get( 'max_execution_time' );
		$original_memory_limit = ini_get( 'memory_limit' );

		// Adjust our internal limits based on what's available
		if ( $original_time_limit > 0 && $original_time_limit < 60 ) {
			// If we have less than 60 seconds, use 80% of available time
			$this->max_execution_time = intval( $original_time_limit * 0.8 );
		}

		try {
			// STAGE 1: Sync procedures from sidebar API
			error_log( 'BRAG book Gallery Sync: ===== STAGE 1: PROCEDURE SYNC =====' );
			$stage1_result = $this->sync_procedures_stage1();

			if ( ! $stage1_result['success'] ) {
				error_log( 'BRAG book Gallery Sync: Stage 1 failed, aborting sync' );
				$this->log_sync_error( 'Stage 1 (procedures) failed: ' . implode( ', ', $stage1_result['errors'] ) );

				// Report failure to BRAG book API
				$this->report_sync_status(
					Sync_Api::STATUS_FAILED,
					0,
					'Stage 1 (procedures) sync failed',
					implode( "\n", $stage1_result['errors'] )
				);

				return $stage1_result;
			}

			$total_result             = $stage1_result;
			$total_result['warnings'] = []; // Initialize warnings array

			// STAGE 2: Sync cases (if requested)
			if ( $include_cases ) {
				// Get sidebar data file from stage 1 result
				if ( empty( $stage1_result['sidebar_data_file'] ) || ! file_exists( $stage1_result['sidebar_data_file'] ) ) {
					error_log( 'BRAG book Gallery Sync: ERROR - Sidebar data file not found from stage 1' );
					throw new Exception( 'Sidebar data file not available for stage 2' );
				}

				// Read sidebar data from file
				error_log( 'BRAG book Gallery Sync: Loading sidebar data from file: ' . basename( $stage1_result['sidebar_data_file'] ) );
				$sidebar_json = file_get_contents( $stage1_result['sidebar_data_file'] );
				if ( $sidebar_json === false ) {
					throw new Exception( 'Failed to read sidebar data file' );
				}

				$sidebar_data = json_decode( $sidebar_json, true );
				unset( $sidebar_json ); // Free memory immediately

				if ( json_last_error() !== JSON_ERROR_NONE ) {
					throw new Exception( 'Invalid JSON in sidebar data file' );
				}

				$stage2_result = $this->sync_cases_stage2( $sidebar_data );
				// Clear sidebar data after use
				unset( $sidebar_data );

				// Clean up the sync file after successful completion
				if ( ! empty( $stage1_result['sidebar_data_file'] ) && file_exists( $stage1_result['sidebar_data_file'] ) ) {
					@unlink( $stage1_result['sidebar_data_file'] );
					error_log( 'BRAG book Gallery Sync: Cleaned up sync data file: ' . basename( $stage1_result['sidebar_data_file'] ) );
				}

				// Merge results.
				$total_result['cases_created']         = $stage2_result['created'];
				$total_result['cases_updated']         = $stage2_result['updated'];
				$total_result['cases_errors']          = $stage2_result['errors'];
				$total_result['cases_warnings']        = $stage2_result['warnings'] ?? [];
				$total_result['total_cases_processed'] = $stage2_result['total_processed'];
				// No longer tracking duplicates.
				$total_result['duplicate_occurrences'] = $stage2_result['duplicate_occurrences'] ?? 0;
				$total_result['errors']                = array_merge( $total_result['errors'], $stage2_result['errors'] );
				$total_result['warnings']              = array_merge( $total_result['warnings'] ?? [], $stage2_result['warnings'] ?? [] );

				// Update success status - only fail on actual errors, not warnings
				if ( ! empty( $stage2_result['errors'] ) ) {
					$total_result['success'] = false;
				}
			}

			$total_errors = count( $total_result['errors'] );
			$success      = empty( $total_result['errors'] );

			error_log( 'BRAG book Gallery Sync: ===== FINAL SYNC SUMMARY =====' );
			error_log( "BRAG book Gallery Sync: Status: " . ( $success ? 'SUCCESS' : 'COMPLETED WITH ERRORS' ) );
			error_log( "BRAG book Gallery Sync: Procedures created: {$total_result['created']}" );
			error_log( "BRAG book Gallery Sync: Procedures updated: {$total_result['updated']}" );
			if ( $include_cases ) {
				error_log( "BRAG book Gallery Sync: Cases created: {$total_result['cases_created']}" );
				error_log( "BRAG book Gallery Sync: Cases updated: {$total_result['cases_updated']}" );
				error_log( "BRAG book Gallery Sync: Total cases processed: {$total_result['total_cases_processed']}" );
			}
			error_log( "BRAG book Gallery Sync: Total errors: {$total_errors}" );

			$total_warnings = count( $total_result['warnings'] ?? [] );
			if ( $total_warnings > 0 ) {
				error_log( "BRAG book Gallery Sync: Total warnings: {$total_warnings}" );
			}

			if ( ! empty( $total_result['errors'] ) ) {
				error_log( 'BRAG book Gallery Sync: Error details:' );
				foreach ( $total_result['errors'] as $error ) {
					error_log( 'BRAG book Gallery Sync: - ' . $error );
				}
			}

			if ( ! empty( $total_result['warnings'] ) ) {
				error_log( 'BRAG book Gallery Sync: Warning details:' );
				foreach ( $total_result['warnings'] as $warning ) {
					error_log( 'BRAG book Gallery Sync: - ' . $warning );
				}
			}

			// Ensure My Favorites page exists after successful sync
			$this->ensure_favorites_page_exists();

			// Clear sync state on successful completion
			$this->clear_sync_state();

			error_log( 'BRAG book Gallery Sync: ===== SYNC COMPLETE =====' );

			// Report sync status to BRAG book API
			$cases_synced  = ( $total_result['cases_created'] ?? 0 ) + ( $total_result['cases_updated'] ?? 0 );
			$sync_status   = empty( $total_result['errors'] ) ? Sync_Api::STATUS_SUCCESS : Sync_Api::STATUS_PARTIAL;
			$status_message = sprintf(
				'Synced %d procedures (%d created, %d updated) and %d cases (%d created, %d updated)',
				( $total_result['created'] ?? 0 ) + ( $total_result['updated'] ?? 0 ),
				$total_result['created'] ?? 0,
				$total_result['updated'] ?? 0,
				$cases_synced,
				$total_result['cases_created'] ?? 0,
				$total_result['cases_updated'] ?? 0
			);

			$this->report_sync_status(
				$sync_status,
				$cases_synced,
				$status_message,
				! empty( $total_result['errors'] ) ? implode( "\n", $total_result['errors'] ) : ''
			);

			$this->log_sync_complete( $total_result );

			// Auto-detect and delete orphaned items
			if ( $this->database ) {
				try {
					$orphan_manager = new Orphan_Manager( $this->database );
					$orphans = $orphan_manager->detect_orphans( $this->sync_session_id, $this->get_registry_api_token() );
					if ( ! empty( $orphans ) ) {
						$orphan_result = $orphan_manager->delete_orphaned_items( $orphans, $this->sync_session_id );
						error_log( sprintf(
							'BRAG book Gallery Sync: Auto-deleted %d orphaned items (%d errors)',
							$orphan_result['deleted'],
							count( $orphan_result['errors'] )
						) );
					}
				} catch ( \Exception $e ) {
					error_log( 'BRAG book Gallery Sync: Orphan cleanup failed: ' . $e->getMessage() );
				}
			}

			// Clear sync start time transient
			delete_transient( 'brag_book_gallery_sync_start_time' );

			return $total_result;

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Sync failed with exception: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Exception stack trace: ' . $e->getTraceAsString() );
			$this->log_sync_error( $e->getMessage() );

			// Report failure to BRAG book API
			$this->report_sync_status(
				Sync_Api::STATUS_FAILED,
				0,
				'Sync failed with exception',
				$e->getMessage() . "\n" . $e->getTraceAsString()
			);

			// Clean up any sync data files on error
			if ( ! empty( $stage1_result['sidebar_data_file'] ) && file_exists( $stage1_result['sidebar_data_file'] ) ) {
				@unlink( $stage1_result['sidebar_data_file'] );
				error_log( 'BRAG book Gallery Sync: Cleaned up sync data file on error: ' . basename( $stage1_result['sidebar_data_file'] ) );
			}

			// Clear sync state on error
			$this->clear_sync_state();
			throw $e;
		}
	}

	/**
	 * Stage 1: Sync procedures from sidebar API
	 *
	 * @return array Sync results with sidebar data
	 * @throws Exception If sync fails
	 * @since 3.0.0
	 */
	/**
	 * Get the sync data directory path
	 *
	 * @return string Directory path for sync data
	 * @since 3.0.0
	 */
	private function get_sync_data_dir(): string {
		$upload_dir = wp_upload_dir();
		$sync_dir   = $upload_dir['basedir'] . '/brag-book-gallery-sync';

		error_log( 'BRAG book Gallery Sync: Sync data directory: ' . $sync_dir );

		// Create directory if it doesn't exist
		if ( ! file_exists( $sync_dir ) ) {
			error_log( 'BRAG book Gallery Sync: Creating sync data directory: ' . $sync_dir );
			$created = wp_mkdir_p( $sync_dir );
			if ( ! $created ) {
				error_log( 'BRAG book Gallery Sync: ERROR - Failed to create sync directory' );
			}
			// Add .htaccess to protect directory
			file_put_contents( $sync_dir . '/.htaccess', 'Deny from all' );
		}

		return $sync_dir;
	}

	/**
	 * Clean up old sync data files
	 *
	 * @param int $max_age_hours Maximum age in hours (default 24)
	 *
	 * @since 3.0.0
	 */
	private function cleanup_old_sync_files( int $max_age_hours = 24 ): void {
		$sync_dir = $this->get_sync_data_dir();
		$max_age  = time() - ( $max_age_hours * 3600 );

		$files = glob( $sync_dir . '/sync-data-*.json' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( filemtime( $file ) < $max_age ) {
					@unlink( $file );
					error_log( 'BRAG book Gallery Sync: Cleaned up old sync file: ' . basename( $file ) );
				}
			}
		}
	}

	private function sync_procedures_stage1(): array {
		try {
			// Log initial memory usage
			$initial_memory = memory_get_usage( true );
			$initial_peak   = memory_get_peak_usage( true );
			error_log( sprintf(
				'BRAG book Gallery Sync: [MEMORY] Starting procedures sync - Current: %s, Peak: %s',
				size_format( $initial_memory ),
				size_format( $initial_peak )
			) );

			// Ensure taxonomy is available before sync
			$this->ensure_taxonomy_registered();

			// Clear any autoloaded options cache before API call
			wp_cache_delete( 'alloptions', 'options' );
			$this->aggressive_memory_cleanup();

			$memory_before_api = memory_get_usage( true );
			error_log( sprintf(
				'BRAG book Gallery Sync: [MEMORY] Before API call - Current: %s (diff: %s)',
				size_format( $memory_before_api ),
				size_format( $memory_before_api - $initial_memory )
			) );

			error_log( 'BRAG book Gallery Sync: Step 1 - Connecting to BRAGBook API...' );
			$api_data = $this->fetch_api_data();

			$memory_after_api = memory_get_usage( true );
			error_log( sprintf(
				'BRAG book Gallery Sync: [MEMORY] After API call - Current: %s (diff: %s)',
				size_format( $memory_after_api ),
				size_format( $memory_after_api - $memory_before_api )
			) );
			error_log( 'BRAG book Gallery Sync: ✓ Successfully connected to API' );

			// Save API data to file immediately to free memory
			$sync_dir  = $this->get_sync_data_dir();
			$sync_file = $sync_dir . '/sync-data-' . date( 'Y-m-d-His' ) . '-' . wp_generate_password( 8, false ) . '.json';

			error_log( 'BRAG book Gallery Sync: Saving API data to file: ' . basename( $sync_file ) );
			$bytes_written = file_put_contents( $sync_file, wp_json_encode( $api_data ) );

			if ( $bytes_written === false ) {
				error_log( 'BRAG book Gallery Sync: ERROR - Failed to save API data to file' );
				throw new Exception( 'Failed to save API data to file' );
			}

			error_log( sprintf(
				'BRAG book Gallery Sync: ✓ Saved %s of API data to file',
				size_format( $bytes_written )
			) );

			// Clean up old sync files
			$this->cleanup_old_sync_files();

			// Keep only the data array in memory for processing, clear everything else
			$categories_data = $api_data['data'];
			unset( $api_data );
			gc_collect_cycles();

			$memory_after_save = memory_get_usage( true );
			error_log( sprintf(
				'BRAG book Gallery Sync: [MEMORY] After saving to file and cleanup - Current: %s (freed: %s)',
				size_format( $memory_after_save ),
				size_format( $memory_after_api - $memory_after_save )
			) );

			error_log( 'BRAG book Gallery Sync: Step 2 - Analyzing API response...' );

			if ( empty( $categories_data ) ) {
				error_log( 'BRAG book Gallery Sync: ✗ No procedure data found in API response' );
				throw new Exception( __( 'No data received from API', 'brag-book-gallery' ) );
			}

			$total_categories = count( $categories_data );
			error_log( 'BRAG book Gallery Sync: ✓ Found ' . $total_categories . ' procedure categories to process' );

			$created_procedures = [];
			$updated_procedures = [];
			$errors             = [];
			$category_count     = 0;

			error_log( 'BRAG book Gallery Sync: Step 3 - Processing procedure categories...' );

			foreach ( $categories_data as $category ) {
				$category_count ++;
				$category_name = $category['name'] ?? 'Unknown';

				// Log memory at start of EVERY category to track the spike
				$current_memory = memory_get_usage( true );
				$current_real   = memory_get_usage( false );
				$memory_limit   = $this->get_memory_limit_bytes();
				error_log( sprintf(
					'BRAG book Gallery Sync: [MEMORY-CAT] Category %d/%d "%s" - Reserved: %s, Real: %s (%.1f%% of limit)',
					$category_count,
					$total_categories,
					$category_name,
					size_format( $current_memory ),
					size_format( $current_real ),
					( $current_memory / $memory_limit ) * 100
				) );

				try {
					error_log( "BRAG book Gallery Sync: Processing category {$category_count}/{$total_categories}: '{$category_name}'" );

					// Count child procedures
					$child_count = isset( $category['procedures'] ) ? count( $category['procedures'] ) : 0;
					error_log( "BRAG book Gallery Sync: Category '{$category_name}' contains {$child_count} procedures" );

					$result             = $this->process_category( $category );
					$created_procedures = array_merge( $created_procedures, $result['created'] );
					$updated_procedures = array_merge( $updated_procedures, $result['updated'] );

					$created_in_category = count( $result['created'] );
					$updated_in_category = count( $result['updated'] );
					error_log( "BRAG book Gallery Sync: ✓ Category '{$category_name}' processed - Created: {$created_in_category}, Updated: {$updated_in_category}" );

					// Clean up memory after each category if memory usage is high
					$current_memory = memory_get_usage( true );
					$memory_limit   = $this->get_memory_limit_bytes();
					if ( $current_memory > ( $memory_limit * 0.5 ) ) { // If using more than 50% of limit
						error_log( sprintf(
							'BRAG book Gallery Sync: [MEMORY] High memory usage detected (%s/%s), running cleanup',
							size_format( $current_memory ),
							size_format( $memory_limit )
						) );
						$this->aggressive_memory_cleanup();
						$memory_after_cleanup = memory_get_usage( true );
						error_log( sprintf(
							'BRAG book Gallery Sync: [MEMORY] After cleanup - Current: %s (freed: %s)',
							size_format( $memory_after_cleanup ),
							size_format( $current_memory - $memory_after_cleanup )
						) );
					}

				} catch ( Exception $e ) {
					$error_msg = sprintf(
						__( 'Error processing category "%s": %s', 'brag-book-gallery' ),
						$category_name,
						$e->getMessage()
					);
					$errors[]  = $error_msg;
					error_log( "BRAG book Gallery Sync: ✗ Failed to process category '{$category_name}': " . $e->getMessage() );
				}
			}

			// Clear categories data and run garbage collection
			unset( $categories_data );
			gc_collect_cycles();

			$total_created = count( $created_procedures );
			$total_updated = count( $updated_procedures );
			$total_errors  = count( $errors );
			$success       = empty( $errors );

			error_log( 'BRAG book Gallery Sync: Step 4 - Stage 1 completed' );
			error_log( "BRAG book Gallery Sync: Stage 1 Status: " . ( $success ? 'SUCCESS' : 'COMPLETED WITH ERRORS' ) );
			error_log( "BRAG book Gallery Sync: Categories processed: {$total_categories}" );
			error_log( "BRAG book Gallery Sync: Procedures created: {$total_created}" );
			error_log( "BRAG book Gallery Sync: Procedures updated: {$total_updated}" );
			error_log( "BRAG book Gallery Sync: Errors encountered: {$total_errors}" );

			// Final memory report
			$final_memory = memory_get_usage( true );
			$final_peak   = memory_get_peak_usage( true );
			error_log( sprintf(
				'BRAG book Gallery Sync: [MEMORY] Stage 1 complete - Current: %s, Peak: %s',
				size_format( $final_memory ),
				size_format( $final_peak )
			) );

			return [
				'success'           => $success,
				'created'           => $total_created,
				'updated'           => $total_updated,
				'errors'            => $errors,
				'sidebar_data_file' => $sync_file, // Pass file path instead of data
				'details'           => [
					'created_procedures'   => $created_procedures,
					'updated_procedures'   => $updated_procedures,
					'categories_processed' => $total_categories,
				],
			];

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Stage 1 failed with exception: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Fetch data from BRAGBook API
	 *
	 * @return array API response data
	 * @throws Exception If API request fails
	 * @since 3.0.0
	 */
	private function fetch_api_data(): array {
		// Log memory at start of API call
		$initial_memory = memory_get_usage( true );
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY] fetch_api_data() start - Current: %s',
			size_format( $initial_memory )
		) );

		$endpoint = '/api/plugin/combine/sidebar';

		error_log( 'BRAG book Gallery Sync: Preparing API request to: ' . $endpoint );

		// Get API tokens for authentication
		error_log( 'BRAG book Gallery Sync: Retrieving API credentials...' );
		$mem_before_option = memory_get_usage( true );
		$api_tokens        = get_option( 'brag_book_gallery_api_token', [] );
		$mem_after_option  = memory_get_usage( true );
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY] After get_option() - Current: %s (diff: %s)',
			size_format( $mem_after_option ),
			size_format( $mem_after_option - $mem_before_option )
		) );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( 'BRAG book Gallery Sync: ✗ No API tokens configured in settings' );
			throw new Exception( __( 'No API tokens configured. Please configure API settings first.', 'brag-book-gallery' ) );
		}

		// Filter out empty tokens
		$valid_tokens = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );

		if ( empty( $valid_tokens ) ) {
			error_log( 'BRAG book Gallery Sync: ✗ No valid API tokens found after filtering' );
			throw new Exception( __( 'No valid API tokens found. Please configure API settings first.', 'brag-book-gallery' ) );
		}

		error_log( 'BRAG book Gallery Sync: ✓ Found ' . count( $valid_tokens ) . ' valid API token(s)' );
		error_log( 'BRAG book Gallery Sync: Using primary token: ' . substr( $valid_tokens[0], 0, 10 ) . '...' );

		// Build full URL for POST request
		$api_base_url = $this->get_api_base_url();
		$full_url     = $api_base_url . $endpoint;

		// Prepare request body with API tokens (only apiTokens needed for sidebar endpoint)
		$request_body = [
			'apiTokens' => array_values( $valid_tokens ),
		];

		error_log( 'BRAG book Gallery Sync: Connecting to: ' . $full_url );
		error_log( 'BRAG book Gallery Sync: Request payload: ' . wp_json_encode( $request_body ) );

		// Make POST request with authentication in body
		error_log( 'BRAG book Gallery Sync: Sending POST request to BRAGBook API...' );
		$start_time = microtime( true );

		$response = wp_remote_post( $full_url, [
			'timeout'   => 30,
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body'      => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );

		$request_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code    = $response->get_error_code();
			error_log( "BRAG book Gallery Sync: ✗ API request failed after {$request_time}ms (code: {$error_code}): {$error_message}" );
			error_log( 'BRAG book Gallery Sync: Failed request URL: ' . $full_url );
			error_log( 'BRAG book Gallery Sync: Failed request body: ' . wp_json_encode( $request_body ) );
			throw new Exception( sprintf(
				__( 'API request failed [%s]: %s', 'brag-book-gallery' ),
				$error_code,
				$error_message
			) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log( 'BRAG book Gallery Sync: ✓ API responded in ' . $request_time . 'ms with status: ' . $response_code );
		error_log( 'BRAG book Gallery Sync: Response size: ' . strlen( $response_body ) . ' bytes' );
		error_log( 'BRAG book Gallery Sync: Response preview: ' . substr( $response_body, 0, 150 ) . '...' );

		// Check for HTML error pages (common cause of JSON parse errors)
		if ( stripos( $response_body, '<html' ) !== false || stripos( $response_body, '<!DOCTYPE' ) !== false ) {
			error_log( 'BRAG book Gallery Sync: WARNING - Response appears to be HTML instead of JSON' );
			error_log( 'BRAG book Gallery Sync: Content-Type header: ' . wp_remote_retrieve_header( $response, 'content-type' ) );
			error_log( 'BRAG book Gallery Sync: Full HTML response: ' . $response_body );
		}

		if ( $response_code !== 200 ) {
			error_log( 'BRAG book Gallery Sync: API returned non-200 status: ' . $response_code );
			throw new Exception( sprintf(
				__( 'API returned error status %d', 'brag-book-gallery' ),
				$response_code
			) );
		}

		// Log memory before JSON decode
		$memory_before_decode = memory_get_usage( true );
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY] Before JSON decode - Current: %s, Response size: %s',
			size_format( $memory_before_decode ),
			size_format( strlen( $response_body ) )
		) );

		$data = json_decode( $response_body, true );

		// Clear the response body immediately after decoding to save memory
		unset( $response_body );
		unset( $response );
		gc_collect_cycles();

		$memory_after_decode = memory_get_usage( true );
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY] After JSON decode and cleanup - Current: %s (diff: %s)',
			size_format( $memory_after_decode ),
			size_format( $memory_after_decode - $memory_before_decode )
		) );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'BRAG book Gallery Sync: JSON decode error: ' . json_last_error_msg() );
			error_log( 'BRAG book Gallery Sync: Response body (first 500 chars): ' . substr( $response_body, 0, 500 ) );
			error_log( 'BRAG book Gallery Sync: Response body length: ' . strlen( $response_body ) );
			error_log( 'BRAG book Gallery Sync: Response content type: ' . wp_remote_retrieve_header( $response, 'content-type' ) );
			throw new Exception( sprintf(
				__( 'Invalid JSON response from API: %s. Response preview: %s', 'brag-book-gallery' ),
				json_last_error_msg(),
				substr( $response_body, 0, 100 )
			) );
		}

		error_log( 'BRAG book Gallery Sync: API response data keys: ' . wp_json_encode( array_keys( $data ) ) );

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			error_log( 'BRAG book Gallery Sync: API returned unsuccessful response. Data: ' . wp_json_encode( $data ) );
			throw new Exception( __( 'API returned unsuccessful response', 'brag-book-gallery' ) );
		}

		// Final memory report for API call
		$final_api_memory = memory_get_usage( true );
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY] fetch_api_data() complete - Current: %s (total diff: %s)',
			size_format( $final_api_memory ),
			size_format( $final_api_memory - $initial_memory )
		) );

		return $data;
	}

	/**
	 * Process a category and its procedures
	 *
	 * @param array $category Category data from API
	 *
	 * @return array Processing results
	 * @throws Exception If processing fails
	 * @since 3.0.0
	 */
	private function process_category( array $category ): array {
		$created = [];
		$updated = [];

		// Create parent category
		$parent_result = $this->create_or_update_procedure( $category, null );
		if ( $parent_result['created'] ) {
			$created[] = $parent_result;
		} else {
			$updated[] = $parent_result;
		}

		$parent_term_id = $parent_result['term_id'];

		// Process child procedures
		if ( ! empty( $category['procedures'] ) ) {
			foreach ( $category['procedures'] as $procedure ) {
				try {
					$child_result = $this->create_or_update_procedure( $procedure, $parent_term_id );
					if ( $child_result['created'] ) {
						$created[] = $child_result;
					} else {
						$updated[] = $child_result;
					}
					// Clear procedure data after processing
					unset( $procedure );
				} catch ( Exception $e ) {
					error_log( sprintf(
						'Error processing procedure "%s": %s',
						$procedure['name'] ?? 'Unknown',
						$e->getMessage()
					) );
				}
			}
			// Clear procedures array from memory after processing
			unset( $category['procedures'] );
		}

		// Clear category data from memory and run garbage collection
		unset( $category );
		gc_collect_cycles();

		return [
			'created' => $created,
			'updated' => $updated,
		];
	}

	/**
	 * Create or update a procedure term
	 *
	 * @param array $data Procedure data from API
	 * @param int|null $parent_id Parent term ID (null for parent categories)
	 *
	 * @return array Operation result
	 * @throws Exception If operation fails
	 * @since 3.0.0
	 */
	private function create_or_update_procedure( array $data, ?int $parent_id = null ): array {
		$slug                 = $data['slugName'] ?? sanitize_title( $data['name'] );
		$name                 = $data['name'];
		$original_description = $data['description'] ?? '';

		// Set term description to shortcode for procedure view
		$term_description = '[brag_book_gallery view="procedure"]';

		// Check if term already exists
		$existing_term = get_term_by( 'slug', $slug, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );

		if ( $existing_term ) {
			// Update existing term
			$term_id = $existing_term->term_id;
			$updated = wp_update_term( $term_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES, [
				'name'        => $name,
				'description' => $term_description,
				'parent'      => $parent_id ?? 0,
			] );

			if ( is_wp_error( $updated ) ) {
				throw new Exception( sprintf(
					__( 'Failed to update term "%s": %s', 'brag-book-gallery' ),
					$name,
					$updated->get_error_message()
				) );
			}

			$created = false;
		} else {
			// Create new term
			$inserted = wp_insert_term( $name, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES, [
				'description' => $term_description,
				'slug'        => $slug,
				'parent'      => $parent_id ?? 0,
			] );

			if ( is_wp_error( $inserted ) ) {
				throw new Exception( sprintf(
					__( 'Failed to create term "%s": %s', 'brag-book-gallery' ),
					$name,
					$inserted->get_error_message()
				) );
			}

			$term_id = $inserted['term_id'];
			$created = true;
		}

		// Update term meta
		$this->update_procedure_meta( $term_id, $data );

		// Log the operation
		$this->log_procedure_operation( $term_id, $data, $created, $parent_id );

		// Register in sync registry
		if ( $this->database && ! empty( $data['ids'] ) && is_array( $data['ids'] ) ) {
			$this->database->upsert_registry_item(
				'procedure',
				(int) $data['ids'][0],
				$term_id,
				'term',
				$this->get_registry_api_token(),
				$this->get_registry_property_id(),
				$this->sync_session_id
			);
		}

		return [
			'term_id'   => $term_id,
			'name'      => $name,
			'slug'      => $slug,
			'created'   => $created,
			'parent_id' => $parent_id,
			'api_data'  => $data,
		];
	}

	/**
	 * Update procedure term meta
	 *
	 * @param int $term_id Term ID
	 * @param array $data API data
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function update_procedure_meta( int $term_id, array $data ): void {
		// Update procedure ID (from API ids array or generate from slug)
		if ( ! empty( $data['ids'] ) && is_array( $data['ids'] ) ) {
			update_term_meta( $term_id, 'procedure_id', $data['ids'][0] );
		}

		// Update member ID (not available in this API response, but preserve field)
		if ( isset( $data['member_id'] ) ) {
			update_term_meta( $term_id, 'member_id', sanitize_text_field( $data['member_id'] ) );
		}

		// Update nudity flag
		$nudity = isset( $data['nudity'] ) && $data['nudity'] ? 'true' : 'false';
		update_term_meta( $term_id, 'nudity', $nudity );

		// Update gallery details from API description
		if ( isset( $data['description'] ) ) {
			$gallery_details = wp_kses_post( $data['description'] );
			if ( ! empty( $gallery_details ) ) {
				update_term_meta( $term_id, 'brag_book_gallery_details', $gallery_details );
			} else {
				delete_term_meta( $term_id, 'brag_book_gallery_details' );
			}
		}

		// HIPAA COMPLIANCE: Do NOT store full API data as it may contain PHI
		// Only store essential non-PHI operational data
		$safe_api_data = [
			'api_id'      => $data['ids'][0] ?? null,
			'slug'        => $data['slugName'] ?? '',
			'total_cases' => $data['totalCase'] ?? 0,
			'sync_date'   => current_time( 'mysql' ),
		];
		update_term_meta( $term_id, 'api_data', $safe_api_data );

		// Store total case count
		if ( isset( $data['totalCase'] ) ) {
			update_term_meta( $term_id, 'total_cases', absint( $data['totalCase'] ) );
		}
	}

	/**
	 * Log procedure operation to sync table
	 *
	 * @param int $term_id Term ID
	 * @param array $data API data
	 * @param bool $created Whether term was created or updated
	 * @param int|null $parent_id Parent term ID
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_procedure_operation( int $term_id, array $data, bool $created, ?int $parent_id ): void {
		global $wpdb;

		// HIPAA Compliance: Only log operational data for procedures (no patient data)
		// Procedure names are safe as they don't contain PHI (e.g., "Breast Augmentation")
		$operation_type = $created ? 'create' : 'update';
		$item_type      = $parent_id ? 'child_procedure' : 'parent_category';

		// Create safe details with only operational info
		$safe_details = sprintf(
			'%s %s: Term ID %d, API ID %d, Slug: %s',
			ucfirst( $operation_type ),
			$item_type,
			$term_id,
			$data['ids'][0] ?? 0,
			$data['slugName'] ?? sanitize_title( $data['name'] )
		);

		$wpdb->insert(
			$this->log_table,
			[
				'sync_session_id' => $this->sync_session_id,
				'sync_type'       => 'procedure',
				'operation'       => $operation_type,
				'item_type'       => $item_type,
				'wordpress_id'    => $term_id,
				'api_id'          => $data['ids'][0] ?? null,
				'slug'            => $data['slugName'] ?? sanitize_title( $data['name'] ),
				'parent_id'       => $parent_id,
				'status'          => 'success',
				'details'         => $safe_details,
				'created_at'      => current_time( 'mysql' ),
			],
			[
				'%s', // sync_session_id
				'%s', // sync_type
				'%s', // operation
				'%s', // item_type
				'%d', // wordpress_id
				'%d', // api_id
				'%s', // slug
				'%d', // parent_id
				'%s', // status
				'%s', // details
				'%s', // created_at
			]
		);
	}

	/**
	 * Log sync start
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_sync_start(): void {
		global $wpdb;

		$wpdb->insert(
			$this->log_table,
			[
				'sync_session_id' => $this->sync_session_id,
				'sync_type'       => 'procedure_sync',
				'operation'       => 'start',
				'item_type'       => 'sync_session',
				'status'          => 'running',
				'details'         => 'Procedure sync started',
				'created_at'      => current_time( 'mysql' ),
			],
			[
				'%s', // sync_session_id
				'%s', // sync_type
				'%s', // operation
				'%s', // item_type
				'%s', // status
				'%s', // details
				'%s', // created_at
			]
		);
	}

	/**
	 * Log sync completion
	 *
	 * @param array $result Sync results
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_sync_complete( array $result ): void {
		global $wpdb;

		// Calculate sync duration
		$start_time = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(created_at) FROM {$this->log_table} WHERE sync_session_id = %s",
			$this->sync_session_id
		) );

		$duration = '';
		if ( $start_time ) {
			$start_timestamp  = strtotime( $start_time );
			$end_timestamp    = time();
			$duration_seconds = abs( $end_timestamp - $start_timestamp ); // Use abs to avoid negative values

			if ( $duration_seconds < 60 ) {
				$duration = $duration_seconds . ' second' . ( $duration_seconds !== 1 ? 's' : '' );
			} else {
				$minutes  = floor( $duration_seconds / 60 );
				$seconds  = $duration_seconds % 60;
				$duration = $minutes . ' minute' . ( $minutes !== 1 ? 's' : '' );
				if ( $seconds > 0 ) {
					$duration .= ' ' . $seconds . ' second' . ( $seconds !== 1 ? 's' : '' );
				}
			}
		}

		// Get all individual operations for this sync session to build a detailed log
		$operations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->log_table}
			WHERE sync_session_id = %s
			ORDER BY created_at ASC",
			$this->sync_session_id
		) );

		// Build detailed activity log
		$activity_log = [];
		foreach ( $operations as $op ) {
			if ( $op->operation !== 'complete' && $op->operation !== 'start' ) {
				$activity_log[] = [
					'time'      => $op->created_at,
					'type'      => $op->sync_type,
					'operation' => $op->operation,
					'item'      => $op->item_type,
					'status'    => $op->status,
					'details'   => $op->details
				];
			}
		}

		// Enhanced result with all sync details
		$enhanced_result                     = $result;
		$enhanced_result['sync_session_id']  = $this->sync_session_id;
		$enhanced_result['duration']         = $duration;
		$enhanced_result['start_time']       = $start_time;
		$enhanced_result['end_time']         = current_time( 'mysql' );
		$enhanced_result['activity_log']     = $activity_log;
		$enhanced_result['total_operations'] = count( $operations );

		// Extract duplicate information if available
		// No longer tracking duplicates
		if ( isset( $result['duplicate_occurrences'] ) ) {
			$enhanced_result['duplicate_occurrences'] = $result['duplicate_occurrences']; // Total duplicate skips
		}
		if ( isset( $result['details']['duplicate_case_ids'] ) ) {
			$enhanced_result['duplicate_ids'] = $result['details']['duplicate_case_ids'];
		}

		// Calculate total cases attempted (created + updated + duplicate occurrences)
		$cases_created                      = $result['cases_created'] ?? 0;
		$cases_updated                      = $result['cases_updated'] ?? 0;
		$duplicate_occurrences              = $result['duplicate_occurrences'] ?? 0;
		$enhanced_result['cases_attempted'] = $cases_created + $cases_updated + $duplicate_occurrences;

		// Insert single consolidated sync record
		$wpdb->insert(
			$this->log_table,
			[
				'sync_session_id' => $this->sync_session_id,
				'sync_type'       => 'full_sync',
				'operation'       => 'complete',
				'item_type'       => 'sync_session',
				'items_created'   => ( $result['created'] ?? 0 ) + ( $result['cases_created'] ?? 0 ),
				'items_updated'   => ( $result['updated'] ?? 0 ) + ( $result['cases_updated'] ?? 0 ),
				'status'          => $result['success'] ? 'completed' : 'failed',
				'details'         => wp_json_encode( $enhanced_result ),
				'created_at'      => $start_time ?: current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			],
			[
				'%s', // sync_session_id
				'%s', // sync_type
				'%s', // operation
				'%s', // item_type
				'%d', // items_created
				'%d', // items_updated
				'%s', // status
				'%s', // details
				'%s', // created_at
				'%s', // updated_at
			]
		);

		// Clean up individual operation logs - keep only the summary
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->log_table}
			WHERE sync_session_id = %s
			AND operation != 'complete'",
			$this->sync_session_id
		) );

		// Clean up the temporary activity log after saving to history
		delete_option( 'brag_book_gallery_completed_sync_log' );
	}

	/**
	 * Log sync error
	 *
	 * @param string $error_message Error message
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_sync_error( string $error_message ): void {
		global $wpdb;

		$wpdb->insert(
			$this->log_table,
			[
				'sync_session_id' => $this->sync_session_id,
				'sync_type'       => 'procedure_sync',
				'operation'       => 'error',
				'item_type'       => 'sync_session',
				'status'          => 'error',
				'details'         => $error_message,
				'created_at'      => current_time( 'mysql' ),
			],
			[
				'%s', // sync_session_id
				'%s', // sync_type
				'%s', // operation
				'%s', // item_type
				'%s', // status
				'%s', // details
				'%s', // created_at
			]
		);
	}

	/**
	 * Create HIPAA-compliant sync log table if it doesn't exist
	 *
	 * HIPAA Compliance Notes:
	 * - Removed 'name' field to prevent storing patient identifiers
	 * - Removed 'api_data' field to prevent storing PHI
	 * - Only stores operational data: IDs, counts, status, timestamps
	 * - No patient information or case details are logged
	 * - Logs are automatically purged after 90 days for data minimization
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function maybe_create_sync_table(): void {
		error_log( 'BRAG book Gallery Sync: Starting maybe_create_sync_table (HIPAA-compliant version)' );
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		error_log( 'BRAG book Gallery Sync: Got charset collate: ' . $charset_collate );

		$sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sync_session_id varchar(50) NOT NULL,
			sync_type varchar(50) NOT NULL,
			operation varchar(20) NOT NULL,
			item_type varchar(50) NOT NULL,
			wordpress_id bigint(20) unsigned NULL,
			api_id bigint(20) unsigned NULL,
			slug varchar(200) NULL,
			parent_id bigint(20) unsigned NULL,
			items_created int(11) DEFAULT 0,
			items_updated int(11) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			details text NULL COMMENT 'Non-PHI operational details only',
			created_at datetime NOT NULL,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY sync_session_id (sync_session_id),
			KEY sync_type (sync_type),
			KEY wordpress_id (wordpress_id),
			KEY api_id (api_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		error_log( 'BRAG book Gallery Sync: About to require upgrade.php and run dbDelta' );
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		error_log( 'BRAG book Gallery Sync: dbDelta completed, HIPAA-compliant table creation finished' );

		// Schedule log cleanup for HIPAA compliance
		$this->schedule_log_cleanup();
	}

	/**
	 * Schedule automatic log cleanup for HIPAA compliance
	 *
	 * HIPAA requires data minimization - keeping logs only as long as necessary
	 * Logs older than 90 days are automatically purged
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function schedule_log_cleanup(): void {
		if ( ! wp_next_scheduled( 'brag_book_gallery_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'brag_book_gallery_cleanup_logs' );
		}

		// Add the cleanup action if not already added
		if ( ! has_action( 'brag_book_gallery_cleanup_logs', [ $this, 'cleanup_old_logs' ] ) ) {
			add_action( 'brag_book_gallery_cleanup_logs', [ $this, 'cleanup_old_logs' ] );
		}
	}

	/**
	 * Clean up old sync logs for HIPAA compliance
	 *
	 * Removes logs older than 90 days to comply with data minimization requirements
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		// Delete logs older than 90 days
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->log_table} WHERE created_at < %s",
				$cutoff_date
			)
		);

		if ( $deleted > 0 ) {
			error_log( "BRAG book Gallery Sync: HIPAA compliance cleanup - Deleted {$deleted} old log entries" );
		}
	}

	/**
	 * Stage 2: Sync cases from API
	 *
	 * @param array $sidebar_data Sidebar API data from stage 1
	 *
	 * @return array Sync results
	 * @since 3.0.0
	 */
	private function sync_cases_stage2( array $sidebar_data ): array {
		try {
			error_log( 'BRAG book Gallery Sync: Starting case synchronization...' );

			// Clear all memory before starting
			$this->aggressive_memory_cleanup();
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
				gc_collect_cycles();
				gc_collect_cycles();
			}

			// Disable Query Monitor to save memory during sync
			if ( class_exists( 'QueryMonitor' ) || class_exists( 'QM' ) ) {
				error_log( 'BRAG book Gallery Sync: Disabling Query Monitor to conserve memory' );
				// Remove all Query Monitor hooks
				remove_all_actions( 'qm/collect' );
				remove_all_filters( 'qm/collect' );
				remove_all_actions( 'qm/output' );
				remove_all_filters( 'qm/output' );
				remove_all_actions( 'shutdown' );
				// Disable Query Monitor collectors
				if ( function_exists( 'qm_dispatchers' ) ) {
					$dispatchers = qm_dispatchers();
					foreach ( $dispatchers as $dispatcher ) {
						remove_action( 'shutdown', [ $dispatcher, 'dispatch' ], 0 );
					}
				}
			}

			// Clear any existing stop flag
			delete_option( 'brag_book_gallery_sync_stop_flag' );

			// Memory optimization: Aggressively increase memory limit for sync operations
			$original_memory_limit = ini_get( 'memory_limit' );
			error_log( "BRAG book Gallery Sync: Current memory limit: {$original_memory_limit}" );

			// Don't try to increase memory - work within the configured limit
			// This respects the server's memory configuration
			error_log( "BRAG book Gallery Sync: Working within configured memory limit of {$original_memory_limit}" );

			// Convert memory limit to bytes for monitoring
			$memory_limit_bytes = $this->get_memory_limit_bytes();
			$memory_limit_mb    = round( $memory_limit_bytes / ( 1024 * 1024 ) );

			// Clear memory again after getting limit
			$this->aggressive_memory_cleanup();

			$initial_memory_usage   = memory_get_usage( true );
			$initial_memory_mb      = round( $initial_memory_usage / ( 1024 * 1024 ), 2 );
			$initial_memory_percent = round( ( $initial_memory_usage / $memory_limit_bytes ) * 100, 1 );

			error_log( "BRAG book Gallery Sync: Memory limit detected: {$memory_limit_mb} MB ({$memory_limit_bytes} bytes)" );
			error_log( "BRAG book Gallery Sync: Initial memory after cleanup: {$initial_memory_mb} MB ({$initial_memory_percent}% of limit)" );

			// Log memory status for monitoring
			error_log( "BRAG book Gallery Sync: Memory limit: {$memory_limit_mb} MB - Using optimized single-case processing" );

			$created_cases   = 0;
			$updated_cases   = 0;
			$errors          = [];
			$warnings        = []; // Track warnings that don't indicate failure
			$total_processed = 0;
			// Don't accumulate arrays - store counts in transients instead

			// Get all procedures from sidebar data (including their IDs)
			$procedures = $this->extract_procedures_from_sidebar( $sidebar_data );
			error_log( 'BRAG book Gallery Sync: Found ' . count( $procedures ) . ' procedures to process' );
			error_log( 'BRAG book Gallery Sync: Procedure details: ' . wp_json_encode( $procedures ) );

			// Calculate total expected cases for accurate progress tracking
			// Note: API may return more cases than sidebar indicates, so this is a baseline
			$baseline_expected_cases = 0;
			foreach ( $procedures as $procedure ) {
				$baseline_expected_cases += $procedure['caseCount'];
			}
			error_log( 'BRAG book Gallery Sync: Baseline expected cases from sidebar: ' . $baseline_expected_cases );

			// Use dynamic case counting for more accurate progress
			$total_expected_cases = $baseline_expected_cases; // Will be updated as we discover actual case counts

			// Memory-based safety only - no artificial case limits
			error_log( "BRAG book Gallery Sync: Processing all {$total_expected_cases} cases from API (no artificial limits)" );

			if ( empty( $procedures ) ) {
				error_log( 'BRAG book Gallery Sync: No procedures found in sidebar data' );

				return [
					'created'         => 0,
					'updated'         => 0,
					'errors'          => [ 'No procedures found in sidebar data' ],
					'total_processed' => 0,
				];
			}

			// Don't track recent cases in array - use transient instead
			set_transient( 'brag_book_gallery_sync_recent_cases', [], 3600 );

			// Calculate better progress tracking - start case sync from 0%
			$case_sync_start_percentage = 0; // Start case sync from 0%
			$case_sync_percentage_range = 100; // Cases take full progress (0% to 100%)

			// Process each procedure, then each individual procedure ID
			foreach ( $procedures as $procedure_index => $procedure ) {
				$procedure_name = $procedure['name'] ?? 'Unknown';
				$procedure_ids  = $procedure['ids'] ?? [];
				$case_count     = $procedure['caseCount'] ?? 0;

				error_log( "BRAG book Gallery Sync: Extracted data for '{$procedure_name}' - IDs: " . wp_json_encode( $procedure_ids ) . ", case_count: {$case_count}" );

				try {
					error_log( "BRAG book Gallery Sync: Entering try block for procedure '{$procedure_name}'" );
					// Monitor memory and time at procedure level
					$procedure_start_time = microtime( true );
					$current_memory       = memory_get_usage( true );
					$peak_memory          = memory_get_peak_usage( true );

					error_log( "BRAG book Gallery Sync: Processing procedure '{$procedure_name}' with " . count( $procedure_ids ) . " IDs [" . implode( ', ', $procedure_ids ) . "] (" . ( $procedure_index + 1 ) . "/" . count( $procedures ) . ")" );
					error_log( "BRAG book Gallery Sync: Procedure '{$procedure_name}' has {$case_count} total cases" );
					error_log( "BRAG book Gallery Sync: Current memory: " . wp_convert_bytes_to_hr( $current_memory ) . ", Peak: " . wp_convert_bytes_to_hr( $peak_memory ) );
					$case_processing_log[] = "Processing procedure: {$procedure_name} (" . count( $procedure_ids ) . " IDs)";

					// Calculate overall progress based on cases processed across all procedures
					$cases_processed_so_far = $total_processed;
					$overall_percentage     = $case_sync_start_percentage + ( ( $cases_processed_so_far / max( $total_expected_cases, 1 ) ) * $case_sync_percentage_range );
					$overall_percentage     = min( $overall_percentage, 99 ); // Cap at 99% until complete

					// Calculate procedure progress (0% at start)
					$procedure_percentage = ( $procedure_index / count( $procedures ) ) * 100;

					error_log( "BRAG book Gallery Sync: Updating detailed progress for procedure start: {$procedure_name}" );
					error_log( "BRAG book Gallery Sync: Overall: {$overall_percentage}%, Procedure: {$procedure_percentage}%, Cases processed: {$cases_processed_so_far}/{$total_expected_cases}" );

					$this->update_detailed_progress( [
						'stage'                => 'cases',
						'overall_percentage'   => round( $overall_percentage, 1 ),
						'current_procedure'    => $procedure_name,
						'procedure_current'    => $procedure_index + 1,
						'procedure_total'      => count( $procedures ),
						'procedure_percentage' => round( $procedure_percentage, 1 ),
						'case_current'         => 0,
						'case_total'           => $case_count,
						'case_percentage'      => 0,
						'current_step'         => "Starting procedure: {$procedure_name} (0 of {$case_count} cases)",
						'recent_cases'         => $recent_cases,
					] );
					error_log( "BRAG book Gallery Sync: ✓ Detailed progress updated" );

					if ( $case_count <= 0 || empty( $procedure_ids ) ) {
						error_log( "BRAG book Gallery Sync: Skipping procedure '{$procedure_name}' - no cases or IDs" );
						continue;
					}

					// Check memory usage before processing procedure
					if ( $current_memory > ( 400 * 1024 * 1024 ) ) { // 400MB threshold
						error_log( "BRAG book Gallery Sync: ⚠ High memory usage detected: " . wp_convert_bytes_to_hr( $current_memory ) );
					}

					// Track cases processed for this procedure
					$procedure_cases_processed = 0;

					// Debug: Check if we have procedure IDs to process
					error_log( "BRAG book Gallery Sync: About to process procedure IDs for '{$procedure_name}': " . wp_json_encode( $procedure_ids ) );
					error_log( "BRAG book Gallery Sync: Number of procedure IDs: " . count( $procedure_ids ) );

					if ( empty( $procedure_ids ) ) {
						error_log( "BRAG book Gallery Sync: ✗ No procedure IDs found for '{$procedure_name}' - skipping" );
						continue; // Skip to next procedure
					}

					// Process each individual procedure ID separately
					error_log( "BRAG book Gallery Sync: Starting foreach loop for procedure IDs..." );
					foreach ( $procedure_ids as $id_index => $procedure_id ) {
						try {
							$procedure_id_start = microtime( true );
							error_log( "BRAG book Gallery Sync: Processing individual procedure ID {$procedure_id} (" . ( $id_index + 1 ) . "/" . count( $procedure_ids ) . ") from '{$procedure_name}'" );
							$case_processing_log[] = "Processing procedure ID {$procedure_id} from {$procedure_name}";

							// Check if we should continue based on time and memory limits
							if ( ! $this->should_continue_processing() ) {
								error_log( 'BRAG book Gallery Sync: Stopping sync - saving state for resume' );

								// Save current state for resumption
								$this->save_sync_state( [
									'stage'              => 'cases',
									'include_cases'      => true,
									'procedure_index'    => $procedure_index,
									'procedure_id_index' => $id_index,
									'procedure_id'       => $procedure_id,
									'page'               => 1,
									'created'            => $created,
									'updated'            => $updated,
									'total_processed'    => $total_processed,
									'errors'             => $errors,
									'warnings'           => $warnings,
									'sidebar_data'       => $sidebar_data
								] );

								return [
									'success'         => false,
									'needs_resume'    => true,
									'message'         => 'Sync paused - will resume automatically',
									'created'         => $created,
									'updated'         => $updated,
									'total_processed' => $total_processed,
									'errors'          => $errors
								];
							}

							// Memory management: Force garbage collection
							if ( function_exists( 'gc_collect_cycles' ) ) {
								gc_collect_cycles();
							}

							// Check if database connection is still alive
							global $wpdb;
							if ( ! $wpdb->check_connection() ) {
								error_log( 'BRAG book Gallery Sync: ✗ Database connection lost, attempting to reconnect...' );
								if ( ! $wpdb->check_connection( false ) ) {
									throw new Exception( 'Database connection lost and could not reconnect' );
								}
								error_log( 'BRAG book Gallery Sync: ✓ Database connection restored' );
							}

							// Stream process cases to avoid loading all IDs into memory at once
							error_log( "BRAG book Gallery Sync: Starting streaming case processing for procedure ID {$procedure_id}..." );

							// No arrays to clean up anymore

							// Stream process cases one at a time for maximum memory efficiency
							// This ensures the sync works on ALL systems regardless of memory
							$batch_size = 1; // Always process one case at a time
							error_log( 'BRAG book Gallery Sync: Using optimized single-case processing for memory efficiency' );
							$page                      = 1;
							$procedure_cases_processed = 0;
							$processed_case_ids        = []; // Track processed cases to detect duplicates
							$empty_page_count          = 0; // Track consecutive empty pages
							$duplicate_page_count      = 0; // Track consecutive pages with all duplicates
							$max_pages_per_procedure   = 50; // Reduced safety limit (API rarely has more than 20-30 pages)
							$max_consecutive_issues    = 3; // Max consecutive problematic pages before stopping
							// Don't track logged cases in array

							error_log( "BRAG book Gallery Sync: Starting while loop for procedure ID {$procedure_id}, page {$page}, max pages: {$max_pages_per_procedure}" );
							while ( $page <= $max_pages_per_procedure ) {
								// Check if we should continue processing
								if ( ! $this->should_continue_processing() ) {
									error_log( 'BRAG book Gallery Sync: Stopping sync in batch loop - saving state' );

									// Save current state
									$this->save_sync_state( [
										'stage'              => 'cases',
										'include_cases'      => true,
										'procedure_index'    => $procedure_index,
										'procedure_id_index' => $id_index,
										'procedure_id'       => $procedure_id,
										'page'               => $page,
										'created'            => $created,
										'updated'            => $updated,
										'total_processed'    => $total_processed,
										'processed_case_ids' => array_slice( $processed_case_ids, - 100 ),
										// Keep last 100
										'errors'             => $errors,
										'warnings'           => $warnings,
										'sidebar_data'       => $sidebar_data
									] );

									if ( get_option( 'brag_book_gallery_sync_stop_flag', false ) ) {
										$errors[] = 'Sync stopped by user request';
									}

									break 3; // Break out of all loops
								}

								// Fetch a page of case IDs for this procedure
								try {
									$fetch_start_time = microtime( true );
									error_log( "BRAG book Gallery Sync: About to fetch case IDs for procedure ID {$procedure_id}, page {$page}, batch_size {$batch_size}" );
									$case_batch     = $this->fetch_case_ids_paginated( $procedure_id, $page, $batch_size );
									$fetch_end_time = microtime( true );
									$fetch_duration = round( ( $fetch_end_time - $fetch_start_time ), 2 );
									error_log( "BRAG book Gallery Sync: Fetch completed for procedure ID {$procedure_id}, page {$page} - got " . count( $case_batch ) . " case IDs in {$fetch_duration}s" );
									if ( empty( $case_batch ) ) {
										$empty_page_count ++;
										error_log( "BRAG book Gallery Sync: Empty page {$page} for procedure ID {$procedure_id} (empty count: {$empty_page_count})" );

										// Break after consecutive empty pages
										if ( $empty_page_count >= $max_consecutive_issues ) {
											error_log( "BRAG book Gallery Sync: Found {$empty_page_count} consecutive empty pages, ending procedure {$procedure_id}" );
											break;
										}

										$page ++;
										continue;
									}

									// Reset empty page counter when we get data
									$empty_page_count = 0;

									// Check for duplicate case IDs (indicates we're in a loop)
									$new_case_ids = array_diff( $case_batch, $processed_case_ids );
									if ( empty( $new_case_ids ) ) {
										$duplicate_page_count ++;
										error_log( "BRAG book Gallery Sync: All cases in page {$page} are duplicates (count: {$duplicate_page_count})" );

										// Break after too many consecutive duplicate pages
										if ( $duplicate_page_count >= $max_consecutive_issues ) {
											error_log( "BRAG book Gallery Sync: {$duplicate_page_count} consecutive duplicate pages, ending procedure {$procedure_id}" );
											break;
										}

										$page ++;
										continue;
									}

									// Reset duplicate counter when we get new cases
									$duplicate_page_count = 0;

									// Track processed case IDs
									$processed_case_ids = array_merge( $processed_case_ids, $case_batch );

									// Clean up array if it gets too large - increased threshold to avoid losing track of cases
									$this->cleanup_array( $processed_case_ids, 5000, 3000 );

									$batch_case_count = count( $case_batch );
									$batch_start_time = microtime( true );
									error_log( "BRAG book Gallery Sync: *** Processing BATCH {$page} for procedure '{$procedure_name}' ({$batch_case_count} cases) ***" );

									// Process this batch of cases
									$batch_results = $this->process_case_batch( $case_batch, $procedure_name, $procedure_id );

									$batch_end_time = microtime( true );
									$batch_duration = round( ( $batch_end_time - $batch_start_time ), 2 );
									error_log( "BRAG book Gallery Sync: *** COMPLETED BATCH {$page} for procedure '{$procedure_name}' in {$batch_duration}s ***" );

									// Process batch summary (no longer iterating over individual results)
									$batch_created   = $batch_results['created'] ?? 0;
									$batch_updated   = $batch_results['updated'] ?? 0;
									$batch_processed = $batch_results['processed'] ?? 0;

									// Update global counters
									$created_cases             += $batch_created;
									$updated_cases             += $batch_updated;
									$total_processed           += $batch_processed;
									$procedure_cases_processed += $batch_processed;

									// Clear batch results immediately
									unset( $batch_results );

									// Update progress
									$overall_percentage         = min( 100, ( $total_processed / max( $total_expected_cases, $total_processed ) ) * 100 );
									$actual_case_count          = max( $case_count, $procedure_cases_processed );
									$procedure_case_progress    = min( 100, ( $procedure_cases_processed / $actual_case_count ) * 100 );
									$procedure_overall_progress = ( $procedure_index / count( $procedures ) ) * 100;

									$this->update_detailed_progress( [
										'stage'                => 'cases',
										'overall_percentage'   => round( $overall_percentage, 1 ),
										'current_procedure'    => $procedure_name,
										'procedure_current'    => $procedure_index + 1,
										'procedure_total'      => count( $procedures ),
										'procedure_percentage' => round( $procedure_overall_progress, 1 ),
										'case_current'         => $procedure_cases_processed,
										'case_total'           => $actual_case_count,
										'case_percentage'      => round( $procedure_case_progress, 1 ),
										'current_step'         => "Processed batch {$page} for {$procedure_name}",
									] );

									// Log batch summary
									error_log( "BRAG book Gallery Sync: === BATCH {$page} SUMMARY: {$batch_case_count} cases processed ({$batch_created} created, {$batch_updated} updated) ===" );
									error_log( "BRAG book Gallery Sync: Procedure '{$procedure_name}' progress: {$procedure_cases_processed} cases total" );

									// Clean up memory after each batch to prevent memory buildup
									$current_memory    = memory_get_usage( true );
									$peak_memory       = memory_get_peak_usage( true );
									$memory_limit      = $this->get_memory_limit_bytes();
									$memory_percentage = ( $current_memory / $memory_limit ) * 100;
									$peak_percentage   = ( $peak_memory / $memory_limit ) * 100;
									$current_mb        = round( $current_memory / ( 1024 * 1024 ), 2 );
									$peak_mb           = round( $peak_memory / ( 1024 * 1024 ), 2 );

									// Log memory status after each batch
									error_log( sprintf(
										"BRAG book Gallery Sync: Memory after batch %d - Current: %.2f MB (%.1f%%), Peak: %.2f MB (%.1f%%)",
										$page,
										$current_mb,
										$memory_percentage,
										$peak_mb,
										$peak_percentage
									) );

									// Always cleanup at 20% to maintain maximum headroom on all systems
									// This ensures the sync works reliably even with minimal memory
									$cleanup_threshold = 20; // Universal aggressive cleanup threshold

									if ( $memory_percentage > $cleanup_threshold ) {
										error_log( sprintf( "BRAG book Gallery Sync: Memory at %.1f%% (%s / %s) - performing aggressive cleanup (threshold: %d%%)",
											$memory_percentage,
											wp_convert_bytes_to_hr( $current_memory ),
											wp_convert_bytes_to_hr( $memory_limit ),
											$cleanup_threshold
										) );

										// First aggressive cleanup
										$this->aggressive_memory_cleanup();
										$new_memory = memory_get_usage( true );
										$freed      = $current_memory - $new_memory;

										// If still above threshold, pause and retry
										if ( ( $new_memory / $memory_limit ) * 100 > $cleanup_threshold ) {
											sleep( 1 ); // Give system time to free memory
											$this->aggressive_memory_cleanup();
											$new_memory = memory_get_usage( true );
											$freed      = $current_memory - $new_memory;
										}

										error_log( sprintf( "BRAG book Gallery Sync: Memory freed: %s, now at %.1f%% (%s / %s)",
											wp_convert_bytes_to_hr( max( 0, $freed ) ),
											( $new_memory / $memory_limit ) * 100,
											wp_convert_bytes_to_hr( $new_memory ),
											wp_convert_bytes_to_hr( $memory_limit )
										) );

										// If memory still critically high, save state and pause
										$critical_threshold = $memory_limit_mb < 128 ? 40 : 45;
										if ( ( $new_memory / $memory_limit ) * 100 > $critical_threshold ) {
											error_log( sprintf( "BRAG book Gallery Sync: CRITICAL - Memory at %.1f%% after cleanup (critical threshold: %d%%). Considering pause.",
												( $new_memory / $memory_limit ) * 100,
												$critical_threshold
											) );

											// For very low memory systems, consider saving state
											if ( $memory_limit_mb < 128 ) {
												error_log( "BRAG book Gallery Sync: Low memory system - may need to save state and resume" );
											}
										}
									}

									// Force progress update after each batch (ensure frontend sees progress)
									$cases_processed_so_far     = $total_processed;
									$overall_percentage         = min( 100, ( $cases_processed_so_far / max( $total_expected_cases, 1 ) ) * 100 );
									$actual_case_count          = max( $case_count, $procedure_cases_processed );
									$procedure_case_progress    = min( 100, ( $procedure_cases_processed / $actual_case_count ) * 100 );
									$procedures_completed       = $procedure_index;
									$procedure_overall_progress = ( $procedures_completed / count( $procedures ) ) * 100;

									// Ensure recent_cases has current information for Recent Activity display
									if ( empty( $recent_cases ) ) {
										$recent_cases[] = "Processing {$procedure_name} - {$procedure_cases_processed} of {$actual_case_count} cases completed";
									}

									error_log( "BRAG book Gallery Sync: Forcing progress update - Overall: {$overall_percentage}%, Procedure: {$procedure_cases_processed}/{$actual_case_count}" );
									error_log( "BRAG book Gallery Sync: Recent cases for display: " . wp_json_encode( $recent_cases ) );
									$this->update_detailed_progress( [
										'stage'                => 'cases',
										'overall_percentage'   => round( $overall_percentage, 1 ),
										'current_procedure'    => $procedure_name,
										'procedure_current'    => $procedure_index + 1,
										'procedure_total'      => count( $procedures ),
										'procedure_percentage' => round( $procedure_overall_progress, 1 ),
										'case_current'         => $procedure_cases_processed,
										'case_total'           => $actual_case_count,
										'case_percentage'      => round( $procedure_case_progress, 1 ),
										'current_step'         => "Batch completed: {$procedure_cases_processed} of {$actual_case_count} cases in {$procedure_name} (procedure ID: {$procedure_id})",
										'recent_cases'         => $recent_cases,
										// Use actual recent cases, not just batch info
									] );

								} catch ( Exception $e ) {
									$error_msg = "Failed to process batch for procedure ID {$procedure_id}: " . $e->getMessage();
									$errors[]  = $error_msg;
									error_log( "BRAG book Gallery Sync: ✗ {$error_msg}" );
									error_log( "BRAG book Gallery Sync: Exception trace: " . $e->getTraceAsString() );

									// Still count failed cases for progress
									$batch_size                = count( $case_batch );
									$total_processed           += $batch_size;
									$procedure_cases_processed += $batch_size;
								}

								// Memory cleanup after each batch
								if ( function_exists( 'gc_collect_cycles' ) ) {
									gc_collect_cycles();
								}

								// Clear WP object cache periodically
								if ( $page % 2 === 0 ) {
									wp_cache_flush();
								}

								// Memory cleanup after batch processing
								unset( $batch_results );
								if ( function_exists( 'gc_collect_cycles' ) ) {
									gc_collect_cycles();
								}

								// Minimal delay for better performance
								usleep( 10000 ); // 0.01 second (much faster)

								// Move to next page
								$page ++;
							}

							// Store the case order list for this procedure
							try {
								$this->store_procedure_case_order_list( $procedure_id, $processed_case_ids );
							} catch ( \Exception $e ) {
								error_log( "BRAG book Gallery Sync: Failed to store case order for procedure {$procedure_id}: " . $e->getMessage() );
								// Continue with sync even if case order storage fails
							}

							// Clean up memory immediately after updating procedure with case order
							$memory_before_cleanup = memory_get_usage( true );
							$this->aggressive_memory_cleanup();
							$memory_after_cleanup = memory_get_usage( true );
							$memory_freed         = $memory_before_cleanup - $memory_after_cleanup;
							error_log( "BRAG book Gallery Sync: Memory cleanup after case order update - Freed: " . wp_convert_bytes_to_hr( max( 0, $memory_freed ) ) );

							// Log procedure completion
							error_log( "BRAG book Gallery Sync: Completed procedure '{$procedure_name}' with {$procedure_cases_processed} cases processed" );

							error_log( "BRAG book Gallery Sync: Completed procedure ID {$procedure_id} - processed {$procedure_cases_processed} cases" );
							$case_processing_log[] = "Completed procedure ID {$procedure_id} - processed {$procedure_cases_processed} cases";

						} catch ( Exception $e ) {
							$errors[] = "Failed to process procedure ID {$procedure_id}: " . $e->getMessage();
							error_log( "BRAG book Gallery Sync: ✗ Failed to process procedure ID {$procedure_id}: " . $e->getMessage() );
						}
					}

					error_log( "BRAG book Gallery Sync: Completed all IDs for procedure '{$procedure_name}'" );

					// Clean up memory after each procedure's cases are completed
					$memory_before = memory_get_usage( true );
					$this->cleanup_memory_after_procedure();
					$memory_after = memory_get_usage( true );
					$memory_freed = $memory_before - $memory_after;
					error_log( "BRAG book Gallery Sync: Memory cleanup after '{$procedure_name}' - Freed: " . wp_convert_bytes_to_hr( max( 0, $memory_freed ) ) );
					error_log( "BRAG book Gallery Sync: Current memory usage: " . wp_convert_bytes_to_hr( $memory_after ) . ", Peak: " . wp_convert_bytes_to_hr( memory_get_peak_usage( true ) ) );

				} catch ( Exception $e ) {
					$errors[] = "Failed to process procedure '{$procedure_name}': " . $e->getMessage();
					error_log( "BRAG book Gallery Sync: ✗ Failed to process procedure '{$procedure_name}': " . $e->getMessage() );
				}
			}

			error_log( 'BRAG book Gallery Sync: Stage 2 completed' );
			error_log( "BRAG book Gallery Sync: Cases created: {$created_cases}" );
			error_log( "BRAG book Gallery Sync: Cases updated: {$updated_cases}" );
			error_log( "BRAG book Gallery Sync: Total processed: {$total_processed}" );
			// No longer tracking unique cases in array

			// No longer tracking duplicates in arrays

			// Verify math using total processed counter
			$expected_total = $created_cases + $updated_cases;
			$actual_total   = $total_processed;
			if ( $expected_total > $actual_total ) {
				error_log( "BRAG book Gallery Sync: ⚠ COUNT MISMATCH! Created ({$created_cases}) + Updated ({$updated_cases}) = {$expected_total}, but unique cases processed = {$actual_total}" );
				$warnings[] = "Case count mismatch: Created ({$created_cases}) + Updated ({$updated_cases}) = {$expected_total}, but unique cases processed = {$actual_total}";
			} else {
				error_log( "BRAG book Gallery Sync: ✓ Case count verified: Created ({$created_cases}) + Updated ({$updated_cases}) = {$actual_total} unique cases" );
			}

			error_log( "BRAG book Gallery Sync: Errors: " . count( $errors ) );
			if ( ! empty( $warnings ) ) {
				error_log( "BRAG book Gallery Sync: Warnings: " . count( $warnings ) );
				foreach ( $warnings as $warning ) {
					error_log( "BRAG book Gallery Sync: - {$warning}" );
				}
			}

			// No need to restore memory limit since we didn't change it

			// Set final completion progress before cleanup
			$this->update_detailed_progress( [
				'stage'                => 'completed',
				'overall_percentage'   => 100,
				'current_procedure'    => '',
				'procedure_current'    => 0,
				'procedure_total'      => 0,
				'procedure_percentage' => 100,
				'case_current'         => $total_processed,
				'case_total'           => $total_processed,
				'case_percentage'      => 100,
				'current_step'         => 'Sync Completed',
				'recent_cases'         => [],
			] );

			// Store activity log for reference before cleanup
			$detailed_progress = get_option( 'brag_book_gallery_detailed_progress', [] );
			if ( ! empty( $detailed_progress ) ) {
				update_option( 'brag_book_gallery_completed_sync_log', $detailed_progress, false );
			}

			// Clean up progress tracking
			delete_option( 'brag_book_gallery_case_progress' );
			delete_option( 'brag_book_gallery_detailed_progress' );

			// Also clear the sync status file
			$this->clear_sync_status_file();

			return [
				'created'         => $created_cases,
				'updated'         => $updated_cases,
				'errors'          => $errors,
				'warnings'        => $warnings,
				'total_processed' => $total_processed,
				'details'         => [],
			];

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Stage 2 failed with exception: ' . $e->getMessage() );

			// No need to restore memory limit since we didn't change it

			// Clean up progress tracking on error
			delete_option( 'brag_book_gallery_case_progress' );
			delete_option( 'brag_book_gallery_detailed_progress' );

			// Also clear the sync status file on error
			$this->clear_sync_status_file();

			throw $e;
		}
	}

	/**
	 * Extract procedures from sidebar data with their full structure
	 *
	 * @param array $sidebar_data Sidebar API response
	 *
	 * @return array Array of procedures with name, ids, caseCount, etc.
	 * @since 3.0.0
	 */
	private function extract_procedures_from_sidebar( array $sidebar_data ): array {
		$procedures = [];

		error_log( 'BRAG book Gallery Sync: Sidebar data keys: ' . wp_json_encode( array_keys( $sidebar_data ) ) );

		if ( ! isset( $sidebar_data['data'] ) || ! is_array( $sidebar_data['data'] ) ) {
			error_log( 'BRAG book Gallery Sync: No data array found in sidebar data' );

			return $procedures;
		}

		error_log( 'BRAG book Gallery Sync: Found ' . count( $sidebar_data['data'] ) . ' categories in sidebar data' );

		foreach ( $sidebar_data['data'] as $category_index => $category ) {
			error_log( "BRAG book Gallery Sync: Processing category {$category_index}: " . wp_json_encode( array_keys( $category ) ) );

			if ( isset( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
				error_log( "BRAG book Gallery Sync: Category {$category_index} has " . count( $category['procedures'] ) . ' procedures' );

				foreach ( $category['procedures'] as $procedure_index => $procedure ) {
					error_log( "BRAG book Gallery Sync: Processing procedure {$procedure_index}: " . wp_json_encode( array_keys( $procedure ) ) );
					error_log( "BRAG book Gallery Sync: Procedure data: " . wp_json_encode( $procedure ) );

					// Only include procedures that have IDs and case count
					if ( isset( $procedure['ids'] ) && is_array( $procedure['ids'] ) && ! empty( $procedure['ids'] ) ) {
						$procedure_data = [
							'name'        => $procedure['name'] ?? 'Unknown',
							'ids'         => array_map( 'intval', $procedure['ids'] ),
							'caseCount'   => (int) ( $procedure['totalCase'] ?? 0 ),
							// Fix: Use 'totalCase' not 'caseCount'
							'slug'        => $procedure['slug'] ?? '',
							'nudity'      => $procedure['nudity'] ?? false,
							'description' => $procedure['description'] ?? '',
							// Add description from API
						];

						error_log( "BRAG book Gallery Sync: Added procedure: " . wp_json_encode( $procedure_data ) );
						$procedures[] = $procedure_data;
					} else {
						error_log( "BRAG book Gallery Sync: Skipped procedure - missing IDs or case count: " . wp_json_encode( $procedure ) );
					}
				}
			} else {
				error_log( "BRAG book Gallery Sync: Category {$category_index} has no procedures array" );
			}
		}

		return $procedures;
	}

	/**
	 * Fetch all case IDs for a single procedure ID with pagination
	 *
	 * @param int $procedure_id Single procedure ID
	 *
	 * @return array Array of all case IDs for this procedure
	 * @throws Exception If API request fails
	 * @since 3.0.0
	 */
	/**
	 * Fetch case IDs for a procedure with pagination (memory efficient)
	 *
	 * @param int $procedure_id Procedure ID
	 * @param int $page Page number (1-based)
	 * @param int $per_page Cases per page (unused, API uses count-based pagination)
	 *
	 * @return array Case IDs for this page
	 * @throws Exception If fetching fails
	 * @since 3.0.0
	 */
	private function fetch_case_ids_paginated( int $procedure_id, int $page, int $per_page = 10 ): array {
		error_log( "BRAG book Gallery Sync: fetch_case_ids_paginated() ENTRY - procedure_id: {$procedure_id}, page: {$page}" );
		try {
			// Use the existing count-based API but calculate the count number from page
			$count = $page; // The API uses 'count' as page number
			error_log( "BRAG book Gallery Sync: About to call fetch_case_ids_for_single_procedure_count() with procedure_id: {$procedure_id}, count: {$count}" );
			$case_ids = $this->fetch_case_ids_for_single_procedure_count( $procedure_id, $count );
			error_log( "BRAG book Gallery Sync: fetch_case_ids_for_single_procedure_count() returned " . count( $case_ids ) . " case IDs" );

			error_log( "BRAG book Gallery Sync: Procedure ID {$procedure_id} page {$page} - fetched " . count( $case_ids ) . " case IDs" );

			return $case_ids ?: [];
		} catch ( Exception $e ) {
			error_log( "BRAG book Gallery Sync: ✗ EXCEPTION in fetch_case_ids_paginated() - procedure {$procedure_id} page {$page}: " . $e->getMessage() );
			error_log( "BRAG book Gallery Sync: ✗ Exception trace: " . $e->getTraceAsString() );

			return []; // Return empty array instead of throwing, let caller handle empty results
		}
		error_log( "BRAG book Gallery Sync: fetch_case_ids_paginated() EXIT - procedure_id: {$procedure_id}, page: {$page}" );
	}

	private function fetch_all_case_ids_for_single_procedure( int $procedure_id ): array {
		$all_case_ids   = [];
		$count          = 1;
		$has_more_cases = true;

		error_log( "BRAG book Gallery Sync: Starting to fetch all cases for procedure ID {$procedure_id}" );

		while ( $has_more_cases ) {
			try {
				error_log( "BRAG book Gallery Sync: Fetching with count {$count} for procedure ID {$procedure_id}" );
				$case_ids = $this->fetch_case_ids_for_single_procedure_count( $procedure_id, $count );

				if ( empty( $case_ids ) ) {
					// No more cases, stop fetching
					$has_more_cases = false;
					error_log( "BRAG book Gallery Sync: No more cases found at count {$count} for procedure ID {$procedure_id}" );
				} else {
					$all_case_ids = array_merge( $all_case_ids, $case_ids );
					error_log( "BRAG book Gallery Sync: Count {$count} returned " . count( $case_ids ) . " case IDs for procedure ID {$procedure_id}" );
					$count ++;

					// Small delay between requests
					usleep( 200000 ); // 0.2 second
				}

			} catch ( Exception $e ) {
				error_log( "BRAG book Gallery Sync: ✗ Failed to fetch count {$count} for procedure ID {$procedure_id}: " . $e->getMessage() );
				// Stop on error to prevent infinite loop
				$has_more_cases = false;
				throw $e;
			}
		}

		// Remove duplicates
		$unique_case_ids = array_unique( $all_case_ids );
		error_log( "BRAG book Gallery Sync: Procedure ID {$procedure_id} - total unique case IDs: " . count( $unique_case_ids ) );

		return $unique_case_ids;
	}

	/**
	 * Fetch case IDs for a single procedure ID with page-based pagination (v2 API)
	 *
	 * @param int $procedure_id Single procedure ID to filter by
	 * @param int $page Page number (1-indexed)
	 * @param int $limit Number of items per page (default 50)
	 *
	 * @return array Array of case IDs
	 * @throws Exception If API request fails
	 * @since 3.3.0
	 */
	private function fetch_case_ids_for_single_procedure_count( int $procedure_id, int $page, int $limit = 50 ): array {
		$api_token           = get_option( 'brag_book_gallery_api_token', [] )[0] ?? '';
		$website_property_id = get_option( 'brag_book_gallery_website_property_id', [] )[0] ?? 0;

		if ( empty( $api_token ) || $website_property_id <= 0 ) {
			throw new Exception( __( 'Invalid API configuration', 'brag-book-gallery' ) );
		}

		error_log( 'BRAG book Gallery Sync: Fetching procedure ' . $procedure_id . ' page ' . $page . ' (v2 API)' );

		// Use Endpoints class for v2 API call
		$endpoints = new \BRAGBookGallery\Includes\REST\Endpoints();
		$response  = $endpoints->get_cases_v2(
			$api_token,
			intval( $website_property_id ),
			$procedure_id,
			$page,
			$limit
		);

		if ( ! $response ) {
			throw new Exception( sprintf(
				__( 'API request failed for procedure %d page %d', 'brag-book-gallery' ),
				$procedure_id,
				$page
			) );
		}

		// v2 API: Extract case IDs from response
		$case_ids = [];
		if ( isset( $response['data']['cases'] ) && is_array( $response['data']['cases'] ) ) {
			error_log( 'BRAG book Gallery Sync: Found ' . count( $response['data']['cases'] ) . ' cases for procedure ' . $procedure_id . ' page ' . $page );
			foreach ( $response['data']['cases'] as $case ) {
				if ( isset( $case['id'] ) ) {
					$case_ids[] = (int) $case['id'];
				}
			}
		} else {
			error_log( 'BRAG book Gallery Sync: No cases found in v2 response for procedure ' . $procedure_id . ' page ' . $page );
		}

		error_log( 'BRAG book Gallery Sync: Procedure ' . $procedure_id . ' page ' . $page . ' returned ' . count( $case_ids ) . ' case IDs' );

		return $case_ids;
	}

	/**
	 * Fetch case IDs for specific procedure IDs and page
	 *
	 * @param array $procedure_ids Array of procedure IDs to filter by
	 * @param int $page Page number (1-based)
	 * @param int $count Number of cases per page
	 *
	 * @return array Array of case IDs
	 * @throws Exception If API request fails
	 * @since 3.0.0
	 */
	private function fetch_case_ids_for_procedure_page( array $procedure_ids, int $page, int $count = 10 ): array {
		$endpoint = '/api/plugin/combine/cases';

		// Get API tokens and website property IDs
		$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			throw new Exception( __( 'No API tokens configured', 'brag-book-gallery' ) );
		}

		$valid_tokens = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build full URL
		$api_base_url = $this->get_api_base_url();
		$full_url     = $api_base_url . $endpoint;

		// Prepare request body with procedure IDs filter and pagination
		$request_body = [
			'apiTokens'          => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'count'              => $count,
			'start'              => ( ( $page - 1 ) * $count ) + 1, // Convert to 1-based start index
			'procedureIds'       => $procedure_ids, // Filter by specific procedure IDs
		];

		error_log( 'BRAG book Gallery Sync: Fetching procedures [' . implode( ', ', $procedure_ids ) . '] page ' . $page . ' (start: ' . $request_body['start'] . ', count: ' . $count . ')' );
		error_log( 'BRAG book Gallery Sync: Request body: ' . wp_json_encode( $request_body ) );

		// Make POST request
		$response = wp_remote_post( $full_url, [
			'timeout'   => 30,
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body'      => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			throw new Exception( sprintf(
				__( 'API request failed: %s', 'brag-book-gallery' ),
				$response->get_error_message()
			) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			error_log( 'BRAG book Gallery Sync: API error response for procedures [' . implode( ', ', $procedure_ids ) . '] page ' . $page . ': ' . $response_body );
			throw new Exception( sprintf(
				__( 'API returned error status %d for procedures [%s] page %d', 'brag-book-gallery' ),
				$response_code,
				implode( ', ', $procedure_ids ),
				$page
			) );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( __( 'Invalid JSON response from API', 'brag-book-gallery' ) );
		}

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			throw new Exception( __( 'API returned unsuccessful response', 'brag-book-gallery' ) );
		}

		// Extract case IDs from response
		$case_ids = [];
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $case ) {
				if ( isset( $case['id'] ) ) {
					$case_ids[] = (int) $case['id'];
				}
			}
		}

		error_log( 'BRAG book Gallery Sync: Procedures [' . implode( ', ', $procedure_ids ) . '] page ' . $page . ' returned ' . count( $case_ids ) . ' case IDs' );

		return $case_ids;
	}

	/**
	 * Extract procedure IDs from sidebar data
	 *
	 * @param array $sidebar_data Sidebar API response
	 *
	 * @return array Array of procedure IDs
	 * @since 3.0.0
	 */
	private function extract_procedure_ids_from_sidebar( array $sidebar_data ): array {
		$procedure_ids = [];

		if ( ! isset( $sidebar_data['data'] ) || ! is_array( $sidebar_data['data'] ) ) {
			return $procedure_ids;
		}

		foreach ( $sidebar_data['data'] as $category ) {
			if ( isset( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( isset( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
						foreach ( $procedure['ids'] as $id ) {
							$procedure_ids[] = (int) $id;
						}
					}
				}
			}
		}

		return array_unique( $procedure_ids );
	}

	/**
	 * Process a batch of cases concurrently for better performance
	 *
	 * @param array $case_batch Array of case IDs to process (up to 5)
	 * @param string $procedure_name Name of the procedure for logging
	 * @param int $procedure_id Procedure ID for logging
	 *
	 * @return array Array of case processing results
	 * @throws Exception If batch processing fails critically
	 * @since 3.0.0
	 */
	private function process_case_batch( array $case_batch, string $procedure_name, int $procedure_id ): array {
		try {
			$batch_size = count( $case_batch );
			error_log( "BRAG book Gallery Sync: process_case_batch() starting with {$batch_size} cases from procedure '{$procedure_name}'" );

			// Update progress: Starting API fetch
			$this->update_step_progress( "[FETCH] Fetching case details for {$batch_size} cases from {$procedure_name}" );

			// Fetch case details using v2 API endpoint with normalization
			$fetch_start_time = microtime( true );
			$case_details_results = [];
			foreach ( $case_batch as $case_id ) {
				try {
					$case_details_results[ $case_id ] = $this->fetch_case_details( $case_id );
				} catch ( Exception $e ) {
					error_log( "BRAG book Gallery Sync: Failed to fetch case {$case_id}: " . $e->getMessage() );
					$case_details_results[ $case_id ] = null;
				}
			}
			$fetch_duration = round( microtime( true ) - $fetch_start_time, 2 );

			error_log( "BRAG book Gallery Sync: API fetch completed in {$fetch_duration}s for {$batch_size} cases" );
			$this->update_step_progress( "Case details fetched in {$fetch_duration}s, processing {$batch_size} cases..." );

			// Process each case immediately without storing results
			$case_count    = 0;
			$total_cases   = count( $case_batch );
			$created_count = 0;
			$updated_count = 0;

			foreach ( $case_batch as $case_id ) {
				try {
					$case_count ++;
					$this->update_step_progress( "Processing case {$case_id} ({$case_count}/{$total_cases}) from {$procedure_name}..." );

					// Get the case data from batch results
					$case_data = $case_details_results[ $case_id ] ?? null;

					if ( ! $case_data ) {
						error_log( "BRAG book Gallery Sync: No data found for case {$case_id} in batch results" );
						// Just log the skip, don't store in array
						continue;
					}

					// Check if this case should be skipped due to isForWebsite
					if ( ! isset( $case_data['isForWebsite'] ) || ! $case_data['isForWebsite'] ) {
						error_log( sprintf(
							'BRAG book Gallery Sync: Skipping case %s - not approved for website (isForWebsite: %s)',
							$case_id,
							isset( $case_data['isForWebsite'] ) ? ( $case_data['isForWebsite'] ? 'true' : 'false' ) : 'not set'
						) );

						// If this case exists in WordPress but is no longer approved for website, delete it
						$existing_post = $this->find_existing_case_post( $case_id );
						if ( $existing_post ) {
							error_log( "BRAG book Gallery Sync: Deleting existing case {$case_id} (post ID: {$existing_post->ID}) - no longer approved for website" );
							wp_delete_post( $existing_post->ID, true ); // true = force delete, skip trash
						}

						// Just log the skip, don't store in array
						continue;
					}

					// Memory optimization: Clear any unnecessary variables from API response
					if ( isset( $case_data['debug'] ) ) {
						unset( $case_data['debug'] );
					}
					if ( isset( $case_data['raw_response'] ) ) {
						unset( $case_data['raw_response'] );
					}

					// Check if case already exists
					$existing_post = $this->find_existing_case_post( $case_id );
					$is_update     = ! empty( $existing_post );

					$this->update_step_progress( "Creating/updating WordPress post for case {$case_id}..." );

					// Handle multi-procedure cases by creating separate posts for each procedure
					$procedure_results = $this->process_multi_procedure_case( $case_id, $case_data, $existing_post );

					$this->update_step_progress( "Saving metadata and taxonomy for case {$case_id}..." );

					// Count results and clean cache immediately
					foreach ( $procedure_results as $procedure_result ) {
						if ( $procedure_result['created'] ) {
							$created_count ++;
						} elseif ( $procedure_result['updated'] ) {
							$updated_count ++;
						}
						// Clean post cache if we have a post_id
						if ( ! empty( $procedure_result['post_id'] ) ) {
							clean_post_cache( $procedure_result['post_id'] );
						}
					}
					// Clear the entire results array immediately
					$procedure_results = null;
					unset( $procedure_results );

					// Memory cleanup: Clear case data to free memory
					$case_data = null;
					unset( $case_data );
					// Clear object cache groups
					wp_cache_flush_group( 'posts' );
					wp_cache_flush_group( 'post_meta' );
					wp_cache_flush_group( 'terms' );
					wp_cache_flush_group( 'post_tag' );

					// Clear database query cache
					global $wpdb;
					$wpdb->flush();

					// Force garbage collection after each case
					if ( function_exists( 'gc_collect_cycles' ) ) {
						gc_collect_cycles();
						gc_collect_cycles();
					}

					$this->update_step_progress( "✓ Completed case {$case_id} ({$case_count}/{$total_cases}) from {$procedure_name}" );

				} catch ( Exception $e ) {
					error_log( "BRAG book Gallery Sync: ✗ Failed to process case {$case_id} in batch: " . $e->getMessage() );
					$batch_results[] = [
						'case_id' => $case_id,
						'post_id' => null,
						'created' => false,
						'updated' => false,
						'error'   => $e->getMessage(),
					];
				}
			}

			error_log( "BRAG book Gallery Sync: process_case_batch() completed {$batch_size} cases from procedure '{$procedure_name}'" );
			$this->update_step_progress( "✓ Batch completed: {$batch_size} cases processed from {$procedure_name}" );

			// Return minimal summary instead of accumulated results
			return [
				'created'   => $created_count,
				'updated'   => $updated_count,
				'processed' => $batch_size,
			];

		} catch ( Exception $e ) {
			error_log( "BRAG book Gallery Sync: ✗ process_case_batch() failed critically: " . $e->getMessage() );
			error_log( "BRAG book Gallery Sync: Exception stack trace: " . $e->getTraceAsString() );
			throw $e;
		}
	}

	/**
	 * Fetch details for a single case - optimized for low memory
	 *
	 * @param int $case_id Case ID to fetch
	 *
	 * @return array|null Case data or null on failure
	 * @since 3.3.0
	 */
	private function fetch_single_case_details( int $case_id ): ?array {
		error_log( "BRAG book Gallery Sync: Fetching single case {$case_id}" );

		// Get API configuration
		$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( "BRAG book Gallery Sync: No API tokens configured" );

			return null;
		}

		$valid_tokens       = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );
		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build request
		$api_base_url = $this->get_api_base_url();
		$endpoint     = '/api/plugin/case/' . $case_id;
		$full_url     = $api_base_url . $endpoint;

		$request_body = wp_json_encode( [
			'apiTokens'          => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
		] );

		// Make simple POST request
		$response = wp_remote_post( $full_url, [
			'timeout' => 10,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'    => $request_body,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( "BRAG book Gallery Sync: Failed to fetch case {$case_id}: " . $response->get_error_message() );

			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			error_log( "BRAG book Gallery Sync: HTTP {$response_code} for case {$case_id}" );

			return null;
		}

		$data = json_decode( $response_body, true );

		// Clear response body immediately
		unset( $response_body );
		unset( $response );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( "BRAG book Gallery Sync: JSON error for case {$case_id}: " . json_last_error_msg() );

			return null;
		}

		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['data'][0] ) ) {
			error_log( "BRAG book Gallery Sync: Invalid API response for case {$case_id}" );

			return null;
		}

		$case_data = $data['data'][0];

		// Clear the full response
		unset( $data );

		error_log( "BRAG book Gallery Sync: Successfully fetched case {$case_id}" );

		return $case_data;
	}

	/**
	 * Fetch case details for multiple cases in parallel (batch operation)
	 *
	 * @param array $case_ids Array of case IDs to fetch
	 *
	 * @return array Associative array with case_id => case_data
	 * @throws Exception If batch fetch fails
	 * @since 3.0.0
	 */
	private function fetch_case_details_batch( array $case_ids ): array {
		$batch_size = count( $case_ids );
		error_log( "BRAG book Gallery Sync: fetch_case_details_batch() starting for {$batch_size} cases: " . implode( ', ', $case_ids ) );

		// Get API tokens and website property IDs
		$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( "BRAG book Gallery Sync: ✗ No API tokens configured for batch fetch" );
			throw new Exception( __( 'No API tokens configured', 'brag-book-gallery' ) );
		}

		$valid_tokens = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Get API base URL
		$api_base_url = $this->get_api_base_url();

		// Prepare common request data
		$base_request_body = [
			'apiTokens'          => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'procedureIds'       => [ 6851 ], // Default fallback, will be updated per case if needed
		];

		// Prepare cURL multi handle for parallel requests
		$multi_handle = curl_multi_init();
		$curl_handles = [];
		$case_results = [];

		// Set up individual cURL handles for each case
		foreach ( $case_ids as $case_id ) {
			$endpoint = '/api/plugin/combine/cases/' . $case_id;
			$full_url = $api_base_url . $endpoint;

			// Try to get procedure IDs from existing case post if it exists
			$request_body  = $base_request_body;
			$existing_post = $this->find_existing_case_post( $case_id );
			if ( $existing_post ) {
				$stored_procedure_ids = get_post_meta( $existing_post->ID, 'brag_book_gallery_procedure_ids', true );
				if ( ! empty( $stored_procedure_ids ) ) {
					$request_body['procedureIds'] = array_map( 'intval', explode( ',', $stored_procedure_ids ) );
				}
			}

			$ch = curl_init();
			curl_setopt_array( $ch, [
				CURLOPT_URL            => $full_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => wp_json_encode( $request_body ),
				CURLOPT_HTTPHEADER     => [
					'Content-Type: application/json',
					'Accept: application/json',
					'User-Agent: BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
				],
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_FOLLOWLOCATION => true,
			] );

			curl_multi_add_handle( $multi_handle, $ch );
			$curl_handles[ $case_id ] = $ch;
		}

		// Execute all requests in parallel
		$request_start = microtime( true );
		$running       = null;
		do {
			curl_multi_exec( $multi_handle, $running );
			curl_multi_select( $multi_handle );
		} while ( $running > 0 );
		$request_duration = round( ( microtime( true ) - $request_start ) * 1000, 2 );

		error_log( "BRAG book Gallery Sync: Parallel API requests for {$batch_size} cases completed in {$request_duration}ms" );

		// Collect results from each handle
		foreach ( $case_ids as $case_id ) {
			$ch            = $curl_handles[ $case_id ];
			$response_body = curl_multi_getcontent( $ch );
			$http_code     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$curl_error    = curl_error( $ch );

			curl_multi_remove_handle( $multi_handle, $ch );
			curl_close( $ch );

			// Handle cURL errors
			if ( ! empty( $curl_error ) ) {
				error_log( "BRAG book Gallery Sync: cURL error for case {$case_id}: {$curl_error}" );
				$case_results[ $case_id ] = null;
				continue;
			}

			// Handle HTTP errors
			if ( $http_code !== 200 ) {
				error_log( "BRAG book Gallery Sync: HTTP error {$http_code} for case {$case_id}: " . substr( $response_body, 0, 200 ) );
				$case_results[ $case_id ] = null;
				continue;
			}

			// Parse JSON response
			$data = json_decode( $response_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( "BRAG book Gallery Sync: JSON decode error for case {$case_id}: " . json_last_error_msg() );
				error_log( "BRAG book Gallery Sync: Case {$case_id} response body (first 500 chars): " . substr( $response_body, 0, 500 ) );
				error_log( "BRAG book Gallery Sync: Case {$case_id} response body length: " . strlen( $response_body ) );
				$case_results[ $case_id ] = null;
				continue;
			}

			// Validate API response structure
			if ( ! isset( $data['success'] ) || ! $data['success'] ) {
				error_log( "BRAG book Gallery Sync: API returned unsuccessful response for case {$case_id}" );
				$case_results[ $case_id ] = null;
				continue;
			}

			if ( ! isset( $data['data'][0] ) ) {
				error_log( "BRAG book Gallery Sync: No case data found in API response for case {$case_id}" );
				$case_results[ $case_id ] = null;
				continue;
			}

			$case_results[ $case_id ] = $data['data'][0];
			error_log( "BRAG book Gallery Sync: ✓ Successfully fetched case details for case {$case_id}" );
		}

		curl_multi_close( $multi_handle );

		$successful_fetches = count( array_filter( $case_results ) );
		error_log( "BRAG book Gallery Sync: fetch_case_details_batch() completed - {$successful_fetches}/{$batch_size} successful fetches" );

		return $case_results;
	}

	/**
	 * Process multi-procedure case by creating separate posts for each procedure or single post with multiple taxonomies
	 *
	 * When a case has multiple procedures, this method can either:
	 * 1. Create separate case posts for each procedure (default - better organization and filtering)
	 * 2. Create a single post with all procedures as taxonomies (legacy approach)
	 *
	 * The approach is configurable via the 'brag_book_gallery_multi_procedure_strategy' option:
	 * - 'separate_posts' (default): Create separate posts for each procedure
	 * - 'single_post': Create one post with multiple procedure taxonomies
	 *
	 * @param int $case_id Original case ID from API
	 * @param array $case_data Case data from API
	 * @param \WP_Post|null $existing_post Existing case post if found
	 *
	 * @return array Array of procedure processing results
	 * @throws Exception If processing fails
	 * @since 3.0.0
	 */
	private function process_multi_procedure_case( int $case_id, array $case_data, ?\WP_Post $existing_post = null ): array {
		$procedure_results = [];

		// Get procedure IDs from the case data
		$procedure_ids = $case_data['procedureIds'] ?? [];

		if ( empty( $procedure_ids ) || ! is_array( $procedure_ids ) ) {
			// Fallback to single case creation if no procedures found
			error_log( "BRAG book Gallery Sync: No procedures found for case {$case_id}, creating single case" );
			$single_result = $this->create_single_case_post( $case_id, $case_data, $existing_post );

			return [ $single_result ];
		}

		// Get multi-procedure strategy from settings
		$strategy        = get_option( 'brag_book_gallery_multi_procedure_strategy', 'single_post' );
		$procedure_count = count( $procedure_ids );

		error_log( "BRAG book Gallery Sync: Case {$case_id} has {$procedure_count} procedures (IDs: " . implode( ', ', $procedure_ids ) . "), using strategy: {$strategy}" );

		// If only one procedure, always use single post approach regardless of strategy
		if ( $procedure_count === 1 ) {
			error_log( "BRAG book Gallery Sync: Single procedure case {$case_id}, creating single post" );
			$single_result = $this->create_single_case_post( $case_id, $case_data, $existing_post );

			return [ $single_result ];
		}

		// Handle multi-procedure cases based on strategy
		if ( $strategy === 'single_post' ) {
			error_log( "BRAG book Gallery Sync: Creating single post with multiple procedure taxonomies for case {$case_id}" );
			$single_result = $this->create_single_case_post( $case_id, $case_data, $existing_post );

			return [ $single_result ];
		}

		// Default strategy: separate posts for each procedure
		error_log( "BRAG book Gallery Sync: Creating separate posts for each procedure in case {$case_id}" );

		// For each procedure, create a separate case post
		foreach ( $procedure_ids as $procedure_index => $procedure_id ) {
			try {
				// Create a modified case ID that includes the procedure
				$procedure_case_id = $case_id . '_' . $procedure_id;

				// Create modified case data for this specific procedure
				$procedure_case_data                     = $case_data;
				$procedure_case_data['procedureIds']     = [ $procedure_id ]; // Only assign this one procedure
				$procedure_case_data['original_case_id'] = $case_id; // Store original case ID for reference

				// Check if this specific procedure case already exists
				$existing_procedure_post = $this->find_existing_procedure_case_post( $case_id, $procedure_id );
				$is_update               = ! empty( $existing_procedure_post );

				// Create or update the procedure-specific case post
				if ( $is_update ) {
					error_log( "BRAG book Gallery Sync: Updating existing procedure case {$procedure_case_id} (post ID: {$existing_procedure_post->ID})..." );
					$post_id = $this->update_case_post( $existing_procedure_post->ID, $procedure_case_data );
				} else {
					error_log( "BRAG book Gallery Sync: Creating new procedure case post {$procedure_case_id}..." );
					$post_id = $this->create_case_post( $procedure_case_data );
				}

				if ( ! $post_id || is_wp_error( $post_id ) ) {
					$error_message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Unknown error creating/updating post';
					throw new Exception( "Failed to create/update post for procedure case {$procedure_case_id}: {$error_message}" );
				}

				// Save API data to meta fields (with procedure-specific data)
				// Pass a progress callback for image downloads
				$progress_callback = function ( $message ) {
					$this->update_step_progress( $message );
				};
				\BRAGBookGallery\Includes\Extend\Post_Types::save_api_response_data( $post_id, $procedure_case_data, $progress_callback );

				// Store the original case ID and procedure mapping
				update_post_meta( $post_id, 'brag_book_gallery_original_case_id', $case_id );
				update_post_meta( $post_id, '_procedure_id', $procedure_id );
				update_post_meta( $post_id, '_procedure_index', $procedure_index );

				// Assign only this specific procedure taxonomy
				$this->assign_single_procedure_taxonomy( $post_id, $procedure_id );

				// Log the operation
				$this->log_case_operation( $post_id, $procedure_case_data, ! $is_update );

				$procedure_results[] = [
					'case_id'          => $procedure_case_id,
					'original_case_id' => $case_id,
					'procedure_id'     => $procedure_id,
					'post_id'          => $post_id,
					'created'          => ! $is_update,
					'updated'          => $is_update,
				];

				error_log( "BRAG book Gallery Sync: ✓ Successfully processed procedure case {$procedure_case_id}" );

			} catch ( Exception $e ) {
				error_log( "BRAG book Gallery Sync: ✗ Failed to process procedure case {$procedure_case_id}: " . $e->getMessage() );
				$procedure_results[] = [
					'case_id'          => $procedure_case_id ?? $case_id,
					'original_case_id' => $case_id,
					'procedure_id'     => $procedure_id,
					'post_id'          => null,
					'created'          => false,
					'updated'          => false,
					'error'            => $e->getMessage(),
				];
			}
		}

		// Clean up any old single case post if it exists (migration from old system)
		if ( $existing_post && count( $procedure_ids ) > 1 ) {
			error_log( "BRAG book Gallery Sync: Cleaning up old single case post {$existing_post->ID} for multi-procedure case {$case_id}" );
			wp_delete_post( $existing_post->ID, true );
		}

		return $procedure_results;
	}

	/**
	 * Create single case post for cases with one procedure or fallback
	 *
	 * @param int $case_id Case ID
	 * @param array $case_data Case data from API
	 * @param \WP_Post|null $existing_post Existing post if found
	 *
	 * @return array Processing result
	 * @throws Exception If processing fails
	 * @since 3.0.0
	 */
	private function create_single_case_post( int $case_id, array $case_data, ?\WP_Post $existing_post = null ): array {
		$is_update = ! empty( $existing_post );

		// Create or update case post
		if ( $is_update ) {
			error_log( "BRAG book Gallery Sync: Updating existing case {$case_id} (post ID: {$existing_post->ID})..." );
			$post_id = $this->update_case_post( $existing_post->ID, $case_data );
		} else {
			error_log( "BRAG book Gallery Sync: Creating new case post for case {$case_id}..." );
			$post_id = $this->create_case_post( $case_data );
		}

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			$error_message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Unknown error creating/updating post';
			throw new Exception( "Failed to create/update post for case {$case_id}: {$error_message}" );
		}

		// Save API data to meta fields
		// Pass a progress callback for image downloads
		$progress_callback = function ( $message ) {
			$this->update_step_progress( $message );
		};
		\BRAGBookGallery\Includes\Extend\Post_Types::save_api_response_data( $post_id, $case_data, $progress_callback );

		// Assign taxonomies (all procedures for single post)
		$this->assign_case_taxonomies( $post_id, $case_data );

		// Assign doctor taxonomy if enabled (website property ID 111)
		$this->maybe_assign_doctor_taxonomy( $post_id, $case_data );

		// Log the operation
		$this->log_case_operation( $post_id, $case_data, ! $is_update );

		return [
			'case_id' => $case_id,
			'post_id' => $post_id,
			'created' => ! $is_update,
			'updated' => $is_update,
		];
	}

	/**
	 * Find existing procedure-specific case post
	 *
	 * @param int $original_case_id Original case ID
	 * @param int $procedure_id Procedure ID
	 *
	 * @return \WP_Post|null Found post or null
	 * @since 3.0.0
	 */
	private function find_existing_procedure_case_post( int $original_case_id, int $procedure_id ): ?\WP_Post {
		$query = new \WP_Query( [
			'post_type'      => 'brag_book_cases',
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => 1,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => 'brag_book_gallery_original_case_id',
					'value'   => $original_case_id,
					'compare' => '=',
				],
				[
					'key'     => '_procedure_id',
					'value'   => $procedure_id,
					'compare' => '=',
				],
			],
			'fields'         => 'ids',
		] );

		if ( $query->have_posts() ) {
			return get_post( $query->posts[0] );
		}

		return null;
	}

	/**
	 * Assign single procedure taxonomy to post
	 *
	 * @param int $post_id Post ID
	 * @param int $procedure_id Procedure ID to assign
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function assign_single_procedure_taxonomy( int $post_id, int $procedure_id ): void {
		error_log( "BRAG book Gallery Sync: assign_single_procedure_taxonomy() - Attempting to assign procedure ID {$procedure_id} to post {$post_id}" );

		// Find the term that matches this procedure ID
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'   => 'procedure_id',
					'value' => $procedure_id,
					'type'  => 'NUMERIC',
				],
			],
		] );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// Assign only this one procedure term
			wp_set_post_terms( $post_id, [ $terms[0]->term_id ], Taxonomies::TAXONOMY_PROCEDURES );
			error_log( "BRAG book Gallery Sync: ✓ Assigned procedure term '{$terms[0]->name}' (ID: {$terms[0]->term_id}) to post {$post_id}" );

			return;
		}

		// DEBUG: Log available procedure terms and their meta
		error_log( "BRAG book Gallery Sync: DEBUG - Could not find procedure term for procedure ID {$procedure_id}" );
		$all_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
		] );

		if ( ! empty( $all_terms ) && ! is_wp_error( $all_terms ) ) {
			error_log( "BRAG book Gallery Sync: DEBUG - Available procedure terms:" );
			foreach ( array_slice( $all_terms, 0, 10 ) as $term ) { // Show first 10 terms
				$stored_id = get_term_meta( $term->term_id, 'procedure_id', true );
				error_log( "  - Term: '{$term->name}' (ID: {$term->term_id}, slug: {$term->slug}, stored procedure_id: {$stored_id})" );
			}
		}

		// FALLBACK STRATEGY 1: Try to match by API data containing the ID
		$terms_with_api_data = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'     => 'api_data',
					'compare' => 'EXISTS',
				],
			],
		] );

		if ( ! empty( $terms_with_api_data ) && ! is_wp_error( $terms_with_api_data ) ) {
			foreach ( $terms_with_api_data as $term ) {
				$api_data = get_term_meta( $term->term_id, 'api_data', true );
				if ( is_array( $api_data ) && isset( $api_data['api_id'] ) && (int) $api_data['api_id'] === $procedure_id ) {
					wp_set_post_terms( $post_id, [ $term->term_id ], Taxonomies::TAXONOMY_PROCEDURES );
					error_log( "BRAG book Gallery Sync: ✓ Assigned procedure term '{$term->name}' (via api_data match) to post {$post_id}" );

					return;
				}
			}
		}

		// FALLBACK: If no matching procedure term found, assign to a default procedure
		error_log( "BRAG book Gallery Sync: ✗ Could not find procedure term for procedure ID {$procedure_id}, assigning fallback" );
		$this->assign_fallback_procedure( $post_id, $procedure_id );
	}

	/**
	 * Process a single case by ID
	 *
	 * @param int $case_id Case ID
	 *
	 * @return array Processing result
	 * @throws Exception If processing fails
	 * @since 3.0.0
	 */
	private function process_single_case( int $case_id ): array {
		try {
			error_log( "BRAG book Gallery Sync: process_single_case() starting for case {$case_id}" );

			// Fetch case details from API
			error_log( "BRAG book Gallery Sync: Fetching case details for case {$case_id}..." );
			$case_data = $this->fetch_case_details( $case_id );
			error_log( "BRAG book Gallery Sync: ✓ Case details fetched for case {$case_id}" );

			// First validation: Skip cases not approved for website use
			if ( ! isset( $case_data['isForWebsite'] ) || ! $case_data['isForWebsite'] ) {
				error_log( sprintf(
					'BRAG book Gallery Sync: Skipping case %s - not approved for website (isForWebsite: %s)',
					$case_id,
					isset( $case_data['isForWebsite'] ) ? ( $case_data['isForWebsite'] ? 'true' : 'false' ) : 'not set'
				) );

				// If this case exists in WordPress but is no longer approved for website, delete it
				$existing_post = $this->find_existing_case_post( $case_id );
				if ( $existing_post ) {
					error_log( "BRAG book Gallery Sync: Deleting existing case {$case_id} (post ID: {$existing_post->ID}) - no longer approved for website" );
					wp_delete_post( $existing_post->ID, true ); // true = force delete, skip trash
				}

				return [
					'case_id' => $case_id,
					'post_id' => null,
					'created' => false,
					'updated' => false,
					'skipped' => true,
					'reason'  => 'Not approved for website (isForWebsite: false)',
				];
			}

			// Memory optimization: Clear any unnecessary variables from API response
			if ( isset( $case_data['debug'] ) ) {
				unset( $case_data['debug'] );
			}
			if ( isset( $case_data['raw_response'] ) ) {
				unset( $case_data['raw_response'] );
			}

			// Check if case already exists
			error_log( "BRAG book Gallery Sync: Checking if case {$case_id} already exists..." );
			$existing_post = $this->find_existing_case_post( $case_id );
			$is_update     = ! empty( $existing_post );
			error_log( "BRAG book Gallery Sync: Case {$case_id} " . ( $is_update ? "exists (post ID: {$existing_post->ID})" : "does not exist" ) );

			// Create or update case post
			if ( $is_update ) {
				error_log( "BRAG book Gallery Sync: Updating existing case {$case_id} (post ID: {$existing_post->ID})..." );
				$post_id = $this->update_case_post( $existing_post->ID, $case_data );
				error_log( "BRAG book Gallery Sync: ✓ Case {$case_id} updated successfully" );
			} else {
				error_log( "BRAG book Gallery Sync: Creating new case post for case {$case_id}..." );
				$post_id = $this->create_case_post( $case_data );
				error_log( "BRAG book Gallery Sync: ✓ Case {$case_id} created successfully with post ID: {$post_id}" );
			}

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				$error_message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Unknown error creating/updating post';
				throw new Exception( "Failed to create/update post for case {$case_id}: {$error_message}" );
			}

			// Save API data to meta fields
			error_log( "BRAG book Gallery Sync: Saving API data to meta fields for case {$case_id}..." );
			\BRAGBookGallery\Includes\Extend\Post_Types::save_api_response_data( $post_id, $case_data );
			error_log( "BRAG book Gallery Sync: ✓ API data saved for case {$case_id}" );

			// Assign taxonomies
			error_log( "BRAG book Gallery Sync: Assigning taxonomies for case {$case_id}..." );
			$this->assign_case_taxonomies( $post_id, $case_data );
			error_log( "BRAG book Gallery Sync: ✓ Taxonomies assigned for case {$case_id}" );

			// Log the operation
			error_log( "BRAG book Gallery Sync: Logging operation for case {$case_id}..." );
			$this->log_case_operation( $post_id, $case_data, ! $is_update );
			error_log( "BRAG book Gallery Sync: ✓ Operation logged for case {$case_id}" );

			error_log( "BRAG book Gallery Sync: process_single_case() completed successfully for case {$case_id}" );

			// Memory cleanup: Clear case data to free memory
			$case_data = null;
			unset( $case_data );

			return [
				'case_id' => $case_id,
				'post_id' => $post_id,
				'created' => ! $is_update,
				'updated' => $is_update,
			];

		} catch ( Exception $e ) {
			error_log( "BRAG book Gallery Sync: ✗ process_single_case() failed for case {$case_id}: " . $e->getMessage() );
			error_log( "BRAG book Gallery Sync: Exception stack trace: " . $e->getTraceAsString() );
			throw $e;
		}
	}

	/**
	 * Fetch case details from API
	 *
	 * @param int $case_id Case ID
	 *
	 * @return array Case data
	 * @throws Exception If API request fails
	 * @since 3.0.0
	 */
	private function fetch_case_details( int $case_id ): array {
		error_log( "BRAG book Gallery Sync: fetch_case_details() starting for case {$case_id} (v2 API)" );

		$api_token           = get_option( 'brag_book_gallery_api_token', [] )[0] ?? '';
		$website_property_id = get_option( 'brag_book_gallery_website_property_id', [] )[0] ?? 0;

		if ( empty( $api_token ) || $website_property_id <= 0 ) {
			error_log( "BRAG book Gallery Sync: ✗ Invalid API configuration for case {$case_id}" );
			throw new Exception( __( 'Invalid API configuration', 'brag-book-gallery' ) );
		}

		// Try to get procedure ID from existing case post if it exists
		$procedure_id  = null;
		$existing_post = $this->find_existing_case_post( $case_id );
		if ( $existing_post ) {
			$stored_procedure_ids = get_post_meta( $existing_post->ID, 'brag_book_gallery_procedure_ids', true );
			if ( ! empty( $stored_procedure_ids ) ) {
				$procedure_ids_array = explode( ',', $stored_procedure_ids );
				$procedure_id        = intval( $procedure_ids_array[0] );
			}
		}

		// Use Endpoints class for v2 API call
		$request_start = microtime( true );
		$endpoints     = new \BRAGBookGallery\Includes\REST\Endpoints();
		$response      = $endpoints->get_case_detail_v2(
			$api_token,
			$case_id,
			intval( $website_property_id ),
			$procedure_id
		);
		$request_duration = round( ( microtime( true ) - $request_start ) * 1000, 2 );

		error_log( "BRAG book Gallery Sync: API request for case {$case_id} completed in {$request_duration}ms" );

		if ( ! $response || ! isset( $response['data']['case'] ) ) {
			error_log( "BRAG book Gallery Sync: ✗ No case data found in v2 API response for case {$case_id}" );
			throw new Exception( __( 'No case data found in API response', 'brag-book-gallery' ) );
		}

		error_log( 'BRAG book Gallery Sync: Successfully fetched case details for case ' . $case_id );

		// Normalize v2 data to v1 format
		return $this->normalize_v2_case_data( $response['data']['case'] );
	}

	/**
	 * Normalize v2 API data to v1 format for backward compatibility
	 *
	 * Converts the v2 nested data structure to the flat v1 format.
	 *
	 * @since 3.3.0
	 * @param array $v2_data v2 API case data
	 * @return array Normalized data in v1 format
	 */
	private function normalize_v2_case_data( array $v2_data ): array {
		$case_id = $v2_data['caseId'] ?? 'unknown';

		// DEBUG: Log incoming v2 data structure
		error_log( "=== DATA_SYNC NORMALIZATION DEBUG: Case {$case_id} ===" );
		error_log( "V2 Data Keys: " . implode( ', ', array_keys( $v2_data ) ) );
		error_log( "Has patientInfo: " . ( isset( $v2_data['patientInfo'] ) ? 'YES' : 'NO' ) );
		error_log( "Has seoInfo: " . ( isset( $v2_data['seoInfo'] ) ? 'YES' : 'NO' ) );
		error_log( "Has photoSets: " . ( isset( $v2_data['photoSets'] ) ? 'YES (' . count( $v2_data['photoSets'] ) . ' sets)' : 'NO' ) );
		error_log( "Has postOp: " . ( isset( $v2_data['postOp'] ) ? 'YES' : 'NO' ) );
		if ( isset( $v2_data['postOp'] ) ) {
			error_log( "postOp data in v2: " . print_r( $v2_data['postOp'], true ) );
		}

		if ( isset( $v2_data['photoSets'][0]['images'] ) ) {
			$first_images = $v2_data['photoSets'][0]['images'];
			error_log( "First photoSet image keys: " . implode( ', ', array_keys( $first_images ) ) );
			error_log( "Before URL: " . ( $first_images['before']['url'] ?? 'MISSING' ) );
			error_log( "After URL: " . ( $first_images['after']['url'] ?? 'MISSING' ) );
		}

		$normalized = [];

		// Basic fields
		$normalized['caseId']       = $v2_data['caseId'] ?? 0;
		$normalized['procedureIds'] = $v2_data['procedureIds'] ?? [];
		$normalized['categoryIds']  = $v2_data['categoryIds'] ?? [];

		// CRITICAL: v2 API doesn't return isForWebsite field, but save_api_response_data() requires it
		// All cases from v2 API are approved for website use (filtered server-side)
		$normalized['isForWebsite'] = true;

		// Patient data (move from patientInfo to root)
		if ( isset( $v2_data['patientInfo'] ) ) {
			$normalized['age']       = $v2_data['patientInfo']['age'] ?? null;
			$normalized['gender']    = $v2_data['patientInfo']['gender'] ?? null;
			$normalized['ethnicity'] = $v2_data['patientInfo']['ethnicity'] ?? null;
			$normalized['height']    = $v2_data['patientInfo']['height'] ?? null;
			$normalized['weight']    = $v2_data['patientInfo']['weight'] ?? null;
		}

		// SEO data (convert from seoInfo to caseDetails array format)
		if ( isset( $v2_data['seoInfo'] ) ) {
			$normalized['caseDetails'] = [
				[
					'seoSuffixUrl'       => $v2_data['seoInfo']['slug'] ?? '',
					'seoHeadline'        => $v2_data['seoInfo']['headline'] ?? '',
					'seoPageTitle'       => $v2_data['seoInfo']['title'] ?? '',
					'seoPageDescription' => $v2_data['seoInfo']['metaDescription'] ?? '',
				],
			];
		}

		// Photo sets (convert nested v2 structure to flat v1 field names)
		if ( isset( $v2_data['photoSets'] ) ) {
			$normalized['photoSets'] = [];

			foreach ( $v2_data['photoSets'] as $photo_set ) {
				$images = $photo_set['images'] ?? [];

				$flat_photo_set = [
					'beforeLocationUrl'                    => $images['before']['url'] ?? '',
					'afterLocationUrl1'                    => $images['after']['url'] ?? '',
					'afterLocationUrl2'                    => $images['afterPlus']['url'] ?? '',
					'afterLocationUrl3'                    => '',
					'postProcessedImageLocation'           => $images['sideBySide']['standard']['url'] ?? '',
					'highResPostProcessedImageLocation'    => $images['sideBySide']['highDefinition']['url'] ?? '',
					'seoAltText'                           => $images['before']['altText'] ?? $images['after']['altText'] ?? '',
					'isNude'                               => false,
				];

				$normalized['photoSets'][] = $flat_photo_set;
			}
		}

		// Procedure details
		$normalized['procedureDetails'] = $v2_data['procedureDetails'] ?? [];

		// v2-specific fields
		$normalized['description'] = $v2_data['description'] ?? '';
		$normalized['createdAt']   = $v2_data['createdAt'] ?? '';
		$normalized['updatedAt']   = $v2_data['updatedAt'] ?? '';

		// Pass through creator information
		if ( isset( $v2_data['creator'] ) ) {
			$normalized['creator'] = $v2_data['creator'];
		}

		// Pass through postOp information
		if ( isset( $v2_data['postOp'] ) ) {
			$normalized['postOp'] = $v2_data['postOp'];
		}

		// DEBUG: Log normalized output
		error_log( "Normalized Data Keys: " . implode( ', ', array_keys( $normalized ) ) );
		error_log( "Normalized age: " . ( $normalized['age'] ?? 'NULL' ) );
		error_log( "Normalized gender: " . ( $normalized['gender'] ?? 'NULL' ) );
		error_log( "Normalized photoSets count: " . ( isset( $normalized['photoSets'] ) ? count( $normalized['photoSets'] ) : '0' ) );
		if ( isset( $normalized['photoSets'][0] ) ) {
			error_log( "First normalized photoSet keys: " . implode( ', ', array_keys( $normalized['photoSets'][0] ) ) );
			error_log( "Normalized beforeLocationUrl: " . ( $normalized['photoSets'][0]['beforeLocationUrl'] ?? 'MISSING' ) );
			error_log( "Normalized afterLocationUrl1: " . ( $normalized['photoSets'][0]['afterLocationUrl1'] ?? 'MISSING' ) );
		}
		if ( isset( $normalized['caseDetails'][0] ) ) {
			error_log( "Normalized seoPageTitle: " . ( $normalized['caseDetails'][0]['seoPageTitle'] ?? 'MISSING' ) );
		}
		error_log( "Normalized has postOp: " . ( isset( $normalized['postOp'] ) ? 'YES' : 'NO' ) );
		if ( isset( $normalized['postOp'] ) ) {
			error_log( "Normalized postOp data: " . print_r( $normalized['postOp'], true ) );
		}
		error_log( "=== END DATA_SYNC NORMALIZATION DEBUG ===" );

		return $normalized;
	}

	/**
	 * Find existing case post by procedure case ID
	 *
	 * @param int $case_id Procedure case ID
	 *
	 * @return \WP_Post|null Existing post or null
	 * @since 3.0.0
	 */
	private function find_existing_case_post( int $case_id ): ?\WP_Post {
		$posts = get_posts( [
			'post_type'   => 'brag_book_cases',
			'meta_key'    => 'brag_book_gallery_procedure_case_id',
			'meta_value'  => $case_id,
			'numberposts' => 1,
			'post_status' => 'any',
		] );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Create new case post
	 *
	 * @param array $case_data Case data from API
	 *
	 * @return int Post ID
	 * @throws Exception If post creation fails
	 * @since 3.0.0
	 */
	private function create_case_post( array $case_data ): int {
		// Generate title from case data
		$title = $this->generate_case_title( $case_data );

		// Generate slug from seoSuffixUrl or fallback to case ID
		$slug = $this->generate_case_slug( $case_data );

		// HIPAA COMPLIANCE: Do NOT store case details as they may contain PHI
		// Use the main gallery shortcode which auto-detects context for case view
		$content = sprintf(
			'[brag_book_gallery]

<!-- Case ID: %d, Procedure ID: %d, Synced: %s -->',
			$case_data['caseId'] ?? 0,
			$case_data['procedureId'] ?? 0,
			current_time( 'mysql' )
		);

		// Prepare meta description
		$meta_description = '';
		if ( isset( $case_data['seoInfo']['metaDescription'] ) && ! empty( $case_data['seoInfo']['metaDescription'] ) ) {
			$meta_description = wp_strip_all_tags( $case_data['seoInfo']['metaDescription'] );
		}

		$post_data = [
			'post_type'    => 'brag_book_cases',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => isset( $case_data['draft'] ) && $case_data['draft'] ? 'draft' : 'publish',
			'meta_input'   => [
				'brag_book_gallery_case_id'       => $case_data['caseId'],
				'brag_book_gallery_synced_at'    => current_time( 'mysql' ),
				'_yoast_wpseo_metadesc' => $meta_description,
			],
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( sprintf(
				__( 'Failed to create case post: %s', 'brag-book-gallery' ),
				$post_id->get_error_message()
			) );
		}

		// Register case in sync registry
		if ( $this->database ) {
			$this->database->upsert_registry_item(
				'case',
				(int) ( $case_data['caseId'] ?? 0 ),
				$post_id,
				'post',
				$this->get_registry_api_token(),
				$this->get_registry_property_id(),
				$this->sync_session_id
			);
		}

		return $post_id;
	}

	/**
	 * Update existing case post
	 *
	 * @param int $post_id Existing post ID
	 * @param array $case_data Case data from API
	 *
	 * @return int Post ID
	 * @throws Exception If post update fails
	 * @since 3.0.0
	 */
	private function update_case_post( int $post_id, array $case_data ): int {
		// Generate title from case data
		$title = $this->generate_case_title( $case_data );

		// Generate slug from seoSuffixUrl or fallback to case ID
		$slug = $this->generate_case_slug( $case_data );

		// HIPAA COMPLIANCE: Do NOT store case details as they may contain PHI
		// Use the main gallery shortcode which auto-detects context for case view
		$content = sprintf(
			'[brag_book_gallery]

<!-- Case ID: %d, Procedure ID: %d, Synced: %s -->',
			$case_data['caseId'] ?? 0,
			$case_data['procedureId'] ?? 0,
			current_time( 'mysql' )
		);

		$post_data = [
			'ID'           => $post_id,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => isset( $case_data['draft'] ) && $case_data['draft'] ? 'draft' : 'publish',
		];

		$result = wp_update_post( $post_data, true );

		// Update meta description separately
		if ( isset( $case_data['seoInfo']['metaDescription'] ) && ! empty( $case_data['seoInfo']['metaDescription'] ) ) {
			$meta_description = wp_strip_all_tags( $case_data['seoInfo']['metaDescription'] );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
		}

		if ( is_wp_error( $result ) ) {
			throw new Exception( sprintf(
				__( 'Failed to update case post: %s', 'brag-book-gallery' ),
				$result->get_error_message()
			) );
		}

		// Update sync timestamp
		update_post_meta( $post_id, 'brag_book_gallery_synced_at', current_time( 'mysql' ) );

		// Register case in sync registry
		if ( $this->database ) {
			$this->database->upsert_registry_item(
				'case',
				(int) ( $case_data['caseId'] ?? 0 ),
				$post_id,
				'post',
				$this->get_registry_api_token(),
				$this->get_registry_property_id(),
				$this->sync_session_id
			);
		}

		return $post_id;
	}

	/**
	 * Clean up memory after processing a procedure's cases
	 *
	 * @return void
	 * @since 3.3.0
	 */
	private function cleanup_memory_after_procedure(): void {
		// Clear WordPress object cache for cases
		wp_cache_flush_group( 'posts' );
		wp_cache_flush_group( 'post_meta' );

		// Clear any transients related to this procedure
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_brag_book_%' AND option_name NOT LIKE '%sync%'" );

		// Clear internal arrays that may be holding references
		if ( isset( $this->processed_cases ) ) {
			$this->processed_cases = [];
		}

		// Force PHP garbage collection multiple times for 64MB systems
		if ( function_exists( 'gc_collect_cycles' ) ) {
			$memory_limit_bytes = $this->get_memory_limit_bytes();
			gc_collect_cycles();
			if ( $memory_limit_bytes <= 67108864 ) { // 64MB system
				gc_collect_cycles(); // Second pass
				gc_collect_cycles(); // Third pass for very low memory
			}
		}
	}

	/**
	 * Aggressive memory cleanup to stay under 50% memory usage
	 *
	 * @return void
	 * @since 3.3.0
	 */
	private function aggressive_memory_cleanup(): void {
		// Log memory before cleanup
		$memory_before      = memory_get_usage( true );
		$real_memory_before = memory_get_usage( false );
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY-CLEANUP] Before cleanup - Reserved: %s, Real: %s',
			size_format( $memory_before ),
			size_format( $real_memory_before )
		) );

		// Disable Query Monitor during sync to save memory
		if ( class_exists( 'QueryMonitor' ) || class_exists( 'QM' ) ) {
			// Remove all Query Monitor hooks
			remove_all_actions( 'qm/collect' );
			remove_all_filters( 'qm/collect' );
			remove_all_actions( 'qm/output' );
			remove_all_filters( 'qm/output' );
			// Disable Query Monitor collectors
			if ( function_exists( 'qm_dispatchers' ) ) {
				$dispatchers = qm_dispatchers();
				foreach ( $dispatchers as $dispatcher ) {
					remove_action( 'shutdown', [ $dispatcher, 'dispatch' ], 0 );
				}
			}
		}

		// Clear all WordPress caches
		wp_cache_flush();

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}

		// Clear any query results cache
		global $wpdb;
		$wpdb->flush();

		// Clear transients aggressively but preserve sync-related ones
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '%sync_progress%' AND option_name NOT LIKE '%sync_start_time%' AND option_name NOT LIKE '%sync_state%' AND option_name NOT LIKE '%sync_activity%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_name NOT LIKE '%sync_progress%' AND option_name NOT LIKE '%sync_start_time%' AND option_name NOT LIKE '%sync_state%' AND option_name NOT LIKE '%sync_activity%'" );

		// Clear any internal arrays
		if ( isset( $this->processed_cases ) ) {
			$this->processed_cases = [];
		}
		if ( isset( $this->case_cache ) ) {
			$this->case_cache = [];
		}

		// Reset WordPress post cache
		clean_post_cache( 0 );

		// Clear term cache
		if ( function_exists( 'clean_term_cache' ) ) {
			clean_term_cache( 0 );
		}

		// Clear user cache
		if ( function_exists( 'clean_user_cache' ) ) {
			clean_user_cache( 0 );
		}

		// Force multiple garbage collection cycles
		if ( function_exists( 'gc_collect_cycles' ) ) {
			$cycles_before = gc_collect_cycles();
			$cycles_after  = gc_collect_cycles(); // Run twice for thorough cleanup
			error_log( sprintf(
				'BRAG book Gallery Sync: [MEMORY-CLEANUP] GC collected %d + %d cycles',
				$cycles_before,
				$cycles_after
			) );
		}

		// Log memory after cleanup
		$memory_after      = memory_get_usage( true );
		$real_memory_after = memory_get_usage( false );
		$memory_freed      = $memory_before - $memory_after;
		$real_memory_freed = $real_memory_before - $real_memory_after;
		error_log( sprintf(
			'BRAG book Gallery Sync: [MEMORY-CLEANUP] After cleanup - Reserved: %s (freed: %s), Real: %s (freed: %s)',
			size_format( $memory_after ),
			size_format( $memory_freed ),
			size_format( $real_memory_after ),
			size_format( $real_memory_freed )
		) );
	}

	/**
	 * Get memory limit in bytes
	 *
	 * @return int Memory limit in bytes
	 * @since 3.3.0
	 */
	private function get_memory_limit_bytes(): int {
		// First check WordPress constants
		if ( defined( 'WP_MEMORY_LIMIT' ) && WP_MEMORY_LIMIT ) {
			$memory_limit = WP_MEMORY_LIMIT;
		} else {
			$memory_limit = ini_get( 'memory_limit' );
		}

		// Convert to bytes
		if ( preg_match( '/^(\d+)(.)$/i', $memory_limit, $matches ) ) {
			$unit  = strtoupper( $matches[2] );
			$value = (int) $matches[1];
			if ( $unit === 'G' ) {
				return $value * 1024 * 1024 * 1024; // GB to bytes
			} elseif ( $unit === 'M' ) {
				return $value * 1024 * 1024; // MB to bytes
			} elseif ( $unit === 'K' ) {
				return $value * 1024; // KB to bytes
			}
		}

		return (int) $memory_limit;
	}

	/**
	 * Generate case title from API data
	 *
	 * Uses seoInfo.title if available, otherwise builds from case data.
	 *
	 * @param array $case_data Case data from API
	 *
	 * @return string Generated title
	 * @since 3.0.0
	 */
	private function generate_case_title( array $case_data ): string {
		// First, check for seoInfo.title (primary source)
		if ( isset( $case_data['seoInfo']['title'] ) && ! empty( $case_data['seoInfo']['title'] ) ) {
			return wp_strip_all_tags( $case_data['seoInfo']['title'] );
		}

		// Fallback: build title from case data
		$case_id = $case_data['caseId'] ?? 'Unknown';
		$age     = $case_data['age'] ?? '';
		$gender  = $case_data['gender'] ?? '';

		// Try to get procedure names from IDs
		$procedure_names = [];
		if ( isset( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			foreach ( $case_data['procedureIds'] as $procedure_id ) {
				$term = get_terms( [
					'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
					'meta_key'   => 'procedure_id',
					'meta_value' => $procedure_id,
					'hide_empty' => false,
					'number'     => 1,
				] );

				if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
					$procedure_names[] = $term[0]->name;
				}
			}
		}

		// Build title components
		$title_parts = [];

		if ( ! empty( $procedure_names ) ) {
			$title_parts[] = implode( ', ', $procedure_names );
		}

		if ( $age && $gender ) {
			$title_parts[] = ucfirst( $gender ) . ', ' . $age;
		} elseif ( $gender ) {
			$title_parts[] = ucfirst( $gender );
		} elseif ( $age ) {
			$title_parts[] = 'Age ' . $age;
		}

		$title_parts[] = 'Case ' . $case_id;

		return implode( ' - ', $title_parts );
	}

	/**
	 * Generate case slug from API data
	 *
	 * Uses seoInfo.slug if available, otherwise falls back to case ID.
	 *
	 * @param array $case_data Case data from API
	 *
	 * @return string Generated slug
	 * @since 3.0.0
	 */
	private function generate_case_slug( array $case_data ): string {
		$case_id = $case_data['caseId'] ?? 'unknown';

		// First, check for seoInfo.slug (primary source)
		if ( isset( $case_data['seoInfo']['slug'] ) && ! empty( $case_data['seoInfo']['slug'] ) ) {
			$slug = sanitize_title( $case_data['seoInfo']['slug'] );

			// Make sure it's not empty after sanitization
			if ( ! empty( $slug ) ) {
				error_log( 'BRAG book Gallery Sync: Using seoInfo.slug for case ' . $case_id . ': ' . $slug );

				return $slug;
			}
		}

		// Fallback to case ID
		$fallback_slug = (string) $case_id;
		error_log( 'BRAG book Gallery Sync: Using fallback slug for case ' . $case_id . ': ' . $fallback_slug );

		return $fallback_slug;
	}

	/**
	 * Assign taxonomies to case post
	 *
	 * @param int $post_id Post ID
	 * @param array $case_data Case data from API
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function assign_case_taxonomies( int $post_id, array $case_data ): void {
		$procedure_ids = $case_data['procedureIds'] ?? [];

		// If no procedure IDs provided, try to assign a fallback
		if ( empty( $procedure_ids ) || ! is_array( $procedure_ids ) ) {
			error_log( "BRAG book Gallery Sync: ✗ No procedure IDs found for post {$post_id}, assigning fallback procedure" );
			$this->assign_fallback_procedure( $post_id );

			return;
		}

		$term_ids = [];

		foreach ( $procedure_ids as $procedure_id ) {
			$terms = get_terms( [
				'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
				'meta_key'   => 'procedure_id',
				'meta_value' => $procedure_id,
				'hide_empty' => false,
			] );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_ids[] = $term->term_id;
				}
			} else {
				error_log( "BRAG book Gallery Sync: ✗ Could not find procedure term for procedure ID {$procedure_id} on post {$post_id}" );
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
			error_log( "BRAG book Gallery Sync: ✓ Assigned " . count( $term_ids ) . " procedure terms to post {$post_id}" );
		} else {
			// FALLBACK: No matching procedure terms found, assign a default
			error_log( "BRAG book Gallery Sync: ✗ No matching procedure terms found for post {$post_id}, assigning fallback" );
			$this->assign_fallback_procedure( $post_id );
		}
	}

	/**
	 * Assign fallback procedure to ensure every case has at least one procedure
	 *
	 * This method ensures that no case is left without a procedure assignment.
	 * It tries multiple fallback strategies to assign an appropriate procedure.
	 *
	 * @param int $post_id Post ID to assign procedure to
	 * @param int|null $original_procedure_id Original procedure ID that failed (optional)
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function assign_fallback_procedure( int $post_id, ?int $original_procedure_id = null ): void {
		// Strategy 1: Try to create missing procedure term if we have the original procedure ID
		if ( $original_procedure_id ) {
			$created_term = $this->create_missing_procedure_term( $original_procedure_id );
			if ( $created_term ) {
				wp_set_post_terms( $post_id, [ $created_term->term_id ], Taxonomies::TAXONOMY_PROCEDURES );
				error_log( "BRAG book Gallery Sync: ✓ Created and assigned missing procedure term for ID {$original_procedure_id} to post {$post_id}" );

				return;
			}
		}

		// Strategy 2: Find and assign to "Other Procedures" or "Miscellaneous" category
		$fallback_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
			'name__in'   => [ 'Other Procedures', 'Miscellaneous', 'Other', 'General Procedures' ],
			'number'     => 1,
		] );

		if ( ! empty( $fallback_terms ) && ! is_wp_error( $fallback_terms ) ) {
			wp_set_post_terms( $post_id, [ $fallback_terms[0]->term_id ], Taxonomies::TAXONOMY_PROCEDURES );
			error_log( "BRAG book Gallery Sync: ✓ Assigned fallback procedure '{$fallback_terms[0]->name}' to post {$post_id}" );

			return;
		}

		// Strategy 3: Create "Other Procedures" category if it doesn't exist
		$fallback_term = wp_insert_term( 'Other Procedures', Taxonomies::TAXONOMY_PROCEDURES, [
			'description' => 'Cases that could not be categorized into specific procedures',
			'parent'      => 0, // Make it a top-level category
		] );

		if ( ! is_wp_error( $fallback_term ) ) {
			// Add meta data for the new term
			update_term_meta( $fallback_term['term_id'], 'procedure_id', 99999 ); // Use high ID for fallback
			update_term_meta( $fallback_term['term_id'], 'nudity', 'false' );

			wp_set_post_terms( $post_id, [ $fallback_term['term_id'] ], Taxonomies::TAXONOMY_PROCEDURES );
			error_log( "BRAG book Gallery Sync: ✓ Created and assigned 'Other Procedures' fallback category to post {$post_id}" );

			return;
		}

		// Strategy 4: Last resort - assign to ANY existing procedure
		$any_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'hide_empty' => false,
			'number'     => 1,
			'orderby'    => 'count',
			'order'      => 'DESC', // Get the most used procedure
		] );

		if ( ! empty( $any_terms ) && ! is_wp_error( $any_terms ) ) {
			wp_set_post_terms( $post_id, [ $any_terms[0]->term_id ], Taxonomies::TAXONOMY_PROCEDURES );
			error_log( "BRAG book Gallery Sync: ✓ Assigned to most common procedure '{$any_terms[0]->name}' as last resort for post {$post_id}" );

			return;
		}

		// If we get here, something is seriously wrong
		error_log( "BRAG book Gallery Sync: ✗ CRITICAL: Could not assign any procedure to post {$post_id} - no procedures exist in taxonomy!" );
	}

	/**
	 * Create missing procedure term from API procedure ID
	 *
	 * Attempts to create a procedure term when one doesn't exist for a given procedure ID.
	 * This helps handle cases where the procedure taxonomy is not fully synced.
	 *
	 * @param int $procedure_id Procedure ID from API
	 *
	 * @return \WP_Term|null Created term or null if failed
	 * @since 3.0.0
	 */
	private function create_missing_procedure_term( int $procedure_id ): ?\WP_Term {
		try {
			// Generate a generic name for the procedure
			$procedure_name = "Procedure {$procedure_id}";

			// Try to get more info from sidebar data if available
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			if ( ! empty( $api_tokens[0] ) ) {
				// This would require implementing a lookup to get procedure name from API
				// For now, use generic name
			}

			// Create the term
			$result = wp_insert_term( $procedure_name, Taxonomies::TAXONOMY_PROCEDURES, [
				'description' => "Auto-created procedure term for procedure ID {$procedure_id}",
				'parent'      => 0, // Make it a top-level category for now
			] );

			if ( is_wp_error( $result ) ) {
				error_log( "BRAG book Gallery Sync: ✗ Failed to create procedure term for ID {$procedure_id}: " . $result->get_error_message() );

				return null;
			}

			// Add meta data for the new term
			update_term_meta( $result['term_id'], 'procedure_id', $procedure_id );
			update_term_meta( $result['term_id'], 'nudity', 'false' );
			update_term_meta( $result['term_id'], 'member_id', '' );

			$term = get_term( $result['term_id'] );
			error_log( "BRAG book Gallery Sync: ✓ Created missing procedure term '{$procedure_name}' for ID {$procedure_id}" );

			return $term;

		} catch ( Exception $e ) {
			error_log( "BRAG book Gallery Sync: ✗ Exception creating procedure term for ID {$procedure_id}: " . $e->getMessage() );

			return null;
		}
	}

	/**
	 * Validate and fix cases without procedures
	 *
	 * This method finds cases that don't have any procedure assignments
	 * and assigns them to fallback procedures. Should be run periodically
	 * or after major sync operations.
	 *
	 * @return array Report of fixed cases
	 * @since 3.0.0
	 */
	public function validate_and_fix_unassigned_cases(): array {
		error_log( "BRAG book Gallery Sync: Starting validation of cases without procedures..." );

		// Find all case posts that don't have any procedure terms assigned
		$unassigned_posts = new \WP_Query( [
			'post_type'      => 'brag_book_cases',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => - 1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
					'operator' => 'NOT EXISTS',
				],
			],
		] );

		$fixed_cases = [];
		$total_found = $unassigned_posts->found_posts;

		error_log( "BRAG book Gallery Sync: Found {$total_found} cases without procedure assignments" );

		if ( $unassigned_posts->have_posts() ) {
			foreach ( $unassigned_posts->posts as $post_id ) {
				try {
					// Try to get original procedure ID from meta
					$original_case_id = get_post_meta( $post_id, 'brag_book_gallery_original_case_id', true );
					$procedure_id     = get_post_meta( $post_id, '_procedure_id', true );

					if ( $procedure_id ) {
						// This is a procedure-specific case, try to assign the correct procedure
						$this->assign_single_procedure_taxonomy( $post_id, $procedure_id );
						$fixed_cases[] = [
							'post_id'      => $post_id,
							'method'       => 'specific_procedure',
							'procedure_id' => $procedure_id,
						];
					} else {
						// Try to get procedure IDs from stored case data
						$procedure_ids_meta = get_post_meta( $post_id, 'brag_book_gallery_procedure_ids', true );
						if ( $procedure_ids_meta ) {
							$procedure_ids = explode( ',', $procedure_ids_meta );
							$case_data     = [ 'procedureIds' => array_map( 'intval', $procedure_ids ) ];
							$this->assign_case_taxonomies( $post_id, $case_data );
							$fixed_cases[] = [
								'post_id'       => $post_id,
								'method'        => 'stored_procedure_ids',
								'procedure_ids' => $procedure_ids,
							];
						} else {
							// Last resort - assign fallback procedure
							$this->assign_fallback_procedure( $post_id );
							$fixed_cases[] = [
								'post_id' => $post_id,
								'method'  => 'fallback',
							];
						}
					}

					error_log( "BRAG book Gallery Sync: ✓ Fixed procedure assignment for post {$post_id}" );

				} catch ( Exception $e ) {
					error_log( "BRAG book Gallery Sync: ✗ Failed to fix procedure assignment for post {$post_id}: " . $e->getMessage() );
					$fixed_cases[] = [
						'post_id' => $post_id,
						'method'  => 'failed',
						'error'   => $e->getMessage(),
					];
				}
			}
		}

		$fixed_count  = count( array_filter( $fixed_cases, fn( $case ) => $case['method'] !== 'failed' ) );
		$failed_count = count( array_filter( $fixed_cases, fn( $case ) => $case['method'] === 'failed' ) );

		$report = [
			'total_found'  => $total_found,
			'fixed_count'  => $fixed_count,
			'failed_count' => $failed_count,
			'details'      => $fixed_cases,
		];

		error_log( "BRAG book Gallery Sync: Validation complete - Fixed: {$fixed_count}, Failed: {$failed_count}" );

		return $report;
	}

	/**
	 * Debug method to run procedure validation and display results
	 *
	 * This method can be called directly to validate and fix any cases
	 * without procedure assignments. Useful for debugging and maintenance.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function debug_validate_procedure_assignments(): void {
		echo "🔍 Starting procedure assignment validation...\n";

		$report = $this->validate_and_fix_unassigned_cases();

		echo "\n📊 Validation Results:\n";
		echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
		echo "Total cases found without procedures: " . $report['total_found'] . "\n";
		echo "Successfully fixed: " . $report['fixed_count'] . "\n";
		echo "Failed to fix: " . $report['failed_count'] . "\n";

		if ( ! empty( $report['details'] ) ) {
			echo "\n📋 Detailed Results:\n";
			echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

			foreach ( $report['details'] as $case ) {
				$post_id    = $case['post_id'];
				$method     = $case['method'];
				$post_title = get_the_title( $post_id ) ?: 'Untitled';

				echo "Post ID {$post_id} ({$post_title}): ";

				switch ( $method ) {
					case 'specific_procedure':
						echo "✅ Fixed using specific procedure ID " . $case['procedure_id'];
						break;
					case 'stored_procedure_ids':
						echo "✅ Fixed using stored procedure IDs: " . implode( ', ', $case['procedure_ids'] );
						break;
					case 'fallback':
						echo "⚠️  Fixed using fallback procedure";
						break;
					case 'failed':
						echo "❌ Failed: " . $case['error'];
						break;
				}
				echo "\n";
			}
		}

		if ( $report['total_found'] === 0 ) {
			echo "\n🎉 All cases have procedures assigned! No issues found.\n";
		} elseif ( $report['failed_count'] === 0 ) {
			echo "\n✅ All unassigned cases have been successfully fixed!\n";
		} else {
			echo "\n⚠️  Some cases could not be fixed. Manual intervention may be required.\n";
		}

		echo "\n🏁 Validation complete!\n";
	}

	/**
	 * Log case operation to sync table
	 *
	 * @param int $post_id Post ID
	 * @param array $case_data Case data from API
	 * @param bool $created Whether case was created or updated
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_case_operation( int $post_id, array $case_data, bool $created ): void {
		global $wpdb;

		// HIPAA Compliance: Only log operational data (IDs, status, counts)
		// Does NOT log: patient names, case details, or any PHI from API data
		$procedure_id   = $case_data['procedureId'] ?? null;
		$operation_type = $created ? 'create' : 'update';

		// Create safe details string with only operational info
		$safe_details = sprintf(
			'Case %s: Post ID %d, API ID %d, Procedure ID %d',
			$operation_type,
			$post_id,
			$case_data['caseId'] ?? 0,
			$procedure_id
		);

		$wpdb->insert(
			$this->log_table,
			[
				'sync_session_id' => $this->sync_session_id,
				'sync_type'       => 'case',
				'operation'       => $operation_type,
				'item_type'       => 'case',
				'wordpress_id'    => $post_id,
				'api_id'          => $case_data['caseId'] ?? null,
				'parent_id'       => $procedure_id,
				'status'          => 'success',
				'details'         => $safe_details,
				'created_at'      => current_time( 'mysql' ),
			],
			[
				'%s', // sync_session_id
				'%s', // sync_type
				'%s', // operation
				'%s', // item_type
				'%d', // wordpress_id
				'%d', // api_id
				'%d', // parent_id
				'%s', // status
				'%s', // details
				'%s', // created_at
			]
		);
	}

	/**
	 * Reverse sync operations for a specific session
	 *
	 * @param string $session_id Sync session ID
	 *
	 * @return array Reverse operation results
	 * @since 3.0.0
	 */
	public function reverse_sync( string $session_id ): array {
		global $wpdb;

		$operations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->log_table}
			 WHERE sync_session_id = %s
			 AND operation IN ('create', 'update')
			 AND status = 'success'
			 ORDER BY id DESC",
			$session_id
		) );

		$deleted = 0;
		$errors  = [];

		foreach ( $operations as $operation ) {
			if ( $operation->operation === 'create' && $operation->wordpress_id ) {
				if ( $operation->item_type === 'case' ) {
					// Delete case post
					$result = wp_delete_post( $operation->wordpress_id, true );
					if ( $result === false ) {
						$errors[] = sprintf(
							__( 'Failed to delete case post %s', 'brag-book-gallery' ),
							$operation->name
						);
					} else {
						$deleted ++;
					}
				} else {
					// Delete taxonomy term
					$result = wp_delete_term( $operation->wordpress_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
					if ( is_wp_error( $result ) ) {
						$errors[] = sprintf(
							__( 'Failed to delete term %s: %s', 'brag-book-gallery' ),
							$operation->name,
							$result->get_error_message()
						);
					} else {
						$deleted ++;
					}
				}
			}
		}

		return [
			'success' => empty( $errors ),
			'deleted' => $deleted,
			'errors'  => $errors,
		];
	}

	/**
	 * Update case processing progress for frontend display
	 *
	 * @param int $processed Number of cases processed
	 * @param int $total Total cases to process
	 * @param string $message Current progress message
	 *
	 * @return void
	 * @since 3.0.0
	 */
	/**
	 * Update detailed progress with procedure and case information
	 *
	 * @param array $progress_data Detailed progress information
	 *
	 * @since 3.0.0
	 */
	private function update_detailed_progress( array $progress_data ): void {
		$progress = [
			'stage'              => $progress_data['stage'] ?? 'cases',
			'overall_percentage' => round( $progress_data['overall_percentage'] ?? 0, 1 ),
			'current_procedure'  => $progress_data['current_procedure'] ?? '',
			'procedure_progress' => [
				'current'    => $progress_data['procedure_current'] ?? 0,
				'total'      => $progress_data['procedure_total'] ?? 0,
				'percentage' => round( $progress_data['procedure_percentage'] ?? 0, 1 ),
			],
			'case_progress'      => [
				'current'    => $progress_data['case_current'] ?? 0,
				'total'      => $progress_data['case_total'] ?? 0,
				'percentage' => round( $progress_data['case_percentage'] ?? 0, 1 ),
			],
			'current_step'       => $progress_data['current_step'] ?? '',
			'recent_cases'       => $progress_data['recent_cases'] ?? [],
			'updated_at'         => current_time( 'mysql' ),
			'sync_session_id'    => $this->sync_session_id,
		];

		// Write to file for real-time monitoring
		$this->write_sync_status_file( $progress );

		// Also update option for backward compatibility
		$update_result = update_option( 'brag_book_gallery_detailed_progress', $progress, false );

		// Clear object cache to ensure fresh data
		wp_cache_delete( 'brag_book_gallery_detailed_progress', 'options' );

		// Log the update result
		error_log( "BRAG book Gallery Sync: update_option result: " . ( $update_result ? 'SUCCESS' : 'FAILED' ) );

		// Verify the option was saved by reading it back
		$saved_progress   = get_option( 'brag_book_gallery_detailed_progress', false );
		$saved_updated_at = $saved_progress['updated_at'] ?? 'NOT_SET';
		error_log( "BRAG book Gallery Sync: Verified saved updated_at: {$saved_updated_at}" );

		$log_msg = "BRAG book Gallery Sync: Detailed Progress - Overall: {$progress['overall_percentage']}%, " .
		           "Procedure: {$progress['procedure_progress']['current']}/{$progress['procedure_progress']['total']} " .
		           "({$progress['procedure_progress']['percentage']}%), " .
		           "Cases: {$progress['case_progress']['current']}/{$progress['case_progress']['total']} " .
		           "({$progress['case_progress']['percentage']}%) - {$progress['current_step']}";
		error_log( $log_msg );
	}

	/**
	 * Quick step progress update for real-time feedback
	 *
	 * Updates just the current step without recalculating percentages
	 * for faster, more responsive progress display
	 *
	 * @param string $step_message Current step being performed
	 *
	 * @since 3.0.0
	 */
	private function update_step_progress( string $step_message ): void {
		// Get current progress data
		$current_progress = get_option( 'brag_book_gallery_detailed_progress', [] );

		// Update only the current step and timestamp
		$current_progress['current_step']    = $step_message;
		$current_progress['updated_at']      = current_time( 'mysql' );
		$current_progress['sync_session_id'] = $this->sync_session_id;

		// Write to file for real-time monitoring
		$this->write_sync_status_file( $current_progress );

		// Save without autoload for performance
		update_option( 'brag_book_gallery_detailed_progress', $current_progress, false );

		// Clear cache to ensure fresh data
		wp_cache_delete( 'brag_book_gallery_detailed_progress', 'options' );

		error_log( "BRAG book Gallery Sync: Step Update - {$step_message}" );
	}

	/**
	 * Legacy progress update method for backward compatibility
	 *
	 * @param int $processed Number of cases processed
	 * @param int $total Total cases to process
	 * @param string $message Current step message
	 *
	 * @since 3.0.0
	 */
	private function update_case_progress( int $processed, int $total, string $message ): void {
		if ( $total <= 0 ) {
			return;
		}

		// Calculate percentage with stage 2 starting at 95%
		$case_percentage    = ( $processed / $total ) * 100;
		$overall_percentage = 95 + ( $case_percentage * 0.05 ); // Stage 2 takes remaining 5%

		// Create progress data similar to sync manager format
		$progress = [
			'total'        => $total,
			'processed'    => $processed,
			'percentage'   => round( $overall_percentage, 2 ),
			'current_step' => $message,
			'stage'        => 'cases',
			'updated_at'   => current_time( 'mysql' ),
		];

		// Store progress in WordPress option for AJAX polling
		update_option( 'brag_book_gallery_case_progress', $progress, false );

		error_log( "BRAG book Gallery Sync: Progress update - {$overall_percentage}% ({$processed}/{$total}): {$message}" );
	}

	/**
	 * Ensure the procedures taxonomy is registered before sync operations
	 *
	 * @throws Exception If taxonomy cannot be registered
	 * @since 3.0.0
	 */
	private function ensure_taxonomy_registered(): void {
		// Check if taxonomy is already registered
		if ( taxonomy_exists( \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES ) ) {
			error_log( 'BRAG book Gallery Sync: ✓ Procedures taxonomy already registered' );

			return;
		}

		error_log( 'BRAG book Gallery Sync: Procedures taxonomy not found, forcing registration...' );

		// Force taxonomy registration by creating a temporary Taxonomies instance
		// This ensures the taxonomy is available even if init hook hasn't run yet
		$taxonomies = new Taxonomies();
		$taxonomies->register_taxonomies();

		// Verify it was registered
		if ( ! taxonomy_exists( \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES ) ) {
			throw new Exception( 'Failed to register procedures taxonomy. Cannot proceed with sync.' );
		}

		error_log( 'BRAG book Gallery Sync: ✓ Procedures taxonomy registration forced successfully' );
	}

	/**
	 * Create or ensure the My Favorites page exists
	 *
	 * Creates a "My Favorites" page with the gallery shortcode if it doesn't exist.
	 * This page is needed for the favorites functionality to work properly.
	 *
	 * @return void
	 * @since 3.3.0
	 */
	private function ensure_favorites_page_exists(): void {
		error_log( 'BRAG book Gallery Sync: Checking for My Favorites page...' );

		// Get the gallery slug
		$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		$gallery_slug        = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;

		// Find the gallery parent page
		$gallery_page = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'name'           => $gallery_slug,
			'posts_per_page' => 1,
		) );

		if ( empty( $gallery_page ) ) {
			error_log( 'BRAG book Gallery Sync: ✗ Gallery page not found with slug: ' . $gallery_slug );

			return;
		}

		$gallery_page_id = $gallery_page[0]->ID;
		error_log( 'BRAG book Gallery Sync: Found gallery page (ID: ' . $gallery_page_id . ', slug: ' . $gallery_slug . ')' );

		// Look for existing favorites page as child of gallery page
		$existing_page = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'post_parent'    => $gallery_page_id,
			'name'           => 'myfavorites',
			'posts_per_page' => 1,
		) );

		if ( ! empty( $existing_page ) ) {
			// Ensure it has the meta flag
			update_post_meta( $existing_page[0]->ID, '_brag_book_gallery_favorites_page', '1' );

			// Trigger rewrite rules flush to ensure the page URL works
			update_option( 'brag_book_gallery_flush_rewrite_rules', true );

			error_log( 'BRAG book Gallery Sync: ✓ My Favorites page already exists as child (ID: ' . $existing_page[0]->ID . ')' );

			return;
		}

		// Check if there's a page with the shortcode content
		$shortcode_page = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			's'              => '[brag_book_gallery view="myfavorites"]'
		) );

		if ( ! empty( $shortcode_page ) ) {
			// Update existing page with our meta flag
			update_post_meta( $shortcode_page[0]->ID, '_brag_book_gallery_favorites_page', '1' );

			// Trigger rewrite rules flush to ensure the page URL works
			update_option( 'brag_book_gallery_flush_rewrite_rules', true );

			error_log( 'BRAG book Gallery Sync: ✓ Found existing page with favorites shortcode, marked it as favorites page (ID: ' . $shortcode_page[0]->ID . ')' );

			return;
		}

		// Create the favorites page as child of gallery page
		$page_data = array(
			'post_title'   => 'My Favorites',
			'post_name'    => 'myfavorites',
			'post_content' => '[brag_book_gallery view="favorites"]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_parent'  => $gallery_page_id, // Make it a child of gallery page
			'post_author'  => 1, // Admin user
		);

		$page_id = wp_insert_post( $page_data );

		if ( is_wp_error( $page_id ) ) {
			error_log( 'BRAG book Gallery Sync: ✗ Failed to create My Favorites page: ' . $page_id->get_error_message() );

			return;
		}

		// Add meta flag to identify this as our favorites page
		update_post_meta( $page_id, '_brag_book_gallery_favorites_page', '1' );

		// Trigger rewrite rules flush to ensure the new page URL works
		update_option( 'brag_book_gallery_flush_rewrite_rules', true );

		error_log( 'BRAG book Gallery Sync: ✓ Created My Favorites page successfully (ID: ' . $page_id . ')' );
	}

	/**
	 * Initialize the sync status file path
	 *
	 * @return void
	 * @since 3.3.2
	 */
	private function init_sync_status_file(): void {
		$upload_dir = wp_upload_dir();
		$sync_dir   = $upload_dir['basedir'] . '/brag-book-sync';

		// Create directory if it doesn't exist
		if ( ! file_exists( $sync_dir ) ) {
			wp_mkdir_p( $sync_dir );

			// Add .htaccess to protect directory
			$htaccess_file = $sync_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "Options -Indexes\nDeny from all" );
			}
		}

		$this->sync_status_file = $sync_dir . '/sync-status.json';
		error_log( 'BRAG book Gallery Sync: Sync status file path: ' . $this->sync_status_file );
	}

	/**
	 * Initialize the Sync API for registration and reporting
	 *
	 * @since 4.0.2
	 *
	 * @return void
	 */
	private function init_sync_api(): void {
		try {
			$this->sync_api = new Sync_Api();
			error_log( 'BRAG book Gallery Sync: Sync API initialized' );
		} catch ( \Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Failed to initialize Sync API: ' . $e->getMessage() );
			$this->sync_api = null;
			$this->enable_sync_reporting = false;
		}
	}

	/**
	 * Set the sync type for registration
	 *
	 * @since 4.0.2
	 *
	 * @param string $sync_type Sync type: 'AUTO' or 'MANUAL'.
	 *
	 * @return void
	 */
	public function set_sync_type( string $sync_type ): void {
		$this->sync_type = in_array( $sync_type, [ Sync_Api::SYNC_TYPE_AUTO, Sync_Api::SYNC_TYPE_MANUAL ], true )
			? $sync_type
			: Sync_Api::SYNC_TYPE_MANUAL;
	}

	/**
	 * Enable or disable sync reporting to BRAG book API
	 *
	 * @since 4.0.2
	 *
	 * @param bool $enable Whether to enable reporting.
	 *
	 * @return void
	 */
	public function set_sync_reporting_enabled( bool $enable ): void {
		$this->enable_sync_reporting = $enable;
	}

	/**
	 * Register sync with BRAG book API
	 *
	 * @since 4.0.2
	 *
	 * @return array|null Registration result or null on failure.
	 */
	private function register_sync_with_api(): ?array {
		error_log( 'BRAG book Gallery Sync: register_sync_with_api() called' );
		error_log( 'BRAG book Gallery Sync: enable_sync_reporting = ' . ( $this->enable_sync_reporting ? 'true' : 'false' ) );
		error_log( 'BRAG book Gallery Sync: sync_api = ' . ( $this->sync_api ? 'initialized' : 'null' ) );

		if ( ! $this->enable_sync_reporting || ! $this->sync_api ) {
			error_log( 'BRAG book Gallery Sync: Skipping registration - sync reporting disabled or API not initialized' );
			return null;
		}

		error_log( 'BRAG book Gallery Sync: Calling sync_api->register_sync() with type: ' . $this->sync_type );
		$result = $this->sync_api->register_sync( $this->sync_type );

		if ( is_wp_error( $result ) ) {
			error_log( 'BRAG book Gallery Sync: Failed to register sync: ' . $result->get_error_message() );
			return null;
		}

		error_log( 'BRAG book Gallery Sync: Registration successful: ' . wp_json_encode( $result ) );
		return $result;
	}

	/**
	 * Report sync status to BRAG book API
	 *
	 * @since 4.0.2
	 *
	 * @param string $status       Status to report.
	 * @param int    $cases_synced Number of cases synced.
	 * @param string $message      Status message.
	 * @param string $error_log    Error details.
	 *
	 * @return array|null Report result or null on failure.
	 */
	private function report_sync_status( string $status, int $cases_synced = 0, string $message = '', string $error_log = '' ): ?array {
		if ( ! $this->enable_sync_reporting || ! $this->sync_api ) {
			return null;
		}

		$result = $this->sync_api->report_sync( $status, $cases_synced, $message, $error_log );

		if ( is_wp_error( $result ) ) {
			error_log( 'BRAG book Gallery Sync: Failed to report sync status: ' . $result->get_error_message() );
			return null;
		}

		return $result;
	}

	/**
	 * Write sync progress to status file
	 *
	 * @param array $progress Progress data to write
	 *
	 * @return void
	 * @since 3.3.2
	 */
	private function write_sync_status_file( array $progress ): void {
		try {
			// Add metadata
			$progress['last_updated']           = time();
			$progress['last_updated_formatted'] = current_time( 'mysql' );

			// Write to file with pretty formatting for easier debugging
			$json_data = wp_json_encode( $progress, JSON_PRETTY_PRINT );

			if ( $json_data !== false ) {
				$bytes_written = file_put_contents( $this->sync_status_file, $json_data );

				if ( $bytes_written === false ) {
					error_log( 'BRAG book Gallery Sync: Failed to write sync status file' );
				} else {
					error_log( 'BRAG book Gallery Sync: Wrote ' . $bytes_written . ' bytes to sync status file' );
				}
			} else {
				error_log( 'BRAG book Gallery Sync: Failed to encode progress data to JSON' );
			}
		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Error writing sync status file: ' . $e->getMessage() );
		}
	}

	/**
	 * Read sync progress from status file
	 *
	 * @return array|null Progress data or null if file doesn't exist
	 * @since 3.3.2
	 */
	public function read_sync_status_file(): ?array {
		if ( ! file_exists( $this->sync_status_file ) ) {
			return null;
		}

		$json_data = file_get_contents( $this->sync_status_file );
		if ( $json_data === false ) {
			return null;
		}

		$data = json_decode( $json_data, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Clear sync status file
	 *
	 * @return void
	 * @since 3.3.2
	 */
	public function clear_sync_status_file(): void {
		if ( file_exists( $this->sync_status_file ) ) {
			unlink( $this->sync_status_file );
			error_log( 'BRAG book Gallery Sync: Cleared sync status file' );
		}
	}

	/**
	 * Store the case order list for a procedure
	 *
	 * Saves the ordered list of case IDs as term meta for efficient retrieval
	 * during gallery display.
	 *
	 * @param int $procedure_id The procedure ID from the API
	 * @param array $case_ids_in_order Array of case IDs in their API response order
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function store_procedure_case_order_list( int $procedure_id, array $case_ids_in_order ): void {
		try {
			// Find the WordPress term for this procedure
			$terms = get_terms( [
				'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
				'meta_key'   => 'procedure_id',
				'meta_value' => $procedure_id,
				'hide_empty' => false,
				'number'     => 1,
			] );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				error_log( "BRAG book Gallery Sync: Warning - No procedure term found for procedure ID {$procedure_id}" );

				return;
			}

			$term = $terms[0];

			if ( empty( $case_ids_in_order ) ) {
				error_log( "BRAG book Gallery Sync: No cases to store for procedure {$procedure_id}" );
				// Clear any existing case order list
				delete_term_meta( $term->term_id, 'brag_book_gallery_case_order_list' );

				return;
			}

			// Store the case order list as term meta
			update_term_meta( $term->term_id, 'brag_book_gallery_case_order_list', $case_ids_in_order );

			error_log( "BRAG book Gallery Sync: ✓ Stored case order list for procedure {$procedure_id} (term: {$term->term_id}) - " . count( $case_ids_in_order ) . " cases" );
			error_log( "BRAG book Gallery Sync: First 5 cases in order: " . implode( ', ', array_slice( $case_ids_in_order, 0, 5 ) ) );
		} catch ( \Exception $e ) {
			error_log( "BRAG book Gallery Sync: Error storing case order list for procedure {$procedure_id}: " . $e->getMessage() );
		}
	}

	/**
	 * Get ordered case post IDs for a specific procedure
	 *
	 * @param int $procedure_id Procedure ID from API
	 *
	 * @return array Array of post IDs in correct order
	 * @since 3.0.0
	 */
	public static function get_ordered_cases_for_procedure( int $procedure_id ): array {
		// Find the WordPress term for this procedure
		$terms = get_terms( [
			'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
			'meta_key'   => 'procedure_id',
			'meta_value' => $procedure_id,
			'hide_empty' => false,
			'number'     => 1,
		] );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		$term = $terms[0];

		// Get the case order list stored on the term
		$case_ids_in_order = get_term_meta( $term->term_id, 'brag_book_gallery_case_order_list', true );
		if ( ! is_array( $case_ids_in_order ) || empty( $case_ids_in_order ) ) {
			error_log( "BRAG book Gallery: No case order list found for procedure {$procedure_id} (term: {$term->term_id})" );

			return [];
		}

		// Get WordPress post IDs for these case IDs in order
		$ordered_post_ids = [];
		foreach ( $case_ids_in_order as $case_id ) {
			// Try current format first (brag_book_gallery_procedure_case_id)
			$posts = get_posts( [
				'post_type'   => 'brag_book_cases',
				'meta_key'    => 'brag_book_gallery_procedure_case_id',
				'meta_value'  => $case_id,
				'numberposts' => 1,
				'fields'      => 'ids'
			] );

			// If not found, try legacy format (_case_api_id)
			if ( empty( $posts ) ) {
				$posts = get_posts( [
					'post_type'   => 'brag_book_cases',
					'meta_key'    => 'brag_book_gallery_procedure_case_id',
					'meta_value'  => $case_id,
					'numberposts' => 1,
					'fields'      => 'ids'
				] );
			}

			if ( ! empty( $posts ) ) {
				$ordered_post_ids[] = $posts[0];
			}
		}

		error_log( "BRAG book Gallery: Retrieved " . count( $ordered_post_ids ) . " ordered post IDs for procedure {$procedure_id}" );

		return $ordered_post_ids;
	}

	/**
	 * Check if we should continue processing based on time and memory limits
	 *
	 * @return bool True if we should continue, false if we should stop
	 * @since 3.3.3
	 */
	private function should_continue_processing(): bool {
		// Check execution time
		$elapsed_time = time() - $this->sync_start_time;
		if ( $elapsed_time >= $this->max_execution_time ) {
			error_log( "BRAG book Gallery Sync: Execution time limit reached ({$elapsed_time}s >= {$this->max_execution_time}s)" );

			return false;
		}

		// Check memory usage
		$memory_usage = memory_get_usage( true );
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory_limit > 0 ) {
			$memory_percent = ( $memory_usage / $memory_limit ) * 100;

			if ( $memory_percent >= $this->max_memory_percent ) {
				error_log( "BRAG book Gallery Sync: Memory limit reached ({$memory_percent}% >= {$this->max_memory_percent}%)" );

				return false;
			}
		}

		// Check for stop flag
		if ( get_option( 'brag_book_gallery_sync_stop_flag', false ) ) {
			error_log( 'BRAG book Gallery Sync: Stop flag detected' );

			return false;
		}

		return true;
	}

	/**
	 * Save current sync state for resumption
	 *
	 * @param array $state State data to save
	 *
	 * @return void
	 * @since 3.3.3
	 */
	private function save_sync_state( array $state ): void {
		$state['session_id']   = $this->sync_session_id;
		$state['timestamp']    = time();
		$state['last_updated'] = current_time( 'mysql' );

		update_option( $this->sync_state_option, $state, false );
		error_log( 'BRAG book Gallery Sync: Saved sync state for resumption' );
	}

	/**
	 * Clear saved sync state
	 *
	 * @return void
	 * @since 3.3.3
	 */
	private function clear_sync_state(): void {
		delete_option( $this->sync_state_option );
		delete_option( 'brag_book_gallery_sync_stop_flag' );
		error_log( 'BRAG book Gallery Sync: Cleared sync state' );
	}

	/**
	 * Resume sync from saved state
	 *
	 * @param array $state Saved state to resume from
	 *
	 * @return array Sync results
	 * @since 3.3.3
	 */
	private function resume_sync( array $state ): array {

		// Update session ID
		$this->sync_session_id = $state['session_id'];

		// Check if state is too old (older than 1 hour)
		if ( isset( $state['timestamp'] ) && ( time() - $state['timestamp'] ) > HOUR_IN_SECONDS ) {
			$this->clear_sync_state();

			return $this->run_two_stage_sync( $state['include_cases'] ?? true );
		}

		// Continue from where we left off based on stage
		if ( ! isset( $state['stage'] ) ) {
			$this->clear_sync_state();

			return $this->run_two_stage_sync( $state['include_cases'] ?? true );
		}

		$this->clear_sync_state();

		return $this->run_two_stage_sync( $state['include_cases'] ?? true );
	}

	/**
	 * Clean up memory by clearing large arrays
	 *
	 * @param array &$array Array to clean up (passed by reference)
	 * @param int $max_size Maximum size before cleanup
	 * @param int $keep_size Number of items to keep
	 *
	 * @return void
	 * @since 3.3.3
	 */
	private function cleanup_array( array &$array, int $max_size = 1000, int $keep_size = 500 ): void {
		if ( count( $array ) > $max_size ) {
			$array = array_slice( $array, - $keep_size, null, true );
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}
			error_log( 'BRAG book Gallery Sync: Cleaned up array from ' . $max_size . ' to ' . $keep_size . ' items' );
		}
	}

	/**
	 * Check if doctors taxonomy is enabled
	 *
	 * Doctors taxonomy is only enabled when website property ID 111 is configured.
	 *
	 * @return bool True if doctors taxonomy should be enabled.
	 * @since 3.3.3
	 */
	private function is_doctors_taxonomy_enabled(): bool {
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( ! is_array( $website_property_ids ) ) {
			$website_property_ids = [ $website_property_ids ];
		}

		return in_array( 111, array_map( 'intval', $website_property_ids ), true );
	}

	/**
	 * Assign doctor taxonomy to a case post if enabled
	 *
	 * Creates or updates the doctor term based on case creator data,
	 * then assigns the term to the case post.
	 *
	 * @param int   $post_id   The case post ID.
	 * @param array $case_data The case data from API.
	 *
	 * @return void
	 * @since 3.3.3
	 */
	private function maybe_assign_doctor_taxonomy( int $post_id, array $case_data ): void {
		// Only process if doctors taxonomy is enabled
		if ( ! $this->is_doctors_taxonomy_enabled() ) {
			return;
		}

		// Check if taxonomy exists (it should be registered if enabled)
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_DOCTORS ) ) {
			error_log( 'BRAG book Gallery Sync: Doctors taxonomy is enabled but not registered' );

			return;
		}

		// Extract creator/doctor data from case
		$creator = $case_data['creator'] ?? null;

		if ( empty( $creator ) || ! is_array( $creator ) ) {
			error_log( "BRAG book Gallery Sync: No creator data found for post {$post_id}" );

			return;
		}

		// Get member ID - required field
		$member_id = $creator['id'] ?? null;

		if ( empty( $member_id ) ) {
			error_log( "BRAG book Gallery Sync: No member ID found in creator data for post {$post_id}" );

			return;
		}

		// Create or update the doctor term
		$doctor_term = $this->create_or_update_doctor_term( $creator );

		if ( ! $doctor_term ) {
			error_log( "BRAG book Gallery Sync: Failed to create/update doctor term for member {$member_id}" );

			return;
		}

		// Assign the doctor term to the case post
		$result = wp_set_object_terms( $post_id, [ $doctor_term->term_id ], Taxonomies::TAXONOMY_DOCTORS );

		if ( is_wp_error( $result ) ) {
			error_log( "BRAG book Gallery Sync: Failed to assign doctor term to post {$post_id}: " . $result->get_error_message() );
		} else {
			error_log( "BRAG book Gallery Sync: ✓ Assigned doctor '{$doctor_term->name}' (term ID: {$doctor_term->term_id}) to post {$post_id}" );
		}
	}

	/**
	 * Create or update a doctor taxonomy term
	 *
	 * Finds existing term by member ID or creates a new one.
	 * Updates term meta with doctor information from API.
	 *
	 * @param array $creator_data The creator data from API case response.
	 *
	 * @return \WP_Term|null The doctor term or null on failure.
	 * @since 3.3.3
	 */
	private function create_or_update_doctor_term( array $creator_data ): ?\WP_Term {
		$member_id  = absint( $creator_data['id'] ?? 0 );
		$first_name = sanitize_text_field( $creator_data['firstName'] ?? '' );
		$last_name  = sanitize_text_field( $creator_data['lastName'] ?? '' );
		$suffix     = sanitize_text_field( $creator_data['suffix'] ?? '' );
		$profile_url = isset( $creator_data['profileLink'] ) ? esc_url_raw( $creator_data['profileLink'] ) : '';

		if ( empty( $member_id ) ) {
			return null;
		}

		// Build the doctor display name
		$name_parts = array_filter( [ $first_name, $last_name ] );
		$doctor_name = implode( ' ', $name_parts );

		if ( ! empty( $suffix ) ) {
			$doctor_name .= ', ' . $suffix;
		}

		// Fallback if no name provided
		if ( empty( $doctor_name ) ) {
			$doctor_name = "Doctor {$member_id}";
		}

		// Check if term already exists by member ID
		$existing_terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_DOCTORS,
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'   => 'doctor_member_id',
					'value' => $member_id,
					'type'  => 'NUMERIC',
				],
			],
			'number'     => 1,
		] );

		if ( ! empty( $existing_terms ) && ! is_wp_error( $existing_terms ) ) {
			$term = $existing_terms[0];

			// Update existing term name if it has changed
			if ( $term->name !== $doctor_name ) {
				wp_update_term( $term->term_id, Taxonomies::TAXONOMY_DOCTORS, [
					'name' => $doctor_name,
					'slug' => sanitize_title( $doctor_name . '-' . $member_id ),
				] );
				error_log( "BRAG book Gallery Sync: Updated doctor term name to '{$doctor_name}' (term ID: {$term->term_id})" );
			}

			// Update term meta
			$this->update_doctor_term_meta( $term->term_id, $first_name, $last_name, $suffix, $member_id, $profile_url );

			$this->register_doctor_in_registry( $member_id, $term->term_id );

			return get_term( $term->term_id, Taxonomies::TAXONOMY_DOCTORS );
		}

		// Create new term
		$result = wp_insert_term(
			$doctor_name,
			Taxonomies::TAXONOMY_DOCTORS,
			[
				'slug'        => sanitize_title( $doctor_name . '-' . $member_id ),
				'description' => sprintf(
					/* translators: %s: member ID */
					__( 'Doctor profile for member ID %s', 'brag-book-gallery' ),
					$member_id
				),
			]
		);

		if ( is_wp_error( $result ) ) {
			// Check if term already exists with same slug
			if ( $result->get_error_code() === 'term_exists' ) {
				$term_id = $result->get_error_data( 'term_exists' );
				$term = get_term( $term_id, Taxonomies::TAXONOMY_DOCTORS );

				if ( $term && ! is_wp_error( $term ) ) {
					// Update meta for the existing term
					$this->update_doctor_term_meta( $term->term_id, $first_name, $last_name, $suffix, $member_id, $profile_url );
					error_log( "BRAG book Gallery Sync: Using existing doctor term '{$doctor_name}' (term ID: {$term->term_id})" );

					$this->register_doctor_in_registry( $member_id, $term->term_id );

					return $term;
				}
			}

			error_log( "BRAG book Gallery Sync: Failed to create doctor term '{$doctor_name}': " . $result->get_error_message() );

			return null;
		}

		$term_id = $result['term_id'];

		// Save term meta
		$this->update_doctor_term_meta( $term_id, $first_name, $last_name, $suffix, $member_id, $profile_url );

		error_log( "BRAG book Gallery Sync: ✓ Created new doctor term '{$doctor_name}' (term ID: {$term_id})" );

		$this->register_doctor_in_registry( $member_id, $term_id );

		return get_term( $term_id, Taxonomies::TAXONOMY_DOCTORS );
	}

	/**
	 * Register a doctor in the sync registry
	 *
	 * @since 4.3.3
	 * @param int $member_id API member ID.
	 * @param int $term_id   WordPress term ID.
	 * @return void
	 */
	private function register_doctor_in_registry( int $member_id, int $term_id ): void {
		if ( $this->database && $member_id > 0 && $term_id > 0 ) {
			$this->database->upsert_registry_item(
				'doctor',
				$member_id,
				$term_id,
				'term',
				$this->get_registry_api_token(),
				$this->get_registry_property_id(),
				$this->sync_session_id
			);
		}
	}

	/**
	 * Update doctor term meta fields
	 *
	 * @param int    $term_id     The term ID.
	 * @param string $first_name  Doctor's first name.
	 * @param string $last_name   Doctor's last name.
	 * @param string $suffix      Professional suffix.
	 * @param int    $member_id   Member ID from API.
	 * @param string $profile_url Profile URL.
	 *
	 * @return void
	 * @since 3.3.3
	 */
	private function update_doctor_term_meta( int $term_id, string $first_name, string $last_name, string $suffix, int $member_id, string $profile_url ): void {
		update_term_meta( $term_id, 'doctor_member_id', $member_id );
		update_term_meta( $term_id, 'doctor_first_name', $first_name );
		update_term_meta( $term_id, 'doctor_last_name', $last_name );
		update_term_meta( $term_id, 'doctor_suffix', $suffix );

		if ( ! empty( $profile_url ) ) {
			update_term_meta( $term_id, 'doctor_profile_url', $profile_url );
		}

		// Note: Profile photo is not synced from API - must be manually uploaded
	}
}
