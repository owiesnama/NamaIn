<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";

defineProps({
    customer: Object,
    account_balance: Number,
    unpaid_invoices: Array,
    payment_history: Array,
});
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

                <Link
                    :href="route('payments.create')"
                    class="px-5 py-2 text-sm text-white bg-emerald-500 rounded-lg hover:bg-emerald-600"
                >
                    {{ __("Record Payment") }}
                </Link>
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
                                {{ account_balance }} SDG
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
                                {{ customer.credit_limit || 0 }} SDG
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
                                {{ __("Unpaid Invoices") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-gray-800 dark:text-gray-200"
                            >
                                {{ unpaid_invoices.length }}
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

            <!-- Unpaid Invoices Table -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                    {{ __("Unpaid Invoices") }}
                </h3>

                <div
                    class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Invoice") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Total") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Paid") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Balance") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Status") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Date") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
                        >
                            <tr
                                v-for="invoice in unpaid_invoices"
                                :key="invoice.id"
                            >
                                <td
                                    class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    <Link
                                        :href="
                                            route('invoices.show', invoice.id)
                                        "
                                        class="text-emerald-600 hover:underline"
                                    >
                                        #{{
                                            invoice.serial_number || invoice.id
                                        }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ invoice.total }} SDG
                                </td>
                                <td class="px-6 py-4 text-sm text-emerald-600">
                                    {{ invoice.paid_amount }} SDG
                                </td>
                                <td class="px-6 py-4 text-sm text-red-600 font-semibold">
                                    {{ invoice.remaining_balance }} SDG
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="{
                                            'bg-red-100 text-red-700':
                                                invoice.payment_status ===
                                                'unpaid',
                                            'bg-yellow-100 text-yellow-700':
                                                invoice.payment_status ===
                                                'partially_paid',
                                        }"
                                    >
                                        {{
                                            __(
                                                invoice.payment_status.replace(
                                                    "_",
                                                    " "
                                                )
                                            )
                                        }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{
                                        new Date(
                                            invoice.created_at
                                        ).toLocaleDateString()
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment History -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                    {{ __("Payment History") }}
                </h3>

                <div
                    class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-100 dark:bg-gray-800">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Invoice") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Amount") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Method") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Reference") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                                >
                                    {{ __("Date") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
                        >
                            <tr
                                v-for="payment in payment_history"
                                :key="payment.id"
                            >
                                <td
                                    class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100"
                                >
                                    <Link
                                        :href="
                                            route(
                                                'invoices.show',
                                                payment.invoice.id
                                            )
                                        "
                                        class="text-emerald-600 hover:underline"
                                    >
                                        #{{
                                            payment.invoice.serial_number ||
                                            payment.invoice.id
                                        }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm text-emerald-600 font-semibold">
                                    {{ payment.amount }} SDG
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{
                                        __(
                                            payment.payment_method
                                                .replace("_", " ")
                                                .toUpperCase()
                                        )
                                    }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ payment.reference || "-" }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{
                                        new Date(
                                            payment.paid_at
                                        ).toLocaleDateString()
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
