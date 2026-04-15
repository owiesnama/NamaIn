<script setup>
    import { useQueryString } from "@/Composables/useQueryString";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import StorageForm from "@/Components/Storages/StorageForm.vue";
    import DeleteStorage from "@/Components/Storages/DeleteStorage.vue";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import { watch, ref } from "vue";
    import { debounce } from "lodash";
    import { router, Link } from "@inertiajs/vue3";
    import EmptySearch from "@/Shared/EmptySearch.vue";

    defineProps({
        storages: Array
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
        if (!dateString) return __("No movements");
        return new Date(dateString).toLocaleDateString(lang === 'ar' ? 'ar-SA' : 'en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    const showSidebar = ref(true);

    const filters = ref({
        search: useQueryString("search").value,
        status: useQueryString("status").value,
        sort_by: useQueryString("sort_by").value || "name",
        sort_order: useQueryString("sort_order").value || "asc"
    });

    const resetFilters = () => {
        filters.value = {
            search: null,
            status: null,
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
                route("storages.index"),
                filters.value,
                { preserveState: true }
            );
        }, 300),
        { deep: true }
    );
</script>

<template>
    <AppLayout :title="__('Storages')">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            {{ __("Storages") }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                        >{{ storages.length }} {{ __("Storage") }}</span
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

                    <StorageForm></StorageForm>
                </div>
            </div>

            <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
                <!-- Sidebar -->
                <FilterSidebar
                    v-if="showSidebar"
                    v-model:filters="filters"
                    :sort-by-options="sortByOptions"
                    :all-label="__('All Storages')"
                    @reset="resetFilters"
                />

                <!-- Table Content -->
                <div class="flex-grow min-w-0 overflow-hidden">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Storage") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Inventory Summary") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Valuation") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Last Stock Movement") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-end text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                                <tr v-for="storage in storages" :key="storage.id" @click="router.visit(route('storages.show', storage.id))" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-x-3">
                                            <div>
                                                <div class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors leading-snug">{{ storage.name }}</div>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500">#{{ storage.id }}</span>
                                                    <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate max-w-[200px]" :title="storage.address">{{ storage.address }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-y-1">
                                            <div class="flex items-end gap-x-2">
                                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ storage.total_quantity }}</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase font-bold tracking-wider mb-0.5 leading-tight">{{ __("Units") }}</span>
                                            </div>
                                            <div class="flex items-center gap-x-2">
                                                <span class="text-xs font-bold text-emerald-600">{{ storage.stock_count }}</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase font-bold tracking-wider leading-tight">{{ __("Products") }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                            {{ formatCurrency(storage.total_stock_value) }}
                                        </div>
                                        <div class="mt-1 text-[10px] font-medium text-gray-400 uppercase tracking-wider">
                                            {{ __("Stock Value") }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col gap-y-1">
                                            <span class="text-xs font-medium">{{ formatDate(storage.last_movement_date) }}</span>
                                            <span class="text-[10px] text-gray-400 uppercase tracking-wider font-bold">{{ __("Last activity") }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                        <div class="flex items-center justify-end gap-x-2">
                                            <Link @click.stop :href="route('storages.show', storage.id)" class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all" :title="__('Details')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </Link>
                                            <div @click.stop>
                                                <StorageForm :storage="storage" />
                                            </div>
                                            <div @click.stop>
                                                <DeleteStorage :storage="storage" />
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <EmptySearch :data="storages" />

                    <div class="mt-6 flex justify-center">
                        <Pagination :links="[]" v-if="false" />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
