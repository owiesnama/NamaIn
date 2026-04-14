<script setup>
import { ref, watch, computed } from "vue";
import debounce from "lodash/debounce";
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import StorageForm from "@/Components/Storages/StorageForm.vue";
import DeleteStorage from "@/Components/Storages/DeleteStorage.vue";
import { Link, router } from "@inertiajs/vue3";
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
    storage: Object,
    products: Object,
    transactions: Object,
    all_products: Array,
    filters: Object,
    stats: Object,
    chart_data: Object
});

const filters = ref({
    search: props.filters.search || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    product_id: props.filters.product_id || '',
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
    router.get(route('storages.show', props.storage.id), filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300), { deep: true });

const resetFilters = () => {
    filters.value = {
        search: '',
        from_date: '',
        to_date: '',
        product_id: '',
        type: 'All'
    };
};
</script>

<template>
    <AppLayout :title="storage.name">
        <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-x-3">
                    <Link :href="route('storages.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ __("Storage Dashboard") }}: {{ storage.name }}
                    </h2>
                </div>

                <div class="flex items-center gap-x-3">
                    <StorageForm :storage="storage" />
                    <DeleteStorage :storage="storage" />
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-8 flex flex-wrap items-center gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <div class="flex flex-col min-w-[140px]">
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("From") }}</label>
                    <TextInput v-model="filters.from_date" type="date" class="!py-1.5 !px-3 text-sm focus:ring-2 focus:ring-emerald-500/20" />
                </div>
                <div class="flex flex-col min-w-[140px]">
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("To") }}</label>
                    <TextInput v-model="filters.to_date" type="date" class="!py-1.5 !px-3 text-sm focus:ring-2 focus:ring-emerald-500/20" />
                </div>
                <div class="flex flex-col min-w-[200px]">
                    <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1.5 px-1">{{ __("Product") }}</label>
                    <select v-model="filters.product_id" class="text-sm py-1.5 pl-3 pr-10 border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-lg shadow-sm transition-all">
                        <option value="">{{ __("All Products") }}</option>
                        <option v-for="product in all_products" :key="product.id" :value="product.id">{{ product.name }}</option>
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
                <div class="flex items-end h-full self-end pb-0.5 ml-auto">
                    <Tooltip :text="__('Reset Filters')">
                        <button @click="resetFilters" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </button>
                    </Tooltip>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <!-- Chart -->
                <div class="lg:col-span-3 p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                        {{ __("Monthly Movement Trend") }}
                    </h3>
                    <div class="h-[300px]">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Stats Stack -->
                <div class="flex flex-col gap-6 lg:col-span-1">
                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            {{ __("Unique Products") }}
                            <Tooltip :text="__('Number of distinct products currently held in this storage.')" position="top">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </Tooltip>
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.unique_products_count }}</h3>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            {{ __("Total Stock Value") }}
                            <Tooltip :text="__('Total monetary value of all stock in this storage (Quantity * Cost).')" position="top">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </Tooltip>
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ formatCurrency(stats.total_stock_value) }}</h3>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            {{ __("Sales Volume") }}
                            <Tooltip :text="__('Total quantity sold from this storage, respecting active filters (Base Unit).')" position="top">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </Tooltip>
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.sales_count }}</h3>
                    </div>

                    <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            {{ __("Purchase Volume") }}
                            <Tooltip :text="__('Total quantity purchased into this storage, respecting active filters (Base Unit).')" position="top">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400 cursor-help hover:text-gray-500 transition-colors">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </Tooltip>
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.purchases_count }}</h3>
                    </div>
                </div>
            </div>

            <!-- Current Stock Table -->
            <div class="mb-10 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                        {{ __("Current Stock") }}
                    </h3>
                    <div class="relative flex items-center max-w-xs">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input v-model="filters.search" type="text" :placeholder="__('Search products...')" class="block w-full py-1.5 pl-10 pr-3 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Product") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Quantity On Hand") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Expire Date") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Added Time") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white">{{ product.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 text-sm font-medium rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-emerald-900/20 dark:text-emerald-400">
                                        {{ product.pivot.quantity }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span v-if="product.expired_at > 0" class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-800/20 dark:text-emerald-500">
                                        {{ product.expire_date }} ({{ __("Not Expired") }})
                                    </span>
                                    <span v-else class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800/20 dark:text-red-500">
                                        {{ product.expire_date }} ({{ __("Expired") }})
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">{{ formatDate(product.created_at) }}</td>
                            </tr>
                            <tr v-if="products.data.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {{ __("No products found in this storage.") }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center">
                    <Pagination :links="products.links" />
                </div>
            </div>

            <!-- Inventory Movement Table -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                        {{ __("Inventory Movement") }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Date") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Product") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Type") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Quantity") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Price") }}</th>
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Invoice") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatDate(transaction.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800 dark:text-white">
                                    {{ transaction.product?.name }}
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
