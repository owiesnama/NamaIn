<script setup>
    import { useForm } from "@inertiajs/vue3";
    import DatePicker from "@/Components/DatePicker.vue";

    const props = defineProps({
        tenant: { type: Object, required: true },
        subscription: { type: Object, default: null },
        plans: { type: Array, default: () => [] },
    });

    const form = useForm({
        plan_id: props.subscription?.plan_id ?? props.plans[0]?.id ?? null,
        trial_ends_at: null,
    });

    const assignPlan = () => {
        form.put(route("admin.tenants.subscription", props.tenant.id), { preserveScroll: true });
    };
</script>

<template>
    <form
        class="flex flex-wrap items-end gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700"
        @submit.prevent="assignPlan"
    >
        <div class="flex-1 min-w-[180px]">
            <label class="block mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 rtl:text-right">
                {{ __('Plan') }}
            </label>
            <select
                v-model="form.plan_id"
                class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
            >
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 rtl:text-right">
                {{ __('Trial until') }}
                <span class="text-gray-400 dark:text-gray-500 font-normal">({{ __('optional') }})</span>
            </label>
            <DatePicker
                v-model="form.trial_ends_at"
                :placeholder="__('Select a trial end date')"
                :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y' }"
            />
        </div>
        <div class="shrink-0">
            <span class="block mb-1.5 text-xs font-medium select-none invisible" aria-hidden="true">&nbsp;</span>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center gap-x-2 px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                </svg>
                {{ __('Assign plan') }}
            </button>
        </div>
    </form>
</template>
