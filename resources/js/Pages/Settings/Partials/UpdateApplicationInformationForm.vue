<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    _method: 'PUT',
    logo: null,
    invoices_headline: null,
    alerts: null,
    language: null,
    currency: null,
    pecentage: null,
});

const logoPreview = ref(null);
const logoInput = ref(null);
const alertsToggle = ref(true);

const updateApplicationInformation = () => {
    if (logoInput.value) {
        form.logo = logoInput.value.files[0];
    }
};

const selectNewLogo = () => {
    logoInput.value.click();
};

const updateLogoPreview = () => {
    const logo = logoInput.value.files[0];

    if (! logo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        logoPreview.value = e.target.result;
    };

    reader.readAsDataURL(logo);
};
</script>

<template>
    <FormSection @submitted="updateApplicationInformation">
        <template #title>
            Application Information
        </template>

        <template #description>
            Update your Application information and Basic Data.
        </template>

        <template #form>
            <!-- Logo logo -->
            <div class="col-span-6 sm:col-span-4">
                <!-- Logo logo File Input -->
                <input
                    ref="logoInput"
                    type="file"
                    class="hidden"
                    @change="updateLogoPreview"
                >

                <InputLabel for="logo" value="Logo" />

                <!-- Current Profile Logo -->
                <div v-show="! logoPreview" class="mt-2">
                    <img src="/images/logo.svg" alt="App Logo" class="object-cover w-12 h-12 rounded-full">
                </div>

                <!-- New Logo Preview -->
                <div v-show="logoPreview" class="mt-2">
                    <span
                        class="block w-12 h-12 bg-center bg-no-repeat bg-cover rounded-full"
                        :style="'background-image: url(\'' + logoPreview + '\');'"
                    />
                </div>

                <SecondaryButton class="mt-2 mr-2" type="button" @click.prevent="selectNewLogo">
                    Select A New Logo
                </SecondaryButton>

                <InputError :message="form.errors.logo" class="mt-2" />
            </div>

            <!-- Invoices Headline -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="invoices_headline" value="Invoices Headline" />
                <textarea name="invoices_headline" v-model="form.invoices_headline" id="invoices_headline" class="w-full h-32 px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"></textarea>
                <InputError :message="form.errors.invoices_headline" class="mt-2" />
            </div>

            <!-- Alerts -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="alerts" value="Alerts" />
                <div class="flex items-center cursor-pointer" @click="alertsToggle =!alertsToggle">
                    <div class="relative w-10 h-5 transition duration-200 ease-linear rounded-full"
                        :class="[alertsToggle ? 'bg-emerald-500' : 'bg-gray-300']">
                        <label for="alertsToggle"
                            @click="alertsToggle =!alertsToggle"
                            class="absolute left-0 w-5 h-5 mb-2 transition duration-100 ease-linear transform bg-white border-2 rounded-full cursor-pointer"
                            :class="[alertsToggle ? 'translate-x-full border-emerald-500' : 'translate-x-0 border-gray-300']"></label>
                        <input type="checkbox" name="alertsToggle" class="hidden w-full h-full rounded-full appearance-none active:outline-none focus:outline-none"/>
                    </div>

                    <p class="mx-3 text-sm text-gray-500">Send Notifications when Stocks Running out</p>
                </div>
                <InputError :message="form.errors.alerts" class="mt-2" />
            </div>

            <!-- Language -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="language" value="Language" />
                <div class="inline-flex mt-1 overflow-hidden bg-white border border-gray-200 divide-x rounded-lg rtl:flex-row-reverse ">
                    <button class="px-4 py-2 text-sm font-medium text-gray-600 transition-colors duration-200 sm:text-base sm:px-6 hover:bg-gray-100">
                        Arabic
                    </button>

                    <button class="px-4 py-2 text-sm font-medium text-white transition-colors duration-200 bg-emerald-500 sm:text-base sm:px-6 hover:bg-emerald-400">
                        English
                    </button>
                </div>
                <InputError :message="form.errors.language" class="mt-2" />
            </div>

            <!-- Pecentage -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="pecentage" value="Pecentage (%)" />
                <TextInput
                    id="pecentage"
                    v-model="form.pecentage"
                    type="number"
                    min="0"
                    max="100"
                    placeholder="60"
                    class="block w-full mt-1"
                    autocomplete="pecentage"
                />
                <InputError :message="form.errors.pecentage" class="mt-2" />
            </div>

            <!-- currency -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="currency" value="Currency" />
                <Dropdown align="left" width="48" class="inline-block mt-1" >
                    <template #trigger>
                        <button type="button" class="inline-flex items-center px-3 py-2 mt-1 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-gray-200 rounded-lg gap-x-2 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>

                            US Dollar
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink as="button">
                            US Dollar
                        </DropdownLink>

                        <div class="border-t border-gray-100" />

                        <DropdownLink as="button">
                            Euro
                        </DropdownLink>

                        <div class="border-t border-gray-100" />

                        <DropdownLink as="button">
                            SDG
                        </DropdownLink>
                    </template>
                </Dropdown>
                <InputError :message="form.errors.currency" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="mr-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
