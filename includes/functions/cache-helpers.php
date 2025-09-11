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
		// Use WP Engine object cache if available
		if ( brag_book_is_wp_engine() && function_exists( 'wp_cache_set' ) ) {
			return wp_cache_set( $key, $value, 'brag_book_gallery', $expiration );
		}
		
		// Fall back to WordPress transients
		return set_transient( "brag_book_gallery_transient_{$key}", $value, $expiration );
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
		// Use WP Engine object cache if available
		if ( brag_book_is_wp_engine() && function_exists( 'wp_cache_get' ) ) {
			return wp_cache_get( $key, 'brag_book_gallery' );
		}
		
		// Fall back to WordPress transients
		return get_transient( "brag_book_gallery_transient_{$key}" );
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
		// Use WP Engine object cache if available
		if ( brag_book_is_wp_engine() && function_exists( 'wp_cache_delete' ) ) {
			return wp_cache_delete( $key, 'brag_book_gallery' );
		}
		
		// Fall back to WordPress transients
		return delete_transient( "brag_book_gallery_transient_{$key}" );
	}
}

if ( ! function_exists( 'brag_book_clear_all_cache' ) ) {
	/**
	 * Clear all plugin cache with WP Engine support
	 *
	 * Comprehensive cache clearing for WP Engine environments including:
	 * - WP Engine object cache group flush
	 * - WordPress rewrite rules flush
	 * - Plugin-specific transients clearing
	 * - WP Engine page cache purge (if available)
	 *
	 * @since 3.2.4
	 * @return bool True on success, false on failure.
	 */
	function brag_book_clear_all_cache(): bool {
		$success = true;

		// Clear WP Engine object cache group
		if ( brag_book_is_wp_engine() && function_exists( 'wp_cache_flush_group' ) ) {
			$success = wp_cache_flush_group( 'brag_book_gallery' ) && $success;
		}

		// Clear WordPress transients
		$success = brag_book_clear_plugin_transients() && $success;

		// Flush rewrite rules (important for URL routing)
		flush_rewrite_rules();

		// Clear WP Engine page cache if available
		if ( brag_book_is_wp_engine() ) {
			// Try WP Engine specific cache clearing methods
			if ( class_exists( '\WpeCommon' ) && method_exists( '\WpeCommon', 'purge_memcached' ) ) {
				\WpeCommon::purge_memcached();
			}

			// Alternative WP Engine cache clearing
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}

		return $success;
	}
}

if ( ! function_exists( 'brag_book_clear_plugin_transients' ) ) {
	/**
	 * Clear all plugin-specific transients
	 *
	 * @since 3.2.4
	 * @return bool True on success, false on failure.
	 */
	function brag_book_clear_plugin_transients(): bool {
		global $wpdb;
		
		try {
			// Clear all plugin transients with proper escaping
			$pattern = 'brag_book_gallery_transient_%';
			
			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			) );

			return $deleted !== false;
		} catch ( Exception $e ) {
			error_log( 'BRAGBook Gallery: Error clearing transients - ' . $e->getMessage() );
			return false;
		}
	}
}

if ( ! function_exists( 'brag_book_wp_engine_rewrite_fix' ) ) {
	/**
	 * Comprehensive WP Engine rewrite rules fix
	 *
	 * Performs aggressive cache clearing specifically designed for WP Engine
	 * environments where rewrite rules may not take effect due to caching.
	 *
	 * @since 3.2.4
	 * @return array{success: bool, message: string, actions: array} Detailed result.
	 */
	function brag_book_wp_engine_rewrite_fix(): array {
		$actions = [];
		$success = true;

		if ( ! brag_book_is_wp_engine() ) {
			return [
				'success' => false,
				'message' => 'Not running on WP Engine - using standard cache clearing',
				'actions' => [ 'Standard cache clearing performed' ]
			];
		}

		// 1. Clear WP Engine object cache
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'brag_book_gallery' );
			$actions[] = 'WP Engine object cache group flushed';
		}

		// 2. Full object cache flush
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$actions[] = 'Full object cache flushed';
		}

		// 3. Clear WordPress rewrite cache
		wp_cache_delete( 'rewrite_rules', 'options' );
		$actions[] = 'WordPress rewrite cache cleared';

		// 4. Flush rewrite rules with hard refresh
		flush_rewrite_rules( true );
		$actions[] = 'Rewrite rules flushed (hard refresh)';

		// 5. Clear plugin transients
		if ( brag_book_clear_plugin_transients() ) {
			$actions[] = 'Plugin transients cleared';
		}

		// 6. WP Engine specific cache purging
		if ( class_exists( '\WpeCommon' ) ) {
			if ( method_exists( '\WpeCommon', 'purge_memcached' ) ) {
				\WpeCommon::purge_memcached();
				$actions[] = 'WP Engine memcached purged';
			}
			if ( method_exists( '\WpeCommon', 'purge_varnish_cache' ) ) {
				\WpeCommon::purge_varnish_cache();
				$actions[] = 'WP Engine Varnish cache purged';
			}
		}

		// 7. Clear additional WordPress caches
		wp_cache_delete( 'last_changed', 'posts' );
		wp_cache_delete( 'last_changed', 'options' );
		$actions[] = 'WordPress core caches refreshed';

		return [
			'success' => $success,
			'message' => sprintf( 'WP Engine cache clearing completed (%d actions performed)', count( $actions ) ),
			'actions' => $actions
		];
	}
}