<?php
/**
 * Advanced Carousel Shortcode Handler for BRAGBook Gallery Plugin
 *
 * Provides comprehensive carousel shortcode functionality with modern WordPress
 * practices, security-first validation, and extensive customization options.
 * Supports both contemporary and legacy shortcode formats for backwards compatibility.
 *
 * Key Features:
 * - Modern and legacy shortcode format support
 * - Intelligent procedure slug to ID conversion
 * - Advanced caching with collision-resistant keys
 * - Comprehensive input validation and sanitization
 * - WordPress VIP compliant code patterns
 * - Accessibility-focused HTML output
 * - Performance-optimized data processing
 * - Extensive customization and filtering hooks
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

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carousel Shortcode Handler Class
 *
 * Advanced shortcode handler that manages carousel functionality for the
 * BRAGBook Gallery plugin. Provides comprehensive support for both modern
 * and legacy shortcode formats with extensive customization options.
 *
 * Supported Shortcodes:
 * - [brag_book_carousel] - Modern format with full feature set
 * - [brag_book_carousel_shortcode] - Legacy format with backwards compatibility
 *
 * Architecture:
 * - Static methods for stateless shortcode processing
 * - Comprehensive input validation and sanitization
 * - Intelligent caching with performance optimization
 * - Accessibility-compliant HTML output generation
 * - Security-first approach to all operations
 *
 * Performance Features:
 * - Object cache integration for procedure ID lookups
 * - Optimized data processing with early validation
 * - Efficient HTML generation with output buffering
 * - Smart carousel item generation with variety optimization
 *
 * Security Features:
 * - Comprehensive input validation and sanitization
 * - XSS prevention through proper output escaping
 * - SQL injection prevention in cache operations
 * - Safe HTML class and attribute handling
 *
 * @since 3.0.0
 */
final class Carousel_Shortcode_Handler {

	/**
	 * Default maximum number of carousel items to display
	 *
	 * Optimized for performance and user experience. Provides sufficient
	 * variety while maintaining fast loading times and smooth animations.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_CAROUSEL_LIMIT = 8;

	/**
	 * Default start index for carousel positioning
	 *
	 * Uses 1-based indexing for consistency with user expectations
	 * and API conventions.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_START_INDEX = 1;

	/**
	 * Object cache group identifier for carousel data
	 *
	 * Used to namespace carousel-specific cache entries for efficient
	 * cache management and avoiding collisions with other plugins.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_carousel';

	/**
	 * Cache expiration time in seconds for procedure ID lookups
	 *
	 * Balances performance with data freshness. Procedure mappings
	 * change infrequently, allowing for longer cache durations.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600; // 1 hour

	/**
	 * Extract and validate API configuration from WordPress options
	 *
	 * Intelligently handles both array and string format configurations
	 * with comprehensive validation and sanitization. Uses PHP 8.2 match
	 * expressions for cleaner type handling.
	 *
	 * @since 3.0.0
	 * @return array{api_token: string, website_property_id: string} Validated API configuration.
	 */
	private static function extract_api_configuration(): array {
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		// Extract API token with type-safe handling
		$default_token = match ( true ) {
			is_array( $api_tokens ) && ! empty( $api_tokens[0] ) => sanitize_text_field( $api_tokens[0] ),
			is_string( $api_tokens ) && ! empty( $api_tokens ) => sanitize_text_field( $api_tokens ),
			default => '',
		};

		// Extract website property ID with type-safe handling
		$default_property_id = match ( true ) {
			is_array( $website_property_ids ) && ! empty( $website_property_ids[0] ) => sanitize_text_field( $website_property_ids[0] ),
			is_string( $website_property_ids ) && ! empty( $website_property_ids ) => sanitize_text_field( $website_property_ids ),
			default => '',
		};

		return [
			'api_token' => $default_token,
			'website_property_id' => $default_property_id,
		];
	}

	/**
	 * Handle the modern carousel shortcode with comprehensive validation
	 *
	 * Processes the [brag_book_carousel] shortcode with full security validation,
	 * input sanitization, and error handling. Implements WordPress VIP compliant
	 * practices for optimal performance and security.
	 *
	 * Features:
	 * - Comprehensive input validation and sanitization
	 * - Intelligent API configuration detection
	 * - Secure error handling with user-friendly messages
	 * - Performance-optimized data processing
	 * - Accessibility-compliant HTML output
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $atts Shortcode attributes from user input.
	 *
	 * @return string Rendered carousel HTML or error message.
	 */
	public static function handle( array $atts ): string {
		// Extract API configuration with intelligent type handling
		$api_config = self::extract_api_configuration();
		$default_token = $api_config['api_token'];
		$default_property_id = $api_config['website_property_id'];

		// Parse and validate shortcode attributes with comprehensive defaults
		$atts = shortcode_atts(
			[
				'api_token'           => $default_token,
				'website_property_id' => $default_property_id,
				'limit'               => self::DEFAULT_CAROUSEL_LIMIT,
				'start'               => self::DEFAULT_START_INDEX,
				'procedure_id'        => '',
				'procedure'           => '', // Legacy support for procedure parameter
				'member_id'           => '',
				'show_controls'       => 'true',
				'show_pagination'     => 'true',
				'auto_play'           => 'false',
				'class'               => '',
				'nudity'              => 'false'
			],
			$atts,
			'brag_book_carousel'
		);

		// Normalize procedure parameter (legacy compatibility)
		$atts['procedure_id'] = $atts['procedure_id'] ?: $atts['procedure'];

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

		// Enqueue carousel assets (CSS and JS).
		self::enqueue_carousel_assets();

		// Localize script data for JavaScript functionality.
		Asset_Manager::localize_carousel_script( $config );

		// Generate and return carousel HTML.
		return self::render_html( $carousel_data, $config );
	}

	/**
	 * Handle legacy carousel shortcode with full backwards compatibility
	 *
	 * Provides seamless migration from the legacy [brag_book_carousel_shortcode]
	 * format to the modern carousel system. Maps all legacy parameters to their
	 * modern equivalents with intelligent defaults and validation.
	 *
	 * Legacy Format Support:
	 * - [brag_book_carousel_shortcode procedure="slug" start="1" limit="10" title="0" details="0"]
	 * - Maps title/details parameters to show_controls/show_pagination
	 * - Automatically retrieves API token from plugin settings
	 * - Preserves all functionality while using modern rendering system
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $atts Legacy shortcode attributes.
	 *
	 * @return string Rendered carousel HTML output.
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

		// Get current API configuration for legacy shortcode
		$api_config = self::extract_api_configuration();

		// Transform legacy attributes to modern format
		$new_atts = [
			'api_token' => $api_config['api_token'],
			'website_property_id' => $legacy_atts['website_property_id'] ?: $api_config['website_property_id'],
			'limit' => absint( $legacy_atts['limit'] ),
			'start' => absint( $legacy_atts['start'] ),
			'procedure_id' => ! empty( $legacy_atts['procedure'] ) ? sanitize_title( $legacy_atts['procedure'] ) : '',
			'show_controls' => '0' !== $legacy_atts['title'] ? 'true' : 'false',
			'show_pagination' => '0' !== $legacy_atts['details'] ? 'true' : 'false',
		];

		// Call the new carousel handler with mapped attributes.
		return self::handle( $new_atts );
	}

	/**
	 * Validate and sanitize carousel configuration with comprehensive security
	 *
	 * Performs thorough validation and sanitization of all carousel configuration
	 * parameters. Implements defense-in-depth security measures including input
	 * validation, type checking, and safe defaults.
	 *
	 * Validation Features:
	 * - Required field validation with user-friendly error messages
	 * - Intelligent type conversion and sanitization
	 * - API configuration fallback handling
	 * - Procedure slug to ID conversion with caching
	 * - Safe boolean parameter processing
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $atts Raw shortcode attributes requiring validation.
	 *
	 * @return array{error: bool, message?: string, config?: array<string, mixed>} Validation result.
	 */
	private static function validate_configuration( array $atts ): array {
		// Fill in missing API configuration from plugin settings
		if ( empty( $atts['api_token'] ) || empty( $atts['website_property_id'] ) ) {
			$api_config = self::extract_api_configuration();
			$atts['api_token'] = $atts['api_token'] ?: $api_config['api_token'];
			$atts['website_property_id'] = $atts['website_property_id'] ?: $api_config['website_property_id'];
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

				// Debug logging with WordPress VIP compliance
				if ( WP_DEBUG && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAGBook Carousel: Converted slug "' . $procedure_slug . '" to ID: ' . ( $procedure_id ?: 'not found' ) );
				}
			}
		}

		$member_id = ! empty( $atts['member_id'] ) ? absint( $atts['member_id'] ) : null;

		return [
			'error'  => false,
			'config' => [
				'api_token'           => sanitize_text_field( (string) $atts['api_token'] ),
				'website_property_id' => sanitize_text_field( (string) $atts['website_property_id'] ),
				'limit'               => absint( $atts['limit'] ?: self::DEFAULT_CAROUSEL_LIMIT ),
				'start'               => absint( $atts['start'] ?: self::DEFAULT_START_INDEX ),
				'procedure_id'        => $procedure_id,
				'procedure_slug'      => $procedure_slug,
				'member_id'           => $member_id,
				'show_controls'       => filter_var( $atts['show_controls'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'show_pagination'     => filter_var( $atts['show_pagination'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'auto_play'           => filter_var( $atts['auto_play'] ?? false, FILTER_VALIDATE_BOOLEAN ),
				'class'               => sanitize_html_class( (string) $atts['class'] ),
				'nudity'              => filter_var( $atts['nudity'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			],
		];
	}

	/**
	 * Get procedure name for carousel title
	 *
	 * Gets the human-readable procedure name from the configuration.
	 *
	 * @param array<string, mixed> $config Carousel configuration.
	 *
	 * @return string Procedure name or default title.
	 * @since 3.0.0
	 */
	private static function get_procedure_name( array $config ): string {
		// If we have a procedure slug, try to get the name from sidebar data
		if ( ! empty( $config['procedure_slug'] ) ) {
			$api_token = $config['api_token'] ?? '';
			if ( ! empty( $api_token ) ) {
				$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );
				if ( ! empty( $sidebar_data ) ) {
					$categories = isset( $sidebar_data['data'] ) ? $sidebar_data['data'] : $sidebar_data;
					foreach ( $categories as $category ) {
						if ( ! empty( $category['procedures'] ) && is_array( $category['procedures'] ) ) {
							foreach ( $category['procedures'] as $procedure ) {
								if ( isset( $procedure['slugName'] ) && $procedure['slugName'] === $config['procedure_slug'] ) {
									return sanitize_text_field( $procedure['name'] ?? '' );
								}
								if ( isset( $procedure['name'] ) && sanitize_title( $procedure['name'] ) === $config['procedure_slug'] ) {
									return sanitize_text_field( $procedure['name'] );
								}
							}
						}
					}
				}
			}

			// Fallback: convert slug to title case
			return ucwords( str_replace( array( '-', '_' ), ' ', $config['procedure_slug'] ) );
		}

		// Default title
		return __( 'Recent Results', 'brag-book-gallery' );
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

		// Get sidebar data which contains procedure information.
		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );

		if ( empty( $sidebar_data ) ) {
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
							return $id;
						}
					}
					// Also check by sanitized name as fallback.
					if ( isset( $procedure['name'] ) && sanitize_title( $procedure['name'] ) === $slug ) {
						if ( ! empty( $procedure['ids'] ) && is_array( $procedure['ids'] ) ) {
							$id = absint( $procedure['ids'][0] );
							return $id;
						}
					}
				}
			}
		}

		// Not found.
		return null;
	}

	/**
	 * Render carousel HTML with accessibility and performance optimization
	 *
	 * Generates comprehensive, accessible HTML structure for the carousel
	 * with proper ARIA attributes, semantic markup, and optimized loading.
	 * Uses output buffering for efficient HTML generation.
	 *
	 * HTML Features:
	 * - Semantic HTML5 structure with proper landmarks
	 * - ARIA attributes for screen reader compatibility
	 * - Responsive design with flexible layouts
	 * - SVG icons for crisp display at all resolutions
	 * - Conditional control and pagination rendering
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $carousel_data API response data for carousel items.
	 * @param array<string, mixed> $config Validated carousel configuration settings.
	 *
	 * @return string Fully rendered, accessible carousel HTML.
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
		<?php
		// Get procedure name for title
		$procedure_name = self::get_procedure_name( $config );
		?>
		<div class="<?php echo esc_attr( $css_class ); ?>"
			 data-carousel="<?php echo esc_attr( $carousel_id ); ?>">
			<div class="brag-book-gallery-carousel-header">
				<h2 class="brag-book-gallery-carousel-title"><?php echo esc_html( $procedure_name ); ?></h2>
				<?php if ( $config['show_controls'] ) : ?>
					<div class="brag-book-gallery-carousel-nav">
						<?php echo self::render_navigation_button( 'prev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::render_navigation_button( 'next' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="brag-book-gallery-carousel-content">
				<div class="brag-book-gallery-carousel-track"
					 data-carousel-track="<?php echo esc_attr( $carousel_id ); ?>"
					 role="region"
					 aria-label="<?php esc_attr_e( 'Image carousel', 'brag-book-gallery' ); ?>">
					<?php
					// Generate carousel items.
					$limit          = absint( $config['limit'] ?: self::DEFAULT_CAROUSEL_LIMIT );
					$procedure_slug = $config['procedure_slug'] ?? '';
					$items_data     = $carousel_data['data'] ?? $carousel_data;

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method.
					echo self::generate_carousel_items( $items_data, $limit, $procedure_slug, $config );
					?>
				</div>
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
	 * Render navigation button with consistent styling and accessibility
	 *
	 * Creates accessible navigation buttons with proper ARIA labels
	 * and SVG icons for optimal display quality.
	 *
	 * @since 3.0.0
	 *
	 * @param string $direction Button direction ('prev' or 'next').
	 *
	 * @return string Rendered navigation button HTML.
	 */
	private static function render_navigation_button( string $direction ): string {
		$is_prev = 'prev' === $direction;
		$label = $is_prev ?
			__( 'Previous slide', 'brag-book-gallery' ) :
			__( 'Next slide', 'brag-book-gallery' );
		$path = $is_prev ?
			'M400-240 160-480l240-240 56 58-142 142h486v80H314l142 142-56 58Z' :
			'm560-240-56-58 142-142H160v-80h486L504-662l56-58 240 240-240 240Z';

		return sprintf(
			'<button class="brag-book-gallery-carousel-btn brag-book-gallery-carousel-btn--%s" data-direction="%s" aria-label="%s">' .
			'<svg class="brag-book-gallery-arrow-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">' .
			'<path d="%s"/>' .
			'</svg></button>',
			esc_attr( $direction ),
			esc_attr( $direction ),
			esc_attr( $label ),
			esc_attr( $path )
		);
	}

	/**
	 * Generate optimized carousel items from API data
	 *
	 * Creates HTML for individual carousel slides with intelligent variety
	 * optimization. Selects the first photo from each case to ensure
	 * maximum visual diversity in the carousel display.
	 *
	 * Optimization Features:
	 * - One photo per case for maximum variety
	 * - Intelligent data structure handling (photoSets vs photos)
	 * - Performance-optimized loop with early termination
	 * - Proper input sanitization and validation
	 * - Semantic HTML generation with accessibility support
	 * - Nudity warning support with carousel-level override
	 *
	 * @since 3.0.0
	 *
	 * @param array<int, array<string, mixed>> $items Array of case items from API.
	 * @param int $max_slides Maximum number of slides to generate.
	 * @param string $procedure_slug Optional procedure slug for contextual information.
	 * @param array<string, mixed> $config Optional carousel configuration.
	 *
	 * @return string Generated HTML for all carousel items.
	 */
	private static function generate_carousel_items( array $items, int $max_slides = 8, string $procedure_slug = '', array $config = [] ): string {
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
				$procedure_slug,
				true, // This is a standalone carousel
				$config // Pass carousel configuration
			);
		}

		return implode( '', $html_parts );
	}

	/**
	 * Enqueue carousel-specific assets
	 *
	 * Loads the necessary CSS and JavaScript files for carousel functionality.
	 * Ensures assets are only loaded once per page even with multiple carousels.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function enqueue_carousel_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$plugin_path = Setup::get_plugin_path();

		// Enqueue gallery styles (shared with carousel).
		if ( ! wp_style_is( 'brag-book-gallery-main', 'enqueued' ) ) {
			wp_enqueue_style(
				'brag-book-gallery-main',
				$plugin_url . 'assets/css/brag-book-gallery.css',
				array(),
				Asset_Manager::get_asset_version( $plugin_path . 'assets/css/brag-book-gallery.css' )
			);
		}

		// Add custom CSS only once.
		if ( ! wp_style_is( 'brag-book-gallery-custom-css', 'enqueued' ) ) {
			$custom_css = get_option( 'brag_book_gallery_custom_css', '' );
			if ( ! empty( $custom_css ) ) {
				wp_add_inline_style( 'brag-book-gallery-main', wp_strip_all_tags( $custom_css ) );
			}
		}

		// Check if GSAP should be loaded.
		if ( ! Asset_Manager::is_gsap_enqueued() ) {
			$gsap_cdn = 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js';
			wp_enqueue_script(
				'gsap',
				$gsap_cdn,
				array(),
				'3.12.2',
				true
			);
		}

		// Enqueue main gallery JavaScript (which includes carousel functionality).
		if ( ! wp_script_is( 'brag-book-gallery-main', 'enqueued' ) ) {
			$js_file = $plugin_path . 'assets/js/brag-book-gallery.js';
			$js_version = Asset_Manager::get_asset_version( $js_file );

			wp_enqueue_script(
				'brag-book-gallery-main',
				$plugin_url . 'assets/js/brag-book-gallery.js',
				array( 'gsap' ),
				$js_version,
				true
			);
		}
	}

	/**
	 * Clear all carousel-related cache data
	 *
	 * Flushes all cached data specific to the carousel functionality,
	 * including procedure ID lookups and carousel item data. This is
	 * typically called when API data is updated or cache needs refreshing.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function clear_cache(): void {
		// Clear the specific carousel cache group
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		}
	}
}
