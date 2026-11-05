<?php

namespace App\Support;

use DOMDocument;
use RuntimeException;
use ZipArchive;

final class SidangOfficeTemplate
{
    public static function stream(string $template, callable $fill): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sita-office-');
        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan berkas unduhan.');
        }

        try {
            if (! copy(resource_path('templates/sidang/'.$template), $path)) {
                throw new RuntimeException('Template sidang tidak dapat dibaca.');
            }
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Template sidang tidak valid.');
            }
            try {
                $fill($zip);
            } finally {
                $zip->close();
            }
            if (readfile($path) === false) {
                throw new RuntimeException('Berkas sidang tidak dapat diunduh.');
            }
        } finally {
            unlink($path);
        }
    }

    public static function readXml(ZipArchive $zip, string $entry): DOMDocument
    {
        $source = $zip->getFromName($entry);
        $xml = new DOMDocument;
        if ($source === false || ! $xml->loadXML($source, LIBXML_NONET)) {
            throw new RuntimeException('Bagian template sidang tidak valid.');
        }

        return $xml;
    }

    public static function saveXml(ZipArchive $zip, string $entry, DOMDocument $xml): void
    {
        if (! $zip->addFromString($entry, $xml->saveXML())) {
            throw new RuntimeException('Bagian template sidang tidak dapat disimpan.');
        }
    }
}
