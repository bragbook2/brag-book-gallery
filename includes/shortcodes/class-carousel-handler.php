<?php
/**
 * Advanced Carousel Shortcode Handler for BRAG book Gallery Plugin
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

namespace BRAGBookGallery\Includes\shortcodes;

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Resources\Asset_Manager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carousel Shortcode Handler Class
 *
 * Advanced shortcode handler that manages carousel functionality for the
 * BRAG book Gallery plugin. Provides comprehensive support for both modern
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
final class Carousel_Handler {

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
			array(
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
				'nudity'              => 'false',
				'title'               => ''
			),
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

		// Get carousel data from WordPress posts.
		$carousel_data = self::get_carousel_data_from_posts( $config );

		// Enqueue carousel assets (CSS and JS).
		self::enqueue_carousel_assets();

		// Localize script data for JavaScript functionality.
		Asset_Manager::localize_carousel_script( $config );

		// Generate and return carousel HTML.
		$output = self::render_html( $carousel_data, $config );
		return $output;
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
	 * - Auto-detection of nudity flag from procedure term meta
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
		$procedure_term = null;
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

				// Get the procedure term for nudity check
				$procedure_term = get_term_by( 'slug', $procedure_slug, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );

				// Debug logging with WordPress VIP compliance
				if ( WP_DEBUG && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAG book Carousel: Converted slug "' . $procedure_slug . '" to ID: ' . ( $procedure_id ?: 'not found' ) );
				}
			}
		}

		// Auto-detect nudity from procedure term meta, unless explicitly set in shortcode
		$nudity = filter_var( $atts['nudity'] ?? false, FILTER_VALIDATE_BOOLEAN );
		if ( ! $nudity && $procedure_term && ! is_wp_error( $procedure_term ) ) {
			$nudity_meta = get_term_meta( $procedure_term->term_id, 'nudity', true );
			$nudity = 'true' === $nudity_meta;

			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel: Auto-detected nudity for ' . $procedure_slug . ': ' . ( $nudity ? 'true' : 'false' ) );
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
				'nudity'              => $nudity,
				'title'               => sanitize_text_field( (string) ( $atts['title'] ?? '' ) ),
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
		// If we have a procedure slug, try to get the name from WordPress taxonomy
		if ( ! empty( $config['procedure_slug'] ) ) {
			$term = get_term_by( 'slug', $config['procedure_slug'], \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
			if ( $term && ! is_wp_error( $term ) ) {
				return sanitize_text_field( $term->name );
			}

			// Fallback: convert slug to title case
			return ucwords( str_replace( array( '-', '_' ), ' ', $config['procedure_slug'] ) );
		}

		// Default title
		return __( 'Recent Results', 'brag-book-gallery' );
	}

	/**
	 * Get procedure ID from slug using WordPress taxonomy data
	 *
	 * Looks up procedure term by slug and returns the stored procedure_id
	 * meta value, falling back to term_id if no meta value exists.
	 *
	 * @param string $slug                 Procedure slug to lookup.
	 * @param string $api_token            Unused - kept for compatibility.
	 * @param string $website_property_id  Unused - kept for compatibility.
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

		// Get procedure term by slug
		$term = get_term_by( 'slug', $slug, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );

		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		// Get stored procedure_id meta value, fall back to term_id
		$procedure_id = get_term_meta( $term->term_id, 'procedure_id', true );

		return ! empty( $procedure_id ) ? (int) $procedure_id : (int) $term->term_id;
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
		// Debug logging for render_html
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: render_html called with data: ' . wp_json_encode( array_keys( $carousel_data ) ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: carousel_data structure: ' . wp_json_encode( $carousel_data ) );
		}

		// Check if we have data in either format.
		$has_data = ! empty( $carousel_data ) &&
					( ! empty( $carousel_data['data'] ) ||
					  ( is_array( $carousel_data ) && isset( $carousel_data[0] ) ) );

		// Additional check: ensure at least one post has images
		if ( $has_data ) {
			$items_data = $carousel_data['data'] ?? $carousel_data;
			$has_images = false;

			foreach ( $items_data as $item ) {
				if ( ! empty( $item['images'] ) && is_array( $item['images'] ) ) {
					$has_images = true;
					break;
				}
			}

			if ( ! $has_images ) {
				$has_data = false;
				if ( WP_DEBUG && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'BRAG book Carousel Debug: Posts found but no images available' );
				}
			}
		}

		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: has_data result: ' . ( $has_data ? 'true' : 'false' ) );
		}

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
		// Use explicit title if provided, otherwise derive from procedure
		$procedure_name = ! empty( $config['title'] ) ? $config['title'] : self::get_procedure_name( $config );
		?>
		<div class="<?php echo esc_attr( $css_class ); ?>"
			 data-carousel="<?php echo esc_attr( $carousel_id ); ?>"
			 <?php if ( ! empty( $config['procedure_slug'] ) ) : ?>
			 data-procedure="<?php echo esc_attr( $config['procedure_slug'] ); ?>"
			 <?php
				// Get term to access both IDs
				$procedure_term = get_term_by( 'slug', $config['procedure_slug'], \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
				if ( $procedure_term && ! is_wp_error( $procedure_term ) ) {
					// WordPress term ID
					echo ' data-current-term-id="' . esc_attr( $procedure_term->term_id ) . '"';
					// API procedure ID from term meta
					$api_procedure_id = get_term_meta( $procedure_term->term_id, 'procedure_id', true );
					if ( ! empty( $api_procedure_id ) ) {
						echo ' data-current-procedure-id="' . esc_attr( $api_procedure_id ) . '"';
					}
				}
				?>
			 <?php endif; ?>>
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
					// Generate carousel items by querying cases for this procedure
					$limit          = absint( $config['limit'] ?: self::DEFAULT_CAROUSEL_LIMIT );
					$procedure_slug = $config['procedure_slug'] ?? '';

					// Get fresh cases for this specific procedure
					$procedure_cases = self::get_cases_for_procedure( $procedure_slug, $config['procedure_id'], $limit );

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method.
					echo self::generate_carousel_items(
						$procedure_cases,
						$limit,
						$procedure_slug,
						$config
					);
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
		$filtered_html = apply_filters( 'brag_book_carousel_html', $html, $carousel_data, $config );

		// Prevent wpautop from adding unwanted <p> and <br> tags to shortcode output
		return $filtered_html;
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
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: generate_carousel_items called with empty items array' );
			}
			return '';
		}

		// Debug logging
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: generate_carousel_items called with ' . count( $items ) . ' items' );
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

			// Check if this case has images
			if ( empty( $case['images'] ) || ! is_array( $case['images'] ) ) {
				continue;
			}

			// Take only the first photo from this case to ensure variety.
			$photo = $case['images'][0];

			++$slide_index;
			++$slide_count;

			$slide_html = self::generate_carousel_slide_html(
				$photo,
				$case,
				$slide_index,
				$procedure_slug,
				true, // This is a standalone carousel
				$config // Pass carousel configuration
			);

			// Debug logging for slide generation
			if ( WP_DEBUG && WP_DEBUG_LOG && $slide_index === 1 ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: Generated slide HTML length: ' . strlen( $slide_html ) );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: Photo data: ' . wp_json_encode( $photo ) );
			}

			$html_parts[] = $slide_html;
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

		// Add custom CSS using centralized Asset_Manager method (prevents duplication).
		Asset_Manager::add_custom_css( 'brag-book-gallery-main' );

		// Enqueue main gallery JavaScript (which includes carousel functionality).
		if ( ! wp_script_is( 'brag-book-gallery-main', 'enqueued' ) ) {
			$js_file = $plugin_path . 'assets/js/brag-book-gallery.js';
			$js_version = Asset_Manager::get_asset_version( $js_file );

			wp_enqueue_script(
				'brag-book-gallery-main',
				$plugin_url . 'assets/js/brag-book-gallery.js',
				array(),
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

	/**
	 * Get carousel data from WordPress posts
	 *
	 * Retrieves posts from the brag_book_cases post type and formats them
	 * for carousel display using WP_Query for enhanced performance and flexibility.
	 *
	 * @param array $config Carousel configuration.
	 * @return array Formatted carousel data.
	 * @since 3.0.0
	 */
	private static function get_carousel_data_from_posts( array $config ): array {
		$query_args = [
			'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $config['limit'],
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [],
			'no_found_rows'  => true, // Performance optimization
			'cache_results'  => true,
		];

		// Debug: First check if there are ANY posts of this type
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			$test_query = new \WP_Query( [
				'post_type' => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
				'post_status' => 'publish',
				'posts_per_page' => 1,
			] );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: Total published brag_book_cases posts: ' . $test_query->found_posts );
			wp_reset_postdata();
		}

		// Note: Procedure filtering is now handled by get_cases_for_procedure() method
		// This method provides fallback data when no procedure-specific cases are found

		// Filter by member_id if specified
		if ( ! empty( $config['member_id'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'member_id',
				'value'   => sanitize_text_field( $config['member_id'] ),
				'compare' => '=',
			];
		}

		$query = new \WP_Query( $query_args );

		// Debug logging
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: Query args: ' . wp_json_encode( $query_args ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: Found posts: ' . $query->post_count );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: Query SQL: ' . $query->request );
		}

		// If no posts found with procedure filter, try without filter as fallback
		if ( ! $query->have_posts() && ( ! empty( $config['procedure_id'] ) || ! empty( $config['procedure_slug'] ) ) ) {
			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: No posts found with procedure filter, trying without filter' );
			}

			// Remove taxonomy filters and try again
			$fallback_args = $query_args;
			unset( $fallback_args['tax_query'] );
			wp_reset_postdata(); // Reset before new query
			$query = new \WP_Query( $fallback_args );

			if ( WP_DEBUG && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: Fallback query found posts: ' . $query->post_count );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: Fallback query SQL: ' . $query->request );
			}
		}

		// Format posts as carousel data
		$carousel_items = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				$case_id = get_post_meta( $post_id, 'brag_book_gallery_case_id', true );
				$images = get_post_meta( $post_id, 'images', true ) ?: [];

				// Ensure images is an array
				if ( ! is_array( $images ) ) {
					$images = [];
				}

				// Get processed URLs and convert to array
				$processed_urls = get_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', true ) ?: '';
				$url_array = [];
				if ( ! empty( $processed_urls ) && is_string( $processed_urls ) ) {
					$url_array = array_filter( array_map( 'trim', explode( ';', $processed_urls ) ) );
				}

				// Get first URL if available
				$first_image_url = ! empty( $url_array ) ? $url_array[0] : '';


				// If we have a processed URL but no images array, create one
				if ( ! empty( $first_image_url ) && empty( $images ) ) {
					$images = [
						[
							'url' => $first_image_url,
							'processed_url' => $first_image_url,
						]
					];
				}

				// Get the procedure case ID (junction ID) for view tracking
				$procedure_case_id = get_post_meta( $post_id, 'brag_book_gallery_procedure_case_id', true );
				if ( empty( $procedure_case_id ) ) {
					$procedure_case_id = get_post_meta( $post_id, 'brag_book_gallery_original_case_id', true );
				}

				$carousel_items[] = [
					'id'                => $case_id,
					'procedure_case_id' => $procedure_case_id,
					'post_id'           => $post_id,
					'images'     => $images,
					'age'        => get_post_meta( $post_id, 'age', true ) ?: '',
					'gender'     => get_post_meta( $post_id, 'gender', true ) ?: '',
					'ethnicity'  => get_post_meta( $post_id, 'ethnicity', true ) ?: '',
					'notes'      => get_post_meta( $post_id, 'notes', true ) ?: '',
					'procedures' => wp_get_post_terms( $post_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES, [ 'fields' => 'names' ] ),
				];
			}
		}

		// Reset global post data
		wp_reset_postdata();

		// Debug logging for final result
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BRAG book Carousel Debug: Final carousel items count: ' . count( $carousel_items ) );
			if ( ! empty( $carousel_items ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'BRAG book Carousel Debug: First item: ' . wp_json_encode( $carousel_items[0] ) );
			}
		}

		return [
			'success' => true,
			'data'    => $carousel_items,
			'total'   => count( $carousel_items ),
		];
	}

	/**
	 * Get cases for a specific procedure
	 *
	 * Queries WordPress posts to get cases for a specific procedure,
	 * formatted for carousel display. Uses the same ordering logic as
	 * the cases/gallery handlers to maintain consistent order.
	 *
	 * @param string $procedure_slug The procedure slug to filter by.
	 * @param int|null $procedure_id The procedure ID to filter by.
	 * @param int $limit Maximum number of cases to return.
	 * @return array Array of formatted case data.
	 * @since 3.0.0
	 */
	private static function get_cases_for_procedure( string $procedure_slug, ?int $procedure_id, int $limit ): array {

		$query_args = array(
			'post_type'      => \BRAGBookGallery\Includes\Extend\Post_Types::POST_TYPE_CASES,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'cache_results'  => true,
		);

		// Get procedure term to access case order list
		if ( ! empty( $procedure_slug ) ) {
			$procedure_term = get_term_by( 'slug', $procedure_slug, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );

			if ( $procedure_term && ! is_wp_error( $procedure_term ) ) {
				// Get the case order list from term meta
				$case_order_list = get_term_meta( $procedure_term->term_id, 'brag_book_gallery_case_order_list', true );

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
						// Limit to the requested number of items
						$post_ids = array_slice( $post_ids, 0, $limit );

						// Use post__in to get only these posts in this exact order
						$query_args['post__in'] = $post_ids;
						$query_args['orderby']  = 'post__in';
						unset( $query_args['order'] );
					} else {
						// Fallback to taxonomy filter if no valid post IDs found
						$query_args['tax_query'] = [
							[
								'taxonomy' => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
								'field'    => 'slug',
								'terms'    => sanitize_title( $procedure_slug ),
							],
						];
					}
				} else {
					// Fallback to taxonomy filter if no case order list
					$query_args['tax_query'] = [
						[
							'taxonomy' => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
							'field'    => 'slug',
							'terms'    => sanitize_title( $procedure_slug ),
						],
					];
				}
			}
		}

		$query = new \WP_Query( $query_args );

		$cases = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				// Get processed URLs and convert to array
				$processed_urls = get_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', true ) ?: '';
				$url_array = [];
				if ( ! empty( $processed_urls ) && is_string( $processed_urls ) ) {
					$url_array = array_filter( array_map( 'trim', explode( ';', $processed_urls ) ) );
				}

				// Get first URL if available
				$first_image_url = ! empty( $url_array ) ? $url_array[0] : '';
				$case_id = get_post_meta( $post_id, 'brag_book_gallery_case_id', true );

				// Get the procedure case ID (junction ID) for view tracking
				$procedure_case_id = get_post_meta( $post_id, 'brag_book_gallery_procedure_case_id', true );
				if ( empty( $procedure_case_id ) ) {
					$procedure_case_id = get_post_meta( $post_id, 'brag_book_gallery_original_case_id', true );
				}

				// Only include cases that have images
				if ( ! empty( $first_image_url ) ) {
					$cases[] = [
						'id'                => $case_id,
						'procedure_case_id' => $procedure_case_id,
						'post_id'           => $post_id,
						'title'      => get_the_title(),
						'images'     => [
							[
								'url' => $first_image_url,
								'processed_url' => $first_image_url,
							]
						],
						'age'        => get_post_meta( $post_id, 'age', true ) ?: '',
						'gender'     => get_post_meta( $post_id, 'gender', true ) ?: '',
						'ethnicity'  => get_post_meta( $post_id, 'ethnicity', true ) ?: '',
						'notes'      => get_post_meta( $post_id, 'notes', true ) ?: '',
						'procedures' => wp_get_post_terms( $post_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES, [ 'fields' => 'names' ] ),
					];
				}
			}
		}

		wp_reset_postdata();

		return $cases;
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
	private static function generate_carousel_slide_html(
		array $photo,
		array $case,
		int $slide_index,
		string $procedure_slug = '',
		bool $is_standalone = false,
		array $config = []
	): string {

		$photo_data = self::extract_photo_data( $photo, $config );

		$case_data = self::extract_case_data_for_slide( $case );

		$slide_data = self::build_slide_data( $photo_data, $case_data, $slide_index );

		$case_url = get_permalink( $case['post_id'] ?? $case['id'] );

		// Get WordPress term ID from procedure slug
		$procedure_term_id = null;
		if ( ! empty( $config['procedure_slug'] ) ) {
			$procedure_term = get_term_by( 'slug', $config['procedure_slug'], \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
			if ( $procedure_term && ! is_wp_error( $procedure_term ) ) {
				$procedure_term_id = $procedure_term->term_id;
			}
		}

		return self::render_slide(
			$photo_data,
			$case_data,
			$slide_data,
			$case_url,
			$is_standalone,
			$procedure_term_id
		);
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
	private static function extract_case_data_for_slide( array $case ): array {
		$seo_suffix = '';
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			$seo_suffix = sanitize_title( $first_detail['seoSuffixUrl'] ?? '' );
		}

		return array(
			'id'                => sanitize_text_field( $case['id'] ?? '' ),
			'procedure_case_id' => sanitize_text_field( $case['procedure_case_id'] ?? '' ),
			'post_id'           => absint( $case['post_id'] ?? 0 ),
			'seo_suffix'        => $seo_suffix,
			'procedures'        => $case['procedures'] ?? array(),
			'total_slides'      => count( $case['photos'] ?? $case['images'] ?? array() ),
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
	 * Render the complete carousel slide
	 *
	 * Assembles all components into complete slide HTML.
	 *
	 * @since 3.0.0
	 *
	 * @param array    $photo_data    Photo data.
	 * @param array    $case_data     Case data.
	 * @param array    $slide_data    Slide data.
	 * @param string   $case_url      Case URL.
	 * @param bool     $is_standalone Whether this is a standalone carousel (no action buttons).
	 * @param int|null $procedure_id  Optional procedure term ID for referrer tracking.
	 *
	 * @return string Complete slide HTML.
	 */
	private static function render_slide( array $photo_data, array $case_data, array $slide_data, string $case_url, bool $is_standalone = false, ?int $procedure_id = null ): string {
		$slide_wrapper = self::render_slide_wrapper( $slide_data, $case_data, $procedure_id );
		$nudity_warning = $photo_data['has_nudity'] ? HTML_Renderer::render_nudity_warning() : '';
		$link_open = ! empty( $case_url ) ? self::render_slide_link_open( $case_url, $photo_data['alt_text'] ) : '';
		$link_close = ! empty( $case_url ) ? '</a>' : '';
		$image_element = self::render_slide_image( $photo_data );

		// Only render action buttons for non-standalone carousels
		// Pass procedure ID (term ID) to get the API procedure ID for favorites
		$action_buttons = $is_standalone ? '' : self::render_slide_action_buttons( $case_data['id'], $procedure_id );

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
	 * @param array    $slide_data   Slide data.
	 * @param array    $case_data    Case data.
	 * @param int|null $procedure_id Optional procedure term ID for referrer tracking.
	 *
	 * @return string Wrapper HTML.
	 */
	private static function render_slide_wrapper( array $slide_data, array $case_data = [], ?int $procedure_id = null ): string {
		$data_attributes = sprintf(
			'data-slide="%s"',
			esc_attr( $slide_data['id'] )
		);

		// Add case debugging data attributes
		if ( ! empty( $case_data ) ) {
			// API case ID
			if ( ! empty( $case_data['id'] ) ) {
				$data_attributes .= sprintf(
					' data-case-id="%s"',
					esc_attr( $case_data['id'] )
				);
			}

			// Procedure case ID (junction ID - the small number for view tracking)
			$pcid = $case_data['procedure_case_id'] ?? $case_data['id'] ?? '';
			if ( ! empty( $pcid ) ) {
				$data_attributes .= sprintf(
					' data-procedure-case-id="%s"',
					esc_attr( $pcid )
				);
			}

			// WordPress post ID
			if ( ! empty( $case_data['post_id'] ) ) {
				$data_attributes .= sprintf(
					' data-post-id="%s"',
					esc_attr( $case_data['post_id'] )
				);
			}

			if ( ! empty( $case_data['seo_suffix'] ) ) {
				$data_attributes .= sprintf(
					' data-seo-suffix="%s"',
					esc_attr( $case_data['seo_suffix'] )
				);
			}
		}

		// Add current term ID and procedure ID if provided
		if ( ! empty( $procedure_id ) ) {
			// WordPress term ID
			$data_attributes .= sprintf(
				' data-current-term-id="%s"',
				esc_attr( $procedure_id )
			);

			// API procedure ID from term meta
			$api_procedure_id = get_term_meta( $procedure_id, 'procedure_id', true );
			if ( ! empty( $api_procedure_id ) ) {
				$data_attributes .= sprintf(
					' data-current-procedure-id="%s"',
					esc_attr( $api_procedure_id )
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
	private static function render_slide_link_open( string $case_url, string $alt_text ): string {
		return sprintf(
			'<a href="%s" class="brag-book-gallery-carousel-link" aria-label="%s">',
			esc_url( $case_url ),
			/* translators: %s: image alt text */
			esc_attr( sprintf( __( 'View case details for %s', 'brag-book-gallery' ), $alt_text ) )
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
	private static function render_slide_image( array $photo_data ): string {
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
	 * @param string   $case_id      Case ID.
	 * @param int|null $procedure_id Optional WordPress term ID for the procedure.
	 *
	 * @return string Action buttons HTML.
	 */
	private static function render_slide_action_buttons( string $case_id, ?int $procedure_id = null ): string {
		// Check if favorites functionality is enabled
		if ( ! \BRAGBookGallery\Includes\Core\Settings_Helper::is_favorites_enabled() ) {
			return '';
		}

		// Use case_id directly - it's the procedure_case_id (junction ID) from the API
		$favorite_item_id = $case_id;

		return sprintf(
			'<div class="brag-book-gallery-item-actions"><button class="brag-book-gallery-favorite-button" data-favorited="false" data-item-id="%s" aria-label="%s"><svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button></div>',
			esc_attr( $favorite_item_id ),
			esc_attr__( 'Add to favorites', 'brag-book-gallery' )
		);
	}
}
