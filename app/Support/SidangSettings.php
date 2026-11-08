<?php

namespace App\Support;

use App\Models\ProgramStudi;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SidangSettings
{
    public const FT_CONTACT = 'Telp (0370) 638369 | Whatsapp 0859-3615-9726 | Email ftd@universitasbumigora.ac.id';

    public static function minimumPaperExaminers(?ProgramStudi $program): int
    {
        return (int) ($program?->sidang_settings['minimum_paper_examiners'] ?? 0);
    }

    public const GRADES = ['A', 'B+', 'B', 'C+', 'C', 'D'];

    public const GRADE_KEYS = ['a', 'b_plus', 'b', 'c_plus', 'c', 'd'];

    public const DEFAULTS = [
        'minimum_paper_examiners' => 0,
        'examiner_1' => 30, 'examiner_2' => 30, 'supervisor' => 40,
        'a' => 81, 'b_plus' => 71, 'b' => 66, 'c_plus' => 61, 'c' => 50, 'd' => 30,
        'city' => 'Mataram', 'heading' => 'Universitas Bumigora',
        'letterhead_address' => 'Jl. Ismail Marzuki No. 22, Cakranegara, Mataram',
        'letterhead_website' => 'www.universitasbumigora.ac.id',
        'letterhead_contact' => null,
        'dean_name' => '', 'dean_nik' => '', 'staff_name' => '', 'staff_nik' => '',
        'examiner_rubric' => "Penguasaan Materi | 25\nDaya Nalar | 20\nKarya yang Dibuat | 25\nKajian Pustaka | 10\nKesesuaian Rancangan dengan Implementasi | 10\nSimpulan dan Saran | 5\nPresentasi | 5",
        'supervisor_rubric' => "Penguasaan Materi | 25\nDaya Nalar | 25\nKarya yang Dibuat | 25\nKemampuan Menulis Ilmiah | 15\nKeaktifan | 10",
    ];

    public static function forProgram(?ProgramStudi $program): array
    {
        $saved = $program?->sidang_settings ?? [];
        $settings = array_replace(self::DEFAULTS, array_filter($saved, fn ($value) => $value !== null));
        foreach (['letterhead_address', 'letterhead_contact', 'letterhead_website'] as $field) {
            if (array_key_exists($field, $saved)) {
                $settings[$field] = (string) $saved[$field];
            }
        }

        return $settings;
    }

    public static function validate(array $settings): void
    {
        $keys = [...self::GRADE_KEYS, 'examiner_1', 'examiner_2', 'supervisor'];
        Validator::make($settings, [
            ...array_fill_keys($keys, 'required|integer|min:1|max:100'),
            'minimum_paper_examiners' => 'nullable|integer|min:0',
        ])->validate();
        if (array_sum(array_intersect_key($settings, array_flip(['examiner_1', 'examiner_2', 'supervisor']))) !== 100) {
            throw ValidationException::withMessages(['supervisor' => 'Total bobot ketiga penilai harus 100%.']);
        }
        $thresholds = array_map(fn ($key) => (int) $settings[$key], self::GRADE_KEYS);
        $descending = $thresholds;
        rsort($descending);
        if ($thresholds !== $descending || count(array_unique($thresholds)) !== count($thresholds)) {
            throw ValidationException::withMessages(['a' => 'Batas nilai huruf harus berurutan dari tertinggi ke terendah.']);
        }
        foreach (['examiner_rubric', 'supervisor_rubric'] as $key) {
            if (self::rubric($settings[$key] ?? '') === []) {
                throw ValidationException::withMessages([$key => 'Gunakan format Komponen | Bobot. Total bobot harus 100%.']);
            }
        }
    }

    public static function rubric(string $value): array
    {
        $rows = [];
        foreach (preg_split('/\R/', trim($value)) as $line) {
            if (! preg_match('/^([^|]+)\|\s*([1-9][0-9]{0,2})\s*$/u', $line, $matches) || trim($matches[1]) === '') {
                return [];
            }
            $rows[] = ['label' => trim($matches[1]), 'weight' => (int) $matches[2]];
        }

        return array_sum(array_column($rows, 'weight')) === 100 ? $rows : [];
    }

    public static function grade(float $score, array $settings): string
    {
        foreach (self::GRADE_KEYS as $index => $key) {
            if ($score >= (int) $settings[$key]) {
                return self::GRADES[$index];
            }
        }

        return 'E';
    }
}
