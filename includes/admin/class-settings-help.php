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
 * Comprehensive help documentation and support resource center for BRAG book Gallery.
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
						<li>
							<strong><?php esc_html_e( 'Get Your API Credentials', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px; margin-top: 5px;">
								<li><?php esc_html_e( 'Log into your BRAG book account at', 'brag-book-gallery' ); ?> <a href="https://app.bragbookgallery.com" target="_blank">app.bragbookgallery.com</a></li>
								<li><?php esc_html_e( 'Navigate to Settings → API', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Copy your API Token and Website Property ID', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Configure API Settings', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px; margin-top: 5px;">
								<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>"><?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?></a></li>
								<li><?php esc_html_e( 'Enter your API Token and Website Property ID', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Save the settings', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Set Gallery Slug', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px; margin-top: 5px;">
								<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ); ?>"><?php esc_html_e( 'General Settings', 'brag-book-gallery' ); ?></a></li>
								<li><?php esc_html_e( 'Set your Gallery Slug (e.g., "gallery", "before-after", "results")', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'This will be the base URL for your gallery pages', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Create Gallery Page', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px; margin-top: 5px;">
								<li><?php esc_html_e( 'Create a new page with the same slug as your Gallery Slug setting', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Add the shortcode:', 'brag-book-gallery' ); ?> <code>[brag_book_gallery]</code></li>
								<li><?php esc_html_e( 'Publish the page', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Flush Permalinks', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px; margin-top: 5px;">
								<li><?php esc_html_e( 'Go to Settings → Permalinks', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Click "Save Changes" (no need to change anything)', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'This ensures your gallery URLs work correctly', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
					</ol>
				</div>
			</div>

			<!-- Shortcodes -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Available Shortcodes', 'brag-book-gallery' ); ?></h2>
				
				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Main Gallery Shortcode', 'brag-book-gallery' ); ?></h3>
					<p><code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">[brag_book_gallery]</code></p>
					<p><?php esc_html_e( 'Displays the full gallery with filtering sidebar, search, and pagination. This is the main shortcode you\'ll use on your gallery page.', 'brag-book-gallery' ); ?></p>
					<p><strong><?php esc_html_e( 'Optional Parameters:', 'brag-book-gallery' ); ?></strong></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><code>website_property_id="123"</code> - <?php esc_html_e( 'Override the global Website Property ID', 'brag-book-gallery' ); ?></li>
					</ul>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Carousel Shortcode', 'brag-book-gallery' ); ?></h3>
					<p><code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">[brag_book_carousel procedure="arm-lift" limit="5"]</code></p>
					<p><?php esc_html_e( 'Displays cases in a carousel/slider format. Perfect for homepage or landing pages.', 'brag-book-gallery' ); ?></p>
					<p><strong><?php esc_html_e( 'Parameters:', 'brag-book-gallery' ); ?></strong></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><code>procedure</code> - <?php esc_html_e( 'Procedure slug to filter by (e.g., "arm-lift", "breast-augmentation")', 'brag-book-gallery' ); ?></li>
						<li><code>procedure_id</code> - <?php esc_html_e( 'Procedure ID to filter by (alternative to slug)', 'brag-book-gallery' ); ?></li>
						<li><code>member_id</code> - <?php esc_html_e( 'Filter by specific member/doctor', 'brag-book-gallery' ); ?></li>
						<li><code>limit</code> - <?php esc_html_e( 'Number of items to display (default: 10)', 'brag-book-gallery' ); ?></li>
						<li><code>show_controls</code> - <?php esc_html_e( 'Show navigation arrows (true/false, default: true)', 'brag-book-gallery' ); ?></li>
						<li><code>show_pagination</code> - <?php esc_html_e( 'Show dots pagination (true/false, default: true)', 'brag-book-gallery' ); ?></li>
						<li><code>autoplay</code> - <?php esc_html_e( 'Auto-advance slides (true/false, default: false)', 'brag-book-gallery' ); ?></li>
						<li><code>autoplay_delay</code> - <?php esc_html_e( 'Delay between slides in ms (default: 3000)', 'brag-book-gallery' ); ?></li>
					</ul>
					<p><strong><?php esc_html_e( 'Legacy Format Support:', 'brag-book-gallery' ); ?></strong></p>
					<p><?php esc_html_e( 'The old shortcode format is still supported:', 'brag-book-gallery' ); ?></p>
					<p><code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">[bragbook_carousel_shortcode procedure="arm-lift" limit="5" title="0" details="0"]</code></p>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Cases Grid Shortcode', 'brag-book-gallery' ); ?></h3>
					<p><code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">[brag_book_gallery_cases]</code></p>
					<p><?php esc_html_e( 'Displays cases in a grid layout without the filtering sidebar. Good for embedding specific cases on other pages.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Single Case Shortcode', 'brag-book-gallery' ); ?></h3>
					<p><code style="display: block; padding: 10px; background: #f0f0f0; margin: 10px 0;">[brag_book_gallery_case case_id="12345"]</code></p>
					<p><?php esc_html_e( 'Displays a single specific case with all its details and images.', 'brag-book-gallery' ); ?></p>
				</div>
			</div>

			<!-- Common Tasks -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Common Tasks', 'brag-book-gallery' ); ?></h2>
				
				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Clear Gallery Cache', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'If your gallery isn\'t showing the latest cases:', 'brag-book-gallery' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=javascript' ) ); ?>"><?php esc_html_e( 'JavaScript Settings', 'brag-book-gallery' ); ?></a></li>
						<li><?php esc_html_e( 'Click the "Clear API Cache" button', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Refresh your gallery page', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Enable Nudity Warnings', 'brag-book-gallery' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=javascript' ) ); ?>"><?php esc_html_e( 'JavaScript Settings', 'brag-book-gallery' ); ?></a></li>
						<li><?php esc_html_e( 'Toggle "Enable Nudity Warning" to ON', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Save settings', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Customize Landing Page', 'brag-book-gallery' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=javascript' ) ); ?>"><?php esc_html_e( 'JavaScript Settings', 'brag-book-gallery' ); ?></a></li>
						<li><?php esc_html_e( 'Edit the "Landing Page Text" field', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'You can use HTML and shortcodes here', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Save settings', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>
			</div>

			<!-- Troubleshooting -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Troubleshooting', 'brag-book-gallery' ); ?></h2>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( '🔴 Gallery URLs Return 404 Errors', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'This is the most common issue. Here\'s how to fix it:', 'brag-book-gallery' ); ?></p>
					
					<h4><?php esc_html_e( 'Quick Fix:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Go to Settings → Permalinks', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Click "Save Changes" (don\'t change anything)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Test your gallery URLs again', 'brag-book-gallery' ); ?></li>
					</ol>

					<h4><?php esc_html_e( 'Advanced Debugging:', 'brag-book-gallery' ); ?></h4>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Open Debug Tools', 'brag-book-gallery' ); ?>
						</a>
					</p>
					<p><?php esc_html_e( 'The Debug Tools provide:', 'brag-book-gallery' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><strong><?php esc_html_e( 'Gallery Checker', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Verifies your gallery page exists and is configured correctly', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Rewrite Debug', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Shows exactly which rewrite rules are active', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Rewrite Fix', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Automatically fixes common rewrite issues', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'View and clear specific cached items', 'brag-book-gallery' ); ?></li>
					</ul>

					<h4><?php esc_html_e( 'For Nginx Servers:', 'brag-book-gallery' ); ?></h4>
					<p><?php esc_html_e( 'Add these rules to your nginx.conf:', 'brag-book-gallery' ); ?></p>
					<pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
location ~ ^/gallery/([^/]+)/([^/]+)/? {
    try_files $uri $uri/ /index.php?$args;
}
location ~ ^/gallery/([^/]+)/? {
    try_files $uri $uri/ /index.php?$args;
}</pre>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( '🟡 Gallery Not Showing Cases', 'brag-book-gallery' ); ?></h3>
					<h4><?php esc_html_e( 'Check these items:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li>
							<strong><?php esc_html_e( 'API Credentials', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Verify API Token is correct in', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>"><?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?></a></li>
								<li><?php esc_html_e( 'Confirm Website Property ID matches your BRAG book account', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Clear Cache', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Clear plugin cache from JavaScript Settings', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Check Console', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Open browser developer tools (F12)', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Check Console tab for JavaScript errors', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Check Network tab for failed API requests', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
					</ol>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( '🟢 Performance Optimization', 'brag-book-gallery' ); ?></h3>
					<h4><?php esc_html_e( 'Speed up your gallery:', 'brag-book-gallery' ); ?></h4>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( 'Enable caching in JavaScript Settings (1 hour recommended)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Use progressive loading (loads 10 cases initially)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Optimize images in your BRAG book account before uploading', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Consider using a CDN like Cloudflare', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Enable lazy loading for images (enabled by default)', 'brag-book-gallery' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- FAQs -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Frequently Asked Questions', 'brag-book-gallery' ); ?></h2>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Q: How do I find my procedure slugs for the carousel shortcode?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'A: Visit your gallery page and look at the URLs when you click on a procedure. The slug is the last part of the URL. For example, in "/gallery/breast-augmentation/", the slug is "breast-augmentation".', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Q: Can I have multiple galleries on different pages?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'A: Yes! You can use the [brag_book_gallery] shortcode on multiple pages. Each can have different Website Property IDs if needed.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Q: How often does the gallery update with new cases?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'A: The gallery caches data for 1 hour by default. New cases will appear automatically after the cache expires, or you can manually clear the cache to see updates immediately.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Q: Can I customize the gallery colors and styling?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'A: Yes! The plugin uses CSS classes prefixed with "brag-book-gallery-". You can add custom CSS in your theme or using the WordPress Customizer → Additional CSS.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Q: What\'s the difference between JavaScript and Local mode?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'A: JavaScript mode (recommended) loads data directly from the BRAG book API and stays in sync. Local mode imports cases into your WordPress database for offline access but requires manual syncing.', 'brag-book-gallery' ); ?></p>
				</div>

				<div class="brag-book-gallery-faq">
					<h3><?php esc_html_e( 'Q: How do I enable the consultation form?', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'A: Go to Consultation Settings and configure your form preferences. The consultation button will automatically appear on gallery pages and case details.', 'brag-book-gallery' ); ?></p>
				</div>
			</div>

			<!-- Support -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Support & Resources', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-grid">
					<div class="brag-book-gallery-card">
						<h3><span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Documentation', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Complete documentation and video tutorials', 'brag-book-gallery' ); ?></p>
						<a href="https://bragbookgallery.com/docs" target="_blank" class="button button-secondary">
							<?php esc_html_e( 'View Documentation', 'brag-book-gallery' ); ?>
						</a>
					</div>

					<div class="brag-book-gallery-card">
						<h3><span class="dashicons dashicons-email"></span> <?php esc_html_e( 'Email Support', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Get help from our support team', 'brag-book-gallery' ); ?></p>
						<a href="mailto:support@bragbookgallery.com" class="button button-secondary">
							<?php esc_html_e( 'Contact Support', 'brag-book-gallery' ); ?>
						</a>
					</div>

					<div class="brag-book-gallery-card">
						<h3><span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'BRAG book Account', 'brag-book-gallery' ); ?></h3>
						<p><?php esc_html_e( 'Manage your cases and settings', 'brag-book-gallery' ); ?></p>
						<a href="https://app.bragbookgallery.com" target="_blank" class="button button-secondary">
							<?php esc_html_e( 'Login to BRAG book', 'brag-book-gallery' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- System Information -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'System Information', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-card">
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
							<tr>
								<th><?php esc_html_e( 'Gallery Page', 'brag-book-gallery' ); ?></th>
								<td>
									<?php
									$gallery_slug = get_option( 'brag_book_gallery_slug', 'gallery' );
									$gallery_page = get_page_by_path( $gallery_slug );
									if ( $gallery_page ) {
										echo '<span style="color: #46b450;">✓</span> ';
										printf(
											'<a href="%s">%s</a>',
											esc_url( get_permalink( $gallery_page->ID ) ),
											esc_html( $gallery_page->post_title )
										);
									} else {
										echo '<span style="color: #dc3232;">✗</span> ';
										esc_html_e( 'Not found', 'brag-book-gallery' );
									}
									?>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'API Status', 'brag-book-gallery' ); ?></th>
								<td>
									<?php
									$api_token = get_option( 'brag_book_gallery_api_token' );
									$website_property_id = get_option( 'brag_book_gallery_website_property_id' );
									
									if ( $api_token && $website_property_id ) {
										echo '<span style="color: #46b450;">✓</span> ';
										esc_html_e( 'Configured', 'brag-book-gallery' );
									} else {
										echo '<span style="color: #dc3232;">✗</span> ';
										esc_html_e( 'Not configured', 'brag-book-gallery' );
									}
									?>
								</td>
							</tr>
						</tbody>
					</table>
					
					<div style="margin-top: 20px;">
						<p><strong><?php esc_html_e( 'Quick Actions:', 'brag-book-gallery' ); ?></strong></p>
						<p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug' ) ); ?>" class="button">
								<?php esc_html_e( 'Debug Tools', 'brag-book-gallery' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ); ?>" class="button">
								<?php esc_html_e( 'General Settings', 'brag-book-gallery' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button">
								<?php esc_html_e( 'Flush Permalinks', 'brag-book-gallery' ); ?>
							</a>
						</p>
					</div>
				</div>
			</div>
		</div>

		<?php
		$this->render_footer();
	}
}