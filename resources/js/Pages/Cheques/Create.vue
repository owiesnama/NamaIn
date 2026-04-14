<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import SelectBox from "@/Shared/SelectBox.vue";
    import { ref, watch } from "vue";
    import axios from "axios";

    defineProps({
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
    <AppLayout title="Products">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Products
            </h2>
        </template>
        <form
            class="max-w-6xl mx-auto"
            action="#"
            @submit.prevent="submit"
        >
            <div
                :class="
                    isCredit(cheque) ? 'border-emerald-500' : 'border-red-500'
                "
                class="p-6 bg-white border-l-4 border-dashed rounded-lg rounded-l-none shadow-md rtl:border-l-0 rtl:border-r-4 rtl:rounded-l-lg rtl:rounded-r-none shadow-gray-200"
            >
                <div class="flex items-center justify-between">
                    <div class="mb-4">
                        <InputLabel :value="__('Bank')" />
                        <select
                            v-model="cheque.bank_id"
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option value="">{{ __('Select Bank') }}</option>
                            <option
                                v-for="bank in banks"
                                :key="bank.id"
                                :value="bank.id"
                            >
                                {{ bank.name }}
                            </option>
                        </select>
                        <InputError :message="cheque.errors.bank_id" class="mt-1" />
                    </div>
                    <div class="mb-4">
                        <InputLabel :value="__('Cheque Number')" />
                        <input
                            v-model="cheque.reference_number"
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            type="text"
                            :placeholder="__('Cheque Number')"
                        />
                        <InputError :message="cheque.errors.reference_number" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel :value="__('Due')" />
                        <input
                            v-model="cheque.due"
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            type="date"
                            :placeholder="__('Due')"
                        />
                        <InputError :message="cheque.errors.due" class="mt-1" />
                    </div>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-gray-800">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <InputLabel :value="__('Payee')" />
                            <select
                                v-model="cheque.payee_id"
                                class="w-full px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                name="payee"
                            >
                                <option :value="null">{{ __('Select Payee') }}</option>
                                <option
                                    v-for="payee in payees"
                                    :key="payee.id + payee.type"
                                    :value="payee.id"
                                    v-text="payee.name + ' (' + payee.type_string + ')'"
                                ></option>
                            </select>
                            <InputError :message="cheque.errors.payee_id" class="mt-1" />
                            <InputError :message="cheque.errors.payee_type" class="mt-1" />
                        </div>

                        <div class="flex-1" v-if="cheque.payee_id">
                            <InputLabel :value="__('Link to Invoice')" />
                            <select
                                v-model="cheque.invoice_id"
                                class="w-full px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                :disabled="isLoadingInvoices"
                            >
                                <option :value="null">{{ isLoadingInvoices ? __('Loading...') : __('No Invoice') }}</option>
                                <option
                                    v-for="invoice in invoices"
                                    :key="invoice.id"
                                    :value="invoice.id"
                                >
                                    #{{ invoice.serial_number }} ({{ __('Remaining') }}: {{ invoice.remaining_balance }})
                                </option>
                            </select>
                            <InputError :message="cheque.errors.invoice_id" class="mt-1" />
                        </div>
                    </div>
                </h2>

                <div class="mt-4">
                    <InputLabel :value="__('Notes')" />
                    <textarea
                        v-model="cheque.notes"
                        class="w-full px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        rows="3"
                        :placeholder="__('Notes')"
                    ></textarea>
                    <InputError :message="cheque.errors.notes" class="mt-1" />
                </div>

                <div class="mt-24 sm:flex sm:items-end sm:justify-between">
                    <div>
                        <InputLabel :value="__('Status')" />
                        <SelectBox
                            v-model="cheque.type"
                            class="w-full mt-1 text-sm rounded-lg sm:w-36"
                        >
                            <option value="0" v-text="__('Payable') + ' (' + __('Outgoing') + ')'"></option>
                            <option value="1" v-text="__('Receivable') + ' (' + __('Incoming') + ')'"></option>
                        </SelectBox>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ isCredit(cheque) ? __("You will receive money") : __("You will pay money") }}
                        </p>
                    </div>
                    <h3
                        :class="
                            isCredit(cheque)
                                ? 'text-emerald-500'
                                : 'text-red-500'
                        "
                        class="mt-4 font-bold sm:mt-0"
                    >
                        <input
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            v-model="cheque.amount"
                            type="number"
                            :placeholder="__('Amount')"
                        />
                        <InputError :message="cheque.errors.amount" class="mt-1" />
                    </h3>
                </div>
            </div>
            <div class="mt-8 text-right rtl:text-left">
                <button
                    class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    type="submit"
                >
                    {{ __("Register Cheque") }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
