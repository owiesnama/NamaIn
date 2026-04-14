<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm();

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="__('Email Verification')" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />

            <h2 class="mt-6 text-xl font-bold text-center text-gray-800">{{ __('Email Verification') }}</h2>
        </template>

        <div class="mb-4 text-sm text-gray-600">
            {{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you did not receive the email, we will gladly send you another.') }}
        </div>

        <div v-if="verificationLinkSent" class="mb-4 text-sm font-medium text-green-600">
            {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ __('Resend Verification Email') }}
                </PrimaryButton>

                <div>
                    <Link
                        :href="route('profile.show')"
                        class="text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        {{ __('Edit Profile') }}</Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="ml-2 text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        {{ __('Log Out') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
