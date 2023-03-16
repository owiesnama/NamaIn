<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";
    import { reactive } from "vue";
    let file = reactive({});
    let form = useForm(file);
    let submit = () => {
        form.post(route("products.import"), { forceFormData: true });
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
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <form @submit.prevent="submit">
                        <input
                            type="file"
                            @input="file = $event.target.files[0]"
                        />
                        <progress
                            v-if="form.progress"
                            :value="form.progress.percentage"
                            max="100"
                        >
                            {{ form.progress.percentage }}%
                        </progress>
                        <button type="submit">Import</button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
