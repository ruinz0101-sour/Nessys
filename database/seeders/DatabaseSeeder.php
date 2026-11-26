<?php

namespace Database\Seeders;

use App\Enums\RaidState;
use App\Enums\RequestStatus;
use App\Enums\SharePermission;
use App\Enums\SmartStatus;
use App\Models\AccessRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DiskStatus;
use App\Models\Node;
use App\Models\RaidPool;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. สร้างแผนกตัวอย่าง
        $itDept = Department::factory()->create(['name' => 'IT']);
        $hrDept = Department::factory()->create(['name' => 'HR']);
        $financeDept = Department::factory()->create(['name' => 'Finance']);

        // 2. สร้าง Admin 1 คน (ไม่สังกัดแผนกใดๆ เป็น Super Admin อิสระ)
        $admin = User::factory()->admin()->create([
            'name' => 'System Admin',
            'email' => 'admin@nas.local',
            'department_id' => null,
        ]);

        // 3. สร้าง User 6 คน (หัวหน้าแผนก 3 คน + พนักงานทั่วไป 3 คน)
        $itManager = User::factory()->deptManager()->create([
            'name' => 'Somchai IT Manager',
            'email' => 'it.manager@nas.local',
            'department_id' => $itDept->id,
        ]);
        $itDept->update(['manager_id' => $itManager->id]);

        $hrManager = User::factory()->deptManager()->create([
            'name' => 'Suda HR Manager',
            'email' => 'hr.manager@nas.local',
            'department_id' => $hrDept->id,
        ]);
        $hrDept->update(['manager_id' => $hrManager->id]);

        $financeManager = User::factory()->deptManager()->create([
            'name' => 'Prasit Finance Manager',
            'email' => 'finance.manager@nas.local',
            'department_id' => $financeDept->id,
        ]);
        $financeDept->update(['manager_id' => $financeManager->id]);

        $itUser = User::factory()->create([
            'name' => 'Nattapong Developer',
            'email' => 'dev@nas.local',
            'department_id' => $itDept->id,
        ]);

        $hrUser = User::factory()->create([
            'name' => 'Kanya HR Officer',
            'email' => 'hr.staff@nas.local',
            'department_id' => $hrDept->id,
        ]);

        $financeUser = User::factory()->create([
            'name' => 'Wipa Accountant',
            'email' => 'accountant@nas.local',
            'department_id' => $financeDept->id,
        ]);

        // 4. สร้างโฟลเดอร์และไฟล์ตัวอย่าง (Shared / Department / Personal)
        // 4.1 โฟลเดอร์ส่วนกลาง (Shared)
        // - โฟลเดอร์ทางการ (Official Folders: เฉพาะ Admin / Dept Manager จัดการ)
        $sharedPolicyFolder = Node::factory()->folder('Company Policies')->shared()->create([
            'owner_id' => $admin->id,
        ]);

        Node::factory()->file('Employee-Handbook-2026.pdf', 'pdf')->shared()->create([
            'parent_id' => $sharedPolicyFolder->id,
            'owner_id' => $admin->id,
            'size_bytes' => 2_500_000,
        ]);

        Node::factory()->file('Company-Logo.png', 'png')->shared()->create([
            'parent_id' => $sharedPolicyFolder->id,
            'owner_id' => $admin->id,
            'size_bytes' => 450_000,
        ]);

        Node::factory()->folder('แบบฟอร์มบริษัท')->shared()->create([
            'owner_id' => $admin->id,
        ]);

        Node::factory()->folder('สื่อประชาสัมพันธ์')->shared()->create([
            'owner_id' => $admin->id,
        ]);

        // - โฟลเดอร์แลกเปลี่ยน (ที่ส่งงานส่วนกลาง: User ทุกคนอัปโหลดได้)
        $publicDropzoneFolder = Node::factory()->dropzone('ที่ส่งงานส่วนกลาง')->create([
            'owner_id' => $admin->id,
        ]);

        Node::factory()->file('Cross-Dept-Handover.pdf', 'pdf')->shared()->create([
            'parent_id' => $publicDropzoneFolder->id,
            'owner_id' => $itUser->id,
            'size_bytes' => 540_000,
        ]);

        // 4.2 โฟลเดอร์แผนก (Department)
        $itInfraFolder = Node::factory()->folder('Infrastructure Docs')->department($itDept)->create([
            'owner_id' => $itManager->id,
        ]);

        $networkSpecFile = Node::factory()->file('NAS-Architecture.pdf', 'pdf')->department($itDept)->important()->create([
            'parent_id' => $itInfraFolder->id,
            'owner_id' => $itManager->id,
            'size_bytes' => 4_200_000,
        ]);

        $hrRecruitFolder = Node::factory()->folder('Recruitment 2026')->department($hrDept)->create([
            'owner_id' => $hrManager->id,
        ]);

        Node::factory()->file('Candidate-Evaluation.xlsx', 'xlsx')->department($hrDept)->create([
            'parent_id' => $hrRecruitFolder->id,
            'owner_id' => $hrUser->id,
            'size_bytes' => 820_000,
        ]);

        // 4.3 โฟลเดอร์ส่วนตัว (Personal)
        $devPersonalFolder = Node::factory()->folder('My Projects')->personal()->create([
            'owner_id' => $itUser->id,
        ]);

        $devReportFile = Node::factory()->file('Sprint-Summary.docx', 'docx')->personal()->create([
            'parent_id' => $devPersonalFolder->id,
            'owner_id' => $itUser->id,
            'size_bytes' => 350_000,
        ]);

        $financePersonalFile = Node::factory()->file('Q4-Budget-Draft.xlsx', 'xlsx')->personal()->important()->create([
            'owner_id' => $financeManager->id,
            'size_bytes' => 1_800_000,
        ]);

        // อัปเดต used_bytes ของผู้ใช้ให้สอดคล้องกับไฟล์ตัวอย่าง
        foreach ([$admin, $itManager, $hrManager, $financeManager, $itUser, $hrUser, $financeUser] as $user) {
            $totalSize = Node::where('owner_id', $user->id)->sum('size_bytes');
            $user->update(['used_bytes' => $totalSize]);
        }

        // 5. ตัวอย่างการแชร์ไฟล์ (Shares)
        Share::factory()->create([
            'node_id' => $devReportFile->id,
            'shared_by' => $itUser->id,
            'shared_with_user_id' => $itManager->id,
            'permission' => SharePermission::Edit,
        ]);

        Share::factory()->create([
            'node_id' => $itInfraFolder->id,
            'shared_by' => $itManager->id,
            'shared_with_user_id' => null,
            'shared_with_department_id' => $financeDept->id,
            'permission' => SharePermission::View,
        ]);

        // 6. ตัวอย่างคำขอเข้าถึงไฟล์ (Access Requests)
        AccessRequest::factory()->create([
            'node_id' => $financePersonalFile->id,
            'requester_id' => $financeUser->id,
            'owner_id' => $financeManager->id,
            'reason' => 'ขอตรวจสอบตัวเลขงบประมาณไตรมาส 4',
            'status' => RequestStatus::Pending,
        ]);

        // 7. ตัวอย่าง Audit Log
        AuditLog::factory()->create([
            'user_id' => $admin->id,
            'action' => 'login',
            'ip' => '127.0.0.1',
            'meta' => ['role' => 'admin'],
        ]);

        AuditLog::factory()->create([
            'user_id' => $itManager->id,
            'action' => 'upload',
            'node_id' => $networkSpecFile->id,
            'node_name' => $networkSpecFile->name,
            'ip' => '127.0.0.1',
            'meta' => ['size_bytes' => $networkSpecFile->size_bytes],
        ]);

        // 8. ข้อมูลจำลอง RAID Pools และ Disk Status (System Monitoring)
        $pool = RaidPool::create([
            'name' => 'md0 (Primary Storage Pool)',
            'level' => 'RAID5',
            'state' => RaidState::Healthy,
            'total_bytes' => 12 * 1024 * 1024 * 1024 * 1024,
            'used_bytes' => 3 * 1024 * 1024 * 1024 * 1024,
            'checked_at' => now(),
        ]);

        $fourTb = 4 * 1024 * 1024 * 1024 * 1024;
        $oneTb = 1024 * 1024 * 1024 * 1024;

        DiskStatus::create([
            'pool_id' => $pool->id,
            'device' => '/dev/sda',
            'model' => 'WD Red Plus 4TB (WD40EFPX)',
            'serial' => 'WD-WX12A901',
            'size_bytes' => $fourTb,
            'used_bytes' => $oneTb,
            'temperature_c' => 36,
            'power_on_hours' => 4120,
            'reallocated_sectors' => 0,
            'smart' => SmartStatus::Ok,
            'checked_at' => now(),
        ]);

        DiskStatus::create([
            'pool_id' => $pool->id,
            'device' => '/dev/sdb',
            'model' => 'WD Red Plus 4TB (WD40EFPX)',
            'serial' => 'WD-WX12A902',
            'size_bytes' => $fourTb,
            'used_bytes' => $oneTb,
            'temperature_c' => 37,
            'power_on_hours' => 4120,
            'reallocated_sectors' => 0,
            'smart' => SmartStatus::Ok,
            'checked_at' => now(),
        ]);

        DiskStatus::create([
            'pool_id' => $pool->id,
            'device' => '/dev/sdc',
            'model' => 'Seagate IronWolf 4TB (ST4000VN006)',
            'serial' => 'ZDH08K11',
            'size_bytes' => $fourTb,
            'used_bytes' => $oneTb,
            'temperature_c' => 41,
            'power_on_hours' => 8760,
            'reallocated_sectors' => 2,
            'smart' => SmartStatus::Warning,
            'checked_at' => now(),
        ]);

        DiskStatus::create([
            'pool_id' => $pool->id,
            'device' => '/dev/sdd',
            'model' => 'Seagate IronWolf 4TB (ST4000VN006)',
            'serial' => 'ZDH08K12',
            'size_bytes' => $fourTb,
            'used_bytes' => $oneTb,
            'temperature_c' => 35,
            'power_on_hours' => 4118,
            'reallocated_sectors' => 0,
            'smart' => SmartStatus::Ok,
            'checked_at' => now(),
        ]);
    }
}
