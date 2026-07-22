<script setup>
    import { computed, ref, onMounted, onUnmounted } from "vue";
    import { router, Head, Link, usePage } from "@inertiajs/vue3";
    import ApplicationLogo from "@/Components/ApplicationLogo.vue";
    import Banner from "@/Components/Banner.vue";
    import Flash from "@/Shared/Flash.vue";
    import ErrorToast from "@/Shared/ErrorToast.vue";

    defineProps({
        title: String,
    });

    const showingSidebar = ref(false);
    const page = usePage();

    const appDomain = computed(() => page.props.appDomain);
    const horizonUrl = computed(() => `https://queue.${appDomain.value}`);
    const telescopeUrl = computed(() => `https://monitor.${appDomain.value}`);

    const logout = () => {
        router.post(route("admin.logout"));
    };

    // Auto-close the mobile menu after navigating (tablet/mobile).
    let stopNavigationListener = null;
    onMounted(() => {
        stopNavigationListener = router.on("navigate", () => {
            showingSidebar.value = false;
        });
    });
    onUnmounted(() => {
        if (stopNavigationListener) {
            stopNavigationListener();
        }
    });
</script>

<template>
    <div>
        <Head :title="title ? `Admin - ${title}` : 'Admin'" />

        <Banner />

        <div class="lg:flex lg:h-screen lg:overflow-hidden">

            <!-- SIDEBAR -->
            <aside class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:border-b-0 lg:border-e lg:border-e-gray-200 dark:lg:border-e-gray-700">

                <!-- Mobile header -->
                <div class="flex h-14 shrink-0 items-center justify-between px-4 lg:hidden">
                    <Link :href="route('admin.dashboard')" class="inline-flex items-center gap-x-2">
                        <ApplicationLogo class="h-8 w-auto" />
                        <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">Admin</span>
                    </Link>
                    <button
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition-colors duration-200 hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        @click="showingSidebar = !showingSidebar"
                    >
                        <svg v-if="!showingSidebar" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg v-else class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Desktop logo header -->
                <div class="hidden h-16 shrink-0 items-center justify-center border-b border-gray-100 px-5 dark:border-gray-800 lg:flex">
                    <Link :href="route('admin.dashboard')" class="group inline-flex items-center gap-x-2.5">
                        <ApplicationLogo class="h-8 w-auto transition-transform duration-200 group-hover:scale-105" />
                        <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">NamaIn</span>
                    </Link>
                </div>

                <!-- Nav body -->
                <div
                    :class="showingSidebar ? 'block' : 'hidden'"
                    class="lg:flex lg:flex-1 lg:flex-col lg:overflow-y-auto px-4 py-5"
                >
                    <!-- Admin badge -->
                    <div class="mb-4 flex items-center gap-x-2 px-3">
                        <span class="inline-flex items-center rounded-full bg-gray-900 dark:bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white dark:text-gray-900">
                            {{ __('Super Admin') }}
                        </span>
                    </div>

                    <nav class="space-y-1">
                        <!-- Dashboard -->
                        <Link
                            :href="route('admin.dashboard')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.dashboard')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                            <span class="text-sm">{{ __('Dashboard') }}</span>
                        </Link>

                        <!-- Tenants -->
                        <Link
                            :href="route('admin.tenants.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.tenants.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <span class="text-sm">{{ __('Tenants') }}</span>
                        </Link>

                        <!-- Plans -->
                        <Link
                            :href="route('admin.plans.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.plans.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span class="text-sm">{{ __('Plans') }}</span>
                        </Link>

                        <!-- Device fleet -->
                        <Link
                            :href="route('admin.device-fleet.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.device-fleet.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                            </svg>
                            <span class="text-sm">{{ __('Device fleet') }}</span>
                        </Link>

                        <!-- Pilot health -->
                        <Link
                            :href="route('admin.pilot-health.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.pilot-health.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12h-2.25l-2.25 6-4.5-12-2.25 6H7.5" />
                            </svg>
                            <span class="text-sm">{{ __('Pilot health') }}</span>
                        </Link>

                        <!-- Messaging -->
                        <Link
                            :href="route('admin.messages.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.messages.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <span class="text-sm">{{ __('Messaging') }}</span>
                        </Link>

                        <!-- Announcements -->
                        <Link
                            :href="route('admin.announcements.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.announcements.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                            </svg>
                            <span class="text-sm">{{ __('Announcements') }}</span>
                        </Link>

                        <!-- Activity Log -->
                        <Link
                            :href="route('admin.activity')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.activity')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span class="text-sm">{{ __('Activity Log') }}</span>
                        </Link>

                        <!-- Backups -->
                        <Link
                            :href="route('admin.backups.index')"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200"
                            :class="route().current('admin.backups.*')
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white font-semibold'
                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white'"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                            <span class="text-sm">{{ __('Backups') }}</span>
                        </Link>

                        <!-- Divider -->
                        <div class="my-3 border-t border-gray-100 dark:border-gray-800"></div>

                        <p class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Monitoring') }}</p>

                        <!-- Horizon (Queue) -->
                        <a
                            :href="horizonUrl"
                            target="_blank"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008ZM6.75 14.25h.008v.008H6.75v-.008Z" />
                            </svg>
                            <span class="text-sm">{{ __('Queues') }}</span>
                            <svg class="h-3.5 w-3.5 ms-auto text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>

                        <!-- Telescope (Monitor) -->
                        <a
                            :href="telescopeUrl"
                            target="_blank"
                            class="flex items-center gap-x-3 px-3 py-2 rounded-lg transition-all duration-200 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-900 dark:hover:text-white"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                            </svg>
                            <span class="text-sm">{{ __('Monitor') }}</span>
                            <svg class="h-3.5 w-3.5 ms-auto text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </nav>
                </div>

                <!-- User footer -->
                <div
                    :class="showingSidebar ? 'block' : 'hidden'"
                    class="lg:block shrink-0 border-t border-gray-100 dark:border-gray-800"
                >
                    <div class="flex items-center gap-x-3 px-4 py-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-bold text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                        >
                            {{ page.props.user?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1 text-start">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ page.props.user?.name }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ __('Administrator') }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg p-2.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            @click.prevent="logout"
                        >
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- CONTENT AREA -->
            <div class="flex min-h-0 flex-1 flex-col lg:overflow-hidden">
                <main class="lg:flex-1 lg:overflow-y-auto">
                    <div class="px-4 py-10 sm:px-6 lg:px-8 2xl:container 2xl:mx-auto">
                        <Flash />
                        <ErrorToast />
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
