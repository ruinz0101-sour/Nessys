<?php

namespace Tests\Feature;

use App\Enums\RootType;
use App\Enums\SharePermission;
use App\Models\Department;
use App\Models\Node;
use App\Models\Share;
use App\Models\ShareLink;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_share_node_with_user_and_recipient_receives_notification(): void
    {
        $owner = User::factory()->create(['name' => 'Owner User']);
        $recipient = User::factory()->create(['name' => 'Recipient User']);
        $node = Node::factory()->file('contract.pdf', 'pdf')->personal()->create([
            'owner_id' => $owner->id,
        ]);

        $expiresAt = now()->addDays(7)->format('Y-m-d H:i:s');

        $this->actingAs($owner)->post('/shares', [
            'node_id' => $node->id,
            'shared_with_user_id' => $recipient->id,
            'permission' => SharePermission::Edit->value,
            'expires_at' => $expiresAt,
        ])->assertSessionHasNoErrors();

        $share = Share::where('node_id', $node->id)
            ->where('shared_with_user_id', $recipient->id)
            ->firstOrFail();

        $this->assertEquals(SharePermission::Edit, $share->permission);
        $this->assertNotNull($share->expires_at);
        $this->assertNull($share->revoked_at);

        // ตรวจว่าผู้รับได้รับสิทธิ์ผ่าน NodePolicy
        $this->assertTrue(Gate::forUser($recipient)->allows('view', $node));
        $this->assertTrue(Gate::forUser($recipient)->allows('update', $node));

        // ตรวจว่ามีการสร้าง Notification แจ้งเตือนผู้รับ
        $notification = UserNotification::where('user_id', $recipient->id)
            ->where('type', 'file_shared')
            ->firstOrFail();

        $this->assertEquals($node->id, $notification->data['node_id']);
        $this->assertEquals('edit', $notification->data['permission']);
        $this->assertNull($notification->read_at);

        // ผู้รับกดอ่านแจ้งเตือน
        $this->actingAs($recipient)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_revoking_share_sets_revoked_at_without_deleting_row_and_removes_access(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        $share = Share::factory()->create([
            'node_id' => $node->id,
            'shared_by' => $owner->id,
            'shared_with_user_id' => $recipient->id,
            'permission' => SharePermission::View,
            'revoked_at' => null,
        ]);

        $this->assertTrue(Gate::forUser($recipient)->allows('view', $node));

        $this->actingAs($owner)
            ->patch("/shares/{$share->id}/revoke")
            ->assertRedirect();

        // แถวในตาราง shares ต้องยังอยู่ แต่มีค่า revoked_at
        $this->assertDatabaseHas('shares', ['id' => $share->id]);
        $this->assertNotNull($share->fresh()->revoked_at);

        // สิทธิ์ของผู้รับต้องถูกเพิกถอนทันที
        $this->assertFalse(Gate::forUser($recipient)->allows('view', $node));
    }

    public function test_user_cannot_share_to_self_or_share_others_private_node(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $recipient = User::factory()->create();
        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        // ห้ามแชร์ให้ตัวเอง
        $this->actingAs($owner)->post('/shares', [
            'node_id' => $node->id,
            'shared_with_user_id' => $owner->id,
            'permission' => SharePermission::View->value,
        ])->assertSessionHasErrors('shared_with_user_id');

        // คนอื่นที่ไม่ใช่เจ้าของห้ามเอาไฟล์ไปแชร์ต่อ
        $this->actingAs($stranger)->post('/shares', [
            'node_id' => $node->id,
            'shared_with_user_id' => $recipient->id,
            'permission' => SharePermission::View->value,
        ])->assertForbidden();
    }

    public function test_shares_index_page_loads_shared_with_me_and_shared_by_me(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $nodeA = Node::factory()->personal()->create(['owner_id' => $userA->id, 'name' => 'From A.pdf']);
        $nodeB = Node::factory()->personal()->create(['owner_id' => $userB->id, 'name' => 'From B.pdf']);

        Share::factory()->create([
            'node_id' => $nodeA->id,
            'shared_by' => $userA->id,
            'shared_with_user_id' => $userB->id,
            'permission' => SharePermission::View,
        ]);

        Share::factory()->create([
            'node_id' => $nodeB->id,
            'shared_by' => $userB->id,
            'shared_with_user_id' => $userA->id,
            'permission' => SharePermission::Edit,
        ]);

        $this->actingAs($userA)
            ->get('/shares')
            ->assertOk();
    }

    public function test_owner_can_share_node_with_department_and_dept_manager_can_create_shared_folder(): void
    {
        $hrDept = Department::factory()->create(['name' => 'HR']);
        $financeDept = Department::factory()->create(['name' => 'Finance']);

        $hrManager = User::factory()->deptManager()->create(['department_id' => $hrDept->id]);
        $financeUser = User::factory()->create(['department_id' => $financeDept->id]);

        // 1. Dept Manager สามารถสร้างโฟลเดอร์ใน โฟลเดอร์ส่วนกลาง (Shared) ได้
        $this->actingAs($hrManager)->post('/files/folders', [
            'name' => 'HR Public Announcements',
            'root_type' => RootType::Shared->value,
            'parent_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nodes', [
            'name' => 'HR Public Announcements',
            'root_type' => RootType::Shared->value,
        ]);

        // 2. แชร์ไฟล์ให้ทั้งแผนก Finance
        $hrPrivateNode = Node::factory()->personal()->create(['owner_id' => $hrManager->id]);

        $this->actingAs($hrManager)->post('/shares', [
            'node_id' => $hrPrivateNode->id,
            'shared_with_department_id' => $financeDept->id,
            'permission' => SharePermission::View->value,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Gate::forUser($financeUser)->allows('view', $hrPrivateNode));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $financeUser->id,
            'type' => 'file_shared',
        ]);
    }

    public function test_owner_can_create_public_url_share_link_with_password_limit_and_revoke(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $uploaded = UploadedFile::fake()->createWithContent('public-doc.txt', 'hello via url');
        $this->actingAs($owner)->post('/files/upload', [
            'files' => [$uploaded],
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ])->assertSessionHasNoErrors();

        $node = Node::where('name', 'public-doc.txt')->firstOrFail();

        // คนอื่นที่ไม่ใช่เจ้าของห้ามสร้างลิงก์แชร์
        $this->actingAs($stranger)->postJson('/share-links', [
            'node_id' => $node->id,
        ])->assertForbidden();

        // เจ้าของสร้างลิงก์แชร์พร้อมรหัสผ่านและจำกัดดาวน์โหลด 1 ครั้ง
        $response = $this->actingAs($owner)->postJson('/share-links', [
            'node_id' => $node->id,
            'password' => 'secret123',
            'max_downloads' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('share_link.has_password', true)
            ->assertJsonPath('share_link.max_downloads', 1);

        $token = (string) $response->json('share_link.token');
        $shareLink = ShareLink::where('token', $token)->firstOrFail();

        // ผู้เยี่ยมชมที่ไม่ได้ล็อกอิน: เปิดดูได้ (แต่ต้องกรอกรหัสผ่านก่อนดาวน์โหลด)
        $this->app['auth']->forgetGuards();
        $this->get("/s/{$token}")->assertOk();
        $this->get("/s/{$token}/download")->assertForbidden();

        // กรอกรหัสผ่านผิด
        $this->post("/s/{$token}/unlock", ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        // กรอกรหัสผ่านถูก -> ดาวน์โหลดได้ 1 ครั้ง
        $this->post("/s/{$token}/unlock", ['password' => 'secret123'])
            ->assertRedirect("/s/{$token}");

        $downloadResponse = $this->get("/s/{$token}/download");
        $downloadResponse->assertOk();
        $this->assertSame('hello via url', $downloadResponse->streamedContent());
        $this->assertSame(1, $shareLink->fresh()->download_count);

        // ดาวน์โหลดครั้งที่ 2 เกิน max_downloads -> ถูกบล็อก
        $this->get("/s/{$token}/download")->assertNotFound();

        // ทดสอบเพิกถอนลิงก์
        $activeLink = ShareLink::factory()->create([
            'node_id' => $node->id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->patch("/share-links/{$activeLink->id}/revoke")
            ->assertRedirect();

        $this->assertNotNull($activeLink->fresh()->revoked_at);
        $this->get("/s/{$activeLink->token}/download")->assertNotFound();
    }
}
