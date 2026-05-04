<?php

namespace Database\Seeders;

use App\Models\CsatResponse;
use App\Models\ProgramStudi;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CsatResponseSeeder extends Seeder
{
    private const RECENT_SCORE_PATTERN = [5, 4, 3, 2, 4, 1, 5, 3];

    private const HISTORICAL_SCORE_PATTERN = [4, 5, 3, 4, 2, 5, 4, 3];

    public function run(): void
    {
        $this->seedUserGroup($this->usersFor('mahasiswa', 'ilkom', 6), 'mahasiswa');
        $this->seedUserGroup($this->usersFor('dosen', 'ilkom', 3), 'dosen');
        $this->seedUserGroup($this->usersFor('mahasiswa', 'si', 3), 'mahasiswa');
        $this->seedUserGroup($this->usersFor('dosen', 'si', 2), 'dosen');
        $this->seedUserGroup($this->usersFor('mahasiswa', 'ti', 2), 'mahasiswa');
        $this->seedUserGroup($this->usersFor('dosen', 'ti', 1), 'dosen');
    }

    /**
     * @return Collection<int, User>
     */
    private function usersFor(string $role, string $programStudiSlug, int $limit): Collection
    {
        $profileRelation = $role === 'dosen' ? 'dosenProfile' : 'mahasiswaProfile';

        return User::query()
            ->whereHas('roles', fn($query) => $query->where('name', $role))
            ->whereHas("{$profileRelation}.programStudi", fn($query) => $query->where('slug', $programStudiSlug))
            ->with("{$profileRelation}.programStudi")
            ->orderBy('email')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function seedUserGroup(Collection $users, string $role): void
    {
        foreach ($users->values() as $index => $user) {
            $programStudi = $this->programStudiFor($user, $role);

            if (! $programStudi instanceof ProgramStudi) {
                continue;
            }

            $historicalWeek = 9 + ($index % 3);
            $recentWeek = $index % 8;

            $this->upsertResponse(
                user: $user,
                programStudi: $programStudi,
                role: $role,
                submittedAt: now()->toImmutable()->startOfWeek()->subWeeks($historicalWeek)->addDays(1)->setTime(9 + ($index % 4), 15),
                score: self::HISTORICAL_SCORE_PATTERN[$index % count(self::HISTORICAL_SCORE_PATTERN)],
            );

            $this->upsertResponse(
                user: $user,
                programStudi: $programStudi,
                role: $role,
                submittedAt: now()->toImmutable()->startOfWeek()->subWeeks($recentWeek)->addDays(2 + ($index % 3))->setTime(10 + ($index % 5), 30),
                score: self::RECENT_SCORE_PATTERN[$index % count(self::RECENT_SCORE_PATTERN)],
            );
        }
    }

    private function upsertResponse(
        User $user,
        ProgramStudi $programStudi,
        string $role,
        CarbonImmutable $submittedAt,
        int $score,
    ): void {
        $response = CsatResponse::query()->firstOrNew([
            'user_id' => $user->id,
            'created_at' => $submittedAt->format('Y-m-d H:i:s'),
        ]);

        $response->forceFill([
            'program_studi_id' => $programStudi->id,
            'respondent_role' => $role,
            'score' => $score,
            'kritik' => $this->kritikFor($score, $role),
            'saran' => $this->saranFor($score, $role),
        ]);
        $response->created_at = $submittedAt;
        $response->updated_at = $submittedAt;
        $response->save();
    }

    private function programStudiFor(User $user, string $role): ?ProgramStudi
    {
        return match ($role) {
            'dosen' => $user->dosenProfile?->programStudi,
            default => $user->mahasiswaProfile?->programStudi,
        };
    }

    private function kritikFor(int $score, string $role): ?string
    {
        if ($score >= 4) {
            return null;
        }

        return match (true) {
            $score <= 2 && $role === 'dosen' => 'Alur tindak lanjut dan status agenda masih kurang cepat terbaca saat membuka workspace.',
            $score <= 2 => 'Beberapa status dan langkah berikutnya masih terasa membingungkan saat dipakai.',
            $role === 'dosen' => 'Masih ada beberapa bagian yang perlu dibuat lebih ringkas untuk memantau mahasiswa.',
            default => 'Masih ada bagian yang perlu dibuat lebih jelas agar tidak membingungkan.',
        };
    }

    private function saranFor(int $score, string $role): string
    {
        return match (true) {
            $score <= 2 && $role === 'dosen' => 'Tambahkan penanda prioritas yang lebih kuat untuk dokumen, pesan, dan jadwal yang perlu ditindaklanjuti.',
            $score <= 2 => 'Tampilkan langkah berikutnya yang lebih jelas di halaman status dan pengajuan.',
            $score === 3 && $role === 'dosen' => 'Ringkasan mahasiswa aktif dan agenda mingguan bisa dibuat lebih padat dalam satu layar.',
            $score === 3 => 'Berikan penjelasan singkat pada tiap status agar mahasiswa lebih cepat paham.',
            $role === 'dosen' => 'Pertahankan alurnya, lalu tambahkan filter yang lebih cepat untuk membaca agenda dan dokumen.',
            default => 'Pertahankan tampilan yang sudah jelas, lalu tambahkan petunjuk singkat pada titik-titik penting.',
        };
    }
}
