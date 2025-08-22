<?php
/**
 * System Info Debug Tool
 *
 * Collects and displays comprehensive system information for debugging.
 *
 * @package    BRAGBookGallery
 * @subpackage Admin\Debug_Tools
 * @since      3.0.0
 */

namespace BRAGBookGallery\Admin\Debug_Tools;

if ( ! defined( 'WPINC' ) ) {
	die( 'Direct access not allowed' );
}

/**
 * System Info Tool Class
 *
 * @since 3.0.0
 */
class System_Info {

	/**
	 * Render the system info tool interface
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="tool-section">
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

			<textarea id="system-info-output" readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; background: #f5f5f5; padding: 15px; border: 1px solid #ddd; resize: vertical;"><?php echo esc_textarea( $this->generate_system_info() ); ?></textarea>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', () => {
			/**
			 * Modern fade-in effect using ES6 and async/await.
			 *
			 * @param {HTMLElement} element - Element to fade in
			 * @param {number} duration - Duration in milliseconds
			 * @returns {Promise<void>}
			 */
			const fadeIn = async (element, duration = 400) => {
				return new Promise((resolve) => {
					element.style.display = 'block';
					element.style.opacity = '0';
					
					setTimeout(() => {
						element.style.transition = `opacity ${duration}ms`;
						element.style.opacity = '1';
						setTimeout(resolve, duration);
					}, 10);
				});
			};
			
			/**
			 * Modern fade-out effect using ES6 and async/await.
			 *
			 * @param {HTMLElement} element - Element to fade out
			 * @param {number} duration - Duration in milliseconds
			 * @returns {Promise<void>}
			 */
			const fadeOut = async (element, duration = 400) => {
				return new Promise((resolve) => {
					element.style.transition = `opacity ${duration}ms`;
					element.style.opacity = '0';
					
					setTimeout(() => {
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
			const showCopyStatus = async (success = true) => {
				const statusElement = document.getElementById('copy-status');
				if (!statusElement) return;
				
				if (success) {
					await fadeIn(statusElement);
					setTimeout(async () => {
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
			const copyToClipboard = async (text) => {
				try {
					// Try modern Clipboard API first
					if (navigator.clipboard?.writeText) {
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
				copyBtn.addEventListener('click', async () => {
					const textarea = document.getElementById('system-info-output');
					if (!textarea) return;
					
					const success = await copyToClipboard(textarea.value);
					await showCopyStatus(success);
				});
			}

			
			// Download button handler with modern approach
			const downloadBtn = document.getElementById('download-system-info');
			if (downloadBtn) {
				downloadBtn.addEventListener('click', () => {
					const textarea = document.getElementById('system-info-output');
					if (!textarea) return;
					
					try {
						const text = textarea.value;
						const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
						const url = URL.createObjectURL(blob);
						
						const a = document.createElement('a');
						a.href = url;
						a.download = `bragbook-system-info-${new Date().toISOString().slice(0, 10)}.txt`;
						a.style.display = 'none';
						
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						
						// Clean up the object URL
						setTimeout(() => URL.revokeObjectURL(url), 100);
					} catch (error) {
						console.error('Download failed:', error);
						alert('<?php echo esc_js( __( 'Failed to download file. Please try again.', 'brag-book-gallery' ) ); ?>');
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Generate comprehensive system information report.
	 *
	 * Collects detailed system information including plugin data, WordPress configuration,
	 * server environment, PHP settings, database details, and gallery-specific settings.
	 *
	 * @since 3.0.0
	 *
	 * @return string Complete system information report in text format.
	 */
	private function generate_system_info(): string {
		$info = [];

		// Header
		$info[] = '=== BRAGBook Gallery System Information ===';
		$info[] = 'Generated: ' . current_time( 'Y-m-d H:i:s' );
		$info[] = '';

		// Plugin Information
		$info[] = '--- PLUGIN INFORMATION ---';
		$info[] = $this->get_plugin_info();
		$info[] = '';

		// WordPress Information
		$info[] = '--- WORDPRESS INFORMATION ---';
		$info[] = $this->get_wordpress_info();
		$info[] = '';

		// Server Information
		$info[] = '--- SERVER INFORMATION ---';
		$info[] = $this->get_server_info();
		$info[] = '';

		// PHP Information
		$info[] = '--- PHP INFORMATION ---';
		$info[] = $this->get_php_info();
		$info[] = '';

		// Database Information
		$info[] = '--- DATABASE INFORMATION ---';
		$info[] = $this->get_database_info();
		$info[] = '';

		// Active Theme
		$info[] = '--- ACTIVE THEME ---';
		$info[] = $this->get_theme_info();
		$info[] = '';

		// Active Plugins
		$info[] = '--- ACTIVE PLUGINS ---';
		$info[] = $this->get_plugins_info();
		$info[] = '';

		// Gallery Configuration
		$info[] = '--- GALLERY CONFIGURATION ---';
		$info[] = $this->get_gallery_config();
		$info[] = '';

		// Browser Information
		$info[] = '--- BROWSER INFORMATION ---';
		$info[] = $this->get_browser_info();
		$info[] = '';

		// WordPress Settings
		$info[] = '--- WORDPRESS SETTINGS ---';
		$info[] = $this->get_wp_settings();

		return implode( "\n", $info );
	}

	/**
	 * Get comprehensive plugin information.
	 *
	 * Retrieves plugin metadata, current mode, and installation details
	 * for debugging and support purposes.
	 *
	 * @since 3.0.0
	 *
	 * @return string Formatted plugin information string.
	 */
	private function get_plugin_info(): string {
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$mode = get_option( 'brag_book_gallery_mode', 'javascript' );

		$info = [];
		$info[] = 'Plugin Name: ' . ( $plugin_data['Name'] ?? 'BRAGBook Gallery' );
		$info[] = 'Plugin Version: ' . ( $plugin_data['Version'] ?? '3.0.0' );
		$info[] = 'Plugin URI: ' . ( $plugin_data['PluginURI'] ?? 'N/A' );
		$info[] = 'Author: ' . ( $plugin_data['Author'] ?? 'N/A' );
		$info[] = 'Text Domain: ' . ( $plugin_data['TextDomain'] ?? 'brag-book-gallery' );
		$info[] = 'Active Mode: ' . ucfirst( $mode );
		$info[] = 'PHP Version Required: 8.2+';
		$info[] = 'WordPress Version Required: 6.8+';
		$info[] = 'Plugin Path: ' . WP_PLUGIN_DIR . '/brag-book-gallery/';

		return implode( "\n", $info );
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
		global $wp_version;

		$info = [];
		$info[] = 'WordPress Version: ' . $wp_version;
		$info[] = 'Site URL: ' . get_site_url();
		$info[] = 'Home URL: ' . get_home_url();
		$info[] = 'Multisite: ' . ( is_multisite() ? 'Yes' : 'No' );
		$info[] = 'Language: ' . get_locale();
		$info[] = 'Timezone: ' . wp_timezone_string();
		$info[] = 'Date Format: ' . get_option( 'date_format' );
		$info[] = 'Time Format: ' . get_option( 'time_format' );
		$info[] = 'Admin Email: ' . get_option( 'admin_email' );
		$info[] = 'Debug Mode: ' . ( WP_DEBUG ? 'Enabled' : 'Disabled' );
		$info[] = 'Debug Display: ' . ( WP_DEBUG_DISPLAY ? 'Enabled' : 'Disabled' );
		$info[] = 'Debug Log: ' . ( WP_DEBUG_LOG ? 'Enabled' : 'Disabled' );
		$info[] = 'Script Debug: ' . ( SCRIPT_DEBUG ? 'Enabled' : 'Disabled' );

		return implode( "\n", $info );
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

		return implode( "\n", $info );
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

		// PHP Extensions
		$extensions = get_loaded_extensions();
		$info[] = 'Loaded Extensions: ' . implode( ', ', $extensions );

		return implode( "\n", $info );
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
		global $wpdb;

		$info = [];
		$info[] = 'Database Host: ' . DB_HOST;
		$info[] = 'Database Name: ' . DB_NAME;
		$info[] = 'Database User: ' . DB_USER;
		$info[] = 'Database Charset: ' . DB_CHARSET;
		$info[] = 'Database Collate: ' . DB_COLLATE;
		$info[] = 'Table Prefix: ' . $wpdb->prefix;

		// MySQL Version
		$mysql_version = $wpdb->get_var( "SELECT VERSION()" );
		$info[] = 'MySQL Version: ' . $mysql_version;

		return implode( "\n", $info );
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
		$theme = wp_get_theme();

		$info = [];
		$info[] = 'Theme Name: ' . $theme->get( 'Name' );
		$info[] = 'Theme Version: ' . $theme->get( 'Version' );
		$info[] = 'Theme URI: ' . $theme->get( 'ThemeURI' );
		$info[] = 'Theme Author: ' . $theme->get( 'Author' );
		$info[] = 'Theme Author URI: ' . $theme->get( 'AuthorURI' );
		$info[] = 'Theme Template: ' . $theme->get_template();
		$info[] = 'Theme Stylesheet: ' . $theme->get_stylesheet();
		$info[] = 'Theme Directory: ' . $theme->get_stylesheet_directory();
		$info[] = 'Is Child Theme: ' . ( is_child_theme() ? 'Yes' : 'No' );

		if ( is_child_theme() ) {
			$parent = $theme->parent();
			$info[] = 'Parent Theme: ' . $parent->get( 'Name' );
			$info[] = 'Parent Version: ' . $parent->get( 'Version' );
		}

		return implode( "\n", $info );
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
		$plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		$info = [];
		foreach ( $plugins as $plugin_path => $plugin_data ) {
			$status = in_array( $plugin_path, $active_plugins, true ) ? 'Active' : 'Inactive';
			$info[] = sprintf(
				'%s v%s by %s [%s]',
				$plugin_data['Name'],
				$plugin_data['Version'],
				$plugin_data['Author'],
				$status
			);
		}

		return implode( "\n", $info );
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
			'posts_per_page' => -1,
		] );

		$info[] = 'Pages with Gallery Shortcode: ' . count( $pages_with_gallery );
		if ( ! empty( $pages_with_gallery ) ) {
			foreach ( $pages_with_gallery as $page ) {
				$info[] = '  - ' . $page->post_title . ' (ID: ' . $page->ID . ', URL: ' . get_permalink( $page ) . ')';
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
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

		$info = [];
		$info[] = 'User Agent: ' . $user_agent;

		// Parse user agent using modern approach
		[$browser, $version] = $this->parse_user_agent( $user_agent );
		$info[] = 'Browser: ' . $browser . ( $version ? ' v' . $version : '' );

		// Operating System using modern parsing
		$os = $this->parse_operating_system( $user_agent );
		$info[] = 'Operating System: ' . $os;

		// Client IP
		$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$client_ip .= ' (Forwarded: ' . $_SERVER['HTTP_X_FORWARDED_FOR'] . ')';
		}
		$info[] = 'Client IP: ' . $client_ip;

		return implode( "\n", $info );
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
		$info = [];

		$info[] = 'Permalink Structure: ' . get_option( 'permalink_structure', 'Default' );
		$info[] = 'Show on Front: ' . get_option( 'show_on_front' );
		$info[] = 'Page on Front: ' . get_option( 'page_on_front' );
		$info[] = 'Page for Posts: ' . get_option( 'page_for_posts' );
		$info[] = 'Blog Charset: ' . get_option( 'blog_charset' );
		$info[] = 'Users Can Register: ' . ( get_option( 'users_can_register' ) ? 'Yes' : 'No' );
		$info[] = 'Default Role: ' . get_option( 'default_role' );
		$info[] = 'Uploads Use Year/Month: ' . ( get_option( 'uploads_use_yearmonth_folders' ) ? 'Yes' : 'No' );
		$info[] = 'Thumbnail Size: ' . get_option( 'thumbnail_size_w' ) . 'x' . get_option( 'thumbnail_size_h' );
		$info[] = 'Medium Size: ' . get_option( 'medium_size_w' ) . 'x' . get_option( 'medium_size_h' );
		$info[] = 'Large Size: ' . get_option( 'large_size_w' ) . 'x' . get_option( 'large_size_h' );

		// Upload directory
		$upload_dir = wp_upload_dir();
		$info[] = 'Upload Path: ' . $upload_dir['basedir'];
		$info[] = 'Upload URL: ' . $upload_dir['baseurl'];

		return implode( "\n", $info );
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
}
