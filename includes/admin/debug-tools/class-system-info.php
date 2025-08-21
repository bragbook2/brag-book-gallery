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
					<span class="dashicons dashicons-clipboard" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Copy to Clipboard', 'brag-book-gallery' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="download-system-info">
					<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Download as Text', 'brag-book-gallery' ); ?>
				</button>
				<span id="copy-status" style="margin-left: 10px; color: #46b450; display: none;">
					<?php esc_html_e( 'Copied to clipboard!', 'brag-book-gallery' ); ?>
				</span>
			</div>
			
			<textarea id="system-info-output" readonly style="width: 100%; height: 500px; font-family: monospace; font-size: 12px; background: #f5f5f5; padding: 15px; border: 1px solid #ddd; resize: vertical;"><?php echo esc_textarea( $this->generate_system_info() ); ?></textarea>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Copy to clipboard
			$('#copy-system-info').on('click', function() {
				var textarea = document.getElementById('system-info-output');
				textarea.select();
				textarea.setSelectionRange(0, 99999); // For mobile devices
				
				try {
					document.execCommand('copy');
					$('#copy-status').fadeIn().delay(2000).fadeOut();
				} catch (err) {
					// Fallback for older browsers
					navigator.clipboard.writeText(textarea.value).then(function() {
						$('#copy-status').fadeIn().delay(2000).fadeOut();
					}).catch(function(err) {
						alert('<?php esc_html_e( 'Failed to copy. Please select and copy manually.', 'brag-book-gallery' ); ?>');
					});
				}
			});
			
			// Download as text file
			$('#download-system-info').on('click', function() {
				var text = $('#system-info-output').val();
				var blob = new Blob([text], { type: 'text/plain' });
				var url = window.URL.createObjectURL(blob);
				var a = document.createElement('a');
				a.href = url;
				a.download = 'bragbook-system-info-' + new Date().toISOString().slice(0, 10) + '.txt';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				window.URL.revokeObjectURL(url);
			});
		});
		</script>
		<?php
	}

	/**
	 * Generate comprehensive system information
	 *
	 * @since 3.0.0
	 * @return string System information report
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
	 * Get plugin information
	 *
	 * @since 3.0.0
	 * @return string
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
		$info[] = 'Plugin Path: ' . WP_PLUGIN_DIR . '/brag-book-gallery/';
		
		return implode( "\n", $info );
	}

	/**
	 * Get WordPress information
	 *
	 * @since 3.0.0
	 * @return string
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
	 * Get server information
	 *
	 * @since 3.0.0
	 * @return string
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
		
		return implode( "\n", $info );
	}

	/**
	 * Get PHP information
	 *
	 * @since 3.0.0
	 * @return string
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
	 * Get database information
	 *
	 * @since 3.0.0
	 * @return string
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
	 * Get theme information
	 *
	 * @since 3.0.0
	 * @return string
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
	 * Get plugins information
	 *
	 * @since 3.0.0
	 * @return string
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
	 * Get gallery configuration
	 *
	 * @since 3.0.0
	 * @return string
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
	 * Get browser information
	 *
	 * @since 3.0.0
	 * @return string
	 */
	private function get_browser_info(): string {
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
		
		$info = [];
		$info[] = 'User Agent: ' . $user_agent;
		
		// Parse user agent for browser details
		$browser = 'Unknown';
		$version = '';
		
		if ( preg_match( '/MSIE ([0-9]+)/', $user_agent, $matches ) ) {
			$browser = 'Internet Explorer';
			$version = $matches[1];
		} elseif ( preg_match( '/Trident\/[0-9]+/', $user_agent ) ) {
			$browser = 'Internet Explorer';
			$version = '11+';
		} elseif ( preg_match( '/Edge\/([0-9]+)/', $user_agent, $matches ) ) {
			$browser = 'Microsoft Edge';
			$version = $matches[1];
		} elseif ( preg_match( '/Edg\/([0-9]+)/', $user_agent, $matches ) ) {
			$browser = 'Microsoft Edge (Chromium)';
			$version = $matches[1];
		} elseif ( preg_match( '/Firefox\/([0-9]+)/', $user_agent, $matches ) ) {
			$browser = 'Firefox';
			$version = $matches[1];
		} elseif ( preg_match( '/Chrome\/([0-9]+)/', $user_agent, $matches ) ) {
			$browser = 'Chrome';
			$version = $matches[1];
		} elseif ( preg_match( '/Safari\/([0-9]+)/', $user_agent, $matches ) ) {
			$browser = 'Safari';
			if ( preg_match( '/Version\/([0-9]+)/', $user_agent, $v_matches ) ) {
				$version = $v_matches[1];
			}
		}
		
		$info[] = 'Browser: ' . $browser . ( $version ? ' v' . $version : '' );
		
		// Operating System
		$os = 'Unknown';
		if ( preg_match( '/Windows NT ([0-9.]+)/', $user_agent, $matches ) ) {
			$os = 'Windows ' . $matches[1];
		} elseif ( preg_match( '/Mac OS X ([0-9._]+)/', $user_agent, $matches ) ) {
			$os = 'macOS ' . str_replace( '_', '.', $matches[1] );
		} elseif ( preg_match( '/Linux/', $user_agent ) ) {
			$os = 'Linux';
		} elseif ( preg_match( '/iPhone|iPad|iPod/', $user_agent ) ) {
			$os = 'iOS';
		} elseif ( preg_match( '/Android ([0-9.]+)/', $user_agent, $matches ) ) {
			$os = 'Android ' . $matches[1];
		}
		
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
	 * Get WordPress settings
	 *
	 * @since 3.0.0
	 * @return string
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
}