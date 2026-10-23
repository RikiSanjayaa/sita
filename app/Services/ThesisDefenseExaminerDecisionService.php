<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\User;
use App\Support\AcademicTerminology;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ThesisDefenseExaminerDecisionService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    /**
     * @param  array{decision:string,score?:float|int|null,decision_notes:?string,revision_notes:?string,revision_items?:?array<int, array{location?:?string,description:string,priority?:?string}>}  $data
     */
    public function submit(User $lecturer, ThesisDefense $defense, array $data, ?UploadedFile $annotation = null): ThesisDefenseExaminer
    {
        $examiner = ThesisDefenseExaminer::query()
            ->where('defense_id', $defense->id)
            ->where('lecturer_user_id', $lecturer->id)
            ->first();

        if (! $examiner instanceof ThesisDefenseExaminer) {
            throw new RuntimeException('Anda bukan penguji untuk ujian ini.');
        }

        if ($defense->status !== 'scheduled') {
            throw new RuntimeException('Ujian tidak dalam status yang dapat dinilai.');
        }

        if ($examiner->decision !== 'pending') {
            throw new RuntimeException('Anda sudah mengirim keputusan untuk ujian ini.');
        }

        DB::transaction(function () use ($annotation, $data, $defense, $examiner, $lecturer): void {
            $requestsRevision = $data['decision'] === 'pass_with_revision';

            $examiner->forceFill([
                'decision' => $data['decision'],
                'score' => $data['score'] ?? null,
                'notes' => $data['decision_notes'] ?? null,
                'revision_notes' => $requestsRevision ? ($data['revision_notes'] ?? null) : null,
                'revision_items' => $requestsRevision ? $this->normalizeItems($data['revision_items'] ?? []) : null,
                'decided_at' => now(),
            ])->save();

            if ($requestsRevision && $annotation instanceof UploadedFile) {
                $this->storeAnnotation($defense, $lecturer, $annotation);
            }

            $this->recalculateDefenseOutcome($defense->fresh('examiners'));

            $student = $defense->project?->student;
            $project = $defense->project;
            $terms = $project instanceof \App\Models\ThesisProject ? AcademicTerminology::forProject($project) : AcademicTerminology::neutral();

            if ($student instanceof User) {
                $this->realtimeNotificationService->notifyUser($student, 'statusTugasAkhir', [
                    'title' => sprintf(
                        'Nilai %s dari penguji tersedia',
                        AcademicTerminology::defenseLabel($terms, $defense->type, short: true),
                    ),
                    'description' => sprintf(
                        '%s sudah mengirim nilai dan keputusan untuk %s Anda.',
                        $lecturer->name,
                        AcademicTerminology::defenseLabel($terms, $defense->type, short: true),
                    ),
                    'url' => '/tugas-akhir',
                    'icon' => 'check-circle',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }
        });

        return $examiner->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{location: ?string, description: string, priority: ?string}>|null
     */
    private function normalizeItems(array $items): ?array
    {
        $normalized = collect($items)
            ->filter(fn (array $item): bool => filled($item['description'] ?? null))
            ->map(fn (array $item): array => [
                'location' => filled($item['location'] ?? null) ? trim((string) $item['location']) : null,
                'description' => trim((string) $item['description']),
                'priority' => $item['priority'] ?? null,
            ])
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    /**
     * The annotated manuscript is stored now and linked to the ThesisRevision
     * when the admin finalises the exam (the revision does not exist yet).
     */
    private function storeAnnotation(ThesisDefense $defense, User $lecturer, UploadedFile $file): void
    {
        $path = $file->storeAs(
            sprintf('thesis/defenses/%d/%s/attempt-%d', $defense->project_id, $defense->type, $defense->attempt_no),
            sprintf('%s-%s-%s', ThesisRevisionRoundService::ANNOTATION_KIND, Str::uuid()->toString(), $file->hashName()),
            'public',
        );

        ThesisDocument::query()->create([
            'project_id' => $defense->project_id,
            'defense_id' => $defense->getKey(),
            'uploaded_by_user_id' => $lecturer->id,
            'kind' => ThesisRevisionRoundService::ANNOTATION_KIND,
            'status' => 'active',
            'version_no' => 1,
            'title' => 'Naskah beranotasi '.$lecturer->name,
            'storage_disk' => 'public',
            'storage_path' => $path,
            'stored_file_name' => basename($path),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'file_size_kb' => (int) ceil(($file->getSize() ?: 0) / 1024),
            'uploaded_at' => now(),
        ]);
    }

    private function recalculateDefenseOutcome(ThesisDefense $defense): void
    {
        $decisions = $defense->examiners->pluck('decision');

        if ($decisions->isEmpty() || $decisions->contains('pending')) {
            $defense->forceFill([
                'status' => 'scheduled',
                'result' => 'pending',
            ])->save();

            return;
        }

        $defense->forceFill([
            'status' => 'awaiting_finalization',
            'result' => 'pending',
        ])->save();
    }
}
