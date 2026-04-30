<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const toasts = ref([]);
const page = usePage();

const flash = computed(() => page.props.flash);

function addToast(data) {
    const existing = toasts.value.findIndex(t => t.id === data.id);
    if (existing >= 0) {
        toasts.value[existing] = { ...toasts.value[existing], ...data };
    } else {
        toasts.value.push(data);
    }

    if (data.status === 'completed' || data.status === 'failed') {
        setTimeout(() => removeToast(data.id), 8000);
    }
}

function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

// Watch flash prop for export_queued events (fires on every Inertia response)
watch(flash, (val) => {
    if (val?.type === 'export_queued') {
        addToast({
            id: val.export_id ?? Date.now(),
            status: 'queued',
            export_key: val.export_key ?? '',
            message: val.message,
        });
    }
}, { immediate: true });

// Subscribe to Reverb for real-time updates
let echoChannel = null;

onMounted(() => {
    if (window.Echo && page.props.user?.id) {
        echoChannel = window.Echo.private(`exports.user.${page.props.user.id}`)
            .listen('ExportStatusUpdated', (e) => {
                addToast(e);
            });
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('ExportStatusUpdated');
    }
});

const statusIcon = {
    queued: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    processing: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644v-4.992',
    completed: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    failed: 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
};

const statusColor = {
    queued: 'text-gray-400 dark:text-gray-500',
    processing: 'text-amber-500 dark:text-amber-400 animate-spin',
    completed: 'text-emerald-500 dark:text-emerald-400',
    failed: 'text-red-500 dark:text-red-400',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-4 end-4 z-50 space-y-2 max-w-sm">
            <TransitionGroup
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4 flex items-start gap-x-3"
                >
                    <svg class="h-5 w-5 shrink-0 mt-0.5" :class="statusColor[toast.status]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="statusIcon[toast.status]" />
                    </svg>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ toast.export_key ? __(toast.export_key.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())) : __('Export') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            <template v-if="toast.status === 'queued'">{{ __('Queued for processing...') }}</template>
                            <template v-else-if="toast.status === 'processing'">{{ __('Generating export...') }}</template>
                            <template v-else-if="toast.status === 'completed'">
                                <a :href="route('exports.download', toast.id)" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium">
                                    {{ __('Download ready') }}
                                </a>
                            </template>
                            <template v-else-if="toast.status === 'failed'">{{ toast.failure_message || __('Export failed.') }}</template>
                        </p>
                    </div>
                    <button @click="removeToast(toast.id)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
