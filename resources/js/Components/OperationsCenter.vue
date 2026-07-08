<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const flash = computed(() => page.props.flash);
const sharedOperations = computed(() => page.props.operations ?? []);
const isRtl = computed(() => page.props.locale === 'ar');

// ─── State ──────────────────────────────────────────────────────────
const operations = ref([]);
const panelOpen = ref(false);
let restoredSharedOperations = false;

// ─── Derived ────────────────────────────────────────────────────────
const active = computed(() => operations.value.filter(o => isActiveStatus(o.status)));
const done = computed(() => operations.value.filter(o => isFinishedStatus(o.status)));
const pillState = computed(() => {
    if (active.value.length > 0) return 'active';
    if (operations.value.length > 0) return 'done';
    return 'idle';
});
const progressPercent = computed(() => {
    if (!operations.value.length) return 0;
    return Math.round((done.value.length / operations.value.length) * 100);
});

// ─── Actions ────────────────────────────────────────────────────────
function addOrUpdate(data) {
    const idx = operations.value.findIndex(o => o.id === data.id);
    if (idx >= 0) {
        operations.value[idx] = { ...operations.value[idx], ...data };
    } else {
        operations.value.unshift(data);
    }
    if (operations.value.length > 10) {
        operations.value = operations.value.slice(0, 10);
    }
}

function hydrateOperations(items) {
    if (restoredSharedOperations) return;

    restoredSharedOperations = true;

    if (!Array.isArray(items) || items.length === 0) return;

    // Re-seed the operations snapshot on each fresh mount so the pill reflects
    // pending work, but never force the panel open — auto-opening on every
    // navigation is disruptive (especially on tablets). The user opens it.
    [...items].reverse().forEach(addOrUpdate);
}

function dismiss(id) {
    operations.value = operations.value.filter(o => o.id !== id);
}

function clearAll() {
    operations.value = [];
}

// ─── Event handlers ─────────────────────────────────────────────────
const eventHandlers = {
    export_queued: (val) => ({
        id: val.export_id ?? Date.now(),
        kind: 'export',
        label: val.export_key ?? 'export',
        status: 'queued',
    }),
    import_queued: (val) => ({
        id: `import-${val.import_id ?? Date.now()}`,
        kind: 'import',
        label: val.import_type ?? 'import',
        status: 'queued',
    }),
};

// ─── Flash watcher ──────────────────────────────────────────────────
// Shared operation snapshot
watch(sharedOperations, hydrateOperations, { immediate: true });

watch(flash, (val) => {
    if (!val?.type) return;
    const handler = eventHandlers[val.type];
    if (handler) {
        addOrUpdate(handler(val));
        if (!panelOpen.value && operations.value.length === 1) {
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
                addOrUpdate({ ...e, kind: 'export', label: e.export_key });
            })
            .listen('ImportStatusUpdated', (e) => {
                addOrUpdate({
                    ...e,
                    id: `import-${e.id}`,
                    kind: 'import',
                    label: e.import_type,
                });
            });
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('ExportStatusUpdated');
        echoChannel.stopListening('ImportStatusUpdated');
    }
});

// ─── Helpers ────────────────────────────────────────────────────────
function isActiveStatus(status) {
    return status === 'processing' || status === 'queued';
}

function isFinishedStatus(status) {
    return status === 'completed' || status === 'failed';
}

function title(op) {
    const label = __(op.label?.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || '');
    const kindLabel = op.kind === 'import' ? __('Import') : __('Export');
    return `${label} — ${kindLabel}`;
}

function statusText(op) {
    if (op.status === 'queued') return __('Queued');
    if (op.status === 'processing') return __('Processing…');
    if (op.status === 'completed') {
        if (op.kind === 'import') return `${__('Imported')} ${op.rows_imported ?? 0} ${__('rows')}`;
        return __('Ready');
    }
    if (op.kind === 'import') return op.failure_message || __('Import failed. Please check the file and try again.');
    return op.failure_message || __('Export failed. Please try again.');
}

const ringCircumference = 2 * Math.PI * 13;
</script>

<template>
    <Teleport to="body">
        <!-- Panel -->
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
                data-testid="operations-panel"
                class="fixed bottom-[calc(4rem+env(safe-area-inset-bottom))] z-50 w-80 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden"
                :class="[isRtl ? 'left-4' : 'right-4']"
            >
                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Operations') }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5" v-if="operations.length">
                            {{ operations.length }} {{ operations.length === 1 ? __('operation') : __('operations') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button v-if="operations.length" @click="clearAll" class="text-[11px] text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium px-2 py-1.5 rounded-md transition-colors">
                            {{ __('Clear') }}
                        </button>
                        <button @click="panelOpen = false" class="w-11 h-11 inline-flex items-center justify-center text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600 dark:hover:text-gray-300 rounded-md transition-colors">
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!operations.length" class="py-10 px-4 text-center">
                    <div class="w-11 h-11 mx-auto mb-3 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                    </div>
                    <div class="text-[13px] font-semibold text-gray-700 dark:text-gray-300">{{ __('No operations yet') }}</div>
                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Background tasks will appear here') }}</div>
                </div>

                <!-- Operations List -->
                <div v-else class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                    <!-- Active -->
                    <template v-if="active.length">
                        <div class="px-4 py-1.5 bg-gray-50 dark:bg-gray-800/40 flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Active') }}</span>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">{{ active.length }}</span>
                        </div>
                        <div v-for="op in active" :key="op.id" class="px-4 py-3 flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center"
                                :class="op.status === 'processing'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'">
                                <svg v-if="op.status === 'processing'" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" d="M12 3a9 9 0 0 1 9 9" />
                                </svg>
                                <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold text-gray-900 dark:text-white truncate">{{ title(op) }}</div>
                                <div class="text-[11px] mt-0.5" :class="op.status === 'processing' ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-400 dark:text-gray-500'">
                                    {{ statusText(op) }}
                                </div>
                            </div>
                            <button @click="dismiss(op.id)" class="w-11 h-11 flex-shrink-0 inline-flex items-center justify-center rounded-md text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>

                    <!-- Done -->
                    <template v-if="done.length">
                        <div class="px-4 py-1.5 bg-gray-50 dark:bg-gray-800/40 flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Completed') }}</span>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500">{{ done.length }}</span>
                        </div>
                        <div v-for="op in done" :key="op.id" class="px-4 py-3 flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center"
                                :class="op.status === 'completed'
                                    ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400'">
                                <svg v-if="op.status === 'completed'" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <svg v-else class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-semibold text-gray-900 dark:text-white truncate">{{ title(op) }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <template v-if="op.status === 'completed' && op.kind === 'export'">
                                        <a :href="route('exports.download', op.id)"
                                            class="text-[11px] text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-semibold inline-flex items-center gap-1">
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9 4.5 4.5m0 0 4.5-4.5m-4.5 4.5V3" />
                                            </svg>
                                            {{ __('Download') }}
                                        </a>
                                    </template>
                                    <template v-else>
                                        <span class="text-[11px] font-medium" :class="op.status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'">
                                            {{ statusText(op) }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                            <button @click="dismiss(op.id)" class="w-11 h-11 flex-shrink-0 inline-flex items-center justify-center rounded-md text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>

        <!-- Pill -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-90"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-90"
        >
            <button
                v-if="!panelOpen && pillState !== 'idle'"
                @click="panelOpen = true"
                data-testid="operations-pill"
                class="fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] z-50 inline-flex items-center gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full px-4 py-2.5 shadow-sm hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200 cursor-pointer"
                :class="[isRtl ? 'left-4' : 'right-4']"
            >
                <span class="relative w-[30px] h-[30px] flex-shrink-0">
                    <svg width="30" height="30" class="-rotate-90">
                        <circle cx="15" cy="15" r="13" fill="none" stroke="currentColor" stroke-width="2" class="text-gray-100 dark:text-gray-800" />
                        <circle cx="15" cy="15" r="13" fill="none"
                            stroke-width="2" stroke-linecap="round"
                            class="text-emerald-600 dark:text-emerald-400"
                            :class="{ 'animate-spin origin-center': pillState === 'active' }"
                            :stroke-dasharray="ringCircumference"
                            :stroke-dashoffset="ringCircumference - (progressPercent / 100) * ringCircumference"
                        />
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center">
                        <svg v-if="pillState === 'done'" class="h-3 w-3 text-emerald-700 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span v-else class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400">{{ active.length }}</span>
                    </span>
                </span>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    <template v-if="pillState === 'active'">{{ active.length }} {{ __('in progress') }}</template>
                    <template v-else>{{ __('Done') }}</template>
                </span>
            </button>
        </Transition>

        <!-- Idle nudge — a tab tucked against the screen edge when there is no work -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-x-2 rtl:-translate-x-2"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-2 rtl:-translate-x-2"
        >
            <button
                v-if="!panelOpen && pillState === 'idle'"
                @click="panelOpen = true"
                data-testid="operations-pill"
                class="group fixed top-1/2 -translate-y-1/2 z-50 inline-flex items-center gap-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm py-3 px-2.5 hover:px-3 transition-all duration-200 cursor-pointer"
                :class="[isRtl ? 'left-0 rounded-e-xl border-s-0' : 'right-0 rounded-s-xl border-e-0']"
            >
                <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 inline-flex items-center justify-center flex-shrink-0">
                    <svg class="h-[15px] w-[15px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </span>
                <span class="max-w-0 overflow-hidden opacity-0 group-hover:max-w-[8rem] group-hover:opacity-100 transition-all duration-200 text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    {{ __('Operations') }}
                </span>
            </button>
        </Transition>
    </Teleport>
</template>
