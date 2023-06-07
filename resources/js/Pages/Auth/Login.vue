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

    defineProps({
        canResetPassword: Boolean,
        status: String,
    });
    const direction = computed(() => {
        return preferences("language", "en") == "en" ? "ltr" : "rtl";
    });
    const form = useForm({
        email: "",
        password: "",
        remember: false,
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            remember: form.remember ? "on" : "",
        })).post(route("login"), {
            onFinish: () => form.reset("password"),
        });
    };
</script>

<template>
    <Head :title="__('Log In')" />

    <AuthenticationCard :dir="direction">
        <template #logo>
            <AuthenticationCardLogo />

            <h2 class="mt-6 text-xl font-bold text-center text-gray-800">
                {{ __("Sign In") }}
            </h2>
        </template>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
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
                    autofocus
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
                    autocomplete="current-password"
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.password"
                />
            </div>

            <div
                class="flex items-center justify-between mt-4"
            >
                <div class="block">
                    <label class="flex items-center">
                        <Checkbox
                            v-model:checked="form.remember"
                            name="remember"
                        />
                        <span class="ml-2 rtl:mr-2 rtl:ml-0 text-sm text-gray-600">{{
                            __("Remember me")
                        }}</span>
                    </label>
                </div>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-gray-600 underline hover:text-gray-900"
                >
                    {{ __("Forgot your password ?") }}
                </Link>
            </div>

            <div class="mt-4">
                <PrimaryButton
                    class="w-full"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ __("Log In") }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
