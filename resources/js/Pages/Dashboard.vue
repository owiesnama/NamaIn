<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { Link } from "@inertiajs/vue3";
    import { computed, ref } from "vue";
    import { usePrivacyMode } from "@/Composables/usePrivacyMode";
    import {
        Chart as ChartJS,
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        Title,
        Legend,
        Tooltip as ChartTooltip,
    } from 'chart.js';
    import { Line } from 'vue-chartjs';
    import Tooltip from "@/Components/Tooltip.vue";

    ChartJS.register(
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        Title,
        ChartTooltip,
        Legend
    );

    const props = defineProps([
        "total_sales",
        "total_purchase",
        "total_inventory_value",
        "expenses_this_month",
        "outstanding_receivables",
        "outstanding_payables",
        "gross_profit",
        "payments_this_month",
        "top_products",
        "top_customers",
        "low_stock_products",
        "upcoming_cheques",
        "monthly_stats",
    ]);

    const activeTab = ref('products');

    const chartData = computed(() => ({
        labels: props.monthly_stats.labels,
        datasets: [
            {
                label: __('Sales'),
                backgroundColor: '#10b981',
                borderColor: '#10b981',
                data: props.monthly_stats.sales,
                tension: 0.3,
                fill: false,
            },
            {
                label: __('Purchases'),
                backgroundColor: '#6366f1',
                borderColor: '#6366f1',
                data: props.monthly_stats.purchases,
                tension: 0.3,
                fill: false,
            },
            {
                label: __('Expenses'),
                backgroundColor: '#ef4444',
                borderColor: '#ef4444',
                data: props.monthly_stats.expenses,
                tension: 0.3,
                fill: false,
            }
        ]
    }));

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: (value) => formatCurrency(value) }
            }
        }
    };

    const formatCurrency = (amount, currency = null) => {
        const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
            (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');

        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: validCurrency,
        }).format(amount || 0);
    };

    const { isPrivate, togglePrivacy } = usePrivacyMode();

    const sensitiveClass = computed(() =>
        isPrivate.value ? "blur-sm select-none pointer-events-none transition-all duration-300" : "transition-all duration-300"
    );

    const isOverdue = (cheque) => new Date(cheque.due) < new Date();

    const daysUntilDue = (cheque) => Math.ceil((new Date(cheque.due) - new Date()) / (1000 * 60 * 60 * 24));

    const alertsEnabled = preferences('alerts', true);
    const attentionCount = computed(() =>
        (alertsEnabled ? (props.low_stock_products?.length || 0) : 0) + (props.upcoming_cheques?.length || 0)
    );
</script>

<template>
    <AppLayout :title="__('Dashboard')">
        <div class="space-y-6">

            <!-- Page Header -->
            <div class="w-full flex items-center justify-between">
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Dashboard') }}</h2>
                </div>
                <button
                    type="button"
                    @click="togglePrivacy"
                    class="inline-flex items-center gap-x-2 px-3 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    <!-- Eye-slash icon (shown when data is visible → click to hide) -->
                    <svg v-if="!isPrivate" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                    <!-- Eye icon (shown when data is hidden → click to show) -->
                    <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </div>

            <!-- Zone 1: 4 Key Numbers -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

                <!-- Gross Profit -->
                <div class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 transition-all">
                    <div class="flex items-center">
                        <div class="p-2 rounded-lg ltr:mr-4 rtl:ml-4 flex-shrink-0 transition-colors"
                             :class="gross_profit >= 0 ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-600'">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <Tooltip :text="__('Net result: Sales minus purchases and expenses over the last 30 days.')" position="top">
                                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate border-b border-dashed border-gray-300 dark:border-gray-600 cursor-help inline-block">{{ __("Gross Profit") }}</p>
                            </Tooltip>
                            <p class="text-2xl font-bold mt-1 tracking-tight" :class="[gross_profit >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600', sensitiveClass]">
                                {{ formatCurrency(gross_profit) }}
                            </p>
                            <p class="text-[10px] text-gray-400 mt-1 font-medium" :class="sensitiveClass">
                                {{ __("Exp") }}: {{ formatCurrency(expenses_this_month) }} &middot; {{ __("Pmts") }}: {{ formatCurrency(payments_this_month) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Sales -->
                <Link :href="route('sales.index')" class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 flex items-center hover:bg-gray-50 dark:hover:bg-gray-800 transition-all group">
                    <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-600 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <Tooltip :text="__('Total revenue from sales and inventory value.')" position="top">
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate border-b border-dashed border-gray-300 dark:border-gray-600 cursor-help inline-block">{{ __("Total Revenue") }}</p>
                        </Tooltip>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tracking-tight" :class="sensitiveClass">{{ formatCurrency(parseFloat(total_sales) + parseFloat(total_inventory_value)) }}</p>
                        <p class="text-[10px] text-gray-400 mt-1 font-medium" :class="sensitiveClass">
                            {{ __("Sales") }}: {{ formatCurrency(total_sales) }} &middot; {{ __("Inventory") }}: {{ formatCurrency(total_inventory_value) }}
                        </p>
                    </div>
                </Link>

                <!-- Outstanding Receivables -->
                <Link :href="route('customers.index')" class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 flex items-center hover:bg-gray-50 dark:hover:bg-gray-800 transition-all group">
                    <div class="p-2 rounded-lg bg-amber-500/10 text-amber-600 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <Tooltip :text="__('Total money owed by customers.')" position="top">
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate border-b border-dashed border-gray-300 dark:border-gray-600 cursor-help inline-block">{{ __("Receivables") }}</p>
                        </Tooltip>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tracking-tight" :class="sensitiveClass">{{ formatCurrency(outstanding_receivables) }}</p>
                        <p class="text-[10px] text-gray-400 mt-1 font-medium" :class="sensitiveClass">{{ __("Open invoices") }}</p>
                    </div>
                </Link>

                <!-- Outstanding Payables -->
                <Link :href="route('suppliers.index')" class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 flex items-center hover:bg-gray-50 dark:hover:bg-gray-800 transition-all group">
                    <div class="p-2 rounded-lg bg-red-500/10 text-red-600 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <Tooltip :text="__('Total money owed to suppliers.')" position="top">
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate border-b border-dashed border-gray-300 dark:border-gray-600 cursor-help inline-block">{{ __("Payables") }}</p>
                        </Tooltip>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tracking-tight" :class="sensitiveClass">{{ formatCurrency(outstanding_payables) }}</p>
                        <p class="text-[10px] text-gray-400 mt-1 font-medium" :class="sensitiveClass">{{ __("Open invoices") }}</p>
                    </div>
                </Link>
            </div>

            <!-- Zone 2: Chart + Needs Attention -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Chart -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-6 transition-all">
                    <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-4">{{ __("Sales vs Purchases vs Expenses") }}</h3>
                    <div class="relative h-[300px]">
                        <Line :data="chartData" :options="chartOptions" />
                        <Transition
                            enter-active-class="ease-out duration-300"
                            enter-from-class="opacity-0"
                            enter-to-class="opacity-100"
                            leave-active-class="ease-in duration-200"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="isPrivate" class="absolute inset-0 flex flex-col items-center justify-center rounded-lg backdrop-blur-md bg-white/60 dark:bg-gray-900/60">
                                <svg class="h-6 w-6 text-gray-400 dark:text-gray-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('Chart data hidden') }}</p>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Needs Attention -->
                <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden transition-all">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
                        <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ __("Needs Attention") }}</h3>
                        <span v-if="attentionCount > 0"
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                            {{ attentionCount }}
                        </span>
                    </div>

                    <div class="flex-grow overflow-y-auto">

                        <!-- Low Stock -->
                        <template v-if="preferences('alerts', true) && low_stock_products?.length > 0">
                            <p class="px-5 pt-4 pb-1 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                {{ __("Low Stock") }}
                            </p>
                            <ul role="list" class="divide-y divide-gray-50 dark:divide-gray-800">
                                <li v-for="product in low_stock_products" :key="product.id"
                                    class="px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <div class="flex items-center justify-between gap-3">
                                        <Link :href="route('products.index', { search: product.name })"
                                              class="text-sm font-medium text-gray-900 dark:text-gray-200 hover:text-emerald-600 dark:hover:text-emerald-400 truncate">
                                            {{ product.name }}
                                        </Link>
                                        <span class="text-xs font-semibold text-red-500 flex-shrink-0">
                                            {{ product.stock.reduce((a, s) => a + s.pivot.quantity, 0) }} {{ __("left") }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </template>

                        <!-- Cheques -->
                        <template v-if="upcoming_cheques?.length > 0">
                            <p class="px-5 pt-4 pb-1 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                {{ __("Cheques") }}
                            </p>
                            <ul role="list" class="divide-y divide-gray-50 dark:divide-gray-800">
                                <li v-for="cheque in upcoming_cheques" :key="cheque.id"
                                    class="px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-200 truncate">
                                                {{ cheque.payee?.name }}
                                            </p>
                                            <p class="text-xs font-semibold mt-0.5"
                                               :class="[cheque.type === 1 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400', sensitiveClass]">
                                                {{ formatCurrency(cheque.amount) }}
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold flex-shrink-0"
                                              :class="isOverdue(cheque) ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'">
                                            {{ isOverdue(cheque)
                                                ? __('Overdue')
                                                : __('Due in :days d', { days: daysUntilDue(cheque) }) }}
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </template>

                        <!-- All clear -->
                        <div v-if="attentionCount === 0"
                             class="flex flex-col items-center justify-center h-full py-12 px-4 text-center">
                            <svg class="w-9 h-9 text-emerald-400 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm font-medium text-gray-500">{{ __("All clear") }}</p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Zone 3: Tabbed Top Products / Top Customers -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden transition-all">
                <div class="border-b border-gray-100 dark:border-gray-800 flex items-center justify-between px-6">
                    <nav class="flex gap-1 py-3" aria-label="Tabs">
                        <button
                            type="button"
                            @click="activeTab = 'products'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                                activeTab === 'products'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                        >
                            {{ __("Top Products") }}
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'customers'"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                                activeTab === 'customers'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                        >
                            {{ __("Top Customers") }}
                        </button>
                    </nav>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ __("Last 30 days") }}</span>
                </div>

                <!-- Top Products tab -->
                <ul v-show="activeTab === 'products'" role="list" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template v-if="top_products?.length > 0">
                        <li v-for="(item, index) in top_products" :key="item.product_id"
                            class="px-6 py-4 flex items-center group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <span class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800 text-xs font-bold text-gray-400 dark:text-gray-500 ltr:mr-4 rtl:ml-4 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/30 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <Link :href="route('products.index', { search: item.product?.name })"
                                      class="text-sm font-medium text-gray-900 dark:text-gray-200 hover:text-emerald-600 dark:hover:text-emerald-400 truncate block">
                                    {{ item.product?.name }}
                                </Link>
                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ item.total_quantity }} {{ __("sold") }}</p>
                            </div>
                            <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 ltr:ml-4 rtl:mr-4" :class="sensitiveClass">
                                {{ formatCurrency(item.total_revenue, item.product?.currency) }}
                            </p>
                        </li>
                    </template>
                    <li v-else class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ __("No sales data available yet") }}
                    </li>
                </ul>

                <!-- Top Customers tab -->
                <ul v-show="activeTab === 'customers'" role="list" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template v-if="top_customers?.length > 0">
                        <li v-for="(item, index) in top_customers" :key="index"
                            class="px-6 py-4 flex items-center group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <span class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800 text-xs font-bold text-gray-400 dark:text-gray-500 ltr:mr-4 rtl:ml-4 group-hover:bg-emerald-50 dark:group-hover:bg-emerald-900/30 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                {{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <Link :href="route('customers.index', { search: item.name })"
                                      class="text-sm font-medium text-gray-900 dark:text-gray-200 hover:text-emerald-600 dark:hover:text-emerald-400 truncate block">
                                    {{ item.name }}
                                </Link>
                            </div>
                            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 ltr:ml-4 rtl:mr-4" :class="sensitiveClass">
                                {{ formatCurrency(item.revenue) }}
                            </p>
                        </li>
                    </template>
                    <li v-else class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ __("No customer data available yet") }}
                    </li>
                </ul>
            </div>

        </div>
    </AppLayout>
</template>
