<script setup>
import { onMounted, ref, watch } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';

const props = defineProps({
    modelValue: [String, Number, Date],
    config: {
        type: Object,
        default: () => ({})
    },
    placeholder: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);
let fp = null;

onMounted(() => {
    fp = flatpickr(input.value, {
        ...props.config,
        defaultDate: props.modelValue,
        onChange: (selectedDates, dateStr) => {
            emit('update:modelValue', dateStr);
        },
    });
});

watch(() => props.modelValue, (newValue) => {
    if (fp && newValue !== fp.currentDateStr) {
        fp.setDate(newValue, false);
    }
});

watch(() => props.config, (newConfig) => {
    if (fp) {
        fp.set(newConfig);
    }
}, { deep: true });

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div class="relative">
        <input
            ref="input"
            type="text"
            class="px-3 py-2 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 w-full"
            :placeholder="placeholder"
            :value="modelValue"
        >
    </div>
</template>
