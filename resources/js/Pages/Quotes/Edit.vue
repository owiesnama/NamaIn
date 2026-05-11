<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import QuoteForm from "@/Components/Quotes/QuoteForm.vue";

defineProps({
    quote: Object,
});
</script>

<template>
    <AppLayout :title="__('Edit Quote')">
        <QuoteForm :quote="quote" />
    </AppLayout>
</template>
