<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";
import { useDate } from '@/Composables/useDate';

defineProps({
    payment: Object,
});

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG');

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const { formatDate } = useDate();
</script>

<template>
    <AppLayout :title="__('Payment Details')">
        <section>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <Link :href="route('payments.index')" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        {{ __("Back to List") }}
                    </Link>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                        {{ __("Payment") }} #{{ payment.id }}
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Payment Information") }}</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Amount") }}</label>
                                <p class="text-2xl font-black text-gray-900 dark:text-white">{{ formatCurrency(payment.amount, payment.currency) }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Date") }}</label>
                                <p class="text-lg font-bold text-gray-700 dark:text-gray-200">{{ formatDate(payment.paid_at) }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Payment Method") }}</label>
                                <span class="px-3 py-1 text-xs font-bold rounded-full inline-block mt-1 text-emerald-700 bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    {{ __(payment.payment_method?.charAt(0).toUpperCase() + payment.payment_method?.slice(1)) }}
                                </span>
                            </div>
                            <div v-if="payment.reference">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Reference Number") }}</label>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ payment.reference }}</p>
                            </div>
                            <div v-if="payment.metadata?.bank_name">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Bank Name") }}</label>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ payment.metadata.bank_name }}</p>
                            </div>
                            <div v-if="payment.receipt_path">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Receipt") }}</label>
                                <a :href="'/storage/' + payment.receipt_path" target="_blank" class="text-sm font-bold text-emerald-600 hover:underline">
                                    {{ __("View Receipt") }}
                                </a>
                            </div>
                            <div v-if="payment.payable">
                                <label class="text-xs font-bold text-gray-400 uppercase tracking-widest block mb-1">
                                    {{ payment.payable_type?.split('\\').pop() === 'Customer' ? __("Customer") : __("Supplier") }}
                                </label>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ payment.payable.name }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="payment.notes" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Notes") }}</h3>
                        </div>
                        <div class="p-6 text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                            {{ payment.notes }}
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-none">
                        <div class="p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ __("Relations") }}</h3>
                        </div>
                        <div class="p-6 space-y-6">
                            <div v-if="payment.invoice">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Invoice") }}</label>
                                <Link :href="route('invoices.show', payment.invoice.id)" class="text-sm font-bold text-emerald-600 hover:underline">
                                    #{{ payment.invoice.serial_number }}
                                </Link>
                                <p class="text-xs text-gray-500" v-if="payment.invoice.invocable">
                                    {{ payment.invoice.invocable.name }}
                                </p>
                            </div>

                            <div v-if="payment.created_by">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">{{ __("Created By") }}</label>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ payment.created_by?.name }}</p>
                                <p class="text-xs text-gray-500">{{ formatDate(payment.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
