<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import Pagination from "@/Shared/Pagination.vue";
import EmptySearch from "@/Shared/EmptySearch.vue";

defineProps({
    expenses: Object,
});

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const formatDate = (date) => {
    return new Intl.DateTimeFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        dateStyle: 'medium',
    }).format(new Date(date));
};
</script>

<template>
    <AppLayout title="Expenses">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ __("Expenses") }}
                    </h2>

                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ expenses.total }} {{ __("Expense") }}
                    </span>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                <Link
                    :href="route('expenses.create')"
                    class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                >
                    + {{ __("Record Expense") }}
                </Link>
            </div>
        </div>

        <div class="flex flex-col mt-8">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                    <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="py-3.5 px-4 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        {{ __("Title") }}
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        {{ __("Amount") }}
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        {{ __("Date") }}
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        {{ __("Categories") }}
                                    </th>
                                    <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                        {{ __("Created By") }}
                                    </th>
                                    <th scope="col" class="relative py-3.5 px-4">
                                        <span class="sr-only">{{ __("Actions") }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                <tr v-for="expense in expenses.data" :key="expense.id">
                                    <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        {{ expense.title }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                        {{ formatCurrency(expense.amount, expense.currency) }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                        {{ formatDate(expense.expensed_at) }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="category in expense.categories" :key="category.id" class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-800 rounded">
                                                {{ category.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                        {{ expense.created_by?.name || expense.created_by_name }}
                                    </td>
                                    <td class="px-4 py-4 text-sm whitespace-nowrap text-right rtl:text-left">
                                        <Link :href="route('expenses.edit', expense.id)" class="text-emerald-500 hover:text-emerald-700 font-medium">
                                            {{ __("Edit") }}
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="expenses.data.length === 0">
                                    <td colspan="6" class="px-4 py-12">
                                        <EmptySearch :title="__('No expenses found')" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <Pagination :links="expenses.links" />
        </div>
    </AppLayout>
</template>
