import { expect, test } from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { thesisScenarios } from '../support/thesis-fixtures';

// Guest-visible pages. No storage state: these must work without logging in.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Public pages', () => {
    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('home links to every public page and to login', async ({ page }) => {
        await page.goto('/');
        await expect(
            page.getByRole('link', { name: 'Masuk ke SiTA' }).first(),
        ).toBeVisible();

        const pages = [
            { link: 'Jadwal', url: /\/jadwal$/ },
            { link: 'Mahasiswa', url: /\/mahasiswa-aktif$/ },
            { link: 'Pembimbing', url: /\/pembimbing$/ },
            { link: 'Topik', url: /\/topik$/ },
        ];

        for (const target of pages) {
            await page.goto('/');
            await page
                .getByRole('link', { name: target.link, exact: true })
                .first()
                .click();
            await expect(page).toHaveURL(target.url);
        }
    });

    test('jadwal lists upcoming exams and filters them by search', async ({
        page,
    }) => {
        await page.goto('/jadwal');
        await expect(
            page.getByText('Jadwal Akan Datang', { exact: true }),
        ).toBeVisible();

        await page
            .getByPlaceholder('Cari mahasiswa, NIM, prodi, atau lokasi...')
            .first()
            .fill(thesisScenarios.semproTwoExaminers.studentName);
        const semproRow = page
            .getByRole('row')
            .filter({ hasText: thesisScenarios.semproTwoExaminers.studentName })
            .first();
        await expect(semproRow).toBeVisible();
        await expect(semproRow).toContainText(
            thesisScenarios.semproTwoExaminers.location,
        );

        await page
            .getByPlaceholder('Cari mahasiswa, NIM, prodi, atau lokasi...')
            .first()
            .fill('tidak-ada-jadwal-seperti-ini');
        await expect(
            page.getByText('Tidak ada jadwal', { exact: true }).first(),
        ).toBeVisible();
    });

    test('pembimbing directory groups lecturers and searches by name', async ({
        page,
    }) => {
        await page.goto('/pembimbing');
        await expect(
            page.getByText('Direktori Pembimbing', { exact: true }).first(),
        ).toBeVisible();

        // The directory opens on the first program studi alphabetically.
        await page.getByRole('combobox').first().click();
        await page.getByRole('option', { name: 'Ilmu Komputer' }).click();
        await page
            .getByPlaceholder('Cari dosen atau konsentrasi...')
            .fill('Budi');
        await expect(
            page.getByRole('row').filter({ hasText: 'Dr. Budi Santoso' }),
        ).toHaveCount(1);

        await page
            .getByPlaceholder('Cari dosen atau konsentrasi...')
            .fill('tidak-ada-dosen-seperti-ini');
        await expect(
            page.getByText('Tidak ada pembimbing', { exact: true }),
        ).toBeVisible();
    });

    test('topik publishes an unsuperseded completed topic and expands its details', async ({
        page,
    }) => {
        runArtisan([
            'tinker',
            '--execute',
            "App\\Models\\ThesisProject::query()->where('student_user_id', App\\Models\\User::query()->where('email', 'e2e-pembimbingbaru@sita.test')->value('id'))->update(['state' => 'completed', 'phase' => 'completed']);",
        ]);
        try {
            await page.goto('/topik');
            const search = page.getByPlaceholder(
                'Cari judul, ringkasan, mahasiswa, atau NIM...',
            );
            await search.fill('Algoritma Genetika');
            const row = page
                .getByRole('row')
                .filter({ hasText: 'Optimasi Rute Pengiriman Logistik' })
                .first();
            await expect(
                row.getByText('Selesai', { exact: true }),
            ).toBeVisible();
            await expect(
                page.getByRole('button', { name: 'Buka detail topik' }),
            ).toHaveCount(1);
            const trigger = row.getByRole('button', {
                name: 'Buka detail topik',
            });
            // Clicking anywhere on the row (here the title) toggles it too.
            await row.getByText('Optimasi Rute Pengiriman Logistik').click();
            await expect(trigger).toHaveAttribute('aria-expanded', 'true');
            await expect(page.getByText('Judul Internasional')).toBeVisible();
            await expect(
                page.getByText('Ringkasan proposal skenario Playwright.'),
            ).toBeVisible();
            await row.getByText('Optimasi Rute Pengiriman Logistik').click();
            await expect(trigger).toHaveAttribute('aria-expanded', 'false');
            await trigger.click();
            await expect(trigger).toHaveAttribute('aria-expanded', 'true');
            await search.fill('tidak-ada-topik-seperti-ini');
            await expect(
                page.getByText('Tidak ada topik', { exact: true }),
            ).toBeVisible();
        } finally {
            seedPlaywrightScenarios();
        }
    });

    test('topik shows an active topic once its sempro is passed', async ({
        page,
    }) => {
        await page.goto('/topik');
        await page
            .getByPlaceholder('Cari judul, ringkasan, mahasiswa, atau NIM...')
            .fill('Algoritma Genetika');

        const row = page
            .getByRole('row')
            .filter({ hasText: 'Optimasi Rute Pengiriman Logistik' })
            .first();
        await expect(row).toBeVisible();
        await expect(
            row.getByText('Sedang berjalan', { exact: true }),
        ).toBeVisible();
    });

    test('mahasiswa aktif shows a student with the active supervisors', async ({
        page,
    }) => {
        await page.goto('/mahasiswa-aktif');
        await expect(
            page.getByText('Daftar Mahasiswa', { exact: true }),
        ).toBeVisible();

        await page
            .getByPlaceholder('Cari nama, NIM, atau prodi...')
            .fill(thesisScenarios.twoSupervisors.studentName);
        const row = page
            .getByRole('row')
            .filter({ hasText: thesisScenarios.twoSupervisors.studentName })
            .first();
        await expect(row).toContainText('2210511001');
        await expect(row).toContainText('Dr. Budi Santoso');
        await expect(row).toContainText('Dr. Ratna Kusuma');

        await page
            .getByPlaceholder('Cari nama, NIM, atau prodi...')
            .fill(thesisScenarios.passedWithoutSupervisors.studentName);
        await expect(
            page
                .getByRole('row')
                .filter({
                    hasText:
                        thesisScenarios.passedWithoutSupervisors.studentName,
                })
                .first(),
        ).toBeVisible();
    });

    test('mahasiswa aktif filters by tahap on the server and keeps it in the URL', async ({
        page,
    }) => {
        await page.goto('/mahasiswa-aktif');
        const pills = page.getByRole('group', { name: 'Tahap mahasiswa' });
        const stagePills = pills.getByRole('radio');
        await expect(stagePills.first()).toBeVisible();

        // The first pill is "Semua"; pick the next one, whatever stage it is.
        const picked = stagePills.nth(1);
        const pickedText = (await picked.innerText()).replace(/\s+/g, ' ');
        const [, stageLabel, stageCount] =
            pickedText.match(/^(.*?)\s+(\d+)$/) ?? [];
        expect(stageLabel).toBeTruthy();

        await picked.click();
        await expect(page).toHaveURL(/[?&]stage=[^&]+/);
        await expect(picked).toHaveAttribute('aria-checked', 'true');

        const rows = page.locator('tbody tr');
        await expect(rows).not.toHaveCount(0);
        const shown = Math.min(Number(stageCount), 10);
        await expect(rows).toHaveCount(shown);
        for (const row of await rows.all()) {
            await expect(row).toContainText(stageLabel);
        }
    });
});
