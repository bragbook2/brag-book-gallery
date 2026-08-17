<?php
/**
 * Provider Filter
 *
 * A dropdown filter listing each provider (doctor) with their avatar and name,
 * styled to match the gallery filter dropdown and rendered before it. Selecting
 * a provider replaces the case grid with that provider's cases via AJAX. When
 * rendered on a procedure view the results are additionally scoped to the
 * current procedure.
 *
 * Active only when the Providers feature is enabled and at least one provider
 * has cases in the current context.
 *
 * @package BRAGBookGallery
 * @subpackage Extend
 * @since 4.8.0
 */

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Settings_Helper;
use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Shortcodes\Cases_Handler;
use BRAGBookGallery\Includes\Shortcodes\HTML_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider_Filter class
 *
 * @since 4.8.0
 */
class Provider_Filter {

	/**
	 * AJAX action name for the provider filter.
	 */
	private const AJAX_ACTION = 'brag_book_gallery_provider_filter';

	/**
	 * Register hooks for the provider filter.
	 *
	 * @since 4.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'ajax_filter' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ self::class, 'ajax_filter' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Whether the provider filter is available.
	 *
	 * @since 4.8.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( 'brag_book_gallery_enable_providers', false );
	}

	/**
	 * Enqueue the provider filter script and configuration.
	 *
	 * @since 4.8.0
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script(
			'brag-book-gallery-provider-filter',
			Setup::get_asset_url( 'assets/js/brag-book-gallery-provider-filter' . $suffix . '.js' ),
			[],
			self::asset_version( $suffix ),
			true
		);

		wp_localize_script(
			'brag-book-gallery-provider-filter',
			'bragBookProviderFilter',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => self::AJAX_ACTION,
				'nonce'        => wp_create_nonce( 'brag_book_gallery_nonce' ),
				'defaultLabel' => __( 'Provider', 'brag-book-gallery' ),
				'emptyLabel'   => __( 'No cases found for this provider.', 'brag-book-gallery' ),
			]
		);
	}

	/**
	 * Asset version string for cache busting.
	 *
	 * @since 4.8.0
	 * @param string $suffix Asset filename suffix ('' or '.min').
	 * @return string
	 */
	private static function asset_version( string $suffix = '.min' ): string {
		$file = Setup::get_plugin_path() . 'assets/js/brag-book-gallery-provider-filter' . $suffix . '.js';
		return file_exists( $file ) ? (string) filemtime( $file ) : '4.8.0';
	}

	/**
	 * Render the provider filter dropdown.
	 *
	 * Lists the providers that have cases in the current context (scoped to the
	 * procedure when one is supplied). Returns an empty string when the feature
	 * is off or there are no providers to choose from.
	 *
	 * @since 4.8.0
	 * @param array $procedure Optional procedure context: 'slug' and 'name'.
	 * @return string Filter HTML, or empty string.
	 */
	public static function render_filter( array $procedure = [] ): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$procedure_slug = isset( $procedure['slug'] ) ? (string) $procedure['slug'] : '';
		$providers      = self::get_providers( $procedure_slug );

		if ( empty( $providers ) ) {
			return '';
		}

		ob_start();
		?>
		<details class="brag-book-gallery-filter-dropdown brag-book-gallery-provider-filter" id="provider-filter-details" data-procedure-slug="<?php echo esc_attr( $procedure_slug ); ?>">
			<summary class="brag-book-gallery-filter-dropdown__toggle">
				<span class="brag-book-gallery-provider-filter__toggle-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				</span>
				<span class="brag-book-gallery-provider-filter__label" data-default-label="<?php esc_attr_e( 'Provider', 'brag-book-gallery' ); ?>"><?php esc_html_e( 'Provider', 'brag-book-gallery' ); ?></span>
			</summary>
			<div class="brag-book-gallery-filter-dropdown__panel">
				<div class="brag-book-gallery-provider-filter__search">
					<svg class="brag-book-gallery-provider-filter__search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
					<input
						type="search"
						class="brag-book-gallery-provider-filter__search-input"
						placeholder="<?php esc_attr_e( 'Search providers…', 'brag-book-gallery' ); ?>"
						aria-label="<?php esc_attr_e( 'Search providers', 'brag-book-gallery' ); ?>"
						aria-controls="provider-filter-list"
						autocomplete="off"
					/>
				</div>
				<ul class="brag-book-gallery-provider-filter__list" id="provider-filter-list" role="listbox" aria-label="<?php esc_attr_e( 'Filter by provider', 'brag-book-gallery' ); ?>">
					<li>
						<button type="button" class="brag-book-gallery-provider-filter__option is-active" data-provider-slug="">
							<?php esc_html_e( 'All Providers', 'brag-book-gallery' ); ?>
						</button>
					</li>
					<?php foreach ( $providers as $provider ) : ?>
						<li>
							<button type="button" class="brag-book-gallery-provider-filter__option" data-provider-slug="<?php echo esc_attr( $provider['slug'] ); ?>" data-provider-id="<?php echo esc_attr( (string) $provider['member_id'] ); ?>" data-provider-name="<?php echo esc_attr( strtolower( $provider['name'] ) ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- avatar_markup escapes its output.
								echo self::avatar_markup( $provider );
								?>
								<span class="brag-book-gallery-provider-filter__name"><?php echo esc_html( $provider['name'] ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
					<li class="brag-book-gallery-provider-filter__no-match" hidden>
						<?php esc_html_e( 'No providers match your search.', 'brag-book-gallery' ); ?>
					</li>
				</ul>
				<div class="brag-book-gallery-provider-filter__actions">
					<button type="button" class="brag-book-gallery-button brag-book-gallery-button--clear" data-provider-reset>
						<?php esc_html_e( 'Reset', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>
		</details>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the procedure filter dropdown for a provider archive.
	 *
	 * The counterpart of the provider filter: on a provider archive the provider
	 * is fixed and the procedure is what there is left to choose, so the same
	 * dropdown lists the procedures that provider has cases in. It shares the
	 * provider filter's markup, styling and script; only the option meaning and
	 * the request it builds differ, which the widget reads from
	 * `data-filter-mode`.
	 *
	 * @since 4.9.3
	 * @param string $provider_slug Providers taxonomy slug of the archive.
	 * @return string Filter HTML, or empty string when there is nothing to filter.
	 */
	public static function render_procedure_filter( string $provider_slug ): string {
		// The shared script is only enqueued with the providers feature, so
		// without it the dropdown would render but do nothing.
		if ( '' === $provider_slug || ! self::is_enabled() ) {
			return '';
		}

		$procedures = self::get_procedures( $provider_slug );

		// One procedure filters to what is already on screen.
		if ( count( $procedures ) < 2 ) {
			return '';
		}

		ob_start();
		?>
		<details class="brag-book-gallery-filter-dropdown brag-book-gallery-provider-filter brag-book-gallery-procedure-filter" id="procedure-filter-details" data-filter-mode="procedure" data-provider-slug="<?php echo esc_attr( $provider_slug ); ?>">
			<summary class="brag-book-gallery-filter-dropdown__toggle">
				<span class="brag-book-gallery-provider-filter__toggle-icon">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor" aria-hidden="true"><path d="M400-240v-80h160v80H400ZM240-440v-80h480v80H240ZM120-640v-80h720v80H120Z"></path></svg>
				</span>
				<span class="brag-book-gallery-provider-filter__label" data-default-label="<?php esc_attr_e( 'Procedure', 'brag-book-gallery' ); ?>"><?php esc_html_e( 'Procedure', 'brag-book-gallery' ); ?></span>
			</summary>
			<div class="brag-book-gallery-filter-dropdown__panel">
				<div class="brag-book-gallery-provider-filter__search">
					<svg class="brag-book-gallery-provider-filter__search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
					<input
						type="search"
						class="brag-book-gallery-provider-filter__search-input"
						placeholder="<?php esc_attr_e( 'Search procedures…', 'brag-book-gallery' ); ?>"
						aria-label="<?php esc_attr_e( 'Search procedures', 'brag-book-gallery' ); ?>"
						aria-controls="procedure-filter-list"
						autocomplete="off"
					/>
				</div>
				<ul class="brag-book-gallery-provider-filter__list" id="procedure-filter-list" role="listbox" aria-label="<?php esc_attr_e( 'Filter by procedure', 'brag-book-gallery' ); ?>">
					<li>
						<button type="button" class="brag-book-gallery-provider-filter__option is-active" data-provider-slug="">
							<?php esc_html_e( 'All Procedures', 'brag-book-gallery' ); ?>
						</button>
					</li>
					<?php foreach ( $procedures as $procedure ) : ?>
						<li>
							<button type="button" class="brag-book-gallery-provider-filter__option" data-provider-slug="<?php echo esc_attr( $procedure['slug'] ); ?>" data-provider-name="<?php echo esc_attr( strtolower( $procedure['name'] ) ); ?>">
								<span class="brag-book-gallery-provider-filter__name"><?php echo esc_html( $procedure['name'] ); ?></span>
								<span class="brag-book-gallery-provider-filter__count"><?php echo esc_html( (string) $procedure['count'] ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
					<li class="brag-book-gallery-provider-filter__no-match" hidden>
						<?php esc_html_e( 'No procedures match your search.', 'brag-book-gallery' ); ?>
					</li>
				</ul>
				<div class="brag-book-gallery-provider-filter__actions">
					<button type="button" class="brag-book-gallery-button brag-book-gallery-button--clear" data-provider-reset>
						<?php esc_html_e( 'Reset', 'brag-book-gallery' ); ?>
					</button>
				</div>
			</div>
		</details>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX handler: return a provider's cases, optionally scoped to a procedure.
	 *
	 * @since 4.8.0
	 * @return void
	 */
	public static function ajax_filter(): void {
		if ( ! check_ajax_referer( 'brag_book_gallery_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'brag-book-gallery' ) ], 403 );
		}

		if ( ! self::is_enabled() ) {
			wp_send_json_error( [ 'message' => __( 'Provider filtering is not enabled.', 'brag-book-gallery' ) ], 400 );
		}

		$provider_slug  = isset( $_POST['provider'] ) ? sanitize_title( wp_unslash( $_POST['provider'] ) ) : '';
		$procedure_slug = isset( $_POST['procedure'] ) ? sanitize_title( wp_unslash( $_POST['procedure'] ) ) : '';

		if ( '' === $provider_slug ) {
			wp_send_json_error( [ 'message' => __( 'A provider is required.', 'brag-book-gallery' ) ], 400 );
		}

		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = Settings_Helper::get_items_per_page();

		// Delegate to the shared, context-aware pager so provider results paginate
		// and order identically to the "load more" button that continues them.
		$result = Cases_Handler::render_context_page(
			[
				'provider_slug'  => $provider_slug,
				'procedure_slug' => $procedure_slug,
			],
			$page,
			$per_page
		);

		wp_send_json_success( [
			'html'    => $result['html'],
			'count'   => $result['total'],
			'total'   => $result['total'],
			'hasMore' => $result['has_more'],
			'page'    => $page,
		] );
	}

	/**
	 * Providers that have cases in the current context, ordered for display.
	 *
	 * @since 4.8.0
	 * @param string $procedure_slug Optional procedures taxonomy slug to scope to.
	 * @return array<int,array{slug:string,name:string,member_id:int,photo_url:string}>
	 */
	private static function get_providers( string $procedure_slug ): array {
		$case_ids = self::get_candidate_case_ids( '', $procedure_slug );
		if ( empty( $case_ids ) ) {
			return [];
		}

		$terms = wp_get_object_terms( $case_ids, Taxonomies::TAXONOMY_PROVIDERS );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$providers = [];
		foreach ( $terms as $term ) {
			$providers[] = [
				'slug'      => $term->slug,
				'name'      => $term->name,
				'member_id' => Taxonomies::provider_member_id( $term->term_id ),
				'photo_url' => self::provider_photo_url( $term->term_id ),
			];
		}

		// Alphanumeric order by name, so a long provider list is easy to scan/search.
		usort(
			$providers,
			static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] )
		);

		return $providers;
	}

	/**
	 * Procedures a provider has cases in, ordered for display.
	 *
	 * Cases carry both the procedure and its parent category, so the parents are
	 * dropped: the filter offers procedures, not the groups they sit in. Terms
	 * that have no parent are kept when nothing else is left, which is the case
	 * for a flat procedure list.
	 *
	 * @since 4.9.3
	 * @param string $provider_slug Providers taxonomy slug.
	 * @return array<int,array{slug:string,name:string}>
	 */
	private static function get_procedures( string $provider_slug ): array {
		$case_ids = self::get_candidate_case_ids( $provider_slug, '' );
		if ( empty( $case_ids ) ) {
			return [];
		}

		// One row per case/term pairing, so each term can be counted against this
		// provider's cases rather than the whole library, which is what the
		// term's own count holds.
		$rows = wp_get_object_terms(
			$case_ids,
			Taxonomies::TAXONOMY_PROCEDURES,
			[ 'fields' => 'all_with_object_id' ]
		);

		if ( is_wp_error( $rows ) || empty( $rows ) ) {
			return [];
		}

		$terms  = [];
		$counts = [];
		foreach ( $rows as $row ) {
			$terms[ $row->term_id ]  = $row;
			$counts[ $row->term_id ] = ( $counts[ $row->term_id ] ?? 0 ) + 1;
		}

		$children = array_filter(
			$terms,
			static fn( \WP_Term $term ): bool => $term->parent > 0
		);

		$procedures = [];
		foreach ( ( ! empty( $children ) ? $children : $terms ) as $term ) {
			$procedures[] = [
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => (int) ( $counts[ $term->term_id ] ?? 0 ),
			];
		}

		usort(
			$procedures,
			static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] )
		);

		return $procedures;
	}

	/**
	 * Resolve a provider's avatar URL: synced/local image, then attachment.
	 *
	 * @since 4.8.0
	 * @param int $term_id Provider term ID.
	 * @return string Image URL, or empty string when none is available.
	 */
	private static function provider_photo_url( int $term_id ): string {
		$image_url = (string) get_term_meta( $term_id, 'provider_image_url', true );
		if ( '' !== $image_url ) {
			return $image_url;
		}

		$attachment_id = (int) get_term_meta( $term_id, 'provider_profile_photo', true );
		if ( $attachment_id > 0 ) {
			return (string) ( wp_get_attachment_image_url( $attachment_id, [ 48, 48 ] ) ?: '' );
		}

		return '';
	}

	/**
	 * Markup for a provider option's avatar (image or placeholder).
	 *
	 * @since 4.8.0
	 * @param array{photo_url:string} $provider Provider display data.
	 * @return string Escaped avatar HTML.
	 */
	private static function avatar_markup( array $provider ): string {
		if ( '' !== $provider['photo_url'] ) {
			return sprintf(
				'<img class="brag-book-gallery-provider-filter__avatar" src="%s" alt="" width="28" height="28" loading="lazy" decoding="async" />',
				esc_url( $provider['photo_url'] )
			);
		}

		return '<span class="brag-book-gallery-provider-filter__avatar brag-book-gallery-provider-filter__avatar--placeholder" aria-hidden="true">'
			. '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
			. '</span>';
	}

	/**
	 * Resolve case IDs scoped to a provider and/or procedure (AND).
	 *
	 * @since 4.8.0
	 * @param string $provider_slug  Optional providers taxonomy slug.
	 * @param string $procedure_slug Optional procedures taxonomy slug.
	 * @return int[] Published case post IDs.
	 */
	private static function get_candidate_case_ids( string $provider_slug, string $procedure_slug ): array {
		$tax_query = [];

		if ( '' !== $provider_slug ) {
			$tax_query[] = [
				'taxonomy' => Taxonomies::TAXONOMY_PROVIDERS,
				'field'    => 'slug',
				'terms'    => $provider_slug,
			];
		}

		if ( '' !== $procedure_slug ) {
			$tax_query[] = [
				'taxonomy' => Taxonomies::TAXONOMY_PROCEDURES,
				'field'    => 'slug',
				'terms'    => $procedure_slug,
			];
		}

		$args = [
			'post_type'              => Post_Types::POST_TYPE_CASES,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		];

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * Render the matched case cards using the shared, design-aware renderer.
	 *
	 * @since 4.8.0
	 * @param int[]  $case_ids       Case post IDs.
	 * @param string $procedure_slug Procedure context the filter is scoped to, if any.
	 * @return string Concatenated case card HTML.
	 */
	private static function render_matched_cases( array $case_ids, string $procedure_slug ): string {
		$image_display_mode = (string) get_option( 'brag_book_gallery_image_display_mode', 'single' );

		$procedure_name    = '';
		$procedure_term_id = '';
		if ( '' !== $procedure_slug ) {
			$term = get_term_by( 'slug', $procedure_slug, Taxonomies::TAXONOMY_PROCEDURES );
			if ( $term instanceof \WP_Term ) {
				$procedure_name    = $term->name;
				$procedure_term_id = (string) $term->term_id;
			}
		}

		$html = '';
		foreach ( $case_ids as $case_id ) {
			$post = get_post( $case_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			// Pass the procedure term id so the AJAX-rendered card carries the
			// procedure context (is_tax() is false here), letting next/previous
			// navigation stay scoped to this provider on the case page.
			$html .= Cases_Handler::render_wordpress_case_card(
				Cases_Handler::build_case_data_from_post( $post ),
				$image_display_mode,
				HTML_Renderer::case_has_nudity( $case_id ),
				$procedure_name,
				'',
				$procedure_term_id
			);
		}

		return $html;
	}
}
