<script>
let {
    file = null,
    onclose = () => {},
} = $props();

function formatBytes(bytes) {
    if (!bytes || bytes === 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

const ext = $derived((file?.extension || file?.name?.split('.').pop() || '').toLowerCase());
const mime = $derived((file?.mime_type || '').toLowerCase());

const isImage = $derived(
    mime.startsWith('image/') ||
    ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'ico'].includes(ext)
);

const isPdf = $derived(
    mime === 'application/pdf' || ext === 'pdf'
);

const isVideo = $derived(
    mime.startsWith('video/') ||
    ['mp4', 'webm', 'ogg', 'mov'].includes(ext)
);

const isAudio = $derived(
    mime.startsWith('audio/') ||
    ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac'].includes(ext)
);

const isText = $derived(
    mime.startsWith('text/') ||
    ['txt', 'log', 'csv', 'json', 'xml', 'md', 'html', 'css', 'js', 'sql', 'sh'].includes(ext)
);

const isPreviewable = $derived(isImage || isPdf || isVideo || isAudio || isText);

function handleKeydown(e) {
    if (e.key === 'Escape') {
        onclose();
    }
}
</script>

<svelte:window onkeydown={handleKeydown} />

{#if file}
    <div
        class="fixed inset-0 z-50 bg-[#131921]/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-label="ตัวอย่างไฟล์"
    >
        <div class="bg-white rounded-[3px] border border-[#DDDDDD] shadow-2xl flex flex-col w-full max-w-5xl max-h-[92vh] overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="bg-[#232F3E] text-white px-4 py-3 flex items-center justify-between gap-3 border-b border-slate-700 shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-[3px] bg-[#131921] border border-slate-700 flex items-center justify-center text-[#FF9900] shrink-0">
                        {#if isImage}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                                <circle cx="9" cy="9" r="2" />
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                            </svg>
                        {:else if isPdf}
                            <svg class="w-4 h-4 text-[#FF4444]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg>
                        {:else if isVideo}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="3" rx="2" />
                                <path d="m9 8 7 4-7 4Z" />
                            </svg>
                        {:else}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                        {/if}
                    </div>
                    <div class="min-w-0">
                        <div class="font-bold text-xs sm:text-sm text-white truncate" title={file.name}>
                            {file.name}
                        </div>
                        <div class="text-[11px] text-slate-400 flex items-center gap-2">
                            <span>{formatBytes(file.size_bytes)}</span>
                            {#if file.shared_by_name}
                                <span>• แชร์โดย: {file.shared_by_name}</span>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Header Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    {#if isPreviewable}
                        <a
                            href={`/files/${file.uuid}/preview`}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[3px] text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 transition"
                            title="เปิดในแท็บใหม่"
                        >
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                <polyline points="15 3 21 3 21 9" />
                                <line x1="10" y1="14" x2="21" y2="3" />
                            </svg>
                            <span>แท็บใหม่</span>
                        </a>
                    {/if}

                    <a
                        href={`/files/${file.uuid}/download`}
                        class="btn-primary inline-flex items-center gap-1.5 text-xs py-1.5 px-3 font-semibold"
                        title="ดาวน์โหลดไฟล์ลงเครื่อง"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        <span>ดาวน์โหลด</span>
                    </a>

                    <button
                        type="button"
                        onclick={onclose}
                        aria-label="ปิดหน้าต่าง"
                        class="p-1.5 rounded-[3px] text-slate-400 hover:text-white hover:bg-slate-700 transition cursor-pointer"
                    >
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Content Body -->
            <div class="p-4 sm:p-6 bg-[#F7F9FA] overflow-y-auto flex-1 flex items-center justify-center min-h-[300px]">
                {#if isImage}
                    <div class="flex items-center justify-center w-full">
                        <img
                            src={`/files/${file.uuid}/preview`}
                            alt={file.name}
                            class="max-h-[70vh] max-w-full object-contain rounded-[3px] shadow-md border border-[#DDDDDD] bg-white"
                        />
                    </div>
                {:else if isPdf}
                    <div class="w-full h-[72vh] rounded-[3px] overflow-hidden border border-[#DDDDDD] bg-white shadow-xs">
                        <iframe
                            src={`/files/${file.uuid}/preview`}
                            title={file.name}
                            class="w-full h-full border-0"
                        ></iframe>
                    </div>
                {:else if isVideo}
                    <div class="w-full max-w-3xl flex items-center justify-center">
                        <!-- svelte-ignore a11y_media_has_caption -->
                        <video
                            controls
                            autoplay
                            preload="metadata"
                            class="max-h-[70vh] w-full rounded-[3px] shadow-md bg-black"
                        >
                            <source src={`/files/${file.uuid}/preview`} type={file.mime_type || 'video/mp4'} />
                            เบราว์เซอร์ไม่รองรับการเล่นวิดีโอนี้
                        </video>
                    </div>
                {:else if isAudio}
                    <div class="w-full max-w-md bg-white p-6 rounded-[3px] border border-[#DDDDDD] shadow-sm text-center">
                        <div class="w-16 h-16 rounded-full bg-[#EBF8FC] text-[#00A8E1] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 18V5l12-2v13" />
                                <circle cx="6" cy="18" r="3" />
                                <circle cx="18" cy="16" r="3" />
                            </svg>
                        </div>
                        <div class="font-bold text-sm text-[#131921] mb-1">{file.name}</div>
                        <div class="text-xs text-[#777777] mb-4">{formatBytes(file.size_bytes)}</div>
                        <!-- svelte-ignore a11y_media_has_caption -->
                        <audio controls autoplay class="w-full">
                            <source src={`/files/${file.uuid}/preview`} type={file.mime_type || 'audio/mpeg'} />
                            เบราว์เซอร์ไม่รองรับการเล่นเสียงนี้
                        </audio>
                    </div>
                {:else if isText}
                    <div class="w-full h-[70vh] rounded-[3px] overflow-hidden border border-[#DDDDDD] bg-white shadow-xs">
                        <iframe
                            src={`/files/${file.uuid}/preview`}
                            title={file.name}
                            class="w-full h-full border-0 font-mono text-xs p-2"
                        ></iframe>
                    </div>
                {:else}
                    <!-- Non-previewable (Office files: xlsx, docx, pptx, zip, etc.) -->
                    <div class="w-full max-w-lg bg-white p-8 rounded-[3px] border border-[#DDDDDD] shadow-sm text-center flex flex-col items-center">
                        <div class="w-16 h-16 rounded-[4px] bg-[#FFF9F0] border border-[#FF9900]/30 flex items-center justify-center text-[#FF9900] mb-4">
                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-sm text-[#131921] mb-1">{file.name}</h3>
                        <p class="text-xs text-[#777777] mb-2 font-mono">{formatBytes(file.size_bytes)}</p>
                        <p class="text-xs text-[#555555] max-w-sm mb-6 leading-relaxed">
                            ไฟล์ประเภทนี้ ({ext ? ext.toUpperCase() : 'Document'}) ไม่รองรับการแสดงผลบนเบราว์เซอร์โดยตรง กรุณาดาวน์โหลดไปเปิดด้วยโปรแกรมในเครื่องของคุณ
                        </p>
                        <a
                            href={`/files/${file.uuid}/download`}
                            class="btn-primary inline-flex items-center gap-2 py-2 px-5 font-semibold text-xs shadow-xs"
                        >
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                <polyline points="7 10 12 15 17 10" />
                                <line x1="12" y1="15" x2="12" y2="3" />
                            </svg>
                            <span>ดาวน์โหลดไฟล์ ({formatBytes(file.size_bytes)})</span>
                        </a>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}
