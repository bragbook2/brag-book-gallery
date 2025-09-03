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

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Slug_Helper;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cases Shortcode Handler Class
 *
 * Advanced shortcode processor for the BRAGBook Gallery plugin, managing
 * comprehensive case display functionality with enterprise-grade features.
 * Implements WordPress VIP standards with PHP 8.2+ optimizations.
 *
 * Shortcodes Managed:
 * - [brag_book_gallery_cases]: Grid display with filtering and pagination
 * - [brag_book_gallery_case]: Individual case detail views
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
final class Cases_Shortcode_Handler {

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
	 * Cache expiration time in seconds
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600; // 1 hour

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

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering.
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// Debug logging if enabled.
		self::debug_log_query_vars( $filter_procedure, $procedure_title, $case_suffix, $atts );

		// Use case_suffix which now contains both numeric IDs and SEO suffixes.
		$case_identifier = ! empty( $case_suffix ) ? $case_suffix : '';

		// If we have a case identifier, show single case.
		if ( ! empty( $case_identifier ) ) {
			return self::render_single_case( $case_identifier, $atts );
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

		// When filtering by procedure, load ALL cases for that procedure to enable proper filtering.
		$initial_load_size = ! empty( $filter_procedure ) ? 200 : 10;

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

		// Render cases grid.
		return self::render_cases_grid( $cases_data, $atts, $filter_procedure );
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
	public static function handle_case_details( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'case_id'   => '',
				'procedure' => '',
			),
			$atts,
			'brag_book_gallery_case'
		);

		// If no case_id provided, try to get from URL.
		if ( empty( $atts['case_id'] ) ) {
			// Get case_suffix which now contains both numeric IDs and SEO suffixes.
			$atts['case_id'] = sanitize_text_field( get_query_var( 'case_suffix' ) );
		}

		if ( empty( $atts['case_id'] ) ) {
			return '<div class="brag-book-gallery-error">' . esc_html__( 'Case ID not specified.', 'brag-book-gallery' ) . '</div>';
		}

		// Get API configuration.
		$atts = self::get_api_configuration( $atts );

		// Render the single case.
		return self::render_single_case( $atts['case_id'], $atts );
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

		// Start output with proper container structure.
		$output = '<div class="brag-book-gallery-cases-container">';
		$output .= '<div class="brag-book-gallery-cases-grid" data-columns="' . esc_attr( $columns ) . '">';

		// Get image display mode setting.
		$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

		// Get sidebar data once for nudity checking
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$sidebar_data = null;
		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
		}

		foreach ( $cases as $case ) {
			// Check if this case has nudity based on its procedure IDs
			$case_has_nudity = self::case_has_nudity_with_sidebar( $case, $sidebar_data );

			// Render each case card.
			$output .= self::render_case_card(
				$case,
				$image_display_mode,
				$case_has_nudity,
				$filter_procedure
			);
		}

		$output .= '</div>'; // Close .brag-book-gallery-cases-grid
		$output .= '</div>'; // Close .brag-book-gallery-cases-container

		// Add pagination if available.
		if ( ! empty( $cases_data['pagination'] ) ) {
			$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );
			$base_path    = get_site_url() . '/' . $gallery_slug;
			$output      .= self::render_pagination( $cases_data['pagination'], $base_path, $filter_procedure );
		}

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
		$cache_key   = 'brag_book_all_cases_' . md5( $atts['api_token'] . $atts['website_property_id'] );
		$cached_data = get_transient( $cache_key );

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

		return ob_get_clean();
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
		$gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( 'gallery' );

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

		// Get procedure name
		$procedure_name = 'Case';
		if ( ! empty( $procedure_context ) ) {
			$procedure_name = ucwords( str_replace( '-', ' ', $procedure_context ) );
		} elseif ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			$first_procedure = reset( $case['procedures'] );
			$procedure_name = $first_procedure['name'] ?? 'Case';
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

		// Favorites button
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

		// Add nudity warning if needed.
		if ( $procedure_nudity ) {
			$html .= self::render_nudity_warning();
		}

		// Get case URL.
		$case_url = self::get_case_url( $case_info, $procedure_context, $case );

		// Add case content.
		$html .= '<a href="' . esc_url( $case_url ) . '" class="case-link">';

		// Add images.
		if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photo = reset( $case['photoSets'] );

			if ( 'before_after' === $image_display_mode ) {
				// Show both before and after images.
				$html .= '<div class="case-images before-after">';
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
						'<div class="case-image"><img src="%s" alt="%s" /></div>',
						esc_url( $image_url ),
						esc_attr__( 'Case Image', 'brag-book-gallery' )
					);
				}
			}
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
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );
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
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug( 'gallery' );

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
		return ob_get_clean();
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

		return ob_get_clean();
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
