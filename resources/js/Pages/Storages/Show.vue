<script setup>
    import { watch } from "vue";
    import { debounce } from "lodash";
    import { useQueryString } from "@/Composables/useQueryString";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
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
                            Storage: {{ storage.name }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                            >{{ storage.stockCount }} Products</span
                        >
                    </div>

                    <p class="mt-2 text-gray-500">
                        Here you can find all products available in this Storage
                    </p>

                    <!-- <div class="relative flex items-center mt-4">
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
                            placeholder="Search here ..."
                            class="block w-full py-2 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 dark:focus:border-emerald-300 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                        />
                    </div> -->
                </div>
            </div>

            <div class="flex flex-col mt-8">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div
                        class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8"
                    >
                        <div
                            class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700"
                        >
                            <table
                                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            #
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            Name
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            Quantity
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            Expire Date
                                        </th>

                                        <th
                                            scope="col"
                                            class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                        >
                                            Added Time
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
                                >
                                    <template v-if="products">
                                        <tr
                                            v-for="product in products"
                                            :key="product.id"
                                        >
                                            <th
                                                class="px-8 py-3 text-sm text-left text-gray-800 whitespace-nowrap"
                                                v-text="product.id"
                                            ></th>

                                            <th
                                                class="px-8 py-3 text-sm text-left text-gray-800 whitespace-nowrap"
                                                v-text="product.name"
                                            ></th>

                                            <td
                                                class="px-8 py-3 text-sm text-left whitespace-nowrap"
                                            >
                                                <a
                                                    class="font-semibold text-emerald-500"
                                                    v-text="
                                                        product.pivot.quantity
                                                    "
                                                ></a>
                                            </td>

                                            <th
                                                class="px-8 py-3 text-sm text-left text-gray-700 whitespace-nowrap"
                                            >
                                                <span
                                                    class="text-emerald-500"
                                                    v-show="
                                                        product.expired_at > 0
                                                    "
                                                >
                                                    ({{ product.expire_date }})
                                                    Not Expire
                                                </span>

                                                <span
                                                    class="text-red-500"
                                                    v-show="
                                                        product.expired_at < 0
                                                    "
                                                >
                                                    ({{ product.expire_date }})
                                                    Expired
                                                </span>
                                                &nbsp;
                                            </th>

                                            <td
                                                class="px-8 py-3 text-sm text-left text-gray-700 whitespace-nowrap"
                                                v-text="product.created_at"
                                            ></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <EmptySearch :data="products"></EmptySearch>
            </div>
        </section>
    </AppLayout>
</template>
