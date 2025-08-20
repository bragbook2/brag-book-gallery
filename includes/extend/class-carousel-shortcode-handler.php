<?php
/**
 * Carousel Shortcode Handler for BRAGBookGallery plugin.
 *
 * Handles the carousel shortcode functionality by delegating to the main Shortcodes class.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

/**
 * Class Carousel_Shortcode_Handler
 *
 * Manages the carousel shortcode [brag_book_carousel].
 * Delegates to the Shortcodes class to maintain backward compatibility.
 *
 * @since 3.0.0
 */
final class Carousel_Shortcode_Handler {

	/**
	 * Handle the carousel shortcode.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Carousel HTML or error message.
	 */
	public static function handle( array $atts ): string {
		// Delegate to the Shortcodes class which has the full implementation
		// This maintains backward compatibility with existing code
		return Shortcodes::carousel_shortcode( $atts );
	}
}