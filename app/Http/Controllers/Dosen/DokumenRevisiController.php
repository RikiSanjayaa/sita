<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\MentorshipDocument;
use App\Services\DosenBimbinganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenRevisiController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);

        $documentQueue = MentorshipDocument::query()
            ->with('student')
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (MentorshipDocument $document): array {
                return [
                    'id' => $document->id,
                    'mahasiswa' => $document->student?->name ?? '-',
                    'title' => $document->title,
                    'file' => $document->file_name,
                    'uploadedAt' => $document->created_at->format('d M Y H:i'),
                    'status' => match ($document->status) {
                        'approved' => 'Disetujui',
                        'needs_revision' => 'Perlu Revisi',
                        default => 'Perlu Review',
                    },
                    'revisionNotes' => $document->revision_notes,
                    'fileUrl' => $document->file_url,
                ];
            })
            ->all();

        return Inertia::render('dosen/dokumen-revisi', [
            'documentQueue' => $documentQueue,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function review(Request $request, MentorshipDocument $document): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);
        abort_unless($document->lecturer_user_id === $lecturer->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:needs_revision,approved'],
            'revision_notes' => ['nullable', 'string'],
        ]);

        $document->forceFill([
            'status' => $data['status'],
            'revision_notes' => $data['revision_notes'] ?? null,
            'reviewed_at' => now(),
        ])->save();

        return back()->with('success', 'Status dokumen berhasil diperbarui.');
    }
}
