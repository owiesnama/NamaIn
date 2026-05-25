<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InvoiceForm from "@/Components/Invoicing/InvoiceForm.vue";

defineProps({
    invoice: Object,
    payment_methods: Object,
    banks: Array,
    treasury_accounts: Array,
    prefill: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <AppLayout :title="__('Edit Sale')">
        <InvoiceForm
            type="sale"
            mode="edit"
            :invoice="invoice"
            :payment-methods="payment_methods"
            :banks="banks"
            :treasury-accounts="treasury_accounts"
            :prefill="prefill"
        />
    </AppLayout>
</template>
