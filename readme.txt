=== BRAG book Gallery ===
Contributors: bragbook2026
Tags: gallery, before-after, medical, cosmetic, procedures
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 4.9.2
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
* `[brag_book_gallery provider="dr-jane-smith"]` — A provider's gallery on any page
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

= 4.9.3-beta10 =
* Changed: The General settings screen is organised into named sections instead of one long list, so related settings sit together. Display & Gallery is now eight groups, and the plugin update channel and API environment are set apart at the end.
* Changed: The Save button stays with you as you scroll a long settings tab, and the settings menu stays in view on wide screens and turns into a row of tabs on narrower ones.
* Changed: The SEO search preview now sits beside the title and description it previews.
* Changed: A provider whose name was synced as "John A Smith" now reads "John A. Smith" everywhere it appears, and their name links to their profile page when they have one.
* Fixed: Arriving at a case from a provider no longer leaves next and previous arrows pointing at cases that provider does not have.

= 4.9.3-beta9 =
* Fixed: Carousel thumbnails cut the sides off a photo and sat at different heights when a case mixed portrait and landscape shots. Each thumbnail now shows its whole photo, and they line up along the strip at one height with no white gap around them.

= 4.9.3-beta8 =
* Changed: On a provider page filtered by procedure, only the provider's name stays bold in the heading — the procedure now sits alongside it in plain text.

= 4.9.3-beta7 =
* Added: Filtering a provider page by procedure now says so in the heading — "Prof. Mc Nugget, MD - Breast Augmentation Before & After Gallery". Reset, or All Procedures, puts it back to the provider's name on its own.

= 4.9.3-beta6 =
* Added: A provider's gallery can now live on a page of its own. Add [brag_book_gallery provider="dr-jane-smith"] to any page — or [brag_book_gallery provider_id="43"] if you have the ID rather than the web address name — and it works exactly like that provider's own page: the same procedure and filter dropdowns, the same case grid and Load More, and previous/next on a case stays inside that provider.
* Added: You can now choose what the gallery calls a provider. Two fields under Enable Providers set the word, singular and plural, for practices who say Doctor, or keep doctors and providers apart. The provider filter takes all of its wording from them. Leave them blank and nothing changes.
* Fixed: The consultation form height field demanded a value on every save, even with the built-in form selected, when it is allowed to be left blank.
* Fixed: Updates to the gallery's stylesheet and scripts were not reaching visitors whose browser had already cached the old ones, which is why last release's thumbnail strip fix may have looked like it had not worked. Rebuilt files are now recognised as new.
* Fixed: The fix that stops the hover panel covering the thumbnail strip no longer applies to V3 cards, which show their details below the image and never covered it.

= 4.9.3-beta5 =
* Added: The consultation dialog can show your own GoHighLevel form instead of the built-in one. Choose the source under the consultation toggle in Display & Gallery Settings and paste the form's address from GoHighLevel; a height field sets how much room it opens at before GoHighLevel resizes it to fit. Requests through the embedded form go straight to GoHighLevel, so the plugin neither stores nor emails them. The built-in form keeps serving requests until an address is filled in.
* Added: The procedure filter on a provider page now shows how many of that provider's cases sit behind each procedure.
* Changed: Opening a case from a provider page hides the gallery filter bar, which offers a gallery picker and filters for the whole library rather than the provider being browsed.
* Fixed: On V2 cards the hover panel covered the thumbnail strip, taking the provider name and arrow with it. The panel now ends where the strip begins, and the thumbnails stay clickable while it is showing.
* Fixed: The thumbnails now sit centred in their strip rather than starting hard left.

= 4.9.3-beta4 =
* Added: A choice of navigation for the image carousel on V2/V3 cards. Alongside the existing dots over the image, cases can now show a strip of photo thumbnails between the image and the case title, with an arrow either side. The choice sits under the carousel toggle in Display & Gallery Settings. Thumbnails use the small version of each photo, and a strip with more thumbnails than fit scrolls sideways.
* Fixed: The Delete All Synced Data dialog cut off its buttons. It is wider now, its buttons wrap onto a second row rather than disappearing on a narrow screen, and the warning and bin icons have been removed.

= 4.9.3-beta3 =
* Changed: The record of which provider a visitor came from now stores the provider's ID as well as their web address name. Navigation used the name and worked, but nothing was kept to fall back on if that name ever stopped matching — after a rename, for instance. Both provider page layouts and every entry in the provider dropdown now carry the ID.

= 4.9.3-beta2 =
* Fixed: Previous/next on a case ignored the provider page a visitor came from wherever cases are shown as single-image cards in the newest card style. Those cards carry a different link to the ones with a photo carousel, and only the carousel one was being watched for, so nothing recorded which gallery the visitor had come from and the buttons kept following the procedure. All case card links are now watched, whatever the card style or number of photos.

= 4.9.3-beta1 =
* Fixed: Provider web addresses still came back with the member id on the end after a sync — /providers/dr-mi-payne-8/ rather than /providers/dr-mi-payne/. Only one of the six places that create or rename a provider was shortening the address, and not the one a full sync uses. All of them now do, and the one-time clean-up that shortens existing addresses runs again and also catches providers renamed since their page was created.
* Fixed: Provider pages showed "Provider profile for provider ID 4" beneath the heading. That text was written into every provider as its description when it was first synced; it is no longer written, and existing ones are cleared. A description you write yourself is kept and still shown.

= 4.9.2 =
Provider pages, responsive images and context-aware navigation. The entries below list the beta iterations that led here.

* Added: Every provider has a page of their own at /providers/{provider}/, listing their cases with the same layout, cards and load-more behaviour as a procedure page, and a /providers/ page listing all providers with their profile photos and case counts. A provider's name on a case links through to their page.
* Added: Provider pages offer a procedure filter in place of the gallery picker, so visitors can narrow a provider's cases to one procedure.
* Added: Previous/next on a case follows wherever the visitor came from — a provider's cases when they arrived from a provider page or filter, looping from the last case back to the first, and the procedure's cases otherwise.
* Added: Small and medium image variants are captured from the API and served to the screens that suit them, so phones and tablets download far smaller files than the full-size image. This covers the grid, carousels, favourites, the case detail viewer and related cases.
* Added: The nudity warning's title, body text, button label and decline link are editable, along with a choice of how it appears: one full-screen warning per page, the per-procedure overlay, or the per-case flag only. Declining sends the visitor to a page of your choosing, and a reset button restores the defaults.
* Added: The `[brag_book_gallery_procedures]` shortcode accepts `randomize="true"` to shuffle cases on every page load; Load More continues the same shuffled order.
* Added: A Reset to Default button on the Landing Page Text editor.
* Changed: Provider web addresses no longer carry the API member id — /providers/dr-mi-payne/ rather than /providers/dr-mi-payne-8/. Existing providers are renamed once; links to the old addresses are not redirected.
* Changed: "Load More" paginates within the active view everywhere — provider, location and procedure — instead of loading everything or ignoring the filters.
* Changed: Delete All Synced Data runs in batches with a progress bar and reports what it removed, so large libraries no longer time out mid-delete.
* Fixed: Carousels swipe naturally on phones and tablets again.
* Fixed: The gallery page's SEO title and meta description rendered as a single letter.
* Fixed: Cases in more than one procedure category no longer appear twice in provider grids, Load More results or the previous/next sequence.
* Fixed: Numerous provider page issues found in testing: the page returning "not found", a missing first card, and stray empty paragraphs in block themes.
* Security: The API token is no longer printed into the page source, and rate limiting works again on the consultation form, the favourites email lookup and the API client. Rotate your token if the site has been public.

= 4.9.2-beta10 =
* Fixed: Opening a case from a provider page followed the procedure's cases rather than the provider's on any site whose Procedures View is not set to Tiles. Provider pages render two layouts, and only the tiles one told the page which provider was being viewed, so the other layout read the provider's name as a procedure and navigated accordingly. Both layouts now carry it, so previous/next follows the provider whatever the setting.
* Fixed: A `[brag_book_gallery_procedures provider_id="…"]` grid did not pass its provider on to the case page either, so navigation from one of those grids fell back to the procedure.

= 4.9.2-beta9 =
* Fixed: Previous/next navigation ignored the gallery a visitor arrived from on any site whose cases use SEO-friendly web addresses. The case page recognised itself only by an address ending in the case number, so on those sites it never applied the procedure or provider the visitor was browsing and left the default links in place. It now recognises the page by the case view it renders.

= 4.9.2-beta8 =
* Added: Visitors can decline the nudity warning. The full-screen warning now shows a decline link next to Proceed, which sends them to a page you choose — the home page by default. The link text and destination are set in the nudity warning settings. It appears on the full-screen warning only, since the per-case overlays cover one case rather than the whole page.
* Added: A Reset Nudity Settings button that restores the warning's preset and wording to the defaults.
* Added: Provider pages now offer a procedure filter in place of the gallery picker, listing the procedures that provider has cases in so visitors can narrow the grid to one of them. The picker only led away to another procedure, so it has been hidden there.
* Added: Opening a case from a provider page now steps the previous/next arrows through that provider's cases instead of the procedure's, and loops from the last case back to the first. Choosing a procedure in the new filter narrows that to the provider's cases in that procedure. Opening a case from a procedure page behaves exactly as before.
* Added: The `[brag_book_gallery_procedures]` shortcode accepts `randomize="true"`, which shuffles the cases into a different order on every page load. Load More continues the same shuffled order rather than starting again, so no case repeats or goes missing.
* Fixed: Provider pages left the first card missing and spaced the rest out oddly, because block themes inserted an empty paragraph into the grid where the first card belonged.

= 4.9.2-beta7 =
* Fixed: Carousels swipe naturally again on phones and tablets. The carousel replaced the browser's own scrolling with its own, so a swipe moved the slides only as far as the finger travelled and stopped the moment it lifted, with no glide and no settling onto a slide. Touch now scrolls the way every other page on the phone does. Grab-and-drag with a mouse is unchanged.
* Added: Each provider now has its own page at /providers/{provider}/, listing that provider's cases with the same layout, card style and load-more behaviour as a procedure page.
* Added: The nudity warning's title, body text and button label are editable in the settings, along with a choice of how it appears: one full-screen warning per page, the existing per-procedure overlay, or the per-case flag only. The per-case flag now comes from the synced data and can be changed on the case itself.
* Fixed: Provider pages returned a "not found" error.
* Fixed: The full-page nudity warning lost its styling, leaving the button without its background.
* Fixed: Block themes showed stray empty paragraphs inside gallery cards.
* Fixed: Accepting the nudity warning was not remembered on the next page.

= 4.9.2-beta5 =
* Fixed: The small and medium versions of case images were never actually downloaded. They were offered to the browser, but as a list of options the browser was free to ignore — and because the generated versions are much smaller than the original, it always chose the full-size image. Narrow screens are now pointed directly at the smaller versions instead of being given a choice, so phones and tablets download a far smaller file. Desktop screens continue to use the full-size image, which remains the only version large enough for them.
* Fixed: Images could lose their smaller versions entirely after a re-sync. The gallery matched an image to its smaller versions by comparing the complete web address, including the security signature that changes every time the address is regenerated, so any mismatch silently dropped them. Matching now ignores the signature.
* Fixed: The gallery understated how much space case images occupy, ignoring the filter sidebar on desktop. Requested image widths were up to 44% too large, pushing every request to the full-size image.
* Added: A Reset to Default button on the Landing Page Text editor. It restores the plugin's default content, including the two procedure carousels added in 4.9.2-beta4, and stores nothing until settings are saved. Sites that had already saved landing page text had no way to pick up that new default.
* Changed: Delete All Synced Data now shows a progress bar and reports what it removed in the dialog itself, rather than a browser alert. The deletion runs in batches, so libraries large enough to previously time out mid-delete — leaving the site half-emptied with no indication of what happened — now complete reliably.
* Fixed: The location search no longer loads Google Maps a second time when another plugin or theme on the page has already loaded it, which previously broke the search box.
* Changed: Removed an unused case-detail rendering routine and its stylesheet rules.

= 4.9.2-beta4 =
* Added: Responsive image variants now apply to the remaining views. Carousels, favourites cards, the case detail main viewer and related-case cards all offer the small and medium renditions alongside the full-size image, so browsers download a file suited to the space it is shown in rather than the full-size image everywhere. Cases synced before variants existed, and any view where the smaller sizes have not been generated, continue to use the full-size image.
* Added: The favourites grid rendered in the browser now receives the matching image sources from the server, so it benefits from the same responsive images as the server-rendered grids.
* Added: The default landing page content now includes two procedure carousels, for breast augmentation and liposuction, which can be edited or removed in the landing page editor. This applies only to sites that have never saved their own landing page text; existing content is untouched.
* Fixed: Grid images could load a rendition smaller than the space they fill. Below 576px a case card spans the full width of the screen, but the gallery declared it as half, so browsers were free to pick an image sized for half the width and render it soft. The declared sizes now match the grid at every breakpoint.
* Fixed: Clicking a thumbnail on a case detail page could leave the previous photo on screen once the main viewer carried multiple image sources, because only the primary source was swapped. Both are now swapped together.
* Changed: Removed roughly 1,850 lines of unreachable gallery JavaScript — card builders with no callers and three paths that posted to server endpoints that do not exist — reducing the frontend script bundle. No behaviour changes: every removed path was verified unreachable before deletion.

= 4.9.2-beta3 =
* Fixed: The gallery page's SEO title and meta description rendered as a single letter — the first character of the configured value — because the saved setting was read as a list when it is stored as plain text. Both now render the full configured title and description.
* Fixed: Several SEO routines crashed or silently did nothing after the caching layer they depended on was removed: the sidebar, procedure and case SEO lookups threw a fatal error whenever they ran, and the transient cleanup routine exited immediately every time without deleting anything. The obsolete cache handling has been removed, and a gutted cleanup routine and an unused rate limiter along with it.
* Fixed: Sync recorded image sizes the API had not generated. When a case's small or medium variant came back as null, the full-size URL was stored in its place, so a case claimed renditions that did not exist and the responsive `srcset` offered a full-size file to the browser under a 400px width descriptor. Null variants are now stored empty; only sizes the API actually returns are saved. Existing cases keep their current values until re-synced.
* Fixed: Clearing a Small or Medium field in a case's "Image URLs" tab silently re-filled it from the next larger size. A cleared field now stays cleared.
* Fixed: The `[brag_book_gallery_cases]` shortcode crashed with a fatal error on any site with API settings configured, so it never rendered a gallery. It referenced a data-fetching class that was removed in September 2025. The call that triggered the crash fetched cases from the API and then discarded them — the grid has rendered from WordPress posts since — so it and the other stale references have been removed.
* Fixed: Procedure pages ignored the "Sorts Per Page" setting and rendered every case on first load. Because the grid already held every case, the first "Load More" appended page two on top of them and repeated cases. Procedure views now render one page and continue from where that page ends.
* Fixed: The "Load More" button on procedure pages did not carry the procedure or provider it was rendered for, so paging could fall back to an unscoped list of every case. It now keeps the active provider, procedure and location context across every load.
* Changed: "Sorts Per Page" is now the single page size everywhere — the initial grid, the provider filter, the location search, the JS-filtered gallery, and every "Load More" batch all use it. It previously resolved to five different values (200, 20, 12 or 10 depending on the code path), so one page could render 200 cases while its Load More button assumed 12.
* Changed: The procedure tiles view and the filtered gallery results grid now page at the setting and offer a "Load More" button, instead of rendering every case at once.
* Fixed: The `[brag_book_gallery_procedures]` grid ignored "Sorts Per Page" and always loaded 20 cases per batch. It now uses the setting; an explicit `limit` attribute still overrides it.
* Fixed: "Load More" in the `[brag_book_gallery_procedures]` grid ignored the selected provider and appended unfiltered cases on top of the filtered results. The grid ran its own pagination that read the provider from the shortcode attribute rather than the provider dropdown, so a provider chosen on the page was never applied. It now shares the same context-aware pager as every other view.
* Fixed: A `provider_id` or provider slug matching no provider returned every case instead of none, so an unresolved provider filter silently behaved as no filter at all.
* Fixed: "Sorts Per Page" is now clamped server-side to its documented 1-200 range. The field's limits were enforced only by the browser, so a value of 0 could be saved and would render an empty, unpageable grid.
* Fixed: Provider-filtered results are no longer capped at 99 cases; the cap was unreachable code and the documented behaviour never applied. Provider results page at the site's setting like every other view.

= 4.9.2-beta2 =
* Added: Small, medium and full image variants are now captured from the v2 cases API and stored per case. The gallery grid, case carousels and single-case view render a responsive `srcset` so browsers load an appropriately sized image (grid cards favour the smaller variants; the case detail viewer uses the full image and its thumbnails use the small variant). Variants that the API has not generated yet fall back to the full-size image.
* Added: The case editor's "Image URLs" section is now an editable, tabbed interface with per-variant (full/medium/small) fields and thumbnail previews. The API Case Data meta-box tabs now work and follow the WordPress admin colour scheme.
* Changed: "Load More" on the main gallery and the `[brag_book_gallery_cases]` shortcode now paginates within the active view. Selecting a provider loads only that provider's cases, entering a location loads only cases within 50 (widening to 100) miles, and on a procedure page the current procedure is combined with either. The provider filter and location search paginate through the same ordered result set instead of loading everything at once.
* Fixed: Provider-filtered case cards now carry their procedure context, so provider-scoped previous/next navigation works when arriving from the provider dropdown.

= 4.9.2-beta1 =
* Added: Context-aware next/previous navigation on single case pages. When a visitor arrives from a provider-filtered view (the provider dropdown or a `[brag_book_gallery_procedures provider_id="…"]` grid), the prev/next buttons walk that provider's cases within the current procedure, falling back to provider-wide when there is no procedure context. Navigating from a view without a provider filter resets to the normal procedure navigation.
* Changed: "Load More" in the `[brag_book_gallery_procedures]` grid now respects the active procedure category on a procedures archive, instead of loading the provider's cases across every category.
* Changed: Provider syncs now update both the Synced Photo and the Profile Photo, so the Profile Photo always reflects the latest synced image. A manually-chosen Profile Photo is no longer preserved across syncs, and the provider term editor wording has been updated to match.
* Fixed: Cases assigned to more than one procedure category no longer appear as duplicates in the provider grid, its "Load More" results, or the case previous/next sequence; entries sharing a case ID are collapsed to a single case.

= 4.9.1 =
* Changed: The `[brag_book_carousel]` and `[brag_book_gallery_procedures]` shortcodes now use a `provider_id` attribute (e.g. `[brag_book_carousel provider_id="43"]`); the former `member_id` attribute has been removed. Cases are matched through the provider taxonomy assigned to each case, so a provider returns every case they appear on, whether primary or secondary.
* Fixed: Provider filtering on the `[brag_book_carousel]` shortcode, which previously matched an unused meta key and returned no results.

= 4.9.0 =
* Added: `provider_id` attribute for the `[brag_book_gallery_cases]` shortcode, so a single provider's cases can be embedded directly (e.g. `[brag_book_gallery_cases provider_id="123"]`). Matches the provider taxonomy term's synced API ID and caps results at 99 cases.
* Added: A search box at the top of the provider filter dropdown so a long provider list can be narrowed by typing instead of scrolling.
* Changed: The provider filter dropdown now lists providers in alphabetical order instead of by synced position.

= 4.9.0-beta1 =
* Added: `provider_id` attribute for the `[brag_book_gallery_cases]` shortcode, so a single provider's cases can be embedded directly (e.g. `[brag_book_gallery_cases provider_id="123"]`). Matches the provider taxonomy term's synced API ID and caps results at 99 cases.
* Added: A search box at the top of the provider filter dropdown so a long provider list can be narrowed by typing instead of scrolling.
* Changed: The provider filter dropdown now lists providers in alphabetical order instead of by synced position.

= 4.8.0 =
* Added: Provider (doctor) dropdown filter, shown before the gallery filters and styled to match them. Each option shows the provider's avatar and name; selecting one replaces the case grid with that provider's cases. On a procedure view the results are scoped to that procedure. Includes an "All Providers" option and a Reset button to restore the unfiltered grid, and the toggle shows the selected provider's avatar.
* Added: Provider images are now downloaded into the WordPress media library during sync, named after the provider slug, and set as the provider's Profile Photo. The downloaded image is tracked and removed from WordPress when the provider term is deleted. Re-syncs skip unchanged images, manually-chosen photos are preserved, and the remote URL is kept as a fallback if a download fails.
* Fixed: The sync UI no longer always says "three stages". The Full Sync tooltip, confirmation dialog, and help text now reflect whether the run has three or four stages (Stage 4, Providers & Practices, only runs when both features are enabled).
* Changed: Provider term editor wording — the synced photo is now described as downloaded into the media library rather than a remote API URL.

= 4.7.1 =
* Fixed: Location search on a procedure page now returns only that procedure's cases. The shared tiles filter bar rendered the search without the procedure context, so the distance search matched cases across every procedure (e.g. "74 cases" on a procedure that has only a handful). The current procedure is now passed through and results are scoped to it.
* Fixed: Location search result cards now follow the configured case card design (default, v2, or v3) so they match the rest of the gallery. They previously used an older card renderer that ignored the setting.
* Changed: Each location search result card now shows how far the case is from the searched location (e.g. "3.4 miles away") as a badge on the image.
* Changed: The location search is no longer shown on the main gallery landing view, which has no procedure to scope results to.
* Changed: The "Showing N cases within R miles of …" results banner now appears below the procedure title instead of above it.

= 4.7.0 =
* Fixed: The Banner Image and Profile Photo buttons on the procedure and provider taxonomy screens did not open the WordPress media library. The enqueued admin assets (taxonomies-media.js and taxonomies.css) were missing and returned 404s, so the button click handler never loaded.
* Changed: Tightened the nudity warning overlay spacing so the title, caption, and acknowledge button sit closer together, and simplified the compact short-height layout.

= 4.6.0-beta11 =
* Added: Inline location search before the gallery filter dropdown — type an address, city, or ZIP (Google Places autocomplete) or use your current location to find cases near you. Shown only when a Google Maps API key is configured and Maps loads.
* Changed: Selecting a location filters the case grid to providers whose associated practice is within 50 miles (widening to 100 miles when none are closer) and orders the results nearest-first. A summary ("Showing N cases within R miles of …") spans the top of the gallery above the title.
* Removed: The previous "Find a Provider" map locator (button, modal, and embedded Google Map) has been replaced by the inline location search.
* Changed: Sync now applies the manifest/terms order to child procedures, not just parent categories, so child procedures match the BRAGBook ordering after a sync.
* Changed: The gallery column view defaults to 2 and follows the configured Columns setting for the active view, while still remembering a visitor's manual choice across reloads.
* Changed: The Google Maps API Key field on the General settings page is now a password input with a show/hide toggle.
* Changed: The image processing disclaimer text is now 14px with spacing above it so it is not crowded against the case grid.

= 4.6.0-beta10 =
* Changed: "Find a Provider" search now uses the Places API (New) — live address suggestions as you type (ZIP/city) plus text search — removing the deprecated legacy Places/Geocoder dependency.
* Added: Practices that are missing coordinates are geocoded by address on the fly so they still appear within the selected radius.
* Added: The "Find a Provider" results are now procedure-aware — on a procedure view the dialog is titled "Find a Provider for {Procedure}" and lists only practices whose providers have cases for that procedure.
* Fixed: The "Find a Provider" button now opens the dialog on case and landing views (the dialog markup was missing from those views' shared bundle).
* Fixed: The "use my location" button now reports geolocation errors and notes that it requires a secure (https) connection, instead of failing silently.
* Changed: "Find a Provider" dialog polish — 48px aligned controls, black numbered result circles, header padding, zero-margin buttons, and a custom suggestions dropdown that renders above the modal.
* Changed: Provider profiles on the case detail now render as an inline list with 40x40 avatars (previously stacked), and the case-card overlay provider avatar is fixed at 40x40.
* Changed: On the procedure tiles view, the image processing disclaimer (when enabled) now displays after the case grid.

= 4.6.0-beta9 =
* Changed: The "Find a Provider" dialog is now 80% of the viewport height and at least 1280px wide on desktop, and goes full-screen on mobile. Padding was removed from the header and inner content so the map fills the dialog edge-to-edge.
* Added: A Reset button in the search bar that clears the search, your-location pin, and radius filter and restores the full list.
* Changed: Result cards are now equal size and numbered by distance (1–10, nearest first), with the list capped at the ten closest practices.
* Added: The map now shows a numbered pin for each result that matches its card number; clicking a pin opens an info window with the practice name, address, phone, website, and providers, and highlights the matching card.
* Changed: The "use my location" target icon is vertically centered within the search input, and the radius selector uses a custom SVG arrow.

= 4.6.0-beta8 =
* Changed: Sync Stage 4 now actively looks up each provider's practices (by provider id, via `/api/plugin/v2/practices`) and creates the practice posts with their post meta — previously it only reported counts. It runs as a visible step within Stage 3 and Full Sync, and the sync highlights Stage 4 on completion when Providers and Practices are enabled (otherwise it ends on Stage 3).
* Added: A `provider_id` term meta on provider terms (the API providers[].id, used as the providerID for the practices lookup), shown below Member ID in the provider editor.
* Changed: The "Find a Provider" button now appears in the tiles/alternative view's filter bar, between the Favorites and Request Consultation buttons.
* Changed: The "Display image processing disclaimer" option now defaults to off.
* Fixed: Testing the `/api/plugin/v2/practices` endpoint on the Debug page no longer returns "Unsupported endpoint" (a duplicate legacy API-test handler was missing the case).
* Fixed: The sync progress bar now hides after Stage 4 finishes when run on its own.

= 4.6.0-beta7 =
* Fixed: The "Find a Provider" locator script is now bundled into the release build. In 4.6.0-beta6 the build's clean step removed the hand-written script, so the locator dialog did nothing; it is now a proper build entry and ships correctly. Contains all 4.6.0-beta6 changes.

= 4.6.0-beta6 =
* Added: Practices are now synced as a new internal `brag_book_practices` custom post type, associated with providers. During sync each provider's practices are fetched from `/api/plugin/v2/practices` (by provider id) and upserted, with name, address, geo coordinates, phone, website, on-site surgical-suite flag, and accreditations stored as editable post meta on the practice (populated from the API, adjustable in the admin between syncs). Each practice is linked to its providers through the `brag_book_providers` taxonomy — the provider term (which carries the provider id) is assigned to the practice post — so providers connect to both cases and practices. Practices are an internal data feed (not publicly queryable); orphaned practices are pruned on sync like other synced records.
* Added: "Enable Providers" and "Enable Practices" toggles on the General settings page (both off by default). Enable Providers gates the providers taxonomy and provider syncing; Enable Practices gates the practices sync and requires Providers to be enabled.
* Added: The Cases list table shows a mini provider avatar next to each provider in the Providers column (API image, then a manually-uploaded photo, then a placeholder).
* Added: Provider images are now captured during sync. The provider `imageUrl` from the `/api/plugin/v2/practices` response is saved to the provider term so it appears on the provider, the cases list, and the front end.
* Added: "Find a Provider" store-locator modal (shown when Providers and Practices are enabled). It lists practices with their providers and plots them on a Google map, with a ZIP/city lookup, a "use my location" target icon, and a radius selector (5/10/25/50/100 miles) that filters results by distance. Adds a Google Maps API Key field on the General settings page (required for the map; needs the Maps JavaScript and Geocoding APIs).
* Added: Sync — a Stage 4: Providers & Practices step (shown when both features are enabled) that reports, and highlights, how many providers and practices the sync holds. It runs automatically after Stage 3 / Full Sync.
* Fixed: The gallery 2/3-column view buttons now reflect the saved Columns setting on load. Previously the JavaScript hardcoded the 3-column button as active and defaulted the grid to 3 columns, ignoring the setting (a saved per-visitor preference still wins).
* Added: API test on the Debug page now includes the `/api/plugin/v2/practices` endpoint (with a Provider ID input); removed the retired `/api/plugin/combine/cases`, `/api/plugin/combine/filters`, `/api/plugin/sitemap`, and `/api/plugin/combine/cases/{id}` tests.

= 4.6.0-beta5 =
* Changed: Renamed the "Doctors" taxonomy to "Providers" (registered as `brag_book_providers`) throughout the plugin — admin menu, case displays, term/post meta, the display toggle, and sync. The terminology is more universal for related medical staff. Existing synced data (provider terms, their photos and meta, case associations, and sync-registry rows) is migrated automatically on upgrade, so no re-sync is required.
* Changed: The provider taxonomy is now available to every account. Previously it was restricted to a single website property ID.
* Changed: Provider sync now reads the v2 `providers` array (a case can have multiple providers) instead of the deprecated single `creator` object. Each provider is stored as a term with its API ID (`provider_member_id`, for reuse against /v2/practices), name, bio, image URL, and position. The case stores the ordered provider ID list in `brag_book_gallery_provider_ids`. All assigned providers render on the case detail and cards, ordered by the API position, with the API-supplied photo preferred over a manual upload.
* Added: `featured` and `topPerforming` from the API are mapped to the `brag_book_gallery_featured` and `brag_book_gallery_top_performing` case post meta during sync.

= 4.6.0-beta4 =
* Fixed: On short case overlays the nudity warning's title, caption, and button no longer stack and overlap unreadably. The content now switches to a side-by-side layout when the overlay is too short — implemented with a height-based CSS container query — and reverts to the stacked layout once there is enough vertical room.

= 4.6.0-beta3 =
* Fixed: Yoast SEO and Rank Math no longer emit bogus `rel="next"` / `rel="prev"` pagination links (e.g. `/gallery/breast-augmentation/page/2/`) on gallery pages, procedure archives, and single-case views. The gallery renders its full result set on one page via JS/AJAX, so those paginated URLs do not exist; suppression is scoped to BRAG book gallery contexts only via each plugin's documented disable filter.
* Fixed: Uninstalling the plugin now removes its custom permalink rewrite rules (the gallery-slug post type and taxonomy rules, the `/myfavorites` endpoint, and the sitemap rule) by clearing the `rewrite_rules` option so WordPress rebuilds cleanly. Previously these stale rules remained after deletion and caused sitewide 404 errors.
* Added: New "Powered by BRAG book Gallery" display option in Display & Gallery Settings. Disabled by default; when enabled, an attribution link is shown in the gallery sidebar.
* Added: "Featured" and "Top Performing" true/false toggles on case posts (API Case Data → Basic Info). Both save through the `case_api_data` nonce-guarded handler, so API syncs do not reset them.
* Changed: Removed default padding on the shared gallery button base so button sizing is driven explicitly by each variant.

= 4.6.0-beta2 =
* Performance: Carousel LCP fix — first slide now renders with `loading="eager"` and `fetchpriority="high"`; other slides remain lazy. Closes Lighthouse's "LCP request discovery" gap on homepage hero carousels.
* Performance: Shipped `assets/.htaccess` that sets `Cache-Control: public, max-age=31536000, immutable` on every static asset under the plugin's `assets/` directory. Filenames are version-busted, so 1-year immutable is safe. Resolves Lighthouse's "Use efficient cache lifetimes" diagnostic that previously flagged plugin assets at the host's default 12-hour TTL.
* Performance: Same `.htaccess` allows `Access-Control-Allow-Origin: *` on font files so WOFF2 preloads work when assets are proxied through a CDN subdomain.

= 4.6.0-beta1 =
* Performance: Production now serves minified CSS/JS through every shortcode handler — previously the unminified bundle was served, wasting ~265 KB per gallery page.
* Performance: Frontend asset handles consolidated onto `brag-book-gallery-main` so multi-shortcode pages no longer double-load the same CSS/JS file.
* Performance: JS code-splitting via dynamic `import()` — `FilterSystem`, `FavoritesManager`, `SearchAutocomplete`, and `ShareManager` are now lazy chunks that load only when their anchor element is on the page. Main bundle dropped from 190 KB to 133 KB.
* Performance: New `brag-book-gallery-carousel.min.js` (~11 KB) ships only the carousel + utilities; the carousel shortcode handler picks this bundle when no other BRAGbook shortcode is on the page (homepage hero use case).
* Performance: Main bundle now `defer`-loaded, keeping it off the critical path.
* Performance: Gallery pages now emit `<link rel="preload">` for the minified CSS plus Poppins-Regular and Lato-Regular WOFF2 fonts, plus `preconnect`/`dns-prefetch` to the BRAGbook API origin.
* Performance: Image CLS fixed via `aspect-ratio: 16 / 10` on `.brag-book-gallery-image-container` and `object-fit: cover` on carousel slide images.
* Performance: All `<img>` rendered by shortcode handlers now carry `decoding="async"`; the case-detail hero image picks up `fetchpriority="high"` for LCP.
* Performance: Removed the dead `localize_frontend_data()` blob (115 lines, 8 unreferenced SVG icon URLs) and stopped duplicating the `completeDataset` payload across two inline scripts.
* Changed: Replaced wasteful single-source `<picture>` wrapper around carousel slide images with a flat `<img>` (the source URL was identical to the img URL).
* Changed: `Asset_Manager::localize_gallery_script()` no longer accepts `$all_cases_data`; the dataset belongs to the inline `brag-book-gallery-dataset` script.
* Build: Unminified bundle artifacts and expanded CSS are now excluded from the dist `.zip` via `.distignore` — production only ships `*.min.{js,css}`.

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

= 4.6.0-beta4 =
Fixes the nudity warning overlapping itself on short case overlays by switching to a side-by-side layout when vertical space is tight.

= 4.6.0-beta3 =
Adds Featured / Top Performing case toggles and an optional "Powered by" link, fixes bogus SEO rel=next/prev pagination links on gallery views, and removes stale rewrite rules on uninstall to prevent sitewide 404s.

= 4.6.0-beta2 =
Builds on 4.6.0-beta1 with two Lighthouse fixes: the first carousel slide is now flagged as the LCP candidate (eager + fetchpriority="high"), and a shipped `assets/.htaccess` extends static-asset cache lifetimes from 12h to 1 year (immutable, safe because filenames are version-busted).

= 4.6.0-beta1 =
Beta release focused on PageSpeed: minified bundles in production, deferred main script, lazy-loaded JS chunks, a new carousel-only bundle for homepage hero use, font/CSS preloads, API-origin preconnect, and image CLS fixes. Main JS bundle drops from 190 KB to 133 KB; carousel-only pages drop to ~11 KB. Test against your shortcodes before promoting to production.

= 4.5.1 =
Fixes signed-URL corruption (Supabase / S3 `InvalidSignature` errors) when saving cases or editing SEO meta descriptions, restores missing slides on cases that have a partial high-resolution image set, and adds managed meta descriptions for cases, procedure archives, and the main gallery page across all major SEO plugins. Re-sync any cases whose images were already corrupted by earlier saves.

= 4.5.0 =
Security and naming-convention release. Internal post type renamed; existing form entries are migrated automatically. Recommended upgrade for all users.

= 4.4.6 =
Fixes favorites display issues and sync procedure ordering for child procedures.
