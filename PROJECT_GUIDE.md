# คู่มือโครงสร้างโค้ดและการแก้ไขระบบ (CloudStorage NAS)

เอกสารนี้ใช้เป็นแผนที่สำหรับค้นหาว่า **หากต้องการแก้ไขฟีเจอร์ หน้าจอ หรือเงื่อนไขการทำงานส่วนใด ต้องไปแก้ที่ไฟล์ไหน** โดยแยกทั้งฝั่ง **Backend (Laravel / PHP)** และ **Frontend (Inertia + Svelte 5)**

---

## 1. ตารางค้นหาด่วน: "อยากแก้ตรงนี้ → ไปไฟล์ไหน"

| เรื่องที่ต้องการแก้ไข | ฝั่ง Backend (PHP) | ฝั่ง Frontend (Svelte) |
| --- | --- | --- |
| **เส้นทาง URL ทั้งหมด (Routes)** | `routes/web.php` | — |

| **สมัครสมาชิก (ค่าเริ่มต้นแผนก, โควต้า 10 GB, role)** | `app/Actions/Fortify/CreateNewUser.php` | `resources/js/Pages/Auth/Register.svelte` |

| **เข้าสู่ระบบ, ล็อกบัญชีเมื่อใส่รหัสผิด (5 ครั้ง / 15 นาที), Redirect หลัง Login** | `app/Providers/FortifyServiceProvider.php`, `config/fortify.php` | `resources/js/Pages/Auth/Login.svelte` |

| **ลืมรหัสผ่าน / รีเซ็ตรหัสผ่าน** | `app/Actions/Fortify/ResetUserPassword.php`, `app/Actions/Fortify/PasswordValidationRules.php` | `resources/js/Pages/Auth/ForgotPassword.svelte`, `ResetPassword.svelte` |

| **จัดการไฟล์ (แสดงรายการ, ค้นหา, สร้างโฟลเดอร์, เปลี่ยนชื่อ, ย้าย, ดาวน์โหลด, พรีวิว)** | `app/Http/Controllers/FileManagerController.php` | `resources/js/Pages/Files/Index.svelte` |

| **อัปโหลดไฟล์ (Chunk Upload, Pause/Resume, ETA, กล่องลอยไม่บล็อกหน้าจอ)** | `app/Http/Controllers/FileManagerController.php` (`uploadChunk`, `completeChunkUpload`, `cancelChunkUpload`) | `resources/js/stores/uploadManager.svelte.js`, `resources/js/Components/UploadProgressModal.svelte`, `resources/js/Layouts/AppLayout.svelte` |
| **Popup ยืนยันการลบ (ย้ายลงถังขยะ / ลบถาวร)** | `app/Http/Controllers/TrashController.php` (`destroy`, `forceDestroy`) | `resources/js/Components/ConfirmDeleteModal.svelte`, `resources/js/Pages/Files/Index.svelte`, `resources/js/Pages/Files/Trash.svelte` |

| **หมวดหมู่ไฟล์ (เอกสาร, รูปภาพ, วิดีโอ, อื่นๆ) และเกณฑ์แยกนามสกุล/MIME** | `app/Enums/FileCategory.php`, `app/Http/Controllers/FileManagerController.php` (`resolveFileCategory`) | `resources/js/Pages/Files/Index.svelte` (dropdown `<select>`) |

| **หน้าต่างพรีวิวไฟล์ (PDF, รูปภาพ, วิดีโอ ฯลฯ)** | `app/Http/Controllers/FileManagerController.php` (`preview`) | `resources/js/Components/FilePreviewModal.svelte` |

| **สิทธิ์การเข้าถึงไฟล์/โฟลเดอร์ (ดู, ดาวน์โหลด, อัปโหลด, แก้ไข, ลบ, แชร์, Dropzone)** | `app/Policies/NodePolicy.php` | — |

| **การแชร์ไฟล์/โฟลเดอร์ (ให้ผู้ใช้หรือแผนก, ยกเลิกแชร์)** | `app/Http/Controllers/ShareController.php` | `resources/js/Pages/Shares/Index.svelte` |

| **คำขอเข้าถึงไฟล์ (ส่งคำขอ, อนุมัติ/ปฏิเสธ, ยกเลิก, อีเมลแจ้งเตือน)** | `app/Http/Controllers/AccessRequestController.php`, `app/Mail/AccessRequest*.php` | `resources/js/Pages/AccessRequests/Index.svelte`, `Prompt.svelte` |

| **ถังขยะ (ย้ายลงถังขยะ, กู้คืน, ลบถาวร, ระยะเวลาเก็บ 30 วัน)** | `app/Http/Controllers/TrashController.php` (`TRASH_RETENTION_DAYS`) | `resources/js/Pages/Files/Trash.svelte` |

| **แดชบอร์ดผู้ดูแลระบบ (สถิติไฟล์, Top Users, RAID Pool & Disk SMART)** | `app/Http/Controllers/Admin/DashboardController.php` | `resources/js/Pages/Admin/Dashboard.svelte` |

| **จัดการผู้ใช้ (สร้าง, แก้ไข, กำหนดแผนก, เปิด/ปิดบัญชี, โควต้า, รีเซ็ตรหัสผ่าน)** | `app/Http/Controllers/Admin/UserController.php`, `app/Http/Requests/Admin/*.php` | `resources/js/Pages/Admin/Users/Index.svelte` |

| **จัดการแผนก (สร้าง/แก้ไขแผนก, ตั้งหัวหน้าแผนก)** | `app/Http/Controllers/Admin/DepartmentController.php` | `resources/js/Pages/Admin/Users/Index.svelte` |

| **ประวัติการใช้งานระบบ (Audit Logs)** | `app/Http/Controllers/Admin/AuditLogController.php` | `resources/js/Pages/Admin/AuditLogs/Index.svelte` |

| **โปรไฟล์ส่วนตัว (ชื่อ, อีเมล, รูปโปรไฟล์) & เปลี่ยนรหัสผ่าน** | `app/Http/Controllers/ProfileController.php`, `app/Actions/Fortify/UpdateUserPassword.php` | `resources/js/Pages/Settings/Profile.svelte`, `Password.svelte` |

| **แถบเมนูด้านข้าง (Sidebar), Header, แจ้งเตือน (Notifications), ข้อมูลผู้ใช้ที่แชร์ทุกหน้า** | `app/Http/Middleware/HandleInertiaRequests.php` | `resources/js/Layouts/AppLayout.svelte`, `AdminLayout.svelte` |

| **งานตั้งเวลาอัตโนมัติ (Cron / Scheduler)** | `routes/console.php`, `app/Console/Commands/*.php` | — |

---

## 2. รายละเอียดจุดที่มักแก้ไขบ่อย

### 2.1 ระบบสมาชิกและการกำหนดแผนก (Registration & Department)
- **หน้าสมัครสมาชิก:** `resources/js/Pages/Auth/Register.svelte`
  - ปัจจุบันรับเฉพาะ `email`, `password`, `password_confirmation`
- **ตรรกะสร้างผู้ใช้ใหม่:** `app/Actions/Fortify/CreateNewUser.php`
  - `DEFAULT_QUOTA_BYTES` = โควต้าพื้นที่เริ่มต้น (ปัจจุบัน `10 GB`)
  - `role` = `UserRole::User`
  - `department_id` = `null` (ยังไม่ได้สังกัดแผนก — ต้องให้ Admin ไปกำหนดให้ภายหลัง)
  - *หากต้องการให้เลือกแผนกตอนสมัครได้:* ต้องส่งรายการ `departments` จาก `Fortify::registerView` ใน `app/Providers/FortifyServiceProvider.php`, เพิ่ม `<select>` ใน `Register.svelte`, และเพิ่ม validation + บันทึก `department_id` ใน `CreateNewUser.php`
- **Admin สร้าง/แก้ไขผู้ใช้และกำหนดแผนก:** `app/Http/Controllers/Admin/UserController.php` (`store`, `update`) + Validation ใน `app/Http/Requests/Admin/StoreUserRequest.php` และ `UpdateUserRequest.php`

### 2.2 การแยกหมวดหมู่ไฟล์ (File Categories)
- **รายชื่อหมวดหมู่ (Enum):** `app/Enums/FileCategory.php` (`image`, `video`, `document`, `other`)
- **เงื่อนไขการจำแนกไฟล์:** `app/Http/Controllers/FileManagerController.php` → เมธอด `resolveFileCategory()`
  - `image/*` → `FileCategory::Image`
  - `video/*` → `FileCategory::Video`
  - `text/*` หรือ MIME เอกสาร (PDF, Word, Excel, PowerPoint, RTF) หรือนามสกุล `pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, md, rtf` → `FileCategory::Document`
  - นอกเหนือจากนี้ทั้งหมด (เช่น `.zip`, `.mp3`, `.exe`, `.psd`, `.json` ฯลฯ) → `FileCategory::Other`
- **ตัวกรองหมวดหมู่บนหน้าจอ:** `resources/js/Pages/Files/Index.svelte` (ค้นหา `categoryInput` หรือ `<option value="">ทุกหมวดหมู่</option>`)

### 2.3 สิทธิ์การเข้าถึงไฟล์และโฟลเดอร์ (Authorization & Roles)
- **บทบาทผู้ใช้ (Roles):** `app/Enums/UserRole.php` (`admin`, `dept_manager`, `user`)
- **ประเภทพื้นที่เก็บไฟล์ (Root Types):** `app/Enums/RootType.php`
  - `personal` = พื้นที่ส่วนตัว
  - `department` = พื้นที่แผนก (ต้องมี `department_id`)
  - `shared` = พื้นที่ส่วนกลาง (ทุกคนดูได้; Admin/DeptManager จัดการได้; โฟลเดอร์ `is_dropzone = true` ให้ทุกคนอัปโหลดได้)
- **ตัวตรวจสิทธิ์กลาง:** `app/Policies/NodePolicy.php`
  - `view()` / `download()` — ตรวจสิทธิ์ดูและดาวน์โหลด (เช็กไล่จาก Admin → Owner → Shared → Department → Active Share → Approved Access Request)
  - `upload()` — ตรวจสิทธิ์อัปโหลด (รวมข้อยกเว้น Public Dropzone)
  - `update()` — ตรวจสิทธิ์แก้ไข/เปลี่ยนชื่อ/ย้าย
  - `delete()` / `restore()` / `forceDelete()` — ตรวจสิทธิ์ลบ/กู้คืน/ลบถาวร
  - `share()` — ตรวจสิทธิ์แชร์ไฟล์
- **Middleware ตรวจบทบาทและสถานะบัญชี:**
  - `app/Http/Middleware/EnsureUserHasRole.php` (ใช้กับ `role:admin`)
  - `app/Http/Middleware/EnsureUserIsActive.php` (กันบัญชีที่ถูกปิดการใช้งาน)

### 2.4 ถังขยะและงานตั้งเวลาอัตโนมัติ (Trash & Scheduled Tasks)
- **จำนวนวันเก็บไฟล์ในถังขยะ:** `TRASH_RETENTION_DAYS = 30` ใน `app/Http/Controllers/TrashController.php`
- **ตารางเวลาทำงานอัตโนมัติ:** `routes/console.php`
  - `01:00` — `nas:purge-trash` (`app/Console/Commands/PurgeExpiredTrashCommand.php`) ลบไฟล์หมดอายุในถังขยะถาวร
  - `02:00` — `nas:recalculate-quotas` (`app/Console/Commands/RecalculateUserQuotasCommand.php`) คำนวณพื้นที่ใช้งานของผู้ใช้ใหม่
  - `08:00` — `nas:remind-access-requests` (`app/Console/Commands/SendAccessRequestRemindersCommand.php`) ส่งอีเมลเตือนคำขอเข้าถึงไฟล์ที่ค้างอยู่

### 2.5 แดชบอร์ดผู้ดูแลระบบ (Admin Dashboard & RAID/SMART)
- **Backend:** `app/Http/Controllers/Admin/DashboardController.php`
  - หากตาราง `raid_pools` ในฐานข้อมูลว่าง ระบบจะคืนข้อมูลจำลองใน `getRaidAndDiskData()` แต่ใช้ยอดพื้นที่ใช้งานจริง (`used_bytes`) จากไฟล์ที่อัปโหลดในระบบ
- **Frontend:** `resources/js/Pages/Admin/Dashboard.svelte`

### 2.6 ระบบอัปโหลดแบบแบ่งชิ้น (Chunk Upload), ขนาดไฟล์สูงสุด และทำไมต้องส่งทีละ 2 MB
- **ไฟล์ควบคุมหลัก:**
  - Frontend: `resources/js/stores/uploadManager.svelte.js` (ตัวจัดการอัปโหลดระดับ Global รองรับ Pause/Resume, จับเวลา, คำนวณเวลาที่คาดว่าจะเสร็จ) และ `resources/js/Components/UploadProgressModal.svelte` (หน้าต่างลอยมุมขวาล่าง ทำงานอื่นหรือเปลี่ยนหน้าได้ระหว่างอัปโหลด)
  - Backend: `app/Http/Controllers/FileManagerController.php` (`uploadChunk`, `completeChunkUpload`, `cancelChunkUpload`)
- **ทำไมต้องหั่นส่งทีละ 2 MB แทนการส่งรวดเดียวทั้งไฟล์?**
  1. **เลี่ยงลิมิตของ PHP / Web Server:** ค่า PHP ของเครื่องตั้ง `upload_max_filesize = 40M` และ `post_max_size = 40M` หากส่งรวดเดียว ไฟล์ที่ใหญ่กว่า 40 MB จะอัปโหลดไม่ผ่านทันที
  2. **รองรับการกดหยุดชั่วคราว (Pause) และอัปโหลดต่อ (Resume):** HTTP Request เดียวไม่สามารถหยุดกลางทางแล้วต่อได้ (ทำได้แค่ยกเลิกแล้วเริ่มจาก `0%`) การหั่นเป็นชิ้นทำให้ชิ้นที่ส่งแล้วถูกบันทึกไว้ในไฟล์ `.part` บนเซิร์ฟเวอร์ พอกดต่อจึงส่งเฉพาะชิ้นที่เหลือได้
  3. **ลดความเสี่ยงเมื่อเน็ตสะดุดหรือติด Timeout:** ไฟล์ใหญ่ระดับหลาย GB จะไม่พังทั้งหมดเมื่อการเชื่อมต่อหลุดกลางทาง
  4. **ความละเอียดและจุดสมดุล:** ระบบส่งขนานทีละ 2 ชิ้น (`PARALLEL_CHUNKS = 2`) เพื่อให้ท่อข้อมูลวิ่งต่อเนื่อง และขนาด `2 MB` ทำให้ตอนกดหยุดชั่วคราวหยุดตรง `%` เดิมได้แม่นยำ *(สามารถปรับขนาดชิ้นได้ที่ `CHUNK_SIZE_BYTES` ใน `resources/js/stores/uploadManager.svelte.js` แต่ต้องไม่เกินลิมิตฝั่ง Backend `max:32768` = 32 MB และค่า `php.ini`)*
- **ขนาดไฟล์ใหญ่สุดที่อัปโหลด/ดาวน์โหลดได้:**
  - **อัปโหลด (Chunk Upload):** ไม่ได้ล็อกเพดานขนาดต่อไฟล์ในโค้ด แต่จำกัดตาม **โควต้าพื้นที่ของผู้ใช้** (ค่าเริ่มต้นสมัครใหม่ `10 GB` หรือ Admin ตั้ง `0 GB` = ไม่จำกัดในหน้า `/admin/users`) และ **พื้นที่ว่างบนดิสก์ของเซิร์ฟเวอร์** *(หมายเหตุ: ไฟล์ใหญ่มากระดับ > 10–20 GB อาจใช้เวลาคำนวณ `sha256` ใน `finalizeChunkUploadFile()` นาน ควรดูค่า `max_execution_time` ของ PHP ประกอบ)*
  - **ดาวน์โหลด:** ไม่จำกัดขนาดไฟล์ เนื่องจากส่งแบบ Stream (`Storage::disk('local')->download(...)`) ไม่ได้โหลดทั้งไฟล์เข้า RAM

---

## 3. โครงสร้างโฟลเดอร์หลัก

```text
app/
├── Actions/Fortify/      # ตรรกะสมัครสมาชิก, เปลี่ยน/รีเซ็ตรหัสผ่าน, กฎรหัสผ่าน
├── Console/Commands/     # คำสั่ง Artisan สำหรับ Cron Job (ล้างถังขยะ, คำนวณโควต้า, เตือนคำขอ)
├── Enums/                # ค่าคงที่แบบ Enum (FileCategory, UserRole, RootType, NodeType ฯลฯ)
├── Http/
│   ├── Controllers/      # ตัวควบคุมหลัก (FileManager, Share, AccessRequest, Trash, Profile)
│   │   └── Admin/        # ตัวควบคุมฝั่ง Admin (Dashboard, User, Department, AuditLog)
│   ├── Middleware/       # HandleInertiaRequests, EnsureUserHasRole, EnsureUserIsActive
│   └── Requests/         # Form Request Validation
├── Mail/                 # คลาสอีเมลแจ้งเตือนคำขอเข้าถึงไฟล์
├── Models/               # Eloquent Models (User, Node, Department, Share, AccessRequest, AuditLog ฯลฯ)
├── Policies/             # NodePolicy (กฎสิทธิ์ไฟล์/โฟลเดอร์ทั้งหมด)
└── Providers/            # AppServiceProvider, FortifyServiceProvider (Login/Lockout/Views)

resources/js/
├── Components/           # คอมโพเนนต์ใช้ซ้ำ (FilePreviewModal, UploadProgressModal, ConfirmDeleteModal)
├── Layouts/              # โครงหน้าหลัก (AppLayout สำหรับผู้ใช้ทั่วไป, AdminLayout สำหรับแอดมิน)
├── Pages/                # หน้าจอทั้งหมด (Auth, Files, Shares, AccessRequests, Settings, Admin)
└── stores/               # สถานะระดับ Global (uploadManager.svelte.js)

routes/
├── web.php               # เส้นทางเว็บทั้งหมด
└── console.php           # ตารางเวลา Cron Job (Schedule)
```

---

## 4. คำสั่งที่ควรรันหลังแก้ไขโค้ด

- **หลังแก้ไฟล์ Svelte / CSS (Frontend):**
  ```bash
  npm run build
  ```
  *(หรือรัน `npm run dev` ค้างไว้ระหว่างพัฒนา)*
- **หลังแก้ไฟล์ PHP (จัดฟอร์แมตและรันเทสต์):**
  ```bash
  vendor/bin/pint --dirty
  php artisan test --compact
  ```
