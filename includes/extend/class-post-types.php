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

// Exit if accessed directly.
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
	 * Cases post type slug
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const POST_TYPE_CASES = 'brag_book_cases';


	/**
	 * Constructor - Register WordPress hooks
	 *
	 * Sets up the necessary WordPress hooks to register custom post types
	 * at the appropriate times.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_case_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_case_meta' ] );
		add_action( 'before_delete_post', [
			$this,
			'cleanup_case_images_before_delete'
		] );
		add_action( 'wp_trash_post', [
			$this,
			'cleanup_case_images_on_trash'
		] );

		add_filter( 'post_type_link', [
			$this,
			'custom_brag_book_cases_permalink'
		], 10, 2 );

		// Bulk actions support
		add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE_CASES, [
			$this,
			'handle_bulk_delete_cases'
		], 10, 3 );
		add_action( 'pre_delete_post', [
			$this,
			'cleanup_case_images_pre_delete'
		], 10, 2 );
		add_action( 'admin_notices', [ $this, 'show_bulk_delete_notices' ] );

		// Media library enhancements
		add_action( 'restrict_manage_posts', [
			$this,
			'add_media_library_filter'
		] );
		add_filter( 'parse_query', [ $this, 'filter_media_library_query' ] );
		add_filter( 'wp_prepare_attachment_for_js', [
			$this,
			'add_brag_book_marker_to_attachment'
		], 10, 3 );
		add_action( 'admin_head', [ $this, 'add_media_library_styles' ] );
	}

	/**
	 * Register custom post types
	 *
	 * Registers the Cases custom post type with appropriate labels,
	 * capabilities, and features for managing case studies.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function register_post_types(): void {
		// Cases post type
		$cases_labels = [
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
		];

		$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		// Handle legacy array format
		$gallery_slug = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;

		$cases_args = [
			'labels'             => $cases_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $gallery_slug . '/%procedures%',
				'with_front' => false
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-camera-alt',
			'supports'           => [
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields'
			],
			'show_in_rest'       => true,
			'taxonomies'         => [ 'procedures' ],
		];

		register_post_type( self::POST_TYPE_CASES, $cases_args );
	}

	public function custom_brag_book_cases_permalink( $post_link, $post ) {
		if ( $post->post_type === 'brag_book_cases' ) {
			$gallery_slug_option = get_option( 'brag_book_gallery_page_slug', 'gallery' );
			// Handle legacy array format
			$gallery_slug = is_array( $gallery_slug_option ) ? ( $gallery_slug_option[0] ?? 'gallery' ) : $gallery_slug_option;
			$terms        = wp_get_object_terms( $post->ID, 'procedures' );

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
	 * Add meta boxes for Cases post type
	 *
	 * Adds custom meta boxes to the Cases edit screen for additional
	 * case information like patient details and procedure specifics.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function add_case_meta_boxes(): void {
		add_meta_box(
			'case_details',
			__( 'Case Details', 'brag-book-gallery' ),
			[ $this, 'render_case_details_meta_box' ],
			self::POST_TYPE_CASES,
			'normal',
			'high'
		);

		add_meta_box(
			'case_api_data',
			__( 'API Case Data', 'brag-book-gallery' ),
			[ $this, 'render_case_api_data_meta_box' ],
			self::POST_TYPE_CASES,
			'normal',
			'default'
		);

		add_meta_box(
			'case_images',
			__( 'Additional Images', 'brag-book-gallery' ),
			[ $this, 'render_case_images_meta_box' ],
			self::POST_TYPE_CASES,
			'side',
			'default'
		);

		add_meta_box(
			'case_procedure_details',
			__( 'Procedure Details', 'brag-book-gallery' ),
			[ $this, 'render_procedure_details_meta_box' ],
			self::POST_TYPE_CASES,
			'normal',
			'default'
		);
	}

	/**
	 * Render case details meta box
	 *
	 * Displays form fields for additional case information such as
	 * patient age, gender, procedure date, and case notes.
	 *
	 * @param \WP_Post $post The current post object.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_case_details_meta_box( \WP_Post $post ): void {
		// Add nonce for security
		wp_nonce_field( 'save_case_details', 'case_details_nonce' );

		// Get existing values
		$patient_age     = get_post_meta( $post->ID, '_case_patient_age', true );
		$patient_gender  = get_post_meta( $post->ID, '_case_patient_gender', true );
		$procedure_date  = get_post_meta( $post->ID, '_case_procedure_date', true );
		$case_notes      = get_post_meta( $post->ID, '_case_notes', true );
		$before_image_id = get_post_meta( $post->ID, '_case_before_image', true );
		$after_image_id  = get_post_meta( $post->ID, '_case_after_image', true );

		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label
						for="case_patient_age"><?php esc_html_e( 'Patient Age', 'brag-book-gallery' ); ?></label>
				</th>
				<td>
					<input type="number"
						   id="case_patient_age"
						   name="case_patient_age"
						   value="<?php echo esc_attr( $patient_age ); ?>"
						   min="18"
						   max="100"
						   class="small-text"/>
					<p class="description"><?php esc_html_e( 'Patient age at time of procedure', 'brag-book-gallery' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label
						for="case_patient_gender"><?php esc_html_e( 'Patient Gender', 'brag-book-gallery' ); ?></label>
				</th>
				<td>
					<select id="case_patient_gender" name="case_patient_gender">
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
						for="case_procedure_date"><?php esc_html_e( 'Procedure Date', 'brag-book-gallery' ); ?></label>
				</th>
				<td>
					<input type="date"
						   id="case_procedure_date"
						   name="case_procedure_date"
						   value="<?php echo esc_attr( $procedure_date ); ?>"/>
					<p class="description"><?php esc_html_e( 'Date when the procedure was performed', 'brag-book-gallery' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label
						for="case_before_image"><?php esc_html_e( 'Before Image', 'brag-book-gallery' ); ?></label>
				</th>
				<td>
					<input type="hidden" id="case_before_image"
						   name="case_before_image"
						   value="<?php echo esc_attr( $before_image_id ); ?>"/>
					<button type="button" class="button"
							id="upload_before_image_button">
						<?php esc_html_e( 'Choose Before Image', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button"
							id="remove_before_image_button"
							style="<?php echo empty( $before_image_id ) ? 'display:none;' : ''; ?>">
						<?php esc_html_e( 'Remove Image', 'brag-book-gallery' ); ?>
					</button>
					<div id="before_image_preview" style="margin-top: 10px;">
						<?php if ( $before_image_id ) : ?>
							<?php echo wp_get_attachment_image( $before_image_id, 'medium' ); ?>
						<?php endif; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label
						for="case_after_image"><?php esc_html_e( 'After Image', 'brag-book-gallery' ); ?></label>
				</th>
				<td>
					<input type="hidden" id="case_after_image"
						   name="case_after_image"
						   value="<?php echo esc_attr( $after_image_id ); ?>"/>
					<button type="button" class="button"
							id="upload_after_image_button">
						<?php esc_html_e( 'Choose After Image', 'brag-book-gallery' ); ?>
					</button>
					<button type="button" class="button"
							id="remove_after_image_button"
							style="<?php echo empty( $after_image_id ) ? 'display:none;' : ''; ?>">
						<?php esc_html_e( 'Remove Image', 'brag-book-gallery' ); ?>
					</button>
					<div id="after_image_preview" style="margin-top: 10px;">
						<?php if ( $after_image_id ) : ?>
							<?php echo wp_get_attachment_image( $after_image_id, 'medium' ); ?>
						<?php endif; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label
						for="case_notes"><?php esc_html_e( 'Case Notes', 'brag-book-gallery' ); ?></label>
				</th>
				<td>
							<textarea id="case_notes"
									  name="case_notes"
									  rows="5"
									  cols="50"
									  class="large-text"><?php echo esc_textarea( $case_notes ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Additional notes about this case', 'brag-book-gallery' ); ?></p>
				</td>
			</tr>
		</table>

		<script>
			jQuery( document ).ready( function ( $ ) {
				// Media uploader for before image
				$( '#upload_before_image_button' ).click( function ( e ) {
					e.preventDefault();
					var mediaUploader = wp.media( {
						title: '<?php esc_html_e( 'Choose Before Image', 'brag-book-gallery' ); ?>',
						button: {
							text: '<?php esc_html_e( 'Choose Image', 'brag-book-gallery' ); ?>'
						},
						multiple: false
					} );

					mediaUploader.on( 'select', function () {
						var attachment = mediaUploader.state().get( 'selection' ).first().toJSON();
						$( '#case_before_image' ).val( attachment.id );
						$( '#before_image_preview' ).html( '<img src="' + attachment.sizes.medium.url + '" style="max-width: 300px;" />' );
						$( '#remove_before_image_button' ).show();
					} );

					mediaUploader.open();
				} );

				$( '#remove_before_image_button' ).click( function ( e ) {
					e.preventDefault();
					$( '#case_before_image' ).val( '' );
					$( '#before_image_preview' ).html( '' );
					$( this ).hide();
				} );

				// Media uploader for after image
				$( '#upload_after_image_button' ).click( function ( e ) {
					e.preventDefault();
					var mediaUploader = wp.media( {
						title: '<?php esc_html_e( 'Choose After Image', 'brag-book-gallery' ); ?>',
						button: {
							text: '<?php esc_html_e( 'Choose Image', 'brag-book-gallery' ); ?>'
						},
						multiple: false
					} );

					mediaUploader.on( 'select', function () {
						var attachment = mediaUploader.state().get( 'selection' ).first().toJSON();
						$( '#case_after_image' ).val( attachment.id );
						$( '#after_image_preview' ).html( '<img src="' + attachment.sizes.medium.url + '" style="max-width: 300px;" />' );
						$( '#remove_after_image_button' ).show();
					} );

					mediaUploader.open();
				} );

				$( '#remove_after_image_button' ).click( function ( e ) {
					e.preventDefault();
					$( '#case_after_image' ).val( '' );
					$( '#after_image_preview' ).html( '' );
					$( this ).hide();
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Render case API data meta box
	 *
	 * Displays form fields for API-related case information such as
	 * API case ID, organization data, quality scores, and approval status.
	 *
	 * @param \WP_Post $post The current post object.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_case_api_data_meta_box( \WP_Post $post ): void {
		// Add nonce for security
		wp_nonce_field( 'save_case_api_data', 'case_api_data_nonce' );

		// Get existing values
		$api_case_id          = get_post_meta( $post->ID, '_case_api_id', true );
		$patient_id           = get_post_meta( $post->ID, '_case_patient_id', true );
		$user_id              = get_post_meta( $post->ID, '_case_user_id', true );
		$org_id               = get_post_meta( $post->ID, '_case_org_id', true );
		$emr_id               = get_post_meta( $post->ID, '_case_emr_id', true );
		$ethnicity            = get_post_meta( $post->ID, '_case_ethnicity', true );
		$height               = get_post_meta( $post->ID, '_case_height', true );
		$height_unit          = get_post_meta( $post->ID, '_case_height_unit', true );
		$weight               = get_post_meta( $post->ID, '_case_weight', true );
		$weight_unit          = get_post_meta( $post->ID, '_case_weight_unit', true );
		$procedure_ids        = get_post_meta( $post->ID, '_case_procedure_ids', true );
		$technique            = get_post_meta( $post->ID, '_case_technique', true );
		$revision_surgery     = get_post_meta( $post->ID, '_case_revision_surgery', true );
		$quality_score        = get_post_meta( $post->ID, '_case_quality_score', true );
		$approved_for_social  = get_post_meta( $post->ID, '_case_approved_for_social', true );
		$is_for_tablet        = get_post_meta( $post->ID, '_case_is_for_tablet', true );
		$is_for_website       = get_post_meta( $post->ID, '_case_is_for_website', true );
		$draft                = get_post_meta( $post->ID, '_case_draft', true );
		$no_watermark         = get_post_meta( $post->ID, '_case_no_watermark', true );
		$is_nude              = get_post_meta( $post->ID, '_case_is_nude', true );
		$after1_timeframe     = get_post_meta( $post->ID, '_case_after1_timeframe', true );
		$after1_unit          = get_post_meta( $post->ID, '_case_after1_unit', true );
		$after2_timeframe     = get_post_meta( $post->ID, '_case_after2_timeframe', true );
		$after2_unit          = get_post_meta( $post->ID, '_case_after2_unit', true );
		$seo_suffix_url       = get_post_meta( $post->ID, '_case_seo_suffix_url', true );
		$seo_headline         = get_post_meta( $post->ID, '_case_seo_headline', true );
		$seo_page_title       = get_post_meta( $post->ID, '_case_seo_page_title', true );
		$seo_page_description = get_post_meta( $post->ID, '_case_seo_page_description', true );
		$seo_alt_text         = get_post_meta( $post->ID, '_case_seo_alt_text', true );

		// Photo URLs
		$before_location_url               = get_post_meta( $post->ID, '_case_before_location_url', true );
		$after_location_url1               = get_post_meta( $post->ID, '_case_after_location_url1', true );
		$after_location_url2               = get_post_meta( $post->ID, '_case_after_location_url2', true );
		$after_location_url3               = get_post_meta( $post->ID, '_case_after_location_url3', true );
		$post_processed_image_url          = get_post_meta( $post->ID, '_case_post_processed_image_url', true );
		$high_res_post_processed_image_url = get_post_meta( $post->ID, '_case_high_res_post_processed_image_url', true );

		?>
		<div class="brag-book-api-data-tabs">
			<nav class="nav-tab-wrapper">
				<a href="#api-basic"
				   class="nav-tab nav-tab-active"><?php esc_html_e( 'Basic Info', 'brag-book-gallery' ); ?></a>
				<a href="#api-patient"
				   class="nav-tab"><?php esc_html_e( 'Patient Data', 'brag-book-gallery' ); ?></a>
				<a href="#api-settings"
				   class="nav-tab"><?php esc_html_e( 'Settings', 'brag-book-gallery' ); ?></a>
				<a href="#api-seo"
				   class="nav-tab"><?php esc_html_e( 'SEO', 'brag-book-gallery' ); ?></a>
				<a href="#api-images"
				   class="nav-tab"><?php esc_html_e( 'Image URLs', 'brag-book-gallery' ); ?></a>
			</nav>

			<div id="api-basic" class="tab-content active">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label
								for="case_api_id"><?php esc_html_e( 'API Case ID', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="number" id="case_api_id"
								   name="case_api_id"
								   value="<?php echo esc_attr( $api_case_id ); ?>"
								   class="regular-text"/>
							<p class="description"><?php esc_html_e( 'Unique case ID from the BRAGBook API', 'brag-book-gallery' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_patient_id"><?php esc_html_e( 'Patient ID', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_patient_id"
								   name="case_patient_id"
								   value="<?php echo esc_attr( $patient_id ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_user_id"><?php esc_html_e( 'User ID', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_user_id"
								   name="case_user_id"
								   value="<?php echo esc_attr( $user_id ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_org_id"><?php esc_html_e( 'Organization ID', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_org_id"
								   name="case_org_id"
								   value="<?php echo esc_attr( $org_id ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_emr_id"><?php esc_html_e( 'EMR ID', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_emr_id"
								   name="case_emr_id"
								   value="<?php echo esc_attr( $emr_id ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_procedure_ids"><?php esc_html_e( 'Procedure IDs', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_procedure_ids"
								   name="case_procedure_ids"
								   value="<?php echo esc_attr( $procedure_ids ); ?>"
								   class="regular-text"/>
							<p class="description"><?php esc_html_e( 'Comma-separated list of procedure IDs', 'brag-book-gallery' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div id="api-patient" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label
								for="case_ethnicity"><?php esc_html_e( 'Ethnicity', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_ethnicity"
								   name="case_ethnicity"
								   value="<?php echo esc_attr( $ethnicity ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_height"><?php esc_html_e( 'Height', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="number" id="case_height"
								   name="case_height"
								   value="<?php echo esc_attr( $height ); ?>"
								   class="small-text"/>
							<select id="case_height_unit"
									name="case_height_unit">
								<option
									value="inches" <?php selected( $height_unit, 'inches' ); ?>>
									Inches
								</option>
								<option
									value="cm" <?php selected( $height_unit, 'cm' ); ?>>
									Centimeters
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_weight"><?php esc_html_e( 'Weight', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="number" id="case_weight"
								   name="case_weight"
								   value="<?php echo esc_attr( $weight ); ?>"
								   class="small-text"/>
							<select id="case_weight_unit"
									name="case_weight_unit">
								<option
									value="lbs" <?php selected( $weight_unit, 'lbs' ); ?>>
									Pounds
								</option>
								<option
									value="kg" <?php selected( $weight_unit, 'kg' ); ?>>
									Kilograms
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_technique"><?php esc_html_e( 'Technique', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_technique"
								   name="case_technique"
								   value="<?php echo esc_attr( $technique ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_revision_surgery"><?php esc_html_e( 'Revision Surgery', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox"
									   id="case_revision_surgery"
									   name="case_revision_surgery"
									   value="1" <?php checked( $revision_surgery, '1' ); ?> />
								<?php esc_html_e( 'This is a revision surgery', 'brag-book-gallery' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_after1_timeframe"><?php esc_html_e( 'After Photo 1 Timeframe', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="number" id="case_after1_timeframe"
								   name="case_after1_timeframe"
								   value="<?php echo esc_attr( $after1_timeframe ); ?>"
								   class="small-text"/>
							<select id="case_after1_unit"
									name="case_after1_unit">
								<option
									value=""><?php esc_html_e( 'Select Unit', 'brag-book-gallery' ); ?></option>
								<option
									value="days" <?php selected( $after1_unit, 'days' ); ?>>
									Days
								</option>
								<option
									value="weeks" <?php selected( $after1_unit, 'weeks' ); ?>>
									Weeks
								</option>
								<option
									value="months" <?php selected( $after1_unit, 'months' ); ?>>
									Months
								</option>
								<option
									value="years" <?php selected( $after1_unit, 'years' ); ?>>
									Years
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_after2_timeframe"><?php esc_html_e( 'After Photo 2 Timeframe', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="number" id="case_after2_timeframe"
								   name="case_after2_timeframe"
								   value="<?php echo esc_attr( $after2_timeframe ); ?>"
								   class="small-text"/>
							<select id="case_after2_unit"
									name="case_after2_unit">
								<option
									value=""><?php esc_html_e( 'Select Unit', 'brag-book-gallery' ); ?></option>
								<option
									value="days" <?php selected( $after2_unit, 'days' ); ?>>
									Days
								</option>
								<option
									value="weeks" <?php selected( $after2_unit, 'weeks' ); ?>>
									Weeks
								</option>
								<option
									value="months" <?php selected( $after2_unit, 'months' ); ?>>
									Months
								</option>
								<option
									value="years" <?php selected( $after2_unit, 'years' ); ?>>
									Years
								</option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<div id="api-settings" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label
								for="case_quality_score"><?php esc_html_e( 'Quality Score', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="number" id="case_quality_score"
								   name="case_quality_score"
								   value="<?php echo esc_attr( $quality_score ); ?>"
								   min="0" max="100" class="small-text"/>
							<p class="description"><?php esc_html_e( 'Quality score from 0-100', 'brag-book-gallery' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Approval & Settings', 'brag-book-gallery' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="case_approved_for_social"
									   value="1" <?php checked( $approved_for_social, '1' ); ?> />
								<?php esc_html_e( 'Approved for Social Media', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="case_is_for_tablet"
									   value="1" <?php checked( $is_for_tablet, '1' ); ?> />
								<?php esc_html_e( 'Available for Tablet', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox"
									   name="case_is_for_website"
									   value="1" <?php checked( $is_for_website, '1' ); ?> />
								<?php esc_html_e( 'Available for Website', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="case_draft"
									   value="1" <?php checked( $draft, '1' ); ?> />
								<?php esc_html_e( 'Draft Status', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="case_no_watermark"
									   value="1" <?php checked( $no_watermark, '1' ); ?> />
								<?php esc_html_e( 'No Watermark', 'brag-book-gallery' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="case_is_nude"
									   value="1" <?php checked( $is_nude, '1' ); ?> />
								<?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div id="api-seo" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label
								for="case_seo_suffix_url"><?php esc_html_e( 'SEO Suffix URL', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_seo_suffix_url"
								   name="case_seo_suffix_url"
								   value="<?php echo esc_attr( $seo_suffix_url ); ?>"
								   class="regular-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_seo_headline"><?php esc_html_e( 'SEO Headline', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_seo_headline"
								   name="case_seo_headline"
								   value="<?php echo esc_attr( $seo_headline ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_seo_page_title"><?php esc_html_e( 'SEO Page Title', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_seo_page_title"
								   name="case_seo_page_title"
								   value="<?php echo esc_attr( $seo_page_title ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_seo_page_description"><?php esc_html_e( 'SEO Page Description', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
									<textarea id="case_seo_page_description"
											  name="case_seo_page_description"
											  rows="3"
											  class="large-text"><?php echo esc_textarea( $seo_page_description ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_seo_alt_text"><?php esc_html_e( 'SEO Alt Text', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="case_seo_alt_text"
								   name="case_seo_alt_text"
								   value="<?php echo esc_attr( $seo_alt_text ); ?>"
								   class="large-text"/>
						</td>
					</tr>
				</table>
			</div>

			<div id="api-images" class="tab-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label
								for="case_before_location_url"><?php esc_html_e( 'Before Image URL', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="url" id="case_before_location_url"
								   name="case_before_location_url"
								   value="<?php echo esc_attr( $before_location_url ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_after_location_url1"><?php esc_html_e( 'After Image URL 1', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="url" id="case_after_location_url1"
								   name="case_after_location_url1"
								   value="<?php echo esc_attr( $after_location_url1 ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_after_location_url2"><?php esc_html_e( 'After Image URL 2', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="url" id="case_after_location_url2"
								   name="case_after_location_url2"
								   value="<?php echo esc_attr( $after_location_url2 ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_after_location_url3"><?php esc_html_e( 'After Image URL 3', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="url" id="case_after_location_url3"
								   name="case_after_location_url3"
								   value="<?php echo esc_attr( $after_location_url3 ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_post_processed_image_url"><?php esc_html_e( 'Post-Processed Image URL', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="url" id="case_post_processed_image_url"
								   name="case_post_processed_image_url"
								   value="<?php echo esc_attr( $post_processed_image_url ); ?>"
								   class="large-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label
								for="case_high_res_post_processed_image_url"><?php esc_html_e( 'High-Res Post-Processed Image URL', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="url"
								   id="case_high_res_post_processed_image_url"
								   name="case_high_res_post_processed_image_url"
								   value="<?php echo esc_attr( $high_res_post_processed_image_url ); ?>"
								   class="large-text"/>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<style>
			.brag-book-api-data-tabs .nav-tab-wrapper {
				border-bottom: 1px solid #ccc;
				margin-bottom: 20px;
			}

			.brag-book-api-data-tabs .tab-content {
				display: none;
			}

			.brag-book-api-data-tabs .tab-content.active {
				display: block;
			}
		</style>

		<script>
			jQuery( document ).ready( function ( $ ) {
				$( '.nav-tab' ).click( function ( e ) {
					e.preventDefault();
					var target = $( this ).attr( 'href' );

					// Remove active class from all tabs and content
					$( '.nav-tab' ).removeClass( 'nav-tab-active' );
					$( '.tab-content' ).removeClass( 'active' );

					// Add active class to clicked tab and corresponding content
					$( this ).addClass( 'nav-tab-active' );
					$( target ).addClass( 'active' );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Render case images meta box
	 *
	 * Displays interface for managing additional case images beyond
	 * the main before/after images.
	 *
	 * @param \WP_Post $post The current post object.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_case_images_meta_box( \WP_Post $post ): void {
		// Add nonce for security
		wp_nonce_field( 'save_case_images', 'case_images_nonce' );

		// Get existing gallery images
		$gallery_images = get_post_meta( $post->ID, '_case_gallery_images', true );
		if ( ! is_array( $gallery_images ) ) {
			$gallery_images = [];
		}

		?>
		<p><?php esc_html_e( 'Add additional images to showcase different angles or stages of the procedure.', 'brag-book-gallery' ); ?></p>

		<input type="hidden" id="case_gallery_images" name="case_gallery_images"
			   value="<?php echo esc_attr( implode( ',', $gallery_images ) ); ?>"/>

		<button type="button" class="button button-primary"
				id="add_gallery_images_button">
			<?php esc_html_e( 'Add Images', 'brag-book-gallery' ); ?>
		</button>

		<div id="gallery_images_preview" style="margin-top: 15px;">
			<?php foreach ( $gallery_images as $image_id ) : ?>
				<div class="gallery-image-item"
					 data-id="<?php echo esc_attr( $image_id ); ?>"
					 style="display: inline-block; margin: 5px; position: relative;">
					<?php echo wp_get_attachment_image( $image_id, 'thumbnail' ); ?>
					<button type="button" class="remove-gallery-image"
							style="position: absolute; top: 0; right: 0; background: red; color: white; border: none; width: 20px; height: 20px; cursor: pointer;">
						×
					</button>
				</div>
			<?php endforeach; ?>
		</div>

		<script>
			jQuery( document ).ready( function ( $ ) {
				$( '#add_gallery_images_button' ).click( function ( e ) {
					e.preventDefault();
					var mediaUploader = wp.media( {
						title: '<?php esc_html_e( 'Choose Images', 'brag-book-gallery' ); ?>',
						button: {
							text: '<?php esc_html_e( 'Add Images', 'brag-book-gallery' ); ?>'
						},
						multiple: true
					} );

					mediaUploader.on( 'select', function () {
						var selection = mediaUploader.state().get( 'selection' );
						var currentImages = $( '#case_gallery_images' ).val().split( ',' ).filter( function ( id ) {
							return id !== '';
						} );

						selection.each( function ( attachment ) {
							attachment = attachment.toJSON();
							if ( currentImages.indexOf( attachment.id.toString() ) === - 1 ) {
								currentImages.push( attachment.id );
								$( '#gallery_images_preview' ).append(
									'<div class="gallery-image-item" data-id="' + attachment.id + '" style="display: inline-block; margin: 5px; position: relative;">' +
									'<img src="' + attachment.sizes.thumbnail.url + '" />' +
									'<button type="button" class="remove-gallery-image" style="position: absolute; top: 0; right: 0; background: red; color: white; border: none; width: 20px; height: 20px; cursor: pointer;">×</button>' +
									'</div>'
								);
							}
						} );

						$( '#case_gallery_images' ).val( currentImages.join( ',' ) );
					} );

					mediaUploader.open();
				} );

				$( document ).on( 'click', '.remove-gallery-image', function ( e ) {
					e.preventDefault();
					var imageId = $( this ).parent().data( 'id' );
					var currentImages = $( '#case_gallery_images' ).val().split( ',' ).filter( function ( id ) {
						return id !== '' && id != imageId;
					} );
					$( '#case_gallery_images' ).val( currentImages.join( ',' ) );
					$( this ).parent().remove();
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Render procedure details meta box
	 *
	 * Displays procedure details from the API response in a readable format.
	 *
	 * @param \WP_Post $post The current post object.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function render_procedure_details_meta_box( \WP_Post $post ): void {
		$procedure_details = self::get_case_procedure_details( $post->ID );

		if ( empty( $procedure_details ) ) {
			echo '<p>' . esc_html__( 'No procedure details available from API.', 'brag-book-gallery' ) . '</p>';

			return;
		}

		echo '<div class="procedure-details-wrapper">';

		foreach ( $procedure_details as $procedure_id => $details ) {
			echo '<div class="procedure-detail-group" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">';
			echo '<h4 style="margin-top: 0; color: #23282d;">' . sprintf( esc_html__( 'Procedure ID: %s', 'brag-book-gallery' ), esc_html( $procedure_id ) ) . '</h4>';

			if ( is_array( $details ) ) {
				echo '<table class="form-table">';
				foreach ( $details as $detail_key => $detail_values ) {
					echo '<tr>';
					echo '<th scope="row" style="font-weight: 600;">' . esc_html( $detail_key ) . ':</th>';
					echo '<td>';

					if ( is_array( $detail_values ) ) {
						echo '<ul style="margin: 0; padding-left: 20px;">';
						foreach ( $detail_values as $value ) {
							echo '<li>' . esc_html( $value ) . '</li>';
						}
						echo '</ul>';
					} else {
						echo esc_html( $detail_values );
					}

					echo '</td>';
					echo '</tr>';
				}
				echo '</table>';
			}

			echo '</div>';
		}

		echo '</div>';

		// Show download status for images
		$api_downloaded_images = get_post_meta( $post->ID, '_case_api_downloaded_images', true );
		if ( ! empty( $api_downloaded_images ) ) {
			echo '<div style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
			echo '<h4 style="margin-top: 0;">' . esc_html__( 'Downloaded Images', 'brag-book-gallery' ) . '</h4>';
			echo '<p>' . sprintf(
					esc_html__( '%d images have been downloaded from the API and added to the Additional Images gallery.', 'brag-book-gallery' ),
					count( $api_downloaded_images )
				) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Clean up case images before permanent deletion
	 *
	 * Removes all associated images from media library before the case is permanently deleted.
	 * This hook fires before_delete_post to ensure we can still access meta data.
	 *
	 * @param int $post_id The post ID being deleted.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function cleanup_case_images_before_delete( int $post_id ): void {
		// Only process case posts
		if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
			return;
		}

		// Don't process if this is a revision or autosave
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->delete_all_case_images( $post_id );
	}

	/**
	 * Clean up case images when moved to trash
	 *
	 * Option to clean up images when case is trashed (can be disabled if you want to keep images).
	 * Images will be deleted permanently when the case is permanently deleted via before_delete_post.
	 *
	 * @param int $post_id The post ID being trashed.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function cleanup_case_images_on_trash( int $post_id ): void {
		// Only process case posts
		if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
			return;
		}

		// Check if we should delete images on trash (optional setting)
		$delete_on_trash = apply_filters( 'brag_book_gallery_delete_images_on_trash', false );

		if ( $delete_on_trash ) {
			$this->delete_all_case_images( $post_id );
		}
	}

	/**
	 * Delete all images associated with a case
	 *
	 * Removes API-downloaded images, gallery images, and featured images.
	 *
	 * @param int $post_id The case post ID.
	 *
	 * @return int Number of images deleted.
	 * @since 3.0.0
	 */
	private function delete_all_case_images( int $post_id ): int {
		// Delete API-downloaded images
		$api_images_deleted = self::delete_api_downloaded_images( $post_id );

		// Delete any remaining gallery images that weren't from API
		$gallery_images  = get_post_meta( $post_id, '_case_gallery_images', true );
		$gallery_deleted = 0;

		if ( is_array( $gallery_images ) ) {
			foreach ( $gallery_images as $attachment_id ) {
				// Check if this image belongs to this case
				$image_case_id = get_post_meta( $attachment_id, '_case_post_id', true );
				if ( $image_case_id == $post_id ) {
					if ( wp_delete_attachment( $attachment_id, true ) ) {
						$gallery_deleted ++;
					}
				}
			}
		}

		// Delete before/after images if they are attached to this case
		$before_after_deleted = 0;
		$before_image_id      = get_post_meta( $post_id, '_case_before_image', true );
		if ( $before_image_id ) {
			$image_case_id = get_post_meta( $before_image_id, '_case_post_id', true );
			if ( $image_case_id == $post_id ) {
				if ( wp_delete_attachment( $before_image_id, true ) ) {
					$before_after_deleted ++;
				}
			}
		}

		$after_image_id = get_post_meta( $post_id, '_case_after_image', true );
		if ( $after_image_id ) {
			$image_case_id = get_post_meta( $after_image_id, '_case_post_id', true );
			if ( $image_case_id == $post_id ) {
				if ( wp_delete_attachment( $after_image_id, true ) ) {
					$before_after_deleted ++;
				}
			}
		}

		// Calculate total deleted images
		$total_deleted = $api_images_deleted + $gallery_deleted + $before_after_deleted;

		// Log the cleanup
		if ( $total_deleted > 0 ) {
			error_log( sprintf(
				'BRAG Book Gallery: Deleted %d images for case %d (%d API images, %d gallery images, %d before/after images)',
				$total_deleted,
				$post_id,
				$api_images_deleted,
				$gallery_deleted,
				$before_after_deleted
			) );
		}

		return $total_deleted;
	}

	/**
	 * Save case meta data
	 *
	 * Handles saving of custom meta fields when a case post is saved.
	 * Includes proper nonce verification and data sanitization.
	 *
	 * @param int $post_id The post ID being saved.
	 *
	 * @return void
	 * @since 3.0.0
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

		// Save case details
		if ( isset( $_POST['case_details_nonce'] ) && wp_verify_nonce( $_POST['case_details_nonce'], 'save_case_details' ) ) {
			$fields = [
				'case_patient_age'    => 'absint',
				'case_patient_gender' => 'sanitize_text_field',
				'case_procedure_date' => 'sanitize_text_field',
				'case_notes'          => 'sanitize_textarea_field',
				'case_before_image'   => 'absint',
				'case_after_image'    => 'absint',
			];

			foreach ( $fields as $field => $sanitize_function ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = call_user_func( $sanitize_function, $_POST[ $field ] );
					update_post_meta( $post_id, '_' . $field, $value );
				}
			}
		}

		// Save API data
		if ( isset( $_POST['case_api_data_nonce'] ) && wp_verify_nonce( $_POST['case_api_data_nonce'], 'save_case_api_data' ) ) {
			$api_fields = [
				// Basic Info
				'case_api_id'                            => 'absint',
				'case_patient_id'                        => 'sanitize_text_field',
				'case_user_id'                           => 'sanitize_text_field',
				'case_org_id'                            => 'sanitize_text_field',
				'case_emr_id'                            => 'sanitize_text_field',
				'case_procedure_ids'                     => 'sanitize_text_field',

				// Patient Data
				'case_ethnicity'                         => 'sanitize_text_field',
				'case_height'                            => 'absint',
				'case_height_unit'                       => 'sanitize_text_field',
				'case_weight'                            => 'absint',
				'case_weight_unit'                       => 'sanitize_text_field',
				'case_technique'                         => 'sanitize_text_field',
				'case_after1_timeframe'                  => 'absint',
				'case_after1_unit'                       => 'sanitize_text_field',
				'case_after2_timeframe'                  => 'absint',
				'case_after2_unit'                       => 'sanitize_text_field',

				// Settings
				'case_quality_score'                     => 'absint',

				// SEO
				'case_seo_suffix_url'                    => 'sanitize_text_field',
				'case_seo_headline'                      => 'sanitize_text_field',
				'case_seo_page_title'                    => 'sanitize_text_field',
				'case_seo_page_description'              => 'sanitize_textarea_field',
				'case_seo_alt_text'                      => 'sanitize_text_field',

				// Image URLs
				'case_before_location_url'               => 'esc_url_raw',
				'case_after_location_url1'               => 'esc_url_raw',
				'case_after_location_url2'               => 'esc_url_raw',
				'case_after_location_url3'               => 'esc_url_raw',
				'case_post_processed_image_url'          => 'esc_url_raw',
				'case_high_res_post_processed_image_url' => 'esc_url_raw',
			];

			foreach ( $api_fields as $field => $sanitize_function ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = call_user_func( $sanitize_function, $_POST[ $field ] );
					update_post_meta( $post_id, '_' . $field, $value );
				}
			}

			// Handle checkboxes separately (they may not be present in $_POST if unchecked)
			$checkbox_fields = [
				'case_revision_surgery',
				'case_approved_for_social',
				'case_is_for_tablet',
				'case_is_for_website',
				'case_draft',
				'case_no_watermark',
				'case_is_nude',
			];

			foreach ( $checkbox_fields as $field ) {
				$value = isset( $_POST[ $field ] ) ? '1' : '0';
				update_post_meta( $post_id, '_' . $field, $value );
			}
		}

		// Save gallery images
		if ( isset( $_POST['case_images_nonce'] ) && wp_verify_nonce( $_POST['case_images_nonce'], 'save_case_images' ) ) {
			if ( isset( $_POST['case_gallery_images'] ) ) {
				$gallery_images = array_map( 'absint', explode( ',', $_POST['case_gallery_images'] ) );
				$gallery_images = array_filter( $gallery_images ); // Remove empty values
				update_post_meta( $post_id, '_case_gallery_images', $gallery_images );
			}
		}
	}

	/**
	 * Get post type slug for Cases
	 *
	 * @return string
	 * @since 3.0.0
	 */
	public static function get_cases_post_type(): string {
		return self::POST_TYPE_CASES;
	}

	/**
	 * Filter case post links to replace %procedures% with actual procedure slug
	 *
	 * @param string $post_link The post's permalink.
	 * @param \WP_Post $post The post object.
	 *
	 * @return string Modified permalink.
	 * @since 3.0.0
	 */
	public static function filter_case_post_link( string $post_link, \WP_Post $post ): string {
		if ( $post->post_type !== self::POST_TYPE_CASES || strpos( $post_link, '%procedures%' ) === false ) {
			return $post_link;
		}

		// Get the first procedure term assigned to this case
		$procedures = wp_get_post_terms( $post->ID, \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES );

		if ( ! empty( $procedures ) && ! is_wp_error( $procedures ) ) {
			$procedure_slug = $procedures[0]->slug;
		} else {
			$procedure_slug = 'uncategorized'; // fallback
		}

		// Replace %procedures% with the actual procedure slug
		return str_replace( '%procedures%', $procedure_slug, $post_link );
	}

	/**
	 * Add custom rewrite rules for hierarchical case URLs
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private static function add_case_rewrite_rules(): void {
		// Get gallery slug from option
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );

		// If it's an array, get the first slug
		if ( is_array( $gallery_slug ) && ! empty( $gallery_slug ) ) {
			$gallery_slug = $gallery_slug[0];
		}

		// Fallback to 'gallery' if empty
		if ( empty( $gallery_slug ) ) {
			$gallery_slug = 'gallery';
		}

		// Add rewrite rule for: /gallery-slug/procedure-slug/case-slug/
		add_rewrite_rule(
			'^' . $gallery_slug . '/([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . self::POST_TYPE_CASES . '&procedures=$matches[1]&name=$matches[2]',
			'top'
		);

		// Add query vars
		add_filter( 'query_vars', [ __CLASS__, 'add_case_query_vars' ] );
	}

	/**
	 * Add query vars for case rewrite rules
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array Modified query vars.
	 * @since 3.0.0
	 */
	public static function add_case_query_vars( array $vars ): array {
		$vars[] = 'procedures';

		return $vars;
	}

	/**
	 * Save API response data to case post meta
	 *
	 * Helper method to programmatically save API response data to a case post.
	 * Useful for importing cases from the BRAGBook API.
	 *
	 * @param int $post_id The post ID to save data to.
	 * @param array $api_data The API response data array.
	 *
	 * @return bool True on success, false on failure.
	 * @since 3.0.0
	 */
	public static function save_api_response_data( int $post_id, array $api_data ): bool {
		// Verify this is a case post
		if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
			return false;
		}

		// First check: Only import cases approved for website use
		if ( ! isset( $api_data['isForWebsite'] ) || ! $api_data['isForWebsite'] ) {
			error_log( sprintf(
				'[BRAG Book Gallery] Skipping case ID %s - not approved for website (isForWebsite: %s)',
				$api_data['id'] ?? 'unknown',
				isset( $api_data['isForWebsite'] ) ? ( $api_data['isForWebsite'] ? 'true' : 'false' ) : 'not set'
			) );

			return false;
		}

		// Map API response fields to post meta
		$field_mapping = [
			// Basic Info
			'id'                => '_case_api_id',
			'patientId'         => '_case_patient_id',
			'userId'            => '_case_user_id',
			'orgId'             => '_case_org_id',
			'emrId'             => '_case_emr_id',

			// Patient Data
			'age'               => '_case_patient_age',
			'gender'            => '_case_patient_gender',
			'ethnicity'         => '_case_ethnicity',
			'height'            => '_case_height',
			'heightUnit'        => '_case_height_unit',
			'weight'            => '_case_weight',
			'weightUnit'        => '_case_weight_unit',
			'technique'         => '_case_technique',
			'revisionSurgery'   => '_case_revision_surgery',
			'after1Timeframe'   => '_case_after1_timeframe',
			'after1Unit'        => '_case_after1_unit',
			'after2Timeframe'   => '_case_after2_timeframe',
			'after2Unit'        => '_case_after2_unit',

			// Settings
			'qualityScore'      => '_case_quality_score',
			'approvedForSocial' => '_case_approved_for_social',
			'isForTablet'       => '_case_is_for_tablet',
			'isForWebsite'      => '_case_is_for_website',
			'draft'             => '_case_draft',
			'noWatermark'       => '_case_no_watermark',

			// Additional fields
			'details'           => '_case_notes',
		];

		// Save basic fields
		foreach ( $field_mapping as $api_field => $meta_key ) {
			if ( isset( $api_data[ $api_field ] ) ) {
				$value = $api_data[ $api_field ];

				// Convert boolean values
				if ( is_bool( $value ) ) {
					$value = $value ? '1' : '0';
				}

				// Sanitize based on field type
				switch ( $meta_key ) {
					case '_case_api_id':
					case '_case_patient_age':
					case '_case_height':
					case '_case_weight':
					case '_case_quality_score':
					case '_case_after1_timeframe':
					case '_case_after2_timeframe':
						$value = absint( $value );
						break;
					case '_case_notes':
						$value = wp_kses_post( $value );
						break;
					default:
						$value = sanitize_text_field( $value );
						break;
				}

				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		// Handle procedure IDs
		if ( isset( $api_data['procedureIds'] ) && is_array( $api_data['procedureIds'] ) ) {
			$procedure_ids = implode( ',', array_map( 'absint', $api_data['procedureIds'] ) );
			update_post_meta( $post_id, '_case_procedure_ids', $procedure_ids );
		}

		// Handle SEO data from caseDetails and update post title
		$seo_headline         = null;
		$seo_page_title       = null;
		$seo_page_description = null;

		if ( isset( $api_data['caseDetails'] ) && is_array( $api_data['caseDetails'] ) ) {
			foreach ( $api_data['caseDetails'] as $case_detail ) {
				if ( isset( $case_detail['seoSuffixUrl'] ) ) {
					update_post_meta( $post_id, '_case_seo_suffix_url', sanitize_text_field( $case_detail['seoSuffixUrl'] ) );
				}
				if ( isset( $case_detail['seoHeadline'] ) && ! empty( $case_detail['seoHeadline'] ) ) {
					$seo_headline = sanitize_text_field( $case_detail['seoHeadline'] );
					update_post_meta( $post_id, '_case_seo_headline', $seo_headline );
				}
				if ( isset( $case_detail['seoPageTitle'] ) && ! empty( $case_detail['seoPageTitle'] ) ) {
					$seo_page_title = sanitize_text_field( $case_detail['seoPageTitle'] );
					update_post_meta( $post_id, '_case_seo_page_title', $seo_page_title );
				}
				if ( isset( $case_detail['seoPageDescription'] ) && ! empty( $case_detail['seoPageDescription'] ) ) {
					$seo_page_description = sanitize_textarea_field( $case_detail['seoPageDescription'] );
					update_post_meta( $post_id, '_case_seo_page_description', $seo_page_description );
				}
			}
		}

		// Update post title based on naming logic
		$case_id    = $api_data['id'] ?? $post_id;
		$post_title = $seo_headline;

		// If no seoHeadline, try to get procedure name + case number
		if ( ! $post_title ) {
			$procedure_name = self::get_primary_procedure_name( $api_data );
			if ( $procedure_name ) {
				// For multi-procedure cases, use the original case ID
				$display_case_id = $api_data['original_case_id'] ?? $case_id;
				$post_title      = $procedure_name . ' ' . $display_case_id;
			} else {
				$post_title = "Procedure #{$case_id}";
			}
		}

		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $post_title,
		] );

		// Apply SEO metadata if we have an SEO plugin installed
		if ( $seo_page_title || $seo_page_description ) {
			self::apply_seo_metadata( $post_id, $seo_page_title, $seo_page_description );
		}

		// Handle procedure details
		if ( isset( $api_data['procedureDetails'] ) && is_array( $api_data['procedureDetails'] ) ) {
			// Save the full procedure details as JSON for reference
			update_post_meta( $post_id, '_case_procedure_details', wp_json_encode( $api_data['procedureDetails'] ) );

			// Extract and save individual procedure details for easier querying
			foreach ( $api_data['procedureDetails'] as $procedure_id => $details ) {
				if ( is_array( $details ) ) {
					foreach ( $details as $detail_key => $detail_values ) {
						// Create a meta key like _case_procedure_detail_7089_Blepharoplasty_Type
						$meta_key = '_case_procedure_detail_' . $procedure_id . '_' . sanitize_key( $detail_key );

						// Save as array if multiple values, string if single value
						if ( is_array( $detail_values ) && count( $detail_values ) > 1 ) {
							update_post_meta( $post_id, $meta_key, $detail_values );
						} elseif ( is_array( $detail_values ) ) {
							update_post_meta( $post_id, $meta_key, $detail_values[0] );
						} else {
							update_post_meta( $post_id, $meta_key, $detail_values );
						}
					}
				}
			}
		}

		// Handle photo sets and download images
		if ( isset( $api_data['photoSets'] ) && is_array( $api_data['photoSets'] ) ) {
			$downloaded_images = [];
			$image_position    = 1; // Track position for filename and alt text

			foreach ( $api_data['photoSets'] as $photo_set ) {
				// Get seoAltText for this photo set
				$seo_alt_text = isset( $photo_set['seoAltText'] ) ? sanitize_text_field( $photo_set['seoAltText'] ) : '';

				// Save image URLs (keep existing functionality)
				if ( isset( $photo_set['beforeLocationUrl'] ) ) {
					update_post_meta( $post_id, '_case_before_location_url', esc_url_raw( $photo_set['beforeLocationUrl'] ) );
				}
				if ( isset( $photo_set['afterLocationUrl1'] ) ) {
					update_post_meta( $post_id, '_case_after_location_url1', esc_url_raw( $photo_set['afterLocationUrl1'] ) );
				}
				if ( isset( $photo_set['afterLocationUrl2'] ) ) {
					update_post_meta( $post_id, '_case_after_location_url2', esc_url_raw( $photo_set['afterLocationUrl2'] ) );
				}
				if ( isset( $photo_set['afterLocationUrl3'] ) ) {
					update_post_meta( $post_id, '_case_after_location_url3', esc_url_raw( $photo_set['afterLocationUrl3'] ) );
				}
				if ( isset( $photo_set['postProcessedImageLocation'] ) ) {
					update_post_meta( $post_id, '_case_post_processed_image_url', esc_url_raw( $photo_set['postProcessedImageLocation'] ) );

					// Only download images if sync option allows it (performance optimization)
					$skip_image_downloads = get_option( 'brag_book_gallery_skip_image_downloads', false );
					if ( ! $skip_image_downloads ) {
						$attachment_id = self::download_image_to_media_library(
							$photo_set['postProcessedImageLocation'],
							$post_id,
							'Post-processed image for case ' . $post_id,
							$seo_alt_text,
							$image_position
						);

						if ( $attachment_id ) {
							$downloaded_images[] = $attachment_id;
							$image_position ++; // Increment position for next image
						}
					}
				}
				if ( isset( $photo_set['highResPostProcessedImageLocation'] ) ) {
					update_post_meta( $post_id, '_case_high_res_post_processed_image_url', esc_url_raw( $photo_set['highResPostProcessedImageLocation'] ) );

					// Only download images if sync option allows it (performance optimization)
					$skip_image_downloads = get_option( 'brag_book_gallery_skip_image_downloads', false );
					if ( ! $skip_image_downloads ) {
						$attachment_id = self::download_image_to_media_library(
							$photo_set['highResPostProcessedImageLocation'],
							$post_id,
							'High-res post-processed image for case ' . $post_id,
							$seo_alt_text,
							$image_position
						);

						if ( $attachment_id ) {
							$downloaded_images[] = $attachment_id;
							$image_position ++; // Increment position for next image
						}
					}
				}
				if ( isset( $photo_set['seoAltText'] ) ) {
					update_post_meta( $post_id, '_case_seo_alt_text', sanitize_text_field( $photo_set['seoAltText'] ) );
				}
				if ( isset( $photo_set['isNude'] ) ) {
					update_post_meta( $post_id, '_case_is_nude', $photo_set['isNude'] ? '1' : '0' );
				}
			}

			// Add downloaded images to the case gallery
			if ( ! empty( $downloaded_images ) ) {
				// Get existing gallery images
				$existing_gallery = get_post_meta( $post_id, '_case_gallery_images', true );
				if ( ! is_array( $existing_gallery ) ) {
					$existing_gallery = [];
				}

				// Merge with new images (avoiding duplicates)
				$updated_gallery = array_unique( array_merge( $existing_gallery, $downloaded_images ) );
				update_post_meta( $post_id, '_case_gallery_images', $updated_gallery );

				// Store a reference to API-downloaded images for easy cleanup
				update_post_meta( $post_id, '_case_api_downloaded_images', $downloaded_images );
			}
		}

		// Store the full API response for reference
		update_post_meta( $post_id, '_case_api_response', $api_data );

		return true;
	}

	/**
	 * Get the primary procedure name from API data
	 *
	 * Extracts the procedure name for case naming from API response data.
	 * Uses procedureDetails or procedureIds to find the procedure name.
	 *
	 * @param array $api_data The API response data array.
	 *
	 * @return string|null The procedure name or null if not found.
	 * @since 3.0.0
	 */
	private static function get_primary_procedure_name( array $api_data ): ?string {
		// Try to get procedure name from procedureDetails first
		if ( isset( $api_data['procedureDetails'] ) && is_array( $api_data['procedureDetails'] ) ) {
			// Get the first procedure ID from procedureDetails
			$procedure_ids = array_keys( $api_data['procedureDetails'] );
			if ( ! empty( $procedure_ids ) ) {
				$first_procedure_id = $procedure_ids[0];

				// Look up the procedure name by ID from our taxonomy
				$procedure_name = self::get_procedure_name_by_id( $first_procedure_id );
				if ( $procedure_name ) {
					return $procedure_name;
				}
			}
		}

		// Fallback to procedureIds array
		if ( isset( $api_data['procedureIds'] ) && is_array( $api_data['procedureIds'] ) && ! empty( $api_data['procedureIds'] ) ) {
			$first_procedure_id = $api_data['procedureIds'][0];
			$procedure_name     = self::get_procedure_name_by_id( $first_procedure_id );
			if ( $procedure_name ) {
				return $procedure_name;
			}
		}

		return null;
	}

	/**
	 * Get procedure name by API ID from taxonomy
	 *
	 * Looks up a procedure name in the procedures taxonomy using the API ID.
	 * Uses caching to improve performance during sync operations.
	 *
	 * @param int $api_id The procedure API ID.
	 *
	 * @return string|null The procedure name or null if not found.
	 * @since 3.0.0
	 */
	private static function get_procedure_name_by_id( int $api_id ): ?string {
		static $procedure_cache = [];

		// Check cache first for performance
		if ( isset( $procedure_cache[ $api_id ] ) ) {
			return $procedure_cache[ $api_id ];
		}

		// Get all procedure terms that match this API ID
		$terms = get_terms( [
			'taxonomy'   => 'procedures',
			'hide_empty' => false,
			'meta_query' => [
				[
					'key'   => 'procedure_id',
					'value' => $api_id,
					'type'  => 'NUMERIC',
				],
			],
			'number'     => 1, // Only need one result
		] );

		$result = null;
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$result = $terms[0]->name;
		}

		// Cache the result (even if null) to avoid repeated queries
		$procedure_cache[ $api_id ] = $result;

		return $result;
	}

	/**
	 * Apply SEO metadata to a case post based on installed SEO plugin
	 *
	 * Detects which SEO plugin is active and applies the appropriate metadata
	 * for seoPageTitle and seoPageDescription from the API.
	 *
	 * @param int $post_id The post ID.
	 * @param string|null $seo_page_title The SEO page title from API.
	 * @param string|null $seo_page_description The SEO page description from API.
	 *
	 * @return void
	 * @since 3.0.0
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
	 * Get the active SEO plugin name
	 *
	 * Returns the name of the currently active SEO plugin for debugging purposes.
	 *
	 * @return string|null The SEO plugin name or null if none detected.
	 * @since 3.0.0
	 */
	public static function get_active_seo_plugin(): ?string {
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'RankMath';
		}
		if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) || defined( 'AIOSEO_VERSION' ) ) {
			return 'AIOSEO';
		}
		if ( class_exists( 'WPSEO_Options' ) || defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}

		return null;
	}

	/**
	 * Download image from URL to WordPress media library
	 *
	 * Downloads an image from a remote URL and adds it to the WordPress media library.
	 * Includes proper error handling and file validation.
	 *
	 * @param string $image_url The URL of the image to download.
	 * @param int $post_id The post ID to attach the image to.
	 * @param string $description Optional description for the image.
	 * @param string $alt_text Optional alt text for the image.
	 * @param int $position Optional image position for filename/alt text generation.
	 *
	 * @return int|false           Attachment ID on success, false on failure.
	 * @since 3.0.0
	 */
	private static function download_image_to_media_library( string $image_url, int $post_id, string $description = '', string $alt_text = '', int $position = 1 ): int|false {
		// Validate URL
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			error_log( 'Invalid image URL provided: ' . $image_url );

			return false;
		}

		// Check if we've already downloaded this image for this case (optimized query)
		global $wpdb;
		$existing_attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_case_source_url'
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_case_post_id'
					WHERE p.post_type = 'attachment'
					AND pm1.meta_value = %s
					AND pm2.meta_value = %d
					LIMIT 1",
			$image_url,
			$post_id
		) );

		if ( $existing_attachment_id ) {
			return (int) $existing_attachment_id;
		}

		// Include WordPress file handling functions
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Get the file extension from URL
		$url_parts  = parse_url( $image_url );
		$path_parts = pathinfo( $url_parts['path'] ?? '' );
		$extension  = $path_parts['extension'] ?? 'jpg';

		// Generate filename and alt text based on case name and position
		$case_post   = get_post( $post_id );
		$case_title  = $case_post ? $case_post->post_title : '';
		$case_api_id = get_post_meta( $post_id, '_case_api_id', true ) ?: $post_id;

		// Generate SEO-friendly filename: procedure-name-id-position.jpg
		$filename_base = '';
		if ( $case_title ) {
			$filename_base = sanitize_file_name( strtolower( str_replace( ' ', '-', $case_title ) ) );
		} else {
			$filename_base = 'case';
		}
		$filename = $filename_base . '-' . $case_api_id . '-' . $position . '.' . $extension;

		// Generate alt text if not provided
		if ( empty( $alt_text ) && $case_title ) {
			$alt_text = $case_title . ' ' . $case_api_id . '-' . $position;
		} elseif ( empty( $alt_text ) ) {
			$alt_text = 'Case ' . $case_api_id . ' image ' . $position;
		}

		// Download the file
		$temp_file = download_url( $image_url );
		if ( is_wp_error( $temp_file ) ) {
			error_log( 'Failed to download image: ' . $temp_file->get_error_message() );

			return false;
		}

		// Validate file type
		$wp_filetype = wp_check_filetype( $temp_file, null );
		if ( ! $wp_filetype['type'] ) {
			unlink( $temp_file );
			error_log( 'Invalid file type for image: ' . $image_url );

			return false;
		}

		// Prepare file array for media_handle_sideload
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $temp_file,
			'type'     => $wp_filetype['type'],
		];

		// Upload the file to media library
		$attachment_id = media_handle_sideload( $file_array, $post_id, $description );

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			unlink( $temp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			error_log( 'Failed to create attachment: ' . $attachment_id->get_error_message() );

			return false;
		}

		// Add metadata to track this image belongs to the case
		update_post_meta( $attachment_id, '_case_post_id', $post_id );
		update_post_meta( $attachment_id, '_case_source_url', esc_url_raw( $image_url ) );
		update_post_meta( $attachment_id, '_case_downloaded_date', current_time( 'mysql' ) );

		// Set alt text for the attachment
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		return $attachment_id;
	}

	/**
	 * Delete API-downloaded images for a case
	 *
	 * Removes all images that were downloaded from the API for a specific case
	 * and deletes them from the media library and file system.
	 *
	 * @param int $post_id The case post ID.
	 *
	 * @return int Number of images deleted.
	 * @since 3.0.0
	 */
	public static function delete_api_downloaded_images( int $post_id ): int {
		$deleted_count = 0;

		// Method 1: Get API-downloaded image IDs from case meta
		$api_images = get_post_meta( $post_id, '_case_api_downloaded_images', true );
		if ( is_array( $api_images ) ) {
			foreach ( $api_images as $attachment_id ) {
				if ( wp_delete_attachment( $attachment_id, true ) ) {
					$deleted_count ++;
				}
			}
		}

		// Method 2: Find images by case association (in case meta is missing)
		$associated_images = get_posts( [
			'post_type'      => 'attachment',
			'meta_query'     => [
				[
					'key'   => '_case_post_id',
					'value' => $post_id,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => - 1,
		] );

		foreach ( $associated_images as $attachment_id ) {
			// Avoid double deletion
			if ( ! in_array( $attachment_id, $api_images ?: [], true ) ) {
				if ( wp_delete_attachment( $attachment_id, true ) ) {
					$deleted_count ++;
				}
			}
		}

		// Clean up the meta fields
		delete_post_meta( $post_id, '_case_api_downloaded_images' );

		// Remove deleted images from gallery
		$gallery_images = get_post_meta( $post_id, '_case_gallery_images', true );
		if ( is_array( $gallery_images ) ) {
			$all_deleted_images = array_merge( $api_images ?: [], $associated_images );
			$updated_gallery    = array_diff( $gallery_images, $all_deleted_images );
			update_post_meta( $post_id, '_case_gallery_images', $updated_gallery );
		}

		return $deleted_count;
	}

	/**
	 * Get procedure details for a case
	 *
	 * Retrieves and formats procedure details for display.
	 *
	 * @param int $post_id The case post ID.
	 *
	 * @return array Formatted procedure details.
	 * @since 3.0.0
	 */
	public static function get_case_procedure_details( int $post_id ): array {
		$procedure_details_json = get_post_meta( $post_id, '_case_procedure_details', true );

		if ( empty( $procedure_details_json ) ) {
			return [];
		}

		$procedure_details = json_decode( $procedure_details_json, true );
		if ( ! is_array( $procedure_details ) ) {
			return [];
		}

		return $procedure_details;
	}

	/**
	 * Clean up orphaned case images
	 *
	 * Removes images that are associated with non-existent cases.
	 * Useful for maintenance and cleanup operations.
	 *
	 * @return int Number of orphaned images deleted.
	 * @since 3.0.0
	 */
	public static function cleanup_orphaned_case_images(): int {
		$deleted_count = 0;

		// Find all images tagged with _case_post_id
		$case_images = get_posts( [
			'post_type'      => 'attachment',
			'meta_key'       => '_case_post_id',
			'fields'         => 'ids',
			'posts_per_page' => - 1,
		] );

		foreach ( $case_images as $attachment_id ) {
			$case_id = get_post_meta( $attachment_id, '_case_post_id', true );

			// Check if the associated case still exists
			if ( ! $case_id || ! get_post( $case_id ) || get_post_type( $case_id ) !== self::POST_TYPE_CASES ) {
				if ( wp_delete_attachment( $attachment_id, true ) ) {
					$deleted_count ++;
				}
			}
		}

		return $deleted_count;
	}

	/**
	 * Get case image statistics
	 *
	 * Returns statistics about images associated with cases.
	 *
	 * @return array Statistics array.
	 * @since 3.0.0
	 */
	public static function get_case_image_stats(): array {
		// Count all case images
		$case_images = get_posts( [
			'post_type'      => 'attachment',
			'meta_key'       => '_case_post_id',
			'fields'         => 'ids',
			'posts_per_page' => - 1,
		] );

		$stats = [
			'total_case_images' => count( $case_images ),
			'orphaned_images'   => 0,
			'api_downloaded'    => 0,
			'manual_uploaded'   => 0,
		];

		foreach ( $case_images as $attachment_id ) {
			$case_id    = get_post_meta( $attachment_id, '_case_post_id', true );
			$source_url = get_post_meta( $attachment_id, '_case_source_url', true );

			// Check if orphaned
			if ( ! $case_id || ! get_post( $case_id ) || get_post_type( $case_id ) !== self::POST_TYPE_CASES ) {
				$stats['orphaned_images'] ++;
			} // Check if API downloaded
			elseif ( $source_url ) {
				$stats['api_downloaded'] ++;
			} // Must be manual upload
			else {
				$stats['manual_uploaded'] ++;
			}
		}

		return $stats;
	}

	/**
	 * Handle bulk delete actions for cases
	 *
	 * Processes bulk deletion of cases and ensures associated images are cleaned up.
	 * This handles the bulk actions from the admin list table.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction The action being performed.
	 * @param array $post_ids Array of post IDs.
	 *
	 * @return string Modified redirect URL.
	 * @since 3.0.0
	 */
	public function handle_bulk_delete_cases( string $redirect_to, string $doaction, array $post_ids ): string {
		// Only handle delete and trash actions for our post type
		if ( ! in_array( $doaction, [ 'delete', 'trash' ], true ) ) {
			return $redirect_to;
		}

		$deleted_images  = 0;
		$processed_cases = 0;

		foreach ( $post_ids as $post_id ) {
			// Verify this is a case post
			if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
				continue;
			}

			// Clean up images for this case
			$images_deleted = $this->delete_all_case_images( $post_id );
			$deleted_images += $images_deleted;
			$processed_cases ++;

			error_log( sprintf(
				'[BRAG Book Gallery] Bulk %s: Cleaned up %d images for case ID %d',
				$doaction,
				$images_deleted,
				$post_id
			) );
		}

		// Add success message to redirect URL
		if ( $processed_cases > 0 ) {
			$redirect_to = add_query_arg( [
				'brag_book_bulk_deleted'   => $processed_cases,
				'brag_book_images_deleted' => $deleted_images,
			], $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * Handle individual case deletion (pre_delete_post hook)
	 *
	 * This runs before a post is deleted and handles image cleanup
	 * for both individual and bulk deletions via wp_delete_post().
	 *
	 * @param int|null $post_id Post ID.
	 * @param \WP_Post|null $post Post object.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function cleanup_case_images_pre_delete( $post_id, $post ): void {
		if ( ! $post_id || ! $post ) {
			return;
		}

		// Only handle our case post type
		if ( $post->post_type !== self::POST_TYPE_CASES ) {
			return;
		}

		// Clean up images
		$images_deleted = $this->delete_all_case_images( $post_id );

		error_log( sprintf(
			'[BRAG Book Gallery] Pre-delete: Cleaned up %d images for case ID %d ("%s")',
			$images_deleted,
			$post_id,
			$post->post_title
		) );
	}

	/**
	 * Show admin notices for bulk delete operations
	 *
	 * Displays success messages when cases and their images have been bulk deleted.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function show_bulk_delete_notices(): void {
		global $pagenow;

		// Only show on cases list page
		if ( 'edit.php' !== $pagenow || ( $_GET['post_type'] ?? '' ) !== self::POST_TYPE_CASES ) {
			return;
		}

		$cases_deleted  = $_GET['brag_book_bulk_deleted'] ?? 0;
		$images_deleted = $_GET['brag_book_images_deleted'] ?? 0;

		if ( $cases_deleted > 0 ) {
			$message = sprintf(
			/* translators: %1$d: number of cases deleted, %2$d: number of images deleted */
				_n(
					'%1$d case deleted and %2$d associated images removed.',
					'%1$d cases deleted and %2$d associated images removed.',
					$cases_deleted,
					'brag-book-gallery'
				),
				$cases_deleted,
				$images_deleted
			);

			printf(
				'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'BRAG Book Gallery:', 'brag-book-gallery' ),
				esc_html( $message )
			);
		}
	}

	/**
	 * Add BRAG Book Gallery filter to media library
	 *
	 * Adds a dropdown filter in the media library to show only BRAG Book Gallery images.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function add_media_library_filter(): void {
		global $pagenow;

		// Only show on media library page
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		$current_filter = $_GET['brag_book_filter'] ?? '';

		echo '<select name="brag_book_filter" id="brag-book-filter">';
		echo '<option value="">' . esc_html__( 'All Images', 'brag-book-gallery' ) . '</option>';
		echo '<option value="brag_book_only"' . selected( $current_filter, 'brag_book_only', false ) . '>' . esc_html__( 'BRAG Book Gallery Images', 'brag-book-gallery' ) . '</option>';
		echo '<option value="api_downloaded"' . selected( $current_filter, 'api_downloaded', false ) . '>' . esc_html__( 'API Downloaded Images', 'brag-book-gallery' ) . '</option>';
		echo '<option value="case_uploads"' . selected( $current_filter, 'case_uploads', false ) . '>' . esc_html__( 'Case Upload Images', 'brag-book-gallery' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Filter media library query based on BRAG Book Gallery filter
	 *
	 * Modifies the media library query to show only BRAG Book Gallery related images
	 * when the filter is active.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function filter_media_library_query( $query ): void {
		global $pagenow;

		// Only modify admin queries on media library page
		if ( ! is_admin() || 'upload.php' !== $pagenow || ! $query->is_main_query() ) {
			return;
		}

		$filter = $_GET['brag_book_filter'] ?? '';
		if ( empty( $filter ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];

		switch ( $filter ) {
			case 'brag_book_only':
				// Show all images with any BRAG Book meta
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'     => '_case_post_id',
						'compare' => 'EXISTS',
					],
					[
						'key'     => '_case_source_url',
						'compare' => 'EXISTS',
					],
					[
						'key'     => '_case_downloaded_date',
						'compare' => 'EXISTS',
					],
				];
				break;

			case 'api_downloaded':
				// Show only API downloaded images
				$meta_query[] = [
					'key'     => '_case_source_url',
					'compare' => 'EXISTS',
				];
				break;

			case 'case_uploads':
				// Show case-related uploads that are not API downloaded
				$meta_query[] = [
					'relation' => 'AND',
					[
						'key'     => '_case_post_id',
						'compare' => 'EXISTS',
					],
					[
						'key'     => '_case_source_url',
						'compare' => 'NOT EXISTS',
					],
				];
				break;
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Add BRAG Book marker to attachment data for JavaScript
	 *
	 * Adds metadata to indicate BRAG Book Gallery images in the media library grid view.
	 *
	 * @param array $response Array of prepared attachment data.
	 * @param WP_Post $attachment Attachment object.
	 * @param array $meta Array of attachment meta data.
	 *
	 * @return array Modified response array.
	 * @since 3.0.0
	 */
	public function add_brag_book_marker_to_attachment( $response, $attachment, $meta ): array {
		$case_post_id  = get_post_meta( $attachment->ID, '_case_post_id', true );
		$source_url    = get_post_meta( $attachment->ID, '_case_source_url', true );
		$download_date = get_post_meta( $attachment->ID, '_case_downloaded_date', true );

		if ( $case_post_id || $source_url || $download_date ) {
			$response['brag_book_gallery'] = true;

			// Determine the type of BRAG Book image
			if ( $source_url ) {
				$response['brag_book_type']  = 'api_downloaded';
				$response['brag_book_label'] = __( 'API Downloaded', 'brag-book-gallery' );
			} elseif ( $case_post_id ) {
				$response['brag_book_type']  = 'case_upload';
				$response['brag_book_label'] = __( 'Case Upload', 'brag-book-gallery' );
			} else {
				$response['brag_book_type']  = 'brag_book';
				$response['brag_book_label'] = __( 'BRAG Book', 'brag-book-gallery' );
			}

			// Add case information if available
			if ( $case_post_id ) {
				$case_post = get_post( $case_post_id );
				if ( $case_post ) {
					$response['brag_book_case_title']    = $case_post->post_title;
					$response['brag_book_case_edit_url'] = get_edit_post_link( $case_post_id );
				}
			}

			// Add download date if available
			if ( $download_date ) {
				$response['brag_book_download_date'] = date( 'Y-m-d H:i:s', strtotime( $download_date ) );
			}
		}

		return $response;
	}

	/**
	 * Add styles for BRAG Book markers in media library
	 *
	 * Adds CSS to visually mark BRAG Book Gallery images in the media library.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function add_media_library_styles(): void {
		global $pagenow;

		// Only add styles on media library page
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		?>
		<style>
			/* BRAG Book Gallery media library enhancements */
			.attachment.brag-book-gallery::after {
				content: 'BRAG Book';
				position: absolute;
				top: 5px;
				right: 5px;
				background: #2271b1;
				color: white;
				padding: 2px 6px;
				font-size: 10px;
				font-weight: bold;
				border-radius: 3px;
				z-index: 10;
				text-transform: uppercase;
			}

			.attachment.brag-book-gallery.api-downloaded::after {
				content: 'API';
				background: #00a32a;
			}

			.attachment.brag-book-gallery.case-upload::after {
				content: 'CASE';
				background: #dba617;
			}

			/* Filter dropdown styling */
			#brag-book-filter {
				margin-left: 10px;
			}

			/* List view indicators */
			.wp-list-table .brag-book-indicator {
				display: inline-block;
				background: #2271b1;
				color: white;
				padding: 2px 6px;
				font-size: 10px;
				font-weight: bold;
				border-radius: 3px;
				margin-left: 5px;
				text-transform: uppercase;
			}

			.wp-list-table .brag-book-indicator.api-downloaded {
				background: #00a32a;
			}

			.wp-list-table .brag-book-indicator.case-upload {
				background: #dba617;
			}

			/* Attachment details modal styling */
			.attachment-details .brag-book-info {
				background: #f6f7f7;
				border: 1px solid #ddd;
				border-radius: 3px;
				padding: 10px;
				margin: 10px 0;
			}

			.attachment-details .brag-book-info h4 {
				margin: 0 0 8px 0;
				color: #2271b1;
				font-size: 13px;
			}

			.attachment-details .brag-book-info p {
				margin: 4px 0;
				font-size: 12px;
			}

			.attachment-details .brag-book-info .case-link {
				color: #2271b1;
				text-decoration: none;
			}

			.attachment-details .brag-book-info .case-link:hover {
				text-decoration: underline;
			}
		</style>

		<script>
			jQuery( document ).ready( function ( $ ) {
				// Add markers to grid view attachments
				function addBragBookMarkers() {
					$( '.attachment' ).each( function () {
						var $attachment = $( this );
						var id = $attachment.data( 'id' );

						if ( !id ) {
							return;
						}

						// Check if attachment has BRAG Book data
						wp.media.attachment( id ).fetch().then( function ( model ) {
							if ( model.get( 'brag_book_gallery' ) ) {
								$attachment.addClass( 'brag-book-gallery' );

								var type = model.get( 'brag_book_type' );
								if ( type === 'api_downloaded' ) {
									$attachment.addClass( 'api-downloaded' );
								} else if ( type === 'case_upload' ) {
									$attachment.addClass( 'case-upload' );
								}
							}
						} );
					} );
				}

				// Add markers to list view
				function addListViewMarkers() {
					$( '#the-list tr' ).each( function () {
						var $row = $( this );
						var id = $row.attr( 'id' );
						if ( !id ) {
							return;
						}

						id = id.replace( 'post-', '' );

						wp.media.attachment( id ).fetch().then( function ( model ) {
							if ( model.get( 'brag_book_gallery' ) ) {
								var label = model.get( 'brag_book_label' ) || 'BRAG Book';
								var type = model.get( 'brag_book_type' ) || '';
								var $title = $row.find( '.title strong' );

								if ( $title.length && !$title.find( '.brag-book-indicator' ).length ) {
									var $indicator = $( '<span class="brag-book-indicator ' + type + '">' + label + '</span>' );
									$title.append( $indicator );
								}
							}
						} );
					} );
				}

				// Add BRAG Book info to attachment details modal
				$( document ).on( 'click', '.attachment', function () {
					setTimeout( function () {
						var $details = $( '.attachment-details' );
						if ( $details.length ) {
							var id = $details.data( 'id' ) || $( '.attachment-details' ).find( '[data-id]' ).first().data( 'id' );

							if ( id ) {
								wp.media.attachment( id ).fetch().then( function ( model ) {
									if ( model.get( 'brag_book_gallery' ) && !$details.find( '.brag-book-info' ).length ) {
										var html = '<div class="brag-book-info">';
										html += '<h4>🎯 BRAG Book Gallery Image</h4>';
										html += '<p><strong>Type:</strong> ' + (
											model.get( 'brag_book_label' ) || 'BRAG Book'
										) + '</p>';

										if ( model.get( 'brag_book_case_title' ) ) {
											html += '<p><strong>Case:</strong> <a href="' + model.get( 'brag_book_case_edit_url' ) + '" class="case-link" target="_blank">' + model.get( 'brag_book_case_title' ) + '</a></p>';
										}

										if ( model.get( 'brag_book_download_date' ) ) {
											html += '<p><strong>Downloaded:</strong> ' + model.get( 'brag_book_download_date' ) + '</p>';
										}

										html += '</div>';

										$details.find( '.attachment-info' ).append( html );
									}
								} );
							}
						}
					}, 100 );
				} );

				// Initial load
				addBragBookMarkers();
				addListViewMarkers();

				// Re-run when media library content changes
				$( document ).on( 'post-load', addBragBookMarkers );
				$( document ).on( 'post-load', addListViewMarkers );
			} );
		</script>
		<?php
	}
}
