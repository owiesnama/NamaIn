<script setup>
    import { useForm, Link } from "@inertiajs/vue3";
    import { computed, ref, watch } from "vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import Modal from "@/Components/Modal.vue";
    import TextInput from "@/Components/TextInput.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";

    const props = defineProps({
        cheque: {
            type: Object,
            required: true
        },
        chequeStatus: {
            type: Object
        },
        bankTreasuryAccounts: {
            type: Array,
            default: () => []
        }
    });

    const isBeyondDue = computed(() => {
        return new Date(props.cheque.due) <= new Date();
    });

    const isOverdue = computed(() => {
        return isBeyondDue.value && props.cheque.status !== 'cleared' && props.cheque.status !== 'cancelled';
    });

    const nextStatus = computed(() => {
        if (props.cheque.status === 'drafted') return 'issued';
        if (props.cheque.status === 'issued') return 'deposited';
        if (props.cheque.status === 'deposited') return 'cleared';
        return null;
    });

    const isEditable = computed(() => props.cheque.status === 'drafted');

    const form = useForm({
        status: props.cheque.status,
        cleared_amount: null,
        treasury_account_id: null,
    });

    const selectedBankAccount = ref(null);

    // Check if the cheque's bank already has a linked treasury account
    const hasLinkedBankAccount = computed(() => {
        if (!props.cheque.bank_id) return false;
        return props.bankTreasuryAccounts.some(a => a.bank_id === props.cheque.bank_id);
    });

    const showConfirmationModal = ref(false);
    const selectedStatus = ref(props.cheque.status);

    watch(
        () => props.cheque.status,
        (newStatus) => {
            selectedStatus.value = newStatus;
            form.status = newStatus;
        }
    );

    const statusLable = (status) =>
        Object.keys(props.chequeStatus).find(
            (key) => props.chequeStatus[key] === status
        );

    const handleStatusChange = (e) => {
        const newValue = e.target.value;
        if (newValue === 'cleared' || newValue === 'partially_cleared') {
            form.status = newValue;
            if (newValue === 'partially_cleared') {
                form.cleared_amount = null;
            } else {
                form.cleared_amount = props.cheque.amount - props.cheque.cleared_amount;
            }
            showConfirmationModal.value = true;
        } else {
            selectedStatus.value = newValue;
            submit();
        }
    };

    const confirmClearance = () => {
        form.put(route("cheques.updateStatus", props.cheque.id), {
            preserveScroll: true,
            onSuccess: () => {
                selectedStatus.value = form.status;
                showConfirmationModal.value = false;
            },
            onError: () => {
                showConfirmationModal.value = false;
                selectedStatus.value = props.cheque.status;
            },
        });
    };

    const cancelClearance = () => {
        selectedStatus.value = props.cheque.status;
        showConfirmationModal.value = false;
    };

    const submit = () => {
        form.status = selectedStatus.value;
        form.put(route("cheques.updateStatus", props.cheque.id), {
            preserveScroll: true,
            onError: () => {
                selectedStatus.value = props.cheque.status;
            },
        });
    };

    const confirmationMessage = computed(() => {
        const amount = form.cleared_amount || props.cheque.amount;
        if (props.cheque.invoice_id) {
            return __("Clearing this cheque will record a payment of :amount against Invoice #:serial. The invoice's remaining balance will decrease by :amount. Continue?", {
                amount: amount,
                serial: props.cheque.invoice?.serial_number || props.cheque.invoice_id
            });
        }

        if (props.cheque.type == 1) { // Receivable
            return __("Clearing this cheque will record an incoming payment of :amount from :name. Their outstanding balance will decrease by :amount. Continue?", {
                amount: amount,
                name: props.cheque.payee.name
            });
        }

        return __("Clearing this cheque will record an outgoing payment of :amount to :name. Your outstanding payable to them will decrease by :amount. Continue?", {
            amount: amount,
            name: props.cheque.payee.name
        });
    });
</script>
<template>
    <div
        :class="[
            cheque.type == 1 ? 'border-emerald-500' : 'border-red-500',
            isOverdue ? 'bg-red-50/50 dark:bg-red-900/10 ring-1 ring-red-500/20 shadow-sm' : 'bg-white dark:bg-gray-900'
        ]"
        class="p-6 border border-gray-200 dark:border-gray-700 border-l-4 rtl:border-l-0 rtl:border-r-4 rounded-xl shadow-none transition-all relative"
    >
        <div class="absolute top-4 right-4 rtl:right-auto rtl:left-4 flex items-center gap-2">
            <span v-if="isOverdue" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 uppercase tracking-wider">
                {{ __("Overdue") }}
            </span>
            <div class="flex items-center gap-x-2">
                <Link v-if="isEditable" :href="route('cheques.edit', cheque.id)" class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:text-gray-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20 rounded-lg transition-all" :title="__('Edit')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                </Link>
                <Link :href="route('cheques.destroy', cheque.id)" method="delete" as="button" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:text-gray-500 dark:hover:text-red-400 dark:hover:bg-red-900/20 rounded-lg transition-all" v-if="cheque.status === 'drafted' || cheque.status === 'cancelled'" :title="__('Delete')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </Link>
            </div>
        </div>

        <div class="flex items-center justify-between mt-4">
            <div class="flex-1 min-w-0 pr-24 rtl:pr-0 rtl:pl-24">
                <InputLabel :value="'#' + cheque.reference_number" class="truncate" />
                <InputLabel :value="cheque.bank" class="truncate" />
            </div>

            <p
                class="text-sm shrink-0"
                :class="
                    isBeyondDue
                        ? 'text-red-500 font-semibold'
                        : 'text-gray-500 font-medium'
                "
                v-text="cheque.due_formatted"
            ></p>
        </div>

        <h2
            class="mt-2 text-lg font-semibold text-gray-800"
            v-text="cheque.payee.name"
        ></h2>
            <div class="flex items-center gap-2">
                <span
                    class="text-sm text-gray-500 font-semibold"
                    v-text="__(statusLable(selectedStatus))"
                ></span>
                <Link v-if="cheque.invoice_id" :href="route('invoices.show', cheque.invoice_id)" class="text-xs text-blue-500 underline">
                    {{ __("Invoice") }} #{{ cheque.invoice?.serial_number || cheque.invoice_id }}
                </Link>
            </div>

        <div class="mt-8 sm:flex sm:items-end sm:justify-between">
            <div>
                <InputLabel :value="__('Status')" />
                <div class="flex items-center gap-2 mt-2">
                        <VueMultiselect
                            :model-value="{ id: selectedStatus, label: statusLable(selectedStatus) }"
                            :options="Object.entries(chequeStatus).map(([label, id]) => ({ id, label }))"
                            :multiple="false"
                            :close-on-select="true"
                            :placeholder="__('Select Status')"
                            label="label"
                            track-by="id"
                            class="w-full sm:w-48"
                            :select-label="''"
                            :deselect-label="''"
                            :selected-label="__('Selected')"
                            @update:model-value="option => handleStatusChange({ target: { value: option?.id } })"
                        >
                            <template #singleLabel="{ option }">
                                {{ __(option.label) }}
                            </template>
                            <template #option="{ option }">
                                {{ __(option.label) }}
                            </template>
                        </VueMultiselect>

                        <button
                            v-if="nextStatus"
                            @click="handleStatusChange({ target: { value: nextStatus } })"
                            class="inline-flex items-center px-3 py-2 text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800 transition-colors shrink-0"
                            :title="__('Move to') + ' ' + __(statusLable(nextStatus))"
                        >
                            {{ __("Mark as") }} {{ __(statusLable(nextStatus)) }}
                        </button>
                    </div>
                </div>

            <div class="text-right">
                <p v-if="cheque.cleared_amount > 0" class="text-xs text-gray-500">
                    {{ __("Cleared") }}: {{ cheque.cleared_amount }} / {{ cheque.amount }}
                </p>
                <h3
                    :class="cheque.type == 1 ? 'text-emerald-500' : 'text-red-500'"
                    class="mt-1 font-bold sm:mt-0"
                    v-text="cheque.amount_formated"
                ></h3>
            </div>
        </div>

        <Modal :show="showConfirmationModal" @close="cancelClearance">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ __("Confirm Cheque Clearance") }}
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    {{ confirmationMessage }}
                </p>

                <div v-if="selectedStatus === 'partially_cleared' || form.status === 'partially_cleared'" class="mt-4">
                    <InputLabel for="cleared_amount" :value="__('Cleared Amount')" />
                    <TextInput
                        id="cleared_amount"
                        type="number"
                        class="mt-1 block w-full"
                        v-model="form.cleared_amount"
                        step="0.01"
                        :max="cheque.amount - cheque.cleared_amount"
                        required
                    />
                </div>

                <!-- Bank treasury account fallback (shown when bank has no linked treasury account) -->
                <div v-if="bankTreasuryAccounts.length && !hasLinkedBankAccount" class="mt-4">
                    <InputLabel :value="__('Deposit Into')" />
                    <p class="text-xs text-gray-400 dark:text-gray-500 mb-1">{{ __("No treasury account linked to this bank. Select one manually:") }}</p>
                    <CustomSelect
                        v-model="selectedBankAccount"
                        :options="bankTreasuryAccounts"
                        label="name"
                        track-by="id"
                        :placeholder="__('Select bank account...')"
                        :close-on-select="true"
                        :multiple="false"
                        :select-label="''"
                        :deselect-label="''"
                        :selected-label="__('Selected')"
                        class="mt-1"
                        @update:model-value="form.treasury_account_id = selectedBankAccount?.id ?? null"
                    />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="cancelClearance">
                        {{ __("Cancel") }}
                    </SecondaryButton>

                    <PrimaryButton
                        class="ml-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="confirmClearance"
                    >
                        {{ __("Confirm") }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
