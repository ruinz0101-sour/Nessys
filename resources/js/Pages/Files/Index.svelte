<script>
import { Link, router, useForm, usePage } from '@inertiajs/svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';
import FilePreviewModal from '../../Components/FilePreviewModal.svelte';
import ConfirmDeleteModal from '../../Components/ConfirmDeleteModal.svelte';
import {
    uploadState,
    startUpload as startGlobalUpload,
    showQuotaExceededModal as showGlobalQuotaExceededModal,
} from '../../stores/uploadManager.svelte.js';

let {
    currentRoot = 'personal',
    currentFolder = null,
    dropzoneFolder = null,
    breadcrumbs = [],
    nodes = [],
    tree = { personal: [], department: [], shared: [] },
    canCreateInCurrent = true,
    canUploadInCurrent = true,
    canCreateFolderInCurrent = true,
    shareRecipients = [],
    shareDepartments = [],
    filters = {},
} = $props();

let viewMode = $state('list'); // 'list' | 'grid'
let searchInput = $state('');
let categoryInput = $state('');
let previewingFile = $state(null);
let openMenuNode = $state(null);
let menuPos = $state({ top: 0, right: 0 });

function portal(el) {
    document.body.appendChild(el);
    return {
        destroy() {
            el.remove();
        },
    };
}

function toggleNodeMenu(node, event) {
    event.stopPropagation();
    if (openMenuNode?.id === node.id) {
        openMenuNode = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    const menuHeight = (node.can_edit ? 76 : 0) + (node.can_delete ? 44 : 0) + 16;
    const openUpward = window.innerHeight - rect.bottom < menuHeight + 12 && rect.top > menuHeight + 12;
    menuPos = {
        top: openUpward ? Math.max(8, rect.top - menuHeight - 4) : rect.bottom + 4,
        right: Math.max(8, window.innerWidth - rect.right),
    };
    openMenuNode = node;
}

function closeNodeMenu() {
    if (openMenuNode) {
        openMenuNode = null;
    }
}

function handleWindowKeydown(event) {
    if (event.key === 'Escape' && openMenuNode) {
        openMenuNode = null;
    }
}

function openPreview(node) {
    if (!node || node.type === 'folder') {
        return;
    }
    previewingFile = {
        uuid: node.uuid,
        name: node.name,
        size_bytes: node.size_bytes,
        mime_type: node.mime_type,
        extension: node.extension,
        shared_by_name: null,
    };
}

$effect(() => {
    searchInput = filters.search ?? '';
    categoryInput = filters.category ?? '';
});

let showFolderModal = $state(false);
let renamingNode = $state(null);
let movingNode = $state(null);
let sharingNode = $state(null);
let shareTargetType = $state('user'); // 'user' | 'department' | 'link'
let shareLinkPassword = $state('');
let shareLinkMaxDownloads = $state('');
let shareLinkCreating = $state(false);
let shareLinkError = $state('');
let generatedShareLink = $state(null);
let copiedShareUrl = $state(false);
let uploadInputRef = $state(null);
let dropzoneUploadInputRef = $state(null);

const folderForm = useForm({
    name: '',
    root_type: 'personal',
    parent_id: null,
    is_dropzone: false,
});

const uploadForm = useForm({
    files: [],
    root_type: 'personal',
    parent_id: null,
});

const renameForm = useForm({
    name: '',
});

const moveForm = useForm({
    target_parent_id: '',
});

const shareForm = useForm({
    node_id: null,
    shared_with_user_id: '',
    shared_with_department_id: '',
    permission: 'view',
    expires_at: '',
});

function buildTreeNodes(flatList, parentId = null, depth = 0) {
    const result = [];
    for (const item of flatList) {
        if ((item.parent_id ?? null) === parentId) {
            result.push({ ...item, depth });
            result.push(...buildTreeNodes(flatList, item.id, depth + 1));
        }
    }
    return result;
}

let personalTree = $derived(buildTreeNodes(tree.personal ?? []));
let departmentTree = $derived(buildTreeNodes(tree.department ?? []));
let sharedTree = $derived(buildTreeNodes(tree.shared ?? []));

let availableMoveFolders = $derived.by(() => {
    const list = currentRoot === 'shared'
        ? sharedTree
        : currentRoot === 'department'
            ? departmentTree
            : personalTree;

    if (!movingNode || movingNode.type !== 'folder') {
        return list;
    }
    const blockedIds = new Set([movingNode.id]);
    let added = true;
    while (added) {
        added = false;
        for (const f of list) {
            if (f.parent_id && blockedIds.has(f.parent_id) && !blockedIds.has(f.id)) {
                blockedIds.add(f.id);
                added = true;
            }
        }
    }
    return list.filter((f) => !blockedIds.has(f.id));
});

function rootLabel(root) {
    if (root === 'shared') {
        return 'พื้นที่ส่วนกลาง';
    }
    if (root === 'department') {
        return 'พื้นที่แผนก';
    }
    return 'พื้นที่ส่วนตัว';
}

function applySearch(e) {
    e?.preventDefault();
    router.get('/files', {
        root: currentRoot,
        folder: currentFolder?.uuid || undefined,
        search: searchInput || undefined,
        category: categoryInput || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function openCreateFolder() {
    folderForm.name = '';
    folderForm.root_type = currentRoot;
    folderForm.parent_id = currentFolder?.id ?? null;
    folderForm.is_dropzone = false;
    folderForm.clearErrors();
    showFolderModal = true;
}

function submitCreateFolder(e) {
    e.preventDefault();
    folderForm.post('/files/folders', {
        preserveScroll: true,
        onSuccess: () => {
            showFolderModal = false;
            folderForm.reset();
        },
    });
}

const page = usePage();
let authUser = $derived(page.props.auth?.user);
let quotaBytes = $derived(Number(authUser?.quota_bytes ?? 0));
let usedBytes = $derived(Number(authUser?.used_bytes ?? 0));
let remainingBytes = $derived(quotaBytes > 0 ? Math.max(0, quotaBytes - usedBytes) : null);
let isQuotaFull = $derived(quotaBytes > 0 && usedBytes >= quotaBytes);

let isUploading = $derived(uploadState.isUploading);

function triggerCurrentUpload() {
    const destination = currentFolder
        ? `${rootLabel(currentRoot)} / ${currentFolder.name}`
        : rootLabel(currentRoot);

    if (isQuotaFull) {
        showGlobalQuotaExceededModal([], destination, { usedBytes, quotaBytes });
        return;
    }
    uploadInputRef?.click();
}

function triggerDropzoneUpload() {
    if (!dropzoneFolder) {
        return;
    }
    if (isQuotaFull) {
        showGlobalQuotaExceededModal([], `พื้นที่ส่วนกลาง / ${dropzoneFolder.name}`, { usedBytes, quotaBytes });
        return;
    }
    dropzoneUploadInputRef?.click();
}

function handleFileSelect(e) {
    const selected = Array.from(e.target.files ?? []);
    if (selected.length === 0) {
        return;
    }
    const destination = currentFolder
        ? `${rootLabel(currentRoot)} / ${currentFolder.name}`
        : rootLabel(currentRoot);

    uploadForm.clearErrors();
    startGlobalUpload(selected, {
        rootType: currentRoot,
        parentId: currentFolder?.id ?? null,
        destination,
        quotaBytes,
        usedBytes,
        resetInput: () => {
            if (uploadInputRef) {
                uploadInputRef.value = '';
            }
        },
    });
}

function handleDropzoneDirectUpload(e) {
    const selected = Array.from(e.target.files ?? []);
    if (selected.length === 0 || !dropzoneFolder) {
        return;
    }

    uploadForm.clearErrors();
    startGlobalUpload(selected, {
        rootType: 'shared',
        parentId: dropzoneFolder.id,
        destination: `พื้นที่ส่วนกลาง / ${dropzoneFolder.name}`,
        quotaBytes,
        usedBytes,
        onSuccessExtra: () => {
            if (currentFolder?.id !== dropzoneFolder.id) {
                router.visit(`/files?root=shared&folder=${dropzoneFolder.uuid}`);
            } else {
                router.reload({ preserveScroll: true });
            }
        },
        resetInput: () => {
            if (dropzoneUploadInputRef) {
                dropzoneUploadInputRef.value = '';
            }
        },
    });
}

function openRenameModal(node) {
    renamingNode = node;
    renameForm.name = node.name;
    renameForm.clearErrors();
}

function submitRename(e) {
    e.preventDefault();
    if (!renamingNode) {
        return;
    }
    renameForm.patch(`/files/${renamingNode.uuid}/rename`, {
        preserveScroll: true,
        onSuccess: () => {
            renamingNode = null;
        },
    });
}

function openMoveModal(node) {
    movingNode = node;
    moveForm.target_parent_id = node.parent_id ? String(node.parent_id) : '';
    moveForm.clearErrors();
}

function submitMove(e) {
    e.preventDefault();
    if (!movingNode) {
        return;
    }
    moveForm.transform((data) => ({
        target_parent_id: data.target_parent_id === '' ? null : Number(data.target_parent_id),
    })).patch(`/files/${movingNode.uuid}/move`, {
        preserveScroll: true,
        onSuccess: () => {
            movingNode = null;
        },
    });
}

let deletingNode = $state(null);
let isDeletingNode = $state(false);

function moveToTrash(node) {
    deletingNode = node;
}

function confirmMoveToTrash() {
    if (!deletingNode || isDeletingNode) {
        return;
    }
    isDeletingNode = true;
    router.delete(`/files/${deletingNode.uuid}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeletingNode = false;
            deletingNode = null;
        },
    });
}

function getXsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function openShareModal(node) {
    sharingNode = node;
    shareTargetType = 'user';
    shareForm.node_id = node.id;
    shareForm.shared_with_user_id = '';
    shareForm.shared_with_department_id = '';
    shareForm.permission = 'view';
    shareForm.expires_at = '';
    shareForm.clearErrors();
    shareLinkPassword = '';
    shareLinkMaxDownloads = '';
    shareLinkCreating = false;
    shareLinkError = '';
    generatedShareLink = null;
    copiedShareUrl = false;
}

async function copyShareUrl(url) {
    if (!url) {
        return;
    }
    try {
        await navigator.clipboard.writeText(url);
        copiedShareUrl = true;
        setTimeout(() => {
            copiedShareUrl = false;
        }, 2000);
    } catch {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copiedShareUrl = true;
        setTimeout(() => {
            copiedShareUrl = false;
        }, 2000);
    }
}

async function createShareLink() {
    if (!sharingNode || shareLinkCreating) {
        return;
    }
    shareLinkCreating = true;
    shareLinkError = '';
    generatedShareLink = null;

    try {
        const xsrf = getXsrfToken();
        const response = await fetch('/share-links', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            },
            body: JSON.stringify({
                node_id: sharingNode.id,
                expires_at: shareForm.expires_at || null,
                password: shareLinkPassword || null,
                max_downloads: shareLinkMaxDownloads ? Number(shareLinkMaxDownloads) : null,
            }),
        });

        const body = await response.json().catch(() => null);
        if (!response.ok) {
            const firstErr = body?.errors
                ? Object.values(body.errors).flat().find((m) => typeof m === 'string' && m.length > 0)
                : null;
            shareLinkError = firstErr || body?.message || 'ไม่สามารถสร้างลิงก์แชร์ได้';
            return;
        }

        generatedShareLink = body?.share_link ?? null;
    } catch {
        shareLinkError = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง';
    } finally {
        shareLinkCreating = false;
    }
}

function submitShareNode(e) {
    e.preventDefault();
    if (!sharingNode) {
        return;
    }
    if (shareTargetType === 'link') {
        createShareLink();
        return;
    }
    shareForm.transform((data) => ({
        node_id: sharingNode.id,
        shared_with_user_id: shareTargetType === 'user' && data.shared_with_user_id ? Number(data.shared_with_user_id) : null,
        shared_with_department_id: shareTargetType === 'department' && data.shared_with_department_id ? Number(data.shared_with_department_id) : null,
        permission: data.permission,
        expires_at: data.expires_at || null,
    })).post('/shares', {
        preserveScroll: true,
        onSuccess: () => {
            sharingNode = null;
            shareForm.reset();
        },
    });
}

function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

function categoryBadge(cat) {
    if (cat === 'image') {
        return { label: 'รูปภาพ', cls: 'bg-[#FFF9F0] text-[#A86400] border border-[#FF9900]/30' };
    }
    if (cat === 'video') {
        return { label: 'วิดีโอ', cls: 'bg-[#F5F0FA] text-[#6B21A8] border border-[#A855F7]/30' };
    }
    if (cat === 'document') {
        return { label: 'เอกสาร', cls: 'bg-[#EBF8FC] text-[#007099] border border-[#00A8E1]/30' };
    }
    return { label: 'ไฟล์ทั่วไป', cls: 'bg-[#F3F4F6] text-[#4B5563] border border-[#D1D5DB]' };
}
</script>

<svelte:window onclick={closeNodeMenu} onkeydown={handleWindowKeydown} onscroll={closeNodeMenu} onresize={closeNodeMenu} />

<AppLayout title="จัดการไฟล์และโฟลเดอร์">
    <div class="flex flex-col gap-5">
        <!-- Root Scope Switcher Tabs -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-2 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div class="inline-flex rounded-xl border border-slate-200/80 bg-slate-100/80 p-1 gap-1">
                <Link
                    href="/files?root=personal"
                    class="px-4 py-2 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-2 {currentRoot === 'personal' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'}"
                >
                    <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                    </svg>
                    <span>โฟลเดอร์ส่วนตัว</span>
                </Link>

                <Link
                    href="/files?root=department"
                    class="px-4 py-2 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-2 {currentRoot === 'department' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'}"
                >
                    <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span>โฟลเดอร์แผนก</span>
                </Link>

                <Link
                    href="/files?root=shared"
                    class="px-4 py-2 rounded-lg text-xs font-semibold transition-all cursor-pointer flex items-center gap-2 {currentRoot === 'shared' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'}"
                >
                    <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="2" y1="12" x2="22" y2="12" />
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                    </svg>
                    <span>โฟลเดอร์ส่วนกลาง</span>
                </Link>
            </div>

            <!-- Direct Dropzone Fast Upload button -->
            {#if dropzoneFolder}
                <div class="flex items-center gap-2">
                    <input
                        bind:this={dropzoneUploadInputRef}
                        type="file"
                        multiple
                        onchange={handleDropzoneDirectUpload}
                        class="hidden"
                    />
                    <button
                        type="button"
                        disabled={isUploading}
                        onclick={triggerDropzoneUpload}
                        class="btn-success"
                        title="อัปโหลดไฟล์ส่งงานเข้าที่ส่งงานส่วนกลางทันที"
                    >
                        <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="17 8 12 3 7 8" />
                            <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                        <span>ส่งงานส่วนกลาง</span>
                    </button>
                </div>
            {/if}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Left Tree Navigation -->
            <div class="lg:col-span-3 bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-col gap-5 h-fit shadow-xs">
                <!-- Personal Tree -->
                <div>
                    <Link
                        href="/files?root=personal"
                        class="flex items-center gap-2 px-3 py-2 rounded-[3px] font-bold text-xs transition duration-200 {currentRoot === 'personal' && !currentFolder ? 'bg-[#232F3E] text-white border-l-[3px] border-[#FF9900] pl-2.5' : 'text-[#131921] hover:bg-[#F7F9FA]'}"
                    >
                        <svg class="w-4 h-4 shrink-0 text-[#00A8E1]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                        </svg>
                        <span>โฟลเดอร์ส่วนตัว</span>
                    </Link>

                    <div class="mt-1 flex flex-col gap-0.5">
                        {#each personalTree as folder (folder.id)}
                            <Link
                                href={`/files?root=personal&folder=${folder.uuid}`}
                                class="py-1.5 pr-2 rounded-[3px] text-xs flex items-center gap-2 truncate transition duration-200 {currentFolder?.id === folder.id ? 'bg-[#EBF8FC] text-[#007099] font-bold border-l-2 border-[#00A8E1]' : 'text-[#555555] hover:bg-[#F7F9FA] hover:text-[#131921]'}"
                                style="padding-left: {(folder.depth + 1) * 12}px"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0 text-[#FF9900]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                </svg>
                                <span class="truncate">{folder.name}</span>
                            </Link>
                        {/each}
                    </div>
                </div>

                <!-- Department Tree -->
                <div class="border-t border-[#DDDDDD] pt-4">
                    <Link
                        href="/files?root=department"
                        class="flex items-center gap-2 px-3 py-2 rounded-[3px] font-bold text-xs transition duration-200 {currentRoot === 'department' && !currentFolder ? 'bg-[#232F3E] text-white border-l-[3px] border-[#FF9900] pl-2.5' : 'text-[#131921] hover:bg-[#F7F9FA]'}"
                    >
                        <svg class="w-4 h-4 shrink-0 text-[#00A8E1]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <span>โฟลเดอร์แผนก</span>
                    </Link>

                    <div class="mt-1 flex flex-col gap-0.5">
                        {#each departmentTree as folder (folder.id)}
                            <Link
                                href={`/files?root=department&folder=${folder.uuid}`}
                                class="py-1.5 pr-2 rounded-[3px] text-xs flex items-center gap-2 truncate transition duration-200 {currentFolder?.id === folder.id ? 'bg-[#EBF8FC] text-[#007099] font-bold border-l-2 border-[#00A8E1]' : 'text-[#555555] hover:bg-[#F7F9FA] hover:text-[#131921]'}"
                                style="padding-left: {(folder.depth + 1) * 12}px"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0 text-[#FF9900]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                </svg>
                                <span class="truncate">{folder.name}</span>
                            </Link>
                        {/each}
                    </div>
                </div>

                <!-- Shared Tree -->
                <div class="border-t border-[#DDDDDD] pt-4">
                    <Link
                        href="/files?root=shared"
                        class="flex items-center gap-2 px-3 py-2 rounded-[3px] font-bold text-xs transition duration-200 {currentRoot === 'shared' && !currentFolder ? 'bg-[#232F3E] text-white border-l-[3px] border-[#FF9900] pl-2.5' : 'text-[#131921] hover:bg-[#F7F9FA]'}"
                    >
                        <svg class="w-4 h-4 shrink-0 text-[#00A8E1]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="2" y1="12" x2="22" y2="12" />
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                        </svg>
                        <span>โฟลเดอร์ส่วนกลาง</span>
                    </Link>

                    <div class="mt-1 flex flex-col gap-0.5">
                        {#each sharedTree as folder (folder.id)}
                            <Link
                                href={`/files?root=shared&folder=${folder.uuid}`}
                                class="py-1.5 pr-2 rounded-[3px] text-xs flex items-center justify-between gap-2 truncate transition duration-200 {currentFolder?.id === folder.id ? 'bg-[#EBF8FC] text-[#007099] font-bold border-l-2 border-[#00A8E1]' : 'text-[#555555] hover:bg-[#F7F9FA] hover:text-[#131921]'}"
                                style="padding-left: {(folder.depth + 1) * 12}px"
                            >
                                <div class="flex items-center gap-1.5 truncate">
                                    {#if folder.is_dropzone}
                                        <svg class="w-3.5 h-3.5 shrink-0 text-[#008000]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="17 8 12 3 7 8" />
                                            <line x1="12" y1="3" x2="12" y2="15" />
                                        </svg>
                                    {:else}
                                        <svg class="w-3.5 h-3.5 shrink-0 text-[#FF9900]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                        </svg>
                                    {/if}
                                    <span class="truncate">{folder.name}</span>
                                </div>
                                {#if folder.is_dropzone}
                                    <span class="px-1.5 py-0.2 rounded-[2px] text-[10px] font-bold shrink-0 bg-[#EBF7EE] text-[#008000] border border-[#008000]/20">
                                        ส่งงาน
                                    </span>
                                {/if}
                            </Link>
                        {/each}
                    </div>
                </div>

                <!-- Trash Direct Shortcut -->
                <div class="border-t border-[#DDDDDD] pt-3">
                    <Link
                        href="/trash"
                        class="flex items-center gap-2 px-3 py-2 rounded-[3px] text-xs font-semibold text-[#888888] hover:bg-[#FDF2F2] hover:text-[#9B1C1C] transition duration-200"
                    >
                        <svg class="w-4 h-4 shrink-0 text-[#888888]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18" />
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                        </svg>
                        <span>ถังขยะ</span>
                    </Link>
                </div>
            </div>

            <!-- Right Main Workspace -->
            <div class="lg:col-span-9 flex flex-col gap-4">
                <!-- Action Bar & Breadcrumbs -->
                <div class="bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-wrap items-center justify-between gap-4 shadow-xs">
                    <!-- Breadcrumbs -->
                    <nav class="flex items-center flex-wrap gap-1.5 text-xs">
                        <Link
                            href={`/files?root=${currentRoot}`}
                            class="font-bold text-[#00A8E1] hover:underline"
                        >
                            {rootLabel(currentRoot)}
                        </Link>

                        {#each breadcrumbs as crumb (crumb.id)}
                            <span class="text-[#AAAAAA]">/</span>
                            <Link
                                href={`/files?root=${currentRoot}&folder=${crumb.uuid}`}
                                class="font-medium text-[#131921] hover:text-[#00A8E1] hover:underline"
                            >
                                {crumb.name}
                            </Link>
                        {/each}
                    </nav>

                    <!-- Primary Action Buttons -->
                    <div class="flex items-center flex-wrap gap-2">
                        {#if canCreateFolderInCurrent}
                            <button
                                type="button"
                                onclick={openCreateFolder}
                                class="btn-secondary"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                                    <line x1="12" y1="11" x2="12" y2="17" />
                                    <line x1="9" y1="14" x2="15" y2="14" />
                                </svg>
                                <span>โฟลเดอร์ใหม่</span>
                            </button>
                        {/if}

                        {#if canUploadInCurrent}
                            <input
                                bind:this={uploadInputRef}
                                type="file"
                                multiple
                                onchange={handleFileSelect}
                                class="hidden"
                            />
                            <button
                                type="button"
                                disabled={isUploading}
                                onclick={triggerCurrentUpload}
                                class="btn-primary"
                            >
                                <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                                <span>{isUploading ? 'กำลังอัปโหลด...' : (currentFolder?.is_dropzone ? 'อัปโหลดส่งงานส่วนกลาง' : 'อัปโหลดไฟล์')}</span>
                            </button>
                        {/if}
                    </div>
                </div>

                <!-- Dropzone Banner when inside Dropzone folder -->
                {#if currentFolder?.is_dropzone}
                    <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-50 via-white to-teal-50/60 border border-emerald-200/80 shadow-xs flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/25">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 flex items-center gap-2">
                                    <span>กล่องส่งงานส่วนกลาง</span>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-600 text-white text-[10px] font-semibold">เปิดใช้งานสำหรับทุกคน</span>
                                </div>
                                <div class="text-[11px] text-slate-600 mt-1">
                                    พนักงานทุกคนสามารถอัปโหลดไฟล์ส่งงานได้ทันที · ระบบป้องกันความปลอดภัย: ห้ามลบหรือแก้ไขไฟล์ของผู้อื่น (แก้ไขได้เฉพาะไฟล์ของตนเองเท่านั้น)
                                </div>
                            </div>
                        </div>
                    </div>
                {:else if currentRoot === 'shared' && !currentFolder}
                    <!-- Organization Guide for Shared Root -->
                    <div class="p-4 rounded-2xl bg-gradient-to-r from-slate-50 via-white to-sky-50/50 border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-col gap-1 text-xs text-[#555555]">
                            <div class="font-bold text-[#131921]">การจัดระเบียบพื้นที่โฟลเดอร์ส่วนกลาง</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="font-bold text-[#232F3E]">1. โฟลเดอร์ทางการ:</span>
                                <span>นโยบาย, แบบฟอร์มบริษัท (สงวนสิทธิ์ผู้ดูแลระบบและหัวหน้าแผนก)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-[#008000]">2. กล่องแลกเปลี่ยน:</span>
                                <span>ผู้ใช้ทุกคนอัปโหลดส่งงานได้อิสระ</span>
                            </div>
                        </div>
                        {#if dropzoneFolder}
                            <Link
                                href={`/files?root=shared&folder=${dropzoneFolder.uuid}`}
                                class="btn-success text-xs"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                                <span>เปิดกล่องส่งงานส่วนกลาง</span>
                            </Link>
                        {/if}
                    </div>
                {/if}

                {#if uploadForm.errors.files}
                    <div class="p-3 rounded-[3px] bg-[#FDF2F2] border border-[#E53E3E]/30 text-[#9B1C1C] text-xs font-medium">
                        {uploadForm.errors.files}
                    </div>
                {/if}

                <!-- Search, Filter & View Mode Switcher -->
                <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 flex flex-wrap items-center justify-between gap-3 shadow-xs">
                    <form onsubmit={applySearch} class="flex flex-wrap items-center gap-2">
                        <input
                            type="text"
                            bind:value={searchInput}
                            placeholder="ค้นหาชื่อไฟล์หรือโฟลเดอร์..."
                            class="input-trust w-60 py-1.5 text-xs"
                        />
                        <!-- ตัวกรองหมวดหมู่ไฟล์: เกณฑ์การแยกหมวดหมู่อยู่ที่ app/Http/Controllers/FileManagerController.php (resolveFileCategory) และ app/Enums/FileCategory.php -->
                        <select
                            bind:value={categoryInput}
                            onchange={applySearch}
                            class="input-trust w-40 py-1.5 text-xs"
                        >
                            <option value="">ทุกหมวดหมู่</option>
                            <option value="document">เอกสาร</option>
                            <option value="image">รูปภาพ</option>
                            <option value="video">วิดีโอ</option>
                            <option value="other">อื่นๆ</option>
                        </select>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-xs font-semibold cursor-pointer transition shadow-2xs"
                        >
                            ค้นหา
                        </button>
                    </form>

                    <!-- View Switcher -->
                    <div class="inline-flex rounded-xl border border-slate-200 p-1 bg-slate-100/80 gap-0.5">
                        <button
                            type="button"
                            onclick={() => (viewMode = 'list')}
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {viewMode === 'list' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                        >
                            รายการ
                        </button>
                        <button
                            type="button"
                            onclick={() => (viewMode = 'grid')}
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {viewMode === 'grid' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                        >
                            ตาราง
                        </button>
                    </div>
                </div>

                <!-- Nodes Display -->
                {#if nodes.length === 0}
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center text-slate-500 text-xs shadow-xs">
                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                        </svg>
                        <div class="font-semibold text-sm text-slate-900">ยังไม่มีไฟล์หรือโฟลเดอร์ในตำแหน่งนี้</div>
                        <div class="text-[11px] text-slate-500 mt-1">เริ่มต้นด้วยการกดปุ่ม "โฟลเดอร์ใหม่" หรือ "อัปโหลดไฟล์" ด้านบน</div>
                    </div>
                {:else if viewMode === 'list'}
                    <!-- List Table View -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 overflow-hidden shadow-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs min-w-[640px]">
                            <thead>
                                <tr class="bg-[#F7F9FA] border-b border-[#DDDDDD] text-[#232F3E] text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-2.5 px-4">ชื่อ</th>
                                    <th class="py-2.5 px-4">หมวดหมู่</th>
                                    <th class="py-2.5 px-4">ขนาด</th>
                                    <th class="py-2.5 px-4">เจ้าของ</th>
                                    <th class="py-2.5 px-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EEEEEE]">
                                {#each nodes as node (node.id)}
                                    <tr class="hover:bg-[#FBFBFC] transition-colors">
                                        <td class="py-2.5 px-4">
                                            {#if node.type === 'folder'}
                                                <Link
                                                    href={`/files?root=${currentRoot}&folder=${node.uuid}`}
                                                    class="font-bold {node.is_dropzone ? 'text-[#008000]' : 'text-[#131921]'} hover:text-[#00A8E1] hover:underline flex items-center gap-2"
                                                >
                                                    {#if node.is_dropzone}
                                                        <svg class="w-4 h-4 text-[#008000] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                            <polyline points="17 8 12 3 7 8" />
                                                            <line x1="12" y1="3" x2="12" y2="15" />
                                                        </svg>
                                                    {:else}
                                                        <svg class="w-4 h-4 text-[#FF9900] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                                        </svg>
                                                    {/if}
                                                    <span>{node.name}</span>
                                                </Link>
                                            {:else}
                                                <button
                                                    type="button"
                                                    onclick={() => openPreview(node)}
                                                    class="font-medium text-[#131921] hover:text-[#00A8E1] hover:underline flex items-center gap-2 text-left cursor-pointer transition group"
                                                    title="คลิกเพื่อเปิดดูไฟล์"
                                                >
                                                    <svg class="w-4 h-4 text-[#00A8E1] shrink-0 group-hover:scale-110 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <path d="M14 2v6h6" />
                                                        <line x1="16" y1="13" x2="8" y2="13" />
                                                        <line x1="16" y1="17" x2="8" y2="17" />
                                                        <line x1="10" y1="9" x2="8" y2="9" />
                                                    </svg>
                                                    <span class="font-bold">{node.name}</span>
                                                </button>
                                            {/if}
                                        </td>
                                        <td class="py-2.5 px-4">
                                            {#if node.type === 'folder'}
                                                {#if node.is_dropzone}
                                                    <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold bg-[#EBF7EE] text-[#008000] border border-[#008000]/30">
                                                        กล่องส่งงานส่วนกลาง
                                                    </span>
                                                {:else if node.root_type === 'shared'}
                                                    <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold bg-[#F3F4F6] text-[#232F3E] border border-[#DDDDDD]">
                                                        โฟลเดอร์ทางการ
                                                    </span>
                                                {:else}
                                                    <span class="text-[11px] text-[#777777]">โฟลเดอร์</span>
                                                {/if}
                                            {:else}
                                                {@const badge = categoryBadge(node.category)}
                                                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold {badge.cls}">
                                                    {badge.label}
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="py-2.5 px-4 text-[#555555] font-mono text-[11px]">
                                            {node.type === 'folder' ? '—' : formatBytes(node.size_bytes)}
                                        </td>
                                        <td class="py-2.5 px-4 text-[#555555] text-[11px]">
                                            {node.owner_name ?? '—'}
                                        </td>
                                        <td class="py-2.5 px-4 text-right">
                                            <div class="inline-flex items-center gap-1.5">
                                                {#if node.type === 'file'}
                                                    <button
                                                        type="button"
                                                        onclick={() => openPreview(node)}
                                                        class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#FF9900]/10 hover:bg-[#FF9900]/20 text-[#A86400] border border-[#FF9900]/30 transition inline-flex items-center gap-1 cursor-pointer"
                                                        title="เปิดดูไฟล์ในเบราว์เซอร์"
                                                    >
                                                        <svg class="w-3.5 h-3.5 text-[#FF9900]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                                            <circle cx="12" cy="12" r="3" />
                                                        </svg>
                                                        <span>เปิดดู</span>
                                                    </button>
                                                    <a
                                                        href={`/files/${node.uuid}/download`}
                                                        class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#EBF8FC] hover:bg-[#D6F1FA] text-[#007099] border border-[#00A8E1]/30 transition inline-flex items-center gap-1"
                                                    >
                                                        ดาวน์โหลด
                                                    </a>
                                                {/if}
                                                {#if node.can_share}
                                                    <button
                                                        type="button"
                                                        onclick={() => openShareModal(node)}
                                                        class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#FFF9F0] hover:bg-[#FFEECB] text-[#A86400] border border-[#FF9900]/30 transition cursor-pointer"
                                                    >
                                                        แชร์
                                                    </button>
                                                {/if}
                                                {#if node.can_edit || node.can_delete}
                                                    <button
                                                        type="button"
                                                        onclick={(e) => toggleNodeMenu(node, e)}
                                                        aria-label="ตัวเลือกเพิ่มเติม"
                                                        aria-haspopup="menu"
                                                        aria-expanded={openMenuNode?.id === node.id}
                                                        title="ตัวเลือกเพิ่มเติม"
                                                        class="p-1.5 rounded-[3px] text-[#555555] hover:text-[#131921] hover:bg-[#F3F4F6] border border-transparent hover:border-[#DDDDDD] transition cursor-pointer {openMenuNode?.id === node.id ? 'bg-[#F3F4F6] border-[#DDDDDD] text-[#131921]' : ''}"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                            <circle cx="5" cy="12" r="1.75" />
                                                            <circle cx="12" cy="12" r="1.75" />
                                                            <circle cx="19" cy="12" r="1.75" />
                                                        </svg>
                                                    </button>
                                                {/if}
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                        </div>
                    </div>
                {:else}
                    <!-- Grid Cards View -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        {#each nodes as node (node.id)}
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-col justify-between gap-3 shadow-xs hover:shadow-md hover:-translate-y-0.5 hover:border-sky-200 transition-all duration-200">
                                <div>
                                    {#if node.type === 'folder'}
                                        <Link
                                            href={`/files?root=${currentRoot}&folder=${node.uuid}`}
                                            class="font-bold text-xs {node.is_dropzone ? 'text-[#008000]' : 'text-[#131921]'} hover:text-[#00A8E1] hover:underline flex items-center gap-2 truncate"
                                        >
                                            {#if node.is_dropzone}
                                                <svg class="w-4 h-4 text-[#008000] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                    <polyline points="17 8 12 3 7 8" />
                                                    <line x1="12" y1="3" x2="12" y2="15" />
                                                </svg>
                                            {:else}
                                                <svg class="w-4 h-4 text-[#FF9900] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                                </svg>
                                            {/if}
                                            <span class="truncate">{node.name}</span>
                                        </Link>
                                        <div class="mt-1 text-[11px] text-[#777777]">
                                            {node.is_dropzone ? 'กล่องส่งงานส่วนกลาง' : (node.root_type === 'shared' ? 'โฟลเดอร์ทางการ' : 'โฟลเดอร์')} · {node.owner_name ?? '—'}
                                        </div>
                                    {:else}
                                        <button
                                            type="button"
                                            onclick={() => openPreview(node)}
                                            class="font-bold text-xs text-[#131921] hover:text-[#00A8E1] hover:underline flex items-center gap-2 truncate text-left cursor-pointer transition w-full"
                                            title="คลิกเพื่อเปิดดูไฟล์"
                                        >
                                            <svg class="w-4 h-4 text-[#00A8E1] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <path d="M14 2v6h6" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                                <line x1="10" y1="9" x2="8" y2="9" />
                                            </svg>
                                            <span class="truncate">{node.name}</span>
                                        </button>
                                        <div class="mt-1 text-[11px] text-[#555555] font-mono">
                                            {formatBytes(node.size_bytes)} · {node.owner_name ?? '—'}
                                        </div>
                                    {/if}
                                </div>

                                <div class="flex items-center justify-between border-t border-[#EEEEEE] pt-2 mt-1">
                                    {#if node.type === 'file'}
                                        <div class="inline-flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                onclick={() => openPreview(node)}
                                                class="text-[11px] font-bold text-[#A86400] hover:underline cursor-pointer"
                                            >
                                                เปิดดู
                                            </button>
                                            <span class="text-[#DDDDDD]">·</span>
                                            <a
                                                href={`/files/${node.uuid}/download`}
                                                class="text-[11px] font-bold text-[#007099] hover:underline"
                                            >
                                                ดาวน์โหลด
                                            </a>
                                        </div>
                                    {:else}
                                        <span></span>
                                    {/if}

                                    <div class="flex items-center gap-1.5">
                                        {#if node.can_share}
                                            <button
                                                type="button"
                                                onclick={() => openShareModal(node)}
                                                class="text-[11px] font-bold text-[#A86400] hover:underline cursor-pointer"
                                            >
                                                แชร์
                                            </button>
                                        {/if}
                                        {#if node.can_edit || node.can_delete}
                                            <button
                                                type="button"
                                                onclick={(e) => toggleNodeMenu(node, e)}
                                                aria-label="ตัวเลือกเพิ่มเติม"
                                                aria-haspopup="menu"
                                                aria-expanded={openMenuNode?.id === node.id}
                                                title="ตัวเลือกเพิ่มเติม"
                                                class="p-1 rounded-md text-[#555555] hover:text-[#131921] hover:bg-[#F3F4F6] transition cursor-pointer {openMenuNode?.id === node.id ? 'bg-[#F3F4F6] text-[#131921]' : ''}"
                                            >
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                    <circle cx="5" cy="12" r="1.75" />
                                                    <circle cx="12" cy="12" r="1.75" />
                                                    <circle cx="19" cy="12" r="1.75" />
                                                </svg>
                                            </button>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- Floating More Actions Menu (...) -->
    {#if openMenuNode}
        <div
            use:portal
            role="menu"
            tabindex="-1"
            onclick={(e) => e.stopPropagation()}
            onkeydown={handleWindowKeydown}
            style="top: {menuPos.top}px; right: {menuPos.right}px;"
            class="fixed z-50 w-40 bg-white rounded-xl border border-slate-200/90 shadow-xl shadow-slate-900/15 py-1.5 text-xs animate-fade-up"
        >
            {#if openMenuNode.can_edit}
                <button
                    type="button"
                    role="menuitem"
                    onclick={() => {
                        const target = openMenuNode;
                        openMenuNode = null;
                        openRenameModal(target);
                    }}
                    class="w-full px-3.5 py-2 text-left text-slate-700 hover:bg-slate-50 hover:text-slate-950 font-medium flex items-center gap-2.5 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                        <path d="m15 5 4 4" />
                    </svg>
                    <span>เปลี่ยนชื่อ</span>
                </button>
                <button
                    type="button"
                    role="menuitem"
                    onclick={() => {
                        const target = openMenuNode;
                        openMenuNode = null;
                        openMoveModal(target);
                    }}
                    class="w-full px-3.5 py-2 text-left text-slate-700 hover:bg-slate-50 hover:text-slate-950 font-medium flex items-center gap-2.5 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                        <path d="M12 10v6" />
                        <path d="m15 13-3 3-3-3" />
                    </svg>
                    <span>ย้ายตำแหน่ง</span>
                </button>
            {/if}
            {#if openMenuNode.can_edit && openMenuNode.can_delete}
                <div class="my-1 border-t border-slate-100"></div>
            {/if}
            {#if openMenuNode.can_delete}
                <button
                    type="button"
                    role="menuitem"
                    onclick={() => {
                        const target = openMenuNode;
                        openMenuNode = null;
                        moveToTrash(target);
                    }}
                    class="w-full px-3.5 py-2 text-left text-rose-600 hover:bg-rose-50 hover:text-rose-700 font-semibold flex items-center gap-2.5 transition cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                    </svg>
                    <span>ลบ</span>
                </button>
            {/if}
        </div>
    {/if}

    <!-- Modal: สร้างโฟลเดอร์ -->
    {#if showFolderModal}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-sm w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-3">สร้างโฟลเดอร์ใหม่</h3>
                <form onsubmit={submitCreateFolder} class="flex flex-col gap-3.5">
                    <div>
                        <label for="folder_name_input" class="block text-[11px] font-bold text-[#232F3E] mb-1">ชื่อโฟลเดอร์</label>
                        <input
                            id="folder_name_input"
                            type="text"
                            bind:value={folderForm.name}
                            required
                            placeholder="ระบุชื่อโฟลเดอร์..."
                            class="input-trust text-xs"
                        />
                        {#if folderForm.errors.name}
                            <p class="mt-1 text-xs text-[#E53E3E]">{folderForm.errors.name}</p>
                        {/if}
                    </div>
                    {#if currentRoot === 'shared' && !currentFolder}
                        <label class="flex items-start gap-2.5 p-2.5 rounded-[3px] border border-[#DDDDDD] bg-[#F7F9FA] cursor-pointer">
                            <input
                                type="checkbox"
                                bind:checked={folderForm.is_dropzone}
                                class="mt-0.5 rounded-[2px] border-[#DDDDDD] text-[#008000] focus:ring-[#00A8E1]"
                            />
                            <div class="text-[11px]">
                                <div class="font-bold text-[#131921]">ตั้งเป็นกล่องแลกเปลี่ยน</div>
                                <div class="text-[#666666] mt-0.5">
                                    อนุญาตให้ผู้ใช้ทุกคนอัปโหลดไฟล์ส่งงานได้ (หากไม่เลือก จะเป็นโฟลเดอร์ทางการที่จัดการได้เฉพาะผู้ดูแลระบบและหัวหน้าแผนก)
                                </div>
                            </div>
                        </label>
                    {/if}
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (showFolderModal = false)} class="btn-secondary">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={folderForm.processing} class="btn-primary">
                            สร้างโฟลเดอร์
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: เปลี่ยนชื่อ -->
    {#if renamingNode}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-sm w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-3">เปลี่ยนชื่อ</h3>
                <form onsubmit={submitRename} class="flex flex-col gap-3.5">
                    <div>
                        <label for="rename_input" class="block text-[11px] font-bold text-[#232F3E] mb-1">ชื่อใหม่</label>
                        <input
                            id="rename_input"
                            type="text"
                            bind:value={renameForm.name}
                            required
                            class="input-trust text-xs"
                        />
                        {#if renameForm.errors.name}
                            <p class="mt-1 text-xs text-[#E53E3E]">{renameForm.errors.name}</p>
                        {/if}
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (renamingNode = null)} class="btn-secondary">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={renameForm.processing} class="btn-primary">
                            บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: ย้ายตำแหน่ง -->
    {#if movingNode}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-sm w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-1">ย้ายตำแหน่ง</h3>
                <p class="text-xs text-[#666666] mb-3 truncate">{movingNode.name}</p>
                <form onsubmit={submitMove} class="flex flex-col gap-3.5">
                    <div>
                        <label for="move_target" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกโฟลเดอร์ปลายทาง</label>
                        <select
                            id="move_target"
                            bind:value={moveForm.target_parent_id}
                            class="input-trust text-xs"
                        >
                            <option value="">/ (ระดับบนสุด)</option>
                            {#each availableMoveFolders as f (f.id)}
                                <option value={String(f.id)}>{'—'.repeat(f.depth)} {f.name}</option>
                            {/each}
                        </select>
                        {#if moveForm.errors.target_parent_id}
                            <p class="mt-1 text-xs text-[#E53E3E]">{moveForm.errors.target_parent_id}</p>
                        {/if}
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (movingNode = null)} class="btn-secondary">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={moveForm.processing} class="btn-primary">
                            ย้าย
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: แชร์ไฟล์หรือโฟลเดอร์ -->
    {#if sharingNode}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-1">แชร์ไฟล์ / โฟลเดอร์</h3>
                <p class="text-xs text-[#666666] mb-3 truncate">{sharingNode.name}</p>
                <form onsubmit={submitShareNode} class="flex flex-col gap-3.5">
                    <!-- Switch User vs Dept vs URL Link Share -->
                    <div class="inline-flex flex-wrap rounded-[3px] border border-[#DDDDDD] p-0.5 bg-[#F7F9FA] w-fit gap-0.5">
                        <button
                            type="button"
                            onclick={() => (shareTargetType = 'user')}
                            class="px-3 py-1 rounded-[2px] text-xs font-bold transition cursor-pointer {shareTargetType === 'user' ? 'bg-[#232F3E] text-white shadow-xs' : 'text-[#555555] hover:text-[#131921]'}"
                        >
                            แชร์ให้รายคน
                        </button>
                        <button
                            type="button"
                            onclick={() => (shareTargetType = 'department')}
                            class="px-3 py-1 rounded-[2px] text-xs font-bold transition cursor-pointer {shareTargetType === 'department' ? 'bg-[#232F3E] text-white shadow-xs' : 'text-[#555555] hover:text-[#131921]'}"
                        >
                            แชร์ให้ทั้งแผนก
                        </button>
                        <button
                            type="button"
                            onclick={() => (shareTargetType = 'link')}
                            class="px-3 py-1 rounded-[2px] text-xs font-bold transition cursor-pointer {shareTargetType === 'link' ? 'bg-[#232F3E] text-white shadow-xs' : 'text-[#555555] hover:text-[#131921]'}"
                        >
                            แชร์เป็นลิงก์ (URL)
                        </button>
                    </div>

                    {#if shareTargetType === 'user'}
                        <div>
                            <label for="file_share_user" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกผู้รับ</label>
                            <select
                                id="file_share_user"
                                bind:value={shareForm.shared_with_user_id}
                                required
                                class="input-trust text-xs"
                            >
                                <option value="">-- เลือกผู้ใช้ --</option>
                                {#each shareRecipients as r (r.id)}
                                    <option value={r.id}>{r.name} ({r.email})</option>
                                {/each}
                            </select>
                            {#if shareForm.errors.shared_with_user_id}
                                <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.shared_with_user_id}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="file_share_perm" class="block text-[11px] font-bold text-[#232F3E] mb-1">สิทธิ์การเข้าถึง</label>
                            <select
                                id="file_share_perm"
                                bind:value={shareForm.permission}
                                class="input-trust text-xs"
                            >
                                <option value="view">ดูอย่างเดียว</option>
                                <option value="edit">ดูและแก้ไข</option>
                            </select>
                        </div>

                        <div>
                            <label for="file_share_exp" class="block text-[11px] font-bold text-[#232F3E] mb-1">วันหมดอายุ (ไม่บังคับ)</label>
                            <input
                                id="file_share_exp"
                                type="datetime-local"
                                bind:value={shareForm.expires_at}
                                class="input-trust text-xs"
                            />
                            {#if shareForm.errors.expires_at}
                                <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.expires_at}</p>
                            {/if}
                        </div>
                    {:else if shareTargetType === 'department'}
                        <div>
                            <label for="file_share_dept" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกแผนกที่ต้องการแชร์ให้</label>
                            <select
                                id="file_share_dept"
                                bind:value={shareForm.shared_with_department_id}
                                required
                                class="input-trust text-xs"
                            >
                                <option value="">-- เลือกแผนก --</option>
                                {#each shareDepartments as d (d.id)}
                                    <option value={d.id}>แผนก {d.name}</option>
                                {/each}
                            </select>
                            {#if shareForm.errors.shared_with_department_id}
                                <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.shared_with_department_id}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="file_share_perm_dept" class="block text-[11px] font-bold text-[#232F3E] mb-1">สิทธิ์การเข้าถึง</label>
                            <select
                                id="file_share_perm_dept"
                                bind:value={shareForm.permission}
                                class="input-trust text-xs"
                            >
                                <option value="view">ดูอย่างเดียว</option>
                                <option value="edit">ดูและแก้ไข</option>
                            </select>
                        </div>

                        <div>
                            <label for="file_share_exp_dept" class="block text-[11px] font-bold text-[#232F3E] mb-1">วันหมดอายุ (ไม่บังคับ)</label>
                            <input
                                id="file_share_exp_dept"
                                type="datetime-local"
                                bind:value={shareForm.expires_at}
                                class="input-trust text-xs"
                            />
                            {#if shareForm.errors.expires_at}
                                <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.expires_at}</p>
                            {/if}
                        </div>
                    {:else}
                        <!-- URL Share Link Tab -->
                        {#if generatedShareLink}
                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 flex flex-col gap-2.5">
                                <div class="text-xs font-bold text-emerald-800">สร้างลิงก์แชร์สำเร็จแล้ว!</div>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        readonly
                                        value={generatedShareLink.url}
                                        class="input-trust text-xs font-mono bg-white flex-1"
                                        onclick={(e) => e.currentTarget.select()}
                                    />
                                    <button
                                        type="button"
                                        onclick={() => copyShareUrl(generatedShareLink.url)}
                                        class="px-3 py-2 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition cursor-pointer shrink-0"
                                    >
                                        {copiedShareUrl ? 'คัดลอกแล้ว!' : 'คัดลอกลิงก์'}
                                    </button>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-emerald-700">
                                    <span>ผู้ที่มีลิงก์นี้สามารถเปิดดูและดาวน์โหลดได้</span>
                                    <a
                                        href={generatedShareLink.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-semibold underline hover:text-emerald-900"
                                    >
                                        เปิดลิงก์ &rarr;
                                    </a>
                                </div>
                            </div>
                        {:else}
                            <p class="text-[11px] text-slate-500">
                                สร้าง URL สำหรับส่งให้ผู้อื่นเปิดดูหรือดาวน์โหลดได้โดยไม่ต้องเข้าสู่ระบบ (จัดการและยกเลิกลิงก์ได้ที่เมนู "การแชร์ไฟล์")
                            </p>

                            <div>
                                <label for="file_share_link_exp" class="block text-[11px] font-bold text-[#232F3E] mb-1">วันหมดอายุ (ค่าเริ่มต้น 7 วัน หากไม่ระบุ)</label>
                                <input
                                    id="file_share_link_exp"
                                    type="datetime-local"
                                    bind:value={shareForm.expires_at}
                                    class="input-trust text-xs"
                                />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="file_share_link_pass" class="block text-[11px] font-bold text-[#232F3E] mb-1">รหัสผ่านป้องกัน (ไม่บังคับ)</label>
                                    <input
                                        id="file_share_link_pass"
                                        type="password"
                                        bind:value={shareLinkPassword}
                                        placeholder="อย่างน้อย 4 ตัวอักษร"
                                        class="input-trust text-xs"
                                    />
                                </div>
                                <div>
                                    <label for="file_share_link_max" class="block text-[11px] font-bold text-[#232F3E] mb-1">จำกัดจำนวนดาวน์โหลด (ไม่บังคับ)</label>
                                    <input
                                        id="file_share_link_max"
                                        type="number"
                                        min="1"
                                        max="10000"
                                        bind:value={shareLinkMaxDownloads}
                                        placeholder="ไม่จำกัด"
                                        class="input-trust text-xs"
                                    />
                                </div>
                            </div>

                            {#if shareLinkError}
                                <p class="text-xs text-[#E53E3E] font-medium">{shareLinkError}</p>
                            {/if}
                        {/if}
                    {/if}

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (sharingNode = null)} class="btn-secondary">
                            {generatedShareLink ? 'ปิดหน้าต่าง' : 'ยกเลิก'}
                        </button>
                        {#if shareTargetType === 'link'}
                            {#if !generatedShareLink}
                                <button type="submit" disabled={shareLinkCreating} class="btn-primary">
                                    {shareLinkCreating ? 'กำลังสร้างลิงก์...' : 'สร้างลิงก์แชร์'}
                                </button>
                            {/if}
                        {:else}
                            <button type="submit" disabled={shareForm.processing} class="btn-primary">
                                ยืนยันการแชร์
                            </button>
                        {/if}
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- File Preview Modal -->
    <FilePreviewModal file={previewingFile} onclose={() => (previewingFile = null)} />

    <!-- Confirm Delete Modal -->
    <ConfirmDeleteModal
        open={Boolean(deletingNode)}
        title={deletingNode?.type === 'folder' ? 'ยืนยันการลบโฟลเดอร์' : 'ยืนยันการลบไฟล์'}
        itemName={deletingNode?.name ?? ''}
        itemType={deletingNode?.type ?? 'file'}
        description={deletingNode?.type === 'folder'
            ? 'โฟลเดอร์นี้และไฟล์ทั้งหมดภายในจะถูกย้ายไปยังถังขยะ สามารถกู้คืนได้ภายใน 30 วันก่อนถูกลบถาวร'
            : 'ไฟล์นี้จะถูกย้ายไปยังถังขยะ สามารถกู้คืนได้ภายใน 30 วันก่อนถูกลบถาวร'}
        confirmLabel="ยืนยันย้ายลงถังขยะ"
        processing={isDeletingNode}
        onconfirm={confirmMoveToTrash}
        oncancel={() => (deletingNode = null)}
    />
</AppLayout>
