<?php
/**
 * AJAX handlers for BRAGBookGallery plugin.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\REST\Endpoints;
use Exception;

/**
 * Class Ajax_Handlers
 *
 * Handles all AJAX operations for the BRAGBookGallery plugin.
 *
 * @since 3.0.0
 */
class Ajax_Handlers {

	/**
	 * Register all AJAX handlers.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register(): void {
		// Gallery filtering
		add_action( 'wp_ajax_brag_book_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );

		// Case details
		add_action( 'wp_ajax_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );

		add_action( 'wp_ajax_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );
		add_action( 'wp_ajax_nopriv_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );

		add_action( 'wp_ajax_brag_book_load_case_details_html', [ __CLASS__, 'ajax_load_case_details_html' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_case_details_html', [ __CLASS__, 'ajax_load_case_details_html' ] );

		// Load more and filtering
		add_action( 'wp_ajax_brag_book_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );

		add_action( 'wp_ajax_brag_book_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );

		// Cache management
		add_action( 'wp_ajax_brag_book_gallery_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );

		// Rewrite rules
		add_action( 'wp_ajax_brag_book_flush_rewrite_rules', [ __CLASS__, 'ajax_flush_rewrite_rules' ] );
	}

	/**
	 * AJAX handler to flush rewrite rules.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_flush_rewrite_rules(): void {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bragbook_flush' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			// Force register our rules
			Rewrite_Rules_Handler::custom_rewrite_rules();

			// Flush rewrite rules
			flush_rewrite_rules( true );

			// Clear the notice
			delete_transient( 'bragbook_show_rewrite_notice' );

			// Get debug info
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

			wp_send_json_success( [
				'message' => 'Rewrite rules flushed successfully',
				'gallery_slugs' => $gallery_slugs,
				'timestamp' => current_time( 'mysql' )
			] );

		} catch ( Exception $e ) {
			wp_send_json_error( 'Error flushing rules: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for loading filtered gallery content.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_gallery(): void {
		// Simple test response - uncomment to test if handler is reached
		// wp_send_json_success( [ 'html' => '<div class="brag-book-gallery-test">AJAX handler is working! Received action: ' . ($_POST['action'] ?? 'none') . '</div>', 'count' => 0 ] );
		// return;
		
		// Wrap everything in try-catch to capture any errors
		try {
			// Debug: Log that we reached the handler
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: ajax_load_filtered_gallery called' );
				error_log( 'POST data: ' . print_r( $_POST, true ) );
			}
			
			// Verify nonce
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
				wp_send_json_error( [
					'message' => __( 'Security check failed - nonce invalid or missing.', 'brag-book-gallery' ),
					'debug' => [
						'has_nonce' => isset( $_POST['nonce'] ),
						'nonce_value' => isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : 'not set',
					],
				] );
				return;
			}
			
			// Get filter parameters
			$procedure_name = isset( $_POST['procedure_name'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_name'] ) ) : '';
			$procedure_ids = isset( $_POST['procedure_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_ids'] ) ) : '';
			$has_nudity = isset( $_POST['has_nudity'] ) ? ( '1' === $_POST['has_nudity'] ) : false;

			// Get gallery page configuration
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
			$base_path = ! empty( $gallery_slugs[0] ) ? '/' . $gallery_slugs[0] : '/before-after';

			// Get API configuration directly from options
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API Tokens: ' . print_r( $api_tokens, true ) );
				error_log( 'Website Property IDs: ' . print_r( $website_property_ids, true ) );
			}
			
			// Get the first configured API token and property ID
			$api_token = ! empty( $api_tokens[0] ) ? $api_tokens[0] : '';
			$website_property_id = ! empty( $website_property_ids[0] ) ? $website_property_ids[0] : '';
			
			if ( empty( $api_token ) || empty( $website_property_id ) ) {
				wp_send_json_error( [
					'message' => __( 'API configuration missing. Please configure the BRAGBook API settings.', 'brag-book-gallery' ),
					'debug' => [
						'has_token' => ! empty( $api_token ),
						'has_property_id' => ! empty( $website_property_id ),
					],
				] );
				return;
			}

			// Fetch filtered cases from API
			try {
				$gallery_data = Data_Fetcher::get_all_cases_for_filtering( 
					$api_token, 
					$website_property_id 
				);
			} catch ( \Exception $api_error ) {
				wp_send_json_error( [
					'message' => __( 'Failed to fetch gallery data from API.', 'brag-book-gallery' ),
					'error' => $api_error->getMessage(),
				] );
				return;
			}

			if ( empty( $gallery_data ) || ! is_array( $gallery_data ) || empty( $gallery_data['data'] ) ) {
				wp_send_json_error( [
					'message' => __( 'No gallery data available.', 'brag-book-gallery' ),
					'debug' => [
						'has_data' => ! empty( $gallery_data ),
						'is_array' => is_array( $gallery_data ),
						'has_data_key' => ! empty( $gallery_data['data'] ),
					],
				] );
				return;
			}

			// Filter cases based on procedure IDs
			$filtered_cases = [];
			if ( ! empty( $procedure_ids ) ) {
				$ids_array = array_map( 'intval', explode( ',', $procedure_ids ) );
				
				foreach ( $gallery_data['data'] as $case ) {
					if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
						foreach ( $case['procedures'] as $procedure ) {
							if ( isset( $procedure['id'] ) && in_array( intval( $procedure['id'] ), $ids_array, true ) ) {
								$filtered_cases[] = $case;
								break;
							}
						}
					}
				}
			} else {
				// If no procedure IDs, return all cases
				$filtered_cases = $gallery_data['data'];
			}

			// Transform case data to match what HTML_Renderer expects
			$transformed_cases = [];
			try {
				foreach ( $filtered_cases as $case ) {
					$transformed_case = $case;
					
					// Extract main image from photoSets
					$transformed_case['mainImageUrl'] = '';
					if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
						$first_photoset = reset( $case['photoSets'] );
						$transformed_case['mainImageUrl'] = $first_photoset['postProcessedImageLocation'] ?? 
															 $first_photoset['beforeLocationUrl'] ?? 
															 $first_photoset['afterLocationUrl1'] ?? '';
					}
					
					// Extract procedure title
					$transformed_case['procedureTitle'] = __( 'Unknown Procedure', 'brag-book-gallery' );
					if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
						$first_procedure = reset( $case['procedures'] );
						$transformed_case['procedureTitle'] = $first_procedure['name'] ?? __( 'Unknown Procedure', 'brag-book-gallery' );
					}
					
					// Only add cases that have at least an image or valid data
					if ( ! empty( $transformed_case['mainImageUrl'] ) || ! empty( $case['id'] ) ) {
						$transformed_cases[] = $transformed_case;
					}
				}
			} catch ( \Exception $transform_error ) {
				wp_send_json_error( [
					'message' => __( 'Failed to transform case data.', 'brag-book-gallery' ),
					'error' => $transform_error->getMessage(),
				] );
				return;
			}

			// Generate HTML for filtered gallery
			try {
				// Build the HTML using the original method structure
				$html = '<div class="brag-book-filtered-results">';

				// Add H2 header with procedure name
				$procedure_display_name = ucwords( str_replace( '-', ' ', $procedure_name ) );
				$html .= sprintf(
					'<h2 class="brag-book-gallery-content-title"><strong>%s</strong> %s</h2>',
					esc_html( $procedure_display_name ),
					esc_html__( 'Before & After Gallery', 'brag-book-gallery' )
				);

				// Add controls container for grid selector and filters
				$html .= '<div class="brag-book-gallery-controls">';

				// Left side container for filters and count
				$html .= '<div class="brag-book-gallery-controls-left">';

				// Procedure filters details
				$html .= sprintf(
					'<details class="brag-book-gallery-filter-dropdown" id="procedure-filters-details">
						<summary class="brag-book-gallery-filter-toggle">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 -960 960 960" fill="currentColor"><path d="M411.15-260v-60h137.31v60H411.15ZM256.16-450v-60h447.3v60h-447.3ZM140-640v-60h680v60H140Z"/></svg>
							<span>%s</span>
						</summary>
						<div class="brag-book-gallery-filter-panel">
							<div class="brag-book-gallery-filter-content">
								<div class="brag-book-gallery-filter-section">
									<div id="brag-book-gallery-filters">
										%s
									</div>
								</div>
							</div>
							<div class="brag-book-gallery-filter-actions">
								<button class="brag-book-gallery-filter-apply" onclick="applyProcedureFilters()">%s</button>
								<button class="brag-book-gallery-filter-clear" onclick="clearProcedureFilters()">%s</button>
							</div>
						</div>
					</details>',
					esc_html__( 'Procedure Filters', 'brag-book-gallery' ),
					'', // Filter options will be dynamically generated by JavaScript
					esc_html__( 'Apply Filters', 'brag-book-gallery' ),
					esc_html__( 'Clear All', 'brag-book-gallery' )
				);

				// Add filter badges container (replacing the count display)
				$html .= '<div class="brag-book-gallery-active-filters">
					<div class="brag-book-gallery-filter-badges" id="brag-book-gallery-filter-badges">
						<!-- Filter badges will be populated by JavaScript -->
					</div>
					<button class="brag-book-gallery-clear-all-filters" id="brag-book-gallery-clear-all" style="display: none;">
						' . esc_html__( 'Clear All', 'brag-book-gallery' ) . '
					</button>
				</div>';

				$html .= '</div>'; // Close left controls

				// Grid layout selector (on the right)
				$html .= sprintf(
					'<div class="brag-book-gallery-grid-selector">
						<span class="brag-book-gallery-grid-label">%s</span>
						<div class="brag-book-gallery-grid-buttons">
							<button class="brag-book-gallery-grid-btn" data-columns="2" onclick="updateGridLayout(2)" aria-label="%s">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg>
								<span class="sr-only">%s</span>
							</button>
							<button class="brag-book-gallery-grid-btn active" data-columns="3" onclick="updateGridLayout(3)" aria-label="%s">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="4" height="4"/><rect x="6" y="1" width="4" height="4"/><rect x="11" y="1" width="4" height="4"/><rect x="1" y="6" width="4" height="4"/><rect x="6" y="6" width="4" height="4"/><rect x="11" y="6" width="4" height="4"/><rect x="1" y="11" width="4" height="4"/><rect x="6" y="11" width="4" height="4"/><rect x="11" y="11" width="4" height="4"/></svg>
								<span class="sr-only">%s</span>
							</button>
						</div>
					</div>',
					esc_html__( 'View:', 'brag-book-gallery' ),
					esc_attr__( 'View in 2 columns', 'brag-book-gallery' ),
					esc_html__( '2 Columns', 'brag-book-gallery' ),
					esc_attr__( 'View in 3 columns', 'brag-book-gallery' ),
					esc_html__( '3 Columns', 'brag-book-gallery' )
				);

				$html .= '</div>'; // Close controls container

				// Start cases grid with CSS Grid layout
				$html .= '<div class="brag-book-gallery-case-grid" data-columns="3">';

				// Loop through cases and render them
				if ( ! empty( $transformed_cases ) ) {
					$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );
					
					foreach ( $transformed_cases as $case ) {
						// Use reflection to access the render_ajax_gallery_case_card method
						try {
							$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
							$method->setAccessible( true );
							$html .= $method->invoke( null, $case, $image_display_mode, $has_nudity, $procedure_name );
						} catch ( \Exception $render_error ) {
							error_log( 'Error rendering case card: ' . $render_error->getMessage() );
						}
					}
				}

				$html .= '</div>'; // Close cases grid

				// Add Load More button if there are enough cases
				if ( count( $transformed_cases ) >= 10 ) {
					$html .= '<div class="brag-book-gallery-load-more-container">';
					$html .= '<button class="brag-book-gallery-load-more-btn" ';
					$html .= 'data-start-page="2" ';
					$html .= 'data-procedure-ids="' . esc_attr( $procedure_ids ) . '" ';
					$html .= 'onclick="loadMoreCases(this)">';
					$html .= 'Load More';
					$html .= '</button>';
					$html .= '</div>';
				}

				$html .= '</div>'; // Close filtered results

				// Add JavaScript for grid control and filters
				$html .= self::generate_filtered_gallery_scripts( $transformed_cases, $procedure_ids );
			
			wp_send_json_success( [
				'html' => $html,
				'totalCount' => count( $transformed_cases ),
				'procedureName' => $procedure_name,
			] );
			
			} catch ( \Exception $html_error ) {
				wp_send_json_error( [
					'message' => __( 'Failed to generate gallery HTML.', 'brag-book-gallery' ),
					'error' => $html_error->getMessage(),
				] );
				return;
			}
		
		} catch ( \Throwable $e ) {
			// Catch any error including fatal errors
			error_log( 'BRAGBook Gallery AJAX Error (Throwable): ' . $e->getMessage() );
			error_log( 'Error Type: ' . get_class( $e ) );
			error_log( 'File: ' . $e->getFile() . ' Line: ' . $e->getLine() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );
			
			wp_send_json_error( [
				'message' => __( 'An error occurred while loading the filtered gallery.', 'brag-book-gallery' ),
				'error' => $e->getMessage(),
				'type' => get_class( $e ),
				'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG ? [
					'message' => $e->getMessage(),
					'file' => basename( $e->getFile() ),
					'line' => $e->getLine(),
					'type' => get_class( $e ),
				] : null,
			] );
		}
	}

	/**
	 * AJAX handler to load case details.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_case_details(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get case ID
		$case_id = isset( $_POST['case_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_id'] ) ) : '';

		if ( empty( $case_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Case ID is required.', 'brag-book-gallery' ),
			] );
		}

		// Debug: Log the case ID being requested
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BRAGBook Gallery: ajax_load_case_details called for case ID: ' . $case_id );
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		// Initialize endpoints
		$endpoints = new Endpoints();

		// Get case details from API
		$case_data = null;
		$error_messages = array();

		// Try to get case details using the caseNumber endpoint
		foreach ( $api_tokens as $index => $token ) {
			$property_id = $website_property_ids[ $index ] ?? null;

			if ( ! $property_id ) {
				$error_messages[] = 'Missing property ID for token index ' . $index;
				continue;
			}

			// Try to fetch case details
			$response = $endpoints->bb_get_case_by_number( $token, (int) $property_id, $case_id );

			if ( ! empty( $response ) && is_array( $response ) ) {
				$case_data = $response;
				break;
			} else {
				$error_messages[] = 'Failed to fetch from token index ' . $index;
			}
		}

		// If no case data found, try pagination endpoint as fallback
		if ( empty( $case_data ) ) {
			$filter_body = [
				'apiTokens' => $api_tokens,
				'websitePropertyIds' => array_map( 'intval', $website_property_ids ),
				'count' => 100,
			];

			$response = $endpoints->bb_get_pagination_data( $filter_body );

			if ( ! empty( $response['cases'] ) && is_array( $response['cases'] ) ) {
				// Debug: Log available case numbers for comparison
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$available_cases = array_map( function( $case ) {
						return $case['caseNumber'] ?? $case['id'] ?? 'no-id';
					}, array_slice( $response['cases'], 0, 10 ) ); // First 10 for brevity
					error_log( 'BRAGBook Gallery: Available case numbers in fallback search: ' . implode(', ', $available_cases) );
					error_log( 'BRAGBook Gallery: Looking for case ID: ' . $case_id );
				}
				
				// Search for the case in the results
				foreach ( $response['cases'] as $case ) {
					if ( isset( $case['caseNumber'] ) && $case['caseNumber'] === $case_id ) {
						$case_data = $case;
						break;
					}
					// Also try matching by ID field as backup
					if ( isset( $case['id'] ) && $case['id'] === $case_id ) {
						$case_data = $case;
						break;
					}
				}
			}
		}

		if ( empty( $case_data ) ) {
			// Always log this error for debugging (even without WP_DEBUG)
			error_log( 'BRAGBook Gallery: Case not found - Case ID: ' . $case_id . ' - Error messages: ' . implode( ', ', $error_messages ) );
			
			wp_send_json_error( [
				'message' => __( 'Case not found.', 'brag-book-gallery' ),
				'case_id' => $case_id,
				'errors' => $error_messages,
			] );
		}

		// Format case data for frontend
		$formatted_data = self::format_case_data_for_frontend( $case_data, $case_id );

		// Send response
		wp_send_json_success( $formatted_data );
	}

	/**
	 * AJAX handler for loading case details HTML.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_case_details_html(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$case_id = sanitize_text_field( $_POST['case_id'] ?? '' );
		$procedure_id = sanitize_text_field( $_POST['procedure_id'] ?? '' );

		if ( empty( $case_id ) ) {
			wp_send_json_error( 'Case ID is required' );
			return;
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// Try to get case from cached data or fresh fetch
		$case_data = self::find_case_by_id( $case_id, $api_token, $website_property_id );

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// Generate HTML for case details using HTML_Renderer class method
			$html = HTML_Renderer::render_case_details_html( $case_data );

			wp_send_json_success( [
				'html' => $html,
				'case_id' => $case_id,
			] );
			return;
		}

		// Case not found
		wp_send_json_error( 'Case not found. Case ID: ' . $case_id );
	}

	/**
	 * Simple AJAX handler for case details.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_simple_case_handler(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$case_id = sanitize_text_field( $_POST['case_id'] ?? '' );

		if ( empty( $case_id ) ) {
			wp_send_json_error( 'Case ID is required' );
			return;
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// Try to get case data
		$case_data = self::find_case_by_id( $case_id, $api_token, $website_property_id );

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// Generate HTML for case details using HTML_Renderer class method
			$html = HTML_Renderer::render_case_details_html( $case_data );

			wp_send_json_success( [
				'html' => $html,
				'case_id' => $case_id,
			] );
			return;
		}

		// Case not found
		wp_send_json_error( 'Case not found. Case ID: ' . $case_id );
	}

	/**
	 * AJAX handler for clearing gallery cache.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_clear_cache(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_clear_cache' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$result = Cache_Manager::clear_all_cache();

		if ( $result['success'] ) {
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * AJAX handler for loading more cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_more_cases(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get parameters
		$start_page = isset( $_POST['start_page'] ) ? intval( $_POST['start_page'] ) : 2;
		$procedure_ids = isset( $_POST['procedure_ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['procedure_ids'] ) ) ) ) : [];
		$has_nudity = isset( $_POST['has_nudity'] ) && $_POST['has_nudity'] === '1';

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		try {
			$endpoints = new Endpoints();
			$all_cases = [];
			$cases_per_page = 10;
			$pages_to_load = 1; // Load 1 page per click

			// Prepare base filter body
			$filter_body = [
				'apiTokens'          => [ $api_tokens[0] ],
				'websitePropertyIds' => [ intval( $website_property_ids[0] ) ],
				'procedureIds'       => $procedure_ids,
			];

			// Fetch additional pages
			$has_more = false;

			for ( $i = 0; $i < $pages_to_load; $i++ ) {
				$current_page = $start_page + $i;
				$filter_body['count'] = $current_page;

				$response = $endpoints->bb_get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						$new_cases_count = count( $page_data['data'] );
						$all_cases = array_merge( $all_cases, $page_data['data'] );

						// Check if there might be more
						if ( $new_cases_count == $cases_per_page ) {
							$next_page = $current_page + 1;
							$filter_body['count'] = $next_page;
							$check_response = $endpoints->bb_get_pagination_data( $filter_body );
							if ( ! empty( $check_response ) ) {
								$check_data = json_decode( $check_response, true );
								if ( is_array( $check_data ) && ! empty( $check_data['data'] ) ) {
									$has_more = true;
								}
							}
						}

						// If we got less than a full page, we've reached the end
						if ( $new_cases_count < $cases_per_page ) {
							$has_more = false;
							break;
						}
					} else {
						break; // No more data
					}
				} else {
					break; // Failed to load
				}
			}

			// Generate HTML for the new cases
			$html = '';
			$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

			foreach ( $all_cases as $case ) {
				// Use reflection to access the private method from Shortcodes class
				$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
				$method->setAccessible( true );
				$html .= $method->invoke( null, $case, $image_display_mode, $has_nudity, '' );
			}

			wp_send_json_success( [
				'html' => $html,
				'casesLoaded' => count( $all_cases ),
				'hasMore' => $has_more,
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load more cases.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * AJAX handler to load specific filtered cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_cases(): void {
		// Get case IDs from request
		$case_ids_str = isset( $_POST['case_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['case_ids'] ) ) : '';

		if ( empty( $case_ids_str ) ) {
			wp_send_json_error( [
				'message' => __( 'No case IDs provided.', 'brag-book-gallery' ),
			] );
			return;
		}

		// Convert to array of IDs
		$case_ids = array_map( 'trim', explode( ',', $case_ids_str ) );

		// Get API credentials
		$api_token = get_option( 'brag_book_gallery_api_token', '' );
		$website_property_id = get_option( 'brag_book_gallery_website_property_id', '' );

		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
			return;
		}

		try {
			// Get cached cases data
			$cache_key = 'brag_book_cases_' . md5( $api_token . $website_property_id );
			$cached_data = get_transient( $cache_key );

			$html = '';
			$cases_found = 0;

			// Check for nudity in procedures
			$procedure_nudity_map = self::get_procedure_nudity_map();

			if ( $cached_data && isset( $cached_data['data'] ) ) {
				// Look for the requested cases in our cached data
				foreach ( $cached_data['data'] as $case ) {
					if ( in_array( $case['id'], $case_ids ) ) {
						// Get image display mode
						$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

						// Check if this case's procedure has nudity
						$case_nudity = false;
						if ( ! empty( $case['technique'] ) && isset( $procedure_nudity_map[ $case['technique'] ] ) ) {
							$case_nudity = $procedure_nudity_map[ $case['technique'] ];
						}

						// Render the case card using Shortcodes class method
						// Use reflection to access the private method
						$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
						$method->setAccessible( true );
						$html .= $method->invoke( null, $case, $image_display_mode, $case_nudity, '' );
						$cases_found++;
					}
				}
			}

			wp_send_json_success( [
				'html' => $html,
				'casesFound' => $cases_found,
				'requestedCount' => count( $case_ids ),
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load filtered cases.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * Helper: Find a case by ID.
	 *
	 * @since 3.0.0
	 * @param string $case_id Case ID to find.
	 * @param string $api_token API token.
	 * @param int    $website_property_id Website property ID.
	 * @return array|null Case data or null if not found.
	 */
	private static function find_case_by_id( string $case_id, string $api_token, int $website_property_id ): ?array {
		// First, try to get the case from cached data
		$cache_key = 'brag_book_all_cases_' . md5( $api_token . $website_property_id );
		$cached_data = get_transient( $cache_key );
		$case_data = null;

		if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) ) {
			// Search for the case in cached data
			foreach ( $cached_data['data'] as $case ) {
				if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					return $case;
				}
			}
		}

		// If not found in cache, try to fetch all cases
		$all_cases = Data_Fetcher::get_all_cases_for_filtering( $api_token, (string) $website_property_id );

		if ( ! empty( $all_cases['data'] ) ) {
			foreach ( $all_cases['data'] as $case ) {
				if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					return $case;
				}
			}
		}

		// Try using Endpoints as last resort
		$endpoints = new Endpoints();
		$filter_body = [
			'apiTokens' => [ $api_token ],
			'websitePropertyIds' => [ $website_property_id ],
			'count' => 1,
			'limit' => 100,
		];

		$response = $endpoints->bb_get_pagination_data( $filter_body );

		if ( ! empty( $response ) ) {
			$response_data = json_decode( $response, true );

			if ( ! empty( $response_data['data'] ) ) {
				foreach ( $response_data['data'] as $case ) {
					if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
						return $case;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Helper: Format case data for frontend.
	 *
	 * @since 3.0.0
	 * @param array  $case_data Case data from API.
	 * @param string $case_id Case ID.
	 * @return array Formatted case data.
	 */
	private static function format_case_data_for_frontend( array $case_data, string $case_id ): array {
		$procedure_names = [];
		if ( ! empty( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			$procedure_names[] = $case_data['technique'] ?? 'Procedure';
		}

		$formatted_data = [
			'caseNumber' => $case_data['id'] ?? $case_id,
			'procedureName' => ! empty( $procedure_names ) ? implode( ', ', $procedure_names ) : ( $case_data['technique'] ?? 'Case Details' ),
			'description' => $case_data['details'] ?? $case_data['description'] ?? '',
			'technique' => $case_data['technique'] ?? '',
			'gender' => $case_data['gender'] ?? '',
			'age' => $case_data['age'] ?? '',
			'photos' => [],
		];

		// Process photoSets
		if ( ! empty( $case_data['photoSets'] ) && is_array( $case_data['photoSets'] ) ) {
			foreach ( $case_data['photoSets'] as $photo_set ) {
				$before_image = $photo_set['postProcessedImageLocation'] ??
								$photo_set['beforeLocationUrl'] ??
								$photo_set['originalBeforeLocation'] ?? '';

				$after_image = $photo_set['afterLocationUrl1'] ??
							   $photo_set['originalAfterLocation1'] ?? '';

				if ( ! empty( $before_image ) || ! empty( $after_image ) ) {
					$formatted_data['photos'][] = [
						'beforeImage' => $before_image,
						'afterImage' => $after_image,
						'caption' => $photo_set['notes'] ?? '',
						'isProcessed' => ! empty( $photo_set['postProcessedImageLocation'] ),
					];
				}
			}
		}

		return $formatted_data;
	}

	/**
	 * Helper: Get procedure nudity map.
	 *
	 * @since 3.0.0
	 * @return array Map of procedure names to nudity boolean values.
	 */
	private static function get_procedure_nudity_map(): array {
		$procedure_nudity_map = [];
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );

		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
			if ( ! empty( $sidebar_data['data'] ) ) {
				foreach ( $sidebar_data['data'] as $category ) {
					if ( ! empty( $category['procedures'] ) ) {
						foreach ( $category['procedures'] as $procedure ) {
							if ( ! empty( $procedure['nudity'] ) ) {
								$procedure_nudity_map[ $procedure['name'] ] = filter_var( $procedure['nudity'], FILTER_VALIDATE_BOOLEAN );
							}
						}
					}
				}
			}
		}

		return $procedure_nudity_map;
	}

	/**
	 * Generate JavaScript for filtered gallery functionality.
	 *
	 * @since 3.0.0
	 * @param array $cases Case data array.
	 * @param string $procedure_ids Comma-separated procedure IDs.
	 * @return string JavaScript code.
	 */
	private static function generate_filtered_gallery_scripts( array $cases, string $procedure_ids ): string {
		$script = '<script>
			// Store complete dataset for filter generation
			window.bragBookCompleteDataset = ' . json_encode( array_map( function( $case ) {
				return [
					'id' => $case['id'] ?? '',
					'age' => $case['age'] ?? $case['patientAge'] ?? '',
					'gender' => $case['gender'] ?? $case['patientGender'] ?? '',
					'ethnicity' => $case['ethnicity'] ?? $case['patientEthnicity'] ?? '',
					'height' => $case['height'] ?? $case['patientHeight'] ?? '',
					'weight' => $case['weight'] ?? $case['patientWeight'] ?? '',
				];
			}, $cases ) ) . ';

			// Initialize procedure filters after data is available
			setTimeout(function() {
				if (typeof initializeProcedureFilters === "function") {
					initializeProcedureFilters();
				}
				// Hook into demographic filter updates for badges
				if (window.updateDemographicFilterBadges && window.activeFilters) {
					window.updateDemographicFilterBadges(window.activeFilters);
				}
			}, 100);

			// Load more cases function
			window.loadMoreCases = function(button) {
				// Disable button and show loading state
				button.disabled = true;
				const originalText = button.textContent;
				button.textContent = "Loading...";

				// Get data from button attributes
				const startPage = button.getAttribute("data-start-page");
				const procedureIds = button.getAttribute("data-procedure-ids");

				// Prepare AJAX data
				const formData = new FormData();
				formData.append("action", "brag_book_load_more_cases");
				formData.append("nonce", "' . wp_create_nonce( 'brag_book_gallery_nonce' ) . '");
				formData.append("start_page", startPage);
				formData.append("procedure_ids", procedureIds);
				formData.append("has_nudity", "' . ( $has_nudity ? '1' : '0' ) . '");

				// Make AJAX request
				fetch("' . admin_url( 'admin-ajax.php' ) . '", {
					method: "POST",
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						// Add new cases to the container
						const container = document.querySelector(".brag-book-gallery-case-grid");
						if (container) {
							container.insertAdjacentHTML("beforeend", data.data.html);
						}

						// Update button for next load
						if (data.data.hasMore) {
							button.setAttribute("data-start-page", parseInt(startPage) + 1);
							button.disabled = false;
							button.textContent = originalText;
						} else {
							// No more cases, hide the button
							button.parentElement.style.display = "none";
						}

						// Count display removed - now using filter badges instead
					} else {
						console.error("Failed to load more cases:", data.data.message);
						button.disabled = false;
						button.textContent = originalText;
					}
				})
				.catch(error => {
					console.error("Error loading more cases:", error);
					button.disabled = false;
					button.textContent = originalText;
				});
			};

			// Update grid layout function
			function updateGridLayout(columns) {
				const grid = document.querySelector(".brag-book-gallery-case-grid");
				const cards = document.querySelectorAll(".brag-book-gallery-case-card[data-card=\"true\"]");
				const buttons = document.querySelectorAll(".brag-book-gallery-grid-btn");

				if (grid && cards.length > 0) {
					// Update grid data attribute
					grid.setAttribute("data-columns", columns);

					// Update button states
					buttons.forEach(btn => {
						if (parseInt(btn.getAttribute("data-columns")) === columns) {
							btn.classList.add("active");
						} else {
							btn.classList.remove("active");
						}
					});
				}
			}

			// Set initial active state
			document.addEventListener("DOMContentLoaded", function() {
				const defaultBtn = document.querySelector(".brag-book-gallery-grid-btn[data-columns=\"3\"]");
				if (defaultBtn) {
					defaultBtn.classList.add("active");
				}
			});
		</script>';
		
		return $script;
	}
}
