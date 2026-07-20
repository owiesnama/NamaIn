<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    show: Boolean,
    total: Number,
    discountAmount: Number,
    processing: Boolean,
    currency: String,
});

const emit = defineEmits(['close', 'confirm']);

const fmt = (val) => window.formatMoney(val);

const selectedPaymentMethod = ref('cash');
const cashTendered = ref('');

const changeAmount = computed(() => {
    if (selectedPaymentMethod.value !== 'cash') return null;
    const tendered = parseFloat(cashTendered.value) || 0;
    return tendered > 0 ? Math.max(0, tendered - props.total) : null;
});

// Shortcut cash amounts: the exact total plus the next round-up for common
// denominations, so cashiers can set the tendered amount in a single tap.
const quickTenderOptions = computed(() => {
    const total = props.total || 0;
    if (total <= 0) return [];
    const roundUpTo = (step) => Math.ceil(total / step) * step;
    const rounded = [roundUpTo(5), roundUpTo(10), roundUpTo(50)]
        .filter((value) => value > total);
    return [...new Set(rounded)].slice(0, 3);
});

const setCashTendered = (value) => {
    cashTendered.value = String(value);
};

const paymentMethods = [
    { value: 'cash', label: __('Cash'), icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />`, color: 'emerald' },
    { value: 'bank_transfer', label: __('Bank Transfer'), icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />`, color: 'blue' },
    { value: 'credit', label: __('Credit'), icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />`, color: 'orange' },
];

const methodColorMap = {
    emerald: { active: 'bg-emerald-600 border-emerald-600 text-white', inactive: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-emerald-300', icon: 'text-white', inactiveIcon: 'text-emerald-500' },
    blue: { active: 'bg-blue-600 border-blue-600 text-white', inactive: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-blue-300', icon: 'text-white', inactiveIcon: 'text-blue-500' },
    orange: { active: 'bg-orange-500 border-orange-500 text-white', inactive: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-orange-300', icon: 'text-white', inactiveIcon: 'text-orange-500' },
};

const confirm = () => {
    emit('confirm', {
        paymentMethod: selectedPaymentMethod.value,
        change: changeAmount.value,
    });
};

const reset = () => {
    cashTendered.value = '';
    selectedPaymentMethod.value = 'cash';
};

defineExpose({ reset, selectedPaymentMethod, changeAmount });
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-50 flex items-end lg:items-center justify-center px-4 pb-4 lg:pb-0">
                <div class="fixed inset-0 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" />
                <Transition enter-active-class="ease-out duration-200" enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to-class="opacity-100 translate-y-0 sm:scale-100">
                    <div v-if="show" class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-sm p-6">
                        <div class="text-center mb-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ __('Total') }}</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-white">{{ fmt(total) }}</p>
                            <p v-if="discountAmount > 0" class="text-xs text-emerald-600 mt-1">{{ __('Includes discount of') }} {{ fmt(discountAmount) }}</p>
                        </div>
                        <div class="mb-5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">{{ __('Payment Method') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="method in paymentMethods" :key="method.value" type="button"
                                    class="flex flex-col items-center gap-y-2 py-4 px-2 rounded-xl border-2 font-semibold text-sm transition-all duration-150"
                                    :class="selectedPaymentMethod === method.value ? methodColorMap[method.color].active : methodColorMap[method.color].inactive"
                                    @click="selectedPaymentMethod = method.value; cashTendered = ''"
                                >
                                    <svg class="h-6 w-6" :class="selectedPaymentMethod === method.value ? methodColorMap[method.color].icon : methodColorMap[method.color].inactiveIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" v-html="method.icon" />
                                    </svg>
                                    <span class="text-xs leading-tight text-center">{{ method.label }}</span>
                                </button>
                            </div>
                        </div>
                        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                            <div v-if="selectedPaymentMethod === 'cash'" class="mb-5 space-y-3">
                                <div>
                                    <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5 block">{{ __('Amount Tendered') }}</label>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="min-h-[44px] px-3 py-2 text-sm font-semibold rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500" @click="setCashTendered(total)">{{ __('Exact') }}</button>
                                        <button v-for="amount in quickTenderOptions" :key="amount" type="button" class="min-h-[44px] px-3 py-2 text-sm font-semibold rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500" @click="setCashTendered(amount)">{{ fmt(amount) }}</button>
                                    </div>
                                    <input v-model="cashTendered" type="number" inputmode="decimal" min="0" step="0.01" :placeholder="fmt(total)" class="w-full px-4 py-3 text-lg font-semibold text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                                </div>
                                <div v-if="changeAmount !== null" class="flex items-center justify-between px-4 py-3 rounded-xl" :class="changeAmount >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                                    <span class="text-sm font-semibold" :class="changeAmount >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ __('Change Due') }}</span>
                                    <span class="text-xl font-bold" :class="changeAmount >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">{{ fmt(changeAmount) }}</span>
                                </div>
                            </div>
                        </Transition>
                        <div class="flex gap-x-3">
                            <button type="button" class="flex-1 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" @click="emit('close')">{{ __('Cancel') }}</button>
                            <button type="button" class="flex-1 py-3 text-sm font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" :disabled="processing" @click="confirm">
                                <span v-if="processing" class="flex items-center justify-center gap-x-2">
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    {{ __('Processing...') }}
                                </span>
                                <span v-else>{{ __('Confirm Payment') }}</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
