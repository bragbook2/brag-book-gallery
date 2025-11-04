# Sync Page Refactoring Guide

## Overview

The Sync Settings page (`class-sync-page.php`) has been refactored from a monolithic 3,418-line file into a modular, component-based architecture. This refactoring improves maintainability, testability, and follows WordPress VIP coding standards and PHP 8.2+ best practices.

## Architecture

### Component Classes

The sync page functionality has been split into three main component classes:

#### 1. Sync_Manual_Controls (`includes/admin/sync/class-sync-manual-controls.php`)

**Purpose**: Handles the manual sync control center UI with stage-based synchronization.

**Features**:
- Stage 1: Procedures sync with sidebar data
- Stage 2: Case ID manifest building
- Stage 3: Case processing from manifest
- Full sync (all three stages sequentially)
- Real-time progress tracking
- File status indicators (sync data & manifest)
- Start/Stop controls

**Methods**:
- `render()` - Main control center UI
- `render_file_status_indicators()` - Sync data & manifest status
- `render_stage_buttons()` - Three stage sync buttons
- `render_stage_progress()` - Animated progress bar
- `render_stage_status_panels()` - Status information for each stage
- `render_full_sync_controls()` - Full sync and stop buttons

**Usage**:
```php
$manual_controls = new Sync_Manual_Controls();
$manual_controls->render();
```

#### 2. Sync_Automatic_Settings (`includes/admin/sync/class-sync-automatic-settings.php`)

**Purpose**: Manages automatic sync configuration including scheduling and cron status.

**Features**:
- Auto-sync enable/disable toggle
- Weekly or custom date/time scheduling
- Cron status display with next run time
- Server and browser time synchronization
- Timezone information display

**Constructor Parameters**:
- `$option_name` (string) - WordPress option name for settings storage (default: `'brag_book_gallery_sync_settings'`)

**Methods**:
- `render_auto_sync_field()` - Toggle control for enabling/disabling automatic sync
- `render_cron_status()` - Shows next scheduled sync time
- `render_sync_frequency_field()` - Weekly or custom scheduling options
- `render_server_time_display()` - Server time, timezone, and browser time

**Usage**:
```php
$automatic_settings = new Sync_Automatic_Settings( 'brag_book_gallery_sync_settings' );
$automatic_settings->render_auto_sync_field();
$automatic_settings->render_sync_frequency_field();
$automatic_settings->render_server_time_display();
```

#### 3. Sync_History_Manager (`includes/admin/sync/class-sync-history-manager.php`)

**Purpose**: Displays and manages sync history records from the database.

**Features**:
- Paginated sync history table
- Bulk delete selected records
- Clear all history
- Individual record viewing and deletion
- Status badges (completed, failed, in progress)
- Duration calculation and display
- Error message viewing
- Source tracking (manual, automatic, REST API)

**Database Schema**:
- Table: `wp_brag_book_sync_log`
- Columns: `id`, `started_at`, `completed_at`, `status`, `type`, `source`, `processed_count`, `failed_count`, `error_messages`

**Methods**:
- `render()` - Main history table with bulk actions
- `delete_record( int $record_id )` - Delete a single sync record
- `clear_all_history()` - Delete all sync records

**Usage**:
```php
$history_manager = new Sync_History_Manager();
$history_manager->render();

// Delete a record
$history_manager->delete_record( 123 );

// Clear all history
$history_manager->clear_all_history();
```

### File Structure

```
includes/admin/
├── pages/
│   └── class-sync-page.php (Orchestrator)
└── sync/ (New directory)
    ├── class-sync-manual-controls.php
    ├── class-sync-automatic-settings.php
    └── class-sync-history-manager.php

src/
├── js/
│   ├── sync-admin.js (Main entry point)
│   └── modules/
│       ├── sync-time-display.js
│       └── sync-cron-test.js
└── scss/
    └── settings/
        ├── _sync.scss
        └── _sync-control.scss
```

## Refactored Sync_Page Class

The main `Sync_Page` class now acts as an orchestrator, delegating UI rendering to component classes.

### Key Changes

**Before**:
```php
class Sync_Page extends Settings_Base {
    // 3,418 lines of mixed concerns
    public function render_manual_sync_section() { /* ... */ }
    public function render_auto_sync_field() { /* ... */ }
    public function render_sync_history_table() { /* ... */ }
    // ... many more methods
}
```

**After**:
```php
class Sync_Page extends Settings_Base {
    private Sync_Manual_Controls $manual_controls;
    private Sync_Automatic_Settings $automatic_settings;
    private Sync_History_Manager $history_manager;

    protected function init(): void {
        $this->manual_controls = new Sync_Manual_Controls();
        $this->automatic_settings = new Sync_Automatic_Settings( $this->page_config['option_name'] );
        $this->history_manager = new Sync_History_Manager();
        // ...
    }

    public function render(): void {
        // ...
        $this->manual_controls->render();
        $this->automatic_settings->render_auto_sync_field();
        $this->history_manager->render();
        // ...
    }
}
```

## CSS Architecture

### SCSS Organization

All inline CSS has been extracted to SCSS files with proper nesting and variables:

**Files**:
- `src/scss/settings/_sync.scss` - General sync styles, progress bars, status badges
- `src/scss/settings/_sync-control.scss` - Stage-based sync controls, file status, animations

**Key Features**:
- CSS custom properties for theming (`--slate-*`, `--blue-*`, etc.)
- BEM-like naming conventions
- Responsive design with media queries
- Smooth animations for progress bars and status changes
- WordPress admin styling compatibility

**Build Process**:
```bash
npm run build:css    # Build CSS from SCSS
npm run watch:css    # Watch SCSS files for changes
```

## JavaScript Architecture

### ES6 Module System

Inline JavaScript has been extracted to ES6 modules with proper dependency management.

#### Module: sync-time-display.js

**Purpose**: Real-time display of server time, browser time, and timezone information.

**Class**: `SyncTimeDisplay`

**Methods**:
- `constructor( initialServerTime )` - Initialize with server time from PHP
- `init()` - Start time display updates
- `updateTimes()` - Update both server and browser time
- `destroy()` - Stop update intervals

**Usage**:
```javascript
import { initSyncTimeDisplay } from './modules/sync-time-display.js';

// Auto-initializes if DOM element exists
// Or manually:
const timeDisplay = new SyncTimeDisplay( '2025-01-03T12:00:00Z' );
timeDisplay.init();
```

#### Module: sync-cron-test.js

**Purpose**: Manual cron job testing functionality.

**Class**: `SyncCronTest`

**Methods**:
- `constructor( config )` - Initialize with AJAX config
- `init()` - Bind event listeners
- `handleTestCron()` - Trigger cron test via AJAX
- `showNotice( message, type )` - Display WordPress-style notices

**Usage**:
```javascript
import { initSyncCronTest } from './modules/sync-cron-test.js';

// Auto-initializes if DOM element exists
// Or manually:
const cronTest = new SyncCronTest({
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'security-nonce',
    messages: { /* localized strings */ }
});
cronTest.init();
```

**Build Process**:
```bash
npm run build:js     # Build JavaScript with webpack
npm run watch:js     # Watch JS files for changes
```

## PHP Standards Compliance

All component classes follow:

### PHP 8.2+ Standards
- Strict types declaration
- Typed properties and parameters
- Return type declarations
- Named parameters support

### WordPress VIP Standards
- Proper escaping (`esc_html()`, `esc_attr()`, `esc_js()`)
- Nonce verification for AJAX requests
- Capability checks for user permissions
- Prepared SQL statements with `$wpdb->prepare()`
- Internationalization (`__()`, `esc_html__()`, `_n()`)

### Documentation
- PHPDoc blocks for all classes, methods, and properties
- `@since` and `@version` tags
- `@param` and `@return` annotations
- Detailed method descriptions

### Example:
```php
/**
 * Delete sync record
 *
 * Removes a sync record from the database.
 *
 * @since 3.3.0
 *
 * @param int $record_id Record ID to delete.
 *
 * @return bool True on success, false on failure
 */
public function delete_record( int $record_id ): bool {
    global $wpdb;

    $table_name = $wpdb->prefix . 'brag_book_sync_log';

    $result = $wpdb->delete(
        $table_name,
        array( 'id' => $record_id ),
        array( '%d' )
    );

    return false !== $result;
}
```

## Integration Steps

### Step 1: Component Initialization

In your main page class:

```php
use BRAGBookGallery\Includes\Admin\Sync\Sync_Manual_Controls;
use BRAGBookGallery\Includes\Admin\Sync\Sync_Automatic_Settings;
use BRAGBookGallery\Includes\Admin\Sync\Sync_History_Manager;

class Sync_Page extends Settings_Base {
    private Sync_Manual_Controls $manual_controls;
    private Sync_Automatic_Settings $automatic_settings;
    private Sync_History_Manager $history_manager;

    protected function init(): void {
        $this->manual_controls = new Sync_Manual_Controls();
        $this->automatic_settings = new Sync_Automatic_Settings( $this->page_config['option_name'] );
        $this->history_manager = new Sync_History_Manager();
    }
}
```

### Step 2: Rendering Components

Replace old render methods with component calls:

```php
public function render(): void {
    // Manual sync section
    $this->manual_controls->render();

    // Automatic sync settings
    $this->automatic_settings->render_auto_sync_field();
    $this->automatic_settings->render_sync_frequency_field();
    $this->automatic_settings->render_server_time_display();

    // Sync history
    $this->history_manager->render();
}
```

### Step 3: Enqueue Assets

Ensure compiled CSS and JS are enqueued:

```php
public function enqueue_admin_assets( $hook ): void {
    if ( 'toplevel_page_brag-book-gallery-sync' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'brag-book-gallery-admin',
        plugins_url( 'assets/css/brag-book-gallery-admin.css', BRAG_BOOK_GALLERY_FILE ),
        [],
        BRAG_BOOK_GALLERY_VERSION
    );

    wp_enqueue_script(
        'brag-book-gallery-sync-admin',
        plugins_url( 'assets/js/brag-book-gallery-sync-admin.js', BRAG_BOOK_GALLERY_FILE ),
        [ 'jquery' ],
        BRAG_BOOK_GALLERY_VERSION,
        true
    );
}
```

## Testing Checklist

### Manual Testing

- [ ] Manual sync controls render correctly
- [ ] Stage 1, 2, 3 buttons function properly
- [ ] Full sync button works
- [ ] Progress bars animate correctly
- [ ] File status indicators update
- [ ] Auto-sync toggle saves settings
- [ ] Weekly/custom frequency options work
- [ ] Cron test button triggers successfully
- [ ] Server/browser time updates every second
- [ ] Sync history table displays records
- [ ] Bulk delete works
- [ ] Individual record deletion works
- [ ] Clear all history works
- [ ] Status badges display correctly

### Code Quality

- [ ] All classes follow PHP 8.2+ syntax
- [ ] Proper type hints on all methods
- [ ] PHPDoc blocks complete and accurate
- [ ] WordPress escaping functions used correctly
- [ ] Nonce verification in place
- [ ] SQL queries properly prepared
- [ ] No inline CSS in PHP files
- [ ] No inline JavaScript in PHP files
- [ ] SCSS compiled to CSS successfully
- [ ] JavaScript bundled without errors

### Browser Compatibility

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Responsive design on mobile/tablet

## Migration Notes

### Breaking Changes

**None** - The refactoring maintains the same public API. All existing AJAX handlers, REST endpoints, and settings continue to work without modification.

### Backwards Compatibility

All existing functionality is preserved:
- AJAX actions remain unchanged
- Database schema unchanged
- WordPress options unchanged
- REST API endpoints unchanged
- Cron hooks unchanged

### Deprecation

The following methods in `Sync_Page` are now deprecated (but still functional for backwards compatibility):
- `render_manual_sync_section()` - Use `Sync_Manual_Controls::render()` instead
- `render_auto_sync_field()` - Use `Sync_Automatic_Settings::render_auto_sync_field()` instead
- `render_sync_frequency_field()` - Use `Sync_Automatic_Settings::render_sync_frequency_field()` instead
- `render_sync_history_table()` - Use `Sync_History_Manager::render()` instead

## Benefits of Refactoring

### Maintainability
- **Single Responsibility**: Each component handles one specific aspect of sync functionality
- **Smaller Files**: Easier to navigate and understand (component classes are 200-330 lines each)
- **Clear Dependencies**: Explicit component instantiation and dependency injection

### Testability
- **Unit Testing**: Components can be tested in isolation
- **Mock Objects**: Easy to mock dependencies for testing
- **Coverage**: Better test coverage due to focused components

### Performance
- **Lazy Loading**: Components only instantiated when needed
- **Asset Optimization**: CSS/JS compiled and minified separately
- **Caching**: Component output can be cached independently

### Developer Experience
- **IDE Support**: Better autocomplete and type inference with PHP 8.2 types
- **Debugging**: Easier to debug specific component issues
- **Documentation**: Comprehensive inline documentation
- **Modularity**: Components can be reused in other contexts

## Troubleshooting

### Components Not Rendering

**Issue**: Blank page or missing sections

**Solution**:
1. Check that components are initialized in `init()` method
2. Verify autoloader is working: `composer dump-autoload`
3. Check PHP error logs for fatal errors
4. Ensure proper namespace imports at top of file

### Styles Not Applied

**Issue**: Sync page looks unstyled

**Solution**:
1. Rebuild CSS: `npm run build:css`
2. Check that CSS file is enqueued correctly
3. Clear WordPress cache
4. Check browser console for 404 errors on CSS files

### JavaScript Not Working

**Issue**: Time display not updating, cron test button not working

**Solution**:
1. Rebuild JS: `npm run build:js`
2. Check that JS file is enqueued correctly
3. Check browser console for JavaScript errors
4. Verify webpack compilation completed successfully

### Database Errors

**Issue**: Sync history table not showing

**Solution**:
1. Check that `wp_brag_book_sync_log` table exists
2. Re-activate plugin to create database tables
3. Check database version: `Database::check_database_version()`
4. Review MySQL error logs

## Future Enhancements

### Planned Improvements

1. **AJAX Handler Separation**: Extract AJAX handlers into dedicated classes
2. **REST API Components**: Separate REST endpoint logic
3. **Progress Tracking**: Real-time WebSocket-based progress updates
4. **Settings Validation**: Dedicated settings validator class
5. **Cron Manager**: Separate class for cron management
6. **Export/Import**: Sync history export to CSV/JSON

### Extension Points

The component architecture allows for easy extension:

```php
// Custom sync control component
class Custom_Sync_Controls extends Sync_Manual_Controls {
    public function render(): void {
        // Custom rendering logic
        parent::render();
        // Additional UI elements
    }
}

// Use custom component
$this->manual_controls = new Custom_Sync_Controls();
```

## References

- [WordPress VIP Coding Standards](https://docs.wpvip.com/technical-references/vip-codebase/code-quality-and-best-practices/)
- [PHP 8.2 Release Notes](https://www.php.net/releases/8.2/en.php)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)

## Version History

- **3.3.0** (2025-01-03)
  - Initial refactoring of Sync_Page into component classes
  - Extracted CSS to SCSS modules
  - Extracted JavaScript to ES6 modules
  - Added comprehensive PHPDoc comments
  - Implemented PHP 8.2+ strict types throughout

## Support

For questions or issues related to this refactoring:
1. Check this documentation first
2. Review component class PHPDoc comments
3. Check GitHub issues for similar problems
4. Create new issue with detailed reproduction steps

---

**Last Updated**: January 3, 2025
**Author**: Claude Code (Anthropic)
**Version**: 3.3.0
