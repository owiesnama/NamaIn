<script setup>
    import { ref } from "vue";
    import { useForm } from "@inertiajs/vue3";
    import ActionMessage from "@/Components/ActionMessage.vue";
    import FormSection from "@/Components/FormSection.vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";
    const alertsToggle = ref(preferences('alerts', true));

    const form = useForm({
        logo: preferences('logo'),
        invoicesHeadline: preferences('invoicesHeadline'),
        alerts: alertsToggle.value,
        currency: preferences('currency', 'SDG'),
        pecentage: preferences('pecentage', 60),
    });

    const logoPreview = ref(null);
    const logoInput = ref(null);

    const updateApplicationInformation = () => {
        form.alerts = alertsToggle.value;
        if (logoInput.value && logoInput.value.files[0]) {
            form.logo = logoInput.value.files[0];
        }

        form.post(route("preferences.update"), {
            onSuccess: () => {
                logoPreview.value = null;
                if (logoInput.value) {
                    logoInput.value.value = null;
                }
            },
        });
    };

    const selectNewLogo = () => {
        logoInput.value.click();
    };

    const updateLogoPreview = () => {
        const logo = logoInput.value.files[0];
        const reader = new FileReader();

        if (!logo) return;
        reader.onload = (e) => (logoPreview.value = e.target.result);
        reader.readAsDataURL(logo);
    };
</script>

<template>
    <FormSection @submitted="updateApplicationInformation">
        <template #title> {{ __("Application Information") }} </template>

        <template #description>
            {{ __("Update your Application information and Basic Data") }}.
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
                />

                <InputLabel
                    for="logo"
                    :value="__('Logo')"
                />

                <!-- Current Profile Logo -->
                <div
                    v-show="!logoPreview"
                    class="mt-2"
                >
                    <img
                        :src="preferences('logo', '/images/logo.svg')"
                        alt="App Logo"
                        class="object-contain w-12 h-12"
                    />
                </div>

                <!-- New Logo Preview -->
                <div
                    v-show="logoPreview"
                    class="mt-2"
                >
                    <img
                        :src="logoPreview"
                        class="object-contain w-12 h-12"
                    />
                </div>

                <SecondaryButton
                    class="mt-2 mr-2"
                    type="button"
                    @click.prevent="selectNewLogo"
                >
                    {{ __("Select A New Logo") }}
                </SecondaryButton>

                <InputError
                    :message="form.errors.logo"
                    class="mt-2"
                />
            </div>

            <!-- Invoices Headline -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel
                    for="invoicesHeadline"
                    :value="__('Invoices Headline')"
                />
                <textarea
                    id="invoicesHeadline"
                    v-model="form.invoicesHeadline"
                    name="invoicesHeadline"
                    class="w-full h-32 px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                ></textarea>
                <InputError
                    :message="form.errors.invoicesHeadline"
                    class="mt-2"
                />
            </div>

            <!-- Alerts -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel
                    for="alerts"
                    :value="__('Alerts')"
                />
                <div
                    class="flex items-center cursor-pointer"
                    @click="alertsToggle = !alertsToggle"
                >
                    <div
                        class="relative w-10 h-5 transition duration-200 ease-linear rounded-full"
                        :class="[
                            alertsToggle ? 'bg-emerald-500' : 'bg-gray-300',
                        ]"
                    >
                        <label
                            for="alertsToggle"
                            class="absolute left-0 w-5 h-5 mb-2 transition duration-100 ease-linear transform bg-white border-2 rounded-full cursor-pointer"
                            :class="[
                                alertsToggle
                                    ? 'translate-x-full border-emerald-500'
                                    : 'translate-x-0 border-gray-300',
                            ]"
                            @click="alertsToggle = !alertsToggle"
                        ></label>
                        <input
                            type="checkbox"
                            name="alertsToggle"
                            class="hidden w-full h-full rounded-full appearance-none active:outline-none focus:outline-none"
                        />
                    </div>

                    <p class="mx-3 text-sm text-gray-500">
                        {{ __("Send Notifications When Stocks Running Out") }}
                    </p>
                </div>
                <InputError
                    :message="form.errors.alerts"
                    class="mt-2"
                />
            </div>

            <!-- Pecentage -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel
                    for="pecentage"
                    :value="__('Margin Revenu Percentage') + ' ' + '(%)'"
                />
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
                <InputError
                    :message="form.errors.pecentage"
                    class="mt-2"
                />
            </div>

            <!-- currency (System Wide SDG) -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel
                    for="currency"
                    :value="__('Currency')"
                />
                <div class="flex gap-x-2 mt-1">
                    <TextInput
                        id="currency"
                        v-model="form.currency"
                        type="text"
                        class="block w-full uppercase bg-gray-50"
                        maxlength="3"
                        readonly
                        :placeholder="__('SDG')"
                    />
                </div>
                <InputError
                    :message="form.errors.currency"
                    class="mt-2"
                />
            </div>
        </template>

        <template #actions>
            <ActionMessage
                :on="form.recentlySuccessful"
                class="mr-3"
            >
                {{ __("Saved.") }}
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
