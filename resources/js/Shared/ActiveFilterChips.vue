<script setup>
defineProps({
    chips: {
        type: Array,
        default: () => [],
    },
});

defineEmits(["remove", "clear"]);
</script>

<template>
    <div v-if="chips.length" class="flex flex-wrap items-center gap-2 mb-6">
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __("Active filters") }}:</span>
        <span
            v-for="chip in chips"
            :key="chip.key"
            class="inline-flex items-center gap-x-1.5 ps-3 pe-1 py-1 text-xs font-medium bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-full"
        >
            <span><span class="text-gray-400 dark:text-gray-500">{{ chip.label }}:</span> {{ chip.value }}</span>
            <button
                type="button"
                class="inline-flex items-center justify-center w-4 h-4 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors"
                @click="$emit('remove', chip.key)"
            >
                <span class="sr-only">{{ __("Remove filter") }}</span>
                <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </span>
        <button
            type="button"
            class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 focus:outline-none"
            @click="$emit('clear')"
        >
            {{ __("Clear all") }}
        </button>
    </div>
</template>
