<script setup>
import TrashFilter from "@/Shared/TrashFilter.vue";

defineProps({
    filters: Object,
    categories: Array,
    sortByOptions: Array,
    allLabel: {
        type: String,
        default: "All"
    }
});

const emit = defineEmits(['reset', 'update:filters']);

const resetFilters = () => {
    emit('reset');
};
</script>

<template>
    <aside class="w-full lg:w-72 shrink-0 transition-all duration-300">
        <div class="sticky top-4 space-y-4">
            <!-- Unified Filter Sidebar -->
            <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Filters") }}</h3>
                    <button type="button" @click="resetFilters" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">{{ __("Reset") }}</button>
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
                        <input :value="filters.search" @input="$emit('update:filters', { ...filters, search: $event.target.value })" type="text" :placeholder="__('Search here') + '...'" class="block w-full py-2 ltr:pl-10 ltr:pr-4 rtl:pr-10 rtl:pl-4 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40" />
                    </div>
                </div>

                <!-- Trash Filter -->
                <div class="space-y-2 overflow-hidden">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Status") }}</label>
                    <div class="flex bg-gray-50 border border-gray-200 divide-x rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700 rtl:flex-row-reverse overflow-hidden h-9">
                        <TrashFilter :model-value="filters.status" @update:model-value="status => $emit('update:filters', { ...filters, status })" class="w-full" />
                    </div>
                </div>

                <!-- Custom Filters Slot -->
                <slot name="extra-filters"></slot>

                <!-- Sorting -->
                <div class="space-y-2" v-if="sortByOptions && sortByOptions.length">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Sort By") }}</label>
                    <div class="space-y-2">
                        <select :value="filters.sort_by" @change="$emit('update:filters', { ...filters, sort_by: $event.target.value })" class="block w-full py-2 px-3 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40">
                            <option v-for="option in sortByOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <select :value="filters.sort_order" @change="$emit('update:filters', { ...filters, sort_order: $event.target.value })" class="block w-full py-2 px-3 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg dark:bg-gray-900 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:outline-none focus:ring focus:ring-opacity-40">
                            <option value="asc">{{ __("Ascending") }}</option>
                            <option value="desc">{{ __("Descending") }}</option>
                        </select>
                    </div>
                </div>

                <!-- Categories -->
                <div class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-800" v-if="categories && categories.length">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Categories") }}</label>
                    <div class="flex flex-col gap-y-1 max-h-64 overflow-y-auto custom-scrollbar pr-1">
                        <button
                            type="button"
                            @click="$emit('update:filters', { ...filters, category: null })"
                            :class="[
                                'text-start px-3 py-2 text-xs transition-all duration-200 rounded-t-lg',
                                !filters.category ? 'bg-emerald-50 text-emerald-700 font-bold border-s-4 border-emerald-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                        >
                            {{ allLabel }}
                        </button>
                        <button
                            type="button"
                            v-for="(category, index) in categories"
                            :key="category.id"
                            @click="$emit('update:filters', { ...filters, category: String(category.id) })"
                            :class="[
                                'text-start px-3 py-2 text-xs transition-all duration-200',
                                index === categories.length - 1 ? 'rounded-b-lg' : '',
                                filters.category == category.id ? 'bg-emerald-50 text-emerald-700 font-bold border-s-4 border-emerald-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
