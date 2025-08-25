<?php
/**
 * On-Page SEO Handler
 *
 * Manages SEO meta tags, titles, descriptions, and canonical URLs for BRAG book gallery pages.
 * Integrates with popular SEO plugins including Yoast, AIOSEO, and RankMath.
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
 * On_Page Class
 *
 * Handles on-page SEO optimization for gallery pages.
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
	 * Cache duration for transients
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * SEO data for current page
	 *
	 * @since 3.0.0
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
	 * Get current page URL
	 *
	 * Builds the complete URL for the current page including protocol,
	 * domain, and request URI.
	 *
	 * @return string Current page URL.
	 * @since 3.0.0
	 */
	public function get_current_url(): string {
		$protocol    = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
		$host        = $_SERVER['HTTP_HOST'] ?? '';
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		if ( empty( $host ) || empty( $request_uri ) ) {
			return '';
		}

		return esc_url( $protocol . '://' . $host . $request_uri );
	}

	/**
	 * Get custom SEO title and description for BRAG book pages
	 *
	 * Analyzes the current URL to determine the appropriate SEO metadata
	 * based on gallery type, procedure, and case details.
	 *
	 * @return array{bb_title: string, bb_description: string, bb_procedure_name: string} SEO data array.
	 * @since 3.0.0
	 */
	public function get_custom_title_and_description(): array {
		$site_title = get_bloginfo( 'name' );
		$url_parts  = $this->parse_current_url();

		// Initialize return data
		$seo_data = array(
			'bb_title'          => '',
			'bb_description'    => '',
			'bb_procedure_name' => ''
		);

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
	 * Parse current URL into parts
	 *
	 * @return array URL parts.
	 * @since 3.0.0
	 */
	private function parse_current_url(): array {
		$request_uri       = $_SERVER['REQUEST_URI'] ?? '';
		$url_without_query = strtok( $request_uri, '?' );
		$clean_url         = trim( $url_without_query, '/' );

		return ! empty( $clean_url ) ? explode( '/', $clean_url ) : [];
	}

	/**
	 * Get SEO configuration from options
	 *
	 * @return array Configuration array.
	 * @since 3.0.0
	 */
	private function get_seo_configuration(): array {
		// Use helper to get the first slug
		$page_slug = \BRAGBookGallery\Includes\Core\Slug_Helper::get_first_gallery_page_slug( '' );

		return array(
			'brag_book_gallery_page_slug'    => $page_slug,
			'brag_book_gallery_page_id' => (int) get_option(
				option: 'brag_book_gallery_page_id',
				default_value: 0
			),
			'api_tokens'              => (array) get_option(
				option: 'brag_book_gallery_api_token',
				default_value: array()
			),
			'website_property_ids'    => (array) get_option(
				option: 'brag_book_gallery_website_property_id',
				default_value: array()
			),
			'gallery_slugs'           => (array) get_option(
				option: 'brag_book_gallery_gallery_page_slug',
				default_value: array()
			),
			'seo_titles'              => (array) get_option(
				option: 'brag_book_gallery_seo_page_title',
				default_value: array()
			),
			'seo_descriptions'        => (array) get_option(
				option: 'brag_book_gallery_seo_page_description',
				default_value: array()
			),
			'brag_book_gallery_seo_title'       => (string) get_option(
				option: 'brag_book_gallery_seo_page_title',
				default_value: ''
			),
			'brag_book_gallery_seo_description' => (string) get_option(
				option: 'brag_book_gallery_seo_page_description',
				default_value: ''
			),
		);
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
	 * Get SEO data for gallery listing page
	 *
	 * @param array $url_parts URL parts.
	 * @param array $config Configuration.
	 * @param string $site_title Site title.
	 *
	 * @return array SEO data.
	 * @since 3.0.0
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

		// Skip favorites and consultation pages
		if ( in_array( $procedure_slug, [
			'favorites',
			'consultation'
		], true ) ) {
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
	 * Fetch case data from API
	 *
	 * @param array $url_parts URL parts.
	 * @param array $config Configuration.
	 * @param int|null $case_id Case ID.
	 * @param string|null $seo_suffix SEO suffix.
	 *
	 * @return array Case data.
	 * @since 3.0.0
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

			// Use transient cache for single gallery requests
			$transient_key = 'cases_' . md5( $api_token . $procedure_id . $website_property_id . $case_id . $seo_suffix );
			$data          = get_transient( $transient_key );

			if ( false === $data ) {
				$data = $this->api_handler->get_case_data(
					$case_id ?? 0,
					$seo_suffix ?? '',
					$api_token,
					$procedure_id,
					$website_property_id
				);
				// Only cache valid responses
				if ( ! empty( $data ) ) {
					set_transient( $transient_key, $data, self::CACHE_DURATION );
				}
			}
		}

		// Check if data is valid before decoding
		if ( empty( $data ) || ! is_string( $data ) ) {
			return [];
		}

		$decoded_data = json_decode( $data, true );

		// Check if JSON decode was successful
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded_data ) ) {
			return [];
		}

		return $decoded_data['data'][0] ?? [];
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
		$cache_key    = $procedure_slug . '-' . ( $is_combine ? 'combine' : 'single' );
		$sidebar_data = get_transient( $cache_key );

		if ( false === $sidebar_data ) {
			$sidebar_data = $this->api_handler->get_api_sidebar( $api_tokens );
			// Only cache valid responses
			if ( ! empty( $sidebar_data ) ) {
				set_transient( $cache_key, $sidebar_data, self::CACHE_DURATION );
			}
		}

		// Check if sidebar_data is valid before decoding
		if ( empty( $sidebar_data ) || ! is_string( $sidebar_data ) ) {
			return [
				'bb_procedure_id'    => null,
				'bb_procedure_name'  => '',
				'procedureTotalCase' => 0
			];
		}

		$sidebar = json_decode( $sidebar_data );

		// Check if JSON decode was successful and data exists
		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $sidebar->data ) ) {
			return [
				'bb_procedure_id'    => null,
				'bb_procedure_name'  => '',
				'procedureTotalCase' => 0
			];
		}

		// Search for matching procedure
		foreach ( $sidebar->data as $category ) {
			foreach ( $category->procedures as $procedure ) {
				if ( $procedure->slugName === $procedure_slug ) {
					return [
						'bb_procedure_id'    => $is_combine ? $procedure->ids : ( $procedure->ids[0] ?? null ),
						'bb_procedure_name'  => $procedure->name ?? '',
						'procedureTotalCase' => $procedure->totalCase ?? 0
					];
				}
			}
		}

		return [
			'bb_procedure_id'    => null,
			'bb_procedure_name'  => '',
			'procedureTotalCase' => 0
		];
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
		$description = esc_attr( $this->get_custom_description() );
		if ( ! empty( $description ) ) {
			echo '<meta name="description" content="' . $description . '">' . "\n";
		}
	}

	/**
	 * Print canonical URL tag
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function print_canonical(): void {
		$url = esc_url( $this->get_current_url() );
		if ( ! empty( $url ) ) {
			echo '<link rel="canonical" href="' . $url . '">' . "\n";
		}
	}

	/**
	 * Initialize SEO hooks based on active plugin
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function initialize_seo(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->is_bragbook_page() ) {
			return;
		}

		$seo_plugin = (int) get_option( 'brag_book_gallery_seo_plugin_selector', self::SEO_PLUGIN_NONE );

		switch ( $seo_plugin ) {
			case self::SEO_PLUGIN_YOAST:
				$this->setup_yoast_filters();
				break;

			case self::SEO_PLUGIN_AIOSEO:
				$this->setup_aioseo_filters();
				break;

			case self::SEO_PLUGIN_RANKMATH:
				$this->setup_rankmath_filters();
				break;

			default:
				$this->setup_default_filters();
				break;
		}
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
			hook_name: 'wpseo_canonical',
			callback: array( $this, 'get_current_url' )
		);

		// Other Yoast filters for title and description.
		add_filter(
			hook_name: 'wpseo_title',
			callback: array( $this, 'get_custom_title' )
		);

		// Description filter.
		add_filter(
			hook_name: 'wpseo_metadesc',
			callback: array( $this, 'get_custom_description' )
		);

		// Open Graph filters.
		add_filter(
			hook_name: 'wpseo_opengraph_title',
			callback: array( $this, 'get_custom_title' )
		);

		// Open Graph description filter.
		add_filter(
			hook_name: 'wpseo_opengraph_desc',
			callback: array( $this, 'get_custom_description' )
		);

		// Open Graph URL filter.
		add_filter(
			hook_name: 'wpseo_opengraph_url',
			callback: array( $this, 'get_current_url' )
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
			hook_name: 'aioseo_canonical_url',
			callback: array( $this, 'get_current_url' )
		);

		// Other AIOSEO filters for title and description.
		add_filter(
			hook_name: 'aioseo_title',
			callback: array( $this, 'get_custom_title' )
		);

		// Description filter.
		add_filter(
			hook_name: 'aioseo_description',
			callback: array( $this, 'get_custom_description' )
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
			hook_name: 'rank_math/frontend/canonical',
			callback: array( $this, 'get_current_url' )
		);

		// Other RankMath filters for title and description.
		add_filter(
			hook_name: 'rank_math/frontend/title',
			callback: array( $this, 'get_custom_title' )
		);

		// Description filter.
		add_filter(
			hook_name: 'rank_math/frontend/description',
			callback: array( $this, 'get_custom_description' )
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
			hook_name: 'wp_title',
			callback: array(
				$this,
				'get_custom_title'
			),
			priority: 999,
			accepted_args: 0
		);

		add_filter(
			hook_name: 'pre_get_document_title',
			callback: array(
				$this,
				'get_custom_title'
			),
			priority: 999,
			accepted_args: 0
		);

		add_action(
			hook_name: 'wp_head',
			callback: array( $this, 'print_custom_description' )
		);

		// Remove default canonical and short link actions to avoid duplicates.
		remove_action(
			hook_name: 'wp_head',
			callback: 'rel_canonical'
		);

		// Also remove the short link to prevent conflicts.
		remove_action(
			hook_name: 'wp_head',
			callback: 'wp_shortlink_wp_head',
			priority: 10
		);

		// Add our canonical action.
		add_action(
			hook_name: 'wp_head',
			callback: array( $this, 'print_canonical' )
		);
	}
}
