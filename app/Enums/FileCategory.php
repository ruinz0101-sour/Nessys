<?php

namespace App\Enums;

/**
 * หมวดหมู่ของไฟล์ในระบบ (ใช้คู่กับ FileManagerController::resolveFileCategory และตัวกรองหน้า Files/Index.svelte)
 */
enum FileCategory: string
{
    case Image = 'image';       // รูปภาพ (MIME image/*)
    case Video = 'video';       // วิดีโอ (MIME video/*)
    case Document = 'document'; // เอกสาร (PDF, Office, Text ฯลฯ)
    case Other = 'other';       // อื่นๆ (ไฟล์ที่ไม่เข้า 3 หมวดด้านบน เช่น zip, mp3, exe)
}
