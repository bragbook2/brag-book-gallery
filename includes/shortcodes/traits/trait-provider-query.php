<?php
/**
 * Provider query trait.
 *
 * Shared helpers for filtering case queries by a provider API ID. Providers are
 * synced into the `brag_book_providers` taxonomy and associated with each
 * `brag_book_cases` post, so filtering is done with a `tax_query` against that
 * taxonomy rather than reading denormalized post meta.
 *
 * @package    BRAGBookGallery
 * @subpackage Shortcodes\Traits
 * @since      4.9.0
 */

namespace BRAGBookGallery\Includes\Shortcodes\Traits;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Trait_Provider_Query
 *
 * @since 4.9.0
 */
trait Trait_Provider_Query {

	/**
	 * Resolve a provider API ID to its matching provider taxonomy term IDs.
	 *
	 * Providers are synced into the `brag_book_providers` taxonomy with the API
	 * ID stored as term meta. Newer syncs store it under `provider_id`; older
	 * syncs may only have `provider_member_id`, so both are checked.
	 *
	 * @param int $provider_id Provider API ID to look up.
	 *
	 * @return int[] Matching term IDs (usually zero or one).
	 * @since 4.9.0
	 */
	protected static function get_provider_term_ids( int $provider_id ): array {
		if ( $provider_id <= 0 ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => Taxonomies::TAXONOMY_PROVIDERS,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => [
					'relation' => 'OR',
					[
						'key'   => 'provider_id',
						'value' => $provider_id,
					],
					[
						'key'   => 'provider_member_id',
						'value' => $provider_id,
					],
				],
			]
		);

		return is_array( $terms ) ? array_map( 'absint', $terms ) : [];
	}

	/**
	 * Build a `tax_query` clause that limits a case query to a provider's terms.
	 *
	 * @param int[] $term_ids Provider taxonomy term IDs from get_provider_term_ids().
	 *
	 * @return array<string, mixed> A single WP_Query tax_query clause.
	 * @since 4.9.0
	 */
	protected static function build_provider_tax_query( array $term_ids ): array {
		return [
			'taxonomy' => Taxonomies::TAXONOMY_PROVIDERS,
			'field'    => 'term_id',
			'terms'    => array_map( 'absint', $term_ids ),
		];
	}

	/**
	 * Build the `tax_query` for a provider/procedure case grid.
	 *
	 * Combines the optional provider clause with an optional procedure category so
	 * a provider's grid on a category page only surfaces that category's cases.
	 * The two clauses are ANDed; either may be absent.
	 *
	 * @param array<string, mixed>|null $provider_tax_query Provider tax_query clause, or null.
	 * @param int                       $term_id            Procedure term ID to scope to, or 0.
	 *
	 * @return array<int|string, mixed> A WP_Query `tax_query` value (possibly empty).
	 * @since 4.9.2
	 */
	protected static function build_procedures_tax_query( ?array $provider_tax_query, int $term_id ): array {
		$clauses = array();

		if ( ! empty( $provider_tax_query ) ) {
			$clauses[] = $provider_tax_query;
		}

		if ( $term_id > 0 ) {
			$clauses[] = array(
				'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
				'field'    => 'term_id',
				'terms'    => array( $term_id ),
			);
		}

		if ( count( $clauses ) > 1 ) {
			$clauses['relation'] = 'AND';
		}

		return $clauses;
	}

	/**
	 * Build the ordered, de-duplicated list of case post IDs for a provider grid.
	 *
	 * The same case synced under more than one procedure category exists as
	 * separate posts that share a `brag_book_gallery_case_id`. Each case must be
	 * counted, paginated and navigated exactly once, so this collapses those
	 * duplicates keeping the first occurrence in the query order. The order is
	 * deterministic (date DESC, post ID tiebreak) so separate requests slice the
	 * same stable list.
	 *
	 * @param array<string, mixed>|null $provider_tax_query Provider tax_query clause, or null.
	 * @param int                       $term_id            Procedure term ID to scope to, or 0.
	 *
	 * @return int[] Ordered, de-duplicated case post IDs.
	 * @since 4.9.2
	 */
	protected static function get_ordered_unique_case_post_ids( ?array $provider_tax_query, int $term_id ): array {
		$query_args = array(
			'post_type'      => Post_Types::POST_TYPE_CASES,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'no_found_rows'  => true,
		);

		$tax_query = self::build_procedures_tax_query( $provider_tax_query, $term_id );
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		$query    = new \WP_Query( $query_args );
		$post_ids = array_map( 'absint', $query->posts );

		if ( empty( $post_ids ) ) {
			return array();
		}

		// Prime the meta cache once so the per-post case ID lookups don't each
		// issue their own query.
		update_meta_cache( 'post', $post_ids );

		$unique_ids    = array();
		$seen_case_ids = array();

		foreach ( $post_ids as $post_id ) {
			$case_id = get_post_meta( $post_id, 'brag_book_gallery_case_id', true );

			if ( '' !== (string) $case_id ) {
				if ( in_array( $case_id, $seen_case_ids, true ) ) {
					continue;
				}
				$seen_case_ids[] = $case_id;
			}

			$unique_ids[] = $post_id;
		}

		return $unique_ids;
	}
}
