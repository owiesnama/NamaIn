<script setup>
    /**
     * A single settings card: a titled (optionally described) surface with a
     * header bar and a content slot. `id` anchors the card so the section nav
     * can scroll to it; `scroll-mt` offsets for the sticky page header.
     *
     * Colours use the theme-aware semantic tokens (surface / line / primary /
     * secondary), which flip under the OS dark-mode media query.
     */
    defineProps({
        id: { type: String, default: undefined },
        title: { type: String, required: true },
        description: { type: String, default: "" },
    });
</script>

<template>
    <section
        :id="id"
        class="scroll-mt-24 rounded-xl border border-line bg-surface"
    >
        <div class="border-b border-line px-6 py-4">
            <h2 class="text-base font-semibold text-primary">
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="mt-1 text-sm text-secondary"
            >
                {{ description }}
            </p>
        </div>

        <div class="p-6">
            <slot />
        </div>
    </section>
</template>
