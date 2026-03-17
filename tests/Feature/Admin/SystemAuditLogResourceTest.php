<?php

use App\Enums\AppRole;
use App\Filament\Resources\SystemAuditLogs\Pages\ListSystemAuditLogs;
use App\Filament\Resources\SystemAuditLogs\SystemAuditLogResource;
use App\Models\AdminProfile;
use App\Models\ProgramStudi;
use App\Models\SystemAuditLog;
use App\Models\User;
use Livewire\Livewire;

test('admin only sees their own system audit logs', function (): void {
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    $admin = User::factory()->asAdmin()->create(['name' => 'Admin Prodi']);
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $programStudi->id,
    ]);

    $otherAdmin = User::factory()->asAdmin()->create(['name' => 'Admin Lain']);
    AdminProfile::query()->create([
        'user_id' => $otherAdmin->id,
        'program_studi_id' => $programStudi->id,
    ]);

    $ownLog = SystemAuditLog::query()->create([
        'user_id' => $admin->id,
        'event_type' => 'login_success',
        'label' => 'Login berhasil',
        'description' => 'Admin berhasil login.',
        'email' => $admin->email,
        'occurred_at' => now()->subMinute(),
    ]);

    $otherLog = SystemAuditLog::query()->create([
        'user_id' => $otherAdmin->id,
        'event_type' => 'role_switched',
        'label' => 'Peran diganti',
        'description' => 'Admin lain mengganti peran.',
        'email' => $otherAdmin->email,
        'occurred_at' => now(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListSystemAuditLogs::class)
        ->assertCanSeeTableRecords([$ownLog])
        ->assertCanNotSeeTableRecords([$otherLog]);

    $this->get(SystemAuditLogResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Audit Sistem');
});

test('super admin can filter system audit logs', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $admin = User::factory()->asAdmin()->create();

    $loginLog = SystemAuditLog::query()->create([
        'user_id' => $admin->id,
        'event_type' => 'login_success',
        'label' => 'Login berhasil',
        'description' => 'Admin berhasil login.',
        'email' => $admin->email,
        'occurred_at' => now()->subMinute(),
    ]);

    $switchLog = SystemAuditLog::query()->create([
        'user_id' => $superAdmin->id,
        'event_type' => 'role_switched',
        'label' => 'Peran diganti',
        'description' => 'Super admin mengganti peran.',
        'email' => $superAdmin->email,
        'occurred_at' => now(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($superAdmin);

    Livewire::test(ListSystemAuditLogs::class)
        ->assertCanSeeTableRecords([$loginLog, $switchLog])
        ->filterTable('event_type', 'role_switched')
        ->assertCanSeeTableRecords([$switchLog])
        ->assertCanNotSeeTableRecords([$loginLog]);
});

test('role switch writes to system audit logs', function (): void {
    $adminRole = App\Models\Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);
    $mahasiswaRole = App\Models\Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);
    $user = User::factory()->create([
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    $user->roles()->sync([$mahasiswaRole->id, $adminRole->id]);

    $this->actingAs($user)
        ->post('/role/switch', ['role' => AppRole::Admin->value])
        ->assertRedirect('/admin');

    $this->assertDatabaseHas('system_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'role_switched',
        'label' => 'Peran diganti',
    ]);
});

test('system audit log prunable query keeps only one year of history', function (): void {
    $olderLog = SystemAuditLog::query()->create([
        'event_type' => 'login_success',
        'label' => 'Login berhasil',
        'description' => 'Log lama',
        'occurred_at' => now()->subYear()->subDay(),
    ]);

    $recentLog = SystemAuditLog::query()->create([
        'event_type' => 'login_success',
        'label' => 'Login berhasil',
        'description' => 'Log baru',
        'occurred_at' => now()->subMonths(6),
    ]);

    $prunableIds = (new SystemAuditLog)->prunable()->pluck('id')->all();

    expect($prunableIds)->toContain($olderLog->id)
        ->and($prunableIds)->not->toContain($recentLog->id);
});
