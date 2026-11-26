<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['login', 'view', 'download', 'upload', 'share']),
            'node_id' => null,
            'node_name' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'meta' => null,
            'created_at' => now(),
        ];
    }
}
