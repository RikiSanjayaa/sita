import fs from 'node:fs';

import { chromium, expect, test, type Browser } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
import {
    findFreePort,
    formatCounts,
    runDetector,
} from '../support/design-detector';
import {
    authStatePath,
    type PlaywrightAccountKey,
} from '../support/thesis-fixtures';

type Area = 'public' | 'mahasiswa' | 'dosen' | 'kaprodi';

/**
 * Areas whose findings fail the build; every other area is advisory and the
 * spec only fails when its scan cannot run. When an area has been overhauled
 * and scans clean, add it here; from then on any non-advisory finding on its
 * pages fails.
 */
const BLOCKING_AREAS: readonly Area[] = ['public', 'dosen', 'kaprodi'];

/**
 * Pages inside a blocking area that stay advisory, with the reason. Keep this
 * list short; each entry is a decision by the product owner.
 */
const ADVISORY_PAGES: Readonly<Record<string, string>> = {
    '/dosen/pesan':
        'The chat shell is shared with mahasiswa; its findings are fixed in the chat components of the mahasiswa overhaul (#83).',
    '/': 'Home keeps its existing design; the product owner rejected the flattened version in #82.',
};

type AreaPages = {
    area: Area;
    /** Auth state to browse with; null browses as a guest. */
    account: PlaywrightAccountKey | null;
    paths: readonly string[];
};

const areas: readonly AreaPages[] = [
    {
        area: 'public',
        account: null,
        paths: ['/', '/jadwal', '/pembimbing', '/topik', '/mahasiswa-aktif'],
    },
    {
        area: 'mahasiswa',
        account: 'mahasiswa',
        paths: [
            '/mahasiswa/dashboard',
            '/mahasiswa/tugas-akhir',
            '/mahasiswa/jadwal-bimbingan',
            '/mahasiswa/upload-dokumen',
            '/mahasiswa/pesan',
        ],
    },
    {
        area: 'dosen',
        account: 'dosen1',
        paths: [
            '/dosen/dashboard',
            '/dosen/seminar-proposal',
            '/dosen/mahasiswa-bimbingan',
            '/dosen/jadwal-bimbingan',
            '/dosen/dokumen-revisi',
            '/dosen/pesan',
        ],
    },
    {
        area: 'kaprodi',
        account: 'kaprodi',
        paths: [
            '/kaprodi/dashboard',
            '/kaprodi/mahasiswa',
            '/kaprodi/sempro-sidang',
            '/kaprodi/dokumen',
            '/kaprodi/dosen-prodi',
        ],
    },
];

const viewports = [
    { name: '1280', width: 1280, height: 800 },
    { name: '390', width: 390, height: 844 },
] as const;

type PageResult = { label: string; summary: string; total: number };

const results: PageResult[] = [];

// The detector attaches through the DevTools protocol, so this spec drives its
// own Chromium with a remote debugging port instead of the fixture browser.
// Everything else (contexts, auth states, baseURL) is the normal Playwright API.
let browser: Browser;
let debugPort: number;

test.describe('Design detector (impeccable detect --cdp)', () => {
    test.beforeAll(async () => {
        seedPlaywrightScenarios();

        debugPort = await findFreePort();
        browser = await chromium.launch({
            executablePath: process.env.PLAYWRIGHT_EXECUTABLE_PATH,
            args: [`--remote-debugging-port=${debugPort}`],
        });
    });

    test.afterAll(async () => {
        await browser?.close();
        reportSummary();
    });

    for (const { area, account, paths } of areas) {
        for (const path of paths) {
            for (const viewport of viewports) {
                const label = `${area} ${path} @${viewport.name}`;

                test(label, async ({ baseURL }, testInfo) => {
                    const context = await browser.newContext({
                        baseURL,
                        viewport: {
                            width: viewport.width,
                            height: viewport.height,
                        },
                        storageState: account
                            ? authStatePath(account)
                            : { cookies: [], origins: [] },
                    });

                    try {
                        const page = await context.newPage();
                        const response = await page.goto(path);

                        expect(
                            response?.status(),
                            `${path} responded with an error`,
                        ).toBeLessThan(400);

                        if (account) {
                            // A lost session lands on /login; scanning that would
                            // report on the wrong page.
                            expect(
                                new URL(page.url()).pathname,
                                `${path} redirected to the login page`,
                            ).not.toBe('/login');
                        }

                        await page
                            .waitForLoadState('networkidle', {
                                timeout: 10_000,
                            })
                            .catch(() => undefined);
                        await page.evaluate(() => document.fonts.ready);

                        const scan = await runDetector({
                            port: debugPort,
                            target: page.url(),
                        });
                        const summary = formatCounts(scan.counts);

                        testInfo.annotations.push({
                            type: 'design-detector',
                            description: `${label}: ${scan.all.length} findings (${summary})`,
                        });
                        await testInfo.attach(
                            `design-detector-${area}${path.replaceAll('/', '_')}-${viewport.name}.json`,
                            {
                                body: JSON.stringify(scan.all, null, 2),
                                contentType: 'application/json',
                            },
                        );
                        results.push({
                            label,
                            summary,
                            total: scan.all.length,
                        });

                        if (
                            BLOCKING_AREAS.includes(area) &&
                            !(path in ADVISORY_PAGES)
                        ) {
                            expect(
                                scan.blocking.map(
                                    (finding) =>
                                        `${finding.antipattern}: ${finding.snippet ?? ''}`,
                                ),
                                `${label} has blocking design findings`,
                            ).toEqual([]);
                        }
                    } finally {
                        await context.close();
                    }
                });
            }
        }
    }
});

function reportSummary(): void {
    if (results.length === 0) {
        return;
    }

    const lines = [
        '### Design detector (advisory)',
        '',
        `Blocking areas: ${BLOCKING_AREAS.length > 0 ? BLOCKING_AREAS.join(', ') : 'none'}`,
        '',
        '| Page | Findings | Rules |',
        '| --- | ---: | --- |',
        ...results.map(
            ({ label, summary, total }) =>
                `| ${label} | ${total} | ${summary} |`,
        ),
        '',
    ];

    console.log(lines.join('\n'));

    // Shown on the workflow run page in CI; the per-finding JSON is attached
    // to each test in the HTML report.
    if (process.env.GITHUB_STEP_SUMMARY) {
        fs.appendFileSync(
            process.env.GITHUB_STEP_SUMMARY,
            `${lines.join('\n')}\n`,
        );
    }
}
