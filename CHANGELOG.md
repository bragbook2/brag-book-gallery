# Changelog

All notable changes to the BRAGBook Gallery plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.3.3-beta8] - 2026-02-17 (Beta Release)

### Added
- **Case Detail Thumbnail Carousel**: Thumbnails now display in a proper carousel with prev/next arrow navigation and pagination dots
  - Responsive layout: 3 thumbnails on desktop, 2 on tablet, 1 on mobile
  - Arrows and pagination dots auto-hide when all thumbnails fit on screen
  - Pagination dynamically recalculates on window resize across breakpoints
  - Carousel capped at 1100px max-width and centered
  - Updated in `includes/shortcodes/class-case-handler.php`, `src/scss/components/case-detail/_index.scss`, `src/js/modules/main-app.js`

### Fixed
- **Main Image Alt Text**: Main case image now uses base SEO alt text only (removed redundant "- Angle 1" suffix)
- **Thumbnail Alt Text**: Thumbnail angles now start from "Angle 1" instead of "Angle 2"
- **Image Swap Flash**: Clicking a thumbnail now updates the main image src/alt in-place instead of replacing the entire DOM, eliminating page flash

---

## [4.3.3-beta1] - 2026-02-09 (Beta Release)

### Added
- **HIPAA-Compliant Sync Registry**: New unified `wp_brag_sync_registry` table replaces the old `wp_brag_case_map` table, tracking all synced items (cases, procedures, doctors) with API-to-WordPress ID mapping
- **Orphan Detection & Cleanup**: Detects WordPress items (posts and terms) that no longer exist in the BRAGBook API after a sync completes
- **Orphan Manager** (`class-orphan-manager.php`): New class for orphan detection, deletion, and HIPAA-compliant audit logging (no PHI in logs)
- **Manual Orphan Review**: Admin UI panel after Stage 3 sync shows orphaned items grouped by type with names, allowing preview before deletion
- **Automatic Orphan Cleanup**: REST/automatic syncs auto-detect and remove orphans after successful completion
- **AJAX Endpoints**: New `brag_book_sync_detect_orphans` and `brag_book_sync_delete_orphans` endpoints for admin orphan management
- **Database Migration**: Automatic migration from `wp_brag_case_map` to `wp_brag_sync_registry` with data preservation (DB version 1.3.0)
- **Multi-tenant Isolation**: Registry entries include `api_token` and `property_id` for tenant isolation
- **Session-based Tracking**: Sync session IDs persist across batched Stage 3 HTTP requests for accurate orphan detection

---

## [4.3.0] - 2025-01-19 (Stable Release)

### Fixed
- **Favorites Removal API**: Fixed 400 error when removing favorites from "My Favorites" page
  - Added proper `caseProcedureId` and `procedureId` fallbacks from multiple meta sources
  - Fixed `ajax_get_case_by_api_id` to search all possible meta keys (`brag_book_gallery_procedure_case_id`, `brag_book_gallery_original_case_id`, `brag_book_gallery_case_id`)
  - Card is now removed from the view with animation when successfully unfavoriting
  - Added state restoration when API call fails
  - Updated in `includes/shortcodes/class-favorites-handler.php` and `src/js/modules/favorites-manager.js`

- **Case Carousel Pagination**: Improved accessibility and fixed invalid HTML
  - Changed pagination dots from anchor tags to semantic button elements
  - Added ARIA attributes (`role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls`, `aria-label`)
  - Fixed invalid nested anchor HTML in v3 card type by moving pagination outside anchor wrapper
  - Added IntersectionObserver to update active dot on scroll
  - Updated in `includes/shortcodes/class-cases-handler.php` and `src/js/modules/main-app.js`

- **Mobile Header Visibility**: Fixed mobile header disappearing between 1024px and 1280px
  - JavaScript breakpoint now matches CSS media query (1279px)
  - Mobile header visible from 0-1279px, sidebar visible from 1280px+
  - Updated in `src/js/modules/mobile-menu.js`

---

## [4.2.0] - 2025-01-09 (Stable Release)

### Enhanced
- **SEO Plugin Detection**: Plugin now detects Yoast SEO, Rank Math, and All in One SEO
  - When a major SEO plugin is active, the custom sitemap is not created separately
  - Gallery URLs are added to the SEO plugin's sitemap index instead
  - Prevents duplicate sitemap functionality and conflicts
  - Updated in `includes/seo/class-sitemap.php`

- **Column View Layout**: Improved procedure category grid layout
  - Columns now cap at 4 maximum regardless of category count
  - Additional categories wrap to the next row automatically
  - Better visual presentation for sites with many procedure categories
  - Updated in `includes/shortcodes/class-gallery-handler.php`

### Fixed
- **Carousel Image Fallback**: Added fallback for case carousel images
  - When high-res URLs are not available, post-processed URLs are used instead
  - Ensures carousel functionality works even without high-res images
  - Updated in `includes/shortcodes/class-cases-handler.php`

---

## [4.1.0] - 2025-12-24 (Stable Release)

### Enhanced
- **Case View Tracking**: Improved view tracking reliability for case detail pages
  - Added `data-procedure-case-id` attribute to case detail view wrappers
  - JavaScript now reads case ID directly from DOM data attributes instead of parsing URLs
  - More reliable tracking across different URL formats and page contexts
  - Updated in `includes/shortcodes/class-case-handler.php`

### Fixed
- **Duplicate View Tracking**: Fixed issue where case views could be tracked twice
  - Removed redundant tracking call from `handleDirectCaseUrl()` function
  - Views are now tracked once via `trackPageView()` when case detail view is detected
  - Updated in `src/js/modules/main-app.js`

### Developer
- **Enhanced Logging**: Improved view tracking API response logging for debugging
  - Added detailed response body logging on successful API calls
  - Better visibility into view tracking success/failure states
  - Updated in `includes/shortcodes/class-gallery-handler.php`

---

## [4.0.0] - 2025-12-09 (Stable Release)

This major release consolidates all features and improvements from the 3.3.2 beta series into a stable production release.

### Added
- **Doctors Taxonomy**: New `brag_book_doctors` taxonomy for managing doctor profiles
  - Term meta fields: First Name, Last Name, Suffix, Profile URL, Profile Photo, and Member ID
  - Doctors submenu in BRAG book admin menu (when property ID 111 is enabled)
  - Automatic doctor term creation during Stage 3 data sync from case creator information
- **Doctor Profile URL Field**: `brag_book_gallery_doctor_profile_url` meta field for case post types
- **Doctor Suffix Field**: `brag_book_gallery_doctor_suffix` meta field for case post types
- **Doctor Details Display**: "Show Doctor Details" toggle in Display Settings
- **Doctor Name Field**: Doctor Name field in case post meta (Basic Information tab)
- **Member ID Field**: Member ID number field in case post meta
- **Minified Assets**: Intelligent asset minification system
  - Production mode loads `.min.js` and `.min.css` files (50-54% smaller JS, 10-13% smaller CSS)
  - Development mode (`SCRIPT_DEBUG` enabled) loads non-minified versions
- **Procedure Links**: Clickable links to procedures in case card details with hover animations

### Enhanced
- **Case View Doctor Profile**: Doctor profile photo and name displayed below case title (property ID 111)
- **Cases Grid Doctor Display**: Case cards display doctor photo and name instead of procedure when enabled
- **V3 Card Doctor Display**: V3 cards show doctor name in overlay when "Show Doctor Details" is enabled
- **Search Input Accessibility**: Improved ARIA attributes for better screen reader support
- **HTML Semantics**: Improved semantic HTML structure throughout the plugin

### Fixed
- **Sitemap Generation**: Fixed critical `TypeError` in Sitemap class
- **Stage 3 Sync Title Assignment**: Fixed case post titles being overwritten with incorrect procedure names
- **V3 Card Image Clickability**: Images in v3 cards are now fully clickable
- **Landing Page Text Editor**: Replaced TinyMCE with Trumbowyg WYSIWYG editor
- **Gallery Landing Page Error**: Fixed null reference error in procedure referrer tracking
- **Generate Favorites Page Button**: Fixed button functionality and status checking
- **Case Navigation URLs**: Fixed navigation buttons to use full absolute URLs

### Styling
- New CSS styles for doctor profile section in case view header
- New CSS styles for doctor avatar and name in case card overlays
- Updated consultation chart colors for consistency

---

## [3.3.2-beta15] - 2025-12-01 (Previous Beta)

### Added
- **Doctors Taxonomy**: New `brag_book_doctors` taxonomy for managing doctor profiles
- Term meta fields: First Name, Last Name, Suffix, Profile URL, Profile Photo, Member ID
- Doctors submenu in BRAG book admin menu (when property ID 111 is enabled)
- Automatic doctor term creation during Stage 3 data sync

### Enhanced
- **Case View Doctor Profile**: Doctor profile photo and name displayed below case title
- **Cases Grid Doctor Display**: Case cards display doctor photo and name when enabled
- Updated v2 and v3 card overlays to support doctor display mode

### Styling
- New CSS styles for doctor profile section in case view header
- New CSS styles for doctor avatar and name in case card overlays

## [3.3.2-beta14] - 2025-11-13

### Enhanced
- **Search Input Accessibility**: Improved search input ARIA attributes for better screen reader support
  - Added `role="combobox"` to mobile search input for proper accessibility compliance
  - Standardized class names across mobile and desktop search inputs (both use `brag-book-gallery-search-input`)
  - Enhanced ARIA labels, autocomplete attributes, and controls
  - Updated in `includes/shortcodes/class-gallery-handler.php:891-906, 943-956, 2251-2264`
- **HTML Semantics**: Improved semantic HTML structure throughout the plugin
  - Changed non-heading titles from `<h4>` to `<p>` tags where headings were not semantically appropriate
  - Updated Gallery Checker "Page Status" title in `includes/admin/debug/class-gallery-checker.php:424`
  - Updated nudity warning title in `src/js/modules/filter-system.js:932`
  - Improves document outline and accessibility for screen readers
- **Chart Colors**: Updated consultation chart colors in Communications page
  - Changed chart border and background colors from `#D94540` to `#CC0000` for consistency
  - Updated in `includes/admin/pages/class-communications-page.php:587, 623-624`

## [3.3.2-beta13] - 2025-11-12

### Added
- **Minified Assets**: Implemented intelligent asset minification system
  - Production mode loads `.min.js` and `.min.css` files for optimal performance
  - Development mode (`SCRIPT_DEBUG` enabled) loads non-minified versions for debugging
  - Webpack generates both minified and non-minified JavaScript files
  - Sass generates both compressed and expanded CSS files
  - File size reductions: JavaScript 50-54%, CSS 10-13%
  - Added `get_asset_suffix()` helper method to determine asset file suffix
  - Updated in `webpack.config.js`, `package.json`, `includes/resources/class-asset-manager.php:105-107`, `includes/resources/class-assets.php:353-398`, and `includes/admin/pages/class-sync-page.php:2062-2093`
- **Procedure Links**: Added clickable links to procedures in case card details
  - Each procedure in "Procedures Performed" list now links to its taxonomy page via `get_term_link()`
  - Includes hover animations with subtle lift effect and box shadow
  - Added `brag-book-gallery-case-card-procedures-list__link` CSS class with full styling
  - Proper ARIA labels for accessibility ("View [Procedure] cases")
  - Enhanced user navigation to related cases by procedure
  - Enhanced in `includes/shortcodes/class-cases-handler.php:2457-2494` and `src/scss/components/case/_procedures-list.scss`

### Fixed
- **Sitemap Generation**: Fixed critical `TypeError` in Sitemap class
  - Resolved "Return value must be of type string, null returned" error
  - Fixed undefined variable references when Cache_Manager was removed
  - Updated `get_sitemap_content()`, `generate_sitemap()`, `is_rate_limited()`, and `get_cached_data()` methods
  - All variables now properly initialized before use (lines 306, 353, 1196, 1232)
  - Fixed in `includes/seo/class-sitemap.php`

## [3.3.2-beta10] - 2025-11-10

### Added
- **Doctor Profile URL Field**: Added `brag_book_gallery_doctor_profile_url` meta field to case post types
  - Allows storing URL to doctor's profile page
  - Field type: URL input with validation
  - Added to case meta box in WordPress admin
  - Automatically saved with proper URL sanitization (`esc_url_raw`)
- **Doctor Suffix Field**: Added `brag_book_gallery_doctor_suffix` meta field to case post types
  - Stores professional suffix (e.g., MD, PhD, DDS)
  - Field type: Text input
  - Added to case meta box in WordPress admin
  - Sanitized using `sanitize_text_field`

### Enhanced
- **V3 Card Doctor Display**: Enhanced v3 card type to show doctor name when "Show Doctor Details" option is enabled
  - Doctor name now displays in card overlay instead of procedure name when toggle is active
  - Controlled by `brag_book_gallery_show_doctor` option (set to `1` to enable)
  - Falls back to procedure name if doctor name is not available
  - Works with both `render_case_card` and `render_wordpress_case_card` methods
  - Enhanced in `includes/shortcodes/class-cases-handler.php`
- **V3 Card Case Number**: Case number now hidden on v3 cards when doctor name display is enabled
  - Provides cleaner appearance when showing doctor information
  - Case number still displays when doctor option is disabled

## [3.3.2-beta9] - 2025-11-07

### Fixed
- **Stage 3 Sync Title Assignment**: Fixed issue where case post titles were being overwritten with incorrect procedure names
  - Moved taxonomy assignment to occur before `save_api_response_data()` call
  - Ensures correct procedure term is available when title is regenerated
  - Previously, cases from different procedures could all show the same procedure name (e.g., all showing "Tummy Tuck")
  - Fixed in `includes/sync/class-chunked-data-sync.php:1619-1643`

### Enhanced
- **V3 Card Image Clickability**: Added anchor link around images in v3 card type
  - Images in v3 cards are now fully clickable and link to case detail page
  - Previously only the arrow button in the overlay was clickable
  - V2 cards remain unchanged (arrow-only clickability)
  - Enhanced in `includes/shortcodes/class-cases-handler.php:2290-2340`

## [3.3.2-beta8] - 2025-11-06

### Fixed
- **General Bug Fixes**: Various bug fixes and stability improvements
- **Case Details**: Enhanced case details display and functionality
- **Titles**: Improved title handling and display
- **Sync Updates**: Updated synchronization functionality and reliability

## [3.3.2-beta7] - 2025-11-04

### Added
- **Doctor Details Display**: New "Show Doctor Details" toggle setting in Display Settings
  - Allows administrators to control visibility of doctor information on case pages
  - Setting: `brag_book_gallery_show_doctor_details` (default: false)
- **Doctor Name Field**: Added Doctor Name field to case post meta in Basic Information tab
  - Stores doctor name as `_brag_book_gallery_doctor_name` post meta
  - Displayed in admin interface for case management
- **Member ID Field**: Added Member ID number field to case post meta in Basic Information tab
  - Stores member ID as `_brag_book_gallery_member_id` post meta
  - Useful for tracking and organizing cases by member

### Fixed
- **Generate Favorites Page Button**: Fixed button functionality and status checking
  - Added initial status check on page load to show correct button state
  - Button now properly detects existing favorites page before showing generate option
  - Fixed edge case where button showed incorrect state after page refresh

## [3.3.2-beta2] - 2025-10-09

### Fixed
- **Landing Page Text Editor**: Replaced TinyMCE with Trumbowyg WYSIWYG editor to resolve AMD/RequireJS conflicts
  - Removed problematic WordPress TinyMCE editor that conflicted with Monaco Editor
  - Implemented lightweight Trumbowyg editor with visual and HTML editing modes
  - Fixed "Can only have one anonymous define call per script file" error in `includes/admin/pages/class-general-page.php:602-612`
  - Vanilla ES6 JavaScript implementation for better performance
  - Toolbar includes formatting, bold, italic, links, lists, and HTML view toggle
- **Gallery Landing Page Error**: Fixed null reference error in procedure referrer tracking
  - Added null check in `src/js/modules/global-utilities.js:210` before accessing regex match results
  - Resolved "Cannot read properties of null (reading '1')" JavaScript error
  - Error only occurred when visiting gallery landing page (non-procedure pages)

## [3.3.2-beta1] - 2025-10-09

### Added
- Initial beta release for testing multi-channel release system

## [3.3.1] - 2025-10-08 (Current Release)

### Added
- **Column View**: New shortcode view for displaying procedures organized by parent categories
  - Adaptive grid layout automatically adjusts columns based on number of parent categories (1-5 columns)
  - Responsive breakpoints for mobile, tablet, and desktop displays
  - Usage: `[brag_book_gallery view="column"]`
- **Procedure Banner Images**: Support for banner images on procedure parent categories
  - Retrieves banner images from `banner_image` term meta
  - Implements responsive `<picture>` elements with multiple image sizes
  - Includes lazy loading and async decoding for performance
  - Automatic fallback to parent category name for alt text
- **Multi-Channel Release System**: Beta, RC, and stable release channels
  - Users can opt into beta or RC releases for early access to new features
  - Channel selection available in General Settings
  - Automatic filtering of GitHub releases based on selected channel
  - Enhanced update notification system with channel-specific warnings

### Fixed
- **Asset Versioning**: Updated Asset_Manager VERSION constant to match plugin version
- **Column View Assets**: Added missing asset enqueuing in `handle_column_view()` method

## [3.3.0] - 2025-10-07

### Added
- **Automatic Sync Cron Jobs**: Full implementation of WordPress cron-based automatic synchronization
  - Added weekly cron schedule support to WordPress (not included by default)
  - Implemented custom date/time scheduling for one-time sync events
  - Created visual cron status display on Sync Settings page showing next scheduled sync
  - Added "Test Cron Now" button for manual cron job testing and validation
  - Full 3-stage sync execution via cron (Procedures, Manifest, Cases)
  - Detailed logging for all cron operations for debugging
  - Automatic schedule clearing when sync is disabled
- **Cron Status Monitoring**: Real-time visibility of scheduled sync operations
  - Shows exact date/time of next scheduled sync
  - Displays human-readable countdown (e.g., "In 6 days")
  - Indicates overdue syncs when cron hasn't executed on schedule
  - Integrated status display directly in admin interface

### Fixed
- **Carousel Cross-Origin Images**: Fixed Firefox cookie rejection errors for Cloudflare-protected images from BRAGBook API
  - Added `crossorigin="anonymous"` attributes to all external image elements in JavaScript modules
  - Prevents Firefox from rejecting Cloudflare `__cf_bm` cookies when loading before/after images
  - Affected files: filter-system.js, global-utilities.js, main-app.js, carousel.js
- **JavaScript Build Errors**: Fixed syntax errors in main-app.js caused by console statement cleanup
  - Removed orphaned object literals left after automated console.log removal
  - Fixed broken JavaScript that was preventing webpack builds from completing
- **Nudity Warnings on Case Cards**: Fixed nudity warnings not appearing on individual case cards for procedures with nudity flags
  - Added missing nudity warning rendering logic to `render_wordpress_case_card()` method in Cases_Handler class
  - Fixed inconsistent nudity detection by using WordPress taxonomy meta instead of API sidebar data
  - Unified nudity detection approach across gallery and sidebar handlers for consistency
- **Favorites Display**: Enhanced favorites functionality with user information display
  - Added user email and favorites count display after content title on favorites page
  - Updated card HTML structure to match exact design specifications
  - Improved favorites grid rendering with proper user info integration
- **Procedure Taxonomy Pages**: Prevented unwanted API calls on procedure taxonomy pages
  - Fixed `is_bragbook_page()` method in Assets class to exclude procedure taxonomy pages
  - Added explicit check using `is_tax('procedures')` to prevent frontend assets from loading
  - Resolves issue where sidebar and cases API endpoints were being called unnecessarily

### Enhanced
- **Code Quality**: Removed all development console.log statements from JavaScript modules
  - Cleaned up debugging code from all frontend JavaScript files for production
  - Improved code maintainability and reduced bundle size
- **Carousel Simplification**: Removed GSAP dependency and autoplay functionality from carousel
  - Simplified carousel implementation to use only native browser APIs
  - Removed complex animation library dependencies for better cross-browser compatibility
  - Eliminated autoplay and auto-scroll options as requested
- **Performance Improvements**: Increased default posts per page from 10 to 200 for better user experience
  - Updated `brag_book_gallery_items_per_page` option default value across all relevant handlers
  - Reduces need for pagination and improves gallery browsing experience
- **Card Structure**: Updated JavaScript-generated favorite cards to match exact HTML structure
  - Ensured consistency between server-rendered and client-rendered case cards
  - Improved responsive design and styling consistency
- **Sync Status Display**: Enhanced file-based sync status to show comprehensive data equivalent to previous database system
  - Updated `parse_log_file_for_status()` method to extract detailed procedure and case counts from log files
  - Added warning detection for duplicate case IDs and other sync warnings
  - Implemented accurate counting of procedures and cases created by parsing log entries
  - Enhanced duration formatting to match previous MM:SS display format
  - Updated sync status display to show warnings, duplicate counts, and comprehensive statistics
  - Maintains full data compatibility with previous sync status information

### Attempted
- **Firefox Carousel Navigation**: Extensive debugging and attempted fixes for Firefox-specific carousel navigation issues
  - Investigated Firefox scrollTo() compatibility and scroll behavior differences
  - Attempted transform-based navigation solutions for Firefox browser
  - Ultimately reverted to universal implementation due to complexity of Firefox-specific workarounds
  - Firefox navigation issues remain unresolved but codebase is clean and maintainable

## [3.2.8] - 2025-09-11

### Fixed
- **Procedure Taxonomy Pages**: Prevented unwanted API calls on procedure taxonomy pages
  - Fixed `is_bragbook_page()` method in Assets class to exclude procedure taxonomy pages
  - Added explicit check using `is_tax('procedures')` to prevent frontend assets from loading
  - Resolves issue where sidebar and cases API endpoints were being called unnecessarily

### Changed
- **Version Numbering**: Updated to semantic versioning 3.3.0 for new feature improvements

## [3.2.8] - 2025-09-11

### Added
- **Delete All Rewrite Rules**: New functionality in Flush Rules debug tool to completely remove all rewrite rules
  - Aggressive deletion mechanism with multiple methods to ensure complete removal
  - Direct database deletion using SQL queries
  - Temporary blocking of WordPress rule regeneration (60 seconds)
  - Confirmation count showing exactly how many rules were deleted
  - Detailed logging of deletion operations

### Fixed
- **Rewrite Rules Detection**: Fixed gallery rules detection in flush rules tool
  - Updated to detect modern query variables (brag_book_gallery_view, brag_gallery_slug, etc.)
  - Fixed issue where gallery rules were showing as 0 when they actually existed
  - Added support for both legacy and current query variable patterns
- **Verify Rules Function**: Enhanced to check database directly without triggering regeneration
  - Uses direct SQL query to check if rules exist
  - Shows clear success message when rules are deleted
  - Prevents WordPress from auto-regenerating rules during verification

### Improved
- **Rewrite Flush Tool**: Enhanced user interface and feedback
  - Added "Danger Zone" section for destructive operations
  - Double confirmation required for delete all rules operation
  - Shows deletion history with exact counts (total and gallery-specific)
  - Better error handling with detailed error messages
  - Visual status indicators for rule presence/absence

## [3.2.7] - 2025-09-11

### Added
- **Dual Caching System**: Implemented comprehensive dual caching strategy for optimal performance
  - All data types (sidebar, cases, individual case, carousel) now use both WP Engine object cache AND transients
  - Automatic fallback mechanism ensures data persistence across cache flushes
  - Intelligent cache retrieval checks object cache first (faster), falls back to transients if needed
- **Legacy Transient Cleanup**: Added dedicated cleanup functionality for old transient patterns
  - New "Clear Legacy Transients" button in Cache Management debug tool
  - Removes obsolete transient patterns from previous plugin versions
  - Automatic detection and cleanup of orphaned cache entries

### Fixed
- **Cache Management Tool**: Resolved critical issues with cache viewing and management
  - Fixed double-prefixing issue preventing cache data from being viewed
  - Updated queries to detect both old and new transient naming patterns
  - Corrected delete operations to handle various key formats
  - Fixed clear_all_cache() method that was returning static message instead of clearing cache
- **Cache Helper Functions**: Enhanced to provide true dual caching
  - brag_book_set_cache() now stores in BOTH wp_cache and transients
  - brag_book_get_cache() checks wp_cache first, falls back to transients
  - brag_book_delete_cache() removes from BOTH cache layers

### Improved
- **Cache Query Performance**: Optimized database queries for cache management
  - Updated SQL queries to search for multiple transient patterns efficiently
  - Improved pagination for large cache datasets
  - Enhanced cache statistics calculation

## [3.2.6] - 2025-09-11

### Fixed
- **Cache Management Debug Tools**: Enhanced cache view functionality with comprehensive diagnostic logging
  - Added detailed debug logging for cache management view operations
  - Implemented database validation checks for transient cache items
  - Added expiration timestamp validation for cache debugging
  - Improved error reporting for cache retrieval issues

## [3.2.5] - 2025-09-11

### Added
- **WP Engine Diagnostics Tool**: Comprehensive diagnostic system specifically designed for WP Engine hosting environments
  - Environment detection and compatibility checking for WP Engine servers
  - Rewrite rules testing and validation with URL pattern matching
  - Query variable registration verification and debugging
  - Cache status analysis including object cache and WP Engine-specific caching
  - Automated recommendations for optimization and troubleshooting
  - AJAX-powered interface for real-time diagnostics
- **Enhanced WP Engine Cache Support**: Improved cache helper functions with proper WP Engine object cache integration
  - Automatic WP Engine environment detection via multiple methods
  - Comprehensive cache clearing functions for all WP Engine cache layers
  - Intelligent fallback to WordPress transients when object cache unavailable

### Fixed
- **Critical 500 Error Resolution**: Fixed circular dependency in SEO On_Page class causing crashes on WP Engine
  - Resolved infinite loop in URL parsing error logging that caused server crashes
  - Enhanced URL parsing with WP Engine-specific header fallbacks (HTTP_X_ORIGINAL_URL, HTTP_X_REWRITE_URL)
  - Added multiple layers of error handling to prevent system failures
  - Improved graceful degradation when URL parsing encounters issues
- **Missing Class Import**: Fixed "Cache_Manager not found" error in SEO_Manager class
  - Added missing namespace import for BRAGBookGallery\Includes\Extend\Cache_Manager
  - Resolved all Cache_Manager method calls throughout SEO functionality
- **Custom CSS Duplication**: Fixed custom CSS being output multiple times per page
  - Eliminated duplicate CSS injection from carousel shortcode handler
  - Centralized all custom CSS injection through Asset_Manager for consistency
  - Improved deduplication logic to prevent circular CSS output

### Enhanced
- **WP Engine Compatibility**: Comprehensive improvements for WP Engine hosting environments
  - Enhanced rewrite rules handling with automatic WP Engine cache clearing
  - Improved error resilience for managed hosting constraints
  - Multiple server environment detection methods for better compatibility
- **Error Handling**: Robust error handling and logging improvements
  - Prevented circular dependencies in error logging systems
  - Enhanced graceful degradation for component failures
  - Improved debugging capabilities for production environments

## [3.2.4] - 2025-09-08

### Added
- **New Settings Features**:
  - "Expand Navigation Menus" toggle in General Settings (default: false)
  - "Show Filter Counts" toggle in General Settings (default: true)
  - **"Enable Favorites" toggle in General Settings (default: true)** - Allows administrators to completely disable favorites functionality site-wide
  - Comprehensive Changelog page to admin settings
  - Created comprehensive CHANGELOG.md file in plugin root
- **Testing Framework**:
  - Comprehensive end-to-end testing framework with Playwright
  - PHPUnit testing configuration for unit and integration tests
  - Four complete test suites covering all major gallery functionality
  - Mock API responses for realistic testing scenarios
  - Responsive design testing across multiple viewports (desktop, tablet, mobile)

### Fixed
- **Admin Interface**: Fixed changelog tab navigation not showing as active when visiting changelog page
  - Added missing page slug mapping for `brag-book-gallery-changelog` in Settings_Base navigation system
  - Changelog tab now correctly highlights as active when viewing version history
- **Testing Framework**: Fixed Playwright test syntax errors across all test suites
  - Fixed invalid CSS selector syntax `button:has-text("text" i)` → proper Playwright `filter({ hasText: /text/i })` syntax
  - Fixed regex text locator syntax `text=/pattern/i` → `getByText(/pattern/i)` approach
  - Resolved CSS parsing errors in case detail view, favorites functionality, and gallery cases view tests
  - All 31 end-to-end tests now pass successfully

### Enhanced
- **Settings Interface**:
  - Navigation filter menus can now be expanded by default when users load the gallery page
  - Filter counts can be hidden for cleaner navigation appearance
  - Enhanced admin interface with new toggle controls using established design patterns
- **Favorites System Control**:
  - Conditional rendering of favorites buttons throughout gallery, carousel, and case views
  - Automatic disabling of `/myfavorites/` page routing when favorites are disabled
  - Centralized favorites setting management with Settings_Helper class and static caching for performance
- **Test Coverage**: Enhanced comprehensive test coverage for gallery functionality:
  - Gallery Cases View Tests: 7 tests covering grid display, images, interactions, load more, procedures, empty states, and responsive design
  - Carousel Functionality Tests: 8 tests covering navigation, dots, autoplay, case information, mobile responsiveness, and touch gestures
  - Case Detail View Tests: 8 tests covering modal display, comprehensive information, high-quality images, demographics, case notes, action buttons, responsiveness, and error states
  - Favorites Functionality Tests: 8 tests covering favorite buttons, toggle states, localStorage persistence, favorites page display, empty states, management actions, user sync, and mobile responsiveness
- **Documentation**:
  - Complete version history now accessible in admin settings
  - Detailed changelog with categorized changes and GitHub integration
  - Updated settings page changelog to reflect testing framework improvements
  - Enhanced test documentation with detailed coverage descriptions

## [3.2.3] - Previous Release

### Added
- Enhanced debug tools styling
- API endpoints improvements
- Admin interface enhancements
- Gallery Checker with card-based configuration display
- Rewrite Debug with modern table styling
- Cache Management with individual item deletion

### Fixed
- jQuery conflicts resolved in admin area
- Factory reset redirect to correct settings page
- Custom notice rendering system for controlled placement

## [3.2.2] - Previous Release

### Enhanced
- Debug Tools Suite with comprehensive system information
- Settings organization with improved tab navigation
- Modern card-based layouts for status displays
- HTML5 dialog elements replacing browser confirm/alert

## [3.2.1] - Previous Release

### Added
- Custom CSS Management with Monaco Editor integration
- Advanced code editing features with IntelliSense and syntax highlighting
- Real-time CSS validation and linting
- CSS formatting tools with dedicated "Format CSS" button

### Fixed
- Prevention of duplicate CSS output across shortcodes
- Centralized CSS injection via Asset_Manager class
- Security sanitization to prevent XSS attacks

## [3.2.0] - Previous Release

### Added
- Complete frontend rewrite with modern JavaScript modules
- Comprehensive favorites functionality with localStorage and API sync
- Progressive loading with "Load More" button functionality
- Multi-select filtering with badge display
- Mobile-responsive design with hamburger menu
- Carousel improvements with HTML output matching design specifications
- Nudity warning with blur effect and proceed button

### Enhanced
- Case view improvements with adaptive card layout using flexbox
- Detail cards automatically size based on content (1-4 columns)
- Accessibility improvements (ARIA labels, roles)
- ES6+ JavaScript with promises for dialog handling

### Technical
- WordPress VIP coding standards compliance
- PHP 8.2+ with modern match expressions
- Automatic gallery page detection and deletion during factory reset
- Rewrite rules management with debug tools

---

**Legend:**
- 🆕 **Added**: New features
- 🔧 **Changed**: Changes in existing functionality
- 🚀 **Enhanced**: Improvements to existing features
- 🐛 **Fixed**: Bug fixes
- 🔒 **Security**: Security improvements
- ⚠️ **Deprecated**: Soon-to-be removed features
- 🗑️ **Removed**: Removed features

For more detailed information about each release, please visit the [GitHub repository](https://github.com/bragbook2/brag-book-gallery).
