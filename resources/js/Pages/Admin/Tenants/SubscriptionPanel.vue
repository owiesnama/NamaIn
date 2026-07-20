<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    tenant: Object,
    subscription: { type: Object, default: null },
    plans: { type: Array, default: () => [] },
    overrides: { type: Array, default: () => [] },
    entitlements: { type: Array, default: () => [] },
});

const planForm = useForm({
    plan_id: props.subscription?.plan_id ?? props.plans[0]?.id ?? null,
    trial_ends_at: null,
});

const overrideForm = useForm({
    feature_key: props.entitlements[0]?.key ?? null,
    value: '',
    expires_at: null,
});

const selectedFeature = ref(props.entitlements[0] ?? null);

const onFeatureChange = () => {
    selectedFeature.value = props.entitlements.find((e) => e.key === overrideForm.feature_key) ?? null;
};

const assignPlan = () => {
    planForm.put(route('admin.tenants.subscription', props.tenant.id), { preserveScroll: true });
};

const saveOverride = () => {
    overrideForm.post(route('admin.tenants.overrides.store', props.tenant.id), { preserveScroll: true });
};

const removeOverride = (feature) => {
    useForm({}).delete(route('admin.tenants.overrides.destroy', [props.tenant.id, feature]), { preserveScroll: true });
};
</script>

<template>
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Subscription & Features') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <template v-if="subscription">
                    {{ subscription.plan_name }} · {{ subscription.status }}
                </template>
                <template v-else>{{ __('No active subscription (falls back to the default plan).') }}</template>
            </p>
        </div>

        <div class="p-6 space-y-6">
            <!-- Assign plan -->
            <form @submit.prevent="assignPlan" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Plan') }}</label>
                    <select v-model="planForm.plan_id" class="mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Trial until (optional)') }}</label>
                    <input v-model="planForm.trial_ends_at" type="date" class="mt-1 px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg" />
                </div>
                <button type="submit" :disabled="planForm.processing" class="px-4 py-2 text-sm font-normal text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-50">{{ __('Assign plan') }}</button>
            </form>

            <!-- Overrides -->
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __('Overrides') }}</p>
                <div v-if="overrides.length" class="space-y-2 mb-3">
                    <div v-for="ov in overrides" :key="ov.feature" class="flex items-center justify-between text-sm">
                        <span :class="ov.is_live ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 line-through'">
                            {{ ov.feature }} = {{ ov.value }}
                            <span v-if="!ov.is_live" class="text-[10px]">({{ __('expired') }})</span>
                        </span>
                        <button type="button" @click="removeOverride(ov.feature)" class="text-red-600 dark:text-red-400 hover:text-red-700 text-xs">{{ __('Remove') }}</button>
                    </div>
                </div>
                <form @submit.prevent="saveOverride" class="flex flex-wrap items-end gap-3">
                    <select v-model="overrideForm.feature_key" @change="onFeatureChange" class="px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <option v-for="f in entitlements" :key="f.key" :value="f.key">{{ f.label }}</option>
                    </select>
                    <label v-if="selectedFeature?.type === 'boolean'" class="inline-flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="overrideForm.value" type="checkbox" true-value="1" false-value="0" class="border-gray-300 dark:border-gray-600 rounded text-emerald-600" />
                        {{ __('Enabled') }}
                    </label>
                    <input v-else v-model="overrideForm.value" type="number" min="0" :placeholder="__('Unlimited')" class="w-28 px-2 py-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg" />
                    <input v-model="overrideForm.expires_at" type="date" class="px-2 py-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg" />
                    <button type="submit" :disabled="overrideForm.processing" class="px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">{{ __('Add override') }}</button>
                </form>
            </div>

            <!-- Effective entitlements preview -->
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ __('Effective entitlements') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5">
                    <div v-for="e in entitlements" :key="e.key" class="flex items-center justify-between text-sm border-b border-gray-100 dark:border-gray-800 py-1">
                        <span class="text-gray-700 dark:text-gray-300">{{ e.label }}</span>
                        <span class="flex items-center gap-x-2">
                            <span class="font-medium text-gray-900 dark:text-white">
                                <template v-if="e.type === 'boolean'">{{ e.value ? __('On') : __('Off') }}</template>
                                <template v-else>{{ e.value === null ? __('Unlimited') : e.value }}</template>
                            </span>
                            <span class="px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 rounded-md">{{ e.source }}</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
