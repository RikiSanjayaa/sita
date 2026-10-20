import { execFileSync } from 'node:child_process';

import { expect, test, type Locator, type Page } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    runArtisan,
} from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

function toDateTimeLocalString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function scheduledDateTimeLabel(scheduledFor: string): string {
    const scheduledDate = new Date(`${scheduledFor}:00+08:00`);
    const dateLabel = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Makassar',
    }).format(scheduledDate);
    const timeLabel = scheduledFor.slice(-5).replace(':', '.');

    return `${dateLabel}, ${timeLabel}`;
}

function scheduleSidangForStudent(
    studentEmail: string,
    scheduledFor: string,
    location: string,
    panelEmails: [string, string, string],
    notes: string,
): void {
    const emailList = panelEmails.map((email) => `"${email}"`).join(', ');

    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); $panelUserIds = App\\Models\\User::query()->whereIn("email", [${emailList}])->pluck("id")->all(); app(App\\Services\\ThesisProjectAdminService::class)->scheduleSidang(project: $project, createdBy: $admin->id, scheduledFor: "${scheduledFor}", location: "${location}", mode: "offline", panelUserIds: $panelUserIds, notes: "${notes}");`,
    ]);
}

function assignSupervisorsForStudent(
    studentEmail: string,
    primaryLecturerEmail: string,
    secondaryLecturerEmail: string,
    notes: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); $primary = App\\Models\\User::query()->where("email", "${primaryLecturerEmail}")->firstOrFail(); $secondary = App\\Models\\User::query()->where("email", "${secondaryLecturerEmail}")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->assignSupervisors(project: $project, assignedBy: $admin->id, primaryLecturerUserId: $primary->id, secondaryLecturerUserId: $secondary->id, notes: "${notes}");`,
    ]);
}

function submitSidangDecisionForStudent(
    studentEmail: string,
    lecturerEmail: string,
    decision: 'pass' | 'pass_with_revision' | 'fail',
    score: number,
    decisionNotes: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $defense = $project->sidangDefenses()->latest("attempt_no")->firstOrFail(); $lecturer = App\\Models\\User::query()->where("email", "${lecturerEmail}")->firstOrFail(); app(App\\Services\\ThesisDefenseExaminerDecisionService::class)->submit($lecturer, $defense, ["decision" => "${decision}", "score" => ${score}, "decision_notes" => "${decisionNotes}"]);`,
    ]);
}

function completeSidangForStudent(
    studentEmail: string,
    result: 'pass' | 'pass_with_revision' | 'fail',
    notes: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->completeSidang(project: $project, decidedBy: $admin->id, result: "${result}", notes: "${notes}");`,
    ]);
}

function defenseRow(
    page: Page,
    studentName: string,
    typeLabel: string | RegExp,
) {
    return page
        .locator('tr')
        .filter({ hasText: studentName })
        .filter({ hasText: typeLabel })
        .first();
}

async function sidangAssignmentRow(
    page: Page,
    studentName: string,
): Promise<ReturnType<typeof defenseRow>> {
    await page.goto('/dosen/seminar-proposal');
    await page
        .getByRole('button', { name: 'Ujian Akhir', exact: true })
        .click();
    await page.getByPlaceholder(/cari nama/i).fill(studentName);

    return defenseRow(page, studentName, /Sidang(?:\s+\w+)*\s*#1/);
}

async function openThreadList(
    page: Page,
    studentName: string,
    filterLabel: 'Bimbingan' | 'Ujian Akhir',
    threadLabel: 'Bimbingan' | 'Sidang',
): Promise<Locator> {
    const redirect = page.waitForURL(/\/dosen\/pesan(?:\?.*)?$/);
    await page.goto('/dosen/pesan-bimbingan');
    await redirect;

    await page.getByRole('button', { name: 'Grup', exact: true }).click();
    const searchInput = page.getByPlaceholder('Cari grup...');
    await searchInput.fill(studentName);
    await searchInput.locator('..').locator('..').getByRole('button').click();
    await page.getByRole('button', { name: filterLabel, exact: true }).click();

    return page
        .locator('button')
        .filter({ hasText: studentName })
        .filter({ hasText: threadLabel })
        .first();
}

async function sidangThreadRow(
    page: Page,
    studentName: string,
): Promise<Locator> {
    return openThreadList(page, studentName, 'Ujian Akhir', 'Sidang');
}

async function bimbinganThreadRow(
    page: Page,
    studentName: string,
): Promise<Locator> {
    return openThreadList(page, studentName, 'Bimbingan', 'Bimbingan');
}

test.describe('Admin sidang workflow', () => {
    test('admin assigns supervisors after sempro pass and keeps thesis chat continuity intact', async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.semproPassedWithoutSupervisors.studentKey,
            ),
        });
        const primarySupervisorContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const secondarySupervisorContext = await browser.newContext({
            storageState: authStatePath('dosen6'),
        });

        assignSupervisorsForStudent(
            'siti@sita.test',
            'dosen2@sita.test',
            'dosen6@sita.test',
            'Pembimbing ditetapkan setelah sempro lulus.',
        );

        try {
            const studentPage = await studentContext.newPage();
            const primarySupervisorPage =
                await primarySupervisorContext.newPage();
            const secondarySupervisorPage =
                await secondarySupervisorContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Dosen Pembimbing').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Ratna Kusuma, M.Kom.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Siska Maharani, M.Kom.').first(),
            ).toBeVisible();

            await studentPage.goto('/pesan');
            await studentPage
                .getByRole('button', { name: 'Bimbingan' })
                .click();
            await expect(
                studentPage.getByText('Dr. Ratna Kusuma, M.Kom.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Siska Maharani, M.Kom.').first(),
            ).toBeVisible();
            await expect(studentPage.getByText('Sempro').first()).toBeVisible();

            const primaryThread = await bimbinganThreadRow(
                primarySupervisorPage,
                thesisScenarios.semproPassedWithoutSupervisors.studentName,
            );
            await expect(primaryThread).toBeVisible();

            const secondaryThread = await bimbinganThreadRow(
                secondarySupervisorPage,
                thesisScenarios.semproPassedWithoutSupervisors.studentName,
            );
            await expect(secondaryThread).toBeVisible();
        } finally {
            await studentContext.close();
            await primarySupervisorContext.close();
            await secondarySupervisorContext.close();
        }
    });

    test('admin schedules sidang with both supervisors plus one extra examiner', async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const examinerContext = await browser.newContext({
            storageState: authStatePath('dosen6'),
        });

        const scheduledFor = toDateTimeLocalString(
            new Date(Date.now() + 9 * 24 * 60 * 60 * 1000),
        );
        scheduleSidangForStudent(
            'mahasiswa@sita.test',
            scheduledFor,
            'Ruang Sidang Playwright A',
            ['dosen@sita.test', 'dosen5@sita.test', 'dosen6@sita.test'],
            'Sidang dijadwalkan dari Playwright untuk memverifikasi panel dan thread penguji.',
        );

        try {
            const studentPage = await studentContext.newPage();
            const examinerPage = await examinerContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Ruang Sidang Playwright A').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText(scheduledDateTimeLabel(scheduledFor), {
                    exact: true,
                }),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Budi Santoso, M.Kom.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Fajar Nugraha, M.Kom.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Siska Maharani, M.Kom.').first(),
            ).toBeVisible();

            await studentPage.goto('/pesan');

            const examinerRow = await sidangAssignmentRow(
                examinerPage,
                thesisScenarios.activeResearch.studentName,
            );
            await expect(examinerRow).toBeVisible();

            const examinerThread = await sidangThreadRow(
                examinerPage,
                thesisScenarios.activeResearch.studentName,
            );
            await expect(examinerThread).toBeVisible();
        } finally {
            await studentContext.close();
            await examinerContext.close();
        }
    });

    test('admin updates the sidang panel and the sidang thread removes old participants', async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.sidangScheduled.studentKey,
            ),
        });
        const removedExaminerContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const newExaminerContext = await browser.newContext({
            storageState: authStatePath('dosen5'),
        });
        const scheduledFor = toDateTimeLocalString(
            new Date(Date.now() + 12 * 24 * 60 * 60 * 1000),
        );
        scheduleSidangForStudent(
            'putra@sita.test',
            scheduledFor,
            'Ruang Sidang Playwright B',
            ['dosen4@sita.test', 'dosen3@sita.test', 'dosen5@sita.test'],
            'Panel sidang diperbarui untuk mengganti penguji eksternal.',
        );

        try {
            const studentPage = await studentContext.newPage();
            const removedExaminerPage = await removedExaminerContext.newPage();
            const newExaminerPage = await newExaminerContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Ruang Sidang Playwright B').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText(scheduledDateTimeLabel(scheduledFor), {
                    exact: true,
                }),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Prof. Ahmad Hidayat, Ph.D.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Dewi Lestari, M.T.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Fajar Nugraha, M.Kom.').first(),
            ).toBeVisible();

            const removedExaminerRow = await sidangAssignmentRow(
                removedExaminerPage,
                thesisScenarios.sidangScheduled.studentName,
            );
            await expect(removedExaminerRow).toHaveCount(0);

            const newExaminerRow = await sidangAssignmentRow(
                newExaminerPage,
                thesisScenarios.sidangScheduled.studentName,
            );
            await expect(newExaminerRow).toBeVisible();

            const removedExaminerThread = await sidangThreadRow(
                removedExaminerPage,
                thesisScenarios.sidangScheduled.studentName,
            );
            await expect(removedExaminerThread).toHaveCount(0);

            const newExaminerThread = await sidangThreadRow(
                newExaminerPage,
                thesisScenarios.sidangScheduled.studentName,
            );
            await expect(newExaminerThread).toBeVisible();
        } finally {
            await studentContext.close();
            await removedExaminerContext.close();
            await newExaminerContext.close();
        }
    });

    test('admin records sidang failure and retry, then closes the project after a passing attempt', async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.sidangScheduled.studentKey,
            ),
        });

        scheduleSidangForStudent(
            'putra@sita.test',
            toDateTimeLocalString(
                new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
            ),
            'Ruang Sidang Retry Awal',
            ['dosen4@sita.test', 'dosen3@sita.test', 'dosen2@sita.test'],
            'Reset sidang awal sebelum skenario gagal dan ulang.',
        );

        submitSidangDecisionForStudent(
            'putra@sita.test',
            'dosen4@sita.test',
            'fail',
            58,
            'Sidang pertama belum memenuhi standar kontribusi penelitian.',
        );
        submitSidangDecisionForStudent(
            'putra@sita.test',
            'dosen3@sita.test',
            'fail',
            60,
            'Analisis hasil perlu diulang dengan justifikasi yang lebih kuat.',
        );
        submitSidangDecisionForStudent(
            'putra@sita.test',
            'dosen2@sita.test',
            'fail',
            57,
            'Presentasi sidang pertama belum layak diluluskan.',
        );
        completeSidangForStudent(
            'putra@sita.test',
            'fail',
            'Sidang pertama tidak lulus dan perlu dijadwalkan ulang.',
        );

        scheduleSidangForStudent(
            'putra@sita.test',
            toDateTimeLocalString(
                new Date(Date.now() + 16 * 24 * 60 * 60 * 1000),
            ),
            'Ruang Sidang Retry Final',
            ['dosen4@sita.test', 'dosen3@sita.test', 'dosen2@sita.test'],
            'Sidang ulang dijadwalkan setelah perbaikan final.',
        );

        submitSidangDecisionForStudent(
            'putra@sita.test',
            'dosen4@sita.test',
            'pass',
            84,
            'Sidang ulang menunjukkan perbaikan yang memadai.',
        );
        submitSidangDecisionForStudent(
            'putra@sita.test',
            'dosen3@sita.test',
            'pass',
            86,
            'Kontribusi penelitian sudah layak diluluskan.',
        );
        submitSidangDecisionForStudent(
            'putra@sita.test',
            'dosen2@sita.test',
            'pass',
            85,
            'Sidang ulang memenuhi standar kelulusan akhir.',
        );
        completeSidangForStudent(
            'putra@sita.test',
            'pass',
            'Sidang ulang dinyatakan lulus dan proyek ditutup.',
        );

        const stateOutput = execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                '--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "putra@sita.test"))->latest("started_at")->firstOrFail(); $defense = $project->sidangDefenses()->where("attempt_no", 2)->firstOrFail(); echo json_encode(["project_state" => $project->state, "defense_type" => $defense->type, "defense_attempt" => $defense->attempt_no, "defense_status" => $defense->status, "defense_result" => $defense->result, "active_supervisor_assignments" => $project->activeSupervisorAssignments()->count()]);',
            ],
            {
                cwd: repositoryRoot,
                env: playwrightEnvironment(),
                encoding: 'utf8',
            },
        );
        const persistedState = JSON.parse(stateOutput) as {
            project_state: string;
            defense_type: string;
            defense_attempt: number;
            defense_status: string;
            defense_result: string;
            active_supervisor_assignments: number;
        };

        if (persistedState.project_state !== 'completed') {
            throw new Error(
                `Expected completed project, got ${persistedState.project_state}`,
            );
        }
        if (
            persistedState.defense_type !== 'sidang' ||
            persistedState.defense_attempt !== 2 ||
            persistedState.defense_status !== 'completed' ||
            persistedState.defense_result !== 'pass'
        ) {
            throw new Error(
                `Expected passing completed sidang attempt 2, got ${JSON.stringify(persistedState)}`,
            );
        }
        if (persistedState.active_supervisor_assignments !== 0) {
            throw new Error(
                `Expected zero active supervisor assignments, got ${persistedState.active_supervisor_assignments}`,
            );
        }
        try {
            const studentPage = await studentContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Selesai').first(),
            ).toBeVisible();

            const sidangHistory = studentPage
                .getByRole('heading', { name: /Riwayat Sidang Skripsi/i })
                .locator('xpath=ancestor::section[1]');
            const completedAttempt = sidangHistory
                .locator('tr')
                .filter({ hasText: /sidang\s+skripsi\s*#?\s*2/i });
            await expect(completedAttempt).toBeVisible();
            await expect(completedAttempt.getByText('Selesai')).toBeVisible();
            await expect(completedAttempt.getByText('Lulus')).toBeVisible();

            const activeSupervisorRegion = studentPage
                .getByText('Dosen Pembimbing', { exact: true })
                .locator('xpath=../..');
            await expect(activeSupervisorRegion).toBeVisible();
            await expect(activeSupervisorRegion.getByRole('link')).toHaveCount(
                0,
            );
        } finally {
            await studentContext.close();
        }
    });
});
