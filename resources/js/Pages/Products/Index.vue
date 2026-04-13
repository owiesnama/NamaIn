<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch, ref } from "vue";
    import { router, useForm } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import ProdcutForm from "@/Components/Products/ProdcutForm.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    import DeleteProduct from "@/Components/Products/DeleteProduct.vue";
    import TextInput from "@/Components/TextInput.vue";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";

    defineProps({
        products: Object,
        categories: Array
    });

    const showSidebar = ref(true);

    let form = useForm({
        file: null
    });

    const filters = ref({
        search: useQueryString("search").value,
        status: useQueryString("status").value,
        category: useQueryString("category").value,
        min_cost: useQueryString("min_cost").value,
        max_cost: useQueryString("max_cost").value,
        expire_from: useQueryString("expire_from").value,
        expire_to: useQueryString("expire_to").value,
        sort_by: useQueryString("sort_by").value || "created_at",
        sort_order: useQueryString("sort_order").value || "desc"
    });

    const resetFilters = () => {
        filters.value = {
            search: null,
            status: null,
            category: null,
            min_cost: null,
            max_cost: null,
            expire_from: null,
            expire_to: null,
            sort_by: "created_at",
            sort_order: "desc"
        };
    };

    const sortByOptions = [
        { label: __("Added Time"), value: "created_at" },
        { label: __("Name"), value: "name" },
        { label: __("Cost"), value: "cost" },
        { label: __("Expire Date"), value: "expire_date" },
    ];

    const formatCurrency = (amount, currency = 'USD') => {
        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: currency || preferences('currency') || 'USD',
        }).format(amount);
    };

    let submit = (files) => {
        form.file = files[0];
        form.post(route("products.import"));
    };

    watch(
        filters,
        debounce(function() {
            router.get(route("products.index"), filters.value, { preserveState: true });
        }, 300),
        { deep: true }
    );
</script>

<template>
    <AppLayout title="Products">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            {{ __("Products") }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                        >{{ products.total }} {{ __("Product") }}</span
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
                            @input="submit"
                        >{{ __("Import") }}
                        </FileUploadButton>

                        <a
                            :href="route('products.import-sample')"
                            class="inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                            :title="__('Download Sample')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>
                    </div>

                    <ProdcutForm :categories="categories"></ProdcutForm>
                </div>
            </div>

            <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
                <!-- Sidebar -->
                <FilterSidebar
                    v-if="showSidebar"
                    :filters="filters"
                    :categories="categories"
                    :sort-by-options="sortByOptions"
                    :all-label="__('All Products')"
                    @update:filters="newFilters => filters = newFilters"
                    @reset="resetFilters"
                >
                    <template #extra-filters>
                        <!-- Cost Range -->
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Cost Range") }}</label>
                            <div class="grid grid-cols-2 gap-2">
                                <TextInput v-model="filters.min_cost" type="number" :placeholder="__('Min')" class="w-full text-xs" />
                                <TextInput v-model="filters.max_cost" type="number" :placeholder="__('Max')" class="w-full text-xs" />
                            </div>
                        </div>

                        <!-- Expiry Range -->
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Expiration Date") }}</label>
                            <div class="space-y-2">
                                <TextInput v-model="filters.expire_from" type="date" class="w-full text-xs" />
                                <TextInput v-model="filters.expire_to" type="date" class="w-full text-xs" />
                            </div>
                        </div>
                    </template>
                </FilterSidebar>

                <!-- Products List -->
                <div class="flex-1 min-w-0">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                                    <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Name") }}</th>
                                    <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Cost") }}</th>
                                    <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Expire Date") }}</th>
                                    <th scope="col" class="px-6 py-4 text-xs font-bold text-start text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Added Time") }}</th>
                                    <th scope="col" class="px-6 py-4 text-xs font-bold text-end text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __("Actions") }}</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ product.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ product.name }}</div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span v-for="cat in product.categories" :key="cat.id" class="px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded">
                                                {{ cat.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ formatCurrency(product.cost, product.currency) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span :class="[
                                                'text-sm font-medium',
                                                product.expired_at < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'
                                            ]">
                                                {{ product.expire_date }}
                                            </span>
                                            <span class="text-[10px] font-medium" :class="product.expired_at < 0 ? 'text-red-500' : 'text-emerald-500'">
                                                {{ product.expired_at < 0 ? __("Expired") : __("Active") }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ product.created_at }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                        <div class="flex items-center justify-end gap-x-3">
                                            <ProdcutForm :product="product" :categories="categories" />
                                            <DeleteProduct :product="product" />
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <EmptySearch :data="products.data"></EmptySearch>
            </div>

            <div class="flex justify-center">
                <Pagination :links="products.links"></Pagination>
            </div>
        </section>
    </AppLayout>
</template>
