<script setup>
    import { useForm } from "@inertiajs/inertia-vue3";

    const customer = useForm({
        name: "",
        phone: "",
    });

    const emit = defineEmits(["customer:saved"]);
    const save = () => customer.post("/customers");
</script>
<template>
    <div
        class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-sm rounded-lg p-4"
    >
        <form
            @submit.prevent="save"
            class="w-full flex items-center"
        >
            <div class="mb-6 w-1/4 mr-8">
                <label
                    for="name"
                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
                    >Customer Name</label
                >
                <input
                    v-model="customer.name"
                    type="text"
                    id="name"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="Abubakr Elashik"
                    required
                />
            </div>
            <div class="mb-6 w-1/4">
                <label
                    for="phone"
                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
                    >Customer phone</label
                >
                <input
                    v-model="customer.phone"
                    type="text"
                    id="phone"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="Abubakr Elashik"
                    required
                />
            </div>

            <div class="mt-2 ml-auto">
                <button
                    class="bg-indigo-500 text-white active:bg-indigo-600 text-xs font-bold uppercase px-4 py-3 rounded outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                    type="submit"
                >
                    Save
                </button>
            </div>
        </form>
    </div>
</template>
