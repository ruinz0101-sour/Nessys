<script>
import { Link, router, useForm } from '@inertiajs/svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';
import FilePreviewModal from '../../Components/FilePreviewModal.svelte';

let {
    sharedWithMe = [],
    sharedByMe = [],
    shareableNodes = [],
    recipients = [],
    departments = [],
} = $props();

let activeTab = $state('with_me'); // 'with_me' | 'by_me'
let showShareModal = $state(false);
let shareTargetType = $state('user'); // 'user' | 'department'
let previewingFile = $state(null);

function openPreview(item) {
    if (!item || item.node_type === 'folder' || !item.node_uuid) {
        return;
    }
    previewingFile = {
        uuid: item.node_uuid,
        name: item.node_name,
        size_bytes: item.size_bytes,
        mime_type: item.mime_type,
        extension: item.extension,
        shared_by_name: item.shared_by_name,
    };
}

const shareForm = useForm({
    node_id: '',
    shared_with_user_id: '',
    shared_with_department_id: '',
    permission: 'view',
    expires_at: '',
});

function openShareModal() {
    shareForm.reset();
    shareForm.clearErrors();
    shareTargetType = 'user';
    showShareModal = true;
}

function submitShare(e) {
    e.preventDefault();
    shareForm.transform((data) => ({
        node_id: Number(data.node_id),
        shared_with_user_id: shareTargetType === 'user' && data.shared_with_user_id ? Number(data.shared_with_user_id) : null,
        shared_with_department_id: shareTargetType === 'department' && data.shared_with_department_id ? Number(data.shared_with_department_id) : null,
        permission: data.permission,
        expires_at: data.expires_at || null,
    })).post('/shares', {
        preserveScroll: true,
        onSuccess: () => {
            showShareModal = false;
            shareForm.reset();
            activeTab = 'by_me';
        },
    });
}

function revokeShare(share) {
    router.patch(`/shares/${share.id}/revoke`, {}, {
        preserveScroll: true,
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

function formatDate(iso) {
    if (!iso) {
        return 'ไม่หมดอายุ';
    }
    return new Date(iso).toLocaleString('th-TH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<AppLayout title="การแชร์ไฟล์และโฟลเดอร์">
    <div class="flex flex-col gap-5">
        <!-- Tabs & Action Bar -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div class="inline-flex rounded-xl border border-slate-200/80 p-1 bg-slate-100/80 gap-1">
                <button
                    type="button"
                    onclick={() => (activeTab = 'with_me')}
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {activeTab === 'with_me' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    แชร์ให้ฉัน ({sharedWithMe.length})
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'by_me')}
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {activeTab === 'by_me' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    ฉันแชร์ไป ({sharedByMe.length})
                </button>
            </div>

            <button
                type="button"
                onclick={openShareModal}
                class="btn-primary"
            >
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3" />
                    <circle cx="6" cy="12" r="3" />
                    <circle cx="18" cy="19" r="3" />
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
                </svg>
                <span>+ แชร์ไฟล์ / โฟลเดอร์</span>
            </button>
        </div>

        {#if activeTab === 'with_me'}
            <!-- Shared With Me Tab -->
            {#if sharedWithMe.length === 0}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] p-12 text-center text-[#777777] text-xs shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="font-bold text-sm text-[#131921]">ยังไม่มีไฟล์หรือโฟลเดอร์ที่แชร์มาให้คุณ</div>
                    <div class="text-[11px] text-[#777777] mt-1">เมื่อมีเพื่อนร่วมงานแชร์ไฟล์ให้คุณ รายการจะปรากฏที่นี่</div>
                </div>
            {:else}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] overflow-hidden shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[640px]">
                            <thead>
                                <tr class="bg-[#F7F9FA] border-b border-[#DDDDDD] text-[#232F3E] text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-2.5 px-4">ไฟล์ / โฟลเดอร์</th>
                                    <th class="py-2.5 px-4">ผู้แชร์</th>
                                    <th class="py-2.5 px-4">สิทธิ์ที่ได้รับ</th>
                                    <th class="py-2.5 px-4">วันหมดอายุ</th>
                                    <th class="py-2.5 px-4 text-right">การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EEEEEE]">
                                {#each sharedWithMe as item (item.id)}
                                    <tr class="hover:bg-[#FBFBFC] transition-colors">
                                        <td class="py-2.5 px-4">
                                            {#if item.node_type === 'folder'}
                                                <Link
                                                    href={`/files?root=${item.root_type}&folder=${item.node_uuid}`}
                                                    class="font-bold text-[#131921] hover:text-[#00A8E1] hover:underline flex items-center gap-2"
                                                >
                                                    <svg class="w-4 h-4 text-[#FF9900] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                                    </svg>
                                                    <span>{item.node_name}</span>
                                                </Link>
                                            {:else}
                                                <button
                                                    type="button"
                                                    onclick={() => openPreview(item)}
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
                                                    <span class="font-bold">{item.node_name}</span>
                                                    <span class="text-[11px] text-[#777777] font-mono">({formatBytes(item.size_bytes)})</span>
                                                    <span class="text-[10px] text-[#00A8E1] opacity-0 group-hover:opacity-100 transition-opacity font-normal">คลิกเพื่อเปิดดู</span>
                                                </button>
                                            {/if}
                                        </td>
                                        <td class="py-2.5 px-4">
                                            <div class="text-xs font-bold text-[#131921]">{item.shared_by_name}</div>
                                            <div class="text-[11px] text-[#666666]">{item.shared_by_email}</div>
                                        </td>
                                        <td class="py-2.5 px-4">
                                            <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold {item.permission === 'edit' ? 'bg-[#FFF9F0] text-[#A86400] border border-[#FF9900]/30' : 'bg-[#EBF8FC] text-[#007099] border border-[#00A8E1]/30'}">
                                                {item.permission === 'edit' ? 'ดูและแก้ไข' : 'ดูอย่างเดียว'}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 text-[#555555] font-mono text-[11px]">
                                            {formatDate(item.expires_at)}
                                        </td>
                                        <td class="py-2.5 px-4 text-right">
                                            {#if item.node_type === 'folder'}
                                                <Link
                                                    href={`/files?root=${item.root_type}&folder=${item.node_uuid}`}
                                                    class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#EBF8FC] hover:bg-[#D6F1FA] text-[#007099] border border-[#00A8E1]/30 transition"
                                                >
                                                    เปิดโฟลเดอร์
                                                </Link>
                                            {:else if item.node_uuid}
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        onclick={() => openPreview(item)}
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
                                                        href={`/files/${item.node_uuid}/download`}
                                                        class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#EBF8FC] hover:bg-[#D6F1FA] text-[#007099] border border-[#00A8E1]/30 transition inline-flex items-center gap-1"
                                                        title="ดาวน์โหลดลงเครื่อง"
                                                    >
                                                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                            <polyline points="7 10 12 15 17 10" />
                                                            <line x1="12" y1="15" x2="12" y2="3" />
                                                        </svg>
                                                        <span>ดาวน์โหลด</span>
                                                    </a>
                                                </div>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            {/if}
        {:else}
            <!-- Shared By Me Tab -->
            {#if sharedByMe.length === 0}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] p-12 text-center text-[#777777] text-xs shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="font-bold text-sm text-[#131921]">คุณยังไม่ได้แชร์ไฟล์หรือโฟลเดอร์ให้ผู้อื่น</div>
                    <div class="text-[11px] text-[#777777] mt-1">กดปุ่ม "+ แชร์ไฟล์ / โฟลเดอร์" ด้านบนเพื่อเริ่มแบ่งปันเอกสาร</div>
                </div>
            {:else}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] overflow-hidden shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[640px]">
                            <thead>
                                <tr class="bg-[#F7F9FA] border-b border-[#DDDDDD] text-[#232F3E] text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-2.5 px-4">ไฟล์ / โฟลเดอร์</th>
                                    <th class="py-2.5 px-4">แชร์ให้กับ</th>
                                    <th class="py-2.5 px-4">สิทธิ์</th>
                                    <th class="py-2.5 px-4">วันหมดอายุ</th>
                                    <th class="py-2.5 px-4">สถานะ</th>
                                    <th class="py-2.5 px-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EEEEEE]">
                                {#each sharedByMe as item (item.id)}
                                    <tr class="hover:bg-[#FBFBFC] transition-colors">
                                        <td class="py-2.5 px-4 font-medium text-[#131921]">
                                            {#if item.node_type === 'folder'}
                                                <div class="flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-[#FF9900] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                                    </svg>
                                                    <span>{item.node_name ?? '(ลบแล้ว)'}</span>
                                                </div>
                                            {:else if item.node_uuid}
                                                <button
                                                    type="button"
                                                    onclick={() => openPreview(item)}
                                                    class="hover:text-[#00A8E1] hover:underline flex items-center gap-2 text-left cursor-pointer transition group"
                                                    title="คลิกเพื่อเปิดดูไฟล์"
                                                >
                                                    <svg class="w-4 h-4 text-[#00A8E1] shrink-0 group-hover:scale-110 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <path d="M14 2v6h6" />
                                                        <line x1="16" y1="13" x2="8" y2="13" />
                                                        <line x1="16" y1="17" x2="8" y2="17" />
                                                        <line x1="10" y1="9" x2="8" y2="9" />
                                                    </svg>
                                                    <span class="font-bold">{item.node_name ?? '(ลบแล้ว)'}</span>
                                                </button>
                                            {:else}
                                                <span class="text-[#888888]">{item.node_name ?? '(ลบแล้ว)'}</span>
                                            {/if}
                                        </td>
                                        <td class="py-2.5 px-4">
                                            {#if item.shared_with_user_name}
                                                <div class="text-xs font-bold text-[#131921]">{item.shared_with_user_name}</div>
                                                <div class="text-[11px] text-[#666666]">{item.shared_with_user_email}</div>
                                            {:else if item.shared_with_department_name}
                                                <div class="text-xs font-bold text-[#007099]">แผนก {item.shared_with_department_name}</div>
                                            {/if}
                                        </td>
                                        <td class="py-2.5 px-4">
                                            <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold {item.permission === 'edit' ? 'bg-[#FFF9F0] text-[#A86400] border border-[#FF9900]/30' : 'bg-[#EBF8FC] text-[#007099] border border-[#00A8E1]/30'}">
                                                {item.permission === 'edit' ? 'ดูและแก้ไข' : 'ดูอย่างเดียว'}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 text-[#555555] font-mono text-[11px]">
                                            {formatDate(item.expires_at)}
                                        </td>
                                        <td class="py-2.5 px-4">
                                            {#if item.is_revoked}
                                                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold bg-[#FDF2F2] text-[#9B1C1C] border border-[#E53E3E]/30">
                                                    ยกเลิกแล้ว
                                                </span>
                                            {:else if item.is_expired}
                                                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold bg-[#F3F4F6] text-[#555555] border border-[#DDDDDD]">
                                                    หมดอายุ
                                                </span>
                                            {:else}
                                                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold bg-[#EBF7EE] text-[#008000] border border-[#008000]/30">
                                                    กำลังแชร์
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="py-2.5 px-4 text-right">
                                            {#if item.is_active}
                                                <button
                                                    type="button"
                                                    onclick={() => revokeShare(item)}
                                                    class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#B91C1C] border border-[#E53E3E]/30 transition cursor-pointer"
                                                >
                                                    เพิกถอนสิทธิ์
                                                </button>
                                            {:else}
                                                <span class="text-[11px] text-[#888888]">—</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            {/if}
        {/if}
    </div>

    <!-- Modal: สร้างการแชร์ใหม่ -->
    {#if showShareModal}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-3">แชร์ไฟล์หรือโฟลเดอร์</h3>
                <form onsubmit={submitShare} class="flex flex-col gap-3.5">
                    <div>
                        <label for="share_node_select" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกไฟล์หรือโฟลเดอร์ของคุณ</label>
                        <select
                            id="share_node_select"
                            bind:value={shareForm.node_id}
                            required
                            class="input-trust text-xs"
                        >
                            <option value="">-- เลือกรายการที่จะแชร์ --</option>
                            {#each shareableNodes as n (n.id)}
                                <option value={n.id}>[{n.type === 'folder' ? 'โฟลเดอร์' : 'ไฟล์'}] {n.name}</option>
                            {/each}
                        </select>
                        {#if shareForm.errors.node_id}
                            <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.node_id}</p>
                        {/if}
                    </div>

                    <!-- Toggle Target: User vs Department -->
                    <div class="inline-flex rounded-[3px] border border-[#DDDDDD] p-0.5 bg-[#F7F9FA] w-fit">
                        <button
                            type="button"
                            onclick={() => (shareTargetType = 'user')}
                            class="px-3 py-1 rounded-[2px] text-xs font-bold transition cursor-pointer {shareTargetType === 'user' ? 'bg-[#232F3E] text-white shadow-xs' : 'text-[#555555] hover:text-[#131921]'}"
                        >
                            แชร์ให้รายบุคคล
                        </button>
                        <button
                            type="button"
                            onclick={() => (shareTargetType = 'department')}
                            class="px-3 py-1 rounded-[2px] text-xs font-bold transition cursor-pointer {shareTargetType === 'department' ? 'bg-[#232F3E] text-white shadow-xs' : 'text-[#555555] hover:text-[#131921]'}"
                        >
                            แชร์ให้ทั้งแผนก
                        </button>
                    </div>

                    {#if shareTargetType === 'user'}
                        <div>
                            <label for="share_user_select" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกผู้ใช้เป้าหมาย</label>
                            <select
                                id="share_user_select"
                                bind:value={shareForm.shared_with_user_id}
                                required
                                class="input-trust text-xs"
                            >
                                <option value="">-- เลือกผู้ใช้ --</option>
                                {#each recipients as r (r.id)}
                                    <option value={r.id}>{r.name} ({r.email})</option>
                                {/each}
                            </select>
                            {#if shareForm.errors.shared_with_user_id}
                                <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.shared_with_user_id}</p>
                            {/if}
                        </div>
                    {:else}
                        <div>
                            <label for="share_dept_select" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกแผนกเป้าหมาย</label>
                            <select
                                id="share_dept_select"
                                bind:value={shareForm.shared_with_department_id}
                                required
                                class="input-trust text-xs"
                            >
                                <option value="">-- เลือกแผนก --</option>
                                {#each departments as d (d.id)}
                                    <option value={d.id}>แผนก {d.name}</option>
                                {/each}
                            </select>
                            {#if shareForm.errors.shared_with_department_id}
                                <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.shared_with_department_id}</p>
                            {/if}
                        </div>
                    {/if}

                    <div>
                        <label for="share_perm_select" class="block text-[11px] font-bold text-[#232F3E] mb-1">สิทธิ์การเข้าถึง</label>
                        <select
                            id="share_perm_select"
                            bind:value={shareForm.permission}
                            class="input-trust text-xs"
                        >
                            <option value="view">ดูอย่างเดียว</option>
                            <option value="edit">ดูและแก้ไข</option>
                        </select>
                    </div>

                    <div>
                        <label for="share_expiry_input" class="block text-[11px] font-bold text-[#232F3E] mb-1">วันหมดอายุ (ไม่บังคับ)</label>
                        <input
                            id="share_expiry_input"
                            type="datetime-local"
                            bind:value={shareForm.expires_at}
                            class="input-trust text-xs"
                        />
                        {#if shareForm.errors.expires_at}
                            <p class="mt-1 text-xs text-[#E53E3E]">{shareForm.errors.expires_at}</p>
                        {/if}
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (showShareModal = false)} class="btn-secondary">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={shareForm.processing} class="btn-primary">
                            บันทึกการแชร์
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- File Preview Modal -->
    <FilePreviewModal file={previewingFile} onclose={() => (previewingFile = null)} />
</AppLayout>
