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

use BRAGBookGallery\Includes\Core\Settings_Helper;

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
	 * Chrome taken out of the viewport by the gallery shell at 1280px and above.
	 *
	 * `.brag-book-gallery-container` is a flex row from 1280px, with
	 * `.brag-book-gallery-sidebar` capped at 382px and
	 * `.brag-book-gallery-main-content` adding 16px of inline padding either side.
	 * Below 1280px the sidebar is `position: fixed` off-canvas and takes no space,
	 * which is why only the widest tier subtracts anything.
	 *
	 * A `sizes` that ignores this claims the image is far wider than it is, and
	 * the browser answers by fetching the largest candidate every time — which is
	 * exactly why the small/medium renditions went unused.
	 *
	 * @since 4.9.2
	 * @var int
	 */
	private const SHELL_CHROME_PX = 414;

	/**
	 * Column gap in `.brag-book-gallery-cases-grid`, in pixels.
	 *
	 * @since 4.9.2
	 * @var int
	 */
	private const GRID_GAP_PX = 20;

	/**
	 * `sizes` for the case-detail main viewer.
	 *
	 * A single full-width block inside the main content column, so it spans the
	 * viewport minus the shell chrome once the sidebar is in flow.
	 *
	 * @since 4.9.2
	 * @var string
	 */
	protected const SIZES_FULL_WIDTH = '(max-width: 1279px) 100vw, calc(100vw - ' . self::SHELL_CHROME_PX . 'px)';

	/**
	 * `sizes` for a card in the favorites grid.
	 *
	 * `.brag-book-gallery-favorites-grid` is `auto-fill, minmax(280px, 1fr)`, so
	 * the column width is not a fixed fraction of the viewport — it sits between
	 * 280px and roughly double that before another column is added. 400px is a
	 * mid-range estimate that rounds up rather than down.
	 *
	 * @since 4.9.2
	 * @var string
	 */
	protected const SIZES_FAVORITES_GRID = '(max-width: 640px) 100vw, 400px';

	/**
	 * `sizes` for a carousel slide.
	 *
	 * Mirrors `.brag-book-gallery-carousel-track`: one slide per view below
	 * 480px, two up to 767px, three from 768px.
	 *
	 * @since 4.9.2
	 * @var string
	 */
	protected const SIZES_CAROUSEL = '(max-width: 479px) 100vw, (max-width: 767px) 50vw, 33vw';

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
	 * Build the `sizes` value for a card in the cases grid.
	 *
	 * Mirrors `.brag-book-gallery-cases-grid`: one column below 576px, two up to
	 * 1279px, and the caller's column count from 1280px — the only tier where the
	 * sidebar is in flow and has to be subtracted.
	 *
	 * The grid's column count is also a runtime control (visitors can switch
	 * between 2 and 3), and `sizes` cannot re-evaluate on that change. The value
	 * therefore describes the saved setting, which is what the page renders with.
	 *
	 * @since 4.9.2
	 * @return string A `sizes` value.
	 */
	protected static function sizes_case_grid(): string {
		$columns = max( 1, (int) Settings_Helper::get_gallery_columns() );
		$chrome  = self::SHELL_CHROME_PX + ( self::GRID_GAP_PX * ( $columns - 1 ) );

		return sprintf(
			'(max-width: 575px) 100vw, (max-width: 1279px) 50vw, calc((100vw - %dpx) / %d)',
			$chrome,
			$columns
		);
	}

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
	 * Reduce a URL to the part that identifies the file it points at.
	 *
	 * Image URLs from the API are signed: the query string carries a JWT whose
	 * `iat` changes every time the object is re-signed. Comparing whole URLs
	 * therefore fails whenever the display URL and the stored variant `full` were
	 * signed at different moments — and because a failed match drops the entire
	 * `srcset`, that failure is silent. Host plus path identifies the same object
	 * regardless of token.
	 *
	 * The path is percent-decoded so that a URL stored as `Eyelid-Lift%20-%20x.jpg`
	 * matches the same file written with literal spaces.
	 *
	 * @since 4.9.2
	 * @param string $url Absolute image URL.
	 * @return string A comparable key, or the original URL when it cannot be parsed.
	 */
	private static function url_match_key( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return $url;
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? rawurldecode( (string) $parts['path'] ) : '';

		// Nothing usable to compare on — fall back to the whole string.
		if ( '' === $host && '' === $path ) {
			return $url;
		}

		return $host . $path;
	}

	/**
	 * Find the variant node whose full-size image is the one at a given URL.
	 *
	 * @since 4.9.2
	 * @param int    $post_id Case post ID.
	 * @param string $url     The full-size URL already chosen for `src`.
	 * @return array<string,string>|null The matching node, or null.
	 */
	private static function find_variant_node( int $post_id, string $url ): ?array {
		$target = self::url_match_key( $url );

		foreach ( self::get_case_image_variants( $post_id ) as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $node ) {
				if ( ! is_array( $node ) || ! isset( $node['full'] ) ) {
					continue;
				}
				if ( self::url_match_key( (string) $node['full'] ) === $target ) {
					return $node;
				}
			}
		}

		return null;
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

		$node = self::find_variant_node( $post_id, $url );

		if ( null === $node ) {
			return $url;
		}

		return ! empty( $node[ $size ] ) ? (string) $node[ $size ] : $url;
	}

	/**
	 * Find the variant node whose full-size URL matches a given URL and return
	 * its `srcset`.
	 *
	 * Lets a caller that already resolved a display URL (e.g. the grid card's
	 * post-processed image) attach the matching small/medium sources without
	 * having to know which photoSet/node it came from.
	 *
	 * The largest candidate is emitted as the caller's own `$url` rather than the
	 * stored `full`. Matching is by path, so the two can carry different signing
	 * tokens; the caller's is the one just resolved for `src` and therefore the
	 * one known to be current. `srcset` wins over `src`, so offering a staler
	 * token here would be what actually breaks the image.
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

		$node = self::find_variant_node( $post_id, $url );

		if ( null !== $node ) {
			$node['full'] = $url;

			return self::build_srcset_from_node( $node );
		}

		return '';
	}

	/**
	 * Build `<source>` elements that pin a variant to a viewport range.
	 *
	 * `srcset` leaves the choice to the browser, which compares candidate widths
	 * against the slot width times the device pixel ratio. The stored renditions
	 * are small enough (320px and 640px against a multi-thousand-pixel original)
	 * that a retina device never reaches for them: a two-column card needs around
	 * 1000 device pixels and the largest rendition offers 640.
	 *
	 * A `<source media="…">` is decided on the media query alone — pixel ratio and
	 * slot width play no part — so this is what actually puts the small and medium
	 * files on the wire. The trade is deliberate: on a high-density display those
	 * renditions are upscaled and will look softer than the full image.
	 *
	 * Returns '' when the case has no smaller renditions, leaving `<picture>` with
	 * only its `<img>` — which is the correct markup for that case.
	 *
	 * @since 4.9.2
	 * @param int    $post_id Case post ID.
	 * @param string $url     The full-size URL already chosen for `src`.
	 * @return string Zero or more `<source>` tags, already escaped.
	 */
	protected static function build_variant_sources( int $post_id, string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$node = self::find_variant_node( $post_id, $url );

		if ( null === $node ) {
			return '';
		}

		// Narrowest first: the browser takes the first `<source>` whose media
		// query matches, so a wider range listed earlier would shadow the rest.
		$tiers = array(
			array( 'small', '(max-width: 575px)' ),
			array( 'medium', '(max-width: 1279px)' ),
		);

		$sources = '';

		foreach ( $tiers as list( $size, $media ) ) {
			$variant = isset( $node[ $size ] ) ? (string) $node[ $size ] : '';

			if ( '' === $variant ) {
				continue;
			}

			$sources .= sprintf(
				'<source media="%s" srcset="%s">',
				esc_attr( $media ),
				esc_url( $variant )
			);
		}

		return $sources;
	}

	/**
	 * Build the ready-to-print `srcset`/`sizes` attribute pair for an image.
	 *
	 * Returns a pre-escaped fragment with a leading space so it can be dropped
	 * straight into an `<img>` tag, or an empty string when the case has no
	 * smaller renditions — in which case `src` alone is the correct markup and
	 * a lone `sizes` attribute would be meaningless.
	 *
	 * @since 4.9.2
	 * @param int    $post_id Case post ID.
	 * @param string $url     The full-size URL already chosen for `src`.
	 * @param string $sizes   A `sizes` value, e.g. self::sizes_case_grid().
	 * @return string ` srcset="…" sizes="…"`, or '' when there is nothing to offer.
	 */
	protected static function build_responsive_attrs( int $post_id, string $url, string $sizes ): string {
		$srcset = self::build_variant_srcset_for_url( $post_id, $url );

		if ( '' === $srcset ) {
			return '';
		}

		return sprintf(
			' srcset="%s" sizes="%s"',
			esc_attr( $srcset ),
			esc_attr( $sizes )
		);
	}
}
