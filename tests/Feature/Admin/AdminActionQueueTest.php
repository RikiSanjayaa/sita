<?php

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Widgets\AdminAgendaWidget;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\AdminActionQueueService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * A student with a confirmed phone number, so only the queue under test can match.
 *
 * @param  array<string, mixed>  $project
 */
function queueProject(ProgramStudi $prodi, array $project = [], bool $withPhone = true): ThesisProject
{
    $student = User::factory()->asMahasiswa()->create($withPhone ? [
        'phone_number' => '081234567890',
        'phone_number_confirmed_at' => now(),
    ] : []);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => (string) fake()->unique()->numerify('22105107##'),
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    return ThesisProject::query()->create(array_merge([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subWeeks(2),
        'mentorship_valid_until' => now()->addMonths(3)->toDateString(),
        'created_by' => $student->id,
    ], $project));
}

function queueSupervisor(ThesisProject $project): void
{
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => User::factory()->asDosen()->create()->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $project->student_user_id,
        'started_at' => now()->subWeek(),
    ]);
}

function queueAdmin(?ProgramStudi $prodi): User
{
    if ($prodi === null) {
        return User::factory()->asSuperAdmin()->create();
    }

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $prodi->id]);

    return $admin;
}

/**
 * @return array<string, array{0: string, 1: Closure(ProgramStudi): ThesisProject}>
 */
function queueScenarios(): array
{
    return [
        'title_review' => ['title_review', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi, ['phase' => 'title_review']);
            ThesisProjectTitle::query()->create([
                'project_id' => $project->id,
                'version_no' => 1,
                'title_id' => 'Judul menunggu',
                'status' => 'submitted',
                'submitted_by_user_id' => $project->student_user_id,
                'submitted_at' => now()->subDay(),
            ]);
            queueSupervisor($project);

            return $project;
        }],
        'awaiting_finalization' => ['awaiting_finalization', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi, ['phase' => 'sempro']);
            queueSupervisor($project);
            ThesisDefense::query()->create([
                'project_id' => $project->id,
                'type' => 'sempro',
                'attempt_no' => 1,
                'status' => 'awaiting_finalization',
                'scheduled_for' => now()->subDay(),
                'created_by' => $project->student_user_id,
            ]);

            return $project;
        }],
        'no_supervisor' => ['no_supervisor', fn (ProgramStudi $prodi): ThesisProject => queueProject($prodi, ['phase' => 'research'])],
        'revision_overdue' => ['revision_overdue', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi);
            queueSupervisor($project);
            ThesisRevision::query()->create([
                'project_id' => $project->id,
                'requested_by_user_id' => $project->student_user_id,
                'status' => 'open',
                'notes' => 'Terlambat.',
                'due_at' => now()->subDay(),
            ]);

            return $project;
        }],
        'mentorship_lapsed' => ['mentorship_lapsed', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi, ['mentorship_valid_until' => now()->subDays(3)->toDateString()]);
            queueSupervisor($project);

            return $project;
        }],
        'archive_soon' => ['archive_soon', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi, [
                'phase' => 'completed',
                'state' => 'completed',
                'completed_at' => now()->subDays((int) config('sita.archive_grace_days', 30) - 3),
            ]);
            queueSupervisor($project);

            return $project;
        }],
        'mentorship_unset' => ['mentorship_unset', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi, ['mentorship_valid_until' => null]);
            queueSupervisor($project);

            return $project;
        }],
        'phone_missing' => ['phone_missing', function (ProgramStudi $prodi): ThesisProject {
            $project = queueProject($prodi, withPhone: false);
            queueSupervisor($project);

            return $project;
        }],
    ];
}

it('queues a project only under its own action', function (string $key, Closure $make): void {
    $prodi = ProgramStudi::factory()->create();
    $match = $make($prodi);

    $healthy = queueProject($prodi);
    queueSupervisor($healthy);

    $service = app(AdminActionQueueService::class);
    $admin = queueAdmin($prodi);

    expect($service->queueQuery($key, $admin)->pluck('thesis_projects.id')->all())->toBe([$match->id]);

    $item = $service->items($admin)->firstWhere('key', $key);

    expect($item)->not->toBeNull()
        ->and($item['count'])->toBe(1)
        ->and($item['samples'])->toBe($match->student->name)
        ->and($item['url'])->toContain('action_queue')->toContain($key);

    // Every other queue stays silent for this project.
    $others = $service->items($admin)->pluck('key')->reject(fn (string $other): bool => $other === $key);
    expect($others->all())->not->toContain($key);
    foreach ($others as $other) {
        expect($service->queueQuery($other, $admin)->whereKey($match->id)->exists())->toBeFalse();
    }
})->with(queueScenarios());

it('never queues archived projects', function (string $key, Closure $make): void {
    $prodi = ProgramStudi::factory()->create();
    $project = $make($prodi);
    $project->forceFill(['archived_at' => now()->subDay()])->save();

    expect(app(AdminActionQueueService::class)->queueQuery($key, queueAdmin($prodi))->count())->toBe(0);
})->with(queueScenarios());

it('scopes queues to the program studi of an admin prodi and shows everything to a super admin', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();
    $own = queueProject($prodi, ['mentorship_valid_until' => now()->subDay()->toDateString()]);
    $foreign = queueProject($otherProdi, ['mentorship_valid_until' => now()->subDay()->toDateString()]);
    queueSupervisor($own);
    queueSupervisor($foreign);

    $service = app(AdminActionQueueService::class);

    expect($service->queueQuery('mentorship_lapsed', queueAdmin($prodi))->pluck('thesis_projects.id')->all())->toBe([$own->id])
        ->and($service->queueQuery('mentorship_lapsed', queueAdmin(null))->pluck('thesis_projects.id')->sort()->values()->all())
        ->toBe(collect([$own->id, $foreign->id])->sort()->values()->all());
});

it('previews a single name with a remainder for a large queue', function (): void {
    $prodi = ProgramStudi::factory()->create();
    foreach (range(1, 5) as $_) {
        queueSupervisor(queueProject($prodi, ['mentorship_valid_until' => now()->subDay()->toDateString()]));
    }

    $item = app(AdminActionQueueService::class)->items(queueAdmin($prodi))->firstWhere('key', 'mentorship_lapsed');

    expect($item['count'])->toBe(5)
        ->and($item['samples'])->toEndWith(' dan 4 lainnya')
        ->and($item['samples'])->not->toContain(',');
});

it('opens the thesis project list already filtered by the queue', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $lapsed = queueProject($prodi, ['mentorship_valid_until' => now()->subDays(2)->toDateString()]);
    $healthy = queueProject($prodi);
    queueSupervisor($lapsed);
    queueSupervisor($healthy);
    $admin = queueAdmin($prodi);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::withQueryParams(['filters' => ['action_queue' => ['value' => 'mentorship_lapsed']]])
        ->test(ListThesisProjects::class)
        ->assertCanSeeTableRecords([$lapsed])
        ->assertCanNotSeeTableRecords([$healthy]);

    Livewire::test(ListThesisProjects::class)
        ->filterTable('action_queue', 'mentorship_lapsed')
        ->assertCanSeeTableRecords([$lapsed])
        ->assertCanNotSeeTableRecords([$healthy]);
});

it('renews a lapsed masa aktif bimbingan from the list row', function (): void {
    Carbon::setTestNow('2026-10-24 09:00:00');

    try {
        $prodi = ProgramStudi::factory()->create();
        $lapsed = queueProject($prodi, ['mentorship_valid_until' => '2026-10-01']);
        $healthy = queueProject($prodi);
        queueSupervisor($lapsed);

        /** @var \Tests\TestCase $this */
        $this->actingAs(queueAdmin($prodi));

        Livewire::test(ListThesisProjects::class)
            ->assertTableActionVisible('renew_mentorship_ticket', $lapsed)
            ->assertTableActionHidden('renew_mentorship_ticket', $healthy)
            ->callTableAction('renew_mentorship_ticket', $lapsed);

        expect($lapsed->fresh()->mentorship_valid_until->toDateString())->toBe('2027-10-24')
            ->and($lapsed->fresh()->isMentorshipSuspended())->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});

it('renders the action queue first, then the agenda, then the statistics', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $lapsed = queueProject($prodi, ['mentorship_valid_until' => now()->subDays(2)->toDateString()]);
    queueSupervisor($lapsed);

    /** @var \Tests\TestCase $this */
    $this->actingAs(queueAdmin($prodi))
        ->get('/admin')
        ->assertOk()
        ->assertSeeInOrder([
            'Perlu tindakan',
            'Masa aktif bimbingan habis',
            'Perpanjang',
            'Agenda ujian 7 hari ke depan',
            'Sebaran Fase Tugas Akhir',
        ]);
});

it('shows an all-clear state and no queue items when nothing needs action', function (): void {
    $prodi = ProgramStudi::factory()->create();
    queueSupervisor(queueProject($prodi));
    $admin = queueAdmin($prodi);

    expect(app(AdminActionQueueService::class)->items($admin))->toBeEmpty();

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Semua beres');
});

it('reports every queue, in priority order, for a scoped admin', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();
    $expected = [];
    foreach (queueScenarios() as [$key, $make]) {
        $make($prodi);
        $make($otherProdi);
        $expected[] = $key;
    }

    $service = app(AdminActionQueueService::class);
    $scoped = $service->items(queueAdmin($prodi));
    $all = $service->items(queueAdmin(null));

    expect($scoped->pluck('key')->all())->toBe($expected)
        ->and($scoped->pluck('count')->unique()->all())->toBe([1])
        ->and($all->pluck('key')->all())->toBe($expected)
        ->and($all->pluck('count')->unique()->all())->toBe([2]);

    foreach ($scoped as $item) {
        expect($item['url'])->toContain('action_queue')->toContain($item['key']);
    }
});

it('previews one name only on the first non-empty queue', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $scenarios = queueScenarios();
    $keys = array_keys($scenarios);

    // Two projects in the second queue and one in the third; the first queue is empty.
    $first = $scenarios[$keys[1]][1]($prodi);
    $second = $scenarios[$keys[1]][1]($prodi);
    $scenarios[$keys[2]][1]($prodi);

    $items = app(AdminActionQueueService::class)->items(queueAdmin($prodi));

    expect($items->pluck('key')->all())->toBe([$keys[1], $keys[2]])
        ->and($items[0]['count'])->toBe(2)
        ->and($items[0]['samples'])->not->toBe('')
        ->and($items[0]['samples'])->toEndWith(' dan 1 lainnya')
        ->and($items[0]['samples'])->not->toContain(',')
        ->and($items[1]['samples'])->toBe('');

    // Once the leading queue clears, the preview moves to the next one.
    $first->forceFill(['archived_at' => now()])->save();
    $second->forceFill(['archived_at' => now()])->save();

    $after = app(AdminActionQueueService::class)->items(queueAdmin($prodi));

    expect($after->pluck('key')->all())->toBe([$keys[2]])
        ->and($after[0]['samples'])->not->toBe('');
});

it('runs a bounded number of queries however many projects are queued', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $admin = queueAdmin($prodi);
    $service = app(AdminActionQueueService::class);

    foreach (queueScenarios() as [, $make]) {
        $make($prodi);
    }

    // Resolve the actor's scope before both measurements so only queue size changes.
    $admin->adminProgramStudiId();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $service->items($admin);
    $small = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (queueScenarios() as [, $make]) {
        $make($prodi);
        $make($prodi);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $service->items($admin);
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($large)->toBe($small);
});

it('lists only scheduled ujian of the next seven days for the admin program studi', function (): void {
    Carbon::setTestNow('2026-10-24 09:00:00');

    try {
        $prodi = ProgramStudi::factory()->create();
        $otherProdi = ProgramStudi::factory()->create();

        $make = function (ProgramStudi $studi, int $days, string $status = 'scheduled') {
            $project = queueProject($studi);

            return ThesisDefense::query()->create([
                'project_id' => $project->id,
                'type' => 'sempro',
                'attempt_no' => 1,
                'status' => $status,
                'scheduled_for' => now()->addDays($days),
                'created_by' => $project->student_user_id,
            ]);
        };

        $soon = $make($prodi, 3);
        $make($prodi, 9);
        $make($prodi, -1);
        $make($prodi, 2, 'completed');
        $make($otherProdi, 2);

        expect(AdminAgendaWidget::agendaQuery(queueAdmin($prodi))->pluck('id')->all())->toBe([$soon->id])
            ->and(AdminAgendaWidget::agendaQuery(queueAdmin(null))->count())->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
});
