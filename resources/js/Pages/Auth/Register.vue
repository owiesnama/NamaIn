<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import { computed, watch } from 'vue';

const direction = computed(() => usePage().props.locale === 'ar' ? 'rtl' : 'ltr');

const appDomain = computed(() => {
    if (usePage().props.appDomain) return usePage().props.appDomain;
    const appName = String(usePage().props.appName || '').trim();
    const normalized = appName.toLowerCase().replace(/[^a-z0-9]/g, '');
    return normalized ? `${normalized}.test` : 'app.test';
});

const fullDomain = computed(() => form.tenant_slug ? `${form.tenant_slug}.${appDomain.value}` : '');

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    tenant_name: '',
    tenant_slug: '',
});

watch(() => form.tenant_name, (val) => {
    form.tenant_slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100';
</script>

<template>
    <Head :title="__('Register')" />

    <AuthenticationCard :dir="direction" max-width="max-w-2xl">
        <!-- Navbar -->
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

        <!-- Heading -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-black text-slate-900">{{ __('Create Your Organization') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('Start managing your business smartly and efficiently with Namain.') }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">

            <!-- ── Organization info ── -->
            <div class="space-y-4">
                <div class="flex items-center gap-3 rtl:flex-row-reverse">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-slate-800">{{ __('Organization Information') }}</h2>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 rtl:text-right">{{ __('Organization Name') }}</label>
                        <input
                            v-model="form.tenant_name"
                            type="text"
                            required
                            autofocus
                            :placeholder="__('e.g. Namain Corp')"
                            :class="inputClass"
                        />
                        <InputError class="mt-1.5" :message="form.errors.tenant_name" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 rtl:text-right">{{ __('Subdomain') }}</label>
                        <div class="flex items-stretch" dir="ltr">
                            <div class="flex items-center rounded-l-xl border border-r-0 border-slate-200 bg-slate-100 px-3 text-sm text-slate-500 select-none">
                                {{ appDomain }}/
                            </div>
                            <input
                                v-model="form.tenant_slug"
                                type="text"
                                required
                                placeholder="my-org"
                                class="min-w-0 flex-1 rounded-r-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800 placeholder-slate-400 transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-100"
                            />
                        </div>
                        <p v-if="fullDomain" class="mt-1.5 text-xs font-medium text-emerald-600" dir="ltr">{{ fullDomain }}</p>
                        <InputError class="mt-1.5" :message="form.errors.tenant_slug" />
                    </div>
                </div>
            </div>

            <!-- ── Account info ── -->
            <div class="space-y-4">
                <div class="flex items-center gap-3 rtl:flex-row-reverse">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <h2 class="text-base font-bold text-slate-800">{{ __('Personal Account Information') }}</h2>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 rtl:text-right">{{ __('Full Name') }}</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            autocomplete="name"
                            :placeholder="__('Ahmed Mohammed')"
                            :class="inputClass"
                        />
                        <InputError class="mt-1.5" :message="form.errors.name" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 rtl:text-right">{{ __('Email') }}</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="name@company.com"
                            :class="inputClass"
                            dir="ltr"
                        />
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 rtl:text-right">{{ __('Password') }}</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 rtl:text-right">{{ __('Confirm Password') }}</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            :class="inputClass"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <div class="space-y-4 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-gradient-to-l from-emerald-600 to-emerald-400 py-3.5 text-base font-semibold text-white shadow-md shadow-emerald-200 transition hover:from-emerald-500 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span v-if="!form.processing">{{ __('Create Your Organization') }}</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ __('Creating...') }}
                    </span>
                </button>

                <p class="text-center text-sm text-slate-500">
                    {{ __('Already registered?') }}
                    <Link :href="route('login')" class="font-semibold text-emerald-600 transition hover:text-emerald-500">
                        {{ __('Sign In') }}
                    </Link>
                </p>
            </div>
        </form>

        <!-- Trust badges -->
        <template #below>
            <div class="mt-6 grid grid-cols-3 gap-4">
                <div v-for="badge in [
                    { icon: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z', label: __('Secure & Trusted'), desc: __('Your data is fully protected') },
                    { icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z', label: __('Quick Setup'), desc: __('Start in less than 60 seconds') },
                    { icon: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z', label: __('24/7 Support'), desc: __('Our team is always available') },
                ]" :key="badge.label"
                    class="flex flex-col items-center rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-100"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="badge.icon" />
                        </svg>
                    </div>
                    <p class="mt-2.5 text-sm font-semibold text-slate-800">{{ badge.label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ badge.desc }}</p>
                </div>
            </div>
        </template>
    </AuthenticationCard>
</template>
