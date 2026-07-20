<script setup>
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    plans: Array,
    catalog: Array,
});

const destroy = (plan) => {
    if (plan.subscriptions_count > 0) {
        return;
    }
    if (window.confirm(__('Delete this plan?'))) {
        router.delete(route('admin.plans.destroy', plan.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AdminLayout :title="__('Plans')">
        <div class="max-w-5xl mx-auto px-4 py-6 space-y-6">
            <div class="lg:flex lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ __('Plans') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Subscription plans and the features each includes.') }}
                    </p>
                </div>
                <Link
                    :href="route('admin.plans.create')"
                    class="mt-4 lg:mt-0 inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    {{ __('New plan') }}
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                            <tr>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Plan') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Key') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Status') }}</th>
                                <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __('Subscribers') }}</th>
                                <th class="px-6 py-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                            <tr v-for="plan in plans" :key="plan.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200 font-medium">
                                    {{ plan.display_name }}
                                    <span v-if="plan.is_default" class="ms-2 px-2 py-0.5 text-[10px] font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">{{ __('Default') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ plan.key }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span :class="plan.is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500'">
                                        {{ plan.is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ plan.subscriptions_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-end text-sm">
                                    <Link :href="route('admin.plans.edit', plan.id)" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300">{{ __('Edit') }}</Link>
                                    <button
                                        type="button"
                                        @click="destroy(plan)"
                                        :disabled="plan.subscriptions_count > 0"
                                        class="ms-4 text-red-600 dark:text-red-400 hover:text-red-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!plans.length">
                                <td colspan="5" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('No plans yet.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
