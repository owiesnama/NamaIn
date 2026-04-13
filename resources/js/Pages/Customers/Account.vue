<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import Pagination from "@/Shared/Pagination.vue";
import { format, parseISO } from "date-fns";

defineProps({
    customer: Object,
    account_balance: Number,
    invoices: Object,
    payments: Object,
    transactions: Object,
});

const activeTab = ref('invoices');

const tabs = [
    { id: 'invoices', name: 'Invoices' },
    { id: 'payments', name: 'Payments' },
    { id: 'transactions', name: 'Transactions' },
];

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return "-";
    try {
        const date = parseISO(dateString);
        return format(date, "yyyy-MM-dd");
    } catch (e) {
        return "-";
    }
};
</script>

<template>
    <AppLayout :title="`${customer.name} Account`">
        <section>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
                        {{ customer.name }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ customer.address }}
                    </p>
                </div>

                <div class="flex gap-x-3">
                    <Link
                        :href="route('customers.statement', customer.id)"
                        class="px-5 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                    >
                        {{ __("View Statement") }}
                    </Link>
                    <Link
                        :href="route('payments.create')"
                        class="px-5 py-2 text-sm text-white bg-emerald-500 rounded-lg hover:bg-emerald-600"
                    >
                        {{ __("Record Payment") }}
                    </Link>
                </div>
            </div>

            <!-- Account Summary Cards -->
            <div class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-3">
                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __("Account Balance") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-red-600 dark:text-red-400"
                            >
                                {{ formatCurrency(account_balance) }}
                            </p>
                        </div>
                        <div
                            class="p-3 bg-red-100 rounded-full dark:bg-red-900"
                        >
                            <svg
                                class="w-6 h-6 text-red-600 dark:text-red-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __("Credit Limit") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-gray-800 dark:text-gray-200"
                            >
                                {{ formatCurrency(customer.credit_limit || 0) }}
                            </p>
                        </div>
                        <div
                            class="p-3 bg-blue-100 rounded-full dark:bg-blue-900"
                        >
                            <svg
                                class="w-6 h-6 text-blue-600 dark:text-blue-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-white rounded-lg shadow dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ __("Total Invoices") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-gray-800 dark:text-gray-200"
                            >
                                {{ invoices.total }}
                            </p>
                        </div>
                        <div
                            class="p-3 bg-yellow-100 rounded-full dark:bg-yellow-900"
                        >
                            <svg
                                class="w-6 h-6 text-yellow-600 dark:text-yellow-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mt-8">
                <div class="sm:hidden">
                    <label for="tabs" class="sr-only">Select a tab</label>
                    <select
                        id="tabs"
                        name="tabs"
                        class="block w-full rounded-md border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                        v-model="activeTab"
                    >
                        <option v-for="tab in tabs" :key="tab.id" :value="tab.id">{{ __(tab.name) }}</option>
                    </select>
                </div>
                <div class="hidden sm:block">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                @click="activeTab = tab.id"
                                :class="[
                                    activeTab === tab.id
                                        ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                                    'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors'
                                ]"
                            >
                                {{ __(tab.name) }}
                            </button>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="mt-6">
                <!-- Invoices Tab -->
                <div v-if="activeTab === 'invoices'">
                    <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Invoice") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Total") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Paid") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Balance") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Status") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Date") }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        <Link :href="route('invoices.show', invoice.id)" class="text-emerald-600 hover:text-emerald-500 transition-colors">
                                            #{{ invoice.serial_number || invoice.id }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatCurrency(invoice.total, invoice.currency) }}</td>
                                    <td class="px-6 py-4 text-sm text-emerald-600 font-medium whitespace-nowrap">{{ formatCurrency(invoice.paid_amount, invoice.currency) }}</td>
                                    <td class="px-6 py-4 text-sm text-red-600 font-bold whitespace-nowrap">{{ formatCurrency(invoice.remaining_balance, invoice.currency) }}</td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full"
                                            :class="{
                                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': invoice.payment_status === 'unpaid',
                                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': invoice.payment_status === 'partially_paid',
                                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': invoice.payment_status === 'paid',
                                            }">
                                            {{ __(invoice.payment_status.replace("_", " ")) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDate(invoice.created_at) }}</td>
                                </tr>
                                <tr v-if="invoices.data.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-sm text-center text-gray-500 dark:text-gray-400">{{ __("No invoices found") }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <Pagination :links="invoices.links" />
                    </div>
                </div>

                <!-- Payments Tab -->
                <div v-if="activeTab === 'payments'">
                    <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Invoice") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Amount") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Method") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Reference") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Date") }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        <Link :href="route('invoices.show', payment.invoice.id)" class="text-emerald-600 hover:text-emerald-500 transition-colors">
                                            #{{ payment.invoice.serial_number || payment.invoice.id }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-emerald-600 font-bold whitespace-nowrap">{{ formatCurrency(payment.amount, payment.currency) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ __(payment.payment_method.replace("_", " ").toUpperCase()) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ payment.reference || "-" }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDate(payment.paid_at) }}</td>
                                </tr>
                                <tr v-if="payments.data.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-sm text-center text-gray-500 dark:text-gray-400">{{ __("No payments found") }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <Pagination :links="payments.links" />
                    </div>
                </div>

                <!-- Transactions Tab -->
                <div v-if="activeTab === 'transactions'">
                    <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Product") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Qty") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Price") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Total") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Invoice") }}</th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Date") }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                <tr v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ transaction.product.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ transaction.quantity }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatCurrency(transaction.price, transaction.currency) }}</td>
                                    <td class="px-6 py-4 text-sm text-emerald-600 font-bold whitespace-nowrap">{{ formatCurrency(transaction.quantity * transaction.price, transaction.currency) }}</td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap">
                                        <Link :href="route('invoices.show', transaction.invoice.id)" class="text-emerald-600 hover:text-emerald-500 transition-colors">
                                            #{{ transaction.invoice.serial_number || transaction.invoice.id }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDate(transaction.created_at) }}</td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-sm text-center text-gray-500 dark:text-gray-400">{{ __("No transactions found") }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <Pagination :links="transactions.links" />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
