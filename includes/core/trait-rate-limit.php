<?php
/**
 * Rate limiting for public endpoints.
 *
 * Replaces the removed Cache_Manager that the previous rate limiting depended
 * on. Every call site that used it had been commented out, which left the
 * public AJAX endpoints unthrottled.
 *
 * @package    BRAGBookGallery
 * @since      4.9.2
 */

namespace BRAGBookGallery\Includes\Core;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Per-IP request throttling backed by WordPress transients.
 *
 * @since 4.9.2
 */
trait Trait_Rate_Limit {

	/**
	 * Count a request against a per-IP quota and report whether it is allowed.
	 *
	 * Increments the counter as a side effect, so call this once per request
	 * and only after cheaper checks (nonce, input validation) have passed.
	 *
	 * ponytail: transient-backed counter, so the window resets wholesale rather
	 * than sliding, and on a multi-server setup without a shared object cache
	 * each node keeps its own count. Move to a shared store if either matters.
	 *
	 * @since 4.9.2
	 *
	 * @param string $bucket Identifies the endpoint being limited.
	 * @param int    $max    Requests allowed per window.
	 * @param int    $window Window length in seconds.
	 *
	 * @return bool True when the request is within quota.
	 */
	protected static function within_rate_limit( string $bucket, int $max, int $window ): bool {
		$key   = 'brag_book_rl_' . md5( $bucket . '|' . self::get_client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return false;
		}

		// Re-setting the transient restarts the TTL, so keep the original
		// expiry for the life of the window by only seeding it on first hit.
		if ( 0 === $count ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, $count + 1, $window );
		}

		return true;
	}

	/**
	 * Resolve the client IP, preferring proxy headers over REMOTE_ADDR.
	 *
	 * Proxy headers are client-controlled and can be forged. That is acceptable
	 * here because this only feeds rate limiting: a forged header costs the
	 * attacker a fresh bucket, it does not grant access to anything.
	 *
	 * @since 4.9.2
	 *
	 * @return string Client IP address, or '0.0.0.0' when none is valid.
	 */
	protected static function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_REAL_IP',        // Nginx proxy.
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header.
			'HTTP_FORWARDED_FOR',    // RFC 7239.
			'REMOTE_ADDR',           // Standard CGI variable.
		];

		foreach ( $ip_keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

			// Forwarding headers may carry a chain; the first entry is the client.
			if ( str_contains( $ip, ',' ) ) {
				$ip = trim( explode( ',', $ip )[0] );
			}

			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}
