<script>
import { router } from '@inertiajs/svelte';
import AdminLayout from '../../../Layouts/AdminLayout.svelte';

let {
    logs = [],
    users = [],
    actions = [],
    filters = {},
} = $props();

let userId = $state('');
let action = $state('');
let dateFrom = $state('');
let dateTo = $state('');
let search = $state('');
let copiedHash = $state(null);
let viewRawModal = $state(null);

$effect(() => {
    userId = filters.user_id !== undefined && filters.user_id !== null && filters.user_id !== ''
        ? String(filters.user_id)
        : '';
    action = filters.action ?? '';
    dateFrom = filters.date_from ?? '';
    dateTo = filters.date_to ?? '';
    search = filters.search ?? '';
});

function applyFilters(e) {
    e?.preventDefault();
    router.get('/admin/audit-logs', {
        user_id: userId || undefined,
        action: action || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        search: search || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    userId = '';
    action = '';
    dateFrom = '';
    dateTo = '';
    search = '';
    router.get('/admin/audit-logs');
}

function actionBadge(act) {
    switch (act) {
        case 'login':
            return {
                bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                dot: 'bg-emerald-500',
                label: 'เข้าสู่ระบบ',
            };
        case 'logout':
            return {
                bg: 'bg-slate-100 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
                label: 'ออกจากระบบ',
            };
        case 'login_failed':
            return {
                bg: 'bg-rose-50 text-rose-700 border-rose-200',
                dot: 'bg-rose-500',
                label: 'ล็อกอินล้มเหลว',
            };
        case 'upload':
            return {
                bg: 'bg-indigo-50 text-indigo-700 border-indigo-200',
                dot: 'bg-indigo-500',
                label: 'อัปโหลด',
            };
        case 'download':
            return {
                bg: 'bg-sky-50 text-sky-700 border-sky-200',
                dot: 'bg-sky-500',
                label: 'ดาวน์โหลด',
            };
        case 'share':
            return {
                bg: 'bg-blue-50 text-blue-700 border-blue-200',
                dot: 'bg-blue-500',
                label: 'แชร์ไฟล์',
            };
        case 'unshare':
            return {
                bg: 'bg-rose-50 text-rose-700 border-rose-200',
                dot: 'bg-rose-500',
                label: 'ยกเลิกแชร์',
            };
        case 'delete':
            return {
                bg: 'bg-rose-50 text-rose-700 border-rose-200',
                dot: 'bg-rose-500',
                label: 'ลบไฟล์',
            };
        case 'restore':
            return {
                bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                dot: 'bg-emerald-500',
                label: 'กู้คืนไฟล์',
            };
        case 'request':
            return {
                bg: 'bg-amber-50 text-amber-700 border-amber-200',
                dot: 'bg-amber-500',
                label: 'ขอสิทธิ์ไฟล์',
            };
        case 'approve':
            return {
                bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                dot: 'bg-emerald-500',
                label: 'อนุมัติคำขอ',
            };
        case 'deny':
            return {
                bg: 'bg-rose-50 text-rose-700 border-rose-200',
                dot: 'bg-rose-500',
                label: 'ปฏิเสธคำขอ',
            };
        case 'rename':
            return {
                bg: 'bg-amber-50 text-amber-700 border-amber-200',
                dot: 'bg-amber-500',
                label: 'เปลี่ยนชื่อ',
            };
        case 'move':
            return {
                bg: 'bg-amber-50 text-amber-700 border-amber-200',
                dot: 'bg-amber-500',
                label: 'ย้ายตำแหน่ง',
            };
        case 'admin_access':
            return {
                bg: 'bg-purple-50 text-purple-700 border-purple-200',
                dot: 'bg-purple-500',
                label: 'จัดการระบบ',
            };
        default:
            return {
                bg: 'bg-slate-50 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
                label: act,
            };
    }
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) {
        return '0 B';
    }
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function copyHash(hash) {
    if (!hash) {
        return;
    }
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(hash).then(() => {
            copiedHash = hash;
            setTimeout(() => {
                if (copiedHash === hash) {
                    copiedHash = null;
                }
            }, 2000);
        });
    }
}

function formatDateTime(iso) {
    if (!iso) {
        return '-';
    }
    const d = new Date(iso);
    return {
        date: d.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' }),
        time: d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
    };
}
</script>

<AdminLayout title="Audit Logs - ประวัติการใช้งาน">
    <div class="flex flex-col gap-6">
        <!-- Page Title & Integrity Card -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Audit Logs & Activity Trail</h1>
                <p class="text-sm text-slate-500 mt-1">
                    บันทึกประวัติการกระทำและความปลอดภัยทั้งหมดในระบบเพื่อการตรวจสอบ (Append-Only Immutable Logs)
                </p>
            </div>

            <!-- Integrity pill -->
            <div class="flex items-center gap-3 bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-xs self-start md:self-auto">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs font-semibold text-slate-900">บันทึกแบบ Append-Only</span>
                    </div>
                    <div class="text-[11px] text-slate-500">
                        {logs.length} รายการที่ตรงกับเงื่อนไข
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-5">
            <form onsubmit={applyFilters} class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="lg:col-span-1">
                    <label for="filter_user" class="block text-xs font-semibold text-slate-600 mb-1.5">ผู้ปฏิบัติงาน</label>
                    <select
                        id="filter_user"
                        bind:value={userId}
                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                        <option value="">ทุกคน</option>
                        {#each users as u (u.id)}
                            <option value={String(u.id)}>{u.name}</option>
                        {/each}
                    </select>
                </div>

                <div class="lg:col-span-1">
                    <label for="filter_action" class="block text-xs font-semibold text-slate-600 mb-1.5">การกระทำ</label>
                    <select
                        id="filter_action"
                        bind:value={action}
                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    >
                        <option value="">ทุกกิจกรรม</option>
                        {#each actions as act (act)}
                            <option value={act}>{actionBadge(act).label}</option>
                        {/each}
                    </select>
                </div>

                <div class="lg:col-span-1">
                    <label for="filter_from" class="block text-xs font-semibold text-slate-600 mb-1.5">ตั้งแต่วันที่</label>
                    <input
                        id="filter_from"
                        type="date"
                        bind:value={dateFrom}
                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    />
                </div>

                <div class="lg:col-span-1">
                    <label for="filter_to" class="block text-xs font-semibold text-slate-600 mb-1.5">ถึงวันที่</label>
                    <input
                        id="filter_to"
                        type="date"
                        bind:value={dateTo}
                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    />
                </div>

                <div class="lg:col-span-1">
                    <label for="filter_search" class="block text-xs font-semibold text-slate-600 mb-1.5">ค้นหาไฟล์หรือ IP</label>
                    <input
                        id="filter_search"
                        type="text"
                        bind:value={search}
                        placeholder="ชื่อไฟล์, IP..."
                        class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                    />
                </div>

                <div class="lg:col-span-1 flex items-end gap-2">
                    <button
                        type="submit"
                        class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                        ค้นหา
                    </button>
                    <button
                        type="button"
                        onclick={resetFilters}
                        title="ล้างตัวกรอง"
                        class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-lg transition cursor-pointer"
                    >
                        ล้าง
                    </button>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                    <h2 class="text-base font-bold text-slate-800">
                        รายการบันทึกกิจกรรม ({logs.length} รายการ)
                    </h2>
                </div>
                <span class="text-xs text-slate-400">เรียงตามลำดับเวลาล่าสุด</span>
            </div>

            {#if logs.length === 0}
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <div class="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                        <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-700">ไม่พบรายการบันทึกที่ตรงกับเงื่อนไข</h3>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm">
                        ลองเปลี่ยนตัวกรอง วันที่ หรือคำค้นหา เพื่อค้นหาประวัติการใช้งาน
                    </p>
                    <button
                        type="button"
                        onclick={resetFilters}
                        class="mt-4 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-sm font-semibold rounded-lg transition cursor-pointer"
                    >
                        แสดงรายการทั้งหมด
                    </button>
                </div>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3.5 px-4">วัน-เวลา</th>
                                <th class="py-3.5 px-4">ผู้ปฏิบัติงาน</th>
                                <th class="py-3.5 px-4">การกระทำ</th>
                                <th class="py-3.5 px-4">ไฟล์ / วัตถุที่เกี่ยวข้อง</th>
                                <th class="py-3.5 px-4">IP Address</th>
                                <th class="py-3.5 px-4">รายละเอียดเพิ่มเติม</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            {#each logs as log (log.id)}
                                {@const badge = actionBadge(log.action)}
                                {@const dt = formatDateTime(log.created_at)}
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <div class="font-medium text-slate-800">{dt.date}</div>
                                        <div class="text-xs text-slate-400 font-mono">{dt.time}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0">
                                                {log.user_name ? log.user_name.charAt(0).toUpperCase() : '?'}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-slate-900 truncate">{log.user_name || 'System / Guest'}</div>
                                                {#if log.user_email}
                                                    <div class="text-xs text-slate-400 truncate">{log.user_email}</div>
                                                {/if}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border {badge.bg}">
                                            <span class="w-1.5 h-1.5 rounded-full {badge.dot}"></span>
                                            {badge.label}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 max-w-xs">
                                        {#if log.node_name}
                                            <div class="flex items-center gap-1.5 text-slate-800 truncate" title={log.node_name}>
                                                <svg class="w-4 h-4 text-slate-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                                                    <polyline points="14 2 14 8 20 8" />
                                                </svg>
                                                <span class="truncate">{log.node_name}</span>
                                            </div>
                                        {:else}
                                            <span class="text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        {#if log.ip}
                                            <span class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">
                                                {log.ip}
                                            </span>
                                        {:else}
                                            <span class="text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-3 px-4 max-w-md text-xs">
                                        {#if !log.meta}
                                            <span class="text-slate-400">-</span>
                                        {:else if log.action === 'login_failed'}
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                {#if log.meta.failed_login_count !== undefined}
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 font-medium">
                                                        <svg class="w-3 h-3 text-rose-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <line x1="12" y1="8" x2="12" y2="12" />
                                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                                        </svg>
                                                        ใส่รหัสผิดครั้งที่ {log.meta.failed_login_count}/5
                                                    </span>
                                                {/if}
                                                {#if log.meta.locked}
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-600 text-white font-medium text-[11px] animate-pulse">
                                                        ล็อกบัญชี 15 นาที
                                                    </span>
                                                {:else if log.meta.failed_login_count !== undefined}
                                                    <span class="text-[11px] text-slate-400">(ยังไม่ล็อก)</span>
                                                {/if}
                                                {#if log.meta.reason === 'user_not_found'}
                                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                                        ไม่พบบัญชี {log.meta.email ? `(${log.meta.email})` : ''}
                                                    </span>
                                                {:else if log.meta.reason === 'account_inactive'}
                                                    <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">
                                                        บัญชีนี้ถูกปิดใช้งานอยู่
                                                    </span>
                                                {/if}
                                            </div>
                                        {:else if log.action === 'upload'}
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                {#if log.meta.type === 'folder'}
                                                    <span class="px-2 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-200 font-medium">
                                                        สร้างโฟลเดอร์ใหม่
                                                    </span>
                                                {:else}
                                                    {#if log.meta.size_bytes !== undefined}
                                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium font-mono text-[11px]">
                                                            {formatBytes(log.meta.size_bytes)}
                                                        </span>
                                                    {/if}
                                                    {#if log.meta.sha256}
                                                        <button
                                                            type="button"
                                                            onclick={() => copyHash(log.meta.sha256)}
                                                            title="คลิกเพื่อคัดลอก SHA-256 Checksum: {log.meta.sha256}"
                                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 font-mono text-[11px] transition cursor-pointer"
                                                        >
                                                            <span class="text-slate-400 font-bold">SHA:</span>
                                                            <span>{log.meta.sha256.substring(0, 8)}...</span>
                                                            {#if copiedHash === log.meta.sha256}
                                                                <span class="text-emerald-600 font-sans font-bold text-[10px]">คัดลอกแล้ว!</span>
                                                            {:else}
                                                                <svg class="w-3 h-3 text-slate-400 hover:text-slate-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
                                                                    <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
                                                                </svg>
                                                            {/if}
                                                        </button>
                                                    {/if}
                                                {/if}
                                                {#if log.meta.root_type}
                                                    <span class="text-[11px] text-slate-400">
                                                        ({log.meta.root_type === 'public' ? 'ส่วนกลาง' : log.meta.root_type === 'department' ? 'แผนก' : 'ส่วนตัว'})
                                                    </span>
                                                {/if}
                                            </div>
                                        {:else if log.action === 'rename'}
                                            <div class="text-slate-700 text-xs">
                                                {#if log.meta.old_name}
                                                    เดิม: <span class="line-through text-slate-400">{log.meta.old_name}</span> &rarr;
                                                    <span class="font-medium text-slate-900">{log.meta.new_name}</span>
                                                {:else}
                                                    <span class="font-mono text-slate-500">{JSON.stringify(log.meta)}</span>
                                                {/if}
                                            </div>
                                        {:else if log.action === 'admin_access'}
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                {#if log.meta.sub_action === 'create_user'}
                                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">
                                                        สร้างผู้ใช้ {log.meta.target_email ? `(${log.meta.target_email})` : ''}
                                                    </span>
                                                {:else if log.meta.sub_action === 'update_user'}
                                                    <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 font-medium">
                                                        แก้ไขผู้ใช้ (ID #{log.meta.target_user_id})
                                                    </span>
                                                {:else if log.meta.sub_action === 'activate_user'}
                                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">
                                                        เปิดใช้งานบัญชี
                                                    </span>
                                                {:else if log.meta.sub_action === 'deactivate_user'}
                                                    <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 font-medium">
                                                        ระงับบัญชีผู้ใช้
                                                    </span>
                                                {:else if log.meta.sub_action === 'update_quota'}
                                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 font-medium">
                                                        ปรับโควต้าพื้นที่
                                                    </span>
                                                {:else if log.meta.sub_action === 'reset_password'}
                                                    <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 font-medium">
                                                        รีเซ็ตรหัสผ่าน
                                                    </span>
                                                {:else if log.meta.sub_action === 'create_department'}
                                                    <span class="px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 font-medium">
                                                        สร้างแผนก: {log.meta.name || ''}
                                                    </span>
                                                {:else if log.meta.sub_action === 'update_department'}
                                                    <span class="px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 font-medium">
                                                        แก้ไขแผนก (ID #{log.meta.target_department_id})
                                                    </span>
                                                {:else}
                                                    <span class="font-mono text-slate-500 text-[11px]">{JSON.stringify(log.meta)}</span>
                                                {/if}
                                            </div>
                                        {:else}
                                            <!-- Generic Fallback with button to inspect full JSON -->
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono text-xs text-slate-500 truncate max-w-[200px]" title={JSON.stringify(log.meta)}>
                                                    {JSON.stringify(log.meta)}
                                                </span>
                                                <button
                                                    type="button"
                                                    onclick={() => (viewRawModal = log.meta)}
                                                    class="text-[10px] text-indigo-600 hover:underline shrink-0 cursor-pointer"
                                                >
                                                    [ดูเต็ม]
                                                </button>
                                            </div>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}
        </div>
    </div>

    <!-- Modal: ดู Raw JSON Metadata แบบเต็ม -->
    {#if viewRawModal}
        <div class="fixed inset-0 z-50 bg-black/45 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-slate-200 max-w-lg w-full p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                    <h3 class="text-base font-bold text-slate-900">Telemetry & Metadata รายละเอียดดิบ</h3>
                    <button
                        type="button"
                        onclick={() => (viewRawModal = null)}
                        class="text-slate-400 hover:text-slate-600 cursor-pointer"
                    >
                        ✕
                    </button>
                </div>
                <pre class="bg-slate-900 text-emerald-400 font-mono text-xs p-4 rounded-lg overflow-x-auto max-h-96">{JSON.stringify(viewRawModal, null, 2)}</pre>
                <div class="flex justify-end pt-4">
                    <button
                        type="button"
                        onclick={() => (viewRawModal = null)}
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-semibold cursor-pointer"
                    >
                        ปิดหน้าต่าง
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
