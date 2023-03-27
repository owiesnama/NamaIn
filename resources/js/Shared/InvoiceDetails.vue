<script setup>
    import { computed } from "vue";
    const props = defineProps({
        invoice: Object,
    });
    let totalPrice = (record) => {
        return record.price * record.quantity;
    };
    let deliverableRecords = computed(() => {
        return props.invoice.details.filter((record) => {
            return !record.delivered;
        });
    });
    let remainingRecords = computed(() => {
        return props.invoice.details.filter((record) => {
            return record.delivered;
        });
    });
    let recordQuantity = (record) => {
        if (!record.unit) {
            return `${record.quantity} <strong>(Base unit)</strong>`;
        }
        return `${record.quantity} <storng>(${record.unit?.name})</storng>`;
    };
</script>
<template>
    <div>
        <table class="w-full">
            <thead>
                <tr>
                    <th class="text-left">Product</th>
                    <th class="text-left">Quantity</th>
                    <th class="text-left">Price</th>
                    <th class="text-left">Total Price</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="record in deliverableRecords"
                    :key="record.id"
                >
                    <td v-text="record.product.name"></td>
                    <td v-html="recordQuantity(record)"></td>
                    <td v-text="record.price"></td>
                    <td v-text="totalPrice(record)"></td>
                </tr>
                <template v-if="remainingRecords.length">
                    <tr>
                        <td
                            colspan="4"
                            class="text-center border-t border-b p-2 bg-gray-100"
                        >
                            <strong>Invoice Remaining</strong>
                        </td>
                    </tr>
                    <tr
                        v-for="record in remainingRecords"
                        :key="record.id"
                    >
                        <td v-text="record.product.name"></td>
                        <td v-html="recordQuantity(record)"></td>
                        <td v-text="record.price"></td>
                        <td v-text="totalPrice(record)"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
