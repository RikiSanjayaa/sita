<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;

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
            'mahasiswaProfile.programStudi',
        ]);

        $roleKey = $this->primaryRoleKey($user);
        $programStudi = $this->programStudiName($user, $roleKey);
        $concentration = $this->concentration($user, $roleKey);

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
            'concentration' => $concentration,
            'subtitle' => collect([
                $this->roleLabel($roleKey),
                $programStudi,
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

        $project = ThesisProject::query()
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
            ->where('state', 'active')
            ->latest('started_at')
            ->first();

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

        $currentDefense = $latestSidang instanceof ThesisDefense
            ? $latestSidang
            : $latestSempro;

        $examiners = $currentDefense?->examiners
            ->sortBy('order_no')
            ->map(fn(ThesisDefenseExaminer $examiner): ?array => $this->summary($examiner->lecturer))
            ->filter()
            ->values()
            ->all() ?? [];

        $summary = $this->summary($user);

        return [
            ...($summary ?? []),
            'headline' => 'Profil mahasiswa',
            'description' => 'Ringkasan identitas akademik dan progres tugas akhir saat ini.',
            'meta' => [
                ['label' => 'NIM', 'value' => $user->mahasiswaProfile?->nim ?? '-'],
                ['label' => 'Program Studi', 'value' => $user->mahasiswaProfile?->programStudi?->name ?? '-'],
                ['label' => 'Konsentrasi', 'value' => $user->mahasiswaProfile?->concentration ?? '-'],
                ['label' => 'Angkatan', 'value' => (string) ($user->mahasiswaProfile?->angkatan ?? '-')],
                ['label' => 'Status', 'value' => $user->mahasiswaProfile?->is_active ? 'Aktif' : 'Nonaktif'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Nomor HP', 'value' => $user->phone_number ?? '-'],
            ],
            'stats' => [
                ['label' => 'Status Skripsi', 'value' => $this->projectStatusLabel($project)],
                ['label' => 'Pembimbing Aktif', 'value' => (string) count($advisors)],
                ['label' => 'Penguji Aktif', 'value' => (string) count($examiners)],
            ],
            'thesis' => [
                'title' => $project?->latestTitle?->title_id,
                'statusLabel' => $this->projectStatusLabel($project),
                'advisors' => $advisors,
                'examiners' => $examiners,
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
            'description' => 'Informasi akademik, kuota bimbingan, dan ringkasan mahasiswa aktif.',
            'meta' => [
                ['label' => 'NIK', 'value' => $user->dosenProfile?->nik ?? '-'],
                ['label' => 'Program Studi', 'value' => $user->dosenProfile?->programStudi?->name ?? '-'],
                ['label' => 'Konsentrasi', 'value' => $user->dosenProfile?->concentration ?? '-'],
                ['label' => 'Kuota Bimbingan', 'value' => (string) $quota],
                ['label' => 'Status', 'value' => $user->dosenProfile?->is_active ? 'Aktif' : 'Nonaktif'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Nomor HP', 'value' => $user->phone_number ?? '-'],
            ],
            'stats' => [
                ['label' => 'Mahasiswa Aktif', 'value' => (string) $activeStudents->count()],
                ['label' => 'Sempro Terjadwal', 'value' => (string) $scheduledSemproCount],
                ['label' => 'Sidang Terjadwal', 'value' => (string) $scheduledSidangCount],
            ],
            'thesis' => null,
            'relatedUsers' => [
                [
                    'title' => 'Mahasiswa aktif',
                    'emptyMessage' => 'Belum ada mahasiswa aktif pada dosen ini.',
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
            AppRole::Admin->value => 'Admin',
            AppRole::SuperAdmin->value => 'Super Admin',
            default => 'Pengguna',
        };
    }

    private function programStudiName(User $user, string $roleKey): ?string
    {
        return match ($roleKey) {
            AppRole::Mahasiswa->value => $user->mahasiswaProfile?->programStudi?->name,
            AppRole::Dosen->value => $user->dosenProfile?->programStudi?->name,
            AppRole::Admin->value, AppRole::SuperAdmin->value => $user->adminProfile?->programStudi?->name,
            default => null,
        };
    }

    private function concentration(User $user, string $roleKey): ?string
    {
        return match ($roleKey) {
            AppRole::Mahasiswa->value => $user->mahasiswaProfile?->concentration,
            AppRole::Dosen->value => $user->dosenProfile?->concentration,
            default => null,
        };
    }

    private function projectStatusLabel(?ThesisProject $project): string
    {
        if (! $project instanceof ThesisProject) {
            return 'Belum ada proyek aktif';
        }

        $project->loadMissing('defenses');

        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            if ($latestSidang->status === 'scheduled') {
                return 'Sidang terjadwal';
            }

            if ($latestSidang->status === 'completed') {
                return match ($latestSidang->result) {
                    'pass' => 'Sidang selesai',
                    'pass_with_revision' => 'Revisi sidang',
                    'fail' => 'Sidang belum lulus',
                    default => 'Sidang berjalan',
                };
            }
        }

        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            if ($latestSempro->status === 'scheduled') {
                return 'Sempro terjadwal';
            }

            if ($latestSempro->status === 'completed') {
                return match ($latestSempro->result) {
                    'pass' => 'Sempro selesai',
                    'pass_with_revision' => 'Revisi sempro',
                    default => 'Sempro berjalan',
                };
            }
        }

        return match ($project->phase) {
            'title_review' => 'Menunggu review judul',
            'sempro' => 'Tahap sempro',
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
