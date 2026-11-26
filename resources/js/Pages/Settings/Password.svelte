<script>
import { useForm } from '@inertiajs/svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

let saved = $state(false);

function submit(e) {
    e.preventDefault();
    saved = false;
    form.put('/user/password', {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            saved = true;
        },
    });
}
</script>

<AppLayout title="เปลี่ยนรหัสผ่าน">
    <div class="max-w-md bg-white rounded-[3px] border border-[#DDDDDD] p-5 shadow-[0_2px_12px_rgba(0,0,0,0.06)]">
        <h2 class="text-sm font-bold text-[#131921] mb-1">เปลี่ยนรหัสผ่านของคุณ</h2>
        <p class="text-xs text-[#555555] mb-5">ใช้รหัสผ่านที่มีความยาวอย่างน้อย 8 ตัวอักษรเพื่อความปลอดภัย</p>

        {#if saved}
            <div class="mb-4 p-3 rounded-[3px] bg-[#EBF7EE] border border-[#008000]/30 text-[#006600] text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-[#008000]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
                <span>เปลี่ยนรหัสผ่านเรียบร้อยแล้ว</span>
            </div>
        {/if}

        <form onsubmit={submit} class="flex flex-col gap-3.5">
            <div>
                <label for="current_password" class="block text-[11px] font-bold text-[#232F3E] mb-1">รหัสผ่านปัจจุบัน</label>
                <input
                    id="current_password"
                    type="password"
                    bind:value={form.current_password}
                    required
                    class="input-trust text-xs"
                />
                {#if form.errors.current_password}
                    <p class="mt-1 text-xs text-[#E53E3E]">{form.errors.current_password}</p>
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

            <div class="pt-2">
                <button
                    type="submit"
                    disabled={form.processing}
                    class="btn-primary w-full"
                >
                    {form.processing ? 'กำลังบันทึก...' : 'บันทึกรหัสผ่านใหม่'}
                </button>
            </div>
        </form>
    </div>
</AppLayout>
