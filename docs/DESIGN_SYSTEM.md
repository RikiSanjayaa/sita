# Design System

Shared UI foundation for the React app. Pages compose the patterns in `resources/js/components/patterns/` and the semantic color tokens in `resources/css/app.css`. They do not hand-roll status colors or redeclare these patterns.

## Color tokens

Tokens are CSS variables with light and dark values, exposed as Tailwind utilities through `@theme`.

| Role      | Hue base     | Solid        | On solid                  | Muted surface      | Text on muted surface           |
| --------- | ------------ | ------------ | ------------------------- | ------------------ | ------------------------------- |
| `primary` | brand blue   | `bg-primary` | `text-primary-foreground` | `bg-primary/10`    | `text-primary`                  |
| `success` | emerald      | `bg-success` | `text-success-foreground` | `bg-success-muted` | `text-success-muted-foreground` |
| `warning` | amber        | `bg-warning` | `text-warning-foreground` | `bg-warning-muted` | `text-warning-muted-foreground` |
| `danger`  | red          | `bg-danger`  | `text-danger-foreground`  | `bg-danger-muted`  | `text-danger-muted-foreground`  |
| `info`    | sky/blue     | `bg-info`    | `text-info-foreground`    | `bg-info-muted`    | `text-info-muted-foreground`    |
| `muted`   | neutral gray | `bg-muted`   | `text-muted-foreground`   | (already muted)    | `text-muted-foreground`         |

- Each status role defines `--<role>`, `--<role>-foreground`, `--<role>-muted` and `--<role>-muted-foreground` in both `:root` (light) and `.dark`, mapped to `--color-*` in `@theme` of `resources/css/app.css`. Read the values there; do not copy them elsewhere.
- Pair solid fills with `-foreground`, and muted surfaces with `-muted-foreground`. Browser-resolved sRGB measurements for the 16 success/warning/danger/info light/dark pairs were at least 5.07:1. Recheck contrast when changing values or introducing new combinations.
- Badges and callouts use the muted surface with its `-muted-foreground` text (`bg-success-muted text-success-muted-foreground`). Solid fills (`bg-danger text-danger-foreground`) are for emphasis such as alerts and buttons.
- Filament admin: `App\Providers\Filament\AdminPanelProvider::SEMANTIC_COLORS` mirrors the color values from `resources/css/app.css`, because the panel does not load the React stylesheet. Keep those two palettes paired. Separately, the TypeScript domain-status mapping mirrors `App\Support\Filament\BadgeStyles::DOMAIN_TONES`.
- Filament primary is the default UBG Blue: solid `#2563eb` in light, `#1d4ed8` in dark, foreground `#ffffff` in both. This matches what the React app applies at runtime from the default `ubg-blue` preset in `resources/js/hooks/use-primary-color.ts` (it writes inline `--primary`, `--ring` and `--sidebar-primary` on the root), and the CSS fallbacks in `resources/css/app.css` carry the same values. The optional React appearance presets (emerald, sunset, rose, indigo, teal) remain React-specific: Filament does not follow them, and a user-selected React preset does not change the admin panel.
- Status palettes are derived from those constants (shade 400 = dark solid, 500 = light solid, 600 = light muted-foreground, 50 = muted surface); primary uses a blue ramp. Filament chooses button shades dynamically, so palettes alone cannot guarantee token matching. `resources/views/filament/semantic-colors.blade.php`, rendered at `PanelsRenderHook::STYLES_AFTER`, pins solid `.fi-btn` backgrounds/foregrounds and `.fi-badge` muted surfaces/text to the light/dark tokens. Outlined, gray and disabled states retain Filament behavior.
- Use opacity modifiers on tokens (`bg-success/10`) when no token fits; never switch to a palette hue.

## Prohibited: raw palettes and redeclared patterns

- Do not use raw Tailwind palette hues (`bg-amber-100`, `text-emerald-700`, `dark:bg-red-950`, `border-sky-200/60`, ...) to express status. Use the semantic tokens above or `StatusBadge`.
- Do not copy a pattern's markup into a page (own page header, stat card, empty state, filter pills, key/value list). Import the pattern. If it lacks something, extend the pattern.
- ESLint enforces the palette rule as a **warning** for `resources/js/pages/**/*.tsx` (`no-restricted-syntax` in `eslint.config.js`). It flags string literals and template fragments, including `cn(...)`/ternary/`clsx` arguments, variants and opacities. Semantic classes never warn. Warnings do not fail `npm run lint`; do not add new ones.
- The legacy `dosen/pesan-bimbingan.tsx` page retains its existing exemption from general lint rules, but the palette guard still checks it. Fixing its unrelated lint debt is outside this foundation change.

## Pattern inventory

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

### PageHeader

```tsx
import { PageHeader } from '@/components/patterns/page-header';

<PageHeader
    title="Sempro dan Sidang"
    description="Pantau jadwal dan hasil di program studi."
    actions={<Button>Ekspor</Button>}
/>;
```

Props: `title: ReactNode`, `description?: ReactNode`, `actions?: ReactNode`, `level?: 1 | 2 | 3` (default 1), `className?`. Renders a `header` with an `h1` by default.

### SectionCard

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

### FilterBar and FilterPills

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

### StatusBadge and status mapping

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

### StatCard and EmptyState

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

### ActionQueue and KeyValueList

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

## Migration (#34)

Phased, one area per PR.

1. **Foundation (#5, this change):** tokens, patterns, status mapping, guard, and one adopted page (`kaprodi/sempro-sidang`). Other pages are not migrated.
2. **Migration (#34):** move remaining pages to patterns and tokens by role area (mahasiswa, dosen, kaprodi, admin). Each migrated page should drop its palette warnings; that is how progress is tracked.
3. **Tighten:** when `eslint` reports no palette warnings, raise the rule to `error` in `eslint.config.js`.

Until a page is migrated, existing warnings there are accepted. New or touched pages must not add any.
