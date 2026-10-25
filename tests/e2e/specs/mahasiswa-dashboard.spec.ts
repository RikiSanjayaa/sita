import { expect, test, type Locator, type Page } from '@playwright/test';

import {
    withMahasiswaDashboard,
    type MahasiswaDashboardFixture,
    type MahasiswaDashboardScenario,
} from '../support/mahasiswa-dashboard';

// Each scenario signs in with its own real DB account and cleans up in finally.
// Submission, scheduling and upload flows themselves live in their own specs.
test.use({ storageState: { cookies: [], origins: [] } });

const dashboardPath = '/mahasiswa/dashboard';
const regionNames = [
    'Tindakan Anda',
    'Ringkasan akademik',
    'Agenda mendatang',
    'Progres akademik',
    'Dosen pembimbing',
] as const;

function region(page: Page, name: (typeof regionNames)[number]): Locator {
    return page.getByRole('region', { name, exact: true });
}

function action(page: Page, name: string | RegExp): Locator {
    return region(page, 'Tindakan Anda').getByRole('link', { name });
}

async function expectDestination(page: Page, path: string): Promise<void> {
    await expect(page).toHaveURL((url) => url.pathname === path);
}

async function expectEligibility(
    page: Page,
    fixture: MahasiswaDashboardFixture,
): Promise<void> {
    const { expected } = fixture;
    const main = action(
        page,
        expected.canSubmitTitle ? /Ajukan Judul/ : /^Buka Skripsi/,
    );
    await expect(main).toBeVisible();
    await expect(main).toHaveAttribute('href', '/mahasiswa/tugas-akhir');
    await expect(main).not.toHaveAttribute('aria-disabled', 'true');
    await expect(action(page, 'Buka Pesan')).toHaveAttribute(
        'href',
        '/mahasiswa/pesan',
    );
    await expect(action(page, 'Buka Pesan')).not.toHaveAttribute(
        'aria-disabled',
        'true',
    );

    for (const [name, enabled, href] of [
        [
            'Ajukan Bimbingan',
            expected.canScheduleMeeting,
            '/mahasiswa/jadwal-bimbingan?open=ajukan',
        ],
        [
            'Upload Dokumen',
            expected.canUploadDocument,
            '/mahasiswa/upload-dokumen?open=unggah',
        ],
    ] as const) {
        if (enabled) {
            await expect(action(page, name)).toHaveAttribute('href', href);
            await expect(action(page, name)).not.toHaveAttribute(
                'aria-disabled',
                'true',
            );
        } else {
            await expect(action(page, name)).toHaveCount(0);
            await expect(
                region(page, 'Tindakan Anda').getByRole('button', { name }),
            ).toBeDisabled();
        }
    }
}

async function expectSummary(
    page: Page,
    fixture: MahasiswaDashboardFixture,
): Promise<void> {
    const summary = region(page, 'Ringkasan akademik');
    const { expected } = fixture;
    const stats = [
        ['Dosen Terhubung', String(expected.advisorCount)],
        ['Agenda Mendatang', String(expected.agendaCount)],
        ['Pesan Belum Dibaca', String(expected.unreadMessages)],
    ];
    for (const [label, value] of stats) {
        const stat = summary.getByRole('group', { name: label, exact: true });
        await expect(stat).toBeVisible();
        await expect(stat.getByText(value, { exact: true })).toBeVisible();
    }
    await expect(summary.getByRole('group')).toHaveCount(4);
    await expect(
        region(page, 'Progres akademik').getByText(`${expected.progress}%`, {
            exact: true,
        }),
    ).toBeVisible();
}

async function expectAgenda(
    page: Page,
    fixture: MahasiswaDashboardFixture,
): Promise<void> {
    const agenda = region(page, 'Agenda mendatang');
    const items = agenda.getByRole('listitem');
    await expect(items).toHaveCount(fixture.expected.agendaCount);
    for (const [index, label] of fixture.agendaLabels.entries()) {
        await expect(items.nth(index)).toContainText(label);
        await expect(items.nth(index)).toContainText(
            fixture.agendaDates[index],
        );
    }
}

for (const scenario of [
    'not-started',
    'pending',
    'rejected',
    'cancelled',
] satisfies MahasiswaDashboardScenario[]) {
    test(`${scenario}: main and messages stay available while meeting and upload follow eligibility`, async ({
        page,
    }) => {
        await withMahasiswaDashboard(page, scenario, async (fixture) => {
            await expectSummary(page, fixture);
            await expectEligibility(page, fixture);
            await expectAgenda(page, fixture);
            await expect(
                region(page, 'Dosen pembimbing').getByRole('link'),
            ).toHaveCount(0);
            await page.setViewportSize({ width: 390, height: 900 });
            await expectTargets(
                region(page, 'Tindakan Anda').getByRole('button'),
            );

            await action(
                page,
                fixture.expected.canSubmitTitle
                    ? /Ajukan Judul/
                    : /^Buka Skripsi/,
            ).click();
            await expectDestination(page, '/mahasiswa/tugas-akhir');
            await page.goto(dashboardPath);
            await action(page, 'Buka Pesan').click();
            await expectDestination(page, '/mahasiswa/pesan');
        });
    });
}

test('populated dashboard renders real counts, progress, ordered agenda and advisor', async ({
    page,
}) => {
    await withMahasiswaDashboard(page, 'populated', async (fixture) => {
        await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'Dashboard mahasiswa',
                exact: true,
            }),
        ).toBeVisible();
        for (const name of regionNames) {
            await expect(region(page, name)).toBeVisible();
        }
        await expectSummary(page, fixture);
        await expect(
            region(page, 'Progres akademik').locator('[aria-current="step"]'),
        ).toHaveCount(1);
        await expectEligibility(page, fixture);
        await expectAgenda(page, fixture);
        await expect(region(page, 'Progres akademik')).toContainText(
            fixture.projectTitle,
        );
        const advisor = region(page, 'Dosen pembimbing').getByRole('link', {
            name: new RegExp(fixture.advisorName),
        });
        expect(
            new URL((await advisor.getAttribute('href'))!, page.url()).pathname,
        ).toBe(fixture.advisorProfilePath);
        await advisor.click();
        await expectDestination(page, fixture.advisorProfilePath);
        await expect(
            page.getByRole('heading', {
                name: fixture.advisorName,
                exact: true,
            }),
        ).toBeVisible();
    });
});

test('enabled CTAs navigate and query links open their real dialogs', async ({
    page,
}) => {
    await withMahasiswaDashboard(page, 'populated', async () => {
        await action(page, /^Buka Skripsi/).click();
        await expectDestination(page, '/mahasiswa/tugas-akhir');
        await page.goto(dashboardPath);
        for (const [name, path, query, dialog] of [
            [
                'Ajukan Bimbingan',
                '/mahasiswa/jadwal-bimbingan',
                'ajukan',
                'Ajukan Jadwal Bimbingan',
            ],
            [
                'Upload Dokumen',
                '/mahasiswa/upload-dokumen',
                'unggah',
                'Upload Dokumen',
            ],
        ] as const) {
            await expect(action(page, name)).toHaveAttribute(
                'href',
                `${path}?open=${query}`,
            );
            await action(page, name).click();
            await expectDestination(page, path);
            await expect(
                page.getByRole('dialog', { name: dialog }),
            ).toBeVisible();
            await page.goto(dashboardPath);
        }
        for (const [label, href] of [
            ['Status Saat Ini', '/mahasiswa/tugas-akhir'],
            ['Dosen Terhubung', '#dosen-pembimbing'],
            ['Agenda Mendatang', '#agenda-mendatang'],
            ['Pesan Belum Dibaca', '/mahasiswa/pesan'],
        ] as const) {
            const link = region(page, 'Ringkasan akademik')
                .getByRole('group', { name: label, exact: true })
                .getByRole('link');
            await expect(link).toHaveAttribute('href', href);
            await link.click();
            if (href.startsWith('#')) {
                await expect(page).toHaveURL((url) => url.hash === href);
                await expect(page.locator(href)).toBeInViewport();
            } else {
                await expectDestination(page, href);
            }
            await page.goto(dashboardPath);
        }
        await region(page, 'Agenda mendatang')
            .getByRole('link')
            .first()
            .click();
        await expectDestination(page, '/mahasiswa/jadwal-bimbingan');
        await page.goto(dashboardPath);
        await action(page, 'Buka Pesan').click();
        await expectDestination(page, '/mahasiswa/pesan');
    });
});

test('scheduled sidang exposes its agenda but never the additional examiner', async ({
    page,
}) => {
    await withMahasiswaDashboard(page, 'scheduled', async (fixture) => {
        await expectSummary(page, fixture);
        await expectEligibility(page, fixture);
        await expectAgenda(page, fixture);
        await expect(page.getByText(fixture.secretExaminerName)).toHaveCount(0);
        // The initial document contains the actual serialized Inertia props.
        const response = await page.goto(dashboardPath);
        expect(response?.status()).toBe(200);
        expect(await response!.text()).not.toContain(
            fixture.secretExaminerName,
        );
        await region(page, 'Agenda mendatang')
            .getByRole('link', { name: new RegExp(fixture.agendaLabels[1]) })
            .click();
        await expectDestination(page, '/mahasiswa/tugas-akhir');
        await expect(page.getByText(fixture.secretExaminerName)).toHaveCount(0);
    });
});

for (const scenario of [
    'completed',
    'archived',
] satisfies MahasiswaDashboardScenario[]) {
    test(`${scenario}: academic history and messages remain accessible without meeting or upload actions`, async ({
        page,
    }) => {
        await withMahasiswaDashboard(page, scenario, async (fixture) => {
            await expectSummary(page, fixture);
            await expectEligibility(page, fixture);
            await expectAgenda(page, fixture);
            await action(page, /Ajukan Judul/).click();
            await expectDestination(page, '/mahasiswa/tugas-akhir');
            await expect(
                page.getByText(fixture.projectTitle, { exact: true }).first(),
            ).toBeVisible();
            await page.goto(dashboardPath);
            await action(page, 'Buka Pesan').click();
            await expectDestination(page, '/mahasiswa/pesan');
            await expect(
                page.getByRole('heading', { name: 'Pesan', exact: true }),
            ).toBeVisible();
        });
    });
}

async function expectTargets(links: Locator): Promise<void> {
    for (const link of await links.all()) {
        const box = await link.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.width).toBeGreaterThanOrEqual(44);
        expect(box!.height).toBeGreaterThanOrEqual(44);
    }
}

test('390px reading order is real DOM and keyboard order, with usable targets and no overflow at phone or desktop widths', async ({
    page,
}) => {
    await withMahasiswaDashboard(page, 'populated', async () => {
        for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(dashboardPath);
            await expect
                .poll(() =>
                    page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <=
                            window.innerWidth,
                    ),
                )
                .toBe(true);
            for (const name of regionNames) {
                await expectTargets(region(page, name).getByRole('link'));
            }
            if (width !== 390) {
                continue;
            }
            for (let index = 0; index < regionNames.length - 1; index += 1) {
                const first = region(page, regionNames[index]);
                const next = region(page, regionNames[index + 1]);
                expect(
                    await first.evaluate(
                        (element, nextName) => {
                            const following = document.querySelector(
                                `[aria-label="${nextName}"]`,
                            );
                            return Boolean(
                                following &&
                                element.compareDocumentPosition(following) &
                                    Node.DOCUMENT_POSITION_FOLLOWING,
                            );
                        },
                        regionNames[index + 1],
                    ),
                ).toBe(true);
                const firstBox = (await first.boundingBox())!;
                const nextBox = (await next.boundingBox())!;
                expect(firstBox.y + firstBox.height).toBeLessThanOrEqual(
                    nextBox.y + 1,
                );
            }

            const links = page.locator(
                '[role="region"] a[href], section[aria-label] a[href]',
            );
            const dashboardLinks: Locator[] = [];
            for (const link of await links.all()) {
                if (
                    await link.evaluate(
                        (element, names) =>
                            names.some((name) =>
                                element.closest(`[aria-label="${name}"]`),
                            ),
                        [...regionNames],
                    )
                ) {
                    dashboardLinks.push(link);
                }
            }
            expect(dashboardLinks.length).toBeGreaterThanOrEqual(6);
            await dashboardLinks[0].focus();
            for (const link of dashboardLinks.slice(1)) {
                await page.keyboard.press('Tab');
                await expect(link).toBeFocused();
            }
        }
    });
});
