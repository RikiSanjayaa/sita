<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisRevision;
use App\Services\DosenThesisWorkspaceService;
use App\Services\ThesisDefenseExaminerDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SeminarProposalController extends Controller
{
    public function __construct(
        private readonly DosenThesisWorkspaceService $dosenThesisWorkspaceService,
        private readonly ThesisDefenseExaminerDecisionService $decisionService,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $examinerRecords = $this->dosenThesisWorkspaceService
            ->defenseExaminerAssignments($lecturer)
            ->filter(fn(ThesisDefenseExaminer $examiner): bool => in_array($examiner->defense?->type, ['sempro', 'sidang'], true))
            ->values();

        $defenses = $examinerRecords->map(function (ThesisDefenseExaminer $examiner) use ($lecturer): array {
            $defense = $examiner->defense;
            $project = $defense?->project;
            $student = $project?->student;
            $title = $project?->latestTitle;

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

            $revisions = $defense?->revisions
                ?->map(fn(ThesisRevision $revision): array => [
                    'id' => $revision->id,
                    'notes' => $revision->notes,
                    'status' => $revision->status,
                    'dueAt' => $revision->due_at?->format('d M Y'),
                    'resolvedAt' => $revision->resolved_at?->format('d M Y'),
                    'requestedBy' => $revision->requestedBy?->name ?? '-',
                ])
                ->values()
                ->all() ?? [];

            return [
                'defenseId' => $defense?->id,
                'type' => $defense?->type,
                'typeLabel' => $defense?->type === 'sidang' ? 'Sidang' : 'Sempro',
                'attemptNo' => $defense?->attempt_no,
                'studentName' => $student?->name ?? '-',
                'studentNim' => $student?->mahasiswaProfile?->nim ?? '-',
                'titleId' => $title?->title_id ?? '-',
                'titleEn' => $title?->title_en ?? '-',
                'defenseStatus' => $defense?->status,
                'defenseResult' => $defense?->result,
                'scheduledFor' => $defense?->scheduled_for?->format('d M Y H:i'),
                'location' => $defense?->location ?? '-',
                'mode' => $defense?->mode ?? '-',
                'myExaminerId' => $examiner->id,
                'myRole' => $examiner->role,
                'myOrder' => $examiner->order_no,
                'myDecision' => $examiner->decision,
                'myScore' => $examiner->score,
                'myDecisionNotes' => $examiner->notes,
                'otherExaminers' => $otherExaminers,
                'revisions' => $revisions,
            ];
        })->all();

        return Inertia::render('dosen/seminar-proposal', [
            'defenses' => $defenses,
        ]);
    }

    public function submitDecision(Request $request, ThesisDefense $defense): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $validated = $request->validate([
            'decision' => [
                'required',
                Rule::in([
                    'pass',
                    'pass_with_revision',
                    'fail',
                ]),
            ],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'revision_notes' => ['nullable', 'required_if:decision,pass_with_revision', 'string', 'max:2000'],
        ]);

        if ($defense->type === 'sempro' && $validated['decision'] === 'fail') {
            return back()->withErrors([
                'decision' => 'Sempro hanya dapat dinilai lulus atau lulus dengan revisi.',
            ]);
        }

        try {
            $this->decisionService->submit($lecturer, $defense, $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'decision' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('dosen.seminar-proposal')
            ->with('success', 'Keputusan berhasil disimpan.');
    }
}
