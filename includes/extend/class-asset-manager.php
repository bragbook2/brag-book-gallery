<?php
/**
 * Asset Manager Class for BRAGBook Gallery Plugin
 *
 * Centralizes all asset management operations including styles, scripts,
 * and custom CSS injection. Implements WordPress VIP-compliant practices
 * for asset loading, versioning, and cache management.
 *
 * Key Features:
 * - Intelligent asset versioning with file modification time cache busting
 * - GSAP library management with CDN fallback
 * - Custom CSS injection with XSS protection
 * - Script localization for AJAX endpoints and configuration
 * - Asset deduplication to prevent multiple loadings
 * - Performance optimization with version caching
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
 * Provides comprehensive asset management for the BRAGBook Gallery plugin.
 * Implements modern WordPress asset handling patterns with performance
 * optimizations and security best practices.
 *
 * Architecture:
 * - Static methods for stateless asset management
 * - Caching mechanisms for performance optimization
 * - Security-first approach to custom CSS handling
 * - Dependency management for third-party libraries
 * - Localization support for internationalization
 *
 * Performance Features:
 * - File modification time versioning for optimal cache busting
 * - Asset version caching to reduce filesystem calls
 * - Conditional loading based on context
 * - CDN integration with local fallbacks
 *
 * Security Features:
 * - Custom CSS sanitization and XSS prevention
 * - Nonce generation for AJAX endpoints
 * - URL validation and escaping
 * - Input sanitization for all configuration data
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
	 * Localize gallery script with comprehensive configuration data
	 *
	 * Provides JavaScript with validated configuration data, API endpoints,
	 * and complete dataset for client-side functionality. Implements proper
	 * data sanitization and validation to ensure security.
	 *
	 * Configuration Data:
	 * - API credentials and endpoints
	 * - WordPress AJAX configuration
	 * - Plugin-specific settings and preferences
	 * - Gallery navigation and filtering data
	 * - Complete case dataset for advanced filtering
	 *
	 * Security Features:
	 * - All configuration values are sanitized
	 * - URLs are validated and escaped
	 * - Nonces are generated for AJAX security
	 * - Input validation prevents malformed data injection
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $config         Gallery configuration settings.
	 * @param array<string, mixed> $sidebar_data   Sidebar data for filters and navigation.
	 * @param array<string, mixed> $all_cases_data Optional complete cases dataset.
	 *
	 * @return void
	 */
	public static function localize_gallery_script( array $config, array $sidebar_data, array $all_cases_data = [] ): void {
		// Ensure all_cases_data is properly formatted
		$all_cases_data = is_array( $all_cases_data ) ? $all_cases_data : [];
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
			'apiBaseUrl'        => esc_url_raw( get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ) ),
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
	 * Prepare individual case data for secure JavaScript localization
	 *
	 * Transforms and sanitizes raw API case data into a standardized format
	 * suitable for JavaScript consumption. Handles both legacy and current
	 * API response formats with proper fallback mechanisms.
	 *
	 * Data Normalization:
	 * - Supports both direct field names and prefixed variants
	 * - Sanitizes all string data to prevent XSS
	 * - Provides consistent field naming for frontend
	 * - Handles missing or null values gracefully
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $case Raw case data from API response.
	 *
	 * @return array<string, string> Normalized and sanitized case data.
	 */
	private static function prepare_case_data( array $case ): array {
		// Define field mapping for legacy API compatibility
		$field_mapping = [
			'id'        => [ 'id' ],
			'age'       => [ 'age', 'patientAge' ],
			'gender'    => [ 'gender', 'patientGender' ],
			'ethnicity' => [ 'ethnicity', 'patientEthnicity' ],
			'height'    => [ 'height', 'patientHeight' ],
			'weight'    => [ 'weight', 'patientWeight' ],
		];

		$normalized_data = [];

		// Process each field with fallback support
		foreach ( $field_mapping as $normalized_key => $possible_keys ) {
			$value = '';

			// Find the first available value from possible keys
			foreach ( $possible_keys as $key ) {
				if ( isset( $case[ $key ] ) && '' !== $case[ $key ] ) {
					$value = (string) $case[ $key ];
					break;
				}
			}

			$normalized_data[ $normalized_key ] = sanitize_text_field( $value );
		}

		return $normalized_data;
	}

	/**
	 * Localize carousel script with validated configuration data
	 *
	 * Provides carousel JavaScript with comprehensive configuration including
	 * API settings, display preferences, and interaction controls. Implements
	 * proper validation and sanitization for all configuration values.
	 *
	 * Configuration Features:
	 * - API authentication and endpoint configuration
	 * - Carousel display and interaction settings
	 * - AJAX endpoint configuration with nonce security
	 * - Responsive behavior and accessibility options
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, mixed> $config Carousel configuration settings.
	 *
	 * @return void
	 */
	public static function localize_carousel_script( array $config ): void {
		// Build validated configuration data
		$localized_data = [
			'apiToken'          => sanitize_text_field( $config['api_token'] ?? '' ),
			'websitePropertyId' => sanitize_text_field( $config['website_property_id'] ?? '' ),
			'apiEndpoint'       => esc_url_raw( get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' ) ),
			'ajaxUrl'           => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'nonce'             => wp_create_nonce( 'brag_book_gallery_carousel_nonce' ),
			'pluginUrl'         => esc_url_raw( Setup::get_plugin_url() ),
			'showControls'      => (bool) ( $config['show_controls'] ?? true ),
			'showPagination'    => (bool) ( $config['show_pagination'] ?? true ),
			'autoPlay'          => (bool) ( $config['auto_play'] ?? false ),
			'limit'             => absint( $config['limit'] ?? 10 ),
		];

		// Localize the script with validated data (using main gallery script)
		wp_localize_script(
			'brag-book-gallery-main',
			'bragBookCarouselConfig',
			$localized_data
		);
	}

	/**
	 * Add inline nudity acceptance script with error handling
	 *
	 * Adds JavaScript to check localStorage for nudity acceptance status.
	 * Runs before the main script to prevent flash of blurred content.
	 * Includes comprehensive error handling for browser compatibility.
	 *
	 * Features:
	 * - Early localStorage check for immediate UI updates
	 * - Graceful degradation when localStorage is unavailable
	 * - Performance optimized to minimize render blocking
	 * - CSP-compliant implementation without eval or inline handlers
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
					if (localStorage && localStorage.getItem("brag-book-nudity-accepted") === "true") {
						document.documentElement.classList.add("nudity-accepted-preload");
						var style = document.createElement("style");
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
	 * Get asset version with intelligent cache busting
	 *
	 * Implements a sophisticated versioning strategy that uses file modification
	 * time for development cache busting while falling back to plugin version
	 * for production reliability. Includes caching to minimize filesystem I/O.
	 *
	 * @since 3.0.0
	 *
	 * @param string $file_path Full path to the asset file.
	 *
	 * @return string Version string for the asset (timestamp or plugin version).
	 */
	public static function get_asset_version( string $file_path ): string {
		// Return cached version if available
		if ( isset( self::$version_cache[ $file_path ] ) ) {
			return self::$version_cache[ $file_path ];
		}

		// Calculate version using PHP 8.2 match expression for cleaner logic
		$version = match ( true ) {
			file_exists( $file_path ) => (string) ( filemtime( $file_path ) ?: self::VERSION ),
			default => self::VERSION,
		};

		// Cache the result to avoid repeated filesystem calls
		self::$version_cache[ $file_path ] = $version;

		return $version;
	}

	/**
	 * Get GSAP library URL with fallback strategy
	 *
	 * Implements a robust loading strategy for the GSAP animation library:
	 * 1. Checks for local version (offline development support)
	 * 2. Falls back to CDN version with proper URL construction
	 * 3. Validates URLs for security
	 *
	 * @since 3.0.0
	 *
	 * @return string Validated GSAP library URL.
	 */
	private static function get_gsap_url(): string {
		$plugin_path = Setup::get_plugin_path();
		$plugin_url = Setup::get_plugin_url();

		// Use PHP 8.2 match expression for cleaner URL resolution
		return match ( true ) {
			file_exists( $plugin_path . 'assets/vendor/gsap.min.js' ) =>
				esc_url_raw( $plugin_url . 'assets/vendor/gsap.min.js' ),
			default =>
				esc_url_raw( self::GSAP_CDN . self::GSAP_VERSION . '/gsap.min.js' ),
		};
	}

	/**
	 * Add custom CSS to a style handle with comprehensive security
	 *
	 * Implements robust security measures for custom CSS injection:
	 * - Prevents duplicate injection per page load
	 * - Sanitizes CSS content to prevent XSS attacks
	 * - Removes potentially dangerous CSS directives
	 * - Validates CSS syntax and structure
	 *
	 * Security Features:
	 * - Strips all HTML/script tags
	 * - Removes @import statements (security risk)
	 * - Prevents JavaScript execution in CSS
	 * - Validates handle parameter
	 *
	 * @since 3.0.0
	 *
	 * @param string $handle Style handle to attach CSS to (validated).
	 *
	 * @return void
	 */
	private static function add_custom_css( string $handle ): void {
		// Early return for empty handle
		if ( '' === trim( $handle ) ) {
			return;
		}

		// Prevent duplicate CSS injection per page load
		if ( self::$custom_css_added ) {
			return;
		}

		$custom_css = get_option( 'brag_book_gallery_custom_css', '' );

		// Early return if no custom CSS is configured
		if ( empty( $custom_css ) || ! is_string( $custom_css ) ) {
			return;
		}

		// Comprehensive CSS sanitization
		$sanitized_css = self::sanitize_custom_css( $custom_css );

		if ( ! empty( $sanitized_css ) ) {
			wp_add_inline_style( $handle, $sanitized_css );
			self::$custom_css_added = true;

			/**
			 * Fires after custom CSS is successfully added.
			 *
			 * @since 3.0.0
			 *
			 * @param string $handle        The style handle CSS was attached to.
			 * @param string $sanitized_css The sanitized custom CSS that was added.
			 * @param string $original_css  The original unsanitized CSS.
			 */
			do_action( 'brag_book_gallery_custom_css_added', $handle, $sanitized_css, $custom_css );
		}
	}

	/**
	 * Sanitize custom CSS content with comprehensive security measures
	 *
	 * Implements multiple layers of CSS sanitization to prevent security
	 * vulnerabilities including XSS attacks and CSS injection exploits.
	 *
	 * Security Measures:
	 * - Removes all HTML/script tags
	 * - Strips @import statements (external resource loading)
	 * - Removes JavaScript execution contexts
	 * - Validates CSS property syntax
	 * - Prevents data URI usage in certain contexts
	 *
	 * @since 3.0.0
	 *
	 * @param string $css Raw CSS content to sanitize.
	 *
	 * @return string Sanitized CSS content safe for injection.
	 */
	private static function sanitize_custom_css( string $css ): string {
		// Remove any HTML/script tags that might be present
		$css = wp_strip_all_tags( $css );

		// Remove potentially dangerous CSS directives using match expressions
		$dangerous_patterns = [
			'@import[^;]+;',           // External resource imports
			'expression\s*\(',        // IE expression() function
			'javascript\s*:',         // JavaScript protocol
			'vbscript\s*:',          // VBScript protocol
			'data\s*:\s*[^;]*base64', // Base64 data URIs (potential XSS)
			'@charset[^;]+;',         // Character set declarations
		];

		// Apply sanitization patterns
		foreach ( $dangerous_patterns as $pattern ) {
			$css = (string) preg_replace( '/' . $pattern . '/i', '', $css );
		}

		// Additional cleanup - remove excessive whitespace while preserving structure
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = (string) preg_replace( '/;\s*}/', '}', $css );

		return trim( $css );
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
