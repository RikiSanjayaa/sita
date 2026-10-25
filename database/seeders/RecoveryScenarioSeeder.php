<?php

namespace Database\Seeders;

use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use App\Services\ThesisProjectAdminService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class RecoveryScenarioSeeder extends Seeder
{
    /**
     * Initial conditions only. Recovery transitions themselves belong to the UI spec.
     * Reuses the rehearsal identity and its owned-data cleanup, never shared students.
     */
    public function run(string $scenario = 'fresh'): void
    {
        $phase = match ($scenario) {
            'fresh' => null,
            'sempro' => 'sempro',
            'research', 'sidang' => 'research',
        };
        $this->call(GoldenPathScenarioSeeder::class);

        if ($phase === null) {
            return;
        }

        $student = User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->firstOrFail();
        $admin = User::query()->where('email', 'admin@sita.test')->firstOrFail();
        $lecturers = collect(['dosen', 'dosen2', 'dosen3', 'dosen4', 'dosen5'])
            ->map(fn (string $handle): User => User::query()->where('email', $handle.'@sita.test')->firstOrFail());
        [$budi, $ratna, $ahmad, $dewi, $fajar] = $lecturers->all();
        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $student->mahasiswaProfile->program_studi_id,
            'phase' => $phase,
            'state' => 'active',
            'started_at' => now()->subMonths(2),
            'created_by' => $student->id,
        ]);
        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => 'Pelacakan Riset untuk Pemulihan Alur Skripsi',
            'title_en' => 'Research Tracking for Thesis Recovery',
            'proposal_summary' => 'Naskah proposal skenario pemulihan lintas peran.',
            'discussion_lecturer_user_id' => $budi->id,
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => now()->subMonths(2),
            'decided_by_user_id' => $admin->id,
            'decided_at' => now()->subMonth(),
        ]);
        $pdf = file_get_contents(base_path('tests/fixtures/panduan-template.pdf'));
        $proposalPath = 'thesis/proposals/recovery-'.$student->id.'.pdf';
        Storage::disk('public')->put($proposalPath, $pdf);
        ThesisDocument::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'uploaded_by_user_id' => $student->id,
            'kind' => 'proposal',
            'version_no' => 1,
            'status' => 'active',
            'title' => 'Naskah Proposal',
            'storage_disk' => 'public',
            'storage_path' => $proposalPath,
            'stored_file_name' => basename($proposalPath),
            'file_name' => 'proposal-pemulihan-awal.pdf',
            'mime_type' => 'application/pdf',
            'file_size_kb' => 1,
            'uploaded_at' => now()->subMonth(),
        ]);
        $service = app(ThesisProjectAdminService::class);

        if ($scenario === 'sempro') {
            $service->scheduleSempro($project, $admin->id, now()->addDays(40)->setTime(9, 0)->toDateTimeString(), 'Ruang Sempro Pemulihan Awal', 'offline', [$ahmad->id, $dewi->id]);

            return;
        }

        $sempro = ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'scheduled_for' => now()->subMonth(),
            'location' => 'Ruang Sempro Pemulihan',
            'mode' => 'offline',
            'created_by' => $admin->id,
            'decided_by' => $admin->id,
            'decision_at' => now()->subMonth(),
        ]);
        foreach ([$ahmad, $dewi] as $index => $examiner) {
            ThesisDefenseExaminer::query()->create([
                'defense_id' => $sempro->id,
                'lecturer_user_id' => $examiner->id,
                'role' => 'examiner',
                'order_no' => $index + 1,
                'decision' => 'pass',
                'decided_at' => now()->subMonth(),
                'assigned_by' => $admin->id,
            ]);
        }
        $service->assignSupervisors($project, $admin->id, $budi->id, $ratna->id, 'Pembimbing skenario pemulihan.');
        $thread = MentorshipChatThread::query()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
        MentorshipChatMessage::query()->create([
            'mentorship_chat_thread_id' => $thread->id,
            'sender_user_id' => $student->id,
            'message_type' => 'text',
            'message' => 'Catatan riset sebelum pemulihan alur.',
            'sent_at' => now()->subDay(),
        ]);
        $draftPath = 'documents/mahasiswa/'.$student->id.'/recovery-draft.pdf';
        Storage::disk('public')->put($draftPath, $pdf);
        MentorshipDocument::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $budi->id,
            'title' => 'Draft Riset Pemulihan',
            'category' => 'draft-tugas-akhir',
            'document_group' => $student->id.':recovery-draft',
            'version_number' => 1,
            'file_name' => 'draft-riset-pemulihan.pdf',
            'storage_disk' => 'public',
            'storage_path' => $draftPath,
            'stored_file_name' => basename($draftPath),
            'mime_type' => 'application/pdf',
            'file_size_kb' => 1,
            'status' => 'approved',
            'uploaded_by_user_id' => $student->id,
            'uploaded_by_role' => 'mahasiswa',
        ]);

        if ($scenario === 'sidang') {
            $service->scheduleSidang($project->fresh(), $admin->id, now()->addDays(45)->setTime(10, 0)->toDateTimeString(), 'Ruang Sidang Pemulihan Awal', 'offline', [$budi->id, $ratna->id, $fajar->id]);
        }
    }
}
