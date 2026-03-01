<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use ZipArchive;

class UserImportTemplateDownloadController extends Controller
{
    public function __invoke(string $format): Response
    {
        $rows = [
            ['name', 'email', 'role', 'password', 'nim', 'prodi', 'angkatan', 'nik'],
            ['Muhammad Akbar', 'akbar@sita.test', 'mahasiswa', '', '2210510001', 'Informatika', '2022', ''],
            ['Dr. Budi Santoso', 'budi@sita.test', 'dosen', '', '', 'Informatika', '', '7301010101010001'],
            ['Admin SITA', 'admin2@sita.test', 'admin', '', '', '', '', ''],
        ];

        if ($format === 'xlsx') {
            $xlsx = $this->asXlsx($rows);

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="user-import-template.xlsx"',
            ]);
        }

        $csv = $this->asCsv($rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="user-import-template.csv"',
        ]);
    }

    private function asCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false ? '' : $content;
    }

    private function asXlsx(array $rows): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'sita-import-xlsx-');

        if ($zipPath === false) {
            return '';
        }

        $zip = new ZipArchive;
        $zipOpenResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($zipOpenResult !== true) {
            @unlink($zipPath);

            return '';
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();

        $binary = file_get_contents($zipPath);
        @unlink($zipPath);

        return $binary === false ? '' : $binary;
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private function rootRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Users" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function workbookRelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
    }

    private function sheetXml(array $rows): string
    {
        $maxColumns = 1;

        foreach ($rows as $row) {
            $maxColumns = max($maxColumns, count($row));
        }

        $lastColumn = $this->columnName($maxColumns);
        $lastRow = max(count($rows), 1);
        $dimension = sprintf('A1:%s%d', $lastColumn, $lastRow);

        $sheetRows = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $cells = '';

            for ($columnIndex = 0; $columnIndex < $maxColumns; $columnIndex++) {
                $columnName = $this->columnName($columnIndex + 1);
                $cellReference = $columnName.$excelRow;
                $cellValue = (string) ($row[$columnIndex] ?? '');

                $cells .= sprintf(
                    '<c r="%s" t="inlineStr"><is>%s</is></c>',
                    $cellReference,
                    $this->inlineTextNode($cellValue),
                );
            }

            $sheetRows .= sprintf('<row r="%d">%s</row>', $excelRow, $cells);
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="%s"/>'
            .'<sheetData>%s</sheetData>'
            .'</worksheet>',
            $dimension,
            $sheetRows,
        );
    }

    private function inlineTextNode(string $value): string
    {
        $escapedValue = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $requiresPreserve = $value !== trim($value);

        if ($requiresPreserve) {
            return '<t xml:space="preserve">'.$escapedValue.'</t>';
        }

        return '<t>'.$escapedValue.'</t>';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }
}
