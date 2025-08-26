<?php
/**
 * Carousel Shortcode Handler Class
 *
 * Manages carousel shortcode functionality for displaying case image carousels.
 * Handles both modern and legacy carousel shortcode formats.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Extend;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carousel Shortcode Handler Class
 *
 * Manages the [brag_book_carousel] and [brag_book_carousel_shortcode] shortcodes.
 * Provides carousel functionality with configurable display options and API integration.
 *
 * @since 3.0.0
 */
final class Carousel_Shortcode_Handler {

	/**
	 * Default limit for carousel items
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_CAROUSEL_LIMIT = 8;

	/**
	 * Default start index for carousel
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_START_INDEX = 1;

	/**
	 * Cache group for carousel data
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_carousel';

	/**
	 * Cache expiration time in seconds
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600; // 1 hour

	/**
	 * Handle the carousel shortcode
	 *
	 * Processes the [brag_book_carousel] shortcode and returns rendered HTML.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Carousel HTML or error message.
	 * @since 3.0.0
	 *
	 */
	public static function handle( array $atts ): string {
		// Get API configuration from settings.
		$api_tokens           = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		// Get the first token and property ID as defaults.
		$default_token = '';
		if ( ! empty( $api_tokens ) ) {
			if ( is_array( $api_tokens ) && isset( $api_tokens[0] ) ) {
				$default_token = sanitize_text_field( $api_tokens[0] );
			} elseif ( is_string( $api_tokens ) ) {
				$default_token = sanitize_text_field( $api_tokens );
			}
		}

		$default_property_id = '';
		if ( ! empty( $website_property_ids ) ) {
			if ( is_array( $website_property_ids ) && isset( $website_property_ids[0] ) ) {
				$default_property_id = sanitize_text_field( $website_property_ids[0] );
			} elseif ( is_string( $website_property_ids ) ) {
				$default_property_id = sanitize_text_field( $website_property_ids );
			}
		}

		// Parse and validate shortcode attributes.
		$atts = shortcode_atts(
			array(
				'api_token'           => $default_token,
				'website_property_id' => $default_property_id,
				'limit'               => self::DEFAULT_CAROUSEL_LIMIT,
				'start'               => self::DEFAULT_START_INDEX,
				'procedure_id'        => '',
				'procedure'           => '',
				// Support both procedure and procedure_id.
				'member_id'           => '',
				'show_controls'       => 'true',
				'show_pagination'     => 'true',
				'auto_play'           => 'false',
				'class'               => '',
			),
			$atts,
			'brag_book_carousel'
		);

		// If procedure is provided but not procedure_id, use procedure.
		if ( empty( $atts['procedure_id'] ) && ! empty( $atts['procedure'] ) ) {
			$atts['procedure_id'] = $atts['procedure'];
		}

		// Validate configuration.
		$validation = self::validate_configuration( $atts );
		if ( isset( $validation['error'] ) && $validation['error'] ) {
			return sprintf(
				'<p class="brag-book-carousel-error">%s</p>',
				esc_html( $validation['message'] )
			);
		}

		$config = $validation['config'];

		// Get carousel data from API.
		$carousel_data = Data_Fetcher::get_carousel_data_from_api( $config );

		// Localize script data for JavaScript functionality.
		Asset_Manager::localize_carousel_script( $config );

		// Generate and return carousel HTML.
		return self::render_html( $carousel_data, $config );
	}

	/**
	 * Handle legacy carousel shortcode
	 *
	 * Maps old [brag_book_carousel_shortcode] attributes to new format for backwards compatibility.
	 * Old format: [brag_book_carousel_shortcode procedure="nonsurgical-facelift" start="1" limit="10" title="0" details="0" website_property_id="89"]
	 *
	 * @param array $atts Legacy shortcode attributes.
	 *
	 * @return string Carousel HTML output.
	 * @since 3.0.0
	 *
	 */
	public static function handle_legacy( array $atts ): string {
		// Parse legacy attributes.
		$legacy_atts = shortcode_atts(
			array(
				'procedure'           => '',
				'start'               => '1',
				'limit'               => '10',
				'title'               => '0',
				'details'             => '0',
				'website_property_id' => '',
			),
			$atts,
			'brag_book_carousel_shortcode'
		);

		// Map legacy attributes to new format.
		$new_atts = array();

		// Get API token from settings (wasn't in old shortcode).
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		if ( ! empty( $api_tokens ) ) {
			if ( is_array( $api_tokens ) && isset( $api_tokens[0] ) ) {
				$new_atts['api_token'] = sanitize_text_field( $api_tokens[0] );
			} elseif ( is_string( $api_tokens ) ) {
				$new_atts['api_token'] = sanitize_text_field( $api_tokens );
			}
		}

		// Map website_property_id directly.
		if ( ! empty( $legacy_atts['website_property_id'] ) ) {
			$new_atts['website_property_id'] = sanitize_text_field( $legacy_atts['website_property_id'] );
		}

		// Map limit and start.
		$new_atts['limit'] = absint( $legacy_atts['limit'] );
		$new_atts['start'] = absint( $legacy_atts['start'] );

		// Map procedure to procedure_id if provided.
		if ( ! empty( $legacy_atts['procedure'] ) ) {
			$new_atts['procedure_id'] = sanitize_title( $legacy_atts['procedure'] );
		}

		// Map title and details to show_controls and show_pagination.
		// In the old version, title="0" meant hide title, details="0" meant hide details.
		$new_atts['show_controls']   = ( '0' !== $legacy_atts['title'] ) ? 'true' : 'false';
		$new_atts['show_pagination'] = ( '0' !== $legacy_atts['details'] ) ? 'true' : 'false';

		// Call the new carousel handler with mapped attributes.
		return self::handle( $new_atts );
	}

	/**
	 * Validate carousel configuration
	 *
	 * Validates and sanitizes carousel configuration parameters.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return array Validation result with config or error.
	 * @since 3.0.0
	 *
	 */
	private static function validate_configuration( array $atts ): array {
		// Get API configuration if not provided in shortcode.
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
			if ( is_array( $api_tokens ) && ! empty( $api_tokens[0] ) ) {
				$atts['api_token'] = sanitize_text_field( $api_tokens[0] );
			} elseif ( is_string( $api_tokens ) && ! empty( $api_tokens ) ) {
				$atts['api_token'] = sanitize_text_field( $api_tokens );
			} else {
				$atts['api_token'] = '';
			}
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );
			if ( is_array( $website_property_ids ) && ! empty( $website_property_ids[0] ) ) {
				$atts['website_property_id'] = sanitize_text_field( $website_property_ids[0] );
			} elseif ( is_string( $website_property_ids ) && ! empty( $website_property_ids ) ) {
				$atts['website_property_id'] = sanitize_text_field( $website_property_ids );
			} else {
				$atts['website_property_id'] = '';
			}
		}

		// Validate required fields.
		if ( empty( $atts['api_token'] ) ) {
			return array(
				'error'   => true,
				'message' => __( 'API token is required for carousel.', 'brag-book-gallery' ),
			);
		}

		// Process procedure ID/slug.
		$procedure_id   = null;
		$procedure_slug = '';
		if ( ! empty( $atts['procedure_id'] ) ) {
			// Check if it's numeric (ID) or string (slug).
			if ( is_numeric( $atts['procedure_id'] ) ) {
				$procedure_id = absint( $atts['procedure_id'] );
			} else {
				// It's a slug - save it and convert to an ID using sidebar data.
				$procedure_slug = sanitize_title( $atts['procedure_id'] );
				$procedure_id   = self::get_procedure_id_from_slug(
					$procedure_slug,
					sanitize_text_field( $atts['api_token'] ),
					sanitize_text_field( $atts['website_property_id'] )
				);

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Carousel: Converted slug "' . $procedure_slug . '" to ID: ' . ( $procedure_id ?: 'not found' ) );
				}
			}
		}

		$member_id = ! empty( $atts['member_id'] ) ? absint( $atts['member_id'] ) : null;

		return array(
			'error'  => false,
			'config' => array(
				'api_token'           => sanitize_text_field( (string) $atts['api_token'] ),
				'website_property_id' => sanitize_text_field( (string) ( $atts['website_property_id'] ?? '' ) ),
				'limit'               => absint( $atts['limit'] ?? self::DEFAULT_CAROUSEL_LIMIT ),
				'start'               => absint( $atts['start'] ?? self::DEFAULT_START_INDEX ),
				'procedure_id'        => $procedure_id,
				'procedure_slug'      => $procedure_slug,
				'member_id'           => $member_id,
				'show_controls'       => filter_var( $atts['show_controls'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'show_pagination'     => filter_var( $atts['show_pagination'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'auto_play'           => filter_var( $atts['auto_play'] ?? false, FILTER_VALIDATE_BOOLEAN ),
				'class'               => sanitize_html_class( (string) ( $atts['class'] ?? '' ) ),
			),
		);
	}

	/**
	 * Get procedure ID from slug using sidebar data
	 *
	 * Converts a procedure slug to its corresponding ID using API sidebar data.
	 *
	 * @param string $slug Procedure slug to convert.
	 * @param string $api_token API token for authentication.
	 * @param string $website_property_id Website property ID.
	 *
	 * @return int|null Procedure ID or null if not found.
	 * @since 3.0.0
	 *
	 */
	private static function get_procedure_id_from_slug( string $slug, string $api_token, string $website_property_id ): ?int {
		// Sanitize inputs.
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return null;
		}

		// Try to get from cache first.
		$cache_key = 'procedure_id_' . md5( $slug . $api_token . $website_property_id );
		$cached_id = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached_id ) {
			return $cached_id > 0 ? $cached_id : null;
		}

		// Get sidebar data which contains procedure information.
		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );

		if ( empty( $sidebar_data ) ) {
			wp_cache_set( $cache_key, 0, self::CACHE_GROUP, self::CACHE_EXPIRATION );

			return null;
		}

		// Get the data array from the response.
		$categories = isset( $sidebar_data['data'] ) ? $sidebar_data['data'] : $sidebar_data;

		// Search through categories for the procedure.
		foreach ( $categories as $category ) {
			if ( ! empty( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					// Check if slug matches.
					if ( isset( $procedure['slugName'] ) && $procedure['slugName'] === $slug ) {
						// Return the first ID from the ids array.
						if ( ! empty( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
							$id = absint( $procedure['ids'][0] );
							wp_cache_set( $cache_key, $id, self::CACHE_GROUP, self::CACHE_EXPIRATION );

							return $id;
						}
					}
					// Also check by sanitized name as fallback.
					if ( isset( $procedure['name'] ) && sanitize_title( $procedure['name'] ) === $slug ) {
						if ( ! empty( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
							$id = absint( $procedure['ids'][0] );
							wp_cache_set( $cache_key, $id, self::CACHE_GROUP, self::CACHE_EXPIRATION );

							return $id;
						}
					}
				}
			}
		}

		// Not found - cache as 0.
		wp_cache_set( $cache_key, 0, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return null;
	}

	/**
	 * Render carousel HTML
	 *
	 * Generates the complete HTML structure for the carousel.
	 *
	 * @param array $carousel_data Data from API for carousel items.
	 * @param array $config Carousel configuration settings.
	 *
	 * @return string Generated carousel HTML.
	 * @since 3.0.0
	 *
	 */
	private static function render_html( array $carousel_data, array $config ): string {
		// Check if we have data in either format.
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
		$css_class   = 'brag-book-gallery-carousel-wrapper';
		if ( ! empty( $config['class'] ) ) {
			$css_class .= ' ' . sanitize_html_class( $config['class'] );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $css_class ); ?>"
			 data-carousel="<?php echo esc_attr( $carousel_id ); ?>">
			<div class="brag-book-gallery-carousel-content">
				<?php if ( $config['show_controls'] ) : ?>
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
					// Generate carousel items.
					$limit          = absint( $config['limit'] ?? self::DEFAULT_CAROUSEL_LIMIT );
					$procedure_slug = ! empty( $config['procedure_slug'] ) ? $config['procedure_slug'] : '';
					$items_data     = isset( $carousel_data['data'] ) ? $carousel_data['data'] : $carousel_data;

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method.
					echo self::generate_carousel_items( $items_data, $limit, $procedure_slug );
					?>
				</div>

				<?php if ( $config['show_controls'] ) : ?>
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

			<?php if ( $config['show_pagination'] ) : ?>
				<div class="brag-book-gallery-carousel-pagination"
					 data-pagination="<?php echo esc_attr( $carousel_id ); ?>"
					 role="tablist"
					 aria-label="<?php esc_attr_e( 'Carousel pagination', 'brag-book-gallery' ); ?>"></div>
			<?php endif; ?>
		</div>
		<?php

		$html = ob_get_clean();

		/**
		 * Filters the carousel HTML output.
		 *
		 * @param string $html The generated carousel HTML.
		 * @param array $carousel_data The carousel data from API.
		 * @param array $config The carousel configuration.
		 *
		 * @since 3.0.0
		 *
		 */
		return apply_filters( 'brag_book_carousel_html', $html, $carousel_data, $config );
	}

	/**
	 * Generate carousel items from API data
	 *
	 * Creates HTML for individual carousel slides from case data.
	 *
	 * @param array $items Array of case items from API.
	 * @param int $max_slides Maximum number of slides to generate.
	 * @param string $procedure_slug Optional procedure slug for context.
	 *
	 * @return string Generated HTML for carousel items.
	 * @since 3.0.0
	 *
	 */
	private static function generate_carousel_items( array $items, int $max_slides = 8, string $procedure_slug = '' ): string {
		if ( empty( $items ) ) {
			return '';
		}

		// Sanitize inputs.
		$max_slides     = absint( $max_slides );
		$procedure_slug = sanitize_title( $procedure_slug );

		$html_parts  = array();
		$slide_index = 0;
		$slide_count = 0;

		// Loop through each case and take only the first photo from each case.
		// This ensures variety in the carousel by showing different cases.
		foreach ( $items as $case ) {
			// Stop if we've reached the maximum number of slides.
			if ( $slide_count >= $max_slides ) {
				break;
			}

			// Check for both possible data structures: photoSets and photos.
			$photo_sets = array();
			if ( isset( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
				$photo_sets = $case['photoSets'];
			} elseif ( isset( $case['photos'] ) && is_array( $case['photos'] ) ) {
				$photo_sets = $case['photos'];
			}

			// If photo_sets is empty, skip this case.
			if ( empty( $photo_sets ) ) {
				continue;
			}

			// Take only the first photo from this case to ensure variety.
			$photo = $photo_sets[0];
			
			++ $slide_index;
			++ $slide_count;
			$html_parts[] = HTML_Renderer::generate_carousel_slide_from_photo(
				$photo,
				$case,
				$slide_index,
				$procedure_slug
			);
		}

		return implode( '', $html_parts );
	}

	/**
	 * Clear carousel cache
	 *
	 * Clears all cached carousel data.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public static function clear_cache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
	}
}
