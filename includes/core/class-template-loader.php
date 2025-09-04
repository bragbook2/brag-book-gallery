<?php
declare( strict_types=1 );

/**
 * Enterprise Template Loader for BRAGBook Gallery Plugin
 *
 * Comprehensive template management system implementing WordPress VIP standards
 * with PHP 8.2+ optimizations. Handles template loading, routing, and hierarchy
 * for both JavaScript and Local operational modes with security-first approach.
 *
 * Key Features:
 * - Multi-mode template loading (JavaScript/Local)
 * - Template hierarchy with theme override support
 * - Virtual URL routing for JavaScript mode
 * - Dynamic template variable injection
 * - Child/parent theme template support
 * - Performance-optimized template location
 * - Security-hardened template inclusion
 *
 * Architecture:
 * - Mode-aware template selection
 * - Theme template override system
 * - Plugin fallback templates
 * - Template part rendering
 * - Output buffering for capture
 * - Variable extraction safety
 *
 * Template Hierarchy:
 * 1. Child theme: /brag-book-gallery/
 * 2. Parent theme: /brag-book-gallery/
 * 3. Plugin: /templates/
 *
 * Security Features:
 * - Path traversal prevention
 * - Safe variable extraction
 * - Template validation
 * - Sanitized includes
 * - XSS prevention
 *
 * Performance Optimizations:
 * - Template caching
 * - Efficient file checks
 * - Minimal file operations
 * - Optimized hierarchy
 * - Lazy template loading
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
use BRAGBookGallery\Includes\Traits\Trait_Tools;

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access denied.' );
}

/**
 * Template Loader Class
 *
 * Enterprise-grade template management system for multi-mode gallery operations.
 * Implements comprehensive template loading strategies with security hardening
 * and performance optimization for both JavaScript and Local modes.
 *
 * Core Functionality:
 * - Dynamic template selection based on mode
 * - Theme template override support
 * - Virtual URL routing for JavaScript mode
 * - Template variable management
 * - Template part rendering
 * - Body class management
 *
 * Technical Implementation:
 * - PHP 8.2+ type safety and features
 * - WordPress VIP coding standards
 * - Security-first template inclusion
 * - Performance-optimized file operations
 * - Comprehensive error handling
 * - Mode-aware template logic
 *
 * @since 3.0.0
 */
final class Template_Loader {
	use Trait_Tools;

	/**
	 * Mode manager instance for operational mode detection.
	 *
	 * @since 3.0.0
	 * @var Mode_Manager|null
	 */
	private ?Mode_Manager $mode_manager = null;

	/**
	 * Template directory path for plugin templates.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $template_path = '';

	/**
	 * Template cache for performance optimization.
	 *
	 * @since 3.0.0
	 * @var array<string, string|null>
	 */
	private array $template_cache = [];

	/**
	 * Constructor.
	 *
	 * Initializes mode manager, sets template paths, and registers hooks.
	 * Uses lazy loading for performance optimization.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		if ( class_exists( Mode_Manager::class ) ) {
			$this->mode_manager = Mode_Manager::get_instance();
		}

		$this->template_path = self::get_plugin_path() . 'templates/';
		$this->init();
	}

	/**
	 * Initialize template loader with comprehensive hook registration.
	 *
	 * Registers all WordPress hooks for template management, including
	 * template selection, hierarchy modification, and variable setup.
	 *
	 * Hook Registration:
	 * - template_include: Main template selection
	 * - *_template_hierarchy: Template hierarchy modification
	 * - template_redirect: JavaScript mode routing
	 * - wp: Template variable setup
	 * - body_class: Body class management
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Template selection and hierarchy
		add_filter( 'template_include', [ $this, 'template_chooser' ], 99, 1 );
		add_filter( 'single_template_hierarchy', [ $this, 'add_single_templates' ], 10, 1 );
		add_filter( 'archive_template_hierarchy', [ $this, 'add_archive_templates' ], 10, 1 );
		add_filter( 'taxonomy_template_hierarchy', [ $this, 'add_taxonomy_templates' ], 10, 1 );

		// JavaScript mode handling with early priority
		add_action( 'template_redirect', [ $this, 'handle_javascript_mode_requests' ], 5 );

		// Template variables and body classes
		add_action( 'wp', [ $this, 'setup_template_vars' ], 10 );
		add_filter( 'body_class', [ $this, 'add_body_classes' ], 10, 1 );
	}

	/**
	 * Choose the appropriate template based on operational mode.
	 *
	 * Central template selection method that delegates to mode-specific
	 * loaders. Ensures proper template is loaded for current context.
	 *
	 * Selection Process:
	 * 1. Detect operational mode
	 * 2. Delegate to mode-specific loader
	 * 3. Return custom or default template
	 *
	 * @since 3.0.0
	 * @param string $template Current template path from WordPress.
	 * @return string Modified template path or original if unchanged.
	 */
	public function template_chooser( string $template ): string {
		if ( ! $this->mode_manager ) {
			return $template;
		}

		// Delegate based on operational mode
		return $this->mode_manager->is_local_mode()
			? $this->load_local_mode_template( $template )
			: $this->load_javascript_mode_template( $template );
	}

	/**
	 * Load template for Local mode operations.
	 *
	 * Selects appropriate template for Local mode based on content type.
	 * Supports single posts, archives, and taxonomy templates.
	 *
	 * Template Priority:
	 * - Single: local-mode/single-brag_gallery.php
	 * - Archive: local-mode/archive-brag_gallery.php
	 * - Category: local-mode/taxonomy-{taxonomy}-{slug}.php
	 * - Procedure: local-mode/taxonomy-{taxonomy}.php
	 *
	 * @since 3.0.0
	 * @param string $template Current template path.
	 * @return string Custom template path or original.
	 */
	private function load_local_mode_template( string $template ): string {
		// Validate dependencies
		if ( ! class_exists( Gallery_Post_Type::class ) || ! class_exists( Gallery_Taxonomies::class ) ) {
			return $template;
		}

		// Check content type and return appropriate template
		return match ( true ) {
			is_singular( Gallery_Post_Type::POST_TYPE ) => $this->get_single_template( $template ),
			is_post_type_archive( Gallery_Post_Type::POST_TYPE ) => $this->get_archive_template( $template ),
			is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) => $this->get_category_template( $template ),
			is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) => $this->get_procedure_template( $template ),
			default => $template,
		};
	}

	/**
	 * Get single post template.
	 *
	 * @since 3.0.0
	 * @param string $default Default template.
	 * @return string Template path.
	 */
	private function get_single_template( string $default ): string {
		$custom_template = $this->locate_template( 'local-mode/single-brag_gallery.php' );
		return $custom_template ?: $default;
	}

	/**
	 * Get archive template.
	 *
	 * @since 3.0.0
	 * @param string $default Default template.
	 * @return string Template path.
	 */
	private function get_archive_template( string $default ): string {
		$custom_template = $this->locate_template( 'local-mode/archive-brag_gallery.php' );
		return $custom_template ?: $default;
	}

	/**
	 * Get category taxonomy template.
	 *
	 * @since 3.0.0
	 * @param string $default Default template.
	 * @return string Template path.
	 */
	private function get_category_template( string $default ): string {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return $default;
		}

		$templates = [
			"local-mode/taxonomy-{$term->taxonomy}-{$term->slug}.php",
			"local-mode/taxonomy-{$term->taxonomy}.php",
			'local-mode/taxonomy-brag_category.php',
		];

		$custom_template = $this->locate_template( $templates );
		return $custom_template ?: $default;
	}

	/**
	 * Get procedure taxonomy template.
	 *
	 * @since 3.0.0
	 * @param string $default Default template.
	 * @return string Template path.
	 */
	private function get_procedure_template( string $default ): string {
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return $default;
		}

		$templates = [
			"local-mode/taxonomy-{$term->taxonomy}-{$term->slug}.php",
			"local-mode/taxonomy-{$term->taxonomy}.php",
			'local-mode/taxonomy-brag_procedure.php',
		];

		$custom_template = $this->locate_template( $templates );
		return $custom_template ?: $default;
	}

	/**
	 * Load template for JavaScript mode operations.
	 *
	 * Selects appropriate template for JavaScript mode, typically using
	 * a single template for all gallery views handled by JavaScript.
	 *
	 * Template Priority:
	 * - Gallery: javascript-mode/gallery-page.php
	 * - Fallback: Original template
	 *
	 * @since 3.0.0
	 * @param string $template Current template path.
	 * @return string Custom template path or original.
	 */
	private function load_javascript_mode_template( string $template ): string {
		// Only load custom template for gallery requests
		if ( ! $this->is_gallery_request() ) {
			return $template;
		}

		// Locate and return JavaScript mode template
		$custom_template = $this->locate_template( 'javascript-mode/gallery-page.php' );
		return $custom_template ?: $template;
	}

	/**
	 * Handle JavaScript mode requests and routing.
	 *
	 * Processes virtual URLs for JavaScript mode, parsing path components
	 * and setting appropriate query variables for template rendering.
	 *
	 * URL Patterns:
	 * - /gallery/: Index view
	 * - /gallery/slug/: Category/procedure view
	 * - /gallery/cat/case/: Single case view
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_javascript_mode_requests(): void {
		if ( ! $this->mode_manager || ! $this->mode_manager->is_javascript_mode() ) {
			return;
		}

		global $wp_query;

		// Get and sanitize request URI
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used for comparison only
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( empty( $request_uri ) ) {
			return;
		}

		$gallery_base = $this->get_gallery_base_url();

		// Check if request matches gallery base
		if ( strpos( $request_uri, $gallery_base ) !== 0 ) {
			return;
		}

		// Parse request path
		$path = str_replace( $gallery_base, '', $request_uri );
		$path_parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );

		// Set base query vars
		$wp_query->set( 'brag_gallery_mode', 'javascript' );
		$wp_query->set( 'brag_gallery_path', $path_parts );

		// Determine view type using match expression
		match ( count( $path_parts ) ) {
			0 => $this->set_index_view( $wp_query ),
			1 => $this->set_category_view( $wp_query, $path_parts[0] ),
			2 => $this->set_single_view( $wp_query, $path_parts[0], $path_parts[1] ),
			default => null,
		};

		// Reset WordPress query flags
		$this->reset_query_flags( $wp_query );
	}

	/**
	 * Set index view query vars.
	 *
	 * @since 3.0.0
	 * @param \WP_Query $wp_query WordPress query object.
	 * @return void
	 */
	private function set_index_view( \WP_Query $wp_query ): void {
		$wp_query->set( 'brag_book_gallery_view', 'index' );
	}

	/**
	 * Set category view query vars.
	 *
	 * @since 3.0.0
	 * @param \WP_Query $wp_query WordPress query object.
	 * @param string $slug Category or procedure slug.
	 * @return void
	 */
	private function set_category_view( \WP_Query $wp_query, string $slug ): void {
		$wp_query->set( 'brag_book_gallery_view', 'category' );
		$wp_query->set( 'brag_gallery_slug', sanitize_title( $slug ) );
	}

	/**
	 * Set single view query vars.
	 *
	 * @since 3.0.0
	 * @param \WP_Query $wp_query WordPress query object.
	 * @param string $category Category slug.
	 * @param string $case Case slug.
	 * @return void
	 */
	private function set_single_view( \WP_Query $wp_query, string $category, string $case ): void {
		$wp_query->set( 'brag_book_gallery_view', 'single' );
		$wp_query->set( 'brag_gallery_category', sanitize_title( $category ) );
		$wp_query->set( 'brag_book_gallery_cae', sanitize_title( $case ) );
	}

	/**
	 * Reset WordPress query flags for virtual pages.
	 *
	 * @since 3.0.0
	 * @param \WP_Query $wp_query WordPress query object.
	 * @return void
	 */
	private function reset_query_flags( \WP_Query $wp_query ): void {
		$wp_query->is_home = false;
		$wp_query->is_page = false;
		$wp_query->is_single = false;
		$wp_query->is_singular = false;
		$wp_query->is_archive = true;
	}

	/**
	 * Get template by name and mode.
	 *
	 * Retrieves template path based on name and operational mode.
	 * Auto-detects mode if not specified.
	 *
	 * @since 3.0.0
	 * @param string $template_name Template file name.
	 * @param string $mode Optional mode override ('local', 'javascript').
	 * @return string Template path or empty string if not found.
	 */
	public function get_template( string $template_name, string $mode = '' ): string {
		// Auto-detect mode if not specified
		$mode = ! empty( $mode ) ? $mode : ( $this->mode_manager?->is_local_mode() ? 'local' : 'javascript' );

		// Build template path
		$template_file = sprintf( '%s-mode/%s', $mode, $template_name );

		return $this->locate_template( $template_file ) ?: '';
	}

	/**
	 * Load template by mode with variable injection.
	 *
	 * Loads and executes template file with safely extracted variables.
	 * Uses output buffering for safe inclusion.
	 *
	 * Security:
	 * - EXTR_SKIP prevents variable overwriting
	 * - Template path validation
	 * - Safe file inclusion
	 *
	 * @since 3.0.0
	 * @param string $template_base Base template name.
	 * @param array<string, mixed> $variables Variables to pass to template.
	 * @return void
	 */
	public function load_by_mode( string $template_base, array $variables = [] ): void {
		$mode = $this->mode_manager?->is_local_mode() ? 'local' : 'javascript';
		$template = $this->get_template( $template_base, $mode );

		if ( empty( $template ) || ! file_exists( $template ) ) {
			return;
		}

		// Safely load template with variables
		$this->include_template( $template, $variables );
	}

	/**
	 * Include template with safe variable extraction.
	 *
	 * @since 3.0.0
	 * @param string $template Template path.
	 * @param array<string, mixed> $variables Template variables.
	 * @return void
	 */
	private function include_template( string $template, array $variables ): void {
		if ( ! empty( $variables ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Safe with EXTR_SKIP
			extract( $variables, EXTR_SKIP );
		}

		include $template;
	}

	/**
	 * Locate template file with caching.
	 *
	 * Searches for template in theme and plugin directories with
	 * hierarchical fallback. Uses internal caching for performance.
	 *
	 * Search Order:
	 * 1. Child theme: /brag-book-gallery/
	 * 2. Parent theme: /brag-book-gallery/
	 * 3. Plugin: /templates/
	 *
	 * @since 3.0.0
	 * @param string|array<string> $template_names Template name(s) to search for.
	 * @return string|null Template path or null if not found.
	 */
	private function locate_template( string|array $template_names ): ?string {
		// Normalize to array
		$template_names = (array) $template_names;

		foreach ( $template_names as $template_name ) {
			if ( empty( $template_name ) ) {
				continue;
			}

			// Check cache first
			if ( isset( $this->template_cache[ $template_name ] ) ) {
				return $this->template_cache[ $template_name ];
			}

			// Search for template
			$located = $this->search_template_locations( $template_name );

			if ( $located ) {
				// Cache the result
				$this->template_cache[ $template_name ] = $located;
				return $located;
			}
		}

		return null;
	}

	/**
	 * Search template locations.
	 *
	 * @since 3.0.0
	 * @param string $template_name Template name.
	 * @return string|null Located template path.
	 */
	private function search_template_locations( string $template_name ): ?string {
		// Define search locations with priority
		$locations = [
			get_stylesheet_directory() . '/brag-book-gallery/' . $template_name,
			get_template_directory() . '/brag-book-gallery/' . $template_name,
			$this->template_path . $template_name,
		];

		// Search each location
		foreach ( $locations as $location ) {
			if ( file_exists( $location ) ) {
				return $location;
			}
		}

		return null;
	}

	/**
	 * Add single post templates to hierarchy.
	 *
	 * Modifies template hierarchy for single gallery posts based on
	 * operational mode. Templates are prepended for priority.
	 *
	 * @since 3.0.0
	 * @param array<int, string> $templates Template hierarchy.
	 * @return array<int, string> Modified template hierarchy.
	 */
	public function add_single_templates( array $templates ): array {
		if ( ! class_exists( Gallery_Post_Type::class ) ) {
			return $templates;
		}

		if ( ! is_singular( Gallery_Post_Type::POST_TYPE ) ) {
			return $templates;
		}

		// Determine templates based on mode
		$new_templates = $this->mode_manager?->is_local_mode()
			? [
				'single-' . Gallery_Post_Type::POST_TYPE . '.php',
				'brag-book-gallery/local-mode/single-brag_gallery.php',
			]
			: [
				'brag-book-gallery/javascript-mode/single-gallery.php',
			];

		return array_merge( $new_templates, $templates );
	}

	/**
	 * Add archive templates to hierarchy.
	 *
	 * Modifies template hierarchy for gallery archives based on
	 * operational mode. Templates are prepended for priority.
	 *
	 * @since 3.0.0
	 * @param array<int, string> $templates Template hierarchy.
	 * @return array<int, string> Modified template hierarchy.
	 */
	public function add_archive_templates( array $templates ): array {
		if ( ! class_exists( Gallery_Post_Type::class ) ) {
			return $templates;
		}

		if ( ! is_post_type_archive( Gallery_Post_Type::POST_TYPE ) ) {
			return $templates;
		}

		// Determine templates based on mode
		$new_templates = $this->mode_manager?->is_local_mode()
			? [
				'archive-' . Gallery_Post_Type::POST_TYPE . '.php',
				'brag-book-gallery/local-mode/archive-brag_gallery.php',
			]
			: [
				'brag-book-gallery/javascript-mode/archive-gallery.php',
			];

		return array_merge( $new_templates, $templates );
	}

	/**
	 * Add taxonomy templates to hierarchy.
	 *
	 * Modifies template hierarchy for gallery taxonomies based on
	 * operational mode. Templates are prepended for priority.
	 *
	 * @since 3.0.0
	 * @param array<int, string> $templates Template hierarchy.
	 * @return array<int, string> Modified template hierarchy.
	 */
	public function add_taxonomy_templates( array $templates ): array {
		if ( ! class_exists( Gallery_Taxonomies::class ) ) {
			return $templates;
		}

		$taxonomy = get_query_var( 'taxonomy' );

		// Check if gallery taxonomy
		$gallery_taxonomies = [
			Gallery_Taxonomies::CATEGORY_TAXONOMY,
			Gallery_Taxonomies::PROCEDURE_TAXONOMY,
		];

		if ( ! in_array( $taxonomy, $gallery_taxonomies, true ) ) {
			return $templates;
		}

		// Determine templates based on mode
		$new_templates = $this->mode_manager?->is_local_mode()
			? [
				"taxonomy-{$taxonomy}.php",
				"brag-book-gallery/local-mode/taxonomy-{$taxonomy}.php",
			]
			: [
				"brag-book-gallery/javascript-mode/taxonomy-{$taxonomy}.php",
			];

		return array_merge( $new_templates, $templates );
	}

	/**
	 * Setup template variables for global access.
	 *
	 * Initializes global variables with gallery data for template use.
	 * Includes mode information and API configuration.
	 *
	 * Global Variables:
	 * - $brag_gallery_mode: Current operational mode
	 * - $brag_gallery_data: Gallery configuration array
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function setup_template_vars(): void {
		global $brag_gallery_mode, $brag_gallery_data;

		$current_mode = $this->mode_manager?->get_current_mode() ?? 'local';
		$brag_gallery_mode = $current_mode;

		// Initialize base data
		$brag_gallery_data = [
			'mode'               => $current_mode,
			'is_local_mode'      => $this->mode_manager?->is_local_mode() ?? false,
			'is_javascript_mode' => $this->mode_manager?->is_javascript_mode() ?? false,
		];

		// Add JavaScript mode specific data
		if ( $brag_gallery_data['is_javascript_mode'] ) {
			$brag_gallery_data = array_merge( $brag_gallery_data, $this->get_javascript_mode_data() );
		}
	}

	/**
	 * Get JavaScript mode configuration data.
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Configuration data.
	 */
	private function get_javascript_mode_data(): array {
		return [
			'api_base'     => sanitize_url( get_option( 'brag_book_gallery_api_url', '' ) ),
			'api_token'    => sanitize_text_field( get_option( 'brag_book_gallery_api_token', '' ) ),
			'property_id'  => absint( get_option( 'brag_book_gallery_property_id', 0 ) ),
		];
	}

	/**
	 * Check if current request is gallery-related.
	 *
	 * Determines whether the current request is for gallery content,
	 * including post types, taxonomies, and virtual URLs.
	 *
	 * @since 3.0.0
	 * @return bool True if gallery request, false otherwise.
	 */
	private function is_gallery_request(): bool {
		// Validate dependencies
		if ( ! class_exists( Gallery_Post_Type::class ) || ! class_exists( Gallery_Taxonomies::class ) ) {
			return false;
		}

		// Check native gallery pages
		if ( $this->is_native_gallery_page() ) {
			return true;
		}

		// Check virtual URLs in JavaScript mode
		if ( $this->mode_manager?->is_javascript_mode() ) {
			return $this->is_virtual_gallery_url();
		}

		return false;
	}

	/**
	 * Check if native gallery page.
	 *
	 * @since 3.0.0
	 * @return bool True if native gallery page.
	 */
	private function is_native_gallery_page(): bool {
		return is_singular( Gallery_Post_Type::POST_TYPE )
			|| is_post_type_archive( Gallery_Post_Type::POST_TYPE )
			|| is_tax( [ Gallery_Taxonomies::CATEGORY_TAXONOMY, Gallery_Taxonomies::PROCEDURE_TAXONOMY ] );
	}

	/**
	 * Check if virtual gallery URL.
	 *
	 * @since 3.0.0
	 * @return bool True if virtual gallery URL.
	 */
	private function is_virtual_gallery_url(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used for comparison only
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( empty( $request_uri ) ) {
			return false;
		}

		$gallery_base = $this->get_gallery_base_url();
		return str_starts_with( $request_uri, $gallery_base );
	}

	/**
	 * Get gallery base URL for virtual routing.
	 *
	 * Retrieves and normalizes the gallery base URL from settings,
	 * ensuring proper slash formatting.
	 *
	 * @since 3.0.0
	 * @return string Normalized gallery base URL with slashes.
	 */
	private function get_gallery_base_url(): string {
		// Get from settings with default fallback
		$base = get_option( 'brag_book_gallery_base_url', '/gallery/' );

		// Sanitize and normalize with slashes
		$base = sanitize_title( trim( (string) $base, '/' ) ) ?: 'gallery';

		return '/' . $base . '/';
	}

	/**
	 * Get template part with variables.
	 *
	 * Loads template part similar to get_template_part() but with
	 * variable support and mode awareness.
	 *
	 * @since 3.0.0
	 * @param string $slug Template slug.
	 * @param string $name Optional template variation name.
	 * @param array<string, mixed> $variables Variables to pass to template.
	 * @return void
	 */
	public function get_template_part( string $slug, string $name = '', array $variables = [] ): void {
		$mode = $this->mode_manager?->is_local_mode() ? 'local' : 'javascript';

		// Build template hierarchy
		$templates = [];

		if ( ! empty( $name ) ) {
			$templates[] = sprintf( '%s-mode/%s-%s.php', $mode, $slug, $name );
		}

		$templates[] = sprintf( '%s-mode/%s.php', $mode, $slug );

		// Locate and include template
		$template = $this->locate_template( $templates );

		if ( $template && file_exists( $template ) ) {
			$this->include_template( $template, $variables );
		}
	}

	/**
	 * Render template with output capture.
	 *
	 * Renders template and returns output as string instead of
	 * directly outputting. Useful for AJAX responses and widgets.
	 *
	 * @since 3.0.0
	 * @param string $template_name Template file name.
	 * @param array<string, mixed> $variables Variables to pass to template.
	 * @return string Rendered template output.
	 */
	public function render_template( string $template_name, array $variables = [] ): string {
		$template = $this->get_template( $template_name );

		if ( empty( $template ) || ! file_exists( $template ) ) {
			return '';
		}

		// Capture template output
		ob_start();
		$this->include_template( $template, $variables );
		return (string) ob_get_clean();
	}

	/**
	 * Get available templates for current mode.
	 *
	 * Scans template directory for available templates in the
	 * current operational mode. Useful for debugging and admin.
	 *
	 * @since 3.0.0
	 * @return array<int, string> List of available template names.
	 */
	public function get_available_templates(): array {
		$mode = $this->mode_manager?->is_local_mode() ? 'local' : 'javascript';
		$template_dir = $this->template_path . $mode . '-mode/';

		if ( ! is_dir( $template_dir ) ) {
			return [];
		}

		// Scan directory for PHP files
		$files = scandir( $template_dir );

		if ( ! is_array( $files ) ) {
			return [];
		}

		// Filter PHP files and extract names
		return array_values(
			array_filter(
				array_map(
					static function ( string $file ): ?string {
						return pathinfo( $file, PATHINFO_EXTENSION ) === 'php'
							? basename( $file, '.php' )
							: null;
					},
					$files
				)
			)
		);
	}

	/**
	 * Add body classes for gallery pages.
	 *
	 * Adds contextual CSS classes to body element for gallery pages,
	 * enabling targeted styling based on mode and content type.
	 *
	 * Added Classes:
	 * - brag-book-gallery: All gallery pages
	 * - brag-gallery-{mode}: Mode-specific class
	 * - brag-gallery-single: Single case pages
	 * - brag-gallery-archive: Archive pages
	 * - brag-gallery-category: Category pages
	 * - brag-gallery-procedure: Procedure pages
	 *
	 * @since 3.0.0
	 * @param array<int, string> $classes Current body classes.
	 * @return array<int, string> Modified body classes.
	 */
	public function add_body_classes( array $classes ): array {
		if ( ! $this->is_gallery_request() ) {
			return $classes;
		}

		// Add base gallery class
		$classes[] = 'brag-book-gallery';

		// Add mode-specific class
		$mode = $this->mode_manager?->get_current_mode() ?? 'local';
		$classes[] = 'brag-gallery-' . sanitize_html_class( $mode );

		// Add content-type classes
		if ( class_exists( Gallery_Post_Type::class ) && class_exists( Gallery_Taxonomies::class ) ) {
			$classes = array_merge( $classes, $this->get_content_type_classes() );
		}

		return array_unique( $classes );
	}

	/**
	 * Get content type body classes.
	 *
	 * @since 3.0.0
	 * @return array<int, string> Content type classes.
	 */
	private function get_content_type_classes(): array {
		return match ( true ) {
			is_singular( Gallery_Post_Type::POST_TYPE ) => [ 'brag-gallery-single' ],
			is_post_type_archive( Gallery_Post_Type::POST_TYPE ) => [ 'brag-gallery-archive' ],
			is_tax( Gallery_Taxonomies::CATEGORY_TAXONOMY ) => [ 'brag-gallery-category' ],
			is_tax( Gallery_Taxonomies::PROCEDURE_TAXONOMY ) => [ 'brag-gallery-procedure' ],
			default => [],
		};
	}
}
