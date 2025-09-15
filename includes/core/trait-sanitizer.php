<?php
/**
 * Trait Sanitizer - Enterprise-grade sanitization and validation system
 *
 * Comprehensive sanitization and validation trait for BRAGBook Gallery plugin.
 * Provides secure, performant, and extensible data handling with advanced
 * features for input validation, output escaping, and security hardening.
 *
 * Features:
 * - Type-safe sanitization for all data types
 * - Context-aware validation rules
 * - XSS and SQL injection prevention
 * - Performance-optimized with caching
 * - Extensible filter system
 * - Comprehensive error reporting
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

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Core;

use DateTime;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Enterprise Sanitization and Validation Trait
 *
 * Orchestrates comprehensive data security operations:
 *
 * Core Responsibilities:
 * - Input sanitization and validation
 * - Output escaping and encoding
 * - Type safety enforcement
 * - Security hardening
 * - Performance optimization
 * - Error tracking and reporting
 *
 * @since 3.0.0
 */
trait Trait_Sanitizer {

	/**
	 * Validation rule constants
	 *
	 * @since 3.0.0
	 */
	protected const MAX_STRING_LENGTH = 5000;
	protected const MAX_TEXT_LENGTH = 65535;
	protected const MAX_SLUG_LENGTH = 200;
	protected const MAX_EMAIL_LENGTH = 254;
	protected const MAX_URL_LENGTH = 2083;
	protected const MAX_PHONE_LENGTH = 20;

	/**
	 * Security constants
	 *
	 * @since 3.0.0
	 */
	protected const ALLOWED_PROTOCOLS = ['http', 'https', 'mailto', 'tel'];
	protected const DANGEROUS_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form'];

	/**
	 * Performance cache
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	protected array $sanitization_cache = [];

	/**
	 * Validation errors
	 *
	 * @since 3.0.0
	 * @var array<string, string[]>
	 */
	protected array $validation_errors = [];

	/**
	 * Performance metrics
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	protected array $sanitization_metrics = [];

	/**
	 * Sanitize and validate API token (UUID v4 format)
	 *
	 * @param mixed $token Token to validate.
	 *
	 * @return string|false Sanitized token or false if invalid.
	 * @since 3.0.0
	 */
	protected function sanitize_api_token( mixed $token ): string|false {
		if ( ! is_string( $token ) ) {
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $token ) );

		// UUID v4 format validation
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

		return preg_match( $pattern, $token ) ? $token : false;
	}

	/**
	 * Sanitize array of API tokens with validation
	 *
	 * @param array $tokens Array of tokens to sanitize.
	 * @param int   $max_tokens Maximum allowed tokens.
	 *
	 * @return array Sanitized tokens array.
	 * @since 3.0.0
	 */
	protected function sanitize_api_tokens( array $tokens, int $max_tokens = 10 ): array {
		$start_time = microtime( true );

		// Limit array size for security
		$tokens = array_slice( $tokens, 0, $max_tokens );

		$sanitized = array_filter(
			array_map( fn( $token ) => $this->sanitize_api_token( $token ), $tokens ),
			fn( $token ) => $token !== false
		);

		$this->track_sanitization_performance( 'api_tokens', microtime( true ) - $start_time );

		return array_values( $sanitized );
	}

	/**
	 * Sanitize integer ID
	 *
	 * @param mixed $id ID to sanitize.
	 * @param int $min Minimum allowed value.
	 *
	 * @return int|false Sanitized ID or false if invalid.
	 * @since 3.0.0
	 */
	protected function sanitize_id( mixed $id, int $min = 1 ): int|false {
		$id = filter_var( $id, FILTER_VALIDATE_INT );

		if ( false === $id || $id < $min ) {
			return false;
		}

		return $id;
	}

	/**
	 * Sanitize array of IDs with validation
	 *
	 * @param array $ids Array of IDs to sanitize.
	 * @param int   $min Minimum allowed value.
	 * @param int   $max Maximum allowed value.
	 * @param int   $max_count Maximum number of IDs.
	 *
	 * @return array Sanitized IDs array.
	 * @since 3.0.0
	 */
	protected function sanitize_ids( array $ids, int $min = 1, int $max = PHP_INT_MAX, int $max_count = 1000 ): array {
		$start_time = microtime( true );

		// Limit array size for security
		$ids = array_slice( $ids, 0, $max_count );

		$sanitized = array_unique(
			array_filter(
				array_map( fn( $id ) => $this->sanitize_id( $id, $min ), $ids ),
				fn( $id ) => $id !== false && $id <= $max
			)
		);

		$this->track_sanitization_performance( 'ids', microtime( true ) - $start_time );

		return array_values( $sanitized );
	}

	/**
	 * Sanitize email address
	 *
	 * @param mixed $email Email to sanitize.
	 *
	 * @return string|false Sanitized email or false if invalid.
	 * @since 3.0.0
	 */
	protected function sanitize_email( mixed $email ): string|false {
		if ( ! is_string( $email ) ) {
			return false;
		}

		$email = sanitize_email( wp_unslash( $email ) );

		return is_email( $email ) ? $email : false;
	}

	/**
	 * Sanitize phone number with international support
	 *
	 * @param mixed  $phone Phone number to sanitize.
	 * @param string $format Output format ('e164', 'international', 'national').
	 *
	 * @return string Sanitized phone number.
	 * @since 3.0.0
	 */
	protected function sanitize_phone( mixed $phone, string $format = 'international' ): string {
		if ( ! is_string( $phone ) && ! is_numeric( $phone ) ) {
			return '';
		}

		// Remove all non-digit characters except + for international
		$phone = preg_replace( '/[^\d+]/', '', wp_unslash( (string) $phone ) );

		// Validate phone number length (7-15 digits)
		$digits_only = preg_replace( '/[^\d]/', '', $phone );
		if ( strlen( $digits_only ) < 7 || strlen( $digits_only ) > 15 ) {
			$this->add_validation_error( 'phone', 'Invalid phone number length' );
			return '';
		}

		// Format based on type
		$formatted = match ( $format ) {
			'e164' => '+' . $digits_only,
			'national' => $this->format_national_phone( $digits_only ),
			default => substr( $phone, 0, self::MAX_PHONE_LENGTH ),
		};

		return $formatted;
	}

	/**
	 * Sanitize URL with validation
	 *
	 * @param mixed $url URL to sanitize.
	 * @param array $protocols Allowed protocols.
	 * @param bool  $validate Perform URL validation.
	 *
	 * @return string Sanitized URL.
	 * @since 3.0.0
	 */
	protected function sanitize_url(
		mixed $url,
		array $protocols = self::ALLOWED_PROTOCOLS,
		bool $validate = true
	): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url = substr( wp_unslash( $url ), 0, self::MAX_URL_LENGTH );
		$sanitized = esc_url_raw( $url, $protocols );

		if ( $validate && ! empty( $sanitized ) ) {
			if ( ! filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
				$this->add_validation_error( 'url', 'Invalid URL format' );
				return '';
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize HTML content with security filtering
	 *
	 * @param mixed  $content Content to sanitize.
	 * @param array  $allowed_tags Allowed HTML tags.
	 * @param string $context Context for sanitization.
	 *
	 * @return string Sanitized content.
	 * @since 3.0.0
	 */
	protected function sanitize_html( mixed $content, array $allowed_tags = [], string $context = 'post' ): string {
		if ( ! is_string( $content ) ) {
			return '';
		}

		$start_time = microtime( true );

		// Get allowed tags based on context
		if ( empty( $allowed_tags ) ) {
			$allowed_tags = match ( $context ) {
				'comment' => wp_kses_allowed_html( 'comment' ),
				'data'    => wp_kses_allowed_html( 'data' ),
				'strip'   => [],
				default   => wp_kses_allowed_html( 'post' ),
			};
		}

		// Remove dangerous tags
		foreach ( self::DANGEROUS_TAGS as $tag ) {
			unset( $allowed_tags[ $tag ] );
		}

		$sanitized = wp_kses( wp_unslash( $content ), $allowed_tags );

		$this->track_sanitization_performance( 'html', microtime( true ) - $start_time );

		return $sanitized;
	}

	/**
	 * Sanitize text field
	 *
	 * @param mixed $text Text to sanitize.
	 * @param int $max_length Maximum allowed length.
	 *
	 * @return string Sanitized text.
	 * @since 3.0.0
	 */
	protected function sanitize_text( mixed $text, int $max_length = 200 ): string {
		if ( ! is_string( $text ) ) {
			return '';
		}

		$text = sanitize_text_field( wp_unslash( $text ) );

		return substr( $text, 0, $max_length );
	}

	/**
	 * Sanitize textarea content
	 *
	 * @param mixed $content Content to sanitize.
	 * @param int $max_length Maximum allowed length.
	 *
	 * @return string Sanitized content.
	 * @since 3.0.0
	 */
	protected function sanitize_textarea( mixed $content, int $max_length = 5000 ): string {
		if ( ! is_string( $content ) ) {
			return '';
		}

		$content = sanitize_textarea_field( wp_unslash( $content ) );

		return substr( $content, 0, $max_length );
	}

	/**
	 * Sanitize slug
	 *
	 * @param mixed $slug Slug to sanitize.
	 *
	 * @return string Sanitized slug.
	 * @since 3.0.0
	 */
	protected function sanitize_slug( mixed $slug ): string {
		if ( ! is_string( $slug ) ) {
			return '';
		}

		return sanitize_title( wp_unslash( $slug ) );
	}

	/**
	 * Sanitize array recursively
	 *
	 * @param array $array Array to sanitize.
	 * @param string $type Sanitization type.
	 *
	 * @return array Sanitized array.
	 * @since 3.0.0
	 */
	protected function sanitize_array( array $array, string $type = 'text' ): array {
		$sanitized = array();

		foreach ( $array as $key => $value ) {
			$clean_key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = $this->sanitize_array( $value, $type );
			} else {
				$sanitized[ $clean_key ] = match ( $type ) {
					'email' => $this->sanitize_email( $value ),
					'url' => $this->sanitize_url( $value ),
					'int' => $this->sanitize_id( $value ),
					'slug' => $this->sanitize_slug( $value ),
					'html' => $this->sanitize_html( $value ),
					'textarea' => $this->sanitize_textarea( $value ),
					default => $this->sanitize_text( $value ),
				};
			}
		}

		return $sanitized;
	}

	/**
	 * Validate nonce with detailed checking
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 *
	 * @return bool|int True (1) if valid and generated 0-12 hours ago,
	 *                  2 if valid and generated 12-24 hours ago,
	 *                  false if invalid.
	 * @since 3.0.0
	 */
	protected function validate_nonce( string $nonce, string $action ): bool|int {
		$result = wp_verify_nonce( $nonce, $action );

		if ( $result === false ) {
			$this->add_validation_error( 'nonce', 'Invalid or expired nonce' );
		}

		return $result;
	}

	/**
	 * Validate request method
	 *
	 * @param string $method Expected request method.
	 *
	 * @return bool True if method matches, false otherwise.
	 * @since 3.0.0
	 */
	protected function validate_request_method( string $method ): bool {
		return isset( $_SERVER['REQUEST_METHOD'] ) &&
		       sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) === strtoupper( $method );
	}

	/**
	 * Validate user capability
	 *
	 * @param string $capability Required capability.
	 * @param int|null $user_id User ID to check.
	 *
	 * @return bool True if user has capability, false otherwise.
	 * @since 3.0.0
	 */
	protected function validate_capability( string $capability = 'manage_options', ?int $user_id = null ): bool {
		if ( null === $user_id ) {
			return current_user_can( $capability );
		}

		return user_can( $user_id, $capability );
	}

	/**
	 * Validate AJAX request with comprehensive checks
	 *
	 * @param string $nonce_action Nonce action to verify.
	 * @param string $capability Required capability.
	 * @param array  $required_params Required request parameters.
	 *
	 * @return bool|\WP_Error True if valid, WP_Error with details otherwise.
	 * @since 3.0.0
	 */
	protected function validate_ajax_request(
		string $nonce_action,
		string $capability = 'manage_options',
		array $required_params = []
	): bool|\WP_Error {
		// Check if AJAX request
		if ( ! wp_doing_ajax() ) {
			return new \WP_Error( 'not_ajax', 'Not an AJAX request' );
		}

		// Check nonce
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! $this->validate_nonce( $nonce, $nonce_action ) ) {
			return new \WP_Error( 'invalid_nonce', 'Security verification failed' );
		}

		// Check capability
		if ( ! $this->validate_capability( $capability ) ) {
			return new \WP_Error( 'insufficient_permissions', 'Insufficient permissions' );
		}

		// Check required parameters
		foreach ( $required_params as $param ) {
			if ( ! isset( $_REQUEST[ $param ] ) || empty( $_REQUEST[ $param ] ) ) {
				return new \WP_Error(
					'missing_parameter',
					sprintf( 'Required parameter missing: %s', $param )
				);
			}
		}

		return true;
	}

	/**
	 * Add validation error
	 *
	 * @since 3.0.0
	 * @param string $field Field name.
	 * @param string $message Error message.
	 * @return void
	 */
	protected function add_validation_error( string $field, string $message ): void {
		if ( ! isset( $this->validation_errors[ $field ] ) ) {
			$this->validation_errors[ $field ] = [];
		}

		$this->validation_errors[ $field ][] = $message;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[BRAGBook Sanitizer] Validation error for %s: %s', $field, $message ) );
		}
	}

	/**
	 * Get validation errors
	 *
	 * @since 3.0.0
	 * @return array<string, string[]> Validation errors.
	 */
	public function get_validation_errors(): array {
		return $this->validation_errors;
	}

	/**
	 * Clear validation errors
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function clear_validation_errors(): void {
		$this->validation_errors = [];
	}

	/**
	 * Format national phone number (US)
	 *
	 * @since 3.0.0
	 * @param string $digits Phone digits.
	 * @return string Formatted phone.
	 */
	protected function format_national_phone( string $digits ): string {
		if ( strlen( $digits ) === 10 ) {
			return sprintf(
				'(%s) %s-%s',
				substr( $digits, 0, 3 ),
				substr( $digits, 3, 3 ),
				substr( $digits, 6, 4 )
			);
		}

		return $digits;
	}

	/**
	 * Track sanitization performance
	 *
	 * @since 3.0.0
	 * @param string $operation Operation name.
	 * @param float  $duration Operation duration.
	 * @return void
	 */
	protected function track_sanitization_performance( string $operation, float $duration ): void {
		if ( ! isset( $this->sanitization_metrics[ $operation ] ) ) {
			$this->sanitization_metrics[ $operation ] = [
				'count'   => 0,
				'total'   => 0,
				'min'     => PHP_FLOAT_MAX,
				'max'     => 0,
			];
		}

		$metrics = &$this->sanitization_metrics[ $operation ];
		$metrics['count']++;
		$metrics['total'] += $duration;
		$metrics['min'] = min( $metrics['min'], $duration );
		$metrics['max'] = max( $metrics['max'], $duration );
		$metrics['average'] = $metrics['total'] / $metrics['count'];
	}

	/**
	 * Sanitize file upload
	 *
	 * @since 3.0.0
	 * @param array  $file File data from $_FILES.
	 * @param array  $allowed_types Allowed MIME types.
	 * @param int    $max_size Maximum file size in bytes.
	 * @return array|\WP_Error Sanitized file data or error.
	 */
	protected function sanitize_file_upload(
		array $file,
		array $allowed_types = ['image/jpeg', 'image/png', 'image/gif'],
		int $max_size = 5242880 // 5MB
	): array|\WP_Error {
		// Check for upload errors
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error(
				'upload_error',
				$this->get_upload_error_message( $file['error'] )
			);
		}

		// Validate file size
		if ( $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				sprintf( 'File size exceeds maximum allowed size of %s', size_format( $max_size ) )
			);
		}

		// Validate MIME type
		$file_type = wp_check_filetype( $file['name'] );
		if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
			return new \WP_Error(
				'invalid_file_type',
				sprintf( 'File type %s is not allowed', $file_type['type'] )
			);
		}

		return [
			'name'     => sanitize_file_name( $file['name'] ),
			'type'     => $file_type['type'],
			'tmp_name' => $file['tmp_name'],
			'size'     => $file['size'],
		];
	}

	/**
	 * Get upload error message
	 *
	 * @since 3.0.0
	 * @param int $error_code Upload error code.
	 * @return string Error message.
	 */
	protected function get_upload_error_message( int $error_code ): string {
		return match ( $error_code ) {
			UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive',
			UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE form directive',
			UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
			UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
			default               => 'Unknown upload error',
		};
	}

	/**
	 * Sanitize JSON string
	 *
	 * @since 3.0.0
	 * @param mixed $json JSON string or data.
	 * @param int   $depth Maximum depth.
	 * @return array|false Decoded and sanitized array or false on error.
	 */
	protected function sanitize_json( mixed $json, int $depth = 512 ): array|false {
		if ( is_array( $json ) ) {
			return $this->sanitize_array( $json );
		}

		if ( ! is_string( $json ) ) {
			return false;
		}

		$decoded = json_decode( wp_unslash( $json ), true, $depth );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->add_validation_error( 'json', json_last_error_msg() );
			return false;
		}

		return $this->sanitize_array( $decoded );
	}

	/**
	 * Sanitize and validate date
	 *
	 * @since 3.0.0
	 * @param mixed  $date Date string.
	 * @param string $format Expected format.
	 * @return string|false Sanitized date or false if invalid.
	 */
	protected function sanitize_date( mixed $date, string $format = 'Y-m-d' ): string|false {
		if ( ! is_string( $date ) ) {
			return false;
		}

		$date = sanitize_text_field( wp_unslash( $date ) );
		$parsed = DateTime::createFromFormat( $format, $date );

		if ( ! $parsed || $parsed->format( $format ) !== $date ) {
			$this->add_validation_error( 'date', 'Invalid date format' );
			return false;
		}

		return $date;
	}
}
