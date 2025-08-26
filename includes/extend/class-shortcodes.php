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
	private const DEFAULT_CAROUSEL_LIMIT = 8;

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

		// Register carousel shortcodes using the handler class
		add_shortcode(
			'brag_book_carousel',
			[ Carousel_Shortcode_Handler::class, 'handle' ]
		);

		// Register backwards compatibility shortcode for old carousel
		add_shortcode(
			'bragbook_carousel_shortcode',
			[ Carousel_Shortcode_Handler::class, 'handle_legacy' ]
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
	 * Render main gallery shortcode using modern markup.
	 * Delegates to Gallery_Shortcode_Handler for implementation.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Rendered HTML.
	 * @since 3.0.0
	 */
	public static function main_gallery_shortcode( array $atts ): string {
		// Delegate to Gallery_Shortcode_Handler which contains the full implementation
		return Gallery_Shortcode_Handler::handle( $atts );
	}
	public static function carousel_shortcode( array $atts ): string {
		// Delegate to the carousel handler
		return Carousel_Shortcode_Handler::handle( $atts );
	}

	/**
	 * Handle legacy carousel shortcode for backwards compatibility.
	 * 
	 * @deprecated 3.0.0 Use Carousel_Shortcode_Handler::handle_legacy() instead.
	 *
	 * Maps old [bragbook_carousel_shortcode] attributes to new [brag_book_carousel] format.
	 * Old format: [bragbook_carousel_shortcode procedure="nonsurgical-facelift" start="1" limit="10" title="0" details="0" website_property_id="89"]
	 *
	 * @param array $atts Legacy shortcode attributes.
	 * @return string Carousel HTML output.
	 * @since 3.0.0
	 */
	public static function legacy_carousel_shortcode( array $atts ): string {
		// Delegate to the carousel handler
		return Carousel_Shortcode_Handler::handle_legacy( $atts );
	}

	/**
	 * Validate carousel configuration and settings.
	 * 
	 * @deprecated 3.0.0 Moved to Carousel_Shortcode_Handler.
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
		$procedure_slug = '';
		if ( ! empty( $atts['procedure_id'] ) ) {
			// Check if it's numeric (ID) or string (slug)
			if ( is_numeric( $atts['procedure_id'] ) ) {
				$procedure_id = absint( $atts['procedure_id'] );
				// We don't have the slug in this case, it will be determined from the API response
			} else {
				// It's a slug - save it and convert to an ID using sidebar data
				$procedure_slug = sanitize_title( $atts['procedure_id'] );
				$procedure_id = self::get_procedure_id_from_slug(
					$procedure_slug,
					$atts['api_token'],
					$atts['website_property_id']
				);

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAG book Carousel: Converted slug "' . $procedure_slug . '" to ID: ' . ( $procedure_id ?: 'not found' ) );
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
				'procedure_slug'      => $procedure_slug,
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
	 * @deprecated 3.0.0 Moved to Carousel_Shortcode_Handler.
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
	 * @deprecated 3.0.0 Moved to Carousel_Shortcode_Handler.
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
					$limit = absint( $config['limit'] ?? self::DEFAULT_CAROUSEL_LIMIT );
					$procedure_slug = ! empty( $config['procedure_slug'] ) ? $config['procedure_slug'] : '';
					echo self::generate_carousel_items_from_data( $carousel_data['data'] ?? $carousel_data, $limit, $procedure_slug );
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
	 * @deprecated 3.0.0 Moved to Carousel_Shortcode_Handler.
	 *
	 * @param array $items Carousel items data.
	 * @param int $max_slides Maximum number of slides.
	 * @param string $procedure_slug Procedure slug for context.
	 *
	 * @return string Generated HTML for carousel items.
	 * @since 3.0.0
	 */
	private static function generate_carousel_items_from_data( array $items, int $max_slides = 8, string $procedure_slug = '' ): string {
		if ( empty( $items ) ) {
			return '';
		}

		$html_parts  = [];
		$slide_index = 0;
		$slide_count = 0;

		// Loop through each case
		foreach ( $items as $case ) {
			// Check for both possible data structures: photoSets and photos
			$photo_sets = $case['photoSets'] ?? $case['photos'] ?? [];

			// If photo_sets is empty, skip this case
			if ( empty( $photo_sets ) ) {
				continue;
			}

			foreach ( $photo_sets as $photo ) {
				// Stop if we've reached the maximum number of slides
				if ( $slide_count >= $max_slides ) {
					break 2; // Break out of both loops
				}

				$slide_index++;
				$slide_count++;
				$html_parts[] = HTML_Renderer::generate_carousel_slide_from_photo( $photo, $case, $slide_index, $procedure_slug );
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
				'<a href="%s" class="brag-book-gallery-case-card-link" data-case-id="%s">',
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
		// Delegate to Cases_Shortcode_Handler
		return Cases_Shortcode_Handler::handle( $atts );
	}

	/**
	 * @deprecated 3.0.0 Moved to Cases_Shortcode_Handler::render_cases_grid()
	 */
	private static function render_cases_grid( array $cases_data, array $atts, string $filter_procedure = '' ): string {
		// This method is now a thin wrapper for backwards compatibility
		return Cases_Shortcode_Handler::render_cases_grid( $cases_data, $atts, $filter_procedure );
	}

	/**
	 * @deprecated 3.0.0 Moved to Cases_Shortcode_Handler::render_single_case()
	 */
	private static function render_single_case( string $case_id, array $atts ): string {
		// This method is now a thin wrapper for backwards compatibility
		return Cases_Shortcode_Handler::render_single_case( $case_id, $atts );
	}

	/**
	 * Render AJAX gallery case card
	 *
	 * Used by AJAX handlers via reflection. Delegates to Cases_Shortcode_Handler.
	 *
	 * @deprecated 3.0.0 Moved to Cases_Shortcode_Handler.
	 *
	 * @param array  $case               Case data.
	 * @param string $image_display_mode Image display mode.
	 * @param bool   $has_nudity         Whether case has nudity.
	 * @param string $procedure_context  Procedure context.
	 *
	 * @return string HTML output.
	 * @since 3.0.0
	 */
	private static function render_ajax_gallery_case_card( array $case, string $image_display_mode, bool $has_nudity = false, string $procedure_context = '' ): string {
		// Delegate to Cases_Shortcode_Handler AJAX method for main gallery rendering
		return Cases_Shortcode_Handler::render_ajax_case_card( $case, $image_display_mode, $has_nudity, $procedure_context );
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
		// Delegate to Cases_Shortcode_Handler
		return Cases_Shortcode_Handler::handle_case_details( $atts );
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
			$html .= '<button type="submit" class="brag-book-gallery-button brag-book-gallery-button--full" data-action="form-submit">View My Favorites</button>';
			$html .= '</div>';
			$html .= '</form>';
			$html .= '</div>';
		}

		// Container for favorites display
		$html .= '<div class="brag-book-gallery-favorites-view" id="favorites-view" style="display: none;">';
		$html .= '<div class="brag-book-gallery-favorites-loading" style="display: none;">Loading your favorites...</div>';
		$html .= '<div class="brag-book-gallery-favorites-empty" style="display: none;">You haven\'t saved any favorites yet.</div>';
		$html .= '<div class="brag-book-gallery-case-grid masonry-layout brag-book-gallery-favorites-grid" id="favorites-grid"></div>';
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
