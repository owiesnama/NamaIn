<script setup>
// Isolates LTR content (numbers, currency, signs, IDs, dates, emails, URLs)
// from the surrounding RTL layout so the bidirectional algorithm doesn't
// reorder punctuation/signs (e.g. "-15" rendering as "15-", or a leading ".").
defineProps({
    as: { type: String, default: "span" },
});
</script>

<template>
    <component :is="as" dir="ltr" class="ltr-isolate">
        <slot />
    </component>
</template>
