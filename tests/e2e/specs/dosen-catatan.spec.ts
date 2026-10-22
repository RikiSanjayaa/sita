import { expect, test, type Page } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Lecturer notes on a thesis project (issue #13). dosen1 and dosen2 supervise
// the dedicated "twoSupervisors" student; each note is either internal
// (lecturers only) or visible to the student on Tugas Akhir.
const scenario = thesisScenarios.twoSupervisors;

async function openNotesSheet(page: Page): Promise<void> {
    await page.goto(
        `/dosen/mahasiswa-bimbingan?activeSearch=${encodeURIComponent(scenario.studentName)}`,
    );
    await page.waitForLoadState('networkidle');

    const row = page
        .getByRole('row')
        .filter({ hasText: scenario.studentName })
        .first();
    await expect(row).toBeVisible();

    await expect(async () => {
        await row.getByRole('button', { name: /^Catatan/ }).click();
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 15_000 });
}

async function addNote(
    page: Page,
    visibilityLabel: 'Internal dosen' | 'Untuk mahasiswa',
    body: string,
): Promise<void> {
    const dialog = page.getByRole('dialog');

    await dialog.getByRole('radio', { name: visibilityLabel }).click();
    await dialog.getByLabel('Catatan', { exact: true }).fill(body);
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                /\/dosen\/tugas-akhir\/\d+\/catatan$/.test(response.url()),
        ),
        dialog.getByRole('button', { name: 'Simpan catatan' }).click(),
    ]);
    await expect(dialog.getByText(body)).toBeVisible();
}

test.describe('Catatan dosen pada tugas akhir', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('supervisors write internal and student-visible notes; the student only sees the student note', async ({
        browser,
    }) => {
        const stamp = Date.now();
        const internalNote = `Catatan internal pembimbing ${stamp}`;
        const studentNote = `Catatan untuk mahasiswa ${stamp}`;
        const editedStudentNote = `${studentNote} (diperbarui)`;

        const supervisorOneContext = await browser.newContext({
            storageState: authStatePath(scenario.supervisorKeys[0]),
        });
        const supervisorTwoContext = await browser.newContext({
            storageState: authStatePath(scenario.supervisorKeys[1]),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(scenario.studentKey),
        });

        try {
            const supervisorOnePage = await supervisorOneContext.newPage();
            const supervisorTwoPage = await supervisorTwoContext.newPage();
            const studentPage = await studentContext.newPage();

            await openNotesSheet(supervisorOnePage);
            await addNote(supervisorOnePage, 'Internal dosen', internalNote);
            await addNote(supervisorOnePage, 'Untuk mahasiswa', studentNote);

            // The student sees the student-visible note, never the internal one.
            await studentPage.goto('/mahasiswa/tugas-akhir');
            await studentPage.waitForLoadState('networkidle');
            await expect(
                studentPage.getByRole('region', { name: 'Catatan Dosen' }),
            ).toBeVisible();
            await expect(studentPage.getByText(studentNote)).toBeVisible();
            await expect(
                studentPage.getByText(scenario.supervisorNames[0]).first(),
            ).toBeVisible();
            await expect(studentPage.getByText(internalNote)).toHaveCount(0);

            // The other supervisor reads both notes but cannot change them.
            await openNotesSheet(supervisorTwoPage);
            const sheetTwo = supervisorTwoPage.getByRole('dialog');
            await expect(sheetTwo.getByText(internalNote)).toBeVisible();
            await expect(sheetTwo.getByText(studentNote)).toBeVisible();
            await expect(
                sheetTwo.getByText('Internal dosen').first(),
            ).toBeVisible();
            await expect(
                sheetTwo.getByRole('button', { name: 'Edit' }),
            ).toHaveCount(0);
            await expect(
                sheetTwo.getByRole('button', { name: 'Hapus' }),
            ).toHaveCount(0);

            // The author edits the student note, and the student sees the new text.
            const sheetOne = supervisorOnePage.getByRole('dialog');
            const studentNoteItem = sheetOne
                .getByTestId('thesis-note')
                .filter({ hasText: studentNote });
            await studentNoteItem.getByRole('button', { name: 'Edit' }).click();
            await studentNoteItem
                .getByLabel('Catatan', { exact: true })
                .fill(editedStudentNote);
            await Promise.all([
                supervisorOnePage.waitForResponse(
                    (response) =>
                        response.request().method() === 'PATCH' &&
                        /\/dosen\/catatan\/\d+$/.test(response.url()),
                ),
                studentNoteItem
                    .getByRole('button', { name: 'Simpan perubahan' })
                    .click(),
            ]);
            await expect(sheetOne.getByText(editedStudentNote)).toBeVisible();

            await studentPage.reload();
            await expect(
                studentPage.getByText(editedStudentNote),
            ).toBeVisible();

            // The author deletes both notes; the student section disappears.
            for (const body of [editedStudentNote, internalNote]) {
                const item = sheetOne
                    .getByTestId('thesis-note')
                    .filter({ hasText: body });
                await item.getByRole('button', { name: 'Hapus' }).click();
                await Promise.all([
                    supervisorOnePage.waitForResponse(
                        (response) =>
                            response.request().method() === 'DELETE' &&
                            /\/dosen\/catatan\/\d+$/.test(response.url()),
                    ),
                    item.getByRole('button', { name: 'Ya, hapus' }).click(),
                ]);
                await expect(sheetOne.getByText(body)).toHaveCount(0);
            }

            await studentPage.reload();
            await expect(
                studentPage.getByRole('region', { name: 'Catatan Dosen' }),
            ).toHaveCount(0);
        } finally {
            await supervisorOneContext.close();
            await supervisorTwoContext.close();
            await studentContext.close();
        }
    });
});
