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

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets management class
 *
 * This class is responsible for:
 * - Enqueueing frontend and admin styles
 * - Enqueueing frontend and admin scripts
 * - Managing script dependencies
 * - Localizing script data
 * - Optimizing asset loading
 *
 * @since 3.0.0
 */
class Assets {

	/**
	 * Asset version for cache busting
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const ASSET_VERSION = '3.0.0';

	/**
	 * CDN URLs for external dependencies
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
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function init_hooks(): void {
		// Enqueue assets with appropriate priority
		add_action( 'wp_enqueue_scripts', [
			$this,
			'enqueue_frontend_assets'
		], 10 );
		add_action( 'admin_enqueue_scripts', [
			$this,
			'enqueue_admin_assets'
		], 10 );

		// Register jQuery override if needed
		add_action( 'wp_enqueue_scripts', [
			$this,
			'maybe_override_jquery'
		], 1 );
		add_action( 'admin_enqueue_scripts', [
			$this,
			'maybe_override_jquery'
		], 1 );
	}

	/**
	 * Maybe override WordPress jQuery with CDN version
	 *
	 * Only overrides if jQuery is not already registered or if using older version.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function maybe_override_jquery(): void {
		// Check if we should use CDN jQuery
		$use_cdn_jquery = apply_filters( 'brag_book_gallery_use_cdn_jquery', true );

		if ( ! $use_cdn_jquery ) {
			return;
		}

		// Deregister WordPress jQuery and register CDN version
		if ( ! is_admin() ) {
			wp_deregister_script( 'jquery' );
			wp_register_script(
				'jquery',
				self::CDN_URLS['jquery'],
				[],
				'3.7.1',
				true
			);
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
		// Bail early if not on a BRAG Book page
		if ( ! $this->is_bragbook_page() ) {
			return;
		}

		// Enqueue styles
		$this->enqueue_frontend_styles();

		// Enqueue scripts
		$this->enqueue_frontend_scripts();

		// Localize script data
		$this->localize_frontend_data();
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads all necessary styles and scripts for the WordPress admin area,
	 * but only on BRAG Book admin pages to avoid conflicts.
	 *
	 * @param string $hook_suffix The current admin page hook suffix
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Only load on our admin pages
		if ( ! $this->is_bragbook_admin_page( $hook_suffix ) ) {
			return;
		}

		// Enqueue admin styles
		$this->enqueue_admin_styles();

		// Enqueue admin scripts
		$this->enqueue_admin_scripts();

		// Localize admin data
		$this->localize_admin_data();
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_frontend_styles(): void {
		// Main plugin styles
		wp_enqueue_style(
			'brag-book-gallery-style',
			Setup::get_asset_url( 'assets/css/brag-book-gallery.css' ),
			[],
			$this->get_asset_version()
		);

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

		// Allow themes to add custom styles
		do_action( 'brag_book_gallery_frontend_styles_enqueued' );
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_frontend_scripts(): void {

		// Ensure jQuery is loaded.
		wp_enqueue_script( handle: 'jquery' );

		// jQuery UI Accordion.
		wp_enqueue_script( handle: 'jquery-ui-accordion' );

		// Slick carousel.
		wp_enqueue_script(
			handle: 'brag-book-gallery-slick',
			src: self::CDN_URLS['slick_js'],
			deps: array(
				'jquery'
			),
			ver: '1.8.1',
			args: true
		);

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

		// Settings table management script
		wp_enqueue_script(
			handle: 'brag-book-gallery-admin-settings-table',
			src: Setup::get_asset_url( 'assets/js/brag-book-gallery-admin-settings-table-add-row.js' ),
			deps: array( 'jquery' ),
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
	 * Check if current page is a BRAG Book page
	 *
	 * @return bool True if on a BRAG Book page, false otherwise
	 * @since 3.0.0
	 */
	private function is_bragbook_page(): bool {

		// Check if we have gallery pages configured.
		$gallery_slugs = get_option(
			option: 'brag_book_gallery_gallery_page_slug',
			default_value: array()
		);

		if ( empty( $gallery_slugs ) || ! is_array( $gallery_slugs ) ) {
			return false;
		}

		// Get current page path
		$current_path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

		// Check if current path starts with any gallery slug
		foreach ( $gallery_slugs as $slug ) {
			if ( ! empty( $slug ) && str_starts_with( $current_path, $slug ) ) {
				return true;
			}
		}

		// Check for combined gallery page
		$combine_slug = get_option( 'brag_book_gallery_combine_gallery_slug', '' );
		if ( ! empty( $combine_slug ) && str_starts_with( $current_path, $combine_slug ) ) {
			return true;
		}

		/**
		 * Filter whether current page is a BRAG Book page
		 *
		 * @param bool $is_bragbook_page Whether current page is a BRAG Book page
		 *
		 * @since 3.0.0
		 */
		return apply_filters(
			hook_name: 'brag_book_gallery_is_gallery_page',
			value: false
		);
	}

	/**
	 * Check if current admin page is a BRAG Book admin page
	 *
	 * @param string $hook_suffix Current admin page hook suffix
	 *
	 * @return bool True if on a BRAG Book admin page, false otherwise
	 * @since 3.0.0
	 */
	private function is_bragbook_admin_page( string $hook_suffix ): bool {
		return in_array(
			$hook_suffix,
			array(
				'brag-book-gallery-settings',
				'brag-book-gallery-api-settings',
				'brag-book-gallery-quick-start',
				'brag-book-gallery-consultation',
			),
			true
		);
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
}
