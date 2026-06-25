<?php
/**
 * Location Search
 *
 * Powers the inline, location-based gallery search rendered before the filter
 * dropdown. The browser resolves a search query (or the visitor's current
 * position) to coordinates via Google Places, then this endpoint returns the
 * cases whose associated provider's practice is nearest — within 50 miles by
 * default, automatically widening to 100 miles when nothing is closer.
 *
 * Active only when both the Providers and Practices features are enabled and a
 * Google Maps API key is configured.
 *
 * @package BRAGBookGallery
 * @subpackage Extend
 * @since 4.7.0
 */

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Shortcodes\Cases_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Location_Search class
 *
 * @since 4.7.0
 */
class Location_Search {

	/**
	 * AJAX action name for the location search.
	 */
	private const AJAX_ACTION = 'brag_book_gallery_location_search';

	/**
	 * Default search radius in miles.
	 */
	private const DEFAULT_RADIUS_MILES = 50;

	/**
	 * Fallback radius in miles, used when nothing is within the default radius.
	 */
	private const EXTENDED_RADIUS_MILES = 100;

	/**
	 * Mean radius of the Earth in miles (for the Haversine formula).
	 */
	private const EARTH_RADIUS_MILES = 3958.8;

	/**
	 * Register hooks for the location search.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'ajax_search' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ self::class, 'ajax_search' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Whether the location search is available.
	 *
	 * Gated on a configured Google Maps API key — without one the autocomplete
	 * cannot load, so the search is not shown. The widget is rendered hidden and
	 * only revealed by JavaScript once the Maps Places library loads correctly.
	 *
	 * @since 4.7.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '' !== trim( self::get_api_key() );
	}

	/**
	 * Get the configured Google Maps API key.
	 *
	 * @since 4.7.0
	 * @return string
	 */
	public static function get_api_key(): string {
		return (string) get_option( 'brag_book_gallery_google_maps_api_key', '' );
	}

	/**
	 * Enqueue the location search script and Google Maps Places library.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Google Maps JS API — Places library only (distance is computed server-side).
		wp_enqueue_script(
			'brag-book-google-maps',
			'https://maps.googleapis.com/maps/api/js?' . http_build_query( [
				'key'       => self::get_api_key(),
				'libraries' => 'places',
				'loading'   => 'async',
			] ),
			[],
			null,
			true
		);

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script(
			'brag-book-gallery-location-search',
			Setup::get_asset_url( 'assets/js/brag-book-gallery-location-search' . $suffix . '.js' ),
			[],
			self::asset_version( $suffix ),
			true
		);

		wp_localize_script(
			'brag-book-gallery-location-search',
			'bragBookLocationSearch',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'action'        => self::AJAX_ACTION,
				'nonce'         => wp_create_nonce( 'brag_book_gallery_nonce' ),
				'defaultRadius' => self::DEFAULT_RADIUS_MILES,
				'placeholder'   => __( 'Enter location...', 'brag-book-gallery' ),
			]
		);
	}

	/**
	 * Asset version string for cache busting.
	 *
	 * @since 4.7.0
	 * @param string $suffix Asset filename suffix ('' or '.min').
	 * @return string
	 */
	private static function asset_version( string $suffix = '.min' ): string {
		$file = Setup::get_plugin_path() . 'assets/js/brag-book-gallery-location-search' . $suffix . '.js';
		return file_exists( $file ) ? (string) filemtime( $file ) : '4.7.0';
	}

	/**
	 * Render the inline location search markup.
	 *
	 * Output before the filter dropdown. The autocomplete and geolocation are
	 * wired up by JavaScript. A procedure context is required: results are scoped
	 * to that procedure's cases, so the search is only shown on a procedure view.
	 * The contextless main gallery would return cases across every procedure, so
	 * the search is intentionally hidden there.
	 *
	 * @since 4.7.0
	 * @param array $procedure Procedure context: 'slug' and 'name'.
	 * @return string Search HTML, or empty string when the feature is off or no
	 *                procedure context is supplied.
	 */
	public static function render_search( array $procedure = [] ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$procedure_slug = isset( $procedure['slug'] ) ? (string) $procedure['slug'] : '';

		// No procedure context means no way to scope results to the page; hide
		// the search rather than return cases from unrelated procedures.
		if ( '' === $procedure_slug ) {
			return '';
		}

		ob_start();
		?>
		<div class="brag-book-gallery-location-search brag-book-gallery-location-search--loading" data-procedure-slug="<?php echo esc_attr( $procedure_slug ); ?>">
			<div class="brag-book-gallery-location-search__field">
				<!-- Google Places PlaceAutocompleteElement is mounted here by JavaScript. -->
				<div class="brag-book-gallery-location-search__autocomplete" id="bbLocationSearchAutocomplete"></div>
				<button type="button" class="brag-book-gallery-location-search__button brag-book-gallery-location-search__locate" data-action="location-search-locate" aria-label="<?php esc_attr_e( 'Use my location', 'brag-book-gallery' ); ?>" title="<?php esc_attr_e( 'Use my location', 'brag-book-gallery' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8"/><line x1="12" y1="1" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="23"/><line x1="1" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="23" y2="12"/><circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/></svg>
				</button>
			</div>
			<p class="brag-book-gallery-location-search__status" role="status" aria-live="polite"></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the page-level results banner.
	 *
	 * Output across the top of the gallery (above the content title). JavaScript
	 * writes the "Showing N cases within R miles of …" message here so it spans
	 * the page rather than sitting beside the search field. Empty until a search
	 * runs (hidden via CSS).
	 *
	 * @since 4.7.0
	 * @return string Banner HTML, or empty string when the feature is off.
	 */
	public static function render_results_banner(): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		return '<div class="brag-book-gallery-location-search-results" id="bbLocationSearchResults" role="status" aria-live="polite"></div>';
	}

	/**
	 * AJAX handler: return cases ordered by proximity to a location.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	public static function ajax_search(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'brag-book-gallery' ) ], 403 );
		}

		if ( ! self::is_enabled() ) {
			wp_send_json_error( [ 'message' => __( 'Location search is not enabled.', 'brag-book-gallery' ) ], 400 );
		}

		if ( ! isset( $_POST['lat'], $_POST['lng'] )
			|| ! is_numeric( wp_unslash( $_POST['lat'] ) )
			|| ! is_numeric( wp_unslash( $_POST['lng'] ) ) ) {
			wp_send_json_error( [ 'message' => __( 'A valid location is required.', 'brag-book-gallery' ) ], 400 );
		}

		$lat = (float) wp_unslash( $_POST['lat'] );
		$lng = (float) wp_unslash( $_POST['lng'] );

		if ( $lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0 ) {
			wp_send_json_error( [ 'message' => __( 'A valid location is required.', 'brag-book-gallery' ) ], 400 );
		}

		$procedure_slug = isset( $_POST['procedure'] )
			? sanitize_title( wp_unslash( $_POST['procedure'] ) )
			: '';

		$case_ids = self::get_candidate_case_ids( $procedure_slug );
		if ( empty( $case_ids ) ) {
			wp_send_json_success( self::empty_response( self::DEFAULT_RADIUS_MILES, 0 ) );
		}

		$distances = self::distances_by_case( $case_ids, $lat, $lng );

		list( $matched_ids, $used_radius ) = self::filter_by_radius( $distances );

		if ( empty( $matched_ids ) ) {
			wp_send_json_success( self::empty_response( self::EXTENDED_RADIUS_MILES, count( $case_ids ) ) );
		}

		wp_send_json_success( [
			'html'   => self::render_matched_cases( $matched_ids, $procedure_slug, $distances ),
			'count'  => count( $matched_ids ),
			'radius' => $used_radius,
			'total'  => count( $case_ids ),
		] );
	}

	/**
	 * Build a no-results success payload.
	 *
	 * @since 4.7.0
	 * @param int $radius The radius (miles) that was searched.
	 * @param int $total  Total candidate cases considered.
	 * @return array{html:string,count:int,radius:int,total:int}
	 */
	private static function empty_response( int $radius, int $total ): array {
		return [
			'html'   => '',
			'count'  => 0,
			'radius' => $radius,
			'total'  => $total,
		];
	}

	/**
	 * Resolve the candidate case IDs, optionally scoped to a procedure.
	 *
	 * @since 4.7.0
	 * @param string $procedure_slug Optional procedures taxonomy slug.
	 * @return int[] Published case post IDs.
	 */
	private static function get_candidate_case_ids( string $procedure_slug ): array {
		$args = [
			'post_type'              => Post_Types::POST_TYPE_CASES,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		];

		if ( '' !== $procedure_slug ) {
			$args['tax_query'] = [
				[
					'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
					'field'    => 'slug',
					'terms'    => $procedure_slug,
				],
			];
		}

		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * Compute the nearest associated practice distance (miles) for each case.
	 *
	 * Cases with no geocoded practice are omitted from the result.
	 *
	 * @since 4.7.0
	 * @param int[] $case_ids Candidate case post IDs.
	 * @param float $lat      Search latitude.
	 * @param float $lng      Search longitude.
	 * @return array<int,float> Map of case ID to nearest distance in miles.
	 */
	private static function distances_by_case( array $case_ids, float $lat, float $lng ): array {
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
	 * Map provider terms to the coordinates of their linked practices.
	 *
	 * @since 4.7.0
	 * @return array<int,array<int,array{0:float,1:float}>> provider term ID => list of [lat, lng].
	 */
	private static function build_provider_practice_geo_map(): array {
		$practice_ids = get_posts( [
			'post_type'              => Post_Types::POST_TYPE_PRACTICES,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		] );

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
	 * @since 4.7.0
	 * @param int[] $case_ids Candidate case post IDs.
	 * @return array<int,int[]> Map of case ID to provider term IDs.
	 */
	private static function map_cases_to_provider_terms( array $case_ids ): array {
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
	 * Filter cases to the default radius, widening to the extended radius when
	 * the default returns nothing. Results are ordered nearest-first.
	 *
	 * @since 4.7.0
	 * @param array<int,float> $distances Map of case ID to distance in miles.
	 * @return array{0:int[],1:int} Ordered matched case IDs and the radius used.
	 */
	private static function filter_by_radius( array $distances ): array {
		asort( $distances );

		$within_default = array_keys(
			array_filter( $distances, static fn( float $miles ): bool => $miles <= self::DEFAULT_RADIUS_MILES )
		);
		if ( ! empty( $within_default ) ) {
			return [ $within_default, self::DEFAULT_RADIUS_MILES ];
		}

		$within_extended = array_keys(
			array_filter( $distances, static fn( float $miles ): bool => $miles <= self::EXTENDED_RADIUS_MILES )
		);

		return [ $within_extended, self::EXTENDED_RADIUS_MILES ];
	}

	/**
	 * Render the matched case cards in distance order.
	 *
	 * Uses the same renderer as the procedure gallery grid so the results honour
	 * the configured card design (default / v2 / v3), with a distance badge added.
	 *
	 * @since 4.7.0
	 * @param int[]            $case_ids       Ordered (nearest-first) case post IDs.
	 * @param string           $procedure_slug Procedure context the search is scoped to.
	 * @param array<int,float> $distances      Map of case ID to nearest distance in miles.
	 * @return string Concatenated case card HTML.
	 */
	private static function render_matched_cases( array $case_ids, string $procedure_slug, array $distances ): string {
		$image_display_mode = (string) get_option( 'brag_book_gallery_image_display_mode', 'single' );

		// Display name for the procedure the search is scoped to, matching the
		// procedure context the gallery grid passes to the card renderer.
		$term           = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
		$procedure_name = $term instanceof \WP_Term ? $term->name : '';

		$html = '';

		foreach ( $case_ids as $case_id ) {
			$post = get_post( $case_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$distance_label = isset( $distances[ $case_id ] )
				? self::format_distance( $distances[ $case_id ] )
				: '';

			$html .= Cases_Handler::render_wordpress_case_card(
				Cases_Handler::build_case_data_from_post( $post ),
				$image_display_mode,
				self::case_has_nudity( $case_id ),
				$procedure_name,
				'',
				'',
				$distance_label
			);
		}

		return $html;
	}

	/**
	 * Format a distance in miles as a human-readable, localized label.
	 *
	 * Distances under 10 miles keep one decimal of precision ("3.4 miles away");
	 * larger distances round to whole miles ("42 miles away").
	 *
	 * @since 4.7.0
	 * @param float $miles Distance in miles.
	 * @return string Localized distance label.
	 */
	private static function format_distance( float $miles ): string {
		$display = $miles < 10.0
			? number_format_i18n( $miles, 1 )
			: number_format_i18n( round( $miles ) );

		/* translators: %s: distance in miles (e.g. "3.4"). */
		return sprintf( _n( '%s mile away', '%s miles away', (int) round( $miles ), 'brag-book-gallery' ), $display );
	}

	/**
	 * Whether any of a case's procedures are flagged for nudity.
	 *
	 * @since 4.7.0
	 * @param int $case_id The case post ID.
	 * @return bool
	 */
	private static function case_has_nudity( int $case_id ): bool {
		$term_ids = wp_get_post_terms( $case_id, Taxonomies::TAXONOMY_PROCEDURES, [ 'fields' => 'ids' ] );
		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return false;
		}

		foreach ( $term_ids as $term_id ) {
			if ( 'true' === (string) get_term_meta( $term_id, 'nudity', true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Great-circle distance between two points, in miles (Haversine).
	 *
	 * @since 4.7.0
	 * @param float $lat1 First latitude.
	 * @param float $lng1 First longitude.
	 * @param float $lat2 Second latitude.
	 * @param float $lng2 Second longitude.
	 * @return float Distance in miles.
	 */
	private static function haversine_miles( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );

		$a = ( sin( $d_lat / 2 ) ** 2 )
			+ ( cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * ( sin( $d_lng / 2 ) ** 2 ) );

		return self::EARTH_RADIUS_MILES * 2 * asin( min( 1.0, sqrt( $a ) ) );
	}
}
