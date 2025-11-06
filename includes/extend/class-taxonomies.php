<?php
/**
 * Taxonomies management class
 *
 * Handles registration and management of custom taxonomies for the plugin.
 *
 * @package BRAGBookGallery
 * @subpackage Extend
 * @since 3.0.0
 */

namespace BRAGBookGallery\Includes\Extend;

/**
 * Taxonomies class
 *
 * Manages custom taxonomy registration, meta fields, and admin interface.
 * Currently handles the Procedures taxonomy with associated meta fields.
 *
 * @since 3.0.0
 */
class Taxonomies {

	/**
	 * Taxonomy constants
	 */
	public const TAXONOMY_PROCEDURES = 'brag_book_procedures';

	/**
	 * Initialize taxonomy functionality
	 *
	 * Sets up hooks for taxonomy registration, meta fields, and admin interface.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		add_action( 'init', [ $this, 'maybe_flush_rewrites' ], 999 );

		add_filter('term_link', [ $this, 'custom_procedure_term_link' ], 10, 3);

		// Add taxonomy meta fields
		add_action( self::TAXONOMY_PROCEDURES . '_add_form_fields', [ $this, 'add_procedure_meta_fields' ] );
		add_action( self::TAXONOMY_PROCEDURES . '_edit_form_fields', [ $this, 'edit_procedure_meta_fields' ] );
		add_action( 'edited_' . self::TAXONOMY_PROCEDURES, [ $this, 'save_procedure_meta' ] );
		add_action( 'create_' . self::TAXONOMY_PROCEDURES, [ $this, 'save_procedure_meta' ] );
		add_filter( 'manage_edit-' . self::TAXONOMY_PROCEDURES . '_columns', [ $this, 'add_procedure_columns' ] );
		add_filter( 'manage_' . self::TAXONOMY_PROCEDURES . '_custom_column', [ $this, 'add_procedure_column_content' ], 10, 3 );

		// Enqueue media scripts for procedures taxonomy pages
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_procedure_admin_scripts' ] );
	}

	/**
	 * Register custom taxonomies
	 *
	 * Registers the Procedures taxonomy with appropriate labels,
	 * capabilities, and features for managing procedure categories.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_taxonomies(): void {
		// Procedures taxonomy
		$procedures_labels = [
			'name'                       => _x( 'Procedures', 'Taxonomy general name', 'brag-book-gallery' ),
			'singular_name'              => _x( 'Procedure', 'Taxonomy singular name', 'brag-book-gallery' ),
			'menu_name'                  => __( 'Procedures', 'brag-book-gallery' ),
			'all_items'                  => __( 'All Procedures', 'brag-book-gallery' ),
			'parent_item'                => __( 'Parent Procedure', 'brag-book-gallery' ),
			'parent_item_colon'          => __( 'Parent Procedure:', 'brag-book-gallery' ),
			'new_item_name'              => __( 'New Procedure Name', 'brag-book-gallery' ),
			'add_new_item'               => __( 'Add New Procedure', 'brag-book-gallery' ),
			'edit_item'                  => __( 'Edit Procedure', 'brag-book-gallery' ),
			'update_item'                => __( 'Update Procedure', 'brag-book-gallery' ),
			'view_item'                  => __( 'View Procedure', 'brag-book-gallery' ),
			'separate_items_with_commas' => __( 'Separate procedures with commas', 'brag-book-gallery' ),
			'add_or_remove_items'        => __( 'Add or remove procedures', 'brag-book-gallery' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'brag-book-gallery' ),
			'popular_items'              => __( 'Popular Procedures', 'brag-book-gallery' ),
			'search_items'               => __( 'Search Procedures', 'brag-book-gallery' ),
			'not_found'                  => __( 'Not Found', 'brag-book-gallery' ),
			'no_terms'                   => __( 'No procedures', 'brag-book-gallery' ),
			'items_list'                 => __( 'Procedures list', 'brag-book-gallery' ),
			'items_list_navigation'      => __( 'Procedures list navigation', 'brag-book-gallery' ),
		];

		// Get gallery page slug for URL structure
		$gallery_slug = get_option('brag_book_gallery_page_slug', 'gallery' );
		// Handle legacy array format
		if ( is_array( $gallery_slug ) ) {
			$gallery_slug = $gallery_slug[0] ?? 'gallery';
		}

		$procedures_args = [
			'labels'                     => $procedures_labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'show_in_menu'               => false,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
			'show_in_rest'               => true,
			'rewrite' => array(
				'slug' => $gallery_slug,
				'with_front' => false,
				'hierarchical' => false
			),
		];

		register_taxonomy( self::TAXONOMY_PROCEDURES, [ 'brag_book_cases' ], $procedures_args );
	}

	public function custom_procedure_term_link( $link, $term, $taxonomy ): ?string {
		if ($taxonomy === self::TAXONOMY_PROCEDURES) {
			$gallery_slug_option = get_option('brag_book_gallery_page_slug', 'gallery');
			// Handle legacy array format
			$gallery_slug = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;
			$link = home_url('/' . $gallery_slug . '/' . $term->slug . '/');
		}
		return $link;
	}

	/**
	 * Enqueue admin scripts for procedure taxonomy pages
	 *
	 * Loads WordPress media scripts on taxonomy administration pages
	 * to enable banner image selection functionality.
	 *
	 * @since 3.0.0
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_procedure_admin_scripts( string $hook_suffix ): void {
		// Check if we're on the procedures taxonomy pages
		if ( 'edit-tags.php' === $hook_suffix || 'term.php' === $hook_suffix ) {
			$screen = get_current_screen();
			if ( $screen && self::TAXONOMY_PROCEDURES === $screen->taxonomy ) {
				// Enqueue WordPress media scripts
				wp_enqueue_media();
				wp_enqueue_script( 'jquery' );
			}
		}
	}

	/**
	 * Add meta fields to procedure add form
	 *
	 * Renders additional form fields for procedure metadata including
	 * procedure ID, member ID, nudity flag, and banner image selection.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_procedure_meta_fields(): void {
		wp_nonce_field( 'save_procedure_meta', 'procedure_meta_nonce' );
		?>
		<div class="form-field">
			<label for="procedure_id"><?php esc_html_e( 'Procedure ID', 'brag-book-gallery' ); ?></label>
			<input type="number" name="procedure_id" id="procedure_id" value="" />
			<p class="description"><?php esc_html_e( 'Unique procedure ID from the BRAGBook API', 'brag-book-gallery' ); ?></p>
		</div>

		<div class="form-field">
			<label for="member_id"><?php esc_html_e( 'Member ID', 'brag-book-gallery' ); ?></label>
			<input type="text" name="member_id" id="member_id" value="" />
			<p class="description"><?php esc_html_e( 'Member/Doctor ID associated with this procedure', 'brag-book-gallery' ); ?></p>
		</div>

		<div class="form-field">
			<label for="procedure_order"><?php esc_html_e( 'Order', 'brag-book-gallery' ); ?></label>
			<input type="number" name="procedure_order" id="procedure_order" value="0" min="0" />
			<p class="description"><?php esc_html_e( 'Order for displaying this procedure (lower numbers appear first)', 'brag-book-gallery' ); ?></p>
		</div>

		<div class="form-field">
			<label for="nudity"><?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?></label>
			<div class="procedure-nudity-toggle">
				<label class="toggle-switch">
					<input type="checkbox" name="nudity" id="nudity" value="true" />
					<span class="toggle-slider"></span>
				</label>
				<span class="toggle-label"><?php esc_html_e( 'This procedure typically involves nudity in before/after photos', 'brag-book-gallery' ); ?></span>
			</div>
		</div>

		<div class="form-field">
			<label for="banner_image"><?php esc_html_e( 'Banner Image', 'brag-book-gallery' ); ?></label>
			<input type="hidden" id="banner_image" name="banner_image" value="" />
			<button type="button" class="button" id="upload_banner_image_button">
				<?php esc_html_e( 'Choose Banner Image', 'brag-book-gallery' ); ?>
			</button>
			<button type="button" class="button" id="remove_banner_image_button" style="display:none;">
				<?php esc_html_e( 'Remove Image', 'brag-book-gallery' ); ?>
			</button>
			<div id="banner_image_preview" style="margin-top: 10px;"></div>
			<p class="description"><?php esc_html_e( 'Banner image displayed for this procedure', 'brag-book-gallery' ); ?></p>
		</div>

		<div class="form-field">
			<label for="brag_book_gallery_details"><?php esc_html_e( 'Gallery Details', 'brag-book-gallery' ); ?></label>
			<textarea name="brag_book_gallery_details" id="brag_book_gallery_details" rows="5" cols="50"></textarea>
			<p class="description"><?php esc_html_e( 'Additional details and information about this procedure for the gallery display. Supports shortcodes.', 'brag-book-gallery' ); ?></p>
		</div>

		<style>
		.procedure-nudity-toggle {
			display: flex;
			align-items: center;
			gap: 12px;
			margin: 0;
		}

		.toggle-switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 24px;
			margin: 0;
			flex-shrink: 0;
		}

		.toggle-switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}

		.toggle-slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: .4s;
			border-radius: 24px;
		}

		.toggle-slider:before {
			position: absolute;
			content: "";
			height: 18px;
			width: 18px;
			left: 3px;
			bottom: 3px;
			background-color: white;
			transition: .4s;
			border-radius: 50%;
		}

		input:checked + .toggle-slider {
			background-color: #2196F3;
		}

		input:focus + .toggle-slider {
			box-shadow: 0 0 1px #2196F3;
		}

		input:checked + .toggle-slider:before {
			transform: translateX(26px);
		}

		.toggle-label {
			font-size: 13px;
			color: #666;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			var mediaUploader;

			$('#upload_banner_image_button').click(function(e) {
				e.preventDefault();
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}
				mediaUploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose Banner Image',
					button: {
						text: 'Choose Image'
					}, multiple: false });
				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#banner_image').val(attachment.id);
					$('#banner_image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" />');
					$('#remove_banner_image_button').show();
				});
				mediaUploader.open();
			});

			$('#remove_banner_image_button').click(function(e) {
				e.preventDefault();
				$('#banner_image').val('');
				$('#banner_image_preview').html('');
				$(this).hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * Add meta fields to procedure edit form
	 *
	 * Renders editable form fields for procedure metadata on the
	 * taxonomy edit page with pre-populated values.
	 *
	 * @since 3.0.0
	 * @param \WP_Term $term The term being edited.
	 * @return void
	 */
	public function edit_procedure_meta_fields( \WP_Term $term ): void {
		// Get existing values
		$procedure_id = get_term_meta( $term->term_id, 'procedure_id', true );
		$member_id = get_term_meta( $term->term_id, 'member_id', true );
		$procedure_order = get_term_meta( $term->term_id, 'procedure_order', true );
		$nudity = get_term_meta( $term->term_id, 'nudity', true );
		$banner_image_id = get_term_meta( $term->term_id, 'banner_image', true );
		$gallery_details = get_term_meta( $term->term_id, 'brag_book_gallery_details', true );

		wp_nonce_field( 'save_procedure_meta', 'procedure_meta_nonce' );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="procedure_id"><?php esc_html_e( 'Procedure ID', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="procedure_id" id="procedure_id" value="<?php echo esc_attr( $procedure_id ); ?>" />
				<p class="description"><?php esc_html_e( 'Unique procedure ID from the BRAGBook API', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="member_id"><?php esc_html_e( 'Member ID', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="text" name="member_id" id="member_id" value="<?php echo esc_attr( $member_id ); ?>" />
				<p class="description"><?php esc_html_e( 'Member/Doctor ID associated with this procedure', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="procedure_order"><?php esc_html_e( 'Order', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="procedure_order" id="procedure_order" value="<?php echo esc_attr( $procedure_order ); ?>" min="0" />
				<p class="description"><?php esc_html_e( 'Order for displaying this procedure (lower numbers appear first)', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="nudity"><?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<div class="procedure-nudity-toggle">
					<label class="toggle-switch">
						<input type="checkbox" name="nudity" id="nudity" value="true" <?php checked( $nudity, 'true' ); ?> />
						<span class="toggle-slider"></span>
					</label>
					<span class="toggle-label"><?php esc_html_e( 'This procedure typically involves nudity in before/after photos', 'brag-book-gallery' ); ?></span>
				</div>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="banner_image"><?php esc_html_e( 'Banner Image', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="hidden" id="banner_image" name="banner_image" value="<?php echo esc_attr( $banner_image_id ); ?>" />
				<button type="button" class="button" id="upload_banner_image_button">
					<?php esc_html_e( 'Choose Banner Image', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button" id="remove_banner_image_button" style="<?php echo empty( $banner_image_id ) ? 'display:none;' : ''; ?>">
					<?php esc_html_e( 'Remove Image', 'brag-book-gallery' ); ?>
				</button>
				<div id="banner_image_preview" style="margin-top: 10px;">
					<?php if ( $banner_image_id ) : ?>
						<?php echo wp_get_attachment_image( $banner_image_id, 'medium' ); ?>
					<?php endif; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Banner image displayed for this procedure', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="brag_book_gallery_details"><?php esc_html_e( 'Gallery Details', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<textarea name="brag_book_gallery_details" id="brag_book_gallery_details" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $gallery_details ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Additional details and information about this procedure for the gallery display. Supports shortcodes.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>

		<?php
		// Get case order list for display
		$case_order_list = get_term_meta( $term->term_id, 'brag_book_gallery_case_order_list', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label><?php esc_html_e( 'Case Ordering', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<?php if ( is_array( $case_order_list ) && ! empty( $case_order_list ) ) : ?>
					<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
						<p><strong><?php echo count( $case_order_list ); ?> cases in API order:</strong></p>
						<div style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;">
							<?php
							// Display cases in order
							foreach ( $case_order_list as $case_data ) {
								// Handle both new format (array) and legacy format (string/int)
								if ( is_array( $case_data ) ) {
									$wp_id = $case_data['wp_id'] ?? 'N/A';
									$api_id = $case_data['api_id'] ?? 'N/A';
									echo sprintf(
										'<div>WordPress ID: %s | API Case ID: %s</div>',
										esc_html( $wp_id ),
										esc_html( $api_id )
									);
								} else {
									// Legacy format (just API ID)
									echo sprintf(
										'<div>API Case ID: %s</div>',
										esc_html( $case_data )
									);
								}
							}
							?>
						</div>
					</div>
				<?php else : ?>
					<div style="background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">
						<strong>No case ordering data found.</strong><br>
						Case ordering is automatically populated when cases are synced from the API.
						Run a sync to see case ordering information here.
					</div>
				<?php endif; ?>
				<p class="description">
					<?php esc_html_e( 'Shows the order in which cases appear for this procedure based on the API response. This is automatically managed during sync operations and cannot be manually edited.', 'brag-book-gallery' ); ?>
				</p>
			</td>
		</tr>

		<style>
		.procedure-nudity-toggle {
			display: flex;
			align-items: center;
			gap: 12px;
			margin: 0;
		}

		.toggle-switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 24px;
			margin: 0;
			flex-shrink: 0;
		}

		.toggle-switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}

		.toggle-slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: .4s;
			border-radius: 24px;
		}

		.toggle-slider:before {
			position: absolute;
			content: "";
			height: 18px;
			width: 18px;
			left: 3px;
			bottom: 3px;
			background-color: white;
			transition: .4s;
			border-radius: 50%;
		}

		input:checked + .toggle-slider {
			background-color: #2196F3;
		}

		input:focus + .toggle-slider {
			box-shadow: 0 0 1px #2196F3;
		}

		input:checked + .toggle-slider:before {
			transform: translateX(26px);
		}

		.toggle-label {
			font-size: 13px;
			color: #666;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			var mediaUploader;

			$('#upload_banner_image_button').click(function(e) {
				e.preventDefault();
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}
				mediaUploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose Banner Image',
					button: {
						text: 'Choose Image'
					}, multiple: false });
				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#banner_image').val(attachment.id);
					$('#banner_image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" />');
					$('#remove_banner_image_button').show();
				});
				mediaUploader.open();
			});

			$('#remove_banner_image_button').click(function(e) {
				e.preventDefault();
				$('#banner_image').val('');
				$('#banner_image_preview').html('');
				$(this).hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save procedure meta data
	 *
	 * Handles saving of custom meta fields for procedure taxonomies
	 * with proper nonce verification and capability checks.
	 *
	 * @since 3.0.0
	 * @param int $term_id The term ID being saved.
	 * @return void
	 */
	public function save_procedure_meta( int $term_id ): void {
		// Verify nonce
		if ( ! isset( $_POST['procedure_meta_nonce'] ) || ! wp_verify_nonce( $_POST['procedure_meta_nonce'], 'save_procedure_meta' ) ) {
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Save procedure ID
		if ( isset( $_POST['procedure_id'] ) ) {
			$procedure_id = sanitize_text_field( $_POST['procedure_id'] );
			update_term_meta( $term_id, 'procedure_id', $procedure_id );
		}

		// Save member ID
		if ( isset( $_POST['member_id'] ) ) {
			$member_id = sanitize_text_field( $_POST['member_id'] );
			update_term_meta( $term_id, 'member_id', $member_id );
		}

		// Save procedure order
		if ( isset( $_POST['procedure_order'] ) ) {
			$procedure_order = absint( $_POST['procedure_order'] );
			update_term_meta( $term_id, 'procedure_order', $procedure_order );
		}

		// Save nudity flag
		$nudity = isset( $_POST['nudity'] ) && 'true' === $_POST['nudity'] ? 'true' : 'false';
		update_term_meta( $term_id, 'nudity', $nudity );

		// Save banner image
		if ( isset( $_POST['banner_image'] ) ) {
			$banner_image_id = absint( $_POST['banner_image'] );
			if ( $banner_image_id ) {
				update_term_meta( $term_id, 'banner_image', $banner_image_id );
			} else {
				delete_term_meta( $term_id, 'banner_image' );
			}
		}

		// Save gallery details
		if ( isset( $_POST['brag_book_gallery_details'] ) ) {
			$gallery_details = wp_kses_post( $_POST['brag_book_gallery_details'] );
			if ( ! empty( $gallery_details ) ) {
				update_term_meta( $term_id, 'brag_book_gallery_details', $gallery_details );
			} else {
				delete_term_meta( $term_id, 'brag_book_gallery_details' );
			}
		}
	}

	/**
	 * Add custom columns to procedure taxonomy table
	 *
	 * Adds columns for displaying procedure metadata in the
	 * taxonomy administration table.
	 *
	 * @since 3.0.0
	 * @param array $columns Existing columns array.
	 * @return array Modified columns array.
	 */
	public function add_procedure_columns( array $columns ): array {
		$new_columns = [];

		// Keep existing columns up to 'posts'
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'posts' === $key ) {
				$new_columns['procedure_id'] = __( 'Procedure ID', 'brag-book-gallery' );
				$new_columns['member_id'] = __( 'Member ID', 'brag-book-gallery' );
				$new_columns['procedure_order'] = __( 'Order', 'brag-book-gallery' );
				$new_columns['banner_image'] = __( 'Banner Image', 'brag-book-gallery' );
				$new_columns['nudity'] = __( 'Contains Nudity', 'brag-book-gallery' );
			}
		}
		return $new_columns;
	}

	/**
	 * Add content to custom procedure columns
	 *
	 * Displays the meta data content in the custom columns.
	 *
	 * @since 3.0.0
	 * @param string $content The column content (empty by default).
	 * @param string $column_name The column name.
	 * @param int    $term_id The term ID.
	 * @return string The column content.
	 */
	public function add_procedure_column_content( string $content, string $column_name, int $term_id ): string {
		switch ( $column_name ) {
			case 'banner_image':
				$banner_image_id = get_term_meta( $term_id, 'banner_image', true );
				if ( $banner_image_id ) {
					$content = wp_get_attachment_image( $banner_image_id, [ 50, 50 ] );
				} else {
					$content = '—';
				}
				break;

			case 'procedure_id':
				$procedure_id = get_term_meta( $term_id, 'procedure_id', true );
				$content = $procedure_id ?: '—';
				break;

			case 'member_id':
				$member_id = get_term_meta( $term_id, 'member_id', true );
				$content = $member_id ?: '—';
				break;

			case 'procedure_order':
				$procedure_order = get_term_meta( $term_id, 'procedure_order', true );
				$content = '' !== $procedure_order ? $procedure_order : '0';
				break;

			case 'nudity':
				$nudity = get_term_meta( $term_id, 'nudity', true );
				$content = 'true' === $nudity ? __( 'Yes', 'brag-book-gallery' ) : __( 'No', 'brag-book-gallery' );
				break;
		}

		return $content;
	}

	/**
	 * Helper method to get procedure meta
	 *
	 * Retrieves all meta data for a given procedure term.
	 *
	 * @since 3.0.0
	 * @param int $term_id The term ID.
	 * @return array Array of meta data.
	 */
	public function get_procedure_meta( int $term_id ): array {
		return [
			'procedure_id'  => get_term_meta( $term_id, 'procedure_id', true ),
			'member_id'     => get_term_meta( $term_id, 'member_id', true ),
			'nudity'        => get_term_meta( $term_id, 'nudity', true ),
			'banner_image'  => get_term_meta( $term_id, 'banner_image', true ),
		];
	}

	/**
	 * Helper method to save procedure meta data
	 *
	 * Programmatically save procedure meta data from an array.
	 *
	 * @since 3.0.0
	 * @param int   $term_id The term ID.
	 * @param array $meta_data Array of meta data to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_procedure_meta_data( int $term_id, array $meta_data ): bool {
		if ( ! term_exists( $term_id ) ) {
			return false;
		}

		foreach ( $meta_data as $key => $value ) {
			switch ( $key ) {
				case 'procedure_id':
				case 'member_id':
					update_term_meta( $term_id, $key, sanitize_text_field( $value ) );
					break;
				case 'nudity':
					$nudity_value = ( 'true' === $value || true === $value ) ? 'true' : 'false';
					update_term_meta( $term_id, $key, $nudity_value );
					break;
				case 'banner_image':
					$image_id = absint( $value );
					if ( $image_id ) {
						update_term_meta( $term_id, $key, $image_id );
					} else {
						delete_term_meta( $term_id, $key );
					}
					break;
			}
		}

		return true;
	}

	/**
	 * Check if rewrites need to be flushed and flush them if needed
	 *
	 * This method runs once after taxonomy registration changes to ensure
	 * WordPress recognizes the new taxonomy rewrite rules.
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function maybe_flush_rewrites(): void {
		$option_key = 'brag_book_taxonomy_version';
		$current_version = '3.3.0_brag_book_procedures';
		$saved_version = get_option( $option_key, '' );

		// If the taxonomy version has changed, flush rewrites
		if ( $saved_version !== $current_version ) {
			flush_rewrite_rules( false );
			update_option( $option_key, $current_version );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook: Flushed rewrite rules after taxonomy update to ' . $current_version );
			}
		}
	}
}
