<?php
/**
 * Image variants trait.
 *
 * Shared helpers for reading the per-photoSet small/medium/full image variants
 * captured at sync time (`brag_book_gallery_case_image_variants`) and turning a
 * variant node into a responsive `srcset`. Older cases synced before variants
 * existed have no such meta; callers fall back to the single full-size URL.
 *
 * @package    BRAGBookGallery
 * @subpackage Shortcodes\Traits
 * @since      3.3.3
 */

namespace BRAGBookGallery\Includes\Shortcodes\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Trait_Image_Variants
 *
 * @since 3.3.3
 */
trait Trait_Image_Variants {

	/**
	 * Assumed intrinsic widths (px) for each stored variant size.
	 *
	 * The API does not report exact pixel dimensions, so these conventional
	 * widths drive the `srcset` width descriptors. They only need to reflect the
	 * relative ordering (small < medium < full) for the browser to choose well.
	 *
	 * @since 3.3.3
	 * @var array<string,int>
	 */
	private static array $variant_widths = array(
		'small'  => 400,
		'medium' => 800,
		'full'   => 1600,
	);

	/**
	 * Read the structured image-variant sets for a case post.
	 *
	 * @since 3.3.3
	 * @param int $post_id Case post ID.
	 * @return array<int,array<string,array<string,string>>> Per-photoSet variant sets, or [].
	 */
	protected static function get_case_image_variants( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		$sets = get_post_meta( $post_id, 'brag_book_gallery_case_image_variants', true );

		return is_array( $sets ) ? $sets : array();
	}

	/**
	 * Build a responsive `srcset` value from a single variant node.
	 *
	 * De-duplicates by URL so that when small/medium fall back to full (null
	 * variants from the API) the result is empty rather than the same URL listed
	 * three times. An empty return means the caller should render `src` alone.
	 *
	 * @since 3.3.3
	 * @param array<string,string> $node A variant node: { full, medium, small, alt }.
	 * @return string A `srcset` string (e.g. "a.jpg 400w, b.jpg 1600w"), or ''.
	 */
	protected static function build_srcset_from_node( array $node ): string {
		$entries  = array();
		$seen_url = array();

		// Smallest first so the browser's width descriptors read low → high.
		foreach ( array( 'small', 'medium', 'full' ) as $size ) {
			$url = isset( $node[ $size ] ) ? (string) $node[ $size ] : '';
			if ( '' === $url || isset( $seen_url[ $url ] ) ) {
				continue;
			}
			$seen_url[ $url ] = true;
			// Raw URL here; the caller escapes the whole srcset once with esc_attr.
			$entries[]        = esc_url_raw( $url ) . ' ' . self::$variant_widths[ $size ] . 'w';
		}

		// A single unique URL needs no srcset — `src` already covers it.
		return count( $entries ) > 1 ? implode( ', ', $entries ) : '';
	}

	/**
	 * Return a specific variant size for the node whose full-size URL matches.
	 *
	 * Used where a smaller fixed size is wanted (e.g. detail-page thumbnails)
	 * rather than a full srcset. Falls back to the passed URL when there is no
	 * matching variant node or the requested size is empty.
	 *
	 * @since 3.3.3
	 * @param int    $post_id Case post ID.
	 * @param string $url     The full-size URL to match against.
	 * @param string $size    One of 'small', 'medium', 'full'.
	 * @return string The requested variant URL, or $url as a fallback.
	 */
	protected static function get_variant_url_for_url( int $post_id, string $url, string $size ): string {
		if ( '' === $url ) {
			return $url;
		}

		foreach ( self::get_case_image_variants( $post_id ) as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $node ) {
				if ( is_array( $node ) && isset( $node['full'] ) && (string) $node['full'] === $url ) {
					return ! empty( $node[ $size ] ) ? (string) $node[ $size ] : $url;
				}
			}
		}

		return $url;
	}

	/**
	 * Find the variant node whose full-size URL matches a given URL and return
	 * its `srcset`.
	 *
	 * Lets a caller that already resolved a display URL (e.g. the grid card's
	 * post-processed image) attach the matching small/medium sources without
	 * having to know which photoSet/node it came from.
	 *
	 * @since 3.3.3
	 * @param int    $post_id Case post ID.
	 * @param string $url     The full-size URL already chosen for `src`.
	 * @return string A `srcset` string, or '' when no variant node matches.
	 */
	protected static function build_variant_srcset_for_url( int $post_id, string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		foreach ( self::get_case_image_variants( $post_id ) as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $node ) {
				if ( is_array( $node ) && isset( $node['full'] ) && (string) $node['full'] === $url ) {
					return self::build_srcset_from_node( $node );
				}
			}
		}

		return '';
	}
}
