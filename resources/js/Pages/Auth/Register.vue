<script setup>
    import { Head, Link, useForm } from "@inertiajs/vue3";
    import AuthenticationCard from "@/Components/AuthenticationCard.vue";
    import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
    import Checkbox from "@/Components/Checkbox.vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";
    import { computed } from "vue";

    const direction = computed(() => {
        return preferences("language", "en") == "en" ? "ltr" : "rtl";
    });

    const form = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        terms: false,
    });

    const submit = () => {
        form.post(route("register"), {
            onFinish: () => form.reset("password", "password_confirmation"),
        });
    };
</script>

<template>
    <Head :title="__('Register')" />

    <AuthenticationCard :dir="direction">
        <template #logo>
            <AuthenticationCardLogo />

            <h2 class="mt-6 text-xl font-bold text-center text-gray-800">
                {{__('Sign Up')}}
            </h2>
        </template>

        <form @submit.prevent="submit">
            <div>
                <InputLabel
                    for="name"
                    :value="__('Name')"
                />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="block w-full mt-1"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.name"
                />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="email"
                    :value="__('Email')"
                />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="block w-full mt-1"
                    required
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.email"
                />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password"
                    :value="__('Password')"
                />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="block w-full mt-1"
                    required
                    autocomplete="new-password"
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.password"
                />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    :value="__('Confirm Password')"
                />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="block w-full mt-1"
                    required
                    autocomplete="new-password"
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>


            <div class="mt-6">
                <PrimaryButton
                    class="w-full"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{__('Register')}}
                </PrimaryButton>

                <Link
                    :href="route('login')"
                    class="inline-flex mt-4 text-sm text-gray-600 underline hover:text-gray-900"
                >
                    {{__('Already registered?')}}
                </Link>
            </div>
        </form>
    </AuthenticationCard>
</template>
