<?php
/**
 * Debug System Status Component
 *
 * Handles rendering and management of system status information in the debug interface.
 * Displays critical system requirements, version checks, and configuration status for
 * WordPress, PHP, plugin, and API connectivity. Utilizes modern PHP 8.2+ syntax and
 * WordPress VIP coding standards.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug
 * @since      3.3.0
 * @version    3.3.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 *
 * @see \BRAGBookGallery\Includes\Admin\Debug\System_Info For detailed system information
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug System Status class
 *
 * Provides visual status indicators for critical system components including plugin version,
 * WordPress version, PHP version, API configuration, and memory limits. Uses color-coded
 * SVG icons to quickly identify potential issues.
 *
 * ## Features:
 * - Plugin version validation
 * - WordPress version requirement checking
 * - PHP version requirement verification
 * - API connection status monitoring
 * - Memory limit validation
 * - Visual status indicators with SVG icons
 *
 * ## Status Indicators:
 * - Green checkmark: Requirement met
 * - Orange/Yellow X: Requirement not met or needs attention
 *
 * ## Version Requirements:
 * - Plugin: 3.0.0+
 * - WordPress: 6.0+
 * - PHP: 8.2+
 * - Memory: 128M+
 *
 * @since 3.3.0
 */
final class Debug_System_Status {

	/**
	 * Render the system status table
	 *
	 * Displays a comprehensive table with system requirements and their current status.
	 * Each row includes the requirement name, current value, and a visual status indicator.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		// Get Plugin info.
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );

		// Determine API connection status for system health assessment.
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$api_status = ! empty( $api_tokens ) ? 'configured' : 'not-configured';
		?>
		<table class="widefat striped">
			<tbody>
			<tr>
				<th><?php esc_html_e( 'Plugin Version', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( $plugin_data['Version'] ?? '3.0.0' ); ?></td>
				<td>
					<?php $this->render_version_status_icon( $plugin_data['Version'] ?? '3.0.0', '3.0.0' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				<td>
					<?php $this->render_version_status_icon( get_bloginfo( 'version' ), '6.0' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( phpversion() ); ?></td>
				<td>
					<?php $this->render_version_status_icon( phpversion(), '8.2' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'API Connection', 'brag-book-gallery' ); ?></th>
				<td>
					<?php
					if ( $api_status === 'configured' ) {
						esc_html_e( 'Configured', 'brag-book-gallery' );
					} else {
						esc_html_e( 'Not Configured', 'brag-book-gallery' );
					}
					?>
				</td>
				<td>
					<?php
					if ( $api_status === 'configured' ) {
						$this->render_success_icon();
					} else {
						$this->render_warning_icon();
					}
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Memory Limit', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
				<td>
					<?php
					$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
					if ( $memory_limit >= 134217728 ) : // 128M
						$this->render_success_icon();
					else :
						$this->render_warning_icon();
					endif;
					?>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render version status icon
	 *
	 * Compares the current version against a minimum required version and displays
	 * the appropriate status icon (success or warning).
	 *
	 * @since 3.3.0
	 *
	 * @param string $current_version The current version string.
	 * @param string $min_version     The minimum required version string.
	 *
	 * @return void Outputs SVG icon HTML
	 */
	private function render_version_status_icon( string $current_version, string $min_version ): void {
		if ( version_compare( $current_version, $min_version, '>=' ) ) {
			$this->render_success_icon();
		} else {
			$this->render_warning_icon();
		}
	}

	/**
	 * Render success status icon
	 *
	 * Displays a green checkmark SVG icon indicating successful status.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs SVG HTML
	 */
	private function render_success_icon(): void {
		?>
		<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
		<?php
	}

	/**
	 * Render warning status icon
	 *
	 * Displays an orange/yellow X SVG icon indicating a warning or issue that needs attention.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs SVG HTML
	 */
	private function render_warning_icon(): void {
		?>
		<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffb900;"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
		<?php
	}
}
