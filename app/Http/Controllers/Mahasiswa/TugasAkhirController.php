<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AdvisorType;
use App\Http\Controllers\Controller;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\User;
use App\Services\ThesisProjectStudentService;
use App\Services\UserProfilePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TugasAkhirController extends Controller
{
    public function __construct(
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $project = $this->resolveProjectForStudent($student);
        $latestSempro = $project?->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();
        $latestSidang = $project?->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        $currentTitle = $project?->titles
            ?->where('status', 'approved')
            ->sortByDesc('version_no')
            ->first() ?? $project?->latestTitle;

        $proposalDocument = $project?->documents
            ?->where('kind', 'proposal')
            ->sortByDesc(fn($document): int => $document->updated_at?->getTimestamp() ?? 0)
            ->first();

        $primaryAdvisor = $project?->activeSupervisorAssignments->firstWhere('role', AdvisorType::Primary->value);
        $secondaryAdvisor = $project?->activeSupervisorAssignments->firstWhere('role', AdvisorType::Secondary->value);
        $examinerOne = $latestSempro?->examiners->sortBy('order_no')->firstWhere('order_no', 1);
        $examinerTwo = $latestSempro?->examiners->sortBy('order_no')->firstWhere('order_no', 2);
        $workflow = $project === null ? null : $this->resolveProjectWorkflow($project);

        return Inertia::render('tugas-akhir', [
            'submission' => $project === null ? null : [
                'id' => $project->id,
                'program_studi' => $project->programStudi?->name
                    ?? $this->resolveProgramStudiForStudent($student),
                'title_id' => $currentTitle?->title_id ?? '-',
                'title_en' => $currentTitle?->title_en ?? '-',
                'proposal_summary' => $currentTitle?->proposal_summary ?? '-',
                'workflow' => $workflow,
                'proposal_file_name' => $proposalDocument?->file_name,
                'proposal_file_view_url' => $proposalDocument?->id !== null
                    ? route('files.thesis-documents.download', [
                        'document' => $proposalDocument->id,
                        'inline' => 1,
                    ])
                    : null,
                'proposal_file_download_url' => $proposalDocument?->id !== null
                    ? route('files.thesis-documents.download', [
                        'document' => $proposalDocument->id,
                    ])
                    : null,
            ],
            'assignedLecturers' => [
                'pembimbing1' => $primaryAdvisor?->lecturer?->name,
                'pembimbing2' => $secondaryAdvisor?->lecturer?->name,
                'penguji1' => $examinerOne?->lecturer?->name,
                'penguji2' => $examinerTwo?->lecturer?->name,
                'ketuaSidang' => null,
                'sekretarisSidang' => null,
                'pengujiSidang' => $latestSidang?->examiners->sortBy('order_no')->firstWhere('role', 'examiner')?->lecturer?->name,
            ],
            'advisorProfiles' => array_values(array_filter([
                $this->userProfilePresenter->summary($primaryAdvisor?->lecturer),
                $this->userProfilePresenter->summary($secondaryAdvisor?->lecturer),
            ])),
            'semproExaminerProfiles' => array_values(array_filter(
                ($latestSempro?->examiners ?? collect())
                    ->sortBy('order_no')
                    ->map(fn($examiner): ?array => $this->userProfilePresenter->summary($examiner->lecturer))
                    ->all(),
            )),
            'sidangExaminerProfiles' => array_values(array_filter(
                ($latestSidang?->examiners ?? collect())
                    ->where('role', 'examiner')
                    ->sortBy('order_no')
                    ->map(fn($examiner): ?array => $this->userProfilePresenter->summary($examiner->lecturer))
                    ->all(),
            )),
            'semproDate' => $latestSempro?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
            'sidangDate' => $latestSidang?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
            'semproResult' => $this->mapDefenseResult($latestSempro, 'Seminar Proposal'),
            'sidangResult' => $this->mapDefenseResult($latestSidang, 'Sidang Skripsi'),
            'defenseHistory' => $project === null ? ['sempro' => [], 'sidang' => []] : [
                'sempro' => $this->mapDefenseHistory($project, 'sempro'),
                'sidang' => $this->mapDefenseHistory($project, 'sidang'),
            ],
            'profileProgramStudi' => $this->resolveProgramStudiForStudent($student),
            'flashMessage' => $request->session()->get('success'),
            'errorMessage' => $request->session()->get('error'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $validated = $request->validate([
            'title_id' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'proposal_summary' => ['required', 'string'],
            'proposal_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $programStudiId = $student->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            return back()->with('error', 'Program studi Anda belum diatur. Hubungi admin terlebih dahulu.');
        }

        $exists = ThesisProject::query()
            ->where('student_user_id', $student->id)
            ->where('state', 'active')
            ->exists();

        if ($exists) {
            return back()->with('error', 'Anda sudah memiliki pengajuan Judul & Proposal aktif.');
        }

        app(ThesisProjectStudentService::class)->submit(
            student: $student,
            data: $validated,
            proposalFile: $request->file('proposal_file'),
        );

        return back()->with('success', 'Judul & Proposal berhasil diajukan dan sedang menunggu review Admin.');
    }

    public function update(Request $request, ThesisProject $project): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);
        abort_unless($project->student_user_id === $student->id, 403);

        if (! app(ThesisProjectStudentService::class)->canEditSubmission($project)) {
            return back()->with('error', 'Pengajuan hanya dapat diedit saat masih ditinjau admin, sempro terjadwal, sempro gagal, atau revisi sempro masih aktif.');
        }

        $validated = $request->validate([
            'title_id' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'proposal_summary' => ['required', 'string'],
            'proposal_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $programStudiId = $student->mahasiswaProfile?->program_studi_id;

        if ($programStudiId === null) {
            return back()->with('error', 'Program studi Anda belum diatur. Hubungi admin terlebih dahulu.');
        }

        app(ThesisProjectStudentService::class)->updatePendingSubmission(
            student: $student,
            project: $project,
            data: $validated,
            proposalFile: $request->file('proposal_file'),
        );

        return back()->with('success', 'Pengajuan Judul & Proposal berhasil diperbarui.');
    }

    private function resolveProgramStudiForStudent(User $student, ?string $fallback = null): string
    {
        $programStudi = $student->mahasiswaProfile?->programStudi?->name;

        if (is_string($programStudi) && trim($programStudi) !== '') {
            return trim($programStudi);
        }

        return $fallback ?? '-';
    }

    private function resolveProjectForStudent(User $student): ?ThesisProject
    {
        $activeProject = $this->projectQueryForStudent($student->id)
            ->where('state', 'active')
            ->latest('started_at')
            ->first();

        if ($activeProject instanceof ThesisProject) {
            return $activeProject;
        }

        return $this->projectQueryForStudent($student->id)
            ->latest('started_at')
            ->first();
    }

    private function projectQueryForStudent(int $studentUserId)
    {
        return ThesisProject::query()
            ->where('student_user_id', $studentUserId)
            ->with([
                'programStudi',
                'latestTitle',
                'titles',
                'documents',
                'activeSupervisorAssignments.lecturer',
                'defenses' => fn($query) => $query
                    ->with(['examiners.lecturer'])
                    ->orderBy('type')
                    ->orderBy('attempt_no'),
                'revisions.requestedBy',
            ]);
    }

    private function resolveProjectWorkflow(ThesisProject $project): array
    {
        /** @var ThesisDefense|null $latestSidang */
        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        $hasOpenRevisions = $project->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty();
        $key = 'title_review_pending';

        if ($latestSidang instanceof ThesisDefense) {
            if ($latestSidang->status === 'scheduled') {
                $key = 'sidang_scheduled';
            } elseif ($latestSidang->status === 'awaiting_finalization') {
                $key = 'sidang_waiting_result';
            } elseif ($latestSidang->status === 'completed') {
                $key = match ($latestSidang->result) {
                    'pass' => 'completed',
                    'pass_with_revision' => $hasOpenRevisions ? 'sidang_revision' : 'completed',
                    'fail' => 'sidang_failed',
                    default => 'sidang_scheduled',
                };
            }

            return [
                'key' => $key,
                'label' => $this->workflowLabel($key),
                'description' => $this->workflowDescription($key),
                'can_edit' => $this->canEditProjectSubmission($project),
            ];
        }

        /** @var ThesisDefense|null $latestSempro */
        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            if ($latestSempro->status === 'scheduled') {
                $key = 'sempro_scheduled';
            } elseif ($latestSempro->status === 'awaiting_finalization') {
                $key = 'sempro_waiting_result';
            } elseif ($latestSempro->status === 'completed' && $latestSempro->result === 'pass_with_revision') {
                $key = $hasOpenRevisions
                    ? 'sempro_revision'
                    : ($project->activeSupervisorAssignments->isNotEmpty()
                        ? 'research_in_progress'
                        : 'sempro_passed');
            } elseif ($latestSempro->status === 'completed' && $latestSempro->result === 'pass') {
                $key = $project->activeSupervisorAssignments->isNotEmpty()
                    ? 'research_in_progress'
                    : 'sempro_passed';
            } elseif ($latestSempro->status === 'completed' && $latestSempro->result === 'fail') {
                $key = 'sempro_failed';
            }
        } elseif ($project->phase === 'title_review') {
            $key = 'title_review_pending';
        } elseif ($project->activeSupervisorAssignments->isNotEmpty()) {
            $key = 'research_in_progress';
        }

        return [
            'key' => $key,
            'label' => $this->workflowLabel($key),
            'description' => $this->workflowDescription($key),
            'can_edit' => $this->canEditProjectSubmission($project),
        ];
    }

    private function workflowLabel(string $key): string
    {
        return match ($key) {
            'title_review_pending' => 'Menunggu Persetujuan',
            'sempro_scheduled' => 'Sempro Dijadwalkan',
            'sempro_waiting_result' => 'Menunggu Hasil Sempro',
            'sempro_revision' => 'Revisi Sempro',
            'sempro_failed' => 'Sempro Tidak Lulus',
            'sempro_passed' => 'Sempro Selesai',
            'research_in_progress' => 'Pembimbing Ditetapkan',
            'sidang_scheduled' => 'Sidang Dijadwalkan',
            'sidang_waiting_result' => 'Menunggu Hasil Sidang',
            'sidang_revision' => 'Revisi Sidang',
            'completed' => 'Sidang Selesai',
            'sidang_failed' => 'Sidang Tidak Lulus',
            default => 'Dalam Proses',
        };
    }

    private function workflowDescription(string $key): string
    {
        return match ($key) {
            'title_review_pending' => 'Pengajuan judul dan proposal Anda sedang ditinjau admin.',
            'sempro_scheduled' => 'Sempro sudah dijadwalkan. Cek dosen dan tanggal pada halaman ini.',
            'sempro_waiting_result' => 'Semua keputusan dosen untuk sempro sudah masuk. Menunggu hasil resmi dari admin.',
            'sempro_revision' => 'Sempro selesai dengan revisi. Periksa catatan revisi dari penguji.',
            'sempro_failed' => 'Sempro belum lulus. Tunggu penjadwalan ulang dari admin untuk attempt berikutnya.',
            'sempro_passed' => 'Tahap Sempro telah selesai. Menunggu pembimbing aktif atau progres penelitian berikutnya.',
            'research_in_progress' => 'Dosen pembimbing sudah ditetapkan. Lanjutkan proses penelitian dan bimbingan.',
            'sidang_scheduled' => 'Sidang skripsi sudah dijadwalkan. Siapkan dokumen akhir Anda dengan baik.',
            'sidang_waiting_result' => 'Seluruh keputusan dosen untuk sidang sudah masuk. Menunggu hasil resmi dari admin.',
            'sidang_revision' => 'Sidang selesai dengan revisi. Periksa catatan revisi dari tim sidang.',
            'completed' => 'Tahap sidang skripsi telah selesai.',
            'sidang_failed' => 'Sidang belum lulus. Hubungi admin dan pembimbing untuk langkah berikutnya.',
            default => 'Pengajuan sedang diproses admin.',
        };
    }

    private function canEditProjectSubmission(?ThesisProject $project): bool
    {
        return app(ThesisProjectStudentService::class)->canEditSubmission($project);
    }

    private function mapDefenseResult(?ThesisDefense $defense, string $label): ?array
    {
        if (! $defense instanceof ThesisDefense || $defense->status !== 'completed') {
            return null;
        }

        if ($defense->type === 'sempro' && $defense->result === 'fail') {
            return null;
        }

        return [
            'label' => $label,
            'resultLabel' => match ($defense->result) {
                'pass' => 'Lulus',
                'pass_with_revision' => 'Lulus dengan Revisi',
                'fail' => 'Tidak Lulus',
                default => 'Menunggu Hasil',
            },
            'scheduledFor' => $defense->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
            'location' => $defense->location,
            'examiners' => $defense->examiners
                ->sortBy('order_no')
                ->map(fn($examiner): array => [
                    'id' => $examiner->id,
                    'name' => $examiner->lecturer?->name ?? '-',
                    'roleLabel' => $this->defenseRoleLabel($examiner->role, $examiner->order_no),
                    'decisionLabel' => match ($examiner->decision) {
                        'pass' => 'Lulus',
                        'pass_with_revision' => 'Lulus dengan Revisi',
                        'fail' => 'Tidak Lulus',
                        default => 'Belum Ada Keputusan',
                    },
                    'score' => $examiner->score,
                    'decisionNotes' => $examiner->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapDefenseHistory(ThesisProject $project, string $type): array
    {
        return $project->defenses
            ->where('type', $type)
            ->sortByDesc('attempt_no')
            ->map(function (ThesisDefense $defense) use ($project): array {
                $title = $defense->titleVersion;
                $proposalDocument = $project->documents
                    ->where('kind', 'proposal')
                    ->where('title_version_id', $defense->title_version_id)
                    ->sortByDesc(fn(ThesisDocument $document): int => $document->uploaded_at?->getTimestamp() ?? 0)
                    ->first();

                return [
                    'id' => $defense->id,
                    'attemptNo' => $defense->attempt_no,
                    'statusLabel' => match ($defense->status) {
                        'draft' => 'Draft',
                        'scheduled' => 'Dijadwalkan',
                        'awaiting_finalization' => 'Menunggu Finalisasi',
                        'completed' => 'Selesai',
                        default => ucwords(str_replace('_', ' ', $defense->status)),
                    },
                    'resultLabel' => match ($defense->result) {
                        'pending' => 'Menunggu Hasil',
                        'pass' => 'Lulus',
                        'pass_with_revision' => 'Lulus dengan Revisi',
                        'fail' => 'Tidak Lulus',
                        default => ucwords(str_replace('_', ' ', $defense->result)),
                    },
                    'scheduledFor' => $defense->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
                    'location' => $defense->location,
                    'mode' => $defense->mode,
                    'officialNotes' => $defense->notes,
                    'titleId' => $title?->title_id ?? '-',
                    'titleEn' => $title?->title_en,
                    'proposalSummary' => $title?->proposal_summary,
                    'proposalFileName' => $proposalDocument?->file_name,
                    'proposalFileViewUrl' => $proposalDocument?->id !== null
                        ? route('files.thesis-documents.download', ['document' => $proposalDocument->id, 'inline' => 1])
                        : null,
                    'proposalFileDownloadUrl' => $proposalDocument?->id !== null
                        ? route('files.thesis-documents.download', ['document' => $proposalDocument->id])
                        : null,
                    'examiners' => $defense->examiners
                        ->sortBy('order_no')
                        ->map(fn($examiner): array => [
                            'id' => $examiner->id,
                            'name' => $examiner->lecturer?->name ?? '-',
                            'roleLabel' => $this->defenseRoleLabel($examiner->role, $examiner->order_no),
                            'decisionLabel' => match ($examiner->decision) {
                                'pass' => 'Lulus',
                                'pass_with_revision' => 'Lulus dengan Revisi',
                                'fail' => 'Tidak Lulus',
                                default => 'Belum Ada Keputusan',
                            },
                            'score' => $examiner->score,
                            'decisionNotes' => $examiner->notes,
                        ])
                        ->values()
                        ->all(),
                    'revisions' => $project->revisions
                        ->where('defense_id', $defense->id)
                        ->sortByDesc('id')
                        ->map(fn($revision): array => [
                            'id' => $revision->id,
                            'statusLabel' => match ($revision->status) {
                                'open' => 'Terbuka',
                                'submitted' => 'Dikirim',
                                'resolved' => 'Selesai',
                                default => ucwords(str_replace('_', ' ', $revision->status)),
                            },
                            'notes' => $revision->notes,
                            'requestedBy' => $revision->requestedBy?->name ?? '-',
                            'dueAt' => $revision->due_at?->locale('id')->translatedFormat('d F Y, H:i'),
                            'resolvedAt' => $revision->resolved_at?->locale('id')->translatedFormat('d F Y, H:i'),
                            'resolutionNotes' => $revision->resolution_notes,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function defenseRoleLabel(string $role, int $orderNo): string
    {
        return match ($role) {
            'primary_supervisor' => 'Pembimbing 1',
            'secondary_supervisor' => 'Pembimbing 2',
            default => 'Penguji',
        };
    }
}
