<?php
/**
 * Trait Tools
 *
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @link       https://www.bragbookgallery.com/
 * @package    BRAGBookGallery
 * @since      3.0.0
 */

namespace BRAGBookGallery\Includes\Traits;

if ( ! defined( constant_name: 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Trait Tools for Core classes.
 *
 * This trait provides comprehensive plugin configuration management including
 * paths, URLs, versions, and asset handling. It serves as the central repository
 * for all plugin-related properties and utilities.
 *
 * @since 3.0.0
 */
trait Trait_Tools {

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
	protected array $accepted_values = array(
		'Name',
		'PluginURI',
		'Description',
		'Version',
		'Author',
		'AuthorURI',
		'TextDomain',
		'DomainPath',
		'Network',
	);

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
	 * Retrieve plugin basename.
	 *
	 * @return  string plugin basename.
	 * @since   3.0.0
	 *
	 * @access  public
	 */
	public function get_plugin_basename(): string {
		return plugin_basename( file: $this->get_plugin_path() );
	}

	/**
	 * Retrieve path for subdirectory.
	 *
	 * @access public
	 *
	 * @param string $dir_name Subdirectory name.
	 *
	 * @return string
	 * @since 3.0.0
	 */
	public function get_sub_dir_path( string $dir_name ): string {
		return $this->get_plugin_path() . $dir_name;
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
		return dirname( path: __DIR__, levels: 2 ) . '/';
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
	 * @param string $asset_path Relative path to asset from plugin root (optional)
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
	 * @param string $asset_path Relative path to asset from plugin root (optional)
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
	 * Get plugin information from main file.
	 *
	 * @param string $value Value to get from plugin file data.
	 *
	 * @return string       Return plugin information
	 * @since 3.0.0
	 *
	 * @access public
	 */
	public function get_plugin_info( string $value ): string {

		$data = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Description' => 'Description',
			'Version'     => 'Version',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
		);

		$plugin_info = get_file_data(
			file: trailingslashit( $this->get_plugin_path() ) . 'brag-book-gallery.php',
			default_headers: $data,
			context: 'plugin'
		);

		return $plugin_info[ $value ];
	}

	/**
	 * Returns the version number of the plugin.
	 *
	 * @param string $request Plugin information value request.
	 *
	 * @return string          Return plugin version number.
	 * @since .0.0
	 *
	 * @access public
	 */
	public function get_plugin_info_value( string $request ): string {
		if ( in_array( needle: $request, haystack: $this->accepted_values, strict: true ) ) {
			return $this->get_plugin_info( value: $request );
		}

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
	 * Setup plugin constants.
	 *
	 * @return void
	 * @since 3.0.0
	 *
	 * @access private
	 */
	private function set_constants(): void {

		$name = strtolower( $this->get_plugin_info_value( request: 'Name' ) );

		$this->constants = array(
			'name'        => str_replace( search: ' ', replace: '-', subject: $name ),
			'version'     => $this->get_plugin_info_value( request: 'Version' ),
			'basename'    => $this->get_plugin_basename(),
			'db-version'  => $this->get_plugin_db_version(),
			'dir'         => $this->get_plugin_path(),
			'url'         => $this->get_plugin_url(),
			'uri'         => $this->get_plugin_info_value( request: 'PluginURI' ),
			'author'      => $this->get_plugin_info_value( request: 'Author' ),
			'lang'        => $this->get_sub_dir_path( $this->languages ),
			'vendors'     => $this->get_sub_dir_path( $this->vendors ),
			'text_domain' => $this->get_plugin_info_value( 'TextDomain' ),
			'assets'      => trailingslashit( value: $this->get_plugin_url() . '/assets' ),
			'media'       => trailingslashit( value: $this->get_plugin_url() . '/media' ),
			'timeout'     => 60,
		);

		// Apply filter after init to avoid early translation loading.
		if ( did_action( 'init' ) ) {
			$this->constants['timeout'] = apply_filters( hook_name: $name . '_timeout', value: 60 );
		} else {
			add_action( 'init', function () use ( $name ) {
				$this->constants['timeout'] = apply_filters( hook_name: $name . '_timeout', value: 60 );
			}, 1 );
		}
	}

	/**
	 * Retrieve requested path.
	 *
	 * @param string $request Type of item.
	 * @param bool $screen If the request is public or admin.
	 * @param string[] $path Array of values.
	 *
	 * @return string       Related path.
	 * @since  1.0.0
	 *
	 * @access public
	 */
	public function get_path( string $request, bool $screen = false, array $path = array() ): string {
		$path['url']     = trailingslashit( $this->get_plugin_url() );
		$path['dir']     = true === $screen ? trailingslashit( 'admin' ) : false;
		$path['request'] = trailingslashit( $request );

		return esc_url_raw( url: implode( separator: '', array: $path ) );
	}
}
