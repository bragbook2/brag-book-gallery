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

use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Core\Trait_Api;
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
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		error_log( 'BRAG book Gallery Sync: Data_Sync constructor started' );

		try {
			global $wpdb;
			error_log( 'BRAG book Gallery Sync: Got $wpdb global' );

			$this->log_table = $wpdb->prefix . 'brag_book_sync_log';
			error_log( 'BRAG book Gallery Sync: Set log_table property' );

			$this->sync_session_id = uniqid( 'sync_', true );
			error_log( 'BRAG book Gallery Sync: Set sync_session_id property' );

			error_log( 'BRAG book Gallery Sync: About to create HIPAA-compliant sync table' );
			$this->maybe_create_sync_table();
			error_log( 'BRAG book Gallery Sync: HIPAA-compliant sync table ready' );
			error_log( 'BRAG book Gallery Sync: Data_Sync constructor completed successfully' );
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
	 * Sync procedures from API (Stage 1 only - for backwards compatibility)
	 *
	 * @since 3.0.0
	 * @return array Sync results
	 * @throws Exception If sync fails
	 */
	public function sync_procedures(): array {
		return $this->run_two_stage_sync( false ); // Only stage 1
	}

	/**
	 * Run complete two-stage sync: procedures + cases
	 *
	 * @since 3.0.0
	 * @param bool $include_cases Whether to include case synchronization (stage 2)
	 * @return array Sync results
	 * @throws Exception If sync fails
	 */
	public function run_two_stage_sync( bool $include_cases = true ): array {
		$this->log_sync_start();

		// Set longer execution time and higher memory limit for sync operations
		$original_time_limit = ini_get( 'max_execution_time' );
		$original_memory_limit = ini_get( 'memory_limit' );

		// Increase limits if possible
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // No time limit
		}
		if ( function_exists( 'ini_set' ) ) {
			ini_set( 'memory_limit', '512M' );
		}

		try {
			error_log( 'BRAG book Gallery Sync: ===== STARTING TWO-STAGE SYNC =====' );
			error_log( 'BRAG book Gallery Sync: Initializing sync session: ' . $this->sync_session_id );
			error_log( 'BRAG book Gallery Sync: Include cases: ' . ( $include_cases ? 'YES' : 'NO' ) );
			error_log( 'BRAG book Gallery Sync: Original time limit: ' . $original_time_limit );
			error_log( 'BRAG book Gallery Sync: Original memory limit: ' . $original_memory_limit );
			error_log( 'BRAG book Gallery Sync: Current time limit: ' . ini_get( 'max_execution_time' ) );
			error_log( 'BRAG book Gallery Sync: Current memory limit: ' . ini_get( 'memory_limit' ) );

			// STAGE 1: Sync procedures from sidebar API
			error_log( 'BRAG book Gallery Sync: ===== STAGE 1: PROCEDURE SYNC =====' );
			$stage1_result = $this->sync_procedures_stage1();

			if ( ! $stage1_result['success'] ) {
				error_log( 'BRAG book Gallery Sync: Stage 1 failed, aborting sync' );
				$this->log_sync_error( 'Stage 1 (procedures) failed: ' . implode( ', ', $stage1_result['errors'] ) );
				return $stage1_result;
			}

			$total_result = $stage1_result;
			$total_result['warnings'] = []; // Initialize warnings array

			// STAGE 2: Sync cases (if requested)
			if ( $include_cases ) {
				error_log( 'BRAG book Gallery Sync: ===== STAGE 2: CASE SYNC =====' );
				$stage2_result = $this->sync_cases_stage2( $stage1_result['sidebar_data'] );

				// Merge results
				$total_result['cases_created'] = $stage2_result['created'];
				$total_result['cases_updated'] = $stage2_result['updated'];
				$total_result['cases_errors'] = $stage2_result['errors'];
				$total_result['cases_warnings'] = $stage2_result['warnings'] ?? [];
				$total_result['total_cases_processed'] = $stage2_result['total_processed'];
				$total_result['errors'] = array_merge( $total_result['errors'], $stage2_result['errors'] );
				$total_result['warnings'] = array_merge( $total_result['warnings'] ?? [], $stage2_result['warnings'] ?? [] );

				// Update success status - only fail on actual errors, not warnings
				if ( ! empty( $stage2_result['errors'] ) ) {
					$total_result['success'] = false;
				}
			}

			$total_errors = count( $total_result['errors'] );
			$success = empty( $total_result['errors'] );

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

			error_log( 'BRAG book Gallery Sync: ===== SYNC COMPLETE =====' );

			$this->log_sync_complete( $total_result );

			return $total_result;

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Sync failed with exception: ' . $e->getMessage() );
			error_log( 'BRAG book Gallery Sync: Exception stack trace: ' . $e->getTraceAsString() );
			$this->log_sync_error( $e->getMessage() );
			throw $e;
		} finally {
			// Restore original limits
			if ( function_exists( 'set_time_limit' ) && $original_time_limit !== false ) {
				set_time_limit( (int) $original_time_limit );
			}
			if ( function_exists( 'ini_set' ) && $original_memory_limit !== false ) {
				ini_set( 'memory_limit', $original_memory_limit );
			}
			error_log( 'BRAG book Gallery Sync: Restored original time/memory limits' );
		}
	}

	/**
	 * Stage 1: Sync procedures from sidebar API
	 *
	 * @since 3.0.0
	 * @return array Sync results with sidebar data
	 * @throws Exception If sync fails
	 */
	private function sync_procedures_stage1(): array {
		try {
			// Ensure taxonomy is available before sync
			$this->ensure_taxonomy_registered();

			error_log( 'BRAG book Gallery Sync: Step 1 - Connecting to BRAGBook API...' );
			$api_data = $this->fetch_api_data();
			error_log( 'BRAG book Gallery Sync: ✓ Successfully connected to API' );

			error_log( 'BRAG book Gallery Sync: Step 2 - Analyzing API response...' );
			error_log( 'BRAG book Gallery Sync: API response structure: ' . wp_json_encode( array_keys( $api_data ) ) );

			if ( empty( $api_data['data'] ) ) {
				error_log( 'BRAG book Gallery Sync: ✗ No procedure data found in API response' );
				throw new Exception( __( 'No data received from API', 'brag-book-gallery' ) );
			}

			$total_categories = count( $api_data['data'] );
			error_log( 'BRAG book Gallery Sync: ✓ Found ' . $total_categories . ' procedure categories to process' );

			$created_procedures = [];
			$updated_procedures = [];
			$errors = [];
			$category_count = 0;

			error_log( 'BRAG book Gallery Sync: Step 3 - Processing procedure categories...' );

			foreach ( $api_data['data'] as $category ) {
				$category_count++;
				$category_name = $category['name'] ?? 'Unknown';

				try {
					error_log( "BRAG book Gallery Sync: Processing category {$category_count}/{$total_categories}: '{$category_name}'" );

					// Count child procedures
					$child_count = isset( $category['procedures'] ) ? count( $category['procedures'] ) : 0;
					error_log( "BRAG book Gallery Sync: Category '{$category_name}' contains {$child_count} procedures" );

					$result = $this->process_category( $category );
					$created_procedures = array_merge( $created_procedures, $result['created'] );
					$updated_procedures = array_merge( $updated_procedures, $result['updated'] );

					$created_in_category = count( $result['created'] );
					$updated_in_category = count( $result['updated'] );
					error_log( "BRAG book Gallery Sync: ✓ Category '{$category_name}' processed - Created: {$created_in_category}, Updated: {$updated_in_category}" );

				} catch ( Exception $e ) {
					$error_msg = sprintf(
						__( 'Error processing category "%s": %s', 'brag-book-gallery' ),
						$category_name,
						$e->getMessage()
					);
					$errors[] = $error_msg;
					error_log( "BRAG book Gallery Sync: ✗ Failed to process category '{$category_name}': " . $e->getMessage() );
				}
			}

			$total_created = count( $created_procedures );
			$total_updated = count( $updated_procedures );
			$total_errors = count( $errors );
			$success = empty( $errors );

			error_log( 'BRAG book Gallery Sync: Step 4 - Stage 1 completed' );
			error_log( "BRAG book Gallery Sync: Stage 1 Status: " . ( $success ? 'SUCCESS' : 'COMPLETED WITH ERRORS' ) );
			error_log( "BRAG book Gallery Sync: Categories processed: {$total_categories}" );
			error_log( "BRAG book Gallery Sync: Procedures created: {$total_created}" );
			error_log( "BRAG book Gallery Sync: Procedures updated: {$total_updated}" );
			error_log( "BRAG book Gallery Sync: Errors encountered: {$total_errors}" );

			return [
				'success' => $success,
				'created' => $total_created,
				'updated' => $total_updated,
				'errors'  => $errors,
				'details' => [
					'created_procedures' => $created_procedures,
					'updated_procedures' => $updated_procedures,
					'categories_processed' => $total_categories,
				],
				'sidebar_data' => $api_data, // Include for stage 2
			];

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Stage 1 failed with exception: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Fetch data from BRAGBook API
	 *
	 * @since 3.0.0
	 * @return array API response data
	 * @throws Exception If API request fails
	 */
	private function fetch_api_data(): array {
		$endpoint = '/api/plugin/combine/sidebar';

		error_log( 'BRAG book Gallery Sync: Preparing API request to: ' . $endpoint );

		// Get API tokens for authentication
		error_log( 'BRAG book Gallery Sync: Retrieving API credentials...' );
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( 'BRAG book Gallery Sync: ✗ No API tokens configured in settings' );
			throw new Exception( __( 'No API tokens configured. Please configure API settings first.', 'brag-book-gallery' ) );
		}

		// Filter out empty tokens
		$valid_tokens = array_filter( $api_tokens, function( $token ) {
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
		$full_url = $api_base_url . $endpoint;

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
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body' => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );

		$request_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code = $response->get_error_code();
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

		$data = json_decode( $response_body, true );

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

		return $data;
	}

	/**
	 * Process a category and its procedures
	 *
	 * @since 3.0.0
	 * @param array $category Category data from API
	 * @return array Processing results
	 * @throws Exception If processing fails
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
				} catch ( Exception $e ) {
					error_log( sprintf(
						'Error processing procedure "%s": %s',
						$procedure['name'] ?? 'Unknown',
						$e->getMessage()
					) );
				}
			}
		}

		return [
			'created' => $created,
			'updated' => $updated,
		];
	}

	/**
	 * Create or update a procedure term
	 *
	 * @since 3.0.0
	 * @param array    $data Procedure data from API
	 * @param int|null $parent_id Parent term ID (null for parent categories)
	 * @return array Operation result
	 * @throws Exception If operation fails
	 */
	private function create_or_update_procedure( array $data, ?int $parent_id = null ): array {
		$slug = $data['slugName'] ?? sanitize_title( $data['name'] );
		$name = $data['name'];
		$original_description = $data['description'] ?? '';

		// Set term description to shortcode for procedure view
		$term_description = '[brag_book_gallery view="procedure"]';

		// Check if term already exists
		$existing_term = get_term_by( 'slug', $slug, Taxonomies::TAXONOMY_PROCEDURES );

		if ( $existing_term ) {
			// Update existing term
			$term_id = $existing_term->term_id;
			$updated = wp_update_term( $term_id, Taxonomies::TAXONOMY_PROCEDURES, [
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
			$inserted = wp_insert_term( $name, Taxonomies::TAXONOMY_PROCEDURES, [
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

		return [
			'term_id'     => $term_id,
			'name'        => $name,
			'slug'        => $slug,
			'created'     => $created,
			'parent_id'   => $parent_id,
			'api_data'    => $data,
		];
	}

	/**
	 * Update procedure term meta
	 *
	 * @since 3.0.0
	 * @param int   $term_id Term ID
	 * @param array $data API data
	 * @return void
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
			'api_id' => $data['ids'][0] ?? null,
			'slug' => $data['slugName'] ?? '',
			'total_cases' => $data['totalCase'] ?? 0,
			'sync_date' => current_time( 'mysql' ),
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
	 * @since 3.0.0
	 * @param int      $term_id Term ID
	 * @param array    $data API data
	 * @param bool     $created Whether term was created or updated
	 * @param int|null $parent_id Parent term ID
	 * @return void
	 */
	private function log_procedure_operation( int $term_id, array $data, bool $created, ?int $parent_id ): void {
		global $wpdb;

		// HIPAA Compliance: Only log operational data for procedures (no patient data)
		// Procedure names are safe as they don't contain PHI (e.g., "Breast Augmentation")
		$operation_type = $created ? 'create' : 'update';
		$item_type = $parent_id ? 'child_procedure' : 'parent_category';

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
	 * @since 3.0.0
	 * @return void
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
	 * @since 3.0.0
	 * @param array $result Sync results
	 * @return void
	 */
	private function log_sync_complete( array $result ): void {
		global $wpdb;

		// Get the completed sync activity log that was saved during cleanup
		$activity_log = get_option( 'brag_book_gallery_completed_sync_log', [] );

		// Enhance result with activity log for the report
		$enhanced_result = $result;
		if ( ! empty( $activity_log ) ) {
			$enhanced_result['activity_log'] = $activity_log;
		}

		$wpdb->insert(
			$this->log_table,
			[
				'sync_session_id' => $this->sync_session_id,
				'sync_type'       => 'procedure_sync',
				'operation'       => 'complete',
				'item_type'       => 'sync_session',
				'items_created'   => $result['created'],
				'items_updated'   => $result['updated'],
				'status'          => $result['success'] ? 'success' : 'error',
				'details'         => wp_json_encode( $enhanced_result ),
				'created_at'      => current_time( 'mysql' ),
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
			]
		);

		// Clean up the temporary activity log after saving to history
		delete_option( 'brag_book_gallery_completed_sync_log' );
	}

	/**
	 * Log sync error
	 *
	 * @since 3.0.0
	 * @param string $error_message Error message
	 * @return void
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
	 * @since 3.0.0
	 * @return void
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
	 * @since 3.0.0
	 * @return void
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
	 * @since 3.0.0
	 * @return void
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
	 * @since 3.0.0
	 * @param array $sidebar_data Sidebar API data from stage 1
	 * @return array Sync results
	 */
	private function sync_cases_stage2( array $sidebar_data ): array {
		try {
			error_log( 'BRAG book Gallery Sync: Starting case synchronization...' );

			// Clear any existing stop flag
			delete_option( 'brag_book_gallery_sync_stop_flag' );

			// Memory optimization: Aggressively increase memory limit for sync operations
			$original_memory_limit = ini_get( 'memory_limit' );
			error_log( "BRAG book Gallery Sync: Original memory limit: {$original_memory_limit}" );

			// Try multiple approaches to increase memory
			$attempts = [
				'2048M', // 2GB
				'1536M', // 1.5GB
				'1024M', // 1GB
				'768M',  // 768MB
				'512M'   // 512MB
			];

			$memory_increased = false;
			foreach ( $attempts as $new_limit ) {
				if ( ini_set( 'memory_limit', $new_limit ) !== false ) {
					$actual_limit = ini_get( 'memory_limit' );
					error_log( "BRAG book Gallery Sync: ✓ Successfully increased memory limit from {$original_memory_limit} to {$actual_limit}" );
					$memory_increased = true;
					break;
				}
			}

			if ( ! $memory_increased ) {
				error_log( "BRAG book Gallery Sync: ⚠ Could not increase memory limit from {$original_memory_limit}. Relying on memory monitoring during processing." );
			}

			$created_cases = 0;
			$updated_cases = 0;
			$errors = [];
			$warnings = []; // Track warnings that don't indicate failure
			$total_processed = 0;
			$case_processing_log = []; // Track detailed progress for UI
			$unique_cases_processed = []; // Track unique case IDs to prevent double counting
			$duplicate_cases_found = []; // Track duplicate cases for logging

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
					'created' => 0,
					'updated' => 0,
					'errors' => [ 'No procedures found in sidebar data' ],
					'total_processed' => 0,
				];
			}

			// Track recent case creations for display
			$recent_cases = [];

			// Calculate better progress tracking - start case sync from 0%
			$case_sync_start_percentage = 0; // Start case sync from 0%
			$case_sync_percentage_range = 100; // Cases take full progress (0% to 100%)

			// Process each procedure, then each individual procedure ID
			foreach ( $procedures as $procedure_index => $procedure ) {
				$procedure_name = $procedure['name'] ?? 'Unknown';
				$procedure_ids = $procedure['ids'] ?? [];
				$case_count = $procedure['caseCount'] ?? 0;

				error_log( "BRAG book Gallery Sync: Extracted data for '{$procedure_name}' - IDs: " . wp_json_encode( $procedure_ids ) . ", case_count: {$case_count}" );

				try {
					error_log( "BRAG book Gallery Sync: Entering try block for procedure '{$procedure_name}'" );
					// Monitor memory and time at procedure level
					$procedure_start_time = microtime( true );
					$current_memory = memory_get_usage( true );
					$peak_memory = memory_get_peak_usage( true );

					error_log( "BRAG book Gallery Sync: Processing procedure '{$procedure_name}' with " . count( $procedure_ids ) . " IDs [" . implode( ', ', $procedure_ids ) . "] (" . ( $procedure_index + 1 ) . "/" . count( $procedures ) . ")" );
					error_log( "BRAG book Gallery Sync: Procedure '{$procedure_name}' has {$case_count} total cases" );
					error_log( "BRAG book Gallery Sync: Current memory: " . wp_convert_bytes_to_hr( $current_memory ) . ", Peak: " . wp_convert_bytes_to_hr( $peak_memory ) );
					$case_processing_log[] = "Processing procedure: {$procedure_name} (" . count( $procedure_ids ) . " IDs)";

					// Calculate overall progress based on cases processed across all procedures
					$cases_processed_so_far = $total_processed;
					$overall_percentage = $case_sync_start_percentage + ( ( $cases_processed_so_far / max( $total_expected_cases, 1 ) ) * $case_sync_percentage_range );
					$overall_percentage = min( $overall_percentage, 99 ); // Cap at 99% until complete

					// Calculate procedure progress (0% at start)
					$procedure_percentage = ( $procedure_index / count( $procedures ) ) * 100;

					error_log( "BRAG book Gallery Sync: Updating detailed progress for procedure start: {$procedure_name}" );
					error_log( "BRAG book Gallery Sync: Overall: {$overall_percentage}%, Procedure: {$procedure_percentage}%, Cases processed: {$cases_processed_so_far}/{$total_expected_cases}" );

					$this->update_detailed_progress( [
						'stage' => 'cases',
						'overall_percentage' => round( $overall_percentage, 1 ),
						'current_procedure' => $procedure_name,
						'procedure_current' => $procedure_index + 1,
						'procedure_total' => count( $procedures ),
						'procedure_percentage' => round( $procedure_percentage, 1 ),
						'case_current' => 0,
						'case_total' => $case_count,
						'case_percentage' => 0,
						'current_step' => "Starting procedure: {$procedure_name} (0 of {$case_count} cases)",
						'recent_cases' => $recent_cases,
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

							// Memory management: Force garbage collection before processing each procedure ID
							if ( function_exists( 'gc_collect_cycles' ) ) {
								gc_collect_cycles();
							}

							// Check memory usage and abort if getting too high
							$current_memory = memory_get_usage( true );
							$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
							$memory_usage_percent = ( $current_memory / $memory_limit ) * 100;

							if ( $memory_usage_percent > 90 ) {
								$error_msg = "Memory usage too high ({$memory_usage_percent}%) - aborting to prevent crash";
								error_log( "BRAG book Gallery Sync: ✗ {$error_msg}" );
								$errors[] = $error_msg;
								break 2; // Break out of both loops
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

							// Limit log array size to prevent memory bloat
							if ( count( $case_processing_log ) > 50 ) {
								$case_processing_log = array_slice( $case_processing_log, -25 ); // Keep only last 25 entries
							}

							// Stream process cases in paginated batches to reduce memory usage
							// Note: API typically returns ~10 cases per page, adjust batch size to match
							$batch_size = 10;
							$page = 1;
							$procedure_cases_processed = 0;
							$processed_case_ids = []; // Track processed cases to detect duplicates
							$empty_page_count = 0; // Track consecutive empty pages
							$max_pages_per_procedure = 100; // Safety limit to prevent infinite loops
							$logged_cases_for_procedure = []; // Track which cases we've already logged progress for

							error_log( "BRAG book Gallery Sync: Starting while loop for procedure ID {$procedure_id}, page {$page}, max pages: {$max_pages_per_procedure}" );
							while ( $page <= $max_pages_per_procedure ) {
								// Check for stop flag before processing batch
								if ( get_option( 'brag_book_gallery_sync_stop_flag', false ) ) {
									error_log( 'BRAG book Gallery Sync: Stop flag detected, terminating sync process' );
									$errors[] = 'Sync stopped by user request';
									delete_option( 'brag_book_gallery_sync_stop_flag' );
									break 3; // Break out of all loops
								}

								// Fetch a page of case IDs for this procedure
								try {
									$fetch_start_time = microtime( true );
									error_log( "BRAG book Gallery Sync: About to fetch case IDs for procedure ID {$procedure_id}, page {$page}, batch_size {$batch_size}" );
									$case_batch = $this->fetch_case_ids_paginated( $procedure_id, $page, $batch_size );
									$fetch_end_time = microtime( true );
									$fetch_duration = round( ( $fetch_end_time - $fetch_start_time ), 2 );
									error_log( "BRAG book Gallery Sync: Fetch completed for procedure ID {$procedure_id}, page {$page} - got " . count( $case_batch ) . " case IDs in {$fetch_duration}s" );
									if ( empty( $case_batch ) ) {
										$empty_page_count++;
										error_log( "BRAG book Gallery Sync: Empty page {$page} for procedure ID {$procedure_id} (empty count: {$empty_page_count})" );

										// Break after 2 consecutive empty pages
										if ( $empty_page_count >= 2 ) {
											error_log( "BRAG book Gallery Sync: Found {$empty_page_count} consecutive empty pages, ending procedure {$procedure_id}" );
											break;
										}

										$page++;
										continue;
									}

									// Reset empty page counter when we get data
									$empty_page_count = 0;

									// Check for duplicate case IDs (indicates we're in a loop)
									$new_case_ids = array_diff( $case_batch, $processed_case_ids );
									if ( empty( $new_case_ids ) ) {
										error_log( "BRAG book Gallery Sync: All cases in page {$page} are duplicates for procedure ID {$procedure_id}, ending procedure" );
										break;
									}

									// Track processed case IDs
									$processed_case_ids = array_merge( $processed_case_ids, $case_batch );

									$batch_case_count = count( $case_batch );
									$batch_start_time = microtime( true );
									error_log( "BRAG book Gallery Sync: *** Processing BATCH {$page} for procedure '{$procedure_name}' ({$batch_case_count} cases) ***" );

									// Process this batch of cases
									$batch_results = $this->process_case_batch( $case_batch, $procedure_name, $procedure_id );

									$batch_end_time = microtime( true );
									$batch_duration = round( ( $batch_end_time - $batch_start_time ), 2 );
									error_log( "BRAG book Gallery Sync: *** COMPLETED BATCH {$page} for procedure '{$procedure_name}' in {$batch_duration}s ***" );

									// Process batch results and update counters
									$batch_created = 0;
									$batch_updated = 0;
									foreach ( $batch_results as $case_result ) {
										$case_id = $case_result['case_id'];

										// Extract original case ID for unique counting
										$original_case_id = $case_result['original_case_id'] ?? $case_id;

										// Check if this is a duplicate case ID
										if ( in_array( $original_case_id, $unique_cases_processed, true ) ) {
											$duplicate_cases_found[] = $original_case_id;
											error_log( "BRAG book Gallery Sync: ⚠ Duplicate case found: {$original_case_id} (appears in multiple procedures)" );
											continue; // Skip counting this duplicate
										}

										if ( isset( $case_result['skipped'] ) && $case_result['skipped'] ) {
											error_log( "BRAG book Gallery Sync: ✓ Skipped case {$case_id}: " . ( $case_result['reason'] ?? 'Unknown reason' ) );
										} elseif ( $case_result['created'] ) {
											$created_cases++;
											$batch_created++;
											$unique_cases_processed[] = $original_case_id;
											error_log( "BRAG book Gallery Sync: ✓ Created case {$case_id} (post ID: {$case_result['post_id']}) - Original ID: {$original_case_id}" );
										} elseif ( $case_result['updated'] ) {
											$updated_cases++;
											$batch_updated++;
											$unique_cases_processed[] = $original_case_id;
											error_log( "BRAG book Gallery Sync: ✓ Updated case {$case_id} (post ID: {$case_result['post_id']}) - Original ID: {$original_case_id}" );
											$case_processing_log[] = "✓ Updated case {$case_id} from procedure ID {$procedure_id}";
										}

										$total_processed++;
										$procedure_cases_processed++;

										// Update progress for this case with dynamic calculation
										$cases_processed_so_far = $total_processed;

										// Dynamic progress calculation: if we exceed expected cases, update the denominator
										if ( $total_processed > $total_expected_cases ) {
											$total_expected_cases = $total_processed + round( $total_processed * 0.1 ); // Add 10% buffer
										}

										// Cap overall percentage at 100%
										$overall_percentage = min( 100, ( $cases_processed_so_far / max( $total_expected_cases, $total_processed ) ) * 100 );

										// Use actual processed count instead of sidebar case count for procedure progress
										$actual_case_count = max( $case_count, $procedure_cases_processed );
										$procedure_case_progress = min( 100, ( $procedure_cases_processed / $actual_case_count ) * 100 );
										$procedures_completed = $procedure_index;
										$procedure_overall_progress = ( $procedures_completed / count( $procedures ) ) * 100;

										// Send progress update frequently to show incremental progress (prevent duplicate logging)
										if ( ! in_array( $case_id, $logged_cases_for_procedure, true ) ) {
											$logged_cases_for_procedure[] = $case_id; // Mark this case as logged

											// Add to recent cases list (keep last 5) with proper counting format
											$case_position = $procedure_cases_processed; // Current position in this procedure
											$total_for_procedure = $actual_case_count; // Total cases for this procedure
											$case_display = "[CREATE] {$case_position}/{$total_for_procedure} {$procedure_name} ({$procedure_id}) - Case Id: {$case_id}";
											$recent_cases[] = $case_display;
											if ( count( $recent_cases ) > 5 ) {
												array_shift( $recent_cases );
											}
											error_log( "BRAG book Gallery Sync: Added to recent_cases: {$case_display}" );

											$this->update_detailed_progress( [
												'stage' => 'cases',
												'overall_percentage' => round( $overall_percentage, 1 ),
												'current_procedure' => $procedure_name,
												'procedure_current' => $procedure_index + 1,
												'procedure_total' => count( $procedures ),
												'procedure_percentage' => round( $procedure_overall_progress, 1 ),
												'case_current' => $procedure_cases_processed,
												'case_total' => $actual_case_count,
												'case_percentage' => round( $procedure_case_progress, 1 ),
												'current_step' => "[CREATE] {$procedure_cases_processed}/{$actual_case_count} {$procedure_name} ({$procedure_id}) - Case Id: {$case_id}",
												'recent_cases' => $recent_cases,
											] );
										}
									}

									// Log batch summary
									error_log( "BRAG book Gallery Sync: === BATCH {$page} SUMMARY: {$batch_case_count} cases processed ({$batch_created} created, {$batch_updated} updated) ===" );
									error_log( "BRAG book Gallery Sync: Procedure '{$procedure_name}' progress: {$procedure_cases_processed} cases total" );

									// Force progress update after each batch (ensure frontend sees progress)
									$cases_processed_so_far = $total_processed;
									$overall_percentage = min( 100, ( $cases_processed_so_far / max( $total_expected_cases, 1 ) ) * 100 );
									$actual_case_count = max( $case_count, $procedure_cases_processed );
									$procedure_case_progress = min( 100, ( $procedure_cases_processed / $actual_case_count ) * 100 );
									$procedures_completed = $procedure_index;
									$procedure_overall_progress = ( $procedures_completed / count( $procedures ) ) * 100;

									// Ensure recent_cases has current information for Recent Activity display
									if ( empty( $recent_cases ) ) {
										$recent_cases[] = "Processing {$procedure_name} - {$procedure_cases_processed} of {$actual_case_count} cases completed";
									}

									error_log( "BRAG book Gallery Sync: Forcing progress update - Overall: {$overall_percentage}%, Procedure: {$procedure_cases_processed}/{$actual_case_count}" );
									error_log( "BRAG book Gallery Sync: Recent cases for display: " . wp_json_encode( $recent_cases ) );
									$this->update_detailed_progress( [
										'stage' => 'cases',
										'overall_percentage' => round( $overall_percentage, 1 ),
										'current_procedure' => $procedure_name,
										'procedure_current' => $procedure_index + 1,
										'procedure_total' => count( $procedures ),
										'procedure_percentage' => round( $procedure_overall_progress, 1 ),
										'case_current' => $procedure_cases_processed,
										'case_total' => $actual_case_count,
										'case_percentage' => round( $procedure_case_progress, 1 ),
										'current_step' => "Batch completed: {$procedure_cases_processed} of {$actual_case_count} cases in {$procedure_name} (procedure ID: {$procedure_id})",
										'recent_cases' => $recent_cases, // Use actual recent cases, not just batch info
									] );

								} catch ( Exception $e ) {
									$error_msg = "Failed to process batch for procedure ID {$procedure_id}: " . $e->getMessage();
									$errors[] = $error_msg;
									error_log( "BRAG book Gallery Sync: ✗ {$error_msg}" );
									error_log( "BRAG book Gallery Sync: Exception trace: " . $e->getTraceAsString() );

									// Still count failed cases for progress
									$batch_size = count( $case_batch );
									$total_processed += $batch_size;
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
								$page++;
							}

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

				} catch ( Exception $e ) {
					$errors[] = "Failed to process procedure '{$procedure_name}': " . $e->getMessage();
					error_log( "BRAG book Gallery Sync: ✗ Failed to process procedure '{$procedure_name}': " . $e->getMessage() );
				}
			}

			error_log( 'BRAG book Gallery Sync: Stage 2 completed' );
			error_log( "BRAG book Gallery Sync: Cases created: {$created_cases}" );
			error_log( "BRAG book Gallery Sync: Cases updated: {$updated_cases}" );
			error_log( "BRAG book Gallery Sync: Total processed: {$total_processed}" );
			error_log( "BRAG book Gallery Sync: Unique cases processed: " . count( $unique_cases_processed ) );

			// Log duplicate cases found
			if ( ! empty( $duplicate_cases_found ) ) {
				$unique_duplicates = array_unique( $duplicate_cases_found );
				error_log( "BRAG book Gallery Sync: Duplicate cases found: " . count( $unique_duplicates ) );
				error_log( "BRAG book Gallery Sync: Duplicate case IDs: " . implode( ', ', $unique_duplicates ) );
				$warnings[] = "Found " . count( $unique_duplicates ) . " duplicate case IDs in multiple procedures: " . implode( ', ', $unique_duplicates );
			}

			// Verify math: created + updated should equal unique cases processed
			$expected_total = $created_cases + $updated_cases;
			$actual_total = count( $unique_cases_processed );
			if ( $expected_total !== $actual_total ) {
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

			// Restore original memory limit
			if ( isset( $original_memory_limit ) && $original_memory_limit !== ini_get( 'memory_limit' ) ) {
				ini_set( 'memory_limit', $original_memory_limit );
				error_log( "BRAG book Gallery Sync: Restored memory limit to {$original_memory_limit}" );
			}

			// Set final completion progress before cleanup
			$this->update_detailed_progress( [
				'stage' => 'completed',
				'overall_percentage' => 100,
				'current_procedure' => '',
				'procedure_current' => 0,
				'procedure_total' => 0,
				'procedure_percentage' => 100,
				'case_current' => count( $unique_cases_processed ),
				'case_total' => count( $unique_cases_processed ),
				'case_percentage' => 100,
				'current_step' => 'Sync Completed',
				'recent_cases' => [],
			] );

			// Store activity log for reference before cleanup
			$detailed_progress = get_option( 'brag_book_gallery_detailed_progress', [] );
			if ( ! empty( $detailed_progress ) ) {
				update_option( 'brag_book_gallery_completed_sync_log', $detailed_progress, false );
			}

			// Clean up progress tracking
			delete_option( 'brag_book_gallery_case_progress' );
			delete_option( 'brag_book_gallery_detailed_progress' );

			return [
				'created' => $created_cases,
				'updated' => $updated_cases,
				'errors' => $errors,
				'warnings' => $warnings,
				'total_processed' => count( $unique_cases_processed ), // Use unique cases count
				'duplicate_cases_found' => count( array_unique( $duplicate_cases_found ) ),
				'details' => [
					'case_processing_log' => $case_processing_log,
					'unique_cases_processed' => $unique_cases_processed,
					'duplicate_case_ids' => array_unique( $duplicate_cases_found ),
				],
			];

		} catch ( Exception $e ) {
			error_log( 'BRAG book Gallery Sync: Stage 2 failed with exception: ' . $e->getMessage() );

			// Restore original memory limit even on error
			if ( isset( $original_memory_limit ) && $original_memory_limit !== ini_get( 'memory_limit' ) ) {
				ini_set( 'memory_limit', $original_memory_limit );
				error_log( "BRAG book Gallery Sync: Restored memory limit to {$original_memory_limit} after error" );
			}

			// Clean up progress tracking on error
			delete_option( 'brag_book_gallery_case_progress' );
			delete_option( 'brag_book_gallery_detailed_progress' );

			throw $e;
		}
	}

	/**
	 * Extract procedures from sidebar data with their full structure
	 *
	 * @since 3.0.0
	 * @param array $sidebar_data Sidebar API response
	 * @return array Array of procedures with name, ids, caseCount, etc.
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
							'name' => $procedure['name'] ?? 'Unknown',
							'ids' => array_map( 'intval', $procedure['ids'] ),
							'caseCount' => (int) ( $procedure['totalCase'] ?? 0 ), // Fix: Use 'totalCase' not 'caseCount'
							'slug' => $procedure['slug'] ?? '',
							'nudity' => $procedure['nudity'] ?? false,
							'description' => $procedure['description'] ?? '', // Add description from API
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
	 * @since 3.0.0
	 * @param int $procedure_id Single procedure ID
	 * @return array Array of all case IDs for this procedure
	 * @throws Exception If API request fails
	 */
	/**
	 * Fetch case IDs for a procedure with pagination (memory efficient)
	 *
	 * @since 3.0.0
	 * @param int $procedure_id Procedure ID
	 * @param int $page Page number (1-based)
	 * @param int $per_page Cases per page (unused, API uses count-based pagination)
	 * @return array Case IDs for this page
	 * @throws Exception If fetching fails
	 */
	private function fetch_case_ids_paginated( int $procedure_id, int $page, int $per_page = 15 ): array {
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
		$all_case_ids = [];
		$count = 1;
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
					$count++;

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
	 * Fetch case IDs for a single procedure ID with specific count
	 *
	 * @since 3.0.0
	 * @param int $procedure_id Single procedure ID to filter by
	 * @param int $count Number of cases to fetch (incremental: 1, 2, 3, etc.)
	 * @return array Array of case IDs
	 * @throws Exception If API request fails
	 */
	private function fetch_case_ids_for_single_procedure_count( int $procedure_id, int $count ): array {
		$endpoint = '/api/plugin/combine/cases';

		// Get API tokens and website property IDs
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			throw new Exception( __( 'No API tokens configured', 'brag-book-gallery' ) );
		}

		$valid_tokens = array_filter( $api_tokens, function( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build full URL
		$api_base_url = $this->get_api_base_url();
		$full_url = $api_base_url . $endpoint;

		// Prepare request body using count-based approach (no start parameter)
		$request_body = [
			'apiTokens' => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'count' => $count,
			'procedureIds' => [ $procedure_id ], // Single procedure ID in array
		];

		error_log( 'BRAG book Gallery Sync: Fetching procedure ' . $procedure_id . ' with count ' . $count );
		error_log( 'BRAG book Gallery Sync: Request body: ' . wp_json_encode( $request_body ) );

		// Make POST request
		$response = wp_remote_post( $full_url, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body' => wp_json_encode( $request_body ),
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
			error_log( 'BRAG book Gallery Sync: API error response for procedure ' . $procedure_id . ' count ' . $count . ': ' . $response_body );
			throw new Exception( sprintf(
				__( 'API returned error status %d for procedure %d count %d', 'brag-book-gallery' ),
				$response_code,
				$procedure_id,
				$count
			) );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( __( 'Invalid JSON response from API', 'brag-book-gallery' ) );
		}

		// Log the full response for debugging
		error_log( 'BRAG book Gallery Sync: Full API response for procedure ' . $procedure_id . ' count ' . $count . ': ' . $response_body );

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			error_log( 'BRAG book Gallery Sync: API response not successful for procedure ' . $procedure_id . ' count ' . $count );
			throw new Exception( __( 'API returned unsuccessful response', 'brag-book-gallery' ) );
		}

		// Extract case IDs from response
		$case_ids = [];
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			error_log( 'BRAG book Gallery Sync: Found data array with ' . count( $data['data'] ) . ' items for procedure ' . $procedure_id . ' count ' . $count );
			foreach ( $data['data'] as $case_index => $case ) {
				if ( isset( $case['id'] ) ) {
					$case_ids[] = (int) $case['id'];
					error_log( 'BRAG book Gallery Sync: Found case ID ' . $case['id'] . ' at index ' . $case_index );
				} else {
					error_log( 'BRAG book Gallery Sync: Case at index ' . $case_index . ' missing ID field: ' . wp_json_encode( array_keys( $case ) ) );
				}
			}
		} else {
			error_log( 'BRAG book Gallery Sync: No data array found in response for procedure ' . $procedure_id . ' count ' . $count . '. Response keys: ' . wp_json_encode( array_keys( $data ) ) );
		}

		error_log( 'BRAG book Gallery Sync: Procedure ' . $procedure_id . ' count ' . $count . ' returned ' . count( $case_ids ) . ' case IDs: ' . wp_json_encode( $case_ids ) );

		return $case_ids;
	}

	/**
	 * Fetch case IDs for specific procedure IDs and page
	 *
	 * @since 3.0.0
	 * @param array $procedure_ids Array of procedure IDs to filter by
	 * @param int   $page Page number (1-based)
	 * @param int   $count Number of cases per page
	 * @return array Array of case IDs
	 * @throws Exception If API request fails
	 */
	private function fetch_case_ids_for_procedure_page( array $procedure_ids, int $page, int $count = 10 ): array {
		$endpoint = '/api/plugin/combine/cases';

		// Get API tokens and website property IDs
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			throw new Exception( __( 'No API tokens configured', 'brag-book-gallery' ) );
		}

		$valid_tokens = array_filter( $api_tokens, function( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build full URL
		$api_base_url = $this->get_api_base_url();
		$full_url = $api_base_url . $endpoint;

		// Prepare request body with procedure IDs filter and pagination
		$request_body = [
			'apiTokens' => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'count' => $count,
			'start' => ( ( $page - 1 ) * $count ) + 1, // Convert to 1-based start index
			'procedureIds' => $procedure_ids, // Filter by specific procedure IDs
		];

		error_log( 'BRAG book Gallery Sync: Fetching procedures [' . implode( ', ', $procedure_ids ) . '] page ' . $page . ' (start: ' . $request_body['start'] . ', count: ' . $count . ')' );
		error_log( 'BRAG book Gallery Sync: Request body: ' . wp_json_encode( $request_body ) );

		// Make POST request
		$response = wp_remote_post( $full_url, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body' => wp_json_encode( $request_body ),
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
	 * @since 3.0.0
	 * @param array $sidebar_data Sidebar API response
	 * @return array Array of procedure IDs
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
	 * @since 3.0.0
	 * @param array  $case_batch Array of case IDs to process (up to 5)
	 * @param string $procedure_name Name of the procedure for logging
	 * @param int    $procedure_id Procedure ID for logging
	 * @return array Array of case processing results
	 * @throws Exception If batch processing fails critically
	 */
	private function process_case_batch( array $case_batch, string $procedure_name, int $procedure_id ): array {
		try {
			$batch_size = count( $case_batch );
			error_log( "BRAG book Gallery Sync: process_case_batch() starting with {$batch_size} cases from procedure '{$procedure_name}'" );

			// Update progress: Starting API fetch
			$this->update_step_progress( "[FETCH] Fetching case details for {$batch_size} cases from {$procedure_name}" );

			// Make parallel API calls for case details
			$fetch_start_time = microtime( true );
			$case_details_results = $this->fetch_case_details_batch( $case_batch );
			$fetch_duration = round( microtime( true ) - $fetch_start_time, 2 );

			error_log( "BRAG book Gallery Sync: API fetch completed in {$fetch_duration}s for {$batch_size} cases" );
			$this->update_step_progress( "Case details fetched in {$fetch_duration}s, processing {$batch_size} cases..." );

			// Process each case with its fetched data
			$batch_results = [];
			$case_count = 0;
			$total_cases = count( $case_batch );

			foreach ( $case_batch as $case_id ) {
				try {
					$case_count++;
					$this->update_step_progress( "Processing case {$case_id} ({$case_count}/{$total_cases}) from {$procedure_name}..." );

					// Get the case data from batch results
					$case_data = $case_details_results[$case_id] ?? null;

					if ( ! $case_data ) {
						error_log( "BRAG book Gallery Sync: No data found for case {$case_id} in batch results" );
						$batch_results[] = [
							'case_id' => $case_id,
							'post_id' => null,
							'created' => false,
							'updated' => false,
							'skipped' => true,
							'reason'  => 'No data found in batch results',
						];
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

						$batch_results[] = [
							'case_id' => $case_id,
							'post_id' => null,
							'created' => false,
							'updated' => false,
							'skipped' => true,
							'reason'  => 'Not approved for website (isForWebsite: false)',
						];
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
					$is_update = ! empty( $existing_post );

					$this->update_step_progress( "Creating/updating WordPress post for case {$case_id}..." );

					// Handle multi-procedure cases by creating separate posts for each procedure
					$procedure_results = $this->process_multi_procedure_case( $case_id, $case_data, $existing_post );

					$this->update_step_progress( "Saving metadata and taxonomy for case {$case_id}..." );

					// Add all procedure results to batch results
					foreach ( $procedure_results as $procedure_result ) {
						$batch_results[] = $procedure_result;
					}

					// Memory cleanup: Clear case data to free memory
					$case_data = null;
					unset( $case_data );

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
			return $batch_results;

		} catch ( Exception $e ) {
			error_log( "BRAG book Gallery Sync: ✗ process_case_batch() failed critically: " . $e->getMessage() );
			error_log( "BRAG book Gallery Sync: Exception stack trace: " . $e->getTraceAsString() );
			throw $e;
		}
	}

	/**
	 * Fetch case details for multiple cases in parallel (batch operation)
	 *
	 * @since 3.0.0
	 * @param array $case_ids Array of case IDs to fetch
	 * @return array Associative array with case_id => case_data
	 * @throws Exception If batch fetch fails
	 */
	private function fetch_case_details_batch( array $case_ids ): array {
		$batch_size = count( $case_ids );
		error_log( "BRAG book Gallery Sync: fetch_case_details_batch() starting for {$batch_size} cases: " . implode( ', ', $case_ids ) );

		// Get API tokens and website property IDs
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( "BRAG book Gallery Sync: ✗ No API tokens configured for batch fetch" );
			throw new Exception( __( 'No API tokens configured', 'brag-book-gallery' ) );
		}

		$valid_tokens = array_filter( $api_tokens, function( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Get API base URL
		$api_base_url = $this->get_api_base_url();

		// Prepare common request data
		$base_request_body = [
			'apiTokens' => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'procedureIds' => [ 6851 ], // Default fallback, will be updated per case if needed
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
			$request_body = $base_request_body;
			$existing_post = $this->find_existing_case_post( $case_id );
			if ( $existing_post ) {
				$stored_procedure_ids = get_post_meta( $existing_post->ID, '_case_procedure_ids', true );
				if ( ! empty( $stored_procedure_ids ) ) {
					$request_body['procedureIds'] = array_map( 'intval', explode( ',', $stored_procedure_ids ) );
				}
			}

			$ch = curl_init();
			curl_setopt_array( $ch, [
				CURLOPT_URL => $full_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => wp_json_encode( $request_body ),
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'Accept: application/json',
					'User-Agent: BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
				],
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_FOLLOWLOCATION => true,
			] );

			curl_multi_add_handle( $multi_handle, $ch );
			$curl_handles[$case_id] = $ch;
		}

		// Execute all requests in parallel
		$request_start = microtime( true );
		$running = null;
		do {
			curl_multi_exec( $multi_handle, $running );
			curl_multi_select( $multi_handle );
		} while ( $running > 0 );
		$request_duration = round( ( microtime( true ) - $request_start ) * 1000, 2 );

		error_log( "BRAG book Gallery Sync: Parallel API requests for {$batch_size} cases completed in {$request_duration}ms" );

		// Collect results from each handle
		foreach ( $case_ids as $case_id ) {
			$ch = $curl_handles[$case_id];
			$response_body = curl_multi_getcontent( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$curl_error = curl_error( $ch );

			curl_multi_remove_handle( $multi_handle, $ch );
			curl_close( $ch );

			// Handle cURL errors
			if ( ! empty( $curl_error ) ) {
				error_log( "BRAG book Gallery Sync: cURL error for case {$case_id}: {$curl_error}" );
				$case_results[$case_id] = null;
				continue;
			}

			// Handle HTTP errors
			if ( $http_code !== 200 ) {
				error_log( "BRAG book Gallery Sync: HTTP error {$http_code} for case {$case_id}: " . substr( $response_body, 0, 200 ) );
				$case_results[$case_id] = null;
				continue;
			}

			// Parse JSON response
			$data = json_decode( $response_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( "BRAG book Gallery Sync: JSON decode error for case {$case_id}: " . json_last_error_msg() );
				error_log( "BRAG book Gallery Sync: Case {$case_id} response body (first 500 chars): " . substr( $response_body, 0, 500 ) );
				error_log( "BRAG book Gallery Sync: Case {$case_id} response body length: " . strlen( $response_body ) );
				$case_results[$case_id] = null;
				continue;
			}

			// Validate API response structure
			if ( ! isset( $data['success'] ) || ! $data['success'] ) {
				error_log( "BRAG book Gallery Sync: API returned unsuccessful response for case {$case_id}" );
				$case_results[$case_id] = null;
				continue;
			}

			if ( ! isset( $data['data'][0] ) ) {
				error_log( "BRAG book Gallery Sync: No case data found in API response for case {$case_id}" );
				$case_results[$case_id] = null;
				continue;
			}

			$case_results[$case_id] = $data['data'][0];
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
	 * @since 3.0.0
	 * @param int $case_id Original case ID from API
	 * @param array $case_data Case data from API
	 * @param \WP_Post|null $existing_post Existing case post if found
	 * @return array Array of procedure processing results
	 * @throws Exception If processing fails
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
		$strategy = get_option( 'brag_book_gallery_multi_procedure_strategy', 'single_post' );
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
				$procedure_case_data = $case_data;
				$procedure_case_data['procedureIds'] = [ $procedure_id ]; // Only assign this one procedure
				$procedure_case_data['original_case_id'] = $case_id; // Store original case ID for reference

				// Check if this specific procedure case already exists
				$existing_procedure_post = $this->find_existing_procedure_case_post( $case_id, $procedure_id );
				$is_update = ! empty( $existing_procedure_post );

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
				$progress_callback = function( $message ) {
					$this->update_step_progress( $message );
				};
				\BRAGBookGallery\Includes\Extend\Post_Types::save_api_response_data( $post_id, $procedure_case_data, $progress_callback );

				// Store the original case ID and procedure mapping
				update_post_meta( $post_id, '_original_case_id', $case_id );
				update_post_meta( $post_id, '_procedure_id', $procedure_id );
				update_post_meta( $post_id, '_procedure_index', $procedure_index );

				// Assign only this specific procedure taxonomy
				$this->assign_single_procedure_taxonomy( $post_id, $procedure_id );

				// Log the operation
				$this->log_case_operation( $post_id, $procedure_case_data, ! $is_update );

				$procedure_results[] = [
					'case_id' => $procedure_case_id,
					'original_case_id' => $case_id,
					'procedure_id' => $procedure_id,
					'post_id' => $post_id,
					'created' => ! $is_update,
					'updated' => $is_update,
				];

				error_log( "BRAG book Gallery Sync: ✓ Successfully processed procedure case {$procedure_case_id}" );

			} catch ( Exception $e ) {
				error_log( "BRAG book Gallery Sync: ✗ Failed to process procedure case {$procedure_case_id}: " . $e->getMessage() );
				$procedure_results[] = [
					'case_id' => $procedure_case_id ?? $case_id,
					'original_case_id' => $case_id,
					'procedure_id' => $procedure_id,
					'post_id' => null,
					'created' => false,
					'updated' => false,
					'error' => $e->getMessage(),
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
	 * @since 3.0.0
	 * @param int $case_id Case ID
	 * @param array $case_data Case data from API
	 * @param \WP_Post|null $existing_post Existing post if found
	 * @return array Processing result
	 * @throws Exception If processing fails
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
		$progress_callback = function( $message ) {
			$this->update_step_progress( $message );
		};
		\BRAGBookGallery\Includes\Extend\Post_Types::save_api_response_data( $post_id, $case_data, $progress_callback );

		// Assign taxonomies (all procedures for single post)
		$this->assign_case_taxonomies( $post_id, $case_data );

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
	 * @since 3.0.0
	 * @param int $original_case_id Original case ID
	 * @param int $procedure_id Procedure ID
	 * @return \WP_Post|null Found post or null
	 */
	private function find_existing_procedure_case_post( int $original_case_id, int $procedure_id ): ?\WP_Post {
		$query = new \WP_Query( [
			'post_type' => 'brag_book_cases',
			'post_status' => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => 1,
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => '_original_case_id',
					'value' => $original_case_id,
					'compare' => '=',
				],
				[
					'key' => '_procedure_id',
					'value' => $procedure_id,
					'compare' => '=',
				],
			],
			'fields' => 'ids',
		] );

		if ( $query->have_posts() ) {
			return get_post( $query->posts[0] );
		}

		return null;
	}

	/**
	 * Assign single procedure taxonomy to post
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID
	 * @param int $procedure_id Procedure ID to assign
	 * @return void
	 */
	private function assign_single_procedure_taxonomy( int $post_id, int $procedure_id ): void {
		error_log( "BRAG book Gallery Sync: assign_single_procedure_taxonomy() - Attempting to assign procedure ID {$procedure_id} to post {$post_id}" );

		// Find the term that matches this procedure ID
		$terms = get_terms( [
			'taxonomy' => 'procedures',
			'hide_empty' => false,
			'meta_query' => [
				[
					'key' => 'procedure_id',
					'value' => $procedure_id,
					'type' => 'NUMERIC',
				],
			],
		] );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// Assign only this one procedure term
			wp_set_post_terms( $post_id, [ $terms[0]->term_id ], 'procedures' );
			error_log( "BRAG book Gallery Sync: ✓ Assigned procedure term '{$terms[0]->name}' (ID: {$terms[0]->term_id}) to post {$post_id}" );
			return;
		}

		// DEBUG: Log available procedure terms and their meta
		error_log( "BRAG book Gallery Sync: DEBUG - Could not find procedure term for procedure ID {$procedure_id}" );
		$all_terms = get_terms( [
			'taxonomy' => 'procedures',
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
			'taxonomy' => 'procedures',
			'hide_empty' => false,
			'meta_query' => [
				[
					'key' => 'api_data',
					'compare' => 'EXISTS',
				],
			],
		] );

		if ( ! empty( $terms_with_api_data ) && ! is_wp_error( $terms_with_api_data ) ) {
			foreach ( $terms_with_api_data as $term ) {
				$api_data = get_term_meta( $term->term_id, 'api_data', true );
				if ( is_array( $api_data ) && isset( $api_data['api_id'] ) && (int) $api_data['api_id'] === $procedure_id ) {
					wp_set_post_terms( $post_id, [ $term->term_id ], 'procedures' );
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
	 * @since 3.0.0
	 * @param int $case_id Case ID
	 * @return array Processing result
	 * @throws Exception If processing fails
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
			$is_update = ! empty( $existing_post );
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
	 * @since 3.0.0
	 * @param int $case_id Case ID
	 * @return array Case data
	 * @throws Exception If API request fails
	 */
	private function fetch_case_details( int $case_id ): array {
		error_log( "BRAG book Gallery Sync: fetch_case_details() starting for case {$case_id}" );
		$endpoint = '/api/plugin/combine/cases/' . $case_id;

		// Get API tokens and website property IDs
		error_log( "BRAG book Gallery Sync: Getting API configuration for case {$case_id}..." );
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( "BRAG book Gallery Sync: ✗ No API tokens configured for case {$case_id}" );
			throw new Exception( __( 'No API tokens configured', 'brag-book-gallery' ) );
		}

		error_log( "BRAG book Gallery Sync: API configuration found - " . count( $api_tokens ) . " tokens, " . count( $website_property_ids ) . " property IDs" );

		$valid_tokens = array_filter( $api_tokens, function( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build full URL
		$api_base_url = $this->get_api_base_url();
		$full_url = $api_base_url . $endpoint;

		// For single case endpoint, we need to get procedure IDs from the case
		// Since we don't know the procedure ID beforehand, we'll use a default from settings
		// or try to get it from the case's existing data
		$default_procedure_ids = [ 6851 ]; // Default fallback

		// Try to get procedure IDs from existing case post if it exists
		$existing_post = $this->find_existing_case_post( $case_id );
		if ( $existing_post ) {
			$stored_procedure_ids = get_post_meta( $existing_post->ID, '_case_procedure_ids', true );
			if ( ! empty( $stored_procedure_ids ) ) {
				$default_procedure_ids = array_map( 'intval', explode( ',', $stored_procedure_ids ) );
			}
		}

		// Prepare request body matching API Test format for single case
		$request_body = [
			'apiTokens' => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'procedureIds' => $default_procedure_ids,
		];

		error_log( "BRAG book Gallery Sync: Making API request for case {$case_id} to: {$full_url}" );
		error_log( 'BRAG book Gallery Sync: Request body: ' . wp_json_encode( $request_body ) );

		// Make POST request
		$request_start = microtime( true );
		$response = wp_remote_post( $full_url, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'body' => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );
		$request_duration = round( ( microtime( true ) - $request_start ) * 1000, 2 );

		error_log( "BRAG book Gallery Sync: API request for case {$case_id} completed in {$request_duration}ms" );

		if ( is_wp_error( $response ) ) {
			error_log( "BRAG book Gallery Sync: ✗ API request failed for case {$case_id}: " . $response->get_error_message() );
			throw new Exception( sprintf(
				__( 'API request failed: %s', 'brag-book-gallery' ),
				$response->get_error_message()
			) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		error_log( "BRAG book Gallery Sync: API response for case {$case_id} - Code: {$response_code}, Body length: " . strlen( $response_body ) . " bytes" );

		if ( $response_code !== 200 ) {
			error_log( 'BRAG book Gallery Sync: API error response for case ' . $case_id . ': ' . $response_body );
			throw new Exception( sprintf(
				__( 'API returned error status %d for case %d', 'brag-book-gallery' ),
				$response_code,
				$case_id
			) );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( "BRAG book Gallery Sync: Case {$case_id} JSON decode error: " . json_last_error_msg() );
			error_log( "BRAG book Gallery Sync: Case {$case_id} response body (first 500 chars): " . substr( $response_body, 0, 500 ) );
			error_log( "BRAG book Gallery Sync: Case {$case_id} response body length: " . strlen( $response_body ) );
			throw new Exception( sprintf(
				__( 'Invalid JSON response from API for case %d: %s', 'brag-book-gallery' ),
				$case_id,
				json_last_error_msg()
			) );
		}

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			error_log( 'BRAG book Gallery Sync: API returned unsuccessful response for case ' . $case_id . ': ' . wp_json_encode( $data ) );
			throw new Exception( __( 'API returned unsuccessful response', 'brag-book-gallery' ) );
		}

		if ( ! isset( $data['data'][0] ) ) {
			throw new Exception( __( 'No case data found in API response', 'brag-book-gallery' ) );
		}

		error_log( 'BRAG book Gallery Sync: Successfully fetched case details for case ' . $case_id );

		return $data['data'][0]; // Return first case data
	}

	/**
	 * Find existing case post by API case ID
	 *
	 * @since 3.0.0
	 * @param int $case_id API case ID
	 * @return \WP_Post|null Existing post or null
	 */
	private function find_existing_case_post( int $case_id ): ?\WP_Post {
		$posts = get_posts( [
			'post_type' => 'brag_book_cases',
			'meta_key' => '_case_api_id',
			'meta_value' => $case_id,
			'numberposts' => 1,
			'post_status' => 'any',
		] );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Create new case post
	 *
	 * @since 3.0.0
	 * @param array $case_data Case data from API
	 * @return int Post ID
	 * @throws Exception If post creation fails
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
			$case_data['id'] ?? 0,
			$case_data['procedureId'] ?? 0,
			current_time( 'mysql' )
		);

		$post_data = [
			'post_type' => 'brag_book_cases',
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => $content,
			'post_status' => isset( $case_data['draft'] ) && $case_data['draft'] ? 'draft' : 'publish',
			'meta_input' => [
				'_case_api_id' => $case_data['id'],
				'_case_synced_at' => current_time( 'mysql' ),
			],
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( sprintf(
				__( 'Failed to create case post: %s', 'brag-book-gallery' ),
				$post_id->get_error_message()
			) );
		}

		return $post_id;
	}

	/**
	 * Update existing case post
	 *
	 * @since 3.0.0
	 * @param int   $post_id Existing post ID
	 * @param array $case_data Case data from API
	 * @return int Post ID
	 * @throws Exception If post update fails
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
			$case_data['id'] ?? 0,
			$case_data['procedureId'] ?? 0,
			current_time( 'mysql' )
		);

		$post_data = [
			'ID' => $post_id,
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => $content,
			'post_status' => isset( $case_data['draft'] ) && $case_data['draft'] ? 'draft' : 'publish',
		];

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			throw new Exception( sprintf(
				__( 'Failed to update case post: %s', 'brag-book-gallery' ),
				$result->get_error_message()
			) );
		}

		// Update sync timestamp
		update_post_meta( $post_id, '_case_synced_at', current_time( 'mysql' ) );

		return $post_id;
	}

	/**
	 * Generate case title from API data
	 *
	 * @since 3.0.0
	 * @param array $case_data Case data from API
	 * @return string Generated title
	 */
	private function generate_case_title( array $case_data ): string {
		$case_id = $case_data['id'] ?? 'Unknown';
		$age = $case_data['age'] ?? '';
		$gender = $case_data['gender'] ?? '';

		// Try to get procedure names from IDs
		$procedure_names = [];
		if ( isset( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			foreach ( $case_data['procedureIds'] as $procedure_id ) {
				$term = get_terms( [
					'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
					'meta_key' => 'procedure_id',
					'meta_value' => $procedure_id,
					'hide_empty' => false,
					'number' => 1,
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
	 * Uses seoSuffixUrl from caseDetails if available, otherwise falls back to case ID.
	 *
	 * @since 3.0.0
	 * @param array $case_data Case data from API
	 * @return string Generated slug
	 */
	private function generate_case_slug( array $case_data ): string {
		$case_id = $case_data['id'] ?? 'unknown';

		// Check for seoSuffixUrl in caseDetails array
		if ( isset( $case_data['caseDetails'] ) && is_array( $case_data['caseDetails'] ) ) {
			foreach ( $case_data['caseDetails'] as $case_detail ) {
				if ( isset( $case_detail['seoSuffixUrl'] ) && ! empty( $case_detail['seoSuffixUrl'] ) ) {
					// Sanitize the seoSuffixUrl to make it a valid WordPress slug
					$slug = sanitize_title( $case_detail['seoSuffixUrl'] );

					// Make sure it's not empty after sanitization
					if ( ! empty( $slug ) ) {
						error_log( 'BRAG book Gallery Sync: Using seoSuffixUrl as slug for case ' . $case_id . ': ' . $slug );
						return $slug;
					}
				}
			}
		}

		// Fallback to case ID
		$fallback_slug = 'case-' . $case_id;
		error_log( 'BRAG book Gallery Sync: Using fallback slug for case ' . $case_id . ': ' . $fallback_slug );

		return $fallback_slug;
	}

	/**
	 * Assign taxonomies to case post
	 *
	 * @since 3.0.0
	 * @param int   $post_id Post ID
	 * @param array $case_data Case data from API
	 * @return void
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
				'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
				'meta_key' => 'procedure_id',
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
			wp_set_object_terms( $post_id, $term_ids, Taxonomies::TAXONOMY_PROCEDURES );
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
	 * @since 3.0.0
	 * @param int $post_id Post ID to assign procedure to
	 * @param int|null $original_procedure_id Original procedure ID that failed (optional)
	 * @return void
	 */
	private function assign_fallback_procedure( int $post_id, ?int $original_procedure_id = null ): void {
		// Strategy 1: Try to create missing procedure term if we have the original procedure ID
		if ( $original_procedure_id ) {
			$created_term = $this->create_missing_procedure_term( $original_procedure_id );
			if ( $created_term ) {
				wp_set_post_terms( $post_id, [ $created_term->term_id ], 'procedures' );
				error_log( "BRAG book Gallery Sync: ✓ Created and assigned missing procedure term for ID {$original_procedure_id} to post {$post_id}" );
				return;
			}
		}

		// Strategy 2: Find and assign to "Other Procedures" or "Miscellaneous" category
		$fallback_terms = get_terms( [
			'taxonomy' => 'procedures',
			'hide_empty' => false,
			'name__in' => [ 'Other Procedures', 'Miscellaneous', 'Other', 'General Procedures' ],
			'number' => 1,
		] );

		if ( ! empty( $fallback_terms ) && ! is_wp_error( $fallback_terms ) ) {
			wp_set_post_terms( $post_id, [ $fallback_terms[0]->term_id ], 'procedures' );
			error_log( "BRAG book Gallery Sync: ✓ Assigned fallback procedure '{$fallback_terms[0]->name}' to post {$post_id}" );
			return;
		}

		// Strategy 3: Create "Other Procedures" category if it doesn't exist
		$fallback_term = wp_insert_term( 'Other Procedures', 'procedures', [
			'description' => 'Cases that could not be categorized into specific procedures',
			'parent' => 0, // Make it a top-level category
		] );

		if ( ! is_wp_error( $fallback_term ) ) {
			// Add meta data for the new term
			update_term_meta( $fallback_term['term_id'], 'procedure_id', 99999 ); // Use high ID for fallback
			update_term_meta( $fallback_term['term_id'], 'nudity', 'false' );

			wp_set_post_terms( $post_id, [ $fallback_term['term_id'] ], 'procedures' );
			error_log( "BRAG book Gallery Sync: ✓ Created and assigned 'Other Procedures' fallback category to post {$post_id}" );
			return;
		}

		// Strategy 4: Last resort - assign to ANY existing procedure
		$any_terms = get_terms( [
			'taxonomy' => 'procedures',
			'hide_empty' => false,
			'number' => 1,
			'orderby' => 'count',
			'order' => 'DESC', // Get the most used procedure
		] );

		if ( ! empty( $any_terms ) && ! is_wp_error( $any_terms ) ) {
			wp_set_post_terms( $post_id, [ $any_terms[0]->term_id ], 'procedures' );
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
	 * @since 3.0.0
	 * @param int $procedure_id Procedure ID from API
	 * @return \WP_Term|null Created term or null if failed
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
			$result = wp_insert_term( $procedure_name, 'procedures', [
				'description' => "Auto-created procedure term for procedure ID {$procedure_id}",
				'parent' => 0, // Make it a top-level category for now
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
	 * @since 3.0.0
	 * @return array Report of fixed cases
	 */
	public function validate_and_fix_unassigned_cases(): array {
		error_log( "BRAG book Gallery Sync: Starting validation of cases without procedures..." );

		// Find all case posts that don't have any procedure terms assigned
		$unassigned_posts = new \WP_Query( [
			'post_type' => 'brag_book_cases',
			'post_status' => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'tax_query' => [
				[
					'taxonomy' => 'procedures',
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
					$original_case_id = get_post_meta( $post_id, '_original_case_id', true );
					$procedure_id = get_post_meta( $post_id, '_procedure_id', true );

					if ( $procedure_id ) {
						// This is a procedure-specific case, try to assign the correct procedure
						$this->assign_single_procedure_taxonomy( $post_id, $procedure_id );
						$fixed_cases[] = [
							'post_id' => $post_id,
							'method' => 'specific_procedure',
							'procedure_id' => $procedure_id,
						];
					} else {
						// Try to get procedure IDs from stored case data
						$procedure_ids_meta = get_post_meta( $post_id, '_case_procedure_ids', true );
						if ( $procedure_ids_meta ) {
							$procedure_ids = explode( ',', $procedure_ids_meta );
							$case_data = [ 'procedureIds' => array_map( 'intval', $procedure_ids ) ];
							$this->assign_case_taxonomies( $post_id, $case_data );
							$fixed_cases[] = [
								'post_id' => $post_id,
								'method' => 'stored_procedure_ids',
								'procedure_ids' => $procedure_ids,
							];
						} else {
							// Last resort - assign fallback procedure
							$this->assign_fallback_procedure( $post_id );
							$fixed_cases[] = [
								'post_id' => $post_id,
								'method' => 'fallback',
							];
						}
					}

					error_log( "BRAG book Gallery Sync: ✓ Fixed procedure assignment for post {$post_id}" );

				} catch ( Exception $e ) {
					error_log( "BRAG book Gallery Sync: ✗ Failed to fix procedure assignment for post {$post_id}: " . $e->getMessage() );
					$fixed_cases[] = [
						'post_id' => $post_id,
						'method' => 'failed',
						'error' => $e->getMessage(),
					];
				}
			}
		}

		$fixed_count = count( array_filter( $fixed_cases, fn( $case ) => $case['method'] !== 'failed' ) );
		$failed_count = count( array_filter( $fixed_cases, fn( $case ) => $case['method'] === 'failed' ) );

		$report = [
			'total_found' => $total_found,
			'fixed_count' => $fixed_count,
			'failed_count' => $failed_count,
			'details' => $fixed_cases,
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
	 * @since 3.0.0
	 * @return void
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
				$post_id = $case['post_id'];
				$method = $case['method'];
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
	 * @since 3.0.0
	 * @param int   $post_id Post ID
	 * @param array $case_data Case data from API
	 * @param bool  $created Whether case was created or updated
	 * @return void
	 */
	private function log_case_operation( int $post_id, array $case_data, bool $created ): void {
		global $wpdb;

		// HIPAA Compliance: Only log operational data (IDs, status, counts)
		// Does NOT log: patient names, case details, or any PHI from API data
		$procedure_id = $case_data['procedureId'] ?? null;
		$operation_type = $created ? 'create' : 'update';

		// Create safe details string with only operational info
		$safe_details = sprintf(
			'Case %s: Post ID %d, API ID %d, Procedure ID %d',
			$operation_type,
			$post_id,
			$case_data['id'] ?? 0,
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
				'api_id'          => $case_data['id'] ?? null,
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
	 * @since 3.0.0
	 * @param string $session_id Sync session ID
	 * @return array Reverse operation results
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
		$errors = [];

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
						$deleted++;
					}
				} else {
					// Delete taxonomy term
					$result = wp_delete_term( $operation->wordpress_id, Taxonomies::TAXONOMY_PROCEDURES );
					if ( is_wp_error( $result ) ) {
						$errors[] = sprintf(
							__( 'Failed to delete term %s: %s', 'brag-book-gallery' ),
							$operation->name,
							$result->get_error_message()
						);
					} else {
						$deleted++;
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
	 * @since 3.0.0
	 * @param int    $processed Number of cases processed
	 * @param int    $total     Total cases to process
	 * @param string $message   Current progress message
	 * @return void
	 */
	/**
	 * Update detailed progress with procedure and case information
	 *
	 * @since 3.0.0
	 * @param array $progress_data Detailed progress information
	 */
	private function update_detailed_progress( array $progress_data ): void {
		$progress = [
			'stage' => $progress_data['stage'] ?? 'cases',
			'overall_percentage' => round( $progress_data['overall_percentage'] ?? 0, 1 ),
			'current_procedure' => $progress_data['current_procedure'] ?? '',
			'procedure_progress' => [
				'current' => $progress_data['procedure_current'] ?? 0,
				'total' => $progress_data['procedure_total'] ?? 0,
				'percentage' => round( $progress_data['procedure_percentage'] ?? 0, 1 ),
			],
			'case_progress' => [
				'current' => $progress_data['case_current'] ?? 0,
				'total' => $progress_data['case_total'] ?? 0,
				'percentage' => round( $progress_data['case_percentage'] ?? 0, 1 ),
			],
			'current_step' => $progress_data['current_step'] ?? '',
			'recent_cases' => $progress_data['recent_cases'] ?? [],
			'updated_at' => current_time( 'mysql' ),
		];

		// Force option update without autoload and clear any caching
		$update_result = update_option( 'brag_book_gallery_detailed_progress', $progress, false );

		// Clear object cache to ensure fresh data
		wp_cache_delete( 'brag_book_gallery_detailed_progress', 'options' );

		// Log the update result
		error_log( "BRAG book Gallery Sync: update_option result: " . ( $update_result ? 'SUCCESS' : 'FAILED' ) );

		// Verify the option was saved by reading it back
		$saved_progress = get_option( 'brag_book_gallery_detailed_progress', false );
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
	 * @since 3.0.0
	 * @param string $step_message Current step being performed
	 */
	private function update_step_progress( string $step_message ): void {
		// Get current progress data
		$current_progress = get_option( 'brag_book_gallery_detailed_progress', [] );

		// Update only the current step and timestamp
		$current_progress['current_step'] = $step_message;
		$current_progress['updated_at'] = current_time( 'mysql' );

		// Save without autoload for performance
		update_option( 'brag_book_gallery_detailed_progress', $current_progress, false );

		// Clear cache to ensure fresh data
		wp_cache_delete( 'brag_book_gallery_detailed_progress', 'options' );

		error_log( "BRAG book Gallery Sync: Step Update - {$step_message}" );
	}

	/**
	 * Legacy progress update method for backward compatibility
	 *
	 * @since 3.0.0
	 * @param int    $processed Number of cases processed
	 * @param int    $total     Total cases to process
	 * @param string $message   Current step message
	 */
	private function update_case_progress( int $processed, int $total, string $message ): void {
		if ( $total <= 0 ) {
			return;
		}

		// Calculate percentage with stage 2 starting at 95%
		$case_percentage = ( $processed / $total ) * 100;
		$overall_percentage = 95 + ( $case_percentage * 0.05 ); // Stage 2 takes remaining 5%

		// Create progress data similar to sync manager format
		$progress = [
			'total' => $total,
			'processed' => $processed,
			'percentage' => round( $overall_percentage, 2 ),
			'current_step' => $message,
			'stage' => 'cases',
			'updated_at' => current_time( 'mysql' ),
		];

		// Store progress in WordPress option for AJAX polling
		update_option( 'brag_book_gallery_case_progress', $progress, false );

		error_log( "BRAG book Gallery Sync: Progress update - {$overall_percentage}% ({$processed}/{$total}): {$message}" );
	}

	/**
	 * Ensure the procedures taxonomy is registered before sync operations
	 *
	 * @since 3.0.0
	 * @throws Exception If taxonomy cannot be registered
	 */
	private function ensure_taxonomy_registered(): void {
		// Check if taxonomy is already registered
		if ( taxonomy_exists( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			error_log( 'BRAG book Gallery Sync: ✓ Procedures taxonomy already registered' );
			return;
		}

		error_log( 'BRAG book Gallery Sync: Procedures taxonomy not found, forcing registration...' );

		// Force taxonomy registration by creating a temporary Taxonomies instance
		// This ensures the taxonomy is available even if init hook hasn't run yet
		$taxonomies = new Taxonomies();
		$taxonomies->register_taxonomies();

		// Verify it was registered
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_PROCEDURES ) ) {
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
	 * @since 3.3.0
	 * @return void
	 */
	private function ensure_favorites_page_exists(): void {
		error_log( 'BRAG book Gallery Sync: Checking for My Favorites page...' );

		// Get the gallery slug
		$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		$gallery_slug = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;

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
			'post_content' => '[brag_book_gallery_favorites]',
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
}