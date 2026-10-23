import { expect, test, type Page } from '@playwright/test';

import { pickCalendarDay } from '../support/dates';
import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Kaprodi portal (kaprodi@sita.test, program studi Ilmu Komputer). Cross-role
// checks run against the dedicated scenario students from
// PlaywrightScenarioSeeder, reset in beforeAll. kaprodi-design-patterns.spec.ts
// covers the pattern components on the sempro-sidang page, not these flows.
const unscheduled = thesisScenarios.semproUnscheduled;
const withoutSupervisors = thesisScenarios.passedWithoutSupervisors;
const supervised = thesisScenarios.twoSupervisors;

function studentRow(page: Page, studentName: string) {
    // Some tables (kaprodi/dokumen) give their rows role="button", so select the tr element.
    return page.locator('tr').filter({ hasText: studentName }).first();
}

async function pickLecturer(
    page: Page,
    inputIndex: number,
    query: string,
    lecturerName: RegExp,
): Promise<void> {
    await page
        .getByRole('dialog')
        .getByPlaceholder('Ketik min. 2 karakter...')
        .nth(inputIndex)
        .fill(query);
    await page.getByRole('button', { name: lecturerName }).click();
}

async function chooseStudentInDialog(
    page: Page,
    studentName: string,
): Promise<void> {
    const dialog = page.getByRole('dialog');
    const option = dialog.locator('option').filter({ hasText: studentName });
    const value = await option.getAttribute('value');

    expect(value).toBeTruthy();
    await dialog
        .getByRole('combobox')
        .first()
        .selectOption(value ?? '');
}

test.describe('Kaprodi portal', () => {
    test.describe.configure({ mode: 'serial' });
    test.use({ storageState: authStatePath('kaprodi') });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('dashboard shows the monitoring sections and links to every kaprodi menu', async ({
        page,
    }) => {
        await page.goto('/kaprodi/dashboard');
        await expect(page).toHaveTitle(/Dashboard Kaprodi/);

        for (const title of [
            'Akses Monitoring',
            'Fase Mahasiswa',
            'Perlu Perhatian',
            'Agenda Terdekat',
            'Arsip Terbaru',
        ]) {
            await expect(page.getByText(title, { exact: true })).toBeVisible();
        }

        const menus = [
            { link: 'Mahasiswa Prodi', url: /\/kaprodi\/mahasiswa$/ },
            { link: 'Dokumen', url: /\/kaprodi\/dokumen$/ },
            { link: 'Dosen Prodi', url: /\/kaprodi\/dosen-prodi$/ },
            {
                link: 'Sempro / Sinopsis & Sidang Akhir',
                url: /\/kaprodi\/sempro-sidang$/,
            },
        ];

        for (const menu of menus) {
            await page.goto('/kaprodi/dashboard');
            await page
                .getByRole('link', { name: menu.link, exact: true })
                .first()
                .click();
            await expect(page).toHaveURL(menu.url);
        }
    });

    test('mahasiswa list finds a student and opens the student profile', async ({
        page,
    }) => {
        await page.goto('/kaprodi/mahasiswa');
        await expect(page).toHaveTitle(/Mahasiswa Prodi/);

        await page
            .getByPlaceholder('Cari nama atau NIM...')
            .fill(supervised.studentName);
        const row = studentRow(page, supervised.studentName);
        await expect(row).toBeVisible();
        await expect(row).toContainText('2210511001');

        await row.getByRole('link', { name: 'Buka profil mahasiswa' }).click();
        await expect(page).toHaveURL(/\/profil\/\d+$/);
        await expect(page.getByText('2210511001').first()).toBeVisible();
    });

    test('kaprodi assigns two supervisors; the student and both dosen see the assignment', async ({
        browser,
        page,
    }) => {
        const studentContext = await browser.newContext({
            storageState: authStatePath(withoutSupervisors.studentKey),
        });
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        try {
            await page.goto('/kaprodi/mahasiswa');
            await page
                .getByPlaceholder('Cari nama atau NIM...')
                .fill(withoutSupervisors.studentName);
            await studentRow(page, withoutSupervisors.studentName)
                .getByRole('button', { name: 'Atur pembimbing' })
                .click();

            const dialog = page.getByRole('dialog', {
                name: 'Atur Pembimbing',
            });
            await expect(dialog).toBeVisible();

            await pickLecturer(page, 0, 'Budi', /Dr\. Budi Santoso/);
            await pickLecturer(page, 1, 'Ratna', /Dr\. Ratna Kusuma/);
            await dialog
                .getByRole('button', { name: 'Simpan Pembimbing' })
                .click();

            await expect(page.getByRole('dialog')).toHaveCount(0);

            const studentPage = await studentContext.newPage();
            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Dr. Budi Santoso, M.Kom.').first(),
            ).toBeVisible();
            await expect(
                studentPage.getByText('Dr. Ratna Kusuma, M.Kom.').first(),
            ).toBeVisible();
            // Progress is a plain count per supervisor, not a target.
            await expect(studentPage.getByText('0 bimbingan')).toHaveCount(2);
            await expect(studentPage.getByText('0/6')).toHaveCount(0);

            const dosenPage = await dosenContext.newPage();
            await dosenPage.goto('/dosen/mahasiswa-bimbingan');
            await expect(
                dosenPage.getByText(withoutSupervisors.studentName).first(),
            ).toBeVisible();
            await expect(
                dosenPage
                    .getByTestId('mentorship-progress')
                    .getByText('0 bimbingan')
                    .first(),
            ).toBeVisible();
        } finally {
            await studentContext.close();
            await dosenContext.close();
        }
    });

    test('kaprodi schedules a sempro with two examiners and the student sees it', async ({
        browser,
        page,
    }) => {
        const location = 'Ruang Seminar Kaprodi E2E';
        const studentContext = await browser.newContext({
            storageState: authStatePath(unscheduled.studentKey),
        });

        try {
            await page.goto('/kaprodi/sempro-sidang');
            await page
                .getByRole('button', { name: /^Jadwalkan Sempro/ })
                .click();

            const dialog = page.getByRole('dialog', {
                name: /^Jadwalkan Sempro/,
            });
            await expect(dialog).toBeVisible();

            await chooseStudentInDialog(page, unscheduled.studentName);
            const nextMonth = new Date();
            nextMonth.setMonth(nextMonth.getMonth() + 1, 15);
            await pickCalendarDay(dialog, nextMonth);
            await dialog.locator('input[type="time"]').fill('09:30');
            await dialog
                .getByPlaceholder('Ruang ujian / tautan meeting')
                .fill(location);

            await pickLecturer(page, 0, 'Fajar', /Dr\. Fajar Nugraha/);
            await pickLecturer(page, 1, 'Siska', /Dr\. Siska Maharani/);

            await dialog.getByRole('button', { name: 'Simpan Jadwal' }).click();
            await expect(page.getByRole('dialog')).toHaveCount(0);

            await page
                .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
                .fill(unscheduled.studentName);
            const row = studentRow(page, unscheduled.studentName);
            await expect(row).toContainText('#1');
            await expect(row).toContainText('Dr. Fajar Nugraha');
            await expect(row).toContainText('Dr. Siska Maharani');

            const studentPage = await studentContext.newPage();
            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(studentPage.getByText(location).first()).toBeVisible();
        } finally {
            await studentContext.close();
        }
    });

    test('kaprodi schedules a sidang for a student whose supervisors were just assigned', async ({
        page,
    }) => {
        await page.goto('/kaprodi/sempro-sidang');
        await page.getByRole('button', { name: /^Jadwalkan Sidang/ }).click();

        const dialog = page.getByRole('dialog', { name: /^Jadwalkan Sidang/ });
        await expect(dialog).toBeVisible();

        await chooseStudentInDialog(page, withoutSupervisors.studentName);
        // Both supervisors from the previous test belong to the panel.
        await expect(
            dialog.getByText(/Pembimbing 1.*Budi/).first(),
        ).toBeVisible();

        // Fresh supervisors have no completed mentorship yet: warn, never block.
        const warning = dialog.getByTestId('mentorship-minimum-warning');
        await expect(warning).toContainText('Bimbingan belum memenuhi minimal');
        await expect(warning).toContainText('Dr. Budi Santoso, M.Kom. (0/6)');
        await expect(warning).toContainText('Dr. Ratna Kusuma, M.Kom. (0/6)');

        const nextMonth = new Date();
        nextMonth.setMonth(nextMonth.getMonth() + 1, 16);
        await pickCalendarDay(dialog, nextMonth);
        await dialog.locator('input[type="time"]').fill('13:00');
        await dialog
            .getByPlaceholder('Ruang ujian / tautan meeting')
            .fill('Ruang Sidang Kaprodi E2E');
        await dialog
            .getByPlaceholder('Cari dosen penguji (min. 2 karakter)...')
            .fill('Ahmad');
        await page
            .getByRole('button', { name: /Prof\. Ahmad Hidayat/ })
            .click();

        await dialog.getByRole('button', { name: 'Simpan Jadwal' }).click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await page
            .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
            .fill(withoutSupervisors.studentName);
        const row = studentRow(page, withoutSupervisors.studentName);
        await expect(row).toContainText('Dr. Budi Santoso');
        await expect(row).toContainText('Dr. Ratna Kusuma');
        await expect(row).toContainText('Prof. Ahmad Hidayat');
    });

    test('dosen prodi lists supervision load and kaprodi changes a quota', async ({
        page,
    }) => {
        try {
            await page.goto('/kaprodi/dosen-prodi');
            await expect(page).toHaveTitle(/Dosen Prodi/);

            await page
                .getByPlaceholder('Cari nama, NIK, konsentrasi...')
                .fill('Fajar');
            const row = studentRow(page, 'Dr. Fajar Nugraha');
            await expect(row).toContainText('Kuota 14');

            await row
                .getByRole('button', { name: 'Atur kuota bimbingan' })
                .click();
            const dialog = page.getByRole('dialog', {
                name: 'Atur Kuota Bimbingan',
            });
            await dialog.getByRole('spinbutton').fill('20');
            await dialog.getByRole('button', { name: 'Simpan Kuota' }).click();

            await expect(page.getByRole('dialog')).toHaveCount(0);
            await expect(row).toContainText('Kuota 20');
        } finally {
            runArtisan([
                'tinker',
                '--execute=App\\Models\\DosenProfile::query()->where("user_id", App\\Models\\User::query()->where("email", "dosen5@sita.test")->value("id"))->update(["supervision_quota" => 14]);',
            ]);
        }
    });

    test('dokumen page lists student documents and opens the detail sheet', async ({
        page,
    }) => {
        await page.goto('/kaprodi/dokumen');
        await expect(page).toHaveTitle(/Dokumen Kaprodi/);

        await page
            .getByPlaceholder('Cari mahasiswa, NIM, file, atau dosen...')
            .fill(supervised.studentName);
        const row = studentRow(page, supervised.studentName);
        await expect(row).toContainText('Proposal Skripsi');

        await row.click();
        await expect(
            page.getByRole('dialog').getByText('Proposal Skripsi').first(),
        ).toBeVisible();
    });
});
