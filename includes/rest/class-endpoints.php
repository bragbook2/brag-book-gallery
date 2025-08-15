<?php
/**
 * REST API Endpoints Class
 *
 * Handles all external API communications with the BragBook service,
 * including data retrieval, filtering, favorites management, and tracking.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\REST
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\REST;

use BRAGBookGallery\Includes\Core\Setup;
use WP_Error;

// Prevent direct access
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Endpoints management class
 *
 * This class is responsible for:
 * - Managing external API communications
 * - Handling data retrieval from BragBook service
 * - Processing filter and pagination requests
 * - Managing favorites functionality
 * - Tracking plugin usage analytics
 *
 * @since 3.0.0
 */
class Endpoints {

	/**
	 * API request timeout in seconds
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const API_TIMEOUT = 30;

	/**
	 * API response cache duration in seconds
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_DURATION = 300; // 5 minutes

	/**
	 * Maximum retry attempts for failed API calls
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * API endpoint paths
	 *
	 * @since 3.0.0
	 * @var array<string, string>
	 */
	private const API_ENDPOINTS = array(
		'filters'        => '/api/plugin/combine/filters',
		'tracker'        => '/api/plugin/tracker',
		'cases'          => '/api/plugin/combine/cases/',
		'case_detail'    => '/api/plugin/combine/cases/%s',
		'favorites_add'  => '/api/plugin/combine/favorites/add',
		'favorites_list' => '/api/plugin/combine/favorites/list',
		'sidebar'        => '/api/plugin/combine/sidebar',
		'views'          => '/api/plugin/views',
		'carousel'       => '/api/plugin/carousel',
	);

	/**
	 * Get filter data from API
	 *
	 * Retrieves available filter options based on provided API tokens,
	 * procedure IDs, and website property IDs.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_tokens           Comma-separated API tokens
	 * @param string $procedure_ids        Comma-separated procedure IDs
	 * @param string $website_property_ids Comma-separated website property IDs
	 *
	 * @return string|null JSON response string on success, null on failure
	 */
	public function bb_get_filter_data(
		string $api_tokens,
		string $procedure_ids,
		string $website_property_ids
	): ?string {
		// Parse and validate input data
		$tokens = $this->parse_comma_separated( $api_tokens );

		// Convert procedure IDs and website property IDs to integers.
		$procedures = array_map(
			'intval',
			$this->parse_comma_separated( $procedure_ids )
		);

		// Convert website property IDs to integers.
		$properties = array_map(
			'intval',
			$this->parse_comma_separated( $website_property_ids )
		);

		// Validate required data.
		if ( empty( $tokens ) || empty( $procedures ) || empty( $properties ) ) {
			$this->send_json_error(
				esc_html__(
					'Missing required parameters for filter data',
					'brag-book-gallery'
				)
			);
			return null;
		}

		// Prepare request body
		$body = array(
			'apiTokens'          => $tokens,
			'procedureIds'       => $procedures,
			'websitePropertyIds' => $properties,
		);

		// Make API request.
		return $this->make_api_request(
			self::API_ENDPOINTS['filters'],
			$body,
			'POST'
		);
	}

	/**
	 * Send plugin version tracking data
	 *
	 * Sends plugin usage analytics to the BragBook service for
	 * tracking active installations and versions.
	 *
	 * @since 3.0.0
	 *
	 * @param string $json_payload JSON-encoded tracking data
	 *
	 * @return string|null Response body on success, null on failure
	 */
	public function send_plugin_version_data( string $json_payload ): ?string {

		// Validate JSON payload.
		$decoded = json_decode( $json_payload, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->send_json_error(
				esc_html__(
					'Invalid JSON payload for version tracking',
					'brag-book-gallery'
				)
			);
			return null;
		}

		// Make API request with raw JSON.
		return $this->make_api_request(
			self::API_ENDPOINTS['tracker'],
			$decoded,
			'POST',
			false // Don't cache tracking requests
		);
	}

	/**
	 * Get individual case data
	 *
	 * Retrieves detailed information for a specific case including
	 * images, patient details, and SEO metadata.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string       $case_id             Case identifier
	 * @param string           $seo_suffix_url      SEO-friendly URL suffix
	 * @param string|array     $api_token           API token(s)
	 * @param string|int|array $procedure_id        Procedure ID(s)
	 * @param string|int|array $website_property_id Website property ID(s)
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function bb_get_case_data(
		int|string $case_id,
		string $seo_suffix_url,
		string|array $api_token,
		string|int|array $procedure_id,
		string|int|array $website_property_id
	): ?string {

		// Validate case ID.
		$case_id = is_numeric( $case_id ) ? (int) $case_id : sanitize_text_field( (string) $case_id );

		// Check if case ID is valid.
		$seo_suffix_url = sanitize_title( $seo_suffix_url );

		// Process API tokens.
		$tokens = $this->normalize_to_array( $api_token );

		// Process procedure IDs.
		$procedures = array_map( 'intval', $this->normalize_to_array( $procedure_id ) );

		// Process website property IDs.
		$properties = array_map( 'intval', $this->normalize_to_array( $website_property_id ) );

		// Build URL with query parameters.
		$endpoint = sprintf( self::API_ENDPOINTS['case_detail'], $case_id );

		// Ensure SEO suffix URL is properly encoded,
		$url = $endpoint . '?' . http_build_query( [ 'seoSuffixUrl' => $seo_suffix_url ] );

		// Prepare request body.
		$body = array(
			'apiTokens'          => $tokens,
			'procedureIds'       => $procedures,
			'websitePropertyIds' => $properties,
		);

		// Make cached API request.
		$cache_key = $this->generate_cache_key( 'case', [ $case_id, $seo_suffix_url ] );

		return $this->make_api_request(
			$url,
			$body,
			'POST',
			true,
			$cache_key
		);
	}

	/**
	 * Add case to favorites
	 *
	 * Saves a case to the user's favorites list along with their
	 * contact information for consultation follow-up.
	 *
	 * @since 3.0.0
	 *
	 * @param array        $api_tokens       Array of API tokens
	 * @param array        $website_prop_ids Array of website property IDs
	 * @param string       $email            User's email address
	 * @param string       $phone            User's phone number
	 * @param string       $name             User's name
	 * @param int|string   $case_id          Case identifier
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function bb_get_favorite_data(
		array $api_tokens,
		array $website_prop_ids,
		string $email,
		string $phone,
		string $name,
		$case_id
	): ?string {

		// Validate email.
		if ( ! is_email( $email ) ) {
			$this->send_json_error(
				esc_html__(
					'Invalid email address',
					'brag-book-gallery'
				)
			);
			return null;
		}

		// Sanitize input data.
		$email   = sanitize_email( $email );
		$phone   = $this->sanitize_phone( $phone );
		$name    = sanitize_text_field( $name );
		$case_id = is_numeric( $case_id ) ? (int) $case_id : sanitize_text_field( (string) $case_id );

		// Validate required fields
		if ( empty( $name ) || empty( $phone ) ) {
			$this->send_json_error(
				esc_html__(
					'Name and phone are required fields',
					'brag-book-gallery'
				)
			);
			return null;
		}

		// Prepare request body
		$body = array(
			'apiTokens'          => array_values( $api_tokens ),
			'websitePropertyIds' => array_map(
				'intval',
				$website_prop_ids
			),
			'email'              => $email,
			'phone'              => $phone,
			'name'               => $name,
			'caseId'             => $case_id,
		);

		// Make API request (don't cache favorites operations).
		return $this->make_api_request(
			self::API_ENDPOINTS['favorites_add'],
			$body,
			'POST',
			false
		);
	}

	/**
	 * Get user's favorites list
	 *
	 * Retrieves all cases that a user has added to their favorites
	 * based on their email address.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $api_tokens Array of API tokens
	 * @param array  $website_ids Array of website property IDs
	 * @param string $email       User's email address
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function bb_get_favorite_list_data(
		array $api_tokens,
		array $website_ids,
		string $email
	): ?string {
		// Validate email
		if ( ! is_email( $email ) ) {
			$this->send_json_error(
				esc_html__(
					'Invalid email address for favorites list',
					'brag-book-gallery'
				)
			);
			return null;
		}

		$email = sanitize_email( $email );

		// Prepare request body
		$body = array(
			'apiTokens'          => array_values( $api_tokens ),
			'websitePropertyIds' => array_map(
				'intval',
				$website_ids
			),
			'email'              => $email,
		);

		// Make cached API request.
		$cache_key = $this->generate_cache_key(
			'favorites',
			array( $email )
		);

		return $this->make_api_request(
			self::API_ENDPOINTS['favorites_list'],
			$body,
			'POST',
			true,
			$cache_key
		);
	}

	/**
	 * Get paginated case data
	 *
	 * Retrieves a paginated list of cases based on dynamic filter
	 * criteria and pagination parameters.
	 *
	 * @since 3.0.0
	 *
	 * @param array $filter_body Dynamic filter and pagination parameters
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function bb_get_pagination_data( array $filter_body ): ?string {

		// Validate filter body structure.
		if ( empty( $filter_body ) ) {
			$this->send_json_error(
				esc_html__(
					'Filter body cannot be empty',
					'brag-book-gallery'
				)
			);
			return null;
		}

		// Ensure required fields exist.
		$required_fields = array(
			'apiTokens',
			'websitePropertyIds'
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $filter_body[ $field ] ) ) {
				$this->send_json_error(
					sprintf(
						/* translators: %s: field name */
						esc_html__(
							'Missing required field: %s',
							'brag-book-gallery'
						),
						$field
					)
				);
				return null;
			}
		}

		// Sanitize count parameter if present (used for pagination)
		if ( isset( $filter_body['count'] ) ) {
			$filter_body['count'] = absint( $filter_body['count'] );
		}

		// Generate cache key based on filter parameters.
		$cache_key = $this->generate_cache_key(
			'pagination',
			$filter_body
		);

		// Use cache for better performance
		return $this->make_api_request(
			self::API_ENDPOINTS['cases'],
			$filter_body,
			'POST',
			true, // Enable cache
			$cache_key
		);
	}

	/**
	 * Get case details by case number.
	 *
	 * @since 3.0.0
	 * @param string $api_token API authentication token
	 * @param int $website_property_id Website property ID
	 * @param string $case_number Case number to fetch
	 * @return array|null Case data on success, null on failure
	 */
	public function bb_get_case_by_number(
		string $api_token,
		int $website_property_id,
		string $case_number
	): ?array {
		// Use the case detail endpoint with case ID
		$endpoint = sprintf( self::API_ENDPOINTS['case_detail'], $case_number );
		
		// Log the request details for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Case API Request Details:' );
			error_log( 'Endpoint: ' . $endpoint );
			error_log( 'Full URL: ' . Setup::get_api_url() . $endpoint );
			error_log( 'Token: ' . substr( $api_token, 0, 10 ) . '...' );
			error_log( 'Property ID: ' . $website_property_id );
			error_log( 'Case Number: ' . $case_number );
		}
		
		// Try both request formats - first with arrays (like pagination endpoint)
		$request_body = [
			'apiTokens' => [ $api_token ],
			'websitePropertyIds' => [ (int) $website_property_id ],
		];

		// Make API request to get specific case
		$response = $this->make_api_request(
			$endpoint,
			$request_body,
			'POST',
			false // Don't use cache for case details
		);
		
		// If first format failed, try with singular keys
		if ( empty( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'First format failed, trying singular keys...' );
			}
			
			// Try with singular keys (some endpoints might expect this)
			$request_body = [
				'apiToken' => $api_token,
				'websitePropertyId' => (int) $website_property_id,
			];
			
			$response = $this->make_api_request(
				$endpoint,
				$request_body,
				'POST',
				false
			);
		}
		
		if ( empty( $response ) ) {
			$this->log_error( 'Empty response from case detail API for case: ' . $case_number . ', endpoint: ' . $endpoint );
			return null;
		}

		// Decode response
		$data = json_decode( $response, true );
		
		// Log the response for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Case API Response status: ' . ( ! empty( $data ) ? 'Has data' : 'Empty' ) );
			if ( ! empty( $data ) ) {
				error_log( 'Case API Response keys: ' . implode( ', ', array_keys( $data ) ) );
				if ( isset( $data['data'] ) ) {
					error_log( 'Case API Response data count: ' . ( is_array( $data['data'] ) ? count( $data['data'] ) : 'not array' ) );
				}
			}
		}
		
		// Check if successful and has data
		if ( ! is_array( $data ) ) {
			$this->log_error( 'Invalid response format from case API' );
			return null;
		}
		
		// Check for success flag
		if ( isset( $data['success'] ) && $data['success'] === true && ! empty( $data['data'] ) ) {
			// Return the first case from data array
			return is_array( $data['data'] ) && ! empty( $data['data'][0] ) ? $data['data'][0] : null;
		}
		
		// If no success flag but has data key, try that
		if ( isset( $data['data'] ) && is_array( $data['data'] ) && ! empty( $data['data'] ) ) {
			// Return the first case from data array even without success flag
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'bb_get_case_by_number: Found data array without success flag' );
			}
			return $data['data'][0];
		}
		
		// If no success flag, maybe the response is the case data directly
		if ( isset( $data['id'] ) || isset( $data['caseId'] ) ) {
			return $data;
		}
		
		$this->log_error( 'Case not found or invalid response structure. Keys: ' . implode(', ', array_keys($data)) );
		return null;
	}

	/**
	 * Get sidebar navigation data
	 *
	 * Retrieves hierarchical navigation data for displaying
	 * procedure categories and subcategories in the sidebar.
	 *
	 * @since 3.0.0
	 *
	 * @param string|array $api_token API token(s)
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function get_api_sidebar( string|array $api_token ): ?string {

		// Normalize API token to array.
		$tokens = $this->normalize_to_array( $api_token );

		if ( empty( $tokens ) ) {
			$this->log_error(
				'No valid API tokens provided for sidebar request'
			);
			return null;
		}

		// Prepare request body.
		$body = array(
			'apiTokens' => $tokens,
		);

		// Generate cache key for sidebar data.
		$cache_key = $this->generate_cache_key( 'sidebar', $tokens );

		// Make cached API request with extended cache time for sidebar.
		return $this->make_api_request(
			self::API_ENDPOINTS['sidebar'],
			$body,
			'POST',
			true,
			$cache_key,
			1800 // Cache sidebar for 30 minutes
		);
	}

	/**
	 * Make API request with error handling and caching
	 *
	 * Centralizes all API requests with consistent error handling,
	 * retry logic, and optional caching.
	 *
	 * @since 3.0.0
	 *
	 * @param string $endpoint      API endpoint path
	 * @param array  $body          Request body data
	 * @param string $method        HTTP method (POST, GET, etc.)
	 * @param bool   $use_cache     Whether to cache the response
	 * @param string $cache_key     Cache key for storing response
	 * @param int    $cache_duration Cache duration in seconds
	 *
	 * @return string|null Response body on success, null on failure
	 */
	private function make_api_request(
		string $endpoint,
		array $body,
		string $method = 'POST',
		bool $use_cache = true,
		string $cache_key = '',
		int $cache_duration = self::CACHE_DURATION
	): ?string {

		// Check cache first if enabled.
		if ( $use_cache && ! empty( $cache_key ) ) {

			// Attempt to retrieve cached response.
			$cached_response = get_transient( $cache_key );

			// If cache hit, return cached response.
			if ( $cached_response !== false ) {
				return $cached_response;
			}
		}

		// Build full URL.
		$url = Setup::get_api_url() . $endpoint;

		// Prepare request arguments.
		$args = array(
			'method'  => $method,
			'timeout' => self::API_TIMEOUT,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'BragBook-Gallery-Plugin/3.0.0',
			),
			'body'    => wp_json_encode( $body ),
		);
		
		// Debug logging
		error_log( 'API Request URL: ' . $url );
		error_log( 'API Request Body: ' . wp_json_encode( $body ) );

		// Add custom headers for tracking.
		$args['headers']['X-Plugin-Version'] = '3.0.0';
		$args['headers']['X-WordPress-Version'] = get_bloginfo( 'version' );
		$args['headers']['X-Site-URL'] = home_url();

		// Attempt request with retry logic.
		$response = $this->request_with_retry( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->handle_api_error( $response, $url );
			return null;
		}

		// Check response code.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			$response_body = wp_remote_retrieve_body( $response );
			$this->log_error( sprintf( 'API request failed with status %d: %s', $response_code, $url ) );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API Response Body: ' . substr( $response_body, 0, 500 ) );
			}
			return null;
		}

		// Get response body.
		$response_body = wp_remote_retrieve_body( $response );
		
		// Debug log the response
		error_log( 'API Response: ' . $response_body );

		// Validate JSON response.
		$decoded = json_decode( $response_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_error( 'Invalid JSON response from API: ' . json_last_error_msg() );
			return null;
		}

		// Cache successful response if enabled
		if ( $use_cache && ! empty( $cache_key ) && ! empty( $response_body ) ) {
			set_transient( $cache_key, $response_body, $cache_duration );
		}

		return $response_body;
	}

	/**
	 * Make request with retry logic
	 *
	 * Attempts to make an API request with automatic retry
	 * on failure with exponential backoff.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url  Request URL
	 * @param array  $args Request arguments
	 *
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	private function request_with_retry( string $url, array $args ) {
		$attempts = 0;
		$last_error = null;

		while ( $attempts < self::MAX_RETRY_ATTEMPTS ) {
			$response = wp_remote_request( $url, $args );

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			$last_error = $response;
			$attempts++;

			// Exponential backoff: 1s, 2s, 4s
			if ( $attempts < self::MAX_RETRY_ATTEMPTS ) {
				sleep( pow( 2, $attempts - 1 ) );
			}
		}

		return $last_error ?? new WP_Error(
			'api_max_retries',
			esc_html__( 'Maximum retry attempts exceeded', 'brag-book-gallery' )
		);
	}

	/**
	 * Handle API error response
	 *
	 * Logs error details and sends appropriate JSON error response.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Error $error WP_Error object
	 * @param string   $url   Request URL for context
	 *
	 * @return void
	 */
	private function handle_api_error( WP_Error $error, string $url ): void {
		$error_message = $error->get_error_message();
		$error_code = $error->get_error_code();

		// Log detailed error
		$this->log_error( sprintf(
			'API Error [%s] for URL %s: %s',
			$error_code,
			$url,
			$error_message
		) );

		// Send user-friendly error message
		$user_message = esc_html__(
			'Unable to connect to the gallery service. Please try again later.',
			'brag-book-gallery'
		);

		// Add specific messages for known errors.
		if ( str_contains( $error_code, 'timeout' ) ) {
			$user_message = esc_html__(
				'The request timed out. Please try again.',
				'brag-book-gallery'
			);
		} elseif ( str_contains( $error_code, 'http_request_failed' ) ) {
			$user_message = esc_html__(
				'Connection failed. Please check your internet connection.',
				'brag-book-gallery'
			);
		}

		$this->send_json_error( $user_message );
	}

	/**
	 * Parse comma-separated string into array
	 *
	 * @since 3.0.0
	 *
	 * @param string $input Comma-separated string
	 *
	 * @return array Parsed array of trimmed values
	 */
	private function parse_comma_separated( string $input ): array {
		if ( empty( $input ) ) {
			return [];
		}

		return array_map( 'trim', explode( ',', $input ) );
	}

	/**
	 * Normalize input to array format
	 *
	 * Handles various input types and converts them to array format.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $input Input value (string, array, or other)
	 *
	 * @return array Normalized array
	 */
	private function normalize_to_array( $input ): array {
		if ( is_array( $input ) ) {
			return $input;
		}

		if ( is_string( $input ) ) {
			return $this->parse_comma_separated( $input );
		}

		if ( is_numeric( $input ) ) {
			return [ $input ];
		}

		return [];
	}

	/**
	 * Generate cache key for API responses
	 *
	 * Creates a unique cache key based on the request type and parameters.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Request type identifier
	 * @param mixed  $data Data to include in cache key
	 *
	 * @return string Generated cache key
	 */
	private function generate_cache_key( string $type, mixed $data ): string {
		$key_data = is_array( $data ) ? wp_json_encode( $data ) : (string) $data;
		return 'brag_book_gallery_api_' . $type . '_' . md5( $key_data );
	}

	/**
	 * Sanitize phone number
	 *
	 * Removes non-numeric characters from phone numbers while
	 * preserving common formatting characters.
	 *
	 * @since 3.0.0
	 *
	 * @param string $phone Phone number to sanitize
	 *
	 * @return string Sanitized phone number
	 */
	private function sanitize_phone( string $phone ): string {
		// Remove everything except numbers, +, -, (, ), and spaces
		$phone = preg_replace( '/[^0-9+\-() ]/', '', $phone );
		return trim( $phone );
	}

	/**
	 * Send JSON error response
	 *
	 * Sends a properly formatted JSON error response and optionally
	 * terminates execution.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message    Error message
	 * @param bool   $terminate  Whether to terminate execution
	 *
	 * @return void
	 */
	private function send_json_error( string $message, bool $terminate = false ): void {
		if ( wp_doing_ajax() ) {
			wp_send_json_error( [ 'message' => $message ] );
		} else {
			$this->log_error( $message );
			if ( $terminate ) {
				wp_die( esc_html( $message ) );
			}
		}
	}

	/**
	 * Log error message
	 *
	 * Logs error messages to the WordPress debug log if enabled.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Error message to log
	 *
	 * @return void
	 */
	private function log_error( string $message ): void {

		if ( defined( constant_name: 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( message: '[BragBook API] ' . $message );
		}

		/**
		 * Action hook for custom error logging
		 *
		 * @since 3.0.0
		 *
		 * @param string $message Error message
		 */
		do_action(
			hook_name: 'brag_book_gallery_api_error',
			value: $message
		);
	}

	/**
	 * Track case view
	 *
	 * Records a view event for a specific case for analytics purposes.
	 * This endpoint is typically called when a user views a case detail page.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token API authentication token
	 * @param int    $case_id   Optional case ID to track
	 * @param array  $metadata  Optional additional metadata for tracking
	 *
	 * @return string|null Response body on success, null on failure
	 */
	public function track_case_view(
		string $api_token,
		int $case_id = 0,
		array $metadata = array()
	): ?string {
		// Build query parameters
		$query_params = array(
			'apiToken' => $api_token,
		);

		// Add case ID if provided
		if ( $case_id > 0 ) {
			$query_params['caseId'] = $case_id;
		}

		// Add any additional metadata
		if ( ! empty( $metadata ) ) {
			$query_params = array_merge( $query_params, $metadata );
		}

		// Build URL with query parameters
		$url = self::API_ENDPOINTS['views'] . '?' . http_build_query( $query_params );

		// Make GET request to track the view
		$response = wp_remote_get(
			$this->get_api_base_url() . $url,
			array(
				'timeout' => self::API_TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			$this->log_api_error(
				self::API_ENDPOINTS['views'],
				$response->get_error_message()
			);
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Check response code
		if ( $response_code !== 200 ) {
			$this->log_api_error(
				self::API_ENDPOINTS['views'],
				sprintf(
					'HTTP %d: %s',
					$response_code,
					$response_body
				)
			);
			return null;
		}

		return $response_body;
	}

	/**
	 * Get carousel data from API
	 *
	 * Retrieves carousel images for display in gallery carousels.
	 *
	 * @since 3.0.0
	 *
	 * @param string|array $api_token API token(s).
	 * @param array        $options   Carousel options.
	 * @return string|null JSON response on success, null on failure.
	 */
	public function get_carousel_data( string|array $api_token, array $options = [] ): ?string {
		// Get first API token (carousel endpoint expects single token)
		$tokens = $this->normalize_to_array( $api_token );
		$token = $tokens[0] ?? '';

		if ( empty( $token ) ) {
			$this->log_error( 'No valid API token provided for carousel request' );
			return null;
		}

		// Set default options with defaults for memberId and procedureId
		$default_options = [
			'websitePropertyId' => '',
			'limit'            => 10,
			'start'            => 1,
			'procedureId'      => 6839,  // Default procedureId
			'memberId'         => 129,   // Default memberId
		];

		$options = array_merge( $default_options, $options );

		// Build query parameters - always include all params with defaults
		$query_params = [
			'websitePropertyId' => absint( $options['websitePropertyId'] ),
			'start'            => absint( $options['start'] ) ?: 1,
			'limit'            => absint( $options['limit'] ) ?: 10,
			'apiToken'         => $token,
			'procedureId'      => absint( $options['procedureId'] ) ?: 6839,
			'memberId'         => absint( $options['memberId'] ) ?: 129,
		];

		// Build URL with query parameters
		$url = self::API_ENDPOINTS['carousel'] . '?' . http_build_query( $query_params );

		// Generate cache key for carousel data
		$cache_key = $this->generate_cache_key( 'carousel', $query_params );

		// For carousel GET request, we need to use a simpler approach
		// The carousel endpoint doesn't use the standard make_api_request method
		
		// Check cache first
		$cached_response = get_transient( $cache_key );
		if ( $cached_response !== false ) {
			return $cached_response;
		}

		// Make direct GET request
		$full_url = Setup::get_api_url() . $url;
		
		$response = wp_remote_get( $full_url, [
			'timeout' => 30,
			'headers' => [
				'Accept' => 'application/json',
				'User-Agent' => 'BragBook Gallery Plugin/3.0.0',
			],
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Carousel API request failed: ' . $response->get_error_message() );
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$this->log_error( sprintf( 'Carousel API returned status %d', $response_code ) );
			return null;
		}

		// Cache successful response
		if ( ! empty( $response_body ) ) {
			set_transient( $cache_key, $response_body, 900 );
		}

		return $response_body;
	}

	/**
	 * Clear API cache
	 *
	 * Clears all cached API responses. Useful when data needs
	 * to be refreshed immediately.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Optional cache type to clear
	 *
	 * @return bool True on success, false on failure
	 */
	public function clear_api_cache( string $type = '' ): bool {
		global $wpdb;

		if ( empty( $type ) ) {
			// Clear all BragBook API cache including timeout transients
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s",
					'_transient_brag_book_gallery_api_%',
					'_transient_timeout_brag_book_gallery_api_%'
				)
			);
		} else {
			// Clear specific type including timeout transients
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s",
					'_transient_brag_book_gallery_api_' . $type . '_%',
					'_transient_timeout_brag_book_gallery_api_' . $type . '_%'
				)
			);
		}

		// Clear object cache as well
		wp_cache_flush();

		/**
		 * Action hook after cache is cleared
		 *
		 * @since 3.0.0
		 *
		 * @param string $type Cache type that was cleared
		 * @param bool   $result Whether cache was successfully cleared
		 */
		do_action(
			'brag_book_gallery_api_cache_cleared',
			$type,
			$result !== false
		);

		return $result !== false;
	}
}
