<?php

use App\Enums\UserRole;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TrashController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

Route::middleware('auth')->group(function (): void {
    // หน้าแรก: Admin ไปหน้าแดชบอร์ด, ผู้ใช้ทั่วไปไปหน้าจัดการไฟล์
    Route::get('/', function (Request $request, FileManagerController $controller): InertiaResponse|RedirectResponse {
        if ($request->user()?->role === UserRole::Admin) {
            return redirect()->route('admin.dashboard');
        }

        return $controller->index($request);
    })->name('dashboard');

    // จัดการไฟล์และโฟลเดอร์ (ดูรายการ, สร้างโฟลเดอร์, อัปโหลด, เปลี่ยนชื่อ, ย้าย, ดาวน์โหลด, พรีวิว, ลบ)
    Route::get('/files', [FileManagerController::class, 'index'])->name('files.index');
    Route::post('/files/folders', [FileManagerController::class, 'createFolder'])->name('files.folders.store');
    Route::post('/files/upload', [FileManagerController::class, 'upload'])->name('files.upload');
    Route::post('/files/upload/chunk', [FileManagerController::class, 'uploadChunk'])->name('files.upload.chunk');
    Route::post('/files/upload/chunk/complete', [FileManagerController::class, 'completeChunkUpload'])->name('files.upload.chunk.complete');
    Route::delete('/files/upload/chunk/{uploadId}', [FileManagerController::class, 'cancelChunkUpload'])->name('files.upload.chunk.cancel');
    Route::patch('/files/{node:uuid}/rename', [FileManagerController::class, 'rename'])->name('files.rename');
    Route::patch('/files/{node:uuid}/move', [FileManagerController::class, 'move'])->name('files.move');
    Route::get('/files/{node:uuid}/download', [FileManagerController::class, 'download'])->name('files.download');
    Route::get('/files/{node:uuid}/preview', [FileManagerController::class, 'preview'])->name('files.preview');
    Route::get('/files/{node:uuid}/request-access', [AccessRequestController::class, 'prompt'])->name('files.request-access');
    Route::delete('/files/{node:uuid}', [TrashController::class, 'destroy'])->name('files.destroy');

    // ระบบแชร์ไฟล์/โฟลเดอร์
    Route::get('/shares', [ShareController::class, 'index'])->name('shares.index');
    Route::post('/shares', [ShareController::class, 'store'])->name('shares.store');
    Route::patch('/shares/{share}/revoke', [ShareController::class, 'revoke'])->name('shares.revoke');

    // ระบบคำขอสิทธิ์เข้าถึงไฟล์
    Route::get('/access-requests', [AccessRequestController::class, 'index'])->name('access-requests.index');
    Route::post('/access-requests', [AccessRequestController::class, 'store'])->name('access-requests.store');
    Route::patch('/access-requests/{accessRequest}/decide', [AccessRequestController::class, 'decide'])->name('access-requests.decide');
    Route::patch('/access-requests/{accessRequest}/cancel', [AccessRequestController::class, 'cancel'])->name('access-requests.cancel');

    // การแจ้งเตือน (Notifications)
    Route::patch('/notifications/{notification}/read', [ShareController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [ShareController::class, 'markAllNotificationsRead'])->name('notifications.read-all');

    // ถังขยะ (Trash)
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/{uuid}/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/{uuid}', [TrashController::class, 'forceDestroy'])->name('trash.force-destroy');

    // ตั้งค่าโปรไฟล์และรหัสผ่านส่วนตัว
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/users/{user}/avatar', [ProfileController::class, 'showAvatar'])->name('users.avatar');

    Route::get('/settings/password', fn (): InertiaResponse => Inertia::render('Settings/Password'))->name('password.edit');

    // ส่วนผู้ดูแลระบบ (Admin เท่านั้น): แดชบอร์ด, Audit Logs, จัดการผู้ใช้และแผนก
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::patch('/users/{user}/quota', [UserController::class, 'updateQuota'])->name('users.quota');
        Route::put('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    });
});
