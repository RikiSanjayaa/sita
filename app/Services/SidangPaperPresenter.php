<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Support\AcademicTerminology;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;

final class SidangPaperPresenter
{
    public function fields(ThesisDefense $defense): array
    {
        $defense->loadMissing(
            'project.student.mahasiswaProfile', 'project.programStudi.faculty',
            'project.programStudi.kaprodiAssignments.user.dosenProfile', 'titleVersion',
            'examiners.lecturer.dosenProfile', 'moderator', 'revisions.items',
        );
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
            'finalWork' => $terms['finalWork'], 'examLabel' => $terms['finalExam'],
            'finalWorkUpper' => mb_strtoupper($terms['finalWork']),
            'finalWorkLower' => mb_strtolower($terms['finalWork']),
            'institution' => $currentSettings['heading'],
            'seminarTitle' => 'Seminar '.$terms['finalWork'],
            'examTitle' => 'Ujian Seminar '.$terms['finalWork'],
            'student' => (string) $project->student?->name, 'nim' => $nim,
            'title' => (string) $defense->titleVersion?->title_id,
            'program' => $programName,
            'letterheadInstitution' => mb_strtoupper($currentSettings['heading']),
            'letterheadFaculty' => $faculty?->is_placeholder ? '' : mb_strtoupper($faculty?->name ?? ''),
            'letterheadProgram' => $program === null ? '' : 'PROGRAM STUDI '.mb_strtoupper($programName),
            'letterheadAddress' => $currentSettings['letterhead_address'],
            'letterheadWebsite' => $currentSettings['letterhead_website'],
            'letterheadContact' => $currentSettings['letterhead_contact'] ?? ($faculty?->code === 'FT' ? SidangSettings::FT_CONTACT : ''),
            'schedule' => $this->schedule($defense).' / '.$defense->location,
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
        foreach ([...SidangSettings::GRADE_KEYS, 'e'] as $index => $grade) {
            $lower = $grade === 'e' ? 0 : $settings[$grade];
            $upper = $index === 0 ? 100 : $settings[SidangSettings::GRADE_KEYS[$index - 1]] - 0.01;
            $fields['gradeRange'.$index] = number_format($lower, 0, ',', '').'–'.number_format($upper, $index === 0 ? 0 : 2, ',', '');
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

    private function schedule(ThesisDefense $defense): string
    {
        $start = $defense->scheduled_for;
        $end = $defense->scheduled_until;
        $schedule = WitaDateTime::translated($start, 'l, d F Y / H.i', withLabel: false);
        if ($end !== null) {
            $sameDay = WitaDateTime::translated($start, 'Y-m-d', withLabel: false)
                === WitaDateTime::translated($end, 'Y-m-d', withLabel: false);
            $schedule .= ' - '.WitaDateTime::translated($end, $sameDay ? 'H.i' : 'l, d F Y / H.i', withLabel: false);
        }

        return $schedule.' WITA';
    }
}
