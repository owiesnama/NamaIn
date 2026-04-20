<script setup>
    import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
    import AuthenticationCard from "@/Components/AuthenticationCard.vue";
    import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";
    import { computed, watch } from "vue";

    const direction = computed(() => {
        return preferences("language", "en") == "en" ? "ltr" : "rtl";
    });

    const appDomain = computed(() => {
        return usePage().props.appDomain || 'namain.test';
    });

    const fullDomain = computed(() => {
        if (!form.tenant_slug) {
            return '';
        }
        return `${form.tenant_slug}.${appDomain.value}`;
    });

    const form = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        tenant_name: "",
        tenant_slug: "",
    });

    watch(() => form.tenant_name, (val) => {
        form.tenant_slug = val
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
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
                {{ __('Create Your Organization') }}
            </h2>
        </template>

        <form @submit.prevent="submit">
            <!-- Organization Details -->
            <div class="pb-4 mb-4 border-b border-gray-200">
                <h3 class="text-sm font-medium text-emerald-700 uppercase tracking-wide mb-3">
                    {{ __('Organization') }}
                </h3>

                <div>
                    <InputLabel for="tenant_name" :value="__('Organization Name')" />
                    <TextInput
                        id="tenant_name"
                        v-model="form.tenant_name"
                        type="text"
                        class="block w-full mt-1"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.tenant_name" />
                </div>

                <div class="mt-3">
                    <InputLabel for="tenant_slug" :value="__('Subdomain')" />
                    <div class="flex items-center mt-1" dir="ltr">
                        <TextInput
                            id="tenant_slug"
                            v-model="form.tenant_slug"
                            type="text"
                            class="block w-full rounded-r-none border-r-0"
                            required
                        />
                        <span class="inline-flex items-center px-3 py-2 text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-r-lg whitespace-nowrap">
                            .{{ appDomain }}
                        </span>
                    </div>
                    <p v-if="fullDomain" class="mt-1.5 text-xs text-emerald-600 font-medium">
                        {{ fullDomain }}
                    </p>
                    <InputError class="mt-2" :message="form.errors.tenant_slug" />
                </div>
            </div>

            <!-- User Details -->
            <h3 class="text-sm font-medium text-emerald-700 uppercase tracking-wide mb-3">
                {{ __('Account Details') }}
            </h3>

            <div>
                <InputLabel for="name" :value="__('Name')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="block w-full mt-1"
                    required
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="email" :value="__('Email')" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="block w-full mt-1"
                    required
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" :value="__('Password')" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="block w-full mt-1"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" :value="__('Confirm Password')" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="block w-full mt-1"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="mt-6">
                <PrimaryButton
                    class="w-full"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ __('Register') }}
                </PrimaryButton>

                <Link
                    :href="route('login')"
                    class="inline-flex mt-4 text-sm text-emerald-600 hover:text-emerald-700"
                >
                    {{ __('Already registered?') }}
                </Link>
            </div>
        </form>
    </AuthenticationCard>
</template>
