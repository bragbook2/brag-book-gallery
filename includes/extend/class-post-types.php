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
		add_filter( 'query_vars', [ $this, 'add_simple_query_vars' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_case_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_case_meta' ] );

		// Register meta fields for Gutenberg - DISABLED: Using classic meta boxes instead
		// add_action( 'init', [ $this, 'register_gutenberg_meta_fields' ] );

		// Enqueue Gutenberg sidebar - DISABLED: Using classic meta boxes instead
		// add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_gutenberg_sidebar' ] );
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
			'show_in_admin_bar'  => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => $gallery_slug . '/%brag_book_procedures%',
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
			'taxonomies'         => [ 'brag_book_procedures' ],
		];

		register_post_type( self::POST_TYPE_CASES, $cases_args );
	}

	public function custom_brag_book_cases_permalink( $post_link, $post ) {
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
	 * Add meta boxes for Cases post type
	 *
	 * Adds custom meta boxes to the Cases edit screen for additional
	 * case information like patient details and procedure specifics.
	 *
	 * @return void
	 * @since 3.0.0
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
		$procedure_case_id    = get_post_meta( $post->ID, 'brag_book_gallery_procedure_case_id', true );
		$case_id              = get_post_meta( $post->ID, 'brag_book_gallery_case_id', true );
		$case_notes           = get_post_meta( $post->ID, 'brag_book_gallery_notes', true );
		$patient_age          = get_post_meta( $post->ID, 'brag_book_gallery_patient_age', true );
		$patient_gender       = get_post_meta( $post->ID, 'brag_book_gallery_gender', true ) ?: get_post_meta( $post->ID, 'brag_book_gallery_patient_gender', true );
		$ethnicity            = get_post_meta( $post->ID, 'brag_book_gallery_ethnicity', true );
		$height               = get_post_meta( $post->ID, 'brag_book_gallery_height', true );
		$height_unit          = get_post_meta( $post->ID, 'brag_book_gallery_height_unit', true );
		$weight               = get_post_meta( $post->ID, 'brag_book_gallery_weight', true );
		$weight_unit          = get_post_meta( $post->ID, 'brag_book_gallery_weight_unit', true );
		$weight_unit          = get_post_meta( $post->ID, 'brag_book_gallery_weight_unit', true );
		$procedure_ids        = get_post_meta( $post->ID, 'brag_book_gallery_procedure_ids', true );
		$seo_suffix_url       = get_post_meta( $post->ID, 'brag_book_gallery_seo_suffix_url', true );
		$seo_headline         = get_post_meta( $post->ID, 'brag_book_gallery_seo_headline', true );
		$seo_page_title       = get_post_meta( $post->ID, 'brag_book_gallery_seo_page_title', true );
		$seo_page_description = get_post_meta( $post->ID, 'brag_book_gallery_seo_page_description', true );
		$seo_alt_text         = get_post_meta( $post->ID, 'brag_book_gallery_seo_alt_text', true );
		$doctor_name          = get_post_meta( $post->ID, 'brag_book_gallery_doctor_name', true );
		$member_id            = get_post_meta( $post->ID, 'brag_book_gallery_member_id', true );

		// Photo URLs
		$post_processed_image_url          = get_post_meta( $post->ID, 'brag_book_gallery_post_processed_image_url', true );
		$high_res_post_processed_image_url = get_post_meta( $post->ID, 'brag_book_gallery_high_res_post_processed_image_url', true );

		?>
		<div class="brag-book-gallery-admin-wrap">
			<div class="brag-book-gallery-section">
				<div class="brag-book-api-data-tabs">
					<nav class="nav-tab-wrapper">
				<a href="#api-basic"
				   class="nav-tab nav-tab-active"><?php esc_html_e( 'Basic Info', 'brag-book-gallery' ); ?></a>
				<a href="#api-patient"
				   class="nav-tab"><?php esc_html_e( 'Patient Info', 'brag-book-gallery' ); ?></a>
				<a href="#api-procedure-details"
				   class="nav-tab"><?php esc_html_e( 'Procedure Details', 'brag-book-gallery' ); ?></a>
				<a href="#api-seo"
				   class="nav-tab"><?php esc_html_e( 'SEO', 'brag-book-gallery' ); ?></a>
				<a href="#api-images"
				   class="nav-tab"><?php esc_html_e( 'Image URLs', 'brag-book-gallery' ); ?></a>
			</nav>

			<div id="api-basic" class="tab-content active">
				<h4><?php esc_html_e( 'Basic Information', 'brag-book-gallery' ); ?></h4>
				<table class="form-table">
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
								for="brag_book_gallery_doctor_name"><?php esc_html_e( 'Doctor Name', 'brag-book-gallery' ); ?></label>
						</th>
						<td>
							<input type="text" id="brag_book_gallery_doctor_name"
								   name="brag_book_gallery_doctor_name"
								   value="<?php echo esc_attr( $doctor_name ); ?>"
								   class="regular-text"/>
							<p class="description"><?php esc_html_e( 'Name of the doctor who performed the procedure', 'brag-book-gallery' ); ?></p>
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
									Pounds
								</option>
								<option
									value="kg" <?php selected( $weight_unit, 'kg' ); ?>>
									Kilograms
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
				if ( empty( $procedure_details_json ) ) {
					$procedure_details_json = get_post_meta( $post->ID, 'brag_book_gallery_procedure_details', true );
				}

				$procedure_details = ! empty( $procedure_details_json ) ? json_decode( $procedure_details_json, true ) : [];

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
				<table class="form-table">
					<?php
					// Define URL fields with their meta keys and labels
					$url_fields = [
						'brag_book_gallery_case_before_url' => __( 'Before Image URLs', 'brag-book-gallery' ),
						'brag_book_gallery_case_after_url' => __( 'After Image URLs', 'brag-book-gallery' ),
						'brag_book_gallery_case_post_processed_url' => __( 'Post-Processed Image URLs', 'brag-book-gallery' ),
						'brag_book_gallery_case_high_res_url' => __( 'High-Res Post-Processed Image URLs', 'brag-book-gallery' )
					];

					foreach ( $url_fields as $meta_key => $label ) :
						// Get the current value from the new meta field format
						$urls_value = get_post_meta( $post->ID, $meta_key, true );

						// Clean up the display value (remove extra semicolons for display)
						$display_value = '';
						if ( ! empty( $urls_value ) ) {
							$lines = explode( "\n", $urls_value );
							$clean_lines = [];
							foreach ( $lines as $line ) {
								$clean_line = trim( rtrim( trim( $line ), ';' ) );
								if ( ! empty( $clean_line ) ) {
									$clean_lines[] = $clean_line;
								}
							}
							$display_value = implode( "\n", $clean_lines );
						}
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $label ); ?></label>
							</th>
							<td>
								<textarea
									name="<?php echo esc_attr( $meta_key ); ?>"
									id="<?php echo esc_attr( $meta_key ); ?>"
									rows="4"
									cols="60"
									class="large-text"
									placeholder="<?php esc_attr_e( 'Enter one URL per line...', 'brag-book-gallery' ); ?>"
								><?php echo esc_textarea( $display_value ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Enter one URL per line for this image type. Semicolons will be added automatically when saved.', 'brag-book-gallery' ); ?>
								</p>
							</td>
						</tr>
						<?php
					endforeach;
					?>
				</table>
			</div>
		</div>

		<style>
			/* Meta Box Global Styles - Modern Admin Design */
			.brag-book-case-meta {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 8px;
				overflow: hidden;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			}

			.brag-book-case-meta .form-table {
				margin: 0;
				border-collapse: separate;
				border-spacing: 0;
				width: 100%;
			}

			.brag-book-case-meta .form-table th {
				background: #f6f7f7;
				border-bottom: 1px solid #c3c4c7;
				border-right: 1px solid #c3c4c7;
				padding: 16px 20px;
				font-weight: 500;
				font-size: 13px;
				color: #2c3338;
				width: 220px;
				vertical-align: top;
			}

			.brag-book-case-meta .form-table td {
				background: #fff;
				border-bottom: 1px solid #c3c4c7;
				padding: 16px 20px;
				vertical-align: top;
			}

			.brag-book-case-meta .form-table tr:last-child th,
			.brag-book-case-meta .form-table tr:last-child td {
				border-bottom: none;
			}

			/* Modern input styling matching admin settings */
			.brag-book-case-meta input[type="text"],
			.brag-book-case-meta input[type="url"],
			.brag-book-case-meta input[type="number"],
			.brag-book-case-meta input[type="date"],
			.brag-book-case-meta select,
			.brag-book-case-meta textarea {
				border: 1px solid #8c8f94;
				border-radius: 6px;
				padding: 8px 12px;
				font-size: 14px;
				line-height: 1.4;
				transition: all 0.2s ease;
				background: white;
				width: 100%;
				max-width: 500px;
			}

			.brag-book-case-meta input[type="text"]:hover,
			.brag-book-case-meta input[type="url"]:hover,
			.brag-book-case-meta input[type="number"]:hover,
			.brag-book-case-meta input[type="date"]:hover,
			.brag-book-case-meta select:hover,
			.brag-book-case-meta textarea:hover {
				border-color: #646970;
			}

			.brag-book-case-meta input[type="text"]:focus,
			.brag-book-case-meta input[type="url"]:focus,
			.brag-book-case-meta input[type="number"]:focus,
			.brag-book-case-meta input[type="date"]:focus,
			.brag-book-case-meta select:focus,
			.brag-book-case-meta textarea:focus {
				outline: 2px solid #2271b1;
				outline-offset: -1px;
				border-color: #2271b1;
				box-shadow: none;
			}

			.brag-book-case-meta .description {
				color: #646970;
				font-size: 13px;
				margin-top: 8px;
				line-height: 1.4;
			}

			.brag-book-case-meta label {
				font-weight: 500;
				color: #1d2327;
			}

			/* Toggle styling for post meta - matches admin settings exactly */
			.brag-book-case-meta-toggles {
				display: flex;
				flex-direction: column;
				gap: 16px;
			}

			.brag-book-case-meta .brag-book-gallery-toggle-wrapper {
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.brag-book-case-meta .brag-book-gallery-toggle {
				position: relative;
				display: inline-block;
				width: 44px;
				height: 24px;
				cursor: pointer;
				user-select: none;
			}

			.brag-book-case-meta .brag-book-gallery-toggle input[type="checkbox"] {
				position: absolute;
				opacity: 0;
				pointer-events: none;
				width: 100%;
				height: 100%;
				margin: 0;
			}

			.brag-book-case-meta .brag-book-gallery-toggle input[type="checkbox"]:checked + .brag-book-gallery-toggle-slider {
				background-color: #2271b1;
			}

			.brag-book-case-meta .brag-book-gallery-toggle input[type="checkbox"]:checked + .brag-book-gallery-toggle-slider::before {
				transform: translateX(20px);
			}

			.brag-book-case-meta .brag-book-gallery-toggle input[type="checkbox"]:focus-visible + .brag-book-gallery-toggle-slider {
				outline: 2px solid #2271b1;
				outline-offset: 2px;
			}

			.brag-book-case-meta .brag-book-gallery-toggle-slider {
				position: relative;
				display: block;
				width: 100%;
				height: 100%;
				background-color: #ccc;
				border-radius: 12px;
				transition: background-color 0.2s ease;
			}

			.brag-book-case-meta .brag-book-gallery-toggle-slider::before {
				content: '';
				position: absolute;
				top: 2px;
				left: 2px;
				width: 20px;
				height: 20px;
				background-color: white;
				border-radius: 50%;
				transition: transform 0.2s ease;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			}

			.brag-book-case-meta .brag-book-gallery-toggle-label {
				font-weight: 500;
				color: #2c3338;
				margin: 0;
			}

			/* Tabbed Interface */
			.brag-book-api-data-tabs {
				background: #fff;
				border: 1px solid #e1e1e1;
				border-radius: 8px;
				overflow: hidden;
			}

			.brag-book-api-data-tabs .nav-tab-wrapper {
				background: #f6f7f7;
				border-bottom: 1px solid #e1e1e1;
				margin: 0;
				padding: 0;
				display: flex;
			}

			.brag-book-api-data-tabs .nav-tab {
				background: transparent;
				border: none;
				border-bottom: 3px solid transparent;
				border-radius: 0;
				margin: 0;
				padding: 16px 24px;
				font-weight: 500;
				color: #50575e;
				transition: all 0.2s ease;
				cursor: pointer;
				text-decoration: none;
			}

			.brag-book-api-data-tabs .nav-tab:hover {
				background: #f0f0f1;
				color: #1d2327;
			}

			.brag-book-api-data-tabs .nav-tab.nav-tab-active {
				background: #fff;
				color: #2271b1;
				border-bottom-color: #2271b1;
			}

			.brag-book-api-data-tabs .tab-content {
				display: none;
				padding: 0;
			}

			.brag-book-api-data-tabs .tab-content.active {
				display: block;
			}

			/* Image URL Repeater Styles */
			.image-urls-repeater {
				padding: 24px;
			}

			.image-urls-repeater h4 {
				margin: 0 0 8px 0;
				font-size: 16px;
				font-weight: 600;
				color: #1d2327;
			}

			.image-urls-repeater > .description {
				color: #646970;
				font-size: 14px;
				margin-bottom: 24px;
				line-height: 1.5;
			}

			.image-url-set {
				border: 2px solid #e1e5e9;
				border-radius: 12px;
				margin-bottom: 24px;
				background: #fff;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
				transition: all 0.2s ease;
			}

			.image-url-set:hover {
				border-color: #c3c4c7;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
			}

			.image-url-set-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px 24px;
				border-bottom: 1px solid #e9ecef;
				background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
				border-radius: 10px 10px 0 0;
			}

			.image-url-set-header h5 {
				margin: 0;
				font-size: 15px;
				font-weight: 600;
				color: #1d2327;
				display: flex;
				align-items: center;
			}

			.image-url-set-header h5:before {
				content: "📷";
				margin-right: 8px;
				font-size: 16px;
			}

			.image-url-set-header .remove-image-set {
				color: #d63638;
				text-decoration: none;
				font-size: 13px;
				font-weight: 500;
				padding: 6px 12px;
				border-radius: 4px;
				transition: all 0.2s ease;
				border: 1px solid transparent;
			}

			.image-url-set-header .remove-image-set:hover {
				background: #d63638;
				color: #fff;
				border-color: #d63638;
			}

			.image-url-set .form-table {
				margin: 0;
				background: #fff;
				border-radius: 0 0 10px 10px;
			}

			.image-url-set .form-table th {
				background: #fafbfc;
				width: 240px;
				font-weight: 500;
				font-size: 13px;
			}

			.image-url-set .form-table td {
				background: #fff;
			}

			.image-url-repeater-controls {
				padding: 20px 0 0 0;
				border-top: 2px solid #f0f0f1;
				text-align: center;
			}

			.image-url-repeater-controls .button {
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
				border-radius: 6px;
				padding: 12px 24px;
				font-weight: 500;
				text-shadow: none;
				box-shadow: none;
				transition: all 0.2s ease;
			}

			.image-url-repeater-controls .button:hover:not(:disabled) {
				background: #135e96;
				border-color: #135e96;
				transform: translateY(-1px);
				box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
			}

			.image-url-repeater-controls .button:disabled {
				background: #c3c4c7;
				border-color: #c3c4c7;
				cursor: not-allowed;
			}

			.image-url-repeater-controls .description {
				display: block;
				margin-top: 12px;
				color: #646970;
				font-style: normal;
				font-size: 13px;
			}

			/* Section Headers */
			.brag-book-section-header {
				background: linear-gradient(135deg, #f6f7f7 0%, #f0f0f1 100%);
				padding: 16px 20px;
				border-bottom: 1px solid #e1e1e1;
				margin: 0;
			}

			.brag-book-section-header h4 {
				margin: 0;
				font-size: 15px;
				font-weight: 600;
				color: #1d2327;
			}

			/* Success/Info Messages */
			.brag-book-info-box {
				background: #f0f6fc;
				border: 1px solid #c3d9ed;
				border-radius: 6px;
				padding: 16px;
				margin: 16px 0;
			}

			.brag-book-info-box h4 {
				margin: 0 0 8px 0;
				color: #0073aa;
				font-size: 14px;
			}

			.brag-book-info-box p {
				margin: 0;
				color: #0073aa;
				font-size: 13px;
			}

			/* Responsive Design */
			@media (max-width: 782px) {
				.brag-book-case-meta .form-table th {
					width: auto;
					display: block;
					padding: 12px 16px 4px;
					border-right: none;
					border-bottom: none;
				}

				.brag-book-case-meta .form-table td {
					display: block;
					padding: 0 16px 16px;
					border-bottom: 1px solid #e9ecef;
				}

				.brag-book-api-data-tabs .nav-tab-wrapper {
					flex-wrap: wrap;
				}

				.brag-book-api-data-tabs .nav-tab {
					flex: 1;
					text-align: center;
					min-width: 120px;
				}

				.image-url-set-header {
					flex-direction: column;
					gap: 12px;
					text-align: center;
				}

				.image-url-repeater {
					padding: 16px;
				}
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
				</div>
			</div>
		</div>
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

		// Get existing gallery images (check new format first, then fallback to old format)
		$gallery_images = get_post_meta( $post->ID, 'brag_book_gallery_images', true );
		if ( ! is_array( $gallery_images ) ) {
			$gallery_images = [];
		}

		?>
		<div class="brag-book-gallery-admin-wrap">
			<div class="brag-book-gallery-section">
				<h3><?php esc_html_e( 'Additional Images', 'brag-book-gallery' ); ?></h3>
			<div style="padding: 20px;">
				<p class="description" style="margin-bottom: 16px;">
					<?php esc_html_e( 'Add additional images to showcase different angles or stages of the procedure.', 'brag-book-gallery' ); ?>
				</p>

				<input type="hidden" id="brag_book_gallery_images" name="brag_book_gallery_images"
					   value="<?php echo esc_attr( implode( ',', $gallery_images ) ); ?>"/>

				<button type="button" class="button button-primary"
						id="add_gallery_images_button"
						style="background: #2271b1; border-color: #2271b1; border-radius: 6px; padding: 12px 20px; font-weight: 500;">
					<?php esc_html_e( 'Add Images', 'brag-book-gallery' ); ?>
				</button>

				<div id="gallery_images_preview" style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;">
					<?php foreach ( $gallery_images as $image_id ) : ?>
						<div class="gallery-image-item"
							 data-id="<?php echo esc_attr( $image_id ); ?>"
							 style="position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
							<?php echo wp_get_attachment_image( $image_id, 'thumbnail', false, [ 'style' => 'width: 100%; height: auto; display: block;' ] ); ?>
							<button type="button" class="remove-gallery-image"
									style="position: absolute; top: 5px; right: 5px; background: #d63638; color: white; border: none; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 14px; line-height: 1; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
								×
							</button>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
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
						var currentImages = $( '#brag_book_gallery_images' ).val().split( ',' ).filter( function ( id ) {
							return id !== '';
						} );

						selection.each( function ( attachment ) {
							attachment = attachment.toJSON();
							if ( currentImages.indexOf( attachment.id.toString() ) === - 1 ) {
								currentImages.push( attachment.id );
								$( '#gallery_images_preview' ).append(
									'<div class="gallery-image-item" data-id="' + attachment.id + '" style="position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">' +
									'<img src="' + attachment.sizes.thumbnail.url + '" style="width: 100%; height: auto; display: block;" />' +
									'<button type="button" class="remove-gallery-image" style="position: absolute; top: 5px; right: 5px; background: #d63638; color: white; border: none; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 14px; line-height: 1; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">×</button>' +
									'</div>'
								);
							}
						} );

						$( '#brag_book_gallery_images' ).val( currentImages.join( ',' ) );
					} );

					mediaUploader.open();
				} );

				$( document ).on( 'click', '.remove-gallery-image', function ( e ) {
					e.preventDefault();
					var imageId = $( this ).parent().data( 'id' );
					var currentImages = $( '#brag_book_gallery_images' ).val().split( ',' ).filter( function ( id ) {
						return id !== '' && id != imageId;
					} );
					$( '#brag_book_gallery_images' ).val( currentImages.join( ',' ) );
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
			$api_downloaded_images = get_post_meta( $post->ID, 'brag_book_gallery_api_downloaded_images', true );
			if ( ! empty( $api_downloaded_images ) ) {
				echo '<div class="brag-book-info-box" style="margin-top: 20px;">';
				echo '<h4>📥 ' . esc_html__( 'Downloaded Images', 'brag-book-gallery' ) . '</h4>';
				echo '<p>' . sprintf(
						esc_html__( '%d images have been downloaded from the API and added to the gallery above.', 'brag-book-gallery' ),
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
		$gallery_images  = get_post_meta( $post_id, 'brag_book_gallery_gallery_images', true );
		$gallery_deleted = 0;

		if ( is_array( $gallery_images ) ) {
			foreach ( $gallery_images as $attachment_id ) {
				// Check if this image belongs to this case
				$image_case_id = get_post_meta( $attachment_id, 'brag_book_gallery_attachment_case_post_id', true );
				if ( $image_case_id == $post_id ) {
					if ( wp_delete_attachment( $attachment_id, true ) ) {
						$gallery_deleted ++;
					}
				}
			}
		}

		// Delete before/after images if they are attached to this case
		$before_after_deleted = 0;

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

		// Save case details (legacy - kept for backward compatibility)
		// Note: Fields have been moved to API Case Data meta box
		if ( isset( $_POST['case_details_nonce'] ) && wp_verify_nonce( $_POST['case_details_nonce'], 'save_case_details' ) ) {
			// This meta box is now empty - fields moved to API Case Data
			// Kept for potential future use
		}

		// Handle meta saves from Gutenberg (new prefix format)
		$gutenberg_fields = [
			// Case details (now in API Case Data meta box)
			'brag_book_gallery_patient_age'    => 'absint',
			'brag_book_gallery_gender'         => 'sanitize_text_field', // Updated field name
			'brag_book_gallery_notes'          => 'sanitize_textarea_field',
			'brag_book_gallery_doctor_name'    => 'sanitize_text_field',
			'brag_book_gallery_member_id'      => 'absint',

			// API data
			'brag_book_gallery_case_id',
					'brag_book_gallery_procedure_case_id'         => 'absint',
			'brag_book_gallery_patient_id'     => 'sanitize_text_field',
			'brag_book_gallery_org_id'         => 'sanitize_text_field',
			'brag_book_gallery_quality_score'  => 'absint',

			// Patient information
			'brag_book_gallery_ethnicity'      => 'sanitize_text_field',
			'brag_book_gallery_height'         => 'absint',
			'brag_book_gallery_height_unit'    => 'sanitize_text_field',
			'brag_book_gallery_weight'         => 'absint',
			'brag_book_gallery_weight_unit'    => 'sanitize_text_field',

			// SEO data
			'brag_book_gallery_seo_suffix_url'        => 'sanitize_text_field',
			'brag_book_gallery_seo_headline'          => 'sanitize_text_field',
			'brag_book_gallery_seo_page_title'        => 'sanitize_text_field',
			'brag_book_gallery_seo_page_description'  => 'sanitize_textarea_field',
			'brag_book_gallery_seo_alt_text'          => 'sanitize_text_field',
		];

		foreach ( $gutenberg_fields as $field => $sanitize_function ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = call_user_func( $sanitize_function, $_POST[ $field ] );
				update_post_meta( $post_id, $field, $value );
			}
		}

		// Handle Gutenberg checkbox fields separately (they may not be present in $_POST if unchecked)
		$gutenberg_checkbox_fields = [
			'brag_book_gallery_approved_for_social',
			'brag_book_gallery_is_for_tablet',
			'brag_book_gallery_is_for_website',
			'brag_book_gallery_draft',
			'brag_book_gallery_no_watermark',
			'brag_book_gallery_is_nude',
		];

		foreach ( $gutenberg_checkbox_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = $_POST[ $field ] === '1' ? '1' : '0';
				update_post_meta( $post_id, $field, $value );
			}
		}

		// Handle Gutenberg image URL sets (JSON format)
		if ( isset( $_POST['brag_book_gallery_image_url_sets'] ) ) {
			$json_data = $_POST['brag_book_gallery_image_url_sets'];
			$image_url_sets = json_decode( $json_data, true );

			if ( is_array( $image_url_sets ) ) {
				$sanitized_sets = [];

				foreach ( $image_url_sets as $index => $url_set ) {
					if ( ! is_array( $url_set ) ) {
						continue;
					}

					$sanitized_set = [];
					$has_content = false;

					$url_fields = [
						'before_url',
						'after_url1',
						'after_url2',
						'after_url3',
						'post_processed_url',
						'high_res_url'
					];

					foreach ( $url_fields as $field ) {
						$url = isset( $url_set[ $field ] ) ? esc_url_raw( trim( $url_set[ $field ] ) ) : '';
						$sanitized_set[ $field ] = $url;
						if ( ! empty( $url ) ) {
							$has_content = true;
						}
					}

					// Only save sets that have at least one URL
					if ( $has_content ) {
						$sanitized_sets[] = $sanitized_set;
					}
				}

				// Save with new format
				update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $sanitized_sets );
				// Also save with old format for backward compatibility
				update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $sanitized_sets );
			}
		}

		// Handle new individual URL fields from meta boxes
		$url_fields = [
			'brag_book_gallery_case_before_url',
			'brag_book_gallery_case_after_url1',
			'brag_book_gallery_case_after_url2',
			'brag_book_gallery_case_after_url3',
			'brag_book_gallery_case_post_processed_url',
			'brag_book_gallery_case_high_res_url'
		];

		foreach ( $url_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$urls_input = $_POST[ $field ];

				// Process the textarea input: split by lines, trim, add semicolons
				$lines = explode( "\n", $urls_input );
				$processed_lines = [];

				foreach ( $lines as $line ) {
					$clean_url = trim( $line );
					$clean_url = rtrim( $clean_url, ';' ); // Remove existing semicolons

					if ( ! empty( $clean_url ) && filter_var( $clean_url, FILTER_VALIDATE_URL ) ) {
						$processed_lines[] = esc_url_raw( $clean_url ) . ';';
					}
				}

				$final_value = implode( "\n", $processed_lines );
				update_post_meta( $post_id, $field, $final_value );
			}
		}

		// Save API data
		if ( isset( $_POST['case_api_data_nonce'] ) && wp_verify_nonce( $_POST['case_api_data_nonce'], 'save_case_api_data' ) ) {
			$api_fields = [
				// Basic Info
				'case_api_id'                            => 'absint',
				'case_procedure_ids'                     => 'sanitize_text_field',

				// Patient Data
				'brag_book_gallery_ethnicity'           => 'sanitize_text_field',
				'brag_book_gallery_height'               => 'absint',
				'brag_book_gallery_height_unit'          => 'sanitize_text_field',
				'brag_book_gallery_weight'               => 'absint',
				'brag_book_gallery_weight_unit'          => 'sanitize_text_field',

				// SEO
				'case_seo_suffix_url'                    => 'sanitize_text_field',
				'case_seo_headline'                      => 'sanitize_text_field',
				'case_seo_page_title'                    => 'sanitize_text_field',
				'case_seo_page_description'              => 'sanitize_textarea_field',
				'case_seo_alt_text'                      => 'sanitize_text_field',

				// Image URLs
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
				'case_revision_surgery'
			];

			foreach ( $checkbox_fields as $field ) {
				$value = isset( $_POST[ $field ] ) ? '1' : '0';
				update_post_meta( $post_id, '_' . $field, $value );
			}
		}

		// Save gallery images
		if ( isset( $_POST['case_images_nonce'] ) && wp_verify_nonce( $_POST['case_images_nonce'], 'save_case_images' ) ) {
			// Handle new format (brag_book_gallery_images)
			if ( isset( $_POST['brag_book_gallery_images'] ) ) {
				$gallery_images = array_map( 'absint', explode( ',', $_POST['brag_book_gallery_images'] ) );
				$gallery_images = array_filter( $gallery_images ); // Remove empty values
				update_post_meta( $post_id, 'brag_book_gallery_images', $gallery_images );
				// Also save with old prefix for backward compatibility
				update_post_meta( $post_id, 'brag_book_gallery_gallery_images', $gallery_images );
			}
			// Handle old format (case_gallery_images) for backward compatibility
			elseif ( isset( $_POST['case_gallery_images'] ) ) {
				$gallery_images = array_map( 'absint', explode( ',', $_POST['case_gallery_images'] ) );
				$gallery_images = array_filter( $gallery_images ); // Remove empty values
				update_post_meta( $post_id, 'brag_book_gallery_gallery_images', $gallery_images );
				// Also save with new prefix
				update_post_meta( $post_id, 'brag_book_gallery_images', $gallery_images );
			}
		}

		// Save image URLs from separate textareas
		if ( isset( $_POST['case_api_data_nonce'] ) && wp_verify_nonce( $_POST['case_api_data_nonce'], 'save_case_api_data' ) ) {
			$url_types = [ 'before_url', 'after_url1', 'after_url2', 'after_url3', 'post_processed_url', 'high_res_url' ];
			$urls_by_type = [];

			// Collect URLs from each textarea
			foreach ( $url_types as $url_type ) {
				if ( isset( $_POST[ 'case_' . $url_type ] ) ) {
					$textarea_content = sanitize_textarea_field( $_POST[ 'case_' . $url_type ] );
					$urls = array_filter( array_map( 'trim', explode( "\n", $textarea_content ) ) );

					// Sanitize URLs
					$sanitized_urls = [];
					foreach ( $urls as $url ) {
						$clean_url = esc_url_raw( $url );
						if ( ! empty( $clean_url ) ) {
							$sanitized_urls[] = $clean_url;
						}
					}
					$urls_by_type[ $url_type ] = $sanitized_urls;
				} else {
					$urls_by_type[ $url_type ] = [];
				}
			}

			// Convert to URL sets format (each row corresponds to one set)
			$max_urls = max( array_map( 'count', $urls_by_type ) );
			$image_url_sets = [];

			for ( $i = 0; $i < $max_urls; $i++ ) {
				$set = [
					'before_url' => $urls_by_type['before_url'][ $i ] ?? '',
					'after_url1' => $urls_by_type['after_url1'][ $i ] ?? '',
					'after_url2' => $urls_by_type['after_url2'][ $i ] ?? '',
					'after_url3' => $urls_by_type['after_url3'][ $i ] ?? '',
					'post_processed_url' => $urls_by_type['post_processed_url'][ $i ] ?? '',
					'high_res_url' => $urls_by_type['high_res_url'][ $i ] ?? ''
				];

				// Only add sets that have at least one URL
				if ( array_filter( $set ) ) {
					$image_url_sets[] = $set;
				}
			}

			// Save in both new and legacy formats
			update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $image_url_sets );
			update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $image_url_sets );
		}
	}

	/**
	 * Register meta fields for Gutenberg sidebar
	 *
	 * @return void
	 *@since 3.0.0
	 */
	public function register_gutenberg_meta_fields(): void {
		$meta_fields = [
			// Case details
			'brag_book_gallery_patient_age',
			'brag_book_gallery_patient_gender',
			'brag_book_gallery_procedure_date',
			'brag_book_gallery_notes',

			// API data
			'brag_book_gallery_case_id',
			'brag_book_gallery_procedure_case_id',
			'brag_book_gallery_patient_id',
			'brag_book_gallery_user_id',
			'brag_book_gallery_org_id',
			'brag_book_gallery_emr_id',
			'brag_book_gallery_procedure_ids',
			'brag_book_gallery_quality_score',

			// Patient information
			'brag_book_gallery_ethnicity',
			'brag_book_gallery_height',
			'brag_book_gallery_height_unit',
			'brag_book_gallery_weight',
			'brag_book_gallery_weight_unit',

			// Settings
			'brag_book_gallery_approved_for_social',
			'brag_book_gallery_is_for_tablet',
			'brag_book_gallery_is_for_website',
			'brag_book_gallery_draft',
			'brag_book_gallery_no_watermark',
			'brag_book_gallery_is_nude',

			// SEO
			'brag_book_gallery_seo_suffix_url',
			'brag_book_gallery_seo_headline',
			'brag_book_gallery_seo_page_title',
			'brag_book_gallery_seo_page_description',
			'brag_book_gallery_seo_alt_text',

			// Image URLs
			'brag_book_gallery_image_url_sets',
		];

		foreach ( $meta_fields as $meta_key ) {
			register_post_meta( self::POST_TYPE_CASES, $meta_key, [
				'show_in_rest' => true,
				'single' => true,
				'type' => 'string',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				}
			] );
		}
	}

	/**
	 * Enqueue Gutenberg sidebar assets
	 *
	 * @return void
	 *@since 3.0.0
	 */
	public function enqueue_gutenberg_sidebar(): void {
		global $post;

		// Only enqueue for brag_book_cases post type
		if ( ! $post || get_post_type( $post ) !== self::POST_TYPE_CASES ) {
			return;
		}

		wp_enqueue_script(
			'brag-book-gallery-gutenberg-sidebar',
			plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/js/gutenberg-sidebar.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ],
			'3.0.0',
			true
		);

		wp_set_script_translations(
			'brag-book-gallery-gutenberg-sidebar',
			'brag-book-gallery'
		);
	}

	/**
	 * Check if Gutenberg is active for the current screen
	 *
	 * @return bool
	 *@since 3.0.0
	 * @deprecated 3.0.0 No longer used - meta boxes are shown for all editors
	 */
	private function is_gutenberg_active(): bool {
		// Always return false - we're using meta boxes for all editors now
		return false;
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
	 * Add query vars for case rewrite rules
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array Modified query vars.
	 * @since 3.0.0
	 */
	public function add_case_query_vars( array $vars ): array {
		$vars[] = 'procedures';
		$vars[] = 'procedure_slug';
		$vars[] = 'case_id';

		return $vars;
	}

	/**
	 * Add simple query vars for case detection
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array Modified query vars.
	 * @since 3.0.0
	 */
	public function add_simple_query_vars( array $vars ): array {
		$vars[] = 'case_id';
		$vars[] = 'procedure_slug';

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
	public static function save_api_response_data( int $post_id, array $api_data, ?callable $progress_callback = null ): bool {
		$case_id = $api_data['caseId'] ?? 'unknown';

		// DEBUG: Log received data
		error_log( "=== SAVE_API_RESPONSE_DATA DEBUG: Post {$post_id}, Case {$case_id} ===" );
		error_log( "API Data Keys: " . implode( ', ', array_keys( $api_data ) ) );
		error_log( "Has age: " . ( isset( $api_data['age'] ) ? 'YES (' . $api_data['age'] . ')' : 'NO' ) );
		error_log( "Has gender: " . ( isset( $api_data['gender'] ) ? 'YES (' . $api_data['gender'] . ')' : 'NO' ) );
		error_log( "Has photoSets: " . ( isset( $api_data['photoSets'] ) ? 'YES (' . count( $api_data['photoSets'] ) . ' sets)' : 'NO' ) );
		if ( isset( $api_data['photoSets'][0] ) ) {
			error_log( "First photoSet keys: " . implode( ', ', array_keys( $api_data['photoSets'][0] ) ) );
			error_log( "beforeLocationUrl: " . ( $api_data['photoSets'][0]['beforeLocationUrl'] ?? 'MISSING' ) );
			error_log( "afterLocationUrl1: " . ( $api_data['photoSets'][0]['afterLocationUrl1'] ?? 'MISSING' ) );
		}
		if ( isset( $api_data['caseDetails'][0] ) ) {
			error_log( "seoPageTitle in caseDetails: " . ( $api_data['caseDetails'][0]['seoPageTitle'] ?? 'MISSING' ) );
		}

		// Verify this is a case post
		if ( get_post_type( $post_id ) !== self::POST_TYPE_CASES ) {
			return false;
		}

		// First check: Only import cases approved for website use
		if ( ! isset( $api_data['isForWebsite'] ) || ! $api_data['isForWebsite'] ) {
			error_log( sprintf(
				'[BRAG Book Gallery] Skipping case ID %s - not approved for website (isForWebsite: %s)',
				$api_data['caseId'] ?? 'unknown',
				isset( $api_data['isForWebsite'] ) ? ( $api_data['isForWebsite'] ? 'true' : 'false' ) : 'not set'
			) );

			return false;
		}

		// Map API response fields to post meta (new format with brag_book_gallery_ prefix)
		$field_mapping = [
			// Basic Info
			'id'                => 'brag_book_gallery_procedure_case_id',
			'caseId'            => 'brag_book_gallery_case_id',
			'patientId'         => 'brag_book_gallery_patient_id',
			'userId'            => 'brag_book_gallery_user_id',
			'orgId'             => 'brag_book_gallery_org_id',
			'emrId'             => 'brag_book_gallery_emr_id',

			// Patient Data (updated for v2 API compatibility)
			'age'               => 'brag_book_gallery_patient_age',
			'gender'            => 'brag_book_gallery_gender', // Updated meta key for v2
			'ethnicity'         => 'brag_book_gallery_ethnicity',
			'height'            => 'brag_book_gallery_height',
			'heightUnit'        => 'brag_book_gallery_height_unit',
			'weight'            => 'brag_book_gallery_weight',
			'weightUnit'        => 'brag_book_gallery_weight_unit',

			// Settings
			'qualityScore'      => 'brag_book_gallery_quality_score',
			'approvedForSocial' => 'brag_book_gallery_approved_for_social',
			'isForTablet'       => 'brag_book_gallery_is_for_tablet',
			'isForWebsite'      => 'brag_book_gallery_is_for_website',
			'draft'             => 'brag_book_gallery_draft',
			'noWatermark'       => 'brag_book_gallery_no_watermark',

			// Additional fields
			'details'           => 'brag_book_gallery_notes',

			// v2-specific fields
			'description'       => 'brag_book_gallery_notes', // v2 description maps to notes field
			'createdAt'         => 'brag_book_gallery_created_at',
			'updatedAt'         => 'brag_book_gallery_updated_at',
		];


		// Save basic fields
		if ( $progress_callback ) {
			$progress_callback( "Writing case data fields for post {$post_id}..." );
		}

	// Save field mappings
		foreach ( $field_mapping as $api_field => $meta_key ) {
				if ( isset( $api_data[ $api_field ] ) ) {
					$value = $api_data[ $api_field ];

					// DEBUG: Log specific fields we're tracking
					if ( in_array( $api_field, [ 'age', 'gender', 'ethnicity', 'height', 'weight' ] ) ) {
						error_log( "Saving field '{$api_field}' => '{$meta_key}': " . var_export( $value, true ) );
					}

					// Convert boolean values
					if ( is_bool( $value ) ) {
						$value = $value ? '1' : '0';
					}

					// Sanitize based on field type
					if ( in_array( $meta_key, [
						'brag_book_gallery_case_id',
					'brag_book_gallery_procedure_case_id',
						'brag_book_gallery_patient_age',
						'brag_book_gallery_height',
						'brag_book_gallery_weight',
						'brag_book_gallery_quality_score',
						'brag_book_gallery_after1_timeframe',
						'brag_book_gallery_after2_timeframe'
					] ) ) {
						$value = absint( $value );
					} elseif ( in_array( $meta_key, [
						'brag_book_gallery_notes'
					] ) ) {
						$value = wp_kses_post( $value );
					} elseif ( in_array( $meta_key, [
						'brag_book_gallery_case_before_url',
						'brag_book_gallery_case_after_url1',
						'brag_book_gallery_case_after_url2',
						'brag_book_gallery_case_after_url3',
						'brag_book_gallery_case_post_processed_url',
						'brag_book_gallery_case_high_res_url'
					] ) ) {
						$value = sanitize_textarea_field( $value );
					} else {
						$value = sanitize_text_field( $value );
					}

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

		// Handle creator/doctor information
		if ( isset( $api_data['creator'] ) && is_array( $api_data['creator'] ) ) {
			$creator = $api_data['creator'];

			// Map creator.id to member ID
			if ( isset( $creator['id'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_member_id', absint( $creator['id'] ) );
			}

			// Map creator.profileLink to profile link
			if ( isset( $creator['profileLink'] ) && ! empty( $creator['profileLink'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_profile_link', esc_url_raw( $creator['profileLink'] ) );
			}

			// Build doctor name from firstName + lastName + ', ' + suffix
			$doctor_name_parts = [];
			if ( isset( $creator['firstName'] ) && ! empty( $creator['firstName'] ) ) {
				$doctor_name_parts[] = sanitize_text_field( $creator['firstName'] );
			}
			if ( isset( $creator['lastName'] ) && ! empty( $creator['lastName'] ) ) {
				$doctor_name_parts[] = sanitize_text_field( $creator['lastName'] );
			}

			if ( ! empty( $doctor_name_parts ) ) {
				$doctor_name = implode( ' ', $doctor_name_parts );

				// Add suffix if present
				if ( isset( $creator['suffix'] ) && ! empty( $creator['suffix'] ) ) {
					$doctor_name .= ', ' . sanitize_text_field( $creator['suffix'] );
				}

				update_post_meta( $post_id, 'brag_book_gallery_doctor_name', $doctor_name );
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

		// Update post title based on naming logic
		// Use the assigned taxonomy term name, not procedureIds (which may have multiple)
		$case_id    = $api_data['id'] ?? $post_id;
		$post_title = $seo_headline ?: '';

		// If no seoHeadline, generate title from assigned procedure taxonomy
		if ( ! $post_title ) {
			// Get the procedure taxonomy term assigned to this post
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
					$procedure_terms = get_terms( [
						'taxonomy'   => \BRAGBookGallery\Includes\Extend\Taxonomies::TAXONOMY_PROCEDURES,
						'meta_key'   => 'procedure_id',
						'meta_value' => $first_procedure_id,
						'hide_empty' => false,
						'number'     => 1,
					] );

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
		}

		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $post_title ?: 'Untitled Case',
		] );

		// Apply SEO metadata if we have an SEO plugin installed
		if ( $seo_page_title || $seo_page_description ) {
			self::apply_seo_metadata( $post_id, $seo_page_title, $seo_page_description );
		}

		// Handle procedure details
		if ( isset( $api_data['procedureDetails'] ) && is_array( $api_data['procedureDetails'] ) ) {
			// Save the full procedure details as JSON for reference (both modern and legacy)
			update_post_meta( $post_id, 'brag_book_gallery_procedure_details', wp_json_encode( $api_data['procedureDetails'] ) );
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

			// Clear existing URL fields before populating fresh data from API
			// Updated for v2 API - removed after_url2 and after_url3
			$url_fields_to_clear = [
				'brag_book_gallery_case_before_url',
				'brag_book_gallery_case_after_url',
				'brag_book_gallery_case_after_url1', // Old field name, will be deleted
				'brag_book_gallery_case_after_url2', // Old field, will be deleted
				'brag_book_gallery_case_after_url3', // Old field, will be deleted
				'brag_book_gallery_case_post_processed_url',
				'brag_book_gallery_case_high_res_url'
			];

			foreach ( $url_fields_to_clear as $field ) {
				delete_post_meta( $post_id, $field );
			}

			$photo_set_count = 0;
			$image_url_sets  = []; // New array for URL sets

			foreach ( $api_data['photoSets'] as $photo_set ) {
				$photo_set_count++;
				if ( $progress_callback ) {
					$progress_callback( "Processing image set {$photo_set_count}/{$total_photo_sets} for post {$post_id}..." );
				}
				// Get seoAltText for this photo set
				$seo_alt_text = isset( $photo_set['seoAltText'] ) ? sanitize_text_field( $photo_set['seoAltText'] ) : '';

				// Debug: Log what we're getting from the API
				error_log( "=== PHOTOSET PROCESSING DEBUG: Set {$photo_set_count} for Post {$post_id} ===" );
				error_log( "PhotoSet keys: " . implode( ', ', array_keys( $photo_set ) ) );
				error_log( "beforeLocationUrl = " . ( $photo_set['beforeLocationUrl'] ?? 'NOT SET' ) );
				error_log( "afterLocationUrl1 = " . ( $photo_set['afterLocationUrl1'] ?? 'NOT SET' ) );
				error_log( "postProcessedImageLocation = " . ( $photo_set['postProcessedImageLocation'] ?? 'NOT SET' ) );
				error_log( "highResPostProcessedImageLocation = " . ( $photo_set['highResPostProcessedImageLocation'] ?? 'NOT SET' ) );

				// Build URL set for this photo set
				// Updated for v2 API - removed after_url2 and after_url3, renamed after_url1 to after_url
				$url_set = [
					'before_url'         => isset( $photo_set['beforeLocationUrl'] ) ? esc_url_raw( $photo_set['beforeLocationUrl'] ) : '',
					'after_url'          => isset( $photo_set['afterLocationUrl1'] ) ? esc_url_raw( $photo_set['afterLocationUrl1'] ) : '',
					'post_processed_url' => isset( $photo_set['postProcessedImageLocation'] ) ? esc_url_raw( $photo_set['postProcessedImageLocation'] ) : '',
					'high_res_url'       => isset( $photo_set['highResPostProcessedImageLocation'] ) ? esc_url_raw( $photo_set['highResPostProcessedImageLocation'] ) : '',
				];

				// Debug: Log the constructed URL set
				error_log( "BRAG Book Gallery: Constructed URL set: " . wp_json_encode( $url_set ) );

				// Only add URL set if it has at least one URL
				$has_urls = false;
				foreach ( $url_set as $url ) {
					if ( ! empty( $url ) ) {
						$has_urls = true;
						break;
					}
				}

				if ( $has_urls ) {
					$image_url_sets[] = $url_set;
				}

				// Append individual URLs to textarea-formatted meta fields (new format)
				// Updated for v2 API compatibility - simplified URL fields
				$url_fields = [
					'beforeLocationUrl' => 'brag_book_gallery_case_before_url',
					'afterLocationUrl1' => 'brag_book_gallery_case_after_url', // Renamed from after_url1
					'postProcessedImageLocation' => 'brag_book_gallery_case_post_processed_url',
					'highResPostProcessedImageLocation' => 'brag_book_gallery_case_high_res_url',
				];

				foreach ( $url_fields as $api_field => $meta_key ) {
					if ( isset( $photo_set[ $api_field ] ) && ! empty( $photo_set[ $api_field ] ) ) {
						$url = esc_url_raw( $photo_set[ $api_field ] );
						$existing_urls = get_post_meta( $post_id, $meta_key, true );

						// DEBUG: Log URL field processing
						error_log( "Processing URL field: {$api_field} => {$meta_key}" );
						error_log( "URL value: {$url}" );

						// Add URL to existing list with semicolon separator
						if ( ! empty( $existing_urls ) ) {
							$updated_urls = $existing_urls . "\n" . $url . ';';
						} else {
							$updated_urls = $url . ';';
						}

						update_post_meta( $post_id, $meta_key, $updated_urls );
					}
				}

				// Clean up old after_url2 and after_url3 fields (no longer used in v2 API)
				delete_post_meta( $post_id, 'brag_book_gallery_case_after_url2' );
				delete_post_meta( $post_id, 'brag_book_gallery_case_after_url3' );
				delete_post_meta( $post_id, 'brag_book_gallery_case_after_url1' ); // Old field name

				// Save legacy individual URL fields (keep existing functionality for backwards compatibility)
				if ( isset( $photo_set['postProcessedImageLocation'] ) ) {
				}
				if ( isset( $photo_set['highResPostProcessedImageLocation'] ) ) {
				}
				if ( isset( $photo_set['seoAltText'] ) ) {
					update_post_meta( $post_id, 'brag_book_gallery_seo_alt_text', sanitize_text_field( $photo_set['seoAltText'] ) );
				}
				if ( isset( $photo_set['isNude'] ) ) {
					update_post_meta( $post_id, 'brag_book_gallery_is_nude', $photo_set['isNude'] ? '1' : '0' );
				}
			}

			// Note: Image downloading has been disabled - we only store Image URLs now

			// Save the collected image URL sets in both new and legacy formats
			if ( ! empty( $image_url_sets ) ) {
				// Debug: Log what we're saving to the database
				error_log( "BRAG Book Gallery: Saving " . count( $image_url_sets ) . " image URL sets for post {$post_id}" );
				error_log( "BRAG Book Gallery: Image URL sets being saved: " . wp_json_encode( $image_url_sets ) );

				update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $image_url_sets );
				if ( $progress_callback ) {
					$progress_callback( "Saved " . count( $image_url_sets ) . " image URL sets for post {$post_id}" );
				}
			} else {
				// Debug: Log when no image URL sets are saved
				error_log( "BRAG Book Gallery: No image URL sets to save for post {$post_id}" );
			}
		}

		// Store the full API response for reference
		update_post_meta( $post_id, 'brag_book_gallery_api_response', $api_data );

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
			'taxonomy'   => Taxonomies::TAXONOMY_PROCEDURES,
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
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'brag_book_gallery_attachment_source_url'
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'brag_book_gallery_attachment_case_post_id'
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
		$case_api_id = get_post_meta( $post_id, true ) ?: $post_id;

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
		update_post_meta( $attachment_id, 'brag_book_gallery_attachment_case_post_id', $post_id );
		update_post_meta( $attachment_id, 'brag_book_gallery_attachment_source_url', esc_url_raw( $image_url ) );
		update_post_meta( $attachment_id, 'brag_book_gallery_attachment_downloaded_date', current_time( 'mysql' ) );

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
		$api_images = get_post_meta( $post_id, 'brag_book_gallery_api_downloaded_images', true );
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
					'key'   => 'brag_book_gallery_attachment_case_post_id',
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
		delete_post_meta( $post_id, 'brag_book_gallery_api_downloaded_images' );

		// Remove deleted images from gallery
		$gallery_images = get_post_meta( $post_id, 'brag_book_gallery_gallery_images', true );
		if ( is_array( $gallery_images ) ) {
			$all_deleted_images = array_merge( $api_images ?: [], $associated_images );
			$updated_gallery    = array_diff( $gallery_images, $all_deleted_images );
			update_post_meta( $post_id, 'brag_book_gallery_gallery_images', $updated_gallery );
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
		$procedure_details_json = get_post_meta( $post_id, 'brag_book_gallery_procedure_details', true );

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
			'meta_key'       => 'brag_book_gallery_attachment_case_post_id',
			'fields'         => 'ids',
			'posts_per_page' => - 1,
		] );

		foreach ( $case_images as $attachment_id ) {
			$case_id = get_post_meta( $attachment_id, 'brag_book_gallery_attachment_case_post_id', true );

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
			'meta_key'       => 'brag_book_gallery_attachment_case_post_id',
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
			$case_id    = get_post_meta( $attachment_id, 'brag_book_gallery_attachment_case_post_id', true );
			$source_url = get_post_meta( $attachment_id, 'brag_book_gallery_attachment_source_url', true );

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
						'key'     => 'brag_book_gallery_attachment_case_post_id',
						'compare' => 'EXISTS',
					],
					[
						'key'     => 'brag_book_gallery_attachment_source_url',
						'compare' => 'EXISTS',
					],
					[
						'key'     => 'brag_book_gallery_attachment_downloaded_date',
						'compare' => 'EXISTS',
					],
				];
				break;

			case 'api_downloaded':
				// Show only API downloaded images
				$meta_query[] = [
					'key'     => 'brag_book_gallery_attachment_source_url',
					'compare' => 'EXISTS',
				];
				break;

			case 'case_uploads':
				// Show case-related uploads that are not API downloaded
				$meta_query[] = [
					'relation' => 'AND',
					[
						'key'     => 'brag_book_gallery_attachment_case_post_id',
						'compare' => 'EXISTS',
					],
					[
						'key'     => 'brag_book_gallery_attachment_source_url',
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
		$case_post_id  = get_post_meta( $attachment->ID, 'brag_book_gallery_attachment_case_post_id', true );
		$source_url    = get_post_meta( $attachment->ID, 'brag_book_gallery_attachment_source_url', true );
		$download_date = get_post_meta( $attachment->ID, 'brag_book_gallery_attachment_downloaded_date', true );

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
										html += '<h4>BRAG Book Gallery Image</h4>';
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
