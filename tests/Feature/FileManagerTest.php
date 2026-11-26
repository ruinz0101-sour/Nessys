<?php

namespace Tests\Feature;

use App\Enums\FileCategory;
use App\Enums\RootType;
use App\Models\Node;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_create_rename_duplicate_prevention_and_move_cycle_prevention(): void
    {
        $user = User::factory()->create();

        // สร้างโฟลเดอร์ A และ B
        $this->actingAs($user)->post('/files/folders', [
            'name' => 'Folder A',
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ])->assertRedirect();

        $this->actingAs($user)->post('/files/folders', [
            'name' => 'Folder B',
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ])->assertRedirect();

        $folderA = Node::where('name', 'Folder A')->firstOrFail();
        $folderB = Node::where('name', 'Folder B')->firstOrFail();

        // กันสร้างชื่อซ้ำในโฟลเดอร์เดียวกัน
        $this->actingAs($user)->post('/files/folders', [
            'name' => 'Folder A',
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ])->assertSessionHasErrors('name');

        // กันเปลี่ยนชื่อซ้ำกับโฟลเดอร์ที่มีอยู่
        $this->actingAs($user)->patch("/files/{$folderB->uuid}/rename", [
            'name' => 'Folder A',
        ])->assertSessionHasErrors('name');

        // สร้างโฟลเดอร์ย่อย Child ใน Folder A
        $this->actingAs($user)->post('/files/folders', [
            'name' => 'Child Folder',
            'root_type' => RootType::Personal->value,
            'parent_id' => $folderA->id,
        ])->assertRedirect();

        $childFolder = Node::where('name', 'Child Folder')->firstOrFail();

        // กันย้าย Folder A เข้าตัวเอง
        $this->actingAs($user)->patch("/files/{$folderA->uuid}/move", [
            'target_parent_id' => $folderA->id,
        ])->assertSessionHasErrors('target_parent_id');

        // กันย้าย Folder A เข้า Child Folder (ลูกของตัวเอง)
        $this->actingAs($user)->patch("/files/{$folderA->uuid}/move", [
            'target_parent_id' => $childFolder->id,
        ])->assertSessionHasErrors('target_parent_id');

        // ย้าย Child Folder ไปไว้ใน Folder B สำเร็จ
        $this->actingAs($user)->patch("/files/{$childFolder->uuid}/move", [
            'target_parent_id' => $folderB->id,
        ])->assertSessionHasNoErrors();

        $this->assertEquals($folderB->id, $childFolder->fresh()->parent_id);
    }

    public function test_multi_file_upload_stores_uuid_outside_public_calculates_metadata_and_enforces_quota(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'quota_bytes' => 5000,
            'used_bytes' => 0,
        ]);

        $file1 = UploadedFile::fake()->createWithContent('report.pdf', '%PDF-1.4 sample content 12345');
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file2 = UploadedFile::fake()->createWithContent('photo.png', $pngBytes);

        $this->actingAs($user)->post('/files/upload', [
            'files' => [$file1, $file2],
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ])->assertSessionHasNoErrors();

        $reportNode = Node::where('name', 'report.pdf')->firstOrFail();
        $photoNode = Node::where('name', 'photo.png')->firstOrFail();

        $this->assertEquals(FileCategory::Document, $reportNode->category);
        $this->assertEquals(FileCategory::Image, $photoNode->category);
        $this->assertNotNull($reportNode->sha256);
        $this->assertEquals(64, strlen($reportNode->sha256));
        $this->assertStringContainsString($reportNode->uuid, (string) $reportNode->storage_path);
        Storage::disk('local')->assertExists($reportNode->storage_path);

        $user->refresh();
        $this->assertEquals($reportNode->size_bytes + $photoNode->size_bytes, $user->used_bytes);

        // ทดสอบอัปโหลดเกิน Quota -> ต้องถูกบล็อก
        $oversizedFile = UploadedFile::fake()->create('huge.zip', 10); // 10 KB > 5000 bytes
        $this->actingAs($user)->post('/files/upload', [
            'files' => [$oversizedFile],
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ])->assertSessionHasErrors('files');
    }

    public function test_download_checks_permission_every_time(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $uploaded = UploadedFile::fake()->createWithContent('secret.txt', 'confidential data');

        $this->actingAs($owner)->post('/files/upload', [
            'files' => [$uploaded],
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ]);

        $node = Node::where('name', 'secret.txt')->firstOrFail();

        $this->actingAs($owner)
            ->get("/files/{$node->uuid}/download")
            ->assertOk();

        $this->actingAs($stranger)
            ->get("/files/{$node->uuid}/download")
            ->assertForbidden();
    }

    public function test_trash_soft_delete_restore_scheduled_purge_and_quota_recalculation(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'quota_bytes' => 10_000_000,
            'used_bytes' => 0,
        ]);

        $file = UploadedFile::fake()->createWithContent('temp.txt', 'temporary file contents');

        $this->actingAs($user)->post('/files/upload', [
            'files' => [$file],
            'root_type' => RootType::Personal->value,
            'parent_id' => null,
        ]);

        $node = Node::where('name', 'temp.txt')->firstOrFail();
        $storagePath = $node->storage_path;

        // ลบลงถังขยะ
        $this->actingAs($user)->delete("/files/{$node->uuid}")->assertRedirect();

        $trashed = Node::withTrashed()->findOrFail($node->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertNotNull($trashed->purge_at);
        $this->assertEquals($user->id, $trashed->deleted_by);

        // กู้คืนจากถังขยะ
        $this->actingAs($user)->post("/trash/{$node->uuid}/restore")->assertRedirect();
        $this->assertNull($node->fresh()->deleted_at);
        $this->assertNull($node->fresh()->purge_at);

        // ลบอีกครั้งและจำลองว่าครบกำหนด 30 วัน (purge_at <= now())
        $this->actingAs($user)->delete("/files/{$node->uuid}");
        Node::onlyTrashed()->where('id', $node->id)->update([
            'purge_at' => now()->subMinute(),
        ]);

        $this->artisan('nas:purge-trash')->assertExitCode(0);

        $this->assertNull(Node::withTrashed()->find($node->id));
        Storage::disk('local')->assertMissing($storagePath);
        $this->assertEquals(0, $user->fresh()->used_bytes);

        // ทดสอบคำสั่งตรวจนับ Quota ซ้ำทุกคืน (nas:recalculate-quotas)
        $user->update(['used_bytes' => 999999]);
        $this->artisan('nas:recalculate-quotas')->assertExitCode(0);
        $this->assertEquals(0, $user->fresh()->used_bytes);
    }

    public function test_public_dropzone_allows_all_users_to_upload_but_forbids_editing_or_deleting_others_files(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $manager = User::factory()->deptManager()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $officialFolder = Node::factory()->folder('Official Policy')->shared()->create([
            'owner_id' => $admin->id,
            'is_dropzone' => false,
        ]);

        $dropzoneFolder = Node::factory()->dropzone()->create([
            'owner_id' => $admin->id,
        ]);

        // 0. เปิดหน้า /files?root=shared และหน้าโฟลเดอร์ Public Dropzone ได้สำเร็จ
        $this->actingAs($userA)->get('/files?root=shared')->assertOk();
        $this->actingAs($userA)->get("/files?root=shared&folder={$dropzoneFolder->uuid}")->assertOk();

        // 1. User ทั่วไปไม่สามารถอัปโหลดไฟล์ลงโฟลเดอร์ทางการ (Official Folder) ได้
        $blockedFile = UploadedFile::fake()->createWithContent('unauthorized.pdf', '%PDF-1.4 blocked');
        $this->actingAs($userA)->post('/files/upload', [
            'files' => [$blockedFile],
            'root_type' => RootType::Shared->value,
            'parent_id' => $officialFolder->id,
        ])->assertForbidden();

        // 2. User A อัปโหลดไฟล์ส่งงานเข้า Public Dropzone ได้สำเร็จ
        $workFile = UploadedFile::fake()->createWithContent('project-submission.pdf', '%PDF-1.4 submission');
        $this->actingAs($userA)->post('/files/upload', [
            'files' => [$workFile],
            'root_type' => RootType::Shared->value,
            'parent_id' => $dropzoneFolder->id,
        ])->assertSessionHasNoErrors();

        $submittedNode = Node::where('name', 'project-submission.pdf')->firstOrFail();
        $this->assertSame($dropzoneFolder->id, $submittedNode->parent_id);
        $this->assertSame($userA->id, $submittedNode->owner_id);

        // 3. User B ดาวน์โหลดได้ แต่ห้ามเปลี่ยนชื่อ ห้ามย้าย และห้ามลบไฟล์ของ User A
        $this->actingAs($userB)
            ->get("/files/{$submittedNode->uuid}/download")
            ->assertOk();

        $this->actingAs($userB)
            ->patch("/files/{$submittedNode->uuid}/rename", ['name' => 'hacked.pdf'])
            ->assertForbidden();

        $this->actingAs($userB)
            ->delete("/files/{$submittedNode->uuid}")
            ->assertForbidden();

        // 4. Dept Manager สามารถลบไฟล์ใน Public Dropzone ได้
        $this->actingAs($manager)
            ->delete("/files/{$submittedNode->uuid}")
            ->assertRedirect();

        $this->assertSoftDeleted('nodes', ['id' => $submittedNode->id]);
    }

    public function test_chunked_upload_reassembles_file_enforces_quota_and_supports_cancel(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'quota_bytes' => 50,
            'used_bytes' => 0,
        ]);

        $uploadId = '11111111-1111-4111-8111-111111111111';
        $part1 = 'Hello ';
        $part2 = 'Chunked Video!';
        $fullContent = $part1.$part2;

        // ส่งชิ้นที่ 1 (ไม่ส่ง parent_id เพื่อจำลองการอัปโหลดเข้าโฟลเดอร์ระดับบนสุดจาก FormData)
        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => $uploadId,
                'chunk' => UploadedFile::fake()->createWithContent('clip.mp4', $part1),
                'chunk_index' => 0,
                'total_chunks' => 2,
                'file_name' => 'clip.mp4',
                'file_size' => strlen($fullContent),
                'root_type' => RootType::Personal->value,
            ])
            ->assertOk()
            ->assertJson(['status' => 'chunk_received', 'chunk_index' => 0]);

        // ส่งชิ้นที่ 2 (ชิ้นสุดท้าย) -> รวมไฟล์และสร้าง Node
        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => $uploadId,
                'chunk' => UploadedFile::fake()->createWithContent('clip.mp4', $part2),
                'chunk_index' => 1,
                'total_chunks' => 2,
                'file_name' => 'clip.mp4',
                'file_size' => strlen($fullContent),
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertOk()
            ->assertJson(['status' => 'completed', 'name' => 'clip.mp4']);

        $node = Node::where('name', 'clip.mp4')->firstOrFail();
        $this->assertSame(strlen($fullContent), $node->size_bytes);
        $this->assertSame($fullContent, Storage::disk('local')->get($node->storage_path));
        $this->assertSame(strlen($fullContent), $user->fresh()->used_bytes);
        Storage::disk('local')->assertMissing("chunks/{$user->id}/{$uploadId}.part");

        // ทดสอบเกิน Quota -> ต้องถูกบล็อกตั้งแต่ชิ้นแรก
        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => '22222222-2222-4222-8222-222222222222',
                'chunk' => UploadedFile::fake()->createWithContent('too-big.mp4', '12345'),
                'chunk_index' => 0,
                'total_chunks' => 2,
                'file_name' => 'too-big.mp4',
                'file_size' => 100,
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);

        // ทดสอบยกเลิกการอัปโหลดกลางทาง -> ลบไฟล์ .part ชั่วคราว
        $cancelId = '33333333-3333-4333-8333-333333333333';
        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => $cancelId,
                'chunk' => UploadedFile::fake()->createWithContent('cancel.mp4', 'abc'),
                'chunk_index' => 0,
                'total_chunks' => 2,
                'file_name' => 'cancel.mp4',
                'file_size' => 10,
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertOk();

        Storage::disk('local')->assertExists("chunks/{$user->id}/{$cancelId}.part");

        $this->actingAs($user)
            ->deleteJson("/files/upload/chunk/{$cancelId}")
            ->assertNoContent();

        Storage::disk('local')->assertMissing("chunks/{$user->id}/{$cancelId}.part");

        // ทดสอบอัปโหลดแบบ Parallel (ส่งชิ้นที่ 2 ก่อนชิ้นที่ 1 ด้วย chunk_offset + defer_finalize แล้วค่อย complete)
        $parallelId = '44444444-4444-4444-8444-444444444444';
        $p0 = 'AAAA';
        $p1 = 'BBBB';
        $p2 = 'CCCC';

        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => $parallelId,
                'chunk' => UploadedFile::fake()->createWithContent('parallel.mp4', $p0),
                'chunk_index' => 0,
                'total_chunks' => 3,
                'chunk_offset' => 0,
                'defer_finalize' => true,
                'file_name' => 'parallel.mp4',
                'file_size' => 12,
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertOk();

        // ส่งชิ้นที่ 2 ก่อนชิ้นที่ 1
        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => $parallelId,
                'chunk' => UploadedFile::fake()->createWithContent('parallel.mp4', $p2),
                'chunk_index' => 2,
                'total_chunks' => 3,
                'chunk_offset' => 8,
                'defer_finalize' => true,
                'file_name' => 'parallel.mp4',
                'file_size' => 12,
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/files/upload/chunk', [
                'upload_id' => $parallelId,
                'chunk' => UploadedFile::fake()->createWithContent('parallel.mp4', $p1),
                'chunk_index' => 1,
                'total_chunks' => 3,
                'chunk_offset' => 4,
                'defer_finalize' => true,
                'file_name' => 'parallel.mp4',
                'file_size' => 12,
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/files/upload/chunk/complete', [
                'upload_id' => $parallelId,
                'file_name' => 'parallel.mp4',
                'root_type' => RootType::Personal->value,
                'parent_id' => null,
            ])
            ->assertOk()
            ->assertJson(['status' => 'completed', 'name' => 'parallel.mp4']);

        $parallelNode = Node::where('name', 'parallel.mp4')->firstOrFail();
        $this->assertSame('AAAABBBBCCCC', Storage::disk('local')->get($parallelNode->storage_path));

        // ทดสอบ HTTP Range Request (206 Partial Content) เพื่อให้เบราว์เซอร์กรอวิดีโอได้
        $rangeResponse = $this->actingAs($user)
            ->withHeaders(['Range' => 'bytes=4-7'])
            ->get("/files/{$parallelNode->uuid}/preview");

        $rangeResponse->assertStatus(206);
        $rangeResponse->assertHeader('Accept-Ranges', 'bytes');
        $rangeResponse->assertHeader('Content-Range', 'bytes 4-7/12');
    }
}
