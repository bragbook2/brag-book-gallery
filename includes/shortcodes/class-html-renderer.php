<?php
/**
 * Enterprise HTML Renderer for BRAGBook Gallery Plugin
 *
 * Comprehensive HTML generation and rendering system for gallery components,
 * implementing WordPress VIP standards with PHP 8.2+ optimizations and
 * enterprise-grade security features. Provides centralized, type-safe HTML
 * output methods with performance optimization and accessibility compliance.
 *
 * Key Features:
 * - Type-safe HTML generation with comprehensive input validation
 * - WordPress VIP compliant output with proper escaping and sanitization
 * - Responsive design patterns with mobile-first approach
 * - Accessibility-compliant markup with ARIA attributes and semantic HTML
 * - Performance-optimized rendering with intelligent caching strategies
 * - Security-first approach with XSS prevention and input validation
 * - SEO-optimized markup generation with structured data support
 * - Modular component rendering for maintainable code architecture
 *
 * Architecture:
 * - Static methods for stateless HTML generation operations
 * - Component-based rendering with reusable building blocks
 * - Security-focused input validation and output escaping
 * - WordPress VIP compliant error handling and logging
 * - Type-safe operations with PHP 8.2+ features and optimizations
 * - Performance-optimized HTML structure generation
 *
 * HTML Components Generated:
 * - Gallery filter navigation with hierarchical procedure organization
 * - Carousel components with responsive image handling
 * - Case detail cards with adaptive layout and accessibility features
 * - Modal dialogs and interactive elements with proper ARIA support
 * - Favorites system interface with localStorage integration
 * - Search and filtering interfaces with real-time interaction support
 *
 * Security Features:
 * - Comprehensive input validation and sanitization for all data
 * - XSS prevention through WordPress-native escaping functions
 * - Safe handling of user-generated content and API responses
 * - Content Security Policy compliant markup generation
 * - Secure URL generation and link handling with validation
 *
 * Performance Optimizations:
 * - Intelligent HTML caching with invalidation strategies
 * - Optimized markup structure for fast rendering and minimal DOM
 * - Conditional component loading based on feature requirements
 * - Efficient data transformation and processing pipelines
 * - Memory-efficient HTML generation for large datasets
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\shortcodes;

use BRAGBookGallery\Includes\Extend\Data_Fetcher;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML Renderer Class
 *
 * Enterprise-grade HTML generation system for the BRAGBook Gallery plugin,
 * implementing comprehensive HTML rendering with WordPress VIP standards,
 * PHP 8.2+ optimizations, and security-first design principles.
 *
 * Core Functionality:
 * - Type-safe HTML component generation with comprehensive validation
 * - Responsive gallery interface rendering with mobile optimization
 * - Accessibility-compliant markup with proper ARIA attributes
 * - Security-focused output with WordPress-native escaping
 * - Performance-optimized rendering with intelligent caching
 * - SEO-optimized markup generation with structured data support
 *
 * Technical Implementation:
 * - PHP 8.2+ features with union types and match expressions
 * - WordPress VIP coding standards compliance throughout
 * - Comprehensive error handling with graceful degradation
 * - Memory-efficient data processing for large gallery datasets
 * - Modular component architecture for maintainable code
 * - Type-safe operations with strict validation and sanitization
 *
 * @since 3.0.0
 */
final class HTML_Renderer {

	/**
	 * Default word limit for descriptions
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_WORD_LIMIT = 50;

	/**
	 * Default carousel item limit
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_CAROUSEL_LIMIT = 10;

	/**
	 * Default start index for carousel
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_START_INDEX = 0;

	/**
	 * Maximum debug log entries
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_DEBUG_ENTRIES = 100;

	/**
	 * Cache group for rendered HTML
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_gallery_html';

	/**
	 * Cache expiration time (1 hour)
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Format procedure display name with comprehensive casing and special case handling
	 *
	 * Transforms procedure names from various input formats (slugs, raw API data,
	 * user input) into properly formatted, human-readable display names. Handles
	 * medical terminology special cases, parenthetical formatting, and maintains
	 * consistency across the application interface.
	 *
	 * Processing Pipeline:
	 * 1. Input validation and sanitization with WordPress functions
	 * 2. Special case detection for medical terminology and abbreviations
	 * 3. Parenthetical format preservation and standardization
	 * 4. Slug-to-title conversion with proper capitalization
	 * 5. Final formatting validation and output preparation
	 *
	 * Supported Input Formats:
	 * - API slugs: 'arm-lift', 'lower-lid-canthoplasty'
	 * - Raw names: 'arm lift', 'CO2 LASER'
	 * - Mixed formats: 'Upper Lid (ptosis repair)'
	 * - Special terminology: 'IPL/BBL', 'RF Microneedling'
	 *
	 * Security Features:
	 * - Comprehensive input sanitization using WordPress functions
	 * - Safe regex processing with bounds checking
	 * - XSS prevention through proper text field sanitization
	 * - Validation of output format consistency
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Raw procedure name from API or user input.
	 *
	 * @return string Formatted, human-readable procedure display name.
	 */
	public static function format_procedure_display_name( string $procedure_name ): string {
		// Validate and sanitize input with comprehensive security
		$procedure_name = self::validate_and_sanitize_procedure_name( $procedure_name );

		if ( empty( $procedure_name ) ) {
			return '';
		}

		// Process standard formatting patterns with PHP 8.2 optimizations
		return self::process_standard_formatting_patterns( $procedure_name );
	}

	/**
	 * Generate filters from sidebar data
	 *
	 * Creates filter HTML from API sidebar data structure.
	 *
	 * @since 3.0.0
	 *
	 * @param array $sidebar_data Sidebar data from API.
	 *
	 * @return string Generated HTML for filters.
	 */
	public static function generate_filters_from_sidebar( array $sidebar_data ): string {
		// Validate sidebar data with comprehensive structure checking
		if ( ! self::is_valid_sidebar_data( $sidebar_data ) ) {
			self::log_debug( 'No valid sidebar data available, using default filters' );
			return self::generate_default_filters();
		}

		// Log successful data processing for debugging
		self::log_debug( sprintf( 'Generating filters for %d categories', count( $sidebar_data['data'] ?? [] ) ) );

		// Process categories with error handling and validation
		$filters = [];
		foreach ( $sidebar_data['data'] as $category_data ) {
			// Validate individual category data before processing
			if ( ! is_array( $category_data ) ) {
				continue;
			}

			$filter = self::generate_category_filter( $category_data );
			if ( ! empty( $filter ) ) {
				$filters[] = $filter;
			}
		}

		// Return assembled HTML with proper concatenation
		return implode( '', $filters );
	}

	/**
	 * Validate sidebar data structure
	 *
	 * Checks if sidebar data has required structure.
	 *
	 * @since 3.0.0
	 *
	 * @param array $sidebar_data Data to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	/**
	 * Validate sidebar data structure with comprehensive checking
	 *
	 * Performs thorough validation of sidebar data structure to ensure
	 * it contains the required fields and valid data types for safe processing.
	 * Uses PHP 8.2+ features for efficient validation.
	 *
	 * Validation Checks:
	 * - Non-empty array structure
	 * - Required 'data' key presence
	 * - Valid array type for data contents
	 * - Basic structure integrity validation
	 *
	 * @since 3.0.0
	 *
	 * @param array $sidebar_data Sidebar data structure from API.
	 *
	 * @return bool True if sidebar data structure is valid and processable.
	 */
	private static function is_valid_sidebar_data( array $sidebar_data ): bool {
		// Use PHP 8.2 match for efficient validation
		return match ( true ) {
			empty( $sidebar_data ) => false,
			! isset( $sidebar_data['data'] ) => false,
			! is_array( $sidebar_data['data'] ) => false,
			default => true,
		};
	}

	/**
	 * Generate comprehensive single category filter with validation and accessibility
	 *
	 * Creates complete HTML for one category filter section including hierarchical
	 * procedure organization, accessibility features, and responsive design elements.
	 * Implements comprehensive data validation and security measures throughout.
	 *
	 * Generated Structure:
	 * - Category header button with expand/collapse functionality
	 * - Hierarchical procedure list with individual filter links
	 * - Case count display and nudity warning integration
	 * - Accessibility-compliant ARIA attributes and semantic markup
	 * - Mobile-responsive collapsible interface elements
	 *
	 * Validation and Security:
	 * - Comprehensive input validation for all category data
	 * - WordPress sanitization for all dynamic content
	 * - Safe HTML generation with proper escaping
	 * - Structure validation before processing
	 *
	 * @since 3.0.0
	 *
	 * @param array $category_data Complete category data structure from API.
	 *
	 * @return string Complete category filter HTML or empty string on validation failure.
	 */
	private static function generate_category_filter( array $category_data ): string {
		// Validate category data structure with comprehensive checks
		$validation_result = self::validate_category_data( $category_data );
		if ( ! $validation_result['is_valid'] ) {
			return '';
		}

		// Extract and sanitize category information
		$category_info = self::extract_and_sanitize_category_info( $category_data );

		// Generate category components with proper structure
		$filter_button = self::render_category_button(
			$category_info['slug'],
			$category_info['name'],
			$category_info['total_cases']
		);
		$procedures_list = self::render_procedures_list( $category_data['procedures'], $category_info['slug'] );

		// Check if navigation menus should be expanded by default
		$expand_nav_menus = get_option( 'brag_book_gallery_expand_nav_menus', false );

		// Assemble final category HTML with proper escaping using semantic details/summary
		$open_attribute = $expand_nav_menus ? ' open' : '';
		return sprintf(
			'<details class="brag-book-gallery-nav-list__item" data-category="%s"%s>%s%s</details>',
			esc_attr( $category_info['slug'] ),
			$open_attribute,
			$filter_button,
			$procedures_list
		);
	}

	/**
	 * Render category button
	 *
	 * Creates category toggle button HTML.
	 *
	 * @since 3.0.0
	 *
	 * @param string $category_slug Category slug.
	 * @param string $category_name Category name.
	 * @param int    $total_cases   Total case count.
	 *
	 * @return string Button HTML.
	 */
	private static function render_category_button( string $category_slug, string $category_name, int $total_cases ): string {
		$button_label = self::render_category_button_label( $category_name, $total_cases );
		$toggle_icon = self::render_toggle_icon();

		return sprintf(
			'<summary class="brag-book-gallery-nav-button" data-category="%s" aria-label="%s">%s%s</summary>',
			esc_attr( $category_slug ),
			/* translators: %s: category name */
			esc_attr( sprintf( __( '%s category filter', 'brag-book-gallery' ), $category_name ) ),
			$button_label,
			$toggle_icon
		);
	}

	/**
	 * Render category button label
	 *
	 * Creates label content for category button.
	 *
	 * @since 3.0.0
	 *
	 * @param string $category_name Category name.
	 * @param int    $total_cases   Total case count.
	 *
	 * @return string Label HTML.
	 */
	private static function render_category_button_label( string $category_name, int $total_cases ): string {
		// Check if filter counts should be displayed
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );

		if ( $show_filter_counts ) {
			return sprintf(
				'<div class="brag-book-gallery-nav-button__label"><span>%s</span><span class="brag-book-gallery-filter-count">(%d)</span></div>',
				esc_html( $category_name ),
				$total_cases
			);
		} else {
			return sprintf(
				'<div class="brag-book-gallery-nav-button__label"><span>%s</span></div>',
				esc_html( $category_name )
			);
		}
	}

	/**
	 * Render toggle icon
	 *
	 * Creates SVG toggle icon for expandable sections.
	 *
	 * @since 3.0.0
	 *
	 * @return string SVG HTML.
	 */
	private static function render_toggle_icon(): string {
		return '<svg class="brag-book-gallery-nav-button__toggle" xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z"/></svg>';
	}

	/**
	 * Render procedures list
	 *
	 * Creates list of procedure filters.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $procedures    Array of procedures.
	 * @param string $category_slug Category slug.
	 *
	 * @return string List HTML.
	 */
	private static function render_procedures_list( array $procedures, string $category_slug ): string {
		$procedure_items = array();

		foreach ( $procedures as $procedure ) {
			$procedure_item = self::render_procedure_item( $procedure, $category_slug );
			if ( $procedure_item ) {
				$procedure_items[] = $procedure_item;
			}
		}

		return sprintf(
			'<ul class="brag-book-gallery-nav-list-submenu">%s</ul>',
			implode( '', $procedure_items )
		);
	}

	/**
	 * Render a single procedure item
	 *
	 * Creates HTML for one procedure filter link.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $procedure     Procedure data.
	 * @param string $category_slug Category slug.
	 *
	 * @return string Item HTML.
	 */
	private static function render_procedure_item( array $procedure, string $category_slug ): string {
		$procedure_data = self::extract_procedure_data_for_filter( $procedure );

		if ( empty( $procedure_data['name'] ) ) {
			return '';
		}

		$filter_url = self::build_filter_url( $procedure_data['slug'] );
		$procedure_link = self::render_procedure_link( $procedure_data, $category_slug, $filter_url );

		return sprintf( '<li class="brag-book-gallery-nav-list-submenu__item">%s</li>', $procedure_link );
	}

	/**
	 * Extract and process procedure data for filters
	 *
	 * Processes procedure data for filter generation.
	 *
	 * @since 3.0.0
	 *
	 * @param array $procedure Procedure data.
	 *
	 * @return array Processed procedure data.
	 */
	private static function extract_procedure_data_for_filter( array $procedure ): array {
		$raw_procedure_name = sanitize_text_field( $procedure['name'] ?? '' );
		$procedure_name = self::format_procedure_display_name( $raw_procedure_name );
		$procedure_slug = sanitize_title( $procedure['slugName'] ?? $raw_procedure_name );
		$case_count = absint( $procedure['totalCase'] ?? 0 );
		$procedure_ids = $procedure['ids'] ?? array();
		$has_nudity = ! empty( $procedure['nudity'] );

		// Debug logging for specific procedures.
		self::log_procedure_debug( $procedure_name, $procedure_ids, $case_count, $procedure );

		// Sanitize procedure IDs.
		$procedure_id_str = '';
		if ( is_array( $procedure_ids ) ) {
			$procedure_ids = array_map( 'absint', $procedure_ids );
			$procedure_id_str = implode( ',', $procedure_ids );
		}

		return array(
			'name' => $procedure_name,
			'slug' => $procedure_slug,
			'case_count' => $case_count,
			'procedure_id_str' => $procedure_id_str,
			'has_nudity' => $has_nudity,
		);
	}

	/**
	 * Build filter URL for procedure
	 *
	 * Creates URL for procedure filter link.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_slug Procedure slug.
	 *
	 * @return string Filter URL.
	 */
	private static function build_filter_url( string $procedure_slug ): string {
		$current_url = get_permalink();
		$base_path = wp_parse_url( $current_url, PHP_URL_PATH ) ?: '';

		return rtrim( $base_path, '/' ) . '/' . $procedure_slug . '/';
	}

	/**
	 * Render procedure link
	 *
	 * Creates complete procedure filter link HTML.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $procedure_data Procedure data.
	 * @param string $category_slug  Category slug.
	 * @param string $filter_url     Filter URL.
	 *
	 * @return string Link HTML.
	 */
	private static function render_procedure_link( array $procedure_data, string $category_slug, string $filter_url ): string {
		$link_attributes = self::build_link_attributes( $procedure_data, $category_slug );
		$link_content = self::render_link_content( $procedure_data );

		return sprintf(
			'<a href="%s" class="brag-book-gallery-nav-link"%s>%s</a>',
			esc_url( $filter_url ),
			$link_attributes,
			$link_content
		);
	}

	/**
	 * Build link data attributes
	 *
	 * Creates data attribute string for procedure link.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $procedure_data Procedure data.
	 * @param string $category_slug  Category slug.
	 *
	 * @return string Attributes string.
	 */
	private static function build_link_attributes( array $procedure_data, string $category_slug ): string {
		return sprintf(
			' data-category="%s" data-procedure="%s" data-procedure-ids="%s" data-procedure-count="%d" data-nudity="%s"',
			esc_attr( $category_slug ),
			esc_attr( $procedure_data['slug'] ),
			esc_attr( $procedure_data['procedure_id_str'] ),
			$procedure_data['case_count'],
			esc_attr( $procedure_data['has_nudity'] ? 'true' : 'false' )
		);
	}

	/**
	 * Render link content (label and count)
	 *
	 * Creates inner content for procedure link.
	 *
	 * @since 3.0.0
	 *
	 * @param array $procedure_data Procedure data.
	 *
	 * @return string Content HTML.
	 */
	private static function render_link_content( array $procedure_data ): string {
		// Check if filter counts should be displayed
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );

		if ( $show_filter_counts ) {
			return sprintf(
				'<span class="brag-book-gallery-filter-option-label">%s</span><span class="brag-book-gallery-filter-count">(%d)</span>',
				esc_html( $procedure_data['name'] ),
				$procedure_data['case_count']
			);
		} else {
			return sprintf(
				'<span class="brag-book-gallery-filter-option-label">%s</span>',
				esc_html( $procedure_data['name'] )
			);
		}
	}

	/**
	 * Log debug information
	 *
	 * Logs debug messages when WP_DEBUG is enabled.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Debug message.
	 *
	 * @return void
	 */
	private static function log_debug( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'BRAG Book Gallery: %s', $message ) );
		}
	}

	/**
	 * Log procedure-specific debug information
	 *
	 * Logs detailed procedure data for debugging.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Procedure name.
	 * @param array  $procedure_ids  Procedure IDs.
	 * @param int    $case_count     Case count.
	 * @param array  $procedure      Full procedure data.
	 *
	 * @return void
	 */
	/**
	 * Log detailed procedure debug information with VIP compliance
	 *
	 * Provides comprehensive procedure debugging with structured logging
	 * and WordPress VIP standards compliance. Uses PHP 8.2 match expression
	 * for efficient filtering of specific procedures.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Procedure name for context.
	 * @param array  $procedure_ids  Array of procedure IDs.
	 * @param int    $case_count     Number of cases for this procedure.
	 * @param array  $procedure      Complete procedure data array.
	 *
	 * @return void
	 */
	private static function log_procedure_debug( string $procedure_name, array $procedure_ids, int $case_count, array $procedure ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// Use PHP 8.2 match for efficient debug filtering
		$should_debug = match ( true ) {
			false !== stripos( $procedure_name, 'liposuction' ) => true,
			false !== stripos( $procedure_name, 'halo' ) => true,
			default => false,
		};

		if ( $should_debug ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Sidebar procedure debug - %s:', $procedure_name ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( sprintf( '  Procedure IDs: %s', print_r( $procedure_ids, true ) ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '  Case count: %d', $case_count ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( sprintf( '  Full procedure data: %s', print_r( $procedure, true ) ) );
		}
	}

	/**
	 * Generate default filters when no sidebar data is available
	 *
	 * Creates fallback filter structure.
	 *
	 * @since 3.0.0
	 *
	 * @return string Generated HTML for default filters.
	 */
	public static function generate_default_filters(): string {
		return self::render_body_filter();
	}

	/**
	 * Render body filter element
	 *
	 * Creates default body category filter.
	 *
	 * @since 3.0.0
	 *
	 * @return string Filter HTML.
	 */
	private static function render_body_filter(): string {
		$filter_button = self::render_filter_button();
		$filter_submenu = self::render_filter_submenu();

		// Check if navigation menus should be expanded by default
		$expand_nav_menus = get_option( 'brag_book_gallery_expand_nav_menus', false );

		$open_attribute = $expand_nav_menus ? ' open' : '';
		return sprintf(
			'<details class="brag-book-gallery-nav-list__item" data-category="body"%s>%s%s</details>',
			$open_attribute,
			$filter_button,
			$filter_submenu
		);
	}

	/**
	 * Render filter button
	 *
	 * Creates default filter button.
	 *
	 * @since 3.0.0
	 *
	 * @return string Button HTML.
	 */
	private static function render_filter_button(): string {
		$button_label = self::render_button_label();
		$toggle_icon = self::render_toggle_icon();

		return sprintf(
			'<summary class="brag-book-gallery-nav-button" data-category="body" aria-label="%s">%s%s</summary>',
			esc_attr__( 'Body category filter', 'brag-book-gallery' ),
			$button_label,
			$toggle_icon
		);
	}

	/**
	 * Render button label section
	 *
	 * Creates button label content.
	 *
	 * @since 3.0.0
	 *
	 * @return string Label HTML.
	 */
	private static function render_button_label(): string {
		// Check if filter counts should be displayed
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );

		if ( $show_filter_counts ) {
			return sprintf(
				'<div class="brag-book-gallery-nav-button__label"><span>%s</span><span class="brag-book-gallery-filter-count">(0)</span></div>',
				esc_html__( 'Body', 'brag-book-gallery' )
			);
		} else {
			return sprintf(
				'<div class="brag-book-gallery-nav-button__label"><span>%s</span></div>',
				esc_html__( 'Body', 'brag-book-gallery' )
			);
		}
	}

	/**
	 * Render filter submenu
	 *
	 * Creates empty submenu for default filter.
	 *
	 * @since 3.0.0
	 *
	 * @return string Submenu HTML.
	 */
	private static function render_filter_submenu(): string {
		return sprintf(
			'<div class="brag-book-gallery-nav-list-submenu"><p class="no-procedures">%s</p></div>',
			esc_html__( 'No procedures available', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate placeholder carousel items
	 *
	 * Creates skeleton loader items for carousel.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $start          Start index.
	 * @param int    $limit          Number of items.
	 * @param string $procedure_slug Procedure slug.
	 *
	 * @return string Placeholder HTML.
	 */
	private static function generate_placeholder_carousel_items(
		int $start = self::DEFAULT_START_INDEX,
		int $limit = self::DEFAULT_CAROUSEL_LIMIT,
		string $procedure_slug = ''
	): string {
		$items = array();
		$end_index = $start + $limit;

		for ( $i = $start; $i < $end_index; $i++ ) {
			$items[] = self::render_placeholder_item( $i, $procedure_slug );
		}

		return implode( '', $items );
	}

	/**
	 * Render a single placeholder item
	 *
	 * Creates skeleton loader for one carousel item.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $index          Item index.
	 * @param string $procedure_slug Procedure slug.
	 *
	 * @return string Placeholder HTML.
	 */
	private static function render_placeholder_item( int $index, string $procedure_slug ): string {
		$procedure_attr = ! empty( $procedure_slug )
			? sprintf( ' data-procedure="%s"', esc_attr( $procedure_slug ) )
			: '';

		return sprintf(
			'<div class="brag-book-gallery-item placeholder-item" data-index="%d"%s><div class="brag-book-gallery-image-container"><div class="brag-book-gallery-placeholder-container"><div class="skeleton-loader"></div></div></div></div>',
			$index,
			$procedure_attr
		);
	}

	/**
	 * Generate single carousel item HTML
	 *
	 * Creates HTML for one carousel item.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case       Case data.
	 * @param int    $index      Item index.
	 * @param string $base_path  Base URL path.
	 * @param bool   $has_nudity Whether content has nudity.
	 *
	 * @return string Carousel item HTML.
	 */
	public static function generate_single_carousel_item(
		array $case,
		int $index,
		string $base_path,
		bool $has_nudity = false
	): string {
		$case_id         = sanitize_text_field( $case['id'] ?? '' );
		$procedure_title = sanitize_text_field( $case['procedureTitle'] ?? __( 'Unknown Procedure', 'brag-book-gallery' ) );
		$procedure_slug  = sanitize_title( $procedure_title );
		$image_url       = esc_url_raw( $case['mainImageUrl'] ?? '' );

		ob_start();
		?>
		<div class="brag-book-gallery-item"
			 data-index="<?php echo esc_attr( (string) $index ); ?>"
			 data-case-id="<?php echo esc_attr( $case_id ); ?>"
			 data-procedure="<?php echo esc_attr( $procedure_slug ); ?>">
			<div class="brag-book-gallery-image-container">
				<?php if ( $has_nudity ) : ?>
					<?php echo self::generate_nudity_warning(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<a href="<?php echo esc_url( "{$base_path}/{$procedure_slug}/{$case_id}/" ); ?>"
				   class="brag-book-gallery-case-card-link"
				   aria-label="<?php
				   /* translators: 1: case ID, 2: procedure title */
				   printf( esc_attr__( 'View case %1$s for %2$s', 'brag-book-gallery' ), esc_attr( $case_id ), esc_attr( $procedure_title ) );
				   ?>">
					<?php echo self::generate_carousel_image( $image_url, $has_nudity ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</div>
			<?php echo self::generate_item_actions( $case_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate carousel items from data
	 *
	 * Creates multiple carousel items from array of cases.
	 *
	 * @since 3.0.0
	 *
	 * @param array $items Carousel items data.
	 *
	 * @return string Carousel items HTML.
	 */
	public static function generate_carousel_items_from_data( array $items ): string {
		$items_html  = '';
		$slide_index = 0;

		foreach ( $items as $case ) {
			if ( ! empty( $case['photos'] ) && is_array( $case['photos'] ) ) {
				foreach ( $case['photos'] as $photo ) {
					$items_html .= self::generate_carousel_slide_from_photo( $photo, $case, $slide_index );
					$slide_index++;
				}
			}
		}

		return $items_html;
	}

	/**
	 * Generate carousel slide from photo data
	 *
	 * Creates carousel slide HTML from photo and case data.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $photo          Photo data.
	 * @param array  $case           Case data.
	 * @param int    $slide_index    Slide index.
	 * @param string $procedure_slug Procedure slug.
	 * @param bool   $is_standalone  Whether this is a standalone carousel (no action buttons).
	 * @param array  $config         Optional carousel configuration for nudity override.
	 *
	 * @return string Slide HTML.
	 */
	public static function generate_carousel_slide_from_photo(
		array $photo,
		array $case,
		int $slide_index,
		string $procedure_slug = '',
		bool $is_standalone = false,
		array $config = []
	): string {
		$photo_data = self::extract_photo_data( $photo, $config );
		$case_data = self::extract_case_data_for_carousel( $case );
		$slide_data = self::build_slide_data( $photo_data, $case_data, $slide_index );
		$case_url = self::build_case_url( $case_data, $procedure_slug );

		return self::render_carousel_slide( $photo_data, $case_data, $slide_data, $case_url, $is_standalone );
	}

	/**
	 * Extract photo data for carousel slide
	 *
	 * Processes photo array to extract necessary data.
	 *
	 * @since 3.0.0
	 *
	 * @param array $photo  Photo data.
	 * @param array $config Optional configuration for nudity override.
	 *
	 * @return array Processed photo data.
	 */
	private static function extract_photo_data( array $photo, array $config = [] ): array {
		$image_url = $photo['postProcessedImageLocation'] ??
					 $photo['url'] ??
					 $photo['originalBeforeLocation'] ?? '';

		$alt_text = ! empty( $photo['seoAltText'] )
			? sanitize_text_field( $photo['seoAltText'] )
			: __( 'Before and after procedure result', 'brag-book-gallery' );

		// Check for nudity - either from photo data OR carousel-level override
		$has_nudity = false;
		if ( ! empty( $config['nudity'] ) ) {
			// If carousel nudity parameter is true, always show nudity warning
			$has_nudity = true;
		} else {
			// Otherwise, use photo's individual nudity flag
			$has_nudity = ! empty( $photo['hasNudity'] ) || ! empty( $photo['nudity'] );
		}

		return array(
			'id'         => sanitize_text_field( $photo['id'] ?? '' ),
			'image_url'  => esc_url_raw( $image_url ),
			'alt_text'   => $alt_text,
			'has_nudity' => $has_nudity,
		);
	}

	/**
	 * Extract case data for carousel slide
	 *
	 * Processes case data for carousel rendering.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return array Processed case data.
	 */
	private static function extract_case_data_for_carousel( array $case ): array {
		$seo_suffix = '';
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			$seo_suffix = sanitize_title( $first_detail['seoSuffixUrl'] ?? '' );
		}

		return array(
			'id'           => sanitize_text_field( $case['id'] ?? '' ),
			'seo_suffix'   => $seo_suffix,
			'procedures'   => $case['procedures'] ?? array(),
			'total_slides' => count( $case['photos'] ?? array() ),
		);
	}

	/**
	 * Build slide-specific data
	 *
	 * Creates data array for individual slide.
	 *
	 * @since 3.0.0
	 *
	 * @param array $photo_data  Photo data.
	 * @param array $case_data   Case data.
	 * @param int   $slide_index Slide index.
	 *
	 * @return array Slide data.
	 */
	private static function build_slide_data( array $photo_data, array $case_data, int $slide_index ): array {
		$slide_id = sprintf( 'bd-%d', $slide_index );

		if ( ! empty( $case_data['id'] ) && ! empty( $photo_data['id'] ) ) {
			$slide_id = sprintf( '%s-%s', $case_data['id'], $photo_data['id'] );
		}

		/* translators: 1: current slide number, 2: total slides */
		$aria_label = sprintf( __( 'Slide %1$d of %2$d', 'brag-book-gallery' ), $slide_index + 1, $case_data['total_slides'] );

		return array(
			'id'         => $slide_id,
			'index'      => $slide_index,
			'aria_label' => $aria_label,
		);
	}

	/**
	 * Build case URL for carousel slide
	 *
	 * Constructs URL to case detail page.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case_data      Case data.
	 * @param string $procedure_slug Procedure slug.
	 *
	 * @return string Case URL.
	 */
	private static function build_case_url( array $case_data, string $procedure_slug ): string {
		if ( empty( $case_data['id'] ) ) {
			return '';
		}

		$gallery_slug = self::get_gallery_slug();
		$resolved_procedure_slug = self::resolve_procedure_slug( $case_data, $procedure_slug );
		$case_identifier = ! empty( $case_data['seo_suffix'] )
			? $case_data['seo_suffix']
			: $case_data['id'];

		return home_url( sprintf( '/%s/%s/%s/', $gallery_slug, $resolved_procedure_slug, $case_identifier ) );
	}

	/**
	 * Resolve procedure slug from case data or parameter
	 *
	 * Determines the correct procedure slug to use.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case_data      Case data.
	 * @param string $procedure_slug Procedure slug.
	 *
	 * @return string Resolved procedure slug.
	 */
	private static function resolve_procedure_slug( array $case_data, string $procedure_slug ): string {
		if ( ! empty( $procedure_slug ) ) {
			return sanitize_title( $procedure_slug );
		}

		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			$first_procedure = reset( $case_data['procedures'] );

			if ( ! empty( $first_procedure['slugName'] ) ) {
				return sanitize_title( $first_procedure['slugName'] );
			}

			if ( ! empty( $first_procedure['name'] ) ) {
				return sanitize_title( $first_procedure['name'] );
			}
		}

		return 'case';
	}

	/**
	 * Render the complete carousel slide
	 *
	 * Assembles all components into complete slide HTML.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $photo_data    Photo data.
	 * @param array  $case_data     Case data.
	 * @param array  $slide_data    Slide data.
	 * @param string $case_url      Case URL.
	 * @param bool   $is_standalone Whether this is a standalone carousel (no action buttons).
	 *
	 * @return string Complete slide HTML.
	 */
	private static function render_carousel_slide( array $photo_data, array $case_data, array $slide_data, string $case_url, bool $is_standalone = false ): string {
		$slide_wrapper = self::render_slide_wrapper( $slide_data, $case_data );
		$nudity_warning = $photo_data['has_nudity'] ? self::render_nudity_warning() : '';
		$link_open = ! empty( $case_url ) ? self::render_link_open( $case_url, $photo_data['alt_text'] ) : '';
		$link_close = ! empty( $case_url ) ? '</a>' : '';
		$image_element = self::render_image_element( $photo_data );

		// Only render action buttons for non-standalone carousels
		$action_buttons = $is_standalone ? '' : self::render_carousel_action_buttons( $case_data['id'] );

		return sprintf(
			'%s%s%s%s%s%s</div>',
			$slide_wrapper,
			$nudity_warning,
			$link_open,
			$image_element,
			$link_close,
			$action_buttons
		);
	}

	/**
	 * Render slide wrapper element
	 *
	 * Creates wrapper div for carousel slide.
	 *
	 * @since 3.0.0
	 *
	 * @param array $slide_data Slide data.
	 *
	 * @return string Wrapper HTML.
	 */
	private static function render_slide_wrapper( array $slide_data, array $case_data = [] ): string {
		$data_attributes = sprintf(
			'data-slide="%s"',
			esc_attr( $slide_data['id'] )
		);

		// Add case debugging data attributes
		if ( ! empty( $case_data ) ) {
			$data_attributes .= sprintf(
				' data-case-id="%s"',
				esc_attr( $case_data['id'] ?? '' )
			);

			if ( ! empty( $case_data['seo_suffix'] ) ) {
				$data_attributes .= sprintf(
					' data-seo-suffix="%s"',
					esc_attr( $case_data['seo_suffix'] )
				);
			}
		}

		return sprintf(
			'<div class="brag-book-gallery-carousel-item" %s role="group" aria-roledescription="slide" aria-label="%s">',
			$data_attributes,
			esc_attr( $slide_data['aria_label'] )
		);
	}

	/**
	 * Render link opening tag
	 *
	 * Creates opening anchor tag for slide link.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_url Case URL.
	 * @param string $alt_text Alt text.
	 *
	 * @return string Link HTML.
	 */
	private static function render_link_open( string $case_url, string $alt_text ): string {
		return sprintf(
			'<a href="%s" class="brag-book-gallery-carousel-link" aria-label="%s">',
			esc_url( $case_url ),
			/* translators: %s: image alt text */
			esc_attr( sprintf( __( 'View case details for %s', 'brag-book-gallery' ), $alt_text ) )
		);
	}

	/**
	 * Render nudity warning overlay
	 *
	 * Creates nudity warning element.
	 *
	 * @since 3.0.0
	 *
	 * @return string Warning HTML.
	 */
	private static function render_nudity_warning(): string {
		return sprintf(
			'<div class="brag-book-gallery-nudity-warning" data-nudity-warning="true"><div class="brag-book-gallery-nudity-warning-content"><h4 class="brag-book-gallery-nudity-warning-title">%s</h4><p class="brag-book-gallery-nudity-warning-caption">%s</p><button class="brag-book-gallery-nudity-warning-button" type="button">%s</button></div></div>',
			esc_html__( 'WARNING: Contains Nudity', 'brag-book-gallery' ),
			esc_html__( 'If you are offended by such material or are under 18 years of age. Please do not proceed.', 'brag-book-gallery' ),
			esc_html__( 'Proceed', 'brag-book-gallery' )
		);
	}

	/**
	 * Render image element with picture tag
	 *
	 * Creates picture element for responsive images.
	 *
	 * @since 3.0.0
	 *
	 * @param array $photo_data Photo data.
	 *
	 * @return string Picture HTML.
	 */
	private static function render_image_element( array $photo_data ): string {
		$blur_class = $photo_data['has_nudity'] ? ' class="brag-book-gallery-nudity-blur"' : '';

		return sprintf(
			'<picture class="brag-book-gallery-carousel-image"><source srcset="%s" type="image/jpeg"><img src="%s" alt="%s" loading="lazy"%s></picture>',
			esc_url( $photo_data['image_url'] ),
			esc_url( $photo_data['image_url'] ),
			esc_attr( $photo_data['alt_text'] ),
			$blur_class
		);
	}

	/**
	 * Render action buttons for carousel slide
	 *
	 * Creates favorite button for carousel items.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case ID.
	 *
	 * @return string Action buttons HTML.
	 */
	private static function render_carousel_action_buttons( string $case_id ): string {
		// Check if favorites functionality is enabled
		if ( ! \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) {
			return '';
		}

		return sprintf(
			'<div class="brag-book-gallery-item-actions"><button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="%s" aria-label="%s"><svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button></div>',
			esc_attr( sprintf( 'case-%s', $case_id ) ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate nudity warning overlay
	 *
	 * Creates warning overlay for content with nudity.
	 *
	 * @since 3.0.0
	 *
	 * @return string Generated HTML for nudity warning.
	 */
	public static function generate_nudity_warning(): string {
		return sprintf(
			'<div class="brag-book-carousel-nudity-warning" aria-label="%s">
				<div class="nudity-warning-content">
					<span class="nudity-warning-text">%s</span>
				</div>
			</div>',
			esc_attr__( 'This image contains nudity', 'brag-book-gallery' ),
			esc_html__( 'Nudity Warning', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate carousel image element
	 *
	 * Creates image element for carousel.
	 *
	 * @since 3.0.0
	 *
	 * @param string $image_url  Image URL.
	 * @param bool   $has_nudity Whether image contains nudity.
	 *
	 * @return string Generated HTML for image.
	 */
	public static function generate_carousel_image( string $image_url, bool $has_nudity ): string {
		$blur_class = $has_nudity ? ' brag-book-carousel-nudity-blur' : '';

		return sprintf(
			'<picture class="brag-book-carousel-image%s">
				<img src="%s" alt="%s" loading="lazy" />
			</picture>',
			esc_attr( $blur_class ),
			esc_url( $image_url ),
			esc_attr__( 'Before and after procedure photo', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate item action buttons
	 *
	 * Creates action buttons for gallery items.
	 *
	 * @since 3.0.0
	 *
	 * @param string $item_id Item identifier.
	 *
	 * @return string Generated HTML for action buttons.
	 */
	public static function generate_item_actions( string $item_id ): string {
		$item_id = sanitize_text_field( $item_id );

		$html = sprintf(
			'<div class="brag-book-carousel-actions">
				<button class="brag-book-gallery-favorite-btn" data-case-id="%1$s" aria-label="%2$s" title="%3$s">
					<svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
					</svg>
				</button>',
			esc_attr( $item_id ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' )
		);

		// Check if sharing is enabled.
		$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
		if ( 'yes' === $enable_sharing ) {
			$html .= sprintf(
				'<button class="brag-book-gallery-share-button" data-case-id="%1$s" aria-label="%2$s" title="%3$s">
					<svg class="share-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="18" cy="5" r="3"></circle>
						<circle cx="6" cy="12" r="3"></circle>
						<circle cx="18" cy="19" r="3"></circle>
						<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
						<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
					</svg>
				</button>',
				esc_attr( $item_id ),
				esc_attr__( 'Share this image', 'brag-book-gallery' ),
				esc_attr__( 'Share this image', 'brag-book-gallery' )
			);
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render case details HTML
	 *
	 * Creates complete case detail view HTML.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data array.
	 *
	 * @return array Array with 'html' and 'seo' keys.
	 */
	public static function render_case_details_html( array $case_data, string $procedure_slug = '', string $procedure_name = '' ): array {
		$procedure_data = self::extract_procedure_data_for_details( $case_data );
		$seo_data = self::extract_seo_data( $case_data );
		$case_id = sanitize_text_field( $case_data['id'] ?? '' );

		// Extract navigation data from case_data
		$navigation_data = $case_data['navigation'] ?? null;

		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'render_case_details_html: Case data keys: ' . implode( ', ', array_keys( $case_data ) ) );
			error_log( 'render_case_details_html: Navigation data extracted: ' . print_r( $navigation_data, true ) );
			error_log( 'render_case_details_html: Has navigation key: ' . ( isset( $case_data['navigation'] ) ? 'YES' : 'NO' ) );
		}

		// Extract procedure IDs for data attributes
		$procedure_ids_attr = '';
		if ( ! empty( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			$procedure_ids_clean = array_map( 'absint', $case_data['procedureIds'] );
			$procedure_ids_attr = sprintf( ' data-procedure-ids="%s"', esc_attr( implode( ',', $procedure_ids_clean ) ) );
		}

		// Add procedure slug attribute if available
		$procedure_slug_attr = ! empty( $procedure_slug )
			? sprintf( ' data-procedure="%s"', esc_attr( $procedure_slug ) )
			: '';

		// Build complete HTML structure with procedure context attributes
		$html = sprintf(
			'<div class="brag-book-gallery-case-detail-view" data-case-id="%s"%s%s>%s%s%s</div>',
			esc_attr( $case_id ),
			$procedure_ids_attr,
			$procedure_slug_attr,
			self::render_case_header( $procedure_data, $seo_data, $case_id, $procedure_slug, $procedure_name, $navigation_data ),
			self::render_case_images( $case_data, $procedure_data, $case_id ),
			self::render_case_details_cards( $case_data )
		);

		return array(
			'html' => $html,
			'seo'  => self::build_seo_response( $seo_data, $procedure_data, $case_id, $procedure_name ),
		);
	}

	/**
	 * Extract procedure data from case data for details view
	 *
	 * Processes procedure information for case details.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return array Procedure data.
	 */
	private static function extract_procedure_data_for_details( array $case_data ): array {
		self::log_debug_info( $case_data );

		$procedure_name = '';
		$procedure_slug = '';
		$procedure_ids = array();

		// Check for procedures array with objects.
		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			// Try to find procedure that matches current page context first
			$current_procedure = null;
			$current_path = $_SERVER['REQUEST_URI'] ?? '';
			$path_segments = array_filter( explode( '/', $current_path ) );
			$url_procedure_slug = count( $path_segments ) >= 3 ? $path_segments[2] : '';

			// Look for procedure that matches current URL slug
			if ( ! empty( $url_procedure_slug ) ) {
				foreach ( $case_data['procedures'] as $procedure ) {
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
				$current_procedure = reset( $case_data['procedures'] );
			}

			if ( ! empty( $current_procedure['name'] ) ) {
				$raw_procedure_name = sanitize_text_field( $current_procedure['name'] );
				$procedure_name = self::format_procedure_display_name( $raw_procedure_name );
				$procedure_slug = sanitize_title( $raw_procedure_name );
			} elseif ( ! empty( $current_procedure['id'] ) ) {
				$procedure_ids[] = absint( $current_procedure['id'] );
			}
		} elseif ( ! empty( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			$procedure_ids = array_map( 'absint', $case_data['procedureIds'] );
		}

		// Look up procedure name if we have IDs but no name.
		if ( empty( $procedure_name ) && ! empty( $procedure_ids ) ) {
			$lookup_result = self::lookup_procedure_by_id( $procedure_ids[0] );
			$procedure_name = $lookup_result['name'];
			$procedure_slug = $lookup_result['slug'];
		}

		// Fallback if still no name.
		if ( empty( $procedure_name ) && ! empty( $procedure_ids[0] ) ) {
			/* translators: %d: procedure ID */
			$procedure_name = sprintf( __( 'Procedure #%d', 'brag-book-gallery' ), $procedure_ids[0] );
			$procedure_slug = sprintf( 'procedure-%d', $procedure_ids[0] );
		}

		return array(
			'name' => $procedure_name,
			'slug' => $procedure_slug,
			'ids'  => $procedure_ids,
		);
	}

	/**
	 * Extract SEO data from case data
	 *
	 * Extracts SEO metadata from case details.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return array SEO data.
	 */
	private static function extract_seo_data( array $case_data ): array {
		$seo_data = array(
			'headline'         => '',
			'page_title'       => '',
			'page_description' => '',
			'suffix_url'       => '',
		);

		if ( ! empty( $case_data['caseDetails'] ) && is_array( $case_data['caseDetails'] ) ) {
			$first_detail = reset( $case_data['caseDetails'] );

			$seo_data['headline']         = sanitize_text_field( $first_detail['seoHeadline'] ?? '' );
			$seo_data['page_title']       = sanitize_text_field( $first_detail['seoPageTitle'] ?? '' );
			$seo_data['page_description'] = sanitize_text_field( $first_detail['seoPageDescription'] ?? '' );
			$seo_data['suffix_url']       = sanitize_title( $first_detail['seoSuffixUrl'] ?? '' );
		}

		return $seo_data;
	}

	/**
	 * Look up procedure by ID from sidebar data
	 *
	 * Finds procedure information by ID.
	 *
	 * @since 3.0.0
	 *
	 * @param int $procedure_id Procedure ID.
	 *
	 * @return array Procedure name and slug.
	 */
	private static function lookup_procedure_by_id( int $procedure_id ): array {
		$result = array( 'name' => '', 'slug' => '' );

		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$api_token = ! empty( $api_tokens[0] ) ? sanitize_text_field( $api_tokens[0] ) : '';

		if ( empty( $api_token ) ) {
			return $result;
		}

		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );
		if ( empty( $sidebar_data['data'] ) ) {
			return $result;
		}

		foreach ( $sidebar_data['data'] as $category ) {
			if ( empty( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $proc ) {
				if ( ! empty( $proc['ids'] ) && in_array( $procedure_id, $proc['ids'], true ) ) {
					$raw_name = sanitize_text_field( $proc['name'] ?? '' );
					$result['name'] = self::format_procedure_display_name( $raw_name );
					$result['slug'] = sanitize_title( $proc['slugName'] ?? $raw_name );

					self::log_debug( sprintf( 'Found procedure! Name: %s', $raw_name ) );

					return $result;
				}
			}
		}

		self::log_debug( sprintf( 'Procedure ID %d not found in sidebar data', $procedure_id ) );

		return $result;
	}

	/**
	 * Render case header section
	 *
	 * Creates header section for case details.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $procedure_data  Procedure data.
	 * @param array  $seo_data       SEO data.
	 * @param string $case_id        Case ID.
	 * @param string $procedure_slug Procedure slug.
	 * @param string $procedure_name Procedure name.
	 * @param array|null $navigation_data Navigation data with previous/next case info.
	 *
	 * @return string Header HTML.
	 */
	private static function render_case_header( array $procedure_data, array $seo_data, string $case_id, string $procedure_slug = '', string $procedure_name = '', ?array $navigation_data = null ): string {
		$gallery_slug = self::get_gallery_slug();
		$base_path = '/' . ltrim( $gallery_slug, '/' );

		// Build back URL and text based on whether we have procedure info
		$back_url = $base_path . '/';
		$back_text = __( '← Back to Gallery', 'brag-book-gallery' );

		if ( ! empty( $procedure_slug ) ) {
			$back_url = $base_path . '/' . $procedure_slug . '/';
			if ( ! empty( $procedure_name ) ) {
				$back_text = sprintf( __( '← Back to %s', 'brag-book-gallery' ), $procedure_name );
			}
		}

		$title_content = self::build_title_content( $seo_data, $procedure_data, $case_id, $procedure_slug, $procedure_name );

		// Build navigation buttons HTML
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'HTML_Renderer: Building navigation buttons' );
			error_log( 'HTML_Renderer: Navigation data: ' . print_r( $navigation_data, true ) );
			error_log( 'HTML_Renderer: Procedure slug: ' . $procedure_slug );
			error_log( 'HTML_Renderer: Base path: ' . $base_path );
		}

		$navigation_buttons = self::build_navigation_buttons( $navigation_data, $procedure_slug, $base_path );

		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'HTML_Renderer: Navigation buttons HTML length: ' . strlen( $navigation_buttons ) );
			error_log( 'HTML_Renderer: Navigation buttons HTML: ' . $navigation_buttons );
		}

		return sprintf(
			'<div class="brag-book-gallery-brag-book-gallery-case-header-section">
				<div class="brag-book-gallery-case-navigation">
					<a href="%s" class="brag-book-gallery-back-link">%s</a>
				</div>
				<div class="brag-book-gallery-brag-book-gallery-case-header">
					<h2 class="brag-book-gallery-content-title">%s</h2>
					%s
				</div>
			</div>',
			esc_url( $back_url ),
			esc_html( $back_text ),
			$title_content, // Already escaped in build_title_content
			$navigation_buttons
		);
	}

	/**
	 * Build title content for header
	 *
	 * Creates title HTML for case header.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $seo_data       SEO data.
	 * @param array  $procedure_data Procedure data.
	 * @param string $case_id        Case ID.
	 *
	 * @return string Title HTML.
	 */
	private static function build_title_content( array $seo_data, array $procedure_data, string $case_id, string $procedure_slug = '', string $procedure_name = '' ): string {
		if ( ! empty( $seo_data['headline'] ) ) {
			return esc_html( $seo_data['headline'] );
		}

		// Use the provided procedure name from the sidebar/context if available
		$display_name = $procedure_data['name'];
		if ( ! empty( $procedure_name ) ) {
			// Use the procedure name as provided from the sidebar (e.g., "Neurotoxins/Botox")
			$display_name = $procedure_name;
		} elseif ( ! empty( $procedure_slug ) ) {
			// Fallback to converting slug to title case only if no name provided
			$display_name = ucwords( str_replace( '-', ' ', $procedure_slug ) );
		}

		$title = sprintf( '<strong>%s</strong>', esc_html( $display_name ) );

		if ( ! empty( $case_id ) ) {
			$title .= sprintf( ' <span class="case-id">#%s</span>', esc_html( $case_id ) );
		}

		return $title;
	}

	/**
	 * Build navigation buttons for case header
	 *
	 * Creates previous/next navigation buttons based on navigation data.
	 *
	 * @since 3.2.4
	 *
	 * @param array|null $navigation_data Navigation data with previous/next case info.
	 * @param string     $procedure_slug  Current procedure slug.
	 * @param string     $base_path       Base gallery path.
	 *
	 * @return string Navigation buttons HTML.
	 */
	private static function build_navigation_buttons( ?array $navigation_data, string $procedure_slug, string $base_path ): string {
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'build_navigation_buttons: Called with navigation_data: ' . print_r( $navigation_data, true ) );
			error_log( 'build_navigation_buttons: procedure_slug: ' . $procedure_slug );
			error_log( 'build_navigation_buttons: base_path: ' . $base_path );
		}

		// Return empty string if no navigation data
		if ( empty( $navigation_data ) ) {
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'build_navigation_buttons: Navigation data is empty, returning empty string' );
			}
			return '';
		}

		$buttons_html = '<div class="brag-book-gallery-case-nav-buttons">';
		$site_url     = home_url();

		// Previous button
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'build_navigation_buttons: Checking previous button' );
			error_log( 'build_navigation_buttons: Previous data exists: ' . ( ! empty( $navigation_data['previous'] ) ? 'YES' : 'NO' ) );
			if ( ! empty( $navigation_data['previous'] ) ) {
				error_log( 'build_navigation_buttons: Previous slug exists: ' . ( ! empty( $navigation_data['previous']['slug'] ) ? 'YES (' . $navigation_data['previous']['slug'] . ')' : 'NO' ) );
			}
		}

		if ( ! empty( $navigation_data['previous'] ) && ! empty( $navigation_data['previous']['slug'] ) ) {
			// Check if navigation data includes procedure slug, otherwise use current procedure slug
			$prev_procedure_slug = ! empty( $navigation_data['previous']['procedureSlug'] ) ?
				$navigation_data['previous']['procedureSlug'] :
				$procedure_slug;

			// Build URL: site_url + base_path + procedure_slug + case_slug
			$prev_url = $site_url . $base_path . '/' . $prev_procedure_slug . '/' . $navigation_data['previous']['slug'] . '/';

			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'build_navigation_buttons: Previous URL: ' . $prev_url );
			}

			$buttons_html .= sprintf(
				'<a href="%s" class="brag-book-gallery-nav-button brag-book-gallery-nav-button--prev" title="%s">' .
				'<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">' .
				'<path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>' .
				'</svg>' .
				'<span class="brag-book-gallery-nav-text">%s</span>' .
				'</a>',
				esc_url( $prev_url ),
				esc_attr__( 'Previous case', 'brag-book-gallery' ),
				esc_html__( 'Previous', 'brag-book-gallery' )
			);
		}

		// Next button
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( 'build_navigation_buttons: Checking next button' );
			error_log( 'build_navigation_buttons: Next data exists: ' . ( ! empty( $navigation_data['next'] ) ? 'YES' : 'NO' ) );
			if ( ! empty( $navigation_data['next'] ) ) {
				error_log( 'build_navigation_buttons: Next slug exists: ' . ( ! empty( $navigation_data['next']['slug'] ) ? 'YES (' . $navigation_data['next']['slug'] . ')' : 'NO' ) );
			}
		}

		if ( ! empty( $navigation_data['next'] ) && ! empty( $navigation_data['next']['slug'] ) ) {
			// Check if navigation data includes procedure slug, otherwise use current procedure slug
			$next_procedure_slug = ! empty( $navigation_data['next']['procedureSlug'] ) ?
				$navigation_data['next']['procedureSlug'] :
				$procedure_slug;

			// Build URL: site_url + base_path + procedure_slug + case_slug
			$next_url = $site_url . $base_path . '/' . $next_procedure_slug . '/' . $navigation_data['next']['slug'] . '/';

			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				error_log( 'build_navigation_buttons: Next URL: ' . $next_url );
			}

			$buttons_html .= sprintf(
				'<a href="%s" class="brag-book-gallery-nav-button brag-book-gallery-nav-button--next" title="%s">' .
				'<span class="brag-book-gallery-nav-text">%s</span>' .
				'<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">' .
				'<path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>' .
				'</svg>' .
				'</a>',
				esc_url( $next_url ),
				esc_attr__( 'Next case', 'brag-book-gallery' ),
				esc_html__( 'Next', 'brag-book-gallery' )
			);
		}

		$buttons_html .= '</div>';

		return $buttons_html;
	}

	/**
	 * Render case images section
	 *
	 * Creates images section for case details.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case_data      Case data.
	 * @param array  $procedure_data Procedure data.
	 * @param string $case_id        Case ID.
	 *
	 * @return string Images section HTML.
	 */
	private static function render_case_images( array $case_data, array $procedure_data, string $case_id ): string {
		if ( empty( $case_data['photoSets'] ) || ! is_array( $case_data['photoSets'] ) ) {
			return self::render_no_images_section();
		}

		$main_viewer = self::render_main_image_viewer( $case_data['photoSets'], $procedure_data, $case_id );
		$thumbnails = count( $case_data['photoSets'] ) > 1
			? self::render_thumbnails( $case_data['photoSets'] )
			: '';

		return sprintf(
			'<div class="brag-book-gallery-brag-book-gallery-case-content"><div class="brag-book-gallery-case-images-section"><div class="brag-book-gallery-case-images-layout">%s%s</div></div>',
			$main_viewer,
			$thumbnails
		);
	}

	/**
	 * Render no images available section
	 *
	 * Creates placeholder for cases without images.
	 *
	 * @since 3.0.0
	 *
	 * @return string No images HTML.
	 */
	private static function render_no_images_section(): string {
		return sprintf(
			'<div class="brag-book-gallery-brag-book-gallery-case-content"><div class="brag-book-gallery-case-images-section"><div class="brag-book-gallery-no-images-container"><p class="brag-book-gallery-no-images">%s</p></div></div>',
			esc_html__( 'No images available for this case.', 'brag-book-gallery' )
		);
	}

	/**
	 * Render main image viewer
	 *
	 * Creates main image viewer component.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $photo_sets     Photo sets array.
	 * @param array  $procedure_data Procedure data.
	 * @param string $case_id        Case ID.
	 *
	 * @return string Viewer HTML.
	 */
	private static function render_main_image_viewer( array $photo_sets, array $procedure_data, string $case_id ): string {
		$first_photo = reset( $photo_sets );
		$main_image_url = esc_url_raw( $first_photo['postProcessedImageLocation'] ?? '' );

		if ( empty( $main_image_url ) ) {
			return '';
		}

		$alt_text = self::get_image_alt_text( $first_photo, $procedure_data, $case_id );
		$action_buttons = self::render_case_action_buttons( $case_id );

		return sprintf(
			'<div class="brag-book-gallery-case-main-viewer"><div class="brag-book-gallery-main-image-container" data-image-index="0"><div class="brag-book-gallery-main-single"><img src="%s" alt="%s" loading="eager">%s</div></div></div>',
			esc_url( $main_image_url ),
			esc_attr( $alt_text ),
			$action_buttons
		);
	}

	/**
	 * Get appropriate alt text for image
	 *
	 * Determines best alt text for image.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $photo          Photo data.
	 * @param array  $procedure_data Procedure data.
	 * @param string $case_id        Case ID.
	 *
	 * @return string Alt text.
	 */
	private static function get_image_alt_text( array $photo, array $procedure_data, string $case_id ): string {
		if ( isset( $photo['seoAltText'] ) && null !== $photo['seoAltText'] ) {
			return sanitize_text_field( $photo['seoAltText'] );
		}

		/* translators: 1: procedure name, 2: case ID */
		return sprintf( __( '%1$s - Case %2$s', 'brag-book-gallery' ), $procedure_data['name'], $case_id );
	}

	/**
	 * Render action buttons for case details
	 *
	 * Creates action buttons for case detail images.
	 *
	 * @since 3.0.0
	 *
	 * @param string $case_id Case ID.
	 *
	 * @return string Action buttons HTML.
	 */
	private static function render_case_action_buttons( string $case_id ): string {
		$favorite_button = '';
		$share_button = '';

		// Add favorite button only if favorites are enabled
		if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) {
			$favorite_button = sprintf(
				'<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case_%s_main" aria-label="%s"><svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>',
				esc_attr( $case_id ),
				esc_attr__( 'Add to favorites', 'brag-book-gallery' )
			);
		}

		$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
		if ( 'yes' === $enable_sharing ) {
			$share_button = sprintf(
				'<button class="brag-book-gallery-share-button" data-item-id="case_%s_main" aria-label="%s"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/></svg></button>',
				esc_attr( $case_id ),
				esc_attr__( 'Share this image', 'brag-book-gallery' )
			);
		}

		// Only render container if we have buttons to show
		if ( empty( $favorite_button ) && empty( $share_button ) ) {
			return '';
		}

		return sprintf( '<div class="brag-book-gallery-item-actions">%s%s</div>', $favorite_button, $share_button );
	}

	/**
	 * Render thumbnails section
	 *
	 * Creates thumbnail grid for multiple images.
	 *
	 * @since 3.0.0
	 *
	 * @param array $photo_sets Photo sets array.
	 *
	 * @return string Thumbnails HTML.
	 */
	private static function render_thumbnails( array $photo_sets ): string {
		$thumbnails_html = '';

		foreach ( $photo_sets as $index => $photo ) {
			$processed_thumb = esc_url_raw( $photo['postProcessedImageLocation'] ?? '' );

			if ( empty( $processed_thumb ) ) {
				continue;
			}

			$active_class = 0 === $index ? ' active' : '';

			$thumbnails_html .= sprintf(
				'<div class="brag-book-gallery-thumbnail-item%s" data-image-index="%d" data-processed-url="%s"><img src="%s" alt="%s" loading="lazy"></div>',
				$active_class,
				$index,
				esc_attr( $processed_thumb ),
				esc_url( $processed_thumb ),
				esc_attr__( 'Thumbnail', 'brag-book-gallery' )
			);
		}

		return sprintf(
			'<div class="brag-book-gallery-case-thumbnails"><div class="brag-book-gallery-thumbnails-grid">%s</div></div>',
			$thumbnails_html
		);
	}

	/**
	 * Render case details cards section
	 *
	 * Creates card grid for case information.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return string Cards HTML.
	 */
	private static function render_case_details_cards( array $case_data ): string {
		$html = '<div class="brag-book-gallery-case-card-details-section"><div class="brag-book-gallery-case-card-details-grid">';

		$html .= self::render_procedures_card( $case_data );
		$html .= self::render_patient_details_card( $case_data );
		$html .= self::render_procedure_details_card( $case_data );
		$html .= self::render_case_notes_card( $case_data );

		return $html . '</div></div></div>'; // Close grids and content.
	}

	/**
	 * Render procedures performed card
	 *
	 * Creates card showing performed procedures.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return string Card HTML.
	 */
	private static function render_procedures_card( array $case_data ): string {
		if ( empty( $case_data['procedures'] ) || ! is_array( $case_data['procedures'] ) ) {
			return '';
		}

		$badges_html = '';
		foreach ( $case_data['procedures'] as $procedure ) {
			if ( ! empty( $procedure['name'] ) ) {
				$badges_html .= sprintf(
					'<span class="procedure-badge">%s</span>',
					esc_html( $procedure['name'] )
				);
			}
		}

		return sprintf(
			'<div class="case-detail-card procedures-performed-card"><div class="card-header"><h3 class="card-title">%s</h3></div><div class="card-content"><div class="brag-book-gallery-procedure-badges-list">%s</div></div></div>',
			esc_html__( 'Procedures Performed', 'brag-book-gallery' ),
			$badges_html
		);
	}

	/**
	 * Render patient details card
	 *
	 * Creates card with patient information.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return string Card HTML.
	 */
	private static function render_patient_details_card( array $case_data ): string {
		$patient_fields = array(
			'id'        => __( 'Case ID', 'brag-book-gallery' ),
			'ethnicity' => __( 'Ethnicity', 'brag-book-gallery' ),
			'gender'    => __( 'Gender', 'brag-book-gallery' ),
			'age'       => __( 'Age', 'brag-book-gallery' ),
			'height'    => __( 'Height', 'brag-book-gallery' ),
			'weight'    => __( 'Weight', 'brag-book-gallery' ),
		);

		$info_html = '';
		foreach ( $patient_fields as $field => $label ) {
			if ( empty( $case_data[ $field ] ) ) {
				continue;
			}

			$value = sanitize_text_field( $case_data[ $field ] );

			// Format specific fields.
			if ( 'gender' === $field ) {
				$value = ucfirst( $value );
			} elseif ( 'age' === $field ) {
				/* translators: %s: age value */
				$value = sprintf( __( '%s years', 'brag-book-gallery' ), $value );
			} elseif ( 'weight' === $field ) {
				/* translators: %s: weight value */
				$value = sprintf( __( '%s lbs', 'brag-book-gallery' ), $value );
			}

			$info_html .= sprintf(
				'<div class="brag-book-gallery-info-item"><span class="brag-book-gallery-info-label">%s</span><span class="brag-book-gallery-info-value">%s</span></div>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		// Return empty string if no patient information is available.
		if ( empty( $info_html ) ) {
			return '';
		}

		return sprintf(
			'<div class="case-detail-card patient-details-card"><div class="card-header"><h3 class="card-title">%s</h3></div><div class="card-content"><div class="patient-info-grid">%s</div></div></div>',
			esc_html__( 'Patient Information', 'brag-book-gallery' ),
			$info_html
		);
	}

	/**
	 * Render procedure details card
	 *
	 * Creates card with procedure-specific details.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return string Card HTML.
	 */
	private static function render_procedure_details_card( array $case_data ): string {
		if ( empty( $case_data['procedureDetails'] ) || ! is_array( $case_data['procedureDetails'] ) ) {
			return '';
		}

		// First pass: collect all regular values and all array values separately
		$all_regular_details = array();
		$all_array_details = array();

		foreach ( $case_data['procedureDetails'] as $procedure_id => $details ) {
			if ( ! is_array( $details ) || empty( $details ) ) {
				continue;
			}

			foreach ( $details as $label => $value ) {
				if ( ! empty( $value ) ) {
					if ( is_array( $value ) ) {
						$all_array_details[ $label ] = $value;
					} else {
						$all_regular_details[ $label ] = $value;
					}
				}
			}
		}

		$all_cards_html = '';

		// Create main Procedure Details card for all regular values first
		if ( ! empty( $all_regular_details ) ) {
			$grid_html = '';
			foreach ( $all_regular_details as $label => $value ) {
				$grid_html .= sprintf(
					'<div class="brag-book-gallery-info-item"><span class="brag-book-gallery-info-label">%s</span><span class="brag-book-gallery-info-value">%s</span></div>',
					esc_html( $label ),
					esc_html( $value )
				);
			}

			if ( $grid_html ) {
				$all_cards_html .= sprintf(
					'<div class="case-detail-card procedure-details-card"><div class="card-header"><h3 class="card-title">%s</h3></div><div class="card-content"><div class="procedure-details-grid">%s</div></div></div>',
					esc_html__( 'Procedure Details', 'brag-book-gallery' ),
					$grid_html
				);
			}
		}

		// Then create separate cards for all array values
		foreach ( $all_array_details as $array_label => $array_values ) {
			$array_items_html = '';
			foreach ( $array_values as $item ) {
				if ( ! empty( $item ) ) {
					$array_items_html .= sprintf(
						'<div class="brag-book-gallery-info-item"><span class="brag-book-gallery-info-value">%s</span></div>',
						esc_html( $item )
					);
				}
			}

			if ( $array_items_html ) {
				$all_cards_html .= sprintf(
					'<div class="case-detail-card procedure-details-card"><div class="card-header"><h3 class="card-title">%s</h3></div><div class="card-content"><div class="procedure-details-grid">%s</div></div></div>',
					esc_html( $array_label ),
					$array_items_html
				);
			}
		}

		return $all_cards_html;
	}

	/**
	 * Render case notes card
	 *
	 * Creates card with case notes.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return string Card HTML.
	 */
	private static function render_case_notes_card( array $case_data ): string {
		if ( empty( $case_data['details'] ) ) {
			return '';
		}

		return sprintf(
			'<div class="case-detail-card case-notes-card"><div class="card-header"><h3 class="card-title">%s</h3></div><div class="card-content"><div class="case-details-content">%s</div></div></div>',
			esc_html__( 'Case Notes', 'brag-book-gallery' ),
			wp_kses_post( $case_data['details'] )
		);
	}

	/**
	 * Get gallery slug from settings
	 *
	 * Retrieves configured gallery page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string Gallery slug.
	 */
	private static function get_gallery_slug(): string {
		$option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		return is_array( $option ) ? ( $option[0] ?? 'gallery' ) : $option;
	}

	/**
	 * Build SEO response data
	 *
	 * Creates SEO metadata array.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $seo_data       SEO data.
	 * @param array  $procedure_data Procedure data.
	 * @param string $case_id        Case ID.
	 *
	 * @return array SEO response.
	 */
	private static function build_seo_response( array $seo_data, array $procedure_data, string $case_id, string $context_procedure_name = '' ): array {
		// Use context procedure name if provided, otherwise fall back to case's procedure
		$display_procedure_name = ! empty( $context_procedure_name ) ? $context_procedure_name : $procedure_data['name'];

		$title = ! empty( $seo_data['page_title'] )
			? $seo_data['page_title']
			/* translators: 1: procedure name, 2: case ID */
			: sprintf( __( '%1$s - Case #%2$s', 'brag-book-gallery' ), $display_procedure_name, $case_id );

		$description = ! empty( $seo_data['page_description'] )
			? $seo_data['page_description']
			/* translators: 1: procedure name, 2: case ID */
			: sprintf( __( 'View before and after photos for %1$s case #%2$s', 'brag-book-gallery' ), $display_procedure_name, $case_id );

		return array(
			'title'       => sanitize_text_field( $title ),
			'description' => sanitize_text_field( $description ),
		);
	}

	/**
	 * Log debug information
	 *
	 * Logs detailed debug data for case processing.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case_data Case data.
	 *
	 * @return void
	 */
	private static function log_debug_info( array $case_data ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( 'Case data keys: %s', implode( ', ', array_keys( $case_data ) ) ) );

		if ( isset( $case_data['procedures'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( sprintf( 'Procedures data: %s', print_r( $case_data['procedures'], true ) ) );
		}

		if ( isset( $case_data['procedureIds'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( sprintf( 'Procedure IDs: %s', print_r( $case_data['procedureIds'], true ) ) );
		}
	}

	/**
	 * Render favorites view
	 *
	 * Creates complete favorites page HTML.
	 *
	 * @since 3.0.0
	 *
	 * @param array $cases Array of favorite cases.
	 *
	 * @return string Favorites view HTML.
	 */
	public static function render_favorites_view( array $cases ): string {
		$count = count( $cases );
		$default_columns = get_option( 'brag_book_gallery_columns', '3' );
		$column2_active = ( '2' === $default_columns ) ? ' active' : '';
		$column3_active = ( '3' === $default_columns ) ? ' active' : '';

		// SVG logo content.
		$svg_logo = self::get_favorites_logo_svg();

		// Main wrapper.
		$html = sprintf(
			'<div class="brag-book-filtered-results">%s<p class="brag-book-gallery-favorites-user"></p><div class="brag-book-gallery-controls">',
			$svg_logo
		);

		// Left controls with favorite count.
		$html .= sprintf(
			'<div class="brag-book-gallery-controls-left"><div class="brag-book-gallery-active-filters"><span class="brag-book-gallery-favorite-count">%s</span></div></div>',
			sprintf(
				/* translators: %d: number of favorites */
				_n( '%d favorite', '%d favorites', $count, 'brag-book-gallery' ),
				$count
			)
		);

		// Grid selector.
		$html .= self::render_grid_selector( $column2_active, $column3_active );

		$html .= '</div>'; // Close controls.

		// Cases grid.
		$html .= sprintf(
			'<div class="brag-book-gallery-case-grid masonry-layout" data-columns="%s">',
			esc_attr( $default_columns )
		);

		if ( empty( $cases ) ) {
			$html .= sprintf(
				'<div class="brag-book-gallery-no-results"><p>%s</p></div>',
				esc_html__( 'No favorites found. Start adding your favorite cases to see them here!', 'brag-book-gallery' )
			);
		} else {
			$html .= self::render_case_cards( $cases );
		}

		return $html . '</div></div>'; // Close grid and wrapper.
	}

	/**
	 * Get favorites logo SVG
	 *
	 * Returns SVG markup for favorites logo.
	 *
	 * @since 3.0.0
	 *
	 * @return string SVG HTML.
	 */
	private static function get_favorites_logo_svg(): string {
		return '<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
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
	}

	/**
	 * Render grid selector controls
	 *
	 * Creates grid view selector buttons.
	 *
	 * @since 3.0.0
	 *
	 * @param string $column2_active Active class for 2 columns.
	 * @param string $column3_active Active class for 3 columns.
	 *
	 * @return string Grid selector HTML.
	 */
	private static function render_grid_selector( string $column2_active, string $column3_active ): string {
		return sprintf(
			'<div class="brag-book-gallery-grid-selector"><span class="brag-book-gallery-grid-label">%s</span><div class="brag-book-gallery-grid-buttons">%s%s</div></div>',
			esc_html__( 'View:', 'brag-book-gallery' ),
			sprintf(
				'<button class="brag-book-gallery-grid-btn%s" data-columns="2" onclick="updateGridLayout(2)" aria-label="%s"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg><span class="sr-only">%s</span></button>',
				esc_attr( $column2_active ),
				esc_attr__( 'View in 2 columns', 'brag-book-gallery' ),
				esc_html__( '2 Columns', 'brag-book-gallery' )
			),
			sprintf(
				'<button class="brag-book-gallery-grid-btn%s" data-columns="3" onclick="updateGridLayout(3)" aria-label="%s"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="4" height="4"/><rect x="6" y="1" width="4" height="4"/><rect x="11" y="1" width="4" height="4"/><rect x="1" y="6" width="4" height="4"/><rect x="6" y="6" width="4" height="4"/><rect x="11" y="6" width="4" height="4"/><rect x="1" y="11" width="4" height="4"/><rect x="6" y="11" width="4" height="4"/><rect x="11" y="11" width="4" height="4"/></svg><span class="sr-only">%s</span></button>',
				esc_attr( $column3_active ),
				esc_attr__( 'View in 3 columns', 'brag-book-gallery' ),
				esc_html__( '3 Columns', 'brag-book-gallery' )
			)
		);
	}

	/**
	 * Render case cards for favorites view
	 *
	 * Creates case card grid for favorites.
	 *
	 * @since 3.0.0
	 *
	 * @param array $cases Array of cases.
	 *
	 * @return string Cards HTML.
	 */
	private static function render_case_cards( array $cases ): string {
		$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );
		$cards_html = '';

		// Get sidebar data once for nudity checking
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$sidebar_data = null;
		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_tokens[0] );
		}

		foreach ( $cases as $case ) {
			$transformed_case = self::transform_case_data( $case );
			$cards_html .= self::render_single_case_card_with_sidebar( $transformed_case, $image_display_mode, $sidebar_data );
		}

		return $cards_html;
	}

	/**
	 * Transform case data for rendering
	 *
	 * Processes case data into standard format.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return array Transformed case data.
	 */
	private static function transform_case_data( array $case ): array {
		$transformed_case = $case;

		// Extract main image from photoSets.
		$transformed_case['mainImageUrl'] = '';
		if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photoset = reset( $case['photoSets'] );
			$transformed_case['mainImageUrl'] = $first_photoset['postProcessedImageLocation'] ??
												$first_photoset['beforeLocationUrl'] ??
												$first_photoset['afterLocationUrl1'] ?? '';
		}

		// Set default procedure title.
		$transformed_case['procedureTitle'] = __( 'Unknown Procedure', 'brag-book-gallery' );

		// Extract procedure title from procedures array.
		if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			$first_procedure = reset( $case['procedures'] );
			$transformed_case['procedureTitle'] = sanitize_text_field(
				$first_procedure['name'] ?? __( 'Unknown Procedure', 'brag-book-gallery' )
			);
		} elseif ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			// Look up procedure names from sidebar data.
			$transformed_case = self::lookup_procedure_names( $transformed_case, $case['procedureIds'] );
		}

		return $transformed_case;
	}

	/**
	 * Look up procedure names from API data
	 *
	 * Resolves procedure names from IDs.
	 *
	 * @since 3.0.0
	 *
	 * @param array $transformed_case Transformed case data.
	 * @param array $procedure_ids    Procedure IDs.
	 *
	 * @return array Updated case data.
	 */
	private static function lookup_procedure_names( array $transformed_case, array $procedure_ids ): array {
		$api_token = get_option( 'brag_book_gallery_api_token' );
		if ( is_array( $api_token ) && ! empty( $api_token[0] ) ) {
			$api_token = sanitize_text_field( $api_token[0] );
		}

		if ( empty( $api_token ) || empty( $procedure_ids[0] ) ) {
			return $transformed_case;
		}

		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );
		if ( empty( $sidebar_data['data'] ) ) {
			return $transformed_case;
		}

		$procedure_info = Data_Fetcher::find_procedure_by_id(
			$sidebar_data['data'],
			absint( $procedure_ids[0] )
		);

		if ( $procedure_info && ! empty( $procedure_info['name'] ) ) {
			$transformed_case['procedureTitle'] = sanitize_text_field( $procedure_info['name'] );

			// Build procedures array for consistency.
			$procedures = array();
			foreach ( $procedure_ids as $procedure_id ) {
				$proc_info = Data_Fetcher::find_procedure_by_id(
					$sidebar_data['data'],
					absint( $procedure_id )
				);
				if ( $proc_info && ! empty( $proc_info['name'] ) ) {
					$procedures[] = array(
						'name' => sanitize_text_field( $proc_info['name'] ),
						'id'   => absint( $procedure_id ),
					);
				}
			}

			if ( ! empty( $procedures ) ) {
				$transformed_case['procedures'] = $procedures;
			}
		}

		return $transformed_case;
	}

	/**
	 * Render a single case card
	 *
	 * Creates HTML for one case card.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $case               Case data.
	 * @param string $image_display_mode Display mode.
	 *
	 * @return string Card HTML.
	 */
	private static function render_single_case_card( array $case, string $image_display_mode ): string {
		try {
			// Check if this case has nudity based on its procedure IDs
			$case_has_nudity = self::case_has_nudity( $case );

			// Try to extract procedure context from current URL or case data
			$procedure_context = '';
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$current_path = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
				$path_segments = array_filter( explode( '/', $current_path ) );
				if ( count( $path_segments ) >= 2 ) {
					$procedure_context = sanitize_title( $path_segments[1] );
				}
			}

			// Use reflection to call protected method from Shortcodes class.
			$shortcodes_class = 'BRAGBookGallery\Includes\Extend\Shortcodes';
			$method = new \ReflectionMethod( $shortcodes_class, 'render_ajax_gallery_case_card' );
			$method->setAccessible( true );

			return $method->invoke( null, $case, $image_display_mode, $case_has_nudity, $procedure_context );
		} catch ( \Exception $e ) {
			self::log_debug( sprintf( 'Error rendering favorite case card: %s', $e->getMessage() ) );
			return self::render_fallback_case_card( $case );
		}
	}

	/**
	 * Render single case card with pre-fetched sidebar data (optimized version).
	 *
	 * @since 3.2.1
	 *
	 * @param array      $case               Case data.
	 * @param string     $image_display_mode Display mode.
	 * @param array|null $sidebar_data       Pre-fetched sidebar data.
	 *
	 * @return string Card HTML.
	 */
	private static function render_single_case_card_with_sidebar( array $case, string $image_display_mode, $sidebar_data ): string {
		try {
			// Check if this case has nudity based on its procedure IDs
			$case_has_nudity = self::case_has_nudity_with_sidebar( $case, $sidebar_data );

			// Try to extract procedure context from current URL or case data
			$procedure_context = '';
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$current_path = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
				$path_segments = array_filter( explode( '/', $current_path ) );
				if ( count( $path_segments ) >= 2 ) {
					$procedure_context = sanitize_title( $path_segments[1] );
				}
			}

			// Use reflection to call protected method from Shortcodes class.
			$shortcodes_class = 'BRAGBookGallery\Includes\Extend\Shortcodes';
			$method = new \ReflectionMethod( $shortcodes_class, 'render_ajax_gallery_case_card' );
			$method->setAccessible( true );

			return $method->invoke( null, $case, $image_display_mode, $case_has_nudity, $procedure_context );
		} catch ( \Exception $e ) {
			self::log_debug( sprintf( 'Error rendering favorite case card: %s', $e->getMessage() ) );
			return self::render_fallback_case_card( $case );
		}
	}

	/**
	 * Fallback case card rendering
	 *
	 * Creates simple fallback card if main renderer fails.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Case data.
	 *
	 * @return string Fallback card HTML.
	 */
	private static function render_fallback_case_card( array $case ): string {
		$case_id = sanitize_text_field( $case['id'] ?? $case['caseId'] ?? '' );
		if ( empty( $case_id ) ) {
			return '';
		}

		return sprintf(
			'<article class="brag-book-gallery-case-card" data-case-id="%s"><div class="brag-book-gallery-case-images">%s</div><div class="brag-book-gallery-case-info"><h3>%s</h3></div></article>',
			esc_attr( $case_id ),
			! empty( $case['mainImageUrl'] ) ? sprintf(
				'<img src="%s" alt="%s" loading="lazy">',
				esc_url( $case['mainImageUrl'] ),
				esc_attr__( 'Case', 'brag-book-gallery' )
			) : '',
			esc_html( $case['procedureTitle'] ?? __( 'Unknown Procedure', 'brag-book-gallery' ) )
		);
	}

	/**
	 * Validate and sanitize procedure name with comprehensive security
	 *
	 * Performs thorough validation and sanitization of procedure names from
	 * various sources (API, user input, URL parameters) using WordPress
	 * sanitization functions and security best practices.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Raw procedure name input.
	 *
	 * @return string Sanitized and validated procedure name.
	 */
	private static function validate_and_sanitize_procedure_name( string $procedure_name ): string {
		// Comprehensive sanitization with WordPress functions
		$sanitized = sanitize_text_field( trim( $procedure_name ) );

		// Additional validation for reasonable length and content
		if ( empty( $sanitized ) || strlen( $sanitized ) > 200 ) {
			return '';
		}

		// Validate against suspicious patterns using PHP 8.2 match
		$is_valid = match ( true ) {
			preg_match( '/[<>"\']/', $sanitized ) => false, // Basic XSS pattern check
			preg_match( '/\b(?:script|javascript|vbscript)\b/i', $sanitized ) => false, // Script injection
			default => true,
		};

		return $is_valid ? $sanitized : '';
	}

	/**
	 * Process special medical terminology cases with comprehensive mapping
	 *
	 * Handles specialized medical and cosmetic procedure terminology using
	 * a comprehensive mapping system. Uses PHP 8.2 match expression for
	 * efficient case processing and maintains consistency across the application.
	 *
	 * Medical Terminology Handled:
	 * - Laser procedures: IPL, BBL, CO2, HALO
	 * - Advanced treatments: RF Microneedling, PRP Therapy
	 * - Surgical procedures: Lid surgeries with proper parenthetical formatting
	 * - Thread treatments: PDO Threads
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Validated procedure name input.
	 *
	 * @return string Formatted special case name or empty string if no match.
	 */
	private static function process_special_medical_cases( string $procedure_name ): string {
		$lower_name = strtolower( trim( $procedure_name ) );

		// Use PHP 8.2 match for efficient special case processing
		return match ( $lower_name ) {
			'ipl bbl laser', 'ipl/bbl laser', 'ipl-bbl-laser' => 'IPL / BBL Laser',
			'co2 laser', 'co2-laser' => 'CO2 Laser',
			'halo laser', 'halo-laser' => 'HALO Laser',
			'bbl laser', 'bbl-laser' => 'BBL Laser',
			'ipl laser', 'ipl-laser' => 'IPL Laser',
			'rf microneedling', 'rf-microneedling' => 'RF Microneedling',
			'prp therapy', 'prp-therapy' => 'PRP Therapy',
			'pdo threads', 'pdo-threads' => 'PDO Threads',
			'lower lid canthoplasty', 'lower-lid-canthoplasty' => 'Lower Lid (Canthoplasty)',
			'upper lid ptosis repair', 'upper-lid-ptosis-repair' => 'Upper Lid (Ptosis Repair)',
			default => '',
		};
	}

	/**
	 * Process standard formatting patterns for procedure names
	 *
	 * Handles standard procedure name formatting including parenthetical
	 * structures, slug conversion, and title case processing. Uses modern
	 * PHP patterns for efficient text processing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Validated procedure name.
	 *
	 * @return string Formatted procedure name.
	 */
	private static function process_standard_formatting_patterns( string $procedure_name ): string {
		// Check if the name already contains parentheses and preserve them
		if ( preg_match( '/^(.+?)\s*\((.+?)\)/', $procedure_name, $matches ) ) {
			// Already has parentheses, ensure proper capitalization
			$main_part = ucwords( strtolower( trim( $matches[1] ) ) );
			$parens_part = ucwords( strtolower( trim( $matches[2] ) ) );

			return $main_part . ' (' . $parens_part . ')';
		}

		// Process slug format (with hyphens) using PHP 8.2 match
		if ( false !== strpos( $procedure_name, '-' ) ) {
			return self::process_hyphenated_procedure_name( $procedure_name );
		}

		// If it already looks properly formatted (has uppercase letters), preserve it
		if ( preg_match( '/[A-Z]/', $procedure_name ) ) {
			return $procedure_name;
		}

		// Default: convert to title case with proper word boundaries
		return ucwords( strtolower( $procedure_name ) );
	}

	/**
	 * Process hyphenated procedure names with special lid surgery handling
	 *
	 * Handles conversion of hyphenated procedure names to proper title case,
	 * with special processing for eyelid surgeries that require parenthetical
	 * formatting for clarity and consistency.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_name Hyphenated procedure name.
	 *
	 * @return string Properly formatted procedure name.
	 */
	private static function process_hyphenated_procedure_name( string $procedure_name ): string {
		// Check if it might be a lid procedure that should have parentheses
		if ( preg_match( '/^(lower|upper)[-\s]lid[-\s](.+)$/i', $procedure_name, $matches ) ) {
			$lid_part = ucwords( strtolower( $matches[1] ) ) . ' Lid';
			$procedure_part = ucwords( str_replace( '-', ' ', $matches[2] ) );

			return $lid_part . ' (' . $procedure_part . ')';
		}

		// Standard hyphen to space conversion with title case
		return ucwords( str_replace( '-', ' ', $procedure_name ) );
	}

	/**
	 * Validate category data structure with comprehensive security checks
	 *
	 * Performs thorough validation of category data to ensure it contains
	 * all required fields and has valid data types for safe processing.
	 *
	 * @since 3.0.0
	 *
	 * @param array $category_data Category data from API.
	 *
	 * @return array Validation result with status and details.
	 */
	private static function validate_category_data( array $category_data ): array {
		// Use PHP 8.2 match for comprehensive validation
		return match ( true ) {
			! isset( $category_data['name'] ) => [ 'is_valid' => false, 'reason' => 'missing_name' ],
			! isset( $category_data['procedures'] ) => [ 'is_valid' => false, 'reason' => 'missing_procedures' ],
			empty( $category_data['procedures'] ) => [ 'is_valid' => false, 'reason' => 'empty_procedures' ],
			! is_array( $category_data['procedures'] ) => [ 'is_valid' => false, 'reason' => 'invalid_procedures_type' ],
			default => [ 'is_valid' => true ],
		};
	}

	/**
	 * Extract and sanitize category information with comprehensive processing
	 *
	 * Processes category data to extract key information and applies WordPress
	 * sanitization functions for security and data integrity.
	 *
	 * @since 3.0.0
	 *
	 * @param array $category_data Raw category data from API.
	 *
	 * @return array Sanitized category information array.
	 */
	private static function extract_and_sanitize_category_info( array $category_data ): array {
		$category_name = sanitize_text_field( $category_data['name'] ?? '' );
		$total_cases = absint( $category_data['totalCase'] ?? 0 );
		$category_slug = sanitize_title( $category_name );

		return [
			'name' => $category_name,
			'slug' => $category_slug,
			'total_cases' => $total_cases,
		];
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
			$procedure = Data_Fetcher::find_procedure_by_id( $sidebar_data['data'], (int) $procedure_id );
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
			error_log( 'HTML_Renderer: Case has no procedureIds or not array. Case ID: ' . ( $case['id'] ?? 'unknown' ) );
			return false;
		}

		// Check if sidebar data is available
		if ( empty( $sidebar_data['data'] ) ) {
			error_log( 'HTML_Renderer: No sidebar data available' );
			return false;
		}

		// Debug: Log case and procedure details
		error_log( 'HTML_Renderer: Checking case ' . ( $case['id'] ?? 'unknown' ) . ' with procedure IDs: ' . implode( ',', $case['procedureIds'] ) );

		// Check each procedure ID for nudity flag
		foreach ( $case['procedureIds'] as $procedure_id ) {
			$procedure = Data_Fetcher::find_procedure_by_id( $sidebar_data['data'], (int) $procedure_id );

			if ( $procedure ) {
				$has_nudity = ! empty( $procedure['nudity'] );
				error_log( 'HTML_Renderer: Procedure ID ' . $procedure_id . ' (' . ( $procedure['name'] ?? 'unknown' ) . ') has nudity: ' . ( $has_nudity ? 'YES' : 'NO' ) );

				if ( $has_nudity ) {
					error_log( 'HTML_Renderer: Case ' . ( $case['id'] ?? 'unknown' ) . ' WILL SHOW nudity warning' );
					return true;
				}
			} else {
				error_log( 'HTML_Renderer: Procedure ID ' . $procedure_id . ' NOT FOUND in sidebar data' );
			}
		}

		error_log( 'HTML_Renderer: Case ' . ( $case['id'] ?? 'unknown' ) . ' will NOT show nudity warning' );
		return false;
	}
}
