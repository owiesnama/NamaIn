<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { watch } from "vue";
    import { router } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import NewCustomer from "@/Components/Customers/NewCustomer.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import { useQueryString } from "@/Composables/useQueryString";

    defineProps({
        customers: Object,
        customers_count: Number,
    });

    let search = useQueryString("search");

    watch(
        search,
        debounce(function (value) {
            router.get(
                "/customers",
                { search: value },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Customers">
        <section>
            <div class="w-full sm:flex sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">Customers</h2>

                        <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">{{ customers_count }} vendors</span>
                    </div>

                    <div class="relative flex items-center mt-4">
                        <span class="absolute">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mx-3 text-gray-400 dark:text-gray-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>

                        <input 
                            v-model="search"
                            type="text"
                            placeholder="Search here ..." 
                            class="block w-full py-1.5 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 dark:focus:border-emerald-300 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                        >
                    </div>
                </div>
                
                <NewCustomer></NewCustomer>
            </div>

            <div class="flex flex-col mt-6">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                        <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th scope="col" class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            #
                                        </th>

                                        <th scope="col" class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            Name
                                        </th>

                                        <th scope="col" class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            Phone
                                        </th>

                                        <th scope="col" class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            Address
                                        </th>

                                        <th scope="col" class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                            Added Time
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900">
                                    <template v-if="customers.data">
                                        <tr
                                            v-for="customer in customers.data"
                                            :key="customer.id"
                                        >
                                            <th
                                                class="px-8 py-3 text-sm text-left text-gray-800 whitespace-nowrap"
                                                v-text="customer.id"
                                            ></th>

                                            <th
                                                class="px-8 py-3 text-sm text-left text-gray-800 whitespace-nowrap"
                                                v-text="customer.name"
                                            ></th>

                                            <td class="px-8 py-3 text-sm text-left whitespace-nowrap">
                                                <a class="font-semibold text-emerald-500 hover:underline" 
                                                    :href="'tel:' + customer.phone_number" v-text="customer.phone_number"
                                                ></a>
                                            </td>

                                            <th
                                                class="px-8 py-3 text-sm text-left text-gray-700 whitespace-nowrap"
                                                v-text="customer.address"
                                            ></th>

                                            <td
                                                class="px-8 py-3 text-sm text-left text-gray-700 whitespace-nowrap"
                                                v-text="customer.created_at"
                                            ></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <EmptySearch :data="customers.data"></EmptySearch>
            </div>

            <div class="flex justify-center">
                <Pagination :links="customers.links"></Pagination>
            </div>
        </section>
    </AppLayout>
</template>
