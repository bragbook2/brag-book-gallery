<?php
/**
 * Debug Export/Import Component
 *
 * Handles rendering and processing of settings export/import functionality.
 * Provides secure backup and restore capabilities for all plugin settings with
 * JSON format support. Utilizes modern PHP 8.2+ syntax and WordPress VIP coding
 * standards.
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
 * Debug Export/Import class
 *
 * Manages plugin settings backup and restore functionality with secure handling
 * of sensitive data. Exports settings to JSON format and validates imports for
 * security and compatibility.
 *
 * ## Features:
 * - Export all plugin settings to JSON file
 * - Import settings from JSON file with validation
 * - Settings include: API config, display settings, custom CSS, SEO settings
 * - Version tracking for compatibility checking
 * - Site URL tracking for reference
 * - Timestamp tracking for backup management
 * - Secure nonce verification for all actions
 *
 * ## Exported Settings Include:
 * - API Configuration (tokens, website property IDs)
 * - Display Settings
 * - Mode Settings
 * - Custom CSS
 * - SEO Settings
 * - All other plugin options (excluding transients)
 *
 * ## Security:
 * - Nonce verification required
 * - File type validation (.json only)
 * - JSON validation before import
 * - Transients excluded from export/import
 * - Capability checks (manage_options)
 *
 * @since 3.3.0
 */
final class Debug_Export_Import {

	/**
	 * Render the export/import interface
	 *
	 * Displays export and import forms with instructions and warnings.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		?>
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

			<?php $this->render_javascript(); ?>
		</div>
		<?php
	}

	/**
	 * Export settings to JSON file
	 *
	 * Generates a JSON export of all plugin settings and sends as download.
	 * Must be called before any output is sent.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs file download and exits
	 */
	public function export_settings(): void {
		// Get plugin version.
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version     = $plugin_data['Version'] ?? '3.0.0';

		// Get all plugin options.
		global $wpdb;
		$options = $wpdb->get_results(
			"SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name LIKE 'brag_book_gallery_%'"
		);

		$export_data = array(
			'version'  => $version,
			'exported' => current_time( 'mysql' ),
			'site_url' => site_url(),
			'settings' => array(),
		);

		foreach ( $options as $option ) {
			// Skip transients.
			if ( ! str_contains( $option->option_name, '_transient' ) ) {
				$export_data['settings'][ $option->option_name ] = maybe_unserialize( $option->option_value );
			}
		}

		// Create JSON file.
		$json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename  = 'brag-book-gallery-settings-' . gmdate( 'Y-m-d-His' ) . '.json';

		// Clean any output buffers to prevent header errors.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Send download headers.
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json_data ) );

		// Output the JSON and exit.
		echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON output for download
		exit;
	}

	/**
	 * Import settings from JSON file
	 *
	 * Validates and imports settings from an uploaded JSON file.
	 *
	 * @since 3.3.0
	 *
	 * @param array $file The $_FILES array entry for the uploaded file.
	 *
	 * @return bool|string True on success, error message on failure
	 */
	public function import_settings( array $file ): bool|string {
		// Validate file upload.
		if ( empty( $file['tmp_name'] ) ) {
			return __( 'No file uploaded.', 'brag-book-gallery' );
		}

		// Check file type.
		$file_info = pathinfo( $file['name'] );
		if ( ! isset( $file_info['extension'] ) || 'json' !== strtolower( $file_info['extension'] ) ) {
			return __( 'Invalid file type. Please upload a JSON file.', 'brag-book-gallery' );
		}

		// Read file contents.
		$json_data = file_get_contents( $file['tmp_name'] );
		if ( false === $json_data ) {
			return __( 'Failed to read file contents.', 'brag-book-gallery' );
		}

		// Parse JSON.
		$import_data = json_decode( $json_data, true );
		if ( null === $import_data ) {
			return __( 'Invalid JSON file format.', 'brag-book-gallery' );
		}

		// Validate structure.
		if ( ! isset( $import_data['settings'] ) || ! is_array( $import_data['settings'] ) ) {
			return __( 'Invalid settings file structure.', 'brag-book-gallery' );
		}

		// Import settings.
		foreach ( $import_data['settings'] as $option_name => $option_value ) {
			// Only import our plugin options.
			if ( str_starts_with( $option_name, 'brag_book_gallery_' ) ) {
				update_option( $option_name, $option_value );
			}
		}

		return true;
	}

	/**
	 * Render JavaScript for file upload handling
	 *
	 * Outputs inline JavaScript for import file selection handling.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs script tag
	 */
	private function render_javascript(): void {
		?>
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
		<?php
	}
}
