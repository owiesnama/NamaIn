<script setup>
    import { useForm } from "@inertiajs/vue3";
    import ActionMessage from "@/Components/ActionMessage.vue";
    import FormSection from "@/Components/FormSection.vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import Dropdown from "@/Components/Dropdown.vue";
    import DropdownLink from "@/Components/DropdownLink.vue";
    import { inject } from "vue";

    const preferences = inject("preferences");
    const form = useForm({
        timezone: preferences.timezone,
        dateFormat: preferences.dateFormat,
    });

    const updateDateInformation = () => {
        form.put(route("preferences.update"));
    };
</script>

<template>
    <FormSection @submitted="updateDateInformation">
        <template #title>{{ __("Date Information") }}</template>

        <template #description>
            {{ __("Update your Date information and Time Zone") }}.
        </template>

        <template #form>
            <!-- Timezone -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel
                    for="timezone"
                    :value="__('Timezone')"
                />
                <Dropdown
                    align="left"
                    width="64"
                    class="inline-block mt-1"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 mt-1 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-gray-200 rounded-lg gap-x-2 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="w-5 h-5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>

                            {{ form.timezone || "+2:00 Khartoum, Sudan" }}
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink
                            as="button"
                            @click="form.timezone = '+4:00'"
                        >
                            (GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi
                        </DropdownLink>

                        <div class="border-t border-gray-100" />

                        <DropdownLink
                            as="button"
                            @click="form.timezone = '+3:00'"
                        >
                            (GMT +3:00) Mecaa, Saudi Arabia
                        </DropdownLink>

                        <div
                            class="border-t border-gray-100"
                            @click="form.timezone = '+2:00'"
                        />

                        <DropdownLink as="button">
                            (GMT +2:00) Khartoum, Sudan
                        </DropdownLink>
                    </template>
                </Dropdown>
                <InputError
                    :message="form.errors.timezone"
                    class="mt-2"
                />
            </div>

            <!-- Date Format -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel
                    for="date_format"
                    :value="__('Date Format')"
                />
                <Dropdown
                    align="left"
                    width="48"
                    class="inline-block mt-1"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-2 mt-1 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-gray-200 rounded-lg gap-x-2 focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50 focus:outline-none"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="w-5 h-5"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z"
                                />
                            </svg>

                            {{ form.dateFormat || "MM/DD/YY" }}
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink
                            as="button"
                            @click="form.dateFormat = 'Month D, Y'"
                        >
                            Month D, Y
                        </DropdownLink>

                        <div class="border-t border-gray-100" />

                        <DropdownLink
                            as="button"
                            @click="form.dateFormat = 'YY/MM/DD'"
                        >
                            YY/MM/DD
                        </DropdownLink>

                        <div class="border-t border-gray-100" />

                        <DropdownLink
                            as="button"
                            @click="form.dateFormat = 'DD/MM/YY'"
                        >
                            DD/MM/YY
                        </DropdownLink>

                        <div class="border-t border-gray-100" />

                        <DropdownLink
                            as="button"
                            @click="form.dateFormat = 'MM/DD/YY'"
                        >
                            MM/DD/YY
                        </DropdownLink>
                    </template>
                </Dropdown>
                <InputError
                    :message="form.errors.date_format"
                    class="mt-2"
                />
            </div>
        </template>

        <template #actions>
            <ActionMessage
                :on="form.recentlySuccessful"
                class="mr-3"
            >
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
