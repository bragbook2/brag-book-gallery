<?php
/**
 * Base Settings Class - Abstract base for all settings pages
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

if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Base Settings Class
 *
 * Abstract base class that provides common functionality and structure for all
 * settings pages in the BRAG book Gallery plugin. This class implements the
 * Template Method pattern to ensure consistent behavior across all settings pages
 * while allowing individual pages to customize their specific functionality.
 *
 * Key features provided:
 * - Consistent page structure with header, navigation, and footer
 * - Common form handling and validation patterns
 * - Shared navigation tabs with intelligent visibility
 * - WordPress settings API integration
 * - Security and capability checking
 * - Admin notice management
 *
 * All settings pages must extend this class and implement the abstract methods
 * to define their specific behavior while inheriting the common structure.
 *
 * @since 3.0.0
 */
abstract class Settings_Base {

	/**
	 * Tabs manager instance
	 *
	 * Handles navigation tab generation and rendering for settings pages.
	 *
	 * @since 3.0.0
	 * @var Tabs|null
	 */
	protected ?Tabs $tabs_manager = null;

	/**
	 * WordPress admin page slug identifier
	 *
	 * Unique identifier used by WordPress for:
	 * - Menu registration (add_menu_page, add_submenu_page)
	 * - URL routing (admin.php?page={slug})
	 * - Settings group identification
	 * - Asset targeting and hook detection
	 *
	 * Must be unique within the WordPress admin to prevent conflicts.
	 *
	 * @since 3.0.0
	 * @var string WordPress page slug (e.g., 'brag-book-gallery-settings')
	 */
	protected string $page_slug = '';

	/**
	 * Localized page title for admin display
	 *
	 * The full page title displayed in the browser title bar and page header.
	 * This should be a human-readable, translated string that clearly identifies
	 * the purpose of the settings page to administrators.
	 *
	 * Set during render() method to ensure translations are available.
	 *
	 * @since 3.0.0
	 * @var string Translated page title (e.g., 'BRAG book Gallery Settings')
	 */
	protected string $page_title = '';

	/**
	 * Localized menu title for navigation display
	 *
	 * Shorter version of the page title used in admin menu and navigation tabs.
	 * Should be concise while remaining descriptive to fit limited menu space.
	 *
	 * Set during render() method to ensure translations are available.
	 *
	 * @since 3.0.0
	 * @var string Translated menu title (e.g., 'Settings', 'API', 'Help')
	 */
	protected string $menu_title = '';

	/**
	 * Constructor - Initialize settings page
	 *
	 * Automatically calls the init() method to allow child classes to perform
	 * their specific initialization without needing to override __construct().
	 * This follows the Template Method pattern where the base class controls
	 * the initialization flow.
	 *
	 * Also initializes the tabs manager for consistent navigation across pages.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->init();
		$this->tabs_manager = new Tabs();
	}

	/**
	 * Initialize the settings page - ABSTRACT METHOD
	 *
	 * Child classes must implement this method to perform their specific
	 * initialization tasks such as:
	 * - Setting page_slug property
	 * - Registering WordPress hooks (if needed)
	 * - Initializing page-specific properties
	 * - Setting up validation rules
	 *
	 * Note: Localized strings (page_title, menu_title) should be set in render()
	 * method to ensure translation functions are available.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	abstract protected function init(): void;

	/**
	 * Render the complete settings page - ABSTRACT METHOD
	 *
	 * Child classes must implement this method to define their page content.
	 * The method should:
	 * - Set localized page_title and menu_title properties
	 * - Handle form submissions (if applicable)
	 * - Call render_header() to output common page structure
	 * - Output page-specific content and forms
	 * - Call render_footer() to close page structure
	 *
	 * Example implementation structure:
	 * ```php
	 * $this->page_title = __('My Settings', 'textdomain');
	 * if (isset($_POST['submit'])) {
	 *     $this->save_settings();
	 * }
	 * $this->render_header();
	 * // Page content here
	 * $this->render_footer();
	 * ```
	 *
	 * @since 3.0.0
	 * @return void
	 */
	abstract public function render(): void;

	/**
	 * Get configuration data for all navigation tabs
	 *
	 * Provides the complete navigation structure used throughout the settings interface.
	 * Each tab includes localized labels and admin URLs. This method serves as the
	 * central source of truth for navigation configuration.
	 *
	 * Tab structure includes:
	 * - Consistent URL patterns following WordPress admin conventions
	 * - Localized labels for internationalization support
	 * - Unique slug identifiers for routing and state management
	 *
	 * Used by render_navigation() to generate the actual navigation HTML.
	 *
	 * @since 3.0.0
	 * @return array Array of tab configuration with keys: label, url, slug
	 */
	protected function get_navigation_tabs(): array {
		return array(
			'general' => array(
				'label' => __( 'General', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'slug'  => 'brag-book-gallery-settings',
			),
			'mode' => array(
				'label' => __( 'Mode', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-mode' ),
				'slug'  => 'brag-book-gallery-mode',
			),
			'javascript' => array(
				'label' => __( 'JavaScript Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-javascript' ),
				'slug'  => 'brag-book-gallery-javascript',
			),
			'local' => array(
				'label' => __( 'Local Settings', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-local' ),
				'slug'  => 'brag-book-gallery-local',
			),
			'api' => array(
				'label' => __( 'API', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'slug'  => 'brag-book-gallery-api-settings',
			),
			'help' => array(
				'label' => __( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'slug'  => 'brag-book-gallery-help',
			),
			'debug' => array(
				'label' => __( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'slug'  => 'brag-book-gallery-debug',
			),
		);
	}

	/**
	 * Render the complete navigation tab system with intelligent visibility
	 *
	 * Generates the HTML navigation interface that appears on all settings pages.
	 * Implements sophisticated logic to show/hide tabs based on plugin configuration:
	 *
	 * - API status determines which tabs are accessible
	 * - Current mode (JavaScript/Local) controls mode-specific tab visibility
	 * - Active tab highlighting provides clear visual feedback
	 * - Responsive design adapts to different screen sizes
	 * - Badge notifications indicate configuration status
	 *
	 * The navigation uses WordPress admin styling conventions and integrates
	 * seamlessly with the existing admin interface.
	 *
	 * Visual features:
	 * - Dashicons for consistent iconography
	 * - Hover states for better UX
	 * - Active state highlighting
	 * - Warning badges for unconfigured settings
	 * - Responsive layout for mobile devices
	 *
	 * @since 3.0.0
	 * @param string $current_page Current page slug for active tab highlighting
	 * @return void Outputs HTML navigation directly
	 */
	protected function render_navigation( string $current_page = '' ): void {
		if ( empty( $current_page ) ) {
			$current_page = $this->page_slug;
		}

		// Map page slugs to tab keys
		$page_to_tab = array(
			'brag-book-gallery-settings'      => 'dashboard',
			'brag-book-gallery-general'        => 'general',
			'brag-book-gallery-mode'           => 'mode',
			'brag-book-gallery-javascript'     => 'javascript',
			'brag-book-gallery-local'          => 'local',
			'brag-book-gallery-api-settings'   => 'api',
			'brag-book-gallery-api-test'       => 'api_test',
			'brag-book-gallery-consultation'   => 'consultation',
			'brag-book-gallery-help'           => 'help',
			'brag-book-gallery-debug'          => 'debug',
		);

		$current_tab = isset( $page_to_tab[ $current_page ] ) ? $page_to_tab[ $current_page ] : 'general';

		// Use the Tabs manager to render the main navigation
		if ( $this->tabs_manager ) {
			$this->tabs_manager->render_tabs( 'settings', $current_tab );
		}
	}

	/**
	 * Render settings header
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function render_header(): void {
		// Get plugin version
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.0.0';
		?>
		<div class="wrap brag-book-gallery-admin-wrap">
			<div class="brag-book-gallery-admin-container">
				<header class="brag-book-gallery-header">
					<div class="brag-book-gallery-header__brand">
						<svg class="brag-book-gallery-logo" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 2337 560.6">
							<g>
								<rect fill="#161a1b" width="560.6" height="560.6"/>
								<path fill="#FFF" d="M107.4,169.9h92.2c23.7,0,41.6,6.1,53.8,18.2,9.2,9.2,13.8,20.6,13.8,34.1v.6c0,6.3-.8,11.8-2.5,16.6-1.6,4.8-3.8,9-6.4,12.6-2.6,3.6-5.7,6.8-9.3,9.6-3.6,2.8-7.2,5.1-10.9,7.1,6,2.2,11.6,4.7,16.5,7.5,5,2.9,9.3,6.2,12.9,10.2s6.4,8.5,8.3,13.8c2,5.3,2.9,11.5,2.9,18.5v.6c0,9.2-1.8,17.4-5.4,24.4-3.6,7.1-8.8,13-15.4,17.7s-14.7,8.3-24.1,10.7c-9.4,2.5-19.8,3.7-31.1,3.7h-95.2v-206h0ZM192.6,256.7c11.7,0,21.1-2.3,28.2-6.9,7-4.6,10.6-11.6,10.6-21v-.6c0-8.2-3.1-14.7-9.2-19.3s-15.1-6.9-26.8-6.9h-51.9v54.8h49.3ZM203.2,343.8c12.3,0,22-2.4,29.1-7.1,7-4.7,10.6-11.8,10.6-21.2v-.6c0-8.8-3.5-15.6-10.4-20.5-6.9-4.8-17.6-7.2-31.8-7.2h-57.2v56.5h59.9Z"/>
								<path fill="#FFF" d="M385.6,379.1c-12.7,0-23.3-2.8-31.7-8.2-8.4-5.5-15.4-11.9-20.9-19.1v24.1h-35.9v-205.8h35l.6,76.3c5.7-8.2,12.8-15.1,21.2-20.8,8.4-5.6,18.9-8.4,31.5-8.4s18.1,1.8,26.8,5.3c8.6,3.5,16.3,8.7,23.1,15.6,6.8,6.9,12.2,15.3,16.3,25.3,4.1,10,6.2,21.5,6.2,34.4v.6c0,13-2,24.4-6,34.4-4,10-9.4,18.4-16.1,25.3s-14.5,12.1-23.2,15.6-17.7,5.3-26.9,5.3h0ZM377.5,348.2c6.1,0,11.8-1.2,17.2-3.5,5.4-2.4,10.1-5.6,14.1-9.9,4-4.2,7.2-9.4,9.6-15.6,2.4-6.2,3.5-13.1,3.5-20.8v-.6c0-7.5-1.2-14.3-3.5-20.5-2.4-6.2-5.5-11.4-9.6-15.8-4-4.3-8.7-7.6-14.1-10-5.4-2.4-11.1-3.5-17.2-3.5s-11.9,1.2-17.4,3.5-10.3,5.7-14.4,10.2c-4.1,4.4-7.4,9.7-9.9,15.8-2.5,6.1-3.7,12.9-3.7,20.3v.6c0,7.5,1.2,14.3,3.7,20.5,2.5,6.2,5.7,11.4,9.9,15.8s8.9,7.6,14.4,10c5.5,2.4,11.3,3.5,17.4,3.5h0Z"/>
								<rect x="705.2" y="66.4" width="6" height="416.5"/>
								<g>
									<g>
										<path d="M848.3,155c5.9,0,11,.6,15.2,1.7,4.3,1.2,7.8,2.8,10.5,5,2.7,2.1,4.8,4.8,6.1,7.9,1.3,3.1,1.9,6.6,1.9,10.5s-.4,4.6-1.1,6.8c-.7,2.2-1.8,4.2-3.3,6.1s-3.3,3.6-5.6,5.1c-2.3,1.5-4.9,2.7-7.9,3.6,7,1.3,12.3,3.8,15.9,7.5s5.3,8.5,5.3,14.5-.7,7.8-2.2,11.1c-1.5,3.3-3.7,6.2-6.6,8.6-2.9,2.4-6.4,4.3-10.6,5.6-4.2,1.3-9,2-14.3,2h-33.8v-95.9h30.6ZM830.6,165.3v32.7h17.1c3.7,0,6.8-.4,9.5-1.2,2.7-.8,4.9-1.9,6.7-3.3,1.8-1.4,3.1-3.2,3.9-5.2.8-2,1.3-4.2,1.3-6.7,0-5.7-1.7-9.8-5.1-12.4-3.4-2.6-8.7-3.9-15.8-3.9h-17.7ZM851.3,240.6c3.7,0,6.9-.4,9.6-1.3,2.7-.8,4.9-2,6.6-3.6,1.7-1.5,3-3.4,3.8-5.5s1.2-4.4,1.2-7c0-4.9-1.8-8.9-5.3-11.7-3.5-2.9-8.8-4.3-15.9-4.3h-20.7v33.4h20.7Z"/>
										<path d="M975,250.9h-11.5c-2.4,0-4.1-.9-5.2-2.7l-24.9-34.2c-.8-1.1-1.6-1.8-2.4-2.3-.9-.5-2.2-.7-4-.7h-9.8v40h-12.9v-95.9h27.1c6.1,0,11.3.6,15.7,1.8,4.4,1.2,8.1,3,10.9,5.3,2.9,2.3,5,5.1,6.4,8.4,1.4,3.3,2.1,6.9,2.1,11s-.5,6.6-1.6,9.5-2.6,5.6-4.6,7.9-4.5,4.3-7.4,6c-2.9,1.7-6.2,2.9-9.9,3.7,1.6.9,3.1,2.3,4.3,4.1l28,38.1ZM930.7,201.4c3.7,0,7.1-.5,9.9-1.4,2.9-.9,5.3-2.2,7.2-3.9,1.9-1.7,3.4-3.7,4.4-6,1-2.3,1.5-4.9,1.5-7.7,0-5.7-1.9-10-5.6-12.9-3.7-2.9-9.4-4.3-16.9-4.3h-14.2v36.2h13.6Z"/>
									</g>
									<g>
										<path d="M1068.3,250.9h-10c-1.2,0-2.1-.3-2.8-.9-.7-.6-1.2-1.3-1.6-2.2l-9-23.1h-43l-9,23.1c-.3.8-.8,1.5-1.5,2.1-.8.6-1.7.9-2.8.9h-10l38.3-95.9h13.1l38.3,95.9ZM1041.3,215.3l-15.1-39c-.4-1.2-.9-2.5-1.4-4-.5-1.5-1-3.2-1.4-4.9-.9,3.6-1.9,6.6-2.9,9l-15.1,38.9h35.8Z"/>
									</g>
									<g>
										<path d="M1156.4,204.8v36.8c-4.8,3.5-10,6.1-15.4,7.8-5.5,1.7-11.5,2.6-18,2.6s-14.7-1.2-20.9-3.6c-6.2-2.4-11.5-5.7-15.9-10-4.4-4.3-7.8-9.5-10.1-15.5-2.4-6-3.5-12.6-3.5-19.9s1.1-14,3.4-20,5.6-11.2,9.8-15.5c4.2-4.3,9.4-7.6,15.5-10,6.1-2.4,12.8-3.5,20.3-3.5s7.3.3,10.6.8c3.3.6,6.3,1.4,9,2.4,2.8,1,5.3,2.3,7.7,3.8,2.4,1.5,4.6,3.2,6.6,5.1l-3.7,5.9c-.6.9-1.3,1.5-2.2,1.7-.9.2-1.9,0-3-.6-1.1-.6-2.3-1.4-3.7-2.3-1.4-.9-3.1-1.8-5-2.6-2-.8-4.3-1.5-7-2.1-2.7-.6-5.9-.9-9.6-.9-5.4,0-10.3.9-14.6,2.6-4.4,1.8-8.1,4.3-11.2,7.6s-5.4,7.2-7.1,11.9c-1.7,4.7-2.5,9.9-2.5,15.7s.9,11.4,2.6,16.2c1.7,4.7,4.2,8.8,7.4,12.1,3.2,3.3,7.1,5.9,11.6,7.6s9.7,2.6,15.3,2.6,8.4-.5,11.9-1.5c3.5-1,6.8-2.4,10.1-4.2v-21.1h-14.9c-.8,0-1.5-.2-2-.7-.5-.5-.8-1.1-.8-1.8v-7.4h29.4Z"/>
										<path d="M1219.7,192.9c2.9-3.3,6.1-5.9,9.7-7.9,3.6-2,7.7-2.9,12.4-2.9s7.5.8,10.7,2.3c3.2,1.5,5.9,3.7,8.1,6.6,2.2,2.9,3.9,6.4,5.1,10.5,1.2,4.1,1.8,8.7,1.8,13.8s-.7,10.5-2,15c-1.3,4.5-3.3,8.4-5.8,11.5-2.5,3.2-5.6,5.7-9.2,7.4-3.6,1.8-7.7,2.6-12.2,2.6s-8.2-.8-11.3-2.5c-3.1-1.7-5.7-4-8-7.1l-.6,6.2c-.4,1.7-1.4,2.5-3.1,2.5h-7.7v-98.5h12v40.5ZM1219.7,234.6c2.2,2.9,4.6,5,7.2,6.2,2.6,1.2,5.5,1.8,8.6,1.8,6.4,0,11.3-2.3,14.7-6.8,3.4-4.5,5.1-11.2,5.1-20.1s-1.5-14.4-4.5-18.3c-3-3.9-7.3-5.9-12.9-5.9s-7.3.9-10.1,2.7c-2.9,1.8-5.5,4.3-8,7.6v32.8Z"/>
										<path d="M1311.2,182c5,0,9.4.8,13.4,2.5,4,1.7,7.4,4,10.2,7,2.8,3,5,6.7,6.5,11,1.5,4.3,2.2,9.1,2.2,14.4s-.7,10.2-2.2,14.4c-1.5,4.3-3.6,7.9-6.5,11-2.8,3-6.2,5.4-10.2,7-4,1.6-8.4,2.4-13.4,2.4s-9.5-.8-13.5-2.4c-4-1.6-7.4-4-10.2-7-2.8-3-5-6.7-6.5-11-1.5-4.3-2.2-9.1-2.2-14.4s.7-10.1,2.2-14.4c1.5-4.3,3.6-8,6.5-11,2.8-3,6.2-5.4,10.2-7,4-1.6,8.5-2.5,13.5-2.5ZM1311.2,242.5c6.7,0,11.7-2.2,15-6.7,3.3-4.5,5-10.7,5-18.8s-1.7-14.4-5-18.9c-3.3-4.5-8.3-6.8-15-6.8s-6.3.6-8.9,1.7c-2.5,1.2-4.6,2.8-6.3,5-1.7,2.2-2.9,4.9-3.7,8.1-.8,3.2-1.2,6.8-1.2,10.8,0,8,1.7,14.3,5,18.8,3.3,4.5,8.4,6.7,15.2,6.7Z"/>
										<path d="M1387.1,182c5,0,9.4.8,13.4,2.5,4,1.7,7.4,4,10.2,7,2.8,3,5,6.7,6.5,11,1.5,4.3,2.2,9.1,2.2,14.4s-.7,10.2-2.2,14.4c-1.5,4.3-3.6,7.9-6.5,11-2.8,3-6.2,5.4-10.2,7-4,1.6-8.4,2.4-13.4,2.4s-9.5-.8-13.5-2.4c-4-1.6-7.4-4-10.2-7-2.8-3-5-6.7-6.5-11-1.5-4.3-2.2-9.1-2.2-14.4s.7-10.1,2.2-14.4c1.5-4.3,3.6-8,6.5-11,2.8-3,6.2-5.4,10.2-7,4-1.6,8.5-2.5,13.5-2.5ZM1387.1,242.5c6.7,0,11.7-2.2,15-6.7,3.3-4.5,5-10.7,5-18.8s-1.7-14.4-5-18.9c-3.3-4.5-8.3-6.8-15-6.8s-6.3.6-8.9,1.7c-2.5,1.2-4.6,2.8-6.3,5-1.7,2.2-2.9,4.9-3.7,8.1-.8,3.2-1.2,6.8-1.2,10.8,0,8,1.7,14.3,5,18.8,3.3,4.5,8.4,6.7,15.2,6.7Z"/>
										<path d="M1446.4,152.3v58h3.1c.9,0,1.6-.1,2.2-.4.6-.2,1.2-.7,1.9-1.5l21.4-22.9c.6-.8,1.3-1.3,2-1.8.7-.4,1.6-.6,2.8-.6h10.8l-25,26.6c-1.2,1.5-2.5,2.7-3.9,3.5.8.5,1.5,1.1,2.2,1.8.6.7,1.3,1.5,1.8,2.4l26.5,33.4h-10.6c-1,0-1.9-.2-2.6-.5-.7-.3-1.4-.9-1.9-1.8l-22.3-27.8c-.7-.9-1.3-1.5-2-1.8-.6-.3-1.6-.4-3-.4h-3.4v32.4h-12v-98.5h12Z"/>
									</g>
									<g>
										<path d="M848.3,315.6c5.9,0,11,.6,15.2,1.7,4.3,1.2,7.8,2.8,10.5,5,2.7,2.1,4.8,4.8,6.1,7.9,1.3,3.1,1.9,6.6,1.9,10.5s-.4,4.6-1.1,6.8c-.7,2.2-1.8,4.2-3.3,6.1s-3.3,3.6-5.6,5.1c-2.3,1.5-4.9,2.7-7.9,3.6,7,1.3,12.3,3.8,15.9,7.5,3.5,3.7,5.3,8.5,5.3,14.5s-.7,7.8-2.2,11.1c-1.5,3.3-3.7,6.2-6.6,8.6-2.9,2.4-6.4,4.3-10.6,5.6-4.2,1.3-9,2-14.3,2h-33.8v-95.9h30.6ZM830.6,325.8v32.7h17.1c3.7,0,6.8-.4,9.5-1.2,2.7-.8,4.9-1.9,6.7-3.3,1.8-1.4,3.1-3.2,3.9-5.2.8-2,1.3-4.2,1.3-6.7,0-5.7-1.7-9.8-5.1-12.4-3.4-2.6-8.7-3.9-15.8-3.9h-17.7ZM851.3,401.1c3.7,0,6.9-.4,9.6-1.3,2.7-.8,4.9-2,6.6-3.6,1.7-1.5,3-3.4,3.8-5.5s1.2-4.4,1.2-7c0-4.9-1.8-8.9-5.3-11.7-3.5-2.9-8.8-4.3-15.9-4.3h-20.7v33.4h20.7Z"/>
										<path d="M955.4,401.9c-1.5,1.8-3.2,3.3-5.3,4.6s-4.2,2.4-6.6,3.2c-2.3.8-4.8,1.5-7.3,1.9-2.5.4-5,.6-7.4.6-4.7,0-9-.8-12.9-2.4-3.9-1.6-7.4-3.9-10.2-7-2.9-3.1-5.1-6.8-6.7-11.3-1.6-4.5-2.4-9.7-2.4-15.5s.7-9.1,2.2-13.2c1.4-4.1,3.5-7.7,6.3-10.7,2.7-3,6-5.4,10-7.1,3.9-1.7,8.3-2.6,13.2-2.6s7.8.7,11.3,2c3.5,1.4,6.4,3.3,9,5.9s4.5,5.7,5.9,9.5,2.1,8.1,2.1,12.9-.2,3.1-.6,3.7c-.4.6-1.2.9-2.3.9h-45.3c.1,4.3.7,8,1.8,11.2,1,3.2,2.5,5.8,4.4,7.9s4.1,3.7,6.7,4.7c2.6,1,5.5,1.6,8.7,1.6s5.6-.3,7.7-1c2.2-.7,4-1.4,5.6-2.2s2.9-1.5,3.9-2.2c1-.7,2-1,2.7-1s1.7.4,2.3,1.1l3.3,4.3ZM945.7,370.2c0-2.8-.4-5.3-1.2-7.6-.8-2.3-1.9-4.3-3.4-6-1.5-1.7-3.3-3-5.5-3.9-2.1-.9-4.6-1.4-7.3-1.4-5.7,0-10.2,1.7-13.5,5-3.3,3.3-5.4,7.9-6.2,13.8h37.1Z"/>
										<path d="M974.8,411.4v-57.6l-7.5-.9c-.9-.2-1.7-.6-2.3-1-.6-.5-.9-1.1-.9-2v-4.9h10.7v-6.6c0-3.9.5-7.3,1.6-10.3,1.1-3,2.7-5.6,4.7-7.6,2-2.1,4.5-3.6,7.3-4.7,2.9-1.1,6.1-1.6,9.6-1.6s5.8.4,8.4,1.3l-.3,6c0,1.1-.7,1.7-1.8,1.7-1.1,0-2.6.1-4.4.1s-3.9.3-5.6.8c-1.7.5-3.1,1.4-4.3,2.6s-2.1,2.8-2.8,4.7c-.6,2-1,4.4-1,7.3v6.2h19.5v8.6h-19.1v57.8h-12Z"/>
										<path d="M1043.6,342.6c5,0,9.4.8,13.4,2.5,4,1.7,7.4,4,10.2,7,2.8,3,5,6.7,6.5,11,1.5,4.3,2.2,9.1,2.2,14.4s-.7,10.2-2.2,14.4c-1.5,4.3-3.6,7.9-6.5,11-2.8,3-6.2,5.4-10.2,7-4,1.6-8.4,2.4-13.4,2.4s-9.5-.8-13.5-2.4c-4-1.6-7.4-4-10.2-7-2.8-3-5-6.7-6.5-11-1.5-4.3-2.2-9.1-2.2-14.4s.7-10.1,2.2-14.4c1.5-4.3,3.6-8,6.5-11,2.8-3,6.2-5.4,10.2-7,4-1.6,8.5-2.5,13.5-2.5ZM1043.6,403.1c6.7,0,11.7-2.2,15-6.7,3.3-4.5,5-10.7,5-18.8s-1.7-14.4-5-18.9c-3.3-4.5-8.3-6.8-15-6.8s-6.3.6-8.9,1.7c-2.5,1.2-4.6,2.8-6.3,5-1.7,2.2-2.9,4.9-3.7,8.1-.8,3.2-1.2,6.8-1.2,10.8,0,8,1.7,14.3,5,18.8,3.3,4.5,8.4,6.7,15.2,6.7Z"/>
										<path d="M1102.2,357.2c2.1-4.6,4.8-8.3,7.9-10.9,3.1-2.6,6.9-3.9,11.4-3.9s2.8.2,4.1.5c1.3.3,2.5.8,3.5,1.5l-.9,8.9c-.3,1.1-.9,1.7-2,1.7s-1.5-.1-2.7-.4-2.6-.4-4.1-.4-4,.3-5.7.9c-1.7.6-3.2,1.5-4.5,2.8-1.3,1.2-2.5,2.7-3.5,4.5-1,1.8-2,3.9-2.8,6.2v42.8h-12v-67.8h6.8c1.3,0,2.2.2,2.7.7.5.5.8,1.3,1,2.5l.8,10.3Z"/>
										<path d="M1191.8,401.9c-1.5,1.8-3.2,3.3-5.3,4.6-2.1,1.3-4.2,2.4-6.6,3.2-2.3.8-4.8,1.5-7.3,1.9-2.5.4-5,.6-7.4.6-4.7,0-9-.8-12.9-2.4-3.9-1.6-7.4-3.9-10.2-7-2.9-3.1-5.1-6.8-6.7-11.3-1.6-4.5-2.4-9.7-2.4-15.5s.7-9.1,2.2-13.2c1.4-4.1,3.5-7.7,6.3-10.7,2.7-3,6-5.4,10-7.1,3.9-1.7,8.3-2.6,13.2-2.6s7.8.7,11.3,2c3.5,1.4,6.4,3.3,9,5.9,2.5,2.6,4.5,5.7,5.9,9.5,1.4,3.8,2.1,8.1,2.1,12.9s-.2,3.1-.6,3.7c-.4.6-1.2.9-2.3.9h-45.3c.1,4.3.7,8,1.8,11.2,1,3.2,2.5,5.8,4.4,7.9s4.1,3.7,6.7,4.7c2.6,1,5.5,1.6,8.7,1.6s5.6-.3,7.7-1c2.2-.7,4-1.4,5.6-2.2,1.6-.8,2.9-1.5,3.9-2.2,1-.7,2-1,2.7-1s1.7.4,2.3,1.1l3.3,4.3ZM1182.2,370.2c0-2.8-.4-5.3-1.2-7.6s-1.9-4.3-3.4-6c-1.5-1.7-3.3-3-5.5-3.9-2.1-.9-4.6-1.4-7.3-1.4-5.7,0-10.2,1.7-13.5,5s-5.4,7.9-6.2,13.8h37.1Z"/>
										<path d="M1326.4,411.4h-11.6c-1.3,0-2.3-.2-3.1-.5-.8-.3-1.7-1-2.6-1.9l-9.7-9.8c-4.2,4.1-9,7.3-14.5,9.7-5.5,2.4-11.5,3.6-18,3.6s-7.1-.6-10.5-1.8c-3.4-1.2-6.5-3-9.2-5.3-2.7-2.3-4.9-5.1-6.6-8.4-1.7-3.3-2.5-7-2.5-11.2s.5-6.1,1.6-8.9c1-2.8,2.5-5.4,4.4-7.7,1.9-2.4,4.1-4.5,6.6-6.4s5.3-3.5,8.4-4.8c-2.7-3.5-4.7-6.8-6-10.1-1.3-3.2-1.9-6.6-1.9-10.1s.6-6.3,1.8-9.2c1.2-2.9,2.9-5.3,5.1-7.5,2.2-2.1,4.9-3.8,8.1-5s6.8-1.8,10.7-1.8,6.7.6,9.6,1.7c2.9,1.1,5.4,2.6,7.5,4.5,2.1,1.9,3.8,4,5,6.5,1.2,2.5,1.9,5.1,2.1,7.7l-7.4,1.5c-1.5.4-2.6-.3-3.3-2.1-.3-1.2-.8-2.4-1.5-3.6-.7-1.2-1.6-2.4-2.7-3.4-1.1-1-2.4-1.9-3.9-2.5-1.5-.7-3.3-1-5.4-1s-4.2.4-6.1,1.1c-1.8.7-3.3,1.7-4.6,3-1.3,1.3-2.2,2.8-2.9,4.5-.7,1.7-1,3.6-1,5.6s.2,3.1.5,4.5c.4,1.4.9,2.9,1.7,4.3s1.8,3,3,4.5c1.2,1.5,2.7,3.1,4.3,4.8l27.6,28c1.7-3,3.1-6.2,4.1-9.5,1-3.3,1.7-6.5,2-9.8,0-.8.4-1.5.8-2,.4-.5,1.1-.7,1.9-.7h7.3c0,5.1-.8,10.2-2.4,15.1-1.6,4.9-3.8,9.6-6.8,13.9l20.1,20.4ZM1264.6,364.2c-4.7,2.5-8.3,5.5-10.7,9.1-2.4,3.6-3.6,7.4-3.6,11.6s.5,5.5,1.6,7.7c1.1,2.2,2.5,4.1,4.2,5.6,1.7,1.5,3.7,2.7,6,3.4,2.2.8,4.5,1.2,6.8,1.2,5,0,9.5-.9,13.5-2.7,4-1.8,7.5-4.3,10.5-7.4l-28.3-28.5Z"/>
										<path d="M1447.5,411.4h-10c-1.2,0-2.1-.3-2.8-.9-.7-.6-1.2-1.3-1.6-2.2l-9-23.1h-43l-9,23.1c-.3.8-.8,1.5-1.5,2.1-.8.6-1.7.9-2.8.9h-10l38.3-95.9h13.1l38.3,95.9ZM1420.5,375.8l-15.1-39c-.4-1.2-.9-2.5-1.4-4-.5-1.5-1-3.2-1.4-4.9-.9,3.6-1.9,6.6-2.9,9l-15.1,38.9h35.8Z"/>
										<path d="M1514.1,353.6v40.7c0,2.9.7,5,2.1,6.4,1.4,1.4,3.1,2.1,5.3,2.1s2.4-.2,3.3-.5c.9-.3,1.7-.7,2.4-1.1s1.2-.8,1.7-1.1c.4-.3.8-.5,1.2-.5s.7,0,1,.3c.2.2.5.5.8.9l3.5,5.7c-2.1,1.9-4.5,3.4-7.4,4.5-2.9,1.1-5.9,1.6-9,1.6-5.3,0-9.4-1.5-12.3-4.5-2.9-3-4.3-7.3-4.3-12.9v-41.5h-31.4v57.8h-12v-57.6l-7.5-.9c-.9-.2-1.7-.6-2.3-1-.6-.5-.9-1.1-.9-2v-4.9h10.7v-6.6c0-3.9.5-7.3,1.6-10.3,1.1-3,2.7-5.6,4.7-7.6,2-2.1,4.5-3.6,7.3-4.7,2.9-1.1,6.1-1.6,9.6-1.6s5.8.4,8.4,1.3l-.3,6c0,1.1-.7,1.7-1.8,1.7-1.1,0-2.6.1-4.4.1s-3.9.3-5.6.8c-1.7.5-3.1,1.4-4.3,2.6s-2.1,2.8-2.8,4.7c-.6,2-1,4.4-1,7.3v6.2h32.2l2.7-21.1c.2-.7.5-1.2,1-1.6.5-.4,1.1-.6,1.8-.6h6v23.3h19.5v8.6h-19.5Z"/>
										<path d="M1600.6,401.9c-1.5,1.8-3.2,3.3-5.3,4.6-2.1,1.3-4.2,2.4-6.6,3.2-2.3.8-4.8,1.5-7.3,1.9-2.5.4-5,.6-7.4.6-4.7,0-9-.8-12.9-2.4-3.9-1.6-7.4-3.9-10.2-7-2.9-3.1-5.1-6.8-6.7-11.3-1.6-4.5-2.4-9.7-2.4-15.5s.7-9.1,2.2-13.2c1.4-4.1,3.5-7.7,6.3-10.7,2.7-3,6-5.4,10-7.1,3.9-1.7,8.3-2.6,13.2-2.6s7.8.7,11.3,2c3.5,1.4,6.4,3.3,9,5.9,2.5,2.6,4.5,5.7,5.9,9.5,1.4,3.8,2.1,8.1,2.1,12.9s-.2,3.1-.6,3.7c-.4.6-1.2.9-2.3.9h-45.3c.1,4.3.7,8,1.8,11.2,1,3.2,2.5,5.8,4.4,7.9s4.1,3.7,6.7,4.7c2.6,1,5.5,1.6,8.7,1.6s5.6-.3,7.7-1c2.2-.7,4-1.4,5.6-2.2,1.6-.8,2.9-1.5,3.9-2.2,1-.7,2-1,2.7-1s1.7.4,2.3,1.1l3.3,4.3ZM1591,370.2c0-2.8-.4-5.3-1.2-7.6s-1.9-4.3-3.4-6c-1.5-1.7-3.3-3-5.5-3.9-2.1-.9-4.6-1.4-7.3-1.4-5.7,0-10.2,1.7-13.5,5s-5.4,7.9-6.2,13.8h37.1Z"/>
										<path d="M1627.6,357.2c2.1-4.6,4.8-8.3,7.9-10.9,3.1-2.6,6.9-3.9,11.4-3.9s2.8.2,4.1.5c1.3.3,2.5.8,3.5,1.5l-.9,8.9c-.3,1.1-.9,1.7-2,1.7s-1.5-.1-2.7-.4-2.6-.4-4.1-.4-4,.3-5.7.9c-1.7.6-3.2,1.5-4.5,2.8-1.3,1.2-2.5,2.7-3.5,4.5-1,1.8-2,3.9-2.8,6.2v42.8h-12v-67.8h6.8c1.3,0,2.2.2,2.7.7.5.5.8,1.3,1,2.5l.8,10.3Z"/>
										<path d="M1777.1,365.3v36.8c-4.8,3.5-10,6.1-15.4,7.8-5.5,1.7-11.5,2.6-18,2.6s-14.7-1.2-20.9-3.6c-6.2-2.4-11.5-5.7-15.9-10-4.4-4.3-7.8-9.5-10.1-15.5-2.4-6-3.5-12.6-3.5-19.9s1.1-14,3.4-20c2.3-6,5.6-11.2,9.8-15.5,4.2-4.3,9.4-7.6,15.5-10,6.1-2.4,12.8-3.5,20.3-3.5s7.3.3,10.6.8c3.3.6,6.3,1.4,9,2.4,2.8,1,5.3,2.3,7.7,3.8,2.4,1.5,4.6,3.2,6.6,5.1l-3.7,5.9c-.6.9-1.3,1.5-2.2,1.7-.9.2-1.9,0-3-.6-1.1-.6-2.3-1.4-3.7-2.3-1.4-.9-3.1-1.8-5.1-2.6-2-.8-4.3-1.5-7-2.1-2.7-.6-5.9-.9-9.6-.9-5.4,0-10.3.9-14.7,2.6-4.4,1.8-8.1,4.3-11.2,7.6-3.1,3.3-5.4,7.2-7.1,11.9-1.7,4.7-2.5,9.9-2.5,15.7s.9,11.4,2.6,16.2c1.7,4.7,4.2,8.8,7.4,12.1,3.2,3.3,7.1,5.9,11.6,7.6s9.7,2.6,15.3,2.6,8.4-.5,11.9-1.5c3.5-1,6.8-2.4,10.1-4.2v-21.1h-14.9c-.8,0-1.5-.2-2-.7-.5-.5-.8-1.1-.8-1.8v-7.4h29.4Z"/>
										<path d="M1792,353.2c3.7-3.6,7.8-6.3,12.1-8.1,4.3-1.8,9.1-2.7,14.4-2.7s7.2.6,10.1,1.9c2.9,1.2,5.4,3,7.4,5.2,2,2.2,3.5,4.9,4.5,8.1,1,3.2,1.5,6.6,1.5,10.4v43.3h-5.3c-1.2,0-2.1-.2-2.7-.6-.6-.4-1.1-1.1-1.5-2.2l-1.3-6.4c-1.8,1.7-3.5,3.1-5.2,4.4-1.7,1.3-3.5,2.3-5.4,3.2-1.9.9-3.9,1.5-6,2-2.1.5-4.5.7-7.1.7s-5.1-.4-7.4-1.1c-2.3-.7-4.3-1.8-6.1-3.3s-3.1-3.3-4.1-5.6c-1-2.3-1.5-4.9-1.5-8s.7-5.3,2.2-7.7c1.5-2.5,3.9-4.7,7.2-6.6,3.3-1.9,7.6-3.5,12.9-4.7,5.3-1.2,11.8-1.9,19.5-2.1v-5.3c0-5.3-1.1-9.2-3.4-11.9s-5.6-4-10-4-5.4.4-7.4,1.1c-2,.7-3.7,1.6-5.2,2.5-1.4.9-2.7,1.7-3.7,2.5-1,.7-2.1,1.1-3.1,1.1s-1.5-.2-2.1-.6c-.6-.4-1.1-.9-1.5-1.6l-2.1-3.8ZM1830.5,380.9c-5.5.2-10.2.6-14,1.3s-7,1.6-9.4,2.7c-2.4,1.1-4.2,2.4-5.3,3.9-1.1,1.5-1.6,3.2-1.6,5.1s.3,3.3.9,4.6c.6,1.3,1.4,2.4,2.4,3.2,1,.8,2.2,1.4,3.5,1.8,1.4.4,2.8.6,4.4.6s4-.2,5.8-.6,3.4-1,4.9-1.8c1.5-.8,3-1.8,4.4-2.9,1.4-1.1,2.8-2.4,4.1-3.8v-14Z"/>
										<path d="M1872.6,312.9v98.5h-11.9v-98.5h11.9Z"/>
										<path d="M1904.1,312.9v98.5h-11.9v-98.5h11.9Z"/>
										<path d="M1978.4,401.9c-1.5,1.8-3.2,3.3-5.3,4.6-2.1,1.3-4.2,2.4-6.6,3.2-2.3.8-4.8,1.5-7.3,1.9-2.5.4-5,.6-7.4.6-4.7,0-9-.8-12.9-2.4-3.9-1.6-7.4-3.9-10.2-7-2.9-3.1-5.1-6.8-6.7-11.3-1.6-4.5-2.4-9.7-2.4-15.5s.7-9.1,2.2-13.2c1.4-4.1,3.5-7.7,6.3-10.7,2.7-3,6-5.4,10-7.1,3.9-1.7,8.3-2.6,13.2-2.6s7.8.7,11.3,2c3.5,1.4,6.4,3.3,9,5.9,2.5,2.6,4.5,5.7,5.9,9.5,1.4,3.8,2.1,8.1,2.1,12.9s-.2,3.1-.6,3.7c-.4.6-1.2.9-2.3.9h-45.3c.1,4.3.7,8,1.8,11.2,1,3.2,2.5,5.8,4.4,7.9s4.1,3.7,6.7,4.7c2.6,1,5.5,1.6,8.7,1.6s5.6-.3,7.7-1c2.2-.7,4-1.4,5.6-2.2,1.6-.8,2.9-1.5,3.9-2.2,1-.7,2-1,2.7-1s1.7.4,2.3,1.1l3.3,4.3ZM1968.8,370.2c0-2.8-.4-5.3-1.2-7.6s-1.9-4.3-3.4-6c-1.5-1.7-3.3-3-5.5-3.9-2.1-.9-4.6-1.4-7.3-1.4-5.7,0-10.2,1.7-13.5,5s-5.4,7.9-6.2,13.8h37.1Z"/>
										<path d="M2005.3,357.2c2.1-4.6,4.8-8.3,7.9-10.9,3.1-2.6,6.9-3.9,11.4-3.9s2.8.2,4.1.5c1.3.3,2.5.8,3.5,1.5l-.9,8.9c-.3,1.1-.9,1.7-2,1.7s-1.5-.1-2.7-.4-2.6-.4-4.1-.4-4,.3-5.7.9c-1.7.6-3.2,1.5-4.5,2.8-1.3,1.2-2.5,2.7-3.5,4.5-1,1.8-2,3.9-2.8,6.2v42.8h-12v-67.8h6.8c1.3,0,2.2.2,2.7.7.5.5.8,1.3,1,2.5l.8,10.3Z"/>
										<path d="M2101.2,343.7l-37.8,87.8c-.4.9-.9,1.6-1.5,2.1-.6.5-1.5.8-2.8.8h-8.8l12.4-26.9-28-63.8h10.3c1,0,1.8.3,2.4.8.6.5,1,1.1,1.2,1.7l18.1,42.7c.7,1.9,1.3,3.8,1.8,5.9.6-2.1,1.3-4.1,2-6l17.6-42.6c.3-.7.7-1.3,1.4-1.8.6-.5,1.4-.7,2.2-.7h9.4Z"/>
									</g>
								</g>
							</g>
						</svg>
					</div>
					<div class="brag-book-gallery-header__status">
						<span class="brag-book-gallery-version-badge">
							<?php echo esc_html( 'v' . $version ); ?>
						</span>
						<a class="button button-primary" href="<?php echo esc_url( 'https://bragbookgallery.com/' ); ?>" target="_blank">
							<?php esc_html_e( 'Visit Site', 'brag-book-gallery' ); ?>
							<svg xmlns="http://www.w3.org/2000/svg" height="18px" viewBox="0 -960 960 960" width="18px" fill="currentColor"><path d="M212.31-140Q182-140 161-161q-21-21-21-51.31v-535.38Q140-778 161-799q21-21 51.31-21h252.3v60h-252.3q-4.62 0-8.46 3.85-3.85 3.84-3.85 8.46v535.38q0 4.62 3.85 8.46 3.84 3.85 8.46 3.85h535.38q4.62 0 8.46-3.85 3.85-3.84 3.85-8.46v-252.3h60v252.3Q820-182 799-161q-21 21-51.31 21H212.31Zm176.46-206.62-42.15-42.15L717.85-760H560v-60h260v260h-60v-157.85L388.77-346.62Z"/></svg>
						</a>
					</div>
				</header>

				<?php $this->render_navigation(); ?>

				<div class="brag-book-gallery-content">
		<?php
	}

	/**
	 * Render settings footer
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function render_footer(): void {
		?>
				</div><!-- .brag-book-gallery-content -->
			</div><!-- .brag-book-gallery-container -->
		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Validate form submission with comprehensive security checks
	 *
	 * Performs critical security validations before processing any form data.
	 * This method should be called at the beginning of any save operation
	 * to ensure the request is legitimate and authorized.
	 *
	 * Security checks performed:
	 * - WordPress nonce verification to prevent CSRF attacks
	 * - User capability verification to ensure proper permissions
	 * - Request method validation (POST required)
	 *
	 * The method returns false if any security check fails, allowing
	 * calling methods to abort processing safely without exposing
	 * sensitive operations to unauthorized users.
	 *
	 * This follows WordPress security best practices and should be used
	 * consistently across all settings save operations.
	 *
	 * @since 3.0.0
	 * @param string $nonce_action Nonce action name used when creating the nonce
	 * @param string $nonce_field  Form field name containing the nonce value
	 * @return bool True if all security checks pass, false if any check fails
	 */
	protected function save_settings( string $nonce_action, string $nonce_field ): bool {
		// Verify nonce to prevent CSRF attacks
		if ( ! isset( $_POST[ $nonce_field ] ) ||
		     ! wp_verify_nonce( $_POST[ $nonce_field ], $nonce_action ) ) {
			return false;
		}

		// Check user capabilities to ensure proper authorization
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add a WordPress admin notice for user feedback
	 *
	 * Creates an admin notice that will be displayed on the current settings page
	 * to provide feedback about operations (successful saves, errors, warnings, etc.).
	 *
	 * Uses WordPress's built-in settings error system which automatically handles:
	 * - Proper HTML structure and CSS classes
	 * - Dismissible notices where appropriate
	 * - Integration with WordPress admin styling
	 * - Accessibility features for screen readers
	 *
	 * Notice types and their typical usage:
	 * - 'success': Settings saved successfully
	 * - 'error': Validation errors or save failures
	 * - 'warning': Configuration issues or deprecation notices
	 * - 'info': Informational messages or status updates
	 *
	 * The notice will be displayed when settings_errors() is called,
	 * typically in the render() method of child classes.
	 *
	 * @since 3.0.0
	 * @param string $message Localized message text to display to user
	 * @param string $type    Notice type: 'success', 'error', 'warning', 'info'
	 * @return void Notice is queued for display, not output immediately
	 */
	protected function add_notice( string $message, string $type = 'success' ): void {
		add_settings_error(
			$this->page_slug,
			$this->page_slug . '_message',
			$message,
			$type
		);
	}

	/**
	 * Get current mode
	 *
	 * @since 3.0.0
	 * @return string Current mode (javascript or local).
	 */
	protected function get_current_mode(): string {
		return get_option( 'brag_book_gallery_mode', 'javascript' );
	}

	/**
	 * Check if in JavaScript mode
	 *
	 * @since 3.0.0
	 * @return bool True if in JavaScript mode.
	 */
	protected function is_javascript_mode(): bool {
		return $this->get_current_mode() === 'javascript';
	}

	/**
	 * Check if in Local mode
	 *
	 * @since 3.0.0
	 * @return bool True if in Local mode.
	 */
	protected function is_local_mode(): bool {
		return $this->get_current_mode() === 'local';
	}
}
