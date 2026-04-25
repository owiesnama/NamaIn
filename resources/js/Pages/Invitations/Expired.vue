<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    tenant: String,
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#F4F9F6] px-4 py-12">
        <div class="w-full max-w-md">
            <div class="flex justify-center mb-8">
                <ApplicationLogo class="h-10 w-auto" />
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
                <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-red-100">
                    <svg class="h-7 w-7 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-slate-900">{{ __('Invitation Expired') }}</h1>
                <p class="mt-2 text-sm text-slate-500">
                    {{ __('This invitation to :tenant has expired. Please ask to be invited again.', { tenant }) }}
                </p>
                <a
                    :href="route('login')"
                    class="mt-6 inline-flex items-center justify-center px-6 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors duration-200"
                >
                    {{ __('Go to Login') }}
                </a>
            </div>
        </div>
    </div>
</template>
