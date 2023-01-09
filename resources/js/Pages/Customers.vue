<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { onMounted, ref, watch } from "vue";
    import { Inertia } from "@inertiajs/inertia";
    import { debounce } from "lodash";
    import NewCustomer from "@/Components/Customers/NewCustomer.vue";
    defineProps({
        customers: Object,
    });

    let search = ref("");

    watch(
        search,
        debounce(function (value) {
            Inertia.get(
                "/customers",
                { search: value },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Customers">
        <div class="container mx-auto">
            <div class="flex justify-between mt-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search ..."
                    class="mb-4 rounded p-2"
                />
                <div
                    class="relative w-full px-4 max-w-full flex-grow flex-1 text-right"
                >
                    <button
                        class="bg-indigo-500 text-white active:bg-indigo-600 text-xs font-bold uppercase px-4 py-3 rounded outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                        type="button"
                    >
                        New Customer
                    </button>
                </div>
            </div>
            <NewCustomer></NewCustomer>
            <div class="w-full mb-12 xl:mb-0 mx-auto">
                <div
                    class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-sm rounded-lg"
                >
                    <div class="rounded-t mb-0 px-4 py-3 border-0">
                        <div class="flex flex-wrap items-center">
                            <div
                                class="relative w-full px-4 max-w-full flex-grow flex-1"
                            >
                                <h3
                                    class="font-semibold text-base text-gray-700"
                                >
                                    Customers
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="block w-full overflow-x-auto">
                        <table
                            class="items-center bg-transparent w-full border-collapse"
                        >
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 bg-gray-100 text-gray-500 align-middle border border-solid border-gray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-6 bg-gray-100 text-gray-500 align-middle border border-solid border-gray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
                                    >
                                        Phone
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="customer in customers"
                                    :key="customer.id"
                                >
                                    <th
                                        class="px-6 align-middle text-xs whitespace-nowrap p-4 text-left text-gray-700"
                                        v-text="customer.name"
                                    ></th>
                                    <td
                                        class="px-6 align-middle text-xs whitespace-nowrap p-4"
                                        v-text="customer.phone_number"
                                    ></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
