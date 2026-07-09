<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    as: String,
});
</script>

<template>
    <div @click="$emit('click')">
        <button v-if="as == 'button'" type="button" class="flex w-full items-center min-h-[44px] px-4 py-2.5 text-sm leading-5 text-left text-gray-700 dark:text-gray-300 transition hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 rtl:text-right">
            <slot />
        </button>

        <a v-else-if="as =='a'" :href="href" class="flex items-center min-h-[44px] px-4 py-2.5 text-sm leading-5 text-gray-700 dark:text-gray-300 transition hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700">
            <slot />
        </a>

        <Link v-else :href="href" class="flex items-center min-h-[44px] px-4 py-2.5 text-sm leading-5 text-gray-700 dark:text-gray-300 transition hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700">
            <slot />
        </Link>
    </div>
</template>
