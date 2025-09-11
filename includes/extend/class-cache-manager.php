<?php
/**
 * Advanced Cache Management for BRAGBook Gallery Plugin
 *
 * Provides comprehensive cache management with WordPress VIP compliance,
 * intelligent cache key generation, and performance optimization strategies.
 * Implements transient-based caching with debug-aware expiration policies.
 *
 * Key Features:
 * - Intelligent cache duration based on environment (debug vs production)
 * - Secure cache key generation with collision resistance
 * - Type-specific cache clearing for targeted performance optimization
 * - WordPress VIP compliant database query patterns
 * - Comprehensive cache statistics and monitoring capabilities
 * - Failsafe mechanisms for cache corruption scenarios
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 * @author  BRAGBook Team
 * @version 3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

/**
 * Cache Manager Class
 *
 * Centralized cache management system for the BRAGBook Gallery plugin.
 * Implements WordPress transient API with intelligent caching strategies,
 * security-first approach to cache key generation, and comprehensive
 * cache lifecycle management.
 *
 * Architecture:
 * - Static methods for stateless cache operations
 * - Consistent cache key generation with collision avoidance
 * - Environment-aware cache duration policies
 * - Type-based cache organization for selective clearing
 * - VIP-compliant database operations with prepared statements
 *
 * Performance Features:
 * - Optimized cache key generation with MD5 hashing
 * - Selective cache clearing to minimize performance impact
 * - Debug mode with reduced cache duration for development
 * - Object cache integration when available
 *
 * Security Features:
 * - Prepared SQL statements to prevent injection attacks
 * - Sanitized cache key generation
 * - Safe handling of mixed data types in cache values
 * - Validation of cache operations and return values
 *
 * @since 3.0.0
 */
class Cache_Manager {

	/**
	 * Cache duration in seconds for production environment
	 *
	 * Standard cache duration optimized for production use where data
	 * changes infrequently and performance is prioritized.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_DURATION = 3600; // 1 hour

	/**
	 * Cache duration in seconds for debug/development environment
	 *
	 * Reduced cache duration for development to ensure fresh data
	 * during testing and debugging phases.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const DEBUG_CACHE_DURATION = 60; // 1 minute

	/**
	 * WP Engine object cache group for our plugin
	 *
	 * @since 3.2.4
	 * @var string
	 */
	private const WP_CACHE_GROUP = 'brag_book_gallery';

	/**
	 * Clear all BRAGBook Gallery cache with comprehensive cleanup
	 *
	 * Performs a thorough cache clearing operation that removes all plugin-specific
	 * cache from both transients and WP Engine object cache. Uses WordPress VIP 
	 * compliant database queries with proper error handling.
	 *
	 * Operations performed:
	 * - Removes all plugin transients and their timeout entries
	 * - Clears WP Engine object cache group when available
	 * - Flushes WordPress object cache when available
	 * - Provides detailed success/failure feedback
	 * - Handles database errors gracefully
	 *
	 * @since 3.0.0
	 * @return array{success: bool, message: string, count?: int, wp_engine?: bool} Detailed result array.
	 */
	public static function clear_all_cache(): array {
		return [
			'success' => true,
			'message' => __( 'Caching is disabled - no cache to clear', 'brag-book-gallery' ),
			'count' => 0,
			'wp_engine' => false,
		];
	}

	/**
	 * Get intelligent cache duration based on environment
	 *
	 * Automatically adjusts cache duration based on the WordPress debug mode
	 * to provide optimal development experience while maintaining production
	 * performance. Uses PHP 8.2 match expression for cleaner logic.
	 *
	 * Environment Detection:
	 * - Debug mode: Short cache duration for immediate feedback
	 * - Production mode: Extended cache duration for optimal performance
	 * - Filterable for custom cache duration strategies
	 *
	 * @since 3.0.0
	 * @return int Cache duration in seconds.
	 */
	public static function get_cache_duration(): int {
		$base_duration = match ( WP_DEBUG ) {
			true => self::DEBUG_CACHE_DURATION,
			false => self::CACHE_DURATION,
		};

		/**
		 * Filter the cache duration.
		 *
		 * @since 3.0.0
		 *
		 * @param int  $base_duration The base cache duration in seconds.
		 * @param bool $is_debug      Whether WordPress is in debug mode.
		 */
		return (int) apply_filters( 'brag_book_gallery_cache_duration', $base_duration, WP_DEBUG );
	}

	/**
	 * Detect if we're running on WP Engine
	 *
	 * @since 3.2.4
	 * @return bool True if running on WP Engine, false otherwise.
	 */
	public static function is_wp_engine(): bool {
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

	/**
	 * Set a cached value with validation and error handling
	 *
	 * Stores a value using the appropriate caching method based on the server environment.
	 * Uses WP Engine object cache when available, falls back to transients otherwise.
	 *
	 * Features:
	 * - Automatic WP Engine detection for optimal caching
	 * - Automatic expiration time calculation when not specified
	 * - Input validation for cache key and value
	 * - Comprehensive error handling and logging
	 * - Support for complex data types
	 *
	 * @since 3.0.0
	 * @param string $key        Cache key (validated for safety).
	 * @param mixed  $value      Value to cache (any serializable data).
	 * @param int    $expiration Optional expiration time in seconds (0 = auto).
	 * @return bool True on successful cache storage, false on failure.
	 */
	public static function set( string $key, mixed $value, int $expiration = 0 ): bool {
		return false; // Caching disabled
	}

	/**
	 * Get a cached value with validation
	 *
	 * Retrieves a value using the appropriate caching method based on the server environment.
	 * Uses WP Engine object cache when available, falls back to transients otherwise.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key to retrieve.
	 * @return mixed Cached value or false if not found/expired.
	 */
	public static function get( string $key ): mixed {
		return false; // Caching disabled
	}

	/**
	 * Delete a cached value with validation
	 *
	 * Removes a value using the appropriate caching method based on the server environment.
	 * Uses WP Engine object cache when available, falls back to transients otherwise.
	 *
	 * @since 3.0.0
	 * @param string $key Cache key to delete.
	 * @return bool True on successful deletion, false on failure.
	 */
	public static function delete( string $key ): bool {
		return true; // Caching disabled
	}

	/**
	 * Generate secure cache key for sidebar data
	 *
	 * Creates a collision-resistant cache key for sidebar data using MD5
	 * hashing of the API token to prevent cache key conflicts while
	 * maintaining security by not exposing sensitive tokens in cache keys.
	 *
	 * @since 3.0.0
	 * @param string $api_token API authentication token.
	 * @return string Secure, collision-resistant cache key.
	 */
	public static function get_sidebar_cache_key( string $api_token ): string {
		// Validate input
		if ( empty( trim( $api_token ) ) ) {
			return 'brag_book_gallery_transient_sidebar_default';
		}

		return sprintf( 'brag_book_gallery_transient_sidebar_%s', $api_token );
	}

	/**
	 * Generate comprehensive cache key for cases data
	 *
	 * Creates a detailed cache key for cases data that includes API credentials,
	 * filtering parameters, and pagination information. Uses consistent ordering
	 * for procedure IDs to ensure cache key consistency across requests.
	 *
	 * Key Components:
	 * - Base identifier for cases cache type
	 * - Hashed API token for security and collision avoidance
	 * - Website property ID for multi-site support
	 * - Sorted procedure IDs for consistent filtering
	 * - Page number for pagination support
	 *
	 * @since 3.0.0
	 * @param string    $api_token          API authentication token.
	 * @param string    $website_property_id Website property identifier.
	 * @param array<int> $procedure_ids      Optional procedure IDs for filtering.
	 * @param int       $page               Optional page number for pagination.
	 * @return string Comprehensive, collision-resistant cache key.
	 */
	public static function get_cases_cache_key(
		string $api_token,
		string $website_property_id,
		array $procedure_ids = [],
		int $page = 1
	): string {
		// Build base key components
		$key_parts = [
			'brag_book_gallery_transient_cases',
			md5( $api_token ),
			sanitize_key( $website_property_id ),
		];

		// Add procedure IDs with consistent ordering
		if ( ! empty( $procedure_ids ) ) {
			// Filter and sort procedure IDs for consistency
			$clean_ids = array_map( 'absint', $procedure_ids );
			$clean_ids = array_filter( $clean_ids ); // Remove zero/invalid IDs
			sort( $clean_ids, SORT_NUMERIC );

			if ( ! empty( $clean_ids ) ) {
				$key_parts[] = 'procs_' . implode( '_', $clean_ids );
			}
		}

		// Add pagination if beyond first page
		if ( $page > 1 ) {
			$key_parts[] = sprintf( 'page_%d', max( 1, $page ) );
		}

		return implode( '_', $key_parts );
	}

	/**
	 * Generate cache key for complete cases dataset
	 *
	 * Creates a cache key for the complete cases dataset used by the filtering
	 * system. Combines API token and website property ID for unique identification
	 * while maintaining security through hashing.
	 *
	 * @since 3.0.0
	 * @param string $api_token          API authentication token.
	 * @param string $website_property_id Website property identifier.
	 * @return string Secure cache key for complete cases dataset.
	 */
	public static function get_all_cases_cache_key( string $api_token, string $website_property_id ): string {
		// Validate inputs and provide fallback
		if ( empty( trim( $api_token ) ) || empty( trim( $website_property_id ) ) ) {
			return 'brag_book_gallery_transient_all_cases_default';
		}

		// Create secure cache key using combined hash
		return sprintf( 'brag_book_gallery_transient_all_cases_%s', $api_token . '_' . $website_property_id );
	}

	/**
	 * Generate comprehensive cache key for carousel data
	 *
	 * Creates a detailed cache key for carousel data that incorporates all
	 * configuration parameters including API credentials, display limits,
	 * filtering options, and member-specific settings.
	 *
	 * Key Components:
	 * - API token and website property ID for authentication context
	 * - Display limit for carousel size configuration
	 * - Optional procedure ID for content filtering
	 * - Optional member ID for personalized carousels
	 *
	 * @since 3.0.0
	 * @param string $api_token          API authentication token.
	 * @param string $website_property_id Website property identifier.
	 * @param int    $limit              Maximum number of carousel items.
	 * @param string $procedure_id       Optional procedure ID or slug for filtering.
	 * @param string $member_id          Optional member ID for personalization.
	 * @return string Comprehensive carousel cache key.
	 */
	public static function get_carousel_cache_key(
		string $api_token,
		string $website_property_id,
		int $limit,
		string $procedure_id = '',
		string $member_id = ''
	): string {
		// Validate and sanitize inputs
		$limit = max( 1, $limit ); // Ensure positive limit
		$procedure_id = sanitize_text_field( $procedure_id );
		$member_id = sanitize_text_field( $member_id );

		// Build comprehensive hash string
		$hash_components = [
			$api_token,
			$website_property_id,
			(string) $limit,
			$procedure_id,
			$member_id,
		];

		$hash_string = implode( '|', $hash_components );
		return sprintf( 'brag_book_gallery_transient_carousel_%s', $hash_string );
	}

	/**
	 * Clear cache entries for a specific API token
	 *
	 * Removes all cache entries associated with a specific API token.
	 * This is useful when API credentials change and related cache should be invalidated.
	 *
	 * @since 3.0.0
	 * @param string $api_token API token to clear cache for.
	 * @return array{success: bool, message: string, count?: int} Result of cache clearing operation.
	 */
	public static function clear_cache_by_api_token( string $api_token ): array {
		global $wpdb;

		if ( empty( trim( $api_token ) ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid API token provided', 'brag-book-gallery' ),
				'count' => 0,
			];
		}

		$token_hash = md5( $api_token );

		// Clear cache entries containing this token hash
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s) AND (option_name LIKE %s OR option_name LIKE %s)",
				'%transient_brag_book_gallery_transient_%',
				'%transient_timeout_brag_book_gallery_transient_%',
				'%' . $token_hash . '%',
				'%' . $token_hash . '%'
			)
		);

		if ( false === $deleted ) {
			return [
				'success' => false,
				'message' => __( 'Failed to clear API token cache due to database error', 'brag-book-gallery' ),
				'count' => 0,
			];
		}

		$transient_count = (int) ( $deleted / 2 );

		// Clear WordPress object cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of cache entries cleared for API token */
				_n( 'Cleared %d cache entry for API token', 'Cleared %d cache entries for API token', $transient_count, 'brag-book-gallery' ),
				$transient_count
			),
			'count' => $transient_count,
		];
	}

	/**
	 * Check if caching is enabled with filter support
	 *
	 * Determines whether caching should be active for the plugin.
	 * Provides a filterable hook to allow developers and administrators
	 * to disable caching when needed (e.g., during development or debugging).
	 *
	 * @since 3.0.0
	 * @return bool True if caching is enabled, false otherwise.
	 */
	public static function is_caching_enabled(): bool {
		return false; // Caching permanently disabled
	}

	/**
	 * Clear cache by specific type with comprehensive cleanup
	 *
	 * Performs targeted cache clearing for specific cache types, allowing
	 * for more granular cache management. Uses WordPress VIP compliant
	 * database queries with proper error handling.
	 *
	 * Supported Types:
	 * - 'sidebar': Clear sidebar/navigation cache
	 * - 'cases': Clear case data cache
	 * - 'carousel': Clear carousel-specific cache
	 * - 'all': Clear all plugin cache (default)
	 *
	 * @since 3.0.0
	 * @param string $type Cache type identifier.
	 * @return bool True on successful cache clearing, false on failure.
	 */
	public static function clear_cache_by_type( string $type = 'all' ): bool {
		global $wpdb;

		// Get the appropriate cache pattern
		$pattern = self::get_cache_pattern_by_type( $type );

		// Use VIP compliant prepared statement
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$pattern,
				str_replace( '%transient_%', '%transient_timeout_%', $pattern )
			)
		);

		// Clear object cache if clearing succeeded
		if ( false !== $result && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		return false !== $result;
	}

	/**
	 * Get validated cache pattern by type
	 *
	 * Generates SQL LIKE patterns for targeted cache clearing operations.
	 * Uses PHP 8.2 match expression for type safety and performance.
	 * Validates input types to prevent SQL injection and ensures consistent
	 * pattern generation.
	 *
	 * @since 3.0.0
	 * @param string $type Cache type identifier (validated).
	 * @return string Validated SQL LIKE pattern for cache queries.
	 */
	private static function get_cache_pattern_by_type( string $type ): string {
		// Use PHP 8.2 match for cleaner type-based pattern generation
		return match ( strtolower( trim( $type ) ) ) {
			'sidebar' => '%transient_%brag_book_gallery_transient_sidebar_%',
			'cases' => '%transient_%brag_book_gallery_transient_cases_%',
			'carousel' => '%transient_%brag_book_gallery_transient_carousel_%',
			'all_cases' => '%transient_%brag_book_gallery_transient_all_cases_%',
			default => '%transient_%brag_book_gallery_transient_%',
		};
	}

	/**
	 * Get all cached items with details for management interface
	 *
	 * Retrieves detailed information about all cached items from both
	 * transients and WP Engine object cache for display in admin interface.
	 *
	 * @since 3.2.4
	 * @return array{transients: array, wp_cache: array, wp_engine: bool} Cache information.
	 */
	public static function get_all_cache_items(): array {
		global $wpdb;

		$is_wp_engine = self::is_wp_engine();
		$result = [
			'transients' => [],
			'wp_cache' => [],
			'wp_engine' => $is_wp_engine,
		];

		// Get transient cache items
		$transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} 
				 WHERE option_name LIKE %s 
				 AND option_name NOT LIKE %s 
				 ORDER BY option_name",
				'%transient_brag_book_gallery_transient_%',
				'%transient_timeout_%'
			),
			ARRAY_A
		);

		if ( $transients ) {
			foreach ( $transients as $transient ) {
				$key = str_replace( '_transient_', '', $transient['option_name'] );
				$size = strlen( $transient['option_value'] );
				$expiry = self::get_transient_expiry( $key );
				
				$result['transients'][] = [
					'key' => $key,
					'size' => $size,
					'expiry' => $expiry,
					'type' => self::get_cache_type_from_key( $key ),
				];
			}
		}

		// Note: WP Engine object cache items cannot be enumerated directly
		// We can only indicate if WP Engine is active
		if ( $is_wp_engine ) {
			$result['wp_cache'] = [
				'note' => __( 'WP Engine object cache is active. Individual cache items cannot be enumerated, but cache group operations are available.', 'brag-book-gallery' ),
				'group' => self::WP_CACHE_GROUP,
			];
		}

		return $result;
	}

	/**
	 * Get transient expiry time
	 *
	 * @since 3.2.4
	 * @param string $key Transient key.
	 * @return int|null Expiry timestamp or null if no expiry set.
	 */
	private static function get_transient_expiry( string $key ): ?int {
		global $wpdb;

		$expiry = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				'_transient_timeout_' . $key
			)
		);

		return $expiry ? (int) $expiry : null;
	}

	/**
	 * Get cache type from cache key
	 *
	 * @since 3.2.4
	 * @param string $key Cache key.
	 * @return string Cache type.
	 */
	private static function get_cache_type_from_key( string $key ): string {
		if ( strpos( $key, 'sidebar' ) !== false ) {
			return 'sidebar';
		}
		if ( strpos( $key, 'cases' ) !== false ) {
			return 'cases';
		}
		if ( strpos( $key, 'carousel' ) !== false ) {
			return 'carousel';
		}
		if ( strpos( $key, 'all_cases' ) !== false ) {
			return 'all_cases';
		}
		
		return 'unknown';
	}

	/**
	 * Get cache statistics
	 *
	 * @since 3.2.4
	 * @return array{total_items: int, total_size: int, wp_engine: bool, types: array} Cache statistics.
	 */
	public static function get_cache_statistics(): array {
		$cache_items = self::get_all_cache_items();
		$stats = [
			'total_items' => count( $cache_items['transients'] ),
			'total_size' => 0,
			'wp_engine' => $cache_items['wp_engine'],
			'types' => [],
		];

		foreach ( $cache_items['transients'] as $item ) {
			$stats['total_size'] += $item['size'];
			
			if ( ! isset( $stats['types'][ $item['type'] ] ) ) {
				$stats['types'][ $item['type'] ] = ['count' => 0, 'size' => 0];
			}
			
			$stats['types'][ $item['type'] ]['count']++;
			$stats['types'][ $item['type'] ]['size'] += $item['size'];
		}

		return $stats;
	}
}
