<?php
/**
 * Cache management for BRAGBookGallery plugin.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

/**
 * Class Cache_Manager
 *
 * Handles all cache operations for the BRAGBookGallery plugin.
 *
 * @since 3.0.0
 */
class Cache_Manager {

	/**
	 * Cache duration in seconds (1 hour for production, 1 minute for debug).
	 *
	 * @var int
	 */
	private const CACHE_DURATION = 3600;
	private const DEBUG_CACHE_DURATION = 60;

	/**
	 * Clear all BRAG Book Gallery transient cache.
	 *
	 * @since 3.0.0
	 * @return array{success: bool, message: string} Result array.
	 */
	public static function clear_all_cache(): array {
		global $wpdb;

		// Clear all BRAG book Gallery transients
		$query = "
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '%transient_brag_book_%'
			OR option_name LIKE '%transient_timeout_brag_book_%'
		";

		$deleted = $wpdb->query( $query );

		if ( $deleted !== false ) {
			$count = $deleted / 2; // Each transient has a timeout entry too

			// Also clear object cache if available
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}

			return [
				'success' => true,
				'message' => sprintf( 'Cleared %d cache entries', $count ),
			];
		}

		return [
			'success' => false,
			'message' => 'Failed to clear cache',
		];
	}

	/**
	 * Get cache duration based on debug mode.
	 *
	 * @since 3.0.0
	 * @return int Cache duration in seconds.
	 */
	public static function get_cache_duration(): int {
		return defined( 'WP_DEBUG' ) && WP_DEBUG 
			? self::DEBUG_CACHE_DURATION 
			: self::CACHE_DURATION;
	}

	/**
	 * Set a cached value.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $expiration Optional expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, $value, int $expiration = 0 ): bool {
		if ( $expiration === 0 ) {
			$expiration = self::get_cache_duration();
		}

		return set_transient( $key, $value, $expiration );
	}

	/**
	 * Get a cached value.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return mixed Cached value or false if not found.
	 */
	public static function get( string $key ) {
		return get_transient( $key );
	}

	/**
	 * Delete a cached value.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key ): bool {
		return delete_transient( $key );
	}

	/**
	 * Generate cache key for sidebar data.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @return string Cache key.
	 */
	public static function get_sidebar_cache_key( string $api_token ): string {
		return 'brag_book_sidebar_' . md5( $api_token );
	}

	/**
	 * Generate cache key for cases data.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @param array  $procedure_ids Optional procedure IDs.
	 * @param int    $page Optional page number.
	 * @return string Cache key.
	 */
	public static function get_cases_cache_key( 
		string $api_token, 
		string $website_property_id, 
		array $procedure_ids = [], 
		int $page = 1 
	): string {
		$key_parts = [
			'brag_book_cases',
			md5( $api_token ),
			$website_property_id,
		];

		if ( ! empty( $procedure_ids ) ) {
			sort( $procedure_ids );
			$key_parts[] = implode( '_', $procedure_ids );
		}

		if ( $page > 1 ) {
			$key_parts[] = 'page_' . $page;
		}

		return implode( '_', $key_parts );
	}

	/**
	 * Generate cache key for all cases (used for filtering).
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @return string Cache key.
	 */
	public static function get_all_cases_cache_key( string $api_token, string $website_property_id ): string {
		return 'brag_book_all_cases_' . md5( $api_token . $website_property_id );
	}

	/**
	 * Generate cache key for carousel data.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token.
	 * @param string $website_property_id Website property ID.
	 * @param int    $limit Carousel limit.
	 * @param string $procedure_id Procedure ID or slug (optional).
	 * @param string $member_id Member ID (optional).
	 * @return string Cache key.
	 */
	public static function get_carousel_cache_key( 
		string $api_token, 
		string $website_property_id, 
		int $limit,
		string $procedure_id = '',
		string $member_id = ''
	): string {
		return 'brag_book_carousel_' . md5( $api_token . $website_property_id . $limit . $procedure_id . $member_id );
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @since 3.0.0
	 * @return bool True if caching is enabled.
	 */
	public static function is_caching_enabled(): bool {
		// Allow disabling cache via filter
		return apply_filters( 'brag_book_gallery_enable_cache', true );
	}

	/**
	 * Clear specific type of cache.
	 *
	 * @since 3.0.0
	 * @param string $type Cache type (sidebar, cases, carousel, all).
	 * @return bool True on success.
	 */
	public static function clear_cache_by_type( string $type = 'all' ): bool {
		global $wpdb;

		$pattern = match ( $type ) {
			'sidebar' => '%transient_%brag_book_sidebar_%',
			'cases' => '%transient_%brag_book_cases_%',
			'carousel' => '%transient_%brag_book_carousel_%',
			'all' => '%transient_%brag_book_%',
			default => '%transient_%brag_book_%',
		};

		$query = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$pattern
		);

		return $wpdb->query( $query ) !== false;
	}
}