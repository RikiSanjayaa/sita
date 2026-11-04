<?php

namespace App\Services;

use App\Models\ProgramStudi;
use App\Support\AcademicTerminology;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;

final class SidangReportWriter
{
    public function write(iterable $defenses, string $period): void
    {
        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::Legal, fitToHeight: 0, fitToWidth: 1));
        foreach ([5, 25, 16, 13, 20, 43, 15, 24, 15, 16, 24, 12, 12, 10, 14] as $column => $width) {
            $options->setColumnWidth($width, $column + 1);
        }
        $writer = new Writer($options);
        $writer->openToFile('php://output');
        $title = (new Style)->setFontBold()->setFontSize(14);
        $header = (new Style)->setFontBold()->setBackgroundColor('E2E8F0')->setShouldWrapText();
        $body = (new Style)->setShouldWrapText();
        $sheets = [];
        try {
            foreach ($defenses as $defense) {
                $project = $defense->project;
                $student = $project->student;
                $profile = $student?->mahasiswaProfile;
                $program = $project->programStudi;
                $key = $program->id.'-'.($profile?->degree_level ?? '');
                $paper = $defense->sidang_paper;
                if (! isset($sheets[$key])) {
                    $sheet = $sheets === [] ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                    $name = preg_replace('~[\\\\/*?:\[\]]~u', ' ', $program->name);
                    $sheet->setName(mb_substr(trim($name, " '"), 0, 20).' '.strtoupper($profile?->degree_level ?? '').' '.(count($sheets) + 1));
                    $sheet->setSheetView((new SheetView)->setFreezeRow(6));
                    $terms = AcademicTerminology::forProject($project);
                    $writer->addRow(Row::fromValues(['Rekap Nilai '.$terms['finalWork'].' - '.$program->name], $title));
                    $writer->addRow(Row::fromValues([$paper['settings']['heading'] ?? SidangSettings::DEFAULTS['heading'], $program->faculty?->name]));
                    $writer->addRow(Row::fromValues(['Periode', $period ?: 'Semua tanggal sidang']));
                    $writer->addRow(Row::fromValues(['Dicetak', WitaDateTime::format(now(), 'd/m/Y')]));
                    $writer->addRow(Row::fromValues([' ']));
                    $writer->addRow(Row::fromValues([
                        'No.', 'Nama Mahasiswa', 'NIM', 'Jenis Anggota', 'Nomor SK', 'Judul',
                        'NIDN Pembimbing', 'Nama Pembimbing', 'Pembimbing Ke',
                        'NIDN Penilai', 'Nama Penilai', 'Nilai Penilai', 'Nilai Akhir', 'Grade', 'Percobaan',
                    ], $header));
                    $sheets[$key] = ['sheet' => $sheet, 'program' => $program, 'index' => 0];
                }
                $writer->setCurrentSheet($sheets[$key]['sheet']);
                $number = ++$sheets[$key]['index'];
                $supervisors = $defense->examiners->filter(fn ($examiner) => $examiner->isSupervisorRole())->sortBy('role')->values();
                $scoreRows = array_values($paper['scores']);
                $rowNumber = 7 + ($number - 1) * 3;
                foreach ([0, 1, 2, 3, 4, 5, 12, 13, 14] as $column) {
                    $options->mergeCells($column, $rowNumber, $column, $rowNumber + 2, $sheets[$key]['sheet']->getIndex());
                }
                foreach ($scoreRows as $index => $score) {
                    $supervisor = $supervisors->get($index);
                    $assessor = $defense->examiners->find($score['panel_id'])?->lecturer;
                    $first = $index === 0;
                    $writer->addRow(Row::fromValues([
                        $first ? $number : null, $first ? $student?->name : null,
                        $first && $profile?->nim !== null ? (string) $profile->nim : null,
                        $first ? 'Personal' : null, $first ? $defense->sidang_sk : null,
                        $first ? $defense->titleVersion?->title_id : null,
                        $supervisor?->lecturer?->dosenProfile?->sidang_nidn,
                        $supervisor?->lecturer?->name,
                        $supervisor === null ? null : ($supervisor->role === 'primary_supervisor' ? 'I' : 'II'),
                        $score['nidn'] ?? $assessor?->dosenProfile?->sidang_nidn, $score['name'], (float) $score['value'],
                        $first ? (float) $paper['value'] : null, $first ? $paper['grade'] : null,
                        $first ? $defense->attempt_no : null,
                    ], $body));
                }
            }
            if ($sheets === []) {
                $writer->getCurrentSheet()->setName('Rekap Nilai Sidang');
                $writer->addRow(Row::fromValues(['Tidak ada hasil sidang terkonfirmasi yang sesuai dengan filter.'], $title));
            }
            foreach ($sheets as $sheet) {
                $writer->setCurrentSheet($sheet['sheet']);
                $this->writeSignatures($writer, $sheet['program']);
            }
        } finally {
            $writer->close();
        }
    }

    private function writeSignatures(Writer $writer, ProgramStudi $program): void
    {
        $settings = SidangSettings::forProgram($program);
        $head = $program->kaprodiAssignments->firstWhere('is_primary', true)?->user;
        $writer->addRow(Row::fromValues([' ']));
        $writer->addRow(Row::fromValues(['Mengetahui', null, null, null, null, 'Disetujui oleh', null, null, null, $settings['city'].', '.WitaDateTime::format(now(), 'd/m/Y', false)]));
        $writer->addRow(Row::fromValues(['Dekan', null, null, null, null, 'Ketua Program Studi', null, null, null, 'Staf Program Studi']));
        for ($space = 0; $space < 3; $space++) {
            $writer->addRow(Row::fromValues([' ']));
        }
        $writer->addRow(Row::fromValues([$settings['dean_name'], null, null, null, null, $head?->name, null, null, null, $settings['staff_name']]));
        $writer->addRow(Row::fromValues([$settings['dean_nik'], null, null, null, null, $head?->dosenProfile?->nik, null, null, null, $settings['staff_nik']]));
    }
}
