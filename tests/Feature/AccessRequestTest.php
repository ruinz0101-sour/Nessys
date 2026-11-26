<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\SharePermission;
use App\Mail\AccessRequestDecidedMail;
use App\Mail\AccessRequestedMail;
use App\Mail\AccessRequestReminderMail;
use App\Models\AccessRequest;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_access_with_reason_and_cannot_submit_duplicate_pending_request(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'owner@nas.local']);
        $requester = User::factory()->create(['email' => 'req@nas.local']);
        $node = Node::factory()->file('budget.xlsx', 'xlsx')->personal()->create([
            'owner_id' => $owner->id,
        ]);

        $this->actingAs($requester)->post('/access-requests', [
            'node_id' => $node->id,
            'reason' => 'ขอตรวจสอบงบประมาณประจำปี',
        ])->assertSessionHasNoErrors();

        $request = AccessRequest::where('node_id', $node->id)
            ->where('requester_id', $requester->id)
            ->firstOrFail();

        $this->assertEquals(RequestStatus::Pending, $request->status);
        $this->assertEquals('ขอตรวจสอบงบประมาณประจำปี', $request->reason);

        // ตรวจ Notification ในระบบของเจ้าของไฟล์
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'access_requested',
        ]);

        // ตรวจอีเมลส่งหาเจ้าของไฟล์
        Mail::assertSent(AccessRequestedMail::class, fn (AccessRequestedMail $mail): bool => $mail->hasTo('owner@nas.local') && ! $mail->escalatedToAdmin);

        // กันขอซ้ำขณะยังรออยู่ (pending)
        $this->actingAs($requester)->post('/access-requests', [
            'node_id' => $node->id,
            'reason' => 'ขอซ้ำอีกครั้ง',
        ])->assertSessionHasErrors('node_id');
    }

    public function test_request_auto_escalates_to_admin_when_owner_is_inactive(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email' => 'admin@nas.local']);
        $inactiveOwner = User::factory()->inactive()->create(['email' => 'inactive@nas.local']);
        $requester = User::factory()->create();

        $node = Node::factory()->file('legacy.pdf', 'pdf')->personal()->create([
            'owner_id' => $inactiveOwner->id,
        ]);

        $this->actingAs($requester)->post('/access-requests', [
            'node_id' => $node->id,
            'reason' => 'ต้องใช้งานไฟล์ของพนักงานที่ลาออก',
        ])->assertSessionHasNoErrors();

        // ต้องส่ง Notification ไปหา Admin อัตโนมัติ
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'access_request_escalated',
        ]);

        // ต้องส่งอีเมลไปหา Admin พร้อมตั้งค่า escalatedToAdmin = true
        Mail::assertSent(AccessRequestedMail::class, fn (AccessRequestedMail $mail): bool => $mail->hasTo('admin@nas.local') && $mail->escalatedToAdmin);
    }

    public function test_owner_or_admin_can_approve_with_time_limit_or_deny_and_notify_requester(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $requester = User::factory()->create(['email' => 'requester@nas.local']);
        $node = Node::factory()->personal()->create(['owner_id' => $owner->id]);

        $accessRequest = AccessRequest::factory()->create([
            'node_id' => $node->id,
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'status' => RequestStatus::Pending,
        ]);

        $grantedUntil = now()->addDays(3)->format('Y-m-d H:i:s');

        $this->actingAs($owner)->patch("/access-requests/{$accessRequest->id}/decide", [
            'status' => RequestStatus::Approved->value,
            'granted_permission' => SharePermission::Edit->value,
            'granted_until' => $grantedUntil,
            'decision_note' => 'อนุมัติให้แก้ไขได้ 3 วัน',
        ])->assertSessionHasNoErrors();

        $accessRequest->refresh();
        $this->assertEquals(RequestStatus::Approved, $accessRequest->status);
        $this->assertEquals(SharePermission::Edit, $accessRequest->granted_permission);
        $this->assertNotNull($accessRequest->granted_until);
        $this->assertEquals($owner->id, $accessRequest->decided_by);

        // ผู้ขอต้องเข้าถึงและแก้ไขไฟล์ได้ทันที
        $this->assertTrue(Gate::forUser($requester)->allows('view', $node));
        $this->assertTrue(Gate::forUser($requester)->allows('update', $node));

        // ตรวจ Notification + Email แจ้งผู้ขอ
        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'type' => 'access_approved',
        ]);

        Mail::assertSent(AccessRequestDecidedMail::class, fn (AccessRequestDecidedMail $mail): bool => $mail->hasTo('requester@nas.local'));
    }

    public function test_reminder_command_sends_reminder_after_x_days_and_escalates_if_owner_inactive(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email' => 'admin@nas.local']);
        $activeOwner = User::factory()->create(['email' => 'active.owner@nas.local']);
        $inactiveOwner = User::factory()->inactive()->create(['email' => 'inactive.owner@nas.local']);
        $requester = User::factory()->create();

        $node1 = Node::factory()->personal()->create(['owner_id' => $activeOwner->id]);
        $node2 = Node::factory()->personal()->create(['owner_id' => $inactiveOwner->id]);

        $oldRequest1 = AccessRequest::factory()->create([
            'node_id' => $node1->id,
            'requester_id' => $requester->id,
            'owner_id' => $activeOwner->id,
            'status' => RequestStatus::Pending,
            'created_at' => now()->subDays(4),
            'last_reminded_at' => null,
        ]);

        $oldRequest2 = AccessRequest::factory()->create([
            'node_id' => $node2->id,
            'requester_id' => $requester->id,
            'owner_id' => $inactiveOwner->id,
            'status' => RequestStatus::Pending,
            'created_at' => now()->subDays(5),
            'last_reminded_at' => null,
        ]);

        $this->artisan('nas:remind-access-requests --days=3')->assertExitCode(0);

        $this->assertNotNull($oldRequest1->fresh()->last_reminded_at);
        $this->assertNotNull($oldRequest2->fresh()->last_reminded_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $activeOwner->id,
            'type' => 'access_request_reminder',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'access_request_escalated',
        ]);

        Mail::assertSent(AccessRequestReminderMail::class, fn (AccessRequestReminderMail $mail): bool => $mail->hasTo('active.owner@nas.local') && ! $mail->escalatedToAdmin);
        Mail::assertSent(AccessRequestReminderMail::class, fn (AccessRequestReminderMail $mail): bool => $mail->hasTo('admin@nas.local') && $mail->escalatedToAdmin);
    }
}
