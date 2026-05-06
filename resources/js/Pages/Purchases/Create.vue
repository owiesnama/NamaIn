<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import InvoiceForm from "@/Components/Invoicing/InvoiceForm.vue";

    defineProps({
        payment_methods: Object,
        banks: Array,
        treasury_accounts: Array,
    });
</script>

<template>
    <AppLayout :title="__('New Purchase')">
        <InvoiceForm
            type="purchase"
            :payment-methods="payment_methods"
            :banks="banks"
            :treasury-accounts="treasury_accounts"
        />
    </AppLayout>
</template>
