<?php
declare( strict_types=1 );

/**
 * Enterprise URL Router for BRAGBook Gallery Plugin
 *
 * Comprehensive URL routing system implementing WordPress VIP standards with
 * PHP 8.2+ optimizations. Manages URL generation, routing, and redirects for
 * both JavaScript and Local operational modes with security-first approach.
 *
 * Key Features:
 * - Multi-mode URL routing (JavaScript/Local)
 * - Dynamic rewrite rule generation
 * - Legacy URL redirect handling
 * - SEO-friendly URL structures
 * - Breadcrumb trail generation
 * - Performance-optimized routing
 * - Security-hardened URL validation
 *
 * Architecture:
 * - Mode-aware routing strategies
 * - Virtual URL support for JavaScript mode
 * - Native WordPress routing for Local mode
 * - Intelligent redirect management
 * - Query variable registration
 * - Post type link filtering
 *
 * Routing Patterns:
 * - Gallery index: /gallery/
 * - Category view: /gallery/category-name/
 * - Case detail: /gallery/category/case-name/
 * - Search results: /gallery/search/term/
 * - Pagination: /gallery/page/2/
 *
 * Security Features:
 * - Input sanitization for all URLs
 * - XSS prevention through escaping
 * - SQL injection prevention
 * - Safe redirect handling
 * - Validated query parameters
 *
 * Performance Optimizations:
 * - Cached rewrite rules
 * - Efficient query parsing
 * - Minimal database queries
 * - Optimized redirect logic
 * - Lazy loading of dependencies
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\Mode\Mode_Manager;
use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access denied.' );
}

/**
 * URL Router Class
 *
 * Enterprise-grade URL routing system for multi-mode gallery operations.
 * Implements comprehensive routing strategies with security hardening and
 * performance optimization for both JavaScript and Local modes.
 *
 * Core Functionality:
 * - Dynamic rewrite rule registration
 * - Mode-specific URL generation
 * - Legacy URL redirect handling
 * - Query variable management
 * - Breadcrumb trail generation
 * - Search URL construction
 *
 * Technical Implementation:
 * - PHP 8.2+ type safety and features
 * - WordPress VIP coding standards
 * - Security-first URL validation
 * - Performance-optimized queries
 * - Comprehensive error handling
 * - Mode-aware routing logic
 *
 * @since 3.0.0
 */
final class URL_Router {

	/**
	 * Mode manager instance for operational mode detection.
	 *
	 * @since 3.0.0
	 * @var Mode_Manager|null
	 */
	private ?Mode_Manager $mode_manager = null;

	/**
	 * Cached rewrite rules for performance optimization.
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $rewrite_rules = [];

	/**
	 * Gallery base slug cache.
	 *
	 * @since 3.0.0
	 * @var string|null
	 */
	private ?string $gallery_base_cache = null;

	/**
	 * Constructor.
	 *
	 * Initializes mode manager and registers all routing hooks.
	 * Uses lazy loading for performance optimization.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		if ( class_exists( Mode_Manager::class ) ) {
			$this->mode_manager = Mode_Manager::get_instance();
		}
		$this->init();
	}

	/**
	 * Initialize URL router with comprehensive hook registration.
	 *
	 * Registers all WordPress hooks for URL routing, including rewrite rules,
	 * query variables, URL filtering, and legacy redirect handling.
	 *
	 * Hook Registration:
	 * - init: Register rewrite rules
	 * - query_vars: Add custom query variables
	 * - parse_request: Process incoming requests
	 * - post_type_link: Filter post URLs
	 * - term_link: Filter taxonomy term URLs
	 * - template_redirect: Handle legacy redirects
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Register rewrite rules with proper priority
		add_action( 'init', [ $this, 'add_rewrite_rules' ], 10 );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ], 10, 1 );
		add_action( 'parse_request', [ $this, 'parse_request' ], 10, 1 );

		// Configure URL generation filters
		add_filter( 'post_type_link', [ $this, 'filter_post_type_link' ], 10, 3 );
		add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 3 );

		// Legacy URL handling with early priority
		add_action( 'template_redirect', [ $this, 'handle_legacy_urls' ], 1 );

		// Mode switch handling
		add_action( 'brag_book_gallery_post_mode_switch', [ $this, 'flush_rewrite_rules' ], 10 );
	}

	/**
	 * Add rewrite rules based on current operational mode.
	 *
	 * Delegates rule registration to mode-specific methods. JavaScript mode
	 * rules are handled externally to avoid query variable conflicts.
	 *
	 * Mode Behavior:
	 * - JavaScript: Rules handled by Shortcodes class
	 * - Local: Native WordPress post type rules
	 * - Default: Falls back to Local mode rules
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		if ( ! $this->mode_manager ) {
			return;
		}

		// Determine mode and apply appropriate rules
		$is_javascript_mode = $this->mode_manager->is_javascript_mode();

		if ( $is_javascript_mode ) {
			// JavaScript mode rules delegated to avoid conflicts
			return;
		}

		$this->add_local_mode_rules();
	}

	/**
	 * Add rewrite rules for JavaScript mode.
	 *
	 * Registers comprehensive URL patterns for virtual gallery pages in
	 * JavaScript mode. Supports categories, cases, search, and pagination.
	 *
	 * URL Patterns:
	 * - Index: /gallery/
	 * - Category: /gallery/category-slug/
	 * - Case: /gallery/category/case-slug/
	 * - Search: /gallery/search/term/
	 * - Pagination: /gallery/page/2/
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function add_javascript_mode_rules(): void {
		$gallery_base = $this->get_gallery_base();

		if ( empty( $gallery_base ) ) {
			return;
		}

		// Escape base for regex safety
		$escaped_base = preg_quote( $gallery_base, '/' );

		// Main gallery index
		add_rewrite_rule(
			"^{$escaped_base}/?$",
			'index.php?brag_book_gallery_view=index',
			'top'
		);

		// Category/Procedure view with sanitized slug
		add_rewrite_rule(
			"^{$escaped_base}/([^/]+)/?$",
			'index.php?brag_book_gallery_view=category&brag_gallery_slug=$matches[1]',
			'top'
		);

		// Individual case with category context
		add_rewrite_rule(
			"^{$escaped_base}/([^/]+)/([^/]+)/?$",
			'index.php?brag_book_gallery_view=single&brag_gallery_category=$matches[1]&brag_book_gallery_cae=$matches[2]',
			'top'
		);

		// Search functionality
		add_rewrite_rule(
			"^{$escaped_base}/search/([^/]+)/?$",
			'index.php?brag_book_gallery_view=search&brag_gallery_search=$matches[1]',
			'top'
		);

		// Pagination for index
		add_rewrite_rule(
			"^{$escaped_base}/page/([0-9]+)/?$",
			'index.php?brag_book_gallery_view=index&brag_gallery_page=$matches[1]',
			'top'
		);

		// Pagination for categories
		add_rewrite_rule(
			"^{$escaped_base}/([^/]+)/page/([0-9]+)/?$",
			'index.php?brag_book_gallery_view=category&brag_gallery_slug=$matches[1]&brag_gallery_page=$matches[2]',
			'top'
		);
	}

	/**
	 * Add rewrite rules for Local mode.
	 *
	 * Leverages WordPress native rewrite rules for post types and taxonomies.
	 * Adds custom rules for advanced filtering and search functionality.
	 *
	 * Rule Strategy:
	 * - Native rules: Handled by post type registration
	 * - Custom rules: Multi-taxonomy filtering and search
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function add_local_mode_rules(): void {
		// Native rules handled by WordPress
		// Add custom enhancement rules
		$this->add_custom_local_rules();
	}

	/**
	 * Add custom rewrite rules for Local mode enhancements.
	 *
	 * Provides advanced URL patterns for multi-taxonomy filtering and
	 * specialized search functionality in Local mode.
	 *
	 * Custom Patterns:
	 * - Multi-taxonomy: /gallery/category/cat-slug/procedure/proc-slug/
	 * - Search: /gallery/search/search-term/
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function add_custom_local_rules(): void {
		if ( ! class_exists( Gallery_Post_Type::class ) ) {
			return;
		}

		$post_type = Gallery_Post_Type::POST_TYPE;

		// Multi-taxonomy filtering rule
		add_rewrite_rule(
			'^gallery/category/([^/]+)/procedure/([^/]+)/?$',
			'index.php?post_type=' . esc_attr( $post_type ) . '&brag_category=$matches[1]&brag_procedure=$matches[2]',
			'top'
		);

		// Enhanced search rule
		add_rewrite_rule(
			'^gallery/search/([^/]+)/?$',
			'index.php?post_type=' . esc_attr( $post_type ) . '&s=$matches[1]',
			'top'
		);
	}

	/**
	 * Add custom query variables for gallery routing.
	 *
	 * Registers query variables used by rewrite rules for proper URL parsing
	 * and request handling. Essential for virtual URL support.
	 *
	 * Registered Variables:
	 * - brag_book_gallery_view: View type (index, category, single, search)
	 * - brag_gallery_slug: Category or procedure slug
	 * - brag_gallery_category: Category context for cases
	 * - brag_book_gallery_cae: Individual case identifier
	 * - brag_gallery_search: Search term
	 * - brag_gallery_page: Pagination number
	 * - brag_gallery_filter: Additional filters
	 *
	 * @since 3.0.0
	 * @param array<int, string> $vars Existing query variables.
	 * @return array<int, string> Modified query variables array.
	 */
	public function add_query_vars( array $vars ): array {
		$gallery_vars = [
			'brag_book_gallery_view',
			'brag_gallery_slug',
			'brag_gallery_category',
			'brag_book_gallery_cae',
			'brag_gallery_search',
			'brag_gallery_page',
			'brag_gallery_filter',
		];

		return array_merge( $vars, $gallery_vars );
	}

	/**
	 * Parse incoming requests for custom routing.
	 *
	 * Processes requests based on operational mode and sets appropriate
	 * query variables for template loading and content display.
	 *
	 * Processing Flow:
	 * 1. Check operational mode
	 * 2. Parse mode-specific query variables
	 * 3. Set internal routing flags
	 * 4. Prepare data for templates
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	public function parse_request( \WP $wp ): void {
		if ( ! $this->mode_manager ) {
			return;
		}

		// Process JavaScript mode virtual URLs
		if ( $this->mode_manager->is_javascript_mode() ) {
			$this->parse_javascript_mode_request( $wp );
		}
	}

	/**
	 * Parse JavaScript mode requests with view routing.
	 *
	 * Routes requests to appropriate handlers based on gallery view type.
	 * Uses PHP 8.2 match expression for efficient routing.
	 *
	 * View Types:
	 * - index: Main gallery listing
	 * - category: Category or procedure view
	 * - single: Individual case display
	 * - search: Search results page
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	private function parse_javascript_mode_request( \WP $wp ): void {
		if ( ! isset( $wp->query_vars['brag_book_gallery_view'] ) ) {
			return;
		}

		$view = sanitize_text_field( $wp->query_vars['brag_book_gallery_view'] );

		// Route to appropriate handler using PHP 8.2 match
		match ( $view ) {
			'index' => $this->handle_gallery_index( $wp ),
			'category' => $this->handle_gallery_category( $wp ),
			'single' => $this->handle_gallery_single( $wp ),
			'search' => $this->handle_gallery_search( $wp ),
			default => null,
		};
	}

	/**
	 * Handle gallery index page routing.
	 *
	 * Sets query variables for main gallery listing with pagination support.
	 * Validates and sanitizes pagination parameters.
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_index( \WP $wp ): void {
		$wp->query_vars['is_gallery_index'] = true;

		// Sanitize and validate pagination
		$page = isset( $wp->query_vars['brag_gallery_page'] )
			? absint( $wp->query_vars['brag_gallery_page'] )
			: 1;

		$wp->query_vars['gallery_page'] = max( 1, $page );
	}

	/**
	 * Handle gallery category page routing.
	 *
	 * Processes category or procedure views with slug validation and
	 * pagination support. Sanitizes all input parameters.
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_category( \WP $wp ): void {
		$slug = isset( $wp->query_vars['brag_gallery_slug'] )
			? sanitize_title( $wp->query_vars['brag_gallery_slug'] )
			: '';

		if ( ! empty( $slug ) ) {
			$wp->query_vars['is_gallery_category'] = true;
			$wp->query_vars['gallery_category_slug'] = $slug;

			// Validate pagination
			$page = isset( $wp->query_vars['brag_gallery_page'] )
				? absint( $wp->query_vars['brag_gallery_page'] )
				: 1;

			$wp->query_vars['gallery_page'] = max( 1, $page );
		}
	}

	/**
	 * Handle gallery single case page routing.
	 *
	 * Processes individual case display with category context.
	 * Validates both category and case slugs for security.
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_single( \WP $wp ): void {
		$category = isset( $wp->query_vars['brag_gallery_category'] )
			? sanitize_title( $wp->query_vars['brag_gallery_category'] )
			: '';

		$case = isset( $wp->query_vars['brag_book_gallery_cae'] )
			? sanitize_title( $wp->query_vars['brag_book_gallery_cae'] )
			: '';

		if ( ! empty( $category ) && ! empty( $case ) ) {
			$wp->query_vars['is_gallery_single'] = true;
			$wp->query_vars['gallery_category_slug'] = $category;
			$wp->query_vars['gallery_case_slug'] = $case;
		}
	}

	/**
	 * Handle gallery search page routing.
	 *
	 * Processes search requests with proper term sanitization and
	 * URL decoding for accurate search functionality.
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_search( \WP $wp ): void {
		$search = isset( $wp->query_vars['brag_gallery_search'] )
			? sanitize_text_field( $wp->query_vars['brag_gallery_search'] )
			: '';

		if ( ! empty( $search ) ) {
			$wp->query_vars['is_gallery_search'] = true;
			$wp->query_vars['gallery_search_term'] = urldecode( $search );
		}
	}

	/**
	 * Generate URL for gallery item.
	 *
	 * Central URL generation method supporting multiple item types and
	 * operational modes. Uses PHP 8.2 match expression for routing.
	 *
	 * Supported Types:
	 * - post: Individual gallery case
	 * - category: Category archive page
	 * - procedure: Procedure archive page
	 *
	 * Mode Behavior:
	 * - JavaScript: Virtual SEO-friendly URLs
	 * - Local: Native WordPress permalinks
	 *
	 * @since 3.0.0
	 * @param int $id Item identifier (post ID or term ID).
	 * @param string $type Item type (post, category, procedure).
	 * @param string $mode Optional mode override (javascript, local).
	 * @return string Generated URL or home URL on failure.
	 */
	public function generate_url( int $id, string $type, string $mode = '' ): string {
		// Validate ID
		if ( $id <= 0 ) {
			return home_url();
		}

		// Determine operational mode
		$mode = ! empty( $mode ) ? $mode : ( $this->mode_manager?->get_current_mode() ?? 'local' );

		// Generate URL based on type using PHP 8.2 match
		return match ( $type ) {
			'post' => $this->generate_post_url( $id, $mode ),
			'category' => $this->generate_category_url( $id, $mode ),
			'procedure' => $this->generate_procedure_url( $id, $mode ),
			default => home_url(),
		};
	}

	/**
	 * Generate post URL based on operational mode.
	 *
	 * Creates appropriate URL for gallery posts, using native permalinks
	 * in Local mode or virtual URLs in JavaScript mode.
	 *
	 * URL Formats:
	 * - Local: WordPress permalink structure
	 * - JavaScript: /gallery/category-slug/post-slug/
	 *
	 * @since 3.0.0
	 * @param int $post_id WordPress post ID.
	 * @param string $mode Operational mode (local or javascript).
	 * @return string Generated post URL.
	 */
	private function generate_post_url( int $post_id, string $mode ): string {
		// Use native permalinks for Local mode
		if ( $mode === 'local' ) {
			$permalink = get_permalink( $post_id );
			return is_string( $permalink ) ? $permalink : home_url();
		}

		// Generate virtual URL for JavaScript mode
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return home_url();
		}

		// Get primary category with validation
		if ( class_exists( Gallery_Taxonomies::class ) ) {
			$categories = get_the_terms( $post_id, Gallery_Taxonomies::CATEGORY_TAXONOMY );
			$category_slug = match ( true ) {
				is_array( $categories ) && ! empty( $categories[0]->slug ) => $categories[0]->slug,
				default => 'uncategorized',
			};
		} else {
			$category_slug = 'uncategorized';
		}

		$gallery_base = $this->get_gallery_base();
		$post_name = ! empty( $post->post_name ) ? $post->post_name : 'case-' . $post_id;

		return home_url( "/{$gallery_base}/{$category_slug}/{$post_name}/" );
	}

	/**
	 * Generate category URL based on operational mode.
	 *
	 * Creates appropriate URL for gallery categories, using native term
	 * links in Local mode or virtual URLs in JavaScript mode.
	 *
	 * URL Formats:
	 * - Local: WordPress term permalink
	 * - JavaScript: /gallery/category-slug/
	 *
	 * @since 3.0.0
	 * @param int $term_id WordPress term ID.
	 * @param string $mode Operational mode (local or javascript).
	 * @return string Generated category URL.
	 */
	private function generate_category_url( int $term_id, string $mode ): string {
		// Validate taxonomy class availability
		if ( ! class_exists( Gallery_Taxonomies::class ) ) {
			return home_url();
		}

		// Use native term links for Local mode
		if ( $mode === 'local' ) {
			$term_link = get_term_link( $term_id, Gallery_Taxonomies::CATEGORY_TAXONOMY );
			return is_string( $term_link ) ? $term_link : home_url();
		}

		// Generate virtual URL for JavaScript mode
		$term = get_term( $term_id, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		if ( ! $term instanceof \WP_Term ) {
			return home_url();
		}

		$gallery_base = $this->get_gallery_base();
		$term_slug = ! empty( $term->slug ) ? $term->slug : 'category-' . $term_id;

		return home_url( "/{$gallery_base}/{$term_slug}/" );
	}

	/**
	 * Generate procedure URL based on operational mode.
	 *
	 * Creates appropriate URL for gallery procedures, using native term
	 * links in Local mode or virtual URLs in JavaScript mode.
	 *
	 * URL Formats:
	 * - Local: WordPress term permalink
	 * - JavaScript: /gallery/procedure-slug/
	 *
	 * @since 3.0.0
	 * @param int $term_id WordPress term ID.
	 * @param string $mode Operational mode (local or javascript).
	 * @return string Generated procedure URL.
	 */
	private function generate_procedure_url( int $term_id, string $mode ): string {
		// Validate taxonomy class availability
		if ( ! class_exists( Gallery_Taxonomies::class ) ) {
			return home_url();
		}

		// Use native term links for Local mode
		if ( $mode === 'local' ) {
			$term_link = get_term_link( $term_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
			return is_string( $term_link ) ? $term_link : home_url();
		}

		// Generate virtual URL for JavaScript mode
		$term = get_term( $term_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		if ( ! $term instanceof \WP_Term ) {
			return home_url();
		}

		$gallery_base = $this->get_gallery_base();
		$term_slug = ! empty( $term->slug ) ? $term->slug : 'procedure-' . $term_id;

		return home_url( "/{$gallery_base}/{$term_slug}/" );
	}

	/**
	 * Filter post type links for mode-specific URLs.
	 *
	 * Modifies gallery post URLs based on operational mode, converting
	 * native permalinks to virtual URLs in JavaScript mode.
	 *
	 * Filter Behavior:
	 * - Local mode: Returns unmodified permalinks
	 * - JavaScript mode: Generates virtual URLs
	 * - Other post types: Pass through unchanged
	 *
	 * @since 3.0.0
	 * @param string $post_link Original post URL.
	 * @param \WP_Post $post WordPress post object.
	 * @param bool $leavename Whether to keep post name placeholder.
	 * @return string Filtered post URL.
	 */
	public function filter_post_type_link( string $post_link, \WP_Post $post, bool $leavename ): string {
		// Only filter gallery post type
		if ( ! class_exists( Gallery_Post_Type::class ) ) {
			return $post_link;
		}

		if ( $post->post_type !== Gallery_Post_Type::POST_TYPE ) {
			return $post_link;
		}

		// Generate virtual URL for JavaScript mode
		if ( $this->mode_manager && $this->mode_manager->is_javascript_mode() ) {
			return $this->generate_post_url( $post->ID, 'javascript' );
		}

		return $post_link;
	}

	/**
	 * Filter term links for mode-specific URLs.
	 *
	 * Modifies gallery taxonomy term URLs based on operational mode,
	 * converting native term links to virtual URLs in JavaScript mode.
	 *
	 * Filter Behavior:
	 * - Local mode: Returns unmodified term links
	 * - JavaScript mode: Generates virtual URLs
	 * - Other taxonomies: Pass through unchanged
	 *
	 * @since 3.0.0
	 * @param string $termlink Original term URL.
	 * @param \WP_Term $term WordPress term object.
	 * @param string $taxonomy Taxonomy name.
	 * @return string Filtered term URL.
	 */
	public function filter_term_link( string $termlink, \WP_Term $term, string $taxonomy ): string {
		// Validate taxonomy class availability
		if ( ! class_exists( Gallery_Taxonomies::class ) ) {
			return $termlink;
		}

		// Check if taxonomy is gallery-related
		$gallery_taxonomies = [
			Gallery_Taxonomies::CATEGORY_TAXONOMY,
			Gallery_Taxonomies::PROCEDURE_TAXONOMY,
		];

		if ( ! in_array( $taxonomy, $gallery_taxonomies, true ) ) {
			return $termlink;
		}

		// Generate virtual URL for JavaScript mode
		if ( $this->mode_manager && $this->mode_manager->is_javascript_mode() ) {
			$type = match ( $taxonomy ) {
				Gallery_Taxonomies::CATEGORY_TAXONOMY => 'category',
				Gallery_Taxonomies::PROCEDURE_TAXONOMY => 'procedure',
				default => 'category',
			};

			return $this->generate_url( $term->term_id, $type, 'javascript' );
		}

		return $termlink;
	}

	/**
	 * Handle legacy URL redirects for mode transitions.
	 *
	 * Provides seamless redirects when switching between operational modes,
	 * ensuring URLs remain functional and SEO-friendly.
	 *
	 * Redirect Strategy:
	 * - Local mode: Redirects JavaScript URLs to native permalinks
	 * - JavaScript mode: Redirects native URLs to virtual paths
	 * - Uses 301 permanent redirects for SEO preservation
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_legacy_urls(): void {
		if ( ! $this->mode_manager ) {
			return;
		}

		// Get and sanitize request URI
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used for comparison only
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( empty( $request_uri ) ) {
			return;
		}

		// Determine redirect based on current mode
		$redirect_url = $this->mode_manager->is_local_mode()
			? $this->handle_javascript_to_local_redirects( $request_uri )
			: $this->handle_local_to_javascript_redirects( $request_uri );

		// Perform redirect if URL changed
		if ( $redirect_url && $redirect_url !== $request_uri ) {
			wp_safe_redirect( $redirect_url, 301 );
			exit;
		}
	}

	/**
	 * Handle redirects from JavaScript mode URLs to Local mode URLs.
	 *
	 * Converts virtual gallery URLs to native WordPress permalinks when
	 * switching from JavaScript to Local mode.
	 *
	 * Redirect Mapping:
	 * - /gallery/ → Post type archive
	 * - /gallery/slug/ → Category or procedure term page
	 * - /gallery/cat/case/ → Individual post page
	 *
	 * @since 3.0.0
	 * @param string $request_uri Current request URI.
	 * @return string|null Redirect URL or null if no redirect needed.
	 */
	private function handle_javascript_to_local_redirects( string $request_uri ): ?string {
		// Validate class dependencies
		if ( ! class_exists( Gallery_Post_Type::class ) || ! class_exists( Gallery_Taxonomies::class ) ) {
			return null;
		}

		$gallery_base = $this->get_gallery_base();
		$base_pattern = '/' . preg_quote( $gallery_base, '/' ) . '/';

		// Check if URI starts with gallery base
		if ( ! preg_match( '^' . $base_pattern, $request_uri ) ) {
			return null;
		}

		// Extract path components
		$path = preg_replace( '^' . $base_pattern, '', $request_uri );
		$path_parts = array_values( array_filter( explode( '/', trim( $path ?? '', '/' ) ) ) );

		// Route based on path depth using match expression
		return match ( count( $path_parts ) ) {
			0 => $this->get_local_archive_url(),
			1 => $this->get_local_term_url( $path_parts[0] ),
			2 => $this->get_local_post_url( $path_parts[1] ),
			default => null,
		};
	}

	/**
	 * Get Local mode archive URL.
	 *
	 * @since 3.0.0
	 * @return string|null Archive URL.
	 */
	private function get_local_archive_url(): ?string {
		$url = get_post_type_archive_link( Gallery_Post_Type::POST_TYPE );
		return is_string( $url ) ? $url : null;
	}

	/**
	 * Get Local mode term URL by slug.
	 *
	 * @since 3.0.0
	 * @param string $slug Term slug.
	 * @return string|null Term URL.
	 */
	private function get_local_term_url( string $slug ): ?string {
		// Try category first
		$category = get_term_by( 'slug', $slug, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		if ( $category instanceof \WP_Term ) {
			$url = get_term_link( $category );
			return is_string( $url ) ? $url : null;
		}

		// Try procedure
		$procedure = get_term_by( 'slug', $slug, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		if ( $procedure instanceof \WP_Term ) {
			$url = get_term_link( $procedure );
			return is_string( $url ) ? $url : null;
		}

		return null;
	}

	/**
	 * Get Local mode post URL by slug.
	 *
	 * @since 3.0.0
	 * @param string $slug Post slug.
	 * @return string|null Post URL.
	 */
	private function get_local_post_url( string $slug ): ?string {
		$posts = get_posts( [
			'post_type'   => Gallery_Post_Type::POST_TYPE,
			'name'        => $slug,
			'post_status' => 'publish',
			'numberposts' => 1,
		] );

		if ( ! empty( $posts[0] ) && $posts[0] instanceof \WP_Post ) {
			$url = get_permalink( $posts[0] );
			return is_string( $url ) ? $url : null;
		}

		return null;
	}

	/**
	 * Handle redirects from Local mode URLs to JavaScript mode URLs.
	 *
	 * Converts native WordPress permalinks to virtual gallery URLs when
	 * switching from Local to JavaScript mode.
	 *
	 * Redirect Mapping:
	 * - Post type archive → /gallery/
	 * - Category term → /gallery/category-slug/
	 * - Procedure term → /gallery/procedure-slug/
	 * - Single post → /gallery/category/post-slug/
	 *
	 * @since 3.0.0
	 * @param string $request_uri Current request URI (unused but kept for consistency).
	 * @return string|null Redirect URL or null if no redirect needed.
	 */
	private function handle_local_to_javascript_redirects( string $request_uri ): ?string {
		// Validate class dependencies
		if ( ! class_exists( Gallery_Post_Type::class ) || ! class_exists( Gallery_Taxonomies::class ) ) {
			return null;
		}

		// Check various WordPress conditional states
		return match ( true ) {
			is_singular( Gallery_Post_Type::POST_TYPE ) => $this->get_javascript_post_url(),
			is_post_type_archive( Gallery_Post_Type::POST_TYPE ) => $this->get_javascript_archive_url(),
			is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) => $this->get_javascript_category_url(),
			is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) => $this->get_javascript_procedure_url(),
			default => null,
		};
	}

	/**
	 * Get JavaScript mode URL for current post.
	 *
	 * @since 3.0.0
	 * @return string|null Post URL.
	 */
	private function get_javascript_post_url(): ?string {
		$post = get_queried_object();
		if ( $post instanceof \WP_Post ) {
			return $this->generate_post_url( $post->ID, 'javascript' );
		}
		return null;
	}

	/**
	 * Get JavaScript mode archive URL.
	 *
	 * @since 3.0.0
	 * @return string Archive URL.
	 */
	private function get_javascript_archive_url(): string {
		$gallery_base = $this->get_gallery_base();
		return home_url( "/{$gallery_base}/" );
	}

	/**
	 * Get JavaScript mode URL for current category.
	 *
	 * @since 3.0.0
	 * @return string|null Category URL.
	 */
	private function get_javascript_category_url(): ?string {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			return $this->generate_category_url( $term->term_id, 'javascript' );
		}
		return null;
	}

	/**
	 * Get JavaScript mode URL for current procedure.
	 *
	 * @since 3.0.0
	 * @return string|null Procedure URL.
	 */
	private function get_javascript_procedure_url(): ?string {
		$term = get_queried_object();
		if ( $term instanceof \WP_Term ) {
			return $this->generate_procedure_url( $term->term_id, 'javascript' );
		}
		return null;
	}

	/**
	 * Get gallery base slug with caching.
	 *
	 * Retrieves the gallery base URL slug from options with fallback
	 * to default. Uses internal caching for performance.
	 *
	 * Priority Order:
	 * 1. Cached value (if available)
	 * 2. brag_book_gallery_page_slug option
	 * 3. brag_book_gallery_base_slug option
	 * 4. Default 'gallery' fallback
	 *
	 * @since 3.0.0
	 * @return string Sanitized gallery base slug.
	 */
	private function get_gallery_base(): string {
		// Return cached value if available
		if ( $this->gallery_base_cache !== null ) {
			return $this->gallery_base_cache;
		}

		// Try primary option
		$base = get_option( 'brag_book_gallery_page_slug', '' );

		// Fall back to secondary option or default
		if ( empty( $base ) ) {
			$base = get_option( 'brag_book_gallery_base_slug', 'gallery' );
		}

		// Sanitize and cache
		$this->gallery_base_cache = sanitize_title( trim( (string) $base, '/' ) ) ?: 'gallery';

		return $this->gallery_base_cache;
	}

	/**
	 * Get current request path information.
	 *
	 * Analyzes the current request to determine gallery context, including
	 * operational mode, content type, and associated objects.
	 *
	 * Information Provided:
	 * - is_gallery_request: Whether request is gallery-related
	 * - mode: Current operational mode (javascript/local)
	 * - type: Content type (single, archive, category, procedure)
	 * - object_id: Associated object ID (post or term)
	 * - object: WordPress object (WP_Post or WP_Term)
	 *
	 * @since 3.0.0
	 * @return array{is_gallery_request: bool, mode: string, type: string, object_id: int, object: \WP_Post|\WP_Term|null} Path information array.
	 */
	public function get_current_path_info(): array {
		global $wp_query;

		// Initialize default info structure
		$info = [
			'is_gallery_request' => false,
			'mode'               => $this->mode_manager?->get_current_mode() ?? 'local',
			'type'               => '',
			'object_id'          => 0,
			'object'             => null,
		];

		// Check if classes exist
		if ( ! class_exists( Gallery_Post_Type::class ) || ! class_exists( Gallery_Taxonomies::class ) ) {
			return $info;
		}

		// Detect gallery context using match expression
		$queried_object = get_queried_object();

		return match ( true ) {
			is_singular( Gallery_Post_Type::POST_TYPE ) && $queried_object instanceof \WP_Post => array_merge( $info, [
				'is_gallery_request' => true,
				'type'               => 'single',
				'object_id'          => $queried_object->ID,
				'object'             => $queried_object,
			] ),
			is_post_type_archive( Gallery_Post_Type::POST_TYPE ) => array_merge( $info, [
				'is_gallery_request' => true,
				'type'               => 'archive',
			] ),
			is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) && $queried_object instanceof \WP_Term => array_merge( $info, [
				'is_gallery_request' => true,
				'type'               => 'category',
				'object_id'          => $queried_object->term_id,
				'object'             => $queried_object,
			] ),
			is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) && $queried_object instanceof \WP_Term => array_merge( $info, [
				'is_gallery_request' => true,
				'type'               => 'procedure',
				'object_id'          => $queried_object->term_id,
				'object'             => $queried_object,
			] ),
			isset( $wp_query->query_vars['brag_book_gallery_view'] ) => array_merge( $info, [
				'is_gallery_request' => true,
				'type'               => sanitize_text_field( $wp_query->query_vars['brag_book_gallery_view'] ),
			] ),
			default => $info,
		};
	}

	/**
	 * Generate breadcrumb data for navigation.
	 *
	 * Creates hierarchical breadcrumb trail for gallery pages, supporting
	 * both operational modes and various content types.
	 *
	 * Breadcrumb Structure:
	 * - Home → Gallery → Category → Case
	 * - Home → Gallery → Procedure
	 * - Home → Gallery (for archive)
	 *
	 * Each Breadcrumb Contains:
	 * - title: Display text
	 * - url: Link destination
	 * - current: Whether this is current page
	 *
	 * @since 3.0.0
	 * @return array<int, array{title: string, url: string, current: bool}> Breadcrumb trail array.
	 */
	public function get_breadcrumbs(): array {
		$breadcrumbs = [];
		$path_info = $this->get_current_path_info();

		// Only generate breadcrumbs for gallery requests
		if ( ! $path_info['is_gallery_request'] ) {
			return $breadcrumbs;
		}

		// Add home breadcrumb
		$breadcrumbs[] = [
			'title'   => __( 'Home', 'brag-book-gallery' ),
			'url'     => home_url(),
			'current' => false,
		];

		// Add gallery root breadcrumb
		$gallery_url = $this->get_gallery_index_url();
		$breadcrumbs[] = [
			'title'   => __( 'Gallery', 'brag-book-gallery' ),
			'url'     => $gallery_url,
			'current' => $path_info['type'] === 'archive',
		];

		// Add type-specific breadcrumbs using match expression
		if ( $path_info['object'] !== null ) {
			match ( $path_info['type'] ) {
				'single' => $this->add_single_breadcrumbs( $breadcrumbs, $path_info['object'] ),
				'category' => $this->add_category_breadcrumbs( $breadcrumbs, $path_info['object'] ),
				'procedure' => $this->add_procedure_breadcrumbs( $breadcrumbs, $path_info['object'] ),
				default => null,
			};
		}

		return $breadcrumbs;
	}

	/**
	 * Add single post breadcrumbs to trail.
	 *
	 * Appends category and post breadcrumbs for single gallery case pages.
	 * Includes primary category if available for hierarchical navigation.
	 *
	 * @since 3.0.0
	 * @param array<int, array{title: string, url: string, current: bool}> $breadcrumbs Breadcrumbs array by reference.
	 * @param \WP_Post $post WordPress post object.
	 * @return void
	 */
	private function add_single_breadcrumbs( array &$breadcrumbs, \WP_Post $post ): void {
		// Add primary category if available
		if ( class_exists( Gallery_Taxonomies::class ) ) {
			$categories = get_the_terms( $post->ID, Gallery_Taxonomies::CATEGORY_TAXONOMY );

			if ( is_array( $categories ) && ! empty( $categories[0] ) && $categories[0] instanceof \WP_Term ) {
				$category = $categories[0];
				$mode = $this->mode_manager?->get_current_mode() ?? 'local';

				$breadcrumbs[] = [
					'title'   => esc_html( $category->name ),
					'url'     => $this->generate_category_url( $category->term_id, $mode ),
					'current' => false,
				];
			}
		}

		// Add current post
		$mode = $this->mode_manager?->get_current_mode() ?? 'local';
		$breadcrumbs[] = [
			'title'   => esc_html( get_the_title( $post ) ),
			'url'     => $this->generate_post_url( $post->ID, $mode ),
			'current' => true,
		];
	}

	/**
	 * Add category breadcrumbs to trail.
	 *
	 * Appends hierarchical category breadcrumbs including parent categories
	 * if the taxonomy supports hierarchy.
	 *
	 * @since 3.0.0
	 * @param array<int, array{title: string, url: string, current: bool}> $breadcrumbs Breadcrumbs array by reference.
	 * @param \WP_Term $term WordPress term object.
	 * @return void
	 */
	private function add_category_breadcrumbs( array &$breadcrumbs, \WP_Term $term ): void {
		if ( ! class_exists( Gallery_Taxonomies::class ) ) {
			return;
		}

		// Build parent hierarchy if term has parent
		if ( $term->parent > 0 ) {
			$parents = $this->get_term_parents( $term->parent, Gallery_Taxonomies::CATEGORY_TAXONOMY );
			$mode = $this->mode_manager?->get_current_mode() ?? 'local';

			// Add parent breadcrumbs
			foreach ( $parents as $parent ) {
				$breadcrumbs[] = [
					'title'   => esc_html( $parent->name ),
					'url'     => $this->generate_category_url( $parent->term_id, $mode ),
					'current' => false,
				];
			}
		}

		// Add current category
		$mode = $this->mode_manager?->get_current_mode() ?? 'local';
		$breadcrumbs[] = [
			'title'   => esc_html( $term->name ),
			'url'     => $this->generate_category_url( $term->term_id, $mode ),
			'current' => true,
		];
	}

	/**
	 * Get term parent hierarchy.
	 *
	 * Recursively builds array of parent terms in root-to-child order.
	 *
	 * @since 3.0.0
	 * @param int $parent_id Parent term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<\WP_Term> Parent terms in hierarchical order.
	 */
	private function get_term_parents( int $parent_id, string $taxonomy ): array {
		$parents = [];
		$parent = get_term( $parent_id, $taxonomy );

		while ( $parent instanceof \WP_Term ) {
			array_unshift( $parents, $parent );
			$parent = $parent->parent > 0 ? get_term( $parent->parent, $taxonomy ) : null;
		}

		return $parents;
	}

	/**
	 * Add procedure breadcrumbs to trail.
	 *
	 * Appends procedure term breadcrumb for procedure archive pages.
	 *
	 * @since 3.0.0
	 * @param array<int, array{title: string, url: string, current: bool}> $breadcrumbs Breadcrumbs array by reference.
	 * @param \WP_Term $term WordPress term object.
	 * @return void
	 */
	private function add_procedure_breadcrumbs( array &$breadcrumbs, \WP_Term $term ): void {
		$mode = $this->mode_manager?->get_current_mode() ?? 'local';

		// Add current procedure
		$breadcrumbs[] = [
			'title'   => esc_html( $term->name ),
			'url'     => $this->generate_procedure_url( $term->term_id, $mode ),
			'current' => true,
		];
	}

	/**
	 * Flush rewrite rules safely.
	 *
	 * Regenerates WordPress rewrite rules when operational mode changes
	 * or gallery settings are updated. Uses soft flush for performance.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function flush_rewrite_rules(): void {
		// Clear gallery base cache
		$this->gallery_base_cache = null;

		// Soft flush for performance
		flush_rewrite_rules( false );
	}

	/**
	 * Get URL for gallery index page.
	 *
	 * Returns the main gallery listing URL appropriate for the current
	 * operational mode.
	 *
	 * URL Formats:
	 * - Local: WordPress post type archive
	 * - JavaScript: /gallery/
	 *
	 * @since 3.0.0
	 * @return string Gallery index URL.
	 */
	public function get_gallery_index_url(): string {
		// Check Local mode with proper class validation
		if ( $this->mode_manager && $this->mode_manager->is_local_mode() ) {
			if ( class_exists( Gallery_Post_Type::class ) ) {
				$url = get_post_type_archive_link( Gallery_Post_Type::POST_TYPE );
				return is_string( $url ) ? $url : home_url();
			}
		}

		// JavaScript mode or fallback
		$gallery_base = $this->get_gallery_base();
		return home_url( "/{$gallery_base}/" );
	}

	/**
	 * Generate search URL for gallery content.
	 *
	 * Creates appropriate search URL based on operational mode,
	 * with proper encoding and sanitization.
	 *
	 * URL Formats:
	 * - Local: /?post_type=gallery&s=term
	 * - JavaScript: /gallery/search/term/
	 *
	 * @since 3.0.0
	 * @param string $search_term User search query.
	 * @return string Generated search URL.
	 */
	public function generate_search_url( string $search_term ): string {
		// Sanitize and encode search term
		$search_term = sanitize_text_field( $search_term );

		if ( empty( $search_term ) ) {
			return $this->get_gallery_index_url();
		}

		// Generate mode-specific search URL
		if ( $this->mode_manager && $this->mode_manager->is_local_mode() ) {
			if ( class_exists( Gallery_Post_Type::class ) ) {
				return add_query_arg(
					[
						'post_type' => Gallery_Post_Type::POST_TYPE,
						's'         => $search_term,
					],
					home_url( '/' )
				);
			}
		}

		// JavaScript mode or fallback
		$gallery_base = $this->get_gallery_base();
		$encoded_term = urlencode( $search_term );

		return home_url( "/{$gallery_base}/search/{$encoded_term}/" );
	}
}
