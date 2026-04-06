import path from 'node:path';

import { expect, test } from '@playwright/test';

import {
    thesisFixtures,
    thesisScenarios,
    authStatePath,
} from '../support/thesis-fixtures';

test.describe('Mahasiswa title and proposal flow', () => {
    test.use({
        storageState: authStatePath(
            thesisScenarios.titleReviewPending.studentKey,
        ),
    });

    test('mahasiswa can revise a title-review submission from the thesis page', async ({
        page,
    }) => {
        await page.goto('/mahasiswa/tugas-akhir');
        await expect(page).toHaveTitle(/Tugas Akhir/i);

        await expect(page.getByText('Informasi Judul').first()).toBeVisible();
        await expect(
            page
                .getByText(thesisScenarios.titleReviewPending.currentTitle)
                .first(),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Edit' }).click();
        await page
            .getByLabel('Judul Skripsi (Bahasa Indonesia)')
            .fill(
                'Implementasi Blockchain untuk Sistem Voting Digital Terdistribusi',
            );
        await page
            .getByLabel('Ringkasan Proposal')
            .fill(
                'Proposal diperbarui dari Playwright untuk memastikan mahasiswa masih dapat mengedit pengajuan yang belum diputuskan admin.',
            );
        await page
            .getByLabel('Ganti File Proposal (Opsional)')
            .setInputFiles(
                path.join(
                    process.cwd(),
                    thesisFixtures.thesisDocumentUploadPath,
                ),
            );
        await page.getByRole('button', { name: 'Simpan Perubahan' }).click();

        await expect(
            page
                .getByText('Pengajuan Judul & Proposal berhasil diperbarui.')
                .first(),
        ).toBeVisible();
        await expect(
            page
                .getByText(
                    'Implementasi Blockchain untuk Sistem Voting Digital Terdistribusi',
                )
                .first(),
        ).toBeVisible();
    });

    test('mahasiswa submits a brand new title and proposal as the first thesis record', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.firstSubmission.studentKey,
            ),
        });
        const adminContext = await browser.newContext({
            storageState: authStatePath('admin'),
        });

        const titleId =
            'Arsitektur Zero-Trust untuk Keamanan Akses Laboratorium Komputasi';

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const adminPage = await adminContext.newPage();

            await mahasiswaPage.goto('/mahasiswa/tugas-akhir');
            await expect(mahasiswaPage).toHaveTitle(/Tugas Akhir/i);
            await expect(
                mahasiswaPage.getByText('Ajukan Judul & Proposal').first(),
            ).toBeVisible();

            await mahasiswaPage
                .getByLabel('Judul Skripsi (Bahasa Indonesia)')
                .fill(titleId);
            await mahasiswaPage
                .getByLabel('Judul Skripsi (Bahasa Inggris)')
                .fill(
                    'Zero-Trust Architecture for Securing Computing Laboratory Access',
                );
            await mahasiswaPage
                .getByLabel('Ringkasan Proposal')
                .fill(
                    'Proposal Playwright ini mengajukan rancangan kontrol akses laboratorium berbasis zero-trust dengan evaluasi kebijakan identitas, perangkat, dan sesi penggunaan.',
                );
            await mahasiswaPage
                .getByLabel('File Proposal (PDF)')
                .setInputFiles(
                    path.join(
                        process.cwd(),
                        thesisFixtures.thesisDocumentUploadPath,
                    ),
                );
            await mahasiswaPage
                .getByRole('button', { name: 'Ajukan Sekarang' })
                .click();

            await expect(
                mahasiswaPage
                    .getByText(
                        'Judul & Proposal berhasil diajukan dan sedang menunggu review Admin.',
                    )
                    .first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText('Status Pengajuan').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText(titleId).first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText('Menunggu Persetujuan').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage
                    .getByText(
                        'Pengajuan judul dan proposal Anda sedang ditinjau admin.',
                    )
                    .first(),
            ).toBeVisible();

            await adminPage.goto(thesisFixtures.adminProjectsPath);
            await expect(adminPage).toHaveURL(/\/admin\/thesis-projects/);

            const projectRow = adminPage
                .locator('tr')
                .filter({
                    hasText: thesisScenarios.firstSubmission.studentName,
                })
                .first();

            await expect(projectRow).toBeVisible();
            await expect(projectRow).toContainText('Arsitektur Zero-Trust');
            await expect(projectRow).toContainText('Review Judul');
        } finally {
            await mahasiswaContext.close();
            await adminContext.close();
        }
    });
});
