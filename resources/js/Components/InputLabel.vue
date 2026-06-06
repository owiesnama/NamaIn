<script setup>
defineProps({
    value: String,
});
</script>

<template>
    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300 rtl:text-right">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
