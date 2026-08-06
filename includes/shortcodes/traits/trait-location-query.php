<?php
/**
 * Location query trait.
 *
 * Shared geo helpers for scoping cases to a search point. Case coordinates live
 * on linked practice posts (`brag_book_gallery_practice_latitude` /
 * `_longitude`), associated to cases through the providers taxonomy. Distances
 * are great-circle (Haversine) from the search point to a case's nearest
 * practice. Used by the location search widget and by the context-aware
 * "load more" pagination so both scope and order results identically.
 *
 * @package    BRAGBookGallery
 * @subpackage Shortcodes\Traits
 * @since      3.3.3
 */

namespace BRAGBookGallery\Includes\Shortcodes\Traits;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Trait_Location_Query
 *
 * @since 3.3.3
 */
trait Trait_Location_Query {

	/**
	 * Earth's mean radius in miles, for the Haversine formula.
	 *
	 * @since 3.3.3
	 * @var float
	 */
	private static float $earth_radius_miles = 3958.7559;

	/**
	 * Map provider terms to the coordinates of their linked practices.
	 *
	 * @since 3.3.3
	 * @return array<int,array<int,array{0:float,1:float}>> provider term ID => list of [lat, lng].
	 */
	protected static function build_provider_practice_geo_map(): array {
		$practice_ids = get_posts(
			[
				'post_type'              => Post_Types::POST_TYPE_PRACTICES,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			]
		);

		$map = [];

		foreach ( $practice_ids as $practice_id ) {
			$lat = get_post_meta( $practice_id, 'brag_book_gallery_practice_latitude', true );
			$lng = get_post_meta( $practice_id, 'brag_book_gallery_practice_longitude', true );

			if ( '' === $lat || '' === $lng ) {
				continue;
			}

			$coord = [ (float) $lat, (float) $lng ];

			$term_ids = wp_get_post_terms( $practice_id, Taxonomies::TAXONOMY_PROVIDERS, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $term_ids ) ) {
				continue;
			}

			foreach ( $term_ids as $term_id ) {
				$map[ (int) $term_id ][] = $coord;
			}
		}

		return $map;
	}

	/**
	 * Map each case to its assigned provider term IDs in a single query.
	 *
	 * @since 3.3.3
	 * @param int[] $case_ids Candidate case post IDs.
	 * @return array<int,int[]> Map of case ID to provider term IDs.
	 */
	protected static function map_cases_to_provider_terms( array $case_ids ): array {
		if ( empty( $case_ids ) ) {
			return [];
		}

		$terms = wp_get_object_terms(
			$case_ids,
			Taxonomies::TAXONOMY_PROVIDERS,
			[ 'fields' => 'all_with_object_id' ]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$map = [];
		foreach ( $terms as $term ) {
			$map[ (int) $term->object_id ][] = (int) $term->term_id;
		}

		return $map;
	}

	/**
	 * Compute the nearest associated practice distance (miles) for each case.
	 *
	 * Cases with no geocoded practice are omitted from the result.
	 *
	 * @since 3.3.3
	 * @param int[] $case_ids Candidate case post IDs.
	 * @param float $lat      Search latitude.
	 * @param float $lng      Search longitude.
	 * @return array<int,float> Map of case ID to nearest distance in miles.
	 */
	protected static function distances_by_case( array $case_ids, float $lat, float $lng ): array {
		$geo_map    = self::build_provider_practice_geo_map();
		$case_terms = self::map_cases_to_provider_terms( $case_ids );
		$distances  = [];

		foreach ( $case_ids as $case_id ) {
			$nearest = null;

			foreach ( $case_terms[ $case_id ] ?? [] as $term_id ) {
				foreach ( $geo_map[ $term_id ] ?? [] as $coord ) {
					$distance = self::haversine_miles( $lat, $lng, $coord[0], $coord[1] );
					if ( null === $nearest || $distance < $nearest ) {
						$nearest = $distance;
					}
				}
			}

			if ( null !== $nearest ) {
				$distances[ $case_id ] = $nearest;
			}
		}

		return $distances;
	}

	/**
	 * Filter cases to the default radius, widening to the extended radius when
	 * the default returns nothing. Results are ordered nearest-first.
	 *
	 * @since 3.3.3
	 * @param array<int,float> $distances       Map of case ID to distance in miles.
	 * @param int              $default_radius  Preferred search radius (miles).
	 * @param int              $extended_radius Fallback radius when the default is empty.
	 * @return array{0:int[],1:int} Ordered matched case IDs and the radius used.
	 */
	protected static function filter_by_radius( array $distances, int $default_radius, int $extended_radius ): array {
		asort( $distances );

		$within_default = array_keys(
			array_filter( $distances, static fn( float $miles ): bool => $miles <= $default_radius )
		);
		if ( ! empty( $within_default ) ) {
			return [ array_map( 'intval', $within_default ), $default_radius ];
		}

		$within_extended = array_keys(
			array_filter( $distances, static fn( float $miles ): bool => $miles <= $extended_radius )
		);

		return [ array_map( 'intval', $within_extended ), $extended_radius ];
	}

	/**
	 * Format a distance in miles as a human-readable, localized label.
	 *
	 * Distances under 10 miles keep one decimal of precision ("3.4 miles away");
	 * larger distances round to whole miles ("42 miles away").
	 *
	 * @since 3.3.3
	 * @param float $miles Distance in miles.
	 * @return string Localized distance label.
	 */
	protected static function format_distance( float $miles ): string {
		$display = $miles < 10.0
			? number_format_i18n( $miles, 1 )
			: number_format_i18n( round( $miles ) );

		/* translators: %s: distance in miles (e.g. "3.4"). */
		return sprintf( _n( '%s mile away', '%s miles away', (int) round( $miles ), 'brag-book-gallery' ), $display );
	}

	/**
	 * Great-circle distance between two points, in miles (Haversine).
	 *
	 * @since 3.3.3
	 * @param float $lat1 First latitude.
	 * @param float $lng1 First longitude.
	 * @param float $lat2 Second latitude.
	 * @param float $lng2 Second longitude.
	 * @return float Distance in miles.
	 */
	protected static function haversine_miles( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );

		$a = ( sin( $d_lat / 2 ) ** 2 )
			+ ( cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * ( sin( $d_lng / 2 ) ** 2 ) );

		return self::$earth_radius_miles * 2 * asin( min( 1.0, sqrt( $a ) ) );
	}
}
