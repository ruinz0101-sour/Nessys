<script>
import { Link } from '@inertiajs/svelte';

let {
    open = false,
    minimized = false,
    status = 'uploading', // 'uploading' | 'paused' | 'success' | 'error' | 'quota_exceeded'
    percentage = 0,
    loadedBytes = 0,
    totalBytes = 0,
    speedBytesPerSec = 0,
    elapsedSeconds = 0,
    remainingSeconds = null,
    estimatedFinishAt = null,
    finishedClockAt = null,
    files = [],
    destination = '',
    errorMessage = '',
    quotaInfo = null, // { usedBytes, quotaBytes, remainingBytes, neededBytes }
    canRetry = false,
    onpause = () => {},
    onresume = () => {},
    onminimize = () => {},
    oncancel = () => {},
    onretry = () => {},
    onrefresh = () => {},
    onclose = () => {},
} = $props();

const clampedPercentage = $derived(Math.min(100, Math.max(0, Math.round(percentage || 0))));
const isUploading = $derived(status === 'uploading');
const isPaused = $derived(status === 'paused');
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

function formatDuration(totalSec) {
    if (totalSec === null || totalSec === undefined || !Number.isFinite(totalSec) || totalSec < 0) {
        return 'กำลังคำนวณ...';
    }
    const sec = Math.round(totalSec);
    const hours = Math.floor(sec / 3600);
    const minutes = Math.floor((sec % 3600) / 60);
    const seconds = sec % 60;

    if (hours > 0) {
        return `${hours} ชม. ${minutes} นาที`;
    }
    if (minutes > 0) {
        return `${minutes} นาที ${seconds} วิ`;
    }
    return `${seconds} วินาที`;
}

const thaiClockFormatter = new Intl.DateTimeFormat('th-TH', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
});

function formatClockTime(timestampMs) {
    if (!timestampMs) {
        return '--:--:--';
    }
    try {
        return `${thaiClockFormatter.format(timestampMs)} น.`;
    } catch {
        return '--:--:--';
    }
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
    <!-- Floating Non-Blocking Upload Panel (ไม่บล็อกหน้าจอ สามารถคลิกทำงานอื่นต่อได้ทันที) -->
    <div
        class="fixed bottom-4 right-4 z-50 w-[calc(100vw-2rem)] max-w-md pointer-events-auto"
        role="region"
        aria-label="สถานะการอัปโหลดไฟล์"
    >
        {#if minimized}
            <!-- Compact Minimized Bar -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xl shadow-slate-900/20 overflow-hidden animate-fade-up">
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        onclick={onminimize}
                        class="flex items-center gap-2.5 min-w-0 flex-1 text-left cursor-pointer group"
                        title="ขยายหน้าต่างอัปโหลด"
                    >
                        {#if status === 'success'}
                            <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                            </span>
                        {:else if status === 'error' || isQuotaExceeded}
                            <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                            </span>
                        {:else if isPaused}
                            <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="6" y="4" width="4" height="16" rx="1" />
                                    <rect x="14" y="4" width="4" height="16" rx="1" />
                                </svg>
                            </span>
                        {:else}
                            <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-4 h-4 animate-bounce" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                    <polyline points="17 8 12 3 7 8" />
                                    <line x1="12" y1="3" x2="12" y2="15" />
                                </svg>
                            </span>
                        {/if}

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2 text-xs font-bold text-slate-900">
                                <span class="truncate">
                                    {#if status === 'success'}
                                        อัปโหลดเสร็จสิ้น
                                    {:else if isPaused}
                                        หยุดชั่วคราว ({files.length} ไฟล์)
                                    {:else if status === 'error' || isQuotaExceeded}
                                        อัปโหลดไม่สำเร็จ
                                    {:else}
                                        กำลังอัปโหลด ({files.length} ไฟล์)
                                    {/if}
                                </span>
                                <span class="font-mono tabular-nums shrink-0 {status === 'success' ? 'text-emerald-600' : isPaused ? 'text-amber-700' : 'text-orange-600'}">
                                    {status === 'success' ? 100 : clampedPercentage}%
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 truncate mt-0.5 font-mono">
                                {#if isUploading}
                                    {#if isSavingOnServer}
                                        กำลังบันทึกไฟล์...
                                    {:else if estimatedFinishAt}
                                        เหลือ ~{formatDuration(remainingSeconds)} • เสร็จ ~{formatClockTime(estimatedFinishAt)}
                                    {:else}
                                        ใช้เวลา {formatDuration(elapsedSeconds)}
                                    {/if}
                                {:else if isPaused}
                                    ใช้เวลาไปแล้ว {formatDuration(elapsedSeconds)} • กดเพื่ออัปโหลดต่อ
                                {:else if status === 'success'}
                                    เสร็จเมื่อ {formatClockTime(finishedClockAt)} ({formatDuration(elapsedSeconds)})
                                {:else}
                                    คลิกเพื่อดูรายละเอียด
                                {/if}
                            </div>
                        </div>
                    </button>

                    <div class="flex items-center gap-1 shrink-0">
                        {#if isUploading && !isSavingOnServer}
                            <button
                                type="button"
                                onclick={onpause}
                                title="หยุดชั่วคราว"
                                aria-label="หยุดชั่วคราว"
                                class="p-1.5 rounded-lg text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="6" y="4" width="4" height="16" rx="1" />
                                    <rect x="14" y="4" width="4" height="16" rx="1" />
                                </svg>
                            </button>
                        {:else if isPaused}
                            <button
                                type="button"
                                onclick={onresume}
                                title="อัปโหลดต่อ"
                                aria-label="อัปโหลดต่อ"
                                class="p-1.5 rounded-lg text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/80 transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="6 3 20 12 6 21 6 3" />
                                </svg>
                            </button>
                        {/if}

                        <button
                            type="button"
                            onclick={onminimize}
                            title="ขยายหน้าต่าง"
                            aria-label="ขยายหน้าต่าง"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition cursor-pointer"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="18 15 12 9 6 15" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Thin bottom progress bar -->
                <div class="w-full h-1 bg-slate-100">
                    <div
                        class="h-full transition-[width] duration-200 {status === 'success' ? 'bg-emerald-500' : status === 'error' || isQuotaExceeded ? 'bg-rose-500' : isPaused ? 'bg-amber-400' : 'bg-gradient-to-r from-amber-400 to-orange-600'}"
                        style="width: {status === 'success' ? 100 : clampedPercentage}%"
                    ></div>
                </div>
            </div>
        {:else}
            <!-- Expanded Floating Card -->
            <div class="bg-white rounded-2xl border border-slate-200/90 w-full overflow-hidden shadow-2xl shadow-slate-900/20 animate-fade-up">
                <!-- Header -->
                <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3 bg-slate-50/80">
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
                        {:else if isPaused}
                            <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                                <svg class="w-4.5 h-4.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="6" y="4" width="4" height="16" rx="1" />
                                    <rect x="14" y="4" width="4" height="16" rx="1" />
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
                                {:else if isPaused}
                                    หยุดการอัปโหลดชั่วคราว
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

                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            onclick={onminimize}
                            title="ย่อหน้าต่าง (ทำงานอื่นต่อได้)"
                            aria-label="ย่อหน้าต่าง"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition cursor-pointer"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>

                        {#if !isUploading && !isPaused}
                            <button
                                type="button"
                                onclick={onclose}
                                aria-label="ปิดหน้าต่าง"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition cursor-pointer"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        {/if}
                    </div>
                </div>

                <!-- Body -->
                <div class="p-4 flex flex-col gap-3.5">
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
                                    {:else if isPaused}
                                        <span class="text-amber-700 font-semibold">หยุดชั่วคราว — กด "อัปโหลดต่อ" เพื่อทำต่อ</span>
                                    {:else if isSavingOnServer}
                                        <span class="text-sky-700 font-semibold">ส่งไฟล์ครบ 100% กำลังบันทึกลงระบบ...</span>
                                    {:else}
                                        <span>กำลังส่งข้อมูล ({files.length} รายการ) • ทำงานหน้าอื่นต่อได้</span>
                                    {/if}
                                </div>

                                <div class="text-2xl font-bold font-mono leading-none tabular-nums {status === 'success' ? 'text-emerald-600' : status === 'error' ? 'text-rose-600' : isPaused ? 'text-amber-600' : 'text-slate-900'}">
                                    {status === 'success' ? 100 : clampedPercentage}%
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden border border-slate-200/70">
                                <div
                                    class="h-full rounded-full transition-[width] duration-200 ease-out {status === 'success' ? 'bg-gradient-to-r from-emerald-500 to-teal-500' : status === 'error' ? 'bg-rose-500' : isPaused ? 'bg-amber-400' : 'bg-gradient-to-r from-amber-400 via-orange-500 to-orange-600'} {isSavingOnServer ? 'animate-pulse' : ''}"
                                    style="width: {status === 'success' ? 100 : clampedPercentage}%"
                                ></div>
                            </div>

                            <!-- Bytes & Speed Summary -->
                            {#if totalBytes > 0}
                                <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono">
                                    <span>
                                        {#if status === 'success'}
                                            {formatBytes(totalBytes)} / {formatBytes(totalBytes)}
                                        {:else}
                                            {formatBytes(Math.min(loadedBytes, totalBytes))} / {formatBytes(totalBytes)}
                                        {/if}
                                    </span>
                                    {#if isUploading}
                                        <span>
                                            {#if isSavingOnServer}
                                                กำลังตรวจสอบและบันทึกไฟล์...
                                            {:else if speedBytesPerSec > 0}
                                                {formatBytes(speedBytesPerSec)}/s
                                            {:else}
                                                กำลังเริ่มส่งข้อมูล...
                                            {/if}
                                        </span>
                                    {:else if isPaused}
                                        <span class="text-amber-700 font-semibold">หยุดชั่วคราว</span>
                                    {/if}
                                </div>
                            {/if}

                            <!-- Time & ETA Cards -->
                            {#if isUploading || isPaused || status === 'success'}
                                <div class="grid grid-cols-3 gap-2 pt-1">
                                    <div class="px-2.5 py-2 rounded-xl bg-slate-50 border border-slate-200/70">
                                        <div class="text-[10px] text-slate-500 font-medium">ใช้เวลาไปแล้ว</div>
                                        <div class="text-xs font-mono font-bold text-slate-800 mt-0.5 tabular-nums">
                                            {formatDuration(elapsedSeconds)}
                                        </div>
                                    </div>
                                    <div class="px-2.5 py-2 rounded-xl bg-slate-50 border border-slate-200/70">
                                        <div class="text-[10px] text-slate-500 font-medium">
                                            {status === 'success' ? 'สถานะเวลา' : 'เหลืออีกประมาณ'}
                                        </div>
                                        <div class="text-xs font-mono font-bold mt-0.5 tabular-nums {isPaused ? 'text-amber-700' : status === 'success' ? 'text-emerald-600' : 'text-slate-800'}">
                                            {#if status === 'success'}
                                                เสร็จแล้ว
                                            {:else if isPaused}
                                                หยุดชั่วคราว
                                            {:else if isSavingOnServer}
                                                ใกล้เสร็จ...
                                            {:else}
                                                {remainingSeconds !== null ? formatDuration(remainingSeconds) : 'คำนวณ...'}
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="px-2.5 py-2 rounded-xl bg-sky-50/70 border border-sky-200/70">
                                        <div class="text-[10px] text-sky-700 font-medium">
                                            {status === 'success' ? 'เสร็จเมื่อเวลา' : 'คาดว่าจะเสร็จ'}
                                        </div>
                                        <div class="text-xs font-mono font-bold text-sky-900 mt-0.5 tabular-nums">
                                            {#if status === 'success'}
                                                {formatClockTime(finishedClockAt)}
                                            {:else if isPaused}
                                                --:--:--
                                            {:else if isSavingOnServer}
                                                {formatClockTime(Date.now())}
                                            {:else if estimatedFinishAt}
                                                {formatClockTime(estimatedFinishAt)}
                                            {:else}
                                                คำนวณ...
                                            {/if}
                                        </div>
                                    </div>
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
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 divide-y divide-slate-200/60 max-h-36 overflow-y-auto">
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
                    <div class="flex flex-wrap justify-end gap-2 pt-1">
                        {#if isUploading}
                            <button
                                type="button"
                                onclick={onpause}
                                disabled={isSavingOnServer}
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="6" y="4" width="4" height="16" rx="1" />
                                    <rect x="14" y="4" width="4" height="16" rx="1" />
                                </svg>
                                <span>หยุดชั่วคราว</span>
                            </button>
                            <button
                                type="button"
                                onclick={oncancel}
                                disabled={isSavingOnServer}
                                class="btn-secondary text-xs disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                ยกเลิกการอัปโหลด
                            </button>
                        {:else if isPaused}
                            <button
                                type="button"
                                onclick={onresume}
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition cursor-pointer shadow-xs"
                            >
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="6 3 20 12 6 21 6 3" />
                                </svg>
                                <span>อัปโหลดต่อ</span>
                            </button>
                            <button
                                type="button"
                                onclick={oncancel}
                                class="btn-secondary text-xs"
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
        {/if}
    </div>
{/if}
