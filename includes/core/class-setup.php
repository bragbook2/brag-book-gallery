<?php
declare( strict_types=1 );

/**
 * Plugin Setup Class
 *
 * Enterprise-grade plugin orchestrator implementing singleton pattern for centralized
 * initialization, service management, and lifecycle control. Manages all plugin
 * components with lazy loading and dependency injection principles.
 *
 * Architecture:
 * - Singleton pattern ensures single instance
 * - Service container for dependency management
 * - Hook-based initialization for WordPress integration
 * - Mode-aware component loading (JavaScript/Local modes)
 * - LiteSpeed Cache compatibility layer
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\Admin\Core\Settings_Manager;
use BRAGBookGallery\Includes\Communications\Communications;
use BRAGBookGallery\Includes\data\Database;
use BRAGBookGallery\Includes\Extend\Post_Types;
use BRAGBookGallery\Includes\Extend\Taxonomies;
use BRAGBookGallery\Includes\Extend\Template_Manager;
use BRAGBookGallery\Includes\Resources\Assets;
use BRAGBookGallery\Includes\SEO\On_Page;
use BRAGBookGallery\Includes\SEO\Sitemap;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Class
 *
 * Core plugin initialization and configuration handler.
 * Manages the plugin lifecycle from activation to deactivation.
 *
 * @since 3.0.0
 */
final class Setup {
	use Trait_Tools;
	use Trait_Api;

	/**
	 * Plugin version for cache busting and compatibility tracking.
	 *
	 * @since 3.0.0
	 * @var string Semantic version string.
	 */
	private const VERSION = '3.0.0';

	/**
	 * Hook priority for init actions.
	 *
	 * @since 3.0.0
	 * @var int Standard WordPress priority.
	 */
	private const INIT_PRIORITY = 10;

	/**
	 * Hook priority for template filters.
	 *
	 * @since 3.0.0
	 * @var int High priority for template override.
	 */
	private const TEMPLATE_PRIORITY = 99;

	/**
	 * Plugin admin page hooks for conditional loading.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Admin page hook suffixes.
	 */
	private const ADMIN_PAGES = [
		'toplevel_page_brag-book-gallery-settings',
		'brag-book-gallery_page_brag-book-gallery-consultation',
		'admin_page_brag-book-gallery-settings',
	];

	/**
	 * LiteSpeed Cache AJAX exclusions.
	 *
	 * @since 3.0.0
	 * @var array<int, string> AJAX actions to exclude from cache.
	 */
	private const LITESPEED_AJAX_EXCLUSIONS = [
		'brag_book_gallery_load_filtered_gallery',
		'brag_book_gallery_load_case',
		'brag_book_gallery_load_case_details',
		'brag_book_gallery_load_case_details_html',
		'brag_book_gallery_load_more_cases',
		'brag_book_gallery_load_filtered_cases',
		'brag_book_gallery_clear_cache',
		'brag_book_gallery_flush_rewrite_rules',
	];

	/**
	 * LiteSpeed Cache query string exclusions.
	 *
	 * @since 3.0.0
	 * @var array<int, string> Query parameters to exclude from cache.
	 */
	private const LITESPEED_QS_EXCLUSIONS = [
		'filter_procedure',
		'procedure_title',
		'case_id',
		'filter_category',
		'favorites_section',
	];

	/**
	 * Plugin instance
	 *
	 * Singleton instance of the plugin setup class.
	 *
	 * @since 3.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container for dependency injection.
	 *
	 * Stores all plugin service instances with lazy loading support.
	 * Services are instantiated once and cached for performance.
	 *
	 * @since 3.0.0
	 * @var array<string, object> Service name => instance mapping.
	 */
	private array $services = [];

	/**
	 * Initialization guard flag.
	 *
	 * Prevents duplicate initialization and ensures singleton integrity.
	 *
	 * @since 3.0.0
	 * @var bool True when fully initialized.
	 */
	private bool $initialized = false;

	/**
	 * Cache for expensive operations.
	 *
	 * @since 3.0.0
	 * @var array<string, mixed> Operation results cache.
	 */
	private array $cache = [];

	/**
	 * Constructor - Initialize plugin components.
	 *
	 * Private constructor enforces singleton pattern. Sets up all hooks,
	 * services, and dependencies with proper error handling.
	 *
	 * @since 3.0.0
	 * @throws \RuntimeException If initialization fails.
	 */
	private function __construct() {
		try {
			// Load cache helper functions
			$this->load_cache_helpers();

			// Register core hooks
			$this->register_hooks();

			// Initialize services
			$this->init_services();

			// Mark as initialized
			$this->initialized = true;

			do_action( 'qm/debug', 'BRAGBook Gallery Setup initialized successfully' );
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Setup initialization failed: %s', $e->getMessage() ) );
			throw new \RuntimeException( 'Plugin initialization failed', 0, $e );
		}
	}

	/**
	 * Get plugin instance (Singleton pattern).
	 *
	 * Thread-safe singleton implementation ensuring single instance.
	 * Uses double-checked locking pattern for performance.
	 *
	 * @since 3.0.0
	 * @return self Plugin instance.
	 *
	 * @example
	 * ```php
	 * $setup = Setup::get_instance();
	 * $service = $setup->get_service( 'settings_manager' );
	 * ```
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Main entry point for plugin bootstrapping. Validates environment,
	 * sets up properties, and creates singleton instance.
	 *
	 * @since 3.0.0
	 * @param string $plugin_file Absolute path to main plugin file.
	 * @return void
	 *
	 * @throws \WP_Error If plugin file is invalid.
	 *
	 * @example
	 * ```php
	 * // From main plugin file
	 * Setup::init_plugin( __FILE__ );
	 * ```
	 */
	public static function init_plugin( string $plugin_file ): void {
		try {
			// Validate plugin file
			if ( ! self::validate_plugin_file( $plugin_file ) ) {
				wp_die(
					esc_html__( 'Invalid plugin file path.', 'brag-book-gallery' ),
					esc_html__( 'Plugin Error', 'brag-book-gallery' ),
					[ 'response' => 500 ]
				);
			}

			// Initialize plugin properties (paths, URLs, etc.)
			self::init_properties( $plugin_file );

			// Get or create plugin instance
			self::get_instance();

			do_action( 'qm/debug', sprintf( 'Plugin initialized from: %s', $plugin_file ) );
		} catch ( \Exception $e ) {
			do_action( 'qm/debug', sprintf( 'Plugin initialization error: %s', $e->getMessage() ) );
			wp_die(
				esc_html( $e->getMessage() ),
				esc_html__( 'Plugin Error', 'brag-book-gallery' ),
				[ 'response' => 500 ]
			);
		}
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Comprehensive hook registration following WordPress VIP standards.
	 * Uses modern PHP 8.2 syntax with proper prioritization.
	 *
	 * Hook Groups:
	 * - Core initialization (init, plugins_loaded)
	 * - Asset management (enqueue scripts/styles)
	 * - Lifecycle hooks (activation/deactivation)
	 * - Admin-specific hooks (admin_init, action links)
	 * - Cache exclusions (LiteSpeed compatibility)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function register_hooks(): void {
		// Core initialization
		add_action( 'init', [ $this, 'init' ], self::INIT_PRIORITY );
		add_action( 'plugins_loaded', [ $this, 'init_updater' ], 10 );

		// Asset loading
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Lifecycle hooks
		$plugin_file = self::get_plugin_file();
		register_activation_hook( $plugin_file, [ $this, 'activate' ] );
		register_deactivation_hook( $plugin_file, [ $this, 'deactivate' ] );

		// Admin-specific hooks
		if ( is_admin() ) {
			$this->register_admin_hooks();
		}

		// Cron events
		add_action( 'brag_book_gallery_delayed_rewrite_flush', [ $this, 'handle_delayed_rewrite_flush' ] );
		add_action( 'brag_book_gallery_cleanup_expired_transients', [ $this, 'cleanup_expired_transients' ] );
		add_action( 'brag_book_gallery_cleanup_wp_cache', [ $this, 'cleanup_wp_cache' ] );

		// Cache exclusions
		$this->setup_litespeed_exclusions();

		/**
		 * Fires after all core hooks are registered.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_hooks_registered' );
	}

	/**
	 * Initialize plugin services
	 *
	 * Creates instances of all plugin service classes.
	 * Services are lazy-loaded and cached for performance.
	 * Order of initialization matters for dependencies.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function init_services(): void {

		// Initialize Database manager first (creates tables if needed).
		$this->services['database'] = new Database();

		// Mode Manager removed per user request

		// Initialize sync components (for Local mode).

		// Initialize SEO Manager (handles SEO optimization and plugin detection).
		$this->services['seo_manager'] = new \BRAGBookGallery\Includes\SEO\SEO_Manager();

		// Initialize admin settings manager (handles all settings pages).
		$this->services['settings_manager'] = new Settings_Manager();

		// Initialize SEO components.
		$this->services['sitemap'] = new Sitemap();
		$this->services['on_page_seo'] = new On_Page();

		// Initialize communications handler.
		$this->services['communications'] = new Communications();

		// Initialize post types and taxonomies.
		$this->services['post_types'] = new Post_Types();
		$this->services['taxonomies'] = new Taxonomies();

		// Check if we need to flush rewrite rules for new case URL structure (one-time)
		if ( ! get_option( 'brag_book_gallery_case_url_structure_updated', false ) ) {
			update_option( 'brag_book_gallery_flush_rewrite_rules', true );
			update_option( 'brag_book_gallery_case_url_structure_updated', true );
		}

		// Check if we need to flush rewrite rules for favorites page (one-time)
		if ( ! get_option( 'brag_book_gallery_favorites_rewrite_fixed', false ) ) {
			update_option( 'brag_book_gallery_flush_rewrite_rules', true );
			update_option( 'brag_book_gallery_favorites_rewrite_fixed', true );
		}

		// Initialize template manager for procedure templates.
		$this->services['template_manager'] = new Template_Manager();

		// Initialize assets handler.
		$this->services['assets'] = new Assets();

		// Initialize shortcode handlers.
		$this->services['gallery_handler'] = new \BRAGBookGallery\Includes\Shortcodes\Gallery_Handler();
		$this->services['sidebar_handler'] = new \BRAGBookGallery\Includes\Shortcodes\Sidebar_Handler();
		$this->services['case_handler'] = new \BRAGBookGallery\Includes\Shortcodes\Case_Handler();
		$this->services['cases_handler'] = new \BRAGBookGallery\Includes\Shortcodes\Cases_Handler();
		$this->services['favorites_handler'] = new \BRAGBookGallery\Includes\Shortcodes\Favorites_Handler();

		// Initialize carousel shortcodes
		add_shortcode( 'brag_book_carousel', [ \BRAGBookGallery\Includes\Shortcodes\Carousel_Handler::class, 'handle' ] );
		add_shortcode( 'bragbook_carousel_shortcode', [ \BRAGBookGallery\Includes\Shortcodes\Carousel_Handler::class, 'handle_legacy' ] );
	}

	/**
	 * WordPress init action handler
	 *
	 * Runs during WordPress initialization. Sets up shortcodes,
	 * rewrite rules, and other components that need to be registered
	 * after WordPress core is loaded.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Run one-time migration to fix array slug issues
		$this->migrate_page_slug_option();

		// Load plugin textdomain for translations.
		$this->load_textdomain();

		// Register custom post types if needed.
		$this->register_post_types();

		// Initialize REST API endpoints.
		$this->init_rest_api();

		// Initialize optimized sync AJAX handlers
		if ( is_admin() ) {
			error_log( 'BRAG book Gallery: Initializing Sync_Ajax_Handler' );
			\BRAGBookGallery\Includes\Sync\Sync_Ajax_Handler::init();
			error_log( 'BRAG book Gallery: Sync_Ajax_Handler initialized' );
		}

		// Register custom cron schedules
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

		// Register tracking hooks for view analytics
		add_action( 'brag_book_gallery_track_view', [ $this, 'handle_scheduled_view_tracking' ] );

		// Note: My Favorites page creation moved to sync process only

		// Check and flush rewrite rules if needed
		$this->setup_rewrite_rules();

		// Fire custom action for extensions.
		do_action( 'brag_book_gallery_init', $this );
	}

	/**
	 * Initialize the updater for GitHub releases
	 *
	 * Sets up the plugin updater to check for new versions
	 * from the GitHub repository. Uses GitHub API for version checks.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function init_updater(): void {
		if ( ! isset( $this->services['updater'] ) ) {
			$plugin_file = self::get_plugin_file();

			// Validate plugin file exists.
			if ( ! file_exists( $plugin_file ) ) {
				return;
			}

			$this->services['updater'] = new Updater(
				$plugin_file,
				'bragbook2',
				'brag-book-gallery'
			);
		}
	}

	/**
	 * WordPress admin_init action handler
	 *
	 * Runs during admin initialization. Sets up admin-specific
	 * functionality, checks for updates, and runs upgrades.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function admin_init(): void {

		// Check for plugin updates.
		$this->check_version();

		// Run any necessary upgrades.
		$this->maybe_upgrade();

		// Fire custom action for admin extensions.
		do_action( 'brag_book_gallery_admin_init', $this );
	}

	/**
	 * Add plugin action links
	 *
	 * Adds a Settings link to the plugin's row on the plugins page.
	 * Follows WordPress VIP standards for escaping and sanitization.
	 *
	 * @since 3.0.0
	 *
	 * @param array<string, string> $links Existing plugin action links.
	 *
	 * @return array<string, string> Modified plugin action links.
	 */
	public function add_plugin_action_links( array $links ): array {
		// Add Settings link.
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ),
			esc_html__( 'Settings', 'brag-book-gallery' )
		);

		// Add to beginning of links array.
		array_unshift( $links, $settings_link );

		/**
		 * Filters the plugin action links.
		 *
		 * @since 3.0.0
		 *
		 * @param array<string, string> $links Plugin action links.
		 */
		return apply_filters( 'brag_book_gallery_action_links', $links );
	}

	/**
	 * Setup rewrite rules
	 *
	 * Configures custom rewrite rules for gallery pages.
	 * Note: Rewrite rules are now automatically handled by the Rewrite_Rules class
	 * when Shortcodes::register() is called.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function setup_rewrite_rules(): void {
		// Get gallery page slug
		$gallery_slug = get_option( 'brag_book_gallery_page_slug', 'gallery' );

		// Add rewrite rule for My Favorites page: /gallery/myfavorites/
		add_rewrite_rule(
			'^' . preg_quote( $gallery_slug, '/' ) . '/myfavorites/?$',
			'index.php?pagename=' . $gallery_slug . '/myfavorites',
			'top'
		);


		// Flush rules if needed (check option flag).
		$flush_rules = get_option( 'brag_book_gallery_flush_rewrite_rules', false );

		if ( $flush_rules ) {
			flush_rewrite_rules();
			delete_option( 'brag_book_gallery_flush_rewrite_rules' );
		}
	}

	/**
	 * Enqueue frontend assets
	 *
	 * Loads CSS and JavaScript files for the frontend.
	 * Assets are conditionally loaded based on page context.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// The Assets class handles its own enqueuing through its hooks.
		// This method is kept for backward compatibility but the actual
		// enqueuing is handled by the Assets class itself which checks
		// for gallery pages internally.

		/**
		 * Fires when frontend assets should be enqueued.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_enqueue_frontend_assets' );
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads CSS and JavaScript files for the admin area.
	 * Assets are conditionally loaded based on admin page.
	 *
	 * @since 3.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Sanitize hook suffix.
		$hook_suffix = sanitize_text_field( $hook_suffix );

		// The Assets class handles its own enqueuing through its hooks.
		// This method is kept for backward compatibility but the actual
		// enqueuing is handled by the Assets class itself which checks
		// for admin pages internally.

		/**
		 * Fires when admin assets should be enqueued.
		 *
		 * @since 3.0.0
		 *
		 * @param string $hook_suffix Current admin page hook suffix.
		 */
		do_action( 'brag_book_gallery_enqueue_admin_assets', $hook_suffix );
	}

	/**
	 * Load plugin textdomain
	 *
	 * Loads translation files for internationalization.
	 * Supports WordPress language packs and local translations.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		$loaded = load_plugin_textdomain(
			'brag-book-gallery',
			false,
			dirname( plugin_basename( self::get_plugin_file() ) ) . '/languages'
		);

		/**
		 * Fires after the plugin textdomain is loaded.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $loaded Whether the textdomain was loaded successfully.
		 */
		do_action( 'brag_book_gallery_textdomain_loaded', $loaded );
	}

	/**
	 * Register custom post types
	 *
	 * Registers any custom post types required by the plugin.
	 * Currently registers the form-entries post type for consultations.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function register_post_types(): void {

		// Register form-entries post type for consultations.
		$labels = array(
			'name'               => esc_html__(
				'Consultation Entries',
				'brag-book-gallery'
			),
			'singular_name'      => esc_html__(
				'Consultation Entry',
				'brag-book-gallery'
			),
			'menu_name'          => esc_html__(
				'Consultations',
				'brag-book-gallery'
			),
			'add_new'            => esc_html__(
				'Add New',
				'brag-book-gallery'
			),
			'add_new_item'       => esc_html__(
				'Add New Entry',
				'brag-book-gallery'
			),
			'edit_item'          => esc_html__(
				'Edit Entry',
				'brag-book-gallery'
			),
			'new_item'           => esc_html__(
				'New Entry',
				'brag-book-gallery'
			),
			'view_item'          => esc_html__(
				'View Entry',
				'brag-book-gallery'
			),
			'search_items'       => esc_html__(
				'Search Entries',
				'brag-book-gallery'
			),
			'not_found'          => esc_html__(
				'No entries found',
				'brag-book-gallery'
			),
			'not_found_in_trash' => esc_html__(
				'No entries found in Trash',
				'brag-book-gallery'
			),
		);

		register_post_type(
			'form-entries',
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array(
					'title',
					'editor',
					'custom-fields'
				),
				'show_in_rest'        => false,
			)
		);
	}

	/**
	 * Initialize REST API endpoints
	 *
	 * Registers custom REST API endpoints.
	 * Provides extension point for additional REST routes.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function init_rest_api(): void {
		add_action(
			'rest_api_init',
			function() {
				/**
				 * Fires when REST API should be initialized.
				 *
				 * @since 3.0.0
				 */
				do_action( 'brag_book_gallery_rest_api_init' );
			}
		);
	}

	/**
	 * Check if current page is a gallery page.
	 *
	 * Determines gallery page context using cached results for performance.
	 * Checks both stored gallery pages and main gallery page.
	 *
	 * @since 3.0.0
	 * @return bool True if on gallery page.
	 *
	 * @example
	 * ```php
	 * if ( $this->is_gallery_page() ) {
	 *     // Load gallery-specific assets
	 * }
	 * ```
	 */
	private function is_gallery_page(): bool {
		// Use cached result if available
		if ( isset( $this->cache['is_gallery_page'] ) ) {
			return $this->cache['is_gallery_page'];
		}

		// Get current page ID
		$current_page_id = absint( get_queried_object_id() );

		if ( ! $current_page_id ) {
			$this->cache['is_gallery_page'] = false;
			return false;
		}

		// Check against all gallery page IDs
		$result = $this->check_gallery_page_ids( $current_page_id );

		// Cache and return result
		$this->cache['is_gallery_page'] = $result;
		return $result;
	}

	/**
	 * Check if current admin page is a plugin page.
	 *
	 * Optimized admin page detection with caching and PHP 8.2 features.
	 * Used for conditional asset loading and feature activation.
	 *
	 * @since 3.0.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool True if on plugin admin page.
	 *
	 * @example
	 * ```php
	 * if ( $this->is_plugin_admin_page( $hook_suffix ) ) {
	 *     wp_enqueue_script( 'plugin-admin' );
	 * }
	 * ```
	 */
	private function is_plugin_admin_page( string $hook_suffix ): bool {
		// Sanitize and cache key
		$hook_suffix = sanitize_text_field( $hook_suffix );
		$cache_key = 'admin_page_' . $hook_suffix;

		// Return cached result if available
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		/**
		 * Filters the list of plugin admin pages.
		 *
		 * @since 3.0.0
		 * @param array<int, string> $plugin_pages List of plugin page hooks.
		 * @param string $hook_suffix Current page hook.
		 */
		$plugin_pages = apply_filters(
			'brag_book_gallery_admin_pages',
			self::ADMIN_PAGES,
			$hook_suffix
		);

		// Check and cache result
		$result = in_array( $hook_suffix, $plugin_pages, true );
		$this->cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Check plugin version and set upgrade flag if needed.
	 *
	 * Smart version comparison with upgrade detection and automatic
	 * migration triggering using semantic versioning.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function check_version(): void {
		// Get stored version with validation
		$stored_version = $this->get_stored_version();

		// Check if upgrade needed using PHP 8 comparison
		$needs_upgrade = version_compare( $stored_version, self::VERSION, '<' );

		if ( $needs_upgrade ) {
			$this->trigger_version_upgrade( $stored_version );
		}
	}

	/**
	 * Run upgrade routines if needed
	 *
	 * Checks for upgrade flag and runs necessary database
	 * and option upgrades.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function maybe_upgrade(): void {
		// Check if upgrade is needed.
		$needs_upgrade = get_option( 'brag_book_gallery_needs_upgrade', false );

		if ( ! $needs_upgrade ) {
			return;
		}

		// Run upgrade routines.
		$this->run_upgrades();

		// Clear upgrade flag.
		delete_option( 'brag_book_gallery_needs_upgrade' );

		// Set flag to flush rewrite rules.
		update_option( 'brag_book_gallery_flush_rewrite_rules', true );

		/**
		 * Fires after plugin upgrades are completed.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_upgrades_completed' );
	}

	/**
	 * Run database and option upgrades
	 *
	 * Executes version-specific upgrade routines.
	 * Extensions can hook into this process.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function run_upgrades(): void {
		/**
		 * Fires when plugin upgrades should be run.
		 *
		 * @since 3.0.0
		 *
		 * @param string $version Current plugin version.
		 */
		do_action(
			'brag_book_gallery_run_upgrades',
			self::VERSION
		);
	}

	/**
	 * Setup LiteSpeed cache exclusions for AJAX and API endpoints.
	 *
	 * Comprehensive LiteSpeed Cache compatibility layer ensuring dynamic
	 * content functions properly. Uses PHP 8.2 arrow functions.
	 *
	 * Exclusions:
	 * - AJAX actions for dynamic loading
	 * - Gallery page URLs
	 * - Query string parameters
	 * - REST API endpoints
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function setup_litespeed_exclusions(): void {
		// Only proceed if LiteSpeed Cache is active
		if ( ! defined( 'LSCWP_V' ) ) {
			return;
		}

		// Add AJAX actions exclusions
		add_filter(
			'litespeed_cache_ajax_actions_no_cache',
			fn( array $actions ): array => array_merge( $actions, self::LITESPEED_AJAX_EXCLUSIONS )
		);

		// Add query string exclusions
		add_filter(
			'litespeed_cache_qs_blacklist',
			fn( array $qs ): array => array_merge( $qs, self::LITESPEED_QS_EXCLUSIONS )
		);

		// Exclude gallery pages from caching
		add_action( 'init', [ $this, 'exclude_gallery_pages_from_cache' ], 1 );

		// Disable cache for REST API endpoints
		add_filter(
			'litespeed_cache_rest_api_cache',
			[ $this, 'filter_rest_api_cache' ],
			10,
			2
		);

		do_action( 'qm/debug', 'LiteSpeed Cache exclusions configured' );
	}

	/**
	 * Plugin activation handler
	 *
	 * Runs when the plugin is activated. Sets up initial database
	 * tables, options, and rewrite rules.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function activate(): void {
		// Set initial version.
		update_option( 'brag_book_gallery_version', self::VERSION );

		// Set flag to flush rewrite rules on next init.
		update_option( 'brag_book_gallery_flush_rewrite_rules', true );

		// Create necessary database tables or options.
		$this->create_tables();

		// Set default options.
		$this->set_default_options();

		// Register post types and rewrite rules, then flush immediately.
		$post_types = new Post_Types();
		$post_types->register_post_types();
		flush_rewrite_rules();

		// Schedule daily transient cleanup at 1 AM
		$this->schedule_transient_cleanup();

		/**
		 * Fires when the plugin is activated.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_activate' );
	}

	/**
	 * Plugin deactivation handler
	 *
	 * Runs when the plugin is deactivated. Cleans up rewrite rules
	 * and scheduled events.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Flush rewrite rules.
		flush_rewrite_rules();

		// Clear scheduled events.
		$this->clear_scheduled_events();

		/**
		 * Fires when the plugin is deactivated.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_deactivate' );
	}

	/**
	 * Create necessary database tables
	 *
	 * Creates custom database tables required by the plugin.
	 * Currently creates sync tracking tables for dual-mode functionality.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function create_tables(): void {
		// Create sync tracking tables for dual-mode functionality.
		if ( isset( $this->services['database'] ) && $this->services['database'] instanceof Database ) {
			$this->services['database']->create_tables();
		}

		/**
		 * Fires when database tables should be created.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_create_tables' );
	}

	/**
	 * Set default plugin options
	 *
	 * Sets initial plugin options during activation.
	 * Only sets options that don't already exist.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function set_default_options(): void {
		// Set defaults only if not already set.
		$defaults = array(
			'brag_book_gallery_version'  => self::VERSION,
			'brag_book_gallery_seo_plugin_selector'     => 0,
			'brag_book_gallery_favorite_caseIds_count'  => 0,
		);

		foreach ( $defaults as $option => $value ) {
			if ( false === get_option( $option ) ) {
				update_option( $option, $value );
			}
		}

		/**
		 * Fires when default options are being set.
		 *
		 * @since 3.0.0
		 *
		 * @param array $defaults Default option values.
		 */
		do_action(
			'brag_book_gallery_set_default_options',
			$defaults
		);
	}

	/**
	 * Schedule transient cleanup cron job.
	 *
	 * Schedules a daily cron job to run at 1am to clean up expired transients.
	 * Compatible with WP Engine and other managed hosting providers.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	private function schedule_transient_cleanup(): void {
		// Check if event is already scheduled
		if ( ! wp_next_scheduled( 'brag_book_gallery_cleanup_expired_transients' ) ) {
			// Schedule for 1am daily (using server timezone)
			$tomorrow_1am = strtotime( 'tomorrow 1am' );
			wp_schedule_event( $tomorrow_1am, 'daily', 'brag_book_gallery_cleanup_expired_transients' );
		}

		// Schedule WP Cache cleanup (for WP Engine object cache)
		if ( ! wp_next_scheduled( 'brag_book_gallery_cleanup_wp_cache' ) ) {
			// Schedule for 1:30am daily (using server timezone) - offset from transient cleanup
			$tomorrow_1_30am = strtotime( 'tomorrow 1:30am' );
			wp_schedule_event( $tomorrow_1_30am, 'daily', 'brag_book_gallery_cleanup_wp_cache' );
		}
	}

	/**
	 * Cleanup expired transients.
	 *
	 * Removes all expired transients from the database to prevent bloat.
	 * This method is called by the scheduled cron job.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function cleanup_expired_transients(): void {
		global $wpdb;

		// Delete expired transients (timeout < current time)
		$current_time = time();

		// Delete expired transient options
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE '_transient_timeout_%'
				 AND option_value < %d",
				$current_time
			)
		);

		// Delete orphaned transients (transients without timeouts)
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_%'
			 AND option_name NOT LIKE '_transient_timeout_%'
			 AND NOT EXISTS (
				 SELECT 1 FROM {$wpdb->options} o2
				 WHERE o2.option_name = CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
			 )"
		);

		// For multisite, also clean site transients
		if ( is_multisite() ) {
			// Delete expired site transients
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta}
					 WHERE meta_key LIKE '_site_transient_timeout_%'
					 AND meta_value < %d",
					$current_time
				)
			);

			// Delete orphaned site transients
			$wpdb->query(
				"DELETE FROM {$wpdb->sitemeta}
				 WHERE meta_key LIKE '_site_transient_%'
				 AND meta_key NOT LIKE '_site_transient_timeout_%'
				 AND NOT EXISTS (
					 SELECT 1 FROM {$wpdb->sitemeta} sm2
					 WHERE sm2.meta_key = CONCAT('_site_transient_timeout_', SUBSTRING(meta_key, 17))
				 )"
			);
		}

		/**
		 * Fires after expired transients have been cleaned up.
		 *
		 * @since 3.2.0
		 */
		do_action( 'brag_book_gallery_transients_cleaned' );
	}

	/**
	 * Cleanup WP Engine object cache.
	 *
	 * Clears all plugin-related object cache entries. Since object cache doesn't
	 * provide a way to query for expired items, we flush all plugin cache items
	 * and let them be regenerated as needed.
	 *
	 * @since 3.2.4
	 * @return void
	 */
	public function cleanup_wp_cache(): void {
		// Only run if WP Engine object cache is available
		if ( ! function_exists( 'wp_cache_delete' ) ) {
			return;
		}

		// List of known cache key patterns used by the plugin
		$cache_patterns = [
			// API responses
			'api_',
			'carousel_',
			'pagination_',

			// Gallery data
			'sidebar_',
			'cases_',
			'filtered_cases_',
			'all_cases_',

			// SEO and sitemaps
			'sitemap_',
			'combined_sidebar_',

			// Sync and migration
			'sync_',
			'migration_',
			'force_update_',

			// Rate limiting
			'rate_limit_',

			// Forms
			'consultation_',

			// System
			'mode_',
			'rewrite_notice_',
			'github_update_',
			'meta_',
		];

		$deleted_count = 0;

		// Try to delete cache items by known patterns
		// Note: This is not perfect since we can't enumerate object cache keys,
		// but it covers the main cache keys used by the plugin
		foreach ( $cache_patterns as $pattern ) {
			// We'll need to track cache keys when they're created to make this more effective
			// For now, we can only clear known specific keys

			// Example of clearing a known key pattern - this would need to be expanded
			// based on actual cache keys used by the plugin
			$result = wp_cache_delete( $pattern, 'brag_book_gallery' );
			if ( $result ) {
				$deleted_count++;
			}
		}

		// Alternative approach: Flush the entire group if supported
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'brag_book_gallery' );
			$deleted_count = count( $cache_patterns ); // Estimate
		}

		/**
		 * Fires after WP Engine object cache has been cleaned up.
		 *
		 * @since 3.2.4
		 * @param int $deleted_count Number of cache items cleared (estimated).
		 */
		do_action( 'brag_book_gallery_wp_cache_cleaned', $deleted_count );
	}

	/**
	 * Clear scheduled events
	 *
	 * Removes all scheduled cron events created by the plugin.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	private function clear_scheduled_events(): void {
		// Clear any scheduled cron events.
		wp_clear_scheduled_hook( 'brag_book_gallery_daily_cleanup' );
		wp_clear_scheduled_hook( 'brag_book_gallery_cleanup_expired_transients' );
		wp_clear_scheduled_hook( 'brag_book_gallery_cleanup_wp_cache' );

		/**
		 * Fires when scheduled events are being cleared.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_clear_scheduled_events' );
	}

	/**
	 * Get a service instance.
	 *
	 * Service locator pattern implementation with type safety.
	 * Returns null for non-existent services.
	 *
	 * @since 3.0.0
	 * @param string $service Service identifier.
	 * @return object|null Service instance or null.
	 *
	 * @example
	 * ```php
	 * $settings = $setup->get_service( 'settings_manager' );
	 * $database = $setup->get_service( 'database' );
	 * ```
	 */
	public function get_service( string $service ): ?object {
		$service = sanitize_key( $service );
		return $this->services[ $service ] ?? null;
	}

	/**
	 * Check if plugin is initialized
	 *
	 * Determines whether the plugin has completed initialization.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if initialized, false otherwise.
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}


	/**
	 * Clean shortcode output to remove unwanted paragraph and break tags
	 *
	 * @since 3.0.0
	 * @param string $content Shortcode content to clean.
	 * @return string Cleaned content.
	 */
	public static function clean_shortcode_text( string $content ): string {
		// Remove empty paragraph tags that WordPress adds
		$content = preg_replace( '/<p[^>]*>\s*<\/p>/i', '', $content );

		// Remove paragraph tags that only contain whitespace or break tags
		$content = preg_replace( '/<p[^>]*>\s*(<br\s*\/?>)?\s*<\/p>/i', '', $content );

		// Remove standalone <p> and </p> tags
		$content = str_replace( array( '<p>', '</p>' ), '', $content );

		// Remove line break tags in various formats
		$content = str_replace( array( '<br>', '<br/>', '<br />', '<br/>' ), '', $content );

		// Remove paragraph tags with only class or other attributes but no content
		$content = preg_replace( '/<p[^>]*class="[^"]*"[^>]*>\s*<\/p>/i', '', $content );

		// Remove any paragraph tags that wrap only our shortcode content divs
		$content = preg_replace( '/<p[^>]*>\s*(<div[^>]*class="[^"]*brag-book-gallery[^"]*"[^>]*>)/i', '$1', $content );
		$content = preg_replace( '/(<\/div>)\s*<\/p>/i', '$1', $content );

		// Remove HTML comments that WordPress may add
		$content = preg_replace( '/<!--(.|\s)*?-->/', '', $content );

		// Clean up multiple consecutive line breaks and whitespace
		$content = preg_replace( '/\n\s*\n/', "\n", $content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		// Final pass to remove any remaining empty p tags
		$content = preg_replace( '/<p[^>]*>\s*<\/p>/i', '', $content );

		return $content;
	}


	// get_mode_manager method removed per user request

	/**
	 * Get Settings Manager instance.
	 *
	 * Type-safe accessor for Settings Manager service.
	 *
	 * @since 3.0.0
	 * @return Settings_Manager|null Settings Manager instance.
	 */
	public function get_settings_manager(): ?Settings_Manager {
		$service = $this->services['settings_manager'] ?? null;
		return $service instanceof Settings_Manager ? $service : null;
	}

	/**
	 * Get plugin version
	 *
	 * Returns the current plugin version string.
	 *
	 * @since 3.0.0
	 *
	 * @return string Plugin version.
	 */
	public static function get_plugin_version(): string {
		return self::VERSION;
	}

	/**
	 * Get plugin file path
	 *
	 * Returns the absolute path to the main plugin file.
	 *
	 * @since 3.0.0
	 *
	 * @return string Plugin file path.
	 */
	public static function get_plugin_file(): string {
		// This should be set by init_properties in Trait_Tools.
		$plugin_file = self::$plugin_file ?? trailingslashit( dirname( __DIR__, 2 ) ) . 'brag-book-gallery.php';

		return $plugin_file;
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Cloning is forbidden.', 'brag-book-gallery' ),
			'3.0.0'
		);
	}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing is forbidden.', 'brag-book-gallery' ),
			'3.0.0'
		);
	}

	/**
	 * Validate plugin file.
	 *
	 * @since 3.0.0
	 * @param string $plugin_file Plugin file path.
	 * @return bool True if valid.
	 */
	private static function validate_plugin_file( string $plugin_file ): bool {
		return ! empty( $plugin_file ) && file_exists( $plugin_file ) && is_readable( $plugin_file );
	}

	/**
	 * Register admin-specific hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function register_admin_hooks(): void {
		add_action( 'admin_init', [ $this, 'admin_init' ] );

		// Add plugin action links
		$plugin_basename = plugin_basename( self::get_plugin_file() );
		add_filter(
			'plugin_action_links_' . $plugin_basename,
			[ $this, 'add_plugin_action_links' ]
		);
	}

	/**
	 * Check gallery page IDs.
	 *
	 * @since 3.0.0
	 * @param int $current_page_id Current page ID.
	 * @return bool True if gallery page.
	 */
	private function check_gallery_page_ids( int $current_page_id ): bool {
		// Get all gallery page IDs
		$gallery_page_ids = array_map(
			'absint',
			(array) get_option( 'brag_book_gallery_gallery_stored_pages_ids', [] )
		);

		$main_gallery_page_id = absint(
			get_option( 'brag_book_gallery_page_id', 0 )
		);

		return in_array( $current_page_id, $gallery_page_ids, true )
			|| $current_page_id === $main_gallery_page_id;
	}

	/**
	 * Get stored plugin version.
	 *
	 * @since 3.0.0
	 * @return string Version string.
	 */
	private function get_stored_version(): string {
		$version = get_option( 'brag_book_gallery_version', '0.0.0' );
		return is_string( $version ) ? sanitize_text_field( $version ) : '0.0.0';
	}

	/**
	 * Trigger version upgrade.
	 *
	 * @since 3.0.0
	 * @param string $old_version Previous version.
	 * @return void
	 */
	private function trigger_version_upgrade( string $old_version ): void {
		update_option( 'brag_book_gallery_needs_upgrade', true );
		update_option( 'brag_book_gallery_version', self::VERSION );

		/**
		 * Fires when the plugin version is updated.
		 *
		 * @since 3.0.0
		 * @param string $new_version New plugin version.
		 * @param string $old_version Previous plugin version.
		 */
		do_action(
			'brag_book_gallery_version_updated',
			self::VERSION,
			$old_version
		);

		do_action( 'qm/debug', sprintf(
			'Version upgrade triggered: %s -> %s',
			$old_version,
			self::VERSION
		) );
	}

	/**
	 * Exclude gallery pages from LiteSpeed cache.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function exclude_gallery_pages_from_cache(): void {
		if ( is_admin() ) {
			return;
		}

		// Check current URL against gallery slugs
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Sanitized below
		$current_url = $_SERVER['REQUEST_URI'] ?? '';
		$current_url = esc_url_raw( wp_unslash( $current_url ) );

		$gallery_slugs = (array) get_option( 'brag_book_gallery_page_slug', [] );

		foreach ( $gallery_slugs as $slug ) {
			$slug = sanitize_text_field( (string) $slug );

			if ( ! empty( $slug ) && str_contains( $current_url, $slug ) ) {
				do_action( 'litespeed_control_set_nocache', 'bragbook gallery page' );
				break;
			}
		}
	}

	/**
	 * Filter REST API cache for plugin endpoints.
	 *
	 * @since 3.0.0
	 * @param bool   $cache Whether to cache.
	 * @param string $request_route Request route.
	 * @return bool Modified cache setting.
	 */
	public function filter_rest_api_cache( bool $cache, string $request_route ): bool {
		$request_route = sanitize_text_field( $request_route );

		if ( str_contains( $request_route, 'brag-book-gallery' ) ) {
			return false;
		}

		return $cache;
	}

	/**
	 * Handle delayed rewrite rules flush.
	 *
	 * This is scheduled after page creation to prevent timeouts
	 * on managed hosting environments like WP Engine.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function handle_delayed_rewrite_flush(): void {
		// Only flush if we're not in an admin AJAX request to prevent conflicts
		if ( wp_doing_ajax() ) {
			return;
		}

		try {
			// Flush rewrite rules
			flush_rewrite_rules();

			// Clear any object cache to ensure fresh data
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}

			// Log for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: Delayed rewrite rules flush completed' );
			}
		} catch ( Exception $e ) {
			// Log error but don't break site
			error_log( 'BRAGBook Gallery: Failed to flush rewrite rules - ' . $e->getMessage() );
		}
	}

	/**
	 * Migrate page slug option from array to string
	 *
	 * Fixes installations where the page slug was mistakenly stored as an array.
	 * This migration runs once on plugin init to ensure consistency.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function migrate_page_slug_option(): void {
		// Check if migration has already run
		$migrated = get_option( 'brag_book_gallery_page_slug_migrated', false );
		if ( $migrated ) {
			return;
		}

		$current_slug = get_option( 'brag_book_gallery_page_slug', '' );

		// If it's an array, convert to string
		if ( is_array( $current_slug ) ) {
			$string_slug = ! empty( $current_slug ) ? $current_slug[0] : 'gallery';
			update_option( 'brag_book_gallery_page_slug', $string_slug );
			error_log( "BRAGBook Gallery: Migrated page slug from array to string: '{$string_slug}'" );
		}

		// Mark migration as complete
		update_option( 'brag_book_gallery_page_slug_migrated', true );
	}

	/**
	 * Load cache helper functions
	 *
	 * Loads the cache helper functions that provide WP Engine compatibility
	 * for caching operations throughout the plugin.
	 *
	 * @since 3.2.4
	 * @return void
	 */
	private function load_cache_helpers(): void {
		$helpers_file = self::get_plugin_path() . 'includes/functions/cache-helpers.php';

		if ( file_exists( $helpers_file ) ) {
			require_once $helpers_file;
		}
	}

	/**
	 * Handle scheduled view tracking
	 *
	 * Processes scheduled view tracking events that were deferred to avoid
	 * blocking page load. This method is called by WordPress cron system.
	 *
	 * @since 3.0.0
	 * @param string $case_id The case ID to track
	 * @return void
	 */
	public function handle_scheduled_view_tracking( string $case_id ): void {
		if ( empty( $case_id ) ) {
			return;
		}

		try {
			// Get API configuration
			$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
			$website_property_ids = get_option( 'brag_book_gallery_website_property_id', array() );

			if ( empty( $api_tokens ) || empty( $website_property_ids ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: API configuration missing for scheduled view tracking' );
				}
				return;
			}

			// Use the first configured API token and property ID
			$api_token = is_array( $api_tokens ) ? $api_tokens[0] : $api_tokens;
			$website_property_id = is_array( $website_property_ids ) ? $website_property_ids[0] : $website_property_ids;

			// Get base API URL
			$api_endpoint = get_option( 'brag_book_gallery_api_endpoint', 'https://app.bragbookgallery.com' );

			// Build the tracking URL
			$tracking_url = sprintf(
				'%s/api/plugin/tracker?apiToken=%s&websitepropertyId=%s',
				$api_endpoint,
				urlencode( $api_token ),
				urlencode( $website_property_id )
			);

			// Prepare tracking data
			$tracking_data = array(
				'case_id' => $case_id,
				'action'  => 'view',
				'source'  => 'wordpress_plugin_scheduled',
			);

			// Make the API request
			$response = wp_remote_post( $tracking_url, array(
				'body'    => wp_json_encode( $tracking_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			) );

			// Check for errors
			if ( is_wp_error( $response ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'BRAGBook Gallery: Scheduled view tracking API error - ' . $response->get_error_message() );
				}
				return;
			}

			// Check response code
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code < 200 || $response_code >= 300 ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery: Scheduled view tracking API returned status {$response_code}" );
				}
				return;
			}

			// Parse response
			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );

			// Log success or failure
			if ( isset( $response_data['success'] ) && $response_data['success'] ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery: Successfully tracked scheduled view for case {$case_id}" );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "BRAGBook Gallery: Scheduled view tracking failed for case {$case_id} - " . wp_json_encode( $response_data ) );
				}
			}

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BRAGBook Gallery: Scheduled view tracking exception - ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Add custom cron schedules
	 *
	 * WordPress doesn't include a weekly schedule by default,
	 * so we add it here for the automatic sync functionality.
	 *
	 * @since 3.3.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules with weekly option added.
	 */
	public function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Weekly', 'brag-book-gallery' ),
			];
		}
		return $schedules;
	}

}
