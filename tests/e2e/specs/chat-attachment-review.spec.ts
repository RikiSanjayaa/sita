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
    test.describe.configure({ mode: 'serial' });
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

            const attachmentInput = mahasiswaPage.locator('input[type="file"]');
            await attachmentInput.setInputFiles(
                path.join(
                    process.cwd(),
                    thesisFixtures.thesisDocumentUploadPath,
                ),
            );
            await expect(
                mahasiswaPage.getByLabel('Nama dokumen'),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText(attachmentName).first(),
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
            await expect(studentRow).toContainText('2 review');
            await expect(studentRow).not.toContainText(revisionNotes);
            await expect(studentRow).not.toContainText(/\bv\d+\b/);

            await studentRow.getByRole('button', { name: /^Detail/ }).click();
            const detailDrawer = mahasiswaPage.getByRole('dialog');
            await expect(detailDrawer).toContainText(revisionNotes);
            await expect(detailDrawer).toContainText('Perlu Revisi');
            await expect(detailDrawer).not.toContainText(/\bv\d+\b/);
            await mahasiswaPage.keyboard.press('Escape');
            await expect(detailDrawer).toBeHidden();

            await openMahasiswaBimbinganThread(mahasiswaPage);
            await expect(
                mahasiswaPage
                    .getByText(/menandai lampiran .* perlu revisi/i)
                    .first(),
            ).toBeVisible();
        } finally {
            await mahasiswaContext.close();
            await secondLecturerContext.close();
        }
    });

    test('second lecturer reviews a chat upload from the notification card', async ({
        browser,
    }) => {
        const revisionNotes = `Tambahkan daftar pustaka ${Date.now()}`;

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

            await mahasiswaPage
                .locator('input[type="file"]')
                .setInputFiles(
                    path.join(
                        process.cwd(),
                        thesisFixtures.thesisDocumentUploadPath,
                    ),
                );
            await expect(
                mahasiswaPage.getByLabel('Nama dokumen'),
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

            await secondLecturerPage.goto('/dosen/pesan-bimbingan');
            await secondLecturerPage
                .locator('button')
                .filter({ hasText: 'Bimbingan' })
                .first()
                .click();
            await secondLecturerPage
                .locator('button')
                .filter({ hasText: thesisScenarios.activeResearch.studentName })
                .first()
                .click();

            await secondLecturerPage
                .getByRole('button', { name: `Tinjau ${attachmentName}` })
                .last()
                .click();

            const revisionDialog = secondLecturerPage.getByRole('dialog', {
                name: 'Kirim Catatan Revisi',
            });

            await expect(revisionDialog).toBeVisible();
            await revisionDialog
                .getByRole('textbox', { name: 'Catatan Revisi' })
                .fill(revisionNotes);
            await revisionDialog
                .getByRole('button', { name: 'Kirim Catatan' })
                .click();

            await expect(revisionDialog).toBeHidden();
            await expect(
                secondLecturerPage
                    .getByText(/menandai lampiran .* perlu revisi/i)
                    .first(),
            ).toBeVisible();

            await mahasiswaPage.goto('/mahasiswa/upload-dokumen');
            await mahasiswaPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(attachmentName);

            const studentRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: attachmentName })
                .first();

            await expect(studentRow).toContainText('Perlu Revisi');
            await studentRow.getByRole('button', { name: /^Detail/ }).click();
            await expect(mahasiswaPage.getByRole('dialog')).toContainText(
                revisionNotes,
            );
        } finally {
            await mahasiswaContext.close();
            await secondLecturerContext.close();
        }
    });
});
