<?php
/**
 * Sitemap Generator Class - Handles XML sitemap generation for gallery pages
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\SEO
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\SEO;

use BRAGBookGallery\Includes\Traits\{Trait_Sanitizer, Trait_Api, Trait_Tools};

if ( ! defined( constant_name: 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Class Sitemap
 *
 * Manages XML sitemap generation for gallery pages and integration with SEO plugins
 *
 * @since 3.0.0
 */
final class Sitemap {
	use Trait_Sanitizer, Trait_Api, Trait_Tools;

	/**
	 * Sitemap file name
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private readonly string $sitemap_filename;

	/**
	 * Sitemap cache duration
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private readonly int $cache_duration;

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->sitemap_filename = 'brag-book-gallery-sitemap.xml';
		$this->cache_duration = 6 * HOUR_IN_SECONDS; // 6 hours

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_hooks(): void {

		// Check for sitemap request very early - before any output
		add_action(
			hook_name: 'init',
			callback: array( $this, 'check_sitemap_request' ),
			priority: 1
		);

		// Add rewrite rule for sitemap.
		add_action(
			hook_name: 'init',
			callback: array( $this, 'add_sitemap_rewrite_rule' ),
			priority: 10
		);

		// Serve sitemap on request - using parse_request to avoid headers already sent
		add_action(
			hook_name: 'parse_request',
			callback: array( $this, 'handle_sitemap_request' )
		);

		// Schedule sitemap generation and clearing cache.
		add_action(
			hook_name: 'brag_book_gallery_generate_sitemap',
			callback: array( $this, 'generate_sitemap' )
		);

		// Clear sitemap cache when gallery data changes.
		add_action(
			hook_name: 'brag_book_gallery_clear_cache',
			callback: array( $this, 'clear_sitemap_cache' )
		);

		// Schedule sitemap generation.
		if ( ! wp_next_scheduled( hook: 'brag_book_gallery_generate_sitemap' ) ) {
			wp_schedule_event(
				timestamp: time(),
				recurrence: 'twicedaily',
				hook: 'brag_book_gallery_generate_sitemap'
			);
		}

		// Hook into popular SEO plugins.
		add_filter(
			hook_name: 'wpseo_sitemap_index',
			callback: array( $this, 'add_to_yoast_sitemap' )
		);

		add_filter(
			hook_name: 'aioseo_sitemap_indexes',
			callback: array( $this, 'add_to_aioseo_sitemap' )
		);

		add_filter(
			hook_name: 'rank_math/sitemap/index',
			callback: array( $this, 'add_to_rankmath_sitemap' )
		);
	}

	/**
	 * Check for sitemap request very early
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function check_sitemap_request(): void {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$sitemap_path = '/' . $this->sitemap_filename;
		
		// Check if this is a sitemap request
		if ( strpos( $request_uri, $sitemap_path ) === false ) {
			return;
		}
		
		// We need to wait for WordPress to be ready, so register a high priority action
		add_action( 'template_redirect', array( $this, 'serve_sitemap_immediately' ), 1 );
	}
	
	/**
	 * Add sitemap rewrite rule
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_sitemap_rewrite_rule(): void {
		add_rewrite_rule(
			regex: '^' . $this->sitemap_filename . '$',
			query: 'index.php?brag_book_gallery_sitemap=1',
			after: 'top'
		);

		add_filter(
			hook_name: 'query_vars',
			callback: function( $vars ) {
				$vars[] = 'brag_book_gallery_sitemap';
				return $vars;
			}
		);
	}

	/**
	 * Handle sitemap request
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	public function handle_sitemap_request( \WP $wp ): void {
		if ( ! isset( $wp->query_vars['brag_book_gallery_sitemap'] ) ) {
			return;
		}

		$sitemap_content = $this->get_sitemap_content();

		if ( empty( $sitemap_content ) ) {
			status_header( 404 );
			exit;
		}

		// Set appropriate headers
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		// Set caching headers
		$cache_time = 6 * HOUR_IN_SECONDS;
		header( 'Cache-Control: max-age=' . $cache_time );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $cache_time ) . ' GMT' );

		echo $sitemap_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Serve sitemap immediately with output buffer handling
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function serve_sitemap_immediately(): void {
		// Clean all output buffers to prevent headers already sent
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		
		// Start a new buffer to capture any errant output
		ob_start();
		
		$sitemap_content = $this->get_sitemap_content();
		
		// Clean the buffer again in case get_sitemap_content produced output
		ob_clean();
		
		if ( empty( $sitemap_content ) ) {
			status_header( 404 );
			exit;
		}
		
		// Remove any potential whitespace or BOM
		$sitemap_content = trim( $sitemap_content );
		
		// Set appropriate headers
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		
		// Set caching headers
		$cache_time = 6 * HOUR_IN_SECONDS;
		header( 'Cache-Control: max-age=' . $cache_time );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $cache_time ) . ' GMT' );
		
		// Output sitemap and exit
		echo $sitemap_content;
		exit;
	}
	
	/**
	 * Serve sitemap when requested (legacy method kept for compatibility)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function serve_sitemap(): void {
		if ( ! get_query_var( 'brag_book_gallery_sitemap' ) ) {
			return;
		}

		$this->serve_sitemap_immediately();
	}

	/**
	 * Generate sitemap XML content
	 *
	 * @since 3.0.0
	 * @return string Sitemap XML content.
	 */
	public function generate_sitemap(): string {
		$cache_key = 'brag_book_gallery_sitemap_content';
		$cached_content = get_transient( $cache_key );

		if ( false !== $cached_content && ! empty( $cached_content ) ) {
			return $cached_content;
		}

		$sitemap_data = $this->get_sitemap_data();

		// If no API data, generate a basic sitemap with gallery page URL
		if ( empty( $sitemap_data ) ) {
			$xml = $this->build_basic_sitemap();
		} else {
			$xml = $this->build_sitemap_xml( $sitemap_data );
		}

		// Only cache if we have content
		if ( ! empty( $xml ) ) {
			set_transient(
				transient: $cache_key,
				value: $xml,
				expiration: $this->cache_duration
			);
		}

		return $xml;
	}

	/**
	 * Get sitemap content (cached or generate new)
	 *
	 * @since 3.0.0
	 * @return string Sitemap XML content.
	 */
	private function get_sitemap_content(): string {
		$cached_content = get_transient( transient: 'brag_book_gallery_sitemap_content' );

		if ( false !== $cached_content ) {
			return $cached_content;
		}

		return $this->generate_sitemap();
	}

	/**
	 * Get sitemap data from API
	 *
	 * @since 3.0.0
	 * @return array Sitemap data.
	 */
	private function get_sitemap_data(): array {

		// Retrieve API tokens and website property IDs from options.
		$api_tokens = get_option(
			option: 'brag_book_gallery_api_token',
			default_value: array()
		);

		// Ensure we have an array.
		$website_property_ids = get_option(
			option: 'brag_book_gallery_website_property_id',
			default_value: array()
		);

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			return [];
		}

		// Clean up arrays and ensure they match
		$api_tokens = array_values( array_filter( $api_tokens ) );
		$website_property_ids = array_values( array_filter( $website_property_ids ) );

		if ( count( $api_tokens ) !== count( $website_property_ids ) ) {
			error_log( 'BRAGBook Sitemap: API tokens and website property IDs count mismatch' );
			return [];
		}

		$request_data = [
			'apiTokens' => $api_tokens,
			'websitePropertyIds' => array_map( 'intval', $website_property_ids ),
		];

		$response = $this->api_post( '/api/plugin/sitemap', $request_data );

		if ( is_wp_error( $response ) ) {
			error_log( 'BRAGBook Sitemap API Error: ' . $response->get_error_message() );
			return [];
		}

		if ( ! isset( $response['data'] ) ) {
			error_log( 'BRAGBook Sitemap: No data in API response' );
			return [];
		}

		return $response['data'];
	}

	/**
	 * Build basic sitemap when no API data available
	 *
	 * @since 3.0.0
	 * @return string Basic XML sitemap content.
	 */
	private function build_basic_sitemap(): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		
		// Get gallery mode and slug
		$mode = get_option( 'brag_book_gallery_mode', 'default' );
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		
		// Handle array or string slug
		if ( is_array( $gallery_slug ) ) {
			$gallery_slugs = $gallery_slug;
		} else {
			$gallery_slugs = array( $gallery_slug );
		}
		
		// Add entry for each gallery page
		foreach ( $gallery_slugs as $slug ) {
			if ( empty( $slug ) ) {
				continue;
			}
			
			$gallery_url = home_url( '/' . $slug . '/' );
			$xml .= '  <url>' . "\n";
			$xml .= '    <loc>' . esc_url( $gallery_url ) . '</loc>' . "\n";
			$xml .= '    <lastmod>' . gmdate( 'c' ) . '</lastmod>' . "\n";
			$xml .= '    <changefreq>weekly</changefreq>' . "\n";
			$xml .= '    <priority>0.8</priority>' . "\n";
			$xml .= '  </url>' . "\n";
		}
		
		$xml .= '</urlset>' . "\n";
		
		return $xml;
	}
	
	/**
	 * Build sitemap XML from data
	 *
	 * @since 3.0.0
	 * @param array $sitemap_data Sitemap data from API.
	 * @return string XML sitemap content.
	 */
	private function build_sitemap_xml( array $sitemap_data ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $this->get_sitemap_xsl_url() ) . '"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
		$xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
		$xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
		$xml .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 ';
		$xml .= 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd ';
		$xml .= 'http://www.google.com/schemas/sitemap-image/1.1 ';
		$xml .= 'http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd">' . "\n";

		// Get gallery mode to determine how to handle slugs
		$mode = get_option( 'brag_book_gallery_mode', 'default' );
		
		if ( $mode === 'local' ) {
			// Local mode: Multiple galleries with array of slugs
			$gallery_slugs = get_option(
				option: 'brag_book_gallery_page_slug',
				default_value: array()
			);
			
			// Ensure it's an array
			if ( ! is_array( $gallery_slugs ) ) {
				$gallery_slugs = array( $gallery_slugs );
			}

			foreach ( $sitemap_data as $index => $org_data ) {
				if ( ! is_array( $org_data ) ) {
					continue;
				}

				$page_slug = $gallery_slugs[ $index ] ?? '';

				// Skip if no page slug found.
				if ( empty( $page_slug ) ) {
					continue;
				}

				foreach ( $org_data as $url_data ) {
					if ( ! is_object( $url_data ) || empty( $url_data->url ) ) {
						continue;
					}

					$full_url = home_url( '/' . $page_slug . $url_data->url );
					$last_modified = $url_data->updatedAt ?? current_time( 'c' );

					// Validate and sanitize the URL
					$full_url = esc_url( $full_url );
					if ( empty( $full_url ) ) {
						continue;
					}

					$xml .= $this->build_url_entry( $full_url, $last_modified, $url_data );
				}
			}
		} else {
			// Default mode: Single gallery with string slug
			$gallery_slug = get_option(
				option: 'brag_book_gallery_page_slug',
				default_value: 'gallery'
			);
			
			// If it's an array, get the first element
			if ( is_array( $gallery_slug ) ) {
				$gallery_slug = ! empty( $gallery_slug ) ? reset( $gallery_slug ) : 'gallery';
			}

			// Process all data for the single gallery
			foreach ( $sitemap_data as $org_data ) {
				if ( ! is_array( $org_data ) ) {
					continue;
				}

				foreach ( $org_data as $url_data ) {
					if ( ! is_object( $url_data ) || empty( $url_data->url ) ) {
						continue;
					}

					$full_url = home_url( '/' . $gallery_slug . $url_data->url );
					$last_modified = $url_data->updatedAt ?? current_time( 'c' );

					$full_url = esc_url( $full_url );
					if ( empty( $full_url ) ) {
						continue;
					}

					$xml .= $this->build_url_entry( $full_url, $last_modified, $url_data );
				}
			}
		}

		$xml .= '</urlset>' . "\n";

		return $xml;
	}

	/**
	 * Build individual URL entry for sitemap
	 *
	 * @since 3.0.0
	 * @param string $url URL for the entry.
	 * @param string $last_modified Last modified date.
	 * @param object $url_data URL data from API.
	 * @return string XML URL entry.
	 */
	private function build_url_entry( string $url, string $last_modified, object $url_data ): string {
		$xml = '  <url>' . "\n";
		$xml .= '    <loc>' . esc_url( $url ) . '</loc>' . "\n";

		// Validate and format last modified date
		$formatted_date = $this->format_sitemap_date( $last_modified );
		if ( ! empty( $formatted_date ) ) {
			$xml .= '    <lastmod>' . esc_xml( $formatted_date ) . '</lastmod>' . "\n";
		}

		// Set priority based on URL type
		$priority = $this->get_url_priority( $url_data );
		if ( ! empty( $priority ) ) {
			$xml .= '    <priority>' . esc_xml( $priority ) . '</priority>' . "\n";
		}

		// Set change frequency
		$changefreq = $this->get_url_change_frequency( $url_data );
		if ( ! empty( $changefreq ) ) {
			$xml .= '    <changefreq>' . esc_xml( $changefreq ) . '</changefreq>' . "\n";
		}

		// Add image information if available
		if ( isset( $url_data->images ) && is_array( $url_data->images ) ) {
			foreach ( $url_data->images as $image ) {
				if ( ! is_object( $image ) || empty( $image->url ) ) {
					continue;
				}

				$xml .= '    <image:image>' . "\n";
				$xml .= '      <image:loc>' . esc_url( $image->url ) . '</image:loc>' . "\n";

				if ( ! empty( $image->caption ) ) {
					$xml .= '      <image:caption>' . esc_xml( $image->caption ) . '</image:caption>' . "\n";
				}

				if ( ! empty( $image->title ) ) {
					$xml .= '      <image:title>' . esc_xml( $image->title ) . '</image:title>' . "\n";
				}

				$xml .= '    </image:image>' . "\n";
			}
		}

		$xml .= '  </url>' . "\n";

		return $xml;
	}

	/**
	 * Format date for sitemap
	 *
	 * @since 3.0.0
	 * @param string $date Date string.
	 * @return string Formatted date or empty string if invalid.
	 */
	private function format_sitemap_date( string $date ): string {
		if ( empty( $date ) ) {
			return current_time( type: 'c' );
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return current_time( type: 'c' );
		}

		return gmdate( format: 'c', timestamp: $timestamp );
	}

	/**
	 * Get URL priority for sitemap
	 *
	 * @since 3.0.0
	 * @param object $url_data URL data.
	 * @return string Priority value.
	 */
	private function get_url_priority( object $url_data ): string {
		// Case detail pages get higher priority
		if ( isset( $url_data->type ) && 'case' === $url_data->type ) {
			return '0.8';
		}

		// Procedure pages get medium priority
		if ( isset( $url_data->type ) && 'procedure' === $url_data->type ) {
			return '0.7';
		}

		// Default priority
		return '0.6';
	}

	/**
	 * Get URL change frequency for sitemap
	 *
	 * @since 3.0.0
	 * @param object $url_data URL data.
	 * @return string Change frequency.
	 */
	private function get_url_change_frequency( object $url_data ): string {

		// Case detail pages change less frequently.
		if ( isset( $url_data->type ) && 'case' === $url_data->type ) {
			return 'monthly';
		}

		// Procedure pages change more frequently as new cases are added.
		if ( isset( $url_data->type ) && 'procedure' === $url_data->type ) {
			return 'weekly';
		}

		// Default frequency
		return 'monthly';
	}

	/**
	 * Get sitemap XSL stylesheet URL
	 *
	 * @since 3.0.0
	 * @return string XSL stylesheet URL.
	 */
	private function get_sitemap_xsl_url(): string {
		return $this->get_plugin_url() . '/assets/css/sitemap-style.xsl';
	}

	/**
	 * Add to Yoast SEO sitemap index
	 *
	 * @since 3.0.0
	 * @param string $sitemap_index Existing sitemap index.
	 * @return string Modified sitemap index.
	 */
	public function add_to_yoast_sitemap( string $sitemap_index ): string {
		$sitemap_url = home_url( '/' . $this->sitemap_filename );
		$last_modified = $this->get_sitemap_last_modified();

		$sitemap_entry = '<sitemap>' . "\n";
		$sitemap_entry .= '<loc>' . esc_url( $sitemap_url ) . '</loc>' . "\n";
		$sitemap_entry .= '<lastmod>' . esc_xml( $last_modified ) . '</lastmod>' . "\n";
		$sitemap_entry .= '</sitemap>' . "\n";

		return $sitemap_index . $sitemap_entry;
	}

	/**
	 * Add to All in One SEO sitemap index
	 *
	 * @since 3.0.0
	 * @param array $sitemaps Existing sitemaps.
	 * @return array Modified sitemaps.
	 */
	public function add_to_aioseo_sitemap( array $sitemaps ): array {
		$sitemaps[] = array(
			'loc' => home_url( '/' . $this->sitemap_filename ),
			'lastmod' => $this->get_sitemap_last_modified(),
		);

		return $sitemaps;
	}

	/**
	 * Add to Rank Math sitemap index
	 *
	 * @since 3.0.0
	 * @param string $sitemap_index Existing sitemap index.
	 * @return string Modified sitemap index.
	 */
	public function add_to_rankmath_sitemap( string $sitemap_index ): string {
		$sitemap_url = home_url( '/' . $this->sitemap_filename );
		$last_modified = $this->get_sitemap_last_modified();

		$sitemap_entry = '<sitemap>' . "\n";
		$sitemap_entry .= '<loc>' . esc_url( $sitemap_url ) . '</loc>' . "\n";
		$sitemap_entry .= '<lastmod>' . esc_xml( $last_modified ) . '</lastmod>' . "\n";
		$sitemap_entry .= '</sitemap>' . "\n";

		return $sitemap_index . $sitemap_entry;
	}

	/**
	 * Get sitemap last modified date
	 *
	 * @since 3.0.0
	 * @return string Last modified date in ISO 8601 format.
	 */
	private function get_sitemap_last_modified(): string {
		$cache_key = 'brag_book_gallery_sitemap_last_modified';
		$last_modified = get_transient( $cache_key );

		if ( false !== $last_modified ) {
			return $last_modified;
		}

		// Get the latest modification date from API or use current time
		$current_time = current_time( 'c' );
		set_transient( $cache_key, $current_time, $this->cache_duration );

		return $current_time;
	}

	/**
	 * Clear sitemap cache
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function clear_sitemap_cache(): void {
		delete_transient( transient: 'brag_book_gallery_sitemap_content' );
		delete_transient( transient: 'brag_book_gallery_sitemap_last_modified' );

		// Clear any API cache that might affect sitemap data.
		$this->clear_api_cache( pattern: 'sitemap' );
	}

	/**
	 * Manually trigger sitemap generation
	 *
	 * @since 3.0.0
	 * @return bool True if successful, false otherwise.
	 */
	public function regenerate_sitemap(): bool {
		$this->clear_sitemap_cache();
		$xml = $this->generate_sitemap();

		return ! empty( $xml );
	}

	/**
	 * Get sitemap URL
	 *
	 * @since 3.0.0
	 * @return string Sitemap URL.
	 */
	public function get_sitemap_url(): string {
		return home_url( '/' . $this->sitemap_filename );
	}

	/**
	 * Check if sitemap is cached
	 *
	 * @since 3.0.0
	 * @return bool True if cached, false otherwise.
	 */
	public function is_sitemap_cached(): bool {
		return false !== get_transient( 'brag_book_gallery_sitemap_content' );
	}

	/**
	 * Get sitemap statistics
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Sitemap statistics.
	 */
	public function get_sitemap_stats(): array {
		$content = $this->get_sitemap_content();

		if ( empty( $content ) ) {
			return [
				'url_count' => 0,
				'last_generated' => null,
				'file_size' => 0,
				'is_cached' => false,
			];
		}

		$url_count = substr_count( $content, '<url>' );
		$file_size = strlen( $content );

		return array(
			'url_count' => $url_count,
			'last_generated' => $this->get_sitemap_last_modified(),
			'file_size' => $file_size,
			'is_cached' => $this->is_sitemap_cached(),
		);
	}
}
