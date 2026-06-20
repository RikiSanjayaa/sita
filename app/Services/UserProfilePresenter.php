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

class UserProfilePresenter
{
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

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phoneNumber' => $user->phone_number,
            'whatsappUrl' => $this->whatsappUrl($user->phone_number),
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
    public function detail(User $user): array
    {
        $roleKey = $this->primaryRoleKey($user);

        return match ($roleKey) {
            AppRole::Mahasiswa->value => $this->mahasiswaDetail($user),
            AppRole::Dosen->value => $this->dosenDetail($user),
            default => $this->genericDetail($user, $roleKey),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mahasiswaDetail(User $user): array
    {
        $user->loadMissing([
            'roles',
            'mahasiswaProfile.programStudi',
        ]);

        $projects = ThesisProject::query()
            ->with([
                'latestTitle',
                'activeSupervisorAssignments.lecturer.roles',
                'activeSupervisorAssignments.lecturer.dosenProfile.programStudi',
                'defenses' => fn($query) => $query
                    ->with(['examiners.lecturer.roles', 'examiners.lecturer.dosenProfile.programStudi'])
                    ->orderBy('type')
                    ->orderByDesc('attempt_no'),
            ])
            ->where('student_user_id', $user->getKey())
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

        $advisors = $project?->activeSupervisorAssignments
            ->sortBy('role')
            ->map(fn(ThesisSupervisorAssignment $assignment): ?array => $this->summary($assignment->lecturer))
            ->filter()
            ->values()
            ->all() ?? [];

        $semproExaminers = $latestSempro?->examiners
            ->sortBy('order_no')
            ->map(fn(ThesisDefenseExaminer $examiner): ?array => $this->summary($examiner->lecturer))
            ->filter()
            ->values()
            ->all() ?? [];

        $sidangExaminers = $latestSidang?->examiners
            ->sortBy('order_no')
            ->map(fn(ThesisDefenseExaminer $examiner): ?array => $this->summary($examiner->lecturer))
            ->filter()
            ->values()
            ->all() ?? [];

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
                'users' => $semproExaminers,
            ],
            [
                'title' => 'Penguji '.$terminology['finalExam'],
                'emptyMessage' => 'Belum ada penguji '.$terminology['finalExam'].'.',
                'users' => $sidangExaminers,
            ],
        ])->filter(fn(array $group): bool => $group['users'] !== [])->values()->all();

        $summary = $this->summary($user);

        return [
            ...($summary ?? []),
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
                ['label' => 'Nomor HP', 'value' => $user->phone_number ?? '-'],
            ],
            'stats' => [
                ['label' => 'Status '.$terminology['finalWork'], 'value' => $this->projectStatusLabel($project)],
                ['label' => 'Pembimbing Aktif', 'value' => (string) count($advisors)],
                ['label' => 'Penguji Aktif', 'value' => (string) count($examiners)],
                ['label' => 'Total Proyek', 'value' => (string) $projects->count()],
                ['label' => 'Arsip Proyek', 'value' => (string) $projects->whereIn('state', ['completed', 'cancelled'])->count()],
                ['label' => 'Total Ujian', 'value' => (string) $allDefenses->count()],
            ],
            'thesis' => [
                'title' => $project?->latestTitle?->title_id,
                'statusLabel' => $this->projectStatusLabel($project),
                'advisors' => $advisors,
                'examiners' => $examiners,
                'examinerGroups' => $examinerGroups,
            ],
            'relatedUsers' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dosenDetail(User $user): array
    {
        $user->loadMissing([
            'roles',
            'dosenProfile.programStudi',
            'activeDosenProgramStudiAssignments.programStudi',
            'expertiseFields',
        ]);

        $activeAssignments = ThesisSupervisorAssignment::query()
            ->with(['project.student.roles', 'project.student.mahasiswaProfile.programStudi'])
            ->where('lecturer_user_id', $user->getKey())
            ->where('status', 'active')
            ->whereHas('project', fn($query) => $query->where('state', 'active'))
            ->get();

        $activeStudents = $activeAssignments
            ->map(fn(ThesisSupervisorAssignment $assignment): ?User => $assignment->project?->student)
            ->filter()
            ->unique('id')
            ->values();

        $scheduledSemproCount = ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $user->getKey())
            ->whereHas('defense', fn($query) => $query
                ->where('type', 'sempro')
                ->where('status', 'scheduled'))
            ->count();

        $scheduledSidangCount = ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $user->getKey())
            ->whereHas('defense', fn($query) => $query
                ->where('type', 'sidang')
                ->where('status', 'scheduled'))
            ->count();

        $summary = $this->summary($user);
        $quota = max(1, (int) ($user->dosenProfile?->supervision_quota ?? 14));

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
                ['label' => 'Nomor HP', 'value' => $user->phone_number ?? '-'],
            ],
            'stats' => [
                ['label' => 'Mahasiswa Bimbingan Aktif', 'value' => (string) $activeStudents->count()],
                ['label' => 'Sempro Terjadwal', 'value' => (string) $scheduledSemproCount],
                ['label' => 'Sidang Terjadwal', 'value' => (string) $scheduledSidangCount],
            ],
            'thesis' => null,
            'relatedUsers' => [
                [
                    'title' => 'Mahasiswa bimbingan aktif',
                    'emptyMessage' => 'Belum ada mahasiswa bimbingan aktif pada dosen ini.',
                    'users' => $activeStudents
                        ->map(fn(User $student): ?array => $this->summary($student))
                        ->filter()
                        ->values()
                        ->all(),
                ],
            ],
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
                ['label' => 'Nomor HP', 'value' => $user->phone_number ?? '-'],
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

    private function projectStatusLabel(?ThesisProject $project): string
    {
        if (! $project instanceof ThesisProject) {
            return 'Belum ada proyek aktif';
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
                    'pass_with_revision' => 'Revisi '.$terms['finalExam'],
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
                    'pass_with_revision' => 'Revisi '.$terms['proposalExamShort'],
                    default => $terms['proposalExamShort'].' berjalan',
                };
            }
        }

        return match ($project->phase) {
            'title_review' => 'Menunggu review judul',
            'sempro' => 'Tahap '.$terms['proposalExamShort'],
            'research' => 'Tahap bimbingan',
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
