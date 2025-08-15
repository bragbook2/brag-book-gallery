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
 * Comprehensive debugging and diagnostic tools for BragBook Gallery troubleshooting.
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

	/**
	 * Initialize debug settings page configuration
	 * 
	 * Sets up the page slug for the debug and diagnostic interface.
	 * This page provides essential troubleshooting tools for administrators
	 * and support personnel working with BragBook Gallery installations.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-debug';
		// Don't translate here - translations happen in render method
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
}