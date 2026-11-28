<?php

namespace App\Http\Controllers;

use App\Enums\NodeType;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ShareLinkController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'node_id' => ['required', 'integer', Rule::exists('nodes', 'id')],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:64'],
            'max_downloads' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $node = Node::findOrFail((int) $validated['node_id']);

        Gate::authorize('share', $node);

        $expiresAt = ! empty($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : now()->addDays(7);

        $password = isset($validated['password']) ? trim((string) $validated['password']) : '';
        $maxDownloads = isset($validated['max_downloads']) && $validated['max_downloads'] !== ''
            ? (int) $validated['max_downloads']
            : null;

        $shareLink = ShareLink::create([
            'node_id' => $node->id,
            'created_by' => $user->id,
            'token' => Str::random(40),
            'password_hash' => $password !== '' ? Hash::make($password) : null,
            'permission' => SharePermission::View,
            'max_downloads' => $maxDownloads,
            'download_count' => 0,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
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
                'share_link_id' => $shareLink->id,
                'share_type' => 'url',
                'has_password' => $shareLink->hasPassword(),
                'max_downloads' => $shareLink->max_downloads,
                'expires_at' => $shareLink->expires_at?->toIso8601String(),
            ],
            'created_at' => now(),
        ]);

        $url = route('share-links.show', ['token' => $shareLink->token]);

        if ($request->expectsJson()) {
            return response()->json([
                'share_link' => [
                    'id' => $shareLink->id,
                    'token' => $shareLink->token,
                    'url' => $url,
                    'node_name' => $node->name,
                    'has_password' => $shareLink->hasPassword(),
                    'max_downloads' => $shareLink->max_downloads,
                    'expires_at' => $shareLink->expires_at?->toIso8601String(),
                ],
                'message' => "สร้างลิงก์แชร์สำหรับ {$node->name} เรียบร้อยแล้ว",
            ], 201);
        }

        return back()->with('success', "สร้างลิงก์แชร์สำหรับ {$node->name} เรียบร้อยแล้ว");
    }

    public function revoke(Request $request, ShareLink $shareLink): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $shareLink->loadMissing('node');

        $canRevoke = $user->role === UserRole::Admin
            || $shareLink->created_by === $user->id
            || $shareLink->node?->owner_id === $user->id;

        if (! $canRevoke) {
            abort(403, 'คุณไม่มีสิทธิ์ยกเลิกลิงก์แชร์นี้');
        }

        if ($shareLink->revoked_at === null) {
            $shareLink->update([
                'revoked_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'unshare',
                'node_id' => $shareLink->node_id,
                'node_name' => $shareLink->node?->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'meta' => [
                    'share_link_id' => $shareLink->id,
                    'share_type' => 'url',
                ],
                'created_at' => now(),
            ]);
        }

        return back()->with('success', 'ยกเลิกลิงก์แชร์เรียบร้อยแล้ว');
    }

    public function show(Request $request, string $token): InertiaResponse
    {
        $shareLink = ShareLink::query()
            ->with(['node.owner:id,name', 'creator:id,name'])
            ->where('token', $token)
            ->first();

        $errorStatus = $this->resolveLinkErrorStatus($shareLink);
        if ($errorStatus !== null || $shareLink === null || $shareLink->node === null) {
            return Inertia::render('Shares/PublicLink', [
                'status' => $errorStatus ?? 'not_found',
                'token' => $token,
                'requiresPassword' => false,
                'shareLink' => null,
                'node' => null,
                'currentFolder' => null,
                'breadcrumbs' => [],
                'children' => [],
            ]);
        }

        $rootNode = $shareLink->node;

        if ($shareLink->hasPassword() && ! $this->isLinkUnlocked($request, $shareLink)) {
            return Inertia::render('Shares/PublicLink', [
                'status' => 'active',
                'token' => $token,
                'requiresPassword' => true,
                'shareLink' => $this->serializeShareLink($shareLink),
                'node' => [
                    'name' => $rootNode->name,
                    'type' => $rootNode->type->value,
                ],
                'currentFolder' => null,
                'breadcrumbs' => [],
                'children' => [],
            ]);
        }

        $currentFolder = null;
        $breadcrumbs = [];
        $children = [];

        if ($rootNode->type === NodeType::Folder) {
            $currentFolder = $rootNode;
            $folderUuid = $request->string('folder')->trim()->toString();

            if ($folderUuid !== '' && $folderUuid !== $rootNode->uuid) {
                $candidate = Node::query()
                    ->where('uuid', $folderUuid)
                    ->where('type', NodeType::Folder)
                    ->first();

                if ($candidate === null || ! $this->isNodeWithinRoot($candidate, $rootNode)) {
                    abort(404, 'ไม่พบโฟลเดอร์ที่ต้องการ');
                }

                $currentFolder = $candidate;
            }

            $breadcrumbs = $this->buildScopedBreadcrumbs($currentFolder, $rootNode);

            $children = Node::query()
                ->where('parent_id', $currentFolder->id)
                ->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(fn (Node $child): array => [
                    'uuid' => $child->uuid,
                    'name' => $child->name,
                    'type' => $child->type->value,
                    'size_bytes' => $child->size_bytes,
                    'mime_type' => $child->mime_type,
                    'extension' => $child->extension,
                    'updated_at' => $child->updated_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('Shares/PublicLink', [
            'status' => 'active',
            'token' => $token,
            'requiresPassword' => false,
            'shareLink' => $this->serializeShareLink($shareLink),
            'node' => [
                'uuid' => $rootNode->uuid,
                'name' => $rootNode->name,
                'type' => $rootNode->type->value,
                'size_bytes' => $rootNode->size_bytes,
                'mime_type' => $rootNode->mime_type,
                'extension' => $rootNode->extension,
                'updated_at' => $rootNode->updated_at?->toIso8601String(),
            ],
            'currentFolder' => $currentFolder ? [
                'uuid' => $currentFolder->uuid,
                'name' => $currentFolder->name,
            ] : null,
            'breadcrumbs' => $breadcrumbs,
            'children' => $children,
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $shareLink = ShareLink::query()
            ->with('node')
            ->where('token', $token)
            ->first();

        if ($this->resolveLinkErrorStatus($shareLink) !== null || $shareLink === null) {
            return redirect()->route('share-links.show', ['token' => $token]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! $shareLink->hasPassword() || ! Hash::check($validated['password'], (string) $shareLink->password_hash)) {
            throw ValidationException::withMessages([
                'password' => ['รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'],
            ]);
        }

        $request->session()->put($this->unlockSessionKey($shareLink), true);

        return redirect()->route('share-links.show', ['token' => $token]);
    }

    public function download(Request $request, string $token): BinaryFileResponse
    {
        [$shareLink, $targetFile] = $this->resolveAccessibleFile($request, $token);

        $rangeHeader = $request->header('Range');
        if ($rangeHeader === null || str_starts_with($rangeHeader, 'bytes=0-')) {
            $shareLink->increment('download_count');

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'download',
                'node_id' => $targetFile->id,
                'node_name' => $targetFile->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'meta' => [
                    'share_link_id' => $shareLink->id,
                    'via_share_link' => true,
                ],
                'created_at' => now(),
            ]);
        }

        $fullPath = Storage::disk('local')->path((string) $targetFile->storage_path);
        $response = response()->download(
            $fullPath,
            $targetFile->name,
            [
                'Content-Type' => $targetFile->mime_type ?? 'application/octet-stream',
                'Accept-Ranges' => 'bytes',
            ]
        );

        if ($targetFile->sha256 !== null && $targetFile->sha256 !== '') {
            $response->setEtag($targetFile->sha256);
        } else {
            $response->setAutoEtag();
        }

        $response->setAutoLastModified();

        return $response;
    }

    public function preview(Request $request, string $token): SymfonyResponse
    {
        [$shareLink, $targetFile] = $this->resolveAccessibleFile($request, $token);

        $rangeHeader = $request->header('Range');
        if ($rangeHeader === null || str_starts_with($rangeHeader, 'bytes=0-')) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'view',
                'node_id' => $targetFile->id,
                'node_name' => $targetFile->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'meta' => [
                    'share_link_id' => $shareLink->id,
                    'via_share_link' => true,
                ],
                'created_at' => now(),
            ]);
        }

        $fullPath = Storage::disk('local')->path((string) $targetFile->storage_path);
        $safeFilename = str_replace(['"', "\r", "\n"], '', $targetFile->name);

        return response()->file($fullPath, [
            'Content-Type' => $targetFile->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"; filename*=UTF-8\'\''.rawurlencode($targetFile->name),
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * @return array{0: ShareLink, 1: Node}
     */
    protected function resolveAccessibleFile(Request $request, string $token): array
    {
        $shareLink = ShareLink::query()
            ->with('node')
            ->where('token', $token)
            ->first();

        $errorStatus = $this->resolveLinkErrorStatus($shareLink);
        if ($errorStatus !== null || $shareLink === null || $shareLink->node === null) {
            abort(404, 'ลิงก์แชร์นี้ไม่ถูกต้อง หมดอายุ หรือถูกยกเลิกแล้ว');
        }

        if ($shareLink->hasPassword() && ! $this->isLinkUnlocked($request, $shareLink)) {
            abort(403, 'กรุณากรอกรหัสผ่านก่อนเข้าถึงไฟล์นี้');
        }

        $rootNode = $shareLink->node;

        if ($rootNode->type === NodeType::File) {
            $targetFile = $rootNode;
        } else {
            $fileUuid = $request->string('file')->trim()->toString();
            if ($fileUuid === '') {
                abort(400, 'กรุณาระบุไฟล์ที่ต้องการภายในโฟลเดอร์');
            }

            $targetFile = Node::query()
                ->where('uuid', $fileUuid)
                ->where('type', NodeType::File)
                ->first();

            if ($targetFile === null || ! $this->isNodeWithinRoot($targetFile, $rootNode)) {
                abort(404, 'ไม่พบไฟล์ที่ต้องการภายในลิงก์แชร์นี้');
            }
        }

        if ($targetFile->storage_path === null || ! Storage::disk('local')->exists($targetFile->storage_path)) {
            abort(404, 'ไม่พบไฟล์บนดิสก์');
        }

        return [$shareLink, $targetFile];
    }

    protected function resolveLinkErrorStatus(?ShareLink $shareLink): ?string
    {
        if ($shareLink === null || $shareLink->node === null) {
            return 'not_found';
        }

        if ($shareLink->isRevoked()) {
            return 'revoked';
        }

        if ($shareLink->isExpired()) {
            return 'expired';
        }

        if ($shareLink->isDownloadLimitReached()) {
            return 'limit_reached';
        }

        return null;
    }

    protected function unlockSessionKey(ShareLink $shareLink): string
    {
        return "share_link_unlocked_{$shareLink->id}";
    }

    protected function isLinkUnlocked(Request $request, ShareLink $shareLink): bool
    {
        return (bool) $request->session()->get($this->unlockSessionKey($shareLink), false);
    }

    protected function isNodeWithinRoot(Node $candidate, Node $rootNode): bool
    {
        $current = $candidate;
        $visited = [];

        while ($current !== null) {
            if ($current->id === $rootNode->id) {
                return true;
            }

            if ($current->parent_id === null || isset($visited[$current->parent_id])) {
                break;
            }

            $visited[$current->parent_id] = true;
            $current = Node::find($current->parent_id);
        }

        return false;
    }

    /**
     * @return array<int, array{uuid: string, name: string}>
     */
    protected function buildScopedBreadcrumbs(Node $currentFolder, Node $rootNode): array
    {
        $trail = [];
        $current = $currentFolder;
        $visited = [];

        while ($current !== null) {
            $trail[] = [
                'uuid' => $current->uuid,
                'name' => $current->name,
            ];

            if ($current->id === $rootNode->id || $current->parent_id === null || isset($visited[$current->parent_id])) {
                break;
            }

            $visited[$current->parent_id] = true;
            $current = Node::find($current->parent_id);
        }

        return array_reverse($trail);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeShareLink(ShareLink $shareLink): array
    {
        return [
            'id' => $shareLink->id,
            'token' => $shareLink->token,
            'shared_by_name' => $shareLink->creator?->name ?? $shareLink->node?->owner?->name,
            'has_password' => $shareLink->hasPassword(),
            'max_downloads' => $shareLink->max_downloads,
            'download_count' => $shareLink->download_count,
            'expires_at' => $shareLink->expires_at?->toIso8601String(),
            'created_at' => $shareLink->created_at?->toIso8601String(),
        ];
    }
}
