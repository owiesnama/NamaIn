<script setup>
    import { ref } from "vue";
    import { router, Head, Link } from "@inertiajs/vue3";
    import ApplicationMark from "@/Components/ApplicationMark.vue";
    import Banner from "@/Components/Banner.vue";
    import Dropdown from "@/Components/Dropdown.vue";
    import DropdownLink from "@/Components/DropdownLink.vue";
    import NavLink from "@/Components/NavLink.vue";
    import Flash from "@/Shared/Flash.vue";

    defineProps({
        title: String,
    });

    const showingSidebar = ref(false);

    const switchToTeam = (team) => {
        router.put(
            route("current-team.update"),
            {
                team_id: team.id,
            },
            {
                preserveState: false,
            }
        );
    };

    const logout = () => {
        router.post(route("logout"));
    };
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="flex h-screen">
            <div
                v-cloak
                class="fixed inset-0 z-20 transition-opacity bg-gray-200/50 xl:hidden"
                :class="showingSidebar ? 'block' : 'hidden'"
                @click="showingSidebar = false"
            ></div>

            <aside
                :class="
                    showingSidebar
                        ? 'translate-x-0 ease-in'
                        : '-translate-x-full ease-out'
                "
                class="fixed inset-y-0 left-0 z-30 w-64 px-5 py-8 overflow-y-auto transition duration-200 transform bg-white border-r border-gray-200 xl:translate-x-0 xl:relative xl:inset-0 rtl:border-r-0 rtl:border-l dark:bg-gray-900 dark:border-gray-700"
            >
                <Link :href="route('dashboard')">
                    <ApplicationMark class="block w-auto h-9" />
                </Link>

                <div class="flex flex-col justify-between flex-1 mt-6">
                    <nav class="-mx-3 space-y-6">
                        <div class="space-y-3">
                            <label
                                class="px-3 text-xs text-gray-500 uppercase dark:text-gray-400"
                                >Main</label
                            >

                            <NavLink
                                :href="route('dashboard')"
                                :active="route().current('dashboard')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Dashboard</span
                                >
                            </NavLink>
                        </div>

                        <div class="space-y-3">
                            <label
                                class="px-3 text-xs text-gray-500 uppercase dark:text-gray-400"
                                >content</label
                            >

                            <NavLink
                                :href="route('customers.index')"
                                :active="route().current('customers.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Customers</span
                                >
                            </NavLink>

                            <NavLink
                                :href="route('products.index')"
                                :active="route().current('products.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Products</span
                                >
                            </NavLink>

                            <NavLink
                                :href="route('storages.index')"
                                :active="route().current('storages.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Storages</span
                                >
                            </NavLink>

                            <NavLink
                                :href="route('suppliers.index')"
                                :active="route().current('suppliers.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Suppliers</span
                                >
                            </NavLink>
                        </div>

                        <div class="space-y-3">
                            <label
                                class="px-3 text-xs text-gray-500 uppercase dark:text-gray-400"
                                >Transactions</label
                            >

                            <NavLink
                                :href="route('cheques.index')"
                                :active="route().current('cheques.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Cheques</span
                                >
                            </NavLink>

                            <NavLink
                                :href="route('purchases.index')"
                                :active="route().current('purchases.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Purchases</span
                                >
                            </NavLink>

                            <NavLink
                                :href="route('sales.index')"
                                :active="route().current('sales.index')"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-5 h-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"
                                    />
                                </svg>

                                <span class="mx-2 text-sm font-medium"
                                    >Sales</span
                                >
                            </NavLink>
                        </div>
                    </nav>
                </div>
            </aside>

            <div class="flex flex-col flex-1 overflow-y-auto">
                <nav class="bg-white border-b border-gray-200 shadow-sm">
                    <!-- Primary Navigation Menu -->
                    <div class="px-4 mx-auto sm:px-6 lg:px-8 2xl:container">
                        <div class="flex items-center justify-between h-16">
                            <form
                                class="relative flex-1 hidden xl:flex"
                                action="#"
                                method="GET"
                            >
                                <label
                                    for="search-field"
                                    class="sr-only"
                                    >Search</label
                                >
                                <svg
                                    class="absolute inset-y-0 left-0 w-5 h-full text-gray-400 pointer-events-none"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <input
                                    id="search-field"
                                    class="block w-full h-full py-0 pl-8 pr-0 text-gray-900 border-0 placeholder:text-gray-400 focus:ring-0 sm:text-sm"
                                    placeholder="Search..."
                                    type="search"
                                    name="search"
                                />
                            </form>

                            <Link
                                class="xl:hidden"
                                :href="route('dashboard')"
                            >
                                <ApplicationMark class="block w-auto h-9" />
                            </Link>

                            <div class="flex items-center">
                                <div class="flex items-center">
                                    <div class="relative">
                                        <!-- Teams Dropdown -->
                                        <Dropdown
                                            align="right"
                                            width="60"
                                        >
                                            <template #trigger>
                                                <span
                                                    class="inline-flex rounded-md"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-transparent rounded-md hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            fill="currentColor"
                                                            class="w-5 h-5"
                                                        >
                                                            <path
                                                                fill-rule="evenodd"
                                                                d="M5.25 9a6.75 6.75 0 0113.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 01-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 11-7.48 0 24.585 24.585 0 01-4.831-1.244.75.75 0 01-.298-1.205A8.217 8.217 0 005.25 9.75V9zm4.502 8.9a2.25 2.25 0 104.496 0 25.057 25.057 0 01-4.496 0z"
                                                                clip-rule="evenodd"
                                                            />
                                                        </svg>
                                                    </button>
                                                </span>
                                            </template>

                                            <template #content>
                                                <div class="w-64 sm:w-80">
                                                    <a
                                                        href="#"
                                                        class="flex items-center px-4 py-3 transition-colors duration-300 transform border-b border-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 dark:border-gray-700"
                                                    >
                                                        <img
                                                            class="flex-shrink-0 object-cover w-8 h-8 mx-1 rounded-full"
                                                            src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=crop&w=334&q=80"
                                                            alt="avatar"
                                                        />
                                                        <p
                                                            class="mx-2 text-sm text-gray-600 dark:text-white"
                                                        >
                                                            <span
                                                                class="font-bold"
                                                                href="#"
                                                                >Sara
                                                                Salah</span
                                                            >
                                                            replied on the
                                                            <span
                                                                class="text-emerald-500 hover:underline"
                                                                href="#"
                                                                >Upload
                                                                Image</span
                                                            >
                                                            artical . 2m
                                                        </p>
                                                    </a>
                                                    <a
                                                        href="#"
                                                        class="flex items-center px-4 py-3 transition-colors duration-300 transform border-b border-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 dark:border-gray-700"
                                                    >
                                                        <img
                                                            class="flex-shrink-0 object-cover w-8 h-8 mx-1 rounded-full"
                                                            src="https://images.unsplash.com/photo-1531427186611-ecfd6d936c79?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=crop&w=634&q=80"
                                                            alt="avatar"
                                                        />
                                                        <p
                                                            class="mx-2 text-sm text-gray-600 dark:text-white"
                                                        >
                                                            <span
                                                                class="font-bold"
                                                                href="#"
                                                                >Slick Net</span
                                                            >
                                                            start following you
                                                            . 45m
                                                        </p>
                                                    </a>
                                                    <a
                                                        href="#"
                                                        class="flex items-center px-4 py-3 transition-colors duration-300 transform border-b border-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700 dark:border-gray-700"
                                                    >
                                                        <img
                                                            class="flex-shrink-0 object-cover w-8 h-8 mx-1 rounded-full"
                                                            src="https://images.unsplash.com/photo-1450297350677-623de575f31c?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=crop&w=334&q=80"
                                                            alt="avatar"
                                                        />
                                                        <p
                                                            class="mx-2 text-sm text-gray-600 dark:text-white"
                                                        >
                                                            <span
                                                                class="font-bold"
                                                                href="#"
                                                                >Jane Doe</span
                                                            >
                                                            Like Your reply on
                                                            <span
                                                                class="text-emerald-500 hover:underline"
                                                                href="#"
                                                                >Test with
                                                                TDD</span
                                                            >
                                                            artical . 1h
                                                        </p>
                                                    </a>
                                                    <a
                                                        href="#"
                                                        class="flex items-center px-4 py-3 transition-colors duration-300 transform hover:bg-gray-100 dark:hover:bg-gray-700"
                                                    >
                                                        <img
                                                            class="flex-shrink-0 object-cover w-8 h-8 mx-1 rounded-full"
                                                            src="https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=crop&w=398&q=80"
                                                            alt="avatar"
                                                        />
                                                        <p
                                                            class="mx-2 text-sm text-gray-600 dark:text-white"
                                                        >
                                                            <span
                                                                class="font-bold"
                                                                href="#"
                                                                >Abigail
                                                                Bennett</span
                                                            >
                                                            start following you
                                                            . 3h
                                                        </p>
                                                    </a>
                                                </div>
                                            </template>
                                        </Dropdown>
                                    </div>

                                    <!-- Preferences Dropdown -->
                                    <div class="relative ml-3">
                                        <Dropdown
                                            align="right"
                                            width="48"
                                        >
                                            <template #trigger>
                                                <button
                                                    v-if="
                                                        $page.props.jetstream
                                                            .managesProfilePhotos
                                                    "
                                                    class="flex text-sm transition border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300"
                                                >
                                                    <img
                                                        class="object-cover w-8 h-8 rounded-full"
                                                        :src="
                                                            $page.props.user
                                                                .profile_photo_url
                                                        "
                                                        :alt="
                                                            $page.props.user
                                                                .name
                                                        "
                                                    />
                                                </button>

                                                <span
                                                    v-else
                                                    class="inline-flex rounded-md"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-transparent rounded-md hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50"
                                                    >
                                                        {{
                                                            $page.props.user
                                                                .name
                                                        }}

                                                        <svg
                                                            class="ml-2 -mr-0.5 h-4 w-4"
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke-width="1.5"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M4.5 12.75l6 6 9-13.5"
                                                            />
                                                        </svg>
                                                    </button>
                                                </span>
                                            </template>

                                            <template #content>
                                                <!-- Account Management -->
                                                <div
                                                    class="block px-4 py-2 text-xs text-gray-400"
                                                >
                                                    Manage Account
                                                </div>

                                                <DropdownLink
                                                    :href="
                                                        route('profile.show')
                                                    "
                                                >
                                                    Profile
                                                </DropdownLink>

                                                <DropdownLink
                                                    :href="
                                                        route(
                                                            'preferences.index'
                                                        )
                                                    "
                                                >
                                                    Preferences
                                                </DropdownLink>

                                                <DropdownLink
                                                    v-if="
                                                        $page.props.jetstream
                                                            .hasApiFeatures
                                                    "
                                                    :href="
                                                        route(
                                                            'api.index-tokens.index'
                                                        )
                                                    "
                                                >
                                                    API Tokens
                                                </DropdownLink>

                                                <div
                                                    class="border-t border-gray-100"
                                                />

                                                <!-- Authentication -->
                                                <form @submit.prevent="logout">
                                                    <DropdownLink as="button">
                                                        Log Out
                                                    </DropdownLink>
                                                </form>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </div>

                                <!-- Hamburger -->
                                <div class="flex items-center xl:hidden">
                                    <button
                                        class="inline-flex items-center justify-center p-2 text-gray-400 transition rounded-md hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500"
                                        @click="
                                            showingSidebar = !showingSidebar
                                        "
                                    >
                                        <svg
                                            class="w-6 h-6"
                                            stroke="currentColor"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                :class="{
                                                    hidden: showingSidebar,
                                                    'inline-flex':
                                                        !showingSidebar,
                                                }"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 6h16M4 12h16M4 18h16"
                                            />
                                            <path
                                                :class="{
                                                    hidden: !showingSidebar,
                                                    'inline-flex':
                                                        showingSidebar,
                                                }"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <main
                    class="px-4 py-10 2xl:mx-auto sm:px-6 lg:px-8 2xl:container"
                >
                    <Flash />
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
