<?php

use App\Models\MahasiswaProfile;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function dosenDashboardProject(User $lecturer, int $startedDaysAgo = 5, string $state = 'active', ?string $name = null): ThesisProject
{
    $prodi = ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create($name === null ? [] : ['name' => $name]);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => $state,
        'started_at' => now()->subDays($startedDaysAgo),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Uji Dashboard',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays($startedDaysAgo),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $student->id,
        'started_at' => now()->subDays($startedDaysAgo),
    ]);

    return $project;
}

function dosenDashboardDefense(ThesisProject $project, User $examiner, string $scheduledFor, string $decision = 'pending'): ThesisDefense
{
    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => ThesisProjectTitle::query()->where('project_id', $project->id)->value('id'),
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => $scheduledFor,
        'location' => 'Ruang Seminar A',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => $decision,
    ]);

    return $defense;
}

function dosenDashboardSchedule(User $lecturer, ThesisProject $project, string $status, mixed $when): MentorshipSchedule
{
    return MentorshipSchedule::factory()->create([
        'student_user_id' => $project->student_user_id,
        'lecturer_user_id' => $lecturer->id,
        'status' => $status,
        'requested_for' => $when,
        'scheduled_for' => $when,
    ]);
}

/**
 * @return array<string, mixed>
 */
function dosenDashboardProps(User $lecturer): array
{
    return test()->actingAs($lecturer)->get('/dosen/dashboard')->assertOk()->viewData('page')['props'];
}

/**
 * @return array<string, mixed>|null
 */
function dosenDashboardItem(User $lecturer, string $key): ?array
{
    return collect(dosenDashboardProps($lecturer)['actionItems'])->firstWhere('key', $key);
}

dataset('dosen dashboard action items', [
    'permintaan jadwal pending' => [
        'pending_schedules',
        2,
        '/dosen/jadwal-bimbingan',
        function (User $lecturer, ThesisProject $project): void {
            dosenDashboardSchedule($lecturer, $project, 'pending', now()->addDays(2));
            dosenDashboardSchedule($lecturer, $project, 'pending', now()->addDays(3));
            dosenDashboardSchedule($lecturer, $project, 'approved', now()->addDays(3));
            dosenDashboardSchedule(User::factory()->asDosen()->create(), $project, 'pending', now()->addDays(3));
        },
    ],
    'bimbingan lewat belum selesai' => [
        'overdue_sessions',
        2,
        '/dosen/jadwal-bimbingan?upcomingFilter=lewat',
        function (User $lecturer, ThesisProject $project): void {
            dosenDashboardSchedule($lecturer, $project, 'approved', now()->subDays(2));
            dosenDashboardSchedule($lecturer, $project, 'rescheduled', now()->subHour());
            dosenDashboardSchedule($lecturer, $project, 'completed', now()->subDays(3));
            dosenDashboardSchedule($lecturer, $project, 'approved', now()->addDay());
            dosenDashboardSchedule(User::factory()->asDosen()->create(), $project, 'approved', now()->subDay());
        },
    ],
    'dokumen menunggu review' => [
        'documents_to_review',
        2,
        '/dosen/dokumen-revisi?status=Perlu%20Review',
        function (User $lecturer, ThesisProject $project): void {
            foreach (['submitted', 'submitted', 'needs_revision', 'approved'] as $status) {
                MentorshipDocument::factory()->create([
                    'student_user_id' => $project->student_user_id,
                    'lecturer_user_id' => $lecturer->id,
                    'status' => $status,
                ]);
            }
            MentorshipDocument::factory()->create([
                'student_user_id' => $project->student_user_id,
                'lecturer_user_id' => User::factory()->asDosen()->create()->id,
                'status' => 'submitted',
            ]);
        },
    ],
    'ujian harus dinilai' => [
        'defenses_to_grade',
        1,
        '/dosen/seminar-proposal?status=pending',
        function (User $lecturer, ThesisProject $project): void {
            dosenDashboardDefense($project, $lecturer, now()->subDay()->toDateTimeString());
            // Not held yet, and already graded: neither is waiting on the lecturer.
            dosenDashboardDefense(dosenDashboardProject($lecturer), $lecturer, now()->addDays(2)->toDateTimeString());
            dosenDashboardDefense(dosenDashboardProject($lecturer), $lecturer, now()->subDay()->toDateTimeString(), 'pass');
        },
    ],
    'revisi menunggu verifikasi' => [
        'revisions_to_verify',
        1,
        '/dosen/seminar-proposal?status=revisi',
        function (User $lecturer, ThesisProject $project): void {
            foreach (['submitted', 'open', 'resolved'] as $status) {
                ThesisRevision::factory()->create([
                    'project_id' => $project->id,
                    'requested_by_user_id' => $lecturer->id,
                    'status' => $status,
                ]);
            }
            ThesisRevision::factory()->create([
                'project_id' => $project->id,
                'requested_by_user_id' => User::factory()->asDosen()->create()->id,
                'status' => 'submitted',
            ]);
        },
    ],
]);

it('lists each kind of work waiting on the lecturer with its count and a filtered link', function (string $key, int $count, string $href, Closure $arrange): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = dosenDashboardProject($lecturer);
    $arrange($lecturer, $project);

    expect(dosenDashboardItem($lecturer, $key))
        ->toMatchArray(['key' => $key, 'count' => $count, 'href' => $href, 'students' => [$project->student->name]]);
})->with('dosen dashboard action items');

it('bounds the leading work preview to one eligible name without changing its count', function (string $key, int $count, string $href, Closure $arrange): void {
    $lecturer = User::factory()->asDosen()->create();
    foreach (['Delta', 'Alpha', 'Charlie', 'Alpha', 'Beta'] as $name) {
        $arrange($lecturer, dosenDashboardProject($lecturer, name: $name));
    }

    $otherLecturer = User::factory()->asDosen()->create();
    $outside = dosenDashboardProject($otherLecturer, name: 'Aardvark Outside');
    $arrange($otherLecturer, $outside);
    // Schedules/documents belonging to this lecturer still need an eligible student.
    dosenDashboardSchedule($lecturer, $outside, 'pending', now()->addDay());
    dosenDashboardSchedule($lecturer, $outside, 'approved', now()->subDay());
    MentorshipDocument::factory()->create([
        'student_user_id' => $outside->student_user_id,
        'lecturer_user_id' => $lecturer->id,
        'status' => 'submitted',
    ]);

    expect(dosenDashboardItem($lecturer, $key))->toMatchArray([
        'count' => $count * 5,
        'href' => $href,
        'students' => ['Alpha'],
    ]);
})->with('dosen dashboard action items');

it('flags supervised students without activity for more than 30 days', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    foreach (['Delta', 'Alpha', 'Charlie', 'Alpha', 'Beta'] as $name) {
        dosenDashboardProject($lecturer, startedDaysAgo: 90, name: $name);
    }
    dosenDashboardProject($lecturer, startedDaysAgo: 10, name: 'Aardvark Recent');
    dosenDashboardProject(User::factory()->asDosen()->create(), startedDaysAgo: 90, name: 'Aardvark Outside');

    expect(dosenDashboardItem($lecturer, 'inactive_students'))
        ->toMatchArray([
            'count' => 5,
            'href' => '/dosen/mahasiswa-bimbingan?activeAttention=inactive',
            'students' => ['Alpha'],
        ]);
});

it('counts a recent document as activity', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = dosenDashboardProject($lecturer, startedDaysAgo: 90);
    MentorshipDocument::factory()->create([
        'student_user_id' => $project->student_user_id,
        'lecturer_user_id' => $lecturer->id,
        'status' => 'approved',
    ]);

    expect(dosenDashboardItem($lecturer, 'inactive_students'))->toBeNull();
});

it('says everything is done when nothing waits on the lecturer', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    dosenDashboardProject($lecturer);

    $props = dosenDashboardProps($lecturer);

    expect($props['actionItems'])->toBe([])
        ->and($props)->not->toHaveKeys(['upcomingActivities', 'activeStudents']);
});

it('orders items by urgency', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    dosenDashboardProject($lecturer, startedDaysAgo: 90);
    dosenDashboardSchedule($lecturer, dosenDashboardProject($lecturer), 'pending', now()->addDay());
    $toGrade = dosenDashboardProject($lecturer);
    dosenDashboardDefense($toGrade, $lecturer, now()->subDay()->toDateTimeString());

    $items = dosenDashboardProps($lecturer)['actionItems'];

    expect(array_column($items, 'key'))
        ->toBe(['defenses_to_grade', 'pending_schedules', 'inactive_students'])
        ->and($items[0]['students'])->toBe([$toGrade->student->name])
        ->and($items[1]['students'])->toBe([])
        ->and($items[2]['students'])->toBe([]);
});

it('keeps archived and completed students out of the action queue', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = dosenDashboardProject($lecturer, startedDaysAgo: 120, state: 'completed');
    $project->forceFill(['archived_at' => now()->subDays(2), 'completed_at' => now()->subDays(40)])->save();
    $project->supervisorAssignments()->update(['status' => 'ended']);

    dosenDashboardSchedule($lecturer, $project, 'pending', now()->addDay());
    dosenDashboardSchedule($lecturer, $project, 'approved', now()->subDays(2));
    MentorshipDocument::factory()->create([
        'student_user_id' => $project->student_user_id,
        'lecturer_user_id' => $lecturer->id,
        'status' => 'submitted',
    ]);
    dosenDashboardDefense($project, $lecturer, now()->subDay()->toDateTimeString());
    ThesisRevision::factory()->create([
        'project_id' => $project->id,
        'requested_by_user_id' => $lecturer->id,
        'status' => 'submitted',
    ]);

    $props = dosenDashboardProps($lecturer);

    expect($props['actionItems'])->toBe([])
        ->and($props['students'])->toBe([]);
});

it('shows students needing attention first with the shared progress snapshot', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    dosenDashboardProject($lecturer, startedDaysAgo: 90, name: 'Mahasiswa Diam');
    dosenDashboardProject($lecturer, startedDaysAgo: 3, name: 'Mahasiswa Baru');
    $lapsed = dosenDashboardProject($lecturer, startedDaysAgo: 90, name: 'Mahasiswa Habis');
    $lapsed->forceFill(['mentorship_valid_until' => now()->subDays(3)->toDateString()])->save();

    $students = dosenDashboardProps($lecturer)['students'];

    expect(array_column($students, 'name'))->toBe(['Mahasiswa Habis', 'Mahasiswa Diam', 'Mahasiswa Baru'])
        ->and($students[0]['attention'])->toContain('Masa aktif bimbingan habis')
        ->and($students[1]['attention'])->toHaveCount(2)
        ->and($students[2]['attention'])->toBe(['Bimbingan 0 dari minimal 6'])
        ->and($students[0]['progress'])->toHaveKeys(['phase', 'phaseLabel', 'detail'])
        ->and($students[0]['progress']['detail']['ticket']['suspended'])->toBeTrue();
});

it('caps the student cards but still counts every inactive student', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    foreach (range(1, 8) as $i) {
        dosenDashboardProject($lecturer, startedDaysAgo: 90);
    }

    $props = dosenDashboardProps($lecturer);

    expect($props['students'])->toHaveCount(6)
        ->and(collect($props['actionItems'])->firstWhere('key', 'inactive_students')['count'])->toBe(8);
});

it('groups the agenda for today and the next seven days', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = dosenDashboardProject($lecturer);
    $today = dosenDashboardSchedule($lecturer, $project, 'approved', now('Asia/Makassar')->setTime(23, 30)->utc());
    dosenDashboardSchedule($lecturer, $project, 'approved', now()->addDays(6)->setTime(9, 0));
    dosenDashboardSchedule($lecturer, $project, 'approved', now()->addDays(12));
    dosenDashboardSchedule($lecturer, $project, 'pending', now()->addDays(2));
    dosenDashboardDefense(dosenDashboardProject($lecturer), $lecturer, now()->addDays(2)->toDateTimeString());

    $agenda = dosenDashboardProps($lecturer)['agenda'];

    expect($agenda)->toHaveCount(3)
        ->and($agenda[0]['isToday'])->toBeTrue()
        ->and($agenda[0]['label'])->toStartWith('Hari ini')
        ->and($agenda[0]['events'][0]['id'])->toBe('schedule-'.$today->id)
        ->and($agenda[0]['events'][0]['category'])->toBe('bimbingan')
        ->and($agenda[1]['events'][0]['href'])->toBe('/dosen/seminar-proposal')
        ->and($agenda[1]['events'][0]['category'])->toBe('ujian');
});

it('does not run more queries for more students', function (): void {
    $countQueries = function (User $lecturer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->actingAs($lecturer)->get('/dosen/dashboard')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $few = User::factory()->asDosen()->create();
    $many = User::factory()->asDosen()->create();
    foreach ([[$few, 1], [$many, 8]] as [$lecturer, $total]) {
        dosenDashboardProject($lecturer, startedDaysAgo: 90, name: 'Inactive Student');
        foreach (range(1, $total) as $i) {
            $project = dosenDashboardProject($lecturer, startedDaysAgo: 90);
            dosenDashboardSchedule($lecturer, $project, 'pending', now()->addDays(2));
            dosenDashboardSchedule($lecturer, $project, 'approved', now()->subDay());
            MentorshipDocument::factory()->create([
                'student_user_id' => $project->student_user_id,
                'lecturer_user_id' => $lecturer->id,
                'status' => 'submitted',
            ]);
            dosenDashboardDefense($project, $lecturer, now()->subDay()->toDateTimeString());
            ThesisRevision::factory()->create([
                'project_id' => $project->id,
                'requested_by_user_id' => $lecturer->id,
                'status' => 'submitted',
            ]);
        }
    }

    $countQueries($few);
    $fewCount = $countQueries($few);
    $countQueries($many);

    expect($countQueries($many))->toBe($fewCount);
});

it('marks attention on Mahasiswa Dosen rows and overdue sessions on Jadwal Bimbingan', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = dosenDashboardProject($lecturer, startedDaysAgo: 90);
    dosenDashboardSchedule($lecturer, $project, 'approved', now()->subDay());
    dosenDashboardSchedule($lecturer, $project, 'approved', now()->addDay());

    $this->actingAs($lecturer)
        ->get('/dosen/mahasiswa-bimbingan')
        ->assertInertia(fn (Assert $page) => $page
            ->where('mahasiswaRows.0.attention.0.key', 'inactive'));

    $this->actingAs($lecturer)
        ->get('/dosen/jadwal-bimbingan')
        ->assertInertia(fn (Assert $page) => $page
            ->where('upcomingSchedules', fn ($rows) => collect($rows)->pluck('isOverdue')->sort()->values()->all() === [false, true]));
});
