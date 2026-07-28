<?php
declare( strict_types=1 );

/**
 * Settings Helper for BRAGBook Gallery Plugin
 *
 * Provides utility methods for accessing and validating plugin settings.
 * Implements performance optimizations with static caching and type-safe
 * operations following WordPress VIP coding standards.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.2.4
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Core;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Settings Helper Class
 *
 * Static utility class for accessing and validating plugin settings.
 * Provides centralized access to configuration options with proper
 * type handling and performance optimization.
 *
 * @since 3.2.4
 */
final class Settings_Helper {

	/**
	 * Cases rendered per page when the site has never saved the setting.
	 *
	 * @since 4.9.2
	 * @var int
	 */
	public const DEFAULT_ITEMS_PER_PAGE = 12;

	/**
	 * Smallest accepted items-per-page value. Below 1 the grid renders nothing
	 * and the pager cannot advance.
	 *
	 * @since 4.9.2
	 * @var int
	 */
	public const MIN_ITEMS_PER_PAGE = 1;

	/**
	 * Largest accepted items-per-page value. Bounds the admin field so a single
	 * load cannot be made arbitrarily expensive.
	 *
	 * @since 4.9.2
	 * @var int
	 */
	public const MAX_ITEMS_PER_PAGE = 200;

	/**
	 * Procedure slugs for the carousels in the default landing page content.
	 *
	 * A starting point for a fresh install, not a guarantee. A site with no
	 * procedure matching one of these slugs still renders a carousel: the
	 * handler falls back to an unfiltered case query rather than showing
	 * nothing, so the slot is filled with whatever cases exist. Editors are
	 * expected to swap these for their own procedures in the landing page
	 * editor, which overrides this default from then on.
	 *
	 * @since 4.9.2
	 * @var array<int,string>
	 */
	public const DEFAULT_LANDING_CAROUSEL_PROCEDURES = [
		'breast-augmentation',
		'liposuction',
	];

	/**
	 * Static cache for settings to avoid repeated database queries
	 *
	 * @since 3.2.4
	 * @var array
	 */
	private static array $settings_cache = [];

	/**
	 * Landing page content used until a site saves its own.
	 *
	 * Single source of truth for the `brag_book_gallery_landing_page_text`
	 * default. It is read by the two admin editors and by the two front-end
	 * render paths, which must agree — if they drift, the admin field shows
	 * content the gallery does not render.
	 *
	 * The returned markup is passed through `do_shortcode()` at render time, so
	 * the carousel shortcodes here are executed rather than printed.
	 *
	 * @since 4.9.2
	 * @return string Landing page HTML, including the default carousels.
	 */
	public static function get_default_landing_page_text(): string {
		$content = sprintf(
			'<h2>%s</h2>' . "\n" . '<p>%s</p>',
			__( 'Go ahead, browse our before & afters... visualize your possibilities.', 'brag-book-gallery' ),
			__( 'Our gallery is full of our real patients. Keep in mind results vary.', 'brag-book-gallery' )
		);

		foreach ( self::DEFAULT_LANDING_CAROUSEL_PROCEDURES as $procedure_slug ) {
			// Not translatable: a shortcode tag and its attributes are code.
			$content .= "\n" . sprintf(
				'[brag_book_carousel procedure="%s"]',
				$procedure_slug
			);
		}

		return $content;
	}

	/**
	 * Check if favorites functionality is enabled
	 *
	 * @since 3.2.4
	 * @return bool True if favorites are enabled, false otherwise
	 */
	public static function is_favorites_enabled(): bool {
		$cache_key = 'enable_favorites';

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		// Get setting with default true (enabled by default)
		$setting = get_option( 'brag_book_gallery_enable_favorites', true );

		// Convert to boolean and cache
		$is_enabled = (bool) $setting;
		self::$settings_cache[ $cache_key ] = $is_enabled;

		return $is_enabled;
	}

	/**
	 * Check if consultation requests functionality is enabled
	 *
	 * @since 3.2.4
	 * @return bool True if consultation requests are enabled, false otherwise
	 */
	public static function is_consultation_enabled(): bool {
		$cache_key = 'enable_consultation';

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		// Get setting with default true (enabled by default)
		$setting = get_option( 'brag_book_gallery_enable_consultation', true );

		// Convert to boolean and cache
		$is_enabled = (bool) $setting;
		self::$settings_cache[ $cache_key ] = $is_enabled;

		return $is_enabled;
	}

	/**
	 * Check if filter counts should be shown
	 *
	 * @since 3.2.4
	 * @return bool True if filter counts should be shown, false otherwise
	 */
	public static function should_show_filter_counts(): bool {
		$cache_key = 'show_filter_counts';

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		// Get setting with default true (enabled by default)
		$setting = get_option( 'brag_book_gallery_show_filter_counts', true );

		// Convert to boolean and cache
		$should_show = (bool) $setting;
		self::$settings_cache[ $cache_key ] = $should_show;

		return $should_show;
	}

	/**
	 * Check if navigation menus should be expanded by default
	 *
	 * @since 3.2.4
	 * @return bool True if menus should be expanded, false otherwise
	 */
	public static function should_expand_nav_menus(): bool {
		$cache_key = 'expand_nav_menus';

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		// Get setting with default false (collapsed by default)
		$setting = get_option( 'brag_book_gallery_expand_nav_menus', false );

		// Convert to boolean and cache
		$should_expand = (bool) $setting;
		self::$settings_cache[ $cache_key ] = $should_expand;

		return $should_expand;
	}

	/**
	 * Get items per page setting
	 *
	 * The single source of truth for how many cases render per load, shared by
	 * the initial grid render, the provider and location filters, and the
	 * "load more" pager, so every batch is the same size.
	 *
	 * Floored at 1 because a page size of 0 would render nothing and leave the
	 * pager unable to advance. Not capped here: the admin field bounds the value
	 * on save, and a site that deliberately stores a larger number should get it.
	 *
	 * @since 3.2.4
	 * @return int Number of items to show per page, always >= 1.
	 */
	public static function get_items_per_page(): int {
		$cache_key = 'items_per_page';

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		$setting = get_option(
			'brag_book_gallery_items_per_page',
			self::DEFAULT_ITEMS_PER_PAGE
		);

		$items_per_page                     = max( 1, absint( $setting ) );
		self::$settings_cache[ $cache_key ] = $items_per_page;

		return $items_per_page;
	}

	/**
	 * Get gallery columns setting
	 *
	 * @since 3.2.4
	 * @return string Number of columns ('2' or '3')
	 */
	public static function get_gallery_columns(): string {
		$cache_key = 'gallery_columns';

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		// Get setting with default '2'
		$setting = get_option( 'brag_book_gallery_columns', '2' );

		// Validate and ensure valid value
		$columns = in_array( $setting, [ '2', '3' ], true ) ? $setting : '2';
		self::$settings_cache[ $cache_key ] = $columns;

		return $columns;
	}

	/**
	 * Clear the internal settings cache
	 *
	 * Useful when settings are updated and cache needs refreshing.
	 *
	 * @since 3.2.4
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$settings_cache = [];
	}

	/**
	 * Get a cached setting or fetch from database
	 *
	 * Generic method for accessing any plugin setting with caching.
	 *
	 * @since 3.2.4
	 * @param string $option_name WordPress option name
	 * @param mixed  $default     Default value if option doesn't exist
	 * @return mixed The option value
	 */
	public static function get_setting( string $option_name, $default = false ) {
		$cache_key = 'setting_' . $option_name;

		// Return cached value if available
		if ( isset( self::$settings_cache[ $cache_key ] ) ) {
			return self::$settings_cache[ $cache_key ];
		}

		// Get option from database
		$value = get_option( $option_name, $default );
		self::$settings_cache[ $cache_key ] = $value;

		return $value;
	}
}
