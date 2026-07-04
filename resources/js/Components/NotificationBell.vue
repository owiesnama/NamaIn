<script setup>
import { ref, watch, onMounted, onUnmounted } from "vue";
import { router, usePage, Link } from "@inertiajs/vue3";
import axios from "axios";
import { resolveType } from "@/Support/notificationTypes";
import { useDate } from "@/Composables/useDate";

const page = usePage();
const { formatDate } = useDate();

const open = ref(false);
const loading = ref(false);
const items = ref([]);
const unreadCount = ref(page.props.unreadNotifications ?? 0);
const toast = ref(null);
let toastTimer = null;

watch(
    () => page.props.unreadNotifications,
    (count) => {
        if (typeof count === "number") unreadCount.value = count;
    }
);

const openPanel = async () => {
    open.value = true;
    dismissToast();
    loading.value = true;

    try {
        const { data } = await axios.get(route("notifications.feed"));
        items.value = data.items;
        unreadCount.value = data.unread_count;
    } finally {
        loading.value = false;
    }
};

const title = (item) => __(item.data?.title ?? "", item.data?.title_params ?? {});

const markRead = (item) => {
    const url = item.data?.url;

    if (item.read_at) {
        if (url) {
            open.value = false;
            router.visit(url);
        }
        return;
    }

    item.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);

    router.put(route("notifications.read", item.id), {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            if (url) {
                open.value = false;
                router.visit(url);
            }
        },
    });
};

const markAllRead = () => {
    items.value.forEach((item) => (item.read_at = item.read_at ?? new Date().toISOString()));
    unreadCount.value = 0;

    router.put(route("notifications.read-all"), {}, { preserveState: true, preserveScroll: true });
};

const dismissToast = () => {
    toast.value = null;
    clearTimeout(toastTimer);
};

const onIncoming = (notification) => {
    unreadCount.value++;

    items.value = [
        {
            id: notification.id,
            type: notification.type,
            data: notification,
            read_at: null,
            created_at: new Date().toISOString(),
        },
        ...items.value,
    ].slice(0, 10);

    if (!open.value) {
        toast.value = notification;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(dismissToast, 5000);
    }
};

const handleEsc = (e) => {
    if (e.key === "Escape") open.value = false;
};

onMounted(() => {
    document.addEventListener("keydown", handleEsc);

    if (window.Echo && page.props.user?.id) {
        window.Echo.private(`App.Models.User.${page.props.user.id}`).notification(onIncoming);
    }
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleEsc);
    clearTimeout(toastTimer);

    if (window.Echo && page.props.user?.id) {
        window.Echo.leave(`App.Models.User.${page.props.user.id}`);
    }
});
</script>

<template>
    <div>
        <!-- Bell trigger -->
        <button
            type="button"
            class="relative inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg text-gray-500 transition-colors duration-200 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            @click="openPanel"
        >
            <span class="sr-only">{{ __('Notifications') }}</span>
            <svg class="h-5 w-5 lg:h-6 lg:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute top-1 end-1 inline-flex min-w-[18px] items-center justify-center rounded-full bg-emerald-600 px-1 py-0.5 text-[10px] font-bold leading-none text-white"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <Teleport to="body">
            <!-- Arrival toast -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <button
                    v-if="toast"
                    type="button"
                    class="fixed top-4 end-4 z-50 w-full max-w-sm text-start"
                    @click="openPanel"
                >
                    <div class="rounded-xl border border-emerald-200 border-s-4 border-s-emerald-500 bg-white p-4 shadow-sm dark:border-emerald-800 dark:bg-gray-900">
                        <div class="flex items-start gap-x-3">
                            <div class="mt-0.5 shrink-0">
                                <svg class="h-5 w-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="resolveType(toast.type).icon" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __(toast.title ?? '', toast.title_params ?? {}) }}</p>
                                <p v-if="toast.body" class="mt-0.5 truncate text-sm text-gray-500 dark:text-gray-400">{{ toast.body }}</p>
                            </div>
                        </div>
                    </div>
                </button>
            </Transition>

            <!-- Backdrop -->
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-40 bg-gray-500/20 backdrop-blur-sm dark:bg-gray-900/60"
                    @click="open = false"
                ></div>
            </Transition>

            <!-- Slide-over panel -->
            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="ltr:translate-x-full rtl:-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="ltr:translate-x-full rtl:-translate-x-full"
            >
                <div
                    v-if="open"
                    class="fixed inset-y-0 end-0 z-50 flex w-full max-w-md flex-col border-s border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                >
                    <!-- Header -->
                    <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <div class="flex items-center gap-x-2">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Notifications') }}</h2>
                            <span
                                v-if="unreadCount > 0"
                                class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-emerald-100 px-1.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                            >
                                {{ unreadCount }}
                            </span>
                        </div>
                        <div class="flex items-center gap-x-1">
                            <button
                                v-if="unreadCount > 0"
                                type="button"
                                class="inline-flex min-h-[44px] items-center rounded-lg px-3 text-xs font-semibold text-emerald-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-900/10"
                                @click="markAllRead"
                            >
                                {{ __('Mark all as read') }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg text-gray-400 transition-colors hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-gray-500 dark:hover:text-gray-300"
                                @click="open = false"
                            >
                                <span class="sr-only">{{ __('Close') }}</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- List -->
                    <div class="flex-1 overflow-y-auto overscroll-contain">
                        <div v-if="loading" class="flex justify-center py-12">
                            <svg class="h-5 w-5 animate-spin text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        <div v-else-if="items.length === 0" class="flex flex-col items-center justify-center py-16 text-center text-gray-400 dark:text-gray-500">
                            <svg class="mb-3 h-10 w-10 opacity-30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            <p class="text-sm">{{ __('No notifications yet') }}</p>
                        </div>

                        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="item in items" :key="item.id">
                                <button
                                    type="button"
                                    class="flex w-full min-h-[44px] items-start gap-x-3 px-5 py-4 text-start transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500 dark:hover:bg-gray-800"
                                    :class="!item.read_at ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : ''"
                                    @click="markRead(item)"
                                >
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="resolveType(item.type).colorClasses">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="resolveType(item.type).icon" />
                                        </svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ title(item) }}</span>
                                        <span v-if="item.data?.body" class="mt-0.5 line-clamp-2 block text-sm text-gray-500 dark:text-gray-400">{{ item.data.body }}</span>
                                        <span class="mt-1 block text-xs text-gray-400 dark:text-gray-500">{{ formatDate(item.created_at) }}</span>
                                    </span>
                                    <span v-if="!item.read_at" class="mt-2 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Footer -->
                    <div class="shrink-0 border-t border-gray-200 p-3 dark:border-gray-700">
                        <Link
                            :href="route('notifications.index')"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-gray-200 px-4 text-sm font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="open = false"
                        >
                            {{ __('View all notifications') }}
                        </Link>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
