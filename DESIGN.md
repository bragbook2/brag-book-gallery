---
name: BRAG book Gallery
description: Two documented realms — an achromatic gallery that survives any host theme, and a crimson-branded WordPress admin.
colors:
  ink-black: "#030712"
  ink-gray: "#4a5565"
  light-gray: "#e5e7eb"
  lighter-gray: "#f3f4f6"
  lightest-gray: "#f9fafb"
  white: "#FFFFFF"
  brag-crimson: "#CC0000"
  slate-900: "#0f172a"
  slate-800: "#1e293b"
  slate-700: "#334155"
  slate-600: "#475569"
  slate-500: "#64748b"
  slate-400: "#94a3b8"
  slate-300: "#cbd5e1"
  slate-200: "#e2e8f0"
  slate-100: "#f1f5f9"
  slate-50: "#f8fafc"
  blue-500: "#3b82f6"
  green-700: "#15803d"
  red-600: "#dc2626"
  amber-500: "#f59e0b"
  skeleton-base: "#f0f0f0"
  skeleton-sheen: "#e0e0e0"
typography:
  display:
    fontFamily: "Poppins, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "48px"
  headline:
    fontFamily: "Poppins, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "44px"
  title:
    fontFamily: "Poppins, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "24px"
    fontWeight: 600
  body:
    fontFamily: "Poppins, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "16px"
    fontWeight: 400
  label:
    fontFamily: "Poppins, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    letterSpacing: "0.05em"
  admin-body:
    fontSize: "14px"
    fontWeight: 400
    lineHeight: "1.25rem"
  admin-label:
    fontSize: "14px"
    fontWeight: 500
  admin-mono:
    fontFamily: "Consolas, Monaco, 'Courier New', monospace"
    fontSize: "14px"
    lineHeight: "1.5"
rounded:
  gallery: "4px"
  gallery-pill: "10em"
  admin-sm: "2px"
  admin: "4px"
  admin-md: "6px"
  admin-lg: "8px"
  admin-panel: "0.8rem"
  admin-full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  2xl: "24px"
  3xl: "28px"
  4xl: "32px"
  5xl: "36px"
  6xl: "40px"
  7xl: "44px"
  8xl: "48px"
components:
  button-primary:
    backgroundColor: "{colors.ink-black}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.gallery}"
    padding: "16px 24px"
  button-primary-hover:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink-black}"
  button-load-more:
    backgroundColor: "{colors.white}"
    textColor: "{colors.slate-500}"
    rounded: "{rounded.admin-lg}"
    padding: "12px 32px"
  button-load-more-hover:
    backgroundColor: "{colors.lightest-gray}"
  button-nudity-reveal:
    backgroundColor: "{colors.ink-black}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.gallery}"
    padding: "12px 16px"
  icon-button:
    backgroundColor: "transparent"
    textColor: "{colors.white}"
    rounded: "{rounded.gallery-pill}"
    width: "40px"
    height: "40px"
  case-card-link:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.gallery}"
  admin-button-primary:
    backgroundColor: "{colors.slate-900}"
    textColor: "{colors.white}"
    typography: "{typography.admin-label}"
    rounded: "{rounded.admin-md}"
    padding: "8px 16px"
  admin-button-primary-hover:
    backgroundColor: "{colors.slate-800}"
  admin-button-secondary:
    backgroundColor: "{colors.white}"
    textColor: "{colors.slate-700}"
    typography: "{typography.admin-label}"
    rounded: "{rounded.admin-md}"
    padding: "8px 16px"
  admin-button-danger:
    backgroundColor: "{colors.red-600}"
    textColor: "{colors.white}"
    typography: "{typography.admin-label}"
    rounded: "{rounded.admin-md}"
    padding: "8px 16px"
  admin-card:
    backgroundColor: "{colors.white}"
    textColor: "{colors.slate-700}"
    rounded: "{rounded.admin-lg}"
    padding: "24px"
  admin-input:
    backgroundColor: "{colors.white}"
    textColor: "{colors.slate-900}"
    typography: "{typography.admin-body}"
    rounded: "{rounded.admin-md}"
    padding: "8px 12px"
  admin-tab-active:
    backgroundColor: "{colors.white}"
    textColor: "{colors.brag-crimson}"
    typography: "{typography.admin-label}"
---

# Design System: BRAG book Gallery

## Overview

**Creative North Star: "The Clinical Portfolio"**

The result photography is the only thing allowed to be loud. Everything else is a museum wall behind it: near-achromatic, evenly lit, dimensionally quiet. A visitor scanning a procedure gallery is comparing clinical outcomes, and any color the interface spends competes directly with skin tone, bruising, and healing — the exact signal they came to read. So the gallery realm holds Ink Black, four grays, and white, and nothing else. That restraint is not minimalism as taste; it is the product working.

The system is deliberately two realms, and they are not required to converge. The **gallery realm** (`.brag-book-gallery-wrapper`, tokens namespaced `--wp--custom--brag-book-gallery--*`) renders inside an unknown host theme and must be legible against any of them; it self-scopes every custom property, sets `box-sizing` on its own subtree, and re-declares the same token block on the globally-printed nudity warning so no `var()` resolves to nothing outside the wrapper. The **admin realm** (`.brag-book-gallery-admin-wrap`, tokens `--slate-*`, `--space-*`, `--text-*`) sits inside WordPress admin and is BRAG book's own product surface: confident and branded, with BRAG Crimson carrying identity and active state. Crimson is admin-only by decision, not by accident.

Poppins is loaded and self-hosted for the gallery realm only, and it is defeasible: a `.disable-custom-font` class on the wrapper hands typography back to the host theme. The admin realm inherits WordPress's own font stack and never loads a webfont.

**Key Characteristics:**

- Achromatic gallery, branded admin — a documented split, not drift.
- Every gallery value is a custom property with an inline fallback, because the host theme cannot be trusted to have loaded anything.
- Four-pixel corner radius throughout the gallery; nothing rounder than a pill on icon buttons.
- Uppercase, letter-spaced labels are the gallery's only typographic ornament.
- Two-space-unit scales that happen to share the same numbers: gallery in `px`, admin in `rem`.

## Colors

Two palettes with no overlap by design: the gallery realm is achromatic, the admin realm is a full Tailwind-derived slate system with semantic accents.

### Primary

- **Ink Black** (`#030712`): The gallery's single working color. Body copy, links (including their hover state — links do not change color), primary button fill and border, checked checkbox fill, nudity-reveal button, sidebar titles. It is not pure black; the slight blue cast keeps it from vibrating against warm imagery.
- **BRAG Crimson** (`#CC0000`): The admin realm's brand accent. Active tab label and its 2px underline bar, loading spinner arc, section emphasis. It is the plugin identifying itself inside someone else's dashboard.

### Secondary

- **Slate 900 / 800** (`#0f172a` / `#1e293b`): Admin primary button fill and its hover, and the darkest admin text. Buttons are slate, not crimson — crimson marks *where you are*, slate marks *what you can do*.
- **Blue 500** (`#3b82f6`): Admin focus ring, applied globally as `outline: 2px solid` with `2px` offset on `*:focus-visible`. This is the only realm with a systematic focus treatment.

### Tertiary

- **Green 700** (`#15803d`), **Red 600** (`#dc2626`), **Amber 500** (`#f59e0b`): Admin status semantics only — sync success, error, warning. Each has a 50/100 tint for notice backgrounds. Never appear in the gallery realm.

### Neutral

- **Ink Gray** (`#4a5565`): Gallery secondary text, filter-option hover label.
- **Light Gray** (`#e5e7eb`): Gallery card-summary borders and dividers.
- **Lighter Gray** (`#f3f4f6`): Gallery checkbox border at rest, sidebar inset edge.
- **Lightest Gray** (`#f9fafb`): Gallery quiet surfaces, Load More hover fill.
- **White** (`#FFFFFF`): Gallery surface, sidebar, dialog, primary-button hover fill.
- **Slate 50–300** (`#f8fafc`→`#cbd5e1`): Admin surfaces, borders, and dividers. Slate 400–700 carry admin text at four weights of emphasis.
- **Skeleton Base / Sheen** (`#f0f0f0` / `#e0e0e0`): The only place the gallery uses a gradient — a 90° three-stop sweep animating loading placeholders.

### Named Rules

**The Achromatic Gallery Rule.** No hue enters the gallery realm. The one exception is the favorited heart, which fills `red`, and the skeleton sheen, which is gray. If a new gallery element wants color, the answer is weight, size, or space instead.

**The Crimson Stays Home Rule.** BRAG Crimson is admin chrome. It does not cross into `.brag-book-gallery-wrapper`.

**The Fallback-Or-It-Didn't-Happen Rule.** Every `var()` in the gallery realm carries an inline fallback (`var(--…--rounded, 4px)`), because the wrapper's tokens are scoped to the wrapper and gallery markup can be printed outside it. *Known defect, not doctrine:* `--wp--custom--brag-book-gallery--color--primary` is referenced six times (loading spinner, cases-grid focus outline and active fill, procedures-list link) and is **declared nowhere in the plugin** — only its `#CC0000` fallback ever paints. That is an accidental crimson leak into an achromatic realm, and it should be resolved by removing the reference or declaring an achromatic token, not by declaring it crimson.

## Typography

**Gallery Font:** Poppins, self-hosted woff2 at 400/600 (roman + italic), `font-display: swap`, falling back to `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif`.
**Admin Font:** Inherited from WordPress admin. No webfont is loaded.

**Character:** Poppins' geometric roundness reads as clean and contemporary without a clinical chill; at 600 it holds uppercase labels tightly, which is where the gallery's only real typographic personality lives. Only two weights exist — regular (400) and semibold (600) — plus a bold (700) token that the gallery scale defines and barely spends.

### Hierarchy

- **Display** (`h1`, 48px desktop / 32px below 1024px): Gallery page and procedure titles. Weight is inherited from the host theme, not asserted.
- **Headline** (`h2`, 44px desktop / 28px below 1024px): Section headings within a gallery view.
- **Title** (`h3`–`h5`, 24px, 600): Sidebar title, dialog title, card group headings. Flattened deliberately — three heading levels share one size, and hierarchy below Headline is carried by position and weight rather than by scale.
- **Body** (16px, 400): Case descriptions, filter labels, form copy.
- **Label** (12px, 600, `0.05em`, uppercase): Buttons, grid-size selector, badges. The gallery's signature.
- **Admin Body** (14px, 400, 20px line-height): The admin realm's default. Admin runs one step smaller than the gallery throughout.
- **Admin Mono** (`Consolas, Monaco, 'Courier New', monospace`, 14px): The Custom CSS editor only. The one place in either realm where a monospace face is doing real work rather than costume.

The gallery type scale runs `12 · 14 · 16 · 18 · 20 · 24 · 28 · 32 · 36 · 40 · 44 · 48px` in 4px steps above 20px — an additive scale, not a ratio. The admin scale is the Tailwind ramp in `rem` (`0.75 · 0.875 · 1 · 1.125 · 1.25 · 1.5 · 1.875 · 2.25 · 3`).

### Named Rules

**The Uppercase-Is-For-Actions Rule.** Uppercase with `0.05em` tracking marks something interactive or structural — a button, a grid-size label, a badge. Never a heading, never body copy.

**The Borrowed-Font Rule.** Poppins is a default, not a requirement. `.disable-custom-font` on the wrapper must leave every layout intact; nothing may depend on Poppins' metrics.

## Layout

The gallery is a two-column shell: a flex `.brag-book-gallery-container` at `min-height: 100vh` with a filter sidebar and the case grid. The sidebar is a full-height off-canvas drawer below 1280px — fixed, `translateX(-100%)`, sliding in on `.brag-book-gallery-active` — and an inline column at 1280px and up, capped at `50%` from 512px and `382px` from 1024px, separated by an inset 1px edge rather than a border.

The case grid is mobile-first: 1 column, 2 at 576px, 2 at 768px, 3 at 1280px, with `data-columns="2|3"` allowed to override only from 768px up (and pinned to 2 at 768px regardless). Gap climbs with the breakpoint (24px → 32px). `grid-auto-flow: dense` fills holes; description and notes blocks span `1 / -1`. Grid-template changes are transitioned over `0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94)` so a column-count change animates rather than snaps.

Card internals use **container queries**, not viewport queries: `.brag-book-gallery-case-card-summary` is an `inline-size` container that reflows its info/details rows at `400px`, so a card behaves correctly at any column count or sidebar width. The nudity overlay uses `container-type: size` — safe there specifically because the overlay is absolutely positioned and its height never derives from its content.

Spacing is a 4px-based scale (`4 · 8 · 12 · 16 · 20 · 24 · 28 · 32 · 36 · 40 · 44 · 48px`) with multiples expressed as `calc(N * var(--…--spacer))` rather than new tokens. The admin uses the Tailwind `rem` scale with the same effective values, plus `--space-320` (80rem / 1280px) as the admin container cap.

Observed breakpoints, in frequency order: `768px` (the dominant mobile/tablet split), `1280px` (sidebar and 3-column desktop), `600px` / `782px` (WordPress admin's own), `1024px`, `640px`, `576px`, `512px`, `480px`, `400px`.

### Named Rules

**The Container-Query-First Rule.** A card and its internals respond to their own width, never to the viewport. Only page-level structure — sidebar, grid column count — reads breakpoints.

**The 1280 Rule.** The desktop gallery begins at 1280px, not 1024px. Below it, the sidebar is a drawer and the grid stays at 2 columns.

## Elevation & Depth

The gallery realm is **shadow-at-rest**, and that is preserved as-is: `shadow-lg` (`0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05)`) sits on every case-card link and card summary by default, with the sidebar drawer and dialog carrying the same. In a plugin whose output lands inside an unknown host theme, resting elevation is how a gallery surface asserts its own plane against whatever background it was dropped onto. Depth is structural here, not atmospheric. **Do not flatten the gallery.**

Hover adds elevation rather than replacing it — the primary button inverts to white and gains `shadow-lg`; Load More steps from `shadow-sm` to `shadow-md`.

The admin realm is quieter: cards rest at `shadow` and lift to `shadow-md` on hover, the admin container sits at `shadow-sm`, and the sticky tab bar at `shadow-sm`. Depth there is a response to interaction, not a permanent state.

### Shadow Vocabulary

- **sm** (`0 1px 2px 0 rgb(0 0 0 / 0.05)`): Admin container, tab bar, Load More at rest.
- **base** (`0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px 0 rgb(0 0 0 / 0.06)`): Admin card at rest.
- **md** (`0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06)`): Admin card hover, Load More hover.
- **lg** (`0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -2px rgb(0 0 0 / 0.05)`): Gallery cards, sidebar drawer, dialog, primary-button hover.
- **xl** (`0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04)`): Defined in both realms, effectively unused.
- **inset edge** (`inset -1px 0 0 <lighter-gray>`): The sidebar's separator — a shadow doing a border's job so it never participates in layout.

Two blur surfaces exist: the nudity warning (`rgba(255,255,255,.75)` + `backdrop-filter: blur(16px)`) and the dialog backdrop (a 45° `rgb(0 0 0 / .85)`→`.65` gradient + `blur(4px)`).

### Named Rules

**The Own-Plane Rule.** Gallery surfaces carry `shadow-lg` at rest because they must read as the plugin's plane inside a theme that owns everything around them. Flat-at-rest is correct for admin and wrong for the gallery.

## Shapes

One radius does nearly all the work in the gallery: **4px** (`--…--rounded`), on cards, card summaries, checkboxes, buttons, the nudity overlay, and badges. The only departures are the pill (`10em`) on circular icon buttons — favorite and share, both 40px squares — and the dialog, which uses the 12px spacer token as its radius, making it the single most rounded surface in the realm.

Borders are sparing and always 1px except the primary button's 2px, which is drawn in the same color as its fill so the button reads as a solid block at rest and as an outline after the hover inversion. Card summary cells carry 1px `light-gray` borders and selectively drop edges via container query (`border-bottom: 0` below 400px) so adjoining cells never double up.

The admin realm runs a fuller radius ramp: 6px on controls and buttons, 8px on cards, and `0.8rem` on the outer container — a hand-tuned value outside the ramp, and the only one.

Checkboxes are fully custom: `appearance: none`, 20px, 4px radius, filled Ink Black when checked with an inline SVG tick as a `background-image` at 80% size.

### Named Rules

**The Four-Pixel Rule.** Gallery corners are 4px. A new gallery surface does not get a new radius; if it needs to feel softer, it is a pill or it is the dialog.

## Components

### Buttons

- **Shape:** 4px corners (gallery) / 6px (admin).
- **Primary (gallery):** Ink Black fill, white label, 2px Ink Black border, `16px 24px` padding, 14px semibold uppercase at `0.05em`. Marked `!important` on color, background, border, and radius — deliberate, since host themes routinely style bare `button`.
- **Hover / Focus:** Full inversion to white fill with Ink Black label, plus `shadow-lg`, over `all 0.2s cubic-bezier(0.4, 0, 0.2, 1)`. Disabled drops to `0.6` opacity with `not-allowed`.
- **Load More:** A deliberately quieter sibling — white fill, Slate 500 label, 1px `light-gray` border, 8px radius, 15px medium, `12px 32px`. It reads as continuation, not as a decision.
- **Icon buttons (favorite / share):** 40px transparent circles with white 24px SVGs, sitting at `z-index: 30` over card imagery. Favorited state fills the heart `red`. *Gap: `:focus { outline: none }` with no replacement — the only unfocusable-looking control in the system.*
- **Admin:** Slate 900 fill / Slate 800 hover for primary; white with Slate 300 border for secondary; Red 600 for danger with a Red 300 disabled state. All three carry `outline: 2px solid` Blue 500 at `2px` offset on `:focus-visible`.

### Chips / Badges

- **Distance badge:** `rgba(0,0,0,0.75)` pill-less 4px block, white 12px medium at `line-height: 1`, `4px 8px`, absolutely positioned 8px from the card's top-left, `pointer-events: none`.

### Cards / Containers

- **Case card link:** 4px radius, `overflow: hidden`, `shadow-lg`, `z-index: 0` so the overlay and action buttons stack above it.
- **Case card summary:** A flex row that becomes a stack below its own 400px container width; 1px `light-gray` cell borders, bottom corners rounded to 4px, `8px 12px` padding.
- **Admin card:** White, 1px Slate 200, 8px radius, 24px padding, `shadow` → `shadow-md` on hover.
- **Admin container:** `max-inline-size: 1280px`, white, 24px padding, `0.8rem` radius, `shadow-sm`.

### Inputs / Fields

- **Gallery checkbox:** Custom 20px control described in Shapes. Focus is a 3px `rgba(17,24,39,.1)` ring via `box-shadow` after `outline: none` — soft, and the gallery's only focus affordance.
- **Admin fields:** 1px Slate 300, 6px radius, `8px 12px`, 14px, white. Hover deepens the border to Slate 400; focus draws the Blue 500 2px outline at `-1px` offset and shifts the border to match. Selects are `appearance: none` with an inline SVG chevron 12px from the right edge; number spinners are stripped in both engines.

### Navigation

- **Filter sidebar:** White, 24px padding (16px below 480px), inset right edge. Filter groups are native `<details>`; options animate open via an `expandDown` keyframe to `max-height: 300px` over `0.3s cubic-bezier(0.4, 0, 0.2, 1)`. Options are 12px/16px rows whose label shifts to Ink Gray on hover.
- **Admin tabs:** Sticky at `top: 2rem`, Slate 50 bar bled to the container edge with 1px Slate 200 top and bottom borders. Active tab goes white with a BRAG Crimson label and a 2px crimson underline drawn as an `::after`. Wraps to 50% items at 768px and full-width at 640px.

### Nudity Warning

The system's signature component and a product requirement, not a decoration. A full-bleed absolute overlay at `rgba(255,255,255,.75)` with `backdrop-filter: blur(16px)`, 4px radius matching the card beneath, centered title (18px semibold) / caption (12px) / reveal button (Ink Black, uppercase 12px). It is a size container so the content can respond to overlay height, and its token block is re-declared on `.brag-book-gallery-nudity-warning--global` because the global instance prints in `wp_footer` outside the wrapper's scope.

### Loading Skeletons

A 90° `#f0f0f0 25% → #e0e0e0 50% → #f0f0f0 75%` gradient sweep — the only gradient the gallery realm permits, and the only place `#f0f0f0`/`#e0e0e0` appear.

## Do's and Don'ts

### Do:

- **Do** scope every new gallery custom property to `.brag-book-gallery-wrapper` **and** to any element printed outside it, exactly as `_wrapper.scss` does for the global nudity warning.
- **Do** give every gallery `var()` an inline fallback matching the token's declared value.
- **Do** reach for a container query when a card or its internals need to respond; reserve media queries for sidebar and grid structure.
- **Do** keep gallery surfaces at `shadow-lg` at rest — that resting elevation is how the plugin holds its own plane inside a host theme.
- **Do** express spacing multiples as `calc(N * var(--…--spacer))` rather than minting a new token.
- **Do** use `outline: 2px solid var(--blue-500)` at `2px` offset for every new admin focus state; the global `*:focus-visible` rule already establishes it.
- **Do** treat Poppins as removable — `.disable-custom-font` must leave layout intact.

### Don't:

- **Don't** introduce a hue into the gallery realm. Weight, size, and space carry emphasis there.
- **Don't** use BRAG Crimson outside `.brag-book-gallery-admin-wrap`.
- **Don't** reference `--wp--custom--brag-book-gallery--color--primary`; it is declared nowhere and its `#CC0000` fallback is an unintended leak. Remove the reference or declare an achromatic token instead.
- **Don't** flatten the gallery to a state-driven shadow model. That rule belongs to the admin realm only.
- **Don't** add a third radius to the gallery. 4px, or the icon-button pill, or the dialog's 12px.
- **Don't** kill focus with `outline: none` unless the same rule supplies a replacement ring — the favorite and share buttons currently violate this and should be fixed, not copied.
- **Don't** set uppercase on headings or body copy; uppercase marks actions and structural labels.
- **Don't** assume the host theme supplies a reset, a font, a container width, or a heading scale.
