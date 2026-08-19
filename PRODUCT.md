# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two confirmed audiences. The WordPress admin operator leads; the public gallery visitor is secondary but real.

**Primary — practice staff or agency admin.** Works inside WP admin on behalf of a medical/cosmetic practice. Jobs: enter the API token and website property ID, run or schedule a sync, place shortcodes, tune columns / items-per-page / image display mode / fonts, add custom CSS, and diagnose why a gallery page is not showing what they expect. Often managing several client sites, not one, and returning to the screens episodically rather than living in them.

**Secondary — prospective patient.** Lands on a practice's public site and browses before/after cases for a procedure they are considering, filtering by procedure category and provider, searching, saving favorites, and opening a case detail. Success is confidence enough to contact the practice.

## Product Purpose

Display before-and-after photo galleries for medical and cosmetic procedures on a WordPress site, with the case data, procedure taxonomy, and images synced from the practice's existing BRAG book account rather than maintained by hand in WordPress.

Success for the admin: an accurate, current gallery standing up on the client's site with minimal ongoing upkeep. Success for the visitor: finding relevant, credible results for their procedure.

## Positioning

Two claims a generic WordPress gallery plugin could not truthfully make:

1. **Synced from the source of record.** Cases, procedure categories, providers, and images come from the practice's BRAG book application account. WordPress is a display surface, not the content system; there is no manual gallery upkeep.
2. **Medical before/after is native, not bolted on.** Procedure taxonomy, before/after image pairing, provider scoping, sensitive-content warning, and per-case SEO are structural parts of the product rather than configuration on top of a general-purpose gallery.

## Operating Context

- Runs as a WordPress plugin (requires WP 6.8+, PHP 8.2+) inside whatever theme the practice already runs.
- Admin work happens across a set of WP admin pages under a **BRAG book** menu: Dashboard, General Settings (incl. Custom CSS via Monaco), API Settings, API Test, Sync, Mode/Default, Communications, Debug, Help, Changelog.
- Frontend work happens through shortcodes placed on pages: `[brag_book_gallery]` (context auto-detecting, optional `provider`/`provider_id`), `[brag_book_gallery_cases]`, `[brag_book_gallery_procedures]`, `[brag_book_gallery_case]`, `[brag_book_gallery_favorites]`, `[brag_book_carousel]`.
- Depends on an external service (`app.bragbookgallery.com`) for sync; an active account and API token are required for the plugin to do anything.
- Data flows one direction for content: the service returns case data and image URLs stored locally in WP. No visitor data is sent to the service; case view tracking uses the site's own REST endpoints.

## Capabilities and Constraints

**Confirmed capabilities:** before/after gallery display (side-by-side and slider comparison), procedure category and subcategory filtering, provider-scoped galleries and archives, autocomplete search, visitor favorites, embeddable carousels, Load More pagination that stays inside the active provider/location/procedure context, automatic and remotely-triggered sync, SEO meta / Open Graph / XML sitemap entries for gallery pages, nudity/sensitive-content warning overlay, per-site configuration of columns, items-per-page, image display mode, fonts, and custom CSS.

**Durable constraints future design must not break:**

- **Theme-resistant output.** Gallery markup renders inside arbitrary client themes. Styles must be self-contained and must not assume the host theme's resets, typography, container widths, or color variables.
- **Sensitive content handling.** Before/after medical imagery keeps the warning overlay and its discretion affordances. Never design a state that exposes sensitive imagery ahead of the visitor's choice.
- **Per-site customization survives.** Configurable columns, items-per-page, image display mode, fonts, and custom CSS are real settings visitors of the admin have set. Design may not hardcode values that override them.

**Explicitly not asserted as binding:** stock WordPress-admin visual language. The admin surfaces were not pinned to native WP chrome, so a departure is open for discussion rather than forbidden — but WP admin's structural affordances (menu placement, notices, capability model, form semantics) are part of the platform, not styling.

**Undecided / not established:** performance budgets, supported locale set, and any browser floor beyond what WordPress 6.8 itself supports.

## Brand Commitments

- Product name is **BRAG book Gallery**; the parent service is the **BRAG book application** (bragbookgallery.com). The lowercase "book" in "BRAG book" is the established spelling.
- Domain vocabulary is fixed and appears in both UI and code: *case*, *procedure*, *provider*, *before/after*, *favorites*, *sync*, *website property ID*.
- GPLv2-or-later, distributed as a WordPress plugin; WordPress coding standards (PHPCS, VIP) and PHPStan are enforced in the repo.

## Evidence on Hand

- Repo docs: `README.md` / `readme.txt` (feature and shortcode truth), `CLAUDE.md` (architecture map), `settings.md` (settings reference), `mapping.md`, `CHANGELOG.md`.
- Incumbent visual system in code: `src/scss/` (`frontend.scss`, `admin.scss`, `components/`, `settings/`, `structure/`), built to `assets/`.
- Frontend behavior in `src/js/modules/` (filter system, carousel, dialog, favorites, mobile menu, search autocomplete, share).
- Playwright e2e config and `playwright-report/` exist in the repo.
- **Absent — do not fabricate:** no customer names, testimonials, case studies, press, pricing, install counts, or performance benchmarks are on hand. Sample before/after imagery belongs to real practices and is not ours to invent or substitute.

## Product Principles

1. **The source of record is the BRAG book app.** WordPress displays; it does not become a second place to author or edit case content.
2. **Design for the operator returning after weeks away.** Admin screens are used episodically and across several client sites — state, errors, and sync outcomes must be legible on arrival, not remembered.
3. **Survive the host theme.** Frontend output owns its own styling and layout assumptions completely.
4. **Discretion before display.** Sensitive clinical imagery is shown on the visitor's terms; the warning is a product feature, not friction to design away.
5. **Configuration is a contract.** Every setting the plugin exposes is a promise the rendered output keeps.

## Accessibility & Inclusion

No product-specific standard was established with the user. Repo scan found no `prefers-reduced-motion`, `aria-live`, or dialog `role` usage in `src/`, so the accessibility posture of the dialog, carousel, filter, and search interactions is currently unverified rather than known-good — treat it as an open audit item, not a recorded requirement.
