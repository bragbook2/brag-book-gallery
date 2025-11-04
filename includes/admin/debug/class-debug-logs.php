<?php
/**
 * Debug Logs Component
 *
 * Handles rendering and management of debug log files in the debug interface.
 * Provides log viewing, clearing, downloading capabilities with secure file handling
 * and AJAX-based log management. Utilizes modern PHP 8.2+ syntax and WordPress VIP
 * coding standards.
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
 * Debug Logs class
 *
 * Manages debug log files with secure access controls, file size limits, and
 * convenient download/clear functionality. Supports both error and API logs
 * with configurable log levels.
 *
 * ## Features:
 * - Debug log settings (enable/disable, log level)
 * - Log file viewing with automatic truncation for large files
 * - Log file clearing
 * - Log file downloading
 * - File size and modification date display
 * - Security-hardened log directory with .htaccess protection
 *
 * ## Log Levels:
 * - error: Errors only
 * - warning: Warnings & Errors
 * - info: Info, Warnings & Errors
 * - debug: All Messages (Debug)
 *
 * ## Security:
 * - Log directory protected with .htaccess (deny from all)
 * - index.php added to prevent directory listing
 * - Nonce verification for all actions
 * - Capability checks for manage_options
 *
 * @since 3.3.0
 */
final class Debug_Logs {

	/**
	 * Render the debug logs interface
	 *
	 * Displays log settings form and log file contents with actions.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs HTML directly
	 */
	public function render(): void {
		$log_file    = $this->get_log_file_path();
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
					$log_contents  = file_get_contents( $log_file );
					$file_size     = filesize( $log_file );
					$file_modified = filemtime( $log_file );

					// If file is too large, get last 100 lines.
					if ( $file_size > 1048576 ) { // 1MB
						$lines        = file( $log_file );
						$last_lines   = array_slice( $lines, -100 );
						$log_contents = implode( '', $last_lines );
						$truncated    = true;
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

		<?php $this->render_styles(); ?>
		<?php
	}

	/**
	 * Get log file path
	 *
	 * Returns the absolute path to the log file, creating the log directory
	 * and security files if they don't exist.
	 *
	 * @since 3.3.0
	 *
	 * @param string $type Log type: 'error' or 'api'. Default 'error'.
	 *
	 * @return string Absolute path to the log file
	 */
	public function get_log_file_path( string $type = 'error' ): string {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/brag-book-gallery-logs';

		// Create directory if it doesn't exist.
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess to protect log files.
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, 'Deny from all' );
			}

			// Add index.php for additional security.
			$index = $log_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, '<?php // Silence is golden' );
			}
		}

		$filename = ( 'api' === $type ) ? 'api.log' : 'error.log';

		return $log_dir . '/' . $filename;
	}

	/**
	 * Get error log contents
	 *
	 * Retrieves the last 100 lines of the error log file.
	 *
	 * @since 3.3.0
	 *
	 * @return string Log contents or error message
	 */
	public function get_error_log(): string {
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
	 * Get API log contents
	 *
	 * Retrieves the last 100 lines of the API log file.
	 *
	 * @since 3.3.0
	 *
	 * @return string Log contents or error message
	 */
	public function get_api_log(): string {
		$log_file = $this->get_log_file_path( 'api' );

		if ( ! file_exists( $log_file ) ) {
			return __( 'No API log file found.', 'brag-book-gallery' );
		}

		$lines = $this->tail_file( $log_file, 100 );

		if ( empty( $lines ) ) {
			return __( 'No API calls logged yet.', 'brag-book-gallery' );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Clear log file
	 *
	 * Clears the contents of the specified log file.
	 *
	 * @since 3.3.0
	 *
	 * @param string $type Log type: 'error' or 'api'. Default 'error'.
	 *
	 * @return bool True on success, false on failure
	 */
	public function clear_log( string $type = 'error' ): bool {
		$log_file = $this->get_log_file_path( $type );

		if ( file_exists( $log_file ) ) {
			return file_put_contents( $log_file, '' ) !== false;
		}

		return false;
	}

	/**
	 * Download log file
	 *
	 * Initiates a file download for the specified log file.
	 *
	 * @since 3.3.0
	 *
	 * @param string $type Log type: 'error' or 'api'. Default 'error'.
	 *
	 * @return void Outputs file download headers and content
	 */
	public function download_log( string $type = 'error' ): void {
		$log_file = $this->get_log_file_path( $type );

		if ( ! file_exists( $log_file ) ) {
			wp_die( esc_html__( 'Log file not found.', 'brag-book-gallery' ) );
		}

		$filename = ( 'api' === $type ) ? 'api.log' : 'error.log';

		// Clean output buffer.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Send download headers.
		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );

		// Output file contents.
		readfile( $log_file );
		exit;
	}

	/**
	 * Read last N lines from a file
	 *
	 * Efficiently reads the last N lines from a file without loading the entire
	 * file into memory. Uses backward file reading for performance.
	 *
	 * @since 3.3.0
	 *
	 * @param string $file  Absolute path to the file.
	 * @param int    $lines Number of lines to read. Default 100.
	 *
	 * @return array Array of lines
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
		$cursor     = -1;
		$content    = [];

		fseek( $handle, $cursor, SEEK_END );
		$char = fgetc( $handle );

		while ( $char !== false && $line_count < $lines ) {
			if ( $char === "\n" ) {
				$line_count++;
			}

			$cursor--;
			if ( fseek( $handle, $cursor, SEEK_END ) === -1 ) {
				break;
			}

			$char = fgetc( $handle );
		}

		// Read the lines from the position we found.
		while ( ! feof( $handle ) ) {
			$line = fgets( $handle );
			if ( $line !== false ) {
				$content[] = rtrim( $line );
			}
		}

		fclose( $handle );

		return $content;
	}

	/**
	 * Render component styles
	 *
	 * Outputs CSS styles for the debug logs interface.
	 *
	 * @since 3.3.0
	 *
	 * @return void Outputs style tag
	 */
	private function render_styles(): void {
		?>
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
}
