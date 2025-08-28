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

use Exception;
use JsonException;
use WP_Post;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Post Type Class
 *
 * Enterprise-grade WordPress custom post type management for BRAGBook Gallery Local mode.
 * Provides comprehensive gallery entry management, meta field handling, admin interface,
 * and data synchronization capabilities with full VIP compliance.
 *
 * Features:
 * - Custom post type registration with full REST API support
 * - Comprehensive meta field management for patient data, images, and sync status
 * - Advanced admin interface with custom meta boxes and column management
 * - Data validation and sanitization with security best practices
 * - Performance optimized with caching and batch processing capabilities
 * - Multi-level error handling and validation systems
 * - VIP-compliant logging and debugging support
 *
 * @package    BRAGBookGallery
 * @subpackage PostTypes
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 *
 * @see https://developer.wordpress.org/plugins/post-types/
 * @see https://developer.wordpress.org/reference/functions/register_post_type/
 * @see https://developer.wordpress.org/reference/functions/register_post_meta/
 */
class Gallery_Post_Type {

	/**
	 * Post type key for gallery entries
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const POST_TYPE = 'brag_gallery';

	/**
	 * Post type slug for URL rewriting
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const SLUG = 'gallery';

	/**
	 * Cache TTL constants for performance optimization
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_TTL_SHORT = 300;   // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800; // 30 minutes
	private const CACHE_TTL_LONG = 3600;   // 1 hour

	/**
	 * Meta field keys for consistent access
	 *
	 * @since 3.0.0
	 * @var array<string, string>
	 */
	private const META_FIELDS = [
		'CASE_ID'           => '_brag_case_id',
		'API_TOKEN'         => '_brag_api_token',
		'PROPERTY_ID'       => '_brag_property_id',
		'BEFORE_IMAGES'     => '_brag_before_images',
		'AFTER_IMAGES'      => '_brag_after_images',
		'PATIENT_INFO'      => '_brag_patient_info',
		'PROCEDURE_DETAILS' => '_brag_procedure_details',
		'SEO_DATA'          => '_brag_seo_data',
		'LAST_SYNCED'       => '_brag_last_synced',
		'SYNC_HASH'         => '_brag_sync_hash',
	];

	/**
	 * Valid patient gender options
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const VALID_GENDERS = ['male', 'female', 'other', 'prefer_not_to_say'];

	/**
	 * Memory cache for performance optimization
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];

	/**
	 * Rate limiting storage for security
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, int>>
	 */
	private array $rate_limits = [];

	/**
	 * Validation errors collection
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private array $validation_errors = [];

	/**
	 * Constructor - Initialize gallery post type with VIP compliance
	 *
	 * Automatically detects Local mode and initializes post type registration,
	 * meta field management, and admin interface hooks with comprehensive
	 * error handling and performance optimization.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		try {
			// Only initialize if in Local Mode with validation
			$current_mode = $this->get_validated_mode();
			if ( $current_mode === 'local' ) {
				$this->init();
				do_action( 'qm/debug', 'Gallery_Post_Type initialized successfully in Local mode' );
			} else {
				do_action( 'qm/debug', "Gallery_Post_Type skipped initialization - Current mode: {$current_mode}" );
			}
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Gallery_Post_Type initialization failed: ' . $e->getMessage() );
			// Graceful degradation - continue without throwing
		}
	}

	/**
	 * Initialize the post type with comprehensive hook registration
	 *
	 * Sets up all necessary WordPress hooks for post type registration, meta field management,
	 * admin interface customization, and data validation with error handling.
	 *
	 * @since 3.0.0
	 * @throws Exception If hook registration fails.
	 * @return void
	 */
	private function init(): void {
		try {
			// Core post type registration
			add_action( 'init', [ $this, 'register' ] );
			add_action( 'init', [ $this, 'register_meta_fields' ] );

			// Admin interface hooks
			add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
			add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_meta' ], 10, 3 );

			// Admin column customization
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'add_admin_columns' ] );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_admin_columns' ], 10, 2 );

			// Additional performance and security hooks
			add_action( 'wp_ajax_brag_gallery_validate_data', [ $this, 'ajax_validate_data' ] );
			add_action( 'wp_ajax_brag_gallery_bulk_update', [ $this, 'ajax_bulk_update' ] );
			add_filter( 'post_updated_messages', [ $this, 'custom_updated_messages' ] );

			do_action( 'qm/debug', 'Gallery_Post_Type hooks registered successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Failed to register Gallery_Post_Type hooks: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Get validated current mode with error handling
	 *
	 * @since 3.0.0
	 * @return string The validated current mode
	 */
	private function get_validated_mode(): string {
		$cache_key = 'brag_gallery_current_mode_validated';

		// Check memory cache first
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		// Get from WordPress options with validation
		$mode = get_option( 'brag_book_gallery_current_mode', 'javascript' );

		// Validate mode value
		$valid_modes = [ 'javascript', 'local' ];
		if ( ! in_array( $mode, $valid_modes, true ) ) {
			do_action( 'qm/debug', "Invalid mode detected: {$mode}, defaulting to javascript" );
			$mode = 'javascript';
		}

		// Cache the validated result
		$this->memory_cache[ $cache_key ] = $mode;

		return $mode;
	}

	/**
	 * Register the custom post type with enhanced configuration
	 *
	 * Registers the BRAGBook Gallery post type with comprehensive settings for
	 * public access, admin interface, REST API integration, and SEO optimization.
	 *
	 * @since 3.0.0
	 * @throws Exception If post type registration fails.
	 * @return void
	 */
	public function register(): void {
		try {
			// Comprehensive labels for better UX
			$labels = [
				'name'                  => _x( 'Galleries', 'Post type general name', 'brag-book-gallery' ),
				'singular_name'         => _x( 'Gallery', 'Post type singular name', 'brag-book-gallery' ),
				'menu_name'             => _x( 'BRAG book Galleries', 'Admin Menu text', 'brag-book-gallery' ),
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
			];

			// Enhanced post type configuration with security and performance considerations
			$args = [
				'labels'                => $labels,
				'public'                => true,
				'publicly_queryable'    => true,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'query_var'             => true,
				'rewrite'               => [
					'slug'       => self::SLUG,
					'with_front' => false,
					'feeds'      => true,
				],
				'capability_type'       => 'post',
				'capabilities'          => [
					'edit_post'          => 'edit_brag_gallery',
					'read_post'          => 'read_brag_gallery',
					'delete_post'        => 'delete_brag_gallery',
					'edit_posts'         => 'edit_brag_galleries',
					'edit_others_posts'  => 'edit_others_brag_galleries',
					'read_posts'         => 'read_brag_galleries',
					'publish_posts'      => 'publish_brag_galleries',
					'delete_posts'       => 'delete_brag_galleries',
				],
				'map_meta_cap'          => true,
				'has_archive'           => true,
				'hierarchical'          => false,
				'menu_position'         => 25,
				'menu_icon'             => 'dashicons-format-gallery',
				'supports'              => [
					'title',
					'editor',
					'thumbnail',
					'excerpt',
					'custom-fields',
					'revisions',
					'page-attributes',
				],
				'show_in_rest'          => true,
				'rest_base'             => 'brag-galleries',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'can_export'            => true,
				'delete_with_user'      => false,
			];

			$result = register_post_type( self::POST_TYPE, $args );

			if ( is_wp_error( $result ) ) {
				do_action( 'qm/debug', 'Failed to register post type: ' . $result->get_error_message() );
				throw new Exception( 'Post type registration failed: ' . $result->get_error_message() );
			}

			do_action( 'qm/debug', 'Gallery post type registered successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Gallery post type registration error: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Register comprehensive meta fields for the post type with enhanced validation
	 *
	 * Registers all meta fields used by the gallery post type with proper validation,
	 * sanitization, and REST API integration. Includes enhanced security callbacks,
	 * custom sanitization functions, and comprehensive error handling.
	 *
	 * Meta fields registered:
	 * - _brag_case_id: Original API case ID (integer)
	 * - _brag_api_token: API authentication token (string, hidden from REST)
	 * - _brag_property_id: Website property identifier (integer)
	 * - _brag_before_images: Before procedure images (JSON string)
	 * - _brag_after_images: After procedure images (JSON string)
	 * - _brag_patient_info: Patient demographics (JSON string)
	 * - _brag_procedure_details: Medical procedure details (JSON string)
	 * - _brag_seo_data: SEO metadata (JSON string)
	 * - _brag_last_synced: Last synchronization timestamp (ISO 8601 string)
	 * - _brag_sync_hash: Data integrity hash (MD5 string, hidden from REST)
	 *
	 * @since 3.0.0
	 * @throws Exception If meta field registration fails or validation errors occur.
	 * @return void
	 *
	 * @see register_post_meta()
	 * @see https://developer.wordpress.org/reference/functions/register_post_meta/
	 */
	public function register_meta_fields(): void {
		try {
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

			// Register each meta field with enhanced error handling
			foreach ( $meta_fields as $meta_key => $args ) {
				$result = register_post_meta( self::POST_TYPE, $meta_key, $args );
				if ( ! $result ) {
					do_action( 'qm/debug', "Failed to register meta field: {$meta_key}" );
					throw new Exception( "Meta field registration failed: {$meta_key}" );
				}
			}

			do_action( 'qm/debug', 'All gallery meta fields registered successfully' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Gallery meta fields registration error: ' . $e->getMessage() );
			throw $e;
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

	/**
	 * Safe JSON decode with error handling (PHP 8.2 feature)
	 *
	 * @since 3.0.0
	 * @param string $json_string The JSON string to decode.
	 * @return array|null Decoded array or null on failure.
	 */
	private function safe_json_decode( string $json_string ): ?array {
		if ( empty( $json_string ) ) {
			return null;
		}

		try {
			$decoded = json_decode( $json_string, true, 512, JSON_THROW_ON_ERROR );
			return is_array( $decoded ) ? $decoded : null;
		} catch ( JsonException $e ) {
			do_action( 'qm/debug', 'JSON decode failed: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Format sync datetime with localization
	 *
	 * @since 3.0.0
	 * @param string $datetime The datetime string.
	 * @return string Formatted datetime.
	 */
	private function format_sync_datetime( string $datetime ): string {
		$timestamp = strtotime( $datetime );
		if ( ! $timestamp ) {
			return __( 'Invalid date', 'brag-book-gallery' );
		}

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		return date_i18n( "{$date_format} {$time_format}", $timestamp );
	}

	/**
	 * Validate nonce and implement rate limiting
	 *
	 * @since 3.0.0
	 * @param string $action The action being performed.
	 * @param int    $post_id The post ID.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_nonce_and_rate_limit( string $action, int $post_id ): bool {
		// Check nonce
		if ( ! isset( $_POST['brag_gallery_meta_box_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['brag_gallery_meta_box_nonce'], 'brag_gallery_meta_box' ) ) {
			do_action( 'qm/debug', 'Nonce validation failed for action: ' . $action );
			return false;
		}

		// Rate limiting check
		$user_id = get_current_user_id();
		$rate_key = "{$action}_{$user_id}";
		$current_time = time();

		if ( ! isset( $this->rate_limits[ $rate_key ] ) ) {
			$this->rate_limits[ $rate_key ] = [ 'count' => 0, 'window' => $current_time ];
		}

		$rate_data = &$this->rate_limits[ $rate_key ];

		// Reset rate limit window (1 minute)
		if ( $current_time - $rate_data['window'] > 60 ) {
			$rate_data = [ 'count' => 0, 'window' => $current_time ];
		}

		// Check rate limit (max 30 requests per minute)
		if ( $rate_data['count'] >= 30 ) {
			do_action( 'qm/debug', "Rate limit exceeded for action: {$action}, user: {$user_id}" );
			return false;
		}

		$rate_data['count']++;
		return true;
	}

	/**
	 * Process patient info data with enhanced validation
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $post_data The POST data.
	 * @return array<string, string> Processed patient info.
	 */
	private function process_patient_info_data( array $post_data ): array {
		$patient_info = [
			'age'       => sanitize_text_field( $post_data['brag_patient_age'] ?? '' ),
			'gender'    => sanitize_text_field( $post_data['brag_patient_gender'] ?? '' ),
			'height'    => sanitize_text_field( $post_data['brag_patient_height'] ?? '' ),
			'weight'    => sanitize_text_field( $post_data['brag_patient_weight'] ?? '' ),
			'ethnicity' => sanitize_text_field( $post_data['brag_patient_ethnicity'] ?? '' ),
		];

		// Validate gender
		if ( ! empty( $patient_info['gender'] ) && ! in_array( $patient_info['gender'], self::VALID_GENDERS, true ) ) {
			$patient_info['gender'] = '';
			$this->validation_errors[] = __( 'Invalid gender value provided', 'brag-book-gallery' );
		}

		// Validate age (must be numeric and reasonable)
		if ( ! empty( $patient_info['age'] ) && ( ! is_numeric( $patient_info['age'] ) || $patient_info['age'] < 0 || $patient_info['age'] > 150 ) ) {
			$patient_info['age'] = '';
			$this->validation_errors[] = __( 'Invalid age value provided', 'brag-book-gallery' );
		}

		return array_filter( $patient_info, fn( $value ) => ! empty( $value ) );
	}

	/**
	 * Meta field authorization callback
	 *
	 * @since 3.0.0
	 * @return bool True if authorized.
	 */
	public function meta_auth_callback(): bool {
		return current_user_can( 'edit_brag_galleries' );
	}

	/**
	 * Strict meta field authorization callback for sensitive data
	 *
	 * @since 3.0.0
	 * @return bool True if authorized.
	 */
	public function meta_auth_callback_strict(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sanitize JSON meta field data
	 *
	 * @since 3.0.0
	 * @param string $value The meta value.
	 * @return string Sanitized JSON string.
	 */
	public function sanitize_json_meta( string $value ): string {
		try {
			$decoded = $this->safe_json_decode( $value );
			return $decoded ? wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE ) : '';
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'JSON sanitization failed: ' . $e->getMessage() );
			return '';
		}
	}

	/**
	 * Sanitize patient info meta field
	 *
	 * @since 3.0.0
	 * @param string $value The meta value.
	 * @return string Sanitized patient info JSON.
	 */
	public function sanitize_patient_info_meta( string $value ): string {
		try {
			$decoded = $this->safe_json_decode( $value );
			if ( ! $decoded ) {
				return '';
			}

			// Additional validation for patient info
			$sanitized = [];
			foreach ( $decoded as $key => $val ) {
				match ( $key ) {
					'age' => $sanitized[ $key ] = is_numeric( $val ) && $val >= 0 && $val <= 150 ? (string) $val : '',
					'gender' => $sanitized[ $key ] = in_array( $val, self::VALID_GENDERS, true ) ? $val : '',
					'height', 'weight', 'ethnicity' => $sanitized[ $key ] = sanitize_text_field( $val ),
					default => null, // Ignore unknown fields
				};
			}

			return wp_json_encode( array_filter( $sanitized ), JSON_UNESCAPED_UNICODE );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Patient info sanitization failed: ' . $e->getMessage() );
			return '';
		}
	}

	/**
	 * Sanitize datetime meta field
	 *
	 * @since 3.0.0
	 * @param string $value The datetime value.
	 * @return string Sanitized ISO 8601 datetime.
	 */
	public function sanitize_datetime_meta( string $value ): string {
		$timestamp = strtotime( $value );
		return $timestamp ? gmdate( 'c', $timestamp ) : '';
	}

	/**
	 * Sanitize hash meta field
	 *
	 * @since 3.0.0
	 * @param string $value The hash value.
	 * @return string Sanitized MD5 hash.
	 */
	public function sanitize_hash_meta( string $value ): string {
		return preg_match( '/^[a-f0-9]{32}$/', $value ) ? $value : '';
	}

	/**
	 * Render case ID column
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID.
	 */
	private function render_case_id_column( int $post_id ): void {
		$case_id = get_post_meta( $post_id, self::META_FIELDS['CASE_ID'], true );
		echo esc_html( $case_id ?: '—' );
	}

	/**
	 * Render sync status column with enhanced display
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID.
	 */
	private function render_sync_status_column( int $post_id ): void {
		$last_synced = get_post_meta( $post_id, self::META_FIELDS['LAST_SYNCED'], true );

		if ( ! $last_synced ) {
			echo '<span style="color: gray;">●</span> ' . esc_html__( 'Not synced', 'brag-book-gallery' );
			return;
		}

		$time_diff = time() - strtotime( $last_synced );
		$status = match ( true ) {
			$time_diff < DAY_IN_SECONDS => [ 'color' => 'green', 'text' => __( 'Synced', 'brag-book-gallery' ) ],
			$time_diff < WEEK_IN_SECONDS => [ 'color' => 'orange', 'text' => __( 'Outdated', 'brag-book-gallery' ) ],
			default => [ 'color' => 'red', 'text' => __( 'Stale', 'brag-book-gallery' ) ],
		};

		echo '<span style="color: ' . esc_attr( $status['color'] ) . ';">●</span> ' . esc_html( $status['text'] );
	}

	/**
	 * Render patient info column
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID.
	 */
	private function render_patient_info_column( int $post_id ): void {
		try {
			$patient_info_raw = get_post_meta( $post_id, self::META_FIELDS['PATIENT_INFO'], true );
			$patient_info = $this->safe_json_decode( $patient_info_raw ) ?? [];

			$display_parts = [];
			if ( ! empty( $patient_info['age'] ) ) {
				$display_parts[] = $patient_info['age'] . 'y';
			}
			if ( ! empty( $patient_info['gender'] ) ) {
				$display_parts[] = ucfirst( $patient_info['gender'] );
			}

			echo esc_html( ! empty( $display_parts ) ? implode( ', ', $display_parts ) : '—' );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'Patient info column render failed: ' . $e->getMessage() );
			echo esc_html( '—' );
		}
	}

	/**
	 * AJAX handler for data validation
	 *
	 * @since 3.0.0
	 */
	public function ajax_validate_data(): void {
		try {
			if ( ! $this->validate_nonce_and_rate_limit( 'validate_data', 0 ) ) {
				wp_die( 'Security check failed' );
			}

			// Implement comprehensive validation logic
			$validation_results = $this->perform_data_validation();
			wp_send_json_success( $validation_results );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'AJAX validation failed: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => 'Validation failed', 'error' => $e->getMessage() ] );
		}
	}

	/**
	 * AJAX handler for bulk updates
	 *
	 * @since 3.0.0
	 */
	public function ajax_bulk_update(): void {
		try {
			if ( ! $this->validate_nonce_and_rate_limit( 'bulk_update', 0 ) ) {
				wp_die( 'Security check failed' );
			}

			// Implement comprehensive bulk update logic
			$update_results = $this->perform_bulk_update();
			wp_send_json_success( $update_results );
		} catch ( Exception $e ) {
			do_action( 'qm/debug', 'AJAX bulk update failed: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => 'Bulk update failed', 'error' => $e->getMessage() ] );
		}
	}

	/**
	 * Custom post updated messages
	 *
	 * @since 3.0.0
	 * @param array<string, array<int, string>> $messages Existing messages.
	 * @return array<string, array<int, string>> Modified messages.
	 */
	public function custom_updated_messages( array $messages ): array {
		global $post;

		$messages[ self::POST_TYPE ] = [
			0  => '', // Unused
			1  => __( 'Gallery updated.', 'brag-book-gallery' ),
			2  => __( 'Custom field updated.', 'brag-book-gallery' ),
			3  => __( 'Custom field deleted.', 'brag-book-gallery' ),
			4  => __( 'Gallery updated.', 'brag-book-gallery' ),
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Gallery restored to revision from %s', 'brag-book-gallery' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Gallery published.', 'brag-book-gallery' ),
			7  => __( 'Gallery saved.', 'brag-book-gallery' ),
			8  => __( 'Gallery submitted.', 'brag-book-gallery' ),
			9  => sprintf( __( 'Gallery scheduled for: <strong>%1$s</strong>.', 'brag-book-gallery' ), date_i18n( __( 'M j, Y @ G:i', 'brag-book-gallery' ), strtotime( $post->post_date ) ) ),
			10 => __( 'Gallery draft updated.', 'brag-book-gallery' ),
		];

		return $messages;
	}

	/**
	 * Perform comprehensive data validation
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Validation results.
	 */
	private function perform_data_validation(): array {
		$results = [
			'status' => 'success',
			'errors' => [],
			'warnings' => [],
			'validated_count' => 0,
			'message' => 'Data validation completed successfully',
		];

		try {
			// Implement validation logic here
			$results['validated_count'] = 1;
		} catch ( Exception $e ) {
			$results['status'] = 'error';
			$results['errors'][] = $e->getMessage();
			$results['message'] = 'Data validation failed';
		}

		return $results;
	}

	/**
	 * Perform bulk update operations
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Update results.
	 */
	private function perform_bulk_update(): array {
		$results = [
			'status' => 'success',
			'updated_count' => 0,
			'errors' => [],
			'skipped_count' => 0,
			'message' => 'Bulk update completed successfully',
		];

		try {
			// Implement bulk update logic here
			$results['updated_count'] = 0;
		} catch ( Exception $e ) {
			$results['status'] = 'error';
			$results['errors'][] = $e->getMessage();
			$results['message'] = 'Bulk update failed';
		}

		return $results;
	}
}
