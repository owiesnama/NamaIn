<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Localized feature label to name in the hint.
    feature: { type: String, default: null },
    // Optional route/URL to an upgrade or plans page.
    href: { type: String, default: null },
});
</script>

<template>
    <div
        class="inline-flex items-center gap-x-1.5 px-2.5 py-1 text-[11px] font-medium rounded-lg border border-amber-200 bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400"
    >
        <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
        <span>{{ feature ? __('entitlements.upgrade_body', { feature }) : __('entitlements.locked') }}</span>
        <Link v-if="href" :href="href" class="font-semibold underline hover:text-amber-800 dark:hover:text-amber-300">
            {{ __('entitlements.upgrade_cta') }}
        </Link>
    </div>
</template>
