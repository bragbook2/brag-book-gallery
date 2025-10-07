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
	 * Checks API configuration and mode settings to control menu visibility.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return void
	 */
	private function determine_plugin_state(): void {
		// Check API configuration.
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$this->plugin_state['has_api'] = ! empty( $api_tokens );

		// Mode manager removed - default to 'default' mode
		$this->plugin_state['current_mode'] = 'default';

		// Check if consultation form is enabled.
		$this->plugin_state['consultations_enabled'] = get_option(
			'brag_book_gallery_consultation_enabled',
			false
		);
	}

	/**
	 * Build menu configuration
	 *
	 * Creates the complete menu structure with conditional visibility rules.
	 * This method should only be called after the 'init' action to ensure
	 * translations are available.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return void
	 */
	private function build_menu_config(): void {

		// Main menu configuration.
		$this->menu_config['main'] = array(
			'page_title' => __(
				'BRAG book Gallery Dashboard',
				'brag-book-gallery'
			),
			'menu_title' => __(
				'BRAG book',
				'brag-book-gallery'
			),
			'capability' => 'manage_options',
			'menu_slug'  => 'brag-book-gallery-settings',
			'callback'   => array(
				$this->settings_pages['dashboard'],
				'render'
			),
			'icon'       => $this->get_menu_icon(),
			'position'   => 30,
		);

		// Submenu items configuration.
		$this->menu_config['submenus'] = array();

		// Dashboard (rename the first submenu item).
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
			'condition'   => true,
		);

		// General Settings (separate page) - hidden from menu but accessible.
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
			'condition'   => false, // Hidden from menu
		);

		// API Settings - hidden from menu but accessible.
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
			'condition'   => false, // Hidden from menu
		);

		// Cases (custom post type management).
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
			'callback'    => '',
			'condition'   => true,
		);

		// Procedures (taxonomy management).
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
			'callback'    => '',
			'condition'   => true,
		);

		// Sync (procedure synchronization) - hidden from menu but accessible via tabs.
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
			'condition'   => false, // Hidden from menu
		);

		// Mode-specific Settings (requires API) - Local mode only when available
		if ( $this->plugin_state['has_api'] ) {

			// Local Settings (Local mode only) - currently disabled/coming soon
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
					'condition'   => true,
				);
			}

		}

		// Communications - hidden from menu but accessible.
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
			'condition'   => false, // Hidden from menu
		);

		// Help - hidden from menu but accessible.
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
			'condition'   => false, // Hidden from menu
		);

		// Changelog - hidden from menu but accessible.
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
			'condition'   => false, // Hidden from menu
		);


		// Debug - hidden from menu but accessible.
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
			'condition'   => false, // Hidden from menu
		);
	}

	/**
	 * Register WordPress admin menus
	 *
	 * Registers the main menu and all submenus based on configuration
	 * and conditional visibility rules. Builds the configuration on demand
	 * to ensure translations are loaded.
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_menus(): void {
		// Build configuration when needed (translations are now loaded).
		if ( empty( $this->menu_config ) ) {
			$this->determine_plugin_state();
			$this->build_menu_config();
		}

		// Register main menu.
		if ( isset( $this->menu_config['main'] ) ) {
			$main = $this->menu_config['main'];

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

		// Register visible submenus.
		foreach ( $this->menu_config['submenus'] as $submenu ) {
			if ( ! empty( $submenu['condition'] ) ) {
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

		// Register hidden pages (accessible via direct URL but not shown in menu).
		foreach ( $this->menu_config['submenus'] as $submenu ) {
			if ( empty( $submenu['condition'] ) && ! empty( $submenu['callback'] ) ) {
				add_submenu_page(
					'', // empty string parent_slug makes it hidden from menu (PHP 8.2+ compatible)
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
	 * Returns the base64-encoded SVG icon for the main menu.
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @return string Base64-encoded SVG icon or dashicon fallback
	 */
	private function get_menu_icon(): string {
		// Use WordPress filesystem for VIP compatibility.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$svg_path = dirname( __DIR__, 3 ) . '/assets/images/brag-book-emblem.svg';

		if ( $wp_filesystem && $wp_filesystem->exists( $svg_path ) ) {
			$svg_content = $wp_filesystem->get_contents( $svg_path );
			if ( ! empty( $svg_content ) ) {
				return 'data:image/svg+xml;base64,' . base64_encode( $svg_content );
			}
		}

		// Fallback to dashicon.
		return 'dashicons-format-gallery';
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
	private function get_communications_settings() {
		// Check if we have a communications settings page in the settings_pages array
		if ( isset( $this->settings_pages['communications'] ) ) {
			return $this->settings_pages['communications'];
		}

		// Fallback to creating new instance.
		return new Communications_Page();
	}

	/**
	 * Get menu configuration
	 *
	 * Returns the complete menu configuration array.
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @return array Menu configuration
	 */
	public function get_menu_config(): array {
		return $this->menu_config;
	}

	/**
	 * Check if menu item should be visible
	 *
	 * Determines if a specific menu item should be shown based on
	 * current plugin state and configuration.
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @param string $menu_key The menu item key to check.
	 *
	 * @return bool True if menu should be visible, false otherwise
	 */
	public function is_menu_visible( string $menu_key ): bool {
		if ( ! isset( $this->menu_config['submenus'][ $menu_key ] ) ) {
			return false;
		}

		return ! empty( $this->menu_config['submenus'][ $menu_key ]['condition'] );
	}
}
