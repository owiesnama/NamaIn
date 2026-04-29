<script setup>
    import { router, usePage } from "@inertiajs/vue3";
    import { ref } from "vue";

    const page = usePage();
    const stopping = ref(false);

    const stopImpersonating = () => {
        stopping.value = true;
        router.post(route("impersonate.stop"), {}, {
            onFinish: () => { stopping.value = false; },
        });
    };
</script>

<template>
    <div
        v-if="page.props.isImpersonating"
        class="fixed top-0 inset-x-0 z-50 bg-amber-500 text-amber-950"
    >
        <div class="flex items-center justify-center gap-x-4 px-4 py-2 text-sm font-medium">
            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <span>
                You are impersonating
                <strong>{{ page.props.impersonatingTenant }}</strong>
            </span>
            <button
                type="button"
                class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-1 text-xs font-semibold text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="stopping"
                @click="stopImpersonating"
            >
                {{ stopping ? 'Stopping...' : 'Stop Impersonating' }}
            </button>
        </div>
    </div>
</template>
