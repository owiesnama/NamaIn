<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from "@/Components/AuthenticationCard.vue";
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import { computed } from 'vue';

defineProps({
    tenants: Array,
});

const form = useForm({});

const fallbackDomain = computed(() => {
    const appName = String(usePage().props.appName ?? '').trim();
    const normalized = appName.toLowerCase().replace(/[^a-z0-9]/g, '');

    return normalized ? `${normalized}.test` : 'app.test';
});

const selectTenant = (tenant) => {
    form.post(route('tenants.switch', { tenant: tenant.slug }, false));
};
</script>

<template>
    <Head :title="__('Select Organization')" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
            <h2 class="mt-6 text-xl font-bold text-center text-gray-800">
                {{ __('Select Organization') }}
            </h2>
            <p class="mt-2 text-sm text-center text-gray-500">
                {{ __('Choose which organization to work with') }}
            </p>
        </template>

        <div class="space-y-3">
            <button
                v-for="tenant in tenants"
                :key="tenant.id"
                type="button"
                @click="selectTenant(tenant)"
                :disabled="form.processing"
                class="flex items-center w-full px-4 py-3 text-left border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-indigo-300 transition group"
                :class="{ 'opacity-60 cursor-not-allowed': form.processing }"
            >
                <div class="flex items-center justify-center w-10 h-10 text-sm font-bold text-indigo-600 bg-indigo-100 rounded-full">
                    {{ tenant.name.charAt(0).toUpperCase() }}
                </div>
                <div class="ml-3">
                    <div class="text-sm font-medium text-gray-900 group-hover:text-indigo-600">
                        {{ tenant.name }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ tenant.slug }}.{{ $page.props.appDomain || fallbackDomain }}
                    </div>
                </div>
            </button>
        </div>
    </AuthenticationCard>
</template>

