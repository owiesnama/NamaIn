<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: [String, Number],
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="px-3 py-2 text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 dark:focus:border-emerald-600 focus:ring focus:ring-emerald-200 dark:focus:ring-emerald-800 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
