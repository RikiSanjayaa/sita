import { expect, test } from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { attachDocumentStates } from '../support/document-detail';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

const topic = 'Interaksi detail sesi bimbingan #157';

for (const role of ['mahasiswa', 'dosen'] as const) {
    test(`${role} opens the same editable session from the row, keyboard and eye repeatedly`, async ({
        browser,
    }, info) => {
        seedPlaywrightScenarios();
        runArtisan([
            'tinker',
            `--execute=$student = App\\Models\\User::where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); $lecturer = App\\Models\\User::where('email', 'dosen@sita.test')->firstOrFail(); App\\Models\\MentorshipSchedule::where('topic', '${topic}')->where('student_user_id', $student->id)->delete(); App\\Models\\MentorshipSchedule::create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $student->id, 'topic' => '${topic}', 'status' => 'completed', 'requested_for' => now()->subHour(), 'scheduled_for' => now()->subHour(), 'location' => 'Ruang Demo', 'lecturer_note' => 'Jadwal dikonfirmasi.']);`,
        ]);
        const context = await browser.newContext({
            storageState: authStatePath(
                role === 'dosen'
                    ? 'dosen1'
                    : thesisScenarios.twoSupervisors.studentKey,
            ),
            viewport: { width: 1280, height: 844 },
            reducedMotion: 'no-preference',
        });
        try {
            const page = await context.newPage();
            await page.goto(`/${role}/jadwal-bimbingan`);
            const row = page.getByRole('row').filter({ hasText: topic });
            await expect(row).toHaveCount(1);
            await expect(
                page.getByRole('button', { name: /^Kelola jadwal / }),
            ).toHaveCount(0);
            for (const activation of [
                'row',
                'Enter',
                ' ',
                'eye',
                'eye',
            ] as const) {
                if (activation === 'eye') {
                    const eye = row.getByRole('button', {
                        name: `Detail sesi ${topic}`,
                        exact: true,
                    });
                    await eye.hover();
                    await expect(page.getByRole('tooltip')).toBeVisible();
                    await eye.click();
                } else if (activation === 'row') {
                    await row
                        .getByRole('cell')
                        .filter({ hasText: topic })
                        .first()
                        .click();
                } else {
                    await row.focus();
                    await row.press(activation);
                }
                const drawer = page.getByRole('dialog');
                await expect(
                    drawer.getByRole('heading', { name: topic, exact: true }),
                ).toBeVisible();
                await expect
                    .poll(async () => {
                        const box = await drawer.boundingBox();
                        return box ? Math.round(box.x + box.width) : -1;
                    })
                    .toBe(1280);
                const notes = drawer.getByRole('textbox', {
                    name: 'Catatan sesi Anda',
                });
                await notes.fill(`Catatan ${role} melalui ${activation}`);
                await expect(notes).toHaveValue(
                    `Catatan ${role} melalui ${activation}`,
                );
                await expect(page.getByRole('dialog')).toHaveCount(1);
                await drawer
                    .getByRole('button', { name: 'Close', exact: true })
                    .click();
                await expect(
                    page.locator('[data-slot="sheet-content"]'),
                ).toHaveCount(0);
            }
            await attachDocumentStates(
                page,
                info,
                `${role}-interactive-schedule`,
            );
            await row
                .getByRole('button', {
                    name: `Detail sesi ${topic}`,
                    exact: true,
                })
                .click();
            await attachDocumentStates(
                page,
                info,
                `${role}-interactive-session-drawer`,
            );
        } finally {
            await context.close();
            runArtisan([
                'tinker',
                `--execute=App\\Models\\MentorshipSchedule::where('topic', '${topic}')->delete();`,
            ]);
        }
    });
}
