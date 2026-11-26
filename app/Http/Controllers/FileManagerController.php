<?php

namespace App\Http\Controllers;

use App\Enums\FileCategory;
use App\Enums\NodeType;
use App\Enums\RequestStatus;
use App\Enums\RootType;
use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Node;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileManagerController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();
        $folderUuid = $request->string('folder')->trim()->toString();
        $requestedRoot = $request->string('root', RootType::Personal->value)->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $categoryFilter = $request->string('category')->trim()->toString();

        $rootType = RootType::tryFrom($requestedRoot) ?? RootType::Personal;
        $currentFolder = null;

        if ($folderUuid !== '') {
            $currentFolder = Node::query()
                ->where('uuid', $folderUuid)
                ->where('type', NodeType::Folder)
                ->firstOrFail();

            if (! Gate::forUser($user)->allows('view', $currentFolder)) {
                $currentFolder->loadMissing('owner:id,name,is_active');
                $hasPending = AccessRequest::query()
                    ->where('node_id', $currentFolder->id)
                    ->where('requester_id', $user->id)
                    ->where('status', RequestStatus::Pending->value)
                    ->exists();

                return Inertia::render('AccessRequests/Prompt', [
                    'node' => [
                        'id' => $currentFolder->id,
                        'uuid' => $currentFolder->uuid,
                        'name' => $currentFolder->name,
                        'type' => $currentFolder->type->value,
                        'owner_name' => $currentFolder->owner?->name,
                        'owner_is_active' => (bool) ($currentFolder->owner?->is_active ?? false),
                    ],
                    'existingPending' => $hasPending,
                ]);
            }

            $rootType = $currentFolder->root_type;

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'view',
                'node_id' => $currentFolder->id,
                'node_name' => $currentFolder->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'created_at' => now(),
            ]);
        }

        $nodesQuery = Node::query()
            ->with(['owner:id,name', 'department:id,name'])
            ->when($currentFolder !== null, function ($query) use ($currentFolder): void {
                $query->where('parent_id', $currentFolder->id);
            }, function ($query) use ($rootType, $user): void {
                $query->whereNull('parent_id')
                    ->where('root_type', $rootType);

                if ($rootType === RootType::Personal) {
                    $query->where('owner_id', $user->id);
                } elseif ($rootType === RootType::Department && $user->role !== UserRole::Admin) {
                    $query->where('department_id', $user->department_id ?? -1);
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($categoryFilter !== '', function ($query) use ($categoryFilter): void {
                $query->where('category', $categoryFilter);
            })
            ->orderByDesc('is_dropzone')
            ->orderByRaw("CASE WHEN type = 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name');

        $nodes = $nodesQuery->get()
            ->filter(fn (Node $node): bool => Gate::forUser($user)->allows('view', $node))
            ->values()
            ->map(fn (Node $node): array => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'parent_id' => $node->parent_id,
                'type' => $node->type->value,
                'name' => $node->name,
                'root_type' => $node->root_type->value,
                'department_id' => $node->department_id,
                'department_name' => $node->department?->name,
                'size_bytes' => $node->size_bytes,
                'mime_type' => $node->mime_type,
                'extension' => $node->extension,
                'category' => $node->category?->value,
                'sha256' => $node->sha256,
                'is_important' => $node->is_important,
                'is_dropzone' => (bool) $node->is_dropzone,
                'owner_id' => $node->owner_id,
                'owner_name' => $node->owner?->name,
                'updated_at' => $node->updated_at?->toIso8601String(),
                'can_edit' => Gate::forUser($user)->allows('update', $node),
                'can_delete' => Gate::forUser($user)->allows('delete', $node),
                'can_share' => Gate::forUser($user)->allows('share', $node),
            ]);

        $shareRecipients = User::query()
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $shareDepartments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $breadcrumbs = $this->buildBreadcrumbs($currentFolder);

        $personalFolders = Node::query()
            ->where('type', NodeType::Folder)
            ->where('root_type', RootType::Personal)
            ->where('owner_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'parent_id', 'name', 'root_type']);

        $departmentFolders = Node::query()
            ->where('type', NodeType::Folder)
            ->where('root_type', RootType::Department)
            ->when($user->role !== UserRole::Admin, function ($query) use ($user): void {
                $query->where('department_id', $user->department_id ?? -1);
            })
            ->orderBy('name')
            ->get(['id', 'uuid', 'parent_id', 'name', 'root_type', 'department_id']);

        $sharedFolders = Node::query()
            ->where('type', NodeType::Folder)
            ->where('root_type', RootType::Shared)
            ->orderByDesc('is_dropzone')
            ->orderBy('name')
            ->get(['id', 'uuid', 'parent_id', 'name', 'root_type', 'is_dropzone']);

        $dropzoneNode = $sharedFolders->firstWhere('is_dropzone', true);

        $canUploadInCurrent = match (true) {
            $currentFolder !== null => Gate::forUser($user)->allows('upload', $currentFolder),
            $rootType === RootType::Personal => true,
            $rootType === RootType::Department => $user->department_id !== null || $user->role === UserRole::Admin,
            $rootType === RootType::Shared => in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true),
            default => false,
        };

        $canCreateFolderInCurrent = match (true) {
            $currentFolder !== null && $currentFolder->root_type === RootType::Shared => in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true),
            $currentFolder !== null => Gate::forUser($user)->allows('update', $currentFolder),
            $rootType === RootType::Personal => true,
            $rootType === RootType::Department => $user->department_id !== null || $user->role === UserRole::Admin,
            $rootType === RootType::Shared => in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true),
            default => false,
        };

        $canCreateInCurrent = $canUploadInCurrent || $canCreateFolderInCurrent;

        return Inertia::render('Files/Index', [
            'currentRoot' => $rootType->value,
            'currentFolder' => $currentFolder ? [
                'id' => $currentFolder->id,
                'uuid' => $currentFolder->uuid,
                'name' => $currentFolder->name,
                'parent_id' => $currentFolder->parent_id,
                'root_type' => $currentFolder->root_type->value,
                'is_dropzone' => (bool) $currentFolder->is_dropzone,
            ] : null,
            'dropzoneFolder' => $dropzoneNode ? [
                'id' => $dropzoneNode->id,
                'uuid' => $dropzoneNode->uuid,
                'name' => $dropzoneNode->name,
            ] : null,
            'breadcrumbs' => $breadcrumbs,
            'nodes' => $nodes,
            'tree' => [
                'personal' => $personalFolders,
                'department' => $departmentFolders,
                'shared' => $sharedFolders,
            ],
            'canCreateInCurrent' => $canCreateInCurrent,
            'canUploadInCurrent' => $canUploadInCurrent,
            'canCreateFolderInCurrent' => $canCreateFolderInCurrent,
            'shareRecipients' => $shareRecipients,
            'shareDepartments' => $shareDepartments,
            'filters' => [
                'search' => $search,
                'category' => $categoryFilter,
            ],
        ]);
    }

    public function createFolder(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[\/\\\\]/'],
            'root_type' => ['required', Rule::enum(RootType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('nodes', 'id')],
            'is_dropzone' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['name']);
        $parentId = $validated['parent_id'] ?? null;
        $rootType = RootType::from($validated['root_type']);
        $departmentId = null;
        $isDropzone = false;

        if ($parentId !== null) {
            $parent = Node::query()
                ->where('id', $parentId)
                ->where('type', NodeType::Folder)
                ->firstOrFail();

            if ($parent->root_type === RootType::Shared && ! in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true)) {
                abort(403, 'เฉพาะผู้ดูแลระบบและหัวหน้าแผนกเท่านั้นที่สร้างโฟลเดอร์ในส่วนกลางได้');
            }

            if (! $parent->is_dropzone) {
                Gate::authorize('update', $parent);
            }
            $rootType = $parent->root_type;
            $departmentId = $parent->department_id;
        } else {
            if ($rootType === RootType::Shared && ! in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true)) {
                abort(403, 'เฉพาะผู้ดูแลระบบและหัวหน้าแผนกเท่านั้นที่สร้างโฟลเดอร์ในส่วนกลางได้');
            }

            if ($rootType === RootType::Shared && ! empty($validated['is_dropzone'])) {
                $isDropzone = true;
            }

            if ($rootType === RootType::Department) {
                if ($user->department_id === null && $user->role !== UserRole::Admin) {
                    abort(403, 'คุณไม่ได้สังกัดแผนกจึงไม่สามารถสร้างโฟลเดอร์แผนกได้');
                }
                $departmentId = $user->department_id;
            }
        }

        $this->ensureUniqueNameInFolder($name, $parentId, $rootType, $user->id, $departmentId);

        $folder = Node::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $parentId,
            'type' => NodeType::Folder,
            'name' => $name,
            'root_type' => $rootType,
            'department_id' => $departmentId,
            'owner_id' => $user->id,
            'size_bytes' => 0,
            'is_dropzone' => $isDropzone,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'upload',
            'node_id' => $folder->id,
            'node_name' => $folder->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => ['type' => 'folder', 'root_type' => $rootType->value],
            'created_at' => now(),
        ]);

        return back()->with('success', "สร้างโฟลเดอร์ {$folder->name} เรียบร้อยแล้ว");
    }

    public function upload(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:512000'],
            'root_type' => ['required', Rule::enum(RootType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('nodes', 'id')],
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $rootType = RootType::from($validated['root_type']);
        $departmentId = null;

        if ($parentId !== null) {
            $parent = Node::query()
                ->where('id', $parentId)
                ->where('type', NodeType::Folder)
                ->firstOrFail();

            Gate::authorize('upload', $parent);
            $rootType = $parent->root_type;
            $departmentId = $parent->department_id;
        } else {
            if ($rootType === RootType::Shared && ! in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true)) {
                abort(403, 'เฉพาะผู้ดูแลระบบและหัวหน้าแผนกเท่านั้นที่อัปโหลดไฟล์ในส่วนกลางได้');
            }

            if ($rootType === RootType::Department) {
                if ($user->department_id === null && $user->role !== UserRole::Admin) {
                    abort(403, 'คุณไม่ได้สังกัดแผนกจึงไม่สามารถอัปโหลดไฟล์ในโฟลเดอร์แผนกได้');
                }
                $departmentId = $user->department_id;
            }
        }

        /** @var array<int, UploadedFile> $uploadedFiles */
        $uploadedFiles = $request->file('files');

        $incomingBytes = 0;
        $seenNamesInBatch = [];

        foreach ($uploadedFiles as $file) {
            $incomingBytes += (int) $file->getSize();
            $originalName = trim($file->getClientOriginalName());

            if (in_array(Str::lower($originalName), $seenNamesInBatch, true)) {
                throw ValidationException::withMessages([
                    'files' => ["ไฟล์ชื่อซ้ำกันในชุดที่อัปโหลด: {$originalName}"],
                ]);
            }
            $seenNamesInBatch[] = Str::lower($originalName);

            $this->ensureUniqueNameInFolder($originalName, $parentId, $rootType, $user->id, $departmentId, null, 'files');
        }

        $user->refresh();
        if ($user->quota_bytes > 0 && ($user->used_bytes + $incomingBytes) > $user->quota_bytes) {
            throw ValidationException::withMessages([
                'files' => ['พื้นที่จัดเก็บเต็มหรือไม่เพียงพอสำหรับการอัปโหลดไฟล์นี้'],
            ]);
        }

        DB::transaction(function () use ($uploadedFiles, $parentId, $rootType, $departmentId, $user, $request): void {
            $totalAdded = 0;

            foreach ($uploadedFiles as $file) {
                $uuid = (string) Str::uuid();
                $originalName = trim($file->getClientOriginalName());
                $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
                $realMime = $this->detectRealMimeType($file);
                $category = $this->resolveFileCategory($realMime, $extension);
                $sizeBytes = (int) $file->getSize();
                $realPath = $file->getRealPath();
                $sha256 = $realPath !== false ? hash_file('sha256', $realPath) : hash('sha256', $uuid);

                $diskFilename = $extension !== '' ? "{$uuid}.{$extension}" : $uuid;
                $storagePath = $file->storeAs('files', $diskFilename, 'local');

                $node = Node::create([
                    'uuid' => $uuid,
                    'parent_id' => $parentId,
                    'type' => NodeType::File,
                    'name' => $originalName,
                    'root_type' => $rootType,
                    'department_id' => $departmentId,
                    'owner_id' => $user->id,
                    'storage_path' => $storagePath,
                    'size_bytes' => $sizeBytes,
                    'mime_type' => $realMime,
                    'extension' => $extension !== '' ? Str::limit($extension, 20, '') : null,
                    'category' => $category,
                    'sha256' => $sha256,
                    'is_important' => false,
                ]);

                $totalAdded += $sizeBytes;

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'upload',
                    'node_id' => $node->id,
                    'node_name' => $node->name,
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'meta' => [
                        'size_bytes' => $sizeBytes,
                        'mime_type' => $realMime,
                        'sha256' => $sha256,
                        'root_type' => $rootType->value,
                    ],
                    'created_at' => now(),
                ]);
            }

            if ($totalAdded > 0) {
                $user->increment('used_bytes', $totalAdded);
            }
        });

        $count = count($uploadedFiles);

        return back()->with('success', "อัปโหลดไฟล์สำเร็จ {$count} รายการ");
    }

    public function uploadChunk(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'chunk' => ['required', 'file', 'max:32768'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'chunk_offset' => ['nullable', 'integer', 'min:0'],
            'defer_finalize' => ['nullable', 'boolean'],
            'file_name' => ['required', 'string', 'max:255', 'not_regex:/[\/\\\\]/'],
            'file_size' => ['required', 'integer', 'min:0'],
            'root_type' => ['required', Rule::enum(RootType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('nodes', 'id')],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $chunkIndex = (int) $validated['chunk_index'];
        $totalChunks = (int) $validated['total_chunks'];
        $chunkOffset = isset($validated['chunk_offset']) ? (int) $validated['chunk_offset'] : null;
        $deferFinalize = (bool) ($validated['defer_finalize'] ?? false);
        $originalName = trim((string) $validated['file_name']);
        $declaredFileSize = (int) $validated['file_size'];
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        if ($chunkIndex >= $totalChunks) {
            throw ValidationException::withMessages([
                'chunk_index' => ['ลำดับชิ้นส่วนไฟล์ไม่ถูกต้อง'],
            ]);
        }

        if ($originalName === '' || $originalName === '.' || $originalName === '..') {
            throw ValidationException::withMessages([
                'file_name' => ['ชื่อไฟล์ไม่ถูกต้อง'],
            ]);
        }

        [$rootType, $departmentId] = $this->resolveUploadTarget(
            $user,
            $parentId,
            RootType::from($validated['root_type']),
        );

        $chunkDir = Storage::disk('local')->path("chunks/{$user->id}");
        if (! is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }
        $partPath = $chunkDir.DIRECTORY_SEPARATOR."{$uploadId}.part";

        if ($chunkIndex === 0) {
            $this->ensureUniqueNameInFolder($originalName, $parentId, $rootType, $user->id, $departmentId, null, 'files');

            $user->refresh();
            if ($user->quota_bytes > 0 && ($user->used_bytes + $declaredFileSize) > $user->quota_bytes) {
                throw ValidationException::withMessages([
                    'files' => ['พื้นที่จัดเก็บเต็มหรือไม่เพียงพอสำหรับการอัปโหลดไฟล์นี้'],
                ]);
            }
        } elseif ($chunkOffset === null && ! is_file($partPath)) {
            throw ValidationException::withMessages([
                'files' => ['ไม่พบข้อมูลชิ้นส่วนไฟล์ก่อนหน้า กรุณาอัปโหลดใหม่อีกครั้ง'],
            ]);
        }

        /** @var UploadedFile $chunkFile */
        $chunkFile = $request->file('chunk');
        $chunkRealPath = $chunkFile->getRealPath();
        if ($chunkRealPath === false) {
            throw ValidationException::withMessages([
                'files' => ['ไม่สามารถอ่านข้อมูลชิ้นส่วนไฟล์ที่อัปโหลดได้'],
            ]);
        }

        $inputStream = fopen($chunkRealPath, 'rb');
        $outputMode = $chunkOffset !== null
            ? 'c+b'
            : ($chunkIndex === 0 ? 'wb' : 'ab');
        $outputStream = fopen($partPath, $outputMode);

        if ($inputStream === false || $outputStream === false) {
            if (is_resource($inputStream)) {
                fclose($inputStream);
            }
            if (is_resource($outputStream)) {
                fclose($outputStream);
            }

            throw ValidationException::withMessages([
                'files' => ['ไม่สามารถบันทึกชิ้นส่วนไฟล์ชั่วคราวได้'],
            ]);
        }

        try {
            if ($chunkOffset !== null) {
                flock($outputStream, LOCK_EX);
                fseek($outputStream, $chunkOffset);
            }

            stream_copy_to_stream($inputStream, $outputStream);

            if ($chunkOffset !== null) {
                fflush($outputStream);
                flock($outputStream, LOCK_UN);
            }
        } finally {
            fclose($inputStream);
            fclose($outputStream);
        }

        if ($deferFinalize || $chunkIndex < $totalChunks - 1) {
            return response()->json([
                'status' => 'chunk_received',
                'chunk_index' => $chunkIndex,
            ]);
        }

        return $this->finalizeChunkUploadFile(
            $request,
            $user,
            $partPath,
            $originalName,
            $parentId,
            $rootType,
            $departmentId,
        );
    }

    public function completeChunkUpload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'file_name' => ['required', 'string', 'max:255', 'not_regex:/[\/\\\\]/'],
            'root_type' => ['required', Rule::enum(RootType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('nodes', 'id')],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $originalName = trim((string) $validated['file_name']);
        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        if ($originalName === '' || $originalName === '.' || $originalName === '..') {
            throw ValidationException::withMessages([
                'file_name' => ['ชื่อไฟล์ไม่ถูกต้อง'],
            ]);
        }

        [$rootType, $departmentId] = $this->resolveUploadTarget(
            $user,
            $parentId,
            RootType::from($validated['root_type']),
        );

        $partPath = Storage::disk('local')->path("chunks/{$user->id}/{$uploadId}.part");
        if (! is_file($partPath)) {
            throw ValidationException::withMessages([
                'files' => ['ไม่พบข้อมูลไฟล์ชั่วคราว กรุณาอัปโหลดใหม่อีกครั้ง'],
            ]);
        }

        return $this->finalizeChunkUploadFile(
            $request,
            $user,
            $partPath,
            $originalName,
            $parentId,
            $rootType,
            $departmentId,
        );
    }

    protected function finalizeChunkUploadFile(
        Request $request,
        User $user,
        string $partPath,
        string $originalName,
        ?int $parentId,
        RootType $rootType,
        ?int $departmentId,
    ): JsonResponse {
        clearstatcache(true, $partPath);
        $actualSize = (int) filesize($partPath);

        try {
            $this->ensureUniqueNameInFolder($originalName, $parentId, $rootType, $user->id, $departmentId, null, 'files');

            $user->refresh();
            if ($user->quota_bytes > 0 && ($user->used_bytes + $actualSize) > $user->quota_bytes) {
                throw ValidationException::withMessages([
                    'files' => ['พื้นที่จัดเก็บเต็มหรือไม่เพียงพอสำหรับการอัปโหลดไฟล์นี้'],
                ]);
            }

            $uuid = (string) Str::uuid();
            $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
            $realMime = $this->detectMimeTypeFromPath($partPath);
            $category = $this->resolveFileCategory($realMime, $extension);
            $sha256 = hash_file('sha256', $partPath) ?: hash('sha256', $uuid);
            $diskFilename = $extension !== '' ? "{$uuid}.{$extension}" : $uuid;
            $storagePath = "files/{$diskFilename}";
            $finalPath = Storage::disk('local')->path($storagePath);

            $filesDir = dirname($finalPath);
            if (! is_dir($filesDir)) {
                mkdir($filesDir, 0755, true);
            }

            if (! rename($partPath, $finalPath)) {
                throw new \RuntimeException('ไม่สามารถย้ายไฟล์ที่อัปโหลดไปยังพื้นที่จัดเก็บได้');
            }

            /** @var Node $node */
            $node = DB::transaction(function () use (
                $uuid,
                $parentId,
                $originalName,
                $rootType,
                $departmentId,
                $user,
                $storagePath,
                $actualSize,
                $realMime,
                $extension,
                $category,
                $sha256,
                $request,
            ): Node {
                $createdNode = Node::create([
                    'uuid' => $uuid,
                    'parent_id' => $parentId,
                    'type' => NodeType::File,
                    'name' => $originalName,
                    'root_type' => $rootType,
                    'department_id' => $departmentId,
                    'owner_id' => $user->id,
                    'storage_path' => $storagePath,
                    'size_bytes' => $actualSize,
                    'mime_type' => $realMime,
                    'extension' => $extension !== '' ? Str::limit($extension, 20, '') : null,
                    'category' => $category,
                    'sha256' => $sha256,
                    'is_important' => false,
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'upload',
                    'node_id' => $createdNode->id,
                    'node_name' => $createdNode->name,
                    'ip' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'meta' => [
                        'size_bytes' => $actualSize,
                        'mime_type' => $realMime,
                        'sha256' => $sha256,
                        'root_type' => $rootType->value,
                    ],
                    'created_at' => now(),
                ]);

                if ($actualSize > 0) {
                    $user->increment('used_bytes', $actualSize);
                }

                return $createdNode;
            });
        } catch (\Throwable $e) {
            if (is_file($partPath)) {
                @unlink($partPath);
            }

            throw $e;
        }

        return response()->json([
            'status' => 'completed',
            'node_uuid' => $node->uuid,
            'name' => $node->name,
        ]);
    }

    public function cancelChunkUpload(Request $request, string $uploadId): Response
    {
        if (! Str::isUuid($uploadId)) {
            abort(422, 'รหัสการอัปโหลดไม่ถูกต้อง');
        }

        /** @var User $user */
        $user = $request->user();
        $partPath = Storage::disk('local')->path("chunks/{$user->id}/{$uploadId}.part");

        if (is_file($partPath)) {
            @unlink($partPath);
        }

        return response()->noContent();
    }

    public function rename(Request $request, Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[\/\\\\]/'],
        ]);

        $newName = trim($validated['name']);

        $this->ensureUniqueNameInFolder(
            $newName,
            $node->parent_id,
            $node->root_type,
            $node->owner_id,
            $node->department_id,
            $node->id,
            'name'
        );

        $oldName = $node->name;
        $node->update(['name' => $newName]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'rename',
            'node_id' => $node->id,
            'node_name' => $newName,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => ['old_name' => $oldName, 'new_name' => $newName],
            'created_at' => now(),
        ]);

        return back()->with('success', "เปลี่ยนชื่อเป็น {$newName} เรียบร้อยแล้ว");
    }

    public function move(Request $request, Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        $validated = $request->validate([
            'target_parent_id' => ['nullable', 'integer', Rule::exists('nodes', 'id')],
        ]);

        $targetParentId = $validated['target_parent_id'] ?? null;
        $targetRootType = $node->root_type;
        $targetDepartmentId = $node->department_id;

        if ($targetParentId !== null) {
            $targetFolder = Node::query()
                ->where('id', $targetParentId)
                ->where('type', NodeType::Folder)
                ->firstOrFail();

            if ($node->type === NodeType::File) {
                Gate::authorize('upload', $targetFolder);
            } else {
                Gate::authorize('update', $targetFolder);
            }

            if ($node->type === NodeType::Folder && $this->isSelfOrDescendant($node, $targetFolder)) {
                throw ValidationException::withMessages([
                    'target_parent_id' => ['ไม่สามารถย้ายโฟลเดอร์เข้าไปในตัวเองหรือโฟลเดอร์ย่อยของตัวเองได้'],
                ]);
            }

            $targetRootType = $targetFolder->root_type;
            $targetDepartmentId = $targetFolder->department_id;
        } else {
            if (
                $targetRootType === RootType::Shared
                && ! in_array($request->user()?->role, [UserRole::Admin, UserRole::DeptManager], true)
            ) {
                abort(403);
            }
        }

        if ($node->parent_id === $targetParentId) {
            return back();
        }

        $this->ensureUniqueNameInFolder(
            $node->name,
            $targetParentId,
            $targetRootType,
            $node->owner_id,
            $targetDepartmentId,
            $node->id,
            'target_parent_id'
        );

        $oldParentId = $node->parent_id;

        $node->update([
            'parent_id' => $targetParentId,
            'root_type' => $targetRootType,
            'department_id' => $targetDepartmentId,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'move',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'meta' => [
                'from_parent_id' => $oldParentId,
                'to_parent_id' => $targetParentId,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "ย้าย {$node->name} เรียบร้อยแล้ว");
    }

    public function download(Request $request, Node $node): StreamedResponse
    {
        Gate::authorize('download', $node);

        if ($node->type !== NodeType::File || $node->storage_path === null) {
            abort(400, 'ไม่สามารถดาวน์โหลดโฟลเดอร์โดยตรงได้');
        }

        if (! Storage::disk('local')->exists($node->storage_path)) {
            abort(404, 'ไม่พบไฟล์บนดิสก์');
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'download',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);

        return Storage::disk('local')->download(
            $node->storage_path,
            $node->name,
            ['Content-Type' => $node->mime_type ?? 'application/octet-stream']
        );
    }

    public function preview(Request $request, Node $node): SymfonyResponse
    {
        Gate::authorize('download', $node);

        if ($node->type !== NodeType::File || $node->storage_path === null) {
            abort(400, 'ไม่สามารถเปิดดูโฟลเดอร์ได้');
        }

        if (! Storage::disk('local')->exists($node->storage_path)) {
            abort(404, 'ไม่พบไฟล์บนดิสก์');
        }

        $rangeHeader = $request->header('Range');
        if ($rangeHeader === null || str_starts_with($rangeHeader, 'bytes=0-')) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'view',
                'node_id' => $node->id,
                'node_name' => $node->name,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'created_at' => now(),
            ]);
        }

        $fullPath = Storage::disk('local')->path($node->storage_path);
        $safeFilename = str_replace(['"', "\r", "\n"], '', $node->name);

        return response()->file($fullPath, [
            'Content-Type' => $node->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"; filename*=UTF-8\'\''.rawurlencode($node->name),
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Ensure that no active node with the same name exists in the target folder.
     */
    protected function ensureUniqueNameInFolder(
        string $name,
        ?int $parentId,
        RootType $rootType,
        int $ownerId,
        ?int $departmentId = null,
        ?int $ignoreNodeId = null,
        string $errorKey = 'name'
    ): void {
        $exists = Node::query()
            ->whereNull('deleted_at')
            ->where('name', $name)
            ->when($parentId !== null, function ($query) use ($parentId): void {
                $query->where('parent_id', $parentId);
            }, function ($query) use ($rootType, $ownerId, $departmentId): void {
                $query->whereNull('parent_id')
                    ->where('root_type', $rootType);

                if ($rootType === RootType::Personal) {
                    $query->where('owner_id', $ownerId);
                } elseif ($rootType === RootType::Department && $departmentId !== null) {
                    $query->where('department_id', $departmentId);
                }
            })
            ->when($ignoreNodeId !== null, function ($query) use ($ignoreNodeId): void {
                $query->where('id', '!=', $ignoreNodeId);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                $errorKey => ["มีไฟล์หรือโฟลเดอร์ชื่อ \"{$name}\" อยู่ในโฟลเดอร์นี้แล้ว"],
            ]);
        }
    }

    /**
     * Determine whether $targetFolder is $folder itself or one of $folder's descendants.
     */
    protected function isSelfOrDescendant(Node $folder, Node $targetFolder): bool
    {
        $current = $targetFolder;
        $visited = [];

        while ($current !== null) {
            if ($current->id === $folder->id) {
                return true;
            }

            if ($current->parent_id === null || isset($visited[$current->parent_id])) {
                break;
            }

            $visited[$current->parent_id] = true;
            $current = Node::withTrashed()->find($current->parent_id);
        }

        return false;
    }

    /**
     * Build breadcrumb trail from the root folder down to the current folder.
     *
     * @return array<int, array{id: int, uuid: string, name: string}>
     */
    protected function buildBreadcrumbs(?Node $currentFolder): array
    {
        if ($currentFolder === null) {
            return [];
        }

        $trail = [];
        $current = $currentFolder;
        $visited = [];

        while ($current !== null) {
            $trail[] = [
                'id' => $current->id,
                'uuid' => $current->uuid,
                'name' => $current->name,
            ];

            if ($current->parent_id === null || isset($visited[$current->parent_id])) {
                break;
            }

            $visited[$current->parent_id] = true;
            $current = Node::find($current->parent_id);
        }

        return array_reverse($trail);
    }

    /**
     * @return array{0: RootType, 1: int|null}
     */
    protected function resolveUploadTarget(User $user, ?int $parentId, RootType $requestedRootType): array
    {
        if ($parentId !== null) {
            $parent = Node::query()
                ->where('id', $parentId)
                ->where('type', NodeType::Folder)
                ->firstOrFail();

            Gate::authorize('upload', $parent);

            return [$parent->root_type, $parent->department_id];
        }

        if ($requestedRootType === RootType::Shared && ! in_array($user->role, [UserRole::Admin, UserRole::DeptManager], true)) {
            abort(403, 'เฉพาะผู้ดูแลระบบและหัวหน้าแผนกเท่านั้นที่อัปโหลดไฟล์ในส่วนกลางได้');
        }

        $departmentId = null;
        if ($requestedRootType === RootType::Department) {
            if ($user->department_id === null && $user->role !== UserRole::Admin) {
                abort(403, 'คุณไม่ได้สังกัดแผนกจึงไม่สามารถอัปโหลดไฟล์ในโฟลเดอร์แผนกได้');
            }
            $departmentId = $user->department_id;
        }

        return [$requestedRootType, $departmentId];
    }

    protected function detectRealMimeType(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if ($realPath !== false) {
            return $this->detectMimeTypeFromPath($realPath, $file->getMimeType());
        }

        return $file->getMimeType() ?: 'application/octet-stream';
    }

    protected function detectMimeTypeFromPath(string $path, ?string $fallback = null): string
    {
        if (is_file($path) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }

        return $fallback ?: 'application/octet-stream';
    }

    protected function resolveFileCategory(string $mimeType, string $extension): FileCategory
    {
        if (str_starts_with($mimeType, 'image/')) {
            return FileCategory::Image;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return FileCategory::Video;
        }

        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/rtf',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md', 'rtf'];

        if (
            in_array($mimeType, $documentMimes, true)
            || str_starts_with($mimeType, 'text/')
            || in_array($extension, $documentExtensions, true)
        ) {
            return FileCategory::Document;
        }

        return FileCategory::Other;
    }
}
