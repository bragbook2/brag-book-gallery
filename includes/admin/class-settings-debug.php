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
		
		// Initialize Debug Tools for AJAX handlers
		// Must be initialized always, not just during AJAX, so the action is registered
		if ( ! class_exists( '\BragBookGallery\Admin\Debug_Tools' ) ) {
			require_once __DIR__ . '/class-debug-tools.php';
		}
		\BragBookGallery\Admin\Debug_Tools::get_instance();
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

		if ( isset( $_POST['export_settings'] ) ) {
			$this->export_settings();
		}

		// Get current settings
		$enable_logs = get_option( 'brag_book_gallery_enable_logs', 'no' );
		$log_level   = get_option( 'brag_book_gallery_log_level', 'error' );

		$this->render_header();
		?>

		<div class="brag-book-gallery-debug-content">
			<!-- System Status -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'System Status', 'brag-book-gallery' ); ?></h2>
				<?php $this->render_system_status(); ?>
			</div>

			<!-- Debug Logs -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Debug Logs', 'brag-book-gallery' ); ?></h2>
				<?php $this->render_debug_logs(); ?>
			</div>

			<!-- Cache Management -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
					<table class="form-table brag-book-gallery-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'API Cache', 'brag-book-gallery' ); ?></th>
							<td>
								<?php
								global $wpdb;
								$cache_count = $wpdb->get_var(
									"SELECT COUNT(*) FROM {$wpdb->options}
									WHERE option_name LIKE '_transient_brag_book_gallery_%'"
								);
								printf(
									/* translators: %d: number of cached items */
									esc_html( _n( '%d cached item', '%d cached items', $cache_count, 'brag-book-gallery' ) ),
									$cache_count
								);
								?>
								<p>
									<button type="submit" name="clear_cache" class="button button-secondary">
										<?php esc_html_e( 'Clear All Cache', 'brag-book-gallery' ); ?>
									</button>
								</p>
							</td>
						</tr>
					</table>
				</form>
			</div>

			<!-- Database Information -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Database Information', 'brag-book-gallery' ); ?></h2>
				<?php $this->render_database_info(); ?>
			</div>

			<!-- Export/Import -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Export & Import', 'brag-book-gallery' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
					<table class="form-table brag-book-gallery-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Export Settings', 'brag-book-gallery' ); ?></th>
							<td>
								<p class="description">
									<?php esc_html_e( 'Export all plugin settings as a JSON file for backup or migration.', 'brag-book-gallery' ); ?>
								</p>
								<button type="submit" name="export_settings" class="button button-secondary">
									<?php esc_html_e( 'Export Settings', 'brag-book-gallery' ); ?>
								</button>
							</td>
						</tr>
					</table>
				</form>
			</div>

			<!-- Diagnostic Tools -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></h2>
				<?php $this->render_diagnostic_tools(); ?>
			</div>
		</div>

		<?php
		$this->render_footer();
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
	 * - Current operational mode (JavaScript/Local)
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
							<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color:#ffb900;"></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
					<td>
						<?php if ( version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color:#ffb900;"></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( phpversion() ); ?></td>
					<td>
						<?php if ( version_compare( phpversion(), '8.2', '>=' ) ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-no-alt" style="color:#dc3232;"></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Active Mode', 'brag-book-gallery' ); ?></th>
					<td><?php echo esc_html( ucfirst( $mode_manager->get_current_mode() ) ); ?></td>
					<td><span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span></td>
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
							<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color:#ffb900;"></span>
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
							<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color:#ffb900;"></span>
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
						<label>
							<input type="checkbox"
							       id="enable_logs"
							       name="enable_logs"
							       value="yes"
							       <?php checked( $enable_logs, 'yes' ); ?>>
							<?php esc_html_e( 'Enable debug logging for troubleshooting', 'brag-book-gallery' ); ?>
						</label>
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
	 * Export settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function export_settings(): void {
		if ( ! $this->save_settings( 'brag_book_gallery_debug_action', 'debug_nonce' ) ) {
			return;
		}

		// Get all plugin options
		global $wpdb;
		$options = $wpdb->get_results(
			"SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'"
		);

		$export_data = array();
		foreach ( $options as $option ) {
			// Skip transients and sensitive data
			if ( strpos( $option->option_name, '_transient' ) === false
				&& strpos( $option->option_name, 'api_token' ) === false ) {
				$export_data[ $option->option_name ] = maybe_unserialize( $option->option_value );
			}
		}

		// Create JSON file
		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename = 'bragbook-settings-' . date( 'Y-m-d-His' ) . '.json';

		// Send download headers
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_data ) );
		echo $json_data;
		exit;
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
		if ( ! class_exists( '\BragBookGallery\Admin\Debug_Tools' ) ) {
			require_once __DIR__ . '/class-debug-tools.php';
		}
		
		// Get debug tools instance
		$debug_tools = \BragBookGallery\Admin\Debug_Tools::get_instance();
		
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
				'nonce'   => wp_create_nonce( 'brag_book_debug_tools' ),
			]
		);

		wp_enqueue_style(
			'brag-book-debug-tools',
			$plugin_dir_url . 'assets/css/debug-tools.css',
			[],
			'1.0.0'
		);
		
		// Render the debug tools interface
		?>
		<div class="brag-book-debug-tools">
			<div class="tool-tabs">
				<ul class="nav-tab-wrapper">
					<li><a href="#gallery-checker" class="nav-tab nav-tab-active"><?php esc_html_e( 'Gallery Checker', 'brag-book-gallery' ); ?></a></li>
					<li><a href="#rewrite-debug" class="nav-tab"><?php esc_html_e( 'Rewrite Debug', 'brag-book-gallery' ); ?></a></li>
					<li><a href="#rewrite-fix" class="nav-tab"><?php esc_html_e( 'Rewrite Fix', 'brag-book-gallery' ); ?></a></li>
					<li><a href="#rewrite-flush" class="nav-tab"><?php esc_html_e( 'Flush Rules', 'brag-book-gallery' ); ?></a></li>
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
					'gallery-checker' => new \BragBookGallery\Admin\Debug_Tools\Gallery_Checker(),
					'rewrite-debug'   => new \BragBookGallery\Admin\Debug_Tools\Rewrite_Debug(),
					'rewrite-fix'     => new \BragBookGallery\Admin\Debug_Tools\Rewrite_Fix(),
					'rewrite-flush'   => new \BragBookGallery\Admin\Debug_Tools\Rewrite_Flush(),
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

		<script>
		jQuery(document).ready(function($) {
			// Tab switching
			$('.nav-tab-wrapper a').on('click', function(e) {
				e.preventDefault();
				var target = $(this).attr('href');
				
				$('.nav-tab').removeClass('nav-tab-active');
				$(this).addClass('nav-tab-active');
				
				$('.tool-panel').removeClass('active');
				$(target).addClass('active');
			});
		});
		</script>
		<?php
	}
}
