<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    _method: 'PUT',
    logo: null,
    invoices_headline: null,
    alerts: null,
    currency: null,
    language: null,
    timezone: null,
    date_format: null,
});

const logoPreview = ref(null);
const logoInput = ref(null);

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

            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="block w-full mt-1"
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <!-- Email -->
            <!-- <div class="col-span-6 sm:col-span-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="block w-full mt-1"
                />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="mt-2 text-sm">
                        Your email address is unverified.

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="text-gray-600 underline hover:text-gray-900"
                            @click.prevent="sendEmailVerification"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>
                </div>
            </div> -->
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
