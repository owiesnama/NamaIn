<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const flash = computed(() => page.props.flash);
const isRtl = computed(() => page.props.locale === 'ar');

// ─── State ──────────────────────────────────────────────────────────
const exports = ref([]);
const panelOpen = ref(false);
const hasBeenTriggered = ref(false);

// ─── Derived ────────────────────────────────────────────────────────
const activeExports = computed(() => exports.value.filter(e => e.status === 'processing' || e.status === 'queued'));
const doneExports = computed(() => exports.value.filter(e => e.status === 'completed' || e.status === 'failed'));
const pillState = computed(() => {
    if (activeExports.value.length > 0) return 'active';
    if (exports.value.length > 0) return 'done';
    return 'idle';
});
const activePercent = computed(() => {
    if (!exports.value.length) return 0;
    const completed = exports.value.filter(e => e.status === 'completed' || e.status === 'failed').length;
    return Math.round((completed / exports.value.length) * 100);
});

// ─── Actions ────────────────────────────────────────────────────────
function addOrUpdate(data) {
    const idx = exports.value.findIndex(e => e.id === data.id);
    if (idx >= 0) {
        exports.value[idx] = { ...exports.value[idx], ...data };
    } else {
        exports.value.unshift(data);
    }
    // Keep max 5
    if (exports.value.length > 5) {
        exports.value = exports.value.slice(0, 5);
    }
}

function togglePanel() {
    panelOpen.value = !panelOpen.value;
}

// ─── Flash watcher ──────────────────────────────────────────────────
watch(flash, (val) => {
    if (val?.type === 'export_queued') {
        addOrUpdate({
            id: val.export_id ?? Date.now(),
            export_key: val.export_key ?? 'export',
            status: 'queued',
        });
        hasBeenTriggered.value = true;
        if (!panelOpen.value && exports.value.length === 1) {
            panelOpen.value = true;
        }
    }
}, { immediate: true });

// ─── Reverb listener ────────────────────────────────────────────────
let echoChannel = null;

onMounted(() => {
    if (window.Echo && page.props.user?.id) {
        echoChannel = window.Echo.private(`operations.user.${page.props.user.id}`)
            .listen('ExportStatusUpdated', (e) => {
                addOrUpdate(e);
                hasBeenTriggered.value = true;
            });
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('ExportStatusUpdated');
    }
});

// ─── Helpers ────────────────────────────────────────────────────────
function humanize(key) {
    if (!key) return __('Export');
    return __(key.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
}

const ringCircumference = 2 * Math.PI * 13; // r=13 for size=30
</script>

<template>
    <Teleport to="body">
        <!-- Expanded Panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
        >
            <div
                v-if="panelOpen"
                class="fixed bottom-16 z-50 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden"
                :class="[isRtl ? 'left-4' : 'right-4']"
                style="end: 1rem; bottom: 4rem;"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Latest Exports') }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5" v-if="exports.length">
                            {{ exports.length }} {{ __('exports') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Link :href="route('exports.index')" class="text-[11px] text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium px-1.5 py-1">
                            {{ __('View All') }}
                        </Link>
                        <button @click="panelOpen = false" class="w-7 h-7 inline-flex items-center justify-center text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600 dark:hover:text-gray-300 rounded-md transition-colors">
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!exports.length" class="py-10 px-4 text-center">
                    <div class="w-11 h-11 mx-auto mb-3 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z" />
                        </svg>
                    </div>
                    <div class="text-[13px] font-semibold text-gray-700 dark:text-gray-300">{{ __('No exports yet.') }}</div>
                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Your exports will appear here') }}</div>
                </div>

                <!-- Export Rows -->
                <div v-else class="max-h-80 overflow-y-auto">
                    <!-- Active section -->
                    <template v-if="activeExports.length">
                        <div class="px-4 py-1.5 bg-gray-50 dark:bg-gray-800/40 flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Active') }}</span>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">{{ activeExports.length }}</span>
                        </div>
                        <div v-for="(exp, i) in activeExports" :key="exp.id"
                            class="px-4 py-3 flex gap-3 items-start"
                            :class="{ 'border-b border-gray-100 dark:border-gray-800': i < activeExports.length - 1 }">
                            <!-- Status icon -->
                            <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center"
                                :class="exp.status === 'processing'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'">
                                <svg v-if="exp.status === 'processing'" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" d="M12 3a9 9 0 0 1 9 9" />
                                </svg>
                                <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold text-gray-900 dark:text-white truncate">{{ humanize(exp.export_key) }}</div>
                                <div class="text-[11px] mt-0.5" :class="exp.status === 'processing' ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400 dark:text-gray-500'">
                                    {{ exp.status === 'processing' ? __('Processing…') : __('Queued') }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Completed section -->
                    <template v-if="doneExports.length">
                        <div class="px-4 py-1.5 bg-gray-50 dark:bg-gray-800/40 flex items-center justify-between"
                            :class="{ 'border-t border-gray-200 dark:border-gray-700': activeExports.length }">
                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Completed') }}</span>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">{{ doneExports.length }}</span>
                        </div>
                        <div v-for="(exp, i) in doneExports" :key="exp.id"
                            class="px-4 py-3 flex gap-3 items-start"
                            :class="{ 'border-b border-gray-100 dark:border-gray-800': i < doneExports.length - 1 }">
                            <!-- Status icon -->
                            <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center"
                                :class="exp.status === 'completed'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400'">
                                <svg v-if="exp.status === 'completed'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold text-gray-900 dark:text-white truncate">{{ humanize(exp.export_key) }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <template v-if="exp.status === 'completed'">
                                        <a :href="route('exports.download', exp.id)"
                                            class="text-[11px] text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-semibold inline-flex items-center gap-1">
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9 4.5 4.5m0 0 4.5-4.5m-4.5 4.5V3" />
                                            </svg>
                                            {{ __('Download') }}
                                        </a>
                                    </template>
                                    <template v-else>
                                        <span class="text-[11px] text-red-500 dark:text-red-400 truncate">{{ exp.failure_message || __('Export failed.') }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>

        <!-- Pill (always visible when triggered or has exports) -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-90"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-90"
        >
            <button
                v-if="!panelOpen"
                @click="togglePanel"
                class="fixed bottom-4 z-50 inline-flex items-center gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full px-4 py-2.5 shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200 cursor-pointer"
                :class="[isRtl ? 'left-4' : 'right-4']"
                style="end: 1rem;"
            >
                <!-- Ring progress or idle bell -->
                <span v-if="pillState === 'idle'" class="w-[30px] h-[30px] rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 inline-flex items-center justify-center">
                    <svg class="h-[15px] w-[15px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </span>
                <span v-else class="relative w-[30px] h-[30px] flex-shrink-0">
                    <svg width="30" height="30" class="-rotate-90">
                        <circle cx="15" cy="15" r="13" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-100 dark:text-gray-800" />
                        <circle cx="15" cy="15" r="13" fill="none"
                            stroke-width="2" stroke-linecap="round"
                            class="text-emerald-600 dark:text-emerald-400"
                            :class="{ 'animate-spin origin-center': pillState === 'active' }"
                            :stroke-dasharray="ringCircumference"
                            :stroke-dashoffset="ringCircumference - (activePercent / 100) * ringCircumference"
                        />
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center">
                        <svg v-if="pillState === 'done'" class="h-3 w-3 text-emerald-700 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <svg v-else class="h-3 w-3 text-emerald-700 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9 4.5 4.5m0 0 4.5-4.5m-4.5 4.5V3" />
                        </svg>
                    </span>
                </span>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    <template v-if="pillState === 'idle'">{{ __('Notifications') }}</template>
                    <template v-else-if="pillState === 'active'">{{ activeExports.length }} {{ __('exports') }}</template>
                    <template v-else>{{ __('Exports ready') }}</template>
                </span>
            </button>
        </Transition>
    </Teleport>
</template>
