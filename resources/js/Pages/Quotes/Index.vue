<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import EmptySearch from "@/Shared/EmptySearch.vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { useQueryString } from "@/Composables/useQueryString";
import CustomSelect from "@/Components/CustomSelect.vue";
import debounce from "lodash/debounce";

defineProps({
    quotes: Object,
});

const showSidebar = ref(true);

const filters = ref({
    search: useQueryString("search").value,
    status: useQueryString("status").value,
    date_from: useQueryString("date_from").value,
    date_to: useQueryString("date_to").value,
});

const resetFilters = () => {
    filters.value = { search: null, status: null, date_from: null, date_to: null };
};

const statusOptions = [
    { label: __("Active"), value: "active" },
    { label: __("Converted"), value: "converted" },
    { label: __("Expired"), value: "expired" },
];

watch(
    filters,
    debounce(() => {
        router.get(route("quotes.index"), filters.value, { preserveState: true });
    }, 300),
    { deep: true }
);

const deleteForm = useForm({});

const confirmDelete = (quote) => {
    if (!confirm(__("Are you sure you want to delete this quote?"))) return;
    deleteForm.delete(route("quotes.destroy", quote.id));
};

const statusClass = (status) => {
    const map = {
        active: "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400",
        converted: "bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400",
        expired: "bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400",
    };
    return map[status] ?? map.active;
};

const statusLabel = (status) => {
    const map = { active: __("Active"), converted: __("Converted"), expired: __("Expired") };
    return map[status] ?? status;
};
</script>

<template>
    <AppLayout :title="__('Quotes')">
        <!-- Page header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __("Quotes") }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ quotes.total }} {{ __("Quote") }}
                    </span>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                <button
                    @click="showSidebar = !showSidebar"
                    :class="[
                        'inline-flex items-center justify-center p-2.5 text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700 transition-colors',
                        showSidebar ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/20' : ''
                    ]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    </svg>
                </button>

                <Link
                    :href="route('quotes.create')"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    + {{ __("Create Quote") }}
                </Link>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-6 border-b border-gray-200 dark:border-gray-700">
            <nav class="flex gap-x-6">
                <Link
                    :href="route('sales.index')"
                    class="pb-3 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 border-b-2 border-transparent hover:border-gray-300 transition-colors duration-200"
                >
                    {{ __("Invoices") }}
                </Link>
                <span class="pb-3 text-sm font-medium text-emerald-600 dark:text-emerald-400 border-b-2 border-emerald-600 dark:border-emerald-400">
                    {{ __("Quotes") }}
                </span>
            </nav>
        </div>

        <div class="flex flex-col mt-6 lg:flex-row lg:gap-x-6">
            <!-- Filter sidebar -->
            <aside v-if="showSidebar" class="w-full lg:w-72 shrink-0">
                <div class="sticky top-4 space-y-4">
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl space-y-6">
                        <!-- Search -->
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __("Search") }}</p>
                            <input
                                v-model="filters.search"
                                type="text"
                                :placeholder="__('Quote number, notes...')"
                                class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white bg-white dark:bg-gray-900 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            />
                        </div>

                        <!-- Status -->
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __("Status") }}</p>
                            <CustomSelect
                                v-model="filters.status"
                                :options="statusOptions"
                                label="label"
                                track-by="value"
                                :placeholder="__('All statuses')"
                                @update:model-value="(v) => filters.status = v"
                            />
                        </div>

                        <!-- Date range -->
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __("Date Range") }}</p>
                            <div class="space-y-2">
                                <input
                                    v-model="filters.date_from"
                                    type="date"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white bg-white dark:bg-gray-900 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                />
                                <input
                                    v-model="filters.date_to"
                                    type="date"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white bg-white dark:bg-gray-900 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                />
                            </div>
                        </div>

                        <button
                            @click="resetFilters"
                            class="w-full text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                        >
                            {{ __("Reset Filters") }}
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Table -->
            <div class="flex-1 min-w-0">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                                <tr>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Quote number") }}</th>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Customer") }}</th>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Items") }}</th>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Total") }}</th>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Expires") }}</th>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Status") }}</th>
                                    <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                                <tr
                                    v-for="quote in quotes.data"
                                    :key="quote.id"
                                    class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ quote.number }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ quote.customer?.name ?? "—" }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ quote.items?.length ?? 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ quote.total }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span v-if="quote.expires_at" :class="quote.is_expired ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-600 dark:text-gray-400'">
                                            {{ quote.expires_at }}
                                        </span>
                                        <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div :class="['inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border', statusClass(quote.status?.value ?? quote.status)]">
                                            {{ statusLabel(quote.status?.value ?? quote.status) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-x-3">
                                            <Link
                                                v-if="(quote.status?.value ?? quote.status) === 'active'"
                                                :href="route('quotes.edit', quote.id)"
                                                class="text-xs text-gray-500 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                            >{{ __("Edit") }}</Link>

                                            <Link
                                                v-if="(quote.status?.value ?? quote.status) === 'active'"
                                                :href="route('quotes.convert', quote.id)"
                                                class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium transition-colors"
                                            >{{ __("Convert to Invoice") }}</Link>

                                            <a
                                                :href="route('quotes.print', quote.id)"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                            >{{ __("Print") }}</a>

                                            <button
                                                @click="confirmDelete(quote)"
                                                class="text-xs text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-400 transition-colors"
                                            >{{ __("Delete") }}</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <EmptySearch :data="quotes.data" />

                    <div class="flex justify-center p-6">
                        <Pagination :links="quotes.links" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
