import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';
import { buildImportWorkbook, cleanupWorkbook } from '../support/xlsx';

const TEMPLATE_PATH = '/admin/thesis-import-template.xlsx';
const MODAL_HEADING = 'yang sudah berjalan';
const HEADER = [
    'nim',
    'judul',
    'pembimbing_1_nik',
    'pembimbing_2_nik',
    'tahap',
    'tanggal_mulai_masa_aktif',
];
const LECTURER_NIK = '7301999900000001';
const STUDENTS = [
    {
        name: 'Impor Tahap Satu',
        email: 'e2e-impor-1@sita.test',
        nim: '2210519901',
    },
    {
        name: 'Impor Tahap Dua',
        email: 'e2e-impor-2@sita.test',
        nim: '2210519902',
    },
    {
        name: 'Impor Tahap Tiga',
        email: 'e2e-impor-3@sita.test',
        nim: '2210519903',
    },
    {
        name: 'Impor Tahap Empat',
        email: 'e2e-impor-4@sita.test',
        nim: '2210519904',
    },
];

/**
 * Creates the students and the lecturer the workbooks refer to, and removes any
 * project a previous run imported, so the scenario is repeatable.
 */
function seedImportFixtures(): void {
    const students = JSON.stringify(
        STUDENTS.map((student) => [student.email, student.name, student.nim]),
    );
    const code = `
        $prodi = App\\Models\\ProgramStudi::query()->where('slug', 'ilkom')->firstOrFail();
        $concentration = App\\Models\\ProgramStudi::defaultConcentrationsForSlug('ilkom')[0];
        $studentRole = App\\Models\\Role::query()->where('name', 'mahasiswa')->firstOrFail();
        $dosenRole = App\\Models\\Role::query()->where('name', 'dosen')->firstOrFail();
        foreach (json_decode('${students}', true) as [$email, $name, $nim]) {
            $user = App\\Models\\User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => bcrypt('password'), 'last_active_role' => 'mahasiswa']);
            $user->roles()->syncWithoutDetaching([$studentRole->id]);
            App\\Models\\MahasiswaProfile::query()->updateOrCreate(['user_id' => $user->id], ['nim' => $nim, 'program_studi_id' => $prodi->id, 'degree_level' => 's1', 'concentration' => $concentration, 'angkatan' => 2022, 'is_active' => true]);
            App\\Models\\ThesisProject::query()->where('student_user_id', $user->id)->delete();
        }
        $dosen = App\\Models\\User::query()->updateOrCreate(['email' => 'e2e-impor-dosen@sita.test'], ['name' => 'Dosen Impor E2E', 'password' => bcrypt('password'), 'last_active_role' => 'dosen']);
        $dosen->roles()->syncWithoutDetaching([$dosenRole->id]);
        App\\Models\\DosenProfile::query()->updateOrCreate(['user_id' => $dosen->id], ['nik' => '${LECTURER_NIK}', 'program_studi_id' => $prodi->id, 'concentration' => $concentration, 'supervision_quota' => 14, 'is_active' => true]);
        App\\Models\\DosenProgramStudiAssignment::query()->updateOrCreate(['user_id' => $dosen->id, 'program_studi_id' => $prodi->id, 'concentration' => $concentration], ['is_primary' => true, 'is_active' => true]);
    `;

    runArtisan(['tinker', `--execute=${code.replace(/\s+/g, ' ')}`]);
}

async function openImportModal(page: Page): Promise<void> {
    await page.goto('/admin/thesis-projects');
    await expect(page).toHaveURL(/\/admin\/thesis-projects/);
    await page.getByRole('button', { name: 'Import Tugas Akhir' }).click();
    await expect(page.getByText(MODAL_HEADING).first()).toBeVisible();
}

async function waitForUploadedWorkbook(
    page: Page,
    workbookPath: string,
): Promise<void> {
    const modal = page.locator('.fi-modal-window').filter({
        hasText: MODAL_HEADING,
    });
    const fileInput = modal.locator('.filepond--root input[type="file"]');

    await expect(fileInput).toBeAttached();
    await fileInput.setInputFiles(workbookPath);

    const item = modal.locator('.filepond--item');

    await expect(item).toHaveAttribute(
        'data-filepond-item-state',
        'processing-complete',
        { timeout: 30_000 },
    );
    await expect(item.locator('.filepond--file-info-main')).toContainText(
        path.basename(workbookPath),
    );
}

async function searchProjects(page: Page, query: string): Promise<void> {
    await page.goto(
        `/admin/thesis-projects?tableSearch=${encodeURIComponent(query)}`,
    );
    await expect(page).toHaveURL(/\/admin\/thesis-projects/);
}

test.describe.configure({ mode: 'serial' });

test.describe('Admin thesis import', () => {
    test.beforeAll(() => {
        seedImportFixtures();
    });

    test('admin downloads the template from the list page', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });

        try {
            const page = await adminContext.newPage();

            await page.goto('/admin/thesis-projects');
            await expect(
                page.getByRole('link', { name: 'Download Template' }),
            ).toHaveAttribute('href', new RegExp(`${TEMPLATE_PATH}$`));

            const response = await page.request.get(TEMPLATE_PATH);

            expect(response.ok()).toBeTruthy();
            expect(response.headers()['content-disposition']).toContain(
                'thesis-import-template.xlsx',
            );
        } finally {
            await adminContext.close();
        }
    });

    test('admin imports a running thesis workbook and the stages show in the table', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });
        const workbookPath = await buildImportWorkbook(
            await adminContext.newPage(),
            TEMPLATE_PATH,
            'thesis-success.xlsx',
            [
                HEADER,
                [
                    STUDENTS[0].nim,
                    'Judul Impor Review E2E',
                    '',
                    '',
                    'Review Judul',
                    '',
                ],
                [
                    STUDENTS[1].nim,
                    'Judul Impor Riset E2E',
                    LECTURER_NIK,
                    '',
                    'Riset',
                    '2026-01-15',
                ],
                [
                    STUDENTS[2].nim,
                    'Judul Impor Selesai E2E',
                    LECTURER_NIK,
                    '',
                    'Selesai',
                    '',
                ],
            ],
        );

        try {
            const page = await adminContext.newPage();

            await openImportModal(page);
            await waitForUploadedWorkbook(page, workbookPath);
            await page.getByRole('button', { name: 'Submit' }).click();

            await expect(
                page.getByText('Import Excel berhasil').first(),
            ).toBeVisible();
            await expect(
                page.getByText('Diproses 3 baris, berhasil 3.').first(),
            ).toBeVisible();

            for (const [index, phase] of [
                [0, 'Review Judul'],
                [1, 'Riset'],
                [2, 'Selesai'],
            ] as const) {
                await searchProjects(page, STUDENTS[index].name);

                const row = page
                    .locator('tr')
                    .filter({ hasText: STUDENTS[index].name })
                    .first();

                await expect(row).toBeVisible();
                await expect(row).toContainText(phase);
            }
        } finally {
            await adminContext.close();
            cleanupWorkbook(workbookPath);
        }
    });

    test('admin sees every row error and nothing is saved when the file has a bad row', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });
        const workbookPath = await buildImportWorkbook(
            await adminContext.newPage(),
            TEMPLATE_PATH,
            'thesis-errors.xlsx',
            [
                HEADER,
                [
                    STUDENTS[3].nim,
                    'Judul Impor Valid E2E',
                    LECTURER_NIK,
                    '',
                    'Riset',
                    '',
                ],
                [
                    '0000000000',
                    'Judul Tanpa Mahasiswa',
                    '',
                    '',
                    'Review Judul',
                    '',
                ],
                [
                    STUDENTS[1].nim,
                    'Judul Impor Riset E2E',
                    LECTURER_NIK,
                    '',
                    'Riset',
                    '',
                ],
                [
                    STUDENTS[0].nim,
                    'Judul Tahap Salah',
                    LECTURER_NIK,
                    '',
                    'Magang',
                    '2026-13-45',
                ],
            ],
        );

        try {
            const page = await adminContext.newPage();

            await openImportModal(page);
            await waitForUploadedWorkbook(page, workbookPath);
            await page.getByRole('button', { name: 'Submit' }).click();

            const notification = page.locator('.fi-no-notification');

            await expect(
                notification.getByText(
                    'Import Excel dibatalkan, tidak ada data yang disimpan',
                ),
            ).toBeVisible();
            await expect(
                notification.getByText(
                    'Baris 3: NIM 0000000000 tidak ditemukan.',
                ),
            ).toBeVisible();
            await expect(
                notification.getByText(
                    'Baris 4: Mahasiswa sudah memiliki Skripsi aktif.',
                ),
            ).toBeVisible();
            await expect(
                notification.getByText("Tahap 'Magang' tidak dikenal."),
            ).toBeVisible();
            await expect(
                notification.getByText(
                    "Tanggal mulai masa aktif '2026-13-45' tidak valid.",
                ),
            ).toBeVisible();

            await searchProjects(page, STUDENTS[3].name);
            await expect(
                page.locator('tr').filter({ hasText: STUDENTS[3].name }),
            ).toHaveCount(0);
        } finally {
            await adminContext.close();
            cleanupWorkbook(workbookPath);
        }
    });
});
