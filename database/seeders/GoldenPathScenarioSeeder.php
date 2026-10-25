<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Enums\DegreeLevel;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * The one fresh student of tests/e2e/specs/golden-path.spec.ts: a confirmed,
 * active mahasiswa of Ilmu Komputer with no thesis project at all. The spec
 * walks this student from the title proposal to graduation through the UI, so
 * every run first deletes whatever an earlier run left behind. Only the Playwright
 * E2E specs call this seeder; it is not part of DatabaseSeeder. Keep the email
 * in sync with `goldenPathStudent` in tests/e2e/support/golden-path.ts.
 */
class GoldenPathScenarioSeeder extends Seeder
{
    public const EMAIL = 'e2e-golden@sita.test';

    public function run(): void
    {
        self::cleanup();

        $prodi = ProgramStudi::query()->where('slug', 'ilkom')->firstOrFail();

        $role = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);

        $student = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Gita Alur Emas',
                'phone_number' => '081200000039',
                'phone_number_confirmed_at' => now(),
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'last_active_role' => AppRole::Mahasiswa->value,
            ],
        );
        $student->roles()->syncWithoutDetaching([$role->id]);

        MahasiswaProfile::query()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'nim' => '2210511039',
                'program_studi_id' => $prodi->id,
                'degree_level' => DegreeLevel::S1->value,
                'concentration' => 'Sistem Cerdas',
                'angkatan' => 2022,
                'is_active' => true,
                'is_obe_curriculum' => false,
            ],
        );
    }

    /**
     * Thesis projects cascade to titles, defenses, revisions and supervisor
     * assignments. Mentorship rows belong to the student, so they go separately.
     */
    private function clearStudentData(User $student): void
    {
        ThesisProject::query()->where('student_user_id', $student->id)->delete();

        MentorshipChatThread::query()->where('student_user_id', $student->id)->get()->each(function (MentorshipChatThread $thread): void {
            $thread->messages()->delete();
            $thread->delete();
        });
        MentorshipSchedule::query()->where('student_user_id', $student->id)->delete();
        MentorshipDocument::query()->where('student_user_id', $student->id)->delete();
        MentorshipAssignment::query()->where('student_user_id', $student->id)->delete();
    }

    /**
     * Removes the owned student, academic records, notifications and uploads.
     * Other users and their files are not deleted. Staff may retain the
     * workflow notifications they received during the rehearsal.
     */
    public static function cleanup(): void
    {
        $student = User::query()->where('email', self::EMAIL)->first();

        if (! $student instanceof User) {
            return;
        }

        $files = collect();

        $projectIds = ThesisProject::query()->where('student_user_id', $student->id)->pluck('id');
        ThesisDocument::query()->whereIn('project_id', $projectIds)->get()
            ->each(fn (ThesisDocument $document) => $files->push([$document->storage_disk, $document->storage_path]));
        MentorshipDocument::query()->where('student_user_id', $student->id)->get()
            ->each(fn (MentorshipDocument $document) => $files->push([$document->storage_disk, $document->storage_path]));
        MentorshipChatThread::query()->where('student_user_id', $student->id)->get()
            ->each(function (MentorshipChatThread $thread) use ($files): void {
                $thread->messages()->get()->each(fn ($message) => $files->push([$message->attachment_disk, $message->attachment_path]));
            });

        DB::transaction(function () use ($student): void {
            (new self)->clearStudentData($student);
            DB::table('notifications')
                ->where('notifiable_type', $student->getMorphClass())
                ->where('notifiable_id', $student->id)
                ->delete();
            $student->roles()->detach();
            MahasiswaProfile::query()->where('user_id', $student->id)->delete();
            $student->delete();
        });

        $files
            ->filter(fn (array $file): bool => filled($file[0]) && filled($file[1]))
            ->unique(fn (array $file): string => $file[0].':'.$file[1])
            ->each(fn (array $file) => Storage::disk($file[0])->delete($file[1]));
    }
}
