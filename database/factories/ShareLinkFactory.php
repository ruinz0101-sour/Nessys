<?php

namespace Database\Factories;

use App\Enums\SharePermission;
use App\Models\Node;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareLink>
 */
class ShareLinkFactory extends Factory
{
    protected $model = ShareLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'node_id' => Node::factory(),
            'created_by' => User::factory(),
            'token' => Str::random(40),
            'password_hash' => null,
            'permission' => SharePermission::View,
            'max_downloads' => null,
            'download_count' => 0,
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
            'created_at' => now(),
        ];
    }
}
