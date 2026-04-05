import { expect, test } from '@playwright/test';

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

    test.fixme('two examiners submit sempro decisions and admin finalizes the result with revision', async () => {
        // Use the scheduled sempro scenario.
        // Dosen 1 submits pass_with_revision, dosen 2 submits pass.
        // Admin records the final sempro result as pass_with_revision.
        // Verify mahasiswa sees Revisi Sempro on /mahasiswa/tugas-akhir.
        // Verify the requesting examiner can resolve the revision from /dosen/seminar-proposal.
    });

    test.fixme('admin records sempro failure, then creates the next attempt and finalizes it as pass', async () => {
        // Use the Bagas multi-attempt scenario.
        // Both examiners submit fail on the active scheduled attempt.
        // Admin records Tidak Lulus.
        // Admin schedules the next sempro attempt.
        // Examiners submit passing decisions on the new attempt.
        // Admin finalizes the new attempt as Lulus and verifies the project moves to research.
    });
});
