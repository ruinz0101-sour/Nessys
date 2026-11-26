<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DiskStatus;
use App\Models\Node;
use App\Models\RaidPool;
use App\Models\Share;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_only_creates_admin_account_without_sample_seed_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEquals(1, User::count());

        $admin = User::where('email', 'admin@nas.local')->firstOrFail();
        $this->assertSame('System Admin', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNull($admin->department_id);
        $this->assertTrue(Hash::check('password', $admin->password));

        $this->assertEquals(0, Department::count());
        $this->assertEquals(0, Node::count());
        $this->assertEquals(0, Share::count());
        $this->assertEquals(0, AccessRequest::count());
        $this->assertEquals(0, AuditLog::count());
        $this->assertEquals(0, RaidPool::count());
        $this->assertEquals(0, DiskStatus::count());
    }
}
