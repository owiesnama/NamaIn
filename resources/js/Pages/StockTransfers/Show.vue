<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import { useDate } from '@/Composables/useDate';

defineProps({
    transfer: Object
});

const { formatDate } = useDate();
</script>

<template>
    <AppLayout :title="__('Stock Transfer Details')">
        <div class="max-w-4xl mx-auto">
            <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
                <div>
                    <div class="flex items-center gap-x-3">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                            {{ __("Stock Transfer") }} #{{ transfer.id }}
                        </h2>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __("Detailed record of the inventory movement.") }}</p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-x-4 lg:mt-0">
                    <a :href="route('stock-transfers.print', transfer.id)" target="_blank" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ltr:mr-2 rtl:ml-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                        </svg>
                        {{ __("Print") }}
                    </a>
                    <Link :href="route('stock-transfers.index')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 ltr:mr-2 rtl:ml-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        {{ __("Back to Index") }}
                    </Link>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden mb-8">
                <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700 rtl:divide-x-reverse">
                    <div class="p-6">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("From") }}</span>
                        <div class="flex items-center gap-x-2 mt-2">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615 3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615V9.349m-11.25 0a4.5 4.5 0 0 1-4.477-4.451m0 0A4.486 4.486 0 0 1 3.75 4.5M3.75 21H3.75m0 0a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h16.5a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3M12 9.75V21" />
                                </svg>
                            </div>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ transfer.from_storage?.name }}</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("To") }}</span>
                        <div class="flex items-center gap-x-2 mt-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615 3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615V9.349m-11.25 0a4.5 4.5 0 0 1-4.477-4.451m0 0A4.486 4.486 0 0 1 3.75 4.5M3.75 21H3.75m0 0a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h16.5a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3M12 9.75V21" />
                                </svg>
                            </div>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ transfer.to_storage?.name }}</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Transferred At") }}</span>
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ formatDate(transfer.transferred_at) }}</p>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex flex-wrap gap-8 bg-gray-50/30 dark:bg-gray-800/20">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Created By") }}</span>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ transfer.creator?.name }}</p>
                    </div>
                    <div v-if="transfer.notes" class="flex-grow">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __("Notes") }}</span>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ transfer.notes }}</p>
                    </div>
                </div>
            </div>

            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 17.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                {{ __("Transferred Items") }}
            </h3>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Product") }}</th>
                            <th class="px-6 py-4 text-end text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Quantity") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr v-for="line in transfer.lines" :key="line.id" class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700 dark:text-gray-300">{{ line.product?.name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-end font-bold text-gray-900 dark:text-white">{{ line.quantity }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
