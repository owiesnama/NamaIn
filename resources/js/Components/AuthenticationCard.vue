<template>
    <div class="min-h-screen bg-[#F4F9F6]" :dir="dir" v-bind="$attrs">

        <!-- Navbar -->
        <header class="sticky top-0 z-50 border-b border-slate-100 bg-white/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <slot name="nav-brand" />
                <slot name="nav-actions" />
            </div>
        </header>

        <!-- Page content -->
        <main class="flex min-h-[calc(100vh-65px)] flex-col items-center justify-start px-4 py-12">
            <div class="w-full" :class="maxWidth">
                <!-- Card -->
                <div class="rounded-3xl bg-white px-8 py-10 shadow-sm ring-1 ring-slate-100 sm:px-10">
                    <slot />
                </div>

                <!-- Below-card slot -->
                <slot name="below" />
            </div>
        </main>

    </div>
</template>

<script setup>
defineProps({
    maxWidth: {
        type: String,
        default: 'max-w-md',
    },
    dir: {
        type: String,
        default: 'ltr',
    },
});
</script>
