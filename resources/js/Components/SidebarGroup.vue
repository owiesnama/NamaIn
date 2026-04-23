<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    title: String,
    active: Boolean,
    defaultOpen: {
        type: Boolean,
        default: false,
    },
});

const storageKey = `sidebar-group:${encodeURIComponent(props.title ?? 'group')}`;
const isOpen = ref(props.defaultOpen);

onMounted(() => {
    const persistedValue = window.localStorage.getItem(storageKey);

    if (persistedValue === null) {
        return;
    }

    isOpen.value = persistedValue === '1';
});

watch(isOpen, (value) => {
    window.localStorage.setItem(storageKey, value ? '1' : '0');
});

const toggle = () => {
    isOpen.value = !isOpen.value;
};
</script>

<template>
    <section>
        <button
            :class="[
                'group flex w-full items-center justify-between rounded-lg px-3 py-2 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/40',
                active && !isOpen
                    ? 'bg-emerald-50 text-emerald-500 dark:bg-emerald-900/20 dark:text-emerald-400 shadow-sm ring-1 ring-inset ring-emerald-500/10'
                    : active && isOpen
                    ? 'text-emerald-500 dark:text-emerald-400'
                    : 'text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50',
            ]"
            type="button"
            @click="toggle"
        >
            <span class="inline-flex w-full items-center gap-x-3">
                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center text-inherit">
                    <slot name="icon" />
                </span>
                <span class="text-sm font-extralight">
                    {{ title }}
                </span>
            </span>

            <svg
                :class="['h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500 transition-transform duration-300', isOpen ? 'rotate-180' : '']"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2.5"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-show="isOpen"
                class="mt-1 ms-4 space-y-0.5 border-s border-gray-200 dark:border-gray-700 ps-2 mx-2 rtl:border-r ltr:border-l rtl:border-gray-200 rtl:dark:border-gray-700 mx-2"
            >
                <slot />
            </div>
        </Transition>
    </section>
</template>
