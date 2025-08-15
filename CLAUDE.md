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
npm run watch

# Production build
npm run build

# Individual builds
npm run build:blocks     # Build Gutenberg blocks
npm run build:admin      # Build admin assets
npm run build:frontend   # Build frontend assets

# Linting
npm run lint:js          # Lint JavaScript files
npm run lint:css         # Lint CSS/SCSS files
npm run format:js        # Format JavaScript files
npm run format:css       # Fix CSS/SCSS issues

# Testing
npm run test:unit        # Run unit tests
npm run test:e2e         # Run Playwright E2E tests
npm run check-types      # TypeScript type checking
```

### PHP Development
```bash
# Install dependencies
composer install

# PHP Linting and Standards
composer run phpcs       # Check WordPress coding standards
composer run phpcs:fix   # Auto-fix coding standard issues
composer run phpstan     # Static analysis with PHPStan

# Testing
composer run test        # Run all PHPUnit tests
composer run test:unit   # Run unit tests only
composer run test:integration  # Run integration tests

# Quality Assurance
composer run qa          # Run all linting and tests
```

### WordPress Environment
```bash
# Local development environment
npm run env:start        # Start wp-env
npm run env:stop         # Stop wp-env
npm run env:reset        # Reset wp-env
```

## Architecture

### Plugin Structure
The plugin follows a modular architecture with clear separation of concerns:

- **Main Entry**: `brag-book-gallery.php` - Plugin bootstrap file that initializes the Setup class
- **Autoloader**: Custom PSR-4 compatible autoloader in `includes/autoload.php`
- **Core Setup**: `Setup` class (includes/core/class-setup.php) handles all initialization, hooks, and service management using singleton pattern

### Key Components

**Core Classes** (`includes/core/`):
- `Setup`: Main plugin orchestrator, manages services and hooks
- `Updater`: Handles plugin updates from GitHub repository
- `Consultation`: Manages consultation form submissions

**Admin** (`includes/admin/`):
- `Settings`: Plugin settings page management

**SEO** (`includes/seo/`):
- `SEO_Manager`: Central SEO functionality coordinator
- `On_Page`: On-page SEO optimizations
- `Sitemap`: XML sitemap generation

**Extend** (`includes/extend/`):
- `Shortcodes`: Gallery shortcode implementations and rewrite rules
- `Templates`: Custom template handling for gallery pages

**Resources** (`includes/resources/`):
- `Assets`: CSS/JS asset management and enqueuing

**REST API** (`includes/rest/`):
- `Endpoints`: Custom REST API endpoints

**Traits** (`includes/traits/`):
- `Trait_Api`: API communication utilities
- `Trait_Sanitizer`: Input sanitization helpers
- `Trait_Tools`: Common utility functions

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
- Custom Post Type: `form-entries` for consultation submissions

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
  - Utility file: `/wp-content/plugins/brag-book-gallery/clear-cache.php?clear=1`
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
- Assets are built using WordPress Scripts package (@wordpress/scripts)
- E2E tests use Playwright framework
- Plugin updates are handled via GitHub repository (bragbook2/brag-book-gallery)
- All button styling should be in CSS files, not inline
- API returns 10 cases per page, requiring pagination for larger datasets