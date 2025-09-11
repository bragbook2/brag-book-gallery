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

namespace BRAGBookGallery\Includes\Admin;

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
class Settings_Changelog extends Settings_Base {

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

			<!-- Version 3.2.7 -->
			<div class="brag-book-gallery-section">
				<div class="brag-book-gallery-changelog-version">
					<h3>
						<span class="version-badge version-patch">v3.2.7</span>
						<?php esc_html_e( 'September 11, 2025', 'brag-book-gallery' ); ?> - <?php esc_html_e( 'Current Release', 'brag-book-gallery' ); ?>
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
							<li><?php esc_html_e( 'Separated gallery shortcode functionality to dedicated Gallery_Shortcode_Handler', 'brag-book-gallery' ); ?></li>
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