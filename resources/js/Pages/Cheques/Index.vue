<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { router } from "@inertiajs/vue3";
    import TextInput from "@/Components/TextInput.vue";
    import Cheque from "@/Shared/Cheque.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { watch, reactive } from "vue";
    import { debounce } from "lodash";
    import EmptySearch from "@/Shared/EmptySearch.vue";

    defineProps({
        cheques: Object,
        status: Object,
    });

    let filters = reactive({
        search: useQueryString("search"),
        type: useQueryString("type"),
        status: useQueryString("status"),
        due: useQueryString("due"),
    });

    watch(
        filters,
        debounce(function (watchedFitlers) {
            router.get(
                route("cheques.index"),
                { ...watchedFitlers },
                { preserveState: true }
            );
        }, 300)
    );
</script>

<template>
    <AppLayout title="Cheques">
        <section>
            <div class="w-full lg:flex lg:items-end lg:justify-between">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2
                            class="text-xl font-semibold text-gray-800 dark:text-white"
                        >
                            Cheques
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                            >{{ cheques.count }} Cheques</span
                        >
                    </div>

                    <div class="flex items-center mt-4 gap-x-4">
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
                                v-model="filters.search"
                                type="text"
                                placeholder="Search here ..."
                                class="block w-full py-2 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 dark:focus:border-emerald-300 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                            />
                        </div>
                        <TextInput
                            v-model="filters.due"
                            type="date"
                            class="text-gray-700"
                            placeholder="Due before.."
                        />
                    </div>
                </div>

                <div
                    class="mt-4 sm:flex sm:items-center sm:justify-between sm:gap-x-4 lg:mt-0"
                >
                    <div
                        class="flex overflow-hidden bg-white border divide-x rounded-lg md:w-auto sm:w-1/2 dark:bg-gray-900 rtl:flex-row-reverse dark:border-gray-700 dark:divide-gray-700"
                    >
                        <button
                            :class="
                                filters.type === '' || filters.type === null
                                    ? 'bg-gray-100'
                                    : 'hover:bg-gray-100'
                            "
                            class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm"
                            @click="filters.type = ''"
                        >
                            All
                        </button>

                        <button
                            :class="
                                filters.type == 1
                                    ? 'bg-gray-100'
                                    : 'hover:bg-gray-100'
                            "
                            class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm"
                            @click="filters.type = 1"
                        >
                            Credit
                        </button>

                        <button
                            :class="
                                filters.type === 0
                                    ? 'bg-gray-100'
                                    : 'hover:bg-gray-100'
                            "
                            class="px-5 w-1/3 md:w-auto shrink-0 py-2.5 text-xs font-semibold text-gray-600 transition-colors duration-200 sm:text-sm"
                            @click="filters.type = 0"
                        >
                            Debit
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="grid grid-cols-1 gap-6 mt-8 md:grid-cols-2 2xl:grid-cols-3"
            >
                <Cheque
                    v-for="cheque in cheques"
                    :key="cheque.id"
                    :cheque="cheque"
                    :cheque-status="status"
                ></Cheque>
            </div>

            <EmptySearch :data="cheques"></EmptySearch>
        </section>
    </AppLayout>
</template>
