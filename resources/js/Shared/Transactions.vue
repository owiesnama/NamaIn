<script setup>
    import { computed, ref } from "vue";
    import { useForm } from "@inertiajs/vue3";
    import Modal from "@/Components/Modal.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import ReceiveGoodsModal from "@/Components/Purchases/ReceiveGoodsModal.vue";

    const props = defineProps({
        invoice: Object,
        storages: {
            type: Array,
            default: () => []
        }
    });

    const showingDeliveryModal = ref(false);
    const selectedTransaction = ref(null);
    const deliveryForm = useForm({
        storage_id: '',
    });

    const markAsDelivered = (transaction) => {
        selectedTransaction.value = transaction;
        deliveryForm.storage_id = transaction.storage_id || '';
        showingDeliveryModal.value = true;
    };

    const confirmDelivery = () => {
        deliveryForm.post(route('transactions.deliver', selectedTransaction.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showingDeliveryModal.value = false;
                selectedTransaction.value = null;
            },
        });
    };

    let totalPrice = (record) => {
        const total = record.price * record.quantity;
        const currency = (record.currency && /^[A-Z]{3}$/.test(record.currency)) ? record.currency :
            (props.invoice?.currency && /^[A-Z]{3}$/.test(props.invoice.currency) ? props.invoice.currency :
            (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'SDG'));
        return `${total} ${currency}`;
    };
    let deliveredRecords = computed(() => {
        return props.invoice.transactions.filter((transaction) => {
            return transaction.delivered;
        });
    });
    let remainingRecords = computed(() => {
        return props.invoice.transactions.filter((transaction) => {
            return ! transaction.delivered;
        });
    });
</script>
<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto md:-mx-6 lg:-mx-8">
            <div
                class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8"
            >
                <div
                    class="overflow-hidden border border-gray-100 dark:border-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                                >
                                    {{__('The Product')}}
                                </th>

                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                                >
                                    {{__('Quantity')}}
                                </th>

                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                                >
                                    {{__('Price')}}
                                </th>

                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                                >
                                    {{__('Total Price')}}
                                </th>

                                <th
                                    v-if="invoice.invocable_type === 'App\\Models\\Customer'"
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                                >
                                    {{__('Delivery')}}
                                </th>
                                <th
                                    v-else
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-[10px] font-bold text-left rtl:text-right text-gray-500 dark:text-gray-400 uppercase tracking-widest"
                                >
                                    {{__('Status')}}
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="bg-white divide-y divide-gray-50 dark:divide-gray-800 dark:bg-gray-900"
                        >
                            <tr
                                v-for="record in deliveredRecords"
                                :key="record.id"
                                :class="remainingRecords.length ? 'bg-gray-100' : ''"
                            >
                                <td
                                    class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                    v-text="record.product?.name"
                                ></td>

                                <td
                                    class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                >
                                    {{ record.quantity }}
                                    <strong v-if="record.unit">({{ record.unit?.name }})</strong>
                                    <strong v-else>({{ __('Base unit') }})</strong>
                                </td>

                                <td
                                    class="px-8 py-3 text-sm text-left rtl:text-right text-emerald-500 whitespace-nowrap"
                                    v-text="record.price"
                                ></td>

                                <td
                                    class="px-8 py-3 text-sm font-semibold text-left rtl:text-right text-emerald-500 whitespace-nowrap"
                                    v-text="totalPrice(record)"
                                ></td>

                                <td
                                    v-if="invoice.invocable_type === 'App\\Models\\Customer'"
                                    class="px-8 py-3 text-xs text-left rtl:text-right text-gray-500 whitespace-nowrap"
                                >
                                    <span class="text-emerald-600 font-medium">✓ {{ __('Delivered') }}</span>
                                </td>
                                <td
                                    v-else
                                    class="px-8 py-3 text-xs text-left rtl:text-right text-gray-500 whitespace-nowrap"
                                >
                                    <span class="text-emerald-600 font-medium">✓ {{ __('Received') }}</span>
                                </td>
                            </tr>

                            <template v-if="remainingRecords.length">
                                <tr
                                    v-if="deliveredRecords.length"
                                >
                                    <td
                                        colspan="4"
                                        class="py-2  text-center text-gray-500 font-semibold bg-gray-100 border-t border-b"
                                    >
                                        {{__('Invoice Remaining')}}
                                    </td>
                                </tr>

                                <tr
                                    v-for="record in remainingRecords"
                                    :key="record.id"
                                >
                                    <td
                                        class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                        v-text="record.product?.name"
                                    ></td>

                                    <td
                                        class="px-8 py-3 text-sm text-left rtl:text-right text-gray-800 whitespace-nowrap"
                                    >
                                        {{ record.quantity }}
                                        <strong v-if="record.unit">({{ record.unit?.name }})</strong>
                                        <strong v-else>({{ __('Base unit') }})</strong>
                                    </td>

                                    <td
                                        class="px-8 py-3 text-sm text-left rtl:text-right text-emerald-500 whitespace-nowrap"
                                        v-text="record.price"
                                    ></td>

                                    <td
                                        class="px-8 py-3 text-sm font-semibold text-left rtl:text-right text-emerald-500 whitespace-nowrap"
                                        v-text="totalPrice(record)"
                                    ></td>

                                    <td
                                        v-if="invoice.invocable_type === 'App\\Models\\Customer'"
                                        class="px-8 py-3 text-sm text-left rtl:text-right whitespace-nowrap"
                                    >
                                        <button
                                            @click="markAsDelivered(record)"
                                            class="text-amber-600 hover:text-amber-800 font-medium text-xs underline"
                                        >
                                            {{ __('Mark Delivered') }}
                                        </button>
                                    </td>
                                    <td
                                        v-else
                                        class="px-8 py-3 text-sm text-left rtl:text-right whitespace-nowrap flex flex-col"
                                    >
                                        <div class="text-xs text-gray-500 mb-1">
                                            {{ record.received_quantity }} / {{ record.base_quantity }}
                                        </div>
                                        <ReceiveGoodsModal :transaction="record" :storages="storages" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showingDeliveryModal" @close="showingDeliveryModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ __('Confirm Delivery') }}
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Select the storage to deduct the items from.') }}
                </p>

                <div class="mt-6">
                    <InputLabel for="storage_id" :value="__('Fulfillment Storage')" />
                    <select
                        id="storage_id"
                        v-model="deliveryForm.storage_id"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                        required
                    >
                        <option value="">{{ __('Select Storage') }}</option>
                        <option v-for="storage in storages" :key="storage.id" :value="storage.id">
                            {{ storage.name }}
                        </option>
                    </select>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        @click="showingDeliveryModal = false"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md mr-2"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <PrimaryButton
                        class="ml-3"
                        :class="{ 'opacity-25': deliveryForm.processing }"
                        :disabled="deliveryForm.processing"
                        @click="confirmDelivery"
                    >
                        {{ __('Confirm Delivery') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
