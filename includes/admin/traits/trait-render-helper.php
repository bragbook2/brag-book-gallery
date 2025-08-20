<?php
/**
 * Render Helper Trait - Common rendering functionality for admin classes
 *
 * This trait provides reusable rendering methods for common UI elements
 * like headers, footers, navigation tabs, and notices.
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin\Traits
 * @since      3.0.0
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace BRAGBookGallery\Includes\Admin\Traits;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
	die( 'Restricted Access' );
}

/**
 * Trait Render_Helper
 *
 * Provides common rendering functionality for admin classes.
 * Includes methods for headers, footers, navigation, and UI elements.
 *
 * @since 3.0.0
 */
trait Trait_Render_Helper {

	/**
	 * Render admin page header
	 *
	 * Outputs the standard page header with logo, title, and version badge.
	 * Creates consistent branding across all admin pages.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @param string $page_title Optional page title to display.
	 *
	 * @return void
	 */
	protected function render_page_header( string $page_title = '' ): void {
		// Get plugin version.
		$plugin_data = $this->get_plugin_data();
		$version = $plugin_data['Version'] ?? '3.0.0';

		if ( empty( $page_title ) ) {
			$page_title = __( 'BRAG book Gallery', 'brag-book-gallery' );
		}
		?>
		<div class="wrap brag-book-gallery-admin-wrap">
			<div class="brag-book-gallery-header">
				<div class="brag-book-gallery-header-left">
					<img
						src="<?php echo esc_url( $this->get_asset_url( 'images/bragbook-logo.svg' ) ); ?>"
						alt="BRAG book" class="brag-book-gallery-logo"/>
					<h1><?php echo esc_html( $page_title ); ?></h1>
				</div>
				<div class="brag-book-gallery-header-right">
					<span class="brag-book-gallery-version-badge">
						<?php echo esc_html( 'v' . $version ); ?>
					</span>
				</div>
			</div>
		<?php
	}

	/**
	 * Render admin page footer
	 *
	 * Closes the standard page wrapper elements.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function render_page_footer(): void {
		?>
			</div><!-- .brag-book-gallery-content -->
		</div><!-- .wrap -->
		<?php
	}

	/**
	 * Render navigation tabs
	 *
	 * Outputs navigation tabs with intelligent visibility based on plugin state.
	 * Supports active state highlighting and conditional tab display.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @param string $current_tab Currently active tab identifier.
	 * @param array  $tabs        Optional custom tabs array.
	 *
	 * @return void
	 */
	protected function render_tabs( string $current_tab = '', array $tabs = array() ): void {
		// Use default tabs if none provided.
		if ( empty( $tabs ) ) {
			$tabs = $this->get_default_tabs();
		}

		// Filter tabs based on visibility conditions.
		$tabs = $this->filter_visible_tabs( $tabs );

		// Check API configuration for badge display.
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api = ! empty( $api_tokens );
		?>
		<nav class="brag-book-gallery-tabs">
			<ul class="brag-book-gallery-tab-list">
				<?php foreach ( $tabs as $key => $tab ) : ?>
					<li class="brag-book-gallery-tab-item <?php echo $current_tab === $key ? 'active' : ''; ?>">
						<a href="<?php echo esc_url( $tab['url'] ); ?>"
						   class="brag-book-gallery-tab-link">
							<?php if ( ! empty( $tab['icon'] ) ) : ?>
								<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<?php endif; ?>
							<span class="brag-book-gallery-tab-title">
								<?php echo esc_html( $tab['title'] ); ?>
							</span>
							<?php if ( $key === 'api' && ! $has_api ) : ?>
								<span class="brag-book-gallery-tab-badge">!</span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Get default navigation tabs
	 *
	 * Returns the standard set of navigation tabs for settings pages.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @return array Default tabs configuration
	 */
	protected function get_default_tabs(): array {
		return array(
			'general' => array(
				'title' => __( 'General', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-settings' ),
				'icon'  => 'dashicons-admin-settings',
			),
			'api' => array(
				'title' => __( 'API', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-api-settings' ),
				'icon'  => 'dashicons-admin-network',
			),
			'mode' => array(
				'title' => __( 'Mode', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-mode' ),
				'icon'  => 'dashicons-admin-generic',
			),
			'help' => array(
				'title' => __( 'Help', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-help' ),
				'icon'  => 'dashicons-sos',
			),
			'debug' => array(
				'title' => __( 'Debug', 'brag-book-gallery' ),
				'url'   => admin_url( 'admin.php?page=brag-book-gallery-debug' ),
				'icon'  => 'dashicons-admin-tools',
			),
		);
	}

	/**
	 * Filter visible tabs based on plugin state
	 *
	 * Removes tabs that shouldn't be visible based on current configuration.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @param array $tabs Tabs to filter.
	 *
	 * @return array Filtered tabs array
	 */
	protected function filter_visible_tabs( array $tabs ): array {
		// Get plugin state.
		$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
		$has_api = ! empty( $api_tokens );

		// Get current mode.
		$current_mode = get_option( 'brag_book_gallery_mode', 'javascript' );

		// Remove mode tab if no API.
		if ( ! $has_api && isset( $tabs['mode'] ) ) {
			unset( $tabs['mode'] );
		}

		// Add mode-specific tabs.
		if ( $has_api ) {
			if ( $current_mode === 'javascript' ) {
				$tabs['javascript'] = array(
					'title' => __( 'JavaScript', 'brag-book-gallery' ),
					'url'   => admin_url( 'admin.php?page=brag-book-gallery-javascript' ),
					'icon'  => 'dashicons-media-code',
				);
			} elseif ( $current_mode === 'local' ) {
				$tabs['local'] = array(
					'title' => __( 'Local', 'brag-book-gallery' ),
					'url'   => admin_url( 'admin.php?page=brag-book-gallery-local' ),
					'icon'  => 'dashicons-database',
				);
			}
		}

		return $tabs;
	}

	/**
	 * Render admin notice
	 *
	 * Outputs a WordPress admin notice with specified type and message.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @param string $message     Notice message to display.
	 * @param string $type        Notice type (success, error, warning, info).
	 * @param bool   $dismissible Whether notice should be dismissible.
	 *
	 * @return void
	 */
	protected function render_notice( string $message, string $type = 'success', bool $dismissible = true ): void {
		$classes = array( 'notice', 'notice-' . $type );

		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<p><?php echo wp_kses_post( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render section header
	 *
	 * Outputs a consistent section header with title and optional description.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @param string $title       Section title.
	 * @param string $description Optional section description.
	 *
	 * @return void
	 */
	protected function render_section_header( string $title, string $description = '' ): void {
		?>
		<div class="brag-book-gallery-section">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! empty( $description ) ) : ?>
				<p class="description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		<?php
	}

	/**
	 * Render section footer
	 *
	 * Closes a section div.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @return void
	 */
	protected function render_section_footer(): void {
		?>
		</div><!-- .brag-book-gallery-section -->
		<?php
	}

	/**
	 * Get plugin data
	 *
	 * Retrieves plugin information from the main plugin file.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @return array Plugin data array
	 */
	protected function get_plugin_data(): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php';

		if ( file_exists( $plugin_file ) ) {
			return get_plugin_data( $plugin_file );
		}

		return array(
			'Version' => '3.0.0',
			'Name'    => 'BRAG book Gallery',
		);
	}

	/**
	 * Get asset URL
	 *
	 * Returns the full URL for a plugin asset file.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @param string $path Relative path to asset from assets directory.
	 *
	 * @return string Full asset URL
	 */
	protected function get_asset_url( string $path ): string {
		return plugin_dir_url( dirname( __DIR__, 2 ) ) . 'assets/' . $path;
	}

	/**
	 * Check if current page is plugin admin page
	 *
	 * Determines if the current admin page belongs to this plugin.
	 *
	 * @since 3.0.0
	 * @access protected
	 *
	 * @return bool True if on plugin admin page, false otherwise
	 */
	protected function is_plugin_admin_page(): bool {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, 'brag-book-gallery' ) !== false;
	}
}
