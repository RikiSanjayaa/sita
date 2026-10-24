import { expect, test } from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Progres Mahasiswa (/progres-mahasiswa) is read-only and open to every logged-in
// role. Search, filters and sorting are all server round trips, so assertions
// read the URL and the rendered table rather than client state. The table lists
// the same students as kaprodi Mahasiswa Prodi, newest stage progress first.
const rows = 'tbody tr';

test.describe('Progres Mahasiswa', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test.describe('guest', () => {
        test.use({ storageState: { cookies: [], origins: [] } });

        test('redirects to login', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expect(page).toHaveURL(/\/login/);
        });
    });

    test.describe('mahasiswa', () => {
        test.use({ storageState: authStatePath('mahasiswa') });

        test('words last activity as relative time, with no "Tidak aktif" flag', async ({
            page,
        }) => {
            await page.goto('/progres-mahasiswa');
            await expect(page.locator(rows).first()).toBeVisible();

            await expect(page.getByText('Tidak aktif')).toHaveCount(0);
            for (const cell of await page
                .locator(`${rows} td[data-label="Aktivitas terakhir"]`)
                .all()) {
                await expect(cell).toHaveText(
                    /^(Terakhir aktif .+|Belum ada aktivitas|-)$/,
                );
            }
        });

        test('opens, searches, filters and opens a profile', async ({
            page,
        }) => {
            await page.goto('/progres-mahasiswa');

            await expect(
                page.getByRole('heading', { name: 'Progres Mahasiswa' }),
            ).toBeVisible();
            await expect(page.locator(rows).first()).toBeVisible();

            const firstName = (
                await page.locator(`${rows} a p`).first().innerText()
            ).trim();

            // Search runs on the server and is carried in the URL.
            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill(firstName);
            await expect(page).toHaveURL(/search=/);
            await expect(page.locator(rows).first()).toContainText(firstName);

            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill('zzz-tidak-ada-998877');
            await expect(page).toHaveURL(/search=zzz-tidak-ada-998877/);
            await expect(page.locator(rows)).toHaveCount(0);

            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill('');
            await expect(page.locator(rows).first()).toBeVisible();

            // Phase pills are a single-select group; the second pill is a real phase.
            const phasePills = page.getByRole('group', {
                name: 'Tahap mahasiswa',
            });
            await phasePills.getByRole('radio').nth(1).click();
            await expect(page).toHaveURL(/phase=/);

            await phasePills.getByRole('radio').first().click();
            await expect(page).not.toHaveURL(/phase=/);

            // Program studi select filters in SQL and is reflected in the URL.
            await page.getByRole('combobox', { name: 'Program studi' }).click();
            await page.getByRole('option').nth(1).click();
            await expect(page).toHaveURL(/prodi=/);

            // There is no Detail button: the name links to the student's profile.
            await page.goto('/progres-mahasiswa');
            await expect(
                page.getByRole('button', { name: 'Detail' }),
            ).toHaveCount(0);
            await expect(page.locator(`${rows} a`).first()).toHaveAttribute(
                'href',
                /\/profil\/\d+/,
            );
            await page.locator(`${rows} a`).first().click();
            await expect(page).toHaveURL(/\/profil\/\d+/);
        });

        test('lists students without a thesis too, filterable by phase', async ({
            page,
        }) => {
            await page.goto('/progres-mahasiswa');

            const none = page
                .getByRole('group', { name: 'Tahap mahasiswa' })
                .getByRole('radio', { name: /Belum ada tugas akhir/ });
            await expect(none).toBeVisible();
            await none.click();
            await expect(page).toHaveURL(/phase=none/);
            await expect(page.locator(rows).first()).toContainText(
                'Belum ada tugas akhir',
            );
        });
    });

    test.describe('sorting', () => {
        test.use({ storageState: authStatePath('kaprodi') });

        test('a student whose sidang gets scheduled moves to the top', async ({
            page,
        }) => {
            const student = thesisScenarios.semproUnscheduled;

            runArtisan([
                'tinker',
                `--execute=$p = App\\Models\\ThesisProject::query()->where("student_user_id", App\\Models\\User::query()->where("name", "${student.studentName}")->value("id"))->latest("id")->firstOrFail(); App\\Models\\ThesisDefense::query()->create(["project_id" => $p->id, "type" => "sidang", "attempt_no" => 1, "status" => "scheduled", "result" => "pending", "scheduled_for" => now()->addDays(14), "location" => "Ruang Sidang", "mode" => "offline"]);`,
            ]);

            await page.goto('/progres-mahasiswa');
            await expect(page.locator(rows).first()).toContainText(
                student.studentName,
            );
            await expect(page.locator(rows).first()).toContainText(
                'Sidang dijadwalkan',
            );
        });
    });

    test.describe('dosen', () => {
        test.use({ storageState: authStatePath('dosen1') });

        test('opens the progress list', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expect(
                page.getByRole('heading', { name: 'Progres Mahasiswa' }),
            ).toBeVisible();
        });
    });

    test.describe('kaprodi', () => {
        test.use({ storageState: authStatePath('kaprodi') });

        test('opens the progress list', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expect(
                page.getByRole('heading', { name: 'Progres Mahasiswa' }),
            ).toBeVisible();
        });
    });
});
