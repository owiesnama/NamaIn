<script setup>
import { ref, watch } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import { debounce } from "lodash";

const props = defineProps({
    invoices: Object,
    sessions: Array,
    summary: Object,
    filters: Object,
});

const formFilters = ref({
    search: props.filters?.search ?? "",
    session_id: props.filters?.session_id ?? "",
    from_date: props.filters?.from_date ?? "",
    to_date: props.filters?.to_date ?? "",
    customer_type: props.filters?.customer_type ?? "",
});

const applyFilters = debounce(() => {
    router.get(route("pos.invoices"), formFilters.value, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch(formFilters, applyFilters, { deep: true });

const resetFilters = () => {
    formFilters.value = {
        search: "",
        session_id: "",
        from_date: "",
        to_date: "",
        customer_type: "",
    };
};

const currencyValue = (amount) => {
    return Number(amount || 0).toLocaleString();
};
</script>

<template>
    <AppLayout :title="__('POS History')">
        <div class="space-y-6">
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('POS History') }}</h2>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                            {{ invoices.total }} {{ __('Sales') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __('Total Revenue') }}</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ currencyValue(summary.total_revenue) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __('Total Sales') }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.total_sales }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __('Walk-in Sales') }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.walk_in_sales }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __('Named Customer Sales') }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ summary.named_customer_sales }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
                    <div class="xl:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Search') }}</label>
                        <input
                            v-model="formFilters.search"
                            type="text"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                            :placeholder="__('Invoice # or customer')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Session') }}</label>
                        <select
                            v-model="formFilters.session_id"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="">{{ __('All Sessions') }}</option>
                            <option v-for="session in sessions" :key="session.id" :value="session.id">
                                #{{ session.id }} - {{ session.cashier_name ?? __('Unknown') }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Customer Type') }}</label>
                        <select
                            v-model="formFilters.customer_type"
                            class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="">{{ __('All') }}</option>
                            <option value="walk_in">{{ __('Walk-in') }}</option>
                            <option value="named">{{ __('Named') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('From Date') }}</label>
                        <input v-model="formFilters.from_date" type="date" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('To Date') }}</label>
                        <input v-model="formFilters.to_date" type="date" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button @click="resetFilters" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                        {{ __('Reset') }}
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Date') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Invoice #') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Customer') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Session') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Items') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Total') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                            <tr v-for="invoice in invoices.data" :key="invoice.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ invoice.created_at_human ?? invoice.created_at }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700 dark:text-gray-300">#{{ invoice.serial_number ?? invoice.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-x-2">
                                        <span>{{ invoice.invocable?.name ?? __('Walk-in Customer') }}</span>
                                        <span v-if="invoice.invocable?.is_system" class="px-2.5 py-0.5 text-[11px] font-bold rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400">{{ __('Walk-in') }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">#{{ invoice.pos_session_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ invoice.transactions?.length ?? 0 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700 dark:text-gray-300">{{ currencyValue(invoice.total) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    <div class="flex items-center gap-x-2">
                                        <Link :href="route('invoices.show', invoice.id)" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300">{{ __('View') }}</Link>
                                        <a :href="route('invoices.print', invoice.id)" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300">{{ __('Print Receipt') }}</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!invoices.data.length" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('No POS invoices found.') }}
                </div>
            </div>

            <Pagination :links="invoices.links" />
        </div>
    </AppLayout>
</template>

