import fs from 'node:fs';
import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { catalogFixedNow } from '../support/catalog-env';
import {
    catalogPages,
    catalogThemes,
    catalogWidths,
    roleAccounts,
} from '../support/catalog-pages';
import { repositoryRoot } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

// Screenshot catalog (issue #127). Run with `npm run catalog`; the normal e2e
// project ignores this file. Nothing here changes data, so pages can be
// sharded and run in any order after the bootstrap setup.
const outputRoot = path.resolve(
    repositoryRoot,
    process.env.CATALOG_OUTPUT ?? 'catalog-output',
);

const freezeAnimations = `
    *, *::before, *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
        caret-color: transparent !important;
    }
`;

function shoot(page: Page): Promise<Buffer> {
    return page.screenshot({
        fullPage: true,
        animations: 'disabled',
        caret: 'hide',
    });
}

const names = new Set<string>();

for (const entry of catalogPages) {
    const key = `${entry.role}/${entry.name}`;

    if (names.has(key)) {
        throw new Error(`Duplicate catalog entry ${key}`);
    }

    names.add(key);

    test(`${key} (${entry.path})`, async ({ browser }) => {
        const account = entry.account ?? roleAccounts[entry.role];
        const directory = path.join(outputRoot, entry.role);

        fs.mkdirSync(directory, { recursive: true });

        for (const { width, height } of catalogWidths) {
            for (const theme of catalogThemes) {
                // A fresh context per capture: only this role's auth state.
                const context = await browser.newContext({
                    storageState: account
                        ? authStatePath(account)
                        : { cookies: [], origins: [] },
                    viewport: { width, height },
                    deviceScaleFactor: 1,
                    colorScheme: theme,
                    reducedMotion: 'reduce',
                    locale: 'id-ID',
                    timezoneId: 'Asia/Makassar',
                });

                try {
                    await context.clock.setFixedTime(new Date(catalogFixedNow));
                    // Inertia pages read `appearance`, Filament reads `theme`.
                    await context.addInitScript((value) => {
                        localStorage.setItem('appearance', value);
                        localStorage.setItem('theme', value);
                    }, theme);

                    const page = await context.newPage();
                    const response = await page.goto(entry.path);

                    expect(response?.ok(), `${entry.path} responded`).toBe(
                        true,
                    );
                    await page.waitForLoadState('networkidle');

                    // Never shoot a login redirect or an error page.
                    expect(new URL(page.url()).pathname).toBe(
                        new URL(entry.path, 'http://catalog.test').pathname,
                    );
                    await expect(
                        page
                            .getByRole('heading', { name: entry.heading })
                            .first(),
                    ).toBeVisible();
                    await expect(page.locator('html')).toHaveClass(
                        theme === 'dark' ? /dark/ : /^(?!.*\bdark\b).*$/,
                    );

                    await page.addStyleTag({ content: freezeAnimations });

                    if (entry.prepare) {
                        await entry.prepare(page);
                    }

                    await page.evaluate(() => document.fonts.ready);
                    await page.waitForLoadState('networkidle');
                    // Two frames so layout and fonts have settled.
                    await page.evaluate(
                        () =>
                            new Promise<void>((resolve) =>
                                requestAnimationFrame(() =>
                                    requestAnimationFrame(() => resolve()),
                                ),
                            ),
                    );

                    // Chevrons and badges can still be a pixel off when the
                    // last Alpine/Livewire tick lands; shoot until two
                    // consecutive captures are identical.
                    let image = await shoot(page);

                    for (let attempt = 0; attempt < 10; attempt += 1) {
                        await page.waitForTimeout(150);

                        const next = await shoot(page);
                        const settled = next.equals(image);

                        image = next;

                        if (settled) {
                            break;
                        }
                    }

                    fs.writeFileSync(
                        path.join(
                            directory,
                            `${entry.name}-${width}-${theme}.png`,
                        ),
                        image,
                    );
                } finally {
                    await context.close();
                }
            }
        }
    });
}
