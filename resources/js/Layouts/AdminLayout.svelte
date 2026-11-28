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

let { title = 'แผงควบคุมผู้ดูแลระบบ', children } = $props();

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

function userInitial(name) {
    if (!name) {
        return 'A';
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

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-100 via-slate-50 to-indigo-50/40 text-slate-900 font-sans flex flex-col md:flex-row antialiased">
    <!-- Midnight Indigo Sidebar -->
    <aside class="w-full md:w-68 bg-gradient-to-b from-[#0F172A] via-[#111827] to-[#0B1120] text-slate-200 flex flex-col shrink-0 border-b md:border-b-0 md:border-r border-indigo-950/60 z-30 shadow-2xl shadow-indigo-950/20">
        <!-- Brand Header -->
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-600 text-white flex items-center justify-center font-bold text-sm shadow-lg shadow-indigo-500/30">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
                        <line x1="6" y1="6" x2="6.01" y2="6" />
                        <line x1="6" y1="18" x2="6.01" y2="18" />
                    </svg>
                </div>
                <div>
                    <div class="font-bold text-sm text-white tracking-tight">
                        NAS Admin
                    </div>
                    <div class="text-[11px] text-slate-400">ศูนย์ควบคุมผู้ดูแลระบบ</div>
                </div>
            </div>

            <button
                type="button"
                onclick={() => (mobileNavOpen = !mobileNavOpen)}
                class="md:hidden p-2 rounded-lg border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800 transition cursor-pointer"
                aria-label="เปิดเมนูผู้ดูแล"
            >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12" />
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="3" y1="18" x2="21" y2="18" />
                </svg>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="{mobileNavOpen ? 'flex' : 'hidden'} md:flex p-3 flex-1 flex-col gap-1.5 text-xs">
            <div class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                การจัดการระบบ
            </div>

            <Link
                href="/admin/dashboard"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl.startsWith('/admin/dashboard') ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl.startsWith('/admin/dashboard') ? 'bg-white/15 text-white' : 'bg-slate-800/80 text-indigo-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="9" x="3" y="3" rx="1" />
                        <rect width="7" height="5" x="14" y="3" rx="1" />
                        <rect width="7" height="9" x="14" y="12" rx="1" />
                        <rect width="7" height="5" x="3" y="16" rx="1" />
                    </svg>
                </span>
                <span>แดชบอร์ด & ฮาร์ดแวร์ดิสก์</span>
            </Link>

            <Link
                href="/admin/users"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl.startsWith('/admin/users') ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl.startsWith('/admin/users') ? 'bg-white/15 text-white' : 'bg-slate-800/80 text-indigo-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </span>
                <span>ผู้ใช้งานและแผนก</span>
            </Link>

            <Link
                href="/admin/audit-logs"
                class="px-3 py-2.5 rounded-xl flex items-center gap-3 transition-all duration-200 {currentUrl.startsWith('/admin/audit-logs') ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'}"
            >
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 {currentUrl.startsWith('/admin/audit-logs') ? 'bg-white/15 text-white' : 'bg-slate-800/80 text-indigo-400'}">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                </span>
                <span>ประวัติความปลอดภัย</span>
            </Link>

            <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                พื้นที่ผู้ใช้งาน
            </div>

            <Link
                href="/files"
                class="px-3 py-2.5 rounded-xl flex items-center justify-between gap-3 text-slate-300 bg-slate-800/40 border border-slate-700/50 hover:bg-slate-800 hover:border-amber-400/40 hover:text-white transition-all duration-200"
            >
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                        </svg>
                    </span>
                    <span class="font-medium">ไปยังคลังไฟล์</span>
                </div>
                <span class="text-slate-500">&rarr;</span>
            </Link>
        </nav>

        <!-- System Quick Status Card in Sidebar -->
        <div class="p-3.5 m-3 rounded-xl bg-slate-800/50 border border-slate-700/60 text-xs flex flex-col gap-1.5">
            <div class="flex items-center justify-between text-[11px] text-slate-300">
                <span class="font-medium">เซิร์ฟเวอร์หลัก</span>
                <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    ออนไลน์ปกติ
                </span>
            </div>
            <div class="text-[10px] text-slate-400 font-mono">
                Linux RAID · mdadm subsystem
            </div>
        </div>
    </aside>

    <!-- Main Content Container -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Frosted Glass Topbar -->
        <header class="sticky top-0 z-20 h-16 bg-white/85 backdrop-blur-md border-b border-slate-200/80 px-6 flex items-center justify-between shadow-[0_1px_10px_rgba(15,23,42,0.03)]">
            <div class="flex items-center gap-3 min-w-0">
                <h1 class="text-base font-bold text-slate-900 tracking-tight truncate">{title}</h1>
                <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-[11px] text-emerald-700 font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>ระบบฮาร์ดแวร์เสถียร</span>
                </div>
            </div>

            {#if user}
                <div class="flex items-center gap-3.5">
                    <!-- Notifications -->
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
                                <span class="absolute top-1 right-1 px-1.5 py-0.2 bg-rose-600 text-white text-[10px] font-bold rounded-full shadow-xs">
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
                                            class="text-xs text-indigo-600 hover:underline cursor-pointer font-medium"
                                        >
                                            อ่านทั้งหมด
                                        </button>
                                    {/if}
                                </div>
                                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 text-xs">
                                    {#if (notifications.items ?? []).length === 0}
                                        <div class="p-6 text-center text-slate-400">
                                            ไม่มีการแจ้งเตือน
                                        </div>
                                    {:else}
                                        {#each notifications.items as n (n.id)}
                                            <button
                                                type="button"
                                                onclick={() => markOneRead(n)}
                                                class="w-full text-left p-3.5 hover:bg-slate-50 transition flex flex-col gap-1 cursor-pointer {!n.read_at ? 'bg-indigo-50/50' : ''}"
                                            >
                                                <div class="font-medium text-slate-800">
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

                    <!-- Admin Profile & Avatar -->
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
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-bold text-xs flex items-center justify-center shadow-xs shrink-0">
                                {userInitial(user.name)}
                            </div>
                        {/if}
                        <div class="hidden sm:block text-left">
                            <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                                <span>{user.name}</span>
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-semibold bg-indigo-100 text-indigo-700">
                                    ผู้ดูแลระบบ
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {user.email}
                            </div>
                        </div>
                    </Link>

                    <!-- Logout Button -->
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 bg-white text-slate-700 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition cursor-pointer shadow-2xs"
                    >
                        ออกจากระบบ
                    </Link>
                </div>
            {/if}
        </header>

        <!-- Flash Banners & Content -->
        <main class="p-6 flex-1 max-w-7xl w-full mx-auto animate-fade-up">
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
        onpause={pauseUpload}
        onresume={resumeUpload}
        onminimize={toggleMinimizeUpload}
        oncancel={cancelUpload}
        onretry={retryUpload}
        onrefresh={refreshPage}
        onclose={closeUploadModal}
    />
</div>
