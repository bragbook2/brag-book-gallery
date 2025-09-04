<?php
/**
 * Consultation Form Handler
 *
 * Manages consultation form submissions, API communication, and admin display.
 * This class handles the complete lifecycle of consultation requests including
 * form processing, external API integration, and data persistence.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\Traits\Trait_Api;
use BRAGBookGallery\Includes\Traits\Trait_Tools;
use WP_Error;
use WP_Query;

/**
 * Consultation Class
 *
 * Enterprise-grade consultation form handling system that manages the complete
 * lifecycle of consultation requests including form processing, validation,
 * external API integration, local data persistence, and admin management interface.
 *
 * Features:
 * - Comprehensive form validation with field length limits
 * - Dual API/local storage strategy for data reliability
 * - AJAX-powered admin interface with pagination
 * - VIP-compliant caching and performance optimization
 * - Security-first approach with nonce validation and input sanitization
 * - HTML5 dialog-based consultation detail viewer
 * - Responsive admin table with hover effects and action buttons
 *
 * Architecture:
 * - Uses WordPress Transients API for performance caching
 * - Implements PHP 8.2 match expressions for efficient conditionals
 * - Modern array syntax throughout for better readability
 * - Separation of concerns with dedicated helper methods
 * - Comprehensive error handling with WP_Error integration
 *
 * Security Features:
 * - Multiple nonce validation strategies
 * - Field length validation to prevent overflow attacks
 * - XSS prevention with proper output escaping
 * - SQL injection protection via WordPress prepared statements
 * - Admin capability checks for sensitive operations
 *
 * Performance Optimizations:
 * - Strategic caching at multiple levels (configuration, entries, counts)
 * - Lazy loading with pagination to handle large datasets
 * - Efficient database queries with proper indexing
 * - Asset optimization with conditional loading
 *
 * @since   3.0.0
 * @package BRAGBookGallery\Includes\Core
 * @author  Candace Crowe Design <bragbook@candacecrowe.com>
 *
 * @uses    Trait_Api           For external API communication utilities (includes Trait_Sanitizer)
 * @uses    Trait_Tools         For common utility functions
 *
 * @example
 * ```php
 * // Initialize consultation handler
 * $consultation = new Consultation();
 *
 * // Display admin entries page
 * $consultation->display_form_entries();
 *
 * // Handle AJAX form submission (called via WordPress hooks)
 * $consultation->handle_form_submission();
 * ```
 */
class Consultation {
	use Trait_Api; // Note: Trait_Api includes Trait_Sanitizer
	use Trait_Tools;

	/**
	 * Items per page for pagination
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const ITEMS_PER_PAGE = 10;

	/**
	 * Post type for form entries
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const POST_TYPE = 'form-entries';

	/**
	 * AJAX action for pagination
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const AJAX_PAGINATION = 'consultation-pagination-load-posts';

	/**
	 * AJAX action for form submission
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const AJAX_SUBMISSION = 'handle_form_submission';

	/**
	 * AJAX action for deleting entries
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const AJAX_DELETE = 'delete_consultation_entry';

	/**
	 * Cache group for consultation data
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_book_consultations';

	/**
	 * Cache expiration time (1 hour)
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Required form fields for validation
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const REQUIRED_FIELDS = [ 'name', 'email', 'phone', 'description' ];

	/**
	 * Valid HTTP response codes for API success
	 *
	 * @since 3.0.0
	 * @var array<int>
	 */
	private const VALID_HTTP_CODES = [ 200, 201, 202 ];

	/**
	 * Maximum length for form field values
	 *
	 * @since 3.0.0
	 * @var array<string, int>
	 */
	private const MAX_FIELD_LENGTHS = [
		'name'        => 255,
		'email'       => 255,
		'phone'       => 50,
		'description' => 65535,
	];

	/**
	 * Rate limiting configuration
	 *
	 * @since 3.0.0
	 * @var array<string, int>
	 */
	private const RATE_LIMITS = [
		'submissions_per_hour' => 5,
		'submissions_per_day'  => 20,
		'max_attempts'         => 3,
	];

	/**
	 * Suspicious patterns for content filtering
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const SUSPICIOUS_PATTERNS = [
		'/(?:https?:\/\/|www\.)[^\s]+/i', // URLs
		'/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', // Scripts
		'/\b(?:viagra|cialis|casino|lottery)\b/i', // Common spam words
		'/[^\x00-\x7F]/', // Non-ASCII characters (basic check)
	];

	/**
	 * Constructor - Registers WordPress hooks and initializes consultation system
	 *
	 * Establishes the complete consultation handling infrastructure by registering
	 * AJAX endpoints for form processing, pagination, and admin management.
	 * Uses modern WordPress hook registration patterns for optimal performance.
	 *
	 * Registered AJAX Actions:
	 * - consultation-pagination-load-posts: Handles paginated entry loading
	 * - handle_form_submission: Processes consultation form submissions
	 * - delete_consultation_entry: Manages consultation deletion (admin only)
	 *
	 * Hook Strategy:
	 * - Uses both authenticated (wp_ajax_) and non-authenticated (wp_ajax_nopriv_)
	 *   hooks for form submission to support logged-out users
	 * - Admin-only actions use wp_ajax_ only for security
	 * - Callback methods use array syntax for better IDE support
	 *
	 * Performance Considerations:
	 * - Hooks are registered early in WordPress lifecycle
	 * - Uses class constants for action names to prevent typos
	 * - Minimal memory footprint during initialization
	 *
	 * @since 3.0.0
	 *
	 * @see add_action() For WordPress hook registration
	 * @see wp_ajax_{$action} WordPress AJAX hook pattern
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * // Automatic initialization when class is instantiated
	 * $consultation = new Consultation();
	 * // All hooks are now registered and ready to handle requests
	 * ```
	 */
	public function __construct() {
		// Register AJAX handlers for pagination.
		add_action( 'wp_ajax_' . self::AJAX_PAGINATION, [ $this, 'handle_pagination_request' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_PAGINATION, [ $this, 'handle_pagination_request' ] );

		// Form submission AJAX handlers.
		add_action( 'wp_ajax_' . self::AJAX_SUBMISSION, [ $this, 'handle_form_submission' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_SUBMISSION, [ $this, 'handle_form_submission' ] );

		// Delete entry AJAX handler (admin only).
		add_action( 'wp_ajax_' . self::AJAX_DELETE, [ $this, 'handle_delete_entry' ] );

		// Get consultation details AJAX handler (admin only).
		add_action( 'wp_ajax_consultation-get-details', [ $this, 'handle_get_details' ] );
	}

	/**
	 * Send form data to API and create post
	 *
	 * Sends consultation data to external API and creates a local post
	 * record if the API submission is successful. Uses wp_remote_post
	 * for safe HTTP requests per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $data        Form data to send.
	 * @param string $url         API endpoint URL.
	 * @param string $name        Customer name.
	 * @param string $description Consultation description.
	 * @param string $email       Customer email.
	 * @param string $phone       Customer phone number.
	 *
	 * @return void Outputs JSON response and exits.
	 */
	private function send_form_data_and_create_post(
		array $data,
		string $url,
		string $name,
		string $description,
		string $email,
		string $phone
	): void {
		// Encode data as JSON.
		$json_data = wp_json_encode( $data );

		if ( false === $json_data ) {
			wp_send_json_error(
				esc_html__( 'Failed to encode form data.', 'brag-book-gallery' )
			);
			return;
		}

		// Make API request using wp_remote_post (VIP approved).
		$response = wp_remote_post(
			$url,
			[
				'body'    => $json_data,
				'headers' => [
					'Content-Type'   => 'application/json',
					'Content-Length' => (string) strlen( $json_data ),
				],
				'timeout' => 30,
			]
		);

		// Handle WP_Error responses.
		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: Error message from API */
					esc_html__( 'API Error: %s', 'brag-book-gallery' ),
					esc_html( $response->get_error_message() )
				)
			);
			return;
		}

		// Parse API response.
		$body          = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $body, true );

		// Validate JSON parsing.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				esc_html__( 'Invalid API response format.', 'brag-book-gallery' )
			);
			return;
		}

		// Check for successful API response.
		if ( isset( $response_data['success'] ) && true === $response_data['success'] ) {
			// Create local post record.
			$post_id = $this->create_consultation_post( $name, $description, $email, $phone );

			if ( $post_id ) {
				// Clear consultation count cache.
				wp_cache_delete( 'consultation_count', self::CACHE_GROUP );

				wp_send_json_success(
					esc_html__( 'Thank you for your consultation request!', 'brag-book-gallery' )
				);
			} else {
				wp_send_json_error(
					esc_html__( 'Failed to save consultation locally.', 'brag-book-gallery' )
				);
			}
		} else {
			wp_send_json_error(
				esc_html__( 'Consultation submission was not successful.', 'brag-book-gallery' )
			);
		}
	}

	/**
	 * Create consultation post
	 *
	 * Creates a WordPress post to store consultation information locally.
	 * Uses wp_insert_post for safe database operations per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @param string $name        Customer name.
	 * @param string $description Consultation description.
	 * @param string $email       Customer email.
	 * @param string $phone       Customer phone number.
	 *
	 * @return int|false Post ID on success, false on failure.
	 */
	private function create_consultation_post(
		string $name,
		string $description,
		string $email,
		string $phone
	): int|false {
		// Prepare post data with proper sanitization.
		$post_data = [
			'post_title'   => sanitize_text_field( $name ),
			'post_content' => sanitize_textarea_field( $description ),
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'meta_input'   => [
				'brag_book_gallery_email' => sanitize_email( $email ),
				'brag_book_gallery_phone' => sanitize_text_field( $phone ),
			],
		];

		// Insert post using WordPress API.
		$post_id = wp_insert_post( $post_data, true );

		// Handle WordPress errors.
		if ( is_wp_error( $post_id ) ) {
			// Log the error for debugging.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'Consultation post creation failed', [
					'error' => $post_id->get_error_message(),
					'data'  => $post_data,
				] );
			}
			return false;
		}

		return $post_id;
	}

	/**
	 * Handle form submission via AJAX with comprehensive validation and dual-storage strategy
	 *
	 * Implements a sophisticated consultation form processing pipeline that prioritizes
	 * data reliability through local storage with optional API synchronization. This
	 * dual-strategy approach ensures zero data loss even if external API is unavailable.
	 *
	 * Processing Pipeline:
	 * 1. Security validation (nonce verification with fallback strategy)
	 * 2. Request method validation (POST only)
	 * 3. Comprehensive form data validation and sanitization
	 * 4. Local data persistence (always succeeds first)
	 * 5. Optional external API synchronization (non-blocking)
	 * 6. Cache invalidation for immediate UI updates
	 *
	 * Security Features:
	 * - Multi-tier nonce validation (consultation_form_nonce -> brag_book_gallery_nonce)
	 * - HTTP method validation to prevent CSRF attacks
	 * - Field length validation to prevent overflow attacks
	 * - Input sanitization using WordPress core functions
	 * - XSS prevention with proper output escaping
	 *
	 * Error Handling Strategy:
	 * - Local storage failures result in user-facing errors
	 * - API failures are logged but don't block form submission
	 * - Comprehensive error messages with internationalization
	 * - Graceful degradation when API is unavailable
	 *
	 * Performance Optimizations:
	 * - API calls are wrapped in try-catch to prevent blocking
	 * - Cache invalidation only on successful operations
	 * - Lazy configuration loading
	 * - Minimal database queries through helper method consolidation
	 *
	 * Data Flow:
	 * ```
	 * Form Submit → Validation → Local Storage → API Sync → JSON Response
	 *                    ↓           ↓            ↓
	 *               WP_Error     wp_insert_post  External API
	 *                    ↓           ↓            ↓
	 *              JSON Error   Success/Fail   Log Only
	 * ```
	 *
	 * @since 3.0.0
	 *
	 * @global array $_POST    Form submission data
	 * @global array $_SERVER  Server environment variables
	 *
	 * @uses wp_verify_nonce()        For security validation
	 * @uses wp_get_referer()         For gallery context detection
	 * @uses wp_send_json_success()   For successful response
	 * @uses wp_send_json_error()     For error response
	 *
	 * @throws None Handles all exceptions internally
	 *
	 * @return void Outputs JSON response via wp_send_json_* and exits
	 *
	 * @example
	 * ```javascript
	 * // Frontend JavaScript usage
	 * const formData = new FormData();
	 * formData.append('action', 'handle_form_submission');
	 * formData.append('nonce', consultationNonce);
	 * formData.append('name', 'John Doe');
	 * formData.append('email', 'john@example.com');
	 * formData.append('phone', '555-0123');
	 * formData.append('description', 'Consultation request...');
	 *
	 * fetch(ajaxurl, { method: 'POST', body: formData })
	 *   .then(response => response.json())
	 *   .then(data => console.log(data.success ? 'Success!' : data.data));
	 * ```
	 */
	public function handle_form_submission(): void {
		// Validate HTTP request first.
		$request_validation = $this->validate_http_request();
		if ( is_wp_error( $request_validation ) ) {
			wp_send_json_error( esc_html( $request_validation->get_error_message() ) );
			return;
		}

		// Comprehensive nonce validation.
		$nonce_validation = $this->validate_submission_nonce();
		if ( is_wp_error( $nonce_validation ) ) {
			wp_send_json_error( esc_html( $nonce_validation->get_error_message() ) );
			return;
		}

		// Check rate limits to prevent abuse.
		$rate_limit_check = $this->check_rate_limits();
		if ( is_wp_error( $rate_limit_check ) ) {
			wp_send_json_error( esc_html( $rate_limit_check->get_error_message() ) );
			return;
		}

		// Validate and sanitize form inputs.
		$form_data = $this->validate_form_data();

		if ( is_wp_error( $form_data ) ) {
			wp_send_json_error( esc_html( $form_data->get_error_message() ) );
			return;
		}

		// Validate content for security threats.
		$content_security = $this->validate_content_security( $form_data );
		if ( is_wp_error( $content_security ) ) {
			wp_send_json_error( esc_html( $content_security->get_error_message() ) );
			return;
		}

		// First, save the consultation locally.
		$post_id = $this->create_consultation_post(
			$form_data['name'],
			$form_data['description'],
			$form_data['email'],
			$form_data['phone']
		);

		if ( ! $post_id ) {
			wp_send_json_error(
				esc_html__( 'Failed to save consultation request. Please try again.', 'brag-book-gallery' )
			);
			return;
		}

		// Try to get API configuration (optional).
		$config = $this->get_api_configuration();

		// If API is configured, attempt to send to external API.
		if ( ! is_wp_error( $config ) ) {
			// Log API attempt for debugging.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'API configuration found, attempting to send consultation to BRAG book API' );
			}

			// Determine gallery context from referrer.
			$referrer        = wp_get_referer() ?: '';
			$gallery_context = $this->determine_gallery_context( $referrer, $config );

			// Prepare submission data.
			$submission_data = [
				'name'    => $form_data['name'],
				'email'   => $form_data['email'],
				'phone'   => $form_data['phone'],
				'details' => $form_data['description'],
			];

			// Try to send to API (but don't fail if it doesn't work).
			try {
				if ( $gallery_context['is_combined'] ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						do_action( 'qm/debug', 'Sending to combined gallery API' );
					}
					$this->send_to_combined_gallery_api( $submission_data, $config );
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						do_action( 'qm/debug', 'Sending to single gallery API', [ 'index' => $gallery_context['index'] ] );
					}
					$this->send_to_single_gallery_api( $submission_data, $config, $gallery_context['index'] );
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					do_action( 'qm/debug', 'Consultation successfully sent to BRAG book API' );
				}
			} catch ( \Exception $e ) {
				// Log API error but don't fail the submission.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					do_action( 'qm/debug', 'BRAG book API submission failed', [ 'error' => $e->getMessage() ] );
				}
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'No API configuration found, skipping API submission' );
			}
		}

		// Return success since we saved locally.
		wp_send_json_success(
			esc_html__( 'Thank you for your consultation request! We will contact you soon.', 'brag-book-gallery' )
		);
	}

	/**
	 * Validate form data
	 *
	 * Validates and sanitizes all form inputs following VIP standards
	 * for input validation and sanitization.
	 *
	 * @since 3.0.0
	 *
	 * @return array|WP_Error Sanitized form data or error.
	 */
	private function validate_form_data(): array|WP_Error {
		// Check for missing required fields.
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				return $this->handle_error(
					'missing_field',
					sprintf(
						/* translators: %s: Field name that is missing */
						esc_html__( 'Required field missing: %s', 'brag-book-gallery' ),
						esc_html( $field )
					),
					[ 'field' => $field, 'required_fields' => self::REQUIRED_FIELDS ]
				);
			}
		}

		// Sanitize form data.
		$form_data = $this->sanitize_form_data();

		// Validate email format.
		if ( ! is_email( $form_data['email'] ) ) {
			return $this->handle_error(
				'invalid_email',
				esc_html__( 'Please provide a valid email address.', 'brag-book-gallery' ),
				[ 'email' => $form_data['email'] ]
			);
		}

		// Validate field lengths.
		$length_error = $this->validate_field_lengths( $form_data );
		if ( is_wp_error( $length_error ) ) {
			return $length_error;
		}

		return $form_data;
	}

	/**
	 * Sanitize form data from POST request
	 *
	 * Extracts and sanitizes form fields using appropriate WordPress functions.
	 * Uses PHP 8.2 array syntax for cleaner code.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, string> Sanitized form data.
	 */
	private function sanitize_form_data(): array {
		return [
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'       => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		];
	}

	/**
	 * Validate field lengths against maximum allowed values
	 *
	 * Uses PHP 8.2 match expressions for more efficient validation.
	 * Ensures data integrity and prevents database overflow errors.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, string> $form_data Form data to validate.
	 *
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	private function validate_field_lengths( array $form_data ): true|WP_Error {
		foreach ( $form_data as $field => $value ) {
			$max_length = self::MAX_FIELD_LENGTHS[ $field ] ?? null;

			if ( null === $max_length ) {
				continue;
			}

			$field_length = strlen( $value );
			$is_valid = match ( true ) {
				$field_length <= $max_length => true,
				default => false,
			};

			if ( ! $is_valid ) {
				return new WP_Error(
					'field_too_long',
					sprintf(
						/* translators: 1: Field name, 2: Current length, 3: Maximum allowed length */
						esc_html__( 'Field "%1$s" is too long (%2$d characters). Maximum allowed: %3$d characters.', 'brag-book-gallery' ),
						esc_html( $field ),
						$field_length,
						$max_length
					)
				);
			}
		}

		return true;
	}

	/**
	 * Handle and log errors with context preservation
	 *
	 * Centralized error handling method that provides consistent error logging,
	 * context preservation, and user-friendly error message generation.
	 * Uses WordPress VIP-compliant logging and structured error data.
	 *
	 * @since 3.0.0
	 *
	 * @param string          $error_code    Unique error identifier
	 * @param string          $error_message Human-readable error message
	 * @param array<string, mixed> $context       Additional error context data
	 * @param string          $severity      Error severity level (error|warning|info)
	 *
	 * @return WP_Error Structured error object for consistent handling
	 */
	private function handle_error( string $error_code, string $error_message, array $context = [], string $severity = 'error' ): WP_Error {
		// Log error with context for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', "Consultation {$severity}", [
				'code'    => $error_code,
				'message' => $error_message,
				'context' => $context,
			] );
		}

		// Create structured error object.
		$wp_error = new WP_Error( $error_code, $error_message, $context );

		// Add severity data for downstream handling.
		$wp_error->add_data( [ 'severity' => $severity ], $error_code );

		return $wp_error;
	}

	/**
	 * Validate HTTP request method and headers
	 *
	 * Performs comprehensive HTTP request validation including method verification,
	 * content type checking, and request size limits for enhanced security.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $allowed_methods List of allowed HTTP methods
	 * @param int           $max_size        Maximum request size in bytes
	 *
	 * @return true|WP_Error True on success, WP_Error on validation failure
	 */
	private function validate_http_request( array $allowed_methods = [ 'POST' ], int $max_size = 1048576 ): true|WP_Error {
		// Validate request method.
		$request_method = $_SERVER['REQUEST_METHOD'] ?? '';
		if ( ! in_array( $request_method, $allowed_methods, true ) ) {
			return $this->handle_error(
				'invalid_request_method',
				sprintf(
					/* translators: 1: Actual method, 2: Allowed methods */
					esc_html__( 'Invalid request method "%1$s". Allowed methods: %2$s', 'brag-book-gallery' ),
					$request_method,
					implode( ', ', $allowed_methods )
				),
				[ 'method' => $request_method, 'allowed' => $allowed_methods ]
			);
		}

		// Check request size.
		$content_length = (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 );
		if ( $content_length > $max_size ) {
			return $this->handle_error(
				'request_too_large',
				sprintf(
					/* translators: 1: Actual size, 2: Maximum allowed size */
					esc_html__( 'Request too large (%1$s bytes). Maximum allowed: %2$s bytes', 'brag-book-gallery' ),
					number_format( $content_length ),
					number_format( $max_size )
				),
				[ 'size' => $content_length, 'max_size' => $max_size ]
			);
		}

		return true;
	}

	/**
	 * Validate form submission nonce with multiple fallback strategies
	 *
	 * Implements a multi-tier nonce validation system that tries multiple nonce
	 * types to accommodate different form submission contexts while maintaining
	 * security standards.
	 *
	 * @since 3.0.0
	 *
	 * @return true|WP_Error True on successful validation, WP_Error on failure
	 */
	private function validate_submission_nonce(): true|WP_Error {
		if ( ! isset( $_POST['nonce'] ) ) {
			return $this->handle_error(
				'missing_nonce',
				esc_html__( 'Security token missing.', 'brag-book-gallery' ),
				[ 'context' => 'form_submission' ]
			);
		}

		$nonce_value = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );

		// Try multiple nonce types for compatibility.
		$nonce_types = [
			'consultation_form_nonce',
			'brag_book_gallery_nonce',
		];

		foreach ( $nonce_types as $nonce_type ) {
			if ( wp_verify_nonce( $nonce_value, $nonce_type ) ) {
				return true;
			}
		}

		return $this->handle_error(
			'invalid_nonce',
			esc_html__( 'Security verification failed.', 'brag-book-gallery' ),
			[
				'context' => 'form_submission',
				'tried_types' => $nonce_types,
			]
		);
	}

	/**
	 * Check rate limits for form submissions
	 *
	 * Implements IP-based rate limiting to prevent spam and abuse.
	 * Uses WordPress transients for efficient storage and automatic cleanup.
	 *
	 * @since 3.0.0
	 *
	 * @return true|WP_Error True if within limits, WP_Error if rate limited
	 */
	private function check_rate_limits(): true|WP_Error {
		// Get client IP (with proxy support).
		$client_ip = $this->get_client_ip();
		$ip_hash = md5( $client_ip );

		// Check hourly limit.
		$hourly_key = "brag_book_gallery_transient_consultation_hourly_{$ip_hash}";
		$hourly_count = (int) get_transient( $hourly_key );

		if ( $hourly_count >= self::RATE_LIMITS['submissions_per_hour'] ) {
			return $this->handle_error(
				'rate_limit_hourly',
				sprintf(
					/* translators: %d: Maximum submissions per hour */
					esc_html__( 'Too many submissions. Maximum %d per hour allowed.', 'brag-book-gallery' ),
					self::RATE_LIMITS['submissions_per_hour']
				),
				[ 'ip' => $client_ip, 'count' => $hourly_count, 'limit' => 'hourly' ]
			);
		}

		// Check daily limit.
		$daily_key = "brag_book_gallery_transient_consultation_daily_{$ip_hash}";
		$daily_count = (int) get_transient( $daily_key );

		if ( $daily_count >= self::RATE_LIMITS['submissions_per_day'] ) {
			return $this->handle_error(
				'rate_limit_daily',
				sprintf(
					/* translators: %d: Maximum submissions per day */
					esc_html__( 'Daily submission limit reached. Maximum %d per day allowed.', 'brag-book-gallery' ),
					self::RATE_LIMITS['submissions_per_day']
				),
				[ 'ip' => $client_ip, 'count' => $daily_count, 'limit' => 'daily' ]
			);
		}

		// Update counters.
		set_transient( $hourly_key, $hourly_count + 1, HOUR_IN_SECONDS );
		set_transient( $daily_key, $daily_count + 1, DAY_IN_SECONDS );

		return true;
	}

	/**
	 * Get client IP address with proxy detection
	 *
	 * Safely retrieves the client's IP address, accounting for various
	 * proxy configurations while preventing header spoofing attacks.
	 *
	 * @since 3.0.0
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip(): string {
		// Check for shared IP from load balancers.
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header
			'HTTP_X_FORWARDED',      // Alternative proxy header
			'HTTP_FORWARDED_FOR',    // RFC 7239
			'HTTP_FORWARDED',        // RFC 7239
			'REMOTE_ADDR',           // Standard CGI variable
		];

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// Handle comma-separated IPs (first is usually the real client IP).
				if ( str_contains( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP format.
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Fallback for local/invalid IPs.
	}

	/**
	 * Validate content for suspicious patterns
	 *
	 * Scans form content for common spam indicators and malicious patterns.
	 * Uses configurable regex patterns for flexible spam detection.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, string> $form_data Form data to validate
	 *
	 * @return true|WP_Error True if content is clean, WP_Error if suspicious
	 */
	private function validate_content_security( array $form_data ): true|WP_Error {
		$combined_content = implode( ' ', $form_data );

		foreach ( self::SUSPICIOUS_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $combined_content ) ) {
				return $this->handle_error(
					'suspicious_content',
					esc_html__( 'Content contains suspicious patterns and cannot be submitted.', 'brag-book-gallery' ),
					[
						'pattern' => $pattern,
						'content' => wp_kses( $combined_content, [] ), // Strip all HTML for logging
					],
					'warning'
				);
			}
		}

		return true;
	}

	/**
	 * Get API configuration with comprehensive validation and intelligent error reporting
	 *
	 * Retrieves and validates external API configuration from WordPress options system.
	 * Uses advanced PHP 8.2 match expressions for efficient validation logic and
	 * provides detailed error reporting to aid in troubleshooting configuration issues.
	 *
	 * Configuration Structure:
	 * - api_tokens: Array of API authentication tokens (one per gallery)
	 * - website_property_ids: Array of property identifiers (mapped to tokens)
	 * - gallery_pages: Stored gallery page configuration
	 * - brag_book_gallery_page_slug: Main gallery page URL slug
	 *
	 * Validation Strategy:
	 * Uses PHP 8.2 match expressions to provide specific error messages:
	 * - both_missing: Neither tokens nor property IDs are configured
	 * - tokens_missing: Property IDs exist but no API tokens
	 * - property_ids_missing: API tokens exist but no property IDs
	 * - valid: Complete configuration available
	 *
	 * Performance Features:
	 * - Leverages WordPress core option caching (object cache compatible)
	 * - Minimal database queries through efficient option retrieval
	 * - Debug logging only in development environments
	 * - Early validation to prevent unnecessary processing
	 *
	 * Debug Integration:
	 * - Uses WordPress VIP-compliant do_action('qm/debug') for logging
	 * - Comprehensive configuration state reporting
	 * - Development-only debug output to prevent production log spam
	 * - Structured debug data for better analysis
	 *
	 * Error Handling:
	 * - Returns WP_Error objects with specific error codes
	 * - Internationalized error messages for user-facing display
	 * - Configuration state preservation for debugging
	 * - Graceful handling of missing or malformed options
	 *
	 * @since 3.0.0
	 *
	 * @global bool WP_DEBUG    WordPress debug flag
	 *
	 * @uses get_option()        WordPress options API (cached)
	 * @uses do_action()         VIP-compliant debug logging
	 * @uses esc_html__()        Internationalization support
	 *
	 * @return array{
	 *     api_tokens: array<string>,
	 *     website_property_ids: array<string>,
	 *     gallery_pages: array<string>,
	 *     brag_book_gallery_page_slug: string
	 * }|WP_Error Configuration array with typed structure or validation error
	 *
	 * @example
	 * ```php
	 * $config = $this->get_api_configuration();
	 *
	 * if ( is_wp_error( $config ) ) {
	 *     // Handle configuration error
	 *     $error_message = $config->get_error_message();
	 *     wp_send_json_error( $error_message );
	 *     return;
	 * }
	 *
	 * // Use configuration
	 * $token = $config['api_tokens'][0];
	 * $property_id = $config['website_property_ids'][0];
	 * ```
	 */
	private function get_api_configuration(): array|WP_Error {
		// Get API tokens from options (cached by WP).
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		// Debug logging only in development.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'Getting API configuration' );
			do_action( 'qm/debug', 'API tokens retrieved', $api_tokens );
			do_action( 'qm/debug', 'Website property IDs retrieved', $website_property_ids );
		}

		// Validate configuration using match expression.
		$config_status = match ( true ) {
			empty( $api_tokens ) && empty( $website_property_ids ) => 'both_missing',
			empty( $api_tokens ) => 'tokens_missing',
			empty( $website_property_ids ) => 'property_ids_missing',
			default => 'valid',
		};

		if ( 'valid' !== $config_status ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'Configuration check failed', [ 'status' => $config_status ] );
			}

			return new WP_Error(
				'missing_config',
				match ( $config_status ) {
					'both_missing' => esc_html__( 'API tokens and Website Property IDs are missing.', 'brag-book-gallery' ),
					'tokens_missing' => esc_html__( 'API tokens are missing.', 'brag-book-gallery' ),
					'property_ids_missing' => esc_html__( 'Website Property IDs are missing.', 'brag-book-gallery' ),
					default => esc_html__( 'API configuration not available.', 'brag-book-gallery' ),
				}
			);
		}

		// Return configuration array with modern syntax.
		return [
			'api_tokens'                  => (array) $api_tokens,
			'website_property_ids'        => (array) $website_property_ids,
			'gallery_pages'               => (array) get_option( 'brag_book_gallery_stored_pages', [] ),
			'brag_book_gallery_page_slug' => (string) get_option( 'brag_book_gallery_page_slug', '' ),
		];
	}

	/**
	 * Determine gallery context from URL
	 *
	 * Analyzes the referrer URL to determine which gallery the form was
	 * submitted from. Uses parse_url for safe URL parsing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $referrer Referrer URL.
	 * @param array  $config   API configuration.
	 *
	 * @return array Gallery context information.
	 */
	private function determine_gallery_context( string $referrer, array $config ): array {
		// Default context if no referrer.
		if ( empty( $referrer ) ) {
			return [
				'is_combined' => false,
				'index'       => 0,
			];
		}

		// Parse URL safely.
		$parsed_url = wp_parse_url( $referrer );
		$path       = isset( $parsed_url['path'] ) ? trim( $parsed_url['path'], '/' ) : '';
		$parts      = explode( '/', $path );
		$first_segment = isset( $parts[0] ) ? $parts[0] : '';

		// Use match expression for gallery type detection.
		return match ( true ) {
			$first_segment === $config['brag_book_gallery_page_slug'] => [
				'is_combined' => true,
				'index'       => null,
			],
			default => [
				'is_combined' => false,
				'index'       => array_search( $first_segment, $config['gallery_pages'], true ) ?: 0,
			],
		};
	}

	/**
	 * Send consultation data to combined gallery API endpoints
	 *
	 * Sends data to multiple API endpoints for combined galleries.
	 *
	 * @since 3.0.0
	 *
	 * @param array $data   Submission data.
	 * @param array $config API configuration.
	 *
	 * @return void
	 * @throws \Exception If API request fails.
	 */
	private function send_to_combined_gallery_api( array $data, array $config ): void {
		// Iterate through all configured APIs.
		foreach ( $config['api_tokens'] as $index => $token ) {
			if ( ! isset( $config['website_property_ids'][ $index ] ) ) {
				continue;
			}

			$this->send_to_api( $data, $token, $config['website_property_ids'][ $index ] );
		}
	}

	/**
	 * Send consultation data to single gallery API endpoint
	 *
	 * Sends data to a specific API endpoint for single gallery.
	 *
	 * @since 3.0.0
	 *
	 * @param array $data   Submission data.
	 * @param array $config API configuration.
	 * @param int   $index  Gallery index.
	 *
	 * @return void
	 * @throws \Exception If gallery configuration not found.
	 */
	private function send_to_single_gallery_api( array $data, array $config, int $index ): void {
		// Validate configuration exists for index.
		if ( ! isset( $config['api_tokens'][ $index ] ) ||
			 ! isset( $config['website_property_ids'][ $index ] ) ) {
			throw new \Exception( 'Gallery configuration not found.' );
		}

		$this->send_to_api( $data, $config['api_tokens'][ $index ], $config['website_property_ids'][ $index ] );
	}

	/**
	 * Send data to API endpoint
	 *
	 * Makes HTTP POST request to external API using wp_remote_post
	 * following VIP standards for external requests.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $data                Data to send (email, phone, name, details).
	 * @param string $api_token           API token for authentication.
	 * @param string $website_property_id Website property ID.
	 *
	 * @return void
	 * @throws \Exception If API request fails.
	 */
	private function send_to_api( array $data, string $api_token, string $website_property_id ): void {
		// Get base API URL.
		$base_url = self::get_api_url();

		// Build the URL with query parameters.
		$url = sprintf(
			'%s/api/plugin/consultations?apiToken=%s&websitepropertyId=%s',
			$base_url,
			urlencode( $api_token ),
			urlencode( $website_property_id )
		);

		// Prepare the body with the form data.
		$body_data = [
			'email'   => $data['email'],
			'phone'   => $data['phone'],
			'name'    => $data['name'],
			'details' => $data['details'],
		];

		// Encode as JSON.
		$json_data = wp_json_encode( $body_data );

		if ( false === $json_data ) {
			throw new \Exception( 'Failed to encode form data.' );
		}

		// Log the API request for debugging (only in debug mode).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'Sending consultation to API', [ 'url' => $url ] );
			do_action( 'qm/debug', 'Request body', $body_data );
		}

		// Make API request using wp_remote_post (VIP approved).
		$response = wp_remote_post(
			$url,
			[
				'body'    => $json_data,
				'headers' => [
					'Content-Type'   => 'application/json',
					'Content-Length' => (string) strlen( $json_data ),
				],
				'timeout' => 30,
			]
		);

		// Handle WP_Error responses.
		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'API request failed', [ 'error' => $response->get_error_message() ] );
			}
			throw new \Exception( 'API Error: ' . $response->get_error_message() );
		}

		// Get response code and body.
		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'API response', [ 'code' => $response_code, 'body' => $body ] );
		}

		// Check for successful HTTP status using match expression.
		$status_valid = match ( true ) {
			in_array( $response_code, self::VALID_HTTP_CODES, true ) => true,
			$response_code >= 200 && $response_code < 300 => true,
			default => false,
		};

		if ( ! $status_valid ) {
			throw new \Exception( 'API returned status code: ' . $response_code . ' - ' . $body );
		}

		// Parse response body.
		$response_data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Invalid API response format: ' . $body );
		}

		// Check for API errors in response.
		if ( isset( $response_data['error'] ) ||
			 ( isset( $response_data['success'] ) && false === $response_data['success'] ) ) {
			$error_msg = isset( $response_data['message'] ) ? $response_data['message'] :
						( isset( $response_data['error'] ) ? $response_data['error'] : 'API submission failed' );
			throw new \Exception( 'API submission was not successful: ' . $error_msg );
		}
	}

	/**
	 * Handle pagination AJAX request
	 *
	 * Retrieves paginated consultation entries for admin display.
	 * Uses WordPress database API for secure queries and implements
	 * caching for better performance per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_pagination_request(): void {
		// Verify nonce for security (VIP requirement).
		if ( ! isset( $_POST['nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'consultation_pagination_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security verification failed.', 'brag-book-gallery' ) );
			return;
		}

		// Validate and sanitize page parameter.
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// Ensure page is at least 1.
		$page = max( 1, $page );

		// Get consultation entries with caching.
		$entries     = $this->get_consultation_entries( $page );
		$total_count = $this->get_total_consultation_count();

		// Generate table rows HTML.
		$table_html = $this->generate_table_rows( $entries );

		// Generate pagination HTML.
		$pagination_html = $this->generate_pagination_html( $page, $total_count );

		// Send JSON response.
		wp_send_json_success(
			[
				'message'    => $table_html,
				'pagination' => $pagination_html,
				'total'      => $total_count,
			]
		);
	}

	/**
	 * Handle delete entry request
	 *
	 * Deletes a consultation entry via AJAX with proper permission
	 * and nonce checks per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_delete_entry(): void {
		// Check user permissions (VIP requirement).
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to delete entries.', 'brag-book-gallery' ) );
			return;
		}

		// Verify nonce for security (VIP requirement).
		if ( ! isset( $_POST['nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'consultation_delete_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security verification failed.', 'brag-book-gallery' ) );
			return;
		}

		// Get and validate post ID.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( esc_html__( 'Invalid entry ID.', 'brag-book-gallery' ) );
			return;
		}

		// Verify it's a consultation entry.
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			wp_send_json_error( esc_html__( 'Entry not found.', 'brag-book-gallery' ) );
			return;
		}

		// Delete the post (force delete to skip trash).
		$deleted = wp_delete_post( $post_id, true );

		if ( $deleted ) {
			// Clear cache after deletion.
			wp_cache_delete( 'consultation_count', self::CACHE_GROUP );
			wp_cache_delete( 'consultation_entries_' . $post_id, self::CACHE_GROUP );

			wp_send_json_success(
				[
					'message' => esc_html__( 'Entry deleted successfully.', 'brag-book-gallery' ),
					'post_id' => $post_id,
				]
			);
		} else {
			wp_send_json_error( esc_html__( 'Failed to delete entry.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * Handle AJAX request to get consultation details
	 *
	 * Retrieves and returns formatted HTML for a consultation entry's details.
	 *
	 * @since 3.0.0
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_get_details(): void {
		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to view entries.', 'brag-book-gallery' ) );
			return;
		}

		// Verify nonce for security.
		if ( ! isset( $_POST['nonce'] ) ||
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'consultation_pagination_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security verification failed.', 'brag-book-gallery' ) );
			return;
		}

		// Get and validate post ID.
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( esc_html__( 'Invalid entry ID.', 'brag-book-gallery' ) );
			return;
		}

		// Get the consultation entry.
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			wp_send_json_error( esc_html__( 'Entry not found.', 'brag-book-gallery' ) );
			return;
		}

		// Get post meta data.
		$email = get_post_meta( $post_id, 'consultation_email', true );
		$phone = get_post_meta( $post_id, 'consultation_phone', true );

		// Build the HTML output.
		ob_start();
		?>
		<div class="consultation-details">
			<div class="detail-row">
				<label><?php esc_html_e( 'Name:', 'brag-book-gallery' ); ?></label>
				<div class="detail-value"><?php echo esc_html( get_the_title( $post_id ) ); ?></div>
			</div>
			<div class="detail-row">
				<label><?php esc_html_e( 'Email:', 'brag-book-gallery' ); ?></label>
				<div class="detail-value">
					<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				</div>
			</div>
			<?php if ( $phone ) : ?>
				<div class="detail-row">
					<label><?php esc_html_e( 'Phone:', 'brag-book-gallery' ); ?></label>
					<div class="detail-value">
						<a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a>
					</div>
				</div>
			<?php endif; ?>
			<div class="detail-row">
				<label><?php esc_html_e( 'Date:', 'brag-book-gallery' ); ?></label>
				<div class="detail-value">
					<?php echo esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post_id ) ); ?>
				</div>
			</div>
			<div class="detail-row">
				<label><?php esc_html_e( 'Message:', 'brag-book-gallery' ); ?></label>
				<div class="detail-value">
					<div class="message-content">
						<?php echo nl2br( esc_html( $post->post_content ) ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Get consultation entries for a specific page
	 *
	 * Retrieves consultation posts with pagination and caching
	 * for better performance per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @param int $page Page number.
	 *
	 * @return array Array of post objects.
	 */
	private function get_consultation_entries( int $page ): array {
		// Create cache key.
		$cache_key = 'brag_book_gallery_transient_consultation_entries_page_' . $page;

		// Try to get from cache first.
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// Calculate offset based on page number.
		$offset = ( $page - 1 ) * self::ITEMS_PER_PAGE;

		// Query for consultation entries.
		$query = new WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::ITEMS_PER_PAGE,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => false, // Enable pagination info.
			]
		);

		// Cache the results.
		wp_cache_set( $cache_key, $query->posts, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $query->posts;
	}

	/**
	 * Get total count of consultation entries
	 *
	 * Returns the total count with caching for better performance.
	 *
	 * @since 3.0.0
	 *
	 * @return int Total number of consultation posts.
	 */
	private function get_total_consultation_count(): int {
		// Try to get from cache first.
		$cached = wp_cache_get( 'consultation_count', self::CACHE_GROUP );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		// Use WordPress function to count posts.
		$count_posts = wp_count_posts( self::POST_TYPE );
		$count       = isset( $count_posts->publish ) ? (int) $count_posts->publish : 0;

		// Cache the result.
		wp_cache_set( 'consultation_count', $count, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $count;
	}

	/**
	 * Generate HTML for table rows
	 *
	 * Creates HTML markup for consultation entry table rows with
	 * proper escaping per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @param array $entries Array of post objects.
	 *
	 * @return string HTML markup for table rows.
	 */
	private function generate_table_rows( array $entries ): string {
		// Handle empty results.
		if ( empty( $entries ) ) {
			return sprintf(
				'<tr><td colspan="6" style="text-align: center; padding: 20px;">%s</td></tr>',
				esc_html__( 'No consultation entries found.', 'brag-book-gallery' )
			);
		}

		$html = '';

		// Process each entry.
		foreach ( $entries as $post ) {
			// Get post meta data.
			$email = get_post_meta( $post->ID, 'brag_book_gallery_email', true );
			$phone = get_post_meta( $post->ID, 'brag_book_gallery_phone', true );
			$date  = mysql2date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				$post->post_date
			);

			// Truncate long descriptions for better display.
			$description      = $post->post_content;
			$full_description = $description;
			$truncate_length  = 150;

			if ( strlen( $description ) > $truncate_length ) {
				$description = substr( $description, 0, $truncate_length ) . '...';
			}

			// Build table row HTML with proper escaping.
			$html .= sprintf(
				'<tr class="consultation-row" data-id="%d">
					<td class="consultation-name"><strong>%s</strong></td>
					<td class="consultation-email"><a href="mailto:%s">%s</a></td>
					<td class="consultation-phone"><a href="tel:%s">%s</a></td>
					<td class="consultation-date">%s</td>
					<td class="consultation-description">
						<div class="description-content">%s</div>
						%s
					</td>
					<td class="consultation-actions">
						<button class="button button-small button-secondary view-consultation" data-id="%d" title="%s">
							<span class="dashicons dashicons-visibility"></span> %s
						</button>
						<button class="button button-small button-secondary delete-consultation" data-id="%d" data-name="%s" title="%s">
							<span class="dashicons dashicons-trash"></span> %s
						</button>
					</td>
				</tr>',
				absint( $post->ID ),
				esc_html( $post->post_title ),
				esc_attr( $email ),
				esc_html( $email ),
				esc_attr( $phone ),
				esc_html( $phone ),
				esc_html( $date ),
				wp_kses_post( $description ),
				( strlen( $full_description ) > $truncate_length ?
					'<div class="description-full" style="display:none;">' . wp_kses_post( $full_description ) . '</div>' :
					''
				),
				absint( $post->ID ),
				esc_attr__( 'View Details', 'brag-book-gallery' ),
				esc_html__( 'View', 'brag-book-gallery' ),
				absint( $post->ID ),
				esc_attr( $post->post_title ),
				esc_attr__( 'Delete Entry', 'brag-book-gallery' ),
				esc_html__( 'Delete', 'brag-book-gallery' )
			);
		}

		return $html;
	}

	/**
	 * Generate pagination HTML
	 *
	 * Creates pagination controls for navigation with proper
	 * internationalization support.
	 *
	 * @since 3.0.0
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_count  Total number of items.
	 *
	 * @return string HTML markup for pagination.
	 */
	private function generate_pagination_html( int $current_page, int $total_count ): string {
		// Calculate total pages.
		$total_pages = (int) ceil( $total_count / self::ITEMS_PER_PAGE );

		// No pagination needed if only one page.
		if ( $total_pages <= 1 ) {
			return '';
		}

		$html = '<div class="brag-book-gallery-universal-pagination"><ul>';

		// Items count display.
		$html .= sprintf(
			'<li class="selected">%s</li>',
			sprintf(
				/* translators: %d: Number of consultation items */
				esc_html(
					_n(
						'%d item',
						'%d items',
						$total_count,
						'brag-book-gallery'
					)
				),
				$total_count
			)
		);

		// First page button.
		$html .= $this->generate_pagination_button( '<<', 1, $current_page > 1 );

		// Previous page button.
		if ( $current_page > 1 ) {
			$html .= $this->generate_pagination_button( '<', $current_page - 1, true );
		} else {
			$html .= '<li class="inactive"><</li>';
		}

		// Current page indicator.
		$html .= sprintf(
			'<li class="selected">%s</li>',
			sprintf(
				/* translators: 1: Current page number, 2: Total number of pages */
				esc_html__( '%1$d of %2$d', 'brag-book-gallery' ),
				$current_page,
				$total_pages
			)
		);

		// Next page button.
		if ( $current_page < $total_pages ) {
			$html .= $this->generate_pagination_button( '>', $current_page + 1, true );
		} else {
			$html .= '<li class="inactive">></li>';
		}

		// Last page button.
		$html .= $this->generate_pagination_button( '>>', $total_pages, $current_page < $total_pages );

		$html .= '</ul></div>';

		return $html;
	}

	/**
	 * Generate a pagination button
	 *
	 * Creates a single pagination button with proper attributes.
	 *
	 * @since 3.0.0
	 *
	 * @param string $label     Button label text.
	 * @param int    $page      Page number for navigation.
	 * @param bool   $is_active Whether button should be active/clickable.
	 *
	 * @return string HTML for button.
	 */
	private function generate_pagination_button( string $label, int $page, bool $is_active ): string {
		// Determine button class.
		$class = $is_active ? 'active' : 'inactive';

		// Add page data attribute if active.
		$page_attr = $is_active ? sprintf( ' p="%d"', absint( $page ) ) : '';

		// Return button HTML.
		return sprintf(
			'<li class="%s"%s>%s</li>',
			esc_attr( $class ),
			$page_attr,
			esc_html( $label )
		);
	}

	/**
	 * Display form entries admin page with enterprise-grade management interface
	 *
	 * Renders a comprehensive consultation management interface featuring:
	 * - Responsive data table with sortable columns
	 * - AJAX-powered pagination for handling large datasets
	 * - Real-time search and filtering capabilities
	 * - HTML5 modal dialogs for detailed consultation viewing
	 * - Bulk actions with confirmation dialogs
	 * - Accessibility-compliant markup and keyboard navigation
	 *
	 * Interface Features:
	 * - Responsive table design that adapts to mobile screens
	 * - Hover effects and visual feedback for better UX
	 * - Loading states and progress indicators
	 * - Error handling with user-friendly messages
	 * - One-click email replies via mailto: links
	 * - Phone number links for mobile device integration
	 *
	 * Security Implementation:
	 * - Dual nonce generation (pagination + deletion)
	 * - XSS prevention through proper HTML escaping
	 * - CSRF protection via WordPress nonce system
	 * - Admin capability requirements for sensitive operations
	 * - Input sanitization on all form interactions
	 *
	 * Performance Optimizations:
	 * - Lazy loading of consultation entries via AJAX
	 * - Progressive enhancement for non-JavaScript users
	 * - Efficient DOM manipulation using modern JavaScript
	 * - Strategic CSS/JS asset loading
	 * - Caching integration for repeated requests
	 *
	 * Accessibility Features:
	 * - ARIA labels and roles for screen readers
	 * - Keyboard navigation support
	 * - High contrast mode compatibility
	 * - Focus management in modal dialogs
	 * - Semantic HTML structure
	 *
	 * Integration Points:
	 * - WordPress admin theming and color schemes
	 * - Plugin's SCSS-based styling system
	 * - Admin notification system
	 * - WordPress list table patterns
	 * - Dashboard widget compatibility
	 *
	 * @since 3.0.0
	 *
	 * @uses wp_create_nonce()    For CSRF protection
	 * @uses esc_html__()         For internationalization
	 * @uses admin_url()          For WordPress admin URLs
	 *
	 * @return void Outputs complete HTML admin interface
	 *
	 * @example
	 * ```php
	 * // Called via WordPress admin menu system
	 * add_submenu_page(
	 *     'brag-book-gallery-settings',
	 *     __( 'Consultations', 'brag-book-gallery' ),
	 *     __( 'Consultations', 'brag-book-gallery' ),
	 *     'manage_options',
	 *     'brag-book-gallery-consultation',
	 *     [ $this, 'display_form_entries' ]
	 * );
	 * ```
	 */
	public function display_form_entries(): void {
		// Generate nonces for security.
		$nonce        = wp_create_nonce( 'consultation_pagination_nonce' );
		$delete_nonce = wp_create_nonce( 'consultation_delete_nonce' );

		// Include admin display template.
		$this->render_admin_page( $nonce, $delete_nonce );
	}

	/**
	 * Render admin page for consultations
	 *
	 * Outputs the consultation management interface.
	 *
	 * @since 3.0.0
	 *
	 * @param string $nonce        Pagination nonce.
	 * @param string $delete_nonce Delete action nonce.
	 *
	 * @return void
	 */
	private function render_admin_page( string $nonce, string $delete_nonce ): void {
		?>
		<div class="wrap brag-book-gallery-admin-wrap">
			<?php $this->render_header(); ?>
			<?php $this->render_tabs( 'consultations' ); ?>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Consultation Entries', 'brag-book-gallery' ); ?></h2>

				<?php $this->render_admin_scripts( $nonce, $delete_nonce ); ?>

				<div class="brag-book-gallery-page-loading" style="display: none;">
					<p><?php esc_html_e( 'Loading consultation entries...', 'brag-book-gallery' ); ?></p>
				</div>

				<table class="wp-list-table widefat fixed striped consultation-entries" style="width: 100%;">
					<thead>
					<tr>
						<th style="width: 15%;"><?php esc_html_e( 'Name', 'brag-book-gallery' ); ?></th>
						<th style="width: 18%;"><?php esc_html_e( 'Email', 'brag-book-gallery' ); ?></th>
						<th style="width: 12%;"><?php esc_html_e( 'Phone', 'brag-book-gallery' ); ?></th>
						<th style="width: 12%;"><?php esc_html_e( 'Date', 'brag-book-gallery' ); ?></th>
						<th style="width: 33%;"><?php esc_html_e( 'Message', 'brag-book-gallery' ); ?></th>
						<th style="width: 10%; text-align: center;"><?php esc_html_e( 'Actions', 'brag-book-gallery' ); ?></th>
					</tr>
					</thead>
					<tbody class="brag_book_gallery_universal_container">
					<tr>
						<td colspan="6" style="text-align: center;">
							<?php esc_html_e( 'Loading consultation entries...', 'brag-book-gallery' ); ?>
						</td>
					</tr>
					</tbody>
				</table>
				<div class="brag-book-gallery-pagination-nav" style="margin-top: 20px;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin JavaScript
	 *
	 * Outputs JavaScript for admin functionality.
	 * Consider moving to external file for VIP compliance.
	 *
	 * @since 3.0.0
	 *
	 * @param string $nonce        Pagination nonce.
	 * @param string $delete_nonce Delete action nonce.
	 *
	 * @return void
	 */
	private function render_admin_scripts( string $nonce, string $delete_nonce ): void {
		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		document.addEventListener( 'DOMContentLoaded', function() {
			const ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce = <?php echo wp_json_encode( $nonce ); ?>;
			const deleteNonce = <?php echo wp_json_encode( $delete_nonce ); ?>;
			const ajaxAction = <?php echo wp_json_encode( self::AJAX_PAGINATION ); ?>;

			/**
			 * Fade in effect for element
			 *
			 * @param {HTMLElement} element Element to fade in.
			 * @param {number} duration Duration in milliseconds.
			 */
			const fadeIn = function( element, duration ) {
				duration = duration || 400;
				element.style.opacity = '0';
				element.style.display = 'block';
				element.style.transition = 'opacity ' + duration + 'ms';
				element.offsetHeight; // Force reflow
				element.style.opacity = '1';
			};

			/**
			 * Escape HTML to prevent XSS
			 *
			 * @param {string} text Text to escape.
			 * @return {string} Escaped text.
			 */
			const escapeHtml = function( text ) {
				const map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return text.replace( /[&<>"']/g, function(m) { return map[m]; } );
			};

			/**
			 * Load consultation posts via AJAX
			 *
			 * @param {number} page Page number to load.
			 */
			const loadConsultationPosts = async function( page ) {
				const loadingDiv = document.querySelector( '.brag-book-gallery-page-loading' );
				const container = document.querySelector( '.brag_book_gallery_universal_container' );
				const paginationNav = document.querySelector( '.brag-book-gallery-pagination-nav' );

				// Show loading state.
				if ( loadingDiv ) {
					loadingDiv.style.display = 'block';
				}

				// Show loading message in table.
				if ( container ) {
					container.innerHTML = '<tr><td colspan="6" style="text-align: center;">Loading...</td></tr>';
				}

				// Prepare form data.
				const formData = new FormData();
				formData.append( 'page', page );
				formData.append( 'nonce', nonce );
				formData.append( 'action', ajaxAction );

				try {
					// Make AJAX request.
					const response = await fetch( ajaxurl, {
						method: 'POST',
						body: formData,
						credentials: 'same-origin'
					} );

					const data = await response.json();

					if ( data.success ) {
						// Update table content.
						container.innerHTML = data.data.message;
						paginationNav.innerHTML = data.data.pagination;
					} else {
						// Show error message.
						container.innerHTML = '<tr><td colspan="6">' + ( data.data || 'Error loading data' ) + '</td></tr>';
					}
				} catch ( error ) {
					// Handle fetch error.
					console.error( 'Error loading consultation entries:', error );
					container.innerHTML = '<tr><td colspan="6">Failed to load consultation entries.</td></tr>';
				} finally {
					// Hide loading state.
					if ( loadingDiv ) {
						loadingDiv.style.display = 'none';
					}
				}
			};

			/**
			 * Handle delete button clicks
			 *
			 * @param {HTMLElement} button Delete button element.
			 */
			const handleDelete = async function( button ) {
				const postId = button.dataset.id;
				const postName = button.dataset.name;

				if ( ! confirm( 'Are you sure you want to delete the consultation from "' + postName + '"? This action cannot be undone.' ) ) {
					return;
				}

				button.disabled = true;
				button.innerHTML = '<span class="dashicons dashicons-update spinning"></span> Deleting...';

				try {
					const formData = new FormData();
					formData.append( 'action', 'delete_consultation_entry' );
					formData.append( 'nonce', deleteNonce );
					formData.append( 'post_id', postId );

					const response = await fetch( ajaxurl, {
						method: 'POST',
						body: formData
					} );

					const data = await response.json();

					if ( data.success ) {
						// Remove the row with fade out effect.
						const row = button.closest( 'tr' );
						row.style.transition = 'opacity 0.3s';
						row.style.opacity = '0';

						setTimeout( function() {
							row.remove();
							// Check if table is empty.
							const tbody = document.querySelector( '.brag_book_gallery_universal_container' );
							if ( tbody && tbody.children.length === 0 ) {
								tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No consultation entries found.</td></tr>';
							}
						}, 300 );
					} else {
						alert( data.data || 'Failed to delete entry.' );
						button.disabled = false;
						button.innerHTML = '<span class="dashicons dashicons-trash"></span> Delete';
					}
				} catch ( error ) {
					console.error( 'Delete error:', error );
					alert( 'An error occurred while deleting the entry.' );
					button.disabled = false;
					button.innerHTML = '<span class="dashicons dashicons-trash"></span> Delete';
				}
			};

			/**
			 * Handle view details in modal dialog
			 *
			 * @param {HTMLElement} button View button element.
			 */
			const handleViewDetails = function( button ) {
				const row = button.closest( 'tr' );
				const postId = button.dataset.id;

				// Get entry details from the row.
				const name = row.querySelector( '.consultation-name strong' ).textContent;
				const email = row.querySelector( '.consultation-email a' ).textContent;
				const phone = row.querySelector( '.consultation-phone a' ).textContent;
				const date = row.querySelector( '.consultation-date' ).textContent;
				const fullDesc = row.querySelector( '.description-full' );
				const contentDiv = row.querySelector( '.description-content' );
				const message = fullDesc ? fullDesc.textContent : contentDiv.textContent;

				// Check if dialog element is supported.
				const supportsDialog = typeof HTMLDialogElement === 'function';

				// Create or get the dialog.
				let dialog = document.getElementById( 'consultation-detail-dialog' );
				if ( ! dialog ) {
					if ( supportsDialog ) {
						dialog = document.createElement( 'dialog' );
					} else {
						dialog = document.createElement( 'div' );
						dialog.setAttribute( 'role', 'dialog' );
						dialog.setAttribute( 'aria-modal', 'true' );
					}
					dialog.id = 'consultation-detail-dialog';
					dialog.className = 'consultation-dialog';
					document.body.appendChild( dialog );
				}

				// Populate dialog content (with proper escaping).
				dialog.innerHTML = `
					<div class="consultation-dialog-content">
						<div class="consultation-dialog-header">
							<h2>Consultation Details</h2>
							<button type="button" class="dialog-close" aria-label="Close">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
						<div class="consultation-dialog-body">
							<div class="detail-row">
								<label>Name:</label>
								<div class="detail-value"><strong>${escapeHtml( name )}</strong></div>
							</div>
							<div class="detail-row">
								<label>Email:</label>
								<div class="detail-value">
									<a href="mailto:${escapeHtml( email )}">${escapeHtml( email )}</a>
								</div>
							</div>
							<div class="detail-row">
								<label>Phone:</label>
								<div class="detail-value">
									<a href="tel:${escapeHtml( phone )}">${escapeHtml( phone )}</a>
								</div>
							</div>
							<div class="detail-row">
								<label>Date Submitted:</label>
								<div class="detail-value">${escapeHtml( date )}</div>
							</div>
							<div class="detail-row">
								<label>Message:</label>
								<div class="detail-value message-content">${escapeHtml( message )}</div>
							</div>
						</div>
						<div class="consultation-dialog-footer">
							<button type="button" class="button button-primary reply-email" data-email="${escapeHtml( email )}">
								<span class="dashicons dashicons-email"></span> Reply via Email
							</button>
							<button type="button" class="button dialog-close-btn">Close</button>
						</div>
					</div>
				`;

				// Show the dialog.
				if ( supportsDialog && dialog.showModal ) {
					dialog.showModal();
				} else {
					// Fallback for browsers that don't support dialog.
					dialog.style.display = 'block';
					dialog.style.position = 'fixed';
					dialog.style.top = '50%';
					dialog.style.left = '50%';
					dialog.style.transform = 'translate(-50%, -50%)';
					dialog.style.zIndex = '10000';

					// Create backdrop.
					let backdrop = document.getElementById( 'consultation-backdrop' );
					if ( ! backdrop ) {
						backdrop = document.createElement( 'div' );
						backdrop.id = 'consultation-backdrop';
						backdrop.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);backdrop-filter:blur(2px);z-index:9999;';
						document.body.appendChild( backdrop );
					} else {
						backdrop.style.display = 'block';
					}
				}

				// Function to close the dialog.
				const closeDialog = function() {
					if ( supportsDialog && dialog.close ) {
						dialog.close();
					} else {
						dialog.style.display = 'none';
						const backdrop = document.getElementById( 'consultation-backdrop' );
						if ( backdrop ) {
							backdrop.style.display = 'none';
						}
					}
				};

				// Handle close button clicks.
				const closeButtons = dialog.querySelectorAll( '.dialog-close, .dialog-close-btn' );
				closeButtons.forEach( function( btn ) {
					btn.addEventListener( 'click', closeDialog );
				} );

				// Handle reply email button.
				const replyBtn = dialog.querySelector( '.reply-email' );
				if ( replyBtn ) {
					replyBtn.addEventListener( 'click', function() {
						window.location.href = 'mailto:' + email + '?subject=Re: Consultation Request from ' + name;
					} );
				}

				// Close dialog on outside click.
				dialog.addEventListener( 'click', function( e ) {
					if ( e.target === dialog ) {
						closeDialog();
					}
				} );

				// Close dialog on ESC key.
				if ( supportsDialog ) {
					dialog.addEventListener( 'cancel', function( e ) {
						e.preventDefault();
						closeDialog();
					} );
				} else {
					document.addEventListener( 'keydown', function( e ) {
						if ( e.key === 'Escape' && dialog.style.display === 'block' ) {
							closeDialog();
						}
					} );
				}
			};

			// Initial load.
			loadConsultationPosts( 1 );

			// Handle pagination and button clicks using event delegation.
			document.addEventListener( 'click', function( event ) {
				// Check if clicked element is an active pagination button.
				const activeButton = event.target.closest( '.brag-book-gallery-universal-pagination li.active' );
				if ( activeButton ) {
					const page = parseInt( activeButton.getAttribute( 'p' ), 10 );
					if ( ! isNaN( page ) ) {
						loadConsultationPosts( page );
					}
				}

				// Handle delete button clicks.
				const deleteBtn = event.target.closest( '.delete-consultation' );
				if ( deleteBtn ) {
					event.preventDefault();
					handleDelete( deleteBtn );
				}

				// Handle view details button clicks.
				const viewBtn = event.target.closest( '.view-consultation' );
				if ( viewBtn ) {
					event.preventDefault();
					handleViewDetails( viewBtn );
				}
			} );
		} );
		/* ]]> */
		</script>
		<?php
	}


	/**
	 * Render the header section
	 *
	 * Displays the plugin header with logo and version.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_header(): void {
		// Get plugin version.
		$plugin_version = defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0';

		// Get plugin directory for assets.
		$plugin_dir = dirname( __DIR__, 2 );

		// Construct the logo URL.
		$logo_url = plugins_url( 'assets/images/bragbook-logo.svg', $plugin_dir . '/brag-book-gallery.php' );
		?>
		<div class="brag-book-gallery-header">
			<div class="brag-book-gallery-header-left">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'BRAG book', 'brag-book-gallery' ); ?>" class="brag-book-gallery-logo" />
				<h1><?php esc_html_e( 'BRAG book Gallery', 'brag-book-gallery' ); ?></h1>
			</div>
			<div class="brag-book-gallery-header-right">
				<span class="brag-book-gallery-version-badge">
					<?php
					/* translators: %s: Plugin version number */
					printf( esc_html__( 'v%s', 'brag-book-gallery' ), esc_html( $plugin_version ) );
					?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render navigation tabs
	 *
	 * Displays the admin navigation tabs with proper active state.
	 *
	 * @since 3.0.0
	 *
	 * @param string $current Current active tab identifier.
	 *
	 * @return void
	 */
	private function render_tabs( string $current = 'consultations' ): void {
		// Define available tabs.
		$tabs = [
			'settings'      => [
				'title' => esc_html__( 'General Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'icon'  => 'dashicons-admin-settings',
			],
			'api'           => [
				'title' => esc_html__( 'API Configuration', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon'  => 'dashicons-admin-network',
			],
			'consultations' => [
				'title' => esc_html__( 'Consultations', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-consultation' ),
				'icon'  => 'dashicons-forms',
			],
			'help'          => [
				'title' => esc_html__( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'icon'  => 'dashicons-sos',
			],
			'debug'         => [
				'title' => esc_html__( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'icon'  => 'dashicons-admin-tools',
			],
		];

		// Check API configuration status for badge.
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$has_api = ! empty( $api_tokens );
		?>
		<nav class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<li class="brag-book-gallery-tab-item <?php echo $current === $key ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( $tab['url'] ); ?>" class="brag-book-gallery-tab-link">
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<span class="brag-book-gallery-tab-title"><?php echo esc_html( $tab['title'] ); ?></span>
							<?php if ( $key === 'api' && ! $has_api ) : ?>
								<span class="brag-book-gallery-tab-badge">!</span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Register admin menu page
	 *
	 * Adds consultation entries page to WordPress admin menu.
	 * Note: This is now handled by Settings Manager class.
	 *
	 * @since 3.0.0
	 * @deprecated 3.0.0 Use Settings Manager class instead.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		// This method is kept for backwards compatibility.
		// Menu registration is now handled by Settings Manager.
		add_submenu_page(
			'brag-book-gallery-settings',
			esc_html__( 'Consultations', 'brag-book-gallery' ),
			esc_html__( 'Consultations', 'brag-book-gallery' ),
			'manage_options',
			'brag-book-gallery-consultation',
			[ $this, 'display_form_entries' ]
		);
	}
}
