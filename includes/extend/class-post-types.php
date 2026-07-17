<?php
/**
 * Post Types Class
 *
 * Handles registration of custom post types for the BRAG book Gallery plugin.
 * This class manages the Cases post type to support local content management
 * alongside API-driven galleries.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Extend
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Extend;

use BRAGBookGallery\Includes\Core\Setup;
use WP_Post;
use WP_Query;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Post Types Class
 *
 * Manages custom post types for the BRAG book Gallery plugin.
 * This class provides local content management capabilities to complement
 * the API-driven gallery system.
 *
 * Features:
 * - Cases custom post type for local case management
 * - Proper WordPress integration with admin UI
 * - Support for featured images and custom fields
 * - SEO-friendly permalinks and archives
 *
 * @since 3.0.0
 */
class Post_Types {

	/**
	 * Post type identifier for cases
	 *
	 * @since 3.3.2-beta10
	 * @var string
	 */
	public const POST_TYPE_CASES = 'brag_book_cases';

	/**
	 * Post type identifier for practices
	 *
	 * Practices are clinic/location entities associated with providers, synced
	 * from the /api/plugin/v2/practices endpoint. Internal data feed only
	 * (not publicly queryable).
	 *
	 * @since 4.6.0
	 * @var string
	 */
	public const POST_TYPE_PRACTICES = 'brag_book_practices';

	/**
	 * Constructor - Register hooks
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		add_action(
			'init',
			array( $this, 'register_post_types' )
		);

		add_filter(
			'query_vars',
			array( $this, 'add_simple_query_vars' )
		);

		add_action(
			'add_meta_boxes',
			array( $this, 'add_case_meta_boxes' )
		);

		add_action(
			'add_meta_boxes',
			array( $this, 'add_practice_meta_boxes' )
		);

		add_action(
			'save_post',
			array( $this, 'save_case_meta' )
		);

		add_action(
			'save_post',
			array( $this, 'save_practice_meta' )
		);

		add_filter(
			'manage_' . self::POST_TYPE_CASES . '_posts_columns',
			array( $this, 'add_case_provider_column' )
		);

		add_action(
			'manage_' . self::POST_TYPE_CASES . '_posts_custom_column',
			array( $this, 'render_case_provider_column' ),
			10,
			2
		);

		add_action(
			'restrict_manage_posts',
			array( $this, 'render_case_provider_filter' )
		);

		add_action(
			'pre_get_posts',
			array( $this, 'filter_cases_by_provider' )
		);

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_case_meta_assets' )
		);

		add_filter(
			'post_type_link',
			array( $this, 'custom_brag_book_cases_permalink' ),
			10,
			2
		);

		add_filter(
			'wp_prepare_attachment_for_js',
			array( $this, 'add_brag_book_marker_to_attachment' ),
			10,
			3
		);
	}

	/**
	 * Register custom post types
	 *
	 * Registers the Cases post type for managing before/after case galleries.
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc and sanitization
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		// Cases post type
		$cases_labels = array(
			'name'                  => _x( 'Cases', 'Post type general name', 'brag-book-gallery' ),
			'singular_name'         => _x( 'Case', 'Post type singular name', 'brag-book-gallery' ),
			'menu_name'             => _x( 'Cases', 'Admin Menu text', 'brag-book-gallery' ),
			'name_admin_bar'        => _x( 'Case', 'Add New on Toolbar', 'brag-book-gallery' ),
			'add_new'               => __( 'Add New', 'brag-book-gallery' ),
			'add_new_item'          => __( 'Add New Case', 'brag-book-gallery' ),
			'new_item'              => __( 'New Case', 'brag-book-gallery' ),
			'edit_item'             => __( 'Edit Case', 'brag-book-gallery' ),
			'view_item'             => __( 'View Case', 'brag-book-gallery' ),
			'all_items'             => __( 'All Cases', 'brag-book-gallery' ),
			'search_items'          => __( 'Search Cases', 'brag-book-gallery' ),
			'parent_item_colon'     => __( 'Parent Cases:', 'brag-book-gallery' ),
			'not_found'             => __( 'No cases found.', 'brag-book-gallery' ),
			'not_found_in_trash'    => __( 'No cases found in Trash.', 'brag-book-gallery' ),
			'featured_image'        => _x( 'Before Image', 'Overrides the "Featured Image" phrase', 'brag-book-gallery' ),
			'set_featured_image'    => _x( 'Set before image', 'Overrides the "Set featured image" phrase', 'brag-book-gallery' ),
			'remove_featured_image' => _x( 'Remove before image', 'Overrides the "Remove featured image" phrase', 'brag-book-gallery' ),
			'use_featured_image'    => _x( 'Use as before image', 'Overrides the "Use as featured image" phrase', 'brag-book-gallery' ),
			'archives'              => _x( 'Case archives', 'The post type archive label', 'brag-book-gallery' ),
			'insert_into_item'      => _x( 'Insert into case', 'Overrides the "Insert into post" phrase', 'brag-book-gallery' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this case', 'Overrides the "Uploaded to this post" phrase', 'brag-book-gallery' ),
			'filter_items_list'     => _x( 'Filter cases list', 'Screen reader text for the filter links', 'brag-book-gallery' ),
			'items_list_navigation' => _x( 'Cases list navigation', 'Screen reader text for the pagination', 'brag-book-gallery' ),
			'items_list'            => _x( 'Cases list', 'Screen reader text for the items list', 'brag-book-gallery' ),
		);

		$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		// Handle legacy array format
		$gallery_slug = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;

		$cases_args = array(
			'labels'             => $cases_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_admin_bar'  => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $gallery_slug . '/%brag_book_procedures%',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			// Disable the post type archive. The gallery is served by a real Page
			// + the brag_book_procedures taxonomy archive + shortcode URL parsing,
			// so the CPT archive is never rendered. Leaving it enabled made WordPress
			// expose a pageable archive at the unresolved `%brag_book_procedures%`
			// rewrite slug, which Yoast crawled as /page/N URLs that all 404.
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-camera-alt',
			'supports'           => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields',
			),
			'show_in_rest'       => true,
			'taxonomies'         => array( 'brag_book_procedures' ),
		);

		register_post_type( self::POST_TYPE_CASES, $cases_args );

		$this->register_practices_post_type();
	}

	/**
	 * Register the Practices post type
	 *
	 * Practices are clinic/location entities associated with providers and synced
	 * from the API. They are an internal data feed: editable in the admin but not
	 * publicly queryable and with no front-end archive or single URLs.
	 *
	 * @since 4.6.0
	 * @return void
	 */
	private function register_practices_post_type(): void {
		$practices_labels = array(
			'name'               => _x( 'Practices', 'Post type general name', 'brag-book-gallery' ),
			'singular_name'      => _x( 'Practice', 'Post type singular name', 'brag-book-gallery' ),
			'menu_name'          => __( 'Practices', 'brag-book-gallery' ),
			'name_admin_bar'     => _x( 'Practice', 'Add New on Toolbar', 'brag-book-gallery' ),
			'add_new_item'       => __( 'Add New Practice', 'brag-book-gallery' ),
			'new_item'           => __( 'New Practice', 'brag-book-gallery' ),
			'edit_item'          => __( 'Edit Practice', 'brag-book-gallery' ),
			'view_item'          => __( 'View Practice', 'brag-book-gallery' ),
			'all_items'          => __( 'All Practices', 'brag-book-gallery' ),
			'search_items'       => __( 'Search Practices', 'brag-book-gallery' ),
			'not_found'          => __( 'No practices found.', 'brag-book-gallery' ),
			'not_found_in_trash' => __( 'No practices found in Trash.', 'brag-book-gallery' ),
		);

		$practices_args = array(
			'labels'             => $practices_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_admin_bar'  => false,
			'show_in_nav_menus'  => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-location',
			'supports'           => array( 'title' ),
			'show_in_rest'       => false,
		);

		register_post_type( self::POST_TYPE_PRACTICES, $practices_args );
	}

	/**
	 * Map a practice object from the API onto practice post meta
	 *
	 * Stores the structured practice fields (address, geo, contact,
	 * accreditations) and the practice's associated provider API IDs.
	 *
	 * @since 4.6.0
	 *
	 * @param int   $post_id  The practice post ID.
	 * @param array $practice A single practice object from /api/plugin/v2/practices.
	 *
	 * @return bool True on success, false if the post is not a practice.
	 */
	public static function save_practice_api_data( int $post_id, array $practice ): bool {
		if ( get_post_type( $post_id ) !== self::POST_TYPE_PRACTICES ) {
			return false;
		}

		// API id (dedupe key), primary flag, and position.
		if ( isset( $practice['id'] ) ) {
			update_post_meta( $post_id, 'brag_book_gallery_practice_api_id', absint( $practice['id'] ) );
		}
		update_post_meta( $post_id, 'brag_book_gallery_practice_is_primary', empty( $practice['isPrimary'] ) ? '0' : '1' );
		update_post_meta( $post_id, 'brag_book_gallery_practice_position', absint( $practice['position'] ?? 0 ) );

		// Contact details.
		if ( isset( $practice['phone'] ) ) {
			update_post_meta( $post_id, 'brag_book_gallery_practice_phone', sanitize_text_field( (string) $practice['phone'] ) );
		}
		if ( ! empty( $practice['websiteUrl'] ) ) {
			update_post_meta( $post_id, 'brag_book_gallery_practice_website_url', esc_url_raw( (string) $practice['websiteUrl'] ) );
		}
		update_post_meta( $post_id, 'brag_book_gallery_practice_has_surgical_suite', empty( $practice['hasOnSiteSurgicalSuite'] ) ? '0' : '1' );

		// Address.
		if ( isset( $practice['address'] ) && is_array( $practice['address'] ) ) {
			$address_map = array(
				'street1'   => 'brag_book_gallery_practice_street1',
				'street2'   => 'brag_book_gallery_practice_street2',
				'city'      => 'brag_book_gallery_practice_city',
				'state'     => 'brag_book_gallery_practice_state',
				'zip'       => 'brag_book_gallery_practice_zip',
				'formatted' => 'brag_book_gallery_practice_address',
			);
			foreach ( $address_map as $field => $meta_key ) {
				if ( isset( $practice['address'][ $field ] ) ) {
					update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $practice['address'][ $field ] ) );
				}
			}
		}

		// Geo coordinates.
		if ( isset( $practice['geo'] ) && is_array( $practice['geo'] ) ) {
			if ( isset( $practice['geo']['latitude'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_practice_latitude', (float) $practice['geo']['latitude'] );
			}
			if ( isset( $practice['geo']['longitude'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_practice_longitude', (float) $practice['geo']['longitude'] );
			}
			if ( isset( $practice['geo']['placeId'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_practice_place_id', sanitize_text_field( (string) $practice['geo']['placeId'] ) );
			}
		}

		// Accreditations (array of short codes).
		if ( isset( $practice['accreditations'] ) && is_array( $practice['accreditations'] ) ) {
			$accreditations = array_values(
				array_filter(
					array_map(
						static fn( $a ) => sanitize_text_field( (string) $a ),
						$practice['accreditations']
					)
				)
			);
			update_post_meta( $post_id, 'brag_book_gallery_practice_accreditations', $accreditations );
		}

		// Associated provider API IDs (from the practice's own providers list).
		if ( isset( $practice['providers'] ) && is_array( $practice['providers'] ) ) {
			$provider_ids = array();
			foreach ( $practice['providers'] as $provider ) {
				if ( is_array( $provider ) && isset( $provider['id'] ) ) {
					$provider_ids[] = absint( $provider['id'] );
				}
			}
			update_post_meta( $post_id, 'brag_book_gallery_practice_provider_ids', implode( ',', $provider_ids ) );
		}

		return true;
	}

	/**
	 * Register the read-only details meta box for practices
	 *
	 * @since 4.6.0
	 * @return void
	 */
	public function add_practice_meta_boxes(): void {
		add_meta_box(
			'brag_book_gallery_practice_details',
			__( 'Practice Details', 'brag-book-gallery' ),
			array( $this, 'render_practice_details_meta_box' ),
			self::POST_TYPE_PRACTICES,
			'normal',
			'high'
		);
	}

	/**
	 * Text post-meta fields rendered on the practice editor
	 *
	 * Maps the editable meta key to its translated label. Sync populates these
	 * from the API; admins can adjust them between syncs.
	 *
	 * @since 4.6.0
	 * @return array<string, string>
	 */
	private static function practice_text_fields(): array {
		return array(
			'brag_book_gallery_practice_street1'     => __( 'Street 1', 'brag-book-gallery' ),
			'brag_book_gallery_practice_street2'     => __( 'Street 2', 'brag-book-gallery' ),
			'brag_book_gallery_practice_city'        => __( 'City', 'brag-book-gallery' ),
			'brag_book_gallery_practice_state'       => __( 'State', 'brag-book-gallery' ),
			'brag_book_gallery_practice_zip'         => __( 'ZIP', 'brag-book-gallery' ),
			'brag_book_gallery_practice_address'     => __( 'Formatted address', 'brag-book-gallery' ),
			'brag_book_gallery_practice_phone'       => __( 'Phone', 'brag-book-gallery' ),
			'brag_book_gallery_practice_website_url' => __( 'Website URL', 'brag-book-gallery' ),
			'brag_book_gallery_practice_latitude'    => __( 'Latitude', 'brag-book-gallery' ),
			'brag_book_gallery_practice_longitude'   => __( 'Longitude', 'brag-book-gallery' ),
			'brag_book_gallery_practice_place_id'    => __( 'Google Place ID', 'brag-book-gallery' ),
		);
	}

	/**
	 * Render the editable practice details meta box
	 *
	 * Practice details are stored as post meta. Sync populates them from the API;
	 * the fields remain editable in the admin between syncs.
	 *
	 * @since 4.6.0
	 * @param \WP_Post $post The practice post.
	 * @return void
	 */
	public function render_practice_details_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'save_practice_meta', 'practice_meta_nonce' );

		$api_id         = get_post_meta( $post->ID, 'brag_book_gallery_practice_api_id', true );
		$is_primary     = get_post_meta( $post->ID, 'brag_book_gallery_practice_is_primary', true );
		$position       = get_post_meta( $post->ID, 'brag_book_gallery_practice_position', true );
		$surgical_suite = get_post_meta( $post->ID, 'brag_book_gallery_practice_has_surgical_suite', true );
		$accreditations = get_post_meta( $post->ID, 'brag_book_gallery_practice_accreditations', true );
		$accreditations = is_array( $accreditations ) ? implode( ', ', $accreditations ) : '';
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Practice ID (API)', 'brag-book-gallery' ); ?></th>
					<td>
						<code><?php echo esc_html( '' === (string) $api_id ? '—' : (string) $api_id ); ?></code>
						<p class="description"><?php esc_html_e( 'Read-only identifier from the API.', 'brag-book-gallery' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="brag_book_gallery_practice_is_primary"><?php esc_html_e( 'Primary', 'brag-book-gallery' ); ?></label></th>
					<td>
						<input type="hidden" name="brag_book_gallery_practice_is_primary" value="0" />
						<input type="checkbox" id="brag_book_gallery_practice_is_primary" name="brag_book_gallery_practice_is_primary" value="1" <?php checked( $is_primary, '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="brag_book_gallery_practice_position"><?php esc_html_e( 'Position', 'brag-book-gallery' ); ?></label></th>
					<td><input type="number" id="brag_book_gallery_practice_position" name="brag_book_gallery_practice_position" value="<?php echo esc_attr( $position ); ?>" class="small-text" /></td>
				</tr>
				<?php foreach ( self::practice_text_fields() as $meta_key => $label ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="text" id="<?php echo esc_attr( $meta_key ); ?>" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, $meta_key, true ) ); ?>" class="regular-text" /></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="brag_book_gallery_practice_has_surgical_suite"><?php esc_html_e( 'On-site surgical suite', 'brag-book-gallery' ); ?></label></th>
					<td>
						<input type="hidden" name="brag_book_gallery_practice_has_surgical_suite" value="0" />
						<input type="checkbox" id="brag_book_gallery_practice_has_surgical_suite" name="brag_book_gallery_practice_has_surgical_suite" value="1" <?php checked( $surgical_suite, '1' ); ?> />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="brag_book_gallery_practice_accreditations"><?php esc_html_e( 'Accreditations', 'brag-book-gallery' ); ?></label></th>
					<td>
						<input type="text" id="brag_book_gallery_practice_accreditations" name="brag_book_gallery_practice_accreditations" value="<?php echo esc_attr( $accreditations ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Comma-separated list.', 'brag-book-gallery' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Linked providers are managed in the Providers box and are kept in sync from the API.', 'brag-book-gallery' ); ?></p>
		<?php
	}

	/**
	 * Save the editable practice meta fields
	 *
	 * @since 4.6.0
	 * @param int $post_id The practice post ID.
	 * @return void
	 */
	public function save_practice_meta( int $post_id ): void {
		if ( ! isset( $_POST['practice_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['practice_meta_nonce'] ) ), 'save_practice_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( get_post_type( $post_id ) !== self::POST_TYPE_PRACTICES || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Text fields.
		foreach ( array_keys( self::practice_text_fields() ) as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) );
			}
		}

		// Position (integer).
		if ( isset( $_POST['brag_book_gallery_practice_position'] ) ) {
			update_post_meta( $post_id, 'brag_book_gallery_practice_position', absint( wp_unslash( $_POST['brag_book_gallery_practice_position'] ) ) );
		}

		// Checkboxes (hidden input guarantees a value when unchecked).
		update_post_meta( $post_id, 'brag_book_gallery_practice_is_primary', empty( $_POST['brag_book_gallery_practice_is_primary'] ) ? '0' : '1' );
		update_post_meta( $post_id, 'brag_book_gallery_practice_has_surgical_suite', empty( $_POST['brag_book_gallery_practice_has_surgical_suite'] ) ? '0' : '1' );

		// Accreditations (comma-separated to array).
		if ( isset( $_POST['brag_book_gallery_practice_accreditations'] ) ) {
			$raw            = sanitize_text_field( wp_unslash( $_POST['brag_book_gallery_practice_accreditations'] ) );
			$accreditations = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
			update_post_meta( $post_id, 'brag_book_gallery_practice_accreditations', $accreditations );
		}
	}

	/**
	 * Replace the auto provider taxonomy column on the Cases list table
	 *
	 * WordPress renders the auto taxonomy column as plain term links and does not
	 * allow filtering its content, so we swap in our own column (in the same
	 * position) that renders a mini avatar per provider.
	 *
	 * @since 4.6.0
	 * @param array $columns Existing list table columns.
	 * @return array Modified columns.
	 */
	public function add_case_provider_column( array $columns ): array {
		$auto_column = 'taxonomy-' . Taxonomies::TAXONOMY_PROVIDERS;
		$rebuilt     = array();

		foreach ( $columns as $key => $label ) {
			if ( $auto_column === $key ) {
				$rebuilt['brag_book_providers_avatars'] = __( 'Providers', 'brag-book-gallery' );
				continue;
			}
			$rebuilt[ $key ] = $label;
		}

		// If the auto column was not present, append ours before the date column.
		if ( ! isset( $rebuilt['brag_book_providers_avatars'] ) ) {
			$rebuilt['brag_book_providers_avatars'] = __( 'Providers', 'brag-book-gallery' );
		}

		return $rebuilt;
	}

	/**
	 * Render the providers column with a mini avatar per provider
	 *
	 * @since 4.6.0
	 * @param string $column  The column key being rendered.
	 * @param int    $post_id The case post ID.
	 * @return void
	 */
	public function render_case_provider_column( string $column, int $post_id ): void {
		if ( 'brag_book_providers_avatars' !== $column ) {
			return;
		}

		$terms = wp_get_post_terms( $post_id, Taxonomies::TAXONOMY_PROVIDERS );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '&mdash;';
			return;
		}

		echo '<div class="brag-book-gallery-admin-providers">';
		foreach ( $terms as $term ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup escaped within helper.
			echo $this->get_provider_avatar_html( $term->term_id, $term->name );
		}
		echo '</div>';
	}

	/**
	 * Build the mini-avatar + name markup for a single provider term
	 *
	 * Resolves the avatar with the API image winning: the synced
	 * `provider_image_url`, then a manually-uploaded attachment, then a
	 * placeholder icon.
	 *
	 * @since 4.6.0
	 * @param int    $term_id The provider term ID.
	 * @param string $name    The provider display name.
	 * @return string Escaped HTML for one provider row.
	 */
	private function get_provider_avatar_html( int $term_id, string $name ): string {
		$image_url     = get_term_meta( $term_id, 'provider_image_url', true );
		$profile_photo = get_term_meta( $term_id, 'provider_profile_photo', true );

		$src = '';
		if ( ! empty( $image_url ) ) {
			$src = (string) $image_url;
		} elseif ( ! empty( $profile_photo ) ) {
			$src = (string) ( wp_get_attachment_image_url( (int) $profile_photo, array( 48, 48 ) ) ?: '' );
		}

		if ( '' !== $src ) {
			$avatar = sprintf(
				'<img src="%s" alt="" width="24" height="24" style="width:24px;height:24px;border-radius:50%%;object-fit:cover;flex:0 0 auto;" />',
				esc_url( $src )
			);
		} else {
			$avatar = '<span class="dashicons dashicons-businessperson" style="width:24px;height:24px;font-size:24px;line-height:24px;color:#a7aaad;flex:0 0 auto;"></span>';
		}

		return sprintf(
			'<div style="display:flex;align-items:center;gap:6px;margin:2px 0;">%s<span>%s</span></div>',
			$avatar,
			esc_html( $name )
		);
	}

	/**
	 * Render a "Providers" dropdown filter above the Cases list table
	 *
	 * Hooked on restrict_manage_posts. Only rendered on the Cases list screen.
	 *
	 * @since 4.9.0
	 * @param string $post_type The list table's post type.
	 * @return void
	 */
	public function render_case_provider_filter( string $post_type ): void {
		if ( self::POST_TYPE_CASES !== $post_type || ! taxonomy_exists( Taxonomies::TAXONOMY_PROVIDERS ) ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::TAXONOMY_PROVIDERS,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter, no state change.
		$selected = isset( $_GET['bb_provider'] ) ? absint( wp_unslash( $_GET['bb_provider'] ) ) : 0;

		echo '<label class="screen-reader-text" for="bb_provider">' . esc_html__( 'Filter by provider', 'brag-book-gallery' ) . '</label>';
		echo '<select name="bb_provider" id="bb_provider">';
		echo '<option value="0">' . esc_html__( 'All providers', 'brag-book-gallery' ) . '</option>';
		foreach ( $terms as $term ) {
			printf(
				'<option value="%d"%s>%s (%d)</option>',
				(int) $term->term_id,
				selected( $selected, (int) $term->term_id, false ),
				esc_html( $term->name ),
				(int) $term->count
			);
		}
		echo '</select>';
	}

	/**
	 * Filter the Cases list table by the selected provider term
	 *
	 * Hooked on pre_get_posts. Applies a tax_query for the chosen provider on the
	 * Cases list screen's main query only.
	 *
	 * @since 4.9.0
	 * @param WP_Query $query The current query.
	 * @return void
	 */
	public function filter_cases_by_provider( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== $pagenow || self::POST_TYPE_CASES !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter, no state change.
		$provider_id = isset( $_GET['bb_provider'] ) ? absint( wp_unslash( $_GET['bb_provider'] ) ) : 0;
		if ( $provider_id <= 0 ) {
			return;
		}

		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => Taxonomies::TAXONOMY_PROVIDERS,
			'field'    => 'term_id',
			'terms'    => $provider_id,
		);
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Customize permalink structure for cases
	 *
	 * Replaces the %brag_book_procedures% placeholder with the actual procedure slug.
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc and escaping
	 *
	 * @param string   $post_link The post's permalink.
	 * @param WP_Post $post The post object.
	 *
	 * @return string Modified permalink
	 */
	public function custom_brag_book_cases_permalink( string $post_link, WP_Post $post ): string {
		if ( $post->post_type === 'brag_book_cases' ) {
			$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
			// Handle legacy array format
			$gallery_slug = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;
			$terms        = wp_get_object_terms( $post->ID, 'brag_book_procedures' );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$procedure_slug = $terms[0]->slug;
			} else {
				$procedure_slug = 'uncategorized';
			}

			$post_link = home_url( '/' . $gallery_slug . '/' . $procedure_slug . '/' . $post->post_name . '/' );
		}

		return $post_link;
	}

	/**
	 * Add meta boxes for case editing
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc and validation
	 *
	 * @return void
	 */
	public function add_case_meta_boxes(): void {
		// Always add meta boxes - we're using classic meta boxes for all editors
		// Ensure we're on the correct post type
		global $post, $current_screen;

		if ( ! $current_screen || $current_screen->post_type !== self::POST_TYPE_CASES ) {
			return;
		}

		add_meta_box(
			'case_api_data',
			__( 'API Case Data', 'brag-book-gallery' ),
			array( $this, 'render_case_api_data_meta_box' ),
			self::POST_TYPE_CASES,
			'normal',
			'default'
		);
	}

	/**
	 * Case image nodes: variant meta key => human label.
	 *
	 * Order matches the API photoSet structure (before, after, afterPlus, then the
	 * two side-by-side renders).
	 *
	 * @since 3.3.3
	 * @var array<string,string>
	 */
	private const CASE_IMAGE_NODES = array(
		'before'         => 'Before',
		'after'          => 'After',
		'after_plus'     => 'After Plus',
		'post_processed' => 'Side-by-Side (Standard)',
		'high_res'       => 'Side-by-Side (High-Res)',
	);

	/**
	 * Case image nodes: variant node key => legacy flat meta key.
	 *
	 * The flat keys are the full-size compatibility layer read by the frontend
	 * renderers; they are re-derived from the variant sets whenever a case is
	 * saved from the editor.
	 *
	 * @since 3.3.3
	 * @var array<string,string>
	 */
	private const CASE_IMAGE_FLAT_KEYS = array(
		'before'         => 'brag_book_gallery_case_before_url',
		'after'          => 'brag_book_gallery_case_after_url',
		'after_plus'     => 'brag_book_gallery_case_after_plus_url',
		'post_processed' => 'brag_book_gallery_case_post_processed_url',
		'high_res'       => 'brag_book_gallery_case_high_res_url',
	);

	/**
	 * Build the editable variant sets for the meta box.
	 *
	 * Prefers the structured `brag_book_gallery_case_image_variants` meta. For
	 * cases synced before variants existed, reconstructs equivalent sets from the
	 * legacy flat URL keys so the editor is always populated. Those keys only ever
	 * held full-size URLs, so medium and small are shown empty — the renditions
	 * genuinely do not exist until the case is re-synced.
	 *
	 * @since 3.3.3
	 * @param int $post_id Case post ID.
	 * @return array<int,array<string,array<string,string>>>
	 */
	private static function get_editable_image_variants( int $post_id ): array {
		$sets = get_post_meta( $post_id, 'brag_book_gallery_case_image_variants', true );
		if ( is_array( $sets ) && ! empty( $sets ) ) {
			return $sets;
		}

		$split = static function ( $raw ): array {
			if ( ! is_string( $raw ) || '' === $raw ) {
				return array();
			}
			$parts = preg_split( '/[\r\n;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
			return is_array( $parts ) ? array_values( array_filter( array_map( 'trim', $parts ) ) ) : array();
		};

		$flat = array();
		$max  = 0;
		foreach ( self::CASE_IMAGE_FLAT_KEYS as $node => $key ) {
			$flat[ $node ] = $split( get_post_meta( $post_id, $key, true ) );
			$max           = max( $max, count( $flat[ $node ] ) );
		}

		$reconstructed = array();
		for ( $i = 0; $i < $max; $i++ ) {
			$set = array();
			foreach ( self::CASE_IMAGE_FLAT_KEYS as $node => $key ) {
				$url          = $flat[ $node ][ $i ] ?? '';
				$set[ $node ] = array( 'full' => $url, 'medium' => '', 'small' => '', 'alt' => '' );
			}
			$reconstructed[] = $set;
		}

		return $reconstructed;
	}

	/**
	 * Render the editable "Image URLs" panel (photoSet × node × size grid).
	 *
	 * @since 3.3.3
	 * @param int $post_id Case post ID.
	 * @return void
	 */
	private static function render_image_variants_panel( int $post_id ): void {
		$sets = self::get_editable_image_variants( $post_id );
		?>
		<div class="brag-book-gallery-image-variants">
			<p class="description">
				<?php esc_html_e( 'Each image has full, medium, and small variants. Leave medium or small blank to fall back to the full-size URL. Values sync from the API; edits here overwrite them until the next sync.', 'brag-book-gallery' ); ?>
			</p>
			<?php if ( empty( $sets ) ) : ?>
				<p><?php esc_html_e( 'No images stored for this case yet.', 'brag-book-gallery' ); ?></p>
			<?php else : ?>
				<?php foreach ( $sets as $set_index => $set ) : ?>
					<fieldset class="brag-book-gallery-image-variant-set">
						<legend>
							<?php
							/* translators: %d: photo set number. */
							printf( esc_html__( 'Photo Set %d', 'brag-book-gallery' ), (int) $set_index + 1 );
							?>
						</legend>
						<?php foreach ( self::CASE_IMAGE_NODES as $node => $label ) : ?>
							<?php
							$node_data = isset( $set[ $node ] ) && is_array( $set[ $node ] ) ? $set[ $node ] : array();
							$full      = (string) ( $node_data['full'] ?? '' );
							$medium    = (string) ( $node_data['medium'] ?? '' );
							$small     = (string) ( $node_data['small'] ?? '' );
							$base_name = sprintf( 'bbg_image_variants[%d][%s]', (int) $set_index, $node );
							?>
							<div class="brag-book-gallery-image-variant-node">
								<div class="brag-book-gallery-image-variant-node__preview">
									<?php if ( '' !== $full ) : ?>
										<img src="<?php echo esc_url( $full ); ?>" alt="" loading="lazy" decoding="async" />
									<?php else : ?>
										<span class="brag-book-gallery-image-variant-node__placeholder" aria-hidden="true">—</span>
									<?php endif; ?>
								</div>
								<div class="brag-book-gallery-image-variant-node__fields">
									<strong class="brag-book-gallery-image-variant-node__label"><?php echo esc_html( $label ); ?></strong>
									<?php
									foreach (
										array(
											'full'   => __( 'Full', 'brag-book-gallery' ),
											'medium' => __( 'Medium', 'brag-book-gallery' ),
											'small'  => __( 'Small', 'brag-book-gallery' ),
										) as $size => $size_label
									) :
										$value = ( 'full' === $size ) ? $full : ( ( 'medium' === $size ) ? $medium : $small );
										?>
										<label class="brag-book-gallery-image-variant-field">
											<span><?php echo esc_html( $size_label ); ?></span>
											<input
												type="url"
												class="large-text code"
												name="<?php echo esc_attr( $base_name . '[' . $size . ']' ); ?>"
												value="<?php echo esc_attr( $value ); ?>"
												placeholder="https://…"
											/>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the API case data meta box
	 *
	 * Displays comprehensive case information in tabbed interface including:
	 * - Basic information
	 * - Patient data
	 * - Procedure details
	 * - PostOp information
	 * - SEO metadata
	 * - Image URLs
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc, escaping, and sanitization
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function render_case_api_data_meta_box( WP_Post $post ): void {
		// Add nonce for security
		wp_nonce_field( 'save_case_api_data', 'case_api_data_nonce' );

		// Basic Info.
		$procedure_case_id    = get_post_meta( $post->ID, 'brag_book_gallery_procedure_case_id', true );
		$case_id              = get_post_meta( $post->ID, 'brag_book_gallery_case_id', true );
		$procedure_ids        = get_post_meta( $post->ID, 'brag_book_gallery_procedure_ids', true );
		$case_notes           = get_post_meta( $post->ID, 'brag_book_gallery_notes', true );
		$provider_name          = get_post_meta( $post->ID, 'brag_book_gallery_provider_name', true );
		$provider_profile_url   = get_post_meta( $post->ID, 'brag_book_gallery_provider_profile_url', true );
		$provider_suffix        = get_post_meta( $post->ID, 'brag_book_gallery_provider_suffix', true );
		$member_id            = get_post_meta( $post->ID, 'brag_book_gallery_member_id', true );

		// Status flags.
		$featured        = get_post_meta( $post->ID, 'brag_book_gallery_featured', true );
		$top_performing  = get_post_meta( $post->ID, 'brag_book_gallery_top_performing', true );

		// Patient Info.
		$age         = get_post_meta( $post->ID, 'brag_book_gallery_patient_age', true );
		$gender      = get_post_meta( $post->ID, 'brag_book_gallery_gender', true );
		$ethnicity   = get_post_meta( $post->ID, 'brag_book_gallery_ethnicity', true );
		$height      = get_post_meta( $post->ID, 'brag_book_gallery_height', true );
		$height_unit = get_post_meta( $post->ID, 'brag_book_gallery_height_unit', true );
		$weight      = get_post_meta( $post->ID, 'brag_book_gallery_weight', true );
		$weight_unit = get_post_meta( $post->ID, 'brag_book_gallery_weight_unit', true );

		// Post Op.
		$postop_technique         = get_post_meta( $post->ID, 'brag_book_gallery_postop_technique', true );
		$postop_revision_surgery  = get_post_meta( $post->ID, 'brag_book_gallery_postop_revision_surgery', true );
		$postop_after1_timeframe  = get_post_meta( $post->ID, 'brag_book_gallery_postop_after1_timeframe', true );
		$postop_after1_unit       = get_post_meta( $post->ID, 'brag_book_gallery_postop_after1_unit', true );

		// SEO.
		$seo_suffix_url       = get_post_meta( $post->ID, 'brag_book_gallery_seo_suffix_url', true );
		$seo_headline         = get_post_meta( $post->ID, 'brag_book_gallery_seo_headline', true );
		$seo_page_title       = get_post_meta( $post->ID, 'brag_book_gallery_seo_page_title', true );
		$seo_page_description = get_post_meta( $post->ID, 'brag_book_gallery_seo_page_description', true );
		$seo_alt_text         = get_post_meta( $post->ID, 'brag_book_gallery_seo_alt_text', true );

		?>
		<div class="brag-book-gallery-admin-wrap">
			<div class="brag-book-gallery-section">
				<div class="brag-book-api-data-tabs">
					<nav class="nav-tab-wrapper">
						<a href="#api-basic" class="nav-tab nav-tab-active">
							<?php esc_html_e( 'Basic Info', 'brag-book-gallery' ); ?>
						</a>
						<a href="#api-patient" class="nav-tab">
							<?php esc_html_e( 'Patient Info', 'brag-book-gallery' ); ?>
						</a>
						<a href="#api-procedure-details" class="nav-tab">
							<?php esc_html_e( 'Procedure Details', 'brag-book-gallery' ); ?>
						</a>
						<a href="#api-postop" class="nav-tab">
							<?php esc_html_e( 'PostOp', 'brag-book-gallery' ); ?>
						</a>
						<a href="#api-seo" class="nav-tab">
							<?php esc_html_e( 'SEO', 'brag-book-gallery' ); ?>
						</a>
						<a href="#api-images" class="nav-tab">
							<?php esc_html_e( 'Image URLs', 'brag-book-gallery' ); ?>
						</a>
					</nav>

					<div id="api-basic" class="tab-content active">
						<h4><?php esc_html_e( 'Basic Information', 'brag-book-gallery' ); ?></h4>
						<table class="form-table">
						<tr>
							<th scope="row">
								<label for="brag_book_gallery_featured"><?php esc_html_e( 'Featured', 'brag-book-gallery' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="brag_book_gallery_featured"
									   name="brag_book_gallery_featured"
									   value="1"
									   <?php checked( $featured, '1' ); ?>/>
								<label for="brag_book_gallery_featured"><?php esc_html_e( 'Mark this case as featured', 'brag-book-gallery' ); ?></label>
								<p class="description"><?php esc_html_e( 'Flags this case as featured.', 'brag-book-gallery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="brag_book_gallery_top_performing"><?php esc_html_e( 'Top Performing', 'brag-book-gallery' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="brag_book_gallery_top_performing"
									   name="brag_book_gallery_top_performing"
									   value="1"
									   <?php checked( $top_performing, '1' ); ?>/>
								<label for="brag_book_gallery_top_performing"><?php esc_html_e( 'Mark this case as top performing', 'brag-book-gallery' ); ?></label>
								<p class="description"><?php esc_html_e( 'Flags this case as top performing.', 'brag-book-gallery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label
									for="brag_book_gallery_procedure_case_id"><?php esc_html_e( 'Procedure Case ID', 'brag-book-gallery' ); ?></label>
							</th>
							<td>
								<input type="number" id="brag_book_gallery_procedure_case_id"
									   name="brag_book_gallery_procedure_case_id"
									   value="<?php echo esc_attr( $procedure_case_id ); ?>"
									   class="regular-text"/>
								<p class="description"><?php esc_html_e( 'The procedure-specific case ID (id field from API)', 'brag-book-gallery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label
									for="brag_book_gallery_case_id"><?php esc_html_e( 'Case ID', 'brag-book-gallery' ); ?></label>
							</th>
							<td>
								<input type="number" id="brag_book_gallery_case_id"
									   name="brag_book_gallery_case_id"
									   value="<?php echo esc_attr( $case_id ); ?>"
									   class="regular-text"/>
								<p class="description"><?php esc_html_e( 'The main case ID (caseId field from API)', 'brag-book-gallery' ); ?></p>
							</td>
						</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_procedure_ids"><?php esc_html_e( 'Procedure IDs', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_procedure_ids"
										   name="brag_book_gallery_procedure_ids"
										   value="<?php echo esc_attr( $procedure_ids ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'Comma-separated list of procedure IDs', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_notes"><?php esc_html_e( 'Case Notes', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<textarea id="brag_book_gallery_notes"
											  name="brag_book_gallery_notes"
											  rows="5"
											  class="large-text"><?php echo esc_textarea( $case_notes ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Additional notes and description about this case', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_provider_name"><?php esc_html_e( 'Provider Name', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_provider_name"
										   name="brag_book_gallery_provider_name"
										   value="<?php echo esc_attr( $provider_name ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'Name of the provider who performed the procedure', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_provider_profile_url"><?php esc_html_e( 'Provider Profile URL', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="url" id="brag_book_gallery_provider_profile_url"
										   name="brag_book_gallery_provider_profile_url"
										   value="<?php echo esc_url( $provider_profile_url ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'URL to the provider\'s profile page', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_provider_suffix"><?php esc_html_e( 'Provider Suffix', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_provider_suffix"
										   name="brag_book_gallery_provider_suffix"
										   value="<?php echo esc_attr( $provider_suffix ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'Professional suffix (e.g., MD, PhD, DDS)', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_member_id"><?php esc_html_e( 'Member ID', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="number" id="brag_book_gallery_member_id"
										   name="brag_book_gallery_member_id"
										   value="<?php echo esc_attr( $member_id ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'Member ID associated with this case', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<div id="api-patient" class="tab-content">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_patient_age"><?php esc_html_e( 'Patient Age', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="number"
										   id="brag_book_gallery_patient_age"
										   name="brag_book_gallery_patient_age"
										   value="<?php echo esc_attr( $age ); ?>"
										   min="18"
										   max="100"
										   class="small-text"/>
									<p class="description"><?php esc_html_e( 'Patient age at time of procedure', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_gender"><?php esc_html_e( 'Patient Gender', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<select id="brag_book_gallery_gender" name="brag_book_gallery_gender">
										<option
											value=""><?php esc_html_e( 'Select Gender', 'brag-book-gallery' ); ?></option>
										<option
											value="female" <?php selected( $patient_gender, 'female' ); ?>><?php esc_html_e( 'Female', 'brag-book-gallery' ); ?></option>
										<option
											value="male" <?php selected( $patient_gender, 'male' ); ?>><?php esc_html_e( 'Male', 'brag-book-gallery' ); ?></option>
										<option
											value="non-binary" <?php selected( $patient_gender, 'non-binary' ); ?>><?php esc_html_e( 'Non-binary', 'brag-book-gallery' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_ethnicity"><?php esc_html_e( 'Ethnicity', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_ethnicity"
										   name="brag_book_gallery_ethnicity"
										   value="<?php echo esc_attr( $ethnicity ); ?>"
										   class="regular-text"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_height"><?php esc_html_e( 'Height', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="number" id="brag_book_gallery_height"
										   name="brag_book_gallery_height"
										   value="<?php echo esc_attr( $height ); ?>"
										   class="small-text"/>
									<select id="brag_book_gallery_height_unit"
											name="brag_book_gallery_height_unit">
										<option
											value="inches" <?php selected( $height_unit, 'inches' ); ?>>
											<?php esc_html_e( 'Inches', 'brag-book-gallery' ); ?>
										</option>
										<option
											value="cm" <?php selected( $height_unit, 'cm' ); ?>>
											<?php esc_html_e( 'Centimeters', 'brag-book-gallery' ); ?>
										</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_weight"><?php esc_html_e( 'Weight', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="number" id="brag_book_gallery_weight"
										   name="brag_book_gallery_weight"
										   value="<?php echo esc_attr( $weight ); ?>"
										   class="small-text"/>
									<select id="brag_book_gallery_weight_unit"
											name="brag_book_gallery_weight_unit">
										<option
											value="lbs" <?php selected( $weight_unit, 'lbs' ); ?>>
											<?php esc_html_e( 'Pounds', 'brag-book-gallery' ); ?>
										</option>
										<option
											value="kg" <?php selected( $weight_unit, 'kg' ); ?>>
											<?php esc_html_e( 'Kilograms', 'brag-book-gallery' ); ?>
										</option>
									</select>
								</td>
							</tr>
						</table>
					</div>

					<div id="api-procedure-details" class="tab-content">
						<?php
						// Get procedure details JSON
						$procedure_details_json = get_post_meta( $post->ID, 'brag_book_gallery_procedure_details', true );

						$procedure_details = ! empty( $procedure_details_json ) ? json_decode( $procedure_details_json, true ) : array();

						if ( ! empty( $procedure_details ) && is_array( $procedure_details ) ) :
							?>
							<table class="form-table">
								<?php
								foreach ( $procedure_details as $procedure_id => $details ) :
									if ( ! is_array( $details ) || empty( $details ) ) {
										continue;
									}

									// Get procedure name if available
									$procedure_term = get_term_by( 'slug', 'procedure-' . $procedure_id, 'procedures' );
									$procedure_name = $procedure_term ? $procedure_term->name : 'Procedure ' . $procedure_id;
									?>
									<tr>
										<td colspan="2">
											<h4 style="margin-top: 0;"><?php echo esc_html( $procedure_name ); ?></h4>
										</td>
									</tr>
									<?php
									foreach ( $details as $detail_label => $detail_value ) :
										?>
										<tr>
											<th scope="row" style="padding-left: 20px;">
												<?php echo esc_html( $detail_label ); ?>
											</th>
											<td>
												<?php
												if ( is_array( $detail_value ) ) {
													echo esc_html( implode( ', ', $detail_value ) );
												} else {
													echo esc_html( $detail_value );
												}
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</table>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'No procedure details available for this case.', 'brag-book-gallery' ); ?></p>
						<?php endif; ?>
					</div>

					<div id="api-postop" class="tab-content">
						<h4><?php esc_html_e( 'PostOp Information', 'brag-book-gallery' ); ?></h4>
						<?php
						// Add nonce for PostOp security
						wp_nonce_field( 'save_case_postop', 'case_postop_nonce' );
						?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="brag_book_gallery_postop_technique"><?php esc_html_e( 'Technique', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_postop_technique"
										   name="brag_book_gallery_postop_technique"
										   value="<?php echo esc_attr( $postop_technique ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'Post-operative technique used', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="brag_book_gallery_postop_revision_surgery"><?php esc_html_e( 'Revision Surgery', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="checkbox" id="brag_book_gallery_postop_revision_surgery"
										   name="brag_book_gallery_postop_revision_surgery"
										   value="1"
										   <?php checked( $postop_revision_surgery, '1' ); ?>/>
									<label for="brag_book_gallery_postop_revision_surgery"><?php esc_html_e( 'This was a revision surgery', 'brag-book-gallery' ); ?></label>
									<p class="description"><?php esc_html_e( 'Indicates if this case was a revision surgery', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="brag_book_gallery_postop_after1_timeframe"><?php esc_html_e( 'After Photo 1 Timeframe', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="number" id="brag_book_gallery_postop_after1_timeframe"
										   name="brag_book_gallery_postop_after1_timeframe"
										   value="<?php echo esc_attr( $postop_after1_timeframe ); ?>"
										   min="0"
										   class="small-text"/>
									<select id="brag_book_gallery_postop_after1_unit"
											name="brag_book_gallery_postop_after1_unit"
											class="regular-text">
										<option value=""><?php esc_html_e( '— Select Unit —', 'brag-book-gallery' ); ?></option>
										<option value="days" <?php selected( $postop_after1_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'brag-book-gallery' ); ?></option>
										<option value="weeks" <?php selected( $postop_after1_unit, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'brag-book-gallery' ); ?></option>
										<option value="months" <?php selected( $postop_after1_unit, 'months' ); ?>><?php esc_html_e( 'Months', 'brag-book-gallery' ); ?></option>
										<option value="years" <?php selected( $postop_after1_unit, 'years' ); ?>><?php esc_html_e( 'Years', 'brag-book-gallery' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Time elapsed between procedure and after photo (e.g., "6 months")', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<div id="api-seo" class="tab-content">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_seo_suffix_url"><?php esc_html_e( 'SEO Suffix URL', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_seo_suffix_url"
										   name="brag_book_gallery_seo_suffix_url"
										   value="<?php echo esc_attr( $seo_suffix_url ); ?>"
										   class="regular-text"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_seo_headline"><?php esc_html_e( 'SEO Headline', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_seo_headline"
										   name="brag_book_gallery_seo_headline"
										   value="<?php echo esc_attr( $seo_headline ); ?>"
										   class="large-text"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_seo_page_title"><?php esc_html_e( 'SEO Page Title', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_seo_page_title"
										   name="brag_book_gallery_seo_page_title"
										   value="<?php echo esc_attr( $seo_page_title ); ?>"
										   class="large-text"/>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_seo_page_description"><?php esc_html_e( 'SEO Page Description', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
											<textarea id="brag_book_gallery_seo_page_description"
													  name="brag_book_gallery_seo_page_description"
													  rows="3"
													  class="large-text"><?php echo esc_textarea( $seo_page_description ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_seo_alt_text"><?php esc_html_e( 'SEO Alt Text', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_seo_alt_text"
										   name="brag_book_gallery_seo_alt_text"
										   value="<?php echo esc_attr( $seo_alt_text ); ?>"
										   class="large-text"/>
								</td>
							</tr>
						</table>
					</div>

					<div id="api-images" class="tab-content">
						<?php self::render_image_variants_panel( $post->ID ); ?>
					</div>
				</div>
			</div>
		</div>


		<?php
	}

	/**
	 * Enqueue case meta box CSS and tab-switching script
	 *
	 * Loads only on the brag_book_cases post edit screen.
	 *
	 * @since 4.4.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_case_meta_assets( string $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE_CASES !== $screen->post_type ) {
			return;
		}

		// Version by file modification time so edits to these standalone assets
		// always bust the browser cache (they are not part of the webpack/sass
		// build, so their version can't ride the plugin version bump).
		$css_path = Setup::get_plugin_path() . 'assets/css/admin/case-meta.css';
		$js_path  = Setup::get_plugin_path() . 'assets/js/admin/case-meta-tabs.js';

		wp_enqueue_style(
			'brag-book-gallery-case-meta',
			Setup::get_asset_url( 'assets/css/admin/case-meta.css' ),
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '3.3.3'
		);

		wp_enqueue_script(
			'brag-book-gallery-case-meta-tabs',
			Setup::get_asset_url( 'assets/js/admin/case-meta-tabs.js' ),
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : '3.3.3',
			true
		);
	}

	/**
	 * Save case meta data
	 *
	 * Handles saving all case-related meta data including:
	 * - Basic case information
	 * - Patient data
	 * - PostOp information
	 * - SEO metadata
	 * - Image URLs
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc, nonce verification, and sanitization
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function save_case_meta( int $post_id ): void {
		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type
		if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save case details (legacy - kept for backward compatibility)
		// Note: Fields have been moved to API Case Data meta box
		if ( isset( $_POST['case_details_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_details_nonce'] ) ), 'save_case_details' ) ) {
			// This meta box is now empty - fields moved to API Case Data
			// Kept for potential future use
		}

		// Handle meta saves from Gutenberg (new prefix format)
		$gutenberg_fields = array(
			// Case details (now in API Case Data meta box)
			'brag_book_gallery_patient_age'            => 'absint',
			'brag_book_gallery_gender'                 => 'sanitize_text_field',
			'brag_book_gallery_notes'                  => 'sanitize_textarea_field',
			'brag_book_gallery_provider_name'            => 'sanitize_text_field',
			'brag_book_gallery_provider_profile_url'     => 'esc_url_raw',
			'brag_book_gallery_provider_suffix'          => 'sanitize_text_field',
			'brag_book_gallery_member_id'              => 'absint',

			// API data
			'brag_book_gallery_case_id'                => 'absint',
			'brag_book_gallery_procedure_case_id'      => 'absint',
			'brag_book_gallery_patient_id'             => 'sanitize_text_field',
			'brag_book_gallery_org_id'                 => 'sanitize_text_field',
			'brag_book_gallery_quality_score'          => 'absint',

			// Patient information
			'brag_book_gallery_ethnicity'              => 'sanitize_text_field',
			'brag_book_gallery_height'                 => 'absint',
			'brag_book_gallery_height_unit'            => 'sanitize_text_field',
			'brag_book_gallery_weight'                 => 'absint',
			'brag_book_gallery_weight_unit'            => 'sanitize_text_field',

			// SEO data
			'brag_book_gallery_seo_suffix_url'         => 'sanitize_text_field',
			'brag_book_gallery_seo_headline'           => 'sanitize_text_field',
			'brag_book_gallery_seo_page_title'         => 'sanitize_text_field',
			'brag_book_gallery_seo_page_description'   => 'sanitize_textarea_field',
			'brag_book_gallery_seo_alt_text'           => 'sanitize_text_field',

			// PostOp data.
			'brag_book_gallery_postop_technique'       => 'sanitize_text_field',
			'brag_book_gallery_postop_after1_timeframe' => 'absint',
			'brag_book_gallery_postop_after1_unit'     => 'sanitize_text_field',
		);

		foreach ( $gutenberg_fields as $field => $sanitize_function ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = call_user_func( $sanitize_function, wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, $field, $value );
			}
		}

		// Handle Gutenberg image URL sets (JSON format).
		// Gated on the API Case Data nonce so unrelated save flows (Rank Math /
		// Yoast / page-content updates) cannot trip this block and rewrite the
		// stored signed-URL JSON. esc_url_raw is not perfectly byte-idempotent
		// against long signed JWT URLs, so re-running it on every save produced
		// "InvalidSignature" errors from Supabase / S3 the next time the image
		// was fetched.
		if (
			isset( $_POST['case_api_data_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_api_data_nonce'] ) ), 'save_case_api_data' )
			&& isset( $_POST['brag_book_gallery_image_url_sets'] )
		) {
			$json_data      = wp_unslash( $_POST['brag_book_gallery_image_url_sets'] );
			$image_url_sets = is_string( $json_data ) ? json_decode( $json_data, true ) : null;

			if ( is_array( $image_url_sets ) ) {
				$sanitized_sets = array();

				foreach ( $image_url_sets as $url_set ) {
					if ( ! is_array( $url_set ) ) {
						continue;
					}

					$sanitized_set = array();
					$has_content   = false;

					$url_field_keys = array(
						'before_url',
						'after_url',
						'post_processed_url',
						'high_res_url',
					);

					foreach ( $url_field_keys as $url_field ) {
						$raw                          = isset( $url_set[ $url_field ] ) && is_string( $url_set[ $url_field ] ) ? trim( $url_set[ $url_field ] ) : '';
						$sanitized_set[ $url_field ] = $raw;
						if ( '' !== $raw ) {
							$has_content = true;
						}
					}

					if ( $has_content ) {
						$sanitized_sets[] = $sanitized_set;
					}
				}

				// Skip the write entirely when the submitted value matches what
				// is already stored — eliminates accidental mutation from
				// idempotent saves of an unchanged meta box.
				$existing      = get_post_meta( $post_id, 'brag_book_gallery_image_url_sets', true );
				$existing_json = is_array( $existing ) ? wp_json_encode( $existing ) : '';
				$new_json      = wp_json_encode( $sanitized_sets );
				if ( $existing_json !== $new_json ) {
					update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $sanitized_sets );
				}
			}
		}

		// Handle the editable image-variant grid from the API Case Data meta box.
		// Writes the structured variant meta and re-derives the legacy flat URL
		// keys the frontend renderers read. Guarded by the meta-box nonce so
		// unrelated save flows (Rank Math / Yoast sidebars, REST updates) can't
		// clobber the signed image URLs.
		if ( isset( $_POST['case_api_data_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_api_data_nonce'] ) ), 'save_case_api_data' ) ) {
			$this->save_case_image_variants( $post_id );
		}

		// Save API data
		if ( isset( $_POST['case_api_data_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_api_data_nonce'] ) ), 'save_case_api_data' ) ) {
			$api_fields = array(
				// Basic Info
				'brag_book_gallery_case_id'              => 'absint',
				'brag_book_gallery_procedure_ids'        => 'sanitize_text_field',

				// Patient Data
				'brag_book_gallery_ethnicity'            => 'sanitize_text_field',
				'brag_book_gallery_height'               => 'absint',
				'brag_book_gallery_height_unit'          => 'sanitize_text_field',
				'brag_book_gallery_weight'               => 'absint',
				'brag_book_gallery_weight_unit'          => 'sanitize_text_field',

				// SEO
				'brag_book_gallery_seo_suffix_url'       => 'sanitize_text_field',
				'brag_book_gallery_seo_headline'         => 'sanitize_text_field',
				'brag_book_gallery_seo_page_title'       => 'sanitize_text_field',
				'brag_book_gallery_seo_page_description' => 'sanitize_textarea_field',
				'brag_book_gallery_seo_alt_text'         => 'sanitize_text_field',
			);

			foreach ( $api_fields as $field => $sanitize_function ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = call_user_func( $sanitize_function, wp_unslash( $_POST[ $field ] ) );
					update_post_meta( $post_id, $field, $value );
				}
			}

			// Handle checkboxes separately (they may not be present in $_POST if unchecked)
			$checkbox_fields = array(
				'brag_book_gallery_postop_revision_surgery',
				'brag_book_gallery_featured',
				'brag_book_gallery_top_performing',
			);

			foreach ( $checkbox_fields as $field ) {
				$value = isset( $_POST[ $field ] ) ? '1' : '0';
				update_post_meta( $post_id, $field, $value );
			}
		}

		// Save PostOp data
		if ( isset( $_POST['case_postop_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_postop_nonce'] ) ), 'save_case_postop' ) ) {
			$postop_fields = array(
				'brag_book_gallery_postop_technique'        => 'sanitize_text_field',
				'brag_book_gallery_postop_after1_timeframe' => 'absint',
				'brag_book_gallery_postop_after1_unit'      => 'sanitize_text_field',
			);

			foreach ( $postop_fields as $field => $sanitize_function ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = call_user_func( $sanitize_function, wp_unslash( $_POST[ $field ] ) );
					update_post_meta( $post_id, $field, $value );
				}
			}

			// Handle revision surgery checkbox (may not be present in $_POST if unchecked)
			$value = isset( $_POST['brag_book_gallery_postop_revision_surgery'] ) ? '1' : '0';
			update_post_meta( $post_id, 'brag_book_gallery_postop_revision_surgery', $value );
		}

		// Save gallery images
		if ( isset( $_POST['case_images_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_images_nonce'] ) ), 'save_case_images' ) ) {
			// Handle new format (brag_book_gallery_images)
			if ( isset( $_POST['brag_book_gallery_images'] ) ) {
				$gallery_images = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['brag_book_gallery_images'] ) ) ) );
				$gallery_images = array_filter( $gallery_images ); // Remove empty values
				update_post_meta( $post_id, 'brag_book_gallery_images', $gallery_images );
				// Also save with old prefix for backward compatibility
				update_post_meta( $post_id, 'brag_book_gallery_gallery_images', $gallery_images );
			} elseif ( isset( $_POST['case_gallery_images'] ) ) {
				// Handle old format (case_gallery_images) for backward compatibility
				$gallery_images = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['case_gallery_images'] ) ) ) );
				$gallery_images = array_filter( $gallery_images ); // Remove empty values
				update_post_meta( $post_id, 'brag_book_gallery_gallery_images', $gallery_images );
				// Also save with new prefix
				update_post_meta( $post_id, 'brag_book_gallery_images', $gallery_images );
			}
		}
	}

	/**
	 * Persist the editable image-variant grid submitted from the meta box.
	 *
	 * Rebuilds the structured `brag_book_gallery_case_image_variants` meta and
	 * re-derives the legacy flat URL keys. Unchanged URLs are preserved verbatim
	 * rather than re-escaped: `esc_url_raw()` is not perfectly byte-idempotent
	 * against long JWT query strings, and re-running it can corrupt a signature
	 * and produce "InvalidSignature" errors at fetch time. Blank medium/small
	 * fields fall back to full so the stored data keeps a complete set.
	 *
	 * @since 3.3.3
	 * @param int $post_id Case post ID.
	 * @return void
	 */
	private function save_case_image_variants( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		if ( ! isset( $_POST['bbg_image_variants'] ) || ! is_array( $_POST['bbg_image_variants'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$submitted = wp_unslash( $_POST['bbg_image_variants'] );
		$stored    = get_post_meta( $post_id, 'brag_book_gallery_case_image_variants', true );
		$stored    = is_array( $stored ) ? $stored : array();

		$clean_url = static function ( string $new, string $old ): string {
			$new = trim( $new );
			if ( '' === $new ) {
				return '';
			}
			// Preserve signed URLs verbatim when unchanged; only escape real edits.
			return ( $new === $old ) ? $old : esc_url_raw( $new );
		};

		$sets = array();
		foreach ( $submitted as $set_index => $nodes ) {
			if ( ! is_array( $nodes ) ) {
				continue;
			}

			$set     = array();
			$has_url = false;
			foreach ( self::CASE_IMAGE_NODES as $node => $label ) {
				$input    = isset( $nodes[ $node ] ) && is_array( $nodes[ $node ] ) ? $nodes[ $node ] : array();
				$old_node = isset( $stored[ $set_index ][ $node ] ) && is_array( $stored[ $set_index ][ $node ] ) ? $stored[ $set_index ][ $node ] : array();

				// A cleared field stays cleared: an empty size means "this rendition
				// does not exist", so it must not be back-filled from a larger one.
				$full   = $clean_url( (string) ( $input['full'] ?? '' ), (string) ( $old_node['full'] ?? '' ) );
				$medium = $clean_url( (string) ( $input['medium'] ?? '' ), (string) ( $old_node['medium'] ?? '' ) );
				$small  = $clean_url( (string) ( $input['small'] ?? '' ), (string) ( $old_node['small'] ?? '' ) );

				$set[ $node ] = array(
					'full'   => $full,
					'medium' => $medium,
					'small'  => $small,
					'alt'    => isset( $old_node['alt'] ) ? (string) $old_node['alt'] : '',
				);

				if ( '' !== $full ) {
					$has_url = true;
				}
			}

			if ( $has_url ) {
				$sets[] = $set;
			}
		}

		if ( empty( $sets ) ) {
			delete_post_meta( $post_id, 'brag_book_gallery_case_image_variants' );
		} else {
			update_post_meta( $post_id, 'brag_book_gallery_case_image_variants', $sets );
		}

		self::sync_flat_image_keys_from_variants( $post_id, $sets );
	}

	/**
	 * Re-derive the legacy flat image URL keys from the structured variant sets.
	 *
	 * Keeps `brag_book_gallery_case_*_url` (semicolon/newline-delimited full-size
	 * URLs) and `brag_book_gallery_image_url_sets` in sync with the variant meta
	 * so the existing frontend renderers stay correct after an editor save.
	 *
	 * @since 3.3.3
	 * @param int                                                  $post_id Case post ID.
	 * @param array<int,array<string,array<string,string>>>        $sets    Variant sets.
	 * @return void
	 */
	private static function sync_flat_image_keys_from_variants( int $post_id, array $sets ): void {
		$flat_lists = array();
		foreach ( self::CASE_IMAGE_FLAT_KEYS as $node => $key ) {
			$flat_lists[ $node ] = array();
		}
		$url_sets = array();

		foreach ( $sets as $set ) {
			$url_set = array();
			foreach ( self::CASE_IMAGE_FLAT_KEYS as $node => $key ) {
				$full = isset( $set[ $node ]['full'] ) ? (string) $set[ $node ]['full'] : '';
				if ( '' !== $full ) {
					$flat_lists[ $node ][] = $full;
				}
				// image_url_sets uses "<node>_url" keys (before_url, after_url, …).
				$url_set[ $node . '_url' ] = $full;
			}
			if ( array_filter( $url_set ) ) {
				$url_sets[] = $url_set;
			}
		}

		foreach ( self::CASE_IMAGE_FLAT_KEYS as $node => $key ) {
			if ( empty( $flat_lists[ $node ] ) ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			$lines = array_map( static fn( string $url ): string => $url . ';', $flat_lists[ $node ] );
			update_post_meta( $post_id, $key, implode( "\n", $lines ) );
		}

		if ( empty( $url_sets ) ) {
			delete_post_meta( $post_id, 'brag_book_gallery_image_url_sets' );
		} else {
			update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $url_sets );
		}
	}

	/**
	 * Add query vars for case filtering
	 *
	 * Registers custom query variables for case and procedure filtering.
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc
	 *
	 * @param array $vars Existing query variables.
	 *
	 * @return array Modified query variables
	 */
	public function add_simple_query_vars( array $vars ): array {
		$vars[] = 'case_id';
		$vars[] = 'procedure_slug';

		return $vars;
	}

	/**
	 * Save API response data to post meta
	 *
	 * Processes and saves comprehensive case data from the BRAG book API including:
	 * - Basic case information
	 * - Patient demographics
	 * - Procedure details
	 * - PostOp data
	 * - SEO metadata
	 * - Photo sets and image URLs
	 * - Provider/creator information
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc, removed debug error_log, improved sanitization
	 *
	 * @param int           $post_id           The post ID to save data to.
	 * @param array         $api_data          The API response data.
	 * @param callable|null $progress_callback Optional callback for progress updates.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function save_api_response_data( int $post_id, array $api_data, ?callable $progress_callback = null ): bool {
		// Verify this is a case post.
		if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
			return false;
		}

		// First check: Only import cases approved for website use.
		if ( ! isset( $api_data['isForWebsite'] ) || ! $api_data['isForWebsite'] ) {
			return false;
		}

		$field_mapping = array(
			// Basic Info.
			'id'                => 'brag_book_gallery_procedure_case_id',
			'caseId'            => 'brag_book_gallery_case_id',
			'patientId'         => 'brag_book_gallery_patient_id',
			'userId'            => 'brag_book_gallery_user_id',
			'orgId'             => 'brag_book_gallery_org_id',
			'emrId'             => 'brag_book_gallery_emr_id',

			// Patient Data (updated for v2 API compatibility).
			'age'               => 'brag_book_gallery_patient_age',
			'gender'            => 'brag_book_gallery_gender',
			'ethnicity'         => 'brag_book_gallery_ethnicity',
			'height'            => 'brag_book_gallery_height',
			'heightUnit'        => 'brag_book_gallery_height_unit',
			'weight'            => 'brag_book_gallery_weight',
			'weightUnit'        => 'brag_book_gallery_weight_unit',

			// Settings.
			'qualityScore'      => 'brag_book_gallery_quality_score',
			'approvedForSocial' => 'brag_book_gallery_approved_for_social',
			'isForTablet'       => 'brag_book_gallery_is_for_tablet',
			'isForWebsite'      => 'brag_book_gallery_is_for_website',
			'draft'             => 'brag_book_gallery_draft',
			'noWatermark'       => 'brag_book_gallery_no_watermark',

			// Additional fields.
			'details'           => 'brag_book_gallery_notes',

			// v2-specific fields.
			'description'       => 'brag_book_gallery_notes',
			'createdAt'         => 'brag_book_gallery_created_at',
			'updatedAt'         => 'brag_book_gallery_updated_at',

			// Case flags (booleans normalized to '1'/'0' by the loop below).
			'featured'          => 'brag_book_gallery_featured',
			'topPerforming'     => 'brag_book_gallery_top_performing',
		);

		// Save basic fields
		if ( $progress_callback ) {
			$progress_callback( "Writing case data fields for post {$post_id}..." );
		}

		// Save field mappings.
		foreach ( $field_mapping as $api_field => $meta_key ) {
			if ( isset( $api_data[ $api_field ] ) ) {
				$value = $api_data[ $api_field ];

				// Convert boolean values
				if ( is_bool( $value ) ) {
					$value = $value ? '1' : '0';
				}

				// Sanitize based on field type
				if (
					in_array(
						$meta_key,
						array(
							'brag_book_gallery_case_id',
							'brag_book_gallery_procedure_case_id',
							'brag_book_gallery_patient_age',
							'brag_book_gallery_height',
							'brag_book_gallery_weight',
							'brag_book_gallery_quality_score',
							'brag_book_gallery_after1_timeframe',
							'brag_book_gallery_after2_timeframe',
						),
						true
					)
				) {
					$value = absint( $value );
				} elseif (
					in_array(
						$meta_key,
						array(
							'brag_book_gallery_notes',
						),
						true
					)
				) {
					$value = wp_kses_post( $value );
				} else {
					$value = sanitize_text_field( $value );
				}

				// NOTE: image URL meta keys (case_before_url, case_after_url,
				// case_post_processed_url, case_high_res_url) are intentionally
				// NOT handled here. They are written separately in the photoSets
				// loop below via esc_url_raw. WordPress's sanitize_textarea_field
				// strips every %XX percent-encoded octet, which would corrupt
				// signed URLs (e.g. Supabase) and break their JWT signature.

				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		// Handle procedure IDs - save in both formats
		if ( isset( $api_data['procedureIds'] ) && is_array( $api_data['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'absint', $api_data['procedureIds'] ) );
			update_post_meta( $post_id, 'brag_book_gallery_procedure_ids', $procedure_ids );
		}

		// Handle category IDs (v2 API field)
		if ( isset( $api_data['categoryIds'] ) && is_array( $api_data['categoryIds'] ) ) {
			$category_ids = implode( ',', array_map( 'absint', $api_data['categoryIds'] ) );
			update_post_meta( $post_id, 'brag_book_gallery_category_ids', $category_ids );
		}

		// Handle provider information. A case may carry multiple providers in the
		// v2 `providers` array. The provider taxonomy (assigned during sync) is the
		// canonical multi-value store; here we denormalize a few values onto the
		// case post: the ordered list of provider API IDs (for /v2/practices
		// lookups and display ordering) and the primary provider for fallback
		// display and member filtering.
		if ( isset( $api_data['providers'] ) && is_array( $api_data['providers'] ) && ! empty( $api_data['providers'] ) ) {
			$providers = $api_data['providers'];

			// Order by the API position field (lowest position is primary).
			usort(
				$providers,
				static function ( $a, $b ) {
					return ( (int) ( $a['position'] ?? 0 ) ) <=> ( (int) ( $b['position'] ?? 0 ) );
				}
			);

			// Ordered list of provider API IDs.
			$provider_ids = array();
			foreach ( $providers as $provider ) {
				if ( is_array( $provider ) && isset( $provider['id'] ) ) {
					$provider_ids[] = absint( $provider['id'] );
				}
			}
			if ( ! empty( $provider_ids ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_provider_ids', implode( ',', $provider_ids ) );
			}

			// Denormalize the primary provider.
			$primary = $providers[0];
			if ( isset( $primary['id'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_member_id', absint( $primary['id'] ) );
			}

			$primary_name = isset( $primary['name'] ) ? sanitize_text_field( $primary['name'] ) : '';
			if ( empty( $primary_name ) ) {
				$primary_name = trim(
					implode(
						' ',
						array_filter(
							array(
								isset( $primary['firstName'] ) ? sanitize_text_field( $primary['firstName'] ) : '',
								isset( $primary['lastName'] ) ? sanitize_text_field( $primary['lastName'] ) : '',
							)
						)
					)
				);
			}
			if ( ! empty( $primary_name ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_provider_name', $primary_name );
			}
		}

		// Handle PostOp data.
		if ( isset( $api_data['postOp'] ) && is_array( $api_data['postOp'] ) ) {

			$postop = $api_data['postOp'];

			if ( isset( $postop['technique'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_postop_technique', sanitize_text_field( $postop['technique'] ) );
			}

			if ( isset( $postop['revisionSurgery'] ) ) {
				$value = is_bool( $postop['revisionSurgery'] ) ? ( $postop['revisionSurgery'] ? '1' : '0' ) : sanitize_text_field( $postop['revisionSurgery'] );
				update_post_meta( $post_id, 'brag_book_gallery_postop_revision_surgery', $value );
			}

			if ( isset( $postop['after1Timeframe'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_postop_after1_timeframe', absint( $postop['after1Timeframe'] ) );
			}

			if ( isset( $postop['after1Unit'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_postop_after1_unit', sanitize_text_field( $postop['after1Unit'] ) );
			}
		}

		// Handle SEO data from caseDetails and update post title
		$seo_headline         = null;
		$seo_page_title       = null;
		$seo_page_description = null;

		if ( isset( $api_data['caseDetails'] ) && is_array( $api_data['caseDetails'] ) ) {
			foreach ( $api_data['caseDetails'] as $case_detail ) {
				if ( isset( $case_detail['seoSuffixUrl'] ) ) {
					$seo_suffix = sanitize_text_field( $case_detail['seoSuffixUrl'] );
					update_post_meta( $post_id, 'brag_book_gallery_seo_suffix_url', $seo_suffix );
				}
				if ( isset( $case_detail['seoHeadline'] ) && ! empty( $case_detail['seoHeadline'] ) ) {
					$seo_headline = sanitize_text_field( $case_detail['seoHeadline'] );
					update_post_meta( $post_id, 'brag_book_gallery_seo_headline', $seo_headline );
				}
				if ( isset( $case_detail['seoPageTitle'] ) && ! empty( $case_detail['seoPageTitle'] ) ) {
					$seo_page_title = sanitize_text_field( $case_detail['seoPageTitle'] );
					update_post_meta( $post_id, 'brag_book_gallery_seo_page_title', $seo_page_title );
				}
				if ( isset( $case_detail['seoPageDescription'] ) && ! empty( $case_detail['seoPageDescription'] ) ) {
					$seo_page_description = sanitize_textarea_field( $case_detail['seoPageDescription'] );
					update_post_meta( $post_id, 'brag_book_gallery_seo_page_description', $seo_page_description );
				}
			}
		}

		$case_id = $api_data['id'] ?? $post_id;

		// Get the procedure taxonomy term assigned to this post.
		$terms = wp_get_post_terms( $post_id, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			// Use the first (and should be only) assigned procedure term
			$procedure_name  = $terms[0]->name;
			$display_case_id = $api_data['caseId'] ?? $case_id;
			$post_title      = $procedure_name . ' #' . $display_case_id;
		} else {
			// Fallback: try to get procedure name from stored procedure IDs
			$display_case_id = $api_data['caseId'] ?? $case_id;
			$procedure_name  = null;

			// Check if we have procedure IDs in the API data
			if ( isset( $api_data['procedureIds'] ) && is_array( $api_data['procedureIds'] ) && ! empty( $api_data['procedureIds'] ) ) {
				// Get the first procedure ID
				$first_procedure_id = $api_data['procedureIds'][0];

				// Look up the procedure term by its API ID
				$procedure_terms = get_terms(
					array(
						'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
						'meta_key'   => 'procedure_id',
						'meta_value' => $first_procedure_id,
						'hide_empty' => false,
						'number'     => 1,
					)
				);

				if ( ! is_wp_error( $procedure_terms ) && ! empty( $procedure_terms ) ) {
					$procedure_name = $procedure_terms[0]->name;
				}
			}

			// Generate title with procedure name if found, otherwise use generic "Case"
			if ( $procedure_name ) {
				$post_title = $procedure_name . ' #' . $display_case_id;
			} else {
				$post_title = "Case #{$display_case_id}";
			}
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $post_title ?: 'Untitled Case',
			)
		);

		// Apply SEO metadata if we have an SEO plugin installed.
		if ( $seo_page_title || $seo_page_description ) {
			self::apply_seo_metadata( $post_id, $seo_page_title, $seo_page_description );
		}

		// Handle procedure details.
		if ( isset( $api_data['procedureDetails'] ) && is_array( $api_data['procedureDetails'] ) ) {
			// Save the full procedure details as JSON for reference (both modern and legacy).
			update_post_meta( $post_id, 'brag_book_gallery_procedure_details', wp_json_encode( $api_data['procedureDetails'] ) );

			// Extract and save individual procedure details for easier querying
			foreach ( $api_data['procedureDetails'] as $procedure_id => $details ) {
				if ( is_array( $details ) ) {
					foreach ( $details as $detail_key => $detail_values ) {
						// Create meta key
						$modern_meta_key = 'brag_book_gallery_procedure_detail_' . $procedure_id . '_' . sanitize_key( $detail_key );

						// Save as array if multiple values, string if single value
						$value = null;
						if ( is_array( $detail_values ) && count( $detail_values ) > 1 ) {
							$value = $detail_values;
						} elseif ( is_array( $detail_values ) ) {
							$value = $detail_values[0];
						} else {
							$value = $detail_values;
						}

						// Save meta
						update_post_meta( $post_id, $modern_meta_key, $value );
					}
				}
			}
		}

		// Handle photo sets and download images
		if ( isset( $api_data['photoSets'] ) && is_array( $api_data['photoSets'] ) ) {
			$total_photo_sets = count( $api_data['photoSets'] );
			if ( $progress_callback ) {
				$progress_callback( "Processing {$total_photo_sets} image sets for post {$post_id}..." );
			}

			$url_fields_to_clear = array(
				'brag_book_gallery_case_before_url',
				'brag_book_gallery_case_after_url',
				'brag_book_gallery_case_after_plus_url',
				'brag_book_gallery_case_post_processed_url',
				'brag_book_gallery_case_high_res_url',
				'brag_book_gallery_case_image_variants',
			);

			foreach ( $url_fields_to_clear as $field ) {
				delete_post_meta( $post_id, $field );
			}

			$photo_set_count    = 0;
			$image_url_sets     = array();
			$image_variant_sets = array();

			foreach ( $api_data['photoSets'] as $photo_set ) {
				++$photo_set_count;

				if ( $progress_callback ) {
					$progress_callback( "Processing image set {$photo_set_count}/{$total_photo_sets} for post {$post_id}..." );
				}

				// Extract images object from photo set (new API structure)
				$images = isset( $photo_set['images'] ) && is_array( $photo_set['images'] ) ? $photo_set['images'] : array();

				// Resolve the small/medium/full variants (and alt text) for every
				// image node. The new nested structure carries them under `variants`;
				// the old flat structure has only a single URL, so only `full` is
				// populated. Sizes the API has not generated are stored empty.
				if ( ! empty( $images ) ) {
					$before_variants     = self::extract_image_variants( $images['before'] ?? array() );
					$after_variants      = self::extract_image_variants( $images['after'] ?? array() );
					$after_plus_variants = self::extract_image_variants( $images['afterPlus'] ?? array() );
					$post_processed_vars = self::extract_image_variants( $images['sideBySide']['standard'] ?? array() );
					$high_res_variants   = self::extract_image_variants( $images['sideBySide']['highDefinition'] ?? array() );
				} else {
					$before_variants     = self::flat_url_to_variants( $photo_set['beforeLocationUrl'] ?? '' );
					$after_variants      = self::flat_url_to_variants( $photo_set['afterLocationUrl1'] ?? '' );
					$after_plus_variants = self::flat_url_to_variants( $photo_set['afterLocationUrl2'] ?? '' );
					$post_processed_vars = self::flat_url_to_variants( $photo_set['postProcessedImageLocation'] ?? '' );
					$high_res_variants   = self::flat_url_to_variants( $photo_set['highResPostProcessedImageLocation'] ?? '' );
				}

				// Full-size URLs kept in the legacy flat meta for existing readers.
				$before_url         = $before_variants['full'];
				$after_url          = $after_variants['full'];
				$after_plus_url     = $after_plus_variants['full'];
				$post_processed_url = $post_processed_vars['full'];
				$high_res_url       = $high_res_variants['full'];

				// Build URL set for this photo set
				$url_set = array(
					'before_url'         => $before_url,
					'after_url'          => $after_url,
					'after_plus_url'     => $after_plus_url,
					'post_processed_url' => $post_processed_url,
					'high_res_url'       => $high_res_url,
				);

				// Only add URL set if it has at least one URL.
				$has_urls = false;

				foreach ( $url_set as $url ) {
					if ( ! empty( $url ) ) {
						$has_urls = true;
						break;
					}
				}

				if ( $has_urls ) {
					$image_url_sets[] = $url_set;

					// Structured variant set: srcset rendering + admin editing.
					$image_variant_sets[] = array(
						'before'         => $before_variants,
						'after'          => $after_variants,
						'after_plus'     => $after_plus_variants,
						'post_processed' => $post_processed_vars,
						'high_res'       => $high_res_variants,
					);
				}

				// Save before image URL
				if ( ! empty( $before_url ) ) {
					$existing_urls = get_post_meta( $post_id, 'brag_book_gallery_case_before_url', true );
					$updated_urls  = ! empty( $existing_urls ) ? $existing_urls . "\n" . $before_url . ';' : $before_url . ';';
					update_post_meta( $post_id, 'brag_book_gallery_case_before_url', $updated_urls );
				}

				// Save after image URL
				if ( ! empty( $after_url ) ) {
					$existing_urls = get_post_meta( $post_id, 'brag_book_gallery_case_after_url', true );
					$updated_urls  = ! empty( $existing_urls ) ? $existing_urls . "\n" . $after_url . ';' : $after_url . ';';
					update_post_meta( $post_id, 'brag_book_gallery_case_after_url', $updated_urls );
				}

				// Save afterPlus image URL
				if ( ! empty( $after_plus_url ) ) {
					$existing_urls = get_post_meta( $post_id, 'brag_book_gallery_case_after_plus_url', true );
					$updated_urls  = ! empty( $existing_urls ) ? $existing_urls . "\n" . $after_plus_url . ';' : $after_plus_url . ';';
					update_post_meta( $post_id, 'brag_book_gallery_case_after_plus_url', $updated_urls );
				}

				// Save side-by-side standard image URL
				if ( ! empty( $post_processed_url ) ) {
					$existing_urls = get_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', true );
					$updated_urls  = ! empty( $existing_urls ) ? $existing_urls . "\n" . $post_processed_url . ';' : $post_processed_url . ';';
					update_post_meta( $post_id, 'brag_book_gallery_case_post_processed_url', $updated_urls );
				}

				// Save side-by-side high definition image URL
				if ( ! empty( $high_res_url ) ) {
					$existing_urls = get_post_meta( $post_id, 'brag_book_gallery_case_high_res_url', true );
					$updated_urls  = ! empty( $existing_urls ) ? $existing_urls . "\n" . $high_res_url . ';' : $high_res_url . ';';
					update_post_meta( $post_id, 'brag_book_gallery_case_high_res_url', $updated_urls );
				}

				if ( isset( $photo_set['isNude'] ) ) {
					update_post_meta( $post_id, 'brag_book_gallery_is_nude', $photo_set['isNude'] ? '1' : '0' );
				}
			}

			if ( ! empty( $image_url_sets ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $image_url_sets );
				if ( $progress_callback ) {
					$progress_callback( 'Saved ' . count( $image_url_sets ) . " image URL sets for post {$post_id}" );
				}
			}

			if ( ! empty( $image_variant_sets ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_case_image_variants', $image_variant_sets );
			}
		}

		// Save SEO alt text from seoInfo (root-level, not per photo set).
		if ( ! empty( $api_data['seoAltText'] ) ) {
			update_post_meta( $post_id, 'brag_book_gallery_seo_alt_text', sanitize_text_field( $api_data['seoAltText'] ) );
		} else {
			delete_post_meta( $post_id, 'brag_book_gallery_seo_alt_text' );
		}

		// Store the full API response for reference
		update_post_meta( $post_id, 'brag_book_gallery_api_response', $api_data );

		return true;
	}

	/**
	 * Resolve the small/medium/full variants and alt text for a v2 image node.
	 *
	 * The v2 API nests sized variants under `variants` ('full', 'medium', 'small').
	 * Any variant can be null until the backend generates it. A null size is stored
	 * empty rather than filled with a larger image: recording a full-size URL as the
	 * "small" variant would claim a rendition that does not exist, and srcset would
	 * then offer the browser a large file under a small width descriptor.
	 *
	 * `full` still falls back to the node's own `url` — that URL *is* the full-size
	 * image, not a substitute — and the legacy flat keys are derived from it.
	 *
	 * Readers already cope with empty sizes: build_srcset_from_node() skips them
	 * (emitting `src` alone when only `full` is known) and get_variant_url_for_url()
	 * falls back to the full URL.
	 *
	 * @since 3.3.3
	 * @param array $node A single image node (e.g. images.before), or an empty array.
	 * @return array{full:string, medium:string, small:string, alt:string} Sizes the
	 *               API has not generated are empty strings.
	 */
	private static function extract_image_variants( array $node ): array {
		$base     = isset( $node['url'] ) ? esc_url_raw( (string) $node['url'] ) : '';
		$variants = isset( $node['variants'] ) && is_array( $node['variants'] ) ? $node['variants'] : array();

		return array(
			'full'   => ! empty( $variants['full'] ) ? esc_url_raw( (string) $variants['full'] ) : $base,
			'medium' => ! empty( $variants['medium'] ) ? esc_url_raw( (string) $variants['medium'] ) : '',
			'small'  => ! empty( $variants['small'] ) ? esc_url_raw( (string) $variants['small'] ) : '',
			'alt'    => isset( $node['altText'] ) ? sanitize_text_field( (string) $node['altText'] ) : '',
		);
	}

	/**
	 * Build a variant set from a single flat URL (old API shape, no variants).
	 *
	 * The old shape carries one full-size URL and no sized renditions, so `medium`
	 * and `small` are left empty rather than pointed at the full image.
	 *
	 * @since 3.3.3
	 * @param string $url The single image URL, or an empty string.
	 * @return array{full:string, medium:string, small:string, alt:string} Only
	 *               `full` is ever populated.
	 */
	private static function flat_url_to_variants( string $url ): array {
		return array(
			'full'   => '' !== $url ? esc_url_raw( $url ) : '',
			'medium' => '',
			'small'  => '',
			'alt'    => '',
		);
	}

	/**
	 * Apply SEO metadata to post
	 *
	 * Detects installed SEO plugins (RankMath, AIOSEO, Yoast) and applies
	 * SEO metadata in the appropriate format for each plugin.
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Updated PHPDoc and escaping
	 *
	 * @param int         $post_id              The post ID.
	 * @param string|null $seo_page_title       The SEO page title.
	 * @param string|null $seo_page_description The SEO page description.
	 *
	 * @return void
	 */
	private static function apply_seo_metadata( int $post_id, ?string $seo_page_title, ?string $seo_page_description ): void {
		// Detect which SEO plugin is active and apply metadata accordingly

		// RankMath SEO
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			if ( $seo_page_title ) {
				update_post_meta( $post_id, 'rank_math_title', $seo_page_title );
			}
			if ( $seo_page_description ) {
				update_post_meta( $post_id, 'rank_math_description', $seo_page_description );
			}

			return;
		}

		// All in One SEO (AIOSEO)
		if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) || defined( 'AIOSEO_VERSION' ) ) {
			if ( $seo_page_title ) {
				update_post_meta( $post_id, '_aioseo_title', $seo_page_title );
			}
			if ( $seo_page_description ) {
				update_post_meta( $post_id, '_aioseo_description', $seo_page_description );
			}

			return;
		}

		// Yoast SEO
		if ( class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' ) ) {
			if ( $seo_page_title ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $seo_page_title );
			}
			if ( $seo_page_description ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_page_description );
			}

			return;
		}

		// No SEO plugin detected - store as custom meta for future use
		if ( $seo_page_title ) {
			update_post_meta( $post_id, '_brag_book_seo_title', $seo_page_title );
		}
		if ( $seo_page_description ) {
			update_post_meta( $post_id, '_brag_book_seo_description', $seo_page_description );
		}
	}

	/**
	 * Add BRAG book marker to attachment
	 *
	 * Placeholder for adding custom metadata to media library attachments.
	 *
	 * @since 3.0.0
	 * @since 3.3.2-beta10 Added PHPDoc
	 *
	 * @param array    $response   Array of prepared attachment data.
	 * @param WP_Post $attachment Attachment object.
	 * @param array    $meta       Array of attachment meta data.
	 *
	 * @return array Modified attachment data
	 */
	public function add_brag_book_marker_to_attachment( $response, $attachment, $meta ) {
		// Implementation would go here if needed
		return $response;
	}
}
