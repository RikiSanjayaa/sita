// Imported for its side effects by playwright.catalog.config.ts, before the
// base config reads the environment.
//
// The server clock is pinned for the whole catalog run (seeding included) so
// relative dates ("3 hari lagi") render the same text on every run. Session
// cookies would otherwise be issued already expired, because the pinned date is
// not "now" for the browser; making them session-only avoids that.
export const catalogFixedNow = (process.env.CATALOG_FIXED_NOW ??=
    '2026-10-14T09:00:00+08:00');
process.env.SESSION_EXPIRE_ON_CLOSE ??= 'true';
