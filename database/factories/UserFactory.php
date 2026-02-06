<?php

namespace Database\Factories;

use App\Enums\AppRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'last_active_role' => AppRole::Mahasiswa->value,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $roleName = is_string($user->last_active_role)
                ? $user->last_active_role
                : AppRole::Mahasiswa->value;

            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
            ]);

            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function asMahasiswa(): static
    {
        return $this->state(fn (): array => [
            'last_active_role' => AppRole::Mahasiswa->value,
        ]);
    }

    public function asDosen(): static
    {
        return $this->state(fn (): array => [
            'last_active_role' => AppRole::Dosen->value,
        ]);
    }

    public function asAdmin(): static
    {
        return $this->state(fn (): array => [
            'last_active_role' => AppRole::Admin->value,
        ]);
    }
}
