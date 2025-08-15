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
 * Comprehensive help documentation and support resource center for BRAG Book Gallery.
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
						<li><?php esc_html_e( 'Get your API Token and Website Property ID from your BRAG Book account', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Configure API credentials in the API Settings page', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Set your Gallery Slug in General Settings (e.g., "gallery" or "before-after")', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Create a page and add the [brag_book_gallery] shortcode', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Customize JavaScript Settings as needed (caching, landing page text)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules', 'brag-book-gallery' ); ?></li>
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
							<td><code>[brag_book_gallery]</code></td>
							<td><?php esc_html_e( 'Main gallery with filtering and pagination', 'brag-book-gallery' ); ?></td>
							<td>
								<code>website_property_id</code> - <?php esc_html_e( 'Override global Website Property ID', 'brag-book-gallery' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>[brag_book_carousel]</code></td>
							<td><?php esc_html_e( 'Image carousel display', 'brag-book-gallery' ); ?></td>
							<td>
								<code>api_token</code> - <?php esc_html_e( 'API Token (required)', 'brag-book-gallery' ); ?><br>
								<code>website_property_id</code> - <?php esc_html_e( 'Website Property ID', 'brag-book-gallery' ); ?><br>
								<code>member_id</code> - <?php esc_html_e( 'Filter by member ID', 'brag-book-gallery' ); ?><br>
								<code>procedure_id</code> - <?php esc_html_e( 'Filter by procedure ID', 'brag-book-gallery' ); ?><br>
								<code>limit</code> - <?php esc_html_e( 'Number of items (default: 10)', 'brag-book-gallery' ); ?><br>
								<code>show_controls</code> - <?php esc_html_e( 'Show nav controls (true/false)', 'brag-book-gallery' ); ?><br>
								<code>show_pagination</code> - <?php esc_html_e( 'Show dots (true/false)', 'brag-book-gallery' ); ?><br>
								<code>auto_play</code> - <?php esc_html_e( 'Auto advance (true/false)', 'brag-book-gallery' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>[brag_book_gallery_cases]</code></td>
							<td><?php esc_html_e( 'Display cases in grid layout', 'brag-book-gallery' ); ?></td>
							<td>
								<code>website_property_id</code> - <?php esc_html_e( 'Override global Website Property ID', 'brag-book-gallery' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>[brag_book_gallery_case]</code></td>
							<td><?php esc_html_e( 'Display single case details', 'brag-book-gallery' ); ?></td>
							<td>
								<code>case_id</code> - <?php esc_html_e( 'Specific case ID to display', 'brag-book-gallery' ); ?><br>
								<code>website_property_id</code> - <?php esc_html_e( 'Override global Website Property ID', 'brag-book-gallery' ); ?>
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
							<td><?php esc_html_e( 'Built-in SEO features', 'brag-book-gallery' ); ?></td>
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
					<h3><?php esc_html_e( 'How do I get my API Token and Website Property ID?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Log into your BRAG Book account at app.bragbookgallery.com, go to Settings → API, and copy your API Token and Website Property ID.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'What is the Gallery Slug setting?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'The Gallery Slug determines your URL structure. For example, if set to "before-after", your gallery URLs will be yoursite.com/before-after/procedure-name/.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Why are my procedure URLs returning 404 errors?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'This usually means rewrite rules need to be flushed. Go to Settings → Permalinks and click "Save Changes", or use the diagnostic tools in the Troubleshooting section above.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'How do I enable nudity warnings?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'In JavaScript Settings, enable "Nudity Warning" to show an overlay on images that require user acceptance before viewing.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Can I customize the gallery appearance?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Yes! The plugin includes CSS classes for styling. You can also add custom CSS through your theme or the WordPress Customizer.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'How does caching work?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'The plugin caches API responses for 1 hour in production (1 minute in debug mode). You can clear cache from JavaScript Settings or use the diagnostic tools.', 'brag-book-gallery' ); ?></p>
				</div>
			</div>

			<!-- Troubleshooting -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Troubleshooting', 'brag-book-gallery' ); ?></h2>
				
				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'URLs Returning 404 Errors', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'If your gallery procedure or case URLs are returning 404 errors, use these diagnostic tools:', 'brag-book-gallery' ); ?></p>
					
					<div class="brag-book-gallery-diagnostic-tools">
						<h4><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li>
								<strong><a href="<?php echo esc_url( content_url( 'plugins/brag-book-gallery/check-gallery-page.php' ) ); ?>" target="_blank">
									<?php esc_html_e( 'Gallery Page Checker', 'brag-book-gallery' ); ?>
								</a></strong><br>
								<?php esc_html_e( 'Verify your gallery page exists and is configured correctly. Can automatically create missing pages.', 'brag-book-gallery' ); ?>
							</li>
							<li>
								<strong><a href="<?php echo esc_url( content_url( 'plugins/brag-book-gallery/debug-rewrite.php' ) ); ?>" target="_blank">
									<?php esc_html_e( 'Rewrite Rules Debugger', 'brag-book-gallery' ); ?>
								</a></strong><br>
								<?php esc_html_e( 'View all active rewrite rules and test if your URLs match any patterns.', 'brag-book-gallery' ); ?>
							</li>
							<li>
								<strong><a href="<?php echo esc_url( content_url( 'plugins/brag-book-gallery/flush-rewrite-rules.php' ) ); ?>" target="_blank">
									<?php esc_html_e( 'Flush Rewrite Rules', 'brag-book-gallery' ); ?>
								</a></strong><br>
								<?php esc_html_e( 'Force WordPress to regenerate all rewrite rules. Use after changing gallery slug.', 'brag-book-gallery' ); ?>
							</li>
							<li>
								<strong><a href="<?php echo esc_url( content_url( 'plugins/brag-book-gallery/fix-live-rewrites.php' ) ); ?>" target="_blank">
									<?php esc_html_e( 'Live Site Fix Tool', 'brag-book-gallery' ); ?>
								</a></strong><br>
								<?php esc_html_e( 'Comprehensive diagnostic for production sites. Checks server config, .htaccess, and applies automatic fixes.', 'brag-book-gallery' ); ?>
							</li>
						</ul>
						<p class="description">
							<strong><?php esc_html_e( 'Note:', 'brag-book-gallery' ); ?></strong> 
							<?php esc_html_e( 'You must be logged in as an administrator to access these tools.', 'brag-book-gallery' ); ?>
						</p>
					</div>
					
					<h4><?php esc_html_e( 'Quick Fixes', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Go to Settings → Permalinks and click "Save Changes"', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Verify your gallery slug matches an existing page with [brag_book_gallery] shortcode', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Clear all caches (browser, CDN, hosting, plugins)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'For Nginx servers, manually add rewrite rules to nginx.conf', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Gallery Not Displaying', 'brag-book-gallery' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Verify API credentials are correct in API Settings', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Check Website Property ID is set correctly', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Clear API cache from JavaScript Settings tab', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Ensure galleries exist in your BRAG Book account', 'brag-book-gallery' ); ?></li>
					</ul>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Performance Issues', 'brag-book-gallery' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Enable caching in JavaScript Settings', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Use progressive loading for large galleries', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Optimize images in BRAG Book account', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Consider using a CDN for faster delivery', 'brag-book-gallery' ); ?></li>
					</ul>
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
