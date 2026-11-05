<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Support\AcademicTerminology;
use App\Support\SidangOfficeTemplate;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;
use DOMComment;
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
                    foreach ($query->query('.//w:br[@w:type="page"]', $first) as $break) {
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
            foreach ($query->query('//w:t') as $run) {
                if (str_contains($run->textContent, '{{')) {
                    $text = preg_replace_callback('/\{\{(\w+)\}\}/', fn (array $match): string => $fields[$match[1]] ?? '', $run->textContent);
                    $run->replaceChildren($xml->createTextNode($text));
                    $run->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                }
            }
            SidangOfficeTemplate::saveXml($zip, $entry, $xml);
        });
    }

    private function fields(ThesisDefense $defense): array
    {
        $project = $defense->project;
        $program = $project->programStudi;
        $terms = AcademicTerminology::forProject($project);
        $settings = $defense->sidang_paper['settings'] ?? SidangSettings::forProgram($program);
        $panel = $defense->examiners->sortBy('order_no');
        $examiners = $panel->where('role', 'examiner')->values();
        $head = $program?->kaprodiAssignments->firstWhere('is_primary', true)?->user;
        $nim = (string) $project->student?->mahasiswaProfile?->nim;
        $complete = $defense->status === 'completed';
        $fields = [
            'student' => (string) $project->student?->name, 'nim' => $nim,
            'title' => (string) $defense->titleVersion?->title_id,
            'program' => trim($terms['degreeLevel'].' '.$program?->name),
            'schedule' => WitaDateTime::translatedDateRange($defense->scheduled_for, $defense->scheduled_until).' / '.$defense->location,
            'placeDate' => $settings['city'].', '.WitaDateTime::translated($defense->scheduled_for, 'd F Y', withLabel: false),
            'supervisor1' => (string) $panel->firstWhere('role', 'primary_supervisor')?->lecturer?->name,
            'supervisor2' => (string) $panel->firstWhere('role', 'secondary_supervisor')?->lecturer?->name,
            'examiner1' => (string) $examiners->get(0)?->lecturer?->name,
            'examiner2' => (string) $examiners->get(1)?->lecturer?->name,
            'moderator' => (string) $defense->moderator?->name,
            'head' => (string) $head?->name, 'headNik' => (string) $head?->dosenProfile?->nik,
            'pass' => ! $complete || in_array($defense->result, ['pass', 'pass_with_revision'], true) ? 'LULUS' : '',
            'fail' => ! $complete || $defense->result === 'fail' ? 'TIDAK LULUS' : '',
            'total' => $complete ? (string) ($defense->sidang_paper['value'] ?? '') : '',
        ];
        for ($index = 0; $index < 10; $index++) {
            $fields['nim'.($index + 1)] = $index === 9 ? substr($nim, $index) : ($nim[$index] ?? '');
        }
        foreach (['examiner_1' => 'examiner1', 'examiner_2' => 'examiner2', 'supervisor' => 'supervisor'] as $role => $key) {
            $value = $complete ? ($defense->sidang_paper['scores'][$role]['value'] ?? null) : null;
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
