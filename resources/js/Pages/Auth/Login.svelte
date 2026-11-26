<script>
import { Link, useForm } from '@inertiajs/svelte';

let { status = null } = $props();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(e) {
    e.preventDefault();
    form.post('/login', {
        onFinish: () => form.reset('password'),
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
                <h1 class="text-lg font-bold text-slate-900 tracking-tight">CloudStorage NAS</h1>
                <p class="text-xs text-slate-500">ระบบจัดเก็บและแชร์ไฟล์ภายในองค์กร</p>
            </div>
        </div>

        {#if status}
            <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-emerald-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <span>{status}</span>
            </div>
        {/if}

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
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-700">รหัสผ่าน</label>
                    <Link href="/forgot-password" class="text-xs font-medium text-sky-600 hover:text-sky-700 hover:underline">
                        ลืมรหัสผ่าน?
                    </Link>
                </div>
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
                        placeholder="กรอกรหัสผ่านของคุณ"
                        class="input-trust pl-10 py-2.5 text-sm"
                    />
                </div>
                {#if form.errors.password}
                    <p class="mt-1.5 text-xs text-rose-600 font-medium">{form.errors.password}</p>
                {/if}
            </div>

            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer select-none">
                <input type="checkbox" bind:checked={form.remember} class="rounded border-slate-300 text-orange-500 focus:ring-sky-500" />
                <span>จดจำการเข้าสู่ระบบ</span>
            </label>

            <button
                type="submit"
                disabled={form.processing}
                class="btn-primary w-full py-3 mt-1 text-sm"
            >
                {form.processing ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ'}
            </button>
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
