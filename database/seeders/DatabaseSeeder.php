<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rolesByName = collect(AppRole::values())
            ->mapWithKeys(fn (string $role): array => [
                $role => Role::query()->firstOrCreate(['name' => $role]),
            ]);

        $mahasiswa = User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Mahasiswa->value,
        ]);
        $mahasiswa->roles()->syncWithoutDetaching([
            $rolesByName[AppRole::Mahasiswa->value]->id,
        ]);
        MahasiswaProfile::query()->updateOrCreate(
            ['user_id' => $mahasiswa->id],
            [
                'nim' => '2210510999',
                'program_studi' => 'Informatika',
                'angkatan' => 2022,
                'status_akademik' => 'aktif',
            ],
        );

        $admin = User::query()->updateOrCreate([
            'email' => 'admin@sita.test',
        ], [
            'name' => 'Admin SiTA',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Admin->value,
        ]);
        $admin->roles()->syncWithoutDetaching([
            $rolesByName[AppRole::Admin->value]->id,
        ]);

        $dosen = User::query()->updateOrCreate([
            'email' => 'dosen@sita.test',
        ], [
            'name' => 'Dr. Budi Santoso, M.Kom.',
            'password' => Hash::make('password'),
            'last_active_role' => AppRole::Dosen->value,
        ]);
        $dosen->roles()->syncWithoutDetaching([
            $rolesByName[AppRole::Dosen->value]->id,
        ]);
        DosenProfile::query()->updateOrCreate(
            ['user_id' => $dosen->id],
            [
                'nidn' => '1234567890',
                'homebase' => 'Informatika',
                'is_active' => true,
            ],
        );

        $students = [
            [
                'email' => 'akbar@sita.test',
                'name' => 'Muhammad Akbar',
                'nim' => '2210510001',
                'status' => 'aktif',
            ],
            [
                'email' => 'nadia@sita.test',
                'name' => 'Nadia Putri',
                'nim' => '2210510020',
                'status' => 'aktif',
            ],
            [
                'email' => 'rizky@sita.test',
                'name' => 'Rizky Pratama',
                'nim' => '2210510011',
                'status' => 'aktif',
            ],
        ];

        foreach ($students as $studentData) {
            $student = User::query()->updateOrCreate(
                ['email' => $studentData['email']],
                [
                    'name' => $studentData['name'],
                    'password' => Hash::make('password'),
                    'last_active_role' => AppRole::Mahasiswa->value,
                ],
            );

            $student->roles()->syncWithoutDetaching([
                $rolesByName[AppRole::Mahasiswa->value]->id,
            ]);

            MahasiswaProfile::query()->updateOrCreate(
                ['user_id' => $student->id],
                [
                    'nim' => $studentData['nim'],
                    'program_studi' => 'Informatika',
                    'angkatan' => 2022,
                    'status_akademik' => $studentData['status'],
                ],
            );

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

            MentorshipSchedule::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $dosen->id,
                    'topic' => 'Review Bab III Metodologi',
                    'status' => 'pending',
                ],
                [
                    'mentorship_assignment_id' => $assignment->id,
                    'requested_for' => now()->addDay(),
                    'scheduled_for' => null,
                    'location' => null,
                    'student_note' => 'Mohon review bagian metodologi.',
                    'lecturer_note' => null,
                    'created_by_user_id' => $student->id,
                ],
            );

            $approvedSchedule = MentorshipSchedule::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $dosen->id,
                    'topic' => 'Diskusi Revisi Draft',
                    'status' => 'approved',
                ],
                [
                    'mentorship_assignment_id' => $assignment->id,
                    'requested_for' => now()->addDays(2),
                    'scheduled_for' => now()->addDays(2)->setTime(10, 0),
                    'location' => 'Google Meet',
                    'student_note' => null,
                    'lecturer_note' => 'Bahas hasil revisi terbaru.',
                    'created_by_user_id' => $student->id,
                ],
            );

            $document = MentorshipDocument::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $dosen->id,
                    'file_name' => sprintf('draft_%s_v3.pdf', strtolower(str_replace(' ', '_', $studentData['name']))),
                ],
                [
                    'mentorship_assignment_id' => $assignment->id,
                    'title' => 'Draft Skripsi',
                    'file_url' => '/storage/demo/draft.pdf',
                    'file_size_kb' => 850,
                    'status' => 'submitted',
                    'revision_notes' => null,
                    'reviewed_at' => null,
                    'uploaded_by_user_id' => $student->id,
                ],
            );

            $thread = MentorshipChatThread::query()->updateOrCreate(
                ['student_user_id' => $student->id],
                [
                    'is_escalated' => false,
                    'escalated_at' => null,
                ],
            );

            MentorshipChatMessage::query()->updateOrCreate(
                [
                    'mentorship_chat_thread_id' => $thread->id,
                    'message_type' => 'document_event',
                    'related_document_id' => $document->id,
                ],
                [
                    'sender_user_id' => null,
                    'message' => 'Mahasiswa mengunggah dokumen baru untuk direview.',
                    'sent_at' => $document->created_at,
                ],
            );

            MentorshipChatMessage::query()->updateOrCreate(
                [
                    'mentorship_chat_thread_id' => $thread->id,
                    'sender_user_id' => $student->id,
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
                    'mentorship_chat_thread_id' => $thread->id,
                    'sender_user_id' => $dosen->id,
                    'message' => 'Baik, saya review sore ini.',
                ],
                [
                    'related_document_id' => null,
                    'message_type' => 'text',
                    'sent_at' => now()->subHour(),
                ],
            );

            // Keep one pending item fresh for dashboard queue.
            $approvedSchedule->touch();
        }
    }
}
