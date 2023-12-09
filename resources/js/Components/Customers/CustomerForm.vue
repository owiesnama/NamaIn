<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { ref } from "vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";

    const props = defineProps({
        customer: {
            type: Object,
            default: () => {},
            required: false,
        },
    });

    const customer = useForm({
        name: props.customer?.name,
        address: props.customer?.address,
        phone_number: props.customer?.phone_number,
    });

    let show = ref(false);

    const formAttributes = () => {
        let action = props.customer ? "put" : "post";
        let url = props.customer
            ? route("customers.update", props.customer)
            : route("customers.index");
        return [action, url];
    };

    const save = () => {
        let [action, route] = formAttributes();
        customer[action](route, {
            preserveState: true,
            onSuccess: () => {
                customer.reset();
                show.value = false;
            },
        });
    };

    let cancel = () => {
        customer.reset();
        show.value = false;
    };
</script>

<template>
    <div>
        <a
            v-if="props.customer"
            href="#"
            class="inline-flex items-center text-gray-600 gap-x-1 hover:text-yellow-500"
            @click="show = true"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-4 h-4"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"
                />
            </svg>
            <span>{{__('Edit')}}</span>
        </a>
        <button
            v-else
            class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
            @click="show = true"
        >
            + {{__('Add New')}} {{__('Customer')}}
        </button>

        <div
            class="relative z-50"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <transition
                enter-from-class="opacity-0"
                enter-active-class="duration-300 ease-out transform"
                enter-to-class="opacity-100"
                leave-from-class="opacity-100"
                leave-active-class="duration-300 ease-in transform"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 transition-opacity bg-gray-500/20 backdrop-blur-sm"
                ></div>
            </transition>

            <transition
                enter-from-class="scale-95 translate-y-0 opacity-0"
                enter-active-class="duration-200 ease-out transform"
                enter-to-class="scale-100 translate-y-0 opacity-100"
                leave-from-class="scale-100 translate-y-0 opacity-100"
                leave-active-class="duration-200 ease-in transform"
                leave-to-class="scale-95 translate-y-0 opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 z-50 overflow-y-auto"
                >
                    <div
                        class="flex items-end justify-center min-h-full p-4 text-center sm:items-center sm:p-0"
                    >
                        <div
                            class="relative px-4 pt-5 pb-4 overflow-hidden text-left transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:w-full sm:max-w-sm sm:p-6"
                        >
                            <h1 class="font-semibold text-gray-800 rtl:text-right">
                                {{ props.customer ? __("Update") : __("Add New") }}
                                {{__('Customer')}}
                            </h1>

                            <form
                                class="mt-4"
                                @submit.prevent="save"
                            >
                                <div>
                                    <InputLabel
                                        for="name"
                                        :value="__('Name')"
                                    />
                                    <TextInput
                                        id="name"
                                        v-model="customer.name"
                                        type="text"
                                        class="block w-full mt-1"
                                        required
                                        autofocus
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="customer.errors.name"
                                    />
                                </div>

                                <div class="mt-4">
                                    <InputLabel
                                        for="address"
                                        :value="__('Address')"
                                    />
                                    <TextInput
                                        id="address"
                                        v-model="customer.address"
                                        type="text"
                                        class="block w-full mt-1"
                                        required
                                        autofocus
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="customer.errors.address"
                                    />
                                </div>

                                <div class="mt-4">
                                    <InputLabel
                                        for="phone"
                                        :value="__('Phone')"
                                    />
                                    <TextInput
                                        id="phone"
                                        v-model="customer.phone_number"
                                        type="text"
                                        class="block w-full mt-1"
                                        required
                                        autofocus
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="customer.errors.phone_number"
                                    />
                                </div>

                                <div class="flex items-center mt-6 gap-x-4">
                                    <button
                                        type="button"
                                        class="px-6 w-1/2 py-2.5 text-sm font-semibold tracking-wide focus:outline-none border rounded-lg"
                                        @click="cancel"
                                    >
                                        {{__('Cancel')}}
                                    </button>

                                    <PrimaryButton
                                        class="w-1/2 font-semibold"
                                        :class="{
                                            'opacity-25': customer.processing,
                                        }"
                                        :disabled="customer.processing"
                                    >
                                        {{ props.customer ? __("Update") : __("Add") }}
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
