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
    student: number;
    current: number;
    completedStudent: number;
    oldProjects: number[];
};

function createFixture(prefix: string): Fixture {
    const script = `
        $result = Illuminate\\Support\\Facades\\DB::transaction(function () {
            $prefix = '${prefix}';
            $kaprodi = App\\Models\\User::query()->where('email', 'kaprodi@sita.test')->firstOrFail();
            $program = $kaprodi->kaprodiAssignment->program_studi_id;
            $students = [];
            foreach (['Current', 'Completed'] as $kind) {
                $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => $prefix.' '.$kind, 'email' => $prefix.'-'.strtolower($kind).'@sita.test']);
                App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program, 'degree_level' => 's1', 'is_active' => true]);
                $students[$kind] = $student;
            }
            $oldProjects = [];
            foreach (['completed', 'cancelled', 'active'] as $index => $state) {
                $project = App\\Models\\ThesisProject::query()->create(['student_user_id' => $students['Current']->id, 'program_studi_id' => $program, 'phase' => $state === 'completed' ? 'completed' : 'sempro', 'state' => $state, 'started_at' => now()->subMonths(12 - $index)]);
                App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $project->id, 'version_no' => 1, 'title_id' => $prefix.' Historical '.$state, 'status' => 'approved']);
                App\\Models\\ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'scheduled', 'scheduled_for' => now()->addWeek(), 'location' => $prefix.' Historical room']);
                App\\Models\\ThesisProjectNote::factory()->create(['project_id' => $project->id, 'author_user_id' => $kaprodi->id, 'body' => $prefix.' Historical note']);
                $oldProjects[] = $project->id;
            }
            $current = App\\Models\\ThesisProject::query()->create(['student_user_id' => $students['Current']->id, 'program_studi_id' => $program, 'phase' => 'title_review', 'state' => 'active', 'started_at' => now()]);
            foreach ([1 => 'Original private title', 2 => 'Revised private title'] as $version => $title) {
                App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $current->id, 'version_no' => $version, 'title_id' => $prefix.' '.$title, 'status' => 'submitted']);
            }
            $completed = App\\Models\\ThesisProject::query()->create(['student_user_id' => $students['Completed']->id, 'program_studi_id' => $program, 'phase' => 'completed', 'state' => 'completed', 'started_at' => now()->subYear(), 'completed_at' => now()->subMonth()]);
            App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $completed->id, 'version_no' => 1, 'title_id' => $prefix.' Published completed title', 'status' => 'approved']);
            return ['student' => $students['Current']->id, 'current' => $current->id, 'completedStudent' => $students['Completed']->id, 'oldProjects' => $oldProjects];
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
        throw new Error(
            `Could not create project visibility fixture: ${output}`,
        );
    }
    return JSON.parse(match[1]) as Fixture;
}

function cleanupFixture(prefix: string): void {
    runArtisan([
        'tinker',
        `--execute=App\\Models\\ThesisProject::query()->whereHas('student', fn($query) => $query->where('email', 'like', '${prefix}-%@sita.test'))->delete(); App\\Models\\User::query()->where('email', 'like', '${prefix}-%@sita.test')->delete();`,
    ]);
}

test('current directory rows exclude old theses while admin retains their history', async ({
    browser,
}) => {
    const prefix = `pw95-${randomUUID()}`;
    const kaprodiContext = await browser.newContext({
        storageState: authStatePath('kaprodi'),
    });
    const publicContext = await browser.newContext();
    const viewerContext = await browser.newContext({
        storageState: authStatePath('dosen3'),
    });
    const adminContext = await browser.newContext({
        storageState: authStatePath('admin'),
    });
    try {
        const fixture = createFixture(prefix);
        const page = await kaprodiContext.newPage();
        await page.goto('/kaprodi/mahasiswa');
        const inertiaVersion = await page
            .locator('#app')
            .evaluate((element) => {
                const initialPage = JSON.parse(
                    element.getAttribute('data-page') ?? '{}',
                ) as { version: string };
                return initialPage.version;
            });
        expect(inertiaVersion).toEqual(expect.any(String));
        const inertiaHeaders = {
            'X-Inertia': 'true',
            'X-Inertia-Version': inertiaVersion,
            'X-Requested-With': 'XMLHttpRequest',
        };
        await page
            .getByPlaceholder('Cari nama atau NIM...')
            .fill(`${prefix} Current`);
        const row = page
            .getByRole('row')
            .filter({ hasText: `${prefix} Current` });
        await expect(row).toHaveCount(1);
        await expect(row).toContainText('Review Judul');
        await expect(
            page.getByText(`${prefix} Historical completed`, { exact: true }),
        ).toHaveCount(0);
        const directory = await page.request.get('/kaprodi/mahasiswa', {
            headers: inertiaHeaders,
        });
        expect(directory.ok()).toBeTruthy();
        const props = (await directory.json()).props;
        expect(
            props.students.filter(
                (student: { id: number }) => student.id === fixture.student,
            ),
        ).toHaveLength(1);
        expect(
            props.archives.filter(
                (archive: { studentId: number }) =>
                    archive.studentId === fixture.student,
            ),
        ).toHaveLength(0);
        expect(JSON.stringify(props)).not.toContain(`${prefix} Historical`);
        for (const id of fixture.oldProjects) {
            expect(Object.keys(props.projectNotes)).not.toContain(String(id));
        }

        const publicPage = await publicContext.newPage();
        await publicPage.goto(`/topik?search=${encodeURIComponent(prefix)}`);
        await expect(
            publicPage.getByText(`${prefix} Published completed title`, {
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            publicPage.getByText(`${prefix} Revised private title`, {
                exact: true,
            }),
        ).toHaveCount(0);
        for (const path of ['/topik', '/mahasiswa-aktif', '/jadwal']) {
            const response = await publicPage.request.get(
                `${path}?search=${encodeURIComponent(prefix)}`,
                {
                    headers: inertiaHeaders,
                },
            );
            expect(response.ok()).toBeTruthy();
            const payload = JSON.stringify((await response.json()).props);
            expect(payload).not.toContain(`${prefix} Historical`);
            expect(payload).not.toContain(`${prefix} Original private title`);
            expect(payload).not.toContain(`${prefix} Revised private title`);
        }
        await publicPage.goto(
            '/mahasiswa-aktif?search=' +
                encodeURIComponent(`${prefix} Current`),
        );
        await expect(
            publicPage
                .getByRole('row')
                .filter({ hasText: `${prefix} Current` }),
        ).toHaveCount(1);

        const viewerPage = await viewerContext.newPage();
        await viewerPage.goto(`/profil/${fixture.student}`);
        await expect(
            viewerPage.getByText(`${prefix} Revised private title`, {
                exact: true,
            }),
        ).toHaveCount(0);
        const profile = await viewerPage.request.get(
            `/profil/${fixture.student}`,
            {
                headers: inertiaHeaders,
            },
        );
        expect(profile.ok()).toBeTruthy();
        expect(
            JSON.stringify((await profile.json()).props.profile),
        ).not.toContain(`${prefix} Historical`);

        const adminPage = await adminContext.newPage();
        for (const [index, state] of [
            'completed',
            'cancelled',
            'active',
        ].entries()) {
            await adminPage.goto(
                `/admin/thesis-projects/${fixture.oldProjects[index]}`,
            );
            await expect(
                adminPage
                    .getByText(`${prefix} Historical ${state}`, { exact: true })
                    .first(),
            ).toBeVisible();
        }

        runArtisan([
            'tinker',
            `--execute=App\\Models\\ThesisProject::query()->create(['student_user_id' => ${fixture.completedStudent}, 'program_studi_id' => App\\Models\\ThesisProject::query()->findOrFail(${fixture.current})->program_studi_id, 'phase' => 'title_review', 'state' => 'cancelled', 'started_at' => now()]);`,
        ]);
        await publicPage.goto(`/topik?search=${encodeURIComponent(prefix)}`);
        await expect(
            publicPage.getByText(`${prefix} Published completed title`, {
                exact: true,
            }),
        ).toHaveCount(0);
        await page.reload();
        await page
            .getByPlaceholder('Cari nama atau NIM...')
            .fill(`${prefix} Completed`);
        await expect(
            page.getByRole('row').filter({ hasText: `${prefix} Completed` }),
        ).toHaveCount(1);
    } finally {
        await kaprodiContext.close();
        await publicContext.close();
        await viewerContext.close();
        await adminContext.close();
        cleanupFixture(prefix);
    }
});
