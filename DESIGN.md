---
name: SiTA
description: Sistem Informasi Tugas Akhir, Universitas Bumigora. A calm blue ledger of thesis progress for students, lecturers and the head of programme.
colors:
    primary: '#2563eb'
    primary-foreground: '#ffffff'
    primary-muted: '#edf2fe'
    primary-muted-foreground: '#2563eb'
    primary-dark: '#60a5fa'
    primary-foreground-dark: '#0f172a'
    primary-muted-dark: '#1a2a3d'
    primary-muted-foreground-dark: '#93c5fd'
    background: '#f8fafc'
    background-dark: '#0b0f14'
    foreground: '#0f172a'
    foreground-dark: '#eaf2f7'
    card: '#ffffff'
    card-dark: '#11161c'
    muted: '#f1f5f9'
    muted-dark: '#151c23'
    muted-foreground: '#5a6a80'
    muted-foreground-dark: '#9fb0bd'
    accent: '#e0ecff'
    accent-dark: '#17202a'
    accent-foreground: '#1e3a5f'
    accent-foreground-dark: '#c2d0da'
    border: '#e2e8f0'
    border-dark: '#202a35'
    sidebar: '#f8fafc'
    sidebar-dark: '#0f141a'
    destructive: 'oklch(0.57 0.2 27)'
    destructive-foreground: 'oklch(0.98 0 0)'
    success: 'oklch(0.5 0.12 160)'
    success-foreground: 'oklch(0.98 0.01 160)'
    success-muted: 'oklch(0.95 0.05 160)'
    success-muted-foreground: 'oklch(0.4 0.1 160)'
    success-dark: 'oklch(0.7 0.15 160)'
    success-foreground-dark: 'oklch(0.2 0.04 160)'
    success-muted-dark: 'oklch(0.3 0.06 160)'
    success-muted-foreground-dark: 'oklch(0.85 0.12 160)'
    warning: 'oklch(0.8 0.16 80)'
    warning-foreground: 'oklch(0.25 0.05 70)'
    warning-muted: 'oklch(0.96 0.07 90)'
    warning-muted-foreground: 'oklch(0.42 0.09 70)'
    warning-dark: 'oklch(0.8 0.16 80)'
    warning-foreground-dark: 'oklch(0.22 0.05 70)'
    warning-muted-dark: 'oklch(0.32 0.06 80)'
    warning-muted-foreground-dark: 'oklch(0.88 0.12 85)'
    danger: 'oklch(0.55 0.2 27)'
    danger-foreground: 'oklch(0.98 0 0)'
    danger-muted: 'oklch(0.95 0.04 20)'
    danger-muted-foreground: 'oklch(0.45 0.18 27)'
    danger-dark: 'oklch(0.68 0.19 25)'
    danger-foreground-dark: 'oklch(0.18 0.03 25)'
    danger-muted-dark: 'oklch(0.32 0.09 25)'
    danger-muted-foreground-dark: 'oklch(0.85 0.1 25)'
    info: 'oklch(0.5 0.13 235)'
    info-foreground: 'oklch(0.98 0.01 235)'
    info-muted: 'oklch(0.95 0.04 235)'
    info-muted-foreground: 'oklch(0.42 0.11 240)'
    info-dark: 'oklch(0.72 0.13 235)'
    info-foreground-dark: 'oklch(0.18 0.03 235)'
    info-muted-dark: 'oklch(0.3 0.06 235)'
    info-muted-foreground-dark: 'oklch(0.86 0.09 235)'
typography:
    headline:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '1.5rem'
        fontWeight: 600
        lineHeight: 1.333
        letterSpacing: '-0.025em'
    title:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '1.25rem'
        fontWeight: 600
        lineHeight: 1.4
        letterSpacing: '-0.025em'
    stat:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '1.875rem'
        fontWeight: 700
        lineHeight: 1.2
        letterSpacing: '-0.025em'
    body:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '0.875rem'
        fontWeight: 400
        lineHeight: 1.43
    label:
        fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
        fontSize: '0.75rem'
        fontWeight: 500
        lineHeight: 1.333
rounded:
    sm: '4px'
    md: '6px'
    lg: '8px'
    xl: '12px'
    full: '9999px'
spacing:
    xs: '4px'
    sm: '8px'
    md: '16px'
    lg: '24px'
components:
    button-primary:
        backgroundColor: '{colors.primary}'
        textColor: '{colors.primary-foreground}'
        typography: '{typography.body}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-soft:
        backgroundColor: '{colors.primary-muted}'
        textColor: '{colors.primary-muted-foreground}'
        typography: '{typography.body}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-outline:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        typography: '{typography.body}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    button-destructive:
        backgroundColor: '{colors.destructive}'
        textColor: '{colors.destructive-foreground}'
        typography: '{typography.body}'
        rounded: '{rounded.md}'
        padding: '8px 16px'
        height: '36px'
    card:
        backgroundColor: '{colors.card}'
        textColor: '{colors.foreground}'
        rounded: '{rounded.xl}'
        padding: '24px'
    input:
        backgroundColor: '{colors.background}'
        textColor: '{colors.foreground}'
        typography: '{typography.body}'
        rounded: '{rounded.md}'
        padding: '4px 12px'
        height: '36px'
    status-badge-success:
        backgroundColor: '{colors.success-muted}'
        textColor: '{colors.success-muted-foreground}'
        typography: '{typography.label}'
        rounded: '{rounded.full}'
        padding: '2px 8px'
    status-badge-warning:
        backgroundColor: '{colors.warning-muted}'
        textColor: '{colors.warning-muted-foreground}'
        typography: '{typography.label}'
        rounded: '{rounded.full}'
        padding: '2px 8px'
    status-badge-danger:
        backgroundColor: '{colors.danger-muted}'
        textColor: '{colors.danger-muted-foreground}'
        typography: '{typography.label}'
        rounded: '{rounded.full}'
        padding: '2px 8px'
    status-badge-info:
        backgroundColor: '{colors.info-muted}'
        textColor: '{colors.info-muted-foreground}'
        typography: '{typography.label}'
        rounded: '{rounded.full}'
        padding: '2px 8px'
    filter-pill:
        backgroundColor: '{colors.muted}'
        textColor: '{colors.muted-foreground}'
        typography: '{typography.label}'
        rounded: '{rounded.full}'
        padding: '0 12px'
        height: '28px'
    filter-pill-selected:
        backgroundColor: '{colors.primary}'
        textColor: '{colors.primary-foreground}'
    stat-icon-chip:
        backgroundColor: '{colors.primary-muted}'
        textColor: '{colors.primary-muted-foreground}'
        rounded: '{rounded.lg}'
        size: '40px'
---

# Design System: SiTA

## Overview

**Creative North Star: "The Thesis Ledger"**

SiTA is a record of where each thesis stands, so the interface reads like a well-kept ledger: pale surfaces, hairline borders, one blue, and status colour that means the same thing on every page. It is calm on purpose. A student and a lecturer both need to find the next step in a few seconds, and nothing decorative should compete with that.

The system is the shadcn/ui stack (Radix primitives, `cva` variants, Tailwind v4 tokens) with a domain layer on top: shared page patterns in `resources/js/components/patterns/` and semantic status tokens in `resources/css/app.css`. Pages compose those patterns and tokens. They do not hand-roll status colours or redeclare the patterns. The Filament admin panel at `/admin` is a separate surface that is kept visually paired with the React tokens (see Components).

This file records the incumbent system as it is built, plus the rules that were carried over from the former standalone design-system doc. Where the build drifts from a rule, the rule stands and the drift is listed under Do's and Don'ts as known drift. Product facts (users, tone, brand) live in `PRODUCT.md`.

**Key Characteristics:**

- One default brand colour (UBG Blue) plus three accessible alternative schemes (Indigo, Zaitun, Grafit), each with a light and a dark theme, all chosen by `data-theme` and defined only in `app.css`.
- Semantic status roles (primary, success, warning, danger, info, muted), each defined as four tokens in both themes.
- Tonal layering with hairline borders; a single soft shadow on cards, larger shadows only on floating layers.
- Sentence-case Indonesian labels, 12px minimum, one sans family.
- Desktop first for every role. Mobile only has to be readable and not broken; do not polish it.

## Colors

A cool slate neutral ramp, one saturated blue for action and selection, and four status hues (emerald, amber, red, sky) used only through tokens. Tokens are CSS variables with light and dark values, exposed as Tailwind utilities through `@theme` in `resources/css/app.css`. Read exact values there; do not copy them elsewhere. The frontmatter above records the same values for tooling, with a `-dark` suffix for the dark theme.

### Primary

- **UBG Blue** (`#2563eb`, dark `#60a5fa`): the brand colour. Solid fills (default button, selected filter pill, active calendar button), `text-primary` on page surfaces (links, active states, icons), the focus ring (`--ring`) and the sidebar primary.
- **On UBG Blue** (`#ffffff`, dark `#0f172a`): text on `bg-primary`. Dark primary is a light blue, so its foreground is dark.
- **Blue Tint** (`#edf2fe`, dark `#1a2a3d`) with **Tint Text** (`#2563eb`, dark `#93c5fd`): the tinted surface and text pair for active navigation, avatar initials, soft badges and buttons, and icon chips.

### Neutral

- **Fog Page** (`#f8fafc`, dark `#0b0f14`): page background and light sidebar. Dark sidebar is `#0f141a`.
- **Ink** (`#0f172a`, dark `#eaf2f7`): body text, and the foreground for card and popover.
- **Paper** (`#ffffff`, dark `#11161c`): card and popover surface.
- **Mist** (`#f1f5f9`, dark `#151c23`): muted and secondary surface (pill and table-header fills).
- **Slate Text** (`#5a6a80`, dark `#9fb0bd`): `text-muted-foreground`, secondary copy and labels.
- **Hairline** (`#e2e8f0`, dark `#202a35`): borders and dividers.
- **Input stroke** (`#828d9c`, dark `#5e6771`): `--input`, retained for checkboxes and other compact selection controls at least 3:1 on page and card surfaces. Text fields, textareas, selects and outline buttons use the theme's `--border` hairline following product review #148; this intentionally relaxes their former 3:1 idle-outline contract. Focus and error borders stay distinct.
- **Highlight Blue** (`#e0ecff`, dark `#17202a`) with text `#1e3a5f` (dark `#c2d0da`): hover and focus fill for ghost buttons, menu items and outline hover (`accent`).

### Status roles

| Role      | Hue base     | Solid        | On solid                  | Muted surface      | Text on muted surface           |
| --------- | ------------ | ------------ | ------------------------- | ------------------ | ------------------------------- |
| `primary` | brand blue   | `bg-primary` | `text-primary-foreground` | `bg-primary-muted` | `text-primary-muted-foreground` |
| `success` | emerald      | `bg-success` | `text-success-foreground` | `bg-success-muted` | `text-success-muted-foreground` |
| `warning` | amber        | `bg-warning` | `text-warning-foreground` | `bg-warning-muted` | `text-warning-muted-foreground` |
| `danger`  | red          | `bg-danger`  | `text-danger-foreground`  | `bg-danger-muted`  | `text-danger-muted-foreground`  |
| `info`    | sky/blue     | `bg-info`    | `text-info-foreground`    | `bg-info-muted`    | `text-info-muted-foreground`    |
| `muted`   | neutral gray | `bg-muted`   | `text-muted-foreground`   | (already muted)    | `text-muted-foreground`         |

- Each role defines `--<role>`, `--<role>-foreground`, `--<role>-muted` and `--<role>-muted-foreground` in both `:root` (light) and `.dark`, mapped to `--color-*` in `@theme` of `resources/css/app.css`. Read the values there; do not copy them elsewhere.
- `destructive` (`oklch(0.57 0.2 27)`) is the shadcn destructive token for destructive buttons and invalid-field rings. `chart-1` to `chart-5` (OKLCH, hue 250/200/280/40/90, lighter in dark) are the chart ramp.
- Use opacity modifiers on tokens (`bg-success/10`) when no token fits; never switch to a palette hue.

### Named Rules

**The Default Theme Rule.** UBG Blue (light and dark) is the default theme. The other schemes (Indigo, Zaitun, Grafit) are `[data-theme]` blocks in `app.css`, meet the same contrast contract, and are never written inline by JavaScript. See Colour schemes below.

**The Light Primary In Dark Rule.** Primary is `#2563eb` with white text in light mode, and `#60a5fa` with dark (`#0f172a`) text in dark mode. Dark primary is light on purpose: `text-primary` (links, active states, icons) must stay readable on dark surfaces, and the dark status solids follow the same light-fill/dark-text rule.

**The Tint Pair Rule.** Text on a primary tint uses `bg-primary-muted text-primary-muted-foreground` (active navigation, avatar initials, soft badges and buttons, icon chips). `bg-primary/10 text-primary` measures about 4.5:1 or less in light mode and fails AA once the tint is stronger or the base is not white.

**The Foreground Pairing Rule.** Text on `bg-primary` uses `text-primary-foreground`; a count chip nested in a primary pill is inverted (`bg-primary-foreground text-primary`). Never `text-white`/`bg-white/20` on primary (dark primary is light), and no translucent `primary-foreground/20` chip under white text (it lightens the fill below 4.5:1). In general, pair solid fills with `-foreground`, and muted surfaces with `-muted-foreground`. Browser-resolved sRGB measurements for the 16 success/warning/danger/info light/dark pairs were at least 5.07:1. Recheck contrast when changing values or introducing new combinations.

**The Muted Badge Rule.** Badges and callouts use the muted surface with its `-muted-foreground` text (`bg-success-muted text-success-muted-foreground`). Solid fills (`bg-danger text-danger-foreground`) are for emphasis such as alerts and buttons.

**The Paired Palettes Rule.** The Filament admin cannot load the React stylesheet, so its palettes mirror these tokens. See Components, Filament admin.

### Colour schemes

The user picks a mode (terang, gelap, sistem) and a scheme in one shared component, `ThemePicker` (`resources/js/components/theme-picker.tsx`), used by the public settings dialog (`theme-settings-button.tsx`) and by Pengaturan Akun > Tampilan. A scheme is a pair of CSS blocks in `resources/css/app.css`, `[data-theme='<id>']` and `.dark[data-theme='<id>']`. UBG Blue is the default and lives in `:root` and `.dark`.

| Id         | Label    | Primary                 | Note                                              |
| ---------- | -------- | ----------------------- | ------------------------------------------------- |
| `ubg-blue` | UBG Blue | blue                    | Default and brand.                                |
| `indigo`   | Indigo   | violet-blue, hue 280    | 45 degrees from info (sky).                       |
| `olive`    | Zaitun   | near-neutral grey-green | Chroma below 0.02; selected by the product owner. |
| `graphite` | Grafit   | neutral gray            | No hue, so no status can be confused.             |

Old presets: Emerald (green), Sunset (orange) and Teal (cyan-green) are gone. Their hues sit on the success, warning and info tones, so a primary button or active link would read as a status. Rose (magenta) was removed after product review because it does not suit an academic setting. Saved `emerald`, `sunset`, `teal`, `rose` and unselected review-candidate values fall back to UBG Blue.

The product owner selected Zaitun as the replacement for Rose. Its near-neutral grey-green sits between the blue schemes and Grafit in the picker. It retains the contrast and status-hue contract without exceptions: chroma below 0.02 avoids relying on an exact 120-degree hue surviving sRGB rounding.

**How it is applied.** `app.blade.php` puts `data-theme` on `<html>` from the `sita_theme_preset` cookie (validated against `App\Support\ThemeSchemes::IDS`, unencrypted like `appearance`), and a head script falls back to the same key in `localStorage` before first paint, so there is no colour flash. `use-theme-scheme.ts` stores the choice in both places, sets `data-theme`, and never writes a colour. The login page uses the same root view, so the choice is the same there.

**Required tokens.** Each scheme block defines all of: `background`, `foreground`, `card`, `card-foreground`, `popover`, `popover-foreground`, `primary`, `primary-foreground`, `primary-muted`, `primary-muted-foreground`, `secondary`, `secondary-foreground`, `muted`, `muted-foreground`, `accent`, `accent-foreground`, `border`, `input`, `ring`, and the sidebar set (`sidebar`, `sidebar-foreground`, `sidebar-primary`, `sidebar-primary-foreground`, `sidebar-accent`, `sidebar-accent-foreground`, `sidebar-border`, `sidebar-ring`). Status, destructive and chart tokens are shared. Use six-digit hex so the contrast test can read them.

**Contrast contract** (every scheme, light and dark; `tests/Unit/ThemeContrastTest.php` computes WCAG ratios from the CSS):

- Text, at least 4.5:1: `foreground` on `background`, `card` and `muted`; `muted-foreground` on `background`, `card`, `muted` and `sidebar`; `primary` on `background` and `card`; `primary-foreground` on `primary`; `primary-muted-foreground` on `primary-muted`; `accent-foreground` on `accent`; `card-foreground`, `popover-foreground` and `secondary-foreground` on their surfaces; the sidebar pairs (`sidebar-foreground`, `sidebar-primary`, `sidebar-primary-foreground`, `sidebar-accent-foreground`).
- Selection controls and focus, at least 3:1: `ring` on `background` and `card`, `input` on `background` and `card`, `sidebar-ring` on `sidebar`. Following #148, text-field/select/outline-button idle borders use the theme's `border` hairline and are not held to 3:1. This is an appearance tradeoff, not a claim that hairline outlines meet the former control-boundary contrast target. Text, focus and error contrast are unchanged.
- Hue: a non-default scheme's `primary` and `primary-muted-foreground` stay at least 40 degrees of OKLCH hue from every status hue, or have chroma under 0.02.

**Roles.** `primary`/`primary-foreground` is the solid fill and `text-primary` on page surfaces; `primary-muted`/`primary-muted-foreground` is the tint pair. In dark mode `primary` is a light shade with a dark foreground, as in the Light Primary In Dark Rule.

**Add a scheme.**

1. Pick a hue at least 40 degrees from 27, 80, 160 and 235 (or a near-neutral). Build the light and dark palettes, check every pair above, and write the two blocks after the existing ones in `app.css`, in the same token order.
2. Add the id to `ThemeSchemes::IDS` and to `THEME_SCHEMES` in `use-theme-scheme.ts` with an Indonesian label and a short description.
3. Run `php artisan test --compact tests/Unit/ThemeContrastTest.php tests/Feature/ThemeSchemeCookieTest.php`, then `tests/e2e/specs/theme-scheme.spec.ts` and an axe color-contrast pass on the key pages in light and dark. The new scheme must add no violation compared with UBG Blue in the same mode.

**Swatches.** A swatch element carries its own `data-theme` (and `dark` for the dark half) and reads `var(--background)`, `var(--primary)` and the like. It cannot use `bg-primary`, because `@theme` resolves `--color-primary` once at the root.

The public appearance dialog reserves 4px of horizontal and bottom clearance inside its desktop scroll container so selected and keyboard-focus rings on theme choices are not clipped. Keep the shared ThemePicker and authenticated settings styling unchanged.

**Filament decision.** The admin panel stays on UBG Blue in every scheme. It does not load the React stylesheet or read `data-theme`, and its palettes are fixed in `SEMANTIC_COLORS`. The picker therefore does not change `/admin`.

## Typography

**Display, Body and Label Font:** Instrument Sans (400, 500, 600 loaded from fonts.bunny.net in `resources/views/app.blade.php`), with `ui-sans-serif, system-ui, sans-serif` and emoji fallbacks (`--font-sans` in `app.css`). One family for everything; hierarchy comes from size, weight and tracking.

**Character:** Plain and legible. Semibold titles with slightly tight tracking over regular 14px copy, muted secondary text, small medium-weight labels.

### Hierarchy

- **Headline** (600, 24px / `text-2xl`, tracking-tight): page title, `PageHeader` level 1 (renders an `h1`).
- **Title** (600, 20px / `text-xl` for level 2, 18px / `text-lg` for level 3, tracking-tight): `PageHeader` levels 2 and 3. Card titles are `font-semibold leading-none`.
- **Stat** (700, 30px / `text-3xl`, tracking-tight): the headline number in `StatCard`.
- **Body** (400, 14px / `text-sm`): descriptions, table cells, form fields (16px on mobile inputs, `text-base md:text-sm`), buttons (500).
- **Label** (500, 12px / `text-xs`): badges, pills, column headers, micro-copy, in `text-muted-foreground` when secondary. Count chips inside pills use 12px bold with `tabular-nums`.

### Named Rules

**The Sentence Case Rule.** Labels are Indonesian, sentence case, at least `text-xs` (12px). Avoid `text-[9px]`/`text-[10px]` and uppercase `tracking-widest` micro-labels for content; use `text-xs font-medium text-muted-foreground`.

**The Tabular Numbers Rule.** Numbers that stand alone as plain text use `tabular-nums`; they are not wrapped in a pill (see Composition rules).

## Layout

A sidebar shell for the portals and a centered content column. The sidebar is 16rem wide (18rem as a mobile sheet, 3rem collapsed to icons) and its open state is stored in the `sidebar_state` cookie. The sticky app header is 3rem high in both sidebar states, with a bottom border. Desktop breadcrumbs show `Portal SiTA > current page`; phones keep the current page title. Page content sits in a `max-w-7xl` (80rem) column with `gap-4` between blocks; the settings layout uses the same column with `px-4 md:px-6` gutters and a `max-w-xl` compact variant. Auth screens use a narrow card (`max-w-md` or `max-w-sm`).

Initialize desktop sidebar state synchronously from the current `sidebar_state` cookie on each mount; use the server preference only when no valid browser cookie exists. Prefetched Inertia pages and history snapshots can contain an older preference and must never reopen a sidebar the user just collapsed (or collapse one just expanded). Mobile drawer state remains independent.

Spacing follows the Tailwind 4px base. Observed rhythm: `gap-2` (8px) between inline controls, `gap-4` (16px) between blocks, `gap-6` (24px) for page sections, and 24px padding inside cards. Headers and actions stack on narrow screens (`flex-col` to `sm:flex-row`). Breakpoints are the Tailwind defaults (sm 640, md 768, lg 1024, xl 1280, 2xl 1536). Every role is designed for a laptop first. Below `md` a page only has to stay readable and unbroken (no horizontal overflow, no text cut off without a way to the full text, buttons can be pressed); portal pages show the dismissible `DesktopNotice` there (width read on the client, dismissal kept in `sessionStorage`). Chat is the exception: never render the laptop notice on mahasiswa/dosen Pesan or leave an empty notice-height offset, whether opened directly or through Inertia navigation. Visiting chat must not dismiss the notice on other pages.

From `xl`, a dashboard agenda beside the action queue is size-contained to the action section's actual height. Its heading stays outside the independently scrollable, keyboard-focusable agenda body. Below `xl`, it returns to normal document flow without clipping. The same rule applies to mahasiswa, dosen, kaprodi and the Filament admin dashboard (#148).

Public pages (`/`, `/jadwal`, `/pembimbing`, `/topik`, `/mahasiswa-aktif`) are guest-visible and follow the same tokens and patterns. They are a read-and-look-up surface, not a landing page: `PublicLayout` (`resources/js/components/public/`) gives a header with the page nav, a `PageHeader` h1 with description, and a footer; the page content sits directly on the page background in a `max-w-6xl` column with no outer card.

The public header preserves its desktop pill navigation from `md`. Only on phones, the same five destinations use text links without pill fills or borders; the current page has primary text and an underline. Both layouts keep `aria-current="page"`. Mobile links share one compact row with 44px touch targets; logo, account and appearance controls stay separate.

Public list pages compose the data-list patterns (see Components, Data lists and tables), with the page content directly on the background and the header not sticky on phones. Expanded detail (topik) is rendered only when open. The product owner approved this treatment for the list pages (#82); tables and lists in the mahasiswa, dosen and kaprodi portals adopt it too.

The home page (`welcome.tsx`) is the exception: it keeps its designed hero (gradient panel, badges, large headline) and the four-card snapshot with count-up numbers. In #82 a flattened version (plain headline plus a divided list) was rejected as "basic, without any style". Do not flatten or strip the home page during refinement; change it only on an explicit request. The detector spec keeps `/` advisory for that reason (`ADVISORY_PAGES`).

Direction decisions from #82: refinement of the incumbent identity was chosen. A redesign into a new visual world ("The Map Sheet": condensed type, mint ground, sharp corners, full-width nav rail) was rejected as too opinionated for an academic campus tool. Overhauls of later areas refine within this system; they do not introduce a new visual world.

## Elevation & Depth

Hybrid. Surfaces are separated first by tonal layering (background, card, muted) and a 1px hairline border, then by a soft shadow only where an element is a card or floats above the page. Tables and nested content stay flat.

### Shadow Vocabulary

- **Control** (`shadow-xs`, `0 1px 2px 0 rgb(0 0 0 / 0.05)`): buttons, inputs, selects, outline controls.
- **Card** (`shadow-sm`, `0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)`): `Card`, `SectionCard`, `StatCard`. It is the card default; a page never adds it again.
- **Floating** (`shadow-md`, `0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)`): popover, dropdown menu, select content.
- **Overlay** (`shadow-lg`, `0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)`): dialog, sheet, dropdown sub-menu.
- **Focus ring** (`3px` ring at `--ring` / 50%, plus a `--ring` border): every interactive control, via `focus-visible`. Invalid fields use a `destructive` ring at 20% (40% in dark).

### Named Rules

**The One Surface Rule.** A `SectionCard`/`Card` already has border and shadow; content inside it uses borders or dividers, not another shadowed card. `DataTableContainer` has a border but no shadow for that reason, and a page must not add `shadow-sm` to a `Card` (it is the default).

## Shapes

Gently rounded and consistent. `--radius` is `0.5rem` (8px): `rounded-lg` is 8px, `rounded-md` 6px (buttons, inputs, selects, badges, popovers), `rounded-sm` 4px (menu items). Cards, table containers and empty-state frames use `rounded-xl` (12px). Dialogs use `rounded-lg`. Status badges, filter pills and avatars are fully round (`rounded-full`). Icon chips are `rounded-lg` squares (40px) holding a 20px Lucide icon. Borders are 1px hairlines in `--border`; the empty state uses a dashed border. Icons are Lucide at 16px (`size-4`) inside controls.

## Components

Primitives live in `resources/js/components/ui/` (shadcn style: Radix, `cva`, `cn` from `clsx` and `tailwind-merge`). Patterns live in `resources/js/components/patterns/` and import as `@/components/patterns/<name>`. Reuse before creating.

### Buttons

- **Shape:** 6px radius (`rounded-md`), 36px high (`h-9`, `px-4`); `sm` is 32px, `lg` is 40px, `icon` is 36px square. 14px medium text, 16px icons, `gap-2`.
- **Default:** `bg-primary text-primary-foreground shadow-xs`, hover `bg-primary/90`.
- **Soft:** `bg-primary-muted text-primary-muted-foreground`, hover at 70%. The tinted button.
- **Outline / Secondary / Ghost:** outline is a hairline border on the page background with `accent` on hover; secondary is the muted fill; ghost is transparent with an `accent` hover; link is `text-primary` with an underline on hover.
- **Destructive:** `bg-destructive` with white text.
- **Focus and disabled:** 3px focus ring; disabled is 50% opacity and not clickable.

### Cards / Containers

- **Corner Style:** 12px (`rounded-xl`).
- **Background:** `bg-card` on `bg-background`, with a 1px border.
- **Shadow Strategy:** `shadow-sm` once, from `Card` itself (see Elevation & Depth).
- **Internal Padding:** 24px horizontal and vertical (`px-6 py-6`), `gap-4` between header and content. Tables inside a card use dividers.

### Inputs / Fields

- **Style:** 1px `--border` hairline, transparent fill, 6px radius, 36px high, `px-3`, `shadow-xs`; `text-base` on mobile and `text-sm` from `md`. Checkboxes retain `--input`.
- **Focus:** border becomes `--ring` plus a 3px ring at 50%.
- **Error / Disabled:** `aria-invalid` shows a destructive border and ring; disabled is 50% opacity.
- Login password visibility uses an inline eye button, never a submit action. Start masked; keep value, autocomplete and keyboard order intact. Its action label switches between Tampilkan kata sandi and Sembunyikan kata sandi; reserve input space for the icon and a 44px mobile target. Filament login retains its built-in reveal control.

### Navigation

Sidebar items are 6px-radius, 14px rows; the active item uses the `primary-muted` pair with a faint `primary/15` border and medium weight, hover uses `sidebar-accent`. The sidebar has its own tokens (`--sidebar*`) equal to the page tokens in both themes. On mobile it becomes an 18rem sheet. Menus per role come from `resources/js/config/role-navigation.ts`.

Unread Pesan uses a numeric attention badge, not an academic status badge: `bg-destructive text-destructive-foreground`, matching the header notification bell. Expanded navigation and the mobile drawer place the badge inline 8px after the label, vertically centred; icon-only navigation anchors it at the icon's upper-right without reducing the click target or obscuring the icon. Use 12px tabular numerals, show `99+` above 99, announce the exact count in the link's accessible name, and hide the badge at zero. Count unread messages from chat receipts; the bell counts independent global notifications (including announcements and academic updates). Reading one must not clear the other.

### Status badge

A fully round pill (`rounded-full`, 12px medium text, `px-2 py-0.5`) with the muted surface and its muted-foreground text for the status tone. Tone comes from `statusClassName(domain, status)`, never from a palette hue.

### Filter pills

28px-high round pills on desktop, at least 44px below `md`, in muted fill; the selected pill is `bg-primary text-primary-foreground`. Count chips sit inside, inverted when selected.

### Stat card and empty state

`StatCard` is a card with a 30px bold value, a muted description and a 40px `primary-muted` icon chip. `EmptyState` is a dashed `rounded-xl` frame with a 40px round `muted` icon holder, a 14px medium title and a muted description.

### Action feedback and notifications

- Mount one Sonner `AppToaster` at the React root for public, portal and settings pages. It follows `resolvedAppearance` and reads card, border, primary and semantic status tokens, so every colour scheme uses the same feedback pattern. Filament keeps its own notifications.
- Place portal feedback at the top center, 64px from the viewport top (16px below the compact header). The close control stays inside the toast at the right, with reserved content space; on phones it has a 44px target.
- Redirects share `flash.success` and `flash.error` through Inertia. Success lasts 4.5 seconds; errors last 8 seconds so the user has time to read them. Each occurrence has an id: StrictMode and history navigation must not repeat it, while repeating the same action may show the same message again.
- Use a toast for a completed action or a new realtime notification. Realtime notifications keep their icon and description, a "Buka" action that marks the item read before navigating, and a close control. Do not also show "Tersimpan" or a flash Alert for the same action.
- Keep an `Alert` for a condition that remains relevant on the page (expired mentoring access, unavailable prerequisites, archived records). Keep field validation beside the field. Authentication statuses such as an email-verification link being sent remain in their dedicated flow.

### Chat workspace

- Active thread rows use `rounded-2xl` with a muted fill in light and the darker background surface in dark, without an outline. Every row reserves the same 56px avatar lane, whether it shows one person or a group, so names and previews align. Inset separators start after the avatars and disappear above and below the active row.
- The conversation plane uses `muted` in light appearance and `background` in dark, without extra black mixing. Incoming bubbles, composer controls and attachment drafts use `card` (white in light appearance, the preset's card surface in dark). Its header gradient uses an eased multi-stop fade from the very top to transparent, without a hard opacity knee; messages scroll behind the member header, with starting clearance inside the scroll content rather than below a fixed header gap.
- Authorized avatars, the `+N` overflow count, thread name and distinct type label share one solid, card-colored outer pill so scrolling content cannot reduce their legibility. Do not nest a separate name pill or badge; omit the type label when it repeats the name. The linked profile title keeps its existing destination. The identity pill is 44px high and only as wide as its content; 32px circles overlap by 8px with 12px initials/counts instead of reserving separate 44px slots. The member trigger keeps a minimum 44px square pointer target. 8px vertical padding keeps the normal header and its fade to 60px.
- Groups show at most two authorized member avatars, including the viewer, then `+N`. Private threads show only the counterpart in avatars, member count and popover; server membership is unchanged. Hover, click/tap or keyboard opens the member list, centered beneath the entire identity pill rather than just the avatar button. Start directly with member rows, without a visible heading; keep an accessible dialog label. Every row links to that member's profile. Membership comes only from the existing server-authorized payload, never an extra panel lookup.
- First messages in a sender/minute group have a sharp upper-corner tail on the sender's side. Following messages keep the same radius on all corners and no tail. Incoming sender avatars are 44px; grouped follow-ups reserve the same horizontal space without repeating the avatar or adding its height.
- Below `sm`, outgoing bubbles reserve the same 44px avatar + 8px gap as incoming rows. Both directions have the same maximum bubble width, and sent messages cannot extend into the incoming avatar column. Desktop keeps the existing 85% row limit.
- Reply/edit (#173): Aksi pesan opens one keyboard/touch menu. Balas shows live source context in composer and bubble, with Batal and an authorized jump to the source; unavailable context contains no source metadata. Edit uses the same input with an explicit save/cancel mode and a Diedit marker, never a file replacement. Preserve the send draft while editing, sent time/order/receipts, and read-only completed/archive surfaces.
- Reply/edit changes context on selection and hands focus to the composer only after the menu releases its focus trap. Cancel, attachment completion/removal and successful send keep the send draft and restore input focus; ordinary menu dismissal/copy keeps trigger focus. Thread selection is remembered per account/page in tab-session storage, validated against authorized threads, with explicit deep links taking precedence.
- Thread lists show a primary attention count in a minimum 20px badge on desktop and mobile, `99+` above 99 and the exact accessible count. Use the same receipt-based unread semantics for mahasiswa and dosen; list-only mobile views never consume unread messages.
- Text and time sit 12px inside every bubble; replies and images keep a tighter 4px outer inset. Quotations are full-width, borderless and slightly darker than their bubble in both appearances; image previews fill the available width without cropping. Keep 4px between content and time. On desktop, Aksi pesan sits beside the bubble, outside the time line, and appears on bubble hover, keyboard focus or while its menu is open. Below md, its hidden trigger does not reserve bubble width; tap noninteractive bubble content to open the same menu. Nested links, images, quotations and text selection keep their own actions. Keyboard users retain a visible focused trigger and a 44px phone target. Salin teks copies only nonempty message/caption text verbatim and reports clipboard success or failure through the shared toast.
- Bubble timestamps show only the original WITA clock (`HH.mm`). Group history by the original send day in Asia/Makassar, never the browser timezone or edit date. Each date stays centered 8px beneath the identity pill while its messages scroll: Hari ini, Kemarin, then a full Indonesian date (for example, Jumat, 30 Januari 2026). Search reserves clearance for its controls and the date; equal clocks on different days never merge sender groups. Relative labels roll over at WITA midnight. Pesan terbaru uses the card surface (white in light, the theme's card color in dark) and card-foreground text; retain its 44px phone target.
- Image drafts (#174): file/drop/paste share native preparation before staging. Preview, filename, MIME, displayed bytes and upload progress represent the outgoing file. Preparing disables send; failures retain the prior draft and expose retry/removal instead of silently uploading the original. Keep the card/token surfaces and 44px phone controls. Policy and measured fixture choices are documented in README.

### Data lists and tables

Approved in #82 for the public list pages; tables and lists in the mahasiswa, dosen and kaprodi portals adopt the same patterns. They live in `resources/js/components/patterns/data-section.tsx`.

- **Compose:** `DataSection` (h2 title, optional summary) > `DataToolbar` (search left, filters right, with `DataSelect` for a select filter) > `FilterPills` for the quick filter > one `DataTableContainer` with `DataTablePagination`. The container is the only bordered surface: no `Card` around it, and no card per row.
- **Sticky controls (#94, #167):** one `StickyFilterBar` groups search, selects, quick filters and reset per table. Desktop keeps its inline sticky toolbar below the portal header (48px) or public header (81px). Below `md`, search remains beside an icon-only Filter toggle; an active dot and accessible count identify applied filters. The toggle expands an inline panel directly below search, not a dialog. One or two criteria use labeled dropdowns; larger sets start with "Filter berdasarkan..." and reveal selected criteria progressively. Changes apply immediately, criteria can be combined or removed, and Reset clears them. Collapse/Escape keeps applied values; Escape returns focus to the toggle. Server lists pass the complete selection through `onApplyFilters` to preserve other filters. Public schedules repeat synchronized search for each table.
- **Mobile filter toggle:** show the filter icon while collapsed and an X while expanded, with the accessible action "Tutup filter". The button stays 44px square in both states; closing the panel keeps applied values and the active indicator.
- **Compact rows on phones (#129, #167):** `stackedTable` keeps identity top-left and status/actions top-right. Short `td` metadata uses two equal columns, 12px text and adjacent labels/values; long `tdDetail` spans the row. No nested cards or centered values. Preserve DOM order, full-content access, wrapping and desktop cells. Student progress/risk are metadata, not separate full-width rows when they fit.
- **Pagination (#167):** default to 5 rows below `md`; preserve each desktop default and manual selection. Client lists paginate the full filtered set. Server lists request complete pages and reset page indices on size changes, preserving filters. Mobile controls share one row: explicit "Menampilkan 1-5 dari 28 mahasiswa" (wrapping, never ellipsized), accessible size select, page count and 44px previous/next arrows. Bottom navigation is also one compact row. Keep full labels on desktop and accessible names on mobile; do not shrink touch targets to save height.
- **Read-only metadata (#168):** `KeyValueList` and profile `FactGrid` pair short facts in two columns on mobile with 12px labels and compact gaps. Long names, titles, addresses, emails, files and notes can span both columns and wrap. Inputs stay 16px, controls 44px. Profile facts, document/snapshot details, schedule/exam drawers, public topic dates and revision-round dates follow this rule, preserving desktop arrangements. Document `SectionCard density="compact"` is opt-in, not a global padding change.
- **44px on phones:** search, select, filter pills (`itemClassName="max-md:h-11 max-md:px-4"`) and icon buttons are 44px high below `md` and 36px from `md`.
- **Actions cell:** the last cell uses `stackedTable.tdActions`: a compact icon or menu cluster at the right of the row (`flex items-center justify-end gap-1`), and at the right of the first line of the entry on phones (a text button, wider than two icons, moves to the detail line with `max-md:order-4 max-md:basis-full`). Its header cell holds `<span className="sr-only">Aksi</span>`. Keep the cluster to two icon buttons or one menu; icon buttons carry an `aria-label`.
- **Read-only row actions:** an action whose only purpose is to read/open a record uses the `Eye` icon in `RowIconButton`, with a tooltip and specific `aria-label`. Do not render text buttons such as "Detail sesi", "Lihat" or "Buka" for these actions, including on phones. Keep the 36px desktop / 44px mobile hit target. Mutation and decision actions retain their verb labels.
- **Detail on demand:** expanded detail is rendered only when open, never hidden at rest.

Progress Seluruh Mahasiswa (`/progres-mahasiswa`, #179) uses that exact global title in navigation, breadcrumbs, heading and browser tab. Dashboard sections covering only a subset keep their own titles. The authenticated student's eligible row leads the filtered query before pagination, including students without a thesis; a soft primary Anda badge beside the identity stays visible at desktop and phone widths. Search/filter exclusions do not insert self outside the result. Other students retain latest-progress/name/profile-ID order, and non-student ordering stays unchanged.
The Anda row also has a quiet `primary-muted/60` background across its full desktop/mobile entry, with `primary-muted` on hover. Ordinary rows keep their existing neutral hover; no status hue, extra border or stronger selection treatment is added.

Programme filter options on Progress Seluruh Mahasiswa and the public mahasiswa, topik and pembimbing pages include their configured degree levels: `Ilmu Komputer (S1)` and `Ilmu Komputer (S2)`. A single programme offering several levels lists them together, for example `Rekayasa Perangkat Lunak (D3, S1)`. Keep distinct programme IDs/slugs and the existing page-specific populations; identical names never merge programmes. Labels come from `AcademicTerminology::programStudiLabel()`.

### Portal: kaprodi

Decided in #85, desktop-first and clean at 390px. The five kaprodi pages (dashboard, Mahasiswa Prodi, Sempro / Sinopsis & Sidang Akhir, Dokumen, Dosen Prodi) all start with `PageHeader` and compose lists from the data-list patterns above.

The prodi-wide document list uses Dokumen Prodi in navigation, page title and return links, distinct from Dokumen & Revisi under Bimbingan Saya. Keep /kaprodi/dokumen and capability scopes unchanged.

- **Row actions:** one primary action stays visible; the rest sit in an "Aksi lainnya" `DropdownMenu`. Mahasiswa Prodi keeps "Atur pembimbing" visible and moves profile, documents, exam schedule and lecturer notes into the menu; Sempro / Sidang keeps "Ubah jadwal"; Dokumen and Dosen Prodi keep two icon buttons. Icon-only buttons are `RowIconButton` (`components/kaprodi/row-icon-button.tsx`): ghost icon button, tooltip, `aria-label`, 44px below `md`.
- **Filters:** one quick filter per list as `FilterPills` with counts over the whole list (phase on Mahasiswa Prodi, status on Dokumen, concentration on Dosen Prodi, type and status on Sempro / Sidang). Secondary filters (risk, angkatan, konsentrasi) are `DataSelect`s in the toolbar. These lists are paginated in the browser over the complete list, so filters and counts cover every row. A separate "Reset filter" ghost button appears only while a filter is set.
- **Cells:** the exam table folds title and NIM under the student name, and the lecturer table folds concentration and expertise under the name. A missing value is a single muted `-`. Status and phase use `StatusBadge` through the `phase`, `defense`, `document` and `state` domains; risk uses `toneClassName`. Dosen Prodi distinguishes P1 with the primary-muted pair and P2 with the success-muted pair (#96); only these role labels are pills. Their counts, quota and exam counts stay plain tabular text.
- **Dashboard (#32, #124):** follows the approved dosen dashboard (#123), using the existing snapshot and bento variants. Desktop starts with one divided four-cell summary strip (active enrolled students, students without supervisors after the proposal stage, upcoming exams, expired mentorship); action cards and an unboxed agenda sit alongside each other from `xl`. Below `md`, actual DOM and keyboard order is actions, 2x2 summary, agenda, then progress. The heading keeps a compact Bimbingan Saya link, not a second lecturer workspace.
- **Dashboard actions:** the first permitted nonempty action spans both columns with a neutral surface, explanation, one contextual name and a normal primary CTA. Secondary cards carry only icon, title, count and action. Name previews are limited to the leading item; counts, capability gates, prodi scope, urgency order and filtered destinations remain unchanged.
- **Dashboard agenda and progress:** WITA day headings and primary-colored clock labels form a timeline without a SectionCard wrapper. Existing exam/revision order and limits stay intact; the exam snapshot is an independent aggregate, not a count of the truncated agenda. Progress is one divided two-column surface with the existing attention order and shared student content. Phase/exam statistics and archives remain below. All-clear uses the same quiet primary-muted treatment as dosen; the empty agenda stays flat. Active enrolled count follows the profile's active flag, not project state.

### Calendar

FullCalendar variables (borders, buttons, today highlight) are mapped to the tokens in `app.css` for both themes; calendar event fills use the `schedule` domain tones. Days outside the month use muted text instead of FullCalendar's 0.3 opacity, which fails AA.

### Filament admin

Konfirmasi ujian (#172) membedakan penilaian individual dari hasil akhir. Portal dosen memakai Dialog di atas drawer yang tetap terpasang agar Batal dan validasi server mempertahankan draft, butir dan file. Konfirmasi merangkum identitas, jenis/attempt, keputusan, skor hanya bila diwajibkan, catatan dan revisi yang benar-benar dikirim. Filament memakai modal konfirmasi bersarang setelah validasi form hasil akhir, dengan ringkasan data dan konsekuensi service. Batal kembali ke form, kegagalan simpan mempertahankan draft dan meminta konfirmasi lagi. Tombol mutasi nonaktif selama processing; tidak ada aktor persetujuan baru.

- `App\Providers\Filament\AdminPanelProvider::SEMANTIC_COLORS` mirrors the color values from `resources/css/app.css`, because the panel does not load the React stylesheet. Keep those two palettes paired. Separately, the TypeScript domain-status mapping mirrors `App\Support\Filament\BadgeStyles::DOMAIN_TONES`.
- The colour scheme picker (`data-theme`) does not reach the admin panel. Filament stays on UBG Blue; see Colour schemes.
- Filament primary matches the React tokens: solid `#2563eb`/white in light, `#60a5fa`/`#0f172a` in dark, and `primary` badges use the primary-muted pair. Filament draws dark-mode text, links, active navigation, tabs and pagination from palette shade 400, so shade 400 is the dark `--primary`.
- Status palettes are derived from those constants (shade 400 = dark solid, 500 = light solid, 600 = light muted-foreground, 50 = muted surface); primary uses a blue ramp. Filament chooses button shades dynamically, so palettes alone cannot guarantee token matching. `resources/views/filament/semantic-colors.blade.php`, rendered at `PanelsRenderHook::STYLES_AFTER`, pins solid `.fi-btn` backgrounds/foregrounds and `.fi-badge` muted surfaces/text to the light/dark tokens, and removes the badge ring so badges match `StatusBadge`. Outlined, gray and disabled states retain Filament behavior.

- The panel font is Instrument Sans (`->font('Instrument Sans')`), the same family as the React app.
- Users template downloads (#171) use one native Filament action-group button, Unduh template Excel, with separate Mahasiswa, Dosen and Admin menu items. Each downloads its own XLSX; keep the existing Import Excel modal and its role-default/programme selection separate. No custom modal or palette for choosing a download.
- Layout rules Filament's PHP API cannot express live in `resources/views/filament/admin-layout.blade.php`, rendered at `PanelsRenderHook::STYLES_AFTER` next to the color pinning: the dashboard "Perlu tindakan" list (`.sita-action-queue*`) and the detail tables on the thesis project page (`.sita-detail-table`). Repeatable-entry tables scroll sideways from 36rem of container width and keep their action column (`.sita-detail-table-sticky-end`, "Unduh") at the right edge; below that Filament's stacked card layout applies. Native list tables already scroll inside `.fi-ta-content-ctn`, so they need no extra rule.
- Admin dashboard (#33, #125): desktop starts with the existing four statistics in one divided strip (icon opposite a semantic-colored value, label/context underneath), then a two-column neutral action grid beside an unboxed 7-day exam timeline from `xl`; charts and recent events stay below. Below `md`, the actual DOM and keyboard order is actions, 2x2 statistics, agenda, charts. The stats widget moves its own node at the breakpoint; no duplicate widgets or CSS-only visual reordering.
- `AdminActionQueueService` retains every queue's count, urgency, `action_queue` destination and program-studi scope. Only the first nonempty action previews one eligible student plus the remaining count; secondary cards contain icon, label, count and one CTA, with no description or name queries. The leading card spans both desktop columns but keeps a neutral surface. All-clear uses a quiet primary-muted surface; the empty agenda stays flat. Archived projects never appear in a queue.
- Dashboard views use native Filament widgets, stats and buttons, scoped `.sita-admin-*` layout rules and the paired `--sita-*` tokens. Numeric status text uses muted-foreground ink for AA contrast on neutral surfaces. Action and agenda links keep 44px minimum targets.

### Pattern inventory

Patterns live in `resources/js/components/patterns/` and import as `@/components/patterns/<name>`. The status mapping is not a component: it lives in `resources/js/lib/status-styles.ts` and imports from `@/lib/status-styles`.

| Pattern                           | Use when                                                               |
| --------------------------------- | ---------------------------------------------------------------------- |
| `PageHeader`                      | Top of every page: title, optional description and page-level actions. |
| `SectionCard`                     | A titled block of content on a page (list, form, details).             |
| `FilterBar`                       | Row of filters/search above a table or list.                           |
| `FilterPills`                     | Mutually exclusive quick filter (status, phase) with optional counts.  |
| `StatusBadge` / `statusClassName` | Any status, phase, result or state label for a domain value.           |
| `StatCard`                        | A headline number with title, description and icon.                    |
| `EmptyState`                      | A list or section with nothing to show.                                |
| `ActionQueue` / `ActionQueueItem` | "Things needing your attention", each with an action.                  |
| `KeyValueList`                    | Label/value details (profile, submission, schedule facts).             |

#### PageHeader

```tsx
import { PageHeader } from '@/components/patterns/page-header';

<PageHeader
    title="Sempro dan Sidang"
    description="Pantau jadwal dan hasil di program studi."
    actions={<Button>Ekspor</Button>}
/>;
```

Props: `title: ReactNode`, `description?: ReactNode`, `actions?: ReactNode`, `level?: 1 | 2 | 3` (default 1), `className?`. Renders a `header` with an `h1` by default.

#### SectionCard

```tsx
import { SectionCard } from '@/components/patterns/section-card';

<SectionCard
    title="Jadwal"
    description="Minggu ini"
    actions={<Button size="sm">Tambah</Button>}
>
    {content}
</SectionCard>;
```

Props: `title?`, `description?`, `actions?`, `children`, `className?`, `contentClassName?`. Renders a card; a title adds a labelled `role="region"`. Headers and actions stack on narrow screens.

#### FilterBar and FilterPills

```tsx
import { FilterBar } from '@/components/patterns/filter-bar';
import { FilterPills } from '@/components/patterns/filter-pills';

type Phase = 'all' | 'sempro' | 'sidang';

<FilterBar>
    <FilterPills<Phase>
        label="Filter fase"
        value={phase}
        onValueChange={setPhase}
        options={[
            { value: 'all', label: 'Semua', count: total },
            { value: 'sempro', label: 'Sempro', count: 0 },
            { value: 'sidang', label: 'Sidang', disabled: true },
        ]}
    />
</FilterBar>;
```

`FilterBar` is a responsive wrapper (`children`, `className?`). `FilterPills<T extends string>` takes `label: string`, `value: T`, `onValueChange: (value: T) => void`, `options: readonly { value: T; label: string; count?: number; disabled?: boolean }[]`, `className?`. It is a single-select Radix `ToggleGroup`: clicking the active pill keeps the selection, a count of `0` is shown, arrow keys move focus, and `label` is the accessible name.

#### StatusBadge and status mapping

```tsx
import { StatusBadge } from '@/components/patterns/status-badge';
import { statusClassName, statusTone } from '@/lib/status-styles';

<StatusBadge domain="defense" status={defense.status}>
    {label}
</StatusBadge>;

statusTone('phase', 'sempro'); // 'info' (a StatusTone)
statusClassName('result', row.result); // classes for custom elements
```

- `resources/js/lib/status-styles.ts` owns the mapping. `StatusTone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'`.
- `StatusDomain = 'phase' | 'state' | 'defense' | 'result' | 'schedule' | 'document' | 'revision' | 'ticket' | 'active'`.
- `statusTone(domain, status)` and `statusClassName(domain, status)` take the raw domain key (`'title_review'`, `'approved'`), never a localized label. `null`, `undefined` and unknown values map to `neutral`.
- `StatusBadge` props: `domain`, `status`, `children` (caller-owned, localized label, usually from `academic-terminology.ts`), `className?`.
- The mapping must stay aligned with `App\Support\Filament\BadgeStyles::DOMAIN_TONES`. Change both sides together; labels and enums do not change.

#### StatCard and EmptyState

```tsx
import { EmptyState } from '@/components/patterns/empty-state';
import { StatCard } from '@/components/patterns/stat-card';

<StatCard title="Bimbingan" value="12" description="Bulan ini" icon={Users} />;
<EmptyState
    icon={Inbox}
    title="Belum ada data"
    description="Data akan muncul di sini."
/>;
```

`StatCard`: `title`, `value`, `description?`, `icon`, `className?`, `variant?: 'default' | 'snapshot'`, `tone?`. The snapshot variant follows the landing-page cells: a semantic icon tint opposite a colored plain count, with title and description below. Its neutral container can be flattened inside one divided summary strip. Values use tabular numerals. `EmptyState`: `icon`, `title`, `description?`, `className?`. Both moved here from `@/components/`; the old paths no longer exist.

#### ActionQueue and KeyValueList

```tsx
import {
    ActionQueue,
    ActionQueueItem,
} from '@/components/patterns/action-queue';
import { KeyValueList } from '@/components/patterns/key-value-list';

<ActionQueue label="Perlu tindakan">
    <ActionQueueItem
        icon={FileText}
        title="Revisi menunggu persetujuan"
        description="Diajukan kemarin"
        meta={
            <StatusBadge domain="revision" status="open">
                Menunggu
            </StatusBadge>
        }
        action={
            <Button size="sm" asChild>
                <Link href={href}>Tinjau</Link>
            </Button>
        }
    />
</ActionQueue>;

<KeyValueList
    layout="inline"
    items={[
        { key: 'room', label: 'Ruang', value: 'R-201' },
        { key: 'time', label: 'Waktu', value: '09:00' },
    ]}
/>;
```

`ActionQueue`: `label` (accessible name), `children`, `className?`, `layout?: 'list' | 'bento'` (default `list`; bento becomes a two-column grid from `md`). `ActionQueueItem`: `title`, `description?`, `icon?`, `meta?`, `action?`, `className?`, `variant?: 'row' | 'card' | 'featured'` (default `row`), `count?`, `tone?`. Card items use 12px padding and a compact icon/title/count header with an optional action. Featured items keep a neutral surface with more generous title/count sizing and a subtle primary border, never a solid primary fill. Both use an h3, plain tabular counts and semantic icon tints; controls remain siblings of the text. `KeyValueList`: `items: { key?, label, value }[]`, `layout?: 'stacked' | 'inline'`, `className?`; renders a `dl`.

### Motion

State changes use `transition-[color,box-shadow]` or `transition-colors`. Overlays use `tw-animate-css` fade and zoom (200ms for dialogs; sheets slide in 500ms and out in 300ms). Respect reduced motion for anything new.

Public topic details (`/topik`) are the one disclosure with motion. A pointer or touch activation fades and slides only the inner detail content: entry 180ms from opacity 0.9 and `translateY(4px)`, exit 120ms to opacity 0.9 and `translateY(-2px)`, both on the existing `--ease-out` curve and the `--public-motion-enter-duration` / `--public-motion-exit-duration` variables. The chevron rotates 150ms. Nothing animates height, padding or row position, so later rows reflow at once. Keyboard activation, filter, search and pagination actions, and `prefers-reduced-motion` skip the motion and settle immediately. A closing row is `aria-hidden` and `inert` from the first frame, keeps focus on its trigger, and is removed when its own transition finishes. Reversing mid-exit retargets the same element.

The public introduction (the homepage hero title with its description, and the `PageHeader` that `PublicLayout` renders on `/topik`, `/jadwal`, `/pembimbing` and `/mahasiswa-aktif`) plays one entrance per browser document: 180ms, opacity 0.9 to 1 and `translateY(4px)` to 0, on `--ease-out`, as a single group with no delay or stagger. Only the document the browser first loaded can play it, and only when that document is one of those five pages. Inertia visits, search and filter changes, pagination, history navigation and keyed remounts on the same document stay settled; a full reload may play it again. Reduced motion shows the text settled and a later preference change never starts it. `PublicIntro` keeps base styles visible, so unsupported animation leaves readable text. Do not extend it to portal headers, rows or counts.

## Do's and Don'ts

### Do:

- **Do** compose pages from `resources/js/components/patterns/` and the semantic tokens in `resources/css/app.css`.
- **Do** express status with a semantic token (`bg-success-muted text-success-muted-foreground`), `StatusBadge` or `statusClassName`.
- **Do** pair every solid fill with its `-foreground` and every muted surface with its `-muted-foreground`, and recheck contrast when changing values or adding combinations.
- **Do** import a pattern when a page needs a header, stat card, empty state, filter pills or key/value list; if the pattern lacks something, extend the pattern.
- **Do** keep `app.css` (UBG Blue) and `AdminPanelProvider::SEMANTIC_COLORS` equal; Filament stays on UBG Blue whatever scheme the React app uses.
- **Do** define a new colour scheme only as `[data-theme]` blocks in `app.css`, with every token, and let `tests/Unit/ThemeContrastTest.php` pass before using it.
- **Do** switch scheme only through `data-theme`; never write colour values from JavaScript (`style.setProperty`, inline colour styles), and preview a scheme in a swatch with `var(--token)` under its own `data-theme`, not with `bg-*` utilities.
- **Do** keep `status-styles.ts` and `BadgeStyles::DOMAIN_TONES` aligned; change both sides together.
- **Do** take every label from `AcademicTerminology` / `academic-terminology.ts`, in Indonesian sentence case.
- **Do** use `text-xs font-medium text-muted-foreground` for small labels.

### Don't:

- **Don't** use raw Tailwind palette hues (`bg-amber-100`, `text-emerald-700`, `dark:bg-red-950`, `border-sky-200/60`, ...) to express status. Use the semantic tokens above or `StatusBadge`.
- **Don't** copy a pattern's markup into a page (own page header, stat card, empty state, filter pills, key/value list). Import the pattern. If it lacks something, extend the pattern.
- **Don't** use `bg-primary/10 text-primary` for tinted text; use the primary-muted pair.
- **Don't** put `text-white` or `bg-white/20` on primary, and no translucent `primary-foreground/20` chip under white text.
- **Don't** add `shadow-sm` to a `Card`, or a shadowed card inside a card.
- **Don't** wrap constant values, column-header repeats or plain counts in a pill, except the explicitly requested P1/P2 role labels on Dosen Prodi (#96).
- **Don't** switch to a palette hue when no token fits; use an opacity modifier on a token (`bg-success/10`).

### Composition rules

- One surface per block. A `SectionCard`/`Card` already has border and shadow; content inside it uses borders or dividers, not another shadowed card. `DataTableContainer` has a border but no shadow for that reason, and a page must not add `shadow-sm` to a `Card` (it is the default).
- Badges carry state. Constant values, column-header repeats and plain counts remain text (`tabular-nums` for numbers). The P1/P2 role-label exception is defined under Portal: kaprodi. A missing value is a single muted `-`, never a badge.
- Two adjacent `FilterPills` groups are separated (`gap-x-3` plus a vertical divider, or a label) so each group's "Semua" reads as its own selection.
- Labels are Indonesian, sentence case, at least `text-xs` (12px). Avoid `text-[9px]`/`text-[10px]` and uppercase `tracking-widest` micro-labels for content; use `text-xs font-medium text-muted-foreground`.
- `DataTablePagination` prints the range and page-size select once (top bar) and page navigation only when there is more than one page; pass `itemLabel` so the range reads "Menampilkan 1-10 dari 24 ujian".

### ESLint guard

- ESLint enforces the palette rule as a **warning** for `resources/js/pages/**/*.tsx` (`no-restricted-syntax` in `eslint.config.js`). It flags string literals and template fragments, including `cn(...)`/ternary/`clsx` arguments, variants and opacities. Semantic classes never warn. Warnings do not fail `npm run lint`; do not add new ones.
- The legacy `dosen/pesan-bimbingan.tsx` page retains its existing exemption from general lint rules, but the palette guard still checks it. Fixing its unrelated lint debt is outside this foundation change.

### Migration (#34)

Phased, one area per PR.

1. **Foundation (#5):** tokens, patterns, status mapping, guard, and one adopted page (`kaprodi/sempro-sidang`). Other pages are not migrated.
2. **Migration (#34):** move remaining pages to patterns and tokens by role area (mahasiswa, dosen, kaprodi, admin). Each migrated page should drop its palette warnings; that is how progress is tracked.
3. **Tighten:** when `eslint` reports no palette warnings, raise the rule to `error` in `eslint.config.js`.

Until a page is migrated, existing warnings there are accepted. New or touched pages must not add any.

### Portal: dosen

Overhaul of the dosen portal (#84) applies the #82 list treatment without changing the identity.

- Every dosen page opens with `PageHeader` (h1 plus a one-line description) because the shell renders no heading. Blocks below are `DataSection` (h2) or `SectionCard`.
- Lists (Mahasiswa Dosen, Sempro / Sinopsis & Sidang, Jadwal Bimbingan, Dokumen & Revisi) are `DataSection` > `DataToolbar` > `FilterPills` > one `DataTableContainer` with `stackedTable`. Two filter groups next to each other carry a visible label ("Jenis", "Status") and a divider from `md`.
- Pagination and filters are client-side over the full prop set; the dosen controllers do not paginate on the server.
- Row actions: one primary mutation/decision action (Kelola, Tanggapi) plus an "Aksi lainnya" menu where more than two remain. Read/open-only actions follow the eye-icon rule above at every viewport; do not add visible phone labels. Icon-only actions have a tooltip and `aria-label` with a 44px phone target.
- Jadwal Bimbingan pending requests are one divided list; the decision form opens on demand ("Tanggapi"), so nothing is hidden at rest and nothing sits in a card inside a card.
- Pesan keeps the shared chat shell; the page only owns the group filter (`FilterPills`) and thread badge tones.

#### Dosen dashboard (#31, #123)

- Desktop: four linked snapshot cells in one divided strip (active students/quota, exams from today's seven-day agenda, revisions awaiting verification, documents awaiting review), then the action grid with the unboxed agenda beside it from `xl`, then student progress in two divided columns. The strip follows the landing-page card treatment: icon opposite colored value, title and context below. Active students use success, exams primary, revisions warning and documents info; all are existing semantic tokens. Cells have no independent border or shadow. Summary values reuse the existing payloads, including zeroes.
- Latest product-owner direction replaces the proposed solid Cobalt focus card with the landing-page treatment. Every action keeps a neutral surface; the first item spans two columns with a larger title/count, explanation, at most one affected name for context and a conventional primary CTA. Supporting cards show only the icon, title, count and action, with no student-name preview or repeated explanation. Only the leading action runs a bounded one-name query; secondary payload entries have empty previews. Server priority, filtered destinations and all aggregate counts remain unchanged. No new palette, font, imagery or dependencies.
- Below `md`, the action queue comes before the 2x2 summary and agenda. DOM and keyboard order follow that reading order via the existing mobile media-query hook. Dashboard action, summary and agenda links have at least 44px targets.
- The agenda and student-progress headings sit directly on the page, without a SectionCard frame. The agenda is a divided timeline with prominent blue time labels; the progress list is one bordered, divided surface with the shared row content and attention ordering. Empty action queues retain "Semua beres" on a quiet primary-muted surface; empty agendas have a flat calendar state.
- Constrained agenda scroll regions pack date groups at the start, rather than stretching sparse date/event tracks into the available height. Student-progress hover and focus-within fill the entire divided row, including its attention footer.

### Portal: mahasiswa and shared shell (#83)

- **Page start:** every portal page opens with `PageHeader` (the one `h1`); the shell header shows the page title as a plain `p` on phones, never a second `h1`. Sections are `h2` (`DataSection` or `SectionCard`), their parts `h3`.
- **One surface per block:** inside a bordered block, rows are separated by dividers (`divide-y`), not boxes. `PersonCardLink` has `variant="row"` for borderless rows inside such a block; profile sections (`ProfileDetailsSections`) use it.
- **Session history:** student profiles integrate mentoring sessions into each lecturer row, collapsed by default. Expanding a lecturer mounts compact session rows (date, topic, status, contribution availability); an eye action opens the full record in a right-hand `Sheet` drawer. Never stack full note bodies on the profile or use a centered modal for reading a session. Archived sessions stay in this same profile hierarchy; do not add an archive feed to lecturer/kaprodi schedule pages. Use one shared session drawer for current and archived records, retaining read-only rules.
- **Schedule interactions (#157):** schedule and history rows open the same session drawer by pointer, Enter or Space; nested controls keep their own behavior. Lecturer completion and cancellation live inside that drawer, before session notes, and disappear after the persisted session leaves the upcoming list. Do not add a second Kelola drawer or disable slide motion to hide a performance problem.
- **Settings:** `SettingsLayout` renders the page `h1` ("Pengaturan akun") and a section nav with the `primary-muted` active pair; settings pages start at `h2`. Notification and appearance switches carry a `label`/`id` pair.
- **Chat (#159):** one compact workspace without a gutter or divider between list and conversation. Two-line rows use actual private-target avatars or two slightly overlapping authorized group participants excluding the viewer; the active row is a neutral card with a 4px radius. Search is collapsed behind a named button; opening focuses the input, Escape/close clears the filter and returns focus. Group/private context remains legible beside a one-line preview; completed threads keep their read-only disclosure. The conversation uses the scoped chat surface (deeper than background in dark mode), a centered profile montage and name/context, and a full-width fading overlay instead of a solid header box. Keep scrollable history below profile/search controls so messages cannot cover them. The rounded composer contains attachment/send only. Same-sender messages in the same displayed minute sit 2px apart; sender/time changes separate them. Incoming messages follow only while pinned to the bottom; reading history preserves scroll position and exposes Pesan terbaru. At 390px show list or thread with an explicit back action; composer remains reachable and controls at least 44px. Keep semantic tokens and 12px minimum text.
- **Shell:** header controls, sidebar items and notification actions are 44px below `md`. The notification sheet is a divided list with unread rows tinted `primary-muted`.
- **Document details (#152/#158):** document-specific eye actions open stable, role-scoped detail URLs for the exact version. Keep download/delete separate, 36px desktop and 44px phone targets. Reviewers edit structured revision points; owners complete/reopen them inline in each reviewer's divided list, with completion time visible read-only to lecturers/kaprodi. No extra read-only alert card and no upload gate. `revision_completed` uses the info tone, not the approved success tone: completion does not approve the document or prove a newer file exists. Preserve version/reviewer boundaries and full legacy notes.
- **Review decisions (#175):** Minta revisi and Setujui belong after the notes and revision-item editor inside Review Anda. Their DOM/tab order follows the inputs; do not require scrolling back to header actions after completing the form. Download stays in the document header. Keep the existing submit values, processing protection, validation and draft behavior.
- **Version history (#170):** one divided list inside Riwayat versi, with each version, filename and status on one 44px row. The filename is a stable Inertia link with `aria-current="page"` for the selected version. A separate native disclosure opens the complete wrapped filename and upload date with touch or keyboard, so truncation never removes access to the full value. Keep semantic status tokens, 12px metadata, every historical URL and snapshot boundary. Metadata, review editor, review notes and spacing across the whole detail page follow the same compact composition, not only the history card.
- **Contact actions:** WhatsApp uses the shared actual WhatsApp mark. Standalone buttons and buttons beside a primary action use the green `success` foreground/background pair; row-menu items use green `success` text/icons on the normal menu surface, including keyboard focus. Internal chat retains its own icon and normal theme treatment. Visible nonempty email, phone and NIM values have compact, named copy controls with truthful success/failure feedback; unavailable values have no copy action.
- **Own contact and student rows (#159):** own-profile phone fields show a WhatsApp badge, never a self-chat link. Keep edit/confirmation/copy independent. Mahasiswa Dosen groups internal group/private chat and available WhatsApp under Chat; other actions do not repeat WhatsApp. Put `x bimbingan` beside student information rather than repeating a Bimbingan column. Counts and minimum-warning rules do not change.
- **Admin notes:** table cells show a two-line excerpt for long review, lecturer and activity notes. A native keyboard-operable disclosure opens the complete escaped, multiline text in place; closing returns to the compact row. Short or absent notes do not require an extra click. Read-only views and visibility rules remain unchanged. Filament contact/identity entries use its built-in copyable behavior.

#### Mahasiswa dashboard (#134, #137)

- Lead with one academic title and workflow-status card at every width. The title (and optional English title/start date) is the primary content; status and its explanation sit alongside on desktop and stack on phones. Do not add advisor, agenda or unread-message counts/links to this card: agenda and advisors already have their own sections, and unread navigation indicators belong to a separate change. No additional queries.
- Keep one PageHeader h1. The main action opens the student's academic record or title submission; its button sits beside the copy when space permits and below on narrow screens. Scheduling and upload remain compact contextual cards with their original eligibility. Unavailable actions show their prerequisite and a disabled control. Messages stay reachable through a compact link even before a thread exists, rather than another sidebar shortcut card. Never infer an all-clear state from unavailable prerequisites.
- The agenda is an unboxed linked timeline beside actions from xl. Progress shows its percentage and a continuous vertical line connecting the academic stages, with completed segments in primary and future segments in border. Do not repeat the academic title inside progress or divide stages into separate cards. Advisors use one divided list of profile rows. No additional student or examiner data is queried.
- Actual DOM and focus order is academic title/status, actions, agenda, progress and advisors at every width. Application navigation uses Inertia. Preserve the shared desktop notice and compact phone table behavior outside the page. Dashboard controls keep 44px targets without a separate mobile redesign.

### Detector guard (Impeccable)

The `design-detector` Playwright spec runs `impeccable detect` against the key pages of every role at 1280px and 390px and reports findings per page. It is advisory until an area is listed in `BLOCKING_AREAS` in `tests/e2e/specs/design-detector.spec.ts`; once an area has been overhauled it is added there and its findings fail the build.

### Known drift (recorded, not canonized)

These are defects the build carries today. They are not part of the system and new surfaces must not copy them.

- Instrument Sans is flagged by the detector as an overused font; it is the incumbent brand face and is kept on purpose. The exception is recorded in `.impeccable/config.json` (`impeccable ignores add-value overused-font "Instrument Sans"`).
- `.animate-pop` uses an overshoot cubic-bezier (`0.175, 0.885, 0.32, 1.275`), and `DataTable` count chips use `text-[10px]`.
- The `shadcn` destructive button uses `text-white`.
