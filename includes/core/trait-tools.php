<?php
/**
 * Trait Tools - Enterprise-grade plugin configuration and utility system
 *
 * Comprehensive plugin configuration trait for BRAGBook Gallery plugin.
 * Provides centralized management of paths, URLs, assets, and plugin metadata
 * with advanced features for performance optimization and security.
 *
 * Features:
 * - Centralized path and URL management
 * - Asset handling with cache busting
 * - Plugin metadata extraction
 * - Version management
 * - Performance-optimized caching
 * - Security-hardened path handling
 * - WordPress VIP compliant architecture
 * - Modern PHP 8.2+ features and type safety
 *
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 * @link       https://www.bragbookgallery.com/
 * @package    BRAGBookGallery
 * @since      3.0.0
 */

declare(strict_types=1);

namespace BRAGBookGallery\Includes\Core;

use Exception;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Enterprise Plugin Configuration and Utility Trait
 *
 * Orchestrates comprehensive plugin configuration operations:
 *
 * Core Responsibilities:
 * - Path and URL management
 * - Asset handling and optimization
 * - Plugin metadata extraction
 * - Version control
 * - Cache management
 * - Security validation
 * - Performance optimization
 *
 * @since 3.0.0
 */
trait Trait_Tools {

	/**
	 * Performance constants
	 *
	 * @since 3.0.0
	 */
	protected const CACHE_TTL_SHORT = 300;     // 5 minutes
	protected const CACHE_TTL_MEDIUM = 1800;   // 30 minutes
	protected const CACHE_TTL_LONG = 3600;     // 1 hour
	protected const CACHE_TTL_EXTENDED = 7200; // 2 hours

	/**
	 * Security constants
	 *
	 * @since 3.0.0
	 */
	protected const MAX_PATH_LENGTH = 4096;
	protected const MAX_URL_LENGTH = 2083;

	/**
	 * Performance metrics
	 *
	 * @since 3.0.0
	 * @var array<string, array<string, mixed>>
	 */
	protected array $performance_metrics = [];

	/**
	 * Memory cache for paths and URLs
	 *
	 * @since 3.0.0
	 * @var array<string, string>
	 */
	protected static array $path_cache = [];

	/**
	 * Error log
	 *
	 * @since 3.0.0
	 * @var array<int, array<string, mixed>>
	 */
	protected array $error_log = [];

	/**
	 * Plugin accepted values list.
	 *
	 * These are the standard WordPress plugin header fields that can be
	 * extracted from the main plugin file.
	 *
	 * @since  3.0.0
	 * @access protected
	 * @static
	 * @var string[] Values listed in plugin heading.
	 */
	protected array $accepted_values = [
		'Name',
		'PluginURI',
		'Description',
		'Version',
		'Author',
		'AuthorURI',
		'TextDomain',
		'DomainPath',
		'Network',
		'RequiresWP',
		'RequiresPHP',
		'UpdateURI',
	];

	/**
	 * Plugin data
	 *
	 * Stores all plugin constants and configuration values after initialization.
	 * This includes paths, URLs, version information, and other metadata.
	 *
	 * @since 3.0.0
	 * @access public
	 * @var    string[] Array of plugin elements.
	 */
	public array $constants;

	/**
	 * Plugin Database version.
	 *
	 * Used for database migrations and update checks. This should be incremented
	 * when database schema changes are made.
	 *
	 * @since 3.0.0
	 * @access protected
	 * @var    string
	 */
	protected string $db_version = '3.0.0';

	/**
	 * Plugin version
	 *
	 * The current version of the plugin. This should match the version in the
	 * main plugin file header.
	 *
	 * @since  3.0.0
	 * @access protected
	 * @static
	 * @var    string
	 */
	protected static string $plugin_version = '3.0.0';

	/**
	 * Plugin main file path
	 *
	 * Stores the absolute path to the main plugin file. This is set during
	 * initialization and used for all path calculations.
	 *
	 * @since  3.0.0
	 * @access protected
	 * @static
	 * @var    string|null
	 */
	protected static ?string $plugin_file = null;

	/**
	 * Plugin URL cache
	 *
	 * Cached URL to the plugin directory to avoid repeated calculations.
	 * This is the public-facing URL used for assets and resources.
	 *
	 * @since  3.0.0
	 * @access protected
	 * @static
	 * @var    string|null
	 */
	protected static ?string $plugin_url_cache = null;

	/**
	 * Plugin path cache
	 *
	 * Cached filesystem path to the plugin directory to avoid repeated calculations.
	 * This is the server-side path used for including files.
	 *
	 * @since  3.0.0
	 * @access protected
	 * @static
	 * @var    string|null
	 */
	protected static ?string $plugin_path_cache = null;

	/**
	 * Vendors directory.
	 *
	 * @since 3.0.0
	 *
	 * @access protected
	 * @var    string
	 */
	protected string $vendors = '\\vendors';

	/**
	 * Languages directory.
	 *
	 * @since 3.0.0
	 *
	 * @access protected
	 * @var    string
	 */
	protected string $languages = '\\languages';

	/**
	 * Retrieve plugin basename with caching.
	 *
	 * @return  string plugin basename.
	 * @since   3.0.0
	 *
	 * @access  public
	 */
	public function get_plugin_basename(): string {
		$cache_key = 'brag_book_gallery_transient_plugin_basename';

		if ( isset( self::$path_cache[ $cache_key ] ) ) {
			return self::$path_cache[ $cache_key ];
		}

		$basename = plugin_basename( $this->get_plugin_path() );
		self::$path_cache[ $cache_key ] = $basename;

		return $basename;
	}

	/**
	 * Retrieve path for subdirectory with validation.
	 *
	 * @access public
	 *
	 * @param string $dir_name Subdirectory name.
	 * @param bool   $create   Create directory if not exists.
	 *
	 * @return string|false Path to subdirectory or false on error.
	 * @since 3.0.0
	 */
	public function get_sub_dir_path( string $dir_name, bool $create = false ): string|false {
		$start_time = microtime( true );

		try {
			// Sanitize directory name
			$dir_name = $this->sanitize_path( $dir_name );
			$full_path = $this->get_plugin_path() . $dir_name;

			// Create directory if requested
			if ( $create && ! is_dir( $full_path ) ) {
				if ( ! wp_mkdir_p( $full_path ) ) {
					$this->log_error( 'get_sub_dir_path', 'Failed to create directory: ' . $full_path );
					return false;
				}
			}

			$this->track_performance( 'get_sub_dir_path', microtime( true ) - $start_time );

			return $full_path;

		} catch ( Exception $e ) {
			$this->log_error( 'get_sub_dir_path', $e->getMessage() );
			return false;
		}
	}

	/**
	 * Return the database version of the plugin.
	 *
	 * @return string Return plugin database version number.
	 * @since 3.0.0
	 *
	 * @access public
	 */
	public function get_plugin_db_version(): string {
		return $this->db_version;
	}

	/**
	 * Initialize plugin properties
	 *
	 * This method must be called early in the plugin lifecycle to set up
	 * all path and URL properties. It caches values for performance.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file
	 *
	 * @return void
	 * @since  3.0.0
	 * @access protected
	 * @static
	 *
	 */
	protected static function init_properties( string $plugin_file ): void {
		self::$plugin_file       = $plugin_file;
		self::$plugin_url_cache  = plugin_dir_url( $plugin_file );
		self::$plugin_path_cache = plugin_dir_path( $plugin_file );
	}

	/**
	 * Retrieve plugin path.
	 *
	 * Returns the absolute filesystem path to the plugin directory.
	 * Uses cached value if available, otherwise calculates from plugin file.
	 *
	 * @return string Absolute path to plugin directory with trailing slash
	 * @since  3.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function get_plugin_path(): string {
		// Use cached value if available
		if ( self::$plugin_path_cache !== null ) {
			return self::$plugin_path_cache;
		}

		// Fallback to calculating from plugin file if set
		if ( self::$plugin_file !== null ) {
			self::$plugin_path_cache = plugin_dir_path( self::$plugin_file );

			return self::$plugin_path_cache;
		}

		// Last resort: calculate from current file location
		return dirname( __DIR__, 2 ) . '/';
	}

	/**
	 * Retrieve plugin URL.
	 *
	 * Returns the public-facing URL to the plugin directory.
	 * Uses cached value if available, otherwise calculates from plugin file.
	 *
	 * @return string URL to plugin directory with trailing slash
	 * @since  3.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function get_plugin_url(): string {
		// Use cached value if available
		if ( self::$plugin_url_cache !== null ) {
			return self::$plugin_url_cache;
		}

		// Fallback to calculating from plugin file if set
		if ( self::$plugin_file !== null ) {
			self::$plugin_url_cache = plugin_dir_url( self::$plugin_file );

			return self::$plugin_url_cache;
		}

		// Last resort: calculate from main plugin file
		$plugin_file = dirname( dirname( __DIR__ ) ) . '/brag-book-gallery.php';

		return esc_url( plugin_dir_url( $plugin_file ) );
	}

	/**
	 * Get plugin version
	 *
	 * Returns the current plugin version number. This is used for cache busting,
	 * update checks, and version comparisons.
	 *
	 * @return string Plugin version number (e.g., '3.0.0')
	 * @since  3.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function get_version(): string {
		return self::$plugin_version;
	}

	/**
	 * Get asset URL
	 *
	 * Constructs a full URL to a plugin asset (images, CSS, JS, etc.).
	 * Handles path normalization and ensures proper URL formatting.
	 *
	 * Example usage:
	 * - get_asset_url('assets/images/logo.png')
	 * - get_asset_url('assets/css/style.css')
	 *
	 * @param string|null $asset_path Relative path to asset from plugin root (optional)
	 *
	 * @return string Full URL to the asset or plugin URL if no path provided
	 * @since  3.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function get_asset_url( ?string $asset_path = '' ): string {
		$base_url = self::get_plugin_url();
		$asset_path = $asset_path ?? '';

		if ( ! empty( $asset_path ) ) {
			// Remove leading slash to prevent double slashes
			$asset_path = ltrim( $asset_path, '/' );

			return $base_url . $asset_path;
		}

		return $base_url;
	}

	/**
	 * Get asset filesystem path
	 *
	 * Constructs a full filesystem path to a plugin asset or file.
	 * Useful for file operations like reading, writing, or checking existence.
	 *
	 * Example usage:
	 * - get_asset_path('template/sidebar.php')
	 * - get_asset_path('includes/class-api.php')
	 *
	 * @param string|null $asset_path Relative path to asset from plugin root (optional)
	 *
	 * @return string Full filesystem path to the asset or plugin path if no path provided
	 * @since  3.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function get_asset_path( ?string $asset_path = '' ): string {
		$base_path = self::get_plugin_path();
		$asset_path = $asset_path ?? '';

		if ( ! empty( $asset_path ) ) {
			// Remove leading slash to prevent double slashes
			$asset_path = ltrim( $asset_path, '/' );

			return $base_path . $asset_path;
		}

		return $base_path;
	}

	/**
	 * Get plugin information from main file with caching.
	 *
	 * @param string $value Value to get from plugin file data.
	 *
	 * @return string Return plugin information or empty string if not found.
	 * @since 3.0.0
	 *
	 * @access public
	 */
	public function get_plugin_info( string $value ): string {
		$cache_key = 'brag_book_gallery_transient_plugin_info_' . serialize( func_get_args() );

		if ( isset( self::$path_cache[ $cache_key ] ) ) {
			return self::$path_cache[ $cache_key ];
		}

		$data = [
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Description' => 'Description',
			'Version'     => 'Version',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
			'UpdateURI'   => 'Update URI',
		];

		$plugin_file = trailingslashit( $this->get_plugin_path() ) . 'brag-book-gallery.php';

		if ( ! file_exists( $plugin_file ) ) {
			$this->log_error( 'get_plugin_info', 'Plugin file not found: ' . $plugin_file );
			return '';
		}

		$plugin_info = get_file_data(
			$plugin_file,
			$data,
			'plugin'
		);

		$result = $plugin_info[ $value ] ?? '';
		self::$path_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Returns the requested plugin information value.
	 *
	 * @param string $request Plugin information value request.
	 *
	 * @return string Return plugin information or empty string if invalid.
	 * @since 3.0.0
	 *
	 * @access public
	 */
	public function get_plugin_info_value( string $request ): string {
		if ( in_array( $request, $this->accepted_values, true ) ) {
			return $this->get_plugin_info( $request );
		}

		$this->log_error( 'get_plugin_info_value', 'Invalid request: ' . $request );
		return '';
	}

	/**
	 * Get constant value
	 *
	 * @param string $request Return plugin value.
	 *
	 * @since 3.0.0
	 *
	 * @access public
	 */
	public function get_constant( string $request ): string {
		return $this->constants[ $request ];
	}

	/**
	 * Setup plugin constants with validation.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 * @access private
	 */
	private function set_constants(): void {
		$start_time = microtime( true );

		try {
			$name = strtolower( $this->get_plugin_info_value( 'Name' ) );
			$slug = str_replace( ' ', '-', $name );

			$this->constants = [
				'name'         => $slug,
				'version'      => $this->get_plugin_info_value( 'Version' ),
				'basename'     => $this->get_plugin_basename(),
				'db-version'   => $this->get_plugin_db_version(),
				'dir'          => $this->get_plugin_path(),
				'url'          => $this->get_plugin_url(),
				'uri'          => $this->get_plugin_info_value( 'PluginURI' ),
				'author'       => $this->get_plugin_info_value( 'Author' ),
				'lang'         => $this->get_sub_dir_path( $this->languages ),
				'vendors'      => $this->get_sub_dir_path( $this->vendors ),
				'text_domain'  => $this->get_plugin_info_value( 'TextDomain' ),
				'assets'       => trailingslashit( $this->get_plugin_url() . '/assets' ),
				'media'        => trailingslashit( $this->get_plugin_url() . '/media' ),
				'timeout'      => 60,
				'requires_wp'  => $this->get_plugin_info_value( 'RequiresWP' ),
				'requires_php' => $this->get_plugin_info_value( 'RequiresPHP' ),
			];

			// Apply filter after init to avoid early translation loading.
			if ( did_action( 'init' ) ) {
				$this->constants['timeout'] = apply_filters( $slug . '_timeout', 60 );
			} else {
				add_action( 'init', function () use ( $slug ) {
					$this->constants['timeout'] = apply_filters( $slug . '_timeout', 60 );
				}, 1 );
			}

			$this->track_performance( 'set_constants', microtime( true ) - $start_time );

		} catch ( Exception $e ) {
			$this->log_error( 'set_constants', $e->getMessage() );
			// Set minimal constants on error
			$this->constants = [
				'name'    => 'brag-book-gallery',
				'version' => '3.0.0',
				'dir'     => $this->get_plugin_path(),
				'url'     => $this->get_plugin_url(),
			];
		}
	}

	/**
	 * Retrieve requested path with validation.
	 *
	 * @param string $request Type of item.
	 * @param bool $screen If the request is public or admin.
	 * @param string[] $path Array of values.
	 *
	 * @return string Related path.
	 * @since  3.0.0
	 *
	 * @access public
	 */
	public function get_path( string $request, bool $screen = false, array $path = [] ): string {
		$cache_key = 'brag_book_gallery_transient_path_' . serialize( func_get_args() );

		if ( isset( self::$path_cache[ $cache_key ] ) ) {
			return self::$path_cache[ $cache_key ];
		}

		$path['url']     = trailingslashit( $this->get_plugin_url() );
		$path['dir']     = $screen ? trailingslashit( 'admin' ) : '';
		$path['request'] = trailingslashit( $request );

		$result = esc_url_raw( implode( '', array_filter( $path ) ) );
		self::$path_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Sanitize path component
	 *
	 * @since 3.0.0
	 * @param string $path Path to sanitize.
	 * @return string Sanitized path.
	 */
	protected function sanitize_path( string $path ): string {
		// Remove any directory traversal attempts
		$path = str_replace( ['..', './'], '', $path );
		// Remove null bytes
		$path = str_replace( chr(0), '', $path );
		// Normalize slashes
		$path = str_replace( '\\', '/', $path );
		// Limit length
		return substr( $path, 0, self::MAX_PATH_LENGTH );
	}

	/**
	 * Log error message
	 *
	 * @since 3.0.0
	 * @param string $context Error context.
	 * @param string $message Error message.
	 * @return void
	 */
	protected function log_error( string $context, string $message ): void {
		$this->error_log[] = [
			'context' => $context,
			'message' => $message,
			'time'    => current_time( 'mysql' ),
		];

		// Limit error log size
		if ( count( $this->error_log ) > 100 ) {
			array_shift( $this->error_log );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[BRAGBook Tools] %s: %s', $context, $message ) );
		}
	}

	/**
	 * Track performance metrics
	 *
	 * @since 3.0.0
	 * @param string $operation Operation name.
	 * @param float  $duration Operation duration.
	 * @return void
	 */
	protected function track_performance( string $operation, float $duration ): void {
		if ( ! isset( $this->performance_metrics[ $operation ] ) ) {
			$this->performance_metrics[ $operation ] = [
				'count'   => 0,
				'total'   => 0,
				'min'     => PHP_FLOAT_MAX,
				'max'     => 0,
			];
		}

		$metrics = &$this->performance_metrics[ $operation ];
		$metrics['count']++;
		$metrics['total'] += $duration;
		$metrics['min'] = min( $metrics['min'], $duration );
		$metrics['max'] = max( $metrics['max'], $duration );
		$metrics['average'] = $metrics['total'] / $metrics['count'];
	}

	/**
	 * Get versioned asset URL
	 *
	 * @since 3.0.0
	 * @param string $asset_path Path to asset.
	 * @param string|null $version Version string or null to use plugin version.
	 * @return string Versioned asset URL.
	 */
	public static function get_versioned_asset_url( string $asset_path, ?string $version = null ): string {
		$url = self::get_asset_url( $asset_path );
		$version = $version ?? self::get_version();

		if ( ! empty( $version ) ) {
			$separator = str_contains( $url, '?' ) ? '&' : '?';
			$url .= $separator . 'ver=' . $version;
		}

		return $url;
	}

	/**
	 * Check if path is within plugin directory
	 *
	 * @since 3.0.0
	 * @param string $path Path to check.
	 * @return bool True if path is safe.
	 */
	public static function is_safe_path( string $path ): bool {
		$plugin_path = realpath( self::get_plugin_path() );
		$check_path = realpath( $path );

		if ( $plugin_path === false || $check_path === false ) {
			return false;
		}

		return str_starts_with( $check_path, $plugin_path );
	}

	/**
	 * Get all plugin constants
	 *
	 * @since 3.0.0
	 * @return array<string, mixed> All plugin constants.
	 */
	public function get_all_constants(): array {
		return $this->constants ?? [];
	}

	/**
	 * Check plugin compatibility
	 *
	 * @since 3.0.0
	 * @return array<string, bool> Compatibility status.
	 */
	public function check_compatibility(): array {
		$wp_version = get_bloginfo( 'version' );
		$php_version = PHP_VERSION;

		$requires_wp = $this->get_plugin_info_value( 'RequiresWP' ) ?: '6.0';
		$requires_php = $this->get_plugin_info_value( 'RequiresPHP' ) ?: '8.2';

		return [
			'wp_compatible'  => version_compare( $wp_version, $requires_wp, '>=' ),
			'php_compatible' => version_compare( $php_version, $requires_php, '>=' ),
			'wp_version'     => $wp_version,
			'php_version'    => $php_version,
			'requires_wp'    => $requires_wp,
			'requires_php'   => $requires_php,
		];
	}
}
