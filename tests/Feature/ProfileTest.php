<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_profile_settings(): void
    {
        $this->get('/settings/profile')->assertRedirect('/login');
    }

    public function test_user_can_update_profile_upload_replace_and_remove_avatar(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@nas.local',
        ]);

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk();

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $firstAvatar = UploadedFile::fake()->createWithContent('avatar1.png', $pngBytes);

        $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@nas.local',
            'avatar' => $firstAvatar,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@nas.local', $user->email);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);

        $firstPath = $user->avatar_path;

        $this->actingAs($user)
            ->get("/users/{$user->id}/avatar")
            ->assertOk();

        // Replace existing avatar
        $secondAvatar = UploadedFile::fake()->createWithContent('avatar2.png', $pngBytes);
        $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@nas.local',
            'avatar' => $secondAvatar,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotSame($firstPath, $user->avatar_path);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($user->avatar_path);

        $secondPath = $user->avatar_path;

        // Remove avatar
        $this->actingAs($user)->post('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@nas.local',
            'remove_avatar' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('local')->assertMissing($secondPath);

        $this->actingAs($user)
            ->get("/users/{$user->id}/avatar")
            ->assertNotFound();
    }

    public function test_profile_update_validates_duplicate_email_and_non_image_avatar(): void
    {
        Storage::fake('local');

        $other = User::factory()->create(['email' => 'taken@nas.local']);
        $user = User::factory()->create(['email' => 'me@nas.local']);

        $this->actingAs($user)->post('/settings/profile', [
            'name' => 'My Name',
            'email' => $other->email,
            'avatar' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors(['email', 'avatar']);
    }
}
