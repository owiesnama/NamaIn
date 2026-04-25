<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    roles: Array,
    permissions: Object,
    permissionGroups: Array,
});

// ── Collapsible cards ─────────────────────────────────────────────────────────
const expandedRoles = ref({});

const toggleRole = (id) => {
    expandedRoles.value = { ...expandedRoles.value, [id]: !expandedRoles.value[id] };
};

const isExpanded = (id) => !!expandedRoles.value[id];

// ── Permission group label translations ───────────────────────────────────────
const groupLabels = {
    users:     __('Users'),
    roles:     __('Roles'),
    products:  __('Products'),
    customers: __('Customers'),
    suppliers: __('Suppliers'),
    sales:     __('Sales'),
    purchases: __('Purchases'),
    inventory: __('Inventory'),
    pos:       __('POS'),
    expenses:  __('Expenses'),
    payments:  __('Payments'),
    treasury:  __('Treasury'),
    settings:  __('Settings'),
    reports:   __('Reports'),
};

// ── Create role ───────────────────────────────────────────────────────────────
const showCreateModal = ref(false);
const createForm = useForm({ name: '', slug: '', permission_ids: [] });

const submitCreate = () => {
    createForm.post(route('roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// ── Edit role ─────────────────────────────────────────────────────────────────
const editingRole = ref(null);
const editForm = useForm({ name: '', permission_ids: [] });

const openEdit = (role) => {
    editingRole.value = role;
    editForm.name = role.name;
    editForm.permission_ids = [...role.permission_ids];
};

const submitEdit = () => {
    editForm.put(route('roles.update', editingRole.value.id), {
        preserveScroll: true,
        onSuccess: () => { editingRole.value = null; },
    });
};

const togglePermission = (form, permId) => {
    const idx = form.permission_ids.indexOf(permId);
    if (idx === -1) {
        form.permission_ids.push(permId);
    } else {
        form.permission_ids.splice(idx, 1);
    }
};

// ── Delete role ───────────────────────────────────────────────────────────────
const confirmingDelete = ref(null);

const deleteRole = () => {
    router.delete(route('roles.destroy', confirmingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => { confirmingDelete.value = null; },
    });
};

// ── Auto-generate slug from name ──────────────────────────────────────────────
const onNameInput = () => {
    createForm.slug = createForm.name
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '');
};
</script>

<template>
    <AppLayout :title="__('Roles & Permissions')">
        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <div class="mb-8 w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Roles & Permissions') }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ roles.length }} {{ __('Roles') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Define what each role can do in your organization.') }}</p>
            </div>
            <div class="mt-4 flex items-center justify-end lg:mt-0">
                <button
                    class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                    @click="showCreateModal = true"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('New Role') }}
                </button>
            </div>
        </div>

        <!-- ── Roles list ─────────────────────────────────────────────────── -->
        <div class="space-y-3">
            <div
                v-for="role in roles"
                :key="role.id"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden"
            >
                <!-- Card header — always visible, click to toggle -->
                <button
                    type="button"
                    class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors duration-200"
                    @click="toggleRole(role.id)"
                >
                    <div class="flex items-center gap-x-3">
                        <!-- Expand/collapse chevron -->
                        <svg
                            class="h-4 w-4 text-gray-400 dark:text-gray-500 transition-transform duration-200 shrink-0"
                            :class="isExpanded(role.id) ? 'rotate-90' : 'rtl:-rotate-90'"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>

                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ role.name }}</h3>

                        <span
                            v-if="role.is_system"
                            class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400"
                        >{{ __('System') }}</span>
                    </div>

                    <div class="flex items-center gap-x-4">
                        <!-- Members count -->
                        <span class="hidden sm:flex items-center gap-x-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            {{ role.users_count }} {{ __('members') }}
                        </span>

                        <!-- Permissions count -->
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ role.permissions_count }} {{ __('permissions') }}</span>

                        <!-- Action buttons — stop propagation so clicking them doesn't toggle the card -->
                        <div class="flex items-center gap-x-1" @click.stop>
                            <button
                                v-if="role.slug !== 'owner'"
                                class="inline-flex items-center justify-center p-1.5 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                                :title="__('Edit role')"
                                @click="openEdit(role)"
                            >
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                </svg>
                            </button>
                            <button
                                v-if="!role.is_system"
                                class="inline-flex items-center justify-center p-1.5 text-red-500 border border-red-200 dark:border-red-900 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200"
                                :title="__('Delete role')"
                                @click="confirmingDelete = role"
                            >
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </button>

                <!-- Expandable permissions body -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div v-if="isExpanded(role.id)" class="px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                        <div class="space-y-3">
                            <div v-for="group in permissionGroups" :key="group">
                                <template v-if="permissions[group]?.some(p => role.permission_ids.includes(p.id))">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">
                                        {{ groupLabels[group] ?? group }}
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="perm in permissions[group]?.filter(p => role.permission_ids.includes(p.id))"
                                            :key="perm.id"
                                            class="px-2 py-0.5 text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800"
                                        >
                                            {{ __('perm.' + perm.slug) !== 'perm.' + perm.slug ? __('perm.' + perm.slug) : perm.description }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <p v-if="role.permissions_count === 0" class="text-sm text-gray-400 dark:text-gray-500">{{ __('No permissions assigned.') }}</p>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- ── Create Role Modal ──────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="showCreateModal = false" />
                    <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
                        <div v-if="showCreateModal" class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-2xl max-h-[85vh] overflow-y-auto">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ __('Create Role') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ __('Define the role name and select its permissions.') }}</p>

                            <form @submit.prevent="submitCreate" class="space-y-5">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Role Name') }}</label>
                                        <input v-model="createForm.name" type="text" @input="onNameInput" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                                        <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Slug') }}</label>
                                        <input v-model="createForm.slug" type="text" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                                        <p v-if="createForm.errors.slug" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.slug }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Permissions') }}</label>
                                    <div class="space-y-4">
                                        <div v-for="group in permissionGroups" :key="group">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ groupLabels[group] ?? group }}</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <label
                                                    v-for="perm in permissions[group]"
                                                    :key="perm.id"
                                                    class="flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        :value="perm.id"
                                                        :checked="createForm.permission_ids.includes(perm.id)"
                                                        class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                                        @change="togglePermission(createForm, perm.id)"
                                                    />
                                                    {{ __('perm.' + perm.slug) !== 'perm.' + perm.slug ? __('perm.' + perm.slug) : perm.description }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="createForm.errors.permission_ids" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.permission_ids }}</p>
                                </div>

                                <div class="flex justify-end gap-x-3 pt-2">
                                    <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="showCreateModal = false">{{ __('Cancel') }}</button>
                                    <button type="submit" :disabled="createForm.processing" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">{{ __('Create Role') }}</button>
                                </div>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Edit Role Modal ────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="editingRole" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="editingRole = null" />
                    <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-2xl max-h-[85vh] overflow-y-auto">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">{{ __('Edit Role: :name', { name: editingRole.name }) }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                            {{ editingRole.is_system ? __('System roles cannot be renamed, but you can customize their permissions.') : __('Update the role name and its permissions.') }}
                        </p>

                        <form @submit.prevent="submitEdit" class="space-y-5">
                            <div v-if="!editingRole.is_system">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Role Name') }}</label>
                                <input v-model="editForm.name" type="text" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ __('Permissions') }}</label>
                                <div class="space-y-4">
                                    <div v-for="group in permissionGroups" :key="group">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ groupLabels[group] ?? group }}</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label v-for="perm in permissions[group]" :key="perm.id" class="flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    :value="perm.id"
                                                    :checked="editForm.permission_ids.includes(perm.id)"
                                                    class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                                    @change="togglePermission(editForm, perm.id)"
                                                />
                                                {{ __('perm.' + perm.slug) !== 'perm.' + perm.slug ? __('perm.' + perm.slug) : perm.description }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-x-3 pt-2">
                                <button type="button" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="editingRole = null">{{ __('Cancel') }}</button>
                                <button type="submit" :disabled="editForm.processing" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Delete confirm ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmingDelete" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="confirmingDelete = null" />
                    <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-sm text-center">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/30">
                            <svg class="w-6 h-6 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Delete Role') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Are you sure you want to delete the ":name" role?', { name: confirmingDelete?.name }) }}</p>
                        <div class="mt-6 flex justify-center gap-x-3">
                            <button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200" @click="confirmingDelete = null">{{ __('Cancel') }}</button>
                            <button class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 transition-colors duration-200" @click="deleteRole">{{ __('Delete') }}</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
