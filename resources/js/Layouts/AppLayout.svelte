<script>
import { Link, router, usePage } from '@inertiajs/svelte';
import UploadProgressModal from '../Components/UploadProgressModal.svelte';
import {
    uploadState,
    pauseUpload,
    resumeUpload,
    toggleMinimizeUpload,
    cancelUpload,
    retryUpload,
    refreshPage,
    closeUploadModal,
} from '../stores/uploadManager.svelte.js';

let { title = 'NAS Storage', children } = $props();

const page = usePage();
let user = $derived(page.props.auth?.user);
let flash = $derived(page.props.flash ?? {});
let visibleFlash = $state({ success: null, error: null, status: null });

$effect(() => {
    const success = flash.success ?? null;
    const error = flash.error ?? null;
    const status = flash.status ?? null;

    visibleFlash = { success, error, status };

    if (!success && !error && !status) {
        return;
    }

    const timer = setTimeout(() => {
        visibleFlash = { success: null, error: null, status: null };
    }, 3000);

    return () => clearTimeout(timer);
});
let notifications = $derived(page.props.notifications ?? { unread_count: 0, items: [] });
let currentUrl = $derived(page.url ?? '/');

let showNotifDropdown = $state(false);
let mobileNavOpen = $state(false);

function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

let usagePercent = $derived.by(() => {
    if (!user || !user.quota_bytes || user.quota_bytes <= 0) {
        return 0;
    }
    return Math.min(100, Math.round((user.used_bytes / user.quota_bytes) * 100));
});

function roleLabel(role) {
    if (role === 'admin') {
        return 'ผู้ดูแลระบบ';
    }
    if (role === 'dept_manager') {
        return 'หัวหน้าแผนก';
    }
    return 'ผู้ใช้งาน';
}

function userInitial(name) {
    if (!name) {
        return '?';
    }
    return name.trim().charAt(0).toUpperCase();
}

function markAllRead() {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
    });
}

function markOneRead(n) {
    if (!n.read_at) {
        router.patch(`/notifications/${n.id}/read`, {}, {
            preserveScroll: true,
        });
    }
}
</script>

<div class="min-h-[100dvh] flex flex-col md:flex-row bg-gradient-to-br from-slate-50 via-white to-sky-50/40 text-slate-900 font-sans antialiased">
    <!-- Sidebar -->
    <aside class="w-full md:w-64 bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 text-slate-200 flex flex-col shrink-0 border-b md:border-b-0 md:border-r border-slate-800/80 z-30 shadow-xl shadow-slate-900/10">
        <!-- Brand / Header -->
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 text-white flex items-center justify-center font-bold text-sm tracking-tight shadow-md shadow-orange-500/25">
                    NAS
                </div>
                <div>
                    <div class="font-bold text-sm tracking-tight text-white">
                        CloudStorage
                    </div>
                    <div class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>พื้นที่ทำงานออนไลน์</span>
                    </div>
                </div>
            </div>

            <button
                type="button"
                onclick={() => (mobileNavOpen = !mobileNavOpen)}
                class="md:hidden p-2 rounded-lg border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                aria-label="เปิดเมนู"
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12" />
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="3" y1="18" x2="21" y2="18" />
                </svg>
            </button>
        </div>

        <!-- Admin Quick Switch (if Admin) -->
        {#if user?.role === 'admin'}
            <div class="px-3 pt-3">
                <Link
                    href="/admin/dashboard"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500/15 to-orange-500/15 border border-amber-400/30 text-amber-300 hover:from-amber-500 hover:to-orange-500 hover:text-slate-950 font-semibold text-xs flex items-center justify-between transition-all duration-200 shadow-xs"
                >
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span>ไปที่หน้าผู้ดูแลระบบ</span>
                    </div>
                    <span class="text-xs">&rarr;</span>
                </Link>
            </div>
        {/if}

        <!-- Navigation Links -->
        <nav class="{mobileNavOpen ? 'flex' : 'hidden'} md:flex p-3 flex-1 flex-col gap-1 text-xs">
            <div class="px-3 pt-2 pb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                เมนูหลัก
            </div>

            <Link
                href="/files"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl === '/' || currentUrl.startsWith('/files') ? 'bg-gradient-to-r from-sky-500/20 to-indigo-500/10 text-white font-semibold border border-sky-400/30 shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border border-transparent'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl === '/' || currentUrl.startsWith('/files') ? 'bg-sky-500/25 text-sky-300' : 'bg-slate-800/80 text-slate-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                    </svg>
                </span>
                <span>จัดการไฟล์และโฟลเดอร์</span>
            </Link>

            <Link
                href="/shares"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl.startsWith('/shares') ? 'bg-gradient-to-r from-sky-500/20 to-indigo-500/10 text-white font-semibold border border-sky-400/30 shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border border-transparent'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl.startsWith('/shares') ? 'bg-sky-500/25 text-sky-300' : 'bg-slate-800/80 text-slate-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="18" cy="5" r="3" />
                        <circle cx="6" cy="12" r="3" />
                        <circle cx="18" cy="19" r="3" />
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
                    </svg>
                </span>
                <span>การแชร์ไฟล์</span>
            </Link>

            <Link
                href="/access-requests"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl.startsWith('/access-requests') ? 'bg-gradient-to-r from-sky-500/20 to-indigo-500/10 text-white font-semibold border border-sky-400/30 shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border border-transparent'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl.startsWith('/access-requests') ? 'bg-sky-500/25 text-sky-300' : 'bg-slate-800/80 text-slate-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                </span>
                <span>คำขอสิทธิ์เข้าถึง</span>
            </Link>

            <Link
                href="/trash"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl.startsWith('/trash') ? 'bg-gradient-to-r from-rose-500/20 to-orange-500/10 text-white font-semibold border border-rose-400/30 shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white border border-transparent'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl.startsWith('/trash') ? 'bg-rose-500/25 text-rose-300' : 'bg-slate-800/80 text-slate-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                    </svg>
                </span>
                <span>ถังขยะ</span>
            </Link>
        </nav>

        <!-- Quota Progress Indicator -->
        {#if user}
            <div class="p-3.5 m-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-xs flex flex-col gap-2.5">
                <div class="flex justify-between items-center text-[11px] text-slate-300">
                    <span class="font-medium">โควต้าพื้นที่จัดเก็บ</span>
                    <span class="font-mono text-white text-[11px] font-semibold px-1.5 py-0.5 rounded bg-slate-900/70">
                        {usagePercent}%
                    </span>
                </div>
                {#if user.quota_bytes > 0}
                    <div class="w-full h-2 bg-slate-900/90 rounded-full overflow-hidden p-0.5">
                        <div
                            class="h-full rounded-full transition-all duration-500 {usagePercent >= 90 ? 'bg-gradient-to-r from-rose-500 to-red-500' : usagePercent >= 75 ? 'bg-gradient-to-r from-amber-400 to-orange-500' : 'bg-gradient-to-r from-sky-400 to-cyan-400'}"
                            style="width: {usagePercent}%"
                        ></div>
                    </div>
                {/if}
                <div class="text-[10px] text-slate-400 flex justify-between font-mono">
                    <span>ใช้ไป {formatBytes(user.used_bytes)}</span>
                    <span>จำกัด {user.quota_bytes > 0 ? formatBytes(user.quota_bytes) : 'ไม่จำกัด'}</span>
                </div>
            </div>
        {/if}
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Frosted Sticky Topbar -->
        <header class="sticky top-0 z-20 h-16 bg-white/85 backdrop-blur-md border-b border-slate-200/80 px-6 flex items-center justify-between shadow-[0_1px_8px_rgba(15,23,42,0.03)]">
            <div class="flex items-center gap-3 min-w-0">
                <h1 class="text-base font-bold text-slate-900 tracking-tight truncate">{title}</h1>
                <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-[11px] text-emerald-700 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>พร้อมใช้งาน</span>
                </div>
            </div>

            {#if user}
                <div class="flex items-center gap-3.5">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button
                            type="button"
                            onclick={() => (showNotifDropdown = !showNotifDropdown)}
                            class="relative p-2.5 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer"
                            title="การแจ้งเตือน"
                        >
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                            </svg>
                            {#if notifications.unread_count > 0}
                                <span class="absolute top-1 right-1 px-1.5 py-0.2 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-bold rounded-full shadow-xs">
                                    {notifications.unread_count}
                                </span>
                            {/if}
                        </button>

                        {#if showNotifDropdown}
                            <div class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-slate-200/90 z-50 overflow-hidden animate-fade-up">
                                <div class="p-3.5 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900">การแจ้งเตือน</span>
                                    {#if notifications.unread_count > 0}
                                        <button
                                            type="button"
                                            onclick={markAllRead}
                                            class="text-xs text-sky-600 hover:text-sky-700 hover:underline cursor-pointer font-medium"
                                        >
                                            อ่านทั้งหมด
                                        </button>
                                    {/if}
                                </div>
                                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                    {#if (notifications.items ?? []).length === 0}
                                        <div class="p-6 text-center text-xs text-slate-400">
                                            ไม่มีการแจ้งเตือน
                                        </div>
                                    {:else}
                                        {#each notifications.items as n (n.id)}
                                            <button
                                                type="button"
                                                onclick={() => markOneRead(n)}
                                                class="w-full text-left p-3.5 hover:bg-slate-50 transition flex flex-col gap-1 cursor-pointer {!n.read_at ? 'bg-amber-50/50' : ''}"
                                            >
                                                <div class="text-xs text-slate-900 font-medium">
                                                    {n.data?.message ?? n.type}
                                                </div>
                                                {#if n.data?.permission}
                                                    <div class="text-[11px] text-slate-500">
                                                        สิทธิ์: {n.data.permission === 'edit' ? 'ดูและแก้ไข' : 'ดูอย่างเดียว'}
                                                    </div>
                                                {/if}
                                            </button>
                                        {/each}
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </div>

                    <!-- User Profile & Avatar -->
                    <Link
                        href="/settings/profile"
                        class="flex items-center gap-2.5 pl-2 border-l border-slate-200 hover:opacity-85 transition"
                        title="แก้ไขโปรไฟล์"
                    >
                        {#if user.avatar_url}
                            <img
                                src={user.avatar_url}
                                alt={user.name}
                                class="w-9 h-9 rounded-full object-cover border border-slate-200 shadow-xs shrink-0"
                            />
                        {:else}
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs shrink-0">
                                {userInitial(user.name)}
                            </div>
                        {/if}
                        <div class="hidden sm:block text-left">
                            <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span>{user.name}</span>
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-semibold {user.role === 'admin' ? 'bg-amber-100 text-amber-800' : user.role === 'dept_manager' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-700'}">
                                    {roleLabel(user.role)}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {user.email}
                                {#if user.department_name}
                                    · แผนก {user.department_name}
                                {/if}
                            </div>
                        </div>
                    </Link>

                    <!-- Logout Button -->
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 bg-white text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition duration-200 cursor-pointer shadow-2xs"
                    >
                        ออกจากระบบ
                    </Link>
                </div>
            {/if}
        </header>

        <!-- Flash Banners & Content -->
        <main class="p-6 flex-1 max-w-[1280px] w-full mx-auto animate-fade-up">
            {#if visibleFlash.success}
                <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center gap-2.5 shadow-2xs">
                    <svg class="w-4 h-4 shrink-0 text-emerald-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>{visibleFlash.success}</span>
                </div>
            {/if}

            {#if visibleFlash.error}
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium flex items-center gap-2.5 shadow-2xs">
                    <svg class="w-4 h-4 shrink-0 text-rose-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span>{visibleFlash.error}</span>
                </div>
            {/if}

            {#if visibleFlash.status}
                <div class="mb-5 p-3.5 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-medium flex items-center gap-2.5 shadow-2xs">
                    <svg class="w-4 h-4 shrink-0 text-sky-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="16" x2="12" y2="12" />
                        <line x1="12" y1="8" x2="12.01" y2="8" />
                    </svg>
                    <span>{visibleFlash.status}</span>
                </div>
            {/if}

            {@render children()}
        </main>
    </div>

    <!-- Global Floating Upload Progress Panel (ทำงานต่อได้แม้เปลี่ยนหน้า) -->
    <UploadProgressModal
        open={uploadState.open}
        minimized={uploadState.minimized}
        status={uploadState.status}
        percentage={uploadState.percentage}
        loadedBytes={uploadState.loadedBytes}
        totalBytes={uploadState.totalBytes}
        speedBytesPerSec={uploadState.speedBytesPerSec}
        elapsedSeconds={uploadState.elapsedSeconds}
        remainingSeconds={uploadState.remainingSeconds}
        estimatedFinishAt={uploadState.estimatedFinishAt}
        finishedClockAt={uploadState.finishedClockAt}
        files={uploadState.files}
        destination={uploadState.destination}
        errorMessage={uploadState.errorMessage}
        quotaInfo={uploadState.quotaInfo}
        canRetry={uploadState.canRetry}
        canResume={uploadState.canResume}
        onpause={pauseUpload}
        onresume={resumeUpload}
        onminimize={toggleMinimizeUpload}
        oncancel={cancelUpload}
        onretry={retryUpload}
        onrefresh={refreshPage}
        onclose={closeUploadModal}
    />
</div>
