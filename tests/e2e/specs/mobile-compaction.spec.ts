import { execFileSync } from 'node:child_process';

import { expect, test, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

async function noOverflow(page: Page, width: number): Promise<void> {
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(width);
}

function profileIds(): number[] {
    const result = execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute=echo json_encode([App\\Models\\User::query()->where("email", "mahasiswa@sita.test")->firstOrFail()->id, App\\Models\\User::query()->where("email", "dosen@sita.test")->firstOrFail()->id]);',
        ],
        {
            cwd: repositoryRoot,
            env: playwrightEnvironment(),
            encoding: 'utf8',
        },
    );
    return JSON.parse(result.trim()) as number[];
}

test.describe('mobile compaction (#167, #168)', () => {
    test.use({ storageState: authStatePath('kaprodi') });

    test('inline filters combine, remove and reset criteria without losing search or keyboard focus', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/kaprodi/mahasiswa');
        const search = page.getByRole('searchbox', {
            name: 'Cari nama atau NIM',
        });
        const filter = page.getByRole('button', { name: /^Filter(?:,|$)/ });
        const panel = page.getByRole('region', { name: 'Pilihan filter' });
        const rows = page.locator('main table tbody tr');
        await expect(rows).toHaveCount(5);
        const original = await rows.allTextContents();
        await filter.click();
        await expect(filter).toHaveAttribute('aria-expanded', 'true');
        await expect(panel.getByRole('combobox')).toHaveCount(1);
        await panel.getByRole('combobox', { name: 'Tambah filter' }).click();
        await page
            .getByRole('option', { name: 'Angkatan', exact: true })
            .click();
        await panel.getByRole('combobox', { name: 'Filter angkatan' }).click();
        await page.getByRole('option', { name: '2022', exact: true }).click();
        await expect(page).toHaveURL(/activeAngkatan=2022/);
        await expect(filter).toHaveAccessibleName('Filter, 1 aktif');
        await expect(rows.first()).toContainText('2022');
        await panel.getByRole('combobox', { name: 'Tambah filter' }).click();
        await page
            .getByRole('option', { name: 'Konsentrasi', exact: true })
            .click();
        await panel
            .getByRole('combobox', { name: 'Filter konsentrasi' })
            .click();
        await page.getByRole('option', { name: /^Jaringan/ }).click();
        await expect(page).toHaveURL(/activeKonsentrasi=Jaringan/);
        await expect(page).toHaveURL(/activeAngkatan=2022/);
        await expect(filter).toHaveAccessibleName('Filter, 2 aktif');
        await panel
            .getByRole('button', { name: 'Hapus filter Angkatan' })
            .click();
        await expect(page).not.toHaveURL(/activeAngkatan=2022/);
        await expect(page).toHaveURL(/activeKonsentrasi=Jaringan/);
        await filter.click();
        await expect(panel).not.toBeVisible();
        await filter.click();
        await expect(
            panel.getByRole('combobox', { name: 'Filter konsentrasi' }),
        ).toHaveText(/Jaringan/);
        await page.keyboard.press('Escape');
        await expect(filter).toBeFocused();
        await expect(panel).not.toBeVisible();
        await filter.click();
        await panel.getByRole('button', { name: 'Reset filter' }).click();
        await expect(filter).toHaveAccessibleName('Filter');
        await expect(page).not.toHaveURL(/activeKonsentrasi=Jaringan/);
        await expect(rows).toHaveCount(5);
        expect(await rows.allTextContents()).toEqual(original);
        await expect(search).toBeVisible();
        await search.fill('zzz-compaction-empty-170');
        await expect(rows).toHaveCount(0);
        await noOverflow(page, 390);
    });

    test('client pagination keeps adjacent records distinct, honors manual size and resets validly', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 844 });
        await page.goto('/kaprodi/mahasiswa');
        const sizes = page.getByRole('combobox', { name: 'Per halaman' });
        const rows = page.locator('main table tbody tr');
        await expect(sizes).toHaveValue('10');
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(sizes).toHaveValue('5');
        await expect(rows).toHaveCount(5);
        const first = await rows
            .locator('td')
            .locator('a[href*="/profil/"]')
            .allTextContents();
        await page
            .getByRole('button', { name: 'Berikutnya', exact: true })
            .first()
            .click();
        await expect(
            page.getByRole('navigation', { name: 'Navigasi halaman' }).first(),
        ).toContainText('2 /');
        const second = await rows
            .locator('a[href*="/profil/"]')
            .allTextContents();
        expect(second.filter((name) => first.includes(name))).toEqual([]);
        await sizes.selectOption('10');
        await expect(rows).toHaveCount(10);
        await page.setViewportSize({ width: 1280, height: 844 });
        await expect(sizes).toHaveValue('10');
        for (const width of [320, 390]) {
            await page.setViewportSize({ width, height: 844 });
            await expect(sizes).toHaveValue('10');
            const top = page.locator('[data-slot="table-pagination"]').first();
            const bounds = await top.boundingBox();
            const controls = [
                sizes,
                top.getByRole('button', { name: 'Sebelumnya', exact: true }),
                top.getByRole('button', { name: 'Berikutnya', exact: true }),
            ];
            for (const control of controls) {
                const box = await control.boundingBox();
                expect(box!.height).toBeGreaterThanOrEqual(44);
                expect(box!.width).toBeGreaterThanOrEqual(44);
                expect(
                    Math.abs(
                        box!.y +
                            box!.height / 2 -
                            bounds!.y -
                            bounds!.height / 2,
                    ),
                ).toBeLessThan(2);
            }
            await noOverflow(page, width);
        }
    });

    test('server pages request five full records and retain filters, manual choices and resize defaults', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 844 });
        await page.goto('/progres-mahasiswa');
        const sizes = page.getByRole('combobox', { name: 'Per halaman' });
        const rows = page.locator('main table tbody tr');
        await expect(sizes).toHaveValue('15');
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(sizes).toHaveValue('5');
        await expect(page).toHaveURL(/per_page=5/);
        await expect(rows).toHaveCount(5);
        const first = await rows
            .locator('a[href*="/profil/"]')
            .allTextContents();
        await page
            .getByRole('button', { name: 'Berikutnya', exact: true })
            .first()
            .click();
        await expect(page).toHaveURL(/page=2/);
        await expect(rows).toHaveCount(5);
        const second = await rows
            .locator('a[href*="/profil/"]')
            .allTextContents();
        expect(second.filter((name) => first.includes(name))).toEqual([]);
        await page.setViewportSize({ width: 1280, height: 844 });
        await expect(sizes).toHaveValue('15');
        await expect(page).toHaveURL(/page=1/);
        await sizes.selectOption('10');
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(sizes).toHaveValue('10');
        await expect(rows).toHaveCount(10);
        await page.getByRole('button', { name: /^Filter(?:,|$)/ }).click();
        const panel = page.getByRole('region', { name: 'Pilihan filter' });
        await panel.getByRole('combobox', { name: 'Tambah filter' }).click();
        await page
            .getByRole('option', { name: 'Angkatan', exact: true })
            .click();
        await panel
            .getByRole('combobox', { name: 'Angkatan', exact: true })
            .click();
        await page.getByRole('option', { name: '2022', exact: true }).click();
        await expect(page).toHaveURL(/angkatan=2022/);
        await expect(sizes).toHaveValue('10');
        await page.reload();
        await expect(sizes).toHaveValue('10');
        await expect(page).toHaveURL(/angkatan=2022/);
        await noOverflow(page, 390);
    });

    test('student and lecturer profile facts use readable paired columns at 320 and 390', async ({
        page,
    }) => {
        for (const id of profileIds()) {
            await page.goto(`/profil/${id}`);
            for (const width of [390, 320]) {
                await page.setViewportSize({ width, height: 844 });
                const section = page.locator('section').filter({
                    has: page.getByRole('heading', {
                        name: 'Detail akademik dan peran',
                        exact: true,
                    }),
                });
                const facts = section.locator('dl').first();
                await expect(facts).toBeVisible();
                const positions = await facts
                    .locator(':scope > div')
                    .evaluateAll((items) =>
                        items.map((item) => {
                            const rect = item.getBoundingClientRect();
                            return {
                                x: Math.round(rect.x),
                                width: rect.width,
                                label: item.querySelector('dt')?.textContent,
                            };
                        }),
                    );
                expect(
                    new Set(positions.map((position) => position.x)).size,
                ).toBe(2);
                expect(positions.every((position) => position.width > 0)).toBe(
                    true,
                );
                for (const button of await facts.getByRole('button').all()) {
                    const rect = await button.boundingBox();
                    expect(rect!.height).toBeGreaterThanOrEqual(44);
                    expect(rect!.width).toBeGreaterThanOrEqual(44);
                }
                await noOverflow(page, width);
            }
        }
    });
});
