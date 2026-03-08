<?php

use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipSchedule;
use App\Models\ThesisProject;
use Database\Seeders\DatabaseSeeder;

test('database seeder skips generic mentorship assignments for thesis-seeded students', function (): void {
    /** @var \Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $bagasProject = ThesisProject::query()
        ->whereHas('student', fn($query) => $query->where('email', 'bagas@sita.test'))
        ->exists();

    $bagasMentorshipAssignments = MentorshipAssignment::query()
        ->whereHas('student', fn($query) => $query->where('email', 'bagas@sita.test'))
        ->count();

    $farhanMentorshipAssignments = MentorshipAssignment::query()
        ->whereHas('student', fn($query) => $query->where('email', 'farhan@sita.test'))
        ->count();

    $mahasiswaThreadExists = MentorshipChatThread::query()
        ->whereHas('student', fn($query) => $query->where('email', 'mahasiswa@sita.test'))
        ->where('type', 'pembimbing')
        ->exists();

    $mahasiswaSchedulesCount = MentorshipSchedule::query()
        ->whereHas('student', fn($query) => $query->where('email', 'mahasiswa@sita.test'))
        ->whereHas('lecturer', fn($query) => $query->where('email', 'dosen@sita.test'))
        ->count();

    expect($bagasProject)->toBeTrue()
        ->and($bagasMentorshipAssignments)->toBe(0)
        ->and($farhanMentorshipAssignments)->toBe(1)
        ->and($mahasiswaThreadExists)->toBeTrue()
        ->and($mahasiswaSchedulesCount)->toBeGreaterThanOrEqual(2);
});
