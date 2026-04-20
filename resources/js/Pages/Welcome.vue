<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Welcome" />

    <div class="flex flex-col items-center justify-center min-h-screen bg-gray-50">
        <div class="w-full max-w-md px-8 py-12 text-center">
            <div class="mb-8">
                <img
                    :src="preferences('logo', '/images/logo.svg')"
                    alt="Logo"
                    class="object-contain w-16 h-16 mx-auto"
                />
                <h1 class="mt-4 text-3xl font-bold text-gray-900">
                    NamaIn
                </h1>
                <p class="mt-2 text-base text-gray-600">
                    {{ __('Manage your business, simplified.') }}
                </p>
            </div>

            <div class="space-y-3">
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="block w-full px-6 py-3 text-base font-semibold text-white bg-emerald-600 rounded-lg shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    {{ __('Create Your Organization') }}
                </Link>

                <Link
                    v-if="canLogin"
                    :href="route('login')"
                    class="block w-full px-6 py-3 text-base font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200"
                >
                    {{ __('Sign In') }}
                </Link>
            </div>
        </div>
    </div>
</template>
