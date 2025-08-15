<?php
/**
 * Shortcodes handler for BRAGBookGallery plugin.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\REST\Endpoints;
use BRAGBookGallery\Includes\Core\Setup;
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
	 * @since 3.0.0
	 * @return void
	 */
	public static function register(): void {
		$hooks = [
			'init' => [
				[ 'custom_rewrite_rules', 5 ],
				[ 'custom_rewrite_flush', 10 ],
				[ 'register_query_vars', 10 ],
			],
		];

		$filters = [
			'query_vars' => [ 'add_query_vars', 10, 1 ],
		];

		// Register hooks using PHP 8.2 syntax
		foreach ( $hooks as $hook => $callbacks ) {
			foreach ( $callbacks as [ $method, $priority ] ) {
				add_action(
					$hook,
					[ __CLASS__, $method ],
					$priority
				);
			}
		}

		// Register filters
		foreach ( $filters as $filter => [ $method, $priority, $accepted_args ] ) {
			add_filter(
				$filter,
				[ __CLASS__, $method ],
				$priority,
				$accepted_args
			);
		}

		// Register AJAX handlers
		add_action( 'wp_ajax_brag_book_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_filtered_gallery', [ __CLASS__, 'ajax_load_filtered_gallery' ] );

		// Register case details AJAX handler
		add_action( 'wp_ajax_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );
		add_action( 'wp_ajax_nopriv_brag_book_gallery_load_case', [ __CLASS__, 'ajax_load_case_details' ] );

		add_action( 'wp_ajax_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );
		add_action( 'wp_ajax_nopriv_load_case_details', [ __CLASS__, 'ajax_simple_case_handler' ] );

		// Register load more cases AJAX handler
		add_action( 'wp_ajax_brag_book_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_more_cases', [ __CLASS__, 'ajax_load_more_cases' ] );

		// Clear cache handler (admin only)
		add_action( 'wp_ajax_brag_book_gallery_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );

		// Load filtered cases handler
		add_action( 'wp_ajax_brag_book_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );
		add_action( 'wp_ajax_nopriv_brag_book_load_filtered_cases', [ __CLASS__, 'ajax_load_filtered_cases' ] );

		// Add admin notice for rewrite rules debugging
		add_action( 'admin_notices', [ __CLASS__, 'rewrite_debug_notice' ] );
		add_action( 'wp_ajax_bragbook_flush_rewrite', [ __CLASS__, 'ajax_flush_rewrite_rules' ] );

		// Register shortcodes
		$shortcodes = [
			'brag_book_gallery' => 'main_gallery_shortcode',
			'brag_book_carousel' => 'carousel_shortcode',
			'brag_book_gallery_cases' => 'cases_shortcode',
			'brag_book_gallery_case' => 'case_details_shortcode',
		];

		foreach ( $shortcodes as $tag => $callback ) {
			add_shortcode(
				$tag,
				[ __CLASS__, $callback ]
			);
		}
	}

	/**
	 * Register custom query vars with WordPress.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function register_query_vars(): void {
		global $wp;

		$query_vars = [ 'procedure_title', 'case_id', 'favorites_section', 'filter_category', 'filter_procedure' ];

		foreach ( $query_vars as $var ) {
			$wp->add_query_var( $var );
		}
	}

	/**
	 * Add custom query vars to WordPress.
	 *
	 * @since 3.0.0
	 * @param array<string> $vars Existing query vars.
	 * @return array<string> Modified query vars.
	 */
	public static function add_query_vars( array $vars ): array {
		return [
			...$vars,
			'procedure_title',
			'case_id',
			'favorites_section',
			'filter_category',
			'filter_procedure',
		];
	}

	/**
	 * Limit words in a text string.
	 *
	 * @since 3.0.0
	 * @param mixed $text The text to limit.
	 * @param int   $word_limit Maximum number of words.
	 * @return string Limited text.
	 */
	public static function limit_words( mixed $text, int $word_limit = self::DEFAULT_WORD_LIMIT ): string {
		// Convert to string and sanitize
		$text = match ( true ) {
			is_string( $text ) => trim( $text ),
			is_numeric( $text ) => (string) $text,
			is_object( $text ) && method_exists( $text, '__toString' ) => (string) $text,
			default => '',
		};

		// Return empty string if no valid text
		if ( empty( $text ) ) {
			return '';
		}

		// Split words and limit count
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $words ) || count( $words ) <= $word_limit ) {
			return $text;
		}

		return implode( ' ', array_slice( $words, 0, $word_limit ) );
	}

	/**
	 * Define custom rewrite rules for gallery pages.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function custom_rewrite_rules(): void {
		// First, auto-detect pages with the [brag_book_gallery] shortcode
		$pages_with_shortcode = self::find_pages_with_gallery_shortcode();
		$processed_slugs = [];

		// Add rewrite rules for each detected page
		foreach ( $pages_with_shortcode as $page ) {
			if ( ! empty( $page->post_name ) && ! in_array( $page->post_name, $processed_slugs ) ) {
				self::add_gallery_rewrite_rules( $page->post_name );
				$processed_slugs[] = $page->post_name;
			}
		}

		// Check for combine_gallery_slug option (used in settings)
		$combine_gallery_slug = get_option( 'combine_gallery_slug' );
		if ( ! empty( $combine_gallery_slug ) && ! in_array( $combine_gallery_slug, $processed_slugs ) ) {
			self::add_gallery_rewrite_rules( $combine_gallery_slug );
			$processed_slugs[] = $combine_gallery_slug;
		}

		// Also check saved options as fallback (legacy support)
		$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug' ) ?: [];

		// Ensure we have an array
		if ( ! is_array( $gallery_slugs ) ) {
			// Fallback to stored pages method
			$stored_pages = get_option( 'brag_book_gallery_stored_pages' ) ?: [];

			if ( ! is_array( $stored_pages ) ) {
				// If no stored configuration and no pages found with shortcode, we're done
				if ( empty( $pages_with_shortcode ) ) {
					return;
				}
			} else {
				// Process each stored page
				foreach ( $stored_pages as $page_path ) {
					$page_slug = self::get_page_slug_from_path( $page_path );

					if ( ! empty( $page_slug ) && ! in_array( $page_slug, $processed_slugs ) ) {
						self::add_gallery_rewrite_rules( $page_slug );
						$processed_slugs[] = $page_slug;
					}
				}
			}
		} else {
			// Process gallery slugs directly
			foreach ( $gallery_slugs as $page_slug ) {
				if ( ! empty( $page_slug ) && is_string( $page_slug ) && ! in_array( $page_slug, $processed_slugs ) ) {
					self::add_gallery_rewrite_rules( $page_slug );
					$processed_slugs[] = $page_slug;
				}
			}
		}

		// Add combined gallery rules
		self::add_combined_gallery_rewrite_rules();
	}

	/**
	 * Find pages containing the gallery shortcode.
	 *
	 * @since 3.0.0
	 * @return array Array of page objects.
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
	 * @since 3.0.0
	 * @param string $page_path The page path.
	 * @return string Page slug or empty string if not found.
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

	/**
	 * Add rewrite rules for a specific gallery page.
	 *
	 * @since 3.0.0
	 * @param string $page_slug The page slug.
	 * @return void
	 */
	private static function add_gallery_rewrite_rules( string $page_slug ): void {
		// Try to find the page by slug
		$page = get_page_by_path( $page_slug );
		
		if ( $page && $page->post_status === 'publish' ) {
			// Use page_id for existing pages to avoid conflicts
			$base_query = sprintf( 'index.php?page_id=%d', $page->ID );
		} else {
			// Check if this matches combine_gallery_slug and has a page_id set
			$combine_gallery_slug = get_option( 'combine_gallery_slug' );
			$combine_gallery_page_id = get_option( 'combine_gallery_page_id' );
			
			if ( $page_slug === $combine_gallery_slug && ! empty( $combine_gallery_page_id ) ) {
				$base_query = sprintf( 'index.php?page_id=%d', absint( $combine_gallery_page_id ) );
			} else {
				// Fallback to pagename (this might cause 404s if page doesn't exist)
				$base_query = sprintf( 'index.php?pagename=%s', $page_slug );
			}
		}

		$rewrite_rules = [
			// Case detail: /gallery/procedure-name/case-id (numeric)
			// This must come BEFORE the procedure page rule to match properly
			[
				'regex' => "^{$page_slug}/([^/]+)/([0-9]+)/?$",
				'query' => "{$base_query}&procedure_title=\$matches[1]&case_id=\$matches[2]",
			],
			// Procedure page: /gallery/procedure-name
			[
				'regex' => "^{$page_slug}/([^/]+)/?$",
				'query' => "{$base_query}&filter_procedure=\$matches[1]",
			],
		];

		foreach ( $rewrite_rules as $rule ) {
			add_rewrite_rule(
				$rule['regex'],
				$rule['query'],
				'top'
			);
		}
	}

	/**
	 * Add rewrite rules for combined gallery pages.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function add_combined_gallery_rewrite_rules(): void {
		$combine_gallery_page_id = get_option( 'combine_gallery_page_id' );

		if ( empty( $combine_gallery_page_id ) ) {
			return;
		}

		$page_slug = self::get_page_slug_from_id( absint( $combine_gallery_page_id ) );

		if ( ! empty( $page_slug ) ) {
			self::add_gallery_rewrite_rules( $page_slug );
		}
	}

	/**
	 * Get page slug from page ID.
	 *
	 * @since 3.0.0
	 * @param int $page_id The page ID.
	 * @return string Page slug or empty string if not found.
	 */
	private static function get_page_slug_from_id( int $page_id ): string {
		$post = get_post( $page_id );

		return ( $post instanceof WP_Post && isset( $post->post_name ) )
			? $post->post_name
			: '';
	}

	/**
	 * Flush rewrite rules with custom rules applied.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function custom_rewrite_flush(): void {
		// Ensure the custom rewrite rules are registered
		self::custom_rewrite_rules();

		// Only flush when explicitly needed (avoids performance issues)
		if ( get_option( 'brag_book_gallery_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'brag_book_gallery_flush_rewrite_rules' );
		}
	}

	/**
	 * Search for a procedure in the sidebar data.
	 *
	 * @since 3.0.0
	 * @param array  $data The data to search through.
	 * @param string $search_term The term to search for.
	 * @return array|null The found procedure or null if not found.
	 */
	public static function searchData( array $data, string $search_term ): ?array {
		if ( empty( $search_term ) || empty( $data ) ) {
			return null;
		}

		$search_term_lower = strtolower( trim( $search_term ) );

		foreach ( $data as $entry ) {
			// Skip invalid entries
			if ( $entry === true || ! is_array( $entry ) ) {
				continue;
			}

			$found_procedure = self::search_in_categories( $entry, $search_term_lower );
			if ( $found_procedure !== null ) {
				return $found_procedure;
			}
		}

		return null;
	}

	/**
	 * Search for a procedure within categories.
	 *
	 * @since 3.0.0
	 * @param array  $categories The categories to search.
	 * @param string $search_term_lower The lowercase search term.
	 * @return array|null The found procedure or null if not found.
	 */
	private static function search_in_categories( array $categories, string $search_term_lower ): ?array {
		foreach ( $categories as $category ) {
			if ( ! isset( $category['procedures'] ) || ! is_array( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $procedure ) {
				if ( self::procedure_matches_search( $procedure, $search_term_lower ) ) {
					return $procedure;
				}
			}
		}

		return null;
	}

	/**
	 * Check if a procedure matches the search term.
	 *
	 * @since 3.0.0
	 * @param array  $procedure The procedure data.
	 * @param string $search_term_lower The lowercase search term.
	 * @return bool True if procedure matches, false otherwise.
	 */
	private static function procedure_matches_search( array $procedure, string $search_term_lower ): bool {
		$procedure_name = strtolower( $procedure['name'] ?? '' );
		$procedure_slug = strtolower( $procedure['slugName'] ?? '' );

		return $procedure_name === $search_term_lower || $procedure_slug === $search_term_lower;
	}

	/**
	 * Get API configuration for a specific website property.
	 *
	 * @since 3.0.0
	 * @param string $website_property_id The website property ID.
	 * @return array{token: string, slug: string, sidebar_data: array} Configuration array.
	 */
	private static function get_api_configuration( string $website_property_id ): array {
		$default_config = [
			'token'        => '',
			'slug'         => '',
			'sidebar_data' => [],
		];

		if ( empty( $website_property_id ) ) {
			return $default_config;
		}

		// Get configuration options with null coalescing
		$api_tokens = get_option( 'brag_book_gallery_api_token' ) ?: [];
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
		$gallery_slugs = get_option( 'bb_gallery_page_slug' ) ?: [];

		// Ensure all are arrays
		if ( ! is_array( $api_tokens ) || ! is_array( $website_property_ids ) || ! is_array( $gallery_slugs ) ) {
			return $default_config;
		}

		// Find matching configuration
		foreach ( $api_tokens as $index => $api_token ) {
			$current_id = $website_property_ids[ $index ] ?? '';
			$page_slug = $gallery_slugs[ $index ] ?? '';

			if ( $current_id !== $website_property_id || empty( $api_token ) ) {
				continue;
			}

			// Get sidebar data
			$sidebar_data = [];
			try {
				$endpoints = new Endpoints();
				$response = $endpoints->get_api_sidebar( $api_token );

				if ( ! empty( $response ) ) {
					$sidebar_data = json_decode( $response, true, 512, JSON_THROW_ON_ERROR ) ?: [];
				}
			} catch ( \JsonException $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAG Book Gallery: Failed to decode API configuration JSON - ' . $e->getMessage() );
				}
			}

			return [
				'token'        => sanitize_text_field( $api_token ),
				'slug'         => sanitize_text_field( $page_slug ),
				'sidebar_data' => $sidebar_data,
			];
		}

		return $default_config;
	}

	/**
	 * Generate filter markup from sidebar data.
	 *
	 * @since 3.0.0
	 * @param array $sidebar_data Sidebar data from API.
	 * @return string Generated HTML for filters.
	 */
	private static function generate_filters_from_sidebar( array $sidebar_data ): string {
		$html = '';

		// Check if we have valid sidebar data
		if ( empty( $sidebar_data ) || ! isset( $sidebar_data['data'] ) || ! is_array( $sidebar_data['data'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAG Book Gallery: No sidebar data, using default filters' );
			}
			return self::generate_default_filters();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BRAG Book Gallery: Generating filters for ' . count( $sidebar_data['data'] ) . ' categories' );
		}

		// Process each category from the sidebar data
		foreach ( $sidebar_data['data'] as $category_data ) {
			if ( ! isset( $category_data['name'] ) || ! isset( $category_data['procedures'] ) ) {
				continue;
			}

			$category_name = sanitize_text_field( $category_data['name'] );
			$procedures = $category_data['procedures'];
			$total_cases = absint( $category_data['totalCase'] ?? 0 );

			// Skip if no procedures
			if ( empty( $procedures ) || ! is_array( $procedures ) ) {
				continue;
			}

			// Generate category slug for data attributes
			$category_slug = sanitize_title( $category_name );

			// Build the filter group HTML using sprintf for better readability
			$html .= sprintf(
				'<div class="brag-book-gallery-filter-group" data-category="%s" data-expanded="false">',
				esc_attr( $category_slug )
			);

			$html .= sprintf(
				'<button class="brag-book-gallery-filter-header" data-category="%1$s" data-expanded="false" aria-label="%2$s">',
				esc_attr( $category_slug ),
				/* translators: %s: Category name */
				esc_attr( sprintf( __( '%s category filter', 'brag-book-gallery' ), $category_name ) )
			);

			$html .= '<div class="brag-book-gallery-filter-label">';
			$html .= sprintf(
				'<span>%s</span>',
				esc_html( $category_name )
			);
			$html .= sprintf(
				'<span class="brag-book-gallery-filter-count">(%d)</span>',
				$total_cases
			);
			$html .= '</div>';
			$html .= '<div class="brag-book-gallery-filter-toggle"></div>';
			$html .= '</button>';
			$html .= '<ul class="brag-book-gallery-filter-content" data-expanded="false">';

			// Add procedures as filter options
			foreach ( $procedures as $procedure ) {
				$procedure_name = sanitize_text_field( $procedure['name'] ?? '' );
				$procedure_slug = sanitize_title( $procedure['slugName'] ?? $procedure_name );
				$case_count = absint( $procedure['totalCase'] ?? 0 );
				$procedure_ids = $procedure['ids'] ?? array();

				// Debug: Log procedure details
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && stripos( $procedure_name, 'liposuction' ) !== false ) {
					error_log( 'Sidebar procedure debug - ' . $procedure_name . ':' );
					error_log( '  Procedure IDs: ' . print_r( $procedure_ids, true ) );
					error_log( '  Case count: ' . $case_count );
					error_log( '  Full procedure data: ' . print_r( $procedure, true ) );
				}

				// Ensure procedure IDs are properly sanitized
				if ( is_array( $procedure_ids ) ) {
					$procedure_ids = array_map( 'absint', $procedure_ids );
					$procedure_id_str = implode( ',', $procedure_ids );
				} else {
					$procedure_id_str = '';
				}

				if ( empty( $procedure_name ) ) {
					continue;
				}

					// Get current page URL and append filter path (procedure only, no category)
				$current_url = get_permalink();
				$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '';
				$filter_url = rtrim( $base_path, '/' ) . '/' . $procedure_slug;

				// Wrap in a li for semantic list
				$html .= '<li class="brag-book-gallery-filter-item">';

				// Check if procedure has nudity
				$has_nudity = ! empty( $procedure['nudity'] ) ? 'true' : 'false';

				$html .= sprintf(
					'<a href="%1$s" class="brag-book-gallery-filter-link" data-category="%2$s" data-procedure="%3$s" data-procedure-ids="%4$s" data-procedure-count="%5$d" data-nudity="%6$s">',
					esc_url( $filter_url ),
					esc_attr( $category_slug ),
					esc_attr( $procedure_slug ),
					esc_attr( $procedure_id_str ),
					$case_count,
					esc_attr( $has_nudity )
				);

				$html .= sprintf(
					'<span class="brag-book-gallery-filter-option-label">%1$s</span>',
					esc_html( $procedure_name )
				);

				$html .= sprintf(
					'<span class="brag-book-gallery-filter-count">(%d)</span>',
					$case_count
				);

				$html .= '</a>';
				$html .= '</li>';
			}

			$html .= '</ul>';
			$html .= '</div>';
		}

		// Always add the favorites filter at the end using sprintf
		$favorites_html = sprintf(
			'<div class="brag-book-gallery-filter-group" data-category="%s" data-expanded="false">',
			'favorites'
		);

		$favorites_html .= sprintf(
			'<button class="brag-book-gallery-filter-header" data-category="%1$s" data-expanded="false" aria-label="%2$s">',
			'favorites',
			esc_attr__( 'My Favorites filter', 'brag-book-gallery' )
		);

		$favorites_html .= '<div class="brag-book-gallery-filter-label">';
		$favorites_html .= sprintf(
			'<span>%s</span>',
			esc_html__( 'My Favorites', 'brag-book-gallery' )
		);
		$favorites_html .= '<span class="brag-book-gallery-filter-count" data-favorites-count>(0)</span>';
		$favorites_html .= '</div>';
		$favorites_html .= '<div class="brag-book-gallery-filter-toggle"></div>';
		$favorites_html .= '</button>';
		$favorites_html .= '<div class="brag-book-gallery-filter-content" data-expanded="false">';
		$favorites_html .= '<!-- Favorites List -->';
		$favorites_html .= '<div class="brag-book-gallery-favorites-list" id="favorites-list">';
		$favorites_html .= '<div class="brag-book-gallery-favorites-grid" id="favorites-grid">';
		$favorites_html .= '<!-- Favorites will be dynamically added here -->';
		$favorites_html .= '</div>';
		$favorites_html .= sprintf(
			'<p class="brag-book-gallery-favorites-empty" id="favorites-empty">%s</p>',
			esc_html__( 'No favorites yet. Click the heart icon on images to add.', 'brag-book-gallery' )
		);
		$favorites_html .= '</div>';
		$favorites_html .= '</div>';
		$favorites_html .= '</div>';

		$html .= $favorites_html;

		return $html;
	}

	/**
	 * Generate default filters when no sidebar data is available.
	 *
	 * @since 3.0.0
	 * @return string Generated HTML for default filters.
	 */
	private static function generate_default_filters(): string {
		$html = '';

		// Add default body filter
		$html .= sprintf(
			'<div class="brag-book-gallery-filter-group" data-category="%s" data-expanded="false">',
			'body'
		);

		$html .= sprintf(
			'<button class="brag-book-gallery-filter-header" data-category="%1$s" data-expanded="false" aria-label="%2$s">',
			'body',
			esc_attr__( 'Body category filter', 'brag-book-gallery' )
		);

		$html .= '<div class="brag-book-gallery-filter-label">';
		$html .= sprintf(
			'<span>%s</span>',
			esc_html__( 'Body', 'brag-book-gallery' )
		);
		$html .= '<span class="brag-book-gallery-filter-count">(0)</span>';
		$html .= '</div>';
		$html .= '<div class="brag-book-gallery-filter-toggle"></div>';
		$html .= '</button>';
		$html .= '<div class="brag-book-gallery-filter-content" data-expanded="false">';
		$html .= sprintf(
			'<p class="no-procedures">%s</p>',
			esc_html__( 'No procedures available', 'brag-book-gallery' )
		);
		$html .= '</div>';
		$html .= '</div>';

		// Add favorites filter
		$html .= sprintf(
			'<div class="brag-book-gallery-filter-group" data-category="%s" data-expanded="false">',
			'favorites'
		);

		$html .= sprintf(
			'<button class="brag-book-gallery-filter-header" data-category="%1$s" data-expanded="false" aria-label="%2$s">',
			'favorites',
			esc_attr__( 'My Favorites filter', 'brag-book-gallery' )
		);

		$html .= '<div class="brag-book-gallery-filter-label">';
		$html .= sprintf(
			'<span>%s</span>',
			esc_html__( 'My Favorites', 'brag-book-gallery' )
		);
		$html .= '<span class="brag-book-gallery-filter-count" data-favorites-count>(0)</span>';
		$html .= '</div>';
		$html .= '<div class="brag-book-gallery-filter-toggle"></div>';
		$html .= '</button>';
		$html .= '<div class="brag-book-gallery-filter-content" data-expanded="false">';
		$html .= '<div class="brag-book-gallery-favorites-list" id="favorites-list">';
		$html .= '<div class="brag-book-gallery-favorites-grid" id="favorites-grid"></div>';
		$html .= sprintf(
			'<p class="brag-book-gallery-favorites-empty" id="favorites-empty">%s</p>',
			esc_html__( 'No favorites yet. Click the heart icon on images to add.', 'brag-book-gallery' )
		);
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate placeholder carousel items for a gallery section.
	 *
	 * @since 3.0.0
	 * @param string $section Section identifier (e.g., 'bd' for body, 'br' for breast).
	 * @param int    $count Number of items to generate.
	 * @param bool   $include_nudity Whether to include nudity warnings on some items.
	 * @return string Generated HTML for carousel items.
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

		for ( $i = 1; $i <= $count; $i++ ) {
			$item_id = sanitize_title( "{$section}-{$i}" );
			$has_nudity = in_array( $i, $nudity_items, true );

			$html_parts[] = self::generate_single_carousel_item(
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
	 * @since 3.0.0
	 * @param string $item_id Item identifier.
	 * @param string $image_url Image URL.
	 * @param bool   $has_nudity Whether item has nudity warning.
	 * @param int    $current_item Current item number.
	 * @param int    $total_items Total number of items.
	 * @return string Generated HTML for single carousel item.
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
			$html .= self::generate_nudity_warning();
		}

		// Add image
		$html .= self::generate_carousel_image( $image_url, $has_nudity );

		// Add action buttons
		$html .= self::generate_item_actions( $item_id );

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate nudity warning HTML.
	 *
	 * @since 3.0.0
	 * @return string Generated HTML for nudity warning.
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
	 * @since 3.0.0
	 * @param string $image_url Image URL.
	 * @param bool   $has_nudity Whether image has nudity blur.
	 * @return string Generated HTML for carousel image.
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
	 * @since 3.0.0
	 * @param string $item_id Item identifier.
	 * @return string Generated HTML for action buttons.
	 */
	private static function generate_item_actions( string $item_id ): string {
		return sprintf(
			'<div class="brag-book-gallery-item-actions">
				<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="%1$s" aria-label="%2$s">
					<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
				</button>
				<button class="brag-book-gallery-share-btn" data-item-id="%1$s" aria-label="%3$s">
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
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
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
		$case_id = get_query_var( 'case_id', '' );

		// Debug: Log what query vars we're getting in main shortcode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Main Gallery Shortcode Debug:' );
			error_log( 'filter_procedure: ' . $filter_procedure );
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
		self::enqueue_gallery_assets();

		// Get sidebar data with caching
		$sidebar_data = self::get_sidebar_data( $config['api_token'] );

		// Fetch all cases for filtering (we need the complete dataset for filters)
		$all_cases_data = self::get_all_cases_for_filtering( $config['api_token'], $config['website_property_id'] );

		// Debug log the fetched data
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Fetched all cases data: ' . count( $all_cases_data['data'] ?? [] ) . ' cases' );
		}

		// Localize script data (now with all_cases_data)
		self::localize_gallery_script( $config, $sidebar_data, $all_cases_data );

		// Add inline nudity acceptance script
		self::add_nudity_acceptance_script();

		// Generate and return gallery HTML
		return self::render_gallery_html( $sidebar_data, $config, $all_cases_data, $filter_procedure, $initial_case_id );
	}

	/**
	 * Validate gallery configuration and settings.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return array Validation result with config or error.
	 */
	private static function validate_gallery_configuration( array $atts ): array {
		// Check mode first
		$current_mode = get_option( 'brag_book_gallery_mode', 'javascript' );
		if ( $current_mode !== 'javascript' ) {
			return [
				'error' => true,
				'message' => __( 'Gallery requires JavaScript mode to be enabled.', 'brag-book-gallery' ),
			];
		}

		// Get API configuration with null coalescing
		$api_tokens = get_option( 'brag_book_gallery_api_token' ) ?: [];
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
		$api_token = $api_tokens[0] ?? '';

		// Validate required configuration
		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			return [
				'error' => true,
				'message' => __( 'Please configure API settings to display the gallery.', 'brag-book-gallery' ),
			];
		}

		return [
			'error' => false,
			'config' => [
				'api_token' => sanitize_text_field( $api_token ),
				'website_property_id' => sanitize_text_field( $website_property_id ),
			],
		];
	}

	/**
	 * Enqueue gallery CSS and JavaScript assets.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function enqueue_gallery_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$plugin_path = Setup::get_plugin_path();
		$version = '3.0.0';

		// Enqueue gallery styles
		wp_enqueue_style(
			handle: 'brag-book-gallery-main',
			src: $plugin_url . 'assets/css/brag-book-gallery.css',
			deps: [],
			ver: $version
		);

		// Enqueue GSAP library
		wp_enqueue_script(
			handle: 'gsap',
			src: 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
			deps: [],
			ver: '3.12.2',
			args: true
		);

		// Get file modification time for cache busting
		$js_file = $plugin_path . 'assets/js/brag-book-gallery.js';
		$js_version = file_exists( $js_file ) ? (string) filemtime( $js_file ) : $version;

		// Enqueue main gallery script
		wp_enqueue_script(
			handle: 'brag-book-gallery-main',
			src: $plugin_url . 'assets/js/brag-book-gallery.js',
			deps: [ 'gsap' ],
			ver: $js_version,
			args: true
		);
	}

	/**
	 * Get sidebar data from API with caching.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @return array Sidebar data.
	 */
	private static function get_sidebar_data( string $api_token ): array {
		if ( empty( $api_token ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAG Book Gallery: get_sidebar_data - Empty API token' );
			}
			return [];
		}

		try {
			$endpoints = new Endpoints();
			$sidebar_response = $endpoints->get_api_sidebar( $api_token );

			if ( ! empty( $sidebar_response ) ) {
				$decoded = json_decode( $sidebar_response, true, 512, JSON_THROW_ON_ERROR );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAG Book Gallery: Sidebar data received - ' . ( isset($decoded['data']) ? count($decoded['data']) . ' categories' : 'no data key' ) );
				}

				return is_array( $decoded ) ? $decoded : [];
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAG Book Gallery: Empty sidebar response' );
				}
			}
		} catch ( \JsonException $e ) {
			// Log JSON decode error if debug is enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAG Book Gallery: Failed to decode sidebar JSON - ' . $e->getMessage() );
			}
		}

		return [];
	}

	/**
	 * Localize script with gallery configuration.
	 *
	 * @since 3.0.0
	 * @param array $config Gallery configuration.
	 * @param array $sidebar_data Sidebar data.
	 * @param array $all_cases_data All cases data for filtering.
	 * @return void
	 */
	private static function localize_gallery_script( array $config, array $sidebar_data, array $all_cases_data = [] ): void {
		$plugin_url = Setup::get_plugin_url();

		wp_localize_script(
			'brag-book-gallery-main',
			'bragBookGalleryConfig',
			[
				'apiToken' => $config['api_token'],
				'websitePropertyId' => $config['website_property_id'],
				'apiEndpoint' => get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'brag_book_gallery_nonce' ),
				'pluginUrl' => $plugin_url,
				'gallerySlug' => get_option( 'combine_gallery_slug', 'gallery' ),
				'enableSharing' => get_option( 'brag_book_gallery_enable_sharing', 'no' ),
				'sidebarData' => $sidebar_data,
				'completeDataset' => ! empty( $all_cases_data['data'] ) ? array_map( function( $case ) {
					return [
						'id' => $case['id'] ?? '',
						'age' => $case['age'] ?? $case['patientAge'] ?? '',
						'gender' => $case['gender'] ?? $case['patientGender'] ?? '',
						'ethnicity' => $case['ethnicity'] ?? $case['patientEthnicity'] ?? '',
						'height' => $case['height'] ?? $case['patientHeight'] ?? '',
						'weight' => $case['weight'] ?? $case['patientWeight'] ?? '',
					];
				}, $all_cases_data['data'] ) : [],
			]
		);
	}

	/**
	 * Add inline script for nudity acceptance.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function add_nudity_acceptance_script(): void {
		$inline_script = '
			(function() {
				"use strict";
				try {
					if (localStorage.getItem("brag-book-nudity-accepted") === "true") {
						document.documentElement.classList.add("nudity-accepted-preload");
						const style = document.createElement("style");
						style.textContent = ".brag-book-gallery-nudity-warning { display: none !important; } .brag-book-gallery-nudity-blur { filter: none !important; }";
						document.head.appendChild(style);
					}
				} catch(e) {
					// Silently fail if localStorage is not available
				}
			})();
		';

		wp_add_inline_script( 'brag-book-gallery-main', trim( $inline_script ), 'before' );
	}

	/**
	 * Render the main gallery HTML.
	 *
	 * @since 3.0.0
	 * @param array $sidebar_data Sidebar data.
	 * @param array $config Gallery configuration.
	 * @param array $all_cases_data All cases data for filtering.
	 * @param string $initial_procedure Initial procedure filter from URL.
	 * @return string Gallery HTML.
	 */
	private static function render_gallery_html( array $sidebar_data, array $config, array $all_cases_data = [], string $initial_procedure = '', string $initial_case_id = '' ): string {
		// Get the current page URL for the base gallery path
		$current_url = get_permalink();
		$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '/';

		ob_start();
		?>
		<!-- BRAG Book Gallery Component Start -->
		<div class="brag-book-gallery-wrapper"
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
			<a href="#gallery-content" class="brag-book-gallery-skip-link">Skip to gallery content</a>
			<!-- Mobile Gallery Navigation Bar -->
			<div class="brag-book-gallery-mobile-header" role="navigation" aria-label="Gallery mobile navigation">
				<button class="brag-book-gallery-mobile-menu-toggle"
						data-menu-open="false"
						aria-label="Open navigation menu"
						aria-expanded="false"
						aria-controls="sidebar-nav">
					<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor" aria-hidden="true">
						<path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>
					</svg>
				</button>

				<div class="brag-book-gallery-mobile-search-wrapper">
					<svg class="brag-book-gallery-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round"
							  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
					</svg>
					<input type="search"
						   class="brag-book-gallery-mobile-search-input"
						   placeholder="Search Procedures..."
						   aria-label="Search cosmetic procedures"
						   aria-describedby="mobile-search-hint"
						   autocomplete="off">
					<span id="mobile-search-hint" class="sr-only">Start typing to search for procedures</span>
					<div class="brag-book-gallery-mobile-search-dropdown" role="listbox" aria-label="Search results" aria-live="polite"></div>
				</div>
			</div>

			<div class="brag-book-gallery-mobile-overlay" data-overlay></div>

			<div class="brag-book-gallery-container">
				<div class="brag-book-gallery-sidebar" role="complementary" id="sidebar-nav" aria-label="Gallery filters">
					<div class="brag-book-gallery-sidebar-header hidden">
						<h2 class="brag-book-gallery-sidebar-title">Filters</h2>
						<button class="brag-book-gallery-sidebar-close" data-action="close-menu" aria-label="Close menu">
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
								<path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>
							</svg>
						</button>
					</div>

					<div class="brag-book-gallery-search-wrapper">
						<svg class="brag-book-gallery-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
						<div class="brag-book-gallery-search-dropdown" id="search-results" role="listbox" aria-label="Search results" aria-live="polite">
							<!-- Results will be populated here -->
						</div>
					</div>

					<div class="brag-book-gallery-filters" role="group" aria-label="Procedure filters">
						<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method
					echo self::generate_filters_from_sidebar( $sidebar_data );
					?>
					</div>

					<button class="brag-book-gallery-btn-consultation" data-action="request-consultation">
						Request a Consultation
					</button>
					<p class="brag-book-gallery-consultation-text">
						Ready for the next step? Contact us to request your consultation.
					</p>
				</div>

				<div class="brag-book-gallery-main-content" role="region" aria-label="Gallery content" id="gallery-content">
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
							<strong>Go ahead, browse our before & afters...</strong>visualize your possibilities
						</h2>

						<!-- Gallery carousel section -->
						<div class="brag-book-gallery-sections" id="gallery-sections">
							<div class="brag-book-gallery-section" aria-label="Gallery Carousel">
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

					<div class="brag-book-gallery-favorites-section" aria-labelledby="favorites-title">
						<div class="brag-book-gallery-favorites-header">
							<svg class="brag-book-gallery-favorites-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 180">
								<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"/>
								<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"/>
								<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"/>
								<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"/>
								<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"/>
								<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6,10.9,7.1,19.3,7.1Z"/>
								<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"/>
								<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"/>
								<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"/>
								<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"/>
								<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"/>
								<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"/>
								<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"/>
								<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"/>
							</svg>
							<div class="brag-book-gallery-favorites-text">
								<p class="brag-book-gallery-favorites-description">
									<strong>Use the MyFavorites tool</strong> to help communicate your specific goals. If a result
									speaks to you, tap the heart.
								</p>
							</div>
						</div>
					</div>

					<div class="brag-book-gallery-powered-by">
						<a href="https://bragbookgallery.com/" class="brag-book-gallery-powered-by-link" target="_blank" rel="noopener noreferrer">Powered by BRAG Book</a>
					</div>
				</div>
			</div>

			<!-- Custom backdrop for dialog -->
			<div class="brag-book-gallery-dialog-backdrop" id="dialogBackdrop"></div>

			<!-- Share dropdown will be created dynamically by JavaScript -->

			<dialog class="brag-book-gallery-consultation-dialog" id="consultationDialog">
				<div class="brag-book-gallery-dialog-backdrop"></div>
				<div class="brag-book-gallery-dialog-content">
					<div class="brag-book-gallery-dialog-header">
						<h2 class="brag-book-gallery-dialog-title">Consultation Request</h2>
						<button class="brag-book-gallery-dialog-close" data-action="close-dialog" aria-label="Close dialog">
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
								<path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>
							</svg>
						</button>
					</div>
					<!-- Message container for success/error messages -->
					<div class="brag-book-gallery-form-message hidden" id="consultationMessage">
						<div class="brag-book-gallery-form-message-content"></div>
					</div>
					<form class="brag-book-gallery-consultation-form" data-form="consultation">
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label" for="name">Name *</label>
							<input type="text" class="brag-book-gallery-form-input" id="name" placeholder="Enter name" name="name" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label" for="email">Email *</label>
							<input type="email" class="brag-book-gallery-form-input" id="email" placeholder="Enter email address" name="email" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label" for="phone">Phone</label>
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
							<label class="brag-book-gallery-form-label" for="message">Message *</label>
							<textarea class="brag-book-gallery-form-textarea" id="message" name="message" required
									  placeholder="Tell us about your goals and how we can help..."></textarea>
						</div>
						<button type="submit" class="brag-book-gallery-form-submit">Submit Request</button>
					</form>
				</div>
			</dialog>

			<!-- Favorites Dialog -->
			<dialog class="brag-book-gallery-favorites-dialog" id="favoritesDialog">
				<div class="brag-book-gallery-dialog-content">
					<div class="brag-book-gallery-dialog-header">
						<svg class="brag-book-gallery-dialog-logo" viewBox="0 0 900 180">
							<path fill="#ff595c" d="M85.5,124.6l40-84.7h16.2v104.9h-12.8V60.7l-39.8,84.1h-7.2L42.2,59.7v85.1h-12.8V39.9h16.8l39.3,84.7Z"/>
							<path fill="#ff595c" d="M186.2,131.1l25-62.4h12.9l-32.6,80.1c-2.6,6.3-5.2,11.4-7.9,15.3-2.7,3.8-5.7,6.6-9.1,8.3-3.3,1.7-7.4,2.6-12.2,2.6s-3.4,0-4.9-.4c-1.5-.2-2.9-.6-4.2-.9v-10.6c1.3.2,2.7.4,4.2.6,1.4.2,2.9.3,4.5.3,3.9,0,7.2-1.3,9.8-3.9,2.6-2.6,5.3-7.2,8.1-13.9l-32.4-77.3h13.4l25.4,62.4v-.2Z"/>
							<path fill="#121827" d="M303.1,39.9v11.2h-60.4v35.6h55.2v11.2h-55.2v46.9h-12.8V39.9h73.2,0Z"/>
							<path fill="#121827" d="M344.1,67.2c11.6,0,20.2,2.9,25.9,8.7,5.7,5.8,8.5,14.9,8.5,27.4v41.5h-7.9l-2.4-23.7c-2.7,7.8-7.2,13.9-13.7,18.4-6.4,4.5-14,6.8-22.8,6.8s-9.2-.9-12.8-2.8c-3.6-1.9-6.5-4.4-8.5-7.5s-3-6.5-3-10,1.3-8.7,3.9-12.5,6.7-7.1,12.4-9.9c5.7-2.8,13-4.7,22.1-5.8l20-2.5c-.8-6.2-2.9-10.7-6.4-13.4s-8.6-4-15.2-4-12.3,1.4-15.7,4.3c-3.3,2.9-5.6,6.8-6.8,11.8h-12.6c1.1-7.8,4.5-14.2,10.2-19.3,5.8-5.1,14-7.6,24.9-7.6h-.1ZM335,135.5c5.8,0,11.1-1.4,15.8-4.2,4.7-2.8,8.4-6.5,11.2-11.2,2.8-4.7,4.2-9.9,4.2-15.7l-15.4,1.9c-7.9,1-14,2.3-18.5,4.2-4.5,1.8-7.7,3.9-9.6,6.3-1.9,2.3-2.8,4.8-2.9,7.4,0,3.2,1.1,5.9,3.7,8.1s6.4,3.3,11.6,3.3h-.1Z"/>
							<path fill="#121827" d="M419.7,127l25-58.4h13.1l-33.4,76.2h-9.8l-33.2-76.2h13.2l25,58.4h.1Z"/>
							<path fill="#121827" d="M495.7,146.3c-7.9,0-14.7-1.6-20.4-4.7-5.8-3.1-10.2-7.5-13.3-13.3s-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.3-13.2,5.8-3.1,12.6-4.7,20.4-4.7s14.6,1.6,20.4,4.7c5.8,3.1,10.2,7.5,13.3,13.2s4.7,12.5,4.7,20.3v2.6c0,7.8-1.6,14.5-4.7,20.3-3.1,5.8-7.5,10.2-13.3,13.3s-12.6,4.7-20.4,4.7ZM495.7,135.5c8.3,0,14.8-2.4,19.3-7.1,4.5-4.8,6.8-12,6.8-21.6s-2.3-16.9-6.8-21.6c-4.5-4.8-10.9-7.1-19.3-7.1s-14.8,2.4-19.3,7.1c-4.5,4.7-6.8,11.9-6.8,21.6s2.3,16.9,6.8,21.6s10.9,7.1,19.3,7.1Z"/>
							<path fill="#121827" d="M579.5,67.2c2.2,0,4,0,5.5.4,1.5.2,2.7.5,3.7.8v12.1c-1.4-.2-2.9-.3-4.5-.4-1.6,0-3.4,0-5.5,0-7.2,0-12.8,2.6-16.8,7.8s-6,13.9-6,26.1v31h-12.2v-76.2h7.9l2.3,22.1c2.1-8.3,5.4-14.4,10-18,4.6-3.7,9.8-5.5,15.6-5.5h0Z"/>
							<path fill="#121827" d="M607.6,144.8h-12.2v-76.2h12.2v76.2Z"/>
							<path fill="#121827" d="M670,68.7v10.8h-27.2v40.5c0,5.5,1.1,9.4,3.4,11.9,2.3,2.4,5.8,3.7,10.5,3.7s5.1,0,7.2-.4c2.1-.3,4.2-.6,6.2-1v10.6c-1.6.4-3.5.7-5.5,1-2.1.3-4.7.4-7.8.4-17.4,0-26.2-8.4-26.2-25.3v-41.5h-15.7v-10.8h16l4-22.6h7.9v22.6h27.2,0Z"/>
							<path fill="#121827" d="M749.7,102.9c0,2.8-.2,5.3-.6,7.5h-62.2c.7,8.5,3.2,14.9,7.6,19,4.4,4.1,10.5,6.2,18.3,6.2s8.8-.7,11.9-2.1c3-1.4,5.4-3.3,7.1-5.5,1.7-2.3,3.1-4.8,4-7.5h12.5c-.9,4.5-2.7,8.7-5.5,12.7s-6.6,7.2-11.6,9.6c-4.9,2.4-11.2,3.6-18.8,3.6s-14.5-1.6-20.2-4.7c-5.7-3.1-10.1-7.5-13.2-13.3-3.1-5.8-4.7-12.5-4.7-20.3v-2.6c0-7.8,1.6-14.6,4.7-20.3,3.1-5.7,7.6-10.1,13.4-13.2,5.8-3.1,12.6-4.7,20.5-4.7s14.1,1.5,19.5,4.5c5.5,3,9.7,7.1,12.7,12.4,3,5.3,4.5,11.6,4.5,18.8h0ZM712.9,78c-7.6,0-13.6,1.9-18,5.6-4.4,3.7-7,9.4-7.9,17h50.3c-.6-7.5-3-13.1-7.1-16.9-4.2-3.8-9.9-5.7-17.3-5.7h0Z"/>
							<path fill="#121827" d="M753.3,119.4h12.5c1.1,5,3.4,8.9,7,11.8,3.7,2.9,9.8,4.3,18.4,4.3s10.1-.5,13.4-1.6c3.3-1.1,5.7-2.5,7.1-4.3,1.4-1.7,2.2-3.5,2.2-5.3s-.6-4.2-1.7-5.8c-1.2-1.6-3.5-2.9-7-4s-8.9-2-16-2.8c-9-1.1-16-2.5-20.9-4.5-4.9-1.9-8.3-4.3-10.1-7.2s-2.8-6.2-2.8-9.9,1.2-7.8,3.7-11.2c2.4-3.4,6.1-6.2,11.1-8.4,4.9-2.2,11.2-3.3,18.8-3.3s14.3,1.2,19.3,3.5,8.9,5.5,11.6,9.6c2.7,4,4.3,8.6,4.8,13.8h-12.5c-.9-5.1-3-9-6.3-11.9s-9-4.3-16.8-4.3-13.4,1.2-16.5,3.5c-3.2,2.3-4.7,5-4.7,7.8s.6,3.9,1.8,5.5c1.2,1.5,3.6,2.9,7.3,4,3.7,1.2,9.2,2.2,16.7,3,8.8,1,15.6,2.4,20.3,4.5,4.8,2,8,4.5,9.9,7.3,1.8,2.9,2.7,6.1,2.7,9.8s-1.3,7.7-3.8,11.2-6.3,6.4-11.5,8.5c-5.2,2.2-11.8,3.2-19.8,3.2s-15.5-1.1-20.9-3.4c-5.4-2.3-9.4-5.5-12.1-9.5-2.7-4-4.4-8.7-5-13.9h-.2Z"/>
							<path fill="#121827" d="M849.8,22.7v2.4h-6.1v20.1h-2.9v-20.1h-6.1v-2.4h15.2-.1Z"/>
							<path fill="#121827" d="M876.2,22.8v22.3h-2.9v-16.6l-7.4,16.6h-2.1l-7.4-16.7v16.7h-2.9v-22.3h3.2l8.3,18.4,8.3-18.4h3.1-.2Z"/>
							<path fill="#ff595c" d="M614.2,19c-2.4-.6-4.8-.3-6.9.9-2.2,1.2-4.1,3.1-5.6,5.2-.2.3-.4.6-.5.9-2.3-3.9-6.6-7.6-11.3-7.2-4.4.4-8.2,3.6-9.1,7.9-1.1,5,2.1,9.6,5.1,13.3,2.8,3.3,5.9,6.3,9,9.3,1.9,1.8,3.9,3.6,5.9,5.3h0c0,0,.2.1.3.1s.2,0,.3-.1c1.7-1.4,3.3-2.9,4.9-4.3,3.2-2.9,6.3-5.9,9.1-9.1,3.1-3.5,6.6-7.9,6.3-12.9-.3-4.3-3.4-8.1-7.6-9.2h0Z"/>
						</svg>
						<button class="brag-book-gallery-dialog-close" data-action="close-favorites-dialog" aria-label="Close dialog">
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
								<path d="M256-213.85 213.85-256l224-224-224-224L256-746.15l224 224 224-224L746.15-704l-224 224 224 224L704-213.85l-224-224-224 224Z"/>
							</svg>
						</button>
					</div>
					<h2 class="brag-book-gallery-dialog-title">Send My Favorites</h2>
					<p class="brag-book-gallery-dialog-subtitle">Fill out the form below and we'll send your favorited images.</p>
					<form class="brag-book-gallery-favorites-form" data-form="favorites">
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label" for="fav-name">Full Name *</label>
							<input type="text" class="brag-book-gallery-form-input" id="fav-name" placeholder="Enter full name" name="name" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label" for="fav-email">Email Address *</label>
							<input type="email" class="brag-book-gallery-form-input" id="fav-email" placeholder="Enter email address" name="email" required>
						</div>
						<div class="brag-book-gallery-form-group">
							<label class="brag-book-gallery-form-label" for="fav-phone">Phone *</label>
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
						<button type="submit" class="brag-book-gallery-form-submit">Submit</button>
					</form>
				</div>
			</dialog>
		</div>

		<?php if ( ! empty( $all_cases_data ) && isset( $all_cases_data['data'] ) ) : ?>
		<script>
			// Store complete dataset for filter generation
			window.bragBookCompleteDataset = <?php echo json_encode( array_map( function( $case ) {
				return [
					'id' => $case['id'] ?? '',
					'age' => $case['age'] ?? $case['patientAge'] ?? '',
					'gender' => $case['gender'] ?? $case['patientGender'] ?? '',
					'ethnicity' => $case['ethnicity'] ?? $case['patientEthnicity'] ?? '',
					'height' => $case['height'] ?? $case['patientHeight'] ?? '',
					'weight' => $case['weight'] ?? $case['patientWeight'] ?? '',
				];
			}, $all_cases_data['data'] ?? [] ) ); ?>;

			// Initialize procedure filters after data is available
			setTimeout(function() {
				if (typeof initializeProcedureFilters === 'function') {
					initializeProcedureFilters();
				}
			}, 100);
		</script>
		<?php endif; ?>

		<!-- BRAG Book Gallery Component End -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Render carousel shortcode using carousel endpoint.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function carousel_shortcode( array $atts ): string {
		// Parse and validate shortcode attributes
		$atts = shortcode_atts(
			[
				'api_token'        => '',
				'website_property_id' => '',
				'limit'            => self::DEFAULT_CAROUSEL_LIMIT,
				'start'            => 1,
				'procedure_id'     => '',
				'member_id'        => '',
				'show_controls'    => 'true',
				'show_pagination'  => 'true',
				'auto_play'        => 'false',
				'class'            => '',
			],
			$atts,
			'brag_book_carousel'
		);

		// Validate configuration
		$validation = self::validate_carousel_configuration( $atts );
		if ( $validation['error'] ) {
			return sprintf(
				'<p class="brag-book-carousel-error">%s</p>',
				esc_html( $validation['message'] )
			);
		}

		$config = $validation['config'];

		// Enqueue carousel assets
		self::enqueue_carousel_assets();

		// Get carousel data from API
		$carousel_data = self::get_carousel_data_from_api( $config );

		// Localize script data
		self::localize_carousel_script( $config );

		// Generate and return carousel HTML
		return self::render_carousel_html( $carousel_data, $config );
	}

	/**
	 * Validate carousel configuration and settings.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return array Validation result with config or error.
	 */
	private static function validate_carousel_configuration( array $atts ): array {
		// Get API configuration if not provided in shortcode
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens = get_option( 'brag_book_gallery_api_token' ) ?: [];
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : '';
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : '';
		}

		// Validate required fields
		if ( empty( $atts['api_token'] ) ) {
			return [
				'error' => true,
				'message' => __( 'API token is required for carousel.', 'brag-book-gallery' ),
			];
		}

		// Get procedure_id and member_id with defaults
		$procedure_id = ! empty( $atts['procedure_id'] ) ? absint( $atts['procedure_id'] ) : 6839;
		$member_id = ! empty( $atts['member_id'] ) ? absint( $atts['member_id'] ) : 129;

		return [
			'error' => false,
			'config' => [
				'api_token'           => sanitize_text_field( $atts['api_token'] ),
				'website_property_id' => sanitize_text_field( $atts['website_property_id'] ),
				'limit'               => absint( $atts['limit'] ) ?: 10,
				'start'               => absint( $atts['start'] ) ?: 1,
				'procedure_id'        => $procedure_id,
				'member_id'           => $member_id,
				'show_controls'       => filter_var( $atts['show_controls'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'show_pagination'     => filter_var( $atts['show_pagination'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'auto_play'           => filter_var( $atts['auto_play'] ?? false, FILTER_VALIDATE_BOOLEAN ),
				'class'               => sanitize_html_class( $atts['class'] ?? '' ),
			],
		];
	}

	/**
	 * Enqueue carousel-specific assets.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function enqueue_carousel_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$plugin_path = Setup::get_plugin_path();
		$version = '3.0.0';

		// Enqueue carousel styles
		wp_enqueue_style(
			handle: 'brag-book-carousel',
			src: $plugin_url . 'assets/css/brag-book-carousel.css',
			deps: [],
			ver: $version
		);

		// Enqueue GSAP library if not already loaded
		if ( ! wp_script_is( 'gsap', 'enqueued' ) ) {
			wp_enqueue_script(
				handle: 'gsap',
				src: 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
				deps: [],
				ver: '3.12.2',
				args: true
			);
		}

		// Get file modification time for cache busting
		$js_file = $plugin_path . 'assets/js/brag-book-carousel.js';
		$js_version = file_exists( $js_file ) ? (string) filemtime( $js_file ) : $version;

		// Enqueue carousel script
		wp_enqueue_script(
			handle: 'brag-book-carousel',
			src: $plugin_url . 'assets/js/brag-book-carousel.js',
			deps: [ 'gsap' ],
			ver: $js_version,
			args: true
		);
	}

	/**
	 * Get carousel data from API.
	 *
	 * @since 3.0.0
	 * @param array $config Carousel configuration.
	 * @return array Carousel data.
	 */
	private static function get_carousel_data_from_api( array $config ): array {
		if ( empty( $config['api_token'] ) ) {
			return [];
		}

		try {
			$endpoints = new Endpoints();
			$options = [
				'websitePropertyId' => $config['website_property_id'],
				'limit'            => $config['limit'],
				'start'            => $config['start'],
				'procedureId'      => $config['procedure_id'],
				'memberId'         => $config['member_id'],
			];

			$carousel_response = $endpoints->get_carousel_data( $config['api_token'], $options );

			if ( ! empty( $carousel_response ) ) {
				$decoded = json_decode( $carousel_response, true, 512, JSON_THROW_ON_ERROR );
				return is_array( $decoded ) ? $decoded : [];
			}
		} catch ( \JsonException $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAG Book Carousel: Failed to decode carousel JSON - ' . $e->getMessage() );
			}
		}

		return [];
	}

	/**
	 * Localize carousel script with configuration.
	 *
	 * @since 3.0.0
	 * @param array $config Carousel configuration.
	 * @return void
	 */
	private static function localize_carousel_script( array $config ): void {
		wp_localize_script(
			'brag-book-carousel',
			'bragBookCarouselConfig',
			[
				'apiToken'          => $config['api_token'],
				'websitePropertyId' => $config['website_property_id'],
				'apiEndpoint'       => get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ),
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'brag_book_carousel_nonce' ),
				'pluginUrl'         => Setup::get_plugin_url(),
				'showControls'      => $config['show_controls'],
				'showPagination'    => $config['show_pagination'],
				'autoPlay'          => $config['auto_play'],
				'limit'             => $config['limit'],
			]
		);
	}

	/**
	 * Render carousel HTML.
	 *
	 * @since 3.0.0
	 * @param array $carousel_data Carousel data from API.
	 * @param array $config Carousel configuration.
	 * @return string Carousel HTML.
	 */
	private static function render_carousel_html( array $carousel_data, array $config ): string {
		if ( empty( $carousel_data ) || ! isset( $carousel_data['data'] ) ) {
			return sprintf(
				'<p class="brag-book-carousel-no-data">%s</p>',
				esc_html__( 'No carousel images available.', 'brag-book-gallery' )
			);
		}

		$carousel_id = 'carousel-' . wp_rand();
		$css_class = 'brag-book-gallery-carousel-wrapper' . ( ! empty( $config['class'] ) ? ' ' . $config['class'] : '' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $css_class ); ?>" data-carousel="<?php echo esc_attr( $carousel_id ); ?>">
			<div class="brag-book-gallery-carousel-content">
				<?php if ( $config['show_controls'] ): ?>
				<button class="brag-book-gallery-carousel-btn" data-direction="prev" aria-label="<?php esc_attr_e( 'Previous slide', 'brag-book-gallery' ); ?>">
					<svg class="brag-book-gallery-arrow-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
						<path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
				<?php endif; ?>

				<div class="brag-book-gallery-carousel-track" data-carousel-track="<?php echo esc_attr( $carousel_id ); ?>" role="region" aria-label="<?php esc_attr_e( 'Image carousel', 'brag-book-gallery' ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is already escaped in method
					echo self::generate_carousel_items_from_data( $carousel_data['data'] );
					?>
				</div>

				<?php if ( $config['show_controls'] ): ?>
				<button class="brag-book-gallery-carousel-btn" data-direction="next" aria-label="<?php esc_attr_e( 'Next slide', 'brag-book-gallery' ); ?>">
					<svg class="brag-book-gallery-arrow-icon" width="24" height="24" viewBox="0 0 24 24" fill="none">
						<path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
				<?php endif; ?>
			</div>

			<?php if ( $config['show_pagination'] ): ?>
			<div class="brag-book-gallery-carousel-pagination" data-pagination="<?php echo esc_attr( $carousel_id ); ?>" role="tablist" aria-label="<?php esc_attr_e( 'Carousel pagination', 'brag-book-gallery' ); ?>"></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate carousel items from API data.
	 *
	 * @since 3.0.0
	 * @param array $items Carousel items data.
	 * @return string Generated HTML for carousel items.
	 */
	private static function generate_carousel_items_from_data( array $items ): string {
		if ( empty( $items ) ) {
			return '';
		}

		$html_parts = [];
		$slide_index = 0;

		// Loop through each case
		foreach ( $items as $case ) {
			// Loop through all photoSets in each case
			$photo_sets = $case['photoSets'] ?? [];
			foreach ( $photo_sets as $photo ) {
				$slide_index++;
				$html_parts[] = self::generate_carousel_slide_from_photo( $photo, $case, $slide_index );
			}
		}

		return implode( '', $html_parts );
	}

	/**
	 * Generate a single carousel slide from photo data.
	 *
	 * @since 3.0.0
	 * @param array $photo Photo data from photoSet.
	 * @param array $case Case data containing the photo.
	 * @param int   $slide_index Current slide index.
	 * @return string Generated HTML for single carousel slide.
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
		$case_id = $case['id'] ?? '';
		$photo_id = $photo['id'] ?? $slide_index;
		$item_id = 'slide-' . $photo_id;

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
				$base_path = ! empty( $gallery_slugs[0] ) ? '/' . $gallery_slugs[0] : '/before-after';
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
				<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="%s" aria-label="%s">
					<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
				</button>
				<button class="brag-book-gallery-share-btn" data-item-id="%s" aria-label="%s">
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
	 * Generate carousel image HTML with custom alt text.
	 *
	 * @since 3.0.0
	 * @param string $image_url Image URL.
	 * @param bool   $has_nudity Whether image has nudity blur.
	 * @param string $alt_text Custom alt text.
	 * @return string Generated HTML for carousel image.
	 */
	private static function generate_carousel_image_with_alt( string $image_url, bool $has_nudity, string $alt_text ): string {
		$img_class = $has_nudity ? ' class="brag-book-gallery-nudity-blur"' : '';

		return sprintf(
			'<picture class="brag-book-gallery-carousel-image">
				<source srcset="%1$s" type="image/jpeg">
				<img src="%1$s" alt="%2$s" loading="lazy"%3$s width="400" height="300">
			</picture>',
			esc_url( $image_url ),
			esc_attr( $alt_text ),
			$img_class
		);
	}

	/**
	 * Cases shortcode handler for displaying case listings.
	 *
	 * Displays cases from the /api/plugin/combine/cases endpoint
	 * filtered by procedure if specified in the URL.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
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
			$api_tokens = get_option( 'brag_book_gallery_api_token' ) ?: [];
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : '';
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : '';
		}

		// Get filter from URL if present
		$filter_procedure = get_query_var( 'filter_procedure', '' );
		$case_id = get_query_var( 'case_id', '' );

		// Debug: Log what query vars we're getting
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Cases Shortcode Debug:' );
			error_log( 'filter_procedure: ' . $filter_procedure );
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
			return '<p class="brag-book-cases-error">' .
				   esc_html__( 'Please configure API settings to display cases.', 'brag-book-gallery' ) .
				   '</p>';
		}

		// Get procedure IDs based on filter
		$procedure_ids = [];
		if ( ! empty( $filter_procedure ) ) {
			// Try to find matching procedure in sidebar data
			$sidebar_data = self::get_sidebar_data( $atts['api_token'] );
			if ( ! empty( $sidebar_data['data'] ) ) {
				$procedure_info = self::find_procedure_by_slug( $sidebar_data['data'], $filter_procedure );
				if ( ! empty( $procedure_info['id'] ) ) {
					$procedure_ids = [ $procedure_info['id'] ];
				}
			}
		} elseif ( ! empty( $atts['procedure_ids'] ) ) {
			$procedure_ids = array_map( 'intval', explode( ',', $atts['procedure_ids'] ) );
		}

		// When filtering by procedure, load ALL cases for that procedure to enable proper filtering
		$initial_load_size = ! empty( $filter_procedure ) ? 200 : 10; // Load all for procedure, or 10 for general

		// Get cases from API
		$cases_data = self::get_cases_from_api(
			$atts['api_token'],
			$atts['website_property_id'],
			$procedure_ids,
			$initial_load_size,
			intval( $atts['page'] )
		);

		// Render cases grid
		return self::render_cases_grid( $cases_data, $atts, $filter_procedure );
	}

	/**
	 * Find procedure by slug in sidebar data.
	 *
	 * @since 3.0.0
	 * @param array  $sidebar_data Sidebar data array.
	 * @param string $slug Procedure slug to find.
	 * @return array|null Procedure info or null if not found.
	 */
	private static function find_procedure_by_slug( array $sidebar_data, string $slug ): ?array {
		foreach ( $sidebar_data as $category ) {
			if ( ! empty( $category['procedures'] ) ) {
				foreach ( $category['procedures'] as $procedure ) {
					if ( sanitize_title( $procedure['name'] ) === $slug ) {
						return $procedure;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Get all cases for filtering purposes.
	 * Fetches ALL cases to enable proper filtering across the complete dataset.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @return array All cases data.
	 */
	private static function get_all_cases_for_filtering( string $api_token, string $website_property_id ): array {
		// Check cache first
		$cache_key = 'brag_book_all_cases_' . md5( $api_token . $website_property_id );
		$cached_data = get_transient( $cache_key );

		if ( $cached_data !== false ) {
			return $cached_data;
		}

		try {
			// Initialize API endpoints
			$endpoints = new Endpoints();

			// Prepare request body with API tokens (plural) as expected by the API
			$filter_body = [
				'apiTokens' => [ $api_token ],  // Changed to plural and array
				'websitePropertyIds' => [ intval( $website_property_id ) ],  // Changed to plural and array
				'count' => 1, // Start with page 1
			];

			$all_cases = [];
			$page = 1;
			$max_pages = 20; // Safety limit

			// Fetch all pages to get complete dataset
			while ( $page <= $max_pages ) {
				$filter_body['count'] = $page;

				$response = $endpoints->bb_get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						$all_cases = array_merge( $all_cases, $page_data['data'] );

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "Page $page fetched: " . count( $page_data['data'] ) . ' cases, total so far: ' . count( $all_cases ) );
						}

						// If we got less than 10 cases, we've reached the end
						if ( count( $page_data['data'] ) < 10 ) {
							break;
						}

						$page++;
					} else {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "Page $page returned no data or invalid format" );
						}
						break;
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "Page $page API call failed" );
					}
					break;
				}
			}

			$result = [
				'data' => $all_cases,
				'total' => count( $all_cases ),
			];

			// Only cache if we have data
			if ( count( $all_cases ) > 0 ) {
				// Cache for 5 minutes during development, 1 hour in production
				$cache_duration = defined( 'WP_DEBUG' ) && WP_DEBUG ? 300 : 3600;
				set_transient( $cache_key, $result, $cache_duration );
			} else {
				// Clear any existing empty cache
				delete_transient( $cache_key );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'No cases fetched from API - not caching empty result' );
				}
			}

			return $result;

		} catch ( \Exception $e ) {
			error_log( 'Error fetching all cases for filtering: ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Get cases from API with automatic pagination to fetch all cases.
	 *
	 * This function fetches all cases for a procedure by making multiple API calls
	 * with pagination. The API returns 10 cases per page, so we loop through
	 * all pages to get the complete dataset.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @param array  $procedure_ids Procedure IDs to filter by.
	 * @param int    $limit Number of cases to retrieve (unused, kept for compatibility).
	 * @param int    $page Page number (unused, kept for compatibility).
	 * @return array Cases data with all fetched cases.
	 */
	private static function get_cases_from_api(
		string $api_token,
		string $website_property_id,
		array $procedure_ids,
		int $initial_load_size = 10,
		int $page = 1
	): array {
		// Create cache key based on parameters
		$cache_key = 'brag_book_cases_' . md5(
			$api_token . '_' .
			$website_property_id . '_' .
			implode( '_', $procedure_ids )
		);

		// Check if we have cached data (cache for 1 hour)
		// Add ability to bypass cache with URL parameter for testing
		$bypass_cache = isset( $_GET['nocache'] ) && $_GET['nocache'] === '1';

		if ( ! $bypass_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( $cached_data !== false ) {
				error_log( 'Using cached cases data' );
				return $cached_data;
			}
		} else {
			error_log( 'Cache bypassed via nocache parameter' );
			// Delete existing cache to force refresh
			delete_transient( $cache_key );
		}

		try {
			$endpoints = new Endpoints();
			$all_cases = [];
			$total_cases = 0;
			$cases_per_page = 10; // API returns 10 cases per page
			$initial_pages = ceil( $initial_load_size / $cases_per_page ); // How many pages for initial load

			// Ensure procedure IDs are integers
			$procedure_ids_int = array_map( 'intval', $procedure_ids );

			// Prepare base filter body
			$filter_body = [
				'apiTokens'          => [ $api_token ],
				'websitePropertyIds' => [ intval( $website_property_id ) ],
				'procedureIds'       => $procedure_ids_int,
				'count'              => 1, // First page
			];

			error_log( "Fast loading strategy: Fetching first {$initial_load_size} cases ({$initial_pages} pages)" );
			error_log( 'Request body: ' . print_r( $filter_body, true ) );

			$response = $endpoints->bb_get_pagination_data( $filter_body );

			if ( empty( $response ) ) {
				error_log( 'Empty response from API' );
				return [];
			}

			$decoded = json_decode( $response, true );
			error_log( 'Initial API response structure: ' . print_r( array_keys( $decoded ?? [] ), true ) );

			if ( ! is_array( $decoded ) ) {
				error_log( 'Response is not an array' );
				return [];
			}

			// Check if we have data
			if ( empty( $decoded['data'] ) ) {
				error_log( 'No data in response' );
				return $decoded; // Return empty structure
			}

			// Store first batch of cases
			$all_cases = $decoded['data'];
			error_log( 'First batch cases count: ' . count( $all_cases ) );

			// Debug pagination structure
			if ( isset( $decoded['pagination'] ) ) {
				error_log( 'Pagination info: ' . print_r( $decoded['pagination'], true ) );
			} else {
				error_log( 'No pagination key in response. Looking for count in root level.' );
				// Check if total is at root level
				if ( isset( $decoded['total'] ) ) {
					error_log( 'Found total at root: ' . $decoded['total'] );
				}
				if ( isset( $decoded['totalCount'] ) ) {
					error_log( 'Found totalCount at root: ' . $decoded['totalCount'] );
				}
				if ( isset( $decoded['count'] ) ) {
					error_log( 'Found count at root: ' . $decoded['count'] );
				}
			}

			// Get total count from pagination info
			// The API might return total in different ways
			$total_cases = 0;
			if ( ! empty( $decoded['pagination']['total'] ) ) {
				$total_cases = intval( $decoded['pagination']['total'] );
				error_log( 'Found total in pagination.total: ' . $total_cases );
			} elseif ( ! empty( $decoded['pagination']['totalCount'] ) ) {
				$total_cases = intval( $decoded['pagination']['totalCount'] );
				error_log( 'Found total in pagination.totalCount: ' . $total_cases );
			} elseif ( ! empty( $decoded['total'] ) ) {
				$total_cases = intval( $decoded['total'] );
				error_log( 'Found total in root.total: ' . $total_cases );
			} elseif ( ! empty( $decoded['totalCount'] ) ) {
				$total_cases = intval( $decoded['totalCount'] );
				error_log( 'Found total in root.totalCount: ' . $total_cases );
			}

			error_log( "Total cases from API: {$total_cases}" );

			// Fetch initial batch quickly (up to initial_load_size cases)
			$has_more_pages = count( $all_cases ) >= $cases_per_page;
			$pages_fetched = 1;
			$more_available = false;
			$actual_total = count( $all_cases ); // Start with first page count

			// Check if we've loaded enough for the initial batch or need more
			// If we got a full page and have reached our initial load limit, check for more
			if ( $pages_fetched >= $initial_pages && count( $all_cases ) == $cases_per_page ) {
				// We've hit our initial load limit with a full page - there might be more
				$more_available = true;
				error_log( "Initial load complete with full page. Checking for additional cases..." );

				// Continue fetching just to count total (without storing all cases)
				$temp_page = $pages_fetched + 1;
				while ( true ) {
					$filter_body['count'] = $temp_page;
					$count_response = $endpoints->bb_get_pagination_data( $filter_body );
					if ( ! empty( $count_response ) ) {
						$count_data = json_decode( $count_response, true );
						if ( is_array( $count_data ) && ! empty( $count_data['data'] ) ) {
							$count_cases = count( $count_data['data'] );
							$actual_total += $count_cases;
							error_log( "Page {$temp_page} has {$count_cases} cases. Running total: {$actual_total}" );
							if ( $count_cases < $cases_per_page ) {
								break; // Found the last page
							}
							$temp_page++;
							if ( $temp_page > 20 ) break; // Safety limit
						} else {
							break;
						}
					} else {
						break;
					}
				}
				error_log( "Total cases found: {$actual_total}. Loaded first " . count( $all_cases ) . " cases." );
			} elseif ( count( $all_cases ) < $cases_per_page ) {
				// First page had less than a full page - no more cases
				$actual_total = count( $all_cases );
				error_log( "First page incomplete ({$actual_total} cases) - no more pages" );
			}

			// First, let's check how many total cases there are by fetching pages
			// Fetch up to initial_pages quickly (only if we need more than 1 page initially)
			while ( $has_more_pages && $pages_fetched < $initial_pages ) {
				$current_page = $pages_fetched + 1;
				error_log( "Fast loading page {$current_page} (initial batch)" );

				// Update the count parameter for pagination
				$filter_body['count'] = $current_page;

				$response = $endpoints->bb_get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						$new_cases_count = count( $page_data['data'] );
						error_log( "Page {$current_page} returned {$new_cases_count} cases" );

						// Merge cases from this page
						$all_cases = array_merge( $all_cases, $page_data['data'] );
						$pages_fetched++;

						// Check if there might be more pages beyond our initial load
						if ( $new_cases_count < $cases_per_page ) {
							$has_more_pages = false;
							$actual_total = count( $all_cases ); // We have all cases
							error_log( "Page {$current_page} was last page (less than {$cases_per_page} cases)" );
						} elseif ( $pages_fetched >= $initial_pages && $new_cases_count == $cases_per_page ) {
							// We've hit our initial load limit but there might be more
							$more_available = true;
							error_log( "Initial load complete. Pages fetched: {$pages_fetched}, Initial pages: {$initial_pages}, New cases: {$new_cases_count}" );
							// Start with the count we already have
							$actual_total = count( $all_cases );
							// Continue fetching just to count total (without storing all cases)
							$temp_page = $pages_fetched + 1;
							while ( true ) {
								$filter_body['count'] = $temp_page;
								$count_response = $endpoints->bb_get_pagination_data( $filter_body );
								if ( ! empty( $count_response ) ) {
									$count_data = json_decode( $count_response, true );
									if ( is_array( $count_data ) && ! empty( $count_data['data'] ) ) {
										$count_cases = count( $count_data['data'] );
										$actual_total += $count_cases;
										error_log( "Page {$temp_page} has {$count_cases} cases. Running total: {$actual_total}" );
										if ( $count_cases < $cases_per_page ) {
											break; // Found the last page
										}
										$temp_page++;
										if ( $temp_page > 20 ) break; // Safety limit
									} else {
										break;
									}
								} else {
									break;
								}
							}
							error_log( "Total cases found: {$actual_total}. Loaded first " . count( $all_cases ) . " cases." );
						}
					} else {
						$has_more_pages = false;
						error_log( "Page {$current_page} returned no data" );
					}
				} else {
					$has_more_pages = false;
					error_log( "Page {$current_page} failed to load" );
				}

				// Small delay between requests to avoid overwhelming the API
				if ( $has_more_pages && $pages_fetched < $initial_pages ) {
					usleep( 50000 ); // 50ms delay for fast loading
				}
			}

			error_log( 'Total cases fetched: ' . count( $all_cases ) );

			// Prepare the complete dataset with pagination info
			$cases_loaded = count( $all_cases );

			$result = [
				'data' => $all_cases,
				'pagination' => [
					'total' => $actual_total, // Use the actual total count
					'display_total' => $actual_total, // Show actual total not "+"
					'current_page' => 1,
					'total_pages' => 1,
					'per_page' => count( $all_cases ),
					'has_more' => $more_available,
					'last_page_loaded' => $pages_fetched,
					'cases_loaded' => $cases_loaded,
				],
				'procedure_ids' => $procedure_ids_int,
			];

			// Cache the result (1 hour default, or shorter for development)
			$cache_duration = 3600; // 1 hour default

			// Allow disabling cache for development
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$cache_duration = 60; // Only 1 minute in debug mode
			}

			// Also allow filter to customize cache duration
			$cache_duration = apply_filters( 'brag_book_gallery_cache_duration', $cache_duration );

			if ( $cache_duration > 0 ) {
				set_transient( $cache_key, $result, $cache_duration );
				error_log( "Cached cases data for {$cache_duration} seconds" );
			}

			return $result;

		} catch ( \Exception $e ) {
			error_log( 'API error while fetching cases: ' . $e->getMessage() );
		}

		return [];
	}

	/**
	 * Render cases grid HTML.
	 *
	 * @since 3.0.0
	 * @param array  $cases_data Cases data from API.
	 * @param array  $atts Shortcode attributes.
	 * @param string $filter_procedure Current procedure filter.
	 * @return string Rendered HTML.
	 */
	private static function render_cases_grid( array $cases_data, array $atts, string $filter_procedure ): string {
		$columns = intval( $atts['columns'] ) ?: 3;
		$show_details = filter_var( $atts['show_details'], FILTER_VALIDATE_BOOLEAN );
		$custom_class = sanitize_html_class( $atts['class'] );
		$include_styles = $atts['include_styles'] ?? true;

		// Get current page URL for case links
		$current_url = get_permalink();
		$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '/';

		// Get procedure info to check for nudity settings
		$procedure_nudity = false;
		if ( ! empty( $filter_procedure ) && ! empty( $atts['api_token'] ) ) {
			$sidebar_data = self::get_sidebar_data( $atts['api_token'] );
			if ( ! empty( $sidebar_data['data'] ) ) {
				$procedure_info = self::find_procedure_by_slug( $sidebar_data['data'], $filter_procedure );
				if ( ! empty( $procedure_info['nudity'] ) ) {
					$procedure_nudity = filter_var( $procedure_info['nudity'], FILTER_VALIDATE_BOOLEAN );
				}
			}
		}

		// Get total cases count and display info
		$shown_count = count( $cases_data['data'] ?? [] );
		$total_cases = ! empty( $cases_data['pagination']['total'] ) ? $cases_data['pagination']['total'] : $shown_count;
		$display_total = ! empty( $cases_data['pagination']['display_total'] ) ? $cases_data['pagination']['display_total'] : $total_cases;
		$has_more = ! empty( $cases_data['pagination']['has_more'] );

		ob_start();
		?>
		<div class="brag-book-cases-grid <?php echo esc_attr( $custom_class ); ?>"
		     data-columns="<?php echo esc_attr( $columns ); ?>">

			<?php if ( ! empty( $filter_procedure ) ) : ?>
				<div class="brag-book-cases-header">
					<h2>
						<?php echo esc_html( ucwords( str_replace( '-', ' ', $filter_procedure ) ) ); ?> Cases
						<?php if ( $shown_count > 0 ) : ?>
							<span class="cases-count">(Showing <?php echo esc_html( $shown_count ); ?> of <?php echo esc_html( $display_total ); ?>)</span>
						<?php endif; ?>
					</h2>
					<a href="<?php echo esc_url( $base_path ); ?>" class="brag-book-cases-back">
						&larr; Back to Gallery
					</a>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $cases_data['data'] ) && is_array( $cases_data['data'] ) ) : ?>
				<div class="brag-book-cases-loading-indicator">
					<div class="loading-spinner"></div>
					<p>Loading all cases...</p>
				</div>

				<div class="brag-book-cases-container" data-columns="<?php echo esc_attr( $columns ); ?>">
					<?php foreach ( $cases_data['data'] as $case ) : ?>
						<?php echo self::render_case_card( $case, $base_path, $show_details, $procedure_nudity ); ?>
					<?php endforeach; ?>
				</div>

				<?php if ( $has_more ) : ?>
					<?php
					$procedure_ids_str = ! empty( $cases_data['procedure_ids'] )
						? implode( ',', $cases_data['procedure_ids'] )
						: ( ! empty( $_GET['procedure_ids'] ) ? sanitize_text_field( $_GET['procedure_ids'] ) : '' );
					?>
					<div class="brag-book-load-more-container">
						<button class="brag-book-load-more-btn"
						        data-start-page="<?php echo esc_attr( ( $cases_data['pagination']['last_page_loaded'] ?? 5 ) + 1 ); ?>"
						        data-procedure-ids="<?php echo esc_attr( $procedure_ids_str ); ?>"
						        onclick="loadMoreCases(this)">
							Load More
						</button>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $cases_data['pagination'] ) && $cases_data['pagination']['total_pages'] > 1 ) : ?>
					<div class="brag-book-cases-pagination">
						<?php echo self::render_pagination( $cases_data['pagination'], $base_path, $filter_procedure ); ?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p class="brag-book-cases-empty">
					<?php esc_html_e( 'No cases found.', 'brag-book-gallery' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php
		// If we're on a procedure page, pass the complete dataset to JavaScript for filtering
		if ( ! empty( $filter_procedure ) && ! empty( $cases_data['data'] ) ) : ?>
			<script>
				// Initialize the complete dataset for this procedure
				window.bragBookCompleteDataset = <?php echo json_encode( $cases_data['data'] ); ?>;
				window.bragBookProcedureFilter = '<?php echo esc_js( $filter_procedure ); ?>';
				console.log('Initialized procedure dataset with', window.bragBookCompleteDataset.length, 'cases for', window.bragBookProcedureFilter);

				// Function to try initializing filters
				function tryInitializeFilters() {
					console.log('Attempting to initialize filters...');

					// Check if function exists
					if (typeof generateProcedureFilterOptions !== 'function') {
						console.log('generateProcedureFilterOptions not available yet, retrying...');
						setTimeout(tryInitializeFilters, 200);
						return;
					}

					// Check if container exists
					const container = document.getElementById('brag-book-gallery-procedure-filters-options');
					if (!container) {
						console.log('Filter container not found, retrying...');
						setTimeout(tryInitializeFilters, 200);
						return;
					}

					console.log('Calling generateProcedureFilterOptions...');
					generateProcedureFilterOptions();
				}

				// Initialize filters after DOM is ready with retry logic
				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', function() {
						setTimeout(tryInitializeFilters, 100);
					});
				} else {
					setTimeout(tryInitializeFilters, 100);
				}
			</script>
		<?php endif; ?>

		<script>
		window.loadMoreCases = function(button) {
			// Disable button and show loading state
			button.disabled = true;
			const originalText = button.textContent;
			button.textContent = 'Loading...';

			// Get data from button attributes
			const startPage = button.getAttribute('data-start-page');
			const procedureIds = button.getAttribute('data-procedure-ids');

			// Prepare AJAX data
			const formData = new FormData();
			formData.append('action', 'brag_book_load_more_cases');
			formData.append('nonce', '<?php echo wp_create_nonce( 'brag_book_gallery_nonce' ); ?>');
			formData.append('start_page', startPage);
			formData.append('procedure_ids', procedureIds);

			// Make AJAX request
			fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
				method: 'POST',
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Add new cases to the container
					const container = document.querySelector('.brag-book-cases-container');
					if (container) {
						container.insertAdjacentHTML('beforeend', data.data.html);
					}

					// Update button for next load
					if (data.data.hasMore) {
						button.setAttribute('data-start-page', parseInt(startPage) + Math.ceil(data.data.casesLoaded / 10));
						button.disabled = false;
						button.textContent = originalText;
					} else {
						// No more cases, hide the button
						button.parentElement.style.display = 'none';
					}

					// Update the count display
					const countLabel = document.querySelector('.cases-count');
					if (countLabel) {
						const currentShown = container.querySelectorAll('[data-case-id]').length;
						const match = countLabel.textContent.match(/of (\d+)/);
						if (match) {
							const total = match[1];
							countLabel.textContent = '(Showing ' + currentShown + ' of ' + total + ')';
						}
					}
				} else {
					console.error('Failed to load more cases:', data.data.message);
					button.disabled = false;
					button.textContent = originalText;
				}
			})
			.catch(error => {
				console.error('Error loading more cases:', error);
				button.disabled = false;
				button.textContent = originalText;
			});
		}
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render single case card.
	 *
	 * @since 3.0.0
	 * @param array  $case Case data.
	 * @param string $base_path Base URL path.
	 * @param bool   $show_details Whether to show case details.
	 * @return string Rendered HTML.
	 */
	private static function render_case_card( array $case, string $base_path, bool $show_details, bool $procedure_nudity = false ): string {
		// Get first photo from photoSets
		$image_url = '';
		$before_url = '';
		$after_url = '';
		if ( ! empty( $case['photoSets'][0] ) ) {
			if ( ! empty( $case['photoSets'][0]['postProcessedImageLocation'] ) ) {
				$image_url = $case['photoSets'][0]['postProcessedImageLocation'];
			}
			if ( ! empty( $case['photoSets'][0]['beforeLocationUrl'] ) ) {
				$before_url = $case['photoSets'][0]['beforeLocationUrl'];
				if ( empty( $image_url ) ) {
					$image_url = $before_url;
				}
			}
			if ( ! empty( $case['photoSets'][0]['afterLocationUrl'] ) ) {
				$after_url = $case['photoSets'][0]['afterLocationUrl'];
			}
		}

		// Get procedure name
		$procedure_name = $case['technique'] ?? 'Case';
		$procedure_slug = sanitize_title( $procedure_name );

		// Build case URL
		$case_url = rtrim( $base_path, '/' ) . '/' . $procedure_slug . '/' . $case['id'];

		// Format patient demographics
		$demographics = [];
		if ( ! empty( $case['age'] ) ) {
			$demographics[] = $case['age'] . ' years';
		}
		if ( ! empty( $case['gender'] ) ) {
			$demographics[] = ucfirst( $case['gender'] );
		}
		if ( ! empty( $case['height'] ) ) {
			$feet = floor( $case['height'] / 12 );
			$inches = $case['height'] % 12;
			$demographics[] = $feet . "'" . $inches . '"';
		}
		if ( ! empty( $case['weight'] ) ) {
			$demographics[] = $case['weight'] . ' lbs';
		}

		// Prepare data attributes for filtering
		$data_attrs = 'data-case-id="' . esc_attr( $case['id'] ) . '"';
		$data_attrs .= ' data-card="true"';

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

		// Add height
		if ( ! empty( $case['height'] ) ) {
			$data_attrs .= ' data-height="' . esc_attr( $case['height'] ) . '"';
			$data_attrs .= ' data-height-unit="inches"';
			$data_attrs .= ' data-height-full="' . esc_attr( $case['height'] . ' inches' ) . '"';
		}

		// Add weight
		if ( ! empty( $case['weight'] ) ) {
			$data_attrs .= ' data-weight="' . esc_attr( $case['weight'] ) . '"';
			$data_attrs .= ' data-weight-unit="lbs"';
			$data_attrs .= ' data-weight-full="' . esc_attr( $case['weight'] . ' lbs' ) . '"';
		}

		ob_start();
		?>
		<div class="brag-book-case-card" <?php echo $data_attrs; ?>>
			<a href="<?php echo esc_url( $case_url ); ?>" class="brag-book-gallery-case-link"
			   data-case-id="<?php echo esc_attr( $case['id'] ); ?>">
				<div class="brag-book-gallery-image-container">
					<?php if ( $before_url && $after_url ) : ?>
						<div class="brag-book-case-images-split">
							<div class="brag-book-case-image-before">
								<span class="brag-book-case-image-label">Before</span>
								<picture class="brag-book-gallery-picture">
									<img src="<?php echo esc_url( $before_url ); ?>"
									     alt="<?php echo esc_attr( $procedure_name . ' - Before' ); ?>"
									     loading="lazy"
									     <?php echo $procedure_nudity ? 'class="brag-book-gallery-nudity-blur"' : ''; ?>>
								</picture>
							</div>
							<div class="brag-book-case-image-after">
								<span class="brag-book-case-image-label">After</span>
								<picture class="brag-book-gallery-picture">
									<img src="<?php echo esc_url( $after_url ); ?>"
									     alt="<?php echo esc_attr( $procedure_name . ' - After' ); ?>"
									     loading="lazy"
									     <?php echo $procedure_nudity ? 'class="brag-book-gallery-nudity-blur"' : ''; ?>>
								</picture>
							</div>
							<?php if ( $procedure_nudity ) : ?>
								<div class="brag-book-gallery-nudity-warning">
									<div class="brag-book-gallery-nudity-warning-content">
										<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>
										<p class="brag-book-gallery-nudity-warning-caption">
											This procedure may contain nudity or sensitive content.
											Click to proceed if you wish to view.
										</p>
										<button class="brag-book-gallery-nudity-warning-button" onclick="event.preventDefault(); event.stopPropagation();">
											Proceed
										</button>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php elseif ( $image_url ) : ?>
						<div class="brag-book-case-image-single">
							<picture class="brag-book-gallery-picture">
								<img src="<?php echo esc_url( $image_url ); ?>"
								     alt="<?php echo esc_attr( $procedure_name . ' - Case ' . $case['id'] ); ?>"
								     loading="lazy"
								     <?php echo $procedure_nudity ? 'class="brag-book-gallery-nudity-blur"' : ''; ?>>
							</picture>
							<?php if ( $procedure_nudity ) : ?>
								<div class="brag-book-gallery-nudity-warning">
									<div class="brag-book-gallery-nudity-warning-content">
										<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>
										<p class="brag-book-gallery-nudity-warning-caption">
											This procedure may contain nudity or sensitive content.
											Click to proceed if you wish to view.
										</p>
										<button class="brag-book-gallery-nudity-warning-button" onclick="event.preventDefault(); event.stopPropagation();">
											Proceed
										</button>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div class="brag-book-case-image-placeholder">
							<span>No Image Available</span>
						</div>
					<?php endif; ?>
					<div class="brag-book-gallery-item-actions">
						<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="case-<?php echo esc_attr( $case['id'] ); ?>" aria-label="Add to favorites">
							<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
							</svg>
						</button>
						<?php if ( 'yes' === get_option( 'brag_book_gallery_enable_sharing', 'no' ) ) : ?>
							<button class="brag-book-gallery-share-btn" data-item-id="case-<?php echo esc_attr( $case['id'] ); ?>" aria-label="Share this image">
								<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
									<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>
								</svg>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<div class="brag-book-case-info">
					<h3 class="brag-book-case-title">
						<?php echo esc_html( $procedure_name ); ?>
					</h3>
					<?php if ( ! empty( $demographics ) ) : ?>
						<div class="brag-book-case-demographics">
							<?php echo esc_html( implode( ' • ', $demographics ) ); ?>
						</div>
					<?php endif; ?>
					<div class="brag-book-case-footer">
						<span class="brag-book-case-id">Case #<?php echo esc_html( $case['id'] ); ?></span>
						<span class="brag-book-case-view-link">View Details →</span>
					</div>
				</div>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render AJAX gallery case card with proper structure and data attributes.
	 *
	 * @since 3.0.0
	 * @param array  $case Case data.
	 * @param string $image_display_mode Image display mode (single or before_after).
	 * @return string Rendered HTML.
	 */
	private static function render_ajax_gallery_case_card( array $case, string $image_display_mode, bool $procedure_nudity = false ): string {
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
			$height_unit = ! empty( $case['heightUnit'] ) ? $case['heightUnit'] : '';
			$data_attrs .= ' data-height="' . esc_attr( $height_value ) . '"';
			$data_attrs .= ' data-height-unit="' . esc_attr( $height_unit ) . '"';
			$data_attrs .= ' data-height-full="' . esc_attr( $height_value . $height_unit ) . '"';
		}

		// Add weight with unit
		if ( ! empty( $case['weight'] ) ) {
			$weight_value = $case['weight'];
			$weight_unit = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
			$data_attrs .= ' data-weight="' . esc_attr( $weight_value ) . '"';
			$data_attrs .= ' data-weight-unit="' . esc_attr( $weight_unit ) . '"';
			$data_attrs .= ' data-weight-full="' . esc_attr( $weight_value . $weight_unit ) . '"';
		}

		$html .= '<div class="brag-book-gallery-case-card" ' . $data_attrs . ' data-case-id="' . esc_attr( $case['id'] ) . '">';

		// Get case ID
		$case_id = '';
		if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
			$first_detail = reset( $case['caseDetails'] );
			$case_id = isset( $first_detail['caseId'] ) ? $first_detail['caseId'] : $case['id'];
		} else {
			$case_id = $case['id'];
		}

		// Display images based on setting
		if ( $image_display_mode === 'single' ) {
			// Single image mode - use postProcessedImageLocation from photoSets
			$single_image = '';
			if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
				$first_photoset = reset( $case['photoSets'] );
				$single_image = ! empty( $first_photoset['postProcessedImageLocation'] ) ? $first_photoset['postProcessedImageLocation'] : '';
			}

			if ( $single_image ) {
				$html .= '<div class="brag-book-gallery-case-images single-image" data-case-id="' . esc_attr( $case_id ) . '">';
				$html .= '<div class="brag-book-gallery-single-image">';
				$html .= '<div class="brag-book-gallery-image-container">';
				$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';

				// Add action buttons (share and heart)
				$html .= '<div class="brag-book-gallery-item-actions">';

				// Heart/Favorite button
				$html .= '<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
				$html .= '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">';
				$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
				$html .= '</svg>';
				$html .= '</button>';

				// Share button (conditional)
				$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
				if ( 'yes' === $enable_sharing ) {
					$html .= '<button class="brag-book-gallery-share-btn" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Share this image">';
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

				$html .= '<picture class="brag-book-gallery-picture">';
				$html .= '<img src="' . esc_url( $single_image ) . '" ';
				$html .= 'alt="Case ' . esc_attr( $case_id ) . '" ';
				$html .= 'loading="lazy" ';
				$html .= 'data-image-type="single" ';
				$html .= 'data-image-url="' . esc_attr( $single_image ) . '" ';
				$html .= $procedure_nudity ? 'class="brag-book-gallery-nudity-blur" ' : '';
				$html .= 'onload="this.parentElement.parentElement.querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';" />';
				$html .= '</picture>';
				$html .= '</div>';
				$html .= '</div>';
				$html .= '</div>'; // Close case-images
			} else {
				// Fallback to placeholder if no single image
				$html .= '<div class="brag-book-gallery-case-image-placeholder">';
				$html .= '<span>No image available</span>';
				$html .= '</div>';
			}
		} else {
			// Before/After mode
			if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
				$first_photoset = reset( $case['photoSets'] );

				// Extract before and after images
				$before_image = ! empty( $first_photoset['beforeLocationUrl'] ) ? $first_photoset['beforeLocationUrl'] : '';
				$after_image = ! empty( $first_photoset['afterLocationUrl1'] ) ? $first_photoset['afterLocationUrl1'] : '';

				// Display images side by side with synchronized heights
				$html .= '<div class="brag-book-gallery-case-images before-after" data-case-id="' . esc_attr( $case_id ) . '">';

				// Before image container
				if ( $before_image ) {
					$html .= '<div class="brag-book-gallery-before-image">';
					$html .= '<div class="brag-book-gallery-image-container">';
					$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';
					$html .= '<div class="brag-book-gallery-image-label">Before</div>';
					$html .= '<picture class="brag-book-gallery-picture">';
					$html .= '<img src="' . esc_url( $before_image ) . '" ';
					$html .= 'alt="Before - Case ' . esc_attr( $case_id ) . '" ';
					$html .= 'loading="lazy" ';
					$html .= 'data-image-type="before" ';
					$html .= $procedure_nudity ? 'class="brag-book-gallery-nudity-blur" ' : '';
					$html .= 'onload="window.syncImageHeights(this);" />';
					$html .= '</picture>';
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
					$html .= '<div class="brag-book-gallery-image-label">After</div>';

					// Add action buttons (share and heart)
					$html .= '<div class="brag-book-gallery-item-actions">';

					// Heart/Favorite button
					$html .= '<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
					$html .= '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">';
					$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
					$html .= '</svg>';
					$html .= '</button>';

					// Share button (conditional)
					$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
					if ( 'yes' === $enable_sharing ) {
						$html .= '<button class="brag-book-gallery-share-btn" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Share this image">';
						$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
						$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
						$html .= '</svg>';
						$html .= '</button>';
					}

					$html .= '</div>';

					$html .= '<picture class="brag-book-gallery-picture">';
					$html .= '<img src="' . esc_url( $after_image ) . '" ';
					$html .= 'alt="After - Case ' . esc_attr( $case_id ) . '" ';
					$html .= 'loading="lazy" ';
					$html .= 'data-image-type="after" ';
					$html .= $procedure_nudity ? 'class="brag-book-gallery-nudity-blur" ' : '';
					$html .= 'onload="window.syncImageHeights(this);" />';
					$html .= '</picture>';
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

		// Add case info section
		$html .= '<div class="brag-book-gallery-case-info">';

		// Get procedure slug - use the current filter or procedure from URL if available
		$filter_procedure = get_query_var( 'filter_procedure', '' );
		$procedure_title = get_query_var( 'procedure_title', '' );

		if ( ! empty( $filter_procedure ) ) {
			// Use the filter's procedure slug (e.g., "liposuction" from filter)
			$procedure_slug = $filter_procedure;
		} elseif ( ! empty( $procedure_title ) ) {
			// Use the procedure from case URL (e.g., "liposuction" from /before-after/liposuction/12345)
			$procedure_slug = $procedure_title;
		} else {
			// Fallback to generating from technique (for non-filtered views)
			$procedure_name = $case['technique'] ?? 'Case';
			$procedure_slug = sanitize_title( $procedure_name );
		}

		// Procedure ID
		$procedure_id = '';
		if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
			$first_procedure = reset( $case['procedures'] );
			$procedure_id = $first_procedure['id'] ?? '';
		}

		// View Case button
		$html .= '<div class="brag-book-gallery-case-actions">';
		$html .= '<button class="brag-book-gallery-view-case-btn" ';
		$html .= 'data-case-id="' . esc_attr( $case_id ) . '" ';
		$html .= 'data-procedure-id="' . esc_attr( $procedure_id ) . '" ';
		$html .= 'data-procedure-slug="' . esc_attr( $procedure_slug ) . '" ';
		$html .= 'onclick="window.loadCaseDetails(\'' . esc_js( $case_id ) . '\', \'' . esc_js( $procedure_id ) . '\', \'' . esc_js( $procedure_slug ) . '\')">View Case</button>';
		$html .= '</div>';

		$html .= '</div>'; // Close case-info
		$html .= '</div>'; // Close case card

		return $html;
	}

	/**
	 * Render pagination links.
	 *
	 * @since 3.0.0
	 * @param array  $pagination Pagination data.
	 * @param string $base_path Base URL path.
	 * @param string $filter_procedure Current procedure filter.
	 * @return string Rendered HTML.
	 */
	private static function render_pagination( array $pagination, string $base_path, string $filter_procedure ): string {
		$current_page = $pagination['currentPage'] ?? 1;
		$total_pages = $pagination['totalPages'] ?? 1;

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
		for ( $i = 1; $i <= $total_pages; $i++ ) {
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
	 * @since 3.0.0
	 * @param string $case_id Case ID.
	 * @param array  $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	private static function render_single_case( string $case_id, array $atts ): string {
		// Get API configuration
		if ( empty( $atts['api_token'] ) ) {
			$api_tokens = get_option( 'brag_book_gallery_api_token' ) ?: [];
			$atts['api_token'] = is_array( $api_tokens ) ? ( $api_tokens[0] ?? '' ) : '';
		}

		if ( empty( $atts['website_property_id'] ) ) {
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id' ) ?: [];
			$atts['website_property_id'] = is_array( $website_property_ids ) ? ( $website_property_ids[0] ?? '' ) : '';
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'render_single_case - Looking for case ID: ' . $case_id );
			error_log( 'API Token exists: ' . ( ! empty( $atts['api_token'] ) ? 'Yes' : 'No' ) );
			error_log( 'Website Property ID: ' . $atts['website_property_id'] );
		}

		// First try to get case from cached data
		$cache_key = 'brag_book_all_cases_' . md5( $atts['api_token'] . $atts['website_property_id'] );
		$cached_data = get_transient( $cache_key );
		$case_data = null;

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
				'apiTokens' => [ $atts['api_token'] ],
				'websitePropertyIds' => [ intval( $atts['website_property_id'] ) ],
			];

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API URL: ' . $api_url );
				error_log( 'Request body: ' . wp_json_encode( $request_body ) );
			}

			$response = wp_remote_post( $api_url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $atts['api_token'],
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode( $request_body ),
				'timeout' => 30,
			] );

			if ( ! is_wp_error( $response ) ) {
				$status_code = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );

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
			$all_cases = self::get_all_cases_for_filtering( $atts['api_token'], $atts['website_property_id'] );
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
			$error_message = '<div class="brag-book-case-error">';
			$error_message .= '<p><strong>' . esc_html__( 'Case not found.', 'brag-book-gallery' ) . '</strong></p>';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error_message .= '<div class="brag-book-debug-error">';
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
		$current_url = get_permalink();
		$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '/';
		$procedure_name = $case_data['technique'] ?? 'Case Details';

		ob_start();
		?>
		<div class="brag-book-single-case">
			<div class="brag-book-case-header">
				<a href="<?php echo esc_url( $base_path ); ?>" class="brag-book-case-back">
					&larr; Back to Gallery
				</a>
				<h1><?php echo esc_html( $procedure_name ); ?></h1>
			</div>

			<div class="brag-book-case-details">
				<div class="brag-book-case-meta-info">
					<?php if ( ! empty( $case_data['age'] ) ) : ?>
						<span class="meta-item">Age: <?php echo esc_html( $case_data['age'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $case_data['gender'] ) ) : ?>
						<span class="meta-item"><?php echo esc_html( ucfirst( $case_data['gender'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $case_data['ethnicity'] ) ) : ?>
						<span class="meta-item"><?php echo esc_html( $case_data['ethnicity'] ); ?></span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $case_data['photoSets'] ) ) : ?>
					<div class="brag-book-case-images">
						<?php foreach ( $case_data['photoSets'] as $photoSet ) : ?>
							<div class="brag-book-photo-set">
								<?php if ( ! empty( $photoSet['beforeLocationUrl'] ) ) : ?>
									<div class="before-image">
										<h3>Before</h3>
										<img src="<?php echo esc_url( $photoSet['beforeLocationUrl'] ); ?>"
										     alt="Before" loading="lazy">
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $photoSet['afterLocationUrl1'] ) ) : ?>
									<div class="after-image">
										<h3>After</h3>
										<img src="<?php echo esc_url( $photoSet['afterLocationUrl1'] ); ?>"
										     alt="After" loading="lazy">
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $case_data['details'] ) ) : ?>
					<div class="brag-book-case-description">
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
	 * Display admin notice for rewrite rules debugging.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function rewrite_debug_notice(): void {
		// Only show on relevant admin pages
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, [ 'plugins', 'settings_page_brag-book-gallery-settings' ] ) ) {
			return;
		}

		// Check if we have 404 issues by checking if option exists to show notice
		if ( ! get_transient( 'bragbook_show_rewrite_notice' ) ) {
			// Set transient to show notice (can be triggered manually or by detection)
			set_transient( 'bragbook_show_rewrite_notice', true, DAY_IN_SECONDS );
		}

		if ( get_transient( 'bragbook_show_rewrite_notice' ) ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong>BRAG Book Gallery:</strong> If you're experiencing 404 errors with gallery filter links,
					<button type="button" class="button-link brag-book-flush-rewrite-btn" onclick="bragbookFlushRewrite()">
						click here to flush rewrite rules
					</button>
				</p>
			</div>
			<script>
			function bragbookFlushRewrite() {
				if (!confirm('This will flush WordPress rewrite rules. Continue?')) return;

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=bragbook_flush_rewrite&_wpnonce=<?php echo wp_create_nonce( 'bragbook_flush' ); ?>'
				})
				.then(response => response.json())
				.then(data => {
					alert(data.success ? 'Rewrite rules flushed successfully!' : 'Error: ' + (data.data || 'Unknown error'));
					if (data.success) location.reload();
				})
				.catch(error => alert('Error: ' + error));
			}
			</script>
			<?php
		}
	}

	/**
	 * AJAX handler to flush rewrite rules.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_flush_rewrite_rules(): void {
		// Check nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bragbook_flush' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		try {
			// Force register our rules
			self::custom_rewrite_rules();

			// Flush rewrite rules
			flush_rewrite_rules( true );

			// Clear the notice
			delete_transient( 'bragbook_show_rewrite_notice' );

			// Get debug info
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

			wp_send_json_success( [
				'message' => 'Rewrite rules flushed successfully',
				'gallery_slugs' => $gallery_slugs,
				'timestamp' => current_time( 'mysql' )
			] );

		} catch ( Exception $e ) {
			wp_send_json_error( 'Error flushing rules: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for loading filtered gallery content.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_gallery(): void {
		// Verify nonce - WP VIP: Use isset() check before wp_verify_nonce()
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get filter parameters from AJAX request - WP VIP: Sanitize all input
		$procedure_name = isset( $_POST['procedure_name'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_name'] ) ) : '';
		$procedure_id = isset( $_POST['procedure_id'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_id'] ) ) : '';
		$procedure_ids_param = isset( $_POST['procedure_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['procedure_ids'] ) ) : '';
		$has_nudity = isset( $_POST['has_nudity'] ) && $_POST['has_nudity'] === '1';

		// Debug logging - WP VIP: Only log in debug mode and use sprintf
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'AJAX Filter Debug: procedure_name=%s, procedure_id=%s, procedure_ids=%s',
				$procedure_name,
				$procedure_id,
				$procedure_ids_param
			) );
		}

		// Get API configuration - WP VIP: Provide default empty array
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		// Get procedure IDs for filtering
		$procedure_ids = [];
		if ( ! empty( $procedure_ids_param ) ) {
			// Use the provided procedure IDs (comma-separated)
			$procedure_ids = array_map( 'intval', explode( ',', $procedure_ids_param ) );
		} elseif ( ! empty( $procedure_id ) ) {
			// Use single procedure ID
			$procedure_ids = array_map( 'intval', explode( ',', $procedure_id ) );
		} elseif ( ! empty( $procedure_name ) ) {
			// Try to find procedure ID from sidebar data
			$sidebar_data = self::get_sidebar_data( $api_tokens[0] );
			if ( ! empty( $sidebar_data['data'] ) ) {
				$procedure_info = self::find_procedure_by_slug( $sidebar_data['data'], $procedure_name );
				if ( ! empty( $procedure_info['id'] ) ) {
					$procedure_ids = [ $procedure_info['id'] ];
				}
			}
		}

		// Debug: Log the final procedure IDs being used
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Final procedure_ids for API call: %s', wp_json_encode( $procedure_ids ) ) );
		}

		// Get cases from API using our existing method
		$cases_data = self::get_cases_from_api(
			$api_tokens[0],
			$website_property_ids[0],
			$procedure_ids
		);

		// Always return something, even if empty
		$count = isset( $cases_data['data'] ) ? count( $cases_data['data'] ) : 0;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'API returned %d cases for procedure IDs: %s', $count, implode( ',', $procedure_ids ) ) );
		}

		if ( $count === 0 ) {
			// Return debug info to help troubleshoot - WP VIP: Use sprintf for string building
			$debug_html = sprintf(
				'<div class="brag-book-gallery-debug">
					<p>%s</p>
					<ul>
						<li>%s</li>
						<li>%s</li>
						<li>%s</li>
						<li>%s</li>
					</ul>
					<p>%s</p>
				</div>',
				esc_html__( 'API Call Debug:', 'brag-book-gallery' ),
				sprintf( esc_html__( 'Procedure IDs sent: %s', 'brag-book-gallery' ), esc_html( implode( ',', $procedure_ids ) ) ),
				sprintf( esc_html__( 'API Token: %s...', 'brag-book-gallery' ), esc_html( substr( $api_tokens[0], 0, 10 ) ) ),
				sprintf( esc_html__( 'Website Property ID: %s', 'brag-book-gallery' ), esc_html( $website_property_ids[0] ) ),
				esc_html__( 'Response: Empty data array', 'brag-book-gallery' ),
				esc_html__( 'The API returned no cases for this procedure, even though the sidebar shows there should be cases.', 'brag-book-gallery' )
			);

			wp_send_json_success( [
				'html' => $debug_html,
				'totalCount' => 0,
				'procedureName' => esc_html( $procedure_name ),
			] );
		} else {
			// Build HTML output for the cases
			$html = '<div class="brag-book-filtered-results">';

			// Add H2 header with procedure name - WP VIP: Use sprintf
			$procedure_display_name = ucwords( str_replace( '-', ' ', $procedure_name ) );
			$html .= sprintf(
				'<h2>%s</h2>',
				sprintf(
					esc_html__( '%s Before & After Gallery', 'brag-book-gallery' ),
					esc_html( $procedure_display_name )
				)
			);

			// Add controls container for grid selector and filters
			$html .= '<div class="brag-book-gallery-controls">';

			// Left side container for filters and count
			$html .= '<div class="brag-book-gallery-controls-left">';

			// Procedure filters details - WP VIP: Use sprintf
			$html .= sprintf(
				'<details class="brag-book-gallery-procedure-filters-dropdown" id="procedure-filters-details">
					<summary class="brag-book-gallery-procedure-filters-toggle">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 -960 960 960" fill="currentColor"><path d="M411.15-260v-60h137.31v60H411.15ZM256.16-450v-60h447.3v60h-447.3ZM140-640v-60h680v60H140Z"/></svg>
						<span>%s</span>
					</summary>
					<div class="brag-book-gallery-procedure-filters-panel">
						<div class="brag-book-gallery-procedure-filters-content">
							<div class="brag-book-gallery-procedure-filters-section">
								<h4>%s</h4>
								<div id="brag-book-gallery-procedure-filters-options">
									%s
								</div>
							</div>
						</div>
						<div class="brag-book-gallery-procedure-filters-actions">
							<button class="brag-book-gallery-procedure-filters-apply" onclick="applyProcedureFilters()">%s</button>
							<button class="brag-book-gallery-procedure-filters-clear" onclick="clearProcedureFilters()">%s</button>
						</div>
					</div>
				</details>',
				esc_html__( 'Procedure Filters', 'brag-book-gallery' ),
				esc_html__( 'Filter by Patient Details', 'brag-book-gallery' ),
				'', // Filter options will be dynamically generated by JavaScript
				esc_html__( 'Apply Filters', 'brag-book-gallery' ),
				esc_html__( 'Clear All', 'brag-book-gallery' )
			);

			// Add showing count - WP VIP: Use sprintf
			$shown_count = ! empty( $cases_data['data'] ) ? count( $cases_data['data'] ) : 0;
			$total_count = ! empty( $cases_data['pagination']['total'] ) ? $cases_data['pagination']['total'] : $shown_count;
			$has_more = ! empty( $cases_data['pagination']['has_more'] );

			$html .= sprintf(
				'<div class="brag-book-gallery-count-display">
					<span class="brag-book-gallery-count-label">%s</span>
				</div>',
				sprintf(
					esc_html__( 'Showing %d of %d', 'brag-book-gallery' ),
					intval( $shown_count ),
					intval( $total_count )
				)
			);

			$html .= '</div>'; // Close left controls

			// Grid layout selector (on the right) - WP VIP: Use sprintf
			$html .= sprintf(
				'<div class="brag-book-gallery-grid-selector">
					<span class="brag-book-gallery-grid-label">%s</span>
					<div class="brag-book-gallery-grid-buttons">
						<button class="brag-book-gallery-grid-btn active" data-columns="2" onclick="updateGridLayout(2)" aria-label="%s">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="6" height="6"/><rect x="9" y="1" width="6" height="6"/><rect x="1" y="9" width="6" height="6"/><rect x="9" y="9" width="6" height="6"/></svg>
							<span class="sr-only">%s</span>
						</button>
						<button class="brag-book-gallery-grid-btn" data-columns="3" onclick="updateGridLayout(3)" aria-label="%s">
							<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="1" y="1" width="4" height="4"/><rect x="6" y="1" width="4" height="4"/><rect x="11" y="1" width="4" height="4"/><rect x="1" y="6" width="4" height="4"/><rect x="6" y="6" width="4" height="4"/><rect x="11" y="6" width="4" height="4"/><rect x="1" y="11" width="4" height="4"/><rect x="6" y="11" width="4" height="4"/><rect x="11" y="11" width="4" height="4"/></svg>
							<span class="sr-only">%s</span>
						</button>
					</div>
				</div>',
				esc_html__( 'View:', 'brag-book-gallery' ),
				esc_attr__( 'View in 2 columns', 'brag-book-gallery' ),
				esc_html__( '2 Columns', 'brag-book-gallery' ),
				esc_attr__( 'View in 3 columns', 'brag-book-gallery' ),
				esc_html__( '3 Columns', 'brag-book-gallery' )
			);

			$html .= '</div>'; // Close controls container

			// Start cases grid with CSS Grid layout
			$html .= '<div class="brag-book-gallery-cases-grid" data-columns="2">';

			// Loop through cases
			if ( ! empty( $cases_data['data'] ) ) {
				foreach ( $cases_data['data'] as $case ) {
					// Prepare patient details for data attributes - WP VIP: Build array first
					$data_attrs_array = [
						'data-card="true"'
					];

					// Add age
					if ( ! empty( $case['age'] ) ) {
						$data_attrs_array[] = sprintf( 'data-age="%s"', esc_attr( $case['age'] ) );
					}

					// Add gender
					if ( ! empty( $case['gender'] ) ) {
						$data_attrs_array[] = sprintf( 'data-gender="%s"', esc_attr( strtolower( $case['gender'] ) ) );
					}

					// Add ethnicity
					if ( ! empty( $case['ethnicity'] ) ) {
						$data_attrs_array[] = sprintf( 'data-ethnicity="%s"', esc_attr( strtolower( $case['ethnicity'] ) ) );
					}

					// Add height with unit
					if ( ! empty( $case['height'] ) ) {
						$height_value = $case['height'];
						$height_unit = ! empty( $case['heightUnit'] ) ? $case['heightUnit'] : '';
						$data_attrs_array[] = sprintf( 'data-height="%s"', esc_attr( $height_value ) );
						$data_attrs_array[] = sprintf( 'data-height-unit="%s"', esc_attr( $height_unit ) );
						$data_attrs_array[] = sprintf( 'data-height-full="%s"', esc_attr( $height_value . $height_unit ) );
					}

					// Add weight with unit
					if ( ! empty( $case['weight'] ) ) {
						$weight_value = $case['weight'];
						$weight_unit = ! empty( $case['weightUnit'] ) ? $case['weightUnit'] : '';
						$data_attrs_array[] = sprintf( 'data-weight="%s"', esc_attr( $weight_value ) );
						$data_attrs_array[] = sprintf( 'data-weight-unit="%s"', esc_attr( $weight_unit ) );
						$data_attrs_array[] = sprintf( 'data-weight-full="%s"', esc_attr( $weight_value . $weight_unit ) );
					}

					$data_attrs = implode( ' ', $data_attrs_array );
					$html .= sprintf( '<div class="brag-book-gallery-case-card" %s>', $data_attrs );

					// Get case ID and procedure name from caseDetails - WP VIP: Use null coalescing
					$procedure_name_for_card = '';
					if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						$first_detail = reset( $case['caseDetails'] );
						$case_id = $first_detail['caseId'] ?? $case['id'] ?? '';
						// Get procedure name from case details
						if ( isset( $first_detail['procedureName'] ) ) {
							$procedure_name_for_card = $first_detail['procedureName'];
						}
					} else {
						$case_id = $case['id'] ?? '';
					}

					// Fallback to the main procedure name if not in case details
					if ( empty( $procedure_name_for_card ) ) {
						$procedure_name_for_card = $procedure_display_name; // Use the already formatted procedure name
					}

					// Get image display mode setting
					$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

					// Display images based on setting
					if ( $image_display_mode === 'single' ) {
						// Single image mode - use postProcessedImageLocation from photoSets
						$single_image = '';
						if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
							$first_photoset = reset( $case['photoSets'] );
							$single_image = ! empty( $first_photoset['postProcessedImageLocation'] ) ? $first_photoset['postProcessedImageLocation'] : '';
						}

						if ( $single_image ) {
							$html .= '<div class="brag-book-gallery-case-images single-image" data-case-id="' . esc_attr( $case_id ) . '">';
							$html .= '<div class="brag-book-gallery-single-image">';
							$html .= '<div class="brag-book-gallery-image-container">';
							$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';

							// Add action buttons (share and heart)
							$html .= '<div class="brag-book-gallery-item-actions">';

							// Heart/Favorite button
							$html .= '<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
							$html .= '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">';
							$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
							$html .= '</svg>';
							$html .= '</button>';

							// Share button (conditional)
							$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
							if ( 'yes' === $enable_sharing ) {
								$html .= '<button class="brag-book-gallery-share-btn" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Share this image">';
								$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
								$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
								$html .= '</svg>';
								$html .= '</button>';
							}

							$html .= '</div>';

							$html .= '<picture class="brag-book-gallery-picture">';
							$html .= '<img src="' . esc_url( $single_image ) . '" ';
							$html .= 'alt="Case ' . esc_attr( $case_id ) . '" ';
							$html .= 'loading="lazy" ';
							$html .= 'data-image-type="single" ';
							$html .= 'data-image-url="' . esc_attr( $single_image ) . '" ';
							if ( $has_nudity ) {
								$html .= 'class="brag-book-gallery-nudity-blur" ';
							}
							$html .= 'onload="this.parentElement.parentElement.querySelector(\'.brag-book-gallery-skeleton-loader\').style.display=\'none\';" />';
							$html .= '</picture>';

							// Add nudity warning overlay if applicable
							if ( $has_nudity ) {
								$html .= '<div class="brag-book-gallery-nudity-warning">';
								$html .= '<div class="brag-book-gallery-nudity-warning-content">';
								$html .= '<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>';
								$html .= '<p class="brag-book-gallery-nudity-warning-caption">';
								$html .= 'This procedure may contain nudity or sensitive content. ';
								$html .= 'Click to proceed if you wish to view.';
								$html .= '</p>';
								$html .= '<button class="brag-book-gallery-nudity-warning-button">Proceed</button>';
								$html .= '</div>';
								$html .= '</div>';
							}

							$html .= '</div>';
							$html .= '</div>';
							$html .= '</div>'; // Close case-images
						} else {
							// Fallback to placeholder if no single image
							$html .= '<div class="brag-book-gallery-case-image-placeholder">';
							$html .= '<span>No image available</span>';
							$html .= '</div>';
						}
					} else {
						// Before/After mode (existing logic)
						if ( ! empty( $case['photoSets'] ) && is_array( $case['photoSets'] ) ) {
							$first_photoset = reset( $case['photoSets'] );

							// Extract before and after images
							$before_image = ! empty( $first_photoset['beforeLocationUrl'] ) ? $first_photoset['beforeLocationUrl'] : '';
							$after_image = ! empty( $first_photoset['afterLocationUrl1'] ) ? $first_photoset['afterLocationUrl1'] : '';

							// Display images side by side with synchronized heights
							$html .= '<div class="brag-book-gallery-case-images before-after" data-case-id="' . esc_attr( $case_id ) . '">';

						// Before image container
						if ( $before_image ) {
							$html .= '<div class="brag-book-gallery-before-image">';
							$html .= '<div class="brag-book-gallery-image-container">';
							$html .= '<div class="brag-book-gallery-skeleton-loader"></div>';
							$html .= '<div class="brag-book-gallery-image-label">Before</div>';
							$html .= '<picture class="brag-book-gallery-picture">';
							$html .= '<img src="' . esc_url( $before_image ) . '" ';
							$html .= 'alt="Before - Case ' . esc_attr( $case_id ) . '" ';
							$html .= 'loading="lazy" ';
							$html .= 'data-image-type="before" ';
							if ( $has_nudity ) {
								$html .= 'class="brag-book-gallery-nudity-blur" ';
							}
							$html .= 'onload="window.syncImageHeights(this);" />';
							$html .= '</picture>';

							// Add nudity warning overlay if applicable
							if ( $has_nudity ) {
								$html .= '<div class="brag-book-gallery-nudity-warning">';
								$html .= '<div class="brag-book-gallery-nudity-warning-content">';
								$html .= '<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>';
								$html .= '<p class="brag-book-gallery-nudity-warning-caption">';
								$html .= 'This procedure may contain nudity or sensitive content. ';
								$html .= 'Click to proceed if you wish to view.';
								$html .= '</p>';
								$html .= '<button class="brag-book-gallery-nudity-warning-button">Proceed</button>';
								$html .= '</div>';
								$html .= '</div>';
							}

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
							$html .= '<div class="brag-book-gallery-image-label">After</div>';

							// Add action buttons (share and heart)
							$html .= '<div class="brag-book-gallery-item-actions">';

							// Heart/Favorite button
							$html .= '<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Add to favorites">';
							$html .= '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">';
							$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
							$html .= '</svg>';
							$html .= '</button>';

							// Share button (conditional)
							$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
							if ( 'yes' === $enable_sharing ) {
								$html .= '<button class="brag-book-gallery-share-btn" data-item-id="case-' . esc_attr( $case_id ) . '" aria-label="Share this image">';
								$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
								$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
								$html .= '</svg>';
								$html .= '</button>';
							}

							$html .= '</div>';

							$html .= '<picture class="brag-book-gallery-picture">';
							$html .= '<img src="' . esc_url( $after_image ) . '" ';
							$html .= 'alt="After - Case ' . esc_attr( $case_id ) . '" ';
							$html .= 'loading="lazy" ';
							$html .= 'data-image-type="after" ';
							$html .= 'data-image-url="' . esc_attr( $after_image ) . '" ';
							if ( $has_nudity ) {
								$html .= 'class="brag-book-gallery-nudity-blur" ';
							}
							$html .= 'onload="window.syncImageHeights(this);" />';
							$html .= '</picture>';

							// Add nudity warning overlay if applicable
							if ( $has_nudity ) {
								$html .= '<div class="brag-book-gallery-nudity-warning">';
								$html .= '<div class="brag-book-gallery-nudity-warning-content">';
								$html .= '<h4 class="brag-book-gallery-nudity-warning-title">Nudity Warning</h4>';
								$html .= '<p class="brag-book-gallery-nudity-warning-caption">';
								$html .= 'This procedure may contain nudity or sensitive content. ';
								$html .= 'Click to proceed if you wish to view.';
								$html .= '</p>';
								$html .= '<button class="brag-book-gallery-nudity-warning-button">Proceed</button>';
								$html .= '</div>';
								$html .= '</div>';
							}

							$html .= '</div>';
							$html .= '</div>';
						} else {
							$html .= '<div class="brag-book-gallery-after-placeholder">';
							$html .= '<div class="brag-book-gallery-placeholder-container">';
							$html .= '<span>After</span>';
							$html .= '</div>';
							$html .= '</div>';
						}

							$html .= '</div>'; // Close case-images
						} else {
							// Fallback if no photoSets in before/after mode
							$html .= '<div class="brag-book-gallery-case-image-placeholder">';
							$html .= '<span>No images available</span>';
							$html .= '</div>';
						}
					}

					// Add case info section below images
					$html .= '<div class="brag-book-gallery-case-info">';

					// Card header with title and View Case button
					$html .= '<div class="brag-book-gallery-case-header">';

					// Title section (left side)
					$html .= '<div class="brag-book-gallery-case-title-section">';
					$html .= sprintf(
						'<h3 class="brag-book-gallery-case-title">%s <span class="brag-book-gallery-case-number">Case #%s</span></h3>',
						esc_html( $procedure_name_for_card ),
						esc_html( $case_id )
					);
					$html .= '</div>';

					// View Case button (right side)
					// Get procedure slug - use the slugName from procedures array or fallback to sanitized name
					$procedure_slug = '';
					$procedure_id = '';
					if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
						$first_procedure = reset( $case['procedures'] );
						$procedure_id = $first_procedure['id'] ?? '';
						// Use slugName if available, otherwise sanitize the procedure name
						if ( ! empty( $first_procedure['slugName'] ) ) {
							$procedure_slug = $first_procedure['slugName'];
						} elseif ( ! empty( $first_procedure['name'] ) ) {
							$procedure_slug = sanitize_title( $first_procedure['name'] );
						}
					}

					// Fallback to the passed procedure name if no slug found
					if ( empty( $procedure_slug ) ) {
						$procedure_slug = sanitize_title( $procedure_name );
					}

					$html .= '<div class="brag-book-gallery-case-actions">';
					$html .= sprintf(
						'<button class="brag-book-gallery-view-case-btn" data-case-id="%s" data-procedure-id="%s" data-procedure-slug="%s" onclick="window.loadCaseDetails(\'%s\', \'%s\', \'%s\')">View Case</button>',
						esc_attr( $case_id ),
						esc_attr( $procedure_id ),
						esc_attr( $procedure_slug ),
						esc_js( $case_id ),
						esc_js( $procedure_id ),
						esc_js( $procedure_slug )
					);
					$html .= '</div>';

					$html .= '</div>'; // Close case-header

					// Add case details if available
					if ( ! empty( $case['caseDetails'] ) && is_array( $case['caseDetails'] ) ) {
						$first_detail = reset( $case['caseDetails'] );
						if ( ! empty( $first_detail['details'] ) ) {
							$html .= '<div class="brag-book-gallery-case-details">';
							$html .= sprintf(
								'<div class="brag-book-gallery-details-text">%s</div>',
								esc_html( wp_trim_words( $first_detail['details'], 25 ) )
							);
							$html .= '</div>';
						}
					}

					// List procedures at the bottom
					if ( ! empty( $case['procedures'] ) && is_array( $case['procedures'] ) ) {
						$html .= '<div class="brag-book-gallery-case-procedures">';
						$html .= '<div class="brag-book-gallery-procedures-label">Procedures performed:</div>';
						$html .= '<div class="brag-book-gallery-procedures-badges">';

						$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
						$gallery_base = ! empty( $gallery_slugs[0] ) ? $gallery_slugs[0] : 'before-after';

						foreach ( $case['procedures'] as $procedure ) {
							if ( ! empty( $procedure['name'] ) && ! empty( $procedure['id'] ) ) {
								$procedure_url = home_url( '/' . $gallery_base . '/' . ( ! empty( $procedure['slugName'] ) ? $procedure['slugName'] : sanitize_title( $procedure['name'] ) ) );
								$html .= sprintf(
									'<a href="%s" class="brag-book-gallery-procedure-badge brag-book-gallery-filter-link" data-procedure-ids="%s" data-category="all" data-procedure="%s">%s</a>',
									esc_url( $procedure_url ),
									esc_attr( $procedure['id'] ),
									esc_attr( $procedure['name'] ),
									esc_html( $procedure['name'] )
								);
							}
						}

						$html .= '</div>';
						$html .= '</div>';
					}

					$html .= '</div>'; // Close case-info

					$html .= '</div>'; // Close case card
				}
			}

			$html .= '</div>'; // Close cases grid

			// Add Load More button if there are more cases
			if ( $has_more ) {
				$procedure_ids_str = ! empty( $procedure_ids ) ? implode( ',', $procedure_ids ) : '';
				$html .= '<div class="brag-book-load-more-container">';
				$html .= '<button class="brag-book-load-more-btn" ';
				$html .= 'data-start-page="' . ( ( $cases_data['pagination']['last_page_loaded'] ?? 5 ) + 1 ) . '" ';
				$html .= 'data-procedure-ids="' . esc_attr( $procedure_ids_str ) . '" ';
				$html .= 'onclick="loadMoreCases(this)">';
				$html .= 'Load More';
				$html .= '</button>';
				$html .= '</div>';
			}

			$html .= '</div>'; // Close filtered results

			// Add JavaScript for grid control and load more
			$html .= '<script>
				// Store complete dataset for filter generation
				window.bragBookCompleteDataset = ' . json_encode( array_map( function( $case ) {
					return [
						'id' => $case['id'] ?? '',
						'age' => $case['age'] ?? $case['patientAge'] ?? '',
						'gender' => $case['gender'] ?? $case['patientGender'] ?? '',
						'ethnicity' => $case['ethnicity'] ?? $case['patientEthnicity'] ?? '',
						'height' => $case['height'] ?? $case['patientHeight'] ?? '',
						'weight' => $case['weight'] ?? $case['patientWeight'] ?? '',
					];
				}, $cases_data['data'] ?? [] ) ) . ';

				// Initialize procedure filters after data is available
				setTimeout(function() {
					if (typeof initializeProcedureFilters === "function") {
						initializeProcedureFilters();
					}
				}, 100);
			</script>

			<script>
				window.loadMoreCases = function(button) {
					// Disable button and show loading state
					button.disabled = true;
					const originalText = button.textContent;
					button.textContent = "Loading...";

					// Get data from button attributes
					const startPage = button.getAttribute("data-start-page");
					const procedureIds = button.getAttribute("data-procedure-ids");

					// Prepare AJAX data
					const formData = new FormData();
					formData.append("action", "brag_book_load_more_cases");
					formData.append("nonce", "' . wp_create_nonce( 'brag_book_gallery_nonce' ) . '");
					formData.append("start_page", startPage);
					formData.append("procedure_ids", procedureIds);

					// Make AJAX request
					fetch("' . admin_url( 'admin-ajax.php' ) . '", {
						method: "POST",
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							// Add new cases to the container
							const container = document.querySelector(".brag-book-cases-grid");
							if (container) {
								container.insertAdjacentHTML("beforeend", data.data.html);
							}

							// Update button for next load
							if (data.data.hasMore) {
								button.setAttribute("data-start-page", parseInt(startPage) + Math.ceil(data.data.casesLoaded / 10));
								button.disabled = false;
								button.textContent = originalText;
							} else {
								// No more cases, hide the button
								button.parentElement.style.display = "none";
							}

							// Update the count display
							const countLabel = document.querySelector(".brag-book-gallery-count-label");
							if (countLabel) {
								const currentShown = container.querySelectorAll(".brag-book-case-card").length;
								const match = countLabel.textContent.match(/of (\d+)/);
								if (match) {
									const total = match[1];
									countLabel.textContent = "Showing " + currentShown + " of " + total;
								}
							}
						} else {
							console.error("Failed to load more cases:", data.data.message);
							button.disabled = false;
							button.textContent = originalText;
						}
					})
					.catch(error => {
						console.error("Error loading more cases:", error);
						button.disabled = false;
						button.textContent = originalText;
					});
				}

				function updateGridLayout(columns) {
					const grid = document.querySelector(".brag-book-cases-grid");
					const cards = document.querySelectorAll(".brag-book-case-card[data-card=\"true\"]");
					const buttons = document.querySelectorAll(".grid-btn");

					if (grid && cards.length > 0) {
						// Update grid data attribute
						grid.setAttribute("data-columns", columns);

						// Calculate card width based on columns
						let cardWidth;
						if (columns === 2) {
							cardWidth = "calc(50% - 10px)";
						} else if (columns === 3) {
							cardWidth = "calc(33.333% - 14px)";
						}

						// Update all cards
						cards.forEach(card => {
							card.style.width = cardWidth;
						});

						// Update button states
						buttons.forEach(btn => {
							if (parseInt(btn.getAttribute("data-columns")) === columns) {
								btn.style.background = "#007cba";
								btn.style.color = "#fff";
								btn.style.borderColor = "#007cba";
							} else {
								btn.style.background = "#fff";
								btn.style.color = "#333";
								btn.style.borderColor = "#ddd";
							}
						});
					}
				}

				// Set initial active state
				document.addEventListener("DOMContentLoaded", function() {
					const defaultBtn = document.querySelector(".grid-btn[data-columns=\"2\"]");
					if (defaultBtn) {
						defaultBtn.style.background = "#007cba";
						defaultBtn.style.color = "#fff";
						defaultBtn.style.borderColor = "#007cba";
					}
				});
			</script>';

			error_log( 'Sending HTML with ' . $count . ' cases' );

			wp_send_json_success( [
				'html' => $html,
				'totalCount' => $count,
				'procedureName' => $procedure_name,
			] );
		}
	}

	/**
	 * AJAX handler to load case details.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_case_details(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get case ID
		$case_id = isset( $_POST['case_id'] ) ? sanitize_text_field( wp_unslash( $_POST['case_id'] ) ) : '';

		if ( empty( $case_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Case ID is required.', 'brag-book-gallery' ),
			] );
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		// Debug log the configuration
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'AJAX Load Case - Case ID: ' . $case_id );
			error_log( 'API Tokens count: ' . count( $api_tokens ) );
			error_log( 'Property IDs count: ' . count( $website_property_ids ) );
			if ( ! empty( $api_tokens ) ) {
				error_log( 'First token (partial): ' . substr( $api_tokens[0], 0, 10 ) . '...' );
			}
			if ( ! empty( $website_property_ids ) ) {
				error_log( 'First property ID: ' . $website_property_ids[0] );
			}
		}

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
				'debug' => [
					'has_tokens' => ! empty( $api_tokens ),
					'has_property_ids' => ! empty( $website_property_ids ),
				],
			] );
		}

		// Initialize endpoints
		$endpoints = new Endpoints();

		// Get case details from API
		$case_data = null;
		$error_messages = [];

		// Try to get case details using the caseNumber endpoint
		foreach ( $api_tokens as $index => $token ) {
			$property_id = $website_property_ids[ $index ] ?? null;

			if ( ! $property_id ) {
				$error_messages[] = 'Missing property ID for token index ' . $index;
				continue;
			}

			// Try to fetch case details
			$response = $endpoints->bb_get_case_by_number( $token, (int) $property_id, $case_id );

			if ( ! empty( $response ) && is_array( $response ) ) {
				$case_data = $response;
				break;
			} else {
				$error_messages[] = 'Failed to fetch from token index ' . $index;
			}
		}

		// If no case data found, try pagination endpoint as fallback
		if ( empty( $case_data ) ) {
			$filter_body = [
				'apiTokens' => $api_tokens,
				'websitePropertyIds' => array_map( 'intval', $website_property_ids ),
				'count' => 100,
			];

			$response = $endpoints->bb_get_pagination_data( $filter_body );

			if ( ! empty( $response['cases'] ) && is_array( $response['cases'] ) ) {
				// Search for the case in the results
				foreach ( $response['cases'] as $case ) {
					if ( isset( $case['caseNumber'] ) && $case['caseNumber'] === $case_id ) {
						$case_data = $case;
						break;
					}
				}
			}
		}

		if ( empty( $case_data ) ) {
			$debug_info = '';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $error_messages ) ) {
				$debug_info = ' Debug: ' . implode( ', ', $error_messages );
			}

			wp_send_json_error( [
				'message' => __( 'Case not found.', 'brag-book-gallery' ) . $debug_info,
				'case_id' => $case_id,
				'errors' => $error_messages,
			] );
		}

		// Get procedure names from procedure IDs
		$procedure_names = [];
		if ( ! empty( $case_data['procedureIds'] ) && is_array( $case_data['procedureIds'] ) ) {
			// You could fetch procedure names from API here if needed
			// For now, we'll use the technique field or a generic name
			$procedure_names[] = $case_data['technique'] ?? 'Procedure';
		}

		// Format case data for frontend
		$formatted_data = [
			'caseNumber' => $case_data['id'] ?? $case_id,
			'procedureName' => ! empty( $procedure_names ) ? implode( ', ', $procedure_names ) : ( $case_data['technique'] ?? 'Case Details' ),
			'description' => $case_data['details'] ?? $case_data['description'] ?? '',
			'technique' => $case_data['technique'] ?? '',
			'gender' => $case_data['gender'] ?? '',
			'age' => $case_data['age'] ?? '',
			'photos' => [],
		];

		// Process photoSets
		if ( ! empty( $case_data['photoSets'] ) && is_array( $case_data['photoSets'] ) ) {
			foreach ( $case_data['photoSets'] as $photo_set ) {
				// Use postProcessedImageLocation if available, otherwise use original URLs
				$before_image = $photo_set['postProcessedImageLocation'] ??
								$photo_set['beforeLocationUrl'] ??
								$photo_set['originalBeforeLocation'] ?? '';

				$after_image = $photo_set['afterLocationUrl1'] ??
							   $photo_set['originalAfterLocation1'] ?? '';

				// Only add if we have at least one image
				if ( ! empty( $before_image ) || ! empty( $after_image ) ) {
					$formatted_data['photos'][] = [
						'beforeImage' => $before_image,
						'afterImage' => $after_image,
						'caption' => $photo_set['notes'] ?? '',
						'isProcessed' => ! empty( $photo_set['postProcessedImageLocation'] ),
					];
				}
			}
		}

		// Send response
		wp_send_json_success( $formatted_data );
	}

	/**
	 * Case details shortcode handler
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function case_details_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts( [
			'case_id' => '',
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
		<div class="brag-book-gallery-case-details-container" data-case-id="<?php echo esc_attr( $atts['case_id'] ); ?>">
			<div class="brag-book-gallery-loading">
				Loading case details...
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const container = document.querySelector('.brag-book-gallery-case-details-container');
			const caseId = container.dataset.caseId;

			if (!caseId) {
				container.innerHTML = '<div class="brag-book-gallery-error">Invalid case ID.</div>';
				return;
			}

			// Load case details via AJAX
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'load_case_details',
					case_id: caseId,
					nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_nonce' ); ?>'
				})
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					container.innerHTML = data.data.html;
				} else {
					container.innerHTML = '<div class="brag-book-gallery-error">Failed to load case details.</div>';
				}
			})
			.catch(error => {
				container.innerHTML = '<div class="brag-book-gallery-error">Error loading case details.</div>';
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler for loading case details HTML
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_case_details_html(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$case_id = sanitize_text_field( $_POST['case_id'] ?? '' );
		$procedure_id = sanitize_text_field( $_POST['procedure_id'] ?? '' );

		if ( empty( $case_id ) ) {
			wp_send_json_error( 'Case ID is required' );
			return;
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'AJAX load_case_details_html - Case ID: ' . $case_id );
			error_log( 'AJAX load_case_details_html - Looking for case in cached data' );
		}

		// First, try to get the case from cached data (where all cases are already loaded)
		$cache_key = 'brag_book_all_cases_' . md5( $api_token . $website_property_id );
		$cached_data = get_transient( $cache_key );
		$case_data = null;

		if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) ) {
			// Search for the case in cached data
			foreach ( $cached_data['data'] as $case ) {
				if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					$case_data = $case;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'AJAX load_case_details_html - Case found in cache!' );
					}
					break;
				}
			}
		}

		// If not found in cache, try to fetch all cases
		if ( empty( $case_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AJAX load_case_details_html - Case not in cache, fetching all cases' );
			}

			// Get all cases
			$all_cases = self::get_all_cases_for_filtering( $api_token, $website_property_id );

			if ( ! empty( $all_cases['data'] ) ) {
				foreach ( $all_cases['data'] as $case ) {
					if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
						$case_data = $case;
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'AJAX load_case_details_html - Case found in fresh data!' );
						}
						break;
					}
				}
			}
		}

		// If still not found, try the other AJAX handler's approach
		if ( empty( $case_data ) ) {
			// Use the Endpoints class as a last resort
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'rest/class-endpoints.php';
			$endpoints = new Endpoints();

			// Try the pagination endpoint to get cases
			$filter_body = [
				'apiTokens' => [ $api_token ],
				'websitePropertyIds' => [ $website_property_id ],
				'count' => 1,  // Page 1
				'limit' => 100, // Get more cases
			];

			$response = $endpoints->bb_get_pagination_data( $filter_body );

			if ( ! empty( $response ) ) {
				$response_data = json_decode( $response, true );

				if ( ! empty( $response_data['data'] ) ) {
					foreach ( $response_data['data'] as $case ) {
						if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
							$case_data = $case;
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( 'AJAX load_case_details_html - Case found via pagination endpoint!' );
							}
							break;
						}
					}
				}
			}
		}

		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			// Generate HTML for case details
			$html = self::render_case_details_html( $case_data );

			wp_send_json_success( [
				'html' => $html,
				'case_id' => $case_id,
			] );
			return;
		}

		// If we get here, the case was not found
		$error_msg = 'Case not found. Case ID: ' . $case_id;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$error_msg .= ' - Case may not exist in this gallery.';
		}
		wp_send_json_error( $error_msg );
	}

	/**
	 * Simple AJAX handler for case details that works
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_simple_case_handler(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$case_id = sanitize_text_field( $_POST['case_id'] ?? '' );

		if ( empty( $case_id ) ) {
			wp_send_json_error( 'Case ID is required' );
			return;
		}

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
			wp_send_json_error( 'API configuration missing' );
			return;
		}

		$api_token = $api_tokens[0];
		$website_property_id = intval( $website_property_ids[0] );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'AJAX simple case handler - Case ID: ' . $case_id );
			error_log( 'API Token (first 10 chars): ' . substr( $api_token, 0, 10 ) . '...' );
			error_log( 'Website Property ID: ' . $website_property_id );
		}

		// First, try to get the case from cached data
		$cache_key = 'brag_book_all_cases_' . md5( $api_token . $website_property_id );
		$cached_data = get_transient( $cache_key );
		$case_data = null;

		if ( $cached_data && isset( $cached_data['data'] ) && is_array( $cached_data['data'] ) ) {
			// Search for the case in cached data
			foreach ( $cached_data['data'] as $case ) {
				if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
					$case_data = $case;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'AJAX simple case handler - Case found in cache!' );
					}
					break;
				}
			}
		}

		// If not found in cache, try to fetch all cases
		if ( empty( $case_data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AJAX simple case handler - Case not in cache, fetching all cases' );
			}
			// Get all cases
			$all_cases = self::get_all_cases_for_filtering( $api_token, $website_property_id );
			if ( ! empty( $all_cases['data'] ) ) {
				foreach ( $all_cases['data'] as $case ) {
					if ( isset( $case['id'] ) && strval( $case['id'] ) === strval( $case_id ) ) {
						$case_data = $case;
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'AJAX simple case handler - Case found in fresh data!' );
						}
						break;
					}
				}
			}
		}

		// If we found the case data, render it
		if ( ! empty( $case_data ) && is_array( $case_data ) ) {
			$html = self::render_case_details_html( $case_data );
			wp_send_json_success( [
				'html' => $html,
				'case_id' => $case_id,
			] );
			return;
		}

		// If we get here, the case was not found
		$error_msg = 'Case not found. Case ID: ' . $case_id;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$error_msg .= ' - Case may not exist in this gallery.';
		}
		wp_send_json_error( $error_msg );
	}

	/**
	 * Render case details HTML
	 *
	 * @since 3.0.0
	 * @param array $case_data Case data from API.
	 * @return string HTML output.
	 */
	private static function render_case_details_html( array $case_data ): string {
		$html = '<div class="brag-book-gallery-case-detail-view">';

		// Get procedure name and case ID
		$procedure_name = '';
		$procedure_slug = '';
		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			$first_procedure = reset( $case_data['procedures'] );
			$procedure_name = $first_procedure['name'] ?? '';
			$procedure_slug = sanitize_title( $procedure_name );
		}
		$case_id = $case_data['id'] ?? '';

		// Get current page URL for back link
		$current_url = get_permalink();
		if ( ! empty( $current_url ) ) {
			$base_path = parse_url( $current_url, PHP_URL_PATH ) ?: '';
			// Remove the case-specific part of the URL to get back to gallery
			$base_path = preg_replace( '/\/[^\/]+\/[^\/]+\/?$/', '', $base_path );
		} else {
			// Fallback to getting gallery page from options
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );
			$base_path = ! empty( $gallery_slugs[0] ) ? '/' . $gallery_slugs[0] : '/before-after';
		}

		// Header section with navigation and title
		$html .= '<div class="brag-book-gallery-case-header-section">';

		// Back to gallery link
		$html .= '<div class="brag-book-gallery-case-navigation">';
		$html .= '<a href="' . esc_url( $base_path ) . '" class="brag-book-gallery-back-link">← Back to Gallery</a>';
		$html .= '</div>';

		// Case header with title
		$html .= '<div class="brag-book-gallery-case-header">';
		$html .= '<h1 class="brag-book-gallery-case-title">';
		$html .= esc_html( $procedure_name );
		if ( ! empty( $case_id ) ) {
			$html .= ' <span class="case-id">#' . esc_html( $case_id ) . '</span>';
		}
		$html .= '</h1>';
		$html .= '</div>';
		$html .= '</div>';

		// Main content container
		$html .= '<div class="brag-book-gallery-case-content">';

		// Images section - now takes full width at top
		$html .= '<div class="brag-book-gallery-case-images-section">';
		$html .= '<h2 class="section-title">Before & After Photos</h2>';
		$html .= '<div class="brag-book-gallery-case-images-grid">';

		if ( ! empty( $case_data['photoSets'] ) && is_array( $case_data['photoSets'] ) ) {
			$image_count = count( $case_data['photoSets'] );
			$grid_class = $image_count === 1 ? 'single-image' : ( $image_count === 2 ? 'two-images' : 'multiple-images' );
			$html .= '<div class="case-images-container ' . esc_attr( $grid_class ) . '">';

			foreach ( $case_data['photoSets'] as $index => $photo ) {
				if ( ! empty( $photo['postProcessedImageLocation'] ) ) {
					$image_url = $photo['postProcessedImageLocation'];
					$image_id = 'case_' . $case_id . '_image_' . $index;

					$html .= '<div class="brag-book-gallery-case-image-container">';
					$html .= '<picture class="brag-book-gallery-case-image">';
					$html .= '<source srcset="' . esc_url( $image_url ) . '" type="image/jpeg">';
					$html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $procedure_name . ' - Case ' . $case_id ) . '" loading="lazy">';
					$html .= '</picture>';
					$html .= '<div class="brag-book-gallery-item-actions">';
					$html .= '<button class="brag-book-gallery-heart-btn" data-favorited="false" data-item-id="' . esc_attr( $image_id ) . '" data-image-url="' . esc_attr( $image_url ) . '" aria-label="Add to favorites">';
					$html .= '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">';
					$html .= '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>';
					$html .= '</svg>';
					$html .= '</button>';

					// Only show share button if sharing is enabled
					$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
					if ( $enable_sharing === 'yes' ) {
						$html .= '<button class="brag-book-gallery-share-btn" data-item-id="' . esc_attr( $image_id ) . '" data-image-url="' . esc_attr( $image_url ) . '" aria-label="Share this image">';
						$html .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">';
						$html .= '<path d="M672.22-100q-44.91 0-76.26-31.41-31.34-31.41-31.34-76.28 0-6 4.15-29.16L284.31-404.31q-14.46 15-34.36 23.5t-42.64 8.5q-44.71 0-76.01-31.54Q100-435.39 100-480q0-44.61 31.3-76.15 31.3-31.54 76.01-31.54 22.74 0 42.64 8.5 19.9 8.5 34.36 23.5l284.46-167.08q-2.38-7.38-3.27-14.46-.88-7.08-.88-15.08 0-44.87 31.43-76.28Q627.49-860 672.4-860t76.25 31.44Q780-797.13 780-752.22q0 44.91-31.41 76.26-31.41 31.34-76.28 31.34-22.85 0-42.5-8.69Q610.15-662 595.69-677L311.23-509.54q2.38 7.39 3.27 14.46.88 7.08.88 15.08t-.88 15.08q-.89 7.07-3.27 14.46L595.69-283q14.46-15 34.12-23.69 19.65-8.69 42.5-8.69 44.87 0 76.28 31.43Q780-252.51 780-207.6t-31.44 76.25Q717.13-100 672.22-100Zm.09-60q20.27 0 33.98-13.71Q720-187.42 720-207.69q0-20.27-13.71-33.98-13.71-13.72-33.98-13.72-20.27 0-33.98 13.72-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98Q652.04-160 672.31-160Zm-465-272.31q20.43 0 34.25-13.71 13.83-13.71 13.83-33.98 0-20.27-13.83-33.98-13.82-13.71-34.25-13.71-20.11 0-33.71 13.71Q160-500.27 160-480q0 20.27 13.6 33.98 13.6 13.71 33.71 13.71Zm465-272.3q20.27 0 33.98-13.72Q720-732.04 720-752.31q0-20.27-13.71-33.98Q692.58-800 672.31-800q-20.27 0-33.98 13.71-13.72 13.71-13.72 33.98 0 20.27 13.72 33.98 13.71 13.72 33.98 13.72Zm0 496.92ZM207.69-480Zm464.62-272.31Z"/>';
						$html .= '</svg>';
						$html .= '</button>';
					}

					$html .= '</div>';
					$html .= '</div>';
				}
			}
			$html .= '</div>';
		} else {
			$html .= '<div class="no-images-container">';
			$html .= '<p class="no-images">No images available for this case.</p>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</div>';

		// Details section - now below images in a card layout
		$html .= '<div class="brag-book-gallery-case-details-section">';
		$html .= '<div class="case-details-grid">';

		// Procedures performed card
		if ( ! empty( $case_data['procedures'] ) && is_array( $case_data['procedures'] ) ) {
			$html .= '<div class="case-detail-card procedures-performed-card">';
			$html .= '<div class="card-header">';
			$html .= '<h3 class="card-title">Procedures Performed</h3>';
			$html .= '</div>';
			$html .= '<div class="card-content">';
			$html .= '<div class="procedures-badges-list">';
			foreach ( $case_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$html .= '<span class="procedure-badge">' . esc_html( $procedure['name'] ) . '</span>';
				}
			}
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		// Patient details card
		$html .= '<div class="case-detail-card patient-details-card">';
		$html .= '<div class="card-header">';
		$html .= '<h3 class="card-title">Patient Information</h3>';
		$html .= '</div>';
		$html .= '<div class="card-content">';
		$html .= '<div class="patient-info-grid">';

		// Ethnicity
		if ( ! empty( $case_data['ethnicity'] ) ) {
			$html .= '<div class="info-item">';
			$html .= '<span class="info-label">Ethnicity</span>';
			$html .= '<span class="info-value">' . esc_html( $case_data['ethnicity'] ) . '</span>';
			$html .= '</div>';
		}

		// Gender
		if ( ! empty( $case_data['gender'] ) ) {
			$html .= '<div class="info-item">';
			$html .= '<span class="info-label">Gender</span>';
			$html .= '<span class="info-value">' . esc_html( ucfirst( $case_data['gender'] ) ) . '</span>';
			$html .= '</div>';
		}

		// Age
		if ( ! empty( $case_data['age'] ) ) {
			$html .= '<div class="info-item">';
			$html .= '<span class="info-label">Age</span>';
			$html .= '<span class="info-value">' . esc_html( $case_data['age'] ) . ' years</span>';
			$html .= '</div>';
		}

		// Height
		if ( ! empty( $case_data['height'] ) ) {
			$html .= '<div class="info-item">';
			$html .= '<span class="info-label">Height</span>';
			$html .= '<span class="info-value">' . esc_html( $case_data['height'] ) . '</span>';
			$html .= '</div>';
		}

		// Weight
		if ( ! empty( $case_data['weight'] ) ) {
			$html .= '<div class="info-item">';
			$html .= '<span class="info-label">Weight</span>';
			$html .= '<span class="info-value">' . esc_html( $case_data['weight'] ) . ' lbs</span>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Procedure details card
		if ( ! empty( $case_data['procedureDetails'] ) && is_array( $case_data['procedureDetails'] ) ) {
			$html .= '<div class="case-detail-card procedure-details-card">';
			$html .= '<div class="card-header">';
			$html .= '<h3 class="card-title">Procedure Details</h3>';
			$html .= '</div>';
			$html .= '<div class="card-content">';

			// Iterate through each procedure's details
			foreach ( $case_data['procedureDetails'] as $procedure_id => $details ) {
				if ( is_array( $details ) && ! empty( $details ) ) {
					$html .= '<div class="procedure-details-grid">';
					foreach ( $details as $label => $value ) {
						if ( ! empty( $value ) ) {
							$html .= '<div class="info-item">';
							$html .= '<span class="info-label">' . esc_html( $label ) . '</span>';
							$html .= '<span class="info-value">' . esc_html( $value ) . '</span>';
							$html .= '</div>';
						}
					}
					$html .= '</div>';
				}
			}

			$html .= '</div>';
			$html .= '</div>';
		}

		// Case details card
		if ( ! empty( $case_data['details'] ) ) {
			$html .= '<div class="case-detail-card case-notes-card">';
			$html .= '<div class="card-header">';
			$html .= '<h3 class="card-title">Case Notes</h3>';
			$html .= '</div>';
			$html .= '<div class="card-content">';
			$html .= '<div class="case-details-content">';
			$html .= wp_kses_post( $case_data['details'] );
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>'; // End case-details-grid
		$html .= '</div>'; // End case-details-section
		$html .= '</div>'; // End case-content
		$html .= '</div>'; // End main container

		return $html;
	}

	/**
	 * AJAX handler for clearing gallery cache.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_clear_cache(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_clear_cache' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		global $wpdb;

		// Clear all BRAG Book Gallery transients
		$query = "
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '%transient_brag_book_cases_%'
			OR option_name LIKE '%transient_timeout_brag_book_cases_%'
		";

		$deleted = $wpdb->query( $query );

		if ( $deleted !== false ) {
			$count = $deleted / 2; // Each transient has a timeout entry too

			// Also clear object cache if available
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}

			wp_send_json_success( sprintf( 'Cleared %d cache entries', $count ) );
		} else {
			wp_send_json_error( 'Failed to clear cache' );
		}
	}

	/**
	 * AJAX handler for loading more cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_more_cases(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'brag-book-gallery' ),
			] );
		}

		// Get parameters
		$start_page = isset( $_POST['start_page'] ) ? intval( $_POST['start_page'] ) : 6;
		$procedure_ids = isset( $_POST['procedure_ids'] ) ? array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['procedure_ids'] ) ) ) ) : [];
		$has_nudity = isset( $_POST['has_nudity'] ) && $_POST['has_nudity'] === '1';

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
		}

		try {
			$endpoints = new Endpoints();
			$all_cases = [];
			$cases_per_page = 10;
			$max_pages_to_load = 10; // Load up to 10 more pages (100 cases)

			// Prepare base filter body
			$filter_body = [
				'apiTokens'          => [ $api_tokens[0] ],
				'websitePropertyIds' => [ intval( $website_property_ids[0] ) ],
				'procedureIds'       => $procedure_ids,
			];

			// Fetch additional pages (only load 1 page at a time for Load More)
			$has_more = false;
			$pages_to_load = 1; // Load 1 page per click

			for ( $i = 0; $i < $pages_to_load; $i++ ) {
				$current_page = $start_page + $i;
				$filter_body['count'] = $current_page;

				$response = $endpoints->bb_get_pagination_data( $filter_body );

				if ( ! empty( $response ) ) {
					$page_data = json_decode( $response, true );

					if ( is_array( $page_data ) && ! empty( $page_data['data'] ) ) {
						$new_cases_count = count( $page_data['data'] );
						$all_cases = array_merge( $all_cases, $page_data['data'] );

						// If we got a full page, there might be more
						if ( $new_cases_count == $cases_per_page ) {
							// Check if there's another page
							$next_page = $current_page + 1;
							$filter_body['count'] = $next_page;
							$check_response = $endpoints->bb_get_pagination_data( $filter_body );
							if ( ! empty( $check_response ) ) {
								$check_data = json_decode( $check_response, true );
								if ( is_array( $check_data ) && ! empty( $check_data['data'] ) ) {
									$has_more = true;
								}
							}
						}

						// If we got less than a full page, we've reached the end
						if ( $new_cases_count < $cases_per_page ) {
							$has_more = false;
							break;
						}
					} else {
						break; // No more data
					}
				} else {
					break; // Failed to load
				}
			}

			// Generate HTML for the new cases - use AJAX gallery card format
			$html = '';

			// Get image display mode setting
			$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

			error_log( 'Load More: Loading ' . count( $all_cases ) . ' cases starting from page ' . $start_page );

			foreach ( $all_cases as $case ) {
				$html .= self::render_ajax_gallery_case_card( $case, $image_display_mode, $has_nudity );
			}

			error_log( 'Generated HTML length: ' . strlen( $html ) );

			wp_send_json_success( [
				'html' => $html,
				'casesLoaded' => count( $all_cases ),
				'hasMore' => $has_more,
				'debug' => [
					'casesCount' => count( $all_cases ),
					'startPage' => $start_page,
					'htmlLength' => strlen( $html ),
					'hasMore' => $has_more,
				]
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load more cases.', 'brag-book-gallery' ),
			] );
		}
	}

	/**
	 * AJAX handler to load specific filtered cases.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function ajax_load_filtered_cases(): void {
		// Get case IDs from request
		$case_ids_str = isset( $_POST['case_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['case_ids'] ) ) : '';

		if ( empty( $case_ids_str ) ) {
			wp_send_json_error( [
				'message' => __( 'No case IDs provided.', 'brag-book-gallery' ),
			] );
			return;
		}

		// Convert to array of IDs
		$case_ids = array_map( 'trim', explode( ',', $case_ids_str ) );

		// Get API credentials
		$api_token = get_option( 'brag_book_gallery_api_token', '' );
		$website_property_id = get_option( 'brag_book_gallery_website_property_id', '' );

		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			wp_send_json_error( [
				'message' => __( 'API configuration missing.', 'brag-book-gallery' ),
			] );
			return;
		}

		try {
			// Get the cases data we already have cached
			$cache_key = 'brag_book_cases_' . md5( $api_token . $website_property_id );
			$cached_data = get_transient( $cache_key );

			$html = '';
			$cases_found = 0;

			// Check for nudity in procedures
			$procedure_nudity = false;
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			if ( ! empty( $api_tokens[0] ) ) {
				$sidebar_data = self::get_sidebar_data( $api_tokens[0] );
				if ( ! empty( $sidebar_data['data'] ) ) {
					// We'll check each case's procedure for nudity
					$procedure_nudity_map = [];
					foreach ( $sidebar_data['data'] as $category ) {
						if ( ! empty( $category['procedures'] ) ) {
							foreach ( $category['procedures'] as $procedure ) {
								if ( ! empty( $procedure['nudity'] ) ) {
									$procedure_nudity_map[ $procedure['name'] ] = filter_var( $procedure['nudity'], FILTER_VALIDATE_BOOLEAN );
								}
							}
						}
					}
				}
			}

			if ( $cached_data && isset( $cached_data['data'] ) ) {
				// Look for the requested cases in our cached data
				foreach ( $cached_data['data'] as $case ) {
					if ( in_array( $case['id'], $case_ids ) ) {
						// Get image display mode
						$image_display_mode = get_option( 'brag_book_gallery_image_display_mode', 'single' );

						// Check if this case's procedure has nudity
						$case_nudity = false;
						if ( ! empty( $case['technique'] ) && isset( $procedure_nudity_map[ $case['technique'] ] ) ) {
							$case_nudity = $procedure_nudity_map[ $case['technique'] ];
						}

						// Render the case card
						$html .= self::render_ajax_gallery_case_card( $case, $image_display_mode, $case_nudity );
						$cases_found++;
					}
				}
			}

			// If we didn't find all cases in cache, we might need to fetch them
			// For now, we'll just return what we found

			wp_send_json_success( [
				'html' => $html,
				'casesFound' => $cases_found,
				'requestedCount' => count( $case_ids ),
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load filtered cases.', 'brag-book-gallery' ),
			] );
		}
	}
}
