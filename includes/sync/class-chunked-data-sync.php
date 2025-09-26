<?php
/**
 * Chunked Data Sync Class
 *
 * Handles stage-based synchronization of data from the BRAGBook API.
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

use BRAGBookGallery\Includes\Core\Trait_Api;
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
	 * Constructor
	 */
	public function __construct() {
		$this->sync_session_id = uniqid( 'chunked_sync_', true );
		$this->date_string     = date( 'Y-m-d' );
		$this->init_sync_directory();
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
				$htaccess_content .= "<FilesMatch \"\\.(json)$\">\n";
				$htaccess_content .= "    Order Allow,Deny\n";
				$htaccess_content .= "    Allow from all\n";
				$htaccess_content .= "</FilesMatch>\n";
				$htaccess_content .= "<FilesMatch \"^(?!.*\\.json$).*$\">\n";
				$htaccess_content .= "    Order Deny,Allow\n";
				$htaccess_content .= "    Deny from all\n";
				$htaccess_content .= "</FilesMatch>\n";
				file_put_contents( $htaccess_file, $htaccess_content );
			}
		}

		error_log( 'Chunked Sync: Initialized sync directory: ' . $this->sync_dir );
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

		$result = [
			'sync_data'     => [
				'exists' => $this->sync_data_exists(),
				'path'   => $this->get_sync_data_file(),
				'date'   => $this->date_string,
			],
			'manifest'      => [
				'exists' => $this->manifest_exists(),
				'path'   => $this->get_manifest_file(),
				'date'   => $this->date_string,
			],
			'stage3_status' => $this->get_stage3_status(),
		];

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
		error_log( 'Chunked Sync: Starting Stage 1 - Fetch sidebar data and process procedures' );

		try {
			// Step 1: Check if sync data file exists for today
			if ( $this->sync_data_exists() ) {
				error_log( 'Chunked Sync: Sync data file exists for today, using existing file' );
				$sidebar_data = $this->load_sync_data();
			} else {
				error_log( 'Chunked Sync: No sync data for today, fetching from API' );
				$sidebar_data = $this->fetch_and_save_sidebar_data();
			}

			// Step 2: Process procedures from the data
			$result = $this->process_procedures_from_data( $sidebar_data );

			error_log( 'Chunked Sync: Stage 1 completed successfully' );

			return [
				'success'            => true,
				'stage'              => 1,
				'file_created'       => ! $this->sync_data_exists(),
				'procedures_created' => $result['created'],
				'procedures_updated' => $result['updated'],
				'total_procedures'   => $result['total'],
				'message'            => 'Stage 1 completed: Procedures processed',
			];

		} catch ( Exception $e ) {
			error_log( 'Chunked Sync: Stage 1 failed: ' . $e->getMessage() );

			return [
				'success' => false,
				'stage'   => 1,
				'error'   => $e->getMessage(),
				'message' => 'Stage 1 failed: ' . $e->getMessage(),
			];
		}
	}

	/**
	 * Fetch sidebar data from API and save to file
	 *
	 * @return array Sidebar data
	 * @throws Exception If API request fails
	 */
	private function fetch_and_save_sidebar_data(): array {
		error_log( 'Chunked Sync: Fetching sidebar data from API' );

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
		$endpoint     = '/api/plugin/combine/sidebar';
		$full_url     = $api_base_url . $endpoint;

		$request_body = [
			'apiTokens' => array_values( $valid_tokens ),
		];

		error_log( 'Chunked Sync: Making API request to: ' . $full_url );

		// Make API request
		$response = wp_remote_post( $full_url, [
			'timeout'   => 30,
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'      => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );

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

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			throw new Exception( 'API returned unsuccessful response' );
		}

		// Save to file
		$file_path     = $this->get_sync_data_file();
		$bytes_written = file_put_contents( $file_path, wp_json_encode( $data, JSON_PRETTY_PRINT ) );

		if ( $bytes_written === false ) {
			throw new Exception( 'Failed to save sync data to file' );
		}

		error_log( 'Chunked Sync: Saved sync data to file: ' . basename( $file_path ) );

		return $data;
	}

	/**
	 * Load sync data from file
	 *
	 * @return array Sync data
	 * @throws Exception If file cannot be read
	 */
	private function load_sync_data(): array {
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
		$created_count = 0;
		$updated_count = 0;
		$total_count   = 0;

		if ( empty( $sidebar_data['data'] ) ) {
			return [
				'created' => 0,
				'updated' => 0,
				'total'   => 0,
			];
		}

		// Ensure taxonomy is registered
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			error_log( 'Chunked Sync: Procedures taxonomy not registered, attempting to register' );
			$taxonomies = new Taxonomies();
			$taxonomies->register_procedures_taxonomy();
		}

		foreach ( $sidebar_data['data'] as $category ) {
			// Process parent category
			$parent_result = $this->create_or_update_procedure( $category, null );
			if ( $parent_result['created'] ) {
				$created_count ++;
			} else {
				$updated_count ++;
			}
			$total_count ++;

			$parent_term_id = $parent_result['term_id'];

			// Process child procedures
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					$child_result = $this->create_or_update_procedure( $procedure, $parent_term_id );
					if ( $child_result['created'] ) {
						$created_count ++;
					} else {
						$updated_count ++;
					}
					$total_count ++;
				}
			}
		}

		return [
			'created' => $created_count,
			'updated' => $updated_count,
			'total'   => $total_count,
		];
	}

	/**
	 * Create or update a procedure term
	 *
	 * @param array $data Procedure data
	 * @param int|null $parent_id Parent term ID
	 *
	 * @return array Operation result
	 */
	private function create_or_update_procedure( array $data, ?int $parent_id = null ): array {
		$slug = $data['slugName'] ?? sanitize_title( $data['name'] );
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
		if ( ! empty( $data['ids'] ) && is_array( $data['ids'] ) ) {
			update_term_meta( $term_id, 'procedure_id', $data['ids'][0] );
		}

		$nudity = isset( $data['nudity'] ) && $data['nudity'] ? 'true' : 'false';
		update_term_meta( $term_id, 'nudity', $nudity );

		if ( isset( $data['description'] ) ) {
			update_term_meta( $term_id, 'brag_book_gallery_details', wp_kses_post( $data['description'] ) );
		}

		if ( isset( $data['totalCase'] ) ) {
			update_term_meta( $term_id, 'total_cases', absint( $data['totalCase'] ) );
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
	public function execute_stage_2(): array {
		error_log( 'Chunked Sync: Starting Stage 2 - Build case ID manifest' );

		try {
			// Check if sync data exists (required for Stage 2)
			if ( ! $this->sync_data_exists() ) {
				throw new Exception( 'Stage 1 must be completed first (sync data file not found)' );
			}

			// Check if manifest already exists for today
			if ( $this->manifest_exists() ) {
				error_log( 'Chunked Sync: Manifest file already exists for today' );
				$manifest   = $this->load_manifest();
				$case_count = $this->count_cases_in_manifest( $manifest );

				return [
					'success'         => true,
					'stage'           => 2,
					'file_exists'     => true,
					'procedure_count' => count( $manifest ),
					'case_count'      => $case_count,
					'message'         => 'Manifest already exists for today',
				];
			}

			// Load sync data
			$sidebar_data = $this->load_sync_data();

			// Build manifest
			$manifest = $this->build_case_manifest( $sidebar_data );

			// Save manifest
			$this->save_manifest( $manifest );

			$case_count = $this->count_cases_in_manifest( $manifest );

			// Clear progress tracking
			$this->clear_stage_progress();

			error_log( 'Chunked Sync: Stage 2 completed successfully' );

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

			error_log( 'Chunked Sync: Stage 2 failed: ' . $e->getMessage() );

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
	private function build_case_manifest( array $sidebar_data ): array {
		$manifest = [];

		if ( empty( $sidebar_data['data'] ) ) {
			error_log( 'Chunked Sync: No data found in sidebar_data' );

			return $manifest;
		}

		// Extract all procedures with their IDs and case counts
		$procedures       = $this->extract_procedures_from_sidebar( $sidebar_data );
		$total_procedures = count( $procedures );

		error_log( 'Chunked Sync: Found ' . $total_procedures . ' procedures to process for manifest' );

		// Update progress tracking
		$this->update_stage_progress( 0, $total_procedures, 'Starting manifest creation...' );

		$processed         = 0;
		$total_cases_found = 0;

		foreach ( $procedures as $procedure ) {
			if ( empty( $procedure['ids'] ) || $procedure['caseCount'] <= 0 ) {
				$processed ++;
				continue;
			}

			$procedure_name = $procedure['name'];
			$procedure_ids  = $procedure['ids'];
			$case_count     = $procedure['caseCount'];

			error_log( "Chunked Sync: Processing procedure '{$procedure_name}' with {$case_count} expected cases" );

			// Update progress
			$this->update_stage_progress( $processed, $total_procedures, "Processing: {$procedure_name}" );

			// Process each procedure ID
			foreach ( $procedure_ids as $procedure_id ) {
				// Skip invalid procedure IDs (0, null, empty)
				if ( empty( $procedure_id ) || $procedure_id === 0 || $procedure_id === '0' ) {
					error_log( "Chunked Sync: Skipping invalid procedure ID: " . var_export( $procedure_id, true ) . " for procedure '{$procedure_name}'" );
					continue;
				}

				try {
					$case_ids = $this->fetch_all_case_ids_for_procedure( intval( $procedure_id ) );

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
						error_log( "Chunked Sync: Added {$case_count_actual} cases for procedure ID {$procedure_id} ('{$procedure_name}')" );
					} else {
						error_log( "Chunked Sync: No cases found for procedure ID {$procedure_id} ('{$procedure_name}')" );
					}
				} catch ( Exception $e ) {
					error_log( "Chunked Sync: ERROR fetching cases for procedure ID {$procedure_id}: " . $e->getMessage() );
					// Continue with next procedure instead of failing completely
				}
			}

			$processed ++;

			// Update progress with case count
			$this->update_stage_progress( $processed, $total_procedures, "Processed: {$procedure_name} - Total cases so far: {$total_cases_found}" );
		}

		error_log( "Chunked Sync: Manifest creation complete. Total procedures: " . count( $manifest ) . ", Total cases: {$total_cases_found}" );

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

		foreach ( $sidebar_data['data'] as $category ) {
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( ! empty( $procedure['ids'] ) && ! empty( $procedure['totalCase'] ) ) {
						// Filter out invalid IDs (0, null, empty) from the IDs array
						$valid_ids = array_filter( $procedure['ids'], function ( $id ) {
							return ! empty( $id ) && $id !== 0 && $id !== '0';
						} );

						// Only add procedures that have at least one valid ID
						if ( ! empty( $valid_ids ) ) {
							$procedures[] = [
								'name'      => $procedure['name'] ?? 'Unknown',
								'ids'       => array_values( $valid_ids ), // Re-index array after filtering
								'caseCount' => $procedure['totalCase'],
							];

							// Log the IDs for debugging
							error_log( "Chunked Sync: Procedure '{$procedure['name']}' has valid IDs: " . implode( ', ', $valid_ids ) );
						} else {
							error_log( "Chunked Sync: Skipping procedure '{$procedure['name']}' - no valid IDs found. Original IDs: " . wp_json_encode( $procedure['ids'] ) );
						}
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
	private function fetch_all_case_ids_for_procedure( int $procedure_id ): array {
		$all_case_ids = [];
		$count        = 1;
		$max_count    = 100; // Safety limit

		error_log( "Chunked Sync: Fetching case IDs for procedure {$procedure_id}" );

		while ( $count <= $max_count ) {
			try {
				$case_ids = $this->fetch_case_ids_with_count( $procedure_id, $count );

				if ( empty( $case_ids ) ) {
					// No more cases
					break;
				}

				$all_case_ids = array_merge( $all_case_ids, $case_ids );
				error_log( "Chunked Sync: Count {$count} returned " . count( $case_ids ) . " case IDs" );

				$count ++;

				// Small delay to avoid overwhelming the API
				usleep( 100000 ); // 0.1 second

			} catch ( Exception $e ) {
				error_log( "Chunked Sync: Failed to fetch count {$count} for procedure {$procedure_id}: " . $e->getMessage() );
				break;
			}
		}

		// Remove duplicates and ensure sequential array
		$all_case_ids = array_unique( $all_case_ids );

		// Re-index to ensure it's a sequential array (0, 1, 2, ...) not an associative array
		$all_case_ids = array_values( $all_case_ids );

		error_log( "Chunked Sync: Total of " . count( $all_case_ids ) . " unique case IDs for procedure {$procedure_id}" );

		return $all_case_ids;
	}

	/**
	 * Fetch case IDs for a procedure with specific count parameter
	 *
	 * @param int $procedure_id Procedure ID
	 * @param int $count Count parameter for API
	 *
	 * @return array Case IDs
	 * @throws Exception If API request fails
	 */
	private function fetch_case_ids_with_count( int $procedure_id, int $count ): array {
		// Get API configuration
		$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			throw new Exception( 'No API tokens configured' );
		}

		$valid_tokens = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build API request
		$api_base_url = $this->get_api_base_url();
		$endpoint     = '/api/plugin/combine/cases';
		$full_url     = $api_base_url . $endpoint;

		$request_body = [
			'apiTokens'          => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'procedureIds'       => [ $procedure_id ],
			'count'              => $count,
		];

		// Make API request
		$response = wp_remote_post( $full_url, [
			'timeout'   => 30,
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'      => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );

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

		// Extract case IDs from response
		$case_ids = [];
		if ( isset( $data['success'] ) && $data['success'] && ! empty( $data['data'] ) ) {
			// Log the structure to understand what we're getting
			if ( $count === 1 ) {
				error_log( "Chunked Sync: API response structure for procedure {$procedure_id}, count {$count}: " . wp_json_encode( array_keys( $data['data'] ) ) );

				// Check if data['data'] is an associative array with count keys or a sequential array
				$first_key = array_key_first( $data['data'] );
				if ( is_numeric( $first_key ) && $first_key > 0 ) {
					error_log( "Chunked Sync: WARNING - API returning count-indexed array for procedure {$procedure_id}" );
				}
			}

			foreach ( $data['data'] as $key => $case ) {
				// Skip if the key is numeric (count index) and the value is just an ID
				if ( is_numeric( $key ) && is_numeric( $case ) ) {
					// This is just a case ID, not a case object
					$case_ids[] = $case;
				} elseif ( is_array( $case ) && isset( $case['id'] ) ) {
					// This is a case object with an id field
					$case_ids[] = $case['id'];
				} elseif ( is_numeric( $case ) ) {
					// Just a plain case ID
					$case_ids[] = $case;
				}
			}
		}

		return $case_ids;
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
		$bytes_written = file_put_contents( $file_path, wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

		if ( $bytes_written === false ) {
			throw new Exception( 'Failed to save manifest to file' );
		}

		error_log( 'Chunked Sync: Saved manifest to file: ' . basename( $file_path ) );
	}

	/**
	 * Load manifest from file
	 *
	 * @return array Manifest data
	 * @throws Exception If file cannot be read
	 */
	private function load_manifest(): array {
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
	 * @return array Result with success status and details
	 */
	public function execute_stage_3(): array {
		error_log( 'Chunked Sync: Starting Stage 3 - Process cases from manifest' );

		// Increase memory limit and time limit for processing
		@ini_set( 'memory_limit', '512M' );
		@set_time_limit( 600 ); // 10 minutes

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
			if ( $state && isset( $state['session_id'] ) && $state['session_id'] === $this->sync_session_id ) {
				error_log( 'Chunked Sync: Resuming Stage 3 from saved state' );

				return $this->resume_stage_3( $state );
			}

			// Start fresh processing
			$result = $this->process_cases_from_manifest( $manifest );

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

			error_log( 'Chunked Sync: Stage 3 completed successfully' );
			error_log( 'Chunked Sync: Memory usage before response: ' . ( memory_get_usage( true ) / 1024 / 1024 ) . 'MB' );

			// Return minimal response to avoid memory issues
			return [
				'success'         => true,
				'stage'           => 3,
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

			error_log( 'Chunked Sync: Stage 3 failed: ' . $e->getMessage() );

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
			error_log( "Chunked Sync Progress: [{$current}/{$total}] {$message}" );
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
	 * Process cases from manifest
	 *
	 * @param array $manifest Manifest data with procedure IDs and case IDs
	 *
	 * @return array Processing results
	 */
	private function process_cases_from_manifest( array $manifest ): array {
		$total_procedures = count( $manifest );
		$total_cases      = 0;
		$processed_cases  = 0;
		$created_posts    = 0;
		$updated_posts    = 0;
		$failed_cases     = 0;
		$errors           = [];

		// Count total cases
		foreach ( $manifest as $case_ids ) {
			$total_cases += count( $case_ids );
		}

		error_log( "Chunked Sync: Stage 3 - Processing {$total_cases} cases from {$total_procedures} procedures" );

		// Update initial progress
		$this->update_stage_progress( 0, $total_cases, 'Starting case processing...' );

		$procedure_index = 0;
		$batch_size      = 5; // Process 5 cases at a time for better memory management
		$batch_count     = 0;

		// Loop through each procedure in the manifest
		foreach ( $manifest as $procedure_id => $case_ids ) {
			$procedure_index ++;
			$procedure_case_count = count( $case_ids );

			error_log( "Chunked Sync: Processing procedure ID {$procedure_id} ({$procedure_index}/{$total_procedures}) with {$procedure_case_count} cases" );

			// Get the procedure term for this ID
			$procedure_term = $this->get_procedure_term_by_api_id( $procedure_id );
			if ( ! $procedure_term ) {
				error_log( "Chunked Sync: WARNING - No procedure term found for API ID {$procedure_id}, skipping cases" );
				$processed_cases += $procedure_case_count;
				continue;
			}

			// Store case ordering for this procedure (case IDs are already in API order)
			$this->store_procedure_case_order( $procedure_term->term_id, $case_ids );
			error_log( "Chunked Sync: Stored case ordering for procedure {$procedure_id} - " . count( $case_ids ) . " cases" );

			// Track the position of cases within this procedure for ordering
			$case_position = 0;

			// Process cases in batches
			$case_batches = array_chunk( $case_ids, $batch_size );

			foreach ( $case_batches as $batch_index => $batch ) {
				$batch_count ++;
				$batch_start = $batch_index * $batch_size;

				error_log( "Chunked Sync: Processing batch " . ( $batch_index + 1 ) . " of " . count( $case_batches ) . " for procedure {$procedure_id} (cases " . ( $batch_start + 1 ) . "-" . ( $batch_start + count( $batch ) ) . ")" );

				// Process each case ID in the batch
				foreach ( $batch as $case_offset => $case_id ) {
					$case_index = $batch_start + $case_offset;

					try {
						// Update progress
						$case_number = $case_index + 1;
						$this->update_stage_progress(
							$processed_cases,
							$total_cases,
							"Processing case {$case_id} from procedure {$procedure_id} ({$case_number}/{$procedure_case_count})"
						);

						// Fetch full case details from API (pass procedure_id for proper API authentication)
						error_log( "Chunked Sync: Fetching details for case {$case_id} with procedure ID {$procedure_id}..." );
						$case_details = $this->fetch_case_details( $case_id, intval( $procedure_id ) );
						if ( ! $case_details ) {
							throw new Exception( "Failed to fetch details for case {$case_id}" );
						}
						error_log( "Chunked Sync: Successfully fetched details for case {$case_id}, data keys: " . implode( ', ', array_keys( $case_details ) ) );

						// Create or update WordPress post with order position
						error_log( "Chunked Sync: Creating/updating post for case {$case_id} at position {$case_position}..." );
						$result = $this->create_or_update_case_post( $case_details, $procedure_term, $case_position );

						if ( $result['created'] ) {
							$created_posts ++;
							error_log( "Chunked Sync: Created NEW post for case {$case_id} (Post ID: {$result['post_id']}) at position {$case_position}" );
						} else {
							$updated_posts ++;
							error_log( "Chunked Sync: Updated EXISTING post for case {$case_id} (Post ID: {$result['post_id']}) at position {$case_position}" );
						}

						// Increment position for next case
						$case_position ++;

					} catch ( Exception $e ) {
						$failed_cases ++;
						$error_msg = "Failed to process case {$case_id}: " . $e->getMessage();
						$errors[]  = $error_msg;
						error_log( "Chunked Sync: ERROR - " . $error_msg );
					}

					$processed_cases ++;

					// Check if we should save state for resumption (every 10 cases)
					if ( $processed_cases % 10 === 0 ) {
						$this->save_stage3_state( $manifest, $procedure_id, $case_index, $processed_cases, $created_posts, $updated_posts, $failed_cases );
					}

					// Small delay to avoid overwhelming the API
					if ( $processed_cases % 5 === 0 ) {
						usleep( 100000 ); // 0.1 second delay every 5 cases
					}
				}

				// Clear memory after every batch with smaller batch size
				error_log( "Chunked Sync: Clearing memory after batch {$batch_count}" );

				// Force garbage collection
				if ( function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}

				// Log memory usage
				$memory_usage = memory_get_usage( true ) / 1024 / 1024; // Convert to MB
				$memory_peak  = memory_get_peak_usage( true ) / 1024 / 1024; // Convert to MB
				error_log( "Chunked Sync: Memory usage: {$memory_usage}MB, Peak: {$memory_peak}MB" );

				// If memory is getting high, pause briefly
				if ( $memory_usage > 200 ) {
					error_log( "Chunked Sync: High memory usage detected (>{$memory_usage}MB), pausing for 1 second" );
					sleep( 1 );
				}
			}
		}

		// Final progress update
		$this->update_stage_progress( $total_cases, $total_cases, 'Case processing complete' );

		// Limit errors in response to prevent memory issues
		$limited_errors = array_slice( $errors, 0, 10 );
		if ( count( $errors ) > 10 ) {
			$limited_errors[] = sprintf( '... and %d more errors', count( $errors ) - 10 );
		}

		return [
			'total_cases'     => $total_cases,
			'processed_cases' => $processed_cases,
			'created_posts'   => $created_posts,
			'updated_posts'   => $updated_posts,
			'failed_cases'    => $failed_cases,
			'errors'          => $limited_errors,
			'error_count'     => count( $errors ),
		];
	}

	/**
	 * Fetch case details from API
	 *
	 * @param int $case_id Case ID
	 * @param int $procedure_id Procedure ID the case belongs to (needed for API)
	 *
	 * @return array|null Case details or null on failure
	 */
	private function fetch_case_details( int $case_id, ?int $procedure_id = null ): ?array {
		// Get API configuration
		$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $api_tokens[0] ) ) {
			error_log( 'Chunked Sync: No API tokens configured for case fetch' );

			return null;
		}

		$valid_tokens = array_filter( $api_tokens, function ( $token ) {
			return ! empty( $token );
		} );

		$valid_property_ids = array_filter( array_map( 'intval', $website_property_ids ) );

		// Build API request for single case
		$api_base_url = $this->get_api_base_url();
		$endpoint     = '/api/plugin/combine/cases/' . $case_id; // Endpoint for single case details
		$full_url     = $api_base_url . $endpoint;

		// API requires procedureIds even for single case fetch
		$procedure_ids = [];
		if ( $procedure_id !== null ) {
			$procedure_ids = [ intval( $procedure_id ) ];
		} else {
			// Use a default if not provided (this should rarely happen)
			$procedure_ids = [ 6851 ];
		}

		$request_body = [
			'apiTokens'          => array_values( $valid_tokens ),
			'websitePropertyIds' => array_values( $valid_property_ids ),
			'procedureIds'       => $procedure_ids,
		];

		// Debug: Log request details
		error_log( 'Chunked Sync: Fetching case ' . $case_id . ' from ' . $full_url );
		error_log( 'Chunked Sync: Request body: ' . wp_json_encode( $request_body ) );
		error_log( 'Chunked Sync: Procedure IDs being sent: ' . implode( ',', $procedure_ids ) );

		// Make API request
		$response = wp_remote_post( $full_url, [
			'timeout'   => 30,
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'      => wp_json_encode( $request_body ),
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'Chunked Sync: API request failed for case ' . $case_id . ': ' . $response->get_error_message() );

			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$response_body = wp_remote_retrieve_body( $response );
			error_log( 'Chunked Sync: API returned error status ' . $response_code . ' for case ' . $case_id );
			error_log( 'Chunked Sync: API error response: ' . substr( $response_body, 0, 500 ) ); // Log first 500 chars of response

			return null;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'Chunked Sync: Invalid JSON response for case ' . $case_id );

			return null;
		}

		if ( ! isset( $data['success'] ) || ! $data['success'] || empty( $data['data'] ) ) {
			error_log( 'Chunked Sync: API returned unsuccessful response for case ' . $case_id );

			return null;
		}

		// The API returns data in an array format - get the first item
		if ( isset( $data['data'][0] ) ) {
			return $data['data'][0];
		}

		// Fallback if data structure is different
		return $data['data'];
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
	private function create_or_update_case_post( array $case_details, $procedure_term, int $case_position = 0 ): array {
		$case_id = $case_details['id'] ?? 'unknown';
		error_log( "Chunked Sync: create_or_update_case_post called for case {$case_id}" );

		// Check if post already exists by case API ID
		$existing_posts = get_posts( [
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'meta_key'       => '_case_api_id',
			'meta_value'     => $case_details['id'],
			'posts_per_page' => 1,
		] );

		error_log( "Chunked Sync: Found " . count( $existing_posts ) . " existing posts for case {$case_id}" );

		// Generate slug from case data
		$slug = $this->generate_case_slug( $case_details );

		// HIPAA COMPLIANCE: Do NOT store case details as they may contain PHI
		// Use the main gallery shortcode which auto-detects context for case view
		$content = sprintf(
			'[brag_book_gallery]

<!-- Case ID: %d, Procedure ID: %d, Synced: %s -->',
			$case_details['id'] ?? 0,
			$case_details['procedureId'] ?? 0,
			current_time( 'mysql' )
		);

		if ( ! empty( $existing_posts ) ) {
			// Update existing post
			$post_id   = $existing_posts[0]->ID;
			$post_data = [
				'ID'           => $post_id,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => isset( $case_details['draft'] ) && $case_details['draft'] ? 'draft' : 'publish',
			];
			$result    = wp_update_post( $post_data );
			error_log( "Chunked Sync: wp_update_post result for case {$case_id}: " . ( $result ? "success (ID: $result)" : "failed" ) );
			if ( is_wp_error( $result ) ) {
				throw new Exception( "Failed to update post: " . $result->get_error_message() );
			}
			// Update sync timestamp
			update_post_meta( $post_id, '_case_synced_at', current_time( 'mysql' ) );
			$created = false;
		} else {
			// Generate a proper title upfront in case save_api_response_data fails
			$procedure_name  = $procedure_term->name ?? 'Unknown Procedure';
			$display_case_id = $case_details['id'] ?? 'unknown';
			$initial_title   = $procedure_name . ' #' . $display_case_id;

			// Create new post with proper title from the start
			$post_data = [
				'post_type'    => Post_Types::POST_TYPE_CASES,
				'post_title'   => $initial_title, // Use proper title from the start
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => isset( $case_details['draft'] ) && $case_details['draft'] ? 'draft' : 'publish',
				'meta_input'   => [
					'_case_api_id'    => $case_details['id'],
					'_case_synced_at' => current_time( 'mysql' ),
				],
			];
			$post_id   = wp_insert_post( $post_data );
			error_log( "Chunked Sync: wp_insert_post result for case {$case_id}: " . ( is_wp_error( $post_id ) ? "error: " . $post_id->get_error_message() : "success (ID: $post_id)" ) );
			if ( is_wp_error( $post_id ) ) {
				throw new Exception( "Failed to create post: " . $post_id->get_error_message() );
			}
			$created = true;
		}

		// Use the Post_Types method to save all API data properly
		// This handles all the field mapping including both new and legacy formats
		// Wrap in try-catch to ensure post is not left in incomplete state
		try {
			Post_Types::save_api_response_data( $post_id, $case_details );
		} catch ( Exception $e ) {
			error_log( "Chunked Sync: Failed to save API data for case {$case_id}: " . $e->getMessage() );
			// Don't throw - the post is created, just missing some metadata
			// The title is already set properly
		}

		// Store additional sync-specific metadata
		update_post_meta( $post_id, '_original_case_id', $case_id );
		update_post_meta( $post_id, '_procedure_id', $case_details['procedureId'] ?? '' );

		// Store procedure IDs if multiple
		if ( isset( $case_details['procedureIds'] ) && is_array( $case_details['procedureIds'] ) ) {
			update_post_meta( $post_id, '_case_procedure_ids', implode( ',', $case_details['procedureIds'] ) );
		}

		// Assign to procedure taxonomy - handle multiple procedures
		$procedure_term_ids = [ $procedure_term->term_id ];

		// If case has multiple procedures, assign all of them to the taxonomy
		if ( isset( $case_details['procedureIds'] ) && is_array( $case_details['procedureIds'] ) && count( $case_details['procedureIds'] ) > 1 ) {
			$procedure_term_ids = [];
			foreach ( $case_details['procedureIds'] as $proc_id ) {
				$term = $this->get_procedure_term_by_api_id( intval( $proc_id ) );
				if ( $term ) {
					$procedure_term_ids[] = $term->term_id;
				}
			}
		} // Also check procedureDetails for multiple procedures
		elseif ( isset( $case_details['procedureDetails'] ) && is_array( $case_details['procedureDetails'] ) && count( $case_details['procedureDetails'] ) > 1 ) {
			$procedure_term_ids = [];
			foreach ( array_keys( $case_details['procedureDetails'] ) as $proc_id ) {
				$term = $this->get_procedure_term_by_api_id( intval( $proc_id ) );
				if ( $term ) {
					$procedure_term_ids[] = $term->term_id;
				}
			}
		}

		wp_set_object_terms( $post_id, $procedure_term_ids, Taxonomies::TAXONOMY_PROCEDURES );

		// Store the case position for ordering within this procedure
		update_post_meta( $post_id, 'brag_book_gallery_case_order', $case_position );
		update_post_meta( $post_id, '_case_order', $case_position ); // Legacy compatibility
		error_log( "Chunked Sync: Set case order position to {$case_position} for post {$post_id}" );

		return [
			'post_id' => $post_id,
			'created' => $created,
		];
	}


	/**
	 * Generate case slug from API data
	 *
	 * @param array $case_data Case data from API
	 *
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
						error_log( 'Chunked Sync: Using seoSuffixUrl as slug for case ' . $case_id . ': ' . $slug );

						return $slug;
					}
				}
			}
		}

		// Fallback to case ID - ensure it's a string
		$fallback_slug = (string) $case_id;
		error_log( 'Chunked Sync: Using fallback slug for case ' . $case_id . ': ' . $fallback_slug );

		return $fallback_slug;
	}

	/**
	 * Get procedure term by API ID
	 *
	 * @param int $api_id Procedure API ID
	 *
	 * @return object|null Term object or null if not found
	 */
	private function get_procedure_term_by_api_id( int $api_id ): ?object {
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
			'meta_key'   => 'procedure_id',
			'meta_value' => $api_id,
			'hide_empty' => false,
		] );

		return ! empty( $terms ) ? $terms[0] : null;
	}

	/**
	 * Save Stage 3 state for resumption
	 *
	 * @param array $manifest Full manifest
	 * @param string $current_procedure_id Current procedure ID
	 * @param int $current_case_index Current case index
	 * @param int $processed_cases Total processed cases
	 * @param int $created_posts Created posts count
	 * @param int $updated_posts Updated posts count
	 * @param int $failed_cases Failed cases count
	 *
	 * @return void
	 */
	private function save_stage3_state( array $manifest, int $current_procedure_id, int $current_case_index, int $processed_cases, int $created_posts, int $updated_posts, int $failed_cases ): void {
		$state = [
			'session_id'           => $this->sync_session_id,
			'manifest'             => $manifest,
			'current_procedure_id' => $current_procedure_id,
			'current_case_index'   => $current_case_index,
			'processed_cases'      => $processed_cases,
			'created_posts'        => $created_posts,
			'updated_posts'        => $updated_posts,
			'failed_cases'         => $failed_cases,
			'timestamp'            => time(),
		];

		update_option( 'brag_book_stage3_state', $state, false );
	}

	/**
	 * Resume Stage 3 from saved state
	 *
	 * @param array $state Saved state
	 *
	 * @return array Processing results
	 */
	private function resume_stage_3( array $state ): array {
		// Implementation would continue from saved position
		// This is a placeholder for the resume logic
		return $this->process_cases_from_manifest( $state['manifest'] );
	}

	/**
	 * Store case ordering for a procedure
	 *
	 * Stores the case IDs in their API response order as term meta.
	 * This allows maintaining the same case order as displayed in the BRAGBook system.
	 *
	 * @param int $term_id WordPress term ID for the procedure
	 * @param array $case_ids Array of case IDs in their API response order
	 *
	 * @return void
	 */
	private function store_procedure_case_order( int $term_id, array $case_ids ): void {
		if ( empty( $case_ids ) ) {
			// Clear any existing case order list if no cases
			delete_term_meta( $term_id, 'brag_book_gallery_case_order_list' );

			return;
		}

		// Store the case order list as term meta
		update_term_meta( $term_id, 'brag_book_gallery_case_order_list', $case_ids );

		error_log( "Chunked Sync: Stored case order for term {$term_id} - " . count( $case_ids ) . " cases in order: " . implode( ', ', array_slice( $case_ids, 0, 5 ) ) . ( count( $case_ids ) > 5 ? '...' : '' ) );
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
			error_log( "Chunked Sync: No procedure term found for API ID {$procedure_id}" );

			return [];
		}

		$term = $terms[0];

		// Get the case order list stored on the term
		$case_ids_in_order = get_term_meta( $term->term_id, 'brag_book_gallery_case_order_list', true );
		if ( ! is_array( $case_ids_in_order ) || empty( $case_ids_in_order ) ) {
			error_log( "Chunked Sync: No case order list found for procedure {$procedure_id} (term: {$term->term_id})" );

			return [];
		}

		// Get WordPress post IDs for these case IDs in order
		$ordered_post_ids = [];
		foreach ( $case_ids_in_order as $case_id ) {
			// Try current format first (brag_book_gallery_api_id)
			$posts = get_posts( [
				'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
				'meta_key'       => 'brag_book_gallery_api_id',
				'meta_value'     => $case_id,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			] );

			// Fallback to legacy format (_case_api_id)
			if ( empty( $posts ) ) {
				$posts = get_posts( [
					'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
					'meta_key'       => '_case_api_id',
					'meta_value'     => $case_id,
					'posts_per_page' => 1,
					'post_status'    => 'any',
				] );
			}

			if ( ! empty( $posts ) ) {
				$ordered_post_ids[] = $posts[0]->ID;
			}
		}

		error_log( "Chunked Sync: Returning " . count( $ordered_post_ids ) . " ordered posts for procedure {$procedure_id}" );

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
}
