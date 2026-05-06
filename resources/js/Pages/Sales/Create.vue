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
    <AppLayout :title="__('New Sales')">
        <InvoiceForm
            type="sale"
            :payment-methods="payment_methods"
            :banks="banks"
            :treasury-accounts="treasury_accounts"
        />
    </AppLayout>
</template>
