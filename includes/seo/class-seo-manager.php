<?php
/**
 * SEO Manager Class - Handles SEO optimization for gallery pages
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

use BRAGBookGallery\Includes\Traits\{Trait_Sanitizer, Trait_Api};

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Class SEO_Manager
 *
 * Manages SEO optimization for gallery pages including titles, descriptions, and structured data
 * @since 3.0.0
 */
final class SEO_Manager {
	use Trait_Sanitizer, Trait_Api;

	/**
	 * Current page SEO data
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $seo_data = array();

	/**
	 * Supported SEO plugins
	 *
	 * @since 3.0.0
	 * @var array<string, array>
	 */
	private array $supported_plugins;

	/**
	 * Current SEO plugin
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private string $active_seo_plugin = 'none';

	/**
	 * Constructor
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->supported_plugins = array(
			'yoast'    => array(
				'name'    => 'Yoast SEO',
				'class'   => 'WPSEO_Options',
				'file'    => 'wordpress-seo/wp-seo.php',
				'filters' => array(
					'title'          => 'wpseo_title',
					'description'    => 'wpseo_metadesc',
					'canonical'      => 'wpseo_canonical',
					'og_title'       => 'wpseo_opengraph_title',
					'og_description' => 'wpseo_opengraph_desc',
					'og_url'         => 'wpseo_opengraph_url',
				),
			),
			'aioseo'   => array(
				'name'    => 'All in One SEO',
				'class'   => 'AIOSEO\\Plugin\\AIOSEO',
				'file'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'filters' => array(
					'title'       => 'aioseo_title',
					'description' => 'aioseo_description',
					'canonical'   => 'aioseo_canonical_url',
				),
			),
			'rankmath' => array(
				'name'    => 'Rank Math SEO',
				'class'   => 'RankMath',
				'file'    => 'seo-by-rank-math/rank-math.php',
				'filters' => array(
					'title'       => 'rank_math/frontend/title',
					'description' => 'rank_math/frontend/description',
					'canonical'   => 'rank_math/frontend/canonical',
				),
			),
		);

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
			hook_name: 'wp',
			callback: array( $this, 'initialize_page_seo' )
		);

		// Add meta tags and structured data in the head.
		add_action(
			hook_name: 'wp_head',
			callback: array( $this, 'add_custom_meta_tags' ),
			priority: 1
		);

		// Structured data should be added after meta tags.
		add_action(
			hook_name: 'wp_head',
			callback: array( $this, 'add_structured_data' ),
			priority: 5
		);

		// Modify document title.
		add_filter(
			hook_name: 'document_title_parts',
			callback: array( $this, 'modify_document_title' ),
			priority: 10
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

		$this->seo_data = $this->generate_seo_data();
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
			option: 'brag_book_gallery_stored_pages',
			default_value: array()
		);

		// Sanitize stored pages to ensure they are strings.
		$page_id = get_option(
			option: 'brag_book_gallery_page_id',
			default_value: ''
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

		// Parse current URL.
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Remove query parameters for clean URL parsing.
		$clean_uri   = strtok( $request_uri, '?' );

		// Split URL into parts.
		$url_parts   = array_filter( explode( '/', trim( $clean_uri, '/' ) ) );

		// Get site title and current page slug.
		$site_title   = get_bloginfo( show: 'name' );

		// Get current page slug.
		$current_page = get_queried_object();

		// Ensure we have a valid page object.
		$page_slug    = $current_page ? $current_page->post_name : '';

		// Check if this is the combine gallery page.
		$slug       = get_option(
			option: 'brag_book_gallery_brag_book_gallery_page_slug',
			default_value: ''
		);

		// Sanitize combine slug to ensure it's a string.
		$is_gallery = $page_slug === $slug;

		// Default SEO data
		$seo_data = array(
			'title'          => '',
			'description'    => '',
			'canonical_url'  => home_url( $clean_uri ),
			'page_type'      => 'gallery_home',
			'procedure_name' => '',
			'case_number'    => '',
			'is_combine'     => $is_gallery,
		);

		// Get base page SEO settings.
		if ( $is_gallery ) {

			$seo_data['title']       = get_option(
				option: 'brag_book_gallery_seo_page_title',
				default_value: ''
			);

			$seo_data['description'] = get_option(
				option: 'brag_book_gallery_seo_page_description',
				default_value: ''
			);
		} else {

			$page_index = $this->get_page_index_by_slug( $page_slug );

			if ( false !== $page_index ) {
				$seo_titles       = get_option(
					option: 'brag_book_gallery_seo_page_title',
					default_value: array()
				);
				$seo_descriptions = get_option(
					option: 'brag_book_gallery_seo_page_description',
					default_value: array()
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
			hook_name: 'brag_book_gallery_seo_data',
			value: $seo_data,
			args: $url_parts
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
		$cache_key   = 'brag_book_gallery_procedure_' . md5( $procedure_slug . ( $is_combine ? '_combine' : '_single' ) );

		// Check cache first.
		$cached_data = get_transient( $cache_key );

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
			set_transient( $cache_key, $procedure_data, HOUR_IN_SECONDS );
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
		$cache_key   = 'brag_book_gallery_case_seo_' . md5( $case_id_or_slug . serialize( $procedure_data ) );

		// Check cache first.
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Determine if this is a numeric case ID or SEO suffix.
		$case_id    = '';
		$seo_suffix = '';

		if ( str_starts_with( $case_id_or_slug, 'bb-case-' ) ) {
			preg_match( '/\d+/', $case_id_or_slug, $matches );
			$case_id = $matches[0] ?? '';
		} else {
			$seo_suffix = $case_id_or_slug;
		}

		// Get case data from API.
		if ( $is_combine ) {
			$api_tokens = get_option(
				option: 'brag_book_gallery_api_token',
				default_value: array()
			);
			$website_property_ids = get_option(
				option: 'brag_book_gallery_website_property_id',
				default_value: array()
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
				option: 'brag_book_gallery_api_token',
				default_value: array()
			);

			// Sanitize website property IDs to ensure they are an array.
			$website_property_ids = get_option(
				option: 'brag_book_gallery_website_property_id',
				default_value: array()
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

		set_transient( $cache_key, $seo_case_data, HOUR_IN_SECONDS );

		return $seo_case_data;
	}

	/**
	 * Get case number from case data
	 *
	 * @param string $case_id_or_slug Case identifier.
	 * @param array $case_data Case data from API.
	 *
	 * @return int Case number.
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
	 * @param array $api_tokens API tokens.
	 *
	 * @return array Combined sidebar data.
	 * @since 3.0.0
	 */
	private function get_combined_sidebar_data( array $api_tokens ): array {

		$cache_key   = 'brag_book_gallery_combined_sidebar_' . md5( serialize( $api_tokens ) );
		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$combined_data = array();

		foreach ( $api_tokens as $api_token ) {
			if ( empty( $api_token ) ) {
				continue;
			}

			$response = $this->api_get(
				endpoint: '/api/plugin/sidebar',
				params: array( 'apiToken' => $api_token )
			);

			if ( ! is_wp_error( $response ) && isset( $response['data'] ) ) {
				$combined_data = array_merge( $combined_data, $response['data'] );
			}
		}

		set_transient( $cache_key, $combined_data, HOUR_IN_SECONDS );

		return $combined_data;
	}

	/**
	 * Get sidebar data for single API token
	 *
	 * @param string $api_token API token.
	 *
	 * @return array Sidebar data.
	 * @since 3.0.0
	 */
	private function get_sidebar_data( string $api_token ): array {

		$cache_key   = 'brag_book_gallery_sidebar_' . md5( $api_token );

		$cached_data = get_transient( $cache_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$response = $this->api_get(
			endpoint: '/api/plugin/sidebar',
			params: array( 'apiToken' => $api_token )
		);

		if ( is_wp_error( $response ) || ! isset( $response['data'] ) ) {
			return [];
		}

		$sidebar_data = $response['data'];
		set_transient( $cache_key, $sidebar_data, HOUR_IN_SECONDS );

		return $sidebar_data;
	}

	/**
	 * Find procedure in sidebar data
	 *
	 * @param array $sidebar_data Sidebar data.
	 * @param string $procedure_slug Procedure slug.
	 *
	 * @return array|null Procedure data or null if not found.
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
	 * @param string $case_id Case ID.
	 * @param string $seo_suffix SEO suffix.
	 * @param array $api_tokens API tokens.
	 * @param array $procedure_ids Procedure IDs.
	 * @param array $website_property_ids Website property IDs.
	 *
	 * @return array|null Case data or null if not found.
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
	 * @param string $case_id Case ID.
	 * @param string $seo_suffix SEO suffix.
	 * @param string $api_token API token.
	 * @param string $procedure_id Procedure ID.
	 * @param string $websiteproperty_id Website property ID.
	 *
	 * @return array|null Case data or null if not found.
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
			option: 'brag_book_gallery_page_slug',
			default_value: array()
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
			echo '<meta name="description" content="' . esc_attr( $this->seo_data['description'] ) . '">' . "\n";
		}

		if ( ! empty( $this->seo_data['canonical_url'] ) ) {
			echo '<link rel="canonical" href="' . esc_url( $this->seo_data['canonical_url'] ) . '">' . "\n";
		}

		// Open Graph tags
		echo '<meta property="og:title" content="' . esc_attr( $this->seo_data['title'] ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $this->seo_data['description'] ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $this->seo_data['canonical_url'] ) . '">' . "\n";
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

		$structured_data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'MedicalWebPage',
			'name'        => $this->seo_data['title'],
			'description' => $this->seo_data['description'],
			'url'         => $this->seo_data['canonical_url'],
		);

		switch ( $this->seo_data['page_type'] ) {
			case 'case_detail':
				$structured_data['mainEntity'] = array(
					'@type' => 'MedicalProcedure',
					'name'  => $this->seo_data['procedure_name'],
					'image' => array(
						'@type' => 'ImageGallery',
						'name'  => sprintf(
							/* translators: 1: procedure name, 2: case number */
							esc_html__(
								'%1$s Before and After Photos - Case %2$s',
								'brag-book-gallery'
							),
							$this->seo_data['procedure_name'],
							$this->seo_data['case_number']
						),
					),
				);
				break;

			case 'case_list':
				$structured_data['mainEntity'] = array(
					'@type' => 'ImageGallery',
					'name'  => sprintf(
						/* translators: %s: procedure name */
						esc_html__(
							'%s Before and After Gallery',
							'brag-book-gallery'
						),
						$this->seo_data['procedure_name']
					),
					'about' => array(
						'@type' => 'MedicalProcedure',
						'name'  => $this->seo_data['procedure_name'],
					),
				);
				break;

			case 'consultation':
				$structured_data['@type']      = 'ContactPage';
				$structured_data['mainEntity'] = [
					'@type'        => 'MedicalBusiness',
					'name'         => get_bloginfo( 'name' ),
					'contactPoint' => array(
						'@type'       => 'ContactPoint',
						'contactType' => 'consultation booking',
					),
				];
				break;

			default:
				$structured_data['mainEntity'] = array(
					'@type'       => 'ImageGallery',
					'name'        => esc_html__(
						'Before and After Gallery',
						'brag-book-gallery'
					),
					'description' => esc_html__(
						'Medical procedure before and after photo gallery',
						'brag-book-gallery'
					),
				);
				break;
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
}
