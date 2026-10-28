import { expect, test } from '@playwright/test';

for (const width of [1280, 390]) {
    test(`password visibility preserves input and keyboard login at ${width}px (#177)`, async ({
        page,
    }) => {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/login');
        const password = page.getByLabel('Kata sandi', { exact: true });
        await page
            .getByLabel('Email', { exact: true })
            .fill('mahasiswa@sita.test');
        await password.fill('wrong-password');
        await expect(password).toHaveAttribute('type', 'password');
        await password.press('Tab');
        const show = page.getByRole('button', { name: 'Tampilkan kata sandi' });
        await expect(show).toBeFocused();
        await show.press('Enter');
        await expect(password).toHaveAttribute('type', 'text');
        await expect(password).toHaveValue('wrong-password');
        await expect(page).toHaveURL(/\/login$/);
        const hide = page.getByRole('button', {
            name: 'Sembunyikan kata sandi',
        });
        await hide.press('Space');
        await expect(password).toHaveAttribute('type', 'password');
        await expect(password).toHaveValue('wrong-password');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(
            page
                .getByLabel('Email', { exact: true })
                .locator('..')
                .locator('p'),
        ).toBeVisible();
        await password.fill('password');
        await page
            .getByRole('button', { name: 'Tampilkan kata sandi' })
            .click();
        await expect(password).toHaveValue('password');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(page).toHaveURL(/\/mahasiswa\/dashboard$/);
    });
}
