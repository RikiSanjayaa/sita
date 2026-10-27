<?php

use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\PhoneVisibility;

it('hides phone numbers and whatsapp links until their owner opts in', function (): void {
    $owner = User::factory()->asMahasiswa()->create(['phone_number' => '081234567890']);
    $viewer = User::factory()->asMahasiswa()->create();
    $url = route('users.profile.show', $owner);

    $page = $this->actingAs($viewer)->get($url)->assertOk()->viewData('page');
    expect(data_get($page, 'props.profile.phoneNumber'))->toBeNull()
        ->and(data_get($page, 'props.profile.whatsappUrl'))->toBeNull()
        ->and(json_encode(data_get($page, 'props.profile.meta')))->not->toContain('081234567890');

    $owner->update(['phone_public' => true]);
    $page = $this->get($url)->assertOk()->viewData('page');
    expect(data_get($page, 'props.profile.phoneNumber'))->toBe('081234567890')
        ->and(data_get($page, 'props.profile.whatsappUrl'))->toBe('https://wa.me/6281234567890');
    expect($owner->toArray())->not->toHaveKey('phone_number');
});

it('allows historical supervisors and examiners while preserving secret sidang examiners', function (): void {
    $student = User::factory()->asMahasiswa()->create();
    $supervisor = User::factory()->asDosen()->create();
    $examiner = User::factory()->asDosen()->create();
    $stranger = User::factory()->asDosen()->create();
    $project = ThesisProject::factory()->create(['student_user_id' => $student->id]);
    ThesisSupervisorAssignment::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $supervisor->id, 'role' => 'primary', 'status' => 'ended']);
    $admin = User::factory()->asAdmin()->create();
    $kaprodi = User::factory()->asKaprodi()->create();
    $defense = ThesisDefense::query()->create(['project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'scheduled']);
    ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $examiner->id, 'role' => 'examiner', 'order_no' => 1]);
    $visibility = app(PhoneVisibility::class);

    expect($visibility->canView($student, $student))->toBeTrue()
        ->and($visibility->canView($student, $supervisor))->toBeTrue()
        ->and($visibility->canView($supervisor, $student))->toBeTrue()
        ->and($visibility->canView($student, $examiner))->toBeTrue()
        ->and($visibility->canView($examiner, $student))->toBeFalse()
        ->and($visibility->canView($student, $stranger))->toBeFalse()
        ->and($visibility->canView($student, null))->toBeFalse()
        ->and($visibility->canView($student, $admin))->toBeTrue()
        ->and($visibility->canView($student, $kaprodi))->toBeTrue();

    $defense->update(['status' => 'completed']);
    expect($visibility->canView($examiner, $student))->toBeTrue();
});

it('persists the phone visibility choice in profile settings', function (): void {
    $user = User::factory()->asMahasiswa()->create();
    expect($user->fresh()->phone_public)->toBeFalse();

    foreach ([true, false] as $choice) {
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '',
            'phone_public' => $choice,
        ])->assertSessionHasNoErrors();
        expect($user->fresh()->phone_public)->toBe($choice);
    }
});
