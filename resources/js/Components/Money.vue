<script setup>
// Renders a monetary value in the tenant's numeral system, isolation-safe.
//
// useCurrency embeds LRI…PDI around Latin output so it survives the RTL layout as
// a bare text node; Arabic output self-isolates. So this is a thin, greppable
// wrapper — prefer it in new code, but any {{ formatCurrency(x) }} from
// useCurrency is equally safe.
import { computed } from "vue";
import { useCurrency } from "@/Composables/useCurrency";

const props = defineProps({
    // The amount. Null/undefined renders the em dash via formatAmount.
    value: { type: [Number, String], default: 0 },
    // Omit the currency symbol — for tables that carry the currency in the header.
    bare: { type: Boolean, default: false },
    // Override the currency code (defaults to the tenant/invoice currency).
    currency: { type: String, default: null },
    as: { type: String, default: "span" },
});

const { formatCurrency, formatAmount } = useCurrency();

const text = computed(() =>
    props.bare ? formatAmount(props.value) : formatCurrency(Number(props.value) || 0, props.currency),
);
</script>

<template>
    <component :is="as">{{ text }}</component>
</template>
