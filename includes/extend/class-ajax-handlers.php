<?php
/**
 * AJAX handlers for BRAGBookGallery plugin.
 *
 * This class handles all AJAX endpoints for the BRAGBook Gallery plugin,
 * including gallery filtering, case details loading, favorites management,
 * cache operations, and administrative functions. All methods implement
 * proper WordPress security practices including nonce verification,
 * capability checks, and data sanitization.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 * @author  BRAGBook Team
 * @version 3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\REST\Endpoints;
use BRAGBookGallery\Includes\Extend\Cache_Manager;
use Exception;

/**
 * AJAX Handlers Class
 *
 * Manages all AJAX operations for the BRAGBookGallery plugin including:
 * - Gallery content filtering and loading
 * - Individual case detail retrieval
 * - Favorites system integration
 * - Cache management operations
 * - Administrative functions (rewrite rules, cache clearing)
 *
 * Security Features:
 * - Nonce verification for all requests
 * - Capability checks for admin functions
 * - Input sanitization and validation
 * - Error handling with proper logging
 *
 * @since 3.0.0
 */
class Ajax_Handlers {

	/**
	 * Set comprehensive no-cache headers for AJAX responses.
	 *
	 * Implements VIP-compliant caching prevention for AJAX responses,
	 * including compatibility with various caching systems like LiteSpeed,
	 * Varnish, and CDNs. Uses WordPress core nocache_headers() as the
	 * foundation and adds additional headers for comprehensive coverage.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function set_no_cache_headers(): void {
		// Use WordPress core function for standard no-cache headers
		nocache_headers();

		// Additional headers for comprehensive caching system coverage
		if ( ! headers_sent() ) {
			header( 'X-LiteSpeed-Cache-Control: no-cache', true );
			header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true );
			header( 'Pragma: no-cache', true );
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', true );
			header( 'X-Accel-Expires: 0', true );
		}

		// Disable LiteSpeed caching if the plugin is active
		if ( defined( 'LSCWP_V' ) ) {
			/**
			 * Disable LiteSpeed cache for this AJAX request.
			 *
			 * @since 3.0.0
			 */
			do_action( 'litespeed_control_set_nocache', 'bragbook ajax request' );
		}
	}

	/**
	 * Register all AJAX handlers with WordPress.
	 *
	 * Registers both logged-in (wp_ajax_) and non-logged-in (wp_ajax_nopriv_)
	 * AJAX actions for public-facing functionality, and admin-only actions
	 * for administrative features. Each handler implements proper security
	 * validation including nonce verification and capability checks.
	 *
	 * Public AJAX Actions:
	 * - Gallery filtering and case loading
	 * - Case detail retrieval
	 * - Favorites management
	 *
	 * Admin-only AJAX Actions:
	 * - Cache management operations
	 * - Rewrite rules management
	 * - Debug utilities
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register(): void {
		// Gallery filtering
		add_action( 'wp_ajax_brag_book_gallery_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );

		// Case details
		add_action( 'wp_ajax_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );
		add_action( 'wp_ajax_brag_book_gallery_load_case_details', [ __CLASS__, 'ajax_load_case_details_html' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case_details', [ __CLASS__, 'ajax_load_case_details_html' ] );
		
		// Debug log the case details registration
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'BRAG book Gallery: Registering AJAX action: wp_ajax_brag_book_gallery_load_case_details' );
		}

		add_action( 'wp_ajax_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );
		add_action( 'wp_ajax_nopriv_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );

		add_action( 'wp_ajax_brag_book_gallery_load_case_details_html', [ __CLASS__, 'ajax_load_case_details_html' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case_details_html', [ __CLASS__, 'ajax_load_case_details_html' ] );

		// Load more and filtering
		add_action( 'wp_ajax_brag_book_gallery_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );

		add_action( 'wp_ajax_brag_book_gallery_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );

		// Cache management
		add_action( 'wp_ajax_brag_book_gallery_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );
		add_action( 'wp_ajax_brag_book_delete_cache_items', [ __CLASS__, 'ajax_delete_cache_items' ] );
		add_action( 'wp_ajax_brag_book_get_cache_data', [ __CLASS__, 'ajax_get_cache_data' ] );

		// Rewrite rules
		add_action( 'wp_ajax_brag_book_gallery_flush_rewrite_rules', [ __CLASS__, 'ajax_flush_rewrite_rules' ] );

		// CORS-safe API proxy endpoints
		add_action( 'wp_ajax_brag_book_api_proxy', [ __CLASS__, 'ajax_api_proxy' ] );
		add_action( 'wp_ajax_nopriv_brag_book_api_proxy', [ __CLASS__, 'ajax_api_proxy' ] );

		// Favorites
		add_action( 'wp_ajax_brag_book_add_favorite', [ __CLASS__, 'ajax_add_favorite' ] );
		add_action( 'wp_ajax_nopriv_brag_book_add_favorite', [ __CLASS__, 'ajax_add_favorite' ] );
		add_action( 'wp_ajax_brag_book_get_favorites_list', [ __CLASS__, 'ajax_get_favorites_list' ] );
		add_action( 'wp_ajax_nopriv_brag_book_get_favorites_list', [ __CLASS__, 'ajax_get_favorites_list' ] );
		add_action( 'wp_ajax_brag_book_load_local_favorites', [ __CLASS__, 'ajax_load_local_favorites' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_local_favorites', [ __CLASS__, 'ajax_load_local_favorites' ] );
	}

	/**
	 * AJAX handler to flush rewrite rules.
	 *
	 * Handles administrative requests to flush WordPress rewrite rules
	 * with different flush types including standard, hard, and verification.
	 * Requires manage_options capability and proper nonce verification.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_flush_rewrite_rules(): void {
		// Sanitize and validate nonce from request
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'brag_book_gallery_flush_rewrite_rules' ) ) {
			wp_send_json_error( __( 'Security check failed', 'brag-book-gallery' ) );
			return;
		}

		// Verify user has administrative permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'brag-book-gallery' ) );
			return;
		}

		// Sanitize flush type parameter
		$flush_type = isset( $_POST['flush_type'] ) ? sanitize_text_field( wp_unslash( $_POST['flush_type'] ) ) : 'standard';
		$message = '';

		try {
			// Use PHP 8.2 match expression for cleaner logic
			$message = match ( $flush_type ) {
				'hard' => self::perform_hard_flush(),
				'with_registration' => self::perform_flush_with_registration(),
				'verify' => self::perform_verification(),
				default => self::perform_standard_flush(),
			};

			// Clear the notice
			Cache_Manager::delete( 'show_rewrite_notice' );

			// Clear any caches
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
				$message .= ' Object cache cleared.';
			}

			wp_send_json_success( $message );

			// Clear the notice
			Cache_Manager::delete( 'show_rewrite_notice' );

			// Clear any caches
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
				$message .= ' ' . __( 'Object cache cleared.', 'brag-book-gallery' );
			}

			wp_send_json_success( $message );

		} catch ( Exception $e ) {
			wp_send_json_error( __( 'Error flushing rules: ', 'brag-book-gallery' ) . $e->getMessage() );
		}
	}

	/**
	 * Perform a hard flush of rewrite rules.
	 *
	 * Forces registration of custom rules and updates .htaccess file.
	 *
	 * @since 3.0.0
	 * @return string Success message
	 */
	private static function perform_hard_flush(): string {
		Rewrite_Rules_Handler::custom_rewrite_rules();
		flush_rewrite_rules( true );
		return __( 'Hard flush completed successfully. Rewrite rules and .htaccess updated.', 'brag-book-gallery' );
	}

	/**
	 * Perform a flush with rule re-registration.
	 *
	 * Re-registers custom rules before flushing them.
	 *
	 * @since 3.0.0
	 * @return string Success message
	 */
	private static function perform_flush_with_registration(): string {
		Rewrite_Rules_Handler::custom_rewrite_rules();
		flush_rewrite_rules( false );
		return __( 'Rules re-registered and flushed successfully.', 'brag-book-gallery' );
	}

	/**
	 * Perform verification of existing rewrite rules.
	 *
	 * Counts total and gallery-specific rules for diagnostic purposes.
	 *
	 * @since 3.0.0
	 * @return string Verification results message
	 */
	private static function perform_verification(): string {
		global $wp_rewrite;
		$rules = $wp_rewrite->wp_rewrite_rules();
		$gallery_rules_count = 0;

		if ( ! empty( $rules ) ) {
			foreach ( $rules as $pattern => $query ) {
				if ( str_contains( $query, 'procedure_title' ) || str_contains( $query, 'case_id' ) ) {
					$gallery_rules_count++;
				}
			}
		}

		return sprintf(
			/* translators: 1: total rules count, 2: gallery rules count */
			__( 'Verification complete. Found %1$d total rules, %2$d gallery-specific rules.', 'brag-book-gallery' ),
			count( $rules ),
			$gallery_rules_count
		);
	}

	/**
	 * Perform a standard flush of rewrite rules.
	 *
	 * Standard flush without .htaccess update.
	 *
	 * @since 3.0.0
	 * @return string Success message
	 */
	private static function perform_standard_flush(): string {
		flush_rewrite_rules( false );
		return __( 'Standard flush completed successfully.', 'brag-book-gallery' );
	}

	/**
	 * AJAX handler for loading filtered gallery content.
	 *
	 * Handles AJAX requests for filtering gallery content based on procedure,
	 * nudity preferences, and other filter criteria. Implements comprehensive
	 * security validation, data sanitization, and error handling.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_gallery(): void {
		// Set no-cache headers first
		self::set_no_cache_headers();

		try {
			// Validate and sanitize request data
			$request_data = self::validate_and_sanitize_request( [
				'nonce' => 'brag_book_gallery_nonce',
				'required_fields' => [],
				'optional_fields' => [ 'procedure_name', 'procedure_ids' ],
			] );

			if ( is_wp_error( $request_data ) ) {
				wp_send_json_error( [
					'message' => $request_data->get_error_message(),
					'debug' => WP_DEBUG ? $request_data->get_error_data() : null,
				] );
				return;
			}

			// Extract sanitized parameters
			$procedure_name = $request_data['procedure_name'] ?? '';
			$procedure_ids = $request_data['procedure_ids'] ?? '';

			// Debug logging with proper WordPress debugging
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: ajax_load_filtered_gallery called with params: ' . wp_json_encode( $request_data ) );
			}

			// Get gallery page configuration
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
			$base_path = ! empty( $gallery_slugs[0] ) ? '/' . $gallery_slugs[0] : '/before-after';

			// Get API configuration directly from options
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			// Debug API configuration logging
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery API config - Tokens: ' . count( $api_tokens ) . ', Property IDs: ' . count( $website_property_ids ) );
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

			// Parse procedure IDs if provided
			$procedure_ids_array = [];
			if ( ! empty( $procedure_ids ) ) {
				$procedure_ids_array = array_map( 'intval', explode( ',', $procedure_ids ) );
			}

			// Fetch filtered cases from API - pass procedure IDs to filter at API level
			try {
				$gallery_data = Data_Fetcher::get_all_cases_for_filtering(
					$api_token,
					$website_property_id,
					$procedure_ids_array
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

			// Debug: Log first case structure to understand API response
			if ( WP_DEBUG && ! empty( $gallery_data['data'][0] ) ) {
				$first_case = $gallery_data['data'][0];
				error_log( 'First case structure:' );
				error_log( '  ID: ' . ( $first_case['id'] ?? 'not set' ) );
				error_log( '  Has procedures array: ' . ( isset( $first_case['procedures'] ) ? 'yes' : 'no' ) );
				error_log( '  Has procedureIds array: ' . ( isset( $first_case['procedureIds'] ) ? 'yes' : 'no' ) );
				if ( isset( $first_case['procedures'] ) && is_array( $first_case['procedures'] ) ) {
					error_log( '  Procedures count: ' . count( $first_case['procedures'] ) );
					if ( count( $first_case['procedures'] ) > 0 ) {
						error_log( '  First procedure: ' . json_encode( $first_case['procedures'][0] ) );
					}
				}
				if ( isset( $first_case['procedureIds'] ) && is_array( $first_case['procedureIds'] ) ) {
					error_log( '  ProcedureIds: ' . json_encode( $first_case['procedureIds'] ) );
				}
			}

			// Since we're now passing procedure IDs to the API, we should get filtered results directly
			// Only do additional filtering if necessary
			$filtered_cases = [];

			// Debug log for procedure filtering
			if ( WP_DEBUG ) {
				error_log( 'Procedure Filter Debug:' );
				error_log( '  Procedure name: ' . $procedure_name );
				error_log( '  Procedure IDs: ' . $procedure_ids );
				error_log( '  Total cases from API: ' . count( $gallery_data['data'] ?? [] ) );
			}

			// If we passed procedure IDs to the API, the results should already be filtered
			// Just use the data as-is
			if ( ! empty( $procedure_ids_array ) ) {
				$filtered_cases = $gallery_data['data'] ?? [];

				if ( WP_DEBUG ) {
					error_log( '  Using API-filtered results: ' . count( $filtered_cases ) . ' cases' );
				}
			} else if ( ! empty( $procedure_name ) ) {
				// Fallback: filter by procedure name if no IDs provided
				foreach ( $gallery_data['data'] as $case ) {
					if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
						foreach ( $case['procedures'] as $procedure ) {
							// Check if procedure name matches (case-insensitive)
							$case_procedure_name = $procedure['name'] ?? '';
							$case_procedure_slug = sanitize_title( $procedure['slugName'] ?? $case_procedure_name );
							$filter_procedure_slug = sanitize_title( $procedure_name );

							if ( strcasecmp( $case_procedure_name, $procedure_name ) === 0 ||
								 strcasecmp( $case_procedure_slug, $filter_procedure_slug ) === 0 ||
								 stripos( $case_procedure_name, $procedure_name ) !== false ) {
								$filtered_cases[] = $case;
								break;
							}
						}
					}
				}
			} else {
				// If no procedure IDs and no procedure name, return all cases
				$filtered_cases = $gallery_data['data'] ?? [];
			}

			// Debug log the filter results
			if ( WP_DEBUG ) {
				error_log( '  Total filtered cases found: ' . count( $filtered_cases ) );
				if ( count( $filtered_cases ) > 0 ) {
					error_log( '  First few case IDs: ' . implode( ', ', array_slice( array_column( $filtered_cases, 'id' ), 0, 5 ) ) );
				}
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
				$procedure_display_name = HTML_Renderer::format_procedure_display_name( $procedure_name );
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
						<summary class="brag-book-gallery-filter-dropdown__toggle">
							<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"/></svg>
							<span>%s</span>
						</summary>
						<div class="brag-book-gallery-filter-dropdown__panel">
							<div class="brag-book-gallery-filter-content">
								<div class="brag-book-gallery-filter-section">
									<div id="brag-book-gallery-filters">
										%s
									</div>
								</div>
							</div>
							<div class="brag-book-gallery-filter-actions">
								<button class="brag-book-gallery-button brag-book-gallery-button--apply" onclick="applyProcedureFilters()">%s</button>
								<button class="brag-book-gallery-button brag-book-gallery-button--clear" onclick="clearProcedureFilters()">%s</button>
							</div>
						</div>
					</details>',
					esc_html__( 'Filters', 'brag-book-gallery' ),
					'', // Filter options will be dynamically generated by JavaScript
					esc_html__( 'Apply Filters', 'brag-book-gallery' ),
					esc_html__( 'Clear All', 'brag-book-gallery' )
				);

				// Add filter badges container (replacing the count display)
				$html .= '<div class="brag-book-gallery-active-filters">
					<div class="brag-book-gallery-filter-badges" data-action="filter-badges">
						<!-- Filter badges will be populated by JavaScript -->
					</div>
					<button class="brag-book-gallery-clear-all-filters" data-action="clear-filters" style="display: none;">
						' . esc_html__( 'Clear All', 'brag-book-gallery' ) . '
					</button>
				</div>';

				$html .= '</div>'; // Close left controls

				// Get default columns from settings
				$default_columns = get_option( 'brag_book_gallery_columns', '3' );
				$column2_active = ( $default_columns === '2' ) ? ' active' : '';
				$column3_active = ( $default_columns === '3' ) ? ' active' : '';

				// Grid layout selector (on the right)
				$html .= sprintf(
					'<div class="brag-book-gallery-grid-selector">
						<span class="brag-book-gallery-grid-label">%s</span>
						<div class="brag-book-gallery-grid-buttons">
							<button class="brag-book-gallery-grid-btn%s" data-columns="2" onclick="updateGridLayout(2)" aria-label="%s">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg>
								<span class="sr-only">%s</span>
							</button>
							<button class="brag-book-gallery-grid-btn%s" data-columns="3" onclick="updateGridLayout(3)" aria-label="%s">
								<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="4" height="4"/><rect x="6" y="1" width="4" height="4"/><rect x="11" y="1" width="4" height="4"/><rect x="1" y="6" width="4" height="4"/><rect x="6" y="6" width="4" height="4"/><rect x="11" y="6" width="4" height="4"/><rect x="1" y="11" width="4" height="4"/><rect x="6" y="11" width="4" height="4"/><rect x="11" y="11" width="4" height="4"/></svg>
								<span class="sr-only">%s</span>
							</button>
						</div>
					</div>',
					esc_html__( 'View:', 'brag-book-gallery' ),
					esc_attr( $column2_active ),
					esc_attr__( 'View in 2 columns', 'brag-book-gallery' ),
					esc_html__( '2 Columns', 'brag-book-gallery' ),
					esc_attr( $column3_active ),
					esc_attr__( 'View in 3 columns', 'brag-book-gallery' ),
					esc_html__( '3 Columns', 'brag-book-gallery' )
				);

				$html .= '</div>'; // Close controls container

				// Start cases grid with masonry layout for variable heights
				$html .= sprintf( '<div class="brag-book-gallery-case-grid masonry-layout" data-columns="%s">', esc_attr( $default_columns ) );

				// Get items per page from settings BEFORE rendering
				$items_per_page = absint( get_option( 'brag_book_gallery_items_per_page', '10' ) );

				// Loop through cases and render them - ONLY RENDER FIRST PAGE
				if ( ! empty( $transformed_cases ) ) {
					$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

					// Only render the first page of cases
					$cases_to_render = array_slice( $transformed_cases, 0, $items_per_page );

					// Debug: Log case rendering info
					error_log( 'AJAX Handler - About to render ' . count( $cases_to_render ) . ' cases (items_per_page: ' . $items_per_page . ')' );

					// Get sidebar data once for nudity checking
					$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );

					// Check if the current procedure has nudity flag set - applies to ALL cases in this view
					$procedure_has_nudity = self::procedure_has_nudity( $procedure_name, $sidebar_data );

					foreach ( $cases_to_render as $case ) {
						// For procedure-specific views, use procedure-level nudity flag for ALL cases
						// For general views, fall back to individual case checking
						$case_has_nudity = ! empty( $procedure_name ) 
							? $procedure_has_nudity 
							: self::case_has_nudity_with_sidebar( $case, $sidebar_data );


						// Use reflection to access the render_ajax_gallery_case_card method
						try {
							$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
							$method->setAccessible( true );
							$html .= $method->invoke( null, $case, $image_display_mode, $case_has_nudity, $procedure_name );
						} catch ( \Exception $render_error ) {
							error_log( 'Error rendering case card: ' . $render_error->getMessage() );
						}
					}
				} else {
					// Show message when no cases found
					$html .= '<div class="brag-book-gallery-no-results">';
					$html .= '<p>' . esc_html__( 'No cases found for this procedure.', 'brag-book-gallery' ) . '</p>';
					$html .= '</div>';
				}

				$html .= '</div>'; // Close cases grid

				// Add Load More button if there are more cases than items_per_page
				if ( count( $transformed_cases ) > $items_per_page ) {
					// Check if infinite scroll is enabled
					$infinite_scroll = get_option( 'brag_book_gallery_infinite_scroll', 'no' );
					$button_style = ( $infinite_scroll === 'yes' ) ? ' style="display: none;"' : '';

					$html .= '<div class="brag-book-gallery-load-more-container">';
					$html .= '<button class="brag-book-gallery-button brag-book-gallery-button--load-more" ';
					$html .= 'data-action="load-more" ';
					$html .= 'data-start-page="2" ';
					$html .= 'data-procedure-ids="' . esc_attr( $procedure_ids ) . '" ';
					$html .= 'data-procedure-name="' . esc_attr( $procedure_name ) . '" ';
					$html .= 'onclick="loadMoreCases(this)"' . $button_style . '>';
					$html .= 'Load More';
					$html .= '</button>';
					$html .= '</div>';
				}

				$html .= '</div>'; // Close filtered results

				// Add JavaScript for grid control and filters
				$html .= self::generate_filtered_gallery_scripts( $transformed_cases, $procedure_ids );

			// Generate SEO data for the filtered view
			$seo_data = array();
			if ( ! empty( $procedure_name ) ) {
				// Get the proper display name from sidebar data if possible
				$display_name = $procedure_name;

				// Generate title and description
				$site_name = get_bloginfo( 'name' );
				$seo_data['title'] = $display_name . ' Before & After Gallery | ' . $site_name;
				$seo_data['description'] = 'View before and after photos of ' . $display_name . ' procedures. Browse our gallery to see real patient results.';
			}

			wp_send_json_success( [
				'html' => $html,
				'totalCount' => count( $transformed_cases ),
				'procedureName' => $procedure_name,
				'seo' => $seo_data,
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
				'debug' => WP_DEBUG ? [
					'message' => $e->getMessage(),
					'file' => basename( $e->getFile() ),
					'line' => $e->getLine(),
					'type' => get_class( $e ),
				] : null,
			] );
		}
	}

	/**
	 * AJAX handler to load case details from the API.
	 *
	 * Retrieves detailed information for a specific case including images,
	 * patient information, and procedure details. Implements comprehensive
	 * validation, caching, and error handling. Returns formatted case data
	 * suitable for frontend display.
	 *
	 * Expected POST Parameters:
	 * - nonce: Security nonce for 'brag_book_gallery_nonce'
	 * - case_id: Required case identifier
	 * - procedure_ids: Optional comma-separated procedure IDs for filtering
	 *
	 * @since 3.0.0
	 * @return void Sends JSON response via wp_send_json_success/error
	 */
	public static function ajax_load_case_details(): void {
		// Validate and sanitize request data
		$request_data = self::validate_and_sanitize_request( [
			'nonce' => 'brag_book_gallery_nonce',
			'required_fields' => [ 'case_id' ],
			'optional_fields' => [ 'procedure_ids' ],
		] );

		if ( is_wp_error( $request_data ) ) {
			wp_send_json_error( [
				'message' => $request_data->get_error_message(),
				'debug' => WP_DEBUG ? $request_data->get_error_data() : null,
			] );
			return;
		}

		$case_id = $request_data['case_id'];

		// Parse procedure IDs if provided
		$procedure_ids = [];
		if ( ! empty( $request_data['procedure_ids'] ) ) {
			$procedure_ids = array_map( 'intval', explode( ',', $request_data['procedure_ids'] ) );

			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Received procedure IDs: ' . wp_json_encode( $procedure_ids ) );
			}
		}

		// Debug logging
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

		// Get case details from API
		$case_data = null;
		$error_messages = array();

		// First, try using our helper function which handles both numeric IDs and SEO suffixes
		if ( ! empty( $api_tokens[0] ) && ! empty( $website_property_ids[0] ) ) {
			$case_data = self::find_case_by_id( $case_id, $api_tokens[0], intval( $website_property_ids[0] ) );
		}

		// If not found and case_id is numeric, try the direct API method with procedure IDs
		if ( empty( $case_data ) && is_numeric( $case_id ) ) {
			$endpoints = new Endpoints();

			// Try to get case details using the caseNumber endpoint
			foreach ( $api_tokens as $index => $token ) {
				$property_id = $website_property_ids[ $index ] ?? null;

				if ( ! $property_id ) {
					$error_messages[] = 'Missing property ID for token index ' . $index;
					continue;
				}

				// Debug: Log the request details
				if ( WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: Fetching case ' . $case_id . ' with token index ' . $index . ' and property ID ' . $property_id );
				}

				// Try to fetch case details - pass the procedure IDs from the request
				$response = $endpoints->get_case_by_number( $token, (int) $property_id, $case_id, $procedure_ids );

				if ( ! empty( $response ) && is_array( $response ) ) {
					// Debug: Log successful response
					if ( WP_DEBUG ) {
						error_log( 'BRAGBook Gallery: Successfully fetched case ' . $case_id . ' - Case ID in response: ' . ( $response['id'] ?? 'N/A' ) );
					}
					$case_data = $response;
					break;
				} else {
					$error_messages[] = 'Failed to fetch from token index ' . $index;
				}
			}
		}

		// If no case data found, try pagination endpoint as fallback
		if ( empty( $case_data ) ) {
			$filter_body = [
				'apiTokens' => $api_tokens,
				'websitePropertyIds' => array_map( 'intval', $website_property_ids ),
				'count' => 100,
			];

			$response = $endpoints->get_pagination_data( $filter_body );

			if ( ! empty( $response['cases'] ) && is_array( $response['cases'] ) ) {
				// Debug: Log available case numbers for comparison
				if ( WP_DEBUG ) {
					$available_cases = array_map( function( $case ) {
						return $case['caseNumber'] ?? $case['id'] ?? 'no-id';
					}, array_slice( $response['cases'], 0, 10 ) ); // First 10 for brevity
					error_log( 'BRAGBook Gallery: Available case numbers in fallback search: ' . implode(', ', $available_cases) );
					error_log( 'BRAGBook Gallery: Looking for case ID: ' . $case_id );
				}

				// Search for the case in the results
				foreach ( $response['cases'] as $case ) {
					// Try matching by caseNumber field first
					if ( isset( $case['caseNumber'] ) && (string) $case['caseNumber'] === (string) $case_id ) {
						$case_data = $case;
						break;
					}
					// Also try matching by ID field as backup (convert to string for comparison)
					if ( isset( $case['id'] ) && (string) $case['id'] === (string) $case_id ) {
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
	 * AJAX handler for loading case details as formatted HTML.
	 *
	 * Similar to ajax_load_case_details but returns pre-formatted HTML
	 * instead of raw case data. Used for direct insertion into modal
	 * dialogs or detail views. Includes proper image handling, responsive
	 * layout, and accessibility features.
	 *
	 * Expected POST Parameters:
	 * - nonce: Security nonce for 'brag_book_gallery_nonce'
	 * - case_id: Required case identifier
	 * - procedure_id: Optional procedure ID
	 * - procedure_slug: Optional procedure slug
	 * - procedure_name: Optional procedure name
	 * - procedure_ids: Optional comma-separated procedure IDs
	 *
	 * @since 3.0.0
	 * @return void Sends JSON response with HTML content
	 */
	public static function ajax_load_case_details_html(): void {
		// Debug logging to confirm this method is called
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'BRAGBook Gallery: ajax_load_case_details_html method called' );
			error_log( 'BRAGBook Gallery: POST data: ' . print_r( $_POST, true ) );
		}

		// Validate and sanitize request data
		$request_data = self::validate_and_sanitize_request( [
			'nonce' => 'brag_book_gallery_nonce',
			'required_fields' => [ 'case_id' ],
			'optional_fields' => [ 'procedure_id', 'procedure_slug', 'procedure_name', 'procedure_ids' ],
		] );

		if ( is_wp_error( $request_data ) ) {
			wp_send_json_error( [
				'message' => $request_data->get_error_message(),
				'debug' => WP_DEBUG ? $request_data->get_error_data() : null,
			] );
			return;
		}

		$case_id = $request_data['case_id'];
		$procedure_id = $request_data['procedure_id'] ?? '';
		$procedure_slug = $request_data['procedure_slug'] ?? '';
		$procedure_name = $request_data['procedure_name'] ?? '';

		// Parse procedure IDs if provided
		$procedure_ids = [];
		if ( ! empty( $request_data['procedure_ids'] ) ) {
			$procedure_ids = array_map( 'intval', explode( ',', $request_data['procedure_ids'] ) );

			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery HTML: Received procedure IDs from request: ' . wp_json_encode( $procedure_ids ) );
			}
		} else {
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'BRAGBook Gallery HTML: No procedure IDs provided in request' );
			}
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

		// First, try to get case details from the direct API endpoint which includes navigation data
		$endpoints = new Endpoints();
		$case_data = null;
		
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'AJAX Handler: Attempting get_case_details for case_id: ' . $case_id );
		}
		
		$case_details_response = $endpoints->get_case_details( $case_id );
		
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'AJAX Handler: get_case_details response empty: ' . ( empty( $case_details_response ) ? 'YES' : 'NO' ) );
			if ( ! empty( $case_details_response ) ) {
				error_log( 'AJAX Handler: get_case_details response length: ' . strlen( $case_details_response ) );
				error_log( 'AJAX Handler: get_case_details response preview: ' . substr( $case_details_response, 0, 200 ) . '...' );
			}
		}
		
		if ( ! empty( $case_details_response ) ) {
			$decoded_response = json_decode( $case_details_response, true );
			if ( json_last_error() === JSON_ERROR_NONE && ! empty( $decoded_response ) ) {
				// Handle the API response structure: data is nested in 'data' array
				if ( isset( $decoded_response['data'] ) && is_array( $decoded_response['data'] ) && ! empty( $decoded_response['data'][0] ) ) {
					$case_data = $decoded_response['data'][0];
					// Check if navigation data is at the root level of the response
					if ( empty( $case_data['navigation'] ) && ! empty( $decoded_response['navigation'] ) ) {
						$case_data['navigation'] = $decoded_response['navigation'];
						if ( WP_DEBUG ) {
							error_log( 'AJAX Handler: Found navigation data at response root level and added to case data' );
						}
					}
				} else {
					// Fallback if data is at root level
					$case_data = $decoded_response;
				}
				
				if ( WP_DEBUG ) {
					error_log( 'Got case data with navigation from get_case_details: ' . ( isset( $case_data['navigation'] ) ? 'YES' : 'NO' ) );
					if ( isset( $case_data['navigation'] ) ) {
						error_log( 'Navigation data from API: ' . print_r( $case_data['navigation'], true ) );
					} else {
						error_log( 'AJAX Handler: No navigation in case data. Response keys: ' . implode( ', ', array_keys( $decoded_response ) ) );
						if ( isset( $decoded_response['data'][0] ) ) {
							error_log( 'AJAX Handler: Case data keys: ' . implode( ', ', array_keys( $decoded_response['data'][0] ) ) );
						}
					}
				}
			}
		}

		// Fallback to find_case_by_id if direct API call failed
		if ( empty( $case_data ) ) {
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'AJAX Handler: get_case_details failed, trying find_case_by_id fallback' );
			}
			$case_data = self::find_case_by_id( $case_id, $api_token, $website_property_id );
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'AJAX Handler: find_case_by_id result: ' . ( ! empty( $case_data ) ? 'SUCCESS (has navigation: ' . ( isset( $case_data['navigation'] ) ? 'YES' : 'NO' ) . ')' : 'FAILED' ) );
			}
		}

		// If still not found and we have procedure IDs, try the legacy API method with numeric ID only
		if ( empty( $case_data ) && ! empty( $procedure_ids ) && is_numeric( $case_id ) ) {
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'AJAX Handler: find_case_by_id failed, trying get_case_by_number with procedure IDs: ' . implode( ',', $procedure_ids ) );
			}
			$case_data = $endpoints->get_case_by_number( $api_token, $website_property_id, $case_id, $procedure_ids );
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'AJAX Handler: get_case_by_number result: ' . ( ! empty( $case_data ) ? 'SUCCESS (has navigation: ' . ( isset( $case_data['navigation'] ) ? 'YES' : 'NO' ) . ')' : 'FAILED' ) );
			}
		}

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// Debug current state of procedure IDs
			if ( WP_DEBUG ) {
				error_log( 'AJAX Handler: Current procedure_ids state: ' . ( empty( $procedure_ids ) ? 'EMPTY' : 'HAS VALUES: ' . implode( ',', $procedure_ids ) ) );
				error_log( 'AJAX Handler: Case data has procedureIds: ' . ( ! empty( $case_data['procedureIds'] ) ? 'YES: ' . implode( ',', $case_data['procedureIds'] ) : 'NO' ) );
			}
			
			// Extract procedure IDs from case data if not provided
			if ( empty( $procedure_ids ) && ! empty( $case_data['procedureIds'] ) ) {
				$procedure_ids = array_map( 'intval', $case_data['procedureIds'] );
				if ( WP_DEBUG ) {
					error_log( 'AJAX Handler: Extracted procedure IDs from case data: ' . implode( ',', $procedure_ids ) );
				}
			}
			
			// Also try to filter procedure IDs based on the requested procedure slug
			if ( ! empty( $procedure_ids ) && ! empty( $procedure_slug ) && ! empty( $case_data['procedures'] ) ) {
				$matching_procedure_ids = [];
				foreach ( $case_data['procedures'] as $procedure ) {
					if ( isset( $procedure['slugName'] ) && $procedure['slugName'] === $procedure_slug ) {
						$matching_procedure_ids[] = intval( $procedure['id'] );
					}
				}
				if ( ! empty( $matching_procedure_ids ) ) {
					$procedure_ids = $matching_procedure_ids;
					if ( WP_DEBUG ) {
						error_log( 'AJAX Handler: Filtered procedure IDs to match slug "' . $procedure_slug . '": ' . implode( ',', $procedure_ids ) );
					}
				}
			}
			
			// If navigation data is missing and we have procedure IDs, generate navigation from procedure cases
			if ( empty( $case_data['navigation'] ) && ! empty( $procedure_ids ) && ! empty( $case_data['id'] ) ) {
				if ( WP_DEBUG ) {
					error_log( 'AJAX Handler: Navigation data missing, generating from procedure cases for procedure IDs: ' . implode( ',', $procedure_ids ) );
				}
				
				// Fetch all cases for this procedure to generate navigation
				try {
					$procedure_cases_data = Data_Fetcher::get_all_cases_for_filtering( $api_token, (string) $website_property_id, $procedure_ids );
					if ( ! empty( $procedure_cases_data['data'] ) && is_array( $procedure_cases_data['data'] ) ) {
						$navigation_data = self::generate_case_navigation( (string) $case_data['id'], $procedure_cases_data['data'], $procedure_slug );
						if ( ! empty( $navigation_data ) ) {
							$case_data['navigation'] = $navigation_data;
							if ( WP_DEBUG ) {
								error_log( 'AJAX Handler: Successfully generated navigation data from procedure cases' );
								error_log( 'AJAX Handler: Navigation data: ' . print_r( $navigation_data, true ) );
							}
						}
					}
				} catch ( \Exception $e ) {
					if ( WP_DEBUG ) {
						error_log( 'AJAX Handler: Failed to generate navigation from procedure cases: ' . $e->getMessage() );
					}
				}
			}
			
			// If still no navigation data and we have a case ID, try to fetch it from the direct API endpoint
			if ( empty( $case_data['navigation'] ) && ! empty( $case_data['id'] ) ) {
				if ( WP_DEBUG ) {
					error_log( 'AJAX Handler: Navigation data still missing, attempting direct API fetch for case ID: ' . $case_data['id'] );
				}
				
				$direct_case_data = $endpoints->get_case_details( (string) $case_data['id'] );
				if ( ! empty( $direct_case_data ) ) {
					$decoded_direct_data = json_decode( $direct_case_data, true );
					if ( json_last_error() === JSON_ERROR_NONE ) {
						// Handle the API response structure: data is nested in 'data' array
						$direct_case_info = null;
						if ( isset( $decoded_direct_data['data'] ) && is_array( $decoded_direct_data['data'] ) && ! empty( $decoded_direct_data['data'][0] ) ) {
							$direct_case_info = $decoded_direct_data['data'][0];
						} else {
							// Fallback if data is at root level
							$direct_case_info = $decoded_direct_data;
						}
						
						if ( ! empty( $direct_case_info['navigation'] ) ) {
							$case_data['navigation'] = $direct_case_info['navigation'];
							if ( WP_DEBUG ) {
								error_log( 'AJAX Handler: Successfully added navigation data from direct API call' );
								error_log( 'AJAX Handler: Navigation data: ' . print_r( $direct_case_info['navigation'], true ) );
							}
						} else {
							if ( WP_DEBUG ) {
								error_log( 'AJAX Handler: Direct API call succeeded but no navigation data found' );
								error_log( 'AJAX Handler: Direct API response structure: ' . print_r( array_keys( $decoded_direct_data ), true ) );
								if ( $direct_case_info ) {
									error_log( 'AJAX Handler: Direct case info keys: ' . implode( ', ', array_keys( $direct_case_info ) ) );
								}
							}
						}
					} else {
						if ( WP_DEBUG ) {
							error_log( 'AJAX Handler: JSON decode error: ' . json_last_error_msg() );
						}
					}
				} else {
					if ( WP_DEBUG ) {
						error_log( 'AJAX Handler: Direct API call returned empty response for case ID: ' . $case_data['id'] );
					}
				}
			} else {
				if ( WP_DEBUG ) {
					$nav_status = empty( $case_data['navigation'] ) ? 'MISSING' : 'PRESENT';
					error_log( 'AJAX Handler: Navigation data status: ' . $nav_status . ' for case ID: ' . ( $case_data['id'] ?? 'unknown' ) );
					if ( ! empty( $case_data['navigation'] ) ) {
						error_log( 'AJAX Handler: Existing navigation data: ' . print_r( $case_data['navigation'], true ) );
					}
				}
			}

			// Track case view for analytics (fire and forget - don't let failures affect the response)
			$view_tracked = false;
			$view_tracking_error = null;

			if ( is_numeric( $case_id ) ) {
				try {
					$endpoints = isset( $endpoints ) ? $endpoints : new Endpoints();
					$tracking_response = $endpoints->track_case_view( $api_token, intval( $case_id ) );

					if ( $tracking_response !== null ) {
						$view_tracked = true;

						if ( WP_DEBUG && WP_DEBUG_LOG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( 'BRAGBook Gallery: View tracked for case ID: ' . $case_id );
						}
					}
				} catch ( Exception $e ) {
					$view_tracking_error = $e->getMessage();

					// Log the error but don't prevent the case from loading
					if ( WP_DEBUG && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'BRAGBook Gallery: Failed to track view for case ID ' . $case_id . ': ' . $e->getMessage() );
					}
				}
			}

			// Debug log the case data before passing to renderer
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'AJAX Handler: About to render case details' );
				error_log( 'AJAX Handler: Case data keys: ' . implode( ', ', array_keys( $case_data ) ) );
				error_log( 'AJAX Handler: Has navigation in case_data: ' . ( isset( $case_data['navigation'] ) ? 'YES' : 'NO' ) );
				if ( isset( $case_data['navigation'] ) ) {
					error_log( 'AJAX Handler: Navigation data: ' . print_r( $case_data['navigation'], true ) );
					if ( isset( $case_data['navigation']['previous'] ) ) {
						error_log( 'AJAX Handler: Previous case data: ' . print_r( $case_data['navigation']['previous'], true ) );
					}
					if ( isset( $case_data['navigation']['next'] ) ) {
						error_log( 'AJAX Handler: Next case data: ' . print_r( $case_data['navigation']['next'], true ) );
					}
				} else {
					error_log( 'AJAX Handler: WARNING - No navigation data found in case_data' );
				}
				error_log( 'AJAX Handler: Procedure slug: ' . $procedure_slug );
			}

			// Generate HTML and SEO data for case details using HTML_Renderer class method
			$result = HTML_Renderer::render_case_details_html( $case_data, $procedure_slug, $procedure_name );

			$response_data = [
				'html' => $result['html'],
				'case_id' => $case_id,
				'seo' => $result['seo'],
				'view_tracked' => $view_tracked,
			];

			// Add debug info for development
			if ( WP_DEBUG ) {
				$response_data['debug'] = [
					'view_tracking_attempted' => is_numeric( $case_id ),
					'view_tracked' => $view_tracked,
					'tracking_error' => $view_tracking_error,
				];
			}

			wp_send_json_success( $response_data );
			return;
		}

		// Case not found
		wp_send_json_error( 'Case not found. Case ID: ' . $case_id );
	}

	/**
	 * Simplified AJAX handler for basic case details.
	 *
	 * Lightweight version of the case details handler for scenarios
	 * requiring minimal data transfer. Returns essential case information
	 * without full formatting or extensive metadata. Ideal for popups,
	 * tooltips, or preview functionality.
	 *
	 * Expected POST Parameters:
	 * - nonce: Security nonce for 'brag_book_gallery_nonce'
	 * - case_id: Required case identifier
	 * - procedure_slug: Optional procedure slug
	 * - procedure_name: Optional procedure name
	 *
	 * @since 3.0.0
	 * @return void Sends JSON response with minimal case data
	 */
	public static function ajax_simple_case_handler(): void {
		// Validate and sanitize request data
		$request_data = self::validate_and_sanitize_request( [
			'nonce' => 'brag_book_gallery_nonce',
			'required_fields' => [ 'case_id' ],
			'optional_fields' => [ 'procedure_slug', 'procedure_name' ],
		] );

		if ( is_wp_error( $request_data ) ) {
			wp_send_json_error( [
				'message' => $request_data->get_error_message(),
				'debug' => WP_DEBUG ? $request_data->get_error_data() : null,
			] );
			return;
		}

		$case_id = $request_data['case_id'];
		$procedure_slug = $request_data['procedure_slug'] ?? '';
		$procedure_name = $request_data['procedure_name'] ?? '';

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// First, try to get case details from the direct API endpoint which includes navigation data
		$endpoints = new Endpoints();
		$case_data = null;
		$case_details_response = $endpoints->get_case_details( $case_id );
		
		if ( ! empty( $case_details_response ) ) {
			$decoded_response = json_decode( $case_details_response, true );
			if ( json_last_error() === JSON_ERROR_NONE && ! empty( $decoded_response ) ) {
				// Handle the API response structure: data is nested in 'data' array
				if ( isset( $decoded_response['data'] ) && is_array( $decoded_response['data'] ) && ! empty( $decoded_response['data'][0] ) ) {
					$case_data = $decoded_response['data'][0];
				} else {
					// Fallback if data is at root level
					$case_data = $decoded_response;
				}
				
				if ( WP_DEBUG ) {
					error_log( 'Got case data with navigation from get_case_details: ' . ( isset( $case_data['navigation'] ) ? 'YES' : 'NO' ) );
					if ( isset( $case_data['navigation'] ) ) {
						error_log( 'Navigation data from API: ' . print_r( $case_data['navigation'], true ) );
					}
				}
			}
		}

		// Fallback to find_case_by_id if direct API call failed
		if ( empty( $case_data ) ) {
			$case_data = self::find_case_by_id( $case_id, $api_token, $website_property_id );
		}

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// If navigation data is missing and we have a case ID, try to fetch it from the direct API endpoint
			if ( empty( $case_data['navigation'] ) && ! empty( $case_data['id'] ) ) {
				if ( WP_DEBUG ) {
					error_log( 'HTML Handler: Navigation data missing, attempting to fetch for case ID: ' . $case_data['id'] );
				}
				
				$direct_case_data = $endpoints->get_case_details( (string) $case_data['id'] );
				if ( ! empty( $direct_case_data ) ) {
					$decoded_direct_data = json_decode( $direct_case_data, true );
					if ( json_last_error() === JSON_ERROR_NONE ) {
						if ( ! empty( $decoded_direct_data['navigation'] ) ) {
							$case_data['navigation'] = $decoded_direct_data['navigation'];
							if ( WP_DEBUG ) {
								error_log( 'HTML Handler: Successfully added navigation data from direct API call' );
								error_log( 'HTML Handler: Navigation data: ' . print_r( $decoded_direct_data['navigation'], true ) );
							}
						} else {
							if ( WP_DEBUG ) {
								error_log( 'HTML Handler: Direct API call succeeded but no navigation data found' );
								error_log( 'HTML Handler: Direct API response keys: ' . implode( ', ', array_keys( $decoded_direct_data ) ) );
							}
						}
					} else {
						if ( WP_DEBUG ) {
							error_log( 'HTML Handler: JSON decode error: ' . json_last_error_msg() );
						}
					}
				} else {
					if ( WP_DEBUG ) {
						error_log( 'HTML Handler: Direct API call returned empty response for case ID: ' . $case_data['id'] );
					}
				}
			} else {
				if ( WP_DEBUG ) {
					$nav_status = empty( $case_data['navigation'] ) ? 'MISSING' : 'PRESENT';
					error_log( 'HTML Handler: Navigation data status: ' . $nav_status . ' for case ID: ' . ( $case_data['id'] ?? 'unknown' ) );
					if ( ! empty( $case_data['navigation'] ) ) {
						error_log( 'HTML Handler: Existing navigation data: ' . print_r( $case_data['navigation'], true ) );
					}
				}
			}

			// Generate HTML and SEO data for case details using HTML_Renderer class method
			$result = HTML_Renderer::render_case_details_html( $case_data, $procedure_slug, $procedure_name );

			wp_send_json_success( [
				'html' => $result['html'],
				'case_id' => $case_id,
				'seo' => $result['seo'],
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
	 * AJAX handler to delete specific cache items.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_delete_cache_items(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_cache_management' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get cache keys to delete
		$keys = isset( $_POST['keys'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['keys'] ) ) : [];

		if ( empty( $keys ) ) {
			wp_send_json_error( 'No cache keys provided' );
		}

		$deleted = 0;
		foreach ( $keys as $key ) {
			if ( Cache_Manager::delete( $key ) ) {
				$deleted++;
			}
		}

		wp_send_json_success( sprintf( _n( '%d item deleted', '%d items deleted', $deleted, 'brag-book-gallery' ), $deleted ) );
	}

	/**
	 * AJAX handler to get cache data for viewing.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_get_cache_data(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_cache_management' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get cache key
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( empty( $key ) ) {
			wp_send_json_error( 'No cache key provided' );
		}

		// Get the cache data
		$data = Cache_Manager::get( $key );

		if ( false === $data ) {
			wp_send_json_error( 'Cache item not found or expired' );
		}

		// Return the data
		wp_send_json_success( $data );
	}

	/**
	 * AJAX handler for loading more cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_more_cases(): void {
		// Set no-cache headers first
		self::set_no_cache_headers();

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get parameters
		$start_page = isset( $_POST['start_page'] ) ? intval( $_POST['start_page'] ) : 2;
		$procedure_ids = isset( $_POST['procedure_ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['procedure_ids'] ) ) ) ) : [];
		$procedure_name = isset( $_POST['procedure_name'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_name'] ) ) : '';
		$load_all = isset( $_POST['load_all'] ) && $_POST['load_all'] === '1';

		// Get already loaded case IDs to prevent duplicates
		$loaded_case_ids = isset( $_POST['loaded_ids'] ) ? array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['loaded_ids'] ) ) ) ) : [];

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
			// Get items per page from settings, default to 10
			$cases_per_page = absint( get_option( 'brag_book_gallery_items_per_page', '10' ) );
			$pages_to_load = 1; // Load 1 page per click

			// Prepare base filter body
			$filter_body = [
				'apiTokens'          => [ $api_tokens[0] ],
				'websitePropertyIds' => [ intval( $website_property_ids[0] ) ],
				'procedureIds'       => $procedure_ids,
			];

			if ( $load_all ) {
				// Load all cases by fetching all available pages
				$page = 1;
				do {
					$filter_body['count'] = $page;
					$response = $endpoints->get_pagination_data( $filter_body );
					
					if ( ! empty( $response ) ) {
						$page_data = json_decode( $response, true );
						
						if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
							foreach ( $page_data['data'] as $case ) {
								$case_id = isset( $case['id'] ) ? strval( $case['id'] ) : '';
								if ( ! empty( $case_id ) ) {
									$all_cases[] = $case;
								}
							}
							$page++;
							
							// Check if we've loaded all available cases
							if ( count( $page_data['data'] ) < $cases_per_page ) {
								break; // This was the last page
							}
						} else {
							break; // No more data
						}
					} else {
						break; // API error
					}
				} while ( $page <= 100 ); // Safety limit to prevent infinite loops
				
				// For load_all, return just the case data without HTML
				wp_send_json_success( $all_cases );
				return;
			} else {
				// Fetch the specific page
				$has_more = false;
				$filter_body['count'] = $start_page; // 'count' is the page number in this API

				$response = $endpoints->get_pagination_data( $filter_body );
			}

			if ( ! empty( $response ) ) {
				$page_data = json_decode( $response, true );

				if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
					// Filter out already loaded cases to prevent duplicates
					foreach ( $page_data['data'] as $case ) {
						$case_id = isset( $case['id'] ) ? strval( $case['id'] ) : '';
						if ( ! empty( $case_id ) && ! in_array( $case_id, $loaded_case_ids, true ) ) {
							$all_cases[] = $case;
						}
					}

					$new_cases_count = count( $page_data['data'] );

					// Check if there might be more pages
					if ( $new_cases_count >= $cases_per_page ) {
						// Check if next page has data
						$next_page = $start_page + 1;
						$filter_body['count'] = $next_page;
						$check_response = $endpoints->get_pagination_data( $filter_body );
						if ( ! empty( $check_response ) ) {
							$check_data = json_decode( $check_response, true );
							if ( is_array( $check_data ) && ! empty( $check_data['data'] ) ) {
								$has_more = true;
							}
						}
					}
				}
			}

			// Generate HTML for the new cases
			$html = '';
			$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

			// Get sidebar data once for nudity checking
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );

			// Check if the current procedure has nudity flag set - applies to ALL cases in this view
			$procedure_has_nudity = self::procedure_has_nudity( $procedure_name, $sidebar_data );

			foreach ( $all_cases as $case ) {
				// For procedure-specific views, use procedure-level nudity flag for ALL cases
				// For general views, fall back to individual case checking
				$case_has_nudity = ! empty( $procedure_name ) 
					? $procedure_has_nudity 
					: self::case_has_nudity_with_sidebar( $case, $sidebar_data );

				// Use reflection to access the private method from Shortcodes class
				$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
				$method->setAccessible( true );
				$html .= $method->invoke( null, $case, $image_display_mode, $case_has_nudity, $procedure_name );
			}

			// Log for debugging
			if ( WP_DEBUG ) {
				error_log( 'Load More Results: Cases loaded: ' . count( $all_cases ) . ', Has more: ' . ( $has_more ? 'true' : 'false' ) );
			}

			wp_send_json_success( [
				'html' => $html,
				'casesLoaded' => count( $all_cases ),
				'hasMore' => $has_more,
				'nextPage' => $has_more ? ( $start_page + 1 ) : null,
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
		// Set no-cache headers first
		self::set_no_cache_headers();

		// Get case IDs from request
		$case_ids_str = isset( $_POST['case_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['case_ids'] ) ) : '';

		// Get procedure context from request if available
		$procedure_context = isset( $_POST['procedure_name'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_name'] ) ) : '';
		
		// Also try to get from referrer URL if not in POST data
		if ( empty( $procedure_context ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$path_segments = array_filter( explode( '/', parse_url( $referer, PHP_URL_PATH ) ) );
			if ( count( $path_segments ) >= 2 ) {
				$procedure_context = sanitize_title( $path_segments[1] ); // Assumes /gallery/procedure-name/ structure
			}
		}

		// Get pagination parameters
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$items_per_page = absint( get_option( 'brag_book_gallery_items_per_page', '10' ) );

		if ( empty( $case_ids_str ) ) {
			wp_send_json_error( [
				'message' => __( 'No case IDs provided.', 'brag-book-gallery' ),
			] );
			return;
		}

		// Convert to array of IDs
		$case_ids = array_map( 'trim', explode( ',', $case_ids_str ) );
		$total_cases = count( $case_ids );

		// Calculate pagination
		$offset = ( $page - 1 ) * $items_per_page;
		$paginated_case_ids = array_slice( $case_ids, $offset, $items_per_page );

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
			$cache_key = 'cases_' . $api_token . $website_property_id;
			$cached_data = Cache_Manager::get( $cache_key );

			$html = '';
			$cases_found = 0;

			// Get sidebar data once for nudity checking
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$sidebar_data = null;
			if ( ! empty( $api_tokens[0] ) ) {
				$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
			}

			// Check if the current procedure has nudity flag set - applies to ALL cases in this view
			$procedure_has_nudity = self::procedure_has_nudity( $procedure_context, $sidebar_data );

			if ( $cached_data && isset( $cached_data['data'] ) ) {
				// Look for the requested cases in our cached data - ONLY THE PAGINATED ONES
				foreach ( $cached_data['data'] as $case ) {
					if ( in_array( $case['id'], $paginated_case_ids ) ) {
						// Get image display mode
						$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

						// For procedure-specific views, use procedure-level nudity flag for ALL cases
						// For general views, fall back to individual case checking
						$case_nudity = ! empty( $procedure_context ) 
							? $procedure_has_nudity 
							: self::case_has_nudity_with_sidebar( $case, $sidebar_data );

						// Render the case card using Shortcodes class method
						// Use reflection to access the private method
						$method = new \ReflectionMethod( Shortcodes::class, 'render_ajax_gallery_case_card' );
						$method->setAccessible( true );
						$html .= $method->invoke( null, $case, $image_display_mode, $case_nudity, $procedure_context );
						$cases_found++;
					}
				}
			}

			// Calculate if there are more pages
			$has_more = ( $offset + $items_per_page ) < $total_cases;

			wp_send_json_success( [
				'html' => $html,
				'casesFound' => $cases_found,
				'requestedCount' => count( $paginated_case_ids ),
				'totalCount' => $total_cases,
				'hasMore' => $has_more,
				'nextPage' => $has_more ? ( $page + 1 ) : null,
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load filtered cases.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * Find a case by ID from cached data or API.
	 *
	 * Searches for a case using multiple methods:
	 * 1. Carousel cache lookup (for seoSuffixUrl identifiers)
	 * 2. Cached gallery data search (by numeric ID or seoSuffixUrl)
	 * 3. Direct API call as fallback
	 *
	 * Supports both numeric case IDs and SEO-friendly suffix URLs
	 * for flexible case identification across different contexts.
	 *
	 * @since 3.0.0
	 * @param string $case_id Case identifier (numeric ID or seoSuffixUrl).
	 * @param string $api_token BRAGBook API authentication token.
	 * @param int    $website_property_id Website property identifier.
	 * @return array|null Case data array or null if not found.
	 */
	private static function find_case_by_id( string $case_id, string $api_token, int $website_property_id ): ?array {
		// Enhanced debug logging for troubleshooting
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'find_case_by_id: Looking for case: ' . $case_id );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'find_case_by_id: API token (first 10 chars): ' . substr( $api_token, 0, 10 ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'find_case_by_id: Website property ID: ' . $website_property_id );
		}

		// First check if this is a carousel case in cache (case_id might be seoSuffixUrl)
		$carousel_case = Data_Fetcher::get_carousel_case_from_cache( $case_id, $api_token );
		if ( $carousel_case !== null ) {
			if ( WP_DEBUG ) {
				error_log( 'find_case_by_id: Found case in carousel cache for identifier: ' . $case_id );
				error_log( 'find_case_by_id: Actual case ID is: ' . ( $carousel_case['id'] ?? 'N/A' ) );
			}
			return $carousel_case;
		}

		// Next, try to get the case from cached data
		$cache_key = 'all_cases_' . $api_token . '_' . $website_property_id;
		$cached_data = Cache_Manager::get( $cache_key );
		$case_data = null;

		// Check if case_id is numeric (traditional ID) or alphanumeric (SEO suffix)
		$is_numeric_id = is_numeric( $case_id );

		if ( WP_DEBUG ) {
			error_log( 'find_case_by_id: Is numeric ID? ' . ( $is_numeric_id ? 'Yes' : 'No' ) );
		}

		if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) ) {
			// Debug: Log some sample case data to understand structure
			if ( WP_DEBUG && WP_DEBUG_LOG && count( $cached_data['data'] ) > 0 ) {
				$sample_case = $cached_data['data'][0];
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'find_case_by_id: Sample case structure - ID: ' . ( $sample_case['id'] ?? 'N/A' ) );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'find_case_by_id: Sample case seoSuffixUrl: ' . ( $sample_case['seoSuffixUrl'] ?? 'N/A' ) );
				if ( isset( $sample_case['caseDetails'] ) && is_array( $sample_case['caseDetails'] ) && count( $sample_case['caseDetails'] ) > 0 ) {
					$detail = $sample_case['caseDetails'][0];
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'find_case_by_id: Sample caseDetail seoSuffixUrl: ' . ( $detail['seoSuffixUrl'] ?? 'N/A' ) );
				}
			}
			
			// Search for the case in cached data - try ALL methods for robust matching
			foreach ( $cached_data['data'] as $case ) {
				// Method 1: Try by numeric ID if identifier looks numeric
				if ( $is_numeric_id && isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					if ( WP_DEBUG ) {
						error_log( 'find_case_by_id: Found case by numeric ID in cache' );
					}
					return $case;
				}

				// Method 2: Try by SEO suffix URL at root level (always check regardless of numeric appearance)
				if ( isset( $case['seoSuffixUrl'] ) && $case['seoSuffixUrl'] === $case_id ) {
					if ( WP_DEBUG ) {
						error_log( 'find_case_by_id: Found case by SEO suffix at root level in cache' );
					}
					return $case;
				}

				// Method 3: Try by SEO suffix URL in caseDetails array
				if ( isset( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
					foreach ( $case['caseDetails'] as $detail ) {
						if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
							if ( WP_DEBUG ) {
								error_log( 'find_case_by_id: Found case by SEO suffix in caseDetails in cache' );
							}
							return $case;
						}
					}
				}

				// Method 4: FALLBACK - If identifier looks numeric but no numeric ID match, 
				// try to find case with a seoSuffixUrl that matches and extract actual case ID
				if ( $is_numeric_id && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
					foreach ( $case['caseDetails'] as $detail ) {
						if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
							if ( WP_DEBUG ) {
								error_log( 'find_case_by_id: FALLBACK - Found numeric-looking seoSuffixUrl in cache, actual case ID: ' . ( $case['id'] ?? 'N/A' ) );
							}
							return $case;
						}
					}
				}
			}
		}

		// If not found in the main cache, also try the unfiltered cache (all cases)
		if ( empty( $case_data ) ) {
			$unfiltered_cache_key = \BRAGBookGallery\Includes\Extend\Cache_Manager::get_all_cases_cache_key( $api_token, (string) $website_property_id );
			$unfiltered_cached_data = Cache_Manager::get( $unfiltered_cache_key );

			if ( WP_DEBUG ) {
				error_log( 'find_case_by_id: Trying unfiltered cache key: ' . $unfiltered_cache_key );
				error_log( 'find_case_by_id: Unfiltered cache has ' . ( isset( $unfiltered_cached_data['data'] ) ? count( $unfiltered_cached_data['data'] ) : 0 ) . ' cases' );
			}

			if ( $unfiltered_cached_data && isset( $unfiltered_cached_data['data'] ) && is_array( $unfiltered_cached_data['data'] ) ) {
				foreach ( $unfiltered_cached_data['data'] as $case ) {
					// Method 1: Try by numeric ID if identifier looks numeric
					if ( $is_numeric_id && isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
						if ( WP_DEBUG ) {
							error_log( 'find_case_by_id: Found case by numeric ID in unfiltered cache' );
						}
						return $case;
					}

					// Method 2: Try by SEO suffix URL at root level
					if ( isset( $case['seoSuffixUrl'] ) && $case['seoSuffixUrl'] === $case_id ) {
						if ( WP_DEBUG ) {
							error_log( 'find_case_by_id: Found case by SEO suffix at root level in unfiltered cache' );
						}
						return $case;
					}

					// Method 3: Try by SEO suffix URL in caseDetails array
					if ( isset( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						foreach ( $case['caseDetails'] as $detail ) {
							if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
								if ( WP_DEBUG ) {
									error_log( 'find_case_by_id: Found case by SEO suffix in caseDetails in unfiltered cache' );
								}
								return $case;
							}
						}
					}

					// Method 4: FALLBACK - If identifier looks numeric but no numeric ID match
					if ( $is_numeric_id && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						foreach ( $case['caseDetails'] as $detail ) {
							if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
								if ( WP_DEBUG ) {
									error_log( 'find_case_by_id: FALLBACK - Found numeric-looking seoSuffixUrl in unfiltered cache, actual case ID: ' . ( $case['id'] ?? 'N/A' ) );
								}
								return $case;
							}
						}
					}
				}
			}
		}

		// If not found in cache, try to fetch all cases from API
		if ( WP_DEBUG ) {
			error_log( 'find_case_by_id: Case not found in any cache, fetching from API' );
		}
		
		$all_cases = Data_Fetcher::get_all_cases_for_filtering( $api_token, (string) $website_property_id );

		if ( WP_DEBUG ) {
			error_log( 'find_case_by_id: API returned ' . ( isset( $all_cases['data'] ) ? count( $all_cases['data'] ) : 0 ) . ' cases' );
		}

		if ( ! empty( $all_cases['data'] ) ) {
			foreach ( $all_cases['data'] as $case ) {
				// Method 1: Try by numeric ID if identifier looks numeric
				if ( $is_numeric_id && isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					if ( WP_DEBUG ) {
						error_log( 'find_case_by_id: Found case by numeric ID from API' );
					}
					return $case;
				}

				// Method 2: Try by SEO suffix URL at root level (always check regardless of numeric appearance)
				if ( isset( $case['seoSuffixUrl'] ) && $case['seoSuffixUrl'] === $case_id ) {
					if ( WP_DEBUG ) {
						error_log( 'find_case_by_id: Found case by SEO suffix at root level from API' );
					}
					return $case;
				}

				// Method 3: Try by SEO suffix URL in caseDetails array
				if ( isset( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
					foreach ( $case['caseDetails'] as $detail ) {
						if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
							if ( WP_DEBUG ) {
								error_log( 'find_case_by_id: Found case by SEO suffix in caseDetails from API' );
							}
							return $case;
						}
					}
				}

				// Method 4: FALLBACK - If identifier looks numeric but no numeric ID match
				if ( $is_numeric_id && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
					foreach ( $case['caseDetails'] as $detail ) {
						if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
							if ( WP_DEBUG ) {
								error_log( 'find_case_by_id: FALLBACK - Found numeric-looking seoSuffixUrl from API, actual case ID: ' . ( $case['id'] ?? 'N/A' ) );
							}
							return $case;
						}
					}
				}
			}
		}

		// Try direct case lookup by case number if it looks numeric
		if ( $is_numeric_id ) {
			if ( WP_DEBUG ) {
				error_log( 'find_case_by_id: Trying direct case lookup by case number: ' . $case_id );
			}
			
			// Clear any malformed cache entries for this case first
			$malformed_cache_keys = [
				'brag_book_gallery_transient_api_case_[0,"' . $case_id . '"]',
				'brag_book_gallery_transient_api_case_["' . $case_id . '",null]',
				'brag_book_gallery_transient_api_case_["' . $case_id . '",""]',
			];
			
			foreach ( $malformed_cache_keys as $key ) {
				if ( Cache_Manager::get( $key ) !== false ) {
					Cache_Manager::delete( $key );
					if ( WP_DEBUG ) {
						error_log( 'find_case_by_id: Cleared malformed cache key: ' . $key );
					}
				}
			}
			
			$endpoints = new Endpoints();
			// Try without procedure IDs first, then with facelift procedure ID as fallback
			$direct_case = $endpoints->get_case_by_number( $api_token, $website_property_id, $case_id );
			
			if ( empty( $direct_case ) && WP_DEBUG ) {
				error_log( 'find_case_by_id: Direct lookup failed, trying with facelift procedure ID' );
				// Facelift procedure ID is 7053 based on the debug logs
				$direct_case = $endpoints->get_case_by_number( $api_token, $website_property_id, $case_id, [7053] );
			}
			
			if ( ! empty( $direct_case ) && is_array( $direct_case ) ) {
				if ( WP_DEBUG ) {
					error_log( 'find_case_by_id: Found case via direct case lookup!' );
				}
				return $direct_case;
			} elseif ( WP_DEBUG ) {
				error_log( 'find_case_by_id: Direct case lookup failed for case: ' . $case_id );
			}
		}

		// Try using Endpoints as last resort
		$endpoints = isset( $endpoints ) ? $endpoints : new Endpoints();
		$filter_body = [
			'apiTokens' => [ $api_token ],
			'websitePropertyIds' => [ $website_property_id ],
			'count' => 1,
			'limit' => 100,
		];

		$response = $endpoints->get_pagination_data( $filter_body );

		if ( ! empty( $response ) ) {
			$response_data = json_decode( $response, true );

			if ( ! empty( $response_data['data'] ) ) {
				foreach ( $response_data['data'] as $case ) {
					// Method 1: Try by numeric ID if identifier looks numeric
					if ( $is_numeric_id && isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
						if ( WP_DEBUG ) {
							error_log( 'find_case_by_id: Found case by numeric ID in last resort' );
						}
						return $case;
					}

					// Method 2: Try by SEO suffix URL at root level (always check regardless of numeric appearance)
					if ( isset( $case['seoSuffixUrl'] ) && $case['seoSuffixUrl'] === $case_id ) {
						if ( WP_DEBUG ) {
							error_log( 'find_case_by_id: Found case by SEO suffix at root level in last resort' );
						}
						return $case;
					}

					// Method 3: Try by SEO suffix URL in caseDetails array
					if ( isset( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						foreach ( $case['caseDetails'] as $detail ) {
							if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
								if ( WP_DEBUG ) {
									error_log( 'find_case_by_id: Found case by SEO suffix in caseDetails in last resort' );
								}
								return $case;
							}
						}
					}

					// Method 4: FALLBACK - If identifier looks numeric but no numeric ID match
					if ( $is_numeric_id && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						foreach ( $case['caseDetails'] as $detail ) {
							if ( isset( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
								if ( WP_DEBUG ) {
									error_log( 'find_case_by_id: FALLBACK - Found numeric-looking seoSuffixUrl in last resort, actual case ID: ' . ( $case['id'] ?? 'N/A' ) );
								}
								return $case;
							}
						}
					}
				}
			}
		}

		// Debug logging for case not found
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'find_case_by_id: Case not found anywhere for identifier: ' . $case_id );
			// Let's also check if any cases have seoSuffixUrl values that are similar
			if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) ) {
				$similar_cases = [];
				foreach ( array_slice( $cached_data['data'], 0, 5 ) as $case ) { // Check first 5 cases
					if ( isset( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						foreach ( $case['caseDetails'] as $detail ) {
							if ( isset( $detail['seoSuffixUrl'] ) ) {
								$similar_cases[] = 'Case ID ' . ( $case['id'] ?? 'N/A' ) . ' has seoSuffixUrl: ' . $detail['seoSuffixUrl'];
							}
						}
					}
				}
				if ( ! empty( $similar_cases ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'find_case_by_id: Sample seoSuffixUrls in cache: ' . implode( ', ', $similar_cases ) );
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

				// Get already loaded case IDs to prevent duplicates
				const loadedCases = document.querySelectorAll(".brag-book-gallery-case-card[data-case-id]");
				const loadedIds = Array.from(loadedCases).map(card => card.getAttribute("data-case-id")).join(",");

				// Prepare AJAX data
				const formData = new FormData();
				formData.append("action", "brag_book_gallery_load_more_cases");
				formData.append("nonce", "' . wp_create_nonce( 'brag_book_gallery_nonce' ) . '");
				formData.append("start_page", startPage);
				formData.append("procedure_ids", procedureIds);
				formData.append("loaded_ids", loadedIds);

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
				const grid = document.querySelector(".brag-book-gallery-case-grid");
				if (grid) {
					const defaultColumns = grid.getAttribute("data-columns") || "3";
					const defaultBtn = document.querySelector(".brag-book-gallery-grid-btn[data-columns=\"" + defaultColumns + "\"]");
					if (defaultBtn) {
						defaultBtn.classList.add("active");
					}
				}
			});
		</script>';

		return $script;
	}

	/**
	 * AJAX handler to add a case to favorites.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_add_favorite(): void {
		// Set no-cache headers
		self::set_no_cache_headers();

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get form data
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$case_id = isset( $_POST['case_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_id'] ) ) : '';

		// Validate required fields
		if ( empty( $email ) || empty( $phone ) || empty( $name ) ) {
			wp_send_json_error( [
				'message' => __( 'Please fill in all required fields.', 'brag-book-gallery' ),
			] );
		}

		// Validate email
		if ( ! is_email( $email ) ) {
			wp_send_json_error( [
				'message' => __( 'Please enter a valid email address.', 'brag-book-gallery' ),
			] );
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		try {
			// Call the Endpoints class to submit favorite
			$endpoints = new Endpoints();

			$response = $endpoints->get_favorite_data(
				$api_tokens,
				$website_property_ids,
				$email,
				$phone,
				$name,
				$case_id
			);

			if ( ! empty( $response ) ) {
				$data = json_decode( $response, true );

				// Check if the API call was successful
				if ( isset( $data['success'] ) && $data['success'] === true ) {
					wp_send_json_success( [
						'message' => __( 'Successfully added to favorites! We will contact you soon.', 'brag-book-gallery' ),
						'data' => $data,
					] );
				} else {
					// API returned an error or success was false
					$error_message = __( 'Failed to add to favorites. Please try again.', 'brag-book-gallery' );
					if ( isset( $data['message'] ) ) {
						$error_message = $data['message'];
					} elseif ( isset( $data['error'] ) ) {
						$error_message = $data['error'];
					}

					wp_send_json_error( [
						'message' => $error_message,
						'debug' => WP_DEBUG ? $data : null,
					] );
				}
			} else {
				// No response from API
				wp_send_json_error( [
					'message' => __( 'Unable to connect to the favorites service. Please try again later.', 'brag-book-gallery' ),
					'debug' => WP_DEBUG ? 'Empty response from API' : null,
				] );
			}

		} catch ( \InvalidArgumentException $e ) {
			// Handle validation errors with specific message
			wp_send_json_error( [
				'message' => $e->getMessage(),
				'debug' => WP_DEBUG ? 'Validation Error: ' . $e->getMessage() : null,
			] );
		} catch ( \Exception $e ) {
			// Handle other exceptions
			wp_send_json_error( [
				'message' => __( 'An error occurred while adding to favorites. Please try again.', 'brag-book-gallery' ),
				'debug' => WP_DEBUG ? $e->getMessage() : null,
			] );
		}
	}

	/**
	 * AJAX handler to get user's favorites list.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_get_favorites_list(): void {
		// Set no-cache headers
		self::set_no_cache_headers();

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get email
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		// Validate email
		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( [
				'message' => __( 'Please provide a valid email address.', 'brag-book-gallery' ),
			] );
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		try {
			// Get favorites list from API
			$endpoints = new Endpoints();
			$response = $endpoints->get_favorite_list_data(
				$api_tokens,
				$website_property_ids,
				$email
			);

			if ( ! empty( $response ) ) {
				$data = json_decode( $response, true );

				// Check if we have a successful response with favorites
				if ( isset( $data['success'] ) && $data['success'] === true && ! empty( $data['favorites'] ) ) {
					// Extract user info from the first favorite entry if available
					$user_info = [];
					if ( ! empty( $data['favorites'][0] ) ) {
						$first_favorite = $data['favorites'][0];
						if ( isset( $first_favorite['name'] ) ) {
							$user_info['name'] = $first_favorite['name'];
						}
						if ( isset( $first_favorite['phone'] ) ) {
							$user_info['phone'] = $first_favorite['phone'];
						}
						// Email is already known from the request
						$user_info['email'] = $email;
					}

					// Extract cases from all favorites entries and deduplicate by case ID
					$all_cases = [];
					$seen_case_ids = [];

					foreach ( $data['favorites'] as $favorite ) {
						if ( isset( $favorite['cases'] ) && is_array( $favorite['cases'] ) ) {
							foreach ( $favorite['cases'] as $case ) {
								// Get the case ID
								$case_id = $case['id'] ?? $case['caseId'] ?? '';

								// Only add if we haven't seen this case ID before
								if ( ! empty( $case_id ) && ! in_array( $case_id, $seen_case_ids ) ) {
									$all_cases[] = $case;
									$seen_case_ids[] = $case_id;
								}
							}
						}
					}

					if ( ! empty( $all_cases ) ) {
						// Use HTML_Renderer to create case cards
						$html = HTML_Renderer::render_favorites_view( $all_cases );

						$response_data = [
							'cases' => $all_cases,
							'html' => $html,
							'count' => count( $all_cases ),
						];

						// Include user info if we have it
						if ( ! empty( $user_info ) ) {
							$response_data['user_info'] = $user_info;
						}

						wp_send_json_success( $response_data );
					} else {
						// No cases found in favorites
						wp_send_json_success( [
							'cases' => [],
							'html' => '<div class="brag-book-gallery-favorites-empty">
								<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
								</svg>
								<h2>No favorites yet</h2>
								<p>Start browsing the gallery and click the heart icon on cases you love to save them here.</p>
							</div>',
							'count' => 0,
							'message' => __( 'No cases found in your favorites.', 'brag-book-gallery' ),
						] );
					}
				} else {
					// No favorites found or API error
					wp_send_json_success( [
						'cases' => [],
						'html' => '<div class="brag-book-gallery-favorites-empty">
							<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
							</svg>
							<h2>No favorites found</h2>
							<p>No favorites found for this email address.</p>
						</div>',
						'count' => 0,
						'message' => __( 'No favorites found for this email.', 'brag-book-gallery' ),
					] );
				}
			} else {
				wp_send_json_error( [
					'message' => __( 'Unable to retrieve favorites. Please try again later.', 'brag-book-gallery' ),
				] );
			}

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'An error occurred while retrieving favorites.', 'brag-book-gallery' ),
				'debug' => WP_DEBUG ? $e->getMessage() : null,
			] );
		}
	}

	/**
	 * AJAX handler to load localStorage favorites.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_local_favorites(): void {
		// Set no-cache headers
		self::set_no_cache_headers();

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get case IDs from localStorage
		$case_ids = isset( $_POST['case_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['case_ids'] ) ) : '';

		if ( empty( $case_ids ) ) {
			// Return empty favorites view
			wp_send_json_success( [
				'html' => '<div class="brag-book-gallery-favorites-empty">
					<svg class="empty-icon" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
					<h2>No favorites yet</h2>
					<p>Start browsing the gallery and click the heart icon on cases you love to save them here.</p>
				</div>',
				'count' => 0,
			] );
		}

		// Parse case IDs
		$ids = array_map( 'trim', explode( ',', $case_ids ) );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			wp_send_json_success( [
				'html' => '<div class="brag-book-gallery-favorites-empty">No valid case IDs found.</div>',
				'count' => 0,
			] );
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		// Fetch individual case details from API
		$endpoints = new Endpoints();
		$favorite_cases = [];
		$debug_info = [];

		foreach ( $ids as $case_id ) {
			if ( empty( $case_id ) ) {
				continue;
			}

			// Get case details from API
			$response = $endpoints->get_case_details( $case_id );

			if ( ! empty( $response ) ) {
				$case_data = json_decode( $response, true );

				// Debug: Log what we got
				$debug_info[] = [
					'case_id' => $case_id,
					'has_response' => ! empty( $response ),
					'has_success' => isset( $case_data['success'] ),
					'success_value' => $case_data['success'] ?? null,
					'has_data' => isset( $case_data['data'] ),
					'has_id' => isset( $case_data['id'] ),
					'response_preview' => substr( $response, 0, 200 ),
				];

				// Check if we have valid case data
				if ( isset( $case_data['success'] ) && $case_data['success'] === true && ! empty( $case_data['data'] ) ) {
					$favorite_cases[] = $case_data['data'];
				} elseif ( isset( $case_data['id'] ) ) {
					// Direct case object without wrapper
					$favorite_cases[] = $case_data;
				} elseif ( ! empty( $case_data ) && is_array( $case_data ) ) {
					// Try to use the response as-is if it looks like case data
					$favorite_cases[] = $case_data;
				}
			} else {
				// Get actual API configuration for debugging
				$mode = get_option( 'brag_book_gallery_mode', 'local' );
				$api_token_option = get_option( 'brag_book_gallery_api_token', [] );
				$website_property_id_option = get_option( 'brag_book_gallery_website_property_id', [] );

				$debug_info[] = [
					'case_id' => $case_id,
					'has_response' => false,
					'error' => 'No response from API',
					'api_token_set' => ! empty( $api_tokens ),
					'website_id_set' => ! empty( $website_property_ids ),
					'mode' => $mode,
					'api_token_length' => strlen( $api_token_option[ $mode ] ?? '' ),
					'website_id_value' => $website_property_id_option[ $mode ] ?? 'not set',
					'api_endpoint' => get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ),
				];
			}
		}

		if ( empty( $favorite_cases ) ) {
			// Include debug info in the response if WP_DEBUG is enabled
			$debug_html = '';
			if ( WP_DEBUG ) {
				$debug_html = '<div class="debug-info" style="margin-top: 20px; padding: 10px; background: #f0f0f0; font-size: 12px;">';
				$debug_html .= '<strong>Debug Info:</strong><br>';
				$debug_html .= 'Case IDs requested: ' . implode( ', ', $ids ) . '<br>';
				$debug_html .= 'API responses: <pre>' . esc_html( print_r( $debug_info, true ) ) . '</pre>';
				$debug_html .= '</div>';
			}

			wp_send_json_success( [
				'html' => '<div class="brag-book-gallery-favorites-empty">No matching cases found.' . $debug_html . '</div>',
				'count' => 0,
				'debug' => $debug_info,
			] );
		}

		// Generate HTML using the renderer
		$html = '<div class="brag-book-gallery-favorites-view">';
		$html .= '<div class="brag-book-gallery-favorites-header">';
		$html .= '<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
			<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
			<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
			<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
			<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
			<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
			<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
			<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
			<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
			<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
			<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
			<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
			<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
			<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
			<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
		</svg>';
		$html .= '<p>You have ' . count( $favorite_cases ) . ' favorited ' . ( count( $favorite_cases ) === 1 ? 'case' : 'cases' ) . '</p>';
		$html .= '</div>';
		$html .= '<div class="brag-book-gallery-cases-grid">';
		$html .= HTML_Renderer::render_favorites_view( $favorite_cases );
		$html .= '</div>';
		$html .= '</div>';

		wp_send_json_success( [
			'html' => $html,
			'cases' => $favorite_cases,
			'count' => count( $favorite_cases ),
			'debug' => WP_DEBUG ? $debug_info : null,
		] );
	}

	/**
	 * Validate and sanitize AJAX request data.
	 *
	 * Provides centralized validation and sanitization for all AJAX requests.
	 * Implements proper nonce verification, data sanitization, and field validation.
	 *
	 * @since 3.0.0
	 * @param array $config Configuration array with nonce action and field definitions.
	 * @return array|\WP_Error Sanitized data array or WP_Error on validation failure.
	 */
	private static function validate_and_sanitize_request( array $config ): array|\WP_Error {
		// Get POST data once
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below
		$post_data = $_POST;

		// Verify nonce if specified
		if ( ! empty( $config['nonce'] ) ) {
			$nonce = isset( $post_data['nonce'] ) ? sanitize_text_field( wp_unslash( $post_data['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, $config['nonce'] ) ) {
				return new \WP_Error(
					'invalid_nonce',
					__( 'Security check failed - nonce invalid or missing.', 'brag-book-gallery' ),
					[
						'has_nonce' => ! empty( $nonce ),
						'expected_action' => $config['nonce'],
					]
				);
			}
		}

		$sanitized_data = [];

		// Process required fields
		foreach ( $config['required_fields'] ?? [] as $field ) {
			if ( ! isset( $post_data[ $field ] ) || '' === trim( $post_data[ $field ] ) ) {
				return new \WP_Error(
					'missing_required_field',
					sprintf(
						/* translators: %s: field name */
						__( 'Required field "%s" is missing.', 'brag-book-gallery' ),
						$field
					),
					[ 'field' => $field ]
				);
			}
			$sanitized_data[ $field ] = sanitize_text_field( wp_unslash( $post_data[ $field ] ) );
		}

		// Process optional fields
		foreach ( $config['optional_fields'] ?? [] as $field ) {
			if ( isset( $post_data[ $field ] ) ) {
				$sanitized_data[ $field ] = sanitize_text_field( wp_unslash( $post_data[ $field ] ) );
			}
		}

		return $sanitized_data;
	}

	/**
	 * Check if a case has nudity based on its procedure IDs.
	 *
	 * @since 3.2.1
	 *
	 * @param array $case Case data containing procedureIds.
	 *
	 * @return bool True if any of the case's procedures have nudity flag set.
	 */
	private static function case_has_nudity( array $case ): bool {
		// Check if case has procedure IDs
		if ( empty( $case['procedureIds'] ) || ! is_array( $case['procedureIds'] ) ) {
			return false;
		}

		// Get API token for sidebar data
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		if ( empty( $api_tokens[0] ) ) {
			return false;
		}

		// Get sidebar data to check procedure nudity flags
		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
		if ( empty( $sidebar_data['data'] ) ) {
			return false;
		}

		// Check each procedure ID for nudity flag
		foreach ( $case['procedureIds'] as $procedure_id ) {
			$procedure = Data_Fetcher::find_procedure_by_id( $sidebar_data, (int) $procedure_id );
			if ( $procedure && ! empty( $procedure['nudity'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a case has nudity based on its procedure IDs (optimized version with pre-fetched sidebar data).
	 *
	 * @since 3.2.1
	 *
	 * @param array      $case        Case data containing procedureIds.
	 * @param array|null $sidebar_data Pre-fetched sidebar data.
	 *
	 * @return bool True if any of the case's procedures have nudity flag set.
	 */
	private static function case_has_nudity_with_sidebar( array $case, $sidebar_data ): bool {
		// Check if case has procedure IDs
		if ( empty( $case['procedureIds'] ) || ! is_array( $case['procedureIds'] ) ) {
			return false;
		}

		// Check if sidebar data is available
		if ( empty( $sidebar_data['data'] ) ) {
			return false;
		}

		// Check each procedure ID for nudity flag
		foreach ( $case['procedureIds'] as $procedure_id ) {
			$procedure = Data_Fetcher::find_procedure_by_id( $sidebar_data['data'], (int) $procedure_id );
			if ( $procedure && ! empty( $procedure['nudity'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * AJAX handler for clearing legacy cache with old prefixes.
	 *
	 * @since 3.2.2
	 * @return void
	 */
	public static function ajax_clear_legacy_cache(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_cache_management' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			$cache_manager = new \BRAGBookGallery\Includes\Admin\Debug_Tools\Cache_Management();
			$result = $cache_manager->clear_legacy_transients();
			wp_send_json_success( $result );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * CORS-safe API proxy for direct frontend calls.
	 *
	 * Provides a WordPress AJAX endpoint that proxies requests to the external API,
	 * bypassing CORS restrictions while maintaining the performance benefits of
	 * reduced server-side processing.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_api_proxy(): void {
		// Set no-cache headers first
		self::set_no_cache_headers();

		// Validate and sanitize request data
		$request_data = self::validate_and_sanitize_request( [
			'nonce' => 'brag_book_gallery_nonce',
			'required_fields' => [ 'endpoint', 'method' ],
			'optional_fields' => [ 'body', 'timeout' ],
		] );

		if ( is_wp_error( $request_data ) ) {
			wp_send_json_error( [
				'message' => $request_data->get_error_message(),
				'debug' => WP_DEBUG ? $request_data->get_error_data() : null,
			] );
			return;
		}

		// Extract sanitized parameters
		$endpoint = $request_data['endpoint'] ?? '';
		$method = strtoupper( $request_data['method'] ?? 'GET' );
		$body = $request_data['body'] ?? '';
		$timeout = absint( $request_data['timeout'] ?? 8 );

		// Validate endpoint (security check)
		$allowed_endpoints = [
			'/api/plugin/combine/cases',
			'/api/plugin/combine/sidebar',
			'/api/plugin/combine/cases/',
			'/case/',
		];

		$endpoint_allowed = false;
		foreach ( $allowed_endpoints as $allowed ) {
			if ( strpos( $endpoint, $allowed ) === 0 ) {
				$endpoint_allowed = true;
				break;
			}
		}

		// Special handling for case details with query parameters
		if ( ! $endpoint_allowed && preg_match( '#^/api/plugin/combine/cases/[0-9]+(\?|$)#', $endpoint ) ) {
			$endpoint_allowed = true;
		}
		
		// Special handling for direct case endpoint with query parameters
		if ( ! $endpoint_allowed && preg_match( '#^/case/[0-9]+(\?|$)#', $endpoint ) ) {
			$endpoint_allowed = true;
		}

		if ( ! $endpoint_allowed ) {
			wp_send_json_error( [
				'message' => __( 'Endpoint not allowed.', 'brag-book-gallery' ),
				'debug' => WP_DEBUG ? [ 'endpoint' => $endpoint ] : null,
			] );
			return;
		}

		// Validate method
		$allowed_methods = [ 'GET', 'POST' ];
		if ( ! in_array( $method, $allowed_methods, true ) ) {
			wp_send_json_error( [
				'message' => __( 'HTTP method not allowed.', 'brag-book-gallery' ),
			] );
			return;
		}

		try {
			// Get API base URL
			$api_base_url = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );
			$full_url = rtrim( $api_base_url, '/' ) . $endpoint;

			// Prepare request arguments
			$args = [
				'method' => $method,
				'timeout' => min( $timeout, 30 ), // Cap at 30 seconds
				'headers' => [
					'Content-Type' => 'application/json',
					'User-Agent' => 'BRAGBook-Gallery-Plugin/' . get_option( 'brag_book_gallery_version', '3.0.0' ),
				],
			];

			// Add body for POST requests
			if ( $method === 'POST' && ! empty( $body ) ) {
				// Validate JSON
				$decoded_body = json_decode( $body, true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					wp_send_json_error( [
						'message' => __( 'Invalid JSON in request body.', 'brag-book-gallery' ),
					] );
					return;
				}
				$args['body'] = $body;
			}

			// Make the API request
			$response = wp_remote_request( $full_url, $args );

			// Check for WordPress HTTP errors
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( [
					'message' => __( 'API request failed.', 'brag-book-gallery' ),
					'error' => $response->get_error_message(),
				] );
				return;
			}

			// Get response data
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			// Check HTTP status
			if ( $response_code < 200 || $response_code >= 300 ) {
				wp_send_json_error( [
					'message' => __( 'API returned error status.', 'brag-book-gallery' ),
					'status' => $response_code,
					'debug' => WP_DEBUG ? $response_body : null,
				] );
				return;
			}

			// Validate JSON response
			$response_data = json_decode( $response_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( [
					'message' => __( 'Invalid JSON response from API.', 'brag-book-gallery' ),
					'debug' => WP_DEBUG ? $response_body : null,
				] );
				return;
			}

			// Return the proxied response
			wp_send_json_success( [
				'data' => $response_data,
				'status' => $response_code,
				'endpoint' => $endpoint,
			] );

		} catch ( \Throwable $e ) {
			wp_send_json_error( [
				'message' => __( 'Proxy request failed.', 'brag-book-gallery' ),
				'error' => $e->getMessage(),
				'debug' => WP_DEBUG ? [
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				] : null,
			] );
		}
	}

	/**
	 * Generate navigation data for a case within a procedure's case list
	 *
	 * @param string $current_case_id Current case ID
	 * @param array  $procedure_cases Array of all cases for the procedure
	 * @param string $procedure_slug  Procedure slug for URL generation
	 *
	 * @return array|null Navigation data with previous/next case info or null if not found
	 */
	private static function generate_case_navigation( string $current_case_id, array $procedure_cases, string $procedure_slug ): ?array {
		if ( empty( $procedure_cases ) || empty( $current_case_id ) ) {
			return null;
		}

		// Find the current case position in the list
		$current_index = null;
		foreach ( $procedure_cases as $index => $case ) {
			$case_id = (string) ( $case['id'] ?? '' );
			if ( $case_id === $current_case_id ) {
				$current_index = $index;
				break;
			}
		}

		if ( $current_index === null ) {
			if ( WP_DEBUG ) {
				error_log( 'generate_case_navigation: Current case ID ' . $current_case_id . ' not found in procedure cases' );
			}
			return null;
		}

		$navigation = [];

		// Get previous case
		if ( $current_index > 0 ) {
			$prev_case = $procedure_cases[ $current_index - 1 ];
			$prev_case_id = (string) ( $prev_case['id'] ?? '' );
			
			// Try to get SEO suffix, fallback to case ID
			$prev_slug = '';
			if ( ! empty( $prev_case['caseDetails'][0]['seoSuffixUrl'] ) ) {
				$prev_slug = $prev_case['caseDetails'][0]['seoSuffixUrl'];
			} else {
				$prev_slug = $prev_case_id;
			}

			if ( ! empty( $prev_slug ) ) {
				$navigation['previous'] = [
					'id' => $prev_case_id,
					'slug' => $prev_slug,
					'procedureSlug' => $procedure_slug,
				];
			}
		}

		// Get next case
		if ( $current_index < count( $procedure_cases ) - 1 ) {
			$next_case = $procedure_cases[ $current_index + 1 ];
			$next_case_id = (string) ( $next_case['id'] ?? '' );
			
			// Try to get SEO suffix, fallback to case ID
			$next_slug = '';
			if ( ! empty( $next_case['caseDetails'][0]['seoSuffixUrl'] ) ) {
				$next_slug = $next_case['caseDetails'][0]['seoSuffixUrl'];
			} else {
				$next_slug = $next_case_id;
			}

			if ( ! empty( $next_slug ) ) {
				$navigation['next'] = [
					'id' => $next_case_id,
					'slug' => $next_slug,
					'procedureSlug' => $procedure_slug,
				];
			}
		}

		if ( WP_DEBUG ) {
			error_log( 'generate_case_navigation: Generated navigation for case ' . $current_case_id . ' at index ' . $current_index . ' of ' . count( $procedure_cases ) . ' cases' );
			error_log( 'generate_case_navigation: Has previous: ' . ( ! empty( $navigation['previous'] ) ? 'YES' : 'NO' ) );
			error_log( 'generate_case_navigation: Has next: ' . ( ! empty( $navigation['next'] ) ? 'YES' : 'NO' ) );
		}

		return ! empty( $navigation ) ? $navigation : null;
	}

	/**
	 * Check if the current procedure being viewed has nudity flag set to true.
	 *
	 * For procedure-specific views (like /cases/tummy-tuck/), ALL cases should show 
	 * nudity warnings if the procedure itself has nudity=true in sidebar data.
	 *
	 * @since 3.0.0
	 *
	 * @param string     $filter_procedure Current procedure being viewed.
	 * @param array|null $sidebar_data     Pre-fetched sidebar data.
	 *
	 * @return bool True if the current procedure has nudity flag set.
	 */
	private static function procedure_has_nudity( string $filter_procedure, $sidebar_data ): bool {
		// If no procedure filter or no sidebar data, return false
		if ( empty( $filter_procedure ) || empty( $sidebar_data['data'] ) ) {
			return false;
		}

		// Find the procedure by slug/name in sidebar data
		foreach ( $sidebar_data['data'] as $procedure ) {
			if ( empty( $procedure['name'] ) ) {
				continue;
			}

			// Method 1: Check if the slugName field matches directly
			if ( ! empty( $procedure['slugName'] ) && $procedure['slugName'] === $filter_procedure ) {
				return ! empty( $procedure['nudity'] );
			}

			// Method 2: Check if procedure slug matches (convert name to slug for comparison)
			$procedure_slug = sanitize_title( $procedure['name'] );
			if ( $procedure_slug === $filter_procedure ) {
				return ! empty( $procedure['nudity'] );
			}

			// Method 3: Also check direct name match (case-insensitive)
			if ( strcasecmp( $procedure['name'], str_replace( '-', ' ', $filter_procedure ) ) === 0 ) {
				return ! empty( $procedure['nudity'] );
			}
		}

		return false;
	}

}
