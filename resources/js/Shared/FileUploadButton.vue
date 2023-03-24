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
        class="cursor-pointer inline-flex items-center px-4 py-3 bg-gray-100 border border-gray-200  rounded font-semibold text-sm text-gray-900 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-200 focus:outline-none focus:border-gray-200 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
        :class="{ 'outline-none border-blue-300 shadow-outline-blue': focused }"
        ><slot> Import </slot></label
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
