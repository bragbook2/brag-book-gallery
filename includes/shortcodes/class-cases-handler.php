<?php
/**
 * Cases Shortcode Handler for BRAGBook Gallery Plugin
 *
 * Comprehensive shortcode handler managing case grid displays, individual case views,
 * and URL routing for the BRAGBook Gallery system. Provides advanced filtering,
 * caching, and SEO-optimized URL generation with WordPress VIP compliance.
 *
 * Key Features:
 * - Dual shortcode support: [brag_book_gallery_cases] and [brag_book_gallery_case]
 * - Intelligent case URL routing with SEO suffix support
 * - Advanced data extraction from multiple API response formats
 * - WordPress VIP compliant caching and database operations
 * - Responsive grid layouts with configurable columns
 * - AJAX-compatible card rendering for dynamic content loading
 * - Comprehensive filtering by procedure, demographics, and patient data
 * - Nudity warning system with accessibility compliance
 * - Progressive image loading with skeleton loaders
 * - Mobile-optimized responsive design patterns
 *
 * Architecture:
 * - Static methods for stateless operations and better performance
 * - Centralized API configuration management with fallback strategies
 * - Modular rendering system with reusable components
 * - Security-first approach with comprehensive input sanitization
 * - Type-safe operations with PHP 8.2+ features
 * - WordPress VIP compliant error handling and logging
 *
 * URL Structure:
 * - Grid View: /gallery-slug/
 * - Filtered Grid: /gallery-slug/procedure-name/
 * - Single Case: /gallery-slug/procedure-name/case-seo-suffix/
 * - Legacy Support: Numeric IDs and various API response formats
 *
 * Caching Strategy:
 * - Transient-based caching with intelligent expiration
 * - Case data cached by API token and property ID combinations
 * - Supports both individual case and bulk case retrieval
 * - Cache invalidation hooks for content updates
 *
 * Security Features:
 * - Comprehensive input validation and sanitization
 * - XSS prevention through proper output escaping
 * - SQL injection protection via prepared statements
 * - CSRF protection through WordPress nonce system
 * - Safe handling of mixed data types from API responses
 *
 * Performance Optimizations:
 * - Lazy loading for images with intersection observer support
 * - Optimized database queries with minimal API calls
 * - Conditional asset loading based on content requirements
 * - Efficient data structure handling for large case collections
 * - Intelligent pagination with SEO-friendly URLs
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\shortcodes;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Resources\Asset_Manager;
use BRAGBookGallery\Includes\Core\Settings_Helper;
use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Shortcodes\Traits\Trait_Provider_Query;
use BRAGBookGallery\Includes\Shortcodes\Traits\Trait_Image_Variants;
use BRAGBookGallery\Includes\Shortcodes\Traits\Trait_Location_Query;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cases Shortcode Handler Class
 *
 * WordPress-based shortcode processor for the BRAGBook Gallery plugin, managing
 * comprehensive case display functionality using WP_Query and custom post types.
 * Implements WordPress VIP standards with PHP 8.2+ optimizations.
 *
 * Shortcodes Managed:
 * - [brag_book_gallery_cases]: Grid display with filtering and pagination using WP_Query
 *
 * Technical Implementation:
 * - PHP 8.2+ match expressions for cleaner conditional logic
 * - Union types for flexible parameter handling
 * - Readonly properties for immutable configuration
 * - Named arguments for improved code readability
 * - Null coalescing operators for safer data access
 *
 * WordPress VIP Compliance:
 * - Prepared SQL statements for database security
 * - Proper use of WordPress transient API
 * - VIP-approved caching strategies
 * - Sanitized output with appropriate escaping functions
 * - Performance-optimized database queries
 *
 * @since 3.0.0
 */
final class Cases_Handler {

	use Trait_Provider_Query;
	use Trait_Image_Variants;
	use Trait_Location_Query;

	/**
	 * Default grid columns
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEFAULT_COLUMNS = 2;

	/**
	 * Cache group for cases data
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_cases';

	/**
	 * Missing data log
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private static $missing_data_log = [];

	/**
	 * Initialize the cases handler
	 *
	 * Sets up shortcode registration and hooks.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_shortcode( 'brag_book_gallery_cases', [ self::class, 'handle' ] );

		// Add filters to prevent unwanted p/br tags in nested shortcodes
		add_filter( 'the_content', [ $this, 'shortcode_content_filter' ], 7 );
		add_filter( 'widget_text_content', [ $this, 'shortcode_content_filter' ], 7 );

		// Enable shortcodes in term descriptions
		add_filter( 'term_description', 'do_shortcode' );
		add_filter( 'category_description', 'do_shortcode' );
		add_filter( 'tag_description', 'do_shortcode' );

		// Register AJAX handlers for procedure navigation
		add_action( 'wp_ajax_brag_book_get_adjacent_cases', [ self::class, 'ajax_get_adjacent_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_get_adjacent_cases', [ self::class, 'ajax_get_adjacent_cases' ] );

		// Enable shortcodes in all taxonomy descriptions (including custom taxonomies)
		add_action( 'init', [ $this, 'enable_shortcodes_in_taxonomy_descriptions' ] );

		// Hook into wp_enqueue_scripts to check for our shortcode and enqueue assets
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ], 5 );

		// Register AJAX handlers for load more functionality
		add_action( 'wp_ajax_brag_book_gallery_load_more_cases', [ self::class, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_more_cases', [ self::class, 'ajax_load_more_cases' ] );
	}

	/**
	 * Filter content to prevent unwanted p/br tags around our shortcodes
	 *
	 * This method prevents WordPress from adding paragraph and line break tags
	 * around our shortcode content, especially important for nested shortcodes.
	 *
	 * @param string $content The content to filter.
	 *
	 * @return string Filtered content.
	 * @since 3.0.0
	 *
	 */
	public function shortcode_content_filter( string $content ): string {
		// Only process if our shortcode is present
		if ( ! has_shortcode( $content, 'brag_book_gallery_cases' ) ) {
			return $content;
		}

		// Temporarily remove wpautop filter to prevent auto p/br tags
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'widget_text_content', 'wpautop' );

		// Process shortcodes
		$content = do_shortcode( $content );

		// Add wpautop back for other content
		add_filter( 'the_content', 'wpautop' );
		add_filter( 'widget_text_content', 'wpautop' );

		// Clean up any remaining unwanted tags
		$patterns = [
			'/<p>\s*\[brag_book_gallery_cases([^\]]*)\]\s*<\/p>/i' => '[brag_book_gallery_cases$1]',
			'/<p>\s*<\/p>/i'                                       => '',
			'/<br\s*\/?>\s*\[brag_book_gallery_cases/i'            => '[brag_book_gallery_cases',
			'/\]\s*<br\s*\/?>/i'                                   => ']',
		];

		foreach ( $patterns as $pattern => $replacement ) {
			$content = preg_replace( $pattern, $replacement, $content );
		}

		return $content;
	}

	/**
	 * Enable shortcodes in all taxonomy descriptions
	 *
	 * This method adds do_shortcode filter to all registered taxonomies,
	 * ensuring shortcodes work in custom taxonomy descriptions.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public function enable_shortcodes_in_taxonomy_descriptions(): void {
		// Get all registered taxonomies
		$taxonomies = get_taxonomies( [], 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			// Add shortcode processing to each taxonomy description
			$filter_name = "{$taxonomy->name}_description";
			add_filter( $filter_name, 'do_shortcode' );
		}

		// Also add filters for common ways term descriptions are displayed
		add_filter( 'get_the_archive_description', 'do_shortcode' );
		add_filter( 'wpseo_metadesc', 'do_shortcode' ); // Yoast SEO compatibility
	}

	/**
	 * Check if cases shortcode is present and enqueue assets
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function maybe_enqueue_assets(): void {
		global $post;

		// Check if we have a post and if it contains our shortcode
		if ( ! $post || ! has_shortcode( $post->post_content, 'brag_book_gallery_cases' ) ) {
			return;
		}

		// Enqueue the main gallery CSS and JS since our cases use the same styles
		$plugin_version = Setup::get_plugin_version();
		$suffix         = Asset_Manager::get_asset_suffix();

		// Reuse the canonical handle so duplicate enqueues from other shortcodes collapse.
		wp_enqueue_style(
			'brag-book-gallery-main',
			Setup::get_asset_url( 'assets/css/brag-book-gallery' . $suffix . '.css' ),
			[],
			$plugin_version
		);

		wp_enqueue_script(
			'brag-book-gallery-main',
			Setup::get_asset_url( 'assets/js/brag-book-gallery' . $suffix . '.js' ),
			[],
			$plugin_version,
			true
		);

		// Route through the canonical localizer rather than writing
		// bragBookGalleryConfig directly — a second payload for the same object
		// replaces the first outright instead of merging. handle() localizes
		// the full config; the short payload that used to sit here added only
		// a columns key that no JavaScript reads.
		Asset_Manager::localize_gallery_script( [], [] );
	}

	/**
	 * Handle the cases shortcode
	 *
	 * Displays cases from the API with optional filtering by procedure.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Cases HTML or error message.
	 * @since 3.0.0
	 *
	 */
	public static function handle( array $atts ): string {
		// Validate and sanitize shortcode attributes with security-first approach
		$atts = self::validate_and_sanitize_shortcode_attributes( $atts );

		// Get API configuration with fallback to WordPress options
		$atts = self::get_api_configuration( $atts );

		// Get filter from URL if present.
		$filter_procedure = sanitize_text_field( get_query_var( 'filter_procedure', '' ) );
		$procedure_title  = sanitize_text_field( get_query_var( 'procedure_title', '' ) );
		$case_suffix      = sanitize_text_field( get_query_var( 'case_suffix', '' ) );

		// Check if we're on a taxonomy archive page
		if ( empty( $filter_procedure ) && is_tax( 'brag_book_procedures' ) ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$filter_procedure = $term->slug;
			}
		}

		// If we have procedure_title but not filter_procedure (case detail URL), use procedure_title for filtering.
		if ( empty( $filter_procedure ) && ! empty( $procedure_title ) ) {
			$filter_procedure = $procedure_title;
		}

		// FALLBACK: If query vars aren't set, try to extract from URL directly
		if ( empty( $filter_procedure ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$current_url   = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path_segments = array_filter( explode( '/', $current_url ) );

			// Look for pattern: /gallery/procedure-name/ or /gallery-slug/procedure-name/
			if ( count( $path_segments ) >= 2 ) {
				$gallery_segment   = $path_segments[0];
				$procedure_segment = $path_segments[1];

				// Check if first segment looks like a gallery (contains "gallery" or is a known gallery slug)
				if ( stripos( $gallery_segment, 'gallery' ) !== false ||
					 in_array( $gallery_segment, [ 'before-after', 'cases', 'results' ] ) ) {
					$filter_procedure = sanitize_title( $procedure_segment );
				}
			}
		}

		// Use case_suffix which now contains both numeric IDs and SEO suffixes.
		$case_identifier = ! empty( $case_suffix ) ? $case_suffix : '';

		// If we have a case identifier, load the main gallery and let JavaScript handle case loading
		if ( ! empty( $case_identifier ) ) {
			// Instead of rendering the case via PHP, load the main gallery with data attributes
			// that JavaScript can detect to automatically load the case
			$gallery_atts                        = $atts;
			$gallery_atts['data_case_id']        = $case_identifier;
			$gallery_atts['data_procedure_slug'] = $procedure_title;

			// Load the main gallery shortcode which will be handled by JavaScript
			return Gallery_Handler::handle( $gallery_atts );
		}

		// Validate required fields.
		if ( empty( $atts['api_token'] ) || empty( $atts['website_property_id'] ) ) {
			return sprintf(
				'<p class="brag-book-gallery-cases-error">%s</p>',
				esc_html__( 'Please configure API settings to display cases.', 'brag-book-gallery' )
			);
		}

		// Enqueue cases assets.
		Asset_Manager::enqueue_cases_assets();

		// Localize script with necessary data for filters. The case dataset is
		// emitted as a separate inline script (see Gallery_Handler) so we don't
		// duplicate it here.
		Asset_Manager::localize_gallery_script(
			[
				'api_token'           => $atts['api_token'],
				'website_property_id' => $atts['website_property_id'],
			],
			[] // Sidebar data is populated by JavaScript at runtime.
		);

		// Render cases grid using WordPress posts instead of API data.
		$output = self::render_cases_grid_from_posts( $filter_procedure, $atts );

		return $output;
	}

	/**
	 * Convert WordPress post to case data format
	 *
	 * @param \WP_Post $post The case post.
	 *
	 * @return array|null Case data array or null if invalid.
	 * @since 3.0.0
	 *
	 */
	private static function convert_post_to_case_data( \WP_Post $post ): ?array {
		// Get post meta data
		$case_data = self::get_case_meta_data( $post->ID );

		// Get procedure information
		$procedures = self::get_case_procedures( $post->ID );

		// Build case data array similar to API format
		return [
			'id'               => $case_data['case_id'] ?: $post->ID,
			'patientAge'       => $case_data['patient_age'],
			'patientGender'    => $case_data['patient_gender'],
			'patientEthnicity' => $case_data['patient_ethnicity'] ?: '',
			'patientHeight'    => $case_data['patient_height'] ?: '',
			'patientWeight'    => $case_data['patient_weight'] ?: '',
			'procedureDate'    => $case_data['procedure_date'],
			'caseNotes'        => $case_data['case_notes'],
			'beforeImage'      => $case_data['before_image'],
			'afterImage'       => $case_data['after_image'],
			'procedureIds'     => wp_list_pluck( $procedures, 'procedure_id' ),
			'procedures'       => $procedures,
			'seoSuffixUrl'     => $post->post_name,
			'permalink'        => get_permalink( $post->ID ),
		];
	}

	/**
	 * Validate and sanitize shortcode attributes with comprehensive security measures
	 *
	 * Processes raw shortcode attributes through WordPress shortcode_atts()
	 * with additional validation, sanitization, and type casting for security
	 * and data integrity. Uses PHP 8.2 features for clean validation logic.
	 *
	 * Security Features:
	 * - Attribute whitelisting through shortcode_atts()
	 * - Integer validation with bounds checking
	 * - String sanitization for text fields
	 * - Boolean validation with type casting
	 * - CSS class sanitization for XSS prevention
	 *
	 * @param array $raw_atts Raw shortcode attributes from user input.
	 *
	 * @return array Validated and sanitized attribute array.
	 * @since 3.0.0
	 *
	 */
	private static function validate_and_sanitize_shortcode_attributes( array $raw_atts ): array {
		// Get items per page from settings
		$items_per_page = Settings_Helper::get_items_per_page();

		// Get columns from settings
		$default_columns = absint( get_option( 'brag_book_gallery_columns', self::DEFAULT_COLUMNS ) );

		// Define default attributes with proper types
		$defaults = [
			'api_token'           => '',
			'website_property_id' => '',
			'procedure_ids'       => '',
			'provider_id'         => '',
			'limit'               => $items_per_page,
			'page'                => 1,
			'columns'             => $default_columns,
			'show_details'        => 'true',
			'class'               => '',
		];

		// Apply WordPress shortcode attribute parsing with defaults
		$atts = shortcode_atts( $defaults, $raw_atts, 'brag_book_gallery_cases' );

		// Validate and sanitize each attribute with type-specific handling
		return [
			'api_token'           => sanitize_text_field( $atts['api_token'] ),
			'website_property_id' => sanitize_text_field( $atts['website_property_id'] ),
			'procedure_ids'       => sanitize_text_field( $atts['procedure_ids'] ),
			'provider_id'         => absint( $atts['provider_id'] ),
			'limit'               => max( 1, min( 200, absint( $atts['limit'] ) ) ), // Bounds: 1-200
			'page'                => max( 1, absint( $atts['page'] ) ), // Minimum: 1
			'columns'             => max( 1, min( 6, absint( $atts['columns'] ) ) ), // Bounds: 1-6
			'show_details'        => sanitize_text_field( $atts['show_details'] ),
			'class'               => sanitize_html_class( $atts['class'] ),
		];
	}

	/**
	 * Extract API configuration from WordPress options with fallback handling
	 *
	 * Retrieves API authentication credentials from WordPress options with
	 * intelligent type handling for both legacy (string) and current (array)
	 * storage formats. Uses PHP 8.2 match expressions for cleaner logic.
	 *
	 * Configuration Sources (in order of precedence):
	 * 1. Shortcode attributes (highest priority)
	 * 2. WordPress options (brag_book_gallery_api_token, brag_book_gallery_website_property_id)
	 * 3. Empty values (handled gracefully by calling methods)
	 *
	 * @param array $atts Shortcode attributes with potential API configuration.
	 *
	 * @return array Enhanced attributes with API configuration populated.
	 * @since 3.0.0
	 *
	 */
	private static function get_api_configuration( array $atts ): array {
		// Extract API token with type-safe handling
		if ( empty( $atts['api_token'] ) ) {
			$atts['api_token'] = self::extract_api_credential(
				'brag_book_gallery_api_token',
				'API token'
			);
		}

		// Extract website property ID with type-safe handling
		if ( empty( $atts['website_property_id'] ) ) {
			$atts['website_property_id'] = self::extract_api_credential(
				'brag_book_gallery_website_property_id',
				'Website property ID'
			);
		}

		return $atts;
	}

	/**
	 * Extract API credential from WordPress options with type safety
	 *
	 * Handles both legacy string format and current array format for
	 * WordPress option storage. Uses PHP 8.2 match expression for
	 * clean type-based credential extraction.
	 *
	 * @param string $option_name WordPress option name to retrieve.
	 * @param string $credential_type Credential type for debugging context.
	 *
	 * @return string Extracted and sanitized credential value.
	 * @since 3.0.0
	 *
	 */
	private static function extract_api_credential( string $option_name, string $credential_type ): string {
		$credential_data = get_option( $option_name, array() );

		// Use PHP 8.2 match expression for type-based extraction
		$credential_value = match ( true ) {
			// Array format (current): Extract first element if available
			is_array( $credential_data ) && ! empty( $credential_data[0] ) => $credential_data[0],
			// String format (legacy): Use directly if not empty
			is_string( $credential_data ) && ! empty( trim( $credential_data ) ) => $credential_data,
			// Default: Return empty string for missing/invalid data
			default => '',
		};

		// Sanitize the extracted credential
		$sanitized_credential = sanitize_text_field( $credential_value );

		// Debug logging for credential extraction issues
		if ( empty( $sanitized_credential ) && ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			brag_book_log( "Cases Shortcode: {$credential_type} not found or empty in option '{$option_name}'" );
		}

		return $sanitized_credential;
	}

	/**
	 * Render case card for grid display
	 *
	 * Generates HTML for a single case card in the grid.
	 *
	 * @param array $case Case data.
	 * @param string $image_display_mode Image display mode.
	 * @param bool|null $procedure_nudity Whether the current procedure is flagged for nudity; null auto-detects from the case.
	 * @param string $procedure_context Procedure context from filter.
	 * @param string $current_procedure_id Current procedure ID for referrer tracking.
	 * @param string $current_term_id Current term ID for referrer tracking.
	 *
	 * @return string HTML output.
	 * @since 3.0.0
	 *
	 */
	public static function render_case_card(
		array $case,
		string $image_display_mode,
		?bool $procedure_nudity = null,
		string $procedure_context = '',
		string $current_procedure_id = '',
		string $current_term_id = ''
	): string {
		$html = '';

		// Prepare data attributes for filtering.
		$data_attrs = self::prepare_case_data_attributes( $case );

		// Get case ID and SEO information.
		$case_info = self::extract_case_info( $case );

		// Get procedure IDs for this case.
		$procedure_ids = '';
		if ( ! empty( $case['procedureIds'] ) && is_array( $case['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'intval', $case['procedureIds'] ) );
		}

		// Compute standardized alt text from API case data.
		$procedure_name = ! empty( $case['procedures'][0]['name'] ) ? $case['procedures'][0]['name'] : '';
		$post_id        = get_the_ID();
		if ( $post_id ) {
			$case_alt = self::get_case_alt_text( $post_id, $procedure_name, $case_info['case_id'] );
		} else {
			$case_alt = sprintf(
				/* translators: 1: procedure name, 2: case number */
				__( 'Before and after %1$s case %2$s', 'brag-book-gallery' ),
				sanitize_text_field( $procedure_name ),
				sanitize_text_field( $case_info['case_id'] )
			);
		}

		$html .= sprintf(
			'<article class="brag-book-gallery-case-card" %s data-case-id="%s" data-procedure-case-id="%s" data-procedure-ids="%s" data-current-procedure-id="%s" data-current-term-id="%s" data-alt-text="%s">',
			$data_attrs,
			esc_attr( $case_info['case_id'] ),
			esc_attr( $case['id'] ?? $case_info['case_id'] ), // The small ID from API for view tracking
			esc_attr( $procedure_ids ),
			esc_attr( $current_procedure_id ),
			esc_attr( $current_term_id ),
			esc_attr( $case_alt )
		);

		// Get case URL.
		$case_url = self::get_case_url( $case_info, $procedure_context, $case );

		// Add case content.
		$html .= '<a href="' . esc_url( $case_url ) . '" class="case-link" data-alt-text="' . esc_attr( $case_alt ) . '">';

		// Add images.
		if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
			$first_photo = reset( $case['photoSets'] );

			if ( 'before_after' === $image_display_mode ) {
				// Show both before and after images.
				$html .= '<div class="brag-book-gallery-case-images before-after">';
				if ( ! empty( $first_photo['beforePhoto'] ) ) {
					$html .= sprintf(
						'<img src="%s" alt="%s" class="before-image" />',
						esc_url( $first_photo['beforePhoto'] ),
						esc_attr( $case_alt )
					);
				}
				if ( ! empty( $first_photo['afterPhoto'] ) ) {
					$html .= sprintf(
						'<img src="%s" alt="%s" class="after-image" />',
						esc_url( $first_photo['afterPhoto'] ),
						esc_attr( $case_alt )
					);
				}
				$html .= '</div>';
			} else {
				// Show single image (post-processed preferred, fallback to after, then before).
				$image_url = $first_photo['postProcessedImageLocation'] ?? $first_photo['afterPhoto'] ?? $first_photo['beforePhoto'] ?? '';
				if ( ! empty( $image_url ) ) {
					$html .= sprintf(
						'<div class="brag-book-gallery-case-images"><img src="%s" alt="%s" /></div>',
						esc_url( $image_url ),
						esc_attr( $case_alt )
					);
				}
			}
		}

		// Add nudity warning for the configured preset.
		$html .= HTML_Renderer::maybe_render_nudity_warning( (int) $post_id, $procedure_nudity );

		// Add case title or provider name based on settings.
		$show_provider = (bool) get_option( 'brag_book_gallery_show_provider', false );

		if ( $show_provider ) {
			// Show provider info with profile photo from taxonomy if available
			$post_id = get_the_ID();
			if ( $post_id ) {
				$html .= self::render_provider_card_info( $post_id, $case_info['seo_headline'] ?? '' );
			} else {
				// Fallback to case data if no post context
				$provider_name = $case['providerName'] ?? $case['provider_name'] ?? '';
				if ( ! empty( $provider_name ) ) {
					$html .= '<h3 class="case-title provider-name">' . esc_html( $provider_name ) . '</h3>';
				}
			}
		} elseif ( ! empty( $case_info['seo_headline'] ) ) {
			// Show case title as before
			$html .= '<h3 class="case-title">' . esc_html( $case_info['seo_headline'] ) . '</h3>';
		}

		$html .= '</a>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Prepare case data attributes
	 *
	 * Prepares data attributes for case card filtering.
	 *
	 * @param array $case Case data.
	 *
	 * @return string Data attributes HTML.
	 * @since 3.0.0
	 *
	 */
	private static function prepare_case_data_attributes( array $case ): string {
		$attrs = 'data-id="' . get_the_ID() . '"';
		$attrs .= 'data-card="true"';

		// Add age.
		if ( ! empty( $case['age'] ) ) {
			$attrs .= ' data-age="' . esc_attr( $case['age'] ) . '"';
		}

		// Add gender.
		if ( ! empty( $case['gender'] ) ) {
			$attrs .= ' data-gender="' . esc_attr( strtolower( $case['gender'] ) ) . '"';
		}

		// Add ethnicity.
		if ( ! empty( $case['ethnicity'] ) ) {
			$attrs .= ' data-ethnicity="' . esc_attr( strtolower( $case['ethnicity'] ) ) . '"';
		}

		// Add height with unit.
		if ( ! empty( $case['height'] ) ) {
			$height_value = $case['height'];
			$height_unit  = ! empty( $case['heightUnit'] ) ? $case['heightUnit'] : '';
			$attrs        .= ' data-height="' . esc_attr( $height_value ) . '"';
			$attrs        .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
			$attrs        .= ' data-height-full="' . esc_attr( $height_value . $height_unit ) . '"';
		}

		// Add weight with unit.
		if ( ! empty( $case['weight'] ) ) {
			$weight_value = $case['weight'];
			$weight_unit  = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
			$attrs        .= ' data-weight="' . esc_attr( $weight_value ) . '"';
			$attrs        .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
			$attrs        .= ' data-weight-full="' . esc_attr( $weight_value . $weight_unit ) . '"';
		}

		// Procedure-detail attributes (scoped to current procedure on taxonomy pages).
		if ( preg_match( '/data-id="(\d+)"/', $attrs, $matches ) && ! empty( $matches[1] ) ) {
			$detail_attrs = self::build_procedure_detail_attrs( (int) $matches[1] );
			if ( ! empty( $detail_attrs ) ) {
				$attrs .= ' ' . implode( ' ', $detail_attrs );
			}
		}

		return $attrs;
	}

	/**
	 * Prepare case data attributes from WordPress post meta
	 *
	 * Generates data attributes for case filtering using post meta values
	 * stored in WordPress database according to mapping.md format.
	 *
	 * @param int|\WP_Post $post Post ID or post object.
	 *
	 * @return string HTML data attributes string.
	 * @since 3.0.0
	 *
	 */
	private static function prepare_case_data_attributes_from_post_meta( $post ): string {
		if ( is_numeric( $post ) ) {
			$post_id = (int) $post;
		} elseif ( $post instanceof \WP_Post ) {
			$post_id = $post->ID;
		} else {
			return 'data-card="true"';
		}

		$attrs = 'data-card="true"';

		// Add age using new meta key format
		$age = get_post_meta( $post_id, 'brag_book_gallery_patient_age', true );
		if ( ! empty( $age ) ) {
			$attrs .= ' data-age="' . esc_attr( $age ) . '"';
		}

		// Add gender using new meta key format
		$gender = get_post_meta( $post_id, 'brag_book_gallery_patient_gender', true );
		if ( ! empty( $gender ) ) {
			$attrs .= ' data-gender="' . esc_attr( strtolower( $gender ) ) . '"';
		}

		// Add ethnicity using new meta key format
		$ethnicity = get_post_meta( $post_id, 'brag_book_gallery_ethnicity', true );
		if ( ! empty( $ethnicity ) ) {
			$attrs .= ' data-ethnicity="' . esc_attr( strtolower( $ethnicity ) ) . '"';
		}

		// Add height with unit using new meta key format
		$height      = get_post_meta( $post_id, 'brag_book_gallery_height', true );
		$height_unit = get_post_meta( $post_id, 'brag_book_gallery_height_unit', true );
		if ( ! empty( $height ) ) {
			$attrs .= ' data-height="' . esc_attr( $height ) . '"';
			if ( ! empty( $height_unit ) ) {
				$attrs .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
				$attrs .= ' data-height-full="' . esc_attr( $height . $height_unit ) . '"';
			}
		}

		// Add weight with unit using new meta key format
		$weight      = get_post_meta( $post_id, 'brag_book_gallery_weight', true );
		$weight_unit = get_post_meta( $post_id, 'brag_book_gallery_weight_unit', true );
		if ( ! empty( $weight ) ) {
			$attrs .= ' data-weight="' . esc_attr( $weight ) . '"';
			if ( ! empty( $weight_unit ) ) {
				$attrs .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
				$attrs .= ' data-weight-full="' . esc_attr( $weight . $weight_unit ) . '"';
			}
		}

		// Procedure-detail attributes (scoped to current procedure on taxonomy pages).
		$detail_attrs = self::build_procedure_detail_attrs( $post_id );
		if ( ! empty( $detail_attrs ) ) {
			$attrs .= ' ' . implode( ' ', $detail_attrs );
		}

		return $attrs;
	}

	/**
	 * Build procedure-detail data attributes for a case post.
	 *
	 * Reads the `brag_book_gallery_procedure_details` post meta (a JSON map of
	 * `{ "<api_procedure_id>": { "<Label>": <value|array> } }`) and returns one
	 * `data-procedure-detail-<slug>="..."` attribute per field.
	 *
	 * On a procedure taxonomy page (`brag_book_procedures`), the result is scoped
	 * to the current term's API procedure id so a multi-procedure case only
	 * contributes filters relevant to the page being viewed. When no procedure
	 * context is present, all procedures' details are emitted (back-compat).
	 *
	 * @param int $post_id Case post ID.
	 *
	 * @return array<int, string> List of attribute strings (`name="value"`).
	 * @since 3.3.3
	 */
	private static function build_procedure_detail_attrs( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return [];
		}

		$json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );
		if ( empty( $json ) ) {
			return [];
		}

		$details = json_decode( (string) $json, true );
		if ( ! is_array( $details ) || empty( $details ) ) {
			return [];
		}

		$current_id = self::get_current_api_procedure_id();
		if ( '' !== $current_id ) {
			if ( ! isset( $details[ $current_id ] ) || ! is_array( $details[ $current_id ] ) ) {
				return [];
			}
			$details = [ $current_id => $details[ $current_id ] ];
		}

		$attrs = [];
		foreach ( $details as $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $label => $value ) {
				$attr_name = sanitize_title_with_dashes( (string) $label );
				if ( '' === $attr_name ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$attr_value = implode( ',', array_map(
						static fn( $v ) => strtolower( (string) $v ),
						$value
					) );
				} else {
					$attr_value = strtolower( (string) $value );
				}
				$attrs[] = 'data-procedure-detail-' . esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '"';
			}
		}

		return $attrs;
	}

	/**
	 * Resolve the current procedure's API id from the queried taxonomy term.
	 *
	 * Returns the `procedure_id` term meta when on a `brag_book_procedures`
	 * taxonomy archive, or an empty string in any other context. Used to scope
	 * card-level data attributes to the procedure being viewed.
	 *
	 * @return string API procedure id, or empty string if not on a procedure page.
	 * @since 3.3.3
	 */
	private static function get_current_api_procedure_id(): string {
		if ( ! is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			return '';
		}
		$term_id = (int) get_queried_object_id();
		if ( $term_id <= 0 ) {
			return '';
		}
		$api_id = get_term_meta( $term_id, 'procedure_id', true );
		if ( '' === $api_id || null === $api_id || false === $api_id ) {
			return '';
		}
		return (string) $api_id;
	}

	/**
	 * Render case card from WordPress post
	 *
	 * Generates case card HTML using WordPress post data and meta fields
	 * instead of API data. Uses the new meta key format from mapping.md.
	 *
	 * @param \WP_Post $post WordPress post object.
	 * @param string $image_display_mode Image display mode (single/before_after).
	 * @param bool|null $procedure_nudity Whether the current procedure is flagged for nudity; null auto-detects from the case.
	 * @param string $procedure_context Procedure context for URL generation.
	 *
	 * @return string Case card HTML.
	 * @since 3.0.0
	 *
	 */
	public static function render_case_card_from_post(
		\WP_Post $post,
		string $image_display_mode = 'single',
		?bool $procedure_nudity = null,
		string $procedure_context = ''
	): string {
		$html = '';

		// Prepare data attributes from post meta.
		$data_attrs = self::prepare_case_data_attributes_from_post_meta( $post );

		// Get case ID from post meta (API ID).
		$case_id = get_post_meta( $post->ID, 'brag_book_gallery_case_id', true );
		if ( empty( $case_id ) ) {
			$case_id = $post->ID; // Use WordPress post ID as fallback
		}

		// Get procedure IDs from post meta.
		$procedure_ids = get_post_meta( $post->ID, 'brag_book_gallery_procedure_ids', true );

		// Get procedure case ID (small API ID) for view tracking
		$procedure_case_id = get_post_meta( $post->ID, 'brag_book_gallery_procedure_case_id', true );
		if ( empty( $procedure_case_id ) ) {
			$procedure_case_id = get_post_meta( $post->ID, 'brag_book_gallery_original_case_id', true );
		}

		// Get SEO suffix URL from post meta.
		$seo_suffix_url = get_post_meta( $post->ID, 'brag_book_gallery_seo_suffix_url', true );

		if ( empty( $seo_suffix_url ) ) {
			$seo_suffix_url = $post->post_name; // Use post slug as fallback
		}

		// Get procedure name for alt text from taxonomy terms.
		$post_procedures    = wp_get_post_terms( $post->ID, Taxonomies::TAXONOMY_PROCEDURES );
		$post_procedure_name = '';
		if ( ! is_wp_error( $post_procedures ) && ! empty( $post_procedures ) ) {
			$post_procedure_name = $post_procedures[0]->name;
		}

		// Compute standardized alt text.
		$case_alt = self::get_case_alt_text( $post->ID, $post_procedure_name, $case_id );

		$html .= sprintf(
			'<article class="brag-book-gallery-case-card" %s data-case-id="%s" data-procedure-case-id="%s" data-procedure-ids="%s" data-alt-text="%s">',
			$data_attrs,
			esc_attr( $case_id ),
			esc_attr( $procedure_case_id ), // The small API ID for view tracking
			esc_attr( $procedure_ids ),
			esc_attr( $case_alt )
		);

		// Build case URL.
		$gallery_slug = self::get_gallery_page_slug();
		if ( ! empty( $procedure_context ) ) {
			$case_url = sprintf( '/%s/%s/%s/', $gallery_slug, $procedure_context, $seo_suffix_url );
		} else {
			$case_url = sprintf( '/%s/case/%s/', $gallery_slug, $seo_suffix_url );
		}

		// Add case content.
		$html .= '<a href="' . esc_url( $case_url ) . '" class="case-link">';

		// Get image URLs from post meta.
		$before_urls         = get_post_meta( $post->ID, 'brag_book_gallery_case_before_url', true );
		$after_urls          = get_post_meta( $post->ID, 'brag_book_gallery_case_after_url', true );
		$post_processed_urls = get_post_meta( $post->ID, 'brag_book_gallery_case_post_processed_url', true );

		if ( empty( $before_urls ) && empty( $after_urls ) && empty( $post_processed_urls ) ) {
			$image_sets = get_post_meta( $post->ID, 'brag_book_gallery_image_url_sets', true );
			if ( ! empty( $image_sets ) && is_array( $image_sets ) ) {
				$first_set           = reset( $image_sets );
				$before_urls         = $first_set['before_url'] ?? '';
				$after_urls          = $first_set['after_url'] ?? '';
				$post_processed_urls = $first_set['post_processed_url'] ?? '';
			}
		}

		// Parse URLs (handle semicolon-separated format).
		$before_url    = '';
		$after_url     = '';
		$processed_url = '';

		if ( ! empty( $post_processed_urls ) ) {
			$urls          = explode( "\n", $post_processed_urls );
			$processed_url = ! empty( $urls[0] ) ? rtrim( $urls[0], ';' ) : '';
		}

		if ( ! empty( $before_urls ) ) {
			$urls       = explode( "\n", $before_urls );
			$before_url = ! empty( $urls[0] ) ? rtrim( $urls[0], ';' ) : '';
		}

		if ( ! empty( $after_urls ) ) {
			$urls      = explode( "\n", $after_urls );
			$after_url = ! empty( $urls[0] ) ? rtrim( $urls[0], ';' ) : '';
		}

		// Determine which image to show.
		$main_image_url = $processed_url ?: $after_url ?: $before_url;

		if ( 'before_after' === $image_display_mode && ! empty( $before_url ) && ! empty( $after_url ) ) {
			// Show both before and after images.
			$html .= '<div class="brag-book-gallery-case-images before-after">';
			$html .= sprintf(
				'<img src="%s" alt="%s" class="before-image" />',
				esc_url( $before_url ),
				esc_attr( $case_alt )
			);
			$html .= sprintf(
				'<img src="%s" alt="%s" class="after-image" />',
				esc_url( $after_url ),
				esc_attr( $case_alt )
			);
			$html .= '</div>';
		} elseif ( ! empty( $main_image_url ) ) {
			// Show single image.
			$html .= '<div class="brag-book-gallery-case-images single-image">';
			$html .= sprintf(
				'<img src="%s" alt="%s" class="case-image" />',
				esc_url( $main_image_url ),
				esc_attr( $case_alt )
			);
			$html .= '</div>';
		} else {
			// No image available.
			$html .= '<div class="brag-book-gallery-case-images no-image">';
			$html .= '<div class="placeholder-image">' . esc_html__( 'No image available', 'brag-book-gallery' ) . '</div>';
			$html .= '</div>';
		}

		// Add case title or provider name based on settings.
		$show_provider = (bool) get_option( 'brag_book_gallery_show_provider', false );

		if ( $show_provider ) {
			// Show provider info with profile photo from taxonomy
			$seo_headline = get_post_meta( $post->ID, 'brag_book_gallery_seo_headline', true );
			$html .= self::render_provider_card_info( $post->ID, $seo_headline ?: '' );
		} else {
			// Show case SEO headline if available
			$seo_headline = get_post_meta( $post->ID, 'brag_book_gallery_seo_headline', true );
			if ( ! empty( $seo_headline ) ) {
				$html .= '<h3 class="case-title">' . esc_html( $seo_headline ) . '</h3>';
			}
		}

		$html .= '</a>'; // Close case link

		// Add nudity warning for the configured preset.
		$html .= HTML_Renderer::maybe_render_nudity_warning( $post->ID, $procedure_nudity );

		$html .= '</article>';

		return $html;
	}

	/**
	 * Renders a grid of cases from WordPress posts with filtering and pagination support.
	 *
	 * This method generates a complete gallery interface including:
	 * - Filter controls for procedures
	 * - Grid layout selector (2 or 3 columns)
	 * - Masonry-style case cards
	 * - Load more functionality with infinite scroll support
	 *
	 * @param string $filter_procedure Optional. Slug of the procedure taxonomy to filter by.
	 *                                 If provided, only cases with this procedure will be shown.
	 * @param array $atts Optional. Additional attributes for customization.
	 *                                 - 'image_display_mode': How to display images ('single' or other modes)
	 *                                 - 'provider_id': Provider taxonomy API ID to filter cases by
	 *
	 * @return string HTML output for the complete cases gallery interface.
	 * @since 1.0.0
	 *
	 */
	public static function render_cases_grid_from_posts( string $filter_procedure = '', array $atts = [] ): string {
		$provider_id = absint( $atts['provider_id'] ?? 0 );

		// Build the view context and render page 1 through the shared pager, so the
		// initial grid and the "load more" button slice the same ordered list.
		$context = [
			'provider_id'    => $provider_id,
			'procedure_slug' => $filter_procedure,
		];

		$items_per_page = Settings_Helper::get_items_per_page();
		$page           = self::render_context_page( $context, 1, $items_per_page );

		// Return early with "no cases" message if there are no results
		if ( 0 === $page['total'] ) {
			return self::render_no_cases_found();
		}

		$default_columns = absint( get_option( 'brag_book_gallery_columns', self::DEFAULT_COLUMNS ) );

		// Build the complete gallery HTML structure
		$output = sprintf(
			'<div class="brag-book-gallery-wrapper" role="application" aria-label="%s">
            <div class="brag-book-gallery-main-content" role="region" aria-label="%s" id="gallery-content">
                %s
                <div class="brag-book-gallery-cases-container">
                    <div class="brag-book-gallery-case-grid masonry-layout" data-columns="%d">
                        %s
                    </div>
                    %s
                </div>
            </div>
        </div>',
			esc_attr__( 'Cases Gallery', 'brag-book-gallery' ),
			esc_attr__( 'Gallery content', 'brag-book-gallery' ),
			self::render_controls( $default_columns ),
			$default_columns,
			$page['html'],
			self::render_load_more_button( $context, $items_per_page, $page['total'] )
		);

		return $output;
	}

	/**
	 * Get WordPress post IDs from case order list
	 *
	 * Converts an array of case API IDs to WordPress post IDs while maintaining order.
	 *
	 * @param array $case_order_list Array of case API IDs in order.
	 *
	 * @return array Array of WordPress post IDs in the same order.
	 * @since 3.3.0
	 */
	/**
	 * Sort posts by case order list
	 *
	 * Sorts an array of WP_Post objects based on the case order list from taxonomy term meta.
	 * The case order list contains arrays with 'wp_id' and 'api_id' keys.
	 *
	 * @param array $posts Array of WP_Post objects.
	 * @param array $case_order_list Array of case data with 'wp_id' and 'api_id' keys.
	 *
	 * @return array Sorted array of WP_Post objects.
	 * @since 3.3.0
	 */
	private static function sort_posts_by_case_order( array $posts, array $case_order_list ): array {
		// Create a map of post ID to post object for quick lookup
		$post_map = [];
		foreach ( $posts as $post ) {
			$post_map[ $post->ID ] = $post;
		}

		// Build ordered array based on case order list (using WordPress post IDs)
		$ordered_posts = [];
		$used_post_ids = [];

		foreach ( $case_order_list as $case_data ) {
			// Support both new format (array with wp_id/api_id) and legacy format (just API IDs)
			$wp_id = is_array( $case_data ) ? ( $case_data['wp_id'] ?? null ) : null;

			if ( $wp_id && isset( $post_map[ $wp_id ] ) ) {
				$ordered_posts[] = $post_map[ $wp_id ];
				$used_post_ids[] = $wp_id;
			}
		}

		// Add any remaining posts that weren't in the case order list
		foreach ( $posts as $post ) {
			if ( ! in_array( $post->ID, $used_post_ids, true ) ) {
				$ordered_posts[] = $post;
			}
		}

		return $ordered_posts;
	}

	/**
	 * Renders the "no cases found" message with proper wrapper structure.
	 *
	 * @return string HTML output for the no cases found message.
	 * @since 1.0.0
	 *
	 */
	private static function render_no_cases_found(): string {
		return sprintf(
			'<div class="brag-book-gallery-wrapper">
            <div class="brag-book-gallery-main-content">
                <p>%s</p>
            </div>
        </div>',
			esc_html__( 'No cases found.', 'brag-book-gallery' )
		);
	}

	/**
	 * Renders the complete controls section including filters and grid selector.
	 *
	 * Creates the top control bar with:
	 * - Left side: Filter dropdown and active filters display
	 * - Right side: Grid layout selector buttons
	 *
	 * @param int $columns Currently active number of columns (2 or 3).
	 *
	 * @return string HTML output for the controls section.
	 * @since 1.0.0
	 *
	 */
	private static function render_controls( int $columns ): string {
		return sprintf(
			'<div class="brag-book-gallery-controls">
            <div class="brag-book-gallery-controls-left">
                %s
                <div class="brag-book-gallery-active-filters" style="display: none;"></div>
            </div>
            %s
        </div>',
			self::render_filter_dropdown(),
			self::render_grid_selector( $columns )
		);
	}

	/**
	 * Renders the collapsible filter dropdown panel.
	 *
	 * Creates a details/summary element containing:
	 * - Filter icon and label
	 * - Expandable panel with filter options (populated by JavaScript)
	 * - Apply and Clear action buttons
	 *
	 * @return string HTML output for the filter dropdown.
	 * @since 1.0.0
	 *
	 */
	private static function render_filter_dropdown(): string {
		// SVG icon for the filter button (Material Design filter list icon)
		$filter_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor">
			<path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"></path>
		</svg>';

		return sprintf(
			'<details class="brag-book-gallery-filter-dropdown" id="procedure-filters-details">
				<summary class="brag-book-gallery-filter-dropdown__toggle">
					%s
					<span>%s</span>
				</summary>
				<div class="brag-book-gallery-filter-dropdown__panel">
					<div class="brag-book-gallery-filter-content">
						<div class="brag-book-gallery-filter-section">
							<div id="brag-book-gallery-filters">
								<!-- Filter options will be populated by JavaScript -->
							</div>
						</div>
					</div>
					<div class="brag-book-gallery-filter-actions">
						<button class="brag-book-gallery-button brag-book-gallery-button--apply" onclick="applyProcedureFilters()">
							%s
						</button>
						<button class="brag-book-gallery-button brag-book-gallery-button--clear" onclick="clearProcedureFilters()">
							%s
						</button>
					</div>
				</div>
			</details>',
			$filter_icon,
			esc_html__( 'Filters', 'brag-book-gallery' ),
			esc_html__( 'Apply Filters', 'brag-book-gallery' ),
			esc_html__( 'Clear All', 'brag-book-gallery' )
		);
	}

	/**
	 * Renders the grid layout selector controls.
	 *
	 * Creates buttons to switch between 2-column and 3-column grid layouts.
	 *
	 * @param int $columns Currently active number of columns (2 or 3).
	 *
	 * @return string HTML output for the grid selector.
	 * @since 1.0.0
	 *
	 */
	private static function render_grid_selector( int $columns ): string {
		return sprintf(
			'<div class="brag-book-gallery-grid-selector">
				<span class="brag-book-gallery-grid-label">%s</span>
				<div class="brag-book-gallery-grid-buttons">
					%s
					%s
				</div>
			</div>',
			esc_html__( 'View:', 'brag-book-gallery' ),
			self::render_grid_button( 2, $columns ),
			self::render_grid_button( 3, $columns )
		);
	}

	/**
	 * Renders an individual grid layout button with icon and accessibility features.
	 *
	 * Creates a button with:
	 * - SVG icon representing the grid layout
	 * - Active state styling when selected
	 * - ARIA labels for screen readers
	 * - Click handler to update layout
	 *
	 * @param int $num_columns Number of columns this button represents (2 or 3).
	 * @param int $active_columns Currently active number of columns for comparison.
	 *
	 * @return string HTML output for the grid button.
	 * @since 1.0.0
	 *
	 */
	private static function render_grid_button( int $num_columns, int $active_columns ): string {
		// Add 'active' class if this button represents the current layout
		$is_active = $num_columns === $active_columns ? ' active' : '';

		// SVG paths for grid icons
		// 2-column icon: 2x2 grid of squares
		// 3-column icon: 3x3 grid of smaller squares
		$svg_icons = [
			2 => '<rect x="1" y="1" width="6" height="6"></rect><rect x="9" y="1" width="6" height="6"></rect>
              <rect x="1" y="9" width="6" height="6"></rect><rect x="9" y="9" width="6" height="6"></rect>',
			3 => '<rect x="1" y="1" width="4" height="4"></rect><rect x="6" y="1" width="4" height="4"></rect><rect x="11" y="1" width="4" height="4"></rect>
              <rect x="1" y="6" width="4" height="4"></rect><rect x="6" y="6" width="4" height="4"></rect><rect x="11" y="6" width="4" height="4"></rect>
              <rect x="1" y="11" width="4" height="4"></rect><rect x="6" y="11" width="4" height="4"></rect><rect x="11" y="11" width="4" height="4"></rect>'
		];

		return sprintf(
			'<button class="brag-book-gallery-grid-btn%s" data-columns="%d" onclick="updateGridLayout(%d)" aria-label="%s">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                %s
            </svg>
            <span class="sr-only">%s</span>
        </button>',
			$is_active,
			$num_columns,
			$num_columns,
			/* translators: %d: column count */
			sprintf( esc_attr__( 'View in %d columns', 'brag-book-gallery' ), $num_columns ),
			$svg_icons[ $num_columns ],
			/* translators: %d: column count */
			sprintf( esc_html__( '%d Columns', 'brag-book-gallery' ), $num_columns )
		);
	}

	/**
	 * Renders all case cards by iterating through the posts array.
	 *
	 * Delegates the rendering of individual cards to the render_case_card_from_post method.
	 *
	 * @param array $posts Array of WP_Post objects representing cases.
	 * @param string $image_display_mode How images should be displayed in cards.
	 * @param string $filter_procedure Current procedure filter for context.
	 *
	 * @return string HTML output for all case cards concatenated.
	 * @since 1.0.0
	 *
	 */
	private static function render_case_cards( array $posts, string $image_display_mode, string $filter_procedure ): string {
		$output = '';

		// Iterate through each post and render its card
		foreach ( $posts as $post ) {
			$output .= self::render_case_card_from_post( $post, $image_display_mode, null, $filter_procedure );
		}

		return $output;
	}

	/**
	 * Renders the "Load More" button for pagination.
	 *
	 * Creates a button that:
	 * - Only appears when there are more cases than the initial page size
	 * - Can be hidden for infinite scroll mode
	 * - Contains data attributes for AJAX loading
	 * - Tracks procedure filters for consistent loading
	 *
	 * @param array<string,mixed> $context        View context (provider/procedure/location).
	 * @param int                 $items_per_page Number of items shown per page.
	 * @param int                 $total_cases    Total number of cases in the context.
	 *
	 * @return string HTML output for the load more button, or empty string if not needed.
	 * @since 1.0.0
	 *
	 */
	public static function render_load_more_button( array $context, int $items_per_page, int $total_cases ): string {
		// Don't show load more button if all cases fit on first page
		if ( $total_cases <= $items_per_page ) {
			return '';
		}

		$provider_id    = absint( $context['provider_id'] ?? 0 );
		$provider_slug  = (string) ( $context['provider_slug'] ?? '' );
		$procedure_slug = (string) ( $context['procedure_slug'] ?? '' );

		// Resolve the procedure term id so the pager can scope by term directly.
		$term_id = absint( $context['term_id'] ?? 0 );
		if ( $term_id <= 0 && '' !== $procedure_slug ) {
			$procedure_term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
			if ( $procedure_term instanceof \WP_Term ) {
				$term_id = $procedure_term->term_id;
			}
		}

		$lat = isset( $context['lat'] ) && is_numeric( $context['lat'] ) ? (string) (float) $context['lat'] : '';
		$lng = isset( $context['lng'] ) && is_numeric( $context['lng'] ) ? (string) (float) $context['lng'] : '';

		// Hide button if infinite scroll is enabled (JavaScript will handle loading)
		$infinite_scroll = get_option( 'brag_book_gallery_infinite_scroll', 'no' );
		$display_style   = ( 'yes' === $infinite_scroll ) ? ' style="display: none;"' : '';

		return sprintf(
			'<div class="brag-book-gallery-load-more-container">
            <button class="brag-book-gallery-button brag-book-gallery-button--load-more"
                data-action="load-more"
                data-start-page="2"
                data-per-page="%1$d"
                data-procedure-ids="%2$s"
                data-procedure-name="%3$s"
                data-term-id="%2$s"
                data-provider-id="%4$s"
                data-provider-slug="%5$s"
                data-lat="%6$s"
                data-lng="%7$s"
                data-random-seed="%8$s"
                onclick="loadMoreCasesFromCache(this)"%9$s>
                %10$s
            </button>
        </div>',
			$items_per_page,
			esc_attr( (string) $term_id ),
			esc_attr( $procedure_slug ),
			esc_attr( (string) $provider_id ),
			esc_attr( $provider_slug ),
			esc_attr( $lat ),
			esc_attr( $lng ),
			esc_attr( (string) absint( $context['random_seed'] ?? 0 ) ),
			$display_style,
			esc_html__( 'Load More', 'brag-book-gallery' )
		);
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
	 * Extract case information
	 *
	 * Extracts case ID and SEO information from case data.
	 *
	 * @param array $case Case data.
	 *
	 * @return array Case information array.
	 * @since 3.0.0
	 *
	 */
	private static function extract_case_info( array $case ): array {
		$info = array(
			'case_id'              => $case['id'] ?? '',
			'seo_suffix_url'       => '',
			'seo_headline'         => '',
			'seo_page_title'       => '',
			'seo_page_description' => '',
		);

		// Extract SEO fields from caseDetails if available.
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );

			if ( empty( $info['case_id'] ) ) {
				$info['case_id'] = $first_detail['caseId'] ?? '';
			}

			$info['seo_suffix_url']       = $first_detail['seoSuffixUrl'] ?? '';
			$info['seo_headline']         = $first_detail['seoHeadline'] ?? '';
			$info['seo_page_title']       = $first_detail['seoPageTitle'] ?? '';
			$info['seo_page_description'] = $first_detail['seoPageDescription'] ?? '';
		}

		// Use seoSuffixUrl for URL if available, otherwise use case_id.
		$info['url_suffix'] = ! empty( $info['seo_suffix_url'] ) ? $info['seo_suffix_url'] : $info['case_id'];

		return $info;
	}

	/**
	 * Get standardized alt text for a case image.
	 *
	 * Uses SEO alt text override from post meta if set, otherwise falls back
	 * to the format: "Before and after {procedure} case {case_id}".
	 *
	 * @param int    $post_id          WordPress post ID.
	 * @param string $procedure_name   Procedure display name.
	 * @param string $fallback_case_id Case ID fallback if meta is empty.
	 *
	 * @return string Sanitized alt text.
	 * @since 3.3.2
	 */
	public static function get_case_alt_text( int $post_id, string $procedure_name = '', string $fallback_case_id = '' ): string {
		$seo_alt = get_post_meta( $post_id, 'brag_book_gallery_seo_alt_text', true );
		if ( ! empty( $seo_alt ) ) {
			return sanitize_text_field( $seo_alt );
		}

		$case_id = get_post_meta( $post_id, 'brag_book_gallery_case_id', true );
		if ( empty( $case_id ) ) {
			$case_id = $fallback_case_id;
		}

		return sprintf(
			/* translators: 1: procedure name, 2: case number */
			__( 'Before and after %1$s case %2$s', 'brag-book-gallery' ),
			sanitize_text_field( $procedure_name ),
			sanitize_text_field( $case_id )
		);
	}

	/**
	 * Get case URL
	 *
	 * Generates the URL for a case detail page.
	 *
	 * @param array $case_info Case information.
	 * @param string $procedure_context Procedure context.
	 * @param array $case Full case data.
	 *
	 * @return string Case URL.
	 * @since 3.0.0
	 *
	 */
	private static function get_case_url( array $case_info, string $procedure_context, array $case ): string {
		// Get query vars.
		$filter_procedure = sanitize_text_field( get_query_var( 'filter_procedure', '' ) );
		$procedure_title  = sanitize_text_field( get_query_var( 'procedure_title', '' ) );

		// Determine procedure slug.
		$procedure_slug = '';

		// First priority: use procedure context passed from AJAX filter.
		if ( ! empty( $procedure_context ) ) {
			$procedure_slug = sanitize_title( $procedure_context );
		} elseif ( ! empty( $filter_procedure ) ) {
			$procedure_slug = $filter_procedure;
		} elseif ( ! empty( $procedure_title ) ) {
			$procedure_slug = $procedure_title;
		} else {
			// Parse current URL to get procedure slug.
			$procedure_slug = self::extract_procedure_from_url( $case );
		}

		// Build the URL.
		$gallery_slug = self::get_gallery_page_slug();
		$base_url     = home_url( '/' . $gallery_slug );

		return sprintf(
			'%s/%s/%s/',
			$base_url,
			$procedure_slug,
			$case_info['url_suffix']
		);
	}

	/**
	 * Extract procedure slug from URL with intelligent fallback strategies
	 *
	 * Extracts procedure identifier from the current request URL using
	 * sophisticated pattern matching, with multiple fallback mechanisms
	 * for various URL structures and case data formats.
	 *
	 * Extraction Strategy (in order of precedence):
	 * 1. URL pattern matching: /gallery-slug/procedure-slug/case-id/
	 * 2. Case data procedures array (first procedure name)
	 * 3. Default fallback: 'case'
	 *
	 * Security Features:
	 * - Safe $_SERVER access with null coalescing
	 * - Regex pattern with proper escaping
	 * - WordPress sanitization of extracted slugs
	 * - XSS prevention through output sanitization
	 *
	 * @param array $case Complete case data array with optional procedures information.
	 *
	 * @return string Sanitized procedure slug for URL generation.
	 * @since 3.0.0
	 *
	 */
	private static function extract_procedure_from_url( array $case ): string {
		// Safe extraction of current URL with null coalescing
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $current_url ) ) {
			return self::extract_procedure_from_case_data( $case );
		}

		$current_url  = wp_unslash( $current_url );
		$gallery_slug = self::get_gallery_page_slug();

		// Build regex pattern with proper escaping
		$pattern = '/' . preg_quote( $gallery_slug, '/' ) . '/([^/]+)(?:/|$)/';

		// Extract procedure slug from URL structure
		if ( preg_match( $pattern, $current_url, $matches ) && ! empty( $matches[1] ) ) {
			return sanitize_title( $matches[1] );
		}

		// Fallback to case data extraction
		return self::extract_procedure_from_case_data( $case );
	}

	/**
	 * Extract procedure slug from case data with PHP 8.2 null coalescing
	 *
	 * Extracts procedure information directly from case data structure
	 * using PHP 8.2 null coalescing operators for safe array access.
	 *
	 * @param array $case Case data array with optional procedures.
	 *
	 * @return string Sanitized procedure slug or default 'case'.
	 * @since 3.0.0
	 *
	 */
	private static function extract_procedure_from_case_data( array $case ): string {
		// Use PHP 8.2 null coalescing for safe nested array access
		$first_procedure = $case['procedures'][0] ?? null;
		$procedure_name  = $first_procedure['name'] ?? 'case';

		return sanitize_title( $procedure_name );
	}

	/**
	 * Get case meta data with missing data logging
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array Case meta data with defaults.
	 * @since 3.0.0
	 */
	private static function get_case_meta_data( int $post_id ): array {
		// Meta field mapping: internal_key => [ meta_key, default ].
		$meta_fields = [
			'case_id'           => [ 'brag_book_gallery_case_id', '' ],
			'patient_age'       => [ 'brag_book_gallery_patient_age', '' ],
			'patient_gender'    => [ 'brag_book_gallery_patient_gender', '' ],
			'patient_ethnicity' => [ 'brag_book_gallery_ethnicity', '' ],
			'patient_height'    => [ 'brag_book_gallery_height', '' ],
			'patient_weight'    => [ 'brag_book_gallery_weight', '' ],
			'procedure_date'    => [ 'brag_book_gallery_procedure_date', '' ],
			'case_notes'        => [ 'brag_book_gallery_notes', '' ],
			'before_image'      => [ 'brag_book_gallery_case_before_url', '' ],
			'after_image'       => [ 'brag_book_gallery_case_after_url', '' ],
		];

		$case_data = [];
		foreach ( $meta_fields as $field => $meta_config ) {
			[ $meta_key, $default ] = $meta_config;

			$value = get_post_meta( $post_id, $meta_key, true );

			if ( empty( $value ) && '0' !== $value ) {
				self::log_missing_data( $post_id, $meta_key );
				$value = $default;
			}

			$case_data[ $field ] = $value;
		}

		return $case_data;
	}

	/**
	 * Get case procedures from taxonomy
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array Array of procedure data.
	 * @since 3.0.0
	 */
	private static function get_case_procedures( int $post_id ): array {
		$procedure_terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_PROCEDURES );

		if ( is_wp_error( $procedure_terms ) || empty( $procedure_terms ) ) {
			self::log_missing_data( $post_id, 'procedures_taxonomy' );

			return [];
		}

		$procedures = [];
		foreach ( $procedure_terms as $term ) {
			$procedure_meta = self::get_procedure_meta( $term->term_id );
			$procedures[]   = [
				'id'           => $procedure_meta['procedure_id'] ?: $term->term_id,
				'procedure_id' => $procedure_meta['procedure_id'] ?: $term->term_id,
				'name'         => $term->name,
				'slug'         => $term->slug,
				'seoSuffixUrl' => $term->slug,
				'nudity'       => 'true' === $procedure_meta['nudity'],
			];
		}

		return $procedures;
	}

	/**
	 * Get procedure metadata
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return array Procedure meta data with defaults.
	 * @since 3.0.0
	 */
	private static function get_procedure_meta( int $term_id ): array {
		return [
			'procedure_id' => get_term_meta( $term_id, 'procedure_id', true ) ?: '',
			'member_id'    => get_term_meta( $term_id, 'member_id', true ) ?: '',
			'nudity'       => get_term_meta( $term_id, 'nudity', true ) ?: 'false',
			'banner_image' => get_term_meta( $term_id, 'banner_image', true ) ?: '',
		];
	}

	/**
	 * Log missing data
	 *
	 * @param int $post_id The post ID.
	 * @param string $field The missing field name.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private static function log_missing_data( int $post_id, string $field ): void {
		self::$missing_data_log[] = [
			'post_id' => $post_id,
			'field'   => $field,
			'time'    => current_time( 'mysql' ),
		];

		// Log to WordPress debug log if enabled
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			brag_book_log( sprintf(
				'BRAGBook Gallery: Missing data for post %d, field: %s',
				$post_id,
				$field
			) );
		}
	}

	/**
	 * Get first URL from post-processed image URLs field
	 *
	 * Parses the semicolon-separated URLs from the post-processed image URLs
	 * textarea field and returns the first valid URL.
	 *
	 * @param int $post_id WordPress post ID.
	 *
	 * @return string First post-processed image URL or empty string.
	 */
	private static function get_first_post_processed_url( int $post_id ): string {
		$post_processed_urls = get_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', true );

		if ( empty( $post_processed_urls ) ) {
			return '';
		}

		// Split by semicolon and get first URL
		$urls      = explode( ';', $post_processed_urls );
		$first_url = trim( $urls[0] );

		// Validate URL format
		if ( ! empty( $first_url ) && filter_var( $first_url, FILTER_VALIDATE_URL ) ) {
			return $first_url;
		}

		return '';
	}

	/**
	 * Build the case-data array the card renderer expects from a case post.
	 *
	 * Shared shape used by the procedure gallery grid and by the location and
	 * provider filters so every entry point renders identical cards.
	 *
	 * @since 4.8.0
	 * @param \WP_Post $post Case post.
	 * @return array<string,mixed>
	 */
	public static function build_case_data_from_post( \WP_Post $post ): array {
		$images = get_post_meta( $post->ID, 'images', true );

		return [
			'id'         => get_post_meta( $post->ID, 'case_id', true ) ?: $post->ID,
			'post_id'    => $post->ID,
			'images'     => is_array( $images ) ? $images : [],
			'age'        => get_post_meta( $post->ID, 'age', true ) ?: '',
			'gender'     => get_post_meta( $post->ID, 'gender', true ) ?: '',
			'ethnicity'  => get_post_meta( $post->ID, 'ethnicity', true ) ?: '',
			'height'     => get_post_meta( $post->ID, 'height', true ) ?: '',
			'weight'     => get_post_meta( $post->ID, 'weight', true ) ?: '',
			'notes'      => get_post_meta( $post->ID, 'notes', true ) ?: '',
			'procedures' => array_map(
				static fn( $term ) => is_object( $term ) ? $term->name : $term,
				wp_get_post_terms( $post->ID, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES ) ?: []
			),
		];
	}

	/**
	 * Render WordPress post-based case card
	 *
	 * Renders a case card using WordPress post data with the correct HTML structure
	 * matching the API-based case cards.
	 *
	 * @param array $case_data WordPress post-based case data.
	 * @param string $image_display_mode Display mode for images.
	 * @param bool|null $procedure_nudity Whether the current procedure is flagged for nudity; null auto-detects from the case.
	 * @param string $procedure_context Context for case display.
	 * @param string $current_procedure_id Current procedure API ID, if any.
	 * @param string $current_term_id Current procedure term ID, if any.
	 * @param string $distance_label Optional distance label (e.g. "3.4 miles away") shown
	 *                               on the card; used by the location search results.
	 *
	 * @return string Generated case card HTML.
	 * @since 3.0.0
	 */
	public static function render_wordpress_case_card(
		array $case_data,
		string $image_display_mode = 'single',
		?bool $procedure_nudity = null,
		string $procedure_context = '',
		string $current_procedure_id = '',
		string $current_term_id = '',
		string $distance_label = ''
	): string {
		$case_id    = $case_data['id'] ?: '';
		$post_id    = $case_data['post_id'] ?? '';
		$images     = $case_data['images'] ?? [];
		$procedures = $case_data['procedures'] ?? [];

		// Get API procedure IDs from taxonomy terms
		$procedure_ids = [];
		$assigned_procedure_ids = []; // Track already assigned procedure IDs to avoid duplicates

		if ( $post_id ) {
			$terms = wp_get_post_terms( $post_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$api_procedure_id = get_term_meta( $term->term_id, 'procedure_id', true );
					if ( ! empty( $api_procedure_id ) ) {
						$procedure_ids[] = $api_procedure_id;
						$assigned_procedure_ids[] = $api_procedure_id;
					}
				}
			}

			// Get additional procedures from brag_book_gallery_procedure_ids meta
			$meta_procedure_ids = get_post_meta( $post_id, 'brag_book_gallery_procedure_ids', true );
			if ( ! empty( $meta_procedure_ids ) ) {
				$meta_ids = array_map( 'trim', explode( ',', $meta_procedure_ids ) );

				foreach ( $meta_ids as $meta_id ) {
					// Skip if already assigned via taxonomy
					if ( in_array( $meta_id, $assigned_procedure_ids, true ) ) {
						continue;
					}

					// Look up the term by procedure_id term meta
					$terms_query = get_terms( [
						'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
						'hide_empty' => false,
						'meta_query' => [
							[
								'key'   => 'procedure_id',
								'value' => $meta_id,
							],
						],
					] );

					if ( ! is_wp_error( $terms_query ) && ! empty( $terms_query ) ) {
						$found_term = $terms_query[0];
						// Add to procedures list
						$procedures[] = $found_term;
						$procedure_ids[] = $meta_id;
					}
				}
			}
		}

		// Get case permalink
		$case_url = $post_id ? get_permalink( $post_id ) : '#';

		// Get primary procedure name for display
		// If procedure_context is provided (e.g., on taxonomy pages), use that instead
		if ( ! empty( $procedure_context ) && 'taxonomy' !== $procedure_context ) {
			$primary_procedure = $procedure_context;
		} else {
			$primary_procedure = is_array( $procedures ) && ! empty( $procedures ) ? $procedures[0] : 'Case';
		}

		// Build data attributes
		$data_attrs = array(
			'data-card="true"'
		);
		$case_id   = get_post_meta( $post_id, 'brag_book_gallery_case_id', true );
		$gender    = get_post_meta( $post_id, 'brag_book_gallery_gender', true );
		$age       = get_post_meta( $post_id, 'brag_book_gallery_age', true );
		$ethnicity = get_post_meta( $post_id, 'brag_book_gallery_ethnicity', true );

		// Initialize current API procedure ID (will be set on taxonomy pages)
		$current_api_procedure_id = '';

		// Get procedure case ID (small junction ID) for favorites
		$procedure_case_id = get_post_meta( $post_id, 'brag_book_gallery_procedure_case_id', true );
		if ( empty( $procedure_case_id ) ) {
			$procedure_case_id = get_post_meta( $post_id, 'brag_book_gallery_original_case_id', true );
		}

		if ( ! empty( $case_id ) ) {
			$data_attrs[] = 'data-case-id="' . $case_id . '"';
		}

		if ( $post_id ) {
			$data_attrs[] = 'data-post-id="' . $post_id . '"';
		}

		if ( ! empty( $procedure_case_id ) ) {
			$data_attrs[] = 'data-procedure-case-id="' . esc_attr( $procedure_case_id ) . '"';
		}

		// On taxonomy archives the current procedure comes from the main query. In
		// other contexts (e.g. the provider-filter AJAX response, where is_tax() is
		// false) it must be supplied via $current_term_id so the card still carries
		// the procedure context that referrer-based next/previous navigation needs.
		if ( is_tax() ) {
			$current_term_id = (string) get_queried_object_id();
		}

		if ( '' !== $current_term_id ) {
			$data_attrs[] = 'data-current-term-id="' . esc_attr( $current_term_id ) . '"';

			// Get API procedure ID from current term meta
			$current_api_procedure_id = get_term_meta( (int) $current_term_id, 'procedure_id', true );
			if ( ! empty( $current_api_procedure_id ) ) {
				$data_attrs[] = 'data-current-procedure-id="' . esc_attr( $current_api_procedure_id ) . '"';
			}
		}

		if ( ! empty( $procedure_ids ) ) {
			$data_attrs[] = 'data-procedure-ids="' . esc_attr( implode( ',', $procedure_ids ) ) . '"';
		}

		// Add demographic data attributes
		if ( ! empty( $age ) ) {
			$data_attrs[] = 'data-age="' . esc_attr( $age ) . '"';
		}

		if ( ! empty( $gender ) ) {
			$data_attrs[] = 'data-gender="' . esc_attr( strtolower( $gender ) ) . '"';
		}

		if ( ! empty( $ethnicity ) ) {
			$data_attrs[] = 'data-ethnicity="' . esc_attr( strtolower( $ethnicity ) ) . '"';
		}

		// Get height and weight metadata
		$height = get_post_meta( $post_id, 'brag_book_gallery_height', true );
		$weight = get_post_meta( $post_id, 'brag_book_gallery_weight', true );

		if ( ! empty( $height ) ) {
			$data_attrs[] = 'data-height="' . esc_attr( $height ) . '"';
		}

		if ( ! empty( $weight ) ) {
			$data_attrs[] = 'data-weight="' . esc_attr( $weight ) . '"';
		}

		// Procedure-detail attributes (scoped to current procedure on taxonomy pages).
		$data_attrs = array_merge( $data_attrs, self::build_procedure_detail_attrs( (int) $post_id ) );

		// Get image URL - prioritize post-processed URLs, fallback to gallery images
		$image_url = '';
		$image_alt = '';
		if ( $post_id ) {
			// First priority: Get first post-processed image URL
			$image_url = self::get_first_post_processed_url( $post_id );

			// Fallback: Use gallery images if no post-processed URL available
			if ( empty( $image_url ) ) {
				$gallery_images = get_post_meta( $post_id, 'brag_book_gallery_images', true );

				if ( is_array( $gallery_images ) && ! empty( $gallery_images ) ) {
					$first_image_id = $gallery_images[0];
					$image_url      = wp_get_attachment_image_url( $first_image_id, 'large' );
				}
			}

			// Set alt text using standardized helper.
			$procedure_name = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
			$image_alt      = self::get_case_alt_text( $post_id, $procedure_name, $case_id );
		}

		// Responsive srcset/sizes for the grid card image, matched to the same
		// source node so small/medium variants load on smaller viewports. Empty
		// for cases synced before variants existed (falls back to plain src).
		$image_responsive_attrs = self::build_responsive_attrs( (int) $post_id, (string) $image_url, self::sizes_case_grid() );

		// Get card type setting
		$case_card_type = get_option( 'brag_book_gallery_case_card_type', 'default' );
		$case_image_carousel = get_option( 'brag_book_gallery_case_image_carousel', false );
		$carousel_nav        = 'thumbnails' === get_option( 'brag_book_gallery_case_carousel_nav', 'dots' ) ? 'thumbnails' : 'dots';
		$card_type_class = '';
		if ( 'v2' === $case_card_type ) {
			$card_type_class = ' brag-book-gallery-case-card--v2';
		} elseif ( 'v3' === $case_card_type ) {
			$card_type_class = ' brag-book-gallery-case-card--v3';
		}

		// Get high-res images for carousel if enabled
		$carousel_images = array();

		if ( $case_image_carousel && ( 'v2' === $case_card_type || 'v3' === $case_card_type ) && $post_id ) {
			// The sync writer (Post_Types::save_api_response_data) appends each photoSet's
			// high-res and post-processed URLs in iteration order, but skips empty entries
			// per field. That means the two lists are only index-aligned when every photoSet
			// has BOTH a high-res and a post-processed URL. When the API only populates
			// sideBySide.highDefinition for some photoSets, the high-res list is shorter
			// and its indexes no longer line up with the post-processed list.
			//
			// Strategy: use the high-res list only when its length matches post-processed
			// (every slot has a high-res variant). Otherwise the post-processed list is
			// the only source where slot order is reliable.
			$split_urls = static function ( $raw ): array {
				if ( ! is_string( $raw ) || '' === $raw ) {
					return array();
				}
				$parts = preg_split( '/[\r\n;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
				if ( ! is_array( $parts ) ) {
					return array();
				}
				return array_values( array_filter( array_map( 'trim', $parts ) ) );
			};

			$high_res_list       = $split_urls( get_post_meta( $post_id, 'brag_book_gallery_case_high_res_url', true ) );
			$post_processed_list = $split_urls( get_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', true ) );

			if ( ! empty( $high_res_list ) && count( $high_res_list ) === count( $post_processed_list ) ) {
				$carousel_images = $high_res_list;
			} elseif ( ! empty( $post_processed_list ) ) {
				$carousel_images = $post_processed_list;
			} elseif ( ! empty( $high_res_list ) ) {
				// No post-processed URLs at all — use whatever high-res we have.
				$carousel_images = $high_res_list;
			}
		}

		ob_start();
		?>
		<article class="brag-book-gallery-case-card<?php echo esc_attr( $card_type_class ); ?>" <?php echo implode( ' ', $data_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Data attributes are escaped individually during construction. ?> data-alt-text="<?php echo esc_attr( $image_alt ); ?>">
			<div class="brag-book-gallery-case-images single-image">
				<div class="brag-book-gallery-image-container">
						<?php if ( '' !== $distance_label ) : ?>
							<span class="brag-book-gallery-case-card__distance">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
								<?php echo esc_html( $distance_label ); ?>
							</span>
						<?php endif; ?>
						<div class="brag-book-gallery-skeleton-loader" style="display: none;"></div>
						<div class="brag-book-gallery-item-actions">
							<?php
							// Determine the favorite item ID - use procedure_case_id (junction ID) for API
							$favorite_item_id = '';
							if ( ! empty( $procedure_case_id ) ) {
								$favorite_item_id = $procedure_case_id;
							} elseif ( ! empty( $case_id ) ) {
								$favorite_item_id = $case_id;
							}
							?>
							<button class="brag-book-gallery-favorite-button"
									data-favorited="false"
									data-item-id="<?php echo esc_attr( $favorite_item_id ); ?>"
									aria-label="Add to favorites">
								<svg fill="rgba(255, 255, 255, 0.5)" stroke="white" stroke-width="2"
									 viewBox="0 0 24 24">
									<path
										d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
								</svg>
							</button>
						</div>
						<?php if ( 'v2' === $case_card_type || 'v3' === $case_card_type ) : ?>
							<!-- V2/V3: All images in single anchor, pagination outside -->
							<?php if ( ! empty( $carousel_images ) ) : ?>
								<!-- Gallery Carousel with multiple images -->
								<div class="brag-book-gallery-case-carousel">
									<a href="<?php echo esc_url( $case_url ); ?>"
									   class="brag-book-gallery-case-permalink brag-book-gallery-carousel-slides"
									   data-case-id="<?php echo esc_attr( $case_id ); ?>"
									   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>">
										<?php foreach ( $carousel_images as $index => $carousel_url ) : ?>
											<?php $carousel_responsive_attrs = self::build_responsive_attrs( (int) $post_id, (string) $carousel_url, self::sizes_case_grid() ); ?>
											<picture class="brag-book-gallery-picture" id="case-<?php echo esc_attr( $case_id ); ?>-img-<?php echo (int) $index; ?>">
												<?php
												// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by build_variant_sources().
												echo self::build_variant_sources( (int) $post_id, (string) $carousel_url );
												?>
												<img src="<?php echo esc_url( $carousel_url ); ?>"
													<?php
													// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by build_responsive_attrs().
													echo $carousel_responsive_attrs;
													?>
													 alt="<?php echo esc_attr( $image_alt ); ?><?php echo count( $carousel_images ) > 1 ? ' - Angle ' . ( (int) $index + 1 ) : ''; ?>"
													 loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
													 decoding="async"
													 data-image-type="carousel"
													 data-image-url="<?php echo esc_url( $carousel_url ); ?>"
													 onload="if(<?php echo (int) $index; ?>===0){this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';}"
													 fetchpriority="<?php echo 0 === $index ? 'high' : 'low'; ?>">
											</picture>
										<?php endforeach; ?>
									</a>
									<?php if ( count( $carousel_images ) > 1 && 'dots' === $carousel_nav ) : ?>
										<nav class="brag-book-gallery-case-carousel-pagination" role="tablist" aria-label="<?php esc_attr_e( 'Carousel image navigation', 'brag-book-gallery' ); ?>">
											<?php foreach ( $carousel_images as $index => $carousel_url ) : ?>
												<button type="button"
														class="brag-book-gallery-case-carousel-dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
														role="tab"
														aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
														aria-controls="case-<?php echo esc_attr( $case_id ); ?>-img-<?php echo (int) $index; ?>"
														aria-label="<?php
														/* translators: 1: current image number, 2: total images */
														echo esc_attr( sprintf( __( 'Show image %1$d of %2$d', 'brag-book-gallery' ), $index + 1, count( $carousel_images ) ) ); ?>"
														data-slide-index="<?php echo (int) $index; ?>"></button>
											<?php endforeach; ?>
										</nav>
									<?php endif; ?>
								</div>
								<?php
								// Set flag to skip closing anchor later
								$v3_anchor_closed = true;
								?>
							<?php elseif ( ! empty( $image_url ) ) : ?>
								<!-- Single image fallback -->
								<picture class="brag-book-gallery-picture">
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by build_variant_sources().
									echo self::build_variant_sources( (int) $post_id, (string) $image_url );
									?>
									<img src="<?php echo esc_url( $image_url ); ?>"
										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by build_responsive_attrs().
										echo $image_responsive_attrs;
										?>
										 alt="<?php echo esc_attr( $image_alt ); ?>"
										 loading="eager"
										 decoding="async"
										 data-image-type="single"
										 data-image-url="<?php echo esc_url( $image_url ); ?>"
										 onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';"
										 fetchpriority="high">
								</picture>
							<?php else : ?>
								<!-- DEBUG: No image URL found for case <?php echo esc_attr( $case_id ); ?> -->
							<?php endif; ?>
							<?php if ( 'v3' === $case_card_type && empty( $v3_anchor_closed ) ) : ?>
								</a>
							<?php endif; ?>
						<?php else : ?>
							<!-- Default: Image wrapped in anchor -->
							<a href="<?php echo esc_url( $case_url ); ?>"
							   class="brag-book-gallery-case-permalink"
							   data-case-id="<?php echo esc_attr( $case_id ); ?>"
							   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>">
								<?php
								if ( ! empty( $image_url ) ) : ?>
									<picture class="brag-book-gallery-picture">
										<?php
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by build_variant_sources().
										echo self::build_variant_sources( (int) $post_id, (string) $image_url );
										?>
										<img src="<?php echo esc_url( $image_url ); ?>"
											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by build_responsive_attrs().
											echo $image_responsive_attrs;
											?>
											 alt="<?php echo esc_attr( $image_alt ); ?>"
											 loading="eager"
											 decoding="async"
											 data-image-type="single"
											 data-image-url="<?php echo esc_url( $image_url ); ?>"
											 onload="this.closest('.brag-book-gallery-image-container').querySelector('.brag-book-gallery-skeleton-loader').style.display='none';"
											 fetchpriority="high">
									</picture>
								<?php else : ?>
									<!-- DEBUG: No image URL found for case <?php echo esc_attr( $case_id ); ?> -->
								<?php endif; ?>
							</a>
						<?php endif; ?>
						<?php
						// Add nudity warning for the configured preset.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is pre-escaped in render method.
						echo HTML_Renderer::maybe_render_nudity_warning( (int) $post_id, $procedure_nudity );
						?>
						<?php if ( 'thumbnails' === $carousel_nav && count( $carousel_images ) > 1 ) : ?>
							<!-- V2/V3 Card: thumbnail strip, above the title bar -->
							<nav class="brag-book-gallery-case-carousel-pagination brag-book-gallery-case-carousel-pagination--thumbnails"
								 role="tablist"
								 aria-label="<?php esc_attr_e( 'Carousel image navigation', 'brag-book-gallery' ); ?>">
								<button type="button"
										class="brag-book-gallery-case-carousel-arrow brag-book-gallery-case-carousel-arrow--prev"
										data-slide-step="-1"
										aria-label="<?php esc_attr_e( 'Previous image', 'brag-book-gallery' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" aria-hidden="true"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>
								</button>
								<div class="brag-book-gallery-case-carousel-thumbs">
									<?php foreach ( $carousel_images as $index => $carousel_url ) : ?>
										<button type="button"
												class="brag-book-gallery-case-carousel-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
												role="tab"
												aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
												aria-controls="case-<?php echo esc_attr( $case_id ); ?>-img-<?php echo (int) $index; ?>"
												aria-label="<?php
												/* translators: 1: current image number, 2: total images */
												echo esc_attr( sprintf( __( 'Show image %1$d of %2$d', 'brag-book-gallery' ), $index + 1, count( $carousel_images ) ) ); ?>"
												data-slide-index="<?php echo (int) $index; ?>">
											<?php
											// A thumbnail is 64px square, so it takes the small
											// rendition where the case has one and the full-size
											// image only where it does not.
											$thumb_url = self::get_variant_url_for_url( (int) $post_id, (string) $carousel_url, 'small' );
											?>
											<img src="<?php echo esc_url( $thumb_url ); ?>"
												 alt=""
												 width="64"
												 height="64"
												 loading="lazy"
												 decoding="async" />
										</button>
									<?php endforeach; ?>
								</div>
								<button type="button"
										class="brag-book-gallery-case-carousel-arrow brag-book-gallery-case-carousel-arrow--next"
										data-slide-step="1"
										aria-label="<?php esc_attr_e( 'Next image', 'brag-book-gallery' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" aria-hidden="true"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>
								</button>
							</nav>
						<?php endif; ?>
						<?php if ( 'v2' === $case_card_type || 'v3' === $case_card_type ) : ?>
							<!-- V2/V3 Card: Overlay with case name and arrow -->
							<div class="brag-book-gallery-case-card-overlay">
								<div class="brag-book-gallery-case-card-overlay-content">
									<?php
									// Check if we should show provider info instead of procedure
									$show_provider = (bool) get_option( 'brag_book_gallery_show_provider', false );
									$provider_data = null;

									if ( $show_provider && $post_id ) {
										$provider_data = self::get_provider_for_post( $post_id );
									}

									if ( $show_provider && $provider_data ) :
									?>
									<div class="brag-book-gallery-case-card-overlay-info brag-book-gallery-case-card-overlay-info--provider">
										<?php if ( ! empty( $provider_data['photo_url'] ) ) : ?>
											<img src="<?php echo esc_url( $provider_data['photo_url'] ); ?>"
												 alt="<?php echo esc_attr( $provider_data['name'] ); ?>"
												 width="48" height="48"
												 class="brag-book-gallery-case-card-overlay-provider-avatar">
										<?php else : ?>
											<div class="brag-book-gallery-case-card-overlay-provider-avatar brag-book-gallery-case-card-overlay-provider-avatar--placeholder">
												<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
													<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
												</svg>
											</div>
										<?php endif; ?>
										<span class="brag-book-gallery-case-card-overlay-title"><?php echo esc_html( $provider_data['name'] ); ?></span>
									</div>
									<?php else : ?>
									<div class="brag-book-gallery-case-card-overlay-info">
										<span class="brag-book-gallery-case-card-overlay-title">
											<?php
											$display_title = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
											echo esc_html( $display_title );
											?>
										</span>
										<span class="brag-book-gallery-case-card-overlay-case-number">Case #<?php echo esc_html( $case_id ); ?></span>
									</div>
									<?php endif; ?>
									<a href="<?php echo esc_url( $case_url ); ?>"
									   class="brag-book-gallery-case-card-overlay-button"
									   data-case-id="<?php echo esc_attr( $case_id ); ?>"
									   data-procedure-ids="<?php echo esc_attr( implode( ',', $procedure_ids ) ); ?>"
									   aria-label="View case details">
										<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
											<path d="M504-480 348-636q-11-11-11-28t11-28q11-11 28-11t28 11l184 184q6 6 8.5 13t2.5 15q0 8-2.5 15t-8.5 13L404-268q-11 11-28 11t-28-11q-11-11-11-28t11-28l156-156Z"/>
										</svg>
									</a>
								</div>
							</div>
						<?php endif; ?>
				</div>
			</div>
			<?php if ( 'v2' !== $case_card_type && 'v3' !== $case_card_type ) : ?>
				<!-- Default Card: Show details/summary -->
				<details class="brag-book-gallery-case-card-details">
					<summary class="brag-book-gallery-case-card-summary">
						<div class="brag-book-gallery-case-card-summary-info">
							<span
								class="brag-book-gallery-case-card-summary-info__name"><?php
								// Use primary procedure name (from taxonomy context) instead of post title
								// This ensures we show the correct procedure name, not "Combo Procedures"
								$display_title = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
								echo esc_html( $display_title );
								?></span>
							<span
								class="brag-book-gallery-case-card-summary-info__case-number">Case #<?php echo esc_html( $case_id ); ?></span>
						</div>
						<p class="brag-book-gallery-case-card-summary-details">
							<strong>More Details</strong>
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"
								 fill="currentColor">
								<path
									d="M444-288h72v-156h156v-72H516v-156h-72v156H288v72h156v156Zm36.28 192Q401-96 331-126t-122.5-82.5Q156-261 126-330.96t-30-149.5Q96-560 126-629.5q30-69.5 82.5-122T330.96-834q69.96-30 149.5-30t149.04 30q69.5 30 122 82.5T834-629.28q30 69.73 30 149Q864-401 834-331t-82.5 122.5Q699-156 629.28-126q-69.73 30-149 30Z"></path>
							</svg>
						</p>
					</summary>
					<div class="brag-book-gallery-case-card-details-content">
						<p class="brag-book-gallery-case-card-details-content__title">Procedures Performed:</p>
						<ul class="brag-book-gallery-case-card-procedures-list">
							<?php if ( ! empty( $procedures ) && is_array( $procedures ) ) : ?>
								<?php foreach ( $procedures as $procedure ) : ?>
									<?php
									$procedure_name = is_object( $procedure ) ? $procedure->name : $procedure;
									$procedure_url = is_object( $procedure ) ? get_term_link( $procedure ) : '#';
									if ( is_wp_error( $procedure_url ) ) {
										$procedure_url = '#';
									}
									?>
									<li class="brag-book-gallery-case-card-procedures-list__item">
										<a href="<?php echo esc_url( $procedure_url ); ?>"
										   class="brag-book-gallery-case-card-procedures-list__link"
										   aria-label="View <?php echo esc_attr( $procedure_name ); ?> cases">
											<?php echo esc_html( $procedure_name ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							<?php else : ?>
								<?php
								$procedure_name = is_object( $primary_procedure ) ? $primary_procedure->name : $primary_procedure;
								$procedure_url = is_object( $primary_procedure ) ? get_term_link( $primary_procedure ) : '#';
								if ( is_wp_error( $procedure_url ) ) {
									$procedure_url = '#';
								}
								?>
								<li class="brag-book-gallery-case-card-procedures-list__item">
									<a href="<?php echo esc_url( $procedure_url ); ?>"
									   class="brag-book-gallery-case-card-procedures-list__link"
									   aria-label="View <?php echo esc_attr( $procedure_name ); ?> cases">
										<?php echo esc_html( $procedure_name ); ?>
									</a>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				</details>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Location radius constants (miles) for location-scoped context. The pager
	 * searches the default radius first and widens to the extended radius only
	 * when the default yields nothing.
	 *
	 * @since 3.3.3
	 */
	private const LOCATION_DEFAULT_RADIUS  = 50;
	private const LOCATION_EXTENDED_RADIUS = 100;

	/**
	 * Read the active view context from an AJAX request.
	 *
	 * Consolidates the provider, procedure and location parameters the front-end
	 * widgets post so every "load more" request scopes identically.
	 *
	 * @since 3.3.3
	 * @return array<string,mixed> Context array for resolve_context_case_ids().
	 */
	private static function build_context_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by the calling AJAX handler.
		$lat = isset( $_POST['lat'] ) && is_numeric( wp_unslash( $_POST['lat'] ) ) ? (float) wp_unslash( $_POST['lat'] ) : null;
		$lng = isset( $_POST['lng'] ) && is_numeric( wp_unslash( $_POST['lng'] ) ) ? (float) wp_unslash( $_POST['lng'] ) : null;

		$context = [
			'provider_id'    => isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0,
			'provider_slug'  => isset( $_POST['provider_slug'] ) ? sanitize_title( wp_unslash( $_POST['provider_slug'] ) ) : '',
			'term_id'        => isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0,
			'procedure_slug' => isset( $_POST['procedure_name'] ) ? sanitize_title( wp_unslash( $_POST['procedure_name'] ) ) : '',
			'lat'            => $lat,
			'lng'            => $lng,
			'random_seed'    => isset( $_POST['random_seed'] ) ? absint( $_POST['random_seed'] ) : 0,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $context;
	}

	/**
	 * Resolve the full, ordered list of case post IDs for a view context.
	 *
	 * The single source of truth shared by the initial grid render, the provider
	 * and location filters, and the "load more" pager, so every page slices the
	 * same ordered list and pagination never duplicates or skips cases.
	 *
	 * Ordering: a pure single-procedure view keeps its curated case-order-list
	 * order; provider/location/all-cases views use the deterministic date-desc,
	 * de-duplicated order; an active location overrides ordering with nearest-first
	 * and drops cases outside the radius.
	 *
	 * @since 3.3.3
	 * @param array<string,mixed> $context Provider/procedure/location context.
	 * @return array{ids:int[],distances:array<int,float>,radius:int}
	 */
	public static function resolve_context_case_ids( array $context ): array {
		$provider_id    = absint( $context['provider_id'] ?? 0 );
		$provider_slug  = (string) ( $context['provider_slug'] ?? '' );
		$term_id        = absint( $context['term_id'] ?? 0 );
		$procedure_slug = (string) ( $context['procedure_slug'] ?? '' );
		$lat            = $context['lat'] ?? null;
		$lng            = $context['lng'] ?? null;

		// Resolve the provider taxonomy clause from slug (preferred) or API id.
		$provider_term_ids = [];
		if ( '' !== $provider_slug ) {
			$term = get_term_by( 'slug', $provider_slug, Taxonomies::TAXONOMY_PROVIDERS );
			if ( $term instanceof \WP_Term ) {
				$provider_term_ids = [ $term->term_id ];
			}
		} elseif ( $provider_id > 0 ) {
			$provider_term_ids = self::get_provider_term_ids( $provider_id );
		}

		// A provider was asked for but matches no term: yield no cases. Falling
		// through would leave the tax query null and widen the result to every
		// provider, which reads as "the filter did nothing" rather than "no match".
		if ( ( '' !== $provider_slug || $provider_id > 0 ) && empty( $provider_term_ids ) ) {
			return [
				'ids'       => [],
				'distances' => [],
				'radius'    => self::LOCATION_DEFAULT_RADIUS,
			];
		}

		$provider_tax_query = ! empty( $provider_term_ids ) ? self::build_provider_tax_query( $provider_term_ids ) : null;

		// Resolve the procedure term id from an explicit id or the slug.
		if ( $term_id <= 0 && '' !== $procedure_slug ) {
			$proc_term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
			if ( $proc_term instanceof \WP_Term ) {
				$term_id = $proc_term->term_id;
			}
		}

		$has_provider = ! empty( $provider_tax_query );
		$has_location = is_numeric( $lat ) && is_numeric( $lng );

		// Base ordered id list.
		if ( ! $has_provider && ! $has_location && $term_id > 0 ) {
			$ids = self::get_procedure_ordered_ids( $term_id );
		} else {
			$ids = self::get_ordered_unique_case_post_ids( $provider_tax_query, $term_id );
		}

		$distances = [];
		$radius    = self::LOCATION_DEFAULT_RADIUS;

		if ( $has_location && ! empty( $ids ) ) {
			$distances = self::distances_by_case( $ids, (float) $lat, (float) $lng );
			list( $matched_ids, $radius ) = self::filter_by_radius(
				$distances,
				self::LOCATION_DEFAULT_RADIUS,
				self::LOCATION_EXTENDED_RADIUS
			);
			$ids = $matched_ids; // Nearest-first, radius-filtered.
		}

		// A seeded shuffle keeps the order random per page load but stable across
		// that load's requests, so "load more" continues the same shuffled list
		// instead of re-drawing and repeating or skipping cases. Nearest-first
		// ordering is the point of a location search, so it is left alone.
		$random_seed = absint( $context['random_seed'] ?? 0 );
		if ( $random_seed > 0 && ! $has_location ) {
			$ids = self::shuffle_with_seed( $ids, $random_seed );
		}

		return [
			'ids'       => array_values( array_map( 'absint', $ids ) ),
			'distances' => $distances,
			'radius'    => (int) $radius,
		];
	}

	/**
	 * Shuffle case IDs deterministically from a seed.
	 *
	 * Sorting by a hash of seed and id gives the same order for the same seed
	 * without seeding the global random number generator, which would change
	 * every other random value in the request.
	 *
	 * @since 4.9.3
	 * @param int[] $ids  Case post IDs.
	 * @param int   $seed Shuffle seed.
	 * @return int[] The same IDs in seeded random order.
	 */
	private static function shuffle_with_seed( array $ids, int $seed ): array {
		$keyed = [];
		foreach ( $ids as $id ) {
			$keyed[ (int) $id ] = md5( $seed . ':' . $id );
		}

		asort( $keyed, SORT_STRING );

		return array_keys( $keyed );
	}

	/**
	 * Ordered case post IDs for a single procedure term.
	 *
	 * Honours the curated `brag_book_gallery_case_order_list` when present (the
	 * same order the initial grid uses), otherwise falls back to the shared
	 * de-duplicated date-desc order scoped to the term.
	 *
	 * @since 3.3.3
	 * @param int $term_id Procedure term id.
	 * @return int[] Ordered, published case post IDs.
	 */
	private static function get_procedure_ordered_ids( int $term_id ): array {
		if ( $term_id <= 0 ) {
			return self::get_ordered_unique_case_post_ids( null, 0 );
		}

		$order_list = get_term_meta( $term_id, 'brag_book_gallery_case_order_list', true );
		if ( is_array( $order_list ) && ! empty( $order_list ) ) {
			$ids = [];
			foreach ( $order_list as $row ) {
				if ( is_array( $row ) && ! empty( $row['wp_id'] ) ) {
					$wp_id = absint( $row['wp_id'] );
					if ( $wp_id > 0 && 'publish' === get_post_status( $wp_id ) ) {
						$ids[] = $wp_id;
					}
				}
			}
			if ( ! empty( $ids ) ) {
				return $ids;
			}
		}

		return self::get_ordered_unique_case_post_ids( null, $term_id );
	}

	/**
	 * Render one page of cases for a view context.
	 *
	 * Slices the shared ordered id list from resolve_context_case_ids() and renders
	 * the cards with the standard grid renderer (adding a distance badge when the
	 * context is a location search). Used by the initial grid render, the provider
	 * and location filters, and the load-more pager so all agree on scope + order.
	 *
	 * @since 3.3.3
	 * @param array<string,mixed> $context  Provider/procedure/location context.
	 * @param int                 $page     1-based page number.
	 * @param int                 $per_page Cases per page.
	 * @return array{html:string,total:int,has_more:bool,radius:int,loaded:int}
	 */
	public static function render_context_page( array $context, int $page, int $per_page ): array {
		$per_page = max( 1, $per_page );
		$page     = max( 1, $page );

		$resolved = self::resolve_context_case_ids( $context );
		$all_ids  = $resolved['ids'];
		$total    = count( $all_ids );
		$offset   = ( $page - 1 ) * $per_page;
		$page_ids = array_slice( $all_ids, $offset, $per_page );

		$image_display_mode = (string) get_option( 'brag_book_gallery_image_display_mode', 'single' );

		// Procedure display name + term id for the card renderer context.
		$term_id        = absint( $context['term_id'] ?? 0 );
		$procedure_slug = (string) ( $context['procedure_slug'] ?? '' );
		if ( $term_id <= 0 && '' !== $procedure_slug ) {
			$term    = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
			$term_id = $term instanceof \WP_Term ? $term->term_id : 0;
		}
		$procedure_name = '';
		if ( $term_id > 0 ) {
			$term           = get_term( $term_id, Taxonomies::TAXONOMY_PROCEDURES );
			$procedure_name = $term instanceof \WP_Term ? $term->name : '';
		}
		$term_id_attr = $term_id > 0 ? (string) $term_id : '';

		$html = '';
		foreach ( $page_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$distance_label = isset( $resolved['distances'][ $post_id ] )
				? self::format_distance( (float) $resolved['distances'][ $post_id ] )
				: '';

			$html .= self::render_wordpress_case_card(
				self::build_case_data_from_post( $post ),
				$image_display_mode,
				HTML_Renderer::case_has_nudity( (int) $post_id ),
				$procedure_name,
				'',
				$term_id_attr,
				$distance_label
			);
		}

		return [
			'html'     => $html,
			'total'    => $total,
			'has_more' => ( $offset + count( $page_ids ) ) < $total,
			'radius'   => $resolved['radius'],
			'loaded'   => $offset + count( $page_ids ),
		];
	}

	/**
	 * AJAX handler for loading more cases
	 *
	 * Handles AJAX requests to load additional cases for pagination.
	 * Returns HTML for additional case cards to be inserted into the grid.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public static function ajax_load_more_cases(): void {
		// Verify nonce for security
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );

			return;
		}

		// Page to load and page size. The context (provider / location / procedure)
		// is read from the same POST fields the filters set on the button.
		$start_page = max( 1, absint( $_POST['start_page'] ?? 2 ) );
		$per_page   = Settings_Helper::get_items_per_page();

		try {
			$context = self::build_context_from_request();
			$result  = self::render_context_page( $context, $start_page, $per_page );

			wp_send_json_success( [
				'html'        => $result['html'],
				'hasMore'     => $result['has_more'],
				'currentPage' => $start_page,
				'totalCases'  => $result['total'],
				'loadedCases' => $result['loaded'],
				'radius'      => $result['radius'],
			] );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			brag_book_log( 'BRAG book Gallery Load More Error: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => 'Failed to load more cases' ] );
		}
	}

	/**
	 * AJAX handler to get adjacent cases for a specific procedure
	 *
	 * Returns the next and previous case URLs for navigation within a procedure
	 *
	 * @return void
	 * @since 3.3.0
	 */
	public static function ajax_get_adjacent_cases(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in ajax_get_adjacent_cases AJAX handler.
		$procedure_slug  = isset( $_POST['procedure_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_slug'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$term_id         = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$current_post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$provider_slug   = isset( $_POST['provider_slug'] ) ? sanitize_title( wp_unslash( $_POST['provider_slug'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$provider_id     = isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0;

		if ( empty( $current_post_id ) ) {
			wp_send_json_error( [ 'message' => 'Missing post ID' ] );
			return;
		}

		// Verify the post exists
		$current_post = get_post( $current_post_id );
		if ( ! $current_post || $current_post->post_type !== Post_Types::POST_TYPE_CASES ) {
			wp_send_json_error( [ 'message' => 'Invalid post ID: ' . $current_post_id ] );
			return;
		}

		// Provider context: when the user arrived from a provider-filtered view,
		// navigate within the provider's cases, scoped to the current procedure
		// when a term is supplied and provider-wide otherwise. Absent provider
		// context falls through to the default procedure navigation below.
		$provider_term_ids = array();
		if ( '' !== $provider_slug ) {
			$provider_term = get_term_by( 'slug', $provider_slug, Taxonomies::TAXONOMY_PROVIDERS );
			if ( $provider_term instanceof \WP_Term ) {
				$provider_term_ids = array( $provider_term->term_id );
			}
		} elseif ( $provider_id > 0 ) {
			$provider_term_ids = self::get_provider_term_ids( $provider_id );
		}

		if ( ! empty( $provider_term_ids ) ) {
			// Scope to the current procedure when one can be resolved (term_id, or
			// the procedure slug from the source view); otherwise navigate across
			// all of the provider's cases.
			$procedure_term_id = $term_id;
			if ( $procedure_term_id <= 0 && '' !== $procedure_slug ) {
				$slug_term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
				if ( $slug_term instanceof \WP_Term ) {
					$procedure_term_id = $slug_term->term_id;
				}
			}

			$provider_case_ids = self::get_ordered_unique_case_post_ids(
				self::build_provider_tax_query( $provider_term_ids ),
				$procedure_term_id
			);

			// Provider navigation wraps, so the arrows keep cycling through that
			// provider's cases instead of dead-ending at either edge.
			wp_send_json_success( self::resolve_adjacent_urls( $provider_case_ids, $current_post_id, true ) );
			return;
		}

		// Get the procedure term - prefer term_id if provided, otherwise fallback to slug lookup
		if ( ! empty( $term_id ) ) {
			$procedure_term = get_term( $term_id, Taxonomies::TAXONOMY_PROCEDURES );
		} elseif ( ! empty( $procedure_slug ) ) {
			$procedure_term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
		} else {
			wp_send_json_error( [ 'message' => 'Missing procedure identifier' ] );
			return;
		}

		if ( ! $procedure_term || is_wp_error( $procedure_term ) ) {
			wp_send_json_error( [ 'message' => 'Invalid procedure' ] );
			return;
		}

		// Get case order list from taxonomy term meta
		$case_order_list = get_term_meta( $procedure_term->term_id, 'brag_book_gallery_case_order_list', true );

		if ( is_array( $case_order_list ) && ! empty( $case_order_list ) ) {
			// Extract WordPress IDs from case order list
			$case_ids = [];
			foreach ( $case_order_list as $case_data ) {
				if ( is_array( $case_data ) && ! empty( $case_data['wp_id'] ) ) {
					$case_ids[] = $case_data['wp_id'];
				}
			}
		} else {
			// Fallback to query if no case order list
			$cases_query = new \WP_Query( [
				'post_type'      => Post_Types::POST_TYPE_CASES,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => [
					[
						'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
						'field'    => 'term_id',
						'terms'    => $procedure_term->term_id,
					],
				],
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			] );

			$case_ids = $cases_query->posts;
		}

		$case_ids = array_map( 'intval', $case_ids );

		wp_send_json_success( self::resolve_adjacent_urls( $case_ids, $current_post_id ) );
	}

	/**
	 * Resolve the previous/next case URLs for a post within an ordered ID list.
	 *
	 * Shared by the procedure and provider navigation paths. Returns null for an
	 * edge of the list (or an unknown current case) so the caller leaves the
	 * existing link in place rather than breaking navigation.
	 *
	 * @since 4.9.2
	 * @param int[] $case_ids        Ordered case post IDs.
	 * @param int   $current_post_id The case currently being viewed.
	 * @param bool  $wrap            Whether the ends of the list join up.
	 * @return array{next: string|null, prev: string|null}
	 */
	private static function resolve_adjacent_urls( array $case_ids, int $current_post_id, bool $wrap = false ): array {
		$case_ids    = array_values( $case_ids );
		$current_key = array_search( $current_post_id, $case_ids, true );

		if ( false === $current_key ) {
			return [ 'next' => null, 'prev' => null ];
		}

		$next_key = $current_key + 1;
		$prev_key = $current_key - 1;

		// Wrapping needs at least two cases; with one, both links would point at
		// the case being viewed.
		if ( $wrap && count( $case_ids ) > 1 ) {
			$next_key = $next_key % count( $case_ids );
			$prev_key = ( $prev_key + count( $case_ids ) ) % count( $case_ids );
		}

		$to_url = static function ( ?int $post_id ): ?string {
			if ( empty( $post_id ) ) {
				return null;
			}

			$url = get_permalink( $post_id );
			if ( ! $url ) {
				return null;
			}

			// Force an absolute URL even when a plugin filters permalinks to be relative.
			if ( ! preg_match( '/^https?:\/\//', $url ) ) {
				$url = home_url( wp_make_link_relative( $url ) );
			}

			return $url;
		};

		return [
			'next' => $to_url( $case_ids[ $next_key ] ?? null ),
			'prev' => $to_url( $case_ids[ $prev_key ] ?? null ),
		];
	}

	/**
	 * Clear cases cache
	 *
	 * Clears all cached cases data.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	public static function clear_cache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Check if providers taxonomy is enabled
	 *
	 * The providers taxonomy is enabled via the Enable Providers setting.
	 *
	 * @return bool True if providers taxonomy should be enabled.
	 * @since 3.3.3
	 */
	private static function is_providers_taxonomy_enabled(): bool {
		return (bool) get_option( 'brag_book_gallery_enable_providers', false );
	}

	/**
	 * Get provider information from taxonomy for a post
	 *
	 * Retrieves the provider term and its metadata for a given case post.
	 *
	 * @param int $post_id The case post ID.
	 * @return array|null Provider data array or null if not found.
	 * @since 3.3.3
	 */
	private static function get_providers_for_post( int $post_id ): array {
		// Check if providers taxonomy is enabled
		if ( ! self::is_providers_taxonomy_enabled() ) {
			return [];
		}

		// Check if taxonomy exists
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_PROVIDERS ) ) {
			return [];
		}

		// Get the provider terms for this post
		$provider_terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_PROVIDERS );

		if ( empty( $provider_terms ) || is_wp_error( $provider_terms ) ) {
			return [];
		}

		// Order terms by the per-case provider ID order captured at sync time.
		$ordered_ids = array_filter(
			array_map(
				'absint',
				explode( ',', (string) get_post_meta( $post_id, 'brag_book_gallery_provider_ids', true ) )
			)
		);

		$by_member = [];
		foreach ( $provider_terms as $term ) {
			$member_id               = absint( get_term_meta( $term->term_id, 'provider_member_id', true ) );
			$by_member[ $member_id ] = $term;
		}

		$ordered_terms = [];
		foreach ( $ordered_ids as $member_id ) {
			if ( isset( $by_member[ $member_id ] ) ) {
				$ordered_terms[] = $by_member[ $member_id ];
				unset( $by_member[ $member_id ] );
			}
		}
		foreach ( $by_member as $term ) {
			$ordered_terms[] = $term;
		}

		$providers = [];
		foreach ( $ordered_terms as $term ) {
			$providers[] = self::build_provider_data( $term );
		}

		return $providers;
	}

	/**
	 * Get the primary provider for a case post
	 *
	 * Returns the first provider in position order, or null when none.
	 *
	 * @param int $post_id The case post ID.
	 * @return array|null Primary provider data or null if not found.
	 * @since 3.3.3
	 */
	private static function get_provider_for_post( int $post_id ): ?array {
		$providers = self::get_providers_for_post( $post_id );

		return $providers[0] ?? null;
	}

	/**
	 * Build a provider display array from a provider term
	 *
	 * Resolves the provider photo with the API image winning: the synced
	 * `provider_image_url` is used first, then a manually-uploaded attachment.
	 *
	 * @param \WP_Term $term The provider term.
	 * @return array Provider display data.
	 * @since 4.6.0
	 */
	private static function build_provider_data( \WP_Term $term ): array {
		$image_url     = get_term_meta( $term->term_id, 'provider_image_url', true );
		$profile_photo = get_term_meta( $term->term_id, 'provider_profile_photo', true );
		$profile_url   = get_term_meta( $term->term_id, 'provider_profile_url', true );

		$photo_url = '';
		if ( ! empty( $image_url ) ) {
			$photo_url = $image_url;
		} elseif ( ! empty( $profile_photo ) ) {
			$photo_url = wp_get_attachment_image_url( $profile_photo, [ 48, 48 ] ) ?: '';
		}

		return [
			'term_id'     => $term->term_id,
			'name'        => $term->name,
			'photo_url'   => $photo_url,
			'profile_url' => $profile_url ?: '',
		];
	}

	/**
	 * Render provider info HTML for case card
	 *
	 * Renders each assigned provider's photo and name (ordered by position) for
	 * display in case cards.
	 *
	 * @param int $post_id The case post ID.
	 * @param string $fallback_name Fallback name to display if no provider found.
	 * @return string HTML output for provider info.
	 * @since 3.3.3
	 */
	private static function render_provider_card_info( int $post_id, string $fallback_name = '' ): string {
		$providers = self::get_providers_for_post( $post_id );

		if ( empty( $providers ) ) {
			// Fall back to post meta if taxonomy not available
			$provider_name = get_post_meta( $post_id, 'brag_book_gallery_provider_name', true );
			if ( ! empty( $provider_name ) ) {
				return '<h3 class="case-title provider-name">' . esc_html( $provider_name ) . '</h3>';
			}
			if ( ! empty( $fallback_name ) ) {
				return '<h3 class="case-title">' . esc_html( $fallback_name ) . '</h3>';
			}
			return '';
		}

		$html = '<div class="brag-book-gallery-case-providers">';

		foreach ( $providers as $provider ) {
			$html .= '<div class="brag-book-gallery-case-provider">';

			// Profile photo (48x48 circle)
			if ( ! empty( $provider['photo_url'] ) ) {
				$html .= sprintf(
					'<img src="%s" alt="%s" width="48" height="48" class="brag-book-gallery-case-provider-avatar">',
					esc_url( $provider['photo_url'] ),
					esc_attr( $provider['name'] )
				);
			} else {
				// Placeholder avatar
				$html .= '<div class="brag-book-gallery-case-provider-avatar brag-book-gallery-case-provider-avatar--placeholder">'
					. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">'
					. '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>'
					. '</svg>'
					. '</div>';
			}

			// Provider name
			$html .= '<span class="brag-book-gallery-case-provider-name">' . esc_html( $provider['name'] ) . '</span>';

			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}
}
