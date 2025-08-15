<?php
/**
 * Query Handler
 *
 * Handles database queries for local mode gallery data.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Taxonomies\Gallery_Taxonomies;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Handler Class
 *
 * Provides methods for querying gallery data in local mode.
 *
 * @since 3.0.0
 */
class Query_Handler {

	/**
	 * Cache group for query results
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_GROUP = 'brag_gallery_queries';

	/**
	 * Default cache expiration time (1 hour)
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600;

	/**
	 * Get galleries with filters and pagination
	 *
	 * @since 3.0.0
	 * @param array $args Query arguments.
	 * @return array Gallery data with posts and pagination info.
	 */
	public function get_galleries( array $args = array() ): array {
		$defaults = array(
			'posts_per_page' => 12,
			'paged' => 1,
			'post_status' => 'publish',
			'orderby' => 'date',
			'order' => 'DESC',
			'category' => '',
			'procedure' => '',
			'patient_age' => '',
			'patient_gender' => '',
			'search' => '',
			'meta_query' => array(),
			'tax_query' => array(),
			'include_meta' => true,
			'include_images' => true,
			'cache' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build cache key
		$cache_key = 'galleries_' . md5( serialize( $args ) );
		
		if ( $args['cache'] ) {
			$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Build WP_Query arguments
		$query_args = array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_status' => $args['post_status'],
			'posts_per_page' => $args['posts_per_page'],
			'paged' => $args['paged'],
			'orderby' => $args['orderby'],
			'order' => $args['order'],
			'meta_query' => $args['meta_query'],
			'tax_query' => $args['tax_query'],
		);

		// Add search
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		// Add taxonomy filters
		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY,
				'field' => is_numeric( $args['category'] ) ? 'term_id' : 'slug',
				'terms' => $args['category'],
			);
		}

		if ( ! empty( $args['procedure'] ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
				'field' => is_numeric( $args['procedure'] ) ? 'term_id' : 'slug',
				'terms' => $args['procedure'],
			);
		}

		// Add patient filters
		if ( ! empty( $args['patient_age'] ) ) {
			$query_args['meta_query'][] = array(
				'key' => '_brag_patient_info',
				'value' => '"age":"' . sanitize_text_field( $args['patient_age'] ) . '"',
				'compare' => 'LIKE',
			);
		}

		if ( ! empty( $args['patient_gender'] ) ) {
			$query_args['meta_query'][] = array(
				'key' => '_brag_patient_info',
				'value' => '"gender":"' . sanitize_text_field( $args['patient_gender'] ) . '"',
				'compare' => 'LIKE',
			);
		}

		// Set tax_query relation if multiple taxonomies
		if ( count( $query_args['tax_query'] ) > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}

		// Set meta_query relation if multiple meta queries
		if ( count( $query_args['meta_query'] ) > 1 ) {
			$query_args['meta_query']['relation'] = 'AND';
		}

		// Execute query
		$query = new \WP_Query( $query_args );

		$result = array(
			'posts' => array(),
			'pagination' => array(
				'current_page' => $args['paged'],
				'total_pages' => $query->max_num_pages,
				'total_posts' => $query->found_posts,
				'posts_per_page' => $args['posts_per_page'],
				'has_prev' => $args['paged'] > 1,
				'has_next' => $args['paged'] < $query->max_num_pages,
			),
		);

		// Process posts
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_data = $this->format_post_data( get_post(), $args );
			$result['posts'][] = $post_data;
		}

		wp_reset_postdata();

		// Cache result
		if ( $args['cache'] ) {
			wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		return $result;
	}

	/**
	 * Get single gallery by ID or slug
	 *
	 * @since 3.0.0
	 * @param mixed $id_or_slug Post ID or slug.
	 * @param array $args Additional arguments.
	 * @return object|null Gallery object or null if not found.
	 */
	public function get_gallery( $id_or_slug, array $args = array() ): ?object {
		$defaults = array(
			'include_meta' => true,
			'include_images' => true,
			'include_related' => true,
			'cache' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Get post
		if ( is_numeric( $id_or_slug ) ) {
			$post = get_post( (int) $id_or_slug );
		} else {
			$posts = get_posts( array(
				'post_type' => Gallery_Post_Type::POST_TYPE,
				'name' => sanitize_title( $id_or_slug ),
				'post_status' => 'publish',
				'numberposts' => 1,
			) );
			
			$post = ! empty( $posts ) ? $posts[0] : null;
		}

		if ( ! $post || $post->post_type !== Gallery_Post_Type::POST_TYPE ) {
			return null;
		}

		$gallery = $this->format_post_data( $post, $args );

		// Add related galleries
		if ( $args['include_related'] ) {
			$gallery->related = $this->get_related_galleries( $post->ID );
		}

		return (object) $gallery;
	}

	/**
	 * Search galleries
	 *
	 * @since 3.0.0
	 * @param string $search_term Search term.
	 * @param array  $args Additional arguments.
	 * @return array Search results.
	 */
	public function search_galleries( string $search_term, array $args = array() ): array {
		$defaults = array(
			'posts_per_page' => 20,
			'search_fields' => array( 'title', 'content', 'excerpt', 'meta' ),
		);

		$args = wp_parse_args( $args, $defaults );
		$args['search'] = $search_term;

		// Build meta query for searching in meta fields
		if ( in_array( 'meta', $args['search_fields'], true ) ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => '_brag_patient_info',
					'value' => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key' => '_brag_procedure_details',
					'value' => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key' => '_brag_seo_data',
					'value' => $search_term,
					'compare' => 'LIKE',
				),
			);
		}

		return $this->get_galleries( $args );
	}

	/**
	 * Get galleries by category
	 *
	 * @since 3.0.0
	 * @param mixed $category Category ID or slug.
	 * @param array $args Additional arguments.
	 * @return array Category galleries.
	 */
	public function get_galleries_by_category( $category, array $args = array() ): array {
		$args['category'] = $category;
		return $this->get_galleries( $args );
	}

	/**
	 * Get galleries by procedure
	 *
	 * @since 3.0.0
	 * @param mixed $procedure Procedure ID or slug.
	 * @param array $args Additional arguments.
	 * @return array Procedure galleries.
	 */
	public function get_galleries_by_procedure( $procedure, array $args = array() ): array {
		$args['procedure'] = $procedure;
		return $this->get_galleries( $args );
	}

	/**
	 * Get featured galleries
	 *
	 * @since 3.0.0
	 * @param int $limit Number of galleries to return.
	 * @return array Featured galleries.
	 */
	public function get_featured_galleries( int $limit = 6 ): array {
		$cache_key = "featured_galleries_{$limit}";
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( $cached !== false ) {
			return $cached;
		}

		$args = array(
			'posts_per_page' => $limit,
			'meta_query' => array(
				array(
					'key' => '_brag_featured',
					'value' => '1',
					'compare' => '=',
				),
			),
			'orderby' => 'menu_order',
			'order' => 'ASC',
		);

		$result = $this->get_galleries( $args );
		
		// If not enough featured galleries, fill with recent ones
		if ( count( $result['posts'] ) < $limit ) {
			$needed = $limit - count( $result['posts'] );
			$recent_args = array(
				'posts_per_page' => $needed,
				'post__not_in' => array_column( $result['posts'], 'id' ),
				'orderby' => 'date',
				'order' => 'DESC',
			);
			
			$recent = $this->get_galleries( $recent_args );
			$result['posts'] = array_merge( $result['posts'], $recent['posts'] );
		}

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		
		return $result;
	}

	/**
	 * Get recent galleries
	 *
	 * @since 3.0.0
	 * @param int $limit Number of galleries to return.
	 * @return array Recent galleries.
	 */
	public function get_recent_galleries( int $limit = 10 ): array {
		return $this->get_galleries( array(
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
		) );
	}

	/**
	 * Get related galleries based on taxonomies
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID to find related galleries for.
	 * @param int $limit Number of related galleries to return.
	 * @return array Related galleries.
	 */
	public function get_related_galleries( int $post_id, int $limit = 4 ): array {
		$cache_key = "related_galleries_{$post_id}_{$limit}";
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( $cached !== false ) {
			return $cached;
		}

		// Get current post's taxonomies
		$categories = wp_get_object_terms( $post_id, Gallery_Taxonomies::CATEGORY_TAXONOMY, array( 'fields' => 'ids' ) );
		$procedures = wp_get_object_terms( $post_id, Gallery_Taxonomies::PROCEDURE_TAXONOMY, array( 'fields' => 'ids' ) );

		$tax_query = array( 'relation' => 'OR' );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY,
				'field' => 'term_id',
				'terms' => $categories,
			);
		}

		if ( ! empty( $procedures ) && ! is_wp_error( $procedures ) ) {
			$tax_query[] = array(
				'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
				'field' => 'term_id',
				'terms' => $procedures,
			);
		}

		$args = array(
			'posts_per_page' => $limit,
			'post__not_in' => array( $post_id ),
			'tax_query' => $tax_query,
			'orderby' => 'rand',
		);

		$result = $this->get_galleries( $args );
		
		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		
		return $result['posts'];
	}

	/**
	 * Get gallery statistics
	 *
	 * @since 3.0.0
	 * @return array Gallery statistics.
	 */
	public function get_gallery_stats(): array {
		$cache_key = 'gallery_stats';
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		
		if ( $cached !== false ) {
			return $cached;
		}

		$post_counts = wp_count_posts( Gallery_Post_Type::POST_TYPE );
		$category_count = wp_count_terms( Gallery_Taxonomies::CATEGORY_TAXONOMY );
		$procedure_count = wp_count_terms( Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		$stats = array(
			'total_galleries' => $post_counts->publish,
			'draft_galleries' => $post_counts->draft,
			'total_categories' => is_wp_error( $category_count ) ? 0 : $category_count,
			'total_procedures' => is_wp_error( $procedure_count ) ? 0 : $procedure_count,
		);

		// Get additional stats
		$stats['recent_galleries'] = $this->get_recent_gallery_count( 7 ); // Last 7 days
		$stats['featured_galleries'] = $this->get_featured_gallery_count();
		$stats['most_popular_category'] = $this->get_most_popular_category();
		$stats['most_popular_procedure'] = $this->get_most_popular_procedure();

		wp_cache_set( $cache_key, $stats, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		
		return $stats;
	}

	/**
	 * Format post data for API response
	 *
	 * @since 3.0.0
	 * @param WP_Post $post WordPress post object.
	 * @param array   $args Formatting arguments.
	 * @return array Formatted post data.
	 */
	private function format_post_data( \WP_Post $post, array $args = array() ): array {
		$data = array(
			'id' => $post->ID,
			'title' => get_the_title( $post ),
			'slug' => $post->post_name,
			'excerpt' => get_the_excerpt( $post ),
			'content' => apply_filters( 'the_content', $post->post_content ),
			'date' => get_the_date( 'c', $post ),
			'modified' => get_the_modified_date( 'c', $post ),
			'status' => $post->post_status,
			'permalink' => get_permalink( $post ),
		);

		// Add featured image
		if ( has_post_thumbnail( $post ) ) {
			$data['featured_image'] = array(
				'id' => get_post_thumbnail_id( $post ),
				'url' => get_the_post_thumbnail_url( $post, 'large' ),
				'alt' => get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true ),
			);
		}

		// Add taxonomies
		$data['categories'] = $this->get_post_terms( $post->ID, Gallery_Taxonomies::CATEGORY_TAXONOMY );
		$data['procedures'] = $this->get_post_terms( $post->ID, Gallery_Taxonomies::PROCEDURE_TAXONOMY );

		// Add meta data if requested
		if ( $args['include_meta'] ?? true ) {
			$data['meta'] = $this->get_post_meta_data( $post->ID );
		}

		// Add images if requested
		if ( $args['include_images'] ?? true ) {
			$data['images'] = $this->get_post_images( $post->ID );
		}

		return $data;
	}

	/**
	 * Get post meta data
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID.
	 * @return array Meta data.
	 */
	private function get_post_meta_data( int $post_id ): array {
		$meta = array();

		// Patient information
		$patient_info = get_post_meta( $post_id, '_brag_patient_info', true );
		if ( $patient_info ) {
			$meta['patient'] = json_decode( $patient_info, true );
		}

		// Procedure details
		$procedure_details = get_post_meta( $post_id, '_brag_procedure_details', true );
		if ( $procedure_details ) {
			$meta['procedure_details'] = json_decode( $procedure_details, true );
		}

		// SEO data
		$seo_data = get_post_meta( $post_id, '_brag_seo_data', true );
		if ( $seo_data ) {
			$meta['seo'] = json_decode( $seo_data, true );
		}

		// Sync information
		$meta['case_id'] = get_post_meta( $post_id, '_brag_case_id', true );
		$meta['last_synced'] = get_post_meta( $post_id, '_brag_last_synced', true );

		return $meta;
	}

	/**
	 * Get post images
	 *
	 * @since 3.0.0
	 * @param int $post_id Post ID.
	 * @return array Image data.
	 */
	private function get_post_images( int $post_id ): array {
		$images = array(
			'before' => array(),
			'after' => array(),
		);

		// Get image attachment IDs
		$before_image_ids = get_post_meta( $post_id, '_brag_before_image_ids', true );
		$after_image_ids = get_post_meta( $post_id, '_brag_after_image_ids', true );

		// Process before images
		if ( $before_image_ids && is_array( $before_image_ids ) ) {
			foreach ( $before_image_ids as $attachment_id ) {
				$image_data = $this->get_image_data( $attachment_id );
				if ( $image_data ) {
					$images['before'][] = $image_data;
				}
			}
		}

		// Process after images
		if ( $after_image_ids && is_array( $after_image_ids ) ) {
			foreach ( $after_image_ids as $attachment_id ) {
				$image_data = $this->get_image_data( $attachment_id );
				if ( $image_data ) {
					$images['after'][] = $image_data;
				}
			}
		}

		// Fallback to meta data if no attachments found
		if ( empty( $images['before'] ) ) {
			$before_images_meta = get_post_meta( $post_id, '_brag_before_images', true );
			if ( $before_images_meta ) {
				$images['before'] = json_decode( $before_images_meta, true ) ?: array();
			}
		}

		if ( empty( $images['after'] ) ) {
			$after_images_meta = get_post_meta( $post_id, '_brag_after_images', true );
			if ( $after_images_meta ) {
				$images['after'] = json_decode( $after_images_meta, true ) ?: array();
			}
		}

		return $images;
	}

	/**
	 * Get image data for attachment
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array|null Image data or null if not found.
	 */
	private function get_image_data( int $attachment_id ): ?array {
		$attachment = get_post( $attachment_id );
		
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return null;
		}

		$image_data = array(
			'id' => $attachment_id,
			'url' => wp_get_attachment_image_url( $attachment_id, 'large' ),
			'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
			'full' => wp_get_attachment_image_url( $attachment_id, 'full' ),
			'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title' => $attachment->post_title,
			'caption' => $attachment->post_excerpt,
			'description' => $attachment->post_content,
		);

		// Get image metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( $metadata ) {
			$image_data['width'] = $metadata['width'] ?? 0;
			$image_data['height'] = $metadata['height'] ?? 0;
			$image_data['file_size'] = $metadata['filesize'] ?? 0;
		}

		return $image_data;
	}

	/**
	 * Get post terms
	 *
	 * @since 3.0.0
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array Terms data.
	 */
	private function get_post_terms( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$terms_data = array();
		
		foreach ( $terms as $term ) {
			$terms_data[] = array(
				'id' => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'description' => $term->description,
				'count' => $term->count,
				'link' => get_term_link( $term ),
			);
		}

		return $terms_data;
	}

	/**
	 * Get recent gallery count
	 *
	 * @since 3.0.0
	 * @param int $days Number of days to look back.
	 * @return int Gallery count.
	 */
	private function get_recent_gallery_count( int $days ): int {
		global $wpdb;

		$date_query = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			 WHERE post_type = %s 
			 AND post_status = 'publish' 
			 AND post_date >= %s",
			Gallery_Post_Type::POST_TYPE,
			$date_query
		) );
	}

	/**
	 * Get featured gallery count
	 *
	 * @since 3.0.0
	 * @return int Featured gallery count.
	 */
	private function get_featured_gallery_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) 
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = %s 
			 AND p.post_status = 'publish'
			 AND pm.meta_key = '_brag_featured'
			 AND pm.meta_value = '1'",
			Gallery_Post_Type::POST_TYPE
		) );
	}

	/**
	 * Get most popular category
	 *
	 * @since 3.0.0
	 * @return array|null Category data or null if none found.
	 */
	private function get_most_popular_category(): ?array {
		$terms = get_terms( array(
			'taxonomy' => Gallery_Taxonomies::CATEGORY_TAXONOMY,
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 1,
		) );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			return array(
				'id' => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'count' => $term->count,
			);
		}

		return null;
	}

	/**
	 * Get most popular procedure
	 *
	 * @since 3.0.0
	 * @return array|null Procedure data or null if none found.
	 */
	private function get_most_popular_procedure(): ?array {
		$terms = get_terms( array(
			'taxonomy' => Gallery_Taxonomies::PROCEDURE_TAXONOMY,
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 1,
		) );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$term = $terms[0];
			return array(
				'id' => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'count' => $term->count,
			);
		}

		return null;
	}

	/**
	 * Clear query cache
	 *
	 * @since 3.0.0
	 * @param string $key Optional specific cache key to clear.
	 * @return void
	 */
	public function clear_cache( string $key = '' ): void {
		if ( $key ) {
			wp_cache_delete( $key, self::CACHE_GROUP );
		} else {
			wp_cache_flush_group( self::CACHE_GROUP );
		}
	}

	/**
	 * Get cache stats
	 *
	 * @since 3.0.0
	 * @return array Cache statistics.
	 */
	public function get_cache_stats(): array {
		// This is a simplified version - actual implementation would depend on cache backend
		return array(
			'cache_group' => self::CACHE_GROUP,
			'cache_expiration' => self::CACHE_EXPIRATION,
			'cache_enabled' => wp_using_ext_object_cache(),
		);
	}
}