<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use ZipArchive;

class UserImportTemplateDownloadController extends Controller
{
    public function __invoke(string $format): Response
    {
        abort_unless($format === 'xlsx', 404);

        $templateRows = [
            ['nama', 'email', 'no_hp', 'role', 'password', 'nim', 'angkatan', 'konsentrasi', 'nik', 'kuota_bimbingan'],
            ['Muhammad Akbar', 'akbar@sita.test', '081234567890', 'mahasiswa', 'Rahasia123!', '2210510001', '2022', 'Jaringan', '', ''],
            ['Dr. Budi Santoso', 'budi@sita.test', '081298765432', 'dosen', 'Rahasia123!', '', '', 'Sistem Cerdas', '7301010101010001', '12'],
            ['Admin SITA', 'admin2@sita.test', '', 'admin', 'Rahasia123!', '', '', '', '', ''],
        ];

        $guideRows = [
            ['Panduan Import User', '', ''],
            ['Peran', 'Field wajib', 'Catatan'],
            ['Mahasiswa', 'nama, email, password, nim, angkatan, konsentrasi', 'NIK dan kuota bimbingan dikosongkan. Program Studi dipilih dari dropdown import.'],
            ['Dosen', 'nama, email, password, nik, konsentrasi', 'NIM dan angkatan dikosongkan. Kuota bimbingan boleh diisi jika diperlukan.'],
            ['Admin', 'nama, email, password', 'Kolom NIM, angkatan, konsentrasi, NIK, dan kuota bimbingan dikosongkan.'],
            ['Nilai role', 'mahasiswa, dosen, admin', 'Gunakan salah satu nilai role ini agar data masuk ke profil yang sesuai.'],
        ];

        $xlsx = $this->asXlsx([
            [
                'name' => 'Template Import',
                'rows' => $templateRows,
                'headerRows' => [1],
            ],
            [
                'name' => 'Panduan',
                'rows' => $guideRows,
                'headerRows' => [1, 2],
            ],
        ]);

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="user-import-template.xlsx"',
        ]);
    }

    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, string>>, headerRows: array<int, int>}>  $sheets
     */
    private function asXlsx(array $sheets): string
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

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml($sheets));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml($sheets));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($sheets as $index => $sheet) {
            $zip->addFromString(
                sprintf('xl/worksheets/sheet%d.xml', $index + 1),
                $this->sheetXml($sheet['rows'], $sheet['headerRows']),
            );
        }

        $zip->close();

        $binary = file_get_contents($zipPath);
        @unlink($zipPath);

        return $binary === false ? '' : $binary;
    }

    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, string>>, headerRows: array<int, int>}>  $sheets
     */
    private function contentTypesXml(array $sheets): string
    {
        $worksheetOverrides = collect($sheets)
            ->values()
            ->map(fn(array $sheet, int $index): string => sprintf(
                '  <Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                $index + 1,
            ))
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
{$worksheetOverrides}
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

    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, string>>, headerRows: array<int, int>}>  $sheets
     */
    private function workbookXml(array $sheets): string
    {
        $sheetNodes = collect($sheets)
            ->values()
            ->map(fn(array $sheet, int $index): string => sprintf(
                '    <sheet name="%s" sheetId="%d" r:id="rId%d"/>',
                htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $index + 1,
                $index + 1,
            ))
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
{$sheetNodes}
  </sheets>
</workbook>
XML;
    }

    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, string>>, headerRows: array<int, int>}>  $sheets
     */
    private function workbookRelsXml(array $sheets): string
    {
        $worksheetRelations = collect($sheets)
            ->values()
            ->map(fn(array $sheet, int $index): string => sprintf(
                '  <Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>',
                $index + 1,
                $index + 1,
            ))
            ->implode("\n");

        $stylesRelation = sprintf(
            '  <Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>',
            count($sheets) + 1,
        );

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
{$worksheetRelations}
{$stylesRelation}
</Relationships>
XML;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, int>  $headerRows
     */
    private function sheetXml(array $rows, array $headerRows = []): string
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
                $style = in_array($excelRow, $headerRows, true) ? ' s="1"' : '';

                $cells .= sprintf(
                    '<c r="%s" t="inlineStr"%s><is>%s</is></c>',
                    $cellReference,
                    $style,
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

    private function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font>
      <sz val="11"/>
      <color theme="1"/>
      <name val="Aptos"/>
      <family val="2"/>
    </font>
    <font>
      <b/>
      <sz val="11"/>
      <color rgb="FFFFFFFF"/>
      <name val="Aptos"/>
      <family val="2"/>
    </font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FF1D4ED8"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
  </fills>
  <borders count="1">
    <border>
      <left/><right/><top/><bottom/><diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center"/>
    </xf>
  </cellXfs>
</styleSheet>
XML;
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
