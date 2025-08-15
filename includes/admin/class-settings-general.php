<?php
/**
 * General Settings Class - Manages general plugin settings
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
 * General Settings Class
 *
 * Provides the main dashboard and overview interface for BRAG Book Gallery.
 * This class serves as the primary landing page for plugin administration,
 * offering users a centralized view of plugin status, quick actions, and
 * essential information.
 *
 * Key functionality:
 * - Plugin status dashboard with real-time configuration feedback
 * - Quick action cards for common administrative tasks
 * - Mode-specific statistics and data visualization
 * - Feature overview and plugin information
 * - Contextual navigation to other settings pages
 * - Progressive disclosure based on configuration state
 *
 * The general settings page adapts its content based on:
 * - Current operating mode (JavaScript vs Local)
 * - API configuration status
 * - Available plugin data and statistics
 *
 * This serves as both an informational dashboard and a navigation hub,
 * guiding users through the plugin setup and ongoing management processes.
 *
 * @since 3.0.0
 */
class Settings_General extends Settings_Base {

	/**
	 * Initialize general settings page configuration
	 *
	 * Establishes the page slug for the main settings page, which serves
	 * as both the default landing page and the general settings interface.
	 * This page uses the primary plugin menu slug to appear as the default
	 * destination when users click the main plugin menu item.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-settings';
		// Don't translate here - translations happen in render method
	}

	/**
	 * Render the comprehensive plugin dashboard and overview interface
	 *
	 * Generates the main settings page that serves as both a dashboard and
	 * navigation hub for BRAG Book Gallery administration. The interface
	 * provides multiple information-rich sections:
	 *
	 * **Dashboard Sections:**
	 * 1. Welcome Section - Plugin introduction and overview
	 * 2. Plugin Status - Current configuration and operational state
	 * 3. Quick Statistics - Mode-specific data summaries (Local mode only)
	 * 4. Quick Actions - Contextual action cards for common tasks
	 * 5. About Section - Feature list and plugin information
	 *
	 * **Adaptive Content:**
	 * - Status cards reflect real-time configuration state
	 * - Action cards change based on API setup and current mode
	 * - Statistics appear only in Local mode with available data
	 * - Navigation prompts guide users through setup process
	 *
	 * **User Experience Features:**
	 * - Visual status indicators with color-coded feedback
	 * - Contextual action buttons that adapt to current state
	 * - Progressive disclosure that shows relevant options only
	 * - Clear calls-to-action for next steps in setup
	 *
	 * The page serves as a single source of truth for plugin status
	 * and provides intuitive pathways for all administrative tasks.
	 *
	 * @since 3.0.0
	 * @return void Outputs HTML dashboard directly to browser
	 */
	public function render(): void {
		// Set localized page titles now that translation functions are available
		$this->page_title = __( 'BRAG Book Gallery', 'brag-book-gallery' );
		$this->menu_title = __( 'General', 'brag-book-gallery' );

		// Handle form submission
		if ( isset( $_POST['submit'] ) && $this->save_settings( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' ) ) {
			$this->save_general_settings();
		}

		// Retrieve plugin metadata from main plugin file
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
		$version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '3.0.0';

		// Get current operational mode and mode manager for status display
		$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
		$current_mode = $mode_manager->get_current_mode();

		$this->render_header();
		?>

		<!-- Welcome Section -->
		<div class="brag-book-gallery-section">
			<div class="brag-book-gallery-welcome">
				<h2><?php esc_html_e( 'Welcome to BRAG Book Gallery', 'brag-book-gallery' ); ?></h2>
				<p class="about-description">
					<?php esc_html_e( 'The most powerful before & after gallery plugin for WordPress. Display stunning medical and cosmetic procedure galleries with ease.', 'brag-book-gallery' ); ?>
				</p>
			</div>
		</div>

		<!-- Plugin Status -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Plugin Status', 'brag-book-gallery' ); ?></h2>
			<div class="brag-book-gallery-stats-grid">
				<div class="stat-card">
					<div class="stat-header">
						<span class="dashicons dashicons-admin-plugins"></span>
						<span class="stat-label"><?php esc_html_e( 'Version', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<?php echo esc_html( $version ); ?>
						<?php if ( get_option( 'brag_book_gallery_update_available', false ) ) : ?>
							<span class="update-badge"><?php esc_html_e( 'Update Available', 'brag-book-gallery' ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-header">
						<span class="dashicons dashicons-admin-generic"></span>
						<span class="stat-label"><?php esc_html_e( 'Active Mode', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<span class="mode-badge mode-<?php echo esc_attr( $current_mode ); ?>">
							<?php echo esc_html( ucfirst( $current_mode ) ); ?>
						</span>
						<?php
						$api_tokens = get_option( 'brag_book_gallery_api_token', array() );
						if ( ! empty( $api_tokens ) ) :
						?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-mode' ) ); ?>" class="button button-secondary button-small">
								<?php esc_html_e( 'Change', 'brag-book-gallery' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-header">
						<span class="dashicons dashicons-admin-network"></span>
						<span class="stat-label"><?php esc_html_e( 'API Connection', 'brag-book-gallery' ); ?></span>
					</div>
					<div class="stat-value">
						<?php
						if ( ! empty( $api_tokens ) ) {
							echo '<span class="status-badge status-success">Configured</span>';
						} else {
							echo '<span class="status-badge status-warning">Not Configured</span>';
						}
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>" class="button button-secondary button-small">
							<?php echo ! empty( $api_tokens ) ? esc_html__( 'Manage', 'brag-book-gallery' ) : esc_html__( 'Configure', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Quick Stats -->
		<?php if ( $current_mode === 'local' ) : ?>
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Quick Statistics', 'brag-book-gallery' ); ?></h2>
			<div class="brag-book-gallery-stats-grid">
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_posts( 'brag_gallery' )->publish ?? 0 ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Published Galleries', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_terms( 'brag_category' ) ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Categories', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php echo esc_html( wp_count_terms( 'brag_procedure' ) ); ?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Procedures', 'brag-book-gallery' ); ?></div>
				</div>
				<div class="stat-card">
					<div class="stat-value">
						<?php
						$last_sync = get_option( 'brag_book_gallery_last_sync', '' );
						if ( $last_sync ) {
							echo esc_html( human_time_diff( strtotime( $last_sync ), current_time( 'timestamp' ) ) );
						} else {
							esc_html_e( 'Never', 'brag-book-gallery' );
						}
						?>
					</div>
					<div class="stat-label"><?php esc_html_e( 'Last Sync', 'brag-book-gallery' ); ?></div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Quick Actions -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'Quick Actions', 'brag-book-gallery' ); ?></h2>
			<div class="action-cards-grid">
				<?php if ( ! empty( $api_tokens ) ) : ?>
					<?php if ( $current_mode === 'javascript' ) : ?>
					<div class="action-card">
						<div class="action-card-icon">
							<span class="dashicons dashicons-media-code"></span>
						</div>
						<div class="action-card-content">
							<h3><?php esc_html_e( 'JavaScript Settings', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Configure gallery display and performance settings.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-javascript' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure Settings', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>
					<?php else : ?>
					<div class="action-card">
						<div class="action-card-icon">
							<span class="dashicons dashicons-database"></span>
						</div>
						<div class="action-card-content">
							<h3><?php esc_html_e( 'Local Settings', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Manage sync settings and local data.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-local' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure Settings', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>
					<?php endif; ?>

					<div class="action-card">
						<div class="action-card-icon">
							<span class="dashicons dashicons-forms"></span>
						</div>
						<div class="action-card-content">
							<h3><?php esc_html_e( 'Consultations', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'View and manage consultation form submissions.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-consultation' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'View Consultations', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>
				<?php else : ?>
					<div class="action-card featured">
						<div class="action-card-icon">
							<span class="dashicons dashicons-admin-network"></span>
						</div>
						<div class="action-card-content">
							<h3><?php esc_html_e( 'API Configuration Required', 'brag-book-gallery' ); ?></h3>
							<p><?php esc_html_e( 'Configure your BRAG Book API connection to get started with galleries and mode settings.', 'brag-book-gallery' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-api-settings' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure API', 'brag-book-gallery' ); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>

				<div class="action-card">
					<div class="action-card-icon">
						<span class="dashicons dashicons-sos"></span>
					</div>
					<div class="action-card-content">
						<h3><?php esc_html_e( 'Help & Support', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Get help with shortcodes, setup guides, and documentation.', 'brag-book-gallery' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-help' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View Help', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- About Section -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'About BRAG Book Gallery', 'brag-book-gallery' ); ?></h2>
			<div class="about-content">
				<p>
					<?php esc_html_e( 'BRAG Book Gallery is designed specifically for medical and cosmetic professionals to showcase their work through stunning before & after galleries. With dual-mode functionality, you can choose between real-time API-driven content or locally stored galleries for maximum flexibility.', 'brag-book-gallery' ); ?>
				</p>
				<h3><?php esc_html_e( 'Key Features', 'brag-book-gallery' ); ?></h3>
				<ul class="feature-list">
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Dual-mode operation (JavaScript/Local)', 'brag-book-gallery' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Responsive gallery layouts', 'brag-book-gallery' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'SEO optimized', 'brag-book-gallery' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Category and procedure filtering', 'brag-book-gallery' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Consultation form integration', 'brag-book-gallery' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Automatic updates', 'brag-book-gallery' ); ?></li>
				</ul>
			</div>
		</div>

		<!-- General Settings Form -->
		<div class="brag-book-gallery-section">
			<h2><?php esc_html_e( 'General Settings', 'brag-book-gallery' ); ?></h2>
			<?php settings_errors( $this->page_slug ); ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'brag_book_gallery_general_settings', 'brag_book_gallery_general_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="brag_book_gallery_enable_sharing">
								<?php esc_html_e( 'Enable Sharing', 'brag-book-gallery' ); ?>
							</label>
						</th>
						<td>
							<?php
							$enable_sharing = get_option( 'brag_book_gallery_enable_sharing', 'no' );
							?>
							<input type="hidden" name="brag_book_gallery_enable_sharing" value="no" />
							<input type="checkbox"
								   id="brag_book_gallery_enable_sharing"
								   name="brag_book_gallery_enable_sharing"
								   value="yes"
								   <?php checked( $enable_sharing, 'yes' ); ?> />
							<p class="description">
								<?php esc_html_e( 'Allow users to share gallery cases via social media and other platforms.', 'brag-book-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'brag-book-gallery' ) ); ?>
			</form>
		</div>
		<?php
		$this->render_footer();
	}

	/**
	 * Save general settings
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function save_general_settings(): void {
		// Save enable sharing setting
		$enable_sharing = isset( $_POST['brag_book_gallery_enable_sharing'] ) ? sanitize_text_field( $_POST['brag_book_gallery_enable_sharing'] ) : 'no';
		update_option( 'brag_book_gallery_enable_sharing', $enable_sharing );

		$this->add_notice( __( 'General settings saved successfully.', 'brag-book-gallery' ), 'success' );
	}

}
