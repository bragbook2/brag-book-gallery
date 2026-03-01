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
			<div class="debug-logs-settings">
				<form method="post">
					<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>

					<div class="debug-logs-setting-row">
						<div class="debug-logs-setting-row__label">
							<?php esc_html_e( 'Debug Logging', 'brag-book-gallery' ); ?>
						</div>
						<div class="debug-logs-setting-row__control">
							<div class="brag-book-gallery-toggle-wrapper">
								<label class="brag-book-gallery-toggle">
									<input type="hidden" name="enable_logs" value="no" />
									<input type="checkbox" name="enable_logs" value="yes" <?php checked( $enable_logs, 'yes' ); ?> />
									<span class="brag-book-gallery-toggle-slider"></span>
								</label>
								<span class="brag-book-gallery-toggle-label">
									<?php esc_html_e( 'Enable debug logging to file', 'brag-book-gallery' ); ?>
								</span>
							</div>
						</div>
					</div>

					<div class="debug-logs-setting-row">
						<div class="debug-logs-setting-row__label">
							<?php esc_html_e( 'Log Level', 'brag-book-gallery' ); ?>
						</div>
						<div class="debug-logs-setting-row__control">
							<select name="log_level" class="debug-logs-level-select">
								<option value="error" <?php selected( $log_level, 'error' ); ?>><?php esc_html_e( 'Errors Only', 'brag-book-gallery' ); ?></option>
								<option value="warning" <?php selected( $log_level, 'warning' ); ?>><?php esc_html_e( 'Warnings & Errors', 'brag-book-gallery' ); ?></option>
								<option value="info" <?php selected( $log_level, 'info' ); ?>><?php esc_html_e( 'Info, Warnings & Errors', 'brag-book-gallery' ); ?></option>
								<option value="debug" <?php selected( $log_level, 'debug' ); ?>><?php esc_html_e( 'All Messages (Debug)', 'brag-book-gallery' ); ?></option>
							</select>
						</div>
					</div>

					<div class="debug-logs-setting-row debug-logs-setting-row--actions">
						<button type="submit" name="save_log_settings" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'brag-book-gallery' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Log File Contents -->
			<?php if ( file_exists( $log_file ) && is_readable( $log_file ) ) : ?>
				<?php
				$log_contents  = file_get_contents( $log_file );
				$file_size     = filesize( $log_file );
				$file_modified = filemtime( $log_file );

				if ( $file_size > 1048576 ) { // 1MB
					$lines        = file( $log_file );
					$last_lines   = array_slice( $lines, -100 );
					$log_contents = implode( '', $last_lines );
					$truncated    = true;
				} else {
					$truncated = false;
				}
				?>
				<div class="debug-logs-file-meta">
					<div class="debug-logs-file-meta__items">
						<div class="debug-logs-file-meta__item">
							<span class="debug-logs-file-meta__label"><?php esc_html_e( 'Path', 'brag-book-gallery' ); ?></span>
							<code class="debug-logs-file-meta__value"><?php echo esc_html( $log_file ); ?></code>
						</div>
						<div class="debug-logs-file-meta__item">
							<span class="debug-logs-file-meta__label"><?php esc_html_e( 'Size', 'brag-book-gallery' ); ?></span>
							<span class="debug-logs-file-meta__value"><?php echo esc_html( size_format( $file_size ) ); ?></span>
						</div>
						<div class="debug-logs-file-meta__item">
							<span class="debug-logs-file-meta__label"><?php esc_html_e( 'Modified', 'brag-book-gallery' ); ?></span>
							<span class="debug-logs-file-meta__value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $file_modified ) ); ?></span>
						</div>
					</div>
					<?php if ( $truncated ) : ?>
						<div class="debug-logs-truncation-notice">
							<?php esc_html_e( 'Log file is large. Showing last 100 lines only.', 'brag-book-gallery' ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="debug-logs-actions">
					<form method="post" class="debug-logs-actions__form">
						<?php wp_nonce_field( 'brag_book_gallery_debug_action', 'debug_nonce' ); ?>
						<button type="submit" name="clear_debug_log" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the debug log?', 'brag-book-gallery' ); ?>');">
							<?php esc_html_e( 'Clear Log', 'brag-book-gallery' ); ?>
						</button>
					</form>
					<a href="<?php echo esc_url( add_query_arg( array( 'download_log' => 1, 'nonce' => wp_create_nonce( 'download_log' ) ) ) ); ?>" class="button">
						<?php esc_html_e( 'Download Log', 'brag-book-gallery' ); ?>
					</a>
				</div>

				<div class="brag-book-gallery-log-viewer">
					<pre class="brag-book-gallery-error-log" id="debug-log-contents" aria-label="<?php esc_attr_e( 'Log Contents', 'brag-book-gallery' ); ?>"><?php echo esc_html( $log_contents ); ?></pre>
				</div>
			<?php else : ?>
				<div class="debug-logs-empty">
					<p><?php esc_html_e( 'No debug log file found. Enable debug logging above to start capturing debug information.', 'brag-book-gallery' ); ?></p>
					<p>
						<span class="debug-logs-file-meta__label"><?php esc_html_e( 'Log file location:', 'brag-book-gallery' ); ?></span>
						<code class="debug-logs-file-meta__value"><?php echo esc_html( $log_file ); ?></code>
					</p>
				</div>
			<?php endif; ?>
		</div>
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

}
