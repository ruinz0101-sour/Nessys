<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserController extends Controller
{
    public const int BYTES_PER_GB = 1073741824;

    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('search')->trim()->toString();
        $roleFilter = $request->string('role')->trim()->toString();
        $deptFilter = $request->string('department_id')->trim()->toString();

        $users = User::query()
            ->with('department')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter): void {
                $query->where('role', $roleFilter);
            })
            ->when($deptFilter !== '', function ($query) use ($deptFilter): void {
                $query->where('department_id', (int) $deptFilter);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatarUrl(),
                'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                'department_id' => $user->department_id,
                'department_name' => $user->department?->name,
                'quota_bytes' => $user->quota_bytes,
                'quota_gb' => round($user->quota_bytes / self::BYTES_PER_GB, 2),
                'used_bytes' => $user->used_bytes,
                'is_active' => $user->is_active,
                'failed_login_count' => $user->failed_login_count,
                'is_locked' => $user->locked_until !== null && $user->locked_until->isFuture(),
                'locked_until' => $user->locked_until?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
            ]);

        $departments = Department::query()
            ->with('manager:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'manager_id' => $department->manager_id,
                'manager_name' => $department->manager?->name,
                'users_count' => $department->users_count,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'departments' => $departments,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'department_id' => $deptFilter,
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $quotaBytes = (int) round(((float) $validated['quota_gb']) * self::BYTES_PER_GB);
        $role = $validated['role'];
        $departmentId = ($role === UserRole::Admin->value || $role === UserRole::Admin) ? null : ($validated['department_id'] ?? null);

        $user = User::create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'department_id' => $departmentId,
            'quota_bytes' => $quotaBytes,
            'used_bytes' => 0,
            'is_active' => (bool) $validated['is_active'],
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'create_user',
                'target_user_id' => $user->id,
                'target_email' => $user->email,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "สร้างบัญชีผู้ใช้ {$user->name} เรียบร้อยแล้ว");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $quotaBytes = (int) round(((float) $validated['quota_gb']) * self::BYTES_PER_GB);

        $isActive = (bool) $validated['is_active'];
        if ($user->id === $request->user()?->id && ! $isActive) {
            return back()->with('error', 'ไม่สามารถปิดบัญชีของตนเองได้');
        }

        $role = $validated['role'];
        $departmentId = ($role === UserRole::Admin->value || $role === UserRole::Admin) ? null : ($validated['department_id'] ?? null);

        $user->update([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role' => $role,
            'department_id' => $departmentId,
            'quota_bytes' => $quotaBytes,
            'is_active' => $isActive,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'update_user',
                'target_user_id' => $user->id,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "อัปเดตข้อมูล {$user->name} เรียบร้อยแล้ว");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()?->id) {
            return back()->with('error', 'ไม่สามารถปิดบัญชีของตนเองได้');
        }

        $newActive = ! $user->is_active;

        $user->update([
            'is_active' => $newActive,
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => $newActive ? 'activate_user' : 'deactivate_user',
                'target_user_id' => $user->id,
            ],
            'created_at' => now(),
        ]);

        $statusLabel = $newActive ? 'เปิดใช้งาน' : 'ปิดใช้งาน';

        return back()->with('success', "{$statusLabel}บัญชี {$user->name} เรียบร้อยแล้ว");
    }

    public function updateQuota(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'quota_gb' => ['required', 'numeric', 'min:0'],
        ]);

        $quotaBytes = (int) round(((float) $validated['quota_gb']) * self::BYTES_PER_GB);

        $user->update([
            'quota_bytes' => $quotaBytes,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'update_quota',
                'target_user_id' => $user->id,
                'quota_bytes' => $quotaBytes,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "ตั้งค่าโควต้าพื้นที่ของ {$user->name} เรียบร้อยแล้ว");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'password' => Hash::make($validated['password']),
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'admin_access',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'sub_action' => 'reset_password',
                'target_user_id' => $user->id,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "รีเซ็ตรหัสผ่านและปลดล็อกบัญชีของ {$user->name} เรียบร้อยแล้ว");
    }
}
