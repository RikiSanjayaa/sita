import { expect, test, type Page } from '@playwright/test';

import { runArtisan } from '../support/db';

// Dedicated fixtures owned by this spec: no seeded account or exam is used,
// so the counts below hold regardless of which specs ran before.
const fixture = {
    facultyCode: 'E2EDP',
    programStudiSlug: 'e2e-design-patterns',
    programStudiName: 'Program Studi E2E Pola Desain',
    kaprodiEmail: 'e2e-kaprodi-patterns@sita.test',
    studentEmail: 'e2e-mahasiswa-patterns@sita.test',
    studentName: 'Mahasiswa E2E Pola Desain',
    studentNim: '9900000001',
    password: 'password',
};

const model = (name: string): string => `App\\Models\\${name}`;

function cleanupFixtures(): void {
    runArtisan([
        'tinker',
        `--execute=$ps = ${model('ProgramStudi')}::query()->where("slug", "${fixture.programStudiSlug}")->first(); if ($ps) { ${model('ThesisDefense')}::query()->whereHas("project", fn($q) => $q->where("program_studi_id", $ps->id))->delete(); ${model('ThesisProject')}::query()->where("program_studi_id", $ps->id)->delete(); ${model('KaprodiAssignment')}::query()->where("program_studi_id", $ps->id)->delete(); ${model('DosenProgramStudiAssignment')}::query()->where("program_studi_id", $ps->id)->delete(); } foreach (${model('User')}::query()->whereIn("email", ["${fixture.kaprodiEmail}", "${fixture.studentEmail}"])->get() as $user) { ${model('DosenProfile')}::query()->where("user_id", $user->id)->delete(); ${model('MahasiswaProfile')}::query()->where("user_id", $user->id)->delete(); $user->roles()->detach(); $user->delete(); } if ($ps) { $ps->delete(); } ${model('Faculty')}::query()->where("code", "${fixture.facultyCode}")->delete();`,
    ]);
}

function createFixtures(): void {
    cleanupFixtures();

    runArtisan([
        'tinker',
        `--execute=$faculty = ${model('Faculty')}::query()->create(["name" => "Fakultas E2E Pola Desain", "code" => "${fixture.facultyCode}", "slug" => "e2e-design-patterns-faculty", "is_active" => true]); $ps = ${model('ProgramStudi')}::query()->create(["faculty_id" => $faculty->id, "name" => "${fixture.programStudiName}", "slug" => "${fixture.programStudiSlug}", "concentrations" => [${model('ProgramStudi')}::DEFAULT_GENERAL_CONCENTRATION], "degree_levels" => ["s1"]]); $kaprodi = ${model('User')}::factory()->asKaprodi()->create(["name" => "Kaprodi E2E Pola Desain", "email" => "${fixture.kaprodiEmail}"]); ${model('KaprodiAssignment')}::query()->create(["program_studi_id" => $ps->id, "user_id" => $kaprodi->id, "is_primary" => true, "capabilities" => ${model('KaprodiAssignment')}::defaultCapabilities()]); $student = ${model('User')}::factory()->asMahasiswa()->create(["name" => "${fixture.studentName}", "email" => "${fixture.studentEmail}"]); ${model('MahasiswaProfile')}::query()->create(["user_id" => $student->id, "nim" => "${fixture.studentNim}", "program_studi_id" => $ps->id, "degree_level" => "s1", "concentration" => ${model('ProgramStudi')}::DEFAULT_GENERAL_CONCENTRATION, "angkatan" => 2024, "is_active" => true]); $project = ${model('ThesisProject')}::query()->create(["student_user_id" => $student->id, "program_studi_id" => $ps->id, "phase" => "sempro", "state" => "active", "started_at" => now()]); ${model('ThesisDefense')}::query()->create(["project_id" => $project->id, "type" => "sempro", "attempt_no" => 1, "status" => "scheduled", "result" => "pending", "scheduled_for" => now()->addDays(7)->setTime(9, 0), "scheduled_until" => now()->addDays(7)->setTime(10, 0), "location" => "Ruang E2E", "mode" => "offline"]);`,
    ]);
}

async function loginAsFixtureKaprodi(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(fixture.kaprodiEmail);
    await page.getByLabel('Kata sandi').fill(fixture.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/kaprodi/);
}

async function setTheme(page: Page, theme: 'light' | 'dark'): Promise<void> {
    await page.evaluate((value) => {
        document.documentElement.classList.toggle('dark', value === 'dark');
        document.documentElement.style.colorScheme = value;
    }, theme);
}

test.describe('Kaprodi sempro-sidang design patterns', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        createFixtures();
    });

    test.afterAll(() => {
        cleanupFixtures();
    });

    test('combined type, status and search filters narrow the one scheduled exam, with zero counts and a sticky selection', async ({
        browser,
    }, testInfo) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            await loginAsFixtureKaprodi(page);
            await page.goto('/kaprodi/sempro-sidang');

            // Radix single ToggleGroup: container role=group, items role=radio.
            const statusGroup = page.getByRole('group', {
                name: 'Filter status ujian',
            });
            const typeGroup = page.getByRole('group', {
                name: 'Filter tipe ujian',
            });
            const search = page.getByPlaceholder(
                'Cari nama, NIM, judul, atau dosen...',
            );
            const rows = page
                .getByRole('region', { name: 'Monitoring Ujian' })
                .locator('tbody tr');
            const emptyState = page.getByText('Tidak ada ujian yang cocok');

            const allStatus = statusGroup.getByRole('radio', {
                name: 'Semua Status',
            });
            const scheduled = statusGroup.getByRole('radio', {
                name: /^Terjadwal\s*1$/,
            });
            const finalization = statusGroup.getByRole('radio', {
                name: /^Finalisasi\s*0$/,
            });
            const completed = statusGroup.getByRole('radio', {
                name: /^Selesai\s*0$/,
            });
            const sempro = typeGroup.getByRole('radio', { name: 'Proposal' });
            const sidang = typeGroup.getByRole('radio', {
                name: 'Ujian Akhir',
            });

            // Known fixture distribution: one scheduled exam, none completed.
            await expect(allStatus).toBeChecked();
            await expect(scheduled).toBeVisible();
            await expect(finalization).toBeVisible();
            await expect(completed).toBeVisible();
            await expect(rows).toHaveCount(1);
            await expect(rows.first()).toContainText(fixture.studentName);

            await setTheme(page, 'light');
            await page
                .getByRole('region', { name: 'Monitoring Ujian' })
                .screenshot({
                    path: testInfo.outputPath(
                        'kaprodi-sempro-sidang-light.png',
                    ),
                });
            await setTheme(page, 'dark');
            await page
                .getByRole('region', { name: 'Monitoring Ujian' })
                .screenshot({
                    path: testInfo.outputPath('kaprodi-sempro-sidang-dark.png'),
                });
            await setTheme(page, 'light');

            // Type + status + search combine on the real exam.
            await sempro.click();
            await scheduled.click();
            await search.fill(fixture.studentNim);
            await expect(page).toHaveURL(/[?&]status=scheduled/);
            await expect(page).toHaveURL(/[?&]type=sempro/);
            await expect(rows).toHaveCount(1);
            await expect(rows.first()).toContainText(fixture.studentName);

            // The selected status cannot toggle off.
            await scheduled.click();
            await expect(scheduled).toBeChecked();
            await expect(rows).toHaveCount(1);

            // A zero-count status shows its count and the empty state.
            await completed.click();
            await expect(completed).toBeChecked();
            await expect(scheduled).not.toBeChecked();
            await expect(emptyState).toBeVisible();
            await expect(rows).toHaveCount(0);

            // Another type with the populated status also has no match.
            await scheduled.click();
            await sidang.click();
            await expect(sidang).toBeChecked();
            await expect(emptyState).toBeVisible();

            // Resetting all filters brings the exam back.
            await sempro.click();
            await allStatus.click();
            await search.fill('');
            await expect(allStatus).toBeChecked();
            await expect(emptyState).toBeHidden();
            await expect(rows).toHaveCount(1);
        } finally {
            await context.close();
        }
    });

    test('type filter works from the keyboard and search shows the empty state', async ({
        browser,
    }) => {
        const context = await browser.newContext();
        const page = await context.newPage();

        try {
            await loginAsFixtureKaprodi(page);
            await page.goto('/kaprodi/sempro-sidang');

            const typeGroup = page.getByRole('group', {
                name: 'Filter tipe ujian',
            });
            const all = typeGroup.getByRole('radio', { name: 'Semua' });
            const sempro = typeGroup.getByRole('radio', { name: 'Proposal' });

            await expect(all).toBeChecked();

            // Arrow keys move focus within the group; Enter selects.
            await all.focus();
            await page.keyboard.press('ArrowRight');
            await expect(sempro).toBeFocused();
            await page.keyboard.press('Enter');
            await expect(sempro).toBeChecked();
            await expect(all).not.toBeChecked();
            await expect(page).toHaveURL(/[?&]type=sempro/);
            await expect(
                page
                    .getByRole('region', { name: 'Monitoring Ujian' })
                    .locator('tbody tr'),
            ).toHaveCount(1);

            // Keyboard activation of the selected item does not clear it.
            await page.keyboard.press('Enter');
            await expect(sempro).toBeChecked();

            await all.focus();
            await page.keyboard.press('Enter');
            await expect(all).toBeChecked();

            // Search with no match shows the shared empty state.
            await page
                .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
                .fill('zzz-tidak-ada-hasil-zzz');
            await expect(
                page.getByText('Tidak ada ujian yang cocok'),
            ).toBeVisible();
            await expect(
                page.getByText('Coba ubah filter atau kata kunci pencarian.'),
            ).toBeVisible();
        } finally {
            await context.close();
        }
    });
});
