<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Support\AcademicTerminology;
use App\Support\SidangOfficeTemplate;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;
use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

final class SidangReportWriter
{
    private const SHEET = 'xl/worksheets/sheet7.xml';

    private const RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public const EMPTY_MESSAGE = 'Tidak ada sidang lulus yang sudah final pada filter ini.';

    public function prepare(iterable $defenses, string $period): string
    {
        $files = [];
        try {
            return SidangOfficeTemplate::prepare('rekap-nilai-sidang.xlsx', function (ZipArchive $zip) use ($defenses, $period, &$files): void {
                $this->fillWorkbook($zip, $defenses, $period, $files);
            });
        } finally {
            foreach ($files as $file) {
                fclose($file);
            }
        }
    }

    private function fillWorkbook(ZipArchive $zip, iterable $defenses, string $period, array &$files): void
    {
        $template = SidangOfficeTemplate::readXml($zip, self::SHEET);
        $groups = [];
        foreach ($defenses as $defense) {
            $summary = app(SidangScoreService::class)->summary($defense);
            if ($summary === null) {
                continue;
            }

            $terms = AcademicTerminology::forProject($defense->project);
            $key = $defense->project->program_studi_id.'-'.$terms['degreeLevel'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'sheet' => clone $template,
                    'program' => $defense->project->programStudi,
                    'terms' => $terms,
                    'count' => 0,
                    'rows' => $this->tempFile($files),
                    'merges' => $this->tempFile($files),
                    'mergeCount' => 0,
                    'skStart' => 12,
                    'sk' => $defense->sidang_sk,
                ];
            }
            $this->appendDefense($groups[$key], $template, $defense, $summary);
        }
        abort_if($groups === [], 422, self::EMPTY_MESSAGE);
        $workbook = SidangOfficeTemplate::readXml($zip, 'xl/workbook.xml');
        $relations = SidangOfficeTemplate::readXml($zip, 'xl/_rels/workbook.xml.rels');
        $types = SidangOfficeTemplate::readXml($zip, '[Content_Types].xml');
        $sheets = $workbook->getElementsByTagName('sheets')->item(0);
        $definedNames = $workbook->getElementsByTagName('definedNames')->item(0);
        $printArea = $definedNames->firstElementChild;
        foreach (array_values($groups) as $index => $group) {
            $program = $group['program'];
            $terms = $group['terms'];
            $settings = SidangSettings::forProgram($program);
            $head = $program?->kaprodiAssignments->firstWhere('is_primary', true)?->user;
            $sheet = $group['sheet'];
            $view = $sheet->getElementsByTagName('sheetView')->item(0);
            $view->removeAttribute('topLeftCell');
            $view->setAttribute('tabSelected', $index === 0 ? '1' : '0');
            foreach ($view->getElementsByTagName('selection') as $selection) {
                $selection->setAttribute('activeCell', 'A7');
                $selection->setAttribute('sqref', 'A7');
            }
            $last = 49 + max(0, $group['count'] - 9) * 3;
            $shift = $last - 49;
            if ($shift > 0) {
                foreach ($sheet->getElementsByTagName('row') as $row) {
                    if ((int) $row->getAttribute('r') >= 39) {
                        $this->moveRow($row, $shift);
                    }
                }
                $sheet->getElementsByTagName('dimension')->item(0)->setAttribute('ref', 'A1:O'.$last);
            }
            $merges = $sheet->getElementsByTagName('mergeCells')->item(0);
            foreach (iterator_to_array($merges->getElementsByTagName('mergeCell')) as $merge) {
                $ref = $merge->getAttribute('ref');
                if ($ref === 'E12:E38') {
                    $merge->remove();
                } elseif ($shift > 0) {
                    $merge->setAttribute('ref', preg_replace_callback('/[0-9]+/', fn (array $match): int => (int) $match[0] + ((int) $match[0] >= 39 ? $shift : 0), $ref));
                }
            }
            $skEnd = $group['skStart'] === 12 ? $last - 11 : 11 + $group['count'] * 3;
            $this->spoolMerge($group, 'E'.$group['skStart'].':E'.$skEnd);
            $title = 'REKAP NILAI '.$terms['finalWork'];
            if ($program !== null) {
                $title .= ' PROGRAM STUDI '.$terms['degreeLevel'].' '.$program->name;
            }
            foreach ([
                'A7' => mb_strtoupper($title),
                'A8' => $period !== '' ? $period : 'REKAP NILAI SIDANG',
                'J'.(41 + $shift) => $settings['city'].', '.WitaDateTime::translated(now(), 'd F Y', withLabel: false),
                'A'.(42 + $shift) => 'Dekan '.($program?->faculty?->name ?? 'Fakultas Teknik'),
                'F'.(42 + $shift) => 'Ketua Program Studi '.trim($terms['degreeLevel'].' '.$program?->name),
                'A'.(48 + $shift) => $settings['dean_name'],
                'F'.(48 + $shift) => $head?->name,
                'J'.(48 + $shift) => $settings['staff_name'],
                'A'.(49 + $shift) => $settings['dean_nik'],
                'F'.(49 + $shift) => $head?->dosenProfile?->nik,
                'J'.(49 + $shift) => $settings['staff_nik'],
            ] as $address => $value) {
                $this->value($sheet, $address, $value);
            }
            if ($index === 0) {
                $name = $sheets->firstElementChild->getAttribute('name');
                $entry = self::SHEET;
                $area = $printArea;
            } else {
                $name = 'skrpsi new '.($index + 1);
                $entry = 'xl/worksheets/rekap'.($index + 1).'.xml';
                $id = 'rIdRekap'.($index + 1);
                $this->element($sheets, 'sheet', ['name' => $name, 'sheetId' => (string) (20 + $index)])
                    ->setAttributeNS(self::RELATIONSHIP, 'r:id', $id);
                $this->element($relations->documentElement, 'Relationship', [
                    'Id' => $id, 'Type' => self::RELATIONSHIP.'/worksheet', 'Target' => substr($entry, 3),
                ]);
                $this->element($types->documentElement, 'Override', [
                    'PartName' => '/'.$entry, 'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
                ]);
                $zip->addFromString('xl/worksheets/_rels/'.basename($entry).'.rels', $zip->getFromName('xl/worksheets/_rels/sheet7.xml.rels'));
                $area = $this->element($definedNames, 'definedName', ['name' => '_xlnm.Print_Area', 'localSheetId' => (string) $index]);
            }
            $area->textContent = "'".$name."'!".'$A$7:$O$'.$last;
            $data = $sheet->getElementsByTagName('sheetData')->item(0);
            $firstFooterRow = $this->row($sheet, 41 + $shift);
            $data->insertBefore($sheet->createComment('sita-extra-rows'), $firstFooterRow);
            $merges->setAttribute('count', (string) ($merges->childElementCount + $group['mergeCount']));
            $merges->appendChild($sheet->createComment('sita-extra-merges'));
            [$header, $body, $footer] = preg_split('/<!--sita-extra-(?:rows|merges)-->/', $sheet->saveXML());
            $file = $this->tempFile($files);
            $this->append($file, $header);
            $this->copyStream($group['rows'], $file);
            $this->append($file, $body);
            $this->copyStream($group['merges'], $file);
            $this->append($file, $footer);
            if (! fflush($file) || ! $zip->addFile(stream_get_meta_data($file)['uri'], $entry)) {
                throw new RuntimeException('Lembar rekap tidak dapat disimpan.');
            }
        }
        SidangOfficeTemplate::saveXml($zip, 'xl/workbook.xml', $workbook);
        SidangOfficeTemplate::saveXml($zip, 'xl/_rels/workbook.xml.rels', $relations);
        SidangOfficeTemplate::saveXml($zip, '[Content_Types].xml', $types);
    }

    private function appendDefense(array &$group, DOMDocument $template, ThesisDefense $defense, array $summary): void
    {
        $number = ++$group['count'];
        $start = 12 + ($number - 1) * 3;
        $sheet = $group['sheet'];
        $extra = [];
        if ($number > 9) {
            $data = $sheet->getElementsByTagName('sheetData')->item(0);
            for ($offset = 0; $offset < 3; $offset++) {
                $row = $sheet->importNode($this->row($template, 36 + $offset), true);
                $this->moveRow($row, $start - 36);
                // These rows precede the footer in the lookup until they are spooled.
                $extra[] = $data->insertBefore($row, $data->firstChild);
            }
            foreach (['A', 'B', 'C', 'D', 'F', 'M', 'N', 'O'] as $column) {
                $this->spoolMerge($group, $column.$start.':'.$column.($start + 2));
            }
        }
        if ($defense->sidang_sk !== $group['sk']) {
            $this->spoolMerge($group, 'E'.$group['skStart'].':E'.($start - 1));
            $group['skStart'] = $start;
            $group['sk'] = $defense->sidang_sk;
        }
        if ($group['skStart'] === $start) {
            $this->value($sheet, 'E'.$start, $group['sk']);
        }
        $merges = $sheet->getElementsByTagName('mergeCells')->item(0);
        $student = $defense->project->student;
        $scores = $summary['scores'];
        $settings = $summary['settings'];
        $supervisors = $defense->examiners->filter(fn ($member) => $member->isSupervisorRole())->sortBy('role')->values();
        foreach ([
            'A' => $number, 'B' => $student?->name, 'C' => (string) $student?->mahasiswaProfile?->nim,
            'D' => 'Personal', 'F' => $defense->titleVersion?->title_id,
        ] as $column => $value) {
            $this->value($sheet, $column.$start, $value);
        }
        foreach (['examiner_1', 'examiner_2', 'supervisor'] as $offset => $role) {
            $row = $start + $offset;
            $score = $scores[$role];
            $lecturer = $defense->examiners->find($score['panel_id'])?->lecturer;
            foreach (['J' => $score['nidn'] ?? $lecturer?->dosenProfile?->sidang_nidn, 'K' => $score['name'], 'L' => (float) $score['value']] as $column => $value) {
                $this->value($sheet, $column.$row, $value);
            }
        }
        foreach ($supervisors->take(2) as $offset => $member) {
            if ($offset === 1 && $start === 21) {
                foreach (iterator_to_array($merges->childNodes) as $merge) {
                    if ($merge instanceof DOMElement && in_array($merge->getAttribute('ref'), ['G21:G23', 'H21:H23'], true)) {
                        $merges->removeChild($merge);
                    }
                }
            }
            $row = $start + $offset;
            $this->value($sheet, 'G'.$row, $member->lecturer?->dosenProfile?->sidang_nidn);
            $this->value($sheet, 'H'.$row, $member->lecturer?->name);
            $this->value($sheet, 'I'.$row, $member->role === 'primary_supervisor' ? '1' : '2');
        }
        $weights = array_map(fn (string $role): int => (int) $settings[$role], ['examiner_1', 'examiner_2', 'supervisor']);
        $weighted = sprintf('ROUND(L%d*%d%%+L%d*%d%%+L%d*%d%%,4)', $start, $weights[0], $start + 1, $weights[1], $start + 2, $weights[2]);
        $grade = '"E"';
        foreach (array_reverse(SidangSettings::GRADE_KEYS, true) as $index => $key) {
            $grade = sprintf('IF(M%d>=%d,"%s",%s)', $start, $settings[$key], SidangSettings::GRADES[$index], $grade);
        }
        $calculated = round(array_sum(array_map(fn (array $score): float => $score['value'] * $score['weight'], $scores)) / 100, 4);
        $this->value($sheet, 'M'.$start, $summary['value'], $calculated === (float) $summary['value'] ? $weighted : null);
        $this->value($sheet, 'N'.$start, (int) round($summary['value']), 'ROUND(M'.$start.',0)');
        $this->value($sheet, 'O'.$start, $summary['grade'], SidangSettings::grade($summary['value'], $settings) === $summary['grade'] ? $grade : null);
        foreach ($extra as $row) {
            $this->append($group['rows'], $sheet->saveXML($row));
            $row->remove();
        }
    }

    /** @return resource */
    private function tempFile(array &$files)
    {
        $file = tmpfile();
        if ($file === false) {
            throw new RuntimeException('Gagal menyiapkan lembar rekap.');
        }
        $files[] = $file;

        return $file;
    }

    /** @param resource $file */
    private function append($file, string $xml): void
    {
        if (fwrite($file, $xml) !== strlen($xml)) {
            throw new RuntimeException('Lembar rekap tidak dapat ditulis.');
        }
    }

    /**
     * @param  resource  $from
     * @param  resource  $to
     */
    private function copyStream($from, $to): void
    {
        if (! rewind($from) || stream_copy_to_stream($from, $to) === false) {
            throw new RuntimeException('Lembar rekap tidak dapat disalin.');
        }
    }

    private function spoolMerge(array &$group, string $range): void
    {
        $this->append($group['merges'], '<mergeCell ref="'.$range.'"/>');
        $group['mergeCount']++;
    }

    private function moveRow(DOMElement $row, int $shift): void
    {
        $number = (int) $row->getAttribute('r') + $shift;
        $row->setAttribute('r', (string) $number);
        foreach ($row->getElementsByTagName('c') as $cell) {
            $cell->setAttribute('r', rtrim($cell->getAttribute('r'), '0123456789').$number);
        }
    }

    private function row(DOMDocument $sheet, int $number): DOMElement
    {
        foreach ($sheet->getElementsByTagName('row') as $row) {
            if ((int) $row->getAttribute('r') === $number) {
                return $row;
            }
        }
        throw new RuntimeException('Baris template rekap tidak ditemukan.');
    }

    private function value(DOMDocument $sheet, string $address, string|float|int|null $value, ?string $formula = null): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $row = $this->row($sheet, (int) ltrim($address, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
        $cell = null;
        foreach ($row->getElementsByTagName('c') as $candidate) {
            $ref = $candidate->getAttribute('r');
            if ($ref === $address) {
                $cell = $candidate;
                break;
            }
            if (strcmp($ref, $address) > 0) {
                $cell = $sheet->createElementNS($row->namespaceURI, 'c');
                $row->insertBefore($cell, $candidate);
                break;
            }
        }
        $cell ??= $this->element($row, 'c', []);
        $cell->setAttribute('r', $address);
        $cell->replaceChildren();
        $cell->removeAttribute('t');
        if ($formula !== null) {
            $this->element($cell, 'f', [])->textContent = $formula;
            if (is_string($value)) {
                $cell->setAttribute('t', 'str');
            }
            $this->element($cell, 'v', [])->textContent = (string) $value;
        } elseif (is_string($value)) {
            $cell->setAttribute('t', 'inlineStr');
            $text = $this->element($this->element($cell, 'is', []), 't', []);
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $text->appendChild($sheet->createTextNode($value));
        } else {
            $this->element($cell, 'v', [])->textContent = (string) $value;
        }
    }

    private function element(DOMElement $parent, string $name, array $attributes): DOMElement
    {
        $element = $parent->ownerDocument->createElementNS($parent->namespaceURI, $name);
        foreach ($attributes as $attribute => $value) {
            $element->setAttribute($attribute, $value);
        }
        $parent->appendChild($element);

        return $element;
    }
}
