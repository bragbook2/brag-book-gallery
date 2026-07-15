<?php
/**
 * Medical Schema
 *
 * Emits schema.org medical structured data (JSON-LD) for case and provider
 * views: a MedicalClinic/MedicalBusiness node for the practice, Physician nodes
 * for the case's providers, and MedicalProcedure nodes for its procedures.
 *
 * When a supported SEO plugin (Yoast, RankMath, AIOSEO) is active, the nodes are
 * injected into that plugin's schema @graph so the page carries a single unified
 * graph. When none is active, the nodes are printed as a standalone
 * <script type="application/ld+json"> block on wp_head.
 *
 * @package BRAGBookGallery
 * @subpackage SEO
 * @since 4.9.0
 */

namespace BRAGBookGallery\Includes\SEO;

use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Medical_Schema class
 *
 * @since 4.9.0
 */
final class Medical_Schema {

	/**
	 * Option keys.
	 */
	private const OPTION_ENABLED   = 'brag_book_gallery_schema_enabled';
	private const OPTION_TYPE      = 'brag_book_gallery_schema_type';
	private const OPTION_SPECIALTY = 'brag_book_gallery_schema_specialty';
	private const OPTION_ORG_NAME  = 'brag_book_gallery_schema_org_name';
	private const OPTION_ORG_LOGO  = 'brag_book_gallery_schema_org_logo';
	private const OPTION_PHYSICIAN = 'brag_book_gallery_schema_include_physician';

	/**
	 * Default primary schema type for the practice/organization.
	 */
	private const DEFAULT_TYPE = 'MedicalClinic';

	/**
	 * Default medical specialty.
	 */
	private const DEFAULT_SPECIALTY = 'PlasticSurgery';

	/**
	 * Memoised graph nodes for the current request.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $nodes = null;

	/**
	 * Constructor — register on plugins_loaded so SEO plugin detection is reliable.
	 *
	 * @since 4.9.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'register' ], 25 );
	}

	/**
	 * Whether medical schema output is enabled.
	 *
	 * @since 4.9.0
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Available primary schema types (value => label).
	 *
	 * @since 4.9.0
	 * @return array<string, string>
	 */
	public static function get_available_types(): array {
		return [
			'MedicalClinic'       => __( 'Medical Clinic', 'brag-book-gallery' ),
			'MedicalBusiness'     => __( 'Medical Business', 'brag-book-gallery' ),
			'Physician'           => __( 'Physician', 'brag-book-gallery' ),
			'MedicalOrganization' => __( 'Medical Organization (no location data)', 'brag-book-gallery' ),
		];
	}

	/**
	 * Available medical specialties (value => label).
	 *
	 * @since 4.9.0
	 * @return array<string, string>
	 */
	public static function get_available_specialties(): array {
		return [
			'PlasticSurgery' => __( 'Plastic Surgery', 'brag-book-gallery' ),
			'Dermatology'    => __( 'Dermatology', 'brag-book-gallery' ),
			'Surgical'       => __( 'Surgical', 'brag-book-gallery' ),
			''               => __( 'None', 'brag-book-gallery' ),
		];
	}

	/**
	 * Register the schema output hook for the active integration.
	 *
	 * @since 4.9.0
	 * @return void
	 */
	public function register(): void {
		if ( is_admin() || ! self::is_enabled() ) {
			return;
		}

		switch ( self::detect_active_seo_plugin() ) {
			case 'yoast':
				add_filter( 'wpseo_schema_graph', [ $this, 'filter_yoast_graph' ], 11, 2 );
				break;
			case 'rankmath':
				add_filter( 'rank_math/json_ld', [ $this, 'filter_rankmath_graph' ], 99, 2 );
				break;
			case 'aioseo':
				add_filter( 'aioseo_schema_output', [ $this, 'filter_aioseo_graph' ], 20 );
				break;
			default:
				add_action( 'wp_head', [ $this, 'print_standalone_graph' ], 20 );
		}
	}

	/**
	 * Detect the active SEO plugin.
	 *
	 * Mirrors the detection order used elsewhere in the plugin.
	 *
	 * @since 4.9.0
	 * @return string One of: 'yoast' | 'rankmath' | 'aioseo' | 'none'.
	 */
	public static function detect_active_seo_plugin(): string {
		static $detected = null;
		if ( null !== $detected ) {
			return $detected;
		}

		if ( class_exists( 'WPSEO_Options' ) ) {
			return $detected = 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			return $detected = 'rankmath';
		}
		if ( function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' ) ) {
			return $detected = 'aioseo';
		}

		return $detected = 'none';
	}

	/**
	 * Append our nodes to Yoast's schema graph.
	 *
	 * @since 4.9.0
	 * @param mixed $graph   Yoast graph pieces.
	 * @param mixed $context Yoast meta tags context (unused).
	 * @return mixed
	 */
	public function filter_yoast_graph( $graph, $context = null ) {
		unset( $context );
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		$nodes = $this->get_nodes();
		return empty( $nodes ) ? $graph : array_merge( $graph, $nodes );
	}

	/**
	 * Append our nodes to RankMath's JSON-LD data.
	 *
	 * @since 4.9.0
	 * @param mixed $data   RankMath entities keyed by name.
	 * @param mixed $jsonld RankMath JsonLD instance (unused).
	 * @return mixed
	 */
	public function filter_rankmath_graph( $data, $jsonld = null ) {
		unset( $jsonld );
		if ( ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $this->get_nodes() as $index => $node ) {
			$data[ 'brag_book_' . $index ] = $node;
		}

		return $data;
	}

	/**
	 * Append our nodes to AIOSEO's schema graph.
	 *
	 * @since 4.9.0
	 * @param mixed $graph AIOSEO graph nodes.
	 * @return mixed
	 */
	public function filter_aioseo_graph( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		$nodes = $this->get_nodes();
		return empty( $nodes ) ? $graph : array_merge( $graph, $nodes );
	}

	/**
	 * Print a standalone JSON-LD graph (no SEO plugin active).
	 *
	 * @since 4.9.0
	 * @return void
	 */
	public function print_standalone_graph(): void {
		$nodes = $this->get_nodes();
		if ( empty( $nodes ) ) {
			return;
		}

		$document = [
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		];

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Build (and memoise) the graph nodes for the current request.
	 *
	 * @since 4.9.0
	 * @return array<int, array<string, mixed>>
	 */
	private function get_nodes(): array {
		if ( null !== $this->nodes ) {
			return $this->nodes;
		}

		$case_post_id = $this->resolve_case_post_id();
		if ( $case_post_id > 0 ) {
			return $this->nodes = $this->build_case_graph( $case_post_id );
		}

		if ( $this->is_gallery_context() ) {
			$org = $this->build_org_node( $this->resolve_primary_practice_id() );
			return $this->nodes = ( [] === $org ) ? [] : [ $org ];
		}

		return $this->nodes = [];
	}

	/**
	 * Build the full graph for a single case view.
	 *
	 * @since 4.9.0
	 * @param int $case_post_id Case post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_case_graph( int $case_post_id ): array {
		$graph = [];

		$org = $this->build_org_node( $this->resolve_practice_for_case( $case_post_id ) );
		if ( [] !== $org ) {
			$graph[] = $org;
		}

		foreach ( $this->build_physician_nodes( $case_post_id ) as $node ) {
			$graph[] = $node;
		}

		foreach ( $this->build_procedure_nodes( $case_post_id ) as $node ) {
			$graph[] = $node;
		}

		return $graph;
	}

	/**
	 * Build the MedicalClinic/MedicalBusiness organization node.
	 *
	 * @since 4.9.0
	 * @param int $practice_id Practice post ID, or 0 when none resolved.
	 * @return array<string, mixed> Node, or empty array when no name is available.
	 */
	private function build_org_node( int $practice_id ): array {
		$name = $this->get_org_name( $practice_id );
		if ( '' === $name ) {
			return [];
		}

		$node = [
			'@type' => $this->get_schema_type(),
			'@id'   => $this->org_id(),
			'name'  => $name,
			'url'   => $this->get_org_url( $practice_id ),
		];

		$specialty = $this->get_specialty();
		if ( '' !== $specialty ) {
			$node['medicalSpecialty'] = $specialty;
		}

		$logo = $this->get_org_logo();
		if ( '' !== $logo ) {
			$node['logo']  = $logo;
			$node['image'] = $logo;
		}

		if ( $practice_id > 0 ) {
			$phone = (string) get_post_meta( $practice_id, 'brag_book_gallery_practice_phone', true );
			if ( '' !== $phone ) {
				$node['telephone'] = $phone;
			}

			$address = $this->build_address_node( $practice_id );
			if ( [] !== $address ) {
				$node['address'] = $address;
			}

			// geo is a Place/LocalBusiness property; the bare MedicalOrganization
			// type is not a Place, so only attach it to location-aware types.
			if ( 'MedicalOrganization' !== $node['@type'] ) {
				$geo = $this->build_geo_node( $practice_id );
				if ( [] !== $geo ) {
					$node['geo'] = $geo;
				}
			}
		}

		return $node;
	}

	/**
	 * Build a PostalAddress node from practice meta.
	 *
	 * @since 4.9.0
	 * @param int $practice_id Practice post ID.
	 * @return array<string, mixed> Address node, or empty array when no parts exist.
	 */
	private function build_address_node( int $practice_id ): array {
		$street = trim(
			(string) get_post_meta( $practice_id, 'brag_book_gallery_practice_street1', true ) . ' ' .
			(string) get_post_meta( $practice_id, 'brag_book_gallery_practice_street2', true )
		);
		$city  = (string) get_post_meta( $practice_id, 'brag_book_gallery_practice_city', true );
		$state = (string) get_post_meta( $practice_id, 'brag_book_gallery_practice_state', true );
		$zip   = (string) get_post_meta( $practice_id, 'brag_book_gallery_practice_zip', true );

		$parts = array_filter(
			[
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'addressRegion'   => $state,
				'postalCode'      => $zip,
			]
		);

		if ( [] === $parts ) {
			return [];
		}

		return array_merge( [ '@type' => 'PostalAddress' ], $parts );
	}

	/**
	 * Build a GeoCoordinates node from practice meta.
	 *
	 * @since 4.9.0
	 * @param int $practice_id Practice post ID.
	 * @return array<string, mixed> Geo node, or empty array when coordinates missing.
	 */
	private function build_geo_node( int $practice_id ): array {
		$lat = get_post_meta( $practice_id, 'brag_book_gallery_practice_latitude', true );
		$lng = get_post_meta( $practice_id, 'brag_book_gallery_practice_longitude', true );

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return [];
		}

		return [
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
		];
	}

	/**
	 * Build Physician nodes for the case's providers.
	 *
	 * @since 4.9.0
	 * @param int $case_post_id Case post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_physician_nodes( int $case_post_id ): array {
		if ( '1' !== (string) get_option( self::OPTION_PHYSICIAN, '1' ) ) {
			return [];
		}
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_PROVIDERS ) ) {
			return [];
		}

		$terms = wp_get_post_terms( $case_post_id, Taxonomies::TAXONOMY_PROVIDERS );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$specialty = $this->get_specialty();
		$nodes     = [];

		foreach ( $terms as $term ) {
			if ( '' === $term->name ) {
				continue;
			}

			$node = [
				'@type'    => 'Physician',
				'@id'      => home_url( '/#brag-book-physician-' . $term->term_id ),
				'name'     => $term->name,
				'worksFor' => [ '@id' => $this->org_id() ],
			];

			$image = (string) get_term_meta( $term->term_id, 'provider_image_url', true );
			if ( '' !== $image ) {
				$node['image'] = $image;
			}

			$profile_url = (string) get_term_meta( $term->term_id, 'provider_profile_url', true );
			if ( '' !== $profile_url ) {
				$node['url'] = $profile_url;
			}

			if ( '' !== $specialty ) {
				$node['medicalSpecialty'] = $specialty;
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Build MedicalProcedure nodes for the case's procedures.
	 *
	 * @since 4.9.0
	 * @param int $case_post_id Case post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_procedure_nodes( int $case_post_id ): array {
		if ( ! taxonomy_exists( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			return [];
		}

		$terms = wp_get_post_terms( $case_post_id, Taxonomies::TAXONOMY_PROCEDURES );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$nodes = [];
		foreach ( $terms as $term ) {
			if ( '' === $term->name ) {
				continue;
			}

			$node = [
				'@type' => 'MedicalProcedure',
				'@id'   => home_url( '/#brag-book-procedure-' . $term->term_id ),
				'name'  => $term->name,
			];

			if ( '' !== $term->description ) {
				$node['description'] = wp_strip_all_tags( $term->description );
			}

			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$node['url'] = $link;
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Resolve the case post ID for the current request, across both modes.
	 *
	 * @since 4.9.0
	 * @return int Case post ID, or 0 when not a case view.
	 */
	private function resolve_case_post_id(): int {
		if ( is_singular( Post_Types::POST_TYPE_CASES ) ) {
			return (int) get_queried_object_id();
		}

		return $this->resolve_default_mode_case_id();
	}

	/**
	 * Resolve a case post from a Default-mode gallery URL segment.
	 *
	 * A case view in Default mode is the gallery page URL with extra path
	 * segments (…/{procedure}/{case}); the trailing segment identifies the case.
	 *
	 * @since 4.9.0
	 * @return int Case post ID, or 0 when the URL is not a case view.
	 */
	private function resolve_default_mode_case_id(): int {
		$page_id = $this->current_gallery_page_id();
		if ( $page_id <= 0 ) {
			return 0;
		}

		$permalink = get_permalink( $page_id );
		if ( ! $permalink ) {
			return 0;
		}

		$page_path = trailingslashit( (string) wp_parse_url( $permalink, PHP_URL_PATH ) );
		$request   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$req_path  = trailingslashit( (string) strtok( $request, '?' ) );

		if ( $page_path === $req_path || 0 !== strpos( $req_path, $page_path ) ) {
			return 0;
		}

		$segments = array_filter( explode( '/', trim( substr( $req_path, strlen( $page_path ) ), '/' ) ) );
		if ( count( $segments ) < 2 ) {
			// Need at least {procedure}/{case} to be a case detail view.
			return 0;
		}

		return $this->find_case_post_id( (string) end( $segments ) );
	}

	/**
	 * Find a published case post by its API case ID or slug.
	 *
	 * @since 4.9.0
	 * @param string $identifier Trailing URL segment (numeric case ID or slug).
	 * @return int Case post ID, or 0 when not found.
	 */
	private function find_case_post_id( string $identifier ): int {
		if ( '' === $identifier ) {
			return 0;
		}

		if ( ctype_digit( $identifier ) ) {
			$by_id = get_posts(
				[
					'post_type'      => Post_Types::POST_TYPE_CASES,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => 'brag_book_gallery_case_id',
					'meta_value'     => $identifier,
				]
			);
			if ( ! empty( $by_id ) ) {
				return (int) $by_id[0];
			}
		}

		$by_slug = get_posts(
			[
				'post_type'      => Post_Types::POST_TYPE_CASES,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'name'           => sanitize_title( $identifier ),
			]
		);

		return empty( $by_slug ) ? 0 : (int) $by_slug[0];
	}

	/**
	 * Resolve the practice post tied to a case (via its providers), falling back
	 * to the primary practice.
	 *
	 * @since 4.9.0
	 * @param int $case_post_id Case post ID.
	 * @return int Practice post ID, or 0 when none exists.
	 */
	private function resolve_practice_for_case( int $case_post_id ): int {
		if ( taxonomy_exists( Taxonomies::TAXONOMY_PROVIDERS ) && post_type_exists( Post_Types::POST_TYPE_PRACTICES ) ) {
			$provider_terms = wp_get_post_terms( $case_post_id, Taxonomies::TAXONOMY_PROVIDERS, [ 'fields' => 'ids' ] );

			if ( ! is_wp_error( $provider_terms ) && ! empty( $provider_terms ) ) {
				$practices = get_posts(
					[
						'post_type'      => Post_Types::POST_TYPE_PRACTICES,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'meta_key'       => 'brag_book_gallery_practice_is_primary',
						'orderby'        => 'meta_value_num',
						'order'          => 'DESC',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'tax_query'      => [
							[
								'taxonomy' => Taxonomies::TAXONOMY_PROVIDERS,
								'field'    => 'term_id',
								'terms'    => $provider_terms,
							],
						],
					]
				);

				if ( ! empty( $practices ) ) {
					return (int) $practices[0];
				}
			}
		}

		return $this->resolve_primary_practice_id();
	}

	/**
	 * Resolve the primary practice post ID (or the first available practice).
	 *
	 * @since 4.9.0
	 * @return int Practice post ID, or 0 when none exists.
	 */
	private function resolve_primary_practice_id(): int {
		if ( ! post_type_exists( Post_Types::POST_TYPE_PRACTICES ) ) {
			return 0;
		}

		$primary = get_posts(
			[
				'post_type'      => Post_Types::POST_TYPE_PRACTICES,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'brag_book_gallery_practice_is_primary',
				'meta_value'     => '1',
			]
		);
		if ( ! empty( $primary ) ) {
			return (int) $primary[0];
		}

		$any = get_posts(
			[
				'post_type'      => Post_Types::POST_TYPE_PRACTICES,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'brag_book_gallery_practice_position',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			]
		);

		return empty( $any ) ? 0 : (int) $any[0];
	}

	/**
	 * Whether the current request is a gallery/procedure view (non-case).
	 *
	 * @since 4.9.0
	 * @return bool
	 */
	private function is_gallery_context(): bool {
		if ( taxonomy_exists( Taxonomies::TAXONOMY_PROCEDURES ) && is_tax( Taxonomies::TAXONOMY_PROCEDURES ) ) {
			return true;
		}

		return $this->current_gallery_page_id() > 0;
	}

	/**
	 * The matched shortcode gallery page ID for the current request.
	 *
	 * @since 4.9.0
	 * @return int Gallery page ID, or 0 when the current page is not one.
	 */
	private function current_gallery_page_id(): int {
		if ( ! is_page() ) {
			return 0;
		}

		$current_id = (int) get_queried_object_id();
		if ( $current_id <= 0 ) {
			return 0;
		}

		if ( (int) get_option( 'brag_book_gallery_page_id', 0 ) === $current_id ) {
			return $current_id;
		}

		foreach ( (array) get_option( 'brag_book_gallery_stored_pages_ids', [] ) as $stored_id ) {
			if ( (int) $stored_id === $current_id ) {
				return $current_id;
			}
		}

		return 0;
	}

	/**
	 * The stable @id for the medical organization node.
	 *
	 * Exposed so other emitters (e.g. the case ImageGallery) can reference this
	 * node by @id instead of duplicating the organization details.
	 *
	 * @since 4.9.0
	 * @return string
	 */
	public static function organization_id(): string {
		return home_url( '/#brag-book-medical-organization' );
	}

	/**
	 * The stable @id for the organization node.
	 *
	 * @since 4.9.0
	 * @return string
	 */
	private function org_id(): string {
		return self::organization_id();
	}

	/**
	 * Resolve the configured (validated) schema type.
	 *
	 * @since 4.9.0
	 * @return string
	 */
	private function get_schema_type(): string {
		$type = (string) get_option( self::OPTION_TYPE, self::DEFAULT_TYPE );
		return array_key_exists( $type, self::get_available_types() ) ? $type : self::DEFAULT_TYPE;
	}

	/**
	 * Resolve the configured (validated) medical specialty.
	 *
	 * @since 4.9.0
	 * @return string Specialty token, or '' for none.
	 */
	private function get_specialty(): string {
		$specialty = (string) get_option( self::OPTION_SPECIALTY, self::DEFAULT_SPECIALTY );
		return array_key_exists( $specialty, self::get_available_specialties() ) ? $specialty : self::DEFAULT_SPECIALTY;
	}

	/**
	 * Resolve the organization name: admin override, then practice, then site.
	 *
	 * @since 4.9.0
	 * @param int $practice_id Practice post ID, or 0.
	 * @return string
	 */
	private function get_org_name( int $practice_id ): string {
		$override = trim( (string) get_option( self::OPTION_ORG_NAME, '' ) );
		if ( '' !== $override ) {
			return $override;
		}

		if ( $practice_id > 0 ) {
			$title = get_the_title( $practice_id );
			if ( '' !== $title ) {
				return $title;
			}
		}

		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Resolve the organization URL: practice website, then home URL.
	 *
	 * @since 4.9.0
	 * @param int $practice_id Practice post ID, or 0.
	 * @return string
	 */
	private function get_org_url( int $practice_id ): string {
		if ( $practice_id > 0 ) {
			$website = (string) get_post_meta( $practice_id, 'brag_book_gallery_practice_website_url', true );
			if ( '' !== $website ) {
				return $website;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Resolve the organization logo: admin override, then the site custom logo.
	 *
	 * @since 4.9.0
	 * @return string Logo URL, or '' when none is configured.
	 */
	private function get_org_logo(): string {
		$override = trim( (string) get_option( self::OPTION_ORG_LOGO, '' ) );
		if ( '' !== $override ) {
			return $override;
		}

		$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		return '';
	}
}
