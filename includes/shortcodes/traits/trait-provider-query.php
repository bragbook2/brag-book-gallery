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
}
