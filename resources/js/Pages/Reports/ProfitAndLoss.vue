<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import ReportDateFilter from "@/Shared/ReportDateFilter.vue";

const props = defineProps(['data', 'summary', 'filters', 'presets']);

const localFilters = ref({
    preset: props.filters?.preset || 'this_month',
    from_date: props.filters?.from_date || '',
    to_date: props.filters?.to_date || '',
});

let debounceTimer;
watch(localFilters, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const params = Object.fromEntries(Object.entries(val).filter(([, v]) => v !== '' && v != null));
        router.get(route('reports.pnl'), params, { preserveState: true, preserveScroll: true });
    }, 400);
}, { deep: true });

const formatCurrency = (amount) => {
    const currency = (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency'))) ? preferences('currency') : 'SDG';
    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', { style: 'currency', currency }).format(amount || 0);
};

const formatPercent = (value) => {
    return (value ?? 0).toFixed(1) + '%';
};

function requestExport() {
    router.post(route('exports.store'), {
        export_key: 'report-pnl',
        format: 'xlsx',
        filters: { ...localFilters.value },
    }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="__('Profit & Loss')">
        <div class="space-y-6">
            <!-- Header -->
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <Link :href="route('reports.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                            <svg class="h-5 w-5 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </Link>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Profit & Loss') }}</h2>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-x-4 lg:mt-0">
                    <button
                        @click="requestExport"
                        :disabled="!data?.length"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                    >
                        <svg class="h-4 w-4 ltr:mr-2 rtl:ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        {{ __('Export') }}
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Revenue') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(summary?.revenue) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('COGS') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(summary?.cogs) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Gross Profit') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(summary?.gross_profit) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Margin') }}: {{ formatPercent(summary?.gross_margin) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Expenses') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(summary?.expenses) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Net Profit') }}</p>
                    <p class="text-2xl font-bold mt-1" :class="(summary?.net_profit ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ formatCurrency(summary?.net_profit) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Margin') }}: {{ formatPercent(summary?.net_margin) }}</p>
                </div>
            </div>

            <!-- Filters + Table -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Filter Sidebar -->
                <aside class="w-full lg:w-72 shrink-0">
                    <div class="sticky top-4 space-y-4">
                        <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl space-y-6">
                            <ReportDateFilter v-model="localFilters" :presets="presets" />
                        </div>
                    </div>
                </aside>

                <!-- Table -->
                <div class="flex-1 min-w-0">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                    <tr>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Period') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Revenue') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('COGS') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Gross Profit') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Expenses') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Net Profit') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                                    <tr v-for="row in data" :key="row.period" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ row.period }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(row.revenue) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(row.cogs) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(row.gross_profit) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(row.expenses) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold"
                                            :class="(row.net_profit ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                            {{ formatCurrency(row.net_profit) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="!data?.length" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __('No data for the selected period.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
