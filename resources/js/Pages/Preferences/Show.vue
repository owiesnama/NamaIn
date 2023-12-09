<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import SectionBorder from "@/Components/SectionBorder.vue";
    import UpdateApplicationInformationForm from "@/Pages/Preferences/Partials/UpdateApplicationInformationForm.vue";
    import UpdateDateInformationForm from "@/Pages/Preferences/Partials/UpdateDateInformationForm.vue";
    import { provide } from "vue";
    const props = defineProps({
        preferences: {
            type: Object,
        },
    });
    provide("preferences", props.preferences);
</script>

<template>
    <AppLayout title="Preferences">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Preferences
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

            <!-- <div>
                <CategoryManagments />
            </div> -->
        </div>
    </AppLayout>
</template>
