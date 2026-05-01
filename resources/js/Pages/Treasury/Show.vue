<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import Pagination from "@/Shared/Pagination.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { usePermissions } from "@/Composables/usePermissions";
import { useDate } from '@/Composables/useDate';

const { can } = usePermissions();

const props = defineProps({
    account: Object,
    movements: Object,
});

const showAdjustModal = ref(false);

const adjustForm = useForm({
    new_balance: props.account.current_balance,
    notes: "",
});

const submitAdjustment = () => {
    adjustForm.post(route("treasury.adjust", props.account.id), {
        onSuccess: () => {
            showAdjustModal.value = false;
            adjustForm.reset("notes");
        },
    });
};

const formatBalance = (amount, currency = "SDG") => {
    const validCurrency =
        currency && /^[A-Z]{3}$/.test(currency) ? currency : "SDG";
    return new Intl.NumberFormat(window.lang === "ar" ? "ar-SA" : "en-US", {
        style: "currency",
        currency: validCurrency,
    }).format(amount / 100);
};

const { formatDate } = useDate();

const reasonLabels = {
    payment_received: "Payment Received",
    payment_refunded: "Payment Refunded",
    expense_paid: "Expense Paid",
    transfer_in: "Transfer In",
    transfer_out: "Transfer Out",
    pos_opening_float: "POS Opening Float",
    pos_closing_float: "POS Closing Float",
    cheque_deposited: "Cheque Deposited",
    cheque_bounced: "Cheque Bounced",
    manual_adjustment: "Manual Adjustment",
};

const typeBgClass = (type) => {
    const colors = {
        cash: "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800",
        bank: "bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800",
        mobile_money: "bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-900/20 dark:text-violet-400 dark:border-violet-800",
        cheque_clearing: "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800",
    };
    return colors[type] ?? colors.cash;
};
</script>

<template>
    <AppLayout :title="account.name">
        <!-- Page Header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
            <div>
                <div class="flex items-center gap-x-3">
                    <Link :href="route('treasury.index')" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 rtl:rotate-180">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">{{ account.name }}</h2>
                    <span :class="['inline-flex items-center px-2.5 py-0.5 text-[10px] font-bold rounded-full border', typeBgClass(account.type)]">
                        {{ __(account.type_label) }}
                    </span>
                </div>
                <p v-if="account.sale_point_name" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ account.sale_point_name }}
                </p>
            </div>
            <div class="mt-4 flex items-center justify-end gap-x-3 lg:mt-0">
                <button
                    v-if="can('treasury.adjust')"
                    @click="showAdjustModal = true"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 ltr:mr-2 rtl:ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    {{ __("Adjust Balance") }}
                </button>
                <Link
                    v-if="can('treasury.transfer')"
                    :href="route('treasury.transfer.create')"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 ltr:mr-2 rtl:ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                    {{ __("Transfer") }}
                </Link>
            </div>
        </div>

        <!-- Balance Hero -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mb-6">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">
                {{ __("Current Balance") }}
            </p>
            <p class="text-4xl font-bold text-gray-900 dark:text-white">
                {{ formatBalance(account.current_balance, account.currency) }}
            </p>
            <p v-if="account.notes" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ account.notes }}</p>
        </div>

        <!-- Movement Ledger -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __("Movement Ledger") }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/40">
                        <tr>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Date") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Reason") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Notes") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Amount") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("Balance After") }}</th>
                            <th class="px-6 py-4 text-start text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">{{ __("By") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/60 dark:divide-gray-700/60 bg-white dark:bg-gray-900">
                        <tr
                            v-for="movement in movements.data"
                            :key="movement.id"
                            class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition-all duration-200"
                        >
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(movement.occurred_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-0.5 text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-md">
                                    {{ __(reasonLabels[movement.reason] ?? movement.reason) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                {{ movement.notes ?? "-" }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                <span :class="movement.amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    {{ movement.amount >= 0 ? "+" : "" }}{{ formatBalance(movement.amount, account.currency) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ formatBalance(movement.balance_after, account.currency) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ movement.created_by?.name ?? "-" }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="!movements.data.length" class="py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                {{ __("No movements recorded yet.") }}
            </div>

            <div v-if="movements.links.length > 3" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center">
                <Pagination :links="movements.links" />
            </div>
        </div>

        <!-- Adjust Balance Modal -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showAdjustModal" class="fixed inset-0 z-50 bg-gray-500/20 dark:bg-gray-900/60 backdrop-blur-sm" @click="showAdjustModal = false" />
        </Transition>

        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <div v-if="showAdjustModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6 w-full max-w-md" @click.stop>
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __("Adjust Balance") }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __("Current balance:") }} {{ formatBalance(account.current_balance, account.currency) }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                                {{ __("New Balance (in cents)") }}
                            </label>
                            <input
                                v-model="adjustForm.new_balance"
                                type="number"
                                min="0"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            />
                            <p v-if="adjustForm.errors.new_balance" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ adjustForm.errors.new_balance }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                                {{ __("Reason for adjustment") }} <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="adjustForm.notes"
                                rows="3"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                :placeholder="__('Explain why you are adjusting this balance...')"
                            ></textarea>
                            <p v-if="adjustForm.errors.notes" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ adjustForm.errors.notes }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-x-3">
                        <button
                            @click="showAdjustModal = false"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        >
                            {{ __("Cancel") }}
                        </button>
                        <button
                            @click="submitAdjustment"
                            :disabled="adjustForm.processing"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        >
                            {{ adjustForm.processing ? __("Saving...") : __("Apply Adjustment") }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>
