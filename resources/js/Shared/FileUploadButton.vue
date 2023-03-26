<script setup>
    import { ref } from "vue";

    defineProps({
        id: {
            type: String,
            default: "fileImport",
        },
    });
    defineEmits(["input"]);
    let focused = ref(false);
</script>
<template>
    <label
        :for="id"
        class="inline-flex items-center px-4 py-2.5 text-sm font-semibold tracking-widest text-gray-700 capitalize transition border border-gray-200 rounded-lg cursor-pointer gap-x-3 hover:bg-gray-50 active:bg-gray-200 focus:outline-none focus:border-gray-200 focus:ring focus:ring-gray-300 disabled:opacity-25"
        :class="{ 'outline-none border-blue-300 shadow-outline-blue': focused }"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
        </svg>

        <slot> Import </slot>
    </label
    >
    <input
        :id="id"
        type="file"
        class="sr-only"
        @focus="focused = true"
        @blur="focused = false"
        @input="$emit('input', $event.target.files)"
    />
</template>
