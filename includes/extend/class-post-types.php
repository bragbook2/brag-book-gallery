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
use WP_Post;

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
			'save_post',
			array( $this, 'save_case_meta' )
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
			'has_archive'        => true,
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
		$doctor_name          = get_post_meta( $post->ID, 'brag_book_gallery_doctor_name', true );
		$doctor_profile_url   = get_post_meta( $post->ID, 'brag_book_gallery_doctor_profile_url', true );
		$doctor_suffix        = get_post_meta( $post->ID, 'brag_book_gallery_doctor_suffix', true );
		$member_id            = get_post_meta( $post->ID, 'brag_book_gallery_member_id', true );

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

		// Image Fields.
		$url_fields = array(
			'brag_book_gallery_case_before_url'          => esc_html__( 'Before Image URLs', 'brag-book-gallery' ),
			'brag_book_gallery_case_after_url'           => esc_html__( 'After Image URLs', 'brag-book-gallery' ),
			'brag_book_gallery_case_after_plus_url'      => esc_html__( 'After Plus Image URLs', 'brag-book-gallery' ),
			'brag_book_gallery_case_post_processed_url'  => esc_html__( 'Post-Processed Image URLs', 'brag-book-gallery' ),
			'brag_book_gallery_case_high_res_url'        => esc_html__( 'High-Res Post-Processed Image URLs', 'brag-book-gallery' ),
		);
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
										for="brag_book_gallery_doctor_profile_url"><?php esc_html_e( 'Doctor Profile URL', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="url" id="brag_book_gallery_doctor_profile_url"
										   name="brag_book_gallery_doctor_profile_url"
										   value="<?php echo esc_url( $doctor_profile_url ); ?>"
										   class="regular-text"/>
									<p class="description"><?php esc_html_e( 'URL to the doctor\'s profile page', 'brag-book-gallery' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label
										for="brag_book_gallery_doctor_suffix"><?php esc_html_e( 'Doctor Suffix', 'brag-book-gallery' ); ?></label>
								</th>
								<td>
									<input type="text" id="brag_book_gallery_doctor_suffix"
										   name="brag_book_gallery_doctor_suffix"
										   value="<?php echo esc_attr( $doctor_suffix ); ?>"
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
						<table class="form-table">
							<?php
							foreach ( $url_fields as $meta_key => $label ) :
								// Get the current value from the new meta field format
								$urls_value = get_post_meta( $post->ID, $meta_key, true );

								// Clean up the display value (remove extra semicolons for display)
								$display_value = '';
								if ( ! empty( $urls_value ) ) {
									$lines       = explode( "\n", $urls_value );
									$clean_lines = array();
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

			.image-url-set-header h5 {
				margin: 0;
				font-size: 15px;
				font-weight: 600;
				color: #1d2327;
				display: flex;
				align-items: center;
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

			.brag-book-section-header h4 {
				margin: 0;
				font-size: 15px;
				font-weight: 600;
				color: #1d2327;
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
			document.addEventListener( 'DOMContentLoaded', () => {
				const navTabs = document.querySelectorAll( '.nav-tab' );

				navTabs.forEach( tab => {
					tab.addEventListener( 'click', ( e ) => {
						e.preventDefault();
						const target = tab.getAttribute( 'href' );

						// Remove active class from all tabs and content
						document.querySelectorAll( '.nav-tab' ).forEach( t => t.classList.remove( 'nav-tab-active' ) );
						document.querySelectorAll( '.tab-content' ).forEach( c => c.classList.remove( 'active' ) );

						// Add active class to clicked tab and corresponding content
						tab.classList.add( 'nav-tab-active' );
						const targetElement = document.querySelector( target );
						if ( targetElement ) {
							targetElement.classList.add( 'active' );
						}
					} );
				} );
			} );
		</script>
		<?php
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
			'brag_book_gallery_doctor_name'            => 'sanitize_text_field',
			'brag_book_gallery_doctor_profile_url'     => 'esc_url_raw',
			'brag_book_gallery_doctor_suffix'          => 'sanitize_text_field',
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

		// Handle Gutenberg image URL sets (JSON format)
		if ( isset( $_POST['brag_book_gallery_image_url_sets'] ) ) {
			$json_data      = sanitize_text_field( wp_unslash( $_POST['brag_book_gallery_image_url_sets'] ) );
			$image_url_sets = json_decode( $json_data, true );

			if ( is_array( $image_url_sets ) ) {
				$sanitized_sets = array();

				foreach ( $image_url_sets as $index => $url_set ) {
					if ( ! is_array( $url_set ) ) {
						continue;
					}

					$sanitized_set = array();
					$has_content   = false;

					$url_fields = array(
						'before_url',
						'after_url',
						'post_processed_url',
						'high_res_url',
					);

					foreach ( $url_fields as $url_field ) {
						$url                      = isset( $url_set[ $url_field ] ) ? esc_url_raw( trim( $url_set[ $url_field ] ) ) : '';
						$sanitized_set[ $url_field ] = $url;
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
			}
		}

		// Handle new individual URL fields from meta boxes
		$url_fields = array(
			'brag_book_gallery_case_before_url',
			'brag_book_gallery_case_after_url',
			'brag_book_gallery_case_post_processed_url',
			'brag_book_gallery_case_high_res_url'
		);

		foreach ( $url_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$urls_input = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );

				// Process the textarea input: split by lines, trim, add semicolons
				$lines           = explode( "\n", $urls_input );
				$processed_lines = array();

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

		// Save image URLs from separate textareas
		if ( isset( $_POST['case_api_data_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['case_api_data_nonce'] ) ), 'save_case_api_data' ) ) {
			$url_types    = array( 'before_url', 'after_url1', 'after_url2', 'after_url3', 'post_processed_url', 'high_res_url' );
			$urls_by_type = array();

			// Collect URLs from each textarea
			foreach ( $url_types as $url_type ) {
				if ( isset( $_POST[ 'case_' . $url_type ] ) ) {
					$textarea_content = sanitize_textarea_field( wp_unslash( $_POST[ 'case_' . $url_type ] ) );
					$urls             = array_filter( array_map( 'trim', explode( "\n", $textarea_content ) ) );

					// Sanitize URLs
					$sanitized_urls = array();
					foreach ( $urls as $url ) {
						$clean_url = esc_url_raw( $url );
						if ( ! empty( $clean_url ) ) {
							$sanitized_urls[] = $clean_url;
						}
					}
					$urls_by_type[ $url_type ] = $sanitized_urls;
				} else {
					$urls_by_type[ $url_type ] = array();
				}
			}

			// Convert to URL sets format (each row corresponds to one set)
			$max_urls       = max( array_map( 'count', $urls_by_type ) );
			$image_url_sets = array();

			for ( $i = 0; $i < $max_urls; $i++ ) {
				$set = array(
					'before_url'         => $urls_by_type['before_url'][ $i ] ?? '',
					'after_url'         => $urls_by_type['after_url'][ $i ] ?? '',
					'post_processed_url' => $urls_by_type['post_processed_url'][ $i ] ?? '',
					'high_res_url'       => $urls_by_type['high_res_url'][ $i ] ?? '',
				);

				// Only add sets that have at least one URL
				if ( array_filter( $set ) ) {
					$image_url_sets[] = $set;
				}
			}

			// Save in both new and legacy formats
			update_post_meta( $post_id, 'brag_book_gallery_image_url_sets', $image_url_sets );
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
	 * - Doctor/creator information
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
				} elseif (
					in_array(
						$meta_key,
						array(
							'brag_book_gallery_case_before_url',
							'brag_book_gallery_case_after_url',
							'brag_book_gallery_case_post_processed_url',
							'brag_book_gallery_case_high_res_url',
						),
						true
					)
				) {
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

		// Handle creator/doctor information.
		if ( isset( $api_data['creator'] ) && is_array( $api_data['creator'] ) ) {
			$creator = $api_data['creator'];

			// Map creator.id to member ID
			if ( isset( $creator['id'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_member_id', absint( $creator['id'] ) );
			}

			// Map creator.profileLink to doctor profile URL
			if ( isset( $creator['profileLink'] ) && ! empty( $creator['profileLink'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_doctor_profile_url', esc_url_raw( $creator['profileLink'] ) );
			}

			// Map creator.suffix to doctor suffix
			if ( isset( $creator['suffix'] ) && ! empty( $creator['suffix'] ) ) {
				update_post_meta( $post_id, 'brag_book_gallery_doctor_suffix', sanitize_text_field( $creator['suffix'] ) );
			}

			// Build doctor name from firstName + lastName + ', ' + suffix
			$doctor_name_parts = array();
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
			);

			foreach ( $url_fields_to_clear as $field ) {
				delete_post_meta( $post_id, $field );
			}

			$photo_set_count = 0;
			$image_url_sets  = array();

			foreach ( $api_data['photoSets'] as $photo_set ) {
				++$photo_set_count;

				if ( $progress_callback ) {
					$progress_callback( "Processing image set {$photo_set_count}/{$total_photo_sets} for post {$post_id}..." );
				}

				// Extract images object from photo set (new API structure)
				$images = isset( $photo_set['images'] ) && is_array( $photo_set['images'] ) ? $photo_set['images'] : array();

				// Support both old flat structure and new nested structure
				// Try new nested structure first, fallback to old flat structure
				$before_url         = '';
				$after_url          = '';
				$after_plus_url     = '';
				$post_processed_url = '';
				$high_res_url       = '';

				if ( ! empty( $images ) ) {
					// New nested structure
					$before_url         = isset( $images['before']['url'] ) ? esc_url_raw( $images['before']['url'] ) : '';
					$after_url          = isset( $images['after']['url'] ) ? esc_url_raw( $images['after']['url'] ) : '';
					$after_plus_url     = isset( $images['afterPlus']['url'] ) ? esc_url_raw( $images['afterPlus']['url'] ) : '';
					$post_processed_url = isset( $images['sideBySide']['standard']['url'] ) ? esc_url_raw( $images['sideBySide']['standard']['url'] ) : '';
					$high_res_url       = isset( $images['sideBySide']['highDefinition']['url'] ) ? esc_url_raw( $images['sideBySide']['highDefinition']['url'] ) : '';
				} else {
					// Old flat structure (fallback)
					$before_url         = isset( $photo_set['beforeLocationUrl'] ) ? esc_url_raw( $photo_set['beforeLocationUrl'] ) : '';
					$after_url          = isset( $photo_set['afterLocationUrl1'] ) ? esc_url_raw( $photo_set['afterLocationUrl1'] ) : '';
					$post_processed_url = isset( $photo_set['postProcessedImageLocation'] ) ? esc_url_raw( $photo_set['postProcessedImageLocation'] ) : '';
					$high_res_url       = isset( $photo_set['highResPostProcessedImageLocation'] ) ? esc_url_raw( $photo_set['highResPostProcessedImageLocation'] ) : '';
				}

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

				if ( isset( $photo_set['seoAltText'] ) ) {
					update_post_meta( $post_id, 'brag_book_gallery_seo_alt_text', sanitize_text_field( $photo_set['seoAltText'] ) );
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
		}

		// Store the full API response for reference
		update_post_meta( $post_id, 'brag_book_gallery_api_response', $api_data );

		return true;
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
