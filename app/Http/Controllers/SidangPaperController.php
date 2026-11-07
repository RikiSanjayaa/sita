<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Http\Requests\StoreSidangPaperRequest;
use App\Models\ThesisDefense;
use App\Policies\SidangPaperPolicy;
use App\Services\SidangPaperService;
use App\Services\SidangScoreService;
use App\Services\SidangWordWriter;
use App\Support\AcademicTerminology;
use App\Support\SidangSettings;
use App\Support\WitaDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SidangPaperController extends Controller
{
    public const FORMS = [
        'berita-acara' => 'Berita Acara', 'revisi-penguji' => 'Catatan Perbaikan Penguji',
        'daftar-hadir' => 'Daftar Hadir', 'nilai-penguji' => 'Formulir Penilaian Penguji',
        'nilai-pembimbing' => 'Formulir Penilaian Pembimbing', 'rekap-nilai' => 'Lembar Total Penilaian',
        'masukan' => 'Masukan untuk Pembimbing', 'tanya-jawab' => 'Pertanyaan dan Jawaban',
        'revisi-pembimbing' => 'Catatan Perbaikan Pembimbing',
    ];

    public function show(Request $request, ThesisDefense $defense, SidangPaperPolicy $policy): Response|RedirectResponse
    {
        abort_unless($defense->type === 'sidang' && $policy->manage($request->user(), (int) $defense->project->program_studi_id), 403);
        if ($request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return redirect(ThesisProjectResource::getUrl('sidang-paper', [
                'record' => $defense->project_id, 'defense' => $defense,
                'dari' => $request->query('dari') === 'daftar' ? 'daftar' : null,
            ]));
        }
        $defense->load('project.student.mahasiswaProfile', 'project.programStudi', 'titleVersion', 'examiners.lecturer.dosenProfile', 'examiners.scoreRecordedBy', 'examiners.paperVerifiedBy');
        $project = $defense->project;
        $scores = app(SidangScoreService::class);
        $summary = $scores->summary($defense);
        $terms = AcademicTerminology::forProject($project);

        return Inertia::render('sidang/berkas', [
            'defense' => [
                'id' => $defense->id,
                'label' => $terms['finalExam'].' #'.$defense->attempt_no,
                'status' => $defense->status,
                'editable' => $scores->editable($defense),
                'canPrint' => $defense->scheduled_for !== null && $defense->status !== 'cancelled',
                'name' => $project->student?->name,
                'nim' => $project->student?->mahasiswaProfile?->nim,
                'title' => $defense->titleVersion?->title_id,
                'schedule' => WitaDateTime::format($defense->scheduled_for),
                'location' => $defense->location,
                'sk' => $defense->sidang_sk,
                'panel' => $defense->examiners->sortBy('order_no')->values()->map(fn ($examiner): array => [
                    'id' => $examiner->id,
                    'name' => $examiner->lecturer?->name ?? '-',
                    'supervisor' => $examiner->isSupervisorRole(),
                    'score' => $examiner->score,
                    'recordedBy' => $examiner->scoreRecordedBy?->name,
                    'recordedAt' => WitaDateTime::formatOrNull($examiner->score_recorded_at),
                    'source' => $examiner->score_source,
                    'verifiedBy' => $examiner->paperVerifiedBy?->name,
                    'verifiedAt' => WitaDateTime::formatOrNull($examiner->paper_verified_at),
                ]),
                'panelIds' => $scores->panelIds($defense),
                'summary' => $summary === null ? null : ['value' => $summary['value'], 'grade' => $summary['grade']],
            ],
            'weights' => array_intersect_key(SidangSettings::forProgram($project->programStudi), SidangPaperService::ROLES),
            'forms' => self::FORMS,
            'returnFilters' => $this->returnFilters($request),
            'backUrl' => route('kaprodi.sempro-sidang', $this->returnFilters($request)),
            'studentProfileUrl' => route('users.profile.show', ['user' => $project->student_user_id]),
        ]);
    }

    public function store(StoreSidangPaperRequest $request, ThesisDefense $defense, SidangPaperService $service): RedirectResponse
    {
        $service->record($defense, $request->user(), $request->validated());

        return to_route('sidang.paper.show', [
            'defense' => $defense,
            'dari' => $request->query('dari') === 'daftar' ? 'daftar' : null,
            ...$this->returnFilters($request),
        ])->with('success', 'Data berkas sidang disimpan.');
    }

    /** @return array<string, string> */
    private function returnFilters(Request $request): array
    {
        $filters = [];
        foreach (['search', 'type', 'status', 'page'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '' && mb_strlen($value) <= 200) {
                $filters[$key] = $value;
            }
        }
        if (isset($filters['type']) && ! in_array($filters['type'], ['sempro', 'sidang'], true)) {
            unset($filters['type']);
        }
        if (isset($filters['status']) && ! in_array($filters['status'], ['scheduled', 'awaiting_finalization', 'completed'], true)) {
            unset($filters['status']);
        }
        if (isset($filters['page']) && filter_var($filters['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            unset($filters['page']);
        }

        return $filters;
    }

    public function print(Request $request, ThesisDefense $defense, SidangPaperPolicy $policy, SidangWordWriter $writer): StreamedResponse
    {
        abort_unless($policy->print($request->user(), $defense), 403);
        abort_unless($defense->scheduled_for !== null && $defense->status !== 'cancelled', 404);
        $form = $request->query('lembar');
        abort_unless($form === null || (is_string($form) && isset(self::FORMS[$form])), 404);
        $defense->load(
            'project.student.mahasiswaProfile', 'project.programStudi.faculty',
            'project.programStudi.kaprodiAssignments.user.dosenProfile', 'titleVersion',
            'examiners.lecturer.dosenProfile', 'moderator', 'revisions.items',
        );

        return response()->streamDownload(
            fn () => $writer->write($defense, $form),
            ($form ?? 'paket-sidang').'-'.$defense->id.'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Cache-Control' => 'private, no-store'],
        );
    }
}
