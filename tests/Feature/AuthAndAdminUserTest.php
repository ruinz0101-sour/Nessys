<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_locks_account_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'user@nas.local',
            'password' => Hash::make('secret1234'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'user@nas.local',
                'password' => 'wrong-password',
            ]);
        }

        $user->refresh();
        $this->assertEquals(5, $user->failed_login_count);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());

        $response = $this->post('/login', [
            'email' => 'user@nas.local',
            'password' => 'secret1234',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out_on_every_request(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_non_admin_cannot_access_admin_user_management(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_update_toggle_quota_and_reset_password_for_user(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Engineering']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Staff',
            'email' => 'staff@nas.local',
            'password' => 'password123',
            'role' => 'user',
            'department_id' => $department->id,
            'quota_gb' => 20,
            'is_active' => true,
        ])->assertRedirect();

        $staff = User::where('email', 'staff@nas.local')->firstOrFail();
        $this->assertEquals(20 * 1073741824, $staff->quota_bytes);

        $this->actingAs($admin)->patch("/admin/users/{$staff->id}/quota", [
            'quota_gb' => 50,
        ])->assertRedirect();

        $this->assertEquals(50 * 1073741824, $staff->fresh()->quota_bytes);

        $this->actingAs($admin)->patch("/admin/users/{$staff->id}/toggle-active")
            ->assertRedirect();

        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}/reset-password", [
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('newsecret123', $staff->fresh()->password));
    }

    public function test_admin_and_regular_user_are_routed_to_separate_frontends_after_login(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@nas.local',
            'password' => Hash::make('secret1234'),
        ]);

        $user = User::factory()->create([
            'email' => 'staff@nas.local',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post('/login', [
            'email' => 'admin@nas.local',
            'password' => 'secret1234',
        ])->assertRedirect('/admin/dashboard');

        $this->get('/')->assertRedirect('/admin/dashboard');

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'staff@nas.local',
            'password' => 'secret1234',
        ])->assertRedirect('/files');
    }
}
