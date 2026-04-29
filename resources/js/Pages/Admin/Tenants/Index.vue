<script setup>
    import { ref, watch } from "vue";
    import { router, Link, useForm } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import TextInput from "@/Components/TextInput.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import InputError from "@/Components/InputError.vue";
    import Modal from "@/Components/Modal.vue";
    import ConfirmationModal from "@/Components/ConfirmationModal.vue";

    const props = defineProps({
        tenants: Object,
        filters: Object,
    });

    const search = ref(props.filters?.search || "");
    const statusFilter = ref(props.filters?.status || "");
    let debounceTimer = null;

    watch([search, statusFilter], () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            router.get(route("admin.tenants.index"), {
                search: search.value || undefined,
                status: statusFilter.value || undefined,
            }, { preserveState: true, replace: true });
        }, 300);
    });

    // Create / Edit modal
    const showFormModal = ref(false);
    const editingTenant = ref(null);

    const form = useForm({
        name: "",
        slug: "",
        owner_email: "",
    });

    const openCreate = () => {
        editingTenant.value = null;
        form.reset();
        form.clearErrors();
        showFormModal.value = true;
    };

    const openEdit = (tenant) => {
        editingTenant.value = tenant;
        form.name = tenant.name;
        form.slug = tenant.slug;
        form.clearErrors();
        showFormModal.value = true;
    };

    const submitForm = () => {
        if (editingTenant.value) {
            form.put(route("admin.tenants.update", editingTenant.value.id), {
                onSuccess: () => { showFormModal.value = false; },
                preserveScroll: true,
            });
        } else {
            form.post(route("admin.tenants.store"), {
                onSuccess: () => { showFormModal.value = false; form.reset(); },
                preserveScroll: true,
            });
        }
    };

    // Toggle status
    const toggleStatus = (tenant) => {
        router.put(route("admin.tenants.status", tenant.id), {}, { preserveScroll: true });
    };

    // Delete
    const confirmingDelete = ref(null);

    const deleteTenant = () => {
        router.delete(route("admin.tenants.destroy", confirmingDelete.value.id), {
            onSuccess: () => { confirmingDelete.value = null; },
            preserveScroll: true,
        });
    };


</script>

<template>
    <AdminLayout title="Tenants">
        <!-- Page header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Tenants</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-gray-700 bg-gray-100 dark:bg-gray-800 dark:text-gray-300">
                        {{ tenants.total }} total
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage all organizations on the platform</p>
            </div>
            <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                <button
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                    @click="openCreate"
                >
                    <svg class="h-4 w-4 ltr:mr-2 rtl:ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Tenant
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-4 mb-6">
            <div class="w-full sm:w-64">
                <TextInput
                    v-model="search"
                    type="text"
                    placeholder="Search tenants..."
                    class="w-full"
                />
            </div>
            <select
                v-model="statusFilter"
                class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Name</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Slug</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Status</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Users</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Created</th>
                            <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr
                            v-for="tenant in tenants.data"
                            :key="tenant.id"
                            class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Link
                                    :href="route('admin.tenants.show', tenant.id)"
                                    class="text-sm font-medium text-gray-900 dark:text-white hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    {{ tenant.name }}
                                </Link>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">
                                {{ tenant.slug }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-full"
                                    :class="tenant.is_active
                                        ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                                        : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                                >
                                    {{ tenant.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                {{ tenant.users_count }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ new Date(tenant.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end">
                                <div class="flex items-center justify-end gap-x-2">
                                    <button
                                        class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                        title="Edit"
                                        @click="openEdit(tenant)"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button
                                        class="inline-flex items-center justify-center p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus:outline-none"
                                        :title="tenant.is_active ? 'Deactivate' : 'Activate'"
                                        @click="toggleStatus(tenant)"
                                    >
                                        <svg v-if="tenant.is_active" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="!tenant.is_active"
                                        class="inline-flex items-center justify-center p-1.5 text-red-400 hover:text-red-600 dark:hover:text-red-300 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200 focus:outline-none"
                                        title="Delete"
                                        @click="confirmingDelete = tenant"
                                    >
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-if="tenants.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                No tenants found.
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="tenants.links && tenants.links.length > 3" class="flex flex-wrap items-center gap-1 mt-8">
            <template v-for="link in tenants.links" :key="link.label">
                <component
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url"
                    class="px-4 py-2.5 text-sm leading-4 border rounded-md transition-colors duration-200"
                    :class="[
                        link.active
                            ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 border-gray-900 dark:border-white'
                            : link.url
                                ? 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'
                                : 'text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700 cursor-not-allowed'
                    ]"
                    v-html="link.label"
                    @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                />
            </template>
        </div>

        <!-- Create / Edit Modal -->
        <Modal :show="showFormModal" @close="showFormModal = false" max-width="lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ editingTenant ? 'Edit Tenant' : 'Create Tenant' }}
                </h3>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" type="text" class="w-full mt-1" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Slug" />
                        <TextInput v-model="form.slug" type="text" class="w-full mt-1 font-mono" required />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Used in the subdomain: slug.{{ $page.props.appDomain }}</p>
                        <InputError :message="form.errors.slug" class="mt-1" />
                    </div>
                    <div v-if="!editingTenant">
                        <InputLabel value="Owner Email" />
                        <TextInput v-model="form.owner_email" type="email" class="w-full mt-1" required />
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Must be an existing user</p>
                        <InputError :message="form.errors.owner_email" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-x-3 pt-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                            @click="showFormModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-gray-900 dark:bg-white dark:text-gray-900 border border-transparent rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            :disabled="form.processing"
                        >
                            {{ editingTenant ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Confirmation -->
        <ConfirmationModal :show="!!confirmingDelete" @close="confirmingDelete = null">
            <template #title>Delete Tenant</template>
            <template #content>
                Are you sure you want to permanently delete <strong>{{ confirmingDelete?.name }}</strong>?
                This action cannot be undone and all associated data will be lost.
            </template>
            <template #footer>
                <div class="flex justify-end gap-x-3">
                    <button
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="confirmingDelete = null"
                    >
                        Cancel
                    </button>
                    <button
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200"
                        @click="deleteTenant"
                    >
                        Delete Permanently
                    </button>
                </div>
            </template>
        </ConfirmationModal>
    </AdminLayout>
</template>
