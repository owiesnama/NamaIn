<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { debounce } from "lodash";
import Pagination from "@/Shared/Pagination.vue";
import EmptySearch from "@/Shared/EmptySearch.vue";
import FilterSidebar from "@/Shared/FilterSidebar.vue";
import { useQueryString } from "@/Composables/useQueryString";

defineProps({
    expenses: Object,
    categories: Array,
    users: Array,
    category_budgets: Array,
    spending_by_category: Array,
});

const showSidebar = ref(true);

const filters = ref({
    search: useQueryString("search").value,
    category: useQueryString("category").value,
    status: useQueryString("status").value,
    from_date: useQueryString("from_date").value,
    to_date: useQueryString("to_date").value,
    min_amount: useQueryString("min_amount").value,
    max_amount: useQueryString("max_amount").value,
    created_by: useQueryString("created_by").value,
});

const resetFilters = () => {
    filters.value = {
        search: null,
        category: null,
        status: null,
        from_date: null,
        to_date: null,
        min_amount: null,
        max_amount: null,
        created_by: null,
    };
};

watch(
    filters,
    debounce(function () {
        router.get(route("expenses.index"), filters.value, { preserveState: true });
    }, 300),
    { deep: true }
);

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');

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

const approveExpense = (expenseId) => {
    if (confirm(__("Are you sure you want to approve this expense?"))) {
        router.put(route("expenses.approval", expenseId), { status: 'approved' });
    }
};

const rejectExpense = (expenseId) => {
    if (confirm(__("Are you sure you want to reject this expense?"))) {
        router.put(route("expenses.approval", expenseId), { status: 'rejected' });
    }
};

const getBudgetColor = (budget) => {
    const percentage = (budget.spent / budget.limit) * 100;
    if (percentage >= 100) return 'bg-red-500';
    if (percentage >= 75) return 'bg-amber-500';
    return 'bg-emerald-500';
};
</script>

<template>
    <AppLayout :title="__('Expenses')">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                            {{ __("Expenses") }}
                        </h2>

                        <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                            {{ expenses.total }} {{ __("Expenses") }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
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
                        :href="route('expenses.export', filters)"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                    >
                        {{ __("Export") }}
                    </Link>

                    <Link
                        :href="route('recurring-expenses.index')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                    >
                        {{ __("Recurring Templates") }}
                    </Link>

                    <Link
                        :href="route('expenses.create')"
                        class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    >
                        + {{ __("Record Expense") }}
                    </Link>
                </div>
            </div>

            <div v-if="category_budgets && category_budgets.some(b => (b.spent / b.limit) >= 0.75)" class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="budget in category_budgets.filter(b => (b.spent / b.limit) >= 0.75)" :key="budget.id" class="p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl transition-all">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ budget.name }}</span>
                        <span class="text-xs font-medium" :class="budget.spent >= budget.limit ? 'text-red-500' : 'text-amber-500'">
                            {{ Math.round((budget.spent / budget.limit) * 100) }}% {{ __("used") }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div :class="['h-2 rounded-full', getBudgetColor(budget)]" :style="{ width: Math.min((budget.spent / budget.limit) * 100, 100) + '%' }"></div>
                    </div>
                    <div class="flex justify-between mt-2 text-[10px] text-gray-500">
                        <span>{{ formatCurrency(budget.spent) }}</span>
                        <span>{{ __("Limit") }}: {{ formatCurrency(budget.limit) }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-8 mt-8">
                <FilterSidebar
                    v-if="showSidebar"
                    v-model:filters="filters"
                    @reset="resetFilters"
                    :categories="categories"
                    :sortByOptions="[]"
                >
                    <template #extra-filters>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Status") }}</label>
                            <VueMultiselect
                                :model-value="[{ id: 'pending', label: __('Pending') }, { id: 'approved', label: __('Approved') }, { id: 'rejected', label: __('Rejected') }].find(o => o.id === filters.status)"
                                :options="[{ id: 'pending', label: __('Pending') }, { id: 'approved', label: __('Approved') }, { id: 'rejected', label: __('Rejected') }]"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('All Statuses')"
                                label="label"
                                track-by="id"
                                class="w-full"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                @update:model-value="option => filters.status = option?.id || null"
                            />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("From Date") }}</label>
                            <input v-model="filters.from_date" type="date" class="block w-full py-2 px-3 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("To Date") }}</label>
                            <input v-model="filters.to_date" type="date" class="block w-full py-2 px-3 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Min") }}</label>
                                <input v-model="filters.min_amount" type="number" step="0.01" class="block w-full py-2 px-3 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Max") }}</label>
                                <input v-model="filters.max_amount" type="number" step="0.01" class="block w-full py-2 px-3 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Created By") }}</label>
                            <VueMultiselect
                                :model-value="users.find(u => u.id == filters.created_by)"
                                :options="users"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('All Users')"
                                label="name"
                                track-by="id"
                                class="w-full"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                @update:model-value="option => filters.created_by = option?.id || null"
                            />
                        </div>
                    </template>
                </FilterSidebar>

                <div class="flex-1 overflow-hidden">
                    <div v-if="spending_by_category && spending_by_category.length" class="mb-6 p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl transition-all">
                        <h3 class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-4">{{ __("Spending by Category") }}</h3>
                        <div class="flex flex-wrap gap-4">
                            <div v-for="item in spending_by_category" :key="item.name" class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ item.name }}:</span>
                                <span class="text-xs font-bold text-gray-900 dark:text-white">{{ formatCurrency(item.total) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-xl transition-all">
                                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                                    <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                        <tr>
                                            <th scope="col" class="py-3.5 px-4 text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                {{ __("Title") }}
                                            </th>
                                            <th scope="col" class="px-4 py-3.5 text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                {{ __("Amount") }}
                                            </th>
                                            <th scope="col" class="px-4 py-3.5 text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                {{ __("Status") }}
                                            </th>
                                            <th scope="col" class="px-4 py-3.5 text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                {{ __("Date") }}
                                            </th>
                                            <th scope="col" class="px-4 py-3.5 text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                                {{ __("Categories") }}
                                            </th>
                                            <th scope="col" class="relative py-3.5 px-4">
                                                <span class="sr-only">{{ __("Actions") }}</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100 dark:divide-gray-800 dark:bg-gray-900">
                                        <tr v-for="expense in expenses.data" :key="expense.id" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors" @click="router.visit(route('expenses.show', expense.id))">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <svg v-if="expense.receipt_path" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-emerald-500" title="Has Receipt">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                    </svg>
                                                    {{ expense.title }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-300 whitespace-nowrap font-bold">
                                                {{ formatCurrency(expense.amount, expense.currency) }}
                                            </td>
                                            <td class="px-4 py-4 text-sm whitespace-nowrap">
                                                <span :class="[
                                                    'px-2 py-1 text-xs font-semibold rounded-full',
                                                    expense.status === 'approved' ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                                    expense.status === 'rejected' ? 'text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-400' :
                                                    'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400'
                                                ]">
                                                    {{ __(expense.status.charAt(0).toUpperCase() + expense.status.slice(1)) }}
                                                </span>
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
                                            <td class="px-4 py-4 text-sm whitespace-nowrap text-right rtl:text-left">
                                                <div class="flex items-center justify-end gap-x-3" @click.stop>
                                                    <template v-if="expense.status === 'pending'">
                                                        <button @click="approveExpense(expense.id)" class="text-emerald-500 hover:text-emerald-700 transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                            </svg>
                                                        </button>
                                                        <button @click="rejectExpense(expense.id)" class="text-red-500 hover:text-red-700 transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </template>
                                                    <Link :href="route('expenses.show', expense.id)" class="text-gray-500 hover:text-emerald-500 transition-colors">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                    </Link>
                                                    <Link :href="route('expenses.edit', expense.id)" class="text-gray-500 transition-colors duration-200 dark:hover:text-emerald-500 dark:text-gray-300 hover:text-emerald-500 focus:outline-none">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                        </svg>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="expenses.data.length === 0">
                                            <td colspan="6" class="px-4 py-12">
                                                <EmptySearch :data="expenses.data" />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <Pagination :links="expenses.links" />
            </div>
        </section>
    </AppLayout>
</template>
