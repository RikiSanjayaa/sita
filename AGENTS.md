# AGENTS.md

Compact operating guide for coding agents in this repository.

## Project Snapshot

- Stack: Laravel 12, PHP 8.4, Inertia v2, React 19, Tailwind v4, Reverb, Pest 4, Playwright.
- Main areas: backend (`app/`, `routes/`, `database/`), frontend (`resources/js/`).
- Real-time features use Echo + Reverb and depend on Reverb env vars.
- UI: Uses Radix UI primitives, Lucide icons, class-variance-authority (cva), clsx, tailwind-merge.
- Deployment: Docker via `scripts/deploy-via-compose.sh` (see `README.md`); aaPanel via `deploy/aapanel-*.sh` (see `docs/AAPANEL_DEPLOYMENT.md`).

## Domain

- SITA documents thesis progress. Build the workflow an issue asks for and nothing more; informal coordination (agreeing on dates, confirming attendance) happens on WhatsApp or in person, outside the app.
- The thesis lifecycle lives in `ThesisProject` and its `Thesis*` models: `phase` moves `title_review` -> `sempro` -> `research` -> `sidang` -> `completed`; `ThesisDefense.type` is `sempro` or `sidang`; supervisors are `ThesisSupervisorAssignment`.
- `Sempro`, `SemproExaminer`, `SemproRevision`, `ThesisSubmission`, and `MentorshipAssignment` are legacy remnants kept for migrated data. Build new features on the `Thesis*` models.
- Academic labels differ per degree (D3/S1/S2) and per audience (one student vs. cross-degree lists). Take every user-facing label from `App\Support\AcademicTerminology` (PHP) or `resources/js/lib/academic-terminology.ts` (TS); `docs/GLOSSARY.md` lists which label to use and the retired phrases ("Proyek", "Ujian Akhir", ...) a Pest test rejects.
- Kaprodi users also hold the dosen role.
- Additional Sidang examiners (`thesis_defense_examiners.role = examiner`) stay secret from every mahasiswa until `thesis_defenses.status = completed`; supervisors on the panel, Sempro and Sinopsis examiners are always visible. Any payload, notification or chat text for a mahasiswa that names a panel member goes through `ThesisDefense::examinersForStudent()`, `SecretSidangExaminers` or the `secretFromStudent` scope, never through `$defense->examiners` directly.

## Core Rules

- Follow existing patterns in nearby files before introducing new ones.
- Keep changes focused; avoid unrelated refactors.
- Do not change dependencies without explicit approval.
- Do not modify secrets or commit `.env` values.
- Do not commit unless explicitly asked.
- Write commit messages (and PR titles) as Conventional Commits: `feat:`, `fix:`, `docs:`, `test:`, `ci:`, `chore:`, `refactor:`; `!` or a `BREAKING CHANGE:` footer for breaking changes. release-please reads them to pick the next version and write `CHANGELOG.md`, so `feat` and `fix` are reserved for user-visible changes.
- Never revert unrelated user changes in a dirty worktree.
- Use ASCII by default in code/docs.

## Key Commands

### Installation & Development

- Install deps: `composer install` and `npm install`
- Dev stack: `composer run dev` (runs Vite, Artisan serve, queue, Reverb)
- Dev with SSR: `composer run dev:ssr`
- Build: `npm run build`

### Linting & Formatting

- Lint PHP: `composer run lint` (runs Pint in parallel)
- Format JS: `npm run format` (runs Prettier)
- Check JS format: `npm run format:check`

### Type Checking

- TypeScript: `npm run types`
- Lint JS/TS: `npm run lint` (runs ESLint --fix)

### Testing

- Run all tests: `php artisan test` or `composer run test` (includes lint first)
- Run all tests (compact): `php artisan test --compact`
- Run specific test file: `php artisan test --compact tests/Feature/ExampleTest.php`
- Run tests matching filter: `php artisan test --compact --filter=testName`
- Run Playwright E2E: `npm run e2e`
- Run Playwright E2E headed: `npm run e2e:headed`
- Run Playwright UI runner: `npm run e2e:ui`
- Run one Playwright spec: `npx playwright test tests/e2e/specs/session-isolation.spec.ts`
- CI verify hook: `composer run ci:verify`

## Laravel / PHP Rules

- Prefer Laravel conventions over custom patterns.
- Use `php artisan make:* --no-interaction` for scaffolding.
- Use Form Requests for validation (avoid inline validation for complex inputs).
- Prefer Eloquent relationships and eager loading (`with`) to avoid N+1.
- Add explicit parameter and return types on all methods.
- Use constructor property promotion when suitable.
- Use `casts()` method in models rather than `$casts` property.
- Use curly braces for all control structures, even single-line.
- Run `vendor/bin/pint --dirty` after PHP changes.

### Imports

- Use alphabetical import ordering.
- Group imports: PHP built-ins, then vendor, then app namespaces.
- Example:

```php
use App\Enums\AppRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
```

### Naming Conventions

- Models: `PascalCase` (e.g., `User`, `MahasiswaProfile`)
- Controllers: `PascalCase` with `Controller` suffix (e.g., `DashboardController`)
- Methods: `camelCase`
- Relationships: `camelCase` (e.g., `mahasiswaProfile()`, `mentorshipAssignmentsAsStudent()`)
- Database columns: `snake_case`
- Enums: TitleCase keys (e.g., `Mahasiswa`, `Dosen`)

### Error Handling

- Use Laravel exceptions for domain errors.
- Return appropriate HTTP status codes.
- Use `abort()` or throw exceptions for invalid states.

## Inertia / React Rules

- Use Inertia navigation (`router.visit`, `<Link>`) over plain anchors.
- Keep pages in existing project structure (`resources/js/pages/...`).
- Use Wayfinder for type-safe route generation (import from `@/actions/...`).
- Keep TS strict; avoid `any`.
- Reuse existing UI primitives/components before creating new ones.
- Use `<Form>` component for forms with method/action props.

### React Component Structure

- Import icons from `lucide-react`.
- Use `cva` for variant components.
- Use `clsx` and `tailwind-merge` for conditional classes.
- Extract reusable components to `resources/js/components/`.
- Before implementing a page or changing an existing page's design, follow "UI work" below, and use the shared patterns and semantic status tokens in `DESIGN.md`. Keep raw Tailwind palette colors out of status styling.

### Imports Order

1. External (react, @inertiajs/react)
2. Internal (@/components, @/layouts, @/routes, @/types)
3. Relative imports

## UI work

- Before any UI change read `.claude/skills/impeccable/SKILL.md` and follow its Setup, then `PRODUCT.md` and `DESIGN.md`. `DESIGN.md` holds the design system rules (tokens, patterns, prohibited palettes, Filament pairing).
- Verify by viewing the rendered page at 1280px and 390px wide, in light and dark.
- Every pull request that adds, changes or removes UI includes screenshots so it can be reviewed without checking out the branch: each touched page at 1280px and 390px, light and dark, plus any changed state (dialog, sheet, empty, error). For a redesign add the matching "before" shots. Capture seeded demo data only (the repository is public). Upload with `scripts/pr-screenshots.sh <key> <images...>` (key such as `pr-123-dashboard`); it pushes the images to the `pr-assets` branch and prints Markdown to paste under a "Screenshots" heading in the PR body, or in a comment on the PR or the related issue.
- Run the detector spec for the areas you touched: `npx playwright test tests/e2e/specs/design-detector.spec.ts --project=chromium`. It is advisory until an area is listed in `BLOCKING_AREAS` in that spec; an area on that list must have zero findings.
- The engine binary is not committed. The launcher `.claude/skills/impeccable/scripts/impeccable` downloads the pinned version into `~/.impeccable/bin` on first use.

## Tailwind Rules

- Use Tailwind v4 utilities only.
- Keep classes readable and consistent with existing UI patterns.
- Prefer `gap-*` for list spacing instead of margin stacking.
- Use `@import "tailwindcss"` (not `@tailwind` directives).
- Configure theme using `@theme` directive in CSS.
- Don't use deprecated v3 utilities (e.g., use `bg-black/50` instead of `bg-opacity-50`).

## Testing & Quality Gates

- All backend/domain changes require tests (Pest).
- Tests live in `tests/Feature/` and `tests/Unit/`.
- Use Pest syntax: `it('description', function () { ... });`
- Run targeted tests first, then full suite if requested.
- Use datasets for parameterized tests.

### Playwright E2E

- E2E specs live in `tests/e2e/specs/`.
- Playwright support utilities live in `tests/e2e/support/`.
- Playwright uses a dedicated SQLite database at `database/playwright.sqlite` and auth state under `storage/playwright`.
- Playwright starts Laravel and Reverb with isolated test settings. The browser gets its Reverb key/host/port/scheme at runtime from the `reverb-config` meta tag (`App\Support\ReverbClientConfig`, from `REVERB_*`), so no build-time Reverb values exist; keep one worker because all specs share the SQLite database.
- Servers listen on 9010 (app) and 9011 (Reverb) and are reused if already running. To run Playwright in several checkouts at once, give each its own ports with `PLAYWRIGHT_APP_PORT` and `PLAYWRIGHT_REVERB_PORT`; otherwise a run can silently test another checkout's server.
- Restore seeded account identity in `finally` when a scenario changes a profile; later specs reuse the same users and auth states.
- `tests/e2e/setup/bootstrap.spec.ts` runs `optimize:clear`, `migrate:fresh --seed --force`, waits for `/up`, and captures login sessions.
- Prefer keeping E2E focused on user-visible multi-role integration flows; keep business logic coverage in Pest.
- A change to a user-visible flow adds or updates a Playwright scenario in the same change. Locally, run only the specs you added or changed (plus the design detector filtered with `-g` to the pages you touched) and the Pest files for the code you changed. The full Pest suite and the full Playwright suite run in CI on every pull request and push to `main` and `develop`; CI is the gate for them.
- CI splits Playwright into three shards (`e2e (shard N/3)`) plus one `e2e` result that passes only when every shard passes. When a shard fails, open the workflow run's Artifacts section and download `playwright-e2e-<run-id>-<attempt>-shard-<N>`. Extract it, then run `npx playwright show-report playwright-report` for the HTML report or `npx playwright show-trace test-results/<failed-test>/trace.zip` for a trace. Screenshots and videos are under `test-results/`; each shard's job summary records its duration.
- `tests/e2e/COVERAGE.md` is excluded from Prettier: add or edit single rows without re-aligning the whole table, so parallel branches do not conflict on it.
- Use stable selectors in E2E assertions. Prefer page titles, labels, roles, unique section copy, or scoped locators over generic repeated text.
- `test.fixme(...)` is acceptable for planned scenarios that are scaffolded but not yet stable enough to run in CI/local verification. Each fixme must have a comment linking its tracking issue.

### Screenshot catalog

- `npm run catalog` captures every page listed in `tests/e2e/support/catalog-pages.ts` (public, mahasiswa, dosen, kaprodi, admin, plus a few dialog, sheet and empty states) at 1280 and 390 px, light and dark, full page, into `catalog-output/<role>/<page>-<width>-<theme>.png`. It runs the normal bootstrap first (fresh seed), takes 8 to 18 minutes on one worker (CI shards it in four) and is not part of `npm run e2e`. Narrow it with `npm run catalog -- -g "mahasiswa/dashboard"`; add `--project=catalog` to skip the bootstrap on a database that is already seeded.
- Add a page with one entry in `catalog-pages.ts`. Its `heading` must be visible on the final URL, otherwise the run fails, so a redirect to a login screen can never end up in the catalog.
- Output is stable run to run: the browser clock (`page.clock`) and the server clock (`CATALOG_FIXED_NOW`, honoured only when `APP_ENV=playwright`) are pinned, animations are off, fonts are awaited and seed data is deterministic. Two runs on the same code give identical images apart from a few anti-aliased edge pixels (a handful of pixels off by a few levels on rounded borders); `scripts/catalog.mjs` ignores that noise (under 20 pixels off by more than 8 levels) when it compares and when it writes to `pr-assets`. A real difference without a code change means something is not deterministic and needs fixing.
- Every push to `main` runs `.github/workflows/screenshot-catalog.yml`, which commits the changed images to `catalog/` on the `pr-assets` branch (message `catalog: <sha> <subject>`) and regenerates `catalog/README.md`, the index of all pages. Read a change with `git log -p pr-assets -- catalog/<role>/` or in GitHub's image diff for a commit on that branch.
- A pull request that touches `resources/js`, `resources/css`, `resources/views` or `app/Filament` gets one automatic comment listing the changed pages with before (main catalog) and after images; the after images live under `pr-<n>/catalog/` on `pr-assets`. That comment is in addition to the screenshots described under "UI work", not a replacement: it does not cover new states or pages outside the list.

### Test Conventions

- Use `actingAs()` for authentication.
- Use factories for model creation.
- Assert with specific methods (`assertOk`, `assertForbidden`) over `assertStatus(200)`.

## Realtime Checklist

- Ensure env has Reverb + Vite Reverb keys.
- If blank screen shows missing Pusher app key, check `REVERB_APP_KEY` on the server (the `reverb-config` meta tag).
- If frontend changes do not appear, run `npm run dev` or `npm run build`.

## Agent Workflow

- Prefer specialized tools for file edits/search over ad-hoc shell commands.
- Search Laravel docs first for Laravel ecosystem work (`search-docs` tool).
- Planned work lives in GitHub issues under milestone `Revisi FT 2026-10`. Before starting one, read tracking issue #35 (order, blockers, files that conflict between issues), then work to the issue's acceptance criteria and its `Konvensi` section.
- Summarize changes with touched file paths and what was validated.
