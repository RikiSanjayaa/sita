import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, test, type Locator, type Page } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    runArtisan,
} from '../support/db';

// Only this spec's accounts and rows are created/deleted. No shared seed reset,
// mocked Inertia responses, synthetic spacer elements, or CSS height changes.
const prefix = `sticky-${randomUUID().slice(0, 8)}`;
const model = (name: string): string => `App\\Models\\${name}`;
const email = (role: string): string => `${prefix}-${role}@sita.test`;

function cleanup(): void {
    runArtisan([
        'tinker',
        `--execute=
        $ps = ${model('ProgramStudi')}::query()->where('slug', '${prefix}')->first();
        if ($ps) { ${model('ThesisProject')}::query()->where('program_studi_id', $ps->id)->delete(); }
        ${model('User')}::query()->where('email', 'like', '${prefix}-%@sita.test')->delete();
        if ($ps) { $ps->delete(); }
        ${model('Faculty')}::query()->where('slug', '${prefix}')->delete();
    `,
    ]);
}

function createFixtures(): void {
    const completionMarker = `${prefix}:fixtures-created`;
    const script = `
        $faculty = ${model('Faculty')}::factory()->create(['slug' => '${prefix}', 'code' => '${prefix}']);
        $ps = ${model('ProgramStudi')}::factory()->create(['faculty_id' => $faculty->id, 'slug' => '${prefix}', 'name' => '${prefix}', 'concentrations' => ['Sistem'], 'degree_levels' => ['s1']]);
        $head = ${model('User')}::factory()->asKaprodi()->create(['email' => '${email('head')}', 'phone_number' => '081234567890', 'phone_number_confirmed_at' => now()]);
        ${model('KaprodiAssignment')}::query()->create(['program_studi_id' => $ps->id, 'user_id' => $head->id, 'is_primary' => true, 'capabilities' => ${model('KaprodiAssignment')}::defaultCapabilities()]);
        $lecturers = [];
        for ($i = 0; $i < 32; $i++) {
            $lecturer = ${model('User')}::factory()->asDosen()->create(['name' => '${prefix} Dosen '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'email' => '${prefix}-lecturer'.$i.'@sita.test', 'phone_number' => '081234567890', 'phone_number_confirmed_at' => now()]);
            // Saving the profile creates its primary program/concentration assignment.
            ${model('DosenProfile')}::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $ps->id, 'concentration' => 'Sistem', 'is_active' => true]);
            $lecturers[] = $lecturer;
        }
        $lecturer = $lecturers[0];
        for ($i = 0; $i < 64; $i++) {
            $archived = $i >= 32;
            $student = ${model('User')}::factory()->asMahasiswa()->create(['name' => '${prefix} Mahasiswa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'email' => '${prefix}-student'.$i.'@sita.test', 'phone_number' => '081234567890', 'phone_number_confirmed_at' => now()]);
            ${model('MahasiswaProfile')}::factory()->create(['user_id' => $student->id, 'program_studi_id' => $ps->id, 'concentration' => 'Sistem', 'is_active' => !$archived]);
            $project = ${model('ThesisProject')}::factory()->create(['student_user_id' => $student->id, 'program_studi_id' => $ps->id, 'phase' => $archived ? 'completed' : 'research', 'state' => $archived ? 'completed' : 'active', 'completed_at' => $archived ? now() : null]);
            $title = ${model('ThesisProjectTitle')}::query()->create(['project_id' => $project->id, 'version_no' => 1, 'title_id' => '${prefix} Topik '.$i, 'proposal_summary' => 'Ringkasan penelitian untuk pengujian tabel.', 'status' => 'approved', 'submitted_by_user_id' => $student->id, 'submitted_at' => now()]);
            ${model('ThesisSupervisorAssignment')}::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => $archived ? 'ended' : 'active', 'started_at' => now()->subMonth(), 'ended_at' => $archived ? now() : null]);
            $sempro = ${model('ThesisDefense')}::query()->create(['project_id' => $project->id, 'title_version_id' => $title->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'completed', 'result' => 'pass_with_revision', 'scheduled_for' => now()->subDays(2), 'location' => '${prefix} Ruang', 'mode' => 'offline']);
            ${model('ThesisDefenseExaminer')}::query()->create(['defense_id' => $sempro->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1]);
            if (!$archived) {
                $sidang = ${model('ThesisDefense')}::query()->create(['project_id' => $project->id, 'title_version_id' => $title->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'scheduled', 'result' => 'pending', 'scheduled_for' => now()->addDays(7)->addMinutes($i), 'location' => '${prefix} Ruang', 'mode' => 'offline']);
                ${model('ThesisDefenseExaminer')}::query()->create(['defense_id' => $sidang->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1]);
                ${model('MentorshipDocument')}::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'uploaded_by_user_id' => $student->id, 'title' => '${prefix} Dokumen '.$i, 'status' => 'submitted']);
            }
            if ($i === 0) {
                for ($j = 0; $j < 32; $j++) {
                    ${model('MentorshipDocument')}::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'uploaded_by_user_id' => $student->id, 'title' => '${prefix} Upload '.$j, 'status' => 'submitted']);
                    foreach (['completed', 'approved'] as $status) {
                        ${model('MentorshipSchedule')}::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $student->id, 'topic' => '${prefix} Bimbingan '.$j, 'status' => $status, 'requested_for' => $status === 'completed' ? now()->subDays(3) : now()->addDays(3), 'scheduled_for' => $status === 'completed' ? now()->subDays(3) : now()->addDays(3)]);
                    }
                }
            }
        }
        echo '${completionMarker}'.PHP_EOL;
    `;
    const output = execFileSync(
        'php',
        ['artisan', 'tinker', `--execute=${script}`],
        {
            cwd: repositoryRoot,
            env: playwrightEnvironment(),
            encoding: 'utf8',
        },
    );
    // Tinker can report a PHP exception while still exiting successfully.
    if (!output.split(/\r?\n/).includes(completionMarker)) {
        throw new Error(`Could not create sticky table fixtures: ${output}`);
    }
}

type TableCase = {
    path: string;
    section: string;
    role: 'head' | 'lecturer0' | 'student0' | 'guest';
    // Public supervisors share one toolbar across concentration tables.
    sharedToolbar?: boolean;
    // The section heading is the page's only h1 (no separate PageHeader).
    pageHeading?: boolean;
};

const inventory: TableCase[] = [
    {
        path: '/kaprodi/mahasiswa',
        section: 'Daftar Mahasiswa',
        role: 'head',
        pageHeading: true,
    },
    {
        path: '/kaprodi/dokumen',
        section: 'Dokumen Prodi',
        role: 'head',
        pageHeading: true,
    },
    {
        path: '/kaprodi/sempro-sidang',
        section: 'Monitoring Ujian',
        role: 'head',
    },
    {
        path: '/kaprodi/dosen-prodi',
        section: 'Daftar Dosen',
        role: 'head',
        pageHeading: true,
    },
    {
        path: '/dosen/mahasiswa-bimbingan',
        section: 'Mahasiswa Aktif',
        role: 'lecturer0',
    },
    {
        path: '/dosen/mahasiswa-bimbingan',
        section: 'Riwayat Mahasiswa',
        role: 'lecturer0',
    },
    {
        path: '/dosen/dokumen-revisi',
        section: 'Daftar Dokumen',
        role: 'lecturer0',
        pageHeading: true,
    },
    {
        path: '/dosen/seminar-proposal',
        section: 'Daftar ujian',
        role: 'lecturer0',
    },
    {
        path: '/dosen/jadwal-bimbingan',
        section: 'Jadwal Mendatang',
        role: 'lecturer0',
    },
    {
        path: '/dosen/jadwal-bimbingan',
        section: 'Riwayat Jadwal',
        role: 'lecturer0',
    },
    {
        path: '/mahasiswa/upload-dokumen',
        section: 'Dokumen yang Diupload',
        role: 'student0',
    },
    {
        path: '/mahasiswa/jadwal-bimbingan',
        section: 'Riwayat Bimbingan',
        role: 'student0',
    },
    { path: '/mahasiswa-aktif', section: 'Daftar Mahasiswa', role: 'guest' },
    { path: '/topik', section: 'Daftar Topik', role: 'guest' },
    { path: '/jadwal', section: 'Jadwal Akan Datang', role: 'guest' },
    { path: '/jadwal', section: 'Tindak Lanjut Terbaru', role: 'guest' },
    {
        path: '/pembimbing',
        section: 'Sistem',
        role: 'guest',
        sharedToolbar: true,
    },
];

async function login(page: Page, role: TableCase['role']): Promise<void> {
    if (role === 'guest') return;
    await page.goto('/login');
    await page.getByLabel('Email').fill(email(role));
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function scrollIntoRows(page: Page, bar: Locator): Promise<void> {
    await bar.scrollIntoViewIfNeeded();
    const scrollContainer = page.locator('html');
    const distance = await bar.evaluate(
        (element) => element.getBoundingClientRect().bottom + 360,
    );
    await scrollContainer.evaluate(
        (element, top) => element.scrollBy(0, top),
        distance,
    );
    await expect
        .poll(() => scrollContainer.evaluate((element) => element.scrollTop))
        .toBeGreaterThan(250);
}

test.describe('every searchable table keeps its complete controls together (#94)', () => {
    test.beforeAll(createFixtures);
    test.afterAll(cleanup);

    for (const width of [1280, 390]) {
        for (const theme of ['light', 'dark'] as const) {
            for (const item of inventory) {
                test(`${width}px ${theme} ${item.path} ${item.section}`, async ({
                    browser,
                }, testInfo) => {
                    const context = await browser.newContext({
                        viewport: { width, height: 800 },
                        colorScheme: theme,
                    });
                    await context.addInitScript(
                        (value) => localStorage.setItem('appearance', value),
                        theme,
                    );
                    const page = await context.newPage();
                    try {
                        await login(page, item.role);
                        await page.goto(item.path);
                        await expect(page.locator('html')).toHaveClass(
                            theme === 'dark' ? /dark/ : /^(?!.*\bdark\b)/,
                        );
                        const section = page.getByRole('region', {
                            name: item.section,
                            exact: true,
                        });
                        const bar = item.sharedToolbar
                            ? page.locator('[data-slot="sticky-filter-bar"]')
                            : section.locator(
                                  '[data-slot="sticky-filter-bar"]',
                              );
                        await expect(bar).toHaveCount(1);
                        if (item.pageHeading) {
                            await expect(page.locator('h1')).toHaveCount(1);
                            await expect(
                                page.getByRole('heading', {
                                    level: 1,
                                    name: item.section,
                                    exact: true,
                                }),
                            ).toBeVisible();
                        }
                        async function controls(): Promise<Locator> {
                            if (width >= 768) return bar;
                            const toggle = bar.getByRole('button', {
                                name: /^(?:Filter|Tutup filter)(?:,|$)/,
                            });
                            if (
                                (await toggle.getAttribute('aria-expanded')) !==
                                'true'
                            )
                                await toggle.click();
                            const panel = bar.getByRole('region', {
                                name: 'Pilihan filter',
                            });
                            const add = panel.getByRole('combobox', {
                                name: 'Tambah filter',
                            });
                            while (await add.count()) {
                                await add.click();
                                await page.getByRole('option').first().click();
                            }
                            return panel;
                        }
                        // Select only our program on the public supervisor directory.
                        if (item.sharedToolbar) {
                            await (await controls())
                                .getByRole('combobox')
                                .click();
                            await page
                                .getByRole('option', {
                                    name: prefix,
                                })
                                .click();
                        }
                        const pageSize = section.getByRole('combobox', {
                            name: 'Per halaman',
                            exact: true,
                        });
                        await pageSize.selectOption('25');
                        await expect(
                            section
                                .getByRole('button', {
                                    name: 'Berikutnya',
                                    exact: true,
                                })
                                .first(),
                        ).toBeEnabled();
                        await section
                            .getByRole('button', {
                                name: 'Berikutnya',
                                exact: true,
                            })
                            .first()
                            .click();
                        await expect(
                            section
                                .getByRole('button', {
                                    name: 'Sebelumnya',
                                    exact: true,
                                })
                                .first(),
                        ).toBeEnabled();
                        await pageSize.selectOption('50');
                        const rows = section.locator('tbody tr');
                        await expect
                            .poll(() => rows.count())
                            .toBeGreaterThanOrEqual(25);
                        await scrollIntoRows(page, bar);
                        const header = page
                            .locator('header.sticky, header.md\\:sticky')
                            .first();
                        if (width >= 768) {
                            const headerGap = async () => {
                                const barBox = await bar.boundingBox();
                                const headerBox = await header.boundingBox();
                                if (!barBox || !headerBox) return Infinity;
                                return Math.abs(
                                    barBox.y - headerBox.y - headerBox.height,
                                );
                            };
                            await expect.poll(headerGap).toBeLessThanOrEqual(2);
                            await expect(
                                bar.getByRole('searchbox'),
                            ).toBeInViewport();
                            if (item.role !== 'guest') {
                                // The width change reflows the table and can move the
                                // scroll position, so scroll into the rows again after
                                // each toggle before asserting the sticky offset.
                                for (let toggle = 0; toggle < 2; toggle++) {
                                    await page
                                        .locator(
                                            '[data-slot="sidebar-trigger"]',
                                        )
                                        .click();
                                    await page.waitForTimeout(400);
                                    await scrollIntoRows(page, bar);
                                    await expect
                                        .poll(headerGap)
                                        .toBeLessThanOrEqual(2);
                                }
                            }
                        } else {
                            // Phone controls scroll away instead of obscuring the rows.
                            await expect
                                .poll(async () => {
                                    const box = await bar.boundingBox();
                                    return (box?.y ?? 0) + (box?.height ?? 0);
                                })
                                .toBeLessThan(0);
                            await expect
                                .poll(() =>
                                    page.evaluate(
                                        () =>
                                            document.documentElement
                                                .scrollWidth <=
                                            window.innerWidth,
                                    ),
                                )
                                .toBe(true);
                        }
                        await expect
                            .poll(async () =>
                                rows.evaluateAll((elements) =>
                                    elements.some((element) => {
                                        const box =
                                            element.getBoundingClientRect();
                                        return (
                                            box.top >= 180 &&
                                            box.top < window.innerHeight
                                        );
                                    }),
                                ),
                            )
                            .toBe(true);
                        const screenshot =
                            testInfo.outputPath('scrolled-table.png');
                        await page.screenshot({ path: screenshot });
                        await testInfo.attach('scrolled-table', {
                            path: screenshot,
                            contentType: 'image/png',
                        });

                        // Interact after the real scroll; mobile deliberately returns to controls.
                        if (width < 768) await bar.scrollIntoViewIfNeeded();
                        const search = bar.getByRole('searchbox');
                        await search.fill(`${prefix}-no-match`);
                        await expect(rows).toHaveCount(0);
                        await search.fill(prefix);
                        await expect
                            .poll(() => rows.count())
                            .toBeGreaterThan(0);
                        const persistsSearch =
                            !item.sharedToolbar &&
                            item.section !== 'Jadwal Mendatang';
                        if (persistsSearch) {
                            await expect
                                .poll(() =>
                                    [
                                        ...new URL(
                                            page.url(),
                                        ).searchParams.values(),
                                    ].includes(prefix),
                                )
                                .toBe(true);
                        }
                        const groupCount = await (await controls())
                            .getByRole('group')
                            .count();
                        for (let index = 0; index < groupCount; index++) {
                            const choice = (await controls())
                                .getByRole('group')
                                .nth(index)
                                .getByRole('radio')
                                .nth(1);
                            await choice.click();
                            await expect(choice).toBeChecked();
                            await expect(search).toHaveValue(prefix);
                            await (await controls())
                                .getByRole('group')
                                .nth(index)
                                .getByRole('radio')
                                .first()
                                .click();
                        }
                        const selectCount = await (await controls())
                            .getByRole('combobox')
                            .count();
                        for (let index = 0; index < selectCount; index++) {
                            const select = (await controls())
                                .getByRole('combobox')
                                .nth(index);
                            const isProgram = /program studi/i.test(
                                (await select.getAttribute('aria-label')) ?? '',
                            );
                            await select.click();
                            const option = item.sharedToolbar
                                ? page.getByRole('option', {
                                      name: prefix,
                                  })
                                : page.getByRole('option').last();
                            await option.click();
                            if (
                                item.role === 'guest' &&
                                !item.sharedToolbar &&
                                isProgram
                            ) {
                                await expect(page).toHaveURL(
                                    (url) =>
                                        url.searchParams.get('program') ===
                                        prefix,
                                );
                            }
                            await expect(search).toHaveValue(prefix);
                            if (!item.sharedToolbar) {
                                await (await controls())
                                    .getByRole('combobox')
                                    .nth(index)
                                    .click();
                                await page.getByRole('option').first().click();
                                if (item.role === 'guest' && isProgram) {
                                    await expect(page).toHaveURL(
                                        (url) =>
                                            !url.searchParams.has('program'),
                                    );
                                }
                            }
                        }
                        if (persistsSearch) {
                            await expect
                                .poll(() =>
                                    [
                                        ...new URL(
                                            page.url(),
                                        ).searchParams.values(),
                                    ].includes(prefix),
                                )
                                .toBe(true);
                        }
                        await search.fill('');
                        await expect
                            .poll(() => rows.count())
                            .toBeGreaterThan(0);
                    } finally {
                        await context.close();
                    }
                });
            }
        }
    }
});
