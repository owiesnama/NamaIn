<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
let timer = null;

const enterFrom = computed(() =>
    page.props.locale === 'ar' ? '-translate-x-4 opacity-0' : 'translate-x-4 opacity-0'
);

const dismiss = () => {
    show.value = false;
    clearTimeout(timer);
};

watch(
    () => page.props.flash?.error,
    (error) => {
        if (error) {
            show.value = true;
            clearTimeout(timer);
            timer = setTimeout(dismiss, 5000);
        }
    },
    { immediate: true }
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        :enter-from-class="enterFrom"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        :leave-to-class="enterFrom"
    >
        <div
            v-if="show && $page.props.flash?.error"
            aria-live="assertive"
            class="fixed top-4 end-4 z-50 w-full max-w-sm"
        >
            <div class="bg-white dark:bg-gray-900 border border-red-200 dark:border-red-800 border-s-4 border-s-red-500 rounded-xl shadow-sm p-4">
                <div class="flex items-start gap-x-3">
                    <!-- Icon -->
                    <div class="shrink-0 mt-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 text-red-500">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('An error occurred') }}</p>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $page.props.flash.error }}</p>
                    </div>

                    <!-- Close -->
                    <button
                        type="button"
                        @click="dismiss"
                        class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
