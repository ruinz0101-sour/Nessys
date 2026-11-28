# คู่มือโครงสร้างโค้ดและการแก้ไขระบบ (CloudStorage NAS)

เอกสารนี้เป็นแผนที่สำหรับค้นหาว่า **หากต้องการแก้ไขฟีเจอร์ หน้าจอ หรือเงื่อนไขการทำงานส่วนใด ต้องไปแก้ที่ไฟล์ไหน** โดยแยกทั้งฝั่ง **Backend (Laravel 13 / PHP)** และ **Frontend (Inertia v3 + Svelte 5 + Tailwind CSS v4)**

## สารบัญ

- [1. ตารางค้นหาด่วน: อยากแก้ตรงนี้ → ไปไฟล์ไหน](#1-ตารางค้นหาด่วน-อยากแก้ตรงนี้--ไปไฟล์ไหน)
- [2. เจาะลึกจุดที่มักแก้ไขบ่อย](#2-เจาะลึกจุดที่มักแก้ไขบ่อย)
  - [2.1 ระบบสมาชิกและการกำหนดแผนก](#21-ระบบสมาชิกและการกำหนดแผนก)
  - [2.2 การแยกหมวดหมู่ไฟล์](#22-การแยกหมวดหมู่ไฟล์)
  - [2.3 สิทธิ์การเข้าถึงไฟล์และบทบาทผู้ใช้](#23-สิทธิ์การเข้าถึงไฟล์และบทบาทผู้ใช้)
  - [2.4 ระบบอัปโหลดแบบแบ่งชิ้น (Chunk Upload)](#24-ระบบอัปโหลดแบบแบ่งชิ้น-chunk-upload)
  - [2.5 ถังขยะและงานตั้งเวลาอัตโนมัติ (Trash & Scheduler)](#25-ถังขยะและงานตั้งเวลาอัตโนมัติ-trash--scheduler)
  - [2.6 แดชบอร์ดผู้ดูแลระบบ (Admin Dashboard & RAID/SMART)](#26-แดชบอร์ดผู้ดูแลระบบ-admin-dashboard--raidsmart)
- [3. โครงสร้างโฟลเดอร์หลัก](#3-โครงสร้างโฟลเดอร์หลัก)
- [4. คำสั่งที่ควรรันหลังแก้ไขโค้ด](#4-คำสั่งที่ควรรันหลังแก้ไขโค้ด)

---

## 1. ตารางค้นหาด่วน: "อยากแก้ตรงนี้ → ไปไฟล์ไหน"

### A. ระบบสมาชิก โปรไฟล์ และการเข้าสู่ระบบ

| เรื่องที่ต้องการแก้ไข | ฝั่ง Backend (PHP) | ฝั่ง Frontend (Svelte) |
| --- | --- | --- |
| **สมัครสมาชิก** (ค่าเริ่มต้นแผนก, โควต้า 10 GB, role) | `app/Actions/Fortify/CreateNewUser.php` | `resources/js/Pages/Auth/Register.svelte` |
| **เข้าสู่ระบบ, ล็อกบัญชีเมื่อใส่รหัสผิด (5 ครั้ง / 15 นาที), Redirect หลัง Login** | `app/Providers/FortifyServiceProvider.php`<br>`config/fortify.php` | `resources/js/Pages/Auth/Login.svelte` |
| **ลืมรหัสผ่าน / รีเซ็ตรหัสผ่าน** | `app/Actions/Fortify/ResetUserPassword.php`<br>`app/Actions/Fortify/PasswordValidationRules.php` | `resources/js/Pages/Auth/ForgotPassword.svelte`<br>`resources/js/Pages/Auth/ResetPassword.svelte` |
| **โปรไฟล์ส่วนตัว** (ชื่อ, อีเมล, รูปโปรไฟล์) & **เปลี่ยนรหัสผ่าน** | `app/Http/Controllers/ProfileController.php`<br>`app/Actions/Fortify/UpdateUserPassword.php` | `resources/js/Pages/Settings/Profile.svelte`<br>`resources/js/Pages/Settings/Password.svelte` |

### B. การจัดการไฟล์ อัปโหลด พรีวิว และถังขยะ

| เรื่องที่ต้องการแก้ไข | ฝั่ง Backend (PHP) | ฝั่ง Frontend (Svelte) |
| --- | --- | --- |
| **จัดการไฟล์** (แสดงรายการ, ค้นหา, สร้างโฟลเดอร์, เปลี่ยนชื่อ, ย้าย, ดาวน์โหลด) | `app/Http/Controllers/FileManagerController.php` | `resources/js/Pages/Files/Index.svelte` |
| **อัปโหลดไฟล์แบบแบ่งชิ้น** (Chunk Upload, Pause/Resume, Auto-Retry, ETA, กล่องลอย) | `app/Http/Controllers/FileManagerController.php`<br>(`uploadChunk`, `completeChunkUpload`, `cancelChunkUpload`) | `resources/js/stores/uploadManager.svelte.js`<br>`resources/js/Components/UploadProgressModal.svelte`<br>`resources/js/Layouts/AppLayout.svelte`<br>`resources/js/Layouts/AdminLayout.svelte` |
| **หมวดหมู่ไฟล์** (รูปภาพ, วิดีโอ, เอกสาร, อื่นๆ) และเกณฑ์แยก MIME/นามสกุล | `app/Enums/FileCategory.php`<br>`app/Http/Controllers/FileManagerController.php` (`resolveFileCategory`) | `resources/js/Pages/Files/Index.svelte` |
| **หน้าต่างพรีวิวไฟล์** (PDF, รูปภาพ, วิดีโอ ฯลฯ) | `app/Http/Controllers/FileManagerController.php` (`preview`) | `resources/js/Components/FilePreviewModal.svelte` |
| **Popup ยืนยันการลบ** (ย้ายลงถังขยะ / ลบถาวร) | `app/Http/Controllers/TrashController.php` (`destroy`, `forceDestroy`) | `resources/js/Components/ConfirmDeleteModal.svelte`<br>`resources/js/Pages/Files/Index.svelte`<br>`resources/js/Pages/Files/Trash.svelte` |
| **ถังขยะ** (กู้คืน, ลบถาวร, ระยะเวลาเก็บ 30 วัน) | `app/Http/Controllers/TrashController.php` (`TRASH_RETENTION_DAYS`) | `resources/js/Pages/Files/Trash.svelte` |

### C. สิทธิ์การเข้าถึง การแชร์ และคำขอสิทธิ์

| เรื่องที่ต้องการแก้ไข | ฝั่ง Backend (PHP) | ฝั่ง Frontend (Svelte) |
| --- | --- | --- |
| **สิทธิ์การเข้าถึงไฟล์/โฟลเดอร์** (ดู, ดาวน์โหลด, อัปโหลด, แก้ไข, ลบ, แชร์, Dropzone) | `app/Policies/NodePolicy.php` | — |
| **การแชร์ไฟล์/โฟลเดอร์** (แชร์ให้ผู้ใช้หรือแผนก, ยกเลิกแชร์) | `app/Http/Controllers/ShareController.php` | `resources/js/Pages/Shares/Index.svelte` |
| **คำขอเข้าถึงไฟล์** (ส่งคำขอ, อนุมัติ/ปฏิเสธ, ยกเลิก, อีเมลแจ้งเตือน) | `app/Http/Controllers/AccessRequestController.php`<br>`app/Mail/AccessRequest*.php` | `resources/js/Pages/AccessRequests/Index.svelte`<br>`resources/js/Pages/AccessRequests/Prompt.svelte` |

### D. ระบบผู้ดูแลระบบ (Admin)

| เรื่องที่ต้องการแก้ไข | ฝั่ง Backend (PHP) | ฝั่ง Frontend (Svelte) |
| --- | --- | --- |
| **แดชบอร์ดผู้ดูแลระบบ** (สถิติไฟล์, Top Users, RAID Pool & Disk SMART) | `app/Http/Controllers/Admin/DashboardController.php` | `resources/js/Pages/Admin/Dashboard.svelte` |
| **จัดการผู้ใช้** (สร้าง, แก้ไข, กำหนดแผนก, เปิด/ปิดบัญชี, โควต้า, รีเซ็ตรหัสผ่าน) | `app/Http/Controllers/Admin/UserController.php`<br>`app/Http/Requests/Admin/*.php` | `resources/js/Pages/Admin/Users/Index.svelte` |
| **จัดการแผนก** (สร้าง/แก้ไขแผนก, ตั้งหัวหน้าแผนก) | `app/Http/Controllers/Admin/DepartmentController.php` | `resources/js/Pages/Admin/Users/Index.svelte` |
| **ประวัติการใช้งานระบบ (Audit Logs)** | `app/Http/Controllers/Admin/AuditLogController.php` | `resources/js/Pages/Admin/AuditLogs/Index.svelte` |

### E. โครงสร้างกลาง, Routes และ Cron Job

| เรื่องที่ต้องการแก้ไข | ฝั่ง Backend (PHP) | ฝั่ง Frontend (Svelte) |
| --- | --- | --- |
| **เส้นทาง URL ทั้งหมด (Routes)** | `routes/web.php` | — |
| **Sidebar, Header, แจ้งเตือน (Notifications), ข้อมูลผู้ใช้ที่แชร์ทุกหน้า** | `app/Http/Middleware/HandleInertiaRequests.php` | `resources/js/Layouts/AppLayout.svelte`<br>`resources/js/Layouts/AdminLayout.svelte` |
| **งานตั้งเวลาอัตโนมัติ (Cron / Scheduler)** | `routes/console.php`<br>`app/Console/Commands/*.php` | — |

---

## 2. เจาะลึกจุดที่มักแก้ไขบ่อย

### 2.1 ระบบสมาชิกและการกำหนดแผนก

- **หน้าสมัครสมาชิก:** `resources/js/Pages/Auth/Register.svelte`
  - ปัจจุบันรับเฉพาะ `email`, `password`, `password_confirmation` (หากไม่กรอก `name` ระบบจะดึงชื่อหน้าเครื่องหมาย `@` ของอีเมลมาใช้แทน)
- **ค่าเริ่มต้นตอนสร้างผู้ใช้ใหม่:** `app/Actions/Fortify/CreateNewUser.php`
  - `DEFAULT_QUOTA_BYTES` = `10 GB`
  - `role` = `UserRole::User`
  - `department_id` = `null` (ยังไม่ได้สังกัดแผนก — ให้ Admin กำหนดภายหลังที่หน้า `/admin/users`)
- **Admin สร้าง/แก้ไขผู้ใช้:** `app/Http/Controllers/Admin/UserController.php` (`store`, `update`) + Validation ที่ `app/Http/Requests/Admin/StoreUserRequest.php` และ `UpdateUserRequest.php`

> **ทิป:** หากต้องการให้เลือกแผนกตอนสมัครสมาชิกได้เอง ต้องแก้ 3 จุด คือ
> 1. ส่งรายการ `departments` จาก `Fortify::registerView()` ใน `app/Providers/FortifyServiceProvider.php`
> 2. เพิ่ม `<select>` เลือกแผนกใน `resources/js/Pages/Auth/Register.svelte`
> 3. เพิ่ม validation และบันทึก `department_id` ใน `app/Actions/Fortify/CreateNewUser.php`

---

### 2.2 การแยกหมวดหมู่ไฟล์

- **Enum หมวดหมู่:** `app/Enums/FileCategory.php`
- **เงื่อนไขจำแนกไฟล์:** `FileManagerController::resolveFileCategory()` ใน `app/Http/Controllers/FileManagerController.php`
- **ตัวกรองบนหน้าจอ:** `resources/js/Pages/Files/Index.svelte` (ค้นหา `categoryInput`)

| หมวดหมู่ (`FileCategory`) | เกณฑ์การจำแนก (MIME จริง / นามสกุล) |
| --- | --- |
| `image` (รูปภาพ) | MIME ขึ้นต้นด้วย `image/` |
| `video` (วิดีโอ) | MIME ขึ้นต้นด้วย `video/` |
| `document` (เอกสาร) | MIME `text/*`, PDF, Word, Excel, PowerPoint, RTF หรือนามสกุล `pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, md, rtf` |
| `other` (อื่นๆ) | ไฟล์ทุกชนิดที่ไม่เข้า 3 หมวดด้านบน เช่น `.zip`, `.mp3`, `.exe`, `.psd`, `.json` |

---

### 2.3 สิทธิ์การเข้าถึงไฟล์และบทบาทผู้ใช้

**บทบาทผู้ใช้ (`app/Enums/UserRole.php`):**
- `admin` — ผู้ดูแลระบบ (เข้าถึงได้ทุกส่วนและจัดการทุกไฟล์)
- `dept_manager` — หัวหน้าแผนก (จัดการพื้นที่แผนกและพื้นที่ส่วนกลางได้)
- `user` — ผู้ใช้งานทั่วไป

**ประเภทพื้นที่เก็บไฟล์ (`app/Enums/RootType.php`):**

| Root Type | ความหมายและสิทธิ์หลัก |
| --- | --- |
| `personal` | พื้นที่ส่วนตัวของเจ้าของไฟล์ |
| `department` | พื้นที่แผนก (ต้องมี `department_id` จึงจะใช้งานได้) |
| `shared` | พื้นที่ส่วนกลาง (ทุกคนดูได้; Admin/DeptManager จัดการได้; โฟลเดอร์ที่ตั้ง `is_dropzone = true` ให้ทุกคนอัปโหลดได้) |

**ศูนย์กลางตรวจสิทธิ์ (`app/Policies/NodePolicy.php`):**
- `view()` / `download()` — ตรวจสิทธิ์ไล่ตามลำดับ: Admin → Owner → Shared → Department → Active Share → Approved Access Request
- `upload()` — ตรวจสิทธิ์อัปโหลด (รวมข้อยกเว้น Public Dropzone)
- `update()` — เปลี่ยนชื่อ / ย้าย
- `delete()` / `restore()` / `forceDelete()` — ย้ายลงถังขยะ / กู้คืน / ลบถาวร
- `share()` — แชร์ไฟล์/โฟลเดอร์

**Middleware ควบคุมการเข้าถึง:**
- `app/Http/Middleware/EnsureUserHasRole.php` — ใช้กับ `role:admin`
- `app/Http/Middleware/EnsureUserIsActive.php` — บล็อกบัญชีที่ถูกปิดการใช้งาน

---

### 2.4 ระบบอัปโหลดแบบแบ่งชิ้น (Chunk Upload)

- **Frontend:**
  - `resources/js/stores/uploadManager.svelte.js` — จัดการคิวอัปโหลดระดับ Global (Pause/Resume, Auto-Retry, จับเวลา, คำนวณความเร็วและเวลาคาดว่าจะเสร็จ)
  - `resources/js/Components/UploadProgressModal.svelte` — กล่องลอยมุมขวาล่าง (ย่อ/ขยายได้ และยังอัปโหลดต่อเนื่องแม้เปลี่ยนหน้า เพราะฝังไว้ทั้งใน `AppLayout.svelte` และ `AdminLayout.svelte`)
- **Backend:**
  - `app/Http/Controllers/FileManagerController.php` — เมธอด `uploadChunk`, `completeChunkUpload`, `cancelChunkUpload`

**ขนาดชิ้นส่วนแบบปรับอัตโนมัติ (`getChunkSizeForFile()` ใน `uploadManager.svelte.js`):**

| ขนาดไฟล์ | ขนาดต่อ 1 ชิ้น (Chunk) | เหตุผล |
| --- | --- | --- |
| `≤ 200 MB` | **2 MB** | กด Pause/Resume ได้ละเอียดตรง `%` เดิม |
| `> 200 MB – 2 GB` | **8 MB** | ลดจำนวน HTTP Request สำหรับไฟล์ขนาดใหญ่ |
| `> 2 GB` | **16 MB** | ส่งไฟล์ระดับหลาย GB ได้ไวและเสถียรขึ้น |

> **หมายเหตุการตั้งค่าขนาดชิ้น:** ฝั่ง Backend จำกัดขนาดต่อชิ้นไว้ไม่เกิน `max:32768` (32 MB) และต้องไม่เกินค่า `upload_max_filesize` / `post_max_size` ใน `php.ini` ด้วย

**กลไกการทำงานและเหตุผลที่ต้องหั่นไฟล์:**
1. **เลี่ยงลิมิต PHP / Web Server:** หากส่งไฟล์ใหญ่รวดเดียวจะติดเพดาน `upload_max_filesize` / `post_max_size` ทันที
2. **รองรับ Pause / Resume:** ชิ้นที่ส่งแล้วจะถูกเขียนลงไฟล์ชั่วคราว `.part` บนเซิร์ฟเวอร์ พอกดอัปโหลดต่อจึงส่งเฉพาะชิ้นที่เหลือได้
3. **ส่งทีละชิ้นตามลำดับ (Sequential) + Auto-Retry:** ส่งทีละชิ้นเพื่อลดการแย่ง socket บน PHP server และถ้าเน็ตสะดุด (Network Error หรือ HTTP `408, 429, 502, 503, 504`) จะลองส่งชิ้นเดิมใหม่อัตโนมัติสูงสุด **5 ครั้ง** (`MAX_CHUNK_RETRIES = 5`)

**ขีดจำกัดขนาดไฟล์สูงสุด:**
- **อัปโหลด:** ไม่ได้ล็อกเพดานขนาดต่อไฟล์ในโค้ด แต่จำกัดตาม **โควต้าของผู้ใช้** (เริ่มต้น `10 GB` หรือ Admin ตั้ง `0 GB` = ไม่จำกัด) และ **พื้นที่ว่างบนดิสก์ของเซิร์ฟเวอร์** *(ฝั่ง Backend เรียก `@set_time_limit(0)` ใน `finalizeChunkUploadFile()` แล้ว แต่หากรันหลัง Nginx/Proxy ควรดูค่า proxy timeout สำหรับขั้นตอนคำนวณ `sha256` ของไฟล์ขนาดใหญ่ระดับ > 10–20 GB ด้วย)*
- **ดาวน์โหลด:** ไม่จำกัดขนาดไฟล์ เพราะส่งแบบ `BinaryFileResponse` (`response()->download(...)`) ไม่ได้โหลดทั้งไฟล์เข้า RAM และรองรับ **HTTP Range (`206 Partial Content`) + `ETag`** ทำให้เบราว์เซอร์สามารถกด **Resume / โหลดต่อจากจุดที่ค้าง** ได้เมื่อเน็ตหลุดหรือพื้นที่ไม่พอแล้วเคลียร์พื้นที่เสร็จ

---

### 2.5 ถังขยะและงานตั้งเวลาอัตโนมัติ (Trash & Scheduler)

- **ระยะเวลาเก็บไฟล์ในถังขยะ:** `TRASH_RETENTION_DAYS = 30` ใน `app/Http/Controllers/TrashController.php`
- **ตารางเวลา Cron Job (`routes/console.php`):**

| เวลา (ทุกวัน) | คำสั่ง Artisan | ไฟล์คำสั่ง | หน้าที่ |
| --- | --- | --- | --- |
| `01:00` | `nas:purge-trash` | `app/Console/Commands/PurgeExpiredTrashCommand.php` | ลบไฟล์ที่หมดอายุในถังขยะถาวร |
| `02:00` | `nas:recalculate-quotas` | `app/Console/Commands/RecalculateUserQuotasCommand.php` | คำนวณพื้นที่ใช้งาน (`used_bytes`) ของผู้ใช้ใหม่ให้ตรงความจริง |
| `08:00` | `nas:remind-access-requests` | `app/Console/Commands/SendAccessRequestRemindersCommand.php` | ส่งอีเมลเตือนคำขอเข้าถึงไฟล์ที่ยังค้างพิจารณา |

---

### 2.6 แดชบอร์ดผู้ดูแลระบบ (Admin Dashboard & RAID/SMART)

- **Backend:** `app/Http/Controllers/Admin/DashboardController.php`
  - หากตาราง `raid_pools` ในฐานข้อมูลยังว่าง เมธอด `getRaidAndDiskData()` จะคืนข้อมูล RAID/Disk จำลอง (12 TB) แต่ยังคงใช้ **ยอดพื้นที่ใช้งานจริง (`used_bytes`)** จากไฟล์ที่อัปโหลดอยู่ในระบบ
- **Frontend:** `resources/js/Pages/Admin/Dashboard.svelte`

---

## 3. โครงสร้างโฟลเดอร์หลัก

```text
app/
├── Actions/Fortify/       # สมัครสมาชิก, เปลี่ยน/รีเซ็ตรหัสผ่าน, กฎรหัสผ่าน
├── Console/Commands/      # คำสั่ง Artisan สำหรับ Cron Job (ล้างถังขยะ, คำนวณโควต้า, เตือนคำขอ)
├── Enums/                 # ค่าคงที่แบบ Enum (FileCategory, UserRole, RootType, NodeType ฯลฯ)
├── Http/
│   ├── Controllers/       # ตัวควบคุมหลัก (FileManager, Share, AccessRequest, Trash, Profile)
│   │   └── Admin/         # ตัวควบคุมฝั่ง Admin (Dashboard, User, Department, AuditLog)
│   ├── Middleware/        # HandleInertiaRequests, EnsureUserHasRole, EnsureUserIsActive
│   └── Requests/          # Form Request Validation
├── Mail/                  # คลาสอีเมลแจ้งเตือนคำขอเข้าถึงไฟล์
├── Models/                # Eloquent Models (User, Node, Department, Share, AccessRequest, AuditLog ฯลฯ)
├── Policies/              # NodePolicy (กฎสิทธิ์ไฟล์/โฟลเดอร์ทั้งหมด)
└── Providers/             # AppServiceProvider, FortifyServiceProvider (Login/Lockout/Views)

resources/js/
├── Components/            # คอมโพเนนต์ใช้ซ้ำ (FilePreviewModal, UploadProgressModal, ConfirmDeleteModal)
├── Layouts/               # โครงหน้าหลัก (AppLayout สำหรับผู้ใช้ทั่วไป, AdminLayout สำหรับแอดมิน)
├── Pages/                 # หน้าจอทั้งหมด (Auth, Files, Shares, AccessRequests, Settings, Admin)
└── stores/                # สถานะระดับ Global (uploadManager.svelte.js)

routes/
├── web.php                # เส้นทางเว็บทั้งหมด
└── console.php            # ตารางเวลา Cron Job (Schedule)
```

---

## 4. คำสั่งที่ควรรันหลังแก้ไขโค้ด

**หลังแก้ไฟล์ Frontend (Svelte / CSS):**
```bash
npm run build
# หรือรัน npm run dev ค้างไว้ระหว่างพัฒนา
```

**หลังแก้ไฟล์ Backend (PHP):**
```bash
vendor/bin/pint --dirty
php artisan test --compact
```
