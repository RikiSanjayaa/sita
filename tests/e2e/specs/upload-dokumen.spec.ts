import path from 'node:path';

import { expect, test } from '@playwright/test';

import { openDocumentDetail } from '../support/document-detail';
import {
    authStatePath,
    thesisFixtures,
    thesisScenarios,
} from '../support/thesis-fixtures';

test.describe('Upload dokumen page', () => {
    test('mahasiswa can download and confirm-delete an unlinked uploaded document', async ({
        browser,
    }) => {
        const title = `Playwright Upload Dokumen ${Date.now()}`;

        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });

        try {
            const page = await mahasiswaContext.newPage();

            await page.goto('/mahasiswa/upload-dokumen?open=unggah');
            await expect(page).toHaveTitle(/Upload Dokumen/i);
            await expect(
                page.getByRole('dialog', { name: 'Upload Dokumen' }),
            ).toBeVisible();

            await page.getByLabel('Judul Dokumen').fill(title);
            await page.getByLabel('Kategori Dokumen').click();
            await page.getByRole('option', { name: 'Draft Skripsi' }).click();
            await page
                .getByLabel('File Dokumen')
                .setInputFiles(
                    path.join(
                        process.cwd(),
                        thesisFixtures.thesisDocumentUploadPath,
                    ),
                );
            await page.getByRole('button', { name: 'Upload' }).click();

            await expect(
                page
                    .locator('[data-sonner-toast][data-type="success"]')
                    .getByText(
                        'Dokumen berhasil diunggah dan notifikasi terkirim ke thread terkait.',
                    ),
            ).toBeVisible();

            await page.getByPlaceholder('Cari judul atau file...').fill(title);

            const documentRow = page
                .locator('tr')
                .filter({ hasText: title })
                .first();

            await expect(documentRow).toBeVisible();

            const downloadPromise = page.waitForEvent('download');
            await page
                .getByRole('link', { name: `Unduh dokumen ${title}` })
                .click();
            const download = await downloadPromise;

            expect(await download.failure()).toBeNull();

            const detailUrl = await openDocumentDetail(
                page,
                documentRow,
                '/mahasiswa/upload-dokumen',
            );
            await expect(
                page.getByText(title, { exact: true }).first(),
            ).toBeVisible();
            await expect(
                page.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveCount(0);
            await page.reload();
            await expect(page).toHaveURL(new RegExp(`${detailUrl}$`));
            await page.goBack();
            await expect(page).toHaveURL(
                /\/mahasiswa\/upload-dokumen(?:\?.*)?$/,
            );
            await page.getByPlaceholder('Cari judul atau file...').fill(title);

            await page
                .getByRole('button', { name: `Hapus dokumen ${title}` })
                .click();
            await expect(
                page.getByRole('dialog', { name: 'Hapus Dokumen' }),
            ).toBeVisible();

            await page.getByRole('button', { name: 'Batal' }).click();
            await expect(
                page.getByRole('dialog', { name: 'Hapus Dokumen' }),
            ).not.toBeVisible();

            await page
                .getByRole('button', { name: `Hapus dokumen ${title}` })
                .click();
            await page.getByRole('button', { name: 'Ya, Hapus' }).click();

            await expect(
                page
                    .locator('[data-sonner-toast][data-type="success"]')
                    .getByText('Dokumen berhasil dihapus.'),
            ).toBeVisible();
            await expect(documentRow).not.toBeVisible();
        } finally {
            await mahasiswaContext.close();
        }
    });
});
