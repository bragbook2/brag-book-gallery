<?php
/**
 * Sitemap Generator Class - Enterprise-grade XML sitemap generation system
 *
 * Comprehensive sitemap generation system for BRAGBook Gallery plugin.
 * Provides advanced XML sitemap creation with multi-plugin integration,
 * intelligent caching, and SEO optimization features.
 *
 * Features:
 * - Dynamic XML sitemap generation with image support
 * - SEO plugin integration (Yoast, AIOSEO, RankMath)
 * - Intelligent caching with automatic invalidation
 * - Comprehensive error handling and validation
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
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

use BRAGBookGallery\Includes\Traits\{Trait_Api, Trait_Tools};
use BRAGBookGallery\Includes\Extend\Cache_Manager;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Enterprise Sitemap Generation System
 *
 * Manages XML sitemap generation for gallery pages with advanced features:
 *
 * Core Responsibilities:
 * - Dynamic XML sitemap generation with proper formatting
 * - Multi-plugin SEO integration with seamless compatibility
 * - Advanced caching strategies for optimal performance
 * - Comprehensive error handling and validation
 * - Security measures for safe data processing
 *
 * @since 3.0.0
 */
final class Sitemap {
	use Trait_Api, Trait_Tools; // Note: Trait_Api includes Trait_Sanitizer

	/**
	 * Sitemap-specific cache duration constants
	 *
	 * @since 3.0.0
	 * Note: Using CACHE_TTL constants from Trait_Api for shared cache durations
	 */
	private const CACHE_TTL_SITEMAP = 21600;      // 6 hours - for sitemap content
	private const CACHE_TTL_SITEMAP_LONG = 86400; // 24 hours - for stable sitemap content

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
	 * Note: The following properties are inherited from traits:
	 * - $memory_cache from Trait_Api
	 * - $validation_errors from Trait_Sanitizer (via Trait_Api)
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->sitemap_filename = 'brag-book-gallery-sitemap.xml';
		$this->cache_duration = self::CACHE_TTL_SITEMAP; // 6 hours - using constant

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
			'init',
			array( $this, 'check_sitemap_request' ),
			1
		);

		// Add rewrite rule for sitemap.
		add_action(
			'init',
			array( $this, 'add_sitemap_rewrite_rule' ),
			10
		);

		// Serve sitemap on request - using parse_request to avoid headers already sent
		add_action(
			'parse_request',
			array( $this, 'handle_sitemap_request' )
		);

		// Schedule sitemap generation and clearing cache.
		add_action(
			'brag_book_gallery_generate_sitemap',
			array( $this, 'generate_sitemap' )
		);

		// Clear sitemap cache when gallery data changes.
		add_action(
			'brag_book_gallery_clear_cache',
			array( $this, 'clear_sitemap_cache' )
		);

		// Schedule sitemap generation.
		if ( ! wp_next_scheduled( 'brag_book_gallery_generate_sitemap' ) ) {
			wp_schedule_event(
				time(),
				'twicedaily',
				'brag_book_gallery_generate_sitemap'
			);
		}

		// Hook into popular SEO plugins.
		add_filter(
			'wpseo_sitemap_index',
			array( $this, 'add_to_yoast_sitemap' )
		);

		add_filter(
			'aioseo_sitemap_indexes',
			array( $this, 'add_to_aioseo_sitemap' )
		);

		add_filter(
			'rank_math/sitemap/index',
			array( $this, 'add_to_rankmath_sitemap' )
		);
	}

	/**
	 * Check for sitemap request very early
	 *
	 * Detects sitemap requests in the URL and schedules immediate serving.
	 * Runs early in the WordPress lifecycle to prevent interference.
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
			'^' . $this->sitemap_filename . '$',
			'index.php?brag_book_gallery_sitemap=1',
			'top'
		);

		add_filter(
			'query_vars',
			function( $vars ) {
				$vars[] = 'brag_book_gallery_sitemap';
				return $vars;
			}
		);
	}

	/**
	 * Handle sitemap request
	 *
	 * Processes sitemap requests and outputs XML with proper headers.
	 * Implements caching headers for improved performance.
	 *
	 * @since 3.0.0
	 * @param \WP $wp WordPress environment instance containing query variables.
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
		try {
			$cache_key = 'brag_book_gallery_transient_sitemap_content';
			$cached_content = Cache_Manager::get( $cache_key );

			if ( ! empty( $cached_content ) ) {
				$this->track_performance( 'sitemap_generate', 'cache_hit' );
				return $cached_content;
			}

			$sitemap_data = $this->get_sitemap_data();

			// If no API data, generate a basic sitemap with gallery page URL
			if ( empty( $sitemap_data ) ) {
				$this->log_warning( 'No API data available, generating basic sitemap' );
				$xml = $this->build_basic_sitemap();
			} else {
				$xml = $this->build_sitemap_xml( $sitemap_data );
			}

			// Validate XML before caching
			if ( ! $this->validate_xml( $xml ) ) {
				$this->log_error( 'Invalid XML generated for sitemap' );
				return $this->get_fallback_sitemap();
			}

			// Only cache if we have valid content
			if ( ! empty( $xml ) ) {
				Cache_Manager::set(
					$cache_key,
					$xml,
					$this->cache_duration
				);
				$this->track_performance( 'sitemap_generate', 'generated' );
			}

			return $xml;

		} catch ( \Exception $e ) {
			$this->log_error( 'Sitemap generation failed', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			] );
			return $this->get_fallback_sitemap();
		}
	}

	/**
	 * Get sitemap content (cached or generate new)
	 *
	 * @since 3.0.0
	 * @return string Sitemap XML content.
	 */
	private function get_sitemap_content(): string {
		$cached_content = Cache_Manager::get( 'brag_book_gallery_transient_sitemap_content' );

		if ( false !== $cached_content ) {
			return $cached_content;
		}

		return $this->generate_sitemap();
	}

	/**
	 * Get sitemap data from API
	 *
	 * Retrieves sitemap data from the BRAGBook API with proper authentication.
	 * Handles multiple API tokens and website property IDs.
	 *
	 * @since 3.0.0
	 * @return array<int, array<string, mixed>> Sitemap data structure:
	 *                                          - Each element contains URL objects with:
	 *                                            - string 'url' Relative URL path
	 *                                            - string 'updatedAt' Last modification date
	 *                                            - string 'type' URL type (case/procedure)
	 *                                            - array 'images' Optional image data
	 */
	private function get_sitemap_data(): array {
		try {
			// Retrieve API tokens and website property IDs with validation
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			// Validate and sanitize API tokens
			$api_tokens = $this->sanitize_api_tokens( $api_tokens );
			$website_property_ids = $this->sanitize_property_ids( $website_property_ids );

			if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
				$this->log_warning( 'Missing API tokens or website property IDs' );
				return [];
			}

			// Ensure arrays match in count
			if ( count( $api_tokens ) !== count( $website_property_ids ) ) {
				$this->log_error( 'API tokens and website property IDs count mismatch', [
					'tokens_count' => count( $api_tokens ),
					'ids_count' => count( $website_property_ids )
				] );
				return [];
			}

			// Rate limiting check
			if ( $this->is_rate_limited( 'sitemap_api', 10, 60 ) ) {
				$this->log_warning( 'Sitemap API rate limit exceeded' );
				return [];
			}

			$request_data = [
				'apiTokens' => $api_tokens,
				'websitePropertyIds' => array_map( fn($id) => (int) $id, $website_property_ids ),
			];

			$response = $this->api_post( '/api/plugin/sitemap', $request_data );

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'Sitemap API request failed', [
					'error' => $response->get_error_message()
				] );
				return [];
			}

			if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
				$this->log_error( 'Invalid API response structure' );
				return [];
			}

			// Sanitize response data
			return $this->sanitize_sitemap_data( $response['data'] );

		} catch ( \Exception $e ) {
			$this->log_error( 'Failed to retrieve sitemap data', [
				'error' => $e->getMessage()
			] );
			return [];
		}
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
	 * Constructs a complete XML sitemap with proper namespaces and formatting.
	 * Supports image sitemaps and multiple gallery modes.
	 *
	 * @since 3.0.0
	 * @param array<int, array<string, mixed>> $sitemap_data Sitemap data from API.
	 * @return string Well-formed XML sitemap content.
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

		// Get gallery mode to determine how to handle slugs with PHP 8.2 features
		$mode = get_option( 'brag_book_gallery_mode', 'default' );

		// Use match expression for mode handling (PHP 8.0+)
		match ( $mode ) {
			'local' => $this->build_local_mode_sitemap( $xml, $sitemap_data ),
			default => $this->build_default_mode_sitemap( $xml, $sitemap_data ),
		};

		$xml .= '</urlset>' . "\n";

		return $xml;
	}

	/**
	 * Build sitemap for local mode with multiple galleries
	 *
	 * @since 3.0.0
	 * @param string &$xml Reference to XML content being built.
	 * @param array<mixed> $sitemap_data Sitemap data from API.
	 * @return void
	 */
	private function build_local_mode_sitemap( string &$xml, array $sitemap_data ): void {
		// Local mode: Multiple galleries with array of slugs using modern syntax
		$gallery_slugs = get_option( 'brag_book_gallery_page_slug', [] );

		// Ensure it's an array using null coalescing
		$gallery_slugs = is_array( $gallery_slugs ) ? $gallery_slugs : [ $gallery_slugs ];

		foreach ( $sitemap_data as $index => $org_data ) {
			if ( ! is_array( $org_data ) ) {
				continue;
			}

			$page_slug = $gallery_slugs[ $index ] ?? '';

			// Skip if no page slug found
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
	}

	/**
	 * Build sitemap for default mode with single gallery
	 *
	 * @since 3.0.0
	 * @param string &$xml Reference to XML content being built.
	 * @param array<mixed> $sitemap_data Sitemap data from API.
	 * @return void
	 */
	private function build_default_mode_sitemap( string &$xml, array $sitemap_data ): void {
		// Default mode: Single gallery with string slug
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );

		// If it's an array, get the first element using modern array function
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

	/**
	 * Build individual URL entry for sitemap
	 *
	 * Creates a single URL entry with metadata and optional image information.
	 * Follows sitemap protocol specifications for proper formatting.
	 *
	 * @since 3.0.0
	 * @param string $url          Fully qualified URL for the entry.
	 * @param string $last_modified Last modified date in ISO 8601 format.
	 * @param object $url_data     URL data object from API containing:
	 *                            - ?string 'type' URL type for priority calculation
	 *                            - ?array 'images' Array of image objects
	 * @return string Well-formed XML URL entry block.
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
			return current_time( 'c' );
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return current_time( 'c' );
		}

		return gmdate( 'c', $timestamp );
	}

	/**
	 * Get URL priority for sitemap
	 *
	 * @since 3.0.0
	 * @param object $url_data URL data.
	 * @return string Priority value.
	 */
	private function get_url_priority( object $url_data ): string {
		// Use match expression for cleaner priority mapping (PHP 8.0+)
		return match ( $url_data->type ?? 'default' ) {
			'case' => '0.8',      // Case detail pages get higher priority
			'procedure' => '0.7',  // Procedure pages get medium priority
			default => '0.6',      // Default priority
		};
	}

	/**
	 * Get URL change frequency for sitemap
	 *
	 * @since 3.0.0
	 * @param object $url_data URL data.
	 * @return string Change frequency.
	 */
	private function get_url_change_frequency( object $url_data ): string {
		// Use match expression for cleaner frequency mapping (PHP 8.0+)
		return match ( $url_data->type ?? 'default' ) {
			'case' => 'monthly',       // Case detail pages change less frequently
			'procedure' => 'weekly',    // Procedure pages change more frequently as new cases are added
			default => 'monthly',       // Default frequency
		};
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
		$cache_key = 'brag_book_gallery_transient_sitemap_last_modified';
		$last_modified = Cache_Manager::get( $cache_key );

		if ( false !== $last_modified ) {
			return $last_modified;
		}

		// Get the latest modification date from API or use current time
		$current_time = current_time( 'c' );
		Cache_Manager::set( $cache_key, $current_time, $this->cache_duration );

		return $current_time;
	}

	/**
	 * Clear sitemap cache
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function clear_sitemap_cache(): void {
		Cache_Manager::delete( 'brag_book_gallery_transient_sitemap_content' );
		Cache_Manager::delete( 'brag_book_gallery_transient_sitemap_last_modified' );

		// Clear any API cache that might affect sitemap data.
		$this->clear_api_cache( 'sitemap' );
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
		return false !== Cache_Manager::get( 'brag_book_gallery_transient_sitemap_content' );
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

	/**
	 * Log error with context information
	 *
	 * Records errors with comprehensive context for debugging purposes.
	 *
	 * @param string $message Error message.
	 * @param array<string, mixed> $context Additional context information.
	 * @return void
	 * @since 3.0.0
	 */
	private function log_error( string $message, array $context = [] ): void {
		$error_id = uniqid( 'sitemap_error_', true );

		$this->validation_errors[ $error_id ] = [
			'message' => $message,
			'context' => $context,
			'timestamp' => time(),
		];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'BRAGBook Sitemap Error [%s]: %s - Context: %s',
				$error_id,
				$message,
				wp_json_encode( $context )
			) );
		}
	}

	/**
	 * Log warning message
	 *
	 * Records warning messages for monitoring purposes.
	 *
	 * @param string $message Warning message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 * @since 3.0.0
	 */
	private function log_warning( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'BRAGBook Sitemap Warning: %s - Context: %s',
				$message,
				wp_json_encode( $context )
			) );
		}
	}

	/**
	 * Track performance metrics
	 *
	 * Records performance data for monitoring and optimization.
	 *
	 * @param string $operation Operation identifier.
	 * @param string $result Result type (hit, miss, generated).
	 * @return void
	 * @since 3.0.0
	 */
	private function track_performance( string $operation, string $result ): void {
		if ( ! isset( $this->performance_metrics[ $operation ] ) ) {
			$this->performance_metrics[ $operation ] = [];
		}

		$this->performance_metrics[ $operation ][] = [
			'result' => $result,
			'timestamp' => microtime( true ),
		];

		// Limit metrics storage to prevent memory issues
		if ( count( $this->performance_metrics[ $operation ] ) > 100 ) {
			$this->performance_metrics[ $operation ] = array_slice(
				$this->performance_metrics[ $operation ],
				-100
			);
		}
	}

	/**
	 * Validate XML content
	 *
	 * Validates XML structure to ensure it's well-formed.
	 *
	 * @param string $xml XML content to validate.
	 * @return bool True if valid, false otherwise.
	 * @since 3.0.0
	 */
	private function validate_xml( string $xml ): bool {
		if ( empty( $xml ) ) {
			return false;
		}

		// Use libxml to validate XML
		$prev_errors = libxml_use_internal_errors( true );
		$doc = new \DOMDocument();

		$valid = $doc->loadXML( $xml );

		if ( ! $valid ) {
			$errors = libxml_get_errors();
			foreach ( $errors as $error ) {
				$this->log_error( 'XML validation error', [
					'message' => $error->message,
					'line' => $error->line,
					'column' => $error->column,
				] );
			}
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		return $valid;
	}

	/**
	 * Get fallback sitemap when generation fails
	 *
	 * Provides a minimal valid sitemap when normal generation fails.
	 *
	 * @return string Basic XML sitemap.
	 * @since 3.0.0
	 */
	private function get_fallback_sitemap(): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// Add main site URL as fallback
		$xml .= '  <url>' . "\n";
		$xml .= '    <loc>' . esc_url( home_url( '/' ) ) . '</loc>' . "\n";
		$xml .= '    <lastmod>' . gmdate( 'c' ) . '</lastmod>' . "\n";
		$xml .= '    <changefreq>daily</changefreq>' . "\n";
		$xml .= '    <priority>1.0</priority>' . "\n";
		$xml .= '  </url>' . "\n";

		// Try to add gallery page if available
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );
		if ( ! empty( $gallery_slug ) ) {
			$slug = is_array( $gallery_slug ) ? reset( $gallery_slug ) : $gallery_slug;
			if ( ! empty( $slug ) ) {
				$xml .= '  <url>' . "\n";
				$xml .= '    <loc>' . esc_url( home_url( '/' . $slug . '/' ) ) . '</loc>' . "\n";
				$xml .= '    <lastmod>' . gmdate( 'c' ) . '</lastmod>' . "\n";
				$xml .= '    <changefreq>weekly</changefreq>' . "\n";
				$xml .= '    <priority>0.8</priority>' . "\n";
				$xml .= '  </url>' . "\n";
			}
		}

		$xml .= '</urlset>' . "\n";

		return $xml;
	}

	/**
	 * Validate and sanitize URL
	 *
	 * Ensures URLs are valid and safe for inclusion in sitemap.
	 *
	 * @param string $url URL to validate.
	 * @return string|null Sanitized URL or null if invalid.
	 * @since 3.0.0
	 */
	private function validate_url( string $url ): ?string {
		if ( empty( $url ) ) {
			return null;
		}

		$url = esc_url_raw( $url );

		// Ensure URL is from current site
		if ( ! str_starts_with( $url, home_url() ) ) {
			$this->log_error( 'Invalid URL domain', [ 'url' => $url ] );
			return null;
		}

		return $url;
	}

	/**
	 * Sanitize API tokens array
	 *
	 * Validates and sanitizes API tokens for secure usage.
	 *
	 * @param mixed $tokens Raw tokens input.
	 * @return array<string> Sanitized tokens array.
	 * @since 3.0.0
	 */
	private function sanitize_api_tokens( $tokens ): array {
		if ( ! is_array( $tokens ) ) {
			return [];
		}

		return array_values( array_filter( array_map( function( $token ) {
			if ( ! is_string( $token ) ) {
				return '';
			}
			// Basic token validation - should be alphanumeric with some special chars
			$token = sanitize_text_field( $token );
			if ( ! preg_match( '/^[a-zA-Z0-9\-_\.]+$/', $token ) ) {
				$this->log_warning( 'Invalid API token format detected' );
				return '';
			}
			return $token;
		}, $tokens ) ) );
	}

	/**
	 * Sanitize property IDs array
	 *
	 * Validates and sanitizes website property IDs.
	 *
	 * @param mixed $ids Raw IDs input.
	 * @return array<int> Sanitized IDs array.
	 * @since 3.0.0
	 */
	private function sanitize_property_ids( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return [];
		}

		return array_values( array_filter( array_map( function( $id ) {
			// Ensure ID is numeric and positive
			$id = absint( $id );
			if ( $id <= 0 || $id > 999999 ) {
				$this->log_warning( 'Invalid property ID detected', [ 'id' => $id ] );
				return 0;
			}
			return $id;
		}, $ids ) ) );
	}

	/**
	 * Sanitize sitemap data from API response
	 *
	 * Deep sanitization of API response data for security.
	 *
	 * @param array<mixed> $data Raw API data.
	 * @return array<mixed> Sanitized data.
	 * @since 3.0.0
	 */
	private function sanitize_sitemap_data( array $data ): array {
		$sanitized = [];

		foreach ( $data as $org_data ) {
			if ( ! is_array( $org_data ) ) {
				continue;
			}

			$sanitized_org = [];
			foreach ( $org_data as $item ) {
				if ( ! is_object( $item ) && ! is_array( $item ) ) {
					continue;
				}

				// Convert arrays to objects for consistency
				$item = (object) $item;

				// Sanitize URL data
				if ( isset( $item->url ) ) {
					$item->url = sanitize_text_field( $item->url );
				}

				if ( isset( $item->updatedAt ) ) {
					$item->updatedAt = sanitize_text_field( $item->updatedAt );
				}

				if ( isset( $item->type ) ) {
					$item->type = sanitize_key( $item->type );
				}

				// Sanitize image data if present
				if ( isset( $item->images ) && is_array( $item->images ) ) {
					$item->images = array_map( function( $image ) {
						if ( ! is_object( $image ) && ! is_array( $image ) ) {
							return null;
						}
						$image = (object) $image;

						if ( isset( $image->url ) ) {
							$image->url = esc_url_raw( $image->url );
						}
						if ( isset( $image->caption ) ) {
							$image->caption = sanitize_text_field( $image->caption );
						}
						if ( isset( $image->title ) ) {
							$image->title = sanitize_text_field( $image->title );
						}

						return $image;
					}, $item->images );

					$item->images = array_filter( $item->images );
				}

				$sanitized_org[] = $item;
			}

			if ( ! empty( $sanitized_org ) ) {
				$sanitized[] = $sanitized_org;
			}
		}

		return $sanitized;
	}

	/**
	 * Check if request is rate limited
	 *
	 * Implements basic rate limiting for API requests.
	 *
	 * @param string $identifier Rate limit identifier.
	 * @param int $limit Maximum requests allowed.
	 * @param int $window Time window in seconds.
	 * @return bool True if rate limited, false otherwise.
	 * @since 3.0.0
	 */
	private function is_rate_limited( string $identifier, int $limit = 10, int $window = 60 ): bool {
		$cache_key = 'brag_book_gallery_transient_rate_limit_' . $identifier;
		$requests = Cache_Manager::get( $cache_key ) ?: 0;

		if ( $requests >= $limit ) {
			$this->log_warning( 'Rate limit exceeded', [
				'identifier' => $identifier,
				'requests' => $requests,
				'limit' => $limit
			] );
			return true;
		}

		Cache_Manager::set( $cache_key, $requests + 1, $window );
		return false;
	}

	/**
	 * Get cached data with performance tracking
	 *
	 * Implements multi-level caching for optimal performance.
	 *
	 * @param string $cache_key Cache identifier.
	 * @param callable $callback Callback to generate data if not cached.
	 * @param int $ttl Cache TTL in seconds.
	 * @return mixed Cached or generated data.
	 * @since 3.0.0
	 */
	private function get_cached_data( string $cache_key, callable $callback, int $ttl = self::CACHE_TTL_SITEMAP ) {
		$start_time = microtime( true );

		// Check memory cache first (fastest)
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			$this->track_performance( 'cache_lookup', 'memory_hit' );
			return $this->memory_cache[ $cache_key ];
		}

		// Check transient cache (second fastest)
		$cached_data = Cache_Manager::get( $cache_key );
		if ( false !== $cached_data ) {
			$this->memory_cache[ $cache_key ] = $cached_data;
			$this->track_performance( 'cache_lookup', 'transient_hit' );
			return $cached_data;
		}

		// Generate data if not cached
		try {
			$data = $callback();

			// Store in both cache levels
			$this->memory_cache[ $cache_key ] = $data;
			Cache_Manager::set( $cache_key, $data, $ttl );

			$duration = microtime( true ) - $start_time;
			if ( $duration > 1.0 ) { // Log slow operations
				$this->log_warning( 'Slow sitemap generation', [ 'duration' => $duration ] );
			}

			$this->track_performance( 'cache_lookup', 'generated' );
			return $data;

		} catch ( \Exception $e ) {
			$this->log_error( 'Cache callback failed', [
				'cache_key' => $cache_key,
				'error' => $e->getMessage()
			] );
			return null;
		}
	}

	/**
	 * Optimize memory usage
	 *
	 * Cleans up memory cache to prevent excessive memory usage.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function optimize_memory(): void {
		$max_cache_entries = 20;

		// Clean memory cache if too large
		if ( count( $this->memory_cache ) > $max_cache_entries ) {
			$this->memory_cache = array_slice( $this->memory_cache, -$max_cache_entries, null, true );
		}

		// Clean performance metrics if too large
		foreach ( $this->performance_metrics as $operation => &$metrics ) {
			if ( count( $metrics ) > 100 ) {
				$metrics = array_slice( $metrics, -100 );
			}
		}
	}

	/**
	 * Warm up sitemap cache proactively
	 *
	 * Pre-loads sitemap data to improve initial performance.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function warm_cache(): void {
		try {
			// Pre-generate sitemap content
			$this->generate_sitemap();

			// Optimize memory after generation
			$this->optimize_memory();

			$this->log_warning( 'Sitemap cache warmed successfully' );
		} catch ( \Exception $e ) {
			$this->log_error( 'Cache warm-up failed', [ 'error' => $e->getMessage() ] );
		}
	}

	/**
	 * Get performance metrics for monitoring
	 *
	 * Returns performance data for analysis and optimization.
	 *
	 * @return array<string, mixed> Performance metrics.
	 * @since 3.0.0
	 */
	public function get_performance_metrics(): array {
		$metrics = [
			'memory_cache_size' => count( $this->memory_cache ),
			'validation_errors' => count( $this->validation_errors ),
			'operations' => [],
		];

		foreach ( $this->performance_metrics as $operation => $data ) {
			$hits = $misses = $generated = 0;

			foreach ( $data as $entry ) {
				match ( $entry['result'] ?? '' ) {
					'memory_hit', 'transient_hit', 'cache_hit' => $hits++,
					'cache_miss' => $misses++,
					'generated' => $generated++,
					default => null
				};
			}

			$total = count( $data );
			$metrics['operations'][ $operation ] = [
				'total' => $total,
				'hits' => $hits,
				'misses' => $misses,
				'generated' => $generated,
				'hit_rate' => $total > 0 ? round( ($hits / $total) * 100, 1 ) : 0,
			];
		}

		return $metrics;
	}

	/**
	 * Batch process sitemap URLs for better performance
	 *
	 * Processes URLs in chunks to prevent memory issues.
	 *
	 * @param array<mixed> $urls URLs to process.
	 * @param int $batch_size Batch size for processing.
	 * @return \Generator Yields processed URL batches.
	 * @since 3.0.0
	 */
	private function batch_process_urls( array $urls, int $batch_size = 100 ): \Generator {
		$chunks = array_chunk( $urls, $batch_size );

		foreach ( $chunks as $chunk ) {
			yield $chunk;

			// Free up memory between batches
			if ( memory_get_usage( true ) > 100 * 1024 * 1024 ) { // 100MB threshold
				$this->optimize_memory();
			}
		}
	}
}
