<?php

use App\Filament\Resources\SystemAnnouncements\Pages\CreateSystemAnnouncement;
use App\Filament\Resources\SystemAnnouncements\Pages\ListSystemAnnouncements;
use App\Filament\Resources\SystemAnnouncements\SystemAnnouncementResource;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\Faculty;
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

test('announcement action url only accepts internal SITA paths', function (): void {
    $programStudi = ProgramStudi::factory()->create();
    $admin = User::factory()->asAdmin()->create();

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $programStudi->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateSystemAnnouncement::class)
        ->fillForm([
            'title' => 'Informasi Beasiswa',
            'body' => 'Baca detail di https://example.com/beasiswa.',
            'target_roles' => ['mahasiswa'],
            'status' => SystemAnnouncement::STATUS_DRAFT,
            'action_url' => 'https://example.com/beasiswa',
        ])
        ->call('create')
        ->assertHasFormErrors(['action_url']);

    Livewire::test(CreateSystemAnnouncement::class)
        ->fillForm([
            'title' => 'Informasi Dashboard',
            'body' => 'Silakan periksa dashboard Anda.',
            'target_roles' => ['mahasiswa'],
            'status' => SystemAnnouncement::STATUS_DRAFT,
            'action_url' => '/dashboard',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
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

test('super admin can target multiple faculties', function (): void {
    Notification::fake();

    $facultyA = Faculty::factory()->create(['name' => 'Fakultas A']);
    $facultyB = Faculty::factory()->create(['name' => 'Fakultas B']);
    $facultyC = Faculty::factory()->create(['name' => 'Fakultas C']);
    $prodiA1 = ProgramStudi::factory()->create(['faculty_id' => $facultyA->id, 'name' => 'Prodi A1']);
    $prodiA2 = ProgramStudi::factory()->create(['faculty_id' => $facultyA->id, 'name' => 'Prodi A2']);
    $prodiB = ProgramStudi::factory()->create(['faculty_id' => $facultyB->id, 'name' => 'Prodi B']);
    $prodiC = ProgramStudi::factory()->create(['faculty_id' => $facultyC->id, 'name' => 'Prodi C']);
    $studentA1 = createAnnouncementStudent($prodiA1);
    $studentA2 = createAnnouncementStudent($prodiA2);
    $studentB = createAnnouncementStudent($prodiB);
    $studentC = createAnnouncementStudent($prodiC);
    $superAdmin = User::factory()->asSuperAdmin()->create();

    $this->actingAs($superAdmin);

    Livewire::test(CreateSystemAnnouncement::class)
        ->fillForm([
            'title' => 'Pengumuman Fakultas A',
            'body' => 'Khusus seluruh prodi di Fakultas A.',
            'target_roles' => ['mahasiswa'],
            'target_scope' => SystemAnnouncement::TARGET_FACULTIES,
            'target_faculty_ids' => [$facultyA->id, $facultyB->id],
            'status' => SystemAnnouncement::STATUS_PUBLISHED,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $announcement = SystemAnnouncement::query()->latest('id')->firstOrFail();

    expect($announcement->program_studi_id)->toBeNull()
        ->and($announcement->target_scope)->toBe(SystemAnnouncement::TARGET_FACULTIES)
        ->and($announcement->target_faculty_ids)->toEqualCanonicalizing([$facultyA->id, $facultyB->id]);

    Notification::assertSentTo($studentA1, RealtimeNotification::class);
    Notification::assertSentTo($studentA2, RealtimeNotification::class);
    Notification::assertSentTo($studentB, RealtimeNotification::class);
    Notification::assertNothingSentTo($studentC);
});

test('super admin can target selected programs across faculties', function (): void {
    Notification::fake();

    $facultyA = Faculty::factory()->create(['name' => 'Fakultas A']);
    $facultyB = Faculty::factory()->create(['name' => 'Fakultas B']);
    $prodiA1 = ProgramStudi::factory()->create(['faculty_id' => $facultyA->id, 'name' => 'Prodi A1']);
    $prodiA2 = ProgramStudi::factory()->create(['faculty_id' => $facultyA->id, 'name' => 'Prodi A2']);
    $prodiB = ProgramStudi::factory()->create(['faculty_id' => $facultyB->id, 'name' => 'Prodi B']);
    $studentA1 = createAnnouncementStudent($prodiA1);
    $studentA2 = createAnnouncementStudent($prodiA2);
    $studentB = createAnnouncementStudent($prodiB);
    $superAdmin = User::factory()->asSuperAdmin()->create();

    $this->actingAs($superAdmin);

    Livewire::test(CreateSystemAnnouncement::class)
        ->fillForm([
            'title' => 'Pengumuman Prodi Pilihan',
            'body' => 'Khusus beberapa prodi.',
            'target_roles' => ['mahasiswa'],
            'target_scope' => SystemAnnouncement::TARGET_PROGRAMS,
            'target_program_studi_ids' => [$prodiA1->id, $prodiB->id],
            'status' => SystemAnnouncement::STATUS_PUBLISHED,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $announcement = SystemAnnouncement::query()->latest('id')->firstOrFail();

    expect($announcement->program_studi_id)->toBeNull()
        ->and($announcement->target_scope)->toBe(SystemAnnouncement::TARGET_PROGRAMS)
        ->and($announcement->target_program_studi_ids)->toEqualCanonicalizing([$prodiA1->id, $prodiB->id]);

    Notification::assertSentTo($studentA1, RealtimeNotification::class);
    Notification::assertNothingSentTo($studentA2);
    Notification::assertSentTo($studentB, RealtimeNotification::class);
});
