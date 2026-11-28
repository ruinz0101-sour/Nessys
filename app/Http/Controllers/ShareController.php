<?php

namespace App\Http\Controllers;

use App\Enums\RootType;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Node;
use App\Models\Share;
use App\Models\ShareLink;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ShareController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sharedWithMe = Share::query()
            ->with(['node.owner:id,name', 'sharedBy:id,name,email'])
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) use ($user): void {
                $query->where('shared_with_user_id', $user->id);

                if ($user->department_id !== null) {
                    $query->orWhere('shared_with_department_id', $user->department_id);
                }
            })
            ->whereHas('node', function ($query): void {
                $query->whereNull('deleted_at');
            })
            ->latest()
            ->get()
            ->map(fn (Share $share): array => [
                'id' => $share->id,
                'node_id' => $share->node_id,
                'node_uuid' => $share->node?->uuid,
                'node_name' => $share->node?->name,
                'node_type' => $share->node?->type?->value,
                'mime_type' => $share->node?->mime_type,
                'extension' => $share->node?->extension,
                'root_type' => $share->node?->root_type?->value,
                'size_bytes' => $share->node?->size_bytes ?? 0,
                'shared_by_name' => $share->sharedBy?->name,
                'shared_by_email' => $share->sharedBy?->email,
                'permission' => $share->permission instanceof SharePermission ? $share->permission->value : $share->permission,
                'expires_at' => $share->expires_at?->toIso8601String(),
                'created_at' => $share->created_at?->toIso8601String(),
            ]);

        $sharedByMe = Share::query()
            ->with(['node' => fn ($query) => $query->withTrashed(), 'sharedWithUser:id,name,email', 'sharedWithDepartment:id,name'])
            ->where('shared_by', $user->id)
            ->latest()
            ->get()
            ->map(function (Share $share): array {
                $isExpired = $share->expires_at !== null && $share->expires_at->isPast();
                $isRevoked = $share->revoked_at !== null;

                return [
                    'id' => $share->id,
                    'node_id' => $share->node_id,
                    'node_uuid' => $share->node?->uuid,
                    'node_name' => $share->node?->name,
                    'node_type' => $share->node?->type?->value,
                    'mime_type' => $share->node?->mime_type,
                    'extension' => $share->node?->extension,
                    'shared_with_user_id' => $share->shared_with_user_id,
                    'shared_with_user_name' => $share->sharedWithUser?->name,
                    'shared_with_user_email' => $share->sharedWithUser?->email,
                    'shared_with_department_id' => $share->shared_with_department_id,
                    'shared_with_department_name' => $share->sharedWithDepartment?->name,
                    'permission' => $share->permission instanceof SharePermission ? $share->permission->value : $share->permission,
                    'expires_at' => $share->expires_at?->toIso8601String(),
                    'revoked_at' => $share->revoked_at?->toIso8601String(),
                    'is_expired' => $isExpired,
                    'is_revoked' => $isRevoked,
                    'is_active' => ! $isExpired && ! $isRevoked,
                    'created_at' => $share->created_at?->toIso8601String(),
                ];
            });

        $shareableNodes = Node::query()
            ->when($user->role !== UserRole::Admin, function ($query) use ($user): void {
                $query->where(function ($sub) use ($user): void {
                    $sub->where('owner_id', $user->id);

                    if ($user->role === UserRole::DeptManager && $user->department_id !== null) {
                        $sub->orWhere(function ($deptQuery) use ($user): void {
                            $deptQuery->where('root_type', RootType::Department)
                                ->where('department_id', $user->department_id);
                        });
                    }
                });
            })
            ->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'type', 'root_type']);

        $recipients = User::query()
            ->with('department:id,name')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $recipient): array => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'department_name' => $recipient->department?->name,
            ]);

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $shareLinks = ShareLink::query()
            ->with(['node' => fn ($query) => $query->withTrashed()])
            ->where('created_by', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ShareLink $link): array {
                $isExpired = $link->isExpired();
                $isRevoked = $link->isRevoked();
                $isLimitReached = $link->isDownloadLimitReached();

                return [
                    'id' => $link->id,
                    'token' => $link->token,
                    'url' => route('share-links.show', ['token' => $link->token]),
                    'node_id' => $link->node_id,
                    'node_uuid' => $link->node?->uuid,
                    'node_name' => $link->node?->name,
                    'node_type' => $link->node?->type?->value,
                    'has_password' => $link->hasPassword(),
                    'max_downloads' => $link->max_downloads,
                    'download_count' => $link->download_count,
                    'expires_at' => $link->expires_at?->toIso8601String(),
                    'revoked_at' => $link->revoked_at?->toIso8601String(),
                    'is_expired' => $isExpired,
                    'is_revoked' => $isRevoked,
                    'is_limit_reached' => $isLimitReached,
                    'is_active' => $link->isActive() && $link->node !== null && $link->node->deleted_at === null,
                    'created_at' => $link->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Shares/Index', [
            'sharedWithMe' => $sharedWithMe,
            'sharedByMe' => $sharedByMe,
            'shareLinks' => $shareLinks,
            'shareableNodes' => $shareableNodes,
            'recipients' => $recipients,
            'departments' => $departments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'node_id' => ['required', 'integer', Rule::exists('nodes', 'id')],
            'shared_with_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'shared_with_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'permission' => ['required', Rule::enum(SharePermission::class)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $node = Node::findOrFail((int) $validated['node_id']);

        Gate::authorize('share', $node);

        $recipientId = ! empty($validated['shared_with_user_id']) ? (int) $validated['shared_with_user_id'] : null;
        $departmentId = ! empty($validated['shared_with_department_id']) ? (int) $validated['shared_with_department_id'] : null;

        if ($recipientId === null && $departmentId === null) {
            throw ValidationException::withMessages([
                'shared_with_user_id' => ['กรุณาเลือกผู้รับหรือแผนกที่ต้องการแชร์'],
            ]);
        }

        $permission = SharePermission::from($validated['permission']);
        $expiresAt = ! empty($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        if ($departmentId !== null) {
            $department = Department::findOrFail($departmentId);

            $share = Share::create([
                'node_id' => $node->id,
                'shared_by' => $user->id,
                'shared_with_user_id' => null,
                'shared_with_department_id' => $department->id,
                'permission' => $permission,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
            ]);

            $deptMembers = User::query()
                ->where('department_id', $department->id)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($deptMembers as $member) {
                UserNotification::create([
                    'user_id' => $member->id,
                    'type' => 'file_shared',
                    'data' => [
                        'share_id' => $share->id,
                        'node_id' => $node->id,
                        'node_uuid' => $node->uuid,
                        'node_name' => $node->name,
                        'node_type' => $node->type->value,
                        'shared_by_id' => $user->id,
                        'shared_by_name' => $user->name,
                        'shared_with_department_id' => $department->id,
                        'shared_with_department_name' => $department->name,
                        'permission' => $permission->value,
                        'expires_at' => $expiresAt?->toIso8601String(),
                        'message' => "{$user->name} ได้แชร์ \"{$node->name}\" ให้แผนก {$department->name}",
                    ],
                    'created_at' => now(),
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'share',
                'node_id' => $node->id,
                'node_name' => $node->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'meta' => [
                    'share_id' => $share->id,
                    'shared_with_department_id' => $department->id,
                    'permission' => $permission->value,
                    'expires_at' => $expiresAt?->toIso8601String(),
                ],
                'created_at' => now(),
            ]);

            return back()->with('success', "แชร์ {$node->name} ให้แผนก {$department->name} เรียบร้อยแล้ว");
        }

        if ($recipientId === $user->id) {
            throw ValidationException::withMessages([
                'shared_with_user_id' => ['ไม่สามารถแชร์ไฟล์หรือโฟลเดอร์ให้ตนเองได้'],
            ]);
        }

        $recipient = User::query()
            ->where('id', $recipientId)
            ->where('is_active', true)
            ->first();

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'shared_with_user_id' => ['ไม่พบผู้รับหรือบัญชีผู้รับถูกปิดการใช้งาน'],
            ]);
        }

        $share = Share::create([
            'node_id' => $node->id,
            'shared_by' => $user->id,
            'shared_with_user_id' => $recipient->id,
            'shared_with_department_id' => null,
            'permission' => $permission,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
        ]);

        UserNotification::create([
            'user_id' => $recipient->id,
            'type' => 'file_shared',
            'data' => [
                'share_id' => $share->id,
                'node_id' => $node->id,
                'node_uuid' => $node->uuid,
                'node_name' => $node->name,
                'node_type' => $node->type->value,
                'shared_by_id' => $user->id,
                'shared_by_name' => $user->name,
                'permission' => $permission->value,
                'expires_at' => $expiresAt?->toIso8601String(),
                'message' => "{$user->name} ได้แชร์ \"{$node->name}\" ให้คุณ",
            ],
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'share',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'share_id' => $share->id,
                'shared_with_user_id' => $recipient->id,
                'permission' => $permission->value,
                'expires_at' => $expiresAt?->toIso8601String(),
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "แชร์ {$node->name} ให้ {$recipient->name} เรียบร้อยแล้ว");
    }

    public function revoke(Request $request, Share $share): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $share->loadMissing('node');

        $canRevoke = $user->role === UserRole::Admin
            || $share->shared_by === $user->id
            || $share->node?->owner_id === $user->id;

        if (! $canRevoke) {
            abort(403, 'คุณไม่มีสิทธิ์ยกเลิกการแชร์นี้');
        }

        if ($share->revoked_at === null) {
            $share->update([
                'revoked_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'unshare',
                'node_id' => $share->node_id,
                'node_name' => $share->node?->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'meta' => [
                    'share_id' => $share->id,
                    'shared_with_user_id' => $share->shared_with_user_id,
                    'shared_with_department_id' => $share->shared_with_department_id,
                ],
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'ยกเลิกการแชร์เรียบร้อยแล้ว');
    }

    public function markNotificationRead(Request $request, UserNotification $notification): RedirectResponse
    {
        if ($notification->user_id !== $request->user()?->id) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()?->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'ทำเครื่องหมายว่าอ่านแจ้งเตือนทั้งหมดแล้ว');
    }
}
