<?php
/**
 * Cache Handler Trait - Common cache management functionality
 *
 * This trait provides reusable cache management methods for clearing
 * various types of caches used throughout the plugin.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin\Traits
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Traits;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Trait Cache_Handler
 *
 * Provides common cache management functionality for admin classes.
 * Handles transient clearing, rewrite rule flushing, and cache invalidation.
 *
 * @since 3.0.0
 */
trait Trait_Cache_Handler {

	/**
	 * Clear all plugin caches
	 *
	 * Removes all cached data including transients, rewrite rules,
	 * and any other cached information used by the plugin.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @return bool True on success, false on failure
	 */
	protected function clear_plugin_cache(): bool {
		$success = true;
		
		// Clear API cache transients.
		$success = $this->clear_api_cache() && $success;
		
		// Clear gallery cache transients.
		$success = $this->clear_gallery_cache() && $success;
		
		// Flush rewrite rules.
		flush_rewrite_rules();
		
		// Trigger action for other components to clear their caches.
		do_action( 'brag_book_gallery_cache_cleared' );
		
		return $success;
	}

	/**
	 * Clear API cache
	 *
	 * Removes all API-related transients including response caches,
	 * filter caches, and sidebar navigation caches.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @return bool True on success, false on failure
	 */
	protected function clear_api_cache(): bool {
		global $wpdb;
		
		// Delete all transients with our API prefix.
		$transient_patterns = array(
			'brag_book_gallery_api_%',
			'brag_book_gallery_filters_%',
			'brag_book_gallery_transient_sidebar_%',
			'brag_book_gallery_transient_cases_%',
			'brag_book_gallery_transient_carousel_%',
			'brag_book_gallery_transient_all_cases_%',
			'brag_book_gallery_combine_%',
		);
		
		$deleted = 0;
		foreach ( $transient_patterns as $pattern ) {
			$deleted += $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					WHERE option_name LIKE %s 
					OR option_name LIKE %s",
					'_transient_' . $pattern,
					'_transient_timeout_' . $pattern
				)
			);
		}
		
		// Clear object cache if available.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		
		return $deleted > 0;
	}

	/**
	 * Clear gallery cache
	 *
	 * Removes gallery-specific cached data including rendered galleries,
	 * pagination states, and filter options.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @return bool True on success, false on failure
	 */
	protected function clear_gallery_cache(): bool {
		global $wpdb;
		
		// Delete gallery-specific transients.
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_brag_book_gallery_gallery_%' 
			OR option_name LIKE '_transient_timeout_brag_book_gallery_gallery_%'"
		);
		
		return $deleted > 0;
	}

	/**
	 * Clear specific cache type
	 *
	 * Removes cached data for a specific cache type identified by key.
	 * Useful for targeted cache invalidation.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $cache_key The cache key or pattern to clear.
	 * 
	 * @return bool True on success, false on failure
	 */
	protected function clear_cache_by_key( string $cache_key ): bool {
		// Ensure cache_key is not empty to avoid issues
		if ( empty( $cache_key ) ) {
			return false;
		}
		
		// Delete specific transient.
		$deleted = delete_transient( $cache_key );
		
		// Also try to delete pattern-based transients.
		if ( strpos( $cache_key, '%' ) !== false ) {
			global $wpdb;
			
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					WHERE option_name LIKE %s 
					OR option_name LIKE %s",
					'_transient_' . $cache_key,
					'_transient_timeout_' . $cache_key
				)
			);
		}
		
		return (bool) $deleted;
	}

	/**
	 * Get cache statistics
	 *
	 * Retrieves statistics about cached data including count and size.
	 * Useful for debug and monitoring purposes.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @return array Cache statistics array
	 */
	protected function get_cache_stats(): array {
		global $wpdb;
		
		// Count plugin transients.
		$transient_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_brag_book_gallery_%'"
		);
		
		// Calculate approximate size.
		$transient_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_brag_book_gallery_%'"
		);
		
		return array(
			'count'      => intval( $transient_count ),
			'size_bytes' => intval( $transient_size ),
			'size_human' => size_format( intval( $transient_size ) ),
		);
	}

	/**
	 * Schedule cache cleanup
	 *
	 * Schedules a WordPress cron event to periodically clean up expired
	 * cache entries and optimize cache storage.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $recurrence How often to run cleanup (hourly, daily, weekly).
	 * 
	 * @return bool True if scheduled successfully, false otherwise
	 */
	protected function schedule_cache_cleanup( string $recurrence = 'daily' ): bool {
		$hook = 'brag_book_gallery_cache_cleanup';
		
		// Clear any existing schedule.
		wp_clear_scheduled_hook( $hook );
		
		// Schedule new cleanup.
		return wp_schedule_event( time(), $recurrence, $hook );
	}
}