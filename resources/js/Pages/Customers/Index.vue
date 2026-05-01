<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch, ref } from "vue";
    import { router, useForm, Link } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import CustomerForm from "@/Components/Customers/CustomerForm.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import DeleteCustomer from "@/Components/Customers/DeleteCustomer.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { usePermissions } from "@/Composables/usePermissions";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    import Tooltip from "@/Components/Tooltip.vue";
    import { useDate } from '@/Composables/useDate';

    const { can } = usePermissions();

    defineProps({
        customers: Array,
        categories: Array
    });

    const formatCurrency = (amount, currency = 'SDG') => {
        const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');
        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: validCurrency,
        }).format(amount);
    };

    const { formatDate } = useDate();

    const showSidebar = ref(true);

    let importForm = useForm({
        file: null
    });

    let submitImport = (files) => {
        importForm.file = files[0];
        importForm.post(route("customers.import"));
    };

    const {
        search: searchQS,
        status: statusQS,
        category: categoryQS,
        sort_by: sortByQS,
        sort_order: sortOrderQS
    } = useQueryString([
        "search",
        "status",
        "category",
        "sort_by",
        "sort_order",
    ]);

    const filters = ref({
        search: searchQS.value,
        status: statusQS.value,
        category: categoryQS.value,
        sort_by: sortByQS.value || "name",
        sort_order: sortOrderQS.value || "asc"
    });

    const resetFilters = () => {
        filters.value = {
            search: null,
            status: null,
            category: null,
            sort_by: "name",
            sort_order: "asc"
        };
    };

    const sortByOptions = [
        { label: __("Name"), value: "name" },
        { label: __("Added Time"), value: "created_at" },
        { label: __("Credit Limit"), value: "credit_limit" },
    ];

    watch(
        filters,
        debounce(function() {
            router.get(
                route("customers.index"),
                filters.value,
                { preserveState: true }
            );
        }, 300),
        { deep: true }
    );
</script>

<template>
    <AppLayout :title="__('Customers')">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            {{ __("Customers") }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                        >{{ customers.length }} {{ __("Customer") }}</span
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

                    <div class="flex items-center gap-x-2">
                        <FileUploadButton
                            @input="submitImport"
                        >{{ __("Import") }}
                        </FileUploadButton>

                        <a
                            :href="route('customers.import.sample')"
                            class="inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                            :title="__('Download Sample')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>

                        <a
                            :href="route('customers.export')"
                            class="inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                            :title="__('Export')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </a>
                    </div>

                    <CustomerForm v-if="can('customers.create')" :categories="categories" />
                </div>
            </div>

            <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
                <!-- Sidebar -->
                <FilterSidebar
                    v-if="showSidebar"
                    v-model:filters="filters"
                    :categories="categories"
                    :sort-by-options="sortByOptions"
                    :all-label="__('All Customers')"
                    @reset="resetFilters"
                />

                <!-- Table Content -->
                <div class="flex-grow min-w-0 overflow-hidden">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Customer") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Contact") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">
                                        <div class="flex items-center gap-x-1">
                                            {{ __("Account Balance") }}
                                            <Tooltip :text="__('Net balance including unpaid invoices, payments, and opening balance.')" position="bottom">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 cursor-help text-gray-400">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                                                </svg>
                                            </Tooltip>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Total Invoiced") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Last Transaction") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-end text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                                <tr v-for="customer in customers" :key="customer.id" @click="router.visit(route('customers.account', customer.id))" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-x-3">
                                            <div>
                                                <div class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors leading-snug">{{ customer.name }}</div>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500">#{{ customer.id }}</span>
                                                    <span v-for="cat in customer.categories" :key="cat.id" class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md leading-tight uppercase tracking-wider">
                                                        {{ cat.name }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-y-1">
                                            <a class="text-sm font-semibold text-emerald-600 hover:text-emerald-500 transition-colors leading-tight" :href="'tel:' + customer.phone_number" @click.stop>{{ customer.phone_number }}</a>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-[150px]" :title="customer.address">{{ customer.address }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div :class="['text-sm font-bold leading-tight', customer.account_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400']">
                                            {{ formatCurrency(customer.account_balance) }}
                                        </div>
                                        <div v-if="customer.credit_limit > 0" class="mt-1 text-[10px] font-medium text-gray-400 uppercase tracking-wider">
                                            {{ __("Limit") }}: {{ formatCurrency(customer.credit_limit) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                            {{ formatCurrency(customer.total_invoiced) }}
                                        </div>
                                        <div class="mt-1 text-[10px] font-medium text-gray-400 uppercase tracking-wider">
                                            {{ customer.invoices_count }} {{ __("Invoices") }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col gap-y-1">
                                            <span class="text-xs font-medium">{{ formatDate(customer.last_transaction_date) }}</span>
                                            <span class="text-[10px] text-gray-400 uppercase tracking-wider font-bold">{{ __("Last activity") }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                        <div class="flex items-center justify-end gap-x-2">
                                            <Link @click.stop :href="route('customers.account', customer.id)" class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all" :title="__('Statement')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </Link>
                                            <div v-if="can('customers.update')" @click.stop>
                                                <CustomerForm :customer="customer" :categories="categories" />
                                            </div>
                                            <div v-if="can('customers.delete')" @click.stop>
                                                <DeleteCustomer :customer="customer" />
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <EmptySearch :data="customers" />

                    <div class="mt-6 flex justify-center">
                        <Pagination :links="[]" v-if="false" />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
