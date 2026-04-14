<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import debounce from "lodash/debounce";
import TextInput from "@/Components/TextInput.vue";
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
    chart_data: Object
});

const filters = ref({
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    storage_id: props.filters.storage_id || '',
    type: props.filters.type || 'All'
});

const formatCurrency = (amount, currency = 'USD') => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');
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
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-x-3">
                    <Link :href="route('products.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ __("Product Dashboard") }}: {{ product.name }}
                    </h2>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("From") }}</label>
                        <TextInput v-model="filters.from_date" type="date" class="!py-1.5 !px-3 text-sm focus:ring-2 focus:ring-emerald-500/20" />
                    </div>
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("To") }}</label>
                        <TextInput v-model="filters.to_date" type="date" class="!py-1.5 !px-3 text-sm focus:ring-2 focus:ring-emerald-500/20" />
                    </div>
                    <div class="flex flex-col min-w-[160px]">
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("Storage") }}</label>
                        <select v-model="filters.storage_id" class="text-sm py-1.5 pl-3 pr-10 border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg shadow-sm transition-all">
                            <option value="">{{ __("All Storages") }}</option>
                            <option v-for="storage in storages" :key="storage.id" :value="storage.id">{{ storage.name }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col min-w-[120px]">
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("Type") }}</label>
                        <select v-model="filters.type" class="text-sm py-1.5 pl-3 pr-10 border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg shadow-sm transition-all">
                            <option value="All">{{ __("All") }}</option>
                            <option value="Sales">{{ __("Sales") }}</option>
                            <option value="Purchases">{{ __("Purchases") }}</option>
                        </select>
                    </div>
                    <div class="flex items-end h-full self-end pb-0.5">
                        <Tooltip :text="__('Reset Filters')">
                            <button @click="resetFilters" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </button>
                        </Tooltip>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Chart -->
                <div class="lg:col-span-2 p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                        {{ __("Sales vs Purchases Trends") }}
                    </h3>
                    <div class="h-[250px]">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Stats Stack -->
                <div class="flex flex-col gap-6">
                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    {{ __("Sales Volume") }}
                                    <Tooltip :text="__('Total quantity sold based on base unit, respecting active filters.')" position="top">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                        </svg>
                                    </Tooltip>
                                </p>
                                <h3 class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.sales_count }}</h3>
                            </div>
                            <div class="p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-600 dark:text-emerald-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a.5.5 0 00.71 0L20.25 4.5M2.25 18.75h19.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    {{ __("Purchase Volume") }}
                                    <Tooltip :text="__('Total quantity purchased based on base unit, respecting active filters.')" position="top">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                        </svg>
                                    </Tooltip>
                                </p>
                                <h3 class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.purchases_count }}</h3>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-600 dark:text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                    {{ __("Current Stock") }}
                                    <Tooltip :text="__('Current available quantity in all storages.')" position="top">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                        </svg>
                                    </Tooltip>
                                </p>
                                <h3 class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.current_stock }}</h3>
                            </div>
                            <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-amber-600 dark:text-amber-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Movement Table -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                        {{ __("Inventory Movement") }}
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
