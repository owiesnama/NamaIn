<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch, ref } from "vue";
    import { router, useForm } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import SupplierForm from "@/Components/Suppliers/SupplierForm.vue";
    import DeleteSupplier from "@/Components/Suppliers/DeleteSupplier.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";

    defineProps({
        suppliers: Object,
        categories: Array
    });

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
        sort_by: useQueryString("sort_by").value || "created_at",
        sort_order: useQueryString("sort_order").value || "desc"
    });

    const resetFilters = () => {
        filters.value = {
            search: null,
            status: null,
            category: null,
            sort_by: "created_at",
            sort_order: "desc"
        };
    };

    const sortByOptions = [
        { label: __("Added Time"), value: "created_at" },
        { label: __("Name"), value: "name" },
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
    <AppLayout title="Suppliers">
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
                        >{{ suppliers.total }} {{ __("Supplier") }}</span
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
                    <div class="overflow-x-auto">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">#</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Name") }}</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Phone") }}</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Address") }}</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Added Time") }}</th>
                                        <th scope="col" class="px-6 py-4 relative"><span class="sr-only">{{ __("actions") }}</span></th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                    <template v-if="suppliers.data.length">
                                        <tr
                                            v-for="supplier in suppliers.data"
                                            :key="supplier.id"
                                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer"
                                            @click="router.visit(route('suppliers.account', supplier.id))"
                                        >
                                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">#{{ supplier.id }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <Link
                                                    :href="route('suppliers.account', supplier.id)"
                                                    class="text-sm font-medium text-gray-900 dark:text-white hover:text-emerald-600 transition-colors inline-block"
                                                    @click.stop
                                                >
                                                    {{ supplier.name }}
                                                </Link>
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    <span v-for="cat in supplier.categories" :key="cat.id" class="px-1.5 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-700 rounded border border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800">
                                                        {{ cat.name }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a class="text-sm font-semibold text-emerald-600 hover:text-emerald-500 transition-colors" :href="'tel:' + supplier.phone_number" @click.stop>{{ supplier.phone_number }}</a>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap dark:text-gray-300">{{ supplier.address }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">{{ supplier.created_at }}</td>
                                            <td class="px-6 py-4 text-sm font-medium text-end whitespace-nowrap" @click.stop>
                                                <div class="flex items-center justify-end gap-x-3">
                                                    <SupplierForm :supplier="supplier" :categories="categories" />
                                                    <DeleteSupplier :supplier="supplier" />
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <EmptySearch :data="suppliers.data" />

                    <div class="mt-6 flex justify-center">
                        <Pagination :links="suppliers.links" />
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
