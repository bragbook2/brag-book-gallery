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
- `Settings_*`: Individual settings pages (Debug, Help, JavaScript, Mode, etc.)
- `Menu`: Admin menu registration
- `Tabs`: Settings page tab management
- `Debug_Tools`: Debugging utilities

**Frontend Extensions** (`includes/extend/`):
- `Shortcodes`: Main shortcode coordinator with rewrite rules
- `Gallery_Shortcode_Handler`: Gallery display shortcode
- `Cases_Shortcode_Handler`: Individual case display
- `Carousel_Shortcode_Handler`: Carousel functionality
- `Ajax_Handlers`: AJAX request processing
- `Asset_Manager`: Asset loading optimization
- `Cache_Manager`: Transient cache management
- `Data_Fetcher`: API data retrieval
- `HTML_Renderer`: HTML generation utilities
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
- `components/`: Modular component styles
- `settings/`: Admin settings page styles
- `structure/`: Layout and structure styles

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

### Caching Strategy
- API responses cached using WordPress transients
- Cache duration: 1 hour (production) / 1 minute (debug mode)
- Clear cache methods:
  - Admin button in JavaScript Settings page
  - WP-CLI: `wp transient delete --all`

### AJAX Endpoints
- `brag_book_load_more_cases`: Load additional cases with pagination
- `brag_book_load_filtered_cases`: Load specific cases by IDs for filtering
- `brag_book_load_filtered_gallery`: Load filtered gallery content
- `brag_book_gallery_clear_cache`: Clear all gallery transient cache

## Important Considerations

- Plugin requires PHP 8.2+ and WordPress 6.8+
- Uses WordPress options for settings storage (no custom database tables)
- Follows WordPress coding standards (WPCS)
- Assets are built using webpack for JavaScript and Sass for CSS
- Plugin updates are handled via GitHub repository (`bragbook2/brag-book-gallery`)
- All button styling should be in CSS files, not inline
- API returns 10 cases per page, requiring pagination for larger datasets