<?php
/**
 * Slug Helper Class
 *
 * Provides utility functions for handling gallery page slugs.
 * Manages URL slug operations with support for multiple formats.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Core;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug Helper Class
 *
 * Handles gallery page slug operations with support for both
 * single string and array formats for backwards compatibility.
 * Provides centralized slug management for the plugin.
 *
 * @since 3.0.0
 */
class Slug_Helper {

	/**
	 * Option name for gallery page slugs
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const SLUG_OPTION = 'brag_book_gallery_page_slug';

	/**
	 * Maximum allowed slugs
	 *
	 * Prevents excessive slug storage.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_SLUGS = 10;

	/**
	 * Cache for slugs to reduce database queries
	 *
	 * @since 3.0.0
	 * @var array|null
	 */
	private static ?array $slugs_cache = null;

	/**
	 * Get the first gallery page slug from the option
	 *
	 * Handles both array and string formats for backwards compatibility.
	 * When brag_book_gallery_page_slug is an array, returns the first element.
	 * When it's a string, returns the string.
	 *
	 * @since 3.0.0
	 *
	 * @param string $default Default value if option is not set or empty.
	 *
	 * @return string The first page slug or default value.
	 */
	public static function get_first_gallery_page_slug( string $default = '' ): string {
		// Sanitize default.
		$default = sanitize_title( $default );
		
		// Get option value.
		$page_slug_option = get_option( self::SLUG_OPTION, $default );
		
		// Handle array format.
		if ( is_array( $page_slug_option ) ) {
			if ( ! empty( $page_slug_option ) ) {
				$first_slug = reset( $page_slug_option );
				return sanitize_title( (string) $first_slug );
			}
			return $default;
		}
		
		// Handle string format.
		if ( is_string( $page_slug_option ) && ! empty( $page_slug_option ) ) {
			return sanitize_title( $page_slug_option );
		}
		
		return $default;
	}

	/**
	 * Get all gallery page slugs as an array
	 *
	 * Always returns an array, even if the option is stored as a string.
	 * Results are cached for performance.
	 *
	 * @since 3.0.0
	 *
	 * @param bool $force_refresh Force refresh of cached slugs.
	 *
	 * @return array Array of sanitized page slugs.
	 */
	public static function get_all_gallery_page_slugs( bool $force_refresh = false ): array {
		// Return cached value if available and not forcing refresh.
		if ( ! $force_refresh && null !== self::$slugs_cache ) {
			return self::$slugs_cache;
		}
		
		// Get option value.
		$page_slug_option = get_option( self::SLUG_OPTION, array() );
		
		// Handle array format.
		if ( is_array( $page_slug_option ) ) {
			$slugs = array_map( 'sanitize_title', $page_slug_option );
			// Remove empty values.
			$slugs = array_filter( $slugs );
			// Re-index array.
			$slugs = array_values( $slugs );
			self::$slugs_cache = $slugs;
			return $slugs;
		}
		
		// Convert string to array.
		if ( is_string( $page_slug_option ) && ! empty( $page_slug_option ) ) {
			$slug = sanitize_title( $page_slug_option );
			if ( ! empty( $slug ) ) {
				self::$slugs_cache = array( $slug );
				return self::$slugs_cache;
			}
		}
		
		self::$slugs_cache = array();
		return array();
	}

	/**
	 * Check if a slug is a gallery page slug
	 *
	 * Performs case-insensitive comparison after sanitization.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Slug to check.
	 *
	 * @return bool True if the slug is a gallery page slug, false otherwise.
	 */
	public static function is_gallery_slug( string $slug ): bool {
		// Sanitize input slug.
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}
		
		// Get all gallery slugs.
		$gallery_slugs = self::get_all_gallery_page_slugs();
		
		return in_array( $slug, $gallery_slugs, true );
	}

	/**
	 * Add a slug to the gallery page slugs
	 *
	 * Validates and adds a new slug to the collection.
	 * Prevents duplicates and enforces maximum slug limit.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Slug to add.
	 *
	 * @return bool True if successfully added, false otherwise.
	 */
	public static function add_gallery_slug( string $slug ): bool {
		// Sanitize slug.
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}

		// Get current slugs.
		$slugs = self::get_all_gallery_page_slugs( true );
		
		// Don't add if already exists.
		if ( in_array( $slug, $slugs, true ) ) {
			return true;
		}
		
		// Check maximum limit.
		if ( count( $slugs ) >= self::MAX_SLUGS ) {
			/**
			 * Fires when slug limit is reached.
			 *
			 * @since 3.0.0
			 *
			 * @param string $slug  The slug that couldn't be added.
			 * @param array  $slugs Current slugs.
			 */
			do_action( 'brag_book_gallery_slug_limit_reached', $slug, $slugs );
			return false;
		}

		// Add new slug.
		$slugs[] = $slug;
		
		// Update option and clear cache.
		$result = update_option( self::SLUG_OPTION, $slugs );
		
		if ( $result ) {
			self::$slugs_cache = null;
			
			/**
			 * Fires after a slug is added.
			 *
			 * @since 3.0.0
			 *
			 * @param string $slug  The slug that was added.
			 * @param array  $slugs All slugs after addition.
			 */
			do_action( 'brag_book_gallery_slug_added', $slug, $slugs );
		}
		
		return $result;
	}

	/**
	 * Remove a slug from the gallery page slugs
	 *
	 * Removes a specific slug from the collection and re-indexes.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Slug to remove.
	 *
	 * @return bool True if successfully removed, false otherwise.
	 */
	public static function remove_gallery_slug( string $slug ): bool {
		// Sanitize slug.
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}
		
		// Get current slugs.
		$slugs = self::get_all_gallery_page_slugs( true );
		
		// Find slug in array.
		$key = array_search( $slug, $slugs, true );
		
		if ( false === $key ) {
			return false;
		}
		
		// Remove slug.
		unset( $slugs[ $key ] );
		// Re-index array.
		$slugs = array_values( $slugs );
		
		// Update option and clear cache.
		$result = update_option( self::SLUG_OPTION, $slugs );
		
		if ( $result ) {
			self::$slugs_cache = null;
			
			/**
			 * Fires after a slug is removed.
			 *
			 * @since 3.0.0
			 *
			 * @param string $slug  The slug that was removed.
			 * @param array  $slugs All slugs after removal.
			 */
			do_action( 'brag_book_gallery_slug_removed', $slug, $slugs );
		}
		
		return $result;
	}

	/**
	 * Update or set the primary gallery slug
	 *
	 * Sets a slug as the primary (first) slug in the collection.
	 * If the slug exists elsewhere in the array, it's moved to first position.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug New primary slug.
	 *
	 * @return bool True if successfully updated, false otherwise.
	 */
	public static function set_primary_slug( string $slug ): bool {
		// Sanitize slug.
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}

		// Get current slugs.
		$slugs = self::get_all_gallery_page_slugs( true );
		
		// Check if slug already exists in array.
		$existing_key = array_search( $slug, $slugs, true );
		
		if ( false !== $existing_key ) {
			// If it's already first, nothing to do.
			if ( 0 === $existing_key ) {
				return true;
			}
			
			// Remove from current position.
			unset( $slugs[ $existing_key ] );
		}
		
		// Add to beginning of array.
		array_unshift( $slugs, $slug );
		
		// Ensure we don't exceed maximum.
		if ( count( $slugs ) > self::MAX_SLUGS ) {
			$slugs = array_slice( $slugs, 0, self::MAX_SLUGS );
		}
		
		// Update option and clear cache.
		$result = update_option( self::SLUG_OPTION, $slugs );
		
		if ( $result ) {
			self::$slugs_cache = null;
			
			/**
			 * Fires after the primary slug is set.
			 *
			 * @since 3.0.0
			 *
			 * @param string $slug  The new primary slug.
			 * @param array  $slugs All slugs after update.
			 */
			do_action( 'brag_book_gallery_primary_slug_set', $slug, $slugs );
		}
		
		return $result;
	}

	/**
	 * Clear all gallery slugs
	 *
	 * Removes all stored gallery slugs. Use with caution.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if successfully cleared, false otherwise.
	 */
	public static function clear_all_slugs(): bool {
		// Delete option and clear cache.
		$result = delete_option( self::SLUG_OPTION );
		
		if ( $result ) {
			self::$slugs_cache = null;
			
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
	 * Validate a slug
	 *
	 * Checks if a slug is valid for use as a gallery slug.
	 * Ensures it doesn't conflict with WordPress reserved terms.
	 *
	 * @since 3.0.0
	 *
	 * @param string $slug Slug to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_slug( string $slug ): bool {
		// Sanitize slug.
		$slug = sanitize_title( $slug );
		
		if ( empty( $slug ) ) {
			return false;
		}
		
		// Check minimum length.
		if ( strlen( $slug ) < 2 ) {
			return false;
		}
		
		// Check against WordPress reserved terms.
		$reserved_terms = array(
			'attachment',
			'attachment_id',
			'author',
			'author_name',
			'calendar',
			'cat',
			'category',
			'category__and',
			'category__in',
			'category__not_in',
			'category_name',
			'comments_per_page',
			'comments_popup',
			'custom',
			'customize_messenger_channel',
			'customized',
			'cpage',
			'day',
			'debug',
			'embed',
			'error',
			'exact',
			'feed',
			'fields',
			'hour',
			'link_category',
			'm',
			'minute',
			'monthnum',
			'more',
			'name',
			'nav_menu',
			'nonce',
			'nopaging',
			'offset',
			'order',
			'orderby',
			'p',
			'page',
			'page_id',
			'paged',
			'pagename',
			'pb',
			'perm',
			'post',
			'post__in',
			'post__not_in',
			'post_format',
			'post_mime_type',
			'post_status',
			'post_tag',
			'post_type',
			'posts',
			'posts_per_archive_page',
			'posts_per_page',
			'preview',
			'robots',
			's',
			'search',
			'second',
			'sentence',
			'showposts',
			'static',
			'subpost',
			'subpost_id',
			'tag',
			'tag__and',
			'tag__in',
			'tag__not_in',
			'tag_id',
			'tag_slug__and',
			'tag_slug__in',
			'taxonomy',
			'tb',
			'term',
			'terms',
			'theme',
			'title',
			'type',
			'w',
			'withcomments',
			'withoutcomments',
			'year',
		);
		
		if ( in_array( $slug, $reserved_terms, true ) ) {
			return false;
		}
		
		/**
		 * Filters slug validation result.
		 *
		 * @since 3.0.0
		 *
		 * @param bool   $is_valid Whether the slug is valid.
		 * @param string $slug     The slug being validated.
		 */
		return apply_filters( 'brag_book_gallery_validate_slug', true, $slug );
	}

	/**
	 * Get slug statistics
	 *
	 * Returns information about current slug configuration.
	 *
	 * @since 3.0.0
	 *
	 * @return array Slug statistics.
	 */
	public static function get_slug_stats(): array {
		$slugs = self::get_all_gallery_page_slugs();
		
		return array(
			'count'        => count( $slugs ),
			'max_allowed'  => self::MAX_SLUGS,
			'primary_slug' => ! empty( $slugs ) ? $slugs[0] : '',
			'all_slugs'    => $slugs,
		);
	}

	/**
	 * Clear slug cache
	 *
	 * Forces cache refresh on next slug retrieval.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$slugs_cache = null;
	}
}