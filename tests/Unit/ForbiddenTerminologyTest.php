<?php

/*
 * Guards docs/GLOSSARY.md: retired academic labels must not come back into
 * application code. Take labels from App\Support\AcademicTerminology or
 * resources/js/lib/academic-terminology.ts instead.
 */

const FORBIDDEN_TERMINOLOGY = [
    'Proyek',
    'Proyek Tugas Akhir',
    'Ujian Akhir',
    'Ujian Proposal',
    'Ujian Tesis',
    'Sempro Tugas Akhir',
    'Seminar Proposal Tesis',
    'Seminar Proposal Skripsi',
    'Seminar Proposal Tugas Akhir',
    'Tiket',
];

it('keeps retired academic labels out of app code', function (string $phrase): void {
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach (['app', 'resources/js'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! preg_match('/\.(php|tsx?)$/', $file->getFilename())) {
                continue;
            }

            foreach (file($file->getPathname()) as $index => $line) {
                if (preg_match('/\b'.preg_quote($phrase, '/').'\b/iu', $line)) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1).':'.($index + 1);
                }
            }
        }
    }

    expect($offenders)->toBe([], "\"{$phrase}\" is retired; see docs/GLOSSARY.md");
})->with(FORBIDDEN_TERMINOLOGY);
