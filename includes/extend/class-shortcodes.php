<?php
declare( strict_types=1 );

/**
 * Enterprise Shortcodes Coordinator for BRAGBook Gallery Plugin
 *
 * Comprehensive shortcode management system implementing WordPress VIP standards
 * with PHP 8.2+ optimizations and enterprise-grade security features. Coordinates
 * all shortcode registrations, delegations, and legacy compatibility handling.
 *
 * Key Features:
 * - Centralized shortcode registration and delegation
 * - Legacy shortcode backwards compatibility support
 * - Body class management for gallery pages
 * - Intelligent delegation to specialized handlers
 * - Security-first approach with input validation
 * - Performance-optimized rendering strategies
 * - Deprecated method preservation for compatibility
 *
 * Architecture:
 * - Static methods for stateless shortcode operations
 * - Delegation pattern to specialized handler classes
 * - Backwards compatibility with deprecated methods
 * - WordPress VIP compliant error handling
 * - Type-safe operations with PHP 8.2+ features
 * - Modular handler architecture for maintainability
 *
 * Shortcodes Managed:
 * - [brag_book_gallery] - Main gallery display
 * - [brag_book_gallery_cases] - Cases grid display
 * - [brag_book_gallery_case] - Single case details
 * - [brag_book_carousel] - Image carousel
 * - [brag_book_favorites] - User favorites
 * - [bragbook_carousel_shortcode] - Legacy carousel
 *
 * Security Features:
 * - Comprehensive input sanitization
 * - Secure delegation to handler classes
 * - XSS prevention through proper escaping
 * - Safe handling of user-generated content
 * - Nonce verification where applicable
 *
 * Performance Optimizations:
 * - Efficient handler delegation
 * - Lazy loading of handler classes
 * - Optimized shortcode registration
 * - Conditional asset enqueuing
 * - Cache-aware rendering strategies
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

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
 * Shortcodes Coordinator Class
 *
 * Enterprise-grade shortcode coordination system for the BRAGBook Gallery plugin,
 * implementing centralized registration with delegation to specialized handlers.
 * Maintains backwards compatibility while enforcing modern standards.
 *
 * Core Functionality:
 * - Shortcode registration and WordPress hook management
 * - Delegation to specialized handler classes for rendering
 * - Legacy shortcode compatibility maintenance
 * - Body class management for gallery page detection
 * - Deprecated method preservation for backwards compatibility
 * - Performance optimization through efficient delegation
 *
 * Technical Implementation:
 * - PHP 8.2+ features with type safety and modern syntax
 * - WordPress VIP coding standards compliance
 * - Comprehensive error handling with graceful degradation
 * - Security-focused input validation and sanitization
 * - Type-safe operations with strict declarations
 * - Modular architecture with single responsibility
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
	 * Comprehensive registration of all shortcodes with delegated handlers.
	 * Implements lazy loading patterns for performance optimization.
	 *
	 * Registered Shortcodes:
	 * - brag_book_gallery: Main gallery with filters
	 * - brag_book_gallery_cases: Cases grid display
	 * - brag_book_gallery_case: Single case details
	 * - brag_book_favorites: User favorites system
	 * - brag_book_carousel: Modern carousel display
	 * - bragbook_carousel_shortcode: Legacy carousel (deprecated)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register(): void {
		// Register external handlers with error suppression
		if ( class_exists( Ajax_Handlers::class ) ) {
			Ajax_Handlers::register();
		}

		if ( class_exists( Rewrite_Rules_Handler::class ) ) {
			Rewrite_Rules_Handler::register();
		}

		// Register main shortcodes with PHP 8.2 syntax
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

		// Register carousel shortcodes using handler delegation
		if ( class_exists( Carousel_Shortcode_Handler::class ) ) {
			add_shortcode(
				'brag_book_carousel',
				[ Carousel_Shortcode_Handler::class, 'handle' ]
			);

			// Legacy carousel for backwards compatibility
			add_shortcode(
				'bragbook_carousel_shortcode',
				[ Carousel_Shortcode_Handler::class, 'handle_legacy' ]
			);
		}

		// Add body class filter for gallery pages
		add_filter( 'body_class', [ __CLASS__, 'add_gallery_body_class' ], 10, 1 );
	}

	/**
	 * Add body class for pages containing gallery shortcodes.
	 *
	 * Intelligently detects gallery pages and adds appropriate body classes
	 * for CSS targeting and JavaScript initialization. Handles both shortcode
	 * detection and virtual URL routing for comprehensive coverage.
	 *
	 * Added Classes:
	 * - brag-book-gallery-page: Indicates gallery content present
	 * - disable-custom-font: When custom fonts are disabled
	 *
	 * Detection Methods:
	 * - Content-based: Scans post content for shortcodes
	 * - URL-based: Checks virtual gallery URLs
	 * - Settings-aware: Respects font preferences
	 *
	 * @since 3.0.0
	 * @param array<int, string> $classes Current body classes.
	 * @return array<int, string> Modified body classes with gallery indicators.
	 */
	public static function add_gallery_body_class( array $classes ): array {
		global $post;

		$is_gallery_page = false;

		// Content-based detection for singular pages
		if ( is_singular() && $post instanceof WP_Post && ! empty( $post->post_content ) ) {
			// Define gallery shortcodes to check
			$gallery_shortcodes = [
				'brag_book_gallery',
				'brag_book_carousel',
				'brag_book_gallery_cases',
				'brag_book_gallery_case',
				'brag_book_favorites',
				'bragbook_carousel_shortcode', // Legacy support
			];

			// Check for any gallery shortcode
			foreach ( $gallery_shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					$classes[]       = 'brag-book-gallery-page';
					$is_gallery_page = true;
					break; // Exit on first match
				}
			}
		}

		// URL-based detection for virtual pages
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server variable used for comparison only
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$gallery_slug = Slug_Helper::get_first_gallery_page_slug();

		if ( ! empty( $gallery_slug ) && ! empty( $current_url ) ) {
			$slug_pattern = '/' . preg_quote( $gallery_slug, '/' ) . '/';
			if ( preg_match( $slug_pattern, $current_url ) ) {
				if ( ! in_array( 'brag-book-gallery-page', $classes, true ) ) {
					$classes[]       = 'brag-book-gallery-page';
					$is_gallery_page = true;
				}
			}
		}

		// Apply font preference class
		if ( $is_gallery_page ) {
			$use_custom_font = get_option( 'brag_book_gallery_use_custom_font', 'yes' );
			if ( $use_custom_font !== 'yes' ) {
				$classes[] = 'disable-custom-font';
			}
		}

		return array_unique( $classes );
	}

	/**
	 * Find pages containing the gallery shortcode.
	 *
	 * Queries the database for published pages containing any BRAGBook shortcode.
	 * Used for gallery page detection and management operations.
	 *
	 * Security Considerations:
	 * - Uses prepared statements to prevent SQL injection
	 * - Limited to published pages only
	 * - Returns sanitized results
	 *
	 * @since 3.0.0
	 * @return array<int, object> Array of page objects with ID, post_name, post_title, post_content.
	 */
	private static function find_pages_with_gallery_shortcode(): array {
		global $wpdb;

		// Build safe query with prepared statement
		$query = $wpdb->prepare(
			"SELECT ID, post_name, post_title, post_content
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_status = %s
			AND post_content LIKE %s",
			'page',
			'publish',
			'%[brag_book_gallery%'
		);

		// Execute query with error handling
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$pages = $wpdb->get_results( $query );

		return is_array( $pages ) ? $pages : [];
	}

	/**
	 * Render main gallery shortcode using modern markup.
	 *
	 * Entry point for [brag_book_gallery] shortcode. Delegates to specialized
	 * Gallery_Shortcode_Handler for complete implementation including filtering,
	 * progressive loading, and favorites integration.
	 *
	 * Supported Attributes:
	 * - website_property_id: Override global property ID
	 * - class: Additional CSS classes
	 * - limit: Initial case limit
	 * - columns: Grid column count
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Shortcode attributes from user input.
	 * @return string Rendered gallery HTML with filters and cases.
	 */
	public static function main_gallery_shortcode( array $atts ): string {
		// Validate handler availability
		if ( ! class_exists( Gallery_Shortcode_Handler::class ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP compliant logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'BRAGBook Gallery: Gallery_Shortcode_Handler class not found' );
			}
			return '<div class="brag-book-gallery-error">' . esc_html__( 'Gallery handler not available.', 'brag-book-gallery' ) . '</div>';
		}

		// Delegate to specialized handler
		return Gallery_Shortcode_Handler::handle( $atts ?? [] );
	}

	/**
	 * Render carousel shortcode.
	 *
	 * Entry point for [brag_book_carousel] shortcode. Delegates to specialized
	 * Carousel_Shortcode_Handler for implementation.
	 *
	 * @deprecated 3.0.0 Use Carousel_Shortcode_Handler::handle() directly
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Rendered carousel HTML.
	 */
	public static function carousel_shortcode( array $atts ): string {
		// Validate handler availability
		if ( ! class_exists( Carousel_Shortcode_Handler::class ) ) {
			return '<div class="brag-book-carousel-error">' . esc_html__( 'Carousel handler not available.', 'brag-book-gallery' ) . '</div>';
		}

		// Delegate to carousel handler
		return Carousel_Shortcode_Handler::handle( $atts ?? [] );
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
	 * Legacy method for carousel configuration validation. Maintained for
	 * backwards compatibility but delegates to Carousel_Shortcode_Handler.
	 *
	 * Security Features:
	 * - Sanitizes all input attributes
	 * - Validates API configuration
	 * - Type-safe conversions
	 * - Prevents XSS through escaping
	 *
	 * @deprecated 3.0.0 Use Carousel_Shortcode_Handler::validate_configuration()
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Raw shortcode attributes.
	 * @return array{error?: bool, message?: string, config?: array<string, mixed>} Validation result.
	 */
	private static function validate_carousel_configuration( array $atts ): array {
		// Get API configuration with PHP 8.2 null coalescing
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens        = get_option( 'brag_book_gallery_api_token', [] );
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : (string) $api_tokens;
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids        = get_option( 'brag_book_gallery_website_property_id', [] );
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : (string) $website_property_ids;
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

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP compliant logging
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log( 'BRAGBook Carousel: Converted slug "' . $procedure_slug . '" to ID: ' . ( $procedure_id ?: 'not found' ) );
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
	 * Converts human-readable procedure slugs to numeric IDs by querying
	 * the sidebar data API. Uses caching for performance optimization.
	 *
	 * Lookup Process:
	 * - Fetches sidebar data from API
	 * - Searches categories for matching slug
	 * - Returns first matching ID
	 * - Falls back to name sanitization
	 *
	 * @deprecated 3.0.0 Use Carousel_Shortcode_Handler::get_procedure_id_from_slug()
	 * @since 3.0.0
	 * @param string $slug Procedure slug to convert.
	 * @param string $api_token Valid API token.
	 * @param string $website_property_id Website property identifier.
	 * @return int|null Procedure ID or null if not found.
	 */
	private static function get_procedure_id_from_slug( string $slug, string $api_token, string $website_property_id ): ?int {
		// Validate inputs
		if ( empty( $slug ) || empty( $api_token ) ) {
			return null;
		}

		// Fetch sidebar data with error handling
		if ( ! class_exists( Data_Fetcher::class ) ) {
			return null;
		}

		$sidebar_data = Data_Fetcher::get_sidebar_data( $api_token );

		if ( empty( $sidebar_data ) ) {
			return null;
		}

		// Extract categories with type safety
		$categories = is_array( $sidebar_data['data'] ?? null ) 
			? $sidebar_data['data'] 
			: ( is_array( $sidebar_data ) ? $sidebar_data : [] );

		// Search through categories using modern iteration
		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) || empty( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $procedure ) {
				if ( ! is_array( $procedure ) ) {
					continue;
				}

				// Check for slug match
				$matches_slug = isset( $procedure['slugName'] ) && $procedure['slugName'] === $slug;
				$matches_name = isset( $procedure['name'] ) && sanitize_title( $procedure['name'] ) === $slug;

				if ( ( $matches_slug || $matches_name ) && ! empty( $procedure['ids'][0] ) ) {
					return absint( $procedure['ids'][0] );
				}
			}
		}

		return null;
	}

	/**
	 * Render carousel HTML.
	 *
	 * Generates complete carousel markup with navigation controls, slides,
	 * and pagination. Supports autoplay, touch gestures, and accessibility.
	 *
	 * HTML Structure:
	 * - Wrapper with data attributes
	 * - Navigation buttons (optional)
	 * - Carousel track with slides
	 * - Pagination dots (optional)
	 *
	 * @deprecated 3.0.0 Use Carousel_Shortcode_Handler::render_html()
	 * @since 3.0.0
	 * @param array<string, mixed> $carousel_data API response with carousel items.
	 * @param array<string, mixed> $config Carousel display configuration.
	 * @return string Complete carousel HTML markup.
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
	 * Processes case data to generate carousel slide HTML. Limits slides
	 * to prevent performance issues and ensures proper image display.
	 *
	 * Processing Steps:
	 * - Iterates through cases and photo sets
	 * - Generates slide markup for each photo
	 * - Respects maximum slide limit
	 * - Handles both photoSets and photos formats
	 *
	 * @deprecated 3.0.0 Use Carousel_Shortcode_Handler::generate_items_from_data()
	 * @since 3.0.0
	 * @param array<int, array<string, mixed>> $items Case items with photo data.
	 * @param int $max_slides Maximum slides to generate (default: 8).
	 * @param string $procedure_slug Procedure context for URL generation.
	 * @return string Combined HTML for all carousel slides.
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
			if ( ! is_array( $case ) ) {
				continue;
			}

			// Support both data structures with PHP 8.2 null coalescing
			$photo_sets = $case['photoSets'] ?? $case['photos'] ?? [];

			if ( ! is_array( $photo_sets ) || empty( $photo_sets ) ) {
				continue;
			}

			foreach ( $photo_sets as $photo ) {
				// Check slide limit
				if ( $slide_count >= $max_slides ) {
					break 2; // Exit both loops
				}

				if ( ! is_array( $photo ) ) {
					continue;
				}

				++$slide_index;
				++$slide_count;

				// Generate slide HTML if renderer is available
				if ( class_exists( HTML_Renderer::class ) ) {
					$html_parts[] = HTML_Renderer::generate_carousel_slide_from_photo( 
						$photo, 
						$case, 
						$slide_index, 
						$procedure_slug 
					);
				}
			}
		}

		return implode( '', $html_parts );
	}

	/**
	 * Generate a single carousel slide from photo data.
	 *
	 * Creates complete slide markup including image, overlays, action buttons,
	 * and metadata. Handles nudity warnings and accessibility attributes.
	 *
	 * Generated Elements:
	 * - Container with data attributes
	 * - Nudity warning overlay (if applicable)
	 * - Linked image with lazy loading
	 * - Favorite and share action buttons
	 *
	 * @deprecated 3.0.0 Use HTML_Renderer::generate_carousel_slide_from_photo()
	 * @since 3.0.0
	 * @param array<string, mixed> $photo Photo data including URL and metadata.
	 * @param array<string, mixed> $case Parent case data for context.
	 * @param int $slide_index Slide position in carousel.
	 * @return string Complete slide HTML markup.
	 */
	private static function generate_carousel_slide_from_photo( array $photo, array $case, int $slide_index ): string {
		// Get image URL from postProcessedImageLocation
		$image_url = esc_url( $photo['postProcessedImageLocation'] ?? '' );
		if ( empty( $image_url ) ) {
			return ''; // Skip photos without valid image URL
		}

		// Generate alt text with multiple fallbacks
		$alt_text = match ( true ) {
			! empty( $photo['seoAltText'] ) => esc_attr( $photo['seoAltText'] ),
			! empty( $case['details'] ) => esc_attr( wp_strip_all_tags( $case['details'] ) ),
			default => esc_attr__( 'Before and after procedure result', 'brag-book-gallery' ),
		};

		// Get case ID and photo ID
		$case_id  = $case['id'] ?? '';
		$photo_id = $photo['id'] ?? $slide_index;
		$item_id  = 'slide-' . $photo_id;

		// Extract procedure information with multiple fallbacks
		$procedure_name = match ( true ) {
			! empty( $case['procedureName'] ) => $case['procedureName'],
			! empty( $case['procedure'] ) => $case['procedure'],
			! empty( $case['name'] ) => $case['name'],
			! empty( $photo['procedureName'] ) => $photo['procedureName'],
			default => '',
		};

		$procedure_slug = ! empty( $procedure_name ) 
			? sanitize_title( $procedure_name ) 
			: 'case';

		// Build case detail URL - format: /gallery-page/procedure-name/case-id
		$case_url = '';

		if ( ! empty( $case_id ) ) {
			// Try to get the current page URL
			$current_url = get_permalink();
			// Determine base path with fallbacks
			$base_path = match ( true ) {
				! empty( $current_url ) => parse_url( $current_url, PHP_URL_PATH ) ?: '',
				default => self::determine_gallery_base_path(),
			};

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
	 * Entry point for [brag_book_gallery_cases] shortcode. Displays cases
	 * from the API endpoint with optional filtering by procedure.
	 *
	 * Supported Attributes:
	 * - limit: Maximum cases to display
	 * - columns: Grid column count (1-6)
	 * - procedure: Filter by procedure slug
	 * - member_id: Filter by member/doctor
	 * - class: Additional CSS classes
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Rendered cases grid HTML.
	 */
	public static function cases_shortcode( array $atts ): string {
		// Validate handler availability
		if ( ! class_exists( Cases_Shortcode_Handler::class ) ) {
			return '<div class="brag-book-cases-error">' . esc_html__( 'Cases handler not available.', 'brag-book-gallery' ) . '</div>';
		}

		// Delegate to specialized handler
		return Cases_Shortcode_Handler::handle( $atts ?? [] );
	}

	/**
	 * Render cases grid display.
	 *
	 * @deprecated 3.0.0 Use Cases_Shortcode_Handler::render_cases_grid()
	 * @since 3.0.0
	 * @param array<string, mixed> $cases_data API response with cases.
	 * @param array<string, mixed> $atts Display attributes.
	 * @param string $filter_procedure Optional procedure filter.
	 * @return string Rendered cases grid HTML.
	 */
	private static function render_cases_grid( array $cases_data, array $atts, string $filter_procedure = '' ): string {
		if ( ! class_exists( Cases_Shortcode_Handler::class ) ) {
			return '';
		}
		return Cases_Shortcode_Handler::render_cases_grid( $cases_data, $atts, $filter_procedure );
	}

	/**
	 * Render single case display.
	 *
	 * @deprecated 3.0.0 Use Cases_Shortcode_Handler::render_single_case()
	 * @since 3.0.0
	 * @param string $case_id Case identifier.
	 * @param array<string, mixed> $atts Display attributes.
	 * @return string Rendered case HTML.
	 */
	private static function render_single_case( string $case_id, array $atts ): string {
		if ( ! class_exists( Cases_Shortcode_Handler::class ) ) {
			return '';
		}
		return Cases_Shortcode_Handler::render_single_case( $case_id, $atts );
	}

	/**
	 * Render AJAX gallery case card.
	 *
	 * Generates case card HTML for AJAX responses. Used by dynamic loading
	 * and filtering operations. Maintains consistency with static rendering.
	 *
	 * @deprecated 3.0.0 Use Cases_Shortcode_Handler::render_ajax_case_card()
	 * @since 3.0.0
	 * @param array<string, mixed> $case Complete case data.
	 * @param string $image_display_mode Display mode for images.
	 * @param bool $has_nudity Nudity flag for content warnings.
	 * @param string $procedure_context Procedure slug for context.
	 * @return string Rendered case card HTML.
	 */
	private static function render_ajax_gallery_case_card( array $case, string $image_display_mode, bool $has_nudity = false, string $procedure_context = '' ): string {
		if ( ! class_exists( Cases_Shortcode_Handler::class ) ) {
			return '';
		}
		return Cases_Shortcode_Handler::render_ajax_case_card( $case, $image_display_mode, $has_nudity, $procedure_context );
	}


	/**
	 * Case details shortcode handler.
	 *
	 * Entry point for [brag_book_gallery_case] shortcode. Displays detailed
	 * information for a single case including all images and metadata.
	 *
	 * Required Attributes:
	 * - case_id: Unique case identifier
	 *
	 * Optional Attributes:
	 * - class: Additional CSS classes
	 * - show_navigation: Display prev/next links
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Rendered case details HTML.
	 */
	public static function case_details_shortcode( array $atts = [] ): string {
		// Validate handler availability
		if ( ! class_exists( Cases_Shortcode_Handler::class ) ) {
			return '<div class="brag-book-case-error">' . esc_html__( 'Case handler not available.', 'brag-book-gallery' ) . '</div>';
		}

		// Validate required case_id
		if ( empty( $atts['case_id'] ) ) {
			return '<div class="brag-book-case-error">' . esc_html__( 'Case ID is required.', 'brag-book-gallery' ) . '</div>';
		}

		// Delegate to specialized handler
		return Cases_Shortcode_Handler::handle_case_details( $atts );
	}


	/**
	 * Render the My Favorites page.
	 *
	 * Legacy method for favorites page rendering. Delegates to the main
	 * favorites shortcode handler for consistency.
	 *
	 * @deprecated 3.0.0 Use favorites_shortcode() directly
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string Rendered favorites page HTML.
	 */
	private static function render_favorites_page( array $atts ): string {
		return self::favorites_shortcode( $atts );
	}

	/**
	 * Determine gallery base path for URL generation.
	 *
	 * Resolves the base path for gallery URLs using configured slugs
	 * or fallback defaults. Used for case detail link generation.
	 *
	 * Resolution Order:
	 * 1. Configured gallery page slug
	 * 2. Default '/before-after' fallback
	 *
	 * @since 3.0.0
	 * @return string Gallery base path with leading slash.
	 */
	private static function determine_gallery_base_path(): string {
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
		
		return match ( true ) {
			is_array( $gallery_slugs ) && ! empty( $gallery_slugs[0] ) => '/' . sanitize_title( $gallery_slugs[0] ),
			is_string( $gallery_slugs ) && ! empty( $gallery_slugs ) => '/' . sanitize_title( $gallery_slugs ),
			default => '/before-after',
		};
	}

	/**
	 * Favorites shortcode handler.
	 *
	 * Entry point for [brag_book_favorites] shortcode. Displays user's
	 * favorited cases with email capture and API synchronization.
	 *
	 * Supported Attributes:
	 * - email: Pre-filled email address
	 * - class: Additional CSS classes
	 * - columns: Grid column count
	 *
	 * Features:
	 * - Email capture form for new users
	 * - LocalStorage persistence
	 * - API synchronization
	 * - Responsive grid layout
	 *
	 * @since 3.0.0
	 * @param array<string, mixed>|string $atts Shortcode attributes (accepts array or string).
	 * @return string Rendered favorites interface HTML.
	 */
	public static function favorites_shortcode( array|string $atts = [] ): string {
		// Normalize attributes to array
		$atts = is_array( $atts ) ? $atts : [];

		// Extract and validate shortcode attributes
		$atts = shortcode_atts(
			[
				'email'   => '', // Pre-filled email address
				'class'   => '', // Additional CSS classes
				'columns' => 3,  // Grid columns
			],
			$atts,
			'brag_book_favorites'
		);

		// Sanitize attributes
		$atts['email']   = sanitize_email( $atts['email'] );
		$atts['class']   = sanitize_html_class( $atts['class'] );
		$atts['columns'] = absint( $atts['columns'] ) ?: 3;

		// Enqueue necessary scripts and styles
		$assets = new Assets();
		$assets->enqueue_frontend_assets();

		// Validate API configuration
		$api_config = self::validate_api_configuration();
		if ( isset( $api_config['error'] ) ) {
			return sprintf(
				'<div class="brag-book-gallery-error">%s</div>',
				esc_html( $api_config['message'] )
			);
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

		// Add inline JavaScript for immediate functionality
		$html .= '<script type="text/javascript">
		(function() {
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
		})();
		</script>';

		return $html;
	}

	/**
	 * Validate API configuration for shortcode operations.
	 *
	 * Ensures API tokens and website property IDs are properly configured
	 * before attempting API calls. Returns standardized error structure.
	 *
	 * Validation Steps:
	 * - Check for API token presence
	 * - Verify website property ID
	 * - Return first valid configuration
	 *
	 * @since 3.0.0
	 * @return array{api_token?: string, website_property_id?: string, error?: bool, message?: string} Configuration or error.
	 */
	private static function validate_api_configuration(): array {
		// Retrieve API tokens with type safety
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$api_token  = match ( true ) {
			is_array( $api_tokens ) && ! empty( $api_tokens[0] ) => (string) $api_tokens[0],
			is_string( $api_tokens ) && ! empty( $api_tokens ) => $api_tokens,
			default => '',
		};

		// Retrieve website property IDs with type safety
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );
		$website_property_id  = match ( true ) {
			is_array( $website_property_ids ) && ! empty( $website_property_ids[0] ) => (string) $website_property_ids[0],
			is_string( $website_property_ids ) && ! empty( $website_property_ids ) => $website_property_ids,
			default => '',
		};

		// Validate configuration
		if ( empty( $api_token ) ) {
			return [
				'error'   => true,
				'message' => __( 'Please configure the plugin API token in settings.', 'brag-book-gallery' ),
			];
		}

		if ( empty( $website_property_id ) ) {
			return [
				'error'   => true,
				'message' => __( 'Please configure the website property ID in settings.', 'brag-book-gallery' ),
			];
		}

		return [
			'api_token'           => $api_token,
			'website_property_id' => $website_property_id,
		];
	}

}
