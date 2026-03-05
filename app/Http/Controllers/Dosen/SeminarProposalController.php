<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Http\Controllers\Controller;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\SemproRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SeminarProposalController extends Controller
{
    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $examinerRecords = SemproExaminer::query()
            ->where('examiner_user_id', $lecturer->id)
            ->with([
                'sempro.submission.student.mahasiswaProfile',
                'sempro.examiners.examiner',
                'sempro.revisions.requestedBy',
            ])
            ->orderByDesc('id')
            ->get();

        $sempros = $examinerRecords->map(function (SemproExaminer $examiner) use ($lecturer) {
            $sempro = $examiner->sempro;
            $submission = $sempro?->submission;
            $student = $submission?->student;

            // Other examiners' decisions (excluding self)
            $otherExaminers = $sempro?->examiners
                ?->filter(fn(SemproExaminer $e) => $e->examiner_user_id !== $lecturer->id)
                ->map(fn(SemproExaminer $e) => [
                    'name' => $e->examiner?->name ?? '-',
                    'order' => $e->examiner_order,
                    'decision' => $e->decision,
                    'score' => $e->score,
                ])
                ->values()
                ->all() ?? [];

            // Revisions
            $revisions = $sempro?->revisions
                ?->map(fn(SemproRevision $r) => [
                    'id' => $r->id,
                    'notes' => $r->notes,
                    'status' => $r->status,
                    'dueAt' => $r->due_at?->format('d M Y'),
                    'resolvedAt' => $r->resolved_at?->format('d M Y'),
                    'requestedBy' => $r->requestedBy?->name ?? '-',
                ])
                ->all() ?? [];

            return [
                'semproId' => $sempro?->id,
                'studentName' => $student?->name ?? '-',
                'studentNim' => $student?->mahasiswaProfile?->nim ?? '-',
                'titleId' => $submission?->title_id ?? '-',
                'titleEn' => $submission?->title_en ?? '-',
                'semproStatus' => $sempro?->status,
                'scheduledFor' => $sempro?->scheduled_for?->format('d M Y H:i'),
                'location' => $sempro?->location ?? '-',
                'mode' => $sempro?->mode ?? '-',
                'myExaminerId' => $examiner->id,
                'myOrder' => $examiner->examiner_order,
                'myDecision' => $examiner->decision,
                'myScore' => $examiner->score,
                'myDecisionNotes' => $examiner->decision_notes,
                'otherExaminers' => $otherExaminers,
                'revisions' => $revisions,
            ];
        })->all();

        return Inertia::render('dosen/seminar-proposal', [
            'sempros' => $sempros,
        ]);
    }

    public function submitDecision(Request $request, Sempro $sempro): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $examiner = SemproExaminer::query()
            ->where('sempro_id', $sempro->id)
            ->where('examiner_user_id', $lecturer->id)
            ->first();

        abort_if($examiner === null, 403, 'Anda bukan penguji untuk sempro ini.');

        // Only allow decisions on scheduled or revision_open sempros
        abort_if(
            ! in_array($sempro->status, [SemproStatus::Scheduled->value, SemproStatus::RevisionOpen->value], true),
            422,
            'Sempro tidak dalam status yang dapat dinilai.'
        );

        $validated = $request->validate([
            'decision' => [
                'required',
                Rule::in([
                    SemproExaminerDecision::Approved->value,
                    SemproExaminerDecision::NeedsRevision->value,
                ]),
            ],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
            'revision_notes' => ['nullable', 'required_if:decision,'.SemproExaminerDecision::NeedsRevision->value, 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($examiner, $sempro, $validated, $lecturer): void {
            $examiner->update([
                'decision' => $validated['decision'],
                'score' => $validated['score'],
                'decision_notes' => $validated['decision_notes'] ?? null,
                'decided_at' => now(),
            ]);

            // If needs revision, create a SemproRevision record and set sempro status
            if ($validated['decision'] === SemproExaminerDecision::NeedsRevision->value) {
                SemproRevision::query()->create([
                    'sempro_id' => $sempro->id,
                    'notes' => $validated['revision_notes'],
                    'status' => 'open',
                    'due_at' => now()->addDays(14),
                    'requested_by_user_id' => $lecturer->id,
                ]);

                if ($sempro->status !== SemproStatus::RevisionOpen->value) {
                    $sempro->forceFill(['status' => SemproStatus::RevisionOpen->value])->save();
                }
            }
        });

        return redirect()->route('dosen.seminar-proposal')
            ->with('flashMessage', 'Keputusan berhasil disimpan.');
    }
}
