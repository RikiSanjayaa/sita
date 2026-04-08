import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { authStatePath } from '../support/thesis-fixtures';

function columnName(index: number): string {
    let current = index;
    let result = '';

    while (current > 0) {
        const remainder = (current - 1) % 26;

        result = String.fromCharCode(65 + remainder) + result;
        current = Math.floor((current - 1) / 26);
    }

    return result;
}

function escapeXml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function inlineTextNode(value: string): string {
    return `<t xml:space="preserve">${escapeXml(value)}</t>`;
}

function buildSheetXml(rows: string[][], headerRows: number[] = [1]): string {
    const maxColumns = rows.reduce(
        (current, row) => Math.max(current, row.length),
        1,
    );
    const lastColumn = columnName(maxColumns);
    const lastRow = Math.max(rows.length, 1);
    const dimension = `A1:${lastColumn}${lastRow}`;

    const sheetRows = rows
        .map((row, rowIndex) => {
            const excelRow = rowIndex + 1;
            const cells = Array.from(
                { length: maxColumns },
                (_, columnIndex) => {
                    const cellReference = `${columnName(columnIndex + 1)}${excelRow}`;
                    const cellValue = row[columnIndex] ?? '';
                    const style = headerRows.includes(excelRow) ? ' s="1"' : '';

                    return `<c r="${cellReference}" t="inlineStr"${style}><is>${inlineTextNode(cellValue)}</is></c>`;
                },
            ).join('');

            return `<row r="${excelRow}">${cells}</row>`;
        })
        .join('');

    return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="${dimension}"/><sheetData>${sheetRows}</sheetData></worksheet>`;
}

async function buildImportWorkbook(
    page: Page,
    fileName: string,
    rows: string[][],
): Promise<string> {
    const response = await page.request.get('/admin/import-template.xlsx');

    expect(response.ok()).toBeTruthy();

    const tempDirectory = fs.mkdtempSync(
        path.join(os.tmpdir(), 'sita-user-import-'),
    );
    const workbookPath = path.join(tempDirectory, fileName);
    const sheetXmlPath = path.join(tempDirectory, 'sheet1.xml');

    fs.writeFileSync(workbookPath, Buffer.from(await response.body()));
    fs.writeFileSync(sheetXmlPath, buildSheetXml(rows));

    execFileSync(
        'php',
        [
            '-r',
            '$zip = new ZipArchive; if ($zip->open($argv[1]) !== true) { fwrite(STDERR, "zip-open-failed"); exit(1); } $xml = file_get_contents($argv[2]); $zip->addFromString("xl/worksheets/sheet1.xml", $xml); $zip->close();',
            workbookPath,
            sheetXmlPath,
        ],
        { stdio: 'inherit' },
    );

    fs.unlinkSync(sheetXmlPath);

    return workbookPath;
}

async function cleanupWorkbook(filePath: string): Promise<void> {
    fs.rmSync(path.dirname(filePath), { recursive: true, force: true });
}

async function openImportModal(page: Page): Promise<void> {
    await page.goto('/admin/users');
    await expect(page).toHaveURL(/\/admin\/users/);
    await page.getByRole('button', { name: 'Import Excel' }).click();
    await expect(
        page.getByText('Import user dari Excel').first(),
    ).toBeVisible();
}

async function waitForUploadedWorkbook(
    page: Page,
    workbookPath: string,
): Promise<void> {
    const modal = page.locator('.fi-modal-window').filter({
        hasText: 'Import user dari Excel',
    });

    await expect(
        modal.getByText(path.basename(workbookPath)).first(),
    ).toBeVisible();
}

async function selectFilamentOption(
    page: Page,
    index: number,
    value: string,
): Promise<void> {
    const modal = page.locator('.fi-modal-window').filter({
        hasText: 'Import user dari Excel',
    });

    await modal.locator('.fi-select-input-btn').nth(index).click();
    await modal
        .locator(`.fi-select-input-option[data-value="${value}"]`)
        .click();
}

async function fillTableSearch(page: Page, query: string): Promise<void> {
    const searchInput = page.getByRole('searchbox', {
        name: 'Search',
        exact: true,
    });

    await expect(searchInput).toBeVisible();
    await searchInput.fill(query);
    await searchInput.press('Enter');
}

test.describe('Admin user import', () => {
    test('admin can import a valid mahasiswa workbook from the Filament modal', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });
        const workbookPath = await buildImportWorkbook(
            await adminContext.newPage(),
            'users-success.xlsx',
            [
                [
                    'nama',
                    'email',
                    'no_hp',
                    'role',
                    'password',
                    'nim',
                    'angkatan',
                    'konsentrasi',
                    'nik',
                    'kuota_bimbingan',
                ],
                [
                    'Import Mahasiswa Satu',
                    'import-mahasiswa-satu@sita.test',
                    '081111111111',
                    'mahasiswa',
                    'Rahasia123!',
                    '2210519001',
                    '2022',
                    'Jaringan',
                    '',
                    '',
                ],
                [
                    'Import Mahasiswa Dua',
                    'import-mahasiswa-dua@sita.test',
                    '082222222222',
                    'mahasiswa',
                    'Rahasia123!',
                    '2210519002',
                    '2023',
                    'Sistem Cerdas',
                    '',
                    '',
                ],
                [
                    'Import Mahasiswa Tiga',
                    'import-mahasiswa-tiga@sita.test',
                    '083333333333',
                    'mahasiswa',
                    'Rahasia123!',
                    '2210519003',
                    '2024',
                    'Computer Vision',
                    '',
                    '',
                ],
            ],
        );

        try {
            const page = await adminContext.newPage();
            await openImportModal(page);
            await page
                .locator('input[type="file"]')
                .setInputFiles(workbookPath);
            await waitForUploadedWorkbook(page, workbookPath);
            await selectFilamentOption(page, 0, 'mahasiswa');
            await selectFilamentOption(page, 1, '1');
            await page.getByRole('button', { name: 'Submit' }).click();

            await expect(
                page.getByText('Import Excel berhasil').first(),
            ).toBeVisible();
            await expect(
                page
                    .getByText('Diproses 3 baris, berhasil 3, gagal 0.')
                    .first(),
            ).toBeVisible();

            await fillTableSearch(page, 'import-mahasiswa-satu@sita.test');
            await expect(
                page
                    .locator('tr')
                    .filter({ hasText: 'Import Mahasiswa Satu' })
                    .first(),
            ).toBeVisible();
            await expect(
                page
                    .locator('tr')
                    .filter({ hasText: 'import-mahasiswa-satu@sita.test' })
                    .first(),
            ).toBeVisible();
        } finally {
            await adminContext.close();
            await cleanupWorkbook(workbookPath);
        }
    });

    test('admin sees partial import failures while valid rows are still imported', async ({
        browser,
    }) => {
        const adminContext = await browser.newContext({
            storageState: authStatePath('superadmin'),
        });
        const workbookPath = await buildImportWorkbook(
            await adminContext.newPage(),
            'users-partial.xlsx',
            [
                [
                    'nama',
                    'email',
                    'no_hp',
                    'role',
                    'password',
                    'nim',
                    'angkatan',
                    'konsentrasi',
                    'nik',
                    'kuota_bimbingan',
                ],
                [
                    'Import Valid Satu',
                    'import-valid-satu@sita.test',
                    '081111111110',
                    'mahasiswa',
                    'Rahasia123!',
                    '2210519010',
                    '2022',
                    'Jaringan',
                    '',
                    '',
                ],
                [
                    'Import Invalid Dua',
                    'import-invalid-dua@sita.test',
                    '082222222220',
                    'mahasiswa',
                    'Rahasia123!',
                    '',
                    '2023',
                    'Sistem Cerdas',
                    '',
                    '',
                ],
                [
                    'Import Valid Tiga',
                    'import-valid-tiga@sita.test',
                    '083333333330',
                    'mahasiswa',
                    'Rahasia123!',
                    '2210519030',
                    '2024',
                    'Computer Vision',
                    '',
                    '',
                ],
            ],
        );

        try {
            const page = await adminContext.newPage();
            await openImportModal(page);
            await page
                .locator('input[type="file"]')
                .setInputFiles(workbookPath);
            await waitForUploadedWorkbook(page, workbookPath);
            await selectFilamentOption(page, 0, 'mahasiswa');
            await selectFilamentOption(page, 1, '1');
            await page.getByRole('button', { name: 'Submit' }).click();

            await expect(
                page.getByText('Import Excel selesai dengan catatan').first(),
            ).toBeVisible();
            await expect(
                page
                    .getByText('Diproses 3 baris, berhasil 2, gagal 1.')
                    .first(),
            ).toBeVisible();
            await expect(
                page
                    .getByText('Baris 3: NIM wajib diisi untuk role mahasiswa.')
                    .first(),
            ).toBeVisible();

            await fillTableSearch(page, 'import-valid-satu@sita.test');
            await expect(
                page
                    .locator('tr')
                    .filter({ hasText: 'Import Valid Satu' })
                    .first(),
            ).toBeVisible();

            await fillTableSearch(page, 'import-invalid-dua@sita.test');
            await expect(
                page
                    .locator('tr')
                    .filter({ hasText: 'import-invalid-dua@sita.test' }),
            ).toHaveCount(0);
        } finally {
            await adminContext.close();
            await cleanupWorkbook(workbookPath);
        }
    });
});
