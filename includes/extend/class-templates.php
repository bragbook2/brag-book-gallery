<?php
/**
 * Template Handler
 *
 * Manages custom template inclusion for BragBook gallery pages.
 * This class determines when to load custom templates based on page slugs
 * and gallery configuration.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Traits\Trait_Sanitizer;
use WP_Post;

/**
 * Templates Class
 *
 * Handles custom template loading for gallery pages.
 *
 * @since 3.0.0
 */
final class Templates {
	use Trait_Sanitizer;

	/**
	 * Template file name for gallery pages
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const GALLERY_TEMPLATE = 'template/brag-book-gallery-brag.php';

	/**
	 * Cache for gallery slugs
	 *
	 * @since 3.0.0
	 * @var array<string>|null
	 */
	private static ?array $gallery_slugs_cache = null;

	/**
	 * Cache for combine gallery slug
	 *
	 * @since 3.0.0
	 * @var string|null
	 */
	private static ?string $combine_slug_cache = null;

	/**
	 * Constructor
	 *
	 * Currently not used but reserved for future functionality.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Reserved for future use
	}

	/**
	 * Include custom template for gallery pages
	 *
	 * Determines whether to use a custom template based on the current page slug.
	 * This method is hooked into the 'template_include' filter to override
	 * the default WordPress template hierarchy for gallery pages.
	 *
	 * @since 3.0.0
	 *
	 * @param string $template The path to the template about to be loaded.
	 * @return string The path to the template to be loaded.
	 */
	public static function include_template( string $template ): string {

		// Get the current page slug.
		$page_slug = self::get_current_page_slug();

		if ( empty( $page_slug ) ) {
			return $template;
		}

		// Check if this is a gallery page.
		if ( ! self::is_gallery_page( $page_slug ) ) {
			return $template;
		}

		// Get the custom template path.
		$custom_template = self::get_custom_template_path();

		// Return custom template if it exists, otherwise return original.
		return file_exists( $custom_template ) ? $custom_template : $template;
	}

	/**
	 * Get the current page slug
	 *
	 * Retrieves the slug of the currently displayed page.
	 *
	 * @since 3.0.0
	 *
	 * @return string The current page slug, or empty string if not available.
	 */
	private static function get_current_page_slug(): string {
		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$slug = get_post_field( 'post_name', $post );

		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * Check if the current page is a gallery page
	 *
	 * Determines whether the given slug matches any configured gallery page.
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_slug The page slug to check.
	 * @return bool True if this is a gallery page, false otherwise.
	 */
	private static function is_gallery_page( string $page_slug ): bool {

		// Check if it's a combined gallery page.
		if ( self::is_combine_gallery_page( $page_slug ) ) {
			return true;
		}

		// Check if it's a regular gallery page.
		return self::is_regular_gallery_page( $page_slug );
	}

	/**
	 * Check if the page is a combined gallery page
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_slug The page slug to check.
	 * @return bool True if this is a combined gallery page.
	 */
	private static function is_combine_gallery_page( string $page_slug ): bool {

		// Get the combined gallery slug from options.
		$combine_slug = self::get_combine_gallery_slug();

		return ! empty( $combine_slug ) && $combine_slug === $page_slug;
	}

	/**
	 * Check if the page is a regular gallery page
	 *
	 * @since 3.0.0
	 *
	 * @param string $page_slug The page slug to check.
	 * @return bool True if this is a regular gallery page.
	 */
	private static function is_regular_gallery_page( string $page_slug ): bool {

		// Get the list of gallery slugs from options.
		$gallery_slugs = self::get_gallery_slugs();

		return in_array( $page_slug, $gallery_slugs, true );
	}

	/**
	 * Get gallery slugs from options
	 *
	 * Retrieves and caches the list of gallery page slugs.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string> Array of gallery page slugs.
	 */
	private static function get_gallery_slugs(): array {

		if ( self::$gallery_slugs_cache === null ) {
			$slugs = get_option( 'brag_book_gallery_page_slug', [] );
			self::$gallery_slugs_cache = is_array( $slugs ) ? array_filter( $slugs, 'is_string' ) : [];
		}

		return self::$gallery_slugs_cache;
	}

	/**
	 * Get combine gallery slug from options
	 *
	 * Retrieves and caches the combined gallery page slug.
	 *
	 * @since 3.0.0
	 *
	 * @return string The combined gallery slug, or empty string if not set.
	 */
	private static function get_combine_gallery_slug(): string {
		if ( self::$combine_slug_cache === null ) {
			$slug = get_option( 'combine_gallery_slug', '' );
			self::$combine_slug_cache = is_string( $slug ) ? $slug : '';
		}

		return self::$combine_slug_cache;
	}

	/**
	 * Get the path to the custom template
	 *
	 * Constructs the full filesystem path to the custom gallery template.
	 *
	 * @since 3.0.0
	 *
	 * @return string The full path to the custom template file.
	 */
	private static function get_custom_template_path(): string {
		// Use the Trait_Tools method to get the correct path
		return Setup::get_plugin_path() . self::GALLERY_TEMPLATE;
	}

	/**
	 * Clear cached values
	 *
	 * Clears the cached gallery slugs and combine slug.
	 * Useful when gallery configuration changes.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$gallery_slugs_cache = null;
		self::$combine_slug_cache = null;
	}

	/**
	 * Get all gallery page IDs
	 *
	 * Retrieves the IDs of all configured gallery pages.
	 *
	 * @since 3.0.0
	 *
	 * @return array<int> Array of page IDs.
	 */
	public static function get_gallery_page_ids(): array {
		$page_ids = [];
		$gallery_slugs = self::get_gallery_slugs();

		// Get IDs for regular gallery pages
		foreach ( $gallery_slugs as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof WP_Post ) {
				$page_ids[] = $page->ID;
			}
		}

		// Get ID for combined gallery page
		$combine_slug = self::get_combine_gallery_slug();
		if ( ! empty( $combine_slug ) ) {
			$page = get_page_by_path( $combine_slug );
			if ( $page instanceof WP_Post ) {
				$page_ids[] = $page->ID;
			}
		}

		return array_unique( $page_ids );
	}

	/**
	 * Check if template file exists
	 *
	 * Verifies that the custom gallery template file exists in the plugin.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if the template file exists, false otherwise.
	 */
	public static function template_exists(): bool {
		return file_exists( self::get_custom_template_path() );
	}

	/**
	 * Get template file contents
	 *
	 * Retrieves the contents of the custom template file.
	 *
	 * @since 3.0.0
	 *
	 * @return string|false The template contents or false on failure.
	 */
	public static function get_template_contents(): string|false {
		$template_path = self::get_custom_template_path();

		if ( ! file_exists( $template_path ) ) {
			return false;
		}

		return file_get_contents( $template_path );
	}

	/**
	 * Register template with WordPress
	 *
	 * Hooks the template inclusion method into WordPress.
	 * This should be called during plugin initialization.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'template_include', [ self::class, 'include_template' ], 99 );
	}

	/**
	 * Unregister template from WordPress
	 *
	 * Removes the template inclusion hook from WordPress.
	 * Useful for deactivation or testing.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function unregister(): void {
		remove_filter( 'template_include', [ self::class, 'include_template' ], 99 );
	}

	/**
	 * Get debug information
	 *
	 * Returns debug information about the current template configuration.
	 * Useful for troubleshooting template loading issues.
	 *
	 * @since 3.0.0
	 *
	 * @return array{
	 *     gallery_slugs: array<string>,
	 *     combine_slug: string,
	 *     template_path: string,
	 *     template_exists: bool,
	 *     current_page_slug: string,
	 *     is_gallery_page: bool
	 * } Debug information array.
	 */
	public static function get_debug_info(): array {
		$current_slug = self::get_current_page_slug();

		return [
			'gallery_slugs' => self::get_gallery_slugs(),
			'combine_slug' => self::get_combine_gallery_slug(),
			'template_path' => self::get_custom_template_path(),
			'template_exists' => self::template_exists(),
			'current_page_slug' => $current_slug,
			'is_gallery_page' => ! empty( $current_slug ) && self::is_gallery_page( $current_slug ),
		];
	}
}
