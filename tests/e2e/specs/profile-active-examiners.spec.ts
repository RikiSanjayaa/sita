import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, test } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    runArtisan,
} from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

type Fixture = {
    lecturer: number;
    students: Record<string, number>;
    projects: Record<string, number>;
};

function createFixture(prefix: string): Fixture {
    const script = `
        $prefix = '${prefix}';
        $result = Illuminate\\Support\\Facades\\DB::transaction(function () use ($prefix) {
            $faculty = App\\Models\\Faculty::factory()->create(['name' => $prefix]);
            $prodi = App\\Models\\ProgramStudi::factory()->create(['name' => $prefix, 'faculty_id' => $faculty->id]);
            $lecturer = App\\Models\\User::factory()->asDosen()->create(['name' => $prefix.' Examiner', 'email' => $prefix.'-lecturer@sita.test']);
            App\\Models\\DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $prodi->id]);
            $result = ['lecturer' => $lecturer->id, 'students' => [], 'projects' => []];
            foreach (['Active', 'Completing', 'Graduating', 'CancelledExam', 'CancelledThesis', 'Superseded', 'Supervised'] as $kind) {
                $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => $prefix.' '.$kind, 'email' => $prefix.'-'.strtolower($kind).'@sita.test']);
                App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $prodi->id]);
                $project = App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $prodi->id, 'state' => $kind === 'CancelledThesis' ? 'cancelled' : 'active', 'phase' => 'sidang', 'started_at' => now()->subMonth()]);
                foreach ([1, 2] as $attempt) {
                    $defense = App\\Models\\ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => $attempt, 'status' => $kind === 'CancelledExam' ? 'cancelled' : 'scheduled']);
                    App\\Models\\ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1]);
                }
                if ($kind === 'Supervised') {
                    App\\Models\\ThesisSupervisorAssignment::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => 'active', 'started_at' => now()->subMonth()]);
                }
                if ($kind === 'Superseded') {
                    $project->update(['state' => 'completed', 'phase' => 'completed']);
                    App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $project->id, 'version_no' => 1, 'title_id' => $prefix.' Archived title', 'status' => 'approved']);
                    App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $prodi->id, 'state' => 'active', 'phase' => 'title_review', 'started_at' => now()]);
                }
                $result['students'][$kind] = $student->id;
                $result['projects'][$kind] = $project->id;
            }
            return $result;
        });
        echo 'FIXTURE:'.json_encode($result);
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
    const match = output.match(/FIXTURE:(\{[^\n]+\})/);
    if (match === null) {
        throw new Error(`Could not create profile fixture: ${output}`);
    }
    return JSON.parse(match[1]) as Fixture;
}

function cleanupFixture(prefix: string): void {
    runArtisan([
        'tinker',
        `--execute=App\\Models\\ThesisProject::query()->whereHas('student', fn($query) => $query->where('email', 'like', '${prefix}-%@sita.test'))->delete(); App\\Models\\User::query()->where('email', 'like', '${prefix}-%@sita.test')->delete(); App\\Models\\ProgramStudi::query()->where('name', '${prefix}')->delete(); App\\Models\\Faculty::query()->where('name', '${prefix}')->delete();`,
    ]);
}

test('active examiner students are unique and leave the profile after completion or graduation', async ({
    browser,
}) => {
    const prefix = `pw97-${randomUUID()}`;
    const context = await browser.newContext({
        storageState: authStatePath('dosen3'),
    });
    try {
        const fixture = createFixture(prefix);
        const page = await context.newPage();
        await page.goto(`/profil/${fixture.lecturer}`);
        const examined = page.locator('section').filter({
            has: page.getByRole('heading', {
                name: 'Mahasiswa yang Anda uji',
                exact: true,
            }),
        });
        const supervised = page.locator('section').filter({
            has: page.getByRole('heading', {
                name: 'Mahasiswa bimbingan aktif',
                exact: true,
            }),
        });
        for (const kind of ['Active', 'Completing', 'Graduating']) {
            await expect(
                examined.getByRole('link', {
                    name: new RegExp(`${prefix} ${kind}`),
                }),
            ).toHaveCount(1);
        }
        await expect(examined.getByRole('link')).toHaveCount(3);
        for (const kind of [
            'CancelledExam',
            'CancelledThesis',
            'Superseded',
            'Supervised',
        ]) {
            await expect(
                examined.getByText(`${prefix} ${kind}`, { exact: true }),
            ).toHaveCount(0);
        }
        await expect(
            supervised.getByRole('link', {
                name: new RegExp(`${prefix} Supervised`),
            }),
        ).toHaveCount(1);
        await expect(
            page.getByRole('link', {
                name: new RegExp(`${prefix} Supervised`),
            }),
        ).toHaveCount(1);

        runArtisan([
            'tinker',
            `--execute=App\\Models\\ThesisDefense::query()->where('project_id', ${fixture.projects.Completing})->update(['status' => 'completed', 'result' => 'pass']); App\\Models\\ThesisProject::query()->whereKey(${fixture.projects.Graduating})->update(['state' => 'completed', 'phase' => 'completed', 'completed_at' => now()]);`,
        ]);
        await page.reload();
        await expect(examined.getByRole('link')).toHaveCount(1);
        await expect(
            examined.getByRole('link', {
                name: new RegExp(`${prefix} Active`),
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', {
                name: new RegExp(`${prefix} Completing|${prefix} Graduating`),
            }),
        ).toHaveCount(0);

        await page.goto(`/profil/${fixture.students.Superseded}`);
        await expect(
            page.getByText(`${prefix} Archived title`, { exact: true }),
        ).toHaveCount(0);
        await expect(
            page
                .locator('dt')
                .filter({ hasText: /^Arsip Skripsi$/ })
                .locator('..')
                .locator('dd'),
        ).toHaveText('0');
        // Inspect the actual initial props, without an unversioned Inertia request.
        const body = await page.locator('#app').evaluate((element) => {
            const payload = element.getAttribute('data-page');
            if (payload === null) {
                throw new Error(
                    'Profile page is missing its initial Inertia payload',
                );
            }
            return JSON.parse(payload);
        });
        expect(
            body.props.profile.stats.find(
                (stat: { label: string }) => stat.label === 'Total Skripsi',
            ).value,
        ).toBe('1');
        expect(
            body.props.profile.stats.find(
                (stat: { label: string }) => stat.label === 'Total Ujian',
            ).value,
        ).toBe('0');
        expect(JSON.stringify(body.props.profile)).not.toContain(
            `${prefix} Archived title`,
        );

        const adminContext = await browser.newContext({
            storageState: authStatePath('admin'),
        });
        try {
            const adminPage = await adminContext.newPage();
            await adminPage.goto(`/profil/${fixture.students.Superseded}`);
            await expect(
                adminPage
                    .locator('dt')
                    .filter({ hasText: /^Arsip Skripsi$/ })
                    .locator('..')
                    .locator('dd'),
            ).toHaveText('1');
            await expect(
                adminPage
                    .locator('dt')
                    .filter({ hasText: /^Total Skripsi$/ })
                    .locator('..')
                    .locator('dd'),
            ).toHaveText('2');
            await expect(
                adminPage
                    .locator('dt')
                    .filter({ hasText: /^Total Ujian$/ })
                    .locator('..')
                    .locator('dd'),
            ).toHaveText('2');
        } finally {
            await adminContext.close();
        }
    } finally {
        await context.close();
        cleanupFixture(prefix);
    }
});
