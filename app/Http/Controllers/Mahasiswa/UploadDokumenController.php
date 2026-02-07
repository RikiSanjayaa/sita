<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AssignmentStatus;
use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UploadDokumenController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $documents = MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('uploaded_by_role', 'mahasiswa')
            ->latest('created_at')
            ->get()
            ->groupBy(fn (MentorshipDocument $document): string => sprintf('%s:%d', (string) $document->document_group, $document->version_number))
            ->map(function ($versions): array {
                /** @var MentorshipDocument $document */
                $document = $versions->first();

                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category ?? '-',
                    'version' => sprintf('v%d', $document->version_number),
                    'uploadedAt' => $document->created_at->format('d M Y H:i'),
                    'fileName' => $document->file_name,
                    'size' => sprintf('%d KB', (int) $document->file_size_kb),
                    'status' => match ($document->status) {
                        'approved' => 'Disetujui',
                        'needs_revision' => 'Perlu Revisi',
                        default => 'Menunggu Review',
                    },
                    'downloadUrl' => route('files.documents.download', ['document' => $document->id]),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('upload-dokumen', [
            'uploadedDocuments' => $documents,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $assignments = MentorshipAssignment::query()
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get();

        if ($assignments->isEmpty()) {
            return back()->withErrors([
                'document' => 'Belum ada dosen pembimbing aktif untuk menerima dokumen.',
            ]);
        }

        $file = $data['document'];
        $disk = 'public';
        $storedPath = $file->store(sprintf('documents/mahasiswa/%d', $student->id), $disk);

        $groupKey = sprintf('%d:%s', $student->id, strtolower(trim($data['category'])));
        $nextVersion = ((int) MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('document_group', $groupKey)
            ->max('version_number')) + 1;

        $createdDocuments = collect();

        DB::transaction(function () use (
            $assignments,
            $student,
            $data,
            $disk,
            $storedPath,
            $file,
            $groupKey,
            $nextVersion,
            &$createdDocuments
        ): void {
            foreach ($assignments as $assignment) {
                $createdDocuments->push(MentorshipDocument::query()->create([
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $assignment->lecturer_user_id,
                    'mentorship_assignment_id' => $assignment->id,
                    'title' => trim($data['title']),
                    'category' => trim($data['category']),
                    'document_group' => $groupKey,
                    'version_number' => $nextVersion,
                    'file_name' => $file->getClientOriginalName(),
                    'file_url' => null,
                    'storage_disk' => $disk,
                    'storage_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType(),
                    'file_size_kb' => (int) ceil($file->getSize() / 1024),
                    'status' => 'submitted',
                    'revision_notes' => null,
                    'reviewed_at' => null,
                    'uploaded_by_user_id' => $student->id,
                    'uploaded_by_role' => 'mahasiswa',
                ]));
            }

            $thread = MentorshipChatThread::query()->firstOrCreate([
                'student_user_id' => $student->id,
            ]);

            $message = $thread->messages()->create([
                'sender_user_id' => $student->id,
                'related_document_id' => $createdDocuments->first()?->id,
                'attachment_disk' => $disk,
                'attachment_path' => $storedPath,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_mime' => $file->getClientMimeType(),
                'attachment_size_kb' => (int) ceil($file->getSize() / 1024),
                'message_type' => 'document_event',
                'message' => sprintf(
                    'Mahasiswa mengunggah dokumen %s versi v%d.',
                    trim($data['category']),
                    $nextVersion,
                ),
                'sent_at' => now(),
            ]);

            broadcast(new ChatMessageCreated(
                threadId: $thread->id,
                messagePayload: $this->mapMessagePayload($message),
            ))->toOthers();
        });

        return redirect()
            ->route('mahasiswa.upload-dokumen')
            ->with('success', 'Dokumen berhasil diunggah dan notifikasi terkirim ke grup bimbingan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMessagePayload($message): array
    {
        return [
            'id' => $message->id,
            'author' => $message->sender?->name ?? 'Sistem',
            'message' => $message->message,
            'time' => $message->created_at->format('d M Y H:i'),
            'type' => $message->message_type,
            'documentName' => $message->relatedDocument?->file_name ?? $message->attachment_name,
            'documentUrl' => $message->attachment_path === null
                ? ($message->related_document_id === null
                    ? null
                    : route('files.documents.download', ['document' => $message->related_document_id]))
                : route('files.chat-attachments.download', ['message' => $message->id]),
        ];
    }

    public function destroy(Request $request, MentorshipDocument $document): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);
        abort_unless($document->student_user_id === $student->id, 403);

        try {
            if ($document->storage_path !== null && $document->storage_disk !== null) {
                Storage::disk($document->storage_disk)->delete($document->storage_path);
            }
        } catch (Throwable) {
            // noop: keep metadata removal resilient when file already missing.
        }

        MentorshipDocument::query()
            ->where('student_user_id', $student->id)
            ->where('document_group', $document->document_group)
            ->where('version_number', $document->version_number)
            ->delete();

        return redirect()
            ->route('mahasiswa.upload-dokumen')
            ->with('success', 'Dokumen berhasil dihapus.');
    }
}
