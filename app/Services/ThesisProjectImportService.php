<?php

namespace App\Services;

use App\Models\DosenProfile;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\SimpleXlsxReader;
use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Imports thesis projects that were already running before SITA was used.
 *
 * Every row is validated first. When any row fails nothing is saved, so the
 * admin can fix the file and upload it again without creating duplicates.
 */
class ThesisProjectImportService
{
    public const IMPORT_NOTE = 'Data import: dilaksanakan sebelum SITA';

    private const REQUIRED_COLUMNS = ['nim', 'judul', 'tahap'];

    private const STAGES_AFTER_PROPOSAL = ['research', 'sidang', 'completed'];

    /** @var array<int, array<int, int>> lecturer user id => student ids already counted in the file */
    private array $pendingSupervisionLoad = [];

    /** @var array<int, Collection<int, int>> */
    private array $existingSupervisionLoad = [];

    public function __construct(
        private readonly SimpleXlsxReader $xlsxReader,
        private readonly ThesisProjectAdminService $adminService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    /**
     * @param  array{notify?: bool|null}  $options
     * @return array{processed: int, imported: int, failed: int, failures: array<int, string>}
     */
    public function import(UploadedFile $file, array $options, User $actor): array
    {
        $rows = $this->xlsxReader->rows($file->getRealPath());

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['File Excel kosong atau tidak memiliki data yang dapat dibaca.'],
            ]);
        }

        $header = array_map(fn(string $value): string => $this->normalizeHeader($value), array_shift($rows));
        $missingColumns = array_values(array_diff(self::REQUIRED_COLUMNS, $header));

        if ($missingColumns !== []) {
            throw ValidationException::withMessages([
                'file' => ['Kolom wajib tidak ditemukan pada baris header: '.implode(', ', $missingColumns).'.'],
            ]);
        }

        $this->pendingSupervisionLoad = [];
        $this->existingSupervisionLoad = [];

        $processed = 0;
        $failures = [];
        $plans = [];
        $seenNims = [];

        foreach ($rows as $rowIndex => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $processed++;
            $lineNumber = $rowIndex + 2;
            $errors = [];

            $plan = $this->planRow($this->rawRow($header, $row), $lineNumber, $actor, $seenNims, $errors);

            if ($errors !== []) {
                $failures[] = "Baris {$lineNumber}: ".implode(' ', $errors);

                continue;
            }

            $plans[] = $plan;
        }

        if ($processed === 0) {
            throw ValidationException::withMessages([
                'file' => ['File Excel tidak memiliki baris data di bawah header.'],
            ]);
        }

        if ($failures !== []) {
            return $this->result($processed, 0, $failures);
        }

        try {
            $projects = DB::transaction(function () use ($plans, $actor): array {
                $created = [];

                foreach ($plans as $plan) {
                    try {
                        $created[] = $this->persist($plan, $actor);
                    } catch (RuntimeException $exception) {
                        throw new RuntimeException("Baris {$plan['line']}: {$exception->getMessage()}", previous: $exception);
                    }
                }

                return $created;
            });
        } catch (RuntimeException $exception) {
            return $this->result($processed, 0, [$exception->getMessage()]);
        }

        if ((bool) ($options['notify'] ?? false)) {
            foreach ($projects as $project) {
                $this->notifyParticipants($project);
            }
        }

        return $this->result($processed, count($projects), []);
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, int>  $seenNims
     * @param  array<int, string>  $errors
     * @return array{line: int, student: User, title: string, phase: string, primary: ?User, secondary: ?User, ticket_start: ?CarbonImmutable}|null
     */
    private function planRow(array $raw, int $lineNumber, User $actor, array &$seenNims, array &$errors): ?array
    {
        $nim = $raw['nim'] ?? '';
        $title = $raw['judul'] ?? '';
        $stageInput = $raw['tahap'] ?? '';
        $primaryNik = $raw['pembimbing_1_nik'] ?? '';
        $secondaryNik = $raw['pembimbing_2_nik'] ?? '';
        $ticketInput = $raw['tanggal_mulai_tiket'] ?? '';

        $student = null;
        $terms = AcademicTerminology::neutral();

        if ($nim === '') {
            $errors[] = 'NIM wajib diisi.';
        } elseif (isset($seenNims[$nim])) {
            $errors[] = "NIM {$nim} muncul lebih dari sekali pada file (pertama di baris {$seenNims[$nim]}).";
        } else {
            $seenNims[$nim] = $lineNumber;
            $student = User::query()
                ->with('mahasiswaProfile')
                ->whereHas('mahasiswaProfile', fn($query) => $query->where('nim', $nim))
                ->first();

            if (! $student instanceof User) {
                $errors[] = "NIM {$nim} tidak ditemukan. Import user mahasiswa lebih dulu.";
            }
        }

        $programStudiId = $student?->mahasiswaProfile?->program_studi_id;

        if ($student instanceof User) {
            $terms = AcademicTerminology::forStudent($student);
            $adminProgramStudiId = $actor->adminProgramStudiId();

            if ($programStudiId === null) {
                $errors[] = "NIM {$nim} belum memiliki program studi.";
                $student = null;
            } elseif ($adminProgramStudiId !== null && $programStudiId !== $adminProgramStudiId) {
                $errors[] = "NIM {$nim} bukan mahasiswa program studi Anda.";
                $student = null;
            }
        }

        if ($title === '') {
            $errors[] = 'Judul wajib diisi.';
        } elseif (mb_strlen($title) > 255) {
            $errors[] = 'Judul maksimal 255 karakter.';
        }

        $phase = $this->parseStage($stageInput);

        if ($stageInput === '') {
            $errors[] = 'Tahap wajib diisi.';
        } elseif ($phase === null) {
            $errors[] = "Tahap '{$stageInput}' tidak dikenal. Gunakan salah satu: ".implode(', ', AcademicTerminology::phaseLabels()).'.';
        }

        $ticketStart = null;

        if ($ticketInput !== '') {
            $ticketStart = $this->parseDate($ticketInput);

            if ($ticketStart === null) {
                $errors[] = "Tanggal mulai tiket '{$ticketInput}' tidak valid. Gunakan format YYYY-MM-DD.";
            }
        }

        if ($student instanceof User) {
            $existingError = $this->existingProjectError($student, $title, $terms);

            if ($existingError !== null) {
                $errors[] = $existingError;
            }
        }

        [$primary, $secondary] = $this->resolveSupervisors(
            primaryNik: $primaryNik,
            secondaryNik: $secondaryNik,
            phase: $phase,
            programStudiId: $programStudiId,
            errors: $errors,
        );

        if ($errors === [] && $student instanceof User && $phase !== null && $phase !== 'completed') {
            $this->reserveSupervisionLoad($student, [$primary, $secondary], $errors);
        }

        if ($errors !== [] || ! $student instanceof User || $phase === null) {
            return null;
        }

        return [
            'line' => $lineNumber,
            'student' => $student,
            'title' => $title,
            'phase' => $phase,
            'primary' => $primary,
            'secondary' => $secondary,
            'ticket_start' => $ticketStart,
        ];
    }

    /**
     * @param  array<string, string>  $terms
     */
    private function existingProjectError(User $student, string $title, array $terms): ?string
    {
        $finalWork = $terms['finalWork'];

        $hasActiveProject = ThesisProject::query()
            ->current()
            ->where('student_user_id', $student->id)
            ->where('state', 'active')
            ->exists();

        if ($hasActiveProject) {
            return "Mahasiswa sudah memiliki {$finalWork} aktif.";
        }

        if ($title === '') {
            return null;
        }

        $hasSameTitle = ThesisProjectTitle::query()
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', '!=', 'cancelled'))
            ->whereRaw('LOWER(title_id) = ?', [mb_strtolower($title)])
            ->exists();

        return $hasSameTitle
            ? "Mahasiswa sudah memiliki {$finalWork} dengan judul yang sama (kemungkinan file ini sudah pernah diimpor)."
            : null;
    }

    /**
     * @param  array<int, string>  $errors
     * @return array{0: ?User, 1: ?User}
     */
    private function resolveSupervisors(
        string $primaryNik,
        string $secondaryNik,
        ?string $phase,
        ?int $programStudiId,
        array &$errors,
    ): array {
        if ($primaryNik === '' && $secondaryNik !== '') {
            $errors[] = 'Pembimbing 2 tidak boleh diisi tanpa Pembimbing 1.';

            return [null, null];
        }

        if ($primaryNik === '') {
            if ($phase !== null && $phase !== 'title_review') {
                $errors[] = 'Pembimbing 1 wajib diisi kecuali pada tahap '.AcademicTerminology::phaseLabel('title_review').'.';
            }

            return [null, null];
        }

        if ($primaryNik === $secondaryNik) {
            $errors[] = 'Pembimbing 1 dan Pembimbing 2 harus berbeda.';

            return [null, null];
        }

        $primary = $this->lecturerByNik($primaryNik, 'Pembimbing 1', $programStudiId, $errors);
        $secondary = $secondaryNik === ''
            ? null
            : $this->lecturerByNik($secondaryNik, 'Pembimbing 2', $programStudiId, $errors);

        return [$primary, $secondary];
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function lecturerByNik(string $nik, string $label, ?int $programStudiId, array &$errors): ?User
    {
        $profile = DosenProfile::query()
            ->with('user')
            ->where('nik', $nik)
            ->first();
        $lecturer = $profile?->user;

        if (! $lecturer instanceof User || ! $lecturer->hasRole('dosen')) {
            $errors[] = "{$label}: NIK {$nik} tidak ditemukan.";

            return null;
        }

        if (! $profile->is_active) {
            $errors[] = "{$label}: dosen dengan NIK {$nik} tidak aktif.";

            return null;
        }

        if (! $lecturer->teachesInProgramStudi($programStudiId)) {
            $errors[] = "{$label}: dosen dengan NIK {$nik} tidak mengajar di program studi mahasiswa.";

            return null;
        }

        return $lecturer;
    }

    /**
     * Mirrors the quota rule of ThesisProjectAdminService::assignSupervisors so
     * a file that would overflow a lecturer is rejected before anything is saved.
     *
     * @param  array<int, ?User>  $lecturers
     * @param  array<int, string>  $errors
     */
    private function reserveSupervisionLoad(User $student, array $lecturers, array &$errors): void
    {
        $reservations = [];

        foreach (array_filter($lecturers) as $lecturer) {
            $lecturer->loadMissing('dosenProfile');
            $quota = max(1, (int) ($lecturer->dosenProfile?->supervision_quota ?? 14));
            $existing = $this->existingSupervisionLoad[$lecturer->id] ??= $this->activeStudentIdsForLecturer($lecturer->id);
            $pending = $this->pendingSupervisionLoad[$lecturer->id] ?? [];
            $load = $existing->merge($pending)->unique()->count();

            if ($load >= $quota) {
                $errors[] = "{$lecturer->name} sudah mencapai kuota bimbingan ({$quota} mahasiswa aktif).";

                continue;
            }

            $reservations[$lecturer->id] = true;
        }

        if ($errors !== []) {
            return;
        }

        foreach (array_keys($reservations) as $lecturerId) {
            $this->pendingSupervisionLoad[$lecturerId][] = $student->id;
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function activeStudentIdsForLecturer(int $lecturerUserId): Collection
    {
        return ThesisSupervisorAssignment::query()
            ->with('project')
            ->where('lecturer_user_id', $lecturerUserId)
            ->where('status', 'active')
            ->whereHas('project', fn($query) => $query->where('state', 'active'))
            ->get()
            ->toBase()
            ->map(fn(ThesisSupervisorAssignment $assignment): ?int => $assignment->project?->student_user_id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  array{line: int, student: User, title: string, phase: string, primary: ?User, secondary: ?User, ticket_start: ?CarbonImmutable}  $plan
     */
    private function persist(array $plan, User $actor): ThesisProject
    {
        $student = $plan['student'];
        $phase = $plan['phase'];
        $ticketStart = $plan['ticket_start'];
        $isCompleted = $phase === 'completed';
        $isTitleReview = $phase === 'title_review';
        $terms = AcademicTerminology::forStudent($student);
        $now = now();

        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $student->mahasiswaProfile->program_studi_id,
            'phase' => $phase,
            'state' => 'active',
            'started_at' => $now,
            'created_by' => $actor->id,
            'mentorship_valid_from' => $ticketStart?->toDateString(),
            'mentorship_valid_until' => $ticketStart?->addMonthsNoOverflow(config('sita.thesis_ticket_months', 12))->toDateString(),
        ]);

        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => $plan['title'],
            'title_en' => '-',
            'proposal_summary' => '-',
            'status' => $isTitleReview ? 'submitted' : 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => $now,
            'decided_by_user_id' => $isTitleReview ? null : $actor->id,
            'decided_at' => $isTitleReview ? null : $now,
            'decision_notes' => $isTitleReview ? null : self::IMPORT_NOTE,
        ]);

        if ($plan['primary'] instanceof User) {
            $this->adminService->assignSupervisors(
                project: $project,
                assignedBy: $actor->id,
                primaryLecturerUserId: $plan['primary']->id,
                secondaryLecturerUserId: $plan['secondary']?->id,
                notes: self::IMPORT_NOTE,
                silent: true,
                enforceQuota: ! $isCompleted,
            );
        }

        if (in_array($phase, self::STAGES_AFTER_PROPOSAL, true)) {
            $this->createPlaceholderDefense($project, $title, 'sempro', $actor);
        }

        if ($isCompleted) {
            $this->createPlaceholderDefense($project, $title, 'sidang', $actor);
            $project->supervisorAssignments()
                ->where('status', 'active')
                ->update(['status' => 'ended', 'ended_at' => $now]);
        }

        $finalState = ['phase' => $phase];

        if ($isCompleted) {
            $finalState += [
                'state' => 'completed',
                'completed_at' => $now,
                'closed_by' => $actor->id,
            ];

            if ($ticketStart === null) {
                $finalState += ['mentorship_valid_from' => null, 'mentorship_valid_until' => null];
            }
        }

        $project = $project->fresh();
        $project->forceFill($finalState)->save();

        ThesisProjectEvent::query()->create([
            'project_id' => $project->id,
            'actor_user_id' => $actor->id,
            'event_type' => 'project_imported',
            'label' => $terms['finalWork'].' diimpor',
            'description' => 'Data lama diimpor pada tahap '.AcademicTerminology::phaseLabel($phase).'.',
            'payload' => [
                'phase' => $phase,
                'state' => $project->state,
                'title' => $plan['title'],
                'primary_supervisor_user_id' => $plan['primary']?->id,
                'secondary_supervisor_user_id' => $plan['secondary']?->id,
            ],
            'occurred_at' => $now->toDateTimeString(),
        ]);

        return $project->fresh(['student', 'activeSupervisorAssignments.lecturer']);
    }

    private function createPlaceholderDefense(ThesisProject $project, ThesisProjectTitle $title, string $type, User $actor): void
    {
        ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => $type,
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'created_by' => $actor->id,
            'decided_by' => $actor->id,
            'decision_at' => now(),
            'notes' => self::IMPORT_NOTE,
        ]);
    }

    private function notifyParticipants(ThesisProject $project): void
    {
        $student = $project->student;

        if (! $student instanceof User) {
            return;
        }

        $terms = AcademicTerminology::forStudent($student);
        $phaseLabel = AcademicTerminology::phaseLabel($project->phase);

        $this->realtimeNotificationService->notifyUser($student, 'statusTugasAkhir', [
            'title' => $terms['finalWork'].' Anda tercatat di SITA',
            'description' => "Admin mencatat {$terms['finalWorkLower']} Anda pada tahap {$phaseLabel}.",
            'url' => '/tugas-akhir',
            'icon' => 'bell',
            'createdAt' => now()->toIso8601String(),
        ]);

        foreach ($project->activeSupervisorAssignments as $assignment) {
            if (! $assignment->lecturer instanceof User) {
                continue;
            }

            $this->realtimeNotificationService->notifyUser($assignment->lecturer, 'statusTugasAkhir', [
                'title' => 'Mahasiswa bimbingan baru',
                'description' => "{$student->name} dicatat sebagai mahasiswa bimbingan Anda pada tahap {$phaseLabel}.",
                'url' => '/dosen/mahasiswa-bimbingan',
                'icon' => 'bell',
                'createdAt' => now()->toIso8601String(),
            ]);
        }
    }

    private function parseStage(string $value): ?string
    {
        $normalized = $this->normalizeLookupKey($value);

        if ($normalized === '') {
            return null;
        }

        foreach (AcademicTerminology::phaseLabels() as $phase => $label) {
            if ($normalized === $this->normalizeLookupKey($phase) || $normalized === $this->normalizeLookupKey($label)) {
                return $phase;
            }
        }

        return null;
    }

    /**
     * Accepts YYYY-MM-DD and the day serial Excel stores for date cells.
     */
    private function parseDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, WitaDateTime::TIMEZONE);

            return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $date : null;
        }

        if (preg_match('/^\d{5}(\.\d+)?$/', $value) === 1) {
            return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, WitaDateTime::TIMEZONE)->addDays((int) $value);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function rawRow(array $header, array $row): array
    {
        $raw = [];

        foreach ($header as $index => $column) {
            $raw[$column] = trim((string) ($row[$index] ?? ''));
        }

        return $raw;
    }

    /**
     * @param  array<int, string>  $failures
     * @return array{processed: int, imported: int, failed: int, failures: array<int, string>}
     */
    private function result(int $processed, int $imported, array $failures): array
    {
        return [
            'processed' => $processed,
            'imported' => $imported,
            'failed' => count($failures),
            'failures' => $failures,
        ];
    }

    private function normalizeLookupKey(string $value): string
    {
        return str($value)->lower()->replace(['_', '-'], ' ')->squish()->toString();
    }

    /**
     * @param  array<int, string>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return collect($row)
            ->map(fn(string $value): string => trim($value))
            ->filter()
            ->isEmpty();
    }

    private function normalizeHeader(string $header): string
    {
        return str($header)
            ->lower()
            ->replace([' ', '-'], '_')
            ->replace('.', '')
            ->toString();
    }
}
