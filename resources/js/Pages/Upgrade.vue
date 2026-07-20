<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    feature: String,
    planName: { type: String, default: null },
});

const page = usePage();
const dir = computed(() => (page.props.locale === 'ar' ? 'rtl' : 'ltr'));

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/dashboard');
    }
};
</script>

<template>
    <div
        :dir="dir"
        class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4"
    >
        <div class="w-full max-w-md text-center">
            <!-- Icon -->
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-2xl bg-emerald-600 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2.25m-7.5-2.25h15a1.5 1.5 0 001.5-1.5V9a1.5 1.5 0 00-1.5-1.5h-15A1.5 1.5 0 003 9v4.5a1.5 1.5 0 001.5 1.5zm12-9V6a3.75 3.75 0 10-7.5 0v.75" />
                </svg>
            </div>

            <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-2">
                {{ __('entitlements.upgrade_title') }}
            </p>

            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-3">
                {{ feature }}
            </h1>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                {{ __('entitlements.upgrade_body', { feature }) }}
                <span v-if="planName" class="block mt-1 text-gray-400 dark:text-gray-500">
                    {{ planName }}
                </span>
            </p>

            <div class="flex items-center justify-center gap-x-3">
                <button
                    type="button"
                    @click="goBack"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    {{ __('entitlements.go_back') }}
                </button>
            </div>
        </div>
    </div>
</template>
