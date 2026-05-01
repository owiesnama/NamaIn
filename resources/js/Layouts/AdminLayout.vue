<script setup>
    import { computed, ref } from "vue";
    import { router, Head, Link, usePage } from "@inertiajs/vue3";
    import ApplicationLogo from "@/Components/ApplicationLogo.vue";
    import Banner from "@/Components/Banner.vue";
    import Flash from "@/Shared/Flash.vue";

    defineProps({
        title: String,
    });

    const showingSidebar = ref(false);
    const page = usePage();

    const direction = computed(() => {
        return page.props.locale === "ar" ? "rtl" : "ltr";
    });

    const logout = () => {
        router.post(route("admin.logout"));
    };
</script>

<template>
    <div :dir="direction">
        <Head :title="title ? `Admin - ${title}` : 'Admin'" />

        <Banner />

        <div class="xl:flex xl:h-screen xl:overflow-hidden">

            <!-- SIDEBAR -->
            <aside class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 xl:flex xl:w-64 xl:shrink-0 xl:flex-col xl:border-b-0 xl:border-e xl:border-e-gray-200 dark:xl:border-e-gray-700">

                <!-- Mobile header -->
                <div class="flex h-14 shrink-0 items-center justify-between px-4 xl:hidden">
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
                <div class="hidden h-16 shrink-0 items-center justify-center border-b border-gray-100 px-5 dark:border-gray-800 xl:flex">
                    <Link :href="route('admin.dashboard')" class="group inline-flex items-center gap-x-2.5">
                        <ApplicationLogo class="h-8 w-auto transition-transform duration-200 group-hover:scale-105" />
                        <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">NamaIn</span>
                    </Link>
                </div>

                <!-- Nav body -->
                <div
                    :class="showingSidebar ? 'block' : 'hidden'"
                    class="xl:flex xl:flex-1 xl:flex-col xl:overflow-y-auto px-4 py-5"
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
                    </nav>
                </div>

                <!-- User footer -->
                <div
                    :class="showingSidebar ? 'block' : 'hidden'"
                    class="xl:block shrink-0 border-t border-gray-100 dark:border-gray-800"
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
                            class="inline-flex items-center justify-center rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300 transition-colors duration-200 focus:outline-none"
                            @click.prevent="logout"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- CONTENT AREA -->
            <div class="flex min-h-0 flex-1 flex-col xl:overflow-hidden">
                <main class="xl:flex-1 xl:overflow-y-auto">
                    <div class="px-4 py-10 sm:px-6 lg:px-8 2xl:container 2xl:mx-auto">
                        <Flash />
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
