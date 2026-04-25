<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { useForm, Link } from "@inertiajs/vue3";
import { watch } from "vue";

const props = defineProps({
    account_types: Object,
    sale_points: Array,
    banks: Array,
});

const form = useForm({
    name: "",
    type: "",
    opening_balance: 0,
    currency: "SDG",
    sale_point_id: null,
    bank_id: null,
    notes: "",
});

const submit = () => {
    form.post(route("treasury.store"));
};

const showSalePoint = () => form.type === "cash";
const showBankSelector = () => form.type === "bank";

// Auto-fill name from selected bank when type is bank
watch(() => form.bank_id, (bankId) => {
    if (!bankId || form.type !== "bank") return;
    const bank = props.banks.find(b => b.id === bankId);
    if (bank && !form.name) {
        form.name = bank.name;
    }
});
</script>

<template>
    <AppLayout :title="__('New Treasury Account')">
        <div class="max-w-2xl mx-auto">
            <!-- Page Header -->
            <div class="w-full lg:flex lg:items-center lg:justify-between mb-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ __("New Treasury Account") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __("Add a new cash drawer, bank account, or wallet.") }}
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
                        {{ form.processing ? __("Saving...") : __("Create Account") }}
                    </PrimaryButton>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">
                            {{ __("Account Details") }}
                        </h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Name -->
                        <div>
                            <InputLabel for="name" :value="__('Account Name')" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="__('e.g. Main Cash Drawer, CIB Account')"
                                autofocus
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div>
                            <InputLabel for="type" :value="__('Account Type')" />
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            >
                                <option value="">{{ __("Select type...") }}</option>
                                <option v-for="(value, label) in account_types" :key="value" :value="value">
                                    {{ __(label) }}
                                </option>
                            </select>
                            <InputError :message="form.errors.type" class="mt-2" />
                        </div>

                        <!-- Bank selector (only for bank type) -->
                        <div v-if="showBankSelector()">
                            <InputLabel for="bank_id" :value="__('Bank Institution')" />
                            <CustomSelect
                                id="bank_id"
                                v-model="form.bank_id"
                                :options="banks"
                                label="name"
                                track-by="id"
                                :placeholder="__('Select bank...')"
                                :close-on-select="true"
                                :multiple="false"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                class="mt-1 w-full"
                            />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                {{ __("Links this treasury account to a bank for automatic cheque clearing.") }}
                            </p>
                            <InputError :message="form.errors.bank_id" class="mt-2" />
                        </div>

                        <!-- Sale Point (only for cash) -->
                        <div v-if="showSalePoint()">
                            <InputLabel for="sale_point_id" :value="__('Sale Point (optional)')" />
                            <select
                                id="sale_point_id"
                                v-model="form.sale_point_id"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            >
                                <option :value="null">{{ __("Not linked to a sale point") }}</option>
                                <option v-for="sp in sale_points" :key="sp.id" :value="sp.id">
                                    {{ sp.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.sale_point_id" class="mt-2" />
                        </div>

                        <!-- Currency -->
                        <div>
                            <InputLabel for="currency" :value="__('Currency')" />
                            <TextInput
                                id="currency"
                                v-model="form.currency"
                                type="text"
                                class="mt-1 block w-full"
                                maxlength="3"
                                placeholder="SDG"
                            />
                            <InputError :message="form.errors.currency" class="mt-2" />
                        </div>

                        <!-- Opening Balance -->
                        <div>
                            <InputLabel for="opening_balance" :value="__('Opening Balance (in cents)')" />
                            <TextInput
                                id="opening_balance"
                                v-model="form.opening_balance"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                                placeholder="0"
                            />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                {{ __("Enter the current balance in the smallest currency unit (e.g. 10000 = 100.00)") }}
                            </p>
                            <InputError :message="form.errors.opening_balance" class="mt-2" />
                        </div>

                        <!-- Notes -->
                        <div>
                            <InputLabel for="notes" :value="__('Notes (optional)')" />
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="3"
                                class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                :placeholder="__('Account number, branch, or any other notes...')"
                            ></textarea>
                            <InputError :message="form.errors.notes" class="mt-2" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
