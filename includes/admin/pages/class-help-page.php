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

namespace BRAGBookGallery\Includes\Admin\Pages;

use BRAGBookGallery\Includes\Admin\Core\Settings_Base;

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
 * - Mode comparison table (Default vs Local)
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
class Help_Page extends Settings_Base {

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
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Help & Documentation', 'brag-book-gallery' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Find answers to common questions and learn how to use the BRAG book gallery effectively.', 'brag-book-gallery' ); ?></p>
			</div>

			<!-- Getting Started -->
			<details class="brag-book-gallery-accordion-item" open>
				<summary><?php esc_html_e( 'Getting Started', 'brag-book-gallery' ); ?></summary>
				<div class="brag-book-gallery-accordion-content">
					<h4><?php esc_html_e( 'Quick Setup Guide', 'brag-book-gallery' ); ?></h4>
					<div class="help-steps">
						<div class="help-step">
							<span class="help-step-number">1</span>
							<div class="help-step-content">
								<strong><?php esc_html_e( 'Get Your API Credentials', 'brag-book-gallery' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'Log into your BRAG book account at', 'brag-book-gallery' ); ?> <a href="https://app.bragbookgallery.com" target="_blank">app.bragbookgallery.com</a></li>
									<li><?php esc_html_e( 'Navigate to Settings → API', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'Copy your API Token and Website Property ID', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</div>
						<div class="help-step">
							<span class="help-step-number">2</span>
							<div class="help-step-content">
								<strong><?php esc_html_e( 'Configure API Settings', 'brag-book-gallery' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>"><?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?></a></li>
									<li><?php esc_html_e( 'Enter your API Token and Website Property ID', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'Save the settings', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</div>
						<div class="help-step">
							<span class="help-step-number">3</span>
							<div class="help-step-content">
								<strong><?php esc_html_e( 'Set Gallery Slug', 'brag-book-gallery' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ); ?>"><?php esc_html_e( 'General Settings', 'brag-book-gallery' ); ?></a></li>
									<li><?php esc_html_e( 'Set your Gallery Slug (e.g., "gallery", "before-after", "results")', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'This will be the base URL for your gallery pages', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</div>
						<div class="help-step">
							<span class="help-step-number">4</span>
							<div class="help-step-content">
								<strong><?php esc_html_e( 'Create Gallery Page', 'brag-book-gallery' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'Create a new page with the same slug as your Gallery Slug setting', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'Add the shortcode:', 'brag-book-gallery' ); ?> <code>[brag_book_gallery]</code></li>
									<li><?php esc_html_e( 'Publish the page', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</div>
						<div class="help-step">
							<span class="help-step-number">5</span>
							<div class="help-step-content">
								<strong><?php esc_html_e( 'Sync Gallery Data', 'brag-book-gallery' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-sync' ) ); ?>"><?php esc_html_e( 'Sync Settings', 'brag-book-gallery' ); ?></a></li>
									<li><?php esc_html_e( 'Click "Full Sync" to import all gallery data', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'Enable Automatic Sync for scheduled updates', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</div>
						<div class="help-step">
							<span class="help-step-number">6</span>
							<div class="help-step-content">
								<strong><?php esc_html_e( 'Flush Permalinks', 'brag-book-gallery' ); ?></strong>
								<ul>
									<li><?php esc_html_e( 'Go to Settings → Permalinks', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'Click "Save Changes" (no need to change anything)', 'brag-book-gallery' ); ?></li>
									<li><?php esc_html_e( 'This ensures your gallery URLs work correctly', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</details>

			<!-- Shortcodes -->
			<details class="brag-book-gallery-accordion-item">
				<summary><?php esc_html_e( 'Available Shortcodes', 'brag-book-gallery' ); ?></summary>
				<div class="brag-book-gallery-accordion-content">
					<h4><?php esc_html_e( 'Main Gallery Shortcode', 'brag-book-gallery' ); ?></h4>
					<div class="help-shortcode-block" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
						<code>[brag_book_gallery]</code>
						<span class="help-shortcode-copy">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
						</span>
					</div>
					<p><?php esc_html_e( 'The primary shortcode for displaying the gallery. It automatically detects the page context and renders the appropriate view.', 'brag-book-gallery' ); ?></p>

					<p><strong><?php esc_html_e( 'View Parameter:', 'brag-book-gallery' ); ?></strong></p>
					<p><?php esc_html_e( 'Use the view parameter to force a specific display mode:', 'brag-book-gallery' ); ?></p>
					<ul>
						<li>
							<div class="help-shortcode-block help-shortcode-inline" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
								<code>[brag_book_gallery]</code>
								<span class="help-shortcode-copy"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span>
							</div>
							<?php esc_html_e( 'Auto-detects context (default)', 'brag-book-gallery' ); ?>
						</li>
						<li>
							<div class="help-shortcode-block help-shortcode-inline" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
								<code>[brag_book_gallery view="myfavorites"]</code>
								<span class="help-shortcode-copy"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span>
							</div>
							<?php esc_html_e( 'Displays the user\'s saved favorites', 'brag-book-gallery' ); ?>
						</li>
						<li>
							<div class="help-shortcode-block help-shortcode-inline" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
								<code>[brag_book_gallery view="column"]</code>
								<span class="help-shortcode-copy"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span>
							</div>
							<?php esc_html_e( 'Column/list layout', 'brag-book-gallery' ); ?>
						</li>
						<li>
							<div class="help-shortcode-block help-shortcode-inline" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
								<code>[brag_book_gallery view="procedure"]</code>
								<span class="help-shortcode-copy"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span>
							</div>
							<?php esc_html_e( 'Procedure tiles view', 'brag-book-gallery' ); ?>
						</li>
					</ul>

					<p><strong><?php esc_html_e( 'Other Parameters:', 'brag-book-gallery' ); ?></strong></p>
					<ul>
						<li><code>case_id</code> - <?php esc_html_e( 'Display a specific case by ID', 'brag-book-gallery' ); ?></li>
						<li><code>cases_only</code> - <?php esc_html_e( 'Show only the cases grid without sidebar (true/false)', 'brag-book-gallery' ); ?></li>
					</ul>

					<h4><?php esc_html_e( 'Carousel Shortcode', 'brag-book-gallery' ); ?></h4>
					<div class="help-shortcode-block" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
						<code>[brag_book_carousel procedure="arm-lift" limit="5"]</code>
						<span class="help-shortcode-copy">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
						</span>
					</div>
					<p><?php esc_html_e( 'Displays cases in a carousel/slider format. Perfect for homepage or landing pages.', 'brag-book-gallery' ); ?></p>
					<p><strong><?php esc_html_e( 'Parameters:', 'brag-book-gallery' ); ?></strong></p>
					<ul>
						<li><code>procedure</code> - <?php esc_html_e( 'Procedure slug to filter by', 'brag-book-gallery' ); ?></li>
						<li><code>procedure_id</code> - <?php esc_html_e( 'Procedure ID (alternative to slug)', 'brag-book-gallery' ); ?></li>
						<li><code>member_id</code> - <?php esc_html_e( 'Filter by specific member/doctor', 'brag-book-gallery' ); ?></li>
						<li><code>limit</code> - <?php esc_html_e( 'Number of items (default: 10)', 'brag-book-gallery' ); ?></li>
						<li><code>show_controls</code> - <?php esc_html_e( 'Navigation arrows (true/false)', 'brag-book-gallery' ); ?></li>
						<li><code>show_pagination</code> - <?php esc_html_e( 'Dots pagination (true/false)', 'brag-book-gallery' ); ?></li>
						<li><code>autoplay</code> - <?php esc_html_e( 'Auto-advance slides (true/false)', 'brag-book-gallery' ); ?></li>
						<li><code>autoplay_delay</code> - <?php esc_html_e( 'Delay in ms (default: 3000)', 'brag-book-gallery' ); ?></li>
					</ul>
					<p><strong><?php esc_html_e( 'Legacy Format:', 'brag-book-gallery' ); ?></strong></p>
					<div class="help-shortcode-block" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to copy', 'brag-book-gallery' ); ?>">
						<code>[bragbook_carousel_shortcode procedure="arm-lift" limit="5" title="0" details="0"]</code>
						<span class="help-shortcode-copy">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
						</span>
					</div>
				</div>
			</details>

			<!-- Common Tasks -->
			<details class="brag-book-gallery-accordion-item">
				<summary><?php esc_html_e( 'Common Tasks', 'brag-book-gallery' ); ?></summary>
				<div class="brag-book-gallery-accordion-content">

					<h4><?php esc_html_e( 'Sync Gallery Data', 'brag-book-gallery' ); ?></h4>
					<p><?php esc_html_e( 'The Stage-Based Sync system imports your gallery data in three sequential stages:', 'brag-book-gallery' ); ?></p>
					<ol class="help-list">
						<li>
							<strong><?php esc_html_e( 'Stage 1: Fetch Procedures', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Downloads all available procedures from BRAG book API', 'brag-book-gallery' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Stage 2: Build Manifest', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Processes procedures to identify all associated cases', 'brag-book-gallery' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Stage 3: Process Cases', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Downloads detailed case data in batches and saves to WordPress', 'brag-book-gallery' ); ?>
						</li>
					</ol>
					<ul>
						<li><strong><?php esc_html_e( 'Full Sync:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Runs all three stages automatically', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Stop Button:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Stops the sync process at any time', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Delete Files:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Remove sync data or manifest files when needed', 'brag-book-gallery' ); ?></li>
					</ul>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-sync' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Go to Sync Settings', 'brag-book-gallery' ); ?>
						</a>
					</p>

					<h4><?php esc_html_e( 'Set Up Automatic Sync', 'brag-book-gallery' ); ?></h4>
					<ol class="help-list">
						<li><?php esc_html_e( 'Navigate to Sync Settings', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Enable "Automatic Sync"', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Choose frequency: Weekly or Custom (hours)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Save settings to activate scheduled sync', 'brag-book-gallery' ); ?></li>
					</ol>

					<h4><?php esc_html_e( 'Clear Gallery Cache', 'brag-book-gallery' ); ?></h4>
					<ol class="help-list">
						<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug' ) ); ?>"><?php esc_html_e( 'Debug Tools', 'brag-book-gallery' ); ?></a></li>
						<li><?php esc_html_e( 'Navigate to the Cache Management tab', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Clear individual cache items or all cached data', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Refresh your gallery page', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>
			</details>

			<!-- Troubleshooting -->
			<details class="brag-book-gallery-accordion-item">
				<summary><?php esc_html_e( 'Troubleshooting', 'brag-book-gallery' ); ?></summary>
				<div class="brag-book-gallery-accordion-content">

					<h4><?php esc_html_e( 'Gallery URLs Return 404 Errors', 'brag-book-gallery' ); ?></h4>
					<p><?php esc_html_e( 'This is the most common issue. Quick fix:', 'brag-book-gallery' ); ?></p>
					<ol class="help-list">
						<li><?php esc_html_e( 'Go to Settings → Permalinks', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Click "Save Changes" (don\'t change anything)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Test your gallery URLs again', 'brag-book-gallery' ); ?></li>
					</ol>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug' ) ); ?>" class="button">
							<?php esc_html_e( 'Open Debug Tools', 'brag-book-gallery' ); ?>
						</a>
					</p>

					<h4><?php esc_html_e( 'For Nginx Servers', 'brag-book-gallery' ); ?></h4>
					<p><?php esc_html_e( 'Add these rules to your nginx.conf:', 'brag-book-gallery' ); ?></p>
					<pre class="help-code-block">location ~ ^/gallery/([^/]+)/([^/]+)/? {
    try_files $uri $uri/ /index.php?$args;
}
location ~ ^/gallery/([^/]+)/? {
    try_files $uri $uri/ /index.php?$args;
}</pre>

					<h4><?php esc_html_e( 'Gallery Not Showing Cases', 'brag-book-gallery' ); ?></h4>
					<ol class="help-list">
						<li><strong><?php esc_html_e( 'API Credentials', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Verify API Token and Website Property ID in', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>"><?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?></a></li>
						<li><strong><?php esc_html_e( 'Clear Cache', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Clear plugin cache from Debug Tools → Cache Management', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Check Console', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Open browser dev tools (F12) and check for JavaScript or network errors', 'brag-book-gallery' ); ?></li>
					</ol>

					<h4><?php esc_html_e( 'Sync Issues', 'brag-book-gallery' ); ?></h4>
					<ol class="help-list">
						<li><strong><?php esc_html_e( 'Check API Credentials', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Verify token and property ID are correct', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Check Stage Status', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Stage buttons show which stage failed. Try running individual stages to isolate the issue.', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Server Timeout', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'If Stage 3 times out, run it again. Progress is saved between attempts.', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>
			</details>

			<!-- FAQs -->
			<details class="brag-book-gallery-accordion-item">
				<summary><?php esc_html_e( 'Frequently Asked Questions', 'brag-book-gallery' ); ?></summary>
				<div class="brag-book-gallery-accordion-content">
					<div class="brag-book-gallery-faq-container">
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'How do I find my procedure slugs for the carousel shortcode?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'Visit your gallery page and look at the URLs when you click on a procedure. The slug is the last part of the URL. For example, in "/gallery/breast-augmentation/", the slug is "breast-augmentation".', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'Can I have multiple galleries on different pages?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'Yes! You can use the [brag_book_gallery] shortcode on multiple pages. Each can have different Website Property IDs if needed.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'How often does the gallery update with new cases?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'You can set up Automatic Sync to run weekly or on a custom schedule. You also have the option to manually sync the items at anytime.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'Can I customize the gallery colors and styling?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'Yes! The plugin uses CSS classes prefixed with "brag-book-gallery-". You can add custom CSS in your theme or using the WordPress Customizer → Additional CSS.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'How do I enable the consultation form?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'Go to Communications Settings and configure your consultation form preferences. The consultation button will automatically appear on gallery pages and case details.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'How do I use Custom CSS for additional styling?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'Navigate to General Settings and scroll down to the Custom CSS section. The built-in Monaco Editor provides syntax highlighting, error checking, and auto-completion.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'What do the sync file status icons mean?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<ul>
									<li><strong><?php esc_html_e( 'Green checkmark:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'File exists and is ready', 'brag-book-gallery' ); ?></li>
									<li><strong><?php esc_html_e( 'Red X:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'File not found or needs to be created', 'brag-book-gallery' ); ?></li>
									<li><strong><?php esc_html_e( 'View link:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Click to download and inspect the JSON file', 'brag-book-gallery' ); ?></li>
								</ul>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'Can I run multiple sync operations at once?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'No, the sync system processes one stage at a time to ensure data integrity. Use the Full Sync button to run all stages automatically in sequence.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
						<details class="brag-book-gallery-accordion-item">
							<summary><?php esc_html_e( 'How does the Automatic Sync work?', 'brag-book-gallery' ); ?></summary>
							<div class="brag-book-gallery-accordion-content">
								<p><?php esc_html_e( 'Automatic Sync runs the full 3-stage process on a schedule you define (weekly or custom interval in hours). It keeps your local data up to date automatically.', 'brag-book-gallery' ); ?></p>
							</div>
						</details>
					</div>
				</div>
			</details>

			<!-- System Information -->
			<details class="brag-book-gallery-accordion-item">
				<summary><?php esc_html_e( 'System Information', 'brag-book-gallery' ); ?></summary>
				<div class="brag-book-gallery-accordion-content">
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

					</div>
			</details>

			<div class="help-quick-actions">
				<h3><?php esc_html_e( 'Quick Actions', 'brag-book-gallery' ); ?></h3>
				<div class="help-quick-actions-grid">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug#diagnostic-tools' ) ); ?>" class="button button-primary-dark">
						<?php esc_html_e( 'Debug Tools', 'brag-book-gallery' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ); ?>" class="button button-primary-dark">
						<?php esc_html_e( 'General Settings', 'brag-book-gallery' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>" class="button button-primary-dark">
						<?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>" class="button button-primary-dark">
						<?php esc_html_e( 'Flush Permalinks', 'brag-book-gallery' ); ?>
					</a>
				</div>
			</div>

		</div>

		<script>
		document.querySelectorAll( '.help-shortcode-block' ).forEach( function( block ) {
			block.addEventListener( 'click', function() {
				var code = this.querySelector( 'code' ).textContent;
				navigator.clipboard.writeText( code ).then( function() {
					var icon = block.querySelector( '.help-shortcode-copy' );
					icon.classList.add( 'copied' );
					setTimeout( function() {
						icon.classList.remove( 'copied' );
					}, 1500 );
				} );
			} );
		} );
		</script>

		<?php
		$this->render_footer();
	}
}
