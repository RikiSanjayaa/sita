import { expect, test } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Covers issue #21: a student whose mentorship ticket has expired sees the
// banner and cannot request bimbingan, and Kaprodi sees the "Tiket habis"
// badge. The expired student is seeded by PlaywrightScenarioSeeder and is
// dedicated to this spec, so other specs are not affected.

test.describe('mahasiswa dengan tiket habis', () => {
    test.use({
        storageState: authStatePath(thesisScenarios.expiredTicket.studentKey),
    });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('melihat peringatan dan tidak bisa mengajukan bimbingan', async ({
        page,
    }) => {
        await page.goto('/mahasiswa/jadwal-bimbingan');

        await expect(page.getByText('Tiket bimbingan habis')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Ajukan Bimbingan' }),
        ).toBeDisabled();
    });
});

test.describe('kaprodi melihat tiket habis', () => {
    test.use({ storageState: authStatePath('kaprodi') });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('daftar mahasiswa menandai tiket habis', async ({ page }) => {
        await page.goto('/kaprodi/mahasiswa');
        await page.getByPlaceholder('Cari nama atau NIM...').fill('Tiara');

        await expect(page.getByText('Tiket habis').first()).toBeVisible();
    });
});
