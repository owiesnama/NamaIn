<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

const props = defineProps({
    status: String,
});

const page = usePage();
const direction = computed(() => page.props.locale === 'ar' ? 'rtl' : 'ltr');

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="__('Email Verification')" />

    <AuthenticationCard :dir="direction">
        <template #nav-brand>
            <AuthenticationCardLogo />
        </template>
        <template #nav-actions>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
            >
                {{ __('Log Out') }}
            </Link>
        </template>

        <!-- Icon + heading -->
        <div class="mb-6 flex flex-col items-center text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-lg shadow-emerald-200">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ __('Email Verification') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500">{{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you did not receive the email, we will gladly send you another.') }}</p>
        </div>

        <div v-if="verificationLinkSent" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
        </div>

        <form @submit.prevent="submit">
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-xl bg-gradient-to-l from-emerald-600 to-emerald-400 py-3.5 text-base font-semibold text-white shadow-md shadow-emerald-200 transition hover:from-emerald-500 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span v-if="!form.processing">{{ __('Resend Verification Email') }}</span>
                <span v-else class="flex items-center justify-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    {{ __('Sending...') }}
                </span>
            </button>
        </form>
    </AuthenticationCard>
</template>
