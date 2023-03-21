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
        class="inline-flex items-center px-4 py-3 bg-gray-100 border border-gray-200 rounded font-semibold text-sm text-gray-900 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-200 focus:outline-none focus:border-gray-200 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
    >
        <slot />
    </button>
</template>
