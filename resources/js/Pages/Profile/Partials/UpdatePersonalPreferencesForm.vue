<script setup>
import { useForm, usePage } from "@inertiajs/vue3";
import ActionMessage from "@/Components/ActionMessage.vue";
import FormSection from "@/Components/FormSection.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const page = usePage();
const userPrefs = page.props.userPreferences ?? {};
const tenantPrefs = page.props.preferences ?? {};

const resolve = (key, fallback = '') => userPrefs[key] ?? tenantPrefs[key] ?? fallback;

const form = useForm({
    language: resolve('language', 'en'),
    timezone: resolve('timezone', '+2:00'),
    dateFormat: resolve('dateFormat', 'DD/MM/YY'),
});

const timezones = [
    { value: '+4:00', label: '(GMT +4:00) Abu Dhabi, Muscat, Baku' },
    { value: '+3:00', label: '(GMT +3:00) Mecca, Riyadh' },
    { value: '+2:00', label: '(GMT +2:00) Khartoum, Cairo' },
    { value: '+1:00', label: '(GMT +1:00) Algiers, Lagos' },
    { value: '+0:00', label: '(GMT +0:00) London, Casablanca' },
];

const dateFormats = [
    { value: 'Month D, Y', label: 'Month D, Y' },
    { value: 'YY/MM/DD', label: 'YY/MM/DD' },
    { value: 'DD/MM/YY', label: 'DD/MM/YY' },
    { value: 'MM/DD/YY', label: 'MM/DD/YY' },
];

const submit = () => {
    form.put(route('user-preferences.update'));
};
</script>

<template>
    <FormSection @submitted="submit">
        <template #title>{{ __("Personal Preferences") }}</template>

        <template #description>
            {{ __("Customize your personal display settings. These override the organization defaults for your account only.") }}
        </template>

        <template #form>
            <!-- Language -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="language" :value="__('Language')" />
                <select
                    id="language"
                    v-model="form.language"
                    class="mt-1 block w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                >
                    <option value="ar">العربية</option>
                    <option value="en">English</option>
                </select>
                <InputError :message="form.errors.language" class="mt-1" />
            </div>

            <!-- Timezone -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="timezone" :value="__('Timezone')" />
                <select
                    id="timezone"
                    v-model="form.timezone"
                    class="mt-1 block w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                >
                    <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                        {{ tz.label }}
                    </option>
                </select>
                <InputError :message="form.errors.timezone" class="mt-1" />
            </div>

            <!-- Date Format -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="dateFormat" :value="__('Date Format')" />
                <select
                    id="dateFormat"
                    v-model="form.dateFormat"
                    class="mt-1 block w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                >
                    <option v-for="df in dateFormats" :key="df.value" :value="df.value">
                        {{ df.label }}
                    </option>
                </select>
                <InputError :message="form.errors.dateFormat" class="mt-1" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                {{ __("Saved") }}.
            </ActionMessage>

            <PrimaryButton
                type="submit"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                {{ __("Save") }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
