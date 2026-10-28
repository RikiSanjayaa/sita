import fs from 'node:fs';
import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import {
    attachDocumentStates,
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
const title = 'Draft penelitian halaman dokumen';
const revision = 'Jelaskan metode pengambilan sampel dan sumber datanya.';
const suggestion = 'Sudah layak. Pertahankan penjelasan batasan penelitian.';
const uploadBytes = fs.readFileSync(
    path.join(process.cwd(), thesisFixtures.thesisDocumentUploadPath),
);

async function uploadVersion(page: Page, version: number): Promise<void> {
    await page.goto('/mahasiswa/upload-dokumen?open=unggah');
    const dialog = page.getByRole('dialog', { name: 'Upload Dokumen' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Judul Dokumen').fill(title);
    await dialog.getByLabel('Kategori Dokumen').click();
    await page
        .getByRole('option', { name: 'Draft Skripsi', exact: true })
        .click();
    await dialog.getByLabel('File Dokumen').setInputFiles({
        name: `draft-detail-v${version}.pdf`,
        mimeType: 'application/pdf',
        buffer: Buffer.concat([
            uploadBytes,
            Buffer.from(`\n% version ${version}\n`),
        ]),
    });
    await dialog.getByRole('button', { name: 'Upload', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(
        page
            .locator('tr')
            .filter({ hasText: `draft-detail-v${version}.pdf` })
            .first(),
    ).toBeVisible();
}

async function openReviewer(page: Page): Promise<string> {
    await page.goto('/dosen/dokumen-revisi');
    await page.getByPlaceholder(/Cari mahasiswa.*file/i).fill(title);
    return openDocumentDetail(
        page,
        page
            .locator('tr')
            .filter({ hasText: student.studentName })
            .filter({ hasText: 'draft-detail-v1.pdf' })
            .first(),
        '/dosen/dokumen-revisi',
    );
}

test.describe('Stable document detail journey (#152)', () => {
    test.beforeEach(() => seedDocumentDetailScenario());

    for (const width of [1280, 390]) {
        test(`review decisions follow the completed notes and revision editor at ${width}px (#175)`, async ({
            browser,
        }, testInfo) => {
            const studentContext = await browser.newContext({
                storageState: authStatePath(student.studentKey),
            });
            const reviewerContext = await browser.newContext({
                storageState: authStatePath('dosen2'),
                viewport: { width, height: 844 },
            });
            try {
                const studentPage = await studentContext.newPage();
                await uploadVersion(studentPage, 1);
                const reviewer = await reviewerContext.newPage();
                await openReviewer(reviewer);
                await reviewer
                    .getByRole('textbox', {
                        name: 'Catatan dosen',
                        exact: true,
                    })
                    .fill(revision);
                for (let index = 1; index <= 3; index += 1) {
                    await reviewer
                        .getByRole('button', {
                            name: 'Tambah butir',
                            exact: true,
                        })
                        .click();
                    await reviewer
                        .getByRole('textbox', {
                            name: `Butir ${index}`,
                            exact: true,
                        })
                        .fill(
                            `Perbaikan ${index}: jelaskan dasar dan hasil pengujian.`,
                        );
                }
                const lastItem = reviewer.getByRole('textbox', {
                    name: 'Butir 3',
                    exact: true,
                });
                await lastItem.focus();
                await lastItem.press('Tab');
                await expect(
                    reviewer.getByRole('button', {
                        name: 'Tambah butir',
                        exact: true,
                    }),
                ).toBeFocused();
                await reviewer.keyboard.press('Tab');
                const decision = reviewer.getByRole('button', {
                    name: 'Minta revisi',
                    exact: true,
                });
                await expect(decision).toBeFocused();
                expect(
                    await decision.evaluate(
                        (element) => element.getBoundingClientRect().top,
                    ),
                ).toBeGreaterThanOrEqual(48);
                const editorBottom = await lastItem.evaluate(
                    (element) =>
                        element.getBoundingClientRect().bottom + scrollY,
                );
                const decisionTop = await decision.evaluate(
                    (element) => element.getBoundingClientRect().top + scrollY,
                );
                expect(decisionTop).toBeGreaterThan(editorBottom);
                await attachDocumentStates(
                    reviewer,
                    testInfo,
                    `review-decisions-after-editor-${width}`,
                );
                await Promise.all([
                    reviewer.waitForResponse(
                        (response) =>
                            response.request().method() === 'POST' &&
                            /\/dosen\/dokumen-revisi\/\d+\/review$/.test(
                                response.url(),
                            ),
                    ),
                    decision.click(),
                ]);
                await expect(
                    reviewer.getByRole('textbox', {
                        name: 'Butir 3',
                        exact: true,
                    }),
                ).toHaveValue(
                    'Perbaikan 3: jelaskan dasar dan hasil pengujian.',
                );
                await studentPage.goto('/mahasiswa/upload-dokumen');
                await studentPage.getByPlaceholder(/Cari/).fill(title);
                await openDocumentDetail(
                    studentPage,
                    studentPage
                        .locator('tr')
                        .filter({ hasText: 'draft-detail-v1.pdf' })
                        .first(),
                    '/mahasiswa/upload-dokumen',
                );
                await expect(
                    studentPage.getByText('Perlu revisi', { exact: true }),
                ).toBeVisible();
                await expect(
                    studentPage.getByText(
                        'Perbaikan 3: jelaskan dasar dan hasil pengujian.',
                        { exact: true },
                    ),
                ).toBeVisible();
            } finally {
                await reviewerContext.close();
                await studentContext.close();
            }
        });
    }

    test('two reviewers save required revision notes and approval suggestions, preserving exact versions and drafts', async ({
        browser,
    }, testInfo) => {
        test.setTimeout(180_000);
        const contexts = await Promise.all([
            browser.newContext({
                storageState: authStatePath(student.studentKey),
            }),
            browser.newContext({ storageState: authStatePath('dosen2') }),
            browser.newContext({ storageState: authStatePath('dosen1') }),
            browser.newContext({ storageState: authStatePath('kaprodi') }),
            browser.newContext({ storageState: authStatePath('dosen3') }),
            browser.newContext({ storageState: authStatePath('akbar') }),
        ]);
        try {
            const [studentPage, secondReviewer, firstReviewer, kaprodi] =
                await Promise.all(
                    contexts.slice(0, 4).map((context) => context.newPage()),
                );
            await uploadVersion(studentPage, 1);

            // The upload notification is the entry point, and targets this
            // second reviewer's copy rather than the first reviewer's ID.
            await secondReviewer.goto('/dosen/pesan-bimbingan');
            await secondReviewer
                .locator('button')
                .filter({ hasText: 'Bimbingan' })
                .first()
                .click();
            await secondReviewer
                .locator('button')
                .filter({ hasText: student.studentName })
                .first()
                .click();
            const chatUrl = secondReviewer.url();
            const reviewLink = secondReviewer
                .getByRole('link', {
                    name: 'Tinjau draft-detail-v1.pdf',
                    exact: true,
                })
                .last();
            const secondUrl = await reviewLink.getAttribute('href');
            expect(new URL(secondUrl!, secondReviewer.url()).pathname).toMatch(
                /^\/dosen\/dokumen-revisi\/\d+$/,
            );
            await reviewLink.click();
            await expect(secondReviewer).toHaveURL(new RegExp(`${secondUrl}$`));
            await expect(
                secondReviewer
                    .getByText('draft-detail-v1.pdf', { exact: true })
                    .first(),
            ).toBeVisible();
            await attachDocumentStates(
                secondReviewer,
                testInfo,
                'dosen-detail-pending',
            );

            let reviewPosts = 0;
            secondReviewer.on('request', (request) => {
                if (
                    request.method() === 'POST' &&
                    /\/dosen\/dokumen-revisi\/\d+\/review$/.test(request.url())
                )
                    reviewPosts += 1;
            });
            await secondReviewer
                .getByRole('button', { name: 'Minta revisi', exact: true })
                .click();
            await expect(
                secondReviewer.getByText(
                    'Catatan revisi wajib diisi saat meminta revisi.',
                    { exact: true },
                ),
            ).toBeVisible();
            expect(reviewPosts).toBe(1);
            await attachDocumentStates(
                secondReviewer,
                testInfo,
                'dosen-detail-revision-required',
            );
            await secondReviewer
                .getByRole('textbox', { name: 'Catatan dosen' })
                .fill(revision);

            secondReviewer.once('dialog', (dialog) => dialog.dismiss());
            await secondReviewer
                .getByRole('link', { name: /Kembali/ })
                .first()
                .click();
            await expect(secondReviewer).toHaveURL(new RegExp(`${secondUrl}$`));
            await expect(
                secondReviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveValue(revision);

            secondReviewer.once('dialog', (dialog) => dialog.dismiss());
            await secondReviewer.goBack();
            await expect(secondReviewer).toHaveURL(new RegExp(`${secondUrl}$`));
            await expect(
                secondReviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveValue(revision);
            await attachDocumentStates(
                secondReviewer,
                testInfo,
                'dosen-detail-dirty-draft',
            );
            await submitDocumentReview(
                secondReviewer,
                'needs_revision',
                revision,
            );
            expect(reviewPosts).toBe(2);
            await secondReviewer.reload();
            await expect(
                secondReviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveValue(revision);
            expect(reviewPosts).toBe(2);
            await attachDocumentStates(
                secondReviewer,
                testInfo,
                'dosen-detail-needs-revision',
            );

            const firstUrl = await openReviewer(firstReviewer);
            expect(firstUrl).not.toBe(secondUrl);
            await submitDocumentReview(firstReviewer, 'approved', suggestion);
            await firstReviewer.reload();
            await expect(
                firstReviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveValue(suggestion);
            await attachDocumentStates(
                firstReviewer,
                testInfo,
                'dosen-detail-approved-with-suggestion',
            );

            const otherReviewerResponse = await contexts[4].request.get(
                secondUrl!,
            );
            expect([403, 404]).toContain(otherReviewerResponse.status());
            const firstReviewerForeignResponse = await contexts[2].request.get(
                secondUrl!,
            );
            expect([403, 404]).toContain(firstReviewerForeignResponse.status());

            await studentPage.goto('/mahasiswa/upload-dokumen');
            await studentPage
                .getByPlaceholder('Cari judul atau file...')
                .fill(title);
            const studentRow = studentPage
                .locator('tr')
                .filter({ hasText: 'draft-detail-v1.pdf' })
                .first();
            await expect(studentRow).toContainText('Perlu Revisi');
            const studentUrl = await openDocumentDetail(
                studentPage,
                studentRow,
                '/mahasiswa/upload-dokumen',
            );
            await expect(
                studentPage.getByText(revision, { exact: true }),
            ).toBeVisible();
            await expect(
                studentPage.getByText(suggestion, { exact: true }),
            ).toBeVisible();
            await expect(
                studentPage.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveCount(0);
            await attachDocumentStates(
                studentPage,
                testInfo,
                'mahasiswa-detail-both-review-notes',
            );
            const foreignStudentResponse =
                await contexts[5].request.get(studentUrl);
            expect([403, 404]).toContain(foreignStudentResponse.status());

            await uploadVersion(studentPage, 2);
            const latestUrl = await openDocumentDetail(
                studentPage,
                studentPage
                    .locator('tr')
                    .filter({ hasText: 'draft-detail-v2.pdf' })
                    .first(),
                '/mahasiswa/upload-dokumen',
            );
            await expect(
                studentPage.getByRole('heading', {
                    name: 'Riwayat versi',
                    exact: true,
                }),
            ).toBeVisible();
            const oldVersion = studentPage
                .locator(`a[href="${studentUrl}"]`)
                .filter({ hasText: 'draft-detail-v1.pdf' });
            await expect(oldVersion).toBeVisible();
            await oldVersion.click();
            await expect(studentPage).toHaveURL(new RegExp(`${studentUrl}$`));
            await expect(
                studentPage.getByText(revision, { exact: true }),
            ).toBeVisible();
            await expect(
                studentPage.getByText(suggestion, { exact: true }),
            ).toBeVisible();
            await studentPage.reload();
            await expect(studentPage).toHaveURL(new RegExp(`${studentUrl}$`));
            await studentPage.goBack();
            await expect(studentPage).toHaveURL(new RegExp(`${latestUrl}$`));
            await attachDocumentStates(
                studentPage,
                testInfo,
                'mahasiswa-detail-version-history',
            );

            await secondReviewer.goto(secondUrl!);
            await expect(
                secondReviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveCount(0);
            await attachDocumentStates(
                secondReviewer,
                testInfo,
                'dosen-detail-old-version-readonly',
            );

            await kaprodi.goto('/kaprodi/dokumen');
            await kaprodi
                .getByPlaceholder('Cari mahasiswa, NIM, file, atau dosen...')
                .fill(title);
            const kaprodiRow = kaprodi
                .locator('tr')
                .filter({ hasText: student.studentName })
                .filter({ hasText: 'draft-detail-v1.pdf' })
                .first();
            await openDocumentDetail(
                kaprodi,
                kaprodiRow,
                '/kaprodi/dokumen/workspace',
            );
            await expect(
                kaprodi.getByText(student.studentName, { exact: true }).first(),
            ).toBeVisible();
            await expect(
                kaprodi.getByText(revision, { exact: true }),
            ).toBeVisible();
            await expect(
                kaprodi.getByText(suggestion, { exact: true }),
            ).toBeVisible();
            await expect(
                kaprodi.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveCount(0);
            await expect(
                kaprodi.getByRole('button', { name: 'Setujui', exact: true }),
            ).toHaveCount(0);
            await attachDocumentStates(
                kaprodi,
                testInfo,
                'kaprodi-detail-readonly',
            );

            await secondReviewer.goto(chatUrl);
            await expect(
                secondReviewer.getByPlaceholder('Tulis pesan...'),
            ).toBeEnabled();
        } finally {
            await Promise.all(contexts.map((context) => context.close()));
        }
    });

    test('stale document URLs stay read-only after expiry/archive and reject a foreign prodi', async ({
        browser,
    }, testInfo) => {
        test.setTimeout(120_000);
        const studentContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
        });
        const reviewerContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });
        const kaprodiContext = await browser.newContext({
            storageState: authStatePath('kaprodi'),
        });
        try {
            const studentPage = await studentContext.newPage();
            await uploadVersion(studentPage, 1);
            const reviewer = await reviewerContext.newPage();
            const reviewerUrl = await openReviewer(reviewer);
            await expect(
                reviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toBeVisible();
            const kaprodi = await kaprodiContext.newPage();
            await kaprodi.goto('/kaprodi/dokumen');
            await kaprodi
                .getByPlaceholder('Cari mahasiswa, NIM, file, atau dosen...')
                .fill(title);
            const kaprodiUrl = await openDocumentDetail(
                kaprodi,
                kaprodi
                    .locator('tr')
                    .filter({ hasText: student.studentName })
                    .filter({ hasText: 'draft-detail-v1.pdf' })
                    .first(),
                '/kaprodi/dokumen/workspace',
            );

            // Simulate external admin changes while retaining the original URL.
            runArtisan([
                'tinker',
                `--execute=$student = App\\Models\\User::query()->where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); $project = App\\Models\\ThesisProject::query()->where('student_user_id', $student->id)->latest('id')->firstOrFail(); $project->forceFill(['mentorship_valid_until' => now()->subDay()->toDateString()])->save();`,
            ]);
            await reviewer.goto(reviewerUrl);
            await expect(
                reviewer.getByRole('textbox', { name: 'Catatan dosen' }),
            ).toHaveCount(0);
            await expect(
                reviewer.getByRole('button', {
                    name: 'Minta revisi',
                    exact: true,
                }),
            ).toHaveCount(0);
            await attachDocumentStates(
                reviewer,
                testInfo,
                'dosen-detail-expired-readonly',
            );

            runArtisan([
                'tinker',
                `--execute=$student = App\\Models\\User::query()->where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); $project = App\\Models\\ThesisProject::query()->where('student_user_id', $student->id)->latest('id')->firstOrFail(); $project->forceFill(['archived_at' => now()])->save();`,
            ]);
            await reviewer.reload();
            await expect(
                reviewer.getByRole('button', { name: 'Setujui', exact: true }),
            ).toHaveCount(0);
            await attachDocumentStates(
                reviewer,
                testInfo,
                'dosen-detail-archived-readonly',
            );

            runArtisan([
                'tinker',
                `--execute=$student = App\\Models\\User::query()->where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); $foreign = App\\Models\\ProgramStudi::query()->where('slug', '!=', 'ilkom')->firstOrFail(); $student->mahasiswaProfile->forceFill(['program_studi_id' => $foreign->id])->save();`,
            ]);
            const denied = await kaprodiContext.request.get(kaprodiUrl);
            expect([403, 404]).toContain(denied.status());
        } finally {
            await Promise.all([
                studentContext.close(),
                reviewerContext.close(),
                kaprodiContext.close(),
            ]);
            seedDocumentDetailScenario();
        }
    });
    test('owner resolves and reopens reviewer points without another upload (#158)', async ({
        browser,
    }, testInfo) => {
        test.setTimeout(240_000);
        const contexts = await Promise.all([
            browser.newContext({
                storageState: authStatePath(student.studentKey),
            }),
            browser.newContext({ storageState: authStatePath('dosen1') }),
            browser.newContext({ storageState: authStatePath('dosen2') }),
            browser.newContext({ storageState: authStatePath('kaprodi') }),
        ]);
        try {
            const [owner, first, second, kaprodi] = await Promise.all(
                contexts.map((context) => context.newPage()),
            );
            await uploadVersion(owner, 1);
            const firstUrl = await openReviewer(first);
            await first
                .getByRole('button', { name: 'Tambah butir', exact: true })
                .click();
            await first
                .getByRole('textbox', { name: 'Butir 1', exact: true })
                .fill('Jelaskan sumber data penelitian.');
            await first
                .getByRole('button', { name: 'Tambah butir', exact: true })
                .click();
            await first
                .getByRole('textbox', { name: 'Butir 2', exact: true })
                .fill('Lengkapi satuan pada tabel hasil.');
            await first
                .getByRole('button', { name: 'Minta revisi', exact: true })
                .click();
            await expect(
                first.getByText('Perlu Revisi', { exact: true }).first(),
            ).toBeVisible();
            await attachDocumentStates(
                first,
                testInfo,
                'dosen-structured-revision-open',
            );
            await openReviewer(second);
            await submitDocumentReview(second, 'needs_revision', revision);
            await owner.goto('/mahasiswa/upload-dokumen');
            const ownerUrl = await openDocumentDetail(
                owner,
                owner
                    .locator('tr')
                    .filter({ hasText: 'draft-detail-v1.pdf' })
                    .first(),
                '/mahasiswa/upload-dokumen',
            );
            await expect(
                owner.getByRole('button', {
                    name: 'Tandai selesai',
                    exact: true,
                }),
            ).toHaveCount(3);
            await attachDocumentStates(
                owner,
                testInfo,
                'mahasiswa-revision-open',
            );
            let uploads = 0;
            owner.on('request', (request) => {
                if (
                    request.method() === 'POST' &&
                    new URL(request.url()).pathname ===
                        '/mahasiswa/upload-dokumen'
                )
                    uploads++;
            });
            await owner
                .getByRole('button', { name: 'Tandai selesai', exact: true })
                .first()
                .click();
            await expect(
                owner.getByRole('button', {
                    name: 'Tandai selesai',
                    exact: true,
                }),
            ).toHaveCount(2);
            await owner
                .getByRole('button', { name: 'Tandai selesai', exact: true })
                .first()
                .click();
            await expect(
                owner.getByRole('button', {
                    name: 'Tandai selesai',
                    exact: true,
                }),
            ).toHaveCount(1);
            await first.goto(firstUrl);
            await expect(
                first.getByText('Revisi selesai', { exact: true }).first(),
            ).toBeVisible();
            await expect(first.getByText(/^Diselesaikan /)).toHaveCount(2);
            await expect(
                first.getByRole('button', {
                    name: 'Buka kembali',
                    exact: true,
                }),
            ).toHaveCount(0);
            await owner
                .getByRole('button', { name: 'Tandai selesai', exact: true })
                .click();
            await expect(
                owner.getByRole('button', {
                    name: 'Tandai selesai',
                    exact: true,
                }),
            ).toHaveCount(0);
            await expect(
                owner.getByText('Revisi selesai', { exact: true }).first(),
            ).toBeVisible();
            await attachDocumentStates(
                owner,
                testInfo,
                'mahasiswa-revision-completed',
            );
            await first.goto(firstUrl);
            await attachDocumentStates(
                first,
                testInfo,
                'dosen-revision-completed',
            );
            await kaprodi.goto('/kaprodi/dokumen');
            await kaprodi.getByPlaceholder(/Cari mahasiswa.*file/i).fill(title);
            await attachDocumentStates(
                kaprodi,
                testInfo,
                'kaprodi-revision-completed-list',
            );
            await openDocumentDetail(
                kaprodi,
                kaprodi
                    .locator('tr')
                    .filter({ hasText: 'draft-detail-v1.pdf' })
                    .first(),
                '/kaprodi/dokumen/workspace',
            );
            await expect(
                kaprodi.getByRole('button', {
                    name: 'Buka kembali',
                    exact: true,
                }),
            ).toHaveCount(0);
            await attachDocumentStates(
                kaprodi,
                testInfo,
                'kaprodi-revision-completed-detail',
            );
            await owner.goto('/mahasiswa/upload-dokumen');
            await expect(
                owner
                    .locator('tr')
                    .filter({ hasText: 'draft-detail-v1.pdf' })
                    .first(),
            ).toContainText('Revisi selesai');
            await attachDocumentStates(
                owner,
                testInfo,
                'mahasiswa-revision-completed-list',
            );
            await first.goto('/dosen/dokumen-revisi');
            await first.getByPlaceholder(/Cari mahasiswa.*file/i).fill(title);
            await expect(
                first
                    .locator('tr')
                    .filter({ hasText: 'draft-detail-v1.pdf' })
                    .first(),
            ).toContainText('Revisi selesai');
            await attachDocumentStates(
                first,
                testInfo,
                'dosen-revision-completed-list',
            );
            await owner.goto(ownerUrl);
            await owner
                .getByRole('button', { name: 'Buka kembali', exact: true })
                .first()
                .click();
            await expect(
                owner.getByRole('button', {
                    name: 'Tandai selesai',
                    exact: true,
                }),
            ).toHaveCount(1);
            await first.goto(firstUrl);
            await expect(
                first.getByText('Perlu Revisi', { exact: true }).first(),
            ).toBeVisible();
            expect(uploads).toBe(0);
        } finally {
            await Promise.all(contexts.map((context) => context.close()));
            seedDocumentDetailScenario();
        }
    });
});
