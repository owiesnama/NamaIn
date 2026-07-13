<script setup>
    /**
     * Shared shell for settings pages.
     *
     * Header and body live inside ONE max-width container so the sticky header
     * stays aligned with the content below it. Colours use the theme-aware
     * semantic tokens (surface / line / primary / secondary), which flip under
     * the OS dark-mode media query with no `dark:` variant needed.
     */
    defineProps({
        title: { type: String, required: true },
        subtitle: { type: String, default: "" },
    });
</script>

<template>
    <div class="mx-auto max-w-7xl">
        <!-- Sticky page header: title + subtitle on the start side, actions on the end. -->
        <header
            class="sticky top-0 z-10 flex items-center justify-between gap-x-4 border-b border-line bg-surface py-4"
        >
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold text-primary">
                    {{ title }}
                </h1>
                <p
                    v-if="subtitle"
                    class="mt-0.5 truncate text-sm text-secondary"
                >
                    {{ subtitle }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-x-3">
                <slot name="header-actions" />
            </div>
        </header>

        <!-- Body: section nav on the start side, content on the end.
             Single column below lg, two columns (150px nav + fluid content) at lg+. -->
        <div
            class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[150px_minmax(0,1fr)] lg:gap-8"
        >
            <aside class="lg:sticky lg:top-24 lg:self-start">
                <slot name="nav" />
            </aside>

            <div class="min-w-0 space-y-6">
                <slot />
            </div>
        </div>
    </div>
</template>
