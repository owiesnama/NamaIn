<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import Pagination from "@/Shared/Pagination.vue";
import EmptySearch from "@/Shared/EmptySearch.vue";
import FilterSidebar from "@/Shared/FilterSidebar.vue";
import { watch, ref } from "vue";
import { useQueryString } from "@/Composables/useQueryString";
import { debounce } from "lodash";

defineProps({
    payments: Object,
});

const showSidebar = ref(true);

const filters = ref({
    search: useQueryString("search").value,
    status: useQueryString("status").value,
    sort_by: useQueryString("sort_by").value || "created_at",
    sort_order: useQueryString("sort_order").value || "desc"
});

const resetFilters = () => {
    filters.value = {
        search: null,
        status: null,
        sort_by: "created_at",
        sort_order: "desc"
    };
};

const sortByOptions = [
    { label: __("Date"), value: "created_at" },
    { label: __("Payment Number"), value: "id" },
    { label: __("Amount"), value: "amount" },
];

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

watch(
    filters,
    debounce(function () {
        router.get(route("payments.index"), filters.value, { preserveState: true });
    }, 300),
    { deep: true }
);
</script>

<template>
    <AppLayout title="Payments">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
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
                class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0"
            >
                <button
                    @click="showSidebar = !showSidebar"
                    :class="[
                        'inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700 transition-colors',
                        showSidebar ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20' : ''
                    ]"
                    :title="__('Filters')"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    </svg>
                </button>

                <Link
                    :href="route('payments.create')"
                    class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                >
                    + {{ __("Record Payment") }}
                </Link>
            </div>
        </div>

        <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
            <!-- Sidebar -->
            <FilterSidebar
                v-if="showSidebar"
                v-model:filters="filters"
                :sort-by-options="sortByOptions"
                :all-label="__('All Payments')"
                @reset="resetFilters"
            />

            <!-- Payments List -->
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-start rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Invoice") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-start rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Related Party") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-start rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Amount") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-start rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Method") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-start rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Date") }}
                                    </th>

                                    <th
                                        scope="col"
                                        class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-start rtl:text-right text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __("Recorded By") }}
                                    </th>

                                    <th scope="col" class="relative py-3.5 px-8">
                                        <span class="sr-only">{{ __("View") }}</span>
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
                                        class="px-8 py-4 text-sm whitespace-nowrap"
                                    >
                                        <Link
                                            :href="route('invoices.show', payment.invoice.id)"
                                            class="text-emerald-600 hover:underline font-medium"
                                        >
                                            #{{ payment.invoice.serial_number || payment.invoice.id }}
                                        </Link>
                                    </td>

                                    <td
                                        class="px-8 py-4 text-sm whitespace-nowrap"
                                    >
                                        <div class="flex items-center gap-x-2">
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ payment.invoice.invocable?.name }}</span>
                                                <span class="text-xs text-gray-500 capitalize">{{ __(payment.invoice.invocable_type.split('\\').pop().toLowerCase()) }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td
                                        class="px-8 py-4 text-sm font-semibold text-emerald-600 whitespace-nowrap"
                                    >
                                        {{ formatCurrency(payment.amount, payment.currency) }}
                                    </td>

                                    <td
                                        class="px-8 py-4 text-sm whitespace-nowrap"
                                    >
                                        <span
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="{
                                                'bg-emerald-100 text-emerald-700': payment.payment_method === 'cash',
                                                'bg-blue-100 text-blue-700': payment.payment_method === 'bank_transfer',
                                                'bg-yellow-100 text-yellow-700': payment.payment_method === 'cheque',
                                                'bg-gray-100 text-gray-700': payment.payment_method === 'credit',
                                            }"
                                        >
                                            {{ __(payment.payment_method.replace("_", " ").toUpperCase()) }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-8 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                    >
                                        {{ payment.created_at }}
                                    </td>

                                    <td
                                        class="px-8 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                    >
                                        {{ payment.created_by?.name || "-" }}
                                    </td>

                                    <td class="px-8 py-4 text-sm whitespace-nowrap text-end">
                                        <Link
                                            :href="route('payments.show', payment.id)"
                                            class="text-gray-500 transition-colors duration-200 dark:hover:text-emerald-500 dark:text-gray-300 hover:text-emerald-500 focus:outline-none"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.43 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <EmptySearch :data="payments.data"></EmptySearch>

                <div class="flex justify-center mt-8">
                    <Pagination :links="payments.links"></Pagination>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
