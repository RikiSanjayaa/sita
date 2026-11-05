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

    public function write(iterable $defenses, string $period): void
    {
        SidangOfficeTemplate::stream('rekap-nilai-sidang.xlsx', function (ZipArchive $zip) use ($defenses, $period): void {
            $groups = [];
            foreach ($defenses as $defense) {
                $terms = AcademicTerminology::forProject($defense->project);
                $key = $defense->project->program_studi_id.'-'.$terms['degreeLevel'];
                if (! isset($groups[$key])) {
                    $groups[$key] = ['sheet' => SidangOfficeTemplate::readXml($zip, self::SHEET), 'first' => $defense, 'count' => 0, 'sk' => []];
                }
                $this->addDefense($groups[$key]['sheet'], $defense, ++$groups[$key]['count']);
                $groups[$key]['sk'][] = $defense->sidang_sk;
            }
            if ($groups === []) {
                $groups[] = ['sheet' => SidangOfficeTemplate::readXml($zip, self::SHEET), 'first' => null, 'count' => 0, 'sk' => []];
            }
            $workbook = SidangOfficeTemplate::readXml($zip, 'xl/workbook.xml');
            $relations = SidangOfficeTemplate::readXml($zip, 'xl/_rels/workbook.xml.rels');
            $types = SidangOfficeTemplate::readXml($zip, '[Content_Types].xml');
            $sheets = $workbook->getElementsByTagName('sheets')->item(0);
            $definedNames = $workbook->getElementsByTagName('definedNames')->item(0);
            $printArea = $definedNames->firstElementChild;
            foreach (array_values($groups) as $index => $group) {
                $first = $group['first'];
                $program = $first?->project->programStudi;
                $terms = $first === null ? AcademicTerminology::neutral() : AcademicTerminology::forProject($first->project);
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
                $this->writeSk($sheet, $group['sk'], $last - 11);
                $shift = $last - 49;
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
                SidangOfficeTemplate::saveXml($zip, $entry, $sheet);
            }
            SidangOfficeTemplate::saveXml($zip, 'xl/workbook.xml', $workbook);
            SidangOfficeTemplate::saveXml($zip, 'xl/_rels/workbook.xml.rels', $relations);
            SidangOfficeTemplate::saveXml($zip, '[Content_Types].xml', $types);
        });
    }

    private function addDefense(DOMDocument $sheet, ThesisDefense $defense, int $number): void
    {
        $start = 12 + ($number - 1) * 3;
        if ($number > 9) {
            $this->insertBlock($sheet, $start);
        }
        $merges = $sheet->getElementsByTagName('mergeCells')->item(0);
        $student = $defense->project->student;
        $scores = $defense->sidang_paper['scores'];
        $settings = $defense->sidang_paper['settings'];
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
        $weighted = sprintf('(L%d*%d%%+L%d*%d%%+L%d*%d%%)', $start, $weights[0], $start + 1, $weights[1], $start + 2, $weights[2]);
        $grade = '"E"';
        foreach (array_reverse(SidangSettings::GRADE_KEYS, true) as $index => $key) {
            $grade = sprintf('IF(M%d>=%d,"%s",%s)', $start, $settings[$key], SidangSettings::GRADES[$index], $grade);
        }
        $this->value($sheet, 'M'.$start, (float) $defense->sidang_paper['value'], $weighted);
        $this->value($sheet, 'N'.$start, (int) round($defense->sidang_paper['value']), 'ROUND(M'.$start.',0)');
        $this->value($sheet, 'O'.$start, $defense->sidang_paper['grade'], $grade);
        $merges->setAttribute('count', (string) $merges->getElementsByTagName('mergeCell')->length);
    }

    private function writeSk(DOMDocument $sheet, array $numbers, int $last): void
    {
        $merges = $sheet->getElementsByTagName('mergeCells')->item(0);
        foreach ($merges->getElementsByTagName('mergeCell') as $merge) {
            if ($merge->getAttribute('ref') === 'E12:E38') {
                if (count(array_unique($numbers, SORT_REGULAR)) <= 1) {
                    $merge->setAttribute('ref', 'E12:E'.$last);
                    $this->value($sheet, 'E12', $numbers[0] ?? null);

                    return;
                }
                $merges->removeChild($merge);
                break;
            }
        }
        foreach ($numbers as $index => $number) {
            $start = 12 + $index * 3;
            $this->merge($merges, 'E'.$start.':E'.($start + 2));
            $this->value($sheet, 'E'.$start, $number);
        }
        $merges->setAttribute('count', (string) $merges->getElementsByTagName('mergeCell')->length);
    }

    private function insertBlock(DOMDocument $sheet, int $start): void
    {
        $data = $sheet->getElementsByTagName('sheetData')->item(0);
        $merges = $sheet->getElementsByTagName('mergeCells')->item(0);
        $footer = null;
        foreach ($data->getElementsByTagName('row') as $row) {
            if ((int) $row->getAttribute('r') >= $start) {
                $footer ??= $row;
                $this->moveRow($row, 3);
            }
        }
        for ($offset = 0; $offset < 3; $offset++) {
            $row = $this->row($sheet, 36 + $offset)->cloneNode(true);
            $this->moveRow($row, $start - 36);
            foreach ($row->getElementsByTagName('c') as $cell) {
                $cell->replaceChildren();
                $cell->removeAttribute('t');
            }
            $data->insertBefore($row, $footer);
        }
        foreach ($merges->getElementsByTagName('mergeCell') as $merge) {
            $ref = $merge->getAttribute('ref');
            $merge->setAttribute('ref', preg_replace_callback('/\\d+/', fn (array $match): int => (int) $match[0] + ((int) $match[0] >= $start ? 3 : 0), $ref));
        }
        foreach (['A', 'B', 'C', 'D', 'F', 'M', 'N', 'O'] as $column) {
            $this->merge($merges, $column.$start.':'.$column.($start + 2));
        }
        $sheet->getElementsByTagName('dimension')->item(0)->setAttribute('ref', 'A1:O'.($start + 13));
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

    private function merge(DOMElement $merges, string $range): void
    {
        $this->element($merges, 'mergeCell', ['ref' => $range]);
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
