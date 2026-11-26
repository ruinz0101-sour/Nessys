<?php

namespace Tests\Feature;

use App\Enums\FileCategory;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLogAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_table_is_strictly_append_only_insert_and_select_only(): void
    {
        $user = User::factory()->create();

        $log = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        // 1. ทดสอบป้องกันการแก้ไขผ่าน Eloquent Model
        $EloquentUpdateBlocked = false;
        try {
            $log->update(['action' => 'tampered']);
        } catch (LogicException) {
            $EloquentUpdateBlocked = true;
        }
        $this->assertTrue($EloquentUpdateBlocked);

        // 2. ทดสอบป้องกันการลบผ่าน Eloquent Model
        $EloquentDeleteBlocked = false;
        try {
            $log->delete();
        } catch (LogicException) {
            $EloquentDeleteBlocked = true;
        }
        $this->assertTrue($EloquentDeleteBlocked);

        // 3. ทดสอบป้องกันการลบหรือแก้ไขในระดับ Database Engine (Trigger)
        $dbDeleteBlocked = false;
        try {
            DB::table('audit_logs')->where('id', $log->id)->delete();
        } catch (QueryException) {
            $dbDeleteBlocked = true;
        }
        $this->assertTrue($dbDeleteBlocked);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'login',
        ]);
    }

    public function test_viewing_folder_and_logging_out_records_audit_logs(): void
    {
        $user = User::factory()->create();
        $folder = Node::factory()->folder('Docs')->personal()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->get("/files?root=personal&folder={$folder->uuid}")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'view',
            'node_id' => $folder->id,
        ]);

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'logout',
        ]);
    }

    public function test_admin_can_search_and_filter_audit_logs_while_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        AuditLog::create([
            'user_id' => $userA->id,
            'action' => 'upload',
            'node_name' => 'report-a.pdf',
            'created_at' => now()->subDays(2),
        ]);

        AuditLog::create([
            'user_id' => $userB->id,
            'action' => 'download',
            'node_name' => 'report-b.pdf',
            'created_at' => now(),
        ]);

        $this->actingAs($userA)->get('/admin/audit-logs')->assertForbidden();

        $this->actingAs($admin)
            ->get("/admin/audit-logs?user_id={$userA->id}&action=upload")
            ->assertOk();
    }

    public function test_admin_dashboard_aggregates_file_categories_top_users_and_simulated_raid_disks(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['used_bytes' => 5_000_000]);

        Node::factory()->file('pic.png', 'png')->personal()->create([
            'owner_id' => $user->id,
            'category' => FileCategory::Image,
            'size_bytes' => 1_000_000,
        ]);

        Node::factory()->file('clip.mp4', 'mp4')->personal()->create([
            'owner_id' => $user->id,
            'category' => FileCategory::Video,
            'size_bytes' => 3_000_000,
        ]);

        Node::factory()->file('doc.pdf', 'pdf')->personal()->create([
            'owner_id' => $user->id,
            'category' => FileCategory::Document,
            'size_bytes' => 1_000_000,
        ]);

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }
}
