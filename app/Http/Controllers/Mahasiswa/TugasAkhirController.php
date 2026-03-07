<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AdvisorType;
use App\Enums\ThesisSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSubmission;
use App\Models\User;
use App\Services\LegacyThesisProjectBackfillService;
use App\Services\ThesisProjectStudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TugasAkhirController extends Controller
{
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

        $legacySubmission = $project?->legacySubmission;

        $primaryAdvisor = $project?->activeSupervisorAssignments->firstWhere('role', AdvisorType::Primary->value);
        $secondaryAdvisor = $project?->activeSupervisorAssignments->firstWhere('role', AdvisorType::Secondary->value);
        $examinerOne = $latestSempro?->examiners->sortBy('order_no')->firstWhere('order_no', 1);
        $examinerTwo = $latestSempro?->examiners->sortBy('order_no')->firstWhere('order_no', 2);
        $sidangChair = $latestSidang?->examiners->firstWhere('role', 'chair');
        $sidangSecretary = $latestSidang?->examiners->firstWhere('role', 'secretary');
        $sidangExaminer = $latestSidang?->examiners->firstWhere('role', 'examiner');

        return Inertia::render('tugas-akhir', [
            'submission' => $project === null ? null : [
                'id' => $legacySubmission?->id ?? $project->id,
                'program_studi' => $project->programStudi?->name
                    ?? $this->resolveProgramStudiForStudent($student),
                'title_id' => $currentTitle?->title_id ?? '-',
                'title_en' => $currentTitle?->title_en ?? '-',
                'proposal_summary' => $currentTitle?->proposal_summary ?? '-',
                'status' => $this->resolveProjectStatus($project),
                'proposal_file_name' => $proposalDocument?->file_name ?? ($legacySubmission?->proposal_file_path === null
                    ? null
                    : basename($legacySubmission->proposal_file_path)),
                'proposal_file_view_url' => $proposalDocument?->id !== null
                    ? route('files.thesis-documents.download', [
                        'document' => $proposalDocument->id,
                        'inline' => 1,
                    ])
                    : ($legacySubmission?->proposal_file_path === null
                        ? null
                        : route('files.thesis-proposals', [
                            'submission' => $legacySubmission->id,
                            'inline' => 1,
                        ])),
                'proposal_file_download_url' => $proposalDocument?->id !== null
                    ? route('files.thesis-documents.download', [
                        'document' => $proposalDocument->id,
                    ])
                    : ($legacySubmission?->proposal_file_path === null
                        ? null
                        : route('files.thesis-proposals', [
                            'submission' => $legacySubmission->id,
                        ])),
            ],
            'assignedLecturers' => [
                'pembimbing1' => $primaryAdvisor?->lecturer?->name,
                'pembimbing2' => $secondaryAdvisor?->lecturer?->name,
                'penguji1' => $examinerOne?->lecturer?->name,
                'penguji2' => $examinerTwo?->lecturer?->name,
                'ketuaSidang' => $sidangChair?->lecturer?->name,
                'sekretarisSidang' => $sidangSecretary?->lecturer?->name,
                'pengujiSidang' => $sidangExaminer?->lecturer?->name,
            ],
            'semproDate' => $latestSempro?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
            'sidangDate' => $latestSidang?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
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

    public function update(Request $request, ThesisSubmission $submission): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);
        abort_unless($submission->student_user_id === $student->id, 403);

        if ($submission->status !== ThesisSubmissionStatus::MenungguPersetujuan->value) {
            return back()->with('error', 'Pengajuan hanya dapat diedit sebelum diproses admin.');
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
            submission: $submission,
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
        $project = $this->projectQueryForStudent($student->id)
            ->where('state', 'active')
            ->latest('started_at')
            ->first();

        if ($project instanceof ThesisProject) {
            return $project;
        }

        $project = $this->projectQueryForStudent($student->id)
            ->latest('started_at')
            ->first();

        if ($project instanceof ThesisProject) {
            return $project;
        }

        $hasLegacySubmission = ThesisSubmission::query()
            ->where('student_user_id', $student->id)
            ->exists();

        if (! $hasLegacySubmission) {
            return null;
        }

        app(LegacyThesisProjectBackfillService::class)->backfill($student->id);

        return $this->projectQueryForStudent($student->id)
            ->where('state', 'active')
            ->latest('started_at')
            ->first()
            ?? $this->projectQueryForStudent($student->id)
                ->latest('started_at')
                ->first();
    }

    private function projectQueryForStudent(int $studentUserId)
    {
        return ThesisProject::query()
            ->where('student_user_id', $studentUserId)
            ->with([
                'programStudi',
                'legacySubmission',
                'latestTitle',
                'titles',
                'documents',
                'activeSupervisorAssignments.lecturer',
                'defenses' => fn($query) => $query
                    ->with(['examiners.lecturer'])
                    ->orderBy('type')
                    ->orderBy('attempt_no'),
                'revisions',
            ]);
    }

    private function resolveProjectStatus(ThesisProject $project): string
    {
        /** @var ThesisDefense|null $latestSidang */
        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            if ($latestSidang->status === 'scheduled') {
                return 'sidang_dijadwalkan';
            }

            if ($latestSidang->status === 'completed') {
                return match ($latestSidang->result) {
                    'pass' => 'sidang_selesai',
                    'pass_with_revision' => $project->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty()
                        ? 'revisi_sidang'
                        : 'sidang_selesai',
                    'fail' => 'sidang_gagal',
                    default => 'sidang_dijadwalkan',
                };
            }
        }

        /** @var ThesisDefense|null $latestSempro */
        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($project->phase === 'title_review') {
            return ThesisSubmissionStatus::MenungguPersetujuan->value;
        }

        if ($latestSempro instanceof ThesisDefense) {
            if ($latestSempro->status === 'scheduled') {
                return ThesisSubmissionStatus::SemproDijadwalkan->value;
            }

            if ($latestSempro->status === 'completed' && $latestSempro->result === 'pass_with_revision') {
                return $project->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty()
                    ? ThesisSubmissionStatus::RevisiSempro->value
                    : ThesisSubmissionStatus::SemproSelesai->value;
            }
        }

        if ($project->activeSupervisorAssignments->isNotEmpty()) {
            return ThesisSubmissionStatus::PembimbingDitetapkan->value;
        }

        if ($latestSempro instanceof ThesisDefense && $latestSempro->result === 'pass') {
            return ThesisSubmissionStatus::SemproSelesai->value;
        }

        return $project->legacySubmission?->status ?? ThesisSubmissionStatus::MenungguPersetujuan->value;
    }
}
