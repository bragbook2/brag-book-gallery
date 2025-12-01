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
							<strong><?php esc_html_e( 'Sync Gallery Data', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px; margin-top: 5px;">
								<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-sync' ) ); ?>"><?php esc_html_e( 'Sync Settings', 'brag-book-gallery' ); ?></a></li>
								<li><?php esc_html_e( 'Click "Full Sync" to import all gallery data', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Enable Automatic Sync for scheduled updates', 'brag-book-gallery' ); ?></li>
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
					<p><?php esc_html_e( 'The primary shortcode for displaying the gallery. It automatically detects the page context and renders the appropriate view.', 'brag-book-gallery' ); ?></p>

					<p><strong><?php esc_html_e( 'View Parameter:', 'brag-book-gallery' ); ?></strong></p>
					<p><?php esc_html_e( 'Use the view parameter to force a specific display mode:', 'brag-book-gallery' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><code>[brag_book_gallery]</code> - <?php esc_html_e( 'Auto-detects context (default). Shows full gallery with sidebar on main page, procedure cases on taxonomy pages, or single case details.', 'brag-book-gallery' ); ?></li>
						<li><code>[brag_book_gallery view="myfavorites"]</code> - <?php esc_html_e( 'Displays the user\'s saved favorites. This is used on the auto-generated My Favorites page.', 'brag-book-gallery' ); ?></li>
						<li><code>[brag_book_gallery view="column"]</code> - <?php esc_html_e( 'Displays procedures in a column/list layout.', 'brag-book-gallery' ); ?></li>
						<li><code>[brag_book_gallery view="procedure"]</code> - <?php esc_html_e( 'Displays procedure tiles view.', 'brag-book-gallery' ); ?></li>
					</ul>

					<p><strong><?php esc_html_e( 'Other Parameters:', 'brag-book-gallery' ); ?></strong></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><code>case_id</code> - <?php esc_html_e( 'Display a specific case by ID', 'brag-book-gallery' ); ?></li>
						<li><code>cases_only</code> - <?php esc_html_e( 'Set to "true" to show only the cases grid without sidebar (true/false)', 'brag-book-gallery' ); ?></li>
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

			</div>

			<!-- Common Tasks -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Common Tasks', 'brag-book-gallery' ); ?></h2>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Sync Gallery Data', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'The Stage-Based Sync system imports your gallery data in three sequential stages:', 'brag-book-gallery' ); ?></p>

					<h4><?php esc_html_e( 'Stage-Based Sync Process:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li>
							<strong><?php esc_html_e( 'Stage 1: Fetch Procedures', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Downloads all available procedures from BRAG book API', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Creates procedures.json sync data file', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Stage 2: Build Manifest', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Processes procedures to identify all associated cases', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Creates manifest.json with case IDs to process', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Requires Stage 1 completion', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Stage 3: Process Cases', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Downloads detailed case data in batches', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Saves cases to WordPress database', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Requires both Stage 1 and 2 completion', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
					</ol>

					<h4><?php esc_html_e( 'Quick Actions:', 'brag-book-gallery' ); ?></h4>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><strong><?php esc_html_e( 'Full Sync:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Runs all three stages automatically', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Stop Button:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Stops the sync process at any time', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Delete Files:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Remove sync data or manifest files when needed', 'brag-book-gallery' ); ?></li>
					</ul>

					<h4><?php esc_html_e( 'Automatic Sync Setup:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Enable Automatic Sync in Sync Settings', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Choose frequency: Weekly or Custom (hours)', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Save settings to activate scheduled sync', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'View next scheduled sync time in the status display', 'brag-book-gallery' ); ?></li>
					</ol>

					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-sync' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Go to Sync Settings', 'brag-book-gallery' ); ?>
						</a>
					</p>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Set Up Automatic Sync', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'Configure automatic synchronization to keep your local gallery data up to date:', 'brag-book-gallery' ); ?></p>

					<h4><?php esc_html_e( 'Configuration Steps:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Navigate to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-sync' ) ); ?>"><?php esc_html_e( 'Sync Settings', 'brag-book-gallery' ); ?></a></li>
						<li><?php esc_html_e( 'Scroll to "Automatic Sync Settings"', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Enable "Automatic Sync"', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Choose frequency:', 'brag-book-gallery' ); ?>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><strong><?php esc_html_e( 'Weekly:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Syncs once per week', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'Custom:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Set interval in hours (e.g., 24 for daily)', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li><?php esc_html_e( 'Save settings to activate', 'brag-book-gallery' ); ?></li>
					</ol>

					<h4><?php esc_html_e( 'Important Notes:', 'brag-book-gallery' ); ?></h4>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( 'Automatic sync runs the full 3-stage process', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Requires WordPress cron to be functioning properly', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Next scheduled sync time displays after saving', 'brag-book-gallery' ); ?></li>
					</ul>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( 'Clear Gallery Cache', 'brag-book-gallery' ); ?></h3>
					<p><?php esc_html_e( 'If your gallery isn\'t showing the latest cases:', 'brag-book-gallery' ); ?></p>

					<h4><?php esc_html_e( 'Cache Management:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li><?php esc_html_e( 'Go to', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug' ) ); ?>"><?php esc_html_e( 'Debug Tools', 'brag-book-gallery' ); ?></a></li>
						<li><?php esc_html_e( 'Navigate to the Cache Management tab', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'View detailed cache information and expiration times', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Clear individual cache items or all cached data', 'brag-book-gallery' ); ?></li>
						<li><?php esc_html_e( 'Refresh your gallery page', 'brag-book-gallery' ); ?></li>
					</ol>
				</div>

				<div class="brag-book-gallery-card">
					<h3><?php esc_html_e( '🟪 Sync Issues', 'brag-book-gallery' ); ?></h3>
					<h4><?php esc_html_e( 'If the sync process fails:', 'brag-book-gallery' ); ?></h4>
					<ol>
						<li>
							<strong><?php esc_html_e( 'Check API Credentials', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Verify your API Token in', 'brag-book-gallery' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>"><?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?></a></li>
								<li><?php esc_html_e( 'Confirm Website Property ID is correct', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Check Stage Status', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Stage buttons show which stage failed', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Try running individual stages to isolate the issue', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Delete sync files and start fresh if needed', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
						<li>
							<strong><?php esc_html_e( 'Server Timeout', 'brag-book-gallery' ); ?></strong>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><?php esc_html_e( 'Stage 3 processes cases in batches', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'If it times out, run Stage 3 again to continue', 'brag-book-gallery' ); ?></li>
								<li><?php esc_html_e( 'Progress is saved between attempts', 'brag-book-gallery' ); ?></li>
							</ul>
						</li>
					</ol>

					<h4><?php esc_html_e( 'Stage-Based Sync Status Indicators:', 'brag-book-gallery' ); ?></h4>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><strong><?php esc_html_e( 'Black button:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Next stage to run', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'White button:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Stage available but not active', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Disabled button:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Prerequisites not met', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Progress bar:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Shows sync operation in progress', 'brag-book-gallery' ); ?></li>
					</ul>
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
					<p><?php esc_html_e( 'The Debug Tools provide comprehensive diagnostic capabilities organized in tabs:', 'brag-book-gallery' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><strong><?php esc_html_e( 'Diagnostic Tools', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'System status, gallery checker, and rewrite rules analysis', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Cache Management', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'View and clear individual cached items with detailed information', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Rewrite Fix', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Automatically detects and fixes common URL rewrite issues', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'Flush Rules', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Regenerate WordPress rewrite rules and query variables', 'brag-book-gallery' ); ?></li>
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
								<li><?php esc_html_e( 'Clear plugin cache from Debug Tools → Cache Management', 'brag-book-gallery' ); ?></li>
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
			</div>

			<!-- FAQs -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Frequently Asked Questions', 'brag-book-gallery' ); ?></h2>

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
							<p><?php esc_html_e( 'Navigate to General Settings and scroll down to the Custom CSS section. The built-in Monaco Editor provides advanced features like IntelliSense, syntax highlighting, error checking, and auto-completion. Your custom CSS is automatically applied to all gallery pages and shortcodes.', 'brag-book-gallery' ); ?></p>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'What should I do if my gallery isn\'t displaying?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'First, check that your API credentials are configured in API Settings. Ensure you\'ve run the Stage-Based Sync to import cases. Then visit Debug Tools → Diagnostic Tools to run the Gallery Checker and verify your page setup. If issues persist, try clearing the cache from the Cache Management tab and flushing rewrite rules from the Flush Rules tab.', 'brag-book-gallery' ); ?></p>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'What do the sync file status icons mean?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'The sync status shows two important files:', 'brag-book-gallery' ); ?></p>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><strong><?php esc_html_e( 'Green checkmark:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'File exists and is ready', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'Red X:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'File not found or needs to be created', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'View link:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Click to download and inspect the JSON file', 'brag-book-gallery' ); ?></li>
							</ul>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'Can I run multiple sync operations at once?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'No, the sync system processes one stage at a time to ensure data integrity. The stages must run in order: Stage 1 (Fetch Procedures), Stage 2 (Build Manifest), then Stage 3 (Process Cases). Use the Full Sync button to run all stages automatically in sequence, or run individual stages as needed.', 'brag-book-gallery' ); ?></p>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'How can I display a specific procedure\'s cases?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'Use the carousel shortcode with the procedure parameter: [brag_book_carousel procedure="procedure-slug" limit="5"]. You can find procedure slugs by visiting your gallery and checking the URL when filtering by a procedure.', 'brag-book-gallery' ); ?></p>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'Can I show/hide specific elements in the gallery?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'Yes! In General Settings, you can control various display options including: grid layout toggle, sharing buttons, image display mode (single or before/after), and more. You can also use Custom CSS to hide specific elements.', 'brag-book-gallery' ); ?></p>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'How does the Automatic Sync work?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'Automatic Sync runs the 3-stage sync process on a schedule you define:', 'brag-book-gallery' ); ?></p>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><strong><?php esc_html_e( 'Weekly:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Runs once per week', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'Custom:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Set your own interval in hours', 'brag-book-gallery' ); ?></li>
							</ul>
							<p><?php esc_html_e( 'When enabled, it automatically runs all three stages (Fetch Procedures, Build Manifest, Process Cases) to keep your local data up to date.', 'brag-book-gallery' ); ?></p>
						</div>
					</details>

					<details class="brag-book-gallery-accordion-item">
						<summary><?php esc_html_e( 'How do the Debug Tools help me troubleshoot issues?', 'brag-book-gallery' ); ?></summary>
						<div class="brag-book-gallery-accordion-content">
							<p><?php esc_html_e( 'The Debug Tools provide comprehensive diagnostic capabilities:', 'brag-book-gallery' ); ?></p>
							<ul style="list-style: disc; margin-left: 20px;">
								<li><strong><?php esc_html_e( 'Diagnostic Tools:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'System status overview, gallery page validation, rewrite rules analysis, and query variables debugging', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'Cache Management:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'View all cached items with expiration times and selectively clear individual cache entries', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'Rewrite Fix:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Automatically detects and repairs common URL rewrite issues with real API data testing', 'brag-book-gallery' ); ?></li>
								<li><strong><?php esc_html_e( 'Flush Rules:', 'brag-book-gallery' ); ?></strong> <?php esc_html_e( 'Regenerates WordPress rewrite rules and query variables with confirmation dialogs', 'brag-book-gallery' ); ?></li>
							</ul>
						</div>
					</details>
				</div>
			</div>

			<!-- Support - Hidden for Release 3.2.1-->
			<div class="brag-book-gallery-section" style="display:none;">
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
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-debug#diagnostic-tools' ) ); ?>" class="button">
								<?php esc_html_e( 'Debug Tools', 'brag-book-gallery' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings' ) ); ?>" class="button">
								<?php esc_html_e( 'General Settings', 'brag-book-gallery' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=brag-book-gallery-settings&tab=api' ) ); ?>" class="button">
								<?php esc_html_e( 'API Settings', 'brag-book-gallery' ); ?>
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
