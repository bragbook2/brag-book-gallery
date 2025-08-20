<?php
/**
 * Cases Shortcode Handler for BRAGBookGallery plugin.
 *
 * Handles the cases shortcode functionality by delegating to the main Shortcodes class.
 *
 * @package BRAGBookGallery
 * @since   3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Extend;

/**
 * Class Cases_Shortcode_Handler
 *
 * Manages the cases shortcode [brag_book_gallery_cases] and case details [brag_book_gallery_case].
 * Delegates to the Gallery_Shortcode_Handler for now as these shortcodes share similar functionality.
 *
 * @since 3.0.0
 */
final class Cases_Shortcode_Handler {

	/**
	 * Handle the cases shortcode.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Cases HTML or error message.
	 */
	public static function handle( array $atts ): string {
		// For now, delegate to the main gallery shortcode
		// The cases shortcode shares the same functionality as the main gallery
		return Gallery_Shortcode_Handler::handle( $atts );
	}

	/**
	 * Handle the case details shortcode.
	 *
	 * @since 3.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Case details HTML.
	 */
	public static function handle_case_details( array $atts = [] ): string {
		// For now, delegate to the main gallery shortcode
		// The case details are handled within the main gallery JavaScript
		return Gallery_Shortcode_Handler::handle( $atts );
	}
}