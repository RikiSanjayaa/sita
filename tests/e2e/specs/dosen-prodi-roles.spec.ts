import { expect, test, type Locator, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';

const fixture = {
    slug: 'e2e-lecturer-role-pills',
    facultyCode: 'E2ELRP',
    emailSuffix: '@lecturer-role-pills.sita.test',
    kaprodiEmail: 'kaprodi@lecturer-role-pills.sita.test',
    kaprodiName: 'Kaprodi Peran Dosen',
    lecturerName: 'Dosen Peran 01',
};

const model = (name: string): string => `App\\Models\\${name}`;

function cleanupFixtures(): void {
    runArtisan([
        'tinker',
        `--execute=$ps = ${model('ProgramStudi')}::query()->where('slug', '${fixture.slug}')->first();
        if ($ps) {
            ${model('ThesisSupervisorAssignment')}::query()->whereHas('project', fn($q) => $q->where('program_studi_id', $ps->id))->delete();
            ${model('ThesisProject')}::query()->where('program_studi_id', $ps->id)->delete();
            ${model('KaprodiAssignment')}::query()->where('program_studi_id', $ps->id)->delete();
            ${model('DosenProgramStudiAssignment')}::query()->where('program_studi_id', $ps->id)->delete();
        }
        foreach (${model('User')}::query()->where('email', 'like', '%${fixture.emailSuffix}')->get() as $user) {
            ${model('DosenProfile')}::query()->where('user_id', $user->id)->delete();
            ${model('MahasiswaProfile')}::query()->where('user_id', $user->id)->delete();
            $user->roles()->detach();
            $user->delete();
        }
        if ($ps) { $ps->delete(); }
        ${model('Faculty')}::query()->where('code', '${fixture.facultyCode}')->delete();`,
    ]);
}

function createFixtures(): void {
    cleanupFixtures();
    runArtisan([
        'tinker',
        `--execute=$faculty = ${model('Faculty')}::factory()->create(['code' => '${fixture.facultyCode}', 'slug' => '${fixture.slug}']);
        $ps = ${model('ProgramStudi')}::factory()->create(['faculty_id' => $faculty->id, 'slug' => '${fixture.slug}', 'name' => 'Program Studi Peran Dosen', 'concentrations' => ['Sistem', 'Jaringan'], 'degree_levels' => ['s1']]);
        $kaprodi = ${model('User')}::factory()->asKaprodi()->create(['name' => '${fixture.kaprodiName}', 'email' => '${fixture.kaprodiEmail}']);
        ${model('DosenProfile')}::query()->create(['user_id' => $kaprodi->id, 'nik' => '9988960031', 'program_studi_id' => $ps->id, 'concentration' => 'Sistem', 'supervision_quota' => 12, 'is_active' => true]);
        ${model('KaprodiAssignment')}::query()->create(['program_studi_id' => $ps->id, 'user_id' => $kaprodi->id, 'is_primary' => true, 'capabilities' => ${model('KaprodiAssignment')}::defaultCapabilities()]);
        $lead = null;
        for ($i = 1; $i <= 30; $i++) {
            $lecturer = ${model('User')}::factory()->asDosen()->create(['name' => sprintf('Dosen Peran %02d', $i), 'email' => 'dosen'.$i.'${fixture.emailSuffix}']);
            ${model('DosenProfile')}::query()->create(['user_id' => $lecturer->id, 'nik' => sprintf('998896%04d', $i), 'program_studi_id' => $ps->id, 'concentration' => $i % 2 ? 'Sistem' : 'Jaringan', 'supervision_quota' => 12, 'is_active' => true]);
            if ($i === 1) { $lead = $lecturer; }
        }
        for ($i = 1; $i <= 3; $i++) {
            $student = ${model('User')}::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Peran '.$i, 'email' => 'student'.$i.'${fixture.emailSuffix}']);
            ${model('MahasiswaProfile')}::query()->create(['user_id' => $student->id, 'nim' => '998896000'.$i, 'program_studi_id' => $ps->id, 'degree_level' => 's1', 'concentration' => 'Sistem', 'angkatan' => 2024, 'is_active' => true]);
            $project = ${model('ThesisProject')}::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $ps->id, 'phase' => 'research', 'state' => 'active', 'started_at' => now()]);
            ${model('ThesisSupervisorAssignment')}::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $lead->id, 'role' => $i === 1 ? 'primary' : 'secondary', 'status' => 'active', 'assigned_by' => $kaprodi->id, 'started_at' => now()]);
        }`,
    ]);
}

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(fixture.kaprodiEmail);
    await page.getByLabel('Kata sandi', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/kaprodi/);
    await page.goto('/kaprodi/dosen-prodi');
}

async function assertRolePair(
    row: Locator,
    primary: number,
    secondary: number,
): Promise<void> {
    const cell = row.locator('td[data-label="Bimbingan"]');
    for (const [label, count] of [
        ['P1', primary],
        ['P2', secondary],
    ] as const) {
        const pill = cell
            .locator('[data-slot="badge"]')
            .filter({ hasText: new RegExp(`^${label}$`) });
        await expect(pill).toBeVisible();
        await expect(pill.locator('..')).toHaveText(
            new RegExp(`^${label}\\s*${count}$`),
        );
        const number = pill.locator('..').locator('span.tabular-nums');
        await expect(number).toHaveText(String(count));
        await expect(number).toHaveCSS('font-variant-numeric', 'tabular-nums');
        await expect(number.locator('[data-slot="badge"]')).toHaveCount(0);
    }
    await expect(cell.getByText('Kuota 12', { exact: true })).toBeVisible();
    await expect(cell.getByText('Kuota 12', { exact: true })).toHaveCSS(
        'font-variant-numeric',
        'tabular-nums',
    );
    await expect(cell.locator('[data-slot="badge"]')).toHaveCount(2);

    const colors = await cell
        .locator('[data-slot="badge"]')
        .evaluateAll((pills) => {
            // Canvas resolves the browser's computed OKLCH colors to sRGB bytes.
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            })!;
            const luminance = (color: string): number => {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                const bytes = context.getImageData(0, 0, 1, 1).data;
                const channels = Array.from(bytes)
                    .slice(0, 3)
                    .map((value) => {
                        const channel = value / 255;
                        return channel <= 0.04045
                            ? channel / 12.92
                            : ((channel + 0.055) / 1.055) ** 2.4;
                    });
                return (
                    0.2126 * channels[0] +
                    0.7152 * channels[1] +
                    0.0722 * channels[2]
                );
            };
            return pills.map((pill) => {
                const style = getComputedStyle(pill);
                const foreground = luminance(style.color);
                const background = luminance(style.backgroundColor);
                return {
                    fill: style.backgroundColor,
                    contrast:
                        (Math.max(foreground, background) + 0.05) /
                        (Math.min(foreground, background) + 0.05),
                };
            });
        });
    expect(colors[0].fill).not.toBe(colors[1].fill);
    for (const color of colors)
        expect(color.contrast).toBeGreaterThanOrEqual(4.5);
}

test.describe('Dosen Prodi role pills and sticky filters', () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeAll(createFixtures);
    test.afterAll(cleanupFixtures);

    for (const width of [1280, 390]) {
        for (const theme of ['light', 'dark'] as const) {
            test(`${width}px ${theme}: labeled roles, plain counts and usable filters after real scroll`, async ({
                browser,
            }, testInfo) => {
                const context = await browser.newContext({
                    viewport: { width, height: 844 },
                    colorScheme: theme,
                });
                await context.addInitScript(
                    (appearance) =>
                        localStorage.setItem('appearance', appearance),
                    theme,
                );
                const page = await context.newPage();
                try {
                    await login(page);
                    if (theme === 'dark') {
                        await expect(page.locator('html')).toHaveClass(/dark/);
                    } else {
                        await expect(page.locator('html')).not.toHaveClass(
                            /dark/,
                        );
                    }
                    const section = page.getByRole('region', {
                        name: 'Daftar Dosen',
                        exact: true,
                    });
                    const rows = section.locator('tbody tr');
                    await expect(rows).toHaveCount(15);
                    await assertRolePair(
                        rows.filter({ hasText: fixture.lecturerName }),
                        1,
                        2,
                    );
                    await assertRolePair(
                        rows.filter({ hasText: 'Dosen Peran 02' }),
                        0,
                        0,
                    );
                    await section
                        .getByRole('button', {
                            name: 'Berikutnya',
                            exact: true,
                        })
                        .first()
                        .click();
                    await expect(page).toHaveURL(/[?&]page=2/);
                    await expect(rows.first()).toContainText('Dosen Peran 16');
                    await section.getByLabel('Per halaman').selectOption('50');
                    // Kaprodi also holds the dosen role and belongs in the directory.
                    await expect(rows).toHaveCount(31);
                    await assertRolePair(
                        rows.filter({ hasText: fixture.kaprodiName }),
                        0,
                        0,
                    );
                    const main = page.locator('[data-slot="sidebar-inset"]');
                    const bar = section.locator(
                        '[data-slot="sticky-filter-bar"]',
                    );
                    const search = bar.getByRole('searchbox');
                    const concentrations = bar.getByRole('group', {
                        name: 'Filter konsentrasi',
                    });
                    await main.hover({
                        position: { x: width === 390 ? 300 : 500, y: 600 },
                    });
                    await page.mouse.wheel(0, 900);
                    await expect
                        .poll(() =>
                            page.evaluate(
                                () => document.scrollingElement!.scrollTop,
                            ),
                        )
                        .toBeGreaterThan(600);

                    if (width >= 768) {
                        const header = main.locator(':scope > header');
                        const assertOffset = async () => {
                            await expect
                                .poll(async () => {
                                    const filterBounds =
                                        await bar.boundingBox();
                                    const headerBounds =
                                        await header.boundingBox();
                                    return Math.abs(
                                        filterBounds!.y -
                                            (headerBounds!.y +
                                                headerBounds!.height),
                                    );
                                })
                                .toBeLessThanOrEqual(1);
                        };
                        await assertOffset();
                        await page
                            .locator('[data-slot="sidebar-trigger"]')
                            .click();
                        await assertOffset();
                    } else {
                        await expect(bar).toHaveCSS('position', 'static');
                        await expect
                            .poll(async () => (await bar.boundingBox())!.y)
                            .toBeLessThan(0);
                        await search.scrollIntoViewIfNeeded();
                    }

                    await concentrations
                        .getByRole('radio', { name: /^Sistem\s*16$/ })
                        .click();
                    await search.fill('Dosen Peran 01');
                    await expect(rows).toHaveCount(1);
                    await expect(page).toHaveURL(/[?&]concentration=Sistem/);
                    await expect(page).toHaveURL(/[?&]search=Dosen/);
                    await page.reload();
                    await expect(search).toHaveValue('Dosen Peran 01');
                    await expect(
                        concentrations.getByRole('radio', {
                            name: /^Sistem\s*16$/,
                        }),
                    ).toBeChecked();
                    await expect(rows).toHaveCount(1);
                    await section.getByLabel('Per halaman').selectOption('50');
                    await assertRolePair(rows.first(), 1, 2);
                    await concentrations
                        .getByRole('radio', { name: /^Jaringan\s*15$/ })
                        .click();
                    await expect(
                        section.getByText('Tidak ada dosen ditemukan', {
                            exact: true,
                        }),
                    ).toBeVisible();
                    await search.fill('');
                    await expect(rows).toHaveCount(15);
                    await concentrations
                        .getByRole('radio', { name: /^Semua\s*31$/ })
                        .click();
                    await expect(rows).toHaveCount(31);
                    await rows.first().scrollIntoViewIfNeeded();
                    await expect
                        .poll(() =>
                            main.evaluate(
                                (element) =>
                                    element.scrollWidth - element.clientWidth,
                            ),
                        )
                        .toBeLessThanOrEqual(1);
                    expect(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth -
                                window.innerWidth,
                        ),
                    ).toBeLessThanOrEqual(1);
                    await page.screenshot({
                        path: testInfo.outputPath(
                            `dosen-prodi-${width}-${theme}.png`,
                        ),
                    });
                } finally {
                    await context.close();
                }
            });
        }
    }
});
