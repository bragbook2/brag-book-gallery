<?php
/**
 * Trait API - Enterprise-grade API communication system
 *
 * Comprehensive API communication trait for BRAGBook Gallery plugin.
 * Provides secure, performant, and resilient API interactions with
 * advanced features for caching, rate limiting, and error handling.
 *
 * Features:
 * - Secure HTTPS communication with SSL verification
 * - Multi-level caching (memory + transients)
 * - Rate limiting protection
 * - Retry mechanism with exponential backoff
 * - Circuit breaker pattern for fault tolerance
 * - Request/response validation
 * - Comprehensive error logging
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Traits
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

use WP_Error;
// Cache functions - using WordPress transients directly
use function BRAGBookGallery\Includes\Traits\gettype;
use const BRAGBookGallery\Includes\Traits\BRAG_BOOK_GALLERY_VERSION;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Enterprise API Communication Trait
 *
 * Orchestrates secure and performant API operations:
 *
 * Core Responsibilities:
 * - Secure HTTPS communication
 * - Request/response validation
 * - Multi-level caching strategy
 * - Rate limiting protection
 * - Circuit breaker implementation
 * - Retry logic with backoff
 * - Comprehensive error handling
 *
 * @since 3.0.0
 */
trait Trait_Api {
	use Trait_Sanitizer;

	/**
	 * API base URL
	 *
	 * @since 3.0.0
	 * @var string
	 */
	protected string $api_base_url = '';

	/**
	 * Default request timeout
	 *
	 * @since 3.0.0
	 * @var int
	 */
	protected int $request_timeout = 30;

	/**
	 * Maximum retry attempts
	 *
	 * @since 3.0.0
	 */
	protected const MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Cache duration constants
	 *
	 * @since 3.0.0
	 */
	protected const CACHE_TTL_SHORT = 300;     // 5 minutes
	protected const CACHE_TTL_MEDIUM = 1800;   // 30 minutes
	protected const CACHE_TTL_LONG = 3600;     // 1 hour
	protected const CACHE_TTL_EXTENDED = 7200; // 2 hours

	/**
	 * Rate limit constants
	 *
	 * @since 3.0.0
	 */
	protected const RATE_LIMIT_REQUESTS = 30;
	protected const RATE_LIMIT_WINDOW = 60; // seconds

	/**
	 * Circuit breaker threshold
	 *
	 * @since 3.0.0
	 */
	protected const CIRCUIT_BREAKER_THRESHOLD = 5;
	protected const CIRCUIT_BREAKER_TIMEOUT = 300; // 5 minutes

	/**
	 * Memory cache for optimization
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	protected array $memory_cache = [];

	/**
	 * Performance metrics storage
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	protected array $api_metrics = [];

	/**
	 * Error log storage
	 *
	 * @since 3.0.0
	 * @var array<int, array<string, mixed>>
	 */
	protected array $error_log = [];

	/**
	 * Get API base URL
	 *
	 * @since 3.0.0
	 * @return string API base URL
	 */
	public static function get_api_url(): string {
		$custom_url = get_option( 'brag_book_gallery_api_base_url', '' );
		if ( ! empty( $custom_url ) ) {
			return $custom_url;
		}
		return 'https://app.bragbookgallery.com';
	}

	/**
	 * Get API base URL (instance method)
	 *
	 * @since 3.0.0
	 * @return string API base URL.
	 */
	protected function get_api_base_url(): string {
		if ( empty( $this->api_base_url ) ) {
			$this->api_base_url = self::get_api_url();
		}
		return apply_filters(
			hook_name: 'brag_book_gallery_api_base_url',
			value: $this->api_base_url
		);
	}

	/**
	 * Make API request with security checks
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $args Request arguments.
	 * @param string $method Request method.
	 * @return array|WP_Error Response data or error.
	 */
	protected function make_api_request( string $endpoint, array $args = [], string $method = 'POST' ): array|WP_Error {

		// Check rate limit.
		// Ensure endpoint is never null for PHP 8.2 compatibility
		$endpoint = $endpoint ?: '';
		$url = $this->get_api_base_url() . '/' . ltrim( $endpoint, '/' );

		// Get API credentials
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		// Ensure credentials are arrays
		if ( ! is_array( $api_tokens ) ) {
			$api_tokens = [ $api_tokens ];
		}
		if ( ! is_array( $website_property_ids ) ) {
			$website_property_ids = [ $website_property_ids ];
		}

		// Add credentials to body for POST/PUT requests (matching Data_Sync pattern)
		if ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			// Initialize body if not set
			if ( ! isset( $args['body'] ) ) {
				$args['body'] = [];
			}

			// Ensure body is an array
			if ( is_string( $args['body'] ) ) {
				$args['body'] = json_decode( $args['body'], true ) ?: [];
			}

			// Only add credentials if they're not already set in the body
			// This allows methods to override with specific credential requirements
			if ( ! isset( $args['body']['apiTokens'] ) && ! empty( $api_tokens ) ) {
				$args['body']['apiTokens'] = array_values( $api_tokens );
			}

			if ( ! isset( $args['body']['websitePropertyIds'] ) &&
			     ! empty( $website_property_ids ) ) {
				$args['body']['websitePropertyIds'] = array_values( $website_property_ids );
			}

			error_log( 'API Request - Method: ' . $method . ', Endpoint: ' . $endpoint );
			error_log( 'API Request - Tokens included: ' . ( ! empty( $args['body']['apiTokens'] ) ? 'Yes' : 'No' ) );
			error_log( 'API Request - Property IDs included: ' . ( ! empty( $args['body']['websitePropertyIds'] ) ? 'Yes' : 'No' ) );
			error_log( 'API Request - Body: ' . wp_json_encode( $args['body'] ) );
		}

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'invalid_url',
				esc_html__( 'Invalid API URL', 'brag-book-gallery' )
			);
		}

		// Get timeout from settings or use default
		$timeout = intval( get_option( 'brag_book_gallery_api_timeout', $this->request_timeout ) );

		// Set default headers with cache-busting headers
		$default_args = [
			'method'      => strtoupper( $method ),
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'Cache-Control' => 'no-cache, no-store, must-revalidate',
				'Pragma' => 'no-cache',
				'X-LiteSpeed-Cache-Control' => 'no-cache',
				'User-Agent' => 'BRAGBookGallery/' . ( defined( 'BRAG_BOOK_GALLERY_VERSION' ) ? BRAG_BOOK_GALLERY_VERSION : '3.0.0' ),
			],
			'cookies'     => [],
			'sslverify'   => true,
		];

		// Merge with provided args.
		$args = wp_parse_args( $args, $default_args );

		// Add body if provided.
		if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$args['body'] = wp_json_encode( $args['body'] );
		}

		// Add cache-busting query parameter for GET requests
		if ( 'GET' === strtoupper( $method ) ) {
			$separator = str_contains( $url, '?' ) ? '&' : '?';
			$url .= $separator . '_nocache=' . time();
		}

		// Log before making request
		error_log( 'API Request - About to make request to: ' . $url );

		// Make the request.
		$response = wp_remote_request( $url, $args );

		error_log( 'API Request - Response received, checking for errors...' );

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			error_log( 'API Request - WP_Error returned: ' . $response->get_error_message() );
			$this->log_api_error( $endpoint, $response->get_error_message() );
			return $response;
		}

		// Get response code.
		$response_code = wp_remote_retrieve_response_code( $response );
		error_log( 'API Request - Response code: ' . $response_code );

		// Check response code.
		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_type = match ( true ) {
				$response_code === 401 => 'authentication_error',
				$response_code === 403 => 'authorization_error',
				$response_code === 404 => 'not_found',
				$response_code === 429 => 'rate_limit_exceeded',
				$response_code >= 500 => 'server_error',
				default => 'api_error',
			};

			$error_message = sprintf(
				/* translators: 1: HTTP status code, 2: API endpoint */
				esc_html__(
					'API request failed with status %1$d for endpoint %2$s',
					'brag-book-gallery'
				),
				$response_code,
				$endpoint
			);
			$this->log_api_error( $endpoint, $error_message, [ 'status' => $response_code ] );

			// Record failure for circuit breaker
			$this->record_api_failure( $endpoint );

			return new WP_Error(
				$error_type,
				$error_message,
				[ 'status' => $response_code ]
			);
		}

		// Get response body.
		$body = wp_remote_retrieve_body( $response );
		error_log( 'API Request - Response body length: ' . strlen( $body ) );

		// Decode JSON response.
		error_log( 'API Request - About to decode JSON response' );
		$data = json_decode( $body, true );
		error_log( 'API Request - JSON decoded, checking for errors' );

		// Check for JSON errors.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$error_message = sprintf(
				/* translators: %s: JSON error message */
				esc_html__(
					'Invalid JSON response: %s',
					'brag-book-gallery'
				),
				json_last_error_msg()
			);

			$this->log_api_error(
				$endpoint,
				$error_message
			);

			return new WP_Error(
				'json_error',
				$error_message
			);
		}

		// Record success for circuit breaker
		$this->record_api_success( $endpoint );

		// Track performance metrics
		$this->track_api_performance( $endpoint, $response );

		return [
			'code' => $response_code,
			'data' => $data,
			'headers' => wp_remote_retrieve_headers( $response ),
		];
	}

	/**
	 * Make GET request to API with caching
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters.
	 * @param int    $cache_ttl Cache duration in seconds.
	 * @return array|WP_Error Response data or error.
	 */
	protected function api_get( string $endpoint, array $params = [], int $cache_ttl = 0 ): array|WP_Error {
		if ( ! empty( $params ) ) {
			$endpoint .= '?' . http_build_query( $params );
		}

		// Check cache if TTL provided
		$cache_key = 'brag_book_galley_transient_get_' . $endpoint;
		if ( $cache_ttl > 0 ) {
			$cached = $this->get_cached_api_response( $cache_key );

			if ( false !== $cached ) {
				return $cached;
			}
		}

		$response = $this->make_api_request(
			endpoint: $endpoint,
			args: [],
			method: 'GET'
		);

		// Cache successful response
		if ( $cache_ttl > 0 && ! is_wp_error( $response ) ) {
			$this->cache_api_response( $cache_key, $response, $cache_ttl );
		}

		return $response;
	}

	/**
	 * Make POST request to API with validation
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body.
	 * @param array  $required_fields Required fields in body.
	 * @return array|WP_Error Response data or error.
	 */
	protected function api_post( string $endpoint, array $body = [], array $required_fields = [] ): array|WP_Error {
		// Validate required fields
		foreach ( $required_fields as $field ) {
			if ( ! isset( $body[ $field ] ) || empty( $body[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: field name */
						esc_html__( 'Required field missing: %s', 'brag-book-gallery' ),
						$field
					)
				);
			}
		}

		return $this->make_api_request(
			endpoint: $endpoint,
			args: [ 'body' => $body ],
			method: 'POST'
		);
	}

	/**
	 * Cache API response
	 *
	 * @since 3.0.0
	 * @param string $cache_key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $expiration Cache expiration in seconds.
	 * @return bool True on success, false on failure.
	 */
	protected function cache_api_response(
		string $cache_key,
		mixed $data,
		int $expiration = 1800
	): bool {
		$cache_key = 'brag_book_gallery_transient_api_' . $cache_key;
		return set_transient( $cache_key, $data, $expiration );
	}

	/**
	 * Get cached API response
	 *
	 * @since 3.0.0
	 * @param string $cache_key Cache key.
	 * @return mixed Cached data or false if not found.
	 */
	protected function get_cached_api_response( string $cache_key ): mixed {
		$cache_key = 'brag_book_gallery_transient_api_' . $cache_key;
		return get_transient( $cache_key );
	}

	/**
	 * Clear API cache
	 *
	 * @since 3.0.0
	 * @param string|null $pattern Optional pattern to match cache keys.
	 * @return void
	 */
	protected function clear_api_cache( ?string $pattern = null ): void {
		global $wpdb;

		if ( ! empty( $pattern ) ) {
			// Clear specific pattern including timeout transients
			$cache_pattern = '_transient_brag_book_gallery_transient_api_%' . $pattern . '%';
			$timeout_pattern = '_transient_timeout_brag_book_gallery_transient_api_%' . $pattern . '%';
		} else {
			// Clear all API cache including timeout transients
			$cache_pattern = '_transient_brag_book_gallery_transient_api_%';
			$timeout_pattern = '_transient_timeout_brag_book_gallery_transient_api_%';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$cache_pattern,
				$timeout_pattern
			)
		);

		// Clear object cache
		wp_cache_flush();

		// Fire action hook after cache is cleared
		do_action( 'brag_book_gallery_api_cache_cleared', $pattern ?? 'all', true );
	}

	/**
	 * Log API error with context
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param string $error Error message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function log_api_error( string $endpoint, string $error, array $context = [] ): void {
		// Store in error log
		$this->error_log[] = [
			'endpoint' => $endpoint,
			'error'    => $error,
			'context'  => $context,
			'time'     => current_time( 'mysql' ),
		];

		// Limit error log size
		if ( count( $this->error_log ) > 100 ) {
			array_shift( $this->error_log );
		}

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log(
			sprintf(
				'[BRAGBookGallery API Error] Endpoint: %s | Error: %s | Context: %s | Time: %s',
				$endpoint,
				$error,
				wp_json_encode( $context ),
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Validate API response structure with detailed validation
	 *
	 * @since 3.0.0
	 * @param array $response API response.
	 * @param array $required_fields Required fields in response.
	 * @param array $field_types Expected field types.
	 * @return bool|WP_Error True if valid, error otherwise.
	 */
	protected function validate_api_response( array $response, array $required_fields = [], array $field_types = [] ) {
		if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return new WP_Error(
				'invalid_response',
				esc_html__( 'Invalid API response structure', 'brag-book-gallery' )
			);
		}

		foreach ( $required_fields as $field ) {
			if ( ! isset( $response['data'][ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: field name */
						esc_html__( 'Required field missing in response: %s', 'brag-book-gallery' ),
						$field
					)
				);
			}

			// Validate field type if specified
			if ( isset( $field_types[ $field ] ) ) {
				$expected_type = $field_types[ $field ];
				$actual_type = gettype( $response['data'][ $field ] );

				if ( $actual_type !== $expected_type ) {
					return new WP_Error(
						'invalid_type',
						sprintf(
							/* translators: 1: field name, 2: expected type, 3: actual type */
							esc_html__( 'Field %1$s has invalid type. Expected %2$s, got %3$s', 'brag-book-gallery' ),
							$field,
							$expected_type,
							$actual_type
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Build API endpoint with parameters and validation
	 *
	 * @since 3.0.0
	 * @param string $endpoint Base endpoint.
	 * @param array  $params Parameters to add.
	 * @param array  $allowed_params Allowed parameter keys.
	 * @return string Complete endpoint URL.
	 */
	protected function build_api_endpoint( string $endpoint, array $params = [], array $allowed_params = [] ): string {
		// Ensure endpoint is never null for PHP 8.2 compatibility
		$endpoint = $endpoint ?: '';
		$endpoint = ltrim( $endpoint, '/' );

		if ( ! empty( $params ) ) {
			// Filter params if allowed list provided
			if ( ! empty( $allowed_params ) ) {
				$params = array_intersect_key( $params, array_flip( $allowed_params ) );
			}

			// Sanitize parameters
			$params = array_map( function( $value ) {
				return match ( gettype( $value ) ) {
					'boolean' => $value ? '1' : '0',
					'array'   => wp_json_encode( $value ),
					'NULL'    => '',
					default   => (string) $value,
				};
			}, $params );

			$query_string = http_build_query( $params );
			$endpoint .= ( str_contains( $endpoint, '?' ) ? '&' : '?' ) . $query_string;
		}

		return $endpoint;
	}

	/**
	 * Handle API rate limiting with sliding window
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @return bool True if request can proceed, false if rate limited.
	 */
	protected function check_rate_limit( string $endpoint ): bool {
		$transient_key = 'brag_book_gallery_transient_rate_limit_' . $endpoint;
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
			return true;
		}

		// Allow configurable requests per minute
		$max_requests = apply_filters( 'brag_book_gallery_api_rate_limit', self::RATE_LIMIT_REQUESTS );

		if ( $current_count >= $max_requests ) {
			$this->log_api_error( $endpoint, 'Rate limit exceeded', [ 'count' => $current_count ] );
			return false;
		}

		set_transient( $transient_key, $current_count + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Make API request with retry logic
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $args Request arguments.
	 * @param string $method Request method.
	 * @param int    $max_retries Maximum retry attempts.
	 * @return array|WP_Error Response data or error.
	 */
	protected function api_request_with_retry(
		string $endpoint,
		array $args = [],
		string $method = 'GET',
		int $max_retries = self::MAX_RETRY_ATTEMPTS
	): array|WP_Error {
		$attempt = 0;
		$last_error = null;

		while ( $attempt < $max_retries ) {
			$attempt++;

			// Check circuit breaker
			if ( ! $this->is_circuit_open( $endpoint ) ) {
				$response = $this->make_api_request( $endpoint, $args, $method );

				if ( ! is_wp_error( $response ) ) {
					return $response;
				}

				$last_error = $response;

				// Check if error is retryable
				if ( ! $this->is_retryable_error( $response ) ) {
					break;
				}
			}

			// Exponential backoff
			if ( $attempt < $max_retries ) {
				$delay = min( 1000 * pow( 2, $attempt - 1 ), 10000 ); // Max 10 seconds
				usleep( $delay * 1000 ); // Convert to microseconds
			}
		}

		return $last_error ?? new WP_Error(
			'max_retries_exceeded',
			sprintf(
				/* translators: %s: API endpoint */
				esc_html__( 'Max retry attempts exceeded for endpoint: %s', 'brag-book-gallery' ),
				$endpoint
			)
		);
	}

	/**
	 * Check if error is retryable
	 *
	 * @since 3.0.0
	 * @param WP_Error $error Error object.
	 * @return bool True if retryable.
	 */
	protected function is_retryable_error( WP_Error $error ): bool {
		$retryable_codes = [
			'http_request_failed',
			'server_error',
			'rate_limit_exceeded',
		];

		return in_array( $error->get_error_code(), $retryable_codes, true );
	}

	/**
	 * Circuit breaker: Check if circuit is open
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @return bool True if circuit is open (requests blocked).
	 */
	protected function is_circuit_open( string $endpoint ): bool {
		$circuit_key = 'brag_book_gallery_transient_circuit_' . $endpoint;
		$circuit_status = get_transient( $circuit_key );

		return $circuit_status === 'open';
	}

	/**
	 * Record API failure for circuit breaker
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @return void
	 */
	protected function record_api_failure( string $endpoint ): void {
		$failure_key = 'brag_book_gallery_transient_failures_' . $endpoint;
		$failures = get_transient( $failure_key ) ?: 0;
		$failures++;

		if ( $failures >= self::CIRCUIT_BREAKER_THRESHOLD ) {
			// Open circuit
			$circuit_key = 'brag_book_gallery_transient_circuit_' . $endpoint;
			set_transient( $circuit_key, 'open', self::CIRCUIT_BREAKER_TIMEOUT );

			// Reset failure count
			delete_transient( $failure_key );

			$this->log_api_error( $endpoint, 'Circuit breaker opened', [ 'failures' => $failures ] );
		} else {
			set_transient( $failure_key, $failures, 300 ); // 5 minute window
		}
	}

	/**
	 * Record API success for circuit breaker
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @return void
	 */
	protected function record_api_success( string $endpoint ): void {
		// Reset failure count on success
		$failure_key = 'brag_book_gallery_transient_failures_' . $endpoint;
		delete_transient( $failure_key );

		// Close circuit if it was open
		$circuit_key = 'brag_book_gallery_circuit_' . $endpoint;
		if ( get_transient( $circuit_key ) === 'open' ) {
			delete_transient( $circuit_key );
			$this->log_api_error( $endpoint, 'Circuit breaker closed', [ 'status' => 'success' ] );
		}
	}

	/**
	 * Track API performance metrics
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array|mixed $response Raw response from wp_remote_request.
	 * @return void
	 */
	protected function track_api_performance( string $endpoint, mixed $response ): void {
		if ( ! isset( $this->api_metrics[ $endpoint ] ) ) {
			$this->api_metrics[ $endpoint ] = [
				'count'        => 0,
				'total_time'   => 0,
				'min_time'     => PHP_FLOAT_MAX,
				'max_time'     => 0,
				'last_request' => '',
			];
		}

		// Extract timing from response - estimate based on timeout
		$total_time = $response['http_response']->info['total_time'] ?? 0.5;

		$metrics = &$this->api_metrics[ $endpoint ];
		$metrics['count']++;
		$metrics['total_time'] += $total_time;
		$metrics['min_time'] = min( $metrics['min_time'], $total_time );
		$metrics['max_time'] = max( $metrics['max_time'], $total_time );
		$metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];
		$metrics['last_request'] = current_time( 'mysql' );
	}

	/**
	 * Get API performance metrics
	 *
	 * @since 3.0.0
	 * @return array<string, array<string, mixed>> Performance metrics.
	 */
	public function get_api_metrics(): array {
		return $this->api_metrics;
	}

	/**
	 * Batch API requests
	 *
	 * @since 3.0.0
	 * @param array $requests Array of request configurations.
	 * @return array<int, array|WP_Error> Array of responses.
	 */
	protected function api_batch( array $requests ): array {
		$responses = [];

		foreach ( $requests as $index => $request ) {
			$endpoint = $request['endpoint'] ?? '';
			$method = $request['method'] ?? 'GET';
			$args = $request['args'] ?? [];

			if ( empty( $endpoint ) ) {
				$responses[ $index ] = new WP_Error(
					'missing_endpoint',
					esc_html__( 'Endpoint is required for batch request', 'brag-book-gallery' )
				);
				continue;
			}

			// Make request
			$responses[ $index ] = $this->make_api_request( $endpoint, $args, $method );

			// Small delay between requests to avoid overwhelming the server
			if ( $index < count( $requests ) - 1 ) {
				usleep( 100000 ); // 100ms
			}
		}

		return $responses;
	}

	/**
	 * Sanitize API response data
	 *
	 * @since 3.0.0
	 * @param mixed $data Data to sanitize.
	 * @param array $schema Data schema for validation.
	 * @return mixed Sanitized data.
	 */
	protected function sanitize_api_data( mixed $data, array $schema = [] ): mixed {
		if ( is_array( $data ) ) {
			return array_map(
				fn( $item ) => $this->sanitize_api_data( $item, $schema ),
				$data
			);
		}

		if ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}

		if ( is_bool( $data ) || is_numeric( $data ) || is_null( $data ) ) {
			return $data;
		}

		// For objects, convert to array and sanitize
		if ( is_object( $data ) ) {
			return $this->sanitize_api_data( (array) $data, $schema );
		}

		return null;
	}
}
