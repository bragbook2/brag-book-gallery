<?php
/**
 * Gallery Shortcode Handler for BRAGBook Gallery Plugin
 *
 * Comprehensive main gallery shortcode handler managing the complete gallery
 * experience including responsive layouts, filtering systems, favorites functionality,
 * and accessibility compliance. Implements WordPress VIP standards with PHP 8.2+
 * optimizations and enterprise-grade performance features.
 *
 * Key Features:
 * - Complete responsive gallery layout with mobile-first design
 * - Advanced filtering system with real-time search and demographic filters
 * - MyFavorites functionality with localStorage and API synchronization
 * - Accessibility-compliant UI with ARIA attributes and semantic HTML
 * - Progressive enhancement with JavaScript-based interactions
 * - SEO-optimized URL structures with clean routing
 * - Comprehensive form handling for consultations and favorites
 * - Performance-optimized asset loading and caching strategies
 *
 * Architecture:
 * - Static methods for stateless shortcode operations
 * - Modular HTML rendering with component-based structure
 * - Security-first approach with comprehensive input validation
 * - WordPress VIP compliant error handling and logging
 * - Type-safe operations with PHP 8.2+ features
 * - Responsive design patterns with mobile optimization
 *
 * Frontend Components:
 * - Responsive sidebar with collapsible navigation
 * - Advanced search with autocomplete functionality
 * - Filter badges system for active filter visualization
 * - Modal dialogs for consultation requests and favorites
 * - Progressive image loading with skeleton loaders
 * - Mobile-optimized touch interactions
 *
 * Security Features:
 * - Comprehensive input validation and sanitization
 * - XSS prevention through proper output escaping
 * - CSRF protection through WordPress nonce system
 * - Content Security Policy compliant inline scripts
 * - Safe handling of user-generated content in forms
 *
 * Performance Optimizations:
 * - Conditional asset loading based on gallery requirements
 * - Optimized HTML structure for fast rendering
 * - Efficient JavaScript initialization with lazy loading
 * - Intelligent caching of API data and configuration
 * - Minimal DOM manipulation for better performance
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

namespace BRAGBookGallery\Includes\Extend;

/**
 * Gallery Shortcode Handler Class
 *
 * Enterprise-grade shortcode processor for the main BRAGBook Gallery display,
 * implementing comprehensive gallery functionality with responsive design,
 * advanced filtering, and accessibility compliance. Uses PHP 8.2+ features
 * for optimal performance and WordPress VIP standards for security.
 *
 * Core Functionality:
 * - [brag_book_gallery] shortcode processing and rendering
 * - Responsive gallery layout with mobile-first design
 * - Advanced filtering system with real-time search capabilities
 * - MyFavorites functionality with persistent storage
 * - Accessibility-compliant user interface components
 * - SEO-optimized URL routing and meta tag management
 *
 * Technical Implementation:
 * - PHP 8.2+ features with type safety and performance optimizations
 * - WordPress VIP coding standards compliance
 * - Semantic HTML5 structure with proper ARIA attributes
 * - Progressive enhancement with JavaScript interactions
 * - Memory-efficient data processing for large galleries
 * - Optimized asset loading and caching strategies
 *
 * @since 3.0.0
 */
final class Gallery_Shortcode_Handler {

	/**
	 * Process main gallery shortcode with comprehensive feature support
	 *
	 * Handles the [brag_book_gallery] shortcode processing including URL routing,
	 * configuration validation, asset management, and complete HTML rendering.
	 * Implements responsive design, accessibility compliance, and performance
	 * optimization strategies with WordPress VIP standards.
	 *
	 * Processing Pipeline:
	 * 1. Shortcode attribute parsing and validation
	 * 2. URL query variable extraction for routing
	 * 3. Gallery configuration validation and API setup
	 * 4. Asset enqueuing with conditional loading
	 * 5. Data fetching with intelligent caching
	 * 6. JavaScript localization with complete dataset
	 * 7. HTML rendering with accessibility compliance
	 *
	 * Supported URL Patterns:
	 * - Base gallery: /gallery-slug/
	 * - Filtered view: /gallery-slug/procedure-name/
	 * - Case detail: /gallery-slug/procedure-name/case-identifier/
	 * - Favorites page: /gallery-slug/myfavorites/
	 *
	 * Features:
	 * - Responsive mobile-first design with collapsible navigation
	 * - Advanced filtering with real-time search and demographic options
	 * - MyFavorites functionality with persistent storage
	 * - Accessibility-compliant UI with proper ARIA attributes
	 * - SEO-optimized URL structure and meta tag management
	 * - Performance-optimized asset loading and caching
	 *
	 * Security Features:
	 * - Comprehensive input validation and sanitization
	 * - XSS prevention through proper output escaping
	 * - Content Security Policy compliant inline scripts
	 * - WordPress VIP compliant error handling
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes array with website_property_id support.
	 *
	 * @return string Complete gallery HTML structure or error message on failure.
	 */
	public static function handle( array $atts ): string {
		// Ensure attributes are in array format with type validation
		$atts = is_array( $atts ) ? $atts : [];

		$validated_atts = self::validate_and_sanitize_shortcode_attributes( $atts );
		if ( false === $validated_atts ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Invalid shortcode attributes provided' );
			}

			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html__( 'Invalid gallery configuration provided.', 'brag-book-gallery' )
			);
		}

		// Check if we're on a filtered URL
		$filter_procedure = get_query_var( 'filter_procedure', '' );
		$procedure_title  = get_query_var( 'procedure_title', '' );
		$case_id          = get_query_var( 'case_id', '' );
		$case_suffix      = get_query_var( 'case_suffix', '' );
		$favorites_page   = get_query_var( 'favorites_page', '' );

		// Check if we're on the favorites page
		// We'll continue with the main gallery rendering but add a flag for JavaScript
		$is_favorites_page = ! empty( $favorites_page );

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// Debug: Log what query vars we're getting in main shortcode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Main Gallery Shortcode Debug:' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'filter_procedure: ' . $filter_procedure );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'procedure_title: ' . $procedure_title );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'case_id: ' . $case_id );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'case_suffix: ' . $case_suffix );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Current URL: ' . ( $_SERVER['REQUEST_URI'] ?? 'N/A' ) );
		}

		// Use case_suffix (which now contains both numeric IDs and SEO suffixes)
		$initial_case_id = ! empty( $case_suffix ) ? $case_suffix : '';
		
		// Check for case ID from shortcode attributes (when called from Cases_Shortcode_Handler)
		if ( empty( $initial_case_id ) && ! empty( $validated_atts['data_case_id'] ) ) {
			$initial_case_id = $validated_atts['data_case_id'];
		}
		
		// PERFORMANCE OPTIMIZATION: For case detail pages, render case details directly
		// instead of loading full gallery first (but skip if loading via JavaScript)
		$skip_fast_render = ! empty( $validated_atts['data_case_id'] ); // Skip when called from Cases_Shortcode_Handler
		if ( ! empty( $initial_case_id ) && ! $skip_fast_render ) {
			// Try to render case details directly for speed
			$fast_case_html = self::render_case_details_fast( $initial_case_id, $procedure_title );
			if ( ! empty( $fast_case_html ) ) {
				return $fast_case_html;
			}
			// If fast rendering fails, fall back to normal gallery rendering
		}

		// For procedure filtering, we'll pass it to the main gallery JavaScript
		// The JavaScript will handle applying the filter on load

		// Validate configuration and mode with comprehensive error handling
		$validation = self::validate_gallery_configuration( $validated_atts );
		if ( $validation['error'] ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Configuration validation failed: ' . ( $validation['message'] ?? 'Unknown error' ) );
			}

			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html( $validation['message'] ?? __( 'Gallery configuration error.', 'brag-book-gallery' ) )
			);
		}

		// Extract validated configuration
		$config = $validation['config'];

		// Enqueue required assets
		Asset_Manager::enqueue_gallery_assets();

		// Fetch sidebar data with error handling and caching
		$sidebar_data = self::fetch_sidebar_data_safely( $config['api_token'] );
		if ( empty( $sidebar_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Failed to fetch sidebar data' );
			}

			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html__( 'Unable to load gallery navigation. Please check your API configuration.', 'brag-book-gallery' )
			);
		}

		// Fetch complete cases dataset for filtering with error handling
		$all_cases_data = self::fetch_cases_data_safely( $config['api_token'], $config['website_property_id'] );
		if ( empty( $all_cases_data['data'] ?? [] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Failed to fetch cases data or no cases available' );
			}

			// Continue with empty dataset rather than failing completely
			$all_cases_data = [ 'data' => [] ];
		}

		// Debug log the fetched data
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Fetched all cases data: ' . count( $all_cases_data['data'] ?? [] ) . ' cases' );
		}

		// Localize script data (now with all_cases_data)
		Asset_Manager::localize_gallery_script( $config, $sidebar_data, $all_cases_data );

		// Add inline nudity acceptance script
		Asset_Manager::add_nudity_acceptance_script();

		// Generate and return gallery HTML
		return self::render_gallery_html( $sidebar_data, $config, $all_cases_data, $filter_procedure, $initial_case_id, $is_favorites_page );
	}

	/**
	 * Validate gallery configuration and settings with comprehensive security
	 *
	 * Performs thorough validation of gallery configuration including mode checks,
	 * API credential validation, and security-focused input sanitization. Uses
	 * PHP 8.2+ features for optimal performance and WordPress VIP standards.
	 *
	 * Validation Process:
	 * 1. Mode validation for JavaScript requirement
	 * 2. API credential extraction and type safety checks
	 * 3. Configuration selection with fallback handling
	 * 4. Security-focused input sanitization and validation
	 *
	 * Security Features:
	 * - Type safety with strict array validation
	 * - Comprehensive input sanitization using WordPress functions
	 * - Safe credential handling with proper escaping
	 * - Fallback handling for edge cases and errors
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Shortcode attributes array from WordPress.
	 *
	 * @return array{error: bool, message?: string, config?: array} Validation result with error status and configuration or message.
	 */
	private static function validate_gallery_configuration( array $atts ): array {
		// Validate and sanitize shortcode attributes first
		$validated_atts = self::validate_and_sanitize_shortcode_attributes( $atts );

		// Check mode requirement with PHP 8.2 match expression
		$current_mode = get_option( 'brag_book_gallery_mode', 'javascript' );
		$mode_valid = match ( $current_mode ) {
			'javascript' => true,
			default => false,
		};

		if ( ! $mode_valid ) {
			return [
				'error'   => true,
				'message' => __( 'Gallery requires JavaScript mode to be enabled.', 'brag-book-gallery' ),
			];
		}

		// Extract API configuration with comprehensive validation
		if ( ! $validated_atts ) {
			return [
				'error'   => true,
				'message' => __( 'Invalid shortcode attributes provided.', 'brag-book-gallery' ),
			];
		}

		// Get configuration arrays with type safety
		$api_configuration = self::extract_api_configuration( $validated_atts );

		if ( $api_configuration['error'] ) {
			return [
				'error'   => true,
				'message' => $api_configuration['message'],
			];
		}

		return [
			'error'  => false,
			'config' => $api_configuration['config'],
		];
	}

	/**
	 * Render comprehensive gallery HTML with accessibility and security features
	 *
	 * Generates the complete gallery HTML structure including responsive layout,
	 * accessibility-compliant navigation, form dialogs, and JavaScript data injection.
	 * Implements WordPress VIP standards with proper output escaping and security measures.
	 *
	 * HTML Structure Generated:
	 * - Responsive wrapper with conditional font handling
	 * - Mobile-first navigation with ARIA attributes
	 * - Sidebar with search functionality and procedure filters
	 * - Main content area with favorites integration
	 * - Modal dialogs for consultation and favorites forms
	 * - Inline JavaScript for data initialization
	 *
	 * Accessibility Features:
	 * - Proper ARIA labels and semantic HTML structure
	 * - Keyboard navigation support with focus management
	 * - Screen reader compatibility with descriptive labels
	 * - Skip links for content navigation
	 *
	 * Security Features:
	 * - All dynamic content properly escaped via WordPress functions
	 * - Safe URL handling with WordPress permalink functions
	 * - XSS prevention through comprehensive output sanitization
	 * - Secure JavaScript data injection with JSON encoding
	 *
	 * @since 3.0.0
	 *
	 * @param array  $sidebar_data      API sidebar data with navigation structure.
	 * @param array  $config            Validated gallery configuration array.
	 * @param array  $all_cases_data    Complete cases dataset for filtering (default: empty).
	 * @param string $initial_procedure Initial procedure filter from URL routing (default: empty).
	 * @param string $initial_case_id   Initial case identifier from URL routing (default: empty).
	 * @param bool   $is_favorites_page Flag indicating favorites page context (default: false).
	 *
	 * @return string Complete gallery HTML structure with embedded JavaScript initialization.
	 */
	private static function render_gallery_html( array $sidebar_data, array $config, array $all_cases_data = [], string $initial_procedure = '', string $initial_case_id = '', bool $is_favorites_page = false ): string {
		// Initialize output buffering for complete HTML capture
		ob_start();

		// Extract and validate URL components with security
		$current_url = get_permalink();
		$base_path = self::extract_safe_base_path( $current_url );

		// Generate CSS classes with conditional font handling
		$wrapper_class = self::generate_wrapper_classes();

		// Generate secure data attributes for JavaScript initialization
		$data_attributes = self::prepare_data_attributes( $base_path, $initial_procedure, $initial_case_id, $is_favorites_page );
		$data_attr_string = '';
		foreach ( $data_attributes as $attr => $value ) {
			$data_attr_string .= sprintf( ' %s="%s"', $attr, $value );
		}
		?>
		<!-- BRAG book Gallery Component Start -->
		<div class="<?php echo esc_attr( $wrapper_class ); ?>"
			 <?php echo $data_attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped in prepare_data_attributes ?>
			 role="application"
			 aria-label="Before and After Gallery">
			<!-- Skip to gallery content for accessibility -->
			<a href="#gallery-content" class="brag-book-gallery-skip-link">Skip
				to gallery content</a>
			<!-- Mobile Gallery Navigation Bar -->
			<div class="brag-book-gallery-mobile-header" role="navigation"
				 aria-label="Gallery mobile navigation">
				<button class="brag-book-gallery-mobile-menu-toggle"
						data-menu-open="false"
						aria-label="Open navigation menu"
						aria-expanded="false"
						aria-controls="sidebar-nav">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
						<path d="M192-360v-72h576v72H192Zm0-168v-72h576v72H192Z"/>
					</svg>
				</button>

				<div class="brag-book-gallery-search-wrapper"
					 data-search-location="mobile">
					<svg class="brag-book-gallery-search-icon" fill="none"
						 stroke="currentColor" viewBox="0 0 24 24"
						 aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round"
							  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
					</svg>
					<input type="search"
						   class="brag-book-gallery-mobile-search-input"
						   placeholder="Search Procedures..."
						   name="procedure-search"
						   aria-label="Search cosmetic procedures"
						   aria-describedby="mobile-search-hint"
						   autocomplete="off">
					<span id="mobile-search-hint" class="sr-only">Start typing to search for procedures</span>
					<div class="brag-book-gallery-mobile-search-dropdown"
						 role="listbox" aria-label="Search results"
						 aria-live="polite"></div>
				</div>
			</div>

			<div class="brag-book-gallery-mobile-overlay" data-overlay></div>

			<div class="brag-book-gallery-container">
				<div class="brag-book-gallery-sidebar" role="complementary"
					 id="sidebar-nav" aria-label="Gallery filters">
					<div class="brag-book-gallery-sidebar-header hidden">
						<h2 class="brag-book-gallery-sidebar-title">Filters</h2>
						<button class="brag-book-gallery-sidebar-close"
								data-action="close-menu"
								aria-label="Close menu">
							<svg xmlns="http://www.w3.org/2000/svg"
								 height="24px" viewBox="0 -960 960 960"
								 width="24px" fill="currentColor">
								<path
									d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>
							</svg>
						</button>
					</div>

					<div class="brag-book-gallery-search-wrapper"
						 data-search-location="desktop">
						<svg class="brag-book-gallery-search-icon" fill="none"
							 stroke="currentColor" viewBox="0 0 24 24"
							 aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round"
								  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
						</svg>
						<input type="search"
							   class="brag-book-gallery-search-input"
							   placeholder="Search Procedures..."
							   aria-label="Search cosmetic procedures"
							   aria-describedby="search-hint"
							   aria-autocomplete="list"
							   aria-controls="search-results"
							   autocomplete="off"
							   aria-expanded="false">
						<span id="search-hint" class="sr-only">Start typing to search for procedures</span>
						<div class="brag-book-gallery-search-dropdown"
							 id="search-results" role="listbox"
							 aria-label="Search results" aria-live="polite">
							<!-- Results will be populated here -->
						</div>
					</div>

					<aside class="brag-book-gallery-nav" role="group"
						   aria-label="Procedure filters">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method
						echo HTML_Renderer::generate_filters_from_sidebar( $sidebar_data );
						?>
					</aside>

					<!-- My Favorites Button -->
					<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) : ?>
					<div class="brag-book-gallery-favorites-link-wrapper">
						<?php
						$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'before-after' );
						if ( is_array( $gallery_slug ) ) {
							$gallery_slug = ! empty( $gallery_slug[0] ) ? $gallery_slug[0] : 'before-after';
						}
						$favorites_url = '/' . ltrim( $gallery_slug, '/' ) . '/myfavorites';
						?>
						<a href="<?php echo esc_url( $favorites_url ); ?>" class="brag-book-gallery-favorites-link" data-action="show-favorites">
							<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
								<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
								<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
								<path fill="currentColor" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
								<path fill="currentColor" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
								<path fill="currentColor" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
								<path fill="currentColor" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
								<path fill="currentColor" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
								<path fill="currentColor" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
								<path fill="currentColor" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
								<path fill="currentColor" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
								<path fill="currentColor" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
								<path fill="currentColor" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
								<path fill="currentColor" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
								<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
							</svg>
							<span class="brag-book-gallery-favorites-count" data-favorites-count>(0)</span>
						</a>
					</div>
					<?php endif; ?>

					<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_consultation_enabled() ): ?>
					<p class="brag-book-gallery-consultation-text">
						<strong>Ready for the next step?</strong><br/>Contact us
						to request your consultation.
					</p>
					<button
						class="brag-book-gallery-button brag-book-gallery-button--full"
						data-action="request-consultation"
					>
						Request a Consultation
					</button>
					<?php endif; ?>
				</div>

				<div class="brag-book-gallery-main-content" role="region"
					 aria-label="Gallery content" id="gallery-content"<?php echo $is_favorites_page ? ' data-favorites-page="true"' : ''; ?>>
					<!-- Filter badges container (initially hidden, populated by JavaScript) -->
					<div class="brag-book-gallery-controls-left">
						<div class="brag-book-gallery-active-filters">
							<div class="brag-book-gallery-filter-badges"
								 data-action="filter-badges">
								<!-- Filter badges will be populated by JavaScript -->
							</div>
							<button class="brag-book-gallery-clear-all-filters"
									data-action="clear-filters"
									style="display: none;">
								<?php echo esc_html__( 'Clear All', 'brag-book-gallery' ); ?>
							</button>
						</div>
					</div>
					<?php
					// Get landing page text from settings with default value
					$default_landing_text = '<h2>Go ahead, browse our before & afters... visualize your possibilities.</h2>' . "\n" .
					                       '<p>Our gallery is full of our real patients. Keep in mind results vary.</p>';
					$landing_page_text = get_option( 'brag_book_gallery_landing_page_text', $default_landing_text );

					if ( ! empty( $landing_page_text ) ) {
						// Remove escaped quotes that may have been added by WYSIWYG editor
						$landing_page_text = str_replace( '\"', '"', $landing_page_text );
						$landing_page_text = str_replace( "\'", "'", $landing_page_text );
						$landing_page_text = stripslashes( $landing_page_text );

						// Output the landing page text with shortcode processing
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is sanitized via wp_kses_post when saved
						echo do_shortcode( $landing_page_text );
					} else {
						// Fallback to default content if no landing page text is set
						?>
						<h2 class="brag-book-gallery-main-heading">
							<strong>Go ahead, browse our before &
								afters...</strong>visualize your possibilities
						</h2>

						<!-- Gallery carousel section -->
						<div class="brag-book-gallery-sections"
							 id="gallery-sections">
							<div class="brag-book-gallery-section"
								 aria-label="Gallery Carousel">
								<h3 class="brag-book-gallery-title">Gallery</h3>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is already escaped
								echo do_shortcode( sprintf(
									'[brag_book_carousel api_token="%s" website_property_id="%s" limit="10" show_controls="true" show_pagination="true" class="main-gallery-carousel"]',
									esc_attr( $config['api_token'] ),
									esc_attr( $config['website_property_id'] )
								) );
								?>
							</div>
						</div>
						<?php
					}
					?>
					<p class="brag-book-gallery-powered-by">
						<?php esc_html_e( 'Proudly powered by', 'brag-book-gallery' ); ?>
						<a href="https://bragbookgallery.com/" class="brag-book-gallery-powered-by-link" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e('BRAG book Gallery', 'brag-book-gallery'); ?>
						</a>
					</p>
				</div>
			</div>

			<!-- Share dropdown will be created dynamically by JavaScript -->

			<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_consultation_enabled() ): ?>
			<dialog class="brag-book-gallery-dialog" id="consultationDialog">
				<div class="brag-book-gallery-dialog-content">
					<div class="brag-book-gallery-dialog-header">
						<h2 class="brag-book-gallery-dialog-title">Consultation
							Request</h2>
						<button class="brag-book-gallery-dialog-close"
								data-action="close-dialog"
								aria-label="Close dialog">
							<svg xmlns="http://www.w3.org/2000/svg"
								 height="24px" viewBox="0 -960 960 960"
								 width="24px" fill="currentColor">
								<path
									d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>
							</svg>
						</button>
					</div>
					<!-- Message container for success/error messages -->
					<div class="brag-book-gallery-form-message hidden"
						 id="consultationMessage">
						<div
							class="brag-book-gallery-form-message-content"></div>
					</div>
					<form class="brag-book-gallery-consultation-form"
						  data-form="consultation">
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="name">Name *</label>
							<input type="text"
								   class="brag-book-gallery-form-input"
								   id="name" placeholder="Enter name"
								   name="name" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="email">Email *</label>
							<input type="email"
								   class="brag-book-gallery-form-input"
								   id="email" placeholder="Enter email address"
								   name="email" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="phone">Phone</label>
							<input type="tel"
								   class="brag-book-gallery-form-input"
								   id="phone"
								   placeholder="(123) 456-7890"
								   name="phone"
								   pattern="\([0-9]{3}\) [0-9]{3}-[0-9]{4}"
								   maxlength="14"
								   data-phone-format="true">
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="message">Message *</label>
							<textarea class="brag-book-gallery-form-textarea"
									  id="message" name="message" required
									  placeholder="Tell us about your goals and how we can help..."></textarea>
						</div>
						<button type="submit"
								class="brag-book-gallery-button brag-book-gallery-button--full"
								data-action="form-submit">Submit
							Request
						</button>
					</form>
				</div>
			</dialog>
			<?php endif; ?>

			<!-- Favorites Dialog -->
			<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) : ?>
			<dialog class="brag-book-gallery-dialog" id="favoritesDialog">
				<div class="brag-book-gallery-dialog-content">
					<div class="brag-book-gallery-dialog-header">
						<svg class="brag-book-gallery-dialog-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
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
						</svg>
						<button class="brag-book-gallery-dialog-close"
								data-action="close-favorites-dialog"
								aria-label="Close dialog">
							<svg xmlns="http://www.w3.org/2000/svg"
								 height="24px" viewBox="0 -960 960 960"
								 width="24px" fill="currentColor">
								<path
									d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>
							</svg>
						</button>
					</div>
					<h2 class="brag-book-gallery-dialog-title">Send My
						Favorites</h2>
					<p class="brag-book-gallery-dialog-subtitle">Fill out the
						form below and we'll send your favorited images.</p>
					<form class="brag-book-gallery-favorites-form"
						  data-form="favorites">
						<div class="brag-book-gallery-form-notification" style="display: none;"></div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="fav-name">Full Name *</label>
							<input type="text"
								   class="brag-book-gallery-form-input"
								   id="fav-name" placeholder="Enter full name"
								   name="name" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="fav-email">Email Address *</label>
							<input type="email"
								   class="brag-book-gallery-form-input"
								   id="fav-email"
								   placeholder="Enter email address"
								   name="email" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label"
								   for="fav-phone">Phone *</label>
							<input type="tel"
								   class="brag-book-gallery-form-input"
								   id="fav-phone"
								   placeholder="(123) 456-7890"
								   name="phone"
								   required
								   pattern="\([0-9]{3}\) [0-9]{3}-[0-9]{4}"
								   maxlength="14"
								   data-phone-format="true">
						</div>
						<button type="submit"
								class="brag-book-gallery-button brag-book-gallery-button--full"
								data-action="form-submit">Submit
						</button>
					</form>
				</div>
			</dialog>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $all_cases_data['data'] ?? [] ) ) : ?>
			<script>
				// Store complete dataset for filter generation
				window.bragBookCompleteDataset = <?php echo json_encode( array_map( function ( $case ) {
					return [
						'id'        => $case['id'] ?? '',
						'age'       => $case['age'] ?? $case['patientAge'] ?? '',
						'gender'    => $case['gender'] ?? $case['patientGender'] ?? '',
						'ethnicity' => $case['ethnicity'] ?? $case['patientEthnicity'] ?? '',
						'height'    => $case['height'] ?? $case['patientHeight'] ?? '',
						'weight'    => $case['weight'] ?? $case['patientWeight'] ?? '',
					];
				}, $all_cases_data['data'] ?? [] ) ); ?>;

				// Initialize procedure filters after data is available
				setTimeout( function () {
					if ( typeof initializeProcedureFilters === 'function' ) {
						initializeProcedureFilters();
					}
					// Hook into demographic filter updates for badges
					if ( window.updateDemographicFilterBadges && window.activeFilters ) {
						window.updateDemographicFilterBadges( window.activeFilters );
					}
				}, 100 );
			</script>
		<?php endif; ?>

		<!-- BRAG book Gallery Component End -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate and sanitize shortcode attributes with comprehensive security
	 *
	 * Performs thorough validation of shortcode attributes with type-specific
	 * sanitization and bounds checking. Uses WordPress sanitization functions
	 * to prevent XSS attacks and ensure data integrity.
	 *
	 * @since 3.0.0
	 *
	 * @param array $raw_atts Raw shortcode attributes from WordPress.
	 *
	 * @return array|false Sanitized attributes array or false on validation failure.
	 */
	private static function validate_and_sanitize_shortcode_attributes( array $raw_atts ): array|false {
		// Define default attributes with proper types
		$defaults = [
			'website_property_id' => '',
			'data_case_id' => '',
			'data_procedure_slug' => '',
		];

		// Apply WordPress shortcode attribute parsing with defaults
		$atts = shortcode_atts( $defaults, $raw_atts, 'brag_book_gallery' );

		// Validate and sanitize each attribute
		return [
			'website_property_id' => sanitize_text_field( $atts['website_property_id'] ),
			'data_case_id' => sanitize_text_field( $atts['data_case_id'] ),
			'data_procedure_slug' => sanitize_text_field( $atts['data_procedure_slug'] ),
		];
	}

	/**
	 * Extract and validate API configuration with security measures
	 *
	 * Retrieves API configuration from WordPress options with comprehensive
	 * validation and security checks. Uses PHP 8.2+ features for optimal
	 * performance and type safety.
	 *
	 * @since 3.0.0
	 *
	 * @param array $validated_atts Pre-validated shortcode attributes.
	 *
	 * @return array{error: bool, message?: string, config?: array} Configuration result with error status.
	 */
	private static function extract_api_configuration( array $validated_atts ): array {
		// Get API configuration arrays with null coalescing operators
		$api_tokens = get_option( 'brag_book_gallery_api_token' ) ?: [];
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id' ) ?: [];

		// Ensure arrays using PHP 8.2 match expression
		$api_tokens = match ( gettype( $api_tokens ) ) {
			'array' => $api_tokens,
			default => [],
		};

		$website_property_ids = match ( gettype( $website_property_ids ) ) {
			'array' => $website_property_ids,
			default => [],
		};

		// Use first configuration if none specified in shortcode
		$website_property_id = $validated_atts['website_property_id'] ?: ( $website_property_ids[0] ?? '' );
		$api_token = $api_tokens[0] ?? '';

		// Validate required configuration with comprehensive checks
		if ( empty( trim( (string) $api_token ) ) || empty( trim( (string) $website_property_id ) ) ) {
			return [
				'error' => true,
				'message' => __( 'Please configure API settings to display the gallery.', 'brag-book-gallery' ),
			];
		}

		return [
			'error' => false,
			'config' => [
				'api_token' => sanitize_text_field( (string) $api_token ),
				'website_property_id' => sanitize_text_field( (string) $website_property_id ),
			],
		];
	}

	/**
	 * Extract safe base path from URL with security validation
	 *
	 * Safely extracts the base path from a WordPress permalink with
	 * comprehensive security checks and fallback handling.
	 *
	 * @since 3.0.0
	 *
	 * @param string|false $current_url WordPress permalink or false on error.
	 *
	 * @return string Safe base path with fallback to root.
	 */
	private static function extract_safe_base_path( string|false $current_url ): string {
		if ( false === $current_url || empty( trim( $current_url ) ) ) {
			return '/';
		}

		$base_path = parse_url( $current_url, PHP_URL_PATH );
		return $base_path ?: '/';
	}

	/**
	 * Generate wrapper CSS classes with conditional font handling
	 *
	 * Creates the appropriate CSS class string for the gallery wrapper
	 * based on font configuration settings.
	 *
	 * @since 3.0.0
	 *
	 * @return string Complete CSS class string for gallery wrapper.
	 */
	private static function generate_wrapper_classes(): string {
		$wrapper_class = 'brag-book-gallery-wrapper';

		// Check custom font setting with default fallback
		$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );

		if ( 'yes' !== $use_custom_font ) {
			$wrapper_class .= ' disable-custom-font';
		}

		return $wrapper_class;
	}

	/**
	 * Prepare secure data attributes for JavaScript initialization
	 *
	 * Generates an array of data attributes for the gallery wrapper element
	 * with proper security sanitization and validation.
	 *
	 * @since 3.0.0
	 *
	 * @param string $base_path         Base URL path for gallery navigation.
	 * @param string $initial_procedure Initial procedure filter from URL.
	 * @param string $initial_case_id   Initial case identifier from URL.
	 * @param bool   $is_favorites_page Flag for favorites page context.
	 *
	 * @return array Sanitized data attributes array for HTML output.
	 */
	private static function prepare_data_attributes( string $base_path, string $initial_procedure, string $initial_case_id, bool $is_favorites_page ): array {
		$attributes = [
			'data-base-url' => esc_attr( rtrim( $base_path, '/' ) ),
		];

		if ( ! empty( trim( $initial_procedure ) ) ) {
			$attributes['data-initial-procedure'] = esc_attr( $initial_procedure );
		}

		if ( ! empty( trim( $initial_case_id ) ) ) {
			$attributes['data-initial-case-id'] = esc_attr( $initial_case_id );
		}

		if ( $is_favorites_page ) {
			$attributes['data-favorites-page'] = 'true';
		}

		$attributes['id'] = 'brag-book-gallery';

		return $attributes;
	}

	/**
	 * Safely fetch sidebar data with comprehensive error handling
	 *
	 * Retrieves sidebar data from the API with proper error handling,
	 * validation, and fallback mechanisms. Uses WordPress VIP standards
	 * for debug logging and error reporting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token API authentication token.
	 *
	 * @return array Sidebar data array or empty array on failure.
	 */
	private static function fetch_sidebar_data_safely( string $api_token ): array {
		try {
			$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );

			// Validate the response structure and content
			if ( ! is_array( $sidebar_data ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook Gallery: Invalid sidebar data structure received' );
				}
				return [];
			}

			return $sidebar_data;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Exception fetching sidebar data: ' . $e->getMessage() );
			}
			return [];
		}
	}

	/**
	 * Safely fetch cases data with comprehensive error handling and validation
	 *
	 * Retrieves complete cases dataset from the API with proper error handling,
	 * data structure validation, and performance monitoring. Implements WordPress
	 * VIP standards for error logging and graceful failure handling.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token          API authentication token.
	 * @param string $website_property_id Website property identifier.
	 *
	 * @return array Cases data array with 'data' key or empty structure on failure.
	 */
	private static function fetch_cases_data_safely( string $api_token, string $website_property_id ): array {
		try {
			$cases_data = Data_Fetcher::get_all_cases_for_filtering( $api_token, $website_property_id );

			// Validate the response structure and content
			if ( ! is_array( $cases_data ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook Gallery: Invalid cases data structure received' );
				}
				return [ 'data' => [] ];
			}

			// Ensure data key exists and contains array
			if ( ! isset( $cases_data['data'] ) || ! is_array( $cases_data['data'] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook Gallery: Cases data missing required structure' );
				}
				return [ 'data' => [] ];
			}

			return $cases_data;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAGBook Gallery: Exception fetching cases data: ' . $e->getMessage() );
			}
			return [ 'data' => [] ];
		}
	}

	/**
	 * Render case details directly for fast initial page loads
	 * 
	 * This method optimizes the initial page load for case detail URLs by:
	 * 1. Making a direct API call to get only the specific case data
	 * 2. Rendering minimal HTML with just the case details
	 * 3. Including JavaScript to handle navigation and interactions
	 * 
	 * @param string $case_id Case identifier (ID or SEO suffix)
	 * @param string $procedure_title Procedure name from URL
	 * @return string Fast-rendered case details HTML or empty string if failed
	 */
	private static function render_case_details_fast( string $case_id, string $procedure_title = '' ): string {
		try {
			// Get API configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );
			
			if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
				return ''; // Fall back to normal rendering
			}
			
			$api_token = $api_tokens[0];
			$website_property_id = intval( $website_property_ids[0] );
			
			// Make direct API call with short timeout for speed
			$api_base_url = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );
			$api_url = sprintf( '%s/api/plugin/combine/cases/%s?apiToken=%s&websitePropertyId=%d', 
				$api_base_url, 
				urlencode( $case_id ), 
				urlencode( $api_token ), 
				$website_property_id 
			);
			
			$response = wp_remote_get( $api_url, [
				'timeout' => 5, // Fast timeout for initial page loads
				'headers' => [ 'Accept' => 'application/json' ],
			] );
			
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return ''; // Fall back to normal rendering
			}
			
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $data['data'][0] ) ) {
				return ''; // Fall back to normal rendering
			}
			
			$case_data = $data['data'][0];
			
			// Enqueue gallery assets for JavaScript functionality
			Asset_Manager::enqueue_gallery_assets();
			
			// Generate fast case details HTML
			return self::generate_fast_case_html( $case_data, $procedure_title );
			
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Fast case rendering failed: ' . $e->getMessage() );
			}
			return ''; // Fall back to normal rendering
		}
	}

	/**
	 * Generate HTML for fast case details rendering
	 */
	private static function generate_fast_case_html( array $case_data, string $procedure_title = '' ): string {
		$case_id = esc_html( $case_data['id'] ?? '' );
		$images = $case_data['images'] ?? [];
		$before_images = array_filter( $images, fn( $img ) => ( $img['type'] ?? '' ) === 'before' );
		$after_images = array_filter( $images, fn( $img ) => ( $img['type'] ?? '' ) === 'after' );
		
		$age = ! empty( $case_data['age'] ) ? esc_html( $case_data['age'] ) : '';
		$gender = ! empty( $case_data['gender'] ) ? esc_html( $case_data['gender'] ) : '';
		$notes = ! empty( $case_data['notes'] ) ? wp_kses_post( $case_data['notes'] ) : '';
		
		ob_start();
		?>
		<div class="brag-book-gallery-wrapper">
			<div class="brag-book-gallery-case-detail-fast">
				<div class="brag-book-gallery-case-detail-header">
					<div class="brag-book-gallery-case-detail-nav">
						<a href="javascript:history.back()" class="brag-book-gallery-back-button">← Back to Gallery</a>
					</div>
					<h1>Case #<?php echo $case_id; ?></h1>
					<?php if ( ! empty( $procedure_title ) ): ?>
						<div class="brag-book-gallery-procedure-name"><?php echo esc_html( str_replace( '-', ' ', $procedure_title ) ); ?></div>
					<?php endif; ?>
					<?php if ( $age || $gender ): ?>
						<div class="brag-book-gallery-case-meta">
							<?php if ( $age ): ?><span class="age"><?php echo $age; ?> years old</span><?php endif; ?>
							<?php if ( $gender ): ?><span class="gender"><?php echo $gender; ?></span><?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
				
				<div class="brag-book-gallery-case-images">
					<?php if ( ! empty( $before_images ) ): ?>
						<div class="brag-book-gallery-image-section">
							<h3>Before</h3>
							<div class="brag-book-gallery-image-grid">
								<?php foreach ( $before_images as $image ): ?>
									<img src="<?php echo esc_url( $image['url'] ?? '' ); ?>" 
										 alt="Before - Case <?php echo $case_id; ?>" 
										 loading="lazy">
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
					
					<?php if ( ! empty( $after_images ) ): ?>
						<div class="brag-book-gallery-image-section">
							<h3>After</h3>
							<div class="brag-book-gallery-image-grid">
								<?php foreach ( $after_images as $image ): ?>
									<img src="<?php echo esc_url( $image['url'] ?? '' ); ?>" 
										 alt="After - Case <?php echo $case_id; ?>" 
										 loading="lazy">
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
				
				<?php if ( $notes ): ?>
					<div class="brag-book-gallery-case-notes">
						<h3>Case Notes</h3>
						<div class="notes-content"><?php echo $notes; ?></div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
