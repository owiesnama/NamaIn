<script setup>
    import { useQueryString } from "@/Composables/useQueryString";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Pagination from "@/Shared/Pagination.vue";
    import { ref, watch } from "vue";
    import { debounce } from "lodash";
    import { router } from "@inertiajs/vue3";

    defineProps({
        storages: Object,
    });
    let search = useQueryString("search");
    let isCreatingStorage = ref(false);
    watch(
        search,
        debounce(function (value) {
            router.get(
                route('storages.index'),
                { search: value },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Storages">
        <div class="container mx-auto">
            <div class="flex justify-between mt-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search ..."
                    class="w-64 p-2 mb-4 border border-gray-200 rounded-lg"
                />
                <div
                    class="relative flex-1 flex-grow w-full max-w-full px-4 text-right"
                >
                    <button
                        class="px-4 py-3 mb-1 mr-1 text-xs font-bold uppercase transition-all duration-150 ease-linear rounded outline-none focus:outline-none"
                        type="button"
                        :class="[
                            isCreatingStorage
                                ? 'border border-gray-200 text-gray-400'
                                : 'text-white bg-indigo-500 active:bg-indigo-600',
                        ]"
                        @click="isCreatingStorage = !isCreatingStorage"
                        v-text="isCreatingStorage ? 'Cancel' : 'New Storage'"
                    ></button>
                </div>
            </div>
            <NewStorage v-if="isCreatingStorage"></NewStorage>
            <div class="w-full mx-auto mb-12 xl:mb-0">
                <div
                    class="relative flex flex-col w-full min-w-0 p-4 mb-6 break-words bg-white rounded-lg shadow-sm"
                >
                    <div class="py-3 mb-0 border-0 rounded-t">
                        <div class="flex flex-wrap items-center">
                            <div
                                class="relative flex-1 flex-grow w-full max-w-full"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-700"
                                >
                                    Storages
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="block w-full overflow-x-auto">
                        <table
                            class="items-center w-full bg-transparent border-separate"
                        >
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 text-xs font-semibold text-left text-gray-500 uppercase align-middle border border-r-0 border-gray-100 border-solid rounded-tl-md border-l-1 whitespace-nowrap"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-6 py-3 text-xs font-semibold text-left text-gray-500 uppercase align-middle border border-l-0 border-gray-100 border-solid rounded-tr-md border-r-1 whitespace-nowrap"
                                    >
                                        Address
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-if="storages.data">
                                    <tr
                                        v-for="storage in storages.data"
                                        :key="storage.id"
                                    >
                                        <th
                                            class="px-6 py-3 text-xs text-left border border-r-0 border-gray-100 border-l-1"
                                            v-text="storage.name"
                                        ></th>
                                        <td
                                            class="px-6 py-3 text-xs text-left border border-l-0 border-gray-100 border-r-1"
                                            v-text="storage.address"
                                        ></td>
                                    </tr>
                                </template>
                                <template v-else>
                                    <tr>
                                        <td
                                            colspan="2"
                                            class="p-4 text-sm leading-7 text-center text-gray-600"
                                        >
                                            No Storage added
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="storages.links"></Pagination>
            </div>
        </div>
    </AppLayout>
</template>
