<script setup>
    import { computed } from "vue";

    const props = defineProps({
        // null = unlimited, a number = the quota.
        modelValue: { type: [Number, String, null], default: null },
    });

    const emit = defineEmits(["update:modelValue"]);

    const isUnlimited = computed(() => props.modelValue === null || props.modelValue === "");

    const display = computed(() => (isUnlimited.value ? "" : String(props.modelValue)));

    const onInput = (event) => {
        const digits = event.target.value.replace(/[^0-9]/g, "");
        emit("update:modelValue", digits === "" ? null : Number(digits));
    };

    const makeUnlimited = () => emit("update:modelValue", null);
</script>

<template>
    <div class="flex items-center gap-x-2">
        <input
            :value="display"
            inputmode="numeric"
            :placeholder="__('Unlimited')"
            class="w-24 px-3 py-1.5 text-sm text-center text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600 tabular-nums"
            @input="onInput"
        />
        <span
            v-if="isUnlimited"
            class="inline-flex items-center gap-x-1 text-xs text-emerald-600 dark:text-emerald-400"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.178 8c5.096 0 5.096 8 0 8-5.095 0-7.133-8-12.739-8-4.585 0-4.585 8 0 8 5.606 0 7.644-8 12.74-8z" />
            </svg>
            {{ __('Unlimited') }}
        </span>
        <button
            v-else
            type="button"
            class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
            @click="makeUnlimited"
        >
            {{ __('Make unlimited') }}
        </button>
    </div>
</template>
