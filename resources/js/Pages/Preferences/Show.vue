<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import UpdateApplicationInformationForm from "@/Pages/Preferences/Partials/UpdateApplicationInformationForm.vue";
    import { provide } from "vue";
    const props = defineProps({
        preferences: {
            type: Object,
        },
    });
    provide("preferences", props.preferences);
</script>

<template>
    <AppLayout :title="__('Organization Settings')">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                {{ __("Organization Settings") }}
            </h2>
        </template>

        <div>
            <UpdateApplicationInformationForm />
        </div>
    </AppLayout>
</template>
