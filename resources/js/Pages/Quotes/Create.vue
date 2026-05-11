<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import QuoteForm from "@/Components/Quotes/QuoteForm.vue";
</script>

<template>
    <AppLayout :title="__('Create Quote')">
        <QuoteForm />
    </AppLayout>
</template>
