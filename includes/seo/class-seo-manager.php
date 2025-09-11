<?php
/**
 * SEO Manager Class - Enterprise-grade SEO optimization system
 *
 * Comprehensive SEO management system for BRAGBook Gallery plugin.
 * Provides advanced SEO optimization with multi-plugin integration,
 * structured data generation, and intelligent content optimization.
 *
 * Features:
 * - Multi-plugin SEO integration (Yoast, AIOSEO, RankMath)
 * - Dynamic structured data generation with schema.org compliance
 * - Advanced caching strategies for optimal performance
 * - Comprehensive input validation and sanitization
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\SEO
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\SEO;

use BRAGBookGallery\Includes\Extend\Cache_Manager;
use BRAGBookGallery\Includes\Traits\Trait_Api;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Enterprise SEO Management System
 *
 * Comprehensive SEO optimization platform with advanced features:
 *
 * Core Responsibilities:
 * - Dynamic SEO metadata generation for all gallery page types
 * - Multi-plugin integration with seamless fallback support
 * - Advanced structured data generation with schema.org compliance
 * - Intelligent caching with performance optimization
 * - Comprehensive input validation and security measures
 *
 * Enterprise Features:
 * - WordPress VIP compliant architecture
 * - Multi-level caching with intelligent invalidation
 * - Advanced error handling and logging
 * - Performance monitoring and optimization
 * - Modern PHP 8.2+ type safety and features
 * - Security enhancements for user data protection
 *
 * @since 3.0.0
 */
final class SEO_Manager {
	use Trait_Api; // Note: Trait_Api includes Trait_Sanitizer

	/**
	 * SEO-specific cache duration constants
	 *
	 * @since 3.0.0
	 * Note: Using CACHE_TTL constants from Trait_Api for shared cache durations
	 * Additional SEO-specific cache constants defined below
	 */
	private const CACHE_TTL_SEO_META = 1800;     // 30 minutes - for SEO metadata
	private const CACHE_TTL_STRUCTURED = 3600;   // 1 hour - for structured data
	private const CACHE_TTL_SITEMAP = 7200;      // 2 hours - for sitemap data

	/**
	 * Current page SEO data
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $seo_data = [];

	/**
	 * Supported SEO plugins with configuration
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $supported_plugins;

	/**
	 * Current active SEO plugin identifier
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $active_seo_plugin = 'none';

	/**
	 * Performance metrics cache
	 *
	 * @since 3.0.0
	 * @var array<string, array>
	 */
	private array $performance_cache = [];

	/**
	 * Constructor
	 *
	 * Note: The following properties are inherited from traits:
	 * - $validation_errors from Trait_Sanitizer
	 * - $memory_cache from Trait_Api
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		// Use modern array syntax for better readability
		$this->supported_plugins = [
			'yoast'    => [
				'name'    => 'Yoast SEO',
				'class'   => 'WPSEO_Options',
				'file'    => 'wordpress-seo/wp-seo.php',
				'filters' => [
					'title'          => 'wpseo_title',
					'description'    => 'wpseo_metadesc',
					'canonical'      => 'wpseo_canonical',
					'og_title'       => 'wpseo_opengraph_title',
					'og_description' => 'wpseo_opengraph_desc',
					'og_url'         => 'wpseo_opengraph_url',
				],
			],
			'aioseo'   => [
				'name'    => 'All in One SEO',
				'class'   => 'AIOSEO\\Plugin\\AIOSEO',
				'file'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'filters' => [
					'title'       => 'aioseo_title',
					'description' => 'aioseo_description',
					'canonical'   => 'aioseo_canonical_url',
				],
			],
			'rankmath' => [
				'name'    => 'Rank Math SEO',
				'class'   => 'RankMath',
				'file'    => 'seo-by-rank-math/rank-math.php',
				'filters' => [
					'title'       => 'rank_math/frontend/title',
					'description' => 'rank_math/frontend/description',
					'canonical'   => 'rank_math/frontend/canonical',
				],
			],
		];

		$this->detect_active_seo_plugin();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function init_hooks(): void {

		// Initialize SEO data on 'wp' action.
		add_action(
			'wp',
			array( $this, 'initialize_page_seo' )
		);

		// Add meta tags and structured data in the head.
		add_action(
			'wp_head',
			array( $this, 'add_custom_meta_tags' ),
			1
		);

		// Structured data should be added after meta tags.
		add_action(
			'wp_head',
			array( $this, 'add_structured_data' ),
			5
		);

		// Modify document title.
		add_filter(
			'document_title_parts',
			array( $this, 'modify_document_title' ),
			10
		);

		// Hook into supported SEO plugins
		$this->register_seo_plugin_filters();
	}

	/**
	 * Detect active SEO plugin
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function detect_active_seo_plugin(): void {
		foreach ( $this->supported_plugins as $plugin_key => $plugin_config ) {
			if ( class_exists( $plugin_config['class'] ) ) {
				$this->active_seo_plugin = $plugin_key;
				break;
			}
		}
	}

	/**
	 * Register SEO plugin filters
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function register_seo_plugin_filters(): void {

		if ( 'none' === $this->active_seo_plugin ) {
			return;
		}

		$plugin_config = $this->supported_plugins[ $this->active_seo_plugin ];
		$filters       = $plugin_config['filters'] ?? [];

		foreach ( $filters as $type => $filter_name ) {
			$method = match ( $type ) {
				'title', 'og_title' => 'get_seo_title',
				'description', 'og_description' => 'get_seo_description',
				'canonical', 'og_url' => 'get_canonical_url',
				default => null,
			};

			if ( $method ) {
				add_filter( $filter_name, [ $this, $method ], 10, 1 );
			}
		}
	}

	/**
	 * Initialize page SEO data
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function initialize_page_seo(): void {
		if ( is_admin() || ! $this->is_gallery_page() ) {
			return;
		}

		try {
			$this->seo_data = $this->generate_seo_data();
		} catch ( \Exception $e ) {
			$this->log_error( 'SEO data generation failed', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'url'   => $_SERVER['REQUEST_URI'] ?? 'unknown',
			] );

			// Provide fallback SEO data
			$this->seo_data = $this->get_fallback_seo_data();
		}
	}

	/**
	 * Check if current page is a gallery page
	 *
	 * @return bool True if gallery page, false otherwise.
	 * @since 3.0.0
	 */
	private function is_gallery_page(): bool {
		if ( ! is_page() ) {
			return false;
		}

		// Get current page ID.
		$current_page_id = get_queried_object_id();

		// Get stored gallery pages and combine page ID.
		$stored_pages    = get_option(
			'brag_book_gallery_stored_pages',
			array()
		);

		// Sanitize stored pages to ensure they are strings.
		$page_id = get_option(
			'brag_book_gallery_page_id',
			''
		);

		// Check if current page is in stored gallery pages
		foreach ( $stored_pages as $page_name ) {
			$page = get_page_by_path( $page_name );
			if ( $page && $page->ID === $current_page_id ) {
				return true;
			}
		}

		// Check if current page is the combine gallery page
		return ! empty( $page_id ) && (int) $page_id === $current_page_id;
	}

	/**
	 * Generate SEO data for current page
	 *
	 * @return array<string, mixed> SEO data.
	 * @since 3.0.0
	 */
	private function generate_seo_data(): array {

		// Parse current URL with validation
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Validate and sanitize the request URI
		if ( empty( $request_uri ) || ! $this->validate_url( home_url( $request_uri ) ) ) {
			$this->log_error( 'Invalid request URI detected', [ 'uri' => $request_uri ] );
			$request_uri = '/';
		}

		// Remove query parameters for clean URL parsing
		$clean_uri = strtok( $request_uri, '?' );

		// Split URL into parts and validate
		$url_parts = array_filter( explode( '/', trim( $clean_uri, '/' ) ) );

		// Sanitize URL parts to prevent injection
		$url_parts = array_map( function( $part ) {
			return $this->sanitize_procedure_slug( $part ) ?? '';
		}, $url_parts );

		$url_parts = array_filter( $url_parts ); // Remove empty parts after sanitization

		// Get site title and current page slug.
		$site_title   = get_bloginfo( 'name' );

		// Get current page slug.
		$current_page = get_queried_object();

		// Ensure we have a valid page object.
		$page_slug    = $current_page ? $current_page->post_name : '';

		// Check if this is the combine gallery page.
		$slug       = get_option(
			'brag_book_gallery_brag_book_gallery_page_slug',
			''
		);

		// Sanitize combine slug to ensure it's a string.
		$is_gallery = $page_slug === $slug;

		// Default SEO data with modern array syntax
		$seo_data = [
			'title'          => '',
			'description'    => '',
			'canonical_url'  => home_url( $clean_uri ),
			'page_type'      => 'gallery_home',
			'procedure_name' => '',
			'case_number'    => '',
			'total_cases'    => 0,
			'is_combine'     => $is_gallery,
		];

		// Get base page SEO settings.
		if ( $is_gallery ) {

			$seo_data['title']       = get_option(
				'brag_book_gallery_seo_page_title',
				''
			);

			$seo_data['description'] = get_option(
				'brag_book_gallery_seo_page_description',
				''
			);
		} else {

			$page_index = $this->get_page_index_by_slug( $page_slug );

			if ( false !== $page_index ) {
				$seo_titles       = get_option(
					'brag_book_gallery_seo_page_title',
					[]
				);
				$seo_descriptions = get_option(
					'brag_book_gallery_seo_page_description',
					[]
				);

				$seo_data['title']       = $seo_titles[ $page_index ] ?? '';
				$seo_data['description'] = $seo_descriptions[ $page_index ] ?? '';
			}
		}

		// Parse URL for specific page types
		if ( count( $url_parts ) >= 2 ) {
			$second_part = $url_parts[1];

			if ( 'consultation' === $second_part ) {
				$seo_data['page_type']   = 'consultation';
				$seo_data['title']       = sprintf(
					/* translators: %s: site title */
					esc_html__( 'Request a Consultation - %s', 'brag-book-gallery' ),
					$site_title
				);
				$seo_data['description'] = esc_html__(
					'Schedule a consultation to discuss your cosmetic procedure options and see before and after photos.',
					'brag-book-gallery'
				);
			} elseif ( 'favorites' === $second_part ) {
				$seo_data['page_type']   = 'favorites';
				$seo_data['title']       = sprintf(
					/* translators: %s: site title */
					esc_html__( 'My Favorites - %s', 'brag-book-gallery' ),
					$site_title
				);
				$seo_data['description'] = esc_html__(
					'View your saved before and after cases from our cosmetic procedure gallery.',
					'brag-book-gallery'
				);
			} else {
				// Procedure-specific page.
				$procedure_slug = $second_part;
				$procedure_data = $this->get_procedure_data( $procedure_slug, $is_gallery );

				if ( $procedure_data ) {
					$seo_data['procedure_name'] = $procedure_data['name'];
					$total_cases                = $procedure_data['total_cases'] ?? 0;
					$seo_data['total_cases']    = $total_cases;

					if ( count( $url_parts ) >= 3 ) {
						// Case detail page.
						$case_id_or_slug = $url_parts[2];
						$case_data       = $this->get_case_seo_data(
							$case_id_or_slug,
							$procedure_data,
							$is_gallery
						);

						if ( $case_data ) {
							$seo_data['page_type']   = 'case_detail';
							$seo_data['case_number'] = $case_data['case_number'];

							$seo_data['title'] = ! empty( $case_data['brag_book_gallery_seo_title'] )
								? $case_data['brag_book_gallery_seo_title'] . ' - ' . $site_title
								: sprintf(
									/* translators: 1: procedure name, 2: case number, 3: site title */
									esc_html__(
										'Before and After %1$s: Patient %2$s - %3$s',
										'brag-book-gallery'
									),
									$procedure_data['name'],
									$case_data['case_number'],
									$site_title
								);

							$seo_data['description'] = ! empty( $case_data['brag_book_gallery_seo_description'] )
								? $case_data['brag_book_gallery_seo_description']
								: sprintf(
									/* translators: 1: procedure name, 2: case number */
									esc_html__(
										'View before and after photos for %1$s patient %2$s. Real results from actual patients.',
										'brag-book-gallery'
									),
									$procedure_data['name'],
									$case_data['case_number']
								);
						}
					} else {
						// Procedure list page
						$seo_data['page_type']   = 'case_list';
						$seo_data['title']       = sprintf(
							/* translators: 1: procedure name, 2: total cases, 3: site title */
							esc_html__(
								'Before and After %1$s Gallery, %2$d Cases - %3$s',
								'brag-book-gallery'
							),
							$procedure_data['name'],
							$total_cases,
							$site_title
						);
						$seo_data['description'] = sprintf(
							/* translators: 1: procedure name, 2: total cases */
							esc_html__(
								'Browse %1$d real before and after %2$s cases. See actual patient results and outcomes.',
								'brag-book-gallery'
							),
							$total_cases,
							$procedure_data['name']
						);
					}
				}
			}
		}

		// Fallback titles and descriptions
		if ( empty( $seo_data['title'] ) ) {
			$seo_data['title'] = get_the_title() . ' - ' . $site_title;
		}

		if ( empty( $seo_data['description'] ) ) {
			$seo_data['description'] = get_the_excerpt() ?: sprintf(
				/* translators: %s: site title */
				esc_html__(
					'Browse our before and after photo gallery showcasing real patient results - %s',
					'brag-book-gallery'
				),
				$site_title
			);
		}

		return apply_filters(
			'brag_book_gallery_seo_data',
			$seo_data,
			$url_parts
		);
	}

	/**
	 * Get procedure data by slug
	 *
	 * @param string $procedure_slug Procedure slug.
	 * @param bool $is_combine Whether this is a combine gallery.
	 *
	 * @return array|null Procedure data or null if not found.
	 * @since 3.0.0
	 */
	private function get_procedure_data( string $procedure_slug, bool $is_combine ): ?array {

		// Cache key based on procedure slug and combine status.
		$cache_key   = 'brag_book_gallery_transient_procedure_' . $procedure_slug . ( $is_combine ? '_combine' : '_single' );

		// Check cache first.
		$cached_data = Cache_Manager::get( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Get API tokens and website property IDs
		if ( $is_combine ) {
			$api_tokens   = get_option(
				option: 'brag_book_gallery_api_token',
				default_value: array()
			);
			$sidebar_data = $this->get_combined_sidebar_data( $api_tokens );
		} else {
			$page_slug  = get_queried_object()->post_name ?? '';
			$page_index = $this->get_page_index_by_slug( $page_slug );

			if ( false === $page_index ) {
				return null;
			}

			$api_tokens = get_option(
				option: 'brag_book_gallery_api_token',
				default_value: array()
			);
			$api_token  = $api_tokens[ $page_index ] ?? '';

			if ( empty( $api_token ) ) {
				return null;
			}

			$sidebar_data = $this->get_sidebar_data( $api_token );
		}

		$procedure_data = $this->find_procedure_in_sidebar( $sidebar_data, $procedure_slug );

		if ( $procedure_data ) {
			Cache_Manager::set( $cache_key, $procedure_data, HOUR_IN_SECONDS );
		}

		return $procedure_data;
	}

	/**
	 * Get case SEO data
	 *
	 * @param string $case_id_or_slug Case ID or SEO suffix URL.
	 * @param array $procedure_data Procedure data.
	 * @param bool $is_combine Whether this is a combine gallery.
	 *
	 * @return array|null Case SEO data or null if not found.
	 * @since 3.0.0
	 */
	private function get_case_seo_data( string $case_id_or_slug, array $procedure_data, bool $is_combine ): ?array {

		// Cache key based on case identifier and procedure data.
		$cache_key   = 'brag_book_gallery_case_seo_' .$case_id_or_slug . serialize( $procedure_data );

		// Check cache first.
		$cached_data = Cache_Manager::get( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Determine if this is a numeric case ID or SEO suffix.
		$case_id    = '';
		$seo_suffix = '';

		if ( str_starts_with( $case_id_or_slug, 'brag-book-gallery-case-' ) ) {
			preg_match( '/\d+/', $case_id_or_slug, $matches );
			$case_id = $matches[0] ?? '';
		} else {
			$seo_suffix = $case_id_or_slug;
		}

		// Get case data from API.
		if ( $is_combine ) {
			$api_tokens = get_option(
				'brag_book_gallery_api_token',
				array()
			);
			$website_property_ids = get_option(
				'brag_book_gallery_website_property_id',
				array()
			);
			$case_data            = $this->get_combined_case_data( $case_id, $seo_suffix, $api_tokens, $procedure_data['ids'], $website_property_ids );
		} else {

			// Get current page slug and index.
			$page_slug  = get_queried_object()->post_name ?? '';

			// Ensure we have a valid page slug.
			$page_index = $this->get_page_index_by_slug( $page_slug );

			if ( false === $page_index ) {
				return null;
			}

			// Get API token and website property ID for this page.
			$api_tokens = get_option(
				'brag_book_gallery_api_token',
				array()
			);

			// Sanitize website property IDs to ensure they are an array.
			$website_property_ids = get_option(
				'brag_book_gallery_website_property_id',
				array()
			);

			$api_token          = $api_tokens[ $page_index ] ?? '';
			$websiteproperty_id = $website_property_ids[ $page_index ] ?? '';

			if ( empty( $api_token ) || empty( $websiteproperty_id ) ) {
				return null;
			}

			$case_data = $this->get_single_case_data( $case_id, $seo_suffix, $api_token, $procedure_data['ids'][0] ?? '', $websiteproperty_id );
		}

		if ( ! $case_data ) {
			return null;
		}

		// Extract SEO data from case response.
		$seo_case_data = [
			'case_number'     => $this->get_case_number( $case_id_or_slug, $case_data ),
			'brag_book_gallery_seo_title'       => $case_data['caseDetails'][0]['seoPageTitle'] ?? '',
			'brag_book_gallery_seo_description' => $case_data['caseDetails'][0]['seoPageDescription'] ?? '',
		];

		Cache_Manager::set( $cache_key, $seo_case_data, HOUR_IN_SECONDS );

		return $seo_case_data;
	}

	/**
	 * Get case number from case data
	 *
	 * Determines the sequential case number within a procedure based on the case
	 * identifier or SEO suffix URL. Provides consistent numbering for display purposes.
	 *
	 * @param non-empty-string      $case_id_or_slug Case ID or SEO suffix identifier.
	 * @param array<string, mixed> $case_data       Case data structure from API containing:
	 *                                               - array<array<string, mixed>> 'caseIds' Case identifier list
	 *
	 * @return positive-int Case number (1-indexed) for display purposes.
	 * @since 3.0.0
	 */
	private function get_case_number( string $case_id_or_slug, array $case_data ): int {

		// Default case number.
		$case_ids    = $case_data['caseIds'] ?? [];

		// If no case IDs, return 1.
		$case_number = 1;

		foreach ( $case_ids as $key => $case_item ) {
			$matches_id  = str_starts_with( $case_id_or_slug, 'case-' ) && (int) $case_item['id'] === (int) str_replace( 'case-', '', $case_id_or_slug );
			$matches_seo = ! str_starts_with( $case_id_or_slug, 'case-' ) && $case_item['seoSuffixUrl'] === $case_id_or_slug;

			if ( $matches_id || $matches_seo ) {
				$case_number = $key + 1;
				break;
			}
		}

		return $case_number;
	}

	/**
	 * Get combined sidebar data for multiple API tokens
	 *
	 * Retrieves and combines sidebar data from multiple API sources.
	 * Implements comprehensive caching and error handling for optimal performance.
	 *
	 * @param array<string> $api_tokens Array of API tokens for data retrieval.
	 *
	 * @return array<string, mixed> Combined sidebar data structure:
	 *                              - array<array<string, mixed>> Procedure categories with:
	 *                                - string 'name' Category display name
	 *                                - array<array<string, mixed>> 'procedures' Procedure list
	 * @since 3.0.0
	 */
	private function get_combined_sidebar_data( array $api_tokens ): array {

		$cache_key   = 'brag_book_gallery_transient_combined_sidebar_' . serialize( $api_tokens );
		$cached_data = Cache_Manager::get( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$combined_data = array();

		foreach ( $api_tokens as $api_token ) {
			if ( empty( $api_token ) ) {
				continue;
			}

			try {
				$response = $this->api_get(
					'/api/plugin/sidebar',
					[ 'apiToken' => $api_token ]
				);

				if ( ! is_wp_error( $response ) && isset( $response['data'] ) && is_array( $response['data'] ) ) {
					$combined_data = array_merge( $combined_data, $response['data'] );
				} elseif ( is_wp_error( $response ) ) {
					$this->log_error( 'API request failed for sidebar data', [
						'error' => $response->get_error_message(),
						'token' => substr( $api_token, 0, 8 ) . '...' // Partial token for debugging
					] );
				}
			} catch ( \Exception $e ) {
				$this->log_error( 'Exception during sidebar API call', [
					'error' => $e->getMessage(),
					'token' => substr( $api_token, 0, 8 ) . '...'
				] );
			}
		}

		Cache_Manager::set( $cache_key, $combined_data, HOUR_IN_SECONDS );

		return $combined_data;
	}

	/**
	 * Get sidebar data for single API token
	 *
	 * Retrieves sidebar data from a single API source with caching.
	 * Handles API errors gracefully and provides structured data response.
	 *
	 * @param non-empty-string $api_token API token for authentication.
	 *
	 * @return array<string, mixed> Sidebar data structure:
	 *                              - array<array<string, mixed>> Categories containing:
	 *                                - string 'name' Category name
	 *                                - array<array<string, mixed>> 'procedures' Procedure data
	 * @since 3.0.0
	 */
	private function get_sidebar_data( string $api_token ): array {

		$cache_key   = 'brag_book_gallery_transient_sidebar_' . $api_token;

		$cached_data = Cache_Manager::get( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$response = $this->api_get(
			'/api/plugin/sidebar',
			array( 'apiToken' => $api_token )
		);

		if ( is_wp_error( $response ) || ! isset( $response['data'] ) ) {
			return [];
		}

		$sidebar_data = $response['data'];
		Cache_Manager::set( $cache_key, $sidebar_data, HOUR_IN_SECONDS );

		return $sidebar_data;
	}

	/**
	 * Find procedure in sidebar data
	 *
	 * Searches through sidebar data structure to find a specific procedure
	 * by slug name. Performs case-insensitive matching for better compatibility.
	 *
	 * @param array<array<string, mixed>> $sidebar_data Sidebar data from API.
	 * @param non-empty-string           $procedure_slug Procedure slug to search for.
	 *
	 * @return array<string, mixed>|null Procedure data structure or null if not found:
	 *                                   - string 'name' Procedure display name
	 *                                   - array<int> 'ids' Procedure ID array
	 *                                   - int 'total_cases' Total case count
	 * @since 3.0.0
	 */
	private function find_procedure_in_sidebar( array $sidebar_data, string $procedure_slug ): ?array {

		// Search for the procedure in the sidebar data.
		foreach ( $sidebar_data as $category ) {
			if ( ! isset( $category['procedures'] ) ) {
				continue;
			}

			foreach ( $category['procedures'] as $procedure ) {
				if ( strtolower( $procedure['slugName'] ?? '' ) === strtolower( $procedure_slug ) ) {
					return [
						'name'        => $procedure['name'] ?? '',
						'ids'         => $procedure['ids'] ?? [],
						'total_cases' => $procedure['totalCase'] ?? 0,
					];
				}
			}
		}

		return null;
	}

	/**
	 * Get combined case data
	 *
	 * Retrieves case data from multiple API sources by combining tokens and IDs.
	 * Supports both case ID and SEO suffix URL lookups.
	 *
	 * @param string        $case_id              Case ID for direct lookup.
	 * @param string        $seo_suffix           SEO suffix URL for friendly URL lookup.
	 * @param array<string> $api_tokens           Array of API authentication tokens.
	 * @param array<int>    $procedure_ids        Array of procedure identifiers.
	 * @param array<string> $website_property_ids Array of website property identifiers.
	 *
	 * @return array<string, mixed>|null Case data structure or null if not found:
	 *                                   - array<array<string, mixed>> 'caseDetails' Case detail information
	 *                                   - array<array<string, mixed>> 'caseIds' Available case identifiers
	 * @since 3.0.0
	 */
	private function get_combined_case_data( string $case_id, string $seo_suffix, array $api_tokens, array $procedure_ids, array $website_property_ids ): ?array {

		$params = array(
			'apiTokens'          => implode( ',', $api_tokens ),
			'procedureIds'       => implode( ',', $procedure_ids ),
			'websitePropertyIds' => implode( ',', $website_property_ids ),
		);

		if ( ! empty( $case_id ) ) {
			$params['caseId'] = $case_id;
		}
		if ( ! empty( $seo_suffix ) ) {
			$params['seoSuffixUrl'] = $seo_suffix;
		}

		$response = $this->api_get( '/api/plugin/cases', $params );

		if ( is_wp_error( $response ) || ! isset( $response['data'][0] ) ) {
			return null;
		}

		return $response['data'][0];
	}

	/**
	 * Get single case data
	 *
	 * Retrieves case data from a single API source with specific identifiers.
	 * Supports both case ID and SEO suffix URL for flexible case lookup.
	 *
	 * @param string $case_id             Case identifier for direct lookup.
	 * @param string $seo_suffix          SEO-friendly URL suffix for lookup.
	 * @param string $api_token           API authentication token.
	 * @param string $procedure_id        Target procedure identifier.
	 * @param string $websiteproperty_id  Website property identifier.
	 *
	 * @return array<string, mixed>|null Case data structure or null if not found:
	 *                                   - array<array<string, mixed>> 'caseDetails' Detailed case information
	 *                                   - array<array<string, mixed>> 'caseIds' Available case identifiers
	 * @since 3.0.0
	 */
	private function get_single_case_data( string $case_id, string $seo_suffix, string $api_token, string $procedure_id, string $websiteproperty_id ): ?array {

		$params = array(
			'apiToken'          => $api_token,
			'procedureId'       => $procedure_id,
			'websitePropertyId' => $websiteproperty_id,
		);

		if ( ! empty( $case_id ) ) {
			$params['caseId'] = $case_id;
		}
		if ( ! empty( $seo_suffix ) ) {
			$params['seoSuffixUrl'] = $seo_suffix;
		}

		$response = $this->api_get( '/api/plugin/cases', $params );

		if ( is_wp_error( $response ) || ! isset( $response['data'][0] ) ) {
			return null;
		}

		return $response['data'][0];
	}

	/**
	 * Get page index by slug
	 *
	 * @param string $page_slug Page slug.
	 *
	 * @return int|false Page index or false if not found.
	 * @since 3.0.0
	 */
	private function get_page_index_by_slug( string $page_slug ): int|false {

		$gallery_slugs = get_option(
			'brag_book_gallery_page_slug',
			array()
		);

		return array_search( $page_slug, $gallery_slugs, true );
	}

	/**
	 * Get current page URL
	 *
	 * @return string Current URL.
	 * @since 3.0.0
	 */
	private function get_current_url(): string {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = $_SERVER['HTTP_HOST'] ?? '';
		$uri    = $_SERVER['REQUEST_URI'] ?? '';

		return $scheme . '://' . $host . $uri;
	}

	/**
	 * Modify document title
	 *
	 * @param array $title_parts Title parts.
	 *
	 * @return array Modified title parts.
	 * @since 3.0.0
	 */
	public function modify_document_title( array $title_parts ): array {

		if ( ! $this->is_gallery_page() || empty( $this->seo_data['title'] ) ) {
			return $title_parts;
		}

		$title_parts['title'] = $this->seo_data['title'];

		return $title_parts;
	}

	/**
	 * Add custom meta tags
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function add_custom_meta_tags(): void {

		if ( ! $this->is_gallery_page() || 'none' !== $this->active_seo_plugin ) {
			return;
		}

		if ( ! empty( $this->seo_data['description'] ) ) {
			$description = $this->sanitize_meta_content( $this->seo_data['description'], 'description' );
			echo '<meta name="description" content="' . $description . '">' . "\n";
		}

		if ( ! empty( $this->seo_data['canonical_url'] ) ) {
			$canonical_url = $this->sanitize_meta_content( $this->seo_data['canonical_url'], 'url' );
			echo '<link rel="canonical" href="' . $canonical_url . '">' . "\n";
		}

		// Open Graph tags with enhanced security
		$og_title = $this->sanitize_meta_content( $this->seo_data['title'], 'title' );
		$og_description = $this->sanitize_meta_content( $this->seo_data['description'], 'description' );
		$og_url = $this->sanitize_meta_content( $this->seo_data['canonical_url'], 'url' );

		echo '<meta property="og:title" content="' . $og_title . '">' . "\n";
		echo '<meta property="og:description" content="' . $og_description . '">' . "\n";
		echo '<meta property="og:url" content="' . $og_url . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
	}

	/**
	 * Add structured data
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function add_structured_data(): void {
		if ( ! $this->is_gallery_page() ) {
			return;
		}

		// Build breadcrumb structure
		$breadcrumb_items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => 'Home',
				'item'     => home_url( '/' ),
			),
		);

		$position = 2;

		// Add gallery page to breadcrumb
		if ( ! empty( $this->seo_data['canonical_url'] ) ) {
			$page_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );
			$page_slug = is_string( $page_slug ) ? $page_slug : 'gallery';
			$gallery_url = home_url( '/' . trim( $page_slug, '/' ) . '/' );

			if ( $this->seo_data['page_type'] !== 'gallery_home' ) {
				$breadcrumb_items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => 'Gallery',
					'item'     => $gallery_url,
				);
			}

			// Add procedure page to breadcrumb if applicable
			if ( ! empty( $this->seo_data['procedure_name'] ) && $this->seo_data['page_type'] === 'case_detail' ) {
				$procedure_url = rtrim( $gallery_url, '/' ) . '/' . sanitize_title( $this->seo_data['procedure_name'] ) . '/';
				$breadcrumb_items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $this->seo_data['procedure_name'],
					'item'     => $procedure_url,
				);
			}

			// Add current page to breadcrumb
			if ( $this->seo_data['page_type'] !== 'gallery_home' ) {
				$current_name = match ( $this->seo_data['page_type'] ) {
					'case_list' => $this->seo_data['procedure_name'] . ' Gallery',
					'case_detail' => 'Case ' . $this->seo_data['case_number'],
					'consultation' => 'Consultation',
					'favorites' => 'My Favorites',
					default => 'Gallery',
				};

				$breadcrumb_items[] = array(
					'@type'    => 'ListItem',
					'position' => $position,
					'name'     => $current_name,
					'item'     => $this->seo_data['canonical_url'],
				);
			}
		}

		// Build main schema structure
		$structured_data = array(
			'@context' => 'https://schema.org',
		);

		switch ( $this->seo_data['page_type'] ) {
			case 'case_detail':
				// For individual case pages
				$structured_data['@type'] = 'ImageGallery';
				$structured_data['name'] = sprintf(
					'%s Before & After Photos - Case %s',
					$this->seo_data['procedure_name'],
					$this->seo_data['case_number']
				);
				$structured_data['description'] = $this->seo_data['description'];
				$structured_data['url'] = $this->seo_data['canonical_url'];
				break;

			case 'case_list':
				// For procedure gallery pages
				$total_cases = $this->seo_data['total_cases'] ?? 0;
				$structured_data['@type'] = 'ImageGallery';
				$structured_data['name'] = sprintf(
					'%s Before & After Gallery',
					$this->seo_data['procedure_name']
				);
				$structured_data['description'] = sprintf(
					'Review %d %s before and after cases submitted by real doctors from our online gallery.',
					$total_cases,
					$this->seo_data['procedure_name']
				);
				$structured_data['url'] = $this->seo_data['canonical_url'];
				$structured_data['numberOfItems'] = $total_cases;
				break;

			case 'consultation':
				$structured_data['@type'] = 'ContactPage';
				$structured_data['name'] = 'Request a Consultation';
				$structured_data['description'] = $this->seo_data['description'];
				$structured_data['url'] = $this->seo_data['canonical_url'];
				$structured_data['mainEntity'] = [
					'@type'        => 'MedicalBusiness',
					'name'         => get_bloginfo( 'name' ),
					'contactPoint' => [
						'@type'       => 'ContactPoint',
						'contactType' => 'consultation booking',
					],
				];
				break;

			case 'favorites':
				$structured_data['@type'] = 'CollectionPage';
				$structured_data['name'] = 'My Favorite Cases';
				$structured_data['description'] = 'Your saved before and after cases from our gallery';
				$structured_data['url'] = $this->seo_data['canonical_url'];
				break;

			default:
				// For main gallery page
				$structured_data['@type'] = 'ImageGallery';
				$structured_data['name'] = 'Before & After Gallery';
				$structured_data['description'] = 'Browse our comprehensive before and after photo gallery showcasing real patient results from cosmetic procedures.';
				$structured_data['url'] = $this->seo_data['canonical_url'];
				break;
		}

		// Add breadcrumb to all page types with modern syntax
		if ( ! empty( $breadcrumb_items ) ) {
			$structured_data['breadcrumb'] = [
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $breadcrumb_items,
			];
		}

		// Add publisher/provider information with modern syntax
		$site_name = get_bloginfo( 'name' );
		if ( ! empty( $site_name ) ) {
			$structured_data['publisher'] = [
				'@type' => 'Organization',
				'name'  => $site_name,
				'url'   => home_url( '/' ),
			];
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Get SEO title (for SEO plugin filters)
	 *
	 * @param string $title Original title.
	 *
	 * @return string Modified title.
	 * @since 3.0.0
	 */
	public function get_seo_title( string $title ): string {
		if ( ! $this->is_gallery_page() || empty( $this->seo_data['title'] ) ) {
			return $title;
		}

		return $this->seo_data['title'];
	}

	/**
	 * Get SEO description (for SEO plugin filters)
	 *
	 * @param string $description Original description.
	 *
	 * @return string Modified description.
	 * @since 3.0.0
	 */
	public function get_seo_description( string $description ): string {
		if ( ! $this->is_gallery_page() || empty( $this->seo_data['description'] ) ) {
			return $description;
		}

		return $this->seo_data['description'];
	}

	/**
	 * Get canonical URL (for SEO plugin filters)
	 *
	 * @param string $canonical_url Original canonical URL.
	 *
	 * @return string Modified canonical URL.
	 * @since 3.0.0
	 */
	public function get_canonical_url( string $canonical_url ): string {
		if ( ! $this->is_gallery_page() || empty( $this->seo_data['canonical_url'] ) ) {
			return $canonical_url;
		}

		return $this->seo_data['canonical_url'];
	}

	/**
	 * Get SEO data for external use
	 *
	 * @return array<string, mixed> SEO data.
	 * @since 3.0.0
	 */
	public function get_seo_data(): array {
		return $this->seo_data;
	}

	/**
	 * Get active SEO plugin information
	 *
	 * @return array SEO plugin information.
	 * @since 3.0.0
	 */
	public function get_active_seo_plugin_info(): array {
		$this->detect_active_seo_plugin();

		if ( 'none' === $this->active_seo_plugin ) {
			return array(
				'active' => false,
				'name'   => 'None',
				'plugin' => 'none',
			);
		}

		$plugin_info = $this->supported_plugins[ $this->active_seo_plugin ] ?? array();

		return array(
			'active' => true,
			'name'   => $plugin_info['name'] ?? 'Unknown',
			'plugin' => $this->active_seo_plugin,
			'class'  => $plugin_info['class'] ?? '',
		);
	}

	/**
	 * Log error with context information
	 *
	 * Logs errors with comprehensive context for debugging purposes.
	 * Includes error tracking in validation_errors array for analysis.
	 *
	 * @param string              $message Error message.
	 * @param array<string, mixed> $context Additional context information.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function log_error( string $message, array $context = [] ): void {
		$error_id = uniqid( 'seo_error_', true );

		$this->validation_errors[ $error_id ] = [
			'message'   => $message,
			'context'   => $context,
			'timestamp' => time(),
		];

		// Log to WordPress error log if debugging is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			try {
				error_log( sprintf(
					'BRAGBook SEO Error [%s]: %s - Context: %s',
					$error_id,
					$message,
					wp_json_encode( $context, JSON_THROW_ON_ERROR )
				) );
			} catch ( \JsonException $e ) {
				error_log( sprintf(
					'BRAGBook SEO Error [%s]: %s - Context encoding failed',
					$error_id,
					$message
				) );
			}
		}
	}

	/**
	 * Get fallback SEO data when primary generation fails
	 *
	 * Provides basic SEO data structure when normal generation encounters errors.
	 * Ensures the plugin continues to function even with API failures.
	 *
	 * @return array<string, mixed> Basic SEO data structure.
	 * @since 3.0.0
	 */
	private function get_fallback_seo_data(): array {
		$site_title = get_bloginfo( 'name' );
		$current_url = home_url( $_SERVER['REQUEST_URI'] ?? '/' );

		return [
			'title'          => get_the_title() . ' - ' . $site_title,
			'description'    => get_the_excerpt() ?: sprintf(
				/* translators: %s: site title */
				__( 'Gallery page - %s', 'brag-book-gallery' ),
				$site_title
			),
			'canonical_url'  => esc_url( $current_url ),
			'page_type'      => 'gallery_home',
			'procedure_name' => '',
			'case_number'    => '',
			'total_cases'    => 0,
			'is_combine'     => false,
		];
	}

	/**
	 * Validate URL structure and parameters
	 *
	 * Validates URL components to ensure they meet security and format requirements.
	 * Prevents injection attacks and malformed URL processing.
	 *
	 * @param string $url URL to validate.
	 *
	 * @return bool True if URL is valid and safe.
	 * @since 3.0.0
	 */
	private function validate_url( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		// Check for basic URL format
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Ensure URL belongs to current site
		$site_url = get_site_url();
		if ( ! str_starts_with( $url, $site_url ) ) {
			$this->log_error( 'Invalid URL domain', [ 'url' => $url, 'expected_domain' => $site_url ] );
			return false;
		}

		return true;
	}

	/**
	 * Sanitize and validate procedure slug
	 *
	 * Ensures procedure slugs are safe and properly formatted.
	 * Prevents directory traversal and injection attacks.
	 *
	 * @param string $slug Procedure slug to validate.
	 *
	 * @return string|null Sanitized slug or null if invalid.
	 * @since 3.0.0
	 */
	private function sanitize_procedure_slug( string $slug ): ?string {
		if ( empty( $slug ) ) {
			return null;
		}

		// Remove any potentially dangerous characters
		$sanitized = sanitize_title( $slug );

		// Ensure slug only contains safe characters
		if ( ! preg_match( '/^[a-z0-9\-]+$/', $sanitized ) ) {
			$this->log_error( 'Invalid procedure slug format', [ 'original' => $slug, 'sanitized' => $sanitized ] );
			return null;
		}

		// Prevent path traversal attempts
		if ( str_contains( $sanitized, '..' ) || str_contains( $sanitized, '//' ) ) {
			$this->log_error( 'Potential path traversal in slug', [ 'slug' => $slug ] );
			return null;
		}

		return $sanitized;
	}

	/**
	 * Sanitize and validate API response data
	 *
	 * Ensures API response data is safe and properly formatted before use.
	 * Prevents XSS attacks and malformed data processing.
	 *
	 * @param mixed $data Raw API response data.
	 *
	 * @return array<string, mixed>|null Sanitized data or null if invalid.
	 * @since 3.0.0
	 */
	private function sanitize_api_response( $data ): ?array {
		if ( ! is_array( $data ) ) {
			$this->log_error( 'API response data is not an array', [ 'type' => gettype( $data ) ] );
			return null;
		}

		return $this->deep_sanitize_array( $data );
	}

	/**
	 * Deep sanitize array data recursively
	 *
	 * Recursively sanitizes array data to prevent XSS and injection attacks.
	 * Handles nested arrays and various data types safely.
	 *
	 * @param array<mixed> $array Input array to sanitize.
	 *
	 * @return array<string, mixed> Sanitized array data.
	 * @since 3.0.0
	 */
	private function deep_sanitize_array( array $array ): array {
		$sanitized = [];

		foreach ( $array as $key => $value ) {
			// Sanitize the key
			$clean_key = sanitize_key( (string) $key );

			if ( is_array( $value ) ) {
				// Recursively sanitize nested arrays
				$sanitized[ $clean_key ] = $this->deep_sanitize_array( $value );
			} elseif ( is_string( $value ) ) {
				// Sanitize string values based on context
				$sanitized[ $clean_key ] = $this->sanitize_string_by_context( $clean_key, $value );
			} elseif ( is_numeric( $value ) ) {
				// Validate and sanitize numeric values
				$sanitized[ $clean_key ] = $this->sanitize_numeric_value( $value );
			} else {
				// Handle other data types safely
				$sanitized[ $clean_key ] = $this->sanitize_mixed_value( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize string value based on context
	 *
	 * Applies appropriate sanitization based on the expected content type.
	 * Prevents XSS while preserving necessary formatting.
	 *
	 * @param string $key   Data key for context determination.
	 * @param string $value String value to sanitize.
	 *
	 * @return string Sanitized string value.
	 * @since 3.0.0
	 */
	private function sanitize_string_by_context( string $key, string $value ): string {
		return match ( $key ) {
			'name', 'title', 'seoPageTitle' => sanitize_text_field( $value ),
			'description', 'seoPageDescription' => sanitize_textarea_field( $value ),
			'url', 'seoSuffixUrl' => esc_url_raw( $value ),
			'slug', 'slugName' => sanitize_title( $value ),
			'id', 'caseId', 'procedureId' => sanitize_text_field( $value ),
			default => sanitize_text_field( $value ),
		};
	}

	/**
	 * Sanitize numeric values with validation
	 *
	 * Validates and sanitizes numeric values to prevent injection attacks.
	 * Ensures proper data types and reasonable value ranges.
	 *
	 * @param mixed $value Numeric value to sanitize.
	 *
	 * @return int|float Sanitized numeric value.
	 * @since 3.0.0
	 */
	private function sanitize_numeric_value( $value ): int|float {
		if ( is_int( $value ) || is_float( $value ) ) {
			// Validate reasonable ranges to prevent memory issues
			if ( abs( $value ) > PHP_INT_MAX / 2 ) {
				$this->log_error( 'Numeric value out of safe range', [ 'value' => $value ] );
				return 0;
			}
			return $value;
		}

		// Convert string numbers safely
		if ( is_string( $value ) && is_numeric( $value ) ) {
			return str_contains( $value, '.' ) ? (float) $value : (int) $value;
		}

		$this->log_error( 'Invalid numeric value', [ 'value' => $value, 'type' => gettype( $value ) ] );
		return 0;
	}

	/**
	 * Sanitize mixed value types
	 *
	 * Handles sanitization of non-standard data types safely.
	 * Provides fallback sanitization for unknown content.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return mixed Sanitized value.
	 * @since 3.0.0
	 */
	private function sanitize_mixed_value( $value ) {
		if ( is_bool( $value ) || is_null( $value ) ) {
			return $value;
		}

		// Log unexpected data types for monitoring
		$this->log_error( 'Unexpected data type encountered', [
			'type' => gettype( $value ),
			'value' => print_r( $value, true )
		] );

		// Convert to string and sanitize as fallback
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Validate and sanitize meta content for output
	 *
	 * Ensures meta tags and structured data content is safe for HTML output.
	 * Prevents XSS attacks in meta descriptions and titles.
	 *
	 * @param string $content Meta content to validate.
	 * @param string $type    Content type (title, description, url).
	 *
	 * @return string Sanitized and validated content.
	 * @since 3.0.0
	 */
	private function sanitize_meta_content( string $content, string $type ): string {
		if ( empty( $content ) ) {
			return '';
		}

		return match ( $type ) {
			'title' => $this->sanitize_meta_title( $content ),
			'description' => $this->sanitize_meta_description( $content ),
			'url' => $this->sanitize_meta_url( $content ),
			default => esc_attr( $content ),
		};
	}

	/**
	 * Sanitize meta title content
	 *
	 * @param string $title Title content to sanitize.
	 *
	 * @return string Sanitized title.
	 * @since 3.0.0
	 */
	private function sanitize_meta_title( string $title ): string {
		// Remove HTML tags and entities
		$title = wp_strip_all_tags( $title );
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Limit title length for SEO best practices
		if ( mb_strlen( $title ) > 60 ) {
			$title = mb_substr( $title, 0, 57 ) . '...';
		}

		return esc_attr( $title );
	}

	/**
	 * Sanitize meta description content
	 *
	 * @param string $description Description content to sanitize.
	 *
	 * @return string Sanitized description.
	 * @since 3.0.0
	 */
	private function sanitize_meta_description( string $description ): string {
		// Remove HTML tags and entities
		$description = wp_strip_all_tags( $description );
		$description = html_entity_decode( $description, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Limit description length for SEO best practices
		if ( mb_strlen( $description ) > 160 ) {
			$description = mb_substr( $description, 0, 157 ) . '...';
		}

		return esc_attr( $description );
	}

	/**
	 * Sanitize meta URL content
	 *
	 * @param string $url URL content to sanitize.
	 *
	 * @return string Sanitized URL.
	 * @since 3.0.0
	 */
	private function sanitize_meta_url( string $url ): string {
		$url = esc_url_raw( $url );

		if ( empty( $url ) || ! $this->validate_url( $url ) ) {
			$this->log_error( 'Invalid meta URL detected', [ 'url' => $url ] );
			return home_url( '/' );
		}

		return $url;
	}

	/**
	 * Rate limit API requests to prevent abuse
	 *
	 * Implements basic rate limiting to prevent API abuse and improve performance.
	 * Uses WordPress transients for simple rate limiting implementation.
	 *
	 * @param string $identifier Unique identifier for rate limiting.
	 * @param int    $limit      Maximum requests allowed.
	 * @param int    $window     Time window in seconds.
	 *
	 * @return bool True if request is allowed, false if rate limited.
	 * @since 3.0.0
	 */
	private function is_rate_limited( string $identifier, int $limit = 100, int $window = 3600 ): bool {
		$cache_key = 'brag_book_gallery_transient_rate_limit_' . $identifier;
		$requests = Cache_Manager::get( $cache_key ) ?: 0;

		if ( $requests >= $limit ) {
			$this->log_error( 'Rate limit exceeded', [
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
	 * Retrieves cached data while tracking performance metrics.
	 * Implements multi-level caching for optimal performance.
	 *
	 * @param string $cache_key Cache identifier.
	 * @param callable $callback Callback to generate data if not cached.
	 * @param int $ttl Cache TTL in seconds.
	 * @param string $category Performance category for tracking.
	 *
	 * @return mixed Cached or generated data.
	 * @since 3.0.0
	 */
	private function get_cached_data( string $cache_key, callable $callback, int $ttl = self::CACHE_TTL_MEDIUM, string $category = 'seo' ) {
		$start_time = microtime( true );

		// Check memory cache first (fastest)
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			$this->track_performance( $category, microtime( true ) - $start_time, 'memory_hit' );
			return $this->memory_cache[ $cache_key ];
		}

		// Check transient cache (second fastest)
		$cached_data = Cache_Manager::get( $cache_key );
		if ( false !== $cached_data ) {
			$this->memory_cache[ $cache_key ] = $cached_data;
			$this->track_performance( $category, microtime( true ) - $start_time, 'transient_hit' );
			return $cached_data;
		}

		// Generate data if not cached
		try {
			$data = $callback();

			// Store in both cache levels
			$this->memory_cache[ $cache_key ] = $data;
			Cache_Manager::set( $cache_key, $data, $ttl );

			$this->track_performance( $category, microtime( true ) - $start_time, 'cache_miss' );
			return $data;
		} catch ( \Exception $e ) {
			$this->log_error( 'Cache callback failed', [
				'cache_key' => $cache_key,
				'error' => $e->getMessage()
			] );

			$this->track_performance( $category, microtime( true ) - $start_time, 'cache_error' );
			return null;
		}
	}

	/**
	 * Track performance metrics
	 *
	 * Records performance data for monitoring and optimization.
	 * Helps identify bottlenecks and improve caching strategies.
	 *
	 * @param string $category Performance category.
	 * @param float $duration Operation duration in seconds.
	 * @param string $type Operation type (hit, miss, error).
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function track_performance( string $category, float $duration, string $type ): void {
		if ( ! isset( $this->performance_cache[ $category ] ) ) {
			$this->performance_cache[ $category ] = [];
		}

		$this->performance_cache[ $category ][] = [
			'type' => $type,
			'duration' => round( $duration * 1000, 2 ), // Convert to milliseconds
			'timestamp' => microtime( true ),
		];

		// Log slow operations for optimization
		if ( $duration > 0.5 ) { // 500ms threshold
			$this->log_error( 'Slow operation detected', [
				'category' => $category,
				'type' => $type,
				'duration' => $duration,
			] );
		}
	}

	/**
	 * Optimize cache management with intelligent cleanup
	 *
	 * Implements intelligent cache cleanup to prevent memory bloat.
	 * Maintains performance while managing resource usage.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function optimize_cache(): void {
		$max_memory_entries = 50;
		$max_performance_entries = 100;

		// Clean memory cache if too large
		if ( count( $this->memory_cache ) > $max_memory_entries ) {
			$this->memory_cache = array_slice( $this->memory_cache, -$max_memory_entries, null, true );
		}

		// Clean performance cache if too large
		foreach ( $this->performance_cache as $category => &$entries ) {
			if ( count( $entries ) > $max_performance_entries ) {
				$entries = array_slice( $entries, -$max_performance_entries );
			}
		}

		// Clear old transients periodically
		$this->cleanup_old_transients();
	}

	/**
	 * Cleanup old transients to prevent database bloat
	 *
	 * Removes expired and unused transients related to the plugin.
	 * Helps maintain database performance and reduce storage usage.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function cleanup_old_transients(): void {
		global $wpdb;

		// Only run cleanup occasionally to avoid performance impact
		$cleanup_key = 'brag_book_seo_cleanup_last';
		$last_cleanup = Cache_Manager::get( $cleanup_key );

		if ( false !== $last_cleanup ) {
			return; // Cleanup already performed recently
		}

		try {
			// Delete expired transients related to the plugin
			$expired_transients = $wpdb->get_col( $wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s
				AND (
					SELECT CAST(option_value AS UNSIGNED)
					FROM {$wpdb->options} o2
					WHERE o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
				) < %d",
				'_transient_brag_book_%',
				'%seo%',
				time()
			) );

			foreach ( $expired_transients as $transient_name ) {
				$transient_key = str_replace( '_transient_', '', $transient_name );
				Cache_Manager::delete( $transient_key );
			}

			// Mark cleanup as completed
			Cache_Manager::set( $cleanup_key, time(), DAY_IN_SECONDS );

		} catch ( \Exception $e ) {
			$this->log_error( 'Transient cleanup failed', [ 'error' => $e->getMessage() ] );
		}
	}

	/**
	 * Get performance metrics for monitoring
	 *
	 * Returns comprehensive performance data for analysis and optimization.
	 * Useful for debugging and performance monitoring dashboards.
	 *
	 * @return array<string, mixed> Performance metrics data.
	 * @since 3.0.0
	 */
	public function get_performance_metrics(): array {
		$metrics = [
			'memory_cache_size' => count( $this->memory_cache ),
			'validation_errors' => count( $this->validation_errors ),
			'categories' => [],
			'summary' => [
				'total_operations' => 0,
				'cache_hits' => 0,
				'cache_misses' => 0,
				'average_duration' => 0,
			],
		];

		$total_operations = 0;
		$total_duration = 0;
		$cache_hits = 0;
		$cache_misses = 0;

		foreach ( $this->performance_cache as $category => $entries ) {
			$category_stats = [
				'operations' => count( $entries ),
				'hits' => 0,
				'misses' => 0,
				'errors' => 0,
				'avg_duration' => 0,
				'total_duration' => 0,
			];

			foreach ( $entries as $entry ) {
				$total_operations++;
				$total_duration += $entry['duration'];
				$category_stats['total_duration'] += $entry['duration'];

				switch ( $entry['type'] ) {
					case 'memory_hit':
					case 'transient_hit':
						$cache_hits++;
						$category_stats['hits']++;
						break;
					case 'cache_miss':
						$cache_misses++;
						$category_stats['misses']++;
						break;
					case 'cache_error':
						$category_stats['errors']++;
						break;
				}
			}

			if ( $category_stats['operations'] > 0 ) {
				$category_stats['avg_duration'] = round(
					$category_stats['total_duration'] / $category_stats['operations'],
					2
				);
			}

			$metrics['categories'][ $category ] = $category_stats;
		}

		// Calculate summary statistics
		$metrics['summary'] = [
			'total_operations' => $total_operations,
			'cache_hits' => $cache_hits,
			'cache_misses' => $cache_misses,
			'hit_rate' => $total_operations > 0 ? round( ($cache_hits / $total_operations) * 100, 1 ) : 0,
			'average_duration' => $total_operations > 0 ? round( $total_duration / $total_operations, 2 ) : 0,
		];

		return $metrics;
	}

	/**
	 * Warm up critical caches proactively
	 *
	 * Pre-loads essential data into cache to improve initial performance.
	 * Should be called during plugin initialization or cron jobs.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function warm_cache(): void {
		if ( ! $this->is_gallery_page() ) {
			return;
		}

		try {
			// Pre-load sidebar data for combine galleries
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			if ( ! empty( $api_tokens ) && is_array( $api_tokens ) ) {
				$this->get_cached_data(
					'brag_book_gallery_transient_combined_sidebar_' . serialize( $api_tokens ),
					fn() => $this->get_combined_sidebar_data( $api_tokens ),
					self::CACHE_TTL_LONG,
					'warm_up'
				);
			}

			// Pre-load basic SEO data
			$this->get_cached_data(
				'brag_book_seo_basic_' . $_SERVER['REQUEST_URI'] ?? '/',
				fn() => $this->generate_seo_data(),
				self::CACHE_TTL_MEDIUM,
				'warm_up'
			);

		} catch ( \Exception $e ) {
			$this->log_error( 'Cache warm-up failed', [ 'error' => $e->getMessage() ] );
		}

		// Perform cache optimization
		$this->optimize_cache();
	}
}
