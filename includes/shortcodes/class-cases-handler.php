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
	private const DEFAULT_COLUMNS = 2;

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
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_shortcode( 'brag_book_gallery_cases', [ self::class, 'handle' ] );

		// Add filters to prevent unwanted p/br tags in nested shortcodes
		add_filter( 'the_content', [ $this, 'shortcode_content_filter' ], 7 );
		add_filter( 'widget_text_content', [ $this, 'shortcode_content_filter' ], 7 );

		// Enable shortcodes in term descriptions
		add_filter( 'term_description', 'do_shortcode' );
		add_filter( 'category_description', 'do_shortcode' );
		add_filter( 'tag_description', 'do_shortcode' );

		// Register AJAX handlers for procedure navigation
		add_action( 'wp_ajax_brag_book_get_adjacent_cases', [ self::class, 'ajax_get_adjacent_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_get_adjacent_cases', [ self::class, 'ajax_get_adjacent_cases' ] );

		// Enable shortcodes in all taxonomy descriptions (including custom taxonomies)
		add_action( 'init', [ $this, 'enable_shortcodes_in_taxonomy_descriptions' ] );

		// Hook into wp_enqueue_scripts to check for our shortcode and enqueue assets
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ], 5 );

		// Register AJAX handlers for load more functionality
		add_action( 'wp_ajax_brag_book_gallery_load_more_cases', [ self::class, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_more_cases', [ self::class, 'ajax_load_more_cases' ] );
	}

	/**
	 * Filter content to prevent unwanted p/br tags around our shortcodes
	 *
	 * This method prevents WordPress from adding paragraph and line break tags
	 * around our shortcode content, especially important for nested shortcodes.
	 *
	 * @param string $content The content to filter.
	 *
	 * @return string Filtered content.
	 * @since 3.0.0
	 *
	 */
	public function shortcode_content_filter( string $content ): string {
		// Only process if our shortcode is present
		if ( ! has_shortcode( $content, 'brag_book_gallery_cases' ) ) {
			return $content;
		}

		// Temporarily remove wpautop filter to prevent auto p/br tags
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'widget_text_content', 'wpautop' );

		// Process shortcodes
		$content = do_shortcode( $content );

		// Add wpautop back for other content
		add_filter( 'the_content', 'wpautop' );
		add_filter( 'widget_text_content', 'wpautop' );

		// Clean up any remaining unwanted tags
		$patterns = [
			'/<p>\s*\[brag_book_gallery_cases([^\]]*)\]\s*<\/p>/i' => '[brag_book_gallery_cases$1]',
			'/<p>\s*<\/p>/i'                                       => '',
			'/<br\s*\/?>\s*\[brag_book_gallery_cases/i'            => '[brag_book_gallery_cases',
			'/\]\s*<br\s*\/?>/i'                                   => ']',
		];

		foreach ( $patterns as $pattern => $replacement ) {
			$content = preg_replace( $pattern, $replacement, $content );
		}

		return $content;
	}

	/**
	 * Enable shortcodes in all taxonomy descriptions
	 *
	 * This method adds do_shortcode filter to all registered taxonomies,
	 * ensuring shortcodes work in custom taxonomy descriptions.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public function enable_shortcodes_in_taxonomy_descriptions(): void {
		// Get all registered taxonomies
		$taxonomies = get_taxonomies( [], 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			// Add shortcode processing to each taxonomy description
			$filter_name = "{$taxonomy->name}_description";
			add_filter( $filter_name, 'do_shortcode' );
		}

		// Also add filters for common ways term descriptions are displayed
		add_filter( 'get_the_archive_description', 'do_shortcode' );
		add_filter( 'wpseo_metadesc', 'do_shortcode' ); // Yoast SEO compatibility
	}

	/**
	 * Check if cases shortcode is present and enqueue assets
	 *
	 * @return void
	 * @since 3.0.0
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
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Cases HTML or error message.
	 * @since 3.0.0
	 *
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

		// Check if we're on a taxonomy archive page
		if ( empty( $filter_procedure ) && is_tax( 'brag_book_procedures' ) ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$filter_procedure = $term->slug;
				if ( WP_DEBUG ) {
					error_log( 'BRAGBook: Cases handler - Auto-detected procedure from taxonomy: ' . $filter_procedure );
				}
			}
		}

		// Debug logging for procedure detection
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: Cases handler - is_tax: ' . ( is_tax( 'brag_book_procedures' ) ? 'YES' : 'NO' ) );
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
			$current_url   = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path_segments = array_filter( explode( '/', $current_url ) );

			// Look for pattern: /gallery/procedure-name/ or /gallery-slug/procedure-name/
			if ( count( $path_segments ) >= 2 ) {
				$gallery_segment   = $path_segments[0];
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
			$gallery_atts                        = $atts;
			$gallery_atts['data_case_id']        = $case_identifier;
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
		$items_per_page = absint( get_option( 'brag_book_gallery_items_per_page', '200' ) );

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
		$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		$sidebar_data = [];
		if ( ! empty( $api_tokens[0] ) && ! empty( $website_property_ids[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0], $website_property_ids[0] );
		}

		// Localize script with necessary data for filters
		Asset_Manager::localize_gallery_script(
			[
				'api_token'           => $atts['api_token'],
				'website_property_id' => $atts['website_property_id'],
			],
			$sidebar_data,
			$cases_data
		);

		// Render cases grid using WordPress posts instead of API data.
		$output = self::render_cases_grid_from_posts( $filter_procedure, $atts );

		return $output;
	}

	/**
	 * Convert WordPress post to case data format
	 *
	 * @param \WP_Post $post The case post.
	 *
	 * @return array|null Case data array or null if invalid.
	 * @since 3.0.0
	 *
	 */
	private static function convert_post_to_case_data( \WP_Post $post ): ?array {
		// Get post meta data
		$case_data = self::get_case_meta_data( $post->ID );

		// Get procedure information
		$procedures = self::get_case_procedures( $post->ID );

		// Build case data array similar to API format
		return [
			'id'               => $case_data['case_id'] ?: $post->ID,
			'patientAge'       => $case_data['patient_age'],
			'patientGender'    => $case_data['patient_gender'],
			'patientEthnicity' => $case_data['patient_ethnicity'] ?: '',
			'patientHeight'    => $case_data['patient_height'] ?: '',
			'patientWeight'    => $case_data['patient_weight'] ?: '',
			'procedureDate'    => $case_data['procedure_date'],
			'caseNotes'        => $case_data['case_notes'],
			'beforeImage'      => $case_data['before_image'],
			'afterImage'       => $case_data['after_image'],
			'procedureIds'     => wp_list_pluck( $procedures, 'procedure_id' ),
			'procedures'       => $procedures,
			'seoSuffixUrl'     => $post->post_name,
			'permalink'        => get_permalink( $post->ID ),
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
	 * @param array $raw_atts Raw shortcode attributes from user input.
	 *
	 * @return array Validated and sanitized attribute array.
	 * @since 3.0.0
	 *
	 */
	private static function validate_and_sanitize_shortcode_attributes( array $raw_atts ): array {
		// Get items per page from settings
		$items_per_page = absint( get_option( 'brag_book_gallery_items_per_page', self::DEFAULT_CASES_LIMIT ) );

		// Get columns from settings
		$default_columns = absint( get_option( 'brag_book_gallery_columns', self::DEFAULT_COLUMNS ) );

		// Define default attributes with proper types
		$defaults = [
			'api_token'           => '',
			'website_property_id' => '',
			'procedure_ids'       => '',
			'limit'               => $items_per_page,
			'page'                => 1,
			'columns'             => $default_columns,
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
	 * @param array $atts Shortcode attributes with potential API configuration.
	 *
	 * @return array Enhanced attributes with API configuration populated.
	 * @since 3.0.0
	 *
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
	 * @param string $option_name WordPress option name to retrieve.
	 * @param string $credential_type Credential type for debugging context.
	 *
	 * @return string Extracted and sanitized credential value.
	 * @since 3.0.0
	 *
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
	 * @param string $filter_procedure Filter procedure slug from query vars.
	 * @param string $procedure_title Procedure title extracted from URL.
	 * @param string $case_suffix Case suffix/identifier from URL.
	 * @param array $atts Shortcode attributes array.
	 *
	 * @return void
	 * @since 3.0.0
	 *
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
	 * @param string $filter_procedure Procedure slug to filter by.
	 * @param array $atts Shortcode attributes.
	 *
	 * @return array Array of procedure IDs.
	 * @since 3.0.0
	 *
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
	 * Render case card for grid display
	 *
	 * Generates HTML for a single case card in the grid.
	 *
	 * @param array $case Case data.
	 * @param string $image_display_mode Image display mode.
	 * @param bool $procedure_nudity Whether procedure has nudity.
	 * @param string $procedure_context Procedure context from filter.
	 * @param string $current_procedure_id Current procedure ID for referrer tracking.
	 * @param string $current_term_id Current term ID for referrer tracking.
	 *
	 * @return string HTML output.
	 * @since 3.0.0
	 *
	 */
	public static function render_case_card(
		array $case,
		string $image_display_mode,
		bool $procedure_nudity = false,
		string $procedure_context = '',
		string $current_procedure_id = '',
		string $current_term_id = ''
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
			'<article class="brag-book-gallery-case-card" %s data-case-id="%s" data-procedure-case-id="%s" data-procedure-ids="%s" data-current-procedure-id="%s" data-current-term-id="%s">',
			$data_attrs,
			esc_attr( $case_info['case_id'] ),
			esc_attr( $case['id'] ?? $case_info['case_id'] ), // The small ID from API for view tracking
			esc_attr( $procedure_ids ),
			esc_attr( $current_procedure_id ),
			esc_attr( $current_term_id )
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
				// Show single image (post-processed preferred, fallback to after, then before).
				$image_url = $first_photo['postProcessedImageLocation'] ?? $first_photo['afterPhoto'] ?? $first_photo['beforePhoto'] ?? '';
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

		// Add case title or doctor name based on settings.
		$show_doctor = (bool) get_option( 'brag_book_gallery_show_doctor', false );

		if ( $show_doctor ) {
			// Show doctor info with profile photo from taxonomy if available
			$post_id = get_the_ID();
			if ( $post_id ) {
				$html .= self::render_doctor_card_info( $post_id, $case_info['seo_headline'] ?? '' );
			} else {
				// Fallback to case data if no post context
				$doctor_name = $case['doctorName'] ?? $case['doctor_name'] ?? '';
				if ( ! empty( $doctor_name ) ) {
					$html .= '<h3 class="case-title doctor-name">' . esc_html( $doctor_name ) . '</h3>';
				}
			}
		} elseif ( ! empty( $case_info['seo_headline'] ) ) {
			// Show case title as before
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
	 * @param array $case Case data.
	 *
	 * @return string Data attributes HTML.
	 * @since 3.0.0
	 *
	 */
	private static function prepare_case_data_attributes( array $case ): string {
		$attrs = 'data-id="' . get_the_ID() . '"';
		$attrs .= 'data-card="true"';

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
			$attrs        .= ' data-height="' . esc_attr( $height_value ) . '"';
			$attrs        .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
			$attrs        .= ' data-height-full="' . esc_attr( $height_value . $height_unit ) . '"';
		}

		// Add weight with unit.
		if ( ! empty( $case['weight'] ) ) {
			$weight_value = $case['weight'];
			$weight_unit  = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
			$attrs        .= ' data-weight="' . esc_attr( $weight_value ) . '"';
			$attrs        .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
			$attrs        .= ' data-weight-full="' . esc_attr( $weight_value . $weight_unit ) . '"';
		}

		// Add procedure details as data attributes for filtering
		// Try to get from post meta if we have the post ID
		if ( ! empty( $attrs ) && strpos( $attrs, 'data-id="' ) !== false ) {
			preg_match( '/data-id="(\d+)"/', $attrs, $matches );
			if ( ! empty( $matches[1] ) ) {
				$post_id = intval( $matches[1] );
				$procedure_details_json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );
				if ( ! empty( $procedure_details_json ) ) {
					$procedure_details = json_decode( $procedure_details_json, true );
					if ( is_array( $procedure_details ) ) {
						foreach ( $procedure_details as $procedure_id => $details ) {
							if ( is_array( $details ) ) {
								foreach ( $details as $detail_label => $detail_value ) {
									// Create a sanitized attribute name from the label (use dashes for dataset compatibility)
									$attr_name = sanitize_title_with_dashes( $detail_label );

									// Handle array values (e.g., ["Upper", "Lower"])
									if ( is_array( $detail_value ) ) {
										$attr_value = implode( ',', array_map( function( $val ) {
											return strtolower( (string) $val );
										}, $detail_value ) );
									} else {
										$attr_value = strtolower( (string) $detail_value );
									}

									$attrs .= ' data-procedure-detail-' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
								}
							}
						}
					}
				}
			}
		}

		return $attrs;
	}

	/**
	 * Prepare case data attributes from WordPress post meta
	 *
	 * Generates data attributes for case filtering using post meta values
	 * stored in WordPress database according to mapping.md format.
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 *
	 * @return string HTML data attributes string.
	 * @since 3.0.0
	 *
	 */
	private static function prepare_case_data_attributes_from_post_meta( $post ): string {
		if ( is_numeric( $post ) ) {
			$post_id = (int) $post;
		} elseif ( $post instanceof \WP_Post ) {
			$post_id = $post->ID;
		} else {
			return 'data-card="true"';
		}

		$attrs = 'data-card="true"';

		// Add age using new meta key format
		$age = get_post_meta( $post_id, 'brag_book_gallery_patient_age', true );
		if ( ! empty( $age ) ) {
			$attrs .= ' data-age="' . esc_attr( $age ) . '"';
		}

		// Add gender using new meta key format
		$gender = get_post_meta( $post_id, 'brag_book_gallery_patient_gender', true );
		if ( ! empty( $gender ) ) {
			$attrs .= ' data-gender="' . esc_attr( strtolower( $gender ) ) . '"';
		}

		// Add ethnicity using new meta key format
		$ethnicity = get_post_meta( $post_id, 'brag_book_gallery_ethnicity', true );
		if ( ! empty( $ethnicity ) ) {
			$attrs .= ' data-ethnicity="' . esc_attr( strtolower( $ethnicity ) ) . '"';
		}

		// Add height with unit using new meta key format
		$height      = get_post_meta( $post_id, 'brag_book_gallery_height', true );
		$height_unit = get_post_meta( $post_id, 'brag_book_gallery_height_unit', true );
		if ( ! empty( $height ) ) {
			$attrs .= ' data-height="' . esc_attr( $height ) . '"';
			if ( ! empty( $height_unit ) ) {
				$attrs .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
				$attrs .= ' data-height-full="' . esc_attr( $height . $height_unit ) . '"';
			}
		}

		// Add weight with unit using new meta key format
		$weight      = get_post_meta( $post_id, 'brag_book_gallery_weight', true );
		$weight_unit = get_post_meta( $post_id, 'brag_book_gallery_weight_unit', true );
		if ( ! empty( $weight ) ) {
			$attrs .= ' data-weight="' . esc_attr( $weight ) . '"';
			if ( ! empty( $weight_unit ) ) {
				$attrs .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
				$attrs .= ' data-weight-full="' . esc_attr( $weight . $weight_unit ) . '"';
			}
		}

		// Add procedure details as data attributes for filtering
		$procedure_details_json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );
		if ( ! empty( $procedure_details_json ) ) {
			$procedure_details = json_decode( $procedure_details_json, true );
			if ( is_array( $procedure_details ) ) {
				foreach ( $procedure_details as $procedure_id => $details ) {
					if ( is_array( $details ) ) {
						foreach ( $details as $detail_label => $detail_value ) {
							// Create a sanitized attribute name from the label (use dashes for dataset compatibility)
							$attr_name = sanitize_title_with_dashes( $detail_label );

							// Handle array values (e.g., ["Upper", "Lower"])
							if ( is_array( $detail_value ) ) {
								$attr_value = implode( ',', array_map( function( $val ) {
									return strtolower( (string) $val );
								}, $detail_value ) );
							} else {
								$attr_value = strtolower( (string) $detail_value );
							}

							$attrs .= ' data-procedure-detail-' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
						}
					}
				}
			}
		}

		return $attrs;
	}

	/**
	 * Render case card from WordPress post
	 *
	 * Generates case card HTML using WordPress post data and meta fields
	 * instead of API data. Uses the new meta key format from mapping.md.
	 *
	 * @param \WP_Post $post WordPress post object.
	 * @param string $image_display_mode Image display mode (single/before_after).
	 * @param bool $procedure_nudity Whether procedure has nudity warning.
	 * @param string $procedure_context Procedure context for URL generation.
	 *
	 * @return string Case card HTML.
	 * @since 3.0.0
	 *
	 */
	public static function render_case_card_from_post(
		\WP_Post $post,
		string $image_display_mode = 'single',
		bool $procedure_nudity = false,
		string $procedure_context = ''
	): string {
		$html = '';

		// Prepare data attributes from post meta.
		$data_attrs = self::prepare_case_data_attributes_from_post_meta( $post );

		// Get case ID from post meta (API ID).
		$case_id = get_post_meta( $post->ID, 'brag_book_gallery_case_id', true );
		if ( empty( $case_id ) ) {
			$case_id = $post->ID; // Use WordPress post ID as fallback
		}

		// Get procedure IDs from post meta.
		$procedure_ids = get_post_meta( $post->ID, 'brag_book_gallery_procedure_ids', true );

		// Get procedure case ID (small API ID) for view tracking
		$procedure_case_id = get_post_meta( $post->ID, 'brag_book_gallery_procedure_case_id', true );
		if ( empty( $procedure_case_id ) ) {
			$procedure_case_id = get_post_meta( $post->ID, 'brag_book_gallery_original_case_id', true );
		}

		// Get SEO suffix URL from post meta.
		$seo_suffix_url = get_post_meta( $post->ID, 'brag_book_gallery_seo_suffix_url', true );

		if ( empty( $seo_suffix_url ) ) {
			$seo_suffix_url = $post->post_name; // Use post slug as fallback
		}

		$html .= sprintf(
			'<article class="brag-book-gallery-case-card" %s data-case-id="%s" data-procedure-case-id="%s" data-procedure-ids="%s">',
			$data_attrs,
			esc_attr( $case_id ),
			esc_attr( $procedure_case_id ), // The small API ID for view tracking
			esc_attr( $procedure_ids )
		);

		// Build case URL.
		$gallery_slug = self::get_gallery_page_slug();
		if ( ! empty( $procedure_context ) ) {
			$case_url = sprintf( '/%s/%s/%s/', $gallery_slug, $procedure_context, $seo_suffix_url );
		} else {
			$case_url = sprintf( '/%s/case/%s/', $gallery_slug, $seo_suffix_url );
		}

		// Add case content.
		$html .= '<a href="' . esc_url( $case_url ) . '" class="case-link">';

		// Get image URLs from post meta.
		$before_urls         = get_post_meta( $post->ID, 'brag_book_gallery_case_before_url', true );
		$after_urls1         = get_post_meta( $post->ID, 'brag_book_gallery_case_after_url1', true );
		$post_processed_urls = get_post_meta( $post->ID, 'brag_book_gallery_case_post_processed_url', true );

		if ( empty( $before_urls ) && empty( $after_urls1 ) && empty( $post_processed_urls ) ) {
			$image_sets = get_post_meta( $post->ID, 'brag_book_gallery_image_url_sets', true );
			if ( ! empty( $image_sets ) && is_array( $image_sets ) ) {
				$first_set           = reset( $image_sets );
				$before_urls         = $first_set['before_url'] ?? '';
				$after_urls1         = $first_set['after_url1'] ?? '';
				$post_processed_urls = $first_set['post_processed_url'] ?? '';
			}
		}

		// Parse URLs (handle semicolon-separated format).
		$before_url    = '';
		$after_url     = '';
		$processed_url = '';

		if ( ! empty( $post_processed_urls ) ) {
			$urls          = explode( "\n", $post_processed_urls );
			$processed_url = ! empty( $urls[0] ) ? rtrim( $urls[0], ';' ) : '';
		}

		if ( ! empty( $before_urls ) ) {
			$urls       = explode( "\n", $before_urls );
			$before_url = ! empty( $urls[0] ) ? rtrim( $urls[0], ';' ) : '';
		}

		if ( ! empty( $after_urls1 ) ) {
			$urls      = explode( "\n", $after_urls1 );
			$after_url = ! empty( $urls[0] ) ? rtrim( $urls[0], ';' ) : '';
		}

		// Determine which image to show.
		$main_image_url = $processed_url ?: $after_url ?: $before_url;

		if ( 'before_after' === $image_display_mode && ! empty( $before_url ) && ! empty( $after_url ) ) {
			// Show both before and after images.
			$html .= '<div class="brag-book-gallery-case-images before-after">';
			$html .= sprintf(
				'<img src="%s" alt="%s" class="before-image" />',
				esc_url( $before_url ),
				esc_attr__( 'Before', 'brag-book-gallery' )
			);
			$html .= sprintf(
				'<img src="%s" alt="%s" class="after-image" />',
				esc_url( $after_url ),
				esc_attr__( 'After', 'brag-book-gallery' )
			);
			$html .= '</div>';
		} elseif ( ! empty( $main_image_url ) ) {
			// Show single image.
			$html .= '<div class="brag-book-gallery-case-images single-image">';
			$html .= sprintf(
				'<img src="%s" alt="%s" class="case-image" />',
				esc_url( $main_image_url ),
				esc_attr( sprintf( __( 'Case %s', 'brag-book-gallery' ), $case_id ) )
			);
			$html .= '</div>';
		} else {
			// No image available.
			$html .= '<div class="brag-book-gallery-case-images no-image">';
			$html .= '<div class="placeholder-image">' . esc_html__( 'No image available', 'brag-book-gallery' ) . '</div>';
			$html .= '</div>';
		}

		// Add case title or doctor name based on settings.
		$show_doctor = (bool) get_option( 'brag_book_gallery_show_doctor', false );

		if ( $show_doctor ) {
			// Show doctor info with profile photo from taxonomy
			$seo_headline = get_post_meta( $post->ID, 'brag_book_gallery_seo_headline', true );
			$html .= self::render_doctor_card_info( $post->ID, $seo_headline ?: '' );
		} else {
			// Show case SEO headline if available
			$seo_headline = get_post_meta( $post->ID, 'brag_book_gallery_seo_headline', true );
			if ( ! empty( $seo_headline ) ) {
				$html .= '<h3 class="case-title">' . esc_html( $seo_headline ) . '</h3>';
			}
		}

		$html .= '</a>'; // Close case link

		// Add nudity warning if needed.
		if ( $procedure_nudity ) {
			$html .= '<div class="brag-book-gallery-nudity-warning" style="display: none;">';
			$html .= '<div class="brag-book-gallery-nudity-warning-content">';
			$html .= '<h3>' . esc_html__( 'Content Advisory', 'brag-book-gallery' ) . '</h3>';
			$html .= '<p>' . esc_html__( 'This content may contain sensitive material. Please proceed with discretion.', 'brag-book-gallery' ) . '</p>';
			$html .= '<div class="brag-book-gallery-nudity-warning-actions">';
			$html .= '<button class="brag-book-gallery-button brag-book-gallery-button--secondary" data-action="nudity-cancel">' . esc_html__( 'Cancel', 'brag-book-gallery' ) . '</button>';
			$html .= '<button class="brag-book-gallery-button brag-book-gallery-button--primary" data-action="nudity-proceed">' . esc_html__( 'Proceed', 'brag-book-gallery' ) . '</button>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</article>';

		return $html;
	}

	/**
	 * Renders a grid of cases from WordPress posts with filtering and pagination support.
	 *
	 * This method generates a complete gallery interface including:
	 * - Filter controls for procedures
	 * - Grid layout selector (2 or 3 columns)
	 * - Masonry-style case cards
	 * - Load more functionality with infinite scroll support
	 *
	 * @param string $filter_procedure Optional. Slug of the procedure taxonomy to filter by.
	 *                                 If provided, only cases with this procedure will be shown.
	 * @param array $atts Optional. Additional attributes for customization.
	 *                                 - 'image_display_mode': How to display images ('single' or other modes)
	 *
	 * @return string HTML output for the complete cases gallery interface.
	 * @since 1.0.0
	 *
	 */
	public static function render_cases_grid_from_posts( string $filter_procedure = '', array $atts = [] ): string {
		// Get cases from WordPress posts based on filter criteria
		$query = self::get_cases_query( $filter_procedure );

		// Return early with "no cases" message if query has no results
		if ( ! $query->have_posts() ) {
			return self::render_no_cases_found();
		}

		// Extract data from query and options
		$posts              = $query->posts;
		$total_cases        = $query->found_posts;
		$items_per_page     = absint( get_option( 'brag_book_gallery_items_per_page', '200' ) );
		$default_columns    = absint( get_option( 'brag_book_gallery_columns', self::DEFAULT_COLUMNS ) );
		$image_display_mode = $atts['image_display_mode'] ?? 'single';

		// Build the complete gallery HTML structure
		$output = sprintf(
			'<div class="brag-book-gallery-wrapper" role="application" aria-label="%s">
            <div class="brag-book-gallery-main-content" role="region" aria-label="%s" id="gallery-content">
                %s
                <div class="brag-book-gallery-cases-container">
                    <div class="brag-book-gallery-case-grid masonry-layout" data-columns="%d">
                        %s
                    </div>
                    %s
                </div>
            </div>
        </div>',
			esc_attr__( 'Cases Gallery', 'brag-book-gallery' ),
			esc_attr__( 'Gallery content', 'brag-book-gallery' ),
			self::render_controls( $default_columns ),
			$default_columns,
			self::render_case_cards( $posts, $image_display_mode, $filter_procedure ),
			self::render_load_more_button( $total_cases, $items_per_page, $filter_procedure )
		);

		// Reset WordPress query globals
		wp_reset_postdata();

		return $output;
	}

	/**
	 * Builds and returns a WP_Query object for fetching case posts.
	 *
	 * Constructs a query with appropriate filters, sorting, and pagination settings.
	 * When a procedure filter is provided, adds taxonomy query and custom sorting by case order
	 * from the procedure taxonomy term meta.
	 *
	 * @param string $filter_procedure Optional. Procedure slug to filter cases by.
	 *
	 * @return \WP_Query WordPress query object containing the case posts.
	 * @since 1.0.0
	 *
	 */
	private static function get_cases_query( string $filter_procedure = '' ): \WP_Query {
		// Debug logging at entry point
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BRAGBook: get_cases_query() called with filter_procedure: "' . $filter_procedure . '"' );
			error_log( 'BRAGBook: filter_procedure empty check: ' . ( empty( $filter_procedure ) ? 'EMPTY' : 'NOT EMPTY' ) );
		}

		// Base query arguments for fetching published case posts
		$query_args = [
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'post_status'    => 'publish',
			'posts_per_page' => absint( get_option( 'brag_book_gallery_items_per_page', '200' ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [],
			'tax_query'      => [],
		];

		// Add procedure-specific filtering and sorting if a filter is specified
		if ( ! empty( $filter_procedure ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook: Filter procedure is not empty, proceeding with term lookup' );
			}
			// Get the procedure term to access its case order
			$procedure_term = get_term_by( 'slug', $filter_procedure, Taxonomies::TAXONOMY_PROCEDURES );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook: Taxonomy constant: ' . Taxonomies::TAXONOMY_PROCEDURES );
				error_log( 'BRAGBook: Term lookup result: ' . ( $procedure_term ? 'FOUND (ID: ' . $procedure_term->term_id . ')' : 'NOT FOUND' ) );
				if ( is_wp_error( $procedure_term ) ) {
					error_log( 'BRAGBook: Term lookup error: ' . $procedure_term->get_error_message() );
				}
			}

			if ( $procedure_term && ! is_wp_error( $procedure_term ) ) {
				// Get the case order list from term meta
				$case_order_list = get_term_meta( $procedure_term->term_id, 'brag_book_gallery_case_order_list', true );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook: Case order list for ' . $filter_procedure . ' (term ID: ' . $procedure_term->term_id . '): ' . print_r( $case_order_list, true ) );
					error_log( 'BRAGBook: Case order list type: ' . gettype( $case_order_list ) . ', is_array: ' . ( is_array( $case_order_list ) ? 'YES' : 'NO' ) . ', count: ' . ( is_array( $case_order_list ) ? count( $case_order_list ) : 'N/A' ) );
				}

				// If we have a case order list with WordPress IDs, use post__in for ordering
				if ( is_array( $case_order_list ) && ! empty( $case_order_list ) ) {
					// Extract WordPress post IDs from the case order list
					$post_ids = [];
					foreach ( $case_order_list as $case_data ) {
						if ( is_array( $case_data ) && ! empty( $case_data['wp_id'] ) ) {
							$post_ids[] = $case_data['wp_id'];
						}
					}

					if ( ! empty( $post_ids ) ) {
						// Use post__in to get only these posts in this exact order
						$query_args['post__in'] = $post_ids;
						$query_args['orderby']  = 'post__in';
						unset( $query_args['order'] );

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'BRAGBook: Using post__in with ' . count( $post_ids ) . ' WordPress IDs for ordering' );
						}
					} else {
						// Fallback to taxonomy filter if no valid post IDs found
						$query_args['tax_query'][] = [
							'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
							'field'    => 'slug',
							'terms'    => $filter_procedure,
						];
					}
				} else {
					// Fallback to taxonomy filter if no case order list
					$query_args['tax_query'][] = [
						'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
						'field'    => 'slug',
						'terms'    => $filter_procedure,
					];
				}
			}
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * Get WordPress post IDs from case order list
	 *
	 * Converts an array of case API IDs to WordPress post IDs while maintaining order.
	 *
	 * @param array $case_order_list Array of case API IDs in order.
	 *
	 * @return array Array of WordPress post IDs in the same order.
	 * @since 3.3.0
	 */
	/**
	 * Sort posts by case order list
	 *
	 * Sorts an array of WP_Post objects based on the case order list from taxonomy term meta.
	 * The case order list contains arrays with 'wp_id' and 'api_id' keys.
	 *
	 * @param array $posts Array of WP_Post objects.
	 * @param array $case_order_list Array of case data with 'wp_id' and 'api_id' keys.
	 *
	 * @return array Sorted array of WP_Post objects.
	 * @since 3.3.0
	 */
	private static function sort_posts_by_case_order( array $posts, array $case_order_list ): array {
		// Create a map of post ID to post object for quick lookup
		$post_map = [];
		foreach ( $posts as $post ) {
			$post_map[ $post->ID ] = $post;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BRAGBook: Total posts to sort: ' . count( $posts ) );
			error_log( 'BRAGBook: Case order list count: ' . count( $case_order_list ) );
		}

		// Build ordered array based on case order list (using WordPress post IDs)
		$ordered_posts = [];
		$used_post_ids = [];

		foreach ( $case_order_list as $case_data ) {
			// Support both new format (array with wp_id/api_id) and legacy format (just API IDs)
			$wp_id = is_array( $case_data ) ? ( $case_data['wp_id'] ?? null ) : null;

			if ( $wp_id && isset( $post_map[ $wp_id ] ) ) {
				$ordered_posts[] = $post_map[ $wp_id ];
				$used_post_ids[] = $wp_id;
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BRAGBook: Sorted ' . count( $ordered_posts ) . ' posts by case order' );
		}

		// Add any remaining posts that weren't in the case order list
		foreach ( $posts as $post ) {
			if ( ! in_array( $post->ID, $used_post_ids, true ) ) {
				$ordered_posts[] = $post;
			}
		}

		return $ordered_posts;
	}

	/**
	 * Renders the "no cases found" message with proper wrapper structure.
	 *
	 * @return string HTML output for the no cases found message.
	 * @since 1.0.0
	 *
	 */
	private static function render_no_cases_found(): string {
		return sprintf(
			'<div class="brag-book-gallery-wrapper">
            <div class="brag-book-gallery-main-content">
                <p>%s</p>
            </div>
        </div>',
			esc_html__( 'No cases found.', 'brag-book-gallery' )
		);
	}

	/**
	 * Renders the complete controls section including filters and grid selector.
	 *
	 * Creates the top control bar with:
	 * - Left side: Filter dropdown and active filters display
	 * - Right side: Grid layout selector buttons
	 *
	 * @param int $columns Currently active number of columns (2 or 3).
	 *
	 * @return string HTML output for the controls section.
	 * @since 1.0.0
	 *
	 */
	private static function render_controls( int $columns ): string {
		return sprintf(
			'<div class="brag-book-gallery-controls">
            <div class="brag-book-gallery-controls-left">
                %s
                <div class="brag-book-gallery-active-filters" style="display: none;"></div>
            </div>
            %s
        </div>',
			self::render_filter_dropdown(),
			self::render_grid_selector( $columns )
		);
	}

	/**
	 * Renders the collapsible filter dropdown panel.
	 *
	 * Creates a details/summary element containing:
	 * - Filter icon and label
	 * - Expandable panel with filter options (populated by JavaScript)
	 * - Apply and Clear action buttons
	 *
	 * @return string HTML output for the filter dropdown.
	 * @since 1.0.0
	 *
	 */
	private static function render_filter_dropdown(): string {
		// SVG icon for the filter button (Material Design filter list icon)
		$filter_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
			<path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"></path>
		</svg>';

		return sprintf(
			'<details class="brag-book-gallery-filter-dropdown" id="procedure-filters-details">
				<summary class="brag-book-gallery-filter-dropdown__toggle">
					%s
					<span>%s</span>
				</summary>
				<div class="brag-book-gallery-filter-dropdown__panel">
					<div class="brag-book-gallery-filter-content">
						<div class="brag-book-gallery-filter-section">
							<div id="brag-book-gallery-filters">
								<!-- Filter options will be populated by JavaScript -->
							</div>
						</div>
					</div>
					<div class="brag-book-gallery-filter-actions">
						<button class="brag-book-gallery-button brag-book-gallery-button--apply" onclick="applyProcedureFilters()">
							%s
						</button>
						<button class="brag-book-gallery-button brag-book-gallery-button--clear" onclick="clearProcedureFilters()">
							%s
						</button>
					</div>
				</div>
			</details>',
			$filter_icon,
			esc_html__( 'Filters', 'brag-book-gallery' ),
			esc_html__( 'Apply Filters', 'brag-book-gallery' ),
			esc_html__( 'Clear All', 'brag-book-gallery' )
		);
	}

	/**
	 * Renders the grid layout selector controls.
	 *
	 * Creates buttons to switch between 2-column and 3-column grid layouts.
	 *
	 * @param int $columns Currently active number of columns (2 or 3).
	 *
	 * @return string HTML output for the grid selector.
	 * @since 1.0.0
	 *
	 */
	private static function render_grid_selector( int $columns ): string {
		return sprintf(
			'<div class="brag-book-gallery-grid-selector">
				<span class="brag-book-gallery-grid-label">%s</span>
				<div class="brag-book-gallery-grid-buttons">
					%s
					%s
				</div>
			</div>',
			esc_html__( 'View:', 'brag-book-gallery' ),
			self::render_grid_button( 2, $columns ),
			self::render_grid_button( 3, $columns )
		);
	}

	/**
	 * Renders an individual grid layout button with icon and accessibility features.
	 *
	 * Creates a button with:
	 * - SVG icon representing the grid layout
	 * - Active state styling when selected
	 * - ARIA labels for screen readers
	 * - Click handler to update layout
	 *
	 * @param int $num_columns Number of columns this button represents (2 or 3).
	 * @param int $active_columns Currently active number of columns for comparison.
	 *
	 * @return string HTML output for the grid button.
	 * @since 1.0.0
	 *
	 */
	private static function render_grid_button( int $num_columns, int $active_columns ): string {
		// Add 'active' class if this button represents the current layout
		$is_active = $num_columns === $active_columns ? ' active' : '';

		// SVG paths for grid icons
		// 2-column icon: 2x2 grid of squares
		// 3-column icon: 3x3 grid of smaller squares
		$svg_icons = [
			2 => '<rect x="1" y="1" width="6" height="6"></rect><rect x="9" y="1" width="6" height="6"></rect>
              <rect x="1" y="9" width="6" height="6"></rect><rect x="9" y="9" width="6" height="6"></rect>',
			3 => '<rect x="1" y="1" width="4" height="4"></rect><rect x="6" y="1" width="4" height="4"></rect><rect x="11" y="1" width="4" height="4"></rect>
              <rect x="1" y="6" width="4" height="4"></rect><rect x="6" y="6" width="4" height="4"></rect><rect x="11" y="6" width="4" height="4"></rect>
              <rect x="1" y="11" width="4" height="4"></rect><rect x="6" y="11" width="4" height="4"></rect><rect x="11" y="11" width="4" height="4"></rect>'
		];

		return sprintf(
			'<button class="brag-book-gallery-grid-btn%s" data-columns="%d" onclick="updateGridLayout(%d)" aria-label="%s">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                %s
            </svg>
            <span class="sr-only">%s</span>
        </button>',
			$is_active,
			$num_columns,
			$num_columns,
			sprintf( esc_attr__( 'View in %d columns', 'brag-book-gallery' ), $num_columns ),
			$svg_icons[ $num_columns ],
			sprintf( esc_html__( '%d Columns', 'brag-book-gallery' ), $num_columns )
		);
	}

	/**
	 * Renders all case cards by iterating through the posts array.
	 *
	 * Delegates the rendering of individual cards to the render_case_card_from_post method.
	 *
	 * @param array $posts Array of WP_Post objects representing cases.
	 * @param string $image_display_mode How images should be displayed in cards.
	 * @param string $filter_procedure Current procedure filter for context.
	 *
	 * @return string HTML output for all case cards concatenated.
	 * @since 1.0.0
	 *
	 */
	private static function render_case_cards( array $posts, string $image_display_mode, string $filter_procedure ): string {
		$output = '';

		// Iterate through each post and render its card
		foreach ( $posts as $post ) {
			$output .= self::render_case_card_from_post( $post, $image_display_mode, false, $filter_procedure );
		}

		return $output;
	}

	/**
	 * Renders the "Load More" button for pagination.
	 *
	 * Creates a button that:
	 * - Only appears when there are more cases than the initial page size
	 * - Can be hidden for infinite scroll mode
	 * - Contains data attributes for AJAX loading
	 * - Tracks procedure filters for consistent loading
	 *
	 * @param int $total_cases Total number of cases in the query.
	 * @param int $items_per_page Number of items shown per page.
	 * @param string $filter_procedure Current procedure filter to maintain during pagination.
	 *
	 * @return string HTML output for the load more button, or empty string if not needed.
	 * @since 1.0.0
	 *
	 */
	private static function render_load_more_button( int $total_cases, int $items_per_page, string $filter_procedure ): string {
		// Don't show load more button if all cases fit on first page
		if ( $total_cases <= $items_per_page ) {
			return '';
		}

		// Get procedure term ID for the filter if specified
		$procedure_ids = '';
		if ( ! empty( $filter_procedure ) ) {
			$procedure_term = get_term_by( 'slug', $filter_procedure, Taxonomies::TAXONOMY_PROCEDURES );
			if ( $procedure_term ) {
				$procedure_ids = $procedure_term->term_id;
			}
		}

		// Hide button if infinite scroll is enabled (JavaScript will handle loading)
		$infinite_scroll = get_option( 'brag_book_gallery_infinite_scroll', 'no' );
		$display_style   = ( $infinite_scroll === 'yes' ) ? ' style="display: none;"' : '';

		return sprintf(
			'<div class="brag-book-gallery-load-more-container">
            <button class="brag-book-gallery-button brag-book-gallery-button--load-more"
                data-action="load-more"
                data-start-page="2"
                data-procedure-ids="%s"
                data-procedure-name="%s"
                onclick="loadMoreCasesFromCache(this)"%s>
                %s
            </button>
        </div>',
			esc_attr( $procedure_ids ),
			esc_attr( $filter_procedure ),
			$display_style,
			esc_html__( 'Load More', 'brag-book-gallery' )
		);
	}

	/**
	 * Get gallery page slug with legacy array format handling
	 *
	 * @param string $default Default value if option is not set
	 *
	 * @return string Gallery page slug
	 * @since 3.0.0
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
	 * @param array $case Case data.
	 *
	 * @return array Case information array.
	 * @since 3.0.0
	 *
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
	 * @param array $case_info Case information.
	 * @param string $procedure_context Procedure context.
	 * @param array $case Full case data.
	 *
	 * @return string Case URL.
	 * @since 3.0.0
	 *
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
	 * @param array $case Complete case data array with optional procedures information.
	 *
	 * @return string Sanitized procedure slug for URL generation.
	 * @since 3.0.0
	 *
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
	 * @param array $case Case data array with optional procedures.
	 *
	 * @return string Sanitized procedure slug or default 'case'.
	 * @since 3.0.0
	 *
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
	 * @return string HTML output.
	 * @since 3.0.0
	 *
	 */
	private static function render_nudity_warning(): string {
		ob_start();
		?>
		<div class="brag-book-gallery-nudity-warning">
			<div class="brag-book-gallery-nudity-warning-content">
				<p class="brag-book-gallery-nudity-warning-title">
					<?php esc_html_e( 'Nudity Warning', 'brag-book-gallery' ); ?>
				</p>
				<p class="brag-book-gallery-nudity-warning-caption">
					<?php esc_html_e( 'Click to proceed if you wish to view.', 'brag-book-gallery' ); ?>
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
	 * Check if the current procedure being viewed has nudity flag set to true.
	 *
	 * Uses the sidebar data to determine if a procedure has nudity enabled.
	 * This ensures that server-rendered case cards show nudity warnings when appropriate.
	 *
	 * @param string $filter_procedure Current procedure being viewed.
	 *
	 * @return bool True if procedure has nudity flag set.
	 * @since 3.0.0
	 *
	 */
	private static function procedure_has_nudity( string $filter_procedure ): bool {
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: procedure_has_nudity called with filter_procedure: ' . $filter_procedure );
		}

		// Get sidebar data to check procedure nudity
		$api_tokens = get_option( 'brag_book_gallery_api_tokens', [] );
		if ( empty( $api_tokens ) || ! is_array( $api_tokens ) ) {
			if ( WP_DEBUG ) {
				error_log( 'BRAGBook: procedure_has_nudity - No API tokens found' );
			}

			return false;
		}

		$sidebar_data = null;
		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = \BRAGBookGallery\Includes\Extend\Data_Fetcher::get_sidebar_data( $api_tokens[0] );
		}

		if ( empty( $sidebar_data ) || ! is_array( $sidebar_data ) ) {
			if ( WP_DEBUG ) {
				error_log( 'BRAGBook: procedure_has_nudity - No sidebar data found' );
			}

			return false;
		}

		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: procedure_has_nudity - Searching through ' . count( $sidebar_data ) . ' categories' );
		}

		// Search through categories for the procedure and check nudity flag
		foreach ( $sidebar_data as $category ) {
			if ( ! isset( $category['procedures'] ) || ! is_array( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $procedure ) {
				// Check if this is the procedure we're looking for (by slug or name)
				$procedure_slug = $procedure['slug'] ?? '';
				$procedure_name = strtolower( $procedure['name'] ?? '' );
				$filter_lower   = strtolower( $filter_procedure );

				if ( WP_DEBUG ) {
					// Log every procedure to see what we're comparing
					error_log( 'BRAGBook: procedure_has_nudity - Checking procedure: ' . $procedure_slug . ' (has_nudity: ' . ( ! empty( $procedure['has_nudity'] ) ? 'true' : 'false' ) . ', nudity: ' . ( ! empty( $procedure['nudity'] ) ? 'true' : 'false' ) . ')' );
				}

				if ( $procedure_slug === $filter_procedure ||
					 $procedure_name === $filter_lower ||
					 sanitize_title( $procedure_name ) === $filter_procedure ) {

					// Check if this procedure has nudity
					$has_nudity = ! empty( $procedure['has_nudity'] ) || ! empty( $procedure['nudity'] );

					if ( WP_DEBUG ) {
						error_log( 'BRAGBook: procedure_has_nudity - MATCH FOUND for procedure: ' . $filter_procedure . ' - has_nudity: ' . ( $has_nudity ? 'true' : 'false' ) );
					}

					return $has_nudity;
				}
			}
		}

		if ( WP_DEBUG ) {
			error_log( 'BRAGBook: procedure_has_nudity - No match found for procedure: ' . $filter_procedure );
		}

		return false;
	}

	/**
	 * Get case meta data with missing data logging
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array Case meta data with defaults.
	 * @since 3.0.0
	 */
	private static function get_case_meta_data( int $post_id ): array {
		// Meta field mapping: internal_key => [new_meta_key, legacy_meta_key]
		$meta_fields = [
			'case_id'           => [ 'brag_book_gallery_case_id', '' ],
			'patient_age'       => [ 'brag_book_gallery_patient_age', '' ],
			'patient_gender'    => [ 'brag_book_gallery_patient_gender', '' ],
			'patient_ethnicity' => [ 'brag_book_gallery_ethnicity', '' ],
			'patient_height'    => [ 'brag_book_gallery_height', '' ],
			'patient_weight'    => [ 'brag_book_gallery_weight', '' ],
			'procedure_date'    => [ 'brag_book_gallery_procedure_date', '' ],
			'case_notes'        => [ 'brag_book_gallery_notes', '' ],
			'before_image'      => [ 'brag_book_gallery_case_before_url', '' ],
			'after_image'       => [ 'brag_book_gallery_case_after_url1', '' ],
		];

		$case_data = [];
		foreach ( $meta_fields as $field => $meta_keys ) {
			$new_key    = $meta_keys[0];
			$legacy_key = $meta_keys[1];
			$default    = $meta_keys[2];

			// Try new format first, fall back to legacy format
			$value = get_post_meta( $post_id, $new_key, true );
			if ( empty( $value ) && $value !== '0' ) {
				$value = get_post_meta( $post_id, $legacy_key, true );
			}

			if ( empty( $value ) && $value !== '0' ) {
				self::log_missing_data( $post_id, $new_key );
				$value = $default;
			}
			$case_data[ $field ] = $value;
		}

		return $case_data;
	}

	/**
	 * Get case procedures from taxonomy
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array Array of procedure data.
	 * @since 3.0.0
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
			$procedures[]   = [
				'id'           => $procedure_meta['procedure_id'] ?: $term->term_id,
				'procedure_id' => $procedure_meta['procedure_id'] ?: $term->term_id,
				'name'         => $term->name,
				'slug'         => $term->slug,
				'seoSuffixUrl' => $term->slug,
				'nudity'       => 'true' === $procedure_meta['nudity'],
			];
		}

		return $procedures;
	}

	/**
	 * Get procedure metadata
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return array Procedure meta data with defaults.
	 * @since 3.0.0
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
	 * @param int $post_id The post ID.
	 * @param string $field The missing field name.
	 *
	 * @return void
	 * @since 3.0.0
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
	 * Get first URL from post-processed image URLs field
	 *
	 * Parses the semicolon-separated URLs from the post-processed image URLs
	 * textarea field and returns the first valid URL.
	 *
	 * @param int $post_id WordPress post ID.
	 *
	 * @return string First post-processed image URL or empty string.
	 */
	private static function get_first_post_processed_url( int $post_id ): string {
		$post_processed_urls = get_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', true );

		if ( empty( $post_processed_urls ) ) {
			return '';
		}

		// Split by semicolon and get first URL
		$urls      = explode( ';', $post_processed_urls );
		$first_url = trim( $urls[0] );

		// Validate URL format
		if ( ! empty( $first_url ) && filter_var( $first_url, FILTER_VALIDATE_URL ) ) {
			return $first_url;
		}

		return '';
	}

	/**
	 * Render WordPress post-based case card
	 *
	 * Renders a case card using WordPress post data with the correct HTML structure
	 * matching the API-based case cards.
	 *
	 * @param array $case_data WordPress post-based case data.
	 * @param string $image_display_mode Display mode for images.
	 * @param bool $procedure_nudity Whether procedure has nudity warning.
	 * @param string $procedure_context Context for case display.
	 *
	 * @return string Generated case card HTML.
	 * @since 3.0.0
	 */
	public static function render_wordpress_case_card(
		array $case_data,
		string $image_display_mode = 'single',
		bool $procedure_nudity = false,
		string $procedure_context = '',
		string $current_procedure_id = '',
		string $current_term_id = ''
	): string {
		$case_id    = $case_data['id'] ?: '';
		$post_id    = $case_data['post_id'] ?? '';
		$images     = $case_data['images'] ?? [];
		$procedures = $case_data['procedures'] ?? [];

		// Get API procedure IDs from taxonomy terms
		$procedure_ids = [];
		$assigned_procedure_ids = []; // Track already assigned procedure IDs to avoid duplicates

		if ( $post_id ) {
			$terms = wp_get_post_terms( $post_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$api_procedure_id = get_term_meta( $term->term_id, 'procedure_id', true );
					if ( ! empty( $api_procedure_id ) ) {
						$procedure_ids[] = $api_procedure_id;
						$assigned_procedure_ids[] = $api_procedure_id;
					}
				}
			}

			// Get additional procedures from brag_book_gallery_procedure_ids meta
			$meta_procedure_ids = get_post_meta( $post_id, 'brag_book_gallery_procedure_ids', true );
			if ( ! empty( $meta_procedure_ids ) ) {
				$meta_ids = array_map( 'trim', explode( ',', $meta_procedure_ids ) );

				foreach ( $meta_ids as $meta_id ) {
					// Skip if already assigned via taxonomy
					if ( in_array( $meta_id, $assigned_procedure_ids, true ) ) {
						continue;
					}

					// Look up the term by procedure_id term meta
					$terms_query = get_terms( [
						'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
						'hide_empty' => false,
						'meta_query' => [
							[
								'key'   => 'procedure_id',
								'value' => $meta_id,
							],
						],
					] );

					if ( ! is_wp_error( $terms_query ) && ! empty( $terms_query ) ) {
						$found_term = $terms_query[0];
						// Add to procedures list
						$procedures[] = $found_term;
						$procedure_ids[] = $meta_id;
					}
				}
			}
		}

		// Get case permalink
		$case_url = $post_id ? get_permalink( $post_id ) : '#';

		// Get primary procedure name for display
		// If procedure_context is provided (e.g., on taxonomy pages), use that instead
		if ( ! empty( $procedure_context ) && 'taxonomy' !== $procedure_context ) {
			$primary_procedure = $procedure_context;
		} else {
			$primary_procedure = is_array( $procedures ) && ! empty( $procedures ) ? $procedures[0] : 'Case';
		}

		// Build data attributes
		$data_attrs = array(
			'data-card="true"'
		);
		$case_id   = get_post_meta( $post_id, 'brag_book_gallery_case_id', true );
		$gender    = get_post_meta( $post_id, 'brag_book_gallery_gender', true );
		$age       = get_post_meta( $post_id, 'brag_book_gallery_age', true );
		$ethnicity = get_post_meta( $post_id, 'brag_book_gallery_ethnicity', true );

		if ( ! empty( $case_id ) ) {
			$data_attrs[] = 'data-case-id="' . $case_id . '"';
		}

		if ( $post_id ) {
			$data_attrs[] = 'data-post-id="' . $post_id . '"';
		}

		if ( is_tax() ) {
			$current_term_id = get_queried_object_id();
			$data_attrs[] = 'data-current-term-id="' . esc_attr( $current_term_id ) . '"';

			// Get API procedure ID from current term meta
			$current_api_procedure_id = get_term_meta( $current_term_id, 'procedure_id', true );
			if ( ! empty( $current_api_procedure_id ) ) {
				$data_attrs[] = 'data-current-procedure-id="' . esc_attr( $current_api_procedure_id ) . '"';
			}
		}

		if ( ! empty( $procedure_ids ) ) {
			$data_attrs[] = 'data-procedure-ids="' . esc_attr( implode( ',', $procedure_ids ) ) . '"';
		}

		// Add demographic data attributes
		if ( ! empty( $age ) ) {
			$data_attrs[] = 'data-age="' . esc_attr( $age ) . '"';
		}

		if ( ! empty( $gender ) ) {
			$data_attrs[] = 'data-gender="' . esc_attr( strtolower( $gender ) ) . '"';
		}

		if ( ! empty( $ethnicity ) ) {
			$data_attrs[] = 'data-ethnicity="' . esc_attr( strtolower( $ethnicity ) ) . '"';
		}

		// Get height and weight metadata
		$height = get_post_meta( $post_id, 'brag_book_gallery_height', true );
		$weight = get_post_meta( $post_id, 'brag_book_gallery_weight', true );

		if ( ! empty( $height ) ) {
			$data_attrs[] = 'data-height="' . esc_attr( $height ) . '"';
		}

		if ( ! empty( $weight ) ) {
			$data_attrs[] = 'data-weight="' . esc_attr( $weight ) . '"';
		}

		// Add procedure details as data attributes for filtering
		$procedure_details_json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );
		if ( ! empty( $procedure_details_json ) ) {
			$procedure_details = json_decode( $procedure_details_json, true );
			if ( is_array( $procedure_details ) ) {
				foreach ( $procedure_details as $procedure_id => $details ) {
					if ( is_array( $details ) ) {
						foreach ( $details as $detail_label => $detail_value ) {
							// Create a sanitized attribute name from the label (use dashes for dataset compatibility)
							$attr_name = sanitize_title_with_dashes( $detail_label );

							// Handle array values (e.g., ["Upper", "Lower"])
							if ( is_array( $detail_value ) ) {
								$attr_value = implode( ',', array_map( function( $val ) {
									return strtolower( (string) $val );
								}, $detail_value ) );
							} else {
								$attr_value = strtolower( (string) $detail_value );
							}

							$data_attrs[] = 'data-procedure-detail-' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
						}
					}
				}
			}
		}

		// Get image URL - prioritize post-processed URLs, fallback to gallery images
		$image_url = '';
		$image_alt = '';
		if ( $post_id ) {
			// First priority: Get first post-processed image URL
			$image_url = self::get_first_post_processed_url( $post_id );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( "BRAGBook Debug: Post ID $post_id post-processed URL: " . ( $image_url ?: 'empty' ) );
			}

			// Fallback: Use gallery images if no post-processed URL available
			if ( empty( $image_url ) ) {
				$gallery_images = get_post_meta( $post_id, 'brag_book_gallery_images', true );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( "BRAGBook Debug: Post ID $post_id _case_gallery_images: " . print_r( $gallery_images, true ) );
				}

				if ( is_array( $gallery_images ) && ! empty( $gallery_images ) ) {
					$first_image_id = $gallery_images[0];
					$image_url      = wp_get_attachment_image_url( $first_image_id, 'large' );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
						error_log( "BRAGBook Debug: Fallback to gallery image ID $first_image_id, URL: " . ( $image_url ?: 'FAILED TO GET URL' ) );
					}
				}
			}

			// Set alt text
			$image_alt = sprintf( '%s - Case %s', is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure, $case_id );
		}

		// Final debug log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( "BRAGBook Debug: Final image_url for case $case_id: " . ( $image_url ?: 'empty' ) );
		}

		// Get card type setting
		$case_card_type = get_option( 'brag_book_gallery_case_card_type', 'default' );
		$case_image_carousel = get_option( 'brag_book_gallery_case_image_carousel', false );
		$card_type_class = '';
		if ( 'v2' === $case_card_type ) {
			$card_type_class = ' brag-book-gallery-case-card--v2';
		} elseif ( 'v3' === $case_card_type ) {
			$card_type_class = ' brag-book-gallery-case-card--v3';
		}

		// Get high-res images for carousel if enabled
		$carousel_images = array();

		if ( $case_image_carousel && ( 'v2' === $case_card_type || 'v3' === $case_card_type ) && $post_id ) {

			$high_res_urls = get_post_meta( $post_id, 'brag_book_gallery_case_high_res_url', true );

			if ( ! empty( $high_res_urls ) ) {
				// Split by newlines and semicolons to get individual URLs
				$urls = preg_split( '/[\r\n;]+/', $high_res_urls, -1, PREG_SPLIT_NO_EMPTY );
				foreach ( $urls as $url ) {
					$url = trim( $url );
					if ( ! empty( $url ) ) {
						$carousel_images[] = $url;
					}
				}
			}
		}

		ob_start();
		?>
		<article class="brag-book-gallery-case-card<?php echo esc_attr( $card_type_class ); ?>" <?php echo implode( ' ', $data_attrs ); ?>>
			<div class="brag-book-gallery-case-images single-image">
				<div class="brag-book-gallery-image-container">
						<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>
						<div class="brag-book-gallery-item-actions">
							<button class="brag-book-gallery-favorite-button"
									data-favorited="false"
									data-item-id="case-<?php echo esc_attr( $case_id ); ?>"
									aria-label="Add to favorites">
								<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2"
									 viewBox="0 0 24 24">
									<path
										d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
								</svg>
							</button>
						</div>
						<?php if ( 'v2' === $case_card_type || 'v3' === $case_card_type ) : ?>
							<!-- V2: Image NOT wrapped in anchor (only arrow is clickable) -->
							<!-- V3: Image wrapped in anchor for clickability -->
							<?php if ( 'v3' === $case_card_type ) : ?>
								<a href="<?php echo esc_url( $case_url ); ?>"
								   class="brag-book-gallery-case-permalink"
								   data-case-id="<?php echo esc_attr( $case_id ); ?>"
								   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>">
							<?php endif; ?>
							<?php if ( ! empty( $carousel_images ) ) : ?>
								<!-- Gallery Carousel with multiple images -->
								<div class="brag-book-gallery-case-carousel">
									<?php foreach ( $carousel_images as $index => $carousel_url ) : ?>
										<picture class="brag-book-gallery-picture" id="case-<?php echo esc_attr( $case_id ); ?>-img-<?php echo $index; ?>">
											<img src="<?php echo esc_url( $carousel_url ); ?>"
												 alt="<?php echo esc_attr( $image_alt ?: 'Case Image' ); ?> - Image <?php echo $index + 1; ?>"
												 loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
												 data-image-type="carousel"
												 data-image-url="<?php echo esc_url( $carousel_url ); ?>"
												 onload="if(<?php echo $index; ?>===0){this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';}"
												 fetchpriority="<?php echo 0 === $index ? 'high' : 'low'; ?>">
										</picture>
									<?php endforeach; ?>
								</div>
								<?php if ( count( $carousel_images ) > 1 ) : ?>
									<div class="brag-book-gallery-case-carousel-pagination">
										<?php foreach ( $carousel_images as $index => $carousel_url ) : ?>
											<a href="#case-<?php echo esc_attr( $case_id ); ?>-img-<?php echo $index; ?>"
											   class="brag-book-gallery-case-carousel-dot"
											   aria-label="Go to image <?php echo $index + 1; ?>"></a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php elseif ( ! empty( $image_url ) ) : ?>
								<!-- Single image fallback -->
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
							<?php if ( 'v3' === $case_card_type ) : ?>
								</a>
							<?php endif; ?>
						<?php else : ?>
							<!-- Default: Image wrapped in anchor -->
							<a href="<?php echo esc_url( $case_url ); ?>"
							   class="brag-book-gallery-case-permalink"
							   data-case-id="<?php echo esc_attr( $case_id ); ?>"
							   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>">
								<?php
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
						<?php endif; ?>
						<?php
						// Add nudity warning if needed
						if ( $procedure_nudity ) {
							echo self::render_nudity_warning();
						}
						?>
						<?php if ( 'v2' === $case_card_type || 'v3' === $case_card_type ) : ?>
							<!-- V2/V3 Card: Overlay with case name and arrow -->
							<div class="brag-book-gallery-case-card-overlay">
								<div class="brag-book-gallery-case-card-overlay-content">
									<?php
									// Check if we should show doctor info instead of procedure
									$show_doctor = (bool) get_option( 'brag_book_gallery_show_doctor', false );
									$doctor_data = null;

									if ( $show_doctor && $post_id ) {
										$doctor_data = self::get_doctor_for_post( $post_id );
									}

									if ( $show_doctor && $doctor_data ) :
									?>
									<div class="brag-book-gallery-case-card-overlay-info brag-book-gallery-case-card-overlay-info--doctor">
										<?php if ( ! empty( $doctor_data['photo_url'] ) ) : ?>
											<img src="<?php echo esc_url( $doctor_data['photo_url'] ); ?>"
												 alt="<?php echo esc_attr( $doctor_data['name'] ); ?>"
												 width="48" height="48"
												 class="brag-book-gallery-case-card-overlay-doctor-avatar">
										<?php else : ?>
											<div class="brag-book-gallery-case-card-overlay-doctor-avatar brag-book-gallery-case-card-overlay-doctor-avatar--placeholder">
												<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
													<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
												</svg>
											</div>
										<?php endif; ?>
										<span class="brag-book-gallery-case-card-overlay-title"><?php echo esc_html( $doctor_data['name'] ); ?></span>
									</div>
									<?php else : ?>
									<div class="brag-book-gallery-case-card-overlay-info">
										<span class="brag-book-gallery-case-card-overlay-title">
											<?php
											$display_title = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
											echo esc_html( $display_title );
											?>
										</span>
										<span class="brag-book-gallery-case-card-overlay-case-number">Case #<?php echo esc_html( $case_id ); ?></span>
									</div>
									<?php endif; ?>
									<a href="<?php echo esc_url( $case_url ); ?>"
									   class="brag-book-gallery-case-card-overlay-button"
									   data-case-id="<?php echo esc_attr( $case_id ); ?>"
									   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>"
									   aria-label="View case details">
										<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
											<path d="M504-480 348-636q-11-11-11-28t11-28q11-11 28-11t28 11l184 184q6 6 8.5 13t2.5 15q0 8-2.5 15t-8.5 13L404-268q-11 11-28 11t-28-11q-11-11-11-28t11-28l156-156Z"/>
										</svg>
									</a>
								</div>
							</div>
						<?php endif; ?>
				</div>
			</div>
			<?php if ( 'v2' !== $case_card_type && 'v3' !== $case_card_type ) : ?>
				<!-- Default Card: Show details/summary -->
				<details class="brag-book-gallery-case-card-details">
					<summary class="brag-book-gallery-case-card-summary">
						<div class="brag-book-gallery-case-card-summary-info">
							<span
								class="brag-book-gallery-case-card-summary-info__name"><?php
								// Use primary procedure name (from taxonomy context) instead of post title
								// This ensures we show the correct procedure name, not "Combo Procedures"
								$display_title = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
								echo esc_html( $display_title );
								?></span>
							<span
								class="brag-book-gallery-case-card-summary-info__case-number">Case #<?php echo esc_html( $case_id ); ?></span>
						</div>
						<p class="brag-book-gallery-case-card-summary-details">
							<strong>More Details</strong>
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"
								 fill="currentColor">
								<path
									d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>
							</svg>
						</p>
					</summary>
					<div class="brag-book-gallery-case-card-details-content">
						<p class="brag-book-gallery-case-card-details-content__title">Procedures Performed:</p>
						<ul class="brag-book-gallery-case-card-procedures-list">
							<?php if ( ! empty( $procedures ) && is_array( $procedures ) ) : ?>
								<?php foreach ( $procedures as $procedure ) : ?>
									<?php
									$procedure_name = is_object( $procedure ) ? $procedure->name : $procedure;
									$procedure_url = is_object( $procedure ) ? get_term_link( $procedure ) : '#';
									if ( is_wp_error( $procedure_url ) ) {
										$procedure_url = '#';
									}
									?>
									<li class="brag-book-gallery-case-card-procedures-list__item">
										<a href="<?php echo esc_url( $procedure_url ); ?>"
										   class="brag-book-gallery-case-card-procedures-list__link"
										   aria-label="View <?php echo esc_attr( $procedure_name ); ?> cases">
											<?php echo esc_html( $procedure_name ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							<?php else : ?>
								<?php
								$procedure_name = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
								$procedure_url = is_object( $primary_procedure ) ? get_term_link( $primary_procedure ) : '#';
								if ( is_wp_error( $procedure_url ) ) {
									$procedure_url = '#';
								}
								?>
								<li class="brag-book-gallery-case-card-procedures-list__item">
									<a href="<?php echo esc_url( $procedure_url ); ?>"
									   class="brag-book-gallery-case-card-procedures-list__link"
									   aria-label="View <?php echo esc_attr( $procedure_name ); ?> cases">
										<?php echo esc_html( $procedure_name ); ?>
									</a>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				</details>
			<?php endif; ?>
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
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public static function ajax_load_more_cases(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );

			return;
		}

		// Get parameters from AJAX request
		$start_page     = absint( $_POST['start_page'] ?? 2 );
		$procedure_ids  = sanitize_text_field( $_POST['procedure_ids'] ?? '' );
		$procedure_name = sanitize_text_field( $_POST['procedure_name'] ?? '' );

		try {
			// Get cases data from WordPress posts (since we're using local data now)
			$atts = [
				'columns'        => 3,
				'show_details'   => true,
				'items_per_page' => get_option( 'brag_book_gallery_items_per_page', '200' ),
			];

			// Calculate offset based on page and items per page
			$items_per_page = absint( $atts['items_per_page'] );
			$offset         = ( $start_page - 1 ) * $items_per_page;

			// Query WordPress posts for cases
			$query_args = [
				'post_type'      => 'brag_book_cases',
				'post_status'    => 'publish',
				'posts_per_page' => $items_per_page,
				'offset'         => $offset,
				'meta_query'     => [],
			];

			// Add procedure filtering if provided
			if ( ! empty( $procedure_name ) ) {
				$query_args['tax_query'] = [
					[
						'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
						'field'    => 'slug',
						'terms'    => $procedure_name,
					],
				];
			}

			$cases_query = new \WP_Query( $query_args );
			$html        = '';

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
						'id'           => get_post_meta( $post_id, 'brag_book_gallery_case_id', true ),
						'mainImageUrl' => get_post_meta( $post_id, 'brag_book_gallery_main_image_url', true ),
						'age'          => get_post_meta( $post_id, 'brag_book_gallery_patient_age', true ),
						'gender'       => get_post_meta( $post_id, 'brag_book_gallery_patient_gender', true ),
						'ethnicity'    => get_post_meta( $post_id, 'brag_book_gallery_ethnicity', true ),
						'seoHeadline'  => get_post_meta( $post_id, 'brag_book_gallery_seo_headline', true ),
					];

					// Use case data or post title as fallback
					if ( empty( $case_data['seoHeadline'] ) ) {
						$case_data['seoHeadline'] = get_the_title( $post_id ) ?: 'Untitled Case';
					}

					// Get current procedure ID and term ID from request
					$current_procedure_id = isset( $_POST['current_procedure_id'] ) ? sanitize_text_field( wp_unslash( $_POST['current_procedure_id'] ) ) : '';
					$current_term_id      = isset( $_POST['current_term_id'] ) ? sanitize_text_field( wp_unslash( $_POST['current_term_id'] ) ) : '';

					// Render case card
					$html .= self::render_case_card(
						$case_data,
						$image_display_mode,
						$procedure_has_nudity,
						$procedure_name,
						$current_procedure_id,
						$current_term_id
					);
				}

				wp_reset_postdata();

				// Check if there are more pages available
				$total_posts    = $cases_query->found_posts;
				$current_loaded = $offset + $cases_query->post_count;
				$has_more       = $current_loaded < $total_posts;

				wp_send_json_success( [
					'html'        => $html,
					'hasMore'     => $has_more,
					'currentPage' => $start_page,
					'totalCases'  => $total_posts,
					'loadedCases' => $current_loaded,
				] );

			} else {
				// No more cases found
				wp_send_json_success( [
					'html'    => '',
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
	 * AJAX handler to get adjacent cases for a specific procedure
	 *
	 * Returns the next and previous case URLs for navigation within a procedure
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function ajax_get_adjacent_cases(): void {
		$procedure_slug  = isset( $_POST['procedure_slug'] ) ? sanitize_text_field( $_POST['procedure_slug'] ) : '';
		$term_id         = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$current_post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		error_log( 'AJAX Adjacent Cases - Procedure: ' . $procedure_slug . ', Term ID: ' . $term_id . ', Post ID: ' . $current_post_id );

		if ( empty( $current_post_id ) ) {
			error_log( 'AJAX Adjacent Cases - Missing post ID' );
			wp_send_json_error( [ 'message' => 'Missing post ID' ] );
			return;
		}

		// Verify the post exists
		$current_post = get_post( $current_post_id );
		if ( ! $current_post || $current_post->post_type !== Post_Types::POST_TYPE_CASES ) {
			error_log( 'AJAX Adjacent Cases - Invalid post ID: ' . $current_post_id );
			wp_send_json_error( [ 'message' => 'Invalid post ID: ' . $current_post_id ] );
			return;
		}

		// Get the procedure term - prefer term_id if provided, otherwise fallback to slug lookup
		if ( ! empty( $term_id ) ) {
			$procedure_term = get_term( $term_id, Taxonomies::TAXONOMY_PROCEDURES );
			error_log( 'AJAX Adjacent Cases - Using term ID: ' . $term_id );
		} elseif ( ! empty( $procedure_slug ) ) {
			$procedure_term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
			error_log( 'AJAX Adjacent Cases - Falling back to slug lookup: ' . $procedure_slug );
		} else {
			error_log( 'AJAX Adjacent Cases - No term ID or slug provided' );
			wp_send_json_error( [ 'message' => 'Missing procedure identifier' ] );
			return;
		}

		if ( ! $procedure_term || is_wp_error( $procedure_term ) ) {
			error_log( 'AJAX Adjacent Cases - Invalid procedure term' );
			wp_send_json_error( [ 'message' => 'Invalid procedure' ] );
			return;
		}

		error_log( 'AJAX Adjacent Cases - Found procedure term: ' . $procedure_term->term_id . ' (' . $procedure_term->slug . ')' );

		// Get case order list from taxonomy term meta
		$case_order_list = get_term_meta( $procedure_term->term_id, 'brag_book_gallery_case_order_list', true );

		if ( is_array( $case_order_list ) && ! empty( $case_order_list ) ) {
			// Extract WordPress IDs from case order list
			$case_ids = [];
			foreach ( $case_order_list as $case_data ) {
				if ( is_array( $case_data ) && ! empty( $case_data['wp_id'] ) ) {
					$case_ids[] = $case_data['wp_id'];
				}
			}

			error_log( 'AJAX Adjacent Cases - Using case order list with ' . count( $case_ids ) . ' cases' );
		} else {
			// Fallback to query if no case order list
			$cases_query = new \WP_Query( [
				'post_type'      => Post_Types::POST_TYPE_CASES,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => [
					[
						'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
						'field'    => 'term_id',
						'terms'    => $procedure_term->term_id,
					],
				],
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			] );

			$case_ids = $cases_query->posts;
			error_log( 'AJAX Adjacent Cases - Fallback to query, found ' . count( $case_ids ) . ' cases' );
		}

		$current_key = array_search( $current_post_id, $case_ids );

		$next_url = null;
		$prev_url = null;

		if ( $current_key === false ) {
			error_log( 'AJAX Adjacent Cases - Current case not found in procedure case order list' );
			wp_send_json_success( [
				'next'    => null,
				'prev'    => null,
				'message' => 'Case not found in this procedure',
			] );
			return;
		}

		// Get gallery page slug for URL construction
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );

		// Get next case
		if ( isset( $case_ids[ $current_key + 1 ] ) ) {
			$next_post_id = $case_ids[ $current_key + 1 ];
			// Use WordPress permalink for absolute URL
			$next_url = get_permalink( $next_post_id );
			// Ensure absolute URL with domain
			if ( ! preg_match( '/^https?:\/\//', $next_url ) ) {
				$next_url = home_url( wp_make_link_relative( $next_url ) );
			}
			error_log( 'AJAX Adjacent Cases - Next case ID: ' . $next_post_id . ', URL: ' . $next_url );
		}

		// Get previous case
		if ( isset( $case_ids[ $current_key - 1 ] ) ) {
			$prev_post_id = $case_ids[ $current_key - 1 ];
			// Use WordPress permalink for absolute URL
			$prev_url = get_permalink( $prev_post_id );
			// Ensure absolute URL with domain
			if ( ! preg_match( '/^https?:\/\//', $prev_url ) ) {
				$prev_url = home_url( wp_make_link_relative( $prev_url ) );
			}
			error_log( 'AJAX Adjacent Cases - Prev case ID: ' . $prev_post_id . ', URL: ' . $prev_url );
		}

		wp_send_json_success( [
			'next' => $next_url,
			'prev' => $prev_url,
		] );
	}

	/**
	 * Clear cases cache
	 *
	 * Clears all cached cases data.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public static function clear_cache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Check if doctors taxonomy is enabled
	 *
	 * Doctors taxonomy is only enabled when website property ID 111 is configured.
	 *
	 * @return bool True if doctors taxonomy should be enabled.
	 * @since 3.3.3
	 */
	private static function is_doctors_taxonomy_enabled(): bool {
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( ! is_array( $website_property_ids ) ) {
			$website_property_ids = [ $website_property_ids ];
		}

		return in_array( 111, array_map( 'intval', $website_property_ids ), true );
	}

	/**
	 * Get doctor information from taxonomy for a post
	 *
	 * Retrieves the doctor term and its metadata for a given case post.
	 *
	 * @param int $post_id The case post ID.
	 * @return array|null Doctor data array or null if not found.
	 * @since 3.3.3
	 */
	private static function get_doctor_for_post( int $post_id ): ?array {
		// Check if doctors taxonomy is enabled
		if ( ! self::is_doctors_taxonomy_enabled() ) {
			return null;
		}

		// Check if taxonomy exists
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_DOCTORS ) ) {
			return null;
		}

		// Get the doctor terms for this post
		$doctor_terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_DOCTORS );

		if ( empty( $doctor_terms ) || is_wp_error( $doctor_terms ) ) {
			return null;
		}

		$doctor_term = $doctor_terms[0];

		// Get doctor meta
		$profile_photo = get_term_meta( $doctor_term->term_id, 'doctor_profile_photo', true );
		$profile_url   = get_term_meta( $doctor_term->term_id, 'doctor_profile_url', true );

		// Get photo URL if we have an attachment ID
		$photo_url = '';
		if ( ! empty( $profile_photo ) ) {
			$photo_url = wp_get_attachment_image_url( $profile_photo, [ 48, 48 ] );
		}

		return [
			'term_id'    => $doctor_term->term_id,
			'name'       => $doctor_term->name,
			'photo_url'  => $photo_url ?: '',
			'profile_url' => $profile_url ?: '',
		];
	}

	/**
	 * Render doctor info HTML for case card
	 *
	 * Renders the doctor profile photo and name for display in case cards.
	 *
	 * @param int $post_id The case post ID.
	 * @param string $fallback_name Fallback name to display if no doctor found.
	 * @return string HTML output for doctor info.
	 * @since 3.3.3
	 */
	private static function render_doctor_card_info( int $post_id, string $fallback_name = '' ): string {
		$doctor = self::get_doctor_for_post( $post_id );

		if ( ! $doctor ) {
			// Fall back to post meta if taxonomy not available
			$doctor_name = get_post_meta( $post_id, 'brag_book_gallery_doctor_name', true );
			if ( ! empty( $doctor_name ) ) {
				return '<h3 class="case-title doctor-name">' . esc_html( $doctor_name ) . '</h3>';
			}
			if ( ! empty( $fallback_name ) ) {
				return '<h3 class="case-title">' . esc_html( $fallback_name ) . '</h3>';
			}
			return '';
		}

		$html = '<div class="brag-book-gallery-case-doctor">';

		// Profile photo (48x48 circle)
		if ( ! empty( $doctor['photo_url'] ) ) {
			$html .= sprintf(
				'<img src="%s" alt="%s" width="48" height="48" class="brag-book-gallery-case-doctor-avatar">',
				esc_url( $doctor['photo_url'] ),
				esc_attr( $doctor['name'] )
			);
		} else {
			// Placeholder avatar
			$html .= '<div class="brag-book-gallery-case-doctor-avatar brag-book-gallery-case-doctor-avatar--placeholder">'
				. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">'
				. '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>'
				. '</svg>'
				. '</div>';
		}

		// Doctor name
		$html .= '<span class="brag-book-gallery-case-doctor-name">' . esc_html( $doctor['name'] ) . '</span>';

		$html .= '</div>';

		return $html;
	}
}
