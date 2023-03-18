<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";
    import FileUploadButton from "@/Shared/FileUploadButton.vue";
    let form = useForm({
        file: null,
    });
    let submit = (files) => {
        form.file = files[0];
        form.post(route("products.import"));
    };
</script>

<template>
    <AppLayout title="Products">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Products
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <form>
                    <FileUploadButton @input="submit"
                        >Import From CSV</FileUploadButton
                    >
                </form>
            </div>
        </div>
    </AppLayout>
</template>
