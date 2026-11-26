<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'department_id' => null,
            'quota_bytes' => 10 * 1024 * 1024 * 1024, // 10 GB
            'used_bytes' => 0,
            'is_active' => true,
            'failed_login_count' => 0,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
            'quota_bytes' => 0, // 0 = ไม่จำกัด
        ]);
    }

    public function deptManager(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::DeptManager,
            'quota_bytes' => 50 * 1024 * 1024 * 1024, // 50 GB
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
