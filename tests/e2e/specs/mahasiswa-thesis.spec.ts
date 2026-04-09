import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import {
    authStatePath,
    thesisFixtures,
    thesisScenarios,
} from '../support/thesis-fixtures';

function seedWorkspaceDocument(
    studentEmail: string,
    {
        title,
        category,
        fileName,
    }: {
        title: string;
        category: 'proposal' | 'final-manuscript' | 'lampiran-sidang';
        fileName: string;
    },
): void {
    runArtisan([
        'tinker',
        `--execute=$student = App\\Models\\User::query()->where("email", "${studentEmail}")->firstOrFail(); $path = "documents/mahasiswa/{$student->id}/${category}/${fileName}"; Illuminate\\Support\\Facades\\Storage::disk("public")->put($path, "playwright-${fileName}"); App\\Models\\MentorshipDocument::query()->create(["student_user_id" => $student->id, "lecturer_user_id" => null, "mentorship_assignment_id" => null, "title" => "${title}", "category" => "${category}", "document_group" => "{$student->id}:${category}:".Illuminate\\Support\\Str::uuid(), "version_number" => 1, "file_name" => "${fileName}", "file_url" => null, "storage_disk" => "public", "storage_path" => $path, "stored_file_name" => "${fileName}", "mime_type" => "application/pdf", "file_size_kb" => 1, "status" => "submitted", "revision_notes" => null, "reviewed_at" => null, "uploaded_by_user_id" => $student->id, "uploaded_by_role" => "mahasiswa"]);`,
    ]);
}

function semproDocumentSection(page: Page) {
    return page
        .locator('section')
        .filter({ hasText: 'Dokumen Seminar Proposal' });
}

function sidangDocumentSection(page: Page) {
    return page.locator('section').filter({ hasText: 'Dokumen Sidang' });
}

function assertSemproSnapshotSelected(
    studentEmail: string,
    fileName: string,
): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $defense = $project->semproDefenses()->latest("attempt_no")->firstOrFail(); throw_unless($defense->documents()->where("kind", "proposal")->where("file_name", "${fileName}")->exists(), new Exception("Sempro snapshot not found"));`,
    ]);
}

function assertSidangSnapshotsSelected(
    studentEmail: string,
    mainFileName: string,
    supportingFileNames: string[],
): void {
    const supportingList = supportingFileNames
        .map((name) => `"${name}"`)
        .join(', ');

    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $defense = $project->sidangDefenses()->latest("attempt_no")->firstOrFail(); throw_unless($defense->documents()->where("kind", "final_manuscript")->where("file_name", "${mainFileName}")->exists(), new Exception("Sidang main snapshot not found")); foreach ([${supportingList}] as $name) { throw_unless($defense->documents()->where("kind", "supporting_document")->where("file_name", $name)->exists(), new Exception("Sidang supporting snapshot missing: ".$name)); }`,
    ]);
}

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
            storageState: authStatePath('superadmin'),
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

    test('mahasiswa selects a workspace proposal for the active sempro attempt', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.semproScheduled.studentKey,
            ),
        });

        const semproFileName = 'proposal-sempro-playwright.pdf';

        try {
            const page = await mahasiswaContext.newPage();

            seedWorkspaceDocument('akbar@sita.test', {
                title: 'Proposal Sempro Playwright',
                category: 'proposal',
                fileName: semproFileName,
            });

            await page.goto('/mahasiswa/upload-dokumen');
            await expect(page).toHaveTitle(/Upload Dokumen/i);
            await expect(
                page.getByText('Proposal Sempro Playwright').first(),
            ).toBeVisible();

            await page.goto('/mahasiswa/tugas-akhir');
            await expect(page).toHaveTitle(/Tugas Akhir/i);

            const semproSection = semproDocumentSection(page);
            await expect(semproSection).toBeVisible();

            await semproSection
                .getByPlaceholder('Cari file utama...')
                .fill('Proposal Sempro Playwright');
            await semproSection
                .getByRole('button')
                .filter({ hasText: semproFileName })
                .first()
                .click();
            await semproSection
                .getByRole('button', { name: 'Simpan Dokumen' })
                .click();

            await expect(
                page.getByText(
                    'Dokumen sempro berhasil diperbarui dari workspace file.',
                ),
            ).toBeVisible();

            await page.reload();
            await expect(
                semproSection.getByText(semproFileName).first(),
            ).toBeVisible();

            assertSemproSnapshotSelected('akbar@sita.test', semproFileName);
        } finally {
            await mahasiswaContext.close();
        }
    });

    test('mahasiswa selects workspace files for the active sidang attempt', async ({
        browser,
    }) => {
        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(
                thesisScenarios.sidangScheduled.studentKey,
            ),
        });

        const mainFileName = 'naskah-akhir-playwright.pdf';
        const supportingFileNames = [
            'lampiran-sidang-a-playwright.pdf',
            'lampiran-sidang-b-playwright.pdf',
        ];

        try {
            const page = await mahasiswaContext.newPage();

            seedWorkspaceDocument('putra@sita.test', {
                title: 'Naskah Akhir Playwright',
                category: 'final-manuscript',
                fileName: mainFileName,
            });
            seedWorkspaceDocument('putra@sita.test', {
                title: 'Lampiran Sidang A Playwright',
                category: 'lampiran-sidang',
                fileName: supportingFileNames[0],
            });
            seedWorkspaceDocument('putra@sita.test', {
                title: 'Lampiran Sidang B Playwright',
                category: 'lampiran-sidang',
                fileName: supportingFileNames[1],
            });

            await page.goto('/mahasiswa/upload-dokumen');
            await expect(page).toHaveTitle(/Upload Dokumen/i);
            await expect(
                page.getByText('Naskah Akhir Playwright').first(),
            ).toBeVisible();
            await expect(
                page.getByText('Lampiran Sidang A Playwright').first(),
            ).toBeVisible();
            await expect(
                page.getByText('Lampiran Sidang B Playwright').first(),
            ).toBeVisible();

            await page.goto('/mahasiswa/tugas-akhir');
            await expect(page).toHaveTitle(/Tugas Akhir/i);

            const sidangSection = sidangDocumentSection(page);
            await expect(sidangSection).toBeVisible();

            await sidangSection
                .getByPlaceholder('Cari file utama...')
                .fill('Naskah Akhir Playwright');
            await sidangSection
                .getByRole('button')
                .filter({ hasText: mainFileName })
                .first()
                .click();

            await sidangSection
                .getByPlaceholder('Cari file lampiran...')
                .fill('Lampiran Sidang A Playwright');
            await sidangSection
                .getByRole('button')
                .filter({ hasText: supportingFileNames[0] })
                .first()
                .click();

            await sidangSection
                .getByPlaceholder('Cari file lampiran...')
                .fill('Lampiran Sidang B Playwright');
            await sidangSection
                .getByRole('button')
                .filter({ hasText: supportingFileNames[1] })
                .first()
                .click();

            await sidangSection
                .getByRole('button', { name: 'Simpan Dokumen' })
                .click();

            await expect(
                page.getByText(
                    'Dokumen sidang berhasil diperbarui dari workspace file.',
                ),
            ).toBeVisible();

            await page.reload();
            await expect(
                sidangSection.getByText(mainFileName).first(),
            ).toBeVisible();
            await expect(
                sidangSection.getByText(supportingFileNames[0]).first(),
            ).toBeVisible();
            await expect(
                sidangSection.getByText(supportingFileNames[1]).first(),
            ).toBeVisible();

            assertSidangSnapshotsSelected(
                'putra@sita.test',
                mainFileName,
                supportingFileNames,
            );
        } finally {
            await mahasiswaContext.close();
        }
    });
});
