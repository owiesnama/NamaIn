<script setup>
    import { computed } from "vue";
    import { useForm, Link } from "@inertiajs/vue3";
    import AdminLayout from "@/Layouts/AdminLayout.vue";
    import InputError from "@/Components/InputError.vue";
    import SwitchCard from "@/Pages/Admin/Plans/Partials/SwitchCard.vue";
    import FeatureSection from "@/Pages/Admin/Plans/Partials/FeatureSection.vue";

    const props = defineProps({
        plan: { type: Object, default: null },
        catalog: Array,
    });

    const isEdit = computed(() => !!props.plan);

    const initialFeatures = () => {
        const values = {};
        props.catalog.forEach((f) => {
            const existing = props.plan?.features?.[f.key];
            values[f.key] = f.type === "boolean"
                ? existing === true || existing === 1
                : existing ?? null;
        });
        return values;
    };

    const form = useForm({
        key: props.plan?.key ?? "",
        name: { en: props.plan?.name?.en ?? "", ar: props.plan?.name?.ar ?? "" },
        description: { en: props.plan?.description?.en ?? "", ar: props.plan?.description?.ar ?? "" },
        is_active: props.plan?.is_active ?? true,
        is_default: props.plan?.is_default ?? false,
        sort: props.plan?.sort ?? 0,
        features: initialFeatures(),
    });

    // Group the catalog by feature group, preserving catalog order.
    const groups = computed(() => {
        const byGroup = {};
        props.catalog.forEach((f) => {
            if (! byGroup[f.group]) {
                byGroup[f.group] = [];
            }
            byGroup[f.group].push(f);
        });
        return byGroup;
    });

    const setFeature = (key, value) => {
        form.features[key] = value;
    };

    const submit = () => {
        if (isEdit.value) {
            form.put(route("admin.plans.update", props.plan.id));
        } else {
            form.post(route("admin.plans.store"));
        }
    };
</script>

<template>
    <AdminLayout :title="isEdit ? __('Edit plan') : __('New plan')">
        <form class="max-w-3xl mx-auto space-y-6" @submit.prevent="submit">
            <!-- Back link -->
            <Link
                :href="route('admin.plans.index')"
                class="inline-flex items-center gap-x-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
            >
                <svg class="h-4 w-4 rtl:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __('Back to plans') }}
            </Link>

            <!-- Header -->
            <div class="flex items-center gap-x-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400">
                    <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                        {{ isEdit ? __('Edit plan') : __('New plan') }}
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ isEdit ? __('Edit the plan details and available features.') : __('Define the plan details and available features.') }}
                    </p>
                </div>
            </div>

            <!-- Plan information -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Plan information') }}</h3>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="flex items-baseline gap-x-2 text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">
                            {{ __('Key') }}
                            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">{{ __('English, unique') }}</span>
                        </label>
                        <input v-model="form.key" type="text" dir="ltr" placeholder="free" class="mt-1.5 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 placeholder-gray-400 dark:placeholder-gray-600" />
                        <InputError :message="form.errors.key" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Name (Arabic)') }}</label>
                            <input v-model="form.name.ar" type="text" dir="rtl" class="mt-1.5 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                            <InputError :message="form.errors['name.ar']" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Name (English)') }}</label>
                            <input v-model="form.name.en" type="text" dir="ltr" class="mt-1.5 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                            <InputError :message="form.errors['name.en']" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr_1fr] gap-4 sm:items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Sort') }}</label>
                            <input v-model.number="form.sort" type="number" min="0" class="mt-1.5 w-full px-3 py-2 text-sm text-center text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 tabular-nums" />
                            <InputError :message="form.errors.sort" class="mt-1" />
                        </div>
                        <SwitchCard
                            v-model="form.is_default"
                            :label="__('Default plan')"
                            :hint="__('Applied when there is no active subscription.')"
                        />
                        <SwitchCard
                            v-model="form.is_active"
                            :label="__('Active')"
                            :hint="__('Available for subscription.')"
                        />
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Features') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Enable features and set limits for each plan.') }}</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                        <FeatureSection
                            v-for="(items, group) in groups"
                            :key="group"
                            :group="group"
                            :items="items"
                            :values="form.features"
                            @change="setFeature"
                        />
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-x-3">
                <Link
                    :href="route('admin.plans.index')"
                    class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
                >
                    {{ __('Cancel') }}
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                    {{ __('Save plan') }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
