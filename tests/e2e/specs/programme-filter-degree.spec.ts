import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, test, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

function php(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        encoding: 'utf8',
    });
}

async function programmeSelect(page: Page, mobile: boolean) {
    if (mobile) {
        await page.getByRole('button', { name: 'Filter', exact: true }).click();
        if (
            !(await page
                .getByRole('combobox', { name: 'Program studi', exact: true })
                .isVisible())
        ) {
            await page.getByRole('combobox', { name: 'Tambah filter' }).click();
            await page
                .getByRole('option', { name: 'Program studi', exact: true })
                .click();
        }
    }
    return page.getByRole('combobox', { name: 'Program studi', exact: true });
}

test('same-name S1 and S2 programmes stay distinguishable and filter independently on progress and public pages', async ({
    browser,
}, testInfo) => {
    test.setTimeout(240_000);
    const token = `programme-${randomUUID()}`;
    const output = php(`
        $faculty = App\\Models\\Faculty::factory()->create(['name' => '${token}']);
        $out = ['faculty' => $faculty->id, 'programmes' => [], 'users' => [], 'projects' => []];
        foreach (['s1', 's2'] as $degree) {
            $programme = App\\Models\\ProgramStudi::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Ilmu Komputer Demo Filter', 'slug' => '${token}-'.$degree, 'degree_levels' => [$degree]]);
            $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Demo Filter '.strtoupper($degree)]);
            App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $programme->id, 'degree_level' => $degree, 'is_active' => true]);
            $lecturer = App\\Models\\User::factory()->asDosen()->create(['name' => 'Pembimbing Demo Filter '.strtoupper($degree)]);
            App\\Models\\DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $programme->id, 'is_active' => true]);
            $project = App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $programme->id, 'phase' => 'research', 'state' => 'active', 'started_at' => now()->subMonth()]);
            $title = App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $project->id, 'version_no' => 1, 'title_id' => 'Topik Demo Filter '.strtoupper($degree), 'status' => 'approved', 'submitted_by_user_id' => $student->id, 'submitted_at' => now()->subMonth()]);
            App\\Models\\ThesisDefense::query()->create(['project_id' => $project->id, 'title_version_id' => $title->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'completed', 'result' => 'pass', 'scheduled_for' => now()->subWeek()]);
            $out['programmes'][$degree] = $programme->id;
            $out['users'][] = $student->id;
            $out['users'][] = $lecturer->id;
            $out['projects'][] = $project->id;
        }
        echo 'FILTER_FIXTURE='.json_encode($out).PHP_EOL;
    `);
    const fixture = JSON.parse(output.match(/FILTER_FIXTURE=(.+)/)![1]) as {
        faculty: number;
        programmes: Record<'s1' | 's2', number>;
        users: number[];
        projects: number[];
    };
    try {
        for (const width of [1280, 390]) {
            for (const theme of ['light', 'dark'] as const) {
                for (const [route, key, expected] of [
                    [
                        '/progres-mahasiswa',
                        'progress',
                        'Mahasiswa Demo Filter S2',
                    ],
                    [
                        '/mahasiswa-aktif',
                        'students',
                        'Mahasiswa Demo Filter S2',
                    ],
                    ['/topik', 'topics', 'Topik Demo Filter S2'],
                    ['/pembimbing', 'advisors', 'Pembimbing Demo Filter S2'],
                ]) {
                    const context = await browser.newContext({
                        baseURL: process.env.PLAYWRIGHT_BASE_URL,
                        viewport: { width, height: 844 },
                        colorScheme: theme,
                        storageState:
                            route === '/progres-mahasiswa'
                                ? authStatePath('mahasiswa')
                                : { cookies: [], origins: [] },
                    });
                    try {
                        await context.addInitScript(
                            (appearance) =>
                                localStorage.setItem('appearance', appearance),
                            theme,
                        );
                        const page = await context.newPage();
                        await page.goto(route);
                        const select = await programmeSelect(
                            page,
                            width === 390,
                        );
                        await select.click();
                        await expect(
                            page.getByRole('option', {
                                name: 'Ilmu Komputer Demo Filter (S1)',
                                exact: true,
                            }),
                        ).toBeVisible();
                        await expect(
                            page.getByRole('option', {
                                name: 'Ilmu Komputer Demo Filter (S2)',
                                exact: true,
                            }),
                        ).toBeVisible();
                        await page.screenshot({
                            path: testInfo.outputPath(
                                `${key}-${width}-${theme}-options.png`,
                            ),
                        });
                        await page
                            .getByRole('option', {
                                name: 'Ilmu Komputer Demo Filter (S2)',
                                exact: true,
                            })
                            .click();
                        if (route === '/progres-mahasiswa') {
                            await expect(page).toHaveURL(
                                new RegExp(
                                    `prodi=${fixture.programmes.s2}(?:&|$)`,
                                ),
                            );
                        } else if (route !== '/pembimbing') {
                            await expect(page).toHaveURL(
                                new RegExp(`program=${token}-s2(?:&|$)`),
                            );
                        }
                        if (width === 390 && !(await select.isVisible())) {
                            await page
                                .getByRole('button', { name: /^Filter(?:,|$)/ })
                                .click();
                        }
                        await expect(select).toContainText(
                            'Ilmu Komputer Demo Filter (S2)',
                        );
                        await expect(page.locator('main')).toContainText(
                            expected,
                        );
                        await expect(page.locator('main')).not.toContainText(
                            expected.replace('S2', 'S1'),
                        );
                        expect(
                            await page.evaluate(
                                () =>
                                    document.documentElement.scrollWidth <=
                                    innerWidth,
                            ),
                        ).toBe(true);
                        await page.screenshot({
                            path: testInfo.outputPath(
                                `${key}-${width}-${theme}-selected.png`,
                            ),
                        });
                    } finally {
                        await context.close();
                    }
                }
            }
        }
    } finally {
        php(`
            App\\Models\\ThesisProject::query()->whereIn('id', [${fixture.projects.join(',')}])->delete();
            App\\Models\\User::query()->whereIn('id', [${fixture.users.join(',')}])->delete();
            App\\Models\\ProgramStudi::query()->whereIn('id', [${Object.values(fixture.programmes).join(',')}])->delete();
            App\\Models\\Faculty::query()->whereKey(${fixture.faculty})->delete();
        `);
    }
});
