<script setup>
import { useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    token: String,
    tenant: String,
    inviter: String,
    role: String,
    email: String,
});

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('invitations.accept.store', props.token), {
        onFinish: () => { form.password = ''; form.password_confirmation = ''; },
    });
};
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#F4F9F6] px-4 py-12">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="flex justify-center mb-8">
                <ApplicationLogo class="h-10 w-auto" />
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                <!-- Invitation header -->
                <div class="text-center mb-8">
                    <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 rounded-2xl bg-emerald-100 shadow-lg shadow-emerald-200">
                        <svg class="h-7 w-7 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-slate-900">{{ __("You're invited!") }}</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __(':inviter invited you to join :tenant as :role.', { inviter, tenant, role }) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">{{ email }}</p>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('Your Name') }}</label>
                        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
                            <div class="flex shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3.5">
                                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="__('John Doe')"
                                required
                                class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                            />
                        </div>
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('Password') }}</label>
                        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
                            <div class="flex shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3.5">
                                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input
                                v-model="form.password"
                                type="password"
                                :placeholder="__('Create a password')"
                                required
                                class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">{{ __('Confirm Password') }}</label>
                        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-emerald-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-100">
                            <div class="flex shrink-0 items-center border-e border-slate-200 bg-slate-100 px-3.5">
                                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                :placeholder="__('Confirm your password')"
                                required
                                class="min-w-0 flex-1 bg-transparent border-0 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-2 w-full rounded-xl bg-gradient-to-l from-emerald-600 to-emerald-400 py-3.5 text-base font-semibold text-white shadow-md shadow-emerald-200 transition hover:from-emerald-500 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? __('Joining…') : __('Accept & Join :tenant', { tenant }) }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
