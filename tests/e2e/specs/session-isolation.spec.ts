import { expect, test } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

test('playwright keeps admin, mahasiswa, and dosen sessions isolated in parallel contexts', async ({
    browser,
}) => {
    const adminContext = await browser.newContext({
        storageState: authStatePath('admin'),
    });
    const mahasiswaContext = await browser.newContext({
        storageState: authStatePath('mahasiswa'),
    });
    const dosenContext = await browser.newContext({
        storageState: authStatePath('dosen1'),
    });

    try {
        const adminPage = await adminContext.newPage();
        const mahasiswaPage = await mahasiswaContext.newPage();
        const dosenPage = await dosenContext.newPage();

        await Promise.all([
            adminPage.goto('/admin'),
            mahasiswaPage.goto('/mahasiswa/dashboard'),
            dosenPage.goto('/dosen/dashboard'),
        ]);

        await expect(adminPage).toHaveURL(/\/admin$/);
        await expect(adminPage).toHaveTitle(/Dashboard Admin/i);

        await expect(mahasiswaPage).toHaveURL(/\/mahasiswa\/dashboard$/);
        await expect(mahasiswaPage).toHaveTitle(/Dashboard Mahasiswa/i);

        await expect(dosenPage).toHaveURL(/\/dosen\/dashboard$/);
        await expect(dosenPage).toHaveTitle(/Dashboard Dosen/i);
    } finally {
        await adminContext.close();
        await mahasiswaContext.close();
        await dosenContext.close();
    }
});
