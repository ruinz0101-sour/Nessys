<script>
import { router, useForm } from '@inertiajs/svelte';
import AdminLayout from '../../../Layouts/AdminLayout.svelte';

let { users = [], departments = [], filters = {} } = $props();

let search = $state('');
let roleFilter = $state('');
let deptFilter = $state('');

$effect(() => {
    search = filters.search ?? '';
    roleFilter = filters.role ?? '';
    deptFilter = filters.department_id !== undefined && filters.department_id !== null && filters.department_id !== ''
        ? String(filters.department_id)
        : '';
});

let showCreateModal = $state(false);
let editingUser = $state(null);
let quotaUser = $state(null);
let resetPasswordUser = $state(null);
let showDeptModal = $state(false);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'user',
    department_id: '',
    quota_gb: 10,
    is_active: true,
});

const editForm = useForm({
    name: '',
    email: '',
    role: 'user',
    department_id: '',
    quota_gb: 10,
    is_active: true,
});

const quotaForm = useForm({
    quota_gb: 0,
});

const resetPassForm = useForm({
    password: '',
    password_confirmation: '',
});

const deptForm = useForm({
    name: '',
    manager_id: '',
});

function applyFilters(e) {
    e?.preventDefault();
    router.get('/admin/users', {
        search: search || undefined,
        role: roleFilter || undefined,
        department_id: deptFilter || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    createForm.role = 'user';
    createForm.quota_gb = 10;
    createForm.is_active = true;
    showCreateModal = true;
}

function submitCreate(e) {
    e.preventDefault();
    createForm.transform((data) => ({
        ...data,
        department_id: data.role === 'admin' ? null : (data.department_id ? Number(data.department_id) : null),
    })).post('/admin/users', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal = false;
            createForm.reset();
        },
    });
}

function openEditModal(user) {
    editingUser = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.department_id = user.role === 'admin' ? '' : (user.department_id ? String(user.department_id) : '');
    editForm.quota_gb = user.quota_gb;
    editForm.is_active = user.is_active;
    editForm.clearErrors();
}

function submitEdit(e) {
    e.preventDefault();
    if (!editingUser) {
        return;
    }
    editForm.transform((data) => ({
        ...data,
        department_id: data.role === 'admin' ? null : (data.department_id ? Number(data.department_id) : null),
    })).put(`/admin/users/${editingUser.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingUser = null;
        },
    });
}

function openQuotaModal(user) {
    quotaUser = user;
    quotaForm.quota_gb = user.quota_gb;
    quotaForm.clearErrors();
}

function submitQuota(e) {
    e.preventDefault();
    if (!quotaUser) {
        return;
    }
    quotaForm.patch(`/admin/users/${quotaUser.id}/quota`, {
        preserveScroll: true,
        onSuccess: () => {
            quotaUser = null;
        },
    });
}

function openResetPassModal(user) {
    resetPasswordUser = user;
    resetPassForm.reset();
    resetPassForm.clearErrors();
}

function submitResetPass(e) {
    e.preventDefault();
    if (!resetPasswordUser) {
        return;
    }
    resetPassForm.put(`/admin/users/${resetPasswordUser.id}/reset-password`, {
        preserveScroll: true,
        onSuccess: () => {
            resetPasswordUser = null;
            resetPassForm.reset();
        },
    });
}

function toggleActive(user) {
    router.patch(`/admin/users/${user.id}/toggle-active`, {}, {
        preserveScroll: true,
    });
}

function submitDept(e) {
    e.preventDefault();
    deptForm.transform((data) => ({
        ...data,
        manager_id: data.manager_id ? Number(data.manager_id) : null,
    })).post('/admin/departments', {
        preserveScroll: true,
        onSuccess: () => {
            deptForm.reset();
            showDeptModal = false;
        },
    });
}

function formatBytes(bytes) {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`;
}

function roleBadgeClass(role) {
    if (role === 'admin') {
        return 'bg-indigo-100 text-indigo-700 border-indigo-200';
    }
    if (role === 'dept_manager') {
        return 'bg-amber-100 text-amber-800 border-amber-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
}

function roleLabel(role) {
    if (role === 'admin') {
        return 'ผู้ดูแลระบบ';
    }
    if (role === 'dept_manager') {
        return 'หัวหน้าแผนก';
    }
    return 'ผู้ใช้งานทั่วไป';
}
</script>

<AdminLayout title="จัดการผู้ใช้งานและแผนก">
    <div class="flex flex-col gap-6">
        <!-- Filter Toolbar & Action Buttons -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs flex flex-wrap items-center justify-between gap-4">
            <form onsubmit={applyFilters} class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                <div class="relative flex-1 min-w-[180px]">
                    <input
                        type="text"
                        bind:value={search}
                        placeholder="ค้นหาชื่อ หรือ อีเมล..."
                        class="w-full px-3.5 py-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <select
                    bind:value={roleFilter}
                    onchange={applyFilters}
                    class="px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">ทุกระดับสิทธิ์</option>
                    <option value="admin">ผู้ดูแลระบบ</option>
                    <option value="dept_manager">หัวหน้าแผนก</option>
                    <option value="user">ผู้ใช้ทั่วไป</option>
                </select>

                <select
                    bind:value={deptFilter}
                    onchange={applyFilters}
                    class="px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">ทุกแผนก</option>
                    {#each departments as dept (dept.id)}
                        <option value={String(dept.id)}>{dept.name}</option>
                    {/each}
                </select>

                <button
                    type="submit"
                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold cursor-pointer transition"
                >
                    ค้นหา
                </button>
            </form>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    onclick={() => (showDeptModal = true)}
                    class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer shadow-xs"
                >
                    + เพิ่มแผนก
                </button>
                <button
                    type="button"
                    onclick={openCreateModal}
                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition cursor-pointer shadow-xs"
                >
                    + เพิ่มผู้ใช้ใหม่
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-slate-900">รายชื่อผู้ใช้งานในระบบ</span>
                    <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-700 text-xs font-semibold">{users.length} คน</span>
                </div>
                <div class="text-xs text-slate-500">
                    ทั้งหมด {departments.length} แผนก
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-bold">
                            <th class="py-3 px-4">ชื่อ - อีเมล</th>
                            <th class="py-3 px-4">ระดับสิทธิ์</th>
                            <th class="py-3 px-4">แผนก</th>
                            <th class="py-3 px-4">พื้นที่ใช้งาน</th>
                            <th class="py-3 px-4">สถานะบัญชี</th>
                            <th class="py-3 px-4 text-right">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        {#each users as u (u.id)}
                            {@const uPercent = u.quota_bytes > 0 ? Math.min(100, Math.round((u.used_bytes / u.quota_bytes) * 100)) : 0}
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        {#if u.avatar_url}
                                            <img
                                                src={u.avatar_url}
                                                alt={u.name}
                                                class="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0"
                                            />
                                        {:else}
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0">
                                                {u.name ? u.name.trim().charAt(0).toUpperCase() : '?'}
                                            </div>
                                        {/if}
                                        <div>
                                            <div class="font-bold text-slate-900">{u.name}</div>
                                            <div class="text-[11px] text-slate-500">{u.email}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full border text-[11px] font-semibold {roleBadgeClass(u.role)}">
                                        {roleLabel(u.role)}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-medium">
                                    {#if u.role === 'admin'}
                                        <span class="text-slate-400 italic">ไม่ระบุ (Admin)</span>
                                    {:else}
                                        {u.department_name ?? '—'}
                                    {/if}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-mono text-slate-800 font-medium">
                                        {formatBytes(u.used_bytes)} <span class="text-slate-400">/</span> {u.quota_bytes > 0 ? `${u.quota_gb} GB` : 'ไม่จำกัด'}
                                    </div>
                                    {#if u.quota_bytes > 0}
                                        <div class="w-28 h-1.5 bg-slate-100 rounded-full overflow-hidden mt-1">
                                            <div class="h-full rounded-full {uPercent >= 90 ? 'bg-rose-500' : uPercent >= 75 ? 'bg-amber-500' : 'bg-indigo-500'}" style="width: {uPercent}%"></div>
                                        </div>
                                    {/if}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        {#if u.is_active}
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-700">
                                                ปกติ
                                            </span>
                                        {:else}
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600">
                                                ปิดใช้งาน
                                            </span>
                                        {/if}

                                        {#if u.is_locked}
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 text-rose-700">
                                                ล็อกชั่วคราว
                                            </span>
                                        {/if}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                        <button
                                            type="button"
                                            onclick={() => openEditModal(u)}
                                            class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                                        >
                                            แก้ไข
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() => openQuotaModal(u)}
                                            class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition cursor-pointer"
                                        >
                                            ตั้งโควต้า
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() => openResetPassModal(u)}
                                            class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-amber-50 hover:bg-amber-100 text-amber-700 transition cursor-pointer"
                                        >
                                            รหัสผ่าน
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() => toggleActive(u)}
                                            class="px-2.5 py-1 rounded-md text-[11px] font-medium transition cursor-pointer {u.is_active ? 'bg-rose-50 hover:bg-rose-100 text-rose-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700'}"
                                        >
                                            {u.is_active ? 'ระงับ' : 'เปิดใช้'}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: เพิ่มผู้ใช้ใหม่ -->
    {#if showCreateModal}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-base font-bold text-slate-900 mb-4">เพิ่มผู้ใช้งานใหม่</h3>
                <form onsubmit={submitCreate} class="flex flex-col gap-4 text-xs">
                    <div>
                        <label for="create_name" class="block font-medium text-slate-700 mb-1">ชื่อ - นามสกุล</label>
                        <input id="create_name" type="text" bind:value={createForm.name} required placeholder="ระบุชื่อจริง..." class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if createForm.errors.name}
                            <p class="mt-1 text-xs text-rose-600">{createForm.errors.name}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="create_email" class="block font-medium text-slate-700 mb-1">อีเมล</label>
                        <input id="create_email" type="email" bind:value={createForm.email} required placeholder="email@company.local" class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if createForm.errors.email}
                            <p class="mt-1 text-xs text-rose-600">{createForm.errors.email}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="create_password" class="block font-medium text-slate-700 mb-1">รหัสผ่านเริ่มต้น</label>
                        <input id="create_password" type="password" bind:value={createForm.password} required placeholder="อย่างน้อย 8 ตัวอักษร" class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if createForm.errors.password}
                            <p class="mt-1 text-xs text-rose-600">{createForm.errors.password}</p>
                        {/if}
                    </div>
                    <div class="grid grid-cols-1 {createForm.role === 'admin' ? '' : 'sm:grid-cols-2'} gap-3">
                        <div>
                            <label for="create_role" class="block font-medium text-slate-700 mb-1">ระดับสิทธิ์</label>
                            <select
                                id="create_role"
                                bind:value={createForm.role}
                                onchange={() => {
                                    if (createForm.role === 'admin') {
                                        createForm.department_id = '';
                                    }
                                }}
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="user">ผู้ใช้ทั่วไป</option>
                                <option value="dept_manager">หัวหน้าแผนก</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        {#if createForm.role !== 'admin'}
                            <div>
                                <label for="create_dept" class="block font-medium text-slate-700 mb-1">สังกัดแผนก</label>
                                <select id="create_dept" bind:value={createForm.department_id} class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">- ไม่ระบุ -</option>
                                    {#each departments as dept (dept.id)}
                                        <option value={String(dept.id)}>{dept.name}</option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                    </div>
                    <div>
                        <label for="create_quota" class="block font-medium text-slate-700 mb-1">โควต้าพื้นที่ — ระบุ 0 หากไม่จำกัด</label>
                        <input id="create_quota" type="number" step="0.5" min="0" bind:value={createForm.quota_gb} required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex justify-end gap-2.5 pt-2">
                        <button type="button" onclick={() => (showCreateModal = false)} class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={createForm.processing} class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                            บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: แก้ไขข้อมูลผู้ใช้ -->
    {#if editingUser}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-lg w-full p-6 shadow-2xl animate-fade-up">
                <div class="border-b border-slate-200 pb-3 mb-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">แก้ไขข้อมูลผู้ใช้: {editingUser.name}</h3>
                    <span class="text-xs text-slate-400">ID #{editingUser.id}</span>
                </div>
                <form onsubmit={submitEdit} class="flex flex-col gap-4 text-xs">
                    <div>
                        <label for="edit_name" class="block font-medium text-slate-700 mb-1">ชื่อ - นามสกุล</label>
                        <input id="edit_name" type="text" bind:value={editForm.name} required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if editForm.errors.name}
                            <p class="mt-1 text-xs text-rose-600">{editForm.errors.name}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="edit_email" class="block font-medium text-slate-700 mb-1">อีเมล</label>
                        <input id="edit_email" type="email" bind:value={editForm.email} required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if editForm.errors.email}
                            <p class="mt-1 text-xs text-rose-600">{editForm.errors.email}</p>
                        {/if}
                    </div>
                    <div class="grid grid-cols-1 {editForm.role === 'admin' ? '' : 'sm:grid-cols-2'} gap-3">
                        <div>
                            <label for="edit_role" class="block font-medium text-slate-700 mb-1">ระดับสิทธิ์</label>
                            <select
                                id="edit_role"
                                bind:value={editForm.role}
                                onchange={() => {
                                    if (editForm.role === 'admin') {
                                        editForm.department_id = '';
                                    }
                                }}
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="user">ผู้ใช้ทั่วไป</option>
                                <option value="dept_manager">หัวหน้าแผนก</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>
                        {#if editForm.role !== 'admin'}
                            <div>
                                <label for="edit_dept" class="block font-medium text-slate-700 mb-1">แผนก</label>
                                <select id="edit_dept" bind:value={editForm.department_id} class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">- ไม่ระบุ -</option>
                                    {#each departments as dept (dept.id)}
                                        <option value={String(dept.id)}>{dept.name}</option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                    </div>
                    <div>
                        <label for="edit_quota" class="block font-medium text-slate-700 mb-1">โควต้าพื้นที่ — 0 หากไม่จำกัด</label>
                        <input id="edit_quota" type="number" step="0.5" min="0" bind:value={editForm.quota_gb} required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                        <input type="checkbox" bind:checked={editForm.is_active} class="rounded border-slate-300 text-indigo-600" />
                        <span>เปิดใช้งานบัญชีนี้</span>
                    </label>
                    <div class="flex justify-end gap-2.5 pt-2">
                        <button type="button" onclick={() => (editingUser = null)} class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={editForm.processing} class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                            บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: ตั้งค่า Quota -->
    {#if quotaUser}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-sm w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-slate-900 mb-1">ตั้งค่าโควต้าพื้นที่จัดเก็บ</h3>
                <p class="text-xs text-slate-500 mb-4">{quotaUser.name} ({quotaUser.email})</p>
                <form onsubmit={submitQuota} class="flex flex-col gap-3.5 text-xs">
                    <div>
                        <label for="quota_gb_input" class="block font-medium text-slate-700 mb-1">พื้นที่จัดเก็บสูงสุด — ระบุ 0 หากไม่จำกัด</label>
                        <input id="quota_gb_input" type="number" step="0.5" min="0" bind:value={quotaForm.quota_gb} required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if quotaForm.errors.quota_gb}
                            <p class="mt-1 text-xs text-rose-600">{quotaForm.errors.quota_gb}</p>
                        {/if}
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (quotaUser = null)} class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={quotaForm.processing} class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                            บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: รีเซ็ตรหัสผ่าน -->
    {#if resetPasswordUser}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-sm w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-slate-900 mb-1">รีเซ็ตรหัสผ่านผู้ใช้งาน</h3>
                <p class="text-xs text-slate-500 mb-4">{resetPasswordUser.name} ({resetPasswordUser.email})</p>
                <form onsubmit={submitResetPass} class="flex flex-col gap-3.5 text-xs">
                    <div>
                        <label for="reset_pass" class="block font-medium text-slate-700 mb-1">รหัสผ่านใหม่</label>
                        <input id="reset_pass" type="password" bind:value={resetPassForm.password} required placeholder="อย่างน้อย 8 ตัวอักษร" class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if resetPassForm.errors.password}
                            <p class="mt-1 text-xs text-rose-600">{resetPassForm.errors.password}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="reset_pass_confirm" class="block font-medium text-slate-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
                        <input id="reset_pass_confirm" type="password" bind:value={resetPassForm.password_confirmation} required class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (resetPasswordUser = null)} class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={resetPassForm.processing} class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold cursor-pointer">
                            เปลี่ยนรหัสผ่าน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal: เพิ่มแผนก -->
    {#if showDeptModal}
        <div class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 max-w-sm w-full p-6 shadow-2xl animate-fade-up">
                <h3 class="text-sm font-bold text-slate-900 mb-3">สร้างแผนกใหม่</h3>
                <form onsubmit={submitDept} class="flex flex-col gap-3.5 text-xs">
                    <div>
                        <label for="dept_name" class="block font-medium text-slate-700 mb-1">ชื่อแผนก</label>
                        <input id="dept_name" type="text" bind:value={deptForm.name} required placeholder="เช่น IT, HR, การตลาด..." class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        {#if deptForm.errors.name}
                            <p class="mt-1 text-xs text-rose-600">{deptForm.errors.name}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="dept_manager" class="block font-medium text-slate-700 mb-1">หัวหน้าแผนก (ไม่บังคับ)</label>
                        <select id="dept_manager" bind:value={deptForm.manager_id} class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">- ไม่ระบุ -</option>
                            {#each users as u (u.id)}
                                <option value={String(u.id)}>{u.name} ({u.email})</option>
                            {/each}
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" onclick={() => (showDeptModal = false)} class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer">
                            ยกเลิก
                        </button>
                        <button type="submit" disabled={deptForm.processing} class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold cursor-pointer">
                            สร้างแผนก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</AdminLayout>
