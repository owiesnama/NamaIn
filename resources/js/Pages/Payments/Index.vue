<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import Pagination from "@/Shared/Pagination.vue";
import EmptySearch from "@/Shared/EmptySearch.vue";

defineProps({
    payments: Object,
});
</script>

<template>
    <AppLayout title="Payments">
        <section>
            <div class="w-full lg:flex lg:items-end lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            {{ __("Payments") }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                            >{{ payments.total }} {{ __("Payment") }}</span
                        >
                    </div>
                </div>

                <div
                    class="mt-4 sm:flex sm:items-center sm:justify-between sm:gap-x-4 lg:mt-0"
                >
                    <Link
                        :href="route('payments.create')"
                        class="flex items-center justify-center px-5 py-2 text-sm tracking-wide text-white transition-colors duration-200 rounded-lg shrink-0 sm:w-auto gap-x-2 bg-emerald-500 hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="w-5 h-5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                        <span>{{ __("Record Payment") }}</span>
                    </Link>
                </div>
            </div>

            <div class="flex flex-col mt-6">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div
                        class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8"
                    >
                        <div
                            class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700"
                        >
                            <table
                                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <thead class="bg-gray-100 dark:bg-gray-800">
                                    <tr>
                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Invoice") }}
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Customer") }}
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Amount") }}
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Method") }}
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Reference") }}
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Date") }}
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            {{ __("Recorded By") }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
                                >
                                    <tr
                                        v-for="payment in payments.data"
                                        :key="payment.id"
                                    >
                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right whitespace-nowrap"
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
                                                    payment.invoice
                                                        .serial_number ||
                                                    payment.invoice.id
                                                }}
                                            </Link>
                                        </td>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-700 whitespace-nowrap"
                                        >
                                            {{
                                                payment.invoice.invocable?.name
                                            }}
                                        </td>

                                        <td
                                            class="px-8 py-3 text-sm font-semibold text-left rtl:text-right text-emerald-600 whitespace-nowrap"
                                        >
                                            {{ payment.amount }} SDG
                                        </td>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right whitespace-nowrap"
                                        >
                                            <span
                                                class="px-2 py-1 text-xs rounded-full"
                                                :class="{
                                                    'bg-emerald-100 text-emerald-700':
                                                        payment.payment_method ===
                                                        'cash',
                                                    'bg-blue-100 text-blue-700':
                                                        payment.payment_method ===
                                                        'bank_transfer',
                                                    'bg-yellow-100 text-yellow-700':
                                                        payment.payment_method ===
                                                        'cheque',
                                                    'bg-gray-100 text-gray-700':
                                                        payment.payment_method ===
                                                        'credit',
                                                }"
                                            >
                                                {{
                                                    __(
                                                        payment.payment_method
                                                            .replace("_", " ")
                                                            .toUpperCase()
                                                    )
                                                }}
                                            </span>
                                        </td>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-700 whitespace-nowrap"
                                        >
                                            {{ payment.reference || "-" }}
                                        </td>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-700 whitespace-nowrap"
                                        >
                                            {{
                                                new Date(
                                                    payment.paid_at
                                                ).toLocaleDateString()
                                            }}
                                        </td>

                                        <td
                                            class="px-8 py-3 text-sm text-left rtl:text-right text-gray-700 whitespace-nowrap"
                                        >
                                            {{
                                                payment.created_by?.name || "-"
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <EmptySearch :data="payments.data"></EmptySearch>
            </div>

            <Pagination :data="payments" />
        </section>
    </AppLayout>
</template>
