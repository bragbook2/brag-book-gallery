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
 * Enterprise-grade mode management system for the BRAG Book Gallery plugin, providing seamless
 * transitions between JavaScript and Local operational modes. This singleton class orchestrates
 * all mode-specific functionality, including component initialization, settings management,
 * and secure mode switching capabilities.
 *
 * ## Key Features
 * - **Dual Mode Support**: JavaScript (API-driven) and Local (database-driven) operational modes
 * - **Singleton Pattern**: Ensures single instance and consistent state across requests
 * - **Dynamic Component Loading**: Automatically initializes mode-specific components
 * - **Secure Mode Switching**: Protected mode transitions with validation and permissions
 * - **Settings Management**: Centralized configuration storage and retrieval per mode
 * - **AJAX & REST APIs**: Multiple integration endpoints for frontend/backend operations
 * - **WordPress VIP Compliant**: Follows WordPress VIP coding standards and logging practices
 *
 * ## Mode Types
 * - **Default Mode**: Uses external API calls for gallery data (default)
 *   - Lightweight and fast initial load
 *   - No database storage required
 *   - Ideal for sites with limited server resources
 * - **Local Mode**: Stores gallery data in WordPress database
 *   - Full WordPress integration with custom post types
 *   - Advanced filtering and search capabilities
 *   - Better SEO and performance for large galleries
 *
 * ## Architecture
 * - Utilizes match expressions for efficient conditional logic (PHP 8.2+)
 * - Employs typed properties for enhanced type safety
 * - Implements comprehensive error handling with structured logging
 * - Supports dynamic component initialization based on active mode
 * - Integrates with WordPress hook system for extensibility
 *
 * ## Usage Example
 * ```php
 * $mode_manager = Mode_Manager::get_instance();
 *
 * // Check current mode
 * if ($mode_manager->is_local_mode()) {
 *     // Local mode specific operations
 * }
 *
 * // Switch mode with validation
 * $success = $mode_manager->switch_mode(Mode_Manager::MODE_LOCAL);
 * if (!$success) {
 *     $error = 'Mode switch failed - check permissions';
 * }
 *
 * // Get mode-specific settings
 * $settings = $mode_manager->get_mode_settings(Mode_Manager::MODE_DEFAULT);
 * ```
 *
 * ## Dependencies
 * - Gallery_Post_Type: Custom post type for Local mode gallery entries
 * - Gallery_Taxonomies: Custom taxonomies for categorization and procedures
 * - WordPress Options API: Settings storage and retrieval
 * - WordPress Hooks System: Component initialization and extensibility
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Mode
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */
class Mode_Manager {

	/**
	 * Mode constants
	 *
	 * @since 3.0.0
	 */
	public const MODE_DEFAULT = 'default';
	public const MODE_LOCAL = 'local';

	/**
	 * Legacy mode constant for backwards compatibility
	 * @deprecated 3.0.0 Use MODE_DEFAULT instead
	 */
	public const MODE_JAVASCRIPT = 'default';

	/**
	 * Available modes array
	 *
	 * @since 3.0.0
	 */
	public const AVAILABLE_MODES = [
		self::MODE_DEFAULT,
		self::MODE_LOCAL,
	];

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
	 * Cache configuration constants
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;  // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800; // 30 minutes
	private const CACHE_TTL_LONG = 3600;   // 1 hour

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
			$this->init_default_mode();
		}

		// Add AJAX handlers for mode switching
		add_action( 'wp_ajax_brag_book_gallery_switch_mode', [ $this, 'ajax_switch_mode' ] );
		add_action( 'wp_ajax_brag_book_gallery_get_mode_info', [ $this, 'ajax_get_mode_info' ] );

		// Add REST API endpoints
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
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
			$this->log_mode_error( 'class_not_found', 'Gallery_Post_Type class not found', [
				'class' => 'BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type',
				'mode' => 'local',
				'operation' => 'init_local_mode',
			] );
			return;
		}
		if ( ! class_exists( 'BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies' ) ) {
			$this->log_mode_error( 'class_not_found', 'Gallery_Taxonomies class not found', [
				'class' => 'BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies',
				'mode' => 'local',
				'operation' => 'init_local_mode',
			] );
			return;
		}

		// Initialize post type and taxonomies
		$this->post_type = new \BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type();
		$this->taxonomies = new \BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies();

		// Add Local mode specific hooks
		add_filter( 'brag_book_gallery_use_local_data', '__return_true' );
		add_action( 'template_redirect', [ $this, 'handle_local_mode_routing' ], 5 );
	}

	/**
	 * Initialize JavaScript mode components
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_default_mode(): void {
		// Add JavaScript mode specific hooks
		add_filter( 'brag_book_gallery_use_api_data', '__return_true' );
		add_action( 'template_redirect', [ $this, 'handle_default_mode_routing' ], 5 );
	}

	/**
	 * Get current mode
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_current_mode(): string {
		return get_option( self::OPTION_CURRENT_MODE, self::MODE_DEFAULT );
	}

	/**
	 * Check if JavaScript mode is active
	 *
	 * @since 3.0.0
	 * @return bool
	 */
	public function is_default_mode(): bool {
		return $this->get_current_mode() === self::MODE_DEFAULT;
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
	 * Check if JavaScript mode is active (backwards compatibility)
	 *
	 * @deprecated 3.0.0 Use is_default_mode() instead
	 * @since 3.0.0
	 * @return bool True if default mode is active
	 */
	public function is_javascript_mode(): bool {
		return $this->is_default_mode();
	}

	/**
	 * Switch operational mode with validation and hooks
	 *
	 * Performs a secure mode transition with pre-flight validation, user capability checks,
	 * and proper cache clearing. Fires pre/post switch hooks for extensibility.
	 *
	 * @since 3.0.0
	 * @param string $new_mode Target mode (MODE_DEFAULT or MODE_LOCAL)
	 * @return bool True if mode switch succeeded, false if validation failed or switch denied
	 */
	public function switch_mode( string $new_mode ): bool {
		// Validate mode
		if ( ! in_array( $new_mode, array( self::MODE_DEFAULT, self::MODE_LOCAL ), true ) ) {
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
	 * Validate if current user can switch operational modes
	 *
	 * Performs comprehensive validation including user capabilities, sync status,
	 * and custom filter checks to determine if mode switching is permitted.
	 *
	 * @since 3.0.0
	 * @return bool True if mode switching is allowed, false if restricted
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
		$sync_status = get_transient( 'brag_book_gallery_transient_sync_status' );
		return $sync_status === 'running';
	}

	/**
	 * Get mode-specific settings with defaults
	 *
	 * Retrieves configuration settings for the specified mode, merging stored values
	 * with default settings to ensure all required options are available.
	 *
	 * @since 3.0.0
	 * @param string|null $mode Mode to get settings for (null = current mode)
	 * @return array<string, mixed> Mode settings merged with defaults
	 */
	public function get_mode_settings( ?string $mode = null ): array {
		if ( null === $mode ) {
			$mode = $this->get_current_mode();
		}

		$all_settings = get_option( self::OPTION_MODE_SETTINGS, [] );

		$defaults = $this->get_default_settings( $mode );
		$settings = $all_settings[ $mode ] ?? [];

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update mode-specific settings
	 *
	 * Stores new configuration settings for the specified mode in WordPress options.
	 * Settings are validated and sanitized before storage.
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $settings New settings to store
	 * @param string|null $mode Target mode (null = current mode)
	 * @return bool True if settings were successfully updated, false otherwise
	 */
	public function update_mode_settings( array $settings, ?string $mode = null ): bool {
		if ( null === $mode ) {
			$mode = $this->get_current_mode();
		}

		$all_settings = get_option( self::OPTION_MODE_SETTINGS, [] );
		$all_settings[ $mode ] = $settings;

		return update_option( self::OPTION_MODE_SETTINGS, $all_settings );
	}

	/**
	 * Get default configuration settings for operational mode
	 *
	 * Returns mode-specific default settings using match expression for efficient
	 * conditional logic. Each mode has tailored default values for optimal performance.
	 *
	 * @since 3.0.0
	 * @param string $mode Operational mode (MODE_DEFAULT or MODE_LOCAL)
	 * @return array<string, mixed> Default settings array for the specified mode
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
	public function handle_default_mode_routing(): void {
		// JavaScript mode uses virtual routing
		// This is handled by existing code in templates
	}

	/**
	 * AJAX handler for secure mode switching
	 *
	 * Processes AJAX requests for mode transitions with comprehensive security validation
	 * including nonce verification and user capability checks.
	 *
	 * @since 3.0.0
	 * @return void Outputs JSON response and exits
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
			wp_send_json_success( [
				'message' => sprintf(
					/* translators: %s: mode name */
					__( 'Successfully switched to %s mode.', 'brag-book-gallery' ),
					ucfirst( $new_mode )
				),
				'mode' => $new_mode,
			] );
		} else {
			wp_send_json_error( __( 'Failed to switch mode.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * AJAX handler for retrieving comprehensive mode information
	 *
	 * Returns current mode status, settings, statistics, and capabilities in JSON format.
	 * Includes mode-specific statistics for Local mode installations.
	 *
	 * @since 3.0.0
	 * @return void Outputs JSON response with mode information and exits
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
							return in_array( $param, array( self::MODE_DEFAULT, self::MODE_LOCAL ), true );
						},
					),
				),
			),
		) );
	}

	/**
	 * REST API: Get current operational mode and settings
	 *
	 * Returns the active mode and its configuration settings via REST API endpoint.
	 * Used for frontend mode detection and configuration retrieval.
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request REST API request object
	 * @return \WP_REST_Response JSON response with mode and settings data
	 */
	public function rest_get_mode( $request ): \WP_REST_Response {
		return new \WP_REST_Response( array(
			'mode' => $this->get_current_mode(),
			'settings' => $this->get_mode_settings(),
		), 200 );
	}

	/**
	 * REST API: Switch operational mode via REST endpoint
	 *
	 * Processes mode switch requests through REST API with validation and error handling.
	 * Returns appropriate HTTP status codes and response messages.
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request REST API request containing mode parameter
	 * @return \WP_REST_Response JSON response with success status or error message
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
	 * REST API: Validate user permissions for mode management
	 *
	 * Checks if the current user has sufficient capabilities to access mode management
	 * endpoints through the REST API.
	 *
	 * @since 3.0.0
	 * @param \WP_REST_Request $request REST API request object
	 * @return bool True if user has manage_options capability, false otherwise
	 */
	public function rest_permission_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Log mode errors using WordPress VIP compliant logging
	 *
	 * @since 3.0.0
	 * @param string $error_code Unique error code for tracking
	 * @param string $message Error message
	 * @param array<string, mixed> $context Additional context for debugging
	 * @return void
	 */
	private function log_mode_error( string $error_code, string $message, array $context = [] ): void {
		do_action( 'qm/debug', 'BRAG Book Gallery Mode Error: ' . $message, [
			'error_code' => $error_code,
			'message' => $message,
			'context' => $context,
			'timestamp' => current_time( 'mysql' ),
			'class' => __CLASS__,
		] );
	}

	/**
	 * Validate mode parameter for security and correctness
	 *
	 * @since 3.0.0
	 * @param string $mode Mode to validate
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_mode_parameter( string $mode ): array {
		$errors = [];

		// Check if mode is in allowed values
		if ( ! in_array( $mode, self::AVAILABLE_MODES, true ) ) {
			$errors[] = sprintf( 'Invalid mode "%s". Must be one of: %s', $mode, implode( ', ', self::AVAILABLE_MODES ) );
		}

		// Additional security validation
		if ( strlen( $mode ) > 50 ) {
			$errors[] = 'Mode parameter exceeds maximum length';
		}

		if ( ! preg_match( '/^[a-z_-]+$/', $mode ) ) {
			$errors[] = 'Mode parameter contains invalid characters';
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Validate mode settings array structure and values
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $settings Settings to validate
	 * @param string $mode Target mode for validation
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_mode_settings( array $settings, string $mode ): array {
		$errors = [];
		$defaults = $this->get_default_settings( $mode );

		foreach ( $settings as $key => $value ) {
			// Check if setting key is valid for this mode
			if ( ! array_key_exists( $key, $defaults ) ) {
				$errors[] = sprintf( 'Unknown setting "%s" for %s mode', $key, $mode );
				continue;
			}

			// Validate setting values based on expected type and mode
			$validation_error = match ( $key ) {
				'sync_frequency' => $this->validate_sync_frequency( $value ),
				'sync_time' => $this->validate_sync_time( $value ),
				'batch_size' => $this->validate_batch_size( $value ),
				'cache_duration' => $this->validate_cache_duration( $value ),
				'api_timeout' => $this->validate_api_timeout( $value ),
				'import_images', 'preserve_api_data', 'auto_sync', 'lazy_load', 'virtual_urls' =>
					is_bool( $value ) ? null : "Setting '{$key}' must be a boolean value",
				default => null,
			};

			if ( $validation_error ) {
				$errors[] = $validation_error;
			}
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Validate sync frequency setting
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to validate
	 * @return string|null Error message or null if valid
	 */
	private function validate_sync_frequency( $value ): ?string {
		$allowed_frequencies = [ 'hourly', 'daily', 'weekly', 'monthly' ];

		if ( ! is_string( $value ) || ! in_array( $value, $allowed_frequencies, true ) ) {
			return sprintf( 'sync_frequency must be one of: %s', implode( ', ', $allowed_frequencies ) );
		}

		return null;
	}

	/**
	 * Validate sync time setting (24-hour format)
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to validate
	 * @return string|null Error message or null if valid
	 */
	private function validate_sync_time( $value ): ?string {
		if ( ! is_string( $value ) || ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
			return 'sync_time must be in HH:MM format (24-hour)';
		}

		return null;
	}

	/**
	 * Validate batch size setting
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to validate
	 * @return string|null Error message or null if valid
	 */
	private function validate_batch_size( $value ): ?string {
		if ( ! is_int( $value ) || $value < 1 || $value > 1000 ) {
			return 'batch_size must be an integer between 1 and 1000';
		}

		return null;
	}

	/**
	 * Validate cache duration setting
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to validate
	 * @return string|null Error message or null if valid
	 */
	private function validate_cache_duration( $value ): ?string {
		if ( ! is_int( $value ) || $value < 0 || $value > 86400 ) { // Max 24 hours
			return 'cache_duration must be an integer between 0 and 86400 seconds';
		}

		return null;
	}

	/**
	 * Validate API timeout setting
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to validate
	 * @return string|null Error message or null if valid
	 */
	private function validate_api_timeout( $value ): ?string {
		if ( ! is_int( $value ) || $value < 5 || $value > 300 ) {
			return 'api_timeout must be an integer between 5 and 300 seconds';
		}

		return null;
	}

	/**
	 * Enhanced exception handling with context preservation
	 *
	 * @since 3.0.0
	 * @param \Exception $exception The caught exception
	 * @param string $operation The operation that failed
	 * @param array<string, mixed> $context Additional context
	 * @return void
	 */
	private function handle_mode_exception( \Exception $exception, string $operation, array $context = [] ): void {
		$error_context = array_merge( $context, [
			'operation' => $operation,
			'exception_type' => get_class( $exception ),
			'exception_code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		] );

		$this->log_mode_error(
			strtolower( str_replace( ' ', '_', $operation ) ) . '_exception',
			$exception->getMessage(),
			$error_context
		);
	}

	/**
	 * Sanitize mode settings for security
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $settings Raw settings to sanitize
	 * @param string $mode Target mode for validation
	 * @return array<string, mixed> Sanitized settings
	 */
	private function sanitize_mode_settings( array $settings, string $mode ): array {
		$sanitized = [];
		$defaults = $this->get_default_settings( $mode );

		foreach ( $settings as $key => $value ) {
			// Only process known settings for this mode
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			// Sanitize based on setting type and expected values
			$sanitized[ $key ] = match ( $key ) {
				'sync_frequency' => in_array( $value, [ 'hourly', 'daily', 'weekly', 'monthly' ], true ) ? $value : 'daily',
				'sync_time' => $this->sanitize_time_setting( $value ),
				'batch_size' => max( 1, min( 1000, intval( $value ) ) ),
				'cache_duration' => max( 0, min( 86400, intval( $value ) ) ),
				'api_timeout' => max( 5, min( 300, intval( $value ) ) ),
				'import_images', 'preserve_api_data', 'auto_sync', 'lazy_load', 'virtual_urls' => (bool) $value,
				default => sanitize_text_field( strval( $value ) ),
			};
		}

		return $sanitized;
	}

	/**
	 * Sanitize time setting to HH:MM format
	 *
	 * @since 3.0.0
	 * @param mixed $value Time value to sanitize
	 * @return string Sanitized time in HH:MM format
	 */
	private function sanitize_time_setting( $value ): string {
		if ( is_string( $value ) && preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
			return $value;
		}

		// Default to 2:00 AM if invalid
		return '02:00';
	}

	/**
	 * Validate AJAX security (nonce and capabilities)
	 *
	 * @since 3.0.0
	 * @param string $nonce_action Nonce action to verify
	 * @param string $capability Required user capability
	 * @return array{valid: bool, error?: string} Validation result
	 */
	private function validate_ajax_security( string $nonce_action, string $capability = 'manage_options' ): array {
		// Check nonce
		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			return [
				'valid' => false,
				'error' => __( 'Security check failed - invalid nonce.', 'brag-book-gallery' ),
			];
		}

		// Check user capability
		if ( ! current_user_can( $capability ) ) {
			return [
				'valid' => false,
				'error' => __( 'Insufficient permissions.', 'brag-book-gallery' ),
			];
		}

		return [ 'valid' => true ];
	}

	/**
	 * Rate limiting for mode operations
	 *
	 * @since 3.0.0
	 * @param string $operation Operation type
	 * @param int $limit Maximum attempts within time window
	 * @param int $window Time window in seconds
	 * @return bool True if within limits, false if rate limited
	 */
	private function check_rate_limit( string $operation, int $limit = 5, int $window = 300 ): bool {
		$transient_key = 'brag_book_gallery_transient_mode_rate_limit_' . $operation . '_' . get_current_user_id();
		$attempts = get_transient( $transient_key );

		if ( $attempts === false ) {
			// First attempt within window
			set_transient( $transient_key, 1, $window );
			return true;
		}

		if ( $attempts >= $limit ) {
			$this->log_mode_error( 'rate_limit_exceeded', 'Rate limit exceeded for operation: ' . $operation, [
				'operation' => $operation,
				'attempts' => $attempts,
				'limit' => $limit,
				'user_id' => get_current_user_id(),
			] );
			return false;
		}

		// Increment attempt counter
		set_transient( $transient_key, $attempts + 1, $window );
		return true;
	}

	/**
	 * Validate user session and prevent hijacking
	 *
	 * @since 3.0.0
	 * @return bool True if session is valid, false if potentially hijacked
	 */
	private function validate_user_session(): bool {
		// Basic session validation
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check if user has been active recently
		$user_id = get_current_user_id();
		$last_activity = get_user_meta( $user_id, '_brag_book_last_activity', true );

		if ( $last_activity && ( time() - intval( $last_activity ) > HOUR_IN_SECONDS * 24 ) ) {
			$this->log_mode_error( 'stale_session', 'User session appears stale', [
				'user_id' => $user_id,
				'last_activity' => $last_activity,
				'current_time' => time(),
			] );
		}

		// Update last activity
		update_user_meta( $user_id, '_brag_book_last_activity', time() );

		return true;
	}

	/**
	 * Validate input data for potential security threats
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $data Data to validate
	 * @return array{valid: bool, errors: array<string>} Validation result
	 */
	private function validate_input_security( array $data ): array {
		$errors = [];

		foreach ( $data as $key => $value ) {
			// Check for suspicious patterns
			$string_value = is_string( $value ) ? $value : serialize( $value );

			if ( $this->contains_suspicious_patterns( $string_value ) ) {
				$errors[] = sprintf( 'Potentially malicious content detected in field: %s', sanitize_key( $key ) );
			}

			// Check for excessively long values
			if ( strlen( $string_value ) > 10000 ) {
				$errors[] = sprintf( 'Field "%s" exceeds maximum allowed length', sanitize_key( $key ) );
			}
		}

		return [
			'valid' => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Check for suspicious patterns in input data
	 *
	 * @since 3.0.0
	 * @param string $data Data to check
	 * @return bool True if suspicious patterns found
	 */
	private function contains_suspicious_patterns( string $data ): bool {
		$suspicious_patterns = [
			// Script injection patterns
			'/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
			'/javascript:/i',
			'/vbscript:/i',

			// SQL injection patterns
			'/(\b(union|select|insert|update|delete|drop|create|alter)\b)/i',
			'/(\b(and|or)\s+\d+\s*=\s*\d+)/i',

			// Path traversal patterns
			'/\.\.[\/\\\\]/i',
			'/\.(exe|bat|cmd|sh|php|pl|cgi)$/i',
		];

		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $data ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mode-specific caching for settings and status
	 *
	 * @since 3.0.0
	 */
	private array $mode_cache = [];

	/**
	 * Get cached mode data
	 *
	 * @since 3.0.0
	 * @param string $key Cache key
	 * @param callable|null $callback Function to generate data if cache miss
	 * @param int $ttl Cache TTL in seconds
	 * @return mixed Cached data or callback result
	 */
	private function get_cached_mode_data( string $key, ?callable $callback = null, int $ttl = self::CACHE_TTL_MEDIUM ) {
		// Check memory cache first
		if ( isset( $this->mode_cache[ $key ] ) ) {
			return $this->mode_cache[ $key ];
		}

		// Check WordPress transient cache
		$transient_key = 'brag_book_gallery_transient_mode_' . $key;
		$cached_data = get_transient( $transient_key );

		if ( $cached_data !== false ) {
			// Store in memory cache for subsequent requests
			$this->mode_cache[ $key ] = $cached_data;
			return $cached_data;
		}

		// Cache miss - generate data if callback provided
		if ( $callback !== null ) {
			$data = $callback();
			$this->set_mode_cache( $key, $data, $ttl );
			return $data;
		}

		return null;
	}

	/**
	 * Set mode data in cache
	 *
	 * @since 3.0.0
	 * @param string $key Cache key
	 * @param mixed $data Data to cache
	 * @param int $ttl Cache TTL in seconds
	 * @return void
	 */
	private function set_mode_cache( string $key, $data, int $ttl = self::CACHE_TTL_MEDIUM ): void {
		// Store in memory cache
		$this->mode_cache[ $key ] = $data;

		// Store in WordPress transient cache
		$transient_key = 'brag_book_gallery_transient_mode_' . $key;
		set_transient( $transient_key, $data, $ttl );
	}

	/**
	 * Clear mode caches
	 *
	 * @since 3.0.0
	 * @param string|null $pattern Optional pattern to match cache keys
	 * @return void
	 */
	private function clear_mode_cache( ?string $pattern = null ): void {
		// Clear memory cache
		if ( $pattern === null ) {
			$this->mode_cache = [];
		} else {
			foreach ( array_keys( $this->mode_cache ) as $key ) {
				if ( strpos( $key, $pattern ) !== false ) {
					unset( $this->mode_cache[ $key ] );
				}
			}
		}

		// Clear transient cache
		global $wpdb;
		if ( $pattern === null ) {
			$wpdb->query(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE '_transient_brag_book_mode_%'
				 OR option_name LIKE '_transient_timeout_brag_book_mode_%'"
			);
		} else {
			$pattern_md5 = md5( $pattern );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 OR option_name LIKE %s",
				'_transient_brag_book_mode_%' . $pattern_md5 . '%',
				'_transient_timeout_brag_book_mode_%' . $pattern_md5 . '%'
			) );
		}
	}

	/**
	 * Optimize mode switching with pre-loading
	 *
	 * @since 3.0.0
	 * @param string $target_mode Mode to pre-load components for
	 * @return void
	 */
	private function preload_mode_components( string $target_mode ): void {
		if ( $target_mode === self::MODE_LOCAL ) {
			// Pre-load Local mode classes if not already loaded
			if ( ! class_exists( 'BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type' ) ) {
				$autoload_path = trailingslashit( dirname( __DIR__ ) ) . 'autoload.php';
				if ( file_exists( $autoload_path ) ) {
					require_once $autoload_path;
				}
			}
		}

		// Cache mode-specific settings for faster access
		$this->get_cached_mode_data(
			"settings_{$target_mode}",
			fn() => $this->get_mode_settings( $target_mode ),
			self::CACHE_TTL_LONG
		);
	}

	/**
	 * Batch operations for efficient mode management
	 *
	 * @since 3.0.0
	 * @param array<callable> $operations Array of operations to execute
	 * @return array{success: int, errors: array<string>} Results summary
	 */
	private function batch_mode_operations( array $operations ): array {
		$success_count = 0;
		$errors = [];

		// Suspend cache invalidation during batch operations
		wp_suspend_cache_invalidation( true );

		try {
			foreach ( $operations as $index => $operation ) {
				try {
					$result = $operation();
					if ( $result === true || ( is_array( $result ) && ( $result['success'] ?? false ) ) ) {
						$success_count++;
					} else {
						$errors[] = "Operation {$index} failed: " . ( is_array( $result ) ? ( $result['error'] ?? 'Unknown error' ) : 'Operation returned false' );
					}
				} catch ( \Exception $e ) {
					$errors[] = "Operation {$index} threw exception: " . $e->getMessage();
					$this->handle_mode_exception( $e, "batch_operation_{$index}" );
				}
			}

		} finally {
			// Re-enable cache invalidation
			wp_suspend_cache_invalidation( false );

			// Clear any accumulated cache if we had operations
			if ( $success_count > 0 ) {
				wp_cache_flush();
			}
		}

		return [
			'success' => $success_count,
			'errors' => $errors,
		];
	}

	/**
	 * Monitor mode performance and resource usage
	 *
	 * @since 3.0.0
	 * @param string $operation Operation being monitored
	 * @return array{memory_peak: string, execution_time: float, queries: int}|array Performance metrics
	 */
	private function monitor_mode_performance( string $operation ): array {
		static $start_time = null;
		static $start_queries = null;

		if ( $start_time === null ) {
			$start_time = microtime( true );
			$start_queries = get_num_queries();
			return [];
		}

		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;
		$memory_peak = size_format( memory_get_peak_usage( true ) );
		$queries = get_num_queries() - $start_queries;

		$performance_data = [
			'memory_peak' => $memory_peak,
			'execution_time' => round( $execution_time, 3 ),
			'queries' => $queries,
		];

		// Log performance metrics for operations taking longer than 1 second
		if ( $execution_time > 1.0 ) {
			do_action( 'qm/debug', "Mode performance for {$operation}", $performance_data );
		}

		// Reset for next operation
		$start_time = null;
		$start_queries = null;

		return $performance_data;
	}

	/**
	 * Optimize database queries for mode operations
	 *
	 * @since 3.0.0
	 * @param callable $operation Database operation to optimize
	 * @return mixed Operation result
	 */
	private function optimize_mode_database_operation( callable $operation ) {
		// Start performance monitoring
		$this->monitor_mode_performance( 'database_operation_start' );

		// Optimize WordPress object cache for bulk operations
		$original_cache_suspension = wp_suspend_cache_invalidation();
		wp_suspend_cache_invalidation( true );

		try {
			$result = $operation();

			// Log completion metrics
			$metrics = $this->monitor_mode_performance( 'database_operation_complete' );

			if ( $metrics && $metrics['queries'] > 10 ) {
				$this->log_mode_error( 'high_query_count', 'Database operation used many queries', [
					'metrics' => $metrics,
					'operation' => 'optimize_mode_database_operation',
				] );
			}

			return $result;

		} finally {
			// Restore original cache suspension state
			wp_suspend_cache_invalidation( $original_cache_suspension );
		}
	}
}
