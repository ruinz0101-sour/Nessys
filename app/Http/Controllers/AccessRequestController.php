<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Mail\AccessRequestDecidedMail;
use App\Mail\AccessRequestedMail;
use App\Models\AccessRequest;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccessRequestController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $incomingQuery = AccessRequest::query()
            ->with(['node', 'requester:id,name,email', 'owner:id,name,email,is_active', 'decidedBy:id,name'])
            ->when($user->role !== UserRole::Admin, function ($query) use ($user): void {
                $query->where('owner_id', $user->id);
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest();

        $incomingRequests = $incomingQuery->get()->map(fn (AccessRequest $req): array => [
            'id' => $req->id,
            'node_id' => $req->node_id,
            'node_uuid' => $req->node?->uuid,
            'node_name' => $req->node?->name ?? '(ไฟล์ถูกลบ)',
            'node_type' => $req->node?->type?->value,
            'requester_id' => $req->requester_id,
            'requester_name' => $req->requester?->name,
            'requester_email' => $req->requester?->email,
            'owner_id' => $req->owner_id,
            'owner_name' => $req->owner?->name,
            'owner_is_active' => (bool) ($req->owner?->is_active ?? false),
            'escalated_to_admin' => ! ($req->owner?->is_active ?? true),
            'reason' => $req->reason,
            'status' => $req->status instanceof RequestStatus ? $req->status->value : $req->status,
            'granted_permission' => $req->granted_permission instanceof SharePermission ? $req->granted_permission->value : $req->granted_permission,
            'granted_until' => $req->granted_until?->toIso8601String(),
            'decided_by_name' => $req->decidedBy?->name,
            'decided_at' => $req->decided_at?->toIso8601String(),
            'decision_note' => $req->decision_note,
            'created_at' => $req->created_at?->toIso8601String(),
        ]);

        $myRequests = AccessRequest::query()
            ->with(['node', 'owner:id,name,email,is_active', 'decidedBy:id,name'])
            ->where('requester_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (AccessRequest $req): array => [
                'id' => $req->id,
                'node_id' => $req->node_id,
                'node_uuid' => $req->node?->uuid,
                'node_name' => $req->node?->name ?? '(ไฟล์ถูกลบ)',
                'node_type' => $req->node?->type?->value,
                'root_type' => $req->node?->root_type?->value,
                'owner_name' => $req->owner?->name,
                'owner_is_active' => (bool) ($req->owner?->is_active ?? false),
                'reason' => $req->reason,
                'status' => $req->status instanceof RequestStatus ? $req->status->value : $req->status,
                'granted_permission' => $req->granted_permission instanceof SharePermission ? $req->granted_permission->value : $req->granted_permission,
                'granted_until' => $req->granted_until?->toIso8601String(),
                'decided_by_name' => $req->decidedBy?->name,
                'decided_at' => $req->decided_at?->toIso8601String(),
                'decision_note' => $req->decision_note,
                'created_at' => $req->created_at?->toIso8601String(),
            ]);

        $pendingNodeIds = AccessRequest::query()
            ->where('requester_id', $user->id)
            ->where('status', RequestStatus::Pending->value)
            ->pluck('node_id')
            ->all();

        $restrictedNodes = Node::query()
            ->with('owner:id,name,is_active')
            ->where('owner_id', '!=', $user->id)
            ->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->filter(fn (Node $node): bool => ! Gate::forUser($user)->allows('view', $node))
            ->values()
            ->map(fn (Node $node): array => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'name' => $node->name,
                'type' => $node->type->value,
                'owner_name' => $node->owner?->name,
                'owner' => [
                    'name' => $node->owner?->name,
                ],
                'owner_is_active' => (bool) ($node->owner?->is_active ?? false),
                'has_pending_request' => in_array($node->id, $pendingNodeIds, true),
            ]);

        return Inertia::render('AccessRequests/Index', [
            'incomingRequests' => $incomingRequests,
            'myRequests' => $myRequests,
            'restrictedNodes' => $restrictedNodes,
        ]);
    }

    public function prompt(Request $request, Node $node): InertiaResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (Gate::forUser($user)->allows('view', $node)) {
            if ($node->type->value === 'folder') {
                return redirect()->route('files.index', [
                    'root' => $node->root_type->value,
                    'folder' => $node->uuid,
                ]);
            }

            return redirect()->route('files.download', $node->uuid);
        }

        $node->loadMissing('owner:id,name,is_active');

        $pendingRequest = AccessRequest::query()
            ->where('node_id', $node->id)
            ->where('requester_id', $user->id)
            ->where('status', RequestStatus::Pending->value)
            ->first();

        return Inertia::render('AccessRequests/Prompt', [
            'node' => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'name' => $node->name,
                'type' => $node->type->value,
                'owner_name' => $node->owner?->name,
                'owner_is_active' => (bool) ($node->owner?->is_active ?? false),
            ],
            'existingPending' => $pendingRequest !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'node_id' => ['required', 'integer', Rule::exists('nodes', 'id')],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $node = Node::with('owner')->findOrFail((int) $validated['node_id']);

        if ($node->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'node_id' => ['คุณเป็นเจ้าของไฟล์หรือโฟลเดอร์นี้อยู่แล้ว'],
            ]);
        }

        $alreadyPending = AccessRequest::query()
            ->where('node_id', $node->id)
            ->where('requester_id', $user->id)
            ->where('status', RequestStatus::Pending->value)
            ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages([
                'node_id' => ['คุณมีคำขอสิทธิ์สำหรับรายการนี้ที่กำลังรอการพิจารณาอยู่แล้ว'],
            ]);
        }

        $accessRequest = AccessRequest::create([
            'node_id' => $node->id,
            'requester_id' => $user->id,
            'owner_id' => $node->owner_id,
            'reason' => $validated['reason'] ?? null,
            'status' => RequestStatus::Pending,
        ]);

        $accessRequest->load(['node', 'requester', 'owner']);

        $owner = $node->owner;

        if ($owner !== null && $owner->is_active) {
            UserNotification::create([
                'user_id' => $owner->id,
                'type' => 'access_requested',
                'data' => [
                    'access_request_id' => $accessRequest->id,
                    'node_id' => $node->id,
                    'node_uuid' => $node->uuid,
                    'node_name' => $node->name,
                    'requester_id' => $user->id,
                    'requester_name' => $user->name,
                    'reason' => $accessRequest->reason,
                    'message' => "{$user->name} ขอสิทธิ์เข้าถึง \"{$node->name}\"",
                ],
                'created_at' => now(),
            ]);

            Mail::to($owner->email)->send(new AccessRequestedMail($accessRequest, false));
        } else {
            // ส่งต่อ Admin อัตโนมัติ ถ้าเจ้าของถูกปิดบัญชี
            $admins = User::query()
                ->where('role', UserRole::Admin)
                ->where('is_active', true)
                ->get();

            foreach ($admins as $admin) {
                UserNotification::create([
                    'user_id' => $admin->id,
                    'type' => 'access_request_escalated',
                    'data' => [
                        'access_request_id' => $accessRequest->id,
                        'node_id' => $node->id,
                        'node_uuid' => $node->uuid,
                        'node_name' => $node->name,
                        'requester_id' => $user->id,
                        'requester_name' => $user->name,
                        'owner_id' => $node->owner_id,
                        'escalated' => true,
                        'reason' => $accessRequest->reason,
                        'message' => "[ส่งต่อ Admin] {$user->name} ขอสิทธิ์เข้าถึง \"{$node->name}\" (เจ้าของถูกปิดบัญชี)",
                    ],
                    'created_at' => now(),
                ]);

                Mail::to($admin->email)->send(new AccessRequestedMail($accessRequest, true));
            }
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'request',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'access_request_id' => $accessRequest->id,
                'owner_id' => $node->owner_id,
                'escalated_to_admin' => ! ($owner?->is_active ?? false),
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('access-requests.index')
            ->with('success', "ส่งคำขอสิทธิ์เข้าถึง {$node->name} เรียบร้อยแล้ว");
    }

    public function decide(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $canDecide = $user->role === UserRole::Admin || $accessRequest->owner_id === $user->id;
        if (! $canDecide) {
            abort(403, 'คุณไม่มีสิทธิ์พิจารณาคำขอนี้');
        }

        if ($accessRequest->status !== RequestStatus::Pending) {
            return back()->with('error', 'คำขอนี้ได้รับการพิจารณาหรือยกเลิกไปแล้ว');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([RequestStatus::Approved->value, RequestStatus::Denied->value])],
            'granted_permission' => ['nullable', Rule::enum(SharePermission::class)],
            'granted_until' => ['nullable', 'date', 'after:now'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = RequestStatus::from($validated['status']);
        $grantedPermission = $newStatus === RequestStatus::Approved
            ? SharePermission::from($validated['granted_permission'] ?? SharePermission::View->value)
            : null;
        $grantedUntil = ($newStatus === RequestStatus::Approved && ! empty($validated['granted_until']))
            ? Carbon::parse($validated['granted_until'])
            : null;

        $accessRequest->update([
            'status' => $newStatus,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'decision_note' => $validated['decision_note'] ?? null,
            'granted_permission' => $grantedPermission,
            'granted_until' => $grantedUntil,
        ]);

        $accessRequest->load(['node', 'requester', 'owner', 'decidedBy']);

        $requester = $accessRequest->requester;
        $nodeName = $accessRequest->node?->name ?? 'ไฟล์/โฟลเดอร์';

        if ($requester !== null) {
            $notifType = $newStatus === RequestStatus::Approved ? 'access_approved' : 'access_denied';
            $statusText = $newStatus === RequestStatus::Approved ? 'อนุมัติแล้ว' : 'ถูกปฏิเสธ';

            UserNotification::create([
                'user_id' => $requester->id,
                'type' => $notifType,
                'data' => [
                    'access_request_id' => $accessRequest->id,
                    'node_id' => $accessRequest->node_id,
                    'node_uuid' => $accessRequest->node?->uuid,
                    'node_name' => $nodeName,
                    'status' => $newStatus->value,
                    'permission' => $grantedPermission?->value,
                    'granted_until' => $grantedUntil?->toIso8601String(),
                    'decided_by_name' => $user->name,
                    'message' => "คำขอเข้าถึง \"{$nodeName}\" ของคุณ{$statusText}",
                ],
                'created_at' => now(),
            ]);

            Mail::to($requester->email)->send(new AccessRequestDecidedMail($accessRequest));
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => $newStatus === RequestStatus::Approved ? 'approve' : 'deny',
            'node_id' => $accessRequest->node_id,
            'node_name' => $nodeName,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'access_request_id' => $accessRequest->id,
                'requester_id' => $accessRequest->requester_id,
                'granted_permission' => $grantedPermission?->value,
                'granted_until' => $grantedUntil?->toIso8601String(),
            ],
            'created_at' => now(),
        ]);

        $actionLabel = $newStatus === RequestStatus::Approved ? 'อนุมัติ' : 'ปฏิเสธ';

        return back()->with('success', "{$actionLabel}คำขอสิทธิ์เรียบร้อยแล้ว");
    }

    public function cancel(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        if ($accessRequest->requester_id !== $request->user()?->id) {
            abort(403);
        }

        if ($accessRequest->status === RequestStatus::Pending) {
            $accessRequest->update([
                'status' => RequestStatus::Cancelled,
            ]);
        }

        return back()->with('success', 'ยกเลิกคำขอสิทธิ์เรียบร้อยแล้ว');
    }
}
