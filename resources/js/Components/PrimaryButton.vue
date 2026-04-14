<script setup>
    defineProps({
        type: {
            type: String,
            default: "submit",
        },
    });
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
    >
        <slot />
    </button>
</template>
