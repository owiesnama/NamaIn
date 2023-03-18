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
        class="cursor-pointer border py-3 px-6 rounded-lg bg-gray-50 font-semibold hover:bg-gray-100"
        :class="{ 'outline-none border-blue-300 shadow-outline-blue': focused }"
        ><slot> Import </slot></label
    >
    <input
        @focus="focused = true"
        @blur="focused = false"
        @input="$emit('input', $event.target.files)"
        :id="id"
        type="file"
        class="sr-only"
    />
</template>
