# Changelog

All notable changes to the BRAGBook Gallery plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.3.0] - 2025-09-18 (Current Release)

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