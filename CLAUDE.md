# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

BRAGBook Gallery is a WordPress plugin (v3.0.0) for displaying before/after medical and cosmetic procedure galleries. The plugin uses modern PHP 8.2+ with namespacing and follows WordPress coding standards.

## Common Development Commands

### JavaScript/Frontend Development
```bash
# Install dependencies
npm install

# Development build with watch mode
npm run watch     # Watches both JS and CSS
npm run dev       # Alias for watch

# Production build
npm run build     # Builds both JS and CSS
npm run build:js  # Build JavaScript only (webpack)
npm run build:css # Build CSS only (sass)

# Watch individual assets
npm run watch:js  # Watch JavaScript files
npm run watch:css # Watch SCSS files

# Code quality
npm run lint:js   # ESLint for JavaScript
npm run lint:css  # Stylelint for SCSS
npm run format:js # Prettier formatting
npm run clean     # Clean build directories
```

### PHP Development
```bash
# Install dependencies
composer install

# PHP quality checks
composer run phpcs       # Check WordPress coding standards
composer run phpcs:vip   # Check VIP coding standards
composer run phpcs:fix   # Auto-fix coding standard issues
composer run phpstan     # Static analysis with PHPStan

# Testing
composer run test        # Run all PHPUnit tests
composer run test:unit   # Run unit tests only
composer run test:integration # Run integration tests
composer run test:coverage    # Generate coverage report

# Combined quality checks
composer run lint        # Run phpcs + phpstan
composer run lint:vip    # Run VIP standards + phpstan
composer run qa          # Run all linting and tests
```

## Architecture

### Plugin Structure
The plugin follows a modular architecture with clear separation of concerns:

- **Main Entry**: `brag-book-gallery.php` - Plugin bootstrap file that initializes the Setup class
- **Autoloader**: Custom PSR-4 compatible autoloader in `includes/autoload.php`
- **Core Setup**: `Setup` class (`includes/core/class-setup.php`) handles all initialization using singleton pattern

### Key Components

**Core Classes** (`includes/core/`):
- `Setup`: Main plugin orchestrator, manages services and hooks
- `Updater`: Handles plugin updates from GitHub repository
- `Consultation`: Manages consultation form submissions
- `Database`: Database operations and queries
- `URL_Router`: Custom URL routing for gallery pages
- `Slug_Helper`: URL slug generation and management

**Admin** (`includes/admin/`):
- `Settings_Manager`: Centralized settings management
- `Settings_*`: Individual settings pages:
  - `Settings_General`: General settings with Custom CSS editor (WordPress CodeMirror integration)
  - `Settings_API`: API configuration
  - `Settings_API_Test`: API testing interface
  - `Settings_Debug`: Debug options
  - `Settings_Help`: Help documentation
  - `Settings_JavaScript`: JavaScript settings
  - `Settings_Mode`: Mode selection
  - `Settings_Local`: Local mode settings
  - `Settings_Default`: Default mode settings (Custom CSS section removed)
  - `Settings_Dashboard`: Dashboard overview
  - `Settings_Consultation`: Consultation form settings
- `Menu`: Admin menu registration
- `Tabs`: Settings page tab management
- `Debug_Tools`: Debugging utilities with specialized debug tools
- **Debug Tools** (`includes/admin/debug-tools/`):
  - `Gallery_Checker`: Gallery page validation
  - `Query_Var_Forcer`: Force query variable registration
  - `Rewrite_Debug`: Rewrite rules debugging
  - `Rewrite_Fix`: Fix rewrite rule issues
  - `Rewrite_Flush`: Flush rewrite rules utility
- **Admin Traits** (`includes/admin/traits/`):
  - `Trait_Ajax_Handler`: AJAX handling utilities
  - `Trait_Cache_Handler`: Cache management utilities  
  - `Trait_Render_Helper`: Rendering helper methods

**Frontend Extensions** (`includes/extend/`):
- `Shortcodes`: Main shortcode coordinator (delegates main gallery to Gallery_Shortcode_Handler)
- `Gallery_Shortcode_Handler`: Main gallery display with full implementation (separated from Shortcodes class)
- `Cases_Shortcode_Handler`: Individual case display
- `Carousel_Shortcode_Handler`: Carousel functionality with single-image-per-case display
- `Ajax_Handlers`: AJAX request processing with carousel case cache support
- `Asset_Manager`: Asset loading optimization with custom CSS injection (prevents duplicate output)
- `Cache_Manager`: Transient cache management
- `Data_Fetcher`: API data retrieval with carousel case caching by ID and seoSuffixUrl
- `HTML_Renderer`: HTML generation utilities with adaptive case detail cards
- `Rewrite_Rules_Handler`: URL rewrite management
- `Templates`: Custom template handling

**Resources** (`includes/resources/`):
- `Assets`: CSS/JS asset management and enqueuing

**SEO** (`includes/seo/`):
- `SEO_Manager`: Central SEO functionality coordinator
- `On_Page`: On-page SEO optimizations
- `Sitemap`: XML sitemap generation

**Traits** (`includes/traits/`):
- `Trait_Api`: API communication utilities
- `Trait_Tools`: Common utility functions

### JavaScript Architecture

**Entry Points** (built via webpack):
- `src/js/frontend.js` → `assets/js/brag-book-gallery.js`
- `src/js/admin.js` → `assets/js/brag-book-gallery-admin.js`
- `src/js/carousel.js` → `assets/js/brag-book-carousel.js`

**Frontend Modules** (`src/js/modules/`):
- `main-app.js`: Main application controller
- `filter-system.js`: Gallery filtering logic
- `carousel.js`: Carousel functionality
- `dialog.js`: Modal dialog management
- `favorites-manager.js`: Favorite cases management
- `mobile-menu.js`: Mobile navigation
- `search-autocomplete.js`: Search functionality
- `share-manager.js`: Social sharing
- `utilities.js`: Common utilities
- `global-utilities.js`: Global helper functions

**SCSS Structure** (`src/scss/`):
- `frontend.scss`: Main frontend styles
- `admin.scss`: Admin area styles
- `components/`: Modular component styles (carousel, cases, dialog, filters, etc.)
- `settings/`: Admin settings page styles (accordion, buttons, forms, tabs, dashboard, debug, etc.)
- `structure/`: Layout and structure styles (container, wrapper, sidebar)

### Data Flow
1. Plugin loads via `brag-book-gallery.php`
2. Autoloader registers class loading
3. `Setup::init_plugin()` initializes the plugin
4. Services are instantiated and hooks registered
5. Gallery pages use shortcodes that connect to external BRAGBook API
6. Templates handle custom page rendering
7. Assets are conditionally loaded based on page context

### External Dependencies
- BRAGBook API: External service for gallery data
- WordPress APIs: Uses standard WP hooks, options, and post types
- GitHub Updater: Plugin updates via `bragbook2/brag-book-gallery` repository

## Gallery Features

### Progressive Loading
- Initial load displays 10 cases for performance
- "Load More" button fetches additional cases via AJAX
- Complete dataset is fetched on page load for filtering capabilities
- Cases are cached using WordPress transients

### Filtering System
- Procedure filters work across the complete dataset (not just visible items)
- Filters include: Age, Gender, Ethnicity, Height, Weight
- When filters are applied, ALL matching cases are loaded (even if not initially visible)
- Filter options are generated from the complete dataset
- Multi-select filtering with AND logic between filter types

### Caching Strategy
- API responses cached using WordPress transients
- Cache duration: 1 hour (production) / 1 minute (debug mode)
- Clear cache methods:
  - Admin button in JavaScript Settings page
  - Debug Tools → Cache Management for granular control
  - WP-CLI: `wp transient delete --all`

### AJAX Endpoints
- `brag_book_load_more_cases`: Load additional cases with pagination
- `brag_book_load_filtered_cases`: Load specific cases by IDs for filtering
- `brag_book_load_filtered_gallery`: Load filtered gallery content
- `brag_book_gallery_clear_cache`: Clear all gallery transient cache
- `brag_book_load_case_details`: Load individual case details
- `brag_book_load_case_details_html`: Load case details HTML content
- `brag_book_simple_case_handler`: Handle simple case operations
- `brag_book_flush_rewrite_rules`: Flush WordPress rewrite rules

## Shortcodes

### Main Gallery
```
[brag_book_gallery]
```
- Displays full gallery with sidebar filters
- Optional: `website_property_id` parameter to override global setting

### Carousel
```
[brag_book_carousel procedure="arm-lift" limit="5"]
```
- Parameters: 
  - `procedure`: Procedure slug to filter by
  - `procedure_id`: Procedure ID (alternative to slug)
  - `member_id`: Filter by specific member/doctor
  - `limit`: Number of items (default: 10)
  - `show_controls`: Navigation arrows (true/false, default: true)
  - `show_pagination`: Dots pagination (true/false, default: true)
  - `autoplay`: Auto-advance (true/false, default: false)
  - `autoplay_delay`: Delay in ms (default: 3000)
- Supports procedure slug to ID conversion via sidebar data
- Autoplay is disabled by default for better UX

### Legacy Carousel (Backwards Compatible)
```
[bragbook_carousel_shortcode procedure="arm-lift" limit="5" title="0" details="0"]
```
- Automatically mapped to new format
- `title="0"` → `show_controls="false"`
- `details="0"` → `show_pagination="false"`

### Cases Grid
```
[brag_book_gallery_cases]
```
- Displays cases in grid layout without sidebar

### Single Case
```
[brag_book_gallery_case case_id="12345"]
```
- Displays single case with all details

### Favorites
```
[brag_book_favorites]
```
- Displays user's favorited cases
- Uses localStorage for persistent favorites
- Email capture form for new users
- Syncs with API for server-side storage

## Favorites Page Implementation

The favorites page (`/gallery-slug/myfavorites/`) uses the main gallery shortcode with special handling:

1. **URL Routing**: Rewrite rules map `/myfavorites/` to the gallery page with `favorites_page=1` query var
2. **Detection**: Main gallery shortcode detects the `favorites_page` query var
3. **Rendering**: Gallery renders normally but adds `data-favorites-page="true"` attribute
4. **JavaScript**: Main app detects this attribute and automatically calls `showFavoritesOnly()`
5. **Display**: Shows either email capture form or favorites based on localStorage user info

This approach ensures consistent layout and functionality with the main gallery while displaying favorites content.

## Recent Updates (v3.0.0)

### Admin Interface
- **Comprehensive Help Section**: Complete setup guide, troubleshooting with color-coded severity, FAQs, and system info
- **Debug Tools Suite**: 
  - Gallery Checker with card-based configuration display
  - Rewrite Debug with modern table styling  
  - Cache Management with individual item deletion and API cache integration
  - System Info with copy/download functionality
  - Rewrite Flush with card-based status display
- **Settings Organization**: 
  - Tabs for General, API, JavaScript, Consultation, Mode, Debug, Help
  - Custom notice placement system for better UX
  - Tailwind-inspired table designs without gradients
- **Enhanced UX**: 
  - Factory reset with HTML5 dialog confirmation
  - Toggle controls for features
  - Modern card-based layouts for status displays
  - HTML5 dialog elements replacing browser confirm/alert
  - Improved checkbox visibility in tables
- **Plugin Integration**: Settings link in plugin row actions on plugins page

### Frontend Features
- **Carousel Improvements**: 
  - HTML output matches exact design specifications
  - Autoplay disabled by default
  - Supports legacy shortcode format
  - Procedure slug to ID conversion
  - Single favorite button with proper styling
  - Fixed to show one image per case for variety (prevents duplicate case images)
  - Carousel case caching with seoSuffixUrl support for proper case lookups
- **Gallery Enhancements**:
  - Progressive loading with "Load More" button
  - Multi-select filtering with badge display
  - Nudity warning with blur effect and proceed button
  - Favorites functionality integrated with localStorage and API sync
  - Mobile-responsive with hamburger menu
  - Favorites page automatically displays user's saved cases
  - Refactored main gallery functionality to Gallery_Shortcode_Handler for separation of concerns
- **Case View Improvements**:
  - Adaptive card layout using flexbox for detail sections
  - Case Notes section always displays full width
  - Patient Information card hidden when empty
  - Detail cards automatically size based on content (1-4 columns)
- **Code Quality**:
  - jQuery conflicts resolved in admin area
  - Vanilla JavaScript for admin tabs and dialogs
  - HTML5 semantic markup (<article> for case cards)
  - Accessibility improvements (ARIA labels, roles)
  - ES6+ JavaScript with promises for dialog handling
  - WordPress VIP coding standards compliance

### Technical Improvements
- Automatic gallery page detection and deletion during factory reset
- Rewrite rules management with debug tools
- API token and Website Property ID validation
- Poppins font option with admin toggle
- WordPress coding standards compliance
- PHP 8.2 match expressions for cleaner code
- Fixed factory reset redirect to correct settings page
- Custom notice rendering system for controlled placement
- **Custom CSS Management**:
  - WordPress CodeMirror editor integration with line numbers and syntax highlighting
  - Real-time CSS validation and linting
  - CSS formatting and minification tools
  - Prevention of duplicate CSS output across shortcodes
  - Centralized CSS injection via Asset_Manager class
  - Security sanitization to prevent XSS attacks

## Important Considerations

- Plugin requires PHP 8.2+ and WordPress 6.8+
- Uses WordPress options for settings storage (no custom database tables)
- Follows WordPress coding standards (WPCS)
- Assets are built using webpack for JavaScript and Sass for CSS
- Plugin updates are handled via GitHub repository (`bragbook2/brag-book-gallery`)
- All button styling should be in CSS files, not inline
- API returns 10 cases per page, requiring pagination for larger datasets
- Carousel autoplay is disabled by default for better UX
- Debug mode provides additional logging and shorter cache times
- Custom CSS is stored in `brag_book_gallery_custom_css` option and injected once per page
- All shortcodes properly enqueue their assets including custom CSS
- WordPress CodeMirror editor provides syntax highlighting and CSS validation

## Debug and Development Tools

### Debug Tools Available
- **Gallery Checker**: Validates gallery page setup and configuration
- **Rewrite Debug**: Analyzes and displays active rewrite rules
- **Rewrite Fix**: Automatically fixes common rewrite issues
- **Cache Management**: View and delete individual cache items
- **System Info**: Display and export system information
- **Flush Rules**: Regenerate WordPress rewrite rules

### Development Environment
- Node.js 18+ and npm 9+ required for frontend builds
- ESLint configuration with tab indentation and single quotes
- Stylelint for SCSS with tab indentation
- Prettier formatting with 4-space tabs and single quotes
- Babel transpilation for modern JavaScript features

### File Organization
- Modular SCSS architecture with component-based styling
- JavaScript modules for maintainable frontend code
- PHP traits for reusable admin functionality
- Separate debug tools for development troubleshooting