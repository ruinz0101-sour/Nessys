<!--
    หน้าสมัครสมาชิก (/register)
    - ตรรกะสร้างผู้ใช้และค่าเริ่มต้น (role, department_id = null, quota 10GB) อยู่ที่: app/Actions/Fortify/CreateNewUser.php
    - การส่ง props เข้าหน้านี้และการ Redirect หลังสมัคร อยู่ที่: app/Providers/FortifyServiceProvider.php
-->
<script>
import { Link, useForm } from '@inertiajs/svelte';

const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
});

function submit(e) {
    e.preventDefault();
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<div class="min-h-[100dvh] flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-4 font-sans text-slate-900 relative overflow-hidden">
    <!-- Subtle Ambient Gradient Orbs -->
    <div class="pointer-events-none absolute -top-24 -left-24 w-96 h-96 rounded-full bg-sky-500/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-amber-500/15 blur-3xl"></div>

    <div class="w-full max-w-md bg-white/95 backdrop-blur-xl rounded-2xl border border-white/20 shadow-2xl shadow-slate-950/40 p-8 relative z-10 animate-fade-up">
        <!-- Logo & Header -->
        <div class="flex items-center gap-3.5 mb-7">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-600 text-white font-bold text-base flex items-center justify-center shadow-lg shadow-orange-500/30">
                NAS
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900 tracking-tight">สมัครสมาชิก CloudStorage NAS</h1>
                <p class="text-xs text-slate-500">สร้างบัญชีผู้ใช้งานด้วยอีเมลและรหัสผ่าน</p>
            </div>
        </div>

        <form onsubmit={submit} class="flex flex-col gap-4">
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">อีเมลผู้ใช้งาน</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2" />
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                        </svg>
                    </span>
                    <input
                        id="email"
                        type="email"
                        bind:value={form.email}
                        required
                        placeholder="name@company.local"
                        class="input-trust pl-10 py-2.5 text-sm"
                    />
                </div>
                {#if form.errors.email}
                    <p class="mt-1.5 text-xs text-rose-600 font-medium">{form.errors.email}</p>
                {/if}
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">รหัสผ่าน</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input
                        id="password"
                        type="password"
                        bind:value={form.password}
                        required
                        placeholder="อย่างน้อย 8 ตัวอักษร"
                        class="input-trust pl-10 py-2.5 text-sm"
                    />
                </div>
                {#if form.errors.password}
                    <p class="mt-1.5 text-xs text-rose-600 font-medium">{form.errors.password}</p>
                {/if}
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1.5">ยืนยันรหัสผ่าน</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input
                        id="password_confirmation"
                        type="password"
                        bind:value={form.password_confirmation}
                        required
                        placeholder="กรอกรหัสผ่านอีกครั้ง"
                        class="input-trust pl-10 py-2.5 text-sm"
                    />
                </div>
            </div>

            <button
                type="submit"
                disabled={form.processing}
                class="btn-primary w-full py-3 mt-1 text-sm"
            >
                {form.processing ? 'กำลังสร้างบัญชี...' : 'สมัครสมาชิก'}
            </button>

            <div class="text-center pt-2 text-xs text-slate-600">
                มีบัญชีผู้ใช้งานอยู่แล้ว?
                <Link href="/login" class="font-semibold text-sky-600 hover:text-sky-700 hover:underline ml-1">
                    เข้าสู่ระบบ
                </Link>
            </div>
        </form>

        <div class="mt-7 pt-4 border-t border-slate-100 flex items-center justify-center gap-2 text-[11px] text-slate-400">
            <svg class="w-3.5 h-3.5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                <path d="m9 12 2 2 4-4" />
            </svg>
            <span>เชื่อมต่อปลอดภัยด้วยการเข้ารหัสข้อมูลภายในองค์กร</span>
        </div>
    </div>
</div>
