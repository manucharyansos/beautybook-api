<?php

namespace Database\Factories;

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

            // ⬇️ Սրանք ավելացնում ենք
            'role' => \App\Models\User::ROLE_STAFF,
            'salon_id' => null,
            'is_active' => true,
            'deactivated_at' => null,
        ];
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

    public function owner($salonId = null): static
    {
        return $this->state(fn () => [
            'role' => 'owner',
            'salon_id' => $salonId,
        ]);
    }

    public function staff($salonId = null): static
    {
        return $this->state(fn () => [
            'role' => 'staff',
            'salon_id' => $salonId,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => 'super_admin',
            'salon_id' => null,
        ]);
    }
}
