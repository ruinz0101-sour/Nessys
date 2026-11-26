<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FileCategory;
use App\Enums\NodeType;
use App\Enums\RaidState;
use App\Enums\SmartStatus;
use App\Http\Controllers\Controller;
use App\Models\DiskStatus;
use App\Models\Node;
use App\Models\RaidPool;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        $categoryStats = [];
        foreach (FileCategory::cases() as $category) {
            $query = Node::query()
                ->where('type', NodeType::File)
                ->where('category', $category);

            $categoryStats[$category->value] = [
                'category' => $category->value,
                'count' => (int) (clone $query)->count(),
                'size_bytes' => (int) (clone $query)->sum('size_bytes'),
            ];
        }

        $totalFiles = (int) Node::query()->where('type', NodeType::File)->count();
        $totalFolders = (int) Node::query()->where('type', NodeType::Folder)->count();
        $totalUsedBytes = (int) Node::withTrashed()->where('type', NodeType::File)->sum('size_bytes');
        $trashedCount = (int) Node::onlyTrashed()->count();

        $topUsers = User::query()
            ->with('department:id,name')
            ->withCount(['nodes as files_count' => function ($query): void {
                $query->where('type', NodeType::File);
            }])
            ->orderByDesc('used_bytes')
            ->limit(5)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatarUrl(),
                'department_name' => $user->department?->name,
                'used_bytes' => $user->used_bytes,
                'quota_bytes' => $user->quota_bytes,
                'files_count' => $user->files_count,
            ]);

        $raidPools = $this->getRaidAndDiskData($totalUsedBytes);

        return Inertia::render('Admin/Dashboard', [
            'summary' => [
                'total_files' => $totalFiles,
                'total_folders' => $totalFolders,
                'total_used_bytes' => $totalUsedBytes,
                'trashed_count' => $trashedCount,
                'categories' => $categoryStats,
            ],
            'topUsers' => $topUsers,
            'raidPools' => $raidPools,
        ]);
    }

    /**
     * Get RAID pools and disk SMART status from database or return simulated data when empty.
     * Uses actual uploaded file bytes from nodes table for pool usage.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getRaidAndDiskData(int $totalUsedBytes): array
    {
        $pools = RaidPool::query()->with('disks')->get();
        $checkedAt = now()->toIso8601String();

        if ($pools->isNotEmpty()) {
            return $pools->values()->map(function (RaidPool $pool, int $index) use ($totalUsedBytes, $checkedAt): array {
                $effectiveUsedBytes = $index === 0
                    ? min($pool->total_bytes, max(0, $totalUsedBytes))
                    : $pool->used_bytes;
                $dataDiskDivisor = max(1, $pool->disks->count() - 1);

                return [
                    'id' => $pool->id,
                    'name' => $pool->name,
                    'level' => $pool->level,
                    'state' => $pool->state instanceof RaidState ? $pool->state->value : $pool->state,
                    'total_bytes' => $pool->total_bytes,
                    'used_bytes' => $effectiveUsedBytes,
                    'checked_at' => $checkedAt,
                    'disks' => $pool->disks->map(fn (DiskStatus $disk): array => [
                        'id' => $disk->id,
                        'device' => $disk->device,
                        'model' => $disk->model,
                        'serial' => $disk->serial,
                        'size_bytes' => $disk->size_bytes,
                        'used_bytes' => $index === 0 ? (int) ($effectiveUsedBytes / $dataDiskDivisor) : $disk->used_bytes,
                        'temperature_c' => $disk->temperature_c,
                        'power_on_hours' => $disk->power_on_hours,
                        'reallocated_sectors' => $disk->reallocated_sectors,
                        'smart' => $disk->smart instanceof SmartStatus ? $disk->smart->value : $disk->smart,
                        'checked_at' => $checkedAt,
                    ])->all(),
                ];
            })->all();
        }

        // ข้อมูลจำลองระดับฮาร์ดแวร์ แต่ใช้ยอดพื้นที่ใช้งานจริงจากไฟล์ที่อัปโหลดในระบบ
        $fourTb = 4 * 1024 * 1024 * 1024 * 1024;
        $twelveTb = 12 * 1024 * 1024 * 1024 * 1024;
        $usedPoolBytes = min($twelveTb, max(0, $totalUsedBytes));

        return [
            [
                'id' => 1,
                'name' => 'md0 (Primary Storage Pool)',
                'level' => 'RAID5',
                'state' => RaidState::Healthy->value,
                'total_bytes' => $twelveTb,
                'used_bytes' => $usedPoolBytes,
                'checked_at' => $checkedAt,
                'disks' => [
                    [
                        'id' => 1,
                        'device' => '/dev/sda',
                        'model' => 'WD Red Plus 4TB (WD40EFPX)',
                        'serial' => 'WD-WX12A901',
                        'size_bytes' => $fourTb,
                        'used_bytes' => (int) ($usedPoolBytes / 3),
                        'temperature_c' => 36,
                        'power_on_hours' => 4120,
                        'reallocated_sectors' => 0,
                        'smart' => SmartStatus::Ok->value,
                        'checked_at' => $checkedAt,
                    ],
                    [
                        'id' => 2,
                        'device' => '/dev/sdb',
                        'model' => 'WD Red Plus 4TB (WD40EFPX)',
                        'serial' => 'WD-WX12A902',
                        'size_bytes' => $fourTb,
                        'used_bytes' => (int) ($usedPoolBytes / 3),
                        'temperature_c' => 37,
                        'power_on_hours' => 4120,
                        'reallocated_sectors' => 0,
                        'smart' => SmartStatus::Ok->value,
                        'checked_at' => $checkedAt,
                    ],
                    [
                        'id' => 3,
                        'device' => '/dev/sdc',
                        'model' => 'Seagate IronWolf 4TB (ST4000VN006)',
                        'serial' => 'ZDH08K11',
                        'size_bytes' => $fourTb,
                        'used_bytes' => (int) ($usedPoolBytes / 3),
                        'temperature_c' => 41,
                        'power_on_hours' => 8760,
                        'reallocated_sectors' => 2,
                        'smart' => SmartStatus::Warning->value,
                        'checked_at' => $checkedAt,
                    ],
                    [
                        'id' => 4,
                        'device' => '/dev/sdd',
                        'model' => 'Seagate IronWolf 4TB (ST4000VN006)',
                        'serial' => 'ZDH08K12',
                        'size_bytes' => $fourTb,
                        'used_bytes' => (int) ($usedPoolBytes / 3),
                        'temperature_c' => 35,
                        'power_on_hours' => 4118,
                        'reallocated_sectors' => 0,
                        'smart' => SmartStatus::Ok->value,
                        'checked_at' => $checkedAt,
                    ],
                ],
            ],
        ];
    }
}
