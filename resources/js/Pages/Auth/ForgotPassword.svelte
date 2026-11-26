<script>
import { Link, useForm } from '@inertiajs/svelte';

let { status = null } = $props();

const form = useForm({
    email: '',
});

function submit(e) {
    e.preventDefault();
    form.post('/forgot-password');
}
</script>

<div class="min-h-[100dvh] flex items-center justify-center bg-[#F7F9FA] p-4 font-sans text-[#131921]">
    <div class="w-full max-w-sm bg-white rounded-[3px] border border-[#DDDDDD] shadow-[0_2px_12px_rgba(0,0,0,0.06)] p-7">
        <h1 class="text-base font-bold text-[#131921] mb-1">ลืมรหัสผ่าน</h1>
        <p class="text-xs text-[#555555] mb-5 leading-relaxed">
            กรอกอีเมลของคุณเพื่อรับลิงก์สำหรับรีเซ็ตรหัสผ่านใหม่
        </p>

        {#if status}
            <div class="mb-4 p-3 rounded-[3px] bg-[#EBF7EE] border border-[#008000]/30 text-[#006600] text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-[#008000]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <span>{status}</span>
            </div>
        {/if}

        <form onsubmit={submit} class="flex flex-col gap-3.5">
            <div>
                <label for="email" class="block text-[11px] font-bold text-[#232F3E] mb-1">อีเมลผู้ใช้งาน</label>
                <input
                    id="email"
                    type="email"
                    bind:value={form.email}
                    required
                    class="input-trust text-xs"
                />
                {#if form.errors.email}
                    <p class="mt-1 text-xs text-[#E53E3E]">{form.errors.email}</p>
                {/if}
            </div>

            <button
                type="submit"
                disabled={form.processing}
                class="btn-primary w-full py-2.5 mt-1"
            >
                {form.processing ? 'กำลังส่งลิงก์...' : 'ส่งลิงก์รีเซ็ตรหัสผ่าน'}
            </button>

            <div class="text-center pt-2">
                <Link href="/login" class="text-xs font-medium text-[#00A8E1] hover:underline">
                    กลับไปหน้าเข้าสู่ระบบ
                </Link>
            </div>
        </form>
    </div>
</div>
