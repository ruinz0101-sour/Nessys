<script>
import { Link } from '@inertiajs/svelte';
import AdminLayout from '../../Layouts/AdminLayout.svelte';

let {
    summary = {
        total_files: 0,
        total_folders: 0,
        total_used_bytes: 0,
        trashed_count: 0,
        categories: {},
    },
    topUsers = [],
    raidPools = [],
} = $props();

function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

function categoryMeta(key) {
    if (key === 'image') {
        return {
            label: 'รูปภาพ',
            color: 'bg-amber-500',
            bg: 'bg-amber-50',
            border: 'border-amber-200',
            text: 'text-amber-700',
            icon: 'image',
        };
    }
    if (key === 'video') {
        return {
            label: 'วิดีโอ',
            color: 'bg-purple-500',
            bg: 'bg-purple-50',
            border: 'border-purple-200',
            text: 'text-purple-700',
            icon: 'video',
        };
    }
    if (key === 'document') {
        return {
            label: 'เอกสาร',
            color: 'bg-sky-500',
            bg: 'bg-sky-50',
            border: 'border-sky-200',
            text: 'text-sky-700',
            icon: 'file-text',
        };
    }
    return {
        label: 'ไฟล์ทั่วไป',
        color: 'bg-slate-400',
        bg: 'bg-slate-50',
        border: 'border-slate-200',
        text: 'text-slate-700',
        icon: 'archive',
    };
}

function raidBadge(state) {
    if (state === 'healthy') {
        return { label: 'ปกติ', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    if (state === 'degraded') {
        return { label: 'ผิดปกติ', cls: 'bg-amber-100 text-amber-800 border-amber-200' };
    }
    if (state === 'rebuilding') {
        return { label: 'กำลังสร้างใหม่', cls: 'bg-sky-100 text-sky-800 border-sky-200' };
    }
    if (state === 'failed') {
        return { label: 'ล้มเหลว', cls: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    return { label: 'ไม่ทราบสถานะ', cls: 'bg-slate-100 text-slate-800 border-slate-200' };
}

function smartBadge(smart) {
    if (smart === 'ok') {
        return { label: 'S.M.A.R.T. ปกติ', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    if (smart === 'warning') {
        return { label: 'S.M.A.R.T. เตือน', cls: 'bg-amber-50 text-amber-700 border-amber-200' };
    }
    if (smart === 'failing') {
        return { label: 'S.M.A.R.T. อันตราย', cls: 'bg-rose-50 text-rose-700 border-rose-200' };
    }
    return { label: 'S.M.A.R.T. N/A', cls: 'bg-slate-50 text-slate-700 border-slate-200' };
}
</script>

<AdminLayout title="แดชบอร์ดภาพรวมระบบ & ฮาร์ดแวร์">
    <div class="flex flex-col gap-6">
        <!-- 4 Metric Cards Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Used Storage -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 to-violet-500"></div>
                <div>
                    <div class="text-xs font-medium text-slate-500">พื้นที่จัดเก็บที่ใช้จริง</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1.5 tracking-tight">
                        {formatBytes(summary.total_used_bytes)}
                    </div>
                    <div class="text-[11px] text-emerald-600 font-medium mt-1">
                        รวมทุกแผนกและผู้ใช้งาน
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-100 border border-indigo-200/60 text-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                        <line x1="12" y1="22.08" x2="12" y2="12" />
                    </svg>
                </div>
            </div>

            <!-- Total Files -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-500 to-cyan-400"></div>
                <div>
                    <div class="text-xs font-medium text-slate-500">จำนวนไฟล์ทั้งหมด</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1.5 tracking-tight">
                        {summary.total_files.toLocaleString()} ไฟล์
                    </div>
                    <div class="text-[11px] text-slate-500 mt-1">
                        ในระบบจัดเก็บข้อมูล
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-50 to-cyan-100 border border-sky-200/60 text-sky-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <path d="M14 2v6h6" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <line x1="10" y1="9" x2="8" y2="9" />
                    </svg>
                </div>
            </div>

            <!-- Total Folders -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
                <div>
                    <div class="text-xs font-medium text-slate-500">จำนวนโฟลเดอร์</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1.5 tracking-tight">
                        {summary.total_folders.toLocaleString()} โฟลเดอร์
                    </div>
                    <div class="text-[11px] text-slate-500 mt-1">
                        ส่วนตัว / แผนก / ส่วนกลาง
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-100 border border-amber-200/60 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                    </svg>
                </div>
            </div>

            <!-- Trashed Items -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-between relative overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 to-pink-500"></div>
                <div>
                    <div class="text-xs font-medium text-slate-500">รายการในถังขยะ</div>
                    <div class="text-2xl font-bold text-slate-900 mt-1.5 tracking-tight">
                        {summary.trashed_count.toLocaleString()} รายการ
                    </div>
                    <div class="text-[11px] text-rose-600 font-medium mt-1">
                        ลบถาวรหลัง 30 วันอัตโนมัติ
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-rose-50 to-pink-100 border border-rose-200/60 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Hardware & RAID Monitoring Section -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-slate-800 to-slate-950 text-white flex items-center justify-center shadow-md shadow-slate-900/15">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="12" x2="2" y2="12" />
                            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                            <line x1="6" y1="16" x2="6.01" y2="16" />
                            <line x1="10" y1="16" x2="10.01" y2="16" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            สถานะ RAID Storage Pool และสุขภาพฮาร์ดดิสก์
                        </h2>
                        <p class="text-xs text-slate-500">
                            มอนิเตอร์ระดับฮาร์ดแวร์ อุณหภูมิ และค่า S.M.A.R.T. ของดิสก์แต่ละลูก
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">ตรวจเช็คล่าสุด:</span>
                    <span class="text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-full">
                        {raidPools[0]?.checked_at ? new Date(raidPools[0].checked_at).toLocaleTimeString('th-TH') : 'เรียลไทม์'}
                    </span>
                </div>
            </div>

            {#each raidPools as pool (pool.id)}
                {@const badge = raidBadge(pool.state)}
                {@const rawPercent = (pool.used_bytes / (pool.total_bytes || 1)) * 100}
                {@const percentLabel = pool.used_bytes > 0 && rawPercent < 0.01 ? '< 0.01' : rawPercent < 1 && pool.used_bytes > 0 ? rawPercent.toFixed(2) : Math.min(100, Math.round(rawPercent))}
                {@const barWidth = pool.used_bytes > 0 ? Math.max(1, Math.min(100, rawPercent)) : 0}
                <div class="bg-slate-50 rounded-xl p-5 border border-slate-200/80 flex flex-col gap-5">
                    <!-- Pool Info Header -->
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="px-2.5 py-1 rounded-md bg-indigo-600 text-white font-bold text-xs">
                                {pool.level}
                            </div>
                            <div>
                                <div class="font-bold text-sm text-slate-900">{pool.name}</div>
                                <div class="text-xs text-slate-500">ความจุรวม: {formatBytes(pool.total_bytes)}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold border {badge.cls}">
                                {badge.label}
                            </span>
                        </div>
                    </div>

                    <!-- Pool Storage Progress Bar -->
                    <div>
                        <div class="flex justify-between text-xs text-slate-600 mb-1.5 font-medium">
                            <span>ใช้ไป {formatBytes(pool.used_bytes)} ({percentLabel}%)</span>
                            <span>คงเหลือ {formatBytes(Math.max(0, pool.total_bytes - pool.used_bytes))}</span>
                        </div>
                        <div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden p-0.5">
                            <div
                                class="h-full rounded-full transition-all duration-300 {rawPercent >= 90 ? 'bg-rose-500' : rawPercent >= 75 ? 'bg-amber-500' : 'bg-emerald-500'}"
                                style="width: {barWidth}%"
                            ></div>
                        </div>
                    </div>

                    <!-- Disks Grid -->
                    <div>
                        <div class="text-xs font-bold text-slate-700 mb-3 uppercase tracking-wider">
                            ฮาร์ดดิสก์ในพูล ({pool.disks?.length ?? 0} ลูก)
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3.5">
                            {#each pool.disks as disk (disk.id)}
                                {@const sBadge = smartBadge(disk.smart)}
                                <div class="bg-white rounded-xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md hover:border-indigo-200 transition-all duration-200 flex flex-col justify-between gap-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono font-bold text-xs text-slate-800 bg-slate-100 px-2 py-0.5 rounded">
                                            {disk.device}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border {sBadge.cls}">
                                            {sBadge.label}
                                        </span>
                                    </div>

                                    <div>
                                        <div class="text-xs font-semibold text-slate-900 truncate" title={disk.model}>
                                            {disk.model}
                                        </div>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5 truncate">
                                            SN: {disk.serial} · {formatBytes(disk.size_bytes)}
                                        </div>
                                    </div>

                                    <!-- Disk Health Telemetry -->
                                    <div class="pt-2 border-t border-slate-100 grid grid-cols-3 gap-1 text-center">
                                        <div class="bg-slate-50 p-1.5 rounded">
                                            <div class="text-[10px] text-slate-500">อุณหภูมิ</div>
                                            <div class="text-xs font-bold {disk.temperature_c > 45 ? 'text-rose-600' : 'text-slate-700'}">
                                                {disk.temperature_c}°C
                                            </div>
                                        </div>
                                        <div class="bg-slate-50 p-1.5 rounded">
                                            <div class="text-[10px] text-slate-500">ชั่วโมงเปิด</div>
                                            <div class="text-xs font-bold text-slate-700 truncate">
                                                {disk.power_on_hours?.toLocaleString()} ชม.
                                            </div>
                                        </div>
                                        <div class="bg-slate-50 p-1.5 rounded">
                                            <div class="text-[10px] text-slate-500">Bad Sectors</div>
                                            <div class="text-xs font-bold {disk.reallocated_sectors > 0 ? 'text-amber-600' : 'text-emerald-600'}">
                                                {disk.reallocated_sectors}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                </div>
            {/each}
        </div>

        <!-- 2 Column Section: Storage by Categories & Top Storage Users -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Categories Breakdown (5 Cols) -->
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col gap-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold text-slate-900">ปริมาณข้อมูลแยกตามประเภท</h2>
                    <span class="text-xs text-slate-400">4 หมวดหมู่</span>
                </div>

                <div class="flex flex-col gap-3">
                    {#each Object.entries(summary.categories ?? {}) as [key, cat]}
                        {@const meta = categoryMeta(key)}
                        {@const catPercent = summary.total_used_bytes > 0 ? Math.round((cat.size_bytes / summary.total_used_bytes) * 100) : 0}
                        <div class="p-3.5 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition bg-slate-50/50 flex flex-col gap-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-800">{meta.label}</span>
                                <span class="font-mono text-slate-600">{formatBytes(cat.size_bytes)} ({catPercent}%)</span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 {meta.color}" style="width: {catPercent}%"></div>
                            </div>
                            <div class="text-[11px] text-slate-500 flex justify-between">
                                <span>จำนวน {cat.count?.toLocaleString() ?? 0} ไฟล์</span>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>

            <!-- Top 5 Storage Users (7 Cols) -->
            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col gap-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-slate-900">ผู้ใช้ที่ใช้พื้นที่มากที่สุด</h2>
                    </div>
                    <Link href="/admin/users" class="text-xs text-indigo-600 hover:text-indigo-700 hover:underline font-semibold">
                        ดูผู้ใช้ทั้งหมด &rarr;
                    </Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100 pb-2">
                                <th class="pb-2.5 font-medium">ผู้ใช้งาน</th>
                                <th class="pb-2.5 font-medium">แผนก</th>
                                <th class="pb-2.5 font-medium">พื้นที่ใช้งาน</th>
                                <th class="pb-2.5 font-medium text-right">จำนวนไฟล์</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each topUsers as u (u.id)}
                                {@const uPercent = u.quota_bytes > 0 ? Math.min(100, Math.round((u.used_bytes / u.quota_bytes) * 100)) : 0}
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3">
                                        <div class="flex items-center gap-2.5">
                                            {#if u.avatar_url}
                                                <img
                                                    src={u.avatar_url}
                                                    alt={u.name}
                                                    class="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0"
                                                />
                                            {:else}
                                                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0">
                                                    {u.name ? u.name.trim().charAt(0).toUpperCase() : '?'}
                                                </div>
                                            {/if}
                                            <div>
                                                <div class="font-bold text-slate-900">{u.name}</div>
                                                <div class="text-[11px] text-slate-500">{u.email}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 text-slate-600">
                                        {u.department_name ?? '—'}
                                    </td>
                                    <td class="py-2.5">
                                        <div class="font-mono text-slate-800">
                                            {formatBytes(u.used_bytes)} <span class="text-slate-400">/</span> {u.quota_bytes > 0 ? formatBytes(u.quota_bytes) : 'ไม่จำกัด'}
                                        </div>
                                        {#if u.quota_bytes > 0}
                                            <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden mt-1">
                                                <div class="h-full rounded-full {uPercent >= 90 ? 'bg-rose-500' : uPercent >= 75 ? 'bg-amber-500' : 'bg-indigo-500'}" style="width: {uPercent}%"></div>
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="py-2.5 text-right font-mono text-slate-700">
                                        {u.files_count?.toLocaleString() ?? 0}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
