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

namespace BRAGBookGallery\Includes\Admin;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Debug Settings Class
 *
 * Comprehensive debugging and diagnostic tools for BRAG book Gallery troubleshooting.
 * This class provides administrators with essential tools for monitoring plugin
 * health, diagnosing issues, and managing plugin data.
 *
 * **Core Features:**
 * - System status monitoring with environment compatibility checks
 * - Debug logging management with configurable verbosity levels
 * - Cache management tools for performance optimization
 * - Database information and statistics viewing
 * - Settings export/import functionality for migration and backup
 *
 * **Diagnostic Capabilities:**
 * - WordPress, PHP, and plugin version compatibility validation
 * - API connection status verification
 * - Memory usage and system resource monitoring
 * - Local gallery data statistics (when in Local mode)
 * - Cache item counting and management
 *
 * **Administrative Tools:**
 * - Log file viewing with recent entries display
 * - Bulk cache clearing operations
 * - Settings export for backup and migration
 * - Database cleanup utilities
 * - System information reporting
 *
 * This class is essential for plugin support and maintenance, providing both
 * automated diagnostics and manual troubleshooting capabilities.
 *
 * @since 3.0.0
 */
class Settings_Debug extends Settings_Base {

	use \BRAGBookGallery\Includes\Admin\Traits\Trait_Ajax_Handler;

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
		// Don't translate here - translations happen in render method
		$this->init_ajax_handlers();
		$this->init_log_file();

		// Handle export/import early, before any output
		add_action( 'admin_init', array( $this, 'handle_export_import_early' ), 1 );

		// Initialize Debug Tools for AJAX handlers
		// Must be initialized always, not just during AJAX, so the action is registered
		// The autoloader will handle loading the class
		\BRAGBookGallery\Includes\Admin\Debug_Tools::get_instance();
	}

	/**
	 * Initialize AJAX handlers for debug settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init_ajax_handlers(): void {
		// Debug settings
		$this->register_ajax_action( 'brag_book_gallery_save_debug_settings', array( $this, 'handle_save_debug_settings' ) );

		// Error log management
		$this->register_ajax_action( 'brag_book_gallery_get_error_log', array( $this, 'handle_get_error_log' ) );
		$this->register_ajax_action( 'brag_book_gallery_clear_error_log', array( $this, 'handle_clear_error_log' ) );
		$this->register_ajax_action( 'brag_book_gallery_download_error_log', array( $this, 'handle_download_error_log' ) );

		// API log management
		$this->register_ajax_action( 'brag_book_gallery_get_api_log', array( $this, 'handle_get_api_log' ) );
		$this->register_ajax_action( 'brag_book_gallery_clear_api_log', array( $this, 'handle_clear_api_log' ) );
		$this->register_ajax_action( 'brag_book_gallery_download_api_log', array( $this, 'handle_download_api_log' ) );

		// System info export
		$this->register_ajax_action( 'brag_book_gallery_export_system_info', array( $this, 'handle_export_system_info' ) );

		// Factory reset
		$this->register_ajax_action( 'brag_book_gallery_factory_reset', array( $this, 'handle_factory_reset' ) );
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
				<li><a href="#export-import"><?php esc_html_e( 'Export & Import', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#cache-management"><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#api-test"><?php esc_html_e( 'API Test', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#diagnostic-tools"><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#flush-rules"><?php esc_html_e( 'Flush Rules', 'brag-book-gallery' ); ?></a></li>
				<li><a href="#factory-reset"><?php esc_html_e( 'Factory Reset', 'brag-book-gallery' ); ?></a></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render tab content panels
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_tab_content(): void {
		?>
		<!-- System Status Tab -->
		<div id="system-status" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'System Status', 'brag-book-gallery' ); ?></h2>
			<?php $this->render_system_status(); ?>

			<!-- System Information Section -->
			<?php
			// Load and render System Info tool
			require_once __DIR__ . '/debug-tools/class-system-info.php';
			$system_info_tool = new \BRAGBookGallery\Includes\Admin\Debug_Tools\System_Info();
			$system_info_tool->render();
			?>
		</div>

		<!-- Debug Logs Tab -->
		<div id="debug-logs" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Debug Logs', 'brag-book-gallery' ); ?></h2>
			<?php $this->render_debug_logs(); ?>
		</div>

		<!-- Database Information Tab -->
		<div id="database-info" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Database Information', 'brag-book-gallery' ); ?></h2>
			<?php $this->render_database_info(); ?>
		</div>

		<!-- Export/Import Tab -->
		<div id="export-import" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Export & Import', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Export your plugin settings for backup or import previously saved settings.', 'brag-book-gallery' ); ?>
			</p>

			<!-- Export Section -->
			<div class="export-import-section export-section">
				<form method="post" id="export-form">
					<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
					<div class="export-content">
						<div class="export-info">
							<h3>
								<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M480-320 280-520l56-58 104 104v-326h80v326l104-104 56 58-200 200ZM240-160q-33 0-56.5-23.5T160-240v-120h80v120h480v-120h80v120q0 33-23.5 56.5T720-160H240Z"/></svg>
								<?php esc_html_e( 'Export Plugin Settings', 'brag-book-gallery' ); ?>
							</h3>
							<p><?php esc_html_e( 'Create a backup of all your plugin settings. This includes:', 'brag-book-gallery' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'API Configuration', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Display Settings', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Mode Settings', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Custom CSS', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'SEO Settings', 'brag-book-gallery' ); ?></li>
							</ul>
							<p class="description"><?php esc_html_e( 'Note: API tokens are included but encrypted for security.', 'brag-book-gallery' ); ?></p>
						</div>
						<div class="export-action">
							<button type="submit" name="export_settings" class="button button-primary button-large">
								<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M480-320 280-520l56-58 104 104v-326h80v326l104-104 56 58-200 200ZM240-160q-33 0-56.5-23.5T160-240v-120h80v120h480v-120h80v120q0 33-23.5 56.5T720-160H240Z"/></svg>
								<?php esc_html_e( 'Download Settings', 'brag-book-gallery' ); ?>
							</button>
						</div>
					</div>
				</form>
			</div>

			<!-- Import Section -->
			<div class="export-import-section import-section">
				<form method="post" enctype="multipart/form-data" id="import-form">
					<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
					<div class="import-content">
						<div class="import-info">
							<h3>
								<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M440-200h80v-326l104 104 56-58-200-200-200 200 56 58 104-104v326ZM240-80q-33 0-56.5-23.5T160-160v-120h80v120h480v-120h80v120q0 33-23.5 56.5T720-80H240Z"/></svg>
								<?php esc_html_e( 'Import Plugin Settings', 'brag-book-gallery' ); ?>
							</h3>
							<p><?php esc_html_e( 'Restore settings from a previously exported JSON file.', 'brag-book-gallery' ); ?></p>
							<div class="file-upload-wrapper">
								<input type="file" name="import_file" accept=".json" id="import-settings-file" class="file-upload-input">
								<label for="import-settings-file" class="file-upload-label">
									<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
									<span class="file-label-text"><?php esc_html_e( 'Choose JSON file', 'brag-book-gallery' ); ?></span>
									<span class="file-name"><?php esc_html_e( 'No file selected', 'brag-book-gallery' ); ?></span>
								</label>
							</div>
							<div class="import-warning">
								<p class="warning-text">
									<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="#d63638"><path d="m40-120 440-760 440 760H40Zm440-120q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Z"/></svg>
									<strong><?php esc_html_e( 'Warning:', 'brag-book-gallery' ); ?></strong>
									<?php esc_html_e( 'Importing will overwrite all current plugin settings. Make sure to export your current settings first if needed.', 'brag-book-gallery' ); ?>
								</p>
							</div>
						</div>
						<div class="import-action">
							<button type="submit" name="import_settings" class="button button-primary button-large" disabled id="import-settings-button">
								<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="0 -960 960 960" width="20" fill="currentColor"><path d="M440-200h80v-326l104 104 56-58-200-200-200 200 56 58 104-104v326ZM240-80q-33 0-56.5-23.5T160-160v-120h80v120h480v-120h80v120q0 33-23.5 56.5T720-80H240Z"/></svg>
								<?php esc_html_e( 'Import Settings', 'brag-book-gallery' ); ?>
							</button>
						</div>
					</div>
				</form>
			</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				// File upload handling for Import section
				const fileInput = document.getElementById('import-settings-file');
				const importButton = document.getElementById('import-settings-button');
				const fileLabel = document.querySelector('.file-upload-label');
				const fileName = document.querySelector('.file-name');

				fileInput?.addEventListener('change', function() {
					if (this.files && this.files[0]) {
						fileName.textContent = this.files[0].name;
						fileLabel.classList.add('has-file');
						importButton.disabled = false;
					} else {
						fileName.textContent = '<?php esc_html_e( 'No file selected', 'brag-book-gallery' ); ?>';
						fileLabel.classList.remove('has-file');
						importButton.disabled = true;
					}
				});
			});
			</script>
		</div>

		<!-- Cache Management Tab -->
		<div id="cache-management" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></h2>
			<?php
			// Load and render Cache Management tool
			$cache_management_tool = new \BRAGBookGallery\Includes\Admin\Debug_Tools\Cache_Management();
			$cache_management_tool->render();
			?>
		</div>

		<!-- API Test Tab -->
		<div id="api-test" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'API Test', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Test your BRAGBook API connection and endpoints to ensure proper gallery functionality.', 'brag-book-gallery' ); ?>
			</p>
			<?php $this->render_api_test_panel(); ?>
		</div>

		<!-- Diagnostic Tools Tab -->
		<div id="diagnostic-tools" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Advanced diagnostic tools for troubleshooting and debugging gallery functionality.', 'brag-book-gallery' ); ?>
			</p>

			<hr/>

			<!-- Gallery Checker Section -->
			<div class="diagnostic-section gallery-checker-section">
				<?php
				$gallery_checker_tool = new \BRAGBookGallery\Includes\Admin\Debug_Tools\Gallery_Checker();
				$gallery_checker_tool->render();
				?>
			</div>

			<hr/>

			<!-- Rewrite Debug Section -->
			<div class="diagnostic-section rewrite-debug-section">
				<?php
				$rewrite_debug_tool = new \BRAGBookGallery\Includes\Admin\Debug_Tools\Rewrite_Debug();
				$rewrite_debug_tool->render();
				?>
			</div>

			<hr/>

			<!-- Rewrite Fix Section -->
			<div class="diagnostic-section rewrite-fix-section">
				<?php
				$rewrite_fix_tool = new \BRAGBookGallery\Includes\Admin\Debug_Tools\Rewrite_Fix();
				$rewrite_fix_tool->render();
				?>
			</div>
		</div>

		<!-- Flush Rules Tab -->
		<div id="flush-rules" class="brag-book-gallery-tab-panel">
			<h2><?php esc_html_e( 'Flush Rules', 'brag-book-gallery' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Flush and regenerate WordPress rewrite rules to resolve URL routing issues.', 'brag-book-gallery' ); ?>
			</p>
			<?php
			require_once __DIR__ . '/debug-tools/class-rewrite-flush.php';
			$rewrite_flush_tool = new \BRAGBookGallery\Includes\Admin\Debug_Tools\Rewrite_Flush();
			$rewrite_flush_tool->render();
			?>
		</div>

		<!-- Factory Reset Tab -->
		<div id="factory-reset" class="brag-book-gallery-tab-panel">
			<div class="brag-book-gallery-section brag-book-gallery-danger-zone">
				<h2 style="color: #dc3232;"><?php esc_html_e( 'Danger Zone - Factory Reset', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-warning-box" style="background: #fff3cd; border-left: 4px solid #dc3232; padding: 12px; margin-bottom: 20px;">
					<p><strong><?php esc_html_e( 'Warning:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. All plugin settings, data, and configurations will be permanently deleted.', 'brag-book-gallery' ); ?></p>
				</div>
				<table class="form-table brag-book-gallery-form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Factory Reset', 'brag-book-gallery' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'This will completely reset the plugin to its initial state by:', 'brag-book-gallery' ); ?>
							</p>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Deleting all plugin settings and configurations', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Clearing all cached data and transients', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Removing all API tokens and credentials', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Deleting custom database tables (if any)', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Resetting rewrite rules', 'brag-book-gallery' ); ?></li>
							</ul>
							<p style="margin-top: 15px;">
								<button type="button" id="brag-book-gallery-factory-reset" class="button button-danger" style="background: #dc3232; color: white; border-color: #dc3232;" data-nonce="<?php echo wp_create_nonce( 'brag_book_gallery_admin' ); ?>">
									<?php esc_html_e( 'Factory Reset Plugin', 'brag-book-gallery' ); ?>
								</button>
							</p>
							<script>
							// Ensure nonce is available globally for admin script
							if (typeof brag_book_gallery_admin === 'undefined') {
								window.brag_book_gallery_admin = {
									ajaxurl: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
									nonce: '<?php echo wp_create_nonce( 'brag_book_gallery_admin' ); ?>'
								};
							}
							</script>
						</td>
					</tr>
				</table>
			</div>
		</div>
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
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
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
					<td><?php echo esc_html( ucfirst( $mode_manager->get_current_mode() ) ); ?></td>
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

	/**
	 * Render debug logs
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_debug_logs(): void {
		$log_file = $this->get_log_file_path();
		$enable_logs = get_option( 'brag_book_gallery_enable_logs', 'no' );
		$log_level   = get_option( 'brag_book_gallery_log_level', 'error' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
			<h3><?php esc_html_e( 'Logging Settings', 'brag-book-gallery' ); ?></h3>
			<table class="form-table brag-book-gallery-form-table">
				<tr>
					<th scope="row">
						<label for="enable_logs">
							<?php esc_html_e( 'Enable Debug Logging', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<div class="brag-book-gallery-toggle-wrapper">
							<label class="brag-book-gallery-toggle">
								<input type="checkbox"
								       id="enable_logs"
								       name="enable_logs"
								       value="yes"
								       <?php checked( $enable_logs, 'yes' ); ?>>
								<span class="brag-book-gallery-toggle-slider"></span>
							</label>
							<span class="brag-book-gallery-toggle-label">
								<?php esc_html_e( 'Enable debug logging for troubleshooting', 'brag-book-gallery' ); ?>
							</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'When enabled, plugin activities will be logged for debugging purposes.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="log_level">
							<?php esc_html_e( 'Log Level', 'brag-book-gallery' ); ?>
						</label>
					</th>
					<td>
						<select id="log_level" name="log_level">
							<option value="error" <?php selected( $log_level, 'error' ); ?>>
								<?php esc_html_e( 'Errors Only', 'brag-book-gallery' ); ?>
							</option>
							<option value="warning" <?php selected( $log_level, 'warning' ); ?>>
								<?php esc_html_e( 'Warnings and Errors', 'brag-book-gallery' ); ?>
							</option>
							<option value="info" <?php selected( $log_level, 'info' ); ?>>
								<?php esc_html_e( 'All Messages', 'brag-book-gallery' ); ?>
							</option>
							<option value="debug" <?php selected( $log_level, 'debug' ); ?>>
								<?php esc_html_e( 'Debug (Verbose)', 'brag-book-gallery' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Set the verbosity of debug logging.', 'brag-book-gallery' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" name="save_debug_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
				</button>
			</p>

			<h3><?php esc_html_e( 'Log File Information', 'brag-book-gallery' ); ?></h3>
			<table class="form-table brag-book-gallery-form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Log Status', 'brag-book-gallery' ); ?></th>
					<td>
						<p>
							<strong><?php esc_html_e( 'Log File:', 'brag-book-gallery' ); ?></strong>
							<code><?php echo esc_html( $log_file ); ?></code>
						</p>
						<?php if ( file_exists( $log_file ) ) : ?>
							<p>
								<strong><?php esc_html_e( 'File Size:', 'brag-book-gallery' ); ?></strong>
								<?php echo esc_html( size_format( filesize( $log_file ) ) ); ?>
							</p>
							<p>
								<button type="submit" name="clear_logs" class="button button-secondary">
									<?php esc_html_e( 'Clear Logs', 'brag-book-gallery' ); ?>
								</button>
								<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=brag_book_gallery_download_logs&nonce=' . wp_create_nonce( 'download_logs' ) ) ); ?>"
								   class="button button-secondary">
									<?php esc_html_e( 'Download Logs', 'brag-book-gallery' ); ?>
								</a>
							</p>

							<h3><?php esc_html_e( 'Recent Log Entries', 'brag-book-gallery' ); ?></h3>
							<div style="background:#f0f0f0;padding:10px;border:1px solid #ddd;max-height:300px;overflow-y:auto;">
								<pre style="margin:0;font-size:12px;">
<?php
								$logs = file_get_contents( $log_file );
								$lines = explode( "\n", $logs );
								$recent_lines = array_slice( $lines, -50 ); // Last 50 lines
								echo esc_html( implode( "\n", $recent_lines ) );
?>
								</pre>
							</div>
						<?php else : ?>
							<p><?php esc_html_e( 'No log file found.', 'brag-book-gallery' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</form>
		<?php
	}

	/**
	 * Render database information
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

	/**
	 * Save debug settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

	/**
	 * Clear cache
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

	/**
	 * Clear logs
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

	/**
	 * Get log file path
	 *
	 * @since 3.0.0
	 * @param string $type Log type ('error' or 'api').
	 * @return string Log file path.
	 */
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
	 * Handle export/import early before any output
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_export_import_early(): void {
		// Only process on our settings page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'brag-book-gallery-debug' ) {
			return;
		}

		// Handle export
		if ( isset( $_POST['export_settings'] ) && isset( $_POST['debug_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) && current_user_can( 'manage_options' ) ) {
				$this->export_settings_direct();
				exit; // Stop execution after export
			}
		}

		// Handle import (keep existing import logic in render method)
	}

	/**
	 * Export settings directly without page rendering
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function export_settings_direct(): void {
		// Get plugin version
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.0.0';

		// Get all plugin options
		global $wpdb;
		$options = $wpdb->get_results(
			"SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'"
		);

		$export_data = array(
			'version' => $version,
			'exported' => current_time( 'mysql' ),
			'site_url' => site_url(),
			'settings' => array(),
		);

		foreach ( $options as $option ) {
			// Skip transients
			if ( strpos( $option->option_name, '_transient' ) === false ) {
				$export_data['settings'][ $option->option_name ] = maybe_unserialize( $option->option_value );
			}
		}

		// Create JSON file
		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename = 'brag-book-gallery-settings-' . date( 'Y-m-d-His' ) . '.json';

		// Clean any output buffers to prevent header errors
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Send download headers
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_data ) );

		// Output the JSON and exit
		echo $json_data;
		exit;
	}

	/**
	 * Export settings (deprecated - kept for backward compatibility)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function export_settings(): void {
		// Verify nonce
		if ( ! isset( $_POST['debug_nonce'] ) || ! wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) ) {
			return;
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get plugin version
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.0.0';

		// Get all plugin options
		global $wpdb;
		$options = $wpdb->get_results(
			"SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'"
		);

		$export_data = array(
			'version' => $version,
			'exported' => current_time( 'mysql' ),
			'site_url' => site_url(),
			'settings' => array(),
		);

		foreach ( $options as $option ) {
			// Skip transients
			if ( strpos( $option->option_name, '_transient' ) === false ) {
				$export_data['settings'][ $option->option_name ] = maybe_unserialize( $option->option_value );
			}
		}

		// Create JSON file
		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename = 'brag-book-gallery-settings-' . date( 'Y-m-d-His' ) . '.json';

		// Send download headers
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_data ) );
		echo $json_data;
		exit;
	}

	/**
	 * Import plugin settings from JSON file
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function import_settings(): void {
		// Verify nonce
		if ( ! isset( $_POST['debug_nonce'] ) || ! wp_verify_nonce( $_POST['debug_nonce'], 'brag_book_gallery_debug_action' ) ) {
			$this->add_notice( __( 'Security check failed.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_notice( __( 'Insufficient permissions.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Check if file was uploaded
		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			$this->add_notice( __( 'No file uploaded or upload error occurred.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Read uploaded file
		$json_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
		if ( ! $json_content ) {
			$this->add_notice( __( 'Could not read uploaded file.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Parse JSON
		$import_data = json_decode( $json_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->add_notice( __( 'Invalid JSON file format.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Validate structure
		if ( ! isset( $import_data['settings'] ) || ! is_array( $import_data['settings'] ) ) {
			$this->add_notice( __( 'Invalid settings file structure.', 'brag-book-gallery' ), 'error' );
			return;
		}

		// Import settings
		$imported_count = 0;
		$skipped_count = 0;

		foreach ( $import_data['settings'] as $option_name => $option_value ) {
			// Only import brag_book_gallery options
			if ( strpos( $option_name, 'brag_book_gallery_' ) === 0 ) {
				// Skip transients
				if ( strpos( $option_name, '_transient' ) !== false ) {
					$skipped_count++;
					continue;
				}

				// Update or add the option
				update_option( $option_name, $option_value );
				$imported_count++;
			}
		}

		// Clear caches after import
		wp_cache_flush();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Show success message
		$message = sprintf(
			__( 'Settings imported successfully! %d settings imported, %d skipped.', 'brag-book-gallery' ),
			$imported_count,
			$skipped_count
		);
		$this->add_notice( $message, 'success' );
	}

	/**
	 * Handle save debug settings AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

		$log_content = $this->get_error_log();
		wp_send_json_success( [ 'log' => $log_content ] );
	}

	/**
	 * Handle clear error log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_clear_error_log(): void {
		$this->verify_ajax_request();

		$log_file = $this->get_log_file_path( 'error' );
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
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

		$log_file = $this->get_log_file_path( 'error' );

		if ( ! file_exists( $log_file ) ) {
			wp_die( __( 'Error log file not found.', 'brag-book-gallery' ) );
		}

		$filename = 'brag-book-gallery-error-log-' . date( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		readfile( $log_file );
		exit;
	}

	/**
	 * Handle get API log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_get_api_log(): void {
		$this->verify_ajax_request();

		$log_content = $this->get_api_log();
		wp_send_json_success( [ 'log' => $log_content ] );
	}

	/**
	 * Handle clear API log AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_clear_api_log(): void {
		$this->verify_ajax_request();

		$log_file = $this->get_log_file_path( 'api' );
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
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

		$log_file = $this->get_log_file_path( 'api' );

		if ( ! file_exists( $log_file ) ) {
			wp_die( __( 'API log file not found.', 'brag-book-gallery' ) );
		}

		$filename = 'brag-book-gallery-api-log-' . date( 'Y-m-d-His' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		readfile( $log_file );
		exit;
	}

	/**
	 * Handle export system info AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

	/**
	 * Get error log content
	 *
	 * @since 3.0.0
	 * @return string
	 */
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

	/**
	 * Get API log content
	 *
	 * @since 3.0.0
	 * @return string
	 */
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


	/**
	 * Read last N lines from a file
	 *
	 * @since 3.0.0
	 * @param string $file File path
	 * @param int    $lines Number of lines to read
	 * @return array
	 */
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

	/**
	 * Get complete system information
	 *
	 * @since 3.0.0
	 * @return string
	 */
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
	 * Render diagnostic tools section
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function render_diagnostic_tools(): void {
		// Load debug tools if not already loaded
		if ( ! class_exists( '\BRAGBookGallery\Includes\Admin\Debug_Tools' ) ) {
			require_once __DIR__ . '/class-debug-tools.php';
		}

		// Get debug tools instance
		$debug_tools = \BRAGBookGallery\Includes\Admin\Debug_Tools::get_instance();

		// Enqueue necessary scripts and styles
		$plugin_dir_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );

		wp_enqueue_script(
			'brag-book-debug-tools',
			$plugin_dir_url . 'assets/js/debug-tools.js',
			[],
			'1.0.0',
			true
		);

		wp_localize_script(
			'brag-book-debug-tools',
			'bragBookDebugTools',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'brag_book_gallery_debug_tools' ),
			]
		);

		// Debug tools styles are now included in brag-book-gallery-admin.css

		// Add inline script to handle tab navigation properly
		wp_add_inline_script( 'brag-book-debug-tools', "
			document.addEventListener('DOMContentLoaded', function() {
				// Function to activate a specific tab
				function activateDebugTab(targetId) {
					// Update active tab
					document.querySelectorAll('.brag-book-debug-tab-item').forEach(function(item) {
						item.classList.remove('active');
					});
					const targetTab = document.querySelector('.brag-book-debug-tab-link[data-tab-target=\"' + targetId + '\"]');
					if (targetTab) {
						targetTab.closest('.brag-book-debug-tab-item').classList.add('active');
					}

					// Update active panel
					document.querySelectorAll('.brag-book-debug-tools .tool-panel').forEach(function(panel) {
						panel.classList.remove('active');
						panel.style.display = 'none';
					});
					const targetPanel = document.getElementById(targetId);
					if (targetPanel) {
						targetPanel.classList.add('active');
						targetPanel.style.display = 'block';
					}
				}

				// Check for hash on page load and activate the corresponding tab
				if (window.location.hash) {
					const hashTarget = window.location.hash.substring(1);
					if (document.getElementById(hashTarget)) {
						activateDebugTab(hashTarget);
					} else {
						// Set Gallery Checker as default if hash doesn't match
						activateDebugTab('gallery-checker');
					}
				} else {
					// Set Gallery Checker as default active tab
					activateDebugTab('gallery-checker');
				}

				// Handle main navigation clicks to prevent jQuery errors
				const mainNavTabs = document.querySelectorAll('.brag-book-gallery-nav-tabs .nav-tab');
				mainNavTabs.forEach(function(tab) {
					tab.addEventListener('click', function(e) {
						// Let the browser handle the navigation naturally
						// Don't prevent default - we want normal navigation
						return true;
					});
				});

				// Handle debug tool tab clicks separately
				const debugTabLinks = document.querySelectorAll('.brag-book-debug-tab-link');
				debugTabLinks.forEach(function(link) {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();

						const targetId = this.getAttribute('data-tab-target') || this.getAttribute('href').substring(1);
						activateDebugTab(targetId);

						// Update URL hash
						window.location.hash = targetId;

						return false;
					});
				});
			});
		", 'before' );

		// Render the debug tools interface
		?>
		<div class="brag-book-debug-tools" data-no-jquery="true">
			<div class="brag-book-gallery-tabs brag-book-debug-tabs">
				<ul class="brag-book-gallery-tab-list brag-book-debug-tab-list">
					<li class="brag-book-gallery-tab-item brag-book-debug-tab-item active">
						<a href="#gallery-checker" class="brag-book-gallery-tab-link brag-book-debug-tab-link" data-tab-target="gallery-checker"><?php esc_html_e( 'Gallery Checker', 'brag-book-gallery' ); ?></a>
					</li>
					<li class="brag-book-gallery-tab-item brag-book-debug-tab-item">
						<a href="#rewrite-debug" class="brag-book-gallery-tab-link brag-book-debug-tab-link" data-tab-target="rewrite-debug"><?php esc_html_e( 'Rewrite Debug', 'brag-book-gallery' ); ?></a>
					</li>
					<li class="brag-book-gallery-tab-item brag-book-debug-tab-item">
						<a href="#rewrite-fix" class="brag-book-gallery-tab-link brag-book-debug-tab-link" data-tab-target="rewrite-fix"><?php esc_html_e( 'Rewrite Fix', 'brag-book-gallery' ); ?></a>
					</li>
					<li class="brag-book-gallery-tab-item brag-book-debug-tab-item">
						<a href="#rewrite-flush" class="brag-book-gallery-tab-link brag-book-debug-tab-link" data-tab-target="rewrite-flush"><?php esc_html_e( 'Flush Rules', 'brag-book-gallery' ); ?></a>
					</li>
				</ul>
			</div>

			<div class="tool-content">
				<?php
				// Load tool classes
				require_once __DIR__ . '/debug-tools/class-gallery-checker.php';
				require_once __DIR__ . '/debug-tools/class-rewrite-debug.php';
				require_once __DIR__ . '/debug-tools/class-rewrite-fix.php';
				require_once __DIR__ . '/debug-tools/class-rewrite-flush.php';

				// Initialize tools
				$tools = [
					'gallery-checker'  => new \BRAGBookGallery\Includes\Admin\Debug_Tools\Gallery_Checker(),
					'rewrite-debug'    => new \BRAGBookGallery\Includes\Admin\Debug_Tools\Rewrite_Debug(),
					'rewrite-fix'      => new \BRAGBookGallery\Includes\Admin\Debug_Tools\Rewrite_Fix(),
					'rewrite-flush'    => new \BRAGBookGallery\Includes\Admin\Debug_Tools\Rewrite_Flush(),
				];
				?>

				<div id="gallery-checker" class="tool-panel active">
					<?php $tools['gallery-checker']->render(); ?>
				</div>

				<div id="rewrite-debug" class="tool-panel">
					<?php $tools['rewrite-debug']->render(); ?>
				</div>

				<div id="rewrite-fix" class="tool-panel">
					<?php $tools['rewrite-fix']->render(); ?>
				</div>

				<div id="rewrite-flush" class="tool-panel">
					<?php $tools['rewrite-flush']->render(); ?>
				</div>
			</div>
		</div>

		<style>
		/* Ensure panels are properly hidden/shown */
		.brag-book-debug-tools .tool-panel {
			display: none;
		}
		.brag-book-debug-tools .tool-panel.active {
			display: block;
		}
		</style>
		<?php
	}

	/**
	 * Handle factory reset AJAX request
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_factory_reset(): void {
		// Start output buffering to catch any unexpected output
		ob_start();

		try {
			// Verify nonce - using the admin nonce that's actually generated
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'brag_book_gallery_admin' ) ) {
				ob_end_clean();
				wp_send_json_error( __( 'Security check failed. Please refresh the page and try again.', 'brag-book-gallery' ) );
				return;
			}

			// Double-check user capabilities
			if ( ! current_user_can( 'manage_options' ) ) {
				ob_end_clean();
				wp_send_json_error( __( 'Insufficient permissions.', 'brag-book-gallery' ) );
				return;
			}

			global $wpdb;
			// Get all plugin options
			$plugin_options = [
				'brag_book_gallery_api_token',
				'brag_book_gallery_website_property_id',
				'brag_book_gallery_page_slug',
				'brag_book_gallery_mode',
				'brag_book_gallery_debug_mode',
				'brag_book_gallery_javascript_enabled',
				'brag_book_gallery_log_api_calls',
				'brag_book_gallery_log_errors',
				'brag_book_gallery_log_verbosity',
				'brag_book_gallery_cache_duration',
				'brag_book_gallery_items_per_page',
				'brag_book_gallery_enable_favorites',
				'brag_book_gallery_enable_sharing',
				'brag_book_gallery_enable_nudity_warning',
				'brag_book_gallery_consultation_form_url',
				'brag_book_gallery_consultation_form_type',
				'brag_book_gallery_consultation_custom_html',
				'brag_book_gallery_db_version',
				'brag_book_gallery_version',
				'brag_book_gallery_activation_time',
				'brag_book_gallery_last_sync',
			];

			// Delete the gallery page if it exists
			$gallery_page_id = get_option( 'brag_book_gallery_page_id' );
			if ( $gallery_page_id ) {
				// Force delete the page (bypass trash)
				wp_delete_post( $gallery_page_id, true );
			}

			// Also check for any pages with the gallery shortcode and optionally delete them
			$pages_with_shortcode = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_content LIKE '%[brag_book_gallery%'
				AND post_type = 'page'"
			);

			// Delete all pages containing the gallery shortcode
			foreach ( $pages_with_shortcode as $page_id ) {
				wp_delete_post( $page_id, true );
			}

			// Delete all plugin options
			foreach ( $plugin_options as $option ) {
				delete_option( $option );
			}

			// Clear all transients
			$wpdb->query(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE '_transient_brag_book_%'
				OR option_name LIKE '_transient_timeout_brag_book_%'"
			);

			// Clear site transients
			$wpdb->query(
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE '_site_transient_brag_book_%'
				OR meta_key LIKE '_site_transient_timeout_brag_book_%'"
			);

			// Drop custom database tables if they exist
			$tables = [
				$wpdb->prefix . 'brag_case_map',
				$wpdb->prefix . 'brag_sync_log',
			];

			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
			}

			// Clear rewrite rules
			flush_rewrite_rules();

			// Clear any cached data
			wp_cache_flush();

			// Clear logs
			$upload_dir = wp_upload_dir();
			$log_dir = $upload_dir['basedir'] . '/brag-book-gallery-logs';
			if ( is_dir( $log_dir ) ) {
				$files = glob( $log_dir . '/*.log' );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}

			// Reinitialize the plugin by running activation routine
			// This will recreate default options and tables
			if ( class_exists( '\BRAGBookGallery\Includes\Core\Setup' ) ) {
				$setup = \BRAGBookGallery\Includes\Core\Setup::get_instance();
				if ( method_exists( $setup, 'activate' ) ) {
					$setup->activate();
				}
			}

			// Clear output buffer and send success response
			ob_end_clean();
			wp_send_json_success( [
				'message' => __( 'Plugin has been successfully reset to factory defaults. The page will reload.', 'brag-book-gallery' ),
				'redirect' => admin_url( 'admin.php?page=brag-book-gallery-settings&reset=success' )
			] );

		} catch ( \Exception $e ) {
			// Clear output buffer on error
			ob_end_clean();
			wp_send_json_error( sprintf(
				/* translators: %s: error message */
				__( 'Factory reset failed: %s', 'brag-book-gallery' ),
				$e->getMessage()
			) );
		} catch ( \Error $e ) {
			// Catch fatal errors too
			ob_end_clean();
			wp_send_json_error( sprintf(
				/* translators: %s: error message */
				__( 'Factory reset critical error: %s', 'brag-book-gallery' ),
				$e->getMessage()
			) );
		}
	}

	/**
	 * Render API Test panel content
	 *
	 * @since 3.0.0
	 * @return void
	 */
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

	/**
	 * Render the API test interface content
	 *
	 * @since 3.0.0
	 * @param array $api_tokens API tokens array
	 * @param array $website_property_ids Website property IDs array
	 * @return void
	 */
	private function render_api_test_content( array $api_tokens, array $website_property_ids ): void {
		?>
		<div class="api-test-config">
			<div class="config-info">
				<strong><?php esc_html_e( 'API Connections:', 'brag-book-gallery' ); ?></strong>
				<?php if ( count( $api_tokens ) > 1 ) : ?>
					<p class="description"><?php esc_html_e( 'Multiple connections configured. Tests will use all connections.', 'brag-book-gallery' ); ?></p>
				<?php endif; ?>
				<ul>
					<?php foreach ( $api_tokens as $index => $token ) : ?>
						<li>
							<?php
							echo esc_html( sprintf(
								__( 'Connection %d: Token %s... | Property ID: %s', 'brag-book-gallery' ),
								$index + 1,
								substr( $token, 0, 10 ),
								$website_property_ids[$index] ?? 'N/A'
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
							<span class="description"><?php esc_html_e( 'Used by: Cases, Filters (default: 129) - Not used by Carousel', 'brag-book-gallery' ); ?></span>
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

	/**
	 * Render API test styles and JavaScript
	 *
	 * @since 3.0.0
	 * @param array $api_tokens API tokens array
	 * @param array $website_property_ids Website property IDs array
	 * @return void
	 */
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
			const baseUrl = 'https://app.bragbookgallery.com';
			const ajaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			const nonce = '<?php echo wp_create_nonce( 'brag_book_api_test' ); ?>';

			// Check if we have valid tokens
			if (!apiTokens || apiTokens.length === 0) {
				console.error('No valid API tokens found. Please check your API settings.');
			}

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
					let url = baseUrl + this.dataset.url;
					const needsId = this.dataset.needsId;

					// Check if case ID is needed
					if (needsId) {
						const caseIdInput = document.getElementById('case-id-input');
						const caseId = caseIdInput ? caseIdInput.value : '';
						if (!caseId) {
							alert('Please enter a Case ID');
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
								url += '?apiToken=' + encodeURIComponent(apiTokens[0]) +
								       '&websitepropertyId=' + encodeURIComponent(websitePropertyIds[0]);
								requestBody = {
									email: "test@example.com",
									phone: "(555) 123-4567",
									name: "Test User",
									details: "This is a test consultation submission from the API testing page."
								};
								break;
						}
					} else {
						// For GET requests (carousel), add params to URL
						const procedureInput = document.getElementById('test-procedure-id');
						const params = new URLSearchParams({
							websitePropertyId: websitePropertyIds[0].toString(),
							start: '1',
							limit: '10',
							apiToken: apiTokens[0]
						});

						if (endpoint === 'carousel') {
							params.append('procedureId', procedureInput?.value || '3405');
						}

						url += '?' + params.toString();
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

	/**
	 * Handle API test requests via AJAX (copied from Settings_Api_Test)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_api_test(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'brag_book_api_test' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Get request parameters
		$endpoint = sanitize_text_field( $_POST['endpoint'] ?? '' );
		$method = sanitize_text_field( $_POST['method'] ?? 'POST' );
		$url = sanitize_url( $_POST['url'] ?? '' );
		$body = isset( $_POST['body'] ) ? json_decode( stripslashes( $_POST['body'] ), true ) : null;

		// Get timeout from settings or use default
		$api_timeout = intval( get_option( 'brag_book_gallery_api_timeout', 30 ) );

		// Make the API request using wp_remote_request - matching the format in class-endpoints.php
		$args = array(
			'method' => $method,
			'timeout' => $api_timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'User-Agent' => 'BRAG book-Gallery-Plugin/3.0.0',
				'X-Plugin-Version' => '3.0.0',
				'X-WordPress-Version' => get_bloginfo( 'version' ),
				'X-Site-URL' => home_url(),
			),
			'sslverify' => false, // Set to false for local development
		);

		// Add body for POST requests
		if ( $method === 'POST' && $body ) {
			$args['body'] = wp_json_encode( $body );
		} elseif ( $method === 'GET' ) {
			// For GET requests, use simpler headers (carousel endpoint)
			$args['headers'] = array(
				'Accept' => 'application/json',
				'User-Agent' => 'BRAG book Gallery Plugin/3.0.0',
			);
		}

		// Log the request for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'API Test Request: ' . print_r( array( 'url' => $url, 'method' => $method, 'body' => $body ), true ) );
		}

		// Make the request
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			// Log detailed error information
			error_log( 'API Test Error: ' . $response->get_error_message() );
			error_log( 'API Test Error Code: ' . $response->get_error_code() );
			error_log( 'API Test URL: ' . $url );
			error_log( 'API Test Args: ' . print_r( $args, true ) );

			wp_send_json_error( array(
				'message' => $response->get_error_message(),
				'code' => $response->get_error_code(),
				'details' => 'Failed to connect to API endpoint: ' . $url,
				'wp_error_data' => $response->get_error_data(),
			) );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Try to decode JSON response
		$decoded_body = json_decode( $response_body, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$response_body = $decoded_body;
		}

		// Get headers array
		$headers_object = wp_remote_retrieve_headers( $response );
		$headers = array();
		if ( is_object( $headers_object ) && method_exists( $headers_object, 'getAll' ) ) {
			$headers = $headers_object->getAll();
		} elseif ( is_array( $headers_object ) ) {
			$headers = $headers_object;
		}

		wp_send_json_success( array(
			'status' => $response_code,
			'body' => $response_body,
			'headers' => $headers,
		) );
	}
}
