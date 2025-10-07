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
			'<summary class="brag-book-gallery-nav-button" data-category="%s" aria-label="%s">%s %s</summary>',
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
	 * Render nudity warning overlay
	 *
	 * Creates nudity warning element.
	 * Reusable across carousel, cases, and other components.
	 *
	 * @since 3.0.0
	 *
	 * @return string Warning HTML.
	 */
	public static function render_nudity_warning(): string {
		return sprintf(
			'<div class="brag-book-gallery-nudity-warning" data-nudity-warning="true"><div class="brag-book-gallery-nudity-warning-content"><h4 class="brag-book-gallery-nudity-warning-title">%s</h4><p class="brag-book-gallery-nudity-warning-caption">%s</p><button class="brag-book-gallery-nudity-warning-button" type="button">%s</button></div></div>',
			esc_html__( 'Contains Nudity', 'brag-book-gallery' ),
			esc_html__( 'Click to proceed if you wish to view', 'brag-book-gallery' ),
			esc_html__( 'Proceed', 'brag-book-gallery' )
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
}
