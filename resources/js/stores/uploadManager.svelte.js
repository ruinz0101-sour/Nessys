import { router } from '@inertiajs/svelte';

const MAX_CHUNK_RETRIES = 5;

function getChunkSizeForFile(fileSize) {
    if (fileSize > 2 * 1024 * 1024 * 1024) {
        return 16 * 1024 * 1024; // > 2 GB: ชิ้นละ 16 MB
    }
    if (fileSize > 200 * 1024 * 1024) {
        return 8 * 1024 * 1024; // > 200 MB: ชิ้นละ 8 MB (ลดจำนวน HTTP request สำหรับไฟล์ระดับ GB)
    }
    return 2 * 1024 * 1024; // <= 200 MB: ชิ้นละ 2 MB (หยุด/ทำต่อได้ละเอียด)
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function isRetryableError(err) {
    if (!err || err.cancelled || err.paused) {
        return false;
    }
    if (err.networkError) {
        return true;
    }
    const status = err.status;
    return status === 408 || status === 429 || status === 502 || status === 503 || status === 504;
}

export const uploadState = $state({
    open: false,
    minimized: false,
    status: 'uploading', // 'uploading' | 'paused' | 'success' | 'error' | 'quota_exceeded'
    percentage: 0,
    loadedBytes: 0,
    totalBytes: 0,
    speedBytesPerSec: 0,
    elapsedSeconds: 0,
    remainingSeconds: null,
    estimatedFinishAt: null, // timestamp (ms) คาดว่าจะเสร็จเวลาไหน (อิงเวลาปัจจุบัน)
    startedClockAt: null, // timestamp (ms) เวลาที่เริ่มอัปโหลด
    finishedClockAt: null, // timestamp (ms) เวลาที่อัปโหลดเสร็จ
    files: [],
    destination: '',
    errorMessage: '',
    quotaInfo: null,
    isUploading: false,
    canRetry: false,
});

const activeUploadXhrs = new Set();
let activeUploadId = null;
let uploadCancelled = false;
let uploadPaused = false;
let resumeResolvers = [];
let lastUploadAttempt = null;
let uploadCloseTimer = null;
let progressTicker = null;

// จับเวลาอัปโหลดจริง (ไม่นับช่วงที่กด Pause)
let accumulatedActiveMs = 0;
let segmentStartedAt = null;

function getXsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function generateUploadId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

function getActiveElapsedMs() {
    if (segmentStartedAt !== null) {
        return accumulatedActiveMs + (performance.now() - segmentStartedAt);
    }
    return accumulatedActiveMs;
}

function updateTimingMetrics() {
    const activeMs = getActiveElapsedMs();
    const activeSec = activeMs / 1000;
    uploadState.elapsedSeconds = Math.floor(activeSec);

    if (uploadState.status !== 'uploading') {
        return;
    }

    if (activeSec > 0.25 && uploadState.loadedBytes > 0) {
        const speed = Math.round(uploadState.loadedBytes / activeSec);
        uploadState.speedBytesPerSec = speed;

        const remainingBytes = Math.max(0, uploadState.totalBytes - uploadState.loadedBytes);
        if (speed > 0) {
            const remainingSec = Math.ceil(remainingBytes / speed);
            uploadState.remainingSeconds = remainingSec;
            uploadState.estimatedFinishAt = Date.now() + remainingSec * 1000;
        }
    }
}

function startTimingTicker() {
    stopTimingTicker();
    progressTicker = setInterval(() => {
        if (uploadState.status === 'uploading') {
            updateTimingMetrics();
        }
    }, 500);
}

function stopTimingTicker() {
    if (progressTicker) {
        clearInterval(progressTicker);
        progressTicker = null;
    }
}

function waitWhilePaused() {
    if (!uploadPaused || uploadCancelled) {
        return Promise.resolve();
    }
    return new Promise((resolve) => {
        resumeResolvers.push(resolve);
    });
}

function flushResumeResolvers() {
    const resolvers = resumeResolvers;
    resumeResolvers = [];
    for (const resolve of resolvers) {
        resolve();
    }
}

function sendChunkRequest(formData, onChunkProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.__pausedAbort = false;
        activeUploadXhrs.add(xhr);

        xhr.open('POST', '/files/upload/chunk', true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const xsrf = getXsrfToken();
        if (xsrf) {
            xhr.setRequestHeader('X-XSRF-TOKEN', xsrf);
        }

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && onChunkProgress) {
                onChunkProgress(event.loaded, event.total);
            }
        };

        xhr.onload = () => {
            activeUploadXhrs.delete(xhr);
            let body = null;
            try {
                body = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch {
                body = null;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(body);
                return;
            }

            const validationMsg = body?.errors
                ? Object.values(body.errors).flat().find((m) => typeof m === 'string' && m.length > 0)
                : null;
            const message = validationMsg
                || body?.message
                || `เกิดข้อผิดพลาดระหว่างอัปโหลด (HTTP ${xhr.status})`;
            const err = new Error(message);
            err.status = xhr.status;
            reject(err);
        };

        xhr.onerror = () => {
            activeUploadXhrs.delete(xhr);
            const err = new Error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ กรุณาตรวจสอบการเชื่อมต่อแล้วลองใหม่อีกครั้ง');
            err.networkError = true;
            reject(err);
        };

        xhr.onabort = () => {
            activeUploadXhrs.delete(xhr);
            const err = new Error(xhr.__pausedAbort ? 'paused' : 'cancelled');
            if (xhr.__pausedAbort) {
                err.paused = true;
            } else {
                err.cancelled = true;
            }
            reject(err);
        };

        xhr.send(formData);
    });
}

async function completeChunkUploadRequest({ uploadId, fileName, rootType, parentId }) {
    const maxAttempts = 3;
    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        try {
            const xsrf = getXsrfToken();
            const response = await fetch('/files/upload/chunk/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                },
                body: JSON.stringify({
                    upload_id: uploadId,
                    file_name: fileName,
                    root_type: rootType,
                    parent_id: parentId ?? null,
                }),
            });

            let body = null;
            try {
                body = await response.json();
            } catch {
                body = null;
            }

            if (!response.ok) {
                const validationMsg = body?.errors
                    ? Object.values(body.errors).flat().find((m) => typeof m === 'string' && m.length > 0)
                    : null;
                const err = new Error(validationMsg || body?.message || `เกิดข้อผิดพลาดระหว่างบันทึกไฟล์ (HTTP ${response.status})`);
                err.status = response.status;
                throw err;
            }

            return body;
        } catch (err) {
            const canRetry = !err?.status || isRetryableError(err);
            if (attempt < maxAttempts && canRetry && !uploadCancelled) {
                await sleep(1000 * attempt);
                continue;
            }
            throw err;
        }
    }
}

export function closeUploadModal() {
    if (uploadCloseTimer) {
        clearTimeout(uploadCloseTimer);
        uploadCloseTimer = null;
    }
    uploadState.open = false;
    uploadState.minimized = false;
}

export function toggleMinimizeUpload() {
    uploadState.minimized = !uploadState.minimized;
}

export function showQuotaExceededModal(selected = [], destination = '', { usedBytes = 0, quotaBytes = 0 } = {}, customMessage = '') {
    if (uploadCloseTimer) {
        clearTimeout(uploadCloseTimer);
        uploadCloseTimer = null;
    }

    const neededBytes = selected.reduce((sum, file) => sum + (file.size || 0), 0);
    const remaining = quotaBytes > 0 ? Math.max(0, quotaBytes - usedBytes) : 0;
    const defaultMessage = remaining <= 0
        ? 'พื้นที่จัดเก็บของคุณเต็มแล้ว ไม่เพียงพอสำหรับการอัปโหลดไฟล์'
        : 'พื้นที่จัดเก็บไม่เพียงพอ ไฟล์ที่เลือกมีขนาดเกินพื้นที่คงเหลือของคุณ';

    uploadState.open = true;
    uploadState.minimized = false;
    uploadState.status = 'quota_exceeded';
    uploadState.percentage = 0;
    uploadState.loadedBytes = 0;
    uploadState.totalBytes = neededBytes;
    uploadState.speedBytesPerSec = 0;
    uploadState.elapsedSeconds = 0;
    uploadState.remainingSeconds = null;
    uploadState.estimatedFinishAt = null;
    uploadState.startedClockAt = null;
    uploadState.finishedClockAt = null;
    uploadState.files = selected.map((file) => ({
        name: file.name,
        size: file.size || 0,
        type: file.type || '',
    }));
    uploadState.destination = destination;
    uploadState.errorMessage = customMessage || defaultMessage;
    uploadState.quotaInfo = {
        usedBytes,
        quotaBytes,
        remainingBytes: remaining,
        neededBytes,
    };
}

export function pauseUpload() {
    if (!uploadState.isUploading || uploadPaused || uploadCancelled || uploadState.percentage >= 100) {
        return;
    }

    uploadPaused = true;
    if (segmentStartedAt !== null) {
        accumulatedActiveMs += performance.now() - segmentStartedAt;
        segmentStartedAt = null;
    }
    stopTimingTicker();

    uploadState.status = 'paused';
    uploadState.speedBytesPerSec = 0;
    uploadState.remainingSeconds = null;
    uploadState.estimatedFinishAt = null;
    uploadState.elapsedSeconds = Math.floor(accumulatedActiveMs / 1000);

    // หยุด XHR ที่กำลังส่งอยู่ชั่วคราว (จะส่งชิ้นส่วนนั้นใหม่เมื่อกด Resume)
    for (const xhr of activeUploadXhrs) {
        xhr.__pausedAbort = true;
        xhr.abort();
    }
    activeUploadXhrs.clear();
}

export function resumeUpload() {
    if (!uploadState.isUploading || !uploadPaused || uploadCancelled) {
        return;
    }

    uploadPaused = false;
    segmentStartedAt = performance.now();
    uploadState.status = 'uploading';
    startTimingTicker();
    flushResumeResolvers();
}

export function togglePauseUpload() {
    if (uploadPaused) {
        resumeUpload();
    } else {
        pauseUpload();
    }
}

export function cancelUpload() {
    uploadCancelled = true;
    uploadPaused = false;
    stopTimingTicker();
    flushResumeResolvers();

    const cancellingId = activeUploadId;
    for (const xhr of activeUploadXhrs) {
        xhr.__pausedAbort = false;
        xhr.abort();
    }
    activeUploadXhrs.clear();

    if (cancellingId) {
        const xsrf = getXsrfToken();
        fetch(`/files/upload/chunk/${cancellingId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
            },
        }).catch(() => {});
    }

    uploadState.isUploading = false;
    activeUploadId = null;
    closeUploadModal();
}

export function retryUpload() {
    if (!lastUploadAttempt || uploadState.isUploading) {
        return;
    }
    startUpload(lastUploadAttempt.selected, lastUploadAttempt.options);
}

export function refreshPage() {
    window.location.reload();
}

export async function startUpload(
    selected,
    {
        rootType,
        parentId,
        destination,
        quotaBytes = 0,
        usedBytes = 0,
        onSuccessExtra = null,
        resetInput = null,
    }
) {
    if (uploadState.isUploading) {
        uploadState.open = true;
        uploadState.minimized = false;
        if (resetInput) {
            resetInput();
        }
        return;
    }

    if (uploadCloseTimer) {
        clearTimeout(uploadCloseTimer);
        uploadCloseTimer = null;
    }

    lastUploadAttempt = {
        selected,
        options: { rootType, parentId, destination, quotaBytes, usedBytes, onSuccessExtra, resetInput },
    };
    uploadState.canRetry = selected.length > 0;

    const totalSize = selected.reduce((sum, file) => sum + (file.size || 0), 0);

    if (quotaBytes > 0 && (usedBytes + totalSize) > quotaBytes) {
        if (resetInput) {
            resetInput();
        }
        showQuotaExceededModal(selected, destination, { usedBytes, quotaBytes });
        return;
    }

    uploadCancelled = false;
    uploadPaused = false;
    resumeResolvers = [];
    accumulatedActiveMs = 0;
    segmentStartedAt = performance.now();

    uploadState.isUploading = true;
    uploadState.open = true;
    uploadState.minimized = false;
    uploadState.status = 'uploading';
    uploadState.percentage = 0;
    uploadState.loadedBytes = 0;
    uploadState.totalBytes = totalSize;
    uploadState.speedBytesPerSec = 0;
    uploadState.elapsedSeconds = 0;
    uploadState.remainingSeconds = null;
    uploadState.estimatedFinishAt = null;
    uploadState.startedClockAt = Date.now();
    uploadState.finishedClockAt = null;
    uploadState.files = selected.map((file) => ({
        name: file.name,
        size: file.size || 0,
        type: file.type || '',
    }));
    uploadState.destination = destination;
    uploadState.errorMessage = '';
    uploadState.quotaInfo = null;

    startTimingTicker();

    let completedFilesBytes = 0;
    let uploadedCount = 0;

    try {
        for (const file of selected) {
            await waitWhilePaused();
            if (uploadCancelled) {
                break;
            }

            const uploadId = generateUploadId();
            activeUploadId = uploadId;
            const chunkSizeBytes = getChunkSizeForFile(file.size || 0);
            const totalChunks = Math.max(1, Math.ceil((file.size || 0) / chunkSizeBytes));
            const isMultiChunk = totalChunks > 1;
            const chunkLoadedMap = new Map();

            const updateProgress = () => {
                if (uploadCancelled || uploadPaused) {
                    return;
                }
                let inFlightBytes = 0;
                for (const bytes of chunkLoadedMap.values()) {
                    inFlightBytes += bytes;
                }
                const currentLoaded = Math.min(totalSize, completedFilesBytes + inFlightBytes);
                // คงค่า % ไม่ให้ถอยหลังเมื่อกดหยุดชั่วคราวและกดอัปโหลดต่อ
                const monotonicLoaded = Math.max(uploadState.loadedBytes, currentLoaded);
                uploadState.loadedBytes = monotonicLoaded;
                uploadState.percentage = totalSize > 0
                    ? Math.min(100, Math.round((monotonicLoaded / totalSize) * 100))
                    : 100;

                updateTimingMetrics();
            };

            const uploadSingleChunk = async (chunkIndex) => {
                let retryAttempt = 0;

                while (!uploadCancelled) {
                    await waitWhilePaused();
                    if (uploadCancelled) {
                        return;
                    }

                    const start = chunkIndex * chunkSizeBytes;
                    const end = Math.min(file.size, start + chunkSizeBytes);
                    const blob = file.slice(start, end);
                    const chunkSize = end - start;

                    const formData = new FormData();
                    formData.append('upload_id', uploadId);
                    formData.append('chunk', blob, file.name);
                    formData.append('chunk_index', String(chunkIndex));
                    formData.append('total_chunks', String(totalChunks));
                    formData.append('chunk_offset', String(start));
                    if (isMultiChunk) {
                        formData.append('defer_finalize', '1');
                    }
                    formData.append('file_name', file.name);
                    formData.append('file_size', String(file.size || 0));
                    formData.append('root_type', rootType);
                    if (parentId !== null && parentId !== undefined) {
                        formData.append('parent_id', String(parentId));
                    }

                    try {
                        await sendChunkRequest(formData, (loaded, total) => {
                            if (uploadPaused || uploadCancelled) {
                                return;
                            }
                            const chunkProgressBytes = total > 0
                                ? Math.min(chunkSize, Math.round((loaded / total) * chunkSize))
                                : 0;
                            chunkLoadedMap.set(chunkIndex, chunkProgressBytes);
                            updateProgress();
                        });

                        chunkLoadedMap.set(chunkIndex, chunkSize);
                        updateProgress();
                        return;
                    } catch (err) {
                        if (err?.paused && !uploadCancelled) {
                            // รีเซ็ตเฉพาะความคืบหน้าภายในของชิ้นที่ถูกหยุด เพื่อส่งชิ้นนี้ใหม่เมื่อกด Resume โดยไม่ทำให้ % บนหน้าจอถอยหลัง
                            chunkLoadedMap.set(chunkIndex, 0);
                            continue;
                        }

                        // หากการเชื่อมต่อสะดุดชั่วคราว (เช่น socket reset) ให้ลองส่งชิ้นนี้ใหม่อัตโนมัติสูงสุด 5 ครั้ง
                        if (isRetryableError(err) && retryAttempt < MAX_CHUNK_RETRIES && !uploadCancelled) {
                            retryAttempt += 1;
                            chunkLoadedMap.set(chunkIndex, 0);
                            await sleep(Math.min(4000, 600 * retryAttempt));
                            continue;
                        }

                        throw err;
                    }
                }
            };

            // ส่งทีละชิ้นตามลำดับ เพื่อป้องกันการแย่ง socket บน PHP server และลดโอกาส connection reset ในไฟล์ขนาดใหญ่
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
                if (uploadCancelled) {
                    break;
                }
                await uploadSingleChunk(chunkIndex);
            }

            if (isMultiChunk && !uploadCancelled) {
                await waitWhilePaused();
                if (!uploadCancelled) {
                    await completeChunkUploadRequest({
                        uploadId,
                        fileName: file.name,
                        rootType,
                        parentId,
                    });
                }
            }

            if (!uploadCancelled) {
                completedFilesBytes += file.size || 0;
                uploadedCount += 1;
            }
            activeUploadId = null;
        }

        if (uploadCancelled) {
            if (uploadedCount > 0 && window.location.pathname === '/files') {
                router.reload({ preserveScroll: true });
            }
            return;
        }

        stopTimingTicker();
        const finalElapsedSec = Math.floor(getActiveElapsedMs() / 1000);
        uploadState.elapsedSeconds = finalElapsedSec;
        uploadState.remainingSeconds = 0;
        uploadState.finishedClockAt = Date.now();
        uploadState.estimatedFinishAt = uploadState.finishedClockAt;
        uploadState.status = 'success';
        uploadState.percentage = 100;
        uploadState.loadedBytes = uploadState.totalBytes;

        if (window.location.pathname === '/files') {
            if (onSuccessExtra) {
                onSuccessExtra();
            } else {
                router.reload({ preserveScroll: true });
            }
        } else {
            router.reload({ preserveScroll: true, only: ['auth'] });
        }

        uploadCloseTimer = setTimeout(() => {
            uploadState.open = false;
            uploadState.minimized = false;
            uploadCloseTimer = null;
        }, 5000);
    } catch (err) {
        stopTimingTicker();
        if (err?.cancelled || uploadCancelled) {
            if (uploadedCount > 0 && window.location.pathname === '/files') {
                router.reload({ preserveScroll: true });
            }
            return;
        }

        for (const xhr of activeUploadXhrs) {
            xhr.__pausedAbort = false;
            xhr.abort();
        }
        activeUploadXhrs.clear();
        if (activeUploadId) {
            const xsrf = getXsrfToken();
            fetch(`/files/upload/chunk/${activeUploadId}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
                },
            }).catch(() => {});
        }

        const message = err?.message || 'เกิดข้อผิดพลาดระหว่างอัปโหลด กรุณาตรวจสอบขนาดไฟล์หรือการเชื่อมต่อแล้วลองใหม่อีกครั้ง';
        if (message.includes('พื้นที่จัดเก็บ') || message.includes('ไม่เพียงพอ')) {
            showQuotaExceededModal(selected, destination, { usedBytes, quotaBytes }, message);
        } else {
            uploadState.status = 'error';
            uploadState.errorMessage = message;
            uploadState.minimized = false;
        }

        if (uploadedCount > 0 && window.location.pathname === '/files') {
            router.reload({ preserveScroll: true });
        }
    } finally {
        stopTimingTicker();
        uploadState.isUploading = false;
        uploadPaused = false;
        segmentStartedAt = null;
        activeUploadId = null;
        activeUploadXhrs.clear();
        if (resetInput) {
            resetInput();
        }
    }
}
