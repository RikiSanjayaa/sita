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

function resetSemproSelectionForStudent(studentEmail: string): void {
    runArtisan([
        'tinker',
        `--execute=$defense = App\\Models\\ThesisDefense::query()->whereHas("project.student", fn($query) => $query->where("email", "${studentEmail}"))->where("type", "sempro")->latest("attempt_no")->firstOrFail(); $defense->forceFill(["status" => "scheduled", "result" => "pending", "decided_by" => null, "decision_at" => null, "notes" => null])->save();`,
    ]);
}

function resetSidangSelectionForStudent(studentEmail: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($query) => $query->where("email", "${studentEmail}"))->latest("started_at")->firstOrFail(); $project->forceFill(["state" => "active", "phase" => "sidang", "completed_at" => null, "closed_by" => null])->save(); $defense = $project->sidangDefenses()->latest("attempt_no")->firstOrFail(); $defense->forceFill(["status" => "scheduled", "result" => "pending", "decided_by" => null, "decision_at" => null, "notes" => null])->save();`,
    ]);
}

function semproDocumentSection(page: Page) {
    return page
        .locator('section')
        .filter({ hasText: 'Dokumen Seminar Proposal' });
}

function sidangDocumentSection(page: Page) {
    return page
        .locator('section')
        .filter({ hasText: 'Dokumen Sidang Skripsi' });
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

        await page.getByRole('button', { name: 'Edit' }).click();
        await page
            .getByLabel('Judul Skripsi (Bahasa Indonesia)')
            .fill(
                'Implementasi Blockchain untuk Sistem Voting Digital Terdistribusi',
            );
        await page
            .getByLabel('Ringkasan Naskah Proposal')
            .fill(
                'Proposal diperbarui dari Playwright untuk memastikan mahasiswa masih dapat mengedit pengajuan yang belum diputuskan admin.',
            );
        await page
            .getByLabel('Ganti File Naskah Proposal (Opsional)')
            .setInputFiles(
                path.join(
                    process.cwd(),
                    thesisFixtures.thesisDocumentUploadPath,
                ),
            );
        await page.getByRole('button', { name: 'Simpan Perubahan' }).click();

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

            await mahasiswaPage
                .getByLabel('Judul Skripsi (Bahasa Indonesia)')
                .fill(titleId);
            await mahasiswaPage
                .getByLabel('Judul Skripsi (Bahasa Inggris)')
                .fill(
                    'Zero-Trust Architecture for Securing Computing Laboratory Access',
                );
            await mahasiswaPage
                .getByLabel('Ringkasan Naskah Proposal')
                .fill(
                    'Proposal Playwright ini mengajukan rancangan kontrol akses laboratorium berbasis zero-trust dengan evaluasi kebijakan identitas, perangkat, dan sesi penggunaan.',
                );
            const primaryPicker = mahasiswaPage
                .getByText('Usulan Dosen Pembimbing 1 (Opsional)')
                .locator('..');
            await primaryPicker
                .getByPlaceholder('Ketik min. 2 karakter...')
                .fill('Budi Santoso');
            await primaryPicker.getByText('Dr. Budi Santoso, M.Kom.').click();
            await expect(
                primaryPicker.getByText('Dr. Budi Santoso, M.Kom.'),
            ).toBeVisible();

            const secondaryPicker = mahasiswaPage
                .getByText('Usulan Dosen Pembimbing 2 (Opsional)')
                .locator('..');
            await secondaryPicker
                .getByPlaceholder('Ketik min. 2 karakter...')
                .fill('Ratna Kusuma');
            await secondaryPicker.getByText('Dr. Ratna Kusuma, M.Kom.').click();
            await expect(
                secondaryPicker.getByText('Dr. Ratna Kusuma, M.Kom.'),
            ).toBeVisible();
            await mahasiswaPage
                .getByLabel('File Naskah Proposal (PDF)')
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
                mahasiswaPage.getByText(titleId).first(),
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

            runArtisan([
                'tinker',
                '--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($query) => $query->where("email", "farhan@sita.test"))->latest("started_at")->firstOrFail(); if ($project->phase !== "title_review" || $project->latestTitle?->status !== "submitted") { throw new RuntimeException("New submission did not enter pending title review."); }',
            ]);
            runArtisan([
                'tinker',
                '--execute=$student = App\\Models\\User::query()->where("email", "farhan@sita.test")->firstOrFail(); $title = App\\Models\\ThesisProjectTitle::query()->where("submitted_by_user_id", $student->id)->latest("id")->firstOrFail(); if ($title->proposedPrimaryLecturer?->email !== "dosen@sita.test" || $title->proposedSecondaryLecturer?->email !== "dosen2@sita.test") { throw new RuntimeException("Proposed supervisors were not saved."); }',
            ]);
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

            resetSemproSelectionForStudent('akbar@sita.test');
            seedWorkspaceDocument('akbar@sita.test', {
                title: 'Proposal Sempro Playwright',
                category: 'proposal',
                fileName: semproFileName,
            });

            await page.goto('/mahasiswa/upload-dokumen');

            await page.goto('/mahasiswa/tugas-akhir');

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

            resetSidangSelectionForStudent('putra@sita.test');
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

            await page.goto('/mahasiswa/tugas-akhir');

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
