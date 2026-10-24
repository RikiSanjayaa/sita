import { expect, test, type Page } from '@playwright/test';

import {
    createPublicMotionTopics,
    type PublicMotionTopics,
} from '../support/public-motion';

// Guest-visible pages; this spec owns its fixture topics and never touches
// shared seed rows or accounts.
test.use({ storageState: { cookies: [], origins: [] } });

let fixture: PublicMotionTopics;

test.beforeAll(() => {
    fixture = createPublicMotionTopics();
});

test.afterAll(() => {
    fixture?.cleanup();
});

const routes = [
    { path: '/', heading: 'Akses cepat ke ritme karya akhir' },
    { path: '/topik', heading: 'Topik Karya Akhir' },
    { path: '/jadwal', heading: 'Jadwal' },
    { path: '/pembimbing', heading: 'Direktori Pembimbing' },
    { path: '/mahasiswa-aktif', heading: 'Mahasiswa Karya Akhir Aktif' },
];

type IntroRecord = {
    type: 'start' | 'end' | 'cancel';
    /** Computed opacity of the animated element when the event fired. */
    opacity: string;
    containsHeading: boolean;
    outsideIntroChrome: boolean;
};

type IntroWindow = Window & {
    __intro?: IntroRecord[];
    __introPause?: boolean;
    __introStop?: () => void;
};

/**
 * Installs a bounded observer before any document script runs, so the 180ms
 * entrance cannot be missed by sampling after navigation. The optional pause
 * freezes the entrance mid-flight for deterministic inspection (harness only).
 */
async function observeIntro(page: Page, pause = false): Promise<void> {
    await page.addInitScript((shouldPause) => {
        const w = window as IntroWindow;
        w.__intro = [];
        const record =
            (type: IntroRecord['type']) =>
            (event: AnimationEvent): void => {
                if (
                    event.animationName !== 'public-intro-enter' ||
                    (w.__intro?.length ?? 0) >= 20
                ) {
                    return;
                }
                const target = event.target as HTMLElement;
                w.__intro?.push({
                    type,
                    opacity: getComputedStyle(target).opacity,
                    containsHeading: target.querySelector('h1, h2') !== null,
                    outsideIntroChrome:
                        target.closest('header, footer, nav') === null &&
                        target.querySelector('table, nav') === null,
                });
                if (type === 'start' && shouldPause) {
                    target.getAnimations().forEach((a) => a.pause());
                }
            };
        const start = record('start');
        const end = record('end');
        const cancel = record('cancel');
        document.addEventListener('animationstart', start, true);
        document.addEventListener('animationend', end, true);
        document.addEventListener('animationcancel', cancel, true);
        w.__introStop = () => {
            document.removeEventListener('animationstart', start, true);
            document.removeEventListener('animationend', end, true);
            document.removeEventListener('animationcancel', cancel, true);
        };
    }, pause);
}

async function introRecords(page: Page): Promise<IntroRecord[]> {
    return page.evaluate(() => (window as IntroWindow).__intro ?? []);
}

async function stopObserving(page: Page): Promise<void> {
    await page.evaluate(() => (window as IntroWindow).__introStop?.());
}

const countOf = (records: IntroRecord[], type: IntroRecord['type']): number =>
    records.filter((record) => record.type === type).length;

for (const route of routes) {
    test(`first load of ${route.path} plays one entrance on the title group only`, async ({
        page,
    }, testInfo) => {
        await observeIntro(page);

        for (const viewport of [
            { width: 1280, height: 900 },
            { width: 390, height: 844 },
        ]) {
            for (const theme of ['light', 'dark'] as const) {
                await page.setViewportSize(viewport);
                await page.emulateMedia({ colorScheme: theme });
                await page.goto(route.path);

                await expect(
                    page.getByRole('heading', { name: route.heading }).first(),
                ).toBeVisible();
                await expect
                    .poll(async () => countOf(await introRecords(page), 'end'))
                    .toBe(1);

                const records = await introRecords(page);
                expect(countOf(records, 'start')).toBe(1);
                expect(countOf(records, 'cancel')).toBe(0);
                // Text was readable from the first frame, and the animated
                // element is the heading group, not header, nav or tables.
                expect(Number(records[0].opacity)).toBeGreaterThanOrEqual(0.9);
                expect(records[0].containsHeading).toBe(true);
                expect(records[0].outsideIntroChrome).toBe(true);

                await testInfo.attach(
                    `intro-${route.path.replaceAll('/', '_')}-${viewport.width}-${theme}`,
                    {
                        body: await page.screenshot(),
                        contentType: 'image/png',
                    },
                );
            }
        }

        await stopObserving(page);
    });
}

test('searching, filtering and paginating /topik never replays the entrance', async ({
    page,
}) => {
    await observeIntro(page);
    await page.goto('/topik');
    await expect(
        page.getByRole('heading', { name: 'Topik Karya Akhir' }),
    ).toBeVisible();
    await expect
        .poll(async () => countOf(await introRecords(page), 'end'))
        .toBe(1);

    const heading = page.getByRole('heading', { name: 'Topik Karya Akhir' });
    const rows = page.getByRole('button', { name: 'Buka detail topik' });
    const search = page.getByPlaceholder(
        'Cari judul, ringkasan, mahasiswa, atau NIM...',
    );

    await search.fill(fixture.prefix);
    await expect(page).toHaveURL(
        new RegExp(`search=${encodeURIComponent(fixture.prefix)}`),
    );
    await expect(rows).toHaveCount(10);
    await expect(heading).toBeVisible();

    await page.getByRole('combobox', { name: 'Program studi' }).click();
    await page.getByRole('option', { name: fixture.programName }).click();
    await expect(page).toHaveURL(
        new RegExp(`program=${encodeURIComponent(fixture.programSlug)}`),
    );
    await expect(rows).toHaveCount(10);

    const year = String(new Date().getFullYear());
    await page.getByRole('radio', { name: new RegExp(`^${year}`) }).click();
    await expect(page).toHaveURL(new RegExp(`year=${year}`));
    await expect(rows).toHaveCount(6);

    await page.getByRole('radio', { name: /^Semua/ }).click();
    await expect(page).not.toHaveURL(/year=/);
    await expect(rows).toHaveCount(10);
    await page.getByRole('button', { name: 'Berikutnya' }).first().click();
    await expect(page).toHaveURL(/page=2/);
    await expect(rows).toHaveCount(2);

    await page
        .getByRole('combobox', { name: 'Per halaman' })
        .first()
        .selectOption('15');
    await expect(page).toHaveURL(/per_page=15/);
    await expect(rows).toHaveCount(12);

    await search.fill('');
    await expect(page).not.toHaveURL(/search=/);
    await expect(heading).toBeVisible();

    const records = await introRecords(page);
    expect(countOf(records, 'start')).toBe(1);
    expect(countOf(records, 'end')).toBe(1);
    await stopObserving(page);
});

test('navigating by pointer, keyboard and history does not start another entrance', async ({
    page,
}) => {
    await observeIntro(page);
    await page.goto('/jadwal');
    await expect
        .poll(async () => countOf(await introRecords(page), 'end'))
        .toBe(1);

    const nav = (name: string) =>
        page.getByRole('link', { name, exact: true }).first();

    await nav('Topik').click();
    await expect(page).toHaveURL(/\/topik$/);
    await expect(
        page.getByRole('heading', { name: 'Topik Karya Akhir' }),
    ).toBeVisible();

    await nav('Pembimbing').focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/pembimbing$/);
    await expect(
        page.getByRole('heading', { name: 'Direktori Pembimbing' }),
    ).toBeVisible();

    await page.goBack();
    await expect(page).toHaveURL(/\/topik$/);
    await expect(
        page.getByRole('heading', { name: 'Topik Karya Akhir' }),
    ).toBeVisible();
    await page.goForward();
    await expect(page).toHaveURL(/\/pembimbing$/);

    await nav('Beranda').click();
    await expect(page).toHaveURL(/\/$/);
    await expect(
        page.getByRole('heading', { name: routes[0].heading }),
    ).toBeVisible();

    const records = await introRecords(page);
    expect(countOf(records, 'start')).toBe(1);
    await stopObserving(page);
});

test('a full reload starts one fresh entrance', async ({ page }) => {
    await observeIntro(page);
    await page.goto('/topik');
    await expect
        .poll(async () => countOf(await introRecords(page), 'end'))
        .toBe(1);

    await page.reload();
    await expect
        .poll(async () => countOf(await introRecords(page), 'end'))
        .toBe(1);
    expect(countOf(await introRecords(page), 'start')).toBe(1);
    await stopObserving(page);
});

test.describe('reduced motion', () => {
    test.use({ contextOptions: { reducedMotion: 'reduce' } });

    for (const route of routes) {
        test(`${route.path} is settled with no entrance`, async ({ page }) => {
            await observeIntro(page);
            await page.goto(route.path);

            const heading = page
                .getByRole('heading', { name: route.heading })
                .first();
            await expect(heading).toBeVisible();
            await expect(heading).toHaveCSS('opacity', '1');
            await expect(
                page.getByRole('link', { name: 'Topik', exact: true }).first(),
            ).toBeVisible();

            expect(await introRecords(page)).toHaveLength(0);
            await stopObserving(page);
        });
    }

    test('changing to normal motion later does not start an entrance', async ({
        page,
    }) => {
        await observeIntro(page);
        await page.goto('/topik');
        await expect(
            page.getByRole('heading', { name: 'Topik Karya Akhir' }),
        ).toBeVisible();

        await page.emulateMedia({ reducedMotion: 'no-preference' });
        await page
            .getByPlaceholder('Cari judul, ringkasan, mahasiswa, atau NIM...')
            .fill(fixture.prefix);
        await expect(page).toHaveURL(/search=/);

        expect(await introRecords(page)).toHaveLength(0);
        await stopObserving(page);
    });
});

test('switching to reduced motion mid-entrance cancels it and switching back cannot restart it', async ({
    page,
}) => {
    // The harness freezes the real entrance after it starts.
    await observeIntro(page, true);
    await page.goto('/topik');
    await expect
        .poll(async () => countOf(await introRecords(page), 'start'))
        .toBe(1);
    expect(countOf(await introRecords(page), 'end')).toBe(0);

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect
        .poll(async () => countOf(await introRecords(page), 'cancel'))
        .toBe(1);
    const heading = page.getByRole('heading', { name: 'Topik Karya Akhir' });
    await expect(heading).toBeVisible();
    await expect(heading).toHaveCSS('opacity', '1');

    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page
        .getByPlaceholder('Cari judul, ringkasan, mahasiswa, atau NIM...')
        .fill(fixture.prefix);
    await expect(page).toHaveURL(/search=/);

    const records = await introRecords(page);
    expect(countOf(records, 'start')).toBe(1);
    expect(countOf(records, 'end')).toBe(0);
    await stopObserving(page);
});
