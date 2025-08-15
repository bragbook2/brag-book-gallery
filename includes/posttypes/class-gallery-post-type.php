<?php
/**
 * Gallery Custom Post Type
 *
 * Registers and manages the gallery custom post type for Local mode.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\PostTypes
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\PostTypes;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Post Type Class
 *
 * Handles registration and management of the gallery custom post type.
 *
 * @since 3.0.0
 */
class Gallery_Post_Type {

	/**
	 * Post type key
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const POST_TYPE = 'brag_gallery';

	/**
	 * Post type slug
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const SLUG = 'gallery';

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the post type
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 3 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
	}

	/**
	 * Register the custom post type
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register(): void {
		$labels = array(
			'name'                  => _x( 'Galleries', 'Post type general name', 'brag-book-gallery' ),
			'singular_name'         => _x( 'Gallery', 'Post type singular name', 'brag-book-gallery' ),
			'menu_name'             => _x( 'BragBook Galleries', 'Admin Menu text', 'brag-book-gallery' ),
			'name_admin_bar'        => _x( 'Gallery', 'Add New on Toolbar', 'brag-book-gallery' ),
			'add_new'               => __( 'Add New', 'brag-book-gallery' ),
			'add_new_item'          => __( 'Add New Gallery', 'brag-book-gallery' ),
			'new_item'              => __( 'New Gallery', 'brag-book-gallery' ),
			'edit_item'             => __( 'Edit Gallery', 'brag-book-gallery' ),
			'view_item'             => __( 'View Gallery', 'brag-book-gallery' ),
			'all_items'             => __( 'All Galleries', 'brag-book-gallery' ),
			'search_items'          => __( 'Search Galleries', 'brag-book-gallery' ),
			'parent_item_colon'     => __( 'Parent Galleries:', 'brag-book-gallery' ),
			'not_found'             => __( 'No galleries found.', 'brag-book-gallery' ),
			'not_found_in_trash'    => __( 'No galleries found in Trash.', 'brag-book-gallery' ),
			'featured_image'        => _x( 'Gallery Cover Image', 'Overrides the "Featured Image" phrase', 'brag-book-gallery' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'brag-book-gallery' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'brag-book-gallery' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'brag-book-gallery' ),
			'archives'              => _x( 'Gallery archives', 'The post type archive label', 'brag-book-gallery' ),
			'insert_into_item'      => _x( 'Insert into gallery', 'Overrides the "Insert into post" phrase', 'brag-book-gallery' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this gallery', 'Overrides the "Uploaded to this post" phrase', 'brag-book-gallery' ),
			'filter_items_list'     => _x( 'Filter galleries list', 'Screen reader text', 'brag-book-gallery' ),
			'items_list_navigation' => _x( 'Galleries list navigation', 'Screen reader text', 'brag-book-gallery' ),
			'items_list'            => _x( 'Galleries list', 'Screen reader text', 'brag-book-gallery' ),
		);

		$args = array(
			'labels'                => $labels,
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'query_var'             => true,
			'rewrite'               => array( 
				'slug' => self::SLUG,
				'with_front' => false,
			),
			'capability_type'       => 'post',
			'has_archive'           => true,
			'hierarchical'          => false,
			'menu_position'         => 25,
			'menu_icon'             => 'dashicons-format-gallery',
			'supports'              => array( 
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'custom-fields',
				'revisions',
			),
			'show_in_rest'          => true,
			'rest_base'             => 'brag-galleries',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register meta fields for the post type
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_meta_fields(): void {
		$meta_fields = array(
			'_brag_case_id' => array(
				'type' => 'integer',
				'description' => 'Original API case ID',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_api_token' => array(
				'type' => 'string',
				'description' => 'Associated API token',
				'single' => true,
				'show_in_rest' => false, // Hide sensitive data from REST
			),
			'_brag_property_id' => array(
				'type' => 'integer',
				'description' => 'Website property ID',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_before_images' => array(
				'type' => 'string',
				'description' => 'Before images data',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_after_images' => array(
				'type' => 'string',
				'description' => 'After images data',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_patient_info' => array(
				'type' => 'string',
				'description' => 'Patient demographics JSON',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_procedure_details' => array(
				'type' => 'string',
				'description' => 'Procedure details JSON',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_seo_data' => array(
				'type' => 'string',
				'description' => 'SEO metadata JSON',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_last_synced' => array(
				'type' => 'string',
				'description' => 'Last sync timestamp',
				'single' => true,
				'show_in_rest' => true,
			),
			'_brag_sync_hash' => array(
				'type' => 'string',
				'description' => 'MD5 hash for change detection',
				'single' => true,
				'show_in_rest' => false,
			),
		);

		foreach ( $meta_fields as $meta_key => $args ) {
			register_post_meta( self::POST_TYPE, $meta_key, $args );
		}
	}

	/**
	 * Add meta boxes for the gallery post type
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'brag_gallery_images',
			__( 'Gallery Images', 'brag-book-gallery' ),
			array( $this, 'render_images_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'brag_gallery_patient_info',
			__( 'Patient Information', 'brag-book-gallery' ),
			array( $this, 'render_patient_info_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'brag_gallery_sync_info',
			__( 'Sync Information', 'brag-book-gallery' ),
			array( $this, 'render_sync_info_meta_box' ),
			self::POST_TYPE,
			'side',
			'low'
		);
	}

	/**
	 * Render images meta box
	 *
	 * @since 3.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_images_meta_box( $post ): void {
		wp_nonce_field( 'brag_gallery_meta_box', 'brag_gallery_meta_box_nonce' );
		
		$before_images = get_post_meta( $post->ID, '_brag_before_images', true );
		$after_images = get_post_meta( $post->ID, '_brag_after_images', true );
		
		$before_images = $before_images ? json_decode( $before_images, true ) : array();
		$after_images = $after_images ? json_decode( $after_images, true ) : array();
		?>
		<div class="brag-gallery-images-wrapper">
			<h4><?php esc_html_e( 'Before Images', 'brag-book-gallery' ); ?></h4>
			<div id="brag-before-images" class="brag-images-container">
				<?php if ( ! empty( $before_images ) ) : ?>
					<?php foreach ( $before_images as $image ) : ?>
						<div class="brag-image-item">
							<img src="<?php echo esc_url( $image['url'] ?? '' ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>" style="max-width: 150px;">
							<p><?php echo esc_html( $image['caption'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No before images', 'brag-book-gallery' ); ?></p>
				<?php endif; ?>
			</div>

			<h4><?php esc_html_e( 'After Images', 'brag-book-gallery' ); ?></h4>
			<div id="brag-after-images" class="brag-images-container">
				<?php if ( ! empty( $after_images ) ) : ?>
					<?php foreach ( $after_images as $image ) : ?>
						<div class="brag-image-item">
							<img src="<?php echo esc_url( $image['url'] ?? '' ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>" style="max-width: 150px;">
							<p><?php echo esc_html( $image['caption'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No after images', 'brag-book-gallery' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<style>
			.brag-images-container {
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				margin-bottom: 20px;
			}
			.brag-image-item {
				border: 1px solid #ddd;
				padding: 10px;
				border-radius: 4px;
			}
		</style>
		<?php
	}

	/**
	 * Render patient info meta box
	 *
	 * @since 3.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_patient_info_meta_box( $post ): void {
		$patient_info = get_post_meta( $post->ID, '_brag_patient_info', true );
		$patient_info = $patient_info ? json_decode( $patient_info, true ) : array();
		?>
		<p>
			<label for="brag_patient_age"><?php esc_html_e( 'Age:', 'brag-book-gallery' ); ?></label><br>
			<input type="text" id="brag_patient_age" name="brag_patient_age" value="<?php echo esc_attr( $patient_info['age'] ?? '' ); ?>" class="widefat">
		</p>
		<p>
			<label for="brag_patient_gender"><?php esc_html_e( 'Gender:', 'brag-book-gallery' ); ?></label><br>
			<select id="brag_patient_gender" name="brag_patient_gender" class="widefat">
				<option value=""><?php esc_html_e( 'Select Gender', 'brag-book-gallery' ); ?></option>
				<option value="male" <?php selected( $patient_info['gender'] ?? '', 'male' ); ?>><?php esc_html_e( 'Male', 'brag-book-gallery' ); ?></option>
				<option value="female" <?php selected( $patient_info['gender'] ?? '', 'female' ); ?>><?php esc_html_e( 'Female', 'brag-book-gallery' ); ?></option>
			</select>
		</p>
		<p>
			<label for="brag_patient_height"><?php esc_html_e( 'Height:', 'brag-book-gallery' ); ?></label><br>
			<input type="text" id="brag_patient_height" name="brag_patient_height" value="<?php echo esc_attr( $patient_info['height'] ?? '' ); ?>" class="widefat">
		</p>
		<p>
			<label for="brag_patient_weight"><?php esc_html_e( 'Weight:', 'brag-book-gallery' ); ?></label><br>
			<input type="text" id="brag_patient_weight" name="brag_patient_weight" value="<?php echo esc_attr( $patient_info['weight'] ?? '' ); ?>" class="widefat">
		</p>
		<p>
			<label for="brag_patient_ethnicity"><?php esc_html_e( 'Ethnicity:', 'brag-book-gallery' ); ?></label><br>
			<input type="text" id="brag_patient_ethnicity" name="brag_patient_ethnicity" value="<?php echo esc_attr( $patient_info['ethnicity'] ?? '' ); ?>" class="widefat">
		</p>
		<?php
	}

	/**
	 * Render sync info meta box
	 *
	 * @since 3.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_sync_info_meta_box( $post ): void {
		$case_id = get_post_meta( $post->ID, '_brag_case_id', true );
		$property_id = get_post_meta( $post->ID, '_brag_property_id', true );
		$last_synced = get_post_meta( $post->ID, '_brag_last_synced', true );
		?>
		<p>
			<strong><?php esc_html_e( 'Case ID:', 'brag-book-gallery' ); ?></strong><br>
			<?php echo esc_html( $case_id ?: __( 'Not synced', 'brag-book-gallery' ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Property ID:', 'brag-book-gallery' ); ?></strong><br>
			<?php echo esc_html( $property_id ?: __( 'Not set', 'brag-book-gallery' ) ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Last Synced:', 'brag-book-gallery' ); ?></strong><br>
			<?php 
			if ( $last_synced ) {
				echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_synced ) ) );
			} else {
				esc_html_e( 'Never', 'brag-book-gallery' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Save meta box data
	 *
	 * @since 3.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function save_meta( $post_id, $post, $update ): void {
		// Check nonce
		if ( ! isset( $_POST['brag_gallery_meta_box_nonce'] ) || 
			 ! wp_verify_nonce( $_POST['brag_gallery_meta_box_nonce'], 'brag_gallery_meta_box' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save patient info
		$patient_info = array(
			'age' => sanitize_text_field( $_POST['brag_patient_age'] ?? '' ),
			'gender' => sanitize_text_field( $_POST['brag_patient_gender'] ?? '' ),
			'height' => sanitize_text_field( $_POST['brag_patient_height'] ?? '' ),
			'weight' => sanitize_text_field( $_POST['brag_patient_weight'] ?? '' ),
			'ethnicity' => sanitize_text_field( $_POST['brag_patient_ethnicity'] ?? '' ),
		);

		update_post_meta( $post_id, '_brag_patient_info', wp_json_encode( $patient_info ) );
	}

	/**
	 * Add custom columns to admin list
	 *
	 * @since 3.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_admin_columns( $columns ): array {
		$new_columns = array();
		
		foreach ( $columns as $key => $title ) {
			if ( $key === 'title' ) {
				$new_columns[$key] = $title;
				$new_columns['case_id'] = __( 'Case ID', 'brag-book-gallery' );
				$new_columns['sync_status'] = __( 'Sync Status', 'brag-book-gallery' );
			} elseif ( $key !== 'date' ) {
				$new_columns[$key] = $title;
			}
		}
		
		$new_columns['date'] = $columns['date'];
		
		return $new_columns;
	}

	/**
	 * Render custom admin columns
	 *
	 * @since 3.0.0
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_columns( $column, $post_id ): void {
		switch ( $column ) {
			case 'case_id':
				$case_id = get_post_meta( $post_id, '_brag_case_id', true );
				echo esc_html( $case_id ?: '—' );
				break;
				
			case 'sync_status':
				$last_synced = get_post_meta( $post_id, '_brag_last_synced', true );
				if ( $last_synced ) {
					$time_diff = time() - strtotime( $last_synced );
					if ( $time_diff < DAY_IN_SECONDS ) {
						echo '<span style="color: green;">●</span> ' . esc_html__( 'Synced', 'brag-book-gallery' );
					} elseif ( $time_diff < WEEK_IN_SECONDS ) {
						echo '<span style="color: orange;">●</span> ' . esc_html__( 'Outdated', 'brag-book-gallery' );
					} else {
						echo '<span style="color: red;">●</span> ' . esc_html__( 'Stale', 'brag-book-gallery' );
					}
				} else {
					echo '<span style="color: gray;">●</span> ' . esc_html__( 'Not synced', 'brag-book-gallery' );
				}
				break;
		}
	}
}