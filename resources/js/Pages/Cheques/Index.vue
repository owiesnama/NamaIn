<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { router, Link, usePage } from "@inertiajs/vue3";
    import TextInput from "@/Components/TextInput.vue";
    import Cheque from "@/Shared/Cheque.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { watch, reactive, ref, onMounted, computed } from "vue";
    import { debounce } from "lodash";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import Dropdown from "@/Components/Dropdown.vue";

    const props = defineProps({
        initialCheques: Object,
        status: Object
    });
    let landMark = ref(null);
    let cheques = ref(props.initialCheques.data);

    let filters = reactive({
        search: useQueryString("search"),
        type: useQueryString("type"),
        status: useQueryString("status"),
        due: useQueryString("due")
    });
    const getStatusLabel = computed(() => {
        let [status] = Object
            .entries(props.status)
            .filter(([, value]) => filters.status === value)[0];

        return __(status);
    });
    const initialUrl = usePage().url;

    const loadMore = () => {
        if (!props.initialCheques.next_page_url) return;
        router.get(
            props.initialCheques.next_page_url,
            { ...filters },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess() {
                    window.history.replaceState({}, "", initialUrl);
                    cheques.value = [
                        ...cheques.value,
                        ...props.initialCheques.data
                    ];
                }
            }
        );
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            loadMore();
        });
    });

    onMounted(() => {
        observer.observe(landMark.value);
    });
    watch(
        filters,
        debounce(function(watchedFitlers) {
            router.get(
                route("cheques.index"),
                { ...watchedFitlers },
                {
                    preserveState: true,
                    onSuccess() {
                        window.history.replaceState({}, "", initialUrl);
                        cheques.value = [...props.initialCheques.data];
                    }
                }
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
                            {{ __("Cheques") }}
                        </h2>

                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400"
                        >{{ initialCheques.total }} {{ __("Cheque") }}</span
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
                                class="block w-full py-2 pr-5 text-gray-700 bg-white border border-gray-200 rounded-lg md:w-80 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 dark:focus:border-emerald-300 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                                :placeholder="__('Search here') + '...'"
                            />
                        </div>
                        <TextInput
                            v-model="filters.due"
                            type="date"
                            class="text-gray-700 rtl:text-right"
                            :placeholder="__('Due before') + '..'"
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
                            {{ __("All") }}
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
                            {{ __("Credit") }}
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
                            {{ __("Debit") }}
                        </button>
                    </div>

                    <Dropdown
                        align="left"
                        width="48"
                    >
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex rtl items-center justify-center w-full px-3 py-2 mt-4 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-gray-200 rounded-lg sm:w-auto sm:mt-0 gap-x-2 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-6 h-6"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"
                                    />
                                </svg>

                                {{ filters.status ? getStatusLabel : __("Status") }}
                            </button>
                        </template>

                        <template #content>
                            <button
                                v-for="(key, value) in status"
                                :key="key"
                                @click="filters.status = key"
                                class="block rtl:text-right w-full px-4 py-2 text-sm leading-5 text-left text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:bg-gray-100"
                                v-text="__(value)"
                            ></button>
                        </template>
                    </Dropdown>

                    <Link
                        as="button"
                        :href="route('cheques.create')"
                        class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                        @click="show = true"
                    >
                        + {{ __("Add New Cheque") }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-8 md:grid-cols-2" v-auto-animate>
                <Cheque
                    v-for="cheque in cheques"
                    :key="cheque.id + (new Date).valueOf()"
                    :cheque="cheque"
                    :cheque-status="status"
                ></Cheque>
            </div>
            <div ref="landMark"></div>

            <EmptySearch :data="cheques"></EmptySearch>
        </section>
    </AppLayout>
</template>
