<script setup>
    import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
    import AuthenticationCard from "@/Components/AuthenticationCard.vue";
    import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
    import InputError from "@/Components/InputError.vue";
    import { computed, ref } from "vue";

    defineProps({
        canResetPassword: Boolean,
        status: String
    });

    const direction = computed(() => usePage().props.locale === "ar" ? "rtl" : "ltr");
    const showPassword = ref(false);

    const form = useForm({
        email: "",
        password: "",
        remember: false
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            remember: form.remember ? "on" : ""
        })).post(route("login"), {
            onFinish: () => form.reset("password")
        });
    };
</script>

<template>
    <Head :title="__('Log In')" />

    <AuthenticationCard :dir="direction">
        <!-- Navbar -->
        <template #nav-brand>
            <AuthenticationCardLogo />
        </template>
        <template #nav-actions>
            <div class="flex items-center gap-3">
                <Link :href="route('login')"
                      class="text-sm font-medium text-slate-600 transition hover:text-emerald-600">
                    {{ __("Sign In") }}
                </Link>
                <Link :href="route('register')"
                      class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    {{ __("Create Your Organization") }}
                </Link>
            </div>
        </template>

        <!-- Status -->
        <div v-if="status"
             class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <!-- Icon -->
        <div class="mb-6 flex flex-col items-center text-center">
            <div
                class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-lg shadow-emerald-200">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ __("Welcome back") }}</h1>
            <p class="mt-1.5 text-sm text-slate-500">{{ __("Enter your credentials to access your dashboard") }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email -->
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">{{ __("Email") }}</label>
                <div
                    class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
                    <div class="flex shrink-0 items-center border-slate-200 bg-slate-100 px-3.5 border-e border-slate-200">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        placeholder="name@company.com"
                        class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <!-- Password -->
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-sm font-medium text-slate-700">{{ __("Password") }}</label>
                    <Link v-if="canResetPassword" :href="route('password.request')"
                          class="text-xs font-medium text-emerald-600 transition hover:text-emerald-500">
                        {{ __("Forgot your password ?") }}
                    </Link>
                </div>
                <div
                    class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
                    <div class="flex shrink-0 items-center border-slate-200 bg-slate-100 px-3.5 border-e border-slate-200">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                    </div>
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="flex shrink-0 items-center  border-slate-200 bg-slate-100 px-3.5 border-s border-slate-200 text-slate-400 transition hover:text-slate-600 focus:outline-none"
                    >
                        <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <!-- Submit -->
            <div class="pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-gradient-to-l from-emerald-600 to-emerald-400 py-3.5 text-base font-semibold text-white shadow-md shadow-emerald-200 transition hover:from-emerald-500 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="!form.processing">{{ __("Log In") }}</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ __("Signing in...") }}
                    </span>
                </button>
            </div>

            <p class="text-center text-sm text-slate-500">
                {{ __("Don't have an account?") }}
                <Link :href="route('register')"
                      class="font-semibold text-emerald-600 transition hover:text-emerald-500">
                    {{ __("Register now") }}
                </Link>
            </p>
        </form>

        <!-- Below card: trust logos -->
        <template #below>
            <div class="mt-8 text-center">
                <p class="mb-4 text-xs font-medium text-slate-400">{{ __("Trusted by leading companies") }}</p>
                <div class="flex items-center justify-center gap-4">
                    <div v-for="i in 3" :key="i"
                         class="flex h-10 w-16 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-slate-100">
                        <div class="h-4 w-10 rounded bg-slate-200" />
                    </div>
                </div>
            </div>
        </template>
    </AuthenticationCard>
</template>
