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

namespace BRAGBookGallery\Includes\Admin;

use BRAGBookGallery\Includes\Traits\Trait_Tools;
use BRAGBookGallery\Includes\Core\Setup;

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Settings Manager Class
 *
 * Centralized manager for all settings pages in the BRAG Book Gallery plugin.
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
	 * - 'mode': Operating mode selection (JavaScript vs Local)
	 * - 'javascript': JavaScript mode specific settings
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
	 * Legacy settings instance for backward compatibility
	 *
	 * Maintains the original Settings class instance to ensure compatibility
	 * with existing code that may rely on the legacy settings system.
	 * This instance is created without menu registration to avoid conflicts
	 * with the new modular settings pages.
	 *
	 * @since 3.0.0
	 * @var Settings|null Legacy settings class instance or null if not initialized
	 */
	private ?Settings $legacy_settings = null;

	/**
	 * Constructor - Initialize the Settings Manager
	 *
	 * Sets up the complete settings management system by initializing WordPress hooks
	 * and loading all settings page instances. The constructor follows a two-step
	 * initialization process to ensure proper dependency loading.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->init_hooks();
		$this->load_settings_pages();
	}

	/**
	 * Initialize WordPress hooks and actions
	 *
	 * Registers all necessary WordPress hooks for the settings system including:
	 * - Admin menu registration for creating settings pages in wp-admin
	 * - Asset enqueuing for loading CSS/JS files on settings pages only
	 *
	 * Uses late binding to ensure all WordPress functions are available when called.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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
		$this->settings_pages['general']    = new Settings_General();
		$this->settings_pages['mode']       = new Settings_Mode();
		$this->settings_pages['javascript'] = new Settings_JavaScript();
		$this->settings_pages['local']      = new Settings_Local();
		$this->settings_pages['api']        = new Settings_Api();
		$this->settings_pages['api_test']   = new Settings_Api_Test();
		$this->settings_pages['help']       = new Settings_Help();
		$this->settings_pages['debug']      = new Settings_Debug();

		// Load legacy settings for backward compatibility (without menu registration)
		// This prevents duplicate menu items while maintaining API compatibility
		$this->legacy_settings = new Settings( false );
	}

	/**
	 * Register WordPress admin menu pages with conditional visibility
	 *
	 * Creates the complete admin menu structure for BRAG Book Gallery settings.
	 * The method implements intelligent menu visibility based on plugin configuration state:
	 *
	 * - General and API settings are always visible
	 * - Mode selection only appears after API is configured
	 * - Mode-specific settings (JavaScript/Local) only appear when that mode is active
	 * - Help and Debug pages are always available for support purposes
	 *
	 * This progressive disclosure approach guides users through the setup process
	 * while preventing configuration errors from incomplete setups.
	 *
	 * Menu structure:
	 * - Main: "BRAG Book Gallery" (General settings)
	 * - Sub: "General" (Overview and status)
	 * - Sub: "API" (API configuration - always visible)
	 * - Sub: "API Test" (Endpoint testing - requires API)
	 * - Sub: "Mode" (Mode selection - requires API)
	 * - Sub: "JavaScript Settings" (JavaScript mode only)
	 * - Sub: "Local Settings" (Local mode only)
	 * - Sub: "Help" (Documentation and support)
	 * - Sub: "Debug" (Diagnostic tools)
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function add_menu_pages(): void {
		// Get custom SVG icon for the main menu item
		$icon_svg = $this->get_menu_icon();

		// Determine current plugin state to control menu visibility
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api_key = ! empty( $api_tokens );

		// Main menu page
		add_menu_page(
			page_title: esc_html__( 'BRAG Book Gallery', 'brag-book-gallery' ),
			menu_title: esc_html__( 'BRAG Book Gallery', 'brag-book-gallery' ),
			capability: 'manage_options',
			menu_slug: 'brag-book-gallery-settings',
			callback: array( $this->settings_pages['general'], 'render' ),
			icon_url: $icon_svg,
			position: 30
		);

		// General Settings (rename first submenu)
		add_submenu_page(
			parent_slug: 'brag-book-gallery-settings',
			page_title: esc_html__( 'General Settings', 'brag-book-gallery' ),
			menu_title: esc_html__( 'General', 'brag-book-gallery' ),
			capability: 'manage_options',
			menu_slug: 'brag-book-gallery-settings',
			callback: array( $this->settings_pages['general'], 'render' )
		);

		// API Settings
		add_submenu_page(
			parent_slug: 'brag-book-gallery-settings',
			page_title: esc_html__( 'API Settings', 'brag-book-gallery' ),
			menu_title: esc_html__( 'API', 'brag-book-gallery' ),
			capability: 'manage_options',
			menu_slug: 'brag-book-gallery-api-settings',
			callback: array( $this->settings_pages['api'], 'render' )
		);

		// API Test - only show if API key is configured
		if ( $has_api_key ) {
			add_submenu_page(
				parent_slug: 'brag-book-gallery-settings',
				page_title: esc_html__( 'API Testing', 'brag-book-gallery' ),
				menu_title: esc_html__( 'API Test', 'brag-book-gallery' ),
				capability: 'manage_options',
				menu_slug: 'brag-book-gallery-api-test',
				callback: array( $this->settings_pages['api_test'], 'render' )
			);
		}

		// Mode Settings - only show if API key is configured
		if ( $has_api_key ) {
			add_submenu_page(
				parent_slug: 'brag-book-gallery-settings',
				page_title: esc_html__( 'Mode Settings', 'brag-book-gallery' ),
				menu_title: esc_html__( 'Mode', 'brag-book-gallery' ),
				capability: 'manage_options',
				menu_slug: 'brag-book-gallery-mode',
				callback: array( $this->settings_pages['mode'], 'render' )
			);
		}

		// JavaScript Settings - only show if JavaScript mode is active AND API key is configured
		if ( $has_api_key && $current_mode === 'javascript' ) {
			add_submenu_page(
				parent_slug: 'brag-book-gallery-settings',
				page_title: esc_html__( 'JavaScript Mode Settings', 'brag-book-gallery' ),
				menu_title: esc_html__( 'JavaScript Settings', 'brag-book-gallery' ),
				capability: 'manage_options',
				menu_slug: 'brag-book-gallery-javascript',
				callback: array( $this->settings_pages['javascript'], 'render' )
			);
		}

		// Local Settings - only show if Local mode is active AND API key is configured
		if ( $has_api_key && $current_mode === 'local' ) {
			add_submenu_page(
				parent_slug: 'brag-book-gallery-settings',
				page_title: esc_html__( 'Local Mode Settings', 'brag-book-gallery' ),
				menu_title: esc_html__( 'Local Settings', 'brag-book-gallery' ),
				capability: 'manage_options',
				menu_slug: 'brag-book-gallery-local',
				callback: array( $this->settings_pages['local'], 'render' )
			);
		}

		// Consultation submenu
		$consultation = Setup::get_instance()->get_service( 'consultation' );
		if ( $consultation && method_exists( $consultation, 'display_form_entries' ) ) {
			add_submenu_page(
				parent_slug: 'brag-book-gallery-settings',
				page_title: esc_html__( 'Consultations', 'brag-book-gallery' ),
				menu_title: esc_html__( 'Consultations', 'brag-book-gallery' ),
				capability: 'manage_options',
				menu_slug: 'brag-book-gallery-consultation',
				callback: array( $consultation, 'display_form_entries' )
			);
		}

		// Help submenu
		add_submenu_page(
			parent_slug: 'brag-book-gallery-settings',
			page_title: esc_html__( 'Help & Documentation', 'brag-book-gallery' ),
			menu_title: esc_html__( 'Help', 'brag-book-gallery' ),
			capability: 'manage_options',
			menu_slug: 'brag-book-gallery-help',
			callback: array( $this->settings_pages['help'], 'render' )
		);

		// Debug submenu
		add_submenu_page(
			parent_slug: 'brag-book-gallery-settings',
			page_title: esc_html__( 'Debug & Diagnostics', 'brag-book-gallery' ),
			menu_title: esc_html__( 'Debug', 'brag-book-gallery' ),
			capability: 'manage_options',
			menu_slug: 'brag-book-gallery-debug',
			callback: array( $this->settings_pages['debug'], 'render' )
		);
	}

	/**
	 * Generate base64-encoded SVG icon for admin menu
	 *
	 * Creates a custom SVG icon representing a gallery/image viewer for use in the
	 * WordPress admin menu. The icon uses a photo/image symbol that clearly
	 * represents the gallery functionality of the plugin.
	 *
	 * The SVG is optimized for WordPress admin menu display:
	 * - Uses black fill for proper contrast in both light and dark admin themes
	 * - 512x512 viewBox provides scalability at different menu sizes
	 * - FontAwesome-inspired path for familiar visual language
	 *
	 * @since 3.0.0
	 * @return string Base64 encoded SVG icon suitable for WordPress add_menu_page()
	 */
	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="black"><path d="M0 96C0 60.7 28.7 32 64 32H448c35.3 0 64 28.7 64 64V416c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V96zM323.8 202.5c-4.5-6.6-11.9-10.5-19.8-10.5s-15.4 3.9-19.8 10.5l-87 127.6L170.7 297c-4.6-5.7-11.5-9-18.7-9s-14.2 3.3-18.7 9l-64 80c-5.8 7.2-6.9 17.1-2.9 25.4s12.4 13.6 21.6 13.6h96 32H416c8.9 0 17.1-4.9 21.2-12.8s3.6-17.4-1.4-24.7l-120-176zM112 192a48 48 0 1 0 0-96 48 48 0 1 0 0 96z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Conditionally enqueue admin assets for settings pages
	 *
	 * Loads CSS and JavaScript files only on BRAG Book Gallery settings pages to avoid
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

		// Enqueue the main admin CSS file for consistent styling across all settings pages
		wp_enqueue_style(
			'brag-book-gallery-admin',
			plugins_url( 'assets/css/admin.css', dirname( __DIR__ ) ),
			array(), // No dependencies - standalone styles
			'3.0.0'  // Version for cache busting
		);

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

	/**
	 * Retrieve a specific settings page instance by identifier
	 *
	 * Provides external access to individual settings page objects for components
	 * that need to interact with specific settings functionality. This method
	 * enables loose coupling between different parts of the plugin.
	 *
	 * Common use cases:
	 * - Accessing settings page methods from other plugin components
	 * - Rendering settings page content in custom contexts
	 * - Validating settings data from external sources
	 *
	 * Available page identifiers: 'general', 'mode', 'javascript', 'local',
	 * 'api', 'help', 'debug'
	 *
	 * @since 3.0.0
	 * @param string $page Page identifier corresponding to settings_pages array key
	 * @return Settings_Base|null Settings page instance or null if identifier not found
	 */
	public function get_settings_page( string $page ): ?Settings_Base {
		return $this->settings_pages[ $page ] ?? null;
	}

	/**
	 * Retrieve the legacy settings instance for backward compatibility
	 *
	 * Provides access to the original Settings class instance to maintain
	 * compatibility with existing code during the transition to the new modular
	 * settings system. This method should be used sparingly and only when
	 * necessary for legacy support.
	 *
	 * The legacy instance is created without menu registration to prevent
	 * conflicts with the new settings pages managed by this class.
	 *
	 * Note: New code should use the modular settings pages instead of the
	 * legacy settings instance whenever possible.
	 *
	 * @since 3.0.0
	 * @return Settings Legacy settings class instance (always available)
	 */
	public function get_legacy_settings(): Settings {
		return $this->legacy_settings;
	}
}
