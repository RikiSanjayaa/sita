import { expect, test } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

// Action-first dosen dashboard (issue #31). Counts and per-item rules are
// covered by Pest (DosenDashboardTest); this checks the page order and that an
// action opens its filtered list.
test.use({ storageState: authStatePath('dosen1') });

test('dosen dashboard leads with the work waiting and drops the sidebar-duplicate shortcuts', async ({
    page,
}) => {
    await page.goto('/dosen/dashboard');

    const queue = page.getByRole('region', { name: 'Perlu tindakan' });
    await expect(queue).toBeVisible();
    await expect(page.getByText('Aksi cepat')).toHaveCount(0);
    await expect(
        page.getByRole('region', { name: 'Hari ini & 7 hari ke depan' }),
    ).toBeVisible();
    await expect(
        page.getByRole('region', { name: 'Progres mahasiswa' }),
    ).toBeVisible();

    const firstAction = queue.getByRole('list').getByRole('link').first();
    if ((await firstAction.count()) === 0) {
        await expect(queue.getByText('Semua beres')).toBeVisible();
        return;
    }

    const href = await firstAction.getAttribute('href');
    await firstAction.click();
    await expect(page).toHaveURL(new RegExp(href!.split('?')[0]));
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});
