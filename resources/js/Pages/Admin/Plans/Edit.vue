<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    plan: { type: Object, default: null },
    catalog: Array,
});

const isEdit = computed(() => !!props.plan);

const initialFeatures = () => {
    const values = {};
    props.catalog.forEach((f) => {
        const existing = props.plan?.features?.[f.key];
        values[f.key] = f.type === 'boolean'
            ? existing === true || existing === 1
            : existing ?? null;
    });
    return values;
};

const form = useForm({
    key: props.plan?.key ?? '',
    name: { en: props.plan?.name?.en ?? '', ar: props.plan?.name?.ar ?? '' },
    description: { en: props.plan?.description?.en ?? '', ar: props.plan?.description?.ar ?? '' },
    is_active: props.plan?.is_active ?? true,
    is_default: props.plan?.is_default ?? false,
    sort: props.plan?.sort ?? 0,
    features: initialFeatures(),
});

const groups = computed(() => {
    const byGroup = {};
    props.catalog.forEach((f) => {
        (byGroup[f.group] ??= []).push(f);
    });
    return byGroup;
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.plans.update', props.plan.id));
    } else {
        form.post(route('admin.plans.store'));
    }
};
</script>

<template>
    <AdminLayout :title="isEdit ? __('Edit plan') : __('New plan')">
        <form @submit.prevent="submit" class="max-w-3xl mx-auto px-4 py-6 space-y-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
                {{ isEdit ? __('Edit plan') : __('New plan') }}
            </h2>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Key') }}</label>
                    <input v-model="form.key" type="text" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                    <InputError :message="form.errors.key" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Name (English)') }}</label>
                        <input v-model="form.name.en" type="text" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                        <InputError :message="form.errors['name.en']" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 rtl:text-right">{{ __('Name (Arabic)') }}</label>
                        <input v-model="form.name.ar" type="text" dir="rtl" class="mt-1 w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                        <InputError :message="form.errors['name.ar']" class="mt-1" />
                    </div>
                </div>

                <div class="flex items-center gap-x-6">
                    <label class="inline-flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="form.is_active" type="checkbox" class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                        {{ __('Active') }}
                    </label>
                    <label class="inline-flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="form.is_default" type="checkbox" class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                        {{ __('Default plan') }}
                    </label>
                    <div class="flex items-center gap-x-2">
                        <label class="text-sm text-gray-700 dark:text-gray-300">{{ __('Sort') }}</label>
                        <input v-model="form.sort" type="number" min="0" class="w-20 px-2 py-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Features') }}</h3>
                </div>
                <div class="p-6 space-y-6">
                    <div v-for="(items, group) in groups" :key="group">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ group }}</p>
                        <div class="space-y-3">
                            <div v-for="f in items" :key="f.key" class="flex items-center justify-between">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ f.label }}</span>
                                <label v-if="f.type === 'boolean'" class="inline-flex items-center">
                                    <input v-model="form.features[f.key]" type="checkbox" class="border-gray-300 dark:border-gray-600 rounded text-emerald-600 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                                </label>
                                <input
                                    v-else
                                    v-model.number="form.features[f.key]"
                                    type="number"
                                    min="0"
                                    :placeholder="__('Unlimited')"
                                    class="w-32 px-2 py-1 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-x-3">
                <Link :href="route('admin.plans.index')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                    {{ __('Cancel') }}
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-normal text-white bg-emerald-600 border border-transparent rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                >
                    {{ __('Save') }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
