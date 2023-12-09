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
        class="px-6 py-2.5 text-sm font-semibold tracking-wide text-white capitalize transition-colors duration-300 transform rounded-lg bg-emerald-500 hover:bg-emerald-400 focus:outline-none focus:ring focus:ring-emerald-300 focus:ring-opacity-50"
    >
        <slot />
    </button>
</template>
