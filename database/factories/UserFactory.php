<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'agency_id' => Agency::factory(),
            'role_id' => fn () => Role::findBySlug(RoleName::Owner)->id,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function superAdmin(): static
    {
        return $this->state(['agency_id' => null, 'role_id' => Role::findBySlug(RoleName::SuperAdmin)->id]);
    }

    public function withRole(RoleName $role): static
    {
        return $this->state(['role_id' => Role::findBySlug($role)->id]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
