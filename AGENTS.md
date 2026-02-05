# AGENTS.md

This repo is a Laravel 12 + Inertia + React + Vite app. Use the commands and
style rules below when operating as an agent in this workspace.

No Cursor or Copilot instruction files were found in `.cursor/rules/`,
`.cursorrules`, or `.github/copilot-instructions.md` at the time of writing.

## Commands

Install deps

- PHP deps: `composer install`
- JS deps: `npm install`

Dev servers

- Full dev (server, queue, Vite): `composer run dev`
- SSR dev: `composer run dev:ssr`

Build

- Client build: `npm run build`
- SSR build: `npm run build:ssr`

Lint and format

- PHP lint (Pint): `composer run lint`
- PHP lint (check only): `composer run test:lint`
- JS lint (auto-fix): `npm run lint`
- JS format (write): `npm run format`
- JS format (check): `npm run format:check`
- Type check: `npm run types`

Tests

- Full test suite (includes Pint check): `composer run test`
- PHPUnit/Pest via artisan: `php artisan test`

Single test

- By class or method name: `php artisan test --filter NamePattern`
- By file: `php artisan test tests/Feature/ExampleTest.php`
- By Pest test name (string): `php artisan test --filter "test name"`
- Direct Pest: `vendor/bin/pest --filter NamePattern`

Useful one-offs

- Clear config cache: `php artisan config:clear`
- Run a single migration: `php artisan migrate --path=database/migrations/...`

## Repo layout

- Backend: `app/`, `routes/`, `database/`, `config/`, `tests/`
- Frontend: `resources/js/`, `resources/css/`, `resources/views/`
- Build config: `vite.config.ts`, `eslint.config.js`, `tsconfig.json`

## Code style guidelines

### General

- Follow existing patterns in the file and nearby modules.
- Prefer small, focused changes; avoid unrelated refactors.
- Keep changes ASCII unless the file already uses non-ASCII.
- Avoid adding comments unless a non-obvious decision needs explanation.

### PHP / Laravel

- Formatting: use Pint with the `laravel` preset (see `pint.json`).
- Naming:
    - Classes: StudlyCase.
    - Methods/vars: camelCase.
    - Constants: SCREAMING_SNAKE_CASE.
    - Files: follow Laravel conventions (models singular, controllers plural).
- Imports:
    - Use `use` statements; avoid fully-qualified names inline.
    - Group imports by vendor and app namespaces; keep them sorted.
- Types:
    - Add return types and parameter types where practical.
    - Use PHPDoc only when types are not expressible (generics, shapes).
- Error handling:
    - Use Laravel validation for request data.
    - Prefer `abort()` or exceptions for invalid state.
    - Avoid swallowing exceptions; log with context if caught.
- Eloquent:
    - Prefer query scopes for reusable constraints.
    - Avoid N+1 queries; eager load with `with()` when needed.
- Controllers:
    - Keep controllers thin; push logic into Actions/Services when complex.
    - Return Inertia responses consistently for UI routes.

### Tests (PHP)

- Framework: Pest + Laravel testing helpers.
- Place unit tests in `tests/Unit`, feature tests in `tests/Feature`.
- Use descriptive test names; keep fixtures minimal.
- Prefer factories over manual model construction.

### TypeScript / React (Inertia)

- Formatting: Prettier is the source of truth (`npm run format`).
- Linting: ESLint with React + import rules (`eslint.config.js`).
- Import order:
    - Enforced by eslint-plugin-import.
    - Groups: builtin, external, internal, parent, sibling, index.
    - Blank line between groups; alphabetize within group.
- Types:
    - Prefer explicit props/interfaces for components.
    - Avoid `any`; use `unknown` with narrowing if necessary.
    - Use `type` for unions and `interface` for object shapes when extending.
- Components:
    - Use functional components and hooks.
    - Prefer named exports for shared components; default export for pages.
- State and effects:
    - Keep effects narrow; include stable dependencies.
    - Avoid derived state; compute from props where possible.
- Error handling:
    - Handle async errors with try/catch and surface UI feedback.
    - Prefer Inertia error bags for form validation.

### Styling

- Tailwind CSS v4 is used via Vite.
- Use `clsx` + `tailwind-merge` patterns when combining classes.
- Keep class lists readable; consider `class-variance-authority` for variants.

### Inertia pages

- Keep page components under `resources/js/Pages`.
- Use `@inertiajs/react` helpers for forms, links, and page props.
- Preserve SSR entry at `resources/js/ssr.tsx`.

## Agent workflow expectations

- Run lint or tests only when needed; prefer targeted runs.
- Do not commit unless explicitly requested.
- Do not modify `.env` or secrets.
- Respect existing git state; do not reset or clean unrelated changes.

## Quick command matrix

- Dev: `composer run dev`
- Build: `npm run build`
- PHP lint: `composer run lint`
- JS lint: `npm run lint`
- Format: `npm run format`
- Types: `npm run types`
- Tests: `composer run test`
- Single test: `php artisan test --filter NamePattern`
