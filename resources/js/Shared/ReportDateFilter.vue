<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ preset: 'this_month', from_date: '', to_date: '' }),
    },
    presets: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue']);

const useCustomDates = ref(!!props.modelValue.from_date && !!props.modelValue.to_date && !props.modelValue.preset);

const localFilters = ref({ ...props.modelValue });

watch(localFilters, (val) => {
    emit('update:modelValue', { ...val });
}, { deep: true });

function selectPreset(preset) {
    useCustomDates.value = false;
    localFilters.value = { preset, from_date: '', to_date: '' };
}

function toggleCustom() {
    useCustomDates.value = true;
    localFilters.value = { preset: '', from_date: localFilters.value.from_date, to_date: localFilters.value.to_date };
}
</script>

<template>
    <div class="space-y-3">
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            {{ __('Date Range') }}
        </p>

        <!-- Preset buttons -->
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="(label, key) in presets"
                :key="key"
                type="button"
                @click="selectPreset(key)"
                class="px-2.5 py-1 text-xs font-medium rounded-lg border transition-colors duration-200"
                :class="localFilters.preset === key && !useCustomDates
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                    : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
            >
                {{ label }}
            </button>
            <button
                type="button"
                @click="toggleCustom"
                class="px-2.5 py-1 text-xs font-medium rounded-lg border transition-colors duration-200"
                :class="useCustomDates
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'
                    : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'"
            >
                {{ __('Custom') }}
            </button>
        </div>

        <!-- Custom date inputs -->
        <div v-if="useCustomDates" class="grid grid-cols-2 gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('From') }}</label>
                <input
                    type="date"
                    v-model="localFilters.from_date"
                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('To') }}</label>
                <input
                    type="date"
                    v-model="localFilters.to_date"
                    class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                />
            </div>
        </div>
    </div>
</template>
