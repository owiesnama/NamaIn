<script setup>
import { ref, watch, computed } from "vue";
import debounce from "lodash/debounce";
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import StorageForm from "@/Components/Storages/StorageForm.vue";
import DeleteStorage from "@/Components/Storages/DeleteStorage.vue";
import AdjustmentModal from "@/Components/Storages/AdjustmentModal.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Tooltip from "@/Components/Tooltip.vue";
import { useDate } from '@/Composables/useDate';
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

const formatCurrency = (amount, currency = 'SDG') => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');
    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount);
};

const { formatDate } = useDate();

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

const canTransfer = computed(() => {
    const user = usePage().props.auth.user;
    return ['owner', 'manager'].includes(user.role_in_current_tenant);
});
</script>

<template>
    <AppLayout :title="storage.name">
        <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-6 px-4 sm:px-0">
                <div class="flex items-center gap-x-4">
                    <Link :href="route('storages.index')" class="p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                            {{ storage.name }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __("Storage Dashboard") }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-x-3">
                    <Link v-if="canTransfer" :href="route('stock-transfers.create', { from_storage_id: storage.id })" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ltr:mr-2 rtl:ml-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        {{ __("Transfer Stock") }}
                    </Link>
                    <StorageForm :storage="storage" />
                    <DeleteStorage :storage="storage" />
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-8 p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 sm:px-6">
                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-400 uppercase tracking-widest mb-1.5 px-1">{{ __("From Date") }}</label>
                        <DatePicker v-model="filters.from_date" class="!py-1.5 !px-3 text-sm rounded-lg" />
                    </div>
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-400 uppercase tracking-widest mb-1.5 px-1">{{ __("To Date") }}</label>
                        <DatePicker v-model="filters.to_date" class="!py-1.5 !px-3 text-sm rounded-lg" />
                    </div>
                    <div class="flex flex-col min-w-[180px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5 px-1">{{ __("Filter by Product") }}</label>
                        <CustomSelect
                            v-model="filters.product_id"
                            :options="all_products"
                            :multiple="false"
                            :close-on-select="true"
                            :placeholder="__('All Products')"
                            label="name"
                            track-by="id"
                            class="w-full"
                        />
                    </div>
                    <div class="flex flex-col min-w-[140px]">
                        <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5 px-1">{{ __("Movement Type") }}</label>
                        <CustomSelect
                            v-model="filters.type"
                            :options="[{ id: 'All', label: __('All Movement') }, { id: 'Sales', label: __('Only Sales') }, { id: 'Purchases', label: __('Only Purchases') }]"
                            :multiple="false"
                            :close-on-select="true"
                            :placeholder="__('All Movement')"
                            label="label"
                            track-by="id"
                            class="w-full"
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 px-4 sm:px-0">
                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Unique Products") }}</span>
                        <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-emerald-600 dark:text-emerald-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.unique_products_count }}</h3>
                        <Tooltip :text="__('Number of distinct products currently held in this storage.')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Items") }}</span>
                        </Tooltip>
                    </div>
                </div>

                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Total Stock Value") }}</span>
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-600 dark:text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(stats.total_stock_value) }}</h3>
                        <Tooltip :text="__('Total monetary value of all stock in this storage (Quantity * Cost).')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Value") }}</span>
                        </Tooltip>
                    </div>
                </div>

                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Sales Volume") }}</span>
                        <div class="p-2 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-amber-600 dark:text-amber-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a.5.5 0 00.71 0L20.25 4.5M2.25 18.75h19.5" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.sales_count }}</h3>
                        <Tooltip :text="__('Total quantity sold from this storage, respecting active filters (Base Unit).')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Units") }}</span>
                        </Tooltip>
                    </div>
                </div>

                <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Purchase Volume") }}</span>
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-indigo-600 dark:text-indigo-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.purchases_count }}</h3>
                        <Tooltip :text="__('Total quantity purchased into this storage, respecting active filters (Base Unit).')" position="top">
                            <span class="text-xs text-gray-400 cursor-help border-b border-dotted border-gray-400">{{ __("Units") }}</span>
                        </Tooltip>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-8 px-4 sm:px-0">
                <!-- Chart -->
                <div class="p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl transition-all">
                    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-4">
                        {{ __("Monthly Movement Trend") }}
                    </h3>
                    <div class="h-[300px]">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <!-- Current Stock Table -->
            <div class="mb-10 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
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
                                <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Actions") }}</th>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <AdjustmentModal :storage="storage" :product="product" :current-quantity="product.pivot.quantity" />
                                </td>
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
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">
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
