<?php
/**
 * Image Sync - Enterprise-grade image management system
 *
 * Comprehensive image synchronization and optimization system for BRAGBook Gallery.
 * Handles secure importing, validation, optimization, and management of images
 * from external APIs to WordPress media library.
 *
 * Features:
 * - Secure image downloading with validation
 * - Automatic image optimization and compression
 * - Duplicate detection and prevention
 * - Batch processing capabilities
 * - Performance monitoring and caching
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

use WP_Error;
use Exception;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enterprise Image Synchronization System
 *
 * Handles comprehensive image management operations:
 *
 * Core Responsibilities:
 * - Import and validate images from external sources
 * - Optimize images for web performance
 * - Manage attachment metadata and associations
 * - Detect and prevent duplicate imports
 * - Batch process image operations
 * - Monitor and report image statistics
 *
 * @since 3.0.0
 */
final class Image_Sync {

	/**
	 * Maximum image file size (in bytes)
	 *
	 * @since 3.0.0
	 */
	private const MAX_FILE_SIZE = 10485760; // 10MB

	/**
	 * Allowed image MIME types for security
	 *
	 * @since 3.0.0
	 */
	private const ALLOWED_TYPES = [
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/gif',
	];

	/**
	 * Image quality for compression
	 *
	 * @since 3.0.0
	 */
	private const COMPRESSION_QUALITY = 85;

	/**
	 * Cache duration constants
	 *
	 * @since 3.0.0
	 */
	private const CACHE_TTL_SHORT = 300;     // 5 minutes
	private const CACHE_TTL_MEDIUM = 1800;   // 30 minutes
	private const CACHE_TTL_LONG = 3600;     // 1 hour
	private const CACHE_TTL_EXTENDED = 7200; // 2 hours

	/**
	 * Maximum retries for failed downloads
	 *
	 * @since 3.0.0
	 */
	private const MAX_DOWNLOAD_RETRIES = 3;

	/**
	 * Download timeout in seconds
	 *
	 * @since 3.0.0
	 */
	private const DOWNLOAD_TIMEOUT = 30;

	/**
	 * Performance metrics storage
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $performance_metrics = [];

	/**
	 * Memory cache for optimization
	 *
	 * @since 3.0.0
	 * @var array<string, mixed>
	 */
	private array $memory_cache = [];

	/**
	 * Error tracking for reporting
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	private array $error_log = [];

	/**
	 * Import images for a case with comprehensive validation
	 *
	 * Processes and imports all images associated with a case from API data.
	 * Handles before/after image sets with duplicate detection and optimization.
	 *
	 * @since 3.0.0
	 * @param int                  $post_id WordPress post ID.
	 * @param array<string, mixed> $case_data Case data from API.
	 * @return array<string, mixed> Result with success status and attachment IDs.
	 */
	public function import_case_images( int $post_id, array $case_data ): array {
		$start_time = microtime( true );
		
		$results = [
			'success'       => true,
			'before_images' => [],
			'after_images'  => [],
			'skipped'       => 0,
			'errors'        => [],
			'stats'         => [],
		];

		try {
			// Validate post exists
			if ( ! get_post( $post_id ) ) {
				throw new Exception( "Invalid post ID: {$post_id}" );
			}

			if ( empty( $case_data['photoSets'] ) || ! is_array( $case_data['photoSets'] ) ) {
				return $results;
			}

			foreach ( $case_data['photoSets'] as $photo_set ) {
				if ( empty( $photo_set['type'] ) || empty( $photo_set['photos'] ) ) {
					continue;
				}

				$set_type = $this->validate_image_type( $photo_set['type'] );
				$photos   = $photo_set['photos'];

				foreach ( $photos as $photo ) {
					try {
						$attachment_id = $this->import_single_image( $photo, $post_id, $set_type );

						if ( $attachment_id ) {
							$result_key = match ( $set_type ) {
								'before' => 'before_images',
								'after'  => 'after_images',
								default  => null,
							};
							
							if ( $result_key ) {
								$results[ $result_key ][] = $attachment_id;
							}
						} else {
							$results['skipped']++;
						}
					} catch ( Exception $e ) {
						$this->log_error( 'import_single', $e->getMessage() );
						$results['errors'][] = $this->format_error_message( $photo, $e->getMessage() );
						$results['success']  = false;
					}
				}
			}

			// Update post meta with attachment IDs
			if ( ! empty( $results['before_images'] ) ) {
				update_post_meta( $post_id, '_brag_before_image_ids', $results['before_images'] );
			}

			if ( ! empty( $results['after_images'] ) ) {
				update_post_meta( $post_id, '_brag_after_image_ids', $results['after_images'] );
			}

			// Track performance
			$duration = microtime( true ) - $start_time;
			$this->track_performance( 'import_case_images', $duration );
			
			$results['stats'] = [
				'duration_seconds' => round( $duration, 2 ),
				'total_processed'  => count( $results['before_images'] ) + count( $results['after_images'] ),
				'memory_peak_mb'   => round( memory_get_peak_usage( true ) / 1048576, 2 ),
			];

		} catch ( Exception $e ) {
			$this->log_error( 'import_case_images', $e->getMessage() );
			$results['success'] = false;
			$results['errors'][] = $e->getMessage();
		}

		return $results;
	}

	/**
	 * Import multiple images from URLs with batch processing
	 *
	 * @since 3.0.0
	 * @param array<string> $image_urls Array of image URLs.
	 * @param int           $post_id Optional post ID to attach images to.
	 * @return array<int> Array of attachment IDs.
	 */
	public function import_images( array $image_urls, int $post_id = 0 ): array {
		$attachment_ids = [];
		$start_time = microtime( true );

		foreach ( $image_urls as $url ) {
			if ( empty( $url ) || ! is_string( $url ) ) {
				continue;
			}

			try {
				// Check cache first
				$cache_key = $this->get_url_cache_key( $url );
				$cached_id = $this->get_cached_attachment_id( $cache_key );
				
				if ( $cached_id ) {
					$attachment_ids[] = $cached_id;
					continue;
				}

				$attachment_id = $this->sideload_image( $url, $post_id );

				if ( $attachment_id ) {
					$attachment_ids[] = $attachment_id;
					$this->cache_attachment_id( $cache_key, $attachment_id );
				}
			} catch ( Exception $e ) {
				$this->log_error( 'import_images', "Failed to import {$url}: {$e->getMessage()}" );
			}
		}

		$this->track_performance( 'import_images_batch', microtime( true ) - $start_time );
		return $attachment_ids;
	}

	/**
	 * Import and sideload single image with validation
	 *
	 * @since 3.0.0
	 * @param mixed  $image_data Image data (URL string or array with metadata).
	 * @param int    $post_id Post ID to attach image to.
	 * @param string $image_type Type of image (before/after).
	 * @return int|null Attachment ID or null on failure.
	 * @throws Exception If image import fails.
	 */
	private function import_single_image( mixed $image_data, int $post_id, string $image_type = '' ): ?int {
		// Extract URL from image data
		$url = is_array( $image_data ) ? ( $image_data['url'] ?? '' ) : $image_data;

		if ( empty( $url ) ) {
			return null;
		}

		// Validate and sanitize URL
		$url = $this->sanitize_url( $url );
		if ( ! $url ) {
			return null;
		}

		// Check if image already exists by URL
		$existing_id = $this->find_existing_attachment_by_url( $url );
		if ( $existing_id ) {
			// Attach to post if not already attached
			$this->ensure_attachment_association( $existing_id, $post_id );
			return $existing_id;
		}

		// Sideload the image with retries
		$attachment_id = $this->sideload_image_with_retries( $url, $post_id );

		if ( ! $attachment_id ) {
			return null;
		}

		// Add metadata if available
		if ( is_array( $image_data ) ) {
			$this->update_attachment_metadata( $attachment_id, $image_data, $image_type );
		}

		// Track successful import
		$this->track_import_success( $attachment_id, $url );

		return $attachment_id;
	}

	/**
	 * Sideload image from URL with security validation
	 *
	 * @since 3.0.0
	 * @param string $url Image URL.
	 * @param int    $post_id Optional post ID to attach to.
	 * @return int|null Attachment ID or null on failure.
	 * @throws Exception If sideload fails.
	 */
	public function sideload_image( string $url, int $post_id = 0 ): ?int {
		$start_time = microtime( true );
		
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		try {
			// Validate URL
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				throw new Exception( "Invalid image URL: {$url}" );
			}

			// Security check for allowed domains
			if ( ! $this->is_allowed_domain( $url ) ) {
				throw new Exception( "URL from unauthorized domain: {$url}" );
			}

			// Check if image is accessible
			if ( ! $this->is_image_accessible( $url ) ) {
				throw new Exception( "Image not accessible: {$url}" );
			}

			// Get temporary file with timeout
			$tmp = download_url( $url, self::DOWNLOAD_TIMEOUT );

			if ( is_wp_error( $tmp ) ) {
				throw new Exception( "Failed to download image: " . $tmp->get_error_message() );
			}

			// Validate image file
			if ( ! $this->validate_image_file( $tmp ) ) {
				@unlink( $tmp );
				throw new Exception( "Invalid image file or security violation" );
			}

			// Prepare file array
			$file_array = [
				'name'     => $this->generate_filename_from_url( $url ),
				'tmp_name' => $tmp,
			];

			// Handle the sideload
			$attachment_id = media_handle_sideload( $file_array, $post_id );

			// Clean up temp file
			if ( file_exists( $tmp ) ) {
				@unlink( $tmp );
			}

			if ( is_wp_error( $attachment_id ) ) {
				throw new Exception( "Failed to sideload image: " . $attachment_id->get_error_message() );
			}

			// Store original URL as meta
			update_post_meta( $attachment_id, '_brag_original_url', esc_url_raw( $url ) );
			update_post_meta( $attachment_id, '_brag_imported_date', current_time( 'mysql' ) );
			update_post_meta( $attachment_id, '_brag_import_source', 'api' );

			// Optimize image
			$this->optimize_image( $attachment_id );

			$this->track_performance( 'sideload_image', microtime( true ) - $start_time );
			return $attachment_id;
			
		} catch ( Exception $e ) {
			$this->log_error( 'sideload_image', $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Optimize imported image
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool Success status.
	 */
	public function optimize_image( int $attachment_id ): bool {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		$image_info = getimagesize( $file_path );

		if ( ! $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];
		$width = $image_info[0];
		$height = $image_info[1];

		// Skip optimization for small images
		if ( $width <= 800 && $height <= 800 ) {
			return true;
		}

		// Load image editor
		$image = wp_get_image_editor( $file_path );

		if ( is_wp_error( $image ) ) {
			return false;
		}

		// Set quality
		$image->set_quality( self::COMPRESSION_QUALITY );

		// Resize if too large
		$max_width = apply_filters( 'brag_book_gallery_max_image_width', 2048 );
		$max_height = apply_filters( 'brag_book_gallery_max_image_height', 2048 );

		if ( $width > $max_width || $height > $max_height ) {
			$image->resize( $max_width, $max_height, false );
		}

		// Save optimized image
		$saved = $image->save( $file_path );

		if ( is_wp_error( $saved ) ) {
			return false;
		}

		// Regenerate metadata
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return true;
	}

	/**
	 * Update attachment metadata from API data
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $image_data Image data from API.
	 * @param string $image_type Image type (before/after).
	 * @return void
	 */
	private function update_attachment_metadata( int $attachment_id, array $image_data, string $image_type = '' ): void {
		$attachment_data = array( 'ID' => $attachment_id );

		// Update alt text
		if ( ! empty( $image_data['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $image_data['alt'] ) );
		}

		// Update title
		if ( ! empty( $image_data['title'] ) ) {
			$attachment_data['post_title'] = sanitize_text_field( $image_data['title'] );
		}

		// Update caption
		if ( ! empty( $image_data['caption'] ) ) {
			$attachment_data['post_excerpt'] = sanitize_text_field( $image_data['caption'] );
		}

		// Update description
		if ( ! empty( $image_data['description'] ) ) {
			$attachment_data['post_content'] = sanitize_textarea_field( $image_data['description'] );
		}

		// Update attachment post if we have data to update
		if ( count( $attachment_data ) > 1 ) {
			wp_update_post( $attachment_data );
		}

		// Add custom meta
		update_post_meta( $attachment_id, '_brag_image_type', sanitize_text_field( $image_type ) );

		if ( ! empty( $image_data['width'] ) ) {
			update_post_meta( $attachment_id, '_brag_original_width', absint( $image_data['width'] ) );
		}

		if ( ! empty( $image_data['height'] ) ) {
			update_post_meta( $attachment_id, '_brag_original_height', absint( $image_data['height'] ) );
		}
	}

	/**
	 * Find existing attachment by original URL
	 *
	 * @since 3.0.0
	 * @param string $url Original image URL.
	 * @return int|null Attachment ID or null if not found.
	 */
	private function find_existing_attachment_by_url( string $url ): ?int {
		global $wpdb;

		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_brag_original_url'
			 AND meta_value = %s
			 LIMIT 1",
			$url
		) );

		return $attachment_id ? (int) $attachment_id : null;
	}

	/**
	 * Ensure attachment is associated with post
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function ensure_attachment_association( int $attachment_id, int $post_id ): void {
		$current_parent = wp_get_post_parent_id( $attachment_id );

		if ( $current_parent !== $post_id && $post_id > 0 ) {
			wp_update_post( array(
				'ID' => $attachment_id,
				'post_parent' => $post_id,
			) );
		}
	}

	/**
	 * Check if image URL is accessible
	 *
	 * @since 3.0.0
	 * @param string $url Image URL.
	 * @return bool True if accessible.
	 */
	private function is_image_accessible( string $url ): bool {
		$response = wp_remote_head( $url, array(
			'timeout' => 15,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		return $response_code === 200 && in_array( $content_type, self::ALLOWED_TYPES, true );
	}

	/**
	 * Validate image file
	 *
	 * @since 3.0.0
	 * @param string $file_path Path to image file.
	 * @return bool True if valid.
	 */
	private function validate_image_file( string $file_path ): bool {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		// Check file size
		$file_size = filesize( $file_path );
		if ( $file_size > self::MAX_FILE_SIZE ) {
			return false;
		}

		// Check if it's actually an image
		$image_info = getimagesize( $file_path );
		if ( ! $image_info ) {
			return false;
		}

		// Check MIME type
		$mime_type = $image_info['mime'];
		if ( ! in_array( $mime_type, self::ALLOWED_TYPES, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate filename from URL
	 *
	 * @since 3.0.0
	 * @param string $url Image URL.
	 * @return string Generated filename.
	 */
	private function generate_filename_from_url( string $url ): string {
		$path = parse_url( $url, PHP_URL_PATH );
		$filename = basename( $path );

		// If no proper filename, generate one
		if ( empty( $filename ) || strpos( $filename, '.' ) === false ) {
			$extension = $this->get_extension_from_url( $url );
			$filename = 'brag-gallery-' . uniqid() . ( $extension ? ".{$extension}" : '.jpg' );
		}

		// Sanitize filename
		$filename = sanitize_file_name( $filename );

		// Ensure we have a valid extension
		$path_info = pathinfo( $filename );
		if ( empty( $path_info['extension'] ) ) {
			$filename .= '.jpg';
		}

		return $filename;
	}

	/**
	 * Get file extension from URL or content type
	 *
	 * @since 3.0.0
	 * @param string $url Image URL.
	 * @return string File extension.
	 */
	private function get_extension_from_url( string $url ): string {
		// Try to get extension from URL path
		$path = parse_url( $url, PHP_URL_PATH );
		$path_info = pathinfo( $path );

		if ( ! empty( $path_info['extension'] ) ) {
			$extension = strtolower( $path_info['extension'] );
			$valid_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );

			if ( in_array( $extension, $valid_extensions, true ) ) {
				return $extension;
			}
		}

		// Try to determine from content type
		$response = wp_remote_head( $url, array(
			'timeout' => 10,
			'sslverify' => false,
		) );

		if ( ! is_wp_error( $response ) ) {
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );

			$type_extensions = array(
				'image/jpeg' => 'jpg',
				'image/png' => 'png',
				'image/gif' => 'gif',
				'image/webp' => 'webp',
			);

			if ( isset( $type_extensions[ $content_type ] ) ) {
				return $type_extensions[ $content_type ];
			}
		}

		// Default to jpg
		return 'jpg';
	}

	/**
	 * Clean up orphaned images
	 *
	 * @since 3.0.0
	 * @param int $days_old Days old to consider for cleanup.
	 * @return int Number of images cleaned up.
	 */
	public function cleanup_orphaned_images( int $days_old = 30 ): int {
		global $wpdb;

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// Find attachments imported by this plugin that are no longer associated with any gallery posts
		$orphaned_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'attachment'
			 AND pm.meta_key = '_brag_imported_date'
			 AND pm.meta_value < %s
			 AND p.post_parent = 0
			 OR p.post_parent NOT IN (
				SELECT ID FROM {$wpdb->posts} WHERE post_type = 'brag_gallery'
			 )",
			$cutoff_date
		) );

		$cleaned_count = 0;

		foreach ( $orphaned_ids as $attachment_id ) {
			if ( wp_delete_attachment( $attachment_id, true ) ) {
				$cleaned_count++;
			}
		}

		return $cleaned_count;
	}

	/**
	 * Get image statistics
	 *
	 * @since 3.0.0
	 * @return array Array of image statistics.
	 */
	public function get_image_stats(): array {
		global $wpdb;

		$stats = array(
			'total_imported' => 0,
			'total_size_mb' => 0,
			'by_type' => array(
				'before' => 0,
				'after' => 0,
				'other' => 0,
			),
			'recent_imports' => 0,
		);

		// Get total imported images
		$stats['total_imported'] = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_brag_imported_date'"
		);

		// Get images by type
		$type_counts = $wpdb->get_results(
			"SELECT pm.meta_value as image_type, COUNT(*) as count
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
			 WHERE pm.meta_key = '_brag_image_type'
			 AND pm2.meta_key = '_brag_imported_date'
			 GROUP BY pm.meta_value"
		);

		foreach ( $type_counts as $row ) {
			$type = $row->image_type ?: 'other';
			if ( isset( $stats['by_type'][ $type ] ) ) {
				$stats['by_type'][ $type ] = (int) $row->count;
			}
		}

		// Get recent imports (last 7 days)
		$week_ago = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$stats['recent_imports'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_brag_imported_date'
			 AND meta_value > %s",
			$week_ago
		) );

		// Calculate total file size
		$attachment_ids = $wpdb->get_col(
			"SELECT post_id
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_brag_imported_date'"
		);

		$total_bytes = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$total_bytes += filesize( $file_path );
			}
		}

		$stats['total_size_mb'] = round( $total_bytes / 1048576, 2 ); // Convert to MB

		return $stats;
	}

	/**
	 * Batch process image optimization
	 *
	 * @since 3.0.0
	 * @param int $batch_size Number of images to process per batch.
	 * @return array Result with processed count and any errors.
	 */
	public function batch_optimize_images( int $batch_size = 10 ): array {
		global $wpdb;

		// Find images that need optimization
		$attachment_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_brag_optimized'
			 WHERE p.post_type = 'attachment'
			 AND pm.meta_key = '_brag_imported_date'
			 AND pm2.meta_value IS NULL
			 LIMIT %d",
			$batch_size
		) );

		$processed = 0;
		$errors = array();

		foreach ( $attachment_ids as $attachment_id ) {
			try {
				if ( $this->optimize_image( $attachment_id ) ) {
					update_post_meta( $attachment_id, '_brag_optimized', current_time( 'mysql' ) );
					$processed++;
				}
			} catch ( \Exception $e ) {
				$errors[] = "Failed to optimize attachment {$attachment_id}: " . $e->getMessage();
			}
		}

		return array(
			'processed' => $processed,
			'errors' => $errors,
			'remaining' => count( $attachment_ids ) - $processed,
		);
	}

	/**
	 * Validate image type string
	 *
	 * @since 3.0.0
	 * @param string $type Image type to validate.
	 * @return string Validated image type.
	 */
	private function validate_image_type( string $type ): string {
		$valid_types = [ 'before', 'after', 'other' ];
		$type = strtolower( trim( $type ) );
		
		return in_array( $type, $valid_types, true ) ? $type : 'other';
	}

	/**
	 * Format error message for display
	 *
	 * @since 3.0.0
	 * @param mixed  $photo Photo data.
	 * @param string $message Error message.
	 * @return string Formatted error message.
	 */
	private function format_error_message( mixed $photo, string $message ): string {
		$url = is_array( $photo ) ? ( $photo['url'] ?? 'unknown' ) : (string) $photo;
		return sprintf( 'Failed to import %s: %s', $url, $message );
	}

	/**
	 * Get cache key for URL
	 *
	 * @since 3.0.0
	 * @param string $url Image URL.
	 * @return string Cache key.
	 */
	private function get_url_cache_key( string $url ): string {
		return 'brag_img_' . md5( $url );
	}

	/**
	 * Get cached attachment ID
	 *
	 * @since 3.0.0
	 * @param string $cache_key Cache key.
	 * @return int|null Cached attachment ID or null.
	 */
	private function get_cached_attachment_id( string $cache_key ): ?int {
		if ( isset( $this->memory_cache[ $cache_key ] ) ) {
			return $this->memory_cache[ $cache_key ];
		}
		
		$cached = get_transient( $cache_key );
		return $cached ? (int) $cached : null;
	}

	/**
	 * Cache attachment ID
	 *
	 * @since 3.0.0
	 * @param string $cache_key Cache key.
	 * @param int    $attachment_id Attachment ID to cache.
	 */
	private function cache_attachment_id( string $cache_key, int $attachment_id ): void {
		$this->memory_cache[ $cache_key ] = $attachment_id;
		set_transient( $cache_key, $attachment_id, self::CACHE_TTL_LONG );
	}

	/**
	 * Sanitize and validate URL
	 *
	 * @since 3.0.0
	 * @param string $url URL to sanitize.
	 * @return string|null Sanitized URL or null if invalid.
	 */
	private function sanitize_url( string $url ): ?string {
		$url = esc_url_raw( $url );
		
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return null;
		}
		
		// Ensure HTTPS for security
		if ( strpos( $url, 'http://' ) === 0 ) {
			$url = str_replace( 'http://', 'https://', $url );
		}
		
		return $url;
	}

	/**
	 * Check if URL domain is allowed
	 *
	 * @since 3.0.0
	 * @param string $url URL to check.
	 * @return bool True if allowed.
	 */
	private function is_allowed_domain( string $url ): bool {
		$allowed_domains = apply_filters( 'brag_book_gallery_allowed_image_domains', [
			'bragbook.com',
			'bragbookplatform.com',
			'cloudinary.com',
			'amazonaws.com',
		] );
		
		if ( empty( $allowed_domains ) ) {
			return true; // No restrictions
		}
		
		$parsed = wp_parse_url( $url );
		$host = $parsed['host'] ?? '';
		
		foreach ( $allowed_domains as $domain ) {
			if ( str_ends_with( $host, $domain ) ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Sideload image with retries
	 *
	 * @since 3.0.0
	 * @param string $url Image URL.
	 * @param int    $post_id Post ID to attach to.
	 * @return int|null Attachment ID or null on failure.
	 */
	private function sideload_image_with_retries( string $url, int $post_id = 0 ): ?int {
		$max_retries = self::MAX_DOWNLOAD_RETRIES;
		$attempt = 0;
		$last_error = null;
		
		while ( $attempt < $max_retries ) {
			try {
				return $this->sideload_image( $url, $post_id );
			} catch ( Exception $e ) {
				$last_error = $e;
				$attempt++;
				
				if ( $attempt < $max_retries ) {
					sleep( min( $attempt * 2, 10 ) ); // Exponential backoff
				}
			}
		}
		
		if ( $last_error ) {
			$this->log_error( 'sideload_retries', "Failed after {$max_retries} attempts: {$last_error->getMessage()}" );
		}
		
		return null;
	}

	/**
	 * Track successful import
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $url Original URL.
	 */
	private function track_import_success( int $attachment_id, string $url ): void {
		update_post_meta( $attachment_id, '_brag_import_success', true );
		update_post_meta( $attachment_id, '_brag_import_timestamp', time() );
		
		/**
		 * Fires after successful image import.
		 *
		 * @since 3.0.0
		 * @param int    $attachment_id The imported attachment ID.
		 * @param string $url The original image URL.
		 */
		do_action( 'brag_book_gallery_image_imported', $attachment_id, $url );
	}

	/**
	 * Log error message
	 *
	 * @since 3.0.0
	 * @param string $context Error context.
	 * @param string $message Error message.
	 */
	private function log_error( string $context, string $message ): void {
		$this->error_log[ $context ][] = [
			'time'    => current_time( 'mysql' ),
			'message' => $message,
		];
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[BRAGBook Image Sync] {$context}: {$message}" );
		}
	}

	/**
	 * Track performance metrics
	 *
	 * @since 3.0.0
	 * @param string $operation Operation name.
	 * @param float  $duration Operation duration.
	 */
	private function track_performance( string $operation, float $duration ): void {
		if ( ! isset( $this->performance_metrics[ $operation ] ) ) {
			$this->performance_metrics[ $operation ] = [
				'count'   => 0,
				'total'   => 0,
				'min'     => PHP_FLOAT_MAX,
				'max'     => 0,
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
	 * Get performance metrics
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> Performance metrics.
	 */
	public function get_performance_metrics(): array {
		return $this->performance_metrics;
	}
}
