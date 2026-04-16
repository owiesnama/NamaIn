<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch, ref } from "vue";
    import { router, useForm, Link } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import SupplierForm from "@/Components/Suppliers/SupplierForm.vue";
    import DeleteSupplier from "@/Components/Suppliers/DeleteSupplier.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    import Tooltip from "@/Components/Tooltip.vue";

    defineProps({
        suppliers: Array,
        categories: Array
    });

    const formatCurrency = (amount, currency = 'SDG') => {
        const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');
        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: validCurrency,
        }).format(amount);
    };

    const lang = window.lang || 'en';

    const formatDate = (dateString) => {
        if (!dateString) return __("No transactions");
        return new Date(dateString).toLocaleDateString(lang === 'ar' ? 'ar-SA' : 'en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    const showSidebar = ref(true);

    let importForm = useForm({
        file: null
    });

    let submitImport = (files) => {
        importForm.file = files[0];
        importForm.post(route("suppliers.import"));
    };

    const filters = ref({
        search: useQueryString("search").value,
        status: useQueryString("status").value,
        category: useQueryString("category").value,
        sort_by: useQueryString("sort_by").value || "name",
        sort_order: useQueryString("sort_order").value || "asc"
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
    ];

    watch(
        filters,
        debounce(function() {
            router.get(
                route("suppliers.index"),
                filters.value,
                { preserveState: true }
            );
        }, 300),
        { deep: true }
    );
</script>

<template>
    <AppLayout :title="__('Suppliers')">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            {{ __("Suppliers") }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                        >{{ suppliers.length }} {{ __("Supplier") }}</span
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
                            :href="route('suppliers.import-sample')"
                            class="inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                            :title="__('Download Sample')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>

                        <a
                            :href="route('suppliers.export')"
                            class="inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                            :title="__('Export')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </a>
                    </div>

                    <SupplierForm :categories="categories"></SupplierForm>
                </div>
            </div>

            <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
                <!-- Sidebar -->
                <FilterSidebar
                    v-if="showSidebar"
                    v-model:filters="filters"
                    :categories="categories"
                    :sort-by-options="sortByOptions"
                    :all-label="__('All Suppliers')"
                    @reset="resetFilters"
                />

                <!-- Table Content -->
                <div class="flex-grow min-w-0 overflow-hidden">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Supplier") }}</th>
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
                                <tr v-for="supplier in suppliers" :key="supplier.id" @click="router.visit(route('suppliers.account', supplier.id))" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-x-3">
                                            <div>
                                                <div class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors leading-snug">{{ supplier.name }}</div>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500">#{{ supplier.id }}</span>
                                                    <span v-for="cat in supplier.categories" :key="cat.id" class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md leading-tight uppercase tracking-wider">
                                                        {{ cat.name }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-y-1">
                                            <a class="text-sm font-semibold text-emerald-600 hover:text-emerald-500 transition-colors leading-tight" :href="'tel:' + supplier.phone_number" @click.stop>{{ supplier.phone_number }}</a>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-[150px]" :title="supplier.address">{{ supplier.address }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div :class="['text-sm font-bold leading-tight', supplier.account_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400']">
                                            {{ formatCurrency(supplier.account_balance) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                            {{ formatCurrency(supplier.total_invoiced) }}
                                        </div>
                                        <div class="mt-1 text-[10px] font-medium text-gray-400 uppercase tracking-wider">
                                            {{ supplier.invoices_count }} {{ __("Invoices") }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col gap-y-1">
                                            <span class="text-xs font-medium">{{ formatDate(supplier.last_transaction_date) }}</span>
                                            <span class="text-[10px] text-gray-400 uppercase tracking-wider font-bold">{{ __("Last activity") }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                        <div class="flex items-center justify-end gap-x-2">
                                            <Link @click.stop :href="route('suppliers.account', supplier.id)" class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all" :title="__('Statement')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.799 7.553 7.384 4.5 12 4.5c4.616 0 8.201 3.053 9.964 7.178.07.162.07.346 0 .508C20.201 16.447 16.616 19.5 12 19.5c-4.616 0-8.201-3.053-9.964-7.178z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </Link>
                                            <div @click.stop>
                                                <SupplierForm :supplier="supplier" :categories="categories" />
                                            </div>
                                            <div @click.stop>
                                                <DeleteSupplier :supplier="supplier" />
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <EmptySearch :data="suppliers" />

                    <div class="mt-6 flex justify-center">
                        <Pagination :links="[]" v-if="false" />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
