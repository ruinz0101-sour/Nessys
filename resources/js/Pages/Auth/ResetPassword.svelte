<script>
import { useForm } from '@inertiajs/svelte';

let { token, email = '' } = $props();

const form = useForm({
    token: token ?? '',
    email: email ?? '',
    password: '',
    password_confirmation: '',
});

function submit(e) {
    e.preventDefault();
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<div class="min-h-[100dvh] flex items-center justify-center bg-[#F7F9FA] p-4 font-sans text-[#131921]">
    <div class="w-full max-w-sm bg-white rounded-[3px] border border-[#DDDDDD] shadow-[0_2px_12px_rgba(0,0,0,0.06)] p-7">
        <h1 class="text-base font-bold text-[#131921] mb-1">ตั้งรหัสผ่านใหม่</h1>
        <p class="text-xs text-[#555555] mb-5">กำหนดรหัสผ่านใหม่สำหรับบัญชีของคุณ</p>

        <form onsubmit={submit} class="flex flex-col gap-3.5">
            <div>
                <label for="email" class="block text-[11px] font-bold text-[#232F3E] mb-1">อีเมล</label>
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

            <div>
                <label for="password" class="block text-[11px] font-bold text-[#232F3E] mb-1">รหัสผ่านใหม่</label>
                <input
                    id="password"
                    type="password"
                    bind:value={form.password}
                    required
                    class="input-trust text-xs"
                />
                {#if form.errors.password}
                    <p class="mt-1 text-xs text-[#E53E3E]">{form.errors.password}</p>
                {/if}
            </div>

            <div>
                <label for="password_confirmation" class="block text-[11px] font-bold text-[#232F3E] mb-1">ยืนยันรหัสผ่านใหม่</label>
                <input
                    id="password_confirmation"
                    type="password"
                    bind:value={form.password_confirmation}
                    required
                    class="input-trust text-xs"
                />
            </div>

            <button
                type="submit"
                disabled={form.processing}
                class="btn-primary w-full py-2.5 mt-1"
            >
                {form.processing ? 'กำลังบันทึก...' : 'บันทึกรหัสผ่านใหม่'}
            </button>
        </form>
    </div>
</div>
