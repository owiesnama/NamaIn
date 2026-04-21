<script setup>
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import { computed } from 'vue';

defineProps({
    tenants: Array,
});

const form = useForm({});

const direction = computed(() => usePage().props.locale === 'ar' ? 'rtl' : 'ltr');

const appDomain = computed(() => {
    return usePage().props.appDomain || usePage().props.appName?.toLowerCase().replace(/[^a-z0-9]/g, '') + '.test' || 'app.test';
});

const selectTenant = (tenant) => {
    form.post(route('tenants.switch', { tenant: tenant.slug }, false));
};
</script>

<template>
    <Head :title="__('Select Organization')" />

    <AuthenticationCard :dir="direction">
        <template #nav-brand>
            <AuthenticationCardLogo />
        </template>

        <!-- Header -->
        <div class="mb-6 flex flex-col items-center text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-lg shadow-emerald-200">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                </svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">{{ __('Welcome back') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500">{{ __('Choose a workspace to continue') }}</p>
        </div>

        <!-- Tenant list -->
        <div class="space-y-2.5">
            <button
                v-for="tenant in tenants"
                :key="tenant.id"
                type="button"
                @click="selectTenant(tenant)"
                :disabled="form.processing"
                class="group flex w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-start transition duration-150 hover:border-emerald-300 hover:bg-emerald-50/60 focus:outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <!-- Avatar segment -->
                <div class="flex w-12 shrink-0 items-center justify-center border-e border-slate-200 bg-slate-100 text-sm font-bold uppercase text-slate-500 transition-colors group-hover:border-emerald-200 group-hover:bg-emerald-100 group-hover:text-emerald-600">
                    {{ tenant.name.charAt(0) }}
                </div>

                <!-- Content -->
                <div class="min-w-0 flex-1 px-4 py-3">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ tenant.name }}</div>
                    <div class="mt-0.5 truncate text-xs text-right ltr:text-left text-slate-400" dir="ltr">{{ tenant.slug }}.{{ appDomain }}</div>
                </div>

                <!-- Chevron -->
                <div class="flex shrink-0 items-center pe-4">
                    <svg
                        class="h-4 w-4 text-slate-400 transition-colors group-hover:text-emerald-500 rtl:rotate-180"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
            </button>
        </div>
    </AuthenticationCard>
</template>
