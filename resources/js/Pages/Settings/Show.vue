<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import UpdateApplicationInformationForm from '@/Pages/Settings/Partials/UpdateApplicationInformationForm.vue';
import UpdateDateInformationForm from '@/Pages/Settings/Partials/UpdateDateInformationForm.vue';
import CategoryManagments from '@/Pages/Settings/Partials/CategoryManagments.vue';
</script>

<template>
    <AppLayout title="Settings">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Settings
            </h2>
        </template>

        <div>
            <div>
                <UpdateApplicationInformationForm />

                <SectionBorder />
            </div>

            <div>
                <UpdateDateInformationForm />

                <SectionBorder />
            </div>

            <div>
                <CategoryManagments />
            </div>
        </div>
    </AppLayout>
</template>
