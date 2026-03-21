<?php

use App\Filament\Resources\SystemAnnouncements\Pages\CreateSystemAnnouncement;
use App\Filament\Resources\SystemAnnouncements\Pages\ListSystemAnnouncements;
use App\Filament\Resources\SystemAnnouncements\SystemAnnouncementResource;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\SystemAnnouncementService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function createAnnouncementStudent(ProgramStudi $prodi, array $userAttributes = [], array $profileAttributes = []): User
{
    $student = User::factory()->asMahasiswa()->create($userAttributes);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
        ...$profileAttributes,
    ]);

    return $student;
}

function createAnnouncementLecturer(ProgramStudi $prodi, array $userAttributes = [], array $profileAttributes = []): User
{
    $lecturer = User::factory()->asDosen()->create($userAttributes);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $prodi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
        ...$profileAttributes,
    ]);

    return $lecturer;
}

test('admin only sees system announcements for their own prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $admin = User::factory()->asAdmin()->create();

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodiA->id,
    ]);

    $announcementA = SystemAnnouncement::query()->create([
        'title' => 'Pengumuman Prodi A',
        'body' => 'Khusus prodi A.',
        'target_roles' => ['mahasiswa'],
        'program_studi_id' => $prodiA->id,
        'status' => SystemAnnouncement::STATUS_DRAFT,
    ]);

    $announcementB = SystemAnnouncement::query()->create([
        'title' => 'Pengumuman Prodi B',
        'body' => 'Khusus prodi B.',
        'target_roles' => ['mahasiswa'],
        'program_studi_id' => $prodiB->id,
        'status' => SystemAnnouncement::STATUS_DRAFT,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListSystemAnnouncements::class)
        ->assertCanSeeTableRecords([$announcementA])
        ->assertCanNotSeeTableRecords([$announcementB]);

    $this->get(SystemAnnouncementResource::getUrl('edit', ['record' => $announcementA]))
        ->assertOk();

    $this->get(SystemAnnouncementResource::getUrl('edit', ['record' => $announcementB]))
        ->assertNotFound();
});

test('admin can create and publish prodi announcement to matching recipients only', function (): void {
    Notification::fake();

    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $admin = User::factory()->asAdmin()->create(['name' => 'Admin Prodi A']);

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodiA->id,
    ]);

    $studentA = createAnnouncementStudent($prodiA, ['name' => 'Mahasiswa A']);
    $studentDisabled = createAnnouncementStudent($prodiA, [
        'name' => 'Mahasiswa Silent',
        'notification_preferences' => [
            'pengumumanSistem' => false,
        ],
    ]);
    $lecturerA = createAnnouncementLecturer($prodiA, ['name' => 'Dosen A']);
    $studentB = createAnnouncementStudent($prodiB, ['name' => 'Mahasiswa B']);

    $this->actingAs($admin);

    Livewire::test(CreateSystemAnnouncement::class)
        ->fillForm([
            'title' => 'Maintenance Portal',
            'body' => 'Portal SiTA akan maintenance malam ini.',
            'target_roles' => ['mahasiswa', 'dosen'],
            'status' => SystemAnnouncement::STATUS_PUBLISHED,
            'action_url' => '/dashboard',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(SystemAnnouncementResource::getUrl('index'));

    $announcement = SystemAnnouncement::query()->latest('id')->firstOrFail();

    expect($announcement->program_studi_id)->toBe($prodiA->id)
        ->and($announcement->created_by)->toBe($admin->id)
        ->and($announcement->notified_at)->not->toBeNull()
        ->and($announcement->isPublished())->toBeTrue();

    Notification::assertSentTo($studentA, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($studentA): bool {
        $data = $notification->toArray($studentA);

        return in_array('database', $channels, true)
            && $data['title'] === 'Maintenance Portal'
            && $data['preferenceKey'] === 'pengumumanSistem';
    });

    Notification::assertSentTo($lecturerA, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($lecturerA): bool {
        $data = $notification->toArray($lecturerA);

        return in_array('broadcast', $channels, true)
            && $data['title'] === 'Maintenance Portal'
            && $data['preferenceKey'] === 'pengumumanSistem';
    });

    Notification::assertNothingSentTo($studentDisabled);
    Notification::assertNothingSentTo($studentB);
});

test('super admin can publish global announcement for admins across prodi', function (): void {
    Notification::fake();

    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $superAdmin = User::factory()->asSuperAdmin()->create(['name' => 'Super Admin']);
    $adminA = User::factory()->asAdmin()->create(['name' => 'Admin A']);
    $adminB = User::factory()->asAdmin()->create(['name' => 'Admin B']);
    $studentA = createAnnouncementStudent($prodiA, ['name' => 'Mahasiswa A']);

    AdminProfile::query()->create([
        'user_id' => $adminA->id,
        'program_studi_id' => $prodiA->id,
    ]);

    AdminProfile::query()->create([
        'user_id' => $adminB->id,
        'program_studi_id' => $prodiB->id,
    ]);

    $announcement = SystemAnnouncement::query()->create([
        'title' => 'Pengumuman Admin Global',
        'body' => 'Mohon cek panel admin untuk pembaruan sistem.',
        'target_roles' => ['admin', 'super_admin'],
        'program_studi_id' => null,
        'status' => SystemAnnouncement::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $superAdmin->id,
        'updated_by' => $superAdmin->id,
    ]);

    $sentCount = app(SystemAnnouncementService::class)->publish($announcement);

    expect($sentCount)->toBe(3)
        ->and($announcement->fresh()->notified_at)->not->toBeNull();

    Notification::assertSentTo($superAdmin, RealtimeNotification::class);
    Notification::assertSentTo($adminA, RealtimeNotification::class);
    Notification::assertSentTo($adminB, RealtimeNotification::class);
    Notification::assertNothingSentTo($studentA);
});
