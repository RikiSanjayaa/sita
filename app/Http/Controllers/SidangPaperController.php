<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Http\Requests\StoreSidangPaperRequest;
use App\Models\ThesisDefense;
use App\Models\User;
use App\Policies\SidangPaperPolicy;
use App\Services\SidangPaperService;
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

    public function show(Request $request, ThesisDefense $defense, SidangPaperPolicy $policy): Response
    {
        abort_unless($defense->type === 'sidang' && $policy->manage($request->user(), (int) $defense->project->program_studi_id), 403);
        $defense->load('project.student.mahasiswaProfile', 'project.programStudi', 'titleVersion', 'examiners.lecturer');
        $project = $defense->project;
        $admin = $request->user()->hasRole('admin') || $request->user()->hasRole('super_admin');
        $fromQueue = $admin && $request->query('dari') === 'daftar';
        $terms = AcademicTerminology::forProject($project);

        return Inertia::render('sidang/berkas', [
            'defense' => [
                'id' => $defense->id,
                'label' => $terms['finalExam'].' #'.$defense->attempt_no,
                'status' => $defense->status,
                'editable' => in_array($defense->status, ['scheduled', 'awaiting_finalization'], true)
                    && $defense->sidang_paper === null && $project->state === 'active',
                'canPrint' => $defense->scheduled_for !== null && $defense->status !== 'cancelled',
                'name' => $project->student?->name,
                'nim' => $project->student?->mahasiswaProfile?->nim,
                'title' => $defense->titleVersion?->title_id,
                'schedule' => WitaDateTime::format($defense->scheduled_for),
                'location' => $defense->location,
                'moderatorId' => $defense->moderator_user_id,
                'sk' => $defense->sidang_sk,
                'panel' => $defense->examiners->sortBy('order_no')->values()->map(fn ($examiner): array => [
                    'id' => $examiner->id,
                    'name' => $examiner->lecturer?->name ?? '-',
                    'supervisor' => $examiner->isSupervisorRole(),
                    'score' => $examiner->score,
                ]),
                'summary' => $defense->sidang_paper === null ? null : [
                    'value' => $defense->sidang_paper['value'], 'grade' => $defense->sidang_paper['grade'],
                ],
            ],
            'moderators' => User::query()->whereHas('dosenProfile', function ($query) use ($project, $defense): void {
                $query->where('is_active', true)->where(function ($query) use ($project, $defense): void {
                    $query->where('program_studi_id', $project->program_studi_id)
                        ->orWhereIn('user_id', $defense->examiners->pluck('lecturer_user_id'));
                });
            })->orderBy('name')->get(['id', 'name']),
            'weights' => array_intersect_key(SidangSettings::forProgram($project->programStudi), SidangPaperService::ROLES),
            'forms' => self::FORMS,
            'fromQueue' => $fromQueue,
            'backUrl' => match (true) {
                $fromQueue => route('filament.admin.resources.thesis-projects.index', ['tab' => ListThesisProjects::SIDANG_PAPER_TAB]),
                $admin => route('filament.admin.resources.thesis-projects.view', $project),
                default => route('kaprodi.sempro-sidang'),
            },
            'finalizeUrl' => $admin && $project->state === 'active' && $defense->status === 'awaiting_finalization'
                ? route('filament.admin.resources.thesis-projects.view', ['record' => $project, 'action' => 'complete_sidang'])
                : null,
            'admin' => $admin,
        ]);
    }

    public function store(StoreSidangPaperRequest $request, ThesisDefense $defense, SidangPaperService $service): RedirectResponse
    {
        $service->record($defense, $request->user(), $request->validated());

        return to_route('sidang.paper.show', [
            'defense' => $defense,
            'dari' => $request->query('dari') === 'daftar' ? 'daftar' : null,
        ])->with('success', 'Data berkas sidang disimpan.');
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
