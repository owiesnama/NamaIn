<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { useForm } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

const props = defineProps({
    storage: Object,
});

const form = useForm({
    storage_id: props.storage.id,
    opening_float: 0,
});

const submit = () => {
    form.post(route('pos.open'));
};
</script>

<template>
    <AppLayout :title="__('Open POS Session')">
        <div class="max-w-md mx-auto mt-12 px-4">
            <div class="bg-white dark:bg-gray-900 p-8 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-600 dark:text-emerald-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615 3.001 3.001 0 0 0 3.75-.615 3.001 3.001 0 0 0 3.75.615V9.349m-11.25 0a4.5 4.5 0 0 1-4.477-4.451m0 0A4.486 4.486 0 0 1 3.75 4.5M3.75 21H3.75m0 0a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h16.5a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3M12 9.75V21" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    {{ __('Open POS Session') }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                    {{ __('Register') }}: <span class="font-semibold text-gray-900 dark:text-white">{{ storage.name }}</span>
                </p>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <InputLabel for="opening_float" :value="__('Opening Float')" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 ltr:left-0 rtl:right-0 flex items-center ltr:ps-3 rtl:pe-3 pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">SDG</span>
                            </div>
                            <TextInput
                                id="opening_float"
                                v-model="form.opening_float"
                                type="number"
                                step="0.01"
                                class="block w-full ltr:ps-12 rtl:pe-12"
                                required
                            />
                        </div>
                        <InputError :message="form.errors.opening_float" class="mt-2" />
                    </div>

                    <PrimaryButton :disabled="form.processing" class="w-full justify-center py-3.5 text-base rounded-xl">
                        {{ __('Open Register') }}
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
