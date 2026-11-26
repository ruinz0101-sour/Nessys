<script>
import { Link, useForm, usePage } from '@inertiajs/svelte';
import AdminLayout from '../../Layouts/AdminLayout.svelte';
import AppLayout from '../../Layouts/AppLayout.svelte';

const page = usePage();
let user = $derived(page.props.auth?.user);
let isAdmin = $derived(user?.role === 'admin');

const form = useForm({
    name: '',
    email: '',
    avatar: null,
    remove_avatar: false,
});

let fileInputRef = $state(null);
let previewUrl = $state(null);
let initializedUserId = $state(null);

$effect(() => {
    if (user && initializedUserId !== user.id) {
        form.name = user.name ?? '';
        form.email = user.email ?? '';
        initializedUserId = user.id;
    }
});

let displayAvatar = $derived.by(() => {
    if (previewUrl) {
        return previewUrl;
    }
    if (form.remove_avatar) {
        return null;
    }
    return user?.avatar_url ?? null;
});

function userInitial(name) {
    if (!name) {
        return '?';
    }
    return name.trim().charAt(0).toUpperCase();
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

function handleAvatarSelect(e) {
    const file = e.target.files?.[0] ?? null;
    if (!file) {
        return;
    }
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
    }
    form.avatar = file;
    form.remove_avatar = false;
    previewUrl = URL.createObjectURL(file);
}

function handleRemoveAvatar() {
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
    form.avatar = null;
    form.remove_avatar = true;
    if (fileInputRef) {
        fileInputRef.value = '';
    }
}

function submit(e) {
    e.preventDefault();
    form.post('/settings/profile', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
            form.avatar = null;
            form.remove_avatar = false;
            if (fileInputRef) {
                fileInputRef.value = '';
            }
        },
    });
}
</script>

{#snippet profileContent()}
    <div class="max-w-2xl mx-auto flex flex-col gap-6">
        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="h-24 bg-gradient-to-r {isAdmin ? 'from-indigo-600 via-violet-600 to-purple-600' : 'from-slate-800 via-sky-700 to-indigo-700'} relative"></div>
            <div class="px-6 pb-6">
                <div class="flex flex-wrap items-end justify-between gap-4 -mt-12 mb-6">
                    <div class="flex items-end gap-4">
                        <div class="relative group">
                            {#if displayAvatar}
                                <img
                                    src={displayAvatar}
                                    alt={form.name || user?.name}
                                    class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-md bg-slate-100"
                                />
                            {:else}
                                <div class="w-24 h-24 rounded-2xl border-4 border-white shadow-md bg-gradient-to-br {isAdmin ? 'from-indigo-500 to-violet-600' : 'from-sky-500 to-indigo-600'} text-white text-3xl font-bold flex items-center justify-center">
                                    {userInitial(form.name || user?.name)}
                                </div>
                            {/if}

                            <button
                                type="button"
                                onclick={() => fileInputRef?.click()}
                                class="absolute -bottom-2 -right-2 w-8 h-8 rounded-xl bg-slate-900 hover:bg-slate-800 text-white border-2 border-white flex items-center justify-center shadow-md transition cursor-pointer"
                                title="เปลี่ยนรูปโปรไฟล์"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" />
                                    <circle cx="12" cy="13" r="3" />
                                </svg>
                            </button>
                        </div>

                        <div class="pb-1">
                            <h2 class="text-lg font-bold text-slate-900">{user?.name}</h2>
                            <div class="flex items-center flex-wrap gap-2 mt-0.5">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {isAdmin ? 'bg-indigo-100 text-indigo-700' : 'bg-sky-100 text-sky-700'}">
                                    {roleLabel(user?.role)}
                                </span>
                                {#if user?.department_name}
                                    <span class="text-xs text-slate-500">แผนก {user.department_name}</span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <Link
                        href="/settings/password"
                        class="btn-secondary text-xs"
                    >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
                        </svg>
                        <span>เปลี่ยนรหัสผ่าน</span>
                    </Link>
                </div>

                <!-- Profile Edit Form -->
                <form onsubmit={submit} class="flex flex-col gap-5">
                    <!-- Avatar Controls -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold text-slate-800">รูปโปรไฟล์</div>
                            <div class="text-[11px] text-slate-500 mt-0.5">
                                รองรับไฟล์ JPG, PNG หรือ WEBP ขนาดไม่เกิน 2 MB
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                bind:this={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onchange={handleAvatarSelect}
                                class="hidden"
                            />
                            <button
                                type="button"
                                onclick={() => fileInputRef?.click()}
                                class="btn-secondary text-xs"
                            >
                                เลือกรูปใหม่
                            </button>
                            {#if displayAvatar}
                                <button
                                    type="button"
                                    onclick={handleRemoveAvatar}
                                    class="px-3 py-2 rounded-xl text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition cursor-pointer"
                                >
                                    ลบรูป
                                </button>
                            {/if}
                        </div>
                        {#if form.errors.avatar}
                            <p class="w-full text-xs text-rose-600 font-medium">{form.errors.avatar}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="profile_name" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                ชื่อ - นามสกุล
                            </label>
                            <input
                                id="profile_name"
                                type="text"
                                bind:value={form.name}
                                required
                                class="input-trust text-sm"
                            />
                            {#if form.errors.name}
                                <p class="mt-1.5 text-xs text-rose-600 font-medium">{form.errors.name}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="profile_email" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                อีเมล
                            </label>
                            <input
                                id="profile_email"
                                type="email"
                                bind:value={form.email}
                                required
                                class="input-trust text-sm"
                            />
                            {#if form.errors.email}
                                <p class="mt-1.5 text-xs text-rose-600 font-medium">{form.errors.email}</p>
                            {/if}
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="btn-primary px-6 py-2.5 text-xs"
                        >
                            {form.processing ? 'กำลังบันทึก...' : 'บันทึกโปรไฟล์'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
{/snippet}

{#if isAdmin}
    <AdminLayout title="แก้ไขโปรไฟล์">
        {@render profileContent()}
    </AdminLayout>
{:else}
    <AppLayout title="แก้ไขโปรไฟล์">
        {@render profileContent()}
    </AppLayout>
{/if}
