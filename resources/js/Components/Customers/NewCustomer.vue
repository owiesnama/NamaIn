<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { ref } from "vue";
    import InputError from '@/Components/InputError.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';

    const customer = useForm({
        name: "",
        address: "",
        phone_number: "",
    });

    let show = ref(false);

    const save = () => {
            customer.post("/customers", {
                preserveState: false,
                onSuccess: () => {
                    customer.reset();
                }
            });
        }
</script>

<template>
    <div>
        <button @click="show = true" class="w-full px-5 py-2 mt-3 text-sm tracking-wide text-white transition-colors duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600">
            + Add New Customer
        </button>
    
        <div class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <transition
                enter-from-class="opacity-0"
                enter-active-class="duration-300 ease-out transform"
                enter-to-class="opacity-100"
                leave-from-class="opacity-100"
                leave-active-class="duration-300 ease-in transform"
                leave-to-class="opacity-0"
                >
                    <div v-show="show" class="fixed inset-0 transition-opacity bg-gray-500/20 backdrop-blur-sm"></div>
            </transition>
    
            <transition
                enter-from-class="scale-95 translate-y-0 opacity-0"
                enter-active-class="duration-200 ease-out transform"
                enter-to-class="scale-100 translate-y-0 opacity-100"
                leave-from-class="scale-100 translate-y-0 opacity-100"
                leave-active-class="duration-200 ease-in transform"
                leave-to-class="scale-95 translate-y-0 opacity-0"
            >
                <div v-show="show" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-end justify-center min-h-full p-4 text-center sm:items-center sm:p-0">
                        <div class="relative px-4 pt-5 pb-4 overflow-hidden text-left transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                            <h1 class="font-semibold text-gray-800">Add New Customer</h1>
                            <p class="mt-1 text-sm text-gray-500 ">Lorem ipsum dolor sit, amet consectetur adipisicing elit.</p>

                            <form class="mt-4" @submit.prevent="save">
                                <div>
                                    <InputLabel for="name" value="Name" />
                                    <TextInput
                                        id="name"
                                        v-model="customer.name"
                                        type="text"
                                        class="block w-full mt-1"
                                        required
                                        autofocus
                                    />
                                    <InputError class="mt-2" :message="customer.errors.name" />
                                </div>

                                <div class="mt-4">
                                    <InputLabel for="address" value="Address" />
                                    <TextInput
                                        id="address"
                                        v-model="customer.address"
                                        type="text"
                                        class="block w-full mt-1"
                                        required
                                        autofocus
                                    />
                                    <InputError class="mt-2" :message="customer.errors.address" />
                                </div>

                                <div class="mt-4">
                                    <InputLabel for="phone" value="Phone" />
                                    <TextInput
                                        id="phone"
                                        v-model="customer.phone_number"
                                        type="text"
                                        class="block w-full mt-1"
                                        required
                                        autofocus
                                    />
                                    <InputError class="mt-2" :message="customer.errors.phone_number" />
                                </div>

                                <div class="flex items-center mt-6 gap-x-4">
                                    <button type="button" @click="show = false" class="px-6 w-1/2 py-2.5 text-sm font-semibold tracking-wide focus:outline-none border rounded-lg">
                                        Cancel
                                    </button>

                                    <PrimaryButton class="w-1/2 font-semibold" :class="{ 'opacity-25': customer.processing }" :disabled="customer.processing">
                                        Add
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
