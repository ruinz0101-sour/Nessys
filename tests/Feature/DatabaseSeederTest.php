<?php

namespace Tests\Feature;

use App\Enums\FileCategory;
use App\Enums\NodeType;
use App\Enums\RequestStatus;
use App\Enums\RootType;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Node;
use App\Models\Share;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_users_nodes_and_relationships_with_enum_casts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@nas.local')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertEquals(1, User::where('role', UserRole::Admin)->count());
        $this->assertEquals(6, User::whereIn('role', [UserRole::DeptManager, UserRole::User])->count());
        $this->assertEquals(3, Department::count());

        $sharedFolder = Node::where('name', 'Company Policies')->firstOrFail();
        $this->assertSame(NodeType::Folder, $sharedFolder->type);
        $this->assertSame(RootType::Shared, $sharedFolder->root_type);
        $this->assertFalse($sharedFolder->is_dropzone);
        $this->assertNull($sharedFolder->parent);
        $this->assertCount(2, $sharedFolder->children);
        $this->assertTrue($sharedFolder->owner->is($admin));

        $dropzoneFolder = Node::where('is_dropzone', true)->firstOrFail();
        $this->assertSame(NodeType::Folder, $dropzoneFolder->type);
        $this->assertSame(RootType::Shared, $dropzoneFolder->root_type);
        $this->assertTrue($dropzoneFolder->is_dropzone);

        $childFile = $sharedFolder->children()->where('name', 'Employee-Handbook-2026.pdf')->firstOrFail();
        $this->assertSame(NodeType::File, $childFile->type);
        $this->assertSame(FileCategory::Document, $childFile->category);
        $this->assertTrue($childFile->parent->is($sharedFolder));

        $share = Share::firstOrFail();
        $this->assertInstanceOf(SharePermission::class, $share->permission);
        $this->assertNotNull($share->node);
        $this->assertNotNull($share->sharedBy);

        $accessRequest = AccessRequest::firstOrFail();
        $this->assertSame(RequestStatus::Pending, $accessRequest->status);
        $this->assertNotNull($accessRequest->node);
        $this->assertNotNull($accessRequest->requester);
        $this->assertNotNull($accessRequest->owner);

        $auditLog = AuditLog::firstOrFail();
        $this->assertNotNull($auditLog->user);
        $this->assertIsArray($auditLog->meta);
    }
}
