import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
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

async function uploadThesisDocument(
    page: Page,
    title: string,
    categoryLabel: 'Draft Skripsi' | 'Naskah Proposal' | 'Laporan' | 'Lampiran',
): Promise<void> {
    await page.goto('/mahasiswa/upload-dokumen?open=unggah');
    await expect(
        page.getByRole('dialog', { name: 'Upload Dokumen' }),
    ).toBeVisible();

    await page.getByLabel('Judul Dokumen').fill(title);
    await page.getByLabel('Kategori Dokumen').click();
    await page.getByRole('option', { name: categoryLabel }).click();
    await page
        .getByLabel('File Dokumen')
        .setInputFiles(
            path.join(process.cwd(), thesisFixtures.thesisDocumentUploadPath),
        );
    await page.getByRole('button', { name: 'Upload' }).click();

    const uploadedRow = page.locator('tr').filter({ hasText: title }).first();
    await expect(uploadedRow).toBeVisible();
    await expect(uploadedRow).toContainText('Menunggu Review');
}

async function waitForThreadSubscription(
    page: Page,
    requireResubscribe = false,
): Promise<void> {
    await page.waitForFunction((requireResubscribe) => {
        const threadId = new URLSearchParams(window.location.search).get(
            'thread',
        );
        const channel = threadId
            ? window.Echo.connector.pusher.channel(
                  `private-mentorship.thread.${threadId}`,
              )
            : undefined;
        const windowWithPreviousChannel = window as Window & {
            __previousThreadChannel?: unknown;
        };

        return (
            channel?.subscribed === true &&
            (!requireResubscribe ||
                channel !== windowWithPreviousChannel.__previousThreadChannel)
        );
    }, requireResubscribe);
}

async function captureThreadChannel(page: Page): Promise<void> {
    await page.evaluate(() => {
        const threadId = new URLSearchParams(window.location.search).get(
            'thread',
        );
        const windowWithPreviousChannel = window as Window & {
            __previousThreadChannel?: unknown;
        };

        windowWithPreviousChannel.__previousThreadChannel = threadId
            ? window.Echo.connector.pusher.channel(
                  `private-mentorship.thread.${threadId}`,
              )
            : undefined;
    });
}

async function sendChatMessage(
    page: Page,
    urlPattern: RegExp,
    message: string,
): Promise<void> {
    const messageInput = page.getByPlaceholder('Tulis pesan...');
    const pageUrl = new URL(page.url());
    await captureThreadChannel(page);
    const submitRequest = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            urlPattern.test(response.url()),
    );
    const inertiaFollowUp = page.waitForResponse((response) => {
        const responseUrl = new URL(response.url());

        return (
            response.request().method() === 'GET' &&
            response.status() === 200 &&
            responseUrl.pathname === pageUrl.pathname
        );
    });

    await messageInput.fill(message);
    await messageInput.press('Enter');
    await submitRequest;
    await inertiaFollowUp;
    await expect(messageInput).toHaveValue('');
    await expect(page.getByText(message).last()).toBeVisible();
    await waitForThreadSubscription(page, true);
}

async function sendChatAttachment(
    page: Page,
    urlPattern: RegExp,
    filePath: string,
    caption: string | null,
): Promise<void> {
    await captureThreadChannel(page);
    const submitRequest = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            urlPattern.test(response.url()),
    );

    await page.locator('input[type="file"]').setInputFiles(filePath);
    await expect(
        page.getByRole('button', { name: 'Hapus lampiran' }),
    ).toBeVisible();

    if (caption !== null) {
        await page.getByPlaceholder('Tulis pesan...').fill(caption);
    }

    await page.getByRole('button', { name: 'Kirim pesan' }).click();
    await submitRequest;
    await expect(
        page.getByRole('button', { name: 'Hapus lampiran' }),
    ).toHaveCount(0);
    await waitForThreadSubscription(page, true);
}

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

async function openDosenBimbinganThread(
    page: Page,
    studentName: string,
): Promise<void> {
    await page.goto('/dosen/pesan-bimbingan');
    await page
        .getByPlaceholder(/Cari (mahasiswa|grup)\.\.\./)
        .fill(studentName);

    const threadButton = page
        .locator('button')
        .filter({ hasText: studentName })
        .filter({ hasText: 'Bimbingan' })
        .first();

    await expect(threadButton).toBeVisible();
    await threadButton.click();
    await expect(page.getByText(studentName).first()).toBeVisible();
}

async function requestBimbinganSchedule(
    page: Page,
    {
        topic,
        requestedFor,
        studentNote,
        recurring,
    }: {
        topic: string;
        requestedFor: string;
        studentNote: string;
        recurring?: {
            pattern: 'Mingguan' | '2 Mingguan' | 'Bulanan';
            count: string;
        };
    },
): Promise<void> {
    await page.goto('/mahasiswa/jadwal-bimbingan?open=ajukan');
    await expect(
        page.getByRole('dialog', { name: 'Ajukan Jadwal Bimbingan' }),
    ).toBeVisible();

    await page.getByLabel('Topik Bimbingan').fill(topic);
    await page.getByLabel('Tanggal & Waktu Preferensi').fill(requestedFor);
    await page.getByLabel('Catatan Tambahan (Opsional)').fill(studentNote);

    if (recurring !== undefined) {
        await page.getByLabel('Jadwalkan Berulang').check();
        await page.getByLabel('Pola Pengulangan').click();
        await page
            .getByRole('option', {
                name: recurring.pattern,
                exact: true,
            })
            .click();
        await page.getByLabel('Jumlah Pertemuan').click();
        await page
            .getByRole('option', {
                name: `${recurring.count} pertemuan`,
                exact: true,
            })
            .click();
    }

    await Promise.all([
        page.waitForResponse((response) => {
            const url = new URL(response.url());

            return (
                response.request().method() === 'POST' &&
                url.pathname === '/mahasiswa/jadwal-bimbingan' &&
                response.status() >= 200 &&
                response.status() < 400
            );
        }),
        page.getByRole('button', { name: 'Kirim Permintaan' }).click(),
    ]);
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
            await expect(
                mahasiswaPage.getByText('Mahasiswa SiTA').first(),
            ).toBeVisible();

            await dosenPage.goto('/dosen/pesan-bimbingan');
            await expect(
                dosenPage
                    .getByText(thesisScenarios.activeResearch.studentName)
                    .first(),
            ).toBeVisible();
            await expect(
                dosenPage.getByPlaceholder('Tulis pesan...'),
            ).toBeEnabled();

            await dosenPage.goto('/dosen/dokumen-revisi');
            await expect(
                dosenPage.getByText('Draft Bab 1').first(),
            ).toBeVisible();

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
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
                mahasiswaPage.getByText(documentTitle).first(),
            ).toBeVisible();

            await mahasiswaPage.goto('/mahasiswa/pesan');
            await expect(
                mahasiswaPage.getByText('Dokumen Baru Diunggah').first(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText(documentFileName).first(),
            ).toBeVisible();

            await dosenPage.goto('/dosen/dokumen-revisi');
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

            const mahasiswaPendingRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic })
                .first();
            await expect(mahasiswaPendingRow).toBeVisible();
            await expect(mahasiswaPendingRow).toContainText(
                'Menunggu Konfirmasi',
            );

            await dosenPage.goto('/dosen/jadwal-bimbingan');

            const requestCard = dosenPage
                .getByRole('list', { name: 'Permintaan menunggu konfirmasi' })
                .getByRole('listitem')
                .filter({ hasText: topic });

            await expect(requestCard).toBeVisible();
            await requestCard.getByRole('button', { name: 'Tanggapi' }).click();
            await requestCard
                .locator('textarea[id^="note-"]')
                .fill(lecturerFeedback);
            await requestCard
                .locator('input[id^="location-"]')
                .fill(confirmedLocation);
            await requestCard
                .getByRole('button', { name: 'Konfirmasi' })
                .click();

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

    test('dosen requests revision for an uploaded document and later approves the revised version', async ({
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

        const initialTitle = 'Draft Bab 4 Evaluasi Playwright';
        const revisedTitle = 'Draft Bab 4 Evaluasi Playwright Revisi';
        const revisionNotes =
            'Perjelas interpretasi hasil uji dan tambahkan ringkasan temuan utama.';

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await uploadThesisDocument(
                mahasiswaPage,
                initialTitle,
                'Draft Skripsi',
            );

            await dosenPage.goto('/dosen/dokumen-revisi');
            await dosenPage
                .getByPlaceholder(/Cari mahasiswa.*file/i)
                .fill(initialTitle);

            const initialReviewRow = dosenPage
                .locator('tr')
                .filter({ hasText: thesisScenarios.activeResearch.studentName })
                .filter({ hasText: initialTitle })
                .first();

            await expect(initialReviewRow).toBeVisible();
            await expect(initialReviewRow).toContainText('Perlu Review');
            await initialReviewRow
                .getByRole('button', { name: 'Kirim Catatan Revisi' })
                .click();

            const revisionDialog = dosenPage.getByRole('dialog', {
                name: 'Kirim Catatan Revisi',
            });

            await expect(revisionDialog).toBeVisible();
            await revisionDialog
                .getByRole('textbox', { name: 'Catatan Revisi' })
                .fill(revisionNotes);
            await dosenPage
                .getByRole('button', { name: 'Kirim Catatan' })
                .click();

            await expect(initialReviewRow).toContainText('Perlu Revisi');
            await expect(initialReviewRow).toContainText(revisionNotes);

            await mahasiswaPage.goto('/mahasiswa/upload-dokumen');
            await mahasiswaPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(initialTitle);

            const initialStudentRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: initialTitle })
                .first();

            await expect(initialStudentRow).toBeVisible();
            await expect(initialStudentRow).toContainText('Perlu Revisi');
            await expect(initialStudentRow).toContainText(/ — v\d+/);
            await initialStudentRow
                .getByRole('button', { name: /^Detail/ })
                .click();
            await expect(mahasiswaPage.getByRole('dialog')).toContainText(
                revisionNotes,
            );
            await mahasiswaPage.keyboard.press('Escape');

            await uploadThesisDocument(
                mahasiswaPage,
                revisedTitle,
                'Draft Skripsi',
            );

            await mahasiswaPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(revisedTitle);

            const revisedStudentPendingRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: revisedTitle })
                .first();

            await expect(revisedStudentPendingRow).toBeVisible();
            await expect(revisedStudentPendingRow).toContainText(
                'Menunggu Review',
            );

            await dosenPage.goto('/dosen/dokumen-revisi');
            await dosenPage
                .getByPlaceholder(/Cari mahasiswa.*file/i)
                .fill(revisedTitle);

            const revisedReviewRow = dosenPage
                .locator('tr')
                .filter({ hasText: thesisScenarios.activeResearch.studentName })
                .filter({ hasText: revisedTitle })
                .first();

            await expect(revisedReviewRow).toBeVisible();
            await expect(revisedReviewRow).toContainText('Perlu Review');
            await revisedReviewRow
                .getByRole('button', { name: 'Setujui Dokumen' })
                .click();

            await expect(revisedReviewRow).toContainText('Disetujui');

            await mahasiswaPage.goto('/mahasiswa/upload-dokumen');
            await mahasiswaPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(revisedTitle);

            const revisedStudentApprovedRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: revisedTitle })
                .first();

            await expect(revisedStudentApprovedRow).toBeVisible();
            // Only one of the two supervisors approved: aggregated status is
            // partial, and the drawer shows the per-lecturer approval.
            await expect(revisedStudentApprovedRow).toContainText(
                'Sebagian direview',
            );
            await revisedStudentApprovedRow
                .getByRole('button', { name: /^Detail/ })
                .click();
            await expect(mahasiswaPage.getByRole('dialog')).toContainText(
                'Disetujui',
            );
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('mahasiswa and dosen can exchange new chat messages from the shared bimbingan thread', async ({
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

        const mahasiswaMessage = `Playwright mahasiswa message ${Date.now()}`;
        const dosenMessage = `Playwright dosen reply ${Date.now()}`;

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await openMahasiswaBimbinganThread(mahasiswaPage);
            await openDosenBimbinganThread(
                dosenPage,
                thesisScenarios.activeResearch.studentName,
            );
            await waitForThreadSubscription(mahasiswaPage);
            await waitForThreadSubscription(dosenPage);

            await sendChatMessage(
                mahasiswaPage,
                /\/mahasiswa\/pesan\/\d+\/messages$/,
                mahasiswaMessage,
            );

            await expect(
                dosenPage.getByText(mahasiswaMessage).last(),
            ).toBeVisible({ timeout: 15_000 });
            await waitForThreadSubscription(mahasiswaPage);

            await sendChatMessage(
                dosenPage,
                /\/dosen\/pesan\/\d+\/messages$/,
                dosenMessage,
            );

            await expect(
                mahasiswaPage.getByText(dosenMessage).last(),
            ).toBeVisible({ timeout: 15_000 });
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('mahasiswa sends a document without a caption and its card shows the review status', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });
        const attachmentName = path.basename(
            thesisFixtures.thesisDocumentUploadPath,
        );

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();

            await openMahasiswaBimbinganThread(mahasiswaPage);
            await sendChatAttachment(
                mahasiswaPage,
                /\/mahasiswa\/pesan\/\d+\/messages$/,
                path.join(
                    process.cwd(),
                    thesisFixtures.thesisDocumentUploadPath,
                ),
                null,
            );

            await expect(
                mahasiswaPage.getByText(attachmentName).last(),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByText('Menunggu Review').last(),
            ).toBeVisible();
        } finally {
            await mahasiswaContext.close();
        }
    });

    test('mahasiswa sends an image with a caption; it shows as a thumbnail for both roles and opens in a lightbox', async ({
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

        const caption = `Playwright grafik ${Date.now()}`;
        const imagePath = path.join(
            process.cwd(),
            'tests',
            'fixtures',
            'chat-image.png',
        );

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await openMahasiswaBimbinganThread(mahasiswaPage);
            await openDosenBimbinganThread(
                dosenPage,
                thesisScenarios.activeResearch.studentName,
            );
            await waitForThreadSubscription(dosenPage);

            await sendChatAttachment(
                mahasiswaPage,
                /\/mahasiswa\/pesan\/\d+\/messages$/,
                imagePath,
                caption,
            );

            await expect(mahasiswaPage.getByText(caption).last()).toBeVisible();

            const mahasiswaThumbnail = mahasiswaPage
                .getByRole('button', { name: /^Perbesar/ })
                .last();

            await expect(mahasiswaThumbnail).toBeVisible();
            await mahasiswaThumbnail.click();
            await expect(mahasiswaPage.getByRole('dialog')).toBeVisible();
            await mahasiswaPage.keyboard.press('Escape');
            await expect(mahasiswaPage.getByRole('dialog')).toBeHidden();

            await expect(dosenPage.getByText(caption).last()).toBeVisible({
                timeout: 15_000,
            });
            await expect(
                dosenPage.getByRole('button', { name: /^Perbesar/ }).last(),
            ).toBeVisible({ timeout: 15_000 });
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('a file type the chat does not accept is rejected in the preview panel and cannot be sent', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.activeResearch.studentKey,
            ),
        });

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();

            await openMahasiswaBimbinganThread(mahasiswaPage);
            await mahasiswaPage.locator('input[type="file"]').setInputFiles({
                name: 'catatan.txt',
                mimeType: 'text/plain',
                buffer: Buffer.from('catatan'),
            });

            await expect(
                mahasiswaPage.getByText(
                    'Format file tidak didukung. Gunakan PDF, DOC, DOCX, JPG, PNG, atau WEBP.',
                ),
            ).toBeVisible();
            await expect(
                mahasiswaPage.getByRole('button', { name: 'Kirim pesan' }),
            ).toBeDisabled();
        } finally {
            await mahasiswaContext.close();
        }
    });

    test('dosen can reject a bimbingan request and mahasiswa sees it in history with feedback', async ({
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

        const topic = `Bimbingan ditolak Playwright ${Date.now()}`;
        const rejectionNote =
            'Topik perlu dipersempit dulu sebelum sesi bimbingan dijadwalkan.';
        const requestedFor = toDateTimeLocalString(
            new Date(Date.now() + 4 * 24 * 60 * 60 * 1000),
        );

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await requestBimbinganSchedule(mahasiswaPage, {
                topic,
                requestedFor,
                studentNote:
                    'Mohon masukan terkait fokus pembahasan bab evaluasi.',
            });

            const mahasiswaPendingRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic })
                .first();
            await expect(mahasiswaPendingRow).toBeVisible();
            await expect(mahasiswaPendingRow).toContainText(
                'Menunggu Konfirmasi',
            );

            await dosenPage.goto('/dosen/jadwal-bimbingan');

            const requestCard = dosenPage
                .getByRole('list', { name: 'Permintaan menunggu konfirmasi' })
                .getByRole('listitem')
                .filter({ hasText: topic });
            await expect(requestCard).toBeVisible();
            await requestCard.getByRole('button', { name: 'Tanggapi' }).click();
            await requestCard
                .locator('textarea[id^="note-"]')
                .fill(rejectionNote);
            await requestCard.getByRole('button', { name: 'Tolak' }).click();

            await expect(requestCard).toHaveCount(0);

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');

            const mahasiswaHistoryRow = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic })
                .first();
            await expect(mahasiswaHistoryRow).toBeVisible();
            await expect(mahasiswaHistoryRow).toContainText('Ditolak');
            await expect(mahasiswaHistoryRow).toContainText(rejectionNote);
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('dosen can approve a recurring bimbingan request group and mahasiswa sees all sessions scheduled', async ({
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

        const topic = `Bimbingan berulang Playwright ${Date.now()}`;
        const lecturerNote =
            'Semua sesi berulang disetujui untuk monitoring progres bab akhir.';
        const location = 'Ruang Bimbingan Reguler Playwright';
        const requestedFor = toDateTimeLocalString(
            new Date(Date.now() + 5 * 24 * 60 * 60 * 1000),
        );

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await requestBimbinganSchedule(mahasiswaPage, {
                topic,
                requestedFor,
                studentNote:
                    'Mohon persetujuan untuk seri bimbingan mingguan menjelang finalisasi.',
                recurring: {
                    pattern: 'Mingguan',
                    count: '3',
                },
            });
            const pendingRows = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic });
            await expect(pendingRows).toHaveCount(3);

            await dosenPage.goto('/dosen/jadwal-bimbingan');

            const recurringCard = dosenPage
                .getByRole('list', { name: 'Permintaan menunggu konfirmasi' })
                .getByRole('listitem')
                .filter({ hasText: topic });
            await expect(recurringCard).toBeVisible();
            await expect(recurringCard).toContainText('3 Pertemuan');
            await recurringCard
                .getByRole('button', { name: 'Tanggapi' })
                .click();
            await recurringCard
                .locator('input[id^="recurring-location-"]')
                .fill(location);
            await recurringCard
                .locator('textarea[id^="recurring-note-"]')
                .fill(lecturerNote);
            await recurringCard
                .getByRole('button', { name: 'Konfirmasi Semua' })
                .click();

            await expect(recurringCard).toHaveCount(0);

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');

            const scheduledRows = mahasiswaPage
                .locator('tr')
                .filter({ hasText: topic });

            await expect(scheduledRows).toHaveCount(3);
            await expect(scheduledRows.first()).toContainText('Terjadwal');
            await expect(scheduledRows.first()).toContainText(location);
            await expect(scheduledRows.first()).toContainText(lecturerNote);
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('dosen can manage mahasiswa-bimbingan workspace and open chat from an active student row', async ({
        browser,
    }) => {
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        try {
            const dosenPage = await dosenContext.newPage();

            await dosenPage.goto('/dosen/mahasiswa-bimbingan');

            const activeSection = dosenPage
                .locator('section')
                .filter({ hasText: 'Mahasiswa Aktif' })
                .first();
            const historySection = dosenPage
                .locator('section')
                .filter({ hasText: 'Riwayat Bimbingan' })
                .first();

            await expect(activeSection).toBeVisible();
            await expect(activeSection).toContainText(
                thesisScenarios.activeResearch.studentName,
            );
            await expect(activeSection).toContainText('Pembimbing 1');

            await activeSection
                .getByPlaceholder('Cari nama atau NIM...')
                .fill(thesisScenarios.activeResearch.studentName);
            await activeSection
                .getByRole('radio', { name: /^Pembimbing 1/ })
                .click();

            const activeStudentRow = activeSection
                .locator('tr')
                .filter({
                    hasText: thesisScenarios.activeResearch.studentName,
                })
                .first();

            await expect(activeStudentRow).toBeVisible();

            await activeStudentRow
                .getByRole('button', { name: 'Chat' })
                .click();
            await dosenPage
                .getByRole('menuitem', { name: 'Chat grup' })
                .click();
            await expect(dosenPage).toHaveURL(/\/dosen\/pesan\?thread=/);
            await expect(
                dosenPage
                    .getByText(thesisScenarios.activeResearch.studentName)
                    .first(),
            ).toBeVisible();
            await expect(
                dosenPage.getByPlaceholder('Tulis pesan...'),
            ).toBeEnabled();

            await dosenPage.goto('/dosen/mahasiswa-bimbingan');
            await expect(historySection).toBeVisible();
            await expect(
                historySection.getByRole('link', { name: 'Chat' }),
            ).toHaveCount(0);
        } finally {
            await dosenContext.close();
        }
    });
});

test.describe('Sidang chat group before the sidang is completed', () => {
    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('mahasiswa sees only the supervisors in the sidang group while the extra examiner stays out of it', async ({
        browser,
    }) => {
        const sidang = thesisScenarios.sidangWithPanel;
        const studentContext = await browser.newContext({
            storageState: authStatePath(sidang.studentKey),
        });
        const examinerContext = await browser.newContext({
            storageState: authStatePath(sidang.panelKeys[2]),
        });

        // The props of the initial Inertia payload, as the page rendered them.
        async function inertiaProps(
            page: Page,
            url: string,
        ): Promise<{ threads: Array<Record<string, unknown>> }> {
            await page.goto(url);

            return page.locator('#app').evaluate((element) => {
                const payload = element.getAttribute('data-page');
                if (payload === null) {
                    throw new Error('Page is missing its Inertia payload');
                }

                return JSON.parse(payload).props;
            });
        }

        try {
            const studentPage = await studentContext.newPage();
            const examinerPage = await examinerContext.newPage();

            const studentProps = await inertiaProps(
                studentPage,
                '/mahasiswa/pesan',
            );
            const studentSidangThread = studentProps.threads.find(
                (thread) => thread.threadType === 'sidang',
            );

            expect(studentSidangThread).toBeTruthy();
            expect(studentSidangThread?.members).toEqual(
                expect.arrayContaining([
                    sidang.studentName,
                    'Dr. Budi Santoso, M.Kom.',
                    'Dr. Ratna Kusuma, M.Kom.',
                ]),
            );
            expect(JSON.stringify(studentSidangThread)).not.toContain(
                'Prof. Ahmad Hidayat, Ph.D.',
            );

            // The extra examiner (dosen3) still sees the student's sidang in the portal,
            // but the sidang group is not in their chat list yet.
            await examinerPage.goto('/dosen/seminar-proposal');
            await expect(
                examinerPage.getByText(sidang.studentName).first(),
            ).toBeVisible();

            const examinerProps = await inertiaProps(
                examinerPage,
                '/dosen/pesan',
            );
            expect(
                examinerProps.threads.some(
                    (thread) =>
                        thread.threadType === 'sidang' &&
                        thread.student === sidang.studentName,
                ),
            ).toBe(false);
        } finally {
            await studentContext.close();
            await examinerContext.close();
        }
    });
});
