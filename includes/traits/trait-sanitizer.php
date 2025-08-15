<?php
/**
 * Trait Sanitizer - Security-focused sanitization and validation
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Traits
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Traits;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Trait Sanitizer
 *
 * Provides secure sanitization and validation methods for data handling
 *
 * @since 3.0.0
 */
trait Trait_Sanitizer {

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
	 * Sanitize array of API tokens
	 *
	 * @param array $tokens Array of tokens to sanitize.
	 *
	 * @return array Sanitized tokens array.
	 * @since 3.0.0
	 */
	protected function sanitize_api_tokens( array $tokens ): array {
		$sanitized = array();

		foreach ( $tokens as $token ) {
			$clean_token = $this->sanitize_api_token( $token );
			if ( false !== $clean_token ) {
				$sanitized[] = $clean_token;
			}
		}

		return $sanitized;
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
	 * Sanitize array of IDs
	 *
	 * @param array $ids Array of IDs to sanitize.
	 * @param int $min Minimum allowed value.
	 *
	 * @return array Sanitized IDs array.
	 * @since 3.0.0
	 */
	protected function sanitize_ids( array $ids, int $min = 1 ): array {
		$sanitized = array();

		foreach ( $ids as $id ) {
			$clean_id = $this->sanitize_id( $id, $min );
			if ( false !== $clean_id ) {
				$sanitized[] = $clean_id;
			}
		}

		return array_unique( $sanitized );
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
	 * Sanitize phone number
	 *
	 * @param mixed $phone Phone number to sanitize.
	 *
	 * @return string Sanitized phone number.
	 * @since 3.0.0
	 */
	protected function sanitize_phone( mixed $phone ): string {
		if ( ! is_string( $phone ) && ! is_numeric( $phone ) ) {
			return '';
		}

		// Remove all non-digit characters except + for international
		$phone = preg_replace( '/[^\d+]/', '', wp_unslash( $phone ) );

		// Validate phone number length (7-15 digits)
		if ( strlen( preg_replace( '/[^\d]/', '', $phone ) ) < 7 ) {
			return '';
		}

		return substr( $phone, 0, 20 ); // Limit length for security
	}

	/**
	 * Sanitize URL
	 *
	 * @param mixed $url URL to sanitize.
	 * @param array $protocols Allowed protocols.
	 *
	 * @return string Sanitized URL.
	 * @since 3.0.0
	 */
	protected function sanitize_url(
		mixed $url, array $protocols = [
		'http',
		'https'
	]
	): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		return esc_url_raw( wp_unslash( $url ), $protocols );
	}

	/**
	 * Sanitize HTML content
	 *
	 * @param mixed $content Content to sanitize.
	 * @param array $allowed_tags Allowed HTML tags.
	 *
	 * @return string Sanitized content.
	 * @since 3.0.0
	 */
	protected function sanitize_html( mixed $content, array $allowed_tags = [] ): string {
		if ( ! is_string( $content ) ) {
			return '';
		}

		if ( empty( $allowed_tags ) ) {
			$allowed_tags = wp_kses_allowed_html( context: 'post' );
		}

		return wp_kses( wp_unslash( $content ), $allowed_tags );
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
	 * Validate nonce
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 *
	 * @return bool True if valid, false otherwise.
	 * @since 3.0.0
	 */
	protected function validate_nonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action ) !== false;
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
	 * Validate AJAX request
	 *
	 * @param string $nonce_action Nonce action to verify.
	 * @param string $capability Required capability.
	 *
	 * @return bool True if valid AJAX request, false otherwise.
	 * @since 3.0.0
	 */
	protected function validate_ajax_request( string $nonce_action, string $capability = 'manage_options' ): bool {
		// Check if AJAX request
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		// Check nonce
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! $this->validate_nonce( $nonce, $nonce_action ) ) {
			return false;
		}

		// Check capability
		return $this->validate_capability( $capability );
	}
}
