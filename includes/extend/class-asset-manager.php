<?php
/**
 * Asset Manager Class
 *
 * Manages all asset loading, script localization, and custom CSS injection.
 * Handles enqueuing of styles and scripts with proper dependencies and versioning.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Manager Class
 *
 * Centralizes asset management for the plugin including styles, scripts,
 * and custom CSS injection. Ensures assets are loaded only once per page
 * and provides proper localization for JavaScript files.
 *
 * @since 3.0.0
 */
final class Asset_Manager {

	/**
	 * Plugin version for asset versioning
	 *
	 * Used as fallback when file modification time is unavailable.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const VERSION = '3.0.0';

	/**
	 * GSAP library version
	 *
	 * Version of the GreenSock Animation Platform library.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const GSAP_VERSION = '3.12.2';

	/**
	 * GSAP CDN URL pattern
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const GSAP_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/gsap/';

	/**
	 * Flag to track if custom CSS has been added
	 *
	 * Prevents duplicate injection of custom CSS.
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private static $custom_css_added = false;

	/**
	 * Cache for asset versions
	 *
	 * Stores calculated asset versions to avoid repeated file system calls.
	 *
	 * @since 3.0.0
	 * @var array<string, string>
	 */
	private static $version_cache = array();

	/**
	 * Enqueue gallery assets
	 *
	 * Loads all necessary styles and scripts for the gallery functionality.
	 * Includes GSAP for animations and custom CSS if configured.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function enqueue_gallery_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$plugin_path = Setup::get_plugin_path();
		$version = self::VERSION;

		// Enqueue gallery styles.
		wp_enqueue_style(
			'brag-book-gallery-main',
			$plugin_url . 'assets/css/brag-book-gallery.css',
			array(),
			$version
		);

		// Add custom CSS only once.
		self::add_custom_css( 'brag-book-gallery-main' );

		// Check if GSAP should be loaded from CDN or locally.
		$gsap_url = self::get_gsap_url();
		
		// Enqueue GSAP library.
		wp_enqueue_script(
			'gsap',
			$gsap_url,
			array(),
			self::GSAP_VERSION,
			true
		);

		// Get file modification time for cache busting.
		$js_file = $plugin_path . 'assets/js/brag-book-gallery.js';
		$js_version = self::get_asset_version( $js_file );

		// Enqueue main gallery script.
		wp_enqueue_script(
			'brag-book-gallery-main',
			$plugin_url . 'assets/js/brag-book-gallery.js',
			array( 'gsap' ),
			$js_version,
			true
		);
		
		/**
		 * Fires after gallery assets are enqueued.
		 *
		 * @since 3.0.0
		 *
		 * @param string $plugin_url  The plugin URL.
		 * @param string $js_version  The JavaScript file version.
		 */
		do_action( 'brag_book_gallery_assets_enqueued', $plugin_url, $js_version );
	}

	/**
	 * Enqueue cases shortcode assets
	 *
	 * Loads styles required for the cases shortcode display.
	 * Reuses gallery styles for consistency.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function enqueue_cases_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$version = self::VERSION;

		// Enqueue gallery styles (reuse for cases).
		wp_enqueue_style(
			'brag-book-gallery-main',
			$plugin_url . 'assets/css/brag-book-gallery.css',
			array(),
			$version
		);

		// Add custom CSS only once.
		self::add_custom_css( 'brag-book-gallery-main' );
		
		/**
		 * Fires after cases assets are enqueued.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_cases_assets_enqueued' );
	}

	/**
	 * Localize gallery script with configuration data
	 *
	 * Provides JavaScript with necessary configuration data and API endpoints.
	 * Includes sidebar data and complete dataset for client-side filtering.
	 *
	 * @since 3.0.0
	 *
	 * @param array $config         Gallery configuration settings.
	 * @param array $sidebar_data   Sidebar data for filters and navigation.
	 * @param array $all_cases_data Optional. Complete cases dataset for filtering.
	 *
	 * @return void
	 */
	public static function localize_gallery_script( array $config, array $sidebar_data, array $all_cases_data = array() ): void {
		$plugin_url = Setup::get_plugin_url();
		
		// Get gallery slug - handle both array and string formats.
		$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		if ( is_array( $gallery_slug_option ) ) {
			// Use Slug_Helper to get the first slug.
			$gallery_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( 'gallery' );
		} else {
			$gallery_slug = sanitize_title( (string) $gallery_slug_option );
		}
		
		// Sanitize and prepare configuration data.
		$localized_data = array(
			'apiToken'          => sanitize_text_field( $config['api_token'] ?? '' ),
			'websitePropertyId' => sanitize_text_field( $config['website_property_id'] ?? '' ),
			'apiEndpoint'       => esc_url_raw( get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ) ),
			'ajaxUrl'           => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'nonce'             => wp_create_nonce( 'brag_book_gallery_nonce' ),
			'pluginUrl'         => esc_url_raw( $plugin_url ),
			'gallerySlug'       => $gallery_slug,
			'enableSharing'     => sanitize_text_field( get_option( 'brag_book_gallery_enable_sharing', 'no' ) ),
			'infiniteScroll'    => sanitize_text_field( get_option( 'brag_book_gallery_infinite_scroll', 'no' ) ),
			'sidebarData'       => $sidebar_data,
			'completeDataset'   => array(),
		);
		
		// Process complete dataset if provided.
		if ( ! empty( $all_cases_data['data'] ) && is_array( $all_cases_data['data'] ) ) {
			$localized_data['completeDataset'] = array_map( 
				array( __CLASS__, 'prepare_case_data' ), 
				$all_cases_data['data'] 
			);
		}

		wp_localize_script(
			'brag-book-gallery-main',
			'bragBookGalleryConfig',
			$localized_data
		);
	}

	/**
	 * Prepare individual case data for localization
	 *
	 * Normalizes case data structure for consistent JavaScript access.
	 *
	 * @since 3.0.0
	 *
	 * @param array $case Raw case data from API.
	 *
	 * @return array Normalized case data.
	 */
	private static function prepare_case_data( array $case ): array {
		return array(
			'id'        => sanitize_text_field( $case['id'] ?? '' ),
			'age'       => sanitize_text_field( $case['age'] ?? $case['patientAge'] ?? '' ),
			'gender'    => sanitize_text_field( $case['gender'] ?? $case['patientGender'] ?? '' ),
			'ethnicity' => sanitize_text_field( $case['ethnicity'] ?? $case['patientEthnicity'] ?? '' ),
			'height'    => sanitize_text_field( $case['height'] ?? $case['patientHeight'] ?? '' ),
			'weight'    => sanitize_text_field( $case['weight'] ?? $case['patientWeight'] ?? '' ),
		);
	}

	/**
	 * Localize carousel script with configuration data
	 *
	 * Provides carousel JavaScript with necessary configuration and API settings.
	 *
	 * @since 3.0.0
	 *
	 * @param array $config Carousel configuration settings.
	 *
	 * @return void
	 */
	public static function localize_carousel_script( array $config ): void {
		$localized_data = array(
			'apiToken'          => sanitize_text_field( $config['api_token'] ?? '' ),
			'websitePropertyId' => sanitize_text_field( $config['website_property_id'] ?? '' ),
			'apiEndpoint'       => esc_url_raw( get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ) ),
			'ajaxUrl'           => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'nonce'             => wp_create_nonce( 'brag_book_carousel_nonce' ),
			'pluginUrl'         => esc_url_raw( Setup::get_plugin_url() ),
			'showControls'      => (bool) ( $config['show_controls'] ?? true ),
			'showPagination'    => (bool) ( $config['show_pagination'] ?? true ),
			'autoPlay'          => (bool) ( $config['auto_play'] ?? false ),
			'limit'             => absint( $config['limit'] ?? 10 ),
		);
		
		wp_localize_script(
			'brag-book-carousel',
			'bragBookCarouselConfig',
			$localized_data
		);
	}

	/**
	 * Add inline nudity acceptance script
	 *
	 * Adds JavaScript to check localStorage for nudity acceptance status.
	 * This runs before main script to prevent flash of blurred content.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function add_nudity_acceptance_script(): void {
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
					if (typeof console !== "undefined" && console.warn) {
						console.warn("BRAGBook Gallery: localStorage not available");
					}
				}
			})();
		';

		wp_add_inline_script( 'brag-book-gallery-main', trim( $inline_script ), 'before' );
	}

	/**
	 * Check if GSAP is already enqueued
	 *
	 * Determines if the GSAP animation library has been enqueued.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if GSAP is enqueued, false otherwise.
	 */
	public static function is_gsap_enqueued(): bool {
		return wp_script_is( 'gsap', 'enqueued' );
	}

	/**
	 * Get asset version with cache busting
	 *
	 * Returns file modification time as version for cache busting,
	 * falling back to plugin version if file doesn't exist.
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path Full path to the asset file.
	 *
	 * @return string Version string for the asset.
	 */
	public static function get_asset_version( string $file_path ): string {
		// Check cache first.
		if ( isset( self::$version_cache[ $file_path ] ) ) {
			return self::$version_cache[ $file_path ];
		}
		
		// Calculate version.
		$version = file_exists( $file_path ) 
			? (string) filemtime( $file_path ) 
			: self::VERSION;
			
		// Cache the result.
		self::$version_cache[ $file_path ] = $version;
		
		return $version;
	}

	/**
	 * Get GSAP library URL
	 *
	 * Returns the URL for GSAP library, checking for local availability first.
	 *
	 * @since 3.0.0
	 *
	 * @return string GSAP library URL.
	 */
	private static function get_gsap_url(): string {
		// Check if a local version exists (for offline development).
		$local_gsap = Setup::get_plugin_path() . 'assets/vendor/gsap.min.js';
		if ( file_exists( $local_gsap ) ) {
			return Setup::get_plugin_url() . 'assets/vendor/gsap.min.js';
		}
		
		// Use CDN version.
		return self::GSAP_CDN . self::GSAP_VERSION . '/gsap.min.js';
	}

	/**
	 * Add custom CSS to a style handle
	 *
	 * Injects custom CSS from plugin settings into the page.
	 * Ensures CSS is only added once per page load.
	 *
	 * @since 3.0.0
	 *
	 * @param string $handle Style handle to attach CSS to.
	 *
	 * @return void
	 */
	private static function add_custom_css( string $handle ): void {
		// Only add custom CSS once per page load.
		if ( self::$custom_css_added ) {
			return;
		}

		$custom_css = get_option( 'brag_book_gallery_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			// Sanitize CSS - remove any script tags or potentially harmful content.
			$custom_css = wp_strip_all_tags( $custom_css );
			
			// Remove any @import statements for security.
			$custom_css = preg_replace( '/@import[^;]+;/i', '', $custom_css );
			
			// Add the sanitized CSS.
			wp_add_inline_style( $handle, $custom_css );
			self::$custom_css_added = true;
			
			/**
			 * Fires after custom CSS is added.
			 *
			 * @since 3.0.0
			 *
			 * @param string $handle     The style handle CSS was attached to.
			 * @param string $custom_css The custom CSS that was added.
			 */
			do_action( 'brag_book_gallery_custom_css_added', $handle, $custom_css );
		}
	}

	/**
	 * Reset custom CSS flag
	 *
	 * Resets the flag tracking whether custom CSS has been added.
	 * Useful for testing or when multiple separate renders occur.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function reset_custom_css_flag(): void {
		self::$custom_css_added = false;
	}

	/**
	 * Clear version cache
	 *
	 * Clears the internal cache of asset versions.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function clear_version_cache(): void {
		self::$version_cache = array();
	}
}