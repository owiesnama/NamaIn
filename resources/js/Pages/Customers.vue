<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { onMounted, ref, watch } from "vue";
    import { Inertia } from "@inertiajs/inertia";
    import { debounce } from "lodash";
    import NewCustomer from "@/Components/Customers/NewCustomer.vue";
    import Pagination from "@/Shared/Pagination.vue";
    defineProps({
        customers: Object,
    });

    let search = ref("");
    let isCreatingCutsomer = ref(false);
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
                    class="mb-4 rounded-lg p-2 border border-gray-200 w-64"
                />
                <div
                    class="relative w-full px-4 max-w-full flex-grow flex-1 text-right"
                >
                    <button
                        class="text-xs font-bold uppercase px-4 py-3 rounded outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                        type="button"
                        :class="[
                            isCreatingCutsomer
                                ? 'border border-gray-200 text-gray-400'
                                : 'text-white bg-indigo-500 active:bg-indigo-600',
                        ]"
                        @click="isCreatingCutsomer = !isCreatingCutsomer"
                        v-text="isCreatingCutsomer ? 'Cancel' : 'New Customer'"
                    ></button>
                </div>
            </div>
            <NewCustomer v-if="isCreatingCutsomer"></NewCustomer>
            <div class="w-full mb-12 xl:mb-0 mx-auto">
                <div
                    class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-sm rounded-lg p-4"
                >
                    <div class="rounded-t mb-0 py-3 border-0">
                        <div class="flex flex-wrap items-center">
                            <div
                                class="relative w-full max-w-full flex-grow flex-1"
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
                            class="items-center bg-transparent w-full border-separate"
                        >
                            <thead >
                                <tr>
                                    <th
                                        class="px-6 rounded-tl-md text-gray-500 align-middle border border-solid border-gray-100 py-3 text-xs uppercase border-l-1 border-r-0 whitespace-nowrap font-semibold text-left"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-6 rounded-tr-md text-gray-500 align-middle border border-solid border-gray-100 py-3 text-xs uppercase border-l-0 border-r-1 whitespace-nowrap font-semibold text-left"
                                    >
                                        Phone
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="customers.data">
                                    <tr
                                        v-for="customer in customers.data"
                                        :key="customer.id"
                                    >
                                        <th
                                            class="px-6 text-xs border-gray-100 py-3 text-left border border-l-1 border-r-0"
                                            v-text="customer.name"
                                        ></th>
                                        <td
                                            class="px-6 text-xs border-gray-100 py-3 text-left border  border-l-0 border-r-1"
                                            v-text="customer.phone"
                                        ></td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr>
                                        <td
                                            colspan="2"
                                            class="text-center text-sm leading-7 p-4 text-gray-600"
                                        >
                                            No Customers available
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="customers.links"></Pagination>
            </div>
        </div>
    </AppLayout>
</template>
