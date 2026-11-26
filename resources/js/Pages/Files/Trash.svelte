<script>
import { Link, router } from '@inertiajs/svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';

let { items = [] } = $props();

function restoreItem(item) {
    router.post(`/trash/${item.uuid}/restore`, {}, {
        preserveScroll: true,
    });
}

function forceDeleteItem(item) {
    router.delete(`/trash/${item.uuid}`, {
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
        return '—';
    }
    return new Date(iso).toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<AppLayout title="ถังขยะ">
    <div class="flex flex-col gap-5">
        <!-- Notice Bar -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2.5 text-xs text-[#555555]">
                <div class="w-2 h-2 rounded-full bg-[#FF9900]"></div>
                <span>นโยบายความปลอดภัย: รายการในถังขยะจะถูกลบถาวรโดยอัตโนมัติเมื่อครบกำหนด 30 วันนับจากวันที่ลบ</span>
            </div>
            <Link
                href="/files"
                class="btn-secondary text-xs"
            >
                <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m12 19-7-7 7-7" />
                    <path d="M19 12H5" />
                </svg>
                <span>กลับไปหน้าคลังไฟล์</span>
            </Link>
        </div>

        {#if items.length === 0}
            <div class="bg-white rounded-[3px] border border-[#DDDDDD] p-12 text-center text-[#777777] text-xs shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                <svg class="w-10 h-10 mx-auto text-[#CCCCCC] mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18" />
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                </svg>
                <div class="font-bold text-sm text-[#131921]">ถังขยะว่างเปล่า</div>
                <div class="text-[11px] text-[#777777] mt-1">ไม่มีไฟล์หรือโฟลเดอร์ที่ถูกลบอยู่ในขณะนี้</div>
            </div>
        {:else}
            <div class="bg-white rounded-[3px] border border-[#DDDDDD] overflow-hidden shadow-[0_2px_12px_rgba(0,0,0,0.04)]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[640px]">
                    <thead>
                        <tr class="bg-[#F7F9FA] border-b border-[#DDDDDD] text-[#232F3E] text-[11px] font-bold uppercase tracking-wider">
                            <th class="py-2.5 px-4">ชื่อไฟล์ / โฟลเดอร์</th>
                            <th class="py-2.5 px-4">ขนาด</th>
                            <th class="py-2.5 px-4">วันที่ลบ</th>
                            <th class="py-2.5 px-4">กำหนดลบถาวร</th>
                            <th class="py-2.5 px-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEEEEE]">
                        {#each items as item (item.id)}
                            <tr class="hover:bg-[#FBFBFC] transition-colors">
                                <td class="py-2.5 px-4 font-bold text-[#131921] flex items-center gap-2">
                                    {#if item.type === 'folder'}
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
                                    <span>{item.name}</span>
                                </td>
                                <td class="py-2.5 px-4 text-[#555555] font-mono text-[11px]">
                                    {item.type === 'folder' ? '—' : formatBytes(item.size_bytes)}
                                </td>
                                <td class="py-2.5 px-4 text-[#555555] text-[11px]">
                                    {formatDate(item.deleted_at)}
                                </td>
                                <td class="py-2.5 px-4 text-[11px] font-bold text-[#E53E3E]">
                                    {formatDate(item.purge_at)}
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            onclick={() => restoreItem(item)}
                                            class="px-2.5 py-1 rounded-[3px] text-[11px] font-bold bg-[#EBF7EE] hover:bg-[#D4EDDA] text-[#008000] border border-[#008000]/30 transition cursor-pointer"
                                        >
                                            กู้คืน
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() => forceDeleteItem(item)}
                                            class="px-2.5 py-1 rounded-[3px] text-[11px] font-medium bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#B91C1C] border border-[#E53E3E]/30 transition cursor-pointer"
                                        >
                                            ลบถาวร
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
                </div>
            </div>
        {/if}
    </div>
</AppLayout>
