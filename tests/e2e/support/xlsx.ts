import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { expect, type Page } from '@playwright/test';

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

function buildSheetXml(rows: string[][]): string {
    const maxColumns = rows.reduce(
        (current, row) => Math.max(current, row.length),
        1,
    );
    const dimension = `A1:${columnName(maxColumns)}${Math.max(rows.length, 1)}`;

    const sheetRows = rows
        .map((row, rowIndex) => {
            const excelRow = rowIndex + 1;
            const cells = Array.from(
                { length: maxColumns },
                (_, columnIndex) => {
                    const reference = `${columnName(columnIndex + 1)}${excelRow}`;
                    const style = excelRow === 1 ? ' s="1"' : '';

                    return `<c r="${reference}" t="inlineStr"${style}><is><t xml:space="preserve">${escapeXml(row[columnIndex] ?? '')}</t></is></c>`;
                },
            ).join('');

            return `<row r="${excelRow}">${cells}</row>`;
        })
        .join('');

    return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="${dimension}"/><sheetData>${sheetRows}</sheetData></worksheet>`;
}

/**
 * Downloads an import template through the signed-in page, replaces its first
 * sheet with `rows` (header row first) and returns the workbook path.
 */
export async function buildImportWorkbook(
    page: Page,
    templatePath: string,
    fileName: string,
    rows: string[][],
): Promise<string> {
    const response = await page.request.get(templatePath);

    expect(response.ok()).toBeTruthy();

    const tempDirectory = fs.mkdtempSync(
        path.join(os.tmpdir(), 'sita-import-'),
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

export function cleanupWorkbook(filePath: string): void {
    fs.rmSync(path.dirname(filePath), { recursive: true, force: true });
}
