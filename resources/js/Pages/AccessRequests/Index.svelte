<script>
import { Link, router, useForm } from '@inertiajs/svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';

let {
    incomingRequests = [],
    myRequests = [],
    restrictedNodes = [],
} = $props();

let activeTab = $state('incoming'); // 'incoming' | 'mine'
let showRequestModal = $state(false);
let decidingRequest = $state(null);

const requestForm = useForm({
    node_id: '',
    reason: '',
});

const decideForm = useForm({
    status: 'approved',
    granted_permission: 'view',
    granted_until: '',
    decision_note: '',
});

function openNewRequestModal() {
    requestForm.reset();
    requestForm.clearErrors();
    showRequestModal = true;
}

function submitNewRequest(e) {
    e.preventDefault();
    requestForm.transform((data) => ({
        node_id: Number(data.node_id),
        reason: data.reason,
    })).post('/access-requests', {
        preserveScroll: true,
        onSuccess: () => {
            showRequestModal = false;
            requestForm.reset();
            activeTab = 'mine';
        },
    });
}

function openDecideModal(req, defaultStatus = 'approved') {
    decidingRequest = req;
    decideForm.status = defaultStatus;
    decideForm.granted_permission = 'view';
    decideForm.granted_until = '';
    decideForm.decision_note = '';
    decideForm.clearErrors();
}

function submitDecision(e) {
    e.preventDefault();
    if (!decidingRequest) {
        return;
    }
    decideForm.transform((data) => ({
        status: data.status,
        granted_permission: data.status === 'approved' ? data.granted_permission : null,
        granted_until: data.status === 'approved' && data.granted_until ? data.granted_until : null,
        decision_note: data.decision_note || null,
    })).patch(`/access-requests/${decidingRequest.id}/decide`, {
        preserveScroll: true,
        onSuccess: () => {
            decidingRequest = null;
        },
    });
}

function cancelRequest(req) {
    router.patch(`/access-requests/${req.id}/cancel`, {}, {
        preserveScroll: true,
    });
}

function statusBadge(status) {
    if (status === 'approved') {
        return { label: 'อนุมัติแล้ว', cls: 'bg-[#EBF7EE] text-[#008000] border border-[#008000]/30' };
    }
    if (status === 'denied') {
        return { label: 'ปฏิเสธแล้ว', cls: 'bg-[#FDF2F2] text-[#9B1C1C] border border-[#E53E3E]/30' };
    }
    if (status === 'cancelled') {
        return { label: 'ยกเลิกแล้ว', cls: 'bg-[#F3F4F6] text-[#555555] border border-[#DDDDDD]' };
    }
    return { label: 'รอพิจารณา', cls: 'bg-[#FFF9F0] text-[#A86400] border border-[#FF9900]/30' };
}

function formatDate(iso) {
    if (!iso) {
        return 'ไม่มีกำหนดหมดอายุ';
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

<AppLayout title="คำขอสิทธิ์เข้าถึงไฟล์">
    <div class="flex flex-col gap-5">
        <!-- Top Bar -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-3.5 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div class="inline-flex rounded-xl border border-slate-200/80 p-1 bg-slate-100/80 gap-1">
                <button
                    type="button"
                    onclick={() => (activeTab = 'incoming')}
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {activeTab === 'incoming' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    คำขอที่ต้องตัดสิน ({incomingRequests.length})
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'mine')}
                    class="px-4 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer {activeTab === 'mine' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'}"
                >
                    คำขอของฉัน ({myRequests.length})
                </button>
            </div>

            <button
                type="button"
                onclick={openNewRequestModal}
                class="btn-primary"
            >
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <span>+ ขอสิทธิ์เข้าถึงไฟล์ / โฟลเดอร์</span>
            </button>
        </div>

        {#if activeTab === 'incoming'}
            <!-- Incoming Requests -->
            {#if incomingRequests.length === 0}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] p-12 text-center text-[#777777] text-xs shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="font-bold text-sm text-[#131921]">ไม่มีคำขอสิทธิ์เข้าถึงที่รอการตัดสิน</div>
                    <div class="text-[11px] text-[#777777] mt-1">เมื่อมีเพื่อนร่วมงานขอเข้าถึงไฟล์ของคุณ รายการจะปรากฏที่นี่</div>
                </div>
            {:else}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] overflow-hidden shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[640px]">
                        <thead>
                            <tr class="bg-[#F7F9FA] border-b border-[#DDDDDD] text-[#232F3E] text-[11px] font-bold uppercase tracking-wider">
                                <th class="py-2.5 px-4">ไฟล์ / โฟลเดอร์</th>
                                <th class="py-2.5 px-4">ผู้ขอสิทธิ์</th>
                                <th class="py-2.5 px-4">เหตุผล</th>
                                <th class="py-2.5 px-4">สถานะ</th>
                                <th class="py-2.5 px-4">สิทธิ์ / วันหมดอายุ</th>
                                <th class="py-2.5 px-4 text-right">การตัดสิน</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEEEEE]">
                            {#each incomingRequests as req (req.id)}
                                {@const badge = statusBadge(req.status)}
                                <tr class="hover:bg-[#FBFBFC] transition-colors">
                                    <td class="py-2.5 px-4">
                                        <div class="font-bold text-[#131921] flex items-center gap-2">
                                            {#if req.node_type === 'folder'}
                                                <svg class="w-4 h-4 text-[#FF9900] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                                </svg>
                                            {:else}
                                                <svg class="w-4 h-4 text-[#00A8E1] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                    <path d="M14 2v6h6" />
                                                    <line x1="16" y1="13" x2="8" y2="13" />
                                                    <line x1="16" y1="17" x2="8" y2="17" />
                                                    <line x1="10" y1="9" x2="8" y2="9" />
                                                </svg>
                                            {/if}
                                            <span>{req.node_name}</span>
                                        </div>
                                        {#if req.escalated_to_admin}
                                            <span class="mt-1 inline-block px-1.5 py-0.2 rounded-[2px] text-[10px] font-bold bg-[#FFF9F0] text-[#A86400] border border-[#FF9900]/30">
                                                ส่งต่อผู้ดูแลระบบ (เจ้าของเดิม: {req.owner_name} ถูกปิดบัญชี)
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <div class="text-xs font-bold text-[#131921]">{req.requester_name}</div>
                                        <div class="text-[11px] text-[#666666]">{req.requester_email}</div>
                                    </td>
                                    <td class="py-2.5 px-4 text-[11px] text-[#555555] max-w-xs">
                                        {req.reason || '—'}
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold {badge.cls}">
                                            {badge.label}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 text-[#555555] text-[11px]">
                                        {#if req.status === 'approved'}
                                            <div>สิทธิ์: {req.granted_permission === 'edit' ? 'ดูและแก้ไข' : 'ดูอย่างเดียว'}</div>
                                            <div class="text-[10px] text-[#888888] font-mono">{formatDate(req.granted_until)}</div>
                                        {:else}
                                            —
                                        {/if}
                                    </td>
                                    <td class="py-2.5 px-4 text-right">
                                        {#if req.status === 'pending'}
                                            <div class="inline-flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onclick={() => openDecideModal(req, 'approved')}
                                                    class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#EBF7EE] hover:bg-[#D4EDDA] text-[#008000] border border-[#008000]/30 transition cursor-pointer"
                                                >
                                                    อนุมัติ
                                                </button>
                                                <button
                                                    type="button"
                                                    onclick={() => openDecideModal(req, 'denied')}
                                                    class="px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#B91C1C] border border-[#E53E3E]/30 transition cursor-pointer"
                                                >
                                                    ปฏิเสธ
                                                </button>
                                            </div>
                                        {:else}
                                            <span class="text-[11px] text-[#888888]">{req.decided_by_name ? `โดย ${req.decided_by_name}` : '—'}</span>
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
            <!-- My Requests -->
            {#if myRequests.length === 0}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] p-12 text-center text-[#777777] text-xs shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="font-bold text-sm text-[#131921]">คุณยังไม่เคยส่งคำขอสิทธิ์เข้าถึง</div>
                    <div class="text-[11px] text-[#777777] mt-1">หากต้องการเข้าถึงไฟล์ที่ถูกล็อก สามารถกดปุ่ม "+ ขอสิทธิ์เข้าถึงไฟล์ / โฟลเดอร์" ด้านบน</div>
                </div>
            {:else}
                <div class="bg-white rounded-[3px] border border-[#DDDDDD] overflow-hidden shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[640px]">
                        <thead>
                            <tr class="bg-[#F7F9FA] border-b border-[#DDDDDD] text-[#232F3E] text-[11px] font-bold uppercase tracking-wider">
                                <th class="py-2.5 px-4">ไฟล์ / โฟลเดอร์</th>
                                <th class="py-2.5 px-4">เจ้าของไฟล์</th>
                                <th class="py-2.5 px-4">เหตุผลของฉัน</th>
                                <th class="py-2.5 px-4">สถานะ</th>
                                <th class="py-2.5 px-4">สิทธิ์ที่ได้รับ</th>
                                <th class="py-2.5 px-4 text-right">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEEEEE]">
                            {#each myRequests as req (req.id)}
                                {@const badge = statusBadge(req.status)}
                                <tr class="hover:bg-[#FBFBFC] transition-colors">
                                    <td class="py-2.5 px-4 font-bold text-[#131921] flex items-center gap-2">
                                        {#if req.node_type === 'folder'}
                                            <svg class="w-4 h-4 text-[#FF9900] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                            </svg>
                                        {:else}
                                            <svg class="w-4 h-4 text-[#00A8E1] shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <path d="M14 2v6h6" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                                <line x1="10" y1="9" x2="8" y2="9" />
                                            </svg>
                                        {/if}
                                        <span>{req.node_name}</span>
                                    </td>
                                    <td class="py-2.5 px-4 text-[#555555] text-[11px]">
                                        {req.owner_name ?? '—'}
                                    </td>
                                    <td class="py-2.5 px-4 text-[11px] text-[#555555] max-w-xs">
                                        {req.reason || '—'}
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-bold {badge.cls}">
                                            {badge.label}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 text-[#555555] text-[11px]">
                                        {#if req.status === 'approved'}
                                            <div>สิทธิ์: {req.granted_permission === 'edit' ? 'ดูและแก้ไข' : 'ดูอย่างเดียว'}</div>
                                            <div class="text-[10px] text-[#888888] font-mono">{formatDate(req.granted_until)}</div>
                                        {:else}
                                            —
                                        {/if}
                                    </td>
                                    <td class="py-2.5 px-4 text-right">
                                        {#if req.status === 'pending'}
                                            <button
                                                type="button"
                                                onclick={() => cancelRequest(req)}
                                                class="px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#B91C1C] border border-[#E53E3E]/30 transition cursor-pointer"
                                            >
                                                ยกเลิกคำขอ
                                            </button>
                                        {:else if req.status === 'approved' && req.node_uuid}
                                            <Link
                                                href={`/files?root=${req.root_type}&folder=${req.node_uuid}`}
                                                class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#EBF8FC] hover:bg-[#D6F1FA] text-[#007099] border border-[#00A8E1]/30 transition"
                                            >
                                                ไปยังไฟล์
                                            </Link>
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

    <!-- Modal: ส่งคำขอสิทธิ์ใหม่ -->
    {#if showRequestModal}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-3">ขอสิทธิ์เข้าถึงไฟล์หรือโฟลเดอร์</h3>
                <form onsubmit={submitNewRequest} class="flex flex-col gap-3.5">
                    <div>
                        <label for="req_node_id" class="block text-[11px] font-bold text-[#232F3E] mb-1">เลือกไฟล์หรือโฟลเดอร์ที่ต้องการขอสิทธิ์</label>
                        <select
                            id="req_node_id"
                            bind:value={requestForm.node_id}
                            required
                            class="input-trust text-xs"
                        >
                            <option value="">-- เลือกรายการ --</option>
                            {#each restrictedNodes as n (n.id)}
                                <option value={n.id} disabled={n.has_pending_request}>
                                    [{n.type === 'folder' ? 'โฟลเดอร์' : 'ไฟล์'}] {n.name} (เจ้าของ: {n.owner_name ?? n.owner?.name ?? 'ไม่ระบุ'}){n.has_pending_request ? ' [รอพิจารณาอยู่แล้ว]' : ''}
                                </option>
                            {/each}
                        </select>
                        {#if requestForm.errors.node_id}
                            <p class="mt-1 text-xs text-[#E53E3E]">{requestForm.errors.node_id}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="req_reason" class="block text-[11px] font-bold text-[#232F3E] mb-1">เหตุผลความจำเป็นในการขอสิทธิ์</label>
                        <textarea
                            id="req_reason"
                            rows="3"
                            bind:value={requestForm.reason}
                            required
                            placeholder="ระบุเหตุผลในการขอสิทธิ์เข้าถึงไฟล์นี้..."
                            class="input-trust text-xs"
                        ></textarea>
                        {#if requestForm.errors.reason}
                            <p class="mt-1 text-xs text-[#E53E3E]">{requestForm.errors.reason}</p>
                        {/if}
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (showRequestModal = false)} class="btn-secondary">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={requestForm.processing} class="btn-primary">
                            ส่งคำขอสิทธิ์
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: ตัดสินคำขอ (อนุมัติ / ปฏิเสธ) -->
    {#if decidingRequest}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-[#131921] mb-1">พิจารณาคำขอสิทธิ์เข้าถึง</h3>
                <p class="text-xs text-[#666666] mb-3 truncate">ไฟล์: {decidingRequest.node_name} · ผู้ขอ: {decidingRequest.requester_name}</p>
                <form onsubmit={submitDecision} class="flex flex-col gap-3.5">
                    <div>
                        <label for="decide_status" class="block text-[11px] font-bold text-[#232F3E] mb-1">ผลการพิจารณา</label>
                        <select
                            id="decide_status"
                            bind:value={decideForm.status}
                            class="input-trust text-xs"
                        >
                            <option value="approved">อนุมัติให้เข้าถึง</option>
                            <option value="denied">ปฏิเสธคำขอ</option>
                        </select>
                    </div>

                    {#if decideForm.status === 'approved'}
                        <div>
                            <label for="decide_perm" class="block text-[11px] font-bold text-[#232F3E] mb-1">ระดับสิทธิ์ที่มอบให้</label>
                            <select
                                id="decide_perm"
                                bind:value={decideForm.granted_permission}
                                class="input-trust text-xs"
                            >
                                <option value="view">ดูอย่างเดียว</option>
                                <option value="edit">ดูและแก้ไข</option>
                            </select>
                        </div>

                        <div>
                            <label for="decide_until" class="block text-[11px] font-bold text-[#232F3E] mb-1">กำหนดวันหมดอายุสิทธิ์ (ไม่บังคับ)</label>
                            <input
                                id="decide_until"
                                type="datetime-local"
                                bind:value={decideForm.granted_until}
                                class="input-trust text-xs"
                            />
                        </div>
                    {/if}

                    <div>
                        <label for="decide_note" class="block text-[11px] font-bold text-[#232F3E] mb-1">หมายเหตุการพิจารณา (ไม่บังคับ)</label>
                        <input
                            id="decide_note"
                            type="text"
                            bind:value={decideForm.decision_note}
                            placeholder="ระบุข้อความหรือเงื่อนไขเพิ่มเติม..."
                            class="input-trust text-xs"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (decidingRequest = null)} class="btn-secondary">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={decideForm.processing} class="btn-primary">
                            บันทึกผลการพิจารณา
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</AppLayout>
