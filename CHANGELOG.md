# Changelog

All notable changes to the BRAGBook Gallery plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.9.3-beta11] - 2026-08-19 (Beta Release)

### Added

- **Settings that depend on another setting now appear only when they apply**:
  the carousel navigation choice under the carousel toggle, the consultation
  form choice under consultation and the GoHighLevel fields under that choice,
  provider naming and Practices under Providers, and the Google Maps key under
  Practices. Turning Providers off takes the whole branch with it. A hidden
  field keeps posting its saved value, so nothing you have already entered is
  lost while it is out of sight.
- **The settings rail lists the sections on the open tab** and marks the one you
  are reading, so a long tab can be moved around rather than scrolled through.
- **The nudity warning has a preview beside its wording**: a drawn stand-in that
  follows what you type, falls back to the defaults when a field is blank, and
  changes shape with the preset you pick. It draws a plain tone under the
  warning rather than loading a case photo into the settings screen.

### Changed

- **The General settings page is laid out in cards**: each group sits in its own
  card with a titled bar, so where a group starts and ends is unmistakable.
- **The switches, checkboxes and radio choices are redrawn**: on is the plugin's
  own red, focus is blue, and off is grey, consistently across all three. The
  words beside a switch now flip it, so the whole row is clickable rather than
  the switch alone, and a radio choice is a row you click rather than a dot you
  have to hit.
- **Inputs and dropdowns fill their field** instead of stopping halfway across
  the card, dropdowns are drawn to match the text fields, and a dropdown with a
  preview button beside it now reads as one control.
- **The settings menu no longer slides under the page tabs** when you scroll,
  and the Save bar stays with you down a long tab.

### Fixed

- **A case card with four or more carousel thumbnails no longer widens its
  column**: the thumbnail strip already scrolled, but the grid column was free
  to grow to fit every thumbnail, so a row of cards came out at different widths
  and the strip never had to scroll. Columns now hold one width and the
  thumbnails scroll within the card.
- **The view-type settings were quartered in width** by a grid that had been
  nested inside itself, and the radio choices in the nudity and consultation
  settings sat flush against each other, both from rules that compiled to
  selectors which could never match.

## [4.9.3-beta10] - 2026-08-19 (Beta Release)

### Changed

- **The General settings page is grouped into named sections**: every tab held a
  flat run of controls in the order they were written, so Gallery Columns sat
  between an image disclaimer and the plugin update channel, and the Favorites
  toggle was nowhere near the Favorites page setting. Display & Gallery is now
  eight titled groups - Gallery Page, Layout & Views, Case Cards, Navigation &
  Filters, Visitor Features, Providers & Locations, Nudity Warning and Plugin
  Updates - with the update channel and API environment set apart at the end as
  the maintenance settings they are. SEO, Performance and Custom CSS use the
  same grouping, and the SEO search preview now sits with the title and
  description it previews instead of below the schema settings. Every field
  stays in the same form it was in, so saving behaves exactly as before.
- **The Save button follows you down a long tab**: it waited at the bottom of a
  page that scrolls for screens. It now stays at the foot of the panel while you
  work. The settings rail does the same on wide screens, and lies down as a
  scrolling strip of tabs under 1024px.
- **The settings rail and its panels announce themselves**: the tabs are marked
  up as tabs, each panel points back at the tab that opens it, and the selected
  tab reports itself as selected, so a keyboard or screen reader can tell which
  panel is showing.
- **A provider's initials are punctuated, and their name links to their
  profile**: a provider synced as "John A Smith" now reads "John A. Smith"
  wherever the name appears - case detail, case cards, the providers grid, the
  filter dropdown and both archive headings - while the stored term name is left
  alone so slugs and the sync's name matching keep working. Themes that print the
  archive title themselves, block themes included, are covered too. The name now
  links to the provider's profile URL when the term has one, falling back to
  their gallery archive when it does not.

### Fixed

- **A case's next and previous buttons no longer point outside the provider you
  came from**: the server draws those buttons from the case's primary procedure,
  so a visitor who arrived from a provider kept arrows leading to cases that
  provider does not have - most visibly on a provider holding a single case,
  where both arrows stayed with nowhere to go. The adjacent-case lookup is now
  the authority in both directions, and a direction it has nothing for loses its
  button. A failed request says nothing about scope, so it leaves the server's
  links alone.
- **Settings controls no longer style themselves in place**: nine inline styles,
  a private method that returned CSS as a string, and JavaScript that wrote
  colours onto the character counters have all moved into the stylesheet, where
  the admin palette and spacing scale own them. Selecting a column count now
  marks itself in the plugin's own red rather than a stray blue, and an
  explanation under a radio choice lines up with the choice instead of the
  toggle above it.

## [4.9.3-beta9] - 2026-08-18 (Beta Release)

### Fixed

- **A carousel thumbnail shows the whole photo**: three things stood in the way.
  Two blanket rules on the card's image container set `height: auto` on every
  image inside the card, thumbnails included, so each took its photo's own shape
  and a portrait one stood taller than the rest of the strip. The 2px active
  border came out of the content box, leaving a transparent frame on all four
  sides. And the fixed width forced a square crop of a photo that is rarely
  square. The two rules now reach the case photo alone, the active marker is an
  inset outline that paints over the photo rather than taking room from it, and
  the button is sized by height alone and takes its width from the photo. The
  overlay's provider avatar, whose size carried `!important` purely to fight the
  same rules, no longer needs it.

## [4.9.3-beta8] - 2026-08-18 (Beta Release)

### Changed

- **Only the provider stays bold in a provider page's heading**: the procedure
  chosen in the filter was written into the same emphasis as the provider, so
  both read as the page's subject. It now sits in its own element between the
  provider and "Before & After Gallery". The heading reads the same; only what
  is bold has changed.

## [4.9.3-beta7] - 2026-08-18 (Beta Release)

### Added

- **A provider page's heading names the procedure chosen in its filter**: the
  heading names the provider, and kept naming only the provider once the
  procedure dropdown had narrowed the cases beneath it, so the page said less
  than it was showing. Choosing a procedure now reads "Provider - Procedure
  Before & After Gallery", and Reset or All Procedures puts the provider's name
  back. Only the procedure dropdown does this: choosing a provider on a
  procedure page leaves the heading alone, since the procedure is still what
  that page is.

## [4.9.3-beta6] - 2026-08-18 (Beta Release)

### Added

- **A provider can be named on the gallery shortcode**: a provider's gallery
  only existed at its taxonomy archive, so a practice wanting one on a page of
  its own had nothing to place there. `[brag_book_gallery provider="dr-jane-smith"]`
  takes the term slug and `[brag_book_gallery provider_id="43"]` the API ID that
  the other provider attributes take. Either resolves to the same context the
  archive builds, so the page gets the archive's structure whole: the procedures
  and filters dropdowns, the provider-scoped grid and its Load More, and the
  provider carried on the wrapper that keeps a case's next and previous links
  inside that provider. Resolution runs after the single-case checks, leaving a
  case URL to open its case.
- **A practice can name what a provider is called**: Provider read fine until
  practices turned up who keep doctors and providers apart. Two fields under
  Enable Providers hold the practice's own wording, singular and plural, and the
  provider filter takes every word it shows from them — the toggle and its
  default label, the search box, the listbox, the All option, the no-match line
  and the empty result the script writes. The plural is stored separately rather
  than derived, because the plural of a label like "Doctor or Provider" is not
  the singular with an s on the end. Both fall back to Provider and Providers
  when left blank.

### Fixed

- **The consultation form height field blocked every save**: it rendered a
  stored 0 as `value="0"` while carrying `min="200"`, so the browser refused the
  form even when the built-in form was selected and the height meant nothing. 0
  stands for "use the default", so it renders blank, which the min attribute
  leaves alone. The range is only advertised while GoHighLevel is the saved
  source, and saving still clamps a given height to 200-2000.
- **Rebuilt stylesheets and scripts reached nobody**: the frontend and admin
  enqueues carried a hand-written version constant left at 4.6.0-beta4, so every
  rebuild since shipped under the same query string and browsers kept serving
  the file they already had — the V2 thumbnail-strip fix among them. They now
  version by the built file's own modified time, through the resolver the
  shortcode enqueues already used.
- The offset that stops the V2 hover panel covering the thumbnail strip no
  longer applies to V3 cards, which keep their overlay in flow beneath the image
  where there is nothing to clear.

## [4.9.3-beta5] - 2026-08-17 (Beta Release)

### Added

- **A GoHighLevel form can replace the built-in consultation form**: a
  Consultation form choice under the consultation toggle switches between the
  two, with fields for the form's address and the height it opens at. The embed
  only stands in once an address is given, so choosing GoHighLevel and leaving
  the field empty keeps the built-in form serving requests rather than opening an
  empty dialog. GoHighLevel's embed script is enqueued alongside it, which is
  what resizes the frame; the setting decides the room it takes until that runs.
  All three dialogs render through one method, so the two forms cannot drift
  apart. Requests through the embed go straight to GoHighLevel: the plugin
  neither stores nor emails them.
- **Case counts in the provider archive's procedure filter**: each procedure
  carries the number of that provider's cases in it, tallied from the case/term
  pairings for the provider's own cases rather than read from the term, whose
  count covers the whole library and would promise cases the filter cannot show.

### Changed

- **The gallery filter bar is hidden on a case opened from a provider**: it
  offers a gallery picker and filters for the whole library, which is a different
  journey from the one the visitor is on. A case reached from a procedure, or
  directly, keeps it.

### Fixed

- **The V2 hover panel covered the thumbnail strip**: the panel fills the image
  container and pins the provider and arrow to its bottom edge, which landed
  behind the strip. It now ends where the strip begins, and the strip stacks
  above it so thumbnails stay clickable while the panel shows.
- Thumbnails centre in their strip rather than starting hard left, with `safe`
  centring so the first stays reachable once the strip scrolls.

## [4.9.3-beta4] - 2026-08-14 (Beta Release)

### Added

- **Carousel navigation choice on V2/V3 cards**: the image carousel could only
  be navigated by the dots over the image, which say how many photos there are
  but not what they show. A Carousel navigation setting, nested under the
  carousel toggle it depends on and greyed out while that toggle is off, now
  offers a thumbnail strip instead. The strip renders between the image and the
  card's title bar with an arrow either side: arrows step from wherever the
  carousel sits, a thumbnail selects its slide, and the strip scrolls rather
  than shrinking when a case has more photos than fit. Thumbnails take the small
  rendition through the shared variant lookup rather than the full-size file,
  and a hairline divides the strip from the title beneath it.

### Fixed

- **The delete-all dialog clipped its buttons**: the shared dialog is capped at
  24rem, sized for a sentence and two buttons, while this one carries a list, a
  confirmation field, a progress bar and up to three buttons, so the footer ran
  past the edge and the last button was cut off by the content's overflow. It
  now sizes to 34rem with a wrapping footer. The warning glyph beside the title
  and the trash glyph beside the message are gone, along with the script that
  swapped that glyph as the delete ran.

### Changed

- The radio-and-description layout the nudity presets use is shared under one
  class rather than copied, now that a second setting uses it.

## [4.9.3-beta3] - 2026-08-14 (Beta Release)

### Changed

- **The referrer records the provider's member id alongside the slug**: only the
  provider-scoped procedures grid ever rendered an id for the script to read, so
  `provider-id` was always null. The slug is what the adjacency endpoint
  prefers, so navigation worked, but nothing was left to fall back on if a slug
  stopped resolving to a term. Both provider archive layouts and every provider
  dropdown option now carry `data-provider-id`, and the script reads whichever it
  finds. The id comes from a single `Taxonomies::provider_member_id()` resolver
  (`provider_id`, falling back to `provider_member_id`) that the queried-archive
  lookup now shares rather than repeating.

## [4.9.3-beta2] - 2026-08-14 (Beta Release)

### Fixed

- **The referrer capture missed a whole card style**: the click handler that
  records which gallery a case was opened from matched only
  `.brag-book-gallery-case-card-link` and `.brag-book-gallery-case-permalink`.
  The permalink is emitted on default cards and on v2/v3 cards with a carousel,
  but a single-image v3 card carries only
  `.brag-book-gallery-case-card-overlay-button`, and the whole-card fallback
  checks for the same two classes before it acts. On those cards nothing was
  recorded, so the case page kept its server-rendered procedure links and
  provider navigation appeared broken while working fine on sites whose cases
  have carousels. All three anchors now come from one selector.

## [4.9.3-beta1] - 2026-08-14 (Beta Release)

### Fixed

- **Provider slugs came back with the member id after a sync**: 4.9.2 added a
  name-only slug builder but wired it into one of the six places that create or
  rename a provider term — and not the chunked sync, which is the path a full
  sync runs. Every sync rebuilt `{name}-{member id}` however many times the
  migration had shortened it. The builder moved to `Taxonomies` and all six call
  sites use it. The migration also matched a slug against the whole old
  `{name}-{member id}` form, so a provider renamed since their term was created
  kept the id; it now shortens any slug ending in `-{member id}` and re-runs on
  sites where it has already run.
- **The placeholder term description is gone**: every provider term was created
  with "Provider profile for provider ID 4" as its description, which the
  provider's page renders under the heading. The syncs no longer write it and
  the clean-up clears existing ones, matched on the exact generated wording so a
  description written by hand survives.

## [4.9.2] - 2026-08-13 (Stable Release)

Stable release of the 4.9.2 line. The beta entries below carry the detail; this
is what changed since 4.9.1, plus the provider work that landed after beta10.

### Added

- **Provider pages**: the providers taxonomy is public, with a term archive at
  `/providers/{provider}/` rendering through the gallery and following the
  Procedures View setting. A `/providers/` page lists every provider with their
  profile photo and case count, built from the new
  `[brag_book_gallery_providers]` shortcode and created once while the providers
  feature is on. A provider's name on a case links to their page, preferring it
  over the API profile URL.
- **A procedure filter on provider archives**, in place of the gallery picker,
  scoping a provider's grid to one procedure.
- **Context-aware previous/next on cases**: arriving from a provider page or
  filter walks that provider's cases and wraps at both ends; arriving from a
  procedure keeps procedure navigation.
- **Responsive image variants** across the grid, carousels, favourites, the case
  detail viewer and related cases, with narrow screens pointed directly at the
  smaller renditions.
- **Configurable nudity warning**: title, body, button label, decline link and
  destination, three presets, and a reset control.
- **`randomize="true"`** on `[brag_book_gallery_procedures]`, seeded per page
  load so Load More continues the same shuffled order.

### Changed

- **Provider slugs drop the API member id**: `/providers/dr-mi-payne/` rather
  than `/providers/dr-mi-payne-8/`. Terms synced earlier are renamed once, with
  hand-edited and colliding slugs left alone. Old URLs are not redirected.
- **Load More paginates within the active view** — provider, location and
  procedure — through the same ordered result set as the initial render.
- **Delete All Synced Data** runs in batches with progress reporting.
- Roughly 1,850 lines of unreachable frontend JavaScript removed.

### Fixed

- Carousel touch scrolling, duplicate cases across procedure categories, the
  single-letter SEO title and meta description, and the provider page issues
  found through the beta line: the 404, the displaced first card, the stray
  block-theme paragraphs, and navigation that ignored the provider on SEO-slugged
  sites or on any layout other than tiles.

### Security

- The API token is no longer localised into the page source, and rate limiting
  works again on the consultation form, the favorites email lookup and the API
  client. Rotate the token if the site has been public.

## [4.9.2-beta10] - 2026-08-13 (Beta Release)

### Fixed

- **Provider navigation only worked on the tiles layout**: a provider archive
  renders through `provider_tiles` or `taxonomy_provider` depending on the
  Procedures View setting, and only the tiles path emitted
  `data-provider-slug`, which is what tells a case link it is on a provider
  page. On every other setting the capture fell back to reading the second path
  segment as a procedure — the provider's own slug — so the case page navigated
  by procedure. The sidebar layout's gallery wrapper now carries the provider
  too, and the script reads it from either.
- **`[brag_book_gallery_procedures provider_id="…"]` did not pass its provider
  on**: the script looked for `data-provider-id` on the procedures wrapper, but
  nothing ever rendered the attribute, so navigation from one of those grids
  fell back to the procedure. The wrapper now carries it.
- Card capture no longer requires a procedure term whenever any provider
  context is present — archive, provider dropdown or provider-scoped grid —
  rather than only on a provider archive.

## [4.9.2-beta9] - 2026-08-12 (Beta Release)

### Fixed

- **Referrer-based previous/next navigation never ran on SEO-slugged sites**:
  the case page identified itself by a path ending in digits, but a case slug
  comes from the API's `seoInfo.slug` whenever the account supplies one and only
  falls back to the case id when it does not. On those sites the referrer was
  written when a card was clicked and then never read, so the arrows kept the
  server-rendered procedure links — procedure combos and the new provider
  navigation alike. The page is now recognised by the
  `.brag-book-gallery-case-detail-view` both case views render; the digit match
  remains as a fallback.

## [4.9.2-beta8] - 2026-08-12 (Beta Release)

### Added

- **The nudity warning can be declined**: the full-screen Global warning now
  renders a decline link beside Proceed, sending the visitor to a configurable
  URL that defaults to the site home page. The link text and destination are set
  on the General settings page, and the link is Global-only — the per-card
  overlays cover one case, not the page, so navigating the whole browser away
  from one of them would not match what was clicked.
- **Reset Nudity Settings**: a button on the same settings section clears the
  preset and every copy field, restoring the plugin defaults.
- **Procedure filter on provider archives**: the "Choose a Gallery" picker is
  hidden there — it navigates away to another procedure and has nothing to do
  with the provider being viewed — and a procedure filter takes its place,
  listing the procedures that provider has cases in and narrowing the grid to
  one of them. It reuses the provider filter's component, so it is organised
  like the gallery picker and styled like the Filters dropdown.
- **Case navigation follows the provider**: arriving at a case from a provider
  page walks the previous/next arrows through that provider's cases rather than
  the procedure's, and wraps, so the last case leads back to the first. The
  procedure filter's selection narrows it further when one is active. Arriving
  from a procedure page is unchanged.
- **`randomize` attribute on `[brag_book_gallery_procedures]`**: `randomize="true"`
  shuffles the cases on every page load instead of using the curated order. The
  shuffle is seeded per page load and the seed travels with the Load More
  button, so later pages continue the same shuffled list instead of re-drawing
  and repeating or skipping cases. Location searches keep their nearest-first
  ordering.

### Fixed

- **The provider archive grid dropped its first card**: `do_shortcode()` runs
  over a block template's raw markup before `do_blocks()`, so the shortcode
  block held the expanded gallery HTML by the time it ran `wpautop()` over it,
  which closed a paragraph at the first blank line and left an empty `<p>` in
  the case grid. That paragraph took the first grid cell, pushing every card one
  cell along with its margins showing as extra space. The template now uses a
  block that passes its content through untouched.

## [4.9.2-beta7] - 2026-08-07 (Beta Release)

### Added

- **Providers have their own front-end pages**: the providers taxonomy is now
  public, with a term archive at `/providers/{provider}/` that renders through
  the gallery and follows the Procedures View setting — same structure, card
  type, columns, page size and load-more behaviour as a procedure page, scoped
  to that provider. Both a classic PHP template and a block template ship with
  it. The location search and provider dropdown are omitted there.
- **Nudity warning copy is configurable**: a Nudity Warning Settings section on
  the General settings page sets the title, body text and button label, plus a
  preset that decides how the warning applies — Global (one full-screen warning
  per page, only when flagged content rendered), Default (the existing
  procedure-based overlay) or Individualized (the per-case flag alone). The
  per-case flag now maps from the v2 `photoSets` `isNude` value across all photo
  sets and is editable on the case editor.

### Fixed

- **Carousels swipe naturally on phones and tablets again**: the touch handler
  replaced the browser's own scrolling with a frame-by-frame position update, so
  a swipe moved the track only as far as the finger travelled and stopped dead
  on release, with no momentum or snap. Touch and pen now scroll natively; the
  grab-and-drag behaviour and its cursor stay on mouse-driven devices only.
- **Provider pages no longer 404**: the term archive had been registered under
  the gallery slug, where the case rewrite rule matched it as a procedure-plus-
  case pair.
- **The global nudity warning renders with its own styling**: it prints in
  `wp_footer`, outside the wrapper declaring the plugin's custom properties, so
  every value in it resolved to nothing and the button lost its background.
- **Block themes no longer show stray paragraphs inside gallery cards**: markup
  comments were run back through `wpautop` by the shortcode block and wrapped in
  empty paragraphs.
- The anti-flash preload script and the frontend wrote different localStorage
  keys, so an accepted nudity warning was never remembered on the next page load.

## [4.9.2-beta6] - 2026-08-05 (Beta Release)

### Security

- **The API token is no longer printed into the page source**: the gallery,
  carousel and favorites shortcodes localised it into `bragBookGalleryConfig`
  and `bragBookCarouselConfig`, both of which are emitted for anonymous
  visitors. Nothing consumed it — the only reader had no callers — so both
  payloads are gone rather than reworked. Rotate the token if the site has been
  public.
- **Rate limiting works again**: `Cache_Manager` had been removed from the tree
  and every call site commented out, so `check_rate_limits()` read a hardcoded
  zero and never wrote a counter. The consultation form, the favorites email
  lookup and the API client were all unthrottled. Replaced with transient-backed
  per-IP counters.
- **The favorites email lookup is throttled**: it returns a patient's name and
  phone for any address that exists, so unthrottled it was an enumeration
  oracle. Capped at ten attempts per IP per hour, and it no longer returns the
  upstream exception message to the browser.
- **Every error path in Communications was fatal**: the file imported `WP_Error`
  from the plugin namespace, where no such class exists, so invalid input, spam
  matches and bad request methods threw `Class not found` instead of returning a
  message.
- **The sync REST routes prefer a header over a query parameter**: a token in
  the query string is written to access logs, proxy logs and `Referer` headers.
  `X-BRAGBook-Token` is now preferred; `?token=` still works during the
  switchover.
- Removed `brag_book_test_ajax`, a debug endpoint registered for unauthenticated
  callers with no nonce and no capability check.

### Fixed

- **Carousel slides are clickable again**: slides carried a `data-slide`
  attribute, and Bootstrap's carousel data-api delegates clicks on
  `[data-slide], [data-slide-to]` and cancels them, treating anything that
  matches as one of its own controls. On any site loading Bootstrap — including
  one still running the legacy `bagallery_v5_1` plugin — clicking a slide never
  reached its case page. Renamed to `data-bb-slide`.
- **Carousel images were never marked as the LCP candidate**: the slide index
  was pre-incremented, so the first slide was index 1 while everything
  downstream treated it as zero-based. No image ever received
  `loading="eager"`, and the first slide announced itself as "Slide 2 of N".
- **Carousel snapping drifted**: snap and current-slide detection computed
  position as `index * offsetWidth`, ignoring the 16px grid gap, so both fell
  further out of true with every slide.
- `.brag-book-gallery-picture` now spans its container; the base rule only set
  `display: flex`, so it sized to its content.

### Added

- **Drag to scroll a carousel, click to open the case**: a press becomes a drag
  only once it travels 5px, so a plain click still opens the case while a
  click-and-hold sweeps the track. Momentum now derives from pointer speed over
  time and is clamped, rather than multiplying the last raw delta — a 5px nudge
  used to coast half a slide.

### Changed

- **Debug logging is gated behind `WP_DEBUG`**: 656 direct `error_log()` calls,
  370 of them in the sync, now route through `brag_book_log()`, which no-ops
  unless debugging. A single sync run was writing hundreds of lines to the
  host's error log on production sites.
- **The changelog admin page renders this file**: it previously carried its own
  hand-maintained copy of the version history in a 1,702-line method, which
  drifted from the file every release.
- Removed unreferenced classes and methods that never ran, including two whose
  bodies read undefined variables and always reported success — one of which
  called `wp_cache_flush()`, emptying the entire site's object cache.
- Collapsed seven copies of `escapeHtml` in the JavaScript into one. Four left
  quotes unescaped, which is safe in a text node but not in an attribute.

## [4.9.2-beta5] - 2026-07-29 (Beta Release)

### Fixed

- **Small and medium renditions are now actually served**: they were emitted in
  `srcset`, but `srcset` only nominates candidates — the browser picks by
  comparing candidate widths against the slot width times the device pixel
  ratio. The stored renditions measure 320px and 640px against multi-thousand
  pixel originals, so a two-column card needing ~1000 device pixels never
  reached for them. The `<picture>` wrappers now carry `<source media>`
  elements, which are decided on the media query alone, pinning the small
  rendition below 576px and the medium one below 1280px. Above that the `<img>`
  keeps its `srcset` and resolves to the full image, which is still the only
  rendition large enough for a desktop card.
- **Variant lookup no longer breaks on re-signed URLs**: nodes were matched by
  comparing whole URLs, including the JWT whose `iat` changes on every
  re-sign. A display URL and a stored `full` URL signed at different moments
  failed to match and silently dropped the entire `srcset`. Matching is now on
  host plus percent-decoded path, and the largest `srcset` candidate is emitted
  as the caller's own URL so the offered token is always the current one.
- **`sizes` accounted for the gallery shell**: the container is a flex row from
  1280px with a 382px sidebar and 32px of main padding, none of which was
  subtracted. A three-column card renders at ~329px but was declared `33vw`
  (~475px), overstating the requirement by 44%. The grid now derives `sizes`
  from the saved column count and the case viewer subtracts the shell.
- **Google Maps is no longer loaded twice**: the location search registered its
  own Maps script tag even when another plugin or theme had already loaded the
  API under a different key, corrupting the shared API so the Places component
  threw. The client now reuses an existing loader and injects its own only when
  none is present.

### Added

- **Reset to Default on the Landing Page Text editor**: restores
  `Settings_Helper::get_default_landing_page_text()` into both TinyMCE and the
  raw textarea, storing nothing until settings are saved. `get_option()` only
  falls back to a default when the row is absent, so sites that had ever saved
  landing page text could not otherwise pick up the default carousels added in
  4.9.2-beta4.

### Changed

- **Delete All Synced Data runs in batches with real progress**: the previous
  handler fetched every case ID at once and deleted them in a single request,
  exceeding `max_execution_time` on a large library and dying with no feedback
  and a half-deleted site. The routine is now counted and batched, and the
  dialog gained progress and result panes in place of `window.alert()`. A step
  reports complete when its query empties or when a full batch deleted nothing,
  so an undeletable row cannot loop forever.
- **Removed `Gallery_Handler::generate_fast_case_html()`**, which had no callers,
  along with the three orphaned `.brag-book-gallery-case-detail-fast` SCSS
  blocks it was the only consumer of.

## [4.9.2-beta4] - 2026-07-28 (Beta Release)

> Note: 4.9.2-beta3 was released without an entry in this file. Its notes are in
> `readme.txt` under `= 4.9.2-beta3 =`.

### Added

- **Responsive variants across the remaining views**: carousel slides, favourites
  cards, the case-detail main viewer and related-case cards now emit a `srcset`
  built from the stored small/medium/full variants, alongside a `sizes` value
  matched to the layout each one is rendered in. Views with no smaller renditions
  stored — cases synced before variants existed, or sizes the API has not
  generated — fall back to the plain full-size `src` as before.
- **Variant data for the client-rendered favourites grid**: the
  `brag_book_get_case_by_api_id` endpoint now returns `featured_image_srcset` and
  `featured_image_sizes` alongside `featured_image_url`, so cards built in the
  browser get the same responsive sources as the server-rendered ones. The
  variant meta is resolved server-side rather than duplicating the lookup in JS.
- **Carousels in the default landing page content**: the default landing page
  text now includes `[brag_book_carousel procedure="breast-augmentation"]` and
  `[brag_book_carousel procedure="liposuction"]`. Applies only to sites that have
  never saved their own landing page text.

### Fixed

- **Under-declared `sizes` on the cases grid**: cards were declared at `50vw`
  below 576px where the grid is a single full-width column, letting the browser
  select a rendition too small for the space and render it soft. The declared
  breakpoints now track the grid SCSS.
- **Thumbnail swap on the case detail page**: `srcset` takes precedence over
  `src`, so once the main viewer carried a `srcset`, updating only `src` on a
  thumbnail click left the previous image on screen. Each thumbnail now carries
  the `srcset` for its own image and both are swapped together.

### Changed

- **Removed ~1,850 lines of unreachable frontend JavaScript**, shrinking the
  built gallery bundles by roughly 1,700 lines. This covered card builders with
  no callers (`createCaseCard`, the `generateCaseDetailHTML` subtree,
  `initializeThumbnailNavigation`) and three chains whose only route was an AJAX
  action that is not registered anywhere in the plugin (`brag_book_api_proxy`
  twice, `brag_book_gallery_load_filtered_cases` once). Each removal was verified
  unreachable first, and the surviving Load More and filter paths were pointed
  straight at their working AJAX handlers.
- **Single source of truth for the default landing page text**: the default was
  duplicated in four places — two admin editors and two render paths — in two
  different forms. All four now read `Settings_Helper::get_default_landing_page_text()`.

### Known issues

- `loadFilteredContentViaAjax` still posts `brag_book_gallery_load_filtered_gallery`,
  which is not registered. This path is reachable from a procedure-filtered URL
  and has been failing since before this release; it was left in place
  deliberately rather than removing live code.

## [4.9.2-beta2] - 2026-07-15 (Beta Release)

### Added

- **Responsive image variants**: Small, medium and full image variants are now
  captured from the v2 cases API and stored per case
  (`brag_book_gallery_case_image_variants`, with the legacy flat URL keys kept as
  a derived compatibility layer). The gallery grid, case carousels and single-case
  view render a responsive `srcset` — grid cards favour the smaller variants, the
  case detail viewer uses the full image and its thumbnails use the small variant.
  Variants the API has not generated yet fall back to the full-size image.
- **Editable image-URL editor**: The case editor's "Image URLs" section is now an
  editable, tabbed interface with per-variant (full/medium/small) fields and
  thumbnail previews. The API Case Data meta-box tabs now function and follow the
  WordPress admin colour scheme.

### Changed

- **Context-aware Load More**: "Load More" on the main gallery and the
  `[brag_book_gallery_cases]` shortcode now paginates within the active view. A
  selected provider loads only that provider's cases, an entered location loads
  only cases within 50 (widening to 100) miles, and on a procedure page the
  current procedure is combined with either. The provider filter and location
  search paginate through the same ordered result set via a shared context pager
  instead of loading everything at once.

### Fixed

- **Provider previous/next navigation**: Provider-filtered case cards now carry
  their procedure context (`data-current-term-id`), so provider-scoped
  previous/next navigation works when arriving from the provider dropdown.

## [4.9.2-beta1] - 2026-07-14 (Beta Release)

### Added

- **Context-aware case navigation**: The previous/next buttons on a single case
  now follow the provider the visitor was browsing. Arriving from a provider
  dropdown selection or a `[brag_book_gallery_procedures provider_id="…"]` grid
  scopes prev/next to that provider's cases within the current procedure, falling
  back to provider-wide when no procedure context is present. Navigating from a
  view without a provider filter resets to the default procedure navigation.

### Changed

- **Category-scoped Load More**: "Load More" in the `[brag_book_gallery_procedures]`
  grid now respects the active procedure category on a procedures archive rather
  than pulling the provider's cases across every category.
- **Provider photo sync**: Every sync now refreshes both the Synced Photo and the
  Profile Photo so the Profile Photo always reflects the latest synced image; a
  manually-chosen Profile Photo is no longer preserved across syncs. Provider term
  editor descriptions were updated to match.

### Fixed

- **Duplicate cases collapsed**: Cases assigned to multiple procedure categories
  exist as separate posts that share a case ID. They are now de-duplicated
  (keeping the first) in the provider grid, its Load More pagination, and the case
  previous/next sequence, so the same case no longer appears twice.

## [4.9.1] - 2026-07-14

### Changed

- **`provider_id` on carousel and procedures shortcodes**: The
  `[brag_book_carousel]` and `[brag_book_gallery_procedures]` shortcodes now use a
  `provider_id` attribute (e.g. `[brag_book_carousel provider_id="43"]`); the
  former `member_id` attribute has been removed. Cases are matched through the
  provider taxonomy assigned to each case, so a provider returns every case they
  appear on, whether primary or secondary.

### Fixed

- **Carousel provider filtering**: `[brag_book_carousel]` provider filtering
  previously matched an unused meta key and returned no results.

## [4.9.0] - 2026-07-02

### Added

- **`provider_id` shortcode attribute**: `[brag_book_gallery_cases]` now accepts
  a `provider_id` attribute (e.g. `[brag_book_gallery_cases provider_id="123"]`)
  to embed a single provider's cases directly. The ID is matched against the
  provider taxonomy term's synced API ID (`provider_id`, falling back to the
  legacy `provider_member_id`), and results are capped at 99 cases.
- **Provider dropdown search**: a search box at the top of the provider filter
  dropdown narrows the list by typing, so long provider lists no longer need to
  be scrolled A-to-Z.

### Changed

- **Provider dropdown ordering**: the provider filter dropdown now lists
  providers alphabetically by name instead of by synced position.

## [4.9.0-beta1] - 2026-07-01 (Beta Release)

### Added

- **`provider_id` shortcode attribute**: `[brag_book_gallery_cases]` now accepts
  a `provider_id` attribute (e.g. `[brag_book_gallery_cases provider_id="123"]`)
  to embed a single provider's cases directly. The ID is matched against the
  provider taxonomy term's synced API ID (`provider_id`, falling back to the
  legacy `provider_member_id`), and results are capped at 99 cases.
- **Provider dropdown search**: a search box at the top of the provider filter
  dropdown narrows the list by typing, so long provider lists no longer need to
  be scrolled A-to-Z.

### Changed

- **Provider dropdown ordering**: the provider filter dropdown now lists
  providers alphabetically by name instead of by synced position.

## [4.8.0] - 2026-06-25

### Added

- **Provider filter**: a provider (doctor) dropdown filter rendered before the
  gallery filters and styled to match them. Each option shows the provider's
  avatar and name; selecting one replaces the case grid with that provider's
  cases via AJAX, scoped to the current procedure on a procedure view. The
  toggle reflects the selected provider's avatar, and an "All Providers" option
  plus a Reset button restore the unfiltered grid. Lists only providers that
  have cases in the current context, and uses the configured case card design.
- **Provider image sync**: provider images are downloaded into the WordPress
  media library during sync, named after the provider slug, and attached as the
  provider's Profile Photo. The created attachment is tracked and deleted from
  WordPress when the provider term is removed. Downloads are idempotent (skipped
  when the source is unchanged), manually-chosen photos are preserved, and the
  remote URL is kept as a fallback when a download fails.

### Fixed

- **Sync stage count**: the Full Sync tooltip, confirmation dialog, and help
  text no longer always say "three stages". They now report three or four
  depending on whether Stage 4 (Providers & Practices) will run, which is only
  when both features are enabled.

### Changed

- **Provider editor wording**: the synced photo field is now described as
  downloaded into the media library rather than a remote API URL.
- **Internal**: extracted a shared `Cases_Handler::build_case_data_from_post()`
  used by the procedure grid, location search, and provider filter so every
  entry point renders identical cards.

## [4.7.1] - 2026-06-25

### Fixed

- **Location search procedure scoping**: a location search on a procedure page
  returned cases from every procedure (e.g. "74 cases" on a procedure with only
  a handful). The shared tiles filter bar rendered the search without the
  procedure context, so no procedure filter was applied. The current procedure
  is now passed through and the candidate cases are scoped to it before the
  distance filtering.
- **Location search card design**: result cards now use the same renderer as
  the procedure gallery grid, so they honour the configured case card design
  (`default` / `v2` / `v3`). They previously used an older renderer that ignored
  the setting.

### Changed

- **Distance badge**: each location search result card now shows how far the
  case is from the searched location (e.g. "3.4 miles away") as a badge on the
  card image.
- **Contextless landing view**: the location search is no longer rendered on
  the main gallery landing view, which has no procedure to scope results to.
- **Results banner placement**: the "Showing N cases within R miles of …"
  banner now appears below the procedure title instead of above it.

## [4.7.0] - 2026-06-16

### Fixed

- **Taxonomy media library**: the Banner Image (procedures) and Profile Photo
  (providers) buttons on the taxonomy admin screens did not open the WordPress
  media library. The enqueued admin assets (`taxonomies-media.js` and
  `taxonomies.css`) were missing and returned 404s, so the button click
  handler never loaded. The assets are now included.

### Changed

- **Nudity warning spacing**: tightened the gaps between the warning title,
  caption, and acknowledge button, and simplified the compact (short-height)
  container layout so the controls are no longer pushed apart.

### Build

- The release `clean` step no longer deletes the static admin assets in
  `assets/js/admin` and `assets/css/admin`, so they are reproduced and shipped
  in the distribution package.

## [4.6.0-beta11] - 2026-06-14 (Beta Release)

### Added

- **Inline location search**: a search field rendered before the gallery
  filter dropdown. Type an address, city, or ZIP (Google Places
  autocomplete) or use your current location to find cases near you. It is
  shown only when a Google Maps API key is configured and Maps loads.

### Changed

- Selecting a location filters the case grid to providers whose associated
  practice is within 50 miles, automatically widening to 100 miles when none
  are closer, and orders the results nearest-first. A summary ("Showing N
  cases within R miles of …") spans the top of the gallery above the title.
- **Sync ordering**: the manifest/terms order now applies to child
  procedures, not just parent categories, so child procedures match the
  BRAGBook ordering after a sync.
- **Gallery columns**: the column view defaults to 2 and follows the
  configured Columns setting for the active view, while still remembering a
  visitor's manual choice across reloads.
- The Google Maps API Key field on the General settings page is now a
  password input with a show/hide toggle.
- The image processing disclaimer text is now 14px with spacing above it so
  it is not crowded against the case grid.

### Removed

- The previous "Find a Provider" map locator (button, modal, and embedded
  Google Map) has been replaced by the inline location search.

## [4.6.0-beta2] - 2026-05-08 (Beta Release)

### Performance

- **Carousel LCP fix**: the first slide of every carousel now renders with
  `loading="eager"` and `fetchpriority="high"` (other slides keep
  `loading="lazy"`). On homepage hero carousels the first image is the
  Largest Contentful Paint candidate, so making it discoverable earlier
  closes the "fetchpriority=high should be applied" gap that Lighthouse
  flags under "LCP request discovery".
- **Plugin asset cache lifetimes**: shipped `assets/.htaccess` that sets
  `Cache-Control: public, max-age=31536000, immutable` on every static
  asset under the plugin's `assets/` directory (CSS, JS, fonts, SVGs,
  images). Filenames are already version-busted via
  `Asset_Manager::get_asset_version()`, so 1-year immutable is safe.
  Resolves Lighthouse's "Use efficient cache lifetimes" diagnostic, which
  previously flagged plugin assets at the host's default 12-hour TTL.
- The same `.htaccess` adds `Access-Control-Allow-Origin: *` for font
  files so WOFF2 preloads still work when assets are proxied through a
  CDN subdomain (e.g. `a.bragbookgallery.com`).

---

## [4.6.0-beta1] - 2026-05-08 (Beta Release)

### Performance

- **Production now serves minified assets**: every shortcode handler and the
  asset registrar now load `*.min.css` / `*.min.js` in production via a shared
  `SCRIPT_DEBUG`-aware suffix helper. Previously the frontend asset registrar
  loaded the unminified bundle (~265 KB of unnecessary bytes per gallery page).
- **Frontend handles consolidated**: every CSS/JS enqueue path now uses the
  canonical `brag-book-gallery-main` handle. Pages with multiple shortcodes
  (e.g. gallery + carousel + sidebar) no longer download the same CSS/JS file
  twice under different handles.
- **JS code-splitting via dynamic `import()`**: `FilterSystem`,
  `FavoritesManager`, `SearchAutocomplete`, and `ShareManager` are now lazy
  chunks (`brag-book-gallery-{filter-system,favorites,search,share}.min.js`).
  Each chunk loads only when its anchor element is in the DOM, dropping the
  main bundle from 190 KB to 133 KB.
- **Carousel-only entry point**: a new `brag-book-gallery-carousel.min.js`
  bundle (~11 KB) ships only the `Carousel` class plus nudity / phone-format
  utilities. The carousel shortcode handler picks this bundle when no other
  BRAGbook shortcode is on the page (typical homepage hero use case),
  otherwise it falls back to the full bundle.
- **Main script deferred**: the frontend bundle is now emitted with a `defer`
  attribute via `script_loader_tag`, keeping it off the critical path.
- **Resource hints**: gallery pages now emit `<link rel="preload">` for the
  minified CSS, Poppins-Regular and Lato-Regular WOFF2 fonts, plus
  `<link rel="preconnect" crossorigin>` and `<link rel="dns-prefetch">` to
  the BRAGbook API origin, so the browser opens the connection before the
  first XHR or image fetch.
- **Image CLS fix**: `aspect-ratio: 16 / 10` (matching the existing skeleton)
  now reserves space on `.brag-book-gallery-image-container`, and carousel
  slide images use `object-fit: cover; height: 100%` so before/after photos
  no longer cause layout shift on load.
- **Image loading hints**: every `<img>` rendered by the shortcode handlers
  now carries `decoding="async"`, and the case-detail hero image picks up
  `fetchpriority="high"` so it's correctly flagged as the LCP candidate.
- **Localized payload trimmed**: removed the unused 115-line
  `localize_frontend_data()` blob (8 unreferenced SVG icon URLs, dead
  `brag_book_gallery_plugin_data` object). Removed duplicate
  `completeDataset` shipping — the case dataset is now emitted only via the
  existing inline `brag-book-gallery-dataset` script instead of being
  duplicated inside `bragBookGalleryConfig`.
- **Wasteful `<picture>` wrapper removed**: carousel slide images previously
  used `<picture><source srcset=… type="image/jpeg"><img></picture>` with the
  source URL identical to the `<img>` URL — adding bytes without giving the
  browser any selection power. Replaced with a flat `<img>` until the API
  exposes WebP/AVIF or multiple sizes.

### Changed

- `Asset_Manager::localize_gallery_script()` no longer accepts an
  `$all_cases_data` argument; the case dataset belongs to the inline
  dataset script and was being shipped twice. The dead
  `Asset_Manager::prepare_case_data()` helper was removed at the same time.
- `Asset_Manager::get_asset_suffix()` is now public so shortcode handlers
  can share the SCRIPT_DEBUG-aware logic.
- `Assets::get_asset_version()` now keys the timestamp cache-bust on
  `SCRIPT_DEBUG` instead of `WP_DEBUG`, so staging sites with `WP_DEBUG`
  enabled still benefit from version-based browser caching.

### Added

- `assets/js/brag-book-gallery-carousel.min.js` — the new carousel-only
  bundle entry, plus its source at `src/js/carousel-frontend.js`.
- `webpack.config.js` now configures `output.publicPath: 'auto'` and a
  stable `chunkFilename` so dynamic-import chunks load from the correct URL
  regardless of where the plugin is installed.

### Build

- Unminified bundle artifacts (`brag-book-gallery.js`,
  `brag-book-gallery-carousel.js`, the admin/sync variants, plus the
  expanded `*.css` files) are now excluded from the dist `.zip` via
  `.distignore`. Production only ships `*.min.{js,css}`.

---

## [4.4.7-beta1] - 2026-05-01 (Beta Release)

### Fixed
- **Filters — procedure-detail filters now scoped to the current procedure**: On a procedure taxonomy archive (e.g. `/blepharoplasty/`), multi-procedure cases were emitting `data-procedure-detail-*` attributes for every procedure attached to the case, so the filter dropdown surfaced labels from unrelated procedures (e.g. Botox "Neuromodulator Types" appeared on the Blepharoplasty page). Card-level detail attributes are now restricted to the current term's API procedure id when on a `brag_book_procedures` archive; non-procedure contexts (cases grid, favorites) keep the prior behavior.
- **Filters — checkbox ids no longer break on values containing quotes**: The dynamic filter HTML built ids by string-substituting raw values like `5'4" - 5'7"`, which terminated the `id` attribute at the first `"` and produced unparseable markup with non-matching `<label for>` references. Ids are now generated through a slug-safe transform (lowercase, non-alphanumerics collapsed to `-`), so input/label pairing works for height ranges and any future values containing punctuation.

### Changed
- Internal refactors across admin pages, debug tools, sync settings, post types, taxonomies, asset/resource managers, and shortcode handlers (cases, favorites, gallery, sidebar). New admin case-meta tabs and taxonomies media UI assets.

---

## [4.4.6] - 2026-04-05

### Fixed
- **Favorites — empty state layout broken**: The "No favorites yet" empty state on the dedicated favorites page rendered with an oversized heart SVG and no layout structure. Added proper CSS for the empty state container, content wrapper (max-width 400px, centered), and icon (48×48px). Removed a conflicting sidebar-context rule that was overriding the page layout.
- **Favorites — logged-in user always sees empty state**: When a user had complete info in localStorage but no locally cached favorites, the page skipped the API call entirely and showed the empty state. The favorites page now always queries the API when the user is logged in, so server-side favorites display even if localStorage is empty.
- **Sync — `procedure_order` no longer written to child procedures**: The sync was assigning `procedure_order` to both parent categories and child procedures. Only parent categories now receive `procedure_order` during sync; child procedures are left without this meta value.

---

## [4.4.5] - 2026-03-27

### Fixed
- **Sync — `procedure_order` written on every sync**: The standard sync path (`class-data-sync.php`) now threads the API array index through `process_category` and `create_or_update_procedure` into `update_procedure_meta`, writing `procedure_order` to term meta on every run. Previously only the chunked sync path wrote this value, so running the standard sync left stale ordering data in place.
- **Sync — `jobId` echoed back to BRAG Book API**: When the BRAG Book application or its cron job triggers a sync with a `jobId` query parameter, the plugin now reads the value, stores it for the background process, and passes it in the body of both the `/register` and `/report` API calls. Without this, the server was creating a new WordPress-attributed job on every externally-triggered sync instead of attributing it to the correct trigger source.
- **Nav — sidebar and dropdown ordered by `procedure_order`**: Parent categories in `.brag-book-gallery-nav` and `.brag-book-gallery-category-nav` are now sorted by `procedure_order` (the API-assigned position written during sync). Child procedures sort alphabetically by default; if `procedure_order` is manually set on a child term it takes precedence, with manually-ordered items appearing before unordered ones.
- **Case detail — height and weight now displayed**: Patient height and weight were present in the case data and rendered by the JavaScript layer but omitted from the PHP-rendered patient details card. Both fields are now included in `get_patient_info_for_card()` and display when the values are available.

---

## [4.4.4] - 2026-03-27

### Fixed
- **Sync — `procedure_order` now populated from API**: Stage 1 now writes each category's and each procedure's position in the terms API response array to the `procedure_order` term meta. Previously this meta was only set manually via the admin UI, so sidebar, gallery, and tiles sort order did not reflect the BRAGBook application's display order after a sync.
- **Sync — Cases associated with multiple procedures**: A procedure can appear in multiple member categories, each with its own category-specific slug, producing multiple WP taxonomy terms sharing the same API procedure ID. Stage 3 was resolving only the first matching term, so cases were only attached to one of those terms. Cases are now assigned to all matching taxonomy terms via `wp_set_object_terms`, and case ordering is stored for each term so procedure views in all categories reflect the correct case list.
- **View tracking — Invalid `caseProcedureId` submissions**: The JS view tracking fell back to `data-case-id` (the global `caseId`, a large number) when `data-procedure-case-id` was absent. The API rejected these as `CaseProcedureRelationship not found` because the global `caseId` is not a valid junction ID. Tracking now uses `data-procedure-case-id` (`brag_book_gallery_procedure_case_id`) exclusively in all three call sites (`trackPageView`, `trackCaseViewFromCard`, and the AJAX case load handler). If the attribute is missing, tracking is skipped and a console warning is logged rather than submitting an invalid ID.
- **View tracking — Procedure views not accidentally triggered on case pages**: Added a missing `return` so that a case detail page with no `data-procedure-case-id` cannot fall through and fire a procedure view tracking request.

---

## [4.4.3] - 2026-03-24

### Fixed
- **Remote Sync Reliability**: Stage 3 case processing now runs as a self-chaining batch chain instead of a single long-lived PHP process loop. Each batch of ~10 cases runs in its own short-lived non-blocking loopback request (~5–15 s), making syncs immune to PHP-FPM `request_terminate_timeout`, Nginx proxy timeouts, and WP Engine's execution limits. Syncs with hundreds of cases that previously stalled mid-way will now complete reliably.
- **Remote Sync — "0 cases synced"**: Fixed a race condition where a fallback sync execution re-ran `execute_full_sync()` while a batch chain was already processing, overwriting the active batch token and resetting Stage 3 to offset 0.
- **Batch execution always reachable**: `execute_sync_batch`, `fire_next_batch`, and `finalize_sync` moved to `Sync_Ajax_Handler` as static methods and called directly from the nopriv AJAX handler — no action hook registration required, no dependency on which admin classes are loaded.
- **One-time batch token**: Each batch dispatch generates a fresh token stored in options. Stale or duplicate loopback requests that arrive after the token has been rotated are rejected, preventing double-processing.

### Improved
- **Sync Performance**: API token, website property ID, and `Endpoints` instance are now cached at construction time in `Chunked_Data_Sync`, eliminating repeated `get_option()` calls and object instantiation on every case fetch during Stage 3 (previously ~2 DB reads + 1 object construction per case).
- **Sync Performance**: Removed artificial `usleep()` delays — the 50 ms pause every 5 cases in the batch loop and the 100 ms pause between pagination pages in manifest building are gone.
- **Sync Performance**: `count()` is pre-calculated outside loops in batch processing, avoiding repeated array counting on every iteration.
- **Sync Performance**: Stage 3 state saved between batches no longer includes the full manifest array; manifest is always loaded from file, reducing option storage size significantly.
- **Sync Performance**: Removed `JSON_PRETTY_PRINT` from manifest and sidebar data file writes, reducing file I/O overhead.
- **Debug Logging**: All sync debug output is now gated behind `brag_book_gallery_debug_mode = yes` via a `debug_log()` method — no disk I/O on every sync in production.

### Changed
- **Removed all WP-Cron from the sync pipeline**: WP Engine's system cron fires too infrequently to be a useful fallback. The sync relies entirely on non-blocking loopback HTTP for both the initial execution trigger and each Stage 3 batch dispatch.

### Security
- Sync data directory `.htaccess` now denies all direct HTTP access (`Deny from all`); previously JSON data files were publicly readable via URL.

### Removed
- Dead code: `process_cases_from_manifest()` (superseded by batched processing), `resume_stage_3()` (placeholder, never called), `save_stage3_state()` (only used by deleted method).
- `test-sync-validation.php` development file removed from production plugin.

---

## [4.4.3-beta8] - 2026-03-24 (Beta Release)

### Changed
- **Removed all WP-Cron usage from the sync pipeline**: WP-Cron is unreliable on WP Engine (system cron interval is too infrequent to be a useful fallback). The sync now relies entirely on non-blocking loopback HTTP requests. Removed `wp_schedule_single_event` from `fire_next_batch()` (per-batch fallback) and from `handle_rest_trigger_sync()` (main sync fallback). Removed `register_batch_hook()` and its `Setup::init()` call. The `brag_book_gallery_rest_sync` action hook remains as the shared execution entry point for the background loopback.

---

## [4.4.3-beta7] - 2026-03-22 (Beta Release)

### Fixed
- **Remote Sync — Batch Chain Never Executed**: The `execute_sync_batch` logic was defined in `Sync_Page` and registered via `add_action` in its constructor. `Sync_Page` is only instantiated in the logged-in admin context, so the nopriv admin-ajax loopback and WP-Cron fallback requests had no listener — `do_action('brag_book_gallery_process_sync_batch')` fired with zero listeners and silently did nothing. Remote syncs therefore always reported 0 cases processed.
- **Moved batch execution to `Sync_Ajax_Handler`**: `execute_sync_batch`, `fire_next_batch`, and `finalize_sync` are now static methods on `Sync_Ajax_Handler` (same namespace as `Chunked_Data_Sync` and `Sync_Api`). `handle_process_sync_batch` calls `self::execute_sync_batch()` directly rather than relying on `do_action`.
- **Unconditional hook registration**: `Sync_Ajax_Handler::register_batch_hook()` is now called outside the `is_admin()` block in `Setup::init()`, so the `brag_book_gallery_process_sync_batch` action listener is present for both the admin-ajax loopback path and wp-cron.php requests.
- **WP-Cron race condition**: `handle_rest_sync_execution()` now also skips if `brag_book_gallery_sync_batch_token` exists, meaning a Stage 3 batch chain is already in flight. Previously the WP-Cron fallback (scheduled 60 s after the REST trigger) would re-run `execute_full_sync()` while the loopback's batch chain was processing — overwriting the batch token and resetting Stage 3 to offset 0, producing the "0 cases synced" result seen in beta6.

---

## [4.4.3-beta6] - 2026-03-22 (Beta Release)

### Fixed
- **Remote Sync Reliability**: Stage 3 case processing now runs as a self-chaining batch chain instead of a single long-lived PHP process loop. Each batch of 10 cases runs in its own short-lived HTTP request (~5–15 seconds), making the sync immune to PHP-FPM timeouts, Nginx proxy timeouts, and WP Engine's `request_terminate_timeout`. Syncs with hundreds of cases previously stalled mid-way on production hosting; they will now complete regardless of server timeout limits.

### How It Works
- After Stage 2 completes, a non-blocking loopback POST fires the first batch immediately
- Each batch processes 10 cases, saves its state, then fires the next batch via loopback
- A WP-Cron single event (30s delay) acts as a fallback if the host blocks loopback HTTP
- The WP-Cron fallback validates a one-time token; if the loopback already ran the batch the cron exits without duplicating work
- On completion the last batch updates the sync log, plugin settings, and reports to the BRAGBook API — identical to the previous single-process flow

---

## [4.4.3-beta2] - 2026-03-22 (Beta Release)

### Improved
- **Sync Performance**: API token, website property ID, and `Endpoints` instance are now cached at construction time, eliminating repeated `get_option()` calls and object instantiation on every case fetch during Stage 3 (previously ~2 DB reads + 1 object construction per case)
- **Sync Performance**: Removed artificial `usleep()` delays — the 50ms pause every 5 cases in the batch loop and the 100ms pause between pagination pages in manifest building are gone
- **Sync Performance**: `count()` is now pre-calculated outside loops in batch processing, avoiding repeated array counting on every iteration
- **Sync Performance**: Stage 3 state saved between batches no longer includes the full manifest array; manifest is always loaded from file, reducing option storage size significantly
- **Sync Performance**: Removed `JSON_PRETTY_PRINT` from manifest and sidebar data file writes, reducing file I/O overhead
- **Debug Logging**: All sync debug output is now gated behind `brag_book_gallery_debug_mode = yes` via a new `debug_log()` method, eliminating disk I/O on every sync operation in production
- **Security**: Sync data directory `.htaccess` now denies all HTTP access (`Deny from all`); previously JSON files were publicly readable

### Removed
- Dead code: `process_cases_from_manifest()` (old method, superseded by batched processing), `resume_stage_3()` (placeholder, never called), and `save_stage3_state()` (only used by the deleted method)
- `test-sync-validation.php` development file removed from production plugin

---

## [4.4.2] - 2026-03-17 (Stable Release)

### Enhanced
- **Dashboard Page**: API Connection status now uses a green badge instead of a dot with red text
- **Dashboard Page**: Gallery Statistics section spacing improved between title and stat cards
- **Dashboard Page**: Stat cards use flexbox column layout with proper gap between label and value
- **Dashboard Page**: Removed box-shadow from stat cards for cleaner appearance
- **General Settings Page**: "Display & Gallery Settings" title upgraded to h1, bold, with tighter description spacing
- **General Settings Page**: "Gallery Page Settings" heading moved inside the card above the gallery slug field
- **Communications Page**: Detail dialog widened to 720px minimum on desktop
- **Communications Page**: Email and phone links styled as blue links in detail dialog
- **Communications Page**: Reply via Email button styled red, Close button styled black
- **Communications Page**: Removed icon and hover lift animation from Reply via Email button
- **Communications Page**: View and Delete action buttons now stack vertically
- **Communications Page**: Date icon tooltip displays date on hover with custom CSS tooltip
- **Communications Page**: Added description paragraph below Consultation Entries heading
- **Communications Page**: Dialog title renamed to "Consultation Entry Details"
- **Communications Page**: Tab badge turns white when parent tab is active
- **Sync Page**: Tablet Mode section hidden
- **Debug Page**: Factory reset section no longer has red background, border, margin, or padding
- **Admin UI**: Tab content padding changed to horizontal-only (0 30px)
- **Admin UI**: Tab panel h2 border-bottom removed
- **Admin UI**: Section h2 margin-bottom increased for better spacing
- **Admin UI**: API row uses border instead of background color
- **Admin UI**: Status badge no longer has fixed width/height constraints

### Removed
- **Display Settings Preview Images**: Removed all preview images from `assets/images/previews/` to reduce plugin package size

---

## [4.4.2-beta2] - 2026-03-12 (Beta Release)

### Added
- **Tablet Mode Parameter**: New `tablet` parameter on the v2 cases API endpoint that filters results to only return cases marked for tablet use
- **Tablet Mode Toggle on Sync Page**: Standalone toggle card on the sync page to enable tablet-only case syncing during Stage 2 manifest building
- **API Debug Tool Updates**: Tablet checkbox added to the API test panels on both the Debug and API Test pages for v2 cases endpoint testing

### Enhanced
- **v2 Cases Endpoint**: `get_cases_v2()` now accepts a `tablet` parameter, passed through as a query parameter to the external API
- **Sync Pipeline**: Tablet mode flows through the full sync chain — AJAX handler, Chunked_Data_Sync, and Data_Sync classes

---

## [4.4.0] - 2026-03-03 (Stable Release)

### Added
- **HIPAA-Compliant Sync Registry**: New unified `wp_brag_sync_registry` table replaces the old `wp_brag_case_map` table, tracking all synced items (cases, procedures, doctors) with API-to-WordPress ID mapping
- **Orphan Detection & Cleanup**: Detects WordPress items (posts and terms) that no longer exist in the BRAGBook API after a sync completes
- **Orphan Manager**: New class for orphan detection, deletion, and HIPAA-compliant audit logging (no PHI in logs)
- **Manual Orphan Review**: Admin UI panel after Stage 3 sync shows orphaned items grouped by type with names, allowing preview before deletion
- **Automatic Orphan Cleanup**: REST/automatic syncs auto-detect and remove orphans after successful completion
- **Database Migration**: Automatic migration from `wp_brag_case_map` to `wp_brag_sync_registry` with data preservation (DB version 1.3.0)
- **Case Detail Thumbnail Carousel**: Thumbnails now display in a proper carousel with prev/next arrow navigation and pagination dots
  - Responsive layout: 3 thumbnails on desktop, 2 on tablet, 1 on mobile
  - Arrows and pagination dots auto-hide when all thumbnails fit on screen
  - Pagination dynamically recalculates on window resize across breakpoints
- **Carousel Title Parameter**: New `title` parameter on `[brag_book_carousel]` shortcode to override the procedure name heading
- **Standardized Case Image Alt Text**: New `get_case_alt_text()` helper for consistent "Before and after {procedure} case {id}" format across all views
  - SEO alt text override supported via `brag_book_gallery_seo_alt_text` post meta
  - `data-alt-text` attribute added to case cards for JS-rendered image support
- **Database Tables Diagnostic**: Diagnostic tools page now verifies `brag_sync_registry` and `brag_sync_log` tables exist with row counts
- **Display Settings Previews**: Preview images added for Procedures View settings dialogs

### Enhanced
- **Admin UI Overhaul**: Replaced WordPress default tables with modern BEM-styled components across debug, communications, dashboard, sync, and settings pages
  - System status uses alternating rows with SVG status indicators
  - System information wrapped in accordion with terminal-style dark theme
  - Debug logs restyled with toggle switch, file metadata cards, and log viewer
  - Communications table, dialog, and pagination updated to design system
  - API test panel consolidated with base URL dropdown
  - All inline styles moved to SCSS with design tokens
- **Delete All Synced Data**: Now also clears the `brag_sync_registry` table

### Fixed
- **SEO Alt Text Sync Source**: Fixed `seoAltText` to source from `seoInfo.altText` instead of photo image `altText`
- **Favorites System**: Fixed incorrect `caseProcedureId` sent to API, card removal animation, heart state sync, and localStorage count using junction IDs
- **Carousel View Tracking**: Fixed nonce mismatch and missing config on carousel-only pages; uses correct junction ID for view tracking
- **API v2 Sidebar Endpoint**: Replaced deprecated `/sidebar` endpoint with `/api/plugin/v2/terms`
- **Duplicate API Test Output**: Fixed debug page rendering request/response details twice
- **Main Image Alt Text**: Uses base SEO alt text only (removed redundant "Angle 1" suffix)
- **Thumbnail Alt Text**: Angles now start from "Angle 1" instead of "Angle 2"
- **Image Swap Flash**: Clicking a thumbnail now updates the image in-place instead of replacing the DOM

---

## [4.3.0] - 2025-01-19 (Stable Release)

### Fixed
- **Favorites Removal API**: Fixed 400 error when removing favorites from "My Favorites" page
  - Added proper `caseProcedureId` and `procedureId` fallbacks from multiple meta sources
  - Fixed `ajax_get_case_by_api_id` to search all possible meta keys (`brag_book_gallery_procedure_case_id`, `brag_book_gallery_original_case_id`, `brag_book_gallery_case_id`)
  - Card is now removed from the view with animation when successfully unfavoriting
  - Added state restoration when API call fails
  - Updated in `includes/shortcodes/class-favorites-handler.php` and `src/js/modules/favorites-manager.js`

- **Case Carousel Pagination**: Improved accessibility and fixed invalid HTML
  - Changed pagination dots from anchor tags to semantic button elements
  - Added ARIA attributes (`role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls`, `aria-label`)
  - Fixed invalid nested anchor HTML in v3 card type by moving pagination outside anchor wrapper
  - Added IntersectionObserver to update active dot on scroll
  - Updated in `includes/shortcodes/class-cases-handler.php` and `src/js/modules/main-app.js`

- **Mobile Header Visibility**: Fixed mobile header disappearing between 1024px and 1280px
  - JavaScript breakpoint now matches CSS media query (1279px)
  - Mobile header visible from 0-1279px, sidebar visible from 1280px+
  - Updated in `src/js/modules/mobile-menu.js`

---

## [4.2.0] - 2025-01-09 (Stable Release)

### Enhanced
- **SEO Plugin Detection**: Plugin now detects Yoast SEO, Rank Math, and All in One SEO
  - When a major SEO plugin is active, the custom sitemap is not created separately
  - Gallery URLs are added to the SEO plugin's sitemap index instead
  - Prevents duplicate sitemap functionality and conflicts
  - Updated in `includes/seo/class-sitemap.php`

- **Column View Layout**: Improved procedure category grid layout
  - Columns now cap at 4 maximum regardless of category count
  - Additional categories wrap to the next row automatically
  - Better visual presentation for sites with many procedure categories
  - Updated in `includes/shortcodes/class-gallery-handler.php`

### Fixed
- **Carousel Image Fallback**: Added fallback for case carousel images
  - When high-res URLs are not available, post-processed URLs are used instead
  - Ensures carousel functionality works even without high-res images
  - Updated in `includes/shortcodes/class-cases-handler.php`

---

## [4.1.0] - 2025-12-24 (Stable Release)

### Enhanced
- **Case View Tracking**: Improved view tracking reliability for case detail pages
  - Added `data-procedure-case-id` attribute to case detail view wrappers
  - JavaScript now reads case ID directly from DOM data attributes instead of parsing URLs
  - More reliable tracking across different URL formats and page contexts
  - Updated in `includes/shortcodes/class-case-handler.php`

### Fixed
- **Duplicate View Tracking**: Fixed issue where case views could be tracked twice
  - Removed redundant tracking call from `handleDirectCaseUrl()` function
  - Views are now tracked once via `trackPageView()` when case detail view is detected
  - Updated in `src/js/modules/main-app.js`

### Developer
- **Enhanced Logging**: Improved view tracking API response logging for debugging
  - Added detailed response body logging on successful API calls
  - Better visibility into view tracking success/failure states
  - Updated in `includes/shortcodes/class-gallery-handler.php`

---

## [4.0.0] - 2025-12-09 (Stable Release)

This major release consolidates all features and improvements from the 3.3.2 beta series into a stable production release.

### Added
- **Doctors Taxonomy**: New `brag_book_doctors` taxonomy for managing doctor profiles
  - Term meta fields: First Name, Last Name, Suffix, Profile URL, Profile Photo, and Member ID
  - Doctors submenu in BRAG book admin menu (when property ID 111 is enabled)
  - Automatic doctor term creation during Stage 3 data sync from case creator information
- **Doctor Profile URL Field**: `brag_book_gallery_doctor_profile_url` meta field for case post types
- **Doctor Suffix Field**: `brag_book_gallery_doctor_suffix` meta field for case post types
- **Doctor Details Display**: "Show Doctor Details" toggle in Display Settings
- **Doctor Name Field**: Doctor Name field in case post meta (Basic Information tab)
- **Member ID Field**: Member ID number field in case post meta
- **Minified Assets**: Intelligent asset minification system
  - Production mode loads `.min.js` and `.min.css` files (50-54% smaller JS, 10-13% smaller CSS)
  - Development mode (`SCRIPT_DEBUG` enabled) loads non-minified versions
- **Procedure Links**: Clickable links to procedures in case card details with hover animations

### Enhanced
- **Case View Doctor Profile**: Doctor profile photo and name displayed below case title (property ID 111)
- **Cases Grid Doctor Display**: Case cards display doctor photo and name instead of procedure when enabled
- **V3 Card Doctor Display**: V3 cards show doctor name in overlay when "Show Doctor Details" is enabled
- **Search Input Accessibility**: Improved ARIA attributes for better screen reader support
- **HTML Semantics**: Improved semantic HTML structure throughout the plugin

### Fixed
- **Sitemap Generation**: Fixed critical `TypeError` in Sitemap class
- **Stage 3 Sync Title Assignment**: Fixed case post titles being overwritten with incorrect procedure names
- **V3 Card Image Clickability**: Images in v3 cards are now fully clickable
- **Landing Page Text Editor**: Replaced TinyMCE with Trumbowyg WYSIWYG editor
- **Gallery Landing Page Error**: Fixed null reference error in procedure referrer tracking
- **Generate Favorites Page Button**: Fixed button functionality and status checking
- **Case Navigation URLs**: Fixed navigation buttons to use full absolute URLs

### Styling
- New CSS styles for doctor profile section in case view header
- New CSS styles for doctor avatar and name in case card overlays
- Updated consultation chart colors for consistency

---

## [3.3.2-beta15] - 2025-12-01 (Previous Beta)

### Added
- **Doctors Taxonomy**: New `brag_book_doctors` taxonomy for managing doctor profiles
- Term meta fields: First Name, Last Name, Suffix, Profile URL, Profile Photo, Member ID
- Doctors submenu in BRAG book admin menu (when property ID 111 is enabled)
- Automatic doctor term creation during Stage 3 data sync

### Enhanced
- **Case View Doctor Profile**: Doctor profile photo and name displayed below case title
- **Cases Grid Doctor Display**: Case cards display doctor photo and name when enabled
- Updated v2 and v3 card overlays to support doctor display mode

### Styling
- New CSS styles for doctor profile section in case view header
- New CSS styles for doctor avatar and name in case card overlays

## [3.3.2-beta14] - 2025-11-13

### Enhanced
- **Search Input Accessibility**: Improved search input ARIA attributes for better screen reader support
  - Added `role="combobox"` to mobile search input for proper accessibility compliance
  - Standardized class names across mobile and desktop search inputs (both use `brag-book-gallery-search-input`)
  - Enhanced ARIA labels, autocomplete attributes, and controls
  - Updated in `includes/shortcodes/class-gallery-handler.php:891-906, 943-956, 2251-2264`
- **HTML Semantics**: Improved semantic HTML structure throughout the plugin
  - Changed non-heading titles from `<h4>` to `<p>` tags where headings were not semantically appropriate
  - Updated Gallery Checker "Page Status" title in `includes/admin/debug/class-gallery-checker.php:424`
  - Updated nudity warning title in `src/js/modules/filter-system.js:932`
  - Improves document outline and accessibility for screen readers
- **Chart Colors**: Updated consultation chart colors in Communications page
  - Changed chart border and background colors from `#D94540` to `#CC0000` for consistency
  - Updated in `includes/admin/pages/class-communications-page.php:587, 623-624`

## [3.3.2-beta13] - 2025-11-12

### Added
- **Minified Assets**: Implemented intelligent asset minification system
  - Production mode loads `.min.js` and `.min.css` files for optimal performance
  - Development mode (`SCRIPT_DEBUG` enabled) loads non-minified versions for debugging
  - Webpack generates both minified and non-minified JavaScript files
  - Sass generates both compressed and expanded CSS files
  - File size reductions: JavaScript 50-54%, CSS 10-13%
  - Added `get_asset_suffix()` helper method to determine asset file suffix
  - Updated in `webpack.config.js`, `package.json`, `includes/resources/class-asset-manager.php:105-107`, `includes/resources/class-assets.php:353-398`, and `includes/admin/pages/class-sync-page.php:2062-2093`
- **Procedure Links**: Added clickable links to procedures in case card details
  - Each procedure in "Procedures Performed" list now links to its taxonomy page via `get_term_link()`
  - Includes hover animations with subtle lift effect and box shadow
  - Added `brag-book-gallery-case-card-procedures-list__link` CSS class with full styling
  - Proper ARIA labels for accessibility ("View [Procedure] cases")
  - Enhanced user navigation to related cases by procedure
  - Enhanced in `includes/shortcodes/class-cases-handler.php:2457-2494` and `src/scss/components/case/_procedures-list.scss`

### Fixed
- **Sitemap Generation**: Fixed critical `TypeError` in Sitemap class
  - Resolved "Return value must be of type string, null returned" error
  - Fixed undefined variable references when Cache_Manager was removed
  - Updated `get_sitemap_content()`, `generate_sitemap()`, `is_rate_limited()`, and `get_cached_data()` methods
  - All variables now properly initialized before use (lines 306, 353, 1196, 1232)
  - Fixed in `includes/seo/class-sitemap.php`

## [3.3.2-beta10] - 2025-11-10

### Added
- **Doctor Profile URL Field**: Added `brag_book_gallery_doctor_profile_url` meta field to case post types
  - Allows storing URL to doctor's profile page
  - Field type: URL input with validation
  - Added to case meta box in WordPress admin
  - Automatically saved with proper URL sanitization (`esc_url_raw`)
- **Doctor Suffix Field**: Added `brag_book_gallery_doctor_suffix` meta field to case post types
  - Stores professional suffix (e.g., MD, PhD, DDS)
  - Field type: Text input
  - Added to case meta box in WordPress admin
  - Sanitized using `sanitize_text_field`

### Enhanced
- **V3 Card Doctor Display**: Enhanced v3 card type to show doctor name when "Show Doctor Details" option is enabled
  - Doctor name now displays in card overlay instead of procedure name when toggle is active
  - Controlled by `brag_book_gallery_show_doctor` option (set to `1` to enable)
  - Falls back to procedure name if doctor name is not available
  - Works with both `render_case_card` and `render_wordpress_case_card` methods
  - Enhanced in `includes/shortcodes/class-cases-handler.php`
- **V3 Card Case Number**: Case number now hidden on v3 cards when doctor name display is enabled
  - Provides cleaner appearance when showing doctor information
  - Case number still displays when doctor option is disabled

## [3.3.2-beta9] - 2025-11-07

### Fixed
- **Stage 3 Sync Title Assignment**: Fixed issue where case post titles were being overwritten with incorrect procedure names
  - Moved taxonomy assignment to occur before `save_api_response_data()` call
  - Ensures correct procedure term is available when title is regenerated
  - Previously, cases from different procedures could all show the same procedure name (e.g., all showing "Tummy Tuck")
  - Fixed in `includes/sync/class-chunked-data-sync.php:1619-1643`

### Enhanced
- **V3 Card Image Clickability**: Added anchor link around images in v3 card type
  - Images in v3 cards are now fully clickable and link to case detail page
  - Previously only the arrow button in the overlay was clickable
  - V2 cards remain unchanged (arrow-only clickability)
  - Enhanced in `includes/shortcodes/class-cases-handler.php:2290-2340`

## [3.3.2-beta8] - 2025-11-06

### Fixed
- **General Bug Fixes**: Various bug fixes and stability improvements
- **Case Details**: Enhanced case details display and functionality
- **Titles**: Improved title handling and display
- **Sync Updates**: Updated synchronization functionality and reliability

## [3.3.2-beta7] - 2025-11-04

### Added
- **Doctor Details Display**: New "Show Doctor Details" toggle setting in Display Settings
  - Allows administrators to control visibility of doctor information on case pages
  - Setting: `brag_book_gallery_show_doctor_details` (default: false)
- **Doctor Name Field**: Added Doctor Name field to case post meta in Basic Information tab
  - Stores doctor name as `_brag_book_gallery_doctor_name` post meta
  - Displayed in admin interface for case management
- **Member ID Field**: Added Member ID number field to case post meta in Basic Information tab
  - Stores member ID as `_brag_book_gallery_member_id` post meta
  - Useful for tracking and organizing cases by member

### Fixed
- **Generate Favorites Page Button**: Fixed button functionality and status checking
  - Added initial status check on page load to show correct button state
  - Button now properly detects existing favorites page before showing generate option
  - Fixed edge case where button showed incorrect state after page refresh

## [3.3.2-beta2] - 2025-10-09

### Fixed
- **Landing Page Text Editor**: Replaced TinyMCE with Trumbowyg WYSIWYG editor to resolve AMD/RequireJS conflicts
  - Removed problematic WordPress TinyMCE editor that conflicted with Monaco Editor
  - Implemented lightweight Trumbowyg editor with visual and HTML editing modes
  - Fixed "Can only have one anonymous define call per script file" error in `includes/admin/pages/class-general-page.php:602-612`
  - Vanilla ES6 JavaScript implementation for better performance
  - Toolbar includes formatting, bold, italic, links, lists, and HTML view toggle
- **Gallery Landing Page Error**: Fixed null reference error in procedure referrer tracking
  - Added null check in `src/js/modules/global-utilities.js:210` before accessing regex match results
  - Resolved "Cannot read properties of null (reading '1')" JavaScript error
  - Error only occurred when visiting gallery landing page (non-procedure pages)

## [3.3.2-beta1] - 2025-10-09

### Added
- Initial beta release for testing multi-channel release system

## [3.3.1] - 2025-10-08 (Current Release)

### Added
- **Column View**: New shortcode view for displaying procedures organized by parent categories
  - Adaptive grid layout automatically adjusts columns based on number of parent categories (1-5 columns)
  - Responsive breakpoints for mobile, tablet, and desktop displays
  - Usage: `[brag_book_gallery view="column"]`
- **Procedure Banner Images**: Support for banner images on procedure parent categories
  - Retrieves banner images from `banner_image` term meta
  - Implements responsive `<picture>` elements with multiple image sizes
  - Includes lazy loading and async decoding for performance
  - Automatic fallback to parent category name for alt text
- **Multi-Channel Release System**: Beta, RC, and stable release channels
  - Users can opt into beta or RC releases for early access to new features
  - Channel selection available in General Settings
  - Automatic filtering of GitHub releases based on selected channel
  - Enhanced update notification system with channel-specific warnings

### Fixed
- **Asset Versioning**: Updated Asset_Manager VERSION constant to match plugin version
- **Column View Assets**: Added missing asset enqueuing in `handle_column_view()` method

## [3.3.0] - 2025-10-07

### Added
- **Automatic Sync Cron Jobs**: Full implementation of WordPress cron-based automatic synchronization
  - Added weekly cron schedule support to WordPress (not included by default)
  - Implemented custom date/time scheduling for one-time sync events
  - Created visual cron status display on Sync Settings page showing next scheduled sync
  - Added "Test Cron Now" button for manual cron job testing and validation
  - Full 3-stage sync execution via cron (Procedures, Manifest, Cases)
  - Detailed logging for all cron operations for debugging
  - Automatic schedule clearing when sync is disabled
- **Cron Status Monitoring**: Real-time visibility of scheduled sync operations
  - Shows exact date/time of next scheduled sync
  - Displays human-readable countdown (e.g., "In 6 days")
  - Indicates overdue syncs when cron hasn't executed on schedule
  - Integrated status display directly in admin interface

### Fixed
- **Carousel Cross-Origin Images**: Fixed Firefox cookie rejection errors for Cloudflare-protected images from BRAGBook API
  - Added `crossorigin="anonymous"` attributes to all external image elements in JavaScript modules
  - Prevents Firefox from rejecting Cloudflare `__cf_bm` cookies when loading before/after images
  - Affected files: filter-system.js, global-utilities.js, main-app.js, carousel.js
- **JavaScript Build Errors**: Fixed syntax errors in main-app.js caused by console statement cleanup
  - Removed orphaned object literals left after automated console.log removal
  - Fixed broken JavaScript that was preventing webpack builds from completing
- **Nudity Warnings on Case Cards**: Fixed nudity warnings not appearing on individual case cards for procedures with nudity flags
  - Added missing nudity warning rendering logic to `render_wordpress_case_card()` method in Cases_Handler class
  - Fixed inconsistent nudity detection by using WordPress taxonomy meta instead of API sidebar data
  - Unified nudity detection approach across gallery and sidebar handlers for consistency
- **Favorites Display**: Enhanced favorites functionality with user information display
  - Added user email and favorites count display after content title on favorites page
  - Updated card HTML structure to match exact design specifications
  - Improved favorites grid rendering with proper user info integration
- **Procedure Taxonomy Pages**: Prevented unwanted API calls on procedure taxonomy pages
  - Fixed `is_bragbook_page()` method in Assets class to exclude procedure taxonomy pages
  - Added explicit check using `is_tax('procedures')` to prevent frontend assets from loading
  - Resolves issue where sidebar and cases API endpoints were being called unnecessarily

### Enhanced
- **Code Quality**: Removed all development console.log statements from JavaScript modules
  - Cleaned up debugging code from all frontend JavaScript files for production
  - Improved code maintainability and reduced bundle size
- **Carousel Simplification**: Removed GSAP dependency and autoplay functionality from carousel
  - Simplified carousel implementation to use only native browser APIs
  - Removed complex animation library dependencies for better cross-browser compatibility
  - Eliminated autoplay and auto-scroll options as requested
- **Performance Improvements**: Increased default posts per page from 10 to 200 for better user experience
  - Updated `brag_book_gallery_items_per_page` option default value across all relevant handlers
  - Reduces need for pagination and improves gallery browsing experience
- **Card Structure**: Updated JavaScript-generated favorite cards to match exact HTML structure
  - Ensured consistency between server-rendered and client-rendered case cards
  - Improved responsive design and styling consistency
- **Sync Status Display**: Enhanced file-based sync status to show comprehensive data equivalent to previous database system
  - Updated `parse_log_file_for_status()` method to extract detailed procedure and case counts from log files
  - Added warning detection for duplicate case IDs and other sync warnings
  - Implemented accurate counting of procedures and cases created by parsing log entries
  - Enhanced duration formatting to match previous MM:SS display format
  - Updated sync status display to show warnings, duplicate counts, and comprehensive statistics
  - Maintains full data compatibility with previous sync status information

### Attempted
- **Firefox Carousel Navigation**: Extensive debugging and attempted fixes for Firefox-specific carousel navigation issues
  - Investigated Firefox scrollTo() compatibility and scroll behavior differences
  - Attempted transform-based navigation solutions for Firefox browser
  - Ultimately reverted to universal implementation due to complexity of Firefox-specific workarounds
  - Firefox navigation issues remain unresolved but codebase is clean and maintainable

## [3.2.8] - 2025-09-11

### Fixed
- **Procedure Taxonomy Pages**: Prevented unwanted API calls on procedure taxonomy pages
  - Fixed `is_bragbook_page()` method in Assets class to exclude procedure taxonomy pages
  - Added explicit check using `is_tax('procedures')` to prevent frontend assets from loading
  - Resolves issue where sidebar and cases API endpoints were being called unnecessarily

### Changed
- **Version Numbering**: Updated to semantic versioning 3.3.0 for new feature improvements

## [3.2.8] - 2025-09-11

### Added
- **Delete All Rewrite Rules**: New functionality in Flush Rules debug tool to completely remove all rewrite rules
  - Aggressive deletion mechanism with multiple methods to ensure complete removal
  - Direct database deletion using SQL queries
  - Temporary blocking of WordPress rule regeneration (60 seconds)
  - Confirmation count showing exactly how many rules were deleted
  - Detailed logging of deletion operations

### Fixed
- **Rewrite Rules Detection**: Fixed gallery rules detection in flush rules tool
  - Updated to detect modern query variables (brag_book_gallery_view, brag_gallery_slug, etc.)
  - Fixed issue where gallery rules were showing as 0 when they actually existed
  - Added support for both legacy and current query variable patterns
- **Verify Rules Function**: Enhanced to check database directly without triggering regeneration
  - Uses direct SQL query to check if rules exist
  - Shows clear success message when rules are deleted
  - Prevents WordPress from auto-regenerating rules during verification

### Improved
- **Rewrite Flush Tool**: Enhanced user interface and feedback
  - Added "Danger Zone" section for destructive operations
  - Double confirmation required for delete all rules operation
  - Shows deletion history with exact counts (total and gallery-specific)
  - Better error handling with detailed error messages
  - Visual status indicators for rule presence/absence

## [3.2.7] - 2025-09-11

### Added
- **Dual Caching System**: Implemented comprehensive dual caching strategy for optimal performance
  - All data types (sidebar, cases, individual case, carousel) now use both WP Engine object cache AND transients
  - Automatic fallback mechanism ensures data persistence across cache flushes
  - Intelligent cache retrieval checks object cache first (faster), falls back to transients if needed
- **Legacy Transient Cleanup**: Added dedicated cleanup functionality for old transient patterns
  - New "Clear Legacy Transients" button in Cache Management debug tool
  - Removes obsolete transient patterns from previous plugin versions
  - Automatic detection and cleanup of orphaned cache entries

### Fixed
- **Cache Management Tool**: Resolved critical issues with cache viewing and management
  - Fixed double-prefixing issue preventing cache data from being viewed
  - Updated queries to detect both old and new transient naming patterns
  - Corrected delete operations to handle various key formats
  - Fixed clear_all_cache() method that was returning static message instead of clearing cache
- **Cache Helper Functions**: Enhanced to provide true dual caching
  - brag_book_set_cache() now stores in BOTH wp_cache and transients
  - brag_book_get_cache() checks wp_cache first, falls back to transients
  - brag_book_delete_cache() removes from BOTH cache layers

### Improved
- **Cache Query Performance**: Optimized database queries for cache management
  - Updated SQL queries to search for multiple transient patterns efficiently
  - Improved pagination for large cache datasets
  - Enhanced cache statistics calculation

## [3.2.6] - 2025-09-11

### Fixed
- **Cache Management Debug Tools**: Enhanced cache view functionality with comprehensive diagnostic logging
  - Added detailed debug logging for cache management view operations
  - Implemented database validation checks for transient cache items
  - Added expiration timestamp validation for cache debugging
  - Improved error reporting for cache retrieval issues

## [3.2.5] - 2025-09-11

### Added
- **WP Engine Diagnostics Tool**: Comprehensive diagnostic system specifically designed for WP Engine hosting environments
  - Environment detection and compatibility checking for WP Engine servers
  - Rewrite rules testing and validation with URL pattern matching
  - Query variable registration verification and debugging
  - Cache status analysis including object cache and WP Engine-specific caching
  - Automated recommendations for optimization and troubleshooting
  - AJAX-powered interface for real-time diagnostics
- **Enhanced WP Engine Cache Support**: Improved cache helper functions with proper WP Engine object cache integration
  - Automatic WP Engine environment detection via multiple methods
  - Comprehensive cache clearing functions for all WP Engine cache layers
  - Intelligent fallback to WordPress transients when object cache unavailable

### Fixed
- **Critical 500 Error Resolution**: Fixed circular dependency in SEO On_Page class causing crashes on WP Engine
  - Resolved infinite loop in URL parsing error logging that caused server crashes
  - Enhanced URL parsing with WP Engine-specific header fallbacks (HTTP_X_ORIGINAL_URL, HTTP_X_REWRITE_URL)
  - Added multiple layers of error handling to prevent system failures
  - Improved graceful degradation when URL parsing encounters issues
- **Missing Class Import**: Fixed "Cache_Manager not found" error in SEO_Manager class
  - Added missing namespace import for BRAGBookGallery\Includes\Extend\Cache_Manager
  - Resolved all Cache_Manager method calls throughout SEO functionality
- **Custom CSS Duplication**: Fixed custom CSS being output multiple times per page
  - Eliminated duplicate CSS injection from carousel shortcode handler
  - Centralized all custom CSS injection through Asset_Manager for consistency
  - Improved deduplication logic to prevent circular CSS output

### Enhanced
- **WP Engine Compatibility**: Comprehensive improvements for WP Engine hosting environments
  - Enhanced rewrite rules handling with automatic WP Engine cache clearing
  - Improved error resilience for managed hosting constraints
  - Multiple server environment detection methods for better compatibility
- **Error Handling**: Robust error handling and logging improvements
  - Prevented circular dependencies in error logging systems
  - Enhanced graceful degradation for component failures
  - Improved debugging capabilities for production environments

## [3.2.4] - 2025-09-08

### Added
- **New Settings Features**:
  - "Expand Navigation Menus" toggle in General Settings (default: false)
  - "Show Filter Counts" toggle in General Settings (default: true)
  - **"Enable Favorites" toggle in General Settings (default: true)** - Allows administrators to completely disable favorites functionality site-wide
  - Comprehensive Changelog page to admin settings
  - Created comprehensive CHANGELOG.md file in plugin root
- **Testing Framework**:
  - Comprehensive end-to-end testing framework with Playwright
  - PHPUnit testing configuration for unit and integration tests
  - Four complete test suites covering all major gallery functionality
  - Mock API responses for realistic testing scenarios
  - Responsive design testing across multiple viewports (desktop, tablet, mobile)

### Fixed
- **Admin Interface**: Fixed changelog tab navigation not showing as active when visiting changelog page
  - Added missing page slug mapping for `brag-book-gallery-changelog` in Settings_Base navigation system
  - Changelog tab now correctly highlights as active when viewing version history
- **Testing Framework**: Fixed Playwright test syntax errors across all test suites
  - Fixed invalid CSS selector syntax `button:has-text("text" i)` → proper Playwright `filter({ hasText: /text/i })` syntax
  - Fixed regex text locator syntax `text=/pattern/i` → `getByText(/pattern/i)` approach
  - Resolved CSS parsing errors in case detail view, favorites functionality, and gallery cases view tests
  - All 31 end-to-end tests now pass successfully

### Enhanced
- **Settings Interface**:
  - Navigation filter menus can now be expanded by default when users load the gallery page
  - Filter counts can be hidden for cleaner navigation appearance
  - Enhanced admin interface with new toggle controls using established design patterns
- **Favorites System Control**:
  - Conditional rendering of favorites buttons throughout gallery, carousel, and case views
  - Automatic disabling of `/myfavorites/` page routing when favorites are disabled
  - Centralized favorites setting management with Settings_Helper class and static caching for performance
- **Test Coverage**: Enhanced comprehensive test coverage for gallery functionality:
  - Gallery Cases View Tests: 7 tests covering grid display, images, interactions, load more, procedures, empty states, and responsive design
  - Carousel Functionality Tests: 8 tests covering navigation, dots, autoplay, case information, mobile responsiveness, and touch gestures
  - Case Detail View Tests: 8 tests covering modal display, comprehensive information, high-quality images, demographics, case notes, action buttons, responsiveness, and error states
  - Favorites Functionality Tests: 8 tests covering favorite buttons, toggle states, localStorage persistence, favorites page display, empty states, management actions, user sync, and mobile responsiveness
- **Documentation**:
  - Complete version history now accessible in admin settings
  - Detailed changelog with categorized changes and GitHub integration
  - Updated settings page changelog to reflect testing framework improvements
  - Enhanced test documentation with detailed coverage descriptions

## [3.2.3] - Previous Release

### Added
- Enhanced debug tools styling
- API endpoints improvements
- Admin interface enhancements
- Gallery Checker with card-based configuration display
- Rewrite Debug with modern table styling
- Cache Management with individual item deletion

### Fixed
- jQuery conflicts resolved in admin area
- Factory reset redirect to correct settings page
- Custom notice rendering system for controlled placement

## [3.2.2] - Previous Release

### Enhanced
- Debug Tools Suite with comprehensive system information
- Settings organization with improved tab navigation
- Modern card-based layouts for status displays
- HTML5 dialog elements replacing browser confirm/alert

## [3.2.1] - Previous Release

### Added
- Custom CSS Management with Monaco Editor integration
- Advanced code editing features with IntelliSense and syntax highlighting
- Real-time CSS validation and linting
- CSS formatting tools with dedicated "Format CSS" button

### Fixed
- Prevention of duplicate CSS output across shortcodes
- Centralized CSS injection via Asset_Manager class
- Security sanitization to prevent XSS attacks

## [3.2.0] - Previous Release

### Added
- Complete frontend rewrite with modern JavaScript modules
- Comprehensive favorites functionality with localStorage and API sync
- Progressive loading with "Load More" button functionality
- Multi-select filtering with badge display
- Mobile-responsive design with hamburger menu
- Carousel improvements with HTML output matching design specifications
- Nudity warning with blur effect and proceed button

### Enhanced
- Case view improvements with adaptive card layout using flexbox
- Detail cards automatically size based on content (1-4 columns)
- Accessibility improvements (ARIA labels, roles)
- ES6+ JavaScript with promises for dialog handling

### Technical
- WordPress VIP coding standards compliance
- PHP 8.2+ with modern match expressions
- Automatic gallery page detection and deletion during factory reset
- Rewrite rules management with debug tools

---

**Legend:**
- 🆕 **Added**: New features
- 🔧 **Changed**: Changes in existing functionality
- 🚀 **Enhanced**: Improvements to existing features
- 🐛 **Fixed**: Bug fixes
- 🔒 **Security**: Security improvements
- ⚠️ **Deprecated**: Soon-to-be removed features
- 🗑️ **Removed**: Removed features

For more detailed information about each release, please visit the [GitHub repository](https://github.com/bragbook2/brag-book-gallery).
