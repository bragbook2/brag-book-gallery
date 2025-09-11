# BRAGBook Gallery Plugin - Complete Cache Documentation

This document provides a comprehensive reference of all transient keys, wp_cache usage, and cache-related items used by the BRAGBook Gallery plugin for caching and temporary data storage.

**IMPORTANT:** Most transient keys use the standardized prefix `brag_book_gallery_transient_` for consistency and easier management, while some legacy keys and wp_cache items use different patterns.

## **API Data Transients**

### Core API Cache Keys:
- **`brag_book_gallery_transient_sidebar_{api_token}`** - Sidebar data from API  
  Locations:
  - Get/Set: `includes/extend/class-cache-manager.php:317,320`
  - Usage: `includes/seo/class-seo-manager.php:774`

- **`brag_book_gallery_transient_cases_{hash}`** - Cases data from API  
  Locations: 
  - Generated: `includes/extend/class-cache-manager.php:352-374`
  - Pattern: `includes/extend/class-cache-manager.php:582`

- **`brag_book_gallery_transient_all_cases_{hash}`** - All cases data  
  Location: `includes/extend/class-cache-manager.php:378-404`

- **`brag_book_gallery_transient_carousel_{hash}`** - Carousel data  
  Locations:
  - Generated: `includes/extend/class-cache-manager.php:420-442`
  - Pattern: `includes/extend/class-cache-manager.php:583`

### Generic API Cache:
- **`brag_book_gallery_transient_api_{type}_{hash}`** - General API responses  
  Location: `includes/rest/class-endpoints.php:1112-1113`

### Filtered Cases Cache:
- **`brag_book_gallery_transient_filtered_cases_{api_token}_{website_property_id}_{procedure_ids}`** - Filtered cases by procedures  
  Location: `includes/extend/class-data-fetcher.php:351`

### Individual Case Cache:
- **`brag_book_gallery_transient_carousel_case_{api_token}_{case_id}`** - Case cache by ID  
  Location: `includes/extend/class-data-fetcher.php:544`

- **`brag_book_gallery_transient_carousel_case_{api_token}_{seoSuffixUrl}`** - Case cache by SEO URL  
  Locations: `includes/extend/class-data-fetcher.php:551,564`

- **`brag_book_gallery_transient_carousel_case_{case_identifier}`** - Generic case cache lookup  
  Location: `includes/extend/class-data-fetcher.php:595`

## **SEO Data Transients**

### Procedure SEO Cache:
- **`brag_book_gallery_transient_procedure_{procedure_slug}_{combine/single}`** - SEO metadata for procedures  
  Location: `includes/seo/class-seo-manager.php:527`

### Combined Sidebar Cache:
- **`brag_book_gallery_transient_combined_sidebar_{hash}`** - Combined sidebar data for SEO  
  Locations: `includes/seo/class-seo-manager.php:717,1888`

### General SEO Cache:
- **`brag_book_gallery_transient_seo_{type}_{key_data}`** - General SEO data caching  
  Location: `includes/seo/class-on-page.php:1379`

### Sitemap Cache:
- **`brag_book_gallery_transient_sitemap_content`** - Sitemap content cache  
  Locations: `includes/seo/class-sitemap.php:304,356,809,846`

- **`brag_book_gallery_transient_sitemap_last_modified`** - Sitemap modification time  
  Locations: `includes/seo/class-sitemap.php:788,810`

## **Sync & Migration Transients**

### Sync Status & Progress:
- **`brag_book_gallery_transient_sync_status`** - Sync operation status  
  Locations:
  - Get: `includes/sync/class-sync-manager.php:792`
  - Delete: `includes/sync/class-sync-manager.php:820,1208`
  - Usage: `includes/mode/class-mode-manager.php:375`

- **`brag_book_gallery_transient_sync_progress`** - Sync operation progress  
  Locations:
  - Set: `includes/sync/class-sync-manager.php:1091,1111`
  - Delete: `includes/sync/class-sync-manager.php:1209`
  - Get: `includes/sync/class-sync-manager.php:1285`

- **`brag_book_gallery_transient_sync_lock`** - Sync operation lock  
  Locations:
  - Set/Get: `includes/sync/class-sync-manager.php:1048`
  - Delete: `includes/sync/class-sync-manager.php:1073`

### Force Update Flags:
- **`brag_book_gallery_transient_force_update_all`** - Force update all flag  
  Location: `includes/sync/class-sync-manager.php:1130`

- **`brag_book_gallery_transient_force_update_cases`** - Force update specific cases  
  Location: `includes/sync/class-sync-manager.php:1123`

### Migration Cache:
- **`brag_book_gallery_transient_migration_status`** - Migration status  
  Location: `includes/migration/class-migration-manager.php:692`

- **`brag_book_gallery_transient_migration_{hash}`** - Migration data cache  
  Locations:
  - Get: `includes/migration/class-migration-manager.php:1653`
  - Set: `includes/migration/class-migration-manager.php:1686`

## **Rate Limiting Transients**

### API Rate Limiting:
- **`brag_book_gallery_transient_rate_limit_{hash}`** - API rate limiting  
  Locations:
  - `includes/rest/class-endpoints.php:1834`
  - `includes/seo/class-seo-manager.php:1609`
  - `includes/seo/class-sitemap.php:1196`

### SEO Rate Limiting:
- **`brag_book_gallery_transient_seo_rate_limit_{identifier}`** - SEO-specific rate limiting  
  Location: `includes/seo/class-on-page.php:1345`

### Migration Rate Limiting:
- **`brag_book_gallery_transient_migration_rate_limit_{operation}_{user_id}`** - Migration rate limiting per user  
  Location: `includes/migration/class-migration-manager.php:1524`

## **Taxonomy Transients**

- **`brag_book_gallery_transient_{taxonomy}_terms`** - Specific taxonomy terms  
  Locations: `includes/taxonomies/class-gallery-taxonomies.php:636,851,871`

- **`brag_book_gallery_transient_{taxonomy}_hierarchy`** - Taxonomy hierarchy  
  Locations: `includes/taxonomies/class-gallery-taxonomies.php:637,886`

- **`brag_book_gallery_transient_term_{id}`** - Individual term data  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:652`

- **`brag_book_gallery_transient_terms_{taxonomy}_{serialized_args}`** - Terms with query args  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:843`

- **`brag_book_gallery_transient_search_{search}_{serialized_taxonomies}`** - Taxonomy search results  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:1111`

- **`brag_book_gallery_transient_api_term_{taxonomy}_{api_id}`** - API-sourced taxonomy terms  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:1154`

## **Query Handler Transients**

- **`brag_book_gallery_transient_gallery_{encoded_args}`** - Gallery query results  
  Location: `includes/core/class-query-handler.php:367`

- **`brag_book_gallery_transient_gallery_stats`** - Gallery statistics data  
  Location: `includes/core/class-query-handler.php:706`

## **Image Sync Transients**

- **`brag_book_gallery_transient_img_{url}`** - Image sync status cache  
  Location: `includes/sync/class-image-sync.php:870`

## **Plugin Updater Transients**

- **`brag_book_gallery_github_release_{hash}`** - GitHub update check cache  
  Locations:
  - Get: `includes/core/class-updater.php:120`
  - Set: `includes/core/class-updater.php:139`
  - Delete: `includes/core/class-updater.php:154`

## **Consultation Form Cache (Legacy Keys)**

**Note:** These keys do NOT use the `brag_book_gallery_transient_` prefix and are handled via Cache_Manager:

- **`consultation_hourly_{ip_hash}`** - Hourly submission limits per IP  
  Locations:
  - Get: `includes/core/class-consultation.php:830-831`
  - Set: `includes/core/class-consultation.php:862`

- **`consultation_daily_{ip_hash}`** - Daily submission limits per IP  
  Locations:
  - Get: `includes/core/class-consultation.php:846-847`
  - Set: `includes/core/class-consultation.php:863`

## **WordPress Cache (wp_cache) Usage**

### WP Engine Compatible Cache Helpers:
The plugin includes custom cache helper functions that automatically use WP Engine object cache when available:

- **`brag_book_set_cache(string $key, mixed $value, int $expiration = 0): bool`**  
  Location: `includes/functions/cache-helpers.php:54-60`

- **`brag_book_get_cache(string $key): mixed`**  
  Location: `includes/functions/cache-helpers.php:71-75`  

- **`brag_book_delete_cache(string $key): bool`**  
  Location: `includes/functions/cache-helpers.php:88-92`

### Direct wp_cache Usage:

#### Cache Flushing:
- **`wp_cache_flush()`** - Complete cache flush  
  Locations:
  - `tests/TestCase.php:40`
  - `includes/rest/class-endpoints.php:1383`  
  - `includes/migration/class-migration-manager.php:800,1812`

#### Group-Specific Cache Operations:
- **`wp_cache_flush_group('posts')`** - Flush posts cache during migration  
  Location: `includes/migration/class-migration-manager.php:1742`

- **`wp_cache_delete('last_changed', 'terms')`** - Clear terms cache  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:655`

- **`wp_cache_flush_group(self::CACHE_GROUP)`** - Flush specific cache groups  
  Locations:
  - `includes/migration/class-data-validator.php:606`
  - `includes/extend/class-carousel-shortcode-handler.php:748`
  - `includes/extend/class-cases-shortcode-handler.php:1466`
  - `includes/core/class-query-handler.php:1243`
  - `includes/core/class-database.php:898,957`

#### Cache Groups:
- **`CACHE_GROUP = 'brag_book_gallery'`** - Main plugin cache group  
  Location: `includes/extend/class-cache-manager.php:86`

- **`CACHE_GROUP = 'brag_book_gallery_transient_data_validator'`** - Data validator cache  
  Location: `includes/migration/class-data-validator.php:211`

- **`CACHE_GROUP = 'brag_book_cases'`** - Cases shortcode cache  
  Location: `includes/extend/class-cases-shortcode-handler.php:127`

- **`CACHE_GROUP = 'brag_book_carousel'`** - Carousel cache  
  Location: `includes/extend/class-carousel-shortcode-handler.php:103`

- **`CACHE_GROUP = 'brag_book_gallery_rewrite'`** - Rewrite rules cache  
  Location: `includes/extend/class-rewrite-rules-handler.php:129`

- **`CACHE_GROUP = 'brag_book_gallery_html'`** - HTML rendering cache  
  Location: `includes/extend/class-html-renderer.php:135`

- **`CACHE_GROUP = 'brag_book_gallery_db'`** - Database operations cache  
  Location: `includes/core/class-database.php:66`

- **`CACHE_GROUP = 'brag_book_gallery_queries'`** - Query handler cache  
  Location: `includes/core/class-query-handler.php:58`

- **`CACHE_GROUP = 'brag_book_consultation'`** - Consultation forms cache  
  Location: `includes/core/class-consultation.php:86`

#### wp_cache Specific Keys:

**Rewrite Rules Cache (group: 'brag_book_gallery_rewrite'):**
- **`'brag_book_gallery_pages_with_shortcode'`** - Gallery pages with shortcode detection  
  Locations:
  - Get/Set: `includes/extend/class-rewrite-rules-handler.php:383,407`
  - Delete: `includes/extend/class-rewrite-rules-handler.php:636,831`

**Consultation Cache (group: 'brag_book_consultation'):**
- **`'consultation_count'`** - Total consultation count  
  Locations: `includes/core/class-consultation.php:329,1337,1492,1502`

- **`'consultation_entries_{post_id}'`** - Entries for specific post  
  Locations: `includes/core/class-consultation.php:1338,1454,1476`

**Carousel Cache (group: 'brag_book_carousel'):**
- **`'case_{procedure_slug}_{hash}'`** - Carousel case lookup  
  Locations: `includes/extend/class-carousel-shortcode-handler.php:419,428,445,454,464`

**Database Cache (group: 'brag_book_gallery_db'):**
- **Various dynamic keys** for gallery posts, sync hashes, and stats  
  Locations: `includes/core/class-database.php:491,512,614,634,661,681,735,798`

**Query Handler Cache (group: 'brag_book_gallery_queries'):**
- **Various dynamic keys** for gallery queries and case searches  
  Locations: `includes/core/class-query-handler.php:190,233,371,410,543,583,634,689,707,735`

## **Debug Tools Cache Management**

### Rewrite Flush Tool Cache:
- **`brag_book_gallery_transient_rewrite_fix_last_flush`** - Last flush timestamp  
  Locations: `includes/admin/debug-tools/class-rewrite-flush.php:219,831,877,892`

- **`brag_book_gallery_transient_show_rewrite_notice`** - Rewrite notice flag  
  Location: `includes/admin/debug-tools/class-rewrite-flush.php:1541` (reference)

### System Info Cache:
- **`brag_book_gallery_transient_system_info_report`** - System information report cache  
  Locations: `includes/admin/debug-tools/class-system-info.php:342,351`

## **Cache TTL Constants**

### REST Endpoints (`includes/rest/class-endpoints.php`):
- **`CACHE_TTL_SHORT = 300`** (5 minutes) - Line 78
- **`CACHE_TTL_MEDIUM = 900`** (15 minutes) - Line 79  
- **`CACHE_TTL_LONG = 1800`** (30 minutes) - Line 80
- **`CACHE_TTL_EXTENDED = 3600`** (1 hour) - Line 81
- **`CACHE_DURATION = 300`** (5 minutes) - Line 113

### SEO Manager (`includes/seo/class-seo-manager.php`):
- **`CACHE_TTL_SEO_META = 1800`** (30 minutes) - Line 67
- **`CACHE_TTL_STRUCTURED = 3600`** (1 hour) - Line 68
- **`CACHE_TTL_SITEMAP = 7200`** (2 hours) - Line 69

### Sync Manager (`includes/sync/class-sync-manager.php`):
- **`CACHE_TTL_SHORT = 300`** (5 minutes) - Line 66
- **`CACHE_TTL_MEDIUM = 1800`** (30 minutes) - Line 67
- **`CACHE_TTL_LONG = 3600`** (1 hour) - Line 68
- **`CACHE_TTL_EXTENDED = 7200`** (2 hours) - Line 69

### Taxonomies (`includes/taxonomies/class-gallery-taxonomies.php`):
- **`CACHE_TTL_SHORT = 300`** (5 minutes) - Line 77
- **`CACHE_TTL_MEDIUM = 1800`** (30 minutes) - Line 78
- **`CACHE_TTL_LONG = 3600`** (1 hour) - Line 79
- **`CACHE_TTL_EXTENDED = 7200`** (2 hours) - Line 80

### Mode Manager (`includes/mode/class-mode-manager.php`):
- **`CACHE_TTL_SHORT = 300`** (5 minutes) - Line 139
- **`CACHE_TTL_MEDIUM = 1800`** (30 minutes) - Line 140
- **`CACHE_TTL_LONG = 3600`** (1 hour) - Line 141

### Migration Data Validator (`includes/migration/class-data-validator.php`):
- **`CACHE_EXPIRATION = 3600`** (1 hour) - Line 219

### Core Classes:
- **Updater**: `CACHE_EXPIRATION = 3600` (1 hour) - `includes/core/class-updater.php:22`
- **Database**: `CACHE_EXPIRATION = 300` (5 minutes) - `includes/core/class-database.php:74`
- **Query Handler**: `CACHE_EXPIRATION = 300` (5 minutes) - `includes/core/class-query-handler.php:66`
- **Consultation**: `CACHE_EXPIRATION = 300` (5 minutes) - `includes/core/class-consultation.php:94`

## **Memory Cache Arrays**

### In-Memory Caching:
- **`private array $memory_cache = []`** - Request-level memory cache  
  Locations:
  - `includes/rest/class-endpoints.php:89`
  - `includes/resources/class-assets.php:112`

- **`private static array $settings_cache = []`** - Settings data memory cache  
  Location: `includes/core/class-settings-helper.php:42`

## **Cache Key Patterns by Type**

The cache management system uses these patterns to identify transient types:

- `%transient_%brag_book_gallery_transient_sidebar_%` - Sidebar data
- `%transient_%brag_book_gallery_transient_cases_%` - Cases data  
- `%transient_%brag_book_gallery_transient_carousel_%` - Carousel data
- `%transient_%brag_book_gallery_transient_all_cases_%` - All cases data
- `%transient_%brag_book_gallery_transient_%` - General pattern

Location: `includes/extend/class-cache-manager.php:581-585`

## **Database Cleanup Patterns**

Several classes include database cleanup that targets these patterns:

- `_transient_brag_book_gallery_transient_%` - Main plugin transients
- `_transient_timeout_brag_book_gallery_transient_%` - Timeout entries
- `_transient_brag_book_gallery_transient_api_%` - API-specific transients
- `_transient_brag_book_gallery_transient_migration_%` - Migration transients

### Cleanup Locations:
- `includes/migration/class-migration-manager.php:795-796,1714-1715`
- `includes/extend/class-cache-manager.php:103-104`
- `includes/rest/class-endpoints.php:1381-1382,1393-1394`
- `includes/traits/trait-api.php:409-410,413-414`

## **Cache Management Methods**

### Cache Key Generation:
- **`generate_cache_key(string $type, mixed $data): string`**  
  Location: `includes/rest/class-endpoints.php:1111`

- **`get_cache_key(): string`**  
  Location: `includes/core/class-updater.php:146`

- **Cache Manager Static Methods:**
  - `get_sidebar_cache_key()` - Line 316
  - `get_cases_cache_key()` - Line 344  
  - `get_all_cases_cache_key()` - Line 378
  - `get_carousel_cache_key()` - Line 420

### Cache Clearing Methods:
- **`clear_api_cache(string $type = ''): bool`**  
  Location: `includes/rest/class-endpoints.php:1368`

- **`clear_cache(): void`**  
  Locations: 
  - `includes/core/class-updater.php:153`
  - `includes/core/class-settings-helper.php:196`

### Pattern-Based Cache Deletion:
- **`Cache_Manager::delete_pattern('brag_book_gallery_transient_api_*')`**  
  Used across multiple files for bulk cache clearing

- **`Cache_Manager::clear_all()`**  
  Location: `includes/extend/class-cache-manager.php:122`

## **Hash Generation**

Most dynamic transient keys use MD5 hashes generated from:
- API tokens
- Website property IDs  
- User IDs
- Operation names
- Serialized data arrays
- Cache key parameters

## **Cache Management Integration**

The admin debug tools Cache Management interface (located at `includes/admin/debug-tools/class-cache-management.php`) provides comprehensive tracking and management of all these transients through:

1. **Pattern Matching**: Uses the plugin prefix `brag_book_gallery_transient_` to identify most plugin transients
2. **Type Detection**: Categorizes transients by their key patterns
3. **Individual Management**: View, delete, and analyze specific cache items
4. **Bulk Operations**: Clear all cache or selected items by type  
5. **Statistics**: Shows cache size, expiration times, and usage metrics

## **Cache Architecture Summary**

The plugin uses a sophisticated multi-level caching system:

1. **Primary Cache Layer**: WordPress transients with standardized `brag_book_gallery_transient_` prefix
2. **Object Cache Layer**: WP Engine object cache support via custom helpers
3. **Memory Cache Layer**: In-memory arrays for request-level caching
4. **Cache Groups**: Organized by functionality (API, SEO, sync, migration, etc.)
5. **Legacy Keys**: Some older keys like consultation rate limiting use shorter patterns
6. **Intelligent Invalidation**: Pattern-based cache clearing and dependency-aware invalidation
7. **Rate Limiting**: Transient-based rate limiting across multiple operations
8. **Performance Optimization**: Multi-TTL strategies based on data volatility

## **Migration Notes**

The current caching system uses:
- **Standard transients**: `brag_book_gallery_transient_*` prefix for most new cache items
- **Legacy transients**: Some older patterns like `consultation_*` for backward compatibility
- **wp_cache groups**: Multiple cache groups for different functionality areas
- **Custom helpers**: WP Engine compatible cache functions that use either object cache or transients

## **Total Summary**

- **50+ unique cache key patterns**
- **12 major categories** (API, SEO, Sync, Migration, Rate Limiting, Taxonomy, Query, Image, Updater, Debug, Consultation, System)
- **10+ wp_cache groups** with organized functionality
- **Multiple TTL strategies** (300s to 7200s based on data volatility)
- **WP Engine compatibility** through custom cache helpers
- **Memory caching** for request-level optimization
- **Mixed naming patterns** for backward compatibility and organization
- **Complete coverage** by the cache management system
- **Proper cleanup** handled by database maintenance routines

This documentation serves as a complete reference for developers working with the plugin's caching system and for troubleshooting cache-related issues. The caching system is designed for enterprise-scale WordPress environments with WordPress VIP compliance and optimal performance across different hosting environments including WP Engine's specialized object caching.