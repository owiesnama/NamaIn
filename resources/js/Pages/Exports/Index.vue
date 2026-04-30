<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";

const props = defineProps(['exports']);

const statusClasses = {
    queued: 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
    processing: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
    completed: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
    failed: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleString(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};
</script>

<template>
    <AppLayout :title="__('Exports')">
        <div class="space-y-6">
            <div class="w-full flex items-center justify-between">
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Export History') }}</h2>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Export') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('User') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Created') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                            <tr v-for="exp in exports.data" :key="exp.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ exp.export_key }}
                                    <span class="ms-1 px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md uppercase">{{ exp.format }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ exp.user?.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-bold rounded-lg border" :class="statusClasses[exp.status]">
                                        {{ __(exp.status.charAt(0).toUpperCase() + exp.status.slice(1)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(exp.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a
                                        v-if="exp.status === 'completed'"
                                        :href="route('exports.download', exp.id)"
                                        class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium"
                                    >
                                        {{ __('Download') }}
                                    </a>
                                    <span v-else-if="exp.status === 'failed'" class="text-xs text-red-500 dark:text-red-400">
                                        {{ exp.failure_message }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!exports.data?.length" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('No exports yet.') }}
                </div>
            </div>

            <Pagination v-if="exports.data?.length" :links="exports.links" />
        </div>
    </AppLayout>
</template>
