<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatThread;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\User;
use App\Services\MentorshipTicketService;
use App\Services\ThesisProjectAdminService;
use App\Support\WitaDateTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

dataset('bimbingan entry points', [
    'jadwal mahasiswa' => ['jadwal mahasiswa', 'mentorship_schedules'],
    'upload mahasiswa' => ['upload mahasiswa', 'mentorship_documents'],
    'pesan mahasiswa' => ['pesan mahasiswa', 'mentorship_chat_messages'],
    'jadwal dosen' => ['jadwal dosen', 'mentorship_schedules'],
]);

dataset('mentorship ticket validity', [
    'habis' => [now()->subDay()->toDateString(), true],
    'berlaku' => [now()->addMonth()->toDateString(), false],
    'null' => [null, false],
]);

/**
 * @return array{student: User, lecturer: User, admin: User, project: ThesisProject, thread: MentorshipChatThread}
 */
function mentorshipTicketFixture(?string $validUntil): array
{
    Notification::fake();

    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    createActiveMentorshipAssignment($student, $lecturer, $admin, AdvisorType::Primary);
    syncThesisSupervisor($student, $lecturer, $admin, AdvisorType::Primary->value);

    $project = ThesisProject::query()->where('student_user_id', $student->id)->firstOrFail();
    $project->forceFill([
        'mentorship_valid_from' => now()->subMonth()->toDateString(),
        'mentorship_valid_until' => $validUntil,
    ])->save();

    $thread = MentorshipChatThread::query()->create([
        'student_user_id' => $student->id,
        'type' => 'pembimbing',
    ]);

    return [
        'student' => $student,
        'lecturer' => $lecturer,
        'admin' => $admin,
        'project' => $project->fresh(),
        'thread' => $thread,
    ];
}

test('bimbingan entry points respect mentorship ticket validity', function (string $entry, string $table, ?string $validUntil, bool $blocked) {
    ['student' => $student, 'lecturer' => $lecturer, 'thread' => $thread] = mentorshipTicketFixture($validUntil);

    $response = match ($entry) {
        'jadwal mahasiswa' => $this->actingAs($student)->post('/mahasiswa/jadwal-bimbingan', [
            'topic' => 'Review Bab 3',
            'lecturer_user_id' => $lecturer->id,
            'requested_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'meeting_type' => 'online',
            'student_note' => 'Mohon jadwalkan siang hari.',
        ]),
        'upload mahasiswa' => $this->actingAs($student)->post('/mahasiswa/upload-dokumen', [
            'title' => 'Draft Bab 3',
            'category' => 'draft-tugas-akhir',
            'document' => UploadedFile::fake()->create('draft-bab3.pdf', 600, 'application/pdf'),
        ]),
        'pesan mahasiswa' => $this->actingAs($student)->postJson("/mahasiswa/pesan/{$thread->id}/messages", ['message' => 'Mohon bimbingan.', 'client_message_id' => (string) \Illuminate\Support\Str::uuid()]),
        'jadwal dosen' => $this->actingAs($lecturer)->post('/dosen/jadwal-bimbingan', [
            'student_user_id' => $student->id,
            'topic' => 'Review Bab 4',
            'requested_for' => now()->addDay()->format('Y-m-d\TH:i'),
            'location' => 'Ruang Dosen 3',
            'lecturer_note' => 'Bawa draft terbaru.',
            'is_recurring' => false,
        ]),
    };

    if ($blocked) {
        if ($entry === 'pesan mahasiswa') {
            $response->assertUnprocessable()->assertJsonValidationErrors('ticket');
        } else {
            $response->assertSessionHasErrors('ticket');
        }
        $this->assertDatabaseCount($table, 0);
    } else {
        if ($entry === 'pesan mahasiswa') {
            $response->assertCreated();
        } else {
            $response->assertSessionDoesntHaveErrors('ticket');
        }
        expect(DB::table($table)->count())->toBeGreaterThan(0);
    }
})->with('bimbingan entry points')->with('mentorship ticket validity');

test('expired mentorship ticket still allows reading bimbingan pages', function () {
    ['student' => $student] = mentorshipTicketFixture(now()->subDay()->toDateString());

    $this->actingAs($student)
        ->get('/mahasiswa/jadwal-bimbingan')
        ->assertOk();
});

test('renewing an expired ticket extends from today and logs an event', function () {
    $fixture = mentorshipTicketFixture(now()->subDay()->toDateString());

    $project = app(ThesisProjectAdminService::class)->renewMentorshipTicket($fixture['project'], $fixture['admin']->id);

    $expected = Carbon::today(WitaDateTime::TIMEZONE)
        ->addMonthsNoOverflow(config('sita.thesis_ticket_months', 12))
        ->toDateString();

    expect($project->mentorship_valid_until?->toDateString())->toBe($expected)
        ->and($project->isMentorshipSuspended())->toBeFalse();

    $event = ThesisProjectEvent::query()
        ->where('project_id', $project->id)
        ->where('event_type', 'mentorship_ticket_renewed')
        ->sole();

    expect($event->actor_user_id)->toBe($fixture['admin']->id)
        ->and($event->payload['new_valid_until'])->toBe($expected);
});

test('renewing an active ticket extends from its current expiry', function () {
    $validUntil = now()->addMonth()->toDateString();
    $fixture = mentorshipTicketFixture($validUntil);

    $project = app(ThesisProjectAdminService::class)->renewMentorshipTicket($fixture['project'], $fixture['admin']->id);

    expect($project->mentorship_valid_until?->toDateString())->toBe(
        Carbon::parse($validUntil)
            ->addMonthsNoOverflow(config('sita.thesis_ticket_months', 12))
            ->toDateString(),
    );
});

test('bulk renewal restores every selected ticket and logs an event for each', function () {
    $first = mentorshipTicketFixture(now()->subDay()->toDateString());
    $second = mentorshipTicketFixture(now()->subWeek()->toDateString());

    $count = app(ThesisProjectAdminService::class)->renewMentorshipTickets(
        collect([$first['project'], $second['project']]),
        $first['admin']->id,
    );

    expect($count)->toBe(2);

    foreach ([$first['project'], $second['project']] as $project) {
        expect($project->fresh()->isMentorshipSuspended())->toBeFalse();
        $this->assertDatabaseHas('thesis_project_events', [
            'project_id' => $project->id,
            'event_type' => 'mentorship_ticket_renewed',
        ]);
    }
});

test('assigning supervisors starts a one year ticket when none is set', function () {
    Notification::fake();

    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    ensureLecturerTeachesStudent($lecturer, $student);
    $project = ensureActiveThesisProject($student);
    $project->forceFill([
        'mentorship_valid_from' => null,
        'mentorship_valid_until' => null,
    ])->save();

    app(ThesisProjectAdminService::class)->assignSupervisors($project, $admin->id, $lecturer->id, null, null);

    $project->refresh();
    $today = Carbon::today(WitaDateTime::TIMEZONE);

    expect($project->mentorship_valid_from?->toDateString())->toBe($today->toDateString())
        ->and($project->mentorship_valid_until?->toDateString())->toBe(
            $today->copy()->addMonthsNoOverflow(config('sita.thesis_ticket_months', 12))->toDateString(),
        );
});

test('the expired banner message names the masa aktif bimbingan, the date and the next step', function () {
    $fixture = mentorshipTicketFixture('2026-10-23');

    $forStudent = MentorshipTicketService::suspensionMessage($fixture['project']);
    $forDosen = MentorshipTicketService::suspensionMessage($fixture['project'], addressStudent: false);

    expect($forStudent)
        ->toContain('Masa aktif bimbingan Anda berakhir pada 23 Oktober 2026')
        ->toContain('Hubungi admin program studi')
        ->not->toContain('iket')
        ->and($forDosen)->toContain('Masa aktif bimbingan mahasiswa berakhir pada 23 Oktober 2026')
        ->not->toContain('Anda');
});
