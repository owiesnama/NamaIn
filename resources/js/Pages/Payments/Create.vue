<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import FileUploader from "@/Components/FileUploader.vue";
import WarningAlert from "@/Components/WarningAlert.vue";
import { useForm, Link } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";

const props = defineProps({
    customers: Array,
    suppliers: Array,
    banks: Array,
    payment_methods: Object,
    treasury_accounts: Array,
});

const selectedType = ref("customer"); // 'customer' or 'supplier'
const selectedEntity = ref(null);
const selectedInvoice = ref(null);

const selectedEntityType = ref('customer');
const selectedEntityObject = ref(null);
const selectedInvoiceObject = ref(null);
const selectedPaymentMethod = ref('cash');
const selectedChequeBank = ref(null);

const addBank = (newTag) => {
    const bank = { name: newTag, id: newTag };
    selectedChequeBank.value = bank;
    form.cheque_bank_id = newTag;
};

const entities = computed(() => {
    return selectedType.value === "customer" ? props.customers : props.suppliers;
});

const unpaidInvoices = computed(() => {
    if (!selectedEntity.value) return [];
    const entity = entities.value.find(
        (e) => e.id === selectedEntity.value
    );
    return entity?.invoices?.filter(
        (inv) =>
            inv.payment_status === "unpaid" ||
            inv.payment_status === "partially_paid"
    ) || [];
});

const selectedInvoiceData = computed(() => {
    if (!selectedInvoice.value) return null;
    return unpaidInvoices.value.find((inv) => inv.id === selectedInvoice.value);
});

const form = useForm({
    invoice_id: null,
    payable_id: null,
    payable_type: null,
    amount: 0,
    paid_at: new Date().toISOString().slice(0, 10),
    payment_method: "cash",
    direction: "in",
    treasury_account_id: null,
    reference: "",
    notes: "",

    // Bank Transfer
    bank_name: "",
    receipt: null,

    // Cheque
    cheque_bank_id: null,
    cheque_due_date: "",
    cheque_number: "",
});

const methodToAccountType = {
    cash: 'cash',
    bank_transfer: 'bank',
    cheque: 'cheque_clearing',
};

const filteredTreasuryAccounts = computed(() => {
    const type = methodToAccountType[form.payment_method];
    if (!type) return [];
    return props.treasury_accounts.filter(a => a.type === type);
});

const selectedTreasuryAccount = ref(null);

// Clear treasury selection when payment method changes type
watch(() => form.payment_method, () => {
    selectedTreasuryAccount.value = null;
    form.treasury_account_id = null;
});

const formatCurrency = (amount, currency = null) => {
    const validCurrency = (currency && /^[A-Z]{3}$/.test(currency)) ? currency :
        (selectedInvoiceData.value?.currency && /^[A-Z]{3}$/.test(selectedInvoiceData.value.currency) ? selectedInvoiceData.value.currency :
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG'));

    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: validCurrency,
    }).format(amount || 0);
};

const submit = () => {
    if (selectedInvoice.value) {
        form.invoice_id = selectedInvoice.value;
        form.payable_id = null;
        form.payable_type = null;
    } else {
        form.invoice_id = null;
        form.payable_id = selectedEntity.value;
        form.payable_type = selectedType.value === "customer" ? "App\\Models\\Customer" : "App\\Models\\Supplier";
    }
    form.post(route("payments.store"));
};
</script>

<template>
    <AppLayout :title="__('Record Payment')">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            {{ __("Record Payment") }}
        </h2>

        <form
            class="mt-6 flex flex-col lg:flex-row gap-8"
            @submit.prevent="submit"
        >
            <div class="flex-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-8">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <!-- Select Type -->
                <div>
                    <InputLabel for="type" :value="__('Payment For')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <CustomSelect
                        v-model="selectedEntityType"
                        :options="[{ id: 'customer', label: 'Customer' }, { id: 'supplier', label: 'Supplier' }]"
                        :multiple="false"
                        :close-on-select="true"
                        :placeholder="__('Select Type')"
                        label="label"
                        track-by="id"
                        class="w-full"
                        :select-label="''"
                        :deselect-label="''"
                        :selected-label="__('Selected')"
                        @update:model-value="(val) => { selectedType = val || 'customer'; selectedEntity = null; selectedInvoice = null; selectedEntityObject = null; selectedInvoiceObject = null; }"
                    >
                        <template #singleLabel="{ option }">
                            {{ __(option.label) }}
                        </template>
                        <template #option="{ option }">
                            {{ __(option.label) }}
                        </template>
                    </CustomSelect>
                </div>

                <!-- Select Customer/Supplier -->
                <div>
                    <InputLabel for="entity" :value="selectedType === 'customer' ? __('Customer') : __('Supplier')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <CustomSelect
                        v-model="selectedEntityObject"
                        :options="entities"
                        :multiple="false"
                        :close-on-select="true"
                        :placeholder="selectedType === 'customer' ? __('Select Customer') : __('Select Supplier')"
                        label="name"
                        track-by="id"
                        class="w-full"
                        :select-label="''"
                        :deselect-label="''"
                        :selected-label="__('Selected')"
                        @update:model-value="(val) => { selectedEntity = val || null; selectedInvoice = null; selectedInvoiceObject = null; }"
                    />
                </div>

                <!-- Select Invoice -->
                <div class="sm:col-span-2">
                    <InputLabel for="invoice" :value="__('Invoice (Optional)')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <CustomSelect
                        v-model="selectedInvoiceObject"
                        :options="unpaidInvoices"
                        :multiple="false"
                        :close-on-select="true"
                        :placeholder="!selectedEntity ? __('Select entity first') : __('General Payment (No Invoice)')"
                        label="serial_number"
                        track-by="id"
                        class="w-full"
                        :select-label="''"
                        :deselect-label="''"
                        :selected-label="__('Selected')"
                        :disabled="!selectedEntity"
                        @update:model-value="(val) => { selectedInvoice = val || null; }"
                    >
                        <template #option="{ option }">
                            #{{ option.serial_number || option.id }} - {{
                                formatCurrency(option.remaining_balance, option.currency)
                            }}
                        </template>
                    </CustomSelect>
                </div>

                <!-- Invoice Details -->
                <div
                    v-if="selectedInvoiceData"
                    class="sm:col-span-2 p-6 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">{{ __("Total") }}</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(selectedInvoiceData.total, selectedInvoiceData.currency) }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">{{ __("Paid") }}</span>
                            <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(selectedInvoiceData.paid_amount, selectedInvoiceData.currency) }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-1">{{ __("Remaining Balance") }}</span>
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ formatCurrency(selectedInvoiceData.remaining_balance, selectedInvoiceData.currency) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Direction -->
                <div>
                    <InputLabel :value="__('Direction')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <div class="flex gap-x-2">
                        <button
                            type="button"
                            @click="form.direction = 'in'"
                            :class="[
                                'flex-1 inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-medium rounded-lg border transition-colors duration-200',
                                form.direction === 'in'
                                    ? 'bg-emerald-600 border-emerald-600 text-white'
                                    : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                            </svg>
                            {{ __('Incoming') }}
                        </button>
                        <button
                            type="button"
                            @click="form.direction = 'out'"
                            :class="[
                                'flex-1 inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-medium rounded-lg border transition-colors duration-200',
                                form.direction === 'out'
                                    ? 'bg-red-600 border-red-600 text-white'
                                    : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                            ]"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                            </svg>
                            {{ __('Outgoing') }}
                        </button>
                    </div>
                    <InputError class="mt-1" :message="form.errors.direction" />
                </div>

                <!-- Amount -->
                <div>
                    <InputLabel for="amount" :value="__('Amount')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <TextInput
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        class="block w-full"
                        required
                        :max="
                            selectedInvoiceData?.remaining_balance
                        "
                    />
                    <InputError class="mt-1" :message="form.errors.amount" />
                </div>

                <!-- Payment Date -->
                <div>
                    <InputLabel for="paid_at" :value="__('Payment Date')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <DatePicker
                        id="paid_at"
                        v-model="form.paid_at"
                        class="block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.paid_at" />
                </div>

                <!-- Payment Method -->
                <div class="sm:col-span-2">
                    <InputLabel
                        for="payment_method"
                        :value="__('Payment Method')"
                        class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500"
                    />
                    <CustomSelect
                        v-model="selectedPaymentMethod"
                        :options="Object.entries(payment_methods).map(([label, value]) => ({ id: value, label }))"
                        :multiple="false"
                        :close-on-select="true"
                        :placeholder="__('Select Payment Method')"
                        label="label"
                        track-by="id"
                        class="w-full"
                        :select-label="''"
                        :deselect-label="''"
                        :selected-label="__('Selected')"
                        @update:model-value="(val) => { form.payment_method = val || 'cash'; }"
                    >
                        <template #singleLabel="{ option }">
                            {{ __(option.label) }}
                        </template>
                        <template #option="{ option }">
                            {{ __(option.label) }}
                        </template>
                    </CustomSelect>
                    <InputError
                        class="mt-1"
                        :message="form.errors.payment_method"
                    />
                </div>

                <!-- Bank Transfer Details -->
                <div v-if="form.payment_method === 'bank_transfer'" class="sm:col-span-2 grid grid-cols-1 gap-6 sm:grid-cols-2 p-6 bg-emerald-50 dark:bg-emerald-900/10 rounded-lg border border-emerald-200 dark:border-emerald-800">
                    <div>
                        <InputLabel for="bank_name" :value="__('Bank Name')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                        <TextInput
                            id="bank_name"
                            v-model="form.bank_name"
                            type="text"
                            class="block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.bank_name" />
                    </div>
                    <div>
                        <InputLabel for="receipt" :value="__('Payment Receipt (Optional)')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                        <FileUploader
                            v-model="form.receipt"
                        />
                        <InputError class="mt-1" :message="form.errors.receipt" />
                    </div>
                </div>

                <!-- Cheque Details -->
                <div v-if="form.payment_method === 'cheque'" class="sm:col-span-2 grid grid-cols-1 gap-6 sm:grid-cols-3 p-6 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div>
                        <InputLabel for="cheque_bank" :value="__('Select Bank')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                        <VueMultiselect
                            v-model="selectedChequeBank"
                            :options="banks"
                            :multiple="false"
                            :close-on-select="true"
                            :placeholder="__('Select Bank')"
                            label="name"
                            track-by="id"
                            :taggable="true"
                            :tag-placeholder="__('Press enter to add a new bank')"
                            class="w-full"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
                            @tag="addBank"
                            @update:model-value="form.cheque_bank_id = selectedChequeBank?.id || null"
                        />
                        <InputError class="mt-1" :message="form.errors.cheque_bank_id" />
                    </div>
                    <div>
                        <InputLabel for="cheque_number" :value="__('Cheque Number')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                        <TextInput
                            id="cheque_number"
                            v-model="form.cheque_number"
                            type="text"
                            class="block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.cheque_number" />
                    </div>
                    <div>
                        <InputLabel for="cheque_due_date" :value="__('Due Date')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                        <DatePicker
                            id="cheque_due_date"
                            v-model="form.cheque_due_date"
                            class="block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.cheque_due_date" />
                    </div>
                </div>

                <!-- Reference -->
                <div>
                    <InputLabel for="reference" :value="__('Reference')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <TextInput
                        id="reference"
                        v-model="form.reference"
                        type="text"
                        class="block w-full"
                        :placeholder="__('Cheque number, transaction ID, etc.')"
                    />
                    <InputError class="mt-1" :message="form.errors.reference" />
                </div>

                <!-- Notes -->
                <div class="sm:col-span-2">
                    <InputLabel for="notes" :value="__('Notes')" class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500" />
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-gray-900 dark:text-white"
                        :placeholder="__('Additional notes about this payment')"
                    ></textarea>
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>
            </div>
            </div>

            <div class="lg:w-96">
                <div class="bg-white dark:bg-gray-800 p-8 rounded-lg border border-gray-200 dark:border-gray-700 sticky top-6">
                    <div class="text-center mb-6">
                        <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">
                            {{ __("Payment Summary") }}
                        </h3>
                    </div>

                    <div class="space-y-4 mb-6">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Payment Type") }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ selectedType === 'customer' ? __('Customer') : __('Supplier') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Direction") }}</span>
                            <span
                                class="inline-flex items-center gap-x-1 px-2.5 py-1 text-xs font-bold rounded-full"
                                :class="form.direction === 'in'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'
                                    : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'"
                            >
                                <svg v-if="form.direction === 'in'" class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" />
                                </svg>
                                <svg v-else class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                                </svg>
                                {{ form.direction === 'in' ? __('Incoming') : __('Outgoing') }}
                            </span>
                        </div>
                        <div v-if="selectedInvoiceData" class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400 font-medium">{{ __("Invoice") }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">#{{ selectedInvoiceData.serial_number || selectedInvoiceData.id }}</span>
                        </div>
                        <div v-if="form.amount > 0" class="pt-4 border-t border-gray-100 dark:border-gray-700">
                            <div class="text-center">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">{{ __("Amount") }}</span>
                                <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                                    {{ formatCurrency(form.amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Treasury Account -->
                    <div v-if="filteredTreasuryAccounts.length" class="pt-4 border-t border-gray-100 dark:border-gray-700">
                        <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">{{ __("Received Into") }}</p>
                        <CustomSelect
                            v-model="selectedTreasuryAccount"
                            :options="filteredTreasuryAccounts"
                            label="name"
                            track-by="id"
                            :placeholder="__('Select account...')"
                            :close-on-select="true"
                            :multiple="false"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
                            @update:model-value="(val) => { form.treasury_account_id = val ?? null; }"
                        >
                            <template #option="{ option }">
                                <span>{{ option.name }}</span>
                                <span class="text-xs text-gray-400 ms-1">({{ option.type_label }})</span>
                            </template>
                        </CustomSelect>
                    </div>
                    <div v-else-if="form.payment_method in methodToAccountType" class="pt-4 border-t border-gray-100 dark:border-gray-700 mb-2">
                        <WarningAlert
                            :title="__('No treasury account configured')"
                            :message="__('This payment method requires a treasury account. Please set one up in treasury settings.')"
                            :action-label="__('Go to Treasury')"
                            :action-href="route('treasury.create')"
                        />
                    </div>

                    <button
                        class="w-full px-6 py-4 text-lg tracking-wide text-white transition-colors font-black duration-200 rounded-lg bg-emerald-600 hover:bg-emerald-700"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ __("Record Payment") }}
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
