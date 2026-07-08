<script setup>
import TrashFilter from "@/Shared/TrashFilter.vue";
import CustomSelect from "@/Components/CustomSelect.vue";

defineProps({
    show: {
        type: Boolean,
        default: false
    },
    filters: Object,
    categories: Array,
    sortByOptions: Array,
    allLabel: {
        type: String,
        default: "All"
    }
});

const emit = defineEmits(['reset', 'update:filters', 'close']);

const resetFilters = () => {
    emit('reset');
};
</script>

<template>
    <div class="contents">
        <!-- Drawer backdrop -->
        <Transition
            enter-active-class="transition-opacity ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm" @click="emit('close')" />
        </Transition>

        <!-- Slide-over drawer, anchored to the edge opposite the nav (all breakpoints) -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-x-full rtl:-translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full rtl:-translate-x-full opacity-0"
        >
        <aside v-if="show" class="fixed inset-y-0 end-0 z-50 w-80 max-w-[85vw] overflow-y-auto bg-white dark:bg-gray-900 shadow-xl">
        <div class="space-y-4 p-4">
            <!-- Unified Filter Sidebar -->
            <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl space-y-6 transition-all">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Filters") }}</h3>
                    <div class="flex items-center gap-x-2">
                        <button type="button" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium" @click="resetFilters">{{ __("Reset") }}</button>
                        <button type="button" class="inline-flex items-center justify-center w-9 h-9 -me-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500" @click="emit('close')">
                            <span class="sr-only">{{ __("Close") }}</span>
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Search -->
                <div class="space-y-2">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Search") }}</label>
                    <div class="relative flex items-center overflow-hidden">
                        <span class="absolute ltr:left-0 rtl:right-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mx-3 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </span>
                        <input :value="filters.search" type="text" :placeholder="__('Search here') + '...'" class="block w-full py-2 ltr:pl-10 ltr:pr-4 rtl:pr-10 rtl:pl-4 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40" @input="$emit('update:filters', { ...filters, search: $event.target.value })" />
                    </div>
                </div>

                <!-- Trash Filter -->
                <div class="space-y-2 overflow-hidden">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Status") }}</label>
                    <div class="flex bg-gray-50 border border-gray-200 divide-x rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700 rtl:flex-row-reverse overflow-hidden h-11">
                        <TrashFilter :model-value="filters.status" class="w-full" @update:model-value="status => $emit('update:filters', { ...filters, status })" />
                    </div>
                </div>

                <!-- Custom Filters Slot -->
                <slot name="extra-filters"></slot>

                <!-- Sorting -->
                <div v-if="sortByOptions && sortByOptions.length" class="space-y-2">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Sort By") }}</label>
                    <div class="space-y-2">
                        <CustomSelect
                            :model-value="filters.sort_by"
                            :options="sortByOptions"
                            label="label"
                            track-by="value"
                            :placeholder="__('Sort By')"
                            class="w-full"
                            @update:model-value="sortBy => $emit('update:filters', { ...filters, sort_by: sortBy })"
                        />
                        <CustomSelect
                            :model-value="filters.sort_order"
                            :options="[{ value: 'asc', label: __('Ascending') }, { value: 'desc', label: __('Descending') }]"
                            label="label"
                            track-by="value"
                            :placeholder="__('Sort Order')"
                            class="w-full"
                            @update:model-value="sortOrder => $emit('update:filters', { ...filters, sort_order: sortOrder })"
                        />
                    </div>
                </div>

                <!-- Categories -->
                <div v-if="categories && categories.length" class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Categories") }}</label>
                    <div class="flex flex-col gap-y-1 max-h-64 overflow-y-auto custom-scrollbar pr-1">
                        <button
                            type="button"
                            :class="[
                                'text-start px-3 py-2 text-xs transition-all duration-200 rounded-t-lg',
                                !filters.category ? 'bg-emerald-50 text-emerald-700 font-bold border-s-4 border-emerald-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                            @click="$emit('update:filters', { ...filters, category: null })"
                        >
                            {{ allLabel }}
                        </button>
                        <button
                            v-for="(category, index) in categories"
                            :key="category.id"
                            type="button"
                            :class="[
                                'text-start px-3 py-2 text-xs transition-all duration-200',
                                index === categories.length - 1 ? 'rounded-b-lg' : '',
                                filters.category == category.id ? 'bg-emerald-50 text-emerald-700 font-bold border-s-4 border-emerald-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                            @click="$emit('update:filters', { ...filters, category: String(category.id) })"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </aside>
        </Transition>
    </div>
</template>
