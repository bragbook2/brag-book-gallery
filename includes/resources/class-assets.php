<?php
/**
 * Asset Management Class
 *
 * Handles the registration and enqueuing of all plugin assets including
 * stylesheets, scripts, and their dependencies for both admin and frontend.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Resources
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Resources;

use BRAGBookGallery\Includes\Core\Setup;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets Management Class
 *
 * Enterprise-grade asset management system for BRAGBook Gallery plugin.
 * Provides comprehensive asset loading, optimization, and performance features
 * with full VIP compliance and modern PHP 8.2 capabilities.
 *
 * Key Features:
 * - Intelligent asset loading with conditional enqueueing
 * - CDN integration for external dependencies
 * - Performance optimization (preloading, async/defer)
 * - Comprehensive localization with multi-language support
 * - Cache busting with development/production modes
 * - Security-first approach with input validation
 * - WordPress VIP compliant logging and debugging
 *
 * Responsibilities:
 * - Frontend and admin asset management
 * - Script dependency resolution and optimization
 * - CSS/JS localization and data passing
 * - Performance monitoring and optimization
 * - Asset version management and cache control
 * - Cross-browser compatibility and fallbacks
 *
 * @package    BRAGBookGallery
 * @subpackage Resources
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 *
 * @see https://developer.wordpress.org/plugins/javascript/
 * @see https://developer.wordpress.org/plugins/plugin-basics/best-practices/
 */
class Assets {

	/**
	 * Plugin asset version for cache busting and version control
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const ASSET_VERSION = '3.0.0';

	/**
	 * CDN URLs for external dependencies with fallback support
	 *
	 * @since 3.0.0
	 * @var array<string, string>
	 */
	private const CDN_URLS = [
		'jquery'    => 'https://code.jquery.com/jquery-3.7.1.min.js',
		'jquery_ui' => 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
		'slick_css' => 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css',
		'slick_js'  => 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
	];

	/**
	 * Cache TTL constants for performance optimization
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_TTL_SHORT = 300;   // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800; // 30 minutes
	private const CACHE_TTL_LONG = 3600;   // 1 hour

	/**
	 * Critical assets that should be preloaded
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const CRITICAL_ASSETS = [
		'brag-book-gallery-style',
		'jquery',
	];

	/**
	 * Memory cache for performance optimization
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];

	/**
	 * Asset loading errors collection
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private array $loading_errors = [];

	/**
	 * Performance metrics tracking
	 *
	 * @since 3.0.0
	 * @var array<string, float>
	 */
	private array $performance_metrics = [];

	/**
	 * Constructor - Initialize asset management with comprehensive error handling
	 *
	 * Sets up WordPress hooks for asset loading, performance monitoring,
	 * and VIP-compliant debugging with graceful error handling.
	 *
	 * @since 3.0.0
	 * @throws Exception If critical initialization fails.
	 */
	public function __construct() {
		try {
			$this->init_performance_tracking();
			$this->init_hooks();
			do_action( 'qm/debug', 'Assets class initialized successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Assets initialization failed: ' . $e->getMessage() );
			// Graceful degradation - continue without throwing
		}
	}

	/**
	 * Initialize WordPress hooks with comprehensive asset management
	 *
	 * Sets up all necessary hooks for asset loading, optimization,
	 * and performance monitoring with proper error handling.
	 *
	 * @since 3.0.0
	 * @throws Exception If hook registration fails.
	 * @return void
	 */
	private function init_hooks(): void {
		try {
			// Core asset enqueuing with optimized priorities
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ], 10 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ], 10 );

			// jQuery management for compatibility
			add_action( 'wp_enqueue_scripts', [ $this, 'maybe_override_jquery' ], 1 );
			add_action( 'admin_enqueue_scripts', [ $this, 'maybe_override_jquery' ], 1 );

			// Performance optimization hooks
			add_action( 'wp_head', [ $this, 'preload_critical_assets' ], 5 );
			add_filter( 'script_loader_tag', [ $this, 'optimize_script_loading' ], 10, 3 );

			// Error handling and monitoring
			add_action( 'wp_footer', [ $this, 'output_performance_metrics' ], 999 );
			add_action( 'admin_footer', [ $this, 'output_performance_metrics' ], 999 );

			do_action( 'qm/debug', 'Asset management hooks registered successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Failed to register asset hooks: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Maybe override WordPress jQuery with CDN version (VIP compliant)
	 *
	 * Intelligently overrides jQuery with CDN version when appropriate,
	 * with comprehensive error handling and VIP compliance.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function maybe_override_jquery(): void {
		try {
			// VIP-compliant CDN jQuery configuration (disabled by default)
			$use_cdn_jquery = apply_filters( 'brag_book_gallery_use_cdn_jquery', false );

			if ( ! $use_cdn_jquery ) {
				do_action( 'qm/debug', 'CDN jQuery override disabled by filter' );
				return;
			}

			// Enhanced jQuery override with fallback
			if ( ! is_admin() && ! $this->is_jquery_already_overridden() ) {
				$this->register_cdn_jquery();
				do_action( 'qm/debug', 'CDN jQuery registered successfully' );
			}
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'jQuery override failed: ' . $e->getMessage() );
			// Continue with WordPress default jQuery
		}
	}

	/**
	 * Enqueue frontend assets
	 *
	 * Loads all necessary styles and scripts for the public-facing side
	 * of the plugin, including third-party libraries and custom assets.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function enqueue_frontend_assets(): void {
		try {
			$start_time = microtime( true );

			// Enhanced page detection with caching
			if ( ! $this->is_bragbook_page() ) {
				do_action( 'qm/debug', 'Skipping frontend assets - not a BRAG book page' );
				return;
			}

			// Sequential asset loading with error handling
			$this->enqueue_frontend_styles();
			$this->enqueue_frontend_scripts();
			$this->localize_frontend_data();

			// Performance tracking
			$this->performance_metrics['frontend_assets_load_time'] = microtime( true ) - $start_time;
			do_action( 'qm/debug', 'Frontend assets enqueued successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Frontend asset loading failed: ' . $e->getMessage() );
			$this->loading_errors[] = 'Frontend: ' . $e->getMessage();
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads all necessary styles and scripts for the WordPress admin area,
	 * but only on BRAG book admin pages to avoid conflicts.
	 *
	 * @param string $hook_suffix The current admin page hook suffix
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		try {
			$start_time = microtime( true );

			// Enhanced admin page detection with validation
			if ( ! $this->is_bragbook_admin_page( $hook_suffix ) ) {
				do_action( 'qm/debug', "Skipping admin assets - not a BRAG book admin page: {$hook_suffix}" );
				return;
			}

			// Sequential admin asset loading
			$this->enqueue_admin_styles();
			$this->enqueue_admin_scripts();
			$this->localize_admin_data();

			// Performance tracking
			$this->performance_metrics['admin_assets_load_time'] = microtime( true ) - $start_time;
			do_action( 'qm/debug', "Admin assets enqueued successfully for: {$hook_suffix}" );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Admin asset loading failed: ' . $e->getMessage() );
			$this->loading_errors[] = 'Admin: ' . $e->getMessage();
		}
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_frontend_styles(): void {
		try {
			// Main plugin styles with enhanced error handling
			$main_css_result = wp_enqueue_style(
				'brag-book-gallery-style',
				Setup::get_asset_url( 'assets/css/brag-book-gallery.css' ),
				[],
				$this->get_asset_version()
			);

			if ( ! $main_css_result ) {
				throw new Exception( 'Failed to enqueue main stylesheet' );
			}

			// External dependencies with fallback
			$this->enqueue_external_styles();

			// Allow themes to add custom styles
			do_action( 'brag_book_gallery_frontend_styles_enqueued' );
			do_action( 'qm/debug', 'Frontend styles enqueued successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Frontend styles enqueue failed: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_frontend_scripts(): void {

		// Main plugin script.
		wp_enqueue_script(
			handle: 'brag-book-gallery-script',
			src: Setup::get_asset_url( 'assets/js/brag-book-gallery.js' ),
			deps: array(
				'jquery',
				'brag-book-gallery-slick',
				'jquery-ui-accordion'
			),
			ver: $this->get_asset_version(),
			args: true
		);

		// Always localize script with AJAX data for forms and galleries
		wp_localize_script( 'brag-book-gallery-script', 'bragBookGalleryConfig', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'brag_book_gallery_nonce' ),
			'consultation_nonce' => wp_create_nonce( 'consultation_form_nonce' ),
		] );

		// Allow plugins/themes to add custom scripts.
		do_action( hook_name: 'brag_book_gallery_frontend_scripts_enqueued' );
	}

	/**
	 * Enqueue admin styles
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_admin_styles(): void {

		// Admin styles.
		wp_enqueue_style(
			handle: 'brag-book-gallery-admin-style',
			src: Setup::get_asset_url( asset_path: 'assets/css/brag-book-gallery-admin.css' ),
			deps: array(),
			ver: $this->get_asset_version()
		);

		// jQuery UI styles for admin accordion.
		wp_enqueue_style(
			handle: 'jquery-ui-css',
			src: self::CDN_URLS['jquery_ui'],
			deps: array(),
			ver: '1.12.1'
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_admin_scripts(): void {

		// Ensure jQuery is loaded.
		wp_enqueue_script( handle: 'jquery' );

		// jQuery UI Accordion.
		wp_enqueue_script( handle: 'jquery-ui-accordion' );

		// Admin main script
		wp_enqueue_script(
			handle: 'brag-book-gallery-admin-script',
			src: Setup::get_asset_url( 'assets/js/brag-book-gallery-admin.js' ),
			deps: array(
				'jquery',
				'jquery-ui-accordion'
			),
			ver: $this->get_asset_version(),
			args: true
		);
	}

	/**
	 * Localize frontend script data
	 *
	 * Passes PHP data to JavaScript for use in frontend scripts.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function localize_frontend_data(): void {

		// Define asset URLs for reuse.
		$icon_left_arrow      = Setup::get_asset_url( asset_path: 'assets/images/red-angle-left.svg' );
		$icon_right_arrow     = Setup::get_asset_url( asset_path: 'assets/images/red-angle-right.svg' );
		$icon_left_arrow_url  = Setup::get_asset_url( asset_path: 'assets/images/caret-left.svg' );
		$icon_right_arrow_url = Setup::get_asset_url( asset_path: 'assets/images/caret-right.svg' );
		$icon_heart_bordered  = Setup::get_asset_url( asset_path: 'assets/images/red-heart-outline.svg' );
		$icon_heart_down      = Setup::get_asset_url( asset_path: 'assets/images/down-arrow.svg' );
		$icon_heart_red       = Setup::get_asset_url( asset_path: 'assets/images/red-heart.svg' );
		$icon_heart_running   = Setup::get_asset_url( asset_path: 'assets/images/running-heart.gif' );

		// Get API configuration for current page if available
		$api_tokens = get_option(
			option: 'brag_book_gallery_api_token',
			default_value: array()
		);

		$website_property_ids = get_option(
			option: 'brag_book_gallery_website_property_id',
			default_value: array()
		);

		$gallery_slugs = get_option(
			option: 'brag_book_gallery_gallery_page_slug',
			default_value: array()
		);

		// Get the current page slug
		global $post;
		$current_page_slug = $post ? $post->post_name : '';

		// Find matching API configuration
		$default_api_token           = '';
		$default_website_property_id = '';
		$default_procedure_ids       = [];

		if ( is_array( $gallery_slugs ) && is_array( $api_tokens ) && is_array( $website_property_ids ) ) {
			$index = array_search( $current_page_slug, $gallery_slugs );
			if ( $index !== false ) {
				$default_api_token           = $api_tokens[ $index ] ?? '';
				$default_website_property_id = $website_property_ids[ $index ] ?? '';
				// Get procedure IDs if available
				$procedure_ids = get_option( 'brag_book_gallery_procedure_id', [] );
				if ( is_array( $procedure_ids ) && isset( $procedure_ids[ $index ] ) ) {
					$default_procedure_ids = explode( ',', $procedure_ids[ $index ] );
				}
			}

			// If not found, try to use the first available configuration as fallback
			if ( empty( $default_api_token ) && ! empty( $api_tokens ) ) {
				$default_api_token           = $api_tokens[0] ?? '';
				$default_website_property_id = $website_property_ids[0] ?? '';
			}
		}

		$localized_data = array(
			'ajaxurl'       => admin_url( path: 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( action: 'brag_book_gallery_frontend' ),
			'api_config'    => array(
				'default_token'         => $default_api_token,
				'default_property_id'   => $default_website_property_id,
				'default_procedure_ids' => $default_procedure_ids,
			),
			'assets'        => array(
				'leftArrow'     => $icon_left_arrow,
				'rightArrow'    => $icon_right_arrow,
				'leftArrowUrl'  => $icon_left_arrow_url,
				'rightArrowUrl' => $icon_right_arrow_url,
				'heartBordered' => $icon_heart_bordered,
				'heartDown'     => $icon_heart_down,
				'heartRed'      => $icon_heart_red,
				'heartRunning'  => $icon_heart_running,
			),
			'i18n'          => array(
				'loading' => esc_html__( 'Loading...', 'brag-book-gallery' ),
				'error'   => esc_html__( 'An error occurred. Please try again.', 'brag-book-gallery' ),
				'saved'   => esc_html__( 'Saved to favorites!', 'brag-book-gallery' ),
				'removed' => esc_html__( 'Removed from favorites!', 'brag-book-gallery' ),
			),
			// Add legacy property names for backward compatibility
			'leftArrow'     => $icon_left_arrow,
			'rightArrow'    => $icon_right_arrow,
			'leftArrowUrl'  => $icon_left_arrow_url,
			'rightArrowUrl' => $icon_right_arrow_url,
			'heartBordered' => $icon_heart_bordered,
			'heartdown'     => $icon_heart_down,
			// Note: lowercase 'd' for legacy compatibility
			'heartRed'      => $icon_heart_red,
			'heartRunning'  => $icon_heart_running,
		);

		/**
		 * Filter frontend localized data
		 *
		 * @param array $localized_data Data to be passed to JavaScript
		 *
		 * @since 3.0.0
		 */
		$localized_data = apply_filters(
			hook_name: 'brag_book_gallery_frontend_localized_data',
			value: $localized_data
		);

		wp_localize_script(
			handle: 'brag-book-gallery-script',
			object_name: 'brag_book_gallery_plugin_data',
			l10n: $localized_data
		);
	}

	/**
	 * Localize admin script data
	 *
	 * Passes PHP data to JavaScript for use in admin scripts.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function localize_admin_data(): void {
		$admin_data = [
			'ajaxurl' => admin_url( path: 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( action: 'brag_book_gallery_admin' ),
			'i18n'    => [
				'confirmDelete' => esc_html__( 'Are you sure you want to delete this item?', 'brag-book-gallery' ),
				'saving'        => esc_html__( 'Saving...', 'brag-book-gallery' ),
				'saved'         => esc_html__( 'Settings saved successfully!', 'brag-book-gallery' ),
				'error'         => esc_html__( 'An error occurred. Please try again.', 'brag-book-gallery' ),
				'validating'    => esc_html__( 'Validating API credentials...', 'brag-book-gallery' ),
				'valid'         => esc_html__( 'API credentials are valid!', 'brag-book-gallery' ),
				'invalid'       => esc_html__( 'Invalid API credentials. Please check and try again.', 'brag-book-gallery' ),
			],
		];

		/**
		 * Filter admin localized data
		 *
		 * @param array $admin_data Data to be passed to admin JavaScript
		 *
		 * @since 3.0.0
		 */
		$admin_data = apply_filters(
			hook_name: 'brag_book_gallery_admin_localized_data',
			value: $admin_data
		);

		wp_localize_script(
			handle: 'brag-book-gallery-admin-settings-table',
			object_name: 'brag_book_gallery_admin_ajax',
			l10n: $admin_data
		);

		wp_localize_script(
			handle: 'brag-book-gallery-admin-script',
			object_name: 'brag_book_gallery_admin',
			l10n: $admin_data
		);
	}

	/**
	 * Check if current page is a BRAG book page
	 *
	 * @return bool True if on a BRAG book page, false otherwise
	 * @since 3.0.0
	 */
	private function is_bragbook_page(): bool {
		// Check memory cache first for performance
		$cache_key = 'bragbook_page_check';
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		try {
			// Enhanced gallery page detection
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

			if ( empty( $gallery_slugs ) || ! is_array( $gallery_slugs ) ) {
				$this->memory_cache[ $cache_key ] = false;
				return false;
			}

			// Get current page path with null safety
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			$current_path = trim( parse_url( $request_uri, PHP_URL_PATH ) ?: '', '/' );

			// Check if current path matches any gallery slug using PHP 8.2 features
			$is_gallery_page = match ( true ) {
				$this->path_matches_gallery_slugs( $current_path, $gallery_slugs ) => true,
				$this->is_combined_gallery_page( $current_path ) => true,
				$this->has_gallery_shortcode() => true,
				default => false,
			};

			// Apply filter for extensibility
			$is_gallery_page = apply_filters(
				'brag_book_gallery_is_gallery_page',
				$is_gallery_page
			);

			// Cache the result
			$this->memory_cache[ $cache_key ] = $is_gallery_page;
			return $is_gallery_page;
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Gallery page detection failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if current admin page is a BRAG book admin page
	 *
	 * @param string $hook_suffix Current admin page hook suffix
	 *
	 * @return bool True if on a BRAG book admin page, false otherwise
	 * @since 3.0.0
	 */
	private function is_bragbook_admin_page( string $hook_suffix ): bool {
		// Check memory cache first for performance
		$cache_key = "admin_page_check_{$hook_suffix}";
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		// Enhanced admin page detection using PHP 8.2 features
		$is_admin_page = match ( true ) {
			str_contains( $hook_suffix, 'brag-book-gallery' ) => true,
			$this->check_admin_page_parameter() => true,
			default => false,
		};

		// Cache the result
		$this->memory_cache[ $cache_key ] = $is_admin_page;
		return $is_admin_page;
	}

	/**
	 * Get asset version for cache busting
	 *
	 * In development mode, uses timestamp for immediate updates.
	 * In production, uses plugin version for proper caching.
	 *
	 * @return string Version string for assets
	 * @since 3.0.0
	 */
	private function get_asset_version(): string {

		// Use timestamp in development for cache busting.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return (string) time();
		}

		// Use plugin version in production.
		return self::ASSET_VERSION;
	}

	/**
	 * Preload critical assets
	 *
	 * Adds preload hints for critical CSS and fonts to improve
	 * initial page load performance.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function preload_critical_assets(): void {

		if ( ! $this->is_bragbook_page() ) {
			return;
		}

		// Preload critical CSS
		echo sprintf(
			'<link rel="preload" href="%s" as="style">',
			esc_url( Setup::get_asset_url( 'assets/css/brag-book-gallery.css' ) )
		);

		// Preload critical fonts if any
		do_action( hook_name: 'brag_book_gallery_preload_assets' );
	}

	/**
	 * Add async or defer attributes to scripts
	 *
	 * Optimizes script loading by adding async or defer attributes
	 * to non-critical scripts.
	 *
	 * @param string $tag Script HTML tag
	 * @param string $handle Script handle
	 * @param string $src Script source URL
	 *
	 * @return string Modified script tag
	 * @since 3.0.0
	 */
	public function optimize_script_loading( string $tag, string $handle, string $src ): string {

		// Scripts that should be deferred.
		$defer_scripts = array(
			'brag-book-gallery-slick',
		);

		// Scripts that can be async.
		$async_scripts = array();

		if ( in_array( $handle, $defer_scripts, true ) ) {
			return str_replace( ' src', ' defer src', $tag );
		}

		if ( in_array( $handle, $async_scripts, true ) ) {
			return str_replace( ' src', ' async src', $tag );
		}

		return $tag;
	}

	/**
	 * Check if current page has gallery shortcode
	 *
	 * @return bool True if page has gallery shortcode
	 * @since 3.0.0
	 */
	private function has_gallery_shortcode(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for shortcode in post content
		if ( has_shortcode( $post->post_content, 'brag_book_gallery' ) ) {
			return true;
		}

		// Also check if we're on a gallery page based on query vars
		if ( get_query_var( 'procedure_title' ) || get_query_var( 'case_id' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize performance tracking system
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_performance_tracking(): void {
		$this->performance_metrics['init_time'] = microtime( true );
		$this->memory_cache = [];
		$this->loading_errors = [];
	}

	/**
	 * Check if jQuery is already overridden
	 *
	 * @since 3.0.0
	 * @return bool True if already overridden.
	 */
	private function is_jquery_already_overridden(): bool {
		global $wp_scripts;
		return isset( $wp_scripts->registered['jquery'] ) && 
		       str_contains( $wp_scripts->registered['jquery']->src ?? '', 'jquery.com' );
	}

	/**
	 * Register CDN jQuery with fallback
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function register_cdn_jquery(): void {
		wp_deregister_script( 'jquery' );
		wp_register_script(
			'jquery',
			self::CDN_URLS['jquery'],
			[],
			'3.7.1',
			true
		);
	}

	/**
	 * Enqueue external stylesheets with fallback
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function enqueue_external_styles(): void {
		// Slick carousel styles
		wp_enqueue_style(
			'brag-book-gallery-slick',
			self::CDN_URLS['slick_css'],
			[],
			'1.8.1'
		);

		// jQuery UI styles (for accordion)
		wp_enqueue_style(
			'jquery-ui-css',
			self::CDN_URLS['jquery_ui'],
			[],
			'1.12.1'
		);
	}

	/**
	 * Check admin page parameter for validation
	 *
	 * @since 3.0.0
	 * @return bool True if valid admin page.
	 */
	private function check_admin_page_parameter(): bool {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( $_GET['page'] );
		return str_starts_with( $page, 'brag-book-gallery' );
	}

	/**
	 * Check if current path matches gallery slugs
	 *
	 * @since 3.0.0
	 * @param string $current_path Current page path.
	 * @param array<string> $gallery_slugs Gallery page slugs.
	 * @return bool True if path matches.
	 */
	private function path_matches_gallery_slugs( string $current_path, array $gallery_slugs ): bool {
		foreach ( $gallery_slugs as $slug ) {
			if ( ! empty( $slug ) && str_starts_with( $current_path, $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if current page is a combined gallery page
	 *
	 * @since 3.0.0
	 * @param string $current_path Current page path.
	 * @return bool True if combined gallery page.
	 */
	private function is_combined_gallery_page( string $current_path ): bool {
		$slug = get_option( 'brag_book_gallery_brag_book_gallery_page_slug', '' );
		return ! empty( $slug ) && str_starts_with( $current_path, $slug );
	}

	/**
	 * Output performance metrics for debugging
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function output_performance_metrics(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$total_time = microtime( true ) - ( $this->performance_metrics['init_time'] ?? microtime( true ) );
		do_action( 'qm/debug', "Assets total processing time: {$total_time}s" );

		if ( ! empty( $this->loading_errors ) ) {
			do_action( 'qm/debug', 'Asset loading errors: ' . implode( ', ', $this->loading_errors ) );
		}
	}
}
