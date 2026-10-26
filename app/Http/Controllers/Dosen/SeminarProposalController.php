<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\SubmitDefenseDecisionRequest;
use App\Http\Requests\Dosen\VerifyRevisionRequest;
use App\Models\MentorshipChatThread;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionRound;
use App\Services\DosenScheduleWorkspaceService;
use App\Services\DosenThesisWorkspaceService;
use App\Services\ThesisDefenseExaminerDecisionService;
use App\Services\ThesisDefenseRevisionService;
use App\Services\ThesisProjectNoteService;
use App\Services\ThesisRevisionRoundService;
use App\Support\AcademicTerminology;
use App\Support\DefenseRevisionLog;
use App\Support\ThesisRevisionPayload;
use App\Support\WitaDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SeminarProposalController extends Controller
{
    public function __construct(
        private readonly DosenScheduleWorkspaceService $dosenScheduleWorkspaceService,
        private readonly DosenThesisWorkspaceService $dosenThesisWorkspaceService,
        private readonly ThesisDefenseExaminerDecisionService $decisionService,
        private readonly ThesisDefenseRevisionService $revisionService,
        private readonly ThesisProjectNoteService $noteService,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $examinerRecords = $this->dosenThesisWorkspaceService
            ->defenseExaminerAssignments($lecturer)
            ->filter(fn(ThesisDefenseExaminer $examiner): bool => in_array($examiner->defense?->type, ['sempro', 'sidang'], true))
            ->values();

        $chatThreadIds = MentorshipChatThread::query()
            ->whereIn('context_id', $examinerRecords->pluck('defense_id')->unique())
            ->whereIn('type', ['sempro', 'sidang'])
            ->whereHas('participants', fn ($query) => $query->where('user_id', $lecturer->id)->where('role', 'examiner'))
            ->get(['id', 'type', 'context_id', 'student_user_id'])
            ->keyBy(fn (MentorshipChatThread $thread): string => $thread->type.':'.$thread->context_id.':'.$thread->student_user_id);

        $defenses = $examinerRecords->map(function (ThesisDefenseExaminer $examiner) use ($lecturer, $chatThreadIds): array {
            $defense = $examiner->defense;
            $project = $defense?->project;
            $student = $project?->student;
            $title = $project?->latestTitle;
            $terminology = $project === null
                ? AcademicTerminology::neutral()
                : AcademicTerminology::forProject($project);
            $chatThread = $defense === null ? null : $chatThreadIds->get($defense->type.':'.$defense->id.':'.$student?->id);

            $otherExaminers = $defense?->examiners
                ?->filter(fn(ThesisDefenseExaminer $item) => $item->lecturer_user_id !== $lecturer->id)
                ->map(fn(ThesisDefenseExaminer $item): array => [
                    'name' => $item->lecturer?->name ?? '-',
                    'role' => $item->role,
                    'order' => $item->order_no,
                    'decision' => $item->decision,
                    'score' => $item->score,
                ])
                ->values()
                ->all() ?? [];

            $verifiableRevisionIds = $defense?->revisions
                ?->filter(fn (ThesisRevision $revision): bool => $revision->requested_by_user_id === $lecturer->id
                    && in_array($revision->status, ['open', 'submitted'], true)
                    && ThesisRevisionPayload::currentRoundStatus($revision) === ThesisRevisionRound::STATUS_AWAITING_REVIEW)
                ->pluck('id')->values()->all() ?? [];

            return [
                'defenseId' => $defense?->id,
                'projectId' => $project?->id,
                'type' => $defense?->type,
                'typeLabel' => $defense?->type === 'sidang'
                    ? $terminology['finalExam']
                    : $terminology['proposalExamShort'],
                'terminology' => $terminology,
                'scoreRequired' => $defense?->requiresScore() ?? true,
                'attemptNo' => $defense?->attempt_no,
                'studentName' => $student?->name ?? '-',
                'studentNim' => $student?->mahasiswaProfile?->nim ?? '-',
                'studentUserId' => $student?->id,
                'chatUrl' => $chatThread === null ? null : route('dosen.pesan', ['thread' => $chatThread->id]),
                'titleId' => $title?->title_id ?? '-',
                'titleEn' => $title?->title_en ?? '-',
                'defenseStatus' => $defense?->status,
                'defenseResult' => $defense?->result,
                'scheduledFor' => WitaDateTime::formatOrNull($defense?->scheduled_for),
                'scheduledAt' => $defense?->scheduled_for?->toIso8601String(),
                'location' => $defense?->location ?? '-',
                'mode' => $defense?->mode ?? '-',
                'myExaminerId' => $examiner->id,
                'myRole' => $examiner->role,
                'myOrder' => $examiner->order_no,
                'myDecision' => $examiner->decision,
                'myScore' => $examiner->score,
                'myDecisionNotes' => $examiner->notes,
                'myRevisionNotes' => $examiner->revision_notes,
                'myRevisionItems' => collect($examiner->revision_items ?? [])
                    ->map(fn (array $entry): array => [
                        'location' => $entry['location'] ?? null,
                        'description' => $entry['description'] ?? '',
                        'priority' => $entry['priority'] ?? null,
                        'priorityLabel' => ThesisRevisionPayload::priorityLabel($entry['priority'] ?? null),
                    ])
                    ->values()
                    ->all(),
                'myAnnotation' => $defense?->documents
                    ->where('kind', ThesisRevisionRoundService::ANNOTATION_KIND)
                    ->where('uploaded_by_user_id', $lecturer->id)
                    ->whereNull('revision_id')
                    ->sortByDesc('id')
                    ->map(fn (ThesisDocument $document): array => ThesisRevisionPayload::document($document))
                    ->first(),
                'otherExaminers' => $otherExaminers,
                'revisionLog' => DefenseRevisionLog::forDefense($defense),
                'verifiableRevisionIds' => $verifiableRevisionIds,
            ];
        })->all();

        return Inertia::render('dosen/seminar-proposal', [
            'defenses' => $defenses,
            'projectNotes' => $this->noteService->payload(
                $lecturer,
                $examinerRecords->pluck('defense.project')->filter()->unique('id'),
            ),
            'workspaceEvents' => $this->dosenScheduleWorkspaceService->workspaceEvents($lecturer),
        ]);
    }

    public function submitDecision(SubmitDefenseDecisionRequest $request, ThesisDefense $defense): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $validated = $request->validated();

        if ($defense->type === 'sempro' && $validated['decision'] === 'pass' && (filled($validated['revision_notes'] ?? null) || $request->hasRevisionItems())) {
            $validated['decision'] = 'pass_with_revision';
        }

        try {
            $this->decisionService->submit($lecturer, $defense, $validated, $request->file('revision_annotation'));
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'decision' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('dosen.seminar-proposal')
            ->with('success', 'Keputusan berhasil disimpan.');
    }

    public function verifyRevision(VerifyRevisionRequest $request, ThesisRevision $revision): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        try {
            $this->revisionService->verify(
                $lecturer,
                $revision,
                $request->verdicts(),
                $request->newItems(),
                $request->file('annotation'),
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('dosen.seminar-proposal')
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('dosen.seminar-proposal')
            ->with('success', 'Verifikasi revisi berhasil disimpan.');
    }
}
