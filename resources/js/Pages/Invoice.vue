<script setup>
    import Card from "@/Pages/Purchases/Card.vue";
    import { Head, useForm } from "@inertiajs/vue3";
    import { computed, ref } from "vue";

    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import InputError from "@/Components/InputError.vue";
    import DialogModal from "@/Components/DialogModal.vue";


    const props = defineProps({
        invoice: Object,
        storages: Array
    });

    let movingToStorage = ref(false);

    let form = useForm({
        invoice: null,
        storage: null
    });

    let moveToStorage = (invoice) => {
        movingToStorage.value = true;
        form.invoice = invoice.id;
    };

    let hasNoInvoices = computed(() => {
        return !props.invoices.data.length;
    });

    let confirmMoving = () => {
        form.put(route("stock.add", form.storage), {
            onFinish: () => closeModal()
        }).then();
    };

    let deductingFromStorage = ref(false);

    let deductFromStorage = (invoice) => {
        deductingFromStorage.value = true;
        form.invoice = invoice.id;
    };

    let closeModal = () => {
        movingToStorage.value = null;
        deductingFromStorage.value = null;
    };

const formatCurrency = (amount, currency = null) => {
    return new Intl.NumberFormat(window.lang === 'ar' ? 'ar-SA' : 'en-US', {
        style: 'currency',
        currency: currency || props.invoice?.currency || preferences('currency') || 'USD',
    }).format(amount || 0);
};

</script>

<template>
    <Head :title="__('Invoice')" />
    <div class="m-2">
        <card v-if="invoice.invocable.type_string == 'Supplier'"
              :invoice="invoice"
              @moveToStorage="moveToStorage"
              :actionTitle="__('Deliver to Storage')" :printable="false"></card>
        <card v-else
              :invoice="invoice"
              @moveToStorage="deductFromStorage"
              :actionTitle="__('Deduct From Storage')" :printable="false"></card>

        <!-- Payment Information -->
        <div class="mt-6 bg-white rounded-lg shadow-md p-6 dark:bg-gray-800">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                {{ __("Payment Information") }}
            </h3>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __("Total") }}</p>
                    <p class="text-lg font-semibold text-gray-800 dark:text-white">
                        {{ formatCurrency(invoice.total, invoice.currency) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __("Paid") }}</p>
                    <p class="text-lg font-semibold text-emerald-600">
                        {{ formatCurrency(invoice.paid_amount || 0, invoice.currency) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __("Balance") }}</p>
                    <p class="text-lg font-semibold text-red-600">
                        {{ formatCurrency(invoice.remaining_balance || invoice.total, invoice.currency) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __("Status") }}</p>
                    <span
                        class="inline-block px-3 py-1 text-xs rounded-full"
                        :class="{
                            'bg-emerald-100 text-emerald-700': invoice.payment_status === 'paid',
                            'bg-yellow-100 text-yellow-700': invoice.payment_status === 'partially_paid',
                            'bg-red-100 text-red-700': invoice.payment_status === 'unpaid',
                        }"
                    >
                        {{ __(invoice.payment_status?.replace('_', ' ') || 'unpaid') }}
                    </span>
                </div>
            </div>

            <!-- Payment History -->
            <div v-if="invoice.payments && invoice.payments.length > 0">
                <h4 class="text-md font-semibold text-gray-800 dark:text-white mb-3">
                    {{ __("Payment History") }}
                </h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ __("Date") }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ __("Amount") }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ __("Method") }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ __("Reference") }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ __("Notes") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            <tr v-for="payment in invoice.payments" :key="payment.id">
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                    {{ new Date(payment.paid_at).toLocaleDateString() }}
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-emerald-600">
                                    {{ formatCurrency(payment.amount, payment.currency) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ __(payment.payment_method?.replace('_', ' ') || 'cash') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ payment.reference || '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ payment.notes || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else class="text-center py-4 text-gray-500 dark:text-gray-400">
                {{ __("No payments recorded yet") }}
            </div>
        </div>
    </div>

    <DialogModal
        :show="deductingFromStorage"
        @close="closeModal"
    >
        <template #title></template>
        <template #content>
            <div
                v-if="form.errors.storage"
                class="flex items-center p-4 mb-4 border border-red-500 rounded"
            >
                <InputError
                    class="mt-2"
                    :message="form.errors.storage"
                />
            </div>
            <select
                id="storage"
                v-model="form.storage"
                class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                name="storage"
            >
                <option
                    v-for="storage in storages"
                    :key="storage.id"
                    :value="storage.id"
                    v-text="storage.name"
                ></option>
            </select>
        </template>
        <template #footer>
            <SecondaryButton @click="closeModal"> {{ __("Cancel") }}</SecondaryButton>

            <PrimaryButton
                class="ml-3 rtl:mr-3 rtl:ml-0"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="confirmDeduct"
            >
                {{ __("Confirm") }}
            </PrimaryButton>
        </template>
    </DialogModal>

    <DialogModal
        :show="movingToStorage"
        @close="closeModal"
    >
        <template #title></template>
        <template #content>
            <select
                id="storage"
                v-model="form.storage"
                class="border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                name="storage"
            >
                <option
                    v-for="storage in storages"
                    :key="storage.id"
                    :value="storage.id"
                    v-text="storage.name"
                ></option>
            </select>
        </template>
        <template #footer>
            <SecondaryButton @click="closeModal"> {{ __("Cancel") }}</SecondaryButton>

            <PrimaryButton
                class="ml-3 rtl:mr-3 rtl:ml-0"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
                @click="confirmMoving"
            >
                {{ __("Confirm") }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>

<style scoped>

</style>
