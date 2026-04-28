<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import WarningAlert from "@/Components/WarningAlert.vue";
import { Link } from "@inertiajs/vue3";
import { usePermissions } from "@/Composables/usePermissions";

const { can } = usePermissions();

defineProps({
    cash_drawers: Array,
    shared_accounts: Array,
    missing_types: Array,
});

const missingTypeLabels = {
    cash: __('Cash'),
    bank: __('Bank'),
    cheque_clearing: __('Cheque Clearing'),
};

const formatBalance = (amount, currency = "SDG") => {
    const validCurrency =
        currency && /^[A-Z]{3}$/.test(currency) ? currency : "SDG";
    return new Intl.NumberFormat(window.lang === "ar" ? "ar-SA" : "en-US", {
        style: "currency",
        currency: validCurrency,
    }).format(amount / 100);
};

const formatDate = (dateString) => {
    if (!dateString) return null;
    return new Intl.DateTimeFormat(window.lang === "ar" ? "ar-SA" : "en-US", {
        dateStyle: "medium",
    }).format(new Date(dateString));
};

const typeColors = {
    cash: "emerald",
    bank: "blue",
    mobile_money: "violet",
    cheque_clearing: "amber",
};

const typeBgClass = (type) => {
    const colors = {
        cash: "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800",
        bank: "bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800",
        mobile_money:
            "bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-900/20 dark:text-violet-400 dark:border-violet-800",
        cheque_clearing:
            "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800",
    };
    return colors[type] ?? colors.cash;
};
</script>

<template>
    <AppLayout :title="__('Treasury')">
        <!-- Page Header -->
        <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
            <div>
                <div class="flex items-center gap-x-3">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ __("Treasury") }}
                    </h2>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full text-emerald-700 bg-emerald-100/60 dark:bg-gray-800 dark:text-emerald-400">
                        {{ cash_drawers.length + shared_accounts.length }} {{ __("Accounts") }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __("Track your cash, bank, and mobile money balances in one place.") }}
                </p>
            </div>
            <div class="mt-4 flex items-center justify-end gap-x-3 lg:mt-0">
                <Link
                    v-if="can('treasury.transfer')"
                    :href="route('treasury.transfer.create')"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 ltr:mr-2 rtl:ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                    {{ __("Transfer") }}
                </Link>
                <Link
                    v-if="can('treasury.create')"
                    :href="route('treasury.create')"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 ltr:mr-2 rtl:ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __("New Account") }}
                </Link>
            </div>
        </div>

        <!-- Missing account types warning -->
        <div v-if="missing_types && missing_types.length" class="mb-6">
            <WarningAlert
                :title="__('Treasury setup incomplete')"
                :action-label="can('treasury.create') ? __('Create missing accounts') : ''"
                :action-href="can('treasury.create') ? route('treasury.create') : ''"
            >
                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">
                    {{ __("The following account types are missing:") }}
                    <strong>{{ missing_types.map(t => missingTypeLabels[t] || t).join(', ') }}</strong>.
                    {{ __("Payments and cheque operations require these accounts to record transactions correctly.") }}
                </p>
            </WarningAlert>
        </div>

        <!-- Cash Drawers (per sale point) -->
        <div v-if="cash_drawers.length" class="mb-8">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-4">
                {{ __("Sale Point Cash Drawers") }}
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="account in cash_drawers"
                    :key="account.id"
                    :href="route('treasury.show', account.id)"
                    class="block bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 hover:shadow-sm hover:border-emerald-200 dark:hover:border-emerald-800 transition-all duration-200 group"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-emerald-600 dark:text-emerald-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                            </svg>
                        </div>
                        <div :class="['inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-md border', typeBgClass(account.type)]">
                            {{ __(account.type_label) }}
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                        {{ account.name }}
                    </p>
                    <p v-if="account.sale_point_name" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ account.sale_point_name }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-4">
                        {{ formatBalance(account.current_balance, account.currency) }}
                    </p>
                    <p v-if="account.last_movement_at" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        {{ __("Last activity") }}: {{ formatDate(account.last_movement_at) }}
                    </p>
                </Link>
            </div>
        </div>

        <!-- Shared Accounts (bank, mobile money, cheques) -->
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-4">
                {{ __("Shared Accounts") }}
            </p>
            <div v-if="shared_accounts.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="account in shared_accounts"
                    :key="account.id"
                    :href="route('treasury.show', account.id)"
                    class="block bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 hover:shadow-sm hover:border-emerald-200 dark:hover:border-emerald-800 transition-all duration-200 group"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <!-- Bank icon -->
                            <svg v-if="account.type === 'bank'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-gray-500 dark:text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />
                            </svg>
                            <!-- Mobile money icon -->
                            <svg v-else-if="account.type === 'mobile_money'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-gray-500 dark:text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 15.75h3" />
                            </svg>
                            <!-- Cheque icon -->
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-gray-500 dark:text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div :class="['inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-md border', typeBgClass(account.type)]">
                            {{ __(account.type_label) }}
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                        {{ account.name }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-4">
                        {{ formatBalance(account.current_balance, account.currency) }}
                    </p>
                    <p v-if="account.last_movement_at" class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        {{ __("Last activity") }}: {{ formatDate(account.last_movement_at) }}
                    </p>
                </Link>
            </div>
            <div v-else class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl py-12 text-center">
                <p class="text-sm text-gray-400 dark:text-gray-500">
                    {{ __("No shared accounts yet.") }}
                </p>
                <Link v-if="can('treasury.create')" :href="route('treasury.create')" class="inline-flex items-center mt-4 text-sm text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium">
                    {{ __("Create your first account") }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
