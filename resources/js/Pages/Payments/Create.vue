<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import FileUploader from "@/Components/FileUploader.vue";
import { useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps({
    customers: Array,
    suppliers: Array,
    banks: Array,
    payment_methods: Object,
});

const selectedType = ref("customer"); // 'customer' or 'supplier'
const selectedEntity = ref(null);
const selectedInvoice = ref(null);

const selectedEntityType = ref({ id: 'customer', label: 'Customer' });
const selectedEntityObject = ref(null);
const selectedInvoiceObject = ref(null);
const selectedPaymentMethod = ref({ id: 'cash', label: 'Cash' });
const selectedChequeBank = ref(null);

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
                        @update:model-value="selectedType = selectedEntityType?.id || 'customer'; selectedEntity = null; selectedInvoice = null; selectedEntityObject = null; selectedInvoiceObject = null"
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
                        @update:model-value="selectedEntity = selectedEntityObject?.id || null; selectedInvoice = null; selectedInvoiceObject = null"
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
                        @update:model-value="selectedInvoice = selectedInvoiceObject?.id || null"
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
                        @update:model-value="form.payment_method = selectedPaymentMethod?.id || 'cash'"
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
                            class="w-full"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
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
