<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { router, Link } from "@inertiajs/vue3";
    import Cheque from "@/Shared/Cheque.vue";
    import { useQueryString } from "@/Composables/useQueryString";
    import { watch, ref, onMounted, onUnmounted } from "vue";
    import { debounce } from "lodash";
    import EmptySearch from "@/Shared/EmptySearch.vue";
    import FilterSidebar from "@/Shared/FilterSidebar.vue";
    import VueMultiselect from "vue-multiselect";
    import "vue-multiselect/dist/vue-multiselect.css";

    const props = defineProps({
        initialCheques: Object,
        status: Object,
        summary: Object
    });

    const showSidebar = ref(true);

    const formatCurrency = (amount, currency = null) => {
        const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency : 'SDG';

        return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: validCurrency,
        }).format(amount || 0);
    };

    let landMark = ref(null);
    let cheques = ref(props.initialCheques.data);

    let filters = ref({
        search: useQueryString("search").value,
        type: useQueryString("type").value,
        status: useQueryString("status").value,
        due: useQueryString("due").value,
        sort_by: useQueryString("sort_by").value || "due",
        sort_order: useQueryString("sort_order").value || "asc"
    });

    const sortByOptions = [
        { label: __("Due Date"), value: "due" },
        { label: __("Amount"), value: "amount" },
        { label: __("Reference Number"), value: "reference_number" },
        { label: __("Date Registered"), value: "created_at" },
    ];

    const resetFilters = () => {
        filters.value = {
            search: null,
            type: null,
            status: null,
            due: null,
            sort_by: "due",
            sort_order: "asc"
        };
    };

    const loadMore = () => {
        if (!props.initialCheques.next_page_url) return;
        router.get(
            props.initialCheques.next_page_url,
            { ...filters.value },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess() {
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
        if (landMark.value) {
            observer.observe(landMark.value);
        }
    });

    watch(landMark, (el, oldEl) => {
        if (oldEl) observer.unobserve(oldEl);
        if (el) observer.observe(el);
    });

    onUnmounted(() => {
        observer.disconnect();
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
                        cheques.value = [...props.initialCheques.data];
                    }
                }
            );
        }, 300),
        { deep: true }
    );
</script>

<template>
    <AppLayout :title="__('Cheques')">
        <div class="w-full lg:flex lg:items-center lg:justify-between">
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

                <Link
                    :href="route('cheques.create')"
                    class="w-full px-5 py-2.5 block text-center text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                >
                    + {{ __("Register Cheque") }}
                </Link>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-5 mt-6 sm:grid-cols-3">
            <!-- Total Receivable -->
            <div
                @click="filters.type = 1"
                class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 flex items-center hover:bg-gray-50 dark:hover:bg-gray-800 transition-all group cursor-pointer"
            >
                <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-600 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1 px-4">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate">{{ __("Receivable") }}</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1 tracking-tight">{{ formatCurrency(summary.total_receivable) }}</p>
                </div>
            </div>

            <!-- Total Payable -->
            <div
                @click="filters.type = 0"
                class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 flex items-center hover:bg-gray-50 dark:hover:bg-gray-800 transition-all group cursor-pointer"
            >
                <div class="p-2 rounded-lg bg-red-500/10 text-red-600 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1 px-4">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate">{{ __("Payable") }}</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1 tracking-tight">{{ formatCurrency(summary.total_payable) }}</p>
                </div>
            </div>

            <!-- Overdue Count -->
            <div
                @click="filters.due = new Date().toISOString().split('T')[0]"
                class="relative px-4 py-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 sm:p-6 flex items-center hover:bg-gray-50 dark:hover:bg-gray-800 transition-all group cursor-pointer"
            >
                <div class="p-2 rounded-lg bg-amber-500/10 text-amber-600 ltr:mr-4 rtl:ml-4 group-hover:scale-110 transition-transform flex-shrink-0">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1 px-4">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest truncate">{{ __("Overdue") }}</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-1 tracking-tight">{{ summary.overdue_count }} {{ __("Cheques") }}</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col mt-8 lg:flex-row lg:gap-x-6">
            <FilterSidebar
                v-if="showSidebar"
                v-model:filters="filters"
                :sort-by-options="sortByOptions"
                :all-label="__('All Cheques')"
                @reset="resetFilters"
            >
                <template #extra-filters>
                    <!-- Cheque Type -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Type") }}</label>
                        <div class="flex bg-gray-50 border border-gray-200 divide-x rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700 rtl:flex-row-reverse overflow-hidden h-9">
                            <button
                                @click="filters.type = null"
                                :class="[
                                    'px-2 flex-1 shrink-0 py-2 text-xs font-semibold transition-colors duration-200 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                                    filters.type === null || filters.type === '' ? 'bg-gray-100 dark:bg-gray-700' : 'text-gray-600'
                                ]"
                            >
                                {{ __("All") }}
                            </button>
                            <button
                                @click="filters.type = 1"
                                :class="[
                                    'px-2 flex-1 shrink-0 py-2 text-xs font-semibold transition-colors duration-200 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                                    filters.type == 1 ? 'bg-gray-100 dark:bg-gray-700' : 'text-gray-600'
                                ]"
                            >
                                {{ __("Receivable") }}
                            </button>
                            <button
                                @click="filters.type = 0"
                                :class="[
                                    'px-2 flex-1 shrink-0 py-2 text-xs font-semibold transition-colors duration-200 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
                                    filters.type === 0 || filters.type === '0' ? 'bg-gray-100 dark:bg-gray-700' : 'text-gray-600'
                                ]"
                            >
                                {{ __("Payable") }}
                            </button>
                        </div>
                    </div>

                    <!-- Due Before -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Due Before") }}</label>
                        <DatePicker
                            v-model="filters.due"
                            class="block w-full text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40"
                        />
                    </div>

                    <!-- Status Filter -->
                    <div class="space-y-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Status") }}</label>
                        <VueMultiselect
                            v-model="filters.status"
                            :options="Object.values(status)"
                            :multiple="true"
                            :close-on-select="false"
                            :placeholder="__('Filter by status')"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
                            class="text-xs custom-multiselect"
                        >
                            <template #noResult>
                                <span class="text-xs text-gray-500">{{ __("No status found") }}</span>
                            </template>
                        </VueMultiselect>
                    </div>
                </template>
            </FilterSidebar>

            <div class="flex-1 min-w-0">
                <div v-if="cheques.length">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Cheque
                            v-for="cheque in cheques"
                            :key="cheque.id + '-' + cheque.status"
                            :cheque="cheque"
                            :cheque-status="status"
                        />
                    </div>
                    <div
                        ref="landMark"
                        class="w-full h-12"
                    ></div>
                </div>
                <EmptySearch
                    v-else
                    :data="cheques"
                />
            </div>
        </div>
    </AppLayout>
</template>
