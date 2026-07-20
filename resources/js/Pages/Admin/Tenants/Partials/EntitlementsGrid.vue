<script setup>
    import Ltr from "@/Components/Ltr.vue";

    defineProps({
        entitlements: { type: Array, default: () => [] },
        columns: { type: Number, default: 2 },
    });

    const sourceTone = {
        override: "bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800",
        plan: "bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700",
        default: "bg-transparent text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-700",
    };
</script>

<template>
    <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">
            {{ __('Effective entitlements') }}
        </p>
        <div
            class="grid rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden"
            :class="columns === 1 ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2'"
        >
            <div
                v-for="e in entitlements"
                :key="e.key"
                class="flex items-center gap-x-3 px-4 py-2.5 border-b border-gray-100 dark:border-gray-800 last:border-b-0"
            >
                <span class="flex-1 text-sm text-gray-600 dark:text-gray-400 truncate">{{ e.label }}</span>
                <span
                    class="text-sm font-semibold"
                    :class="e.type === 'limit' && e.value === null
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-gray-900 dark:text-white'"
                >
                    <template v-if="e.type === 'boolean'">{{ e.value ? __('Enabled') : __('Disabled') }}</template>
                    <template v-else-if="e.value === null">{{ __('Unlimited') }}</template>
                    <Ltr v-else>{{ e.value }}</Ltr>
                </span>
                <span
                    class="inline-flex px-1.5 py-0.5 text-[9px] font-bold rounded-md border"
                    :class="sourceTone[e.source]"
                >
                    {{ __(e.source) }}
                </span>
            </div>
        </div>
    </div>
</template>
