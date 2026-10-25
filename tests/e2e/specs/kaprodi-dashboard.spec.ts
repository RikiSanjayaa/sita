import { expect, test, type Locator, type Page } from '@playwright/test';

import {
    dashboardNames,
    populatedQueue,
    summaryTiles,
    withKaprodiDashboard,
    type KaprodiDashboardFixture,
    type QueueExpectation,
} from '../support/kaprodi-dashboard';

// The kaprodi dashboard against isolated fixtures (see support/kaprodi-dashboard.ts):
// a populated prodi, an empty one and a kaprodi limited to viewing documents.
// Every test signs in as the fixture kaprodi, so the shared seeded kaprodi and
// the scenario students other specs change are never read or written.
test.use({ storageState: { cookies: [], origins: [] } });

const gatedActions = /Tetapkan pembimbing|Jadwalkan|Atur kuota/;
// Descriptions of the secondary queue items; only the featured item may carry text beyond its label.
const secondaryDescriptions =
    /ditangguhkan|melewati|Belum punya jadwal|Tidak ada aktivitas|Menjelang|Jumlah mahasiswa/;

function regions(page: Page) {
    return {
        queue: page.getByRole('region', {
            name: 'Perlu tindakan',
            exact: true,
        }),
        summary: page.getByRole('navigation', { name: 'Ringkasan prodi' }),
        agenda: page.getByRole('region', {
            name: 'Hari ini & 7 hari ke depan',
            exact: true,
        }),
        progress: page.getByRole('region', {
            name: 'Progres mahasiswa',
            exact: true,
        }),
    };
}

function queueRows(page: Page): Locator {
    return regions(page)
        .queue.getByRole('list', { name: 'Perlu tindakan', exact: true })
        .locator(':scope > li');
}

function visibleQueue(fixture: KaprodiDashboardFixture): QueueExpectation[] {
    return populatedQueue.filter(
        (item) => fixture.scenario !== 'restricted' || !item.capability,
    );
}

async function expectNoOverflow(page: Page): Promise<void> {
    await expect
        .poll(() =>
            page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        )
        .toBe(true);
}

async function expectTargetSize(links: Locator): Promise<void> {
    for (const link of await links.all()) {
        const box = await link.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.width).toBeGreaterThanOrEqual(44);
        expect(box!.height).toBeGreaterThanOrEqual(44);
    }
}

async function expectDestination(page: Page, href: string): Promise<void> {
    const expected = new URL(href, 'http://sita.test');
    const opensScheduleDialog = expected.searchParams.has('jadwalkan');
    // The schedule dialog consumes this query while hiding background headings.
    expected.searchParams.delete('jadwalkan');
    await expect(page).toHaveURL((url) => {
        if (url.pathname !== expected.pathname) {
            return false;
        }

        return url.searchParams.toString() === expected.searchParams.toString();
    });
    if (!opensScheduleDialog) {
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    }
}

async function expectSummary(page: Page, counts: number[]): Promise<void> {
    const { summary } = regions(page);
    await expect(summary.getByRole('link')).toHaveCount(summaryTiles.length);
    for (const [index, tile] of summaryTiles.entries()) {
        const link = summary.getByRole('link', {
            name: new RegExp(tile.label),
        });
        await expect(link).toHaveAttribute(
            'href',
            new RegExp(`${tile.href.replace('?', '\\?')}$`),
        );
        await expect(
            link.getByText(String(counts[index]), { exact: true }),
        ).toBeVisible();
    }
}

function studentRow(page: Page, name: string): Locator {
    return page.locator('tr').filter({ hasText: name });
}

test('populated dashboard shows headings, real counts, one featured item with at most one name, and compact secondary items', async ({
    page,
}) => {
    await withKaprodiDashboard(page, 'populated', async (fixture) => {
        await expect(page).toHaveTitle(/Dashboard Kaprodi/i);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'Dashboard kaprodi',
                exact: true,
            }),
        ).toBeVisible();
        for (const heading of [
            'Perlu tindakan',
            'Hari ini & 7 hari ke depan',
            'Progres mahasiswa',
            'Fase mahasiswa',
            'Arsip terbaru',
        ]) {
            await expect(
                page.getByRole('heading', { name: heading, exact: true }),
            ).toBeVisible();
        }

        const { progress, agenda } = regions(page);
        await expectSummary(
            page,
            summaryTiles.map((tile) => tile.populatedCount),
        );

        const rows = queueRows(page);
        await expect(rows).toHaveCount(populatedQueue.length);
        for (const [index, item] of visibleQueue(fixture).entries()) {
            const row = rows.nth(index);
            await expect(row).toContainText(item.label);
            await expect(
                row.getByText(String(item.count), { exact: true }),
            ).toBeVisible();
            await expect(
                row.getByRole('link', { name: item.actionLabel }),
            ).toHaveAttribute(
                'href',
                new RegExp(`${item.href.replace('?', '\\?')}$`),
            );
        }

        // Featured item: neutral, one "Terkait:" context line with a single eligible name.
        const featured = rows.first();
        const context = featured.locator('p').filter({ hasText: /^Terkait:/ });
        await expect(context).toHaveCount(1);
        const names = [dashboardNames.awaitingA, dashboardNames.awaitingB];
        const contextText = await context.innerText();
        expect(names.filter((name) => contextText.includes(name)).length).toBe(
            1,
        );
        expect(contextText).not.toContain(',');

        // Secondary items: label, count and action only. No names, context or descriptions.
        for (let index = 1; index < populatedQueue.length; index += 1) {
            const text = await rows.nth(index).innerText();
            expect(text).not.toMatch(/Terkait:/);
            expect(text).not.toMatch(secondaryDescriptions);
            for (const name of Object.values(dashboardNames)) {
                expect(text).not.toContain(name);
            }
        }

        const entries = agenda.getByRole('listitem');
        await expect(entries).toHaveCount(3);
        await expect(entries.nth(0)).toContainText(dashboardNames.examToday);
        await expect(
            agenda.getByRole('heading', { name: /^Hari ini,/ }),
        ).toBeVisible();
        await expect(entries.nth(0)).toContainText('23.00');
        await expect(entries.nth(1)).toContainText(dashboardNames.examLater);
        await expect(entries.nth(1)).toContainText('09.00');
        await expect(
            agenda
                .getByRole('heading', {
                    name: /^(Senin|Selasa|Rabu|Kamis|Jumat|Sabtu|Minggu|Besok), /,
                })
                .first(),
        ).toBeVisible();
        await expect(entries.nth(2)).toContainText('Revisi');
        await expect(entries.nth(2)).toContainText('10.00');
        await expect(agenda.getByText(dashboardNames.awaitingA)).toHaveCount(0);

        await expect(progress).toContainText(dashboardNames.lapsedA);
        await expect(progress).toContainText(dashboardNames.inactive);
        await expect(progress).toContainText(dashboardNames.noSupervisor);
        await expect(
            progress.getByText('Tidak ada mahasiswa bermasalah'),
        ).toHaveCount(0);
        await expect(
            page.getByText('Semua beres', { exact: true }),
        ).toHaveCount(0);

        await expect(
            page
                .getByRole('region', { name: 'Arsip terbaru' })
                .getByText(dashboardNames.archived, { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Bimbingan Saya' }),
        ).toHaveAttribute('href', '/dosen/dashboard');
    });
});

test('all-clear dashboard keeps four zero summary destinations and empty regions', async ({
    page,
}) => {
    await withKaprodiDashboard(page, 'all-clear', async () => {
        const { queue, summary, agenda, progress } = regions(page);

        await expect(
            queue.getByText('Semua beres', { exact: true }),
        ).toBeVisible();
        await expect(queue.getByRole('link')).toHaveCount(0);
        await expectSummary(page, [0, 0, 0, 0]);
        await expect(agenda.getByRole('listitem')).toHaveCount(0);
        await expect(agenda).toContainText('Tidak ada agenda');
        await expect(progress).toContainText('Tidak ada mahasiswa bermasalah');

        for (const tile of summaryTiles) {
            await summary
                .getByRole('link', { name: new RegExp(tile.label) })
                .click();
            await expectDestination(page, tile.href);
            await page.goto('/kaprodi/dashboard');
        }
    });
});

test('every populated queue item and summary tile opens its filtered destination', async ({
    page,
}) => {
    await withKaprodiDashboard(page, 'populated', async () => {
        for (const [index, item] of populatedQueue.entries()) {
            await queueRows(page)
                .nth(index)
                .getByRole('link', { name: item.actionLabel })
                .click();
            await expectDestination(page, item.href);

            if (item.id.startsWith('schedule-')) {
                await expect(page.getByRole('dialog')).toBeVisible();
            }

            if (item.id === 'awaiting-finalization') {
                for (const name of [
                    dashboardNames.awaitingA,
                    dashboardNames.awaitingB,
                ]) {
                    await expect(studentRow(page, name)).toBeVisible();
                }
                await expect(
                    studentRow(page, dashboardNames.examToday),
                ).toHaveCount(0);
            }

            // A focus filter lists only its students, not the other flagged ones.
            if (item.destinationRows) {
                for (const name of item.destinationRows) {
                    await expect(studentRow(page, name)).toBeVisible();
                }
                const others = [
                    dashboardNames.lapsedA,
                    dashboardNames.lapsedB,
                    dashboardNames.noSupervisor,
                    dashboardNames.inactive,
                    dashboardNames.readySidang,
                ].filter((name) => !item.destinationRows!.includes(name));
                for (const name of others) {
                    await expect(studentRow(page, name)).toHaveCount(0);
                }
            }

            if (item.id === 'over-quota') {
                await expect(
                    studentRow(page, dashboardNames.lecturer),
                ).toBeVisible();
            }

            await page.goto('/kaprodi/dashboard');
        }

        for (const tile of summaryTiles) {
            await regions(page)
                .summary.getByRole('link', { name: new RegExp(tile.label) })
                .click();
            await expectDestination(page, tile.href);
            await page.goto('/kaprodi/dashboard');
        }
    });
});

test('a kaprodi without scheduling, supervisor and quota capabilities sees no such actions but keeps the counts', async ({
    page,
}) => {
    await withKaprodiDashboard(page, 'restricted', async (fixture) => {
        const rows = queueRows(page);
        const expected = visibleQueue(fixture);

        await expect(rows).toHaveCount(expected.length);
        for (const [index, item] of expected.entries()) {
            await expect(rows.nth(index)).toContainText(item.label);
            await expect(
                rows.nth(index).getByRole('link', { name: item.actionLabel }),
            ).toBeVisible();
        }

        await expect(
            page.getByRole('link', { name: gatedActions }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: gatedActions }),
        ).toHaveCount(0);
        // The first permitted item keeps at most one name.
        await expect(
            rows
                .first()
                .locator('p')
                .filter({ hasText: /^Terkait:/ }),
        ).toHaveCount(1);
        // Summary counts do not depend on which queue items are permitted.
        await expectSummary(
            page,
            summaryTiles.map((tile) => tile.populatedCount),
        );
    });
});

test('layout reads actions first on a phone and summary first on a desktop, without overflow or small targets', async ({
    page,
}) => {
    await withKaprodiDashboard(page, 'populated', async () => {
        const { queue, summary, agenda } = regions(page);

        // Phone: actions, then a 2x2 summary, then the agenda.
        await page.setViewportSize({ width: 390, height: 900 });
        await expect(queue).toBeVisible();
        await expectNoOverflow(page);
        const phoneQueue = (await queue.boundingBox())!;
        const phoneSummary = (await summary.boundingBox())!;
        const phoneAgenda = (await agenda.boundingBox())!;
        expect(phoneQueue.y + phoneQueue.height).toBeLessThanOrEqual(
            phoneSummary.y,
        );
        expect(phoneSummary.y + phoneSummary.height).toBeLessThanOrEqual(
            phoneAgenda.y,
        );
        const tiles = await summary.getByRole('link').all();
        const boxes = await Promise.all(
            tiles.map((tile) => tile.boundingBox()),
        );
        const [first, second, third, fourth] = boxes.map((box) => box!);
        expect(Math.abs(first.y - second.y)).toBeLessThan(2);
        expect(Math.abs(third.y - fourth.y)).toBeLessThan(2);
        expect(Math.abs(first.x - third.x)).toBeLessThan(2);
        expect(second.x).toBeGreaterThan(first.x + first.width - 2);
        expect(third.y).toBeGreaterThan(first.y + first.height - 2);
        for (const link of [queue, summary]) {
            await expectTargetSize(link.getByRole('link'));
        }

        // Tablet: the featured item spans both columns; secondary items share a row.
        await page.setViewportSize({ width: 768, height: 900 });
        await expectNoOverflow(page);
        const tabletRows = queueRows(page);
        const featured = (await tabletRows.nth(0).boundingBox())!;
        const secondary = (await tabletRows.nth(1).boundingBox())!;
        expect(featured.width).toBeGreaterThan(secondary.width * 1.5);
        expect(featured.y + featured.height).toBeLessThanOrEqual(
            secondary.y + 1,
        );

        // Desktop: summary above, queue and agenda side by side.
        await page.setViewportSize({ width: 1280, height: 900 });
        await expectNoOverflow(page);
        const wideQueue = (await queue.boundingBox())!;
        const wideSummary = (await summary.boundingBox())!;
        const wideAgenda = (await agenda.boundingBox())!;
        expect(wideSummary.y + wideSummary.height).toBeLessThanOrEqual(
            wideQueue.y,
        );
        expect(wideAgenda.x).toBeGreaterThanOrEqual(
            wideQueue.x + wideQueue.width,
        );
        expect(wideAgenda.y).toBeLessThan(wideQueue.y + wideQueue.height);
        for (const link of [queue, summary]) {
            await expectTargetSize(link.getByRole('link'));
        }
    });
});

test('keyboard focus follows the visual reading order of the interactive regions at every width', async ({
    page,
}) => {
    await withKaprodiDashboard(page, 'populated', async () => {
        const landmarks = [
            { key: 'summary', selector: 'nav[aria-label="Ringkasan prodi"]' },
            { key: 'queue', selector: '[aria-label="Perlu tindakan"]' },
        ];

        for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/kaprodi/dashboard');
            await page.evaluate(() =>
                (document.activeElement as HTMLElement | null)?.blur(),
            );

            const visited: string[] = [];
            for (let press = 0; press < 120; press += 1) {
                await page.keyboard.press('Tab');
                const key = await page.evaluate((entries) => {
                    const active = document.activeElement;

                    return (
                        entries.find((entry) => active?.closest(entry.selector))
                            ?.key ?? null
                    );
                }, landmarks);

                if (key && visited.at(-1) !== key) {
                    visited.push(key);
                }
                if (visited.length === landmarks.length && key === null) {
                    break;
                }
            }

            // Each landmark is entered once: focus never bounces between them.
            expect(new Set(visited).size).toBe(visited.length);

            const positions = await Promise.all(
                landmarks.map(async ({ key, selector }) => {
                    const box = (await page
                        .locator(selector)
                        .first()
                        .boundingBox())!;

                    return { key, y: Math.round(box.y / 24), x: box.x };
                }),
            );
            const reading = positions
                .sort((a, b) => a.y - b.y || a.x - b.x)
                .map((position) => position.key);
            expect(visited).toEqual(reading);

            // The agenda is a noninteractive timeline: it never takes focus, but it
            // still follows the queue in the DOM.
            expect(
                await page.evaluate(() => {
                    const queue = document.querySelector(
                        'section[aria-label="Perlu tindakan"], [role="region"][aria-label="Perlu tindakan"]',
                    );
                    const agenda = document.querySelector(
                        '[aria-label="Hari ini & 7 hari ke depan"]',
                    );

                    return Boolean(
                        queue &&
                        agenda &&
                        queue.compareDocumentPosition(agenda) &
                            Node.DOCUMENT_POSITION_FOLLOWING,
                    );
                }),
            ).toBe(true);
        }
    });
});
