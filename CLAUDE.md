# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

BRAGBook Gallery is a WordPress plugin (v3.3.0) for displaying before/after medical and cosmetic procedure galleries. The plugin uses modern PHP 8.2+ with namespacing and follows WordPress coding standards.

## Common Development Commands

### JavaScript/Frontend Development
```bash
# Install dependencies
npm install

# Development build with watch mode
npm run watch     # Watches both JS and CSS concurrently
npm run dev       # Alias for watch

# Production build
npm run build     # Builds both JS and CSS
npm run build:js  # Build JavaScript only (webpack)
npm run build:css # Build CSS only (sass)

# Watch individual assets
npm run watch:js  # Watch JavaScript files
npm run watch:css # Watch SCSS files

# Code quality
npm run lint:js   # ESLint for JavaScript (tab indents, single quotes)
npm run lint:css  # Stylelint for SCSS (tab indents, single quotes)
npm run format:js # Prettier formatting (4-space tabs, single quotes)
npm run clean     # Clean build directories

# Plugin packaging
npm run package   # Create distribution package
npm run release   # Full release build with zip creation

# End-to-end testing
npm run test:e2e         # Run Playwright tests
npm run test:e2e:ui      # Playwright with UI
npm run test:e2e:debug   # Debug mode
npm run test:e2e:headed  # Run with visible browser
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
composer run format      # Auto-fix code standards
```

## Architecture

### Plugin Structure
The plugin follows a modular architecture with clear separation of concerns:

- **Main Entry**: `brag-book-gallery.php` - Plugin bootstrap file that initializes the Setup class
- **Autoloader**: Custom PSR-4 compatible autoloader in `includes/autoload.php`
- **Core Setup**: `Setup` class (`includes/core/class-setup.php`) handles all initialization using singleton pattern

### Key Components

**Core Classes** (`includes/core/`):
- `Setup`: Main plugin orchestrator with singleton pattern, manages services and hooks
- `Updater`: Handles plugin updates from GitHub repository (`bragbook2/brag-book-gallery`)
- `Settings_Helper`: Settings management and validation
- Traits:
  - `Trait_Tools`: Common utility functions
  - `Trait_Api`: API communication utilities
  - `Trait_Sanitizer`: Input sanitization utilities

**Admin** (`includes/admin/`):
- `Settings_Manager`: Centralized settings management
- Pages (`includes/admin/pages/`):
  - `General_Page`: General settings and Custom CSS editor (Monaco Editor)
  - `API_Page`: API configuration
  - `API_Test_Page`: API testing interface
  - `Sync_Page`: Data synchronization interface
  - `Debug_Page`: Debug options
  - `Help_Page`: Help documentation
  - `Dashboard_Page`: Dashboard overview
  - `Communications_Page`: Communication settings
  - `Default_Page`: Default mode settings
  - `Local_Page`: Local mode settings
  - `Changelog_Page`: Version history
- Debug Tools (`includes/admin/debug/`):
  - `Debug_Tools`: Main debug tools coordinator
  - `Gallery_Checker`: Gallery page validation
  - `System_Info`: System information display
- UI Components (`includes/admin/ui/`):
  - `Tabs`: Settings page tab management
  - Traits:
    - `Trait_Ajax_Handler`: AJAX handling utilities
    - `Trait_Render_Helper`: Rendering helper methods

**Data Layer** (`includes/data/`):
- `Database`: Database operations and queries with caching

**Sync Module** (`includes/sync/`):
- `Data_Sync`: Handles data synchronization with external API

**Frontend Extensions** (`includes/extend/`):
- `Post_Types`: Custom post type registration
- `Taxonomies`: Custom taxonomy registration
- `Template_Manager`: Custom template handling

**Resources** (`includes/resources/`):
- `Assets`: CSS/JS asset management and enqueuing
- `Asset_Manager`: Asset loading optimization with custom CSS injection

**Shortcodes** (`includes/shortcodes/`):
- `Gallery_Handler`: Main gallery display with auto-detection
- `Case_Handler`: Single case display
- `Cases_Handler`: Multiple cases grid
- `Favorites_Handler`: User favorites management
- `Carousel_Handler`: Carousel functionality
- `Sidebar_Handler`: Sidebar filter management
- `HTML_Renderer`: HTML generation utilities

**SEO** (`includes/seo/`):
- `SEO_Manager`: Central SEO functionality coordinator
- `On_Page`: On-page SEO optimizations
- `Sitemap`: XML sitemap generation

**REST API** (`includes/rest/`):
- `Endpoints`: REST API endpoint registration

**Communications** (`includes/communications/`):
- `Communications`: Email and notification handling

### JavaScript Architecture

**Entry Points** (built via webpack):
- `src/js/frontend.js` → `assets/js/brag-book-gallery.js`
- `src/js/admin.js` → `assets/js/brag-book-gallery-admin.js`
- `src/js/sync-admin.js` → `assets/js/brag-book-gallery-sync-admin.js`

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
- `components/`: Modular component styles (carousel, cases, dialog, filters, nudity)
- `settings/`: Admin settings page styles (sync, accordion, buttons, forms, tabs)
- `structure/`: Layout and structure styles

### Data Flow
1. Plugin loads via `brag-book-gallery.php`
2. Autoloader registers class loading
3. `Setup::init_plugin()` initializes the plugin
4. Services are instantiated and hooks registered
5. Gallery pages use shortcodes that connect to external BRAGBook API
6. Templates handle custom page rendering
7. Assets are conditionally loaded based on page context

## Shortcodes

### Main Gallery
```
[brag_book_gallery]
```
- **Smart Auto-Detection**: Automatically detects page context and shows appropriate view
- No parameters needed - context is automatically detected

### Carousel
```
[brag_book_carousel procedure="arm-lift" limit="5"]
```
- Parameters: `procedure`, `procedure_id`, `member_id`, `limit`, `show_controls`, `show_pagination`, `autoplay`, `autoplay_delay`

### Cases Grid
```
[brag_book_gallery_cases]
```

### Single Case
```
[brag_book_gallery_case case_id="12345"]
```

### Favorites
```
[brag_book_gallery_favorites]
```

## Important Notes

- Plugin requires PHP 8.2+ and WordPress 6.8+
- Uses WordPress options for settings storage (no custom database tables)
- Follows WordPress coding standards (WPCS)
- Assets are built using webpack for JavaScript and Sass for CSS
- Plugin updates are handled via GitHub repository (`bragbook2/brag-book-gallery`)
- Debug mode provides additional logging and shorter cache times
- Custom CSS is stored in `brag_book_gallery_custom_css` option and injected once per page
- Node.js 18+ and npm 9+ required for frontend builds
- ESLint and Stylelint configurations use tab indentation
- Prettier formatting uses 4-space tabs and single quotes