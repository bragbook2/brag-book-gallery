<?php
/**
 * Shortcodes handler for BRAGBookGallery plugin.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\REST\Endpoints;
use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Core\Slug_Helper;
use BRAGBookGallery\Includes\Extend\Asset_Manager;
use BRAGBookGallery\Includes\Extend\Data_Fetcher;
use BRAGBookGallery\Includes\Extend\HTML_Renderer;
use BRAGBookGallery\Includes\Resources\Assets;
use WP_Post;

/**
 * Class Shortcodes
 *
 * Handles all shortcode registrations and rendering for the BRAGBookGallery plugin.
 *
 * @since 3.0.0
 */
final class Shortcodes {

	/**
	 * Default limit for carousel items.
	 *
	 * @var int
	 */
	private const DEFAULT_CAROUSEL_LIMIT = 10;

	/**
	 * Default start index for carousel.
	 *
	 * @var int
	 */
	private const DEFAULT_START_INDEX = 0;

	/**
	 * Default word limit for descriptions.
	 *
	 * @var int
	 */
	private const DEFAULT_WORD_LIMIT = 50;

	/**
	 * Register all shortcodes and associated hooks.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function register(): void {
		// Register external handlers
		Ajax_Handlers::register();
		Rewrite_Rules_Handler::register();

		// Register shortcodes
		$shortcodes = [
			'brag_book_gallery'       => 'main_gallery_shortcode',
			'brag_book_carousel'      => 'carousel_shortcode',
			'brag_book_gallery_cases' => 'cases_shortcode',
			'brag_book_gallery_case'  => 'case_details_shortcode',
			'brag_book_favorites'     => 'favorites_shortcode',
		];

		foreach ( $shortcodes as $tag => $callback ) {
			add_shortcode(
				$tag,
				[ __CLASS__, $callback ]
			);
		}
		
		// Register backwards compatibility shortcode for old carousel
		add_shortcode(
			'bragbook_carousel_shortcode',
			[ __CLASS__, 'legacy_carousel_shortcode' ]
		);
		
		// Add body class for gallery pages
		add_filter( 'body_class', [ __CLASS__, 'add_gallery_body_class' ] );
	}

	/**
	 * Add body class for pages containing gallery shortcodes.
	 *
	 * @since 3.0.0
	 * @param array $classes Current body classes.
	 * @return array Modified body classes.
	 */
	public static function add_gallery_body_class( array $classes ): array {
		global $post;
		
		$is_gallery_page = false;
		
		// Check if we're on a singular page/post
		if ( is_singular() && isset( $post->post_content ) ) {
			// Check if the content contains any of our shortcodes
			$gallery_shortcodes = [
				'brag_book_gallery',
				'brag_book_carousel',
				'brag_book_gallery_cases',
				'brag_book_gallery_case',
				'brag_book_favorites',
			];
			
			foreach ( $gallery_shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					$classes[] = 'brag-book-gallery-page';
					$is_gallery_page = true;
					break; // Only add the class once
				}
			}
		}
		
		// Also check if we're on a gallery virtual URL (for rewrite rules)
		$current_url = $_SERVER['REQUEST_URI'] ?? '';
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug();
		
		if ( ! empty( $gallery_slug ) && strpos( $current_url, '/' . $gallery_slug . '/' ) !== false ) {
			if ( ! in_array( 'brag-book-gallery-page', $classes, true ) ) {
				$classes[] = 'brag-book-gallery-page';
				$is_gallery_page = true;
			}
		}
		
		// Add disable-custom-font class to body if custom font is disabled and we're on a gallery page
		if ( $is_gallery_page ) {
			$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );
			if ( $use_custom_font !== 'yes' ) {
				$classes[] = 'disable-custom-font';
			}
		}
		
		return $classes;
	}

	/**
	 * Find pages containing the gallery shortcode.
	 *
	 * @return array Array of page objects.
	 * @since 3.0.0
	 */
	private static function find_pages_with_gallery_shortcode(): array {
		global $wpdb;

		// Query for pages containing our shortcode
		$pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_type = 'page'
				AND post_status = 'publish'
				AND post_content LIKE %s",
				'%[brag_book_gallery%'
			)
		);

		return $pages ?: [];
	}

	/**
	 * Get page slug from page path.
	 *
	 * @param string $page_path The page path.
	 *
	 * @return string Page slug or empty string if not found.
	 * @since 3.0.0
	 */
	private static function get_page_slug_from_path( string $page_path ): string {
		$page = get_page_by_path( $page_path, OBJECT, 'page' );

		if ( ! $page instanceof WP_Post ) {
			return '';
		}

		$post = get_post( $page->ID );

		return ( $post instanceof WP_Post && isset( $post->post_name ) )
			? $post->post_name
			: '';
	}

	// Note: Rewrite rules are now handled by the Rewrite_Rules_Handler class
	// These methods have been removed to avoid duplication and conflicts

	/**
	 * Generate placeholder carousel items for a gallery section.
	 *
	 * @param string $section Section identifier (e.g., 'bd' for body, 'br' for breast).
	 * @param int $count Number of items to generate.
	 * @param bool $include_nudity Whether to include nudity warnings on some items.
	 *
	 * @return string Generated HTML for carousel items.
	 * @since 3.0.0
	 */
	private static function generate_placeholder_carousel_items(
		string $section,
		int $count = self::DEFAULT_CAROUSEL_LIMIT,
		bool $include_nudity = true
	): string {
		if ( empty( $section ) || $count <= 0 ) {
			return '';
		}

		$placeholder_image = 'https://bragbookgallery.com/nitropack_static/FCmixFCiYNkGgqjxyaUSblqHbCgLrqyJ/assets/images/optimized/rev-407fb37/ngnqwvuungodwrpnrczq.supabase.co/storage/v1/object/sign/brag-photos/org_2vm5nGWtoCYuaQBCP587ez6cYXF/c68b56b086f4f8eef8292f3f23320f1b.Blepharoplasty%20-%20aa239d58-badc-4ded-a26b-f89c2dd059b6.jpg';

		// Items that should have nudity warnings (2nd and 3rd items)
		$nudity_items = $include_nudity ? [ 2, 3 ] : [];

		$html_parts = [];

		for ( $i = 1; $i <= $count; $i ++ ) {
			$item_id    = sanitize_title( "{$section}-{$i}" );
			$has_nudity = in_array( $i, $nudity_items, true );

			$html_parts[] = HTML_Renderer::generate_single_carousel_item(
				$item_id,
				$placeholder_image,
				$has_nudity,
				$i,
				$count
			);
		}

		return implode( '', $html_parts );
	}

	/**
	 * Generate a single carousel item HTML.
	 *
	 * @param string $item_id Item identifier.
	 * @param string $image_url Image URL.
	 * @param bool $has_nudity Whether item has nudity warning.
	 * @param int $current_item Current item number.
	 * @param int $total_items Total number of items.
	 *
	 * @return string Generated HTML for single carousel item.
	 * @since 3.0.0
	 */
	private static function generate_single_carousel_item(
		string $item_id,
		string $image_url,
		bool $has_nudity,
		int $current_item,
		int $total_items
	): string {
		$aria_label = sprintf(
		/* translators: 1: current item number, 2: total items */
			__( 'Slide %1$d of %2$d', 'brag-book-gallery' ),
			$current_item,
			$total_items
		);

		$html = sprintf(
			'<div class="brag-book-gallery-carousel-item" data-slide="%s" role="group" aria-roledescription="slide" aria-label="%s">',
			esc_attr( $item_id ),
			esc_attr( $aria_label )
		);

		// Add nudity warning if needed
		if ( $has_nudity ) {
			$html .= HTML_Renderer::generate_nudity_warning();
		}

		// Add image
		$html .= HTML_Renderer::generate_carousel_image( $image_url, $has_nudity );

		// Add action buttons
		$html .= HTML_Renderer::generate_item_actions( $item_id );

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate nudity warning HTML.
	 *
	 * @return string Generated HTML for nudity warning.
	 * @since 3.0.0
	 */
	private static function generate_nudity_warning(): string {
		return sprintf(
			'<div class="brag-book-gallery-nudity-warning">
				<div class="brag-book-gallery-nudity-warning-content">
					<h4 class="brag-book-gallery-nudity-warning-title">%1$s</h4>
					<p class="brag-book-gallery-nudity-warning-caption">%2$s</p>
					<button class="brag-book-gallery-nudity-warning-button" type="button">%3$s</button>
				</div>
			</div>',
			esc_html__( 'WARNING: Contains Nudity', 'brag-book-gallery' ),
			esc_html__( 'If you are offended by such material or are under 18 years of age. Please do not proceed.', 'brag-book-gallery' ),
			esc_html__( 'Proceed', 'brag-book-gallery' )
		);
	}

	/**
	 * Generate carousel image HTML.
	 *
	 * @param string $image_url Image URL.
	 * @param bool $has_nudity Whether image has nudity blur.
	 *
	 * @return string Generated HTML for carousel image.
	 * @since 3.0.0
	 */
	private static function generate_carousel_image( string $image_url, bool $has_nudity ): string {
		$img_class = $has_nudity ? ' class="brag-book-gallery-nudity-blur"' : '';

		return sprintf(
			'<picture class="brag-book-gallery-carousel-image">
				<source srcset="%1$s" type="image/jpeg">
				<img src="%1$s" alt="%2$s" loading="lazy"%3$s width="400" height="300">
			</picture>',
			esc_url( $image_url ),
			esc_attr__( 'Gallery procedure before and after result', 'brag-book-gallery' ),
			$img_class
		);
	}

	/**
	 * Generate item action buttons HTML.
	 *
	 * @param string $item_id Item identifier.
	 *
	 * @return string Generated HTML for action buttons.
	 * @since 3.0.0
	 */
	private static function generate_item_actions( string $item_id ): string {
		return sprintf(
			'<div class="brag-book-gallery-item-actions">
				<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="%1$s" aria-label="%2$s">
					<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
				</button>
				<button class="brag-book-gallery-share-button" data-item-id="%1$s" aria-label="%3$s">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
						<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>
					</svg>
				</button>
			</div>',
			esc_attr( $item_id ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' ),
			esc_attr__( 'Share this image', 'brag-book-gallery' )
		);
	}

	/**
	 * Render main gallery shortcode using modern markup.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	public static function main_gallery_shortcode( array $atts ): string {
		// Parse and validate shortcode attributes using PHP 8.2 syntax
		$atts = shortcode_atts(
			[
				'website_property_id' => '',
			],
			$atts,
			'brag_book_gallery'
		);

		// Check if we're on a filtered URL
		$filter_procedure = get_query_var( 'filter_procedure', '' );
		$procedure_title  = get_query_var( 'procedure_title', '' );
		$case_id          = get_query_var( 'case_id', '' );
		$favorites_page   = get_query_var( 'favorites_page', '' );

		// Check if we're on the favorites page
		if ( ! empty( $favorites_page ) ) {
			return self::render_favorites_page( $atts );
		}

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// Debug: Log what query vars we're getting in main shortcode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Main Gallery Shortcode Debug:' );
			error_log( 'filter_procedure: ' . $filter_procedure );
			error_log( 'procedure_title: ' . $procedure_title );
			error_log( 'case_id: ' . $case_id );
			error_log( 'Current URL: ' . ( $_SERVER['REQUEST_URI'] ?? 'N/A' ) );
		}

		// For case detail pages, we'll load the full gallery with the case details loaded via JavaScript
		// This ensures the filters sidebar remains visible
		$initial_case_id = ! empty( $case_id ) ? $case_id : '';

		// For procedure filtering, we'll pass it to the main gallery JavaScript
		// The JavaScript will handle applying the filter on load

		// Validate configuration and mode
		$validation = self::validate_gallery_configuration( $atts );
		if ( $validation['error'] ) {
			return sprintf(
				'<p class="brag-book-gallery-error">%s</p>',
				esc_html( $validation['message'] )
			);
		}

		// Extract validated configuration
		$config = $validation['config'];

		// Enqueue required assets
		Asset_Manager::enqueue_gallery_assets();

		// Get sidebar data with caching
		$sidebar_data = Data_Fetcher::get_sidebar_data( $config['api_token'] );

		// Fetch all cases for filtering (we need the complete dataset for filters)
		$all_cases_data = Data_Fetcher::get_all_cases_for_filtering( $config['api_token'], $config['website_property_id'] );

		// Debug log the fetched data
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Fetched all cases data: ' . count( $all_cases_data['data'] ?? [] ) . ' cases' );
		}

		// Localize script data (now with all_cases_data)
		Asset_Manager::localize_gallery_script( $config, $sidebar_data, $all_cases_data );

		// Add inline nudity acceptance script
		Asset_Manager::add_nudity_acceptance_script();

		// Generate and return gallery HTML
		return self::render_gallery_html( $sidebar_data, $config, $all_cases_data, $filter_procedure, $initial_case_id );
	}

	/**
	 * Validate gallery configuration and settings.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return array Validation result with config or error.
	 * @since 3.0.0
	 */
	private static function validate_gallery_configuration( array $atts ): array {
		// Check mode first
		$current_mode = get_option( 'brag_book_gallery_mode', 'javascript' );
		if ( $current_mode !== 'javascript' ) {
			return [
				'error'   => true,
				'message' => __( 'Gallery requires JavaScript mode to be enabled.', 'brag-book-gallery' ),
			];
		}

		// Get API configuration with null coalescing
		$api_tokens           = get_option( 'brag_book_gallery_api_token' ) ?: [];
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id' ) ?: [];

		// Ensure arrays
		if ( ! is_array( $api_tokens ) ) {
			$api_tokens = [];
		}
		if ( ! is_array( $website_property_ids ) ) {
			$website_property_ids = [];
		}

		// Use first configuration if none specified
		$website_property_id = $atts['website_property_id'] ?: ( $website_property_ids[0] ?? '' );
		$api_token           = $api_tokens[0] ?? '';

		// Validate required configuration
		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			return [
				'error'   => true,
				'message' => __( 'Please configure API settings to display the gallery.', 'brag-book-gallery' ),
			];
		}

		return [
			'error'  => false,
			'config' => [
				'api_token'           => sanitize_text_field( (string) $api_token ),
				'website_property_id' => sanitize_text_field( (string) $website_property_id ),
			],
		];
	}

	/**
	 * Render the main gallery HTML.
	 *
	 * @param array $sidebar_data Sidebar data.
	 * @param array $config Gallery configuration.
	 * @param array $all_cases_data All cases data for filtering.
	 * @param string $initial_procedure Initial procedure filter from URL.
	 *
	 * @return string Gallery HTML.
	 * @since 3.0.0
	 */
	private static function render_gallery_html( array $sidebar_data, array $config, array $all_cases_data = [], string $initial_procedure = '', string $initial_case_id = '' ): string {
		// Get the current page URL for the base gallery path
		$current_url = get_permalink();
		$base_path   = parse_url( $current_url, PHP_URL_PATH ) ?: '/';

		ob_start();
		
		// Check if custom font is disabled
		$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );
		$wrapper_class = 'brag-book-gallery-wrapper';
		if ( $use_custom_font !== 'yes' ) {
			$wrapper_class .= ' disable-custom-font';
		}
		?>
		<!-- BRAG book Gallery Component Start -->
		<div class="<?php echo esc_attr( $wrapper_class ); ?>"
			 data-base-url="<?php echo esc_attr( rtrim( $base_path, '/' ) ); ?>"
			<?php if ( ! empty( $initial_procedure ) ) : ?>
				data-initial-procedure="<?php echo esc_attr( $initial_procedure ); ?>"
			<?php endif; ?>
			<?php if ( ! empty( $initial_case_id ) ) : ?>
				data-initial-case-id="<?php echo esc_attr( $initial_case_id ); ?>"
			<?php endif; ?>
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
					<svg xmlns="http://www.w3.org/2000/svg" height="24px"
						 viewBox="0 -960 960 960" width="24px"
						 fill="currentColor" aria-hidden="true">
						<path
							d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>
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

					<p class="brag-book-gallery-consultation-text">
						<strong>Ready for the next step?</strong><br/>Contact us
						to request your consultation.
					</p>
					<button class="brag-book-gallery-button"
							data-action="request-consultation">
						Request a Consultation
					</button>
				</div>

				<div class="brag-book-gallery-main-content" role="region"
					 aria-label="Gallery content" id="gallery-content">
					<!-- Filter badges container (initially hidden, populated by JavaScript) -->
					<div class="brag-book-gallery-controls-left">
						<div class="brag-book-gallery-active-filters">
							<div class="brag-book-gallery-filter-badges"
								 id="brag-book-gallery-filter-badges">
								<!-- Filter badges will be populated by JavaScript -->
							</div>
							<button class="brag-book-gallery-clear-all-filters"
									id="brag-book-gallery-clear-all"
									style="display: none;">
								<?php echo esc_html__( 'Clear All', 'brag-book-gallery' ); ?>
							</button>
						</div>
					</div>
					<?php
					// Get landing page text from settings
					$landing_page_text = get_option( 'brag_book_gallery_landing_page_text', '' );

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

					<div class="brag-book-gallery-favorites-section"
						 aria-labelledby="favorites-title">
						<div class="brag-book-gallery-favorites-header">
							<svg class="brag-book-gallery-favorites-logo"
								 xmlns="http://www.w3.org/2000/svg"
								 viewBox="0 0 900 180">
								<path fill="#ff595c"
									  d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"/>
								<path fill="#ff595c"
									  d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"/>
								<path fill="#121827"
									  d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"/>
								<path fill="#121827"
									  d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"/>
								<path fill="#121827"
									  d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"/>
								<path fill="#121827"
									  d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"/>
								<path fill="#121827"
									  d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"/>
								<path fill="#121827"
									  d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"/>
								<path fill="#121827"
									  d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"/>
								<path fill="#121827"
									  d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"/>
								<path fill="#121827"
									  d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"/>
								<path fill="#121827"
									  d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"/>
								<path fill="#121827"
									  d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"/>
								<path fill="#ff595c"
									  d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"/>
							</svg>
							<div class="brag-book-gallery-favorites-text">
								<p class="brag-book-gallery-favorites-description">
									<strong>Use the MyFavorites tool</strong> to
									help communicate your specific goals. If a
									result
									speaks to you, tap the heart.
								</p>
							</div>
						</div>
					</div>

					<div class="brag-book-gallery-powered-by">
						<a href="https://bragbookgallery.com/"
						   class="brag-book-gallery-powered-by-link"
						   target="_blank" rel="noopener noreferrer">Powered by
							BRAG book</a>
					</div>
				</div>
			</div>

			<!-- Share dropdown will be created dynamically by JavaScript -->

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
								class="brag-book-gallery-form-submit">Submit
							Request
						</button>
					</form>
				</div>
			</dialog>

			<!-- Favorites Dialog -->
			<dialog class="brag-book-gallery-dialog" id="favoritesDialog">
				<div class="brag-book-gallery-dialog-content">
					<div class="brag-book-gallery-dialog-header">
						<svg class="brag-book-gallery-dialog-logo"
							 viewBox="0 0 900 180">
							<path fill="#ff595c"
								  d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"/>
							<path fill="#ff595c"
								  d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"/>
							<path fill="#121827"
								  d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"/>
							<path fill="#121827"
								  d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"/>
							<path fill="#121827"
								  d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"/>
							<path fill="#121827"
								  d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6s10.9,7.1,19.3,7.1Z"/>
							<path fill="#121827"
								  d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"/>
							<path fill="#121827"
								  d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"/>
							<path fill="#121827"
								  d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"/>
							<path fill="#121827"
								  d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"/>
							<path fill="#121827"
								  d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"/>
							<path fill="#121827"
								  d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"/>
							<path fill="#121827"
								  d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"/>
							<path fill="#ff595c"
								  d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"/>
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
								class="brag-book-gallery-form-submit">Submit
						</button>
					</form>
				</div>
			</dialog>
		</div>

		<?php if ( ! empty( $all_cases_data ) && isset( $all_cases_data['data'] ) ) : ?>
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
	 * Render carousel shortcode using carousel endpoint.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	public static function carousel_shortcode( array $atts ): string {

		// Get API tokens and website property IDs from settings
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		// Get the first token and property ID as defaults
		$default_token = ! empty( $api_tokens ) && isset( $api_tokens[0] ) ? $api_tokens[0] : '';
		$default_property_id = ! empty( $website_property_ids ) && isset( $website_property_ids[0] ) ? $website_property_ids[0] : '';

		// Parse and validate shortcode attributes.
		$atts = shortcode_atts(
			[
				'api_token'           => $default_token,
				'website_property_id' => $default_property_id,
				'limit'               => self::DEFAULT_CAROUSEL_LIMIT,
				'start'               => 1,
				'procedure_id'        => '',
				'procedure'           => '', // Support both procedure and procedure_id
				'member_id'           => '',
				'show_controls'       => 'true',
				'show_pagination'     => 'true',
				'auto_play'           => 'false',
				'class'               => '',
			],
			$atts,
			'brag_book_carousel'
		);
		
		// If procedure is provided but not procedure_id, use procedure
		if ( empty( $atts['procedure_id'] ) && ! empty( $atts['procedure'] ) ) {
			$atts['procedure_id'] = $atts['procedure'];
		}

		// Validate configuration
		$validation = self::validate_carousel_configuration( $atts );
		if ( $validation['error'] ) {
			return sprintf(
				'<p class="brag-book-carousel-error">%s</p>',
				esc_html( $validation['message'] )
			);
		}

		$config = $validation['config'];

		// Get carousel data from API
		$carousel_data = Data_Fetcher::get_carousel_data_from_api( $config );

		// Enqueue carousel assets (includes custom CSS)
		Asset_Manager::enqueue_carousel_assets();

		// Localize script data
		Asset_Manager::localize_carousel_script( $config );

		// Generate and return carousel HTML
		return self::render_carousel_html( $carousel_data, $config );
	}

	/**
	 * Handle legacy carousel shortcode for backwards compatibility.
	 *
	 * Maps old [bragbook_carousel_shortcode] attributes to new [brag_book_carousel] format.
	 * Old format: [bragbook_carousel_shortcode procedure="nonsurgical-facelift" start="1" limit="10" title="0" details="0" website_property_id="89"]
	 * 
	 * @param array $atts Legacy shortcode attributes.
	 * @return string Carousel HTML output.
	 * @since 3.0.0
	 */
	public static function legacy_carousel_shortcode( array $atts ): string {
		// Parse legacy attributes
		$legacy_atts = shortcode_atts(
			[
				'procedure'           => '',
				'start'               => '1',
				'limit'               => '10',
				'title'               => '0',
				'details'             => '0',
				'website_property_id' => '',
			],
			$atts,
			'bragbook_carousel_shortcode'
		);
		
		// Map legacy attributes to new format
		$new_atts = [];
		
		// Get API token from settings (wasn't in old shortcode)
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$new_atts['api_token'] = ! empty( $api_tokens ) && isset( $api_tokens[0] ) ? $api_tokens[0] : '';
		
		// Map website_property_id directly
		if ( ! empty( $legacy_atts['website_property_id'] ) ) {
			$new_atts['website_property_id'] = $legacy_atts['website_property_id'];
		}
		
		// Map limit and start
		$new_atts['limit'] = $legacy_atts['limit'];
		$new_atts['start'] = $legacy_atts['start'];
		
		// Map procedure to procedure_id if provided
		if ( ! empty( $legacy_atts['procedure'] ) ) {
			// Try to find the procedure ID from the slug
			// First, get the carousel data to find the procedure ID
			$new_atts['procedure_id'] = $legacy_atts['procedure']; // Will be converted to ID in the carousel handler
		}
		
		// Map title and details to show_controls and show_pagination
		// In the old version, title="0" meant hide title, details="0" meant hide details
		// We'll interpret these as controls for the new carousel
		$new_atts['show_controls'] = ( $legacy_atts['title'] !== '0' ) ? 'true' : 'false';
		$new_atts['show_pagination'] = ( $legacy_atts['details'] !== '0' ) ? 'true' : 'false';
		
		// Call the new carousel shortcode with mapped attributes
		return self::carousel_shortcode( $new_atts );
	}

	/**
	 * Validate carousel configuration and settings.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return array Validation result with config or error.
	 * @since 3.0.0
	 */
	private static function validate_carousel_configuration( array $atts ): array {
		// Get API configuration if not provided in shortcode
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens        = get_option( 'brag_book_gallery_api_token' ) ?: [];
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : '';
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids        = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : '';
		}

		// Validate required fields
		if ( empty( $atts['api_token'] ) ) {
			return [
				'error'   => true,
				'message' => __( 'API token is required for carousel.', 'brag-book-gallery' ),
			];
		}

		// Get procedure_id and member_id - only set if provided
		$procedure_id = null;
		if ( ! empty( $atts['procedure_id'] ) ) {
			// Check if it's numeric (ID) or string (slug)
			if ( is_numeric( $atts['procedure_id'] ) ) {
				$procedure_id = absint( $atts['procedure_id'] );
			} else {
				// It's a slug - convert it to an ID using sidebar data
				$procedure_id = self::get_procedure_id_from_slug( 
					$atts['procedure_id'], 
					$atts['api_token'], 
					$atts['website_property_id'] 
				);
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAG book Carousel: Converted slug "' . $atts['procedure_id'] . '" to ID: ' . ( $procedure_id ?: 'not found' ) );
				}
			}
		}
		$member_id = ! empty( $atts['member_id'] ) ? absint( $atts['member_id'] ) : null;

		return [
			'error'  => false,
			'config' => [
				'api_token'           => sanitize_text_field( (string) $atts['api_token'] ),
				'website_property_id' => sanitize_text_field( (string) ( $atts['website_property_id'] ?? '' ) ),
				'limit'               => absint( $atts['limit'] ?? 0 ) ?: 10,
				'start'               => absint( $atts['start'] ?? 0 ) ?: 1,
				'procedure_id'        => $procedure_id,
				'member_id'           => $member_id,
				'show_controls'       => filter_var( $atts['show_controls'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'show_pagination'     => filter_var( $atts['show_pagination'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'auto_play'           => filter_var( $atts['auto_play'] ?? false, FILTER_VALIDATE_BOOLEAN ),
				'class'               => sanitize_html_class( (string) ( $atts['class'] ?? '' ) ),
			],
		];
	}

	/**
	 * Get procedure ID from slug using sidebar data.
	 *
	 * @param string $slug Procedure slug.
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @return int|null Procedure ID or null if not found.
	 * @since 3.0.0
	 */
	private static function get_procedure_id_from_slug( string $slug, string $api_token, string $website_property_id ): ?int {
		// Get sidebar data which contains procedure information
		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );
		
		if ( empty( $sidebar_data ) ) {
			return null;
		}
		
		// Get the data array from the response
		$categories = $sidebar_data['data'] ?? $sidebar_data;
		
		// Search through categories for the procedure
		foreach ( $categories as $category ) {
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					// Check if slug matches
					if ( isset( $procedure['slugName'] ) && $procedure['slugName'] === $slug ) {
						// Return the first ID from the ids array
						if ( ! empty( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
							return (int) $procedure['ids'][0];
						}
					}
					// Also check by sanitized name as fallback
					if ( isset( $procedure['name'] ) && sanitize_title( $procedure['name'] ) === $slug ) {
						if ( ! empty( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
							return (int) $procedure['ids'][0];
						}
					}
				}
			}
		}
		
		return null;
	}

	/**
	 * Render carousel HTML.
	 *
	 * @param array $carousel_data Carousel data from API.
	 * @param array $config Carousel configuration.
	 *
	 * @return string Carousel HTML.
	 * @since 3.0.0
	 */
	private static function render_carousel_html( array $carousel_data, array $config ): string {
		// Check if we have data in either format
		$has_data = ! empty( $carousel_data ) && 
					( ! empty( $carousel_data['data'] ) || 
					  ( is_array( $carousel_data ) && isset( $carousel_data[0] ) ) );
		
		if ( ! $has_data ) {
			return sprintf(
				'<p class="brag-book-carousel-no-data">%s</p>',
				esc_html__( 'No carousel images available.', 'brag-book-gallery' )
			);
		}

		$carousel_id = 'carousel-' . wp_rand();
		$css_class   = 'brag-book-gallery-carousel-wrapper' . ( ! empty( $config['class'] ) ? ' ' . $config['class'] : '' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $css_class ); ?>"
			 data-carousel="<?php echo esc_attr( $carousel_id ); ?>">
			<div class="brag-book-gallery-carousel-content">
				<?php if ( $config['show_controls'] ): ?>
					<button class="brag-book-gallery-carousel-btn"
							data-direction="prev"
							aria-label="<?php esc_attr_e( 'Previous slide', 'brag-book-gallery' ); ?>">
						<svg class="brag-book-gallery-arrow-icon" width="24"
							 height="24" viewBox="0 0 24 24" fill="none">
							<path d="M15 18L9 12L15 6" stroke="currentColor"
								  stroke-width="2" stroke-linecap="round"
								  stroke-linejoin="round"/>
						</svg>
					</button>
				<?php endif; ?>

				<div class="brag-book-gallery-carousel-track"
					 data-carousel-track="<?php echo esc_attr( $carousel_id ); ?>"
					 role="region"
					 aria-label="<?php esc_attr_e( 'Image carousel', 'brag-book-gallery' ); ?>">
					<?php
					// Use the local method which handles the correct data structure
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method
					echo self::generate_carousel_items_from_data( $carousel_data['data'] ?? $carousel_data );
					?>
				</div>

				<?php if ( $config['show_controls'] ): ?>
					<button class="brag-book-gallery-carousel-btn"
							data-direction="next"
							aria-label="<?php esc_attr_e( 'Next slide', 'brag-book-gallery' ); ?>">
						<svg class="brag-book-gallery-arrow-icon" width="24"
							 height="24" viewBox="0 0 24 24" fill="none">
							<path d="M9 18L15 12L9 6" stroke="currentColor"
								  stroke-width="2" stroke-linecap="round"
								  stroke-linejoin="round"/>
						</svg>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $config['show_pagination'] ): ?>
				<div class="brag-book-gallery-carousel-pagination"
					 data-pagination="<?php echo esc_attr( $carousel_id ); ?>"
					 role="tablist"
					 aria-label="<?php esc_attr_e( 'Carousel pagination', 'brag-book-gallery' ); ?>"></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate carousel items from API data.
	 *
	 * @param array $items Carousel items data.
	 *
	 * @return string Generated HTML for carousel items.
	 * @since 3.0.0
	 */
	private static function generate_carousel_items_from_data( array $items ): string {
		if ( empty( $items ) ) {
			return '';
		}

		$html_parts  = [];
		$slide_index = 0;

		// Loop through each case
		foreach ( $items as $case ) {
			// Check for both possible data structures: photoSets and photos
			$photo_sets = $case['photoSets'] ?? $case['photos'] ?? [];
			
			// If photo_sets is empty, skip this case
			if ( empty( $photo_sets ) ) {
				continue;
			}
			
			foreach ( $photo_sets as $photo ) {
				$slide_index++;
				$html_parts[] = HTML_Renderer::generate_carousel_slide_from_photo( $photo, $case, $slide_index );
			}
		}

		return implode( '', $html_parts );
	}

	/**
	 * Generate a single carousel slide from photo data.
	 *
	 * @param array $photo Photo data from photoSet.
	 * @param array $case Case data containing the photo.
	 * @param int $slide_index Current slide index.
	 *
	 * @return string Generated HTML for single carousel slide.
	 * @since 3.0.0
	 */
	private static function generate_carousel_slide_from_photo( array $photo, array $case, int $slide_index ): string {
		// Get image URL from postProcessedImageLocation
		$image_url = esc_url( $photo['postProcessedImageLocation'] ?? '' );
		if ( empty( $image_url ) ) {
			return ''; // Skip photos without valid image URL
		}

		// Get alt text from seoAltText or use details as fallback
		$alt_text = ! empty( $photo['seoAltText'] )
			? esc_attr( $photo['seoAltText'] )
			: esc_attr( strip_tags( $case['details'] ?? __( 'Before and after procedure result', 'brag-book-gallery' ) ) );

		// Get case ID and photo ID
		$case_id  = $case['id'] ?? '';
		$photo_id = $photo['id'] ?? $slide_index;
		$item_id  = 'slide-' . $photo_id;

		// Get procedure information for URL
		// Try multiple possible field names for procedure
		$procedure_name = $case['procedureName'] ?? $case['procedure'] ?? $case['name'] ?? '';

		// If no procedure name in case data, try to get from the photo metadata
		if ( empty( $procedure_name ) && ! empty( $photo['procedureName'] ) ) {
			$procedure_name = $photo['procedureName'];
		}

		$procedure_slug = ! empty( $procedure_name ) ? sanitize_title( $procedure_name ) : 'case';

		// Build case detail URL - format: /gallery-page/procedure-name/case-id
		$case_url = '';

		if ( ! empty( $case_id ) ) {
			// Try to get the current page URL
			$current_url = get_permalink();
			if ( ! empty( $current_url ) ) {
				$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '';
			} else {
				// Fallback to getting gallery page from options
				$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
				$base_path     = ! empty( $gallery_slugs[0] ) ? '/' . $gallery_slugs[0] : '/before-after';
			}

			// Always create a URL if we have a case ID
			$case_url = rtrim( $base_path, '/' ) . '/' . $procedure_slug . '/' . $case_id;
		}

		// Check for nudity flag if available
		$has_nudity = ! empty( $photo['has_nudity'] ) || ! empty( $case['has_nudity'] );

		// Build carousel item HTML matching original markup
		$html = sprintf(
			'<div class="brag-book-gallery-carousel-item" data-slide="%s" data-case-id="%s" data-photo-id="%s" data-procedure-slug="%s">',
			esc_attr( $item_id ),
			esc_attr( $case_id ),
			esc_attr( $photo_id ),
			esc_attr( $procedure_slug )
		);

		// Add nudity warning if needed
		if ( $has_nudity ) {
			$html .= '<div class="brag-book-gallery-nudity-warning">
				<span class="warning-icon">⚠️</span>
				<span class="warning-text">' . esc_html__( 'This image contains nudity', 'brag-book-gallery' ) . '</span>
			</div>';
		}

		// Add image with anchor link if URL is available
		$img_class = $has_nudity ? 'brag-book-gallery-nudity-blur' : '';

		if ( ! empty( $case_url ) ) {
			$html .= sprintf(
				'<a href="%s" class="brag-book-gallery-case-link" data-case-id="%s">',
				esc_url( $case_url ),
				esc_attr( $case_id )
			);
		}

		$html .= sprintf(
			'<img src="%s" alt="%s" class="brag-book-gallery-carousel-image %s" loading="lazy">',
			$image_url,
			$alt_text,
			esc_attr( $img_class )
		);

		if ( ! empty( $case_url ) ) {
			$html .= '</a>';
		}

		// Add action buttons matching original markup exactly
		$html .= sprintf(
			'<div class="brag-book-gallery-item-actions">
				<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="%s" aria-label="%s">
					<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
				</button>
				<button class="brag-book-gallery-share-button" data-item-id="%s" aria-label="%s">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
						<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Z"/>
					</svg>
				</button>
			</div>',
			esc_attr( $item_id ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' ),
			esc_attr( $item_id ),
			esc_attr__( 'Share this image', 'brag-book-gallery' )
		);

		$html .= '</div>';

		return $html;
	}


	/**
	 * Cases shortcode handler for displaying case listings.
	 *
	 * Displays cases from the /api/plugin/combine/cases endpoint
	 * filtered by procedure if specified in the URL.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	public static function cases_shortcode( array $atts ): string {
		// Parse shortcode attributes
		$atts = shortcode_atts(
			[
				'api_token'           => '',
				'website_property_id' => '',
				'procedure_ids'       => '',
				'limit'               => 20,
				'page'                => 1,
				'columns'             => 3,
				'show_details'        => 'true',
				'class'               => '',
			],
			$atts,
			'brag_book_gallery_cases'
		);

		// Get API configuration if not provided (do this BEFORE checking case_id)
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens        = get_option( 'brag_book_gallery_api_token' ) ?: [];
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : '';
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids        = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : '';
		}

		// Get filter from URL if present
		$filter_procedure = get_query_var( 'filter_procedure', '' );
		$procedure_title  = get_query_var( 'procedure_title', '' );
		$case_id          = get_query_var( 'case_id', '' );

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// Debug: Log what query vars we're getting
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Cases Shortcode Debug:' );
			error_log( 'filter_procedure: ' . $filter_procedure );
			error_log( 'procedure_title: ' . $procedure_title );
			error_log( 'case_id: ' . $case_id );
			error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
			error_log( 'Website Property ID: ' . $atts['website_property_id'] );
			error_log( 'Current URL: ' . ( $_SERVER['REQUEST_URI'] ?? 'N/A' ) );
		}

		// If we have a case_id, show single case (now with proper API config)
		if ( ! empty( $case_id ) ) {
			return self::render_single_case( $case_id, $atts );
		}

		// Validate required fields
		if ( empty( $atts['api_token'] ) || empty( $atts['website_property_id'] ) ) {
			return '<p class="brag-book-gallery-cases-error">' .
				   esc_html__( 'Please configure API settings to display cases.', 'brag-book-gallery' ) .
				   '</p>';
		}

		// Get procedure IDs based on filter
		$procedure_ids = [];
		if ( ! empty( $filter_procedure ) ) {
			// Try to find matching procedure in sidebar data
			$sidebar_data = Data_Fetcher::get_sidebar_data( $atts['api_token'] );
			if ( ! empty( $sidebar_data['data'] ) ) {
				$procedure_info = Data_Fetcher::find_procedure_by_slug( $sidebar_data['data'], $filter_procedure );
				// Use 'ids' array which contains all procedure IDs for this procedure type
				if ( ! empty( $procedure_info['ids'] ) && is_array( $procedure_info['ids'] ) ) {
					$procedure_ids = array_map( 'intval', $procedure_info['ids'] );
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Cases shortcode - Found procedure IDs for ' . $filter_procedure . ': ' . implode( ',', $procedure_ids ) );
						error_log( 'Total case count from sidebar: ' . ( $procedure_info['totalCase'] ?? 0 ) );
					}
				} elseif ( ! empty( $procedure_info['id'] ) ) {
					// Fallback to single 'id' if 'ids' array doesn't exist
					$procedure_ids = [ intval( $procedure_info['id'] ) ];
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Cases shortcode - Using single procedure ID for ' . $filter_procedure . ': ' . $procedure_info['id'] );
					}
				}
			}
		} elseif ( ! empty( $atts['procedure_ids'] ) ) {
			$procedure_ids = array_map( 'intval', explode( ',', $atts['procedure_ids'] ) );
		}

		// When filtering by procedure, load ALL cases for that procedure to enable proper filtering
		$initial_load_size = ! empty( $filter_procedure ) ? 200 : 10; // Load all for procedure, or 10 for general

		// Get cases from API
		$cases_data = Data_Fetcher::get_cases_from_api(
			$atts['api_token'],
			$atts['website_property_id'],
			$procedure_ids,
			$initial_load_size,
			intval( $atts['page'] )
		);

		// Enqueue cases assets (includes custom CSS)
		Asset_Manager::enqueue_cases_assets();

		// Render cases grid
		return self::render_cases_grid( $cases_data, $atts, $filter_procedure );
	}

	/**
	 * Render cases grid
	 *
	 * @param array $cases_data Cases data from API.
	 * @param array $atts Shortcode attributes.
	 * @param string $filter_procedure Procedure filter if any.
	 * @return string HTML output.
	 * @since 3.0.0
	 */
	private static function render_cases_grid( array $cases_data, array $atts, string $filter_procedure = '' ): string {
		if ( empty( $cases_data ) || empty( $cases_data['data'] ) ) {
			return '<p class="brag-book-gallery-cases-no-data">' . 
				   esc_html__( 'No cases found.', 'brag-book-gallery' ) . 
				   '</p>';
		}

		$cases = $cases_data['data'];
		$columns = intval( $atts['columns'] ) ?: 3;
		$show_details = filter_var( $atts['show_details'], FILTER_VALIDATE_BOOLEAN );
		
		// Start output
		$output = '<div class="brag-book-gallery-cases-grid columns-' . esc_attr( $columns ) . '">';
		
		// Get image display mode setting
		$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );
		
		foreach ( $cases as $case ) {
			// Render each case card
			$output .= self::render_ajax_gallery_case_card( 
				$case, 
				$image_display_mode,
				false,
				$filter_procedure 
			);
		}
		
		$output .= '</div>';

		// Add pagination if available
		if ( ! empty( $cases_data['pagination'] ) ) {
			$base_path = get_site_url() . '/' . get_option( 'brag_book_gallery_page_slug', 'gallery' )[0];
			$output .= self::render_pagination( $cases_data['pagination'], $base_path, $filter_procedure );
		}

		return $output;
	}

	/**
	 * Find procedure by slug in sidebar data.
	 *
	 * @param array $sidebar_data Sidebar data array.
	 * @param string $slug Procedure slug to find.
	 *
	 * @return array|null Procedure info or null if not found.
	 * @since 3.0.0
	 */


	/**
	 * Render AJAX gallery case card with proper structure and data attributes.
	 *
	 * @param array $case Case data.
	 * @param string $image_display_mode Image display mode (single or before_after).
	 * @param bool $procedure_nudity Whether procedure has nudity.
	 * @param string $procedure_context Procedure context from AJAX filter.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_ajax_gallery_case_card( array $case, string $image_display_mode, bool $procedure_nudity = false, string $procedure_context = '' ): string {
		$html = '';

		// Prepare patient details for data attributes
		$data_attrs = 'data-card="true"';

		// Add age
		if ( ! empty( $case['age'] ) ) {
			$data_attrs .= ' data-age="' . esc_attr( $case['age'] ) . '"';
		}

		// Add gender
		if ( ! empty( $case['gender'] ) ) {
			$data_attrs .= ' data-gender="' . esc_attr( strtolower( $case['gender'] ) ) . '"';
		}

		// Add ethnicity
		if ( ! empty( $case['ethnicity'] ) ) {
			$data_attrs .= ' data-ethnicity="' . esc_attr( strtolower( $case['ethnicity'] ) ) . '"';
		}

		// Add height with unit
		if ( ! empty( $case['height'] ) ) {
			$height_value = $case['height'];
			$height_unit  = ! empty( $case['heightUnit'] ) ? $case['heightUnit'] : '';
			$data_attrs   .= ' data-height="' . esc_attr( $height_value ) . '"';
			$data_attrs   .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
			$data_attrs   .= ' data-height-full="' . esc_attr( $height_value . $height_unit ) . '"';
		}

		// Add weight with unit
		if ( ! empty( $case['weight'] ) ) {
			$weight_value = $case['weight'];
			$weight_unit  = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
			$data_attrs   .= ' data-weight="' . esc_attr( $weight_value ) . '"';
			$data_attrs   .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
			$data_attrs   .= ' data-weight-full="' . esc_attr( $weight_value . $weight_unit ) . '"';
		}

		// Get case ID using consistent logic - prefer main case ID for API compatibility
		$case_id = $case['id'] ?? '';

		// If main ID is empty, fall back to caseDetails ID
		if ( empty( $case_id ) && ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			$case_id      = $first_detail['caseId'] ?? '';
		}

		// Get procedure IDs for this case
		$procedure_ids = '';
		if ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'intval', $case['procedureIds'] ) );
		}

		$html .= '<article class="brag-book-gallery-case-card" ' . $data_attrs . ' data-case-id="' . esc_attr( $case_id ) . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '">';

		// Get case URL for linking - prioritize procedure context from AJAX filter
		$filter_procedure = get_query_var( 'filter_procedure', '' );
		$procedure_title  = get_query_var( 'procedure_title', '' );

		// First priority: use procedure context passed from AJAX filter
		if ( ! empty( $procedure_context ) ) {
			$procedure_slug = sanitize_title( $procedure_context );
		} elseif ( ! empty( $filter_procedure ) ) {
			$procedure_slug = $filter_procedure;
		} elseif ( ! empty( $procedure_title ) ) {
			$procedure_slug = $procedure_title;
		} else {
			// Parse current URL to get procedure slug (maintain current page context)
			$current_url  = $_SERVER['REQUEST_URI'] ?? '';
			$gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();

			// Extract procedure from URL pattern: /gallery-slug/procedure-slug/ or /gallery-slug/procedure-slug/case-id
			$pattern = '/' . preg_quote( $gallery_slug, '/' ) . '\/([^\/]+)(?:\/|$)/';
			if ( preg_match( $pattern, $current_url, $matches ) && ! empty( $matches[1] ) ) {
				$procedure_slug = $matches[1];
			} else {
				// Fallback: extract procedure name from case data
				$temp_procedure_name = 'Case';
				if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
					$first_procedure     = reset( $case['procedures'] );
					$temp_procedure_name = $first_procedure['name'] ?? 'Case';
				}
				$procedure_slug = sanitize_title( $temp_procedure_name );
			}
		}

		$gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug();
		$case_url     = home_url( '/' . $gallery_slug . '/' . $procedure_slug . '/' . $case_id );

		// Display images based on setting
		if ( $image_display_mode === 'single' ) {
			// Single image mode - use postProcessedImageLocation from photoSets
			$single_image = '';
			if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
				$first_photoset = reset( $case['photoSets'] );
				$single_image   = ! empty( $first_photoset['postProcessedImageLocation'] ) ? $first_photoset['postProcessedImageLocation'] : '';
			}

			if ( $single_image ) {
				$html .= '<div class="brag-book-gallery-case-images single-image">';
				$html .= '<div class="brag-book-gallery-single-image">';
				$html .= '<div class="brag-book-gallery-image-container">';
				$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';

				// Add action buttons (share and heart)
				$html .= '<div class="brag-book-gallery-item-actions">';

				// Heart/Favorite button
				$html .= '<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
				$html .= '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
				$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
				$html .= '</svg>';
				$html .= '</button>';

				// Share button (conditional)
				$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
				if ( 'yes' === $enable_sharing ) {
					$html .= '<button class="brag-book-gallery-share-button" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Share this image">';
					$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
					$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
					$html .= '</svg>';
					$html .= '</button>';
				}

				$html .= '</div>';

				// Add nudity warning if procedure has nudity flag
				if ( $procedure_nudity ) {
					$html .= '<div class="brag-book-gallery-nudity-warning">';
					$html .= '<div class="brag-book-gallery-nudity-warning-content">';
					$html .= '<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>';
					$html .= '<p class="brag-book-gallery-nudity-warning-caption">';
					$html .= 'This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.';
					$html .= '</p>';
					$html .= '<button class="brag-book-gallery-nudity-warning-button">Proceed</button>';
					$html .= '</div>';
					$html .= '</div>';
				}

				$html .= '<a href="' . esc_url( $case_url ) . '" class="brag-book-gallery-case-link" data-case-id="' . esc_attr( $case_id ) . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '">';
				$html .= '<picture class="brag-book-gallery-picture">';
				$html .= '<img src="' . esc_url( $single_image ) . '" ';
				$html .= 'alt="Case ' . esc_attr( $case_id ) . '" ';
				$html .= 'loading="lazy" ';
				$html .= 'data-image-type="single" ';
				$html .= 'data-image-url="' . esc_attr( $single_image ) . '" ';
				$html .= $procedure_nudity ? 'class="brag-book-gallery-nudity-blur" ' : '';
				$html .= 'onload="this.closest(\'.brag-book-gallery-image-container\').querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';" />';
				$html .= '</picture>';
				$html .= '</a>';
				$html .= '</div>';
				$html .= '</div>';
				$html .= '</div>'; // Close case-images
			} else {
				// Fallback to placeholder if no single image
				$html .= '<div class="brag-book-gallery-case-image-placeholder">';
				$html .= '<a href="' . esc_url( $case_url ) . '" class="brag-book-gallery-case-link" data-case-id="' . esc_attr( $case_id ) . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '">';
				$html .= '<span>No image available</span>';
				$html .= '</a>';
				$html .= '</div>';
			}
		} else {
			// Before/After mode
			if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
				$first_photoset = reset( $case['photoSets'] );

				// Extract before and after images
				$before_image = ! empty( $first_photoset['beforeLocationUrl'] ) ? $first_photoset['beforeLocationUrl'] : '';
				$after_image  = ! empty( $first_photoset['afterLocationUrl1'] ) ? $first_photoset['afterLocationUrl1'] : '';

				// Display images side by side with synchronized heights
				$html .= '<div class="brag-book-gallery-case-images before-after">';

				// Before image container
				if ( $before_image ) {
					$html .= '<div class="brag-book-gallery-before-image">';
					$html .= '<div class="brag-book-gallery-image-container">';
					$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';
					$html .= '<div class="brag-book-gallery-case-image-label">Before</div>';
					$html .= '<a href="' . esc_url( $case_url ) . '" class="brag-book-gallery-case-link" data-case-id="' . esc_attr( $case_id ) . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '">';
					$html .= '<picture class="brag-book-gallery-picture">';
					$html .= '<img src="' . esc_url( $before_image ) . '" ';
					$html .= 'alt="Before - Case ' . esc_attr( $case_id ) . '" ';
					$html .= 'loading="lazy" ';
					$html .= 'data-image-type="before" ';
					$html .= $procedure_nudity ? 'class="brag-book-gallery-nudity-blur" ' : '';
					$html .= 'onload="window.syncImageHeights(this); this.closest(\'.brag-book-gallery-image-container\').querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';" />';
					$html .= '</picture>';
					$html .= '</a>';
					$html .= '</div>';
					$html .= '</div>';
				} else {
					$html .= '<div class="brag-book-gallery-before-placeholder">';
					$html .= '<div class="brag-book-gallery-placeholder-container">';
					$html .= '<span>Before</span>';
					$html .= '</div>';
					$html .= '</div>';
				}

				// After image container with share and heart buttons
				if ( $after_image ) {
					$html .= '<div class="brag-book-gallery-after-image">';
					$html .= '<div class="brag-book-gallery-image-container">';
					$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';
					$html .= '<div class="brag-book-gallery-case-image-label">After</div>';

					// Add action buttons (share and heart)
					$html .= '<div class="brag-book-gallery-item-actions">';

					// Heart/Favorite button
					$html .= '<button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
					$html .= '<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24">';
					$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
					$html .= '</svg>';
					$html .= '</button>';

					// Share button (conditional)
					$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
					if ( 'yes' === $enable_sharing ) {
						$html .= '<button class="brag-book-gallery-share-button" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Share this image">';
						$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
						$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
						$html .= '</svg>';
						$html .= '</button>';
					}

					$html .= '</div>';

					$html .= '<a href="' . esc_url( $case_url ) . '" class="brag-book-gallery-case-link" data-case-id="' . esc_attr( $case_id ) . '" data-procedure-ids="' . esc_attr( $procedure_ids ) . '">';
					$html .= '<picture class="brag-book-gallery-picture">';
					$html .= '<img src="' . esc_url( $after_image ) . '" ';
					$html .= 'alt="After - Case ' . esc_attr( $case_id ) . '" ';
					$html .= 'loading="lazy" ';
					$html .= 'data-image-type="after" ';
					$html .= $procedure_nudity ? 'class="brag-book-gallery-nudity-blur" ' : '';
					$html .= 'onload="window.syncImageHeights(this); this.closest(\'.brag-book-gallery-image-container\').querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';" />';
					$html .= '</picture>';
					$html .= '</a>';
					$html .= '</div>';
					$html .= '</div>';
				} else {
					$html .= '<div class="brag-book-gallery-after-placeholder">';
					$html .= '<div class="brag-book-gallery-placeholder-container">';
					$html .= '<span>After</span>';
					$html .= '</div>';
					$html .= '</div>';
				}

				// Add nudity warning if procedure has nudity flag
				if ( $procedure_nudity ) {
					$html .= '<div class="brag-book-gallery-nudity-warning">';
					$html .= '<div class="brag-book-gallery-nudity-warning-content">';
					$html .= '<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>';
					$html .= '<p class="brag-book-gallery-nudity-warning-caption">';
					$html .= 'This procedure may contain nudity or sensitive content. Click to proceed if you wish to view.';
					$html .= '</p>';
					$html .= '<button class="brag-book-gallery-nudity-warning-button">Proceed</button>';
					$html .= '</div>';
					$html .= '</div>';
				}

				$html .= '</div>'; // Close case-images
			}
		}

		// Add case details section
		// Use the gallery's main procedure type for display, not individual case procedures
		$procedure_display_name = 'Case';
		
		// Try to get the procedure name from the URL context or filter
		if ( ! empty( $procedure_context ) ) {
			// Convert slug back to proper display format
			$procedure_display_name = HTML_Renderer::format_procedure_display_name( $procedure_context );
		} elseif ( ! empty( $procedure_slug ) ) {
			// Use the procedure slug we determined earlier
			$procedure_display_name = HTML_Renderer::format_procedure_display_name( $procedure_slug );
		} else {
			// Fallback to first procedure from case data if no context
			if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
				$first_procedure = reset( $case['procedures'] );
				$procedure_display_name = $first_procedure['name'] ?? 'Case';
			}
		}
		$html .= '<details class="brag-book-gallery-case-details">';
		$html .= '<summary class="brag-book-gallery-case-summary">';
		$html .= '<div class="brag-book-gallery-case-summary-left">';
		$html .= '<span class="brag-book-gallery-procedure-name">' . esc_html( $procedure_display_name ) . '</span>';
		$html .= '<span class="brag-book-gallery-case-number">Case #' . esc_html( $case_id ) . '</span>';
		$html .= '</div>';
		$html .= '<div class="brag-book-gallery-case-summary-right">';
		$html .= '<span class="brag-book-gallery-more-details">More Details <span class="plus">+</span></span>';
		$html .= '</div>';
		$html .= '</summary>';

		// Details content - list of procedures
		$html .= '<div class="brag-book-gallery-case-details-content">';
		$html .= '<h4>Procedures Performed:</h4>';
		$html .= '<ul class="brag-book-gallery-procedures-list">';

		if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			foreach ( $case['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$html .= '<li>' . esc_html( $procedure['name'] ) . '</li>';
				}
			}
		} else {
			// Fallback to display name if no procedures list
			$html .= '<li>' . esc_html( $procedure_display_name ) . '</li>';
		}

		$html .= '</ul>';
		$html .= '</div>'; // Close details content
		$html .= '</details>'; // Close details
		$html .= '</article>'; // Close case card

		return $html;
	}

	/**
	 * Render pagination links.
	 *
	 * @param array $pagination Pagination data.
	 * @param string $base_path Base URL path.
	 * @param string $filter_procedure Current procedure filter.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_pagination( array $pagination, string $base_path, string $filter_procedure ): string {
		$current_page = $pagination['currentPage'] ?? 1;
		$total_pages  = $pagination['totalPages'] ?? 1;

		if ( $total_pages <= 1 ) {
			return '';
		}

		ob_start();

		// Previous link
		if ( $current_page > 1 ) {
			$prev_url = $base_path;
			if ( $filter_procedure ) {
				$prev_url .= $filter_procedure . '/';
			}
			$prev_url .= '?page=' . ( $current_page - 1 );
			echo '<a href="' . esc_url( $prev_url ) . '">&laquo; Previous</a>';
		}

		// Page numbers
		for ( $i = 1; $i <= $total_pages; $i ++ ) {
			if ( $i == $current_page ) {
				echo '<span class="current">' . $i . '</span>';
			} else {
				$page_url = $base_path;
				if ( $filter_procedure ) {
					$page_url .= $filter_procedure . '/';
				}
				$page_url .= '?page=' . $i;
				echo '<a href="' . esc_url( $page_url ) . '">' . $i . '</a>';
			}
		}

		// Next link
		if ( $current_page < $total_pages ) {
			$next_url = $base_path;
			if ( $filter_procedure ) {
				$next_url .= $filter_procedure . '/';
			}
			$next_url .= '?page=' . ( $current_page + 1 );
			echo '<a href="' . esc_url( $next_url ) . '">Next &raquo;</a>';
		}

		return ob_get_clean();
	}

	/**
	 * Render single case view.
	 *
	 * @param string $case_id Case ID.
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_single_case( string $case_id, array $atts ): string {
		// Get API configuration
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens        = get_option( 'brag_book_gallery_api_token' ) ?: [];
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : '';
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids        = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : '';
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'render_single_case - Looking for case ID: ' . $case_id );
			error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
			error_log( 'Website Property ID: ' . $atts['website_property_id'] );
		}

		// First try to get case from cached data
		$cache_key   = 'brag_book_all_cases_' . md5( $atts['api_token'] . $atts['website_property_id'] );
		$cached_data = get_transient( $cache_key );
		$case_data   = null;

		// Check if cache exists and has valid data
		if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) && count( $cached_data['data'] ) > 0 ) {
			// Search for the case in cached data
			foreach ( $cached_data['data'] as $case ) {
				// Use loose comparison to handle string/int mismatch
				if ( isset( $case['id'] ) && ( strval( $case['id'] ) === strval( $case_id ) ) ) {
					$case_data = $case;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Case found in cache!' );
					}
					break;
				}
			}
			if ( ! $case_data && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Case not found in cache. Total cached cases: ' . count( $cached_data['data'] ) );
				// Log sample of case IDs for debugging
				$sample_ids = array_slice( array_column( $cached_data['data'], 'id' ), 0, 5 );
				error_log( 'Sample case IDs in cache: ' . implode( ', ', $sample_ids ) );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				if ( $cached_data && isset( $cached_data['data'] ) && count( $cached_data['data'] ) === 0 ) {
					error_log( 'Cache exists but is empty - will skip cache and fetch from API' );
					// Clear the empty cache
					delete_transient( $cache_key );
				} else {
					error_log( 'No valid cached data available' );
				}
			}
		}

		// If not found in cache, try the direct API endpoint (same as AJAX handler)
		if ( empty( $case_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Trying direct API endpoint for case: ' . $case_id );
				error_log( 'API Token (first 10 chars): ' . substr( $atts['api_token'], 0, 10 ) );
				error_log( 'Website Property ID: ' . $atts['website_property_id'] );
			}

			// Use the same API endpoint format as the AJAX handler
			$api_url = "https://app.bragbookgallery.com/api/plugin/combine/cases/{$case_id}";

			$request_body = [
				'apiTokens'          => [ $atts['api_token'] ],
				'websitePropertyIds' => [ intval( $atts['website_property_id'] ) ],
			];

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API URL: ' . $api_url );
				error_log( 'Request body: ' . wp_json_encode( $request_body ) );
			}

			// Get API timeout from settings
			$api_timeout = intval( get_option( 'brag_book_gallery_api_timeout', 30 ) );

			$response = wp_remote_post( $api_url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $atts['api_token'],
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
				'timeout' => $api_timeout,
			] );

			if ( ! is_wp_error( $response ) ) {
				$status_code = wp_remote_retrieve_response_code( $response );
				$body        = wp_remote_retrieve_body( $response );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'API Response status: ' . $status_code );
					error_log( 'API Response body (first 500 chars): ' . substr( $body, 0, 500 ) );
				}

				$response_data = json_decode( $body, true );

				if ( ! empty( $response_data['data'] ) && is_array( $response_data['data'] ) ) {
					// Get the first case from the data array
					$case_data = $response_data['data'][0];
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Case found via direct API!' );
						error_log( 'Case ID from response: ' . ( $case_data['id'] ?? 'NO ID' ) );
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'API response empty or invalid' );
						error_log( 'Response structure: ' . print_r( array_keys( $response_data ?? [] ), true ) );
					}
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'API request failed: ' . $response->get_error_message() );
				}
			}
		}

		// If still not found, try fetching from the general cases endpoint
		if ( empty( $case_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Trying to fetch all cases as last resort' );
			}
			// Get all cases and search for our specific case
			$all_cases = Data_Fetcher::get_all_cases_for_filtering( $atts['api_token'], $atts['website_property_id'] );
			if ( ! empty( $all_cases['data'] ) ) {
				foreach ( $all_cases['data'] as $case ) {
					// Use string comparison to handle type mismatch
					if ( isset( $case['id'] ) && ( strval( $case['id'] ) === strval( $case_id ) ) ) {
						$case_data = $case;
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'Case found in all cases!' );
						}
						break;
					}
				}
				if ( ! $case_data && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Case not found in all cases. Total cases: ' . count( $all_cases['data'] ) );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'No cases returned from get_all_cases_for_filtering' );
				}
			}
		}

		if ( empty( $case_data ) ) {
			$error_message = '<div class="brag-book-gallery-case-error">';
			$error_message .= '<p><strong>' . esc_html__( 'Case not found.', 'brag-book-gallery' ) . '</strong></p>';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error_message .= '<div class="brag-book-gallery-debug-error">';
				$error_message .= '<p><strong>Debug Information:</strong></p>';
				$error_message .= '<ul>';
				$error_message .= '<li>Case ID requested: ' . esc_html( $case_id ) . '</li>';
				$error_message .= '<li>API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) . '</li>';
				$error_message .= '<li>Website Property ID: ' . esc_html( $atts['website_property_id'] ) . '</li>';
				$error_message .= '<li>Cache checked: ' . ( $cached_data ? 'Yes (with ' . count( $cached_data['data'] ?? [] ) . ' cases)' : 'No cache available' ) . '</li>';
				$error_message .= '<li>Current URL: ' . esc_html( $_SERVER['REQUEST_URI'] ?? 'N/A' ) . '</li>';
				$error_message .= '</ul>';
				$error_message .= '<p>To debug further, visit: <a href="/wp-content/plugins/brag-book-gallery/debug-case-url.php?case_id=' . esc_attr( $case_id ) . '" target="_blank">Debug Case URL</a></p>';
				$error_message .= '</div>';
			}

			$error_message .= '</div>';

			return $error_message;
		}

		// Get current page URL for back link
		$current_url    = get_permalink();
		$base_path      = parse_url( $current_url, PHP_URL_PATH ) ?: '/';
		$procedure_name = $case_data['technique'] ?? 'Case Details';

		ob_start();
		?>
		<div class="brag-book-gallery-single-case">
			<div class="brag-book-gallery-brag-book-gallery-case-header">
				<a href="<?php echo esc_url( $base_path ); ?>"
				   class="brag-book-gallery-case-back">
					&larr; Back to Gallery
				</a>
				<h1><?php echo esc_html( $procedure_name ); ?></h1>
			</div>

			<div class="brag-book-gallery-case-details">
				<div class="brag-book-gallery-case-meta-info">
					<?php if ( ! empty( $case_data['age'] ) ) : ?>
						<span
							class="meta-item">Age: <?php echo esc_html( $case_data['age'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $case_data['gender'] ) ) : ?>
						<span
							class="meta-item"><?php echo esc_html( ucfirst( $case_data['gender'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $case_data['ethnicity'] ) ) : ?>
						<span
							class="meta-item"><?php echo esc_html( $case_data['ethnicity'] ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $case_data['photoSets'] ) ) : ?>
					<div class="brag-book-gallery-case-images">
						<?php foreach ( $case_data['photoSets'] as $photoSet ) : ?>
							<div class="brag-book-gallery-photo-set">
								<?php if ( ! empty( $photoSet['beforeLocationUrl'] ) ) : ?>
									<div class="before-image">
										<h3>Before</h3>
										<img
											src="<?php echo esc_url( $photoSet['beforeLocationUrl'] ); ?>"
											alt="Before" loading="lazy">
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $photoSet['afterLocationUrl1'] ) ) : ?>
									<div class="after-image">
										<h3>After</h3>
										<img
											src="<?php echo esc_url( $photoSet['afterLocationUrl1'] ); ?>"
											alt="After" loading="lazy">
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $case_data['details'] ) ) : ?>
					<div class="brag-book-gallery-case-description">
						<h2>Case Details</h2>
						<?php echo wp_kses_post( $case_data['details'] ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}


	/**
	 * Case details shortcode handler
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 *
	 * @return string HTML output.
	 * @since 3.0.0
	 */
	public static function case_details_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts( [
			'case_id'   => '',
			'procedure' => '',
		], $atts, 'brag_book_gallery_case' );

		// If no case_id provided, try to get from URL
		if ( empty( $atts['case_id'] ) ) {
			$atts['case_id'] = get_query_var( 'case_id' );
		}

		if ( empty( $atts['case_id'] ) ) {
			return '<div class="brag-book-gallery-error">Case ID not specified.</div>';
		}

		// Start output buffering
		ob_start();
		?>
		<div class="brag-book-gallery-case-details-container"
			 data-case-id="<?php echo esc_attr( $atts['case_id'] ); ?>">
			<div class="brag-book-gallery-loading">
				Loading case details...
			</div>
		</div>

		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				const container = document.querySelector( '.brag-book-gallery-case-details-container' );
				const caseId = container.dataset.caseId;

				if ( !caseId ) {
					container.innerHTML = '<div class="brag-book-gallery-error">Invalid case ID.</div>';
					return;
				}

				// Load case details via AJAX
				fetch( ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( {
						action: 'load_case_details',
						case_id: caseId,
						nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_nonce' ); ?>'
					} )
				} )
					.then( response => response.json() )
					.then( data => {
						if ( data.success ) {
							container.innerHTML = data.data.html;
						} else {
							container.innerHTML = '<div class="brag-book-gallery-error">Failed to load case details.</div>';
						}
					} )
					.catch( error => {
						container.innerHTML = '<div class="brag-book-gallery-error">Error loading case details.</div>';
					} );
			} );
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the My Favorites page.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	private static function render_favorites_page( array $atts ): string {
		// Use the new favorites shortcode which handles API-based favorites
		return self::favorites_shortcode( $atts );
	}

	/**
	 * Render consultation form styles.
	 *
	 * @since 3.0.0
	 * @return string CSS styles.
	 */
	private static function render_consultation_styles(): string {
		ob_start();
		?>
		<style>
		.brag-book-gallery-consultation-form {
			max-width: 600px;
			margin: 2rem auto;
			padding: 2rem;
			background: #f9f9f9;
			border-radius: 8px;
		}

		.brag-book-gallery-consultation-form h2 {
			margin-top: 0;
			margin-bottom: 1.5rem;
			font-size: 1.5rem;
		}

		.brag-book-gallery-consultation-form .form-group {
			margin-bottom: 1.5rem;
		}

		.brag-book-gallery-consultation-form label {
			display: block;
			margin-bottom: 0.5rem;
			font-weight: bold;
		}

		.brag-book-gallery-consultation-form input[type="text"],
		.brag-book-gallery-consultation-form input[type="email"],
		.brag-book-gallery-consultation-form input[type="tel"],
		.brag-book-gallery-consultation-form select,
		.brag-book-gallery-consultation-form textarea {
			width: 100%;
			padding: 0.75rem;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 1rem;
		}

		.brag-book-gallery-consultation-form textarea {
			min-height: 120px;
			resize: vertical;
		}

		.brag-book-gallery-consultation-form button {
			background: #333;
			color: white;
			padding: 0.75rem 2rem;
			border: none;
			border-radius: 4px;
			font-size: 1rem;
			cursor: pointer;
			transition: background-color 0.3s;
		}

		.brag-book-gallery-consultation-form button:hover {
			background: #555;
		}

		.brag-book-gallery-consultation-form .form-message {
			padding: 1rem;
			margin-bottom: 1rem;
			border-radius: 4px;
		}

		.brag-book-gallery-consultation-form .form-success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}

		.brag-book-gallery-consultation-form .form-error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		</style>
		<?php
		return ob_get_clean();
	}

	/**
	 * Favorites shortcode handler.
	 *
	 * Displays user's favorited cases.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function favorites_shortcode( $atts ): string {
		// Extract shortcode attributes
		$atts = shortcode_atts(
			[
				'email' => '', // User can provide email or it will use a form
			],
			$atts,
			'brag_book_favorites'
		);

		// Enqueue necessary scripts and styles
		$assets = new Assets();
		$assets->enqueue_frontend_assets();

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			return '<div class="brag-book-gallery-error">Please configure the plugin API settings.</div>';
		}

		// Start building the HTML
		$html = '<div class="brag-book-gallery-favorites-wrapper">';
		$html .= '<div class="brag-book-gallery-favorites-container">';
		
		// Add title
		$html .= '<h2 class="brag-book-gallery-favorites-title">My Favorites</h2>';
		
		// Add email input form if no email provided
		if ( empty( $atts['email'] ) ) {
			$html .= '<div class="brag-book-gallery-favorites-form-wrapper">';
			$html .= '<p class="brag-book-gallery-favorites-description">Enter your email to view your saved favorites:</p>';
			$html .= '<form class="brag-book-gallery-favorites-lookup-form" data-form="favorites-lookup">';
			$html .= '<div class="brag-book-gallery-form-group">';
			$html .= '<input type="email" class="brag-book-gallery-form-input" name="email" placeholder="Your email address" required>';
			$html .= '<button type="submit" class="brag-book-gallery-form-submit">View My Favorites</button>';
			$html .= '</div>';
			$html .= '</form>';
			$html .= '</div>';
		}

		// Container for favorites display
		$html .= '<div class="brag-book-gallery-favorites-view" id="favorites-view" style="display: none;">';
		$html .= '<div class="brag-book-gallery-favorites-loading" style="display: none;">Loading your favorites...</div>';
		$html .= '<div class="brag-book-gallery-favorites-empty" style="display: none;">You haven\'t saved any favorites yet.</div>';
		$html .= '<div class="brag-book-gallery-case-grid brag-book-gallery-favorites-grid" id="favorites-grid"></div>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</div>';

		// Add JavaScript to handle the form and load favorites
		$html .= '<script>
		document.addEventListener("DOMContentLoaded", function() {
			const favoritesForm = document.querySelector("[data-form=\"favorites-lookup\"]");
			const favoritesView = document.getElementById("favorites-view");
			const favoritesGrid = document.getElementById("favorites-grid");
			const loadingDiv = document.querySelector(".brag-book-gallery-favorites-loading");
			const emptyDiv = document.querySelector(".brag-book-gallery-favorites-empty");
			
			// Auto-load if email is provided
			const providedEmail = "' . esc_js( $atts['email'] ) . '";
			if (providedEmail) {
				loadFavorites(providedEmail);
			}
			
			// Handle form submission
			if (favoritesForm) {
				favoritesForm.addEventListener("submit", function(e) {
					e.preventDefault();
					const formData = new FormData(favoritesForm);
					const email = formData.get("email");
					if (email) {
						loadFavorites(email);
					}
				});
			}
			
			function loadFavorites(email) {
				// Show loading state
				favoritesView.style.display = "block";
				loadingDiv.style.display = "block";
				emptyDiv.style.display = "none";
				favoritesGrid.style.display = "none";
				favoritesGrid.innerHTML = "";
				
				// Prepare request data
				const requestData = new FormData();
				requestData.append("action", "brag_book_get_favorites_list");
				requestData.append("nonce", window.bragBookGalleryConfig?.nonce || "");
				requestData.append("email", email);
				
				// Make AJAX request
				fetch(window.bragBookGalleryConfig?.ajaxUrl || "/wp-admin/admin-ajax.php", {
					method: "POST",
					body: requestData
				})
				.then(response => response.json())
				.then(response => {
					loadingDiv.style.display = "none";
					
					if (response.success && response.data.cases && response.data.cases.length > 0) {
						// Display the cases
						favoritesGrid.innerHTML = response.data.html;
						favoritesGrid.style.display = "grid";
					} else {
						// No favorites found
						emptyDiv.style.display = "block";
					}
				})
				.catch(error => {
					console.error("Error loading favorites:", error);
					loadingDiv.style.display = "none";
					emptyDiv.textContent = "An error occurred while loading your favorites. Please try again.";
					emptyDiv.style.display = "block";
				});
			}
		});
		</script>';

		return $html;
	}


}
