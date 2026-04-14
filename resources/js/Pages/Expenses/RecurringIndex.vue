<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import Pagination from "@/Shared/Pagination.vue";

defineProps({
    recurring_expenses: Object,
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
    if (!date) return __("Never");
    return new Intl.DateTimeFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        dateStyle: 'medium',
    }).format(new Date(date));
};

const toggleStatus = (id) => {
    router.put(route("recurring-expenses.toggle", id));
};

const deleteTemplate = (id) => {
    if (confirm(__("Are you sure you want to delete this template? Future expenses will not be generated."))) {
        router.delete(route("recurring-expenses.destroy", id));
    }
};
</script>

<template>
    <AppLayout :title="__('Recurring Expense Templates')">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <Link :href="route('expenses.index')" class="text-gray-500 hover:text-emerald-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                        </Link>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                            {{ __("Recurring Expense Templates") }}
                        </h2>

                        <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                            {{ recurring_expenses.total }} {{ __("Templates") }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                    <Link
                        :href="route('recurring-expenses.create')"
                        class="px-5 py-2.5 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg bg-emerald-500 hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    >
                        + {{ __("New Template") }}
                    </Link>
                </div>
            </div>

            <div class="flex flex-col mt-8">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                        <div class="overflow-hidden border border-gray-200 dark:border-gray-700 md:rounded-lg shadow-sm">
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
                                            {{ __("Frequency") }}
                                        </th>
                                        <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            {{ __("Status") }}
                                        </th>
                                        <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            {{ __("Next Due") }}
                                        </th>
                                        <th scope="col" class="px-4 py-3.5 text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            {{ __("Expenses") }}
                                        </th>
                                        <th scope="col" class="relative py-3.5 px-4">
                                            <span class="sr-only">{{ __("Actions") }}</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                    <tr v-for="template in recurring_expenses.data" :key="template.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                            {{ template.title }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap font-bold">
                                            {{ formatCurrency(template.amount, template.currency) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap uppercase tracking-wider font-medium text-[10px]">
                                            {{ __(template.frequency) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm whitespace-nowrap">
                                            <button @click="toggleStatus(template.id)" class="focus:outline-none">
                                                <span :class="[
                                                    'px-2 py-1 text-xs font-semibold rounded-full',
                                                    template.is_active ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' : 'text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-400'
                                                ]">
                                                    {{ template.is_active ? __("Active") : __("Paused") }}
                                                </span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                            {{ formatDate(template.starts_at) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap">
                                            <Link :href="route('expenses.index', { search: template.title })" class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors font-bold text-xs uppercase tracking-widest">
                                                {{ template.expenses_count }} {{ __("Generated") }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-4 text-sm whitespace-nowrap text-right rtl:text-left">
                                            <div class="flex items-center justify-end gap-x-3">
                                                <Link :href="route('recurring-expenses.edit', template.id)" class="text-gray-500 hover:text-emerald-500 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                </Link>
                                                <button @click="deleteTemplate(template.id)" class="text-gray-500 hover:text-red-500 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="recurring_expenses.data.length === 0">
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                            {{ __("No recurring templates found. Create one from the Record Expense form.") }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <Pagination :links="recurring_expenses.links" />
            </div>
        </section>
    </AppLayout>
</template>
