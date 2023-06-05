<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { ref } from "vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";

    const props = defineProps({
        product: {
            type: Object,
            default: () => {},
            required: false,
        },
    });

    const show = ref(false);
    const product = useForm({
        name: props.product?.name,
        cost: props.product?.cost,
        expire_date: props.product?.expire_date,
        units: props.product?.units?.length
            ? props.product?.units
            : [{ unit: "", conversion_factor: null }],
    });
    const addUnit = () => {
        product.units.push({ name: "", conversion_factor: "" });
    };

    const formAttributes = () => {
        let action = props.product ? "put" : "post";
        let url = props.product
            ? route("products.update", props.product)
            : route("products.index");
        return [action, url];
    };

    const save = () => {
        let [action, route] = formAttributes();
        product[action](route, {
            preserveState: true,
            onSuccess: () => {
                product.reset();
                show.value = false;
            },
        });
    };

    const cancel = () => {
        product.reset();
        show.value = false;
    };
</script>

<template>
    <div>
        <a
            v-if="props.product"
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
            <span>{{ __("Edit") }}</span>
        </a>
        <button
            v-else
            class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
            @click="show = true"
        >
            + {{ __("Add New Product") }}
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
                            class="relative px-4 pt-5 pb-4 overflow-hidden text-left transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:w-full sm:max-w-xl sm:p-6"
                        >
                            <h1 class="font-semibold text-gray-800 rtl:text-right">
                                {{ __("Add New Product") }}
                            </h1>

                            <form
                                class="mt-4"
                                @submit.prevent="save"
                            >
                                <div class="md:flex md:gap-x-6">
                                    <div class="md:w-1/2">
                                        <div>
                                            <InputLabel
                                                for="name"
                                                :value="__('Name')"
                                            />
                                            <TextInput
                                                id="name"
                                                v-model="product.name"
                                                type="text"
                                                class="block w-full mt-1"
                                                required
                                                autofocus
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="product.errors.name"
                                            />
                                        </div>

                                        <div class="mt-4">
                                            <InputLabel
                                                for="cost"
                                                :value="__('Cost')"
                                            />
                                            <TextInput
                                                id="cost"
                                                v-model="product.cost"
                                                type="text"
                                                class="block w-full mt-1"
                                                required
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="product.errors.cost"
                                            />
                                        </div>

                                        <div class="mt-4">
                                            <InputLabel
                                                for="expire_date"
                                                :value="__('Expire Date')"
                                            />
                                            <TextInput
                                                id="expire_date"
                                                v-model="product.expire_date"
                                                type="date"
                                                class="block w-full mt-1 rtl:text-right"
                                                required
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="
                                                    product.errors.expire_date
                                                "
                                            />
                                        </div>

                                        <div class="mt-4 rtl:text-right">
                                            <label
                                                class="inline-flex rtl:flex-row-reverse items-center text-sm text-gray-600 gap-x-2"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="alert"
                                                    class="border-gray-300 p-1.5 rounded-md text-emerald-500 focus:ring-transparent"
                                                />
                                                <span>{{
                                                    __(
                                                        "Alert when stock is low"
                                                    )
                                                }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div
                                        class="h-56 overflow-y-auto md:w-1/2 md:px-2"
                                    >
                                        <div
                                            v-for="(
                                                unit, index
                                            ) in product.units"
                                            :key="`unit-` + index"
                                            class="mt-1 mb-4 space-y-4"
                                        >
                                            <div>
                                                <InputLabel
                                                    for="unit name"
                                                    :value="__('Unit Name')"
                                                />
                                                <TextInput
                                                    v-model="unit.name"
                                                    class="w-full focus:outline-none"
                                                    :placeholder="
                                                        __('Unit eg: box')
                                                    "
                                                />
                                            </div>

                                            <div>
                                                <InputLabel
                                                    for="conversion_factor"
                                                    :value="
                                                        __(
                                                            'Unit Conversion Factor'
                                                        )
                                                    "
                                                />
                                                <TextInput
                                                    v-model="
                                                        unit.conversion_factor
                                                    "
                                                    class="w-full mt-1 focus:outline-none"
                                                    type="number"
                                                    min="1"
                                                    :placeholder="
                                                        __(
                                                            'Unit Conversion Factor'
                                                        )
                                                    "
                                                />
                                            </div>

                                            <button
                                                v-if="
                                                    index ==
                                                    product.units.length - 1
                                                "
                                                type="button"
                                                class="px-4 py-2.5 bg-gray-100 rounded-lg w-full text-sm font-semibold"
                                                @click="addUnit"
                                            >
                                                {{ __("Add Unit") }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center mt-6 gap-x-4"
                                >
                                    <button
                                        type="button"
                                        class="px-6 w-1/2 py-2.5 text-sm font-semibold tracking-wide focus:outline-none border rounded-lg"
                                        @click="cancel"
                                    >
                                        {{ __("Cancel") }}
                                    </button>

                                    <PrimaryButton
                                        class="w-1/2 font-semibold"
                                        :class="{
                                            'opacity-25': product.processing,
                                        }"
                                        :disabled="product.processing"
                                    >
                                        {{ __("Add") }}
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
