<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Notifications\Auth\AdminResetPasswordNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admin can send manual password reset link to mahasiswa from users table', function (): void {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $prodi = ProgramStudi::factory()->create();

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510001',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('sendPasswordResetLink')->table($student))
        ->assertNotified();

    Notification::assertSentTo($student, ResetPasswordNotification::class);

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_reset_link_sent_by_admin',
        'user_id' => $student->id,
        'email' => $student->email,
    ]);
});

test('super admin can send manual password reset link to admin from user view page', function (): void {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $superAdmin = User::factory()->asSuperAdmin()->create();
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($superAdmin);

    Livewire::test(ViewUser::class, ['record' => $admin->getKey()])
        ->callAction('sendPasswordResetLink')
        ->assertNotified();

    Notification::assertSentTo($admin, AdminResetPasswordNotification::class);

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_reset_link_sent_by_admin',
        'user_id' => $admin->id,
        'email' => $admin->email,
    ]);
});

test('admin cannot see manual password reset link action for admin accounts', function (): void {
    /** @var \Tests\TestCase $this */
    $prodi = ProgramStudi::factory()->create();

    $actor = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $actor->id,
        'program_studi_id' => $prodi->id,
    ]);

    $target = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $target->id,
        'program_studi_id' => $prodi->id,
    ]);

    $this->actingAs($actor);

    Livewire::test(ViewUser::class, ['record' => $target->getKey()])
        ->assertActionHidden('sendPasswordResetLink');
});
