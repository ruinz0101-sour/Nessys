<script>
import { Link, useForm } from '@inertiajs/svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';

let { node, existingPending = false } = $props();

const form = useForm({
    node_id: null,
    reason: '',
});

function submitRequest(e) {
    e.preventDefault();
    form.transform((data) => ({
        node_id: node.id,
        reason: data.reason,
    })).post('/access-requests');
}
</script>

<AppLayout title="ขอสิทธิ์เข้าถึงไฟล์ / โฟลเดอร์">
    <div class="max-w-md mx-auto bg-white rounded-[3px] border border-[#DDDDDD] p-6 shadow-[0_2px_12px_rgba(0,0,0,0.06)]">
        <div class="w-10 h-10 rounded-[3px] bg-[#FFF9F0] border border-[#FF9900]/30 text-[#FF9900] flex items-center justify-center mb-3.5">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
        </div>

        <h2 class="text-sm font-bold text-[#131921] mb-1">คุณยังไม่มีสิทธิ์เข้าถึงรายการนี้</h2>
        <p class="text-xs text-[#555555] mb-4 leading-relaxed">
            คุณสามารถส่งคำขอสิทธิ์เข้าถึงไปยังเจ้าของไฟล์ (หรือผู้ดูแลระบบ หากเจ้าของเดิมถูกปิดบัญชี) เพื่อขออนุมัติได้
        </p>

        <div class="p-3.5 rounded-[3px] bg-[#F7F9FA] border border-[#DDDDDD] mb-5 text-xs flex flex-col gap-1">
            <div class="font-bold text-[#131921] flex items-center gap-2">
                {#if node.type === 'folder'}
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
                <span>{node.name}</span>
            </div>
            <div class="text-[11px] text-[#666666]">
                เจ้าของ: {node.owner_name ?? '—'}
                {#if !node.owner_is_active}
                    <span class="ml-1 px-1.5 py-0.2 rounded-[2px] bg-[#FFF9F0] text-[#A86400] text-[10px] font-bold border border-[#FF9900]/30">
                        ส่งต่อผู้ดูแลระบบอัตโนมัติ
                    </span>
                {/if}
            </div>
        </div>

        {#if existingPending}
            <div class="p-3.5 rounded-[3px] bg-[#FFF9F0] border border-[#FF9900]/30 text-[#A86400] text-xs font-medium mb-4">
                คุณมีคำขอสิทธิ์สำหรับรายการนี้ที่กำลังรอการพิจารณาอยู่แล้ว ไม่สามารถส่งคำขอซ้ำได้ในขณะนี้
            </div>
            <div class="flex justify-end gap-2">
                <Link href="/access-requests" class="btn-primary text-xs">
                    ดูสถานะคำขอของฉัน
                </Link>
            </div>
        {:else}
            <form onsubmit={submitRequest} class="flex flex-col gap-3.5">
                <div>
                    <label for="req_reason" class="block text-[11px] font-bold text-[#232F3E] mb-1">
                        เหตุผลในการขอสิทธิ์เข้าถึง
                    </label>
                    <textarea
                        id="req_reason"
                        rows="3"
                        bind:value={form.reason}
                        required
                        placeholder="ระบุเหตุผลและความจำเป็นในการเข้าถึงไฟล์หรือโฟลเดอร์นี้..."
                        class="input-trust text-xs"
                    ></textarea>
                    {#if form.errors.node_id}
                        <p class="mt-1 text-xs text-[#E53E3E]">{form.errors.node_id}</p>
                    {/if}
                    {#if form.errors.reason}
                        <p class="mt-1 text-xs text-[#E53E3E]">{form.errors.reason}</p>
                    {/if}
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <Link
                        href="/files"
                        class="btn-secondary"
                    >
                        กลับหน้าไฟล์
                    </Link>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="btn-primary"
                    >
                        {form.processing ? 'กำลังส่งคำขอ...' : 'ส่งคำขอสิทธิ์'}
                    </button>
                </div>
            </form>
        {/if}
    </div>
</AppLayout>
