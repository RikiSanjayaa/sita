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
- Before implementing a page or changing an existing page's design, read `docs/DESIGN_SYSTEM.md` and use its shared patterns and semantic status tokens. Keep raw Tailwind palette colors out of status styling.

### Imports Order

1. External (react, @inertiajs/react)
2. Internal (@/components, @/layouts, @/routes, @/types)
3. Relative imports

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
- Playwright starts Laravel and Reverb with isolated test settings. CI builds assets with matching `VITE_REVERB_*` values before starting the servers; keep one worker because all specs share the SQLite database.
- Restore seeded account identity in `finally` when a scenario changes a profile; later specs reuse the same users and auth states.
- `tests/e2e/setup/bootstrap.spec.ts` runs `optimize:clear`, `migrate:fresh --seed --force`, waits for `/up`, and captures login sessions.
- Prefer keeping E2E focused on user-visible multi-role integration flows; keep business logic coverage in Pest.
- A change to a user-visible flow adds or updates a Playwright scenario in the same change, and the affected specs plus relevant Pest tests are run before handing off. The `e2e` job in `.github/workflows/tests.yml` also runs the full suite on pull requests and pushes to `main` and `develop`.
- When CI E2E fails, open the workflow run's Artifacts section and download `playwright-e2e-<run-id>-<attempt>`. Extract it, then run `npx playwright show-report playwright-report` for the HTML report or `npx playwright show-trace test-results/<failed-test>/trace.zip` for a trace. Screenshots and videos are under `test-results/`; the job summary records suite duration.
- Use stable selectors in E2E assertions. Prefer page titles, labels, roles, unique section copy, or scoped locators over generic repeated text.
- `test.fixme(...)` is acceptable for planned scenarios that are scaffolded but not yet stable enough to run in CI/local verification. Each fixme must have a comment linking its tracking issue.

### Test Conventions

- Use `actingAs()` for authentication.
- Use factories for model creation.
- Assert with specific methods (`assertOk`, `assertForbidden`) over `assertStatus(200)`.

## Realtime Checklist

- Ensure env has Reverb + Vite Reverb keys.
- If blank screen shows missing Pusher app key, check `VITE_REVERB_APP_KEY`.
- If frontend changes do not appear, run `npm run dev` or `npm run build`.

## Agent Workflow

- Prefer specialized tools for file edits/search over ad-hoc shell commands.
- Search Laravel docs first for Laravel ecosystem work (`search-docs` tool).
- Planned work lives in GitHub issues under milestone `Revisi FT 2026-10`. Before starting one, read tracking issue #35 (order, blockers, files that conflict between issues), then work to the issue's acceptance criteria and its `Konvensi` section.
- Summarize changes with touched file paths and what was validated.
