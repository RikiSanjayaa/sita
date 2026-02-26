# AGENTS.md

Compact operating guide for coding agents in this repository.

## Project Snapshot

- Stack: Laravel 12, PHP 8.4, Inertia v2, React 19, Tailwind v4, Reverb, Pest 4.
- Main areas: backend (`app/`, `routes/`, `database/`), frontend (`resources/js/`).
- Real-time features use Echo + Reverb and depend on Reverb env vars.

## Core Rules

- Follow existing patterns in nearby files before introducing new ones.
- Keep changes focused; avoid unrelated refactors.
- Do not change dependencies without explicit approval.
- Do not modify secrets or commit `.env` values.
- Do not commit unless explicitly asked.
- Never revert unrelated user changes in a dirty worktree.
- Use ASCII by default in code/docs.

## Laravel / PHP Rules

- Prefer Laravel conventions over custom patterns.
- Use `php artisan make:* --no-interaction` for scaffolding.
- Use Form Requests for validation (avoid inline validation for complex inputs).
- Prefer Eloquent relationships and eager loading (`with`) to avoid N+1.
- Add explicit parameter and return types.
- Use constructor property promotion when suitable.
- Use `casts()` in models when following existing model style.

## Inertia / React Rules

- Use Inertia navigation (`router.visit`, `<Link>`) over plain anchors.
- Keep pages in existing project structure (`resources/js/pages/...`).
- Keep TS strict; avoid `any` where possible.
- Reuse existing UI primitives/components before creating new ones.

## Tailwind Rules

- Use Tailwind v4 utilities only.
- Keep classes readable and consistent with existing UI patterns.
- Prefer `gap-*` for list spacing instead of margin stacking.

## Testing & Quality Gates

- All functional changes require tests (Pest).
- Run minimum targeted tests first:
    - `php artisan test --compact tests/Feature/...`
    - or `php artisan test --compact --filter=...`
- If PHP files changed, run formatter: `vendor/bin/pint --dirty`.
- If TS/React files changed, run: `npm run types`.

## Key Commands

- Install deps: `composer install` and `npm install`
- Dev stack: `composer run dev`
- Build: `npm run build`
- Lint PHP: `composer run lint`
- Lint JS: `npm run lint`
- Format JS: `npm run format`
- Full test script: `composer run test`
- CI verify hook target: `composer run ci:verify`

## Realtime Checklist

- Ensure env has Reverb + Vite Reverb keys.
- If blank screen shows missing Pusher app key, check `VITE_REVERB_APP_KEY`.
- If frontend changes do not appear, run `npm run dev` or `npm run build`.

## Agent Workflow

- Prefer specialized tools for file edits/search over ad-hoc shell commands.
- Search Laravel docs first for Laravel ecosystem work (`search-docs`).
- Summarize changes with touched file paths and what was validated.
