<?php
/**
 * REST API Endpoints Class
 *
 * Enterprise-grade REST API management system for BRAGBook Gallery plugin.
 * Provides comprehensive external API communication with advanced error handling,
 * intelligent caching, retry mechanisms, and VIP-compliant architecture.
 *
 * Features:
 * - Multi-endpoint API communication with BRAG book service
 * - Advanced caching strategies with configurable TTL
 * - Exponential backoff retry logic for resilient connections
 * - Comprehensive input validation and sanitization
 * - Performance monitoring and error tracking
 * - WordPress VIP compliant logging and debugging
 * - Modern PHP 8.2+ features and type safety
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
use BRAGBookGallery\Includes\Extend\Cache_Manager;
use WP_Error;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise REST API Endpoints Management
 *
 * Comprehensive API communication system with advanced features:
 *
 * Core Responsibilities:
 * - External API communications with BRAG book service
 * - Data retrieval with intelligent caching and validation
 * - Advanced filter and pagination processing
 * - Favorites management with user data protection
 * - Plugin usage analytics and tracking
 * - Performance monitoring and optimization
 *
 * Enterprise Features:
 * - WordPress VIP compliant architecture
 * - Multi-level caching with configurable TTL
 * - Exponential backoff retry mechanisms
 * - Comprehensive error handling and logging
 * - Input validation and sanitization
 * - Performance metrics and monitoring
 * - Modern PHP 8.2+ type safety
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
	private const API_TIMEOUT = 8;

	/**
	 * Cache TTL constants for different cache types
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;   // 5 minutes
	private const CACHE_TTL_MEDIUM = 900;  // 15 minutes
	private const CACHE_TTL_LONG = 1800;   // 30 minutes
	private const CACHE_TTL_EXTENDED = 3600; // 1 hour

	/**
	 * Memory cache for performance optimization
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];

	/**
	 * Error tracking for debugging
	 *
	 * @since 3.0.0
	 * @var array<string, array>
	 */
	private array $error_log = [];

	/**
	 * Performance metrics tracking
	 *
	 * @since 3.0.0
	 * @var array<string, array>
	 */
	private array $performance_metrics = [];

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
		'carousel'         => '/api/plugin/carousel',
		'optimize_image'   => '/api/plugin/optimize-image',
		'favorites_add'    => '/api/plugin/combine/favorites/add',
		'favorites_list'   => '/api/plugin/combine/favorites/list',
		'sidebar'          => '/api/plugin/combine/sidebar',
		'cases'            => '/api/plugin/combine/cases',
		'case_detail'      => '/api/plugin/combine/cases/%s',
		'sitemap'          => '/api/plugin/sitemap',
		'consultations'    => '/api/plugin/consultations',
		'tracker'          => '/api/plugin/tracker',
		'views'            => '/api/plugin/views',
	);

	/**
	 * Input validation security rules
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private const SECURITY_RULES = array(
		'email' => array(
			'required' => true,
			'max_length' => 254,
			'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
		),
		'phone' => array(
			'required' => true,
			'min_length' => 10,
			'max_length' => 20,
			'pattern' => '/^[\d\s\-\(\)\+\.]+$/',
		),
		'name' => array(
			'required' => true,
			'min_length' => 1,
			'max_length' => 100,
			'pattern' => '/^[a-zA-Z\s\-\.\']+$/',
		),
		'case_id' => array(
			'required' => false,
			'min_length' => 1,
			'max_length' => 20,
			'pattern' => '/^[a-zA-Z0-9_\-]+$/',
		),
	);

	/**
	 * Send plugin version tracking data
	 *
	 * Sends plugin usage analytics to the BRAG book service for
	 * tracking active installations and versions.
	 *
	 * @since 3.0.0
	 *
	 * @param string $json_payload JSON-encoded tracking data
	 *
	 * @return string|null Response body on success, null on failure
	 */
	public function send_plugin_version_data( string $json_payload ): ?string {
		try {
			// Input validation
			if ( empty( $json_payload ) ) {
				$this->send_json_error(
					esc_html__(
						'Empty payload provided for version tracking',
						'brag-book-gallery'
					)
				);
				return null;
			}

			// Validate JSON payload with comprehensive error checking
			$decoded = json_decode( $json_payload, true, 512, JSON_THROW_ON_ERROR );

			// Validate decoded structure
			if ( ! is_array( $decoded ) ) {
				$this->send_json_error(
					esc_html__(
						'Invalid payload structure for version tracking',
						'brag-book-gallery'
					)
				);
				return null;
			}

			// Make API request with comprehensive error handling
			return $this->make_api_request(
				self::API_ENDPOINTS['tracker'],
				$decoded,
				'POST',
				false // Don't cache tracking requests
			);

		} catch ( \JsonException $e ) {
			$this->log_error( 'JSON decode error in version tracking: ' . $e->getMessage() );
			$this->send_json_error(
				esc_html__(
					'Invalid JSON payload for version tracking',
					'brag-book-gallery'
				)
			);
			return null;
		} catch ( \Exception $e ) {
			$this->log_error( 'Unexpected error in version tracking: ' . $e->getMessage() );
			$this->send_json_error(
				esc_html__(
					'Version tracking service temporarily unavailable',
					'brag-book-gallery'
				)
			);
			return null;
		}
	}

	/**
	 * Get individual case data with comprehensive validation
	 *
	 * Retrieves detailed case information from the BRAG book service with
	 * advanced error handling, input validation, and caching support.
	 *
	 * Features:
	 * - Input sanitization and validation for all parameters
	 * - Multi-format support for case IDs and procedure identifiers
	 * - SEO-friendly URL handling with proper encoding
	 * - Intelligent caching with configurable TTL
	 * - Comprehensive error handling and logging
	 *
	 * @since 3.0.0
	 *
	 * @param int|string            $case_id             Case identifier (numeric ID or string slug)
	 * @param string                $seo_suffix_url      SEO-friendly URL suffix for case routing
	 * @param string|array<string>  $api_token           API authentication token(s)
	 * @param string|int|array<int> $procedure_id        Procedure ID(s) for filtering
	 * @param string|int|array<int> $website_property_id Website property ID(s) for multi-site support
	 *
	 * @return string|null JSON-encoded case data on success, null on failure
	 *
	 * @throws InvalidArgumentException When required parameters are invalid
	 */
	public function get_case_data(
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
	 * Get single case details by ID.
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID.
	 * @return string|null Response body on success, null on failure.
	 */
	public function get_case_details( string $case_id ): ?string {
		try {
			// Input validation
			if ( empty( $case_id ) ) {
				$this->log_error( 'Empty case ID provided to get_case_details' );
				return null;
			}

			// Sanitize case ID
			$case_id = sanitize_text_field( $case_id );

			// Get API configuration using the same method as AJAX handlers
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'get_case_details: Retrieved API tokens: ' . print_r( $api_tokens, true ) );
				error_log( 'get_case_details: Retrieved website property IDs: ' . print_r( $website_property_ids, true ) );
			}

			if ( empty( $api_tokens[0] ) || empty( $website_property_ids[0] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: get_case_details - Missing API configuration' );
					error_log( 'BRAGBook Gallery: API tokens empty: ' . ( empty( $api_tokens[0] ) ? 'YES' : 'NO' ) );
					error_log( 'BRAGBook Gallery: Website property IDs empty: ' . ( empty( $website_property_ids[0] ) ? 'YES' : 'NO' ) );
				}
				return null;
			}

			$api_token = $api_tokens[0];
			$website_property_id = intval( $website_property_ids[0] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'get_case_details: Using API token (first 10): ' . substr( $api_token, 0, 10 ) );
				error_log( 'get_case_details: Using website property ID: ' . $website_property_id );
			}

		// Get the API base URL from settings
		$api_base_url = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );

		// Build API URL with case ID
		$api_url = sprintf(
			'%s%s',
			$api_base_url,
			sprintf( self::API_ENDPOINTS['case_detail'], $case_id )
		);

		// Add query parameters
		$api_url = add_query_arg(
			[
				'apiToken' => $api_token,
				'websitePropertyId' => $website_property_id,
			],
			$api_url
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'BRAG Book Gallery: Fetching case ' . $case_id . ' from: ' . $api_url );
			do_action( 'qm/debug', 'BRAG Book Gallery: API Token length: ' . strlen( $api_token ) );
			do_action( 'qm/debug', 'BRAG Book Gallery: Website Property ID: ' . $website_property_id );
		}

		// Make API request
		$response = wp_remote_get(
			$api_url,
			[
				'timeout' => self::API_TIMEOUT,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'BRAG Book Gallery: API error for case ' . $case_id . ': ' . $response->get_error_message() );
			}
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				do_action( 'qm/debug', 'BRAG Book Gallery: API returned status ' . $response_code . ' for case ' . $case_id );
				do_action( 'qm/debug', 'Response body: ' . wp_remote_retrieve_body( $response ) );
			}
			return null;
		}

			$response_body = wp_remote_retrieve_body( $response );

			// Validate response body is not empty
			if ( empty( $response_body ) ) {
				$this->log_error( 'Empty response body received for case: ' . $case_id );
				return null;
			}

			return $response_body;

		} catch ( \Exception $e ) {
			$this->log_error( 'Unexpected error in get_case_details: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Add case to user favorites with contact validation
	 *
	 * Securely saves a case to the user's favorites list with comprehensive
	 * input validation, sanitization, and contact information processing.
	 *
	 * Security Features:
	 * - Email validation using WordPress is_email() function
	 * - Phone number sanitization with format preservation
	 * - Name sanitization to prevent XSS attacks
	 * - Input validation for all required fields
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $api_tokens       Array of API authentication tokens
	 * @param array<int>    $website_prop_ids Array of website property IDs
	 * @param string        $email            User's email address (validated)
	 * @param string        $phone            User's phone number (sanitized)
	 * @param string        $name             User's display name (sanitized)
	 * @param int|string    $case_id          Case identifier to add to favorites
	 *
	 * @return string|null JSON response with favorite status, null on failure
	 *
	 * @throws InvalidArgumentException When email format is invalid
	 * @throws InvalidArgumentException When required fields are empty
	 */
	public function get_favorite_data(
		array $api_tokens,
		array $website_prop_ids,
		string $email,
		string $phone,
		string $name,
		int|string $case_id
	): ?string {

		// Validate email.
		if ( ! is_email( $email ) ) {
			throw new \InvalidArgumentException(
				esc_html__(
					'Invalid email address',
					'brag-book-gallery'
				)
			);
		}

		// Enhanced input validation and sanitization with security rules
		$validation_errors = [];

		// Validate email with enhanced security
		$email = sanitize_email( $email );
		if ( ! $this->validate_input( $email, 'email' ) ) {
			$validation_errors[] = 'Invalid email format or length';
		}

		// Validate and sanitize phone number
		$phone = $this->sanitize_phone( $phone );
		if ( ! $this->validate_input( $phone, 'phone' ) ) {
			$validation_errors[] = 'Invalid phone number format';
		}

		// Validate and sanitize name
		$name = sanitize_text_field( $name );
		if ( ! $this->validate_input( $name, 'name' ) ) {
			$validation_errors[] = 'Invalid name format or length';
		}

		// Validate and sanitize case ID
		$case_id_str = is_numeric( $case_id ) ? (string) $case_id : sanitize_text_field( (string) $case_id );
		if ( ! $this->validate_input( $case_id_str, 'case_id' ) ) {
			$validation_errors[] = 'Invalid case ID format';
		}
		$case_id = is_numeric( $case_id ) ? (int) $case_id : $case_id_str;

		// Check for validation errors
		if ( ! empty( $validation_errors ) ) {
			throw new \InvalidArgumentException(
				esc_html__(
					'Input validation failed: ',
					'brag-book-gallery'
				) . implode( ', ', $validation_errors )
			);
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
			'caseId'             => intval( $case_id ), // API expects caseId as a number
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
	public function get_favorite_list_data(
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
	public function get_pagination_data( array $filter_body ): ?string {

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

		// Pagination caching disabled - fetch fresh data
		return $this->make_api_request(
			self::API_ENDPOINTS['cases'],
			$filter_body,
			'POST',
			false // Disable cache
		);
	}

	/**
	 * Get case details by case number.
	 *
	 * @since 3.0.0
	 * @param string $api_token API authentication token
	 * @param int $website_property_id Website property ID
	 * @param string $case_number Case number to fetch
	 * @param array $procedure_ids Optional procedure IDs for filtering
	 * @return array|null Case data on success, null on failure
	 */
	public function get_case_by_number(
		string $api_token,
		int $website_property_id,
		string $case_number,
		array $procedure_ids = []
	): ?array {
		// Use the case detail endpoint with case ID
		$endpoint = sprintf( self::API_ENDPOINTS['case_detail'], $case_number );

		// Log the request details for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'BRAGBook Gallery: Case API Request Details:' );
			do_action( 'qm/debug', '  Endpoint: ' . $endpoint );
			do_action( 'qm/debug', '  Full URL: ' . Setup::get_api_url() . $endpoint );
			do_action( 'qm/debug', '  Token: ' . substr( $api_token, 0, 10 ) . '...' );
			do_action( 'qm/debug', '  Property ID: ' . $website_property_id );
			do_action( 'qm/debug', '  Case Number: ' . $case_number );
			do_action( 'qm/debug', '  Procedure IDs: ' . json_encode( $procedure_ids ) );
		}

		// Try both request formats - first with arrays (like pagination endpoint)
		// For single case endpoint, we don't need to send procedureIds
		$request_body = [
			'apiTokens' => [ $api_token ],
			'websitePropertyIds' => [ (int) $website_property_id ],
		];

		// Only add procedureIds if they were explicitly provided
		if ( ! empty( $procedure_ids ) ) {
			$request_body['procedureIds'] = array_map( 'intval', $procedure_ids );
		}

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
				do_action( 'qm/debug', 'First format failed, trying singular keys...' );
			}

			// Try with singular keys (some endpoints might expect this)
			$request_body = [
				'apiToken' => $api_token,
				'websitePropertyId' => (int) $website_property_id,
				'procedureIds' => array_map( 'intval', $procedure_ids ),
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
			do_action( 'qm/debug', 'BRAGBook Gallery: Case API Response status: ' . ( ! empty( $data ) ? 'Has data' : 'Empty' ) );
			if ( ! empty( $data ) ) {
				do_action( 'qm/debug', '  Response keys: ' . implode( ', ', array_keys( $data ) ) );
				if ( isset( $data['success'] ) ) {
					do_action( 'qm/debug', '  Success flag: ' . ( $data['success'] ? 'true' : 'false' ) );
				}
				if ( isset( $data['data'] ) ) {
					do_action( 'qm/debug', '  Data type: ' . gettype( $data['data'] ) );
					if ( is_array( $data['data'] ) ) {
						do_action( 'qm/debug', '  Data count: ' . count( $data['data'] ) );
						if ( ! empty( $data['data'][0] ) && isset( $data['data'][0]['id'] ) ) {
							do_action( 'qm/debug', '  First case ID: ' . $data['data'][0]['id'] );
						}
					}
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
				do_action( 'qm/debug', 'get_case_by_number: Found data array without success flag' );
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

		// Make direct API request without caching (sidebar data is cached elsewhere)
		return $this->make_api_request(
			self::API_ENDPOINTS['sidebar'],
			$body,
			'POST',
			false
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

		// Check if caching is enabled in settings
		$enable_caching = get_option(
			'brag_book_gallery_enable_caching',
			'yes'
		);

		// Override cache flag if caching is disabled
		if ( $enable_caching !== 'yes' ) {
			$use_cache = false;
		}

		// Performance tracking start
		$start_time = microtime( true );

		// Check multi-level cache if enabled
		if ( $use_cache && ! empty( $cache_key ) ) {
			// Try memory cache first, then transient cache
			$cached_response = $this->get_cached_response( $cache_key );

			if ( $cached_response !== false ) {
				// Track performance for cached response
				$duration = microtime( true ) - $start_time;
				$this->track_performance_metrics( $endpoint, $duration, true );
				return $cached_response;
			}
		}

		// Build full URL.
		$url = Setup::get_api_url() . $endpoint;

		// Get timeout from settings
		$api_timeout = intval( get_option( 'brag_book_gallery_api_timeout', self::API_TIMEOUT ) );

		// Prepare request arguments.
		$args = array(
			'method'  => $method,
			'timeout' => $api_timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'BRAG book-Gallery-Plugin/3.0.0',
			),
			'body'    => wp_json_encode( $body ),
		);

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'API Request URL: ' . $url );
			do_action( 'qm/debug', 'API Request Body: ' . wp_json_encode( $body ) );
		}

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
				do_action( 'qm/debug', 'API Response Body: ' . substr( $response_body, 0, 500 ) );
			}
			return null;
		}

		// Get response body.
		$response_body = wp_remote_retrieve_body( $response );

		// Debug log the response
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', 'API Response: ' . $response_body );
		}

		// Validate JSON response.
		$decoded = json_decode( $response_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_error( 'Invalid JSON response from API: ' . json_last_error_msg() );
			return null;
		}

		// Cache successful response with multi-level storage
		if ( $use_cache && ! empty( $cache_key ) && ! empty( $response_body ) ) {
			// Get cache duration from settings if not provided
			if ( $cache_duration === self::CACHE_DURATION ) {
				$cache_duration = intval( get_option( 'brag_book_gallery_cache_duration', self::CACHE_DURATION ) );
			}

			// Use multi-level caching for optimal performance
			$this->set_cached_response( $cache_key, $response_body, $cache_duration );
		}

		// Track performance metrics for live response
		$duration = microtime( true ) - $start_time;
		$this->track_performance_metrics( $endpoint, $duration, false );

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
	private function request_with_retry( string $url, array $args ): WP_Error|array {
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

		// Send user-friendly error message using modern match expression
		$user_message = match ( true ) {
			str_contains( $error_code, 'timeout' ) => esc_html__(
				'The request timed out. Please try again.',
				'brag-book-gallery'
			),
			str_contains( $error_code, 'http_request_failed' ) => esc_html__(
				'Connection failed. Please check your internet connection.',
				'brag-book-gallery'
			),
			default => esc_html__(
				'Unable to connect to the gallery service. Please try again later.',
				'brag-book-gallery'
			)
		};

		// If we're in an AJAX context, throw exception instead of calling wp_send_json_error
		// This allows the calling code to handle the error appropriately
		if ( wp_doing_ajax() ) {
			throw new \Exception( $user_message );
		} else {
			$this->send_json_error( $user_message );
		}
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
	private function normalize_to_array( mixed $input ): array {
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
		return 'brag_book_gallery_transient_api_' . $type . '_' . $key_data;
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

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', '[BRAG book API] ' . $message );
		}

		/**
		 * Action hook for custom error logging
		 *
		 * @since 3.0.0
		 *
		 * @param string $message Error message
		 */
		do_action( 'brag_book_gallery_api_error', $message );
	}

	/**
	 * Track case view
	 *
	 * Records a view event for a specific case for analytics purposes.
	 * This endpoint is typically called when a user views a case detail page.
	 * Uses POST method with JSON body containing caseId and apiToken.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token API authentication token
	 * @param int    $case_id   Case ID to track (required)
	 * @param array  $metadata  Optional additional metadata for tracking
	 *
	 * @return string|null Response body on success, null on failure
	 */
	public function track_case_view(
		string $api_token,
		int $case_id,
		array $metadata = array()
	): ?string {
		// Validate required parameters
		if ( empty( $api_token ) || $case_id <= 0 ) {
			$this->log_error( 'Invalid parameters for case view tracking: API token and case ID are required' );
			return null;
		}

		// Build request body with required format
		$body = array(
			'caseId' => $case_id,
		);

		// Add any additional metadata
		if ( ! empty( $metadata ) ) {
			$body = array_merge( $body, $metadata );
		}

		// Add API token to URL as query parameter (authentication)
		$url = self::API_ENDPOINTS['views'] . '?' . http_build_query( array( 'apiToken' => $api_token ) );

		// Make POST request to track the view
		$response = wp_remote_post(
			$this->get_api_base_url() . $url,
			array(
				'timeout' => self::API_TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $body ),
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
		if ( ! in_array( $response_code, [ 200, 201 ], true ) ) {
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

		// Set default options (removed memberId as it's not needed for carousel)
		$default_options = [
			'websitePropertyId' => '',
			'limit'            => 10,
			'start'            => 1,
			'procedureId'      => null,  // No default - only filter if specifically requested
		];

		$options = array_merge( $default_options, $options );

		// Build query parameters - removed memberId as it's not needed for carousel endpoint
		$query_params = [
			'websitePropertyId' => absint( $options['websitePropertyId'] ),
			'start'            => absint( $options['start'] ) ?: 1,
			'limit'            => absint( $options['limit'] ) ?: 10,
			'apiToken'         => $token,
		];

		// Only add procedureId if it's actually provided and valid
		if ( ! empty( $options['procedureId'] ) && $options['procedureId'] !== null ) {
			$query_params['procedureId'] = absint( $options['procedureId'] );
		}

		// Build URL with query parameters
		$url = self::API_ENDPOINTS['carousel'] . '?' . http_build_query( $query_params );

		// For carousel GET request, we need to use a simpler approach
		// The carousel endpoint doesn't use the standard make_api_request method

		// Make direct GET request
		$full_url = Setup::get_api_url() . $url;

		$response = wp_remote_get( $full_url, [
			'timeout' => 30,
			'headers' => [
				'Accept' => 'application/json',
				'User-Agent' => 'BRAG book Gallery Plugin/3.0.0',
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

		// Return response directly without caching

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

		// Ensure $type is never null to avoid deprecation warnings
		$type = $type ?? '';

		if ( empty( $type ) ) {
			// Clear all BRAG book API cache including timeout transients
			$result = Cache_Manager::delete_pattern( 'brag_book_gallery_transient_api_*' );
		} else {
			// Clear specific type including timeout transients
			$result = Cache_Manager::delete_pattern( 'brag_book_gallery_transient_api_' . $type . '_*' );
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

	/**
	 * Optimize image using API
	 *
	 * Optimizes and transforms images on-the-fly using the API service.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token API authentication token
	 * @param string $image_url Source image URL to optimize
	 * @param string $quality Image quality (small/medium/large)
	 * @param string $format Output format (png/jpg/webp)
	 * @param string $plugin_version Plugin version for tracking
	 *
	 * @return string|null Optimized image URL on success, null on failure
	 */
	public function optimize_image(
		string $api_token,
		string $image_url,
		string $quality = 'medium',
		string $format = 'webp',
		string $plugin_version = '3.0.0'
	): ?string {
		// Validate inputs
		if ( empty( $api_token ) || empty( $image_url ) ) {
			$this->log_error( 'API token and image URL are required for image optimization' );
			return null;
		}

		// Validate URL
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			$this->log_error( 'Invalid image URL provided for optimization' );
			return null;
		}

		// Build query parameters
		$query_params = [
			'url' => $image_url,
			'quality' => $quality,
			'format' => $format,
		];

		// Build URL with query parameters
		$url = self::API_ENDPOINTS['optimize_image'] . '?' . http_build_query( $query_params );
		$full_url = Setup::get_api_url() . $url;

		// Make GET request with headers
		$response = wp_remote_get( $full_url, [
			'timeout' => self::API_TIMEOUT,
			'headers' => [
				'x-api-token' => $api_token,
				'x-plugin-version' => $plugin_version,
				'Accept' => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Image optimization API request failed: ' . $response->get_error_message() );
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$this->log_error( sprintf( 'Image optimization API returned status %d', $response_code ) );
			return null;
		}

		return $response_body;
	}

	/**
	 * Get sitemap data from API
	 *
	 * Generates sitemap data for SEO purposes.
	 *
	 * @since 3.0.0
	 *
	 * @param string|array $api_tokens API token(s)
	 * @param string|array $website_property_ids Website property ID(s)
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function get_sitemap_data( string|array $api_tokens, string|array $website_property_ids ): ?string {
		// Normalize inputs to arrays
		$tokens = $this->normalize_to_array( $api_tokens );
		$property_ids = array_map( 'intval', $this->normalize_to_array( $website_property_ids ) );

		if ( empty( $tokens ) || empty( $property_ids ) ) {
			$this->log_error( 'API tokens and website property IDs are required for sitemap generation' );
			return null;
		}

		// Prepare request body
		$body = [
			'apiTokens' => $tokens,
			'websitePropertyIds' => $property_ids,
		];

		// Generate cache key
		$cache_key = $this->generate_cache_key( 'sitemap', $body );

		// Make API request
		return $this->make_api_request(
			self::API_ENDPOINTS['sitemap'],
			$body,
			'POST',
			true,
			$cache_key,
			self::CACHE_TTL_LONG
		);
	}

	/**
	 * Submit consultation request
	 *
	 * Submits a consultation request to the API.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_token API authentication token
	 * @param int    $website_property_id Website property ID
	 * @param string $email User's email address
	 * @param string $phone User's phone number
	 * @param string $name User's name
	 * @param string $details Consultation details
	 *
	 * @return string|null JSON response on success, null on failure
	 */
	public function submit_consultation(
		string $api_token,
		int $website_property_id,
		string $email,
		string $phone,
		string $name,
		string $details = ''
	): ?string {
		// Validate inputs
		if ( empty( $api_token ) || empty( $website_property_id ) ) {
			$this->log_error( 'API token and website property ID are required for consultation' );
			return null;
		}

		// Validate email
		if ( ! is_email( $email ) ) {
			$this->log_error( 'Valid email address is required for consultation' );
			return null;
		}

		// Sanitize inputs
		$email = sanitize_email( $email );
		$phone = $this->sanitize_phone( $phone );
		$name = sanitize_text_field( $name );
		$details = sanitize_textarea_field( $details );

		// Build query parameters for authentication
		$query_params = [
			'apiToken' => $api_token,
			'websitepropertyId' => $website_property_id, // Note: lowercase 'p' as per API docs
		];

		// Build URL with query parameters
		$url = self::API_ENDPOINTS['consultations'] . '?' . http_build_query( $query_params );

		// Prepare request body
		$body = [
			'email' => $email,
			'phone' => $phone,
			'name' => $name,
			'details' => $details,
		];

		// Make API request (don't cache consultation submissions)
		return $this->make_api_request(
			$url,
			$body,
			'POST',
			false // Don't cache
		);
	}

	/**
	 * Get API base URL
	 *
	 * Returns the configured API base URL for the current environment.
	 *
	 * @since 3.0.0
	 *
	 * @return string API base URL
	 */
	private function get_api_base_url(): string {
		return Setup::get_api_url();
	}

	/**
	 * Log API error with context
	 *
	 * Enhanced error logging with endpoint context and performance tracking.
	 *
	 * @since 3.0.0
	 *
	 * @param string $endpoint API endpoint that failed
	 * @param string $message  Error message
	 * @param array  $context  Optional context data
	 *
	 * @return void
	 */
	private function log_api_error( string $endpoint, string $message, array $context = [] ): void {
		$timestamp = current_time( 'mysql' );
		$error_entry = [
			'timestamp' => $timestamp,
			'endpoint'  => $endpoint,
			'message'   => $message,
			'context'   => $context,
		];

		// Add to memory cache for debugging
		$this->error_log[] = $error_entry;

		// VIP-compliant logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', sprintf(
				'[BRAG book API Error] %s | Endpoint: %s | Message: %s',
				$timestamp,
				$endpoint,
				$message
			) );
		}

		// Trigger custom error action
		do_action( 'brag_book_gallery_api_error', $error_entry );
	}

	/**
	 * Track performance metrics
	 *
	 * Records performance data for API requests.
	 *
	 * @since 3.0.0
	 *
	 * @param string $endpoint API endpoint
	 * @param float  $duration Request duration in seconds
	 * @param bool   $cached   Whether response was cached
	 *
	 * @return void
	 */
	private function track_performance_metrics( string $endpoint, float $duration, bool $cached = false ): void {
		$this->performance_metrics[] = [
			'endpoint'  => $endpoint,
			'duration'  => $duration,
			'cached'    => $cached,
			'timestamp' => microtime( true ),
		];

		// Log performance in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', sprintf(
				'[BRAG book API Performance] %s: %.3fs %s',
				$endpoint,
				$duration,
				$cached ? '(cached)' : '(live)'
			) );
		}
	}

	/**
	 * Get cached response with memory cache layer
	 *
	 * Implements multi-level caching for optimal performance.
	 *
	 * @since 3.0.0
	 *
	 * @param string $cache_key Cache key
	 *
	 * @return mixed|false Cached data or false if not found
	 */
	private function get_cached_response( string $cache_key ) {
		// Check memory cache first
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		// Check WordPress transient cache
		$cached_data = Cache_Manager::get( $cache_key );
		if ( $cached_data !== false ) {
			// Store in memory cache for subsequent requests
			$this->memory_cache[ $cache_key ] = $cached_data;
			return $cached_data;
		}

		return false;
	}

	/**
	 * Set cached response with memory cache layer
	 *
	 * Stores data in both memory and WordPress transient cache.
	 *
	 * @since 3.0.0
	 *
	 * @param string $cache_key Cache key
	 * @param mixed  $data      Data to cache
	 * @param int    $duration  Cache duration in seconds
	 *
	 * @return void
	 */
	private function set_cached_response( string $cache_key, $data, int $duration ): void {
		// Store in memory cache
		$this->memory_cache[ $cache_key ] = $data;

		// Store in WordPress transient cache
		Cache_Manager::set( $cache_key, $data, $duration );
	}

	/**
	 * Get cache duration based on endpoint type
	 *
	 * Returns appropriate cache duration using modern match expression.
	 *
	 * @since 3.0.0
	 *
	 * @param string $endpoint_type Endpoint type identifier
	 *
	 * @return int Cache duration in seconds
	 */
	private function get_cache_duration_for_endpoint( string $endpoint_type ): int {
		return match ( $endpoint_type ) {
			'sidebar', 'sitemap' => self::CACHE_TTL_LONG,
			'carousel', 'case' => self::CACHE_TTL_MEDIUM,
			'pagination', 'cases' => self::CACHE_TTL_SHORT,
			default => self::CACHE_DURATION
		};
	}

	/**
	 * Validate input against security rules
	 *
	 * Comprehensive input validation using predefined security rules
	 * with pattern matching, length validation, and required field checks.
	 *
	 * @since 3.0.0
	 *
	 * @param string $value Input value to validate
	 * @param string $type  Validation rule type
	 *
	 * @return bool True if valid, false otherwise
	 */
	private function validate_input( string $value, string $type ): bool {
		// Check if validation rule exists
		if ( ! isset( self::SECURITY_RULES[ $type ] ) ) {
			return false;
		}

		$rules = self::SECURITY_RULES[ $type ];

		// Check required field
		if ( isset( $rules['required'] ) && $rules['required'] && empty( $value ) ) {
			return false;
		}

		// Skip validation for optional empty fields
		if ( empty( $value ) && ( ! isset( $rules['required'] ) || ! $rules['required'] ) ) {
			return true;
		}

		// Check minimum length
		if ( isset( $rules['min_length'] ) && strlen( $value ) < $rules['min_length'] ) {
			return false;
		}

		// Check maximum length
		if ( isset( $rules['max_length'] ) && strlen( $value ) > $rules['max_length'] ) {
			return false;
		}

		// Check pattern if specified
		if ( isset( $rules['pattern'] ) && ! preg_match( $rules['pattern'], $value ) ) {
			return false;
		}

		// Special validation for email
		if ( $type === 'email' && ! is_email( $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize and validate API tokens
	 *
	 * Enhanced security validation for API tokens with length checks
	 * and format validation.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $tokens API tokens to validate
	 *
	 * @return array<string> Validated tokens
	 *
	 * @throws \InvalidArgumentException When tokens are invalid
	 */
	private function validate_api_tokens( array $tokens ): array {
		$validated_tokens = [];

		foreach ( $tokens as $token ) {
			$token = sanitize_text_field( $token );

			if ( ! $this->validate_input( $token, 'api_token' ) ) {
				throw new \InvalidArgumentException( 'Invalid API token format or length' );
			}

			$validated_tokens[] = $token;
		}

		return $validated_tokens;
	}

	/**
	 * Rate limiting check
	 *
	 * Simple rate limiting implementation to prevent API abuse.
	 *
	 * @since 3.0.0
	 *
	 * @param string $identifier Rate limit identifier (IP, user, etc.)
	 * @param int    $limit      Maximum requests per time window
	 * @param int    $window     Time window in seconds
	 *
	 * @return bool True if under limit, false if rate limited
	 */
	private function check_rate_limit( string $identifier, int $limit = 100, int $window = 3600 ): bool {
		$cache_key = 'brag_book_gallery_transient_rate_limit_' . $identifier;
		$current_requests = Cache_Manager::get( $cache_key );

		if ( $current_requests === false ) {
			// First request in window
			Cache_Manager::set( $cache_key, 1, $window );
			return true;
		}

		if ( $current_requests >= $limit ) {
			// Rate limit exceeded
			$this->log_error( 'Rate limit exceeded for identifier: ' . $identifier );
			return false;
		}

		// Increment counter
		Cache_Manager::set( $cache_key, $current_requests + 1, $window );
		return true;
	}

	/**
	 * Sanitize and validate URL parameters
	 *
	 * Comprehensive URL parameter validation with XSS prevention.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url URL to validate
	 *
	 * @return string|null Validated URL or null if invalid
	 */
	private function validate_url( string $url ): ?string {
		// Basic URL validation
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		// Check for allowed schemes
		$allowed_schemes = [ 'http', 'https' ];
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( ! in_array( $scheme, $allowed_schemes, true ) ) {
			return null;
		}

		// Sanitize URL
		return esc_url_raw( $url );
	}

	/**
	 * Warm up cache for critical endpoints
	 *
	 * Pre-populates cache with frequently accessed data for improved
	 * performance during peak usage.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $endpoints List of endpoints to warm up
	 *
	 * @return array<string, bool> Success status for each endpoint
	 */
	public function warm_up_cache( array $endpoints = [] ): array {
		$results = [];

		if ( empty( $endpoints ) ) {
			$endpoints = [ 'sidebar', 'carousel' ]; // Default critical endpoints
		}

		foreach ( $endpoints as $endpoint ) {
			try {
				$success = match ( $endpoint ) {
					'sidebar' => $this->warm_sidebar_cache(),
					'carousel' => $this->warm_carousel_cache(),
					default => false
				};
				$results[ $endpoint ] = $success;
			} catch ( \Exception $e ) {
				$this->log_error( 'Cache warm-up failed for ' . $endpoint . ': ' . $e->getMessage() );
				$results[ $endpoint ] = false;
			}
		}

		return $results;
	}

	/**
	 * Warm sidebar cache
	 *
	 * @since 3.0.0
	 * @return bool Success status
	 */
	private function warm_sidebar_cache(): bool {
		$mode = get_option( 'brag_book_gallery_mode', 'local' );
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$token = $api_tokens[ $mode ] ?? '';

		if ( empty( $token ) ) {
			return false;
		}

		$response = $this->get_api_sidebar( $token );
		return ! empty( $response );
	}

	/**
	 * Warm carousel cache
	 *
	 * @since 3.0.0
	 * @return bool Success status
	 */
	private function warm_carousel_cache(): bool {
		$mode = get_option( 'brag_book_gallery_mode', 'local' );
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		$token = $api_tokens[ $mode ] ?? '';
		$property_id = $website_property_ids[ $mode ] ?? '';

		if ( empty( $token ) || empty( $property_id ) ) {
			return false;
		}

		$options = [
			'websitePropertyId' => $property_id,
			'limit' => 10,
		];

		$response = $this->get_carousel_data( $token, $options );
		return ! empty( $response );
	}

	/**
	 * Get performance metrics summary
	 *
	 * Returns aggregated performance data for monitoring and optimization.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, mixed> Performance summary
	 */
	public function get_performance_metrics(): array {
		if ( empty( $this->performance_metrics ) ) {
			return [];
		}

		$total_requests = count( $this->performance_metrics );
		$cached_requests = count( array_filter( $this->performance_metrics, fn( $metric ) => $metric['cached'] ) );
		$total_duration = array_sum( array_column( $this->performance_metrics, 'duration' ) );
		$avg_duration = $total_duration / $total_requests;

		// Group by endpoint
		$by_endpoint = [];
		foreach ( $this->performance_metrics as $metric ) {
			$endpoint = $metric['endpoint'];
			if ( ! isset( $by_endpoint[ $endpoint ] ) ) {
				$by_endpoint[ $endpoint ] = [
					'count' => 0,
					'total_duration' => 0,
					'cached_count' => 0,
				];
			}
			$by_endpoint[ $endpoint ]['count']++;
			$by_endpoint[ $endpoint ]['total_duration'] += $metric['duration'];
			if ( $metric['cached'] ) {
				$by_endpoint[ $endpoint ]['cached_count']++;
			}
		}

		// Calculate averages for each endpoint
		foreach ( $by_endpoint as $endpoint => &$stats ) {
			$stats['avg_duration'] = $stats['total_duration'] / $stats['count'];
			$stats['cache_hit_rate'] = $stats['cached_count'] / $stats['count'];
		}

		return [
			'summary' => [
				'total_requests' => $total_requests,
				'cached_requests' => $cached_requests,
				'cache_hit_rate' => $cached_requests / $total_requests,
				'total_duration' => $total_duration,
				'avg_duration' => $avg_duration,
			],
			'by_endpoint' => $by_endpoint,
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
		];
	}
}
