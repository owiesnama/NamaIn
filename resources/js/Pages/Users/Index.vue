<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    members: Array,
    invitations: Array,
    roles: Array,
});

const { can } = usePermissions();

// ── Filters ───────────────────────────────────────────────────────────────────
const search = ref('');
const filterRole = ref('');
const filterStatus = ref('');

const roleOptions = computed(() => {
    const seen = new Set();
    return props.members
        .filter(m => m.role && !seen.has(m.role) && seen.add(m.role))
        .map(m => ({ value: m.role, label: m.role }));
});

const filteredMembers = computed(() => {
    const q = search.value.toLowerCase();
    return props.members.filter(member => {
        const matchSearch = !q ||
            member.name?.toLowerCase().includes(q) ||
            member.email?.toLowerCase().includes(q);
        const matchRole = !filterRole.value || member.role === filterRole.value;
        const matchStatus = filterStatus.value === '' ||
            (filterStatus.value === 'active' ? member.is_active : !member.is_active);
        return matchSearch && matchRole && matchStatus;
    });
});

// ── Pagination ────────────────────────────────────────────────────────────────
const perPage = 15;
const currentPage = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredMembers.value.length / perPage)));

const paginatedMembers = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredMembers.value.slice(start, start + perPage);
});

watch([search, filterRole, filterStatus], () => { currentPage.value = 1; });

// ── Invite modal ──────────────────────────────────────────────────────────────
const showInviteModal = ref(false);
const inviteForm = useForm({ email: '', role_id: '' });

const submitInvite = () => {
    inviteForm.post(route('users.invite'), {
        preserveScroll: true,
        onSuccess: () => {
            showInviteModal.value = false;
            inviteForm.reset();
        },
    });
};

// ── Edit member modal ─────────────────────────────────────────────────────────
const editingMember = ref(null);
const editForm = useForm({ role_id: '', is_active: true });

const openEdit = (member) => {
    editingMember.value = member;
    editForm.role_id = member.role_id ?? '';
    editForm.is_active = member.is_active;
};

const submitEdit = () => {
    editForm.put(route('users.update', editingMember.value.id), {
        preserveScroll: true,
        onSuccess: () => { editingMember.value = null; },
    });
};

// ── Remove user ───────────────────────────────────────────────────────────────
const confirmingRemoval = ref(null);

const removeUser = () => {
    router.delete(route('users.destroy', confirmingRemoval.value.id), {
        preserveScroll: true,
        onSuccess: () => { confirmingRemoval.value = null; },
    });
};

// ── Create user directly ──────────────────────────────────────────────────────
const showCreateModal = ref(false);
const createForm = useForm({ name: '', email: '', role_id: '' });
const createdUserCredentials = ref(null);

const submitCreate = () => {
    createForm.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            const flash = usePage().props.flash;
            if (flash?.createdUser) {
                createdUserCredentials.value = flash.createdUser;
            }
        },
    });
};

// ── Cancel invitation ─────────────────────────────────────────────────────────
const confirmingCancelInvite = ref(null);

const cancelInvitation = () => {
    router.delete(route('users.invitations.cancel', confirmingCancelInvite.value.id), {
        preserveScroll: true,
        onSuccess: () => { confirmingCancelInvite.value = null; },
    });
};

// ── Role badge colours ────────────────────────────────────────────────────────
const roleBadgeClass = (slug) => {
    const map = {
        owner:   'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/20 dark:text-purple-400 dark:border-purple-800',
        manager: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800',
        cashier: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
        staff:   'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
    };
    return map[slug] ?? map.staff;
};
</script>

<template>
    <AppLayout :title="__('Team Members')">
        <!-- ── Page header ──────────────────────────────────────────────────── -->
        <div class="mb-6 w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Team Members') }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ filteredMembers.length }} {{ __('Members') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Manage who has access to your organization.') }}</p>
            </div>
            <div class="mt-4 flex items-center justify-end gap-x-3 lg:mt-0">
                <button
                    v-if="can('users.invite')"
                    class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                    @click="showInviteModal = true"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                    {{ __('Invite Member') }}
                </button>
                <button
                    v-if="can('users.invite')"
                    class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                    @click="showCreateModal = true"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>
                    {{ __('Add User') }}
                </button>
            </div>
        </div>

        <!-- ── Filter bar ──────────────────────────────────────────────────── -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-48">
                <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <input
                    v-model="search"
                    type="text"
                    :placeholder="__('Search by name or email...')"
                    class="w-full ps-9 pe-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                />
            </div>

            <!-- Role filter -->
            <select
                v-model="filterRole"
                class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
            >
                <option value="">{{ __('All Roles') }}</option>
                <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <!-- Status filter -->
            <select
                v-model="filterStatus"
                class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
            >
                <option value="">{{ __('All Statuses') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="disabled">{{ __('Disabled') }}</option>
            </select>

            <!-- Clear filters -->
            <button
                v-if="search || filterRole || filterStatus"
                class="inline-flex items-center gap-x-1.5 px-3 py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200"
                @click="search = ''; filterRole = ''; filterStatus = '';"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                {{ __('Clear') }}
            </button>
        </div>

        <!-- ── Members table ───────────────────────────────────────────────── -->
        <div class="mb-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Member') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Role') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Joined') }}</th>
                            <th v-if="can('users.manage') || can('users.assign-role')" class="px-6 py-4" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr v-for="member in paginatedMembers" :key="member.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <!-- Member info -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-x-3">
                                    <img
                                        v-if="member.profile_photo_url"
                                        :src="member.profile_photo_url"
                                        class="h-8 w-8 rounded-full object-cover"
                                        :alt="member.name"
                                    />
                                    <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        {{ member.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ member.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ member.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role badge -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border"
                                    :class="roleBadgeClass(member.role)"
                                >
                                    {{ member.role }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border"
                                    :class="member.is_active
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                                        : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="member.is_active ? 'bg-emerald-500' : 'bg-red-500'" />
                                    {{ member.is_active ? __('Active') : __('Disabled') }}
                                </span>
                            </td>

                            <!-- Joined date -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ member.joined_at ? new Date(member.joined_at).toLocaleDateString() : '—' }}
                            </td>

                            <!-- Actions -->
                            <td v-if="can('users.manage') || can('users.assign-role')" class="px-6 py-4 whitespace-nowrap text-end">
                                <div v-if="member.role !== 'owner'" class="flex items-center justify-end gap-x-2">
                                    <button
                                        class="inline-flex items-center justify-center gap-x-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200"
                                        @click="openEdit(member)"
                                    >
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                        </svg>
                                        {{ __('Edit') }}
                                    </button>

                                    <button
                                        v-if="can('users.manage')"
                                        class="inline-flex items-center justify-center gap-x-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-900 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                        @click="confirmingRemoval = member"
                                    >
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                        {{ __('Remove') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="filteredMembers.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                {{ search || filterRole || filterStatus ? __('No members match your filters.') : __('No members found.') }}
            </div>
        </div>

        <!-- ── Pagination ──────────────────────────────────────────────────── -->
        <div v-if="totalPages > 1" class="flex flex-wrap items-center gap-1 mb-8">
            <!-- Prev -->
            <button
                class="px-3 py-2 text-sm leading-4 border rounded-lg transition-colors duration-200"
                :class="currentPage === 1
                    ? 'text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                    : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
                :disabled="currentPage === 1"
                @click="currentPage--"
            >
                {{ __('Prev') }}
            </button>

            <!-- Pages -->
            <button
                v-for="page in totalPages"
                :key="page"
                class="px-3 py-2 text-sm leading-4 border rounded-lg transition-colors duration-200"
                :class="page === currentPage
                    ? 'bg-emerald-600 text-white border-emerald-600'
                    : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
                @click="currentPage = page"
            >
                {{ page }}
            </button>

            <!-- Next -->
            <button
                class="px-3 py-2 text-sm leading-4 border rounded-lg transition-colors duration-200"
                :class="currentPage === totalPages
                    ? 'text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                    : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
                :disabled="currentPage === totalPages"
                @click="currentPage++"
            >
                {{ __('Next') }}
            </button>
        </div>

        <!-- ── Pending Invitations ─────────────────────────────────────────── -->
        <div v-if="invitations.length > 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Pending Invitations') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Email') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Role') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Invited By') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Expires') }}</th>
                            <th v-if="can('users.invite')" class="px-6 py-4" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr v-for="inv in invitations" :key="inv.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ inv.email }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700">
                                    {{ inv.role.name }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ inv.invited_by }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ new Date(inv.expires_at).toLocaleDateString() }}</td>
                            <td v-if="can('users.invite')" class="px-6 py-4 whitespace-nowrap text-end">
                                <button
                                    class="inline-flex items-center justify-center gap-x-1.5 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-900 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                    @click="confirmingCancelInvite = inv"
                                >
                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                    {{ __('Cancel') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Edit Member Modal ──────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="editingMember" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="editingMember = null" />
                    <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100 translate-y-0 sm:scale-100" leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div v-if="editingMember" class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
                            <div class="flex items-center gap-x-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-800">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-base font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    {{ editingMember.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ editingMember.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ editingMember.email }}</p>
                                </div>
                            </div>

                            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ __('Edit Member') }}</h3>

                            <form @submit.prevent="submitEdit" class="space-y-4">
                                <div v-if="can('users.assign-role')">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __('Role') }}</label>
                                    <select
                                        v-model="editForm.role_id"
                                        class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                    >
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                    <p v-if="editForm.errors.role_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ editForm.errors.role_id }}</p>
                                </div>

                                <div v-if="can('users.manage')">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-2">{{ __('Status') }}</label>
                                    <div class="flex items-center gap-x-4">
                                        <label class="flex items-center gap-x-2 cursor-pointer">
                                            <input type="radio" v-model="editForm.is_active" :value="true" class="text-emerald-600 border-gray-300 dark:border-gray-600 focus:ring-emerald-200" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Active') }}</span>
                                        </label>
                                        <label class="flex items-center gap-x-2 cursor-pointer">
                                            <input type="radio" v-model="editForm.is_active" :value="false" class="text-emerald-600 border-gray-300 dark:border-gray-600 focus:ring-emerald-200" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Disabled') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-x-3 pt-2">
                                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="editingMember = null">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" :disabled="editForm.processing" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                                        {{ __('Save Changes') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Invite modal ────────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="showInviteModal = false" />
                    <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100 translate-y-0 sm:scale-100" leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div v-if="showInviteModal" class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ __('Invite a Member') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ __('An invitation email will be sent to the provided address.') }}</p>

                            <form @submit.prevent="submitInvite" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __('Email Address') }}</label>
                                    <input
                                        v-model="inviteForm.email"
                                        type="email"
                                        :placeholder="__('colleague@example.com')"
                                        class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                                    />
                                    <p v-if="inviteForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ inviteForm.errors.email }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __('Role') }}</label>
                                    <select
                                        v-model="inviteForm.role_id"
                                        class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                    >
                                        <option value="" disabled>{{ __('Select a role') }}</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                    <p v-if="inviteForm.errors.role_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ inviteForm.errors.role_id }}</p>
                                </div>

                                <div class="flex justify-end gap-x-3 mt-6">
                                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200" @click="showInviteModal = false">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" :disabled="inviteForm.processing" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                                        {{ __('Send Invitation') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Confirm remove modal ───────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmingRemoval" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="confirmingRemoval = null" />
                    <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm text-center">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Remove Member') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Are you sure you want to remove :name from the organization?', { name: confirmingRemoval?.name }) }}
                        </p>
                        <div class="mt-6 flex justify-center gap-x-3">
                            <button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="confirmingRemoval = null">
                                {{ __('Cancel') }}
                            </button>
                            <button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200" @click="removeUser">
                                {{ __('Remove') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Cancel invitation modal ────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmingCancelInvite" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="confirmingCancelInvite = null" />
                    <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm text-center">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-orange-100 dark:bg-orange-900/30">
                            <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Cancel Invitation') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Cancel the invitation sent to :email?', { email: confirmingCancelInvite?.email }) }}
                        </p>
                        <div class="mt-6 flex justify-center gap-x-3">
                            <button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="confirmingCancelInvite = null">
                                {{ __('Keep') }}
                            </button>
                            <button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200" @click="cancelInvitation">
                                {{ __('Cancel Invitation') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Create User modal ──────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="showCreateModal = false" />
                    <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100 translate-y-0 sm:scale-100" leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <div v-if="showCreateModal" class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ __('Add User') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ __('Create an account directly. A temporary password will be generated.') }}</p>

                            <form @submit.prevent="submitCreate" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __('Full Name') }}</label>
                                    <input
                                        v-model="createForm.name"
                                        type="text"
                                        :placeholder="__('Ahmed Mohammed')"
                                        class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                                    />
                                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.name }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __('Email Address') }}</label>
                                    <input
                                        v-model="createForm.email"
                                        type="email"
                                        :placeholder="__('colleague@example.com')"
                                        class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                                    />
                                    <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.email }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __('Role') }}</label>
                                    <select
                                        v-model="createForm.role_id"
                                        class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                    >
                                        <option value="" disabled>{{ __('Select a role') }}</option>
                                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                    </select>
                                    <p v-if="createForm.errors.role_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.role_id }}</p>
                                </div>

                                <!-- Notice -->
                                <div class="flex items-start gap-x-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3">
                                    <svg class="h-4 w-4 mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <p class="text-xs text-amber-700 dark:text-amber-400">{{ __('The user will be required to set a new password on their first login.') }}</p>
                                </div>

                                <div class="flex justify-end gap-x-3 pt-2">
                                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200" @click="showCreateModal = false">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" :disabled="createForm.processing" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                                        {{ __('Create User') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Created user credentials modal ────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="createdUserCredentials" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" />
                    <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white text-center">{{ __('User Created') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 text-center mb-5">{{ __('Share these credentials with the user. They will be prompted to change their password on first login.') }}</p>

                        <div class="space-y-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 p-4">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">{{ __('Name') }}</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ createdUserCredentials.name }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">{{ __('Email') }}</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ createdUserCredentials.email }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">{{ __('Temporary Password') }}</p>
                                <div class="flex items-center justify-between gap-x-2">
                                    <p class="text-sm font-mono font-semibold text-emerald-700 dark:text-emerald-400 tracking-wider">{{ createdUserCredentials.password }}</p>
                                    <button
                                        class="inline-flex items-center justify-center p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
                                        @click="navigator.clipboard.writeText(createdUserCredentials.password)"
                                        :title="__('Copy')"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            class="mt-5 w-full inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                            @click="createdUserCredentials = null"
                        >
                            {{ __('Done') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
