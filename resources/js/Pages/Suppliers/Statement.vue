<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { format, parseISO } from "date-fns";

const props = defineProps({
    supplier: Object,
    invoices: Array,
    payments: Array,
    start_date: String,
    end_date: String,
    opening_balance: Number,
});

const filters = ref({
    start_date: props.start_date ? format(parseISO(props.start_date), "yyyy-MM-dd") : "",
    end_date: props.end_date ? format(parseISO(props.end_date), "yyyy-MM-dd") : "",
});

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
        return format(parseISO(dateString), "yyyy-MM-dd");
    } catch (e) {
        return "-";
    }
};

const applyFilters = () => {
    router.get(route('suppliers.statement', props.supplier.id), filters.value, {
        preserveState: true,
        replace: true,
    });
};

const printStatement = () => {
    const url = route('suppliers.print-statement', {
        supplier: props.supplier.id,
        start_date: filters.value.start_date,
        end_date: filters.value.end_date,
    });
    window.open(url, '_blank');
};

// Combine and sort activities
const statementLines = computed(() => {
    const activities = [
        ...props.invoices.map(i => ({ ...i, type: 'invoice', date: i.created_at, amount: i.total })),
        ...props.payments.map(p => ({ ...p, type: 'payment', date: p.paid_at, amount: p.amount }))
    ].sort((a, b) => new Date(a.date) - new Date(b.date));

    let runningBalance = props.opening_balance;
    return activities.map(activity => {
        if (activity.type === 'invoice') {
            runningBalance += parseFloat(activity.amount);
        } else {
            runningBalance -= parseFloat(activity.amount);
        }
        return { ...activity, balance: runningBalance };
    });
});
</script>

<template>
    <AppLayout :title="`${supplier.name} Statement`">
        <section class="print:m-0">
            <div class="flex items-center justify-between mb-6 print:hidden">
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">
                    {{ __("Supplier Statement") }} - {{ supplier.name }}
                </h2>
                <div class="flex gap-x-3">
                    <button
                        @click="printStatement"
                        class="px-5 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                    >
                        {{ __("Print") }}
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="p-4 mb-6 bg-white rounded-lg shadow dark:bg-gray-800 print:hidden">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __("Start Date") }}</label>
                        <input
                            type="date"
                            v-model="filters.start_date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __("End Date") }}</label>
                        <input
                            type="date"
                            v-model="filters.end_date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300"
                        >
                    </div>
                    <div>
                        <button
                            @click="applyFilters"
                            class="w-full px-5 py-2 text-sm text-white bg-emerald-500 rounded-lg hover:bg-emerald-600"
                        >
                            {{ __("Filter") }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statement Header (Visible on Print) -->
            <div class="hidden print:block mb-8">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ supplier.name }}</h1>
                        <p class="text-gray-600">{{ supplier.address }}</p>
                        <p class="text-gray-600">{{ supplier.phone_number }}</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-xl font-bold uppercase text-gray-500">{{ __("Statement") }}</h2>
                        <p class="text-sm text-gray-600">{{ formatDate(filters.start_date) }} {{ __("to") }} {{ formatDate(filters.end_date) }}</p>
                    </div>
                </div>
            </div>

            <!-- Statement Table -->
            <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Date") }}</th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Description") }}</th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Debit") }}</th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Credit") }}</th>
                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Balance") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ formatDate(filters.start_date) }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ __("Opening Balance") }}</td>
                            <td class="px-6 py-4"></td>
                            <td class="px-6 py-4"></td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ formatCurrency(opening_balance) }}</td>
                        </tr>
                        <tr v-for="(line, index) in statementLines" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDate(line.date) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <span v-if="line.type === 'invoice'">
                                    {{ __("Invoice") }} #{{ line.serial_number || line.id }}
                                </span>
                                <span v-else>
                                    {{ __("Payment") }} - {{ __(line.payment_method.replace('_', ' ')) }} {{ line.reference ? `(${line.reference})` : '' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-red-600 whitespace-nowrap">
                                {{ line.type === 'invoice' ? formatCurrency(line.amount) : '' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-emerald-600 whitespace-nowrap">
                                {{ line.type === 'payment' ? formatCurrency(line.amount) : '' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                                {{ formatCurrency(line.balance) }}
                            </td>
                        </tr>
                        <tr v-if="statementLines.length === 0">
                            <td colspan="5" class="px-6 py-4 text-sm text-center text-gray-500 dark:text-gray-400">{{ __("No transactions found for this period") }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-sm font-bold text-end text-gray-900 dark:text-white">{{ __("Closing Balance") }}</td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ formatCurrency(statementLines.length > 0 ? statementLines[statementLines.length - 1].balance : opening_balance) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
