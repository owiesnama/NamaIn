<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
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
        (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD'));

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
    <AppLayout title="Record Payment">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            {{ __("Record Payment") }}
        </h2>

        <form
            class="mt-6 bg-white border-2 border-dashed rounded-lg p-6 dark:bg-gray-900 dark:border-gray-700"
            @submit.prevent="submit"
        >
            <div class="grid grid-cols-1 gap-6 mt-4 sm:grid-cols-2">
                <!-- Select Type -->
                <div>
                    <InputLabel for="type" :value="__('Payment For')" />
                    <select
                        v-model="selectedType"
                        id="type"
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring"
                        required
                        @change="selectedEntity = null; selectedInvoice = null"
                    >
                        <option value="customer">{{ __("Customer") }}</option>
                        <option value="supplier">{{ __("Supplier") }}</option>
                    </select>
                </div>

                <!-- Select Customer/Supplier -->
                <div>
                    <InputLabel for="entity" :value="selectedType === 'customer' ? __('Customer') : __('Supplier')" />
                    <select
                        v-model="selectedEntity"
                        id="entity"
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring"
                        required
                        @change="selectedInvoice = null"
                    >
                        <option :value="null">{{ selectedType === 'customer' ? __("Select Customer") : __("Select Supplier") }}</option>
                        <option
                            v-for="entity in entities"
                            :key="entity.id"
                            :value="entity.id"
                        >
                            {{ entity.name }}
                        </option>
                    </select>
                </div>

                <!-- Select Invoice -->
                <div>
                    <InputLabel for="invoice" :value="__('Invoice (Optional)')" />
                    <select
                        v-model="selectedInvoice"
                        id="invoice"
                        :disabled="!selectedEntity"
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring disabled:opacity-50"
                    >
                        <option :value="null">{{ __("General Payment (No Invoice)") }}</option>
                        <option
                            v-for="invoice in unpaidInvoices"
                            :key="invoice.id"
                            :value="invoice.id"
                        >
                            #{{ invoice.serial_number || invoice.id }} - {{
                                formatCurrency(invoice.remaining_balance, invoice.currency)
                            }}
                        </option>
                    </select>
                </div>

                <!-- Invoice Details -->
                <div
                    v-if="selectedInvoiceData"
                    class="col-span-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg"
                >
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400"
                                >{{ __("Total") }}:</span
                            >
                            <span class="ml-2 font-semibold"
                                >{{ formatCurrency(selectedInvoiceData.total, selectedInvoiceData.currency) }}</span
                            >
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400"
                                >{{ __("Paid") }}:</span
                            >
                            <span class="ml-2 font-semibold text-emerald-600"
                                >{{ formatCurrency(selectedInvoiceData.paid_amount, selectedInvoiceData.currency) }}</span
                            >
                        </div>
                        <div class="col-span-2">
                            <span class="text-gray-600 dark:text-gray-400"
                                >{{ __("Remaining Balance") }}:</span
                            >
                            <span class="ml-2 font-semibold text-red-600"
                                >{{
                                    formatCurrency(selectedInvoiceData.remaining_balance, selectedInvoiceData.currency)
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div>
                    <InputLabel for="amount" :value="__('Amount')" />
                    <TextInput
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        class="block w-full mt-2"
                        required
                        :max="
                            selectedInvoiceData?.remaining_balance
                        "
                    />
                    <InputError class="mt-2" :message="form.errors.amount" />
                </div>

                <!-- Payment Method -->
                <div>
                    <InputLabel
                        for="payment_method"
                        :value="__('Payment Method')"
                    />
                    <select
                        v-model="form.payment_method"
                        id="payment_method"
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring"
                        required
                    >
                        <option
                            v-for="(value, label) in payment_methods"
                            :key="value"
                            :value="value"
                        >
                            {{ __(label) }}
                        </option>
                    </select>
                    <InputError
                        class="mt-2"
                        :message="form.errors.payment_method"
                    />
                </div>

                <!-- Bank Transfer Details -->
                <div v-if="form.payment_method === 'bank_transfer'" class="col-span-2 grid grid-cols-1 gap-6 sm:grid-cols-2 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                    <div>
                        <InputLabel for="bank_name" :value="__('Bank Name')" />
                        <TextInput
                            id="bank_name"
                            v-model="form.bank_name"
                            type="text"
                            class="block w-full mt-2"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.bank_name" />
                    </div>
                    <div>
                        <InputLabel for="receipt" :value="__('Payment Receipt (Optional)')" />
                        <input
                            id="receipt"
                            type="file"
                            class="block w-full mt-2 text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400"
                            @input="form.receipt = $event.target.files[0]"
                        />
                        <InputError class="mt-2" :message="form.errors.receipt" />
                    </div>
                </div>

                <!-- Cheque Details -->
                <div v-if="form.payment_method === 'cheque'" class="col-span-2 grid grid-cols-1 gap-6 sm:grid-cols-3 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <div>
                        <InputLabel for="cheque_bank" :value="__('Select Bank')" />
                        <select
                            v-model="form.cheque_bank_id"
                            id="cheque_bank"
                            class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring"
                            required
                        >
                            <option :value="null">{{ __("Select Bank") }}</option>
                            <option
                                v-for="bank in banks"
                                :key="bank.id"
                                :value="bank.id"
                            >
                                {{ bank.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.cheque_bank_id" />
                    </div>
                    <div>
                        <InputLabel for="cheque_number" :value="__('Cheque Number')" />
                        <TextInput
                            id="cheque_number"
                            v-model="form.cheque_number"
                            type="text"
                            class="block w-full mt-2"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.cheque_number" />
                    </div>
                    <div>
                        <InputLabel for="cheque_due_date" :value="__('Due Date')" />
                        <TextInput
                            id="cheque_due_date"
                            v-model="form.cheque_due_date"
                            type="date"
                            class="block w-full mt-2"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.cheque_due_date" />
                    </div>
                </div>

                <!-- Reference -->
                <div>
                    <InputLabel for="reference" :value="__('Reference')" />
                    <TextInput
                        id="reference"
                        v-model="form.reference"
                        type="text"
                        class="block w-full mt-2"
                        :placeholder="__('Cheque number, transaction ID, etc.')"
                    />
                    <InputError class="mt-2" :message="form.errors.reference" />
                </div>

                <!-- Notes -->
                <div class="col-span-2">
                    <InputLabel for="notes" :value="__('Notes')" />
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="block w-full px-4 py-2 mt-2 text-gray-700 bg-white border border-gray-200 rounded-md dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 focus:border-emerald-400 focus:ring-emerald-300 focus:ring-opacity-40 dark:focus:border-emerald-300 focus:outline-none focus:ring"
                        :placeholder="__('Additional notes about this payment')"
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.notes" />
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <PrimaryButton :disabled="form.processing">
                    {{ __("Record Payment") }}
                </PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
