# BRAGBook Gallery Plugin - Complete Transient Key Documentation

This document provides a comprehensive reference of all transient keys used by the BRAGBook Gallery plugin for caching and temporary data storage.

**IMPORTANT:** All transient keys now use the standardized prefix `brag_book_gallery_transient_` for consistency and easier management.

## **API Data Transients**

### Core API Cache Keys (generated via `get_*_cache_key()` methods):
- **`brag_book_gallery_transient_sidebar_{hash}`** - Sidebar data from API  
  Location: `includes/extend/class-cache-manager.php:259`
- **`brag_book_gallery_transient_cases_{hash}`** - Cases data from API  
  Location: `includes/extend/class-cache-manager.php:289`
- **`brag_book_gallery_transient_all_cases_{hash}`** - All cases data  
  Location: `includes/extend/class-cache-manager.php:334`
- **`brag_book_gallery_transient_carousel_{hash}`** - Carousel data  
  Location: `includes/extend/class-cache-manager.php:365`

### Generic API Cache:
- **`brag_book_gallery_transient_api_{type}_{hash}`** - General API responses  
  Location: `includes/rest/class-endpoints.php:1112`

### Carousel-Specific Cache:
- **`brag_book_gallery_transient_carousel_case_{hash}`** - Individual carousel case cache  
  Location: `includes/extend/class-data-fetcher.php:595`

## **SEO Data Transients**

- **`brag_book_gallery_transient_combined_sidebar_{hash}`** - Combined sidebar data for SEO  
  Location: `includes/seo/class-seo-manager.php:717`
- **`brag_book_gallery_transient_sidebar_{hash}`** - Individual sidebar data for SEO  
  Location: `includes/seo/class-seo-manager.php:774`
- **`brag_book_gallery_transient_sitemap_content`** - Sitemap content cache  
  Location: `includes/seo/class-sitemap.php`
- **`brag_book_gallery_transient_sitemap_last_modified`** - Sitemap modification time  
  Location: `includes/seo/class-sitemap.php`

## **Sync & Migration Transients**

### Sync Status & Progress:
- **`brag_book_gallery_transient_sync_status`** - Sync operation status  
  Locations:
  - Get: `includes/sync/class-sync-manager.php:791`
  - Get: `includes/admin/class-settings-local.php:102`
  - Get: `includes/admin/class-settings-mode.php:327`
  - Get: `includes/mode/class-mode-manager.php:373`
  - Set: `includes/admin/class-settings-local.php:431`
  - Delete: `includes/sync/class-sync-manager.php:819`

- **`brag_book_gallery_transient_sync_progress`** - Sync operation progress  
  Locations:
  - Set: `includes/sync/class-sync-manager.php:1090`
  - Set: `includes/sync/class-sync-manager.php:1110`
  - Delete: `includes/sync/class-sync-manager.php:1208`

- **`brag_book_gallery_transient_sync_lock`** - Sync operation lock  
  Locations:
  - Set: `includes/sync/class-sync-manager.php:1051`
  - Get: `includes/sync/class-sync-manager.php:1057`
  - Delete: `includes/sync/class-sync-manager.php:1072`

### Force Update Flags:
- **`brag_book_gallery_transient_force_update_all`** - Force update all flag  
  Location: `includes/sync/class-sync-manager.php:1129`
- **`brag_book_gallery_transient_force_update_cases`** - Force update specific cases  
  Location: `includes/sync/class-sync-manager.php:1122`

### Migration Cache:
- **`brag_book_gallery_transient_migration_status`** - Migration status  
  Location: `includes/migration/class-migration-manager.php:692`
- **`brag_book_gallery_transient_migration_{hash}`** - Migration data cache  
  Locations:
  - Get: `includes/migration/class-migration-manager.php:1653`
  - Set: `includes/migration/class-migration-manager.php:1686`

## **Rate Limiting Transients**

- **`brag_book_gallery_transient_mode_rate_limit_{hash}`** - Mode operation rate limiting  
  Locations:
  - Key generation: `includes/mode/class-mode-manager.php:915`
  - Get: `includes/mode/class-mode-manager.php:916`
  - Set: `includes/mode/class-mode-manager.php:920`
  - Set: `includes/mode/class-mode-manager.php:935`

- **`brag_book_gallery_transient_migration_rate_limit_{hash}`** - Migration rate limiting  
  Location: `includes/migration/class-migration-manager.php:1524`

- **`brag_book_gallery_transient_rate_limit_{hash}`** - API rate limiting  
  Locations:
  - Location: `includes/traits/trait-api.php:570`
  - Location: `includes/rest/class-endpoints.php:1851`

## **Consultation Form Transients**

- **`brag_book_gallery_transient_consultation_hourly_{hash}`** - Hourly submission limits  
  Locations:
  - Key: `includes/core/class-consultation.php:829`
  - Get: `includes/core/class-consultation.php:830`
  - Set: `includes/core/class-consultation.php:861`

- **`brag_book_gallery_transient_consultation_daily_{hash}`** - Daily submission limits  
  Locations:
  - Key: `includes/core/class-consultation.php:845`
  - Get: `includes/core/class-consultation.php:846`
  - Set: `includes/core/class-consultation.php:862`

## **Taxonomy Transients**

- **`brag_book_gallery_transient_{taxonomy}_terms`** - Specific taxonomy terms  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:635`
- **`brag_book_gallery_transient_{taxonomy}_hierarchy`** - Taxonomy hierarchy  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:636`
- **`brag_book_gallery_transient_term_{id}`** - Individual term data  
  Location: `includes/taxonomies/class-gallery-taxonomies.php:651`
- **`brag_book_gallery_transient_{cache_key}`** - General taxonomy cache  
  Locations:
  - Get: `includes/taxonomies/class-gallery-taxonomies.php:850`
  - Set: `includes/taxonomies/class-gallery-taxonomies.php:870`

## **Mode Management Transients**

- **`brag_book_gallery_transient_mode_{hash}`** - Mode data cache  
  Locations:
  - Key: `includes/mode/class-mode-manager.php:1054`
  - Get: `includes/mode/class-mode-manager.php:1055`
  - Set: `includes/mode/class-mode-manager.php:1088`

## **Notice/UI Transients**

- **`brag_book_gallery_transient_show_rewrite_notice`** - Rewrite rules notice flag  
  Locations:
  - Get: `includes/extend/class-rewrite-rules-handler.php:676`
  - Delete: `includes/extend/class-rewrite-rules-handler.php:823`
  - Delete: `includes/extend/class-ajax-handlers.php:177`
  - Delete: `includes/extend/class-ajax-handlers.php:188`
  - Reference: `includes/admin/debug-tools/class-rewrite-flush.php:1541`

## **Plugin Updater Transients**

- **`{plugin_slug}_github_update_check`** - GitHub update check cache  
  Locations:
  - Get: `includes/core/class-updater.php:120`
  - Set: `includes/core/class-updater.php:139`
  - Delete: `includes/core/class-updater.php:154`

## **Cache Key Patterns by Type**

The cache management system uses these patterns to identify transient types:
- `%transient_%brag_book_gallery_transient_sidebar_%` - Sidebar data
- `%transient_%brag_book_gallery_transient_cases_%` - Cases data  
- `%transient_%brag_book_gallery_transient_carousel_%` - Carousel data
- `%transient_%brag_book_gallery_transient_all_cases_%` - All cases data
- `%transient_%brag_book_gallery_transient_%` - General pattern

Location: `includes/extend/class-cache-manager.php:466-470`

## **Database Cleanup Patterns**

Several classes include database cleanup that targets these patterns:
- `_transient_brag_book_gallery_transient_%` - Main plugin transients
- `_transient_timeout_brag_book_gallery_transient_%` - Timeout entries
- `_transient_brag_book_gallery_transient_api_%` - API-specific transients
- `_transient_brag_book_gallery_transient_migration_%` - Migration transients

### Cleanup Locations:
- `includes/migration/class-migration-manager.php:795-796`
- `includes/migration/class-migration-manager.php:1714-1715`
- `includes/extend/class-cache-manager.php:103-104`
- `includes/rest/class-endpoints.php:1381-1382`
- `includes/rest/class-endpoints.php:1393-1394`
- `includes/traits/trait-api.php:409-410`
- `includes/traits/trait-api.php:413-414`

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

1. **Pattern Matching**: Uses the plugin prefix `brag_book_gallery_transient_` to identify all plugin transients
2. **Type Detection**: Categorizes transients by their key patterns
3. **Individual Management**: View, delete, and analyze specific cache items
4. **Bulk Operations**: Clear all cache or selected items by type
5. **Statistics**: Shows cache size, expiration times, and usage metrics

## **Standardized Prefix Benefits**

The new `brag_book_gallery_transient_` prefix provides:

- **Consistency**: All transients follow the same naming convention
- **Easy Identification**: Clear distinction from WordPress core and other plugin transients
- **Improved Management**: Simplified cache management and debugging
- **Better Organization**: Logical grouping of all plugin-related transients
- **Future-Proof**: Scalable naming system for new features

## **Migration Notes**

This update changed the transient key structure from various patterns like:
- `brag_book_*` → `brag_book_gallery_transient_*`
- `brag_book_gallery_*` → `brag_book_gallery_transient_*`
- `consultation_*` → `brag_book_gallery_transient_consultation_*`

All existing transients with the old naming will be cleared automatically by the cache management system.

## **Total Summary**

- **30+ unique transient key patterns**
- **8 major categories** (API, SEO, Sync, Rate Limiting, Forms, Taxonomy, Mode, UI)
- **Standardized prefix** for all keys: `brag_book_gallery_transient_`
- **Complete coverage** by the cache management system
- **Consistent naming** following plugin standards
- **Proper cleanup** handled by database maintenance routines

This documentation serves as a complete reference for developers working with the plugin's caching system and for troubleshooting cache-related issues.