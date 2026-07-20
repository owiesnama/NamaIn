<script setup>
    import { computed, ref } from "vue";
    import { router, Link } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import Ltr from "@/Components/Ltr.vue";

    const props = defineProps({
        plans: Array,
        catalog: Array,
    });

    const search = ref("");
    const status = ref("all");

    const filteredPlans = computed(() => {
        const term = search.value.trim().toLowerCase();

        return props.plans.filter((plan) => {
            const matchesTerm =
                ! term ||
                plan.display_name?.toLowerCase().includes(term) ||
                plan.key?.toLowerCase().includes(term);
            const matchesStatus =
                status.value === "all" ||
                (status.value === "active" ? plan.is_active : ! plan.is_active);

            return matchesTerm && matchesStatus;
        });
    });

    const destroy = (plan) => {
        if (plan.subscriptions_count > 0) {
            return;
        }
        if (window.confirm(__("Delete this plan?"))) {
            router.delete(route("admin.plans.destroy", plan.id), { preserveScroll: true });
        }
    };
</script>

<template>
    <AdminLayout :title="__('Plans')">
        <div class="space-y-6">
            <!-- Page header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Plans') }}</h2>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                            <Ltr>{{ plans.length }}</Ltr> {{ __('Total') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Subscription plans and the features each includes.') }}
                    </p>
                </div>
                <Link
                    :href="route('admin.plans.create')"
                    class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('New plan') }}
                </Link>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[240px] max-w-sm">
                    <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400 dark:text-gray-500 pointer-events-none">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="__('Search plans…')"
                        class="w-full ps-9 pe-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                    />
                </div>
                <div class="relative">
                    <select
                        v-model="status"
                        class="appearance-none ps-8 pe-8 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 cursor-pointer"
                    >
                        <option value="all">{{ __('All statuses') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                    <span class="absolute inset-y-0 start-2 flex items-center text-gray-400 dark:text-gray-500 pointer-events-none">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Plan') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Key') }}</th>
                                <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Subscribers') }}</th>
                                <th class="px-6 py-4 text-center text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Features') }}</th>
                                <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                            <tr
                                v-for="plan in filteredPlans"
                                :key="plan.id"
                                class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-x-2">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ plan.display_name }}</span>
                                        <span v-if="plan.is_default" class="px-2 py-0.5 text-[10px] font-bold rounded-full text-emerald-700 bg-emerald-50 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800">
                                            {{ __('Default') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-mono rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700" dir="ltr">
                                        {{ plan.key }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-full"
                                        :class="plan.is_active
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500'"
                                    >
                                        {{ plan.is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-white">
                                    <Ltr>{{ plan.subscriptions_count }}</Ltr>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-xs text-gray-500 dark:text-gray-400">
                                    <Ltr>{{ plan.features_count }}</Ltr> {{ __('features') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-end">
                                    <div class="flex items-center justify-end gap-x-1">
                                        <Link
                                            :href="route('admin.plans.edit', plan.id)"
                                            class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                                            :title="__('Edit')"
                                        >
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </Link>
                                        <button
                                            type="button"
                                            :disabled="plan.subscriptions_count > 0"
                                            class="inline-flex items-center justify-center p-2 text-red-400 hover:text-red-600 dark:hover:text-red-300 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-red-400"
                                            :title="plan.subscriptions_count > 0 ? __('This plan has subscriptions and cannot be deleted.') : __('Delete')"
                                            @click="destroy(plan)"
                                        >
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="filteredPlans.length === 0" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ plans.length ? __('No plans match your filters.') : __('No plans yet.') }}
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
