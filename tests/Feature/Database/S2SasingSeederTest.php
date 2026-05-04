<?php

use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\SystemAnnouncement;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\User;
use Database\Seeders\S2SasingSeeder;

test('s2 sastra inggris deployment seeder seeds the expected showcase accounts and prodi data', function (): void {
    /** @var \Tests\TestCase $this */
    $this->seed(S2SasingSeeder::class);

    $programStudi = ProgramStudi::query()->where('slug', 's2-sastra-inggris')->firstOrFail();

    expect(ProgramStudi::query()->count())->toBe(1)
        ->and($programStudi->name)->toBe('S2 Sastra Inggris')
        ->and($programStudi->concentrationList())->toBe(['Umum'])
        ->and(Role::query()->pluck('name')->sort()->values()->all())->toBe(collect(AppRole::values())->sort()->values()->all())
        ->and(User::query()->count())->toBe(25)
        ->and(User::query()->where('email', 'like', '%@gmail.com')->count())->toBe(25)
        ->and(User::query()->where('email', 'superadmin.s2.sasing@gmail.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'admin.s2.sasing@gmail.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'dosen1.s2.sasing@gmail.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'siswa1.s2.sasing@gmail.com')->exists())->toBeTrue()
        ->and(User::query()->whereHas('roles', fn($query) => $query->where('name', AppRole::SuperAdmin->value))->count())->toBe(1)
        ->and(User::query()->whereHas('roles', fn($query) => $query->where('name', AppRole::Admin->value))->count())->toBe(1)
        ->and(User::query()->whereHas('roles', fn($query) => $query->where('name', AppRole::Dosen->value))->count())->toBe(8)
        ->and(User::query()->whereHas('roles', fn($query) => $query->where('name', AppRole::Mahasiswa->value))->count())->toBe(15)
        ->and(User::query()->whereHas('roles', fn($query) => $query->where('name', AppRole::Penguji->value))->count())->toBe(0)
        ->and(SystemAnnouncement::query()->where('program_studi_id', $programStudi->id)->where('status', SystemAnnouncement::STATUS_PUBLISHED)->count())->toBe(1);
});

test('s2 sastra inggris deployment seeder covers the planned thesis workflow scenarios', function (): void {
    /** @var \Tests\TestCase $this */
    $this->seed(S2SasingSeeder::class);

    expect(ThesisProject::query()->count())->toBe(16)
        ->and(ThesisProject::query()->where('phase', 'title_review')->where('state', 'active')->count())->toBe(4)
        ->and(ThesisProject::query()->where('phase', 'sempro')->where('state', 'active')->count())->toBe(4)
        ->and(ThesisProject::query()->where('phase', 'research')->where('state', 'active')->count())->toBe(2)
        ->and(ThesisProject::query()->where('phase', 'sidang')->where('state', 'active')->count())->toBe(3)
        ->and(ThesisProject::query()->where('phase', 'completed')->where('state', 'completed')->count())->toBe(1)
        ->and(ThesisProject::query()->where('state', 'on_hold')->count())->toBe(1)
        ->and(ThesisProject::query()->where('state', 'cancelled')->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sempro')->where('status', 'scheduled')->where('scheduled_for', '>', now())->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sidang')->where('status', 'scheduled')->where('scheduled_for', '>', now())->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sempro')->where('status', 'awaiting_finalization')->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sidang')->where('status', 'awaiting_finalization')->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sempro')->where('result', 'pass_with_revision')->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sempro')->where('result', 'fail')->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sidang')->where('result', 'pass_with_revision')->count())->toBe(1)
        ->and(ThesisDefense::query()->where('type', 'sidang')->where('result', 'pass')->count())->toBe(1)
        ->and(ThesisRevision::query()->where('status', 'open')->count())->toBe(2);

    $rotationProjectExists = ThesisProject::query()
        ->where('phase', 'sidang')
        ->where('state', 'active')
        ->whereHas('supervisorAssignments', fn($query) => $query->where('role', 'primary')->where('status', 'ended'))
        ->whereHas('supervisorAssignments', fn($query) => $query->where('role', 'primary')->where('status', 'active'))
        ->exists();

    $restartProjectExists = ThesisProject::query()
        ->where('state', 'cancelled')
        ->whereHas('student', fn($query) => $query->where('email', 'siswa15.s2.sasing@gmail.com'))
        ->exists();

    expect($rotationProjectExists)->toBeTrue()
        ->and($restartProjectExists)->toBeTrue();
});
