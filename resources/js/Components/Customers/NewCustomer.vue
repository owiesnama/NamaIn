<script setup>
    import Panel from "@/Shared/Panel.vue";
    import { useForm } from "@inertiajs/vue3";
    import ValidationError from "../../Shared/ValidationError.vue";
    const emit = defineEmits(["customer:saved"]);

    const customer = useForm({
        name: "",
        phone: "",
    });

    const save = () =>
        customer.post("/customers", {
            preserveScroll: true,
            onSuccess: () => customer.reset(),
        });
</script>
<template>
    <Transition
        appear
        enter-active-class="transform ease-out duration-300 transition"
        enter-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <Panel>
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
                        :class="{
                            'border-red-400': customer.errors.phone,
                            'border-gray-300': !customer.errors.phone,
                        }"
                        placeholder="Abubakr Elashik"
                        required
                    />
                    <ValidationError :error="customer.errors.phone" />
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
        </Panel>    
    </Transition>
</template>
