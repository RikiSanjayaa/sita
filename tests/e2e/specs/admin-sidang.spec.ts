import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

function toDateTimeLocalString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
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

function defenseRow(page: Page, studentName: string, typeLabel: string) {
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
    await expect(page).toHaveTitle(/Sempro & Sidang/i);
    await page.getByPlaceholder('Cari nama atau judul...').fill(studentName);

    return defenseRow(page, studentName, 'Sidang #1');
}

async function sidangThreadRow(
    page: Page,
    studentName: string,
): Promise<ReturnType<Page['locator']>> {
    await page.goto('/dosen/pesan-bimbingan');
    await expect(page).toHaveTitle(/Pesan Bimbingan/i);
    await page.getByPlaceholder('Cari mahasiswa...').fill(studentName);

    return page
        .locator('button')
        .filter({ hasText: studentName })
        .filter({ hasText: 'Sidang' })
        .first();
}

async function bimbinganThreadRow(
    page: Page,
    studentName: string,
): Promise<ReturnType<Page['locator']>> {
    await page.goto('/dosen/pesan-bimbingan');
    await expect(page).toHaveTitle(/Pesan Bimbingan/i);
    await page.getByPlaceholder('Cari mahasiswa...').fill(studentName);

    return page
        .locator('button')
        .filter({ hasText: studentName })
        .filter({ hasText: 'Bimbingan' })
        .first();
}

test.describe('Admin sidang workflow', () => {
    test.use({ storageState: authStatePath('admin') });

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
            await expect(studentPage).toHaveTitle(/Tugas Akhir/i);
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
            await expect(studentPage).toHaveTitle(/Pesan/i);
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

        scheduleSidangForStudent(
            'mahasiswa@sita.test',
            toDateTimeLocalString(
                new Date(Date.now() + 9 * 24 * 60 * 60 * 1000),
            ),
            'Ruang Sidang Playwright A',
            ['dosen@sita.test', 'dosen5@sita.test', 'dosen6@sita.test'],
            'Sidang dijadwalkan dari Playwright untuk memverifikasi panel dan thread penguji.',
        );

        try {
            const studentPage = await studentContext.newPage();
            const examinerPage = await examinerContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(studentPage).toHaveTitle(/Tugas Akhir/i);
            await expect(
                studentPage.getByText('Sidang Dijadwalkan').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Penguji Sidang').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Siska Maharani, M.Kom.').first(),
            ).toBeVisible();

            await studentPage.goto('/pesan');
            await expect(studentPage).toHaveTitle(/Pesan/i);
            await expect(studentPage.getByText('Sempro').first()).toBeVisible();
            await expect(studentPage.getByText('Sidang').first()).toBeVisible();

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

        scheduleSidangForStudent(
            'putra@sita.test',
            toDateTimeLocalString(
                new Date(Date.now() + 12 * 24 * 60 * 60 * 1000),
            ),
            'Ruang Sidang Playwright B',
            ['dosen4@sita.test', 'dosen3@sita.test', 'dosen5@sita.test'],
            'Panel sidang diperbarui untuk mengganti penguji eksternal.',
        );

        try {
            const studentPage = await studentContext.newPage();
            const removedExaminerPage = await removedExaminerContext.newPage();
            const newExaminerPage = await newExaminerContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(studentPage).toHaveTitle(/Tugas Akhir/i);
            await expect(
                studentPage.getByText('Penguji Sidang').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Penguji Sidang 1').first(),
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

        try {
            const studentPage = await studentContext.newPage();

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(studentPage).toHaveTitle(/Tugas Akhir/i);
            await expect(
                studentPage.getByText('Selesai').first(),
            ).toBeVisible();
            await expect(
                studentPage
                    .getByText('Tahap sidang skripsi telah selesai.')
                    .first(),
            ).toBeVisible();
            await expect(
                studentPage
                    .getByText('Belum ada dosen pembimbing aktif.')
                    .first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Riwayat Sidang Skripsi').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Sidang #2').first(),
            ).toBeVisible();
        } finally {
            await studentContext.close();
        }
    });
});
