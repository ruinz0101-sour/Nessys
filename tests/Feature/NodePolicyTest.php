<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\SharePermission;
use App\Models\AccessRequest;
use App\Models\Department;
use App\Models\Node;
use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class NodePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_update_and_delete_any_active_node(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $node));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $node));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $node));
    }

    public function test_owner_can_view_update_and_delete_own_node(): void
    {
        $owner = User::factory()->create();
        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $node));
        $this->assertTrue(Gate::forUser($owner)->allows('delete', $node));
    }

    public function test_shared_root_type_node_can_be_viewed_by_any_active_user_but_not_updated(): void
    {
        $owner = User::factory()->admin()->create();
        $viewer = User::factory()->create();
        $sharedNode = Node::factory()->shared()->create(['owner_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($viewer)->allows('view', $sharedNode));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', $sharedNode));
        $this->assertFalse(Gate::forUser($viewer)->allows('delete', $sharedNode));
    }

    public function test_user_with_active_direct_or_department_share_can_access_node(): void
    {
        $itDept = Department::factory()->create(['name' => 'IT']);
        $hrDept = Department::factory()->create(['name' => 'HR']);

        $owner = User::factory()->create();
        $directUser = User::factory()->create();
        $itMember = User::factory()->create(['department_id' => $itDept->id]);
        $hrMember = User::factory()->create(['department_id' => $hrDept->id]);

        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        Share::factory()->create([
            'node_id' => $node->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => $directUser->id,
            'permission' => SharePermission::Edit,
            'expires_at' => now()->addDay(),
        ]);

        Share::factory()->create([
            'node_id' => $node->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => null,
            'shared_with_department_id' => $itDept->id,
            'permission' => SharePermission::View,
        ]);

        $this->assertTrue(Gate::forUser($directUser)->allows('view', $node));
        $this->assertTrue(Gate::forUser($directUser)->allows('update', $node));

        $this->assertTrue(Gate::forUser($itMember)->allows('view', $node));
        $this->assertFalse(Gate::forUser($itMember)->allows('update', $node));

        $this->assertFalse(Gate::forUser($hrMember)->allows('view', $node));
    }

    public function test_share_and_access_request_inherit_from_deep_ancestor_folders(): void
    {
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();
        $approvedUser = User::factory()->create();
        $stranger = User::factory()->create();

        // สร้างโครงสร้างโฟลเดอร์ลึก 5 ชั้น: L1 -> L2 -> L3 -> L4 -> File (L5)
        $level1 = Node::factory()->folder('L1')->personal()->create(['owner_id' => $owner->id]);
        $level2 = Node::factory()->folder('L2')->personal()->create(['owner_id' => $owner->id, 'parent_id' => $level1->id]);
        $level3 = Node::factory()->folder('L3')->personal()->create(['owner_id' => $owner->id, 'parent_id' => $level2->id]);
        $level4 = Node::factory()->folder('L4')->personal()->create(['owner_id' => $owner->id, 'parent_id' => $level3->id]);
        $deepFile = Node::factory()->file('deep.pdf', 'pdf')->personal()->create(['owner_id' => $owner->id, 'parent_id' => $level4->id]);

        // แชร์ที่โฟลเดอร์บนสุด (L1)
        Share::factory()->create([
            'node_id' => $level1->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => $sharedUser->id,
            'permission' => SharePermission::Edit,
        ]);

        // อนุมัติคำขอที่โฟลเดอร์ระดับ 2 (L2)
        AccessRequest::factory()->create([
            'node_id' => $level2->id,
            'requester_id' => $approvedUser->id,
            'owner_id' => $owner->id,
            'status' => RequestStatus::Approved,
            'granted_permission' => SharePermission::View,
            'granted_until' => now()->addDays(7),
        ]);

        $this->assertTrue(Gate::forUser($sharedUser)->allows('view', $deepFile));
        $this->assertTrue(Gate::forUser($sharedUser)->allows('update', $deepFile));

        $this->assertTrue(Gate::forUser($approvedUser)->allows('view', $deepFile));
        $this->assertFalse(Gate::forUser($approvedUser)->allows('update', $deepFile));

        $this->assertFalse(Gate::forUser($stranger)->allows('view', $deepFile));
    }

    public function test_approved_access_request_allows_access_while_pending_denied_or_expired_requests_deny(): void
    {
        $owner = User::factory()->create();
        $approvedUser = User::factory()->create();
        $pendingUser = User::factory()->create();
        $deniedUser = User::factory()->create();
        $expiredReqUser = User::factory()->create();

        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        AccessRequest::factory()->create([
            'node_id' => $node->id,
            'requester_id' => $approvedUser->id,
            'owner_id' => $owner->id,
            'status' => RequestStatus::Approved,
            'granted_permission' => SharePermission::Edit,
            'granted_until' => now()->addHour(),
        ]);

        AccessRequest::factory()->create([
            'node_id' => $node->id,
            'requester_id' => $pendingUser->id,
            'owner_id' => $owner->id,
            'status' => RequestStatus::Pending,
        ]);

        AccessRequest::factory()->create([
            'node_id' => $node->id,
            'requester_id' => $deniedUser->id,
            'owner_id' => $owner->id,
            'status' => RequestStatus::Denied,
        ]);

        AccessRequest::factory()->create([
            'node_id' => $node->id,
            'requester_id' => $expiredReqUser->id,
            'owner_id' => $owner->id,
            'status' => RequestStatus::Approved,
            'granted_permission' => SharePermission::View,
            'granted_until' => now()->subMinute(),
        ]);

        $this->assertTrue(Gate::forUser($approvedUser)->allows('view', $node));
        $this->assertTrue(Gate::forUser($approvedUser)->allows('update', $node));

        $this->assertFalse(Gate::forUser($pendingUser)->allows('view', $node));
        $this->assertFalse(Gate::forUser($deniedUser)->allows('view', $node));
        $this->assertFalse(Gate::forUser($expiredReqUser)->allows('view', $node));
    }

    public function test_expired_or_revoked_share_is_denied(): void
    {
        $owner = User::factory()->create();
        $expiredUser = User::factory()->create();
        $revokedUser = User::factory()->create();

        $folder = Node::factory()->folder('Parent')->personal()->create(['owner_id' => $owner->id]);
        $childFile = Node::factory()->file('child.pdf', 'pdf')->personal()->create([
            'owner_id' => $owner->id,
            'parent_id' => $folder->id,
        ]);

        Share::factory()->create([
            'node_id' => $folder->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => $expiredUser->id,
            'permission' => SharePermission::View,
            'expires_at' => now()->subSecond(),
        ]);

        Share::factory()->create([
            'node_id' => $folder->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => $revokedUser->id,
            'permission' => SharePermission::Edit,
            'revoked_at' => now()->subMinute(),
        ]);

        $this->assertFalse(Gate::forUser($expiredUser)->allows('view', $childFile));
        $this->assertFalse(Gate::forUser($revokedUser)->allows('view', $childFile));
        $this->assertFalse(Gate::forUser($revokedUser)->allows('update', $childFile));
    }

    public function test_trashed_node_or_node_inside_trashed_ancestor_folder_is_denied(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $sharedUser = User::factory()->create();

        $trashedFile = Node::factory()->personal()->create([
            'owner_id' => $owner->id,
            'deleted_at' => now(),
        ]);

        $trashedFolder = Node::factory()->folder('Trashed Folder')->personal()->create([
            'owner_id' => $owner->id,
            'deleted_at' => now(),
        ]);

        $fileInsideTrashedFolder = Node::factory()->file('inside.pdf', 'pdf')->personal()->create([
            'owner_id' => $owner->id,
            'parent_id' => $trashedFolder->id,
        ]);

        Share::factory()->create([
            'node_id' => $fileInsideTrashedFolder->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => $sharedUser->id,
            'permission' => SharePermission::Edit,
        ]);

        $this->assertFalse(Gate::forUser($owner)->allows('view', $trashedFile));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $trashedFile));
        $this->assertTrue(Gate::forUser($owner)->allows('restore', $trashedFile));

        $this->assertFalse(Gate::forUser($owner)->allows('view', $fileInsideTrashedFolder));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $fileInsideTrashedFolder));
        $this->assertFalse(Gate::forUser($sharedUser)->allows('view', $fileInsideTrashedFolder));
    }

    public function test_inactive_user_is_denied_for_all_actions_even_if_admin_or_owner(): void
    {
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $inactiveOwner = User::factory()->inactive()->create();

        $node = Node::factory()->shared()->create(['owner_id' => $inactiveOwner->id]);

        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('view', $node));
        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('update', $node));
        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('delete', $node));

        $this->assertFalse(Gate::forUser($inactiveOwner)->allows('view', $node));
        $this->assertFalse(Gate::forUser($inactiveOwner)->allows('update', $node));
        $this->assertFalse(Gate::forUser($inactiveOwner)->allows('delete', $node));
    }

    public function test_official_shared_folder_vs_public_dropzone_permissions_matrix(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->deptManager()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // 1. โฟลเดอร์ทางการ (Official Folder)
        $officialFolder = Node::factory()->folder('Company Policies')->shared()->create([
            'owner_id' => $admin->id,
            'is_dropzone' => false,
        ]);

        $this->assertTrue(Gate::forUser($userA)->allows('view', $officialFolder));
        $this->assertFalse(Gate::forUser($userA)->allows('upload', $officialFolder));
        $this->assertFalse(Gate::forUser($userA)->allows('update', $officialFolder));
        $this->assertFalse(Gate::forUser($userA)->allows('delete', $officialFolder));

        $this->assertTrue(Gate::forUser($manager)->allows('upload', $officialFolder));
        $this->assertTrue(Gate::forUser($manager)->allows('update', $officialFolder));
        $this->assertTrue(Gate::forUser($admin)->allows('upload', $officialFolder));

        // 2. โฟลเดอร์แลกเปลี่ยน (Public Dropzone / ที่ส่งงานส่วนกลาง)
        $dropzoneFolder = Node::factory()->dropzone()->create([
            'owner_id' => $admin->id,
        ]);

        // User ทุกคนอัปโหลดเข้า Public Dropzone ได้ แต่ลบหรือแก้ไขตัวโฟลเดอร์ Dropzone ไม่ได้
        $this->assertTrue(Gate::forUser($userA)->allows('view', $dropzoneFolder));
        $this->assertTrue(Gate::forUser($userA)->allows('upload', $dropzoneFolder));
        $this->assertFalse(Gate::forUser($userA)->allows('update', $dropzoneFolder));
        $this->assertFalse(Gate::forUser($userA)->allows('delete', $dropzoneFolder));
        $this->assertFalse(Gate::forUser($manager)->allows('delete', $dropzoneFolder));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $dropzoneFolder));

        // 3. ไฟล์ที่ User A อัปโหลดใน Public Dropzone
        $userAFile = Node::factory()->file('handover.pdf', 'pdf')->shared()->create([
            'parent_id' => $dropzoneFolder->id,
            'owner_id' => $userA->id,
        ]);

        // User A (เจ้าของไฟล์), Dept Manager และ Admin สามารถแก้ไข/ลบได้
        $this->assertTrue(Gate::forUser($userA)->allows('update', $userAFile));
        $this->assertTrue(Gate::forUser($userA)->allows('delete', $userAFile));
        $this->assertTrue(Gate::forUser($manager)->allows('update', $userAFile));
        $this->assertTrue(Gate::forUser($manager)->allows('delete', $userAFile));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $userAFile));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $userAFile));

        // User B (คนอื่น) ดูและดาวน์โหลดได้ แต่ห้ามลบหรือแก้ไขไฟล์ของ User A
        $this->assertTrue(Gate::forUser($userB)->allows('view', $userAFile));
        $this->assertTrue(Gate::forUser($userB)->allows('download', $userAFile));
        $this->assertFalse(Gate::forUser($userB)->allows('update', $userAFile));
        $this->assertFalse(Gate::forUser($userB)->allows('delete', $userAFile));
    }
}
