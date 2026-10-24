<?php

use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\MentorshipTicketService;
use App\Services\UserProfilePresenter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(now()->setTime(12, 0));
});

/**
 * One student, two active supervisors, one kaprodi of the same prodi.
 *
 * @return array{prodi: ProgramStudi, student: User, project: ThesisProject, primary: User, secondary: User, kaprodi: User}
 */
function activitySurfaceFixture(): array
{
    $prodi = ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Aktivitas']);
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $prodi->id, 'is_active' => true]);
    $primary = User::factory()->asDosen()->create();
    $secondary = User::factory()->asDosen()->create();
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $kaprodi->id, 'program_studi_id' => $prodi->id]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(90),
        'created_by' => $student->id,
    ]);
    foreach (['primary' => $primary, 'secondary' => $secondary] as $role => $lecturer) {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => $role,
            'status' => 'active',
            'assigned_by' => $kaprodi->id,
            'started_at' => now()->subDays(80),
        ]);
    }

    return compact('prodi', 'student', 'project', 'primary', 'secondary', 'kaprodi');
}

it('shows one last activity value on the dosen list, kaprodi list, profile card and progress page', function (): void {
    $fixture = activitySurfaceFixture();
    ['student' => $student, 'primary' => $primary, 'secondary' => $secondary, 'kaprodi' => $kaprodi] = $fixture;

    // A document with the secondary supervisor, then a newer student chat in the shared group thread.
    MentorshipDocument::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $secondary->id,
        'uploaded_by_user_id' => $student->id,
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $student->id,
        'message_type' => 'text',
        'sent_at' => now()->subDays(3),
    ]);
    $expected = \App\Support\WitaDateTime::format(now()->subDays(3), 'd M Y', false);

    $this->actingAs($primary)->get('/dosen/mahasiswa-bimbingan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mahasiswaRows.0.progress.detail.lastActivityAt', $expected)
            ->where('mahasiswaRows.0.lastUpdate', now()->subDays(3)->locale('id')->diffForHumans()));

    $this->actingAs($kaprodi)->get('/kaprodi/mahasiswa')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.0.progress.detail.lastActivityAt', $expected)
            ->where('students.0.progressRisk.lastActivityLabel', 'Aktivitas mahasiswa'));

    $this->actingAs($kaprodi)->get('/progres-mahasiswa')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.progress.detail.lastActivityAt', $expected));

    $card = app(UserProfilePresenter::class)->detail($primary, $kaprodi)['relatedUsers'][0]['users'][0];
    expect($card['progress']['detail']['lastActivityAt'])->toBe($expected);
});

it('counts activity with the other supervisor of the same project on the dosen list', function (): void {
    ['student' => $student, 'primary' => $primary, 'secondary' => $secondary] = activitySurfaceFixture();

    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private']);
    $thread->participants()->createMany([['user_id' => $student->id], ['user_id' => $secondary->id]]);
    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $student->id,
        'message_type' => 'text',
        'sent_at' => now()->subDays(2),
    ]);

    $this->actingAs($primary)->get('/dosen/mahasiswa-bimbingan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mahasiswaRows.0.progress.detail.lastActivityAt', \App\Support\WitaDateTime::format(now()->subDays(2), 'd M Y', false))
            ->where('mahasiswaRows.0.lastUpdate', now()->subDays(2)->locale('id')->diffForHumans()));
});

it('ignores a chat with a lecturer who does not supervise the project', function (): void {
    ['student' => $student, 'primary' => $primary] = activitySurfaceFixture();
    $stranger = User::factory()->asDosen()->create();

    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'private']);
    $thread->participants()->createMany([['user_id' => $student->id], ['user_id' => $stranger->id]]);
    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $student->id,
        'message_type' => 'text',
        'sent_at' => now()->subDay(),
    ]);

    $this->actingAs($primary)->get('/dosen/mahasiswa-bimbingan')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mahasiswaRows.0.progress.detail.lastActivityAt', null)
            ->where('mahasiswaRows.0.lastUpdate', 'Belum ada aktivitas'));
});

it('lists a student once on the progress page and gates the ticket on the current project', function (): void {
    ['student' => $student, 'project' => $older, 'prodi' => $prodi, 'kaprodi' => $kaprodi] = activitySurfaceFixture();
    $older->forceFill(['mentorship_valid_until' => now()->subMonth()->toDateString()])->save();

    // A newer submission supersedes the older one even though the older one is still "active".
    $current = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subDays(5),
        'mentorship_valid_until' => now()->addMonths(6)->toDateString(),
        'created_by' => $student->id,
    ]);

    $this->actingAs($kaprodi)->get('/progres-mahasiswa')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('rows.0.projectId', $current->id));

    $tickets = app(MentorshipTicketService::class);
    expect($tickets->activeProjectForStudent($student->id)?->id)->toBe($current->id)
        ->and($tickets->suspensionMessageForStudent($student->id))->toBeNull();
});
