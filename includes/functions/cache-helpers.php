<?php
/**
 * Cache Helper Functions for WP Engine Compatibility
 *
 * Provides helper functions that automatically use WP Engine object cache
 * when available, falling back to WordPress transients otherwise.
 *
 * @package BRAGBookGallery
 * @since   3.2.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'brag_book_is_wp_engine' ) ) {
	/**
	 * Check if we're running on WP Engine
	 *
	 * @since 3.2.4
	 * @return bool True if running on WP Engine, false otherwise.
	 */
	function brag_book_is_wp_engine(): bool {
		// Check for WP Engine specific constants
		if ( defined( 'WPE_APIKEY' ) || defined( 'PWP_NAME' ) ) {
			return true;
		}

		// Check for WP Engine in server environment
		if ( isset( $_SERVER['HTTP_HOST'] ) && strpos( $_SERVER['HTTP_HOST'], '.wpengine.com' ) !== false ) {
			return true;
		}

		// Check for WP Engine cache directory
		if ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR . '/mu-plugins/wpengine-common' ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'brag_book_set_cache' ) ) {
	/**
	 * Set cache value with WP Engine support
	 *
	 * @since 3.2.4
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	function brag_book_set_cache( string $key, mixed $value, int $expiration = 0 ): bool {
		return true; // Caching disabled
	}
}

if ( ! function_exists( 'brag_book_get_cache' ) ) {
	/**
	 * Get cache value with WP Engine support
	 *
	 * @since 3.2.4
	 * @param string $key Cache key.
	 * @return mixed Cached value or false if not found.
	 */
	function brag_book_get_cache( string $key ): mixed {
		return false; // Caching disabled
	}
}

if ( ! function_exists( 'brag_book_delete_cache' ) ) {
	/**
	 * Delete cache value with WP Engine support
	 *
	 * @since 3.2.4
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	function brag_book_delete_cache( string $key ): bool {
		return true; // Caching disabled
	}
}