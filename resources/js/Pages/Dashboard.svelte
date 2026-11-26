<script>
import { Link, usePage } from '@inertiajs/svelte';
import AppLayout from '../Layouts/AppLayout.svelte';

const page = usePage();
let user = $derived(page.props.auth?.user);
</script>

<AppLayout title="แดชบอร์ด">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="text-xs font-medium text-slate-500 uppercase">ผู้ใช้งาน</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">{user?.name}</div>
            <div class="text-sm text-slate-500">{user?.email}</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="text-xs font-medium text-slate-500 uppercase">แผนกและสิทธิ์</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">
                {user?.department_name ?? 'ไม่สังกัดแผนก'}
            </div>
            <div class="text-sm text-slate-500">Role: {user?.role}</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col justify-between">
            <div>
                <div class="text-xs font-medium text-slate-500 uppercase">การจัดการบัญชี</div>
                <div class="mt-2 text-sm text-slate-600">จัดการรหัสผ่านและการตั้งค่าระบบ</div>
            </div>
            <div class="mt-4 flex gap-3">
                <Link
                    href="/settings/password"
                    class="px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-medium transition"
                >
                    เปลี่ยนรหัสผ่าน
                </Link>
                {#if user?.role === 'admin'}
                    <Link
                        href="/admin/users"
                        class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium transition"
                    >
                        จัดการผู้ใช้
                    </Link>
                {/if}
            </div>
        </div>
    </div>
</AppLayout>
