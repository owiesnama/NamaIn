<script setup>
import { ref, watch } from "vue";
import { router, Link } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import Pagination from "@/Shared/Pagination.vue";

const props = defineProps({
    tenants: Object,
    filters: Object,
});

const search = ref(props.filters?.search || "");

watch(search, (value) => {
    router.get(route("admin.device-fleet.index"), { search: value }, { preserveState: true, replace: true });
});

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "—";
</script>

<template>
    <AdminLayout :title="__('Device fleet')">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __("Device fleet") }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Offline device health across all tenants.") }}</p>
        </div>

        <div class="mb-4">
            <input v-model="search" type="text" :placeholder="__('Search tenants...')" class="w-full max-w-sm px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Tenant") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Offline") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Devices") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Active / Revoked") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Open items") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Last sync") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                        <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Link :href="route('admin.device-fleet.show', tenant.id)" class="text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700">{{ tenant.name }}</Link>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-0.5 text-[11px] font-semibold rounded-full" :class="tenant.offline_enabled ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400' : 'text-gray-600 bg-gray-100 dark:bg-gray-800 dark:text-gray-400'">
                                    {{ __(tenant.offline_enabled ? "On" : "Off") }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ tenant.device_count }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ tenant.active_count }} / {{ tenant.revoked_count }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ tenant.open_reconciliation_count }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(tenant.last_seen_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="tenants.data.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">{{ __("No tenants found.") }}</div>
        </div>

        <Pagination :links="tenants.links" />
    </AdminLayout>
</template>
