import { expect, test, type Page } from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Progress Seluruh Mahasiswa (/progres-mahasiswa) is read-only and open to every logged-in
// role. Search, filters and sorting are all server round trips, so assertions
// read the URL and the rendered table rather than client state. The table lists
// the same students as kaprodi Mahasiswa Prodi, with self first for students.
const rows = 'tbody tr';
const pageTitle = 'Progress Seluruh Mahasiswa';

async function expectGlobalLabels(page: Page): Promise<void> {
    await expect(
        page.getByRole('heading', { name: pageTitle, exact: true }),
    ).toBeVisible();
    await expect(page).toHaveTitle(`${pageTitle} - SiTA`);
    await expect(page.locator('header').first()).toContainText(pageTitle);
    await expect(
        page
            .locator('a[href="/progres-mahasiswa"]')
            .filter({ hasText: pageTitle }),
    ).toHaveAttribute('href', '/progres-mahasiswa');
}

test.describe('Progress Seluruh Mahasiswa', () => {
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

    test.describe('mobile mahasiswa', () => {
        test.use({
            storageState: authStatePath('mahasiswa'),
            viewport: { width: 390, height: 844 },
        });

        test('keeps Anda readable and restores self priority after filter reset', async ({
            page,
        }) => {
            await page.goto('/progres-mahasiswa');
            const self = page.locator(rows).first();
            await expect(self).toContainText('Mahasiswa SiTA');
            await expect(self.getByText('Anda', { exact: true })).toBeVisible();
            await page
                .getByRole('button', { name: 'Filter', exact: true })
                .click();
            await page.getByRole('combobox', { name: 'Tambah filter' }).click();
            await page
                .getByRole('option', { name: 'Tahap mahasiswa', exact: true })
                .click();
            await page
                .getByRole('combobox', { name: 'Tahap mahasiswa', exact: true })
                .click();
            await page
                .getByRole('option', { name: /Belum ada tugas akhir/ })
                .click();
            await expect(page).toHaveURL(/phase=none/);
            await expect(
                page.locator('tbody').getByText('Anda', { exact: true }),
            ).toHaveCount(0);
            await page
                .getByRole('button', { name: 'Reset filter', exact: true })
                .click();
            await expect(page).not.toHaveURL(/phase=/);
            await expect(self).toContainText('Mahasiswa SiTA');
            await expect(self.getByText('Anda', { exact: true })).toBeVisible();
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
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

            await expectGlobalLabels(page);
            await expect(page.locator(rows).first()).toBeVisible();
            await expect(page.locator(rows).first()).toContainText(
                'Mahasiswa SiTA',
            );
            await expect(
                page.locator(rows).first().getByText('Anda', { exact: true }),
            ).toBeVisible();
            await expect(
                page.locator('tbody').getByText('Anda', { exact: true }),
            ).toHaveCount(1);

            const firstName = (
                await page.locator(`${rows} a p`).first().innerText()
            ).trim();

            // Search runs on the server and is carried in the URL.
            await page
                .getByPlaceholder('Cari nama, NIM, atau prodi...')
                .fill(firstName);
            await expect(page).toHaveURL(/search=/);
            await expect(page.locator(rows).first()).toContainText(firstName);
            await expect(
                page.locator(rows).first().getByText('Anda', { exact: true }),
            ).toBeVisible();

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
            await expect(page.locator(rows).first()).toContainText(
                'Mahasiswa SiTA',
            );

            // A real phase excludes self rather than appending it outside the result.
            await phasePills
                .getByRole('radio', { name: /Belum ada tugas akhir/ })
                .click();
            await expect(page).toHaveURL(/phase=none/);
            await expect(
                page.locator('tbody').getByText('Anda', { exact: true }),
            ).toHaveCount(0);
            await page.getByRole('button', { name: 'Reset filter' }).click();
            await expect(page).not.toHaveURL(/phase=/);
            await expect(page.locator(rows).first()).toContainText(
                'Mahasiswa SiTA',
            );
            await expect(
                page.locator(rows).first().getByText('Anda', { exact: true }),
            ).toBeVisible();
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
            await expect(
                page.locator('tbody').getByText('Anda', { exact: true }),
            ).toHaveCount(0);
        });
    });

    test.describe('dosen', () => {
        test.use({ storageState: authStatePath('dosen1') });

        test('opens the progress list', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expectGlobalLabels(page);
            await expect(
                page.locator('tbody').getByText('Anda', { exact: true }),
            ).toHaveCount(0);
        });
    });

    test.describe('kaprodi', () => {
        test.use({ storageState: authStatePath('kaprodi') });

        test('opens the progress list', async ({ page }) => {
            await page.goto('/progres-mahasiswa');

            await expectGlobalLabels(page);
            await expect(
                page.locator('tbody').getByText('Anda', { exact: true }),
            ).toHaveCount(0);
        });
    });

    for (const account of ['admin', 'superadmin'] as const) {
        test.describe(account, () => {
            test.use({ storageState: authStatePath(account) });

            test('uses the global list label without a self marker', async ({
                page,
            }) => {
                await page.goto('/progres-mahasiswa');
                await expectGlobalLabels(page);
                await expect(
                    page.locator('tbody').getByText('Anda', { exact: true }),
                ).toHaveCount(0);
            });
        });
    }
});
