<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const MAHASISWA_EMAILS = [
        'mahasiswa@sita.test',
        'akbar@sita.test',
        'nadia@sita.test',
        'rizky@sita.test',
    ];

    public function run(): void
    {
        $this->call([UserSeeder::class]);

        $admin = User::query()->where('email', 'admin@sita.test')->first();
        $dosen = User::query()->where('email', 'dosen@sita.test')->first();

        if ($admin === null || $dosen === null) {
            return;
        }

        foreach (self::MAHASISWA_EMAILS as $index => $email) {
            $student = User::query()->where('email', $email)->first();
            if ($student === null) {
                continue;
            }

            $assignment = MentorshipAssignment::query()->firstOrCreate([
                'student_user_id' => $student->id,
                'lecturer_user_id' => $dosen->id,
                'advisor_type' => 'primary',
                'status' => AssignmentStatus::Active->value,
            ], [
                'assigned_by' => $admin->id,
                'started_at' => now()->subMonths(2),
                'notes' => 'Assignment seeded',
            ]);

            $this->seedSchedules($assignment->id, $student->id, $dosen->id, $index);
            $document = $this->seedDocument($assignment->id, $student->id, $dosen->id, $email);
            $thread = $this->seedThread($student->id);
            $this->seedThreadMessages($thread->id, $student->id, $dosen->id, $document);
        }
    }

    private function seedSchedules(int $assignmentId, int $studentId, int $dosenId, int $index): void
    {
        MentorshipSchedule::query()->updateOrCreate(
            [
                'student_user_id' => $studentId,
                'lecturer_user_id' => $dosenId,
                'topic' => 'Review Bab III Metodologi',
                'status' => 'pending',
            ],
            [
                'mentorship_assignment_id' => $assignmentId,
                'requested_for' => now()->addDays($index + 1),
                'scheduled_for' => null,
                'location' => null,
                'student_note' => 'Mohon review bagian metodologi.',
                'lecturer_note' => null,
                'created_by_user_id' => $studentId,
            ],
        );

        $approvedSchedule = MentorshipSchedule::query()->updateOrCreate(
            [
                'student_user_id' => $studentId,
                'lecturer_user_id' => $dosenId,
                'topic' => 'Diskusi Revisi Draft',
                'status' => 'approved',
            ],
            [
                'mentorship_assignment_id' => $assignmentId,
                'requested_for' => now()->addDays($index + 2),
                'scheduled_for' => now()->addDays($index + 2)->setTime(10, 0),
                'location' => 'Google Meet',
                'student_note' => null,
                'lecturer_note' => 'Bahas hasil revisi terbaru.',
                'created_by_user_id' => $studentId,
            ],
        );

        $approvedSchedule->touch();
    }

    private function seedDocument(int $assignmentId, int $studentId, int $dosenId, string $email): MentorshipDocument
    {
        $emailHandle = Str::before($email, '@');
        $documentGroup = sprintf('%d:%s', $studentId, 'draft-tugas-akhir');

        return MentorshipDocument::query()->updateOrCreate(
            [
                'mentorship_assignment_id' => $assignmentId,
                'document_group' => $documentGroup,
                'version_number' => 1,
                'uploaded_by_role' => 'mahasiswa',
            ],
            [
                'student_user_id' => $studentId,
                'lecturer_user_id' => $dosenId,
                'title' => 'Draft Skripsi',
                'category' => 'draft-tugas-akhir',
                'file_name' => sprintf('draft_%s_v1.pdf', $emailHandle),
                'file_url' => '/storage/demo/draft.pdf',
                'storage_disk' => null,
                'storage_path' => null,
                'mime_type' => 'application/pdf',
                'file_size_kb' => 850,
                'status' => 'submitted',
                'revision_notes' => null,
                'reviewed_at' => null,
                'uploaded_by_user_id' => $studentId,
            ],
        );
    }

    private function seedThread(int $studentId): MentorshipChatThread
    {
        return MentorshipChatThread::query()->updateOrCreate(
            ['student_user_id' => $studentId],
            [
                'is_escalated' => false,
                'escalated_at' => null,
            ],
        );
    }

    private function seedThreadMessages(
        int $threadId,
        int $studentId,
        int $dosenId,
        MentorshipDocument $document,
    ): void {
        MentorshipChatMessage::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $threadId,
                'message_type' => 'document_event',
                'related_document_id' => $document->id,
            ],
            [
                'sender_user_id' => null,
                'message' => 'Mahasiswa mengunggah dokumen baru untuk direview.',
                'attachment_disk' => null,
                'attachment_path' => null,
                'attachment_name' => $document->file_name,
                'attachment_mime' => 'application/pdf',
                'attachment_size_kb' => $document->file_size_kb,
                'sent_at' => $document->created_at,
            ],
        );

        MentorshipChatMessage::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $threadId,
                'sender_user_id' => $studentId,
                'message' => 'Dokumen revisi sudah saya unggah, mohon ditinjau.',
            ],
            [
                'related_document_id' => $document->id,
                'message_type' => 'text',
                'sent_at' => now()->subHours(2),
            ],
        );

        MentorshipChatMessage::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $threadId,
                'sender_user_id' => $dosenId,
                'message' => 'Baik, saya review sore ini.',
            ],
            [
                'related_document_id' => null,
                'message_type' => 'text',
                'sent_at' => now()->subHour(),
            ],
        );
    }
}
