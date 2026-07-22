<script setup>
import { ref, watch, computed } from "vue";
import { router } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({
    tenants: Array,
    filters: Object,
    slos: Object,
});

const filters = ref({
    tenant: props.filters?.tenant ?? null,
    from_date: props.filters?.from_date ?? null,
    to_date: props.filters?.to_date ?? null,
});

watch(
    filters,
    (value) => {
        router.get(route("admin.pilot-health.index"), value, { preserveState: true, replace: true });
    },
    { deep: true },
);

const cards = computed(() => {
    if (!props.slos) return [];
    return [
        {
            label: __("Sale latency p95"),
            value: props.slos.sale_latency_p95_seconds === null ? "—" : `${props.slos.sale_latency_p95_seconds}s`,
            target: __("Target: < 60s"),
            ok: props.slos.sale_latency_p95_seconds === null || props.slos.sale_latency_p95_seconds < 60,
        },
        {
            label: __("Duplicated sales"),
            value: props.slos.duplicated_sales,
            target: __("Target: 0"),
            ok: props.slos.duplicated_sales === 0,
        },
        {
            label: __("Applied sales"),
            value: props.slos.applied_sales,
            target: __("Window total"),
            ok: true,
        },
        {
            label: __("Resolution p95"),
            value: props.slos.resolution_p95_hours === null ? "—" : `${props.slos.resolution_p95_hours}h`,
            target: __("Target: < 48h"),
            ok: props.slos.resolution_p95_hours === null || props.slos.resolution_p95_hours < 48,
        },
        {
            label: __("Open items"),
            value: props.slos.open_items,
            target: __("Lower is better"),
            ok: true,
        },
        {
            label: __("Crash-free sessions"),
            value: props.slos.crash_free_rate === null ? "—" : `${(props.slos.crash_free_rate * 100).toFixed(2)}%`,
            target: __("Client-reported"),
            ok: props.slos.crash_free_rate === null || props.slos.crash_free_rate >= 0.99,
        },
    ];
});
</script>

<template>
    <AdminLayout :title="__('Pilot health')">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __("Pilot health") }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Offline pilot SLOs computed from the sync audit trail.") }}</p>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("Tenant") }}</label>
                <select v-model.number="filters.tenant" class="mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                    <option :value="null">{{ __("Select tenant") }}</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("From") }}</label>
                <input v-model="filters.from_date" type="date" class="mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __("To") }}</label>
                <input v-model="filters.to_date" type="date" class="mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
            </div>
        </div>

        <!-- SLO cards -->
        <div v-if="slos" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="card in cards" :key="card.label" class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ card.label }}</p>
                <p class="mt-2 text-2xl font-bold" :class="card.ok ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'">{{ card.value }}</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ card.target }}</p>
            </div>
        </div>

        <div v-else class="py-12 text-center text-sm text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
            {{ __("Select a tenant to compute its pilot SLOs.") }}
        </div>
    </AdminLayout>
</template>
