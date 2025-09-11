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
		// Flush rewrite rules.
		flush_rewrite_rules();
		
		// Trigger action for other components to clear their caches.
		do_action( 'brag_book_gallery_cache_cleared' );
		
		return true; // Caching disabled
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
		return true; // Caching disabled
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
		return true; // Caching disabled
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
		return true; // Caching disabled
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
		return array(
			'count'      => 0,
			'size_bytes' => 0,
			'size_human' => '0 B',
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
		return true; // Caching disabled
	}
}