<?php
declare( strict_types=1 );

/**
 * Enterprise Slug Helper for BRAGBook Gallery Plugin
 *
 * Comprehensive slug management system implementing WordPress VIP standards
 * with PHP 8.2+ optimizations. Handles gallery page slug operations with
 * support for multiple formats and backwards compatibility.
 *
 * Key Features:
 * - Multi-format slug support (array/string)
 * - Backwards compatibility maintenance
 * - Slug validation and sanitization
 * - Reserved term conflict prevention
 * - Performance-optimized caching
 * - Maximum slug limit enforcement
 * - Primary slug management
 *
 * Architecture:
 * - Static utility methods for stateless operations
 * - Internal caching for performance
 * - WordPress option storage
 * - Hook integration for extensibility
 * - Type-safe operations with strict types
 * - Defensive programming patterns
 *
 * Slug Management:
 * - Add/remove individual slugs
 * - Set primary slug positioning
 * - Validate against reserved terms
 * - Clear all slugs safely
 * - Cache management for performance
 *
 * Security Features:
 * - Comprehensive input sanitization
 * - Reserved term validation
 * - Maximum limit enforcement
 * - Safe option storage
 * - XSS prevention through sanitization
 *
 * Performance Optimizations:
 * - Static cache for slug storage
 * - Single database query per request
 * - Efficient array operations
 * - Lazy loading patterns
 * - Cache invalidation strategies
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     BRAGBook Team
 * @version    3.0.0
 * @copyright  Copyright (c) 2025, BRAGBook Team
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Core;

// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct access denied.' );
}

/**
 * Slug Helper Class
 *
 * Enterprise-grade slug management utility for multi-format slug operations.
 * Provides centralized slug handling with caching, validation, and security
 * features for WordPress gallery page URLs.
 *
 * Core Functionality:
 * - Multi-format slug support (array/string)
 * - Slug validation and sanitization
 * - Reserved term conflict prevention
 * - Primary slug management
 * - Cache optimization
 * - Hook integration
 *
 * Technical Implementation:
 * - PHP 8.2+ type safety and features
 * - WordPress VIP coding standards
 * - Security-first slug validation
 * - Performance-optimized caching
 * - Comprehensive error handling
 * - Defensive programming patterns
 *
 * @since 3.0.0
 */
final class Slug_Helper {

	/**
	 * WordPress option name for gallery page slugs.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const SLUG_OPTION = 'brag_book_gallery_page_slug';

	/**
	 * Maximum allowed slugs to prevent database bloat.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_SLUGS = 10;

	/**
	 * Minimum slug length for validation.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MIN_SLUG_LENGTH = 2;

	/**
	 * Static cache for slugs to reduce database queries.
	 *
	 * @since 3.0.0
	 * @var array<int, string>|null
	 */
	private static ?array $slugs_cache = null;

	/**
	 * WordPress reserved terms that cannot be used as slugs.
	 *
	 * @since 3.0.0
	 * @var array<int, string>
	 */
	private const RESERVED_TERMS = [
		'attachment', 'attachment_id', 'author', 'author_name',
		'calendar', 'cat', 'category', 'category__and',
		'category__in', 'category__not_in', 'category_name',
		'comments_per_page', 'comments_popup', 'custom',
		'customize_messenger_channel', 'customized', 'cpage',
		'day', 'debug', 'embed', 'error', 'exact', 'feed',
		'fields', 'hour', 'link_category', 'm', 'minute',
		'monthnum', 'more', 'name', 'nav_menu', 'nonce',
		'nopaging', 'offset', 'order', 'orderby', 'p',
		'page', 'page_id', 'paged', 'pagename', 'pb',
		'perm', 'post', 'post__in', 'post__not_in',
		'post_format', 'post_mime_type', 'post_status',
		'post_tag', 'post_type', 'posts',
		'posts_per_archive_page', 'posts_per_page',
		'preview', 'robots', 's', 'search', 'second',
		'sentence', 'showposts', 'static', 'subpost',
		'subpost_id', 'tag', 'tag__and', 'tag__in',
		'tag__not_in', 'tag_id', 'tag_slug__and',
		'tag_slug__in', 'taxonomy', 'tb', 'term',
		'terms', 'theme', 'title', 'type', 'w',
		'withcomments', 'withoutcomments', 'year',
	];

	/**
	 * Get the first gallery page slug from the option.
	 *
	 * Handles both array and string formats for backwards compatibility.
	 * Returns the primary (first) slug when multiple are stored.
	 *
	 * Format Support:
	 * - Array: Returns first element
	 * - String: Returns the string value
	 * - Empty/Invalid: Returns default
	 *
	 * @since 3.0.0
	 * @param string $default Default value if option is empty or invalid.
	 * @return string Sanitized primary slug or default value.
	 */
	public static function get_first_gallery_page_slug( string $default = '' ): string {
		// Sanitize default value
		$default = sanitize_title( $default );
		
		// Retrieve option with type safety
		$page_slug_option = get_option( self::SLUG_OPTION, $default );
		
		// Process based on type using PHP 8.2 match
		return match ( true ) {
			is_array( $page_slug_option ) && ! empty( $page_slug_option ) => 
				sanitize_title( (string) reset( $page_slug_option ) ),
			is_string( $page_slug_option ) && ! empty( $page_slug_option ) => 
				sanitize_title( $page_slug_option ),
			default => $default,
		};
	}

	/**
	 * Get all gallery page slugs as an array.
	 *
	 * Always returns an array regardless of storage format. Implements
	 * caching for performance optimization with optional refresh.
	 *
	 * Cache Strategy:
	 * - Static cache persists for request lifecycle
	 * - Force refresh bypasses cache
	 * - Automatic sanitization and validation
	 *
	 * @since 3.0.0
	 * @param bool $force_refresh Force cache refresh from database.
	 * @return array<int, string> Array of sanitized page slugs.
	 */
	public static function get_all_gallery_page_slugs( bool $force_refresh = false ): array {
		// Return cached value if available
		if ( ! $force_refresh && self::$slugs_cache !== null ) {
			return self::$slugs_cache;
		}
		
		// Retrieve and process option
		$page_slug_option = get_option( self::SLUG_OPTION, [] );
		
		// Process and cache based on type
		self::$slugs_cache = match ( true ) {
			is_array( $page_slug_option ) => self::process_array_slugs( $page_slug_option ),
			is_string( $page_slug_option ) && ! empty( $page_slug_option ) => self::process_string_slug( $page_slug_option ),
			default => [],
		};
		
		return self::$slugs_cache;
	}

	/**
	 * Process array of slugs.
	 *
	 * @since 3.0.0
	 * @param array<mixed> $slugs Raw slug array.
	 * @return array<int, string> Processed slugs.
	 */
	private static function process_array_slugs( array $slugs ): array {
		return array_values(
			array_filter(
				array_map( 'sanitize_title', $slugs ),
				static fn( string $slug ): bool => ! empty( $slug )
			)
		);
	}

	/**
	 * Process string slug.
	 *
	 * @since 3.0.0
	 * @param string $slug Raw slug string.
	 * @return array<int, string> Array with single slug.
	 */
	private static function process_string_slug( string $slug ): array {
		$sanitized = sanitize_title( $slug );
		return ! empty( $sanitized ) ? [ $sanitized ] : [];
	}

	/**
	 * Check if a slug is a gallery page slug.
	 *
	 * Performs sanitized comparison against registered gallery slugs.
	 * Case-insensitive through sanitization process.
	 *
	 * @since 3.0.0
	 * @param string $slug Slug to check.
	 * @return bool True if slug is registered as gallery slug.
	 */
	public static function is_gallery_slug( string $slug ): bool {
		// Sanitize and validate input
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}
		
		// Check against registered slugs
		return in_array( $slug, self::get_all_gallery_page_slugs(), true );
	}

	/**
	 * Add a slug to the gallery page slugs.
	 *
	 * Validates and adds a new slug with duplicate prevention and
	 * limit enforcement. Triggers hooks for extensibility.
	 *
	 * Validation:
	 * - Sanitization and length check
	 * - Duplicate prevention
	 * - Maximum limit enforcement
	 * - Reserved term validation
	 *
	 * @since 3.0.0
	 * @param string $slug Slug to add.
	 * @return bool True if successfully added or already exists.
	 */
	public static function add_gallery_slug( string $slug ): bool {
		// Validate slug
		if ( ! self::validate_slug( $slug ) ) {
			return false;
		}

		$slug = sanitize_title( $slug );
		$slugs = self::get_all_gallery_page_slugs( true );
		
		// Check if already exists
		if ( in_array( $slug, $slugs, true ) ) {
			return true; // Already exists, consider it success
		}
		
		// Enforce maximum limit
		if ( count( $slugs ) >= self::MAX_SLUGS ) {
			/**
			 * Fires when slug limit is reached.
			 *
			 * @since 3.0.0
			 * @param string $slug The slug that couldn't be added.
			 * @param array<int, string> $slugs Current slugs.
			 */
			do_action( 'brag_book_gallery_slug_limit_reached', $slug, $slugs );
			return false;
		}

		// Add and update
		$slugs[] = $slug;
		$result = update_option( self::SLUG_OPTION, $slugs );
		
		if ( $result ) {
			self::invalidate_cache();
			
			/**
			 * Fires after a slug is added.
			 *
			 * @since 3.0.0
			 * @param string $slug The slug that was added.
			 * @param array<int, string> $slugs All slugs after addition.
			 */
			do_action( 'brag_book_gallery_slug_added', $slug, $slugs );
		}
		
		return $result;
	}

	/**
	 * Remove a slug from the gallery page slugs.
	 *
	 * Removes specific slug and re-indexes array. Triggers hooks
	 * for extensibility and cache invalidation.
	 *
	 * @since 3.0.0
	 * @param string $slug Slug to remove.
	 * @return bool True if successfully removed.
	 */
	public static function remove_gallery_slug( string $slug ): bool {
		// Sanitize input
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}
		
		// Get current slugs and find target
		$slugs = self::get_all_gallery_page_slugs( true );
		$key = array_search( $slug, $slugs, true );
		
		if ( $key === false ) {
			return false; // Slug not found
		}
		
		// Remove and re-index
		unset( $slugs[ $key ] );
		$slugs = array_values( $slugs );
		
		// Update storage
		$result = update_option( self::SLUG_OPTION, $slugs );
		
		if ( $result ) {
			self::invalidate_cache();
			
			/**
			 * Fires after a slug is removed.
			 *
			 * @since 3.0.0
			 * @param string $slug The slug that was removed.
			 * @param array<int, string> $slugs All slugs after removal.
			 */
			do_action( 'brag_book_gallery_slug_removed', $slug, $slugs );
		}
		
		return $result;
	}

	/**
	 * Update or set the primary gallery slug.
	 *
	 * Moves or adds slug to primary (first) position. Existing slugs
	 * are shifted or removed to maintain limit.
	 *
	 * Behavior:
	 * - Existing slug: Moved to first position
	 * - New slug: Added at first position
	 * - Limit exceeded: Oldest slugs removed
	 *
	 * @since 3.0.0
	 * @param string $slug New primary slug.
	 * @return bool True if successfully updated.
	 */
	public static function set_primary_slug( string $slug ): bool {
		// Validate slug
		if ( ! self::validate_slug( $slug ) ) {
			return false;
		}

		$slug = sanitize_title( $slug );
		$slugs = self::get_all_gallery_page_slugs( true );
		
		// Check current position
		$existing_key = array_search( $slug, $slugs, true );
		
		if ( $existing_key === 0 ) {
			return true; // Already primary
		}
		
		// Remove if exists
		if ( $existing_key !== false ) {
			unset( $slugs[ $existing_key ] );
		}
		
		// Add to beginning and enforce limit
		array_unshift( $slugs, $slug );
		$slugs = array_slice( $slugs, 0, self::MAX_SLUGS );
		
		// Update storage
		$result = update_option( self::SLUG_OPTION, $slugs );
		
		if ( $result ) {
			self::invalidate_cache();
			
			/**
			 * Fires after the primary slug is set.
			 *
			 * @since 3.0.0
			 * @param string $slug The new primary slug.
			 * @param array<int, string> $slugs All slugs after update.
			 */
			do_action( 'brag_book_gallery_primary_slug_set', $slug, $slugs );
		}
		
		return $result;
	}

	/**
	 * Clear all gallery slugs.
	 *
	 * Removes all stored slugs from database. Use with caution as
	 * this will break existing gallery URLs.
	 *
	 * @since 3.0.0
	 * @return bool True if successfully cleared.
	 */
	public static function clear_all_slugs(): bool {
		// Delete from database
		$result = delete_option( self::SLUG_OPTION );
		
		if ( $result ) {
			self::invalidate_cache();
			
			/**
			 * Fires after all slugs are cleared.
			 *
			 * @since 3.0.0
			 */
			do_action( 'brag_book_gallery_slugs_cleared' );
		}
		
		return $result;
	}

	/**
	 * Validate a slug for gallery use.
	 *
	 * Comprehensive validation including length, format, and
	 * reserved term checking. Extensible via filter.
	 *
	 * Validation Rules:
	 * - Minimum length requirement
	 * - No WordPress reserved terms
	 * - Proper sanitization
	 * - Filter extensibility
	 *
	 * @since 3.0.0
	 * @param string $slug Slug to validate.
	 * @return bool True if valid for use.
	 */
	public static function validate_slug( string $slug ): bool {
		// Sanitize and check emptiness
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}
		
		// Check length requirement
		if ( strlen( $slug ) < self::MIN_SLUG_LENGTH ) {
			return false;
		}
		
		// Check against reserved terms
		if ( in_array( $slug, self::RESERVED_TERMS, true ) ) {
			return false;
		}
		
		/**
		 * Filters slug validation result.
		 *
		 * @since 3.0.0
		 * @param bool $is_valid Whether the slug is valid.
		 * @param string $slug The slug being validated.
		 */
		return (bool) apply_filters( 'brag_book_gallery_validate_slug', true, $slug );
	}

	/**
	 * Get slug statistics and information.
	 *
	 * Provides comprehensive information about current slug
	 * configuration for debugging and admin interfaces.
	 *
	 * @since 3.0.0
	 * @return array{count: int, max_allowed: int, primary_slug: string, all_slugs: array<int, string>, remaining: int} Statistics array.
	 */
	public static function get_slug_stats(): array {
		$slugs = self::get_all_gallery_page_slugs();
		$count = count( $slugs );
		
		return [
			'count'        => $count,
			'max_allowed'  => self::MAX_SLUGS,
			'primary_slug' => $slugs[0] ?? '',
			'all_slugs'    => $slugs,
			'remaining'    => self::MAX_SLUGS - $count,
		];
	}

	/**
	 * Clear slug cache.
	 *
	 * Forces cache refresh on next slug retrieval. Useful after
	 * external option updates or debugging.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function clear_cache(): void {
		self::invalidate_cache();
	}

	/**
	 * Invalidate internal cache.
	 *
	 * Internal method for consistent cache clearing.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private static function invalidate_cache(): void {
		self::$slugs_cache = null;
	}

	/**
	 * Batch update slugs.
	 *
	 * Replace all slugs with new set. Useful for import/export
	 * or bulk operations.
	 *
	 * @since 3.0.0
	 * @param array<int, string> $new_slugs New slug array.
	 * @return bool True if successfully updated.
	 */
	public static function batch_update_slugs( array $new_slugs ): bool {
		// Validate and sanitize all slugs
		$validated_slugs = [];
		
		foreach ( $new_slugs as $slug ) {
			if ( ! is_string( $slug ) ) {
				continue;
			}
			
			$sanitized = sanitize_title( $slug );
			if ( self::validate_slug( $sanitized ) && ! in_array( $sanitized, $validated_slugs, true ) ) {
				$validated_slugs[] = $sanitized;
			}
			
			// Stop at limit
			if ( count( $validated_slugs ) >= self::MAX_SLUGS ) {
				break;
			}
		}
		
		if ( empty( $validated_slugs ) ) {
			return false;
		}
		
		// Update storage
		$result = update_option( self::SLUG_OPTION, $validated_slugs );
		
		if ( $result ) {
			self::invalidate_cache();
			
			/**
			 * Fires after batch slug update.
			 *
			 * @since 3.0.0
			 * @param array<int, string> $validated_slugs New slugs.
			 */
			do_action( 'brag_book_gallery_slugs_batch_updated', $validated_slugs );
		}
		
		return $result;
	}

	/**
	 * Check if slug limit is reached.
	 *
	 * @since 3.0.0
	 * @return bool True if at maximum capacity.
	 */
	public static function is_at_limit(): bool {
		return count( self::get_all_gallery_page_slugs() ) >= self::MAX_SLUGS;
	}

	/**
	 * Get slug by index.
	 *
	 * @since 3.0.0
	 * @param int $index Array index (0-based).
	 * @return string|null Slug at index or null.
	 */
	public static function get_slug_by_index( int $index ): ?string {
		$slugs = self::get_all_gallery_page_slugs();
		return $slugs[ $index ] ?? null;
	}
}