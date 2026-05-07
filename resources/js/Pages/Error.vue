<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    status: Number,
    homeUrl: String,
});

const page = usePage();
const dir = computed(() => page.props.locale === 'ar' ? 'rtl' : 'ltr');

const config = computed(() => {
    const map = {
        403: {
            title: __('Access denied'),
            description: __('You do not have permission to access this page.'),
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>`,
        },
        404: {
            title: __('Page not found'),
            description: __('The page you are looking for does not exist or has been moved.'),
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>`,
        },
        419: {
            title: __('Session expired'),
            description: __('Your session has expired. Please refresh the page and try again.'),
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>`,
        },
        500: {
            title: __('Something went wrong'),
            description: __('An unexpected error occurred on the server. Please try again later.'),
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`,
        },
        503: {
            title: __('Service unavailable'),
            description: __('The service is temporarily unavailable. Please check back shortly.'),
            icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-6.837m5.686 5.686l4.655-5.653a2.548 2.548 0 00-3.586-3.586l-6.837 6.837" /></svg>`,
        },
    };

    return map[props.status] ?? map[500];
});

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.assign(props.homeUrl);
    }
};
</script>

<template>
    <div
        :dir="dir"
        class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 px-4"
    >
        <div class="w-full max-w-md text-center">
            <!-- Icon container -->
            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-2xl bg-emerald-600 shadow-sm">
                <span v-html="config.icon" />
            </div>

            <!-- Status code -->
            <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-2">
                {{ status }}
            </p>

            <!-- Title -->
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-3">
                {{ config.title }}
            </h1>

            <!-- Description -->
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                {{ config.description }}
            </p>

            <!-- Actions -->
            <div class="flex items-center justify-center gap-3">
                <a
                    :href="homeUrl"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    {{ __('Go home') }}
                </a>
                <button
                    type="button"
                    @click="goBack"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    {{ __('Go back') }}
                </button>
            </div>
        </div>
    </div>
</template>
