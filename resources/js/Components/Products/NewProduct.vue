<script setup>
    import { useForm } from "@inertiajs/vue3";
    import { ref } from "vue";
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";

    let show = ref(false);

    let product = useForm({
        name: "",
        cost: 50,
        units: [{ name: "", conversionFactor: "" }],
    });

    const addUnit = () => {
        product.units.push({ name: "", conversionFactor: "" });
    };

    const submit = () => {
        product.post(route("products.store"));
    };

    let cancel = () => {
        product.reset();
        show.value = false;
    };
</script>

<template>
    <div>
        <button
            class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors duration-200 font-bold rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
            @click="show = true"
        >
            + Add New Product
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
                            <h1 class="font-semibold text-gray-800">
                                Add New Product
                            </h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Lorem ipsum dolor sit, amet consectetur
                                adipisicing elit.
                            </p>

                            <form @submit.prevent="submit">
                                <div>
                                    <InputLabel
                                        for="name"
                                        value="Name"
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

                                <TextInput
                                    v-model="product.cost"
                                    placeholder="Product cost"
                                    type="number"
                                ></TextInput>
                                Units:
                                <div
                                    v-for="(unit, index) in product.units"
                                    :key="`unit-` + index"
                                >
                                    <TextInput
                                        v-model="unit.name"
                                        placeholder="Unit eg: box"
                                    />
                                    <TextInput
                                        v-model="unit.conversionFactor"
                                        type="number"
                                        min="1"
                                        placeholder="Unit conversion factor"
                                    />
                                    <SecondaryButton
                                        v-if="index == product.units.length - 1"
                                        @click="addUnit"
                                        >Add Unit</SecondaryButton
                                    >
                                </div>
                                <PrimaryButton>Save product</PrimaryButton>
                            </form>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
