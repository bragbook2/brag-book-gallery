<?php
/**
 * URL Router
 *
 * Handles URL routing for both JavaScript and Local modes.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\Mode\Mode_Manager;
use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL Router Class
 *
 * Manages URL generation and routing for different operational modes.
 *
 * @since 3.0.0
 */
class URL_Router {

	/**
	 * Mode manager instance
	 *
	 * @since 3.0.0
	 * @var Mode_Manager
	 */
	private $mode_manager;

	/**
	 * Rewrite rules
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private $rewrite_rules = array();

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->mode_manager = Mode_Manager::get_instance();
		$this->init();
	}

	/**
	 * Initialize URL router
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Add rewrite rules
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ) );
		
		// Handle URL generation
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 3 );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 3 );
		
		// Handle legacy URLs
		add_action( 'template_redirect', array( $this, 'handle_legacy_urls' ), 1 );
		
		// Flush rewrite rules when mode changes
		add_action( 'brag_book_gallery_post_mode_switch', array( $this, 'flush_rewrite_rules' ) );
	}

	/**
	 * Add rewrite rules based on current mode
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		if ( $this->mode_manager->is_javascript_mode() ) {
			// JavaScript mode rules are handled by Shortcodes class
			// to avoid conflicts with filter_procedure and case_id query vars
			return;
		} else {
			$this->add_local_mode_rules();
		}
	}

	/**
	 * Add rewrite rules for JavaScript mode
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function add_javascript_mode_rules(): void {
		$gallery_base = $this->get_gallery_base();
		
		// Main gallery page
		add_rewrite_rule(
			"^{$gallery_base}/?$",
			'index.php?brag_gallery_view=index',
			'top'
		);

		// Category/Procedure listing
		add_rewrite_rule(
			"^{$gallery_base}/([^/]+)/?$",
			'index.php?brag_gallery_view=category&brag_gallery_slug=$matches[1]',
			'top'
		);

		// Individual case
		add_rewrite_rule(
			"^{$gallery_base}/([^/]+)/([^/]+)/?$",
			'index.php?brag_gallery_view=single&brag_gallery_category=$matches[1]&brag_gallery_case=$matches[2]',
			'top'
		);

		// Search
		add_rewrite_rule(
			"^{$gallery_base}/search/([^/]+)/?$",
			'index.php?brag_gallery_view=search&brag_gallery_search=$matches[1]',
			'top'
		);

		// Pagination
		add_rewrite_rule(
			"^{$gallery_base}/page/([0-9]+)/?$",
			'index.php?brag_gallery_view=index&brag_gallery_page=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			"^{$gallery_base}/([^/]+)/page/([0-9]+)/?$",
			'index.php?brag_gallery_view=category&brag_gallery_slug=$matches[1]&brag_gallery_page=$matches[2]',
			'top'
		);
	}

	/**
	 * Add rewrite rules for Local mode
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function add_local_mode_rules(): void {
		// Local mode uses WordPress native rewrite rules
		// These are automatically handled by the post type and taxonomy registration
		
		// Add any custom rules if needed
		$this->add_custom_local_rules();
	}

	/**
	 * Add custom rules for Local mode
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function add_custom_local_rules(): void {
		// Filter galleries by multiple taxonomies
		add_rewrite_rule(
			'^gallery/category/([^/]+)/procedure/([^/]+)/?$',
			'index.php?post_type=' . Gallery_Post_Type::POST_TYPE . '&brag_category=$matches[1]&brag_procedure=$matches[2]',
			'top'
		);

		// Gallery search
		add_rewrite_rule(
			'^gallery/search/([^/]+)/?$',
			'index.php?post_type=' . Gallery_Post_Type::POST_TYPE . '&s=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars
	 *
	 * @since 3.0.0
	 * @param array $vars Query variables.
	 * @return array Modified query variables.
	 */
	public function add_query_vars( array $vars ): array {
		$gallery_vars = array(
			'brag_gallery_view',
			'brag_gallery_slug',
			'brag_gallery_category',
			'brag_gallery_case',
			'brag_gallery_search',
			'brag_gallery_page',
			'brag_gallery_filter',
		);

		return array_merge( $vars, $gallery_vars );
	}

	/**
	 * Parse incoming requests
	 *
	 * @since 3.0.0
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	public function parse_request( $wp ): void {
		// Handle JavaScript mode requests
		if ( $this->mode_manager->is_javascript_mode() ) {
			$this->parse_javascript_mode_request( $wp );
		}
	}

	/**
	 * Parse JavaScript mode requests
	 *
	 * @since 3.0.0
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	private function parse_javascript_mode_request( $wp ): void {
		if ( ! isset( $wp->query_vars['brag_gallery_view'] ) ) {
			return;
		}

		$view = $wp->query_vars['brag_gallery_view'];

		switch ( $view ) {
			case 'index':
				$this->handle_gallery_index( $wp );
				break;
			case 'category':
				$this->handle_gallery_category( $wp );
				break;
			case 'single':
				$this->handle_gallery_single( $wp );
				break;
			case 'search':
				$this->handle_gallery_search( $wp );
				break;
		}
	}

	/**
	 * Handle gallery index page
	 *
	 * @since 3.0.0
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_index( $wp ): void {
		$wp->query_vars['is_gallery_index'] = true;
		$wp->query_vars['gallery_page'] = $wp->query_vars['brag_gallery_page'] ?? 1;
	}

	/**
	 * Handle gallery category page
	 *
	 * @since 3.0.0
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_category( $wp ): void {
		$slug = $wp->query_vars['brag_gallery_slug'] ?? '';
		
		if ( $slug ) {
			$wp->query_vars['is_gallery_category'] = true;
			$wp->query_vars['gallery_category_slug'] = $slug;
			$wp->query_vars['gallery_page'] = $wp->query_vars['brag_gallery_page'] ?? 1;
		}
	}

	/**
	 * Handle gallery single case page
	 *
	 * @since 3.0.0
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_single( $wp ): void {
		$category = $wp->query_vars['brag_gallery_category'] ?? '';
		$case = $wp->query_vars['brag_gallery_case'] ?? '';
		
		if ( $category && $case ) {
			$wp->query_vars['is_gallery_single'] = true;
			$wp->query_vars['gallery_category_slug'] = $category;
			$wp->query_vars['gallery_case_slug'] = $case;
		}
	}

	/**
	 * Handle gallery search page
	 *
	 * @since 3.0.0
	 * @param WP $wp WordPress environment instance.
	 * @return void
	 */
	private function handle_gallery_search( $wp ): void {
		$search = $wp->query_vars['brag_gallery_search'] ?? '';
		
		if ( $search ) {
			$wp->query_vars['is_gallery_search'] = true;
			$wp->query_vars['gallery_search_term'] = urldecode( $search );
		}
	}

	/**
	 * Generate URL for gallery item
	 *
	 * @since 3.0.0
	 * @param int    $id Item ID.
	 * @param string $type Item type (post, category, procedure).
	 * @param string $mode Optional mode override.
	 * @return string Generated URL.
	 */
	public function generate_url( int $id, string $type, string $mode = '' ): string {
		if ( empty( $mode ) ) {
			$mode = $this->mode_manager->get_current_mode();
		}

		switch ( $type ) {
			case 'post':
				return $this->generate_post_url( $id, $mode );
			case 'category':
				return $this->generate_category_url( $id, $mode );
			case 'procedure':
				return $this->generate_procedure_url( $id, $mode );
			default:
				return home_url();
		}
	}

	/**
	 * Generate post URL
	 *
	 * @since 3.0.0
	 * @param int    $post_id Post ID.
	 * @param string $mode Mode to generate URL for.
	 * @return string Post URL.
	 */
	private function generate_post_url( int $post_id, string $mode ): string {
		if ( $mode === 'local' ) {
			return get_permalink( $post_id );
		}

		// JavaScript mode - generate virtual URL
		$post = get_post( $post_id );
		if ( ! $post ) {
			return home_url();
		}

		$categories = get_the_terms( $post_id, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		$category_slug = ! empty( $categories ) && ! is_wp_error( $categories ) 
			? $categories[0]->slug 
			: 'uncategorized';

		$gallery_base = $this->get_gallery_base();
		
		return home_url( "/{$gallery_base}/{$category_slug}/{$post->post_name}/" );
	}

	/**
	 * Generate category URL
	 *
	 * @since 3.0.0
	 * @param int    $term_id Term ID.
	 * @param string $mode Mode to generate URL for.
	 * @return string Category URL.
	 */
	private function generate_category_url( int $term_id, string $mode ): string {
		if ( $mode === 'local' ) {
			return get_term_link( $term_id, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		}

		// JavaScript mode - generate virtual URL
		$term = get_term( $term_id, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return home_url();
		}

		$gallery_base = $this->get_gallery_base();
		
		return home_url( "/{$gallery_base}/{$term->slug}/" );
	}

	/**
	 * Generate procedure URL
	 *
	 * @since 3.0.0
	 * @param int    $term_id Term ID.
	 * @param string $mode Mode to generate URL for.
	 * @return string Procedure URL.
	 */
	private function generate_procedure_url( int $term_id, string $mode ): string {
		if ( $mode === 'local' ) {
			return get_term_link( $term_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		}

		// JavaScript mode - generate virtual URL
		$term = get_term( $term_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return home_url();
		}

		$gallery_base = $this->get_gallery_base();
		
		return home_url( "/{$gallery_base}/{$term->slug}/" );
	}

	/**
	 * Filter post type links
	 *
	 * @since 3.0.0
	 * @param string  $post_link Post URL.
	 * @param WP_Post $post Post object.
	 * @param bool    $leavename Whether to keep post name.
	 * @return string Filtered post URL.
	 */
	public function filter_post_type_link( string $post_link, \WP_Post $post, bool $leavename ): string {
		if ( $post->post_type !== Gallery_Post_Type::POST_TYPE ) {
			return $post_link;
		}

		// In JavaScript mode, redirect to virtual URLs
		if ( $this->mode_manager->is_javascript_mode() ) {
			return $this->generate_post_url( $post->ID, 'javascript' );
		}

		return $post_link;
	}

	/**
	 * Filter term links
	 *
	 * @since 3.0.0
	 * @param string  $termlink Term URL.
	 * @param WP_Term $term Term object.
	 * @param string  $taxonomy Taxonomy name.
	 * @return string Filtered term URL.
	 */
	public function filter_term_link( string $termlink, \WP_Term $term, string $taxonomy ): string {
		if ( ! in_array( $taxonomy, array( Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY ), true ) ) {
			return $termlink;
		}

		// In JavaScript mode, redirect to virtual URLs
		if ( $this->mode_manager->is_javascript_mode() ) {
			$type = $taxonomy === Gallery_Taxonomies::CATEGORY_TAXONOMY ? 'category' : 'procedure';
			return $this->generate_url( $term->term_id, $type, 'javascript' );
		}

		return $termlink;
	}

	/**
	 * Handle legacy URL redirects
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_legacy_urls(): void {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$redirect_url = null;

		// Handle mode-specific legacy URLs
		if ( $this->mode_manager->is_local_mode() ) {
			$redirect_url = $this->handle_javascript_to_local_redirects( $request_uri );
		} else {
			$redirect_url = $this->handle_local_to_javascript_redirects( $request_uri );
		}

		if ( $redirect_url && $redirect_url !== $request_uri ) {
			wp_redirect( $redirect_url, 301 );
			exit;
		}
	}

	/**
	 * Handle redirects from JavaScript mode URLs to Local mode URLs
	 *
	 * @since 3.0.0
	 * @param string $request_uri Current request URI.
	 * @return string|null Redirect URL or null if no redirect needed.
	 */
	private function handle_javascript_to_local_redirects( string $request_uri ): ?string {
		$gallery_base = $this->get_gallery_base();
		
		if ( strpos( $request_uri, "/{$gallery_base}/" ) !== 0 ) {
			return null;
		}

		$path = str_replace( "/{$gallery_base}/", '', $request_uri );
		$path_parts = array_filter( explode( '/', trim( $path, '/' ) ) );

		if ( empty( $path_parts ) ) {
			// Gallery index -> Gallery archive
			return get_post_type_archive_link( Gallery_Post_Type::POST_TYPE );
		}

		if ( count( $path_parts ) === 1 ) {
			// Category/Procedure page
			$slug = $path_parts[0];
			
			// Try category first
			$category = get_term_by( 'slug', $slug, Gallery_Taxonomies::CATEGORY_TAXONOMY );
			if ( $category && ! is_wp_error( $category ) ) {
				return get_term_link( $category );
			}
			
			// Try procedure
			$procedure = get_term_by( 'slug', $slug, Gallery_Taxonomies::PROCEDURE_TAXONOMY );
			if ( $procedure && ! is_wp_error( $procedure ) ) {
				return get_term_link( $procedure );
			}
		}

		if ( count( $path_parts ) === 2 ) {
			// Individual case
			$case_slug = $path_parts[1];
			$post = get_posts( array(
				'post_type' => Gallery_Post_Type::POST_TYPE,
				'name' => $case_slug,
				'post_status' => 'publish',
				'numberposts' => 1,
			) );
			
			if ( ! empty( $post ) ) {
				return get_permalink( $post[0] );
			}
		}

		return null;
	}

	/**
	 * Handle redirects from Local mode URLs to JavaScript mode URLs
	 *
	 * @since 3.0.0
	 * @param string $request_uri Current request URI.
	 * @return string|null Redirect URL or null if no redirect needed.
	 */
	private function handle_local_to_javascript_redirects( string $request_uri ): ?string {
		global $wp_query;

		if ( is_singular( Gallery_Post_Type::POST_TYPE ) ) {
			$post = get_queried_object();
			return $this->generate_post_url( $post->ID, 'javascript' );
		}

		if ( is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ) {
			$gallery_base = $this->get_gallery_base();
			return home_url( "/{$gallery_base}/" );
		}

		if ( is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) ) {
			$term = get_queried_object();
			return $this->generate_category_url( $term->term_id, 'javascript' );
		}

		if ( is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) ) {
			$term = get_queried_object();
			return $this->generate_procedure_url( $term->term_id, 'javascript' );
		}

		return null;
	}

	/**
	 * Get gallery base slug
	 *
	 * @since 3.0.0
	 * @return string Gallery base slug.
	 */
	private function get_gallery_base(): string {
		// Use combine_gallery_slug if set, otherwise fall back to default
		$base = get_option( 'combine_gallery_slug' );
		if ( empty( $base ) ) {
			$base = get_option( 'brag_book_gallery_base_slug', 'gallery' );
		}
		return trim( $base, '/' );
	}

	/**
	 * Get current request path info
	 *
	 * @since 3.0.0
	 * @return array Path information.
	 */
	public function get_current_path_info(): array {
		global $wp_query;

		$info = array(
			'is_gallery_request' => false,
			'mode' => $this->mode_manager->get_current_mode(),
			'type' => '',
			'object_id' => 0,
			'object' => null,
		);

		if ( is_singular( Gallery_Post_Type::POST_TYPE ) ) {
			$post = get_queried_object();
			$info = array_merge( $info, array(
				'is_gallery_request' => true,
				'type' => 'single',
				'object_id' => $post->ID,
				'object' => $post,
			) );
		} elseif ( is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ) {
			$info = array_merge( $info, array(
				'is_gallery_request' => true,
				'type' => 'archive',
			) );
		} elseif ( is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) ) {
			$term = get_queried_object();
			$info = array_merge( $info, array(
				'is_gallery_request' => true,
				'type' => 'category',
				'object_id' => $term->term_id,
				'object' => $term,
			) );
		} elseif ( is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) ) {
			$term = get_queried_object();
			$info = array_merge( $info, array(
				'is_gallery_request' => true,
				'type' => 'procedure',
				'object_id' => $term->term_id,
				'object' => $term,
			) );
		} elseif ( isset( $wp_query->query_vars['brag_gallery_view'] ) ) {
			// JavaScript mode virtual URLs
			$view = $wp_query->query_vars['brag_gallery_view'];
			$info = array_merge( $info, array(
				'is_gallery_request' => true,
				'type' => $view,
			) );
		}

		return $info;
	}

	/**
	 * Generate breadcrumb data
	 *
	 * @since 3.0.0
	 * @return array Breadcrumb data.
	 */
	public function get_breadcrumbs(): array {
		$breadcrumbs = array();
		$path_info = $this->get_current_path_info();

		if ( ! $path_info['is_gallery_request'] ) {
			return $breadcrumbs;
		}

		// Add home
		$breadcrumbs[] = array(
			'title' => __( 'Home', 'brag-book-gallery' ),
			'url' => home_url(),
			'current' => false,
		);

		// Add gallery home
		$gallery_base = $this->get_gallery_base();
		$gallery_url = $this->mode_manager->is_local_mode() 
			? get_post_type_archive_link( Gallery_Post_Type::POST_TYPE )
			: home_url( "/{$gallery_base}/" );

		$breadcrumbs[] = array(
			'title' => __( 'Gallery', 'brag-book-gallery' ),
			'url' => $gallery_url,
			'current' => $path_info['type'] === 'archive',
		);

		// Add specific breadcrumbs based on type
		switch ( $path_info['type'] ) {
			case 'single':
				$this->add_single_breadcrumbs( $breadcrumbs, $path_info['object'] );
				break;
			case 'category':
				$this->add_category_breadcrumbs( $breadcrumbs, $path_info['object'] );
				break;
			case 'procedure':
				$this->add_procedure_breadcrumbs( $breadcrumbs, $path_info['object'] );
				break;
		}

		return $breadcrumbs;
	}

	/**
	 * Add single post breadcrumbs
	 *
	 * @since 3.0.0
	 * @param array   $breadcrumbs Breadcrumbs array.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	private function add_single_breadcrumbs( array &$breadcrumbs, \WP_Post $post ): void {
		// Add category if available
		$categories = get_the_terms( $post->ID, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$category = $categories[0];
			$breadcrumbs[] = array(
				'title' => $category->name,
				'url' => $this->generate_category_url( $category->term_id, $this->mode_manager->get_current_mode() ),
				'current' => false,
			);
		}

		// Add current post
		$breadcrumbs[] = array(
			'title' => get_the_title( $post ),
			'url' => $this->generate_post_url( $post->ID, $this->mode_manager->get_current_mode() ),
			'current' => true,
		);
	}

	/**
	 * Add category breadcrumbs
	 *
	 * @since 3.0.0
	 * @param array   $breadcrumbs Breadcrumbs array.
	 * @param WP_Term $term Term object.
	 * @return void
	 */
	private function add_category_breadcrumbs( array &$breadcrumbs, \WP_Term $term ): void {
		// Add parent categories if hierarchical
		if ( $term->parent ) {
			$parents = array();
			$parent = get_term( $term->parent, Gallery_Taxonomies::CATEGORY_TAXONOMY );
			
			while ( $parent && ! is_wp_error( $parent ) ) {
				$parents[] = $parent;
				$parent = $parent->parent ? get_term( $parent->parent, Gallery_Taxonomies::CATEGORY_TAXONOMY ) : null;
			}

			// Add parents in reverse order
			foreach ( array_reverse( $parents ) as $parent ) {
				$breadcrumbs[] = array(
					'title' => $parent->name,
					'url' => $this->generate_category_url( $parent->term_id, $this->mode_manager->get_current_mode() ),
					'current' => false,
				);
			}
		}

		// Add current category
		$breadcrumbs[] = array(
			'title' => $term->name,
			'url' => $this->generate_category_url( $term->term_id, $this->mode_manager->get_current_mode() ),
			'current' => true,
		);
	}

	/**
	 * Add procedure breadcrumbs
	 *
	 * @since 3.0.0
	 * @param array   $breadcrumbs Breadcrumbs array.
	 * @param WP_Term $term Term object.
	 * @return void
	 */
	private function add_procedure_breadcrumbs( array &$breadcrumbs, \WP_Term $term ): void {
		// Add current procedure
		$breadcrumbs[] = array(
			'title' => $term->name,
			'url' => $this->generate_procedure_url( $term->term_id, $this->mode_manager->get_current_mode() ),
			'current' => true,
		);
	}

	/**
	 * Flush rewrite rules
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function flush_rewrite_rules(): void {
		flush_rewrite_rules( false );
	}

	/**
	 * Get URL for gallery index
	 *
	 * @since 3.0.0
	 * @return string Gallery index URL.
	 */
	public function get_gallery_index_url(): string {
		if ( $this->mode_manager->is_local_mode() ) {
			return get_post_type_archive_link( Gallery_Post_Type::POST_TYPE );
		}

		$gallery_base = $this->get_gallery_base();
		return home_url( "/{$gallery_base}/" );
	}

	/**
	 * Generate search URL
	 *
	 * @since 3.0.0
	 * @param string $search_term Search term.
	 * @return string Search URL.
	 */
	public function generate_search_url( string $search_term ): string {
		$search_term = urlencode( $search_term );

		if ( $this->mode_manager->is_local_mode() ) {
			return add_query_arg( array(
				'post_type' => Gallery_Post_Type::POST_TYPE,
				's' => $search_term,
			), home_url( '/' ) );
		}

		$gallery_base = $this->get_gallery_base();
		return home_url( "/{$gallery_base}/search/{$search_term}/" );
	}
}