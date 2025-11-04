<?php
/**
 * Settings Manager Class - Coordinates all settings pages
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Core;

use BRAGBookGallery\Includes\Core\Trait_Tools;
use BRAGBookGallery\Includes\Admin\Pages\API_Page;
use BRAGBookGallery\Includes\Admin\Pages\API_Test_Page;
use BRAGBookGallery\Includes\Admin\Pages\Debug_Page;
use BRAGBookGallery\Includes\Admin\Pages\Dashboard_Page;
use BRAGBookGallery\Includes\Admin\Pages\General_Page;
use BRAGBookGallery\Includes\Admin\Pages\Default_Page;
use BRAGBookGallery\Includes\Admin\Pages\Communications_Page;
use BRAGBookGallery\Includes\Admin\Pages\Help_Page;
use BRAGBookGallery\Includes\Admin\Pages\Changelog_Page;
use BRAGBookGallery\Includes\Admin\Pages\Sync_Page;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Settings Manager Class
 *
 * Centralized manager for all settings pages in the BRAG book Gallery plugin.
 * This class coordinates between different settings pages, manages the admin menu structure,
 * handles asset loading, and maintains backward compatibility with the legacy settings system.
 *
 * The manager follows a modular approach where each settings page is a separate class
 * extending Settings_Base, allowing for clean separation of concerns and easier maintenance.
 *
 * Key responsibilities:
 * - Initialize and manage all settings page instances
 * - Register admin menu pages with conditional visibility based on configuration state
 * - Handle admin asset enqueuing for settings pages
 * - Provide access to individual settings pages for external components
 * - Maintain legacy settings compatibility during transition period
 *
 * @since 3.0.0
 */
class Settings_Manager {
	use Trait_Tools;

	/**
	 * Collection of settings page instances
	 *
	 * Stores all instantiated settings page objects indexed by their type.
	 * Each page handles its own rendering, form processing, and data validation.
	 *
	 * Structure:
	 * - 'general': General plugin settings and overview
	 * - 'mode': Operating mode selection (Default vs Local)
	 * - 'default': Default mode specific settings
	 * - 'local': Local mode specific settings
	 * - 'api': API connection and configuration settings
	 * - 'api_test': API endpoint testing and diagnostics
	 * - 'help': Help documentation and support resources
	 * - 'debug': Debug tools and diagnostic information
	 *
	 * @since 3.0.0
	 * @var array<string, Settings_Base> Array of settings page instances
	 */
	private array $settings_pages = array();


	/**
	 * Menu manager instance
	 *
	 * Handles all menu registration and visibility logic.
	 *
	 * @since 3.0.0
	 * @var Menu|null Menu manager instance
	 */
	private ?Menu $menu_manager = null;

	/**
	 * Constructor - Initialize the Settings Manager
	 *
	 * Sets up the complete settings management system by initializing WordPress hooks.
	 * Settings pages are loaded lazily when needed to avoid early translation loading.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks and actions
	 *
	 * Registers all necessary WordPress hooks for the settings system including:
	 * - Settings page loading and menu registration
	 * - Asset enqueuing for loading CSS/JS files on settings pages only
	 * - AJAX handlers for settings operations
	 * - Cache management hooks
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		// Register AJAX actions early - before admin_menu
		add_action( 'init', array( $this, 'register_ajax_actions' ) );

		// Load settings pages and register menus after init to ensure translations are loaded
		add_action( 'admin_menu', array( $this, 'initialize_settings_pages' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Register AJAX handler for cache clearing.
		add_action( 'wp_ajax_brag_book_clear_legacy_cache', array( 'BRAGBookGallery\Includes\Extend\Ajax_Handlers', 'ajax_clear_legacy_cache' ) );
	}

	/**
	 * Register AJAX actions early
	 *
	 * Creates settings page instances early just to register their AJAX actions.
	 * The pages will be re-used when initialize_settings_pages() is called later.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_ajax_actions(): void {
		// Only register AJAX actions if we haven't loaded pages yet
		if ( empty( $this->settings_pages ) ) {
			// Create minimal instances just for AJAX registration.
			$this->settings_pages['api'] = new API_Page();
			$this->settings_pages['api_test'] = new API_Test_Page();
			$this->settings_pages['debug'] = new Debug_Page();
			$this->settings_pages['sync'] = new Sync_Page();
		}
	}

	/**
	 * Load and instantiate all settings page classes
	 *
	 * Creates instances of each settings page class and stores them in the
	 * settings_pages array for later access. Each page is responsible for its own
	 * initialization, form handling, and rendering.
	 *
	 * Also initializes the legacy Settings class in compatibility mode (without
	 * menu registration) to maintain backward compatibility with existing code
	 * that may directly access the legacy settings system.
	 *
	 * Page loading order is intentional - general settings first, then API config,
	 * followed by mode-specific pages, and finally utility pages (help, debug).
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function load_settings_pages(): void {
		// Initialize all settings pages in logical order
		$this->settings_pages['dashboard']    = new Dashboard_Page();
		$this->settings_pages['general']      = new General_Page();
		// Mode settings removed per user request
		$this->settings_pages['default']      = new Default_Page();

		// Only create Settings_Api if it doesn't exist (it may have been created for AJAX registration)
		if ( ! isset( $this->settings_pages['api'] ) ) {
			$this->settings_pages['api'] = new API_Page();
		}

		// Only create Settings_Api_Test if it doesn't exist (it may have been created for AJAX registration)
		if ( ! isset( $this->settings_pages['api_test'] ) ) {
			$this->settings_pages['api_test'] = new API_Test_Page();
		}

		// Only create Settings_Debug if it doesn't exist (it may have been created for AJAX registration)
		if ( ! isset( $this->settings_pages['debug'] ) ) {
			$this->settings_pages['debug'] = new Debug_Page();
		}

		// Only create Settings_Sync if it doesn't exist (it may have been created for AJAX registration)
		if ( ! isset( $this->settings_pages['sync'] ) ) {
			$this->settings_pages['sync'] = new Sync_Page();
		}

		$this->settings_pages['communications'] = new Communications_Page();
		$this->settings_pages['help']         = new Help_Page();
		$this->settings_pages['changelog']    = new Changelog_Page();

		// Initialize menu manager with settings pages.
		$this->menu_manager = new Menu( $this->settings_pages );
	}

	/**
	 * Initialize settings pages and menu manager
	 *
	 * Loads all settings page instances and initializes the menu manager.
	 * This is called via admin_menu hook to ensure translations are available.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function initialize_settings_pages(): void {
		// Only load once - check if menu manager exists (which means full initialization is done)
		if ( $this->menu_manager !== null ) {
			return;
		}

		$this->load_settings_pages();
	}

	/**
	 * Conditionally enqueue admin assets for settings pages
	 *
	 * Loads CSS and JavaScript files only on BRAG book Gallery settings pages to avoid
	 * bloating other admin pages. Performs several optimizations:
	 *
	 * - Hook-based filtering to load assets only on relevant pages
	 * - File existence check before enqueueing JavaScript to prevent 404 errors
	 * - AJAX localization for dynamic frontend interactions
	 * - Version-based cache busting for proper asset updates
	 *
	 * Assets loaded:
	 * - admin.css: Main admin styling for all settings pages
	 * - admin.js: JavaScript functionality (if file exists)
	 * - Localized AJAX data: URL, nonce, and configuration data
	 *
	 * The method follows WordPress best practices for admin asset management.
	 *
	 * @since 3.0.0
	 * @param string $hook Current admin page hook suffix from WordPress
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on our settings pages to avoid unnecessary asset loading
		if ( ! str_contains( $hook, 'brag-book-gallery' ) ) {
			return;
		}

		// Conditionally enqueue admin JavaScript if the file exists
		$js_file = plugins_url( 'assets/js/admin.js', dirname( __DIR__ ) );
		if ( file_exists( WP_PLUGIN_DIR . '/brag-book-gallery/assets/js/admin.js' ) ) {
			wp_enqueue_script(
				'brag-book-gallery-admin',
				$js_file,
				array( 'jquery' ), // Depends on jQuery for DOM manipulation
				'3.0.0',          // Version for cache busting
				true              // Load in footer for better page performance
			);

			// Provide AJAX configuration data to JavaScript
			wp_localize_script(
				'brag-book-gallery-admin',
				'brag_book_gallery_admin',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ), // WordPress AJAX endpoint
					'nonce'    => wp_create_nonce( 'brag_book_gallery_admin' ), // Security nonce
				)
			);
		}
	}
}
