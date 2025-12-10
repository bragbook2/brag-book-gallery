<?php
/**
 * Changelog Settings Class
 *
 * @package    BRAGBookGallery
 * @subpackage Includes\Admin
 * @since      3.2.4
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
 * Changelog Settings Class
 *
 * Displays the plugin version history and changelog information.
 * This class provides users with a comprehensive view of plugin updates,
 * new features, bug fixes, and improvements across all versions.
 *
 * **Features:**
 * - Complete version history with release dates
 * - Categorized changes (new features, improvements, bug fixes)
 * - GitHub release links for detailed information
 * - Visual indicators for version types (major, minor, patch)
 * - Searchable changelog content
 *
 * **User Benefits:**
 * - Track plugin evolution and improvements
 * - Understand what changed between versions
 * - Plan updates based on feature additions
 * - Reference bug fixes and improvements
 * - Access detailed release information on GitHub
 *
 * This class serves as a historical record of the plugin's development
 * and helps users stay informed about updates and improvements.
 *
 * @since 3.2.4
 */
class Changelog_Page extends Settings_Base {

	/**
	 * Initialize the settings page
	 *
	 * @since 3.2.4
	 * @return void
	 */
	protected function init(): void {
		$this->page_slug = 'brag-book-gallery-changelog';
	}

	/**
	 * Render the settings page
	 *
	 * @since 3.2.4
	 * @return void
	 */
	public function render(): void {
		// Set translated strings when rendering
		$this->page_title = __( 'Changelog & Version History', 'brag-book-gallery' );
		$this->menu_title = __( 'Changelog', 'brag-book-gallery' );

		$this->render_header();
		?>

		<div class="brag-book-gallery-changelog-content">
			<!-- Introduction -->
			<div class="brag-book-gallery-section">
				<h2><?php esc_html_e( 'Version History', 'brag-book-gallery' ); ?></h2>
				<div class="brag-book-gallery-card">
					<p><?php esc_html_e( 'This page documents all changes, improvements, and new features added to BRAG book Gallery. Each version includes detailed information about what\'s new and what has been fixed.', 'brag-book-gallery' ); ?></p>
					<p>
						<strong><?php esc_html_e( 'Current Version:', 'brag-book-gallery' ); ?></strong>
						<?php
						$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/brag-book-gallery/brag-book-gallery.php' );
						echo esc_html( $plugin_data['Version'] ?? '3.0.0' );
						?>
					</p>
					<p>
						<a href="https://github.com/bragbook2/brag-book-gallery/releases" target="_blank" class="button button-primary">
							<?php esc_html_e( 'View GitHub Releases', 'brag-book-gallery' ); ?>
						</a>
					</p>
				</div>
			</div>

			<!-- Version 4.0.1 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v4.0.1</span>
						<?php esc_html_e( 'December 10, 2025', 'brag-book-gallery' ); ?> - <?php esc_html_e( 'Patch Release', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<p><?php esc_html_e( 'This patch release fixes favorites functionality and implements proper case view tracking with the correct API parameters.', 'brag-book-gallery' ); ?></p>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Favorites API: Fixed add/remove favorites using correct caseProcedureId parameter for v2 API endpoints', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Favorites Remove: Fixed ajax_remove_favorite to properly look up caseProcedureId from post meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Views Tracking: Fixed /views endpoint to send caseProcedureId instead of caseId', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Server-side View Tracking: Fixed scheduled view tracking to use correct caseProcedureId', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '✨ Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Client-side View Tracking: Added JavaScript tracking when clicking case cards and carousel items', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Data Attributes: Added data-procedure-case-id attribute to case cards, case detail views, and carousel items', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Console Logging: Added console.log messages for view tracking confirmation', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 4.0.0 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-major">v4.0.0</span>
						<?php esc_html_e( 'December 9, 2025', 'brag-book-gallery' ); ?> - <?php esc_html_e( 'Stable Release', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<p><?php esc_html_e( 'This major release consolidates all features and improvements from the 3.3.2 beta series into a stable production release.', 'brag-book-gallery' ); ?></p>
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Doctors Taxonomy: New brag_book_doctors taxonomy for managing doctor profiles', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Term meta fields: First Name, Last Name, Suffix, Profile URL, Profile Photo, and Member ID', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctors submenu in BRAG Book admin menu (when property ID 111 is enabled)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic doctor term creation during Stage 3 data sync from case creator information', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor Profile URL Field: brag_book_gallery_doctor_profile_url meta field for case post types', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor Suffix Field: brag_book_gallery_doctor_suffix meta field for case post types', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor Details Display: "Show Doctor Details" toggle in Display Settings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor Name Field: Doctor Name field in case post meta (Basic Information tab)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Member ID Field: Member ID number field in case post meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Minified Assets: Intelligent asset minification system (50-54% smaller JS, 10-13% smaller CSS)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Procedure Links: Clickable links to procedures in case card details with hover animations', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '✨ Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Case View Doctor Profile: Doctor profile photo and name displayed below case title (property ID 111)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Cases Grid Doctor Display: Case cards display doctor photo and name instead of procedure when enabled', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'V3 Card Doctor Display: V3 cards show doctor name in overlay when "Show Doctor Details" is enabled', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Search Input Accessibility: Improved ARIA attributes for better screen reader support', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'HTML Semantics: Improved semantic HTML structure throughout the plugin', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Sitemap Generation: Fixed critical TypeError in Sitemap class', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Stage 3 Sync Title Assignment: Fixed case post titles being overwritten with incorrect procedure names', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'V3 Card Image Clickability: Images in v3 cards are now fully clickable', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Landing Page Text Editor: Replaced TinyMCE with Trumbowyg WYSIWYG editor', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Gallery Landing Page Error: Fixed null reference error in procedure referrer tracking', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Generate Favorites Page Button: Fixed button functionality and status checking', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Case Navigation URLs: Fixed navigation buttons to use full absolute URLs', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🎨 Styling', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'New CSS styles for doctor profile section in case view header', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'New CSS styles for doctor avatar and name in case card overlays', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated consultation chart colors for consistency', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta15 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta15</span>
						<?php esc_html_e( 'December 1, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Doctors Taxonomy: New brag_book_doctors taxonomy for managing doctor profiles (visible when website property ID 111 is active)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Term meta fields include: First Name, Last Name, Suffix, Profile URL, Profile Photo (media upload), and Member ID', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctors submenu added to BRAG Book admin menu when property ID 111 is enabled', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic doctor term creation during Stage 3 data sync from case creator information', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '✨ Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Case View Doctor Profile: When property ID 111 is active, doctor profile photo (48x48 circle) and name displayed below case title', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor profile section includes clickable link to doctor profile URL when available', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Cases Grid Doctor Display: When Show Doctor option is enabled, case cards display doctor photo and name instead of procedure name and case number', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor information pulled from taxonomy terms with fallback to post meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated v2 and v3 card overlays to support doctor display mode', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🎨 Styling', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'New CSS styles for doctor profile section in case view header', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'New CSS styles for doctor avatar and name in case card overlays', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor name links styled consistently in black without underline', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta14 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta14</span>
						<?php esc_html_e( 'November 13, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '✨ Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Search Input Accessibility: Improved search input ARIA attributes for better screen reader support', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added role="combobox" to mobile search input for proper accessibility compliance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Standardized class names across mobile and desktop search inputs', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced ARIA labels, autocomplete attributes, and controls', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'HTML Semantics: Improved semantic HTML structure throughout the plugin', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Changed non-heading titles from h4 to p tags where headings were not semantically appropriate', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improves document outline and accessibility for screen readers', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Chart Colors: Updated consultation chart colors in Communications page for consistency', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta13 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta13</span>
						<?php esc_html_e( 'November 12, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Minified Assets: Implemented intelligent asset minification system for optimal performance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Production mode loads .min.js and .min.css files (50-54% smaller JS, 10-13% smaller CSS)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Development mode (SCRIPT_DEBUG enabled) loads non-minified versions for debugging', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Webpack generates both minified and non-minified JavaScript files', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Sass generates both compressed and expanded CSS files', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Procedure Links: Added clickable links to procedures in case card details', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Each procedure in "Procedures Performed" list now links to its taxonomy page', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Includes hover animations with subtle lift effect and box shadow', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Proper ARIA labels for accessibility (View [Procedure] cases)', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Sitemap Generation: Fixed critical TypeError in Sitemap class', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolved "Return value must be of type string, null returned" error', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed undefined variable references when Cache_Manager was removed', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated get_sitemap_content(), generate_sitemap(), is_rate_limited(), and get_cached_data()', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'All variables now properly initialized before use', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta10 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta10</span>
						<?php esc_html_e( 'November 10, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Doctor Profile URL Field: Added brag_book_gallery_doctor_profile_url meta field to case post types', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Allows storing URL to doctor\'s profile page with URL input validation', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor Suffix Field: Added brag_book_gallery_doctor_suffix meta field to case post types', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Stores professional suffix (e.g., MD, PhD, DDS)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Both fields added to case meta box in WordPress admin with proper sanitization', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '✨ Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'V3 Card Doctor Display: Enhanced v3 card type to show doctor name when "Show Doctor Details" option is enabled', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor name now displays in card overlay instead of procedure name when toggle is active', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Controlled by brag_book_gallery_show_doctor option (set to 1 to enable)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Falls back to procedure name if doctor name is not available', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'V3 Card Case Number: Case number now hidden on v3 cards when doctor name display is enabled', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Provides cleaner appearance when showing doctor information', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta7 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta7</span>
						<?php esc_html_e( 'November 4, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Doctor Details Display: New "Show Doctor Details" toggle setting in Display Settings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Allows administrators to control visibility of doctor information on case pages', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Setting: brag_book_gallery_show_doctor_details (default: false)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Doctor Name Field: Added Doctor Name field to case post meta in Basic Information tab', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Stores doctor name as _brag_book_gallery_doctor_name post meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Displayed in admin interface for case management', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Member ID Field: Added Member ID number field to case post meta in Basic Information tab', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Stores member ID as _brag_book_gallery_member_id post meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Useful for tracking and organizing cases by member', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Generate Favorites Page Button: Fixed button functionality and status checking', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added initial status check on page load to show correct button state', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Button now properly detects existing favorites page before showing generate option', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed edge case where button showed incorrect state after page refresh', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta3 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta3</span>
						<?php esc_html_e( 'October 13, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Case Navigation URLs: Fixed navigation buttons to use full absolute URLs with domain', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated PHP navigation button generation in class-case-handler.php to use WordPress permalinks', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed AJAX endpoint in class-cases-handler.php to return absolute URLs instead of relative paths', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Navigation buttons now respect Case Ordering from taxonomy term meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Ensured JavaScript AJAX calls receive proper absolute URLs for next/previous case navigation', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta2 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta2</span>
						<?php esc_html_e( 'October 9, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Landing Page Text Editor: Replaced TinyMCE with Trumbowyg WYSIWYG editor to resolve AMD/RequireJS conflicts', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Removed problematic WordPress TinyMCE editor that conflicted with Monaco Editor', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implemented lightweight Trumbowyg editor with visual and HTML editing modes', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed "Can only have one anonymous define call per script file" error', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Vanilla ES6 JavaScript implementation for better performance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Toolbar includes formatting, bold, italic, links, lists, and HTML view toggle', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Gallery Landing Page Error: Fixed null reference error in procedure referrer tracking', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added null check in global-utilities.js before accessing regex match results', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolved "Cannot read properties of null (reading \'1\')" JavaScript error', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Error only occurred when visiting gallery landing page (non-procedure pages)', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.2-beta1 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-beta">v3.3.2-beta1</span>
						<?php esc_html_e( 'October 9, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Initial beta release for testing multi-channel release system', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.1 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.3.1</span>
						<?php esc_html_e( 'October 8, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Column View: New shortcode view for displaying procedures organized by parent categories', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Adaptive grid layout automatically adjusts columns based on number of parent categories (1-5 columns)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Responsive breakpoints for mobile, tablet, and desktop displays', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Usage: [brag_book_gallery view="column"]', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Procedure Banner Images: Support for banner images on procedure parent categories', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Retrieves banner images from banner_image term meta', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implements responsive <picture> elements with multiple image sizes', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Includes lazy loading and async decoding for performance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic fallback to parent category name for alt text', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Multi-Channel Release System: Beta, RC, and stable release channels', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Users can opt into beta or RC releases for early access to new features', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Channel selection available in General Settings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic filtering of GitHub releases based on selected channel', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced update notification system with channel-specific warnings', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Asset Versioning: Updated Asset_Manager VERSION constant to match plugin version', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Column View Assets: Added missing asset enqueuing in handle_column_view() method', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.3.0 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-minor">v3.3.0</span>
						<?php esc_html_e( 'October 7, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Automatic Sync Cron Jobs: Full implementation of WordPress cron-based automatic synchronization', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added weekly cron schedule support to WordPress (not included by default)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implemented custom date/time scheduling for one-time sync events', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Created visual cron status display on Sync Settings page showing next scheduled sync', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added "Test Cron Now" button for manual cron job testing and validation', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Full 3-stage sync execution via cron (Procedures, Manifest, Cases)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Detailed logging for all cron operations for debugging', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic schedule clearing when sync is disabled', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Cron Status Monitoring: Real-time visibility of scheduled sync operations', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Shows exact date/time of next scheduled sync', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Displays human-readable countdown (e.g., "In 6 days")', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Indicates overdue syncs when cron hasn\'t executed on schedule', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Integrated status display directly in admin interface', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Carousel Cross-Origin Images: Fixed Firefox cookie rejection errors for Cloudflare-protected images from BRAGBook API', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added crossorigin="anonymous" attributes to all external image elements in JavaScript modules', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Prevents Firefox from rejecting Cloudflare __cf_bm cookies when loading before/after images', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Affected files: filter-system.js, global-utilities.js, main-app.js, carousel.js', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'JavaScript Build Errors: Fixed syntax errors in main-app.js caused by console statement cleanup', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Removed orphaned object literals left after automated console.log removal', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed broken JavaScript that was preventing webpack builds from completing', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Nudity Warnings on Case Cards: Fixed nudity warnings not appearing on individual case cards for procedures with nudity flags', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added missing nudity warning rendering logic to render_wordpress_case_card() method in Cases_Handler class', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed inconsistent nudity detection by using WordPress taxonomy meta instead of API sidebar data', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Unified nudity detection approach across gallery and sidebar handlers for consistency', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Favorites Display: Enhanced favorites functionality with user information display', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added user email and favorites count display after content title on favorites page', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated card HTML structure to match exact design specifications', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved favorites grid rendering with proper user info integration', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Procedure Taxonomy Pages: Prevented unwanted API calls on procedure taxonomy pages', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed is_bragbook_page() method in Assets class to exclude procedure taxonomy pages', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added explicit check using is_tax(\'procedures\') to prevent frontend assets from loading', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolves issue where sidebar and cases API endpoints were being called unnecessarily', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Performance & Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Sync Status Display: Enhanced file-based sync status to show comprehensive data equivalent to previous database system', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated parse_log_file_for_status() method to extract detailed procedure and case counts from log files', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added warning detection for duplicate case IDs and other sync warnings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implemented accurate counting of procedures and cases created by parsing log entries', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced duration formatting to match previous MM:SS display format', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated sync status display to show warnings, duplicate counts, and comprehensive statistics', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Maintains full data compatibility with previous sync status information', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Performance Improvements: Increased default posts per page from 10 to 200 for better user experience', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated brag_book_gallery_items_per_page option default value across all relevant handlers', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Reduces need for pagination and improves gallery browsing experience', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Card Structure: Updated JavaScript-generated favorite cards to match exact HTML structure', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Ensured consistency between server-rendered and client-rendered case cards', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved responsive design and styling consistency', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🔧 Code Quality', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Code Quality: Removed all development console.log statements from JavaScript modules', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Cleaned up debugging code from all frontend JavaScript files for production', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved code maintainability and reduced bundle size', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Carousel Simplification: Removed GSAP dependency and autoplay functionality from carousel', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Simplified carousel implementation to use only native browser APIs', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Removed complex animation library dependencies for better cross-browser compatibility', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Eliminated autoplay and auto-scroll options as requested', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.7 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.7</span>
						<?php esc_html_e( 'September 11, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Dual Caching System: Implemented comprehensive dual caching strategy for optimal performance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'All data types (sidebar, cases, individual case, carousel) now use both WP Engine object cache AND transients', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic fallback mechanism ensures data persistence across cache flushes', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Intelligent cache retrieval checks object cache first (faster), falls back to transients if needed', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Legacy Transient Cleanup: Added dedicated cleanup functionality for old transient patterns', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'New "Clear Legacy Transients" button in Cache Management debug tool', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Removes obsolete transient patterns from previous plugin versions', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic detection and cleanup of orphaned cache entries', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Cache Management Tool: Resolved critical issues with cache viewing and management', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed double-prefixing issue preventing cache data from being viewed', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated queries to detect both old and new transient naming patterns', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Corrected delete operations to handle various key formats', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed clear_all_cache() method that was returning static message instead of clearing cache', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Cache Helper Functions: Enhanced to provide true dual caching', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'brag_book_set_cache() now stores in BOTH wp_cache and transients', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'brag_book_get_cache() checks wp_cache first, falls back to transients', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'brag_book_delete_cache() removes from BOTH cache layers', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Performance Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Cache Query Performance: Optimized database queries for cache management', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated SQL queries to search for multiple transient patterns efficiently', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved pagination for large cache datasets', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced cache statistics calculation', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Sub-millisecond cache retrieval on WP Engine with object cache', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Redundant storage ensures data availability even after cache flushes', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.6 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.6</span>
						<?php esc_html_e( 'September 11, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🛠️ Bug Fixes & Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Cache Management Debug Tools: Enhanced cache view functionality with comprehensive diagnostic logging', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added detailed debug logging for cache management view operations', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implemented database validation checks for transient cache items', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added expiration timestamp validation for cache debugging', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved error reporting for cache retrieval issues', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.5 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.5</span>
						<?php esc_html_e( 'September 11, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'WP Engine Diagnostics Tool: Comprehensive diagnostic system specifically designed for WP Engine hosting environments', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Environment detection and compatibility checking for WP Engine servers', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Rewrite rules testing and validation with URL pattern matching', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Query variable registration verification and debugging', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Cache status analysis including object cache and WP Engine-specific caching', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automated recommendations for optimization and troubleshooting', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'AJAX-powered interface for real-time diagnostics', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced WP Engine Cache Support: Improved cache helper functions with proper WP Engine object cache integration', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Automatic WP Engine environment detection via multiple methods', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Comprehensive cache clearing functions for all WP Engine cache layers', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Intelligent fallback to WordPress transients when object cache unavailable', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🐛 Critical Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Critical 500 Error Resolution: Fixed circular dependency in SEO On_Page class causing crashes on WP Engine', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolved infinite loop in URL parsing error logging that caused server crashes', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced URL parsing with WP Engine-specific header fallbacks (HTTP_X_ORIGINAL_URL, HTTP_X_REWRITE_URL)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added multiple layers of error handling to prevent system failures', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved graceful degradation when URL parsing encounters issues', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Missing Class Import: Fixed "Cache_Manager not found" error in SEO_Manager class', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added missing namespace import for BRAGBookGallery\\Includes\\Extend\\Cache_Manager', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolved all Cache_Manager method calls throughout SEO functionality', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Custom CSS Duplication: Fixed custom CSS being output multiple times per page', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Eliminated duplicate CSS injection from carousel shortcode handler', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Centralized all custom CSS injection through Asset_Manager for consistency', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved deduplication logic to prevent circular CSS output', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Enhanced WP Engine Compatibility', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Comprehensive improvements for WP Engine hosting environments', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced rewrite rules handling with automatic WP Engine cache clearing', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved error resilience for managed hosting constraints', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Multiple server environment detection methods for better compatibility', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Robust error handling and logging improvements', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Prevented circular dependencies in error logging systems', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced graceful degradation for component failures', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved debugging capabilities for production environments', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.4 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.4</span>
						<?php esc_html_e( 'September 8, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Added "Expand Navigation Menus" toggle in General Settings (default: false)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added "Show Filter Counts" toggle in General Settings (default: true)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added comprehensive Changelog page to admin settings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Created comprehensive CHANGELOG.md file in plugin root', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Navigation filter menus can now be expanded by default when users load the gallery page', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Filter counts can be hidden for cleaner navigation appearance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced admin interface with new toggle controls using established design patterns', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🔧 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Fixed changelog tab navigation not showing as active when visiting changelog page', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added missing page slug mapping for changelog page in Settings_Base navigation system', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Changelog tab now correctly highlights as active when viewing version history', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🧪 Testing Framework Enhancements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Fixed Playwright test syntax errors across all test suites', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolved invalid CSS selector syntax: button:has-text("text" i) → filter({ hasText: /text/i })', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed regex text locator syntax: text=/pattern/i → getByText(/pattern/i)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Corrected CSS parsing errors in case detail view, favorites functionality, and gallery cases view tests', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'All 31 end-to-end tests now pass successfully', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '✅ Test Coverage Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Gallery Cases View Tests: 7 tests covering grid display, images, interactions, load more, procedures, empty states, and responsive design', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Carousel Functionality Tests: 8 tests covering navigation, dots, autoplay, case information, mobile responsiveness, and touch gestures', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Case Detail View Tests: 8 tests covering modal display, comprehensive information, high-quality images, demographics, case notes, action buttons, responsiveness, and error states', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Favorites Functionality Tests: 8 tests covering favorite buttons, toggle states, localStorage persistence, favorites page display, empty states, management actions, user sync, and mobile responsiveness', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '📖 Documentation', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Complete version history now accessible in admin settings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Detailed changelog with categorized changes and GitHub integration', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Updated settings page changelog to reflect testing framework improvements', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced test documentation with detailed coverage descriptions', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.3 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.3</span>
						<?php esc_html_e( 'September 4, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🔧 Bug Fixes & Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Enhanced carousel case lookup with multiple fallback methods', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed cache key inconsistency between direct navigation and AJAX calls', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added unfiltered cache fallback for cases not in main procedure cache', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implemented direct API case lookup for newer cases (19xxx range)', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added malformed cache key cleanup and error handling', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved debug logging throughout case lookup process', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added data attributes to carousel items for debugging', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced find_case_by_id method with 4-method lookup approach', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed type casting error in Cache_Manager calls', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Resolved carousel link failures for specific case IDs', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '📖 Documentation', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Added comprehensive transient keys documentation', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.2 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.2</span>
						<?php esc_html_e( 'September 3, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🔧 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Fixed nudity warnings not displaying on procedure pages with nudity content', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed Load More functionality where additional cards weren\'t showing nudity warnings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed data structure mismatch in find_procedure_by_id() method calls', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Implemented per-case nudity detection instead of using global nudity flags', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced JavaScript initialization for the NudityWarningManager component', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added proper nudity warning rendering to AJAX case card generation', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.1 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.1</span>
						<?php esc_html_e( 'September 3, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🔧 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Fix favorites form 500 error on WP Engine and other hosting platforms', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Add missing SECURITY_RULES validation constant', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Improve error handling with proper exception management', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhance user experience with specific validation messages', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Update help documentation and Debug Tools descriptions', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.2.0 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-minor">v3.2.0</span>
						<?php esc_html_e( 'September 2, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 New Features', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Added /api/plugin/views POST endpoint with case view tracking', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced API test interface with views endpoint testing', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added URL hash navigation for direct tab access', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Converted FAQ section to HTML5 details elements', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Implemented JSON export/import for settings with dialog UI', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Converted debug logging checkbox to toggle switch', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🔧 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Fixed carousel asset loading for shortcodes', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed consultation page tab display and styling', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed infinite scroll functionality', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed gallery page ID configuration issue', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed Cache Management tab persistence on refresh', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed export headers already sent error', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed PHP fatal errors in gallery checker with proper null handling', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Modernized debug tools interface with clean card-based design', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Fixed critical tab switching functionality and JavaScript navigation', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved query variables debugging with accurate registration status', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Standardized table styling across all debug tool components', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced test URLs to use real API data instead of placeholders', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added gallery page creation notice to API settings', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Removed MyFavorites promotional section from gallery', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Renamed JavaScript mode to Default mode throughout plugin', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Added option to hide consultation Settings tab', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Dynamic version number in exports', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.1.0 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-minor">v3.1.0</span>
						<?php esc_html_e( 'August 26, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 Major Code Refactoring', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Separated gallery shortcode functionality to dedicated Gallery_Handler', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced HTML_Renderer with improved case detail card layout', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved carousel shortcode handler with better error handling', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced cases shortcode with better data processing', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '⚡ Performance Improvements', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Optimized asset loading with Asset_Manager enhancements', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved cache management with better expiration handling', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced data fetcher with new Data_Fetcher class', 'brag-book-gallery' ); ?></li>
						</ul>
						<h4><?php esc_html_e( '🔧 Bug Fixes', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Fixed case detail card responsive layout issues', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved filter system reliability', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced consultation form processing', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.0.x Series -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.0.15</span>
						<?php esc_html_e( 'August 25, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎨 Styling Updates', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'CSS updates and improvements for dialog components', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Enhanced mobile responsive design', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Improved sidebar and wrapper styling', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.0.14</span>
						<?php esc_html_e( 'August 25, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎨 Styling Updates', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Content component styling improvements', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.0.13</span>
						<?php esc_html_e( 'August 25, 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎨 Styling Updates', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Case detail component enhancements', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Dialog and form styling improvements', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Version 3.0.0 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-major">v3.0.0</span>
						<?php esc_html_e( 'August 2025', 'brag-book-gallery' ); ?>
					</h3>
					<div class="brag-book-gallery-card">
						<h4><?php esc_html_e( '🎉 Major Release - Complete Rewrite', 'brag-book-gallery' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Modern PHP 8.2+ architecture with namespacing', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Custom PSR-4 compatible autoloader', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Modular component architecture with clear separation of concerns', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Progressive loading with "Load More" functionality', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Advanced filtering system with multi-select capabilities', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Favorites system with localStorage and API sync', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Mobile-responsive design with hamburger menu', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Comprehensive admin settings interface', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Monaco Editor integration for custom CSS', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Advanced debug tools suite', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'WordPress VIP coding standards compliance', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'SEO optimization with sitemap generation', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Carousel shortcode with autoplay and controls', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'Consultation form integration', 'brag-book-gallery' ); ?></li>
							<li><?php esc_html_e( 'GitHub-based plugin updates', 'brag-book-gallery' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<!-- Footer Notice -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-card" style="background: #f8f9fa; border-left: 4px solid #007cba;">
					<h4><?php esc_html_e( '📋 Note about Version History', 'brag-book-gallery' ); ?></h4>
					<p><?php esc_html_e( 'This changelog shows the major releases and improvements. For a complete list of all changes, including minor fixes and internal improvements, visit the', 'brag-book-gallery' ); ?> <a href="https://github.com/bragbook2/brag-book-gallery/releases" target="_blank"><?php esc_html_e( 'GitHub Releases page', 'brag-book-gallery' ); ?></a>.</p>
					<p><?php esc_html_e( 'Each version follows semantic versioning (MAJOR.MINOR.PATCH) where:', 'brag-book-gallery' ); ?></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><strong><?php esc_html_e( 'MAJOR', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Breaking changes or complete rewrites', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'MINOR', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'New features and significant improvements', 'brag-book-gallery' ); ?></li>
						<li><strong><?php esc_html_e( 'PATCH', 'brag-book-gallery' ); ?></strong> - <?php esc_html_e( 'Bug fixes and minor improvements', 'brag-book-gallery' ); ?></li>
					</ul>
				</div>
			</div>
		</div>

		<style>
		.brag-book-gallery-changelog-content {
			max-width: 800px;
		}

		.brag-book-gallery-changelog-version {
			margin-bottom: 30px;
		}

		.brag-book-gallery-changelog-version h3 {
			display: flex;
			align-items: center;
			gap: 15px;
			margin-bottom: 15px;
			font-size: 1.3em;
			color: #1e1e1e;
		}

		.version-badge {
			display: inline-block;
			padding: 6px 12px;
			border-radius: 20px;
			font-size: 0.9em;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			color: white;
		}

		.version-major {
			background: #d63638;
		}

		.version-minor {
			background: #00a32a;
		}

		.version-patch {
			background: #007cba;
		}

		.version-beta {
			background: #f0b849;
		}

		.brag-book-gallery-changelog-version ul {
			list-style: disc;
			margin-left: 20px;
			margin-bottom: 15px;
		}

		.brag-book-gallery-changelog-version li {
			margin-bottom: 5px;
		}

		.brag-book-gallery-changelog-version h4 {
			margin-top: 20px;
			margin-bottom: 10px;
			color: #1e1e1e;
			font-size: 1.1em;
		}

		.brag-book-gallery-changelog-version h4:first-child {
			margin-top: 0;
		}
		</style>

		<?php
		$this->render_footer();
	}
}
