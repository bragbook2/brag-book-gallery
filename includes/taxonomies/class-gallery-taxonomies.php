<?php
/**
 * Gallery Taxonomies
 *
 * Registers and manages medical categories and procedures taxonomies.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Taxonomies
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Taxonomies;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Taxonomies Class
 *
 * Handles registration and management of gallery taxonomies.
 *
 * @since 3.0.0
 */
class Gallery_Taxonomies {

	/**
	 * Medical categories taxonomy key
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const CATEGORY_TAXONOMY = 'brag_category';

	/**
	 * Procedures taxonomy key
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const PROCEDURE_TAXONOMY = 'brag_procedure';

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Only initialize if in Local Mode
		$current_mode = get_option( 'brag_book_gallery_current_mode', 'javascript' );
		if ( $current_mode === 'local' ) {
			$this->init();
		}
	}

	/**
	 * Initialize taxonomies
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		add_action( 'init', array( $this, 'register_categories' ) );
		add_action( 'init', array( $this, 'register_procedures' ) );
		add_action( 'init', array( $this, 'register_term_meta' ) );

		// Add form fields for taxonomies
		add_action( self::CATEGORY_TAXONOMY . '_add_form_fields', array( $this, 'add_category_fields' ) );
		add_action( self::CATEGORY_TAXONOMY . '_edit_form_fields', array( $this, 'edit_category_fields' ) );
		add_action( self::PROCEDURE_TAXONOMY . '_add_form_fields', array( $this, 'add_procedure_fields' ) );
		add_action( self::PROCEDURE_TAXONOMY . '_edit_form_fields', array( $this, 'edit_procedure_fields' ) );

		// Save term meta
		add_action( 'created_' . self::CATEGORY_TAXONOMY, array( $this, 'save_category_meta' ) );
		add_action( 'edited_' . self::CATEGORY_TAXONOMY, array( $this, 'save_category_meta' ) );
		add_action( 'created_' . self::PROCEDURE_TAXONOMY, array( $this, 'save_procedure_meta' ) );
		add_action( 'edited_' . self::PROCEDURE_TAXONOMY, array( $this, 'save_procedure_meta' ) );
	}

	/**
	 * Register medical categories taxonomy
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_categories(): void {
		$labels = array(
			'name'                       => _x( 'Medical Categories', 'taxonomy general name', 'brag-book-gallery' ),
			'singular_name'              => _x( 'Medical Category', 'taxonomy singular name', 'brag-book-gallery' ),
			'search_items'               => __( 'Search Medical Categories', 'brag-book-gallery' ),
			'popular_items'              => __( 'Popular Medical Categories', 'brag-book-gallery' ),
			'all_items'                  => __( 'All Medical Categories', 'brag-book-gallery' ),
			'parent_item'                => __( 'Parent Medical Category', 'brag-book-gallery' ),
			'parent_item_colon'          => __( 'Parent Medical Category:', 'brag-book-gallery' ),
			'edit_item'                  => __( 'Edit Medical Category', 'brag-book-gallery' ),
			'update_item'                => __( 'Update Medical Category', 'brag-book-gallery' ),
			'add_new_item'               => __( 'Add New Medical Category', 'brag-book-gallery' ),
			'new_item_name'              => __( 'New Medical Category Name', 'brag-book-gallery' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'brag-book-gallery' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'brag-book-gallery' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'brag-book-gallery' ),
			'not_found'                  => __( 'No categories found.', 'brag-book-gallery' ),
			'menu_name'                  => __( 'Medical Categories', 'brag-book-gallery' ),
		);

		$args = array(
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'show_in_nav_menus'     => true,
			'show_tagcloud'         => false,
			'query_var'             => true,
			'rewrite'               => array(
				'slug' => 'gallery-category',
				'with_front' => false,
				'hierarchical' => true,
			),
			'show_in_rest'          => true,
			'rest_base'             => 'brag-categories',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
		);

		register_taxonomy( self::CATEGORY_TAXONOMY, array( Gallery_Post_Type::POST_TYPE ), $args );
	}

	/**
	 * Register procedures taxonomy
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_procedures(): void {
		$labels = array(
			'name'                       => _x( 'Procedures', 'taxonomy general name', 'brag-book-gallery' ),
			'singular_name'              => _x( 'Procedure', 'taxonomy singular name', 'brag-book-gallery' ),
			'search_items'               => __( 'Search Procedures', 'brag-book-gallery' ),
			'popular_items'              => __( 'Popular Procedures', 'brag-book-gallery' ),
			'all_items'                  => __( 'All Procedures', 'brag-book-gallery' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'edit_item'                  => __( 'Edit Procedure', 'brag-book-gallery' ),
			'update_item'                => __( 'Update Procedure', 'brag-book-gallery' ),
			'add_new_item'               => __( 'Add New Procedure', 'brag-book-gallery' ),
			'new_item_name'              => __( 'New Procedure Name', 'brag-book-gallery' ),
			'separate_items_with_commas' => __( 'Separate procedures with commas', 'brag-book-gallery' ),
			'add_or_remove_items'        => __( 'Add or remove procedures', 'brag-book-gallery' ),
			'choose_from_most_used'      => __( 'Choose from the most used procedures', 'brag-book-gallery' ),
			'not_found'                  => __( 'No procedures found.', 'brag-book-gallery' ),
			'menu_name'                  => __( 'Procedures', 'brag-book-gallery' ),
		);

		$args = array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'show_in_nav_menus'     => true,
			'show_tagcloud'         => true,
			'query_var'             => true,
			'rewrite'               => array(
				'slug' => 'gallery-procedure',
				'with_front' => false,
			),
			'show_in_rest'          => true,
			'rest_base'             => 'brag-procedures',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
		);

		register_taxonomy( self::PROCEDURE_TAXONOMY, array( Gallery_Post_Type::POST_TYPE ), $args );
	}

	/**
	 * Register term meta fields
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_term_meta(): void {
		// Category meta
		register_term_meta(
			self::CATEGORY_TAXONOMY,
			'brag_category_api_id',
			array(
				'type' => 'integer',
				'description' => 'API category ID',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		register_term_meta(
			self::CATEGORY_TAXONOMY,
			'brag_category_order',
			array(
				'type' => 'integer',
				'description' => 'Display order',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		// Procedure meta
		register_term_meta(
			self::PROCEDURE_TAXONOMY,
			'brag_procedure_api_id',
			array(
				'type' => 'integer',
				'description' => 'API procedure ID',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		register_term_meta(
			self::PROCEDURE_TAXONOMY,
			'brag_procedure_slug_name',
			array(
				'type' => 'string',
				'description' => 'URL slug name',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		register_term_meta(
			self::PROCEDURE_TAXONOMY,
			'brag_procedure_nudity',
			array(
				'type' => 'boolean',
				'description' => 'Contains nudity',
				'single' => true,
				'show_in_rest' => true,
			)
		);

		register_term_meta(
			self::PROCEDURE_TAXONOMY,
			'brag_procedure_case_count',
			array(
				'type' => 'integer',
				'description' => 'Total case count',
				'single' => true,
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Add category form fields
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_category_fields(): void {
		?>
		<div class="form-field">
			<label for="brag_category_api_id"><?php esc_html_e( 'API Category ID', 'brag-book-gallery' ); ?></label>
			<input type="number" name="brag_category_api_id" id="brag_category_api_id" value="">
			<p class="description"><?php esc_html_e( 'The category ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
		</div>
		<div class="form-field">
			<label for="brag_category_order"><?php esc_html_e( 'Display Order', 'brag-book-gallery' ); ?></label>
			<input type="number" name="brag_category_order" id="brag_category_order" value="0">
			<p class="description"><?php esc_html_e( 'Order for displaying this category.', 'brag-book-gallery' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit category form fields
	 *
	 * @since 3.0.0
	 * @param WP_Term $term Current term object.
	 * @return void
	 */
	public function edit_category_fields( $term ): void {
		$api_id = get_term_meta( $term->term_id, 'brag_category_api_id', true );
		$order = get_term_meta( $term->term_id, 'brag_category_order', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_category_api_id"><?php esc_html_e( 'API Category ID', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="brag_category_api_id" id="brag_category_api_id" value="<?php echo esc_attr( $api_id ); ?>">
				<p class="description"><?php esc_html_e( 'The category ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_category_order"><?php esc_html_e( 'Display Order', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="brag_category_order" id="brag_category_order" value="<?php echo esc_attr( $order ); ?>">
				<p class="description"><?php esc_html_e( 'Order for displaying this category.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Add procedure form fields
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_procedure_fields(): void {
		?>
		<div class="form-field">
			<label for="brag_procedure_api_id"><?php esc_html_e( 'API Procedure ID', 'brag-book-gallery' ); ?></label>
			<input type="number" name="brag_procedure_api_id" id="brag_procedure_api_id" value="">
			<p class="description"><?php esc_html_e( 'The procedure ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
		</div>
		<div class="form-field">
			<label for="brag_procedure_slug_name"><?php esc_html_e( 'URL Slug', 'brag-book-gallery' ); ?></label>
			<input type="text" name="brag_procedure_slug_name" id="brag_procedure_slug_name" value="">
			<p class="description"><?php esc_html_e( 'URL-friendly slug for this procedure.', 'brag-book-gallery' ); ?></p>
		</div>
		<div class="form-field">
			<label for="brag_procedure_nudity"><?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?></label>
			<input type="checkbox" name="brag_procedure_nudity" id="brag_procedure_nudity" value="1">
			<p class="description"><?php esc_html_e( 'Check if this procedure contains nudity.', 'brag-book-gallery' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Edit procedure form fields
	 *
	 * @since 3.0.0
	 * @param WP_Term $term Current term object.
	 * @return void
	 */
	public function edit_procedure_fields( $term ): void {
		$api_id = get_term_meta( $term->term_id, 'brag_procedure_api_id', true );
		$slug_name = get_term_meta( $term->term_id, 'brag_procedure_slug_name', true );
		$nudity = get_term_meta( $term->term_id, 'brag_procedure_nudity', true );
		$case_count = get_term_meta( $term->term_id, 'brag_procedure_case_count', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_procedure_api_id"><?php esc_html_e( 'API Procedure ID', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="number" name="brag_procedure_api_id" id="brag_procedure_api_id" value="<?php echo esc_attr( $api_id ); ?>">
				<p class="description"><?php esc_html_e( 'The procedure ID from the BRAG book API.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_procedure_slug_name"><?php esc_html_e( 'URL Slug', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="text" name="brag_procedure_slug_name" id="brag_procedure_slug_name" value="<?php echo esc_attr( $slug_name ); ?>">
				<p class="description"><?php esc_html_e( 'URL-friendly slug for this procedure.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="brag_procedure_nudity"><?php esc_html_e( 'Contains Nudity', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<input type="checkbox" name="brag_procedure_nudity" id="brag_procedure_nudity" value="1" <?php checked( $nudity, '1' ); ?>>
				<p class="description"><?php esc_html_e( 'Check if this procedure contains nudity.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label><?php esc_html_e( 'Case Count', 'brag-book-gallery' ); ?></label>
			</th>
			<td>
				<strong><?php echo esc_html( $case_count ?: '0' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Total number of cases for this procedure.', 'brag-book-gallery' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save category meta
	 *
	 * @since 3.0.0
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_category_meta( $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
		if ( isset( $_POST['brag_category_api_id'] ) ) {
			update_term_meta(
				$term_id,
				'brag_category_api_id',
				absint( $_POST['brag_category_api_id'] )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
		if ( isset( $_POST['brag_category_order'] ) ) {
			update_term_meta(
				$term_id,
				'brag_category_order',
				absint( $_POST['brag_category_order'] )
			);
		}
	}

	/**
	 * Save procedure meta
	 *
	 * @since 3.0.0
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_procedure_meta( $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
		if ( isset( $_POST['brag_procedure_api_id'] ) ) {
			update_term_meta(
				$term_id,
				'brag_procedure_api_id',
				absint( $_POST['brag_procedure_api_id'] )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
		if ( isset( $_POST['brag_procedure_slug_name'] ) ) {
			update_term_meta(
				$term_id,
				'brag_procedure_slug_name',
				sanitize_text_field( $_POST['brag_procedure_slug_name'] )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress handles nonce verification for term saves
		if ( isset( $_POST['brag_procedure_nudity'] ) ) {
			update_term_meta(
				$term_id,
				'brag_procedure_nudity',
				'1'
			);
		} else {
			delete_term_meta( $term_id, 'brag_procedure_nudity' );
		}
	}
}
