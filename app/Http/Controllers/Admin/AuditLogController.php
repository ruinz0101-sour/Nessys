<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $userId = $request->string('user_id')->trim()->toString();
        $action = $request->string('action')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($userId !== '', function ($query) use ($userId): void {
                $query->where('user_id', (int) $userId);
            })
            ->when($action !== '', function ($query) use ($action): void {
                $query->where('action', $action);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('node_name', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name ?? 'ระบบ / ไม่ระบุ',
                'user_email' => $log->user?->email,
                'action' => $log->action,
                'node_id' => $log->node_id,
                'node_name' => $log->node_name,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $actions = [
            'login',
            'logout',
            'login_failed',
            'view',
            'download',
            'upload',
            'rename',
            'move',
            'delete',
            'restore',
            'share',
            'unshare',
            'request',
            'approve',
            'deny',
            'admin_access',
        ];

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'filters' => [
                'user_id' => $userId,
                'action' => $action,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
            ],
        ]);
    }
}
