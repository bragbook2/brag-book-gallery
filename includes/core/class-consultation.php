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
	 * Constructor - Registers WordPress hooks
	 *
	 * Sets up admin menu items and AJAX handlers for both authenticated
	 * and non-authenticated users.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {

		// Register admin menu.
		// Menu registration is now handled by Settings Manager
		// add_action(	'admin_menu', array( $this, 'register_admin_menu' ) );

		// Register AJAX handlers.
		add_action( 'wp_ajax_' . self::AJAX_PAGINATION, array(
			$this,
			'handle_pagination_request'
		) );

		// Handle non-authenticated AJAX requests.
		add_action( 'wp_ajax_nopriv_' . self::AJAX_PAGINATION, array(
			$this,
			'handle_pagination_request'
		) );

		// Form submission AJAX handlers.
		add_action( 'wp_ajax_' . self::AJAX_SUBMISSION, array(
			$this,
			'handle_form_submission'
		) );

		// Handle non-authenticated form submissions.
		add_action( 'wp_ajax_nopriv_' . self::AJAX_SUBMISSION, array(
			$this,
			'handle_form_submission'
		) );

		// Delete entry AJAX handler (admin only)
		add_action( 'wp_ajax_' . self::AJAX_DELETE, array(
			$this,
			'handle_delete_entry'
		) );
	}

	/**
	 * Send form data to API and create post
	 *
	 * Sends consultation data to external API and creates a local post
	 * record if the API submission is successful.
	 *
	 * @param array $data Form data to send.
	 * @param string $url API endpoint URL.
	 * @param string $name Customer name.
	 * @param string $description Consultation description.
	 * @param string $email Customer email.
	 * @param string $phone Customer phone number.
	 *
	 * @return void Outputs JSON response and exits.
	 * @since 3.0.0
	 *
	 */
	private function send_form_data_and_create_post(
		array $data,
		string $url,
		string $name,
		string $description,
		string $email,
		string $phone
	): void {
		$json_data = wp_json_encode( $data );

		if ( false === $json_data ) {
			wp_send_json_error(
				esc_html__(
					'Failed to encode form data.',
					'brag-book-gallery'
				)
			);

			return;
		}

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

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				sprintf(
				/* translators: %s: Error message */
					esc_html__( 'API Error: %s', 'brag-book-gallery' ),
					$response->get_error_message()
				)
			);

			return;
		}

		$body          = wp_remote_retrieve_body( $response );
		$response_data = json_decode( json: $body, associative: true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error(
				esc_html__(
					'Invalid API response format.',
					'brag-book-gallery'
				)
			);

			return;
		}

		if ( isset( $response_data['success'] ) && true === $response_data['success'] ) {
			$post_id = $this->create_consultation_post(
				$name,
				$description,
				$email,
				$phone
			);

			if ( $post_id ) {
				wp_send_json_success(
					esc_html__(
						'Thank you for your consultation request!',
						'brag-book-gallery'
					)
				);
			} else {
				wp_send_json_error(
					esc_html__(
						'Failed to save consultation locally.',
						'brag-book-gallery'
					)
				);
			}
		} else {
			wp_send_json_error(
				esc_html__(
					'Consultation submission was not successful.',
					'brag-book-gallery'
				)
			);
		}
	}

	/**
	 * Create consultation post
	 *
	 * Creates a WordPress post to store consultation information locally.
	 *
	 * @param string $name Customer name.
	 * @param string $description Consultation description.
	 * @param string $email Customer email.
	 * @param string $phone Customer phone number.
	 *
	 * @return int|false Post ID on success, false on failure.
	 * @since 3.0.0
	 *
	 */
	private function create_consultation_post(
		string $name,
		string $description,
		string $email,
		string $phone
	): int|false {

		// Prepare post data.
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

		// Insert the post into the database.
		$post_id = wp_insert_post(
			$post_data,
			true
		);

		// Return post ID or false on failure.
		return is_wp_error( $post_id ) ? false : $post_id;
	}

	/**
	 * Handle form submission via AJAX
	 *
	 * Processes consultation form submissions, validates input, determines
	 * the appropriate API endpoint based on gallery configuration, and
	 * sends data to external API.
	 *
	 * @return void Outputs JSON response and exits.
	 * @since 3.0.0
	 */
	public function handle_form_submission(): void {

		// Verify nonce for security - try both possible nonce actions
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error(
				esc_html__(
					'Security token missing.',
					'brag-book-gallery'
				)
			);

			return;
		}

		// Try to verify with consultation_form_nonce first, then fall back to general nonce
		$nonce_valid = wp_verify_nonce( $_POST['nonce'], 'consultation_form_nonce' );

		// If consultation nonce fails, try the general gallery nonce
		if ( ! $nonce_valid ) {
			$nonce_valid = wp_verify_nonce( $_POST['nonce'], 'brag_book_gallery_nonce' );
		}

		if ( ! $nonce_valid ) {
			wp_send_json_error(
				esc_html__(
					'Security verification failed.',
					'brag-book-gallery'
				)
			);

			return;
		}

		// Validate request method and required fields.
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error(
				esc_html__(
					'Invalid request method.',
					'brag-book-gallery'
				)
			);
		}

		// Validate and sanitize form inputs.
		$form_data = $this->validate_form_data();

		if ( is_wp_error( $form_data ) ) {
			wp_send_json_error( $form_data->get_error_message() );

			return;
		}

		// First, save the consultation locally
		$post_id = $this->create_consultation_post(
			$form_data['name'],
			$form_data['description'],
			$form_data['email'],
			$form_data['phone']
		);

		if ( ! $post_id ) {
			wp_send_json_error(
				esc_html__(
					'Failed to save consultation request. Please try again.',
					'brag-book-gallery'
				)
			);

			return;
		}

		// Try to get API configuration (optional)
		$config = $this->get_api_configuration();

		// If API is configured, attempt to send to external API
		if ( ! is_wp_error( $config ) ) {
			error_log( 'API configuration found, attempting to send consultation to BRAG book API' );

			// Determine gallery context from referrer.
			$gallery_context = $this->determine_gallery_context(
				wp_get_referer() ?: '',
				$config
			);

			// Prepare submission data.
			$submission_data = [
				'name'    => $form_data['name'],
				'email'   => $form_data['email'],
				'phone'   => $form_data['phone'],
				'details' => $form_data['description'],
			];

			// Try to send to API (but don't fail if it doesn't work)
			try {
				if ( $gallery_context['is_combined'] ) {
					error_log( 'Sending to combined gallery API' );
					$this->send_to_combined_gallery_api(
						$submission_data,
						$config
					);
				} else {
					error_log( 'Sending to single gallery API (index: ' . $gallery_context['index'] . ')' );
					$this->send_to_single_gallery_api(
						$submission_data,
						$config,
						$gallery_context['index']
					);
				}
				error_log( 'Consultation successfully sent to BRAG book API' );
			} catch ( \Exception $e ) {
				// Log API error but don't fail the submission
				error_log( 'BRAG book API submission failed: ' . $e->getMessage() );
			}
		} else {
			error_log( 'No API configuration found, skipping API submission' );
		}

		// Return success since we saved locally
		wp_send_json_success(
			esc_html__(
				'Thank you for your consultation request! We will contact you soon.',
				'brag-book-gallery'
			)
		);
	}

	/**
	 * Validate form data
	 *
	 * Validates and sanitizes all form inputs.
	 *
	 * @return array|WP_Error Sanitized form data or error.
	 * @since 3.0.0
	 */
	private function validate_form_data(): array|WP_Error {

		$required_fields = array(
			'name',
			'email',
			'phone',
			'description'
		);

		foreach ( $required_fields as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
					/* translators: %s: Field name */
						esc_html__(
							'Required field missing: %s',
							'brag-book-gallery'
						),
						$field
					)
				);
			}
		}

		$email = sanitize_email( $_POST['email'] );
		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				esc_html__(
					'Please provide a valid email address.',
					'brag-book-gallery'
				)
			);
		}

		return array(
			'name'        => sanitize_text_field( $_POST['name'] ),
			'email'       => $email,
			'phone'       => sanitize_text_field( $_POST['phone'] ),
			'description' => sanitize_textarea_field( $_POST['description'] ),
		);
	}

	/**
	 * Get API configuration
	 *
	 * Retrieves API tokens and related configuration from WordPress options.
	 *
	 * @return array|WP_Error Configuration array or error.
	 * @since 3.0.0
	 */
	private function get_api_configuration(): array|WP_Error {

		$api_tokens = get_option(
			option: 'brag_book_gallery_api_token',
			default_value: array()
		);

		$website_property_ids = get_option(
			option: 'brag_book_gallery_website_property_id',
			default_value: array()
		);

		// Debug logging
		error_log( 'Getting API configuration:' );
		error_log( 'Option name for tokens: brag_book_gallery_api_token' );
		error_log( 'Option name for property IDs: brag_book_gallery_website_property_id' );
		error_log( 'API tokens retrieved: ' . print_r( $api_tokens, true ) );
		error_log( 'Website property IDs retrieved: ' . print_r( $website_property_ids, true ) );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			error_log( 'Configuration check failed - tokens empty: ' . ( empty( $api_tokens ) ? 'yes' : 'no' ) );
			error_log( 'Configuration check failed - property IDs empty: ' . ( empty( $website_property_ids ) ? 'yes' : 'no' ) );

			return new WP_Error(
				'missing_config',
				esc_html__(
					'API configuration not available.',
					'brag-book-gallery'
				)
			);
		}

		return array(
			'api_tokens'                  => (array) $api_tokens,
			'website_property_ids'        => (array) $website_property_ids,
			'gallery_pages'               => (array) get_option(
				option: 'bb_gallery_stored_pages',
				default_value: array()
			),
			'brag_book_gallery_page_slug' => (string) get_option(
				option: 'brag_book_gallery_page_slug',
				default_value: ''
			),
		);
	}

	/**
	 * Determine gallery context from URL
	 *
	 * Analyzes the referrer URL to determine which gallery the form was
	 * submitted from.
	 *
	 * @param string $referrer Referrer URL.
	 * @param array $config API configuration.
	 *
	 * @return array Gallery context information.
	 * @since 3.0.0
	 *
	 */
	private function determine_gallery_context(
		string $referrer,
		array $config
	): array {

		if ( empty( $referrer ) ) {
			return array(
				'is_combined' => false,
				'index'       => 0
			);
		}

		$parsed_url    = parse_url( $referrer );
		$path          = trim( $parsed_url['path'] ?? '', '/' );
		$parts         = explode( '/', $path );
		$first_segment = $parts[0] ?? '';

		// Check if it's a combined gallery
		if ( $first_segment === $config['brag_book_gallery_page_slug'] ) {
			return [ 'is_combined' => true, 'index' => null ];
		}

		// Find the gallery index
		$index = array_search(
			$first_segment,
			$config['gallery_pages'],
			true
		);

		return array(
			'is_combined' => false,
			'index'       => false !== $index ? $index : 0,
		);
	}

	/**
	 * Process combined gallery submission
	 *
	 * Sends consultation data to all configured API endpoints for combined galleries.
	 *
	 * @param array $data Submission data.
	 * @param array $config API configuration.
	 * @param array $form_data Form data.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	private function process_combined_gallery_submission(
		array $data,
		array $config,
		array $form_data
	): void {

		// Validate API tokens and website property IDs.
		$base_url = self::get_api_url();

		foreach ( $config['api_tokens'] as $index => $token ) {
			if ( ! isset( $config['website_property_ids'][ $index ] ) ) {
				continue;
			}

			$url = sprintf(
				'%s/api/plugin/consultations?apiToken=%s&websitepropertyId=%s',
				$base_url,
				urlencode( $token ),
				urlencode( $config['website_property_ids'][ $index ] )
			);

			$this->send_form_data_and_create_post(
				$data,
				$url,
				$form_data['name'],
				$form_data['description'],
				$form_data['email'],
				$form_data['phone']
			);
		}
	}

	/**
	 * Process single gallery submission
	 *
	 * Sends consultation data to a specific API endpoint.
	 *
	 * @param array $data Submission data.
	 * @param array $config API configuration.
	 * @param int $index Gallery index.
	 * @param array $form_data Form data.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 */
	private function process_single_gallery_submission(
		array $data,
		array $config,
		int $index,
		array $form_data
	): void {

		if ( ! isset( $config['api_tokens'][ $index ] ) ||
			 ! isset( $config['website_property_ids'][ $index ] ) ) {
			wp_send_json_error(
				esc_html__(
					'Gallery configuration not found.',
					'brag-book-gallery'
				)
			);

			return;
		}

		$base_url = self::get_api_url();

		$url = sprintf(
			'%s/api/plugin/consultations?apiToken=%s&websitepropertyId=%s',
			$base_url,
			urlencode( $config['api_tokens'][ $index ] ),
			urlencode( $config['website_property_ids'][ $index ] )
		);

		$this->send_form_data_and_create_post(
			$data,
			$url,
			$form_data['name'],
			$form_data['description'],
			$form_data['email'],
			$form_data['phone']
		);
	}

	/**
	 * Send consultation data to combined gallery API endpoints
	 *
	 * @param array $data Submission data.
	 * @param array $config API configuration.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function send_to_combined_gallery_api( array $data, array $config ): void {
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
	 * @param array $data Submission data.
	 * @param array $config API configuration.
	 * @param int $index Gallery index.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function send_to_single_gallery_api( array $data, array $config, int $index ): void {
		if ( ! isset( $config['api_tokens'][ $index ] ) ||
			 ! isset( $config['website_property_ids'][ $index ] ) ) {
			throw new \Exception( 'Gallery configuration not found.' );
		}

		$this->send_to_api( $data, $config['api_tokens'][ $index ], $config['website_property_ids'][ $index ] );
	}

	/**
	 * Send data to API endpoint
	 *
	 * @param array $data Data to send (email, phone, name, details).
	 * @param string $api_token API token for authentication.
	 * @param string $website_property_id Website property ID.
	 *
	 * @return void
	 * @throws \Exception If API request fails.
	 * @since 3.0.0
	 */
	private function send_to_api( array $data, string $api_token, string $website_property_id ): void {
		$base_url = self::get_api_url();

		// Build the URL with query parameters
		$url = sprintf(
			'%s/api/plugin/consultations?apiToken=%s&websitepropertyId=%s',
			$base_url,
			urlencode( $api_token ),
			urlencode( $website_property_id )
		);

		// Prepare the body with the form data
		$body_data = array(
			'email'   => $data['email'],
			'phone'   => $data['phone'],
			'name'    => $data['name'],
			'details' => $data['details']
		);

		$json_data = wp_json_encode( $body_data );

		if ( false === $json_data ) {
			throw new \Exception( 'Failed to encode form data.' );
		}

		// Log the API request for debugging
		error_log( 'Sending consultation to API: ' . $url );
		error_log( 'Request body: ' . $json_data );

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

		if ( is_wp_error( $response ) ) {
			error_log( 'API request failed: ' . $response->get_error_message() );
			throw new \Exception( 'API Error: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		error_log( 'API response code: ' . $response_code );
		error_log( 'API response body: ' . $body );

		// Check for successful HTTP status
		if ( $response_code < 200 || $response_code >= 300 ) {
			throw new \Exception( 'API returned status code: ' . $response_code . ' - ' . $body );
		}

		$response_data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( 'Invalid API response format: ' . $body );
		}

		// The API might return success in different ways, so be flexible
		if ( isset( $response_data['error'] ) ||
			 ( isset( $response_data['success'] ) && false === $response_data['success'] ) ) {
			$error_msg = $response_data['message'] ?? $response_data['error'] ?? 'API submission failed';
			throw new \Exception( 'API submission was not successful: ' . $error_msg );
		}
	}

	/**
	 * Handle pagination AJAX request
	 *
	 * Retrieves paginated consultation entries for admin display.
	 * Uses WordPress database API for secure queries.
	 *
	 * @return void Outputs JSON response and exits.
	 * @since 3.0.0
	 */
	public function handle_pagination_request(): void {

		// Verify nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'consultation_pagination_nonce' ) ) {
			wp_send_json_error( __( 'Security verification failed.', 'brag-book-gallery' ) );
		}

		// Validate page parameter.
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// Ensure page is at least 1.
		if ( $page < 1 ) {
			$page = 1;
		}

		// Get consultation entries.
		$entries     = $this->get_consultation_entries( $page );
		$total_count = $this->get_total_consultation_count();

		// Generate table rows HTML.
		$table_html = $this->generate_table_rows( $entries );

		// Generate pagination HTML.
		$pagination_html = $this->generate_pagination_html(
			$page,
			$total_count
		);

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
	 * Deletes a consultation entry via AJAX.
	 *
	 * @return void Outputs JSON response and exits.
	 * @since 3.0.0
	 */
	public function handle_delete_entry(): void {

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to delete entries.', 'brag-book-gallery' ) );
		}

		// Verify nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'consultation_delete_nonce' ) ) {
			wp_send_json_error( __( 'Security verification failed.', 'brag-book-gallery' ) );
		}

		// Get and validate post ID
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Invalid entry ID.', 'brag-book-gallery' ) );
		}

		// Verify it's a consultation entry
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			wp_send_json_error( __( 'Entry not found.', 'brag-book-gallery' ) );
		}

		// Delete the post
		$deleted = wp_delete_post( $post_id, true ); // true = force delete (skip trash)

		if ( $deleted ) {
			wp_send_json_success( array(
				'message' => __( 'Entry deleted successfully.', 'brag-book-gallery' ),
				'post_id' => $post_id
			) );
		} else {
			wp_send_json_error( __( 'Failed to delete entry.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * Get consultation entries for a specific page
	 *
	 * Retrieves consultation posts with pagination.
	 *
	 * @param int $page Page number.
	 *
	 * @return array Array of post objects.
	 * @since 3.0.0
	 *
	 */
	private function get_consultation_entries( int $page ): array {

		// Calculate offset based on page number.
		$offset = ( $page - 1 ) * self::ITEMS_PER_PAGE;

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::ITEMS_PER_PAGE,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $query->posts;
	}

	/**
	 * Get total count of consultation entries
	 *
	 * @return int Total number of consultation posts.
	 * @since 3.0.0
	 */
	private function get_total_consultation_count(): int {

		// Use WordPress function to count posts of the custom post type.
		$count_posts = wp_count_posts( self::POST_TYPE );

		return (int) $count_posts->publish;
	}

	/**
	 * Generate HTML for table rows
	 *
	 * Creates HTML markup for consultation entry table rows.
	 *
	 * @param array $entries Array of post objects.
	 *
	 * @return string HTML markup for table rows.
	 * @since 3.0.0
	 *
	 */
	private function generate_table_rows( array $entries ): string {

		if ( empty( $entries ) ) {
			return sprintf(
				'<tr><td colspan="6" style="text-align: center; padding: 20px;">%s</td></tr>',
				esc_html__( 'No consultation entries found.', 'brag-book-gallery' )
			);
		}

		$html = '';

		foreach ( $entries as $post ) {
			$email = get_post_meta( $post->ID, 'bb_email', true );
			$phone = get_post_meta( $post->ID, 'bb_phone', true );
			$date  = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post->post_date );

			// Truncate long descriptions for better display
			$description      = $post->post_content;
			$full_description = $description;
			if ( strlen( $description ) > 150 ) {
				$description = substr( $description, 0, 150 ) . '...';
			}

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
							<span class="dashicons dashicons-visibility"></span> View
						</button>
						<button class="button button-small button-secondary delete-consultation" data-id="%d" data-name="%s" title="%s">
							<span class="dashicons dashicons-trash"></span> Delete
						</button>
					</td>
				</tr>',
				$post->ID,
				esc_html( $post->post_title ),
				esc_attr( $email ),
				esc_html( $email ),
				esc_attr( $phone ),
				esc_html( $phone ),
				esc_html( $date ),
				wp_kses_post( $description ),
				( strlen( $full_description ) > 150 ?
					'<div class="description-full" style="display:none;">' . wp_kses_post( $full_description ) . '</div>' :
					''
				),
				$post->ID,
				esc_attr__( 'View Details', 'brag-book-gallery' ),
				$post->ID,
				esc_attr( $post->post_title ),
				esc_attr__( 'Delete Entry', 'brag-book-gallery' )
			);
		}

		return $html;
	}

	/**
	 * Generate pagination HTML
	 *
	 * Creates pagination controls for navigation.
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_count Total number of items.
	 *
	 * @return string HTML markup for pagination.
	 * @since 3.0.0
	 *
	 */
	private function generate_pagination_html( int $current_page, int $total_count ): string {

		// Calculate total pages.
		$total_pages = (int) ceil( $total_count / self::ITEMS_PER_PAGE );

		// No pagination needed if only one page.
		if ( $total_pages <= 1 ) {
			return '';
		}

		$html = '<div class="bb-universal-pagination"><ul>';

		// Items count.
		$html .= sprintf(
			'<li class="selected">%s</li>',
			sprintf(
			/* translators: %d: Number of items */
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
		$html .= $this->generate_pagination_button(
			label: '<<',
			page: 1,
			is_active: $current_page > 1
		);

		// Previous page button
		if ( $current_page > 1 ) {
			$html .= $this->generate_pagination_button(
				label: '<',
				page: $current_page - 1,
				is_active: true
			);
		} else {
			$html .= '<li class="inactive"><</li>';
		}

		// Current page indicator
		$html .= sprintf(
			'<li class="selected">%s</li>',
			sprintf(
			/* translators: 1: Current page, 2: Total pages */
				esc_html__(
					'%1$d of %2$d',
					'brag-book-gallery'
				),
				$current_page,
				$total_pages
			)
		);

		// Next page button
		if ( $current_page < $total_pages ) {
			$html .= $this->generate_pagination_button(
				label: '>',
				page: $current_page + 1,
				is_active: true
			);
		} else {
			$html .= '<li class="inactive">></li>';
		}

		// Last page button
		$html .= $this->generate_pagination_button(
			label: '>>',
			page: $total_pages,
			is_active: $current_page < $total_pages
		);

		$html .= '</ul></div>';

		return $html;
	}

	/**
	 * Generate a pagination button
	 *
	 * @param string $label Button label.
	 * @param int $page Page number.
	 * @param bool $is_active Whether button is active.
	 *
	 * @return string HTML for button.
	 * @since 3.0.0
	 *
	 */
	private function generate_pagination_button( string $label, int $page, bool $is_active ): string {

		// If the button is not active, return inactive HTML.
		$class = $is_active ? 'active' : 'inactive';

		// If the button is active, add a data attribute for the page number.
		$page_attr = $is_active ? sprintf( ' p="%d"', $page ) : '';

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
	 * Includes inline JavaScript for AJAX functionality.
	 *
	 * @return void Outputs HTML.
	 * @since 3.0.0
	 */
	public function display_form_entries(): void {

		// Generate nonce for security.
		$nonce        = wp_create_nonce( 'consultation_pagination_nonce' );
		$delete_nonce = wp_create_nonce( 'consultation_delete_nonce' );
		?>
		<div class="wrap brag-book-gallery-admin-wrap">
			<?php $this->render_header(); ?>
			<?php $this->render_tabs( 'consultations' ); ?>

			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Consultation Entries', 'brag-book-gallery' ); ?></h2>
				<script>
					document.addEventListener( 'DOMContentLoaded', () => {
						const ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
						const nonce = <?php echo wp_json_encode( $nonce ); ?>;
						const deleteNonce = <?php echo wp_json_encode( $delete_nonce ); ?>;

						/**
						 * Fade in effect for element
						 * @param {HTMLElement} element Element to fade in
						 * @param {number} duration Duration in milliseconds
						 */
						const fadeIn = ( element, duration = 400 ) => {
							element.style.opacity = '0';
							element.style.display = 'block';
							element.style.transition = `opacity ${duration}ms`;

							// Force reflow
							element.offsetHeight;

							element.style.opacity = '1';
						};

						/**
						 * Load consultation posts via AJAX
						 * @param {number} page Page number to load
						 */
						const loadConsultationPosts = async ( page ) => {
							const loadingDiv = document.querySelector( '.bb_pag_loading' );
							const container = document.querySelector( '.bb_universal_container' );
							const paginationNav = document.querySelector( '.bb-pagination-nav' );

							// Show loading state
							if ( loadingDiv ) {
								loadingDiv.style.display = 'block';
							}

							// Show loading message in table
							if ( container ) {
								container.innerHTML = '<tr><td colspan="5" style="text-align: center;">Loading...</td></tr>';
							}

							// Prepare form data
							const formData = new FormData();
							formData.append( 'page', page );
							formData.append( 'nonce', nonce );
							formData.append( 'action', '<?php echo esc_js( self::AJAX_PAGINATION ); ?>' );

							try {
								// Make AJAX request
								const response = await fetch( ajaxurl, {
									method: 'POST',
									body: formData,
									credentials: 'same-origin'
								} );

								const data = await response.json();

								if ( data.success ) {
									// Update table content
									container.innerHTML = data.data.message;
									paginationNav.innerHTML = data.data.pagination;
								} else {
									// Show error message
									container.innerHTML = `<tr><td colspan="5">${data.data || 'Error loading data'}</td></tr>`;
								}
							} catch ( error ) {
								// Handle fetch error
								console.error( 'Error loading consultation entries:', error );
								container.innerHTML = '<tr><td colspan="5">Failed to load consultation entries.</td></tr>';
							} finally {
								// Hide loading state
								if ( loadingDiv ) {
									loadingDiv.style.display = 'none';
								}
							}
						};

						// Initial load
						loadConsultationPosts( 1 );

						/**
						 * Handle delete button clicks
						 */
						const handleDelete = async ( button ) => {
							const postId = button.dataset.id;
							const postName = button.dataset.name;

							if ( !confirm( `Are you sure you want to delete the consultation from "${postName}"? This action cannot be undone.` ) ) {
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
									// Remove the row with fade out effect
									const row = button.closest( 'tr' );
									row.style.transition = 'opacity 0.3s';
									row.style.opacity = '0';
									setTimeout( () => {
										row.remove();
										// Check if table is empty
										const tbody = document.querySelector( '.bb_universal_container' );
										if ( tbody && tbody.children.length === 0 ) {
											tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No consultation entries found.</td></tr>';
										}
									}, 300 );
								} else {
									alert( data.data || 'Failed to delete entry.' );
									button.disabled = false;
									button.innerHTML = '<span class="dashicons dashicons-trash"></span>';
								}
							} catch ( error ) {
								console.error( 'Delete error:', error );
								alert( 'An error occurred while deleting the entry.' );
								button.disabled = false;
								button.innerHTML = '<span class="dashicons dashicons-trash"></span>';
							}
						};

						/**
						 * Handle view details in modal dialog
						 */
						const handleViewDetails = ( button ) => {
							const row = button.closest( 'tr' );
							const postId = button.dataset.id;

							// Get entry details from the row
							const name = row.querySelector( '.consultation-name strong' ).textContent;
							const email = row.querySelector( '.consultation-email a' ).textContent;
							const phone = row.querySelector( '.consultation-phone a' ).textContent;
							const date = row.querySelector( '.consultation-date' ).textContent;
							const fullDesc = row.querySelector( '.description-full' );
							const contentDiv = row.querySelector( '.description-content' );
							const message = fullDesc ? fullDesc.textContent : contentDiv.textContent;

							// Check if dialog element is supported, otherwise use a div fallback
							const supportsDialog = typeof HTMLDialogElement === 'function';

							// Create or get the dialog
							let dialog = document.getElementById( 'consultation-detail-dialog' );
							if ( !dialog ) {
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

							// Populate dialog content
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

							// Show the dialog
							if ( supportsDialog && dialog.showModal ) {
								dialog.showModal();
							} else {
								// Fallback for browsers that don't support dialog
								dialog.style.display = 'block';
								dialog.style.position = 'fixed';
								dialog.style.top = '50%';
								dialog.style.left = '50%';
								dialog.style.transform = 'translate(-50%, -50%)';
								dialog.style.zIndex = '10000';

								// Create backdrop
								let backdrop = document.getElementById( 'consultation-backdrop' );
								if ( !backdrop ) {
									backdrop = document.createElement( 'div' );
									backdrop.id = 'consultation-backdrop';
									backdrop.style.position = 'fixed';
									backdrop.style.top = '0';
									backdrop.style.left = '0';
									backdrop.style.width = '100%';
									backdrop.style.height = '100%';
									backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
									backdrop.style.backdropFilter = 'blur(2px)';
									backdrop.style.zIndex = '9999';
									document.body.appendChild( backdrop );
								} else {
									backdrop.style.display = 'block';
								}
							}

							// Function to close the dialog
							const closeDialog = () => {
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

							// Handle close button clicks
							const closeButtons = dialog.querySelectorAll( '.dialog-close, .dialog-close-btn' );
							closeButtons.forEach( btn => {
								btn.addEventListener( 'click', closeDialog );
							} );

							// Handle reply email button
							const replyBtn = dialog.querySelector( '.reply-email' );
							if ( replyBtn ) {
								replyBtn.addEventListener( 'click', () => {
									window.location.href = `mailto:${email}?subject=Re: Consultation Request from ${name}`;
								} );
							}

							// Close dialog on outside click
							dialog.addEventListener( 'click', ( e ) => {
								if ( e.target === dialog ) {
									closeDialog();
								}
							} );

							// For backdrop click (fallback mode)
							if ( !supportsDialog ) {
								const backdrop = document.getElementById( 'consultation-backdrop' );
								if ( backdrop ) {
									backdrop.addEventListener( 'click', closeDialog );
								}
							}

							// Close dialog on ESC key
							if ( supportsDialog ) {
								dialog.addEventListener( 'cancel', ( e ) => {
									e.preventDefault();
									closeDialog();
								} );
							} else {
								document.addEventListener( 'keydown', ( e ) => {
									if ( e.key === 'Escape' && dialog.style.display === 'block' ) {
										closeDialog();
									}
								} );
							}
						};

						/**
						 * Escape HTML to prevent XSS
						 */
						const escapeHtml = ( text ) => {
							const map = {
								'&': '&amp;',
								'<': '&lt;',
								'>': '&gt;',
								'"': '&quot;',
								"'": '&#039;'
							};
							return text.replace( /[&<>"']/g, m => map[m] );
						};

						// Handle pagination clicks using event delegation
						document.addEventListener( 'click', ( event ) => {
							// Check if clicked element is an active pagination button
							const activeButton = event.target.closest( '.bb-universal-pagination li.active' );

							if ( activeButton ) {
								const page = parseInt( activeButton.getAttribute( 'p' ), 10 );
								if ( !isNaN( page ) ) {
									loadConsultationPosts( page );
								}
							}

							// Handle delete button clicks
							const deleteBtn = event.target.closest( '.delete-consultation' );
							if ( deleteBtn ) {
								event.preventDefault();
								handleDelete( deleteBtn );
							}

							// Handle view details button clicks
							const viewBtn = event.target.closest( '.view-consultation' );
							if ( viewBtn ) {
								event.preventDefault();
								handleViewDetails( viewBtn );
							}
						} );
					} );
				</script>

				<style>
					.consultation-row:hover {
						background-color: #f0f8ff;
					}

					.consultation-actions {
						white-space: nowrap;
						text-align: center;
					}

					.consultation-actions .button {
						margin: 0 2px;
						padding: 4px 8px;
						min-width: auto;
						display: inline-flex;
						align-items: center;
						gap: 4px;
					}

					.consultation-actions .button-small {
						font-size: 12px;
					}

					.consultation-actions .dashicons {
						font-size: 16px;
						width: 16px;
						height: 16px;
						line-height: 1;
					}

					.consultation-description {
						max-width: 400px;
					}

					.description-content, .description-full {
						word-wrap: break-word;
						line-height: 1.6;
						color: #555;
					}

					.consultation-email a, .consultation-phone a {
						text-decoration: none;
						color: #0073aa;
						font-weight: 500;
					}

					.consultation-email a:hover, .consultation-phone a:hover {
						text-decoration: underline;
					}

					.dashicons.spinning {
						animation: spin 1s linear infinite;
					}

					@keyframes spin {
						from {
							transform: rotate(0deg);
						}
						to {
							transform: rotate(360deg);
						}
					}

					.wp-list-table.consultation-entries th {
						font-weight: 600;
						background: #f1f1f1;
						color: #333;
					}

					.consultation-name strong {
						color: #23282d;
						font-size: 14px;
					}

					.consultation-date {
						white-space: nowrap;
						color: #666;
					}

					.view-consultation, .delete-consultation {
						cursor: pointer;
						transition: all 0.3s ease;
					}

					.delete-consultation:hover {
						color: #dc3232 !important;
						border-color: #dc3232 !important;
						background: #fff !important;
					}

					.view-consultation:hover {
						color: #0073aa !important;
						border-color: #0073aa !important;
						background: #fff !important;
					}

					.bb_pag_loading {
						text-align: center;
						padding: 20px;
					}

					.bb-pagination-nav {
						margin-top: 20px;
						text-align: center;
					}

					.consultation-entries tbody td {
						padding: 12px 8px;
						vertical-align: middle;
					}

					/* Dialog Styles */
					.consultation-dialog {
						border: none;
						border-radius: 8px;
						box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
						padding: 0;
						max-width: 600px;
						width: 90%;
						max-height: 80vh;
						overflow: visible;
					}

					.consultation-dialog::backdrop {
						background: rgba(0, 0, 0, 0.5);
						backdrop-filter: blur(2px);
					}

					.consultation-dialog-content {
						display: flex;
						flex-direction: column;
						height: 100%;
					}

					.consultation-dialog-header {
						display: flex;
						justify-content: space-between;
						align-items: center;
						padding: 20px;
						border-bottom: 1px solid #e5e5e5;
						background: #f8f9fa;
						border-radius: 8px 8px 0 0;
					}

					.consultation-dialog-header h2 {
						margin: 0;
						font-size: 20px;
						font-weight: 600;
						color: #23282d;
					}

					.dialog-close {
						background: none;
						border: none;
						cursor: pointer;
						padding: 5px;
						display: flex;
						align-items: center;
						justify-content: center;
						border-radius: 4px;
						transition: background 0.2s;
					}

					.dialog-close:hover {
						background: rgba(0, 0, 0, 0.05);
					}

					.dialog-close .dashicons {
						font-size: 24px;
						width: 24px;
						height: 24px;
						color: #666;
					}

					.consultation-dialog-body {
						padding: 20px;
						overflow-y: auto;
						flex-grow: 1;
					}

					.detail-row {
						display: flex;
						margin-bottom: 15px;
						align-items: flex-start;
					}

					.detail-row label {
						font-weight: 600;
						color: #666;
						min-width: 120px;
						margin-right: 15px;
						padding-top: 2px;
					}

					.detail-value {
						flex: 1;
						color: #23282d;
						line-height: 1.6;
					}

					.detail-value a {
						color: #0073aa;
						text-decoration: none;
					}

					.detail-value a:hover {
						text-decoration: underline;
					}

					.message-content {
						background: #f5f5f5;
						padding: 12px;
						border-radius: 4px;
						white-space: pre-wrap;
						word-wrap: break-word;
						max-height: 200px;
						overflow-y: auto;
					}

					.consultation-dialog-footer {
						display: flex;
						justify-content: flex-end;
						gap: 10px;
						padding: 20px;
						border-top: 1px solid #e5e5e5;
						background: #f8f9fa;
						border-radius: 0 0 8px 8px;
					}

					.consultation-dialog-footer .button {
						margin: 0;
					}

					.reply-email {
						display: flex;
						align-items: center;
						gap: 5px;
					}

					.reply-email .dashicons {
						font-size: 16px;
						width: 16px;
						height: 16px;
					}
				</style>

				<div class="bb_pag_loading" style="display: none;">
					<p>
						<?php
						esc_html_e(
							'Loading consultation entries...',
							'brag-book-gallery'
						);
						?>
					</p>
				</div>
				<table
					class="wp-list-table widefat fixed striped consultation-entries"
					style="width: 100%;"
				>
					<thead>
					<tr>
						<th style="width: 15%;">
							<?php
							esc_html_e(
								'Name',
								'brag-book-gallery'
							);
							?>
						</th>
						<th style="width: 18%;">
							<?php
							esc_html_e(
								'Email',
								'brag-book-gallery'
							);
							?>
						</th>
						<th style="width: 12%;">
							<?php
							esc_html_e(
								'Phone',
								'brag-book-gallery'
							);
							?>
						</th>
						<th style="width: 12%;">
							<?php
							esc_html_e(
								'Date',
								'brag-book-gallery'
							);
							?>
						</th>
						<th style="width: 33%;">
							<?php esc_html_e(
								'Message',
								'brag-book-gallery'
							);
							?>
						</th>
						<th style="width: 10%; text-align: center;">
							<?php esc_html_e(
								'Actions',
								'brag-book-gallery'
							);
							?>
						</th>
					</tr>
					</thead>
					<tbody class="bb_universal_container">
					<tr>
						<td colspan="6"
							style="text-align: center;">
							<?php
							esc_html_e(
								'Loading consultation entries...',
								'brag-book-gallery'
							);
							?>
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
	 * Render the header section.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function render_header(): void {

		// Get plugin version and logo URL.
		$plugin_version = '3.0.0'; // You can get this from your plugin constants.

		// Get plugin directory and logo URL.
		$plugin_dir = dirname( path: __DIR__, levels: 2 );

		// Construct the logo URL.
		$logo_url = plugins_url(
			path: 'assets/images/bragbook-logo.svg',
			plugin: $plugin_dir . '/brag-book-gallery.php'
		);
		?>
		<div class="brag-book-gallery-header">
			<div class="brag-book-gallery-header-left">
				<img
					src="<?php echo esc_url( $logo_url ); ?>"
					alt="BRAG book"
					class="brag-book-gallery-logo"
				/>
				<h1>
					<?php
					esc_html_e(
						'BRAG book Gallery',
						'brag-book-gallery'
					);
					?>
				</h1>
			</div>
			<div class="brag-book-gallery-header-right">
				<span
					class="brag-book-gallery-version-badge"
				>
					<?php esc_html_e( 'v', 'brag-book-gallery' ); ?>
					<?php echo esc_html( $plugin_version ); ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render navigation tabs.
	 *
	 * @param string $current Current active tab.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function render_tabs( string $current = 'consultations' ): void {

		$tabs = array(
			'settings'      => array(
				'title' => esc_html__(
					'General Settings',
					'brag-book-gallery'
				),
				'url'   => admin_url( path: 'admin.php?page=brag-book-gallery-settings' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'api'           => array(
				'title' => esc_html__(
					'API Configuration',
					'brag-book-gallery'
				),
				'url'   => admin_url( path: 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon'  => 'dashicons-admin-network',
			),
			'consultations' => array(
				'title' => esc_html__(
					'Consultations',
					'brag-book-gallery'
				),
				'url'   => admin_url( path: 'admin.php?page=brag-book-gallery-consultation' ),
				'icon'  => 'dashicons-forms',
			),
			'help'          => array(
				'title' => esc_html__(
					'Help',
					'brag-book-gallery'
				),
				'url'   => admin_url( path: 'admin.php?page=brag-book-gallery-help' ),
				'icon'  => 'dashicons-sos',
			),
			'debug'         => array(
				'title' => esc_html__(
					'Debug',
					'brag-book-gallery'
				),
				'url'   => admin_url( path: 'admin.php?page=brag-book-gallery-debug' ),
				'icon'  => 'dashicons-admin-tools',
			),
		);

		// Check API configuration status
		$api_tokens = get_option(
			option: 'brag_book_gallery_api_token',
			default_value: array()
		);

		// Determine if API configuration is available.
		$has_api = ! empty( $api_tokens );
		?>
		<nav class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<li class="brag-book-gallery-tab-item <?php echo $current === $key ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( $tab['url'] ); ?>"
						   class="brag-book-gallery-tab-link">
							<span
								class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<span
								class="brag-book-gallery-tab-title">
								<?php echo esc_html( $tab['title'] ); ?>
							</span>
							<?php if ( $key === 'api' && ! $has_api ) : ?>
								<span class="brag-book-gallery-tab-badge">
									!
								</span>
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
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			parent_slug: 'brag-book-gallery-settings',
			page_title: esc_html__( 'Consultations', 'brag-book-gallery' ),
			menu_title: esc_html__( 'Consultations', 'brag-book-gallery' ),
			capability: 'manage_options',
			menu_slug: 'brag-book-gallery-consultation',
			callback: array( $this, 'display_form_entries' )
		);
	}
}
