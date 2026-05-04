<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('mahasiswa dashboard uses real thesis and upcoming activity data', function () {
    $programStudi = ProgramStudi::factory()->create([
        'name' => 'Ilmu Komputer',
        'slug' => 'ilkom',
        'concentrations' => ['Jaringan', 'Sistem Cerdas', 'Computer Vision'],
    ]);

    $student = User::factory()->asMahasiswa()->create([
        'name' => 'Mahasiswa Dashboard',
    ]);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Jaringan',
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $advisor = User::factory()->asDosen()->create([
        'name' => 'Dr. Budi Santoso, M.Kom.',
    ]);
    DosenProfile::factory()->create([
        'user_id' => $advisor->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 10,
        'is_active' => true,
    ]);

    $examiner = User::factory()->asDosen()->create([
        'name' => 'Dr. Ratna Kusuma, M.Kom.',
    ]);
    DosenProfile::factory()->create([
        'user_id' => $examiner->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 10,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(20),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Dashboard Skripsi Berbasis Data Nyata',
        'title_en' => 'Real Data Thesis Dashboard',
        'proposal_summary' => 'Ringkasan proposal dashboard.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(19),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $advisor->id,
        'role' => 'primary',
        'status' => 'active',
        'started_at' => now()->subDays(18),
    ]);

    MentorshipSchedule::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $advisor->id,
        'topic' => 'Review proposal terbaru',
        'status' => 'approved',
        'requested_for' => now()->addDays(1),
        'scheduled_for' => now()->addDays(1)->setTime(9, 0),
        'location' => 'Ruang Dosen 2',
        'created_by_user_id' => $student->id,
    ]);

    $sempro = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3)->setTime(13, 0),
        'location' => 'Ruang Seminar A',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $sempro->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pending',
    ]);

    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
        'label' => 'Bimbingan Utama',
    ]);

    MentorshipChatMessage::query()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $advisor->id,
        'message_type' => 'text',
        'message' => 'Silakan cek revisi proposal sebelum bimbingan berikutnya.',
        'sent_at' => now()->subHour(),
    ]);

    $this->actingAs($student)
        ->get('/mahasiswa/dashboard')
        ->assertInertia(fn(Assert $page) => $page
            ->component('dashboard')
            ->where('summary.projectTitle', 'Dashboard Skripsi Berbasis Data Nyata')
            ->where('summary.workflow.key', 'sempro_scheduled')
            ->where('summary.advisors.0.name', 'Dr. Budi Santoso, M.Kom.')
            ->where('stats.2.value', '2')
            ->where('stats.3.value', '1')
            ->where('quickActionState.canScheduleMeeting', true)
            ->where('quickActionState.canUploadDocument', true)
            ->where('upcomingActivities.0.badge', 'Bimbingan')
            ->where('upcomingActivities.0.title', 'Review proposal terbaru')
            ->where('upcomingActivities.1.badge', 'Sempro')
            ->where('timeline.1.status', 'current'));
});
