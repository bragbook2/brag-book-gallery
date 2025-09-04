<?php
/**
 * System Information Tool
 *
 * Provides comprehensive system information collection and display capabilities with
 * advanced diagnostics, performance monitoring, and enterprise-grade security features.
 * Utilizes modern PHP 8.2+ syntax and WordPress VIP coding standards.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 * @version    3.0.0
 *
 * @author     BRAGBook Team
 * @license    GPL-2.0-or-later
 *
 * @see \BRAGBookGallery\Admin\Debug_Tools\Gallery_Checker For gallery diagnostics
 * @see \BRAGBookGallery\Admin\Debug_Tools\Rewrite_Debug For rewrite rule debugging
 * @see phpinfo() PHP's built-in information function for additional details
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Debug_Tools;

use WP_Error;
use Exception;
use Throwable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Information class
 *
 * Enterprise-grade system information collection tool with comprehensive diagnostics,
 * security-hardened output, and performance optimization features.
 *
 * ## Features:
 * - Comprehensive system diagnostics collection
 * - Security-sanitized output with sensitive data protection
 * - Export capabilities (clipboard and download)
 * - Performance metrics tracking
 * - Browser and environment detection
 * - Plugin conflict detection
 * - Resource usage monitoring
 * - Hosting provider detection
 *
 * @since 3.0.0
 * @final
 */
final class System_Info {

	/**
	 * Performance metrics storage.
	 *
	 * @since 3.0.0
	 * @var array<string, float>
	 */
	private array $metrics = [];

	/**
	 * Cache key prefix for transient operations.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const CACHE_PREFIX = 'brag_book_gallery_transient_sysinfo_';

	/**
	 * Cache duration in seconds (5 minutes).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const CACHE_DURATION = 300;

	/**
	 * Maximum execution time for system info generation (seconds).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const MAX_EXECUTION_TIME = 30;

	/**
	 * Sensitive data patterns to redact.
	 *
	 * @since 3.0.0
	 * @var array<string>
	 */
	private const SENSITIVE_PATTERNS = [
		'api_key',
		'api_token',
		'password',
		'secret',
		'private_key',
		'access_token',
	];

	/**
	 * System info sections for modular collection.
	 *
	 * @since 3.0.0
	 * @var array<string, string>
	 */
	private array $sections = [];

	/**
	 * Render the comprehensive system information tool interface.
	 *
	 * Displays a complete interface for viewing and exporting system information
	 * with security-hardened output, modern JavaScript functionality, and
	 * performance optimization.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 * @throws Exception If rendering fails or user lacks permissions.
	 */
	public function render(): void {
		try {
			$start_time = microtime( true );

			// Security check
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new Exception( __( 'Insufficient permissions to view system information.', 'brag-book-gallery' ) );
			}

			// Set maximum execution time for info generation
			if ( ! ini_get( 'safe_mode' ) ) {
				@set_time_limit( self::MAX_EXECUTION_TIME );
			}

			// Generate or retrieve cached system info
			$system_info = $this->get_cached_system_info();
			?>
			<div class="tool-section" data-nonce="<?php echo esc_attr( wp_create_nonce( 'brag_book_system_info' ) ); ?>">
			<h3><?php esc_html_e( 'System Information', 'brag-book-gallery' ); ?></h3>
			<p><?php esc_html_e( 'Comprehensive system information for debugging and support.', 'brag-book-gallery' ); ?></p>

			<div class="system-info-actions" style="margin: 20px 0;">
				<button type="button" class="button button-primary" id="copy-system-info">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z"/></svg>
					<?php esc_html_e( 'Copy to Clipboard', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="download-system-info">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-320 280-520l56-58 104 104v-326h80v326l104-104 56 58-200 200ZM240-160q-33 0-56.5-23.5T160-240v-120h80v120h480v-120h80v120q0 33-23.5 56.5T720-160H240Z"/></svg>
					<?php esc_html_e( 'Download as Text', 'brag-book-gallery' ); ?>
				</button>
				<span id="copy-status" style="margin-left: 10px; color: #46b450; display: none;">
					<?php esc_html_e( 'Copied to clipboard!', 'brag-book-gallery' ); ?>
				</span>
			</div>

				<textarea id="system-info-output" readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; background: #f5f5f5; padding: 15px; border: 1px solid #ddd; resize: vertical;" aria-label="<?php esc_attr_e( 'System Information Output', 'brag-book-gallery' ); ?>"><?php echo esc_textarea( $system_info ); ?></textarea>

				<!-- Performance Metrics -->
				<div class="performance-metrics" style="margin-top: 10px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">
					<small>
						<?php
						printf(
							/* translators: %s: Time in seconds */
							esc_html__( 'System information generated in %s seconds', 'brag-book-gallery' ),
							number_format( microtime( true ) - $start_time, 3 )
						);
						?>
					</small>
				</div>
		</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				/**
				 * Modern fade-in effect with ES5 compatibility.
				 *
				 * @param {HTMLElement} element - Element to fade in
				 * @param {number} duration - Duration in milliseconds
				 * @returns {Promise<void>}
				 */
				const fadeIn = async function(element, duration) {
					if (!duration) duration = 400;
					return new Promise(function(resolve) {
						element.style.display = 'block';
						element.style.opacity = '0';

						setTimeout(function() {
							element.style.transition = 'opacity ' + duration + 'ms';
							element.style.opacity = '1';
							setTimeout(resolve, duration);
						}, 10);
					});
				};

				/**
				 * Modern fade-out effect with ES5 compatibility.
				 *
				 * @param {HTMLElement} element - Element to fade out
				 * @param {number} duration - Duration in milliseconds
				 * @returns {Promise<void>}
				 */
				const fadeOut = async function(element, duration) {
					if (!duration) duration = 400;
					return new Promise(function(resolve) {
						element.style.transition = 'opacity ' + duration + 'ms';
						element.style.opacity = '0';

						setTimeout(function() {
							element.style.display = 'none';
							resolve();
						}, duration);
					});
				};


				/**
				 * Show copy status with modern async handling.
				 *
				 * @param {boolean} success - Whether copy was successful
				 * @returns {Promise<void>}
				 */
				const showCopyStatus = async function(success) {
					if (success === undefined) success = true;
					const statusElement = document.getElementById('copy-status');
					if (!statusElement) return;

					if (success) {
						await fadeIn(statusElement);
						setTimeout(async function() {
							await fadeOut(statusElement);
						}, 2000);
					} else {
						alert('<?php echo esc_js( __( 'Failed to copy. Please select and copy manually.', 'brag-book-gallery' ) ); ?>');
					}
				};

				/**
				 * Modern clipboard copy with fallback support.
				 *
				 * @param {string} text - Text to copy
				 * @returns {Promise<boolean>}
				 */
				const copyToClipboard = async function(text) {
					try {
						// Try modern Clipboard API first
						if (navigator.clipboard && navigator.clipboard.writeText) {
							await navigator.clipboard.writeText(text);
							return true;
						}

						// Fallback to execCommand
						const textarea = document.getElementById('system-info-output');
						if (textarea) {
							textarea.select();
							textarea.setSelectionRange(0, 99999);
							return document.execCommand('copy');
						}

						return false;
					} catch (error) {
						console.error('Copy failed:', error);
						return false;
					}
				};

				// Copy button handler
				const copyBtn = document.getElementById('copy-system-info');
				if (copyBtn) {
					copyBtn.addEventListener('click', async function() {
						const textarea = document.getElementById('system-info-output');
						if (!textarea) return;

						const success = await copyToClipboard(textarea.value);
						await showCopyStatus(success);
					});
				}


				// Download button handler with modern approach
				const downloadBtn = document.getElementById('download-system-info');
				if (downloadBtn) {
					downloadBtn.addEventListener('click', function() {
						const textarea = document.getElementById('system-info-output');
						if (!textarea) return;

						try {
							const text = textarea.value;
							const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
							const url = URL.createObjectURL(blob);

							const a = document.createElement('a');
							a.href = url;
							a.download = 'bragbook-system-info-' + new Date().toISOString().slice(0, 10) + '.txt';
							a.style.display = 'none';

							document.body.appendChild(a);
							a.click();
							document.body.removeChild(a);

							// Clean up the object URL
							setTimeout(function() { URL.revokeObjectURL(url); }, 100);
						} catch (error) {
							console.error('Download failed:', error);
							alert('<?php echo esc_js( __( 'Failed to download file. Please try again.', 'brag-book-gallery' ) ); ?>');
						}
					});
				}
			});
			</script>
			<?php

			$this->metrics['render_time'] = microtime( true ) - $start_time;
			$this->maybe_log_performance();

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			echo '<div class="notice notice-error"><p>' .
				esc_html( sprintf(
					/* translators: %s: Error message */
					__( 'Failed to render system info tool: %s', 'brag-book-gallery' ),
					$e->getMessage()
				) ) . '</p></div>';
		}
	}

	/**
	 * Get cached system information or generate new.
	 *
	 * Retrieves system information from cache if available and fresh,
	 * otherwise generates new information with performance monitoring.
	 *
	 * @since 3.0.0
	 *
	 * @return string Complete system information report.
	 * @throws Exception If system info generation fails.
	 */
	private function get_cached_system_info(): string {
		try {
			// Check cache first
			$cached = get_transient( self::CACHE_PREFIX . 'report' );
			if ( false !== $cached && is_string( $cached ) ) {
				return $cached;
			}

			// Generate new system info
			$system_info = $this->generate_system_info();

			// Cache for future requests
			set_transient( self::CACHE_PREFIX . 'report', $system_info, self::CACHE_DURATION );

			return $system_info;

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return __( 'Error: Unable to generate system information.', 'brag-book-gallery' );
		}
	}

	/**
	 * Generate comprehensive system information report.
	 *
	 * Collects detailed system information including plugin data, WordPress configuration,
	 * server environment, PHP settings, database details, and gallery-specific settings.
	 * All sensitive data is automatically redacted for security.
	 *
	 * @since 3.0.0
	 *
	 * @return string Complete system information report in text format.
	 * @throws Exception If critical system information cannot be retrieved.
	 */
	private function generate_system_info(): string {
		$start_time = microtime( true );
		$info = [];
		$this->sections = [];

		try {
			// Header
			$info[] = '=== BRAGBook Gallery System Information ===';
			$info[] = 'Generated: ' . current_time( 'Y-m-d H:i:s' );
			$info[] = 'Report Version: 3.0.0';
			$info[] = '';

			// Plugin Information
			$info[] = '--- PLUGIN INFORMATION ---';
			$this->sections['plugin'] = $this->get_plugin_info();
			$info[] = $this->sections['plugin'];
			$info[] = '';

			// WordPress Information
			$info[] = '--- WORDPRESS INFORMATION ---';
			$this->sections['wordpress'] = $this->get_wordpress_info();
			$info[] = $this->sections['wordpress'];
			$info[] = '';

			// Server Information
			$info[] = '--- SERVER INFORMATION ---';
			$this->sections['server'] = $this->get_server_info();
			$info[] = $this->sections['server'];
			$info[] = '';

			// PHP Information
			$info[] = '--- PHP INFORMATION ---';
			$this->sections['php'] = $this->get_php_info();
			$info[] = $this->sections['php'];
			$info[] = '';

			// Database Information
			$info[] = '--- DATABASE INFORMATION ---';
			$this->sections['database'] = $this->get_database_info();
			$info[] = $this->sections['database'];
			$info[] = '';

			// Active Theme
			$info[] = '--- ACTIVE THEME ---';
			$this->sections['theme'] = $this->get_theme_info();
			$info[] = $this->sections['theme'];
			$info[] = '';

			// Active Plugins
			$info[] = '--- ACTIVE PLUGINS ---';
			$this->sections['plugins'] = $this->get_plugins_info();
			$info[] = $this->sections['plugins'];
			$info[] = '';

			// Gallery Configuration
			$info[] = '--- GALLERY CONFIGURATION ---';
			$this->sections['gallery'] = $this->get_gallery_config();
			$info[] = $this->sections['gallery'];
			$info[] = '';

			// Browser Information
			$info[] = '--- BROWSER INFORMATION ---';
			$this->sections['browser'] = $this->get_browser_info();
			$info[] = $this->sections['browser'];
			$info[] = '';

			// WordPress Settings
			$info[] = '--- WORDPRESS SETTINGS ---';
			$this->sections['settings'] = $this->get_wp_settings();
			$info[] = $this->sections['settings'];

			// Hosting Provider Detection
			$info[] = '';
			$info[] = '--- HOSTING ENVIRONMENT ---';
			$this->sections['hosting'] = $this->get_hosting_info();
			$info[] = $this->sections['hosting'];

			// Performance Metrics
			$this->metrics['generation_time'] = microtime( true ) - $start_time;
			$info[] = '';
			$info[] = sprintf(
				'Report generated in %.3f seconds',
				$this->metrics['generation_time']
			);

			// Redact sensitive information
			$report = implode( "\n", $info );
			return $this->redact_sensitive_data( $report );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return '=== ERROR GENERATING SYSTEM INFORMATION ===' . "\n" .
				   'Error: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive plugin information.
	 *
	 * Retrieves plugin metadata, current mode, and installation details
	 * for debugging and support purposes. Includes version compatibility checks.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted plugin information string.
	 * @throws Exception If plugin data cannot be retrieved.
	 */
	private function get_plugin_info(): string {
		try {
			$plugin_file = WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php';
			if ( ! file_exists( $plugin_file ) ) {
				throw new Exception( 'Plugin file not found' );
			}

			$plugin_data = get_plugin_data( $plugin_file );
			$mode = get_option( 'brag_book_gallery_mode', 'javascript' );

			$info = [];
			$info[] = 'Plugin Name: ' . ( $plugin_data['Name'] ?? 'BRAGBook Gallery' );
			$info[] = 'Plugin Version: ' . ( $plugin_data['Version'] ?? '3.0.0' );
			$info[] = 'Plugin URI: ' . ( $plugin_data['PluginURI'] ?? 'N/A' );
			$info[] = 'Author: ' . ( $plugin_data['Author'] ?? 'N/A' );
			$info[] = 'Text Domain: ' . ( $plugin_data['TextDomain'] ?? 'brag-book-gallery' );
			$info[] = 'Active Mode: ' . ucfirst( $mode );
			$info[] = 'PHP Version Required: 8.2+ (Current: ' . phpversion() . ')';
			$info[] = 'PHP Compatibility: ' . ( version_compare( phpversion(), '8.2', '>=' ) ? 'YES' : 'NO - INCOMPATIBLE' );
			$info[] = 'WordPress Version Required: 6.8+';
			$info[] = 'Plugin Path: ' . WP_PLUGIN_DIR . '/brag-book-gallery/';
			$info[] = 'Plugin Active: ' . ( is_plugin_active( 'brag-book-gallery/brag-book-gallery.php' ) ? 'Yes' : 'No' );
			$info[] = 'Debug Mode: ' . ( get_option( 'brag_book_gallery_debug_mode', 'no' ) === 'yes' ? 'Enabled' : 'Disabled' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Plugin information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive WordPress configuration information.
	 *
	 * Collects WordPress version, site URLs, multisite status, debug settings,
	 * and other WordPress-specific configuration details.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted WordPress information string.
	 */
	private function get_wordpress_info(): string {
		try {
			global $wp_version;

			$info = [];
			$info[] = 'WordPress Version: ' . $wp_version;
			$info[] = 'Site URL: ' . get_site_url();
			$info[] = 'Home URL: ' . get_home_url();
			$info[] = 'Multisite: ' . ( is_multisite() ? 'Yes (Network: ' . get_network()->domain . ')' : 'No' );
			$info[] = 'Language: ' . get_locale();
			$info[] = 'Timezone: ' . wp_timezone_string();
			$info[] = 'Date Format: ' . get_option( 'date_format' );
			$info[] = 'Time Format: ' . get_option( 'time_format' );
			$info[] = 'Admin Email: ' . $this->redact_email( get_option( 'admin_email' ) );
			$info[] = 'Debug Mode: ' . ( WP_DEBUG ? 'Enabled' : 'Disabled' );
			$info[] = 'Debug Display: ' . ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? 'Enabled' : 'Disabled' );
			$info[] = 'Debug Log: ' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? 'Enabled' : 'Disabled' );
			$info[] = 'Script Debug: ' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'Enabled' : 'Disabled' );
			$info[] = 'Memory Limit (WP): ' . WP_MEMORY_LIMIT;
			$info[] = 'Max Memory Limit (WP): ' . WP_MAX_MEMORY_LIMIT;

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'WordPress information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive server environment information.
	 *
	 * Collects server software details, protocol information, execution limits,
	 * and other server-specific configuration that affects plugin functionality.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted server information string.
	 */
	private function get_server_info(): string {
		try {
			$info = [];
			$info[] = 'Server Software: ' . ( $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' );
			$info[] = 'Server Protocol: ' . ( $_SERVER['SERVER_PROTOCOL'] ?? 'N/A' );
			$info[] = 'Server Name: ' . ( $_SERVER['SERVER_NAME'] ?? 'N/A' );
			$info[] = 'Server Port: ' . ( $_SERVER['SERVER_PORT'] ?? 'N/A' );
			$info[] = 'Document Root: ' . ( $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' );
			$info[] = 'HTTPS: ' . ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 'Yes' : 'No' );
			$info[] = 'Server Time: ' . date( 'Y-m-d H:i:s' );
			$info[] = 'Max Execution Time: ' . ini_get( 'max_execution_time' ) . ' seconds';
			$info[] = 'Max Input Time: ' . ini_get( 'max_input_time' ) . ' seconds';
			$info[] = 'Max Input Vars: ' . ini_get( 'max_input_vars' );
			$info[] = 'cURL: ' . ( function_exists( 'curl_version' ) ? 'Enabled (v' . curl_version()['version'] . ')' : 'Disabled' );
			$info[] = 'OpenSSL: ' . ( extension_loaded( 'openssl' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'JSON: ' . ( extension_loaded( 'json' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'Opcache: ' . ( extension_loaded( 'Zend OPcache' ) ? 'Enabled' : 'Disabled' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Server information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive PHP environment information.
	 *
	 * Collects PHP version, configuration settings, memory limits, extensions,
	 * and other PHP-specific details crucial for plugin functionality.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted PHP information string.
	 */
	private function get_php_info(): string {
		try {
			$info = [];
			$info[] = 'PHP Version: ' . phpversion();
			$info[] = 'PHP SAPI: ' . php_sapi_name();
			$info[] = 'Memory Limit: ' . ini_get( 'memory_limit' );
			$info[] = 'Upload Max Filesize: ' . ini_get( 'upload_max_filesize' );
			$info[] = 'Post Max Size: ' . ini_get( 'post_max_size' );
			$info[] = 'Display Errors: ' . ( ini_get( 'display_errors' ) ? 'On' : 'Off' );
			$info[] = 'Error Reporting: ' . error_reporting();
			$info[] = 'GD Library: ' . ( extension_loaded( 'gd' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'Imagick: ' . ( extension_loaded( 'imagick' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'ZIP Archive: ' . ( class_exists( 'ZipArchive' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'DOMDocument: ' . ( class_exists( 'DOMDocument' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'SimpleXML: ' . ( extension_loaded( 'simplexml' ) ? 'Enabled' : 'Disabled' );
			$info[] = 'MB String: ' . ( extension_loaded( 'mbstring' ) ? 'Enabled' : 'Disabled' );

			// PHP Extensions (limited to avoid overflow)
			$extensions = get_loaded_extensions();
			$info[] = 'Total Extensions: ' . count( $extensions );
			$info[] = 'Key Extensions: ' . implode( ', ', array_slice( $extensions, 0, 20 ) ) . ( count( $extensions ) > 20 ? '...' : '' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'PHP information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive database configuration information.
	 *
	 * Collects database connection details, MySQL version, character set,
	 * and other database-specific configuration information.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted database information string.
	 */
	private function get_database_info(): string {
		try {
			global $wpdb;

			$info = [];
			$info[] = 'Database Host: ' . DB_HOST;
			$info[] = 'Database Name: ' . DB_NAME;
			$info[] = 'Database User: ' . substr( DB_USER, 0, 3 ) . '***';
			$info[] = 'Database Charset: ' . DB_CHARSET;
			$info[] = 'Database Collate: ' . ( DB_COLLATE ?: 'Default' );
			$info[] = 'Table Prefix: ' . $wpdb->prefix;

			// MySQL Version
			$mysql_version = $wpdb->get_var( 'SELECT VERSION()' );
			$info[] = 'MySQL Version: ' . ( $mysql_version ?: 'N/A' );

			// Database size estimate
			$table_count = $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'" );
			$info[] = 'Total Tables: ' . ( $table_count ?: 'N/A' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Database information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive active theme information.
	 *
	 * Collects theme details including name, version, author, template hierarchy,
	 * and child theme information if applicable.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted theme information string.
	 */
	private function get_theme_info(): string {
		try {
			$theme = wp_get_theme();

			$info = [];
			$info[] = 'Theme Name: ' . $theme->get( 'Name' );
			$info[] = 'Theme Version: ' . $theme->get( 'Version' );
			$info[] = 'Theme URI: ' . ( $theme->get( 'ThemeURI' ) ?: 'N/A' );
			$info[] = 'Theme Author: ' . $theme->get( 'Author' );
			$info[] = 'Theme Author URI: ' . ( $theme->get( 'AuthorURI' ) ?: 'N/A' );
			$info[] = 'Theme Template: ' . $theme->get_template();
			$info[] = 'Theme Stylesheet: ' . $theme->get_stylesheet();
			$info[] = 'Theme Directory: ' . basename( $theme->get_stylesheet_directory() );
			$info[] = 'Is Child Theme: ' . ( is_child_theme() ? 'Yes' : 'No' );
			$info[] = 'Theme Status: ' . ( $theme->errors() ? 'Has Errors' : 'OK' );

			if ( is_child_theme() ) {
				$parent = $theme->parent();
				if ( $parent ) {
					$info[] = 'Parent Theme: ' . $parent->get( 'Name' );
					$info[] = 'Parent Version: ' . $parent->get( 'Version' );
				}
			}

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Theme information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive installed plugins information.
	 *
	 * Collects information about all installed plugins including their
	 * activation status, versions, and authors.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted plugins information string.
	 */
	private function get_plugins_info(): string {
		try {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins = get_plugins();
			$active_plugins = get_option( 'active_plugins', [] );
			$network_active = is_multisite() ? get_site_option( 'active_sitewide_plugins', [] ) : [];

			$info = [];
			$info[] = 'Total Plugins: ' . count( $plugins );
			$info[] = 'Active Plugins: ' . count( $active_plugins );
			if ( is_multisite() ) {
				$info[] = 'Network Active: ' . count( $network_active );
			}
			$info[] = '';

			foreach ( $plugins as $plugin_path => $plugin_data ) {
				$status = match ( true ) {
					array_key_exists( $plugin_path, $network_active ) => 'Network Active',
					in_array( $plugin_path, $active_plugins, true ) => 'Active',
					default => 'Inactive',
				};

				$info[] = sprintf(
					'%s v%s by %s [%s]',
					$plugin_data['Name'],
					$plugin_data['Version'] ?: 'N/A',
					strip_tags( $plugin_data['Author'] ),
					$status
				);
			}

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Plugin information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive gallery plugin configuration.
	 *
	 * Collects all gallery-specific settings including API configuration,
	 * display settings, performance options, and pages using gallery shortcodes.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted gallery configuration string.
	 */
	private function get_gallery_config(): string {
		try {
			$info = [];

			// API Configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', [] );
			$website_ids = get_option( 'brag_book_gallery_website_property_id', [] );

			$info[] = 'API Configured: ' . ( ! empty( $api_tokens ) ? 'Yes' : 'No' );
			$info[] = 'Number of API Tokens: ' . count( (array) $api_tokens );
			$info[] = 'Number of Website IDs: ' . count( (array) $website_ids );

			// Gallery Pages
			$pages_with_gallery = get_posts( [
				'post_type' => 'page',
				'post_status' => 'publish',
				's' => '[brag_book_gallery',
				'posts_per_page' => 10, // Limit to prevent memory issues
				'fields' => 'ids',
			] );

			$info[] = 'Pages with Gallery Shortcode: ' . count( $pages_with_gallery );
			if ( ! empty( $pages_with_gallery ) ) {
				foreach ( array_slice( $pages_with_gallery, 0, 5 ) as $page_id ) {
					$info[] = '  - ' . get_the_title( $page_id ) . ' (ID: ' . $page_id . ')';
				}
				if ( count( $pages_with_gallery ) > 5 ) {
					$info[] = '  ... and ' . ( count( $pages_with_gallery ) - 5 ) . ' more';
				}
			}

			// Settings
			$info[] = '';
			$info[] = 'Display Settings:';
			$info[] = '  Columns: ' . get_option( 'brag_book_gallery_columns', '3' );
			$info[] = '  Items Per Page: ' . get_option( 'brag_book_gallery_items_per_page', '10' );
			$info[] = '  Enable Sharing: ' . get_option( 'brag_book_gallery_enable_sharing', 'no' );
			$info[] = '  Enable Lightbox: ' . get_option( 'brag_book_gallery_enable_lightbox', 'no' );
			$info[] = '  Enable Filtering: ' . get_option( 'brag_book_gallery_enable_filtering', 'yes' );
			$info[] = '  Use Poppins Font: ' . get_option( 'brag_book_gallery_use_custom_font', 'yes' );
			$info[] = '  Image Display Mode: ' . get_option( 'brag_book_gallery_image_display_mode', 'single' );
			$info[] = '  Lazy Load: ' . get_option( 'brag_book_gallery_lazy_load', 'yes' );

			$info[] = '';
			$info[] = 'Performance Settings:';
			$info[] = '  Cache Duration: ' . get_option( 'brag_book_gallery_cache_duration', '3600' ) . ' seconds';
			$info[] = '  AJAX Timeout: ' . get_option( 'brag_book_gallery_ajax_timeout', '30' ) . ' seconds';
			$info[] = '  Minify Assets: ' . get_option( 'brag_book_gallery_minify_assets', 'no' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Gallery configuration unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive browser and client information.
	 *
	 * Parses user agent to extract browser details, operating system,
	 * and client IP information for debugging purposes.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted browser information string.
	 */
	private function get_browser_info(): string {
		try {
			$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

			$info = [];
			$info[] = 'User Agent: ' . substr( $user_agent, 0, 200 ); // Limit length for security

			// Parse user agent using modern approach
			[$browser, $version] = $this->parse_user_agent( $user_agent );
			$info[] = 'Browser: ' . $browser . ( $version ? ' v' . $version : '' );

			// Operating System using modern parsing
			$os = $this->parse_operating_system( $user_agent );
			$info[] = 'Operating System: ' . $os;

			// Client IP (partially redacted for privacy)
			$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
			if ( $client_ip !== 'N/A' ) {
				// Redact last octet for privacy
				$ip_parts = explode( '.', $client_ip );
				if ( count( $ip_parts ) === 4 ) {
					$ip_parts[3] = 'xxx';
					$client_ip = implode( '.', $ip_parts );
				}
			}
			$info[] = 'Client IP: ' . $client_ip;

			// Additional client info
			$info[] = 'Accept Language: ' . ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A' );
			$info[] = 'DNT Header: ' . ( isset( $_SERVER['HTTP_DNT'] ) ? $_SERVER['HTTP_DNT'] : 'Not Set' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'Browser information unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Get comprehensive WordPress configuration settings.
	 *
	 * Collects WordPress-specific settings including permalinks, media settings,
	 * user registration, upload directories, and other core WordPress configurations.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted WordPress settings string.
	 */
	private function get_wp_settings(): string {
		try {
			$info = [];

			$info[] = 'Permalink Structure: ' . ( get_option( 'permalink_structure' ) ?: 'Default' );
			$info[] = 'Show on Front: ' . get_option( 'show_on_front', 'posts' );

			// Get page titles for front/blog pages
			$front_page_id = get_option( 'page_on_front' );
			$blog_page_id = get_option( 'page_for_posts' );
			$info[] = 'Page on Front: ' . ( $front_page_id ? get_the_title( $front_page_id ) . ' (ID: ' . $front_page_id . ')' : 'Not Set' );
			$info[] = 'Page for Posts: ' . ( $blog_page_id ? get_the_title( $blog_page_id ) . ' (ID: ' . $blog_page_id . ')' : 'Not Set' );

			$info[] = 'Blog Charset: ' . get_option( 'blog_charset', 'UTF-8' );
			$info[] = 'Users Can Register: ' . ( get_option( 'users_can_register' ) ? 'Yes' : 'No' );
			$info[] = 'Default Role: ' . get_option( 'default_role', 'subscriber' );
			$info[] = 'Uploads Use Year/Month: ' . ( get_option( 'uploads_use_yearmonth_folders' ) ? 'Yes' : 'No' );
			$info[] = 'Thumbnail Size: ' . get_option( 'thumbnail_size_w' ) . 'x' . get_option( 'thumbnail_size_h' );
			$info[] = 'Medium Size: ' . get_option( 'medium_size_w' ) . 'x' . get_option( 'medium_size_h' );
			$info[] = 'Large Size: ' . get_option( 'large_size_w' ) . 'x' . get_option( 'large_size_h' );

			// Upload directory
			$upload_dir = wp_upload_dir();
			$info[] = 'Upload Path: ' . basename( $upload_dir['basedir'] ); // Show only basename for security
			$info[] = 'Upload URL: ' . $upload_dir['baseurl'];
			$info[] = 'Writable: ' . ( wp_is_writable( $upload_dir['basedir'] ) ? 'Yes' : 'No' );

			// Additional settings
			$info[] = 'DISALLOW_FILE_EDIT: ' . ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ? 'Yes' : 'No' );
			$info[] = 'DISALLOW_FILE_MODS: ' . ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ? 'Yes' : 'No' );
			$info[] = 'AUTOMATIC_UPDATER_DISABLED: ' . ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ? 'Yes' : 'No' );

			return implode( "\n", $info );

		} catch ( Exception $e ) {
			$this->handle_error( $e, __METHOD__ );
			return 'WordPress settings unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Parse user agent to extract browser information.
	 *
	 * @since 3.0.0
	 *
	 * @param string $user_agent User agent string.
	 * @return array{0: string, 1: string} Browser name and version.
	 */
	private function parse_user_agent( string $user_agent ): array {
		return match ( true ) {
			preg_match( '/MSIE ([0-9]+)/', $user_agent, $matches ) => ['Internet Explorer', $matches[1] ?? ''],
			preg_match( '/Trident\/[0-9]+/', $user_agent ) => ['Internet Explorer', '11+'],
			preg_match( '/Edge\/([0-9]+)/', $user_agent, $matches ) => ['Microsoft Edge', $matches[1] ?? ''],
			preg_match( '/Edg\/([0-9]+)/', $user_agent, $matches ) => ['Microsoft Edge (Chromium)', $matches[1] ?? ''],
			preg_match( '/Firefox\/([0-9]+)/', $user_agent, $matches ) => ['Firefox', $matches[1] ?? ''],
			preg_match( '/Chrome\/([0-9]+)/', $user_agent, $matches ) => ['Chrome', $matches[1] ?? ''],
			preg_match( '/Safari\/([0-9]+)/', $user_agent, $matches ) => [
				'Safari',
				preg_match( '/Version\/([0-9]+)/', $user_agent, $v_matches ) ? $v_matches[1] : ''
			],
			default => ['Unknown', '']
		};
	}

	/**
	 * Parse user agent to extract operating system information.
	 *
	 * @since 3.0.0
	 *
	 * @param string $user_agent User agent string.
	 * @return string Operating system name and version.
	 */
	private function parse_operating_system( string $user_agent ): string {
		return match ( true ) {
			preg_match( '/Windows NT ([0-9.]+)/', $user_agent, $matches ) => 'Windows ' . $matches[1],
			preg_match( '/Mac OS X ([0-9._]+)/', $user_agent, $matches ) => 'macOS ' . str_replace( '_', '.', $matches[1] ),
			preg_match( '/Linux/', $user_agent ) => 'Linux',
			preg_match( '/iPhone|iPad|iPod/', $user_agent ) => 'iOS',
			preg_match( '/Android ([0-9.]+)/', $user_agent, $matches ) => 'Android ' . $matches[1],
			default => 'Unknown'
		};
	}

	/**
	 * Get hosting provider information.
	 *
	 * Detects hosting provider and provides specific configuration details.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted hosting information.
	 */
	private function get_hosting_info(): string {
		$info = [];

		// Detect hosting provider
		$provider = $this->detect_hosting_provider();
		$info[] = 'Hosting Provider: ' . $provider;

		// Add provider-specific details
		$info = array_merge( $info, $this->get_provider_specific_info( $provider ) );

		return implode( "\n", $info );
	}

	/**
	 * Detect hosting provider.
	 *
	 * @since 3.0.0
	 *
	 * @return string Detected hosting provider name.
	 */
	private function detect_hosting_provider(): string {
		return match ( true ) {
			defined( 'WPE_APIKEY' ) => 'WP Engine',
			defined( 'KINSTAMU_VERSION' ) => 'Kinsta',
			defined( 'IS_PRESSABLE' ) => 'Pressable',
			defined( 'FLYWHEEL_CONFIG_DIR' ) => 'Flywheel',
			defined( 'WPCOMSH_VERSION' ) => 'WordPress.com',
			defined( 'VIP_GO_APP_ID' ) => 'WordPress VIP',
			file_exists( '/etc/siteground' ) => 'SiteGround',
			str_contains( gethostname(), 'dreamhost' ) => 'DreamHost',
			defined( 'GD_SYSTEM_PLUGIN_DIR' ) => 'GoDaddy',
			defined( 'MM_BASE_DIR' ) => 'Bluehost',
			file_exists( '/opt/bitnami' ) => 'Bitnami',
			default => 'Standard/Unknown',
		};
	}

	/**
	 * Get provider-specific information.
	 *
	 * @since 3.0.0
	 *
	 * @param string $provider Hosting provider name.
	 * @return array<string> Provider-specific details.
	 */
	private function get_provider_specific_info( string $provider ): array {
		return match ( $provider ) {
			'WP Engine' => [
				'WPE Account: ' . ( defined( 'PWP_NAME' ) ? PWP_NAME : 'N/A' ),
				'WPE Cluster: ' . ( defined( 'WPE_CLUSTER' ) ? WPE_CLUSTER : 'N/A' ),
			],
			'Kinsta' => [
				'Kinsta Cache: ' . ( defined( 'KINSTA_CACHE' ) ? 'Enabled' : 'Disabled' ),
			],
			'WordPress VIP' => [
				'VIP App ID: ' . ( defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : 'N/A' ),
				'VIP Environment: ' . ( defined( 'VIP_GO_ENV' ) ? VIP_GO_ENV : 'N/A' ),
			],
			default => [],
		};
	}

	/**
	 * Redact sensitive data from the report.
	 *
	 * @since 3.0.0
	 *
	 * @param string $report The system information report.
	 * @return string Report with sensitive data redacted.
	 */
	private function redact_sensitive_data( string $report ): string {
		// Redact API tokens and keys
		foreach ( self::SENSITIVE_PATTERNS as $pattern ) {
			$report = preg_replace(
				'/(' . preg_quote( $pattern, '/' ) . '[^:]*:)\s*(.+)$/mi',
				'$1 [REDACTED]',
				$report
			);
		}

		// Redact database password
		$report = str_replace( DB_PASSWORD, '[REDACTED]', $report );

		return $report;
	}

	/**
	 * Redact email address for privacy.
	 *
	 * @since 3.0.0
	 *
	 * @param string $email Email address to redact.
	 * @return string Partially redacted email.
	 */
	private function redact_email( string $email ): string {
		if ( empty( $email ) ) {
			return 'N/A';
		}

		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return '[INVALID EMAIL]';
		}

		$username = $parts[0];
		$domain = $parts[1];

		// Show first character and last character of username
		if ( strlen( $username ) > 2 ) {
			$username = substr( $username, 0, 1 ) . '***' . substr( $username, -1 );
		} else {
			$username = '***';
		}

		return $username . '@' . $domain;
	}

	/**
	 * Handle errors with logging.
	 *
	 * @since 3.0.0
	 *
	 * @param Exception $e       Exception to handle.
	 * @param string    $context Context where error occurred.
	 * @return void
	 */
	private function handle_error( Exception $e, string $context ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'BRAGBook Gallery System Info Error in %s: %s',
				$context,
				$e->getMessage()
			) );
		}
	}

	/**
	 * Log performance metrics if needed.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function maybe_log_performance(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $this->metrics ) ) {
			$total_time = array_sum( $this->metrics );
			if ( $total_time > 1.0 ) { // Log if operations take more than 1 second
				error_log( sprintf(
					'BRAGBook Gallery System Info Performance: %s',
					wp_json_encode( $this->metrics )
				) );
			}
		}
	}
}
