<template>
    <div v-if="links.length > 3">
        <div class="flex flex-wrap gap-1 mt-8">
            <template
                v-for="(link, key) in links"
                :key="key"
            >
                <div
                    v-if="link.url === null"
                    class="inline-flex items-center justify-center px-4 py-3 min-w-[44px] cursor-not-allowed text-sm leading-4 text-center text-gray-400 border rounded-md"
                >
                    <span v-if="link.label.includes('Previous')">&laquo;</span>
                    <span v-else-if="link.label.includes('Next')">&raquo;</span>
                    <span v-html="link.label"></span>
                </div>

                <Link
                    v-else
                    class="inline-flex items-center justify-center px-4 py-3 min-w-[44px] text-sm leading-4 text-center hover:bg-gray-100 dark:hover:bg-gray-800 border rounded-md focus:border-emerald-500 focus:text-emerald-500"
                    :class="{ 'bg-emerald-600 text-white': link.active, 'bg-white dark:bg-gray-900': !link.active }"
                    :href="link.url"
                    :preserve-state="true"
                >
                    <span v-if="link.label.includes('Previous')">&laquo;</span>
                    <span v-else-if="link.label.includes('Next')">&raquo;</span>
                    <span v-html="link.label"></span>
                </Link>
            </template>
        </div>
    </div>
</template>

<script setup>
    import { Link } from "@inertiajs/vue3";

    defineProps({
        links: Array,
    });
</script>
