<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Support\AcademicTerminology;
use App\Support\SidangOfficeTemplate;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

final class SidangWordWriter
{
    public function write(ThesisDefense $defense, ?string $form): void
    {
        SidangOfficeTemplate::stream('berkas-sidang.docx', function (ZipArchive $zip) use ($defense, $form): void {
            $entry = 'word/document.xml';
            $xml = SidangOfficeTemplate::readXml($zip, $entry);
            $word = $xml->documentElement->namespaceURI;
            $query = new DOMXPath($xml);
            $query->registerNamespace('w', $word);
            $body = $query->query('/w:document/w:body')->item(0);
            $active = null;
            foreach (iterator_to_array($body->childNodes) as $node) {
                if ($node instanceof DOMComment && str_starts_with($node->data, 'sita:')) {
                    $active = substr($node->data, 5);
                    $node->parentNode->removeChild($node);
                } elseif ($node instanceof DOMElement && $node->localName !== 'sectPr' && $form !== null && $active !== $form) {
                    $node->parentNode->removeChild($node);
                }
            }
            if ($form !== null) {
                $first = $query->query('./w:p[1]', $body)->item(0);
                if ($first !== null) {
                    foreach ($query->query('./w:pPr/w:pageBreakBefore | .//w:br[@w:type="page"]', $first) as $break) {
                        $break->parentNode->removeChild($break);
                    }
                }
                $last = $query->query('./w:p[last()]', $body)->item(0);
                if ($last !== null) {
                    foreach ($query->query('./w:r/w:br[@w:type="page"]', $last) as $break) {
                        $break->parentNode->removeChild($break);
                    }
                }
            }
            $fields = $this->fields($defense);
            $this->fillFields($xml, $fields);
            SidangOfficeTemplate::saveXml($zip, $entry, $xml);

            $headerEntry = 'word/header1.xml';
            $header = SidangOfficeTemplate::readXml($zip, $headerEntry);
            $this->fillFields($header, $fields);
            SidangOfficeTemplate::saveXml($zip, $headerEntry, $header);
        });
    }

    private function fillFields(DOMDocument $xml, array $fields): void
    {
        foreach ($xml->getElementsByTagNameNS($xml->documentElement->namespaceURI, 't') as $run) {
            if (str_contains($run->textContent, '{{')) {
                $text = preg_replace_callback('/\{\{(\w+)\}\}/', fn (array $match): string => $fields[$match[1]] ?? '', $run->textContent);
                $run->replaceChildren($xml->createTextNode($text));
                $run->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            }
        }
    }

    private function fields(ThesisDefense $defense): array
    {
        $project = $defense->project;
        $program = $project->programStudi;
        $terms = AcademicTerminology::forProject($project);
        $currentSettings = SidangSettings::forProgram($program);
        $summary = app(SidangScoreService::class)->summary($defense);
        $settings = $summary['settings'] ?? $currentSettings;
        $faculty = $program?->faculty;
        $programName = trim($terms['degreeLevel'].' '.$program?->name);
        $panel = $defense->examiners->sortBy('order_no');
        $examiners = $panel->where('role', 'examiner')->values();
        $panelIds = app(SidangScoreService::class)->panelIds($defense);
        $head = $program?->kaprodiAssignments->firstWhere('is_primary', true)?->user;
        $nim = (string) $project->student?->mahasiswaProfile?->nim;
        $complete = $defense->status === 'completed';
        $fields = [
            'student' => (string) $project->student?->name, 'nim' => $nim,
            'title' => (string) $defense->titleVersion?->title_id,
            'program' => $programName,
            'letterheadInstitution' => mb_strtoupper($currentSettings['heading']),
            'letterheadFaculty' => $faculty?->is_placeholder ? '' : mb_strtoupper($faculty?->name ?? ''),
            'letterheadProgram' => $program === null ? '' : 'PROGRAM STUDI '.mb_strtoupper($programName),
            'letterheadContact' => $faculty?->code === 'FT'
                ? 'Telp (0370) 638369 | Whatsapp 0859-3615-9726 | Email ftd@universitasbumigora.ac.id' : '',
            'schedule' => WitaDateTime::translatedDateRange($defense->scheduled_for, $defense->scheduled_until).' / '.$defense->location,
            'placeDate' => $settings['city'].', '.WitaDateTime::translated($defense->scheduled_for, 'd F Y', withLabel: false),
            'supervisor1' => (string) $panel->firstWhere('role', 'primary_supervisor')?->lecturer?->name,
            'supervisor2' => (string) $panel->firstWhere('role', 'secondary_supervisor')?->lecturer?->name,
            'examiner1' => (string) ($panelIds === null ? $examiners->get(0) : $panel->find($panelIds['examiner_1']))?->lecturer?->name,
            'examiner2' => (string) ($panelIds === null ? $examiners->get(1) : $panel->find($panelIds['examiner_2']))?->lecturer?->name,
            'moderator' => (string) $defense->moderator?->name,
            'head' => (string) $head?->name, 'headNik' => (string) $head?->dosenProfile?->nik,
            'pass' => ! $complete || in_array($defense->result, ['pass', 'pass_with_revision'], true) ? 'LULUS' : '',
            'fail' => ! $complete || $defense->result === 'fail' ? 'TIDAK LULUS' : '',
            'total' => $complete ? (string) ($summary['value'] ?? '') : '',
        ];
        for ($index = 0; $index < 10; $index++) {
            $fields['nim'.($index + 1)] = $index === 9 ? substr($nim, $index) : ($nim[$index] ?? '');
        }
        foreach (['examiner_1' => 'examiner1', 'examiner_2' => 'examiner2', 'supervisor' => 'supervisor'] as $role => $key) {
            $value = $complete ? ($summary['scores'][$role]['value'] ?? null) : null;
            $fields[$key.'Weight'] = $settings[$role].' %';
            $fields[$key.'Score'] = (string) $value;
            $fields[$key.'Weighted'] = $value === null ? '' : (string) ($value * $settings[$role] / 100);
        }
        $supervisors = $panel->filter(fn ($member) => $member->isSupervisorRole())->pluck('lecturer_user_id');
        if ($complete) {
            foreach ($defense->revisions as $revision) {
                $key = $supervisors->contains($revision->requested_by_user_id) ? 'supervisorNotes' : 'examinerNotes';
                $notes = $revision->items->pluck('description')->prepend($revision->notes)->filter()->implode(' ');
                $fields[$key] = trim(($fields[$key] ?? '').' '.$notes);
            }
        }

        return $fields;
    }
}
