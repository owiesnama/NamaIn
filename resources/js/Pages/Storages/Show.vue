<script setup>
    import { watch } from "vue";
    import { debounce } from "lodash";
    import { useQueryString } from "@/Composables/useQueryString";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import StorageForm from "@/Components/Storages/StorageForm.vue";
    import DeleteStorage from "@/Components/Storages/DeleteStorage.vue";
    import { router } from "@inertiajs/vue3";

    let search = useQueryString("search");
    let props = defineProps({
        storage: Object,
        products: Object,
    });
    watch(
        search,
        debounce(function (value) {
            router.get(
                route("storages.show", props.storage),
                { search: value },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Storage">
        <section>
            <div class="w-full lg:flex lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            {{ __("The Storage") }}: {{ storage.name }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                            >{{ storage.stockCount }} {{ __("Product") }}</span
                        >
                    </div>

                    <p class="mt-2 text-gray-500">
                        {{
                            __(
                                "Here you can find all products available in this storage with it's transactions"
                            )
                        }}
                    </p>
                </div>

                <div class="mt-4 flex items-center gap-x-3 lg:mt-0">
                    <StorageForm :storage="storage" />
                    <DeleteStorage :storage="storage" />
                </div>
            </div>

            <div class="mt-6 md:flex md:items-center md:justify-between">
                <div class="relative flex items-center">
                    <span class="absolute">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                            />
                        </svg>
                    </span>

                    <input
                        v-model="search"
                        type="text"
                        :placeholder="__('Search products...')"
                        class="block w-full py-2 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 dark:focus:border-emerald-300 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                    />
                </div>
            </div>

            <div class="flex flex-col mt-6">
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full align-middle">
                        <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700 shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">#</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Name") }}</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Quantity On Hand") }}</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Expire Date") }}</th>
                                        <th scope="col" class="px-6 py-4 text-xs font-semibold text-start text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __("Added Time") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                    <template v-if="products.data.length">
                                        <tr v-for="product in products.data" :key="product.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">#{{ product.id }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-semibold text-gray-800 dark:text-white">{{ product.name }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                                <span class="px-2.5 py-1 text-sm font-medium rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                                                    {{ product.pivot.quantity }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                                <span v-if="product.expired_at > 0" class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-800/20 dark:text-emerald-500">
                                                    {{ product.expire_date }} ({{ __("Not Expired") }})
                                                </span>
                                                <span v-else class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800/20 dark:text-red-500">
                                                    {{ product.expire_date }} ({{ __("Expired") }})
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">{{ product.created_at }}</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <EmptySearch :data="products.data" />

                <div class="mt-6 flex justify-center">
                    <Pagination :links="products.links" />
                </div>
            </div>
        </section>
    </AppLayout>
</template>
