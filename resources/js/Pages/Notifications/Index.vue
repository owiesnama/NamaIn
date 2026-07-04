<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import { router } from "@inertiajs/vue3";
import { resolveType } from "@/Support/notificationTypes";
import { useDate } from "@/Composables/useDate";

const props = defineProps({
    notifications: Object,
});

const { formatDate } = useDate();

const title = (item) => __(item.data?.title ?? "", item.data?.title_params ?? {});

const openItem = (item) => {
    const url = item.data?.url;

    if (!item.read_at) {
        item.read_at = new Date().toISOString();

        router.put(route("notifications.read", item.id), {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => url && router.visit(url),
        });

        return;
    }

    if (url) router.visit(url);
};

const markAllRead = () => {
    router.put(route("notifications.read-all"), {}, { preserveScroll: true });
};

const hasUnread = () => props.notifications.data.some((item) => !item.read_at);
</script>

<template>
    <AppLayout :title="__('Notifications')">
        <!-- Page header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Notifications') }}</h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ notifications.total }}
                    </span>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                <button
                    v-if="hasUnread()"
                    type="button"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-normal text-gray-700 transition-colors duration-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="markAllRead"
                >
                    {{ __('Mark all as read') }}
                </button>
            </div>
        </div>

        <!-- List -->
        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div v-if="notifications.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center text-gray-400 dark:text-gray-500">
                <svg class="mb-3 h-10 w-10 opacity-30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                <p class="text-sm">{{ __('No notifications yet') }}</p>
            </div>

            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                <li v-for="item in notifications.data" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full min-h-[44px] items-start gap-x-4 px-5 py-4 text-start transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500 dark:hover:bg-gray-800"
                        :class="!item.read_at ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : ''"
                        @click="openItem(item)"
                    >
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="resolveType(item.type).colorClasses">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="resolveType(item.type).icon" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ title(item) }}</span>
                            <span v-if="item.data?.body" class="mt-0.5 block whitespace-pre-line text-sm text-gray-500 dark:text-gray-400">{{ item.data.body }}</span>
                            <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ formatDate(item.created_at) }}</span>
                        </span>
                        <span v-if="!item.read_at" class="mt-2 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                    </button>
                </li>
            </ul>
        </div>

        <Pagination :links="notifications.links" />
    </AppLayout>
</template>
