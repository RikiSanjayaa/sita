import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import {
    authStatePath,
    thesisFixtures,
    thesisScenarios,
} from '../support/thesis-fixtures';

const attachmentName = path.basename(thesisFixtures.thesisDocumentUploadPath);

async function openMahasiswaBimbinganThread(page: Page): Promise<void> {
    await page.goto('/mahasiswa/pesan');

    const bimbinganThreadButton = page
        .locator('button')
        .filter({ hasText: 'Bimbingan' })
        .first();

    await expect(bimbinganThreadButton).toBeVisible();
    await bimbinganThreadButton.click();
    await expect(page.getByPlaceholder('Tulis pesan...')).toBeEnabled();
}

test.describe('Chat attachment review visibility', () => {
    test('chat attachment reviewed by second lecturer shows status to mahasiswa', async ({
        browser,
    }) => {
        const revisionNotes = `Perbaiki sitasi pada bab pembuka ${Date.now()}`;

        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const secondLecturerContext = await browser.newContext({
            storageState: authStatePath('dosen5'),
        });

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();

            await openMahasiswaBimbinganThread(mahasiswaPage);

            const attachmentInput = mahasiswaPage.locator(
                'input[type="file"][accept=".pdf,.doc,.docx"]',
            );
            await attachmentInput.setInputFiles(
                path.join(
                    process.cwd(),
                    thesisFixtures.thesisDocumentUploadPath,
                ),
            );
            await expect(
                mahasiswaPage.getByText(`Lampiran: ${attachmentName}`),
            ).toBeVisible();

            const messageInput =
                mahasiswaPage.getByPlaceholder('Tulis pesan...');
            const submitRequest = mahasiswaPage.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    response.url().includes('/mahasiswa/pesan/'),
            );
            await messageInput.press('Enter');
            await submitRequest;

            const secondLecturerPage = await secondLecturerContext.newPage();

            await secondLecturerPage.goto('/dosen/dokumen-revisi');
            await secondLecturerPage
                .getByPlaceholder(/Cari mahasiswa.*file/i)
                .fill(attachmentName);

            const reviewRow = secondLecturerPage
                .locator('tr')
                .filter({ hasText: thesisScenarios.activeResearch.studentName })
                .filter({ hasText: attachmentName })
                .first();

            await expect(reviewRow).toBeVisible();
            await reviewRow
                .getByRole('button', { name: 'Kirim Catatan Revisi' })
                .click();

            const revisionDialog = secondLecturerPage.getByRole('dialog', {
                name: 'Kirim Catatan Revisi',
            });

            await expect(revisionDialog).toBeVisible();
            await revisionDialog
                .getByRole('textbox', { name: 'Catatan Revisi' })
                .fill(revisionNotes);
            await secondLecturerPage
                .getByRole('button', { name: 'Kirim Catatan' })
                .click();

            await expect(reviewRow).toContainText('Perlu Revisi');
            await expect(reviewRow).toContainText(revisionNotes);

            await mahasiswaPage.goto('/mahasiswa/upload-dokumen');
            await mahasiswaPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(attachmentName);

            const studentRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: attachmentName })
                .first();

            await expect(studentRow).toBeVisible();
            await expect(studentRow).toContainText('Perlu Revisi');
            await expect(studentRow).toContainText(revisionNotes);

            await openMahasiswaBimbinganThread(mahasiswaPage);
            await expect(
                mahasiswaPage
                    .getByText(/menandai dokumen .* perlu revisi/i)
                    .first(),
            ).toBeVisible();
        } finally {
            await mahasiswaContext.close();
            await secondLecturerContext.close();
        }
    });
});
