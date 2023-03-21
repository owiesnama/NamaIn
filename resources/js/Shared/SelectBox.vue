<script setup>
    import { onMounted, ref } from "vue";

    defineProps({
        modelValue: String,
    });

    defineEmits(["update:modelValue"]);

    const select = ref(null);

    onMounted(() => {
        if (select.value.hasAttribute("autofocus")) {
            select.value.focus();
        }
    });

    defineExpose({ focus: () => select.value.focus() });
</script>

<template>
    <select
        ref="select"
        class="border border-gray-200 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded p-2"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
        <slot></slot>
    </select>
</template>
