<?php
/**
 * Sync AJAX Handler
 *
 * Handles AJAX requests for data sync
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      3.3.0
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Sync;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Sync AJAX Handler Class
 *
 * @since 3.3.0
 */
class Sync_Ajax_Handler {

	/**
	 * Initialize AJAX handlers
	 *
	 * @since 3.3.0
	 */
	public static function init(): void {
		// Register AJAX actions for Data_Sync compatibility
		add_action( 'wp_ajax_brag_book_optimized_sync_start', [ self::class, 'handle_sync_start' ] );
		add_action( 'wp_ajax_brag_book_optimized_sync_chunk', [
			self::class,
			'handle_sync_start'
		] ); // Use same handler as start
		add_action( 'wp_ajax_brag_book_optimized_sync_status', [ self::class, 'handle_sync_status' ] );
		add_action( 'wp_ajax_brag_book_optimized_sync_cancel', [ self::class, 'handle_sync_cancel' ] );
		add_action( 'wp_ajax_brag_book_optimized_sync_log', [ self::class, 'handle_get_log' ] );
		add_action( 'wp_ajax_brag_book_optimized_sync_clear_log', [ self::class, 'handle_clear_log' ] );

		// Stage-based sync endpoints
		add_action( 'wp_ajax_brag_book_sync_stage_1', [ self::class, 'handle_stage_1' ] );
		add_action( 'wp_ajax_brag_book_sync_stage_2', [ self::class, 'handle_stage_2' ] );
		add_action( 'wp_ajax_brag_book_sync_stage_3', [ self::class, 'handle_stage_3' ] );
		add_action( 'wp_ajax_brag_book_sync_check_files', [ self::class, 'handle_check_files' ] );
		add_action( 'wp_ajax_brag_book_sync_get_manifest_preview', [ self::class, 'handle_get_manifest_preview' ] );
		add_action( 'wp_ajax_brag_book_sync_get_progress', [ self::class, 'handle_get_progress' ] );
		add_action( 'wp_ajax_brag_book_sync_delete_file', [ self::class, 'handle_delete_file' ] );
		add_action( 'wp_ajax_brag_book_sync_clear_stage3_status', [ self::class, 'handle_clear_stage3_status' ] );

		// Legacy endpoints for backward compatibility
		add_action( 'wp_ajax_brag_book_sync_data', [ self::class, 'handle_sync_start' ] );
	}

	/**
	 * Handle sync start request
	 *
	 * @since 3.3.0
	 */
	public static function handle_sync_start(): void {
		error_log( 'Data_Sync: handle_sync_start called' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'Data_Sync: Insufficient permissions' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			error_log( 'Data_Sync: Invalid nonce' );
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get database instance for logging
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );

		// Create initial log entry
		$log_id = null;
		if ( $database ) {
			error_log( 'Data_Sync: Database service available, attempting to log sync operation' );
			$log_id = $database->log_sync_operation( 'full', 'started', 0, 0, '', 'manual' );
			if ( $log_id ) {
				error_log( 'Data_Sync: Sync log created with ID: ' . $log_id );
			} else {
				error_log( 'Data_Sync: Failed to create sync log entry' );
			}
		} else {
			error_log( 'Data_Sync: Database service not available!' );
		}

		try {
			error_log( 'Data_Sync: Creating Data_Sync instance' );
			// Initialize sync
			$sync = new Data_Sync();
			error_log( 'Data_Sync: Calling run_two_stage_sync' );

			// Run the full sync with cases
			$result = $sync->run_two_stage_sync( true );
			error_log( 'Data_Sync: run_two_stage_sync completed' );

			// Update log entry with success
			if ( $database && $log_id ) {
				$items_processed = 0;
				$items_failed = 0;

				// Try to extract counts from result
				if ( is_array( $result ) ) {
					$items_processed = ( $result['cases_created'] ?? 0 ) + ( $result['cases_updated'] ?? 0 );
					$items_failed = $result['cases_failed'] ?? 0;
				}

				$database->update_sync_log( $log_id, 'completed', $items_processed, $items_failed, '' );
			}

			// Update last sync time and status
			update_option( 'brag_book_gallery_last_sync_time', current_time( 'mysql' ) );
			update_option( 'brag_book_gallery_last_sync_status', 'success' );

			// Send success response
			wp_send_json_success( $result );

		} catch ( \Exception $e ) {
			error_log( 'Data_Sync start error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );

			// Update log entry with failure
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, $e->getMessage() );
			}

			// Update last sync status
			update_option( 'brag_book_gallery_last_sync_time', current_time( 'mysql' ) );
			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( $e->getMessage() );
		} catch ( \Error $e ) {
			error_log( 'Data_Sync fatal error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );

			// Update log entry with failure
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, 'Fatal error: ' . $e->getMessage() );
			}

			// Update last sync status
			update_option( 'brag_book_gallery_last_sync_time', current_time( 'mysql' ) );
			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( 'Fatal error: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle sync chunk processing
	 *
	 * @since 3.3.0
	 */
	public static function handle_sync_chunk(): void {
		// Data_Sync doesn't use chunking - it processes everything in one go
		// This method is kept for compatibility but redirects to handle_sync_start
		self::handle_sync_start();
	}

	/**
	 * Handle sync status request
	 *
	 * @since 3.3.0
	 */
	public static function handle_sync_status(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get current progress from transient
		$progress = get_transient( 'brag_book_gallery_sync_progress' );

		if ( ! $progress ) {
			wp_send_json_success( [
				'active'  => false,
				'message' => 'No active sync'
			] );
		}

		// Format progress for frontend
		$response = [
			'active'             => true,
			'stage'              => $progress['stage'] ?? 'unknown',
			'current_step'       => $progress['message'] ?? '',
			'overall_percentage' => $progress['progress'] ?? 0,
			'procedure_progress' => [
				'percentage' => $progress['item_progress'] ?? 0
			],
			'recent_cases'       => $progress['recent_cases'] ?? [],
			'errors'             => $progress['errors'] ?? [],
			'warnings'           => $progress['warnings'] ?? []
		];

		wp_send_json_success( $response );
	}

	/**
	 * Handle sync cancel request
	 *
	 * @since 3.3.0
	 */
	public static function handle_sync_cancel(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Set stop flag for Data_Sync
		update_option( 'brag_book_gallery_sync_stop_flag', true );

		// Clear progress transient
		delete_transient( 'brag_book_gallery_sync_progress' );

		wp_send_json_success( 'Sync stop requested' );
	}

	/**
	 * Handle AJAX request to get log contents
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_get_log(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// For Data_Sync, we'll return recent logs from the database
		global $wpdb;
		$lines     = isset( $_POST['lines'] ) ? (int) $_POST['lines'] : 50;
		$log_table = $wpdb->prefix . 'brag_book_sync_log';

		// Get recent log entries
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT %d",
				$lines
			)
		);

		// Format logs for display
		$log_contents = '';
		if ( $logs ) {
			foreach ( array_reverse( $logs ) as $log ) {
				$log_contents .= sprintf(
					"[%s] %s - %s: %s\n",
					$log->created_at,
					$log->operation,
					$log->status,
					$log->details ?? ''
				);
			}
		} else {
			$log_contents = 'No log entries found.';
		}

		wp_send_json_success( [
			'log'     => $log_contents,
			'log_url' => admin_url( 'admin.php?page=brag-book-gallery-sync' )
		] );
	}

	/**
	 * Handle AJAX request to clear log
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_clear_log(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Clear Data_Sync log table
		global $wpdb;
		$log_table = $wpdb->prefix . 'brag_book_sync_log';
		$wpdb->query( "TRUNCATE TABLE {$log_table}" );

		wp_send_json_success( 'Log cleared' );
	}

	/**
	 * Handle Stage 1 sync request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_stage_1(): void {
		error_log( 'AJAX: handle_stage_1 called' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'AJAX: Stage 1 - Insufficient permissions' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			error_log( 'AJAX: Stage 1 - Invalid nonce' );
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get database instance for logging
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );

		// Create initial log entry
		$log_id = null;
		if ( $database ) {
			$log_id = $database->log_sync_operation( 'stage_1', 'started', 0, 0, '', 'manual' );
		}

		try {
			error_log( 'AJAX: Creating Chunked_Data_Sync instance' );
			// Use new Chunked_Data_Sync
			$sync = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();

			error_log( 'AJAX: Calling execute_stage_1' );
			$result = $sync->execute_stage_1();

			error_log( 'AJAX: Stage 1 result: ' . wp_json_encode( $result ) );

			if ( $result['success'] ) {
				// Update log entry
				if ( $database && $log_id ) {
					$items_processed = ( $result['procedures_created'] ?? 0 ) + ( $result['procedures_updated'] ?? 0 );
					$database->update_sync_log( $log_id, 'completed', $items_processed, 0, '' );
				}

				// Update last sync time
				update_option( 'brag_book_gallery_last_sync_time', current_time( 'mysql' ) );
				update_option( 'brag_book_gallery_last_sync_status', 'success' );

				wp_send_json_success( $result );
			} else {
				// Update log entry
				if ( $database && $log_id ) {
					$database->update_sync_log( $log_id, 'failed', 0, 0, $result['message'] ?? 'Unknown error' );
				}

				update_option( 'brag_book_gallery_last_sync_status', 'error' );

				wp_send_json_error( $result );
			}

		} catch ( \Exception $e ) {
			error_log( 'AJAX: Stage 1 sync exception: ' . $e->getMessage() );
			error_log( 'AJAX: Stack trace: ' . $e->getTraceAsString() );

			// Update log entry
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, $e->getMessage() );
			}

			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( [
				'message' => $e->getMessage(),
				'stage'   => 1,
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			] );
		} catch ( \Error $e ) {
			error_log( 'AJAX: Stage 1 sync fatal error: ' . $e->getMessage() );
			error_log( 'AJAX: Stack trace: ' . $e->getTraceAsString() );

			// Update log entry
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, 'Fatal error: ' . $e->getMessage() );
			}

			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( [
				'message' => 'Fatal error: ' . $e->getMessage(),
				'stage'   => 1,
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			] );
		}
	}

	/**
	 * Handle Stage 2 sync request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_stage_2(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get database instance for logging
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );

		// Create initial log entry
		$log_id = null;
		if ( $database ) {
			$log_id = $database->log_sync_operation( 'stage_2', 'started', 0, 0, '', 'manual' );
		}

		try {
			// Use new Chunked_Data_Sync
			$sync   = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();
			$result = $sync->execute_stage_2();

			if ( $result['success'] ) {
				// Update log entry
				if ( $database && $log_id ) {
					$items_processed = ( $result['procedure_count'] ?? 0 );
					$database->update_sync_log( $log_id, 'completed', $items_processed, 0, '' );
				}

				// Update last sync time
				update_option( 'brag_book_gallery_last_sync_time', current_time( 'mysql' ) );
				update_option( 'brag_book_gallery_last_sync_status', 'success' );

				wp_send_json_success( $result );
			} else {
				// Update log entry
				if ( $database && $log_id ) {
					$database->update_sync_log( $log_id, 'failed', 0, 0, $result['message'] ?? 'Unknown error' );
				}

				update_option( 'brag_book_gallery_last_sync_status', 'error' );

				wp_send_json_error( $result );
			}

		} catch ( \Exception $e ) {
			error_log( 'Stage 2 sync error: ' . $e->getMessage() );

			// Update log entry
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, $e->getMessage() );
			}

			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( [
				'message' => $e->getMessage(),
				'stage'   => 2,
			] );
		}
	}

	/**
	 * Handle Stage 3 sync request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_stage_3(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get database instance for logging
		$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
		$database = $setup->get_service( 'database' );

		// Create initial log entry
		$log_id = null;
		if ( $database ) {
			$log_id = $database->log_sync_operation( 'stage_3', 'started', 0, 0, '', 'manual' );
		}

		try {
			error_log( 'AJAX: Starting Stage 3 handler' );

			// Use new Chunked_Data_Sync
			$sync = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();
			error_log( 'AJAX: Chunked_Data_Sync instance created for Stage 3' );

			$result = $sync->execute_stage_3();
			error_log( 'AJAX: Stage 3 execution completed' );

			if ( $result['success'] ) {
				// Update log entry
				if ( $database && $log_id ) {
					$items_processed = ( $result['created'] ?? 0 ) + ( $result['updated'] ?? 0 );
					$items_failed = $result['failed'] ?? 0;
					$database->update_sync_log( $log_id, 'completed', $items_processed, $items_failed, '' );
				}

				// Update last sync time
				update_option( 'brag_book_gallery_last_sync_time', current_time( 'mysql' ) );
				update_option( 'brag_book_gallery_last_sync_status', 'success' );

				wp_send_json_success( $result );
			} else {
				// Update log entry
				if ( $database && $log_id ) {
					$database->update_sync_log( $log_id, 'failed', 0, 0, $result['message'] ?? 'Unknown error' );
				}

				update_option( 'brag_book_gallery_last_sync_status', 'error' );

				wp_send_json_error( $result );
			}

		} catch ( \Exception $e ) {
			error_log( 'Stage 3 sync Exception: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );

			// Update log entry
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, $e->getMessage() );
			}

			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( [
				'message' => $e->getMessage(),
				'stage'   => 3,
			] );
		} catch ( \Error $e ) {
			error_log( 'Stage 3 sync Fatal Error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );

			// Update log entry
			if ( $database && $log_id ) {
				$database->update_sync_log( $log_id, 'failed', 0, 0, 'Fatal error: ' . $e->getMessage() );
			}

			update_option( 'brag_book_gallery_last_sync_status', 'error' );

			wp_send_json_error( [
				'message' => 'Fatal error: ' . $e->getMessage(),
				'stage'   => 3,
			] );
		}
	}

	/**
	 * Handle check files request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_check_files(): void {
		error_log( 'AJAX: handle_check_files called' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( 'AJAX: Check files - Insufficient permissions' );
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			error_log( 'AJAX: About to create Chunked_Data_Sync instance' );

			// Check if class exists
			if ( ! class_exists( '\BRAGBookGallery\Includes\Sync\Chunked_Data_Sync' ) ) {
				error_log( 'AJAX: Chunked_Data_Sync class does not exist!' );

				// Try to manually include the file
				$file_path = plugin_dir_path( dirname( __DIR__ ) ) . 'sync/class-chunked-data-sync.php';
				error_log( 'AJAX: Attempting to include file: ' . $file_path );

				if ( file_exists( $file_path ) ) {
					require_once $file_path;
					error_log( 'AJAX: File included manually' );
				} else {
					error_log( 'AJAX: File not found at: ' . $file_path );
					wp_send_json_error( 'Chunked_Data_Sync class file not found' );
				}
			}

			$sync = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();
			error_log( 'AJAX: Chunked_Data_Sync instance created' );

			$file_status = $sync->get_file_status();
			error_log( 'AJAX: File status retrieved' );

			wp_send_json_success( $file_status );

		} catch ( \Exception $e ) {
			error_log( 'Check files Exception: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );
			wp_send_json_error( $e->getMessage() );
		} catch ( \Error $e ) {
			error_log( 'Check files Fatal Error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );
			wp_send_json_error( 'Fatal error: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle get manifest preview request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_get_manifest_preview(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			$sync    = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();
			$preview = $sync->get_manifest_preview();
			wp_send_json_success( $preview );

		} catch ( \Exception $e ) {
			error_log( 'Get manifest preview error: ' . $e->getMessage() );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Handle get progress request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_get_progress(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			$sync     = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();
			$progress = $sync->get_stage_progress();

			if ( $progress === false ) {
				wp_send_json_success( [
					'active'  => false,
					'message' => 'No active sync'
				] );
			} else {
				wp_send_json_success( array_merge(
					[ 'active' => true ],
					$progress
				) );
			}

		} catch ( \Exception $e ) {
			error_log( 'Get progress error: ' . $e->getMessage() );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Handle delete file request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_delete_file(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get the file type to delete
		$file = isset( $_POST['file'] ) ? sanitize_text_field( $_POST['file'] ) : '';

		if ( empty( $file ) ) {
			wp_send_json_error( 'No file specified' );
		}

		try {
			$sync       = new \BRAGBookGallery\Includes\Sync\Chunked_Data_Sync();
			$upload_dir = wp_upload_dir();
			// Use the correct sync directory path
			$sync_dir = $upload_dir['basedir'] . '/brag-book-gallery-sync/';

			// Get today's date string for file naming
			$date_string = current_time( 'Y-m-d' );

			$deleted_files = [];
			$errors        = [];

			switch ( $file ) {
				case 'sync_data':
					// Delete sync data JSON file (with date suffix)
					$sync_data_file = $sync_dir . 'sync-data-' . $date_string . '.json';
					if ( file_exists( $sync_data_file ) ) {
						if ( @unlink( $sync_data_file ) ) {
							$deleted_files[] = 'sync-data-' . $date_string . '.json';
						} else {
							$errors[] = 'Failed to delete sync-data-' . $date_string . '.json';
						}
					} else {
						// Try to delete any sync-data files regardless of date
						$sync_data_files = glob( $sync_dir . 'sync-data-*.json' );
						if ( ! empty( $sync_data_files ) ) {
							foreach ( $sync_data_files as $sf ) {
								if ( @unlink( $sf ) ) {
									$deleted_files[] = basename( $sf );
								} else {
									$errors[] = 'Failed to delete ' . basename( $sf );
								}
							}
						}
					}
					break;

				case 'manifest':
					// Delete manifest JSON file (with date suffix)
					$manifest_file = $sync_dir . 'manifest-' . $date_string . '.json';
					if ( file_exists( $manifest_file ) ) {
						if ( @unlink( $manifest_file ) ) {
							$deleted_files[] = 'manifest-' . $date_string . '.json';
						} else {
							$errors[] = 'Failed to delete manifest-' . $date_string . '.json';
						}
					} else {
						// Try to delete any manifest files regardless of date
						$manifest_files = glob( $sync_dir . 'manifest-*.json' );
						if ( ! empty( $manifest_files ) ) {
							foreach ( $manifest_files as $mf ) {
								if ( @unlink( $mf ) ) {
									$deleted_files[] = basename( $mf );
								} else {
									$errors[] = 'Failed to delete ' . basename( $mf );
								}
							}
						}
					}
					break;

				case 'all':
					// Delete all sync-related JSON files
					if ( is_dir( $sync_dir ) ) {
						// Delete all sync-data files
						$sync_data_files = glob( $sync_dir . 'sync-data-*.json' );
						if ( ! empty( $sync_data_files ) ) {
							foreach ( $sync_data_files as $sf ) {
								if ( @unlink( $sf ) ) {
									$deleted_files[] = basename( $sf );
								} else {
									$errors[] = 'Failed to delete ' . basename( $sf );
								}
							}
						}

						// Delete all manifest files
						$manifest_files = glob( $sync_dir . 'manifest-*.json' );
						if ( ! empty( $manifest_files ) ) {
							foreach ( $manifest_files as $mf ) {
								if ( @unlink( $mf ) ) {
									$deleted_files[] = basename( $mf );
								} else {
									$errors[] = 'Failed to delete ' . basename( $mf );
								}
							}
						}

						// Also check for cases JSON files
						$case_files = glob( $sync_dir . 'cases_*.json' );
						if ( ! empty( $case_files ) ) {
							foreach ( $case_files as $case_file ) {
								if ( @unlink( $case_file ) ) {
									$deleted_files[] = basename( $case_file );
								} else {
									$errors[] = 'Failed to delete ' . basename( $case_file );
								}
							}
						}
					}
					break;

				default:
					wp_send_json_error( 'Invalid file type' );

					return;
			}

			// Clear related transients
			delete_transient( 'brag_book_gallery_sync_progress' );
			delete_transient( 'brag_book_gallery_manifest_preview' );

			// Prepare response
			if ( ! empty( $errors ) ) {
				wp_send_json_error( [
					'message' => 'Some files could not be deleted',
					'deleted' => $deleted_files,
					'errors'  => $errors
				] );
			} else if ( ! empty( $deleted_files ) ) {
				wp_send_json_success( [
					'message' => 'Files deleted successfully',
					'deleted' => $deleted_files
				] );
			} else {
				wp_send_json_success( [
					'message' => 'No files to delete',
					'deleted' => []
				] );
			}

		} catch ( \Exception $e ) {
			error_log( 'Delete file error: ' . $e->getMessage() );
			wp_send_json_error( 'Error deleting files: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle clear Stage 3 status request
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function handle_clear_stage3_status(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Verify nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_sync', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		try {
			// Delete the Stage 3 status option
			$deleted = delete_option( 'brag_book_stage3_last_run' );

			if ( $deleted ) {
				wp_send_json_success( [
					'message' => 'Stage 3 status cleared successfully'
				] );
			} else {
				wp_send_json_success( [
					'message' => 'No Stage 3 status to clear'
				] );
			}

		} catch ( \Exception $e ) {
			error_log( 'Clear Stage 3 status error: ' . $e->getMessage() );
			wp_send_json_error( 'Error clearing Stage 3 status: ' . $e->getMessage() );
		}
	}
}
