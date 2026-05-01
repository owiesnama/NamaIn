<script setup>
    import { computed } from "vue";
    import { Link } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import {
        Chart as ChartJS,
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        Title,
        Legend,
        Tooltip as ChartTooltip,
        Filler,
    } from 'chart.js';
    import { Line } from 'vue-chartjs';

    ChartJS.register(
        CategoryScale,
        LinearScale,
        PointElement,
        LineElement,
        Title,
        ChartTooltip,
        Legend,
        Filler,
    );

    const props = defineProps({
        stats: Object,
        mostActiveTenants: Array,
        registrationTrends: Object,
    });

    const chartData = computed(() => ({
        labels: props.registrationTrends.labels,
        datasets: [
            {
                label: __('Tenants'),
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderColor: '#10b981',
                data: props.registrationTrends.tenants,
                tension: 0.3,
                fill: true,
                pointRadius: 2,
            },
            {
                label: __('Users'),
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderColor: '#6366f1',
                data: props.registrationTrends.users,
                tension: 0.3,
                fill: true,
                pointRadius: 2,
            },
        ],
    }));

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    callback: (value) => Number.isInteger(value) ? value : null,
                },
            },
        },
    };
</script>

<template>
    <AdminLayout :title="__('Dashboard')">
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Dashboard') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Platform overview') }}</p>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Tenants -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.totalTenants }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total Tenants') }}</p>
                    </div>
                </div>
            </div>

            <!-- Active Tenants -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.activeTenants }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Active') }}</p>
                    </div>
                </div>
            </div>

            <!-- Inactive Tenants -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.inactiveTenants }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Inactive') }}</p>
                    </div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.totalUsers }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total Users') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Widgets Row -->
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Registration Trends Chart -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Registration Trends') }}</h3>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('Last 30 days') }}</p>
                </div>
                <div class="p-6">
                    <div class="h-72">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <!-- Most Active Tenants -->
            <div class="lg:col-span-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Most Active Tenants') }}</h3>
                </div>
                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Tenant') }}</th>
                                <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Invoices') }}</th>
                                <th class="px-4 py-3 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Users') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60">
                            <tr
                                v-for="(tenant, index) in mostActiveTenants"
                                :key="tenant.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                            >
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <Link
                                        :href="route('admin.tenants.show', tenant.id)"
                                        class="flex items-center gap-x-2"
                                    >
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                            {{ index + 1 }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400">
                                            {{ tenant.name }}
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ tenant.invoices_count }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ tenant.users_count }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="!mostActiveTenants.length" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ __('No active tenants yet.') }}
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
