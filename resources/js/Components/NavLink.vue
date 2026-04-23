<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'group flex w-full items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 shadow-sm ring-1 ring-inset ring-emerald-500/10 transition-all duration-300 focus:outline-none'
        : 'group flex w-full items-center gap-x-3 rounded-lg px-3 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-emerald-600 dark:hover:text-emerald-400 hover:translate-x-1 rtl:hover:-translate-x-1 transition-all duration-300 focus:outline-none';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
