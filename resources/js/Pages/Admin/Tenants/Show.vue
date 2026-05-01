<script setup>
    import { ref } from "vue";
    import { router, Link, useForm } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import TextInput from "@/Components/TextInput.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import InputError from "@/Components/InputError.vue";
    import Modal from "@/Components/Modal.vue";
    import ConfirmationModal from "@/Components/ConfirmationModal.vue";

    const props = defineProps({
        tenant: Object,
        members: Array,
        roles: Array,
    });

    // Add user modal
    const showAddUser = ref(false);
    const addUserForm = useForm({
        name: "",
        email: "",
        role_id: "",
    });

    const submitAddUser = () => {
        addUserForm.post(route("admin.tenants.users.store", props.tenant.id), {
            onSuccess: () => { showAddUser.value = false; addUserForm.reset(); },
            preserveScroll: true,
        });
    };

    // Change role
    const changingRoleFor = ref(null);
    const roleForm = useForm({ role_id: "" });

    const openChangeRole = (member) => {
        changingRoleFor.value = member;
        roleForm.role_id = member.role_id || "";
    };

    const submitChangeRole = () => {
        roleForm.put(route("admin.tenants.users.role", [props.tenant.id, changingRoleFor.value.id]), {
            onSuccess: () => { changingRoleFor.value = null; },
            preserveScroll: true,
        });
    };

    // Toggle user status
    const toggleUserStatus = (member) => {
        router.put(route("admin.tenants.users.status", [props.tenant.id, member.id]), {}, { preserveScroll: true });
    };

    // Remove user
    const confirmingRemove = ref(null);

    const removeUser = () => {
        router.delete(route("admin.tenants.users.destroy", [props.tenant.id, confirmingRemove.value.id]), {
            onSuccess: () => { confirmingRemove.value = null; },
            preserveScroll: true,
        });
    };

    // Transfer ownership
    const showTransfer = ref(false);
    const transferForm = useForm({ user_id: "" });

    const submitTransfer = () => {
        transferForm.put(route("admin.tenants.ownership", props.tenant.id), {
            onSuccess: () => { showTransfer.value = false; transferForm.reset(); },
            preserveScroll: true,
        });
    };

    // Impersonate
    const impersonate = (member) => {
        router.post(route("admin.impersonate.start", [props.tenant.id, member.id]));
    };

    // Force password change
    const forcePasswordChange = (member) => {
        router.put(route("admin.tenants.users.status", [props.tenant.id, member.id]), {
            force_password_change: true,
        }, { preserveScroll: true });
    };
</script>

<template>
    <AdminLayout :title="tenant.name">
        <!-- Back link + header -->
        <div class="mb-8">
            <Link
                :href="route('admin.tenants.index')"
                class="inline-flex items-center gap-x-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-4"
            >
                <svg class="h-4 w-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __('Back to Tenants') }}
            </Link>

            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ tenant.name }}</h2>
                        <span
                            class="px-2.5 py-0.5 text-[11px] font-bold rounded-full"
                            :class="tenant.is_active
                                ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                                : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                        >
                            {{ tenant.is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 font-mono">{{ tenant.slug }}.{{ $page.props.appDomain }}</p>
                </div>
                <div class="mt-4 flex items-center gap-x-3 lg:mt-0">
                    <button
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="showAddUser = true"
                    >
                        <svg class="h-4 w-4 ltr:mr-2 rtl:ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        {{ __('Add User') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Members table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ __('Members') }}
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ms-1">({{ members.length }})</span>
                </h3>
                <button
                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors duration-200"
                    @click="showTransfer = true"
                >
                    {{ __('Transfer Ownership') }}
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('User') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Role') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Joined') }}</th>
                            <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr
                            v-for="member in members"
                            :key="member.id"
                            class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-x-3">
                                    <img
                                        v-if="member.profile_photo_url"
                                        :src="member.profile_photo_url"
                                        :alt="member.name"
                                        class="h-8 w-8 rounded-full object-cover"
                                    />
                                    <div v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-xs font-bold text-gray-600 dark:text-gray-300">
                                        {{ member.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ member.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ member.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                    {{ member.role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 text-[11px] font-bold rounded-full"
                                    :class="member.is_active
                                        ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                                        : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                                >
                                    {{ member.is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ member.joined_at ? new Date(member.joined_at).toLocaleDateString() : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end">
                                <div class="flex items-center justify-end gap-x-2">
                                    <!-- Impersonate (all users) -->
                                    <button
                                        v-if="tenant.is_active"
                                        class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                        :title="__('Impersonate')"
                                        @click="impersonate(member)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                    <!-- Non-owner actions -->
                                    <template v-if="member.role !== 'owner'">
                                        <button
                                            class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                            :title="__('Change Role')"
                                            @click="openChangeRole(member)"
                                        >
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </button>
                                        <button
                                            class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                            :title="member.is_active ? __('Deactivate') : __('Activate')"
                                            @click="toggleUserStatus(member)"
                                        >
                                            <svg v-if="member.is_active" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                            </svg>
                                            <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                        </button>
                                        <button
                                            class="inline-flex items-center justify-center p-1.5 text-red-400 hover:text-red-600 dark:hover:text-red-300 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200 focus:outline-none"
                                            :title="__('Remove')"
                                            @click="confirmingRemove = member"
                                        >
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-8.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="members.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                {{ __('No members found.') }}
            </div>
        </div>

        <!-- Add User Modal -->
        <Modal :show="showAddUser" @close="showAddUser = false" max-width="lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Add User to Tenant') }}</h3>
                <form @submit.prevent="submitAddUser" class="space-y-4">
                    <div>
                        <InputLabel :value="__('Name')" />
                        <TextInput v-model="addUserForm.name" type="text" class="w-full mt-1" required />
                        <InputError :message="addUserForm.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="__('Email')" />
                        <TextInput v-model="addUserForm.email" type="email" class="w-full mt-1" required />
                        <InputError :message="addUserForm.errors.email" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="__('Role')" />
                        <select
                            v-model="addUserForm.role_id"
                            class="w-full mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
                            required
                        >
                            <option value="" disabled>{{ __('Select a role') }}</option>
                            <option v-for="role in roles.filter(r => r.slug !== 'owner')" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <InputError :message="addUserForm.errors.role_id" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-x-3 pt-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                            @click="showAddUser = false"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="addUserForm.processing"
                        >
                            {{ __('Add User') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Change Role Modal -->
        <Modal :show="!!changingRoleFor" @close="changingRoleFor = null" max-width="sm">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Change Role') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Changing role for') }} <strong>{{ changingRoleFor?.name }}</strong></p>
                <form @submit.prevent="submitChangeRole" class="space-y-4">
                    <div>
                        <select
                            v-model="roleForm.role_id"
                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
                            required
                        >
                            <option value="" disabled>{{ __('Select a role') }}</option>
                            <option v-for="role in roles.filter(r => r.slug !== 'owner')" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <InputError :message="roleForm.errors.role_id" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-x-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                            @click="changingRoleFor = null"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="roleForm.processing"
                        >
                            {{ __('Update Role') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Transfer Ownership Modal -->
        <Modal :show="showTransfer" @close="showTransfer = false" max-width="sm">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Transfer Ownership') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('The current owner will be demoted to Manager.') }}</p>
                <form @submit.prevent="submitTransfer" class="space-y-4">
                    <div>
                        <InputLabel :value="__('New Owner')" />
                        <select
                            v-model="transferForm.user_id"
                            class="w-full mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
                            required
                        >
                            <option value="" disabled>{{ __('Select a user') }}</option>
                            <option
                                v-for="member in members.filter(m => m.role !== 'owner')"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} ({{ member.email }})
                            </option>
                        </select>
                        <InputError :message="transferForm.errors.user_id" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-x-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                            @click="showTransfer = false"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="transferForm.processing"
                        >
                            {{ __('Transfer') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Remove User Confirmation -->
        <ConfirmationModal :show="!!confirmingRemove" @close="confirmingRemove = null">
            <template #title>{{ __('Remove User') }}</template>
            <template #content>
                {{ __('Are you sure you want to remove') }} <strong>{{ confirmingRemove?.name }}</strong> {{ __('from this tenant?') }}
            </template>
            <template #footer>
                <div class="flex justify-end gap-x-3">
                    <button
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="confirmingRemove = null"
                    >
                        Cancel
                    </button>
                    <button
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="removeUser"
                    >
                        {{ __('Remove') }}
                    </button>
                </div>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
