<?php
/**
 * Debug Settings Class
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Pages;

use BRAGBookGallery\Includes\Admin\Core\Settings_Base;
use BRAGBookGallery\Includes\Admin\Debug\Debug_API_Test;
use BRAGBookGallery\Includes\Admin\Debug\Debug_Database_Info;
use BRAGBookGallery\Includes\Admin\Debug\Debug_Diagnostic_Tools;
use BRAGBookGallery\Includes\Admin\Debug\Debug_Export_Import;
use BRAGBookGallery\Includes\Admin\Debug\Debug_Factory_Reset;
use BRAGBookGallery\Includes\Admin\Debug\Debug_Logs;
use BRAGBookGallery\Includes\Admin\Debug\Debug_System_Status;
use BRAGBookGallery\Includes\Admin\Debug\System_Info;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Debug Page class
 *
 * Main debug page orchestrator that coordinates multiple debug components
 * including system status, logs, database info, API testing, and diagnostic tools.
 *
 * @since 3.0.0
 */
class Debug_Page extends Settings_Base {
	use \BRAGBookGallery\Includes\Admin\UI\Traits\Trait_Ajax_Handler;

	/**
	 * System status component instance
	 *
	 * @since 3.3.0
	 * @var Debug_System_Status
	 */
	private Debug_System_Status $system_status;

	/**
	 * Debug logs component instance
	 *
	 * @since 3.3.0
	 * @var Debug_Logs
	 */
	private Debug_Logs $logs;

	/**
	 * Database info component instance
	 *
	 * @since 3.3.0
	 * @var Debug_Database_Info
	 */
	private Debug_Database_Info $database_info;

	/**
	 * Export/Import component instance
	 *
	 * @since 3.3.0
	 * @var Debug_Export_Import
	 */
	private Debug_Export_Import $export_import;

	/**
	 * Factory reset component instance
	 *
	 * @since 3.3.0
	 * @var Debug_Factory_Reset
	 */
	private Debug_Factory_Reset $factory_reset;

	/**
	 * Diagnostic tools component instance
	 *
	 * @since 3.3.0
	 * @var Debug_Diagnostic_Tools
	 */
	private Debug_Diagnostic_Tools $diagnostic_tools;

	/**
	 * API test component instance
	 *
	 * @since 3.3.0
	 * @var Debug_API_Test
	 */
	private Debug_API_Test $api_test;

	/**
	 * Initialize debug settings page configuration
	 *
	 * Sets up the page slug for the debug and diagnostic interface.
	 * This page provides essential troubleshooting tools for administrators
	 * and support personnel working with BRAG book Gallery installations.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {

		$this->page_slug  = 'brag-book-gallery-debug';
		// Don't translate here - translations happen in render method.

		// Initialize component instances.
		$this->system_status     = new Debug_System_Status();
		$this->logs              = new Debug_Logs();
		$this->database_info     = new Debug_Database_Info();
		$this->export_import     = new Debug_Export_Import();
		$this->factory_reset     = new Debug_Factory_Reset();
		$this->diagnostic_tools  = new Debug_Diagnostic_Tools();
		$this->api_test          = new Debug_API_Test();

		$this->init_ajax_handlers();
		$this->init_log_file();

		// Handle export/import early, before any output.
		add_action( 'admin_init', array( $this, 'handle_export_import_early' ), 1 );
	}

	/**
	 * Initialize AJAX handlers for debug settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init_ajax_handlers(): void {
		// Debug settings
		$this->register_ajax_action(
			'brag_book_gallery_save_debug_settings',
			array( $this, 'handle_save_debug_settings' )
		);

		// Error log management
		$this->register_ajax_action(
			'brag_book_gallery_get_error_log',
			array( $this, 'handle_get_error_log' )
		);

		$this->register_ajax_action(
			'brag_book_gallery_clear_error_log', array( $this, 'handle_clear_error_log' ) );
		$this->register_ajax_action(
			'brag_book_gallery_download_error_log', array( $this, 'handle_download_error_log' ) );

		// API log management.
		$this->register_ajax_action(
			'brag_book_gallery_get_api_log',
			array( $this, 'handle_get_api_log' )
		);

		$this->register_ajax_action(
			'brag_book_gallery_clear_api_log', array( $this, 'handle_clear_api_log' ) );
		$this->register_ajax_action(
			'brag_book_gallery_download_api_log', array( $this, 'handle_download_api_log' ) );

		// System info export.
		$this->register_ajax_action(
			'brag_book_gallery_export_system_info', array( $this, 'handle_export_system_info' ) );

		// Factory reset.
		$this->register_ajax_action(
			'brag_book_gallery_factory_reset', array( $this, 'handle_factory_reset' ) );
	}

	/**
	 * Initialize log file and directory
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_log_file(): void {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess for security
			$htaccess_content = "deny from all";
			file_put_contents( $log_dir . '/.htaccess', $htaccess_content );

			// Add index.php for additional security
			$index_content = "<?php // Silence is golden";
			file_put_contents( $log_dir . '/index.php', $index_content );
		}
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering (after init)
		$this->page_title = __( 'Debug & Diagnostics', 'brag-book-gallery' );
		$this->menu_title = __( 'Debug', 'brag-book-gallery' );

		// Handle actions
		if ( isset( $_POST['save_debug_settings'] ) ) {
			$this->save_debug_settings();
		}

		if ( isset( $_POST['clear_cache'] ) ) {
			$this->clear_cache();
		}

		if ( isset( $_POST['clear_logs'] ) ) {
			$this->clear_logs();
		}

		// Handle debug log actions
		if ( isset( $_POST['save_log_settings'] ) ) {
			$this->save_log_settings();
		}

		if ( isset( $_POST['clear_debug_log'] ) ) {
			$this->clear_debug_log();
		}

		// Handle log download
		if ( isset( $_GET['download_log'] ) && isset( $_GET['nonce'] ) && wp_verify_nonce( $_GET['nonce'], 'download_log' ) ) {
			$this->download_debug_log();
		}

		// Export is now handled in handle_export_import_early()
		// Import is still handled here to show success messages
		if ( isset( $_POST['import_settings'] ) ) {
			$this->import_settings();
		}

		// Get current settings
		$enable_logs = get_option( 'brag_book_gallery_enable_logs', 'no' );
		$log_level   = get_option( 'brag_book_gallery_log_level', 'error' );

		$this->render_header();
		?>

		<!-- Custom Notices Section -->
		<div class="brag-book-gallery-notices">
			<?php $this->render_custom_notices(); ?>
		</div>

		<!-- Debug Settings with Side Tabs -->
		<div class="brag-book-gallery-tabbed-section">
			<?php $this->render_side_tabs(); ?>
			<div class="brag-book-gallery-tab-content">
				<?php $this->render_tab_content(); ?>
			</div>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Function to show a specific tab panel
				function showTabPanel(targetId) {
					// Hide all panels
					const allPanels = document.querySelectorAll('.brag-book-gallery-tab-panel');
					allPanels.forEach(panel => {
						panel.classList.remove('active');
						panel.style.display = 'none';
					});

					// Show target panel
					const targetPanel = document.getElementById(targetId);
					if (targetPanel) {
						targetPanel.classList.add('active');
						targetPanel.style.display = 'block';
					}

					// Update tab active states
					const allTabs = document.querySelectorAll('.brag-book-gallery-side-tabs a');
					allTabs.forEach(tab => {
						if (tab.getAttribute('href') === '#' + targetId) {
							tab.classList.add('active');
						} else {
							tab.classList.remove('active');
						}
					});
				}

				// Tab switching functionality
				const tabLinks = document.querySelectorAll('.brag-book-gallery-side-tabs a');

				tabLinks.forEach(link => {
					link.addEventListener('click', function(e) {
						e.preventDefault();

						const targetId = this.getAttribute('href').substring(1);
						showTabPanel(targetId);

						// Update URL hash without triggering page reload
						if (history.pushState) {
							history.pushState(null, null, '#' + targetId);
						} else {
							location.hash = '#' + targetId;
						}
					});
				});

				// Check for URL hash and activate corresponding tab
				function initializeTabFromHash() {
					const hash = window.location.hash.substring(1);
					if (hash && document.getElementById(hash)) {
						showTabPanel(hash);
					} else {
						// Default to system-status if no valid hash
						showTabPanel('system-status');
					}
				}

				// Handle browser back/forward navigation
				window.addEventListener('hashchange', function() {
					const hash = window.location.hash.substring(1);
					if (hash && document.getElementById(hash)) {
						showTabPanel(hash);
					}
				});

				// Initialize the correct tab based on URL hash
				initializeTabFromHash();
			});
		</script>

		<?php
		$this->render_footer();
	}

	/**
	 * Render side tabs navigation
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_side_tabs(): void {
		?>
		<div class="brag-book-gallery-side-tabs">
			<ul>
				<li><a href="#system-status"><?php esc_html_e( 'System Status', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#debug-logs"><?php esc_html_e( 'Debug Logs', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#database-info"><?php esc_html_e( 'Database Info', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#api-test"><?php esc_html_e( 'API Test', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#diagnostic-tools"><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#factory-reset"><?php esc_html_e( 'Factory Reset', 'brag-book-gallery' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render tab content
	 *
	 * Delegates rendering to component classes for each tab section.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_tab_content(): void {
		?>
		<!-- System Status Tab -->
		<div id="system-status" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'System Status', 'brag-book-gallery' ); ?></h2>
			<?php $this->system_status->render(); ?>

			<!-- System Information Section -->
			<?php
			$system_info_tool = ( new System_Info() )->render();
			?>
		</div>

		<!-- Debug Logs Tab -->
		<div id="debug-logs" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Debug Logs', 'brag-book-gallery' ); ?></h2>
			<?php $this->logs->render(); ?>
		</div>

		<!-- Database Information Tab -->
		<div id="database-info" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Database Information', 'brag-book-gallery' ); ?></h2>
			<?php $this->database_info->render(); ?>
		</div>

		<!-- Export/Import Tab -->
		<?php $this->export_import->render(); ?>

		<!-- API Test Tab -->
		<div id="api-test" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'API Test', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Test your BRAGBook API connection and endpoints to ensure proper gallery functionality.', 'brag-book-gallery' ); ?>
			</p>
			<?php $this->api_test->render(); ?>
			<?php
			// Hook for detailed API test content (handled in render_api_test_panel for now)
			if ( has_action( 'brag_book_gallery_render_api_test_content' ) === false ) {
				$this->render_api_test_panel();
			}
			?>
		</div>

		<!-- Diagnostic Tools Tab -->
		<?php $this->diagnostic_tools->render(); ?>

		<!-- Factory Reset Tab -->
		<?php $this->factory_reset->render(); ?>
		<?php
	}

	/**
	 * Render comprehensive system status diagnostics table
	 *
	 * Generates a detailed system status table that checks multiple aspects
	 * of the plugin's operational environment. Each check includes:
	 * - Current value or status
	 * - Visual indicator (green checkmark, yellow warning, or red X)
	 * - Compatibility assessment against minimum requirements
	 *
	 * **Status Checks Performed:**
	 * - Plugin version vs minimum requirements
	 * - WordPress version compatibility
	 * - PHP version requirements (8.2+ recommended)
	 * - Current operational mode (Default/Local)
	 * - API connection configuration status
	 * - Memory limit sufficiency for plugin operations
	 *
	 * **Visual Indicators:**
	 * - Green checkmark: Requirements met or status optimal
	 * - Yellow warning: Functional but suboptimal configuration
	 * - Red X: Critical issue requiring attention
	 *
	 * This provides administrators with an at-a-glance view of system health
	 * and highlights any configuration issues that may affect plugin performance.
	 *
	 * @since 3.0.0
	 * @return void Outputs HTML table directly to browser
	 */
	private function render_system_status(): void {

		// Get Plugin info.
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );

		// Determine API connection status for system health assessment
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$api_status = ! empty( $api_tokens ) ? 'configured' : 'not-configured';
		?>
		<table class="widefat striped">
			<tbody>
			<tr>
				<th><?php esc_html_e( 'Plugin Version', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( $plugin_data['Version'] ?? '3.0.0' ); ?></td>
				<td>
					<?php if ( version_compare( $plugin_data['Version'], '3.0.0', '>=' ) ) : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php else : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffb900;"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
				<td>
					<?php if ( version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ) : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php else : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffb900;"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( phpversion() ); ?></td>
				<td>
					<?php if ( version_compare( phpversion(), '8.2', '>=' ) ) : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php else : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffb900;"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Active Mode', 'brag-book-gallery' ); ?></th>
				<td></td>
				<td><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg></td>
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
					<?php if ( $api_status === 'configured' ) : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php else : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffb900;"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Memory Limit', 'brag-book-gallery' ); ?></th>
				<td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
				<td>
					<?php
					$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
					if ( $memory_limit >= 134217728 ) : // 128M
						?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#46b450"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php else : ?>
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffb900;"><path d="m336-280 144-144 144 144 56-56-144-144 144-144-56-56-144 144-144-144-56 56 144 144-144 144 56 56ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Z"/></svg>
					<?php endif; ?>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	private function render_debug_logs(): void {
		$log_file = $this->get_log_file_path();
		$enable_logs = get_option( 'brag_book_gallery_enable_logs', 'no' );
		$log_level   = get_option( 'brag_book_gallery_log_level', 'error' );
		?>
		<div class="brag-book-gallery-debug-logs">
			<!-- Log Settings -->
			<div class="brag-book-gallery-card">
				<h3><?php esc_html_e( 'Debug Log Settings', 'brag-book-gallery' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Debug Logging', 'brag-book-gallery' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_logs" value="yes" <?php checked( $enable_logs, 'yes' ); ?> />
									<?php esc_html_e( 'Enable debug logging to file', 'brag-book-gallery' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Log Level', 'brag-book-gallery' ); ?></th>
							<td>
								<select name="log_level">
									<option value="error" <?php selected( $log_level, 'error' ); ?>><?php esc_html_e( 'Errors Only', 'brag-book-gallery' ); ?></option>
									<option value="warning" <?php selected( $log_level, 'warning' ); ?>><?php esc_html_e( 'Warnings & Errors', 'brag-book-gallery' ); ?></option>
									<option value="info" <?php selected( $log_level, 'info' ); ?>><?php esc_html_e( 'Info, Warnings & Errors', 'brag-book-gallery' ); ?></option>
									<option value="debug" <?php selected( $log_level, 'debug' ); ?>><?php esc_html_e( 'All Messages (Debug)', 'brag-book-gallery' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" name="save_log_settings" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Log File Contents -->
			<div class="brag-book-gallery-card">
				<h3><?php esc_html_e( 'Debug Log File', 'brag-book-gallery' ); ?></h3>
				<?php if ( file_exists( $log_file ) && is_readable( $log_file ) ) : ?>
					<?php
					$log_contents = file_get_contents( $log_file );
					$file_size = filesize( $log_file );
					$file_modified = filemtime( $log_file );

					// If file is too large, get last 100 lines
					if ( $file_size > 1048576 ) { // 1MB
						$lines = file( $log_file );
						$last_lines = array_slice( $lines, -100 );
						$log_contents = implode( '', $last_lines );
						$truncated = true;
					} else {
						$truncated = false;
					}
					?>
					<div class="log-file-info">
						<p>
							<strong><?php esc_html_e( 'File Path:', 'brag-book-gallery' ); ?></strong>
							<code><?php echo esc_html( $log_file ); ?></code>
						</p>
						<p>
							<strong><?php esc_html_e( 'File Size:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( size_format( $file_size ) ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Last Modified:', 'brag-book-gallery' ); ?></strong>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $file_modified ) ); ?>
						</p>
						<?php if ( $truncated ) : ?>
							<p class="notice notice-warning inline">
								<?php esc_html_e( 'Log file is large. Showing last 100 lines only.', 'brag-book-gallery' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="log-file-actions">
						<form method="post" style="display: inline;">
							<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
							<button type="submit" name="clear_debug_log" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the debug log?', 'brag-book-gallery' ); ?>');">
								<?php esc_html_e( 'Clear Log', 'brag-book-gallery' ); ?>
							</button>
						</form>
						<a href="<?php echo esc_url( add_query_arg( array( 'download_log' => 1, 'nonce' => wp_create_nonce( 'download_log' ) ) ) ); ?>" class="button">
							<?php esc_html_e( 'Download Log', 'brag-book-gallery' ); ?>
						</a>
					</div>

					<div class="log-file-contents">
						<label for="debug-log-contents"><?php esc_html_e( 'Log Contents:', 'brag-book-gallery' ); ?></label>
						<textarea id="debug-log-contents" readonly rows="20" style="width: 100%; font-family: monospace; font-size: 12px; background: #f0f0f0; padding: 10px;"><?php echo esc_textarea( $log_contents ); ?></textarea>
					</div>
				<?php else : ?>
					<p class="notice notice-info inline">
						<?php esc_html_e( 'No debug log file found. Enable debug logging above to start capturing debug information.', 'brag-book-gallery' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Log file location:', 'brag-book-gallery' ); ?></strong>
						<code><?php echo esc_html( $log_file ); ?></code>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.brag-book-gallery-debug-logs .log-file-info {
				background: #f0f0f1;
				padding: 15px;
				margin: 15px 0;
				border-left: 4px solid #2271b1;
			}
			.brag-book-gallery-debug-logs .log-file-info p {
				margin: 5px 0;
			}
			.brag-book-gallery-debug-logs .log-file-actions {
				margin: 15px 0;
			}
			.brag-book-gallery-debug-logs .log-file-actions .button {
				margin-right: 10px;
			}
			.brag-book-gallery-debug-logs .log-file-contents {
				margin-top: 15px;
			}
			.brag-book-gallery-debug-logs .log-file-contents label {
				display: block;
				margin-bottom: 5px;
				font-weight: 600;
			}
		</style>
		<?php
	}

	private function render_database_info(): void {
		global $wpdb;

		// Get option sizes
		$options = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) as size
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'
			ORDER BY size DESC
			LIMIT 10"
		);

		// Get post counts
		$gallery_count = wp_count_posts( 'brag_gallery' )->publish ?? 0;
		$draft_count = wp_count_posts( 'brag_gallery' )->draft ?? 0;
		?>
		<h3><?php esc_html_e( 'Plugin Options', 'brag-book-gallery' ); ?></h3>
		<table class="widefat striped">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Option Name', 'brag-book-gallery' ); ?></th>
				<th><?php esc_html_e( 'Size', 'brag-book-gallery' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $options as $option ) : ?>
				<tr>
					<td><code><?php echo esc_html( $option->option_name ); ?></code></td>
					<td><?php echo esc_html( size_format( $option->size ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $gallery_count > 0 || $draft_count > 0 ) : ?>
			<h3><?php esc_html_e( 'Gallery Data', 'brag-book-gallery' ); ?></h3>
			<table class="widefat striped">
				<tbody>
				<tr>
					<th><?php esc_html_e( 'Published Galleries', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $gallery_count ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Draft Galleries', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( $draft_count ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Categories', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( wp_count_terms( 'brag_category' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( wp_count_terms( 'brag_procedure' ) ); ?></td>
				</tr>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function save_debug_settings(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_debug_action', 'debug_nonce' ) ) {
			return;
		}

		// Save logging settings
		$enable_logs = isset( $_POST['enable_logs'] ) && $_POST['enable_logs'] === 'yes' ? 'yes' : 'no';
		update_option( 'brag_book_gallery_enable_logs', $enable_logs );

		if ( isset( $_POST['log_level'] ) ) {
			$valid_levels = array( 'error', 'warning', 'info', 'debug' );
			$log_level = in_array( $_POST['log_level'], $valid_levels, true )
				? $_POST['log_level']
				: 'error';
			update_option( 'brag_book_gallery_log_level', $log_level );
		}

		$this->add_notice( __( 'Debug settings saved successfully.', 'brag-book-gallery' ) );
		settings_errors( $this->page_slug );
	}

	private function clear_cache(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_debug_action', 'debug_nonce' ) ) {
			return;
		}

		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_brag_book_gallery_%'
			OR option_name LIKE '_transient_timeout_brag_book_gallery_%'"
		);

		$this->add_notice( __( 'Cache cleared successfully.', 'brag-book-gallery' ) );
		settings_errors( $this->page_slug );
	}

	private function clear_logs(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_debug_action', 'debug_nonce' ) ) {
			return;
		}

		$log_file = $this->get_log_file_path();
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
			$this->add_notice( __( 'Logs cleared successfully.', 'brag-book-gallery' ) );
		}

		settings_errors( $this->page_slug );
	}

	private function save_log_settings(): void {
		if ( ! isset( $_POST['debug_nonce'] ) || ! wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) ) {
			$this->add_notice( __( 'Security check failed.', 'brag-book-gallery' ), 'error' );
			return;
		}

		$enable_logs = isset( $_POST['enable_logs'] ) && $_POST['enable_logs'] === 'yes' ? 'yes' : 'no';
		$log_level = isset( $_POST['log_level'] ) ? sanitize_text_field( $_POST['log_level'] ) : 'error';

		// Validate log level
		$valid_levels = array( 'error', 'warning', 'info', 'debug' );
		if ( ! in_array( $log_level, $valid_levels, true ) ) {
			$log_level = 'error';
		}

		update_option( 'brag_book_gallery_enable_logs', $enable_logs );
		update_option( 'brag_book_gallery_log_level', $log_level );

		$this->add_notice( __( 'Log settings saved successfully.', 'brag-book-gallery' ) );
	}

	private function clear_debug_log(): void {
		if ( ! isset( $_POST['debug_nonce'] ) || ! wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) ) {
			$this->add_notice( __( 'Security check failed.', 'brag-book-gallery' ), 'error' );
			return;
		}

		$log_file = $this->get_log_file_path();
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
			$this->add_notice( __( 'Debug log cleared successfully.', 'brag-book-gallery' ) );
		} else {
			$this->add_notice( __( 'Debug log file not found.', 'brag-book-gallery' ), 'error' );
		}
	}

	private function download_debug_log(): void {
		$log_file = $this->get_log_file_path();

		if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
			wp_die( esc_html__( 'Log file not found or not readable.', 'brag-book-gallery' ) );
		}

		$filename = 'brag-book-gallery-debug-' . date( 'Y-m-d-H-i-s' ) . '.log';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		header( 'Pragma: public' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Expires: 0' );

		readfile( $log_file );
		exit;
	}

	private function get_log_file_path( string $type = 'error' ): string {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		// Create directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess to protect log files
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, 'Deny from all' );
			}
		}

		$filename = ( 'api' === $type ) ? 'api.log' : 'error.log';

		return $log_dir . '/' . $filename;
	}

	/**
	 * Handle export/import early
	 *
	 * Processes export requests before any output is sent. Delegates to the
	 * export_import component.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_export_import_early(): void {
		// Only process on our settings page.
		if ( ! isset( $_GET['page'] ) || 'brag-book-gallery-debug' !== $_GET['page'] ) {
			return;
		}

		// Handle export.
		if ( isset( $_POST['export_settings'] ) && isset( $_POST['debug_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) && current_user_can( 'manage_options' ) ) {
				$this->export_import->export_settings();
				exit; // Stop execution after export.
			}
		}

		// Handle import.
		if ( isset( $_POST['import_settings'] ) && isset( $_FILES['import_file'] ) && isset( $_POST['debug_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) && current_user_can( 'manage_options' ) ) {
				$result = $this->export_import->import_settings( $_FILES['import_file'] );
				if ( true === $result ) {
					$this->add_notice( __( 'Settings imported successfully.', 'brag-book-gallery' ) );
				} else {
					$this->add_notice( $result, 'error' );
				}
			}
		}
	}

	public function handle_save_debug_settings(): void {
		$this->verify_ajax_request();

		// Get and sanitize settings
		$debug_mode  = isset( $_POST['debug_mode'] ) && $_POST['debug_mode'] === '1';
		$api_logging = isset( $_POST['api_logging'] ) && $_POST['api_logging'] === '1';
		$wp_debug    = isset( $_POST['wp_debug'] ) && $_POST['wp_debug'] === '1';
		$log_level   = sanitize_text_field( $_POST['log_level'] ?? 'error' );

		// Validate log level
		$valid_levels = [ 'error', 'warning', 'info', 'debug' ];
		if ( ! in_array( $log_level, $valid_levels, true ) ) {
			$log_level = 'error';
		}

		// Save settings
		update_option( 'brag_book_gallery_debug_mode', $debug_mode );
		update_option( 'brag_book_gallery_api_logging', $api_logging );
		update_option( 'brag_book_gallery_log_level', $log_level );

		// Build success message
		$messages   = [];
		$messages[] = __( 'Debug settings saved successfully.', 'brag-book-gallery' );

		wp_send_json_success( [ 'message' => implode( ' ', $messages ) ] );
	}

	/**
	 * Handle get error log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_get_error_log(): void {
		$this->verify_ajax_request();

		$log_content = $this->logs->get_error_log();
		wp_send_json_success( array( 'log' => $log_content ) );
	}

	/**
	 * Handle clear error log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_clear_error_log(): void {
		$this->verify_ajax_request();

		$result = $this->logs->clear_log( 'error' );
		if ( $result ) {
			wp_send_json_success( __( 'Error log cleared successfully.', 'brag-book-gallery' ) );
		} else {
			wp_send_json_error( __( 'Error log file not found.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * Handle download error log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_download_error_log(): void {
		$this->verify_ajax_request();

		$this->logs->download_log( 'error' );
	}

	/**
	 * Handle get API log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_get_api_log(): void {
		$this->verify_ajax_request();

		$log_content = $this->logs->get_api_log();
		wp_send_json_success( array( 'log' => $log_content ) );
	}

	/**
	 * Handle clear API log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_clear_api_log(): void {
		$this->verify_ajax_request();

		$result = $this->logs->clear_log( 'api' );
		if ( $result ) {
			wp_send_json_success( __( 'API log cleared successfully.', 'brag-book-gallery' ) );
		} else {
			wp_send_json_error( __( 'API log file not found.', 'brag-book-gallery' ) );
		}
	}

	/**
	 * Handle download API log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_download_api_log(): void {
		$this->verify_ajax_request();

		$this->logs->download_log( 'api' );
	}

	public function handle_export_system_info(): void {
		$this->verify_ajax_request();

		$system_info = $this->get_complete_system_info();
		$filename = 'brag-book-gallery-system-info-' . date( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $system_info ) );
		echo $system_info;
		exit;
	}

	private function get_error_log(): string {
		$log_file = $this->get_log_file_path( 'error' );

		if ( ! file_exists( $log_file ) ) {
			return __( 'No error log file found.', 'brag-book-gallery' );
		}

		$lines = $this->tail_file( $log_file, 100 );

		if ( empty( $lines ) ) {
			return __( 'No errors logged yet.', 'brag-book-gallery' );
		}

		return implode( "\n", $lines );
	}

	private function get_api_log(): string {
		$log_file = $this->get_log_file_path( 'api' );

		if ( ! file_exists( $log_file ) ) {
			return __( 'No API log file found.', 'brag-book-gallery' );
		}

		$lines = $this->tail_file( $log_file, 100 );

		if ( empty( $lines ) ) {
			return __( 'No API requests logged yet.', 'brag-book-gallery' );
		}

		return implode( "\n", $lines );
	}

	private function tail_file( string $file, int $lines = 100 ): array {
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return [];
		}

		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return [];
		}

		$line_count = 0;
		$cursor = -1;
		$content = [];

		fseek( $handle, $cursor, SEEK_END );
		$char = fgetc( $handle );

		while ( $char !== false && $line_count < $lines ) {
			if ( $char === "\n" ) {
				$line_count++;
			}
			$content[] = $char;
			fseek( $handle, --$cursor, SEEK_END );
			$char = fgetc( $handle );
		}

		fclose( $handle );

		// Reverse and join the content
		$content = array_reverse( $content );
		$text = implode( '', $content );

		// Split into lines and return
		return array_filter( explode( "\n", $text ) );
	}

	private function get_complete_system_info(): string {
		global $wpdb;

		$info = [];
		$info[] = '=== BRAG book Gallery System Information ===';
		$info[] = 'Generated: ' . current_time( 'mysql' );
		$info[] = '';

		// Plugin Information
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$info[] = '== Plugin Information ==';
		$info[] = 'Version: ' . ( $plugin_data['Version'] ?? 'Unknown' );
		$info[] = 'PHP Version Required: ' . ( $plugin_data['RequiresPHP'] ?? 'Unknown' );
		$info[] = 'WP Version Required: ' . ( $plugin_data['RequiresWP'] ?? 'Unknown' );
		$info[] = '';

		// WordPress Information
		$info[] = '== WordPress Information ==';
		$info[] = 'Version: ' . get_bloginfo( 'version' );
		$info[] = 'Language: ' . get_locale();
		$info[] = 'Multisite: ' . ( is_multisite() ? 'Yes' : 'No' );
		$info[] = 'Home URL: ' . home_url();
		$info[] = 'Site URL: ' . site_url();
		$info[] = 'Admin Email: ' . get_option( 'admin_email' );
		$info[] = '';

		// Server Information
		$info[] = '== Server Information ==';
		$info[] = 'PHP Version: ' . phpversion();
		$info[] = 'MySQL Version: ' . $wpdb->db_version();
		$info[] = 'Server Software: ' . ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' );
		$info[] = 'Max Execution Time: ' . ini_get( 'max_execution_time' );
		$info[] = 'Memory Limit: ' . ini_get( 'memory_limit' );
		$info[] = 'Upload Max Filesize: ' . ini_get( 'upload_max_filesize' );
		$info[] = 'Post Max Size: ' . ini_get( 'post_max_size' );
		$info[] = '';

		// Plugin Settings
		$info[] = '== Plugin Settings ==';
		$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
		$info[] = 'API Connections: ' . count( $api_tokens );
		$info[] = 'Debug Mode: ' . ( get_option( 'brag_book_gallery_debug_mode' ) ? 'Enabled' : 'Disabled' );
		$info[] = 'API Logging: ' . ( get_option( 'brag_book_gallery_api_logging' ) ? 'Enabled' : 'Disabled' );
		$info[] = '';

		// Active Theme
		$theme = wp_get_theme();
		$info[] = '== Active Theme ==';
		$info[] = 'Name: ' . $theme->get( 'Name' );
		$info[] = 'Version: ' . $theme->get( 'Version' );
		$info[] = 'Author: ' . $theme->get( 'Author' );
		$info[] = '';

		// Active Plugins
		$info[] = '== Active Plugins ==';
		$active_plugins = get_option( 'active_plugins', [] );
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$info[] = $plugin_data['Name'] . ' v' . $plugin_data['Version'] . ' by ' . $plugin_data['Author'];
		}

		return implode( "\n", $info );
	}

	/**
	 * Handle factory reset AJAX request
	 *
	 * Verifies nonce and permissions, then delegates to the factory reset component.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_factory_reset(): void {
		// Verify nonce - using the admin nonce that's actually generated.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_admin' ) ) {
			wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'brag-book-gallery' ) );
			return;
		}

		// Double-check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'brag-book-gallery' ) );
			return;
		}

		// Execute reset via component.
		$result = $this->factory_reset->execute_reset();

		// Send response.
		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'  => $result['message'],
				'redirect' => $result['redirect'] ?? '',
			) );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	private function render_api_test_panel(): void {
		// Enqueue CodeMirror for JSON display
		wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		// Check if API is configured
		$has_api_config = ! empty( $api_tokens ) && ! empty( $website_property_ids );

		?>
		<div class="brag-book-gallery-section">
			<h3><?php esc_html_e( 'API Endpoint Testing', 'brag-book-gallery' ); ?></h3>

			<?php if ( ! $has_api_config ) : ?>
				<div class="brag-book-gallery-notice brag-book-gallery-notice--warning">
					<p>
						<?php esc_html_e( 'Please configure your API settings first.', 'brag-book-gallery' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>">
							<?php esc_html_e( 'Go to API Settings', 'brag-book-gallery' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<p class="description">
					<?php esc_html_e( 'Test various BRAG book API endpoints to verify connectivity and data retrieval.', 'brag-book-gallery' ); ?>
				</p>

				<?php $this->render_api_test_content( $api_tokens, $website_property_ids ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_api_test_content( array $api_tokens, array $website_property_ids ): void {
		// Get saved account info which contains organization names
		$account_info = get_option( 'brag_book_gallery_account_info', array() );
		$connections_info = [];

		foreach ( $api_tokens as $index => $token ) {
			$property_id = $website_property_ids[$index] ?? null;
			if ( ! empty( $token ) && ! empty( $property_id ) ) {
				// Get organization name from saved account info
				$org_name = 'Unknown';
				if ( ! empty( $account_info[$index]['organization']['name'] ) ) {
					$org_name = $account_info[$index]['organization']['name'];
				}

				$connections_info[$index] = [
					'token' => $token,
					'property_id' => $property_id,
					'organization' => $org_name,
					'valid' => ! empty( $token ) && ! empty( $property_id ),
				];
			}
		}
		?>
		<div class="api-test-config">
			<div class="config-info">
				<strong><?php esc_html_e( 'API Connections:', 'brag-book-gallery' ); ?></strong>
				<?php if ( count( $api_tokens ) > 1 ) : ?>
					<p class="description"><?php esc_html_e( 'Multiple connections configured. Tests will use all connections.', 'brag-book-gallery' ); ?></p>
				<?php endif; ?>
				<ul>
					<?php foreach ( $connections_info as $index => $info ) : ?>
						<li>
							<?php
							$status_icon = $info['valid'] ? '<span style="color: #00a32a;">●</span>' : '<span style="color: #d63638;">●</span>';
							echo wp_kses_post( sprintf(
								__( '%s Connection %d: <strong>%s</strong> | Token %s... | Property ID: %s', 'brag-book-gallery' ),
								$status_icon,
								$index + 1,
								esc_html( $info['organization'] ),
								esc_html( substr( $info['token'], 0, 10 ) ),
								esc_html( $info['property_id'] )
							) );
							?>
						</li>
					<?php endforeach; ?>
				</ul>
				<p><strong><?php esc_html_e( 'Base URL:', 'brag-book-gallery' ); ?></strong> <code>https://app.bragbookgallery.com</code></p>
			</div>
			<div class="test-parameters">
				<strong><?php esc_html_e( 'Test Parameters:', 'brag-book-gallery' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Configure optional parameters for testing different scenarios.', 'brag-book-gallery' ); ?></p>
				<table class="form-table" style="margin-top: 10px;">
					<tr>
						<th style="padding: 5px; width: 150px;">
							<label for="test-base-url"><?php esc_html_e( 'Base URL:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="text" id="test-base-url" value="https://app.bragbookgallery.com" class="input-field regular-text" style="width: 350px;">
							<span class="description"><?php esc_html_e( 'API base URL (e.g., https://dev.bragbookgallery.com for dev)', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding: 5px;">
							<label for="test-api-token"><?php esc_html_e( 'API Token:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="text" id="test-api-token" placeholder="<?php echo esc_attr( ! empty( $api_tokens[0] ) ? substr( $api_tokens[0], 0, 20 ) . '...' : 'Enter API token' ); ?>" class="input-field regular-text" style="width: 350px;">
							<span class="description"><?php esc_html_e( 'API token for Bearer authentication (used by v2 endpoints)', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding: 5px;">
							<label for="test-website-property-id"><?php esc_html_e( 'Website Property ID:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="number" id="test-website-property-id" placeholder="<?php echo esc_attr( ! empty( $website_property_ids[0] ) ? $website_property_ids[0] : '84' ); ?>" class="input-field regular-text" style="width: 150px;">
							<span class="description"><?php esc_html_e( 'Website Property ID for API requests', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding: 5px;">
							<label for="test-procedure-id"><?php esc_html_e( 'Procedure ID:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="number" id="test-procedure-id" placeholder="3405" class="input-field regular-text" style="width: 150px;">
							<span class="description"><?php esc_html_e( 'Used by: Carousel, Cases, Filters (default: 3405)', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding: 5px;">
							<label for="test-member-id"><?php esc_html_e( 'Member ID:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="number" id="test-member-id" placeholder="129" class="input-field regular-text" style="width: 150px;">
							<span class="description"><?php esc_html_e( 'Used by: Cases, Filters (default: 129)', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding: 5px;">
							<label for="test-page"><?php esc_html_e( 'Page:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="number" id="test-page" placeholder="1" value="1" class="input-field regular-text" style="width: 150px;">
							<span class="description"><?php esc_html_e( 'Page number for pagination (default: 1)', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
					<tr>
						<th style="padding: 5px;">
							<label for="test-limit"><?php esc_html_e( 'Limit:', 'brag-book-gallery' ); ?></label>
						</th>
						<td style="padding: 5px;">
							<input type="number" id="test-limit" placeholder="20" value="20" class="input-field regular-text" style="width: 150px;">
							<span class="description"><?php esc_html_e( 'Items per page (default: 20)', 'brag-book-gallery' ); ?></span>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="api-test-container">
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Endpoint', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Method', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Action', 'brag-book-gallery' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<!-- Sidebar Endpoint -->
				<tr>
					<td><code>/api/plugin/combine/sidebar</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td><?php esc_html_e( 'Get categories and procedures with case counts', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="sidebar"
						        data-method="POST"
						        data-url="/api/plugin/combine/sidebar">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Cases Endpoint -->
				<tr>
					<td><code>/api/plugin/combine/cases</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td><?php esc_html_e( 'Get paginated case listings', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="cases"
						        data-method="POST"
						        data-url="/api/plugin/combine/cases">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Carousel Endpoint -->
				<tr>
					<td><code>/api/plugin/carousel</code></td>
					<td><span class="method-badge method-get">GET</span></td>
					<td><?php esc_html_e( 'Get carousel data (requires procedureId)', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="carousel"
						        data-method="GET"
						        data-url="/api/plugin/carousel">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Filters Endpoint -->
				<tr>
					<td><code>/api/plugin/combine/filters</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td><?php esc_html_e( 'Get available filter options', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="filters"
						        data-method="POST"
						        data-url="/api/plugin/combine/filters">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Favorites List -->
				<tr>
					<td><code>/api/plugin/combine/favorites/list</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td><?php esc_html_e( 'Get user\'s favorite cases', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="favorites-list"
						        data-method="POST"
						        data-url="/api/plugin/combine/favorites/list">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Sitemap -->
				<tr>
					<td><code>/api/plugin/sitemap</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td><?php esc_html_e( 'Generate sitemap data', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="sitemap"
						        data-method="POST"
						        data-url="/api/plugin/sitemap">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Single Case -->
				<tr>
					<td><code>/api/plugin/combine/cases/{id}</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td>
						<?php esc_html_e( 'Get specific case details', 'brag-book-gallery' ); ?>
						<input type="number" id="case-id-input" placeholder="Case ID" class="small-text" style="margin-left: 10px;">
					</td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="single-case"
						        data-method="POST"
						        data-url="/api/plugin/combine/cases/"
						        data-needs-id="true">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Consultations -->
				<tr>
					<td><code>/api/plugin/consultations</code></td>
					<td><span class="method-badge method-post">POST</span></td>
					<td>
						<?php esc_html_e( 'Submit consultation request (Test with sample data)', 'brag-book-gallery' ); ?>
					</td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="consultations"
						        data-method="POST"
						        data-url="/api/plugin/consultations"
						        data-test-consultation="true">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>
				</tbody>
			</table>
		</div>

		<!-- v2 API Endpoints -->
		<h3 style="margin-top: 30px;"><?php esc_html_e( 'v2 API Endpoints', 'brag-book-gallery' ); ?></h3>
		<div class="api-test-container">
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Endpoint', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Method', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
					<th><?php esc_html_e( 'Action', 'brag-book-gallery' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<!-- Cases Endpoint (v2) -->
				<tr>
					<td><code>/api/plugin/v2/cases/</code></td>
					<td><span class="method-badge method-get">GET</span></td>
					<td><?php esc_html_e( 'Get paginated case listings (v2 - requires procedureId)', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="cases-v2"
						        data-method="GET"
						        data-url="/api/plugin/v2/cases/">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Single Case (v2) -->
				<tr>
					<td><code>/api/plugin/v2/cases/{id}</code></td>
					<td><span class="method-badge method-get">GET</span></td>
					<td>
						<?php esc_html_e( 'Get specific case details (v2 - Bearer auth)', 'brag-book-gallery' ); ?>
						<input type="number" id="case-id-v2-input" placeholder="Case ID" class="small-text" style="margin-left: 10px;">
					</td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="single-case-v2"
						        data-method="GET"
						        data-url="/api/plugin/v2/cases/"
						        data-needs-case-id-v2="true">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				<!-- Token Validation -->
				<tr>
					<td><code>/api/plugin/v2/validation/token</code></td>
					<td><span class="method-badge method-get">GET</span></td>
					<td><?php esc_html_e( 'Validate API token (Bearer auth)', 'brag-book-gallery' ); ?></td>
					<td>
						<button class="button button-secondary test-endpoint-btn"
						        data-endpoint="validate-token"
						        data-method="GET"
						        data-url="/api/plugin/v2/validation/token">
							<?php esc_html_e( 'Test', 'brag-book-gallery' ); ?>
						</button>
					</td>
				</tr>

				</tbody>
			</table>
		</div>

		<!-- Response Display Area -->
		<div class="api-response-container" style="display: none;">
			<h3><?php esc_html_e( 'API Response', 'brag-book-gallery' ); ?></h3>
			<div class="response-header">
				<span class="response-status"></span>
				<span class="response-time"></span>
				<button class="button button-small copy-response-btn">
					<?php esc_html_e( 'Copy Response', 'brag-book-gallery' ); ?>
				</button>
				<button class="button button-small clear-response-btn">
					<?php esc_html_e( 'Clear', 'brag-book-gallery' ); ?>
				</button>
			</div>
			<div class="request-details">
				<h4><?php esc_html_e( 'Request Details:', 'brag-book-gallery' ); ?></h4>
				<textarea class="api-request-content" readonly rows="10"></textarea>
			</div>
			<div class="response-details">
				<h4><?php esc_html_e( 'Response:', 'brag-book-gallery' ); ?></h4>
				<textarea class="api-response-content" readonly rows="15"></textarea>
			</div>
		</div>

		<?php $this->render_api_test_styles_and_scripts( $api_tokens, $website_property_ids ); ?>
		<?php
	}

	private function render_api_test_styles_and_scripts( array $api_tokens, array $website_property_ids ): void {
		?>
		<style>
			.api-test-config {
				background: var(--slate-100);
				border: 1px solid var(--slate-200);
				padding: var(--space-6);
				border-radius: 0.25rem;
				margin-block: var(--space-6);
				display: flex;
				gap: var(--space-6);
			}
			.config-info, .test-parameters {
				flex: 1;
			}
			.config-info ul {
				margin: 10px 0 0 20px;
			}
			.config-info code {
				background: #fff;
				padding: 2px 5px;
				border-radius: 3px;
			}
			.test-parameters {
				border-left: 1px solid #c3c4c7;
				padding-left: 30px;
			}
			.test-parameters .description {
				margin: 10px 0;
			}
			.method-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.method-get {
				background: #00a32a;
				color: white;
			}
			.method-post {
				background: #2271b1;
				color: white;
			}
			.api-test-container {
				margin: 20px 0;
			}
			.api-response-container {
				margin-top: 30px;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px;
			}
			.response-header {
				display: flex;
				align-items: center;
				gap: 15px;
				margin-bottom: 15px;
				padding-bottom: 15px;
				border-bottom: 1px solid #dcdcde;
			}
			.response-status {
				font-weight: 600;
			}
			.response-status.success {
				color: #00a32a;
			}
			.response-status.error {
				color: #d63638;
			}
			.response-time {
				color: #646970;
				font-size: 13px;
			}
			.api-response-content, .api-request-content {
				width: 100%;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
				font-size: 13px;
				line-height: 1.6;
				resize: vertical;
			}

			/* CodeMirror will handle the styling, but we need some basic fallback */
			.CodeMirror {
				border: 1px solid #dcdcde;
				border-radius: 4px;
				max-height: 400px;
			}

			.CodeMirror-scroll {
				max-height: 400px;
			}
			.request-details, .response-details {
				margin-top: 20px;
			}
			.request-details h4, .response-details h4 {
				margin: 0 0 10px 0;
				color: #1d2327;
				font-size: 14px;
			}
			.test-endpoint-btn:disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}
			.spinner {
				display: inline-block;
				margin-left: 5px;
			}
		</style>

		<?php
		// Include the JavaScript from the original API test file
		// We need to access the handle_api_test method, so we'll include the AJAX handler
		$this->register_ajax_action( 'brag_book_test_api', array( $this, 'handle_api_test' ) );
		?>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Get all API tokens and property IDs as arrays
				const apiTokens = <?php echo wp_json_encode( array_values( array_filter( $api_tokens ) ) ); ?>;
				const websitePropertyIds = <?php echo wp_json_encode( array_values( array_filter( array_map( 'intval', $website_property_ids ) ) ) ); ?>;
				const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
				const nonce = '<?php echo wp_create_nonce( 'brag_book_api_test' ); ?>';

				// Check if we have valid tokens
				if (!apiTokens || apiTokens.length === 0) {
					console.error('No valid API tokens found. Please check your API settings.');
				}

				// Helper function to get base URL from input
				const getBaseUrl = () => {
					const baseUrlInput = document.getElementById('test-base-url');
					return baseUrlInput ? baseUrlInput.value.trim() : 'https://app.bragbookgallery.com';
				};

				const getWebsitePropertyId = () => {
					const propertyIdInput = document.getElementById('test-website-property-id');
					if (propertyIdInput && propertyIdInput.value) {
						return propertyIdInput.value;
					}
					// Fallback to configured value or default
					return websitePropertyIds[0] ? websitePropertyIds[0].toString() : '84';
				};

				const getApiToken = () => {
					const tokenInput = document.getElementById('test-api-token');
					if (tokenInput && tokenInput.value) {
						return tokenInput.value;
					}
					// Fallback to configured value
					return apiTokens[0] || '';
				};

				// Helper functions
				const showElement = (selector) => {
					const el = document.querySelector(selector);
					if (el) el.style.display = 'block';
				};

				const hideElement = (selector) => {
					const el = document.querySelector(selector);
					if (el) el.style.display = 'none';
				};

				const setText = (selector, text) => {
					const el = document.querySelector(selector);
					if (el) {
						if (el.tagName.toLowerCase() === 'textarea') {
							el.value = text;
							// Trigger CodeMirror refresh if it exists
							if (el.codeMirrorInstance) {
								el.codeMirrorInstance.setValue(text);
								el.codeMirrorInstance.refresh();
							}
						} else {
							el.textContent = text;
						}
					}
				};

				const addClass = (selector, className) => {
					const el = document.querySelector(selector);
					if (el) el.classList.add(className);
				};

				const removeClass = (selector, className) => {
					const el = document.querySelector(selector);
					if (el) el.classList.remove(className);
				};

				// Initialize CodeMirror editors
				let requestEditor = null;
				let responseEditor = null;

				const initializeCodeMirror = () => {
					// Check if wp.codeEditor is available
					if (typeof wp !== 'undefined' && wp.codeEditor) {
						const editorSettings = wp.codeEditor.defaultSettings ? wp.codeEditor.defaultSettings : {};
						editorSettings.codemirror = {
							...editorSettings.codemirror,
							mode: 'application/json',
							lineNumbers: true,
							lineWrapping: true,
							readOnly: true,
							foldGutter: true,
							gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
							theme: 'default'
						};

						// Initialize request details editor
						const requestTextarea = document.querySelector('.api-request-content');
						if (requestTextarea && !requestEditor) {
							requestEditor = wp.codeEditor.initialize(requestTextarea, editorSettings);
							requestTextarea.codeMirrorInstance = requestEditor.codemirror;
						}

						// Initialize response editor
						const responseTextarea = document.querySelector('.api-response-content');
						if (responseTextarea && !responseEditor) {
							responseEditor = wp.codeEditor.initialize(responseTextarea, editorSettings);
							responseTextarea.codeMirrorInstance = responseEditor.codemirror;
						}
					}
				};

				// Format JSON for display
				const formatJSON = (json) => {
					if (typeof json === 'string') {
						try {
							json = JSON.parse(json);
						} catch (e) {
							return json; // Return as-is if not valid JSON
						}
					}
					return JSON.stringify(json, null, 2);
				};

				// Test endpoint button click
				document.querySelectorAll('.test-endpoint-btn').forEach(btn => {
					btn.addEventListener('click', function() {
						const endpoint = this.dataset.endpoint;
						const method = this.dataset.method;
						const baseUrl = getBaseUrl();
						let url = baseUrl + this.dataset.url;
						const needsId = this.dataset.needsId;
						const needsCaseIdV2 = this.dataset.needsCaseIdV2;

						// Check if case ID is needed (v1 endpoint)
						if (needsId) {
							const caseIdInput = document.getElementById('case-id-input');
							const caseId = caseIdInput ? caseIdInput.value : '';
							if (!caseId) {
								alert('Please enter a Case ID');
								return;
							}
							url += caseId;
						}

						// Check if case ID is needed (v2 endpoint)
						if (needsCaseIdV2) {
							const caseIdInput = document.getElementById('case-id-v2-input');
							const caseId = caseIdInput ? caseIdInput.value : '';
							if (!caseId) {
								alert('Please enter a Case ID for v2 endpoint');
								return;
							}
							url += caseId;
						}

						// Disable button and show loading
						this.disabled = true;
						const originalText = this.textContent;
						this.innerHTML = 'Testing... <span class="spinner is-active"></span>';

						// Clear previous response
						hideElement('.api-response-container');
						setText('.api-response-content', '');
						setText('.api-request-content', '');

						// Start timer
						const startTime = Date.now();

						// Build request body for POST requests
						let requestBody = null;
						let requestHeaders = {
							'Accept': 'application/json'
						};

						if (method === 'POST') {
							requestHeaders['Content-Type'] = 'application/json';

							// Build request body with arrays
							requestBody = {
								apiTokens: apiTokens,
								websitePropertyIds: websitePropertyIds
							};

							// Get optional parameters from inputs
							const procedureInput = document.getElementById('test-procedure-id');
							const memberInput = document.getElementById('test-member-id');
							const testProcedureId = procedureInput ? (procedureInput.value || null) : null;
							const testMemberId = memberInput ? (memberInput.value || null) : null;

							// Add endpoint-specific parameters
							switch(endpoint) {
								case 'sidebar':
									const validTokens = apiTokens.filter(token => token && token.length > 0);
									if (validTokens.length === 0) {
										alert('No valid API tokens found. Please check your API settings.');
										this.disabled = false;
										this.textContent = originalText;
										return;
									}
									requestBody = {
										apiTokens: validTokens
									};
									break;

								case 'cases':
									requestBody.count = 1;
									if (testProcedureId) {
										requestBody.procedureIds = [parseInt(testProcedureId)];
									}
									if (testMemberId) {
										requestBody.memberId = parseInt(testMemberId);
									}
									break;

								case 'single-case':
									requestBody.procedureIds = [parseInt(testProcedureId || 6851)];
									if (testMemberId) {
										requestBody.memberId = parseInt(testMemberId);
									}
									break;

								case 'filters':
									requestBody.procedureIds = [parseInt(testProcedureId || 6851)];
									break;

								case 'consultations':
									url += '?apiToken=' + encodeURIComponent(getApiToken()) +
									       '&websitepropertyId=' + encodeURIComponent(getWebsitePropertyId());
									requestBody = {
										email: "test@example.com",
										phone: "(555) 123-4567",
										name: "Test User",
										details: "This is a test consultation submission from the API testing page."
									};
									break;
							}
						} else {
							// For GET requests, handle different endpoint types
							if (endpoint === 'validate-token') {
								// Token validation uses Bearer auth and websitePropertyId query param
								const params = new URLSearchParams({
									websitePropertyId: getWebsitePropertyId()
								});
								url += '?' + params.toString();
								// Add Bearer token to headers
								const token = getApiToken();
								requestHeaders['Authorization'] = 'Bearer ' + token;
								console.log('Token Validation GET Request URL:', url);
								console.log('Token Validation will use Bearer auth with token:', token.substring(0, 10) + '...');
							} else if (endpoint === 'cases-v2') {
								// v2 cases endpoint uses Bearer auth with websitePropertyId and procedureId
								const procedureInput = document.getElementById('test-procedure-id');
								const memberInput = document.getElementById('test-member-id');
								const pageInput = document.getElementById('test-page');
								const limitInput = document.getElementById('test-limit');

								const testProcedureId = procedureInput ? (procedureInput.value || '4168') : '4168';
								const testMemberId = memberInput ? memberInput.value : '';
								const testPage = pageInput ? (pageInput.value || '1') : '1';
								const testLimit = limitInput ? (limitInput.value || '20') : '20';

								const params = new URLSearchParams({
									websitePropertyId: getWebsitePropertyId(),
									procedureId: testProcedureId,
									page: testPage,
									limit: testLimit
								});

								// Add optional memberId if provided
								if (testMemberId) {
									params.append('memberId', testMemberId);
								}

								url += '?' + params.toString();
								// Add Bearer token to headers
								const token = getApiToken();
								requestHeaders['Authorization'] = 'Bearer ' + token;
								console.log('v2 Cases GET Request URL:', url);
								console.log('v2 Cases will use Bearer auth with token:', token.substring(0, 10) + '...');
							} else if (endpoint === 'case-detail-v2') {
								// v2 case detail endpoint uses Bearer auth with websitePropertyId
								const procedureInput = document.getElementById('test-procedure-id');
								const memberInput = document.getElementById('test-member-id');
								const testProcedureId = procedureInput ? procedureInput.value : '';
								const testMemberId = memberInput ? memberInput.value : '';

								const params = new URLSearchParams({
									websitePropertyId: getWebsitePropertyId()
								});

								// Add optional parameters if provided
								if (testProcedureId) {
									params.append('procedureId', testProcedureId);
								}
								if (testMemberId) {
									params.append('memberId', testMemberId);
								}

								url += '?' + params.toString();
								// Add Bearer token to headers
								const token = getApiToken();
								requestHeaders['Authorization'] = 'Bearer ' + token;
								console.log('v2 Case Detail GET Request URL:', url);
								console.log('v2 Case Detail will use Bearer auth with token:', token.substring(0, 10) + '...');
							} else {
								// For other GET requests (carousel), add params to URL
								const procedureInput = document.getElementById('test-procedure-id');
								const params = new URLSearchParams({
									websitePropertyId: getWebsitePropertyId(),
									start: '1',
									limit: '10',
									apiToken: getApiToken()
								});

								if (endpoint === 'carousel') {
									params.append('procedureId', procedureInput?.value || '3405');
								}

								url += '?' + params.toString();
							}
						}

						// Store request details for display
						const requestDetails = {
							url: url,
							method: method,
							headers: requestHeaders,
							body: requestBody
						};

						// Make the request through WordPress AJAX (server-side proxy)
						const button = this;

						// Prepare form data for AJAX
						const formData = new FormData();
						formData.append('action', 'brag_book_test_api');
						formData.append('nonce', nonce);
						formData.append('endpoint', endpoint);
						formData.append('method', method);
						formData.append('url', url);
						if (requestBody) {
							formData.append('body', JSON.stringify(requestBody));
						}

						// Make the request to WordPress AJAX
						fetch(ajaxUrl, {
							method: 'POST',
							body: formData
						})
							.then(response => response.json())
							.then(result => {
								const endTime = Date.now();
								const duration = endTime - startTime;

								// Show request details
								setText('.api-request-content', formatJSON(requestDetails));

								if (result.success) {
									const apiResponse = result.data;

									if (apiResponse.status >= 200 && apiResponse.status < 300) {
										// Show success response
										showElement('.api-response-container');
										setText('.response-status', `Success (${apiResponse.status})`);
										addClass('.response-status', 'success');
										removeClass('.response-status', 'error');
										setText('.response-time', duration + 'ms');
										setText('.api-response-content', formatJSON(apiResponse.body));
									} else {
										// Show error response from API
										showElement('.api-response-container');
										setText('.response-status', `Error: ${apiResponse.status}`);
										addClass('.response-status', 'error');
										removeClass('.response-status', 'success');
										setText('.response-time', duration + 'ms');
										setText('.api-response-content', formatJSON({
											status: apiResponse.status,
											body: apiResponse.body,
											headers: apiResponse.headers
										}));
									}
								} else {
									// Show WordPress/network error
									showElement('.api-response-container');
									setText('.response-status', 'Server Error');
									addClass('.response-status', 'error');
									removeClass('.response-status', 'success');
									setText('.response-time', duration + 'ms');
									setText('.api-response-content', formatJSON(result.data || {
										message: 'Failed to connect to API'
									}));
								}

								// Re-enable button
								button.disabled = false;
								button.textContent = originalText;
							})
							.catch(error => {
								const endTime = Date.now();
								const duration = endTime - startTime;

								// Show request details
								setText('.api-request-content', formatJSON(requestDetails));

								// Show error response
								showElement('.api-response-container');
								setText('.response-status', 'Network Error');
								addClass('.response-status', 'error');
								removeClass('.response-status', 'success');
								setText('.response-time', duration + 'ms');
								setText('.api-response-content', formatJSON({
									error: error.message,
									message: 'Could not connect to WordPress AJAX'
								}));

								// Re-enable button
								button.disabled = false;
								button.textContent = originalText;
							});
					});
				});

				// Copy response button
				document.querySelector('.copy-response-btn')?.addEventListener('click', function() {
					const responseContent = document.querySelector('.api-response-content');
					// Get the plain text content, removing HTML tags
					const responseText = responseContent ? responseContent.textContent || responseContent.innerText : '';
					if (responseText) {
						navigator.clipboard.writeText(responseText).then(() => {
							alert('Response copied to clipboard!');
						}).catch(err => {
							console.error('Failed to copy:', err);
						});
					}
				});

				// Clear response button
				document.querySelector('.clear-response-btn')?.addEventListener('click', function() {
					hideElement('.api-response-container');
					setText('.api-response-content', '');
					setText('.api-request-content', '');
				});

				// Initialize CodeMirror when the response container becomes visible
				const observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
							const container = document.querySelector('.api-response-container');
							if (container && container.style.display !== 'none') {
								// Initialize CodeMirror after a small delay to ensure DOM is ready
								setTimeout(initializeCodeMirror, 100);
							}
						}
					});
				});

				const container = document.querySelector('.api-response-container');
				if (container) {
					observer.observe(container, {
						attributes: true,
						attributeFilter: ['style']
					});
				}
			});
		</script>
		<?php
	}

	public function handle_api_test(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_api_test' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Get request parameters
		$endpoint = sanitize_text_field( $_POST['endpoint'] ?? '' );
		$body = isset( $_POST['body'] ) ? json_decode( stripslashes( $_POST['body'] ), true ) : null;

		// Get API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

		if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
			wp_send_json_error( array(
				'message' => 'API configuration is missing. Please configure your API settings.',
			) );
			return;
		}

		// Initialize Endpoints class
		$endpoints = new \BRAGBookGallery\Includes\REST\Endpoints();

		try {
			$response_body = null;
			$start_time = microtime( true );

			// Route to appropriate Endpoints method based on endpoint type
			switch ( $endpoint ) {
				case 'sidebar':
					$response_body = $endpoints->get_api_sidebar( $api_tokens );
					break;

				case 'cases':
					$response_body = $endpoints->get_pagination_data( $body );
					break;

				case 'carousel':
					$options = array(
						'websitePropertyId' => $website_property_ids[0],
						'procedureId' => $body['procedureId'] ?? null,
						'limit' => 10,
						'start' => 1,
					);
					$response_body = $endpoints->get_carousel_data( $api_tokens[0], $options );
					break;

				case 'filters':
					$response_body = $endpoints->get_pagination_data( $body );
					break;

				case 'favorites-list':
					$email = $body['email'] ?? 'test@example.com';
					$response_body = $endpoints->get_favorite_list_data( $api_tokens, $website_property_ids, $email );
					break;

				case 'sitemap':
					$response_body = $endpoints->get_sitemap_data( $api_tokens, $website_property_ids );
					break;

				case 'single-case':
					$case_id = $body['caseId'] ?? '';
					if ( empty( $case_id ) ) {
						throw new \Exception( 'Case ID is required for single case endpoint' );
					}
					$response_body = $endpoints->get_case_details( (string) $case_id );
					break;

				case 'consultations':
					$response_body = $endpoints->submit_consultation(
						$api_tokens[0],
						intval( $website_property_ids[0] ),
						$body['email'] ?? 'test@example.com',
						$body['phone'] ?? '(555) 123-4567',
						$body['name'] ?? 'Test User',
						$body['details'] ?? 'Test consultation from API test page'
					);
					break;

				case 'views':
					$case_id = intval( $body['caseId'] ?? 0 );
					if ( $case_id <= 0 ) {
						throw new \Exception( 'Valid Case ID is required for views endpoint' );
					}
					$response_body = $endpoints->track_case_view( $api_tokens[0], $case_id );
					break;

				case 'validate-token':
					$result = $endpoints->validate_token( $api_tokens[0], intval( $website_property_ids[0] ) );
					$response_body = wp_json_encode( $result );
					break;

				case 'cases-v2':
					// For GET requests, parameters come from the URL
					$url_parts = wp_parse_url( sanitize_text_field( $_POST['url'] ?? '' ) );
					parse_str( $url_parts['query'] ?? '', $query_params );

					$procedure_id = intval( $query_params['procedureId'] ?? 0 );
					if ( $procedure_id <= 0 ) {
						throw new \Exception( 'Valid Procedure ID is required for v2 cases endpoint' );
					}

					$page = intval( $query_params['page'] ?? 1 );
					$limit = intval( $query_params['limit'] ?? 20 );
					$member_id = isset( $query_params['memberId'] ) ? strval( $query_params['memberId'] ) : null;

					$result = $endpoints->get_cases_v2(
						$api_tokens[0],
						intval( $website_property_ids[0] ),
						$procedure_id,
						$page,
						$limit,
						$member_id
					);
					$response_body = wp_json_encode( $result );
					break;

				case 'single-case-v2':
					// For GET requests, parameters come from the URL
					$url_parts = wp_parse_url( sanitize_text_field( $_POST['url'] ?? '' ) );
					parse_str( $url_parts['query'] ?? '', $query_params );

					// Case ID comes from the URL path
					preg_match( '/\/cases\/(\d+)/', sanitize_text_field( $_POST['url'] ?? '' ), $matches );
					$case_id = intval( $matches[1] ?? 0 );

					if ( $case_id <= 0 ) {
						throw new \Exception( 'Valid Case ID is required for v2 single case endpoint' );
					}

					$procedure_id = intval( $query_params['procedureId'] ?? 0 );
					$member_id = isset( $query_params['memberId'] ) ? strval( $query_params['memberId'] ) : null;

					$result = $endpoints->get_case_detail_v2(
						$api_tokens[0],
						$case_id,
						intval( $website_property_ids[0] ),
						$procedure_id > 0 ? $procedure_id : null,
						$member_id
					);
					$response_body = wp_json_encode( $result );
					break;

				default:
					throw new \Exception( 'Unsupported endpoint: ' . $endpoint );
			}

			$duration = microtime( true ) - $start_time;

			// Check if we got a response
			if ( $response_body === null ) {
				throw new \Exception( 'No response received from API' );
			}

			// Try to decode JSON response if it's a string
			$decoded_body = $response_body;
			if ( is_string( $response_body ) ) {
				$decoded = json_decode( $response_body, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$decoded_body = $decoded;
				}
			}

			wp_send_json_success( array(
				'status' => 200,
				'body' => $decoded_body,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'duration' => round( $duration * 1000, 2 ) . 'ms',
			) );

		} catch ( \Exception $e ) {
			// Log detailed error information
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'API Test Error: ' . $e->getMessage() );
				error_log( 'API Test Endpoint: ' . $endpoint );
			}

			wp_send_json_error( array(
				'message' => $e->getMessage(),
				'code' => 'api_test_error',
				'details' => 'Failed to test endpoint: ' . $endpoint,
			) );
		}
	}
}
