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

function defenseRow(page: Page, studentName: string, attemptLabel?: string) {
    const matchingRows = page.locator('tr').filter({ hasText: studentName });

    return attemptLabel === undefined
        ? matchingRows.first()
        : matchingRows.filter({ hasText: attemptLabel }).first();
}

async function openDefenseSheet(
    page: Page,
    studentName: string,
    attemptLabel?: string,
): Promise<void> {
    const searchInput = page.getByPlaceholder('Cari nama atau judul...');

    await expect(searchInput).toBeVisible();
    await searchInput.fill(studentName);

    const row = defenseRow(page, studentName, attemptLabel);
    const dialog = page.getByRole('dialog');

    await expect(row).toBeVisible();
    await row.click();

    try {
        await dialog.waitFor({ state: 'visible', timeout: 2_000 });
    } catch {
        await row.evaluate((element: HTMLElement) => element.click());

        try {
            await dialog.waitFor({ state: 'visible', timeout: 2_000 });
        } catch {
            await row.locator('td').first().click();

            try {
                await dialog.waitFor({ state: 'visible', timeout: 2_000 });
            } catch {
                await row.locator('td').last().click({ force: true });
                await expect(dialog).toBeVisible();
            }
        }
    }
}

async function submitDefenseDecision(
    page: Page,
    {
        studentName,
        decision,
        score,
        decisionNotes,
        revisionNotes,
        attemptLabel,
    }: {
        studentName: string;
        decision: 'pass' | 'pass_with_revision' | 'fail';
        score: string;
        decisionNotes: string;
        revisionNotes?: string;
        attemptLabel?: string;
    },
): Promise<void> {
    await page.goto('/dosen/seminar-proposal');
    await expect(page).toHaveTitle(/Sempro & Sidang/i);

    await openDefenseSheet(page, studentName, attemptLabel);
    await page.getByRole('button', { name: 'Input Keputusan Saya' }).click();

    await page
        .getByRole('button', {
            name:
                decision === 'pass'
                    ? /Setujui/i
                    : decision === 'pass_with_revision'
                      ? /Perlu Revisi/i
                      : /Tidak Lulus/i,
        })
        .click();
    await page.getByLabel(/Nilai/i).fill(score);
    await page.getByLabel('Catatan Keputusan').fill(decisionNotes);

    if (decision === 'pass_with_revision') {
        await page.getByLabel('Catatan Revisi *').fill(revisionNotes ?? '');
    }

    const decisionRequest = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            /\/dosen\/seminar-proposal\/\d+\/decision$/.test(response.url()),
    );

    await page.getByRole('button', { name: 'Submit Keputusan' }).click();
    await decisionRequest;

    const row = defenseRow(page, studentName, attemptLabel);
    await expect(row).toContainText(
        decision === 'pass'
            ? 'Disetujui'
            : decision === 'pass_with_revision'
              ? 'Perlu Revisi'
              : 'Tidak Lulus',
    );
    await expect(row).toContainText(score);
}

function finalizeSemproForStudent(
    studentEmail: string,
    result: 'pass' | 'pass_with_revision' | 'fail',
    notes: string,
    revisionDueAt?: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->finalizeSempro(project: $project, decidedBy: $admin->id, result: "${result}", notes: "${notes}", revisionDueAt: ${revisionDueAt ? `"${revisionDueAt}"` : 'null'});`,
    ]);
}

function submitSemproDecisionForStudent(
    studentEmail: string,
    lecturerEmail: string,
    decision: 'pass' | 'pass_with_revision' | 'fail',
    score: number,
    decisionNotes: string,
    revisionNotes?: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $defense = $project->semproDefenses()->latest("attempt_no")->firstOrFail(); $lecturer = App\\Models\\User::query()->where("email", "${lecturerEmail}")->firstOrFail(); app(App\\Services\\ThesisDefenseExaminerDecisionService::class)->submit($lecturer, $defense, ["decision" => "${decision}", "score" => ${score}, "decision_notes" => "${decisionNotes}", "revision_notes" => ${revisionNotes ? `"${revisionNotes}"` : 'null'}]);`,
    ]);
}

function resolveSemproRevisionForStudent(
    studentEmail: string,
    lecturerEmail: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $revision = App\\Models\\ThesisRevision::query()->where("project_id", $project->id)->whereHas("requestedBy", fn($q) => $q->where("email", "${lecturerEmail}"))->latest("id")->firstOrFail(); $lecturer = App\\Models\\User::query()->where("email", "${lecturerEmail}")->firstOrFail(); app(App\\Services\\ThesisDefenseRevisionService::class)->approveByLecturer($lecturer, $revision);`,
    ]);
}

function scheduleSemproForStudent(
    studentEmail: string,
    scheduledFor: string,
    location: string,
    examinerEmails: [string, string],
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); $examinerOne = App\\Models\\User::query()->where("email", "${examinerEmails[0]}")->firstOrFail(); $examinerTwo = App\\Models\\User::query()->where("email", "${examinerEmails[1]}")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->scheduleSempro(project: $project, scheduledBy: $admin->id, scheduledFor: "${scheduledFor}", location: "${location}", mode: "offline", examinerUserIds: [$examinerOne->id, $examinerTwo->id]);`,
    ]);
}

test.describe('Admin sempro workflow', () => {
    test('admin reschedules sempro and the sempro thread follows the new examiner set', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext({
            storageState: authStatePath('admin'),
        });
        const removedExaminerContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const addedExaminerContext = await browser.newContext({
            storageState: authStatePath('dosen5'),
        });

        const scheduledFor = toDateTimeLocalString(
            new Date(Date.now() + 7 * 24 * 60 * 60 * 1000),
        );
        const rescheduledLocation = 'Ruang Seminar Playwright';

        try {
            const adminPage = await adminContext.newPage();
            const removedExaminerPage = await removedExaminerContext.newPage();
            const addedExaminerPage = await addedExaminerContext.newPage();

            await removedExaminerPage.goto('/dosen/seminar-proposal');
            await expect(removedExaminerPage).toHaveTitle(/Sempro & Sidang/i);
            await expect(
                removedExaminerPage
                    .locator('tr')
                    .filter({
                        hasText: thesisScenarios.semproScheduled.studentName,
                    })
                    .first(),
            ).toBeVisible();

            await adminPage.goto('/admin/thesis-projects?tableSearch=Akbar');
            await expect(adminPage).toHaveURL(/\/admin\/thesis-projects/);
            await expect(adminPage).toHaveTitle(/Proyek Tugas Akhir/i);
            await adminPage
                .locator('tr')
                .filter({
                    hasText: thesisScenarios.semproScheduled.studentName,
                })
                .first()
                .getByRole('link')
                .last()
                .click();

            await expect(
                adminPage
                    .getByText(thesisScenarios.semproScheduled.studentName)
                    .first(),
            ).toBeVisible();

            runArtisan([
                'tinker',
                '--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "akbar@sita.test"))->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); $dosen1 = App\\Models\\User::query()->where("email", "dosen@sita.test")->firstOrFail(); $dosen5 = App\\Models\\User::query()->where("email", "dosen5@sita.test")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->scheduleSempro(project: $project, scheduledBy: $admin->id, scheduledFor: "' +
                    scheduledFor +
                    '", location: "' +
                    rescheduledLocation +
                    '", mode: "offline", examinerUserIds: [$dosen1->id, $dosen5->id]);',
            ]);

            await adminPage.reload();

            await removedExaminerPage.goto('/dosen/seminar-proposal');
            await expect(
                removedExaminerPage
                    .locator('tr')
                    .filter({
                        hasText: thesisScenarios.semproScheduled.studentName,
                    })
                    .first(),
            ).toHaveCount(0);

            await addedExaminerPage.goto('/dosen/seminar-proposal');
            await expect(addedExaminerPage).toHaveTitle(/Sempro & Sidang/i);
            await expect(
                addedExaminerPage
                    .locator('tr')
                    .filter({
                        hasText: thesisScenarios.semproScheduled.studentName,
                    })
                    .first(),
            ).toBeVisible();

            await addedExaminerPage.goto('/dosen/pesan-bimbingan');
            await expect(addedExaminerPage).toHaveTitle(
                /Pesan Bimbingan Dosen/i,
            );
            await expect(
                addedExaminerPage
                    .getByText(thesisScenarios.semproScheduled.studentName)
                    .first(),
            ).toBeVisible();
        } finally {
            await adminContext.close();
            await removedExaminerContext.close();
            await addedExaminerContext.close();
        }
    });

    test('two examiners submit sempro decisions and admin finalizes the result with revision', async ({
        browser,
    }) => {
        scheduleSemproForStudent(
            'akbar@sita.test',
            toDateTimeLocalString(
                new Date(Date.now() + 6 * 24 * 60 * 60 * 1000),
            ),
            'Ruang Seminar Reset Playwright',
            ['dosen@sita.test', 'dosen2@sita.test'],
        );

        const examinerOneContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });
        const examinerTwoContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.semproScheduled.studentKey,
            ),
        });

        const revisionDueAt = toDateTimeLocalString(
            new Date(Date.now() + 5 * 24 * 60 * 60 * 1000),
        );

        try {
            const examinerOnePage = await examinerOneContext.newPage();
            const studentPage = await studentContext.newPage();

            submitSemproDecisionForStudent(
                'akbar@sita.test',
                'dosen@sita.test',
                'pass_with_revision',
                84,
                'Proposal layak lanjut setelah revisi metodologi dicatat.',
                'Lengkapi justifikasi dataset dan perjelas metrik evaluasi.',
            );

            submitSemproDecisionForStudent(
                'akbar@sita.test',
                'dosen2@sita.test',
                'pass',
                88,
                'Topik sudah matang dan dapat dilanjutkan ke tahap penelitian.',
            );

            finalizeSemproForStudent(
                'akbar@sita.test',
                'pass_with_revision',
                'Sempro dinyatakan lulus dengan revisi metodologi.',
                revisionDueAt,
            );

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(studentPage).toHaveTitle(/Tugas Akhir/i);
            await expect(
                studentPage.getByText('Revisi Sempro').first(),
            ).toBeVisible();

            await examinerOnePage.goto('/dosen/seminar-proposal');
            await expect(examinerOnePage).toHaveTitle(/Sempro & Sidang/i);
            await expect(
                examinerOnePage
                    .locator('tr')
                    .filter({
                        hasText: thesisScenarios.semproScheduled.studentName,
                    })
                    .first(),
            ).toBeVisible();

            resolveSemproRevisionForStudent(
                'akbar@sita.test',
                'dosen@sita.test',
            );

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Sempro Selesai').first(),
            ).toBeVisible();
        } finally {
            await examinerOneContext.close();
            await examinerTwoContext.close();
            await studentContext.close();
        }
    });

    test('admin records sempro failure, then creates the next attempt and finalizes it as pass', async ({
        browser,
    }) => {
        const examinerOneContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });
        const examinerTwoContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(thesisScenarios.semproRetry.studentKey),
        });

        const nextAttemptSchedule = toDateTimeLocalString(
            new Date(Date.now() + 10 * 24 * 60 * 60 * 1000),
        );

        try {
            const examinerOnePage = await examinerOneContext.newPage();
            const examinerTwoPage = await examinerTwoContext.newPage();
            const studentPage = await studentContext.newPage();

            await submitDefenseDecision(examinerOnePage, {
                studentName: thesisScenarios.semproRetry.studentName,
                decision: 'fail',
                score: '56',
                decisionNotes:
                    'Proposal belum siap dipresentasikan dan analisis rubrik masih lemah.',
                attemptLabel: 'Sempro #2',
            });

            await submitDefenseDecision(examinerTwoPage, {
                studentName: thesisScenarios.semproRetry.studentName,
                decision: 'fail',
                score: '58',
                decisionNotes:
                    'Instrumen evaluasi dan skenario validasi perlu disusun ulang.',
                attemptLabel: 'Sempro #2',
            });

            finalizeSemproForStudent(
                'bagas@sita.test',
                'fail',
                'Sempro dinyatakan tidak lulus dan perlu dijadwalkan ulang.',
            );

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(studentPage).toHaveTitle(/Tugas Akhir/i);
            await expect(
                studentPage.getByText('Sempro Tidak Lulus').first(),
            ).toBeVisible();

            scheduleSemproForStudent(
                'bagas@sita.test',
                nextAttemptSchedule,
                'Ruang Seminar Retry Playwright',
                ['dosen@sita.test', 'dosen2@sita.test'],
            );

            await examinerOnePage.goto('/dosen/seminar-proposal');
            await expect(
                defenseRow(
                    examinerOnePage,
                    thesisScenarios.semproRetry.studentName,
                    'Sempro #3',
                ),
            ).toBeVisible();

            await submitDefenseDecision(examinerOnePage, {
                studentName: thesisScenarios.semproRetry.studentName,
                decision: 'pass',
                score: '85',
                decisionNotes:
                    'Attempt baru sudah siap dan dapat dilanjutkan ke penelitian.',
                attemptLabel: 'Sempro #3',
            });

            await submitDefenseDecision(examinerTwoPage, {
                studentName: thesisScenarios.semproRetry.studentName,
                decision: 'pass',
                score: '87',
                decisionNotes:
                    'Perbaikan attempt baru memadai untuk lolos sempro.',
                attemptLabel: 'Sempro #3',
            });

            finalizeSemproForStudent(
                'bagas@sita.test',
                'pass',
                'Attempt sempro terbaru dinyatakan lulus.',
            );

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Sempro Selesai').first(),
            ).toBeVisible();
        } finally {
            await examinerOneContext.close();
            await examinerTwoContext.close();
            await studentContext.close();
        }
    });
});
