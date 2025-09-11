<?php
/**
 * Updater class for BRAGBookGallery.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Core;
use BRAGBookGallery\Includes\Extend\Cache_Manager;
use WP_Error;

final class Updater {
	private const API_BASE_URL = 'https://api.github.com/repos/%s/%s/releases/latest';
	private const CACHE_KEY = 'brag_book_gallery_github_release';
	private const CACHE_EXPIRATION = 3600; // 1 hour

	private readonly string $file;
	private readonly string $basename;
	private ?array $plugin = null;
	private bool $active = false;
	private ?string $username = null;
	private ?string $repository = null;
	private ?string $authorize_token = null;
	private ?array $github_response = null;
	private ?string $github_version = null;

	/**
	 * Initialize the updater
	 */
	public function __construct(
		string $file,
		?string $username = null,
		?string $repository = null,
		?string $token = null
	) {
		$this->file = $file;
		$this->basename = plugin_basename($file);
		$this->username = $username;
		$this->repository = $repository;
		$this->authorize_token = $token;

		// Defer plugin properties loading to avoid early translation loading
		add_action('init', [$this, 'set_plugin_properties']);
		$this->register_hooks();
	}

	/**
	 * Set plugin properties from file data
	 */
	public function set_plugin_properties(): void {
		// Only load once
		if ($this->plugin !== null) {
			return;
		}
		
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Load plugin data without translations to avoid early loading
		$this->plugin = get_plugin_data($this->file, false, false);
		$this->active = is_plugin_active($this->basename);
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks(): void {
		add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
		add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
		add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
		add_action('delete_site_transient_update_plugins', [$this, 'clear_cache']);
		add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
	}

	/**
	 * Set GitHub username
	 */
	public function set_username(string $username): self {
		$this->username = $username;
		return $this;
	}

	/**
	 * Set GitHub repository
	 */
	public function set_repository(string $repository): self {
		$this->repository = $repository;
		return $this;
	}

	/**
	 * Set authorization token
	 */
	public function authorize(string $token): self {
		$this->authorize_token = $token;
		return $this;
	}

	/**
	 * Get repository information from GitHub API with caching
	 */
	private function get_repository_info(): void {
		if ($this->github_response !== null) {
			return;
		}

		if (!$this->username || !$this->repository) {
			return;
		}

		// Check cache first (VIP compatible)
		$cache_key = $this->get_cache_key();
		$cached_response = Cache_Manager::get($cache_key);

		if ($cached_response !== false && is_array($cached_response)) {
			$this->github_response = $cached_response;
			$this->github_version = $this->extract_version($cached_response);
			return;
		}

		// Fetch from API
		$response = $this->fetch_from_github();

		if ($response === null) {
			return;
		}

		$this->github_response = $response;
		$this->github_version = $this->extract_version($response);

		// Cache the response
		Cache_Manager::set($cache_key, $response, self::CACHE_EXPIRATION);
	}

	/**
	 * Generate cache key for transient
	 */
	private function get_cache_key(): string {
		return self::CACHE_KEY . '_' . md5($this->username . '_' . $this->repository);
	}

	/**
	 * Clear cached GitHub data
	 */
	public function clear_cache(): void {
		if ($this->username && $this->repository) {
			Cache_Manager::delete($this->get_cache_key());
		}
		$this->github_response = null;
		$this->github_version = null;
	}

	/**
	 * Fetch release data from GitHub API
	 */
	private function fetch_from_github(): ?array {
		$request_uri = sprintf(
			self::API_BASE_URL,
			sanitize_text_field($this->username),
			sanitize_text_field($this->repository)
		);

		$args = [
			'timeout' => 15,
			'sslverify' => true,
			'headers' => array(
				'User-Agent' => 'WordPress-Plugin-Updater/3.0',
				'Accept' => 'application/vnd.github.v3+json',
			),
		];

		if ($this->authorize_token) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->authorize_token;
		}

		// Use vip_safe_wp_remote_get if available (VIP environment)
		$response = function_exists('vip_safe_wp_remote_get')
			? vip_safe_wp_remote_get($request_uri, '', 3, 1, 20, $args)
			: wp_remote_get($request_uri, $args);

		if (is_wp_error($response)) {
			$this->log_error('GitHub API request failed', $response);
			return null;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			$this->log_error('GitHub API returned non-200 status', new WP_Error('api_error', "Status code: {$response_code}"));
			return null;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

		if (!$this->validate_github_response($data)) {
			return null;
		}

		return $data;
	}

	/**
	 * Validate GitHub API response structure
	 */
	private function validate_github_response(mixed $data): bool {
		if (!is_array($data)) {
			return false;
		}

		$required_fields = ['tag_name', 'published_at', 'body'];
		foreach ($required_fields as $field) {
			if (!isset($data[$field])) {
				$this->log_error("Missing required field in GitHub response: {$field}");
				return false;
			}
		}

		// Check for release assets
		if (empty($data['assets']) || !is_array($data['assets'])) {
			$this->log_error('No release assets found');
			return false;
		}

		return true;
	}

	/**
	 * Extract version from GitHub response
	 */
	private function extract_version(array $response): string {
		return ltrim($response['tag_name'] ?? '', 'v');
	}

	/**
	 * Log errors for debugging
	 */
	private function log_error(string $message, ?WP_Error $error = null): void {
		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			return;
		}

		$log_message = sprintf('[BRAG book Updater] %s', $message);

		if ($error instanceof WP_Error) {
			$log_message .= sprintf(' - %s', $error->get_error_message());
		}

		error_log($log_message);
	}

	/**
	 * Modify the update transient to include our plugin
	 */
	public function modify_transient(mixed $transient): mixed {
		if (!is_object($transient) || !property_exists($transient, 'checked')) {
			return $transient;
		}

		if (empty($transient->checked) || !is_array($transient->checked)) {
			return $transient;
		}

		if (!isset($transient->checked[$this->basename])) {
			return $transient;
		}

		// Ensure plugin data is loaded
		$this->set_plugin_properties();
		
		$this->get_repository_info();

		if (!$this->github_version || !$this->github_response) {
			return $transient;
		}

		$current_version = $transient->checked[$this->basename];
		$is_out_of_date = version_compare($this->github_version, $current_version, '>');

		if (!$is_out_of_date) {
			return $transient;
		}

		$slug = dirname($this->basename);
		$update_data = $this->prepare_update_data($slug);

		$transient->response[$this->basename] = (object) $update_data;

		return $transient;
	}

	/**
	 * Get download URL from release assets
	 */
	private function get_download_url(): string {
		if (!isset($this->github_response['assets']) || !is_array($this->github_response['assets'])) {
			return '';
		}

		// Look for brag-book-gallery.zip in assets
		foreach ($this->github_response['assets'] as $asset) {
			if (isset($asset['name']) && $asset['name'] === 'brag-book-gallery.zip') {
				return $asset['browser_download_url'] ?? '';
			}
		}

		// Fallback to zipball_url if no asset found
		return $this->github_response['zipball_url'] ?? '';
	}

	/**
	 * Prepare update data for WordPress
	 */
	private function prepare_update_data(string $slug): array {
		return [
			'id' => $this->basename,
			'slug' => $slug,
			'plugin' => $this->basename,
			'new_version' => $this->github_version,
			'url' => $this->plugin['PluginURI'] ?? '',
			'package' => $this->get_download_url(),
			'icons' => $this->get_plugin_icons(),
			'banners' => $this->get_plugin_banners(),
			'banners_rtl' => [],
			'tested' => $this->plugin['TestedUpTo'] ?? '',
			'requires_php' => $this->plugin['RequiresPHP'] ?? '8.2',
			'compatibility' => new \stdClass(),
		];
	}

	/**
	 * Get plugin icons
	 */
	private function get_plugin_icons(): array {
		$default_icon = plugins_url('assets/images/brag-book-emblem.svg', $this->file);

		return [
			'1x' => $default_icon,
			'2x' => $default_icon,
			'svg' => $default_icon,
		];
	}

	/**
	 * Get plugin banners
	 */
	private function get_plugin_banners(): array {
		return [
			'low' => '',
			'high' => '',
		];
	}

	/**
	 * Display plugin information popup
	 */
	public function plugin_popup(mixed $result, string $action, object $args): mixed {
		if ($action !== 'plugin_information') {
			return $result;
		}

		if (empty($args->slug)) {
			return $result;
		}

		$our_slug = dirname($this->basename);
		if ($args->slug !== $our_slug) {
			return $result;
		}

		// Ensure plugin data is loaded
		$this->set_plugin_properties();
		
		$this->get_repository_info();

		if (!$this->github_response || !$this->github_version) {
			return $result;
		}

		return $this->prepare_plugin_info();
	}

	/**
	 * Prepare plugin information for popup
	 */
	private function prepare_plugin_info(): \stdClass {
		$info = new \stdClass();

		$info->name = $this->plugin['Name'] ?? 'BRAG book Gallery';
		$info->slug = dirname($this->basename);
		$info->version = $this->github_version;
		$info->author = sprintf(
			'<a href="%s">%s</a>',
			esc_url($this->plugin['AuthorURI'] ?? ''),
			esc_html($this->plugin['AuthorName'] ?? '')
		);
		$info->homepage = $this->plugin['PluginURI'] ?? '';
		$info->short_description = $this->plugin['Description'] ?? '';
		$info->sections = [
			'description' => $this->plugin['Description'] ?? '',
			'changelog' => $this->format_changelog($this->github_response['body'] ?? ''),
		];

		$info->download_link = $this->get_download_url();
		$info->trunk = $info->download_link;
		$info->last_updated = $this->github_response['published_at'] ?? '';
		$info->added = $this->github_response['created_at'] ?? $info->last_updated;
		$info->tags = $this->extract_tags();

		$info->donate_link = '';
		$info->tested = $this->plugin['TestedUpTo'] ?? '';
		$info->requires = $this->plugin['RequiresWP'] ?? '';
		$info->requires_php = $this->plugin['RequiresPHP'] ?? '8.2';

		$info->icons = $this->get_plugin_icons();
		$info->banners = $this->get_plugin_banners();
		$info->banners_rtl = [];

		return $info;
	}

	/**
	 * Format changelog for display
	 */
	private function format_changelog(string $body): string {
		// Convert markdown to HTML-ish format
		$changelog = wp_kses_post($body);
		$changelog = str_replace(['##', '**'], ['<h4>', '</h4>'], $changelog);
		$changelog = nl2br($changelog);

		return $changelog;
	}

	/**
	 * Extract tags from plugin data
	 */
	private function extract_tags(): array {
		$tags = [];

		if (!empty($this->plugin['Tags'])) {
			$tags = array_map('trim', explode(',', $this->plugin['Tags']));
		}

		return array_combine($tags, $tags);
	}

	/**
	 * Handle post-install actions
	 */
	public function after_install(mixed $response, array $hook_extra, array $result): mixed {
		global $wp_filesystem;

		if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
			return $response;
		}

		// SAFETY CHECK: Skip processing in development environments
		// If we're in a git repository, don't move/delete anything
		if ($this->is_development_environment()) {
			$this->log_error('Skipping after_install in development environment');
			
			// Clear cache after update
			$this->clear_cache();
			
			// Reactivate if it was active
			if ($this->active) {
				activate_plugin($this->basename);
			}
			
			return $response;
		}

		if (!$wp_filesystem) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$plugin_slug = dirname($this->basename);
		$plugins_dir = WP_PLUGIN_DIR;
		$destination = trailingslashit($plugins_dir) . $plugin_slug;

		// SAFETY CHECK: Don't process if destination already exists and contains our plugin
		if ($wp_filesystem->exists($destination . '/brag-book-gallery.php')) {
			// Plugin files already exist in the correct location
			$this->log_error('Plugin files already exist in destination, skipping move');
			
			// Clear cache after update
			$this->clear_cache();
			
			// Reactivate if it was active
			if ($this->active) {
				activate_plugin($this->basename);
			}
			
			return $result;
		}

		// Find the extracted directory (GitHub adds a prefix)
		$extracted_dir = $this->find_extracted_directory($result['destination']);

		if (!$extracted_dir) {
			$this->log_error('Could not find extracted directory');
			return $response;
		}

		// SAFETY CHECK: Verify the extracted directory is not the same as destination
		if (realpath($extracted_dir) === realpath($destination)) {
			$this->log_error('Extracted directory is the same as destination, skipping move');
			
			// Clear cache after update
			$this->clear_cache();
			
			// Reactivate if it was active
			if ($this->active) {
				activate_plugin($this->basename);
			}
			
			return $result;
		}

		// Ensure we have proper permissions
		if (!$wp_filesystem->is_writable($plugins_dir)) {
			return new WP_Error('filesystem_error', 'Plugin directory is not writable');
		}

		// SAFETY: Only proceed if extracted_dir is in a temp location
		$temp_dirs = [
			sys_get_temp_dir(),
			WP_CONTENT_DIR . '/upgrade',
			WP_CONTENT_DIR . '/upgrade-temp-backup',
		];
		
		$is_temp_location = false;
		foreach ($temp_dirs as $temp_dir) {
			if (strpos($extracted_dir, $temp_dir) === 0) {
				$is_temp_location = true;
				break;
			}
		}
		
		if (!$is_temp_location) {
			$this->log_error('Extracted directory is not in a temporary location, skipping move for safety');
			return $response;
		}

		// Move to correct location
		if ($extracted_dir !== $destination) {
			// Remove old directory if it exists (only if it's actually old)
			if ($wp_filesystem->exists($destination) && !$wp_filesystem->exists($destination . '/brag-book-gallery.php')) {
				$wp_filesystem->delete($destination, true);
			}

			// Move the extracted files
			if ($wp_filesystem->exists($extracted_dir)) {
				$wp_filesystem->move($extracted_dir, $destination);
				$result['destination'] = $destination;
			}
		}

		// Reactivate if it was active
		if ($this->active) {
			activate_plugin($this->basename);
		}

		// Clear cache after update
		$this->clear_cache();

		return $result;
	}

	/**
	 * Check if we're in a development environment
	 */
	private function is_development_environment(): bool {
		$plugin_dir = dirname($this->file);
		
		// Check for .git directory
		if (file_exists($plugin_dir . '/.git')) {
			return true;
		}
		
		// Check for common development files
		$dev_files = [
			'/.gitignore',
			'/composer.json',
			'/package.json',
			'/webpack.config.js',
			'/CLAUDE.md',
		];
		
		foreach ($dev_files as $dev_file) {
			if (file_exists($plugin_dir . $dev_file)) {
				return true;
			}
		}
		
		// Check if WP_DEBUG is enabled
		if (defined('WP_DEBUG') && WP_DEBUG) {
			return true;
		}
		
		// Check for Local by Flywheel environment
		if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
			return true;
		}
		
		// Check if running on localhost
		$is_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
		if ($is_localhost) {
			return true;
		}
		
		return false;
	}

	/**
	 * Find the actual extracted directory from GitHub
	 */
	private function find_extracted_directory(string $base_dir): ?string {
		global $wp_filesystem;

		if (!$wp_filesystem->is_dir($base_dir)) {
			return null;
		}

		// GitHub extracts as username-repo-hash
		$pattern = sprintf('%s-%s-*', $this->username, $this->repository);
		$dirs = glob(trailingslashit($base_dir) . $pattern, GLOB_ONLYDIR);

		if (!empty($dirs)) {
			return reset($dirs);
		}

		// Fallback to the provided directory
		return $base_dir;
	}
}

