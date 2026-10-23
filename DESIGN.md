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

SiTA is a record of where each thesis stands, so the interface reads like a well-kept ledger: pale surfaces, hairline borders, one blue, and status colour that means the same thing on every page. It is calm on purpose. A student on a phone and a lecturer on a laptop both need to find the next step in a few seconds, and nothing decorative should compete with that.

The system is the shadcn/ui stack (Radix primitives, `cva` variants, Tailwind v4 tokens) with a domain layer on top: shared page patterns in `resources/js/components/patterns/` and semantic status tokens in `resources/css/app.css`. Pages compose those patterns and tokens. They do not hand-roll status colours or redeclare the patterns. The Filament admin panel at `/admin` is a separate surface that is kept visually paired with the React tokens (see Components).

This file records the incumbent system as it is built, plus the rules that were carried over from the former standalone design-system doc. Where the build drifts from a rule, the rule stands and the drift is listed under Do's and Don'ts as known drift. Product facts (users, tone, brand) live in `PRODUCT.md`.

**Key Characteristics:**

- One brand colour (UBG Blue) with a light and a dark theme; UBG Blue is the only theme that meets WCAG AA.
- Semantic status roles (primary, success, warning, danger, info, muted), each defined as four tokens in both themes.
- Tonal layering with hairline borders; a single soft shadow on cards, larger shadows only on floating layers.
- Sentence-case Indonesian labels, 12px minimum, one sans family.
- Phone-first for mahasiswa, laptop-first for dosen, kaprodi and admin.

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
- **Hairline** (`#e2e8f0`, dark `#202a35`): borders and input strokes.
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

**The Default Theme Rule.** UBG Blue (light and dark) is the default theme and the only one that meets WCAG AA. `resources/js/hooks/use-primary-color.ts` writes the selected preset inline on `<html>`, overriding `app.css`, so its `ubg-blue` entry must carry the same values as `:root`/`.dark`. The other presets (emerald, sunset, rose, indigo, teal) are proof-of-concept and fail AA in light and dark; accessible schemes defined as CSS tokens are tracked in #50.

**The Light Primary In Dark Rule.** Primary is `#2563eb` with white text in light mode, and `#60a5fa` with dark (`#0f172a`) text in dark mode. Dark primary is light on purpose: `text-primary` (links, active states, icons) must stay readable on dark surfaces, and the dark status solids follow the same light-fill/dark-text rule.

**The Tint Pair Rule.** Text on a primary tint uses `bg-primary-muted text-primary-muted-foreground` (active navigation, avatar initials, soft badges and buttons, icon chips). `bg-primary/10 text-primary` measures about 4.5:1 or less in light mode and fails AA once the tint is stronger or the base is not white.

**The Foreground Pairing Rule.** Text on `bg-primary` uses `text-primary-foreground`; a count chip nested in a primary pill is inverted (`bg-primary-foreground text-primary`). Never `text-white`/`bg-white/20` on primary (dark primary is light), and no translucent `primary-foreground/20` chip under white text (it lightens the fill below 4.5:1). In general, pair solid fills with `-foreground`, and muted surfaces with `-muted-foreground`. Browser-resolved sRGB measurements for the 16 success/warning/danger/info light/dark pairs were at least 5.07:1. Recheck contrast when changing values or introducing new combinations.

**The Muted Badge Rule.** Badges and callouts use the muted surface with its `-muted-foreground` text (`bg-success-muted text-success-muted-foreground`). Solid fills (`bg-danger text-danger-foreground`) are for emphasis such as alerts and buttons.

**The Paired Palettes Rule.** The Filament admin cannot load the React stylesheet, so its palettes mirror these tokens. See Components, Filament admin.

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

A sidebar shell for the portals and a centered content column. The sidebar is 16rem wide (18rem as a mobile sheet, 3rem collapsed to icons) and its open state is stored in the `sidebar_state` cookie. The sticky app header is 4rem high with a bottom border. Page content sits in a `max-w-7xl` (80rem) column with `gap-4` between blocks; the settings layout uses the same column with `px-4 md:px-6` gutters and a `max-w-xl` compact variant. Auth screens use a narrow card (`max-w-md` or `max-w-sm`).

Spacing follows the Tailwind 4px base. Observed rhythm: `gap-2` (8px) between inline controls, `gap-4` (16px) between blocks, `gap-6` (24px) for page sections, and 24px padding inside cards. Headers and actions stack on narrow screens (`flex-col` to `sm:flex-row`). Breakpoints are the Tailwind defaults (sm 640, md 768, lg 1024, xl 1280, 2xl 1536). Mahasiswa pages are designed for a phone first; dosen, kaprodi and admin pages for a laptop first, without breaking the other.

Public pages (`/`, `/jadwal`, `/pembimbing`, `/topik`, `/mahasiswa-aktif`) are guest-visible and follow the same tokens and patterns. They are a read-and-look-up surface, not a landing page: `PublicLayout` (`resources/js/components/public/`) gives a header with the page nav, a `PageHeader` h1 with description, and a footer; the page content sits directly on the page background in a `max-w-6xl` column with no outer card.

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

- **Style:** 1px `--input` border, transparent fill, 6px radius, 36px high, `px-3`, `shadow-xs`; `text-base` on mobile and `text-sm` from `md`.
- **Focus:** border becomes `--ring` plus a 3px ring at 50%.
- **Error / Disabled:** `aria-invalid` shows a destructive border and ring; disabled is 50% opacity.

### Navigation

Sidebar items are 6px-radius, 14px rows; the active item uses the `primary-muted` pair with a faint `primary/15` border and medium weight, hover uses `sidebar-accent`. The sidebar has its own tokens (`--sidebar*`) equal to the page tokens in both themes. On mobile it becomes an 18rem sheet. Menus per role come from `resources/js/config/role-navigation.ts`.

### Status badge

A fully round pill (`rounded-full`, 12px medium text, `px-2 py-0.5`) with the muted surface and its muted-foreground text for the status tone. Tone comes from `statusClassName(domain, status)`, never from a palette hue.

### Filter pills

28px-high round pills in muted fill; the selected pill is `bg-primary text-primary-foreground`. Count chips sit inside, inverted when selected.

### Stat card and empty state

`StatCard` is a card with a 30px bold value, a muted description and a 40px `primary-muted` icon chip. `EmptyState` is a dashed `rounded-xl` frame with a 40px round `muted` icon holder, a 14px medium title and a muted description.

### Data lists and tables

Approved in #82 for the public list pages; tables and lists in the mahasiswa, dosen and kaprodi portals adopt the same patterns. They live in `resources/js/components/patterns/data-section.tsx`.

- **Compose:** `DataSection` (h2 title, optional summary) > `DataToolbar` (search left, filters right, with `DataSelect` for a select filter) > `FilterPills` for the quick filter > one `DataTableContainer` with `DataTablePagination`. The container is the only bordered surface: no `Card` around it, and no card per row.
- **Sticky controls (#94):** group search, selects, quick filters and reset controls inside one `StickyFilterBar` per table. From `md`, it stays beneath the portal header (64px, 48px with collapsed sidebar) or public header (81px, `layout="public"`). Below `md`, controls scroll normally so stacked filters do not obscure the list. Multi-table public schedules repeat the synchronized search in each table's filter bar.
- **Stacked on phones:** below `md` the table becomes a divided list through the `stackedTable` classes (`table`, `thead`, `tbody`, `tr`, `td`, `tdPlain`) instead of scrolling sideways. Name-like cells use `tdPlain`; secondary cells use `td` with a `data-label`, shown above the value. Header cells use `dataTh`. The breakpoint is the viewport's, so a portal table that is too wide beside the sidebar between `md` and `lg` keeps a `min-w-*` and scrolls inside its container.
- **44px on phones:** search, select, filter pills (`itemClassName="max-md:h-11 max-md:px-4"`) and icon buttons are 44px high below `md` and 36px from `md`.
- **Actions cell:** the last cell uses `stackedTable.tdActions`: a compact icon or menu cluster at the right of the row (`flex items-center justify-end gap-1 max-md:justify-start`), and the last line of the entry on phones. Its header cell holds `<span className="sr-only">Aksi</span>`. Keep the cluster to two icon buttons or one menu; icon buttons carry an `aria-label`.
- **Detail on demand:** expanded detail is rendered only when open, never hidden at rest.

### Portal: kaprodi

Decided in #85, desktop-first and clean at 390px. The five kaprodi pages (dashboard, Mahasiswa Prodi, Sempro / Sinopsis & Sidang Akhir, Dokumen, Dosen Prodi) all start with `PageHeader` and compose lists from the data-list patterns above.

- **Row actions:** one primary action stays visible; the rest sit in an "Aksi lainnya" `DropdownMenu`. Mahasiswa Prodi keeps "Atur pembimbing" visible and moves profile, documents, exam schedule and lecturer notes into the menu; Sempro / Sidang keeps "Ubah jadwal"; Dokumen and Dosen Prodi keep two icon buttons. Icon-only buttons are `RowIconButton` (`components/kaprodi/row-icon-button.tsx`): ghost icon button, tooltip, `aria-label`, 44px below `md`.
- **Filters:** one quick filter per list as `FilterPills` with counts over the whole list (phase on Mahasiswa Prodi, status on Dokumen, concentration on Dosen Prodi, type and status on Sempro / Sidang). Secondary filters (risk, angkatan, konsentrasi) are `DataSelect`s in the toolbar. These lists are paginated in the browser over the complete list, so filters and counts cover every row. A separate "Reset filter" ghost button appears only while a filter is set.
- **Cells:** the exam table folds title and NIM under the student name, and the lecturer table folds concentration and expertise under the name. A missing value is a single muted `-`. Status and phase use `StatusBadge` through the `phase`, `defense`, `document` and `state` domains; risk uses `toneClassName`. Dosen Prodi distinguishes P1 with the primary-muted pair and P2 with the success-muted pair (#96); only these role labels are pills. Their counts, quota and exam counts stay plain tabular text.
- **Dashboard:** the welcome panel keeps its gradient and the "Ringkasan prodi" aside. The four navigation shortcuts, phase and exam counts, attention items, agenda and archives are `SectionCard`s holding divided rows (no nested cards, no filled primary tile); count values are tabular text.

### Calendar

FullCalendar variables (borders, buttons, today highlight) are mapped to the tokens in `app.css` for both themes; calendar event fills use the `schedule` domain tones. Days outside the month use muted text instead of FullCalendar's 0.3 opacity, which fails AA.

### Filament admin

- `App\Providers\Filament\AdminPanelProvider::SEMANTIC_COLORS` mirrors the color values from `resources/css/app.css`, because the panel does not load the React stylesheet. Keep those two palettes paired. Separately, the TypeScript domain-status mapping mirrors `App\Support\Filament\BadgeStyles::DOMAIN_TONES`.
- Filament primary matches the React tokens: solid `#2563eb`/white in light, `#60a5fa`/`#0f172a` in dark, and `primary` badges use the primary-muted pair. Filament draws dark-mode text, links, active navigation, tabs and pagination from palette shade 400, so shade 400 is the dark `--primary`.
- Status palettes are derived from those constants (shade 400 = dark solid, 500 = light solid, 600 = light muted-foreground, 50 = muted surface); primary uses a blue ramp. Filament chooses button shades dynamically, so palettes alone cannot guarantee token matching. `resources/views/filament/semantic-colors.blade.php`, rendered at `PanelsRenderHook::STYLES_AFTER`, pins solid `.fi-btn` backgrounds/foregrounds and `.fi-badge` muted surfaces/text to the light/dark tokens, and removes the badge ring so badges match `StatusBadge`. Outlined, gray and disabled states retain Filament behavior.

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

`StatCard`: `title`, `value`, `description?`, `icon`, `className?`. `EmptyState`: `icon`, `title`, `description?`, `className?`. Both moved here from `@/components/`; the old paths no longer exist.

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

`ActionQueue`: `label` (accessible name), `children`, `className?`. `ActionQueueItem`: `title`, `description?`, `icon?`, `meta?`, `action?`, `className?`; controls go in `action` so they are never nested in text. `KeyValueList`: `items: { key?, label, value }[]`, `layout?: 'stacked' | 'inline'`, `className?`; renders a `dl`.

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
- **Do** keep `app.css`, the `ubg-blue` preset in `use-primary-color.ts` and `AdminPanelProvider::SEMANTIC_COLORS` equal; change all three together.
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

- Every dosen page opens with `PageHeader` (h1 plus a one-line description) because the shell renders no heading. Blocks below are `DataSection` (h2) or `SectionCard`. The dashboard hero keeps its gradient panel and quota summary; its h1 is the greeting.
- Lists (Mahasiswa Dosen, Sempro / Sinopsis & Sidang, Jadwal Bimbingan, Dokumen & Revisi) are `DataSection` > `DataToolbar` > `FilterPills` > one `DataTableContainer` with `stackedTable`. Two filter groups next to each other carry a visible label ("Jenis", "Status") and a divider from `md`.
- Pagination and filters are client-side over the full prop set; the dosen controllers do not paginate on the server.
- Row actions: one primary action (Chat, Buka, Kelola, Tanggapi) plus an "Aksi lainnya" menu where more than two remain. Icon-only actions have a tooltip and `aria-label`; on phones they show their label at 44px.
- Jadwal Bimbingan pending requests are one divided list; the decision form opens on demand ("Tanggapi"), so nothing is hidden at rest and nothing sits in a card inside a card.
- Pesan keeps the shared chat shell; the page only owns the group filter (`FilterPills`) and thread badge tones.

### Portal: mahasiswa and shared shell (#83)

- **Page start:** every portal page opens with `PageHeader` (the one `h1`); the shell header shows the page title as a plain `p` on phones, never a second `h1`. Sections are `h2` (`DataSection` or `SectionCard`), their parts `h3`.
- **One surface per block:** inside a bordered block, rows are separated by dividers (`divide-y`), not boxes. `PersonCardLink` has `variant="row"` for borderless rows inside such a block; profile sections (`ProfileDetailsSections`) use it.
- **Settings:** `SettingsLayout` renders the page `h1` ("Pengaturan akun") and a section nav with the `primary-muted` active pair; settings pages start at `h2`. Notification and appearance switches carry a `label`/`id` pair.
- **Chat:** thread rows use a `primary-muted` fill for the active thread (no left stripe), 12px minimum text, type badges in the `primary-muted` or `warning-muted` pair, and a plain fade for new messages (no overshoot). Controls are 44px below `md`.
- **Shell:** header controls, sidebar items and notification actions are 44px below `md`. The notification sheet is a divided list with unread rows tinted `primary-muted`.

### Detector guard (Impeccable)

The `design-detector` Playwright spec runs `impeccable detect` against the key pages of every role at 1280px and 390px and reports findings per page. It is advisory until an area is listed in `BLOCKING_AREAS` in `tests/e2e/specs/design-detector.spec.ts`; once an area has been overhauled it is added there and its findings fail the build.

### Known drift (recorded, not canonized)

These are defects the build carries today. They are not part of the system and new surfaces must not copy them.

- Instrument Sans is flagged by the detector as an overused font; it is the incumbent brand face and is kept on purpose. The exception is recorded in `.impeccable/config.json` (`impeccable ignores add-value overused-font "Instrument Sans"`).
- `.animate-pop` uses an overshoot cubic-bezier (`0.175, 0.885, 0.32, 1.275`), and `DataTable` count chips use `text-[10px]`.
- The `shadcn` destructive button uses `text-white`, and the proof-of-concept theme presets fail AA.
