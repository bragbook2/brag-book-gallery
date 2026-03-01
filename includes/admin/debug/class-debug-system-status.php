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
 * @author     BRAG book Team
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

		$memory_limit    = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$memory_ok       = $memory_limit >= 134217728; // 128M

		$rows = [
			[
				'label'  => __( 'Plugin Version', 'brag-book-gallery' ),
				'value'  => $plugin_data['Version'] ?? '3.0.0',
				'status' => version_compare( $plugin_data['Version'] ?? '3.0.0', '3.0.0', '>=' ),
			],
			[
				'label'  => __( 'WordPress Version', 'brag-book-gallery' ),
				'value'  => get_bloginfo( 'version' ),
				'status' => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ),
			],
			[
				'label'  => __( 'PHP Version', 'brag-book-gallery' ),
				'value'  => phpversion(),
				'status' => version_compare( phpversion(), '8.2', '>=' ),
			],
			[
				'label'  => __( 'API Connection', 'brag-book-gallery' ),
				'value'  => $api_status === 'configured'
					? __( 'Configured', 'brag-book-gallery' )
					: __( 'Not Configured', 'brag-book-gallery' ),
				'status' => $api_status === 'configured',
			],
			[
				'label'  => __( 'Memory Limit', 'brag-book-gallery' ),
				'value'  => ini_get( 'memory_limit' ),
				'status' => $memory_ok,
			],
		];
		?>
		<div class="system-status-rows">
			<?php foreach ( $rows as $index => $row ) : ?>
				<div class="system-status-row<?php echo $index % 2 === 0 ? '' : ' system-status-row--alt'; ?>">
					<span class="system-status-label"><?php echo esc_html( $row['label'] ); ?></span>
					<span class="system-status-value"><?php echo esc_html( $row['value'] ); ?></span>
					<span class="system-status-indicator">
						<?php if ( $row['status'] ) : ?>
							<span class="status-badge status-badge--success">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
							</span>
						<?php else : ?>
							<span class="status-badge status-badge--error">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
							</span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
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
