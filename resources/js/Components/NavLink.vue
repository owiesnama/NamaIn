<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'flex items-center px-3 py-2 text-emerald-700 transition-all duration-300 transform rounded-lg dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 font-semibold shadow-sm ring-1 ring-inset ring-emerald-500/10'
        : 'flex items-center px-3 py-2 text-gray-600 transition-all duration-300 transform rounded-lg dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-emerald-600 dark:hover:text-emerald-400 hover:translate-x-1 rtl:hover:-translate-x-1';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
