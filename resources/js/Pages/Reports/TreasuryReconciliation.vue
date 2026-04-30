<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import ReportDateFilter from "@/Shared/ReportDateFilter.vue";

const props = defineProps(['data', 'summary', 'filters', 'accounts', 'presets']);

const localFilters = ref({
    preset: props.filters?.preset || 'this_month',
    from_date: props.filters?.from_date || '',
    to_date: props.filters?.to_date || '',
    treasury_account: props.filters?.treasury_account || '',
});

let debounceTimer;
watch(localFilters, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const params = Object.fromEntries(Object.entries(val).filter(([, v]) => v !== '' && v != null));
        router.get(route('reports.treasury'), params, { preserveState: true, preserveScroll: true });
    }, 400);
}, { deep: true });

const formatCurrency = (amount) => {
    const currency = (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency'))) ? preferences('currency') : 'SDG';
    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', { style: 'currency', currency }).format(amount || 0);
};

function requestExport() {
    router.post(route('exports.store'), {
        export_key: 'report-treasury',
        format: 'xlsx',
        filters: { ...localFilters.value },
    }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="__('Treasury Reconciliation')">
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
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Treasury Reconciliation') }}</h2>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Total Opening') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(summary?.total_opening) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Credits') }}</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ formatCurrency(summary?.credits) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Debits') }}</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ formatCurrency(summary?.debits) }}</p>
                </div>
                <div class="px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __('Total Closing') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(summary?.total_closing) }}</p>
                </div>
            </div>

            <!-- Filters + Table -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Filter Sidebar -->
                <aside class="w-full lg:w-72 shrink-0">
                    <div class="sticky top-4 space-y-4">
                        <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl space-y-6">
                            <ReportDateFilter v-model="localFilters" :presets="presets" />

                            <!-- Treasury Account -->
                            <div v-if="accounts">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __('Treasury Account') }}</p>
                                <select v-model="localFilters.treasury_account"
                                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50">
                                    <option value="">{{ __('All') }}</option>
                                    <option v-for="(name, id) in accounts" :key="id" :value="id">{{ name }}</option>
                                </select>
                            </div>
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
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Account') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Date') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Reason') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Amount') }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Balance After') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                                    <tr v-for="(row, index) in data" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ row.account }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ row.date }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ row.reason }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold"
                                            :class="(row.amount ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                            {{ formatCurrency(row.amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(row.balance_after) }}</td>
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
