<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    status: String,
});

const page = usePage();
const direction = computed(() => page.props.locale === 'ar' ? 'rtl' : 'ltr');

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head :title="__('Forgot Password')" />

    <AuthenticationCard :dir="direction">
        <template #nav-brand>
            <AuthenticationCardLogo />
        </template>
        <template #nav-actions>
            <div class="flex items-center gap-3">
                <Link :href="route('login')" class="text-sm font-medium text-slate-600 transition hover:text-emerald-600">
                    {{ __('Sign In') }}
                </Link>
                <Link :href="route('register')" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                    {{ __('Create Your Organization') }}
                </Link>
            </div>
        </template>

        <!-- Icon + heading -->
        <div class="mb-6 flex flex-col items-center text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-lg shadow-emerald-200">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ __('Forgot Password') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500">{{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}</p>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
                    <div class="flex shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3.5">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        placeholder="name@company.com"
                        dir="ltr"
                        class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                    />
                </div>
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-gradient-to-l from-emerald-600 to-emerald-400 py-3.5 text-base font-semibold text-white shadow-md shadow-emerald-200 transition hover:from-emerald-500 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="!form.processing">{{ __('Email Password Reset Link') }}</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ __('Sending...') }}
                    </span>
                </button>
            </div>

            <p class="text-center text-sm text-slate-500">
                {{ __("Don't have an account?") }}
                <Link :href="route('register')" class="font-semibold text-emerald-600 transition hover:text-emerald-500">
                    {{ __('Register now') }}
                </Link>
            </p>
        </form>
    </AuthenticationCard>
</template>
