<?php
/**
 * Help Settings Class
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
 * Help Settings Class
 *
 * Comprehensive help documentation and support resource center for BragBook Gallery.
 * This class provides users with essential information, guides, and support resources
 * to effectively use and troubleshoot the plugin.
 * 
 * **Documentation Sections:**
 * - Quick setup guide with step-by-step instructions
 * - Complete shortcode reference with parameters
 * - Mode comparison table (JavaScript vs Local)
 * - Frequently asked questions with solutions
 * - System requirements and compatibility information
 * 
 * **Support Resources:**
 * - Direct links to online documentation
 * - Support contact information and channels
 * - Plugin update information and changelog access
 * - Community resources and forums
 * 
 * **User Experience Features:**
 * - Interactive FAQ section with common scenarios
 * - Visual comparison tables for mode selection
 * - Copy-paste ready shortcode examples
 * - System information display for support requests
 * - Direct links to relevant settings pages
 * 
 * This class serves as the primary self-service support interface,
 * reducing support requests by providing comprehensive documentation.
 *
 * @since 3.0.0
 */
class Settings_Help extends Settings_Base {

	/**
	 * Initialize the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug  = 'brag-book-gallery-help';
		// Don't translate here - translations happen in render
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering (after init)
		$this->page_title = __( 'Help & Documentation', 'brag-book-gallery' );
		$this->menu_title = __( 'Help', 'brag-book-gallery' );

		$this->render_header();
		?>

		<div class="brag-book-gallery-help-content">
			<!-- Getting Started -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Getting Started', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Quick Setup Guide', 'brag-book-gallery' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Configure your API credentials in the API settings page', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Choose between JavaScript mode (API-driven) or Local mode (WordPress native)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Add the [bragbook_gallery] shortcode to any page or post', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Customize display settings as needed', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>
			</div>

			<!-- Shortcodes -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Available Shortcodes', 'brag-book-gallery' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Shortcode', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Description', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Parameters', 'brag-book-gallery' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>[bragbook_gallery]</code></td>
							<td><?php esc_html_e( 'Display the main gallery', 'brag-book-gallery' ); ?></td>
							<td>
								<code>category</code> - <?php esc_html_e( 'Filter by category', 'brag-book-gallery' ); ?><br>
								<code>procedure</code> - <?php esc_html_e( 'Filter by procedure', 'brag-book-gallery' ); ?><br>
								<code>limit</code> - <?php esc_html_e( 'Number of items to display', 'brag-book-gallery' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>[bragbook_categories]</code></td>
							<td><?php esc_html_e( 'Display category list', 'brag-book-gallery' ); ?></td>
							<td>
								<code>layout</code> - <?php esc_html_e( 'grid or list', 'brag-book-gallery' ); ?><br>
								<code>columns</code> - <?php esc_html_e( 'Number of columns', 'brag-book-gallery' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>[bragbook_procedures]</code></td>
							<td><?php esc_html_e( 'Display procedure list', 'brag-book-gallery' ); ?></td>
							<td>
								<code>category</code> - <?php esc_html_e( 'Filter by category', 'brag-book-gallery' ); ?><br>
								<code>style</code> - <?php esc_html_e( 'Display style', 'brag-book-gallery' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Mode Comparison -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Mode Comparison', 'brag-book-gallery' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'JavaScript Mode', 'brag-book-gallery' ); ?></th>
							<th><?php esc_html_e( 'Local Mode', 'brag-book-gallery' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Data Storage', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'External API', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'WordPress Database', 'brag-book-gallery' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Real-time Updates', 'brag-book-gallery' ); ?></td>
							<td><span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span></td>
							<td><span class="dashicons dashicons-no-alt" style="color:#dc3232;"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'SEO Optimization', 'brag-book-gallery' ); ?></td>
							<td><span class="dashicons dashicons-no-alt" style="color:#dc3232;"></span></td>
							<td><span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Offline Access', 'brag-book-gallery' ); ?></td>
							<td><span class="dashicons dashicons-no-alt" style="color:#dc3232;"></span></td>
							<td><span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Performance', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Depends on API', 'brag-book-gallery' ); ?></td>
							<td><?php esc_html_e( 'Fast (local data)', 'brag-book-gallery' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- FAQs -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Frequently Asked Questions', 'brag-book-gallery' ); ?></h2>
				
				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'How do I switch between modes?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Go to the Mode settings page and select your preferred mode. The plugin will handle the transition automatically.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Can I use both modes simultaneously?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'No, only one mode can be active at a time. However, switching between modes preserves your data.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'How often does Local mode sync with the API?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'You can configure sync frequency in Local mode settings, from manual to hourly, daily, or weekly.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'What happens to my data when I switch modes?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Your data is preserved. Local mode data remains in the database, and you can switch back anytime.', 'brag-book-gallery' ); ?></p>
				</div>
			</div>

			<!-- Support -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Support & Resources', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-grid">
					<div class="brag-book-gallery-card">
						<h3><span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Documentation', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Complete documentation and guides', 'brag-book-gallery' ); ?></p>
						<a href="https://bragbook.com/docs" target="_blank" class="button button-secondary">
							<?php esc_html_e( 'View Documentation', 'brag-book-gallery' ); ?>
						</a>
					</div>

					<div class="brag-book-gallery-card">
						<h3><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Email Support', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Get help from our support team', 'brag-book-gallery' ); ?></p>
						<a href="mailto:support@bragbook.com" class="button button-secondary">
							<?php esc_html_e( 'Contact Support', 'brag-book-gallery' ); ?>
						</a>
					</div>

					<div class="brag-book-gallery-card">
						<h3><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Updates', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Check for plugin updates', 'brag-book-gallery' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View Updates', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- System Information -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'System Information', 'brag-book-gallery' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Plugin Version', 'brag-book-gallery' ); ?></th>
							<td>
								<?php 
								$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
								echo esc_html( $plugin_data['Version'] ?? '3.0.0' );
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WordPress Version', 'brag-book-gallery' ); ?></th>
							<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'PHP Version', 'brag-book-gallery' ); ?></th>
							<td><?php echo esc_html( phpversion() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Active Mode', 'brag-book-gallery' ); ?></th>
							<td>
								<?php 
								$mode_manager = \BRAGBookGallery\Includes\Mode\Mode_Manager::get_instance();
								echo esc_html( ucfirst( $mode_manager->get_current_mode() ) );
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<?php
		$this->render_footer();
	}
}