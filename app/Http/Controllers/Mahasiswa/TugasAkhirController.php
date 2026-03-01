<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\ThesisSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TugasAkhirController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $submission = ThesisSubmission::query()
            ->where('student_user_id', $student->id)
            ->with([
                'sempros' => fn($query) => $query
                    ->latest('created_at')
                    ->limit(1),
                'sempros.examiners' => fn($query) => $query
                    ->with('examiner:id,name')
                    ->orderBy('examiner_order'),
            ])
            ->latest()
            ->first();

        $assignments = MentorshipAssignment::query()
            ->with('lecturer:id,name')
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get();

        $latestSempro = $submission?->sempros->first();
        $examinerOne = $latestSempro?->examiners->firstWhere('examiner_order', 1);
        $examinerTwo = $latestSempro?->examiners->firstWhere('examiner_order', 2);

        $primaryAdvisor = $assignments->firstWhere('advisor_type', AdvisorType::Primary->value);
        $secondaryAdvisor = $assignments->firstWhere('advisor_type', AdvisorType::Secondary->value);

        return Inertia::render('tugas-akhir', [
            'submission' => $submission === null ? null : [
                'id' => $submission->id,
                'program_studi' => $submission->program_studi,
                'title_id' => $submission->title_id,
                'title_en' => $submission->title_en,
                'proposal_summary' => $submission->proposal_summary,
                'status' => $submission->status,
                'proposal_file_name' => $submission->proposal_file_path === null
                    ? null
                    : basename($submission->proposal_file_path),
                'proposal_file_view_url' => $submission->proposal_file_path === null
                    ? null
                    : route('files.thesis-proposals', [
                        'submission' => $submission->id,
                        'inline' => 1,
                    ]),
                'proposal_file_download_url' => $submission->proposal_file_path === null
                    ? null
                    : route('files.thesis-proposals', [
                        'submission' => $submission->id,
                    ]),
            ],
            'assignedLecturers' => [
                'pembimbing1' => $primaryAdvisor?->lecturer?->name,
                'pembimbing2' => $secondaryAdvisor?->lecturer?->name,
                'penguji1' => $examinerOne?->examiner?->name,
                'penguji2' => $examinerTwo?->examiner?->name,
            ],
            'semproDate' => $latestSempro?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
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

        $exists = ThesisSubmission::query()
            ->where('student_user_id', $student->id)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Anda sudah memiliki pengajuan Judul & Proposal aktif.');
        }

        $path = $request->file('proposal_file')->store('proposal_files', 'public');

        ThesisSubmission::create([
            'student_user_id' => $student->id,
            'program_studi' => $this->resolveProgramStudiForStudent($student),
            'title_id' => $validated['title_id'],
            'title_en' => $validated['title_en'] ?? '-',
            'proposal_summary' => $validated['proposal_summary'],
            'proposal_file_path' => $path,
            'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
            'is_active' => true,
            'submitted_at' => now(),
        ]);

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

        $payload = [
            'program_studi' => $this->resolveProgramStudiForStudent($student, $submission->program_studi),
            'title_id' => $validated['title_id'],
            'title_en' => $validated['title_en'] ?? '-',
            'proposal_summary' => $validated['proposal_summary'],
        ];

        if ($request->hasFile('proposal_file')) {
            $oldPath = $submission->proposal_file_path;
            $payload['proposal_file_path'] = $request->file('proposal_file')->store('proposal_files', 'public');

            if ($oldPath !== null && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $submission->update($payload);

        return back()->with('success', 'Pengajuan Judul & Proposal berhasil diperbarui.');
    }

    private function resolveProgramStudiForStudent(User $student, ?string $fallback = null): string
    {
        $programStudi = $student->mahasiswaProfile?->program_studi;
        if (is_string($programStudi) && trim($programStudi) !== '') {
            return trim($programStudi);
        }

        return $fallback ?? '-';
    }
}
