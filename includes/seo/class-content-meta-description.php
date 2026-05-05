<?php
/**
 * Content Meta Description Manager
 *
 * Supplies meta descriptions for three contexts that the existing SEO classes
 * do not cover:
 *
 *   - `brag_book_cases` singular posts: prefer the plugin's
 *     `brag_book_gallery_seo_page_description` post meta, falling back to
 *     `brag_book_gallery_notes`.
 *   - `brag_book_procedures` taxonomy archives: use the
 *     `brag_book_gallery_details` term meta (HTML stripped).
 *   - The main gallery shortcode page: synthesize a description from the
 *     synced post-type / taxonomy data so the meta tag never echoes the
 *     rendered shortcode markup.
 *
 * "User-set wins" rule: every supported SEO plugin (Yoast, Rank Math,
 * AIOSEO, SEOPress) is queried for a per-post or per-term description value.
 * If that value is non-empty the user has explicitly typed something into
 * that plugin's UI, and we leave the description filter argument untouched.
 * Only when the per-post / per-term value is empty do we replace the auto-
 * generated default with the managed description.
 *
 * @package BRAGBookGallery
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\SEO;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Content_Meta_Description {

	private const SYNTHETIC_TRANSIENT_KEY = 'brag_book_gallery_synth_meta_description';
	private const SYNTHETIC_CACHE_TTL     = HOUR_IN_SECONDS;
	private const MAX_DESCRIPTION_LENGTH  = 320;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'register_filters' ), 25 );

		// Invalidate the synthesized gallery description when content shifts.
		add_action( 'save_post_' . Post_Types::POST_TYPE_CASES, array( $this, 'flush_synthetic_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_synthetic_cache' ) );
		add_action( 'created_' . Taxonomies::TAXONOMY_PROCEDURES, array( $this, 'flush_synthetic_cache' ) );
		add_action( 'edited_' . Taxonomies::TAXONOMY_PROCEDURES, array( $this, 'flush_synthetic_cache' ) );
		add_action( 'delete_' . Taxonomies::TAXONOMY_PROCEDURES, array( $this, 'flush_synthetic_cache' ) );
	}

	/**
	 * Attach description filters for whichever SEO plugin is active.
	 *
	 * Runs late on `plugins_loaded` so every supported SEO plugin's main
	 * file has been included and `class_exists()` / `function_exists()`
	 * checks are reliable.
	 */
	public function register_filters(): void {
		if ( is_admin() ) {
			return;
		}

		$plugin = $this->detect_active_seo_plugin();
		if ( 'none' === $plugin ) {
			// No SEO plugin: still emit our description on the WP-native head.
			add_action( 'wp_head', array( $this, 'maybe_print_native_description' ), 1 );
			return;
		}

		$filter_names = $this->filter_names_for( $plugin );
		foreach ( $filter_names as $filter_name ) {
			add_filter( $filter_name, array( $this, 'filter_description' ), 20, 1 );
		}
	}

	/**
	 * Filter callback shared by every supported SEO plugin's description hook.
	 *
	 * @param mixed $description Incoming description from the SEO plugin.
	 * @return mixed Original or replacement description.
	 */
	public function filter_description( $description ) {
		if ( ! is_string( $description ) ) {
			return $description;
		}

		$context = $this->resolve_context();
		if ( null === $context ) {
			return $description;
		}

		// Honour any user-set value in the active SEO plugin's own UI.
		if ( $this->user_has_set_description( $context ) ) {
			return $description;
		}

		$managed = $this->get_managed_description( $context );
		if ( '' === $managed ) {
			return $description;
		}

		return $this->trim_to_length( $managed );
	}

	/**
	 * Output a `<meta name="description">` tag when no SEO plugin is active.
	 */
	public function maybe_print_native_description(): void {
		$context = $this->resolve_context();
		if ( null === $context ) {
			return;
		}

		$managed = $this->get_managed_description( $context );
		if ( '' === $managed ) {
			return;
		}

		printf(
			"<meta name=\"description\" content=\"%s\" />\n",
			esc_attr( $this->trim_to_length( $managed ) )
		);
	}

	public function flush_synthetic_cache( $post_id_or_term_id = 0 ): void {
		unset( $post_id_or_term_id );
		delete_transient( self::SYNTHETIC_TRANSIENT_KEY );
	}

	/**
	 * Resolve the current request to one of our managed contexts.
	 *
	 * @return array{type: string, id: int}|null
	 */
	private function resolve_context(): ?array {
		if ( is_singular( Post_Types::POST_TYPE_CASES ) ) {
			$post_id = (int) get_queried_object_id();
			return $post_id > 0 ? array( 'type' => 'case', 'id' => $post_id ) : null;
		}

		if ( is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			$term_id = (int) get_queried_object_id();
			return $term_id > 0 ? array( 'type' => 'procedure', 'id' => $term_id ) : null;
		}

		if ( $this->is_main_gallery_page() ) {
			return array( 'type' => 'gallery', 'id' => (int) get_queried_object_id() );
		}

		return null;
	}

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

	/**
	 * Build the managed description for a context.
	 *
	 * @param array{type: string, id: int} $context
	 */
	private function get_managed_description( array $context ): string {
		switch ( $context['type'] ) {
			case 'case':
				return $this->managed_case_description( $context['id'] );
			case 'procedure':
				return $this->managed_procedure_description( $context['id'] );
			case 'gallery':
				return $this->synthesize_gallery_description();
		}
		return '';
	}

	private function managed_case_description( int $post_id ): string {
		$seo = (string) get_post_meta( $post_id, 'brag_book_gallery_seo_page_description', true );
		$seo = $this->normalize_text( $seo );
		if ( '' !== $seo ) {
			return $seo;
		}

		$notes = (string) get_post_meta( $post_id, 'brag_book_gallery_notes', true );
		return $this->normalize_text( $notes );
	}

	private function managed_procedure_description( int $term_id ): string {
		$details = (string) get_term_meta( $term_id, 'brag_book_gallery_details', true );
		return $this->normalize_text( $details );
	}

	/**
	 * Build a description from the synced post type and taxonomy.
	 *
	 * Cached as a transient because both inputs are stable between sync runs.
	 */
	private function synthesize_gallery_description(): string {
		$cached = get_transient( self::SYNTHETIC_TRANSIENT_KEY );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$counts     = wp_count_posts( Post_Types::POST_TYPE_CASES );
		$case_count = isset( $counts->publish ) ? (int) $counts->publish : 0;

		$top_terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 5,
				'fields'     => 'names',
			)
		);

		$procedure_names = is_array( $top_terms ) ? array_values( array_filter( $top_terms ) ) : array();
		$site_name       = get_bloginfo( 'name' );

		if ( $case_count <= 0 || empty( $procedure_names ) ) {
			$fallback = sprintf(
				/* translators: %s: site name */
				esc_html__( 'Browse the %s before-and-after gallery of real patient results.', 'brag-book-gallery' ),
				$site_name
			);
			set_transient( self::SYNTHETIC_TRANSIENT_KEY, $fallback, self::SYNTHETIC_CACHE_TTL );
			return $fallback;
		}

		$procedure_list = $this->join_with_and( $procedure_names );

		$description = sprintf(
			/* translators: 1: case count, 2: comma-separated procedure list, 3: site name */
			esc_html__(
				'Browse %1$d before-and-after cases at %3$s, featuring %2$s.',
				'brag-book-gallery'
			),
			$case_count,
			$procedure_list,
			$site_name
		);

		$description = $this->trim_to_length( $description );
		set_transient( self::SYNTHETIC_TRANSIENT_KEY, $description, self::SYNTHETIC_CACHE_TTL );
		return $description;
	}

	/**
	 * Has the active SEO plugin captured a per-post / per-term description?
	 *
	 * @param array{type: string, id: int} $context
	 */
	private function user_has_set_description( array $context ): bool {
		$plugin = $this->detect_active_seo_plugin();

		switch ( $plugin ) {
			case 'yoast':
				return $this->yoast_user_description( $context ) !== '';
			case 'rankmath':
				return $this->rankmath_user_description( $context ) !== '';
			case 'aioseo':
				return $this->aioseo_user_description( $context ) !== '';
			case 'seopress':
				return $this->seopress_user_description( $context ) !== '';
			default:
				return false;
		}
	}

	private function yoast_user_description( array $context ): string {
		if ( 'gallery' === $context['type'] || 'case' === $context['type'] ) {
			return $this->normalize_text( (string) get_post_meta( $context['id'], '_yoast_wpseo_metadesc', true ) );
		}

		// Term meta lives in a serialized option keyed by taxonomy + term id.
		$tax_meta = get_option( 'wpseo_taxonomy_meta' );
		if ( ! is_array( $tax_meta ) ) {
			return '';
		}
		$value = $tax_meta[ Taxonomies::TAXONOMY_PROCEDURES ][ $context['id'] ]['wpseo_desc'] ?? '';
		return $this->normalize_text( (string) $value );
	}

	private function rankmath_user_description( array $context ): string {
		if ( 'procedure' === $context['type'] ) {
			return $this->normalize_text( (string) get_term_meta( $context['id'], 'rank_math_description', true ) );
		}
		return $this->normalize_text( (string) get_post_meta( $context['id'], 'rank_math_description', true ) );
	}

	private function aioseo_user_description( array $context ): string {
		// Legacy meta — populated on AIOSEO v3 sites and on v4 sites that
		// imported existing data. Read this first to avoid coupling to the
		// AIOSEO v4 model API.
		if ( 'procedure' === $context['type'] ) {
			$legacy = (string) get_term_meta( $context['id'], '_aioseo_description', true );
			if ( '' !== $legacy ) {
				return $this->normalize_text( $legacy );
			}
		} else {
			$legacy = (string) get_post_meta( $context['id'], '_aioseo_description', true );
			if ( '' !== $legacy ) {
				return $this->normalize_text( $legacy );
			}
		}

		// AIOSEO v4 stores per-post overrides in a custom DB table reachable
		// through `aioseo()->helpers->getPost()`. Term descriptions only
		// gained first-class support in newer 4.x releases, so we guard each
		// call with `function_exists()` / `method_exists()`.
		if ( ! function_exists( 'aioseo' ) ) {
			return '';
		}

		try {
			$aio = aioseo();
		} catch ( \Throwable $e ) {
			return '';
		}

		if ( 'procedure' === $context['type'] ) {
			if ( isset( $aio->helpers ) && method_exists( $aio->helpers, 'getTerm' ) ) {
				$term_obj = $aio->helpers->getTerm( $context['id'] );
				if ( is_object( $term_obj ) && ! empty( $term_obj->description ) ) {
					return $this->normalize_text( (string) $term_obj->description );
				}
			}
			return '';
		}

		if ( isset( $aio->helpers ) && method_exists( $aio->helpers, 'getPost' ) ) {
			$post_obj = $aio->helpers->getPost( $context['id'] );
			if ( is_object( $post_obj ) && ! empty( $post_obj->description ) ) {
				return $this->normalize_text( (string) $post_obj->description );
			}
		}
		return '';
	}

	private function seopress_user_description( array $context ): string {
		if ( 'procedure' === $context['type'] ) {
			return $this->normalize_text( (string) get_term_meta( $context['id'], '_seopress_titles_desc', true ) );
		}
		return $this->normalize_text( (string) get_post_meta( $context['id'], '_seopress_titles_desc', true ) );
	}

	/**
	 * @return string One of: 'yoast' | 'rankmath' | 'aioseo' | 'seopress' | 'none'.
	 */
	private function detect_active_seo_plugin(): string {
		static $detected = null;
		if ( null !== $detected ) {
			return $detected;
		}

		// Match SEO_Manager's detection style and order.
		if ( class_exists( 'WPSEO_Options' ) ) {
			return $detected = 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			return $detected = 'rankmath';
		}
		if ( function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' ) ) {
			return $detected = 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return $detected = 'seopress';
		}
		return $detected = 'none';
	}

	/**
	 * @return string[]
	 */
	private function filter_names_for( string $plugin ): array {
		return match ( $plugin ) {
			'yoast'    => array( 'wpseo_metadesc', 'wpseo_opengraph_desc' ),
			'rankmath' => array( 'rank_math/frontend/description', 'rank_math/opengraph/facebook/og_description' ),
			'aioseo'   => array( 'aioseo_description', 'aioseo_open_graph_description' ),
			'seopress' => array( 'seopress_titles_desc', 'seopress_social_og_desc' ),
			default    => array(),
		};
	}

	private function normalize_text( string $value ): string {
		$value = wp_strip_all_tags( $value, true );
		$value = preg_replace( '/\s+/', ' ', $value ) ?? '';
		return trim( $value );
	}

	private function trim_to_length( string $value ): string {
		$value = $this->normalize_text( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $value ) <= self::MAX_DESCRIPTION_LENGTH ) {
			return $value;
		}
		if ( ! function_exists( 'mb_strlen' ) && strlen( $value ) <= self::MAX_DESCRIPTION_LENGTH ) {
			return $value;
		}

		// Trim to the last word boundary inside the limit, then append an ellipsis.
		$limit = self::MAX_DESCRIPTION_LENGTH - 1;
		if ( function_exists( 'mb_substr' ) ) {
			$truncated = mb_substr( $value, 0, $limit );
		} else {
			$truncated = substr( $value, 0, $limit );
		}

		$last_space = strrpos( $truncated, ' ' );
		if ( false !== $last_space && $last_space > ( $limit / 2 ) ) {
			$truncated = substr( $truncated, 0, $last_space );
		}
		return rtrim( $truncated, " ,;:.-" ) . '…';
	}

	/**
	 * @param string[] $names
	 */
	private function join_with_and( array $names ): string {
		$count = count( $names );
		if ( 0 === $count ) {
			return '';
		}
		if ( 1 === $count ) {
			return $names[0];
		}
		if ( 2 === $count ) {
			return sprintf(
				/* translators: 1: first item, 2: second item */
				esc_html__( '%1$s and %2$s', 'brag-book-gallery' ),
				$names[0],
				$names[1]
			);
		}
		$last = array_pop( $names );
		return sprintf(
			/* translators: 1: comma-separated items, 2: final item */
			esc_html__( '%1$s, and %2$s', 'brag-book-gallery' ),
			implode( ', ', $names ),
			$last
		);
	}
}
