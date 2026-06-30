<script setup>
import { router } from "@inertiajs/vue3";

const props = defineProps({
    salePoints: {
        type: Array,
        default: () => [],
    },
    selectedId: {
        type: [Number, String],
        default: null,
    },
});

const switchSalePoint = (event) => {
    const storageId = event.target.value;
    if (Number(storageId) === Number(props.selectedId)) return;

    router.get(route('pos.index'), { storage_id: storageId }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div v-if="salePoints.length > 1" class="flex items-center gap-x-2">
        <label for="sale-point-picker" class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 shrink-0">
            {{ __('Sale Point') }}
        </label>
        <div class="relative flex-1">
            <select
                id="sale-point-picker"
                :value="selectedId"
                class="w-full appearance-none ltr:pl-3 rtl:pr-3 ltr:pr-9 rtl:pl-9 py-2 text-sm font-medium text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none"
                @change="switchSalePoint"
            >
                <option v-for="salePoint in salePoints" :key="salePoint.id" :value="salePoint.id">
                    {{ salePoint.name }}
                </option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 ltr:right-3 rtl:left-3 flex items-center text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </span>
        </div>
    </div>
</template>
