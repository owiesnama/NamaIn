<script setup>
    import { useForm, Link } from "@inertiajs/vue3";
    import { computed, ref } from "vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import SelectBox from "./SelectBox.vue";
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
        }
    });

    const isBeyondDue = computed(() => {
        return new Date(props.cheque.due) <= new Date();
    });

    const isEditable = computed(() => props.cheque.status === 'drafted');

    const form = useForm({
        status: props.cheque.status,
        cleared_amount: null
    });

    const showConfirmationModal = ref(false);
    const selectedStatus = ref(props.cheque.status);

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
            onSuccess: () => {
                selectedStatus.value = form.status;
                showConfirmationModal.value = false;
            }
        });
    };

    const cancelClearance = () => {
        selectedStatus.value = props.cheque.status;
        showConfirmationModal.value = false;
    };

    const submit = () => {
        form.status = selectedStatus.value;
        form.put(route("cheques.updateStatus", props.cheque.id));
    };

    const confirmationMessage = computed(() => {
        const amount = form.cleared_amount || props.cheque.amount;
        if (props.cheque.invoice_id) {
            return `Clearing this cheque will record a payment of ${amount} against Invoice #${props.cheque.invoice?.serial_number || props.cheque.invoice_id}. The invoice's remaining balance will decrease by ${amount}. Continue?`;
        }

        if (props.cheque.type == 1) { // Receivable
            return `Clearing this cheque will record an incoming payment of ${amount} from ${props.cheque.payee.name}. Their outstanding balance will decrease by ${amount}. Continue?`;
        }

        return `Clearing this cheque will record an outgoing payment of ${amount} to ${props.cheque.payee.name}. Your outstanding payable to them will decrease by ${amount}. Continue?`;
    });
</script>
<template>
    <div
        :class="cheque.type == 1 ? 'border-emerald-500' : 'border-red-500'"
        class="p-6 bg-white border-l-4 border-dashed rounded-lg rounded-l-none shadow-md rtl:border-l-0 rtl:border-r-4 rtl:rounded-l-lg rtl:rounded-r-none shadow-gray-200 relative"
    >
        <div class="absolute top-4 right-4 flex gap-2" v-if="isEditable">
            <Link :href="route('cheques.edit', cheque.id)" class="text-blue-500 hover:text-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
            </Link>
            <Link :href="route('cheques.destroy', cheque.id)" method="delete" as="button" class="text-red-500 hover:text-red-700" v-if="cheque.status === 'drafted' || cheque.status === 'cancelled'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </Link>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <InputLabel :value="'#' + cheque.reference_number" />
                <InputLabel :value="'#' + cheque.bank" />
            </div>

            <p
                class="text-sm"
                :class="
                    isBeyondDue
                        ? 'text-red-500 font-semibold'
                        : 'text-gray-500 font-medium'
                "
                v-text="cheque.due_for_humans"
            ></p>
        </div>

        <h2
            class="mt-1 text-lg font-semibold text-gray-800"
            v-text="cheque.payee.name"
        ></h2>
        <div class="flex items-center gap-2">
            <span
                class="text-sm text-gray-500 font-semibold"
                v-text="__(statusLable(cheque.status))"
            ></span>
            <Link v-if="cheque.invoice_id" :href="route('invoices.show', cheque.invoice_id)" class="text-xs text-blue-500 underline">
                {{ __("Invoice") }} #{{ cheque.invoice?.serial_number || cheque.invoice_id }}
            </Link>
        </div>

        <div class="mt-24 sm:flex sm:items-end sm:justify-between">
            <div>
                <InputLabel :value="__('Status')" />
                <SelectBox
                    v-model="selectedStatus"
                    class="w-full mt-1 text-sm rounded-lg sm:w-36"
                    @change="handleStatusChange"
                >
                    <option
                        v-for="(key, status) in chequeStatus"
                        :key="key"
                        :value="key"
                        v-text="__(status)"
                    ></option>
                </SelectBox>
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
