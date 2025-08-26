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
use BRAGBookGallery\Includes\Traits\Trait_Sanitizer;
use BRAGBookGallery\Includes\Traits\Trait_Tools;
use WP_Error;
use WP_Query;

/**
 * Consultation Class
 *
 * Handles consultation form submissions and management.
 * Follows WordPress VIP coding standards for security and performance.
 *
 * @since 3.0.0
 */
class Consultation {
	use Trait_Api;
	use Trait_Sanitizer;
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
	 * Constructor - Registers WordPress hooks
	 *
	 * Sets up admin menu items and AJAX handlers for both authenticated
	 * and non-authenticated users. Follows WordPress best practices for
	 * hook registration.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __construct() {
		// Register AJAX handlers for pagination.
		add_action( 'wp_ajax_' . self::AJAX_PAGINATION, array( $this, 'handle_pagination_request' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_PAGINATION, array( $this, 'handle_pagination_request' ) );

		// Form submission AJAX handlers.
		add_action( 'wp_ajax_' . self::AJAX_SUBMISSION, array( $this, 'handle_form_submission' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_SUBMISSION, array( $this, 'handle_form_submission' ) );

		// Delete entry AJAX handler (admin only).
		add_action( 'wp_ajax_' . self::AJAX_DELETE, array( $this, 'handle_delete_entry' ) );
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
			array(
				'body'    => $json_data,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'Content-Length' => (string) strlen( $json_data ),
				),
				'timeout' => 30,
			)
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
		$post_data = array(
			'post_title'   => sanitize_text_field( $name ),
			'post_content' => sanitize_textarea_field( $description ),
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'publish',
			'meta_input'   => array(
				'bb_email' => sanitize_email( $email ),
				'bb_phone' => sanitize_text_field( $phone ),
			),
		);

		// Insert post using WordPress API.
		$post_id = wp_insert_post( $post_data, true );

		// Return post ID or false on failure.
		return is_wp_error( $post_id ) ? false : $post_id;
	}

	/**
	 * Handle form submission via AJAX
	 *
	 * Processes consultation form submissions, validates input, determines
	 * the appropriate API endpoint based on gallery configuration, and
	 * sends data to external API. Follows VIP standards for security.
	 *
	 * @since 3.0.0
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_form_submission(): void {
		// Verify nonce for security (VIP requirement).
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error(
				esc_html__( 'Security token missing.', 'brag-book-gallery' )
			);
			return;
		}

		// Try to verify with consultation_form_nonce first, then fall back to general nonce.
		$nonce_valid = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'consultation_form_nonce' );

		// If consultation nonce fails, try the general gallery nonce.
		if ( ! $nonce_valid ) {
			$nonce_valid = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_nonce' );
		}

		if ( ! $nonce_valid ) {
			wp_send_json_error(
				esc_html__( 'Security verification failed.', 'brag-book-gallery' )
			);
			return;
		}

		// Validate request method.
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error(
				esc_html__( 'Invalid request method.', 'brag-book-gallery' )
			);
			return;
		}

		// Validate and sanitize form inputs.
		$form_data = $this->validate_form_data();

		if ( is_wp_error( $form_data ) ) {
			wp_send_json_error( esc_html( $form_data->get_error_message() ) );
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
			// Log API attempt (use error_log sparingly in production).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API configuration found, attempting to send consultation to BRAG book API' );
			}

			// Determine gallery context from referrer.
			$referrer        = wp_get_referer() ?: '';
			$gallery_context = $this->determine_gallery_context( $referrer, $config );

			// Prepare submission data.
			$submission_data = array(
				'name'    => $form_data['name'],
				'email'   => $form_data['email'],
				'phone'   => $form_data['phone'],
				'details' => $form_data['description'],
			);

			// Try to send to API (but don't fail if it doesn't work).
			try {
				if ( $gallery_context['is_combined'] ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Sending to combined gallery API' );
					}
					$this->send_to_combined_gallery_api( $submission_data, $config );
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Sending to single gallery API (index: ' . $gallery_context['index'] . ')' );
					}
					$this->send_to_single_gallery_api( $submission_data, $config, $gallery_context['index'] );
				}
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Consultation successfully sent to BRAG book API' );
				}
			} catch ( \Exception $e ) {
				// Log API error but don't fail the submission.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAG book API submission failed: ' . $e->getMessage() );
				}
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'No API configuration found, skipping API submission' );
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
		// Define required fields.
		$required_fields = array( 'name', 'email', 'phone', 'description' );

		// Check for missing required fields.
		foreach ( $required_fields as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: Field name that is missing */
						esc_html__( 'Required field missing: %s', 'brag-book-gallery' ),
						esc_html( $field )
					)
				);
			}
		}

		// Sanitize and validate email.
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		
		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				esc_html__( 'Please provide a valid email address.', 'brag-book-gallery' )
			);
		}

		// Return sanitized form data.
		return array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'       => $email,
			'phone'       => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);
	}

	/**
	 * Get API configuration
	 *
	 * Retrieves API tokens and related configuration from WordPress options.
	 * Uses get_option which is cached by WordPress core.
	 *
	 * @since 3.0.0
	 *
	 * @return array|WP_Error Configuration array or error.
	 */
	private function get_api_configuration(): array|WP_Error {
		// Get API tokens from options (cached by WP).
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		// Debug logging only in development.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Getting API configuration:' );
			error_log( 'API tokens retrieved: ' . print_r( $api_tokens, true ) );
			error_log( 'Website property IDs retrieved: ' . print_r( $website_property_ids, true ) );
		}

		// Validate configuration exists.
		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Configuration check failed - tokens empty: ' . ( empty( $api_tokens ) ? 'yes' : 'no' ) );
				error_log( 'Configuration check failed - property IDs empty: ' . ( empty( $website_property_ids ) ? 'yes' : 'no' ) );
			}

			return new WP_Error(
				'missing_config',
				esc_html__( 'API configuration not available.', 'brag-book-gallery' )
			);
		}

		// Return configuration array.
		return array(
			'api_tokens'                  => (array) $api_tokens,
			'website_property_ids'        => (array) $website_property_ids,
			'gallery_pages'               => (array) get_option( 'bb_gallery_stored_pages', array() ),
			'brag_book_gallery_page_slug' => (string) get_option( 'brag_book_gallery_page_slug', '' ),
		);
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
			return array(
				'is_combined' => false,
				'index'       => 0,
			);
		}

		// Parse URL safely.
		$parsed_url = wp_parse_url( $referrer );
		$path       = isset( $parsed_url['path'] ) ? trim( $parsed_url['path'], '/' ) : '';
		$parts      = explode( '/', $path );
		$first_segment = isset( $parts[0] ) ? $parts[0] : '';

		// Check if it's a combined gallery.
		if ( $first_segment === $config['brag_book_gallery_page_slug'] ) {
			return array( 'is_combined' => true, 'index' => null );
		}

		// Find the gallery index.
		$index = array_search( $first_segment, $config['gallery_pages'], true );

		return array(
			'is_combined' => false,
			'index'       => false !== $index ? $index : 0,
		);
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
		$body_data = array(
			'email'   => $data['email'],
			'phone'   => $data['phone'],
			'name'    => $data['name'],
			'details' => $data['details'],
		);

		// Encode as JSON.
		$json_data = wp_json_encode( $body_data );

		if ( false === $json_data ) {
			throw new \Exception( 'Failed to encode form data.' );
		}

		// Log the API request for debugging (only in debug mode).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Sending consultation to API: ' . $url );
			error_log( 'Request body: ' . $json_data );
		}

		// Make API request using wp_remote_post (VIP approved).
		$response = wp_remote_post(
			$url,
			array(
				'body'    => $json_data,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'Content-Length' => (string) strlen( $json_data ),
				),
				'timeout' => 30,
			)
		);

		// Handle WP_Error responses.
		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API request failed: ' . $response->get_error_message() );
			}
			throw new \Exception( 'API Error: ' . $response->get_error_message() );
		}

		// Get response code and body.
		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'API response code: ' . $response_code );
			error_log( 'API response body: ' . $body );
		}

		// Check for successful HTTP status.
		if ( $response_code < 200 || $response_code >= 300 ) {
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
			array(
				'message'    => $table_html,
				'pagination' => $pagination_html,
				'total'      => $total_count,
			)
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
				array(
					'message' => esc_html__( 'Entry deleted successfully.', 'brag-book-gallery' ),
					'post_id' => $post_id,
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'Failed to delete entry.', 'brag-book-gallery' ) );
		}
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
		$cache_key = 'consultation_entries_page_' . $page;
		
		// Try to get from cache first.
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached;
		}

		// Calculate offset based on page number.
		$offset = ( $page - 1 ) * self::ITEMS_PER_PAGE;

		// Query for consultation entries.
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::ITEMS_PER_PAGE,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => false, // Enable pagination info.
			)
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
			$email = get_post_meta( $post->ID, 'bb_email', true );
			$phone = get_post_meta( $post->ID, 'bb_phone', true );
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

		$html = '<div class="bb-universal-pagination"><ul>';

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
	 * Display form entries admin page
	 *
	 * Renders the consultation entries table with pagination.
	 * Includes inline JavaScript for AJAX functionality (consider moving
	 * to external file for VIP compliance in production).
	 *
	 * @since 3.0.0
	 *
	 * @return void Outputs HTML.
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
				<?php $this->render_admin_styles(); ?>

				<div class="bb_pag_loading" style="display: none;">
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
					<tbody class="bb_universal_container">
					<tr>
						<td colspan="6" style="text-align: center;">
							<?php esc_html_e( 'Loading consultation entries...', 'brag-book-gallery' ); ?>
						</td>
					</tr>
					</tbody>
				</table>
				<div class="bb-pagination-nav" style="margin-top: 20px;"></div>
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
				const loadingDiv = document.querySelector( '.bb_pag_loading' );
				const container = document.querySelector( '.bb_universal_container' );
				const paginationNav = document.querySelector( '.bb-pagination-nav' );

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
							const tbody = document.querySelector( '.bb_universal_container' );
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
				const activeButton = event.target.closest( '.bb-universal-pagination li.active' );
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
	 * Render admin styles
	 *
	 * Outputs CSS for admin interface.
	 * Consider moving to external file for VIP compliance.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function render_admin_styles(): void {
		?>
		<style type="text/css">
			.consultation-row:hover { background-color: #f0f8ff; }
			.consultation-actions { white-space: nowrap; text-align: center; }
			.consultation-actions .button { margin: 0 2px; padding: 4px 8px; min-width: auto; display: inline-flex; align-items: center; gap: 4px; }
			.consultation-actions .button-small { font-size: 12px; }
			.consultation-actions .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 1; }
			.consultation-description { max-width: 400px; }
			.description-content, .description-full { word-wrap: break-word; line-height: 1.6; color: #555; }
			.consultation-email a, .consultation-phone a { text-decoration: none; color: #0073aa; font-weight: 500; }
			.consultation-email a:hover, .consultation-phone a:hover { text-decoration: underline; }
			.dashicons.spinning { animation: spin 1s linear infinite; }
			@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
			.wp-list-table.consultation-entries th { font-weight: 600; background: #f1f1f1; color: #333; }
			.consultation-name strong { color: #23282d; font-size: 14px; }
			.consultation-date { white-space: nowrap; color: #666; }
			.view-consultation, .delete-consultation { cursor: pointer; transition: all 0.3s ease; }
			.delete-consultation:hover { color: #dc3232 !important; border-color: #dc3232 !important; background: #fff !important; }
			.view-consultation:hover { color: #0073aa !important; border-color: #0073aa !important; background: #fff !important; }
			.bb_pag_loading { text-align: center; padding: 20px; }
			.bb-pagination-nav { margin-top: 20px; text-align: center; }
			.consultation-entries tbody td { padding: 12px 8px; vertical-align: middle; }
			
			/* Dialog Styles */
			.consultation-dialog { border: none; border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); padding: 0; max-width: 600px; width: 90%; max-height: 80vh; overflow: visible; }
			.consultation-dialog::backdrop { background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(2px); }
			.consultation-dialog-content { display: flex; flex-direction: column; height: 100%; }
			.consultation-dialog-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e5e5e5; background: #f8f9fa; border-radius: 8px 8px 0 0; }
			.consultation-dialog-header h2 { margin: 0; font-size: 20px; font-weight: 600; color: #23282d; }
			.dialog-close { background: none; border: none; cursor: pointer; padding: 5px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; }
			.dialog-close:hover { background: rgba(0, 0, 0, 0.05); }
			.dialog-close .dashicons { font-size: 24px; width: 24px; height: 24px; color: #666; }
			.consultation-dialog-body { padding: 20px; overflow-y: auto; flex-grow: 1; }
			.detail-row { display: flex; margin-bottom: 15px; align-items: flex-start; }
			.detail-row label { font-weight: 600; color: #666; min-width: 120px; margin-right: 15px; padding-top: 2px; }
			.detail-value { flex: 1; color: #23282d; line-height: 1.6; }
			.detail-value a { color: #0073aa; text-decoration: none; }
			.detail-value a:hover { text-decoration: underline; }
			.message-content { background: #f5f5f5; padding: 12px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; max-height: 200px; overflow-y: auto; }
			.consultation-dialog-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #e5e5e5; background: #f8f9fa; border-radius: 0 0 8px 8px; }
			.consultation-dialog-footer .button { margin: 0; }
			.reply-email { display: flex; align-items: center; gap: 5px; }
			.reply-email .dashicons { font-size: 16px; width: 16px; height: 16px; }
		</style>
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
		$tabs = array(
			'settings'      => array(
				'title' => esc_html__( 'General Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'api'           => array(
				'title' => esc_html__( 'API Configuration', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon'  => 'dashicons-admin-network',
			),
			'consultations' => array(
				'title' => esc_html__( 'Consultations', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-consultation' ),
				'icon'  => 'dashicons-forms',
			),
			'help'          => array(
				'title' => esc_html__( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'icon'  => 'dashicons-sos',
			),
			'debug'         => array(
				'title' => esc_html__( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'icon'  => 'dashicons-admin-tools',
			),
		);

		// Check API configuration status for badge.
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
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
			array( $this, 'display_form_entries' )
		);
	}
}