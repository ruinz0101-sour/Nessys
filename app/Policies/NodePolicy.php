<?php

namespace App\Policies;

use App\Enums\NodeType;
use App\Enums\RequestStatus;
use App\Enums\RootType;
use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\AccessRequest;
use App\Models\Node;
use App\Models\Share;
use App\Models\User;

class NodePolicy
{
    /**
     * Determine whether the user can view the node.
     *
     * Order of evaluation:
     * 0. User must be active and node (including ancestors) must not be in trash
     * 1. Admin
     * 2. Owner
     * 3. Shared root_type (viewable by all active users)
     * 3b. Department root_type (viewable by active members of the same department)
     * 4. Active (non-expired, non-revoked) share on node or any ancestor folder (user or department)
     * 5. Approved (non-expired) access request on node or any ancestor folder
     * 6. Otherwise deny
     */
    public function view(User $user, Node $node): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $chain = $this->getNodeWithAncestors($node);

        if ($this->isAnyNodeTrashed($chain)) {
            return false;
        }

        // 1. Admin
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // 2. เจ้าของ (Owner)
        if ($node->owner_id === $user->id) {
            return true;
        }

        // 3. ไฟล์ส่วนกลาง (Shared root_type -> ดูได้ทุกคน)
        if ($node->root_type === RootType::Shared) {
            return true;
        }

        // 3b. ไฟล์แผนก (Department root_type -> สมาชิกในแผนกเดียวกันดูได้)
        if (
            $node->root_type === RootType::Department
            && $user->department_id !== null
            && $node->department_id === $user->department_id
        ) {
            return true;
        }

        $nodeIds = array_map(static fn (Node $item): int => $item->id, $chain);

        // 4. มี Share ที่ยังไม่หมดอายุ และไม่ถูก revoke (รวมสืบทอดจากโฟลเดอร์แม่)
        if ($this->hasActiveShare($user, $nodeIds)) {
            return true;
        }

        // 5. มีคำขอที่อนุมัติ และยังไม่หมดอายุ (รวมสืบทอดจากโฟลเดอร์แม่)
        if ($this->hasApprovedAccessRequest($user, $nodeIds)) {
            return true;
        }

        // 6. นอกนั้นปฏิเสธ
        return false;
    }

    /**
     * Determine whether the user can download the node.
     */
    public function download(User $user, Node $node): bool
    {
        return $this->view($user, $node);
    }

    /**
     * Determine whether the user can upload files into the given folder.
     */
    public function upload(User $user, Node $folder): bool
    {
        if (! $user->is_active || $folder->type !== NodeType::Folder) {
            return false;
        }

        $chain = $this->getNodeWithAncestors($folder);

        if ($this->isAnyNodeTrashed($chain)) {
            return false;
        }

        // โฟลเดอร์แลกเปลี่ยนส่วนกลาง (Public Dropzone): User ทุกคนที่ active สามารถอัปโหลดไฟล์ส่งงานได้
        if ($folder->root_type === RootType::Shared && $this->isInsideDropzone($chain)) {
            return true;
        }

        return $this->update($user, $folder);
    }

    /**
     * Determine whether the user can edit/update the node.
     */
    public function update(User $user, Node $node): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $chain = $this->getNodeWithAncestors($node);

        if ($this->isAnyNodeTrashed($chain)) {
            return false;
        }

        // 1. Admin
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // ป้องกันการเปลี่ยนชื่อหรือย้ายโฟลเดอร์ Public Dropzone หลักโดยผู้ที่ไม่ใช่ Admin
        if ($node->is_dropzone) {
            return false;
        }

        // 2. เจ้าของ (Owner) สามารถแก้ไข/เปลี่ยนชื่อไฟล์ของตนเองได้
        if ($node->owner_id === $user->id) {
            return true;
        }

        // 3. โฟลเดอร์ส่วนกลาง (Official Folders & ไฟล์ในส่วนกลาง): Admin และ Dept Manager จัดการได้
        if (
            $node->root_type === RootType::Shared
            && $user->role === UserRole::DeptManager
        ) {
            return true;
        }

        // โฟลเดอร์แผนก: Dept Manager จัดการได้ทุกไฟล์ในแผนก และสมาชิกแผนกอัปโหลดไฟล์ลงโฟลเดอร์ของแผนกตนได้
        if (
            $node->root_type === RootType::Department
            && $user->department_id !== null
            && $node->department_id === $user->department_id
        ) {
            if ($user->role === UserRole::DeptManager || $node->type === NodeType::Folder) {
                return true;
            }
        }

        $nodeIds = array_map(static fn (Node $item): int => $item->id, $chain);

        // 4. มี Share สิทธิ์ Edit ที่ยังไม่หมดอายุ และไม่ถูก revoke (รวมสืบทอดจากโฟลเดอร์แม่)
        if ($this->hasActiveShare($user, $nodeIds, SharePermission::Edit)) {
            return true;
        }

        // 5. มีคำขอที่อนุมัติด้วยสิทธิ์ Edit และยังไม่หมดอายุ (รวมสืบทอดจากโฟลเดอร์แม่)
        if ($this->hasApprovedAccessRequest($user, $nodeIds, SharePermission::Edit)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can soft-delete (move to trash) the node.
     */
    public function delete(User $user, Node $node): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $chain = $this->getNodeWithAncestors($node);

        if ($this->isAnyNodeTrashed($chain)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        // ป้องกันการลบโฟลเดอร์ Public Dropzone หลักโดยผู้ที่ไม่ใช่ Admin
        if ($node->is_dropzone) {
            return false;
        }

        if ($node->owner_id === $user->id) {
            return true;
        }

        if ($node->root_type === RootType::Shared && $user->role === UserRole::DeptManager) {
            return true;
        }

        return $node->root_type === RootType::Department
            && $user->role === UserRole::DeptManager
            && $user->department_id !== null
            && $node->department_id === $user->department_id;
    }

    /**
     * Determine whether the user can restore a trashed node.
     */
    public function restore(User $user, Node $node): bool
    {
        if (! $user->is_active || ! $node->trashed()) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($node->owner_id === $user->id) {
            return true;
        }

        if ($node->root_type === RootType::Shared && $user->role === UserRole::DeptManager) {
            return true;
        }

        return $node->root_type === RootType::Department
            && $user->role === UserRole::DeptManager
            && $user->department_id !== null
            && $node->department_id === $user->department_id;
    }

    /**
     * Determine whether the user can permanently delete the node.
     */
    public function forceDelete(User $user, Node $node): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $node->owner_id === $user->id;
    }

    /**
     * Determine whether the user can share the node.
     */
    public function share(User $user, Node $node): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $chain = $this->getNodeWithAncestors($node);

        if ($this->isAnyNodeTrashed($chain)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($node->owner_id === $user->id) {
            return true;
        }

        return $node->root_type === RootType::Department
            && $user->role === UserRole::DeptManager
            && $user->department_id !== null
            && $node->department_id === $user->department_id;
    }

    /**
     * Collect the given node and all of its ancestor folders up to the root.
     *
     * @return array<int, Node>
     */
    protected function getNodeWithAncestors(Node $node): array
    {
        $chain = [$node];
        $current = $node;
        $visited = [$node->id => true];

        while ($current->parent_id !== null) {
            if (isset($visited[$current->parent_id])) {
                break;
            }

            $parent = $current->relationLoaded('parent')
                ? $current->parent
                : Node::withTrashed()->find($current->parent_id);

            if ($parent === null) {
                break;
            }

            $visited[$parent->id] = true;
            $chain[] = $parent;
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Check if the node itself or any ancestor in the chain is soft-deleted.
     *
     * @param  array<int, Node>  $chain
     */
    protected function isAnyNodeTrashed(array $chain): bool
    {
        foreach ($chain as $item) {
            if ($item->trashed()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the node itself or any ancestor in the chain is a Public Dropzone folder.
     *
     * @param  array<int, Node>  $chain
     */
    protected function isInsideDropzone(array $chain): bool
    {
        foreach ($chain as $item) {
            if ($item->is_dropzone) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user (or their department) has an active share on any node ID in the chain.
     *
     * @param  array<int, int>  $nodeIds
     */
    protected function hasActiveShare(User $user, array $nodeIds, ?SharePermission $requiredPermission = null): bool
    {
        return Share::query()
            ->whereIn('node_id', $nodeIds)
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
            ->when($requiredPermission !== null, function ($query) use ($requiredPermission): void {
                $query->where('permission', $requiredPermission->value);
            })
            ->exists();
    }

    /**
     * Check if the user has an approved, non-expired access request on any node ID in the chain.
     *
     * @param  array<int, int>  $nodeIds
     */
    protected function hasApprovedAccessRequest(User $user, array $nodeIds, ?SharePermission $requiredPermission = null): bool
    {
        return AccessRequest::query()
            ->whereIn('node_id', $nodeIds)
            ->where('requester_id', $user->id)
            ->where('status', RequestStatus::Approved->value)
            ->where(function ($query): void {
                $query->whereNull('granted_until')
                    ->orWhere('granted_until', '>', now());
            })
            ->when($requiredPermission !== null, function ($query) use ($requiredPermission): void {
                $query->where('granted_permission', $requiredPermission->value);
            })
            ->exists();
    }
}
