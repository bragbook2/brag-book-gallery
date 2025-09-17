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
 * - MyFavorites functionality with localStorage and WordPress data synchronization
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
 * - Intelligent caching of WordPress data and configuration
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

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Shortcodes;

use BRAGBookGallery\Includes\Resources\Asset_Manager;
use BRAGBookGallery\Includes\Shortcodes\Sidebar_Handler;
use BRAGBookGallery\Includes\Shortcodes\Cases_Handler;
use BRAGBookGallery\Includes\Shortcodes\Case_Handler;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Core\Trait_Api;

final class Gallery_Handler {
	use Trait_Api;

	/**
	 * Initialize the gallery handler
	 *
	 * Sets up shortcode registration.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_shortcode( 'brag_book_gallery', [ self::class, 'handle' ] );
	}

	public static function handle( array $atts ): string {
		// Ensure attributes are in array format with type validation
		$atts = is_array( $atts ) ? $atts : [];

		$validated_atts = self::validate_and_sanitize_shortcode_attributes( $atts );

		// Auto-detect context and show appropriate view
		return self::auto_detect_and_render( $validated_atts );
	}

	/**
	 * Auto-detect the current context and render appropriate content
	 *
	 * Uses a switch-based approach to determine the correct view based on context:
	 * - Single case pages: Show single case view
	 * - Procedure taxonomy pages: Show full gallery filtered to current procedure
	 * - Favorites pages: Show full gallery with favorites context
	 * - Regular pages: Show full gallery with sidebar
	 *
	 * @param array $validated_atts Validated shortcode attributes.
	 *
	 * @return string Rendered content.
	 */
	private static function auto_detect_and_render( array $validated_atts ): string {
		// Handle explicit view parameter
		if ( ! empty( $validated_atts['view'] ) && 'myfavorites' === $validated_atts['view'] ) {
			$validated_atts['is_favorites_page'] = true;

			return self::render_full_gallery( $validated_atts );
		}

		// Determine the current context
		$context = self::determine_page_context( $validated_atts );

		switch ( $context['type'] ) {
			case 'single_case':
				// Use full gallery wrapper but delegate main content to Case_Handler
				$validated_atts['case_id']      = $context['case_id'];
				$validated_atts['is_case_view'] = true;

				return self::render_full_gallery( $validated_atts );

			case 'taxonomy_procedure':
				// Add procedure filter and show full gallery
				$validated_atts['procedure_slug']   = $context['procedure_slug'];
				$validated_atts['current_taxonomy'] = $context['taxonomy_term'];

				return self::render_full_gallery( $validated_atts );

			case 'favorites':
				$validated_atts['is_favorites_page'] = true;

				return self::render_full_gallery( $validated_atts );

			case 'cases_only':
				return Cases_Handler::handle( $validated_atts );

			case 'procedure_view':
				return self::handle_procedure_view( $validated_atts );

			case 'full_gallery':
			default:
				return self::render_full_gallery( $validated_atts );
		}
	}

	/**
	 * Determine the current page context
	 *
	 * @param array $validated_atts Validated shortcode attributes.
	 *
	 * @return array Context information with type and relevant data.
	 */
	private static function determine_page_context( array $validated_atts ): array {
		// 1. Check if single case is explicitly requested
		$case_id = $validated_atts['case_id'] ?? '';
		if ( ! empty( $case_id ) ) {
			return [
				'type'    => 'single_case',
				'case_id' => $case_id,
			];
		}

		// 2. Check if we're on a single case post page
		if ( is_singular( \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES ) ) {
			$post_id = get_the_ID();
			$case_id = get_post_meta( $post_id, 'brag_book_gallery_api_id', true ) ?: get_post_meta( $post_id, '_case_api_id', true );

			return [
				'type'    => 'single_case',
				'case_id' => $case_id,
			];
		}

		// 2.5. Check for case ID in URL structure (e.g., /gallery/procedure/case-id/)
		$case_id_from_query = get_query_var( 'case_id' );

		// Fallback: check $_GET directly if query var isn't working
		if ( empty( $case_id_from_query ) && ! empty( $_GET['case_id'] ) ) {
			$case_id_from_query = sanitize_text_field( $_GET['case_id'] );
		}

		if ( ! empty( $case_id_from_query ) ) {
			return [
				'type'    => 'single_case',
				'case_id' => $case_id_from_query,
			];
		}

		// 2.6. Fallback: Check for case ID in URL structure using manual parsing
		$case_id_from_url = self::extract_case_id_from_url();
		if ( ! empty( $case_id_from_url ) ) {
			return [
				'type'    => 'single_case',
				'case_id' => $case_id_from_url,
			];
		}

		// 3. Check if we're on a procedure taxonomy page
		if ( is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			$current_term = get_queried_object();
			if ( $current_term && ! is_wp_error( $current_term ) ) {
				return [
					'type'           => 'taxonomy_procedure',
					'procedure_slug' => $current_term->slug,
					'taxonomy_term'  => $current_term,
				];
			}
		}

		// 4. Check if favorites page is requested
		// 4.1. Check if current page slug is "myfavorites"
		$current_page = get_queried_object();
		if ( $current_page && isset( $current_page->post_name ) && 'myfavorites' === $current_page->post_name ) {
			return [
				'type' => 'favorites',
			];
		}

		// 4.2. Check via query parameter for manual overrides
		if ( isset( $_GET['favorites'] ) && $_GET['favorites'] ) {
			return [
				'type' => 'favorites',
			];
		}

		// 4.3. Fallback: Check URL path for myfavorites (for direct URL access)
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, '/myfavorites' ) !== false ) {
			return [
				'type' => 'favorites',
			];
		}

		// 5. Check if cases-only view is requested
		$show_cases_only = $validated_atts['cases_only'] ?? false;
		if ( $show_cases_only ) {
			return [
				'type' => 'cases_only',
			];
		}

		// 6. Check if procedure view is explicitly requested
		$view = $validated_atts['view'] ?? '';
		if ( 'procedure' === $view ) {
			return [
				'type' => 'procedure_view',
			];
		}

		// 7. Default: Full gallery
		return [
			'type' => 'full_gallery',
		];
	}

	/**
	 * Extract case ID from URL structure
	 *
	 * Checks for URLs like /gallery/procedure-slug/case-id/ and extracts the case ID.
	 *
	 * @return string Case ID if found, empty string otherwise.
	 * @since 3.0.0
	 */
	private static function extract_case_id_from_url(): string {
		// Get current URL path
		$current_url = $_SERVER['REQUEST_URI'] ?? '';
		$url_path    = parse_url( $current_url, PHP_URL_PATH );

		if ( empty( $url_path ) ) {
			return '';
		}

		// Get gallery page slug
		$gallery_page_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );

		// Pattern: /gallery-slug/procedure-slug/case-id/
		$pattern = '#/' . preg_quote( $gallery_page_slug, '#' ) . '/([^/]+)/([^/]+)/?$#';

		if ( preg_match( $pattern, $url_path, $matches ) ) {
			$potential_case_id = $matches[2] ?? '';

			// Validate that it looks like a case ID (numeric)
			if ( is_numeric( $potential_case_id ) ) {
				return $potential_case_id;
			}
		}

		return '';
	}

	/**
	 * Render the full gallery with sidebar (default view)
	 *
	 * @param array $validated_atts Validated shortcode attributes.
	 *
	 * @return string Rendered gallery content.
	 */
	private static function render_full_gallery( array $validated_atts ): string {
		// Extract context information from attributes
		$current_taxonomy  = $validated_atts['current_taxonomy'] ?? null;
		$filter_procedure  = $validated_atts['procedure_slug'] ?? '';
		$is_favorites_page = $validated_atts['is_favorites_page'] ?? false;
		$is_case_view      = $validated_atts['is_case_view'] ?? false;
		$case_id           = $validated_atts['case_id'] ?? '';

		// If we have a procedure slug but no taxonomy object, get it
		if ( ! empty( $filter_procedure ) && ! $current_taxonomy ) {
			$current_taxonomy = get_term_by( 'slug', $filter_procedure, Taxonomies::TAXONOMY_PROCEDURES );
		}

		// Validate configuration and mode with comprehensive error handling
		$validation = self::validate_gallery_configuration( $validated_atts );
		if ( $validation['error'] ) {
			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html( $validation['message'] ?? __( 'Gallery configuration error.', 'brag-book-gallery' ) )
			);
		}

		// Enqueue required assets - JavaScript has smart detection for server-rendered case pages
		Asset_Manager::enqueue_gallery_assets();

		// Get API credentials from mode-based arrays for JavaScript localization
		$api_tokens_option           = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids_option = get_option( 'brag_book_gallery_website_property_id', [] );
		$mode                        = 'default'; // Mode manager removed - default to 'default' mode

		$api_token           = '';
		$website_property_id = '';

		if ( is_array( $api_tokens_option ) && isset( $api_tokens_option[ $mode ] ) ) {
			$api_token = $api_tokens_option[ $mode ];
		}

		if ( is_array( $website_property_ids_option ) && isset( $website_property_ids_option[ $mode ] ) ) {
			$website_property_id = $website_property_ids_option[ $mode ];
		}

		// Localize script with API configuration for favorites functionality
		Asset_Manager::localize_gallery_script(
			[
				'api_token'           => $api_token,
				'website_property_id' => $website_property_id,
			],
			[], // Empty sidebar data for now - will be populated by JavaScript
			[]  // Empty cases data - will be populated by JavaScript
		);

		// Ensure consultation form configuration is available
		Asset_Manager::ensure_consultation_form_config();

		// Get complete cases dataset - placeholder for JavaScript population
		$all_cases_data = [ 'data' => [] ];

		// Add inline nudity acceptance script
		Asset_Manager::add_nudity_acceptance_script();

		// Generate and return gallery HTML
		$output = self::render_gallery_html(
			$all_cases_data,
			$filter_procedure,
			'',
			$is_favorites_page,
			$current_taxonomy,
			$is_case_view,
			(string) $case_id
		);

		return $output;
	}

	private static function validate_gallery_configuration( array $atts ): array {
		// Validate and sanitize shortcode attributes first
		$validated_atts = self::validate_and_sanitize_shortcode_attributes( $atts );

		// For now, skip mode validation since we're using WordPress-native approach
		// Gallery configuration is valid - return config data
		$config = [
			'error'  => false,
			'config' => $validated_atts,
		];

		return $config;
	}


	private static function render_gallery_html( array $all_cases_data = [], string $initial_procedure = '', string $initial_case_id = '', bool $is_favorites_page = false, \WP_Term $current_taxonomy = null, bool $is_case_view = false, string $case_id = '' ): string {
		// Initialize output buffering for complete HTML capture
		ob_start();

		// Extract and validate URL components with security
		$current_url = get_permalink();
		$base_path   = self::extract_safe_base_path( $current_url );

		// Generate CSS classes with conditional font handling
		$wrapper_class = self::generate_wrapper_classes();

		// Generate secure data attributes for JavaScript initialization
		$data_attributes  = self::prepare_data_attributes( $base_path, $initial_procedure, $initial_case_id, $is_favorites_page );
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
					<svg xmlns="http://www.w3.org/2000/svg" height="20px"
						 viewBox="0 -960 960 960" width="20px"
						 fill="currentColor">
						<path
							d="M192-360v-72h576v72H192Zm0-168v-72h576v72H192Z"/>
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
					<span id="mobile-search-hint" class="sr-only">
						<?php
						echo esc_html__( 'Start typing to search for procedures: ', 'brag-book-gallery' );
						echo self::generate_search_hint_links();
						?>
					</span>
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
						<h2 class="brag-book-gallery-sidebar-title">
							<?php esc_html_e( 'Choose a Gallery', 'brag-book-gallery' ); ?>
						</h2>
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
						<span id="search-hint" class="sr-only">
							<?php
							echo esc_html__( 'Start typing to search for procedures: ', 'brag-book-gallery' );
							echo self::generate_search_hint_links();
							?>
						</span>
						<div class="brag-book-gallery-search-dropdown"
							 id="search-results" role="listbox"
							 aria-label="Search results" aria-live="polite">
							<!-- Results will be populated here --></div>
					</div>

					<aside class="brag-book-gallery-nav" role="group"
						   aria-label="Procedure filters">
						<?php
						// Generate sidebar content using shortcode
						echo do_shortcode( '[brag_book_gallery_sidebar]' );
						?>
					</aside>

					<!-- My Favorites Button -->
					<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) : ?>
						<div class="brag-book-gallery-favorites-link-wrapper">
							<?php
							$favorites_url = self::get_favorites_page_url();
							?>
							<a href="<?php echo esc_url( $favorites_url ); ?>"
							   class="brag-book-gallery-favorites-link"
							   data-action="show-favorites">
								<span
									class="brag-book-gallery-favorites-link-label"><strong><?php esc_html_e( 'My', 'brag-book-gallery' ); ?></strong><?php esc_html_e( 'Favorites', 'brag-book-gallery' ); ?></span>
								<span
									class="brag-book-gallery-favorites-link-count"
									data-favorites-count>(0)</span>
							</a>
						</div>
					<?php endif; ?>

					<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_consultation_enabled() ): ?>
						<div class="brag-book-gallery-consultation-text">
							<strong>
								<?php esc_html_e( 'Ready for the next step?', 'brag-book-gallery' ); ?>
							</strong>
							<span>
								<?php esc_html_e( 'Contact us to request your consultation.', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<button
							class="brag-book-gallery-button brag-book-gallery-button--full"
							data-action="request-consultation"
						>
							<?php esc_html_e( 'Request a Consultation', 'brag-book-gallery' ); ?>
						</button>
					<?php endif; ?>
					<div class="brag-book-gallery-powered-by">
						<?php esc_html_e( 'Powered by', 'brag-book-gallery' ); ?>
						<a href="https://bragbookgallery.com/"
						   class="brag-book-gallery-powered-by-link"
						   target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'BRAG book Gallery', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>

				<div class="brag-book-gallery-main-content" role="region"
					 aria-label="Gallery content"
					 id="gallery-content"<?php echo $is_favorites_page ? ' data-favorites-page="true"' : ''; ?>>
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
					// Check if we're showing a single case view
					if ( $is_case_view && ! empty( $case_id ) ) {
						// Track the case view for server-side rendering
						self::track_case_view_server_side( $case_id );

						// Delegate main content to Case_Handler for case detail view
						$case_handler = new Case_Handler();
						echo $case_handler->render_case_content_only( $case_id );
					} elseif ( $is_favorites_page ) {
						echo do_shortcode( '[brag_book_gallery_favorites]' );
					} elseif ( $current_taxonomy ) {
						// Show procedure-specific content
						?>
						<h2 class="brag-book-gallery-content-title">
							<strong><?php echo esc_html( $current_taxonomy->name ); ?></strong>
							Before &amp; After Gallery
						</h2>

						<!-- Filter controls will be added here by JavaScript -->
						<div class="brag-book-gallery-controls">
							<div class="brag-book-gallery-controls-left">
								<details
									class="brag-book-gallery-filter-dropdown"
									id="procedure-filters-details"
									data-initialized="true">
									<summary
										class="brag-book-gallery-filter-dropdown__toggle">
										<svg xmlns="http://www.w3.org/2000/svg"
											 height="20px"
											 viewBox="0 -960 960 960"
											 width="20px" fill="currentColor">
											<path
												d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"></path>
										</svg>
										<span>Filters</span></summary>
									<div
										class="brag-book-gallery-filter-dropdown__panel">
										<div
											class="brag-book-gallery-filter-content">
											<div
												class="brag-book-gallery-filter-section">
												<div
													id="brag-book-gallery-filters">
													<details
														class="brag-book-gallery-filter">
														<summary
															class="brag-book-gallery-filter-label">
															<span
																class="brag-book-gallery-filter-label__name">Age</span>
															<svg
																class="brag-book-gallery-filter-label__arrow"
																width="16"
																height="16"
																viewBox="0 0 12 12"
																fill="none"
																xmlns="http://www.w3.org/2000/svg">
																<path
																	d="M3 4.5L6 7.5L9 4.5"
																	stroke="currentColor"
																	stroke-width="1.5"
																	stroke-linecap="round"
																	stroke-linejoin="round"></path>
															</svg>
														</summary>
														<ul class="brag-book-gallery-filter-options">
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-age-18-24"
																	value="18-24"
																	data-filter-type="age">
																<label
																	for="procedure-filter-age-18-24">18-24</label>
															</li>
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-age-25-34"
																	value="25-34"
																	data-filter-type="age">
																<label
																	for="procedure-filter-age-25-34">25-34</label>
															</li>
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-age-35-44"
																	value="35-44"
																	data-filter-type="age">
																<label
																	for="procedure-filter-age-35-44">35-44</label>
															</li>
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-age-45-54"
																	value="45-54"
																	data-filter-type="age">
																<label
																	for="procedure-filter-age-45-54">45-54</label>
															</li>
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-age-55-64"
																	value="55-64"
																	data-filter-type="age">
																<label
																	for="procedure-filter-age-55-64">55-64</label>
															</li>
														</ul>
													</details>
													<details
														class="brag-book-gallery-filter">
														<summary
															class="brag-book-gallery-filter-label">
															<span
																class="brag-book-gallery-filter-label__name">Gender</span>
															<svg
																class="brag-book-gallery-filter-label__arrow"
																width="16"
																height="16"
																viewBox="0 0 12 12"
																fill="none"
																xmlns="http://www.w3.org/2000/svg">
																<path
																	d="M3 4.5L6 7.5L9 4.5"
																	stroke="currentColor"
																	stroke-width="1.5"
																	stroke-linecap="round"
																	stroke-linejoin="round"></path>
															</svg>
														</summary>
														<ul class="brag-book-gallery-filter-options">
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-gender-female"
																	value="female"
																	data-filter-type="gender">
																<label
																	for="procedure-filter-gender-female">Female</label>
															</li>
														</ul>
													</details>
													<details
														class="brag-book-gallery-filter">
														<summary
															class="brag-book-gallery-filter-label">
															<span
																class="brag-book-gallery-filter-label__name">Ethnicity</span>
															<svg
																class="brag-book-gallery-filter-label__arrow"
																width="16"
																height="16"
																viewBox="0 0 12 12"
																fill="none"
																xmlns="http://www.w3.org/2000/svg">
																<path
																	d="M3 4.5L6 7.5L9 4.5"
																	stroke="currentColor"
																	stroke-width="1.5"
																	stroke-linecap="round"
																	stroke-linejoin="round"></path>
															</svg>
														</summary>
														<ul class="brag-book-gallery-filter-options">
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-ethnicity-hispanic-or-latino"
																	value="hispanic or latino"
																	data-filter-type="ethnicity">
																<label
																	for="procedure-filter-ethnicity-hispanic-or-latino">Hispanic
																	or
																	latino</label>
															</li>
															<li class="brag-book-gallery-filter-option">
																<input
																	type="checkbox"
																	id="procedure-filter-ethnicity-white"
																	value="white"
																	data-filter-type="ethnicity">
																<label
																	for="procedure-filter-ethnicity-white">White</label>
															</li>
														</ul>
													</details>
												</div>
											</div>
										</div>
										<div
											class="brag-book-gallery-filter-actions">
											<button
												class="brag-book-gallery-button brag-book-gallery-button--apply"
												onclick="applyProcedureFilters()">
												Apply Filters
											</button>
											<button
												class="brag-book-gallery-button brag-book-gallery-button--clear"
												onclick="clearProcedureFilters()">
												Clear All
											</button>
										</div>
									</div>
								</details>
							</div>
							<div class="brag-book-gallery-grid-selector">
								<span
									class="brag-book-gallery-grid-label">View:</span>
								<div class="brag-book-gallery-grid-buttons">
									<button class="brag-book-gallery-grid-btn"
											data-columns="2"
											onclick="updateGridLayout(2)"
											aria-label="View in 2 columns">
										<svg width="16" height="16"
											 viewBox="0 0 16 16"
											 fill="currentColor"
											 aria-hidden="true">
											<rect x="1" y="1" width="6"
												  height="6"></rect>
											<rect x="9" y="1" width="6"
												  height="6"></rect>
											<rect x="1" y="9" width="6"
												  height="6"></rect>
											<rect x="9" y="9" width="6"
												  height="6"></rect>
										</svg>
										<span class="sr-only">2 Columns</span>
									</button>
									<button
										class="brag-book-gallery-grid-btn active"
										data-columns="3"
										onclick="updateGridLayout(3)"
										aria-label="View in 3 columns">
										<svg width="16" height="16"
											 viewBox="0 0 16 16"
											 fill="currentColor"
											 aria-hidden="true">
											<rect x="1" y="1" width="4"
												  height="4"></rect>
											<rect x="6" y="1" width="4"
												  height="4"></rect>
											<rect x="11" y="1" width="4"
												  height="4"></rect>
											<rect x="1" y="6" width="4"
												  height="4"></rect>
											<rect x="6" y="6" width="4"
												  height="4"></rect>
											<rect x="11" y="6" width="4"
												  height="4"></rect>
											<rect x="1" y="11" width="4"
												  height="4"></rect>
											<rect x="6" y="11" width="4"
												  height="4"></rect>
											<rect x="11" y="11" width="4"
												  height="4"></rect>
										</svg>
										<span class="sr-only">3 Columns</span>
									</button>
								</div>
							</div>
						</div>

						<!-- Gallery sections -->
						<div class="brag-book-gallery-sections"
							 id="gallery-sections">
							<div class="brag-book-gallery-section"
								 aria-label="Filtered Gallery Results">
								<div
									class="brag-book-gallery-case-grid masonry-layout"
									data-columns="3">
									<?php
									// Load cases from WordPress posts for this taxonomy
									echo self::render_taxonomy_cases( $current_taxonomy );
									?>
								</div>
							</div>
						</div>
						<?php
					} else {
						// Show default gallery content
						$default_landing_text = '<h2>Go ahead, browse our before & afters... visualize your possibilities.</h2>' . "\n" .
												'<p>Our gallery is full of our real patients. Keep in mind results vary.</p>';
						$landing_page_text    = get_option( 'brag_book_gallery_landing_page_text', $default_landing_text );

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
									afters...</strong>visualize your
								possibilities
							</h2>

							<!-- Gallery sections -->
							<div class="brag-book-gallery-sections"
								 id="gallery-sections">
								<!-- Cases grid section -->
								<div class="brag-book-gallery-section"
									 aria-label="Gallery Cases">
									<h3 class="brag-book-gallery-title">
										Gallery</h3>
									<div
										class="brag-book-gallery-procedure-cases"
										data-procedure="all"
										data-loaded="false"
										data-initial-load="true"
										role="region"
										aria-label="Procedure cases">
										<!-- Cases will be loaded here by JavaScript -->
									</div>
								</div>
							</div>
							<?php
						}
					}
					?>
				</div>
			</div>

			<!-- Share dropdown will be created dynamically by JavaScript -->

			<?php if ( \BRAGBookGallery\Includes\Core\Settings_Helper::is_consultation_enabled() ): ?>
				<dialog class="brag-book-gallery-dialog"
						id="consultationDialog">
					<div class="brag-book-gallery-dialog-content">
						<div class="brag-book-gallery-dialog-header">
							<h2 class="brag-book-gallery-dialog-title">
								Consultation
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
									   id="email"
									   placeholder="Enter email address"
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
								<textarea
									class="brag-book-gallery-form-textarea"
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
							<svg class="brag-book-gallery-dialog-logo"
								 xmlns="http://www.w3.org/2000/svg"
								 viewBox="0 0 900 180">
								<path fill="#ff595c"
									  d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"></path>
								<path fill="#ff595c"
									  d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"></path>
								<path fill="#121827"
									  d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"></path>
								<path fill="#121827"
									  d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"></path>
								<path fill="#121827"
									  d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"></path>
								<path fill="#121827"
									  d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"></path>
								<path fill="#121827"
									  d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"></path>
								<path fill="#121827"
									  d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"></path>
								<path fill="#121827"
									  d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"></path>
								<path fill="#121827"
									  d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"></path>
								<path fill="#121827"
									  d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"></path>
								<path fill="#121827"
									  d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"></path>
								<path fill="#121827"
									  d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"></path>
								<path fill="#ff595c"
									  d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"></path>
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
						<p class="brag-book-gallery-dialog-subtitle">Fill out
							the
							form below and we'll send your favorited images.</p>
						<form class="brag-book-gallery-favorites-form"
							  data-form="favorites">
							<div class="brag-book-gallery-form-notification"
								 style="display: none;"></div>
							<div class="brag-book-gallery-form-group">
								<label class="brag-book-gallery-form-label"
									   for="fav-name">Full Name *</label>
								<input type="text"
									   class="brag-book-gallery-form-input"
									   id="fav-name"
									   placeholder="Enter full name"
									   name="fav-name" required>
							</div>
							<div class="brag-book-gallery-form-group">
								<label class="brag-book-gallery-form-label"
									   for="fav-email">Email Address *</label>
								<input type="email"
									   class="brag-book-gallery-form-input"
									   id="fav-email"
									   placeholder="Enter email address"
									   name="fav-email" required>
							</div>
							<div class="brag-book-gallery-form-group">
								<label class="brag-book-gallery-form-label"
									   for="fav-phone">Phone *</label>
								<input type="tel"
									   class="brag-book-gallery-form-input"
									   id="fav-phone"
									   placeholder="(123) 456-7890"
									   name="fav-phone"
									   required
									   pattern="\([0-9]{3}\) [0-9]{3}-[0-9]{4}"
									   maxlength="14"
									   data-phone-format="true">
							</div>
							<input type="hidden" name="fav-case-id" value="">
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
		$output = ob_get_clean();

		// Clean up any remaining whitespace issues that might cause wpautop problems
		$output = trim( $output );

		// Remove any accidental empty lines or extra whitespace between PHP closing/opening tags
		$output = preg_replace( '/\?\>\s+\<\?php/', '?><?php', $output );

		// Clean up excessive whitespace but maintain readable formatting
		$output = preg_replace( '/\n\s*\n\s*\n/', "\n\n", $output );
		$output = trim( $output );

		// Return clean HTML (wpautop prevention handled by block filter)
		return $output;
	}

	private static function validate_and_sanitize_shortcode_attributes( array $raw_atts ): array|false {

		// Define default attributes with proper types.
		$defaults = array(
			'case_id'             => '',
			'cases_only'          => false,
			'procedure_slug'      => '',
			'data_case_id'        => '',
			'data_procedure_slug' => '',
			'view'                => '',
			'limit'               => 20,
			'columns'             => 3,
		);

		// Apply WordPress shortcode attribute parsing with defaults
		$atts = shortcode_atts( $defaults, $raw_atts, 'brag_book_gallery' );

		// Validate and sanitize each attribute
		return array(
			'case_id'             => sanitize_text_field( $atts['case_id'] ),
			'cases_only'          => filter_var( $atts['cases_only'], FILTER_VALIDATE_BOOLEAN ),
			'procedure_slug'      => sanitize_text_field( $atts['procedure_slug'] ),
			'data_case_id'        => sanitize_text_field( $atts['data_case_id'] ),
			'data_procedure_slug' => sanitize_text_field( $atts['data_procedure_slug'] ),
			'view'                => sanitize_text_field( $atts['view'] ),
			'limit'               => max( 1, min( 200, absint( $atts['limit'] ) ) ),
			'columns'             => max( 1, min( 6, absint( $atts['columns'] ) ) ),
		);
	}


	private static function extract_safe_base_path( string|false $current_url ): string {
		if ( false === $current_url || empty( trim( $current_url ) ) ) {
			return '/';
		}

		$base_path = parse_url( $current_url, PHP_URL_PATH );

		return $base_path ?: '/';
	}

	private static function generate_wrapper_classes(): string {
		$wrapper_class = 'brag-book-gallery-wrapper';

		// Check custom font setting with default fallback
		$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );

		if ( 'yes' !== $use_custom_font ) {
			$wrapper_class .= ' disable-custom-font';
		}

		// Check filter counts setting
		$show_filter_counts = get_option( 'brag_book_gallery_show_filter_counts', true );

		if ( ! $show_filter_counts ) {
			$wrapper_class .= ' brag-book-gallery-hide-filter-counts';
		}

		return $wrapper_class;
	}

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
	 * Get the My Favorites page URL using WordPress permalinks
	 *
	 * @return string Favorites page URL, falls back to path-based URL if page not found
	 * @since 3.0.0
	 */
	private static function get_favorites_page_url(): string {
		// Get the gallery page slug to find the parent page
		$gallery_slug = self::get_gallery_page_slug();

		// Find the gallery parent page
		$gallery_page = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'name'           => $gallery_slug,
			'posts_per_page' => 1,
		) );

		if ( empty( $gallery_page ) ) {
			// Fallback to hardcoded path if gallery page not found
			return '/' . ltrim( $gallery_slug, '/' ) . '/myfavorites';
		}

		$gallery_page_id = $gallery_page[0]->ID;

		// Look for the favorites page as child of gallery page
		$favorites_page = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'post_parent'    => $gallery_page_id,
			'name'           => 'myfavorites',
			'posts_per_page' => 1,
		) );

		if ( ! empty( $favorites_page ) ) {
			// Use WordPress permalink if page exists
			return get_permalink( $favorites_page[0]->ID );
		}

		// Fallback to hardcoded path if favorites page not found
		return '/' . ltrim( $gallery_slug, '/' ) . '/myfavorites';
	}

	private static function render_case_details_fast( string $case_id, string $procedure_title = '' ): string {
		try {
			// Get API configuration
			$api_tokens           = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
				return ''; // Fall back to normal rendering
			}

			$api_token           = $api_tokens[0];
			$website_property_id = (int) $website_property_ids[0];

			// Initialize case data
			$case_data = null;

			// If not in cache, make API call
			if ( empty( $case_data ) ) {
				// Make direct API call with short timeout for speed
				$api_base_url = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );
				$api_url      = sprintf( '%s/api/plugin/combine/cases/%s',
					$api_base_url,
					urlencode( $case_id )
				);

				// Prepare request body - API expects parameters wrapped in 'items'
				$body = [
					'items' => [
						'apiToken'          => $api_token,
						'websitePropertyId' => $website_property_id,
					],
				];

				$response = wp_remote_post( $api_url, [
					'timeout' => 5, // Fast timeout for initial page loads
					'headers' => [
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					],
					'body'    => wp_json_encode( $body ),
				] );

				if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
					return ''; // Fall back to normal rendering
				}

				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( empty( $data['data'][0] ) ) {
					return ''; // Fall back to normal rendering
				}

				$case_data = $data['data'][0];

				// Cache the case data for future use (caching logic handled by API)
			}

			// Enqueue gallery assets for JavaScript functionality
			Asset_Manager::enqueue_gallery_assets();

			// Generate fast case details HTML
			$output = self::generate_fast_case_html( $case_data, $procedure_title );

			return $output;

		} catch ( \Exception $e ) {
			return ''; // Fall back to normal rendering
		}
	}

	private static function generate_fast_case_html( array $case_data, string $procedure_title = '' ): string {
		$case_id       = esc_html( $case_data['id'] ?? '' );
		$images        = $case_data['images'] ?? [];
		$before_images = array_filter( $images, fn( $img ) => ( $img['type'] ?? '' ) === 'before' );
		$after_images  = array_filter( $images, fn( $img ) => ( $img['type'] ?? '' ) === 'after' );

		$age    = ! empty( $case_data['age'] ) ? esc_html( $case_data['age'] ) : '';
		$gender = ! empty( $case_data['gender'] ) ? esc_html( $case_data['gender'] ) : '';
		$notes  = ! empty( $case_data['notes'] ) ? wp_kses_post( $case_data['notes'] ) : '';

		ob_start();
		?>
		<div class="brag-book-gallery-wrapper">
			<div class="brag-book-gallery-case-detail-fast">
				<div class="brag-book-gallery-case-detail-header">
					<div class="brag-book-gallery-case-detail-nav">
						<a href="javascript:history.back()"
						   class="brag-book-gallery-back-button">← Back to
							Gallery</a>
					</div>
					<h1>Case #<?php echo $case_id; ?></h1>
					<?php if ( ! empty( $procedure_title ) ): ?>
						<div
							class="brag-book-gallery-procedure-name"><?php echo esc_html( str_replace( '-', ' ', $procedure_title ) ); ?></div>
					<?php endif; ?>
					<?php if ( $age || $gender ): ?>
						<div class="brag-book-gallery-case-meta">
							<?php if ( $age ): ?><span
								class="age"><?php echo $age; ?> years
								old</span><?php endif; ?>
							<?php if ( $gender ): ?><span
								class="gender"><?php echo $gender; ?></span><?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="brag-book-gallery-case-images">
					<?php if ( ! empty( $before_images ) ): ?>
						<div class="brag-book-gallery-image-section">
							<h3>Before</h3>
							<div class="brag-book-gallery-image-grid">
								<?php foreach ( $before_images as $image ): ?>
									<img
										src="<?php echo esc_url( $image['url'] ?? '' ); ?>"
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
									<img
										src="<?php echo esc_url( $image['url'] ?? '' ); ?>"
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

	private static function handle_procedure_view( array $validated_atts ): string {
		// Check if we're on a taxonomy archive page
		$procedure_slug = '';
		$procedure_term = null;

		if ( is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			$current_term = get_queried_object();
			if ( $current_term && ! is_wp_error( $current_term ) ) {
				$procedure_slug = $current_term->slug;
				$procedure_term = $current_term;
			}
		} elseif ( ! empty( $validated_atts['procedure_slug'] ) ) {
			// Procedure specified in shortcode attributes
			$procedure_slug = $validated_atts['procedure_slug'];
			$procedure_term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
		}

		if ( ! $procedure_term || is_wp_error( $procedure_term ) ) {
			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html__( 'Procedure view requires being on a procedure taxonomy page or providing a procedure_slug parameter.', 'brag-book-gallery' )
			);
		}

		$procedure_name        = $procedure_term->name;
		$procedure_description = get_term_meta( $procedure_term->term_id, 'description', true );

		// Enqueue gallery assets
		Asset_Manager::enqueue_gallery_assets();

		// Start output buffering
		ob_start();
		?>
		<!-- Procedure Template View -->
		<div class="brag-book-gallery-procedure-template"
			 data-view="procedure"
			 data-procedure-slug="<?php echo esc_attr( $procedure_slug ); ?>">

			<?php if ( ! empty( $procedure_name ) ) : ?>
				<header class="brag-book-gallery-procedure-header">
					<h1 class="brag-book-gallery-procedure-title">
						<?php echo esc_html( $procedure_name ); ?>
					</h1>
					<?php if ( ! empty( $procedure_description ) ) : ?>
						<div class="brag-book-gallery-procedure-description">
							<?php echo wp_kses_post( $procedure_description ); ?>
						</div>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<!-- Gallery container for procedure cases -->
			<div class="brag-book-gallery-procedure-cases"
				 data-filter-procedure="<?php echo esc_attr( $procedure_slug ); ?>">
				<div class="brag-book-gallery-case-grid masonry-layout"
					 data-columns="3">
					<?php
					// Load cases from WordPress posts for this procedure
					echo self::render_taxonomy_cases( $procedure_term );
					?>
				</div>

				<?php
				// Add Load More pagination if there are more than 12 cases
				$total_cases = wp_count_posts( \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES )->publish;
				if ( $total_cases > 12 ) {
					$infinite_scroll = get_option( 'brag_book_gallery_infinite_scroll', 'no' );
					$button_style    = ( $infinite_scroll === 'yes' ) ? ' style="display: none;"' : '';
					?>
					<div class="brag-book-gallery-load-more-container">
						<button
							class="brag-book-gallery-button brag-book-gallery-button--load-more"<?php echo $button_style; ?>
							data-action="load-more"
							data-start-page="2"
							data-procedure-ids=""
							data-procedure-name="<?php echo esc_attr( $procedure_slug ); ?>"
							data-total-pages="<?php echo ceil( $total_cases / 12 ); ?>">
							<?php esc_html_e( 'Load More', 'brag-book-gallery' ); ?>
						</button>
					</div>
					<?php
				}
				?>
			</div>
		</div>

		<!-- Cases loaded directly from WordPress, no JavaScript initialization needed -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Render cases for taxonomy pages
	 *
	 * Gets WordPress posts for the current taxonomy and renders them
	 * as case cards using the Cases_Handler.
	 *
	 * @param \WP_Term $taxonomy Current taxonomy term.
	 *
	 * @return string HTML for taxonomy cases.
	 * @since 3.0.0
	 */
	private static function render_taxonomy_cases( \WP_Term $taxonomy ): string {

		$query_args = array(
			'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array(
				array(
					'taxonomy' => $taxonomy->taxonomy,
					'field'    => 'term_id',
					'terms'    => $taxonomy->term_id,
				),
			),
		);

		$posts = get_posts( $query_args );

		if ( empty( $posts ) ) {
			return '<div class="brag-book-gallery-no-cases" style="grid-column: 1/-1; text-align: center; padding: 2rem;">
				<p>No cases found for ' . esc_html( $taxonomy->name ) . '.</p>
			</div>';
		}

		// Check if current taxonomy has nudity using WordPress term meta (same as sidebar)
		$nudity_meta = get_term_meta( $taxonomy->term_id, 'nudity', true );
		$procedure_nudity = 'true' === $nudity_meta;


		// Use Cases_Handler to render the cases
		$cases_html = '';
		foreach ( $posts as $post ) {
			// Convert post to case data format
			$case_data = [
				'id'         => get_post_meta( $post->ID, 'case_id', true ) ?: $post->ID,
				'post_id'    => $post->ID,
				'images'     => get_post_meta( $post->ID, 'images', true ) ?: [],
				'age'        => get_post_meta( $post->ID, 'age', true ) ?: '',
				'gender'     => get_post_meta( $post->ID, 'gender', true ) ?: '',
				'ethnicity'  => get_post_meta( $post->ID, 'ethnicity', true ) ?: '',
				'height'     => get_post_meta( $post->ID, 'height', true ) ?: '',
				'weight'     => get_post_meta( $post->ID, 'weight', true ) ?: '',
				'notes'      => get_post_meta( $post->ID, 'notes', true ) ?: '',
				'procedures' => array_map( function ( $term ) {
					return is_object( $term ) ? $term->name : $term;
				}, wp_get_post_terms( $post->ID, $taxonomy->taxonomy ) ?: [] ),
			];


			// Ensure images is an array
			if ( ! is_array( $case_data['images'] ) ) {
				$case_data['images'] = [];
			}

			// Use Cases_Handler to render the WordPress case card
			$cases_html .= \BRAGBookGallery\Includes\Shortcodes\Cases_Handler::render_wordpress_case_card(
				$case_data,
				'single', // image_display_mode - use single mode for taxonomy pages
				$procedure_nudity, // procedure_nudity - from taxonomy meta
				'taxonomy' // procedure_context - we're on a taxonomy page
			);
		}

		// Note: Pagination moved outside of grid container in the calling method

		return $cases_html;
	}

	/**
	 * Generate search hint links from procedure taxonomy
	 *
	 * Creates a list of procedure page links for the search-hint span,
	 * using the same structure as the sidebar markup.
	 *
	 * @return string Formatted procedure links for search hint.
	 * @since 3.0.0
	 */
	private static function generate_search_hint_links(): string {
		// Get all child procedure terms (not categories)
		$procedure_terms = get_terms( [
			'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
			'parent'     => 0, // Get parent terms first
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 10, // Limit for search hint
		] );

		if ( is_wp_error( $procedure_terms ) || empty( $procedure_terms ) ) {
			return '';
		}

		$links = [];

		foreach ( $procedure_terms as $parent_term ) {
			// Get child procedures for this category
			$child_terms = get_terms( [
				'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
				'parent'     => $parent_term->term_id,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 5, // Limit child procedures
			] );

			if ( is_wp_error( $child_terms ) || empty( $child_terms ) ) {
				continue;
			}

			foreach ( $child_terms as $child_term ) {
				$procedure_url = get_term_link( $child_term );
				if ( is_wp_error( $procedure_url ) ) {
					continue;
				}

				$links[] = sprintf(
					'<a href="%s" class="brag-book-gallery-search-hint-link" data-procedure-slug="%s">%s (%d)</a>',
					esc_url( $procedure_url ),
					esc_attr( $child_term->slug ),
					esc_html( $child_term->name ),
					$child_term->count
				);

				// Limit total links in search hint
				if ( count( $links ) >= 8 ) {
					break 2;
				}
			}
		}

		return ! empty( $links ) ? implode( ', ', $links ) : '';
	}

	/**
	 * Track case view for server-side rendering
	 *
	 * Tracks case views when users visit case pages directly (not via AJAX).
	 * This ensures all case views are tracked regardless of how they're accessed.
	 *
	 * @param string $case_id The case ID to track
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private static function track_case_view_server_side( string $case_id ): void {
		// Skip tracking if case_id is empty
		if ( empty( $case_id ) ) {
			return;
		}

		// Skip tracking for bots and crawlers
		if ( self::is_bot_request() ) {
			return;
		}

		// Defer the tracking to avoid blocking page load
		// Use WordPress background processing or schedule for better performance
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( time(), 'brag_book_gallery_track_view', array( $case_id ) );
		} else {
			// Fallback: track immediately but asynchronously if possible
			self::track_view_async( $case_id );
		}
	}

	/**
	 * Check if the current request is from a bot or crawler
	 *
	 * @return bool True if request is from a bot, false otherwise
	 * @since 3.0.0
	 */
	private static function is_bot_request(): bool {
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		$bot_patterns = array(
			'bot',
			'crawl',
			'spider',
			'slurp',
			'search',
			'scanner',
			'Googlebot',
			'Bingbot',
			'facebookexternalhit',
			'Twitterbot'
		);

		foreach ( $bot_patterns as $pattern ) {
			if ( stripos( $user_agent, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Track view asynchronously
	 *
	 * Makes the tracking API call in a non-blocking way.
	 *
	 * @param string $case_id The case ID to track
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private static function track_view_async( string $case_id ): void {
		try {
			// Get API configuration
			$api_tokens           = get_option( 'brag_book_gallery_api_token', array() );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

			if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: API configuration missing for server-side view tracking' );
				}

				return;
			}

			// Use the first configured API token and property ID
			$api_token           = is_array( $api_tokens ) ? $api_tokens[0] : $api_tokens;
			$website_property_id = is_array( $website_property_ids ) ? $website_property_ids[0] : $website_property_ids;

			// Get base API URL
			$api_endpoint = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );

			// Build the tracking URL
			$tracking_url = sprintf(
				'%s/api/plugin/tracker?apiToken=%s&websitepropertyId=%s',
				$api_endpoint,
				urlencode( $api_token ),
				urlencode( $website_property_id )
			);

			// Prepare tracking data
			$tracking_data = array(
				'case_id'    => $case_id,
				'action'     => 'view',
				'source'     => 'wordpress_plugin_server',
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
			);

			// Make the API request asynchronously
			wp_remote_post( $tracking_url, array(
				'body'     => wp_json_encode( $tracking_data ),
				'headers'  => array(
					'Content-Type' => 'application/json',
				),
				'timeout'  => 5, // Short timeout for non-blocking
				'blocking' => false, // Non-blocking request
			) );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "BRAGBook Gallery: Initiated async view tracking for case {$case_id}" );
			}

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: Server-side view tracking exception - ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Check if a procedure has nudity flag set using API sidebar data.
	 *
	 * Uses the sidebar data to determine if a procedure has nudity enabled.
	 * This ensures consistent nudity detection across all shortcode handlers.
	 *
	 * @since 3.0.0
	 *
	 * @param string $procedure_slug Procedure slug to check.
	 *
	 * @return bool True if procedure has nudity flag set.
	 */
	private static function procedure_has_nudity_from_api( string $procedure_slug ): bool {
		if ( WP_DEBUG ) {
			error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api called with slug: ' . $procedure_slug );
		}


		// Get sidebar data to check procedure nudity
		$api_tokens = get_option( 'brag_book_gallery_api_tokens', [] );
		if ( empty( $api_tokens ) || ! is_array( $api_tokens ) ) {
			if ( WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api - No API tokens found' );
			}
			return false;
		}

		$sidebar_data = null;
		if ( ! empty( $api_tokens[0] ) ) {
			$sidebar_data = \BRAGBookGallery\Includes\Extend\Data_Fetcher::get_sidebar_data( $api_tokens[0] );
		}

		if ( empty( $sidebar_data ) || ! is_array( $sidebar_data ) ) {
			if ( WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api - No sidebar data found' );
			}
			return false;
		}

		if ( WP_DEBUG ) {
			error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api - Searching through ' . count( $sidebar_data ) . ' categories' );
		}

		// Search through categories for the procedure and check nudity flag
		foreach ( $sidebar_data as $category ) {
			if ( ! isset( $category['procedures'] ) || ! is_array( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $procedure ) {
				// Check if this is the procedure we're looking for (by slug or name)
				$api_procedure_slug = $procedure['slug'] ?? '';
				$procedure_name = strtolower( $procedure['name'] ?? '' );
				$slug_lower = strtolower( $procedure_slug );

				if ( WP_DEBUG ) {
					// Log every procedure to see what we're comparing
					error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api - Checking procedure: ' . $api_procedure_slug . ' (has_nudity: ' . ( ! empty( $procedure['has_nudity'] ) ? 'true' : 'false' ) . ', nudity: ' . ( ! empty( $procedure['nudity'] ) ? 'true' : 'false' ) . ')' );
				}

				if ( $api_procedure_slug === $procedure_slug ||
					 $procedure_name === $slug_lower ||
					 sanitize_title( $procedure_name ) === $procedure_slug ) {

					// Check if this procedure has nudity
					$has_nudity = ! empty( $procedure['has_nudity'] ) || ! empty( $procedure['nudity'] );

					if ( WP_DEBUG ) {
						error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api - MATCH FOUND for procedure: ' . $procedure_slug . ' - has_nudity: ' . ( $has_nudity ? 'true' : 'false' ) );
					}

					return $has_nudity;
				}
			}
		}

		if ( WP_DEBUG ) {
			error_log( 'BRAGBook Gallery: procedure_has_nudity_from_api - No match found for procedure: ' . $procedure_slug );
		}

		return false;
	}
}
