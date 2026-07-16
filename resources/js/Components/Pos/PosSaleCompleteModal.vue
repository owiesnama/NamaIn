<script setup>
import Modal from "@/Components/Modal.vue";

defineProps({
    show: Boolean,
    sale: Object,
    currency: String,
    paymentMethods: Array,
});

const emit = defineEmits(['new-sale', 'print']);

const fmt = (val) => window.formatMoney(val);
</script>

<template>
    <Modal :show="show" @close="emit('new-sale')">
        <div class="p-6 text-center">
            <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                <svg class="w-7 h-7 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('Sale Completed') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Invoice') }} <span class="font-semibold text-gray-800 dark:text-gray-200">#{{ sale.invoiceId }}</span></p>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 mb-4 space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total') }}</span>
                    <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ fmt(sale.total) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Payment Method') }}</span>
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ paymentMethods.find(m => m.value === sale.paymentMethod)?.label ?? sale.paymentMethod }}
                    </span>
                </div>
                <div v-if="sale.change !== null" class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ __('Change Due') }}</span>
                    <span class="text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ fmt(sale.change) }}</span>
                </div>
            </div>
            <div class="flex gap-x-3">
                <button class="flex-1 inline-flex items-center justify-center gap-x-2 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" @click="emit('print')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                    {{ __('Print Receipt') }}
                </button>
                <button class="flex-1 py-2.5 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" @click="emit('new-sale')">{{ __('New Sale') }}</button>
            </div>
        </div>
    </Modal>
</template>
