<?php
/**
 * Provider Finder
 *
 * Powers the "Find a Provider" store-locator modal: a geo-based list of
 * practices/providers with a Google map. Active only when both the Providers
 * and Practices features are enabled.
 *
 * @package BRAGBookGallery
 * @subpackage Extend
 * @since 4.6.0
 */

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider_Finder class
 *
 * @since 4.6.0
 */
class Provider_Finder {

	/**
	 * AJAX action name for fetching practices.
	 */
	private const AJAX_ACTION = 'brag_book_get_practices';

	/**
	 * Register hooks for the provider finder.
	 *
	 * @since 4.6.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'ajax_get_practices' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ self::class, 'ajax_get_practices' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Whether the provider finder is available.
	 *
	 * Requires both the Providers and Practices features to be enabled.
	 *
	 * @since 4.6.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( 'brag_book_gallery_enable_providers', false )
			&& (bool) get_option( 'brag_book_gallery_enable_practices', false );
	}

	/**
	 * Get the configured Google Maps API key.
	 *
	 * @since 4.6.0
	 * @return string
	 */
	public static function get_api_key(): string {
		return (string) get_option( 'brag_book_gallery_google_maps_api_key', '' );
	}

	/**
	 * Enqueue the provider finder script and Google Maps.
	 *
	 * @since 4.6.0
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$api_key = self::get_api_key();

		// Google Maps JS API (geometry library for distance calculations).
		if ( ! empty( $api_key ) ) {
			wp_enqueue_script(
				'brag-book-google-maps',
				'https://maps.googleapis.com/maps/api/js?' . http_build_query( [
					'key'       => $api_key,
					'libraries' => 'geometry,places',
					'loading'   => 'async',
				] ),
				[],
				null,
				true
			);
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script(
			'brag-book-gallery-provider-finder',
			Setup::get_asset_url( 'assets/js/brag-book-gallery-provider-finder' . $suffix . '.js' ),
			[],
			self::asset_version( $suffix ),
			true
		);

		wp_localize_script(
			'brag-book-gallery-provider-finder',
			'bragBookProviderFinder',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( 'brag_book_gallery_nonce' ),
				'hasMap'  => ! empty( $api_key ),
			]
		);
	}

	/**
	 * Asset version string for cache busting.
	 *
	 * @since 4.6.0
	 * @param string $suffix Asset filename suffix ('' or '.min').
	 * @return string
	 */
	private static function asset_version( string $suffix = '.min' ): string {
		$file = Setup::get_plugin_path() . 'assets/js/brag-book-gallery-provider-finder' . $suffix . '.js';
		return file_exists( $file ) ? (string) filemtime( $file ) : '4.6.0';
	}

	/**
	 * Render the "Find a Provider" trigger button.
	 *
	 * @since 4.6.0
	 * @param string $context Placement context: 'title' or 'controls'.
	 * @return string Button HTML, or empty string when the feature is off.
	 */
	public static function render_button( string $context = 'controls' ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		// Match the Request a Consultation button styling; full width in the sidebar.
		$classes = 'brag-book-gallery-button brag-book-gallery-find-provider';
		if ( 'controls' === $context ) {
			$classes .= ' brag-book-gallery-button--full';
		}

		return sprintf(
			'<button type="button" class="%1$s" data-action="find-provider">'
			. '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
			. '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'
			. '<span>%2$s</span></button>',
			esc_attr( $classes ),
			esc_html__( 'Find a Provider', 'brag-book-gallery' )
		);
	}

	/**
	 * Render the locator dialog markup.
	 *
	 * Output once per gallery. The list and map are populated by JavaScript.
	 * When a procedure context is supplied (e.g. on a procedure view), the
	 * title reads "Find a Provider for {Procedure}" and results are scoped to
	 * practices whose providers have cases for that procedure.
	 *
	 * @since 4.6.0
	 * @param array $procedure Optional procedure context: 'slug' and 'name'.
	 * @return string Dialog HTML, or empty string when the feature is off.
	 */
	public static function render_dialog( array $procedure = [] ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$procedure_slug = isset( $procedure['slug'] ) ? (string) $procedure['slug'] : '';
		$procedure_name = isset( $procedure['name'] ) ? (string) $procedure['name'] : '';

		$title = '' !== $procedure_name
			/* translators: %s: procedure name, e.g. "Breast Implants". */
			? sprintf( __( 'Find a Provider for %s', 'brag-book-gallery' ), $procedure_name )
			: __( 'Find a Provider', 'brag-book-gallery' );

		ob_start();
		?>
		<dialog class="brag-book-gallery-dialog brag-book-gallery-provider-finder-dialog" id="findProviderDialog"<?php echo '' !== $procedure_slug ? ' data-procedure-slug="' . esc_attr( $procedure_slug ) . '"' : ''; ?>>
			<div class="brag-book-gallery-dialog-content brag-book-gallery-provider-finder">
				<div class="brag-book-gallery-dialog-header">
					<h2 class="brag-book-gallery-dialog-title"><?php echo esc_html( $title ); ?></h2>
					<button class="brag-book-gallery-dialog-close" data-action="close-dialog" aria-label="<?php esc_attr_e( 'Close dialog', 'brag-book-gallery' ); ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
				<div class="brag-book-gallery-provider-finder-search">
					<div class="brag-book-gallery-provider-finder-search-field">
						<input type="text" id="bbProviderFinderSearch" class="brag-book-gallery-provider-finder-search-input" placeholder="<?php esc_attr_e( 'Enter ZIP code or city', 'brag-book-gallery' ); ?>" autocomplete="off" />
						<button type="button" class="brag-book-gallery-provider-finder-locate" data-action="provider-finder-locate" aria-label="<?php esc_attr_e( 'Use my location', 'brag-book-gallery' ); ?>" title="<?php esc_attr_e( 'Use my location', 'brag-book-gallery' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8"/><line x1="12" y1="1" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="23"/><line x1="1" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="23" y2="12"/><circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/></svg>
						</button>
					</div>
					<select id="bbProviderFinderRadius" class="brag-book-gallery-provider-finder-radius" aria-label="<?php esc_attr_e( 'Search radius', 'brag-book-gallery' ); ?>">
						<option value="5"><?php esc_html_e( '5 miles', 'brag-book-gallery' ); ?></option>
						<option value="10"><?php esc_html_e( '10 miles', 'brag-book-gallery' ); ?></option>
						<option value="25" selected><?php esc_html_e( '25 miles', 'brag-book-gallery' ); ?></option>
						<option value="50"><?php esc_html_e( '50 miles', 'brag-book-gallery' ); ?></option>
						<option value="100"><?php esc_html_e( '100 miles', 'brag-book-gallery' ); ?></option>
					</select>
					<button type="button" class="brag-book-gallery-button" data-action="provider-finder-search"><?php esc_html_e( 'Search', 'brag-book-gallery' ); ?></button>
					<button type="button" class="brag-book-gallery-button brag-book-gallery-provider-finder-reset" data-action="provider-finder-reset"><?php esc_html_e( 'Reset', 'brag-book-gallery' ); ?></button>
				</div>
				<div class="brag-book-gallery-provider-finder-status" id="bbProviderFinderStatus" role="status" aria-live="polite"></div>
				<div class="brag-book-gallery-provider-finder-body">
					<div class="brag-book-gallery-provider-finder-list" id="bbProviderFinderList" aria-live="polite"></div>
					<div class="brag-book-gallery-provider-finder-map" id="bbProviderFinderMap"></div>
				</div>
			</div>
		</dialog>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX handler: return all practices with geo, details, and providers.
	 *
	 * @since 4.6.0
	 * @return void
	 */
	public static function ajax_get_practices(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'brag-book-gallery' ) ], 403 );
		}

		if ( ! self::is_enabled() ) {
			wp_send_json_error( [ 'message' => __( 'Provider finder is not enabled.', 'brag-book-gallery' ) ], 400 );
		}

		// Optional procedure context — scope results to practices whose providers
		// have cases for that procedure.
		$procedure_slug = isset( $_POST['procedure'] )
			? sanitize_title( wp_unslash( $_POST['procedure'] ) )
			: '';

		$query_args = [
			'post_type'      => Post_Types::POST_TYPE_PRACTICES,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( '' !== $procedure_slug ) {
			$allowed_ids = self::get_practice_ids_for_procedure( $procedure_slug );
			if ( empty( $allowed_ids ) ) {
				wp_send_json_success( [ 'practices' => [] ] );
			}
			$query_args['post__in'] = $allowed_ids;
		}

		$posts = get_posts( $query_args );

		$practices = [];
		foreach ( $posts as $post ) {
			$formatted = self::format_practice( $post );
			if ( null !== $formatted ) {
				$practices[] = $formatted;
			}
		}

		wp_send_json_success( [ 'practices' => $practices ] );
	}

	/**
	 * Build a procedure context array for the dialog from a procedures term.
	 *
	 * @since 4.6.0
	 * @param \WP_Term|null $term A term that may belong to the procedures taxonomy.
	 * @return array{slug:string,name:string} Context, or an empty-string pair.
	 */
	public static function procedure_context( ?\WP_Term $term ): array {
		if ( $term instanceof \WP_Term && Taxonomies::TAXONOMY_PROCEDURES === $term->taxonomy ) {
			return [ 'slug' => $term->slug, 'name' => $term->name ];
		}
		return [ 'slug' => '', 'name' => '' ];
	}

	/**
	 * Get the practice post IDs relevant to a procedure.
	 *
	 * Resolves the chain procedure → providers (from that procedure's cases) →
	 * practices linked to those providers.
	 *
	 * @since 4.6.0
	 * @param string $procedure_slug The procedures taxonomy term slug.
	 * @return int[] Practice post IDs (empty when none apply).
	 */
	private static function get_practice_ids_for_procedure( string $procedure_slug ): array {
		// Cases assigned to this procedure.
		$case_ids = get_posts( [
			'post_type'              => Post_Types::POST_TYPE_CASES,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'tax_query'              => [
				[
					'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
					'field'    => 'slug',
					'terms'    => $procedure_slug,
				],
			],
		] );

		if ( empty( $case_ids ) ) {
			return [];
		}

		// Providers assigned to those cases.
		$provider_term_ids = wp_get_object_terms(
			$case_ids,
			Taxonomies::TAXONOMY_PROVIDERS,
			[ 'fields' => 'ids' ]
		);

		if ( is_wp_error( $provider_term_ids ) || empty( $provider_term_ids ) ) {
			return [];
		}

		$provider_term_ids = array_values( array_unique( array_map( 'intval', $provider_term_ids ) ) );

		// Practices linked to those providers.
		$practice_ids = get_posts( [
			'post_type'              => Post_Types::POST_TYPE_PRACTICES,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'tax_query'              => [
				[
					'taxonomy' => Taxonomies::TAXONOMY_PROVIDERS,
					'field'    => 'term_id',
					'terms'    => $provider_term_ids,
				],
			],
		] );

		return array_map( 'intval', $practice_ids );
	}

	/**
	 * Format a practice post for the locator response.
	 *
	 * @since 4.6.0
	 * @param \WP_Post $post The practice post.
	 * @return array|null Practice data, or null if it cannot be mapped.
	 */
	private static function format_practice( \WP_Post $post ): ?array {
		$lat = get_post_meta( $post->ID, 'brag_book_gallery_practice_latitude', true );
		$lng = get_post_meta( $post->ID, 'brag_book_gallery_practice_longitude', true );

		$accreditations = get_post_meta( $post->ID, 'brag_book_gallery_practice_accreditations', true );

		return [
			'id'             => (int) $post->ID,
			'name'           => get_the_title( $post ),
			'address'        => (string) get_post_meta( $post->ID, 'brag_book_gallery_practice_address', true ),
			'city'           => (string) get_post_meta( $post->ID, 'brag_book_gallery_practice_city', true ),
			'state'          => (string) get_post_meta( $post->ID, 'brag_book_gallery_practice_state', true ),
			'zip'            => (string) get_post_meta( $post->ID, 'brag_book_gallery_practice_zip', true ),
			'phone'          => (string) get_post_meta( $post->ID, 'brag_book_gallery_practice_phone', true ),
			'website'        => (string) get_post_meta( $post->ID, 'brag_book_gallery_practice_website_url', true ),
			'lat'            => '' !== $lat ? (float) $lat : null,
			'lng'            => '' !== $lng ? (float) $lng : null,
			'accreditations' => is_array( $accreditations ) ? array_values( $accreditations ) : [],
			'providers'      => self::get_practice_providers( $post->ID ),
		];
	}

	/**
	 * Get the providers linked to a practice (via the providers taxonomy).
	 *
	 * @since 4.6.0
	 * @param int $post_id The practice post ID.
	 * @return array List of providers with name and image.
	 */
	private static function get_practice_providers( int $post_id ): array {
		$terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_PROVIDERS );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}

		$providers = [];
		foreach ( $terms as $term ) {
			$image = (string) get_term_meta( $term->term_id, 'provider_image_url', true );
			if ( '' === $image ) {
				$photo = get_term_meta( $term->term_id, 'provider_profile_photo', true );
				if ( ! empty( $photo ) ) {
					$image = (string) ( wp_get_attachment_image_url( (int) $photo, [ 48, 48 ] ) ?: '' );
				}
			}

			$providers[] = [
				'name'  => $term->name,
				'image' => $image,
			];
		}

		return $providers;
	}
}
