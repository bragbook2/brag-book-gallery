<?php
/**
 * Data Mapper Class - Enterprise-grade API data mapping system
 *
 * Comprehensive data mapping system for BRAGBook Gallery plugin.
 * Provides advanced API-to-WordPress data transformation with validation,
 * security measures, and intelligent mapping strategies.
 *
 * Features:
 * - Bidirectional data mapping (API to WordPress and vice versa)
 * - Intelligent content generation from API data
 * - Comprehensive data validation and sanitization
 * - Change detection and sync optimization
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Sync
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\PostTypes\Gallery_Post_Type;
use BRAGBookGallery\Includes\Extend\Cache_Manager;
use WP_Post;
use WP_Term;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise Data Mapping System
 *
 * Handles comprehensive data transformation between API and WordPress structures:
 *
 * Core Responsibilities:
 * - Transform API case data to WordPress post structures
 * - Map API categories to WordPress taxonomies
 * - Generate intelligent content from structured data
 * - Detect changes and optimize sync operations
 * - Ensure data integrity and security
 *
 * @since 3.0.0
 */
final class Data_Mapper {

	/**
	 * Cache duration constants for different data types
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;     // 5 minutes - for frequently changing data
	private const CACHE_TTL_MEDIUM = 1800;   // 30 minutes - for moderate change data
	private const CACHE_TTL_LONG = 3600;     // 1 hour - for stable data
	private const CACHE_TTL_EXTENDED = 7200; // 2 hours - for very stable data

	/**
	 * Maximum allowed string lengths for security
	 *
	 * @since 3.0.0
	 */
	private const MAX_TITLE_LENGTH = 200;
	private const MAX_SLUG_LENGTH = 200;
	private const MAX_EXCERPT_LENGTH = 500;
	private const MAX_CONTENT_LENGTH = 50000;

	/**
	 * Performance metrics storage
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $performance_metrics = [];

	/**
	 * Memory cache for frequently accessed data
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];

	/**
	 * Validation errors tracking
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $validation_errors = [];

	/**
	 * Map API case data to WordPress post data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return array|null WordPress post data or null on failure.
	 */
	public function api_to_post( array $api_data ): ?array {
		$start_time = microtime( true );

		try {
			// Validate required data
			if ( empty( $api_data['id'] ) ) {
				$this->log_validation_error( 'api_to_post', 'Missing required ID field' );
				return null;
			}

			// Sanitize and validate API data
			$api_data = $this->sanitize_api_data( $api_data );

			// Generate post components with caching
			$title = $this->get_cached_or_generate(
				"title_{$api_data['id']}",
				fn() => $this->generate_post_title( $api_data ),
				self::CACHE_TTL_MEDIUM
			);

			$content = $this->get_cached_or_generate(
				"content_{$api_data['id']}",
				fn() => $this->generate_post_content( $api_data ),
				self::CACHE_TTL_MEDIUM
			);

			$excerpt = $this->get_cached_or_generate(
				"excerpt_{$api_data['id']}",
				fn() => $this->generate_post_excerpt( $api_data ),
				self::CACHE_TTL_MEDIUM
			);

			$slug = $this->get_cached_or_generate(
				"slug_{$api_data['id']}",
				fn() => $this->generate_post_slug( $api_data ),
				self::CACHE_TTL_LONG
			);

			$post_data = [
				'post_type'    => Gallery_Post_Type::POST_TYPE,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_name'    => $slug,
				'post_status'  => $this->determine_post_status( $api_data ),
				'post_author'  => $this->get_default_author_id(),
				'meta_input'   => $this->map_meta_fields( $api_data ),
			];

			$this->track_performance( 'api_to_post', microtime( true ) - $start_time );
			return $post_data;

		} catch ( \Exception $e ) {
			$this->log_error( 'api_to_post', $e->getMessage() );
			return null;
		}
	}

	/**
	 * Map API data for post update
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @param int   $post_id Existing post ID.
	 * @return array|null WordPress post data or null on failure.
	 */
	public function api_to_post_update( array $api_data, int $post_id ): ?array {
		$start_time = microtime( true );

		try {
			// Validate post exists
			if ( ! get_post( $post_id ) ) {
				$this->log_validation_error( 'api_to_post_update', "Post ID {$post_id} does not exist" );
				return null;
			}

			$post_data = $this->api_to_post( $api_data );

			if ( ! $post_data ) {
				return null;
			}

			$post_data['ID'] = $post_id;

			// Remove meta_input for updates - we'll handle meta separately
			unset( $post_data['meta_input'] );

			$this->track_performance( 'api_to_post_update', microtime( true ) - $start_time );
			return $post_data;

		} catch ( \Exception $e ) {
			$this->log_error( 'api_to_post_update', $e->getMessage() );
			return null;
		}
	}

	/**
	 * Map API category data to WordPress term data
	 *
	 * @since 3.0.0
	 * @param array $api_data API category data.
	 * @return array|null WordPress term data or null on failure.
	 */
	public function api_to_term( array $api_data ): ?array {
		$start_time = microtime( true );

		try {
			// Validate required fields
			if ( empty( $api_data['id'] ) || empty( $api_data['name'] ) ) {
				$this->log_validation_error( 'api_to_term', 'Missing required ID or name field' );
				return null;
			}

			// Sanitize and validate term data
			$term_data = [
				'name'        => $this->sanitize_string( $api_data['name'], self::MAX_TITLE_LENGTH ),
				'slug'        => $this->sanitize_slug( $api_data['slugName'] ?? $api_data['name'], self::MAX_SLUG_LENGTH ),
				'description' => $this->sanitize_string( $api_data['description'] ?? '', self::MAX_EXCERPT_LENGTH ),
			];

			// Handle parent category with validation
			if ( ! empty( $api_data['parentId'] ) ) {
				$cache_key = "parent_term_{$api_data['parentId']}";

				$parent_term = $this->get_cached_or_generate(
					$cache_key,
					fn() => $this->find_term_by_api_id(
						$api_data['parentId'],
						'brag_category_api_id'
					),
					self::CACHE_TTL_LONG
				);

				if ( $parent_term ) {
					$term_data['parent'] = $parent_term->term_id;
				}
			}

			$this->track_performance( 'api_to_term', microtime( true ) - $start_time );
			return $term_data;

		} catch ( \Exception $e ) {
			$this->log_error( 'api_to_term', $e->getMessage() );
			return null;
		}
	}

	/**
	 * Check if API data has changed compared to existing post
	 *
	 * @since 3.0.0
	 * @param array   $api_data API case data.
	 * @param WP_Post $post Existing WordPress post.
	 * @return bool True if update is needed.
	 */
	public function should_update( array $api_data, WP_Post $post ): bool {
		try {
			// Validate inputs
			if ( empty( $api_data ) || ! $post instanceof WP_Post ) {
				return false;
			}

			// Generate new sync hash with caching
			$case_id = $api_data['id'] ?? 'unknown';
			$cache_key = "sync_hash_{$case_id}";
			$new_hash = $this->get_cached_or_generate(
				$cache_key,
				fn() => $this->generate_sync_hash( $api_data ),
				self::CACHE_TTL_SHORT
			);

			// Get existing sync hash with validation
			$existing_hash = get_post_meta( $post->ID, '_brag_sync_hash', true ) ?: '';

			// Track change detection
			$has_changed = $new_hash !== $existing_hash;

			if ( $has_changed ) {
				$this->log_debug( 'should_update', "Post {$post->ID} requires update" );
			}

			return $has_changed;

		} catch ( \Exception $e ) {
			$this->log_error( 'should_update', $e->getMessage() );
			return false;
		}
	}

	/**
	 * Generate post title from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return string Generated title.
	 */
	private function generate_post_title( array $api_data ): string {
		// Try to use provided title first
		if ( ! empty( $api_data['title'] ) ) {
			return $this->sanitize_string( $api_data['title'], self::MAX_TITLE_LENGTH );
		}

		// Generate title from procedure names
		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			$procedure_names = array_filter(
				array_map(
					fn( $procedure ) => $procedure['name'] ?? null,
					$api_data['procedures']
				)
			);

			if ( ! empty( $procedure_names ) ) {
				$title = implode( ' + ', $procedure_names );

				// Add patient info if available using match expression
				if ( ! empty( $api_data['patient']['age'] ) ) {
					$age = (int) $api_data['patient']['age'];
					$gender = strtolower( $api_data['patient']['gender'] ?? '' );

					$patient_info = match( $gender ) {
						'male'   => "{$age} Year Old Male",
						'female' => "{$age} Year Old Female",
						'other'  => "{$age} Year Old",
						default  => "{$age} Years Old",
					};

					$title .= " - {$patient_info}";
				}

				return $this->sanitize_string( $title, self::MAX_TITLE_LENGTH );
			}
		}

		// Fallback to case ID
		$case_id = $api_data['id'] ?? 'Unknown';
		return "Gallery Case #{$case_id}";
	}

	/**
	 * Generate post content from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return string Generated content.
	 */
	private function generate_post_content( array $api_data ): string {
		$content_parts = [];

		// Add case details if available
		if ( ! empty( $api_data['details'] ) ) {
			$sanitized_details = $this->sanitize_html_content( $api_data['details'] );
			$content_parts[] = wpautop( $sanitized_details );
		}

		// Add procedure information using modern array methods
		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			$procedure_items = array_filter(
				array_map(
					fn( $procedure ) => $this->format_procedure_item( $procedure ),
					$api_data['procedures']
				)
			);

			if ( ! empty( $procedure_items ) ) {
				$content_parts[] = sprintf(
					"<h3>%s</h3>\n<ul>\n%s</ul>\n",
					esc_html__( 'Procedures', 'brag-book-gallery' ),
					implode( "\n", $procedure_items )
				);
			}
		}

		// Add patient demographics if available
		if ( ! empty( $api_data['patient'] ) ) {
			$demographics = $this->format_patient_demographics( $api_data['patient'] );

			if ( ! empty( $demographics ) ) {
				$content_parts[] = sprintf(
					"<h3>%s</h3>\n<ul>\n%s</ul>\n",
					esc_html__( 'Patient Information', 'brag-book-gallery' ),
					implode( "\n", array_map( fn( $item ) => "<li>{$item}</li>", $demographics ) )
				);
			}
		}

		// Add timeline information if available
		if ( ! empty( $api_data['timeline'] ) ) {
			$content_parts[] = sprintf(
				"<h3>%s</h3>\n<p>%s</p>\n",
				esc_html__( 'Timeline', 'brag-book-gallery' ),
				wp_kses_post( $api_data['timeline'] )
			);
		}

		return implode( "\n\n", $content_parts );
	}

	/**
	 * Generate post excerpt from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return string Generated excerpt.
	 */
	private function generate_post_excerpt( array $api_data ): string {
		// Use provided summary if available
		if ( ! empty( $api_data['summary'] ) ) {
			return $this->sanitize_string( $api_data['summary'], self::MAX_EXCERPT_LENGTH );
		}

		// Generate from details
		if ( ! empty( $api_data['details'] ) ) {
			$details = wp_strip_all_tags( $api_data['details'] );
			return wp_trim_words( $details, 25, '...' );
		}

		// Generate from procedure names and patient info
		$excerpt_parts = array();

		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			$procedure_names = array();
			foreach ( $api_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$procedure_names[] = $procedure['name'];
				}
			}

			if ( ! empty( $procedure_names ) ) {
				$excerpt_parts[] = implode( ' and ', $procedure_names );
			}
		}

		if ( ! empty( $api_data['patient']['age'] ) ) {
			$age = $api_data['patient']['age'];
			$gender = $api_data['patient']['gender'] ?? 'patient';
			$excerpt_parts[] = "{$age}-year-old {$gender}";
		}

		if ( ! empty( $excerpt_parts ) ) {
			return implode( ' for ', $excerpt_parts );
		}

		return '';
	}

	/**
	 * Generate post slug from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return string Generated slug.
	 */
	private function generate_post_slug( array $api_data ): string {
		$slug_parts = array();

		// Add procedure names
		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			foreach ( $api_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['slugName'] ) ) {
					$slug_parts[] = $procedure['slugName'];
				} elseif ( ! empty( $procedure['name'] ) ) {
					$slug_parts[] = sanitize_title( $procedure['name'] );
				}
			}
		}

		// Add case ID
		if ( ! empty( $api_data['id'] ) ) {
			$slug_parts[] = 'case-' . $api_data['id'];
		}

		$slug = implode( '-', $slug_parts );

		// Ensure slug is not empty
		if ( empty( $slug ) ) {
			$slug = 'gallery-case-' . ( $api_data['id'] ?? uniqid() );
		}

		return sanitize_title( $slug );
	}

	/**
	 * Determine post status from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return string Post status.
	 */
	private function determine_post_status( array $api_data ): string {
		// Check if case is published in API
		if ( isset( $api_data['published'] ) ) {
			return $api_data['published'] ? 'publish' : 'draft';
		}

		// Check status field
		if ( ! empty( $api_data['status'] ) ) {
			$status_mapping = array(
				'published' => 'publish',
				'active' => 'publish',
				'visible' => 'publish',
				'draft' => 'draft',
				'hidden' => 'draft',
				'private' => 'private',
			);

			$api_status = strtolower( $api_data['status'] );

			if ( isset( $status_mapping[ $api_status ] ) ) {
				return $status_mapping[ $api_status ];
			}
		}

		// Default to published
		return 'publish';
	}

	/**
	 * Get default author ID
	 *
	 * @since 3.0.0
	 * @return int Default author ID.
	 */
	private function get_default_author_id(): int {
		// Check cached value first
		return (int) $this->get_cached_or_generate(
			'default_author_id',
			function() {
				// Try to get first admin user
				$admins = get_users( [
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
					'order'   => 'ASC',
				] );

				return ! empty( $admins ) ? $admins[0]->ID : 1;
			},
			self::CACHE_TTL_EXTENDED
		);
	}

	/**
	 * Map meta fields from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return array Mapped meta fields.
	 */
	private function map_meta_fields( array $api_data ): array {
		$meta_fields = array(
			'_brag_case_id' => $api_data['id'] ?? 0,
			'_brag_api_token' => get_option( 'brag_book_gallery_api_token', '' ),
			'_brag_property_id' => get_option( 'brag_book_gallery_property_id', 0 ),
			'_brag_last_synced' => current_time( 'mysql' ),
			'_brag_sync_hash' => $this->generate_sync_hash( $api_data ),
		);

		// Map patient information
		if ( ! empty( $api_data['patient'] ) ) {
			$meta_fields['_brag_patient_info'] = wp_json_encode( $api_data['patient'] );
		}

		// Map procedure details
		if ( ! empty( $api_data['procedures'] ) ) {
			$procedure_details = array();

			foreach ( $api_data['procedures'] as $procedure ) {
				$procedure_details[] = array(
					'id' => $procedure['id'] ?? 0,
					'name' => $procedure['name'] ?? '',
					'technique' => $procedure['technique'] ?? '',
					'timeframe' => $procedure['timeframe'] ?? '',
					'description' => $procedure['description'] ?? '',
				);
			}

			$meta_fields['_brag_procedure_details'] = wp_json_encode( $procedure_details );
		}

		// Map SEO data
		if ( ! empty( $api_data['seo'] ) ) {
			$meta_fields['_brag_seo_data'] = wp_json_encode( $api_data['seo'] );
		}

		// Map image data
		if ( ! empty( $api_data['photoSets'] ) ) {
			$before_images = array();
			$after_images = array();

			foreach ( $api_data['photoSets'] as $photo_set ) {
				$photos = $photo_set['photos'] ?? array();

				if ( $photo_set['type'] === 'before' ) {
					$before_images = $this->map_image_data( $photos );
				} elseif ( $photo_set['type'] === 'after' ) {
					$after_images = $this->map_image_data( $photos );
				}
			}

			$meta_fields['_brag_before_images'] = wp_json_encode( $before_images );
			$meta_fields['_brag_after_images'] = wp_json_encode( $after_images );
		}

		return $meta_fields;
	}

	/**
	 * Map image data from API
	 *
	 * @since 3.0.0
	 * @param array $photos Photos array from API.
	 * @return array Mapped image data.
	 */
	private function map_image_data( array $photos ): array {
		$images = array();

		foreach ( $photos as $photo ) {
			if ( empty( $photo['url'] ) ) {
				continue;
			}

			$image_data = array(
				'url' => esc_url_raw( $photo['url'] ),
				'alt' => sanitize_text_field( $photo['alt'] ?? '' ),
				'caption' => sanitize_text_field( $photo['caption'] ?? '' ),
				'title' => sanitize_text_field( $photo['title'] ?? '' ),
				'description' => sanitize_textarea_field( $photo['description'] ?? '' ),
			);

			// Add dimension information if available
			if ( ! empty( $photo['width'] ) ) {
				$image_data['width'] = absint( $photo['width'] );
			}

			if ( ! empty( $photo['height'] ) ) {
				$image_data['height'] = absint( $photo['height'] );
			}

			// Add file size if available
			if ( ! empty( $photo['fileSize'] ) ) {
				$image_data['file_size'] = absint( $photo['fileSize'] );
			}

			$images[] = $image_data;
		}

		return $images;
	}

	/**
	 * Generate sync hash for change detection
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return string MD5 hash of relevant data.
	 */
	private function generate_sync_hash( array $api_data ): string {
		// Create hash based on key fields that matter for sync
		$hash_data = array(
			'id' => $api_data['id'] ?? '',
			'title' => $api_data['title'] ?? '',
			'details' => $api_data['details'] ?? '',
			'status' => $api_data['status'] ?? '',
			'published' => $api_data['published'] ?? '',
			'procedures' => $api_data['procedures'] ?? array(),
			'patient' => $api_data['patient'] ?? array(),
			'photoSets' => $api_data['photoSets'] ?? array(),
			'seo' => $api_data['seo'] ?? array(),
		);

		return md5( serialize( $hash_data ) );
	}

	/**
	 * Find term by API ID
	 *
	 * @since 3.0.0
	 * @param int    $api_id API ID to search for.
	 * @param string $meta_key Meta key containing the API ID.
	 * @return WP_Term|null Found term or null.
	 */
	private function find_term_by_api_id( int $api_id, string $meta_key ): ?WP_Term {
		$terms = get_terms( array(
			'taxonomy' => array( 'brag_category', 'brag_procedure' ),
			'meta_key' => $meta_key,
			'meta_value' => $api_id,
			'hide_empty' => false,
			'number' => 1,
		) );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[0];
		}

		return null;
	}

	/**
	 * Sanitize HTML content while preserving structure
	 *
	 * @since 3.0.0
	 * @param string $content Content to sanitize.
	 * @return string Sanitized content.
	 */
	private function sanitize_html_content( string $content ): string {
		$allowed_html = array(
			'h1' => array(),
			'h2' => array(),
			'h3' => array(),
			'h4' => array(),
			'h5' => array(),
			'h6' => array(),
			'p' => array( 'class' => array(), 'id' => array() ),
			'br' => array(),
			'strong' => array(),
			'b' => array(),
			'em' => array(),
			'i' => array(),
			'u' => array(),
			'ul' => array( 'class' => array() ),
			'ol' => array( 'class' => array() ),
			'li' => array( 'class' => array() ),
			'a' => array(
				'href' => array(),
				'title' => array(),
				'class' => array(),
				'target' => array(),
				'rel' => array()
			),
			'blockquote' => array( 'cite' => array() ),
			'div' => array( 'class' => array(), 'id' => array() ),
			'span' => array( 'class' => array(), 'id' => array() ),
		);

		return wp_kses( $content, $allowed_html );
	}

	/**
	 * Extract procedure IDs from API data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return array Array of procedure IDs.
	 */
	public function extract_procedure_ids( array $api_data ): array {
		$procedure_ids = array();

		// Check direct procedureIds field
		if ( ! empty( $api_data['procedureIds'] ) && is_array( $api_data['procedureIds'] ) ) {
			return array_map( 'absint', $api_data['procedureIds'] );
		}

		// Extract from procedures array
		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			foreach ( $api_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['id'] ) ) {
					$procedure_ids[] = absint( $procedure['id'] );
				}
			}
		}

		return array_unique( $procedure_ids );
	}

	/**
	 * Validate API data structure
	 *
	 * @since 3.0.0
	 * @param array $api_data API data to validate.
	 * @return bool True if valid structure.
	 */
	public function validate_api_data( array $api_data ): bool {
		// Check required fields
		if ( empty( $api_data['id'] ) ) {
			return false;
		}

		// Validate ID is numeric
		if ( ! is_numeric( $api_data['id'] ) ) {
			return false;
		}

		// Check for basic structure
		if ( isset( $api_data['photoSets'] ) && ! is_array( $api_data['photoSets'] ) ) {
			return false;
		}

		if ( isset( $api_data['procedures'] ) && ! is_array( $api_data['procedures'] ) ) {
			return false;
		}

		if ( isset( $api_data['patient'] ) && ! is_array( $api_data['patient'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize string with length limit
	 *
	 * @since 3.0.0
	 * @param string $string String to sanitize.
	 * @param int    $max_length Maximum allowed length.
	 * @return string Sanitized string.
	 */
	private function sanitize_string( string $string, int $max_length ): string {
		$sanitized = sanitize_text_field( $string );
		return mb_substr( $sanitized, 0, $max_length );
	}

	/**
	 * Sanitize slug with length limit
	 *
	 * @since 3.0.0
	 * @param string $slug Slug to sanitize.
	 * @param int    $max_length Maximum allowed length.
	 * @return string Sanitized slug.
	 */
	private function sanitize_slug( string $slug, int $max_length ): string {
		$sanitized = sanitize_title( $slug );
		return mb_substr( $sanitized, 0, $max_length );
	}

	/**
	 * Sanitize API data comprehensively
	 *
	 * @since 3.0.0
	 * @param array $api_data Data to sanitize.
	 * @return array Sanitized data.
	 */
	private function sanitize_api_data( array $api_data ): array {
		$sanitized = [];

		foreach ( $api_data as $key => $value ) {
			$sanitized[ $key ] = match ( true ) {
				is_array( $value )  => $this->sanitize_api_data( $value ),
				is_string( $value ) => sanitize_text_field( $value ),
				is_int( $value )    => absint( $value ),
				is_bool( $value )   => (bool) $value,
				default             => $value,
			};
		}

		return $sanitized;
	}

	/**
	 * Get cached data or generate new
	 *
	 * @since 3.0.0
	 * @param string   $cache_key Cache key.
	 * @param callable $generator Function to generate data.
	 * @param int      $ttl Cache TTL in seconds.
	 * @return mixed Cached or generated data.
	 */
	private function get_cached_or_generate( string $cache_key, callable $generator, int $ttl = self::CACHE_TTL_MEDIUM ): mixed {
		// Check memory cache first
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}

		// Check WordPress transient cache
		$transient_key = 'mapper_' . $cache_key;
		$cached = Cache_Manager::get( $transient_key );

		if ( false !== $cached ) {
			$this->memory_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Generate new data
		$data = $generator();

		// Store in both caches
		$this->memory_cache[ $cache_key ] = $data;
		Cache_Manager::set( $transient_key, $data, $ttl );

		return $data;
	}

	/**
	 * Format procedure item for display
	 *
	 * @since 3.0.0
	 * @param array $procedure Procedure data.
	 * @return string|null Formatted procedure HTML or null.
	 */
	private function format_procedure_item( array $procedure ): ?string {
		if ( empty( $procedure['name'] ) ) {
			return null;
		}

		$item = '<li>' . esc_html( $procedure['name'] );

		if ( ! empty( $procedure['technique'] ) ) {
			$item .= ' <em>(' . esc_html( $procedure['technique'] ) . ')</em>';
		}

		if ( ! empty( $procedure['description'] ) ) {
			$item .= '<br><small>' . esc_html( $procedure['description'] ) . '</small>';
		}

		$item .= '</li>';

		return $item;
	}

	/**
	 * Format patient demographics for display
	 *
	 * @since 3.0.0
	 * @param array $patient Patient data.
	 * @return array Formatted demographics.
	 */
	private function format_patient_demographics( array $patient ): array {
		$demographics = [];

		// Age and Gender
		if ( ! empty( $patient['age'] ) ) {
			$age_text = sprintf(
				/* translators: %d: Patient age */
				__( 'Age: %d years', 'brag-book-gallery' ),
				(int) $patient['age']
			);
			$demographics[] = esc_html( $age_text );
		}

		if ( ! empty( $patient['gender'] ) ) {
			$gender = ucfirst( strtolower( $patient['gender'] ) );
			$demographics[] = sprintf(
				/* translators: %s: Patient gender */
				__( 'Gender: %s', 'brag-book-gallery' ),
				esc_html( $gender )
			);
		}

		// Physical attributes
		if ( ! empty( $patient['height'] ) ) {
			$demographics[] = sprintf(
				/* translators: %s: Patient height */
				__( 'Height: %s', 'brag-book-gallery' ),
				esc_html( $patient['height'] )
			);
		}

		if ( ! empty( $patient['weight'] ) ) {
			$demographics[] = sprintf(
				/* translators: %s: Patient weight */
				__( 'Weight: %s', 'brag-book-gallery' ),
				esc_html( $patient['weight'] )
			);
		}

		if ( ! empty( $patient['ethnicity'] ) ) {
			$demographics[] = sprintf(
				/* translators: %s: Patient ethnicity */
				__( 'Ethnicity: %s', 'brag-book-gallery' ),
				esc_html( $patient['ethnicity'] )
			);
		}

		return $demographics;
	}

	/**
	 * Log validation error
	 *
	 * @since 3.0.0
	 * @param string $context Error context.
	 * @param string $message Error message.
	 */
	private function log_validation_error( string $context, string $message ): void {
		$this->validation_errors[ $context ][] = [
			'time'    => current_time( 'mysql' ),
			'message' => $message,
		];

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[BRAGBook Data Mapper] Validation Error in {$context}: {$message}" );
		}
	}

	/**
	 * Log general error
	 *
	 * @since 3.0.0
	 * @param string $context Error context.
	 * @param string $message Error message.
	 */
	private function log_error( string $context, string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[BRAGBook Data Mapper] Error in {$context}: {$message}" );
		}
	}

	/**
	 * Log debug information
	 *
	 * @since 3.0.0
	 * @param string $context Debug context.
	 * @param string $message Debug message.
	 */
	private function log_debug( string $context, string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( "[BRAGBook Data Mapper] Debug in {$context}: {$message}" );
		}
	}

	/**
	 * Track performance metrics
	 *
	 * @since 3.0.0
	 * @param string $operation Operation name.
	 * @param float  $duration Operation duration in seconds.
	 */
	private function track_performance( string $operation, float $duration ): void {
		if ( ! isset( $this->performance_metrics[ $operation ] ) ) {
			$this->performance_metrics[ $operation ] = [
				'count'    => 0,
				'total'    => 0,
				'min'      => PHP_FLOAT_MAX,
				'max'      => 0,
			];
		}

		$metrics = &$this->performance_metrics[ $operation ];
		$metrics['count']++;
		$metrics['total'] += $duration;
		$metrics['min'] = min( $metrics['min'], $duration );
		$metrics['max'] = max( $metrics['max'], $duration );
		$metrics['average'] = $metrics['total'] / $metrics['count'];
	}

	/**
	 * Sanitize patient data
	 *
	 * @since 3.0.0
	 * @param array<string, mixed> $patient_data Patient data from API.
	 * @return array<string, mixed> Sanitized patient data.
	 */
	private function sanitize_patient_data( array $patient_data ): array {
		$sanitized = [];

		// Sanitize each field appropriately
		if ( isset( $patient_data['age'] ) ) {
			$sanitized['age'] = absint( $patient_data['age'] );
		}

		if ( isset( $patient_data['gender'] ) ) {
			$sanitized['gender'] = sanitize_text_field( $patient_data['gender'] );
		}

		if ( isset( $patient_data['height'] ) ) {
			$sanitized['height'] = sanitize_text_field( $patient_data['height'] );
		}

		if ( isset( $patient_data['weight'] ) ) {
			$sanitized['weight'] = sanitize_text_field( $patient_data['weight'] );
		}

		if ( isset( $patient_data['ethnicity'] ) ) {
			$sanitized['ethnicity'] = sanitize_text_field( $patient_data['ethnicity'] );
		}

		if ( isset( $patient_data['bmi'] ) ) {
			$sanitized['bmi'] = (float) $patient_data['bmi'];
		}

		// Add any additional custom fields
		foreach ( $patient_data as $key => $value ) {
			if ( ! isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}

		return $sanitized;
	}
}
