<?php
/**
 * Menu Class - Centralized menu registration and management
 *
 * This class handles all admin menu registration, providing a single
 * point of control for the plugin's admin navigation structure.
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

use BRAGBookGallery\Includes\Admin\Pages\Communications_Page;
use WP_Filesystem_Base;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Menu Class
 *
 * Centralized management of WordPress admin menus for the BRAG book Gallery plugin.
 * This class provides a clean interface for registering menus with conditional
 * visibility based on plugin configuration state.
 *
 * @since 3.0.0
 */
class Menu {

	/**
	 * Menu configuration array
	 *
	 * Stores the complete menu structure including main and submenu items.
	 * Each item contains settings for title, capability, callback, etc.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private array $menu_config = array();

	/**
	 * Settings page instances
	 *
	 * References to settings page objects that handle rendering.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private array $settings_pages = array();

	/**
	 * Plugin configuration state
	 *
	 * Stores current plugin state for conditional menu visibility.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private array $plugin_state = array();

	/**
	 * Constructor
	 *
	 * Initializes the menu system with settings page instances.
	 *
	 * @since 3.0.0
	 *
	 * @param array $settings_pages Array of settings page instances.
	 */
	public function __construct( array $settings_pages ) {
		$this->settings_pages = $settings_pages;
		$this->init();
	}

	/**
	 * Initialize menu system
	 *
	 * Sets up WordPress hooks. Plugin state and menu configuration
	 * are determined later when menus are actually registered.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return void
	 */
	private function init(): void {
		add_action(
			'admin_menu',
			array( $this, 'register_menus' ),
			10
		);
	}

	/**
	 * Determine current plugin state
	 *
	 * Analyzes the current plugin configuration to determine operational state
	 * and feature availability. This method checks various settings and options
	 * to populate the plugin_state array, which is used throughout the class to
	 * control menu visibility and feature access.
	 *
	 * State checks include:
	 * - API connectivity and token configuration
	 * - Current operational mode (default, local, etc.)
	 * - Feature flags (consultations, etc.)
	 *
	 * This method should be called before building the menu configuration to
	 * ensure conditional menu items are properly registered based on current state.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return void
	 */
	private function determine_plugin_state(): void {

		/**
		 * Initialize plugin state array.
		 *
		 * Ensures clean state with default values before populating.
		 * This prevents issues with undefined array keys and provides
		 * sensible defaults if any checks fail.
		 */
		$this->plugin_state = array(
			'has_api'                => false,
			'current_mode'           => 'default',
			'consultations_enabled'  => false,
		);

		/**
		 * =========================================================================
		 * API CONFIGURATION CHECK
		 * =========================================================================
		 *
		 * Determines if API integration is configured and available.
		 * The presence of API tokens indicates that the plugin can communicate
		 * with external services for data synchronization and enhanced features.
		 */

		/**
		 * Retrieve stored API tokens from WordPress options.
		 *
		 * The API token option stores authentication credentials for external
		 * API integration. This is stored as an array to support multiple
		 * tokens or token types (e.g., access token, refresh token).
		 *
		 * @var array $api_tokens Array of API authentication tokens.
		 *                        Empty array if no tokens are configured.
		 */
		$api_tokens = get_option(
			'brag_book_gallery_api_token',
			array() // Default to empty array if option doesn't exist.
		);

		/**
		 * Validate API tokens structure.
		 *
		 * Ensures the retrieved value is actually an array before checking
		 * if it's empty. This prevents issues if the option was corrupted
		 * or manually edited to a non-array value.
		 */
		if ( ! is_array( $api_tokens ) ) {
			$api_tokens = array();
		}

		/**
		 * Set API availability flag.
		 *
		 * API is considered available if the tokens array is not empty.
		 * This flag is used throughout the plugin to conditionally enable
		 * features that require API connectivity (e.g., sync, remote mode).
		 *
		 * @var bool True if API tokens are configured, false otherwise.
		 */
		$this->plugin_state['has_api'] = ! empty( $api_tokens );

		/**
		 * =========================================================================
		 * OPERATIONAL MODE DETERMINATION
		 * =========================================================================
		 *
		 * Determines the current operational mode of the plugin.
		 * Mode affects which features are available and how data is managed.
		 *
		 * Available modes:
		 * - 'default': Standard local WordPress operation
		 * - 'local': Local mode with API integration (future feature)
		 * - 'remote': Remote data management (future feature)
		 */

		/**
		 * Set default operational mode.
		 *
		 * Mode manager functionality has been removed from current version.
		 * Plugin operates in 'default' mode which provides core functionality
		 * without advanced mode-specific features.
		 *
		 * Future versions may reintroduce mode selection via the mode manager,
		 * at which point this should be replaced with:
		 * $this->plugin_state['current_mode'] = $this->mode_manager->get_current_mode();
		 *
		 * @var string Current operational mode identifier.
		 */
		$this->plugin_state['current_mode'] = 'default';

		/**
		 * =========================================================================
		 * FEATURE FLAGS
		 * =========================================================================
		 *
		 * Checks optional feature settings to determine which functionality
		 * should be available to users. Feature flags control access to
		 * supplementary features that can be enabled or disabled independently.
		 */

		/**
		 * Check consultation form feature status.
		 *
		 * The consultation form allows website visitors to request consultations
		 * directly through the plugin's interface. This is an optional feature
		 * that can be enabled or disabled based on practice preferences.
		 *
		 * @var bool True if consultation forms are enabled, false otherwise.
		 */
		$consultation_enabled = get_option(
			'brag_book_gallery_consultation_enabled',
			false // Default to disabled for new installations.
		);

		/**
		 * Validate and normalize consultation enabled value.
		 *
		 * Ensures the value is a proper boolean. get_option() may return
		 * various truthy/falsy values (1, "1", true, etc.) depending on
		 * how it was stored. This normalizes to a strict boolean.
		 *
		 * Uses WordPress rest_sanitize_boolean() for consistent handling
		 * of various input formats ('yes', '1', 'true', etc.).
		 */
		$this->plugin_state['consultations_enabled'] = rest_sanitize_boolean( $consultation_enabled );

		/**
		 * Allow filtering of plugin state.
		 *
		 * Provides a hook for extensions or custom code to modify the
		 * determined plugin state before it's used for menu configuration.
		 * This is useful for adding custom conditions or overriding
		 * state determination logic.
		 *
		 * @since 3.0.0
		 *
		 * @param array $plugin_state Current plugin state array.
		 */
		$this->plugin_state = apply_filters(
			'brag_book_gallery_plugin_state',
			$this->plugin_state
		);

		/**
		 * Validate final state structure.
		 *
		 * Ensures all required state keys exist even after filtering.
		 * This prevents undefined index errors if a filter inadvertently
		 * removes required keys.
		 */
		$this->plugin_state = wp_parse_args(
			$this->plugin_state,
			array(
				'has_api'               => false,
				'current_mode'          => 'default',
				'consultations_enabled' => false,
			)
		);
	}

	/**
	 * Build menu configuration
	 *
	 * Creates the complete menu structure with conditional visibility rules.
	 * This method constructs a multi-level array defining the main menu page
	 * and all submenu items, including both visible menu items and hidden pages
	 * that are accessible via direct URL only.
	 *
	 * IMPORTANT: This method should only be called after the 'init' action
	 * to ensure WordPress translation functions are available. Calling this
	 * too early will result in untranslated menu labels.
	 *
	 * Menu structure includes:
	 * - Main menu: Top-level admin menu page
	 * - Visible submenus: Shown in the admin menu (condition = true)
	 * - Hidden pages: Accessible via URL but not shown in menu (condition = false)
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return void
	 */
	private function build_menu_config(): void {

		/**
		 * Initialize menu configuration array.
		 *
		 * Ensures clean state before building configuration to prevent
		 * issues with repeated calls or stale data.
		 */
		$this->menu_config = array(
			'main'     => array(),
			'submenus' => array(),
		);

		/**
		 * =========================================================================
		 * MAIN MENU CONFIGURATION
		 * =========================================================================
		 *
		 * Defines the top-level menu item in the WordPress admin sidebar.
		 * This serves as the parent for all submenu items and is always visible
		 * to users with 'manage_options' capability.
		 */
		$this->menu_config['main'] = array(
			/**
			 * Page title - displayed in browser title bar and admin page heading.
			 *
			 * @var string Translated page title.
			 */
			'page_title' => __(
				'BRAG book Gallery Dashboard',
				'brag-book-gallery'
			),

			/**
			 * Menu title - displayed in the WordPress admin sidebar.
			 *
			 * @var string Translated menu label (shorter than page title).
			 */
			'menu_title' => __(
				'BRAG book',
				'brag-book-gallery'
			),

			/**
			 * Required capability to access this menu.
			 *
			 * @var string WordPress capability (manage_options = administrators).
			 */
			'capability' => 'manage_options',

			/**
			 * Menu slug - unique identifier for this menu page.
			 *
			 * @var string URL-safe identifier used in admin URLs.
			 */
			'menu_slug'  => 'brag-book-gallery-settings',

			/**
			 * Callback function to render the page content.
			 *
			 * @var callable Array containing object instance and method name.
			 */
			'callback'   => array(
				$this->settings_pages['dashboard'],
				'render'
			),

			/**
			 * Menu icon - SVG data URI or dashicon class name.
			 *
			 * @var string Base64-encoded SVG or dashicon identifier.
			 */
			'icon'       => $this->get_menu_icon(),

			/**
			 * Menu position in admin sidebar.
			 *
			 * @var int Position value (30 places it after Comments menu).
			 * @link https://developer.wordpress.org/reference/functions/add_menu_page/#menu-structure
			 */
			'position'   => 30,
		);

		/**
		 * =========================================================================
		 * SUBMENU ITEMS CONFIGURATION
		 * =========================================================================
		 *
		 * Defines all submenu pages with their visibility conditions.
		 * Each submenu array contains configuration for registration and
		 * a 'condition' key that determines visibility in the admin menu.
		 */
		$this->menu_config['submenus'] = array();

		/**
		 * Dashboard Submenu
		 *
		 * Renames the first submenu item (WordPress default behavior makes the
		 * first submenu duplicate the parent menu title). This entry provides
		 * a more descriptive "Dashboard" label while maintaining the same slug
		 * as the main menu page.
		 *
		 * Visibility: Always shown (condition = true)
		 */
		$this->menu_config['submenus']['dashboard'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Dashboard',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Dashboard',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-settings',
			'callback'    => array(
				$this->settings_pages['dashboard'],
				'render'
			),
			'condition'   => true, // Always visible in menu.
		);

		/**
		 * General Settings Submenu
		 *
		 * Contains general plugin configuration options. Hidden from the menu
		 * but accessible via direct URL or tabbed navigation from the dashboard.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 * Access: Direct URL or internal navigation
		 */
		$this->menu_config['submenus']['general'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'General Settings',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'General',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-general',
			'callback'    => array(
				$this->settings_pages['general'],
				'render'
			),
			'condition'   => false, // Hidden from menu, accessible via tabs.
		);

		/**
		 * API Settings Submenu
		 *
		 * Manages API connection settings and credentials. Hidden from menu
		 * to reduce clutter but accessible through tabbed interface.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 * Access: Direct URL or tabbed navigation
		 */
		$this->menu_config['submenus']['api'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'API Settings',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'API',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-api-settings',
			'callback'    => array(
				$this->settings_pages['api'],
				'render'
			),
			'condition'   => false, // Hidden from menu, accessible via tabs.
		);

		/**
		 * Cases Submenu
		 *
		 * Links to the custom post type edit screen for 'brag_book_cases'.
		 * Uses WordPress built-in post type listing page instead of custom callback.
		 *
		 * Note: Empty callback because WordPress handles the rendering automatically
		 * when menu_slug points to a post type edit page.
		 *
		 * Visibility: Always shown (condition = true)
		 * Capability: edit_posts (allows editors and administrators)
		 */
		$this->menu_config['submenus']['cases'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Cases Management',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Cases',
				'brag-book-gallery'
			),
			'capability'  => 'edit_posts',
			'menu_slug'   => 'edit.php?post_type=brag_book_cases',
			'callback'    => '', // Empty callback - WordPress core handles rendering.
			'condition'   => true, // Always visible in menu.
		);

		/**
		 * Procedures Submenu
		 *
		 * Links to the taxonomy edit screen for 'brag_book_procedures'.
		 * Provides interface for managing procedure terms/categories.
		 *
		 * Note: Empty callback because WordPress handles taxonomy screens automatically
		 * when menu_slug points to a taxonomy edit page.
		 *
		 * Visibility: Always shown (condition = true)
		 * Capability: manage_categories (allows administrators)
		 */
		$this->menu_config['submenus']['procedures'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Procedures Management',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Procedures',
				'brag-book-gallery'
			),
			'capability'  => 'manage_categories',
			'menu_slug'   => 'edit-tags.php?taxonomy=brag_book_procedures&post_type=brag_book_cases',
			'callback'    => '', // Empty callback - WordPress core handles rendering.
			'condition'   => true, // Always visible in menu.
		);

		/**
		 * Doctors Submenu
		 *
		 * Links to the taxonomy edit screen for 'brag_book_doctors'.
		 * Provides interface for managing doctor/provider terms.
		 *
		 * Note: Empty callback because WordPress handles taxonomy screens automatically
		 * when menu_slug points to a taxonomy edit page.
		 *
		 * Visibility: Only shown when website property ID 111 is configured
		 * Capability: manage_categories (allows administrators)
		 *
		 * @since 3.3.3
		 */
		$this->menu_config['submenus']['doctors'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Doctors Management',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Doctors',
				'brag-book-gallery'
			),
			'capability'  => 'manage_categories',
			'menu_slug'   => 'edit-tags.php?taxonomy=brag_book_doctors&post_type=brag_book_cases',
			'callback'    => '', // Empty callback - WordPress core handles rendering.
			'condition'   => $this->is_doctors_taxonomy_enabled(), // Only visible when property ID 111 is configured.
		);

		/**
		 * Sync Settings Submenu
		 *
		 * Manages procedure synchronization settings and operations.
		 * Hidden from menu but accessible via tabbed interface on settings pages.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 * Access: Tabbed navigation from other settings pages
		 */
		$this->menu_config['submenus']['sync'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Sync Settings',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Sync',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-sync',
			'callback'    => array(
				$this->settings_pages['sync'],
				'render'
			),
			'condition'   => false, // Hidden from menu, accessible via tabs.
		);

		/**
		 * =========================================================================
		 * MODE-SPECIFIC SETTINGS
		 * =========================================================================
		 *
		 * Conditional menu items that only appear when specific plugin modes
		 * are active and API functionality is available.
		 */

		/**
		 * Check if API functionality is available.
		 *
		 * Mode-specific settings require API integration to be configured.
		 * This check prevents showing non-functional menu items when API
		 * is not set up.
		 */
		if ( $this->plugin_state['has_api'] ) {

			/**
			 * Local Settings Submenu
			 *
			 * Configuration options specific to local mode operation.
			 * Only registered when plugin is operating in local mode with
			 * API connectivity available.
			 *
			 * Status: Currently disabled/coming soon feature
			 * Visibility: Shown only in local mode (condition = true when in local mode)
			 */
			if ( $this->plugin_state['current_mode'] === 'local' ) {
				$this->menu_config['submenus']['local'] = array(
					'parent_slug' => 'brag-book-gallery-settings',
					'page_title'  => __(
						'Local Settings',
						'brag-book-gallery'
					),
					'menu_title'  => __(
						'Local',
						'brag-book-gallery'
					),
					'capability'  => 'manage_options',
					'menu_slug'   => 'brag-book-gallery-local',
					'callback'    => array(
						$this->settings_pages['local'],
						'render'
					),
					'condition'   => true, // Visible when in local mode.
				);
			}
		}

		/**
		 * =========================================================================
		 * UTILITY AND DOCUMENTATION PAGES
		 * =========================================================================
		 *
		 * Pages that provide supplementary functionality, help documentation,
		 * and administrative tools. All hidden from menu to reduce clutter.
		 */

		/**
		 * Communications Submenu
		 *
		 * Manages communication settings and consultation request handling.
		 * Hidden from menu but accessible via direct URL or internal navigation.
		 *
		 * Note: Uses different callback structure (render_admin_page method)
		 * from dedicated communications settings instance.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 */
		$this->menu_config['submenus']['consultations'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Communications Management',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Communications',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-communications',
			'callback'    => array(
				$this->get_communications_settings(),
				'render_admin_page'
			),
			'condition'   => false, // Hidden from menu.
		);

		/**
		 * Help & Documentation Submenu
		 *
		 * Provides user guides, documentation, and plugin usage instructions.
		 * Hidden from menu but accessible via help links throughout the plugin.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 * Access: Help links in other admin pages
		 */
		$this->menu_config['submenus']['help'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Help & Documentation',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Help',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-help',
			'callback'    => array(
				$this->settings_pages['help'],
				'render'
			),
			'condition'   => false, // Hidden from menu.
		);

		/**
		 * Changelog Submenu
		 *
		 * Displays plugin version history, recent changes, and update notes.
		 * Hidden from menu but accessible via plugin update notifications.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 * Access: Update notification links
		 */
		$this->menu_config['submenus']['changelog'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Changelog & Version History',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Changelog',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-changelog',
			'callback'    => array(
				$this->settings_pages['changelog'],
				'render'
			),
			'condition'   => false, // Hidden from menu.
		);

		/**
		 * Debug & Diagnostics Submenu
		 *
		 * Provides debugging tools, system diagnostics, and troubleshooting
		 * information for administrators. Hidden from menu for security and
		 * to avoid confusion for non-technical users.
		 *
		 * Visibility: Hidden from menu (condition = false)
		 * Access: Direct URL for administrators needing diagnostic information
		 */
		$this->menu_config['submenus']['debug'] = array(
			'parent_slug' => 'brag-book-gallery-settings',
			'page_title'  => __(
				'Debug & Diagnostics',
				'brag-book-gallery'
			),
			'menu_title'  => __(
				'Debug',
				'brag-book-gallery'
			),
			'capability'  => 'manage_options',
			'menu_slug'   => 'brag-book-gallery-debug',
			'callback'    => array(
				$this->settings_pages['debug'],
				'render'
			),
			'condition'   => false, // Hidden from menu.
		);
	}

	/**
	 * Register WordPress admin menus
	 *
	 * Registers the main menu and all submenus based on configuration
	 * and conditional visibility rules. Builds the configuration on demand
	 * to ensure translations are loaded before menu registration.
	 *
	 * This method handles three types of menu items:
	 * 1. Main menu page - The top-level plugin menu
	 * 2. Visible submenus - Child pages shown in the menu
	 * 3. Hidden pages - Accessible via direct URL but not displayed in menu
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_menus(): void {
		/**
		 * Initialize menu configuration if not already built.
		 *
		 * Configuration is built lazily (on-demand) rather than during class
		 * construction to ensure WordPress translation functions are available.
		 * This prevents issues with i18n strings being registered too early.
		 */
		if ( empty( $this->menu_config ) ) {
			$this->determine_plugin_state();
			$this->build_menu_config();
		}

		/**
		 * Validate that menu configuration was successfully built.
		 *
		 * If configuration is still empty after build attempts, exit early
		 * to prevent warnings from accessing undefined array keys.
		 */
		if ( empty( $this->menu_config ) || ! is_array( $this->menu_config ) ) {
			return;
		}

		/**
		 * Register the main menu page.
		 *
		 * Creates the top-level menu item in the WordPress admin sidebar.
		 * This serves as the parent for all submenu items.
		 */
		if ( isset( $this->menu_config['main'] ) && is_array( $this->menu_config['main'] ) ) {
			/**
			 * Main menu configuration array.
			 *
			 * @var array{
			 *     page_title: string,
			 *     menu_title: string,
			 *     capability: string,
			 *     menu_slug: string,
			 *     callback: callable,
			 *     icon: string,
			 *     position: int
			 * } $main Main menu configuration parameters.
			 */
			$main = $this->menu_config['main'];

			/**
			 * Add the top-level menu page.
			 *
			 * Uses add_menu_page() to register the main plugin menu in the
			 * WordPress admin sidebar. The icon is retrieved via get_menu_icon()
			 * which provides either a base64-encoded SVG or dashicon fallback.
			 *
			 * @link https://developer.wordpress.org/reference/functions/add_menu_page/
			 */
			add_menu_page(
				$main['page_title'],
				$main['menu_title'],
				$main['capability'],
				$main['menu_slug'],
				$main['callback'],
				$main['icon'],
				$main['position']
			);
		}

		/**
		 * Validate submenus array exists.
		 *
		 * Ensures the submenus key exists and is an array before attempting
		 * to iterate. This prevents errors if configuration is malformed.
		 */
		if ( ! isset( $this->menu_config['submenus'] ) || ! is_array( $this->menu_config['submenus'] ) ) {
			return;
		}

		/**
		 * Register visible submenu pages.
		 *
		 * Iterates through all configured submenus and registers those that
		 * meet their visibility conditions. These pages will appear as child
		 * items under the main menu in the WordPress admin sidebar.
		 *
		 * Visibility is determined by the 'condition' key, which should evaluate
		 * to a boolean indicating whether the submenu should be shown.
		 */
		foreach ( $this->menu_config['submenus'] as $submenu ) {
			/**
			 * Validate submenu configuration structure.
			 *
			 * Ensures the submenu is an array and contains required keys
			 * before attempting to register it.
			 */
			if ( ! is_array( $submenu ) ) {
				continue;
			}

			/**
			 * Check if submenu should be visible.
			 *
			 * The 'condition' key holds a boolean value indicating visibility.
			 * Non-empty condition means the submenu should be shown in the menu.
			 */
			if ( ! empty( $submenu['condition'] ) ) {
				/**
				 * Register visible submenu page.
				 *
				 * Creates a submenu item that will be displayed under the parent
				 * menu in the WordPress admin sidebar.
				 *
				 * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
				 */
				add_submenu_page(
					$submenu['parent_slug'],
					$submenu['page_title'],
					$submenu['menu_title'],
					$submenu['capability'],
					$submenu['menu_slug'],
					$submenu['callback']
				);
			}
		}

		/**
		 * Register hidden pages.
		 *
		 * These pages are accessible via direct URL but do not appear in the
		 * admin menu. This is useful for pages that should only be accessed
		 * through specific workflows or links (e.g., "Add New" pages when
		 * accessed from a list table).
		 *
		 * Hidden pages are created by passing an empty string as the parent_slug
		 * to add_submenu_page(). This is the recommended WordPress approach for
		 * hidden admin pages and is fully compatible with PHP 8.2+.
		 */
		foreach ( $this->menu_config['submenus'] as $submenu ) {
			/**
			 * Validate submenu configuration structure.
			 */
			if ( ! is_array( $submenu ) ) {
				continue;
			}

			/**
			 * Check if submenu should be hidden.
			 *
			 * A submenu is hidden when:
			 * 1. Its condition is empty (false or falsy value)
			 * 2. It has a valid callback function defined
			 *
			 * The callback check ensures we only register functional pages,
			 * not configuration placeholders.
			 */
			if ( empty( $submenu['condition'] ) && ! empty( $submenu['callback'] ) ) {
				/**
				 * Register hidden page.
				 *
				 * By passing an empty string as the parent_slug, WordPress
				 * registers the page without adding it to any menu. The page
				 * remains accessible via its direct admin URL.
				 *
				 * This technique is compatible with PHP 8.2+ and follows
				 * WordPress VIP standards.
				 *
				 * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
				 */
				add_submenu_page(
					'', // Empty parent_slug creates a hidden page (WordPress standard method).
					$submenu['page_title'],
					$submenu['menu_title'],
					$submenu['capability'],
					$submenu['menu_slug'],
					$submenu['callback']
				);
			}
		}
	}

	/**
	 * Get menu icon
	 *
	 * Returns the base64-encoded SVG icon for the main menu. Uses WP_Filesystem
	 * for VIP compatibility and implements static caching to prevent redundant
	 * file system operations on subsequent calls.
	 *
	 * The method attempts to load the SVG icon from the plugin's assets directory.
	 * If the file cannot be loaded or is empty, it falls back to a WordPress
	 * dashicon identifier.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return string Base64-encoded SVG data URI or dashicon class name.
	 *                Format: 'data:image/svg+xml;base64,{encoded_content}' or 'dashicons-format-gallery'
	 */
	private function get_menu_icon(): string {

		/**
		 * Static cache for the menu icon.
		 *
		 * Stores the resolved icon (either base64 SVG or dashicon fallback) to avoid
		 * repeated filesystem operations and WP_Filesystem initialization on each call.
		 *
		 * @var string|null
		 */
		static $cached_icon = null;

		// Return cached value if already resolved.
		if ( null !== $cached_icon ) {
			return $cached_icon;
		}

		/**
		 * Initialize WordPress Filesystem API.
		 *
		 * Required for VIP compatibility as direct file access (file_get_contents)
		 * is not allowed on VIP environments.
		 *
		 * @var WP_Filesystem_Base|null $wp_filesystem WordPress filesystem handler.
		 */
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		/**
		 * Construct the absolute path to the SVG icon file.
		 *
		 * Uses dirname() with levels to traverse up from the current file location
		 * to the plugin root, then appends the relative path to the asset.
		 *
		 * @var string $svg_path Absolute filesystem path to the SVG icon.
		 */
		$svg_path = dirname( __DIR__, 3 ) . '/assets/images/brag-book-emblem.svg';

		/**
		 * Attempt to load the SVG file using WP_Filesystem.
		 *
		 * Validates that:
		 * 1. WP_Filesystem is properly initialized
		 * 2. The file exists on the filesystem
		 * 3. The file contents can be successfully retrieved
		 * 4. The retrieved content is not empty
		 */
		if ( $wp_filesystem && $wp_filesystem->exists( $svg_path ) ) {
			/**
			 * Read the SVG file contents.
			 *
			 * @var string|false $svg_content Raw SVG content or false on failure.
			 */
			$svg_content = $wp_filesystem->get_contents( $svg_path );

			if ( false !== $svg_content && ! empty( $svg_content ) ) {
				/**
				 * Sanitize and minify SVG content.
				 *
				 * Removes unnecessary whitespace to reduce the encoded size.
				 * This is safe for SVG content and improves performance.
				 */
				$svg_content = preg_replace( '/\s+/', ' ', trim( $svg_content ) );

				/**
				 * Encode the SVG as a base64 data URI.
				 *
				 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				 * Base64 encoding is intentional here for creating a data URI,
				 * not for obfuscation purposes.
				 */
				$cached_icon = 'data:image/svg+xml;base64,' . base64_encode( $svg_content );
				// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

				return $cached_icon;
			}
		}

		/**
		 * Fallback to WordPress dashicon.
		 *
		 * If the SVG file cannot be loaded for any reason (missing file, permissions,
		 * empty content), use a standard WordPress dashicon as the menu icon.
		 *
		 * @link https://developer.wordpress.org/resource/dashicons/
		 */
		$cached_icon = 'dashicons-format-gallery';

		return $cached_icon;
	}

	/**
	 * Get consultation settings instance
	 *
	 * Returns the consultation settings page handler for menu callback.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return object Consultation settings instance
	 */
	private function get_communications_settings(): object {
		// Check if we have a communications settings page in the settings_pages array
		if ( isset( $this->settings_pages['communications'] ) ) {
			return $this->settings_pages['communications'];
		}

		// Fallback to creating new instance.
		return new Communications_Page();
	}

	/**
	 * Check if doctors taxonomy is enabled
	 *
	 * Doctors taxonomy is only enabled when website property ID 111 is configured.
	 * This mirrors the logic in the Taxonomies class.
	 *
	 * @since 3.3.3
	 * @access private
	 *
	 * @return bool True if doctors taxonomy should be enabled.
	 */
	private function is_doctors_taxonomy_enabled(): bool {
		$website_property_ids = get_option( 'brag_book_gallery_website_property_id', [] );

		if ( ! is_array( $website_property_ids ) ) {
			$website_property_ids = [ $website_property_ids ];
		}

		return in_array( 111, array_map( 'intval', $website_property_ids ), true );
	}
}
