<?php
/**
 * On-Page SEO Handler
 *
 * Enterprise-grade SEO management system for BRAGBook Gallery plugin.
 * Provides comprehensive on-page SEO optimization with advanced meta tag management,
 * dynamic title/description generation, and seamless SEO plugin integration.
 *
 * Features:
 * - Dynamic SEO metadata generation based on gallery content
 * - Multi-plugin SEO integration (Yoast, AIOSEO, RankMath)
 * - Advanced caching strategies for optimal performance
 * - Comprehensive input validation and sanitization
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

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\SEO;

use BRAGBookGallery\Includes\REST\Endpoints;
use BRAGBookGallery\Includes\Traits\Trait_Sanitizer;
use BRAGBookGallery\Includes\Traits\Trait_Tools;

/**
 * Enterprise On-Page SEO Management
 *
 * Comprehensive SEO optimization system with advanced features:
 *
 * Core Responsibilities:
 * - Dynamic meta tag generation for gallery pages
 * - Multi-plugin SEO integration and compatibility
 * - Advanced caching for optimal performance
 * - Structured data and Open Graph optimization
 * - Canonical URL management and validation
 *
 * Enterprise Features:
 * - WordPress VIP compliant architecture
 * - Multi-level caching with intelligent invalidation
 * - Comprehensive input validation and sanitization
 * - Performance monitoring and optimization
 * - Modern PHP 8.2+ type safety and features
 * - Security enhancements for user data protection
 *
 * @since 3.0.0
 */
class On_Page {
	use Trait_Sanitizer;
	use Trait_Tools;

	/**
	 * SEO plugin identifiers
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const SEO_PLUGIN_YOAST = 1;
	private const SEO_PLUGIN_AIOSEO = 2;
	private const SEO_PLUGIN_RANKMATH = 3;
	private const SEO_PLUGIN_NONE = 0;

	/**
	 * Cache duration constants for different data types
	 *
	 * @since 3.0.0
	 * Note: Using CACHE_TTL_* constants from Trait_Tools
	 */
	private const CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * SEO data for current page
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $seo_data;

	/**
	 * API handler instance
	 *
	 * @since 3.0.0
	 * @var Endpoints
	 */
	private Endpoints $api_handler;

	/**
	 * Performance metrics cache
	 *
	 * @since 3.0.0
	 * @var array<string, array>
	 */
	private array $performance_cache = [];

	/**
	 * Memory cache for frequently accessed data
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];


	/**
	 * Constructor - Initializes SEO data and hooks
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->api_handler = new Endpoints();
		$this->seo_data    = $this->get_custom_title_and_description();
		add_action( 'wp', [ $this, 'initialize_seo' ] );
	}

	/**
	 * Get current page URL with enhanced security validation
	 *
	 * Builds the complete URL for the current page with comprehensive validation
	 * including protocol detection, host validation, and request URI sanitization.
	 *
	 * Security Features:
	 * - Host validation to prevent header injection attacks
	 * - Request URI sanitization to prevent malicious URLs
	 * - Protocol detection with HTTPS preference
	 * - Input validation to prevent empty or malformed URLs
	 *
	 * @since 3.0.0
	 *
	 * @return string Current page URL with validation, empty string if invalid
	 */
	public function get_current_url(): string {
		// Use modern null coalescing operators and validation
		$protocol    = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
		$host        = $_SERVER['HTTP_HOST'] ?? '';
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Enhanced validation for security
		if ( empty( $host ) || empty( $request_uri ) || ! $this->is_valid_host( $host ) ) {
			return '';
		}

		return esc_url( $protocol . '://' . $host . $request_uri );
	}

	/**
	 * Get custom SEO title and description for BRAG book pages
	 *
	 * Advanced SEO metadata generation system that analyzes URL structure,
	 * retrieves relevant gallery data, and generates optimized SEO content.
	 *
	 * Page Type Analysis:
	 * - Gallery listing pages: Uses configured SEO titles and descriptions
	 * - Procedure pages: Generates dynamic titles with case counts
	 * - Case detail pages: Creates patient-specific SEO metadata
	 * - Special pages: Handles favorites and consultation pages
	 *
	 * @since 3.0.0
	 *
	 * @return array{
	 *     bb_title: string,
	 *     bb_description: string,
	 *     bb_procedure_name: string
	 * } SEO data array with comprehensive metadata
	 */
	public function get_custom_title_and_description(): array {
		$site_title = get_bloginfo( 'name' );
		$url_parts  = $this->parse_current_url();

		// Initialize return data with modern array syntax
		$seo_data = [
			'bb_title'          => '',
			'bb_description'    => '',
			'bb_procedure_name' => ''
		];

		// Get configuration options
		$config = $this->get_seo_configuration();

		// Determine page type and get appropriate SEO data
		if ( $this->is_gallery_listing_page( $url_parts ) ) {
			$seo_data = $this->get_gallery_listing_seo(
				$url_parts,
				$config,
				$site_title
			);
		} elseif ( $this->is_procedure_page( $url_parts ) ) {
			$seo_data = $this->get_procedure_page_seo(
				$url_parts,
				$config,
				$site_title
			);
		} elseif ( $this->is_case_details_page( $url_parts ) ) {
			$seo_data = $this->get_case_details_seo(
				$url_parts,
				$config,
				$site_title
			);
		}

		return $seo_data;
	}

	/**
	 * Parse current URL into parts with security validation
	 *
	 * Safely parses the current URL with comprehensive security measures
	 * to prevent malicious URL manipulation and injection attacks.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string> Validated URL parts, empty array if invalid
	 */
	private function parse_current_url(): array {
		try {
			// Safely get and validate REQUEST_URI
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';

			// Security validation for malicious patterns
			if ( ! $this->is_safe_request_uri( $request_uri ) ) {
				$this->log_validation_error( 'Potentially malicious REQUEST_URI detected' );
				return [];
			}

			// Remove query parameters and sanitize
			$url_without_query = strtok( $request_uri, '?' );
			$clean_url         = trim( $url_without_query, '/' );

			// Additional validation for URL structure
			if ( empty( $clean_url ) ) {
				return [];
			}

			// Split and validate each URL segment
			$url_parts = explode( '/', $clean_url );
			return array_map( [ $this, 'sanitize_url_segment' ], $url_parts );

		} catch ( \Exception $e ) {
			$this->log_validation_error( 'Error parsing URL: ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Get comprehensive SEO configuration from WordPress options
	 *
	 * Retrieves and consolidates all SEO-related configuration data
	 * with proper type casting and validation.
	 *
	 * @since 3.0.0
	 *
	 * @return array{
	 *     brag_book_gallery_page_slug: string,
	 *     brag_book_gallery_page_id: int,
	 *     api_tokens: array<string>,
	 *     website_property_ids: array<int>,
	 *     gallery_slugs: array<string>,
	 *     seo_titles: array<string>,
	 *     seo_descriptions: array<string>,
	 *     brag_book_gallery_seo_title: string,
	 *     brag_book_gallery_seo_description: string
	 * } Complete configuration array with typed structure
	 */
	private function get_seo_configuration(): array {
		// Use helper to get the first slug
		$page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( '' );

		return [
			'brag_book_gallery_page_slug'    => $page_slug,
			'brag_book_gallery_page_id' => (int) get_option(
				'brag_book_gallery_page_id',
				0
			),
			'api_tokens'              => (array) get_option(
				'brag_book_gallery_api_token',
				array()
			),
			'website_property_ids'    => (array) get_option(
				'brag_book_gallery_website_property_id',
				array()
			),
			'gallery_slugs'           => (array) get_option(
				'brag_book_gallery_gallery_page_slug',
				array()
			),
			'seo_titles'              => (array) get_option(
				'brag_book_gallery_seo_page_title',
				array()
			),
			'seo_descriptions'        => (array) get_option(
				'brag_book_gallery_seo_page_description',
				array()
			),
			'brag_book_gallery_seo_title'       => (string) get_option(
				'brag_book_gallery_seo_page_title',
				''
			),
			'brag_book_gallery_seo_description' => (string) get_option(
				'brag_book_gallery_seo_page_description',
				''
			),
		];
	}

	/**
	 * Check if current page is a gallery listing
	 *
	 * @param array $url_parts URL parts.
	 *
	 * @return bool True if gallery listing page.
	 * @since 3.0.0
	 */
	private function is_gallery_listing_page( array $url_parts ): bool {
		return isset( $url_parts[0] ) && empty( $url_parts[1] ) && empty( $url_parts[2] );
	}

	/**
	 * Check if current page is a procedure page
	 *
	 * @param array $url_parts URL parts.
	 *
	 * @return bool True if procedure page.
	 * @since 3.0.0
	 */
	private function is_procedure_page( array $url_parts ): bool {
		return isset( $url_parts[1] ) && empty( $url_parts[2] );
	}

	/**
	 * Check if current page is a case details page
	 *
	 * @param array $url_parts URL parts.
	 *
	 * @return bool True if case details page.
	 * @since 3.0.0
	 */
	private function is_case_details_page( array $url_parts ): bool {
		return isset( $url_parts[2] ) && ! empty( $url_parts[2] );
	}

	/**
	 * Get SEO data for gallery listing page with intelligent matching
	 *
	 * Analyzes URL structure to determine the appropriate SEO metadata
	 * for gallery listing pages, including main gallery and sub-galleries.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $url_parts  Parsed URL segments for analysis
	 * @param array<string, mixed> $config Configuration data from WordPress options
	 * @param string $site_title Site title for fallback generation
	 *
	 * @return array{
	 *     title: string,
	 *     description: string,
	 *     procedure_name: string
	 * } SEO metadata for gallery listing page
	 */
	private function get_gallery_listing_seo( array $url_parts, array $config, string $site_title ): array {
		$first_segment = $url_parts[0] ?? '';

		if ( $first_segment === $config['brag_book_gallery_page_slug'] ) {
			return [
				'title'          => $config['brag_book_gallery_seo_title'],
				'description'    => $config['brag_book_gallery_seo_description'],
				'procedure_name' => ''
			];
		}

		// Find matching gallery
		foreach ( $config['gallery_slugs'] as $index => $slug ) {
			if ( $slug === $first_segment ) {
				return [
					'title'          => $config['seo_titles'][ $index ] ?? '',
					'description'    => $config['seo_descriptions'][ $index ] ?? '',
					'procedure_name' => ''
				];
			}
		}

		return [
			'title'          => '',
			'description'    => '',
			'procedure_name' => ''
		];
	}

	/**
	 * Get SEO data for procedure page
	 *
	 * @param array $url_parts URL parts.
	 * @param array $config Configuration.
	 * @param string $site_title Site title.
	 *
	 * @return array SEO data.
	 * @since 3.0.0
	 */
	private function get_procedure_page_seo( array $url_parts, array $config, string $site_title ): array {
		$procedure_slug = $url_parts[1] ?? '';

		// Skip special pages using modern match expression
		if ( match ( $procedure_slug ) {
			'favorites', 'consultation', 'myfavorites' => true,
			default => false
		} ) {
			return [
				'bb_title'          => '',
				'bb_description'    => '',
				'bb_procedure_name' => ''
			];
		}

		$is_combine = $url_parts[0] === $config['brag_book_gallery_page_slug'];
		$api_tokens = $is_combine ? array_values( $config['api_tokens'] ) : $this->get_matching_api_token( $url_parts[0], $config );

		$procedure_data = $this->get_procedure_data_from_sidebar( $api_tokens, $procedure_slug, $is_combine );

		$procedure_name = ucwords( str_replace( '-', ' ', $procedure_slug ) );
		$total_cases    = $procedure_data['procedureTotalCase'] ?? 0;

		$title = sprintf(
			'Before and After %s Gallery, %d Cases - %s',
			$procedure_name,
			$total_cases,
			$site_title
		);

		return [
			'bb_title'          => $title,
			'bb_description'    => '',
			'bb_procedure_name' => $procedure_data['bb_procedure_name'] ?? $procedure_name
		];
	}

	/**
	 * Get SEO data for case details page
	 *
	 * @param array $url_parts URL parts.
	 * @param array $config Configuration.
	 * @param string $site_title Site title.
	 *
	 * @return array SEO data.
	 * @since 3.0.0
	 */
	private function get_case_details_seo( array $url_parts, array $config, string $site_title ): array {
		$case_identifier = $url_parts[2];
		$is_case_id      = str_contains( $case_identifier, 'bb-case' );

		// Extract case ID or SEO suffix
		$case_id    = null;
		$seo_suffix = null;

		if ( $is_case_id ) {
			preg_match( '/\d+/', $case_identifier, $matches );
			$case_id = isset( $matches[0] ) ? (int) $matches[0] : null;
		} else {
			$seo_suffix = $case_identifier;
		}

		// Get case data
		$case_data = $this->fetch_case_data( $url_parts, $config, $case_id, $seo_suffix );

		if ( empty( $case_data ) ) {
			return [
				'bb_title'          => '',
				'bb_description'    => '',
				'bb_procedure_name' => ''
			];
		}

		return $this->build_case_seo_data( $case_data, $url_parts[1], $site_title );
	}

	/**
	 * Fetch case data from API with intelligent caching
	 *
	 * Retrieves case data from the BRAG book API using either case ID or
	 * SEO suffix, with comprehensive error handling and caching strategies.
	 *
	 * Features:
	 * - Supports both combined and individual gallery configurations
	 * - Intelligent caching with configurable TTL
	 * - Comprehensive error handling and validation
	 * - API response validation and JSON parsing
	 * - Fallback mechanisms for failed requests
	 *
	 * @since 3.0.0
	 *
	 * @param array<string> $url_parts  URL segments for gallery identification
	 * @param array<string, mixed> $config Plugin configuration data
	 * @param int|null $case_id Case identifier (numeric)
	 * @param string|null $seo_suffix SEO-friendly URL suffix
	 *
	 * @return array<string, mixed> Case data from API, empty array if not found
	 *
	 * @throws \JsonException When API response contains invalid JSON
	 */
	private function fetch_case_data( array $url_parts, array $config, ?int $case_id, ?string $seo_suffix ): array {
		$is_combine     = $url_parts[0] === $config['brag_book_gallery_page_slug'];
		$procedure_slug = $url_parts[1];

		if ( $is_combine ) {
			$api_tokens     = array_values( $config['api_tokens'] );
			$procedure_data = $this->get_procedure_data_from_sidebar( $api_tokens, $procedure_slug, true );
			$procedure_ids  = $procedure_data['bb_procedure_id'] ?? [];

			$data = $this->api_handler->get_case_data(
				$case_id ?? 0,
				$seo_suffix ?? '',
				$api_tokens,
				$procedure_ids,
				$config['website_property_ids']
			);

			// Check for valid data
			if ( empty( $data ) ) {
				return [];
			}
		} else {
			$api_token           = $this->get_matching_api_token( $url_parts[0], $config );
			$website_property_id = $this->get_matching_website_property_id( $url_parts[0], $config );

			if ( empty( $api_token ) || empty( $website_property_id ) ) {
				return [];
			}

			$procedure_data = $this->get_procedure_data_from_sidebar( $api_token, $procedure_slug, false );
			$procedure_id   = $procedure_data['bb_procedure_id'] ?? null;

			// Enhanced caching for case data with memory layer
			$cache_key = $this->generate_cache_key( 'case_data', [
				'api_token'   => $api_token,
				'procedure_id' => $procedure_id,
				'property_id' => $website_property_id,
				'case_id'     => $case_id,
				'seo_suffix'  => $seo_suffix
			] );

			// Check memory cache first
			if ( isset( $this->memory_cache[ $cache_key ] ) ) {
				$data = $this->memory_cache[ $cache_key ];
				$this->track_cache_performance( 'case_data', true, 'memory' );
			} else {
				// Check transient cache
				$data = get_transient( $cache_key );

				if ( $data !== false ) {
					$this->memory_cache[ $cache_key ] = $data;
					$this->track_cache_performance( 'case_data', true, 'transient' );
				} else {
					// Fetch from API with performance tracking
					$start_time = microtime( true );
					$data = $this->api_handler->get_case_data(
						$case_id ?? 0,
						$seo_suffix ?? '',
						$api_token,
						$procedure_id,
						$website_property_id
					);
					$fetch_duration = microtime( true ) - $start_time;

					// Cache valid responses with intelligent TTL
					if ( ! empty( $data ) ) {
						$cache_duration = $this->get_cache_duration_for_data_type( 'case_data' );
						set_transient( $cache_key, $data, $cache_duration );
						$this->memory_cache[ $cache_key ] = $data;
						$this->track_cache_performance( 'case_data', false, 'api', $fetch_duration );
					}
				}
			}
		}

		// Enhanced error handling and validation
		try {
			// Validate API response data
			if ( empty( $data ) || ! is_string( $data ) ) {
				$this->log_validation_error( 'Empty or invalid API response data' );
				return [];
			}

			// Use JSON_THROW_ON_ERROR for better error handling
			$decoded_data = json_decode( $data, true, 512, JSON_THROW_ON_ERROR );

			// Validate decoded structure
			if ( ! is_array( $decoded_data ) ) {
				$this->log_validation_error( 'API response is not a valid array structure' );
				return [];
			}

			// Validate required data structure
			if ( ! isset( $decoded_data['data'] ) || ! is_array( $decoded_data['data'] ) ) {
				$this->log_validation_error( 'API response missing required data structure' );
				return [];
			}

			return $decoded_data['data'][0] ?? [];

		} catch ( \JsonException $e ) {
			$this->log_validation_error( 'JSON parsing error: ' . $e->getMessage() );
			return [];
		} catch ( \Exception $e ) {
			$this->log_validation_error( 'Unexpected error in case data processing: ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Build SEO data for case details
	 *
	 * @param array $case_data Case data from API.
	 * @param string $procedure_slug Procedure slug.
	 * @param string $site_title Site title.
	 *
	 * @return array SEO data.
	 * @since 3.0.0
	 */
	private function build_case_seo_data( array $case_data, string $procedure_slug, string $site_title ): array {
		$case_details   = $case_data['caseDetails'][0] ?? [];
		$procedure_name = ucwords( str_replace( '-', ' ', $procedure_slug ) );

		// Get case number
		$case_number = $this->get_case_number( $case_data );

		// Build title
		$title = '';
		if ( ! empty( $case_details['seoPageTitle'] ) ) {
			$title = $case_details['seoPageTitle'] . ' - ' . $site_title;
		} else {
			$title = sprintf(
				'Before and After %s: Patient %d - %s',
				$procedure_name,
				$case_number,
				$site_title
			);
		}

		// Get description
		$description = $case_details['seoPageDescription'] ?? '';

		return [
			'bb_title'          => $title,
			'bb_description'    => $description,
			'bb_procedure_name' => $procedure_name
		];
	}

	/**
	 * Get case number from case data
	 *
	 * @param array $case_data Case data.
	 *
	 * @return int Case number.
	 * @since 3.0.0
	 */
	private function get_case_number( array $case_data ): int {
		$case_ids = $case_data['caseIds'] ?? [];

		foreach ( $case_ids as $key => $case_item ) {
			if ( isset( $case_item['id'] ) && $case_item['id'] == $case_data['id'] ) {
				return $key + 1;
			}
		}

		return 1;
	}

	/**
	 * Get matching API token for gallery slug
	 *
	 * @param string $gallery_slug Gallery slug.
	 * @param array $config Configuration.
	 *
	 * @return string|array API token(s).
	 * @since 3.0.0
	 */
	private function get_matching_api_token( string $gallery_slug, array $config ): string|array {
		foreach ( $config['gallery_slugs'] as $index => $slug ) {
			if ( $slug === $gallery_slug ) {
				return $config['api_tokens'][ $index ] ?? '';
			}
		}

		return '';
	}

	/**
	 * Get matching website property ID for gallery slug
	 *
	 * @param string $gallery_slug Gallery slug.
	 * @param array $config Configuration.
	 *
	 * @return string Website property ID.
	 * @since 3.0.0
	 */
	private function get_matching_website_property_id( string $gallery_slug, array $config ): string {
		foreach ( $config['gallery_slugs'] as $index => $slug ) {
			if ( $slug === $gallery_slug ) {
				return $config['website_property_ids'][ $index ] ?? '';
			}
		}

		return '';
	}

	/**
	 * Get procedure data from sidebar API
	 *
	 * @param string|array $api_tokens API token(s).
	 * @param string $procedure_slug Procedure slug.
	 * @param bool $is_combine Whether this is a combined gallery.
	 *
	 * @return array Procedure data.
	 * @since 3.0.0
	 */
	public function get_procedure_data_from_sidebar( string|array $api_tokens, string $procedure_slug, bool $is_combine ): array {
		// Enhanced caching with memory layer and intelligent TTL
		$cache_key = $this->generate_cache_key( 'sidebar', [
			'procedure_slug' => $procedure_slug,
			'is_combine'     => $is_combine,
			'tokens'         => is_array( $api_tokens ) ? implode( '|', $api_tokens ) : $api_tokens
		] );

		// Check memory cache first
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			$this->track_cache_performance( 'sidebar', true, 'memory' );
			return $this->memory_cache[ $cache_key ];
		}

		// Check transient cache
		$sidebar_data = get_transient( $cache_key );
		if ( $sidebar_data !== false ) {
			// If cached data is already processed (array), return it
			if ( is_array( $sidebar_data ) ) {
				$this->memory_cache[ $cache_key ] = $sidebar_data;
				$this->track_cache_performance( 'sidebar', true, 'transient' );
				return $sidebar_data;
			}
			// If cached data is raw JSON string, continue to process it below
		}

		// Fetch from API with performance tracking
		$start_time = microtime( true );
		$sidebar_data = $this->api_handler->get_api_sidebar( $api_tokens );
		$fetch_duration = microtime( true ) - $start_time;

		// Enhanced error handling for sidebar data
		$result = [];
		try {
			// Validate sidebar data before processing
			if ( empty( $sidebar_data ) || ! is_string( $sidebar_data ) ) {
				$this->log_validation_error( 'Empty or invalid sidebar data' );
				$result = [
					'bb_procedure_id'    => null,
					'bb_procedure_name'  => '',
					'procedureTotalCase' => 0
				];
			} else {
				// Use JSON_THROW_ON_ERROR for comprehensive error handling
				$sidebar = json_decode( $sidebar_data, false, 512, JSON_THROW_ON_ERROR );

				// Validate sidebar structure
				if ( ! isset( $sidebar->data ) || ! is_array( $sidebar->data ) ) {
					$this->log_validation_error( 'Sidebar data missing required structure' );
					$result = [
						'bb_procedure_id'    => null,
						'bb_procedure_name'  => '',
						'procedureTotalCase' => 0
					];
				} else {
					// Search for matching procedure
					$found = false;
					foreach ( $sidebar->data as $category ) {
						foreach ( $category->procedures as $procedure ) {
							if ( $procedure->slugName === $procedure_slug ) {
								$result = [
									'bb_procedure_id'    => $is_combine ? $procedure->ids : ( $procedure->ids[0] ?? null ),
									'bb_procedure_name'  => $procedure->name ?? '',
									'procedureTotalCase' => $procedure->totalCase ?? 0
								];
								$found = true;
								break 2;
							}
						}
					}

					if ( ! $found ) {
						$result = [
							'bb_procedure_id'    => null,
							'bb_procedure_name'  => '',
							'procedureTotalCase' => 0
						];
					}
				}
			}

		} catch ( \JsonException $e ) {
			$this->log_validation_error( 'JSON parsing error in sidebar data: ' . $e->getMessage() );
			$result = [
				'bb_procedure_id'    => null,
				'bb_procedure_name'  => '',
				'procedureTotalCase' => 0
			];
		} catch ( \Exception $e ) {
			$this->log_validation_error( 'Unexpected error in sidebar processing: ' . $e->getMessage() );
			$result = [
				'bb_procedure_id'    => null,
				'bb_procedure_name'  => '',
				'procedureTotalCase' => 0
			];
		}

		// Cache the processed result with intelligent TTL
		if ( ! empty( $sidebar_data ) ) {
			$cache_duration = $this->get_cache_duration_for_data_type( 'sidebar' );
			set_transient( $cache_key, $result, $cache_duration );
			$this->memory_cache[ $cache_key ] = $result;
			$this->track_cache_performance( 'sidebar', false, 'api', $fetch_duration );
		}

		return $result;
	}

	/**
	 * Get custom BRAG book title
	 *
	 * @return string SEO title.
	 * @since 3.0.0
	 */
	public function get_custom_title(): string {
		return $this->seo_data['bb_title'] ?? '';
	}

	/**
	 * Get custom BRAG book description
	 *
	 * @return string SEO description.
	 * @since 3.0.0
	 */
	public function get_custom_description(): string {
		return $this->seo_data['bb_description'] ?? '';
	}

	/**
	 * Print meta description tag
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function print_custom_description(): void {
		$description = $this->get_custom_description();

		// Enhanced security validation for description content
		if ( ! empty( $description ) && $this->is_safe_description( $description ) ) {
			$sanitized_description = esc_attr( wp_strip_all_tags( $description ) );
			echo '<meta name="description" content="' . $sanitized_description . '">' . "\n";
		}
	}

	/**
	 * Print canonical URL tag
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function print_canonical(): void {
		$url = $this->get_current_url();

		// Enhanced security validation for canonical URL
		if ( ! empty( $url ) && $this->is_safe_canonical_url( $url ) ) {
			$sanitized_url = esc_url( $url );
			echo '<link rel="canonical" href="' . $sanitized_url . '">' . "\n";
		}
	}

	/**
	 * Initialize SEO hooks based on active plugin with modern architecture
	 *
	 * Dynamically configures SEO hooks based on the selected SEO plugin,
	 * ensuring compatibility with Yoast, AIOSEO, RankMath, or default WordPress.
	 *
	 * Plugin Support:
	 * - Yoast SEO: Complete integration with all meta tags and Open Graph
	 * - All in One SEO: Full compatibility with AIOSEO filters
	 * - RankMath: Comprehensive integration with RankMath hooks
	 * - Default WordPress: Native WordPress SEO implementation
	 *
	 * Security Features:
	 * - Admin context detection to prevent frontend interference
	 * - BRAG book page validation to ensure proper scope
	 * - Hook sanitization to prevent conflicts
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function initialize_seo(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->is_bragbook_page() ) {
			return;
		}

		$seo_plugin = (int) get_option( 'brag_book_gallery_seo_plugin_selector', self::SEO_PLUGIN_NONE );

		// Use modern match expression for cleaner code
		match ( $seo_plugin ) {
			self::SEO_PLUGIN_YOAST => $this->setup_yoast_filters(),
			self::SEO_PLUGIN_AIOSEO => $this->setup_aioseo_filters(),
			self::SEO_PLUGIN_RANKMATH => $this->setup_rankmath_filters(),
			default => $this->setup_default_filters()
		};
	}

	/**
	 * Check if current page is a BRAG book gallery page
	 *
	 * @return bool True if BRAG book page.
	 * @since 3.0.0
	 */
	private function is_bragbook_page(): bool {

		// Get current page ID.
		$current_page_id = get_queried_object_id();

		// Get stored gallery page IDs and combine gallery settings.
		$stored_pages_ids        = (array) get_option(
			option: 'brag_book_gallery_stored_pages_ids',
			default_value: array()
		);

		// Ensure all IDs are integers.
		$gallery_page_id = (int) get_option(
			option: 'brag_book_gallery_page_id',
			default_value: 0
		);

		// Get all gallery page slugs
		$brag_book_gallery_page_slugs = \BRAGBookGallery\Includes\Core\Slug_Helper::get_all_gallery_page_slugs();

		$current_post = get_post( $current_page_id );
		$current_slug = $current_post->post_name ?? '';

		return in_array( $current_page_id, $stored_pages_ids, true )
		       || $current_page_id === $gallery_page_id
		       || in_array( $current_slug, $brag_book_gallery_page_slugs, true );
	}

	/**
	 * Setup Yoast SEO filters
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function setup_yoast_filters(): void {

		// Yoast uses a dynamic hook for canonical URLs, so we hook into it directly.
		add_filter(
			'wpseo_canonical',
			array( $this, 'get_current_url' )
		);

		// Other Yoast filters for title and description.
		add_filter(
			'wpseo_title',
			array( $this, 'get_custom_title' )
		);

		// Description filter.
		add_filter(
			'wpseo_metadesc',
			array( $this, 'get_custom_description' )
		);

		// Open Graph filters.
		add_filter(
			'wpseo_opengraph_title',
			array( $this, 'get_custom_title' )
		);

		// Open Graph description filter.
		add_filter(
			'wpseo_opengraph_desc',
			array( $this, 'get_custom_description' )
		);

		// Open Graph URL filter.
		add_filter(
			'wpseo_opengraph_url',
			array( $this, 'get_current_url' )
		);
	}

	/**
	 * Setup AIOSEO filters
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function setup_aioseo_filters(): void {

		// AIOSEO uses a dynamic hook for canonical URLs, so we hook into it directly.
		add_filter(
			'aioseo_canonical_url',
			array( $this, 'get_current_url' )
		);

		// Other AIOSEO filters for title and description.
		add_filter(
			'aioseo_title',
			array( $this, 'get_custom_title' )
		);

		// Description filter.
		add_filter(
			'aioseo_description',
			array( $this, 'get_custom_description' )
		);
	}

	/**
	 * Setup RankMath filters
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function setup_rankmath_filters(): void {

		// RankMath uses a dynamic hook for canonical URLs, so we hook into it directly.
		add_filter(
			'rank_math/frontend/canonical',
			array( $this, 'get_current_url' )
		);

		// Other RankMath filters for title and description.
		add_filter(
			'rank_math/frontend/title',
			array( $this, 'get_custom_title' )
		);

		// Description filter.
		add_filter(
			'rank_math/frontend/description',
			array( $this, 'get_custom_description' )
		);
	}

	/**
	 * Setup default WordPress filters
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function setup_default_filters(): void {

		add_filter(
			'wp_title',
			array(
				$this,
				'get_custom_title'
			),
			999
		);

		add_filter(
			'pre_get_document_title',
			array(
				$this,
				'get_custom_title'
			),
			999
		);

		add_action(
			'wp_head',
			array( $this, 'print_custom_description' )
		);

		// Remove default canonical and short link actions to avoid duplicates.
		remove_action(
			'wp_head',
			'rel_canonical'
		);

		// Also remove the short link to prevent conflicts.
		remove_action(
			'wp_head',
			'wp_shortlink_wp_head',
			10
		);

		// Add our canonical action.
		add_action(
			'wp_head',
			array( $this, 'print_canonical' )
		);
	}

	/**
	 * Validate host for security
	 *
	 * Validates the HTTP_HOST header to prevent header injection attacks
	 * and ensure the host is properly formatted.
	 *
	 * @since 3.0.0
	 *
	 * @param string $host Host to validate
	 *
	 * @return bool True if valid host, false otherwise
	 */
	private function is_valid_host( string $host ): bool {
		// Basic validation for empty or malformed hosts
		if ( empty( $host ) || strlen( $host ) > 255 ) {
			return false;
		}

		// Check for valid hostname pattern
		if ( ! preg_match( '/^[a-zA-Z0-9.-]+$/', $host ) ) {
			return false;
		}

		// Prevent header injection attempts
		if ( strpos( $host, "\n" ) !== false || strpos( $host, "\r" ) !== false ) {
			return false;
		}

		return true;
	}

	/**
	 * Log validation error with context
	 *
	 * Logs validation errors with context information for debugging
	 * and monitoring purposes.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Error message to log
	 * @param array  $context Optional context data
	 *
	 * @return void
	 */
	private function log_validation_error( string $message, array $context = [] ): void {
		$timestamp = current_time( 'mysql' );
		$error_entry = [
			'timestamp' => $timestamp,
			'message'   => $message,
			'context'   => $context,
			'url'       => $this->get_current_url(),
		];

		// Store in memory for debugging
		$this->validation_errors[] = $error_entry;

		// WordPress VIP compliant logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', '[BRAG book SEO Validation Error] ' . $message );
		}

		// Trigger custom error action
		do_action( 'brag_book_gallery_seo_validation_error', $error_entry );
	}

	/**
	 * Get validation errors
	 *
	 * Returns all validation errors that occurred during the current request.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, array> Array of validation errors with context
	 */
	public function get_validation_errors(): array {
		return $this->validation_errors;
	}

	/**
	 * Clear validation errors
	 *
	 * Clears all stored validation errors from memory.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function clear_validation_errors(): void {
		$this->validation_errors = [];
	}

	/**
	 * Validate REQUEST_URI for security threats
	 *
	 * Checks for common attack patterns in REQUEST_URI to prevent
	 * various injection and manipulation attacks.
	 *
	 * @since 3.0.0
	 *
	 * @param string $request_uri Request URI to validate
	 *
	 * @return bool True if safe, false if potentially malicious
	 */
	private function is_safe_request_uri( string $request_uri ): bool {
		// Check for empty or excessively long URIs
		if ( empty( $request_uri ) || strlen( $request_uri ) > 2000 ) {
			return false;
		}

		// Check for null bytes (directory traversal)
		if ( str_contains( $request_uri, "\0" ) ) {
			return false;
		}

		// Check for directory traversal patterns
		if ( str_contains( $request_uri, '../' ) || str_contains( $request_uri, '..\\' ) ) {
			return false;
		}

		// Check for script injection attempts
		$malicious_patterns = [
			'<script',
			'javascript:',
			'vbscript:',
			'onload=',
			'onerror=',
			'%3Cscript',
		];

		foreach ( $malicious_patterns as $pattern ) {
			if ( stripos( $request_uri, $pattern ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize URL segment for security
	 *
	 * Sanitizes individual URL segments to prevent injection attacks
	 * while preserving valid characters for gallery URLs.
	 *
	 * @since 3.0.0
	 *
	 * @param string $segment URL segment to sanitize
	 *
	 * @return string Sanitized URL segment
	 */
	private function sanitize_url_segment( string $segment ): string {
		// Remove dangerous characters while preserving hyphens and numbers
		$sanitized = preg_replace( '/[^a-zA-Z0-9._-]/', '', $segment );

		// Limit length to prevent excessively long segments
		return substr( $sanitized ?? '', 0, 100 );
	}

	/**
	 * Validate description content for security
	 *
	 * Validates meta description content to prevent XSS and ensure
	 * proper content formatting.
	 *
	 * @since 3.0.0
	 *
	 * @param string $description Description content to validate
	 *
	 * @return bool True if safe description, false otherwise
	 */
	private function is_safe_description( string $description ): bool {
		// Check for reasonable length (meta descriptions should be 150-160 chars)
		if ( strlen( $description ) > 300 ) {
			return false;
		}

		// Check for HTML tags (should be plain text)
		if ( $description !== wp_strip_all_tags( $description ) ) {
			return false;
		}

		// Check for control characters
		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate canonical URL for security
	 *
	 * Validates canonical URL to ensure it's safe and properly formatted.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url Canonical URL to validate
	 *
	 * @return bool True if safe URL, false otherwise
	 */
	private function is_safe_canonical_url( string $url ): bool {
		// Basic URL validation
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check for allowed schemes only
		$parsed_url = wp_parse_url( $url );
		if ( ! isset( $parsed_url['scheme'] ) || ! in_array( $parsed_url['scheme'], [ 'http', 'https' ], true ) ) {
			return false;
		}

		// Prevent excessively long URLs
		if ( strlen( $url ) > 2000 ) {
			return false;
		}

		// Check for suspicious characters
		if ( str_contains( $url, '<' ) || str_contains( $url, '>' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Rate limiting for SEO operations
	 *
	 * Simple rate limiting to prevent abuse of SEO generation functionality.
	 *
	 * @since 3.0.0
	 *
	 * @param string $identifier Rate limit identifier
	 * @param int    $limit      Maximum operations per window
	 * @param int    $window     Time window in seconds
	 *
	 * @return bool True if under limit, false if rate limited
	 */
	private function check_rate_limit( string $identifier, int $limit = 50, int $window = 3600 ): bool {
		$cache_key = 'brag_book_seo_rate_limit_' . md5( $identifier );
		$current_count = get_transient( $cache_key );

		if ( $current_count === false ) {
			// First operation in window
			set_transient( $cache_key, 1, $window );
			return true;
		}

		if ( $current_count >= $limit ) {
			// Rate limit exceeded
			$this->log_validation_error( 'SEO rate limit exceeded for identifier: ' . $identifier );
			return false;
		}

		// Increment counter
		set_transient( $cache_key, $current_count + 1, $window );
		return true;
	}

	/**
	 * Generate cache key for data
	 *
	 * Creates consistent cache keys with proper prefixing and hashing.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Cache type identifier
	 * @param array  $data Data to include in cache key
	 *
	 * @return string Generated cache key
	 */
	private function generate_cache_key( string $type, array $data ): string {
		$key_data = wp_json_encode( $data );
		return 'brag_book_seo_' . $type . '_' . md5( $key_data );
	}

	/**
	 * Track cache performance metrics
	 *
	 * Records cache performance data for monitoring and optimization.
	 *
	 * @since 3.0.0
	 *
	 * @param string $data_type    Type of data being cached
	 * @param bool   $cache_hit    Whether this was a cache hit
	 * @param string $cache_source Source of cache (memory, transient, api)
	 * @param float  $duration     Optional fetch duration for API calls
	 *
	 * @return void
	 */
	private function track_cache_performance( string $data_type, bool $cache_hit, string $cache_source, float $duration = 0.0 ): void {
		$this->performance_cache[] = [
			'data_type'     => $data_type,
			'cache_hit'     => $cache_hit,
			'cache_source'  => $cache_source,
			'duration'      => $duration,
			'timestamp'     => microtime( true ),
			'memory_usage'  => memory_get_usage( true ),
		];

		// WordPress VIP compliant performance logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			do_action( 'qm/debug', sprintf(
				'[BRAG book SEO Cache] %s: %s from %s (%.3fs)',
				$data_type,
				$cache_hit ? 'HIT' : 'MISS',
				$cache_source,
				$duration
			) );
		}
	}

	/**
	 * Get cache duration for data type
	 *
	 * Returns appropriate cache duration based on data type using modern match expression.
	 *
	 * @since 3.0.0
	 *
	 * @param string $data_type Data type identifier
	 *
	 * @return int Cache duration in seconds
	 */
	private function get_cache_duration_for_data_type( string $data_type ): int {
		return match ( $data_type ) {
			'sidebar'    => self::CACHE_TTL_LONG,     // Sidebar data changes infrequently
			'case_data'  => self::CACHE_TTL_MEDIUM,  // Case data moderately stable
			'seo_config' => self::CACHE_TTL_SHORT,   // Config may change more frequently
			default      => self::CACHE_DURATION     // Fallback to default
		};
	}

	/**
	 * Get performance metrics summary
	 *
	 * Returns aggregated performance data for monitoring and optimization.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string, mixed> Performance summary
	 */
	public function get_performance_metrics(): array {
		if ( empty( $this->performance_cache ) ) {
			return [];
		}

		$total_operations = count( $this->performance_cache );
		$cache_hits = count( array_filter( $this->performance_cache, fn( $metric ) => $metric['cache_hit'] ) );
		$total_duration = array_sum( array_column( $this->performance_cache, 'duration' ) );

		// Group by data type
		$by_type = [];
		foreach ( $this->performance_cache as $metric ) {
			$type = $metric['data_type'];
			if ( ! isset( $by_type[ $type ] ) ) {
				$by_type[ $type ] = [
					'operations' => 0,
					'cache_hits' => 0,
					'total_duration' => 0,
				];
			}
			$by_type[ $type ]['operations']++;
			if ( $metric['cache_hit'] ) {
				$by_type[ $type ]['cache_hits']++;
			}
			$by_type[ $type ]['total_duration'] += $metric['duration'];
		}

		// Calculate hit rates
		foreach ( $by_type as $type => &$stats ) {
			$stats['hit_rate'] = $stats['operations'] > 0 ? $stats['cache_hits'] / $stats['operations'] : 0;
		}

		return [
			'summary' => [
				'total_operations' => $total_operations,
				'cache_hits'       => $cache_hits,
				'overall_hit_rate' => $total_operations > 0 ? $cache_hits / $total_operations : 0,
				'total_duration'   => $total_duration,
				'memory_peak'      => memory_get_peak_usage( true ),
			],
			'by_type' => $by_type,
		];
	}

	/**
	 * Clear performance cache
	 *
	 * Clears all performance metrics from memory.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function clear_performance_cache(): void {
		$this->performance_cache = [];
	}
}
