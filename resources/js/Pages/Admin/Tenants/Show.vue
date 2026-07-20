<script setup>
    import { computed, ref } from "vue";
    import { router, Link, useForm } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import TextInput from "@/Components/TextInput.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import InputError from "@/Components/InputError.vue";
    import Modal from "@/Components/Modal.vue";
    import ConfirmationModal from "@/Components/ConfirmationModal.vue";
    import Ltr from "@/Components/Ltr.vue";
    import KpiStrip from "@/Pages/Admin/Tenants/Partials/KpiStrip.vue";
    import MembersTable from "@/Pages/Admin/Tenants/Partials/MembersTable.vue";
    import PlanAssignBar from "@/Pages/Admin/Tenants/Partials/PlanAssignBar.vue";
    import OverridesPanel from "@/Pages/Admin/Tenants/Partials/OverridesPanel.vue";
    import EntitlementsGrid from "@/Pages/Admin/Tenants/Partials/EntitlementsGrid.vue";

    const props = defineProps({
        tenant: Object,
        members: Array,
        roles: Array,
        subscription: { type: Object, default: null },
        plans: { type: Array, default: () => [] },
        overrides: { type: Array, default: () => [] },
        entitlements: { type: Array, default: () => [] },
    });

    // Tabs
    const STATUS_LABELS = { active: "Active", trialing: "Trialing", canceled: "Canceled" };
    const statusLabel = (status) => __(STATUS_LABELS[status] ?? status);

    const activeTab = ref("members");
    const liveOverridesCount = computed(() => props.overrides.filter((o) => o.is_live).length);
    const tabs = computed(() => [
        {
            key: "members",
            label: __("Members"),
            count: props.members.length,
            icon: "M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z",
        },
        {
            key: "subscription",
            label: __("Subscription & Features"),
            count: null,
            icon: "M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122",
        },
        {
            key: "overrides",
            label: __("Overrides"),
            count: liveOverridesCount.value,
            icon: "M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z",
        },
    ]);

    // Add user modal
    const showAddUser = ref(false);
    const addUserForm = useForm({ name: "", email: "", role_id: "" });

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
</script>

<template>
    <AdminLayout :title="tenant.name">
        <!-- Back link -->
        <Link
            :href="route('admin.tenants.index')"
            class="inline-flex items-center gap-x-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-4"
        >
            <svg class="h-4 w-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            {{ __('Back to Tenants') }}
        </Link>

        <!-- Tenant identity header -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-x-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-x-3">
                            <h2 class="text-2xl font-bold text-gray-800 dark:text-white truncate">{{ tenant.name }}</h2>
                            <span
                                class="px-2.5 py-0.5 text-[11px] font-bold rounded-full"
                                :class="tenant.is_active
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                    : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                            >
                                {{ tenant.is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-emerald-600 dark:text-emerald-400">
                            <Ltr>{{ tenant.slug }}.{{ $page.props.appDomain }}</Ltr>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-x-3">
                    <button
                        class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="showTransfer = true"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        {{ __('Transfer Ownership') }}
                    </button>
                    <button
                        class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="showAddUser = true"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        {{ __('Add User') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="mb-6">
            <KpiStrip
                :tenant="tenant"
                :members="members"
                :subscription="subscription"
                :plans="plans"
                :overrides="overrides"
                :entitlements="entitlements"
            />
        </div>

        <!-- Tabbed detail -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="flex gap-x-1 px-4 border-b border-gray-200 dark:border-gray-700 overflow-x-auto overflow-y-hidden">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="inline-flex items-center gap-x-2 px-4 py-3.5 -mb-px text-sm border-b-2 transition-colors duration-200 focus:outline-none whitespace-nowrap"
                    :class="activeTab === tab.key
                        ? 'border-emerald-500 text-gray-900 dark:text-white font-semibold'
                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium'"
                    @click="activeTab = tab.key"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                    </svg>
                    {{ tab.label }}
                    <span
                        v-if="tab.count != null"
                        class="inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 text-[10px] font-bold rounded-full"
                        :class="activeTab === tab.key
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                            : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                    >
                        <Ltr>{{ tab.count }}</Ltr>
                    </span>
                </button>
            </div>

            <!-- Members tab -->
            <MembersTable
                v-show="activeTab === 'members'"
                :members="members"
                :tenant="tenant"
                @impersonate="impersonate"
                @change-role="openChangeRole"
                @toggle-status="toggleUserStatus"
                @remove="confirmingRemove = $event"
            />

            <!-- Subscription & Features tab -->
            <div v-if="activeTab === 'subscription'" class="p-6 space-y-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="subscription">{{ subscription.plan_name }} · {{ statusLabel(subscription.status) }}</template>
                    <template v-else>{{ __('No active subscription (falls back to the default plan).') }}</template>
                </p>
                <PlanAssignBar :tenant="tenant" :subscription="subscription" :plans="plans" />
                <EntitlementsGrid :entitlements="entitlements" :columns="2" />
            </div>

            <!-- Overrides tab -->
            <div v-if="activeTab === 'overrides'" class="p-6">
                <OverridesPanel :tenant="tenant" :overrides="overrides" :entitlements="entitlements" />
            </div>
        </div>

        <!-- Add User Modal -->
        <Modal :show="showAddUser" max-width="lg" @close="showAddUser = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Add User to Tenant') }}</h3>
                <form class="space-y-4" @submit.prevent="submitAddUser">
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
                            class="w-full mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
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
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="addUserForm.processing"
                        >
                            {{ __('Add User') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Change Role Modal -->
        <Modal :show="!!changingRoleFor" max-width="sm" @close="changingRoleFor = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Change Role') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Changing role for') }} <strong>{{ changingRoleFor?.name }}</strong></p>
                <form class="space-y-4" @submit.prevent="submitChangeRole">
                    <div>
                        <select
                            v-model="roleForm.role_id"
                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
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
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="roleForm.processing"
                        >
                            {{ __('Update Role') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Transfer Ownership Modal -->
        <Modal :show="showTransfer" max-width="sm" @close="showTransfer = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ __('Transfer Ownership') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('The current owner will be demoted to Manager.') }}</p>
                <form class="space-y-4" @submit.prevent="submitTransfer">
                    <div>
                        <InputLabel :value="__('New Owner')" />
                        <select
                            v-model="transferForm.user_id"
                            class="w-full mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
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
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
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
                        {{ __('Cancel') }}
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
