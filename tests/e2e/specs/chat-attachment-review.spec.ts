import fs from 'node:fs';
import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import {
    openDocumentDetail,
    seedDocumentDetailScenario,
    submitDocumentReview,
} from '../support/document-detail';
import {
    authStatePath,
    thesisFixtures,
    thesisScenarios,
} from '../support/thesis-fixtures';

const student = thesisScenarios.twoSupervisors;
const attachmentName = 'chat-review-exact-copy.pdf';
const attachmentTitle = 'Lampiran chat untuk dua pembimbing';

async function openThread(page: Page, lecturer = false): Promise<void> {
    await page.goto(lecturer ? '/dosen/pesan-bimbingan' : '/mahasiswa/pesan');
    await page
        .locator('button')
        .filter({ hasText: 'Bimbingan' })
        .first()
        .click();
    if (lecturer) {
        await page
            .locator('button')
            .filter({ hasText: student.studentName })
            .first()
            .click();
    }
    await expect(page.getByPlaceholder('Tulis pesan...')).toBeEnabled();
}

async function sendAttachment(page: Page, name: string): Promise<void> {
    await openThread(page);
    await page.locator('input[type="file"]').setInputFiles({
        name,
        mimeType: 'application/pdf',
        buffer: fs.readFileSync(
            path.join(process.cwd(), thesisFixtures.thesisDocumentUploadPath),
        ),
    });
    await page.getByLabel('Nama dokumen').fill(attachmentTitle);
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                response.url().includes('/mahasiswa/pesan/'),
        ),
        page.getByPlaceholder('Tulis pesan...').press('Enter'),
    ]);
}

test.describe('Chat attachment stable document reviews', () => {
    test.beforeEach(() => seedDocumentDetailScenario());

    test('second reviewer follows the exact chat copy, not another reviewer or newer attachment', async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
        });
        const reviewerContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const otherReviewerContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });
        try {
            const studentPage = await studentContext.newPage();
            await sendAttachment(studentPage, attachmentName);
            await sendAttachment(studentPage, 'chat-review-newer-copy.pdf');
            const reviewerPage = await reviewerContext.newPage();
            await openThread(reviewerPage, true);
            const reviewLink = reviewerPage.getByRole('link', {
                name: `Tinjau ${attachmentName}`,
                exact: true,
            });
            const reviewUrl = await reviewLink.getAttribute('href');
            expect(new URL(reviewUrl!, reviewerPage.url()).pathname).toMatch(
                /^\/dosen\/dokumen-revisi\/\d+$/,
            );
            await reviewLink.click();
            await expect(reviewerPage).toHaveURL(new RegExp(`${reviewUrl}$`));
            await expect(
                reviewerPage.getByRole('heading', {
                    name: 'Detail dokumen',
                    exact: true,
                }),
            ).toBeVisible();
            await expect(
                reviewerPage.getByText(attachmentName, { exact: true }).first(),
            ).toBeVisible();
            await submitDocumentReview(
                reviewerPage,
                'needs_revision',
                'Tambahkan sumber untuk lampiran pertama.',
            );
            await reviewerPage.reload();
            await expect(
                reviewerPage.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveValue('Tambahkan sumber untuk lampiran pertama.');

            // A reviewer ID is not a shared upload ID. Another authorized
            // supervisor still cannot open or submit this reviewer's copy.
            const denied = await otherReviewerContext.request.get(reviewUrl!);
            expect([403, 404]).toContain(denied.status());

            await openThread(reviewerPage, true);
            await expect(
                reviewerPage
                    .getByText(/menandai lampiran .* perlu revisi/i)
                    .first(),
            ).toBeVisible();
            await expect(
                reviewerPage.getByRole('link', {
                    name: `Tinjau ${attachmentName}`,
                    exact: true,
                }),
            ).toHaveCount(0);
            await expect(
                reviewerPage.getByRole('link', {
                    name: 'Tinjau chat-review-newer-copy.pdf',
                    exact: true,
                }),
            ).toBeVisible();

            await studentPage.goto('/mahasiswa/upload-dokumen');
            await studentPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(attachmentName);
            const row = studentPage
                .locator('tr')
                .filter({ hasText: attachmentName })
                .first();
            await expect(row).toContainText('Perlu Revisi');
            await expect(row).toContainText('2 review');
            await openDocumentDetail(
                studentPage,
                row,
                '/mahasiswa/upload-dokumen',
            );
            await expect(
                studentPage.getByText(
                    'Tambahkan sumber untuk lampiran pertama.',
                    { exact: true },
                ),
            ).toBeVisible();
            await expect(
                studentPage.getByRole('button', {
                    name: 'Minta revisi',
                    exact: true,
                }),
            ).toHaveCount(0);
            await expect(
                studentPage.getByRole('heading', {
                    name: 'Riwayat versi',
                    exact: true,
                }),
            ).toBeVisible();
        } finally {
            await studentContext.close();
            await reviewerContext.close();
            await otherReviewerContext.close();
        }
    });

    test('second reviewer opens a chat upload from the document list and student can reload the detail', async ({
        browser,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
        });
        const reviewerContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        try {
            const studentPage = await studentContext.newPage();
            await sendAttachment(studentPage, attachmentName);
            const reviewerPage = await reviewerContext.newPage();
            await reviewerPage.goto('/dosen/dokumen-revisi');
            await reviewerPage
                .getByPlaceholder(/Cari mahasiswa.*file/i)
                .fill(attachmentName);
            const row = reviewerPage
                .locator('tr')
                .filter({ hasText: student.studentName })
                .filter({ hasText: attachmentName })
                .first();
            await openDocumentDetail(
                reviewerPage,
                row,
                '/dosen/dokumen-revisi',
            );
            await submitDocumentReview(
                reviewerPage,
                'needs_revision',
                'Perbaiki sitasi pada bab pembuka.',
            );
            await studentPage.goto('/mahasiswa/upload-dokumen');
            await studentPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(attachmentName);
            const studentRow = studentPage
                .locator('tr')
                .filter({ hasText: attachmentName })
                .first();
            await expect(studentRow).toContainText('Perlu Revisi');
            const url = await openDocumentDetail(
                studentPage,
                studentRow,
                '/mahasiswa/upload-dokumen',
            );
            await studentPage.reload();
            await expect(studentPage).toHaveURL(new RegExp(`${url}$`));
            await expect(
                studentPage.getByText('Perbaiki sitasi pada bab pembuka.', {
                    exact: true,
                }),
            ).toBeVisible();
            await studentPage.goBack();
            await expect(studentPage).toHaveURL(
                /\/mahasiswa\/upload-dokumen(?:\?.*)?$/,
            );
        } finally {
            await studentContext.close();
            await reviewerContext.close();
        }
    });
});
