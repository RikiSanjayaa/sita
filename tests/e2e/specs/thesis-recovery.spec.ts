import { readFileSync } from 'node:fs';
import path from 'node:path';

import { expect, type Locator, type Page } from '@playwright/test';

import { daysFromNow, toDateTimeLocalString } from '../support/dates';
import {
    attachStageScreenshot,
    goldenPathStudent,
    openAdminProject,
    openWorkflowAction,
    pickFilamentSelect,
} from '../support/golden-path';
import { openRecoveryDefense, test } from '../support/recovery';
import { thesisFixtures } from '../support/thesis-fixtures';

const proposalFile = path.join(
    process.cwd(),
    thesisFixtures.thesisDocumentUploadPath,
);
const originalTitle = 'Pelacakan Riset untuk Pemulihan Alur Skripsi';

async function submitTitle(page: Page, title: string): Promise<void> {
    await page.goto('/mahasiswa/tugas-akhir');
    await page.getByLabel('Judul Skripsi (Bahasa Indonesia)').fill(title);
    await page
        .getByLabel('Judul Skripsi (Bahasa Inggris)')
        .fill('Thesis Recovery Proposal');
    await page
        .getByLabel('Ringkasan Naskah Proposal')
        .fill(
            'Pengajuan naskah proposal untuk pemulihan alur skripsi melalui antarmuka setiap peran.',
        );
    const discussionPicker = page
        .getByText('Dosen Diskusi Proposal', { exact: true })
        .locator('..');
    await discussionPicker
        .getByPlaceholder('Ketik min. 2 karakter...')
        .fill('Budi');
    await discussionPicker
        .getByText('Dr. Budi Santoso, M.Kom.', { exact: true })
        .click();
    await page
        .getByLabel('File Naskah Proposal (PDF)')
        .setInputFiles(proposalFile);
    await page.getByRole('button', { name: 'Ajukan Sekarang' }).click();
    await expect(page.getByText(title, { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Menunggu Persetujuan').first()).toBeVisible();
}

test('a rejected title explains the reason and lets the student submit a new proposal for approval', async ({
    recovery,
}, testInfo) => {
    const { student, admin } = recovery;
    const rejectedTitle =
        'Perancangan Pemantauan Riset dengan Lingkup Terlalu Luas';
    const newTitle = 'Pemantauan Satu Tahap Riset untuk Evaluasi Terukur';
    const reason =
        'Lingkup penelitian terlalu luas. Batasi pada satu tahap riset dan metrik yang jelas.';

    await test.step('Submit and reject the first proposal through the admin workflow', async () => {
        await submitTitle(student, rejectedTitle);
        await openAdminProject(admin, goldenPathStudent.name);
        const modal = await openWorkflowAction(admin, 'Tidak Setujui Judul');
        await modal.getByLabel('Alasan').fill(reason);
        await modal
            .getByRole('button', { name: 'Confirm', exact: true })
            .click();
        await expect(modal).toBeHidden();
    });
    const rejectedProjectUrl = admin.url();

    await test.step('The student reads the rejection and submits a new title, not an edit of the rejected record', async () => {
        await student.goto('/mahasiswa/tugas-akhir');
        await expect(
            student.getByText('Judul Tidak Disetujui').first(),
        ).toBeVisible();
        await expect(student.getByText(reason).first()).toBeVisible();
        await expect(
            student.getByRole('button', { name: 'Ajukan Sekarang' }),
        ).toBeVisible();
        await attachStageScreenshot(
            testInfo,
            student,
            'recovery-judul-ditolak-alasan-dan-langkah-berikutnya',
        );
        await submitTitle(student, newTitle);
    });

    await test.step('Admin approves the new record while the rejected history stays available to admin only', async () => {
        await openAdminProject(admin, goldenPathStudent.name);
        expect(admin.url()).not.toBe(rejectedProjectUrl);
        await expect(
            admin.getByText(newTitle, { exact: true }).first(),
        ).toBeVisible();
        const modal = await openWorkflowAction(admin, 'Setujui Judul');
        await modal
            .getByRole('button', { name: 'Submit', exact: true })
            .click();
        await expect(modal).toBeHidden();
        await student.goto('/mahasiswa/tugas-akhir');
        await expect(
            student.getByText(newTitle, { exact: true }).first(),
        ).toBeVisible();
        await expect(
            student.getByText('Judul Disetujui').first(),
        ).toBeVisible();
        await attachStageScreenshot(
            testInfo,
            student,
            'recovery-pengajuan-baru-disetujui',
        );
        await admin.goto(rejectedProjectUrl);
        await expect(
            admin.getByText(rejectedTitle, { exact: true }).first(),
        ).toBeVisible();
        await expect(
            admin.getByText('Dibatalkan', { exact: true }).first(),
        ).toBeVisible();
        await attachStageScreenshot(
            testInfo,
            admin,
            'recovery-admin-riwayat-judul-ditolak',
        );
        const forbidden = await student.goto(rejectedProjectUrl);
        expect(forbidden?.status()).toBe(403);
    });
});

test.describe('research recovery', () => {
    test.use({ recoveryScenario: 'research' });

    test('admin corrects an approved title and replaces the manuscript without resetting research or losing the student notification', async ({
        recovery,
    }, testInfo) => {
        const { student, admin } = recovery;
        const title = 'Pelacakan Riset dengan Evaluasi Pemulihan Terukur';
        const fileName = 'proposal-pemulihan-baru.pdf';
        const pdf = Buffer.concat([
            readFileSync(proposalFile),
            Buffer.from('\n% Updated recovery manuscript\n'),
        ]);

        await test.step('Admin replaces the approved submission through the edit form', async () => {
            await openAdminProject(admin, goldenPathStudent.name);
            await admin
                .getByRole('link', { name: 'Edit Skripsi', exact: true })
                .click();
            await admin.getByLabel('Judul Indonesia').fill(title);
            await admin
                .getByLabel('Judul Inggris')
                .fill('Measured Research Recovery Tracking');
            await admin
                .getByLabel('Ringkasan Naskah Proposal')
                .fill(
                    'Ruang lingkup riset diperjelas tanpa memulai ulang proses bimbingan.',
                );
            await admin.locator('input[type="file"]').setInputFiles({
                name: fileName,
                mimeType: 'application/pdf',
                buffer: pdf,
            });
            await expect(
                admin.getByText('Upload complete', { exact: true }),
            ).toBeVisible();
            await admin
                .getByRole('button', { name: 'Save changes', exact: true })
                .click();
            await expect(admin).toHaveURL(/\/admin\/thesis-projects\/\d+$/);
            await expect(
                admin.getByText(title, { exact: true }).first(),
            ).toBeVisible();
            await expect(
                admin.getByText('Riset', { exact: true }).first(),
            ).toBeVisible();
            await attachStageScreenshot(
                testInfo,
                admin,
                'recovery-admin-judul-naskah-riset-diperbarui',
            );
        });

        await test.step('The notification leads the student to the updated title and real replacement download', async () => {
            await student.goto('/mahasiswa/dashboard');
            await student
                .getByRole('button', { name: 'Open notifications' })
                .click();
            const notification = student
                .getByTestId('notification-card')
                .filter({
                    hasText: 'Judul / Naskah Proposal diperbarui admin',
                });
            await expect(notification).toContainText(originalTitle);
            await expect(notification).toContainText(title);
            await attachStageScreenshot(
                testInfo,
                student,
                'recovery-mahasiswa-notifikasi-perubahan-judul',
            );
            await notification.click({ position: { x: 20, y: 70 } });
            await expect(student).toHaveURL(/\/mahasiswa\/tugas-akhir/);
            await expect(
                student.getByText(title, { exact: true }).first(),
            ).toBeVisible();
            for (const name of [
                'Dr. Budi Santoso, M.Kom.',
                'Dr. Ratna Kusuma, M.Kom.',
            ]) {
                await expect(
                    student.getByText(name, { exact: true }).first(),
                ).toBeVisible();
            }
            await expect(
                student.getByRole('row').filter({ hasText: 'Sempro #1' }),
            ).toBeVisible();
            const fileRow = student
                .getByText(fileName, { exact: true })
                .locator('..')
                .locator('..')
                .locator('..');
            const [download] = await Promise.all([
                student.waitForEvent('download'),
                fileRow
                    .getByRole('link', { name: 'Unduh', exact: true })
                    .click(),
            ]);
            expect(download.suggestedFilename()).toBe(fileName);
            const downloadedPath = testInfo.outputPath(
                'proposal-pemulihan-diunduh.pdf',
            );
            await download.saveAs(downloadedPath);
            expect(readFileSync(downloadedPath)).toEqual(pdf);
            await attachStageScreenshot(
                testInfo,
                student,
                'recovery-mahasiswa-riset-pembimbing-dan-naskah-tetap-tersedia',
            );
        });

        await test.step('Existing workspace documents and chat remain accessible after the correction', async () => {
            await student.goto('/mahasiswa/upload-dokumen');
            await expect(
                student
                    .getByRole('row')
                    .filter({ hasText: 'Draft Riset Pemulihan' }),
            ).toBeVisible();
            await student.goto('/mahasiswa/pesan');
            await student
                .locator('button')
                .filter({ hasText: 'Bimbingan' })
                .first()
                .click();
            await expect(
                student
                    .getByText('Catatan riset sebelum pemulihan alur.', {
                        exact: true,
                    })
                    .last(),
            ).toBeVisible();
            await attachStageScreenshot(
                testInfo,
                student,
                'recovery-riwayat-chat-setelah-koreksi-judul',
            );
        });
    });
});

test.describe('cancelled research recovery', () => {
    test.use({ recoveryScenario: 'research' });

    test('admin cancels research and the student starts a separate proposal while documents, chat and admin history retain their actual scope', async ({
        recovery,
    }, testInfo) => {
        const { student, admin } = recovery;
        const reason =
            'Riset dihentikan karena perubahan ruang lingkup; ajukan judul baru.';
        const newTitle = 'Evaluasi Pemulihan Riset Setelah Pembatalan Skripsi';

        await openAdminProject(admin, goldenPathStudent.name);
        const cancelledProjectUrl = admin.url();
        await admin
            .getByRole('link', { name: 'Edit Skripsi', exact: true })
            .click();
        await admin.getByRole('combobox', { name: /^State/ }).click();
        await admin
            .getByRole('option', { name: 'Dibatalkan', exact: true })
            .click();
        await expect(
            admin.getByRole('combobox', { name: /^Dibatalkan Pada/ }),
        ).not.toHaveValue('');
        await admin.getByLabel('Catatan Admin').fill(reason);
        await admin
            .getByRole('button', { name: 'Save changes', exact: true })
            .click();
        await expect(admin).toHaveURL(cancelledProjectUrl);
        await expect(
            admin.getByText('Dibatalkan', { exact: true }).first(),
        ).toBeVisible();
        await expect(admin.getByText(reason, { exact: true })).toBeVisible();
        await attachStageScreenshot(
            testInfo,
            admin,
            'recovery-admin-pembatalan-riset',
        );

        await test.step('The dashboard explains the cancellation and its CTA opens a usable fresh submission form', async () => {
            await student.goto('/mahasiswa/dashboard');
            await expect(
                student
                    .getByText('Skripsi Dibatalkan', { exact: true })
                    .first(),
            ).toBeVisible();
            await student
                .getByRole('link', { name: /Ajukan Judul/ })
                .first()
                .click();
            await expect(student).toHaveURL(/\/mahasiswa\/tugas-akhir/);
            await expect(
                student.getByRole('button', { name: 'Ajukan Sekarang' }),
            ).toBeVisible();
            await expect(
                student.getByText(originalTitle, { exact: true }),
            ).toHaveCount(0);
            await expect(
                student.getByText('Dr. Budi Santoso, M.Kom.', { exact: true }),
            ).toHaveCount(0);
            await attachStageScreenshot(
                testInfo,
                student,
                'recovery-form-pengajuan-baru-setelah-pembatalan',
            );
        });

        await test.step('Student-level workspace files and the existing bimbingan conversation are retained, without former active advisors', async () => {
            await student.goto('/mahasiswa/upload-dokumen');
            await expect(
                student
                    .getByRole('row')
                    .filter({ hasText: 'Draft Riset Pemulihan' }),
            ).toBeVisible();
            await student.goto('/mahasiswa/pesan');
            await student
                .locator('button')
                .filter({ hasText: 'Bimbingan' })
                .first()
                .click();
            await expect(
                student
                    .getByText('Catatan riset sebelum pemulihan alur.', {
                        exact: true,
                    })
                    .last(),
            ).toBeVisible();
            await expect(
                student.getByText('Dr. Budi Santoso, M.Kom.', { exact: true }),
            ).toHaveCount(0);
            await expect(
                student.getByText('Dr. Ratna Kusuma, M.Kom.', { exact: true }),
            ).toHaveCount(0);
            await attachStageScreenshot(
                testInfo,
                student,
                'recovery-chat-tersimpan-tanpa-pembimbing-aktif-lama',
            );
        });

        await test.step('The new approved project does not inherit the cancelled project advisors or defense attempts', async () => {
            await submitTitle(student, newTitle);
            await openAdminProject(admin, goldenPathStudent.name);
            expect(admin.url()).not.toBe(cancelledProjectUrl);
            const approval = await openWorkflowAction(admin, 'Setujui Judul');
            await approval
                .getByRole('button', { name: 'Submit', exact: true })
                .click();
            await expect(approval).toBeHidden();
            await student.goto('/mahasiswa/tugas-akhir');
            await expect(
                student.getByText('Judul Disetujui', { exact: true }).first(),
            ).toBeVisible();
            await expect(
                student.getByText(newTitle, { exact: true }).first(),
            ).toBeVisible();
            await expect(
                student.getByRole('row').filter({ hasText: 'Sempro #1' }),
            ).toHaveCount(0);
            await expect(
                student.getByText('Dr. Ratna Kusuma, M.Kom.', { exact: true }),
            ).toHaveCount(0);
            await attachStageScreenshot(
                testInfo,
                student,
                'recovery-pengajuan-terpisah-setelah-pembatalan',
            );
            await admin.goto(cancelledProjectUrl);
            await expect(
                admin
                    .getByRole('region', {
                        name: 'Ringkasan Skripsi',
                        exact: true,
                    })
                    .getByText(originalTitle, { exact: true }),
            ).toBeVisible();
            await expect(
                admin
                    .getByText('Dr. Ratna Kusuma, M.Kom.', { exact: false })
                    .first(),
            ).toBeVisible();
            await admin
                .getByRole('tab', { name: 'Dokumen', exact: true })
                .click();
            await expect(
                admin.getByText('proposal-pemulihan-awal.pdf', { exact: true }),
            ).toBeVisible();
            await attachStageScreenshot(
                testInfo,
                admin,
                'recovery-admin-riwayat-dan-naskah-yang-dibatalkan',
            );
            const forbidden = await student.goto(cancelledProjectUrl);
            expect(forbidden?.status()).toBe(403);
        });
    });
});

async function submitRecoveryDecision(
    page: Page,
    decision: 'Lulus' | 'Lulus dengan Syarat' | 'Tidak Lulus',
    notes: string,
    score?: string,
    revision?: { location: string; description: string },
): Promise<void> {
    await page
        .getByRole('button', { name: 'Input Keputusan Saya', exact: true })
        .click();
    await page.getByRole('button', { name: decision, exact: true }).click();
    if (score !== undefined) {
        await page.getByLabel(/^Nilai/).fill(score);
    }
    await page.getByLabel('Catatan Keputusan').fill(notes);
    if (revision !== undefined) {
        await page.getByLabel(/^Catatan Revisi/).fill(revision.description);
        await page
            .getByRole('button', { name: 'Tambah Butir', exact: true })
            .click();
        await page.getByLabel('Butir 1: lokasi').fill(revision.location);
        await page
            .getByRole('textbox', { name: 'Uraian', exact: true })
            .fill(revision.description);
    }
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                /\/dosen\/seminar-proposal\/\d+\/decision$/.test(
                    response.url(),
                ),
        ),
        page
            .getByRole('button', { name: 'Submit Keputusan', exact: true })
            .click(),
    ]);
}

async function respondToRecoveryRevision(
    page: Page,
    responses: string[],
): Promise<void> {
    await page.goto('/mahasiswa/tugas-akhir');
    await page
        .getByRole('row')
        .filter({ hasText: 'Perlu ditanggapi' })
        .first()
        .click();
    const form = page.getByTestId('revision-response-form');
    await expect(form).toBeVisible();
    for (const [index, response] of responses.entries()) {
        await form
            .getByRole('checkbox', {
                name: `Butir ${index + 1} sudah diperbaiki`,
            })
            .click();
        await form.getByLabel(`Tanggapan butir ${index + 1}`).fill(response);
    }
    await form.getByLabel(/^Naskah perbaikan/).setInputFiles(proposalFile);
    await form
        .getByRole('button', { name: 'Kirim perbaikan', exact: true })
        .click();
    await expect(page.getByTestId('revision-response-form')).toHaveCount(0);
}

test.describe('conditional Sidang recovery', () => {
    test.use({ recoveryScenario: 'sidang' });

    test('a conditional Sidang pass needs a second revision round before graduation and public completion', async ({
        recovery,
        browser,
    }, testInfo) => {
        test.setTimeout(180_000);
        const { student, admin, lecturer } = recovery;
        const budi = await lecturer('dosen1');
        const ratna = await lecturer('dosen2');
        const fajar = await lecturer('dosen5');
        const publicContext = await browser.newContext();
        const publicPage = await publicContext.newPage();
        const revision = {
            location: 'Bab 5, hlm. 40',
            description: 'Lengkapi keterbatasan dataset pada pembahasan.',
        };
        const rejection = 'Batas generalisasi hasil belum dijelaskan.';
        const extraItem = 'Tambahkan evaluasi pada dataset pembanding.';

        try {
            await test.step('The complete panel grades and admin records the conditional result through its real workflow', async () => {
                await student.goto('/mahasiswa/tugas-akhir');
                await expect(
                    student.getByText('Dr. Fajar Nugraha, M.Kom.', {
                        exact: true,
                    }),
                ).toHaveCount(0);
                for (const member of [
                    { page: budi, score: '88' },
                    { page: ratna, score: '90' },
                ]) {
                    await openRecoveryDefense(
                        member.page,
                        goldenPathStudent.name,
                        'Sidang Akhir',
                        1,
                    );
                    await submitRecoveryDecision(
                        member.page,
                        'Lulus',
                        'Naskah dan presentasi memenuhi standar.',
                        member.score,
                    );
                }
                await openRecoveryDefense(
                    fajar,
                    goldenPathStudent.name,
                    'Sidang Akhir',
                    1,
                );
                await submitRecoveryDecision(
                    fajar,
                    'Lulus dengan Syarat',
                    'Lengkapi pembahasan sebelum kelulusan.',
                    '78',
                    revision,
                );
                await openAdminProject(admin, goldenPathStudent.name);
                const modal = await openWorkflowAction(
                    admin,
                    'Selesaikan Sidang Skripsi',
                );
                await pickFilamentSelect(modal, 0, 'pass_with_revision');
                await modal
                    .getByLabel('Batas Revisi')
                    .fill(toDateTimeLocalString(daysFromNow(14)));
                await modal
                    .getByLabel('Catatan Revisi')
                    .fill(revision.description);
                await modal
                    .getByLabel('Catatan Sidang Skripsi')
                    .fill('Lulus bersyarat; selesaikan revisi penguji.');
                await modal
                    .getByRole('button', { name: 'Submit', exact: true })
                    .click();
                await expect(modal).toBeHidden();
                await student.goto('/mahasiswa/tugas-akhir');
                await expect(
                    student
                        .getByText('Revisi Sidang Skripsi', { exact: true })
                        .first(),
                ).toBeVisible();
                await expect(
                    student
                        .getByText('Dr. Fajar Nugraha, M.Kom.', { exact: true })
                        .first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    student,
                    'recovery-sidang-lulus-bersyarat-belum-lulus-skripsi',
                );
            });

            await test.step('The student answers and uploads, but the examiner rejects the item and opens another round', async () => {
                await respondToRecoveryRevision(student, [
                    'Keterbatasan dataset sudah ditambahkan.',
                ]);
                await openRecoveryDefense(
                    fajar,
                    goldenPathStudent.name,
                    'Sidang Akhir',
                    1,
                );
                await expect(
                    fajar
                        .getByText('Keterbatasan dataset sudah ditambahkan.', {
                            exact: false,
                        })
                        .first(),
                ).toBeVisible();
                await fajar
                    .getByRole('button', { name: 'Belum butir 1', exact: true })
                    .click();
                await fajar.getByLabel('Komentar butir 1').fill(rejection);
                await fajar
                    .getByRole('button', { name: 'Tambah Butir', exact: true })
                    .click();
                await fajar
                    .getByRole('textbox', { name: 'Uraian', exact: true })
                    .fill(extraItem);
                await fajar
                    .getByRole('button', {
                        name: 'Simpan dan buka putaran berikutnya',
                        exact: true,
                    })
                    .click();
                await expect(
                    fajar.getByRole('button', {
                        name: 'Simpan dan buka putaran berikutnya',
                        exact: true,
                    }),
                ).toHaveCount(0);
                await student.goto('/mahasiswa/tugas-akhir');
                await student
                    .getByRole('row')
                    .filter({ hasText: 'Perlu ditanggapi' })
                    .click();
                const responseForm = student.getByTestId(
                    'revision-response-form',
                );
                await expect(responseForm).toContainText(rejection);
                await expect(responseForm).toContainText(extraItem);
                await expect(
                    responseForm.getByRole('checkbox', {
                        name: 'Butir 2 sudah diperbaiki',
                    }),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    student,
                    'recovery-sidang-putaran-kedua-alasan-penolakan-dan-butir-baru',
                );
                await publicPage.goto('/topik');
                await publicPage
                    .getByPlaceholder(
                        'Cari judul, ringkasan, mahasiswa, atau NIM...',
                    )
                    .fill(originalTitle);
                const row = publicPage
                    .getByRole('row')
                    .filter({ hasText: originalTitle })
                    .first();
                await expect(row).toBeVisible();
                await expect(
                    row.getByText('Selesai', { exact: true }),
                ).toHaveCount(0);
            });

            await test.step('Only acceptance of every item completes the project, retains both rounds, and publishes completion', async () => {
                await respondToRecoveryRevision(student, [
                    'Batas generalisasi diperjelas untuk setiap kelompok data.',
                    'Evaluasi dataset pembanding dan hasilnya sudah ditambahkan.',
                ]);
                await openRecoveryDefense(
                    fajar,
                    goldenPathStudent.name,
                    'Sidang Akhir',
                    1,
                );
                for (const index of [1, 2]) {
                    await fajar
                        .getByRole('button', {
                            name: `Diterima butir ${index}`,
                            exact: true,
                        })
                        .click();
                }
                await fajar
                    .getByRole('button', {
                        name: 'Simpan dan selesaikan revisi',
                        exact: true,
                    })
                    .click();
                await expect(
                    fajar.getByRole('button', {
                        name: 'Simpan dan selesaikan revisi',
                        exact: true,
                    }),
                ).toHaveCount(0);
                await student.goto('/mahasiswa/tugas-akhir');
                await expect(
                    student
                        .getByText('Sidang Skripsi Selesai', { exact: true })
                        .first(),
                ).toBeVisible();
                await expect(
                    student
                        .getByRole('row')
                        .filter({ hasText: 'Perlu ditanggapi' }),
                ).toHaveCount(0);
                await student
                    .getByRole('row')
                    .filter({ hasText: 'Sidang Skripsi #1' })
                    .click();
                await expect(
                    student.getByText('Putaran 1', { exact: true }).first(),
                ).toBeVisible();
                await expect(
                    student.getByText('Putaran 2', { exact: true }).first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    student,
                    'recovery-sidang-revisi-dua-putaran-selesai',
                );
                await admin.reload();
                await expect(
                    admin
                        .getByRole('region', {
                            name: 'Ringkasan Skripsi',
                            exact: true,
                        })
                        .getByText('Selesai', { exact: true })
                        .first(),
                ).toBeVisible();
                await publicPage.reload();
                const row = publicPage
                    .getByRole('row')
                    .filter({ hasText: originalTitle })
                    .first();
                await expect(
                    row.getByText('Selesai', { exact: true }),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    publicPage,
                    'recovery-topik-publik-selesai-setelah-revisi-sidang',
                );
            });
        } finally {
            await publicContext.close();
        }
    });
});

async function fillAdminRetrySchedule(
    modal: Locator,
    date: Date,
    location: string,
): Promise<void> {
    await modal.getByRole('combobox', { name: /^Tanggal Mulai/ }).click();
    const calendar = modal.getByRole('dialog', {
        name: 'Tanggal Mulai',
        exact: true,
    });
    await calendar
        .getByRole('spinbutton', { name: 'Year', exact: true })
        .fill(String(date.getFullYear()));
    await calendar
        .getByRole('combobox', { name: 'Month', exact: true })
        .selectOption(String(date.getMonth()));
    await calendar
        .locator(
            `[role="gridcell"][data-date="${toDateTimeLocalString(date).slice(0, 10)}"]`,
        )
        .click();
    await calendar.press('Escape');
    await modal.getByRole('combobox', { name: /^Jam/ }).click();
    const time = modal.getByRole('dialog', { name: /^Jam/ });
    await time
        .getByRole('spinbutton', { name: 'Hour', exact: true })
        .fill('11');
    await time
        .getByRole('spinbutton', { name: 'Minute', exact: true })
        .fill('00');
    await modal.getByLabel('Lokasi').fill(location);
}

for (const type of ['sempro', 'sidang'] as const) {
    test.describe(`${type} failure recovery`, () => {
        test.use({ recoveryScenario: type });

        test(`${type} failure is finalized, rescheduled, and passed through admin UI while the failed attempt remains readable`, async ({
            recovery,
        }, testInfo) => {
            test.setTimeout(180_000);
            const { student, admin, lecturer } = recovery;
            const label = type === 'sempro' ? 'Sempro' : 'Sidang Skripsi';
            const list =
                type === 'sempro' ? 'Sempro / Sinopsis' : 'Sidang Akhir';
            const members =
                type === 'sempro'
                    ? [await lecturer('dosen3'), await lecturer('dosen4')]
                    : [
                          await lecturer('dosen1'),
                          await lecturer('dosen2'),
                          await lecturer('dosen5'),
                      ];
            const failedReason = `${label} tidak lulus; evaluasi dan pembahasan belum memenuhi standar.`;
            const location = `Ruang ${label} Ulang Pemulihan`;
            const followUp = {
                location: 'Bab 3, hlm. 15',
                description:
                    'Perjelas batas evaluasi pada naskah proposal akhir.',
            };

            await test.step('All examiners grade the first attempt and admin finalizes its failure', async () => {
                for (const member of members) {
                    await openRecoveryDefense(
                        member,
                        goldenPathStudent.name,
                        list,
                        1,
                    );
                    await submitRecoveryDecision(
                        member,
                        'Tidak Lulus',
                        failedReason,
                        type === 'sidang' ? '50' : undefined,
                    );
                }
                await openAdminProject(admin, goldenPathStudent.name);
                const result = await openWorkflowAction(
                    admin,
                    type === 'sempro'
                        ? 'Catat Hasil Sempro'
                        : 'Selesaikan Sidang Skripsi',
                );
                await pickFilamentSelect(result, 0, 'fail');
                await result
                    .getByRole('textbox', {
                        name:
                            type === 'sempro'
                                ? /^Catatan/
                                : /^Catatan Sidang Skripsi/,
                    })
                    .fill(failedReason);
                await result
                    .getByRole('button', { name: 'Submit', exact: true })
                    .click();
                await expect(result).toBeHidden();
                await student.goto('/mahasiswa/tugas-akhir');
                await expect(
                    student
                        .getByText(`${label} Tidak Lulus`, { exact: true })
                        .first(),
                ).toBeVisible();
                await student
                    .getByRole('row')
                    .filter({ hasText: `${label} #1` })
                    .click();
                await expect(
                    student.getByText(failedReason, { exact: true }).first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    student,
                    `recovery-${type}-tidak-lulus-alasan-dan-riwayat`,
                );
            });

            await test.step('Admin creates the second attempt with the real calendar, time and examiner selectors', async () => {
                const schedule = await openWorkflowAction(
                    admin,
                    `Jadwalkan ${label}`,
                );
                await fillAdminRetrySchedule(
                    schedule,
                    daysFromNow(60),
                    location,
                );
                if (type === 'sempro') {
                    for (const [index, name] of [
                        'Ahmad Hidayat',
                        'Dewi Lestari',
                    ].entries()) {
                        await schedule
                            .getByRole('button', {
                                name: new RegExp(`^Penguji ${index + 1}`),
                            })
                            .click();
                        await schedule
                            .locator(
                                '.fi-select-input-search-ctn:visible input',
                            )
                            .fill(name);
                        await schedule
                            .getByRole('option', { name: new RegExp(name) })
                            .click();
                    }
                }
                await schedule
                    .getByRole('button', { name: 'Submit', exact: true })
                    .click();
                await expect(schedule).toBeHidden();
                await student.goto('/mahasiswa/tugas-akhir');
                await expect(
                    student
                        .getByText(`${label} Dijadwalkan`, { exact: true })
                        .first(),
                ).toBeVisible();
                await expect(
                    student.getByRole('row').filter({ hasText: `${label} #1` }),
                ).toContainText('Tidak Lulus');
                await expect(
                    student.getByRole('row').filter({ hasText: `${label} #2` }),
                ).toContainText(location);
                await attachStageScreenshot(
                    testInfo,
                    student,
                    `recovery-${type}-attempt-kedua-dan-riwayat-gagal`,
                );
            });

            await test.step('The panel grades the second attempt and admin records its passing result', async () => {
                for (const [index, member] of members.entries()) {
                    await openRecoveryDefense(
                        member,
                        goldenPathStudent.name,
                        list,
                        2,
                    );
                    await submitRecoveryDecision(
                        member,
                        'Lulus',
                        'Perbaikan attempt ulang sudah memenuhi standar.',
                        type === 'sidang' ? '88' : undefined,
                        type === 'sempro' && index === 0 ? followUp : undefined,
                    );
                }
                await admin.reload();
                const modal = await openWorkflowAction(
                    admin,
                    type === 'sempro'
                        ? 'Catat Hasil Sempro'
                        : 'Selesaikan Sidang Skripsi',
                );
                await pickFilamentSelect(
                    modal,
                    0,
                    type === 'sempro' ? 'pass_with_revision' : 'pass',
                );
                if (type === 'sempro') {
                    await modal
                        .getByLabel('Batas Revisi')
                        .fill(toDateTimeLocalString(daysFromNow(14)));
                }
                await modal
                    .getByRole('textbox', {
                        name:
                            type === 'sempro'
                                ? /^Catatan/
                                : /^Catatan Sidang Skripsi/,
                    })
                    .fill('Attempt ulang dinyatakan lulus.');
                await modal
                    .getByRole('button', { name: 'Submit', exact: true })
                    .click();
                await expect(modal).toBeHidden();
                if (type === 'sempro') {
                    await respondToRecoveryRevision(student, [
                        'Batas evaluasi dan data uji sudah diperjelas.',
                    ]);
                    await openRecoveryDefense(
                        members[0],
                        goldenPathStudent.name,
                        list,
                        2,
                    );
                    await members[0]
                        .getByRole('button', {
                            name: 'Diterima butir 1',
                            exact: true,
                        })
                        .click();
                    await members[0]
                        .getByRole('button', {
                            name: 'Simpan dan selesaikan revisi',
                            exact: true,
                        })
                        .click();
                    await expect(
                        members[0].getByRole('button', {
                            name: 'Simpan dan selesaikan revisi',
                            exact: true,
                        }),
                    ).toHaveCount(0);
                }
                await student.goto('/mahasiswa/tugas-akhir');
                await expect(
                    student
                        .getByText(`${label} Selesai`, { exact: true })
                        .first(),
                ).toBeVisible();
                const failedAttempt = student
                    .getByRole('row')
                    .filter({ hasText: `${label} #1` });
                await expect(failedAttempt).toContainText('Tidak Lulus');
                await expect(
                    student.getByRole('row').filter({ hasText: `${label} #2` }),
                ).toContainText('Lulus');
                await failedAttempt.click();
                await expect(
                    student.getByText(failedReason, { exact: true }).first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    student,
                    `recovery-${type}-lulus-attempt-kedua-riwayat-tetap-tersedia`,
                );
                await openRecoveryDefense(
                    members[0],
                    goldenPathStudent.name,
                    list,
                    1,
                );
                await expect(
                    members[0].getByRole('button', {
                        name: 'Input Keputusan Saya',
                        exact: true,
                    }),
                ).toHaveCount(0);
                await attachStageScreenshot(
                    testInfo,
                    members[0],
                    `recovery-${type}-dosen-riwayat-attempt-gagal`,
                );
            });
        });
    });
}
