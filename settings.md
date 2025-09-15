# BRAG Book Gallery Plugin - Settings Documentation

## Overview

This document provides a comprehensive guide to all settings and configuration options available in the BRAG Book Gallery WordPress plugin (v3.0.0+). The plugin uses a modular settings architecture with clear separation of concerns across multiple admin pages.

## Table of Contents

1. [Settings Architecture](#settings-architecture)
2. [General Settings](#general-settings)
3. [API Settings](#api-settings)
4. [Mode Settings](#mode-settings)
5. [Debug & Diagnostic Settings](#debug--diagnostic-settings)
6. [Help & Documentation](#help--documentation)
7. [Database Options Reference](#database-options-reference)
8. [Settings Management Classes](#settings-management-classes)
9. [Caching & Performance](#caching--performance)
10. [Security & Validation](#security--validation)

## Settings Architecture

### Admin Menu Structure
The plugin creates a dedicated "BRAG Book Gallery" menu with the following tabs:
- **Dashboard**: Overview and quick access to key settings
- **General**: Display, gallery, and customization options
- **API**: API connection and authentication settings
- **Mode**: Operating mode selection and management
- **Debug**: Debugging tools and diagnostics
- **Help**: Documentation and troubleshooting guides

### Settings Base Class
All settings pages inherit from `Settings_Base` which provides:
- Consistent page structure and navigation
- Security validation (nonce verification, capability checks)
- Form handling and data sanitization
- Notice management system
- Asset loading coordination

### Settings Helper Class
The `Settings_Helper` class (`includes/core/class-settings-helper.php`) provides:
- Cached access to frequently used settings
- Type-safe getter methods
- Performance optimization through static caching
- Centralized settings validation

## General Settings

### Location
**Admin Path**: BRAG Book Gallery → General
**File**: `includes/admin/class-settings-general.php`
**Class**: `BRAGBookGallery\Includes\Admin\Settings_General`

### Display & Gallery Settings

#### Gallery Columns
- **Option**: `brag_book_gallery_columns`
- **Type**: String
- **Default**: `'3'`
- **Options**: `'2'` or `'3'`
- **Description**: Number of columns in gallery grid layout

#### Items Per Page
- **Option**: `brag_book_gallery_items_per_page`
- **Type**: Integer
- **Default**: `10`
- **Range**: 1-100
- **Description**: Initial number of gallery items to display (additional items loaded via "Load More")

#### Gallery Page Slug
- **Option**: `brag_book_gallery_page_slug`
- **Type**: String/Array
- **Default**: `'gallery'`
- **Description**: URL slug for the gallery page (auto-creates page if needed)

#### Landing Page Text
- **Option**: `brag_book_gallery_landing_page_text`
- **Type**: HTML String
- **Default**: Predefined welcome text with HTML formatting
- **Description**: Content displayed at the top of gallery pages (supports rich text editing)

### Navigation & Interface Settings

#### Expand Navigation Menus
- **Option**: `brag_book_gallery_expand_nav_menus`
- **Type**: Boolean
- **Default**: `false`
- **Description**: Whether filter navigation menus are expanded by default

#### Show Filter Counts
- **Option**: `brag_book_gallery_show_filter_counts`
- **Type**: Boolean
- **Default**: `true`
- **Description**: Display case counts next to filter categories (e.g., "Age Group (8)")

#### Enable Favorites
- **Option**: `brag_book_gallery_enable_favorites`
- **Type**: Boolean
- **Default**: `true`
- **Description**: Allow users to save and manage favorite cases

#### Enable Consultation Requests
- **Option**: `brag_book_gallery_enable_consultation`
- **Type**: Boolean
- **Default**: `true`
- **Description**: Display consultation request CTAs and dialog forms

### SEO Settings

#### SEO Page Title
- **Option**: `brag_book_gallery_seo_page_title`
- **Type**: String
- **Max Length**: 60 characters
- **Description**: Custom page title for search engines (integrated with Yoast, RankMath, etc.)

#### SEO Meta Description
- **Option**: `brag_book_gallery_seo_page_description`
- **Type**: String
- **Max Length**: 160 characters
- **Description**: Custom meta description for search results

### Performance Settings

#### AJAX Timeout
- **Option**: `brag_book_gallery_ajax_timeout`
- **Type**: Integer
- **Default**: `30`
- **Range**: 5-120 seconds
- **Description**: Maximum time to wait for API responses

#### Cache Duration
- **Option**: `brag_book_gallery_cache_duration`
- **Type**: Integer
- **Default**: `300` (5 minutes)
- **Range**: 0-86400 seconds
- **Description**: How long to cache API responses (0 disables caching)

#### Lazy Load Images
- **Option**: `brag_book_gallery_lazy_load`
- **Type**: String
- **Options**: `'yes'` or `'no'`
- **Default**: `'yes'`
- **Description**: Enable lazy loading for gallery images

### Custom CSS

#### Custom CSS Code
- **Option**: `brag_book_gallery_custom_css`
- **Type**: String (CSS)
- **Editor**: Monaco Editor with IntelliSense
- **Description**: Custom CSS styles with syntax highlighting and validation

## API Settings

### Location
**Admin Path**: BRAG Book Gallery → API
**File**: `includes/admin/class-settings-api.php`
**Class**: `BRAGBookGallery\Includes\Admin\Settings_Api`

### API Connection Settings

#### API Tokens
- **Option**: `brag_book_gallery_api_token`
- **Type**: Array of strings
- **Default**: `[]` (empty array)
- **Description**: API authentication tokens (supports multiple connections)

#### Website Property IDs
- **Option**: `brag_book_gallery_website_property_id`
- **Type**: Array of strings
- **Default**: `[]` (empty array)
- **Description**: Website property IDs corresponding to API tokens

#### API Endpoint
- **Option**: `brag_book_gallery_api_endpoint`
- **Type**: String (URL)
- **Default**: `'https://app.bragbookgallery.com'`
- **Description**: Base URL for API requests

### Connection Configuration

#### Request Timeout
- **Option**: `brag_book_gallery_api_timeout`
- **Type**: Integer
- **Default**: `30`
- **Range**: 5-120 seconds
- **Description**: Maximum time to wait for API responses

#### Enable Caching
- **Option**: `brag_book_gallery_enable_caching`
- **Type**: String
- **Options**: `'yes'` or `'no'`
- **Default**: `'yes'`
- **Description**: Cache API responses for better performance

#### Cache Duration
- **Option**: `brag_book_gallery_api_cache_duration`
- **Type**: Integer
- **Default**: `3600` (1 hour)
- **Range**: 60-86400 seconds
- **Description**: How long to cache API responses

### API Validation
The settings page includes real-time API validation that:
- Tests connection to BRAG Book API endpoints
- Validates token/property ID pairs
- Provides detailed error messages
- Shows connection status indicators
- Supports multiple concurrent connections

## Mode Settings

### Location
**Admin Path**: BRAG Book Gallery → Mode
**File**: `includes/admin/class-settings-mode.php`
**Class**: `BRAGBookGallery\Includes\Admin\Settings_Mode`

### Operating Modes

#### Current Mode
- **Option**: `brag_book_gallery_mode`
- **Type**: String
- **Options**: `'default'` or `'local'`
- **Default**: `'default'`
- **Description**: Current operating mode of the plugin

#### Default Mode (Active)
- **API-Driven**: Content loaded dynamically from BRAG Book API
- **Virtual URLs**: Gallery pages use custom URL routing
- **Real-Time**: Updates reflect immediately from API changes
- **Minimal Storage**: Low database footprint

#### Local Mode (Coming Soon)
- **WordPress Native**: Content stored as post types and taxonomies
- **SEO Optimized**: Better search engine indexing
- **Offline Access**: Works without API connectivity
- **Performance**: Faster loading with local database queries

### Mode Management Features
- Visual mode comparison interface
- One-click switching (when Local mode available)
- Data migration and preservation tools
- SEO plugin integration status
- Performance impact analysis

## Debug & Diagnostic Settings

### Location
**Admin Path**: BRAG Book Gallery → Debug
**File**: `includes/admin/class-settings-debug.php`
**Class**: `BRAGBookGallery\Includes\Admin\Settings_Debug`

### Debug Configuration

#### Enable Debug Logging
- **Option**: `brag_book_gallery_enable_logs`
- **Type**: String
- **Options**: `'yes'` or `'no'`
- **Default**: `'no'`
- **Description**: Enable plugin debug logging

#### Log Level
- **Option**: `brag_book_gallery_log_level`
- **Type**: String
- **Options**: `'error'`, `'warning'`, `'info'`, `'debug'`
- **Default**: `'error'`
- **Description**: Minimum level for log entries

### System Information
- WordPress version and compatibility
- PHP version and memory limits
- Plugin version and mode status
- API connection status
- Cache statistics
- Database information
- Server environment details

### Debug Tools
- **Gallery Checker**: Validates gallery page setup
- **Rewrite Debug**: Analyzes URL rewrite rules
- **Cache Management**: Individual cache item control
- **System Info Export**: Downloadable diagnostic report
- **Factory Reset**: Complete plugin settings reset

### Log Management
- Error log viewing and download
- API request logging
- Log file clearing utilities
- Automatic log rotation
- Secure log directory with .htaccess protection

## Help & Documentation

### Location
**Admin Path**: BRAG Book Gallery → Help
**File**: `includes/admin/class-settings-help.php`
**Class**: `BRAGBookGallery\Includes\Admin\Settings_Help`

### Help Sections
- **Setup Guide**: Step-by-step configuration instructions
- **Troubleshooting**: Common issues and solutions
- **FAQs**: Frequently asked questions
- **System Requirements**: Compatibility information
- **Support Resources**: Contact information and documentation links

## Database Options Reference

### Option Naming Convention
All plugin options use the prefix `brag_book_gallery_` followed by the setting name.

### Core Options by Category

#### API & Authentication
```php
brag_book_gallery_api_token                 // Array of API tokens
brag_book_gallery_website_property_id       // Array of property IDs
brag_book_gallery_api_endpoint               // API base URL
brag_book_gallery_api_timeout                // Request timeout
brag_book_gallery_enable_caching             // Caching enabled
brag_book_gallery_api_cache_duration         // Cache duration
```

#### Display & Gallery
```php
brag_book_gallery_columns                    // Grid columns
brag_book_gallery_items_per_page            // Items per page
brag_book_gallery_enable_favorites          // Favorites enabled
brag_book_gallery_enable_consultation       // Consultation enabled
brag_book_gallery_show_filter_counts        // Show filter counts
brag_book_gallery_expand_nav_menus          // Expand navigation
```

#### Page & URL Management
```php
brag_book_gallery_page_slug                 // Gallery page slug
brag_book_gallery_landing_page_text         // Landing page content
brag_book_gallery_seo_page_title            // SEO title
brag_book_gallery_seo_page_description      // SEO description
```

#### Mode & Operation
```php
brag_book_gallery_mode                      // Operating mode
brag_book_gallery_mode_settings             // Mode-specific settings
```

#### Performance & Caching
```php
brag_book_gallery_cache_duration            // General cache duration
brag_book_gallery_ajax_timeout              // AJAX timeout
brag_book_gallery_lazy_load                 // Lazy loading
```

#### Customization
```php
brag_book_gallery_custom_css                // Custom CSS code
```

#### Debug & Logging
```php
brag_book_gallery_enable_logs               // Debug logging
brag_book_gallery_log_level                 // Log verbosity
```

#### Plugin Management
```php
brag_book_gallery_version                   // Plugin version
brag_book_gallery_db_version                // Database version
brag_book_gallery_activation_time           // Activation timestamp
```

### Default Values
Most settings have sensible defaults defined in their respective classes. The `Settings_Helper` class provides cached access to commonly used options with their defaults.

## Settings Management Classes

### Settings Base (`Settings_Base`)
**File**: `includes/admin/class-settings-base.php`
- Abstract base class for all settings pages
- Provides security, validation, and form handling
- Manages page structure and navigation
- Handles notice display and asset loading

### Settings Manager (`Settings_Manager`)
**File**: `includes/admin/class-settings-manager.php`
- Central coordinator for all settings pages
- Manages admin menu structure
- Handles asset loading and dependencies
- Provides backward compatibility

### Settings Helper (`Settings_Helper`)
**File**: `includes/core/class-settings-helper.php`
- Cached access to frequently used settings
- Type-safe getter methods with validation
- Static caching for performance optimization
- Centralized default value management

### Individual Settings Classes
Each settings page has its own class:
- `Settings_General`: Display and gallery configuration
- `Settings_Api`: API connection management
- `Settings_Mode`: Operating mode selection
- `Settings_Debug`: Debugging and diagnostics
- `Settings_Help`: Documentation and support

## Caching & Performance

### Caching Layers
1. **Static Caching**: Settings_Helper caches options in memory
2. **WordPress Transients**: API responses cached with configurable duration
3. **Object Caching**: Compatible with external caching solutions

### Cache Management
- Individual cache item deletion
- Bulk cache clearing operations
- API-specific cache invalidation
- Debug mode with shorter cache times

### Performance Features
- Lazy loading of images
- Progressive gallery loading ("Load More" button)
- Configurable cache durations
- Minimal database queries through caching

## Security & Validation

### Security Measures
- WordPress nonce verification for all form submissions
- Capability checking (`manage_options` required)
- Input sanitization using WordPress functions
- XSS prevention in custom CSS and user inputs
- Secure log file storage with .htaccess protection

### Data Validation
- Type checking for all option values
- Range validation for numeric settings
- URL validation for API endpoints
- CSS sanitization for custom styles
- Array structure validation for complex options

### Access Control
- Admin-only access to all settings pages
- AJAX request security verification
- Capability-based feature access
- Secure password field handling with visibility toggles

## Migration & Backup

### Factory Reset
Complete plugin reset removes:
- All plugin options from database
- Gallery pages containing shortcodes
- Transient cache data
- Log files and debug data

### Settings Export/Import
- JSON-based settings export
- Selective setting restoration
- Backup before major changes
- Migration between sites support

### Version Compatibility
- Automatic database schema updates
- Backward compatibility maintenance
- Legacy option name mapping
- Migration progress tracking

## Shortcode Integration

### Settings-Aware Shortcodes
The plugin's shortcodes respect settings configuration:

#### Main Gallery Shortcode
```php
[brag_book_gallery]
```
- Uses `brag_book_gallery_columns` for layout
- Respects `brag_book_gallery_items_per_page` for pagination
- Applies favorites and consultation settings

#### Carousel Shortcode
```php
[brag_book_carousel procedure="arm-lift" limit="5"]
```
- Integrates with API settings for data fetching
- Supports procedure filtering and limits

### Settings Impact on Frontend
- Display settings control gallery appearance
- Performance settings affect loading behavior
- Feature toggles enable/disable functionality
- Custom CSS applies to all gallery instances

## Troubleshooting

### Common Issues

#### API Connection Problems
- Check API token and Website Property ID validity
- Verify network connectivity and firewall settings
- Test with API validation tool in settings
- Review API timeout settings

#### Gallery Display Issues
- Verify gallery page exists and contains shortcode
- Check custom CSS for conflicts
- Clear cache and test again
- Review display settings for correct values

#### Performance Problems
- Adjust cache duration settings
- Enable lazy loading for images
- Optimize items per page setting
- Check server resources and limits

### Debug Mode
Enable debug logging to troubleshoot:
1. Go to Debug settings
2. Enable "Debug Logging"
3. Set appropriate log level
4. Reproduce the issue
5. Review logs in Debug → Log Viewer

### Support Resources
- Built-in help documentation
- System information export
- Debug tools and diagnostics
- Factory reset for clean slate

---

This documentation covers all available settings and configuration options in the BRAG Book Gallery plugin. For additional support, use the built-in help system or contact the plugin developers.