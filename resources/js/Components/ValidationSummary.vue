<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
    title: {
        type: String,
        default: null,
    },
});

const dismissed = ref(false);

const messages = computed(() => {
    const result = [];
    for (const value of Object.values(props.errors)) {
        if (Array.isArray(value)) {
            result.push(...value);
        } else if (value) {
            result.push(value);
        }
    }
    return result;
});

const visible = computed(() => messages.value.length > 0 && !dismissed.value);

watch(() => props.errors, () => {
    dismissed.value = false;
}, { deep: true });
</script>

<template>
    <div
        v-if="visible"
        role="alert"
        class="mb-4 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4"
    >
        <div class="flex items-start gap-x-3">
            <!-- Icon -->
            <div class="shrink-0 mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-red-500 dark:text-red-400">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-red-700 dark:text-red-400">
                    {{ title ?? __('Please correct the following errors.') }}
                </p>
                <ul class="mt-2 space-y-1 list-disc list-inside">
                    <li
                        v-for="(message, index) in messages"
                        :key="index"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ message }}
                    </li>
                </ul>
            </div>

            <!-- Dismiss -->
            <button
                type="button"
                @click="dismissed = true"
                class="shrink-0 text-red-400 dark:text-red-500 hover:text-red-600 dark:hover:text-red-300 transition-colors duration-200"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>
    </div>
</template>
