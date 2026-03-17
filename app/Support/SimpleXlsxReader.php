<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class SimpleXlsxReader
{
    /**
     * @return array<int, array<int, string>>
     */
    public function rows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! is_string($worksheetXml) || $worksheetXml === '') {
            throw new RuntimeException('Sheet pertama pada file Excel tidak ditemukan.');
        }

        $document = new DOMDocument;

        if (! @$document->loadXML($worksheetXml)) {
            throw new RuntimeException('Isi sheet Excel tidak dapat dibaca.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];

        foreach ($xpath->query('//main:sheetData/main:row') ?: [] as $rowNode) {
            if (! $rowNode instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($xpath->query('./main:c', $rowNode) ?: [] as $cellNode) {
                if (! $cellNode instanceof DOMElement) {
                    continue;
                }

                $reference = $cellNode->getAttribute('r');
                $columnIndex = $this->columnIndexFromReference($reference);

                if ($columnIndex === null) {
                    continue;
                }

                $cells[$columnIndex] = $this->cellValue($xpath, $cellNode, $sharedStrings);
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $lastIndex = max(array_keys($cells));
            $normalized = [];

            for ($index = 0; $index <= $lastIndex; $index++) {
                $normalized[] = trim((string) ($cells[$index] ?? ''));
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $document = new DOMDocument;

        if (! @$document->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];

        foreach ($xpath->query('//main:si') ?: [] as $item) {
            $value = '';

            foreach ($xpath->query('.//main:t', $item) ?: [] as $textNode) {
                $value .= $textNode->textContent;
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(DOMXPath $xpath, DOMElement $cellNode, array $sharedStrings): string
    {
        $type = $cellNode->getAttribute('t');

        if ($type === 'inlineStr') {
            $value = '';

            foreach ($xpath->query('.//main:t', $cellNode) ?: [] as $textNode) {
                $value .= $textNode->textContent;
            }

            return $value;
        }

        $valueNode = $xpath->query('./main:v', $cellNode)?->item(0);
        $value = $valueNode?->textContent ?? '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $value;
    }

    private function columnIndexFromReference(string $reference): ?int
    {
        if (! preg_match('/^[A-Z]+/i', $reference, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[0]);
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
