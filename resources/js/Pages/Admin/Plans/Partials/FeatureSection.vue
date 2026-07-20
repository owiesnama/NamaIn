<script setup>
    import ToggleField from "@/Components/ToggleField.vue";
    import LimitField from "@/Pages/Admin/Plans/Partials/LimitField.vue";

    defineProps({
        // Group key, e.g. "operations".
        group: { type: String, required: true },
        // Catalog items in this group: { key, type, label }.
        items: { type: Array, default: () => [] },
        // Current feature values keyed by feature key.
        values: { type: Object, required: true },
    });

    const emit = defineEmits(["change"]);
</script>

<template>
    <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-900">
        <div class="flex items-center gap-x-2 px-4 py-2.5 bg-gray-50 dark:bg-gray-800/40 border-b border-gray-200 dark:border-gray-700">
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500" dir="ltr">
                {{ group.toUpperCase() }}
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500">· {{ __(group) }}</span>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            <div
                v-for="item in items"
                :key="item.key"
                class="flex items-center justify-between gap-x-3 px-4 py-3"
            >
                <span class="text-sm text-gray-800 dark:text-gray-200">{{ item.label }}</span>
                <ToggleField
                    v-if="item.type === 'boolean'"
                    :model-value="values[item.key]"
                    @update:model-value="emit('change', item.key, $event)"
                />
                <LimitField
                    v-else
                    :model-value="values[item.key]"
                    @update:model-value="emit('change', item.key, $event)"
                />
            </div>
        </div>
    </div>
</template>
