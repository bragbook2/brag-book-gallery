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

use BRAGBookGallery\Includes\Core\Settings_Helper;
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
	 * Default search radius in miles, advertised to the client script. The
	 * authoritative radius (and the 50→100 mile widening) is applied server-side
	 * by the shared context pager.
	 */
	private const DEFAULT_RADIUS_MILES = 50;

	/**
	 * Register hooks for the location search.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'ajax_search' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ self::class, 'ajax_search' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
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
	 * Register the location search script and Google Maps Places library.
	 *
	 * Registered (not enqueued) here so the assets load only where the widget is
	 * actually rendered. {@see render_search()} enqueues them on demand, which
	 * keeps the (billable) Google Maps API off case views and every other page
	 * that has no location search to drive.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	public static function register_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Google Maps JS API — Places library only (distance is computed server-side).
		wp_register_script(
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

		wp_register_script(
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
	 * Enqueue the previously-registered location search assets.
	 *
	 * Called from {@see render_search()} so Google Maps and the search script
	 * load only on views that render the widget.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	private static function enqueue_assets(): void {
		wp_enqueue_script( 'brag-book-google-maps' );
		wp_enqueue_script( 'brag-book-gallery-location-search' );
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

		// Committed to rendering the widget — load its assets now, so Google Maps
		// only loads on views that actually have a location search.
		self::enqueue_assets();

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

		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = Settings_Helper::get_items_per_page();

		// Delegate to the shared, context-aware pager so the location results
		// paginate and order identically to the "load more" button that continues
		// them. The context carries the location, so distance scoping/sorting and
		// the 50→100 mile widening happen inside the shared resolver.
		$result = Cases_Handler::render_context_page(
			[
				'procedure_slug' => $procedure_slug,
				'lat'            => $lat,
				'lng'            => $lng,
			],
			$page,
			$per_page
		);

		wp_send_json_success( [
			'html'    => $result['html'],
			'count'   => $result['total'],
			'total'   => $result['total'],
			'radius'  => $result['radius'],
			'hasMore' => $result['has_more'],
			'page'    => $page,
		] );
	}
}
