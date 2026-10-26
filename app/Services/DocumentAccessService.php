<?php

namespace App\Services;

use App\Models\KaprodiAssignment;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\User;

class DocumentAccessService
{
    public function kaprodiCan(User $user, ?int $programStudiId, string $capability): bool
    {
        $assignment = $user->kaprodiAssignment;

        return $user->hasRole('kaprodi')
            && $assignment instanceof KaprodiAssignment
            && $assignment->hasCapability($capability)
            && $programStudiId !== null
            && $programStudiId === $user->kaprodiProgramStudiId();
    }

    public function workspace(User $user, MentorshipDocument $document, string $role, bool $download = false): bool
    {
        return match ($role) {
            'mahasiswa' => $user->hasRole('mahasiswa') && $document->student_user_id === $user->id,
            'dosen' => $user->hasRole('dosen') && $document->lecturer_user_id === $user->id,
            'kaprodi' => $this->kaprodiCan($user, $document->student?->mahasiswaProfile?->program_studi_id,
                $download ? KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS : KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS),
            default => false,
        };
    }

    public function thesis(User $user, ThesisDocument $document, ?string $role = null, bool $download = true): bool
    {
        $project = $document->project;
        if ($project === null) {
            return false;
        }

        if ($role === 'kaprodi') {
            return $this->kaprodiCan($user, $project->program_studi_id,
                $download ? KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS : KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS);
        }

        if ($role === null && $user->hasRole('super_admin')) {
            return true;
        }
        if ($role === null && $user->hasRole('admin')) {
            return $user->adminProgramStudiId() === null || $project->program_studi_id === $user->adminProgramStudiId();
        }
        if ($role === null && $user->hasRole('mahasiswa')) {
            return $project->student_user_id === $user->id
                && ($document->kind !== ThesisRevisionRoundService::ANNOTATION_KIND
                    || ($document->revision_id !== null && ! ThesisDefenseExaminer::query()->secretFromStudent()
                        ->where('defense_id', $document->defense_id)->where('lecturer_user_id', $document->uploaded_by_user_id)->exists()));
        }

        $kaprodi = $this->kaprodiCan($user, $project->program_studi_id,
            $download || $role === 'dosen' ? KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS : KaprodiAssignment::CAPABILITY_VIEW_DOCUMENTS);
        if ($user->hasRole('dosen')) {
            $supervisor = $project->supervisorAssignments()->where('lecturer_user_id', $user->id)->exists();
            $examiner = $document->defense_id !== null && ThesisDefenseExaminer::query()
                ->where('lecturer_user_id', $user->id)->where('defense_id', $document->defense_id)
                ->whereHas('defense', fn ($query) => $query->where('project_id', $project->id))->exists();

            return ($supervisor || $examiner || $kaprodi)
                && ($document->kind !== ThesisRevisionRoundService::ANNOTATION_KIND
                    || $document->revision_id !== null || $document->uploaded_by_user_id === $user->id || $kaprodi);
        }

        return $role === null && $kaprodi;
    }

    public function reviewReadOnlyReason(User $user, MentorshipDocument $document): ?string
    {
        if (! $this->workspace($user, $document, 'dosen')) {
            return 'Hanya dosen penerima dokumen ini yang dapat memberi review.';
        }
        if ($document->uploaded_by_role === 'dosen') {
            return 'Lampiran dosen tidak memerlukan review dokumen mahasiswa.';
        }
        $project = ThesisProject::query()->latestPerStudent()->where('student_user_id', $document->student_user_id)->first();
        if ($project === null || $project->state !== 'active' || $project->isArchived()) {
            return 'Bimbingan tidak aktif. Dokumen hanya dapat dibaca.';
        }
        if ($document->student?->mahasiswaProfile?->is_active === false) {
            return 'Mahasiswa tidak aktif. Dokumen hanya dapat dibaca.';
        }
        if ($project->isMentorshipSuspended()) {
            return MentorshipTicketService::suspensionMessage($project, false);
        }
        $supervisor = $project->supervisorAssignments()->where('lecturer_user_id', $user->id)->where('status', 'active')->exists();
        $examiner = $project->defenses()->where(function ($query): void {
            $query->where('status', 'scheduled')->orWhere(fn ($query) => $query->where('status', 'completed')->where('result', 'pass_with_revision'));
        })->whereHas('examiners', fn ($query) => $query->where('lecturer_user_id', $user->id))->exists();
        if (! $supervisor && ! $examiner) {
            return 'Penugasan review tidak aktif. Dokumen hanya dapat dibaca.';
        }
        if ($document->document_group !== null && MentorshipDocument::query()->where('student_user_id', $document->student_user_id)
            ->where('document_group', $document->document_group)->where('version_number', '>', $document->version_number)->exists()
            && $document->category !== 'lampiran-chat') {
            return 'Versi ini telah digantikan. Beri review pada versi terbaru.';
        }

        return null;
    }
}
