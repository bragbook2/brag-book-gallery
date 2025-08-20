<?php
/**
 * AJAX Handler Trait - Common AJAX functionality for admin classes
 *
 * This trait provides reusable AJAX handling methods including security
 * verification, response formatting, and common AJAX operations.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin\Traits
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Traits;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Trait Ajax_Handler
 *
 * Provides common AJAX handling functionality for admin classes.
 * Includes security verification, response formatting, and utility methods.
 *
 * @since 3.0.0
 */
trait Trait_Ajax_Handler {

	/**
	 * Verify AJAX request security
	 *
	 * Performs comprehensive security checks for AJAX requests including
	 * nonce verification and user capability checks. Dies with JSON error
	 * response if any check fails.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $nonce_action The nonce action to verify.
	 * @param string $capability   The capability to check (default: 'manage_options').
	 * 
	 * @return void Dies with JSON error on failure
	 */
	protected function verify_ajax_request( string $nonce_action = 'brag_book_gallery_settings_nonce', string $capability = 'manage_options' ): void {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $nonce_action ) ) {
			wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'brag-book-gallery' ) );
		}

		// Check user capability.
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * Get sanitized POST parameter
	 *
	 * Retrieves and sanitizes a POST parameter with optional default value.
	 * Handles arrays and strings appropriately.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $key     The POST parameter key.
	 * @param mixed  $default Default value if parameter not set.
	 * 
	 * @return mixed Sanitized parameter value or default
	 */
	protected function get_post_param( string $key, $default = null ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		$value = wp_unslash( $_POST[ $key ] );

		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Send AJAX success response
	 *
	 * Formats and sends a standardized success response for AJAX requests.
	 * Includes optional data payload and message.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $message Success message to display.
	 * @param array  $data    Optional additional data to include.
	 * 
	 * @return void Dies after sending response
	 */
	protected function ajax_success( string $message, array $data = array() ): void {
		$response = array_merge(
			array( 'message' => $message ),
			$data
		);
		
		wp_send_json_success( $response );
	}

	/**
	 * Send AJAX error response
	 *
	 * Formats and sends a standardized error response for AJAX requests.
	 * Includes optional error code and additional data.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $message Error message to display.
	 * @param string $code    Optional error code.
	 * @param array  $data    Optional additional data to include.
	 * 
	 * @return void Dies after sending response
	 */
	protected function ajax_error( string $message, string $code = '', array $data = array() ): void {
		$response = array_merge(
			array( 
				'message' => $message,
				'code'    => $code,
			),
			$data
		);
		
		wp_send_json_error( $response );
	}

	/**
	 * Parse form data string
	 *
	 * Parses URL-encoded form data string into an associative array.
	 * Useful for processing serialized form data from AJAX requests.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string $form_data URL-encoded form data string.
	 * 
	 * @return array Parsed form data array
	 */
	protected function parse_form_data( string $form_data ): array {
		parse_str( $form_data, $parsed_data );
		return $parsed_data;
	}

	/**
	 * Validate required fields
	 *
	 * Checks that all required fields are present and non-empty in the
	 * provided data array. Returns validation result with error messages.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param array $data     Data array to validate.
	 * @param array $required Array of required field names.
	 * 
	 * @return array Validation result with 'valid' boolean and 'errors' array
	 */
	protected function validate_required_fields( array $data, array $required ): array {
		$errors = array();
		
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: field name */
					__( 'The %s field is required.', 'brag-book-gallery' ),
					$field
				);
			}
		}
		
		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Register AJAX action
	 *
	 * Helper method to register both logged-in and non-logged-in AJAX actions
	 * with a single call. Reduces boilerplate code for AJAX registration.
	 *
	 * @since 3.0.0
	 * @access protected
	 * 
	 * @param string   $action   The AJAX action name (without wp_ajax_ prefix).
	 * @param callable $callback The callback method to handle the action.
	 * @param bool     $nopriv   Whether to also register for non-logged-in users.
	 * 
	 * @return void
	 */
	protected function register_ajax_action( string $action, callable $callback, bool $nopriv = false ): void {
		// Debug logging for AJAX action registration
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "BRAG book Gallery: Registering AJAX action: wp_ajax_{$action}" );
		}

		add_action( 'wp_ajax_' . $action, $callback );
		
		if ( $nopriv ) {
			add_action( 'wp_ajax_nopriv_' . $action, $callback );
		}
	}
}