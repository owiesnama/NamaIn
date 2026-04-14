<script setup>
    import { computed } from "vue";
    const props = defineProps({
        invoice: Object,
    });
    let totalPrice = (record) => {
        const total = record.price * record.quantity;
        const currency = (record.currency && /^[A-Z]{3}$/.test(record.currency)) ? record.currency :
            (props.invoice?.currency && /^[A-Z]{3}$/.test(props.invoice.currency) ? props.invoice.currency :
            (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency')) ? preferences('currency') : 'USD'));
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
                    class="overflow-hidden border border-gray-200 rounded-b-lg dark:border-gray-700"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-100">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                >
                                    {{__('The Product')}}
                                </th>

                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                >
                                    {{__('Quantity')}}
                                </th>

                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                >
                                    {{__('Price')}}
                                </th>

                                <th
                                    scope="col"
                                    class="px-8 py-3.5 whitespace-nowrap text-sm font-normal text-left rtl:text-right text-gray-500 dark:text-gray-400"
                                >
                                    {{__('Total Price')}}
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="bg-white divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-900"
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
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
