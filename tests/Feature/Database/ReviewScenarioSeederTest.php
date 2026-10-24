<?php

use App\Models\ThesisProject;
use App\Models\User;
use App\Services\ThesisProjectImportService;
use Database\Seeders\ReviewScenarioSeeder;
use Database\Seeders\ThesisWorkflowSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;

function reviewScenarioCounts(): array
{
    return [
        'users' => User::query()->count(),
        'projects' => ThesisProject::query()->count(),
        'defenses' => DB::table('thesis_defenses')->count(),
        'assignments' => DB::table('thesis_supervisor_assignments')->count(),
        'events' => DB::table('thesis_project_events')->count(),
    ];
}

beforeEach(function (): void {
    $this->seed(UserSeeder::class);
    $this->seed(ThesisWorkflowSeeder::class);
});

it('is idempotent', function (): void {
    $this->seed(ReviewScenarioSeeder::class);
    $first = reviewScenarioCounts();

    $this->seed(ReviewScenarioSeeder::class);

    expect(reviewScenarioCounts())->toBe($first);
});

it('seeds a valid, an expiring and an expired ticket from the configured ticket length', function (): void {
    $this->seed(ReviewScenarioSeeder::class);
    $months = (int) config('sita.thesis_ticket_months');

    $project = fn (string $email): ThesisProject => ThesisProject::query()
        ->whereHas('student', fn ($query) => $query->where('email', $email))
        ->with('activeSupervisorAssignments')
        ->firstOrFail();

    $valid = $project('dewi.anggraini@sita.test');
    $expiring = $project('fajar.ramadhan@sita.test');
    $expired = $project('hendra.gunawan@sita.test');

    expect(today()->diffInDays($valid->mentorship_valid_until))->toBeGreaterThan(280)
        ->and($expiring->mentorship_valid_until->isBetween(today(), today()->addDays(14)))->toBeTrue()
        ->and($expired->mentorship_valid_until->toDateString())->toBe(today()->subDay()->toDateString())
        ->and($expired->isMentorshipSuspended())->toBeTrue()
        ->and($valid->isMentorshipSuspended())->toBeFalse()
        ->and($expiring->isMentorshipSuspended())->toBeFalse();

    foreach ([$valid, $expiring, $expired] as $each) {
        expect($each->state)->toBe('active')
            ->and($each->activeSupervisorAssignments)->not->toBeEmpty()
            ->and($each->mentorship_valid_from->copy()->addMonthsNoOverflow($months)->toDateString())->toBe($each->mentorship_valid_until->toDateString());
    }
});

it('leaves the three import students without a project and imports the sample file as-is', function (): void {
    $this->seed(ReviewScenarioSeeder::class);

    $nims = ['2210510111', '2210510112', '2210510113'];
    $students = User::query()->whereHas('mahasiswaProfile', fn ($query) => $query->whereIn('nim', $nims))->get();
    expect($students)->toHaveCount(3)
        ->and(ThesisProject::query()->whereIn('student_user_id', $students->modelKeys())->count())->toBe(0);

    $sample = base_path('docs/samples/contoh-import-tugas-akhir.xlsx');
    $file = new UploadedFile($sample, 'contoh-import-tugas-akhir.xlsx', null, null, true);
    $result = app(ThesisProjectImportService::class)->import($file, [], User::query()->where('email', 'admin@sita.test')->firstOrFail());

    expect($result['failures'])->toBe([])
        ->and($result['imported'])->toBe(3)
        ->and(ThesisProject::query()->whereIn('student_user_id', $students->modelKeys())->pluck('phase')->sort()->values()->all())
        ->toBe(['research', 'sidang', 'title_review']);

    // A second run keeps the imported projects and does not give the students new ones.
    $this->seed(ReviewScenarioSeeder::class);
    expect(ThesisProject::query()->whereIn('student_user_id', $students->modelKeys())->count())->toBe(3);
});

it('corrects laila only when her old attempt still has the completed shape', function (): void {
    $old = ThesisProject::query()
        ->whereHas('student', fn ($query) => $query->where('email', 'laila@sita.test'))
        ->orderBy('started_at')->firstOrFail();
    $others = ThesisProject::query()->where('id', '!=', $old->id)->pluck('state', 'id')->all();

    // Rebuild the pre-fix shape: completed, with a sidang and a final manuscript.
    $old->forceFill(['state' => 'completed', 'completed_at' => now()->subYear(), 'cancelled_at' => null])->save();
    $sidang = $old->defenses()->create([
        'title_version_id' => $old->titles()->value('id'), 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'completed',
        'result' => 'pass', 'scheduled_for' => now()->subYear(), 'location' => 'B', 'mode' => 'offline',
    ]);
    $old->documents()->create([
        'title_version_id' => $old->titles()->value('id'), 'defense_id' => $sidang->id, 'kind' => 'final_manuscript', 'version_no' => 1,
        'status' => 'active', 'title' => 'Naskah Akhir', 'storage_disk' => 'public', 'storage_path' => 'x.pdf',
        'stored_file_name' => 'x.pdf', 'file_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'file_size_kb' => 1, 'uploaded_at' => now(),
    ]);

    $this->seed(ReviewScenarioSeeder::class);

    $old->refresh();
    expect($old->state)->toBe('cancelled')
        ->and($old->completed_at)->toBeNull()
        ->and($old->cancelled_at)->not->toBeNull()
        ->and($old->defenses()->where('type', 'sidang')->exists())->toBeFalse()
        ->and($old->defenses()->where('type', 'sempro')->exists())->toBeTrue()
        ->and($old->documents()->where('kind', 'final_manuscript')->exists())->toBeFalse()
        ->and(ThesisProject::query()->whereIn('id', array_keys($others))->pluck('state', 'id')->all())->toEqual($others);
});
