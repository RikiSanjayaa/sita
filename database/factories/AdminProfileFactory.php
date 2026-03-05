<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminProfile>
 */
class AdminProfileFactory extends Factory
{
  protected $model = AdminProfile::class;

  private const PROGRAM_STUDI = [
    'Ilmu Komputer',
    'Teknologi Informasi',
    'Rekayasa Perangkat Lunak',
    'Sistem Informasi',
    'Teknologi Pangan',
    'Desain Komunikasi Visual',
    'Seni Pertunjukan',
    'Gizi',
    'Farmasi',
    'Sastra Inggris',
    'Pariwisata',
    'Hukum',
    'Manajemen',
    'Akuntansi',
    'Bisnis Digital',
    'Pendidikan Teknologi Informasi',
    'Pendidikan Kepelatihan Olahraga',
  ];

  /**
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'user_id' => User::factory()->asAdmin(),
      'program_studi' => fake()->randomElement(self::PROGRAM_STUDI),
    ];
  }
}
