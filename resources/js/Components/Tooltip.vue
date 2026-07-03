<script setup>
import { ref, onUnmounted } from 'vue';

defineProps({
    text: {
        type: String,
        required: true,
    },
    position: {
        type: String,
        default: 'top', // top, bottom, left, right
    },
});

const show = ref(false);
const root = ref(null);

// Tablets have no hover, so support tap-to-toggle with tap-away to close,
// while keeping the hover behaviour intact for pointer devices.
const onDocumentClick = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
};

const open = () => {
    if (show.value) return;
    show.value = true;
    document.addEventListener('click', onDocumentClick);
};

const close = () => {
    if (!show.value) return;
    show.value = false;
    document.removeEventListener('click', onDocumentClick);
};

const toggle = () => {
    show.value ? close() : open();
};

onUnmounted(() => document.removeEventListener('click', onDocumentClick));

const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'inset-inline-end-full top-1/2 -translate-y-1/2 margin-inline-end-2',
    right: 'inset-inline-start-full top-1/2 -translate-y-1/2 margin-inline-start-2',
};

const arrowClasses = {
    top: 'top-full left-1/2 -translate-x-1/2 border-t-gray-800 dark:border-t-gray-700',
    bottom: 'bottom-full left-1/2 -translate-x-1/2 border-b-gray-800 dark:border-b-gray-700',
    left: 'inset-inline-start-full top-1/2 -translate-y-1/2 border-l-gray-800 dark:border-l-gray-700 rtl:border-l-transparent rtl:border-r-gray-800 rtl:dark:border-r-gray-700',
    right: 'inset-inline-end-full top-1/2 -translate-y-1/2 border-r-gray-800 dark:border-r-gray-700 rtl:border-r-transparent rtl:border-l-gray-800 rtl:dark:border-l-gray-700',
};
</script>

<template>
    <div ref="root" class="relative inline-block" @mouseenter="open" @mouseleave="close" @click="toggle">
        <slot />

        <div v-if="show"
             class="absolute z-[100] px-2 py-1 text-xs font-medium text-white bg-gray-800 dark:bg-gray-700 rounded shadow-sm whitespace-normal min-w-[200px] max-w-[300px] text-center pointer-events-none transition-opacity duration-200"
             :class="positionClasses[position]">
            {{ text }}
            <div class="absolute border-4 border-transparent" :class="arrowClasses[position]"></div>
        </div>
    </div>
</template>
