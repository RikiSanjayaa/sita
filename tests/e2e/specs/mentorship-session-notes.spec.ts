import { expect, test, type Page } from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { attachDocumentStates } from '../support/document-detail';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

const topic = 'Evaluasi hasil sesi bimbingan #150';
const otherTopic = 'Sesi terpisah tanpa catatan #150';
const studentNote =
    'Hasil bimbingan mahasiswa:\n' +
    'Pembahasan data dan metode. '.repeat(35) +
    '\nRencana: lengkapi tabel dan sumber data.';
const lecturerNote =
    'Masukan dosen:\nPerjelas metode analisis dan satuan pada tabel.\n' +
    'ReferensiTanpaSpasi'.repeat(25) +
    '\nAkhir catatan dosen.';

async function openSession(page: Page): Promise<number> {
    await page
        .getByRole('button', { name: `Detail sesi ${topic}`, exact: true })
        .click();
    const notes = page.getByRole('dialog').locator('[data-session-notes]');
    await expect(notes).toBeVisible();
    return Number(await notes.getAttribute('data-session-notes'));
}

async function saveNote(page: Page, body: string): Promise<void> {
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('textbox', { name: 'Catatan sesi Anda' }).fill(body);
    const response = page.waitForResponse(
        (response) =>
            response.request().method() === 'PATCH' &&
            response.url().endsWith('/notes'),
    );
    await dialog
        .getByRole('button', { name: 'Simpan catatan sesi', exact: true })
        .click();
    expect([200, 302, 303]).toContain((await response).status());
    await expect(
        dialog.locator('article').getByText(body, { exact: true }),
    ).toBeVisible();
}

test('participants write independent session notes and scoped monitoring reads the same persisted content', async ({
    browser,
}, info) => {
    test.setTimeout(240_000);
    seedPlaywrightScenarios();
    runArtisan([
        'tinker',
        `--execute=$student = App\\Models\\User::where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); $lecturer = App\\Models\\User::where('email', 'dosen@sita.test')->firstOrFail(); App\\Models\\MentorshipSchedule::where('student_user_id', $student->id)->whereIn('topic', ['${topic}', '${otherTopic}'])->delete(); foreach (['${topic}', '${otherTopic}'] as $topic) { App\\Models\\MentorshipSchedule::create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $student->id, 'topic' => $topic, 'status' => 'completed', 'requested_for' => now()->subDay(), 'scheduled_for' => now()->subDay(), 'location' => 'Ruang Bimbingan Demo', 'student_note' => 'Bawa naskah untuk diskusi.', 'lecturer_note' => 'Jadwal dikonfirmasi.']); }`,
    ]);
    const studentContext = await browser.newContext({
        storageState: authStatePath(thesisScenarios.twoSupervisors.studentKey),
    });
    const lecturerContext = await browser.newContext({
        storageState: authStatePath('dosen1'),
    });
    const kaprodiContext = await browser.newContext({
        storageState: authStatePath('kaprodi'),
    });
    const adminContext = await browser.newContext({
        storageState: authStatePath('admin'),
    });
    const outsiderContext = await browser.newContext({
        storageState: authStatePath('dosen2'),
    });
    const outsiderStudentContext = await browser.newContext({
        storageState: authStatePath('mahasiswa'),
    });
    try {
        const studentPage = await studentContext.newPage();
        const lecturerPage = await lecturerContext.newPage();
        await studentPage.goto('/mahasiswa/jadwal-bimbingan');
        const scheduleId = await openSession(studentPage);
        await expect(
            studentPage
                .getByRole('dialog')
                .getByText('Belum ada catatan sesi bimbingan.', {
                    exact: true,
                }),
        ).toBeVisible();
        await attachDocumentStates(studentPage, info, 'session-notes-empty');
        await studentPage
            .getByRole('dialog')
            .getByRole('textbox', { name: 'Catatan sesi Anda' })
            .press('Tab');
        await expect(
            studentPage
                .getByRole('dialog')
                .getByRole('button', { name: 'Simpan catatan sesi' }),
        ).toBeFocused();
        await studentPage
            .getByRole('dialog')
            .getByRole('button', { name: 'Simpan catatan sesi' })
            .click();
        await expect(
            studentPage.getByRole('dialog').getByRole('alert'),
        ).toContainText('Isi catatan hasil bimbingan');
        await attachDocumentStates(studentPage, info, 'session-notes-error');
        await saveNote(studentPage, studentNote);
        await studentPage.reload();
        await openSession(studentPage);
        await expect(
            studentPage
                .getByRole('dialog')
                .getByRole('textbox', { name: 'Catatan sesi Anda' }),
        ).toHaveValue(studentNote);

        await lecturerPage.goto('/dosen/jadwal-bimbingan');
        await openSession(lecturerPage);
        await expect(
            lecturerPage
                .getByRole('dialog')
                .getByText(studentNote, { exact: true }),
        ).toBeVisible();
        await saveNote(lecturerPage, lecturerNote);
        await lecturerPage.reload();
        await openSession(lecturerPage);
        await expect(
            lecturerPage
                .getByRole('dialog')
                .getByRole('textbox', { name: 'Catatan sesi Anda' }),
        ).toHaveValue(lecturerNote);
        await expect(
            lecturerPage
                .getByRole('dialog')
                .getByText(studentNote, { exact: true }),
        ).toBeVisible();
        await attachDocumentStates(lecturerPage, info, 'dosen-session-notes');
        await studentPage.reload();
        await openSession(studentPage);
        await expect(
            studentPage
                .getByRole('dialog')
                .getByText(lecturerNote, { exact: true }),
        ).toBeVisible();
        await attachDocumentStates(
            studentPage,
            info,
            'mahasiswa-session-notes',
        );

        const initialProps = await studentPage.evaluate(
            () =>
                JSON.parse(
                    document.querySelector('#app')!.getAttribute('data-page')!,
                ).props,
        );
        const studentId = initialProps.auth.user.id as number;
        const kaprodiPage = await kaprodiContext.newPage();
        await kaprodiPage.goto(`/profil/${studentId}`);
        const monitor = kaprodiPage.locator(
            `[data-monitoring-session="${scheduleId}"]`,
        );
        await expect(
            monitor.getByText(studentNote, { exact: true }),
        ).toBeVisible();
        await expect(
            monitor.getByText(lecturerNote, { exact: true }),
        ).toBeVisible();
        await expect(monitor.getByRole('textbox')).toHaveCount(0);
        await kaprodiPage.reload();
        await expect(
            monitor.getByText(lecturerNote, { exact: true }),
        ).toBeVisible();
        await attachDocumentStates(kaprodiPage, info, 'kaprodi-session-notes');

        const adminPage = await adminContext.newPage();
        await adminPage.goto('/admin/thesis-projects');
        const row = adminPage
            .getByRole('row')
            .filter({ hasText: thesisScenarios.twoSupervisors.studentName });
        await row.getByRole('link', { name: 'View', exact: true }).click();
        await adminPage
            .getByRole('tab', { name: 'Catatan', exact: true })
            .click();
        const adminMonitor = adminPage.locator(
            `[data-monitoring-session="${scheduleId}"]`,
        );
        await expect(
            adminMonitor.getByText(studentNote, { exact: true }),
        ).toBeVisible();
        await expect(
            adminMonitor.getByText(lecturerNote, { exact: true }),
        ).toBeVisible();
        await expect(adminMonitor.getByRole('textbox')).toHaveCount(0);
        await attachDocumentStates(adminPage, info, 'admin-session-notes');

        const outsiderPage = await outsiderContext.newPage();
        await outsiderPage.goto('/dosen/jadwal-bimbingan');
        await expect(
            outsiderPage.getByRole('button', {
                name: `Detail sesi ${topic}`,
                exact: true,
            }),
        ).toHaveCount(0);
        await outsiderPage.goto(`/profil/${studentId}`);
        await expect(
            outsiderPage.locator('[data-monitoring-session]'),
        ).toHaveCount(0);
        const outsiderMutation = await outsiderContext.request.patch(
            `/dosen/jadwal-bimbingan/${scheduleId}/notes`,
            {
                data: { body: 'Tidak berhak' },
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (await outsiderPage
                        .locator('meta[name="csrf-token"]')
                        .getAttribute('content'))!,
                },
            },
        );
        expect(outsiderMutation.status()).toBe(403);
        const foreignStudentPage = await outsiderStudentContext.newPage();
        await foreignStudentPage.goto(`/profil/${studentId}`);
        await expect(
            foreignStudentPage.locator('[data-monitoring-session]'),
        ).toHaveCount(0);
        const studentMutation = await outsiderStudentContext.request.patch(
            `/mahasiswa/jadwal-bimbingan/${scheduleId}/notes`,
            {
                data: { body: 'Tidak berhak' },
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (await foreignStudentPage
                        .locator('meta[name="csrf-token"]')
                        .getAttribute('content'))!,
                },
            },
        );
        expect(studentMutation.status()).toBe(403);

        await studentPage
            .getByRole('dialog')
            .getByRole('button', { name: 'Close', exact: true })
            .click();
        await studentPage
            .getByRole('button', {
                name: `Detail sesi ${otherTopic}`,
                exact: true,
            })
            .click();
        await expect(
            studentPage
                .getByRole('dialog')
                .getByText('Belum ada catatan sesi bimbingan.', {
                    exact: true,
                }),
        ).toBeVisible();
        await expect(
            studentPage
                .getByRole('dialog')
                .getByRole('textbox', { name: 'Catatan sesi Anda' }),
        ).toHaveValue('');

        // Establish an archived fixture; the assertions below exercise its read-only UI.
        runArtisan([
            'tinker',
            `--execute=$project = App\\Models\\ThesisProject::where('student_user_id', ${studentId})->current()->firstOrFail(); $project->update(['state' => 'completed', 'phase' => 'completed', 'completed_at' => now()->subDays(40)]); app(App\\Services\\ThesisArchiveService::class)->archive($project);`,
        ]);
        for (const [page, name] of [
            [studentPage, 'mahasiswa'],
            [lecturerPage, 'dosen'],
        ] as const) {
            await page.reload();
            const archived = page.locator(
                `[data-archived-session="${scheduleId}"]`,
            );
            await expect(
                archived.getByText(studentNote, { exact: true }),
            ).toBeVisible();
            await expect(
                archived.getByText(lecturerNote, { exact: true }),
            ).toBeVisible();
            await expect(archived.getByRole('textbox')).toHaveCount(0);
            await expect(
                archived.getByRole('button', { name: 'Simpan catatan sesi' }),
            ).toHaveCount(0);
            await attachDocumentStates(
                page,
                info,
                `${name}-session-notes-archived`,
            );
        }
        await kaprodiPage.reload();
        await expect(
            monitor.getByText(studentNote, { exact: true }),
        ).toBeVisible();
        await expect(
            monitor.getByText(lecturerNote, { exact: true }),
        ).toBeVisible();
        await adminPage.reload();
        await adminPage
            .getByRole('tab', { name: 'Catatan', exact: true })
            .click();
        await expect(
            adminMonitor.getByText(studentNote, { exact: true }),
        ).toBeVisible();
        await expect(
            adminMonitor.getByText(lecturerNote, { exact: true }),
        ).toBeVisible();
    } finally {
        await Promise.all(
            [
                studentContext,
                lecturerContext,
                kaprodiContext,
                adminContext,
                outsiderContext,
                outsiderStudentContext,
            ].map((context) => context.close()),
        );
        runArtisan([
            'tinker',
            `--execute=App\\Models\\MentorshipSchedule::whereIn('topic', ['${topic}', '${otherTopic}'])->delete();`,
        ]);
        seedPlaywrightScenarios();
    }
});
