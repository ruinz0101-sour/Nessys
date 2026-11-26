<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('department');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatarUrl(),
                    'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
                    'department_id' => $user->department_id,
                    'department_name' => $user->department?->name,
                    'quota_bytes' => $user->quota_bytes,
                    'used_bytes' => $user->used_bytes,
                    'is_active' => $user->is_active,
                ] : null,
            ],
            'notifications' => fn (): array => $user ? [
                'unread_count' => UserNotification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
                'items' => UserNotification::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (UserNotification $n): array => [
                        'id' => $n->id,
                        'type' => $n->type,
                        'data' => $n->data,
                        'read_at' => $n->read_at?->toIso8601String(),
                        'created_at' => $n->created_at?->toIso8601String(),
                    ]),
            ] : ['unread_count' => 0, 'items' => []],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
