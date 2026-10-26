import { expect, test, type Locator, type Page } from '@playwright/test';

import {
    adminDashboardNames,
    agendaLocations,
    foreignStudentNames,
    lapseMentorship,
    ownStudentNames,
    populatedQueue,
    seedDocuments,
    summaryStats,
    withAdminDashboard,
    type AdminDashboardScenario,
} from '../support/admin-dashboard';
import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('admin') });

const lapsedStudent = 'Bagas Saputra';
const documentStudent = 'Mahasiswa SiTA';

test.describe('admin dashboard and thesis project detail', () => {
    test.beforeAll(() => {
        seedPlaywrightScenarios();
        lapseMentorship('bagas@sita.test');
        seedDocuments('mahasiswa@sita.test');
    });

    test('a queue row opens the thesis project list filtered, and the lapsed masa aktif can be renewed', async ({
        page,
    }) => {
        await page.goto('/admin');

        await page
            .getByRole('link', { name: /^Lihat aktivitas / })
            .first()
            .click();
        await expect(page).toHaveURL(/\/admin\/thesis-projects\/\d+/);
        await expect(page.getByRole('tab', { name: 'Ujian' })).toBeVisible();
        await page.goto('/admin');

        await page
            .locator('.sita-action-queue-row')
            .filter({ hasText: 'Masa aktif bimbingan habis' })
            .getByRole('link', { name: /Perpanjang/ })
            .click();

        await expect(page).toHaveURL(/action_queue/);

        const row = page.locator('tr').filter({ hasText: lapsedStudent });

        await expect(row).toBeVisible();
        await expect(
            page.locator('tr').filter({ hasText: documentStudent }),
        ).toHaveCount(0);

        await row
            .getByRole('button', { name: 'Perpanjang masa aktif' })
            .click();
        await page.getByRole('button', { name: /confirm|konfirmasi/i }).click();

        // The renewed project drops out of the filtered list, also after a reload.
        await expect(row).toHaveCount(0);
        await page.reload();
        await expect(page.locator('table')).toBeVisible();
        await expect(
            page.locator('tr').filter({ hasText: lapsedStudent }),
        ).toHaveCount(0);
    });

    test('the documents table scrolls sideways and keeps the download action in view', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1366, height: 768 });
        await page.goto(
            `/admin/thesis-projects?search=${encodeURIComponent(documentStudent)}`,
        );
        await page
            .locator('tr')
            .filter({ hasText: documentStudent })
            .first()
            .getByRole('link', { name: /view/i })
            .click();

        await expect(page.getByRole('tab', { name: 'Ujian' })).toBeVisible();
        await expect(page.getByRole('tab', { name: 'Workflow' })).toHaveCount(
            0,
        );

        await page.getByRole('tab', { name: 'Dokumen' }).click();

        const container = page.locator('.sita-detail-table-sticky-end').first();

        await expect(container).toBeVisible();

        const overflow = await container.evaluate((element) => ({
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
        }));

        expect(overflow.scrollWidth).toBeGreaterThan(overflow.clientWidth);

        // Headers never wrap per letter: every header cell stays on one line.
        const headerHeights = await container
            .locator('thead th')
            .evaluateAll((cells) =>
                cells.map((cell) => cell.getBoundingClientRect().height),
            );

        expect(Math.max(...headerHeights)).toBeLessThan(48);

        const download = container.getByRole('link', { name: 'Unduh' }).first();

        await expect(download).toBeVisible();

        const containerBox = await container.boundingBox();
        const downloadBox = await download.boundingBox();

        expect(downloadBox!.x + downloadBox!.width).toBeLessThanOrEqual(
            containerBox!.x + containerBox!.width + 1,
        );

        // Scrolled fully left, the sticky action column is still at the right edge.
        await container.evaluate((element) => {
            element.scrollLeft = 0;
        });

        await expect(download).toBeInViewport();
    });
});

// The dashboard against isolated fixtures (see support/admin-dashboard.ts): a
// program studi with every queue item and an agenda, and an empty one. Each
// test signs in as the fixture's program-studi admin, so the shared seeded
// admin and the scenario students other specs change are never read or written.
// Every fixture also holds a second program studi the admin must never see.
test.describe('admin dashboard against isolated fixtures', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    const agendaHeadingName = 'Agenda ujian 7 hari ke depan';
    const chartHeadingName = 'Sebaran Fase Tugas Akhir';
    const queueRowSelector = 'ul.sita-action-queue > li.sita-action-queue-row';

    function regions(page: Page) {
        return {
            summary: page.getByRole('region', {
                name: 'Ringkasan akademik',
                exact: true,
            }),
            queue: page.locator('ul.sita-action-queue'),
            rows: page.locator(queueRowSelector),
            agendaHeading: page.getByRole('heading', {
                name: agendaHeadingName,
                exact: true,
            }),
            chartHeading: page.getByText(chartHeadingName).first(),
        };
    }

    async function box(locator: Locator) {
        await expect(locator).toBeVisible();
        const result = await locator.boundingBox();
        expect(result).not.toBeNull();

        return result!;
    }

    async function expectNoOverflow(page: Page): Promise<void> {
        await expect
            .poll(() =>
                page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            )
            .toBe(true);
    }

    async function expectTargetSize(links: Locator): Promise<void> {
        for (const link of await links.all()) {
            const size = await box(link);
            expect(size.width).toBeGreaterThanOrEqual(44);
            expect(size.height).toBeGreaterThanOrEqual(44);
        }
    }

    function uniqueRanks(values: number[], tolerance: number): number {
        const clusters: number[] = [];

        for (const value of [...values].sort((a, b) => a - b)) {
            if (
                clusters.length === 0 ||
                value - clusters[clusters.length - 1] > tolerance
            ) {
                clusters.push(value);
            }
        }

        return clusters.length;
    }

    async function openDashboard(
        page: Page,
        scenario: AdminDashboardScenario,
        width: number,
        height: number,
        run: () => Promise<void>,
    ): Promise<void> {
        await page.setViewportSize({ width, height });
        await withAdminDashboard(page, scenario, async () => {
            await expect(
                page.getByRole('heading', { name: 'Perlu tindakan' }),
            ).toBeVisible();
            await run();
        });
    }

    test('populated: every queue category in urgency order with real counts, one preview name on the first card only, flat agenda and real statistics', async ({
        page,
    }) => {
        await openDashboard(page, 'populated', 1280, 800, async () => {
            const { summary, queue, rows, agendaHeading } = regions(page);
            const names = adminDashboardNames;

            await expect(rows).toHaveCount(populatedQueue.length);
            await expect(
                page.locator(`${queueRowSelector}.sita-action-card`),
            ).toHaveCount(populatedQueue.length);
            await expect(queue.locator(':scope > li')).toHaveCount(
                populatedQueue.length,
            );

            for (const [index, item] of populatedQueue.entries()) {
                const row = rows.nth(index);

                await expect(row).toContainText(item.label);
                await expect(
                    row.getByText(String(item.count), { exact: true }).first(),
                ).toBeVisible();
                await expect(row.getByRole('link').first()).toBeVisible();
            }

            // Only the most urgent card may preview a student, and only one of the
            // students that really are in that queue.
            const everyName = [...ownStudentNames, ...foreignStudentNames];
            const firstText = await rows.first().innerText();
            const previewed = everyName.filter((name) =>
                firstText.includes(name),
            );

            expect(previewed).toHaveLength(1);
            expect([names.titleA, names.titleB]).toContain(previewed[0]);

            for (let index = 1; index < populatedQueue.length; index++) {
                const text = await rows.nth(index).innerText();

                expect(
                    everyName.filter((name) => text.includes(name)),
                    `${populatedQueue[index].label} must not preview names`,
                ).toEqual([]);
            }

            // The queue is a list of cards, never a table.
            await expect(
                page.locator('table').filter({ hasText: 'Perlu tindakan' }),
            ).toHaveCount(0);
            await expect(
                page
                    .locator('table')
                    .filter({ hasText: populatedQueue[0].label }),
            ).toHaveCount(0);

            // Statistics keep their old metrics and values.
            await expect(summary).toBeVisible();

            for (const stat of summaryStats) {
                await expect(
                    summary.getByText(stat.label).first(),
                ).toBeVisible();
                await expect(
                    summary.getByText(stat.populated, { exact: true }).first(),
                ).toBeVisible();
            }

            await expect(summary.getByText('1', { exact: true })).toHaveCount(
                2,
            );

            // Agenda: inside the seven days only, in time order, as a flat timeline.
            await expect(agendaHeading).toBeVisible();
            await expect(
                page.getByText(names.agendaSoon).first(),
            ).toBeVisible();
            await expect(
                page.getByText(names.agendaLater).first(),
            ).toBeVisible();
            await expect(
                page.getByText(agendaLocations.soon).first(),
            ).toBeVisible();
            await expect(
                page.getByText(agendaLocations.later).first(),
            ).toBeVisible();
            await expect(page.getByText(names.agendaBeyond)).toHaveCount(0);
            await expect(page.getByText(agendaLocations.beyond)).toHaveCount(0);

            expect(
                (await box(page.getByText(names.agendaSoon).first())).y,
            ).toBeLessThan(
                (await box(page.getByText(names.agendaLater).first())).y,
            );
            await expect(
                page.locator('table').filter({ hasText: names.agendaSoon }),
            ).toHaveCount(0);

            // A scoped admin never sees the other program studi anywhere.
            for (const text of [
                ...foreignStudentNames,
                agendaLocations.foreign,
            ]) {
                await expect(page.locator('body')).not.toContainText(text);
            }
        });
    });

    test('populated: every call to action opens the thesis project list with exactly its own students', async ({
        page,
    }) => {
        await openDashboard(page, 'populated', 1280, 800, async () => {
            const everyName = [...ownStudentNames, ...foreignStudentNames];

            for (const item of populatedQueue) {
                await page.goto('/admin');

                const row = regions(page).rows.filter({ hasText: item.label });
                const links = row.getByRole('link');

                await expect(row).toHaveCount(1);
                await expect(links.first()).toContainText(item.actionLabel);

                for (const link of await links.all()) {
                    const href = new URL(
                        (await link.getAttribute('href'))!,
                        'http://sita.test',
                    );

                    expect(href.pathname).toBe('/admin/thesis-projects');
                    expect(
                        href.searchParams.get('filters[action_queue][value]'),
                    ).toBe(item.key);
                }

                await links.first().click();
                await expect(page).toHaveURL((url) => {
                    return (
                        url.pathname === '/admin/thesis-projects' &&
                        url.searchParams.get('filters[action_queue][value]') ===
                            item.key
                    );
                });

                for (const student of item.students) {
                    await expect(
                        page.locator('tr').filter({ hasText: student }),
                    ).toHaveCount(1);
                }

                // The list holds nobody else: not another queue, nor another program studi.
                for (const student of everyName.filter(
                    (name) => !item.students.includes(name),
                )) {
                    await expect(
                        page.locator('tr').filter({ hasText: student }),
                    ).toHaveCount(0);
                }
            }
        });
    });

    test('desktop: summary strip first, a two-column action grid with a wide first card beside a flat agenda, charts below', async ({
        page,
    }) => {
        await openDashboard(page, 'populated', 1280, 800, async () => {
            const { summary, queue, rows, agendaHeading, chartHeading } =
                regions(page);

            const summaryBox = await box(summary);
            const queueBox = await box(queue);
            const agendaBox = await box(agendaHeading);
            const lastAgendaBox = await box(
                page.getByText(agendaLocations.later).first(),
            );
            const chartBox = await box(chartHeading);

            // Summary strip above the actions.
            expect(summaryBox.y + summaryBox.height).toBeLessThanOrEqual(
                queueBox.y + 1,
            );

            // The agenda sits to the right of the actions, level with them.
            expect(agendaBox.x).toBeGreaterThanOrEqual(
                queueBox.x + queueBox.width - 1,
            );
            expect(agendaBox.y).toBeLessThan(queueBox.y + queueBox.height);
            expect(agendaBox.y).toBeGreaterThanOrEqual(
                summaryBox.y + summaryBox.height - 1,
            );

            // Two columns; the most urgent card spans both.
            const first = await box(rows.nth(0));
            const second = await box(rows.nth(1));
            const third = await box(rows.nth(2));
            const fourth = await box(rows.nth(3));

            expect(first.width).toBeGreaterThan(second.width * 1.7);
            expect(Math.abs(second.y - third.y)).toBeLessThanOrEqual(2);
            expect(second.x).toBeLessThan(third.x);
            expect(fourth.y).toBeGreaterThan(second.y);
            expect(first.y).toBeLessThan(second.y);

            // Passive charts stay below both the actions and the agenda.
            expect(chartBox.y).toBeGreaterThanOrEqual(
                queueBox.y + queueBox.height - 1,
            );
            expect(chartBox.y).toBeGreaterThanOrEqual(
                lastAgendaBox.y + lastAgendaBox.height - 1,
            );

            await expectTargetSize(rows.getByRole('link'));
            await expectTargetSize(summary.getByRole('link'));
            await expectNoOverflow(page);
        });
    });

    test('desktop: keyboard focus follows the reading order, summary then actions then agenda', async ({
        page,
    }) => {
        await openDashboard(page, 'populated', 1280, 800, async () => {
            const order = await focusOrder(page);

            expect(order.regions).toEqual(
                [...order.regions].sort(
                    (a, b) =>
                        ['summary', 'queue', 'agenda'].indexOf(a) -
                        ['summary', 'queue', 'agenda'].indexOf(b),
                ),
            );
            expect(order.queueStops).toBe(
                await regions(page).queue.getByRole('link').count(),
            );
            expect(order.queueStops).toBeGreaterThanOrEqual(
                populatedQueue.length,
            );
        });
    });

    test('mobile 390px: actions come before summary and agenda in the DOM, the focus order and the page, with 44px targets and no overflow', async ({
        page,
    }) => {
        await openDashboard(page, 'populated', 390, 844, async () => {
            const { summary, queue, rows, agendaHeading, chartHeading } =
                regions(page);

            // The real DOM order, not only the painted order.
            const domOrder = await page.evaluate(
                ({ agenda, chart }) => {
                    const queueNode = document.querySelector(
                        'ul.sita-action-queue',
                    );
                    const summaryNode = document.querySelector(
                        '[aria-label="Ringkasan akademik"]',
                    );
                    const find = (text: string) => {
                        const walker = document.createTreeWalker(
                            document.body,
                            NodeFilter.SHOW_TEXT,
                        );

                        while (walker.nextNode()) {
                            if (
                                walker.currentNode.textContent?.trim() === text
                            ) {
                                return walker.currentNode.parentElement;
                            }
                        }

                        return null;
                    };
                    const agendaNode = find(agenda);
                    const chartNode = find(chart);
                    const before = (
                        a: Node | null | undefined,
                        b: Node | null | undefined,
                    ) =>
                        Boolean(
                            a &&
                            b &&
                            a.compareDocumentPosition(b) &
                                Node.DOCUMENT_POSITION_FOLLOWING,
                        );

                    return {
                        queueBeforeSummary: before(queueNode, summaryNode),
                        summaryBeforeAgenda: before(summaryNode, agendaNode),
                        agendaBeforeChart: before(agendaNode, chartNode),
                    };
                },
                { agenda: agendaHeadingName, chart: chartHeadingName },
            );

            expect(domOrder).toEqual({
                queueBeforeSummary: true,
                summaryBeforeAgenda: true,
                agendaBeforeChart: true,
            });

            const queueBox = await box(queue);
            const summaryBox = await box(summary);
            const agendaBox = await box(agendaHeading);
            const chartBox = await box(chartHeading);

            expect(queueBox.y + queueBox.height).toBeLessThanOrEqual(
                summaryBox.y + 1,
            );
            expect(summaryBox.y + summaryBox.height).toBeLessThanOrEqual(
                agendaBox.y + 1,
            );
            expect(agendaBox.y).toBeLessThan(chartBox.y);

            // One column of cards.
            const first = await box(rows.nth(0));
            const second = await box(rows.nth(1));

            expect(Math.abs(first.x - second.x)).toBeLessThanOrEqual(2);
            expect(second.y).toBeGreaterThan(first.y);

            // The four statistics form a 2x2 grid.
            const labels = await Promise.all(
                summaryStats.map((stat) =>
                    box(summary.getByText(stat.label).first()),
                ),
            );

            expect(
                uniqueRanks(
                    labels.map((label) => label.x),
                    8,
                ),
            ).toBe(2);
            expect(
                uniqueRanks(
                    labels.map((label) => label.y),
                    8,
                ),
            ).toBe(2);

            const order = await focusOrder(page);

            expect(order.regions).toEqual(
                [...order.regions].sort(
                    (a, b) =>
                        ['queue', 'summary', 'agenda'].indexOf(a) -
                        ['queue', 'summary', 'agenda'].indexOf(b),
                ),
            );
            expect(order.queueStops).toBe(
                await regions(page).queue.getByRole('link').count(),
            );
            expect(order.queueStops).toBeGreaterThanOrEqual(
                populatedQueue.length,
            );

            await expectTargetSize(rows.getByRole('link'));
            await expectTargetSize(summary.getByRole('link'));
            await expectNoOverflow(page);
        });
    });

    test('all-clear: calm empty queue, flat empty agenda, zero statistics kept, and nothing from another program studi', async ({
        page,
    }) => {
        await openDashboard(page, 'all-clear', 1280, 800, async () => {
            const { summary, rows, queue, agendaHeading, chartHeading } =
                regions(page);

            await expect(page.getByText('Semua beres').first()).toBeVisible();
            await expect(queue).toHaveCount(0);
            await expect(rows).toHaveCount(0);
            await expect(page.locator('.sita-action-queue-row')).toHaveCount(0);

            await expect(summary).toBeVisible();

            for (const stat of summaryStats) {
                await expect(
                    summary.getByText(stat.label).first(),
                ).toBeVisible();
                await expect(
                    summary.getByText(stat.allClear, { exact: true }).first(),
                ).toBeVisible();
            }

            await expect(summary.getByText('0%', { exact: true })).toHaveCount(
                2,
            );
            await expect(summary.getByText('0', { exact: true })).toHaveCount(
                2,
            );

            await expect(agendaHeading).toBeVisible();
            await expect(chartHeading).toBeVisible();
            await expect(
                page.locator('table').filter({ hasText: agendaHeadingName }),
            ).toHaveCount(0);

            for (const text of [
                ...foreignStudentNames,
                agendaLocations.foreign,
            ]) {
                await expect(page.locator('body')).not.toContainText(text);
            }

            await expectNoOverflow(page);

            // The other program studi's queue stays out of this admin's project list too.
            await page.goto(
                '/admin/thesis-projects?filters[action_queue][value]=title_review',
            );
            await expect(page.locator('.fi-ta-empty-state')).toBeVisible();

            for (const student of foreignStudentNames) {
                await expect(
                    page.locator('tr').filter({ hasText: student }),
                ).toHaveCount(0);
            }
        });
    });

    /**
     * Tabs from the page heading through the dashboard and records, in order,
     * which region each stop belongs to (summary, queue, agenda) without
     * repeats, plus how many distinct queue links were visited.
     */
    async function focusOrder(
        page: Page,
    ): Promise<{ regions: string[]; queueStops: number }> {
        await page.getByRole('heading', { level: 1 }).first().click();

        const seen: string[] = [];
        const queueLinks = new Set<number>();
        let afterAgenda = false;

        for (let press = 0; press < 150; press++) {
            await page.keyboard.press('Tab');

            const stop = await page.evaluate((agendaTitle) => {
                const active = document.activeElement;

                if (!active || active === document.body) {
                    return { region: 'other', index: -1 };
                }

                const queueNode = document.querySelector(
                    'ul.sita-action-queue',
                );
                const summaryNode = document.querySelector(
                    '[aria-label="Ringkasan akademik"]',
                );
                const walker = document.createTreeWalker(
                    document.body,
                    NodeFilter.SHOW_TEXT,
                );
                let agendaRoot: Element | null = null;

                while (walker.nextNode()) {
                    if (
                        walker.currentNode.textContent?.trim() === agendaTitle
                    ) {
                        agendaRoot = walker.currentNode.parentElement;
                        break;
                    }
                }

                while (
                    agendaRoot?.parentElement &&
                    !agendaRoot.parentElement.contains(queueNode) &&
                    !agendaRoot.parentElement.contains(summaryNode)
                ) {
                    agendaRoot = agendaRoot.parentElement;
                }

                if (queueNode?.contains(active)) {
                    return {
                        region: 'queue',
                        index: Array.from(
                            queueNode.querySelectorAll('a'),
                        ).indexOf(active as HTMLAnchorElement),
                    };
                }

                if (summaryNode?.contains(active)) {
                    return { region: 'summary', index: -1 };
                }

                if (agendaRoot?.contains(active)) {
                    return { region: 'agenda', index: -1 };
                }

                return { region: 'other', index: -1 };
            }, agendaHeadingName);

            if (stop.region === 'other') {
                if (afterAgenda) {
                    break;
                }

                continue;
            }

            if (stop.region === 'agenda') {
                afterAgenda = true;
            }

            if (stop.region === 'queue') {
                queueLinks.add(stop.index);
            }

            if (seen[seen.length - 1] !== stop.region) {
                seen.push(stop.region);
            }
        }

        return { regions: seen, queueStops: queueLinks.size };
    }
});
