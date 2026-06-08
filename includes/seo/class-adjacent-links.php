<?php
/**
 * Adjacent (rel next/prev) Link Suppression
 *
 * Gallery views render their entire result set on a single page via
 * JavaScript/AJAX, so the paginated `/page/2/` URLs that WordPress derives
 * from the underlying archive query do not exist as navigable pages. Yoast
 * SEO and Rank Math, however, emit `<link rel="next">` / `<link rel="prev">`
 * tags on any archive whose main query reports `max_num_pages > 1`, pointing
 * at those non-existent paginated URLs.
 *
 * This class disables those adjacent rel links — but only on BRAG book
 * gallery contexts, leaving pagination on the rest of the site untouched.
 * It uses each SEO plugin's documented opt-out filter:
 *
 *   - Yoast SEO: `wpseo_disable_adjacent_rel_links`
 *   - Rank Math: `rank_math/frontend/disable_adjacent_rel_links`
 *
 * Both filters are registered unconditionally; a filter for an inactive
 * plugin simply never fires, which also covers the case where both plugins
 * are active simultaneously.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\SEO
 * @since      4.6.0
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\SEO;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suppresses bogus rel=next/prev links on gallery views.
 *
 * @since 4.6.0
 */
final class Adjacent_Links {

	/**
	 * Constructor.
	 *
	 * Defers filter registration to `plugins_loaded` so the SEO plugins'
	 * main files (and therefore their filter hooks) have been loaded.
	 *
	 * @since 4.6.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'register_filters' ), 25 );
	}

	/**
	 * Register the adjacent-link opt-out filters for supported SEO plugins.
	 *
	 * @return void
	 * @since 4.6.0
	 */
	public function register_filters(): void {
		if ( is_admin() ) {
			return;
		}

		// Yoast SEO.
		add_filter( 'wpseo_disable_adjacent_rel_links', array( $this, 'maybe_disable' ) );

		// Rank Math.
		add_filter( 'rank_math/frontend/disable_adjacent_rel_links', array( $this, 'maybe_disable' ) );
	}

	/**
	 * Disable adjacent rel links on gallery contexts only.
	 *
	 * Returns the incoming value unchanged off gallery views so other code
	 * and the SEO plugin's own defaults are preserved.
	 *
	 * @param mixed $disabled Current "disabled" flag from the SEO plugin.
	 * @return bool Whether adjacent rel links should be disabled.
	 * @since 4.6.0
	 */
	public function maybe_disable( $disabled ): bool {
		if ( $this->is_gallery_context() ) {
			return true;
		}

		return (bool) $disabled;
	}

	/**
	 * Determine whether the current request is a BRAG book gallery view.
	 *
	 * Covers both Local mode (real case posts and procedure/provider archives)
	 * and Default mode (the shortcode-driven gallery page).
	 *
	 * @return bool True on any gallery context.
	 * @since 4.6.0
	 */
	private function is_gallery_context(): bool {
		if (
			is_singular( Post_Types::POST_TYPE_CASES )
			|| is_post_type_archive( Post_Types::POST_TYPE_CASES )
			|| is_tax( Taxonomies::TAXONOMY_PROCEDURES )
			|| is_tax( Taxonomies::TAXONOMY_PROVIDERS )
		) {
			return true;
		}

		return $this->is_main_gallery_page();
	}

	/**
	 * Check whether the current page is the shortcode-driven gallery page.
	 *
	 * @return bool True if the queried page is a configured gallery page.
	 * @since 4.6.0
	 */
	private function is_main_gallery_page(): bool {
		if ( ! is_page() ) {
			return false;
		}

		$current_id = (int) get_queried_object_id();
		if ( $current_id <= 0 ) {
			return false;
		}

		$gallery_page_id = (int) get_option( 'brag_book_gallery_page_id', 0 );
		if ( $gallery_page_id > 0 && $gallery_page_id === $current_id ) {
			return true;
		}

		$stored_pages_ids = (array) get_option( 'brag_book_gallery_stored_pages_ids', array() );
		foreach ( $stored_pages_ids as $stored_id ) {
			if ( (int) $stored_id === $current_id ) {
				return true;
			}
		}

		return false;
	}
}
