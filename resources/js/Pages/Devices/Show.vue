<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";

defineProps({
    device: Object,
    logs: Array,
});

const healthClasses = {
    healthy: "text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400",
    stale: "text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400",
    offline: "text-gray-600 bg-gray-100 border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400",
    skewed: "text-orange-700 bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-400",
    revoked: "text-red-700 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400",
};

const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "—";
</script>

<template>
    <AppLayout :title="device.name">
        <div class="mb-6">
            <Link :href="route('devices.index')" class="inline-flex items-center gap-x-1 text-sm text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400">
                <svg class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __("Back to devices") }}
            </Link>
        </div>

        <div class="flex items-center gap-x-3 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ device.name }}</h2>
            <span class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="healthClasses[device.health]">{{ device.health_label }}</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __("Details") }}</h3>
                </div>
                <dl class="p-6 space-y-4">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Register") }}</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ device.register ?? "—" }} · {{ device.register_label ?? "—" }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Status") }}</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ device.status }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Last push / pull") }}</dt>
                        <dd class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(device.last_push_at) }} · {{ formatDate(device.last_pull_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Pending outbox") }}</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ device.pending_count ?? 0 }} · {{ formatDate(device.oldest_pending_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Clock skew") }}</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ device.clock_skew_seconds ?? 0 }}s</dd>
                    </div>
                </dl>
            </div>

            <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __("Sync activity") }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-6 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Endpoint") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Applied") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Rejected") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("When") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                            <tr v-for="(log, i) in logs" :key="i">
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ log.endpoint }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ log.applied_count }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700 dark:text-gray-300">{{ log.rejected_count }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(log.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="logs.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">{{ __("No sync activity yet.") }}</div>
            </div>
        </div>
    </AppLayout>
</template>
