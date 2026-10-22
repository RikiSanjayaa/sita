<?php

namespace App\Services;

use App\Enums\AdvisorType;
use App\Enums\ThesisNoteVisibility;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Policies\ThesisProjectNotePolicy;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;

class ThesisProjectNoteService
{
    public function __construct(
        private readonly ThesisProjectNotePolicy $policy,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    /**
     * Notes the viewer may see, grouped per project id. Internal notes are
     * excluded in the query itself for viewers who may only read student-visible
     * notes, so they never reach the Inertia payload.
     *
     * @param  iterable<ThesisProject>  $projects
     * @return array<int, array{canWrite: bool, notes: array<int, array<string, mixed>>}>
     */
    public function payload(User $viewer, iterable $projects, bool $allowWrite = true): array
    {
        $internalIds = [];
        $studentIds = [];
        $result = [];

        foreach ($projects as $project) {
            if (isset($result[$project->id])) {
                continue;
            }

            $isInternalReader = $this->policy->canReadInternal($viewer, $project);

            if ($isInternalReader) {
                $internalIds[] = $project->id;
            } elseif ($this->policy->canReadStudentVisible($viewer, $project)) {
                $studentIds[] = $project->id;
            } else {
                continue;
            }

            $result[$project->id] = [
                'canWrite' => $allowWrite && $this->policy->isNoteWriter($viewer, $project),
                'notes' => [],
            ];
        }

        if ($result === []) {
            return [];
        }

        $notes = ThesisProjectNote::query()
            ->with('author')
            ->where(function ($query) use ($internalIds, $studentIds): void {
                $query->whereIn('project_id', $internalIds)
                    ->orWhere(function ($query) use ($studentIds): void {
                        $query->whereIn('project_id', $studentIds)
                            ->where('visibility', ThesisNoteVisibility::Student->value);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $authorRoles = $this->authorRoleLabels($notes);

        foreach ($notes as $note) {
            $result[$note->project_id]['notes'][] = $this->present($viewer, $note, $authorRoles, $allowWrite);
        }

        return $result;
    }

    /**
     * Student-visible notes for the student's own project, for the Tugas Akhir page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function studentPayload(User $student, ?ThesisProject $project): array
    {
        if (! $project instanceof ThesisProject || $project->student_user_id !== $student->id) {
            return [];
        }

        return $this->payload($student, [$project], allowWrite: false)[$project->id]['notes'] ?? [];
    }

    public function create(User $author, ThesisProject $project, ThesisNoteVisibility $visibility, string $body): ThesisProjectNote
    {
        $note = $project->lecturerNotes()->create([
            'author_user_id' => $author->id,
            'visibility' => $visibility,
            'body' => $body,
        ]);

        if ($visibility === ThesisNoteVisibility::Student) {
            $this->notifyStudent($author, $project);
        }

        return $note;
    }

    public function update(ThesisProjectNote $note, ThesisNoteVisibility $visibility, string $body): ThesisProjectNote
    {
        $becameVisible = $note->visibility !== ThesisNoteVisibility::Student
            && $visibility === ThesisNoteVisibility::Student;

        $note->update([
            'visibility' => $visibility,
            'body' => $body,
        ]);

        if ($becameVisible && $note->author instanceof User && $note->project instanceof ThesisProject) {
            $this->notifyStudent($note->author, $note->project);
        }

        return $note;
    }

    private function notifyStudent(User $author, ThesisProject $project): void
    {
        $student = $project->student;

        if (! $student instanceof User) {
            return;
        }

        $terms = AcademicTerminology::forProject($project);

        $this->realtimeNotificationService->notifyUser($student, 'statusTugasAkhir', [
            'title' => 'Catatan baru dari dosen',
            'description' => sprintf('%s menambahkan catatan pada %s Anda.', $author->name, $terms['finalWorkLower']),
            'url' => '/mahasiswa/tugas-akhir',
            'icon' => 'message-square-text',
            'createdAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, string>  $authorRoles
     * @return array<string, mixed>
     */
    private function present(User $viewer, ThesisProjectNote $note, array $authorRoles, bool $allowWrite): array
    {
        return [
            'id' => $note->id,
            'projectId' => $note->project_id,
            'visibility' => $note->visibility->value,
            'visibilityLabel' => $note->visibility->label(),
            'body' => $note->body,
            'authorName' => $note->author?->name ?? 'Dosen',
            'authorRole' => $authorRoles[$note->project_id.':'.$note->author_user_id] ?? 'Dosen',
            'createdAt' => WitaDateTime::format($note->created_at),
            'isEdited' => $note->updated_at?->gt($note->created_at) ?? false,
            'canManage' => $allowWrite && $note->author_user_id !== null && $note->author_user_id === $viewer->id,
        ];
    }

    /**
     * Label per "projectId:authorId": supervisor role first, then examiner.
     *
     * @param  \Illuminate\Support\Collection<int, ThesisProjectNote>  $notes
     * @return array<string, string>
     */
    private function authorRoleLabels($notes): array
    {
        if ($notes->isEmpty()) {
            return [];
        }

        $projectIds = $notes->pluck('project_id')->unique()->all();
        $authorIds = $notes->pluck('author_user_id')->filter()->unique()->all();
        $labels = [];

        ThesisDefenseExaminer::query()
            ->whereIn('lecturer_user_id', $authorIds)
            ->whereHas('defense', fn ($query) => $query->whereIn('project_id', $projectIds))
            ->with('defense:id,project_id')
            ->get()
            ->each(function (ThesisDefenseExaminer $examiner) use (&$labels): void {
                $labels[$examiner->defense->project_id.':'.$examiner->lecturer_user_id] = 'Penguji';
            });

        ThesisSupervisorAssignment::query()
            ->whereIn('project_id', $projectIds)
            ->whereIn('lecturer_user_id', $authorIds)
            ->orderByRaw("case when status = 'active' then 0 else 1 end desc")
            ->get()
            ->each(function (ThesisSupervisorAssignment $assignment) use (&$labels): void {
                $labels[$assignment->project_id.':'.$assignment->lecturer_user_id] = $assignment->role === AdvisorType::Primary->value
                    ? 'Pembimbing 1'
                    : 'Pembimbing 2';
            });

        return $labels;
    }
}
