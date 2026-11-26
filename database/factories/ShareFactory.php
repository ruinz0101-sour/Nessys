<?php

namespace Database\Factories;

use App\Enums\SharePermission;
use App\Models\Node;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Share>
 */
class ShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'shared_by' => User::factory(),
            'shared_with_user_id' => User::factory(),
            'shared_with_department_id' => null,
            'permission' => SharePermission::View,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }
}
