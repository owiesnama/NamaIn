<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { useForm, Link } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    accounts: Array,
});

const form = useForm({
    from_account_id: "",
    to_account_id: "",
    amount: "",
    notes: "",
});

const fromAccount = computed(() =>
    props.accounts.find((a) => a.id == form.from_account_id)
);

const availableToAccounts = computed(() =>
    props.accounts.filter((a) => a.id != form.from_account_id)
);

const formatBalance = (amount, currency = "SDG") => {
    const validCurrency =
        currency && /^[A-Z]{3}$/.test(currency) ? currency : "SDG";
    return new Intl.NumberFormat(window.lang === "ar" ? "ar-SA" : "en-US", {
        style: "currency",
        currency: validCurrency,
    }).format(amount / 100);
};

const submit = () => {
    form.post(route("treasury.transfer.store"));
};
</script>

<template>
    <AppLayout :title="__('Transfer Between Accounts')">
        <div class="max-w-2xl mx-auto">
            <!-- Page Header -->
            <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ __("Transfer Between Accounts") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __("Move money between your treasury accounts.") }}
                    </p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-x-3 lg:mt-0">
                    <Link
                        :href="route('treasury.index')"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-200"
                    >
                        {{ __("Cancel") }}
                    </Link>
                    <PrimaryButton @click="submit" :disabled="form.processing">
                        <svg v-if="form.processing" class="animate-spin h-4 w-4 ltr:mr-2 rtl:ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ form.processing ? __("Processing...") : __("Execute Transfer") }}
                    </PrimaryButton>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
                            {{ __("Transfer Details") }}
                        </h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- From -->
                        <div>
                            <InputLabel for="from_account_id" :value="__('From Account')" />
                            <select
                                id="from_account_id"
                                v-model="form.from_account_id"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            >
                                <option value="">{{ __("Select source account...") }}</option>
                                <option v-for="account in accounts" :key="account.id" :value="account.id">
                                    {{ account.name }} ({{ account.type_label }}) — {{ formatBalance(account.current_balance, account.currency) }}
                                </option>
                            </select>
                            <InputError :message="form.errors.from_account_id" class="mt-2" />
                            <!-- Available balance -->
                            <p v-if="fromAccount" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                {{ __("Available:") }} <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatBalance(fromAccount.current_balance, fromAccount.currency) }}</span>
                            </p>
                        </div>

                        <!-- To -->
                        <div>
                            <InputLabel for="to_account_id" :value="__('To Account')" />
                            <select
                                id="to_account_id"
                                v-model="form.to_account_id"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                :disabled="!form.from_account_id"
                            >
                                <option value="">{{ __("Select destination account...") }}</option>
                                <option v-for="account in availableToAccounts" :key="account.id" :value="account.id">
                                    {{ account.name }} ({{ account.type_label }})
                                </option>
                            </select>
                            <InputError :message="form.errors.to_account_id" class="mt-2" />
                        </div>

                        <!-- Amount -->
                        <div>
                            <InputLabel for="amount" :value="__('Amount (in cents)')" />
                            <input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                min="1"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600"
                                placeholder="0"
                            />
                            <InputError :message="form.errors.amount" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div>
                            <InputLabel for="notes" :value="__('Notes (optional)')" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                :placeholder="__('Reason for transfer...')"
                            ></textarea>
                            <InputError :message="form.errors.notes" class="mt-2" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
