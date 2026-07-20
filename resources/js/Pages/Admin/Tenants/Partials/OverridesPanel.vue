<script setup>
    import { computed } from "vue";
    import { useForm } from "@inertiajs/vue3";
    import DatePicker from "@/Components/DatePicker.vue";
    import Ltr from "@/Components/Ltr.vue";

    const props = defineProps({
        tenant: { type: Object, required: true },
        overrides: { type: Array, default: () => [] },
        entitlements: { type: Array, default: () => [] },
    });

    const form = useForm({
        feature_key: props.entitlements[0]?.key ?? null,
        value: "",
        expires_at: null,
    });

    const selectedFeature = computed(
        () => props.entitlements.find((e) => e.key === form.feature_key) ?? null
    );

    const featureLabel = (key) => props.entitlements.find((e) => e.key === key)?.label ?? key;

    const featureType = (key) => props.entitlements.find((e) => e.key === key)?.type;

    const isBooleanOverride = (ov) => featureType(ov.feature) === "boolean";

    const isOverrideEnabled = (ov) => ov.value === true || ov.value === 1 || ov.value === "1";

    // The stored value is JSON-cast, so a boolean feature reads as true/false — render
    // it as the enabled/disabled label (matching the design), not the raw literal.
    const overrideValueLabel = (ov) => {
        if (isBooleanOverride(ov)) {
            return isOverrideEnabled(ov) ? __("Enabled") : __("Disabled");
        }

        return ov.value === null ? __("Unlimited") : ov.value;
    };

    const saveOverride = () => {
        form.post(route("admin.tenants.overrides.store", props.tenant.id), { preserveScroll: true });
    };

    const removeOverride = (feature) => {
        useForm({}).delete(
            route("admin.tenants.overrides.destroy", [props.tenant.id, feature]),
            { preserveScroll: true }
        );
    };
</script>

<template>
    <div>
        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">
            {{ __('Overrides') }}
        </p>

        <!-- Current overrides -->
        <div v-if="overrides.length" class="space-y-2 mb-4">
            <div
                v-for="ov in overrides"
                :key="ov.feature"
                class="flex items-center gap-x-3 px-4 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700"
            >
                <span class="flex-1 text-sm font-medium text-gray-900 dark:text-white">
                    {{ featureLabel(ov.feature) }}
                </span>
                <span
                    class="inline-flex items-center px-2.5 py-0.5 text-[11px] font-bold rounded-full border"
                    :class="!ov.is_live
                        ? 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-800 dark:text-gray-500 dark:border-gray-700'
                        : (isBooleanOverride(ov) && !isOverrideEnabled(ov)
                            ? 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800')"
                >
                    <Ltr v-if="typeof ov.value === 'number'">{{ overrideValueLabel(ov) }}</Ltr>
                    <template v-else>{{ overrideValueLabel(ov) }}</template>
                    <span v-if="!ov.is_live" class="ms-1">· {{ __('expired') }}</span>
                </span>
                <button
                    type="button"
                    class="text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                    @click="removeOverride(ov.feature)"
                >
                    {{ __('Remove') }}
                </button>
            </div>
        </div>
        <p v-else class="mb-4 text-sm text-gray-400 dark:text-gray-500">{{ __('No overrides set.') }}</p>

        <!-- Add override -->
        <form
            class="flex flex-wrap items-center gap-3 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-dashed border-gray-300 dark:border-gray-600"
            @submit.prevent="saveOverride"
        >
            <select
                v-model="form.feature_key"
                class="flex-1 min-w-[150px] px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
            >
                <option v-for="f in entitlements" :key="f.key" :value="f.key">{{ f.label }}</option>
            </select>

            <label
                v-if="selectedFeature?.type === 'boolean'"
                class="inline-flex items-center gap-x-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer"
            >
                <input
                    v-model="form.value"
                    type="checkbox"
                    true-value="1"
                    false-value="0"
                    class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                />
                {{ __('Enabled') }}
            </label>
            <input
                v-else
                v-model="form.value"
                type="number"
                min="0"
                :placeholder="__('Unlimited')"
                class="w-28 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
            />

            <DatePicker
                v-model="form.expires_at"
                :placeholder="__('Expires (optional)')"
                :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y' }"
            />

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Add override') }}
            </button>
        </form>
    </div>
</template>
