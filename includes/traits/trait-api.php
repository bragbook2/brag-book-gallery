<?php
/**
 * Trait API - Handles API communications
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Traits
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Traits;

use WP_Error;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Trait API
 *
 * Provides secure API communication methods
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
	protected string $api_base_url = 'https://app.bragbookgallery.com';

	/**
	 * Default request timeout
	 *
	 * @since 3.0.0
	 * @var int
	 */
	protected int $request_timeout = 30;

	/**
	 * Get API base URL
	 *
	 * @since 3.0.0
	 * @return string API base URL
	 */
	public static function get_api_url(): string {
		return 'https://app.bragbookgallery.com';
	}

	/**
	 * Get API base URL (instance method)
	 *
	 * @since 3.0.0
	 * @return string API base URL.
	 */
	protected function get_api_base_url(): string {
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

		// Validate URL.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'invalid_url',
				esc_html__( 'Invalid API URL', 'brag-book-gallery' )
			);
		}

		// Get timeout from settings or use default
		$timeout = intval( get_option( 'brag_book_gallery_api_timeout', $this->request_timeout ) );
		
		// Set default headers.
		$default_args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'cookies'     => array(),
			'sslverify'   => true,
		);

		// Merge with provided args.
		$args = wp_parse_args( $args, $default_args );

		// Add body if provided.
		if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$args['body'] = wp_json_encode( $args['body'] );
		}

		// Make the request.
		$response = wp_remote_request( $url, $args );

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			$this->log_api_error( $endpoint, $response->get_error_message() );
			return $response;
		}

		// Get response code.
		$response_code = wp_remote_retrieve_response_code( $response );

		// Check response code.
		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = sprintf(
				/* translators: 1: HTTP status code, 2: API endpoint */
				esc_html__(
					'API request failed with status %1$d for endpoint %2$s',
					'brag-book-gallery'
				),
				$response_code,
				$endpoint
			);
			$this->log_api_error( $endpoint, $error_message );
			return new WP_Error(
				'api_error',
				$error_message,
				array( 'status' => $response_code )
			);
		}

		// Get response body.
		$body = wp_remote_retrieve_body( $response );

		// Decode JSON response.
		$data = json_decode( $body, true );

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

		return array(
			'code' => $response_code,
			'data' => $data,
			'headers' => wp_remote_retrieve_headers( $response ),
		);
	}

	/**
	 * Make GET request to API
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $params Query parameters.
	 * @return array|WP_Error Response data or error.
	 */
	protected function api_get( string $endpoint, array $params = array() ): array|WP_Error {
		if ( ! empty( $params ) ) {
			$endpoint .= '?' . http_build_query( $params );
		}

		return $this->make_api_request(
			endpoint: $endpoint,
			args: array(),
			method: 'GET'
		);
	}

	/**
	 * Make POST request to API
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body.
	 * @return array|WP_Error Response data or error.
	 */
	protected function api_post( string $endpoint, array $body = array() ): array|WP_Error {
		return $this->make_api_request(
			endpoint: $endpoint,
			args: array( 'body' => $body ),
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
		$cache_key = 'brag_book_gallery_api_' . md5( $cache_key );
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
		$cache_key = 'brag_book_gallery_api_' . md5( $cache_key );
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
			$cache_pattern = '_transient_brag_book_gallery_api_%' . $pattern . '%';
			$timeout_pattern = '_transient_timeout_brag_book_gallery_api_%' . $pattern . '%';
		} else {
			// Clear all API cache including timeout transients
			$cache_pattern = '_transient_brag_book_gallery_api_%';
			$timeout_pattern = '_transient_timeout_brag_book_gallery_api_%';
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
	 * Log API error
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @param string $error Error message.
	 * @return void
	 */
	protected function log_api_error( string $endpoint, string $error ): void {
		if ( ! defined( constant_name: 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log(
			sprintf(
				'[BRAGBookGallery API Error] Endpoint: %s | Error: %s | Time: %s',
				$endpoint,
				$error,
				current_time( type: 'mysql' )
			)
		);
	}

	/**
	 * Validate API response structure
	 *
	 * @since 3.0.0
	 * @param array $response API response.
	 * @param array $required_fields Required fields in response.
	 * @return bool True if valid, false otherwise.
	 */
	protected function validate_api_response( array $response, array $required_fields = array() ): bool {
		if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return false;
		}

		foreach ( $required_fields as $field ) {
			if ( ! isset( $response['data'][ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build API endpoint with parameters
	 *
	 * @since 3.0.0
	 * @param string $endpoint Base endpoint.
	 * @param array  $params Parameters to add.
	 * @return string Complete endpoint URL.
	 */
	protected function build_api_endpoint( string $endpoint, array $params = array() ): string {
		// Ensure endpoint is never null for PHP 8.2 compatibility
		$endpoint = $endpoint ?: '';
		$endpoint = ltrim( $endpoint, '/' );

		if ( ! empty( $params ) ) {
			$query_string = http_build_query( $params );
			$endpoint .= ( str_contains( $endpoint, '?' ) ? '&' : '?' ) . $query_string;
		}

		return $endpoint;
	}

	/**
	 * Handle API rate limiting
	 *
	 * @since 3.0.0
	 * @param string $endpoint API endpoint.
	 * @return bool True if request can proceed, false if rate limited.
	 */
	protected function check_rate_limit( string $endpoint ): bool {
		$transient_key = 'brag_book_gallery_rate_limit_' . md5( $endpoint );
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		// Allow 30 requests per minute
		$max_requests = apply_filters( 'brag_book_gallery_api_rate_limit', 30 );

		if ( $current_count >= $max_requests ) {
			$this->log_api_error( $endpoint, 'Rate limit exceeded' );
			return false;
		}

		set_transient( $transient_key, $current_count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
