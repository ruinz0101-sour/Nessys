<script>
let {
    open = false,
    title = 'ยืนยันการลบรายการ',
    itemName = '',
    itemType = 'file', // 'file' | 'folder'
    description = '',
    confirmLabel = 'ยืนยันการลบ',
    cancelLabel = 'ยกเลิก',
    isPermanent = false,
    processing = false,
    onconfirm = () => {},
    oncancel = () => {},
} = $props();

function handleKeydown(e) {
    if (open && e.key === 'Escape' && !processing) {
        oncancel();
    }
}
</script>

<svelte:window onkeydown={handleKeydown} />

{#if open}
    <div
        class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label={title}
    >
        <!-- Backdrop click to close -->
        <button
            type="button"
            tabindex="-1"
            aria-label="ปิดหน้าต่างยืนยัน"
            onclick={() => { if (!processing) oncancel(); }}
            class="fixed inset-0 cursor-default"
        ></button>

        <div class="relative z-10 bg-white rounded-2xl border border-slate-200 max-w-md w-full overflow-hidden shadow-2xl animate-fade-up">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3 {isPermanent ? 'bg-rose-50/70' : 'bg-amber-50/60'}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {isPermanent ? 'bg-rose-100 text-rose-600' : 'bg-amber-100 text-amber-700'}">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                        <line x1="10" y1="11" x2="10" y2="17" />
                        <line x1="14" y1="11" x2="14" y2="17" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-slate-900">{title}</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        กรุณาตรวจสอบรายการก่อนกดยืนยัน
                    </p>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 flex flex-col gap-3.5">
                {#if itemName}
                    <div class="px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center gap-2.5">
                        {#if itemType === 'folder'}
                            <svg class="w-4.5 h-4.5 text-amber-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                            </svg>
                        {:else}
                            <svg class="w-4.5 h-4.5 text-sky-600 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                        {/if}
                        <span class="text-xs font-bold text-slate-900 truncate" title={itemName}>{itemName}</span>
                    </div>
                {/if}

                {#if description}
                    <p class="text-xs leading-relaxed {isPermanent ? 'text-rose-700 bg-rose-50 border border-rose-200/80 rounded-xl p-3 font-medium' : 'text-slate-600'}">
                        {description}
                    </p>
                {/if}

                <!-- Actions -->
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        onclick={oncancel}
                        disabled={processing}
                        class="btn-secondary text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        onclick={onconfirm}
                        disabled={processing}
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 transition cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? 'กำลังลบ...' : confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}
