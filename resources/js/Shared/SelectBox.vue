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
        class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
        <slot></slot>
    </select>
</template>
