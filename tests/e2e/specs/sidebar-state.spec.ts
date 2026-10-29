import { expect, test } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

test.use({
    storageState: authStatePath('mahasiswa'),
    viewport: { width: 1280, height: 800 },
});

test('uses the latest sidebar choice on prefetched navigation, history and reload', async ({
    page,
}) => {
    await page.goto('/mahasiswa/dashboard');
    const sidebar = page.locator('[data-slot="sidebar"][data-state]');
    const toggle = page.locator('[data-slot="sidebar-trigger"]');
    await expect(sidebar).toHaveAttribute('data-state', 'expanded');

    const destination = sidebar.locator('a[href="/progres-mahasiswa"]');
    const prefetch = page.waitForResponse(
        (response) =>
            response.url().includes('/progres-mahasiswa') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await destination.hover();
    const response = await prefetch;
    expect((await response.json()).props.sidebarOpen).toBe(true);
    await toggle.click();
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');
    await destination.click();
    await expect(page).toHaveURL(/\/progres-mahasiswa$/);
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');

    await page.goBack();
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');
    await page.goForward();
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');
    await page.reload();
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');

    // The inverse transition must also override a page prefetched while collapsed.
    const dashboard = sidebar.locator('a[href="/mahasiswa/dashboard"]');
    const collapsedPrefetch = page.waitForResponse(
        (response) =>
            response.url().endsWith('/mahasiswa/dashboard') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await dashboard.hover();
    expect((await (await collapsedPrefetch).json()).props.sidebarOpen).toBe(
        false,
    );
    await toggle.click();
    await dashboard.click();
    await expect(page).toHaveURL(/\/mahasiswa\/dashboard$/);
    await expect(sidebar).toHaveAttribute('data-state', 'expanded');
    await page.reload();
    await expect(sidebar).toHaveAttribute('data-state', 'expanded');
});
