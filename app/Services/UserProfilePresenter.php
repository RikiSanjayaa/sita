<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\DegreeLevel;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class UserProfilePresenter
{
    public function __construct(
        private readonly MentorshipProgressService $mentorshipProgressService,
        private readonly StudentProgressSnapshot $studentProgressSnapshot,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function summary(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $user->loadMissing([
            'roles',
            'adminProfile.programStudi',
            'dosenProfile.programStudi',
            'expertiseFields',
            'activeDosenProgramStudiAssignments.programStudi',
            'kaprodiAssignment.programStudi',
            'mahasiswaProfile.programStudi',
        ]);

        $roleKey = $this->primaryRoleKey($user);
        $programStudi = $this->programStudiName($user, $roleKey);
        $concentration = $this->concentration($user, $roleKey);
        $degreeLevel = $roleKey === AppRole::Mahasiswa->value
            ? DegreeLevel::tryFrom((string) $user->mahasiswaProfile?->degree_level)?->label()
            : null;
        $expertiseFields = $roleKey === AppRole::Dosen->value
            ? $user->expertiseFields->pluck('name')->sort()->values()->all()
            : [];
        $phoneNumber = app(PhoneVisibility::class)->canView($user, auth()->user()) ? $user->phone_number : null;

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phoneNumber' => $phoneNumber,
            'whatsappUrl' => $this->whatsappUrl($phoneNumber),
            'avatar' => $user->avatar,
            'profileUrl' => route('users.profile.show', ['user' => $user->getKey()]),
            'roleKey' => $roleKey,
            'roleLabel' => $this->roleLabel($roleKey),
            'programStudi' => $programStudi,
            'degreeLevel' => $degreeLevel,
            'concentration' => $concentration,
            'expertiseFields' => $expertiseFields,
            'subtitle' => collect([
                $this->roleLabel($roleKey),
                $programStudi,
                $degreeLevel,
                $concentration,
            ])->filter()->implode(' · '),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $user, User $viewer): array
    {
        $roleKey = $this->primaryRoleKey($user);

        return match ($roleKey) {
            AppRole::Mahasiswa->value => $this->mahasiswaDetail($user, $viewer),
            AppRole::Dosen->value => $this->dosenDetail($user, $viewer),
            default => $this->genericDetail($user, $roleKey),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mahasiswaDetail(User $user, User $viewer): array
    {
        $user->loadMissing([
            'roles',
            'mahasiswaProfile.programStudi',
        ]);

        $projects = ThesisProject::query()
            ->with([
                'latestTitle',
                'approvedTitles',
                'passedSemproDefenses',
                'activeSupervisorAssignments.lecturer.roles',
                'activeSupervisorAssignments.lecturer.dosenProfile.programStudi',
                'defenses' => fn($query) => $query
                    ->with(['examiners.lecturer.roles', 'examiners.lecturer.dosenProfile.programStudi'])
                    ->when(! $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin]), fn($query) => $query->where('status', '!=', 'cancelled'))
                    ->orderBy('type')
                    ->orderByDesc('attempt_no'),
            ])
            ->where('student_user_id', $user->getKey())
            ->when(! $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin]), fn($query) => $query->current())
            ->latest('started_at')
            ->get();

        $project = $projects->firstWhere('state', 'active') ?? $projects->first();
        $allDefenses = $projects->flatMap(fn(ThesisProject $item) => $item->defenses);

        $latestSempro = $project?->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();
        $latestSidang = $project?->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        $mentorshipCounts = $project === null
            ? []
            : collect($this->mentorshipProgressService->forProject($project))
                ->mapWithKeys(fn(array $row): array => [$row['lecturer']['id'] => $row['completed']])
                ->all();

        $advisors = $project?->activeSupervisorAssignments
            ->sortBy('role')
            ->map(function (ThesisSupervisorAssignment $assignment) use ($mentorshipCounts): ?array {
                $summary = $this->summary($assignment->lecturer);

                return $summary === null
                    ? null
                    : [...$summary, 'mentorshipCount' => $mentorshipCounts[(int) $assignment->lecturer_user_id] ?? 0];
            })
            ->filter()
            ->values()
            ->all() ?? [];

        $semproExaminers = $latestSempro?->examiners
            ->sortBy('order_no')
            ->map(fn(ThesisDefenseExaminer $examiner): ?array => $this->summary($examiner->lecturer))
            ->filter()
            ->values()
            ->all() ?? [];

        // Lecturers and admins see the whole panel. A mahasiswa, whether it is their own profile or
        // another student's, only sees the panel members ThesisDefense::examinersForStudent() allows.
        $hidesSecretExaminers = $this->isStudentViewer($viewer);
        $sidangPanel = $latestSidang instanceof ThesisDefense
            ? ($hidesSecretExaminers ? $latestSidang->examinersForStudent() : $latestSidang->examiners->sortBy('order_no'))
            : collect();
        $sidangExaminers = $sidangPanel
            ->map(fn(ThesisDefenseExaminer $examiner): ?array => $this->summary($examiner->lecturer))
            ->filter()
            ->values()
            ->all();
        $sidangExaminersSecret = $hidesSecretExaminers
            && $latestSidang instanceof ThesisDefense
            && ! $latestSidang->examinersVisibleToStudent();

        $examiners = collect($sidangExaminers)
            ->merge($semproExaminers)
            ->unique('id')
            ->values()
            ->all();

        $terminology = AcademicTerminology::forStudent($user);
        $examinerGroups = collect([
            [
                'title' => 'Penguji '.$terminology['proposalExamShort'],
                'emptyMessage' => 'Belum ada penguji '.$terminology['proposalExamShort'].'.',
                'note' => null,
                'users' => $semproExaminers,
            ],
            [
                'title' => 'Penguji '.$terminology['finalExam'],
                'emptyMessage' => $sidangExaminersSecret
                    ? 'Penguji '.$terminology['finalExam'].' dirahasiakan sampai '.mb_strtolower($terminology['finalExam']).' selesai.'
                    : 'Belum ada penguji '.$terminology['finalExam'].'.',
                'note' => $sidangExaminersSecret && $sidangExaminers !== []
                    ? 'Penguji lainnya dirahasiakan sampai '.mb_strtolower($terminology['finalExam']).' selesai.'
                    : null,
                'users' => $sidangExaminers,
            ],
        ])->filter(fn(array $group): bool => $group['users'] !== [] || ($sidangExaminersSecret && $group['title'] === 'Penguji '.$terminology['finalExam']))->values()->all();

        $canViewFullTitle = $project !== null && $this->canViewFullTitle($project, $viewer);
        $thesisTitle = $canViewFullTitle ? $project?->latestTitle : $project?->publicTitle();

        $progress = $project === null
            ? null
            : ($this->studentProgressSnapshot->forProjects(new EloquentCollection([$project]), $viewer)[(int) $project->getKey()] ?? null);

        $summary = $this->summary($user);

        return [
            ...($summary ?? []),
            'progress' => $progress,
            'headline' => 'Profil mahasiswa',
            'description' => 'Ringkasan identitas akademik dan progres '.$terminology['finalWorkLower'].' saat ini.',
            'academicTerminology' => $terminology,
            'meta' => [
                ['label' => 'NIM', 'value' => $user->mahasiswaProfile?->nim ?? '-'],
                ['label' => 'Program Studi', 'value' => $user->mahasiswaProfile?->programStudi?->name ?? '-'],
                ['label' => 'Jenjang', 'value' => DegreeLevel::tryFrom((string) $user->mahasiswaProfile?->degree_level)?->label() ?? '-'],
                ['label' => 'Konsentrasi', 'value' => $user->mahasiswaProfile?->concentration ?? '-'],
                ['label' => 'Angkatan', 'value' => (string) ($user->mahasiswaProfile?->angkatan ?? '-')],
                ['label' => 'Status', 'value' => $user->mahasiswaProfile?->is_active ? 'Aktif' : 'Nonaktif'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Nomor HP', 'value' => $summary['phoneNumber'] ?? '-'],
            ],
            'stats' => [
                ['label' => 'Status '.$terminology['finalWork'], 'value' => $this->projectStatusLabel($project, $terminology)],
                ['label' => 'Pembimbing Aktif', 'value' => (string) count($advisors)],
                ['label' => 'Penguji Aktif', 'value' => (string) count($examiners)],
                ['label' => 'Total '.$terminology['finalWork'], 'value' => (string) $projects->count()],
                ['label' => 'Arsip '.$terminology['finalWork'], 'value' => (string) $projects->whereIn('state', ['completed', 'cancelled'])->count()],
                ['label' => 'Total Ujian', 'value' => (string) $allDefenses->count()],
            ],
            'thesis' => [
                'title' => $thesisTitle?->title_id,
                'titleVisible' => $canViewFullTitle || ($project?->hasPublicTitle() ?? false),
                'statusLabel' => $this->projectStatusLabel($project, $terminology),
                'advisors' => $advisors,
                'examiners' => $examiners,
                'examinerGroups' => $examinerGroups,
            ],
            'relatedUsers' => [],
        ];
    }

    /**
     * The full thesis title is for the student, their supervisors and examiners,
     * the kaprodi of the project's prodi, and admins. Everyone else sees it only
     * when the project passes the public title rule.
     */
    private function canViewFullTitle(ThesisProject $project, User $viewer): bool
    {
        if ($project->student_user_id === $viewer->getKey()
            || $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin])
            || $viewer->kaprodiAssignment?->program_studi_id === $project->program_studi_id) {
            return true;
        }

        if ($project->supervisorAssignments()->where('lecturer_user_id', $viewer->getKey())->exists()) {
            return true;
        }

        return $project->defenses->contains(
            fn(ThesisDefense $defense): bool => $defense->examiners->contains('lecturer_user_id', $viewer->getKey()),
        );
    }

    /**
     * Whether the viewer is acting as a mahasiswa (or nobody): those viewers never get secret
     * Sidang examiners, see ThesisDefense::examinersVisibleToStudent(). Staff roles win over the
     * mahasiswa role for a user who holds both.
     */
    private function isStudentViewer(?User $viewer): bool
    {
        return ! $viewer instanceof User
            || ($viewer->hasRole(AppRole::Mahasiswa)
                && ! $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin, AppRole::Dosen, AppRole::Kaprodi]));
    }

    /**
     * @return array<string, mixed>
     */
    private function dosenDetail(User $user, ?User $viewer): array
    {
        $user->loadMissing([
            'roles',
            'dosenProfile.programStudi',
            'activeDosenProgramStudiAssignments.programStudi',
            'expertiseFields',
        ]);

        $activeAssignments = ThesisSupervisorAssignment::query()
            ->with([
                'project.student.roles',
                'project.student.adminProfile.programStudi',
                'project.student.dosenProfile.programStudi',
                'project.student.expertiseFields',
                'project.student.activeDosenProgramStudiAssignments.programStudi',
                'project.student.kaprodiAssignment.programStudi',
                'project.student.mahasiswaProfile.programStudi',
            ])
            ->where('lecturer_user_id', $user->getKey())
            ->where('status', 'active')
            ->whereHas('project', fn($query) => $query->current()->where('state', 'active'))
            ->get();

        $supervisedProjects = $activeAssignments
            ->map(fn(ThesisSupervisorAssignment $assignment): ?ThesisProject => $assignment->project?->student instanceof User ? $assignment->project : null)
            ->filter()
            ->unique(fn(ThesisProject $project): int => (int) $project->student_user_id)
            ->values();

        $mentorshipCounts = $this->mentorshipProgressService
            ->completedByStudentForLecturer((int) $user->getKey(), $activeAssignments);

        $scheduledSemproCount = ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $user->getKey())
            ->whereHas('defense', fn($query) => $query
                ->where('type', 'sempro')
                ->where('status', 'scheduled')
                ->whereHas('project', fn($query) => $query->current()->where('state', 'active')))
            ->count();

        // A mahasiswa viewing this lecturer must not learn which student the lecturer will examine at a
        // Sidang that is not completed yet, so those rows are left out of the count and the list below.
        $hidesSecretExaminers = $this->isStudentViewer($viewer);

        $scheduledSidangCount = ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $user->getKey())
            ->when($hidesSecretExaminers, fn($query) => $query->whereNot(fn($query) => $query->secretFromStudent()))
            ->whereHas('defense', fn($query) => $query
                ->where('type', 'sidang')
                ->where('status', 'scheduled')
                ->whereHas('project', fn($query) => $query->current()->where('state', 'active')))
            ->count();

        $summary = $this->summary($user);
        $quota = max(1, (int) ($user->dosenProfile?->supervision_quota ?? 14));

        // Only draft, scheduled and awaiting-finalization defenses on the student's
        // current active thesis are open assignments. Completed/cancelled attempts
        // remain in administrative history, not this list. Count each student once,
        // and leave supervised students in the supervision list with their counts.
        // Examined-only students never get a mentorship count; counts exist only for supervision.
        $supervisedStudentIds = $supervisedProjects->pluck('student_user_id')->map(fn($id): int => (int) $id);

        $examinedProjects = ThesisDefenseExaminer::query()
            ->with([
                'defense.project.student.roles',
                'defense.project.student.adminProfile.programStudi',
                'defense.project.student.dosenProfile.programStudi',
                'defense.project.student.expertiseFields',
                'defense.project.student.activeDosenProgramStudiAssignments.programStudi',
                'defense.project.student.kaprodiAssignment.programStudi',
                'defense.project.student.mahasiswaProfile.programStudi',
            ])
            ->where('lecturer_user_id', $user->getKey())
            ->when($hidesSecretExaminers, fn($query) => $query->whereNot(fn($query) => $query->secretFromStudent()))
            ->whereHas('defense', fn($query) => $query
                ->whereIn('status', ['draft', 'scheduled', 'awaiting_finalization'])
                ->whereHas('project', fn($query) => $query->current()->where('state', 'active')))
            ->get()
            ->map(fn(ThesisDefenseExaminer $examiner): ?ThesisProject => $examiner->defense?->project)
            ->filter(fn(?ThesisProject $project): bool => $project instanceof ThesisProject
                && $project->student instanceof User
                && ! $supervisedStudentIds->contains((int) $project->student_user_id))
            ->unique(fn(ThesisProject $project): int => (int) $project->student_user_id)
            ->values();

        // One batched snapshot for every student card on this profile.
        $progress = $this->studentProgressSnapshot->forProjects(
            new EloquentCollection($supervisedProjects->merge($examinedProjects)->all()),
            $viewer,
        );

        $supervisedStudents = $supervisedProjects
            ->map(fn(ThesisProject $project): ?array => $this->studentProfileRow(
                $project,
                $mentorshipCounts[(int) $project->student_user_id] ?? 0,
                $progress[(int) $project->getKey()] ?? null,
            ))
            ->filter()
            ->values()
            ->all();

        $examinedStudents = $examinedProjects
            ->map(fn(ThesisProject $project): ?array => $this->studentProfileRow(
                $project,
                null,
                $progress[(int) $project->getKey()] ?? null,
            ))
            ->filter()
            ->values()
            ->all();

        $relatedUsers = [[
            'title' => 'Mahasiswa bimbingan aktif',
            'emptyMessage' => 'Belum ada mahasiswa bimbingan aktif pada dosen ini.',
            'users' => $supervisedStudents,
        ]];

        if ($examinedStudents !== []) {
            $relatedUsers[] = [
                'title' => 'Mahasiswa yang Anda uji',
                'emptyMessage' => 'Belum ada mahasiswa yang diuji.',
                'users' => $examinedStudents,
            ];
        }

        return [
            ...($summary ?? []),
            'headline' => 'Profil dosen',
            'description' => 'Informasi akademik, kuota bimbingan, dan ringkasan mahasiswa bimbingan aktif.',
            'meta' => [
                ['label' => 'NIK', 'value' => $user->dosenProfile?->nik ?? '-'],
                ['label' => 'Program Studi', 'value' => $this->dosenProgramStudiSummary($user) ?? '-'],
                ['label' => 'Konsentrasi', 'value' => $this->dosenConcentrationSummary($user) ?? '-'],
                ['label' => 'Bidang Keilmuan', 'value' => $user->expertiseFields->pluck('name')->sort()->implode(', ') ?: '-'],
                ['label' => 'Kuota Bimbingan', 'value' => (string) $quota],
                ['label' => 'Status', 'value' => $user->dosenProfile?->is_active ? 'Aktif' : 'Nonaktif'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Nomor HP', 'value' => $summary['phoneNumber'] ?? '-'],
            ],
            'stats' => [
                ['label' => 'Mahasiswa Bimbingan Aktif', 'value' => (string) $supervisedProjects->count()],
                ['label' => AcademicTerminology::neutral()['proposalExamShort'].' Terjadwal', 'value' => (string) $scheduledSemproCount],
                ['label' => AcademicTerminology::neutral()['finalExamShort'].' Terjadwal', 'value' => (string) $scheduledSidangCount],
            ],
            'thesis' => null,
            'relatedUsers' => $relatedUsers,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $progress  Entry from StudentProgressSnapshot for this row's project.
     * @return array<string, mixed>|null
     */
    private function studentProfileRow(ThesisProject $project, ?int $mentorshipCount, ?array $progress): ?array
    {
        $summary = $this->summary($project->student);

        return $summary === null
            ? null
            : [
                ...$summary,
                'phase' => $project->phase,
                'phaseLabel' => AcademicTerminology::phaseLabel($project->phase),
                'mentorshipCount' => $mentorshipCount,
                'progress' => $progress,
            ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericDetail(User $user, string $roleKey): array
    {
        $summary = $this->summary($user);

        return [
            ...($summary ?? []),
            'headline' => 'Profil pengguna',
            'description' => 'Ringkasan informasi akun pada sistem.',
            'meta' => [
                ['label' => 'Nama', 'value' => $user->name],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Nomor HP', 'value' => $summary['phoneNumber'] ?? '-'],
                ['label' => 'Peran', 'value' => $this->roleLabel($roleKey)],
            ],
            'stats' => [],
            'thesis' => null,
            'relatedUsers' => [],
        ];
    }

    private function primaryRoleKey(User $user): string
    {
        $availableRoles = $user->availableRoles();

        if ($user->last_active_role !== null && in_array($user->last_active_role, $availableRoles, true)) {
            return $user->last_active_role;
        }

        return $availableRoles[0] ?? AppRole::Mahasiswa->value;
    }

    private function roleLabel(string $roleKey): string
    {
        return match ($roleKey) {
            AppRole::Mahasiswa->value => 'Mahasiswa',
            AppRole::Dosen->value => 'Dosen',
            AppRole::Kaprodi->value => 'Kaprodi',
            AppRole::Admin->value => 'Admin',
            AppRole::SuperAdmin->value => 'Super Admin',
            default => 'Pengguna',
        };
    }

    private function programStudiName(User $user, string $roleKey): ?string
    {
        return match ($roleKey) {
            AppRole::Mahasiswa->value => $user->mahasiswaProfile?->programStudi?->name,
            AppRole::Dosen->value => $this->dosenProgramStudiSummary($user),
            AppRole::Kaprodi->value => $user->kaprodiAssignment?->programStudi?->name,
            AppRole::Admin->value, AppRole::SuperAdmin->value => $user->adminProfile?->programStudi?->name,
            default => null,
        };
    }

    private function concentration(User $user, string $roleKey): ?string
    {
        return match ($roleKey) {
            AppRole::Mahasiswa->value => $user->mahasiswaProfile?->concentration,
            AppRole::Dosen->value => $this->dosenConcentrationSummary($user),
            default => null,
        };
    }

    private function dosenProgramStudiSummary(User $user): ?string
    {
        $summary = $user->activeDosenProgramStudiAssignments
            ->pluck('programStudi.name')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return $summary !== '' ? $summary : $user->dosenProfile?->programStudi?->name;
    }

    private function dosenConcentrationSummary(User $user): ?string
    {
        $summary = $user->activeDosenProgramStudiAssignments
            ->pluck('concentration')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return $summary !== '' ? $summary : $user->dosenProfile?->concentration;
    }

    private function projectStatusLabel(?ThesisProject $project, array $terms): string
    {
        if (! $project instanceof ThesisProject) {
            return 'Belum ada '.$terms['finalWorkLower'].' aktif';
        }

        $project->loadMissing('defenses');
        $terms = AcademicTerminology::forProject($project);

        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            if ($latestSidang->status === 'scheduled') {
                return $terms['finalExam'].' terjadwal';
            }

            if ($latestSidang->status === 'completed') {
                return match ($latestSidang->result) {
                    'pass' => $terms['finalExam'].' selesai',
                    'pass_with_revision' => $terms['finalExam'].' lulus bersyarat',
                    'fail' => $terms['finalExam'].' belum lulus',
                    default => $terms['finalExam'].' berjalan',
                };
            }
        }

        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            if ($latestSempro->status === 'scheduled') {
                return $terms['proposalExamShort'].' terjadwal';
            }

            if ($latestSempro->status === 'completed') {
                return match ($latestSempro->result) {
                    'pass' => $terms['proposalExamShort'].' selesai',
                    'pass_with_revision' => $terms['proposalExamShort'].' lulus',
                    default => $terms['proposalExamShort'].' berjalan',
                };
            }
        }

        return match ($project->phase) {
            'title_review' => AcademicTerminology::phaseLabel('title_review'),
            'sempro' => AcademicTerminology::phaseLabel('sempro'),
            'research' => AcademicTerminology::phaseLabel('research'),
            default => 'Dalam proses',
        };
    }

    private function whatsappUrl(?string $phoneNumber): ?string
    {
        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $phoneNumber) ?? '';

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return 'https://wa.me/'.$normalized;
    }
}
