<?php
/**
 * Plugin Setup Class
 *
 * Manages plugin initialization, configuration, and bootstrapping.
 * This is the main entry point for initializing all plugin functionality.
 * Implements the Singleton pattern to ensure only one instance exists.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Core
 * @since      3.0.0
 * @author     Candace Crowe Design <info@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Core;

use BRAGBookGallery\Includes\Resources\Assets;
use BRAGBookGallery\Includes\Extend\Templates;
use BRAGBookGallery\Includes\Extend\Shortcodes;
use BRAGBookGallery\Includes\Extend\Rewrite_Rules_Handler;
use BRAGBookGallery\Includes\Admin\Settings_Manager;
use BRAGBookGallery\Includes\SEO\On_Page;
use BRAGBookGallery\Includes\SEO\Sitemap;
use BRAGBookGallery\Includes\Mode\Mode_Manager;
use BRAGBookGallery\Includes\Core\Database;
use BRAGBookGallery\Includes\Core\Template_Loader;
use BRAGBookGallery\Includes\Core\Query_Handler;
use BRAGBookGallery\Includes\Core\URL_Router;
use BRAGBookGallery\Includes\Sync\Sync_Manager;
use BRAGBookGallery\Includes\Migration\Migration_Manager;
use BRAGBookGallery\Includes\Traits\Trait_Api;
use BRAGBookGallery\Includes\Traits\Trait_Tools;

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
	 * Plugin version for cache busting
	 *
	 * Used for versioning assets and database schema.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	private const VERSION = '3.0.0';

	/**
	 * Hook priority for init actions
	 *
	 * Standard priority for WordPress init hooks.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const INIT_PRIORITY = 10;

	/**
	 * Hook priority for template filters
	 *
	 * High priority to ensure our templates override others.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	private const TEMPLATE_PRIORITY = 99;

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
	 * Service instances
	 *
	 * Container for all plugin service instances.
	 * Keys are service names, values are service objects.
	 *
	 * @since 3.0.0
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Initialization status
	 *
	 * Tracks whether the plugin has been initialized.
	 * Prevents duplicate initialization.
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor - Initialize plugin components
	 *
	 * Sets up all hooks, services, and dependencies required for the plugin.
	 * This method should only be called once during plugin initialization.
	 *
	 * @since 3.0.0
	 */
	private function __construct() {
		// Register core hooks
		$this->register_hooks();

		// Initialize services
		$this->init_services();

		// Mark as initialized
		$this->initialized = true;
	}

	/**
	 * Get plugin instance (Singleton pattern)
	 *
	 * Ensures only one instance of the plugin setup is created.
	 * Thread-safe implementation of the Singleton pattern.
	 *
	 * @since 3.0.0
	 * 
	 * @return self Plugin instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 *
	 * Main entry point for plugin initialization. Should be called
	 * from the main plugin file during WordPress initialization.
	 *
	 * @since 3.0.0
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 * 
	 * @return void
	 */
	public static function init_plugin( string $plugin_file ): void {
		// Validate plugin file.
		if ( ! file_exists( $plugin_file ) ) {
			wp_die(
				esc_html__( 'Invalid plugin file path.', 'brag-book-gallery' ),
				esc_html__( 'Plugin Error', 'brag-book-gallery' ),
				array( 'response' => 500 )
			);
		}

		// Initialize plugin properties (paths, URLs, etc.).
		self::init_properties( $plugin_file );

		// Get or create plugin instance.
		self::get_instance();
	}

	/**
	 * Register WordPress hooks
	 *
	 * Sets up all action and filter hooks required by the plugin.
	 * Follows WordPress VIP standards for hook registration.
	 *
	 * @since 3.0.0
	 * 
	 * @return void
	 */
	private function register_hooks(): void {
		// Core initialization.
		add_action(
			'init',
			array( $this, 'init' ),
			self::INIT_PRIORITY
		);
		
		// Initialize updater after plugins are loaded.
		add_action(
			'plugins_loaded',
			array( $this, 'init_updater' ),
			10
		);

		// Asset loading hooks.
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_frontend_assets' )
		);

		// Admin asset loading.
		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_admin_assets' )
		);

		// Template hooks.
		add_filter(
			'template_include',
			array( Templates::class, 'include_template' ),
			self::TEMPLATE_PRIORITY
		);

		// Activation hook.
		register_activation_hook(
			self::get_plugin_file(),
			array( $this, 'activate' )
		);

		// Deactivation hook.
		register_deactivation_hook(
			self::get_plugin_file(),
			array( $this, 'deactivate' )
		);

		// Admin hooks.
		if ( is_admin() ) {
			add_action(
				'admin_init',
				array( $this, 'admin_init' )
			);

			// Add plugin action links (Settings link on plugins page)
			$plugin_basename = plugin_basename( self::get_plugin_file() );
			add_filter(
				'plugin_action_links_' . $plugin_basename,
				array( $this, 'add_plugin_action_links' )
			);
		}

		// Add LiteSpeed cache exclusions
		$this->setup_litespeed_exclusions();
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

		// Initialize Mode Manager (handles mode-specific components).
		$this->services['mode_manager'] = Mode_Manager::get_instance();

		// Initialize core dual-mode components.
		$this->services['template_loader'] = new Template_Loader();
		$this->services['query_handler'] = new Query_Handler();
		$this->services['url_router'] = new URL_Router();

		// Initialize sync components (for Local mode).
		$this->services['sync_manager'] = new Sync_Manager();

		// Initialize migration components.
		$this->services['migration_manager'] = new Migration_Manager();

		// Initialize SEO Manager (handles SEO optimization and plugin detection).
		$this->services['seo_manager'] = new \BRAGBookGallery\Includes\SEO\SEO_Manager();

		// Initialize admin settings manager (handles all settings pages).
		$this->services['settings_manager'] = new Settings_Manager();

		// Initialize SEO components.
		$this->services['sitemap'] = new Sitemap();
		$this->services['on_page_seo'] = new On_Page();

		// Initialize consultation handler.
		$this->services['consultation'] = new Consultation();

		// Initialize assets handler.
		$this->services['assets'] = new Assets();
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

		// Register shortcodes.
		Shortcodes::register();

		// Setup rewrite rules.
		$this->setup_rewrite_rules();

		// Load plugin textdomain for translations.
		$this->load_textdomain();

		// Register custom post types if needed.
		$this->register_post_types();

		// Initialize REST API endpoints.
		$this->init_rest_api();

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
		// Rewrite rules are handled by the Shortcodes class.
		// This method is kept for potential future manual flush operations.

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
	 * Check if current page is a gallery page
	 *
	 * Determines whether the current request is for a gallery page.
	 * Checks against stored gallery page IDs.
	 *
	 * @since 3.0.0
	 * 
	 * @return bool True if on a gallery page, false otherwise.
	 */
	private function is_gallery_page(): bool {
		// Get current page ID.
		$current_page_id = absint( get_queried_object_id() );

		if ( ! $current_page_id ) {
			return false;
		}

		// Check against stored gallery page IDs.
		$gallery_page_ids = array_map(
			'absint',
			(array) get_option( 'bb_gallery_stored_pages_ids', array() )
		);

		$main_gallery_page_id = absint(
			get_option( 'brag_book_gallery_page_id', 0 )
		);

		return in_array( $current_page_id, $gallery_page_ids, true ) 
			|| $current_page_id === $main_gallery_page_id;
	}

	/**
	 * Check if current admin page is a plugin page
	 *
	 * Determines whether the current admin page belongs to this plugin.
	 * Used for conditional asset loading.
	 *
	 * @since 3.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * 
	 * @return bool True if on a plugin admin page, false otherwise.
	 */
	private function is_plugin_admin_page( string $hook_suffix ): bool {
		// Sanitize input.
		$hook_suffix = sanitize_text_field( $hook_suffix );
		
		// List of plugin admin pages.
		$plugin_pages = array(
			'toplevel_page_brag-book-gallery-settings',
			'brag-book-gallery_page_bb-consultation',
			'admin_page_brag-book-gallery-settings',
		);

		/**
		 * Filters the list of plugin admin pages.
		 *
		 * @since 3.0.0
		 *
		 * @param array  $plugin_pages List of plugin page hooks.
		 * @param string $hook_suffix  Current page hook.
		 */
		$plugin_pages = apply_filters(
			'brag_book_gallery_admin_pages',
			$plugin_pages,
			$hook_suffix
		);

		// Check if current page is in the list.
		return in_array( $hook_suffix, $plugin_pages, true );
	}

	/**
	 * Check plugin version and set upgrade flag if needed
	 *
	 * Compares stored version with current version to determine
	 * if database or option upgrades are required.
	 *
	 * @since 3.0.0
	 * 
	 * @return void
	 */
	private function check_version(): void {
		// Get current version from options.
		$stored_version = sanitize_text_field(
			get_option( 'brag_book_gallery_version', '0.0.0' )
		);

		// Compare versions and set upgrade flag if needed.
		if ( version_compare( $stored_version, self::VERSION, '<' ) ) {
			update_option( 'brag_book_gallery_needs_upgrade', true );
			update_option( 'brag_book_gallery_version', self::VERSION );
			
			/**
			 * Fires when the plugin version is updated.
			 *
			 * @since 3.0.0
			 *
			 * @param string $new_version New plugin version.
			 * @param string $old_version Previous plugin version.
			 */
			do_action(
				'brag_book_gallery_version_updated',
				self::VERSION,
				$stored_version
			);
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
	 * Setup LiteSpeed cache exclusions for AJAX and API endpoints
	 *
	 * Configures LiteSpeed Cache to exclude gallery pages and AJAX
	 * endpoints from caching to ensure dynamic content works properly.
	 *
	 * @since 3.0.0
	 * 
	 * @return void
	 */
	private function setup_litespeed_exclusions(): void {
		// Only proceed if LiteSpeed Cache is active.
		if ( ! defined( 'LSCWP_V' ) ) {
			return;
		}

		// Add AJAX actions to LiteSpeed no-cache list.
		add_filter(
			'litespeed_cache_ajax_actions_no_cache',
			function( $actions ) {
				$bragbook_actions = array(
					'brag_book_load_filtered_gallery',
					'brag_book_gallery_load_case',
					'load_case_details',
					'brag_book_load_case_details_html',
					'brag_book_load_more_cases',
					'brag_book_load_filtered_cases',
					'brag_book_gallery_clear_cache',
					'brag_book_flush_rewrite_rules',
				);

				return array_merge( $actions, $bragbook_actions );
			}
		);

		// Exclude gallery pages from caching.
		add_action(
			'init',
			function() {
				if ( ! is_admin() ) {
					// Check if we're on a gallery page.
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
					$current_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
					$current_url = esc_url_raw( $current_url );
					
					$gallery_slugs = (array) get_option( 'brag_book_gallery_page_slug', array() );

					foreach ( $gallery_slugs as $slug ) {
						$slug = sanitize_text_field( $slug );
						
						if ( ! empty( $slug ) && false !== strpos( $current_url, $slug ) ) {
							// Tell LiteSpeed not to cache this page.
							do_action( 'litespeed_control_set_nocache', 'bragbook gallery page' );
							break;
						}
					}
				}
			},
			1
		);

		// Add query string exclusions.
		add_filter(
			'litespeed_cache_qs_blacklist',
			function( $qs ) {
				$bragbook_qs = array(
					'filter_procedure',
					'procedure_title',
					'case_id',
					'filter_category',
					'favorites_section',
				);

				return array_merge( $qs, $bragbook_qs );
			}
		);

		// Disable cache for REST API endpoints.
		add_filter(
			'litespeed_cache_rest_api_cache',
			function( $cache, $request_route ) {
				$request_route = sanitize_text_field( $request_route );
				
				if ( false !== strpos( $request_route, 'brag-book-gallery' ) ) {
					return false;
				}
				
				return $cache;
			},
			10,
			2
		);
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

		// Register rewrite rules and flush immediately.
		// Rewrite rules are handled by Rewrite_Rules_Handler class
		Rewrite_Rules_Handler::custom_rewrite_rules();
		flush_rewrite_rules();

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
			'bb_seo_plugin_selector'     => 0,
			'bb_favorite_caseIds_count'  => 0,
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

		/**
		 * Fires when scheduled events are being cleared.
		 *
		 * @since 3.0.0
		 */
		do_action( 'brag_book_gallery_clear_scheduled_events' );
	}

	/**
	 * Get a service instance
	 *
	 * Retrieves a specific service instance from the container.
	 *
	 * @since 3.0.0
	 *
	 * @param string $service Service name.
	 * 
	 * @return object|null Service instance or null if not found.
	 */
	public function get_service( string $service ): ?object {
		$service = sanitize_key( $service );
		
		return isset( $this->services[ $service ] ) ? $this->services[ $service ] : null;
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
	 * Get Mode Manager instance
	 *
	 * Returns the Mode Manager service instance for mode operations.
	 * Mode Manager handles switching between API and Local modes.
	 *
	 * @since 3.0.0
	 * 
	 * @return Mode_Manager|null Mode Manager instance or null if not initialized.
	 */
	public function get_mode_manager(): ?Mode_Manager {
		return isset( $this->services['mode_manager'] ) 
			&& $this->services['mode_manager'] instanceof Mode_Manager
			? $this->services['mode_manager'] 
			: null;
	}

	/**
	 * Get Settings Manager instance
	 *
	 * Returns the Settings Manager service instance for settings operations.
	 * Settings Manager handles all plugin configuration pages.
	 *
	 * @since 3.0.0
	 * 
	 * @return Settings_Manager|null Settings Manager instance or null if not initialized.
	 */
	public function get_settings_manager(): ?Settings_Manager {
		return isset( $this->services['settings_manager'] )
			&& $this->services['settings_manager'] instanceof Settings_Manager
			? $this->services['settings_manager']
			: null;
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
	 * Prevent cloning of the instance
	 *
	 * Enforces singleton pattern by preventing object cloning.
	 *
	 * @since 3.0.0
	 * 
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
	 * Prevent unserializing of the instance
	 *
	 * Enforces singleton pattern by preventing object unserialization.
	 *
	 * @since 3.0.0
	 * 
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing is forbidden.', 'brag-book-gallery' ),
			'3.0.0'
		);
	}
}
