<?php
/**
 * Logger Trait
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.3.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Core;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Logger trait for debug logging functionality
 *
 * @since 3.3.0
 */
trait Trait_Logger {

	/**
	 * Log a debug message
	 *
	 * @since 3.3.0
	 * @param string $message The message to log.
	 * @param string $level The log level (debug, info, warning, error).
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function debug_log( string $message, string $level = 'info', array $context = [] ): void {
		// Check if logging is enabled
		$enable_logs = get_option( 'brag_book_gallery_enable_logs', 'no' );
		if ( 'yes' !== $enable_logs ) {
			return;
		}

		// Check log level
		$configured_level = get_option( 'brag_book_gallery_log_level', 'error' );
		if ( ! $this->should_log( $level, $configured_level ) ) {
			return;
		}

		// Get log file path
		$log_file = $this->get_debug_log_path();
		if ( ! $log_file ) {
			return;
		}

		// Format the log entry
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );
		$caller = $this->get_caller_info();

		$log_entry = sprintf(
			"[%s] [%s] %s - %s",
			$timestamp,
			$level_upper,
			$caller,
			$message
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		$log_entry .= PHP_EOL;

		// Write to log file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Log an error message
	 *
	 * @since 3.3.0
	 * @param string $message The error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function log_error( string $message, array $context = [] ): void {
		$this->debug_log( $message, 'error', $context );
	}

	/**
	 * Log a warning message
	 *
	 * @since 3.3.0
	 * @param string $message The warning message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function log_warning( string $message, array $context = [] ): void {
		$this->debug_log( $message, 'warning', $context );
	}

	/**
	 * Log an info message
	 *
	 * @since 3.3.0
	 * @param string $message The info message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function log_info( string $message, array $context = [] ): void {
		$this->debug_log( $message, 'info', $context );
	}

	/**
	 * Log a debug message
	 *
	 * @since 3.3.0
	 * @param string $message The debug message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function log_debug( string $message, array $context = [] ): void {
		$this->debug_log( $message, 'debug', $context );
	}

	/**
	 * Check if a message should be logged based on configured level
	 *
	 * @since 3.3.0
	 * @param string $message_level The level of the message.
	 * @param string $configured_level The configured log level.
	 * @return bool
	 */
	private function should_log( string $message_level, string $configured_level ): bool {
		$levels = array(
			'debug' => 0,
			'info' => 1,
			'warning' => 2,
			'error' => 3,
		);

		$message_priority = $levels[ $message_level ] ?? 1;
		$configured_priority = $levels[ $configured_level ] ?? 3;

		// Log if message priority is >= configured priority
		// (Higher number = more severe, should always be logged)
		return $message_priority >= $configured_priority;
	}

	/**
	 * Get the debug log file path
	 *
	 * @since 3.3.0
	 * @return string|false
	 */
	private function get_debug_log_path() {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		// Create directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess to protect log files
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $htaccess, 'Deny from all' );
			}
		}

		return $log_dir . '/debug.log';
	}

	/**
	 * Get caller information for better debugging
	 *
	 * @since 3.3.0
	 * @return string
	 */
	private function get_caller_info(): string {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );

		// Skip the logger methods themselves
		foreach ( $backtrace as $trace ) {
			if ( isset( $trace['class'] ) && isset( $trace['function'] ) ) {
				$method = $trace['function'];
				if ( ! in_array( $method, array( 'debug_log', 'log_error', 'log_warning', 'log_info', 'log_debug', 'get_caller_info' ), true ) ) {
					$class = $trace['class'] ?? 'Unknown';
					$function = $trace['function'] ?? 'Unknown';
					return sprintf( '%s::%s', $class, $function );
				}
			}
		}

		return 'Unknown';
	}
}