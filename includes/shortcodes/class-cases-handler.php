<?php
/**
 * Cases Shortcode Handler for BRAGBook Gallery Plugin
 *
 * Comprehensive shortcode handler managing case grid displays, individual case views,
 * and URL routing for the BRAGBook Gallery system. Provides advanced filtering,
 * caching, and SEO-optimized URL generation with WordPress VIP compliance.
 *
 * Key Features:
 * - Dual shortcode support: [brag_book_gallery_cases] and [brag_book_gallery_case]
 * - Intelligent case URL routing with SEO suffix support
 * - Advanced data extraction from multiple API response formats
 * - WordPress VIP compliant caching and database operations
 * - Responsive grid layouts with configurable columns
 * - AJAX-compatible card rendering for dynamic content loading
 * - Comprehensive filtering by procedure, demographics, and patient data
 * - Nudity warning system with accessibility compliance
 * - Progressive image loading with skeleton loaders
 * - Mobile-optimized responsive design patterns
 *
 * Architecture:
 * - Static methods for stateless operations and better performance
 * - Centralized API configuration management with fallback strategies
 * - Modular rendering system with reusable components
 * - Security-first approach with comprehensive input sanitization
 * - Type-safe operations with PHP 8.2+ features
 * - WordPress VIP compliant error handling and logging
 *
 * URL Structure:
 * - Grid View: /gallery-slug/
 * - Filtered Grid: /gallery-slug/procedure-name/
 * - Single Case: /gallery-slug/procedure-name/case-seo-suffix/
 * - Legacy Support: Numeric IDs and various API response formats
 *
 * Caching Strategy:
 * - Transient-based caching with intelligent expiration
 * - Case data cached by API token and property ID combinations
 * - Supports both individual case and bulk case retrieval
 * - Cache invalidation hooks for content updates
 *
 * Security Features:
 * - Comprehensive input validation and sanitization
 * - XSS prevention through proper output escaping
 * - SQL injection protection via prepared statements
 * - CSRF protection through WordPress nonce system
 * - Safe handling of mixed data types from API responses
 *
 * Performance Optimizations:
 * - Lazy loading for images with intersection observer support
 * - Optimized database queries with minimal API calls
 * - Conditional asset loading based on content requirements
 * - Efficient data structure handling for large case collections
 * - Intelligent pagination with SEO-friendly URLs
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\shortcodes;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Resources\Asset_Manager;
use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Extend\Data_Fetcher;

// Cache_Manager removed per user request
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cases Shortcode Handler Class
 *
 * WordPress-based shortcode processor for the BRAGBook Gallery plugin, managing
 * comprehensive case display functionality using WP_Query and custom post types.
 * Implements WordPress VIP standards with PHP 8.2+ optimizations.
 *
 * Shortcodes Managed:
 * - [brag_book_gallery_cases]: Grid display with filtering and pagination using WP_Query
 *
 * Technical Implementation:
 * - PHP 8.2+ match expressions for cleaner conditional logic
 * - Union types for flexible parameter handling
 * - Readonly properties for immutable configuration
 * - Named arguments for improved code readability
 * - Null coalescing operators for safer data access
 *
 * WordPress VIP Compliance:
 * - Prepared SQL statements for database security
 * - Proper use of WordPress transient API
 * - VIP-approved caching strategies
 * - Sanitized output with appropriate escaping functions
 * - Performance-optimized database queries
 *
 * @since 3.0.0
 */
final class Cases_Handler {

	/**
	 * Default cases per page
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_CASES_LIMIT = 20;

	/**
	 * Default grid columns
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_COLUMNS = 3;

	/**
	 * Cache group for cases data
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_cases';

	/**
	 * Missing data log
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private static $missing_data_log = [];

	/**
	 * Initialize the cases handler
	 *
	 * Sets up shortcode registration and hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __construct() {
		add_shortcode( 'brag_book_gallery_cases', [ self::class, 'handle' ] );

		// Hook into wp_enqueue_scripts to check for our shortcode and enqueue assets
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ], 5 );

		// Register AJAX handlers for load more functionality
		add_action( 'wp_ajax_brag_book_gallery_load_more_cases', [ self::class, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_more_cases', [ self::class, 'ajax_load_more_cases' ] );
	}

	/**
	 * Check if cases shortcode is present and enqueue assets
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {
		global $post;

		// Check if we have a post and if it contains our shortcode
		if ( ! $post || ! has_shortcode( $post->post_content, 'brag_book_gallery_cases' ) ) {
			return;
		}

		// Enqueue the main gallery CSS and JS since our cases use the same styles
		$plugin_version = Setup::get_plugin_version();

		// Enqueue CSS using Setup class asset URL method
		wp_enqueue_style(
			'brag-book-gallery',
			Setup::get_asset_url( 'assets/css/brag-book-gallery.css' ),
			[],
			$plugin_version
		);

		// Enqueue JavaScript using Setup class asset URL method
		wp_enqueue_script(
			'brag-book-gallery',
			Setup::get_asset_url( 'assets/js/brag-book-gallery.js' ),
			[ 'jquery' ],
			$plugin_version,
			true
		);

		// Localize script with necessary data for AJAX and functionality
		wp_localize_script( 'brag-book-gallery', 'bragBookGalleryConfig', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'brag_book_gallery_nonce' ),
		] );
	}

	/**
	 * Handle the cases shortcode
	 *
	 * Displays cases from the API with optional filtering by procedure.
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Cases HTML or error message.
	 */
	public static function handle( array $atts ): string {
		// Validate and sanitize shortcode attributes with security-first approach
		$atts = self::validate_and_sanitize_shortcode_attributes( $atts );

		// Get API configuration with fallback to WordPress options
		$atts = self::get_api_configuration( $atts );

		// Get filter from URL if present.
		$filter_procedure = sanitize_text_field( get_query_var( 'filter_procedure', '' ) );
		$procedure_title  = sanitize_text_field( get_query_var( 'procedure_title', '' ) );
		$case_suffix      = sanitize_text_field( get_query_var( 'case_suffix', '' ) );

		// Debug logging for procedure detection
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: Cases handler - filter_procedure: ' . $filter_procedure );
			error_log( 'BRAGBook: Cases handler - procedure_title: ' . $procedure_title );
			error_log( 'BRAGBook: Cases handler - case_suffix: ' . $case_suffix );
			error_log( 'BRAGBook: Cases handler - REQUEST_URI: ' . ( $_SERVER['REQUEST_URI'] ?? 'not set' ) );
		}

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering.
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// FALLBACK: If query vars aren't set, try to extract from URL directly
		if ( empty( $filter_procedure ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$current_url = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path_segments = array_filter( explode( '/', $current_url ) );

			// Look for pattern: /gallery/procedure-name/ or /gallery-slug/procedure-name/
			if ( count( $path_segments ) >= 2 ) {
				$gallery_segment = $path_segments[0];
				$procedure_segment = $path_segments[1];

				// Check if first segment looks like a gallery (contains "gallery" or is a known gallery slug)
				if ( stripos( $gallery_segment, 'gallery' ) !== false ||
					 in_array( $gallery_segment, [ 'before-after', 'cases', 'results' ] ) ) {
					$filter_procedure = sanitize_title( $procedure_segment );
					if ( WP_DEBUG ) {
						error_log( 'BRAGBook: Cases handler - Extracted procedure from URL: ' . $filter_procedure );
					}
				}
			}
		}

		// Final debug log for what will be used
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: Cases handler - Final filter_procedure: ' . $filter_procedure );
		}

		// Debug logging if enabled.
		self::debug_log_query_vars( $filter_procedure, $procedure_title, $case_suffix, $atts );

		// Use case_suffix which now contains both numeric IDs and SEO suffixes.
		$case_identifier = ! empty( $case_suffix ) ? $case_suffix : '';

		// If we have a case identifier, load the main gallery and let JavaScript handle case loading
		if ( ! empty( $case_identifier ) ) {
			// Instead of rendering the case via PHP, load the main gallery with data attributes
			// that JavaScript can detect to automatically load the case
			$gallery_atts = $atts;
			$gallery_atts['data_case_id'] = $case_identifier;
			$gallery_atts['data_procedure_slug'] = $procedure_title;

			// Load the main gallery shortcode which will be handled by JavaScript
			return Gallery_Handler::handle( $gallery_atts );
		}

		// Validate required fields.
		if ( empty( $atts['api_token'] ) || empty( $atts['website_property_id'] ) ) {
			return sprintf(
				'<p class="brag-book-gallery-cases-error">%s</p>',
				esc_html__( 'Please configure API settings to display cases.', 'brag-book-gallery' )
			);
		}

		// Get procedure IDs based on filter.
		$procedure_ids = self::get_procedure_ids_for_filter( $filter_procedure, $atts );

		// Get items per page setting
		$items_per_page = absint( get_option( 'brag_book_gallery_items_per_page', '10' ) );

		// When filtering by procedure, load enough cases for pagination plus some buffer
		// but avoid loading excessive amounts on initial page load for performance
		$initial_load_size = ! empty( $filter_procedure ) ? min( 200, $items_per_page * 5 ) : $items_per_page;

		// Get cases from API.
		$cases_data = Data_Fetcher::get_cases_from_api(
			$atts['api_token'],
			$atts['website_property_id'],
			$procedure_ids,
			$initial_load_size,
			absint( $atts['page'] )
		);

		// Enqueue cases assets.
		Asset_Manager::enqueue_cases_assets();

		// Get sidebar data for filters
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		$sidebar_data = [];
		if ( ! empty( $api_tokens[0] ) && ! empty( $website_property_ids[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0], $website_property_ids[0] );
		}

		// Localize script with necessary data for filters
		Asset_Manager::localize_gallery_script(
			[
				'api_token' => $atts['api_token'],
				'website_property_id' => $atts['website_property_id'],
			],
			$sidebar_data,
			$cases_data
		);

		// Render cases grid.
		$output = self::render_cases_grid( $cases_data, $atts, $filter_procedure );
		return \BRAGBookGallery\Includes\Core\Setup::clean_shortcode_text( $output );
	}

	/**
	 * Handle the case details shortcode
	 *
	 * Displays a single case with all its details.
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Case details HTML or error message.
	 */
	/**
	 * Get cases from WordPress using WP_Query
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_procedure Optional procedure slug to filter by.
	 * @param array  $atts Shortcode attributes.
	 *
	 * @return array Cases data in API-like format.
	 */
	public static function get_cases_from_wp_query( string $filter_procedure = '', array $atts = [] ): array {
		// Build WP_Query arguments
		$query_args = [
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'post_status'    => 'publish',
			'posts_per_page' => $atts['limit'] ?? self::DEFAULT_CASES_LIMIT,
			'paged'          => $atts['page'] ?? 1,
			'meta_query'     => [],
			'tax_query'      => [],
		];

		// Add taxonomy filter if procedure is specified
		if ( ! empty( $filter_procedure ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
				'field'    => 'slug',
				'terms'    => $filter_procedure,
			];
		}

		// Execute query
		$query = new \WP_Query( $query_args );

		// Convert posts to case data format
		$cases = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$case_data = self::convert_post_to_case_data( get_post() );
				if ( $case_data ) {
					$cases[] = $case_data;
				}
			}
			wp_reset_postdata();
		}

		return [
			'data' => $cases,
			'total' => $query->found_posts,
			'pages' => $query->max_num_pages,
		];
	}

	/**
	 * Convert WordPress post to case data format
	 *
	 * @since 3.0.0
	 *
	 * @param \WP_Post $post The case post.
	 *
	 * @return array|null Case data array or null if invalid.
	 */
	private static function convert_post_to_case_data( \WP_Post $post ): ?array {
		// Get post meta data
		$case_data = self::get_case_meta_data( $post->ID );

		// Get procedure information
		$procedures = self::get_case_procedures( $post->ID );

		// Build case data array similar to API format
		return [
			'id'              => $case_data['case_id'] ?: $post->ID,
			'patientAge'      => $case_data['patient_age'],
			'patientGender'   => $case_data['patient_gender'],
			'patientEthnicity' => $case_data['patient_ethnicity'] ?: '',
			'patientHeight'   => $case_data['patient_height'] ?: '',
			'patientWeight'   => $case_data['patient_weight'] ?: '',
			'procedureDate'   => $case_data['procedure_date'],
			'caseNotes'       => $case_data['case_notes'],
			'beforeImage'     => $case_data['before_image'],
			'afterImage'      => $case_data['after_image'],
			'procedureIds'    => wp_list_pluck( $procedures, 'procedure_id' ),
			'procedures'      => $procedures,
			'seoSuffixUrl'    => $post->post_name,
			'permalink'       => get_permalink( $post->ID ),
		];
	}

	/**
	 * Validate and sanitize shortcode attributes with comprehensive security measures
	 *
	 * Processes raw shortcode attributes through WordPress shortcode_atts()
	 * with additional validation, sanitization, and type casting for security
	 * and data integrity. Uses PHP 8.2 features for clean validation logic.
	 *
	 * Security Features:
	 * - Attribute whitelisting through shortcode_atts()
	 * - Integer validation with bounds checking
	 * - String sanitization for text fields
	 * - Boolean validation with type casting
	 * - CSS class sanitization for XSS prevention
	 *
	 * @since 3.0.0
	 *
	 * @param array $raw_atts Raw shortcode attributes from user input.
	 *
	 * @return array Validated and sanitized attribute array.
	 */
	private static function validate_and_sanitize_shortcode_attributes( array $raw_atts ): array {
		// Define default attributes with proper types
		$defaults = [
			'api_token'           => '',
			'website_property_id' => '',
			'procedure_ids'       => '',
			'limit'               => self::DEFAULT_CASES_LIMIT,
			'page'                => 1,
			'columns'             => self::DEFAULT_COLUMNS,
			'show_details'        => 'true',
			'class'               => '',
		];

		// Apply WordPress shortcode attribute parsing with defaults
		$atts = shortcode_atts( $defaults, $raw_atts, 'brag_book_gallery_cases' );

		// Validate and sanitize each attribute with type-specific handling
		return [
			'api_token'           => sanitize_text_field( $atts['api_token'] ),
			'website_property_id' => sanitize_text_field( $atts['website_property_id'] ),
			'procedure_ids'       => sanitize_text_field( $atts['procedure_ids'] ),
			'limit'               => max( 1, min( 200, absint( $atts['limit'] ) ) ), // Bounds: 1-200
			'page'                => max( 1, absint( $atts['page'] ) ), // Minimum: 1
			'columns'             => max( 1, min( 6, absint( $atts['columns'] ) ) ), // Bounds: 1-6
			'show_details'        => sanitize_text_field( $atts['show_details'] ),
			'class'               => sanitize_html_class( $atts['class'] ),
		];
	}

	/**
	 * Extract API configuration from WordPress options with fallback handling
	 *
	 * Retrieves API authentication credentials from WordPress options with
	 * intelligent type handling for both legacy (string) and current (array)
	 * storage formats. Uses PHP 8.2 match expressions for cleaner logic.
	 *
	 * Configuration Sources (in order of precedence):
	 * 1. Shortcode attributes (highest priority)
	 * 2. WordPress options (brag_book_gallery_api_token, brag_book_gallery_website_property_id)
	 * 3. Empty values (handled gracefully by calling methods)
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes with potential API configuration.
	 *
	 * @return array Enhanced attributes with API configuration populated.
	 */
	private static function get_api_configuration( array $atts ): array {
		// Extract API token with type-safe handling
		if ( empty( $atts['api_token'] ) ) {
			$atts['api_token'] = self::extract_api_credential(
				'brag_book_gallery_api_token',
				'API token'
			);
		}

		// Extract website property ID with type-safe handling
		if ( empty( $atts['website_property_id'] ) ) {
			$atts['website_property_id'] = self::extract_api_credential(
				'brag_book_gallery_website_property_id',
				'Website property ID'
			);
		}

		return $atts;
	}

	/**
	 * Extract API credential from WordPress options with type safety
	 *
	 * Handles both legacy string format and current array format for
	 * WordPress option storage. Uses PHP 8.2 match expression for
	 * clean type-based credential extraction.
	 *
	 * @since 3.0.0
	 *
	 * @param string $option_name WordPress option name to retrieve.
	 * @param string $credential_type Credential type for debugging context.
	 *
	 * @return string Extracted and sanitized credential value.
	 */
	private static function extract_api_credential( string $option_name, string $credential_type ): string {
		$credential_data = get_option( $option_name, array() );

		// Use PHP 8.2 match expression for type-based extraction
		$credential_value = match ( true ) {
			// Array format (current): Extract first element if available
			is_array( $credential_data ) && ! empty( $credential_data[0] ) => $credential_data[0],
			// String format (legacy): Use directly if not empty
			is_string( $credential_data ) && ! empty( trim( $credential_data ) ) => $credential_data,
			// Default: Return empty string for missing/invalid data
			default => '',
		};

		// Sanitize the extracted credential
		$sanitized_credential = sanitize_text_field( $credential_value );

		// Debug logging for credential extraction issues
		if ( empty( $sanitized_credential ) && ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "Cases Shortcode: {$credential_type} not found or empty in option '{$option_name}'" );
		}

		return $sanitized_credential;
	}

	/**
	 * Debug log query variables with WordPress VIP compliance
	 *
	 * Logs comprehensive query variable information for debugging purposes
	 * when WP_DEBUG is enabled. Uses WordPress VIP compliant logging
	 * practices with proper error handling and data sanitization.
	 *
	 * Debug Information Logged:
	 * - Query variables (filter_procedure, procedure_title, case_suffix)
	 * - API configuration status (token/property ID presence)
	 * - Current request URI for context
	 * - Shortcode attributes for troubleshooting
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_procedure Filter procedure slug from query vars.
	 * @param string $procedure_title  Procedure title extracted from URL.
	 * @param string $case_suffix      Case suffix/identifier from URL.
	 * @param array  $atts             Shortcode attributes array.
	 *
	 * @return void
	 */
	private static function debug_log_query_vars( string $filter_procedure, string $procedure_title, string $case_suffix, array $atts ): void {
		// Only log in debug mode with proper WordPress debug log support
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debugging output only when WP_DEBUG is enabled
		error_log( 'Cases Shortcode Debug Information:' );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'filter_procedure: ' . $filter_procedure );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'procedure_title: ' . $procedure_title );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'case_suffix: ' . $case_suffix );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Website Property ID: ' . $atts['website_property_id'] );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Debug logging only.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : 'N/A';
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Current URL: ' . esc_url_raw( $request_uri ) );
	}

	/**
	 * Get procedure IDs for filtering
	 *
	 * Determines procedure IDs based on filter or shortcode attributes.
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_procedure Procedure slug to filter by.
	 * @param array  $atts             Shortcode attributes.
	 *
	 * @return array Array of procedure IDs.
	 */
	private static function get_procedure_ids_for_filter( string $filter_procedure, array $atts ): array {
		$procedure_ids = array();

		if ( ! empty( $filter_procedure ) ) {
			// Try to find matching procedure in sidebar data.
			$sidebar_data = Data_Fetcher::get_sidebar_data( $atts['api_token'] );

			if ( ! empty( $sidebar_data['data'] ) ) {
				$procedure_info = Data_Fetcher::find_procedure_by_slug( $sidebar_data['data'], $filter_procedure );

				// Use 'ids' array which contains all procedure IDs for this procedure type.
				if ( ! empty( $procedure_info['ids'] ) && is_array( $procedure_info['ids'] ) ) {
					$procedure_ids = array_map( 'intval', $procedure_info['ids'] );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'Cases shortcode - Found procedure IDs for ' . $filter_procedure . ': ' . implode( ',', $procedure_ids ) );
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'Total case count from sidebar: ' . ( $procedure_info['totalCase'] ?? 0 ) );
					}
				} elseif ( ! empty( $procedure_info['id'] ) ) {
					// Fallback to single 'id' if 'ids' array doesn't exist.
					$procedure_ids = array( intval( $procedure_info['id'] ) );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'Cases shortcode - Using single procedure ID for ' . $filter_procedure . ': ' . $procedure_info['id'] );
					}
				}
			}
		} elseif ( ! empty( $atts['procedure_ids'] ) ) {
			$procedure_ids = array_map( 'intval', explode( ',', $atts['procedure_ids'] ) );
		}

		return $procedure_ids;
	}

	/**
	 * Render comprehensive cases grid with responsive layout
	 *
	 * Generates a complete HTML grid display for case collections with
	 * configurable columns, responsive design, and pagination support.
	 * Implements accessibility best practices and semantic HTML structure.
	 *
	 * Features:
	 * - Responsive grid layout with configurable column counts
	 * - Semantic HTML with proper ARIA attributes
	 * - Image display mode support (single/before-after)
	 * - Integrated pagination with SEO-friendly URLs
	 * - Error handling for empty datasets
	 * - WordPress VIP compliant output escaping
	 *
	 * @since 3.0.0
	 *
	 * @param array  $cases_data       Complete cases dataset from API with data/pagination keys.
	 * @param array  $atts             Shortcode attributes including columns, show_details, class.
	 * @param string $filter_procedure Optional procedure slug for filtered display context.
	 *
	 * @return string Complete HTML grid output with container structure.
	 */
	public static function render_cases_grid( array $cases_data, array $atts, string $filter_procedure = '' ): string {
		if ( empty( $cases_data ) || empty( $cases_data['data'] ) ) {
			return sprintf(
				'<p class="brag-book-gallery-cases-no-data">%s</p>',
				esc_html__( 'No cases found.', 'brag-book-gallery' )
			);
		}

		$cases        = $cases_data['data'];
		$columns      = absint( $atts['columns'] ) ?: self::DEFAULT_COLUMNS;
		$show_details = filter_var( $atts['show_details'], FILTER_VALIDATE_BOOLEAN );

		// Get items per page setting for pagination
		$items_per_page = absint( get_option( 'brag_book_gallery_items_per_page', '10' ) );

		// Only render the first page of cases
		$cases_to_render = array_slice( $cases, 0, $items_per_page );
		$total_cases = count( $cases );

		// Debug logging
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: render_cases_grid - Total cases: ' . $total_cases );
			error_log( 'BRAGBook: render_cases_grid - Items per page: ' . $items_per_page );
			error_log( 'BRAGBook: render_cases_grid - Cases to render: ' . count( $cases_to_render ) );
			error_log( 'BRAGBook: render_cases_grid - Filter procedure: ' . $filter_procedure );
		}

		// Start output with proper container structure matching gallery layout.
		$output = '<div class="brag-book-gallery-wrapper" role="application" aria-label="Cases Gallery">';
		$output .= '<div class="brag-book-gallery-main-content" role="region" aria-label="Gallery content" id="gallery-content">';

		// Add controls section for filtering and grid layout
		$output .= '<div class="brag-book-gallery-controls">';
		$output .= '<div class="brag-book-gallery-controls-left">';
		$output .= '<details class="brag-book-gallery-filter-dropdown" id="procedure-filters-details">';
		$output .= '<summary class="brag-book-gallery-filter-dropdown__toggle">';
		$output .= '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">';
		$output .= '<path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"></path>';
		$output .= '</svg>';
		$output .= '<span>' . esc_html__( 'Filters', 'brag-book-gallery' ) . '</span>';
		$output .= '</summary>';
		$output .= '<div class="brag-book-gallery-filter-dropdown__panel">';
		$output .= '<div class="brag-book-gallery-filter-content">';
		$output .= '<div class="brag-book-gallery-filter-section">';
		$output .= '<div id="brag-book-gallery-filters">';
		$output .= '<!-- Filter options will be populated by JavaScript -->';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '<div class="brag-book-gallery-filter-actions">';
		$output .= '<button class="brag-book-gallery-button brag-book-gallery-button--apply" onclick="applyProcedureFilters()">';
		$output .= esc_html__( 'Apply Filters', 'brag-book-gallery' );
		$output .= '</button>';
		$output .= '<button class="brag-book-gallery-button brag-book-gallery-button--clear" onclick="clearProcedureFilters()">';
		$output .= esc_html__( 'Clear All', 'brag-book-gallery' );
		$output .= '</button>';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '</details>';
		$output .= '<div class="brag-book-gallery-active-filters" style="display: none;"></div>';
		$output .= '</div>';
		$output .= '<div class="brag-book-gallery-grid-selector">';
		$output .= '<span class="brag-book-gallery-grid-label">' . esc_html__( 'View:', 'brag-book-gallery' ) . '</span>';
		$output .= '<div class="brag-book-gallery-grid-buttons">';
		$output .= '<button class="brag-book-gallery-grid-btn" data-columns="2" onclick="updateGridLayout(2)" aria-label="' . esc_attr__( 'View in 2 columns', 'brag-book-gallery' ) . '">';
		$output .= '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">';
		$output .= '<rect x="1" y="1" width="6" height="6"></rect><rect x="9" y="1" width="6" height="6"></rect>';
		$output .= '<rect x="1" y="9" width="6" height="6"></rect><rect x="9" y="9" width="6" height="6"></rect>';
		$output .= '</svg>';
		$output .= '<span class="sr-only">' . esc_html__( '2 Columns', 'brag-book-gallery' ) . '</span>';
		$output .= '</button>';
		$output .= '<button class="brag-book-gallery-grid-btn active" data-columns="3" onclick="updateGridLayout(3)" aria-label="' . esc_attr__( 'View in 3 columns', 'brag-book-gallery' ) . '">';
		$output .= '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">';
		$output .= '<rect x="1" y="1" width="4" height="4"></rect><rect x="6" y="1" width="4" height="4"></rect><rect x="11" y="1" width="4" height="4"></rect>';
		$output .= '<rect x="1" y="6" width="4" height="4"></rect><rect x="6" y="6" width="4" height="4"></rect><rect x="11" y="6" width="4" height="4"></rect>';
		$output .= '<rect x="1" y="11" width="4" height="4"></rect><rect x="6" y="11" width="4" height="4"></rect><rect x="11" y="11" width="4" height="4"></rect>';
		$output .= '</svg>';
		$output .= '<span class="sr-only">' . esc_html__( '3 Columns', 'brag-book-gallery' ) . '</span>';
		$output .= '</button>';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		$output .= '<div class="brag-book-gallery-case-grid masonry-layout" data-columns="' . esc_attr( $columns ) . '" data-items-per-page="' . esc_attr( $items_per_page ) . '">';

		// Get image display mode setting.
		$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

		// Get sidebar data once for nudity checking
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$sidebar_data = null;
		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
		}

		// Check if the current procedure has nudity flag set - applies to ALL cases in this view
		// Note: Nudity detection will be handled by JavaScript using data-nudity attributes
		$procedure_has_nudity = self::procedure_has_nudity( $filter_procedure );

		foreach ( $cases_to_render as $case ) {
			// Use procedure nudity setting for this case
			$case_has_nudity = $procedure_has_nudity;

			// Render each case card.
			$output .= self::render_case_card(
				$case,
				$image_display_mode,
				$case_has_nudity,
				$filter_procedure
			);
		}

		$output .= '</div>'; // Close .brag-book-gallery-case-grid

		// Add Load More button if there are more cases than items_per_page
		if ( $total_cases > $items_per_page ) {
			// Get procedure IDs for the load more button
			$procedure_ids = '';
			if ( ! empty( $filter_procedure ) ) {
				$procedure_ids_array = self::get_procedure_ids_for_filter( $filter_procedure, $atts );
				$procedure_ids = implode( ',', $procedure_ids_array );
			}

			// Check if infinite scroll is enabled
			$infinite_scroll = get_option( 'brag_book_gallery_infinite_scroll', 'no' );
			$button_style = ( $infinite_scroll === 'yes' ) ? ' style="display: none;"' : '';

			$output .= '<div class="brag-book-gallery-load-more-container">';
			$output .= '<button class="brag-book-gallery-button brag-book-gallery-button--load-more" ';
			$output .= 'data-action="load-more" ';
			$output .= 'data-start-page="2" ';
			$output .= 'data-procedure-ids="' . esc_attr( $procedure_ids ) . '" ';
			$output .= 'data-procedure-name="' . esc_attr( $filter_procedure ) . '" ';
			$output .= 'onclick="loadMoreCasesFromCache(this)"' . $button_style . '>';
			$output .= esc_html__( 'Load More', 'brag-book-gallery' );
			$output .= '</button>';
			$output .= '</div>';
		}

		$output .= '</div>'; // Close .brag-book-gallery-cases-container

		// Add pagination if available (for API-level pagination).
		if ( ! empty( $cases_data['pagination'] ) ) {
			$gallery_slug = self::get_gallery_page_slug();
			$base_path    = get_site_url() . '/' . $gallery_slug;
			$output      .= self::render_pagination( $cases_data['pagination'], $base_path, $filter_procedure );
		}

		$output .= '</div>'; // Close .brag-book-gallery-main-content
		$output .= '</div>'; // Close .brag-book-gallery-wrapper

		return $output;
	}

	/**
	 * Render single case
	 *
	 * Displays a single case with all its details.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case identifier (ID or SEO suffix).
	 * @param array  $atts    Shortcode attributes.
	 *
	 * @return string HTML output.
	 */
	public static function render_single_case( string $case_id, array $atts ): string {
		// Sanitize case ID.
		$case_id = sanitize_text_field( $case_id );

		// Debug logging for single case rendering.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'render_single_case - Looking for case ID: ' . $case_id );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Website Property ID: ' . $atts['website_property_id'] );
		}

		// Try to get from cache first.
		$case_data = self::get_case_from_cache( $case_id, $atts );

		// If not in cache, fetch from API.
		if ( ! $case_data ) {
			$case_data = self::fetch_case_from_api( $case_id, $atts );
		}

		if ( ! $case_data ) {
			return sprintf(
				'<div class="brag-book-gallery-case-not-found">%s</div>',
				esc_html__( 'Case not found', 'brag-book-gallery' )
			);
		}

		// Enqueue cases assets.
		Asset_Manager::enqueue_cases_assets();

		// Render the case details.
		return self::render_case_details( $case_data );
	}

	/**
	 * Get case from cache
	 *
	 * Attempts to retrieve a case from cached data.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case identifier.
	 * @param array  $atts    Shortcode attributes.
	 *
	 * @return array|null Case data or null if not found.
	 */
	private static function get_case_from_cache( string $case_id, array $atts ): ?array {
		$cache_key   = 'brag_book_gallery_all_cases';
		// $cached_data = false; // Cache_Manager::get( $cache_key );

		// Check if cache exists and has valid data.
		if ( ! $cached_data || ! isset( $cached_data['data'] ) || ! is_array( $cached_data['data'] ) || empty( $cached_data['data'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'No valid cached data available' );
			}
			return null;
		}

		// Search for the case in cached data.
		foreach ( $cached_data['data'] as $case ) {
			// Check by ID (loose comparison to handle string/int mismatch).
			if ( isset( $case['id'] ) && ( strval( $case['id'] ) === strval( $case_id ) ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Case found in cache by ID!' );
				}
				return $case;
			}

			// Also check by SEO suffix if available.
			if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
				foreach ( $case['caseDetails'] as $detail ) {
					if ( ! empty( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							error_log( 'Case found in cache by SEO suffix!' );
						}
						return $case;
					}
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Case not found in cache. Total cached cases: ' . count( $cached_data['data'] ) );
		}

		return null;
	}

	/**
	 * Fetch case from API
	 *
	 * Retrieves a single case from the API.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case identifier.
	 * @param array  $atts    Shortcode attributes.
	 *
	 * @return array|null Case data or null if not found.
	 */
	private static function fetch_case_from_api( string $case_id, array $atts ): ?array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Fetching case from API...' );
		}

		// Try to get all cases (which might give us the case we need).
		$all_cases = Data_Fetcher::get_all_cases_for_filtering(
			$atts['api_token'],
			$atts['website_property_id']
		);

		if ( ! empty( $all_cases['data'] ) ) {
			foreach ( $all_cases['data'] as $case ) {
				// Check by ID.
				if ( isset( $case['id'] ) && ( strval( $case['id'] ) === strval( $case_id ) ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( 'Case found via API by ID!' );
					}
					return $case;
				}

				// Check by SEO suffix.
				if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
					foreach ( $case['caseDetails'] as $detail ) {
						if ( ! empty( $detail['seoSuffixUrl'] ) && $detail['seoSuffixUrl'] === $case_id ) {
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
								error_log( 'Case found via API by SEO suffix!' );
							}
							return $case;
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Render comprehensive single case details with semantic HTML
	 *
	 * Generates a complete single case view with before/after image galleries,
	 * patient demographics, and procedure information. Uses semantic HTML5
	 * markup with proper accessibility attributes and WordPress VIP compliant
	 * output escaping.
	 *
	 * Features:
	 * - Before/after image gallery with proper labeling
	 * - Patient demographics display (age, gender, ethnicity)
	 * - Procedure descriptions with wp_kses_post sanitization
	 * - Semantic HTML5 structure with accessibility compliance
	 * - Responsive image handling with proper alt attributes
	 * - WordPress internationalization support
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Complete case data array from API containing photoSets, demographics, descriptions.
	 *
	 * @return string Semantic HTML output for single case display.
	 */
	private static function render_case_details( array $case_data ): string {
		ob_start();
		?>
		<div class="brag-book-gallery-single-case">
			<div class="case-images">
				<?php
				if ( ! empty( $case_data['photoSets'] ) ) {
					foreach ( $case_data['photoSets'] as $photo_set ) {
						if ( ! empty( $photo_set['beforePhoto'] ) ) {
							?>
							<div class="case-image before-image">
								<img src="<?php echo esc_url( $photo_set['beforePhoto'] ); ?>"
									 alt="<?php esc_attr_e( 'Before', 'brag-book-gallery' ); ?>" />
								<span class="image-label"><?php esc_html_e( 'Before', 'brag-book-gallery' ); ?></span>
							</div>
							<?php
						}
						if ( ! empty( $photo_set['afterPhoto'] ) ) {
							?>
							<div class="case-image after-image">
								<img src="<?php echo esc_url( $photo_set['afterPhoto'] ); ?>"
									 alt="<?php esc_attr_e( 'After', 'brag-book-gallery' ); ?>" />
								<span class="image-label"><?php esc_html_e( 'After', 'brag-book-gallery' ); ?></span>
							</div>
							<?php
						}
					}
				}
				?>
			</div>

			<div class="case-details">
				<?php if ( ! empty( $case_data['description'] ) ) : ?>
					<div class="case-description">
						<?php echo wp_kses_post( $case_data['description'] ); ?>
					</div>
				<?php endif; ?>

				<div class="case-meta">
					<?php if ( ! empty( $case_data['age'] ) ) : ?>
						<div class="meta-item">
							<span class="meta-label"><?php esc_html_e( 'Age:', 'brag-book-gallery' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $case_data['age'] ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $case_data['gender'] ) ) : ?>
						<div class="meta-item">
							<span class="meta-label"><?php esc_html_e( 'Gender:', 'brag-book-gallery' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $case_data['gender'] ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $case_data['ethnicity'] ) ) : ?>
						<div class="meta-item">
							<span class="meta-label"><?php esc_html_e( 'Ethnicity:', 'brag-book-gallery' ); ?></span>
							<span class="meta-value"><?php echo esc_html( $case_data['ethnicity'] ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php

		$output = ob_get_clean();

		// Prevent wpautop from adding unwanted <p> and <br> tags to shortcode output
		return '<!--brag-book-gallery-start-->' . $output . '<!--brag-book-gallery-end-->';
	}

	/**
	 * Render case card for main gallery AJAX
	 *
	 * Generates HTML specifically for main gallery case cards loaded via AJAX.
	 * This uses the exact structure expected by the main gallery JavaScript.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case                Case data.
	 * @param string $image_display_mode  Image display mode.
	 * @param bool   $procedure_nudity    Whether procedure has nudity.
	 * @param string $procedure_context   Procedure context from filter.
	 *
	 * @return string HTML output.
	 */
	/**
	 * Render case card for main gallery AJAX
	 *
	 * Generates HTML specifically for main gallery case cards loaded via AJAX.
	 * This uses the exact structure expected by the main gallery JavaScript.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case                Case data.
	 * @param string $image_display_mode  Image display mode.
	 * @param bool   $procedure_nudity    Whether procedure has nudity.
	 * @param string $procedure_context   Procedure context from filter.
	 *
	 * @return string HTML output.
	 */
	public static function render_ajax_case_card(
		array $case,
		string $image_display_mode,
		bool $procedure_nudity = false,
		string $procedure_context = ''
	): string {
		$case_id = sanitize_text_field( $case['id'] ?? '' );

		if ( empty( $case_id ) ) {
			return '';
		}

		// Build data attributes
		$data_attrs = [
			'data-card="true"',
			sprintf( 'data-case-id="%s"', esc_attr( $case_id ) ),
		];

		// Add optional filtering attributes
		if ( ! empty( $case['age'] ) ) {
			$data_attrs[] = sprintf( 'data-age="%s"', esc_attr( $case['age'] ) );
		}
		if ( ! empty( $case['gender'] ) ) {
			$data_attrs[] = sprintf( 'data-gender="%s"', esc_attr( strtolower( $case['gender'] ) ) );
		}
		if ( ! empty( $case['ethnicity'] ) ) {
			$data_attrs[] = sprintf( 'data-ethnicity="%s"', esc_attr( strtolower( $case['ethnicity'] ) ) );
		}

		// Add procedure IDs
		$procedure_ids = '';
		if ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'absint', $case['procedureIds'] ) );
			$data_attrs[] = sprintf( 'data-procedure-ids="%s"', esc_attr( $procedure_ids ) );
		}

		// Build case URL
		$gallery_slug = self::get_gallery_page_slug();

		// Extract SEO suffix
		$seo_suffix = $case_id;
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			$seo_suffix = $first_detail['seoSuffixUrl'] ?? $case_id;
		}

		$procedure_slug = ! empty( $procedure_context )
			? sanitize_title( $procedure_context )
			: 'case';

		$case_url = home_url( sprintf( '/%s/%s/%s/', $gallery_slug, $procedure_slug, $seo_suffix ) );

		// Extract main image URL with priority checking
		$main_image_url = '';
		$alt_text = sprintf( ' - Case %s', $case_id );

		// Check photoSets first
		if ( empty( $main_image_url ) && ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photo = reset( $case['photoSets'] );
			$image_fields = [
				'postProcessedImageLocation',
				'afterLocationUrl1',
				'beforeLocationUrl',
				'afterPhoto',
				'beforePhoto',
			];

			foreach ( $image_fields as $field ) {
				if ( ! empty( $first_photo[ $field ] ) ) {
					$main_image_url = $first_photo[ $field ];
					break;
				}
			}
		}

		// Check direct case properties
		$main_image_url = $main_image_url ?: ( $case['afterImage'] ?? $case['beforeImage'] ?? $case['mainImageUrl'] ?? '' );

		// Check caseDetails as last resort
		if ( empty( $main_image_url ) && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			foreach ( $case['caseDetails'] as $detail ) {
				if ( ! empty( $detail['afterPhoto'] ) ) {
					$main_image_url = $detail['afterPhoto'];
					break;
				}
				if ( ! empty( $detail['beforePhoto'] ) ) {
					$main_image_url = $detail['beforePhoto'];
					break;
				}
			}
		}

		// Get procedure name - prioritize the procedure context from the current filter/URL
		$procedure_name = 'Case';
		if ( ! empty( $procedure_context ) ) {
			// Use the procedure context passed from AJAX (this maintains the current page context)
			$procedure_name = ucwords( str_replace( '-', ' ', $procedure_context ) );
		} elseif ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			// Try to find procedure that matches current page context first
			$current_procedure = null;
			$current_path = $_SERVER['REQUEST_URI'] ?? '';
			$path_segments = array_filter( explode( '/', $current_path ) );
			$url_procedure_slug = count( $path_segments ) >= 3 ? $path_segments[2] : '';

			// Look for procedure that matches current URL slug
			if ( ! empty( $url_procedure_slug ) ) {
				foreach ( $case['procedures'] as $procedure ) {
					if ( ! empty( $procedure['name'] ) ) {
						$proc_slug = sanitize_title( $procedure['name'] );
						if ( $proc_slug === $url_procedure_slug ) {
							$current_procedure = $procedure;
							break;
						}
					}
				}
			}

			// Fall back to first procedure if no match found
			if ( ! $current_procedure ) {
				$current_procedure = reset( $case['procedures'] );
			}

			$procedure_name = $current_procedure['name'] ?? 'Case';
		}

		// Build HTML
		$html = sprintf(
			'<article class="brag-book-gallery-case-card" %s>',
			implode( ' ', $data_attrs )
		);

		// Image section
		$html .= '<div class="brag-book-gallery-case-images single-image">
        <div class="brag-book-gallery-single-image">
            <div class="brag-book-gallery-image-container">
                <div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>';

		// Favorites button - only if favorites are enabled
		if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) {
			$html .= sprintf(
				'<div class="brag-book-gallery-item-actions">
				<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case-%s" aria-label="Add to favorites">
					<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
					</svg>
				</button>
			</div>',
				esc_attr( $case_id )
			);
		}

		// Case link with image
		$html .= sprintf(
			'<a href="%s" class="brag-book-gallery-case-card-link" data-case-id="%s" data-procedure-ids="%s">
            <picture class="brag-book-gallery-picture">',
			esc_url( $case_url ),
			esc_attr( $case_id ),
			esc_attr( $procedure_ids )
		);

		if ( ! empty( $main_image_url ) ) {
			$html .= sprintf(
				'<img src="%s" alt="%s" loading="lazy" data-image-type="single" data-image-url="%s" onload="this.closest(\'.brag-book-gallery-image-container\').querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';">',
				esc_url( $main_image_url ),
				esc_attr( $alt_text ),
				esc_url( $main_image_url )
			);
		}

		$html .= '</picture></a></div></div></div>';

		// Add nudity warning if needed
		if ( $procedure_nudity ) {
			$html .= self::render_nudity_warning();
		}

		// Details section
		$html .= sprintf(
			'<details class="brag-book-gallery-case-card-details">
            <summary class="brag-book-gallery-case-card-summary">
                <div class="brag-book-gallery-case-card-summary-info">
                    <span class="brag-book-gallery-case-card-summary-info__name">%s</span>
                    <span class="brag-book-gallery-case-card-summary-info__case-number">%s #%s</span>
                </div>
                <div class="brag-book-gallery-case-card-summary-details">
                    <p class="brag-book-gallery-case-card-summary-details__more">
                        <strong>%s</strong>
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                            <path d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>
                        </svg>
                    </p>
                </div>
            </summary>',
			esc_html( $procedure_name ),
			esc_html__( 'Case', 'brag-book-gallery' ),
			esc_html( $case_id ),
			esc_html__( 'More Details', 'brag-book-gallery' )
		);

		// Procedures list
		$html .= '<div class="brag-book-gallery-case-card-details-content">
        <p class="brag-book-gallery-case-card-details-content__title">' . esc_html__( 'Procedures Performed:', 'brag-book-gallery' ) . '</p>
        <ul class="brag-book-gallery-case-card-procedures-list">';

		if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			foreach ( $case['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$html .= sprintf(
						'<li class="brag-book-gallery-case-card-procedures-list__item">%s</li>',
						esc_html( $procedure['name'] )
					);
				}
			}
		}

		$html .= '</ul></div></details></article>';

		return $html;
	}

	/**
	 * Render case card for grid display
	 *
	 * Generates HTML for a single case card in the grid.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case                Case data.
	 * @param string $image_display_mode  Image display mode.
	 * @param bool   $procedure_nudity    Whether procedure has nudity.
	 * @param string $procedure_context   Procedure context from filter.
	 *
	 * @return string HTML output.
	 */
	public static function render_case_card(
		array $case,
		string $image_display_mode,
		bool $procedure_nudity = false,
		string $procedure_context = ''
	): string {
		$html = '';

		// Prepare data attributes for filtering.
		$data_attrs = self::prepare_case_data_attributes( $case );

		// Get case ID and SEO information.
		$case_info = self::extract_case_info( $case );

		// Get procedure IDs for this case.
		$procedure_ids = '';
		if ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'intval', $case['procedureIds'] ) );
		}

		$html .= sprintf(
			'<article class="brag-book-gallery-case-card" %s data-case-id="%s" data-procedure-ids="%s">',
			$data_attrs,
			esc_attr( $case_info['case_id'] ),
			esc_attr( $procedure_ids )
		);

		// Get case URL.
		$case_url = self::get_case_url( $case_info, $procedure_context, $case );

		// Add case content.
		$html .= '<a href="' . esc_url( $case_url ) . '" class="case-link">';

		// Add images.
		if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photo = reset( $case['photoSets'] );

			if ( 'before_after' === $image_display_mode ) {
				// Show both before and after images.
				$html .= '<div class="brag-book-gallery-case-images before-after">';
				if ( ! empty( $first_photo['beforePhoto'] ) ) {
					$html .= sprintf(
						'<img src="%s" alt="%s" class="before-image" />',
						esc_url( $first_photo['beforePhoto'] ),
						esc_attr__( 'Before', 'brag-book-gallery' )
					);
				}
				if ( ! empty( $first_photo['afterPhoto'] ) ) {
					$html .= sprintf(
						'<img src="%s" alt="%s" class="after-image" />',
						esc_url( $first_photo['afterPhoto'] ),
						esc_attr__( 'After', 'brag-book-gallery' )
					);
				}
				$html .= '</div>';
			} else {
				// Show single image (after preferred, fallback to before).
				$image_url = $first_photo['afterPhoto'] ?? $first_photo['beforePhoto'] ?? '';
				if ( ! empty( $image_url ) ) {
					$html .= sprintf(
						'<div class="brag-book-gallery-case-images"><img src="%s" alt="%s" /></div>',
						esc_url( $image_url ),
						esc_attr__( 'Case Image', 'brag-book-gallery' )
					);
				}
			}
		}

		// Add nudity warning only if the current procedure has nudity.
		if ( $procedure_nudity ) {
			if ( WP_DEBUG ) {
				error_log( 'BRAGBook: render_case_card - Adding nudity warning for case: ' . $case_info['case_id'] . ' (procedure has nudity)' );
			}
			$html .= self::render_nudity_warning();
		} elseif ( WP_DEBUG ) {
			error_log( 'BRAGBook: render_case_card - NOT adding nudity warning for case: ' . $case_info['case_id'] . ' (procedure_nudity = false)' );
		}

		// Add case title if available.
		if ( ! empty( $case_info['seo_headline'] ) ) {
			$html .= '<h3 class="case-title">' . esc_html( $case_info['seo_headline'] ) . '</h3>';
		}

		$html .= '</a>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Prepare case data attributes
	 *
	 * Prepares data attributes for case card filtering.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return string Data attributes HTML.
	 */
	private static function prepare_case_data_attributes( array $case ): string {
		$attrs = 'data-card="true"';

		// Add age.
		if ( ! empty( $case['age'] ) ) {
			$attrs .= ' data-age="' . esc_attr( $case['age'] ) . '"';
		}

		// Add gender.
		if ( ! empty( $case['gender'] ) ) {
			$attrs .= ' data-gender="' . esc_attr( strtolower( $case['gender'] ) ) . '"';
		}

		// Add ethnicity.
		if ( ! empty( $case['ethnicity'] ) ) {
			$attrs .= ' data-ethnicity="' . esc_attr( strtolower( $case['ethnicity'] ) ) . '"';
		}

		// Add height with unit.
		if ( ! empty( $case['height'] ) ) {
			$height_value = $case['height'];
			$height_unit  = ! empty( $case['heightUnit'] ) ? $case['heightUnit'] : '';
			$attrs       .= ' data-height="' . esc_attr( $height_value ) . '"';
			$attrs       .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
			$attrs       .= ' data-height-full="' . esc_attr( $height_value . $height_unit ) . '"';
		}

		// Add weight with unit.
		if ( ! empty( $case['weight'] ) ) {
			$weight_value = $case['weight'];
			$weight_unit  = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
			$attrs       .= ' data-weight="' . esc_attr( $weight_value ) . '"';
			$attrs       .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
			$attrs       .= ' data-weight-full="' . esc_attr( $weight_value . $weight_unit ) . '"';
		}

		return $attrs;
	}

	/**
	 * Get gallery page slug with legacy array format handling
	 *
	 * @since 3.0.0
	 * @param string $default Default value if option is not set
	 * @return string Gallery page slug
	 */
	private static function get_gallery_page_slug( string $default = 'gallery' ): string {
		$option = get_option( 'brag_book_gallery_page_slug', $default );

		// Handle legacy array format from old Slug Helper
		if ( is_array( $option ) ) {
			return $option[0] ?? $default;
		}

		return $option ?: $default;
	}

	/**
	 * Extract case information
	 *
	 * Extracts case ID and SEO information from case data.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return array Case information array.
	 */
	private static function extract_case_info( array $case ): array {
		$info = array(
			'case_id'              => $case['id'] ?? '',
			'seo_suffix_url'       => '',
			'seo_headline'         => '',
			'seo_page_title'       => '',
			'seo_page_description' => '',
		);

		// Extract SEO fields from caseDetails if available.
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );

			if ( empty( $info['case_id'] ) ) {
				$info['case_id'] = $first_detail['caseId'] ?? '';
			}

			$info['seo_suffix_url']       = $first_detail['seoSuffixUrl'] ?? '';
			$info['seo_headline']         = $first_detail['seoHeadline'] ?? '';
			$info['seo_page_title']       = $first_detail['seoPageTitle'] ?? '';
			$info['seo_page_description'] = $first_detail['seoPageDescription'] ?? '';
		}

		// Use seoSuffixUrl for URL if available, otherwise use case_id.
		$info['url_suffix'] = ! empty( $info['seo_suffix_url'] ) ? $info['seo_suffix_url'] : $info['case_id'];

		return $info;
	}

	/**
	 * Get case URL
	 *
	 * Generates the URL for a case detail page.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case_info         Case information.
	 * @param string $procedure_context Procedure context.
	 * @param array  $case              Full case data.
	 *
	 * @return string Case URL.
	 */
	private static function get_case_url( array $case_info, string $procedure_context, array $case ): string {
		// Get query vars.
		$filter_procedure = sanitize_text_field( get_query_var( 'filter_procedure', '' ) );
		$procedure_title  = sanitize_text_field( get_query_var( 'procedure_title', '' ) );

		// Determine procedure slug.
		$procedure_slug = '';

		// First priority: use procedure context passed from AJAX filter.
		if ( ! empty( $procedure_context ) ) {
			$procedure_slug = sanitize_title( $procedure_context );
		} elseif ( ! empty( $filter_procedure ) ) {
			$procedure_slug = $filter_procedure;
		} elseif ( ! empty( $procedure_title ) ) {
			$procedure_slug = $procedure_title;
		} else {
			// Parse current URL to get procedure slug.
			$procedure_slug = self::extract_procedure_from_url( $case );
		}

		// Build the URL.
		$gallery_slug = self::get_gallery_page_slug();
		$base_url     = home_url( '/' . $gallery_slug );

		return sprintf(
			'%s/%s/%s/',
			$base_url,
			$procedure_slug,
			$case_info['url_suffix']
		);
	}

	/**
	 * Extract procedure slug from URL with intelligent fallback strategies
	 *
	 * Extracts procedure identifier from the current request URL using
	 * sophisticated pattern matching, with multiple fallback mechanisms
	 * for various URL structures and case data formats.
	 *
	 * Extraction Strategy (in order of precedence):
	 * 1. URL pattern matching: /gallery-slug/procedure-slug/case-id/
	 * 2. Case data procedures array (first procedure name)
	 * 3. Default fallback: 'case'
	 *
	 * Security Features:
	 * - Safe $_SERVER access with null coalescing
	 * - Regex pattern with proper escaping
	 * - WordPress sanitization of extracted slugs
	 * - XSS prevention through output sanitization
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Complete case data array with optional procedures information.
	 *
	 * @return string Sanitized procedure slug for URL generation.
	 */
	private static function extract_procedure_from_url( array $case ): string {
		// Safe extraction of current URL with null coalescing
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URL parsing only.
		$current_url = $_SERVER['REQUEST_URI'] ?? '';

		if ( empty( $current_url ) ) {
			return self::extract_procedure_from_case_data( $case );
		}

		$current_url  = wp_unslash( $current_url );
		$gallery_slug = self::get_gallery_page_slug();

		// Build regex pattern with proper escaping
		$pattern = '/' . preg_quote( $gallery_slug, '/' ) . '/([^/]+)(?:/|$)/';

		// Extract procedure slug from URL structure
		if ( preg_match( $pattern, $current_url, $matches ) && ! empty( $matches[1] ) ) {
			return sanitize_title( $matches[1] );
		}

		// Fallback to case data extraction
		return self::extract_procedure_from_case_data( $case );
	}

	/**
	 * Extract procedure slug from case data with PHP 8.2 null coalescing
	 *
	 * Extracts procedure information directly from case data structure
	 * using PHP 8.2 null coalescing operators for safe array access.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data array with optional procedures.
	 *
	 * @return string Sanitized procedure slug or default 'case'.
	 */
	private static function extract_procedure_from_case_data( array $case ): string {
		// Use PHP 8.2 null coalescing for safe nested array access
		$first_procedure = $case['procedures'][0] ?? null;
		$procedure_name  = $first_procedure['name'] ?? 'case';

		return sanitize_title( $procedure_name );
	}

	/**
	 * Render nudity warning
	 *
	 * Generates HTML for nudity warning overlay.
	 *
	 * @since 3.0.0
	 *
	 * @return string HTML output.
	 */
	private static function render_nudity_warning(): string {
		ob_start();
		?>
		<div class="brag-book-gallery-nudity-warning">
			<div class="brag-book-gallery-nudity-warning-content">
				<h4 class="brag-book-gallery-nudity-warning-title">
					<?php esc_html_e( 'Nudity Warning', 'brag-book-gallery' ); ?>
				</h4>
				<p class="brag-book-gallery-nudity-warning-caption">
					<?php esc_html_e( 'This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.', 'brag-book-gallery' ); ?>
				</p>
				<button class="brag-book-gallery-nudity-warning-button">
					<?php esc_html_e( 'Proceed', 'brag-book-gallery' ); ?>
				</button>
			</div>
		</div>
		<?php
		$output = ob_get_clean();

		// Prevent wpautop from adding unwanted <p> and <br> tags to shortcode output
		return '<!--brag-book-gallery-start-->' . $output . '<!--brag-book-gallery-end-->';
	}

	/**
	 * Render pagination
	 *
	 * Generates pagination HTML for cases grid.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $pagination       Pagination data.
	 * @param string $base_path        Base URL path.
	 * @param string $filter_procedure Filter procedure if any.
	 *
	 * @return string HTML output.
	 */
	private static function render_pagination( array $pagination, string $base_path, string $filter_procedure ): string {
		$current_page = absint( $pagination['currentPage'] ?? 1 );
		$total_pages  = absint( $pagination['totalPages'] ?? 1 );

		if ( $total_pages <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<div class="brag-book-gallery-pagination">
			<?php
			// Previous link.
			if ( $current_page > 1 ) {
				$prev_url = $base_path;
				if ( $filter_procedure ) {
					$prev_url .= '/' . $filter_procedure;
				}
				$prev_url .= '?page=' . ( $current_page - 1 );
				?>
				<a href="<?php echo esc_url( $prev_url ); ?>" class="prev-page">
					<?php esc_html_e( '&laquo; Previous', 'brag-book-gallery' ); ?>
				</a>
				<?php
			}

			// Page numbers.
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				if ( $i === $current_page ) {
					?>
					<span class="current-page"><?php echo esc_html( (string) $i ); ?></span>
					<?php
				} else {
					$page_url = $base_path;
					if ( $filter_procedure ) {
						$page_url .= '/' . $filter_procedure;
					}
					$page_url .= '?page=' . $i;
					?>
					<a href="<?php echo esc_url( $page_url ); ?>" class="page-number">
						<?php echo esc_html( (string) $i ); ?>
					</a>
					<?php
				}
			}

			// Next link.
			if ( $current_page < $total_pages ) {
				$next_url = $base_path;
				if ( $filter_procedure ) {
					$next_url .= '/' . $filter_procedure;
				}
				$next_url .= '?page=' . ( $current_page + 1 );
				?>
				<a href="<?php echo esc_url( $next_url ); ?>" class="next-page">
					<?php esc_html_e( 'Next &raquo;', 'brag-book-gallery' ); ?>
				</a>
				<?php
			}
			?>
		</div>
		<?php

		$output = ob_get_clean();

		// Prevent wpautop from adding unwanted <p> and <br> tags to shortcode output
		return '<!--brag-book-gallery-start-->' . $output . '<!--brag-book-gallery-end-->';
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
	 * Check if the current procedure being viewed has nudity flag set to true.
	 *
	 * This method will be handled by JavaScript which can check the data-nudity
	 * attribute on the active procedure link. For server-side rendering, we'll
	 * pass the procedure context and let JavaScript handle the nudity detection.
	 *
	 * @since 3.0.0
	 *
	 * @param string $filter_procedure Current procedure being viewed.
	 *
	 * @return bool Always returns false for server-side rendering (JS will handle it).
	 */
	private static function procedure_has_nudity( string $filter_procedure ): bool {
		// This will be handled by JavaScript using data-nudity attributes
		// Return false for server-side rendering to avoid complexity
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: procedure_has_nudity - Deferring to JavaScript for procedure: ' . $filter_procedure );
		}
		return false;
	}

	/**
	 * Get case meta data with missing data logging
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @return array Case meta data with defaults.
	 */
	private static function get_case_meta_data( int $post_id ): array {
		$meta_fields = [
			'case_id' => '',
			'patient_age' => '',
			'patient_gender' => '',
			'patient_ethnicity' => '',
			'patient_height' => '',
			'patient_weight' => '',
			'procedure_date' => '',
			'case_notes' => '',
			'before_image' => '',
			'after_image' => '',
		];

		$case_data = [];
		foreach ( $meta_fields as $field => $default ) {
			$value = get_post_meta( $post_id, $field, true );
			if ( empty( $value ) && $value !== '0' ) {
				self::log_missing_data( $post_id, $field );
				$value = $default;
			}
			$case_data[ $field ] = $value;
		}

		return $case_data;
	}

	/**
	 * Get case procedures from taxonomy
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @return array Array of procedure data.
	 */
	private static function get_case_procedures( int $post_id ): array {
		$procedure_terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_PROCEDURES );

		if ( is_wp_error( $procedure_terms ) || empty( $procedure_terms ) ) {
			self::log_missing_data( $post_id, 'procedures_taxonomy' );
			return [];
		}

		$procedures = [];
		foreach ( $procedure_terms as $term ) {
			$procedure_meta = self::get_procedure_meta( $term->term_id );
			$procedures[] = [
				'id' => $procedure_meta['procedure_id'] ?: $term->term_id,
				'procedure_id' => $procedure_meta['procedure_id'] ?: $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'seoSuffixUrl' => $term->slug,
				'nudity' => 'true' === $procedure_meta['nudity'],
			];
		}

		return $procedures;
	}

	/**
	 * Get procedure metadata
	 *
	 * @since 3.0.0
	 * @param int $term_id The term ID.
	 * @return array Procedure meta data with defaults.
	 */
	private static function get_procedure_meta( int $term_id ): array {
		return [
			'procedure_id' => get_term_meta( $term_id, 'procedure_id', true ) ?: '',
			'member_id'    => get_term_meta( $term_id, 'member_id', true ) ?: '',
			'nudity'       => get_term_meta( $term_id, 'nudity', true ) ?: 'false',
			'banner_image' => get_term_meta( $term_id, 'banner_image', true ) ?: '',
		];
	}

	/**
	 * Log missing data
	 *
	 * @since 3.0.0
	 * @param int $post_id The post ID.
	 * @param string $field The missing field name.
	 * @return void
	 */
	private static function log_missing_data( int $post_id, string $field ): void {
		self::$missing_data_log[] = [
			'post_id' => $post_id,
			'field'   => $field,
			'time'    => current_time( 'mysql' ),
		];

		// Log to WordPress debug log if enabled
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'BRAGBook Gallery: Missing data for post %d, field: %s',
				$post_id,
				$field
			) );
		}
	}

	/**
	 * Get missing data log
	 *
	 * @since 3.0.0
	 * @return array The missing data log.
	 */
	public static function get_missing_data_log(): array {
		return self::$missing_data_log;
	}

	/**
	 * Render WordPress post-based case card
	 *
	 * Renders a case card using WordPress post data with the correct HTML structure
	 * matching the API-based case cards.
	 *
	 * @since 3.0.0
	 * @param array $case_data WordPress post-based case data.
	 * @param string $image_display_mode Display mode for images.
	 * @param bool $procedure_nudity Whether procedure has nudity warning.
	 * @param string $procedure_context Context for case display.
	 * @return string Generated case card HTML.
	 */
	public static function render_wordpress_case_card(
		array $case_data,
		string $image_display_mode = 'single',
		bool $procedure_nudity = false,
		string $procedure_context = ''
	): string {
		$case_id = $case_data['id'] ?? $case_data['post_id'] ?? '';
		$post_id = $case_data['post_id'] ?? '';
		$images = $case_data['images'] ?? [];
		$procedures = $case_data['procedures'] ?? [];

		// Get procedure IDs from taxonomy terms
		$procedure_ids = [];
		if ( $post_id ) {
			$terms = wp_get_post_terms( $post_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
			if ( ! is_wp_error( $terms ) ) {
				$procedure_ids = wp_list_pluck( $terms, 'term_id' );
			}
		}

		// Get case permalink
		$case_url = $post_id ? get_permalink( $post_id ) : '#';

		// Debug permalink generation - check required meta fields
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$case_id_meta = get_post_meta( $post_id, '_case_id', true );
			$seo_suffix_meta = get_post_meta( $post_id, '_case_seo_suffix_url', true );
			$debug_procedures = wp_get_post_terms( $post_id, 'procedures' );
			error_log( "BRAGBook Debug: Post ID $post_id, generated case_url: $case_url" );
			error_log( "BRAGBook Debug: _case_id meta: " . ( $case_id_meta ?: 'MISSING' ) );
			error_log( "BRAGBook Debug: _case_seo_suffix_url meta: " . ( $seo_suffix_meta ?: 'MISSING' ) );
			error_log( "BRAGBook Debug: procedures taxonomy: " . ( !is_wp_error($debug_procedures) && !empty($debug_procedures) ? $debug_procedures[0]->slug : 'MISSING' ) );
		}

		// Get primary procedure name for display
		$primary_procedure = is_array( $procedures ) && ! empty( $procedures ) ? $procedures[0] : 'Case';

		// Build data attributes
		$data_attrs = [
			'data-card="true"',
			'data-case-id="' . esc_attr( $case_id ) . '"',
		];

		// Add demographic data attributes
		if ( ! empty( $case_data['age'] ) ) {
			$data_attrs[] = 'data-age="' . esc_attr( $case_data['age'] ) . '"';
		}
		if ( ! empty( $case_data['gender'] ) ) {
			$data_attrs[] = 'data-gender="' . esc_attr( strtolower( $case_data['gender'] ) ) . '"';
		}
		if ( ! empty( $case_data['ethnicity'] ) ) {
			$data_attrs[] = 'data-ethnicity="' . esc_attr( strtolower( $case_data['ethnicity'] ) ) . '"';
		}
		if ( ! empty( $procedure_ids ) ) {
			$data_attrs[] = 'data-procedure-ids="' . esc_attr( implode( ',', $procedure_ids ) ) . '"';
		}

		// Get first image from additional images (gallery) - this should be the primary source
		$image_url = '';
		$image_alt = '';
		if ( $post_id ) {
			// Get gallery images array
			$gallery_images = get_post_meta( $post_id, '_case_gallery_images', true );

			// Debug logging - let's see gallery images
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( "BRAGBook Debug: Post ID $post_id _case_gallery_images: " . print_r( $gallery_images, true ) );
			}

			// Use first image from gallery if available
			if ( is_array( $gallery_images ) && ! empty( $gallery_images ) ) {
				$first_image_id = $gallery_images[0];
				$image_url = wp_get_attachment_image_url( $first_image_id, 'large' );
				$image_alt = sprintf( '%s - Case %s', is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure, $case_id );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( "BRAGBook Debug: Using first gallery image ID $first_image_id, URL: " . ( $image_url ?: 'FAILED TO GET URL' ) );
				}
			}
		}

		// Final debug log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( "BRAGBook Debug: Final image_url for case $case_id: " . ( $image_url ?: 'empty' ) );
		}

		ob_start();
		?>
		<article class="brag-book-gallery-case-card" <?php echo implode( ' ', $data_attrs ); ?>>
			<div class="brag-book-gallery-case-images single-image">
				<div class="brag-book-gallery-single-image">
					<div class="brag-book-gallery-image-container">
						<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>
						<div class="brag-book-gallery-item-actions">
							<button class="brag-book-gallery-favorite-button"
									data-favorited="false"
									data-item-id="case-<?php echo esc_attr( $case_id ); ?>"
									aria-label="Add to favorites">
								<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
								</svg>
							</button>
						</div>
						<a href="<?php echo esc_url( $case_url ); ?>"
						   class="brag-book-gallery-case-permalink"
						   data-case-id="<?php echo esc_attr( $case_id ); ?>"
						   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>">
							<?php
							// Debug: Log the image URL right before conditional check
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
								error_log( "BRAGBook Debug: About to check image_url for case $case_id. Value: '" . $image_url . "', empty check: " . ( empty( $image_url ) ? 'TRUE (will skip image)' : 'FALSE (will show image)' ) );
							}

							if ( ! empty( $image_url ) ) : ?>
								<picture class="brag-book-gallery-picture">
									<img src="<?php echo esc_url( $image_url ); ?>"
										 alt="<?php echo esc_attr( $image_alt ?: 'Case Image' ); ?>"
										 loading="eager"
										 data-image-type="single"
										 data-image-url="<?php echo esc_url( $image_url ); ?>"
										 onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';"
										 fetchpriority="high">
								</picture>
							<?php else : ?>
								<!-- DEBUG: No image URL found for case <?php echo esc_attr( $case_id ); ?> -->
							<?php endif; ?>
						</a>
					</div>
				</div>
			</div>
			<details class="brag-book-gallery-case-card-details">
				<summary class="brag-book-gallery-case-card-summary">
					<div class="brag-book-gallery-case-card-summary-info">
						<span class="brag-book-gallery-case-card-summary-info__name"><?php echo esc_html( is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure ); ?></span>
						<span class="brag-book-gallery-case-card-summary-info__case-number">Case #<?php echo esc_html( $case_id ); ?></span>
					</div>
					<div class="brag-book-gallery-case-card-summary-details">
						<p class="brag-book-gallery-case-card-summary-details__more">
							<strong>More Details</strong>
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
								<path d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>
							</svg>
						</p>
					</div>
				</summary>
				<div class="brag-book-gallery-case-card-details-content">
					<p class="brag-book-gallery-case-card-details-content__title">Procedures Performed:</p>
					<ul class="brag-book-gallery-case-card-procedures-list">
						<?php if ( ! empty( $procedures ) && is_array( $procedures ) ) : ?>
							<?php foreach ( $procedures as $procedure ) : ?>
								<li class="brag-book-gallery-case-card-procedures-list__item">
									<?php echo esc_html( is_object( $procedure ) ? $procedure->name : $procedure ); ?>
								</li>
							<?php endforeach; ?>
						<?php else : ?>
							<li class="brag-book-gallery-case-card-procedures-list__item">
								<?php echo esc_html( is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure ); ?>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</details>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler for loading more cases
	 *
	 * Handles AJAX requests to load additional cases for pagination.
	 * Returns HTML for additional case cards to be inserted into the grid.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function ajax_load_more_cases(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
			return;
		}

		// Get parameters from AJAX request
		$start_page = absint( $_POST['start_page'] ?? 2 );
		$procedure_ids = sanitize_text_field( $_POST['procedure_ids'] ?? '' );
		$procedure_name = sanitize_text_field( $_POST['procedure_name'] ?? '' );

		try {
			// Get cases data from WordPress posts (since we're using local data now)
			$atts = [
				'columns' => 3,
				'show_details' => true,
				'items_per_page' => get_option( 'brag_book_gallery_items_per_page', '10' ),
			];

			// Calculate offset based on page and items per page
			$items_per_page = absint( $atts['items_per_page'] );
			$offset = ( $start_page - 1 ) * $items_per_page;

			// Query WordPress posts for cases
			$query_args = [
				'post_type' => 'brag_book_cases',
				'post_status' => 'publish',
				'posts_per_page' => $items_per_page,
				'offset' => $offset,
				'meta_query' => [],
			];

			// Add procedure filtering if provided
			if ( ! empty( $procedure_name ) ) {
				$query_args['tax_query'] = [
					[
						'taxonomy' => 'procedures',
						'field' => 'slug',
						'terms' => $procedure_name,
					],
				];
			}

			$cases_query = new \WP_Query( $query_args );
			$html = '';

			if ( $cases_query->have_posts() ) {
				// Get image display mode setting
				$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

				// Check if the current procedure has nudity flag set
				$procedure_has_nudity = self::procedure_has_nudity( $procedure_name );

				while ( $cases_query->have_posts() ) {
					$cases_query->the_post();
					$post_id = get_the_ID();

					// Build case data array from WordPress post
					$case_data = [
						'id' => get_post_meta( $post_id, '_case_api_id', true ),
						'mainImageUrl' => get_post_meta( $post_id, '_case_main_image_url', true ),
						'age' => get_post_meta( $post_id, '_case_age', true ),
						'gender' => get_post_meta( $post_id, '_case_gender', true ),
						'ethnicity' => get_post_meta( $post_id, '_case_ethnicity', true ),
						'seoHeadline' => get_post_meta( $post_id, '_case_seo_headline', true ),
					];

					// Use case data or post title as fallback
					if ( empty( $case_data['seoHeadline'] ) ) {
						$case_data['seoHeadline'] = get_the_title( $post_id );
					}

					// Render case card
					$html .= self::render_case_card(
						$case_data,
						$image_display_mode,
						$procedure_has_nudity,
						$procedure_name
					);
				}

				wp_reset_postdata();

				// Check if there are more pages available
				$total_posts = $cases_query->found_posts;
				$current_loaded = $offset + $cases_query->post_count;
				$has_more = $current_loaded < $total_posts;

				wp_send_json_success( [
					'html' => $html,
					'hasMore' => $has_more,
					'currentPage' => $start_page,
					'totalCases' => $total_posts,
					'loadedCases' => $current_loaded,
				] );

			} else {
				// No more cases found
				wp_send_json_success( [
					'html' => '',
					'hasMore' => false,
					'message' => 'No more cases found',
				] );
			}

		} catch ( \Exception $e ) {
			error_log( 'BRAG Book Gallery Load More Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => 'Failed to load more cases' ] );
		}
	}

	/**
	 * Clear cases cache
	 *
	 * Clears all cached cases data.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
	}
}
