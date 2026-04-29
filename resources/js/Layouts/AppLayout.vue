<script setup>
    import { computed, ref } from "vue";
    import { router, Head, Link, usePage } from "@inertiajs/vue3";
    import ApplicationLogo from "@/Components/ApplicationLogo.vue";
    import Banner from "@/Components/Banner.vue";
    import ImpersonationBanner from "@/Components/ImpersonationBanner.vue";
    import DropdownLink from "@/Components/DropdownLink.vue";
    import NavLink from "@/Components/NavLink.vue";
    import SidebarGroup from "@/Components/SidebarGroup.vue";
    import GlobalSearch from "@/Components/GlobalSearch.vue";
    import Flash from "@/Shared/Flash.vue";
    import ExportProgressToast from "@/Components/ExportProgressToast.vue";
    import { usePermissions } from "@/Composables/usePermissions";

    defineProps({
        title: String,
    });

    const showingSidebar = ref(false);
    const showingUserMenu = ref(false);
    const direction = computed(() => {
        return usePage().props.locale === "ar" ? "rtl" : "ltr";
    });

    const page = usePage();

    const logout = () => {
        router.post(route("logout"));
    };

    const { can } = usePermissions();

    const switchingToTenant = ref(null);

    const switchTenant = (tenant) => {
        if (switchingToTenant.value) return;
        switchingToTenant.value = tenant.id;
        router.post(`${window.location.origin}/switch-tenant/${tenant.id}`, {}, {
            onFinish: () => { switchingToTenant.value = null; },
        });
    };
</script>

<template>
    <div :dir="direction">
        <Head :title="__(title)" />

        <ImpersonationBanner />
        <Banner />

        <!-- App shell -->
        <div class="xl:flex xl:h-screen xl:overflow-hidden">

            <!-- ─────────────────────────────────────────
                 SIDEBAR
                 DOM order: first → top on mobile, end-side on desktop via xl:order-last
            ───────────────────────────────────────── -->
            <aside class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 xl:flex xl:w-72 xl:shrink-0 xl:flex-col xl:border-b-0 xl:border-s xl:border-s-gray-200 dark:xl:border-s-gray-700 text-right ltr:text-left">

                <!-- Mobile header: logo + hamburger toggle -->
                <div class="flex h-14 shrink-0 items-center justify-between px-4 xl:hidden">
                    <Link :href="route('dashboard')" class="inline-flex items-center gap-x-2">
                        <ApplicationLogo class="h-8 w-auto" />
                        <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">NamaIn</span>
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

                <!-- Desktop logo header (h-16 aligns with desktop search bar) -->
                <div class="hidden h-16 shrink-0 items-center justify-center border-b border-gray-100 px-5 dark:border-gray-800 xl:flex ">
                    <Link :href="route('dashboard')" class="group inline-flex items-center gap-x-2.5">
                        <ApplicationLogo class="h-8 w-auto transition-transform duration-200 group-hover:scale-105" />
                        <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">NamaIn</span>
                    </Link>
                </div>

                <!-- Nav body: always visible on xl, toggled on mobile -->
                <div
                    :class="showingSidebar ? 'block' : 'hidden'"
                    class="xl:flex xl:flex-1 xl:flex-col xl:overflow-y-auto px-4 py-5 border-l ltr:border-l border-gray-100 dark:border-gray-800"
                >
                    <div class="mb-4">
                        <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            <svg class="h-5 w-5 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                            <span>{{ __('Dashboard') }}</span>
                        </NavLink>
                    </div>

                    <div class="mb-4 border-t border-gray-100 dark:border-gray-800" />

                    <nav class="space-y-1">
                        <!-- Operations -->
                        <SidebarGroup
                            v-if="can('pos.view') || can('pos.operate') || can('inventory.transfer')"
                            title="العمليات"
                            :active="route().current('pos.*') || route().current('stock-transfers.*')"
                        >
                            <template #icon>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                                </svg>
                            </template>

                            <NavLink v-if="can('pos.view') || can('pos.operate')" :href="route('pos.index')" :active="route().current('pos.index')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                                </svg>
                                <span>{{ __('POS') }}</span>
                            </NavLink>

                            <NavLink v-if="can('pos.view')" :href="route('pos.invoices')" :active="route().current('pos.invoices')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                </svg>
                                <span>{{ __('POS History') }}</span>
                            </NavLink>

                            <NavLink v-if="can('inventory.transfer')" :href="route('stock-transfers.index')" :active="route().current('stock-transfers.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                                <span>{{ __('Stock Transfers') }}</span>
                            </NavLink>
                        </SidebarGroup>

                        <!-- Inventory -->
                        <SidebarGroup
                            v-if="can('products.view') || can('inventory.view')"
                            title="المخزون"
                            :active="route().current('products.*') || route().current('storages.*')"
                        >
                            <template #icon>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </template>

                            <NavLink v-if="can('products.view')" :href="route('products.index')" :active="route().current('products.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L9.568 3Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                                </svg>
                                <span>{{ __('Products') }}</span>
                            </NavLink>

                            <NavLink v-if="can('inventory.view')" :href="route('storages.index')" :active="route().current('storages.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016 2.993 2.993 0 0 0 2.25-1.015 3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                </svg>
                                <span>{{ __('Storages') }}</span>
                            </NavLink>
                        </SidebarGroup>

                        <!-- Relations -->
                        <SidebarGroup
                            v-if="can('customers.view') || can('suppliers.view')"
                            title="العلاقات"
                            :active="route().current('customers.*') || route().current('suppliers.*')"
                        >
                            <template #icon>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </template>

                            <NavLink v-if="can('customers.view')" :href="route('customers.index')" :active="route().current('customers.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span>{{ __('Customers') }}</span>
                            </NavLink>

                            <NavLink v-if="can('suppliers.view')" :href="route('suppliers.index')" :active="route().current('suppliers.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m3.75-10.5H18a2.25 2.25 0 0 1 0 4.5h-.75v4.5m-7.5-9H9.75a1.125 1.125 0 0 0-1.125 1.125v3.375m0 0H3.375c-.621 0-1.125.504-1.125 1.125V18.75" />
                                </svg>
                                <span>{{ __('Suppliers') }}</span>
                            </NavLink>
                        </SidebarGroup>

                        <!-- Accounting -->
                        <SidebarGroup
                            v-if="can('sales.view') || can('purchases.view') || can('payments.view') || can('payments.manage-cheques') || can('expenses.view') || can('treasury.view')"
                            title="المحاسبة"
                            :active="route().current('sales.*') || route().current('purchases.*') || route().current('payments.*') || route().current('cheques.*') || route().current('expenses.*') || route().current('treasury.*')"
                        >
                            <template #icon>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                                </svg>
                            </template>

                            <NavLink v-if="can('sales.view')" :href="route('sales.index')" :active="route().current('sales.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                </svg>
                                <span>{{ __('Sales') }}</span>
                            </NavLink>

                            <NavLink v-if="can('purchases.view')" :href="route('purchases.index')" :active="route().current('purchases.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                                </svg>
                                <span>{{ __('Purchases') }}</span>
                            </NavLink>

                            <NavLink v-if="can('payments.view')" :href="route('payments.index')" :active="route().current('payments.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                </svg>
                                <span>{{ __('Payments') }}</span>
                            </NavLink>

                            <NavLink v-if="can('payments.manage-cheques')" :href="route('cheques.index')" :active="route().current('cheques.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                                </svg>
                                <span>{{ __('Cheques') }}</span>
                            </NavLink>

                            <NavLink v-if="can('expenses.view')" :href="route('expenses.index')" :active="route().current('expenses.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <span>{{ __('Expenses') }}</span>
                            </NavLink>

                            <NavLink v-if="can('treasury.view')" :href="route('treasury.index')" :active="route().current('treasury.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                                </svg>
                                <span>{{ __('Treasury') }}</span>
                            </NavLink>
                        </SidebarGroup>

                        <!-- Reports -->
                        <NavLink v-if="can('reports.view')" :href="route('reports.index')" :active="route().current('reports.*')">
                            <svg class="h-5 w-5 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                            <span>{{ __('Reports') }}</span>
                        </NavLink>

                        <!-- Team Management -->
                        <SidebarGroup
                            v-if="can('users.view') || can('roles.manage')"
                            title="الفريق"
                            :active="route().current('users.*') || route().current('roles.*')"
                        >
                            <template #icon>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                            </template>

                            <NavLink v-if="can('users.view')" :href="route('users.index')" :active="route().current('users.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span>{{ __('Team Members') }}</span>
                            </NavLink>

                            <NavLink v-if="can('roles.manage')" :href="route('roles.index')" :active="route().current('roles.*')">
                                <svg class="h-4 w-4 shrink-0 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>{{ __('Roles') }}</span>
                            </NavLink>
                        </SidebarGroup>
                    </nav>
                </div>

                <!-- User footer: toggled on mobile, always visible on desktop -->
                <div
                    :class="showingSidebar ? 'block' : 'hidden'"
                    class="xl:block shrink-0 border-t border-gray-100 dark:border-gray-800"
                >
                    <!-- Click-outside overlay -->
                    <div v-if="showingUserMenu" class="fixed inset-0 z-40" @click="showingUserMenu = false" />

                    <div class="relative">
                        <!-- Trigger -->
                        <button
                            type="button"
                            class="flex w-full items-center gap-x-3 px-4 py-3 transition-colors duration-200 hover:bg-gray-50 focus:outline-none dark:hover:bg-gray-800"
                            @click="showingUserMenu = !showingUserMenu"
                        >
                            <img
                                v-if="$page.props.user?.profile_photo_url"
                                class="h-8 w-8 shrink-0 rounded-full object-cover"
                                :src="$page.props.user.profile_photo_url"
                                :alt="$page.props.user?.name"
                            />
                            <div
                                v-else
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                            >
                                {{ $page.props.user?.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1 text-start">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $page.props.user?.name }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $page.props.currentTenant?.name }}</p>
                            </div>
                            <svg
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500"
                                :class="showingUserMenu ? 'rotate-180' : ''"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </button>

                        <!-- Popover — opens upward -->
                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 translate-y-1"
                        >
                            <div
                                v-if="showingUserMenu"
                                class="absolute bottom-full z-50 mb-1 w-72 rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                                :class="direction === 'rtl' ? 'right-0' : 'left-0'"
                                @click="showingUserMenu = false"
                            >
                                <div class="py-1">
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ __('Manage Account') }}
                            </div>

                            <DropdownLink :href="route('profile.show')">
                                {{ __('Profile') }}
                            </DropdownLink>

                            <DropdownLink :href="route('preferences.index')">
                                {{ __('Preferences') }}
                            </DropdownLink>

                            <!-- Tenant Switcher -->
                            <template v-if="$page.props.tenants?.length > 1">
                                <div class="mx-3 my-1 border-t border-slate-100 dark:border-gray-700" />

                                <div class="px-4 pb-1 pt-2">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Workspaces') }}</p>
                                </div>

                                <div class="space-y-0.5 px-2 pb-2">
                                    <button
                                        v-for="tenant in $page.props.tenants"
                                        :key="tenant.id"
                                        type="button"
                                        class="group flex w-full overflow-hidden rounded-xl border text-sm transition-all duration-150 focus:outline-none"
                                        :class="[
                                            tenant.id === $page.props.currentTenant?.id
                                                ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20 cursor-default'
                                                : switchingToTenant === tenant.id
                                                    ? 'border-slate-200 bg-slate-100 dark:border-gray-700 dark:bg-gray-800 cursor-wait'
                                                    : 'border-slate-200 bg-slate-100 dark:border-gray-700 dark:bg-gray-800 hover:border-slate-300 dark:hover:border-gray-600 cursor-pointer',
                                            switchingToTenant && switchingToTenant !== tenant.id && tenant.id !== $page.props.currentTenant?.id
                                                ? 'opacity-50' : ''
                                        ]"
                                        :disabled="tenant.id === $page.props.currentTenant?.id || !!switchingToTenant"
                                        @click="tenant.id !== $page.props.currentTenant?.id && switchTenant(tenant)"
                                    >
                                        <!-- Avatar segment -->
                                        <span
                                            class="flex h-9 w-9 shrink-0 items-center justify-center border-e text-xs font-bold uppercase transition-colors"
                                            :class="tenant.id === $page.props.currentTenant?.id
                                                ? 'bg-emerald-500 text-white border-emerald-200 dark:border-emerald-800'
                                                : switchingToTenant === tenant.id
                                                    ? 'bg-emerald-500 text-white border-slate-200 dark:border-gray-700'
                                                    : 'bg-slate-200 dark:bg-gray-700 text-slate-500 dark:text-gray-300 border-slate-200 dark:border-gray-700 group-hover:bg-slate-300 dark:group-hover:bg-gray-600'"
                                        >
                                            <svg
                                                v-if="switchingToTenant === tenant.id"
                                                class="h-3.5 w-3.5 animate-spin"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            <template v-else>{{ tenant.name.charAt(0) }}</template>
                                        </span>

                                        <!-- Name -->
                                        <span
                                            class="flex-1 truncate px-3 font-medium ltr:text-left rtl:text-right"
                                            :class="tenant.id === $page.props.currentTenant?.id
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : switchingToTenant === tenant.id
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-slate-700 dark:text-gray-300'"
                                        >{{ tenant.name }}</span>

                                        <!-- Active badge -->
                                        <span
                                            v-if="tenant.id === $page.props.currentTenant?.id"
                                            class="me-2.5 inline-flex shrink-0 items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400"
                                        >{{ __('Active') }}</span>

                                        <!-- Switching label -->
                                        <span
                                            v-else-if="switchingToTenant === tenant.id"
                                            class="me-2.5 shrink-0 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                        >{{ __('Switching…') }}</span>

                                        <!-- Arrow on hover -->
                                        <svg
                                            v-else
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="me-2.5 h-3.5 w-3.5 shrink-0 text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 rtl:rotate-180"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div class="border-t border-gray-100 dark:border-gray-800" />

                            <!-- Log Out -->
                            <form>
                                <DropdownLink as="button">
                                    <span class="text-red-400" @click.prevent="logout">{{ __('Log Out') }}</span>
                                </DropdownLink>
                            </form>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </aside>

            <!-- ─────────────────────────────────────────
                 CONTENT AREA
            ───────────────────────────────────────── -->
            <div class="flex min-h-0 flex-1 flex-col xl:overflow-hidden">

                <!-- Desktop top bar: search only, h-16 aligns with sidebar logo header -->
                <div class="hidden h-16 shrink-0 items-center border-b border-gray-100 bg-white px-4 dark:border-gray-700 dark:bg-gray-900 sm:px-6 lg:px-8 xl:flex">
                    <GlobalSearch />
                </div>

                <!-- Mobile search bar (shown below the aside on mobile) -->
                <div class=" border-b border-gray-100 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900 xl:hidden">
                    <GlobalSearch />
                </div>

                <!-- Main content -->
                <main class="xl:flex-1 xl:overflow-y-auto">
                    <div class="px-4 py-10 sm:px-6 lg:px-8 2xl:container 2xl:mx-auto">
                        <Flash />
                        <ExportProgressToast />
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
