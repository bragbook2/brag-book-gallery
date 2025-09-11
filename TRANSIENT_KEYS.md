# BRAGBook Gallery Cache Documentation

This document provides comprehensive documentation for all caching mechanisms used in the BRAGBook Gallery WordPress plugin v3.0.0+.

## Overview

The plugin implements a sophisticated multi-tier caching system with:
- **WP Engine Object Cache** (primary on WP Engine)
- **WordPress Transients** (fallback/standard hosting)
- **Memory Cache** (request-level optimization)
- **Rate Limiting Cache** (performance protection)

## Cache Architecture

### Dual Caching Strategy
The plugin automatically detects WP Engine environments and uses:
1. **WP Engine**: `wp_cache_set/get()` with cache group `brag_book_gallery`
2. **Standard WordPress**: `set_transient/get_transient()` with prefix `brag_book_gallery_transient_`

### Cache Groups
- `brag_book_gallery` - Main plugin cache group
- `brag_book_gallery_rewrite` - URL rewrite cache
- `brag_book_gallery_html` - HTML content cache
- `brag_book_gallery_db` - Database query cache
- `brag_book_cases` - Case-specific data cache
- `brag_book_carousel` - Carousel data cache
- `brag_book_consultation` - Consultation form cache

## Current Active Cache Keys

### Core Data Cache Keys

#### **Sidebar Data**
- **Key**: `brag_book_gallery_sidebar`
- **Transient**: `brag_book_gallery_transient_sidebar`
- **Purpose**: API sidebar navigation data (procedures, categories)
- **TTL**: 1 hour (production), 1 minute (debug)
- **Files**: `class-data-fetcher.php`, `class-cache-manager.php`

#### **Cases by Procedure**
- **Key**: `brag_book_gallery_cases_{procedure_name}`
- **Transient**: `brag_book_gallery_transient_cases_{procedure_name}`
- **Purpose**: Cached case lists for specific procedures
- **TTL**: 1 hour (production), 1 minute (debug)
- **Files**: `class-data-fetcher.php`, `class-cache-manager.php`

#### **Individual Case Views**
- **Key**: `brag_book_gallery_case_view_{procedure}_{case_suffix}`
- **Transient**: `brag_book_gallery_transient_case_view_{procedure}_{case_suffix}`
- **Purpose**: Individual case detail pages
- **TTL**: 1 hour (production), 1 minute (debug)
- **Files**: `class-ajax-handlers.php`, `class-cache-manager.php`

### SEO Cache Keys

#### **SEO Sidebar Data**
- **Key**: `brag_book_gallery_seo_sidebar_{api_token}`
- **Transient**: `brag_book_gallery_transient_seo_sidebar_{api_token}`
- **Purpose**: SEO-specific sidebar data with API token context
- **TTL**: 30 minutes
- **Files**: `class-seo-manager.php`

#### **SEO Combined Sidebar**
- **Key**: `brag_book_gallery_seo_combined_sidebar_{serialized_tokens}`
- **Transient**: `brag_book_gallery_transient_seo_combined_sidebar_{serialized_tokens}`
- **Purpose**: Combined sidebar data for multiple API tokens
- **TTL**: 30 minutes
- **Files**: `class-seo-manager.php`

#### **SEO Procedure Data**
- **Key**: `brag_book_gallery_seo_procedure_{slug}_{combine_status}`
- **Transient**: `brag_book_gallery_transient_seo_procedure_{slug}_{combine_status}`
- **Purpose**: Procedure-specific SEO metadata
- **TTL**: 1 hour
- **Files**: `class-seo-manager.php`

#### **SEO Case Data**
- **Key**: `brag_book_gallery_seo_case_{case_id_or_slug}_{serialized_procedure_data}`
- **Transient**: `brag_book_gallery_transient_seo_case_{case_id_or_slug}_{serialized_procedure_data}`
- **Purpose**: Individual case SEO metadata
- **TTL**: 1 hour
- **Files**: `class-seo-manager.php`

#### **SEO Rate Limiting**
- **Key**: `brag_book_gallery_seo_rate_limit_{identifier}`
- **Transient**: `brag_book_gallery_transient_seo_rate_limit_{identifier}`
- **Purpose**: Rate limiting for SEO operations
- **TTL**: 1 hour
- **Files**: `class-seo-manager.php`

### Plugin Management Cache Keys

#### **Mode Management**
- **Key**: `brag_book_gallery_mode_{operation}_{hash}`
- **Transient**: `brag_book_gallery_transient_mode_{operation}_{hash}`
- **Purpose**: Plugin mode switching and validation
- **TTL**: Variable based on operation
- **Files**: `class-mode-manager.php`

#### **Migration Cache**
- **Key**: `brag_book_gallery_migration_{operation}_{hash}`
- **Transient**: `brag_book_gallery_transient_migration_{operation}_{hash}`
- **Purpose**: Data migration process status
- **TTL**: Variable based on operation
- **Files**: `class-migration-manager.php`

#### **Data Validation Cache**
- **Key**: `brag_book_gallery_data_validator_{hash}`
- **Transient**: `brag_book_gallery_transient_data_validator_{hash}`
- **Purpose**: Validation result caching
- **TTL**: Variable based on validation type
- **Files**: `class-data-validator.php`

### Taxonomy Cache Keys

#### **Taxonomy Data**
- **Key**: `brag_book_gallery_{taxonomy}_{hash}`
- **Transient**: `brag_book_gallery_transient_{taxonomy}_{hash}`
- **Purpose**: WordPress taxonomy data caching
- **TTL**: Variable (300-7200 seconds)
- **Files**: `class-gallery-taxonomies.php`

### Rate Limiting Cache Keys

#### **API Rate Limits**
- **Key**: `brag_book_gallery_rate_limit_{identifier}`
- **Transient**: `brag_book_gallery_transient_rate_limit_{identifier}`
- **Purpose**: API request rate limiting
- **TTL**: 1 hour
- **Files**: `class-endpoints.php`

#### **User Action Rate Limits**
- **Key**: `brag_book_gallery_user_rate_limit_{user_id}_{action}`
- **Transient**: `brag_book_gallery_transient_user_rate_limit_{user_id}_{action}`
- **Purpose**: User-specific action rate limiting
- **TTL**: Variable based on action type
- **Files**: Multiple handler classes

## Cache TTL Constants

### REST Endpoints (`class-endpoints.php`)
- `CACHE_TTL_SHORT = 300` (5 minutes)
- `CACHE_TTL_MEDIUM = 900` (15 minutes)
- `CACHE_TTL_LONG = 1800` (30 minutes)
- `CACHE_TTL_EXTENDED = 3600` (1 hour)

### SEO Manager (`class-seo-manager.php`)
- `CACHE_TTL_SEO_META = 1800` (30 minutes)
- `CACHE_TTL_STRUCTURED = 3600` (1 hour)
- `CACHE_TTL_SITEMAP = 7200` (2 hours)

### Taxonomies (`class-gallery-taxonomies.php`)
- `CACHE_TTL_SHORT = 300` (5 minutes)
- `CACHE_TTL_MEDIUM = 1800` (30 minutes)
- `CACHE_TTL_LONG = 3600` (1 hour)
- `CACHE_TTL_EXTENDED = 7200` (2 hours)

### Cache Manager (`class-cache-manager.php`)
- `CACHE_DURATION = 3600` (1 hour - production)
- `DEBUG_CACHE_DURATION = 60` (1 minute - debug mode)

## Memory Cache Usage

### Request-Level Caching
Multiple classes implement `private array $memory_cache = []` for request-level optimization:
- `class-endpoints.php`
- `class-seo-manager.php`
- `class-gallery-taxonomies.php`
- `class-migration-manager.php`
- `class-sitemap.php`

### Specialized Memory Cache
- **Path Cache**: `trait-tools.php` - `private static array $path_cache = []`
- **Validation Cache**: `class-data-validator.php` - `private array $validation_cache = []`

## Cache Helper Functions

### Global Functions (`cache-helpers.php`)
- `brag_book_set_cache($key, $value, $expiration = 0)` - Dual cache set
- `brag_book_get_cache($key)` - Dual cache get  
- `brag_book_delete_cache($key)` - Dual cache delete
- `brag_book_is_wp_engine()` - WP Engine detection
- `brag_book_flush_cache_group($group)` - Group cache flush

### Cache Manager Static Methods
- `Cache_Manager::set($key, $value, $expiration)` - Primary cache interface
- `Cache_Manager::get($key)` - Primary cache retrieval
- `Cache_Manager::delete($key)` - Individual cache deletion
- `Cache_Manager::clear()` - Full cache clear
- `Cache_Manager::get_sidebar_cache_key($api_token)` - Sidebar key generation
- `Cache_Manager::get_cases_by_procedure_cache_key($procedure_name)` - Cases key generation
- `Cache_Manager::get_case_view_cache_key($procedure_slug, $case_suffix)` - Case view key generation

## Cache Type Detection Patterns

For cache management and clearing operations, the following SQL patterns are used:

```sql
-- Sidebar cache
'sidebar' => '%transient_%brag_book_gallery_sidebar%'

-- Cases by procedure cache  
'cases' => '%transient_%brag_book_gallery_cases_%'

-- Case view cache
'case_view' => '%transient_%brag_book_gallery_case_view_%'

-- SEO cache patterns
'seo_sidebar' => '%transient_%brag_book_gallery_seo_sidebar_%'
'seo_procedure' => '%transient_%brag_book_gallery_seo_procedure_%'
'seo_case' => '%transient_%brag_book_gallery_seo_case_%'

-- Rate limiting patterns
'rate_limit' => '%transient_%brag_book_gallery_rate_limit_%'
'seo_rate_limit' => '%transient_%brag_book_gallery_seo_rate_limit_%'

-- Mode management patterns
'mode' => '%transient_%brag_book_gallery_mode_%'

-- Migration patterns
'migration' => '%transient_%brag_book_gallery_migration_%'

-- Validation patterns
'validation' => '%transient_%brag_book_gallery_data_validator_%'

-- Taxonomy patterns
'taxonomy' => '%transient_%brag_book_gallery_category_%'
'taxonomy' => '%transient_%brag_book_gallery_procedure_%'
```

## WP Engine Optimizations

### Object Cache Groups
When running on WP Engine, the plugin uses object cache with these groups:
- `brag_book_gallery` (primary)
- `brag_book_gallery_rewrite`
- `brag_book_gallery_html`
- `brag_book_gallery_db`
- `brag_book_gallery_queries`
- `brag_book_cases`
- `brag_book_carousel`
- `brag_book_consultation`

### Cache Detection
The plugin automatically detects WP Engine using:
- `function_exists('wp_cache_set')`
- Server environment detection
- Fallback gracefully to WordPress transients

## Cache Clearing Methods

### Admin Interface
- **Settings → Debug → Cache Management**: Individual cache type clearing
- **Settings → Debug → Clear All Cache**: Complete cache flush
- **Factory Reset**: Removes all plugin cache data

### WP-CLI Commands
```bash
# Clear specific cache types
wp cache flush
wp transient delete --all

# Clear object cache (WP Engine)
wp cache flush-group brag_book_gallery
```

### Programmatic Clearing
- `Cache_Manager::clear()` - Complete plugin cache clear
- `wp_cache_flush()` - Full object cache clear
- `brag_book_flush_cache_group($group)` - Group-specific clear

## Performance Considerations

### Cache Strategy
1. **Memory Cache**: Fastest, request-level only
2. **Object Cache**: Fast, persistent (WP Engine)
3. **Transients**: Reliable fallback, database-stored

### TTL Strategy
- **Debug Mode**: Short TTLs (60 seconds) for development
- **Production**: Longer TTLs (300-3600 seconds) for performance
- **Rate Limiting**: Medium TTLs (3600 seconds) for protection

### Cache Size Management
- Automatic cleanup of expired transients
- Memory cache size limits to prevent bloat
- Intelligent cache invalidation on data changes

## Migration from Legacy Cache

### Removed Cache Keys
- `brag_book_gallery_transient_all_cases_*` - Eliminated in v3.0.0+
- `brag_book_gallery_transient_api_carousel_*` - Removed for performance
- `brag_book_gallery_transient_api_pagination_*` - No longer cached
- `brag_book_gallery_transient_api_sidebar_*` - Replaced with simplified sidebar cache

### Backward Compatibility
The plugin maintains backward compatibility by:
- Graceful fallback for missing cache data
- Automatic migration of cache formats
- Legacy key cleanup during updates

## Security Considerations

### Cache Key Security
- API tokens are hashed when used in cache keys
- Sensitive data is never stored in cache keys
- User-specific data uses secure identifiers

### Rate Limiting
- Multiple layers of rate limiting prevent abuse
- IP-based and user-based rate limiting
- Automatic cleanup of rate limit data

## Debug and Monitoring

### Debug Mode Features
- Reduced cache TTLs for development
- Additional logging of cache operations
- Cache hit/miss tracking

### Cache Statistics
Available through:
- WordPress admin debug interface
- WP-CLI cache commands
- Plugin debug tools

---

*Last Updated: December 2024*  
*Plugin Version: 3.0.0+*  
*Documentation Version: 2.0*