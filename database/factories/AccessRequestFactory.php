<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\AccessRequest;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessRequest>
 */
class AccessRequestFactory extends Factory
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
            'requester_id' => User::factory(),
            'owner_id' => User::factory(),
            'reason' => fake()->sentence(),
            'status' => RequestStatus::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
            'granted_permission' => null,
            'granted_until' => null,
            'last_reminded_at' => null,
        ];
    }
}
