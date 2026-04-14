<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch, ref } from "vue";
    import { router, useForm, Link } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import ProductForm from "@/Components/Products/ProductForm.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    import DeleteProduct from "@/Components/Products/DeleteProduct.vue";
    import TextInput from "@/Components/TextInput.vue";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import Tooltip from "@/Components/Tooltip.vue";

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
        { label: __("Avg Cost"), value: "average_cost" },
        { label: __("Expire Date"), value: "expire_date" },
    ];

    const getStockStatus = (product) => {
        const qtyOnHand = product.stock.reduce((sum, s) => sum + s.pivot.quantity, 0);
        const alertQty = product.alert_quantity || 10;

        if (product.pending_sales > qtyOnHand) {
            return {
                label: __('Overcommitted'),
                color: 'text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" /></svg>'
            };
        }
        if (qtyOnHand === 0) {
            return {
                label: __('Out of Stock'),
                color: 'text-red-700 bg-red-50 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>'
            };
        }
        if (qtyOnHand <= alertQty) {
            return {
                label: __('Low Stock'),
                color: 'text-orange-700 bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:text-orange-400 dark:border-orange-800',
                icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625l6.28-10.875zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>'
            };
        }
        return {
            label: __('In Stock'),
            color: 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>'
        };
    };

    const formatCurrency = (amount, currency = 'USD') => {
        const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD');
        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: validCurrency,
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
    <AppLayout :title="__('Products')">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-bold text-gray-800 dark:text-white"
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

                        <a
                            :href="route('products.export')"
                            class="inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700"
                            :title="__('Export')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </a>
                    </div>

                    <ProductForm :categories="categories"></ProductForm>
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
                <div class="flex-1 min-w-0 overflow-hidden">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Product") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Status") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em] leading-tight">
                                        <div class="flex items-center gap-x-1">
                                            {{ __("Stock & Available") }}
                                            <Tooltip :text="__('Stock is physical quantity on hand, Available is stock minus pending sales')" position="bottom">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 cursor-help text-gray-400">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                                                </svg>
                                            </Tooltip>
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Avg Cost") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-start text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]">{{ __("Total Value") }}</th>
                                    <th scope="col" class="px-6 py-4 text-[10px] font-bold text-end text-gray-400 dark:text-gray-500 uppercase tracking-[0.1em]"></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                                <tr v-for="product in products.data" :key="product.id" @click="router.visit(route('products.show', product.id))" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200 cursor-pointer">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-x-3">
                                            <div>
                                                <div class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors leading-snug">{{ product.name }}</div>
                                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500">#{{ product.id }}</span>
                                                    <span v-for="cat in product.categories" :key="cat.id" class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md leading-tight uppercase tracking-wider">
                                                        {{ cat.name }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-y-1.5">
                                            <div :class="['inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border w-fit leading-tight', getStockStatus(product).color]">
                                                <span v-html="getStockStatus(product).icon"></span>
                                                {{ getStockStatus(product).label }}
                                            </div>
                                            <div v-if="product.pending_sales > 0" class="inline-flex items-center gap-x-1 px-2 py-0.5 text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100 rounded-md w-fit dark:bg-amber-900/10 dark:border-amber-900/30 leading-tight">
                                                <Tooltip :text="__('Quantity committed in unexecuted sales invoices.')" position="top">
                                                    <div class="flex items-center gap-x-1 cursor-help">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 shrink-0">
                                                            <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                                            <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                        </svg>
                                                        {{ product.pending_sales }} {{ __("Pending Sales") }}
                                                    </div>
                                                </Tooltip>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-y-1">
                                            <div class="flex items-end gap-x-2">
                                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ product.stock.reduce((sum, s) => sum + s.pivot.quantity, 0) }}</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase font-bold tracking-wider mb-0.5 leading-tight">{{ __("Qty on Hand") }}</span>
                                            </div>
                                            <div class="flex items-center gap-x-2">
                                                <div class="w-16 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden shrink-0">
                                                    <div
                                                        class="h-full transition-all duration-500"
                                                        :class="[
                                                            product.available_qty <= 0 ? 'bg-red-500' :
                                                            product.available_qty <= (product.alert_quantity || 10) ? 'bg-orange-500' : 'bg-emerald-500'
                                                        ]"
                                                        :style="{ width: Math.min(100, Math.max(0, (product.available_qty / (product.stock.reduce((sum, s) => sum + s.pivot.quantity, 0) || 1)) * 100)) + '%' }"
                                                    ></div>
                                                </div>
                                                <span :class="['text-xs font-bold', product.available_qty <= 0 ? 'text-red-600' : 'text-emerald-600']">
                                                    {{ product.available_qty }}
                                                </span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase font-bold tracking-wider leading-tight">{{ __("Available") }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold">
                                        <div class="text-emerald-600 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-400/5 px-2 py-1 rounded-md w-fit leading-tight">
                                            {{ formatCurrency(product.average_cost, product.currency) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                            {{ formatCurrency(product.stock.reduce((sum, s) => sum + s.pivot.quantity, 0) * product.average_cost, product.currency) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-end text-sm font-medium">
                                        <div class="flex items-center justify-end gap-x-2">
                                            <Link @click.stop :href="route('products.show', product.id)" class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all" :title="__('Details')">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </Link>
                                            <div @click.stop>
                                                <ProductForm :product="product" :categories="categories" />
                                            </div>
                                            <div @click.stop>
                                                <DeleteProduct :product="product" />
                                            </div>
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
