<script setup>
import { Link, router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({
    tenant: Object,
    devices: Array,
    open_reconciliation_count: Number,
});

const healthClasses = {
    healthy: "text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400",
    stale: "text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400",
    offline: "text-gray-600 bg-gray-100 border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400",
    skewed: "text-orange-700 bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-400",
    revoked: "text-red-700 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400",
};

const toggleOffline = () => {
    if (confirm(__("Toggle offline mode for this tenant?"))) {
        router.put(route("admin.device-fleet.offline", props.tenant.id), {}, { preserveScroll: true });
    }
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "—";
</script>

<template>
    <AdminLayout :title="tenant.name">
        <div class="mb-6">
            <Link :href="route('admin.device-fleet.index')" class="inline-flex items-center gap-x-1 text-sm text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400">
                <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __("Back to fleet") }}
            </Link>
        </div>

        <div class="w-full lg:flex lg:items-center lg:justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ tenant.name }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __(":count open reconciliation items", { count: open_reconciliation_count }) }}</p>
            </div>
            <div class="mt-4 flex items-center gap-x-3 lg:mt-0">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ __("Offline mode") }}:</span>
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal rounded-lg border transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2"
                    :class="tenant.offline_enabled
                        ? 'text-white bg-emerald-600 border-transparent hover:bg-emerald-700 focus:ring-emerald-500'
                        : 'text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500'"
                    @click="toggleOffline"
                >
                    {{ __(tenant.offline_enabled ? "Enabled" : "Disabled") }}
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Device") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Register") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Health") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Last seen") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Pending") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                        <tr v-for="(device, i) in devices" :key="i" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ device.name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ device.register ?? "—" }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="healthClasses[device.health]">{{ device.health_label }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(device.last_seen_at) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ device.pending_count ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="devices.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">{{ __("No devices enrolled.") }}</div>
        </div>
    </AdminLayout>
</template>
