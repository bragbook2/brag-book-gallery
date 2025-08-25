<?php
/**
 * Asset Manager for BRAGBookGallery plugin.
 *
 * Handles all asset enqueuing and script localization.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;

/**
 * Class Asset_Manager
 *
 * Manages all asset loading and script localization for the plugin.
 *
 * @since 3.0.0
 */
final class Asset_Manager {

	/**
	 * Plugin version for asset versioning.
	 *
	 * @var string
	 */
	private const VERSION = '3.0.0';

	/**
	 * GSAP library version.
	 *
	 * @var string
	 */
	private const GSAP_VERSION = '3.12.2';

	/**
	 * Flag to track if custom CSS has been added.
	 *
	 * @var bool
	 */
	private static $custom_css_added = false;

	/**
	 * Enqueue gallery assets.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function enqueue_gallery_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$plugin_path = Setup::get_plugin_path();
		$version = self::VERSION;

		// Enqueue gallery styles
		wp_enqueue_style(
			handle: 'brag-book-gallery-main',
			src: $plugin_url . 'assets/css/brag-book-gallery.css',
			deps: [],
			ver: $version
		);

		// Add custom CSS only once
		self::add_custom_css( 'brag-book-gallery-main' );

		// Enqueue GSAP library
		wp_enqueue_script(
			handle: 'gsap',
			src: 'https://cdnjs.cloudflare.com/ajax/libs/gsap/' . self::GSAP_VERSION . '/gsap.min.js',
			deps: [],
			ver: self::GSAP_VERSION,
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
	 * Enqueue cases shortcode assets.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function enqueue_cases_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$version = self::VERSION;

		// Enqueue gallery styles (reuse for cases)
		wp_enqueue_style(
			handle: 'brag-book-gallery-main',
			src: $plugin_url . 'assets/css/brag-book-gallery.css',
			deps: [],
			ver: $version
		);

		// Add custom CSS only once
		self::add_custom_css( 'brag-book-gallery-main' );
	}

	/**
	 * Enqueue carousel assets.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function enqueue_carousel_assets(): void {
		$plugin_url = Setup::get_plugin_url();
		$plugin_path = Setup::get_plugin_path();
		$version = self::VERSION;

		// Enqueue carousel styles
		wp_enqueue_style(
			handle: 'brag-book-carousel',
			src: $plugin_url . 'assets/css/brag-book-carousel.css',
			deps: [],
			ver: $version
		);

		// Add custom CSS only if main gallery styles aren't loaded
		if ( ! wp_style_is( 'brag-book-gallery-main', 'enqueued' ) ) {
			self::add_custom_css( 'brag-book-carousel' );
		}

		// Enqueue GSAP library if not already loaded
		if ( ! wp_script_is( 'gsap', 'enqueued' ) ) {
			wp_enqueue_script(
				handle: 'gsap',
				src: 'https://cdnjs.cloudflare.com/ajax/libs/gsap/' . self::GSAP_VERSION . '/gsap.min.js',
				deps: [],
				ver: self::GSAP_VERSION,
				args: true
			);
		}
	}

	/**
	 * Localize gallery script with configuration data.
	 *
	 * @since 3.0.0
	 * @param array $config Gallery configuration.
	 * @param array $sidebar_data Sidebar data.
	 * @param array $all_cases_data All cases data for filtering.
	 * @return void
	 */
	public static function localize_gallery_script( array $config, array $sidebar_data, array $all_cases_data = [] ): void {
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
				'gallerySlug' => get_option( 'brag_book_gallery_page_slug', 'gallery' ),
				'enableSharing' => get_option( 'brag_book_gallery_enable_sharing', 'no' ),
				'infiniteScroll' => get_option( 'brag_book_gallery_infinite_scroll', 'no' ),
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
	 * Localize carousel script with configuration data.
	 *
	 * @since 3.0.0
	 * @param array $config Carousel configuration.
	 * @return void
	 */
	public static function localize_carousel_script( array $config ): void {
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
	 * Add inline nudity acceptance script.
	 *
	 * @since 3.0.0
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
				}
			})();
		';

		wp_add_inline_script( 'brag-book-gallery-main', trim( $inline_script ), 'before' );
	}

	/**
	 * Check if GSAP is already enqueued.
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public static function is_gsap_enqueued(): bool {
		return wp_script_is( 'gsap', 'enqueued' );
	}

	/**
	 * Get asset version with cache busting.
	 *
	 * @since 3.0.0
	 * @param string $file_path Full path to the asset file.
	 * @return string Version string.
	 */
	public static function get_asset_version( string $file_path ): string {
		return file_exists( $file_path ) ? (string) filemtime( $file_path ) : self::VERSION;
	}

	/**
	 * Add custom CSS to a style handle (only once).
	 *
	 * @since 3.0.0
	 * @param string $handle Style handle to attach CSS to.
	 * @return void
	 */
	private static function add_custom_css( string $handle ): void {
		// Only add custom CSS once per page load
		if ( self::$custom_css_added ) {
			return;
		}

		$custom_css = get_option( 'brag_book_gallery_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( $handle, $custom_css );
			self::$custom_css_added = true;
		}
	}
}
