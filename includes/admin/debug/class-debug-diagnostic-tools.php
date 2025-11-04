<?php
/**
 * Debug Diagnostic Tools Component
 *
 * Handles rendering of diagnostic tools in the debug interface. Provides access
 * to advanced troubleshooting tools including gallery checker and system analysis.
 * Utilizes modern PHP 8.2+ syntax and WordPress VIP coding standards.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Diagnostic Tools class
 *
 * Provides access to advanced diagnostic and troubleshooting tools for analyzing
 * gallery functionality, page detection, shortcode validation, and other
 * gallery-specific issues.
 *
 * ## Features:
 * - Gallery page checker tool integration
 * - Advanced diagnostic capabilities
 * - Troubleshooting utilities
 *
 * ## Available Tools:
 * - Gallery Checker: Validates gallery pages and shortcodes
 *
 * @since 3.3.0
 */
final class Debug_Diagnostic_Tools {

	/**
	 * Render the diagnostic tools interface
	 *
	 * Displays available diagnostic tools with descriptions and controls.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		?>
		<div id="diagnostic-tools" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Advanced diagnostic tools for troubleshooting and debugging gallery functionality.', 'brag-book-gallery' ); ?>
			</p>

			<!-- Gallery Checker Section -->
			<div class="diagnostic-section gallery-checker-section">
				<?php
				$gallery_checker_tool = ( new Gallery_Checker() )->render();
				?>
			</div>

		</div>
		<?php
	}

	/**
	 * Get available diagnostic tools
	 *
	 * Returns an array of available diagnostic tools with their metadata.
	 *
	 * @since 3.3.0
	 *
	 * @return array Array of diagnostic tools
	 */
	public function get_available_tools(): array {
		return array(
			'gallery_checker' => array(
				'name'        => __( 'Gallery Checker', 'brag-book-gallery' ),
				'description' => __( 'Validates gallery pages, shortcodes, and configuration.', 'brag-book-gallery' ),
				'class'       => Gallery_Checker::class,
			),
		);
	}
}
