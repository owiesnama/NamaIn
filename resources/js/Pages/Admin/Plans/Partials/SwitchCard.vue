<script setup>
    import ToggleField from "@/Components/ToggleField.vue";

    defineProps({
        modelValue: { type: Boolean, default: false },
        label: { type: String, default: "" },
        hint: { type: String, default: "" },
    });

    defineEmits(["update:modelValue"]);
</script>

<template>
    <div class="flex items-center justify-between gap-x-3 px-4 py-3 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ label }}</p>
            <p v-if="hint" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ hint }}</p>
        </div>
        <ToggleField
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </div>
</template>
