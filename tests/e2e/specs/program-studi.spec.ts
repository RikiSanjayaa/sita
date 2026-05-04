import { expect, test } from '@playwright/test';

import { runArtisan } from '../support/db';
import { playwrightAccounts } from '../support/thesis-fixtures';

function resetIlmuKomputerConcentrations(): void {
    runArtisan([
        'tinker',
        `--execute=$program = App\\Models\\ProgramStudi::query()->where("slug", "ilkom")->firstOrFail(); $program->forceFill(["concentrations" => App\\Models\\ProgramStudi::ILMU_KOMPUTER_CONCENTRATIONS])->save();`,
    ]);
}

test.describe('Program Studi management', () => {
    test('super admin can update konsentrasi and the public advisor directory reflects the new grouping', async ({
        browser,
    }) => {
        resetIlmuKomputerConcentrations();

        const adminContext = await browser.newContext();

        const newConcentration = `Keamanan Siber Playwright ${Date.now()}`;

        try {
            const adminPage = await adminContext.newPage();

            await adminPage.goto('/admin');
            await adminPage
                .locator('#form\\.email')
                .fill(playwrightAccounts.superadmin.email);
            await adminPage
                .locator('#form\\.password')
                .fill(playwrightAccounts.superadmin.password);
            await adminPage
                .getByRole('button', { name: /Masuk ke dashboard/i })
                .click();
            await expect(adminPage).toHaveURL(/\/admin$/);

            await adminPage.goto('/admin/program-studis/1/edit');
            await expect(adminPage).toHaveURL(
                /\/admin\/program-studis\/1\/edit/,
            );
            const nameInput = adminPage.getByRole('textbox', {
                name: 'Name*',
            });
            const concentrationsInput = adminPage.getByRole('combobox', {
                name: 'Konsentrasi*',
            });

            await expect(nameInput).toBeVisible();
            await expect(nameInput).toHaveValue('Ilmu Komputer');

            await concentrationsInput.fill(newConcentration);
            await concentrationsInput.press('Enter');
            await adminPage
                .getByRole('button', { name: 'Save changes', exact: true })
                .click();

            await expect(
                adminPage.getByRole('heading', { name: 'Edit Ilmu Komputer' }),
            ).toBeVisible();
            await expect(
                adminPage.getByText('Ilmu Komputer').first(),
            ).toBeVisible();
            await expect(
                adminPage.getByText(newConcentration).first(),
            ).toBeVisible();

            await adminPage.goto('/admin/users/create');
            await expect(adminPage).toHaveURL(/\/admin\/users\/create/);
            await adminPage.locator('select').first().selectOption('mahasiswa');
            await adminPage.waitForTimeout(1000);
            await expect(adminPage.getByText('Prodi*').first()).toBeVisible();
            await adminPage
                .locator('button')
                .filter({ hasText: 'Select an option' })
                .first()
                .click();
            await adminPage
                .getByRole('option', { name: 'Ilmu Komputer', exact: true })
                .click();
            await adminPage.waitForTimeout(1000);
            await adminPage
                .locator('button')
                .filter({ hasText: 'Select an option' })
                .first()
                .click();
            await expect(
                adminPage.getByRole('option', {
                    name: newConcentration,
                    exact: true,
                }),
            ).toBeVisible();
        } finally {
            await adminContext.close();
        }
    });
});
