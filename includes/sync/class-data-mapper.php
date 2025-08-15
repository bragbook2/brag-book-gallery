<?php
/**
 * Data Mapper
 *
 * Maps API data to WordPress post and term structures.
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

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Mapper Class
 *
 * Handles the conversion of API data structures to WordPress data structures.
 *
 * @since 3.0.0
 */
class Data_Mapper {

	/**
	 * Map API case data to WordPress post data
	 *
	 * @since 3.0.0
	 * @param array $api_data API case data.
	 * @return array|null WordPress post data or null on failure.
	 */
	public function api_to_post( array $api_data ): ?array {
		if ( empty( $api_data['id'] ) ) {
			return null;
		}

		// Generate post title
		$title = $this->generate_post_title( $api_data );
		
		// Generate post content
		$content = $this->generate_post_content( $api_data );
		
		// Generate post excerpt
		$excerpt = $this->generate_post_excerpt( $api_data );
		
		// Generate post slug
		$slug = $this->generate_post_slug( $api_data );

		$post_data = array(
			'post_type' => Gallery_Post_Type::POST_TYPE,
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_name' => $slug,
			'post_status' => $this->determine_post_status( $api_data ),
			'post_author' => $this->get_default_author_id(),
			'meta_input' => $this->map_meta_fields( $api_data ),
		);

		return $post_data;
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
		$post_data = $this->api_to_post( $api_data );
		
		if ( ! $post_data ) {
			return null;
		}

		$post_data['ID'] = $post_id;
		
		// Remove meta_input for updates - we'll handle meta separately
		unset( $post_data['meta_input'] );

		return $post_data;
	}

	/**
	 * Map API category data to WordPress term data
	 *
	 * @since 3.0.0
	 * @param array $api_data API category data.
	 * @return array|null WordPress term data or null on failure.
	 */
	public function api_to_term( array $api_data ): ?array {
		if ( empty( $api_data['id'] ) || empty( $api_data['name'] ) ) {
			return null;
		}

		$term_data = array(
			'name' => sanitize_text_field( $api_data['name'] ),
			'slug' => sanitize_title( $api_data['slugName'] ?? $api_data['name'] ),
			'description' => sanitize_textarea_field( $api_data['description'] ?? '' ),
		);

		// Handle parent category
		if ( ! empty( $api_data['parentId'] ) ) {
			$parent_term = $this->find_term_by_api_id( 
				$api_data['parentId'], 
				'brag_category_api_id' 
			);
			
			if ( $parent_term ) {
				$term_data['parent'] = $parent_term->term_id;
			}
		}

		return $term_data;
	}

	/**
	 * Check if API data has changed compared to existing post
	 *
	 * @since 3.0.0
	 * @param array   $api_data API case data.
	 * @param WP_Post $post Existing WordPress post.
	 * @return bool True if update is needed.
	 */
	public function should_update( array $api_data, \WP_Post $post ): bool {
		// Generate new sync hash
		$new_hash = $this->generate_sync_hash( $api_data );
		
		// Get existing sync hash
		$existing_hash = get_post_meta( $post->ID, '_brag_sync_hash', true );
		
		return $new_hash !== $existing_hash;
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
			return sanitize_text_field( $api_data['title'] );
		}

		// Generate title from procedure names
		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			$procedure_names = array();
			
			foreach ( $api_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$procedure_names[] = $procedure['name'];
				}
			}
			
			if ( ! empty( $procedure_names ) ) {
				$title = implode( ' + ', $procedure_names );
				
				// Add patient info if available
				if ( ! empty( $api_data['patient']['age'] ) ) {
					$age = $api_data['patient']['age'];
					$gender = $api_data['patient']['gender'] ?? '';
					
					if ( $gender ) {
						$title .= " - {$age} Year Old " . ucfirst( $gender );
					} else {
						$title .= " - {$age} Years Old";
					}
				}
				
				return sanitize_text_field( $title );
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
		$content = '';

		// Add case details if available
		if ( ! empty( $api_data['details'] ) ) {
			$content .= wpautop( wp_kses_post( $api_data['details'] ) );
		}

		// Add procedure information
		if ( ! empty( $api_data['procedures'] ) && is_array( $api_data['procedures'] ) ) {
			$content .= "<h3>Procedures</h3>\n<ul>\n";
			
			foreach ( $api_data['procedures'] as $procedure ) {
				if ( ! empty( $procedure['name'] ) ) {
					$content .= '<li>' . esc_html( $procedure['name'] );
					
					if ( ! empty( $procedure['technique'] ) ) {
						$content .= ' (' . esc_html( $procedure['technique'] ) . ')';
					}
					
					$content .= "</li>\n";
				}
			}
			
			$content .= "</ul>\n";
		}

		// Add patient demographics if available
		if ( ! empty( $api_data['patient'] ) ) {
			$patient = $api_data['patient'];
			$demographics = array();

			if ( ! empty( $patient['age'] ) ) {
				$demographics[] = 'Age: ' . esc_html( $patient['age'] );
			}

			if ( ! empty( $patient['gender'] ) ) {
				$demographics[] = 'Gender: ' . esc_html( ucfirst( $patient['gender'] ) );
			}

			if ( ! empty( $patient['height'] ) ) {
				$demographics[] = 'Height: ' . esc_html( $patient['height'] );
			}

			if ( ! empty( $patient['weight'] ) ) {
				$demographics[] = 'Weight: ' . esc_html( $patient['weight'] );
			}

			if ( ! empty( $patient['ethnicity'] ) ) {
				$demographics[] = 'Ethnicity: ' . esc_html( $patient['ethnicity'] );
			}

			if ( ! empty( $demographics ) ) {
				$content .= "<h3>Patient Information</h3>\n";
				$content .= '<p>' . implode( ' | ', $demographics ) . "</p>\n";
			}
		}

		// Add timeline information if available
		if ( ! empty( $api_data['timeline'] ) ) {
			$content .= "<h3>Timeline</h3>\n";
			$content .= '<p>' . wp_kses_post( $api_data['timeline'] ) . "</p>\n";
		}

		return $content;
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
			return sanitize_text_field( $api_data['summary'] );
		}

		// Generate from details
		if ( ! empty( $api_data['details'] ) ) {
			$details = strip_tags( $api_data['details'] );
			return wp_trim_words( $details, 25 );
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
		// Use admin user as default
		$admin_users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
		
		if ( ! empty( $admin_users ) ) {
			return $admin_users[0]->ID;
		}

		// Fallback to user ID 1
		return 1;
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
	private function find_term_by_api_id( int $api_id, string $meta_key ): ?\WP_Term {
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
}