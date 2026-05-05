=== BRAG book Gallery ===
Contributors: bragbook2026
Tags: gallery, before-after, medical, cosmetic, procedures
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 4.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display before and after photo galleries for medical and cosmetic procedures, synced from the BRAG book application.

== Description ==

BRAG book Gallery displays before and after photo galleries for medical and cosmetic procedures on your WordPress site. Cases are synced from the [BRAG book application](https://www.bragbookgallery.com/) and displayed using shortcodes with filtering, search, and responsive layouts.

= Features =

* **Before & After Galleries** — Showcase procedure results with side-by-side or slider comparisons
* **Procedure Filtering** — Visitors can filter cases by procedure category and subcategory
* **Search** — Autocomplete search across cases and procedures
* **Favorites** — Visitors can save favorite cases for later viewing
* **Carousel** — Embeddable before/after carousels for any page or post
* **SEO Optimized** — Automatic meta tags, Open Graph data, and XML sitemap entries for gallery pages
* **Responsive Design** — Mobile-first layouts that work on all screen sizes
* **Customizable** — Configure columns, items per page, image display modes, and custom CSS
* **Data Sync** — Automatic and manual synchronization with the BRAG book application
* **Nudity Warning** — Optional content warning overlay for sensitive before/after images

= Shortcodes =

* `[brag_book_gallery]` — Main gallery with smart auto-detection of page context
* `[brag_book_gallery_cases]` — Cases grid view
* `[brag_book_gallery_case case_id="12345"]` — Single case detail
* `[brag_book_gallery_favorites]` — User favorites page
* `[brag_book_carousel procedure="arm-lift" limit="5"]` — Procedure carousel

= External Service =

This plugin connects to the **BRAG book application** (https://app.bragbookgallery.com) to sync case data, procedure categories, and images. An active BRAG book account and API token are required for the plugin to function.

* **Service website:** [https://www.bragbookgallery.com/](https://www.bragbookgallery.com/)
* **Terms of Service:** [https://www.bragbookgallery.com/terms](https://www.bragbookgallery.com/terms)
* **Privacy Policy:** [https://www.bragbookgallery.com/privacy](https://www.bragbookgallery.com/privacy)

Data transmitted to the service includes your API token, website property ID, and sync status reports. The service returns case data, procedure categories, and image URLs that are stored locally in your WordPress database.

No visitor data is sent to the external service. Case view tracking uses your site's own REST API endpoints.

== Installation ==

1. Upload the `brag-book-gallery` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **BRAG book > API Settings** and enter your API token and website property ID
4. Run an initial data sync from **BRAG book > Sync**
5. The plugin automatically creates a gallery page, or you can add the `[brag_book_gallery]` shortcode to any page

= Requirements =

* WordPress 6.8 or higher
* PHP 8.2 or higher
* An active [BRAG book](https://www.bragbookgallery.com/) account

== Frequently Asked Questions ==

= Do I need a BRAG book account? =

Yes. The plugin displays cases from the BRAG book application. You need an active account with an API token and website property ID to sync data.

= How do I sync cases? =

Go to **BRAG book > Sync** in your WordPress admin. You can run a manual sync or configure automatic sync on a schedule. The BRAG book application can also trigger syncs remotely.

= Can I customize the gallery appearance? =

Yes. The settings page lets you configure columns, items per page, image display modes, fonts, and more. You can also add custom CSS from **BRAG book > General Settings**.

= Does the plugin create custom database tables? =

Yes. The plugin creates two tables for sync logging and case registry tracking. These are removed when the plugin is uninstalled.

= What happens to my data if I uninstall the plugin? =

Uninstalling the plugin removes all plugin settings, custom database tables, transient caches, and the synced case post type data. Deactivating the plugin does not remove any data.

== Screenshots ==

1. Gallery grid view with procedure filtering
2. Case detail with before and after images
3. Admin settings page
4. Sync management interface

== Changelog ==

= 4.5.1 =
* Fixed: Saving a case post (or editing its SEO description in Yoast / Rank Math / AIOSEO) no longer corrupts signed Supabase / S3 image URLs by stripping `%XX` percent-encoded sequences. WordPress's `sanitize_text_field` and `sanitize_textarea_field` strip every `%XX` octet from any string they touch, which silently rewrote stored URLs and broke their JWT signatures (causing `InvalidSignature` errors when fetching images).
* Fixed: Case-card carousel now shows every slide when the API populates `sideBySide.highDefinition` for some photoSets but not all. The reader previously fell back from the high-res list to the post-processed list only when the high-res list was completely empty, so a partially-populated high-res field hid the full slide set. The reader now uses the high-res list only when its length matches the post-processed list and falls back to the post-processed list otherwise.
* Fixed: Removed an unreachable `sanitize_textarea_field` branch from the API field-mapping loop in `Post_Types::save_api_response_data` that would have corrupted URL meta if anyone wired a future API field to one of those keys.
* Added: Managed meta descriptions for case posts, procedure taxonomy archives, and the main gallery page across Yoast, Rank Math, AIOSEO, SEOPress, and the WordPress default. A user-edited per-post or per-term value in the active SEO plugin always wins; the managed value only fills in when the SEO plugin would otherwise emit a generic auto-generated default.
* Added: Single-case meta description hierarchy — `brag_book_gallery_seo_page_description` post meta, falling back to `brag_book_gallery_notes`.
* Added: Procedure-archive meta description sourced from the procedure term's *Gallery Details* meta (`brag_book_gallery_details`).
* Added: Main gallery page meta description synthesized from the synced post-type and taxonomy data (case count + top procedure names) instead of rendering the shortcode into the description; cached as a transient that invalidates on case save and procedure-term changes.
* Hardened: Case API meta box save handler — URL textareas (`brag_book_gallery_case_*_url`) and the Gutenberg `brag_book_gallery_image_url_sets` JSON are now both nonce-gated on `case_api_data_nonce` and short-circuit when the submitted value matches what is already stored, so unrelated save flows can no longer trigger an idempotent re-save that mutates URLs.

= 4.5.0 =
* Changed: Renamed internal post type `form-entries` to `brag_book_forms` for plugin namespace compliance; existing entries are migrated automatically on upgrade
* Changed: Prefixed AJAX actions with `brag_book_gallery_` to avoid global naming collisions
* Security: Removed `JSON_UNESCAPED_SLASHES` from inline JSON output to prevent script-tag breakout
* Security: Hardened nonce verification by sanitizing inputs with `sanitize_text_field( wp_unslash() )`
* Security: Sanitized JSON-decoded `$_POST` values in the favorites AJAX handler
* Improved: Switched native `json_encode()` to `wp_json_encode()` across the plugin for safer escaping

= 4.4.6 =
* Fixed: Favorites empty state layout on dedicated favorites page
* Fixed: Logged-in users always seeing empty favorites state when localStorage was empty
* Fixed: Sync no longer writes procedure_order to child procedures

= 4.4.5 =
* Fixed: procedure_order now written on every standard sync run
* Fixed: jobId echoed back to BRAG Book API for externally-triggered syncs
* Fixed: Sidebar and dropdown navigation ordered by procedure_order
* Fixed: Case detail now displays patient height and weight

= 4.4.4 =
* Fixed: procedure_order populated from API during sync
* Fixed: Cases now associated with all matching procedure terms
* Fixed: View tracking uses correct caseProcedureId exclusively
* Fixed: Procedure views no longer accidentally triggered on case pages

= 4.4.3 =
* Fixed: Remote sync reliability with self-chaining batch processing
* Fixed: Race condition causing "0 cases synced" on remote syncs
* Improved: Sync performance with cached construction-time values
* Improved: Removed artificial sync delays

== Upgrade Notice ==

= 4.5.1 =
Fixes signed-URL corruption (Supabase / S3 `InvalidSignature` errors) when saving cases or editing SEO meta descriptions, restores missing slides on cases that have a partial high-resolution image set, and adds managed meta descriptions for cases, procedure archives, and the main gallery page across all major SEO plugins. Re-sync any cases whose images were already corrupted by earlier saves.

= 4.5.0 =
Security and naming-convention release. Internal post type renamed; existing form entries are migrated automatically. Recommended upgrade for all users.

= 4.4.6 =
Fixes favorites display issues and sync procedure ordering for child procedures.
