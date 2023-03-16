<script setup>
    defineProps({
        invoice: Object,
    });
    let totalPrice = (record) => {
        return record.price * record.quantity;
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
                <tr v-for="record in invoice.details">
                    <td v-text="record.product.name"></td>
                    <td v-text="record.quantity"></td>
                    <td v-text="record.price"></td>
                    <td v-text="totalPrice(record)"></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
