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
	private const ASSET_VERSION = '4.6.0-beta4';

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
		'brag-book-gallery-main',
	];

	/**
	 * Get asset suffix based on SCRIPT_DEBUG.
	 *
	 * Mirrors Asset_Manager so production always serves minified bundles.
	 *
	 * @since 3.3.2
	 * @return string '.min' in production, '' when SCRIPT_DEBUG is true.
	 */
	private function get_asset_suffix(): string {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	}

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

			// Sequential asset loading with error handling. Note that the
			// configuration object (bragBookGalleryConfig) is attached inside
			// enqueue_frontend_scripts() and replaced with the full payload
			// by Asset_Manager when a shortcode actually renders.
			$this->enqueue_frontend_styles();
			$this->enqueue_frontend_scripts();

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
			$suffix = $this->get_asset_suffix();

			// Main plugin styles with enhanced error handling
			$main_css_result = wp_enqueue_style(
				'brag-book-gallery-main',
				Setup::get_asset_url( 'assets/css/brag-book-gallery' . $suffix . '.css' ),
				[],
				$this->get_asset_version()
			);

			if ( ! $main_css_result ) {
				throw new Exception( 'Failed to enqueue main stylesheet' );
			}

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
		$suffix = $this->get_asset_suffix();

		// Main plugin script. Shared handle with Asset_Manager so shortcode-driven
		// enqueues collapse onto a single registration instead of double-loading.
		wp_enqueue_script(
			handle: 'brag-book-gallery-main',
			src: Setup::get_asset_url( 'assets/js/brag-book-gallery' . $suffix . '.js' ),
			deps: array(),
			ver: $this->get_asset_version(),
			args: true
		);

		// Always localize script with AJAX data for forms and galleries
		wp_localize_script( 'brag-book-gallery-main', 'bragBookGalleryConfig', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'brag_book_gallery_nonce' ),
			'consultation_nonce' => wp_create_nonce( 'consultation_form_nonce' ),
			'columns' => absint( get_option( 'brag_book_gallery_columns', 2 ) ),
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
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// Admin styles.
		wp_enqueue_style(
			handle: 'brag-book-gallery-admin-style',
			src: Setup::get_asset_url( asset_path: 'assets/css/brag-book-gallery-admin' . $suffix . '.css' ),
			deps: array(),
			ver: $this->get_asset_version()
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_admin_scripts(): void {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// Admin main script
		wp_enqueue_script(
			handle: 'brag-book-gallery-admin-script',
			src: Setup::get_asset_url( 'assets/js/brag-book-gallery-admin' . $suffix . '.js' ),
			deps: array(),
			ver: $this->get_asset_version(),
			args: true
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
		$cache_key = 'brag_book_gallery_transient_page_check';
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		try {
			// Exclude procedure taxonomy pages - they should not load gallery assets
			if ( is_tax( 'procedures' ) ) {
				$this->memory_cache[ $cache_key ] = false;
				return false;
			}

			// Enhanced gallery page detection
			$gallery_slugs = get_option( 'brag_book_gallery_gallery_page_slug', [] );

			if ( empty( $gallery_slugs ) || ! is_array( $gallery_slugs ) ) {
				$this->memory_cache[ $cache_key ] = false;
				return false;
			}

			// Get current page path with null safety
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			$current_path = trim( wp_parse_url( $request_uri, PHP_URL_PATH ) ?: '', '/' );

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
		$cache_key = "brag_book_gallery_transient_admin_page_check_{$hook_suffix}";
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

		// Use timestamp only when SCRIPT_DEBUG is on so staging sites with
		// WP_DEBUG enabled still benefit from version-based caching.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
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

		$suffix = $this->get_asset_suffix();

		// Preload critical CSS using the same minified path the enqueue uses,
		// otherwise the browser fetches both files.
		echo sprintf(
			'<link rel="preload" href="%s" as="style">',
			esc_url( Setup::get_asset_url( 'assets/css/brag-book-gallery' . $suffix . '.css' ) )
		);

		// Preconnect to the BRAGbook API origin since every gallery page hits it
		// for case data and image delivery.
		$api_endpoint = (string) get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );
		$api_origin   = wp_parse_url( $api_endpoint, PHP_URL_SCHEME ) && wp_parse_url( $api_endpoint, PHP_URL_HOST )
			? wp_parse_url( $api_endpoint, PHP_URL_SCHEME ) . '://' . wp_parse_url( $api_endpoint, PHP_URL_HOST )
			: '';
		if ( $api_origin !== '' ) {
			printf(
				'<link rel="preconnect" href="%1$s" crossorigin><link rel="dns-prefetch" href="%1$s">',
				esc_url( $api_origin )
			);
		}

		// Preload primary self-hosted fonts so they're discovered before CSS parses.
		$fonts = [
			'assets/fonts/poppins/Poppins-Regular.woff2',
			'assets/fonts/lato/Lato-Regular.woff2',
		];
		foreach ( $fonts as $font_path ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>',
				esc_url( Setup::get_asset_url( $font_path ) )
			);
		}

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

		// Scripts that should be deferred. The main bundle ships in the footer
		// already; defer lets the browser parse it in parallel with HTML and
		// keeps it out of the critical path.
		$defer_scripts = array(
			'brag-book-gallery-main',
		);

		// Scripts that can be async.
		$async_scripts = array();

		if ( in_array( $handle, $defer_scripts, true ) ) {
			if ( ! str_contains( $tag, ' defer' ) ) {
				return str_replace( ' src', ' defer src', $tag );
			}
		}

		if ( in_array( $handle, $async_scripts, true ) ) {
			if ( ! str_contains( $tag, ' async' ) ) {
				return str_replace( ' src', ' async src', $tag );
			}
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
				// Ensure we match the full slug segment, not just a prefix
				// e.g. slug "before-after" should match "before-after/foo" but not "before-after-columbia"
				$remaining = substr( $current_path, strlen( $slug ) );
				if ( $remaining === '' || $remaining === false || $remaining[0] === '/' ) {
					return true;
				}
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
		if ( empty( $slug ) || ! str_starts_with( $current_path, $slug ) ) {
			return false;
		}
		$remaining = substr( $current_path, strlen( $slug ) );
		return $remaining === '' || $remaining === false || $remaining[0] === '/';
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
