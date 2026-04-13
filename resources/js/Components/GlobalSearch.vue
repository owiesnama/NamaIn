<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { debounce } from 'lodash';

defineProps({
    placeholder: {
        type: String,
        default: ''
    }
});

const isOpen = ref(false);
const search = ref('');
const results = ref([]);
const isLoading = ref(false);
const selectedIndex = ref(-1);

const toggleSearch = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        setTimeout(() => {
            document.getElementById('global-search-input')?.focus();
        }, 100);
    } else {
        search.value = '';
        results.value = [];
    }
};

const performSearch = debounce(async () => {
    if (!search.value) {
        results.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get(route('global-search'), {
            params: { search: search.value }
        });
        results.value = response.data;
        selectedIndex.value = results.value.length > 0 ? 0 : -1;
    } catch (error) {
        console.error('Search error:', error);
    } finally {
        isLoading.value = false;
    }
}, 300);

watch(search, () => {
    performSearch();
});

const handleKeydown = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        toggleSearch();
    }

    if (!isOpen.value) return;

    if (e.key === 'Escape') {
        isOpen.value = false;
    } else if (e.key === 'ArrowDown') {
        if (results.value.length === 0) return;
        e.preventDefault();
        selectedIndex.value = (selectedIndex.value + 1) % results.value.length;
    } else if (e.key === 'ArrowUp') {
        if (results.value.length === 0) return;
        e.preventDefault();
        selectedIndex.value = (selectedIndex.value - 1 + results.value.length) % results.value.length;
    } else if (e.key === 'Enter') {
        if (selectedIndex.value >= 0 && results.value[selectedIndex.value]) {
            router.visit(results.value[selectedIndex.value].url);
            isOpen.value = false;
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

const navigateTo = (url) => {
    router.visit(url);
    isOpen.value = false;
};
</script>

<template>
    <div class="relative flex items-center flex-1">
        <button
            @click="toggleSearch"
            class="flex items-center w-full px-4 py-2 text-sm text-gray-400 bg-gray-100 border border-transparent rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all duration-200 dark:bg-gray-800 dark:text-gray-500 dark:hover:bg-gray-700"
        >
            <svg class="w-4 h-4 ltr:mr-2 rtl:ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span class="flex-1 text-start">{{ __('Search') }}...</span>
            <kbd class="hidden sm:inline-block px-1.5 py-0.5 text-xs font-semibold text-gray-500 bg-white border border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400">
                <span class="text-xs">⌘</span> K
            </kbd>
        </button>

        <!-- Modal -->
        <div v-if="isOpen" class="fixed inset-0 z-[100] overflow-y-auto p-4 sm:p-6 md:p-20" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div @click="isOpen = false" class="fixed inset-0 bg-gray-500/75 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>

            <div class="mx-auto max-w-2xl transform divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 transition-all dark:bg-gray-900 dark:divide-gray-800">
                <div class="relative">
                    <svg class="pointer-events-none absolute ltr:left-4 rtl:right-4 top-3.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                    <input
                        id="global-search-input"
                        v-model="search"
                        type="text"
                        class="h-12 w-full border-0 bg-transparent ltr:pl-11 rtl:pr-11 ltr:pr-4 rtl:pl-4 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm dark:text-white"
                        :placeholder="__('Search') + '...'"
                    >
                </div>

                <!-- Results -->
                <ul v-if="results.length > 0" class="max-h-96 overflow-y-auto p-2 text-sm text-gray-700 dark:text-gray-300">
                    <li v-for="(result, index) in results" :key="result.type + result.id">
                        <button
                            @click="navigateTo(result.url)"
                            @mouseenter="selectedIndex = index"
                            :class="[
                                'flex w-full items-center rounded-md px-3 py-2',
                                index === selectedIndex ? 'bg-emerald-600 text-white' : ''
                            ]"
                        >
                            <div class="flex flex-col items-start">
                                <span class="font-medium">{{ result.name }}</span>
                                <span :class="['text-xs', index === selectedIndex ? 'text-emerald-100' : 'text-gray-500']">
                                    {{ __(result.type) }} • {{ result.subtext }}
                                </span>
                            </div>
                        </button>
                    </li>
                </ul>

                <!-- Empty state -->
                <div v-if="search && results.length === 0 && !isLoading" class="px-6 py-14 text-center sm:px-14">
                    <svg class="mx-auto h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p class="mt-4 text-sm text-gray-900 dark:text-white">{{ __('No result found, Try a different query') }}</p>
                </div>

                <!-- Footer -->
                <div class="flex flex-wrap items-center bg-gray-50 px-4 py-2.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    {{ __('Press') }} <kbd class="mx-1 flex h-5 w-5 items-center justify-center rounded border bg-white font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">↵</kbd> {{ __('to select') }},
                    <kbd class="mx-1 flex h-5 w-5 items-center justify-center rounded border bg-white font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">↓</kbd>
                    <kbd class="mx-1 flex h-5 w-5 items-center justify-center rounded border bg-white font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">↑</kbd> {{ __('to navigate') }},
                    {{ __('and') }} <kbd class="mx-1 flex h-5 items-center justify-center rounded border bg-white px-1.5 font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">esc</kbd> {{ __('to close') }}.
                </div>
            </div>
        </div>
    </div>
</template>
