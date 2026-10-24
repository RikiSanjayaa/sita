<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\CompletedChatThreadService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A student supervised by $lecturer, with a bimbingan thread. When $completed the thesis is
 * done five days ago (inside the 30 day grace period) and the assignment has ended.
 *
 * @return array{student: User, project: ThesisProject, thread: MentorshipChatThread}
 */
function graceStudent(User $lecturer, User $admin, bool $completed): array
{
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'is_active' => true]);

    createActiveMentorshipAssignment($student, $lecturer, $admin, AdvisorType::Primary);
    syncThesisSupervisor($student, $lecturer, $admin, AdvisorType::Primary->value);
    $project = ensureActiveThesisProject($student);

    if ($completed) {
        $project->forceFill(['phase' => 'completed', 'state' => 'completed', 'completed_at' => now()->subDays(5)])->save();
        ThesisSupervisorAssignment::query()->where('project_id', $project->id)->update(['status' => 'ended', 'ended_at' => now()->subDays(5)]);
    }

    $thread = MentorshipChatThread::query()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);
    MentorshipChatMessage::factory()->create(['mentorship_chat_thread_id' => $thread->id, 'sender_user_id' => $student->id]);

    return compact('student', 'project', 'thread');
}

it('marks threads of a completed thesis in the dosen payload and leaves the rest alone', function (): void {
    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    ['student' => $done, 'project' => $doneProject] = graceStudent($lecturer, $admin, completed: true);
    ['student' => $busy] = graceStudent($lecturer, $admin, completed: false);

    $title = ThesisProjectTitle::query()->create(['project_id' => $doneProject->id, 'version_no' => 1, 'title_id' => 'Judul', 'status' => 'approved', 'submitted_by_user_id' => $done->id, 'submitted_at' => now()->subMonths(6)]);
    $sidang = ThesisDefense::query()->create(['project_id' => $doneProject->id, 'title_version_id' => $title->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'completed', 'result' => 'pass', 'scheduled_for' => now()->subDays(6)]);
    $sidangThread = MentorshipChatThread::query()->create(['student_user_id' => $done->id, 'type' => 'sidang', 'context_id' => $sidang->id, 'label' => 'Sidang']);
    MentorshipChatThreadParticipant::query()->create(['thread_id' => $sidangThread->id, 'user_id' => $lecturer->id, 'role' => 'examiner']);

    $privateThread = MentorshipChatThread::query()->create(['student_user_id' => $done->id, 'type' => 'private', 'private_key' => $done->id.':'.$lecturer->id]);
    foreach ([[$done, 'student'], [$lecturer, 'lecturer']] as [$user, $role]) {
        MentorshipChatThreadParticipant::query()->create(['thread_id' => $privateThread->id, 'user_id' => $user->id, 'role' => $role]);
    }

    $this->actingAs($lecturer)
        ->get(route('dosen.pesan'))
        ->assertInertia(function (Assert $page) use ($done, $busy, $sidangThread, $privateThread): void {
            $threads = collect($page->toArray()['props']['threads']);
            $completedOf = fn (callable $match): mixed => $threads->first($match)['isCompleted'] ?? null;

            expect($threads)->toHaveCount(4)
                ->and($completedOf(fn (array $t): bool => $t['threadType'] === 'pembimbing' && $t['student'] === $done->name))->toBeTrue()
                ->and($completedOf(fn (array $t): bool => $t['id'] === $sidangThread->id))->toBeTrue()
                ->and($completedOf(fn (array $t): bool => $t['threadType'] === 'pembimbing' && $t['student'] === $busy->name))->toBeFalse()
                ->and($completedOf(fn (array $t): bool => $t['id'] === $privateThread->id))->toBeFalse();
        });
});

it('rejects a lecturer message in a completed thread and accepts one in an active thread', function (): void {
    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    ['thread' => $doneThread] = graceStudent($lecturer, $admin, completed: true);
    ['thread' => $busyThread] = graceStudent($lecturer, $admin, completed: false);
    $before = MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $doneThread->id)->count();

    $this->actingAs($lecturer)
        ->post("/dosen/pesan/{$doneThread->id}/messages", ['message' => 'Selamat ya'])
        ->assertSessionHasErrors(['message' => CompletedChatThreadService::READ_ONLY_MESSAGE]);

    expect(MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $doneThread->id)->count())->toBe($before);

    $this->actingAs($lecturer)
        ->post("/dosen/pesan/{$busyThread->id}/messages", ['message' => 'Lanjutkan bab 3'])
        ->assertSessionHasNoErrors();

    expect(MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $busyThread->id)->where('message', 'Lanjutkan bab 3')->exists())->toBeTrue();
});

it('rejects a student message in a completed thread and accepts one in an active thread', function (bool $completed, bool $accepted): void {
    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    ['student' => $student, 'thread' => $thread] = graceStudent($lecturer, $admin, $completed);
    $before = MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $thread->id)->count();

    $response = $this->actingAs($student)->post("/mahasiswa/pesan/{$thread->id}/messages", ['message' => 'Terima kasih Pak']);

    if ($accepted) {
        $response->assertSessionHasNoErrors();
    } else {
        $response->assertSessionHasErrors(['message' => CompletedChatThreadService::READ_ONLY_MESSAGE]);
    }

    expect(MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $thread->id)->count())->toBe($before + ($accepted ? 1 : 0));
})->with([
    'completed thesis' => [true, false],
    'active thesis' => [false, true],
]);

it('flags completed threads for the student and does not recreate the deleted bimbingan thread after archiving', function (): void {
    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    ['student' => $student, 'project' => $project, 'thread' => $thread] = graceStudent($lecturer, $admin, completed: true);

    $this->actingAs($student)
        ->get(route('mahasiswa.pesan'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pesan')
            ->where('threads.0.id', $thread->id)
            ->where('threads.0.isCompleted', true));

    // Archived: the thread is gone and opening Pesan must not bring an empty one back.
    $thread->delete();
    $project->forceFill(['archived_at' => now(), 'archive_summary' => ['version' => 1]])->save();

    $this->actingAs($student)
        ->get(route('mahasiswa.pesan'))
        ->assertInertia(fn (Assert $page) => $page->component('pesan')->has('threads', 0));

    expect(MentorshipChatThread::query()->where('student_user_id', $student->id)->count())->toBe(0);
});

it('shows the archived notice on student jadwal and dokumen pages only after archiving', function (): void {
    $admin = createUserWithRole(AppRole::Admin->value);
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    ['student' => $student, 'project' => $project] = graceStudent($lecturer, $admin, completed: true);

    foreach (['mahasiswa.jadwal-bimbingan' => 'jadwal-bimbingan', 'mahasiswa.upload-dokumen' => 'upload-dokumen'] as $route => $component) {
        $this->actingAs($student)->get(route($route))
            ->assertInertia(fn (Assert $page) => $page->component($component)->where('archivedProject', false));
    }

    $project->forceFill(['archived_at' => now(), 'archive_summary' => ['version' => 1]])->save();

    foreach (['mahasiswa.jadwal-bimbingan' => 'jadwal-bimbingan', 'mahasiswa.upload-dokumen' => 'upload-dokumen'] as $route => $component) {
        $this->actingAs($student)->get(route($route))
            ->assertInertia(fn (Assert $page) => $page->component($component)->where('archivedProject', true));
    }
});
