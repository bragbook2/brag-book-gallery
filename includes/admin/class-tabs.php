<?php
/**
 * Tabs Class - Manages navigation tabs for admin pages
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

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Tabs Class
 *
 * Centralized management of navigation tabs for BRAG book Gallery admin pages.
 * This class provides a consistent interface for creating and rendering
 * tabbed navigation across all settings pages, ensuring a unified user
 * experience throughout the plugin's admin interface.
 *
 * Features:
 * - Dynamic tab generation based on configuration
 * - Conditional tab visibility based on plugin state
 * - Active tab highlighting
 * - Icon support for visual clarity
 * - Badge indicators for important states
 * - Responsive design for mobile compatibility
 *
 * @since 3.0.0
 */
class Tabs {

	/**
	 * Tab configurations for different page contexts
	 *
	 * Stores tab definitions organized by page context. Each context
	 * contains an array of tabs with their properties including title,
	 * URL, icon, and visibility conditions.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private array $tab_configs = array();

	/**
	 * Current plugin state
	 *
	 * Stores information about the plugin's current configuration state
	 * to determine which tabs should be visible.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	private array $plugin_state = array();

	/**
	 * Constructor
	 *
	 * Initializes the tabs system and sets up the plugin state.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->determine_plugin_state();
		$this->init_tab_configs();
	}

	/**
	 * Determine current plugin state
	 *
	 * Checks various plugin settings to determine which tabs should be
	 * visible and what badges or indicators should be shown.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function determine_plugin_state(): void {
		// Check API configuration
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$this->plugin_state['has_api'] = ! empty( $api_tokens );

		// Get current mode
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$this->plugin_state['current_mode'] = $mode_manager->get_current_mode();

		// Check if consultation is enabled
		$this->plugin_state['consultations_enabled'] = get_option(
			'brag_book_gallery_consultation_enabled',
			false
		);

		// Check for updates
		$this->plugin_state['update_available'] = get_option(
			'brag_book_gallery_update_available',
			false
		);
	}

	/**
	 * Initialize tab configurations
	 *
	 * Sets up tab definitions for different page contexts. Each context
	 * represents a different admin page that may have its own set of tabs.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function init_tab_configs(): void {
		// Main settings tabs
		$this->tab_configs['settings'] = $this->get_main_settings_tabs();

		// Mode-specific tabs
		$this->tab_configs['default'] = $this->get_default_tabs();
		$this->tab_configs['local'] = $this->get_local_tabs();

		// API tabs
		$this->tab_configs['api'] = $this->get_api_tabs();

		// Help tabs
		$this->tab_configs['help'] = $this->get_help_tabs();
	}

	/**
	 * Get main settings tabs
	 *
	 * Returns the tab configuration for the main settings pages.
	 *
	 * @since 3.0.0
	 * @return array Tab configuration
	 */
	private function get_main_settings_tabs(): array {
		$tabs = array(
			'dashboard' => array(
				'title' => __( 'Dashboard', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'icon' => '',
				'visible' => true,
			),
			'api' => array(
				'title' => __( 'API', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon' => '',
				'visible' => true,
				'badge' => ! $this->plugin_state['has_api'] ? '!' : '',
			),
			'general' => array(
				'title' => __( 'General', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-general' ),
				'icon' => '',
				'visible' => true,
			),
		);

		// Add mode-specific settings tab if API is configured - Local mode only when available
		if ( $this->plugin_state['has_api'] ) {
			// Add mode-specific settings tab - only Local mode (currently disabled/coming soon)
			if ( $this->plugin_state['current_mode'] === 'local' ) {
				$tabs['local'] = array(
					'title' => __( 'Local', 'brag-book-gallery' ),
					'url' => admin_url( 'admin.php?page=brag-book-gallery-local' ),
					'icon' => '',
					'visible' => true,
				);
			}

		}

		// Add consultation tab (always visible, settings inside control features)
		$tabs['consultation'] = array(
			'title' => __( 'Consultations', 'brag-book-gallery' ),
			'url' => admin_url( 'admin.php?page=brag-book-gallery-consultation' ),
			'icon' => '',
			'visible' => true,
		);

		// Always add debug and help tabs
		$tabs['debug'] = array(
			'title' => __( 'Debug', 'brag-book-gallery' ),
			'url' => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
			'icon' => '',
			'visible' => true,
		);

		$tabs['help'] = array(
			'title' => __( 'Help', 'brag-book-gallery' ),
			'url' => admin_url( 'admin.php?page=brag-book-gallery-help' ),
			'icon' => '',
			'visible' => true,
		);

		return $tabs;
	}

	/**
	 * Get Default mode tabs
	 *
	 * Returns tab configuration for default mode settings.
	 *
	 * @since 3.0.0
	 * @return array Tab configuration
	 */
	private function get_default_tabs(): array {
		return array(
			'display' => array(
				'title' => __( 'Display Settings', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-default&tab=display' ),
				'icon' => 'dashicons-layout',
				'visible' => true,
			),
			'performance' => array(
				'title' => __( 'Performance', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-default&tab=performance' ),
				'icon' => 'dashicons-performance',
				'visible' => true,
			),
			'advanced' => array(
				'title' => __( 'Advanced', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-default&tab=advanced' ),
				'icon' => 'dashicons-admin-generic',
				'visible' => true,
			),
		);
	}

	/**
	 * Get Local mode tabs
	 *
	 * Returns tab configuration for Local mode settings.
	 *
	 * @since 3.0.0
	 * @return array Tab configuration
	 */
	private function get_local_tabs(): array {
		return array(
			'sync' => array(
				'title' => __( 'Sync Settings', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-local&tab=sync' ),
				'icon' => 'dashicons-update',
				'visible' => true,
			),
			'galleries' => array(
				'title' => __( 'Galleries', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-local&tab=galleries' ),
				'icon' => 'dashicons-format-gallery',
				'visible' => true,
			),
			'import_export' => array(
				'title' => __( 'Import/Export', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-local&tab=import_export' ),
				'icon' => 'dashicons-migrate',
				'visible' => true,
			),
		);
	}

	/**
	 * Get API tabs
	 *
	 * Returns tab configuration for API settings pages.
	 *
	 * @since 3.0.0
	 * @return array Tab configuration
	 */
	private function get_api_tabs(): array {
		return array(
			'credentials' => array(
				'title' => __( 'Credentials', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-api-settings&tab=credentials' ),
				'icon' => 'dashicons-admin-network',
				'visible' => true,
			),
			'endpoints' => array(
				'title' => __( 'Endpoints', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-api-settings&tab=endpoints' ),
				'icon' => 'dashicons-rest-api',
				'visible' => $this->plugin_state['has_api'],
			),
			'logs' => array(
				'title' => __( 'Logs', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-api-settings&tab=logs' ),
				'icon' => 'dashicons-text-page',
				'visible' => $this->plugin_state['has_api'],
			),
		);
	}

	/**
	 * Get help tabs
	 *
	 * Returns tab configuration for help pages.
	 *
	 * @since 3.0.0
	 * @return array Tab configuration
	 */
	private function get_help_tabs(): array {
		return array(
			'documentation' => array(
				'title' => __( 'Documentation', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-help&tab=documentation' ),
				'icon' => 'dashicons-book',
				'visible' => true,
			),
			'shortcodes' => array(
				'title' => __( 'Shortcodes', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-help&tab=shortcodes' ),
				'icon' => 'dashicons-editor-code',
				'visible' => true,
			),
			'support' => array(
				'title' => __( 'Support', 'brag-book-gallery' ),
				'url' => admin_url( 'admin.php?page=brag-book-gallery-help&tab=support' ),
				'icon' => 'dashicons-sos',
				'visible' => true,
			),
		);
	}

	/**
	 * Render tabs for a specific context
	 *
	 * Outputs the HTML for navigation tabs based on the specified context
	 * and current active tab.
	 *
	 * @since 3.0.0
	 * @param string $context The tab context (e.g., 'settings', 'api')
	 * @param string $current The current active tab key
	 * @param array $custom_tabs Optional custom tab configuration
	 * @return void
	 */
	public function render_tabs( string $context = 'settings', string $current = '', array $custom_tabs = array() ): void {
		// Use custom tabs if provided, otherwise get from config
		$tabs = ! empty( $custom_tabs ) ? $custom_tabs : ( $this->tab_configs[ $context ] ?? array() );

		if ( empty( $tabs ) ) {
			return;
		}

		// Filter out invisible tabs
		$tabs = array_filter( $tabs, function( $tab ) {
			return ! isset( $tab['visible'] ) || $tab['visible'] === true;
		});

		?>
		<nav class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<li class="brag-book-gallery-tab-item <?php echo $current === $key ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( $tab['url'] ); ?>"
						   class="brag-book-gallery-tab-link">
							<?php if ( ! empty( $tab['icon'] ) ) : ?>
								<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<?php endif; ?>
							<span class="brag-book-gallery-tab-title">
								<?php echo esc_html( $tab['title'] ); ?>
							</span>
							<?php if ( ! empty( $tab['badge'] ) ) : ?>
								<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="var(--brag-book-gallery-primary)"><path d="M480-290.77q13.73 0 23.02-9.29t9.29-23.02q0-13.73-9.29-23.02-9.29-9.28-23.02-9.28t-23.02 9.28q-9.29 9.29-9.29 23.02t9.29 23.02q9.29 9.29 23.02 9.29Zm-30-146.15h60v-240h-60v240ZM480.07-100q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.92-148.21t81.21-120.68q51.29-51.31 120.63-81.25Q401.1-860 479.93-860q78.84 0 148.21 29.92t120.68 81.21q51.31 51.29 81.25 120.63Q860-558.9 860-480.07q0 78.84-29.92 148.21t-81.21 120.68q-51.29 51.31-120.63 81.25Q558.9-100 480.07-100Z"/></svg>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Render WordPress-style nav tabs
	 *
	 * Renders tabs using WordPress native nav-tab styling for consistency
	 * with core WordPress admin interfaces.
	 *
	 * @since 3.0.0
	 * @param array $tabs Tab configuration array
	 * @param string $current Current active tab
	 * @return void
	 */
	public function render_nav_tabs( array $tabs, string $current = '' ): void {
		if ( empty( $tabs ) ) {
			return;
		}

		?>
		<nav class="nav-tab-wrapper brag-book-gallery-nav-tabs">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<?php
				if ( isset( $tab['visible'] ) && ! $tab['visible'] ) {
					continue;
				}
				?>
				<a href="<?php echo esc_url( $tab['url'] ); ?>"
				   class="nav-tab <?php echo $current === $key ? 'nav-tab-active' : ''; ?>">
					<?php if ( ! empty( $tab['icon'] ) ) : ?>
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
					<?php endif; ?>
					<?php echo esc_html( $tab['title'] ); ?>
					<?php if ( ! empty( $tab['badge'] ) ) : ?>
						<span class="brag-book-gallery-badge"><?php echo esc_html( $tab['badge'] ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Get tab configuration for a specific context
	 *
	 * Returns the tab configuration array for the specified context.
	 *
	 * @since 3.0.0
	 * @param string $context The tab context
	 * @return array Tab configuration
	 */
	public function get_tabs( string $context ): array {
		return $this->tab_configs[ $context ] ?? array();
	}

	/**
	 * Add custom tab to a context
	 *
	 * Allows dynamic addition of tabs to existing contexts.
	 *
	 * @since 3.0.0
	 * @param string $context The tab context
	 * @param string $key The tab key
	 * @param array $tab Tab configuration
	 * @return void
	 */
	public function add_tab( string $context, string $key, array $tab ): void {
		if ( ! isset( $this->tab_configs[ $context ] ) ) {
			$this->tab_configs[ $context ] = array();
		}

		$this->tab_configs[ $context ][ $key ] = $tab;
	}

	/**
	 * Remove tab from a context
	 *
	 * Removes a specific tab from a context.
	 *
	 * @since 3.0.0
	 * @param string $context The tab context
	 * @param string $key The tab key to remove
	 * @return void
	 */
	public function remove_tab( string $context, string $key ): void {
		if ( isset( $this->tab_configs[ $context ][ $key ] ) ) {
			unset( $this->tab_configs[ $context ][ $key ] );
		}
	}

	/**
	 * Update plugin state
	 *
	 * Refreshes the plugin state and rebuilds tab configurations.
	 * Useful when plugin settings change that affect tab visibility.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function refresh_state(): void {
		$this->determine_plugin_state();
		$this->init_tab_configs();
	}

	/**
	 * Check if a specific tab should be visible
	 *
	 * Determines if a tab should be shown based on current plugin state.
	 *
	 * @since 3.0.0
	 * @param string $context The tab context
	 * @param string $key The tab key
	 * @return bool True if tab should be visible
	 */
	public function is_tab_visible( string $context, string $key ): bool {
		if ( ! isset( $this->tab_configs[ $context ][ $key ] ) ) {
			return false;
		}

		$tab = $this->tab_configs[ $context ][ $key ];
		return ! isset( $tab['visible'] ) || $tab['visible'] === true;
	}

	/**
	 * Get active tab from URL
	 *
	 * Determines the active tab based on URL parameters.
	 *
	 * @since 3.0.0
	 * @param string $default Default tab if none specified
	 * @param string $param URL parameter name
	 * @return string Active tab key
	 */
	public function get_active_tab( string $default = '', string $param = 'tab' ): string {
		return isset( $_GET[ $param ] ) ? sanitize_text_field( $_GET[ $param ] ) : $default;
	}
}
