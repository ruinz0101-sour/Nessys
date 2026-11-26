<script>
import { Link } from '@inertiajs/svelte';

let {
    open = false,
    status = 'uploading', // 'uploading' | 'success' | 'error' | 'quota_exceeded'
    percentage = 0,
    loadedBytes = 0,
    totalBytes = 0,
    speedBytesPerSec = 0,
    files = [],
    destination = '',
    errorMessage = '',
    quotaInfo = null, // { usedBytes, quotaBytes, remainingBytes, neededBytes }
    canRetry = false,
    oncancel = () => {},
    onretry = () => {},
    onrefresh = () => {},
    onclose = () => {},
} = $props();

const clampedPercentage = $derived(Math.min(100, Math.max(0, Math.round(percentage || 0))));
const isSavingOnServer = $derived(status === 'uploading' && clampedPercentage >= 100);
const isQuotaExceeded = $derived(status === 'quota_exceeded');
const quotaUsedPercent = $derived.by(() => {
    if (!quotaInfo || !quotaInfo.quotaBytes || quotaInfo.quotaBytes <= 0) {
        return 100;
    }
    return Math.min(100, Math.round((quotaInfo.usedBytes / quotaInfo.quotaBytes) * 100));
});
const hasVideo = $derived(
    files.some((f) => {
        const mime = (f.type || '').toLowerCase();
        const ext = (f.name?.split('.').pop() || '').toLowerCase();
        return mime.startsWith('video/') || ['mp4', 'webm', 'ogg', 'mov', 'mkv', 'avi'].includes(ext);
    })
);

function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

function isVideoFile(file) {
    const mime = (file.type || '').toLowerCase();
    const ext = (file.name?.split('.').pop() || '').toLowerCase();
    return mime.startsWith('video/') || ['mp4', 'webm', 'ogg', 'mov', 'mkv', 'avi'].includes(ext);
}

function isImageFile(file) {
    const mime = (file.type || '').toLowerCase();
    const ext = (file.name?.split('.').pop() || '').toLowerCase();
    return mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'].includes(ext);
}
</script>

{#if open}
    <div
        class="fixed inset-0 z-50 bg-slate-900/55 backdrop-blur-xs flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label="สถานะการอัปโหลดไฟล์"
    >
        <div class="bg-white rounded-2xl border border-slate-200 max-w-md w-full overflow-hidden shadow-2xl animate-fade-up">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 bg-slate-50/70">
                <div class="flex items-center gap-3 min-w-0">
                    {#if status === 'success'}
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </div>
                    {:else if status === 'error' || isQuotaExceeded}
                        <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                        </div>
                    {:else}
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-orange-500/25">
                            <svg class="w-4.5 h-4.5 animate-bounce" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="17 8 12 3 7 8" />
                                <line x1="12" y1="3" x2="12" y2="15" />
                            </svg>
                        </div>
                    {/if}

                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 truncate">
                            {#if status === 'success'}
                                อัปโหลดเสร็จสิ้น
                            {:else if isQuotaExceeded}
                                พื้นที่จัดเก็บไม่เพียงพอ
                            {:else if status === 'error'}
                                อัปโหลดไม่สำเร็จ
                            {:else if hasVideo}
                                กำลังอัปโหลดไฟล์ / วิดีโอ
                            {:else}
                                กำลังอัปโหลดไฟล์
                            {/if}
                        </h3>
                        {#if destination}
                            <p class="text-[11px] text-slate-500 truncate mt-0.5">
                                ปลายทาง: {destination}
                            </p>
                        {/if}
                    </div>
                </div>

                {#if status !== 'uploading'}
                    <button
                        type="button"
                        onclick={onclose}
                        aria-label="ปิดหน้าต่าง"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition cursor-pointer shrink-0"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                {/if}
            </div>

            <!-- Body -->
            <div class="p-5 flex flex-col gap-4">
                {#if isQuotaExceeded}
                    <!-- Quota Exceeded Notice -->
                    <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex flex-col gap-1.5">
                        <div class="font-bold text-rose-700">
                            {errorMessage || 'พื้นที่จัดเก็บของคุณเต็มหรือไม่เพียงพอสำหรับการอัปโหลด'}
                        </div>
                        <p class="text-[11px] text-rose-600">
                            กรุณาลบไฟล์ที่ไม่จำเป็นหรือล้างถังขยะเพื่อเพิ่มพื้นที่ว่าง หรือติดต่อผู้ดูแลระบบเพื่อขอเพิ่มโควต้าพื้นที่
                        </p>
                    </div>

                    {#if quotaInfo && quotaInfo.quotaBytes > 0}
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col gap-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-slate-700">การใช้งานพื้นที่ของคุณ</span>
                                <span class="font-mono font-bold text-rose-600">{quotaUsedPercent}%</span>
                            </div>
                            <div class="w-full h-2.5 rounded-full bg-slate-200 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-rose-500 to-red-600"
                                    style="width: {quotaUsedPercent}%"
                                ></div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
                                <div class="p-2 rounded-lg bg-white border border-slate-200/70">
                                    <div class="text-slate-500">ใช้ไปแล้ว / ทั้งหมด</div>
                                    <div class="font-mono font-bold text-slate-800 mt-0.5">
                                        {formatBytes(quotaInfo.usedBytes)} / {formatBytes(quotaInfo.quotaBytes)}
                                    </div>
                                </div>
                                <div class="p-2 rounded-lg bg-white border border-slate-200/70">
                                    <div class="text-slate-500">พื้นที่คงเหลือ</div>
                                    <div class="font-mono font-bold text-rose-600 mt-0.5">
                                        {formatBytes(quotaInfo.remainingBytes)}
                                    </div>
                                </div>
                            </div>
                            {#if quotaInfo.neededBytes > 0}
                                <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-200/70">
                                    <span class="text-slate-600">ขนาดไฟล์ที่ต้องการอัปโหลด</span>
                                    <span class="font-mono font-bold text-slate-900">{formatBytes(quotaInfo.neededBytes)}</span>
                                </div>
                            {/if}
                        </div>
                    {/if}
                {:else}
                    <!-- Progress Section -->
                    <div class="flex flex-col gap-2">
                        <div class="flex items-end justify-between gap-2">
                            <div class="text-xs font-medium text-slate-600">
                                {#if status === 'success'}
                                    <span class="text-emerald-700 font-semibold">เพิ่มไฟล์เข้าสู่ระบบเรียบร้อยแล้ว ({files.length} รายการ)</span>
                                {:else if status === 'error'}
                                    <span class="text-rose-600 font-semibold">เกิดข้อผิดพลาดระหว่างอัปโหลด</span>
                                {:else if isSavingOnServer}
                                    <span class="text-sky-700 font-semibold">ส่งไฟล์ครบ 100% กำลังบันทึกลงระบบ...</span>
                                {:else}
                                    <span>กำลังส่งข้อมูลเข้าสู่ระบบ ({files.length} รายการ)</span>
                                {/if}
                            </div>

                            <div class="text-2xl font-bold font-mono leading-none tabular-nums {status === 'success' ? 'text-emerald-600' : status === 'error' ? 'text-rose-600' : 'text-slate-900'}">
                                {status === 'success' ? 100 : clampedPercentage}%
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden border border-slate-200/70">
                            <div
                                class="h-full rounded-full transition-all duration-200 ease-out {status === 'success' ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : status === 'error' ? 'bg-rose-500' : 'bg-gradient-to-r from-amber-400 via-orange-500 to-orange-600'} {isSavingOnServer ? 'animate-pulse' : ''}"
                                style="width: {status === 'success' ? 100 : clampedPercentage}%"
                            ></div>
                        </div>

                        <!-- Bytes Summary -->
                        {#if totalBytes > 0}
                            <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono">
                                <span>
                                    {#if status === 'success'}
                                        {formatBytes(totalBytes)} / {formatBytes(totalBytes)}
                                    {:else}
                                        {formatBytes(Math.min(loadedBytes, totalBytes))} / {formatBytes(totalBytes)}
                                    {/if}
                                </span>
                                {#if status === 'uploading'}
                                    <span>
                                        {#if isSavingOnServer}
                                            กำลังตรวจสอบและบันทึกไฟล์...
                                        {:else if speedBytesPerSec > 0}
                                            {formatBytes(speedBytesPerSec)}/s
                                        {:else}
                                            กรุณาอย่าปิดหน้าต่างนี้
                                        {/if}
                                    </span>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <!-- Error Box -->
                    {#if status === 'error' && errorMessage}
                        <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">
                            {errorMessage}
                        </div>
                    {/if}
                {/if}

                <!-- File List -->
                {#if files.length > 0}
                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 divide-y divide-slate-200/60 max-h-44 overflow-y-auto">
                        {#each files as file, idx (`${file.name}-${idx}`)}
                            <div class="px-3 py-2 flex items-center justify-between gap-2.5 text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    {#if isVideoFile(file)}
                                        <svg class="w-4 h-4 shrink-0 text-purple-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="18" x="3" y="3" rx="2" />
                                            <path d="m9 8 7 4-7 4Z" />
                                        </svg>
                                    {:else if isImageFile(file)}
                                        <svg class="w-4 h-4 shrink-0 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                                            <circle cx="9" cy="9" r="2" />
                                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                                        </svg>
                                    {:else}
                                        <svg class="w-4 h-4 shrink-0 text-sky-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14 2 14 8 20 8" />
                                        </svg>
                                    {/if}
                                    <span class="truncate font-medium text-slate-800" title={file.name}>{file.name}</span>
                                </div>
                                <span class="text-[11px] text-slate-500 font-mono shrink-0">{formatBytes(file.size)}</span>
                            </div>
                        {/each}
                    </div>
                {/if}

                <!-- Footer Actions -->
                <div class="flex justify-end gap-2 pt-1">
                    {#if status === 'uploading'}
                        <button
                            type="button"
                            onclick={oncancel}
                            disabled={isSavingOnServer}
                            class="btn-secondary text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            ยกเลิกการอัปโหลด
                        </button>
                    {:else if isQuotaExceeded}
                        <Link
                            href="/trash"
                            onclick={onclose}
                            class="btn-secondary text-xs"
                        >
                            ไปที่ถังขยะ
                        </Link>
                        <button
                            type="button"
                            onclick={onclose}
                            class="btn-primary text-xs"
                        >
                            รับทราบ
                        </button>
                    {:else if status === 'error'}
                        <button
                            type="button"
                            onclick={onrefresh}
                            class="btn-secondary text-xs"
                        >
                            <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" />
                                <path d="M21 3v5h-5" />
                            </svg>
                            <span>รีเฟรชหน้า</span>
                        </button>
                        {#if canRetry}
                            <button
                                type="button"
                                onclick={onretry}
                                class="btn-primary text-xs"
                            >
                                ลองอัปโหลดอีกครั้ง
                            </button>
                        {/if}
                        <button
                            type="button"
                            onclick={onclose}
                            class="btn-secondary text-xs"
                        >
                            ปิดหน้าต่าง
                        </button>
                    {:else}
                        <button
                            type="button"
                            onclick={onclose}
                            class="{status === 'success' ? 'btn-success' : 'btn-primary'} text-xs"
                        >
                            ปิดหน้าต่าง
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    </div>
{/if}
