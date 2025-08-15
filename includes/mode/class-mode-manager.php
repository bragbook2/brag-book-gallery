<?php
/**
 * Mode Manager
 *
 * Manages switching between JavaScript and Local modes.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Mode
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Mode;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mode Manager Class
 *
 * Handles mode detection, switching, and configuration.
 *
 * @since 3.0.0
 */
class Mode_Manager {

	/**
	 * JavaScript mode constant
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const MODE_JAVASCRIPT = 'javascript';

	/**
	 * Local mode constant
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public const MODE_LOCAL = 'local';

	/**
	 * Option key for current mode
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const OPTION_CURRENT_MODE = 'brag_book_gallery_mode';

	/**
	 * Option key for mode settings
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const OPTION_MODE_SETTINGS = 'brag_book_gallery_mode_settings';

	/**
	 * Instance of Mode Manager
	 *
	 * @since 3.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Gallery post type instance
	 *
	 * @since 3.0.0
	 * @var \BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type|null
	 */
	private ?object $post_type = null;

	/**
	 * Gallery taxonomies instance
	 *
	 * @since 3.0.0
	 * @var \BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies|null
	 */
	private ?object $taxonomies = null;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Get instance
	 *
	 * @since 3.0.0
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize mode manager
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init(): void {
		// Initialize components based on current mode
		if ( $this->is_local_mode() ) {
			$this->init_local_mode();
		} else {
			$this->init_javascript_mode();
		}

		// Add AJAX handlers for mode switching
		add_action( 'wp_ajax_brag_book_gallery_switch_mode', array( $this, 'ajax_switch_mode' ) );
		add_action( 'wp_ajax_brag_book_gallery_get_mode_info', array( $this, 'ajax_get_mode_info' ) );

		// Add REST API endpoints
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Initialize Local mode components
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_local_mode(): void {
		// Check if classes exist before instantiating
		if ( ! class_exists( 'BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type' ) ) {
			error_log( 'BRAG Book Gallery: Gallery_Post_Type class not found!' );
			return;
		}
		if ( ! class_exists( 'BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies' ) ) {
			error_log( 'BRAG Book Gallery: Gallery_Taxonomies class not found!' );
			return;
		}

		// Initialize post type and taxonomies
		$this->post_type = new \BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type();
		$this->taxonomies = new \BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies();

		// Add Local mode specific hooks
		add_filter( 'brag_book_gallery_use_local_data', '__return_true' );
		add_action( 'template_redirect', array( $this, 'handle_local_mode_routing' ), 5 );
	}

	/**
	 * Initialize JavaScript mode components
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_javascript_mode(): void {
		// Add JavaScript mode specific hooks
		add_filter( 'brag_book_gallery_use_api_data', '__return_true' );
		add_action( 'template_redirect', array( $this, 'handle_javascript_mode_routing' ), 5 );
	}

	/**
	 * Get current mode
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_current_mode(): string {
		return get_option( self::OPTION_CURRENT_MODE, self::MODE_JAVASCRIPT );
	}

	/**
	 * Check if JavaScript mode is active
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function is_javascript_mode(): bool {
		return $this->get_current_mode() === self::MODE_JAVASCRIPT;
	}

	/**
	 * Check if Local mode is active
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function is_local_mode(): bool {
		return $this->get_current_mode() === self::MODE_LOCAL;
	}

	/**
	 * Switch mode
	 *
	 * @since 3.0.0
	 * @param string $new_mode New mode to switch to.
	 * @return bool Success status.
	 */
	public function switch_mode( string $new_mode ): bool {
		// Validate mode
		if ( ! in_array( $new_mode, array( self::MODE_JAVASCRIPT, self::MODE_LOCAL ), true ) ) {
			return false;
		}

		// Check if already in this mode
		if ( $this->get_current_mode() === $new_mode ) {
			return true;
		}

		// Check if switch is allowed
		if ( ! $this->can_switch_mode() ) {
			return false;
		}

		// Fire pre-switch action
		do_action( 'brag_book_gallery_pre_mode_switch', $this->get_current_mode(), $new_mode );

		// Update mode
		$updated = update_option( self::OPTION_CURRENT_MODE, $new_mode );

		if ( $updated ) {
			// Flush rewrite rules
			flush_rewrite_rules();

			// Clear caches
			wp_cache_flush();

			// Fire post-switch action
			do_action( 'brag_book_gallery_post_mode_switch', $new_mode );
		}

		return $updated;
	}

	/**
	 * Check if mode can be switched
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function can_switch_mode(): bool {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if sync is running (future implementation)
		if ( $this->is_sync_running() ) {
			return false;
		}

		// Allow filtering
		return apply_filters( 'brag_book_gallery_can_switch_mode', true );
	}

	/**
	 * Check if sync is running
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	private function is_sync_running(): bool {
		$sync_status = get_transient( 'brag_book_gallery_sync_status' );
		return $sync_status === 'running';
	}

	/**
	 * Get mode settings
	 *
	 * @since 3.0.0
	 * @param string|null $mode Mode to get settings for.
	 * @return array
	 */
	public function get_mode_settings( ?string $mode = null ): array {
		if ( null === $mode ) {
			$mode = $this->get_current_mode();
		}

		$all_settings = get_option( self::OPTION_MODE_SETTINGS, array() );

		$defaults = $this->get_default_settings( $mode );
		$settings = isset( $all_settings[ $mode ] ) ? $all_settings[ $mode ] : array();

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update mode settings
	 *
	 * @since 3.0.0
	 * @param array  $settings New settings.
	 * @param string|null $mode Mode to update settings for.
	 * @return bool
	 */
	public function update_mode_settings( array $settings, ?string $mode = null ): bool {
		if ( null === $mode ) {
			$mode = $this->get_current_mode();
		}

		$all_settings = get_option( self::OPTION_MODE_SETTINGS, array() );
		$all_settings[ $mode ] = $settings;

		return update_option( self::OPTION_MODE_SETTINGS, $all_settings );
	}

	/**
	 * Get default settings for a mode
	 *
	 * @since 3.0.0
	 * @param string $mode Mode to get defaults for.
	 * @return array
	 */
	private function get_default_settings( string $mode ): array {
		if ( $mode === self::MODE_LOCAL ) {
			return array(
				'sync_frequency' => 'daily',
				'sync_time' => '02:00',
				'batch_size' => 20,
				'import_images' => true,
				'preserve_api_data' => true,
				'auto_sync' => false,
			);
		}

		return array(
			'cache_duration' => 300,
			'api_timeout' => 30,
			'lazy_load' => true,
			'virtual_urls' => true,
		);
	}

	/**
	 * Handle Local mode routing
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_local_mode_routing(): void {
		// Local mode uses WordPress native routing
		// Additional routing logic can be added here if needed
	}

	/**
	 * Handle JavaScript mode routing
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_javascript_mode_routing(): void {
		// JavaScript mode uses virtual routing
		// This is handled by existing code in templates
	}

	/**
	 * AJAX handler for mode switching
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_switch_mode(): void {
		// Check nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_mode_switch', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'brag-book-gallery' ) );
		}

		// Get new mode
		$new_mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : '';

		// Switch mode
		if ( $this->switch_mode( $new_mode ) ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %s: mode name */
					__( 'Successfully switched to %s mode.', 'brag-book-gallery' ),
					ucfirst( $new_mode )
				),
				'mode' => $new_mode,
			) );
		} else {
			wp_send_json_error( __( 'Failed to switch mode.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * AJAX handler for getting mode info
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function ajax_get_mode_info(): void {
		// Check nonce
		if ( ! check_ajax_referer( 'brag_book_gallery_mode_info', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'brag-book-gallery' ) );
		}

		$current_mode = $this->get_current_mode();
		$settings = $this->get_mode_settings();

		// Get statistics for Local mode
		$stats = array();
		if ( $this->is_local_mode() ) {
			$stats = array(
				'total_galleries' => wp_count_posts( \BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type::POST_TYPE )->publish,
				'total_categories' => wp_count_terms( \BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies::CATEGORY_TAXONOMY ),
				'total_procedures' => wp_count_terms( \BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies::PROCEDURE_TAXONOMY ),
			);
		}

		wp_send_json_success( array(
			'mode' => $current_mode,
			'settings' => $settings,
			'can_switch' => $this->can_switch_mode(),
			'stats' => $stats,
		) );
	}

	/**
	 * Register REST API routes
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route( 'brag-book-gallery/v1', '/mode', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_mode' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_switch_mode' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'mode' => array(
						'required' => true,
						'validate_callback' => function( $param ) {
							return in_array( $param, array( self::MODE_JAVASCRIPT, self::MODE_LOCAL ), true );
						},
					),
				),
			),
		) );
	}

	/**
	 * REST API: Get current mode
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_get_mode( $request ): \WP_REST_Response {
		return new \WP_REST_Response( array(
			'mode' => $this->get_current_mode(),
			'settings' => $this->get_mode_settings(),
		), 200 );
	}

	/**
	 * REST API: Switch mode
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_switch_mode( $request ): \WP_REST_Response {
		$new_mode = $request->get_param( 'mode' );

		if ( $this->switch_mode( $new_mode ) ) {
			return new \WP_REST_Response( array(
				'success' => true,
				'mode' => $new_mode,
			), 200 );
		}

		return new \WP_REST_Response( array(
			'success' => false,
			'message' => __( 'Failed to switch mode.', 'brag-book-gallery' ),
		), 400 );
	}

	/**
	 * REST API: Permission check
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function rest_permission_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}
}
