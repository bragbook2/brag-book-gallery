<?php
/**
 * Debug logging.
 *
 * @package    BRAGBookGallery
 * @since      4.9.2
 */

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

if ( ! function_exists( 'brag_book_log' ) ) {
	/**
	 * Write a debug line to the PHP error log, but only when debugging.
	 *
	 * Deliberately a global function rather than a static method: it is called
	 * from every namespace in the plugin, and PHP falls back to global scope
	 * for unqualified function calls, so no import is needed at any call site.
	 *
	 * Replaces direct error_log() calls, which ran on production sites and made
	 * a single sync write hundreds of lines to the host's error log.
	 *
	 * @since 4.9.2
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	function brag_book_log( string $message ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Gated behind WP_DEBUG above.
		error_log( $message );
	}
}
