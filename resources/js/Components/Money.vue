<script setup>
// Renders a monetary value in the tenant's numeral system, isolated correctly.
//
// Isolation is not a constant: Latin output needs dir="ltr" + unicode-bidi:isolate
// or its leading minus reorders ("-20" -> "20-"), while Arabic-Indic output from
// Intl already carries its own bidi controls (U+061C, U+200F) and is *broken* by
// being forced LTR. Pairing those two decisions at every call site is the bug
// class this component exists to remove — always render money through <Money>.
import { computed } from "vue";
import { useCurrency, needsLtrIsolation } from "@/Composables/useCurrency";

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

const isolate = computed(() => needsLtrIsolation());
</script>

<template>
    <component :is="as" v-if="isolate" dir="ltr" class="ltr-isolate">{{ text }}</component>
    <component :is="as" v-else>{{ text }}</component>
</template>
