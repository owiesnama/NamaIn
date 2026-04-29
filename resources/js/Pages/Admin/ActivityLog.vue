<script setup>
    import { ref, watch } from "vue";
    import { router } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import TextInput from "@/Components/TextInput.vue";

    const props = defineProps({
        logs: Object,
        actions: Array,
        filters: Object,
    });

    const search = ref(props.filters?.search || "");
    const actionFilter = ref(props.filters?.action || "");
    let debounceTimer = null;

    watch([search, actionFilter], () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            router.get(route("admin.activity"), {
                search: search.value || undefined,
                action: actionFilter.value || undefined,
            }, { preserveState: true, replace: true });
        }, 300);
    });

    const actionLabel = (action) => {
        const labels = {
            'tenant.created': 'Created tenant',
            'tenant.updated': 'Updated tenant',
            'tenant.deleted': 'Deleted tenant',
            'impersonation.started': 'Started impersonation',
            'impersonation.stopped': 'Stopped impersonation',
        };
        return labels[action] || action;
    };

    const actionStyle = (action) => {
        if (action.startsWith('impersonation')) {
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800';
        }
        if (action.includes('deleted')) {
            return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800';
        }
        return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
    };

    const formatDate = (date) => {
        return new Date(date).toLocaleString();
    };

    const metadataSummary = (log) => {
        if (!log.metadata) return null;
        const meta = log.metadata;

        if (meta.owner_email) return `Owner: ${meta.owner_email}`;
        if (meta.target_user_email) return `User: ${meta.target_user_email}`;
        if (meta.old_slug) return `Slug: ${meta.old_slug} → changed`;
        if (meta.name) return meta.name;
        if (meta.tenant_id) return `Tenant #${meta.tenant_id}`;
        return null;
    };
</script>

<template>
    <AdminLayout title="Activity Log">
        <!-- Page header -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Activity Log</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All admin actions and impersonation sessions</p>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-4 mb-6">
            <div class="w-full sm:w-64">
                <TextInput
                    v-model="search"
                    type="text"
                    placeholder="Search by admin name or email..."
                    class="w-full"
                />
            </div>
            <select
                v-model="actionFilter"
                class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-gray-400 focus:ring focus:ring-gray-200 focus:ring-opacity-50"
            >
                <option value="">All actions</option>
                <option v-for="action in actions" :key="action" :value="action">
                    {{ actionLabel(action) }}
                </option>
            </select>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Admin</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Action</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Details</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">IP</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-x-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-xs font-bold text-gray-600 dark:text-gray-300">
                                        {{ log.admin?.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ log.admin?.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ log.admin?.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-lg border"
                                    :class="actionStyle(log.action)"
                                >
                                    {{ actionLabel(log.action) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ metadataSummary(log) || '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-400 dark:text-gray-500 font-mono">
                                {{ log.ip_address || '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(log.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty state -->
            <div v-if="logs.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                No activity recorded yet.
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="logs.links && logs.links.length > 3" class="flex flex-wrap items-center gap-1 mt-8">
            <template v-for="link in logs.links" :key="link.label">
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
    </AdminLayout>
</template>
