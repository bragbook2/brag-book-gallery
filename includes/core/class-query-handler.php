<?php
declare( strict_types=1 );

/**
 * Query Handler
 *
 * Enterprise-grade database query manager for local mode gallery data.
 * Implements advanced caching strategies, security measures, and performance
 * optimizations following WordPress VIP coding standards.
 *
 * Features:
 * - Multi-layer caching with cache groups
 * - Parameterized queries for SQL injection prevention
 * - Performance-optimized database operations
 * - Comprehensive error handling and validation
 * - Statistical analysis capabilities
 * - Taxonomy and meta query builders
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;
use WP_Post;
use WP_Query;
use WP_Error;
use WP_Term;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Handler Class
 *
 * Provides methods for querying gallery data in local mode.
 * Implements caching strategies and security best practices
 * according to WordPress VIP standards.
 *
 * @since 3.0.0
 */
class Query_Handler {

	/**
	 * Cache group identifier for query results.
	 *
	 * @since 3.0.0
	 * @var string Cache group name.
	 */
	private const CACHE_GROUP = 'brag_book_gallery_queries';

	/**
	 * Default cache expiration time in seconds.
	 *
	 * @since 3.0.0
	 * @var int Cache TTL (1 hour).
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Maximum posts per page limit for performance.
	 *
	 * @since 3.0.0
	 * @var int Query result limit.
	 */
	private const MAX_POSTS_PER_PAGE = 100;

	/**
	 * Valid orderby options for security.
	 *
	 * @since 3.0.0
	 * @var array<string, string> Allowed orderby values.
	 */
	private const VALID_ORDERBY = [
		'date' => 'post_date',
		'modified' => 'post_modified',
		'title' => 'post_title',
		'menu_order' => 'menu_order',
		'rand' => 'rand',
		'id' => 'ID',
	];

	/**
	 * Valid sort orders.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Allowed order values.
	 */
	private const VALID_ORDER = [ 'ASC', 'DESC' ];

	/**
	 * Common cache keys for efficient clearing.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Frequently used cache keys.
	 */
	private const COMMON_CACHE_KEYS = [
		'gallery_stats',
		'featured_galleries_6',
		'featured_galleries_10',
		'recent_galleries_10',
		'recent_galleries_20',
	];

	/**
	 * Get galleries with filters and pagination.
	 *
	 * Primary query method with comprehensive filtering, pagination,
	 * and caching. Implements security best practices and performance
	 * optimizations for enterprise-grade applications.
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $args {
	 *     Query configuration array.
	 *
	 *     @type int    $posts_per_page Number of posts (1-100).
	 *     @type int    $paged          Page number (1+).
	 *     @type string $post_status    Post status filter.
	 *     @type string $orderby        Sort field (date|title|modified|menu_order|rand|id).
	 *     @type string $order          Sort direction (ASC|DESC).
	 *     @type string $category       Category slug or ID.
	 *     @type string $procedure      Procedure slug or ID.
	 *     @type string $patient_age    Patient age filter.
	 *     @type string $patient_gender Patient gender filter.
	 *     @type string $search         Search term.
	 *     @type array  $meta_query     Meta query array.
	 *     @type array  $tax_query      Taxonomy query array.
	 *     @type bool   $include_meta   Include post meta data.
	 *     @type bool   $include_images Include image data.
	 *     @type bool   $cache          Enable result caching.
	 * }
	 *
	 * @return array<string, mixed> {
	 *     Formatted gallery results.
	 *
	 *     @type array<int, array> $posts Gallery post data.
	 *     @type array<string, mixed> $pagination Pagination metadata.
	 * }
	 *
	 * @throws \InvalidArgumentException If arguments are invalid.
	 *
	 * @example
	 * ```php
	 * $results = $query_handler->get_galleries([
	 *     'posts_per_page' => 12,
	 *     'category' => 'cosmetic-surgery',
	 *     'orderby' => 'date',
	 *     'order' => 'DESC'
	 * ]);
	 * ```
	 */
	public function get_galleries( array $args = [] ): array {
		try {
			// Set default arguments
			$defaults = [
				'posts_per_page' => 12,
				'paged'          => 1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'category'       => '',
				'procedure'      => '',
				'patient_age'    => '',
				'patient_gender' => '',
				'search'         => '',
				'meta_query'     => [],
				'tax_query'      => [],
				'include_meta'   => true,
				'include_images' => true,
				'cache'          => true,
			];

			$args = wp_parse_args( $args, $defaults );

			// Sanitize and validate arguments
			$args = $this->sanitize_query_args( $args );

			// Build cache key and check cache
			$cache_key = $this->build_cache_key( 'galleries', $args );

			if ( $args['cache'] ) {
				$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
				if ( false !== $cached ) {
					return $cached;
				}
			}

			// Build WP_Query arguments
			$query_args = [
				'post_type'      => Gallery_Post_Type::POST_TYPE,
				'post_status'    => sanitize_key( $args['post_status'] ),
				'posts_per_page' => $args['posts_per_page'],
				'paged'          => $args['paged'],
				'orderby'        => $args['orderby'],
				'order'          => $args['order'],
				'meta_query'     => $args['meta_query'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'tax_query'      => $args['tax_query'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			];

			// Add search query
			if ( ! empty( $args['search'] ) ) {
				$query_args['s'] = sanitize_text_field( $args['search'] );
			}

			// Add taxonomy filters
			$this->add_taxonomy_filters( $query_args, $args );

			// Add patient filters
			$this->add_patient_filters( $query_args, $args );

			// Set query relations
			$this->set_query_relations( $query_args );

			// Execute query
			$query = new WP_Query( $query_args );

			// Build result array
			$result = $this->build_query_result( $query, $args );

			// Reset global post data
			wp_reset_postdata();

			// Cache result
			if ( $args['cache'] ) {
				wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_EXPIRATION );
			}

			return $result;
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Gallery query error: %s', $e->getMessage() ) );
			return [
				'posts'      => [],
				'pagination' => [],
			];
		}
	}

	/**
	 * Sanitize query arguments.
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed> Sanitized arguments.
	 */
	private function sanitize_query_args( array $args ): array {
		// Sanitize numeric values
		$args['posts_per_page'] = min( absint( $args['posts_per_page'] ), self::MAX_POSTS_PER_PAGE );
		$args['paged'] = max( absint( $args['paged'] ), 1 );

		// Validate orderby
		$args['orderby'] = array_key_exists( $args['orderby'], self::VALID_ORDERBY )
			? $args['orderby']
			: 'date';

		// Validate order
		$args['order'] = in_array( strtoupper( $args['order'] ), self::VALID_ORDER, true )
			? strtoupper( $args['order'] )
			: 'DESC';

		return $args;
	}

	/**
	 * Build cache key from arguments.
	 *
	 * @since 3.0.0
	 * @param string $prefix Key prefix.
	 * @param array<string, mixed> $args Arguments.
	 * @return string Cache key.
	 */
	private function build_cache_key( string $prefix, array $args ): string {
		return $prefix . '_' . md5( wp_json_encode( $args ) );
	}

	/**
	 * Set query relations.
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $query_args Query arguments by reference.
	 * @return void
	 */
	private function set_query_relations( array &$query_args ): void {
		// Set tax_query relation if multiple taxonomies
		if ( isset( $query_args['tax_query'] ) && count( $query_args['tax_query'] ) > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}

		// Set meta_query relation if multiple meta queries
		if ( isset( $query_args['meta_query'] ) && count( $query_args['meta_query'] ) > 1 ) {
			$query_args['meta_query']['relation'] = 'AND';
		}
	}

	/**
	 * Build query result array.
	 *
	 * @since 3.0.0
	 * @param WP_Query $query WordPress query object.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed> Formatted result.
	 */
	private function build_query_result( WP_Query $query, array $args ): array {
		$result = [
			'posts'      => [],
			'pagination' => [
				'current_page'   => $args['paged'],
				'total_pages'    => $query->max_num_pages,
				'total_posts'    => $query->found_posts,
				'posts_per_page' => $args['posts_per_page'],
				'has_prev'       => $args['paged'] > 1,
				'has_next'       => $args['paged'] < $query->max_num_pages,
			],
		];

		// Process posts
		while ( $query->have_posts() ) {
			$query->the_post();
			$post = get_post();
			if ( $post instanceof WP_Post ) {
				$result['posts'][] = $this->format_post_data( $post, $args );
			}
		}

		return $result;
	}

	/**
	 * Get single gallery by ID or slug
	 *
	 * Retrieves a single gallery post with related data.
	 * Uses caching for improved performance.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $id_or_slug Post ID or slug.
	 * @param array      $args {
	 *     Additional arguments.
	 *
	 *     @type bool $include_meta    Include post meta data.
	 *     @type bool $include_images  Include image data.
	 *     @type bool $include_related Include related galleries.
	 *     @type bool $cache           Enable caching.
	 * }
	 *
	 * @return object|null Gallery object or null if not found.
	 */
	public function get_gallery( $id_or_slug, array $args = array() ): ?object {
		// Set default arguments.
		$defaults = array(
			'include_meta'    => true,
			'include_images'  => true,
			'include_related' => true,
			'cache'           => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build cache key.
		$cache_key = 'gallery_' . md5( wp_json_encode( array( $id_or_slug, $args ) ) );

		// Try to get from cache.
		if ( $args['cache'] ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Get post.
		$post = null;
		if ( is_numeric( $id_or_slug ) ) {
			$post = get_post( absint( $id_or_slug ) );
		} else {
			$posts = get_posts( array(
				'post_type'      => Gallery_Post_Type::POST_TYPE,
				'name'           => sanitize_title( $id_or_slug ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			) );

			$post = ! empty( $posts ) ? $posts[0] : null;
		}

		// Validate post.
		if ( ! $post instanceof WP_Post || $post->post_type !== Gallery_Post_Type::POST_TYPE ) {
			return null;
		}

		// Format gallery data.
		$gallery = $this->format_post_data( $post, $args );

		// Add related galleries if requested.
		if ( $args['include_related'] ) {
			$gallery['related'] = $this->get_related_galleries( $post->ID );
		}

		// Convert to object.
		$gallery_object = (object) $gallery;

		// Cache result.
		if ( $args['cache'] ) {
			wp_cache_set( $cache_key, $gallery_object, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $gallery_object;
	}

	/**
	 * Search galleries
	 *
	 * Performs a search across gallery posts including meta fields.
	 * Implements proper escaping and sanitization per VIP standards.
	 *
	 * @since 3.0.0
	 *
	 * @param string $search_term Search term.
	 * @param array  $args {
	 *     Additional arguments.
	 *
	 *     @type int   $posts_per_page Number of results.
	 *     @type array $search_fields  Fields to search in.
	 * }
	 *
	 * @return array Search results.
	 */
	public function search_galleries( string $search_term, array $args = array() ): array {
		// Sanitize search term.
		$search_term = sanitize_text_field( $search_term );

		if ( empty( $search_term ) ) {
			return array(
				'posts'      => array(),
				'pagination' => array(),
			);
		}

		// Set default arguments.
		$defaults = array(
			'posts_per_page' => 20,
			'search_fields'  => array( 'title', 'content', 'excerpt', 'meta' ),
		);

		$args = wp_parse_args( $args, $defaults );
		$args['search'] = $search_term;

		// Build meta query for searching in meta fields.
		if ( in_array( 'meta', $args['search_fields'], true ) ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_brag_patient_info',
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_brag_procedure_details',
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_brag_seo_data',
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
			);
		}

		return $this->get_galleries( $args );
	}

	/**
	 * Get galleries by category
	 *
	 * Retrieves galleries filtered by category.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $category Category ID or slug.
	 * @param array      $args     Additional arguments.
	 *
	 * @return array Category galleries.
	 */
	public function get_galleries_by_category( $category, array $args = array() ): array {
		// Sanitize category input.
		if ( is_numeric( $category ) ) {
			$args['category'] = absint( $category );
		} else {
			$args['category'] = sanitize_title( $category );
		}

		return $this->get_galleries( $args );
	}

	/**
	 * Get galleries by procedure
	 *
	 * Retrieves galleries filtered by procedure.
	 *
	 * @since 3.0.0
	 *
	 * @param int|string $procedure Procedure ID or slug.
	 * @param array      $args      Additional arguments.
	 *
	 * @return array Procedure galleries.
	 */
	public function get_galleries_by_procedure( $procedure, array $args = array() ): array {
		// Sanitize procedure input.
		if ( is_numeric( $procedure ) ) {
			$args['procedure'] = absint( $procedure );
		} else {
			$args['procedure'] = sanitize_title( $procedure );
		}

		return $this->get_galleries( $args );
	}

	/**
	 * Get featured galleries
	 *
	 * Retrieves featured galleries with fallback to recent posts.
	 * Uses caching for improved performance.
	 *
	 * @since 3.0.0
	 *
	 * @param int $limit Number of galleries to return (max 50).
	 *
	 * @return array Featured galleries.
	 */
	public function get_featured_galleries( int $limit = 6 ): array {
		// Sanitize and limit input.
		$limit = min( max( absint( $limit ), 1 ), 50 );

		// Build cache key.
		$cache_key = 'featured_galleries_' . $limit;
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Query featured galleries.
		$args = array(
			'posts_per_page' => $limit,
			'meta_query'     => array(
				array(
					'key'     => '_brag_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		);

		$result = $this->get_galleries( $args );

		// If not enough featured galleries, fill with recent ones.
		$current_count = count( $result['posts'] );
		if ( $current_count < $limit ) {
			$needed = $limit - $current_count;
			$post_ids = wp_list_pluck( $result['posts'], 'id' );

			$recent_args = array(
				'posts_per_page' => $needed,
				'post__not_in'   => $post_ids,
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			$recent = $this->get_galleries( $recent_args );
			$result['posts'] = array_merge( $result['posts'], $recent['posts'] );
		}

		// Cache result.
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $result;
	}

	/**
	 * Get recent galleries
	 *
	 * Retrieves the most recent gallery posts.
	 *
	 * @since 3.0.0
	 *
	 * @param int $limit Number of galleries to return (max 50).
	 *
	 * @return array Recent galleries.
	 */
	public function get_recent_galleries( int $limit = 10 ): array {
		// Sanitize and limit input.
		$limit = min( max( absint( $limit ), 1 ), 50 );

		return $this->get_galleries( array(
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
	}

	/**
	 * Get related galleries based on taxonomies
	 *
	 * Finds galleries related to a given post based on shared taxonomies.
	 * Uses caching and limits results for performance.
	 *
	 * @since 3.0.0
	 *
	 * @param int $post_id Post ID to find related galleries for.
	 * @param int $limit   Number of related galleries to return (max 20).
	 *
	 * @return array Related galleries.
	 */
	public function get_related_galleries( int $post_id, int $limit = 4 ): array {
		// Validate and sanitize inputs.
		$post_id = absint( $post_id );
		$limit   = min( max( absint( $limit ), 1 ), 20 );

		if ( ! $post_id ) {
			return array();
		}

		// Build cache key.
		$cache_key = 'related_galleries_' . $post_id . '_' . $limit;
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get current post's taxonomies.
		$categories = wp_get_object_terms(
			$post_id,
			Gallery_Taxonomies::CATEGORY_TAXONOMY,
			array( 'fields' => 'ids' )
		);

		$procedures = wp_get_object_terms(
			$post_id,
			Gallery_Taxonomies::PROCEDURE_TAXONOMY,
			array( 'fields' => 'ids' )
		);

		// Build taxonomy query.
		$tax_query = array( 'relation' => 'OR' );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $categories,
			);
		}

		if ( ! empty( $procedures ) && ! is_wp_error( $procedures ) ) {
			$tax_query[] = array(
				'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $procedures,
			);
		}

		// If no taxonomies found, return empty.
		if ( count( $tax_query ) <= 1 ) {
			return array();
		}

		// Query related galleries.
		$args = array(
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
			'tax_query'      => $tax_query,
			'orderby'        => 'rand',
			'cache'          => false, // Don't double cache.
		);

		$result = $this->get_galleries( $args );

		// Cache result.
		wp_cache_set( $cache_key, $result['posts'], self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $result['posts'];
	}

	/**
	 * Get gallery statistics
	 *
	 * Retrieves aggregated statistics about galleries.
	 * Uses caching for performance optimization.
	 *
	 * @since 3.0.0
	 *
	 * @return array Gallery statistics.
	 */
	public function get_gallery_stats(): array {
		// Try cache first.
		$cache_key = 'gallery_stats';
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Get post counts.
		$post_counts = wp_count_posts( Gallery_Post_Type::POST_TYPE );

		// Get taxonomy counts.
		$category_count  = wp_count_terms( array( 'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY ) );
		$procedure_count = wp_count_terms( array( 'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY ) );

		// Build stats array.
		$stats = array(
			'total_galleries'  => isset( $post_counts->publish ) ? absint( $post_counts->publish ) : 0,
			'draft_galleries'  => isset( $post_counts->draft ) ? absint( $post_counts->draft ) : 0,
			'total_categories' => is_wp_error( $category_count ) ? 0 : absint( $category_count ),
			'total_procedures' => is_wp_error( $procedure_count ) ? 0 : absint( $procedure_count ),
		);

		// Get additional stats.
		$stats['recent_galleries']      = $this->get_recent_gallery_count( 7 );
		$stats['featured_galleries']    = $this->get_featured_gallery_count();
		$stats['most_popular_category'] = $this->get_most_popular_category();
		$stats['most_popular_procedure'] = $this->get_most_popular_procedure();

		// Cache stats.
		wp_cache_set( $cache_key, $stats, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $stats;
	}

	/**
	 * Format post data for API response
	 *
	 * Formats a WordPress post object into a standardized array structure.
	 * Includes related data based on provided arguments.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post $post WordPress post object.
	 * @param array   $args {
	 *     Formatting arguments.
	 *
	 *     @type bool $include_meta   Include post meta data.
	 *     @type bool $include_images Include image data.
	 * }
	 *
	 * @return array Formatted post data.
	 */
	private function format_post_data( WP_Post $post, array $args = array() ): array {
		// Basic post data.
		$data = array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'slug'      => $post->post_name,
			'excerpt'   => get_the_excerpt( $post ),
			'content'   => apply_filters( 'the_content', $post->post_content ),
			'date'      => get_the_date( 'c', $post ),
			'modified'  => get_the_modified_date( 'c', $post ),
			'status'    => $post->post_status,
			'permalink' => get_permalink( $post ),
		);

		// Add featured image.
		if ( has_post_thumbnail( $post ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post );
			$data['featured_image'] = array(
				'id'  => absint( $thumbnail_id ),
				'url' => get_the_post_thumbnail_url( $post, 'large' ),
				'alt' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
			);
		}

		// Add taxonomies.
		$data['categories'] = $this->get_post_terms( $post->ID, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		$data['procedures'] = $this->get_post_terms( $post->ID, Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		// Add meta data if requested.
		if ( ! empty( $args['include_meta'] ) ) {
			$data['meta'] = $this->get_post_meta_data( $post->ID );
		}

		// Add images if requested.
		if ( ! empty( $args['include_images'] ) ) {
			$data['images'] = $this->get_post_images( $post->ID );
		}

		return $data;
	}

	/**
	 * Get post meta data
	 *
	 * Retrieves and formats post meta data.
	 * Properly decodes JSON data and handles errors.
	 *
	 * @since 3.0.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Meta data.
	 */
	private function get_post_meta_data( int $post_id ): array {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}

		$meta = array();

		// Patient information.
		$patient_info = get_post_meta( $post_id, '_brag_patient_info', true );
		if ( $patient_info ) {
			$decoded = json_decode( $patient_info, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$meta['patient'] = $decoded;
			}
		}

		// Procedure details.
		$procedure_details = get_post_meta( $post_id, '_brag_procedure_details', true );
		if ( $procedure_details ) {
			$decoded = json_decode( $procedure_details, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$meta['procedure_details'] = $decoded;
			}
		}

		// SEO data.
		$seo_data = get_post_meta( $post_id, '_brag_seo_data', true );
		if ( $seo_data ) {
			$decoded = json_decode( $seo_data, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$meta['seo'] = $decoded;
			}
		}

		// Sync information.
		$meta['case_id']     = get_post_meta( $post_id, '_brag_case_id', true );
		$meta['last_synced'] = get_post_meta( $post_id, '_brag_last_synced', true );

		return $meta;
	}

	/**
	 * Get post images
	 *
	 * Retrieves before and after images for a gallery post.
	 * Handles both attachment IDs and legacy JSON data.
	 *
	 * @since 3.0.0
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Image data with 'before' and 'after' arrays.
	 */
	private function get_post_images( int $post_id ): array {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array(
				'before' => array(),
				'after'  => array(),
			);
		}

		$images = array(
			'before' => array(),
			'after'  => array(),
		);

		// Get image attachment IDs.
		$before_image_ids = get_post_meta( $post_id, '_brag_before_image_ids', true );
		$after_image_ids  = get_post_meta( $post_id, '_brag_after_image_ids', true );

		// Process before images.
		if ( $before_image_ids && is_array( $before_image_ids ) ) {
			foreach ( $before_image_ids as $attachment_id ) {
				$image_data = $this->get_image_data( absint( $attachment_id ) );
				if ( $image_data ) {
					$images['before'][] = $image_data;
				}
			}
		}

		// Process after images.
		if ( $after_image_ids && is_array( $after_image_ids ) ) {
			foreach ( $after_image_ids as $attachment_id ) {
				$image_data = $this->get_image_data( absint( $attachment_id ) );
				if ( $image_data ) {
					$images['after'][] = $image_data;
				}
			}
		}

		// Fallback to meta data if no attachments found.
		if ( empty( $images['before'] ) ) {
			$before_images_meta = get_post_meta( $post_id, '_brag_before_images', true );
			if ( $before_images_meta ) {
				$decoded = json_decode( $before_images_meta, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$images['before'] = $decoded;
				}
			}
		}

		if ( empty( $images['after'] ) ) {
			$after_images_meta = get_post_meta( $post_id, '_brag_after_images', true );
			if ( $after_images_meta ) {
				$decoded = json_decode( $after_images_meta, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$images['after'] = $decoded;
				}
			}
		}

		return $images;
	}

	/**
	 * Get image data for attachment
	 *
	 * Retrieves comprehensive data for an image attachment.
	 * Returns null if attachment is invalid.
	 *
	 * @since 3.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array|null Image data or null if not found.
	 */
	private function get_image_data( int $attachment_id ): ?array {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return null;
		}

		// Get attachment post.
		$attachment = get_post( $attachment_id );

		if ( ! $attachment instanceof WP_Post || $attachment->post_type !== 'attachment' ) {
			return null;
		}

		// Build image data array.
		$image_data = array(
			'id'          => $attachment_id,
			'url'         => wp_get_attachment_image_url( $attachment_id, 'large' ),
			'thumbnail'   => wp_get_attachment_image_url( $attachment_id, 'medium' ),
			'full'        => wp_get_attachment_image_url( $attachment_id, 'full' ),
			'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title'       => $attachment->post_title,
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
		);

		// Add image metadata.
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) ) {
			$image_data['width']     = isset( $metadata['width'] ) ? absint( $metadata['width'] ) : 0;
			$image_data['height']    = isset( $metadata['height'] ) ? absint( $metadata['height'] ) : 0;
			$image_data['file_size'] = isset( $metadata['filesize'] ) ? absint( $metadata['filesize'] ) : 0;
		}

		return $image_data;
	}

	/**
	 * Get post terms
	 *
	 * Retrieves and formats terms for a post.
	 * Properly handles errors and returns empty array on failure.
	 *
	 * @since 3.0.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array Array of term data.
	 */
	private function get_post_terms( int $post_id, string $taxonomy ): array {
		$post_id = absint( $post_id );
		if ( ! $post_id || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		// Get terms for the post.
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$terms_data = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$term_link = get_term_link( $term );

			$terms_data[] = array(
				'id'          => absint( $term->term_id ),
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'count'       => absint( $term->count ),
				'link'        => is_wp_error( $term_link ) ? '' : $term_link,
			);
		}

		return $terms_data;
	}

	/**
	 * Add taxonomy filters to query
	 *
	 * Adds category and procedure taxonomy filters to the query args.
	 *
	 * @since 3.0.0
	 *
	 * @param array $query_args Query arguments array (passed by reference).
	 * @param array $args       Original arguments.
	 *
	 * @return void
	 */
	private function add_taxonomy_filters( array &$query_args, array $args ): void {
		// Add category filter.
		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY,
				'field'    => is_numeric( $args['category'] ) ? 'term_id' : 'slug',
				'terms'    => is_numeric( $args['category'] )
							? absint( $args['category'] )
							: sanitize_title( $args['category'] ),
			);
		}

		// Add procedure filter.
		if ( ! empty( $args['procedure'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
				'field'    => is_numeric( $args['procedure'] ) ? 'term_id' : 'slug',
				'terms'    => is_numeric( $args['procedure'] )
							? absint( $args['procedure'] )
							: sanitize_title( $args['procedure'] ),
			);
		}
	}

	/**
	 * Add patient filters to query
	 *
	 * Adds patient age and gender meta filters to the query args.
	 *
	 * @since 3.0.0
	 *
	 * @param array $query_args Query arguments array (passed by reference).
	 * @param array $args       Original arguments.
	 *
	 * @return void
	 */
	private function add_patient_filters( array &$query_args, array $args ): void {
		// Add patient age filter.
		if ( ! empty( $args['patient_age'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_brag_patient_info',
				'value'   => '"age":"' . sanitize_text_field( $args['patient_age'] ) . '"',
				'compare' => 'LIKE',
			);
		}

		// Add patient gender filter.
		if ( ! empty( $args['patient_gender'] ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_brag_patient_info',
				'value'   => '"gender":"' . sanitize_text_field( $args['patient_gender'] ) . '"',
				'compare' => 'LIKE',
			);
		}
	}

	/**
	 * Get recent gallery count
	 *
	 * Gets the count of galleries published in the last N days.
	 * Uses direct database query for performance.
	 *
	 * @since 3.0.0
	 *
	 * @param int $days Number of days to look back (max 365).
	 *
	 * @return int Gallery count.
	 */
	private function get_recent_gallery_count( int $days ): int {
		global $wpdb;

		// Sanitize and limit days.
		$days = min( max( absint( $days ), 1 ), 365 );

		// Calculate date threshold.
		$date_query = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Query for count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s
				 AND post_status = 'publish'
				 AND post_date >= %s",
				Gallery_Post_Type::POST_TYPE,
				$date_query
			)
		);

		return absint( $count );
	}

	/**
	 * Get featured gallery count
	 *
	 * Gets the count of featured galleries.
	 * Uses direct database query with proper JOIN for performance.
	 *
	 * @since 3.0.0
	 *
	 * @return int Featured gallery count.
	 */
	private function get_featured_gallery_count(): int {
		global $wpdb;

		// Query for featured galleries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s
				 AND p.post_status = 'publish'
				 AND pm.meta_key = '_brag_featured'
				 AND pm.meta_value = '1'",
				Gallery_Post_Type::POST_TYPE
			)
		);

		return absint( $count );
	}

	/**
	 * Get most popular category
	 *
	 * Retrieves the category with the most galleries.
	 *
	 * @since 3.0.0
	 *
	 * @return array|null Category data or null if none found.
	 */
	private function get_most_popular_category(): ?array {
		$terms = get_terms( array(
			'taxonomy'   => Gallery_Taxonomies::CATEGORY_TAXONOMY,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 1,
			'hide_empty' => true,
		) );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			if ( $term instanceof WP_Term ) {
				return array(
					'id'    => absint( $term->term_id ),
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => absint( $term->count ),
				);
			}
		}

		return null;
	}

	/**
	 * Get most popular procedure
	 *
	 * Retrieves the procedure with the most galleries.
	 *
	 * @since 3.0.0
	 *
	 * @return array|null Procedure data or null if none found.
	 */
	private function get_most_popular_procedure(): ?array {
		$terms = get_terms( array(
			'taxonomy'   => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 1,
			'hide_empty' => true,
		) );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			if ( $term instanceof WP_Term ) {
				return array(
					'id'    => absint( $term->term_id ),
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => absint( $term->count ),
				);
			}
		}

		return null;
	}

	/**
	 * Clear query cache
	 *
	 * Clears cached query results.
	 * Can clear specific key or entire cache group.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Optional specific cache key to clear.
	 *
	 * @return void
	 */
	public function clear_cache( string $key = '' ): void {
		if ( ! empty( $key ) ) {
			wp_cache_delete( sanitize_key( $key ), self::CACHE_GROUP );
		} else {
			// Clear entire cache group if supported.
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( self::CACHE_GROUP );
			} else {
				// Fallback: Clear common keys
				foreach ( self::COMMON_CACHE_KEYS as $cache_key ) {
					wp_cache_delete( $cache_key, self::CACHE_GROUP );
				}
			}
		}
	}

	/**
	 * Get cache statistics.
	 *
	 * Returns comprehensive cache configuration and status information.
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Cache statistics and configuration.
	 */
	public function get_cache_stats(): array {
		return [
			'cache_group'      => self::CACHE_GROUP,
			'cache_expiration' => self::CACHE_EXPIRATION,
			'cache_enabled'    => wp_using_ext_object_cache(),
			'max_posts_limit'  => self::MAX_POSTS_PER_PAGE,
		];
	}

	/**
	 * Get post by slug.
	 *
	 * @since 3.0.0
	 * @param string $slug Post slug.
	 * @return WP_Post|null Post object or null.
	 */
	private function get_post_by_slug( string $slug ): ?WP_Post {
		$posts = get_posts( [
			'post_type'      => Gallery_Post_Type::POST_TYPE,
			'name'           => $slug,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		] );

		return ! empty( $posts ) ? $posts[0] : null;
	}
}
