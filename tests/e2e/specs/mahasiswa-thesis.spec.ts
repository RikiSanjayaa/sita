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

    test.fixme('mahasiswa submits a brand new title and proposal as the first thesis record', async () => {
        // Use a dedicated mahasiswa account without an existing thesis project.
        // Fill the initial form in /mahasiswa/tugas-akhir.
        // Upload a PDF proposal and verify the workflow badge becomes title review pending.
        // Verify admin sees the new project in /admin/thesis-projects.
    });
});
