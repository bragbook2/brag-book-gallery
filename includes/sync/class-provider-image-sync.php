<?php
/**
 * Provider Image Sync
 *
 * Downloads a provider's remote profile image into the WordPress media library
 * during sync, attaches it to the `brag_book_providers` term as its Profile
 * Photo, and tracks the created attachment so it is removed from WordPress when
 * the provider term is deleted.
 *
 * The download is idempotent: the image is only re-fetched when its source
 * object path changes (the signed query string is ignored) or the tracked
 * attachment has gone missing. A manually-chosen Profile Photo is preserved.
 *
 * @package BRAGBookGallery
 * @subpackage Sync
 * @since 4.8.0
 */

namespace BRAGBookGallery\Includes\Sync;

use BRAGBookGallery\Includes\Extend\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider_Image_Sync class
 *
 * @since 4.8.0
 */
class Provider_Image_Sync {

	/**
	 * Term meta: URL rendered as the provider's image. Set to the local
	 * attachment URL once the image has been downloaded.
	 */
	private const META_IMAGE_URL = 'provider_image_url';

	/**
	 * Term meta: Profile Photo attachment ID (shared with the manual upload field).
	 */
	private const META_PROFILE_PHOTO = 'provider_profile_photo';

	/**
	 * Term meta: ID of the attachment this sync created, so it can be cleaned up
	 * on term deletion without touching a manually-uploaded photo.
	 */
	private const META_SYNCED_ATTACHMENT = 'provider_synced_image_id';

	/**
	 * Term meta: stable source object path last downloaded, used to skip
	 * re-downloading an unchanged image across syncs.
	 */
	private const META_SYNCED_SOURCE = 'provider_synced_image_source';

	/**
	 * Register the term-deletion cleanup hook.
	 *
	 * Registered unconditionally (independent of the Providers feature toggle) so
	 * a tracked attachment is always removed when its provider term is deleted,
	 * whether by the admin UI or by sync orphan cleanup.
	 *
	 * @since 4.8.0
	 * @return void
	 */
	public static function register(): void {
		add_action( 'pre_delete_term', [ self::class, 'cleanup_on_delete' ], 10, 2 );
	}

	/**
	 * Download a provider's remote image and attach it to the term.
	 *
	 * On failure the remote URL is stored as the image meta so the avatar still
	 * renders; the tracked attachment is left untouched.
	 *
	 * @since 4.8.0
	 * @param int    $term_id   Provider term ID.
	 * @param string $image_url Remote (signed) image URL from the API.
	 * @return void
	 */
	public static function sync_from_url( int $term_id, string $image_url ): void {
		$image_url = trim( $image_url );
		if ( $term_id <= 0 || '' === $image_url ) {
			return;
		}

		$source_path = self::source_path( $image_url );
		$tracked_id  = (int) get_term_meta( $term_id, self::META_SYNCED_ATTACHMENT, true );
		$tracked_src = (string) get_term_meta( $term_id, self::META_SYNCED_SOURCE, true );

		// Unchanged source and the attachment still exists: nothing to download.
		if ( $tracked_id > 0 && $source_path === $tracked_src && get_post( $tracked_id ) instanceof \WP_Post ) {
			self::apply_attachment( $term_id, $tracked_id );
			return;
		}

		$attachment_id = self::sideload( $image_url, self::filename( $term_id, $source_path ) );

		if ( is_wp_error( $attachment_id ) ) {
			// Keep the remote URL so the avatar still shows; leave tracking as-is.
			update_term_meta( $term_id, self::META_IMAGE_URL, $image_url );
			return;
		}

		// Replace the previously-synced attachment we own.
		if ( $tracked_id > 0 && $tracked_id !== $attachment_id ) {
			wp_delete_attachment( $tracked_id, true );
		}

		update_term_meta( $term_id, self::META_SYNCED_ATTACHMENT, $attachment_id );
		update_term_meta( $term_id, self::META_SYNCED_SOURCE, $source_path );
		self::apply_attachment( $term_id, $attachment_id, $tracked_id );
	}

	/**
	 * Delete the tracked attachment when a provider term is removed.
	 *
	 * @since 4.8.0
	 * @param int    $term_id  The term about to be deleted.
	 * @param string $taxonomy The taxonomy of the term.
	 * @return void
	 */
	public static function cleanup_on_delete( int $term_id, string $taxonomy ): void {
		if ( Taxonomies::TAXONOMY_PROVIDERS !== $taxonomy ) {
			return;
		}

		$attachment_id = (int) get_term_meta( $term_id, self::META_SYNCED_ATTACHMENT, true );
		if ( $attachment_id > 0 ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	/**
	 * Point the term's image meta and Profile Photo at the local attachment.
	 *
	 * The Profile Photo is only set when it is empty or still references the
	 * attachment a previous sync created, so a manually-chosen photo is kept.
	 *
	 * @since 4.8.0
	 * @param int $term_id            Provider term ID.
	 * @param int $attachment_id      Local attachment to apply.
	 * @param int $previous_synced_id Attachment a prior sync had set, if any.
	 * @return void
	 */
	private static function apply_attachment( int $term_id, int $attachment_id, int $previous_synced_id = 0 ): void {
		$local_url = wp_get_attachment_url( $attachment_id );
		if ( is_string( $local_url ) && '' !== $local_url ) {
			update_term_meta( $term_id, self::META_IMAGE_URL, $local_url );
		}

		$current_photo = (int) get_term_meta( $term_id, self::META_PROFILE_PHOTO, true );
		if ( 0 === $current_photo || $current_photo === $previous_synced_id ) {
			update_term_meta( $term_id, self::META_PROFILE_PHOTO, $attachment_id );
		}
	}

	/**
	 * Build the attachment filename from the provider slug and source extension.
	 *
	 * @since 4.8.0
	 * @param int    $term_id     Provider term ID.
	 * @param string $source_path Source object path (no query string).
	 * @return string Filename such as "ashley-lentz-md-32.jpg".
	 */
	private static function filename( int $term_id, string $source_path ): string {
		$term = get_term( $term_id, Taxonomies::TAXONOMY_PROVIDERS );
		$slug = ( $term instanceof \WP_Term && '' !== $term->slug ) ? $term->slug : 'provider-' . $term_id;

		$ext = strtolower( (string) pathinfo( $source_path, PATHINFO_EXTENSION ) );
		if ( 1 !== preg_match( '/^[a-z0-9]{2,5}$/', $ext ) ) {
			$ext = 'jpg';
		}

		return $slug . '.' . $ext;
	}

	/**
	 * Stable source object path (query string stripped) used to detect changes.
	 *
	 * @since 4.8.0
	 * @param string $image_url Remote image URL.
	 * @return string
	 */
	private static function source_path( string $image_url ): string {
		$path = wp_parse_url( $image_url, PHP_URL_PATH );

		return is_string( $path ) && '' !== $path ? $path : $image_url;
	}

	/**
	 * Download a remote image into the media library under the given filename.
	 *
	 * @since 4.8.0
	 * @param string $image_url Remote image URL.
	 * @param string $filename  Desired attachment filename.
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	private static function sideload( string $image_url, string $filename ) {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$temp_file = download_url( $image_url );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$file = [
			'name'     => $filename,
			'tmp_name' => $temp_file,
		];

		$attachment_id = media_handle_sideload( $file, 0 );

		// media_handle_sideload removes the temp file on success only.
		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			return $attachment_id;
		}

		return (int) $attachment_id;
	}
}
