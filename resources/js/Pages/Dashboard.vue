<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { Link } from "@inertiajs/vue3";
    import { computed } from "vue";
    import {
        Chart as ChartJS,
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        Title,
        Tooltip,
        Legend,
        BarElement,
    } from 'chart.js';
    import { Line } from 'vue-chartjs';

    ChartJS.register(
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        BarElement,
        Title,
        Tooltip,
        Legend
    );

    const props = defineProps([
        "total_sales",
        "total_purchase",
        "outstanding_receivables",
        "payments_this_month",
        "transactions",
        "recent_payments",
        "top_products",
        "low_stock_products",
        "monthly_stats",
    ]);

    const chartData = computed(() => ({
        labels: props.monthly_stats.labels,
        datasets: [
            {
                label: __('Sales'),
                backgroundColor: '#10b981',
                borderColor: '#10b981',
                data: props.monthly_stats.sales,
                tension: 0.3
            },
            {
                label: __('Purchases'),
                backgroundColor: '#6366f1',
                borderColor: '#6366f1',
                data: props.monthly_stats.purchases,
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
                ticks: {
                    callback: (value) => formatCurrency(value)
                }
            }
        }
    };

    const formatCurrency = (amount, currency = null) => {
        const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
            (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');

        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: validCurrency,
        }).format(amount || 0);
    };

    const quantityForHumans = (transaction) => {
        if (!transaction.unit) {
            return `${transaction.quantity} <strong>(Base unit)</strong>`;
        }
        return `${transaction.quantity} <storng>(${transaction.unit?.name})</storng>`;
    };
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __("Dashboard") }}
            </h2>
        </template>

        <div class="space-y-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Sales -->
                <Link :href="route('sales.index')" class="relative px-4 py-5 overflow-hidden bg-white rounded-lg shadow sm:p-6 flex items-center hover:bg-gray-50 transition-colors group">
                    <div class="p-2 rounded-md bg-emerald-500 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">{{ __("Total Sales") }}</p>
                        <dd class="flex items-baseline mt-1">
                            <p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(total_sales) }}</p>
                        </dd>
                    </div>
                </Link>

                <!-- Total Purchase -->
                <Link :href="route('purchases.index')" class="relative px-4 py-5 overflow-hidden bg-white rounded-lg shadow sm:p-6 flex items-center hover:bg-gray-50 transition-colors group">
                    <div class="p-2 bg-indigo-500 rounded-md ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">{{ __("Total Purchase") }}</p>
                        <dd class="flex items-baseline mt-1">
                            <p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(total_purchase) }}</p>
                        </dd>
                    </div>
                </Link>

                <!-- Outstanding Receivables -->
                <Link :href="route('customers.index')" class="relative px-4 py-5 overflow-hidden bg-white rounded-lg shadow sm:p-6 flex items-center hover:bg-gray-50 transition-colors group">
                    <div class="p-2 bg-red-500 rounded-md ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">{{ __("Outstanding Receivables") }}</p>
                        <dd class="flex items-baseline mt-1">
                            <p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(outstanding_receivables) }}</p>
                        </dd>
                    </div>
                </Link>

                <!-- Payments This Month -->
                <Link :href="route('payments.index')" class="relative px-4 py-5 overflow-hidden bg-white rounded-lg shadow sm:p-6 flex items-center hover:bg-gray-50 transition-colors group">
                    <div class="p-2 bg-blue-500 rounded-md ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                        <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">{{ __("Payments This Month") }}</p>
                        <dd class="flex items-baseline mt-1">
                            <p class="text-2xl font-semibold text-gray-900">{{ formatCurrency(payments_this_month) }}</p>
                        </dd>
                    </div>
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Main Chart (2/3 width) -->
                <div class="lg:col-span-2">
                    <div class="p-6 bg-white rounded-lg shadow h-full">
                        <h3 class="mb-4 text-base font-semibold leading-6 text-gray-900">
                            {{ __("Sales vs Purchases") }}
                        </h3>
                        <div class="h-[350px]">
                            <Line :data="chartData" :options="chartOptions" />
                        </div>
                    </div>
                </div>

                <!-- Actionable Insights (1/3 width) -->
                <div class="space-y-8">
                    <!-- Low Stock Alerts -->
                    <div class="bg-white rounded-lg shadow flex flex-col h-full overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-900">{{ __("Low Stock Alerts") }}</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ltr:ml-auto rtl:mr-auto">
                                {{ low_stock_products?.length || 0 }}
                            </span>
                        </div>
                        <div class="flex-grow overflow-y-auto max-h-[350px]">
                            <ul v-if="low_stock_products?.length > 0" role="list" class="divide-y divide-gray-100">
                                <li v-for="product in low_stock_products" :key="product.id" class="px-6 py-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ product.name }}</p>
                                            <p class="text-xs text-red-500 font-semibold">
                                                {{ __("Only") }} {{ product.stock.reduce((acc, s) => acc + s.pivot.quantity, 0) }} {{ __("left") }}
                                            </p>
                                        </div>
                                        <Link :href="route('products.index', { search: product.name })" class="ml-4 text-xs font-semibold text-emerald-600 hover:text-emerald-500">
                                            {{ __("Restock") }}
                                        </Link>
                                    </div>
                                </li>
                            </ul>
                            <div v-else class="flex flex-col items-center justify-center py-12 px-4 text-center">
                                <svg class="w-12 h-12 text-gray-300 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-gray-500 font-medium">{{ __("All products are well-stocked") }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <!-- Top Selling Products -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">{{ __("Top Selling Products (Last 30 Days)") }}</h3>
                    </div>
                    <ul v-if="top_products?.length > 0" role="list" class="divide-y divide-gray-100">
                        <li v-for="(item, index) in top_products" :key="item.product_id" class="px-6 py-4 flex items-center group hover:bg-gray-50 transition-colors">
                            <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-gray-50 text-xs font-bold text-gray-500 ltr:mr-4 rtl:ml-4 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                #{{ index + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <Link :href="route('products.index', { search: item.product?.name })" class="text-sm font-medium text-gray-900 truncate hover:text-indigo-600">
                                    {{ item.product?.name }}
                                </Link>
                                <p class="text-xs text-gray-500">{{ item.total_quantity }} {{ __("Sold") }}</p>
                            </div>
                            <div class="ltr:text-right rtl:text-left">
                                <p class="text-sm font-semibold text-indigo-600">{{ formatCurrency(item.total_revenue, item.product?.currency) }}</p>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wider">{{ __("Revenue") }}</p>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="py-12 text-center text-sm text-gray-500">
                        {{ __("No sales data available yet") }}
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">{{ __("Recent Payments") }}</h3>
                        <Link :href="route('payments.index')" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">
                            {{ __("View All") }}
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table v-if="recent_payments?.length > 0" class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Party") }}</th>
                                    <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Amount") }}</th>
                                    <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Date") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="payment in recent_payments" :key="payment.id" class="hover:bg-gray-50 transition-colors group">
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <Link v-if="payment.invoice?.invocable"
                                                  :href="payment.invoice.invocable_type.includes('Customer') ? route('customers.index', { search: payment.invoice.invocable.name }) : route('suppliers.index', { search: payment.invoice.invocable.name })"
                                                  class="hover:text-emerald-600">
                                                {{ payment.invoice.invocable.name }}
                                            </Link>
                                            <span v-else>-</span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <Link v-if="payment.invoice" :href="route('invoices.show', payment.invoice.id)" class="hover:underline">
                                                #{{ payment.invoice.serial_number || payment.invoice.id }}
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-emerald-600">{{ formatCurrency(payment.amount, payment.currency) }}</span>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                                        {{ payment.paid_at_human || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="py-12 text-center text-sm text-gray-500">
                            {{ __("No recent payments found") }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">
                        {{ __("Recent Inventory Activity") }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="transactions?.length > 0" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                                <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Product") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Storage") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Quantity") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Type") }}</th>
                                <th class="px-6 py-3 text-start text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __("Date") }}</th>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <Link :href="route('products.index', { search: transaction.product.name })" class="hover:text-indigo-600">
                                        {{ transaction.product.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <Link :href="route('storages.index', { search: transaction.storage.name })" class="hover:text-indigo-600">
                                        {{ transaction.storage.name }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" v-html="quantityForHumans(transaction)"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="{
                                              'bg-emerald-100 text-emerald-800': transaction.type === 'Sales',
                                              'bg-indigo-100 text-indigo-800': transaction.type === 'Purchases',
                                              'bg-gray-100 text-gray-800': !['Sales', 'Purchases'].includes(transaction.type)
                                          }">
                                        {{ __(transaction.type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ transaction.created_at || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="py-12 text-center text-sm text-gray-500">
                        {{ __("No recent transactions found") }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
