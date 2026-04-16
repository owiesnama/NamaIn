<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import debounce from "lodash/debounce";
import Tooltip from "@/Components/Tooltip.vue";
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Legend,
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Legend
);

const props = defineProps({
    product: Object,
    transactions: Object,
    stats: Object,
    storages: Array,
    filters: Object,
    chart_data: Object,
    insights: Array
});

const filters = ref({
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    storage_id: props.filters.storage_id || '',
    type: props.filters.type || 'All'
});

const formatCurrency = (amount, currency = 'SDG') => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');
    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount);
};

const formatDate = (dateString) => {
    if (!dateString) return "-";
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return "-";
        return date.toLocaleDateString(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return "-";
    }
};

const chartData = computed(() => ({
    labels: props.chart_data.labels,
    datasets: [
        {
            label: __('Sales'),
            backgroundColor: '#10b981',
            borderColor: '#10b981',
            data: props.chart_data.sales,
            tension: 0.3
        },
        {
            label: __('Purchases'),
            backgroundColor: '#6366f1',
            borderColor: '#6366f1',
            data: props.chart_data.purchases,
            tension: 0.3
        }
    ]
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
    },
    scales: {
        y: {
            beginAtZero: true,
        }
    }
};

watch(filters, debounce(() => {
    router.get(route('products.show', props.product.id), filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300), { deep: true });

const resetFilters = () => {
    filters.value = {
        from_date: '',
        to_date: '',
        storage_id: '',
        type: 'All'
    };
};
</script>

<template>
    <AppLayout :title="product.name">
        <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 px-4 sm:px-0">
                <div class="flex items-center gap-x-4">
                    <Link :href="route('products.index')" class="p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                            {{ product.name }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __("Product Intelligence Dashboard") }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('sales.index', { product_id: product.id })"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-all"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                        {{ __("View All Sales") }}
                    </Link>
                    <Link
                        :href="route('purchases.index', { product_id: product.id })"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-all"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                        {{ __("View All Purchases") }}
                    </Link>
                </div>
            </div>

            <!-- Product Insights (Critical Alerts) -->
            <div v-if="insights.length > 0" class="mb-6 px-4 sm:px-0">
                <div
                     :class="[
                         'flex items-center p-4 rounded-xl border transition-all',
                         insights.some(i => i.type === 'danger') ? 'bg-red-50 border-red-100 dark:bg-red-900/10 dark:border-red-900/30 text-red-800 dark:text-red-400' :
                         insights.some(i => i.type === 'warning') ? 'bg-amber-50 border-amber-100 dark:bg-amber-900/10 dark:border-amber-900/30 text-amber-800 dark:text-amber-400' :
                         'bg-blue-50 border-blue-100 dark:bg-blue-900/10 dark:border-blue-900/30 text-blue-800 dark:text-blue-400'
                     ]"
                >
                    <div class="flex-shrink-0 mr-5">
                        <div :class="[
                            'p-1.5 rounded-lg',
                            insights.some(i => i.type === 'danger') ? 'bg-red-100 dark:bg-red-900/20' :
                            insights.some(i => i.type === 'warning') ? 'bg-amber-100 dark:bg-amber-900/20' :
                            'bg-blue-100 dark:bg-blue-900/20'
                        ]">
                            <svg v-if="insights.some(i => i.type === 'danger')" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.401 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else-if="insights.some(i => i.type === 'warning')" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex-grow mx-1">
                        <p class="text-xs font-bold uppercase tracking-wider opacity-70 mb-1">
                            {{ insights.some(i => i.type === 'danger') ? __("Critical Attention Required") : insights.some(i => i.type === 'warning') ? __("Stock Warning") : __("Inventory Notice") }}
                        </p>
                        <div class="space-y-1">
                            <p v-for="(insight, index) in insights" :key="index" class="text-sm font-semibold">{{ insight.message }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 px-4 sm:px-0">
                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Sales Volume") }}</span>
                        <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-emerald-600 dark:text-emerald-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a.5.5 0 00.71 0L20.25 4.5M2.25 18.75h19.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.sales_count }}</h3>
                        <Tooltip :text="__('Total number of units sold for this product, respecting the current filters.')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ product.unit?.name || __("Units") }}</span>
                        </Tooltip>
                    </div>
                </div>

                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Purchase Volume") }}</span>
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-600 dark:text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.purchases_count }}</h3>
                        <Tooltip :text="__('Total number of units purchased for this product, respecting the current filters.')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ product.unit?.name || __("Units") }}</span>
                        </Tooltip>
                    </div>
                </div>

                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Current Stock") }}</span>
                        <div class="p-2 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-amber-600 dark:text-amber-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.current_stock }}</h3>
                        <Tooltip :text="__('Physical quantity currently on hand across selected storages.')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ product.unit?.name || __("Units") }}</span>
                        </Tooltip>
                    </div>
                </div>

                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Available Stock") }}</span>
                        <div class="p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-600 dark:text-purple-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.available_qty }}</h3>
                        <Tooltip :text="__('Stock available for new sales (Physical Stock - Pending Sales).')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ product.unit?.name || __("Units") }}</span>
                        </Tooltip>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="mb-8 p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 sm:px-6">
                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5 px-1">{{ __("From Date") }}</label>
                        <DatePicker v-model="filters.from_date" class="!py-1.5 !px-3 text-sm rounded-lg" />
                    </div>
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5 px-1">{{ __("To Date") }}</label>
                        <DatePicker v-model="filters.to_date" class="!py-1.5 !px-3 text-sm rounded-lg" />
                    </div>
                    <div class="flex flex-col min-w-[180px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5 px-1">{{ __("Storage Location") }}</label>
                        <VueMultiselect
                            :model-value="storages.find(s => s.id == filters.storage_id)"
                            :options="storages"
                            :multiple="false"
                            :close-on-select="true"
                            :placeholder="__('All Storages')"
                            label="name"
                            track-by="id"
                            class="w-full"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
                            @update:model-value="option => filters.storage_id = option?.id || ''"
                        />
                    </div>
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5 px-1">{{ __("Transaction Type") }}</label>
                        <VueMultiselect
                            :model-value="[{ id: 'All', label: __('All Transactions') }, { id: 'Sales', label: __('Only Sales') }, { id: 'Purchases', label: __('Only Purchases') }].find(o => o.id === filters.type)"
                            :options="[{ id: 'All', label: __('All Transactions') }, { id: 'Sales', label: __('Only Sales') }, { id: 'Purchases', label: __('Only Purchases') }]"
                            :multiple="false"
                            :close-on-select="true"
                            :placeholder="__('All Transactions')"
                            label="label"
                            track-by="id"
                            class="w-full"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
                            @update:model-value="option => filters.type = option?.id || 'All'"
                        />
                    </div>
                    <div class="flex items-end flex-grow justify-end h-full self-end pb-0.5">
                        <button @click="resetFilters" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {{ __("Reset Filters") }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8 px-4 sm:px-0">
                <!-- Trend Chart -->
                <div class="lg:col-span-2 p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">
                            {{ __("Inventory Flow Trends") }}
                        </h3>
                    </div>
                    <div class="h-[300px]">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Secondary Stats / Valuation -->
                <div class="flex flex-col gap-6">
                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                        <p class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-4">{{ __("Inventory Valuation") }}</p>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-800 pb-3">
                                <Tooltip :text="__('Weighted average cost calculated from all purchase transactions.')" position="top">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Avg. Cost") }}</span>
                                </Tooltip>
                                <span class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(product.average_cost) }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-800 pb-3">
                                <Tooltip :text="__('Total monetary value of current stock (Current Stock * Avg. Cost).')" position="top">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Total Value") }}</span>
                                </Tooltip>
                                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(stats.current_stock * product.average_cost) }}</span>
                            </div>
                            <div class="pt-2">
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 italic">
                                    {{ __("* Valuation based on current stock across all storages.") }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-widest mb-4">{{ __("Commitment Status") }}</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <Tooltip :text="__('Quantity committed in unexecuted sales invoices.')" position="top">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Pending Sales") }}</span>
                                </Tooltip>
                                <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ stats.pending_sales }}</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                                <div class="bg-red-500 h-1.5 rounded-full" :style="{ width: Math.min((stats.pending_sales / (stats.current_stock || 1)) * 100, 100) + '%' }"></div>
                            </div>
                            <div class="flex justify-between items-center pt-2">
                                <Tooltip :text="__('Quantity expected from unexecuted purchase invoices.')" position="top">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Expected Arrivals") }}</span>
                                </Tooltip>
                                <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ stats.pending_purchases }}</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                                <div class="bg-blue-500 h-1.5 rounded-full" :style="{ width: Math.min((stats.pending_purchases / (stats.current_stock || 1)) * 100, 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Movement Table -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden mx-4 sm:mx-0">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                        {{ __("Recent Invoices") }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Date") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Type") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Quantity") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Price") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Storage") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Invoice") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatDate(transaction.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-1 text-xs font-semibold rounded-full',
                                        transaction.type === 'Sales' ? 'text-emerald-700 bg-emerald-100/60 dark:bg-emerald-900/20 dark:text-emerald-400' : 'text-blue-700 bg-blue-100/60 dark:bg-blue-900/20 dark:text-blue-400'
                                    ]">
                                        {{ __(transaction.type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" :class="transaction.type === 'Sales' ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                                    {{ transaction.type === 'Sales' ? '-' : '+' }}{{ transaction.quantity }} {{ transaction.unit?.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatCurrency(transaction.price, transaction.currency) }}
                                    <span class="text-[10px] text-gray-400 block">{{ __("Unit Price") }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ transaction.storage?.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <Link :href="route('invoices.show', transaction.invoice_id)" class="text-emerald-600 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-300">
                                        #{{ transaction.invoice_id }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="transactions.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {{ __("No inventory movement recorded yet.") }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center">
                    <Pagination :links="transactions.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
