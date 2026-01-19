<?php
/**
 * Sync API Class
 *
 * Handles communication with the BragBook Sync API for registering syncs
 * and reporting their status. Implements the two-step sync process:
 * 1. Register - Creates a SyncJob with PENDING status
 * 2. Report - Updates the job status after sync completes
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      4.0.2
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Core\Trait_Api;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Sync API Class
 *
 * Manages sync registration and status reporting with the BragBook API.
 *
 * @since 4.0.2
 */
class Sync_Api {
	use Trait_Api;

	/**
	 * Sync status constants
	 *
	 * @since 4.0.2
	 */
	public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
	public const STATUS_SUCCESS     = 'SUCCESS';
	public const STATUS_FAILED      = 'FAILED';
	public const STATUS_PARTIAL     = 'PARTIAL';
	public const STATUS_TIMEOUT     = 'TIMEOUT';

	/**
	 * Sync type constants
	 *
	 * @since 4.0.2
	 */
	public const SYNC_TYPE_AUTO   = 'AUTO';
	public const SYNC_TYPE_MANUAL = 'MANUAL';

	/**
	 * Option name for storing current sync job data
	 *
	 * @since 4.0.2
	 * @var string
	 */
	private const JOB_OPTION_NAME = 'brag_book_gallery_current_sync_job';

	/**
	 * Option name for storing last sync report response
	 *
	 * @since 4.0.2
	 * @var string
	 */
	private const LAST_REPORT_OPTION_NAME = 'brag_book_gallery_last_sync_report';

	/**
	 * API endpoint for sync registration
	 *
	 * @since 4.0.2
	 * @var string
	 */
	private const REGISTER_ENDPOINT = '/api/plugin/v2/sync/register';

	/**
	 * API endpoint for sync status reporting
	 *
	 * @since 4.0.2
	 * @var string
	 */
	private const REPORT_ENDPOINT = '/api/plugin/v2/sync/report';

	/**
	 * Register a sync with the BragBook API
	 *
	 * Creates a SyncSite (if new) and a SyncJob with PENDING status.
	 * Only one active (PENDING or IN_PROGRESS) job is allowed per site.
	 *
	 * @since 4.0.2
	 *
	 * @param string      $sync_type      Sync type: 'AUTO' or 'MANUAL'. Default 'MANUAL'.
	 * @param string|null $scheduled_time ISO 8601 datetime for AUTO syncs. Must be in future.
	 *
	 * @return array{success: bool, job_id?: int, sync_site_id?: int, status?: string, message: string, error?: string}|WP_Error
	 */
	public function register_sync( string $sync_type = self::SYNC_TYPE_MANUAL, ?string $scheduled_time = null ): array|WP_Error {
		error_log( 'BRAG Book Gallery Sync API: ========== REGISTER SYNC CALLED ==========' );
		error_log( 'BRAG Book Gallery Sync API: Sync type: ' . $sync_type );
		error_log( 'BRAG Book Gallery Sync API: WordPress home_url(): ' . home_url() );
		error_log( 'BRAG Book Gallery Sync API: WordPress site_url(): ' . site_url() );

		// Get website property ID for query parameter
		$website_property_id = $this->get_website_property_id();
		if ( empty( $website_property_id ) ) {
			error_log( 'BRAG Book Gallery Sync API: No website property ID configured' );
			return new WP_Error(
				'missing_property_id',
				__( 'Website property ID is not configured.', 'brag-book-gallery' )
			);
		}

		// Build endpoint with query parameter
		$endpoint = self::REGISTER_ENDPOINT . '?websitePropertyId=' . urlencode( (string) $website_property_id );

		// Build request body
		$body = [
			'url'      => home_url(),
			'syncType' => $sync_type,
		];

		// Add scheduled time for AUTO syncs
		if ( self::SYNC_TYPE_AUTO === $sync_type && ! empty( $scheduled_time ) ) {
			$body['scheduledTime'] = $scheduled_time;
		}

		// Make the API request
		$response = $this->make_sync_api_request( $endpoint, $body );

		if ( is_wp_error( $response ) ) {
			error_log( 'BRAG Book Gallery Sync API: Registration failed - ' . $response->get_error_message() );
			return $response;
		}

		// Check response structure
		if ( ! isset( $response['data'] ) ) {
			error_log( 'BRAG Book Gallery Sync API: Invalid response structure' );
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from sync registration API.', 'brag-book-gallery' )
			);
		}

		$data = $response['data'];

		// Extract sync job data
		$sync_job = $data['syncJob'] ?? null;
		if ( ! $sync_job ) {
			error_log( 'BRAG Book Gallery Sync API: No syncJob in response' );
			return new WP_Error(
				'missing_sync_job',
				__( 'No sync job returned from registration.', 'brag-book-gallery' )
			);
		}

		// Store the job data
		$job_data = [
			'job_id'        => $sync_job['id'] ?? null,
			'sync_site_id'  => $sync_job['syncSiteId'] ?? null,
			'status'        => $sync_job['status'] ?? 'PENDING',
			'scheduled_at'  => $sync_job['scheduledAt'] ?? null,
			'registered_at' => current_time( 'c' ),
			'started_at'    => null,
			'completed_at'  => null,
			'sync_type'     => $sync_type,
		];

		$this->store_current_job( $job_data );

		error_log( 'BRAG Book Gallery Sync API: Registration successful - Job ID: ' . $job_data['job_id'] );

		return [
			'success'      => true,
			'job_id'       => $job_data['job_id'],
			'sync_site_id' => $job_data['sync_site_id'],
			'status'       => $job_data['status'],
			'message'      => $data['message'] ?? 'Sync registered successfully',
		];
	}

	/**
	 * Report sync status to the BragBook API
	 *
	 * Updates the job status after WordPress completes (or fails) the sync.
	 *
	 * @since 4.0.2
	 *
	 * @param string $status       Job status: IN_PROGRESS, SUCCESS, FAILED, PARTIAL, TIMEOUT.
	 * @param int    $cases_synced Number of cases synced in this job.
	 * @param string $message      Human-readable status message.
	 * @param string $error_log    Error details for debugging.
	 *
	 * @return array{success: bool, job?: array, next_sync?: array, message: string}|WP_Error
	 */
	public function report_sync(
		string $status,
		int $cases_synced = 0,
		string $message = '',
		string $error_log = ''
	): array|WP_Error {
		error_log( 'BRAG Book Gallery Sync API: Reporting sync status - ' . $status );

		// Validate status
		$valid_statuses = [
			self::STATUS_IN_PROGRESS,
			self::STATUS_SUCCESS,
			self::STATUS_FAILED,
			self::STATUS_PARTIAL,
			self::STATUS_TIMEOUT,
		];

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new WP_Error(
				'invalid_status',
				sprintf(
					/* translators: %s: provided status */
					__( 'Invalid sync status: %s', 'brag-book-gallery' ),
					$status
				)
			);
		}

		// Get website property ID for query parameter
		$website_property_id = $this->get_website_property_id();
		if ( empty( $website_property_id ) ) {
			error_log( 'BRAG Book Gallery Sync API: No website property ID configured' );
			return new WP_Error(
				'missing_property_id',
				__( 'Website property ID is not configured.', 'brag-book-gallery' )
			);
		}

		// Build endpoint with query parameter
		$endpoint = self::REPORT_ENDPOINT . '?websitePropertyId=' . urlencode( (string) $website_property_id );

		// Build request body - use URL for job lookup (recommended approach)
		$body = [
			'url'    => home_url(),
			'status' => $status,
		];

		// Add optional fields
		if ( $cases_synced > 0 ) {
			$body['casesSynced'] = $cases_synced;
		}

		if ( ! empty( $message ) ) {
			$body['message'] = $message;
		}

		if ( ! empty( $error_log ) ) {
			$body['errorLog'] = $error_log;
		}

		// Make the API request
		$response = $this->make_sync_api_request( $endpoint, $body );

		if ( is_wp_error( $response ) ) {
			error_log( 'BRAG Book Gallery Sync API: Report failed - ' . $response->get_error_message() );
			return $response;
		}

		// Check response structure
		if ( ! isset( $response['data'] ) ) {
			error_log( 'BRAG Book Gallery Sync API: Invalid response structure' );
			return new WP_Error(
				'invalid_response',
				__( 'Invalid response from sync report API.', 'brag-book-gallery' )
			);
		}

		$data = $response['data'];

		// Update stored job status
		$current_job = $this->get_current_job();
		if ( $current_job ) {
			if ( self::STATUS_IN_PROGRESS === $status ) {
				$current_job['started_at'] = current_time( 'c' );
			}

			if ( in_array( $status, [ self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_PARTIAL, self::STATUS_TIMEOUT ], true ) ) {
				$current_job['completed_at'] = current_time( 'c' );
			}

			$current_job['status'] = $status;
			$this->store_current_job( $current_job );
		}

		// Store last report response for UI display
		$report_data = [
			'reported_at'          => current_time( 'c' ),
			'status'               => $status,
			'cases_synced'         => $cases_synced,
			'job'                  => $data['job'] ?? null,
			'next_sync'            => $data['nextSync'] ?? null,
			'manual_sync_required' => $data['manualSyncRequired'] ?? false,
		];
		update_option( self::LAST_REPORT_OPTION_NAME, $report_data );

		error_log( 'BRAG Book Gallery Sync API: Report successful - Status: ' . $status );

		// Clear job data on completion statuses
		if ( in_array( $status, [ self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_PARTIAL, self::STATUS_TIMEOUT ], true ) ) {
			$this->clear_current_job();
		}

		return [
			'success'   => true,
			'job'       => $data['job'] ?? null,
			'next_sync' => $data['nextSync'] ?? null,
			'message'   => 'Sync status reported successfully',
		];
	}

	/**
	 * Make an API request to the sync endpoints
	 *
	 * Uses Bearer token authentication in header (not body credentials).
	 *
	 * @since 4.0.2
	 *
	 * @param string $endpoint API endpoint with query parameters.
	 * @param array  $body     Request body.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	private function make_sync_api_request( string $endpoint, array $body ): array|WP_Error {
		// Get API token for Bearer authentication
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		error_log( 'BRAG Book Gallery Sync API: Raw api_tokens option: ' . wp_json_encode( $api_tokens ) );

		if ( ! is_array( $api_tokens ) ) {
			$api_tokens = [ $api_tokens ];
		}
		$api_tokens = array_filter( $api_tokens );

		if ( empty( $api_tokens ) ) {
			error_log( 'BRAG Book Gallery Sync API: ERROR - No API tokens configured' );
			return new WP_Error(
				'missing_api_token',
				__( 'API token is not configured.', 'brag-book-gallery' )
			);
		}

		// Use the first token for Bearer auth
		$api_token = reset( $api_tokens );
		error_log( 'BRAG Book Gallery Sync API: Using token: ' . substr( $api_token, 0, 8 ) . '...' );

		$url = $this->get_api_base_url() . $endpoint;
		error_log( 'BRAG Book Gallery Sync API: Base URL: ' . $this->get_api_base_url() );

		// Get timeout from settings
		$timeout = intval( get_option( 'brag_book_gallery_api_timeout', 30 ) );

		$args = [
			'method'      => 'POST',
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $api_token,
				'User-Agent'    => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '4.0.0' ),
			],
			'body'        => wp_json_encode( $body ),
			'sslverify'   => true,
		];

		error_log( 'BRAG Book Gallery Sync API: ========== SYNC API REQUEST ==========' );
		error_log( 'BRAG Book Gallery Sync API: Full URL: ' . $url );
		error_log( 'BRAG Book Gallery Sync API: Request body: ' . wp_json_encode( $body ) );
		error_log( 'BRAG Book Gallery Sync API: Authorization: Bearer ' . substr( $api_token, 0, 8 ) . '...' );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'BRAG Book Gallery Sync API: WP_Error: ' . $response->get_error_message() );
			$this->log_api_error( $endpoint, $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log( 'BRAG Book Gallery Sync API: ========== SYNC API RESPONSE ==========' );
		error_log( 'BRAG Book Gallery Sync API: Response code: ' . $response_code );
		error_log( 'BRAG Book Gallery Sync API: Response body: ' . $response_body );

		// Handle error responses
		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_data = json_decode( $response_body, true );

			// Try to extract error message from various possible response formats
			$error_message = null;
			if ( isset( $error_data['error'] ) ) {
				$error_message = $error_data['error'];
			} elseif ( isset( $error_data['message'] ) ) {
				$error_message = $error_data['message'];
			} elseif ( isset( $error_data['errors'] ) && is_array( $error_data['errors'] ) ) {
				$error_message = implode( ', ', $error_data['errors'] );
			}

			if ( empty( $error_message ) ) {
				$error_message = sprintf(
					/* translators: %d: HTTP status code */
					__( 'API request failed with status %d', 'brag-book-gallery' ),
					$response_code
				);
			}

			// Handle array of errors
			if ( is_array( $error_message ) ) {
				$error_message = implode( ', ', $error_message );
			}

			error_log( 'BRAG Book Gallery Sync API: Error message: ' . $error_message );
			$this->log_api_error( $endpoint, $error_message, [ 'status' => $response_code, 'body' => $response_body ] );

			return new WP_Error(
				'api_error',
				$error_message,
				[ 'status' => $response_code, 'response' => $error_data ]
			);
		}

		// Decode JSON response
		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'json_error',
				__( 'Invalid JSON response from API.', 'brag-book-gallery' )
			);
		}

		return [
			'code' => $response_code,
			'data' => $data,
		];
	}

	/**
	 * Get the website property ID
	 *
	 * @since 4.0.2
	 *
	 * @return int|string|null Website property ID or null if not configured.
	 */
	private function get_website_property_id(): int|string|null {
		$property_ids = get_option( 'brag_book_gallery_website_property_id', [] );
		error_log( 'BRAG Book Gallery Sync API: Raw property_ids option: ' . wp_json_encode( $property_ids ) );

		if ( ! is_array( $property_ids ) ) {
			error_log( 'BRAG Book Gallery Sync API: property_ids is not array, returning: ' . ( $property_ids ?: 'null' ) );
			return $property_ids ?: null;
		}

		$property_ids = array_filter( $property_ids );
		$result       = ! empty( $property_ids ) ? reset( $property_ids ) : null;
		error_log( 'BRAG Book Gallery Sync API: Resolved property ID: ' . ( $result ?? 'null' ) );

		return $result;
	}

	/**
	 * Store current sync job data
	 *
	 * @since 4.0.2
	 *
	 * @param array $job_data Job data to store.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function store_current_job( array $job_data ): bool {
		return update_option( self::JOB_OPTION_NAME, $job_data );
	}

	/**
	 * Get current sync job data
	 *
	 * @since 4.0.2
	 *
	 * @return array|null Job data or null if no active job.
	 */
	public function get_current_job(): ?array {
		$job_data = get_option( self::JOB_OPTION_NAME, null );
		return is_array( $job_data ) ? $job_data : null;
	}

	/**
	 * Clear current sync job data
	 *
	 * @since 4.0.2
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_current_job(): bool {
		return delete_option( self::JOB_OPTION_NAME );
	}

	/**
	 * Get last sync report data
	 *
	 * @since 4.0.2
	 *
	 * @return array|null Last report data or null if none.
	 */
	public function get_last_report(): ?array {
		$report_data = get_option( self::LAST_REPORT_OPTION_NAME, null );
		return is_array( $report_data ) ? $report_data : null;
	}

	/**
	 * Check if there's an active sync job
	 *
	 * @since 4.0.2
	 *
	 * @return bool True if there's an active job.
	 */
	public function has_active_job(): bool {
		$job = $this->get_current_job();

		if ( ! $job ) {
			return false;
		}

		// Check if job is still active (PENDING or IN_PROGRESS)
		$active_statuses = [ 'PENDING', self::STATUS_IN_PROGRESS ];
		return in_array( $job['status'] ?? '', $active_statuses, true );
	}
}
