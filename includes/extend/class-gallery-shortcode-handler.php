<?php
/**
 * Gallery Shortcode Handler for BRAGBookGallery plugin.
 *
 * This is a thin wrapper that delegates to the main Shortcodes class
 * to avoid code duplication.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

/**
 * Class Gallery_Shortcode_Handler
 *
 * Manages the main gallery shortcode [brag_book_gallery].
 * Delegates the actual implementation to the Shortcodes class to maintain
 * backward compatibility with existing code that may be calling Shortcodes methods directly.
 *
 * @since 3.0.0
 */
final class Gallery_Shortcode_Handler {

	/**
	 * Handle the main gallery shortcode.
	 *
	 * This delegates to the Shortcodes class which contains the full implementation.
	 * This approach maintains backward compatibility while following the handler pattern.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Gallery HTML or error message.
	 */
	public static function handle( array $atts ): string {
		// Delegate to the Shortcodes class which has the full implementation
		// This maintains backward compatibility with existing code
		return Shortcodes::main_gallery_shortcode( $atts );
	}
}