<?php
/**
 * Chunked Data Sync Class
 *
 * Handles stage-based synchronization of data from the BRAG book API.
 * Stage 1: Fetch sidebar data and process procedures
 * Stage 2: Build case ID manifest
 * Stage 3: Process cases (future)
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      3.3.0
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Core\Trait_Api;
use BRAGBookGallery\Includes\Data\Database;
use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Chunked Data Sync Class
 *
 * @since 3.3.0
 */
class Chunked_Data_Sync {
	use Trait_Api;

	/**
	 * Sync session ID
	 *
	 * @var string
	 */
	private string $sync_session_id;

	/**
	 * Database instance for sync registry operations
	 *
	 * @since 4.3.3
	 * @var Database|null
	 */
	private ?Database $database = null;

	/**
	 * Sync data directory path
	 *
	 * @var string
	 */
	private string $sync_dir;

	/**
	 * Today's date string (YYYY-MM-DD)
	 *
	 * @var string
	 */
	private string $date_string;

	/**
	 * Cached API token
	 *
	 * @var string
	 */
	private string $api_token = '';

	/**
	 * Cached website property ID
	 *
	 * @var int
	 */
	private int $property_id = 0;

	/**
	 * Cached Endpoints instance
	 *
	 * @var \BRAGBookGallery\Includes\REST\Endpoints|null
	 */
	private ?\BRAGBookGallery\Includes\REST\Endpoints $endpoints_instance = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Check for existing session in Stage 3 state (batched persistence)
		$state = get_option( 'brag_book_stage3_state', null );
		if ( $state && ! empty( $state['sync_session_id'] ) ) {
			$this->sync_session_id = $state['sync_session_id'];
		} else {
			$this->sync_session_id = uniqid( 'chunked_sync_', true );
		}

		$this->date_string = date( 'Y-m-d' );
		$this->init_sync_directory();

		// Initialize database dependency
		try {
			$setup = Setup::get_instance();
			$db    = $setup->get_service( 'database' );
			if ( $db instanceof Database ) {
				$this->database = $db;
			}
		} catch ( \Exception $e ) {
			$this->debug_log( 'Chunked Sync: Could not initialize database for registry: ' . $e->getMessage() );
		}

		// Cache API token and property ID to avoid repeated DB reads per-call
		$tokens              = get_option( 'brag_book_gallery_api_token', [] );
		$this->api_token     = is_array( $tokens ) ? ( $tokens[0] ?? '' ) : (string) $tokens;
		$ids                 = get_option( 'brag_book_gallery_website_property_id', [] );
		$this->property_id   = is_array( $ids ) ? absint( $ids[0] ?? 0 ) : absint( $ids );
		$this->endpoints_instance = new \BRAGBookGallery\Includes\REST\Endpoints();
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
	 * Get API token for registry operations
	 *
	 * @since 4.3.3
	 * @return string
	 */
	private function get_api_token(): string {
		return $this->api_token;
	}

	/**
	 * Get website property ID for registry operations
	 *
	 * @since 4.3.3
	 * @return int
	 */
	private function get_property_id(): int {
		return $this->property_id;
	}

	/**
	 * Initialize sync directory
	 *
	 * @return void
	 */
	private function init_sync_directory(): void {
		$upload_dir     = wp_upload_dir();
		$this->sync_dir = $upload_dir['basedir'] . '/brag-book-gallery-sync';

		// Create directory if it doesn't exist
		if ( ! file_exists( $this->sync_dir ) ) {
			wp_mkdir_p( $this->sync_dir );

			// Add .htaccess for security - allow JSON files but block directory listing
			$htaccess_file = $this->sync_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				$htaccess_content = "Options -Indexes\n";
				$htaccess_content .= "Deny from all\n";
				file_put_contents( $htaccess_file, $htaccess_content );
			}
		}

		$this->debug_log( 'Chunked Sync: Initialized sync directory: ' . $this->sync_dir );
	}

	/**
	 * Get sync data file path for today
	 *
	 * @return string
	 */
	private function get_sync_data_file(): string {
		return $this->sync_dir . '/sync-data-' . $this->date_string . '.json';
	}

	/**
	 * Get manifest file path for today
	 *
	 * @return string
	 */
	private function get_manifest_file(): string {
		return $this->sync_dir . '/manifest-' . $this->date_string . '.json';
	}

	/**
	 * Check if sync data file exists for today
	 *
	 * @return bool
	 */
	public function sync_data_exists(): bool {
		return file_exists( $this->get_sync_data_file() );
	}

	/**
	 * Check if manifest file exists for today
	 *
	 * @return bool
	 */
	public function manifest_exists(): bool {
		return file_exists( $this->get_manifest_file() );
	}

	/**
	 * Get file status for all stages
	 *
	 * @return array
	 */
	public function get_file_status(): array {
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'] . '/brag-book-gallery-sync';

		$result = array(
			'sync_data'     => array(
				'exists' => $this->sync_data_exists(),
				'path'   => $this->get_sync_data_file(),
				'date'   => $this->date_string,
			),
			'manifest'      => array(
				'exists' => $this->manifest_exists(),
				'path'   => $this->get_manifest_file(),
				'date'   => $this->date_string,
			),
			'stage3_status' => $this->get_stage3_status(),
		);

		// Add URLs for existing files
		if ( $result['sync_data']['exists'] ) {
			$result['sync_data']['url'] = $base_url . '/sync-data-' . $this->date_string . '.json';
		}

		if ( $result['manifest']['exists'] ) {
			$result['manifest']['url'] = $base_url . '/manifest-' . $this->date_string . '.json';
		}

		return $result;
	}

	/**
	 * Execute Stage 1: Fetch sidebar data and process procedures
	 *
	 * @return array Result with success status and details
	 */
	public function execute_stage_1(): array {

		try {
			// Step 1: Check if sync data file exists for today.
			if ( $this->sync_data_exists() ) {
				$sidebar_data = $this->load_sync_data();
			} else {
				$sidebar_data = $this->fetch_and_save_sidebar_data();
			}

			// Step 2: Process procedures from the data.
			$result = $this->process_procedures_from_data( $sidebar_data );

			return array(
				'success'            => true,
				'stage'              => 1,
				'file_created'       => ! $this->sync_data_exists(),
				'procedures_created' => $result['created'],
				'procedures_updated' => $result['updated'],
				'total_procedures'   => $result['total'],
				'categories_synced'  => $result['categories_synced'],
				'procedures_synced'  => $result['procedures_synced'],
				'message'            => 'Stage 1 completed: Procedures processed',
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'stage'   => 1,
				'error'   => $e->getMessage(),
				'message' => 'Stage 1 failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Fetch sidebar data from API and save to file
	 *
	 * @return array Sidebar data
	 * @throws Exception If API request fails
	 */
	private function fetch_and_save_sidebar_data(): array {

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			throw new Exception( 'No API tokens configured' );
		}

		$valid_tokens = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );

		if ( empty( $valid_tokens ) ) {
			throw new Exception( 'No valid API tokens found' );
		}

		// Build API request
		$api_base_url = $this->get_api_base_url();
		$endpoint     = '/api/plugin/v2/terms';
		$full_url     = $api_base_url . $endpoint;

		// Make GET request with Bearer token authentication
		$response = wp_remote_get(
			$full_url,
			array(
				'timeout'   => 30,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $valid_tokens[0],
					'Accept'        => 'application/json',
				),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'API request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code !== 200 ) {
			throw new Exception( 'API returned error status: ' . $response_code );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Invalid JSON response: ' . json_last_error_msg() );
		}

		if ( ! isset( $data['data']['terms'] ) || ! is_array( $data['data']['terms'] ) ) {
			throw new Exception( 'API returned unexpected response structure (missing data.terms key)' );
		}

		// Save to file
		$file_path     = $this->get_sync_data_file();
		$bytes_written = file_put_contents( $file_path, wp_json_encode( $data ) );

		if ( $bytes_written === false ) {
			throw new Exception( 'Failed to save sync data to file' );
		}

		return $data;
	}

	/**
	 * Load sync data from file
	 *
	 * @return array Sync data
	 * @throws Exception If file cannot be read
	 */
	public function load_sync_data(): array {
		$file_path = $this->get_sync_data_file();

		if ( ! file_exists( $file_path ) ) {
			throw new Exception( 'Sync data file does not exist' );
		}

		$json_content = file_get_contents( $file_path );
		if ( $json_content === false ) {
			throw new Exception( 'Failed to read sync data file' );
		}

		$data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Invalid JSON in sync data file: ' . json_last_error_msg() );
		}

		return $data;
	}

	/**
	 * Process procedures from sidebar data
	 *
	 * @param array $sidebar_data Sidebar API data
	 *
	 * @return array Processing results
	 */
	private function process_procedures_from_data( array $sidebar_data ): array {
		$categories_created  = 0;
		$categories_updated  = 0;
		$procedures_created  = 0;
		$procedures_updated  = 0;

		$terms = $sidebar_data['data']['terms'] ?? [];
		if ( empty( $terms ) ) {
			return [
				'created'             => 0,
				'updated'             => 0,
				'total'               => 0,
				'categories_synced'   => 0,
				'procedures_synced'   => 0,
			];
		}

		// Ensure taxonomy is registered
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			$this->debug_log( 'Chunked Sync: Procedures taxonomy not registered, attempting to register' );
			$taxonomies = new Taxonomies();
			$taxonomies->register_procedures_taxonomy();
		}

		foreach ( $terms as $category_index => $category ) {

			// Process parent category — pass its position in the terms array as the order.
			$parent_result = $this->create_or_update_procedure( $category, null, $category_index );

			if ( $parent_result['created'] ) {
				$categories_created++;
			} else {
				$categories_updated++;
			}

			$parent_term_id = $parent_result['term_id'];

			// Process child procedures — pass each procedure's position within the
			// category's procedures array as its order.
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure_index => $procedure ) {
					$child_result = $this->create_or_update_procedure( $procedure, $parent_term_id, $procedure_index );
					if ( $child_result['created'] ) {
						$procedures_created++;
					} else {
						$procedures_updated++;
					}
				}
			}
		}

		$total_categories = $categories_created + $categories_updated;
		$total_procedures = $procedures_created + $procedures_updated;

		return [
			'created'           => $categories_created + $procedures_created,
			'updated'           => $categories_updated + $procedures_updated,
			'total'             => $total_categories + $total_procedures,
			'categories_synced' => $total_categories,
			'procedures_synced' => $total_procedures,
		];
	}

	/**
	 * Create or update a procedure term
	 *
	 * @param array    $data      Procedure data
	 * @param int|null $parent_id Parent term ID
	 * @param int      $order     Position in the API terms/procedures array (0-based)
	 *
	 * @return array Operation result
	 */
	private function create_or_update_procedure( array $data, ?int $parent_id = null, int $order = 0 ): array {
		$slug = $data['slug'] ?? sanitize_title( $data['name'] );
		$name = $data['name'];

		// Set term description to shortcode for procedure view
		$term_description = '[brag_book_gallery view="procedure"]';

		// Check if term already exists
		$existing_term = get_term_by( 'slug', $slug, Taxonomies::TAXONOMY_PROCEDURES );

		if ( $existing_term ) {
			// Update existing term
			$term_id = $existing_term->term_id;
			wp_update_term( $term_id, Taxonomies::TAXONOMY_PROCEDURES, [
				'name'        => $name,
				'description' => $term_description,
				'parent'      => $parent_id ?? 0,
			] );
			$created = false;
		} else {
			// Create new term
			$inserted = wp_insert_term( $name, Taxonomies::TAXONOMY_PROCEDURES, [
				'description' => $term_description,
				'slug'        => $slug,
				'parent'      => $parent_id ?? 0,
			] );

			if ( is_wp_error( $inserted ) ) {
				throw new Exception( 'Failed to create term: ' . $inserted->get_error_message() );
			}

			$term_id = $inserted['term_id'];
			$created = true;
		}

		// Update term meta
		if ( ! empty( $data['id'] ) ) {
			update_term_meta( $term_id, 'procedure_id', $data['id'] );
		}

		$nudity = isset( $data['nudity'] ) && $data['nudity'] ? 'true' : 'false';
		update_term_meta( $term_id, 'nudity', $nudity );

		if ( isset( $data['description'] ) ) {
			update_term_meta( $term_id, 'brag_book_gallery_details', wp_kses_post( $data['description'] ) );
		}

		// Handle both category totalCases and procedure caseCount
		$case_count = $data['totalCases'] ?? $data['caseCount'] ?? null;
		if ( $case_count !== null ) {
			update_term_meta( $term_id, 'total_cases', absint( $case_count ) );
		}

		// Store the display order from the API response so the sidebar, gallery,
		// and tiles views sort terms in the same order as the BRAGBook application.
		update_term_meta( $term_id, 'procedure_order', $order );

		// Register in sync registry
		if ( $this->database && ! empty( $data['id'] ) ) {
			$this->database->upsert_registry_item(
				'procedure',
				(int) $data['id'],
				$term_id,
				'term',
				$this->get_api_token(),
				$this->get_property_id(),
				$this->sync_session_id
			);
		}

		return [
			'term_id' => $term_id,
			'name'    => $name,
			'slug'    => $slug,
			'created' => $created,
		];
	}

	/**
	 * Execute Stage 2: Build case ID manifest
	 *
	 * @return array Result with success status and details
	 */
	public function execute_stage_2( bool $tablet = false ): array {
		$this->debug_log( 'Chunked Sync: Starting Stage 2 - Build case ID manifest' . ( $tablet ? ' (tablet mode)' : '' ) );

		try {
			// Check if sync data exists (required for Stage 2)
			if ( ! $this->sync_data_exists() ) {
				throw new Exception( 'Stage 1 must be completed first (sync data file not found)' );
			}

			// Check if manifest already exists for today
			if ( $this->manifest_exists() ) {
				$this->debug_log( 'Chunked Sync: Manifest file already exists for today' );
				$manifest   = $this->load_manifest();
				$case_count = $this->count_cases_in_manifest( $manifest );

				return array(
					'success'         => true,
					'stage'           => 2,
					'file_exists'     => true,
					'procedure_count' => count( $manifest ),
					'case_count'      => $case_count,
					'message'         => 'Manifest already exists for today',
				);
			}

			// Load sync data
			$sidebar_data = $this->load_sync_data();

			// Build manifest
			$manifest = $this->build_case_manifest( $sidebar_data, $tablet );

			// Save manifest
			$this->save_manifest( $manifest );

			$case_count = $this->count_cases_in_manifest( $manifest );

			// Clear progress tracking
			$this->clear_stage_progress();

			$this->debug_log( 'Chunked Sync: Stage 2 completed successfully' );

			return [
				'success'         => true,
				'stage'           => 2,
				'file_created'    => true,
				'procedure_count' => count( $manifest ),
				'case_count'      => $case_count,
				'message'         => 'Stage 2 completed: Manifest created',
			];

		} catch ( Exception $e ) {
			// Clear progress on error
			$this->clear_stage_progress();

			$this->debug_log( 'Chunked Sync: Stage 2 failed: ' . $e->getMessage() );

			return [
				'success' => false,
				'stage'   => 2,
				'error'   => $e->getMessage(),
				'message' => 'Stage 2 failed: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Build case manifest from sidebar data
	 *
	 * @param array $sidebar_data Sidebar data
	 *
	 * @return array Manifest data
	 */
	private function build_case_manifest( array $sidebar_data, bool $tablet = false ): array {
		$manifest = [];

		if ( empty( $sidebar_data['data']['terms'] ) ) {
			$this->debug_log( 'Chunked Sync: No terms found in sidebar_data' );

			return $manifest;
		}

		// Extract all procedures with their IDs and case counts
		$procedures       = $this->extract_procedures_from_sidebar( $sidebar_data );
		$total_procedures = count( $procedures );

		$this->debug_log( 'Chunked Sync: Found ' . $total_procedures . ' procedures to process for manifest' );

		// Update progress tracking
		$this->update_stage_progress( 0, $total_procedures, 'Starting manifest creation...' );

		$processed         = 0;
		$total_cases_found = 0;

		foreach ( $procedures as $procedure ) {
			if ( empty( $procedure['ids'] ) || ( $procedure['caseCount'] ?? 0 ) <= 0 ) {
				$processed ++;
				continue;
			}

			$procedure_name = $procedure['name'];
			$procedure_ids  = $procedure['ids'];
			$case_count     = $procedure['caseCount'];

			$this->debug_log( "Chunked Sync: Processing procedure '{$procedure_name}' with {$case_count} expected cases" );

			// Update progress
			$this->update_stage_progress( $processed, $total_procedures, "Processing: {$procedure_name}" );

			// Process each procedure ID
			foreach ( $procedure_ids as $procedure_id ) {
				// Skip invalid procedure IDs (0, null, empty)
				if ( empty( $procedure_id ) || $procedure_id === 0 || $procedure_id === '0' ) {
					$this->debug_log( "Chunked Sync: Skipping invalid procedure ID: " . var_export( $procedure_id, true ) . " for procedure '{$procedure_name}'" );
					continue;
				}

				try {
					$case_ids = $this->fetch_all_case_ids_for_procedure( intval( $procedure_id ), $tablet );

					if ( ! empty( $case_ids ) ) {
						// Ensure case_ids is a sequential array of IDs, not an associative array
						if ( is_array( $case_ids ) ) {
							// If it's an associative array, extract just the values
							$case_ids = array_values( $case_ids );
							// Ensure all values are integers
							$case_ids = array_map( 'intval', $case_ids );
						}

						$manifest[ $procedure_id ] = $case_ids;
						$case_count_actual         = count( $case_ids );
						$total_cases_found         += $case_count_actual;
						$this->debug_log( "Chunked Sync: Added {$case_count_actual} cases for procedure ID {$procedure_id} ('{$procedure_name}')" );
					} else {
						$this->debug_log( "Chunked Sync: No cases found for procedure ID {$procedure_id} ('{$procedure_name}')" );
					}
				} catch ( Exception $e ) {
					$this->debug_log( "Chunked Sync: ERROR fetching cases for procedure ID {$procedure_id}: " . $e->getMessage() );
					// Continue with next procedure instead of failing completely
				}
			}

			$processed ++;

			// Update progress with case count
			$this->update_stage_progress( $processed, $total_procedures, "Processed: {$procedure_name} - Total cases so far: {$total_cases_found}" );
		}

		$this->debug_log( "Chunked Sync: Manifest creation complete. Total procedures: " . count( $manifest ) . ", Total cases: {$total_cases_found}" );

		return $manifest;
	}

	/**
	 * Extract procedures from sidebar data
	 *
	 * @param array $sidebar_data Sidebar data
	 *
	 * @return array Procedures with IDs and case counts
	 */
	private function extract_procedures_from_sidebar( array $sidebar_data ): array {
		$procedures = [];
		$terms      = $sidebar_data['data']['terms'] ?? [];

		foreach ( $terms as $category ) {
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( ! empty( $procedure['id'] ) && ! empty( $procedure['caseCount'] ) ) {
						$procedures[] = [
							'name'      => $procedure['name'] ?? 'Unknown',
							'ids'       => [ (int) $procedure['id'] ],
							'caseCount' => $procedure['caseCount'],
						];

						$this->debug_log( "Chunked Sync: Procedure '{$procedure['name']}' has ID: {$procedure['id']}" );
					}
				}
			}
		}

		return $procedures;
	}

	/**
	 * Fetch all case IDs for a specific procedure
	 *
	 * @param int $procedure_id Procedure ID
	 *
	 * @return array Case IDs
	 */
	private function fetch_all_case_ids_for_procedure( int $procedure_id, bool $tablet = false ): array {
		$all_case_ids = [];
		$page         = 1;
		$limit        = 50;

		$this->debug_log( "Chunked Sync: Fetching case IDs for procedure {$procedure_id}" );

		while ( true ) {
			try {
				$result = $this->fetch_case_ids_with_count( $procedure_id, $page, $limit, $tablet );

				if ( empty( $result['case_ids'] ) ) {
					break;
				}

				$all_case_ids = array_merge( $all_case_ids, $result['case_ids'] );
				$this->debug_log( "Chunked Sync: Page {$page} returned " . count( $result['case_ids'] ) . " case IDs" );

				// Check pagination metadata to see if there are more pages
				$pagination = $result['pagination'];
				if ( ! $pagination || ! ( $pagination['hasNext'] ?? false ) ) {
					break;
				}

				$page++;

			} catch ( Exception $e ) {
				$this->debug_log( "Chunked Sync: Failed to fetch page {$page} for procedure {$procedure_id}: " . $e->getMessage() );
				break;
			}
		}

		// Remove duplicates and ensure sequential array
		$all_case_ids = array_unique( $all_case_ids );
		$all_case_ids = array_values( $all_case_ids );

		$this->debug_log( "Chunked Sync: Total of " . count( $all_case_ids ) . " unique case IDs for procedure {$procedure_id}" );

		return $all_case_ids;
	}

	/**
	 * Fetch case IDs for a procedure with pagination
	 *
	 * @since 3.3.0
	 * @param int $procedure_id Procedure ID
	 * @param int $page Page number for pagination
	 * @param int $limit Number of items per page
	 * @return array Case IDs and pagination info
	 * @throws Exception If API request fails
	 */
	private function fetch_case_ids_with_count( int $procedure_id, int $page = 1, int $limit = 50, bool $tablet = false ): array {
		if ( empty( $this->api_token ) || $this->property_id <= 0 ) {
			throw new Exception( 'Invalid API configuration' );
		}

		$response = $this->endpoints_instance->get_cases_v2(
			$this->api_token,
			$this->property_id,
			$procedure_id,
			$page,
			$limit,
			null,
			null,
			null,
			$tablet
		);

		if ( ! $response || ! isset( $response['data']['cases'] ) ) {
			return [ 'case_ids' => [], 'pagination' => null ];
		}

		// Extract case IDs (procedure-specific 'id' field, not 'caseId')
		$case_ids = [];
		foreach ( $response['data']['cases'] as $case ) {
			if ( isset( $case['id'] ) ) {
				$case_ids[] = intval( $case['id'] );
			}
		}

		return [
			'case_ids'   => $case_ids,
			'pagination' => $response['data']['pagination'] ?? null,
		];
	}

	/**
	 * Save manifest to file
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return void
	 * @throws Exception If save fails
	 */
	private function save_manifest( array $manifest ): void {
		$file_path     = $this->get_manifest_file();
		$bytes_written = file_put_contents( $file_path, wp_json_encode( $manifest ) );

		if ( $bytes_written === false ) {
			throw new Exception( 'Failed to save manifest to file' );
		}

		$this->debug_log( 'Chunked Sync: Saved manifest to file: ' . basename( $file_path ) );
	}

	/**
	 * Load manifest from file
	 *
	 * @return array Manifest data
	 * @throws Exception If file cannot be read
	 */
	public function load_manifest(): array {
		$file_path = $this->get_manifest_file();

		if ( ! file_exists( $file_path ) ) {
			throw new Exception( 'Manifest file does not exist' );
		}

		$json_content = file_get_contents( $file_path );
		if ( $json_content === false ) {
			throw new Exception( 'Failed to read manifest file' );
		}

		$data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Invalid JSON in manifest file: ' . json_last_error_msg() );
		}

		return $data;
	}

	/**
	 * Count total cases in manifest
	 *
	 * @param array $manifest Manifest data
	 *
	 * @return int Total case count
	 */
	private function count_cases_in_manifest( array $manifest ): int {
		$count = 0;
		foreach ( $manifest as $case_ids ) {
			$count += count( $case_ids );
		}

		return $count;
	}

	/**
	 * Get manifest preview (for UI display)
	 *
	 * @param int $limit Maximum number of procedures to show
	 *
	 * @return array Preview data
	 */
	public function get_manifest_preview( int $limit = 5 ): array {
		try {
			if ( ! $this->manifest_exists() ) {
				return [
					'exists'  => false,
					'message' => 'No manifest file exists for today',
				];
			}

			$manifest         = $this->load_manifest();
			$total_procedures = count( $manifest );
			$total_cases      = $this->count_cases_in_manifest( $manifest );

			// Get preview of first few procedures
			$preview = [];
			$count   = 0;
			foreach ( $manifest as $procedure_id => $case_ids ) {
				if ( $count >= $limit ) {
					break;
				}
				$preview[ $procedure_id ] = [
					'case_count' => count( $case_ids ),
					'sample_ids' => array_slice( $case_ids, 0, 3 ),
				];
				$count ++;
			}

			return [
				'exists'           => true,
				'date'             => $this->date_string,
				'total_procedures' => $total_procedures,
				'total_cases'      => $total_cases,
				'preview'          => $preview,
			];

		} catch ( Exception $e ) {
			return [
				'exists' => false,
				'error'  => $e->getMessage(),
			];
		}
	}

	/**
	 * Execute Stage 3: Process cases from manifest
	 *
	 * @param int $batch_size Number of cases to process per chunk (default 20)
	 *
	 * @return array Result with success status and details
	 */
	public function execute_stage_3( int $batch_size = 10 ): array {
		$this->debug_log( 'Chunked Sync: Starting Stage 3 - Process cases from manifest (batch size: ' . $batch_size . ')' );

		// Increase memory limit and time limit for processing
		@ini_set( 'memory_limit', '512M' );
		@set_time_limit( 180 ); // 3 minutes per chunk

		try {
			// Check if manifest exists (required for Stage 3)
			if ( ! $this->manifest_exists() ) {
				throw new Exception( 'Stage 2 must be completed first (manifest file not found)' );
			}

			// Load the manifest
			$manifest = $this->load_manifest();
			if ( empty( $manifest ) ) {
				throw new Exception( 'Manifest is empty - no cases to process' );
			}

			// Check for existing state to resume from
			$state = get_option( 'brag_book_stage3_state', null );
			if ( $state ) {
				$this->debug_log( 'Chunked Sync: Resuming Stage 3 from saved state' );
				$result = $this->process_batch_from_state( $state, $manifest, $batch_size );
			} else {
				// Start fresh processing
				$this->debug_log( 'Chunked Sync: Starting fresh Stage 3 processing' );
				$result = $this->process_batch_from_manifest( $manifest, $batch_size );
			}

			// Check if we need to continue processing
			if ( $result['needs_continue'] ?? false ) {
				$this->debug_log( 'Chunked Sync: Stage 3 needs to continue - ' . $result['processed_cases'] . '/' . $result['total_cases'] . ' processed' );
				return [
					'success'         => true,
					'stage'           => 3,
					'needs_continue'  => true,
					'message'         => sprintf(
						'Processing batch: %d/%d cases completed',
						$result['processed_cases'],
						$result['total_cases']
					),
					'created_posts'   => $result['created_posts'],
					'updated_posts'   => $result['updated_posts'],
					'failed_cases'    => $result['failed_cases'],
					'processed_cases' => $result['processed_cases'],
					'total_cases'     => $result['total_cases'],
					'progress'        => round( ( $result['processed_cases'] / $result['total_cases'] ) * 100 ),
				];
			}

			// Processing complete
			$this->debug_log( 'Chunked Sync: Stage 3 completed successfully' );

			// Store completion status for display
			$completion_status = [
				'completed_at'    => current_time( 'mysql' ),
				'total_cases'     => $result['total_cases'] ?? 0,
				'processed_cases' => $result['processed_cases'] ?? 0,
				'created_posts'   => $result['created_posts'] ?? 0,
				'updated_posts'   => $result['updated_posts'] ?? 0,
				'failed_cases'    => $result['failed_cases'] ?? 0,
				'errors'          => ! empty( $result['errors'] ) ? array_slice( $result['errors'], 0, 5 ) : [],
			];
			update_option( 'brag_book_stage3_last_run', $completion_status, false );

			// Clear any saved state on completion
			delete_option( 'brag_book_stage3_state' );

			// Clear progress tracking
			$this->clear_stage_progress();

			// Force garbage collection before returning to free memory
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}

			// Return minimal response to avoid memory issues
			return [
				'success'         => true,
				'stage'           => 3,
				'needs_continue'  => false,
				'message'         => sprintf(
					'Stage 3 completed: %d cases processed (%d created, %d updated, %d failed)',
					$result['processed_cases'] ?? 0,
					$result['created_posts'] ?? 0,
					$result['updated_posts'] ?? 0,
					$result['failed_cases'] ?? 0
				),
				'created_posts'   => $result['created_posts'] ?? 0,
				'updated_posts'   => $result['updated_posts'] ?? 0,
				'failed_cases'    => $result['failed_cases'] ?? 0,
				'processed_cases' => $result['processed_cases'] ?? 0,
				'total_cases'     => $result['total_cases'] ?? 0,
			];

		} catch ( Exception $e ) {
			// Clear progress on error
			$this->clear_stage_progress();
			delete_option( 'brag_book_stage3_state' );

			$this->debug_log( 'Chunked Sync: Stage 3 failed: ' . $e->getMessage() );

			return [
				'success' => false,
				'stage'   => 3,
				'error'   => $e->getMessage(),
				'message' => 'Stage 3 failed: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Update stage progress for frontend polling
	 *
	 * @param int $current Current item being processed
	 * @param int $total Total items to process
	 * @param string $message Progress message
	 *
	 * @return void
	 */
	private function update_stage_progress( int $current, int $total, string $message = '' ): void {
		$progress = [
			'current'    => $current,
			'total'      => $total,
			'percentage' => $total > 0 ? round( ( $current / $total ) * 100 ) : 0,
			'message'    => $message,
			'timestamp'  => time(),
		];

		// Store in transient for frontend polling
		set_transient( 'brag_book_stage_progress', $progress, 300 ); // 5 minute expiry

		// Also log for debugging
		if ( ! empty( $message ) ) {
			$this->debug_log( "Chunked Sync Progress: [{$current}/{$total}] {$message}" );
		}
	}

	/**
	 * Get current stage progress
	 *
	 * @return array|false Progress data or false if not available
	 */
	public function get_stage_progress() {
		return get_transient( 'brag_book_stage_progress' );
	}

	/**
	 * Clear stage progress
	 *
	 * @return void
	 */
	public function clear_stage_progress(): void {
		delete_transient( 'brag_book_stage_progress' );
	}

	/**
	 * Log a message only when debug mode is enabled
	 *
	 * @param string $message Message to log
	 *
	 * @return void
	 */
	private function debug_log( string $message ): void {
		if ( get_option( 'brag_book_gallery_debug_mode' ) === 'yes' ) {
			error_log( $message );
		}
	}

	/**
	 * Process a batch of cases from manifest (fresh start)
	 *
	 * @param array $manifest Manifest data with procedure IDs and case IDs
	 * @param int $batch_size Number of cases to process
	 *
	 * @return array Processing results with needs_continue flag
	 */
	private function process_batch_from_manifest( array $manifest, int $batch_size ): array {
		// Calculate total cases
		$total_cases = 0;
		foreach ( $manifest as $case_ids ) {
			$total_cases += count( $case_ids );
		}

		$this->debug_log( "Chunked Sync: Processing first batch of {$batch_size} cases from total {$total_cases}" );

		// Initialize state
		$state = [
			'total_cases'     => $total_cases,
			'processed_cases' => 0,
			'created_posts'   => 0,
			'updated_posts'   => 0,
			'failed_cases'    => 0,
			'errors'          => [],
			'procedure_index' => 0,
			'case_index'      => 0,
			'sync_session_id' => $this->sync_session_id,
		];

		return $this->process_batch_from_state( $state, $manifest, $batch_size );
	}

	/**
	 * Process a batch of cases from saved state (resume)
	 *
	 * @param array $state Saved processing state
	 * @param array $manifest Manifest data
	 * @param int $batch_size Number of cases to process in this batch
	 *
	 * @return array Processing results with needs_continue flag
	 */
	private function process_batch_from_state( array $state, array $manifest, int $batch_size ): array {
		$processed_in_batch = 0;
		$procedure_keys     = array_keys( $manifest );
		$num_procedure_keys = count( $procedure_keys );

		// Ensure total_cases is set (calculate if missing)
		if ( empty( $state['total_cases'] ) ) {
			$total_cases = 0;
			foreach ( $manifest as $case_ids ) {
				$total_cases += count( $case_ids );
			}
			$state['total_cases'] = $total_cases;
		}

		// Ensure counters are initialized
		$state['processed_cases'] = $state['processed_cases'] ?? 0;
		$state['created_posts']   = $state['created_posts'] ?? 0;
		$state['updated_posts']   = $state['updated_posts'] ?? 0;
		$state['failed_cases']    = $state['failed_cases'] ?? 0;
		$state['errors']          = $state['errors'] ?? [];

		// Resume from saved position
		$procedure_index = $state['procedure_index'] ?? 0;
		$case_index      = $state['case_index'] ?? 0;

		$this->debug_log( "Chunked Sync: Resuming from procedure index {$procedure_index}, case index {$case_index}" );
		$this->debug_log( "Chunked Sync: Progress so far: {$state['processed_cases']}/{$state['total_cases']} cases" );

		// Update progress
		$this->update_stage_progress(
			(int) $state['processed_cases'],
			(int) $state['total_cases'],
			'Processing batch of cases...'
		);

		// Process cases until batch is full
		while ( $processed_in_batch < $batch_size && $procedure_index < $num_procedure_keys ) {
			$procedure_id = $procedure_keys[ $procedure_index ];
			$case_ids     = $manifest[ $procedure_id ];
			$num_case_ids = count( $case_ids );

			// Get all procedure terms for this API ID — the same procedure can appear
			// in multiple categories (each with its own slug), so we collect all terms
			// and assign the case to every one of them.
			$procedure_terms = $this->get_all_procedure_terms_by_api_id( intval( $procedure_id ) );
			if ( empty( $procedure_terms ) ) {
				$this->debug_log( "Chunked Sync: WARNING - No procedure terms found for API ID {$procedure_id}, skipping" );
				$procedure_index ++;
				$case_index = 0;
				continue;
			}

			// Build case order list when we first start processing this procedure
			if ( $case_index === 0 ) {
				// We'll store the full order after processing all cases for this procedure
				$state['current_procedure_case_order'] = [];
			}

			// Process cases for this procedure
			while ( $case_index < $num_case_ids && $processed_in_batch < $batch_size ) {
				$case_id = $case_ids[ $case_index ];

				try {
					// Fetch and process case
					$case_details = $this->fetch_case_details( intval( $case_id ), intval( $procedure_id ) );
					if ( ! $case_details ) {
						throw new Exception( "Failed to fetch details for case {$case_id}" );
					}

					// Create or update post
					$result = $this->create_or_update_case_post( $case_details, $procedure_terms, $case_index );

					if ( $result['created'] ) {
						$state['created_posts'] ++;
					} else {
						$state['updated_posts'] ++;
					}

					// Add to case order list (WordPress ID + API ID)
					if ( ! empty( $result['post_id'] ) ) {
						$state['current_procedure_case_order'][] = [
							'wp_id'  => $result['post_id'],
							'api_id' => $case_id,
						];
					}

					// Update progress
					$state['processed_cases'] ++;
					$this->update_stage_progress(
						$state['processed_cases'],
						$state['total_cases'],
						"Processed case {$case_id} ({$state['processed_cases']}/{$state['total_cases']})"
					);

				} catch ( Exception $e ) {
					$state['failed_cases'] ++;
					$state['errors'][] = "Failed to process case {$case_id}: " . $e->getMessage();
					$this->debug_log( "Chunked Sync: ERROR - Failed to process case {$case_id}: " . $e->getMessage() );
					$state['processed_cases'] ++;
				}

				$case_index ++;
				$processed_in_batch ++;

			}

			// Move to next procedure if we finished this one
			if ( $case_index >= $num_case_ids ) {
				// Store the case order for this completed procedure
				if ( ! empty( $state['current_procedure_case_order'] ) ) {
					foreach ( $procedure_terms as $term ) {
						$this->store_procedure_case_order( $term->term_id, $state['current_procedure_case_order'] );
					}
					$term_ids_str = implode( ', ', array_map( fn( $t ) => $t->term_id, $procedure_terms ) );
					$this->debug_log( "Chunked Sync: Stored case ordering for procedure {$procedure_id} (terms: {$term_ids_str}) - " . count( $state['current_procedure_case_order'] ) . " cases" );
				}

				$procedure_index ++;
				$case_index = 0;
				$state['current_procedure_case_order'] = []; // Reset for next procedure
			}
		}

		// Save state for next batch
		$state['procedure_index'] = $procedure_index;
		$state['case_index']      = $case_index;

		// Check if we need to continue
		// We're done if we've reached the end of all procedures OR if we've processed all cases
		$reached_end    = $procedure_index >= $num_procedure_keys;
		$needs_continue = ! $reached_end && ( $state['processed_cases'] < $state['total_cases'] );

		if ( $needs_continue ) {
			// Save state for resumption — omit manifest as it's loaded from file
			unset( $state['manifest'] );
			update_option( 'brag_book_stage3_state', $state, false );
			$this->debug_log( "Chunked Sync: Saved state - {$state['processed_cases']}/{$state['total_cases']} processed, continuing..." );
		} else {
			// Log completion or end of available data
			if ( $reached_end ) {
				$this->debug_log( "Chunked Sync: Reached end of manifest - {$state['processed_cases']}/{$state['total_cases']} processed" );
				if ( $state['processed_cases'] < $state['total_cases'] ) {
					$this->debug_log( "Chunked Sync: WARNING - Expected {$state['total_cases']} cases but only found {$state['processed_cases']} in manifest" );
				}
			} else {
				$this->debug_log( "Chunked Sync: All cases processed - {$state['processed_cases']}/{$state['total_cases']}" );
			}
		}

		// Force garbage collection
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		return [
			'needs_continue'  => $needs_continue,
			'total_cases'     => $state['total_cases'],
			'processed_cases' => $state['processed_cases'],
			'created_posts'   => $state['created_posts'],
			'updated_posts'   => $state['updated_posts'],
			'failed_cases'    => $state['failed_cases'],
			'errors'          => $state['errors'],
		];
	}


	/**
	 * Fetch case details from API
	 *
	 * @since 3.3.0
	 * @param int      $case_id Case ID to fetch
	 * @param int|null $procedure_id Optional procedure ID
	 * @return array|null Normalized case data or null on failure
	 */
	private function fetch_case_details( int $case_id, ?int $procedure_id = null ): ?array {
		if ( empty( $this->api_token ) || $this->property_id <= 0 ) {
			$this->debug_log( 'Chunked Sync: Invalid API configuration for case fetch' );
			return null;
		}

		$response = $this->endpoints_instance->get_case_detail_v2(
			$this->api_token,
			$case_id,
			$this->property_id,
			$procedure_id
		);

		if ( ! $response || ! isset( $response['data']['case'] ) ) {
			$this->debug_log( "Chunked Sync: Failed to fetch case {$case_id}" );
			return null;
		}

		$this->debug_log( "Chunked Sync: Successfully fetched case {$case_id}" );

		// Normalize v2 data to v1 format
		return $this->normalize_v2_to_v1( $response['data']['case'] );
	}

	/**
	 * Normalize v2 API data to v1 format for backward compatibility
	 *
	 * Converts the v2 nested data structure to the flat v1 format expected
	 * by the Post_Types::save_api_response_data() method.
	 *
	 * @since 3.3.0
	 * @param array $v2_data v2 API case data
	 * @return array Normalized data in v1 format
	 */
	private function normalize_v2_to_v1( array $v2_data ): array {
		$case_id = $v2_data['caseId'] ?? 'unknown';

		// DEBUG: Log incoming v2 data structure
		$this->debug_log( "=== NORMALIZATION DEBUG: Case {$case_id} ===" );
		$this->debug_log( "V2 Data Keys: " . implode( ', ', array_keys( $v2_data ) ) );
		$this->debug_log( "Has patientInfo: " . ( isset( $v2_data['patientInfo'] ) ? 'YES' : 'NO' ) );
		$this->debug_log( "Has seoInfo: " . ( isset( $v2_data['seoInfo'] ) ? 'YES' : 'NO' ) );
		$this->debug_log( "Has photoSets: " . ( isset( $v2_data['photoSets'] ) ? 'YES (' . count( $v2_data['photoSets'] ) . ' sets)' : 'NO' ) );

		if ( isset( $v2_data['photoSets'][0]['images'] ) ) {
			$first_images = $v2_data['photoSets'][0]['images'];
			$this->debug_log( "First photoSet image keys: " . implode( ', ', array_keys( $first_images ) ) );
			$this->debug_log( "Before URL: " . ( $first_images['before']['url'] ?? 'MISSING' ) );
			$this->debug_log( "After URL: " . ( $first_images['after']['url'] ?? 'MISSING' ) );
		}

		$normalized = [];

		// Basic fields (unchanged)
		$normalized['id']           = $v2_data['id'] ?? 0; // Procedure-specific case ID
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
			// Keep seoInfo at root level for slug generation
			$normalized['seoInfo'] = $v2_data['seoInfo'];
			$normalized['seoAltText'] = $v2_data['seoInfo']['altText'] ?? '';

			// Also convert to caseDetails format for backward compatibility
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
		// CRITICAL: Use *LocationUrl field names that v1 code expects
		if ( isset( $v2_data['photoSets'] ) ) {
			$normalized['photoSets'] = [];

			foreach ( $v2_data['photoSets'] as $photo_set ) {
				$images = $photo_set['images'] ?? [];

				// Convert v2 nested structure to v1 flat format
				$flat_photo_set = [
					'beforeLocationUrl'                    => $images['before']['url'] ?? '',
					'afterLocationUrl1'                    => $images['after']['url'] ?? '',
					'afterLocationUrl2'                    => $images['afterPlus']['url'] ?? '',
					'afterLocationUrl3'                    => '', // No equivalent in v2
					'postProcessedImageLocation'           => $images['sideBySide']['standard']['url'] ?? '',
					'highResPostProcessedImageLocation'    => $images['sideBySide']['highDefinition']['url'] ?? '',
					'seoAltText'                           => '', // Alt text comes from seoInfo, not photo images
					'isNude'                               => false, // Default value (not in v2 response)
				];

				$normalized['photoSets'][] = $flat_photo_set;
			}
		}

		// Procedure details (unchanged structure)
		$normalized['procedureDetails'] = $v2_data['procedureDetails'] ?? [];

		// Store v2-specific fields separately
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
		$this->debug_log( "Normalized Data Keys: " . implode( ', ', array_keys( $normalized ) ) );
		$this->debug_log( "Normalized age: " . ( $normalized['age'] ?? 'NULL' ) );
		$this->debug_log( "Normalized gender: " . ( $normalized['gender'] ?? 'NULL' ) );
		$this->debug_log( "Normalized photoSets count: " . ( isset( $normalized['photoSets'] ) ? count( $normalized['photoSets'] ) : '0' ) );
		if ( isset( $normalized['photoSets'][0] ) ) {
			$this->debug_log( "First normalized photoSet keys: " . implode( ', ', array_keys( $normalized['photoSets'][0] ) ) );
			$this->debug_log( "Normalized beforeLocationUrl: " . ( $normalized['photoSets'][0]['beforeLocationUrl'] ?? 'MISSING' ) );
			$this->debug_log( "Normalized afterLocationUrl1: " . ( $normalized['photoSets'][0]['afterLocationUrl1'] ?? 'MISSING' ) );
		}
		if ( isset( $normalized['caseDetails'][0] ) ) {
			$this->debug_log( "Normalized seoPageTitle: " . ( $normalized['caseDetails'][0]['seoPageTitle'] ?? 'MISSING' ) );
		}
		$this->debug_log( "=== END NORMALIZATION DEBUG ===" );

		return $normalized;
	}

	/**
	 * Create or update WordPress post for a case
	 *
	 * @param array $case_details Case details from API
	 * @param object $procedure_term WordPress term object for the procedure
	 * @param int $case_position Position of this case in the procedure (for ordering)
	 *
	 * @return array Result with post_id and created flag
	 */
	private function create_or_update_case_post( array $case_details, array $procedure_terms, int $case_position = 0 ): array {
		// Use the first term as the primary for title and logging; all terms receive
		// the taxonomy association so the case appears under every category-specific slug.
		$procedure_term    = $procedure_terms[0];
		$procedure_case_id = $case_details['id'] ?? '';
		$global_case_id    = $case_details['caseId'] ?? '';
		$this->debug_log( "Chunked Sync: create_or_update_case_post called for procedure case {$procedure_case_id} (global caseId: {$global_case_id})" );

		// Check if post already exists by procedure-specific case ID
		// Each procedure gets its own post, even if they share the same global caseId
		$existing_posts = get_posts( [
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'meta_key'       => 'brag_book_gallery_procedure_case_id',
			'meta_value'     => $procedure_case_id,
			'posts_per_page' => 1,
		] );

		$this->debug_log( "Chunked Sync: Found " . count( $existing_posts ) . " existing posts for procedure case {$procedure_case_id}" );

		// Generate slug from case data
		$slug = $this->generate_case_slug( $case_details );

		// HIPAA COMPLIANCE: Do NOT store case details as they may contain PHI
		// Use the main gallery shortcode which auto-detects context for case view
		$content = sprintf(
			'[brag_book_gallery]

<!-- Case ID: %d, Procedure ID: %d, Synced: %s -->',
			$case_details['caseId'] ?? 0,
			$case_details['procedureId'] ?? 0,
			current_time( 'mysql' )
		);

		$procedure_name  = $procedure_term->name ?? 'Unknown Procedure';
		$display_case_id = $case_details['caseId'] ?? 'unknown';
		$post_title      = $procedure_name . ' #' . $display_case_id;

		// Prepare meta description
		$meta_description = '';
		if ( isset( $case_details['seoInfo']['metaDescription'] ) && ! empty( $case_details['seoInfo']['metaDescription'] ) ) {
			$meta_description = wp_strip_all_tags( $case_details['seoInfo']['metaDescription'] );
		}

		if ( ! empty( $existing_posts ) ) {
			// Update existing post
			$post_id   = $existing_posts[0]->ID;
			$post_data = [
				'ID'           => $post_id,
				'post_title'   => $post_title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => isset( $case_details['draft'] ) && $case_details['draft'] ? 'draft' : 'publish',
			];
			$result    = wp_update_post( $post_data );
			$this->debug_log( "Chunked Sync: wp_update_post result for procedure case {$procedure_case_id}: " . ( $result ? "success (ID: $result)" : "failed" ) );
			if ( is_wp_error( $result ) ) {
				throw new Exception( "Failed to update post: " . $result->get_error_message() );
			}
			// Update sync timestamp and meta description
			update_post_meta( $post_id, 'brag_book_gallery_synced_at', current_time( 'mysql' ) );
			if ( ! empty( $meta_description ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
			}
			$created = false;
		} else {
			// Create new post with proper title from the start
			$post_data = [
				'post_type'    => Post_Types::POST_TYPE_CASES,
				'post_title'   => $post_title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => isset( $case_details['draft'] ) && $case_details['draft'] ? 'draft' : 'publish',
				'meta_input'   => [
					'brag_book_gallery_procedure_case_id' => $procedure_case_id,
					'brag_book_gallery_case_id'           => $global_case_id,
					'brag_book_gallery_synced_at'         => current_time( 'mysql' ),
					'_yoast_wpseo_metadesc'               => $meta_description,
				],
			];
			$post_id   = wp_insert_post( $post_data );
			$this->debug_log( "Chunked Sync: wp_insert_post result for procedure case {$procedure_case_id}: " . ( is_wp_error( $post_id ) ? "error: " . $post_id->get_error_message() : "success (ID: $post_id)" ) );
			if ( is_wp_error( $post_id ) ) {
				throw new Exception( "Failed to create post: " . $post_id->get_error_message() );
			}
			$created = true;
		}

		// Assign to procedure taxonomy FIRST — a procedure can appear in multiple
		// categories each with its own slug, so we assign all matching terms here.
		// This MUST happen before save_api_response_data() so the correct procedure is used for the title.
		$term_ids = array_map( fn( $t ) => $t->term_id, $procedure_terms );
		wp_set_object_terms( $post_id, $term_ids, Taxonomies::TAXONOMY_PROCEDURES );

		// Store additional sync-specific metadata
		update_post_meta( $post_id, 'brag_book_gallery_original_case_id', $procedure_case_id );
		update_post_meta( $post_id, '_procedure_id', $case_details['procedureId'] ?? '' );

		// Store procedure IDs in meta (for reference only, not taxonomy assignment)
		if ( isset( $case_details['procedureIds'] ) && is_array( $case_details['procedureIds'] ) ) {
			update_post_meta( $post_id, 'brag_book_gallery_procedure_ids', implode( ',', $case_details['procedureIds'] ) );
		}

		// Use the Post_Types method to save all API data properly
		// This handles all the field mapping including both new and legacy formats
		// The taxonomy assignment above ensures save_api_response_data() uses the correct procedure for the title
		// Wrap in try-catch to ensure post is not left in incomplete state
		try {
			Post_Types::save_api_response_data( $post_id, $case_details );
		} catch ( Exception $e ) {
			$this->debug_log( "Chunked Sync: Failed to save API data for procedure case {$procedure_case_id}: " . $e->getMessage() );
			// Don't throw - the post is created, just missing some metadata
			// The title is already set properly
		}

		// Store the case position for ordering within this procedure
		update_post_meta( $post_id, 'brag_book_gallery_case_order', $case_position );
		$this->debug_log( "Chunked Sync: Set case order position to {$case_position} for post {$post_id}" );

		// Assign doctor taxonomy if enabled (website property ID 111)
		$this->maybe_assign_doctor_taxonomy( $post_id, $case_details );

		// Register case in sync registry
		if ( $this->database ) {
			$this->database->upsert_registry_item(
				'case',
				(int) ( $case_details['caseId'] ?? 0 ),
				$post_id,
				'post',
				$this->get_api_token(),
				$this->get_property_id(),
				$this->sync_session_id,
				(int) $procedure_case_id
			);
		}

		return [
			'post_id' => $post_id,
			'created' => $created,
		];
	}


	/**
	 * Generate a slug for the case post
	 *
	 * Priority order:
	 * 1. seoInfo.slug (primary source)
	 * 2. procedure-specific case ID (fallback)
	 *
	 * @param array $case_data Case data from API
	 *
	 * @return string Generated slug
	 */
	private function generate_case_slug( array $case_data ): string {
		// Use procedure-specific 'id' (from manifest) as fallback - this is unique per procedure
		$procedure_case_id = $case_data['id'] ?? '';

		// Check for seoInfo.slug (primary source)
		if ( isset( $case_data['seoInfo']['slug'] ) && ! empty( $case_data['seoInfo']['slug'] ) ) {
			$slug = sanitize_title( $case_data['seoInfo']['slug'] );

			// Remove 'case-' prefix if present
			if ( str_starts_with( $slug, 'case-' ) ) {
				$slug = substr( $slug, 5 ); // Remove 'case-' (5 characters)
			}

			// Make sure it's not empty after sanitization and processing
			if ( ! empty( $slug ) ) {
				$this->debug_log( 'Chunked Sync: Using seoInfo.slug for procedure case ' . $procedure_case_id . ': ' . $slug );

				return $slug;
			}
		}

		// Fallback to procedure-specific case ID (from manifest)
		$fallback_slug = (string) $procedure_case_id;
		$this->debug_log( 'Chunked Sync: Using fallback slug (procedure case id) for case: ' . $fallback_slug );

		return $fallback_slug;
	}

	/**
	 * Get all procedure terms for a given API ID
	 *
	 * A procedure can appear in multiple categories, each with its own slug,
	 * so this returns every matching WP term rather than just the first.
	 *
	 * @param int $api_id Procedure API ID
	 *
	 * @return array Array of WP_Term objects (empty if none found)
	 */
	private function get_all_procedure_terms_by_api_id( int $api_id ): array {
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'meta_key'   => 'procedure_id',
			'meta_value' => $api_id,
			'hide_empty' => false,
		] );

		return is_array( $terms ) ? $terms : [];
	}



	/**
	 * Store case ordering for a procedure
	 *
	 * Stores the case WordPress IDs and API IDs in their API response order as term meta.
	 * This allows maintaining the same case order as displayed in the BRAG book system.
	 *
	 * @param int $term_id WordPress term ID for the procedure
	 * @param array $case_order Array of case data with 'wp_id' and 'api_id' keys
	 *
	 * @return void
	 */
	private function store_procedure_case_order( int $term_id, array $case_order ): void {
		if ( empty( $case_order ) ) {
			// Clear any existing case order list if no cases
			delete_term_meta( $term_id, 'brag_book_gallery_case_order_list' );

			return;
		}

		// Store the case order list as term meta
		update_term_meta( $term_id, 'brag_book_gallery_case_order_list', $case_order );

		$sample_cases = array_slice( $case_order, 0, 3 );
		$sample_str = implode( ', ', array_map( function( $case ) {
			return "WP:{$case['wp_id']}/API:{$case['api_id']}";
		}, $sample_cases ) );

		$this->debug_log( "Chunked Sync: Stored case order for term {$term_id} - " . count( $case_order ) . " cases. Sample: " . $sample_str . ( count( $case_order ) > 3 ? '...' : '' ) );
	}

	/**
	 * Get ordered case post IDs for a specific procedure
	 *
	 * This static method is used by shortcodes to retrieve cases in the correct order.
	 * It converts the stored case API IDs to WordPress post IDs.
	 *
	 * @param int $procedure_id Procedure API ID
	 *
	 * @return array Array of WordPress post IDs in correct order
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
			$this->debug_log( "Chunked Sync: No procedure term found for API ID {$procedure_id}" );

			return [];
		}

		$term = $terms[0];

		// Get the case order list stored on the term
		$case_ids_in_order = get_term_meta( $term->term_id, 'brag_book_gallery_case_order_list', true );
		if ( ! is_array( $case_ids_in_order ) || empty( $case_ids_in_order ) ) {
			$this->debug_log( "Chunked Sync: No case order list found for procedure {$procedure_id} (term: {$term->term_id})" );

			return [];
		}

		// Get WordPress post IDs for these case IDs in order
		$ordered_post_ids = [];
		foreach ( $case_ids_in_order as $case_data ) {
			// Handle new format with wp_id and api_id
			if ( is_array( $case_data ) && ! empty( $case_data['wp_id'] ) ) {
				$ordered_post_ids[] = $case_data['wp_id'];
				continue;
			}

			// Handle legacy format (simple case ID)
			$case_id = is_array( $case_data ) ? ( $case_data['api_id'] ?? null ) : $case_data;
			if ( empty( $case_id ) ) {
				continue;
			}

			// Try current format first (brag_book_gallery_procedure_case_id)
			$posts = get_posts( [
				'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
				'meta_key'       => 'brag_book_gallery_procedure_case_id',
				'meta_value'     => $case_id,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			] );

			// Fallback to legacy format (_case_api_id)
			if ( empty( $posts ) ) {
				$posts = get_posts( [
					'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
					'meta_key'       => 'brag_book_gallery_procedure_case_id',
					'meta_value'     => $case_id,
					'posts_per_page' => 1,
					'post_status'    => 'any',
				] );
			}

			if ( ! empty( $posts ) ) {
				$ordered_post_ids[] = $posts[0]->ID;
			}
		}

		$this->debug_log( "Chunked Sync: Returning " . count( $ordered_post_ids ) . " ordered posts for procedure {$procedure_id}" );

		return $ordered_post_ids;
	}

	/**
	 * Get Stage 3 completion status
	 *
	 * @return array|null Status data or null if not available
	 */
	public function get_stage3_status(): ?array {
		$status = get_option( 'brag_book_stage3_last_run', null );

		if ( ! $status ) {
			return null;
		}

		// Add human-readable time
		if ( isset( $status['completed_at'] ) ) {
			$status['completed_at_human'] = human_time_diff( strtotime( $status['completed_at'] ), current_time( 'timestamp' ) ) . ' ago';
		}

		return $status;
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
			$this->debug_log( 'Chunked Sync: Doctors taxonomy is enabled but not registered' );

			return;
		}

		// Extract creator/doctor data from case
		$creator = $case_data['creator'] ?? null;

		if ( empty( $creator ) || ! is_array( $creator ) ) {
			$this->debug_log( "Chunked Sync: No creator data found for post {$post_id}" );

			return;
		}

		// Get member ID - required field
		$member_id = $creator['id'] ?? null;

		if ( empty( $member_id ) ) {
			$this->debug_log( "Chunked Sync: No member ID found in creator data for post {$post_id}" );

			return;
		}

		// Create or update the doctor term
		$doctor_term = $this->create_or_update_doctor_term( $creator );

		if ( ! $doctor_term ) {
			$this->debug_log( "Chunked Sync: Failed to create/update doctor term for member {$member_id}" );

			return;
		}

		// Assign the doctor term to the case post
		$result = wp_set_object_terms( $post_id, [ $doctor_term->term_id ], Taxonomies::TAXONOMY_DOCTORS );

		if ( is_wp_error( $result ) ) {
			$this->debug_log( "Chunked Sync: Failed to assign doctor term to post {$post_id}: " . $result->get_error_message() );
		} else {
			$this->debug_log( "Chunked Sync: ✓ Assigned doctor '{$doctor_term->name}' (term ID: {$doctor_term->term_id}) to post {$post_id}" );
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
		$member_id   = absint( $creator_data['id'] ?? 0 );
		$first_name  = sanitize_text_field( $creator_data['firstName'] ?? '' );
		$last_name   = sanitize_text_field( $creator_data['lastName'] ?? '' );
		$suffix      = sanitize_text_field( $creator_data['suffix'] ?? '' );
		$profile_url = isset( $creator_data['profileLink'] ) ? esc_url_raw( $creator_data['profileLink'] ) : '';

		if ( empty( $member_id ) ) {
			return null;
		}

		// Build the doctor display name
		$name_parts  = array_filter( [ $first_name, $last_name ] );
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
				$this->debug_log( "Chunked Sync: Updated doctor term name to '{$doctor_name}' (term ID: {$term->term_id})" );
			}

			// Update term meta
			$this->update_doctor_term_meta( $term->term_id, $first_name, $last_name, $suffix, $member_id, $profile_url );

			// Register doctor in sync registry
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
				$term    = get_term( $term_id, Taxonomies::TAXONOMY_DOCTORS );

				if ( $term && ! is_wp_error( $term ) ) {
					// Update meta for the existing term
					$this->update_doctor_term_meta( $term->term_id, $first_name, $last_name, $suffix, $member_id, $profile_url );
					$this->debug_log( "Chunked Sync: Using existing doctor term '{$doctor_name}' (term ID: {$term->term_id})" );

					// Register doctor in sync registry
					$this->register_doctor_in_registry( $member_id, $term->term_id );

					return $term;
				}
			}

			$this->debug_log( "Chunked Sync: Failed to create doctor term '{$doctor_name}': " . $result->get_error_message() );

			return null;
		}

		$term_id = $result['term_id'];

		// Save term meta
		$this->update_doctor_term_meta( $term_id, $first_name, $last_name, $suffix, $member_id, $profile_url );

		// Register doctor in sync registry
		$this->register_doctor_in_registry( $member_id, $term_id );

		$this->debug_log( "Chunked Sync: ✓ Created new doctor term '{$doctor_name}' (term ID: {$term_id})" );

		return get_term( $term_id, Taxonomies::TAXONOMY_DOCTORS );
	}

	/**
	 * Register a doctor in the sync registry
	 *
	 * @since 4.3.3
	 *
	 * @param int $member_id API member ID.
	 * @param int $term_id   WordPress term ID.
	 *
	 * @return void
	 */
	private function register_doctor_in_registry( int $member_id, int $term_id ): void {
		if ( $this->database && $member_id > 0 && $term_id > 0 ) {
			$this->database->upsert_registry_item(
				'doctor',
				$member_id,
				$term_id,
				'term',
				$this->get_api_token(),
				$this->get_property_id(),
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
