<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import TextInput from "@/Components/TextInput.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import { ref, watch } from "vue";
    import axios from "axios";

    const props = defineProps({
        payees: Object,
        banks: Object
    });

    let cheque = useForm({
        type: 1,
        payee_id: null,
        payee_type: "",
        invoice_id: null,
        bank_id: "",
        reference_number: "",
        amount: 0.0,
        due: null,
        notes: ""
    });

    const invoices = ref([]);
    const isLoadingInvoices = ref(false);

    const selectedBank = ref(null);
    const selectedPayee = ref(null);
    const selectedInvoice = ref(null);

    watch(() => cheque.payee_id, (newPayeeId) => {
        if (!newPayeeId) {
            invoices.value = [];
            cheque.invoice_id = null;
            return;
        }

        const payee = props.payees.find(p => p.id === newPayeeId);
        cheque.payee_type = payee?.type || "";

        fetchInvoices(newPayeeId, cheque.payee_type);
    });

    const fetchInvoices = async (payeeId, payeeType) => {
        isLoadingInvoices.value = true;
        try {
            const response = await axios.get(route('cheques.payee-invoices'), {
                params: { payee_id: payeeId, payee_type: payeeType }
            });
            invoices.value = response.data;
        } catch (error) {
            console.error("Failed to fetch invoices", error);
        } finally {
            isLoadingInvoices.value = false;
        }
    };

    const isCredit = (cheque) => {
        return cheque.type == 1;
    };

    const submit = () => {
        cheque.post(route("cheques.store"));
    };
</script>

<template>
    <AppLayout :title="__('Register Cheque')">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __("Register Cheque") }}
            </h2>
        </template>

        <form
            class="max-w-4xl mx-auto py-8 px-4 sm:px-0"
            @submit.prevent="submit"
        >
            <div
                :class="isCredit(cheque) ? 'border-emerald-500' : 'border-red-500'"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 border-l-4 rtl:border-l-0 rtl:border-r-4 rounded-xl overflow-hidden shadow-none"
            >
                <div class="p-6 sm:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Bank -->
                        <div class="col-span-1">
                            <InputLabel :value="__('Bank')" />
                            <CustomSelect
                                v-model="selectedBank"
                                :options="banks"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('Select Bank')"
                                label="name"
                                track-by="id"
                                class="w-full mt-1"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                @update:model-value="cheque.bank_id = selectedBank?.id || ''"
                            />
                            <InputError :message="cheque.errors.bank_id" class="mt-1" />
                        </div>

                        <!-- Cheque Number -->
                        <div class="col-span-1">
                            <InputLabel :value="__('Cheque Number')" />
                            <TextInput
                                v-model="cheque.reference_number"
                                type="text"
                                class="w-full mt-1 text-sm"
                                :placeholder="__('Reference Number')"
                            />
                            <InputError :message="cheque.errors.reference_number" class="mt-1" />
                        </div>

                        <!-- Due Date -->
                        <div class="col-span-1">
                            <InputLabel :value="__('Due Date')" />
                            <TextInput
                                v-model="cheque.due"
                                type="date"
                                class="w-full mt-1 text-sm rtl:text-right"
                            />
                            <InputError :message="cheque.errors.due" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Payee -->
                        <div>
                            <InputLabel :value="__('Payee')" />
                            <CustomSelect
                                v-model="selectedPayee"
                                :options="payees"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('Select Payee')"
                                label="name"
                                track-by="id"
                                class="w-full mt-1"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                @update:model-value="cheque.payee_id = selectedPayee?.id || null"
                            >
                                <template #option="{ option }">
                                    {{ option.name }} ({{ __(option.type_string) }})
                                </template>
                            </CustomSelect>
                            <InputError :message="cheque.errors.payee_id" class="mt-1" />
                            <InputError :message="cheque.errors.payee_type" class="mt-1" />
                        </div>

                        <!-- Invoice Link -->
                        <div v-if="cheque.payee_id">
                            <InputLabel :value="__('Link to Invoice')" />
                            <CustomSelect
                                v-model="selectedInvoice"
                                :options="invoices"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="isLoadingInvoices ? __('Loading...') : __('No Invoice')"
                                label="serial_number"
                                track-by="id"
                                class="w-full mt-1"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                                :disabled="isLoadingInvoices"
                                @update:model-value="cheque.invoice_id = selectedInvoice?.id || null"
                            >
                                <template #option="{ option }">
                                    #{{ option.serial_number }} ({{ __('Remaining') }}: {{ option.remaining_balance }})
                                </template>
                            </CustomSelect>
                            <InputError :message="cheque.errors.invoice_id" class="mt-1" />
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-8">
                        <InputLabel :value="__('Notes')" />
                        <textarea
                            v-model="cheque.notes"
                            rows="2"
                            class="w-full mt-1 border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 rounded-lg shadow-none text-sm"
                            :placeholder="__('Notes')"
                        ></textarea>
                        <InputError :message="cheque.errors.notes" class="mt-1" />
                    </div>

                    <!-- Type and Amount -->
                    <div class="mt-12 pt-8 border-t border-gray-100 dark:border-gray-800 flex flex-col md:flex-row md:items-end md:justify-between gap-6">
                        <div class="flex-1">
                            <InputLabel :value="__('Cheque Type')" />
                            <div class="flex items-center gap-4 mt-2">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="radio"
                                        v-model="cheque.type"
                                        :value="1"
                                        class="hidden"
                                    />
                                    <div
                                        :class="cheque.type == 1 ? 'bg-emerald-500 text-white' : 'bg-gray-50 dark:bg-gray-800 text-gray-500'"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all border border-transparent"
                                    >
                                        {{ __("Receivable") }}
                                    </div>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="radio"
                                        v-model="cheque.type"
                                        :value="0"
                                        class="hidden"
                                    />
                                    <div
                                        :class="cheque.type == 0 ? 'bg-red-500 text-white' : 'bg-gray-50 dark:bg-gray-800 text-gray-500'"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all border border-transparent"
                                    >
                                        {{ __("Payable") }}
                                    </div>
                                </label>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                {{ isCredit(cheque) ? __("You will receive money (Incoming)") : __("You will pay money (Outgoing)") }}
                            </p>
                        </div>

                        <div class="w-full md:w-48">
                            <InputLabel :value="__('Amount')" />
                            <div class="relative mt-1">
                                <TextInput
                                    v-model="cheque.amount"
                                    type="number"
                                    step="0.01"
                                    class="w-full pr-12 text-lg font-bold"
                                    :class="isCredit(cheque) ? 'text-emerald-600 focus:border-emerald-500' : 'text-red-600 focus:border-red-500'"
                                    :placeholder="__('0.00')"
                                />
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400 font-semibold uppercase">
                                    $
                                </div>
                            </div>
                            <InputError :message="cheque.errors.amount" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': cheque.processing }"
                    :disabled="cheque.processing"
                >
                    {{ __("Register Cheque") }}
                </PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
