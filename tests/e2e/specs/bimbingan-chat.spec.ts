import path from 'node:path';

import { expect, test } from '@playwright/test';

import {
    authStatePath,
    thesisFixtures,
    thesisScenarios,
} from '../support/thesis-fixtures';

function toDateTimeLocalString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

test.describe('Bimbingan and chat integration', () => {
    test('mahasiswa and dosen can both see the seeded mentorship workspace for the same thesis project', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await mahasiswaPage.goto('/mahasiswa/pesan');
            await expect(mahasiswaPage).toHaveTitle(/Pesan/i);
            await expect(
                mahasiswaPage.getByText('Thread bimbingan dan sempro Anda'),
            ).toBeVisible();
            await expect(
                mahasiswaPage
                    .getByText(
                        'Pak, saya sudah unggah draft terbaru. Mohon review sebelum bimbingan berikutnya.',
                    )
                    .first(),
            ).toBeVisible();

            await dosenPage.goto('/dosen/pesan-bimbingan');
            await expect(dosenPage).toHaveTitle(/Pesan Bimbingan Dosen/i);
            await expect(
                dosenPage
                    .getByText(thesisScenarios.activeResearch.studentName)
                    .first(),
            ).toBeVisible();
            await expect(
                dosenPage
                    .getByText(
                        'Baik, saya cek dulu. Kita bahas saat sesi bimbingan terjadwal.',
                    )
                    .first(),
            ).toBeVisible();

            await dosenPage.goto('/dosen/dokumen-revisi');
            await expect(dosenPage).toHaveTitle(/Dokumen & Revisi Dosen/i);
            await expect(
                dosenPage.getByText('Draft Bab 1').first(),
            ).toBeVisible();

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
            await expect(mahasiswaPage).toHaveTitle(/Jadwal Bimbingan/i);
            await expect(
                mahasiswaPage
                    .getByText('Review Bab II dan kesiapan implementasi')
                    .first(),
            ).toBeVisible();
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('mahasiswa uploads a new thesis document and it appears in both chat and dosen review queue', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        const documentTitle = 'Draft Bab 3 Metodologi Playwright';
        const documentFileName = path.basename(
            thesisFixtures.thesisDocumentUploadPath,
        );

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await mahasiswaPage.goto('/mahasiswa/upload-dokumen?open=unggah');
            await expect(mahasiswaPage).toHaveTitle(/Upload Dokumen/i);
            await expect(
                mahasiswaPage.getByRole('dialog', { name: 'Upload Dokumen' }),
            ).toBeVisible();

            await mahasiswaPage.getByLabel('Judul Dokumen').fill(documentTitle);
            await mahasiswaPage
                .getByLabel('File Dokumen')
                .setInputFiles(
                    path.join(
                        process.cwd(),
                        thesisFixtures.thesisDocumentUploadPath,
                    ),
                );
            await mahasiswaPage.getByRole('button', { name: 'Upload' }).click();

            await expect(
                mahasiswaPage.getByText(
                    'Dokumen berhasil diunggah dan notifikasi terkirim ke thread terkait.',
                ),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText(documentTitle).first(),
            ).toBeVisible();

            await mahasiswaPage.goto('/mahasiswa/pesan');
            await expect(mahasiswaPage).toHaveTitle(/Pesan/i);
            await expect(
                mahasiswaPage.getByText('Dokumen Baru Diunggah').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText(documentFileName).first(),
            ).toBeVisible();

            await dosenPage.goto('/dosen/dokumen-revisi');
            await expect(dosenPage).toHaveTitle(/Dokumen & Revisi Dosen/i);
            await expect(
                dosenPage
                    .getByText(thesisScenarios.activeResearch.studentName)
                    .first(),
            ).toBeVisible();
            await expect(
                dosenPage.getByText(documentTitle).first(),
            ).toBeVisible();
            await expect(
                dosenPage.getByText(documentFileName).first(),
            ).toBeVisible();
            await expect(
                dosenPage.getByText('Perlu Review').first(),
            ).toBeVisible();
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('mahasiswa requests a new bimbingan slot and dosen approves it from the lecturer workspace', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        const topic = 'Bimbingan Playwright Bab 5 Integrasi';
        const lecturerFeedback =
            'Silakan siapkan evaluasi akhir dan ringkasan hasil eksperimen.';
        const confirmedLocation = 'Ruang Bimbingan Playwright';
        const requestedFor = toDateTimeLocalString(
            new Date(Date.now() + 3 * 24 * 60 * 60 * 1000),
        );

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan?open=ajukan');
            await expect(mahasiswaPage).toHaveTitle(/Jadwal Bimbingan/i);
            await expect(
                mahasiswaPage.getByRole('dialog', {
                    name: 'Ajukan Jadwal Bimbingan',
                }),
            ).toBeVisible();

            await mahasiswaPage.getByLabel('Topik Bimbingan').fill(topic);
            await mahasiswaPage
                .getByLabel('Tanggal & Waktu Preferensi')
                .fill(requestedFor);
            await mahasiswaPage
                .getByLabel('Catatan Tambahan (Opsional)')
                .fill('Mohon validasi kesiapan presentasi dan bab penutup.');
            await mahasiswaPage
                .getByRole('button', { name: 'Kirim Permintaan' })
                .click();

            await expect(
                mahasiswaPage.getByText(
                    'Permintaan jadwal bimbingan berhasil dikirim.',
                ),
            ).toBeVisible();

            const mahasiswaPendingRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic })
                .first();
            await expect(mahasiswaPendingRow).toBeVisible();
            await expect(mahasiswaPendingRow).toContainText(
                'Menunggu Konfirmasi',
            );

            await dosenPage.goto('/dosen/jadwal-bimbingan');
            await expect(dosenPage).toHaveTitle(/Jadwal Bimbingan Dosen/i);

            const requestCard = dosenPage
                .locator('div.rounded-xl.border.bg-card.p-5.shadow-sm')
                .filter({ hasText: topic })
                .first();

            await expect(requestCard).toBeVisible();
            await requestCard
                .locator('textarea[id^="note-"]')
                .fill(lecturerFeedback);
            await requestCard
                .locator('input[id^="location-"]')
                .fill(confirmedLocation);
            await requestCard
                .getByRole('button', { name: 'Konfirmasi' })
                .click();

            await expect(
                dosenPage.getByText('Keputusan jadwal berhasil disimpan.'),
            ).toBeVisible();
            await expect(requestCard).toHaveCount(0);

            const dosenUpcomingRow = dosenPage
                .locator('tr')
                .filter({ hasText: topic })
                .first();
            await expect(dosenUpcomingRow).toBeVisible();
            await expect(dosenUpcomingRow).toContainText(
                thesisScenarios.activeResearch.studentName,
            );

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
            await expect(mahasiswaPage).toHaveTitle(/Jadwal Bimbingan/i);

            const mahasiswaApprovedRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic })
                .first();
            await expect(mahasiswaApprovedRow).toBeVisible();
            await expect(mahasiswaApprovedRow).toContainText('Terjadwal');
            await expect(mahasiswaApprovedRow).toContainText(confirmedLocation);
            await expect(mahasiswaApprovedRow).toContainText(lecturerFeedback);
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });
});
