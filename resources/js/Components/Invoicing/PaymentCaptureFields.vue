<script setup>
    import InputLabel from "@/Components/InputLabel.vue";
    import TextInput from "@/Components/TextInput.vue";
    import InputError from "@/Components/InputError.vue";
    import FileUploader from "@/Components/FileUploader.vue";
    import { ref, computed, watch } from "vue";

    const props = defineProps({
        form: {
            type: Object,
            required: true,
        },
        isSale: {
            type: Boolean,
            default: false,
        },
        banks: {
            type: Array,
            default: () => [],
        },
        treasuryAccounts: {
            type: Array,
            default: () => [],
        },
    });

    const methodToAccountType = { cash: 'cash', bank_transfer: 'bank', cheque: 'cheque_clearing' };

    const filteredTreasuryAccounts = computed(() => {
        const type = methodToAccountType[props.form.payment_method];
        if (!type) return [];
        return props.treasuryAccounts.filter(a => a.type === type);
    });

    const selectedTreasuryAccount = ref(null);

    watch(() => props.form.payment_method, () => {
        selectedTreasuryAccount.value = null;
        props.form.treasury_account_id = null;
    });

    const addBank = (newTag) => {
        props.form.cheque_bank_id = newTag;
    };
</script>

<template>
    <div class="space-y-4">
        <div v-if="filteredTreasuryAccounts.length">
            <InputLabel :value="isSale ? __('Received Into') : __('Paid From')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
            <CustomSelect
                v-model="selectedTreasuryAccount"
                :options="filteredTreasuryAccounts"
                label="name"
                track-by="id"
                :placeholder="__('Select account...')"
                :close-on-select="true"
                :multiple="false"
                @update:model-value="form.treasury_account_id = $event ?? null"
            >
                <template #option="{ option }">
                    <span>{{ option.name }}</span>
                    <span class="text-xs text-gray-400 ms-1">({{ option.type_label }})</span>
                </template>
            </CustomSelect>
            <p v-if="form.payment_method === 'cash' && !form.treasury_account_id" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ __("Defaults to the main cash account") }}
            </p>
        </div>

        <div>
            <InputLabel for="initial_payment" :value="__('Payment')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
            <TextInput id="initial_payment" v-model="form.initial_payment_amount" type="number" inputmode="decimal" min="0" step="0.01" class="block w-full" :placeholder="__('Leave empty for no payment')" />
            <InputError :message="form.errors.initial_payment_amount" class="mt-1" />
        </div>

        <div>
            <InputLabel for="payment_reference" :value="__('Reference #')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
            <TextInput id="payment_reference" v-model="form.payment_reference" type="text" class="block w-full" :placeholder="__('Ref / Cheque #')" />
            <InputError :message="form.errors.payment_reference" class="mt-1" />
        </div>

        <!-- Bank Transfer Details -->
        <div v-if="form.payment_method === 'bank_transfer'" class="space-y-4 p-4 bg-emerald-50 dark:bg-emerald-900/10 rounded-lg border border-emerald-200 dark:border-emerald-800">
            <div>
                <InputLabel for="bank_name" :value="__('Bank Name')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                <TextInput id="bank_name" v-model="form.bank_name" type="text" class="block w-full" required />
                <InputError class="mt-1" :message="form.errors.bank_name" />
            </div>
            <div>
                <InputLabel for="receipt" :value="__('Payment Receipt (Optional)')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                <FileUploader v-model="form.receipt" />
                <InputError class="mt-1" :message="form.errors.receipt" />
            </div>
        </div>

        <!-- Cheque Details -->
        <div v-if="form.payment_method === 'cheque'" class="space-y-4 p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-200 dark:border-blue-800">
            <div>
                <InputLabel for="cheque_bank" :value="__('Select Bank')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                <CustomSelect
                    v-model="form.cheque_bank_id"
                    :options="banks"
                    :multiple="false"
                    :close-on-select="true"
                    :placeholder="__('Select Bank')"
                    label="name"
                    track-by="id"
                    :taggable="true"
                    :tag-placeholder="__('Press enter to add a new bank')"
                    class="w-full"
                    @tag="addBank"
                />
                <InputError class="mt-1" :message="form.errors.cheque_bank_id" />
            </div>
            <div>
                <InputLabel for="cheque_number" :value="__('Cheque Number')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                <TextInput id="cheque_number" v-model="form.cheque_number" type="text" class="block w-full" required />
                <InputError class="mt-1" :message="form.errors.cheque_number" />
            </div>
            <div>
                <InputLabel for="cheque_due_date" :value="__('Due Date')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                <DatePicker id="cheque_due_date" v-model="form.cheque_due_date" class="block w-full" required />
                <InputError class="mt-1" :message="form.errors.cheque_due_date" />
            </div>
        </div>

        <div>
            <InputLabel for="payment_notes" :value="__('Notes')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
            <textarea
                id="payment_notes"
                v-model="form.payment_notes"
                rows="3"
                class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-sm resize-none"
                :placeholder="__('Internal notes...')"
            ></textarea>
            <InputError :message="form.errors.payment_notes" class="mt-1" />
        </div>
    </div>
</template>
