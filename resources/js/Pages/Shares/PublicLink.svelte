<script>
import { Link, useForm } from '@inertiajs/svelte';

let {
    status = 'active', // 'active' | 'not_found' | 'revoked' | 'expired' | 'limit_reached'
    token = '',
    requiresPassword = false,
    shareLink = null,
    node = null,
    currentFolder = null,
    breadcrumbs = [],
    children = [],
} = $props();

const unlockForm = useForm({
    password: '',
});

let previewingChild = $state(null);

function submitUnlock(e) {
    e.preventDefault();
    unlockForm.post(`/s/${token}/unlock`, {
        onFinish: () => unlockForm.reset('password'),
    });
}

function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

function formatDate(iso) {
    if (!iso) {
        return 'ไม่ระบุ';
    }
    return new Date(iso).toLocaleString('th-TH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getPreviewKind(item) {
    if (!item) {
        return 'none';
    }
    const mime = (item.mime_type || '').toLowerCase();
    const ext = (item.extension || item.name?.split('.').pop() || '').toLowerCase();

    if (mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
        return 'image';
    }
    if (mime.startsWith('video/') || ['mp4', 'webm', 'ogg', 'mov'].includes(ext)) {
        return 'video';
    }
    if (mime.startsWith('audio/') || ['mp3', 'wav', 'm4a', 'flac'].includes(ext)) {
        return 'audio';
    }
    if (mime === 'application/pdf' || ext === 'pdf') {
        return 'pdf';
    }
    return 'none';
}

const rootPreviewKind = $derived(node?.type === 'file' ? getPreviewKind(node) : 'none');
const childPreviewKind = $derived(previewingChild ? getPreviewKind(previewingChild) : 'none');

function fileDownloadUrl(fileUuid = null) {
    if (fileUuid) {
        return `/s/${token}/download?file=${encodeURIComponent(fileUuid)}`;
    }
    return `/s/${token}/download`;
}

function filePreviewUrl(fileUuid = null) {
    if (fileUuid) {
        return `/s/${token}/preview?file=${encodeURIComponent(fileUuid)}`;
    }
    return `/s/${token}/preview`;
}
</script>

<div class="min-h-[100dvh] bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-slate-900 font-sans antialiased flex flex-col">
    <!-- Top Header -->
    <header class="w-full border-b border-white/10 bg-slate-950/40 backdrop-blur-md">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-600 text-white font-bold text-sm flex items-center justify-center shadow-md shadow-orange-500/25">
                    NAS
                </div>
                <div>
                    <div class="text-sm font-bold text-white tracking-tight">CloudStorage NAS</div>
                    <div class="text-[11px] text-slate-400">ลิงก์แชร์ไฟล์ปลอดภัย</div>
                </div>
            </div>

            <Link
                href="/login"
                class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-200 border border-white/15 hover:bg-white/10 transition"
            >
                เข้าสู่ระบบ
            </Link>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6">
        {#if status !== 'active'}
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-8 text-center animate-fade-up">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mb-4">
                    <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                </div>
                <h1 class="text-base font-bold text-slate-900">
                    {#if status === 'revoked'}
                        ลิงก์แชร์นี้ถูกยกเลิกแล้ว
                    {:else if status === 'expired'}
                        ลิงก์แชร์นี้หมดอายุแล้ว
                    {:else if status === 'limit_reached'}
                        ลิงก์แชร์นี้ครบจำนวนครั้งดาวน์โหลดแล้ว
                    {:else}
                        ไม่พบลิงก์แชร์ที่คุณต้องการ
                    {/if}
                </h1>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                    {#if status === 'revoked'}
                        เจ้าของไฟล์ได้เพิกถอนสิทธิ์การเข้าถึงลิงก์นี้แล้ว กรุณาติดต่อผู้แชร์เพื่อขอลิงก์ใหม่
                    {:else if status === 'expired'}
                        ลิงก์นี้เกินระยะเวลาที่กำหนดไว้แล้ว กรุณาติดต่อผู้แชร์เพื่อขอสร้างลิงก์ใหม่
                    {:else if status === 'limit_reached'}
                        ลิงก์นี้ถูกดาวน์โหลดครบตามโควต้าที่ผู้แชร์กำหนดไว้แล้ว
                    {:else}
                        ลิงก์อาจไม่ถูกต้อง หรือไฟล์ต้นทางถูกลบออกจากระบบแล้ว
                    {/if}
                </p>
            </div>
        {:else if requiresPassword}
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-7 animate-fade-up">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-sm font-bold text-slate-900">ลิงก์นี้มีการป้องกันด้วยรหัสผ่าน</h1>
                        <p class="text-xs text-slate-500 truncate mt-0.5">{node?.name}</p>
                    </div>
                </div>

                <form onsubmit={submitUnlock} class="flex flex-col gap-4">
                    <div>
                        <label for="share_link_password" class="block text-xs font-semibold text-slate-700 mb-1.5">กรอกรหัสผ่านเพื่อเข้าถึงไฟล์</label>
                        <input
                            id="share_link_password"
                            type="password"
                            bind:value={unlockForm.password}
                            required
                            placeholder="รหัสผ่านของลิงก์แชร์"
                            class="input-trust text-sm py-2.5"
                        />
                        {#if unlockForm.errors.password}
                            <p class="mt-1.5 text-xs text-rose-600 font-medium">{unlockForm.errors.password}</p>
                        {/if}
                    </div>
                    <button
                        type="submit"
                        disabled={unlockForm.processing}
                        class="btn-primary w-full py-2.5 text-sm"
                    >
                        {unlockForm.processing ? 'กำลังตรวจสอบ...' : 'ปลดล็อกเพื่อเปิดดูไฟล์'}
                    </button>
                </form>
            </div>
        {:else if node?.type === 'file'}
            <!-- Single File Public View -->
            <div class="w-full max-w-3xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden animate-fade-up">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-11 h-11 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-base font-bold text-slate-900 truncate" title={node.name}>{node.name}</h1>
                            <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                                <span class="font-mono font-semibold text-slate-700">{formatBytes(node.size_bytes)}</span>
                                {#if shareLink?.shared_by_name}
                                    <span>แชร์โดย {shareLink.shared_by_name}</span>
                                {/if}
                                {#if shareLink?.expires_at}
                                    <span>หมดอายุ {formatDate(shareLink.expires_at)}</span>
                                {/if}
                                {#if shareLink?.max_downloads}
                                    <span>ดาวน์โหลดแล้ว {shareLink.download_count}/{shareLink.max_downloads} ครั้ง</span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <a
                        href={fileDownloadUrl()}
                        class="btn-primary text-xs px-4 py-2.5 shrink-0"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        <span>ดาวน์โหลดไฟล์</span>
                    </a>
                </div>

                <!-- Inline File Preview -->
                <div class="p-6 bg-slate-100/60 flex items-center justify-center min-h-[260px]">
                    {#if rootPreviewKind === 'image'}
                        <img
                            src={filePreviewUrl()}
                            alt={node.name}
                            class="max-h-[65vh] max-w-full rounded-xl shadow-md object-contain bg-white"
                        />
                    {:else if rootPreviewKind === 'video'}
                        <video
                            src={filePreviewUrl()}
                            controls
                            class="max-h-[65vh] w-full rounded-xl shadow-md bg-black"
                        >
                            <track kind="captions" />
                        </video>
                    {:else if rootPreviewKind === 'audio'}
                        <div class="w-full max-w-md bg-white p-6 rounded-2xl border border-slate-200 shadow-xs text-center">
                            <p class="text-xs font-semibold text-slate-700 mb-3 truncate">{node.name}</p>
                            <audio src={filePreviewUrl()} controls class="w-full"></audio>
                        </div>
                    {:else if rootPreviewKind === 'pdf'}
                        <iframe
                            src={filePreviewUrl()}
                            title={node.name}
                            class="w-full h-[65vh] rounded-xl border border-slate-200 bg-white"
                        ></iframe>
                    {:else}
                        <div class="text-center py-8">
                            <div class="w-16 h-16 mx-auto rounded-2xl bg-white border border-slate-200 text-slate-400 flex items-center justify-center mb-3 shadow-xs">
                                <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-800">{node.name}</p>
                            <p class="text-xs text-slate-500 mt-1">ไม่รองรับการแสดงตัวอย่างไฟล์ประเภทนี้ในเบราว์เซอร์ กดปุ่มด้านบนเพื่อดาวน์โหลด</p>
                        </div>
                    {/if}
                </div>
            </div>
        {:else if node?.type === 'folder'}
            <!-- Folder Public View -->
            <div class="w-full max-w-4xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden animate-fade-up">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-base font-bold text-slate-900 truncate">{currentFolder?.name ?? node.name}</h1>
                            <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                                {#if shareLink?.shared_by_name}
                                    <span>แชร์โดย {shareLink.shared_by_name}</span>
                                {/if}
                                {#if shareLink?.expires_at}
                                    <span>หมดอายุ {formatDate(shareLink.expires_at)}</span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <!-- Breadcrumbs -->
                    {#if breadcrumbs.length > 1}
                        <nav class="flex items-center flex-wrap gap-1.5 text-xs">
                            {#each breadcrumbs as crumb, idx (crumb.uuid)}
                                {#if idx > 0}
                                    <span class="text-slate-400">/</span>
                                {/if}
                                <Link
                                    href={idx === 0 ? `/s/${token}` : `/s/${token}?folder=${crumb.uuid}`}
                                    class="font-semibold {idx === breadcrumbs.length - 1 ? 'text-slate-900' : 'text-sky-600 hover:underline'}"
                                >
                                    {crumb.name}
                                </Link>
                            {/each}
                        </nav>
                    {/if}
                </div>

                {#if children.length === 0}
                    <div class="p-12 text-center text-xs text-slate-500">
                        ไม่มีไฟล์หรือโฟลเดอร์ภายในโฟลเดอร์นี้
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-2.5 px-5">ชื่อ</th>
                                    <th class="py-2.5 px-4">ขนาด</th>
                                    <th class="py-2.5 px-5 text-right">การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each children as item (item.uuid)}
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-3 px-5">
                                            {#if item.type === 'folder'}
                                                <Link
                                                    href={`/s/${token}?folder=${item.uuid}`}
                                                    class="font-bold text-slate-900 hover:text-sky-600 hover:underline flex items-center gap-2"
                                                >
                                                    <svg class="w-4 h-4 text-amber-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z" />
                                                    </svg>
                                                    <span>{item.name}</span>
                                                </Link>
                                            {:else}
                                                <div class="flex items-center gap-2 font-semibold text-slate-900">
                                                    <svg class="w-4 h-4 text-sky-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                        <polyline points="14 2 14 8 20 8" />
                                                    </svg>
                                                    <span>{item.name}</span>
                                                </div>
                                            {/if}
                                        </td>
                                        <td class="py-3 px-4 font-mono text-slate-500">
                                            {item.type === 'folder' ? '—' : formatBytes(item.size_bytes)}
                                        </td>
                                        <td class="py-3 px-5 text-right">
                                            {#if item.type === 'folder'}
                                                <Link
                                                    href={`/s/${token}?folder=${item.uuid}`}
                                                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 transition"
                                                >
                                                    เปิดโฟลเดอร์
                                                </Link>
                                            {:else}
                                                <div class="inline-flex items-center gap-1.5">
                                                    {#if getPreviewKind(item) !== 'none'}
                                                        <button
                                                            type="button"
                                                            onclick={() => (previewingChild = item)}
                                                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition cursor-pointer"
                                                        >
                                                            เปิดดู
                                                        </button>
                                                    {/if}
                                                    <a
                                                        href={fileDownloadUrl(item.uuid)}
                                                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 transition"
                                                    >
                                                        ดาวน์โหลด
                                                    </a>
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
        {/if}
    </main>

    <!-- Child File Preview Modal (for shared folder) -->
    {#if previewingChild}
        <div class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-3xl w-full overflow-hidden shadow-2xl animate-fade-up">
                <div class="px-5 py-3.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between gap-3">
                    <div class="font-bold text-sm text-slate-900 truncate">{previewingChild.name}</div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a
                            href={fileDownloadUrl(previewingChild.uuid)}
                            class="btn-primary text-xs py-1.5 px-3"
                        >
                            ดาวน์โหลด
                        </a>
                        <button
                            type="button"
                            onclick={() => (previewingChild = null)}
                            class="btn-secondary text-xs py-1.5 px-3"
                        >
                            ปิด
                        </button>
                    </div>
                </div>
                <div class="p-4 bg-slate-100 flex items-center justify-center max-h-[75vh] overflow-auto">
                    {#if childPreviewKind === 'image'}
                        <img src={filePreviewUrl(previewingChild.uuid)} alt={previewingChild.name} class="max-h-[68vh] max-w-full rounded-xl object-contain" />
                    {:else if childPreviewKind === 'video'}
                        <video src={filePreviewUrl(previewingChild.uuid)} controls class="max-h-[68vh] w-full rounded-xl bg-black">
                            <track kind="captions" />
                        </video>
                    {:else if childPreviewKind === 'audio'}
                        <audio src={filePreviewUrl(previewingChild.uuid)} controls class="w-full max-w-md"></audio>
                    {:else if childPreviewKind === 'pdf'}
                        <iframe src={filePreviewUrl(previewingChild.uuid)} title={previewingChild.name} class="w-full h-[68vh] rounded-xl bg-white"></iframe>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</div>
