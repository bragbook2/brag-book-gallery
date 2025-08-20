<?php
/**
 * Slug Helper Class
 *
 * Provides utility functions for handling gallery page slugs.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug Helper Class
 *
 * Handles gallery page slug operations with support for both
 * single string and array formats for backwards compatibility.
 *
 * @since 3.0.0
 */
class Slug_Helper {

	/**
	 * Get the first gallery page slug from the option
	 * 
	 * Handles both array and string formats for backwards compatibility.
	 * When brag_book_gallery_page_slug is an array, returns the first element.
	 * When it's a string, returns the string.
	 * 
	 * @param string $default Default value if option is not set or empty
	 * @return string The first page slug or default value
	 * @since 3.0.0
	 */
	public static function get_first_gallery_page_slug( string $default = '' ): string {
		$page_slug_option = get_option( 'brag_book_gallery_page_slug', $default );
		
		// Handle both array and string formats
		if ( is_array( $page_slug_option ) ) {
			return ! empty( $page_slug_option ) ? (string) $page_slug_option[0] : $default;
		}
		
		return (string) ( $page_slug_option ?: $default );
	}

	/**
	 * Get all gallery page slugs as an array
	 * 
	 * Always returns an array, even if the option is stored as a string.
	 * 
	 * @return array Array of page slugs
	 * @since 3.0.0
	 */
	public static function get_all_gallery_page_slugs(): array {
		$page_slug_option = get_option( 'brag_book_gallery_page_slug', array() );
		
		// Handle both array and string formats
		if ( is_array( $page_slug_option ) ) {
			return $page_slug_option;
		}
		
		// Convert string to array
		if ( is_string( $page_slug_option ) && ! empty( $page_slug_option ) ) {
			return array( $page_slug_option );
		}
		
		return array();
	}

	/**
	 * Check if a slug is a gallery page slug
	 * 
	 * @param string $slug Slug to check
	 * @return bool True if the slug is a gallery page slug
	 * @since 3.0.0
	 */
	public static function is_gallery_slug( string $slug ): bool {
		$gallery_slugs = self::get_all_gallery_page_slugs();
		return in_array( $slug, $gallery_slugs, true );
	}

	/**
	 * Add a slug to the gallery page slugs
	 * 
	 * @param string $slug Slug to add
	 * @return bool True if successfully added
	 * @since 3.0.0
	 */
	public static function add_gallery_slug( string $slug ): bool {
		if ( empty( $slug ) ) {
			return false;
		}

		$slugs = self::get_all_gallery_page_slugs();
		
		// Don't add if already exists
		if ( in_array( $slug, $slugs, true ) ) {
			return true;
		}

		$slugs[] = $slug;
		return update_option( 'brag_book_gallery_page_slug', $slugs );
	}

	/**
	 * Remove a slug from the gallery page slugs
	 * 
	 * @param string $slug Slug to remove
	 * @return bool True if successfully removed
	 * @since 3.0.0
	 */
	public static function remove_gallery_slug( string $slug ): bool {
		$slugs = self::get_all_gallery_page_slugs();
		$key = array_search( $slug, $slugs, true );
		
		if ( $key !== false ) {
			unset( $slugs[ $key ] );
			$slugs = array_values( $slugs ); // Re-index
			return update_option( 'brag_book_gallery_page_slug', $slugs );
		}

		return false;
	}

	/**
	 * Update or set the primary gallery slug
	 * 
	 * @param string $slug New primary slug
	 * @return bool True if successfully updated
	 * @since 3.0.0
	 */
	public static function set_primary_slug( string $slug ): bool {
		if ( empty( $slug ) ) {
			return false;
		}

		$slugs = self::get_all_gallery_page_slugs();
		
		// Set as first element
		if ( ! empty( $slugs ) ) {
			$slugs[0] = $slug;
		} else {
			$slugs = array( $slug );
		}

		return update_option( 'brag_book_gallery_page_slug', $slugs );
	}
}