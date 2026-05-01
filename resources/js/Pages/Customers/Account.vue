<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import Pagination from "@/Shared/Pagination.vue";
import { format, parseISO } from "date-fns";

const props = defineProps({
    customer: Object,
    account_balance: Number,
    invoices: Object,
    payments: Object,
    transactions: Object,
    advances: Array,
    treasury_accounts: Array,
});

const activeTab = ref('invoices');

const tabs = [
    { id: 'invoices', name: 'Invoices' },
    { id: 'payments', name: 'Payments' },
    { id: 'transactions', name: 'Transactions' },
    { id: 'advances', name: 'Advances' },
];

// ── Record Advance form ──────────────────────────────────────
const showAdvanceForm = ref(false);
const advanceForm = useForm({
    amount: '',
    treasury_account_id: '',
    notes: '',
    given_at: new Date().toISOString().split('T')[0],
});

function submitAdvance() {
    advanceForm.post(route('customer-advances.store', props.customer.id), {
        onSuccess: () => {
            advanceForm.reset();
            showAdvanceForm.value = false;
        },
    });
}

// ── Settle Advance form ──────────────────────────────────────
const settlingAdvance = ref(null);
const settleForm = useForm({
    amount: '',
    treasury_account_id: '',
    invoice_id: '',
    notes: '',
});

function openSettle(advance) {
    settlingAdvance.value = advance;
    settleForm.reset();
    settleForm.amount = advance.remaining_balance;
    settleForm.treasury_account_id = advance.treasury_account_id;
}

function submitSettle() {
    settleForm.post(route('customer-advances.settle', settlingAdvance.value.id), {
        onSuccess: () => {
            settlingAdvance.value = null;
            settleForm.reset();
        },
    });
}

function statusClass(status) {
    return {
        'outstanding': 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400',
        'partially_settled': 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-400',
        'settled': 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400',
    }[status] ?? 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400';
}

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');

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
                                {{ __("Opening Debit") }} / {{ __("Credit") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-gray-800 dark:text-gray-200"
                            >
                                {{ formatCurrency(customer.opening_debit || 0) }} / {{ formatCurrency(customer.opening_credit || 0) }}
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
                                {{ __("Credit Limit") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-blue-600 dark:text-blue-400"
                            >
                                {{ formatCurrency(customer.credit_limit) }}
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
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mt-8">
                <div class="sm:hidden">
                    <label for="tabs" class="sr-only">{{ __("Select a tab") }}</label>
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
                                        <Link v-if="payment.invoice" :href="route('invoices.show', payment.invoice.id)" class="text-emerald-600 hover:text-emerald-500 transition-colors">
                                            #{{ payment.invoice.serial_number || payment.invoice.id }}
                                        </Link>
                                        <span v-else class="text-gray-500 italic">
                                            {{ __("Direct Payment") }}
                                        </span>
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

                <!-- Advances Tab -->
                <div v-if="activeTab === 'advances'">
                    <!-- Record Advance button -->
                    <div class="flex justify-end mb-4">
                        <button
                            @click="showAdvanceForm = !showAdvanceForm"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        >
                            <svg class="h-4 w-4 ltr:mr-2 rtl:ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ __("Record Advance") }}
                        </button>
                    </div>

                    <!-- Record Advance Form -->
                    <div v-if="showAdvanceForm" class="mb-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ __("Record New Advance") }}</h3>
                        <form @submit.prevent="submitAdvance" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Amount") }}</label>
                                <input
                                    v-model="advanceForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                                />
                                <p v-if="advanceForm.errors.amount" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ advanceForm.errors.amount }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Treasury Account") }}</label>
                                <select
                                    v-model="advanceForm.treasury_account_id"
                                    required
                                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                >
                                    <option value="">{{ __("Select account") }}</option>
                                    <option v-for="account in treasury_accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                                </select>
                                <p v-if="advanceForm.errors.treasury_account_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ advanceForm.errors.treasury_account_id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Date Given") }}</label>
                                <input
                                    v-model="advanceForm.given_at"
                                    type="date"
                                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Notes") }}</label>
                                <input
                                    v-model="advanceForm.notes"
                                    type="text"
                                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                />
                            </div>

                            <div class="sm:col-span-2 flex justify-end gap-x-3">
                                <button
                                    type="button"
                                    @click="showAdvanceForm = false"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                                >
                                    {{ __("Cancel") }}
                                </button>
                                <button
                                    type="submit"
                                    :disabled="advanceForm.processing"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                                >
                                    {{ __("Save Advance") }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Advances Table -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                    <tr>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">#</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Amount") }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Settled") }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Remaining") }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Status") }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Account") }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Given At") }}</th>
                                        <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Actions") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                                    <tr v-for="advance in advances" :key="advance.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ advance.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">{{ formatCurrency(advance.amount) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-emerald-600 dark:text-emerald-400">{{ formatCurrency(advance.settled_amount) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold" :class="advance.remaining_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'">
                                            {{ formatCurrency(advance.remaining_balance) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="statusClass(advance.status)">
                                                {{ __(advance.status.replace('_', ' ')) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ advance.treasury_account?.name ?? '—' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatDate(advance.given_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button
                                                v-if="advance.status !== 'settled'"
                                                @click="openSettle(advance)"
                                                class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors duration-200"
                                            >
                                                {{ __("Settle") }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="advances.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __("No advances found.") }}
                        </div>
                    </div>

                    <!-- Settle Advance Modal -->
                    <Transition
                        enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div v-if="settlingAdvance" class="fixed inset-0 z-50 flex items-center justify-center">
                            <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="settlingAdvance = null" />
                            <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                                <div class="mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("Settle Advance") }} #{{ settlingAdvance.id }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __("Remaining balance") }}: <span class="font-semibold text-red-600 dark:text-red-400">{{ formatCurrency(settlingAdvance.remaining_balance) }}</span>
                                    </p>
                                </div>

                                <form @submit.prevent="submitSettle" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Settlement Amount") }}</label>
                                        <input
                                            v-model="settleForm.amount"
                                            type="number"
                                            step="0.01"
                                            :max="settlingAdvance.remaining_balance"
                                            min="0.01"
                                            required
                                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                        />
                                        <p v-if="settleForm.errors.amount" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ settleForm.errors.amount }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Treasury Account") }}</label>
                                        <select
                                            v-model="settleForm.treasury_account_id"
                                            required
                                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                        >
                                            <option value="">{{ __("Select account") }}</option>
                                            <option v-for="account in treasury_accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                                        </select>
                                        <p v-if="settleForm.errors.treasury_account_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ settleForm.errors.treasury_account_id }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">
                                            {{ __("Invoice Offset (optional)") }}
                                        </label>
                                        <input
                                            v-model="settleForm.invoice_id"
                                            type="number"
                                            placeholder="Invoice ID"
                                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                                        />
                                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __("Leave blank for direct cash repayment") }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right mb-1">{{ __("Notes") }}</label>
                                        <input
                                            v-model="settleForm.notes"
                                            type="text"
                                            class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                        />
                                    </div>

                                    <div class="flex justify-end gap-x-3 mt-6">
                                        <button
                                            type="button"
                                            @click="settlingAdvance = null"
                                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                                        >
                                            {{ __("Cancel") }}
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="settleForm.processing"
                                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                                        >
                                            {{ __("Confirm Settlement") }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
