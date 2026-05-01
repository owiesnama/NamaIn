<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link, router } from "@inertiajs/vue3";

const props = defineProps({
    expense: Object,
});

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const formatDate = (date) => {
    if (!date) return "";
    return new Intl.DateTimeFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
};

const approveExpense = () => {
    if (confirm(__("Are you sure you want to approve this expense?"))) {
        router.put(route("expenses.approval", props.expense.id), { status: 'approved' });
    }
};

const rejectExpense = () => {
    if (confirm(__("Are you sure you want to reject this expense?"))) {
        router.put(route("expenses.approval", props.expense.id), { status: 'rejected' });
    }
};
</script>

<template>
    <AppLayout :title="__('Expense Details')">
        <section>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <Link :href="route('expenses.index')" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        {{ __("Back to List") }}
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ expense.title }}
                    </h2>
                </div>

                <div class="flex items-center gap-3">
                    <template v-if="expense.status === 'pending'">
                        <button @click="approveExpense" class="px-5 py-2 text-sm font-bold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition-colors">
                            {{ __("Approve") }}
                        </button>
                        <button @click="rejectExpense" class="px-5 py-2 text-sm font-bold text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors">
                            {{ __("Reject") }}
                        </button>
                    </template>
                    <Link :href="route('expenses.edit', expense.id)" class="px-5 py-2 text-sm font-bold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700 transition-colors">
                        {{ __("Edit") }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Expense Information") }}</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Amount") }}</label>
                                <p class="text-2xl font-black text-gray-900 dark:text-white">{{ formatCurrency(expense.amount, expense.currency) }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Date") }}</label>
                                <p class="text-lg font-bold text-gray-700 dark:text-gray-200">{{ formatDate(expense.expensed_at) }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Status") }}</label>
                                <span :class="[
                                    'px-3 py-1 text-xs font-bold rounded-full inline-block mt-1',
                                    expense.status === 'approved' ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                    expense.status === 'rejected' ? 'text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-400' :
                                    'text-amber-700 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400'
                                ]">
                                    {{ __(expense.status.charAt(0).toUpperCase() + expense.status.slice(1)) }}
                                </span>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Categories") }}</label>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span v-for="category in expense.categories" :key="category.id" class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded font-medium">
                                        {{ category.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="expense.notes" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Notes") }}</h3>
                        </div>
                        <div class="p-6 text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                            {{ expense.notes }}
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Audit Trail") }}</h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Created By") }}</label>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ expense.created_by?.name }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(expense.created_at) }}</p>
                            </div>

                            <div v-if="expense.status !== 'pending'">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">
                                    {{ expense.status === 'approved' ? __("Approved By") : __("Rejected By") }}
                                </label>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ expense.approved_by?.name }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(expense.approved_at) }}</p>
                            </div>

                            <div v-if="expense.recurring_expense_id">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Source") }}</label>
                                <Link :href="route('recurring-expenses.index')" class="text-sm font-bold text-emerald-600 hover:underline">
                                    {{ __("Recurring Template") }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-if="expense.receipt_path" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Receipt") }}</h3>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-col items-center justify-center p-8 bg-gray-50 dark:bg-gray-900 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-300 mb-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <a :href="route('expenses.receipt', expense.id)" target="_blank" class="px-4 py-2 bg-emerald-500 text-white text-sm font-bold rounded-lg hover:bg-emerald-600 transition-colors">
                                    {{ __("Download Receipt") }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
