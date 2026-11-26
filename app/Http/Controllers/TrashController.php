<?php

namespace App\Http\Controllers;

use App\Enums\NodeType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TrashController extends Controller
{
    public const int TRASH_RETENTION_DAYS = 30;

    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trashedNodes = Node::onlyTrashed()
            ->with('owner:id,name')
            ->when($user->role !== UserRole::Admin, function ($query) use ($user): void {
                $query->where(function ($subQuery) use ($user): void {
                    $subQuery->where('owner_id', $user->id)
                        ->orWhere('deleted_by', $user->id);
                });
            })
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (Node $node): array => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'name' => $node->name,
                'type' => $node->type->value,
                'root_type' => $node->root_type->value,
                'size_bytes' => $node->size_bytes,
                'owner_name' => $node->owner?->name,
                'deleted_at' => $node->deleted_at?->toIso8601String(),
                'purge_at' => $node->purge_at?->toIso8601String(),
            ]);

        return Inertia::render('Files/Trash', [
            'items' => $trashedNodes,
        ]);
    }

    public function destroy(Request $request, Node $node): RedirectResponse
    {
        Gate::authorize('delete', $node);

        /** @var User $user */
        $user = $request->user();
        $deletedAt = now();
        $purgeAt = $deletedAt->copy()->addDays(self::TRASH_RETENTION_DAYS);

        $this->softDeleteRecursively($node, $user->id, $deletedAt, $purgeAt);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'delete',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => ['purge_at' => $purgeAt->toIso8601String()],
            'created_at' => now(),
        ]);

        return back()->with('success', "ย้าย {$node->name} ไปยังถังขยะแล้ว (ลบถาวรใน 30 วัน)");
    }

    public function restore(Request $request, string $uuid): RedirectResponse
    {
        $node = Node::onlyTrashed()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('restore', $node);

        if ($node->parent_id !== null) {
            $parent = Node::withTrashed()->find($node->parent_id);
            if ($parent === null || $parent->trashed()) {
                $node->parent_id = null;
            }
        }

        $conflictExists = Node::query()
            ->whereNull('deleted_at')
            ->where('parent_id', $node->parent_id)
            ->where('root_type', $node->root_type)
            ->where('name', $node->name)
            ->where('id', '!=', $node->id)
            ->exists();

        if ($conflictExists) {
            $node->name = $node->name.' (restored-'.now()->format('His').')';
        }

        $this->restoreRecursively($node);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'restore',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);

        return back()->with('success', "กู้คืน {$node->name} เรียบร้อยแล้ว");
    }

    public function forceDestroy(Request $request, string $uuid): RedirectResponse
    {
        $node = Node::withTrashed()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('forceDelete', $node);

        $nodeName = $node->name;
        $nodeId = $node->id;

        $this->forceDeleteRecursively($node);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'delete',
            'node_id' => $nodeId,
            'node_name' => $nodeName,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => ['permanent' => true],
            'created_at' => now(),
        ]);

        return back()->with('success', "ลบ {$nodeName} ถาวรเรียบร้อยแล้ว");
    }

    protected function softDeleteRecursively(Node $node, int $deletedBy, Carbon $deletedAt, Carbon $purgeAt): void
    {
        $children = Node::query()->where('parent_id', $node->id)->get();

        foreach ($children as $child) {
            $this->softDeleteRecursively($child, $deletedBy, $deletedAt, $purgeAt);
        }

        $node->forceFill([
            'deleted_at' => $deletedAt,
            'deleted_by' => $deletedBy,
            'purge_at' => $purgeAt,
        ])->save();
    }

    protected function restoreRecursively(Node $node): void
    {
        $node->forceFill([
            'deleted_at' => null,
            'deleted_by' => null,
            'purge_at' => null,
        ])->save();

        $children = Node::onlyTrashed()->where('parent_id', $node->id)->get();

        foreach ($children as $child) {
            $this->restoreRecursively($child);
        }
    }

    protected function forceDeleteRecursively(Node $node): void
    {
        $children = Node::withTrashed()->where('parent_id', $node->id)->get();

        foreach ($children as $child) {
            $this->forceDeleteRecursively($child);
        }

        if ($node->type === NodeType::File) {
            if ($node->storage_path !== null && Storage::disk('local')->exists($node->storage_path)) {
                Storage::disk('local')->delete($node->storage_path);
            }

            if ($node->size_bytes > 0) {
                $owner = User::find($node->owner_id);
                if ($owner !== null) {
                    $owner->update([
                        'used_bytes' => max(0, $owner->used_bytes - $node->size_bytes),
                    ]);
                }
            }
        }

        $node->forceDelete();
    }
}
