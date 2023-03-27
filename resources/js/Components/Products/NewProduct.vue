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
        expire_date: "",
        units: [{ name: "", conversionFactor: "" }],
    });

    const addUnit = () => {
        product.units.push({ name: "", conversionFactor: "" });
    };

    const save = () => {
        product.post(route("products.store"), {
            preserveState: true,
            onSuccess: () => {
                product.reset();

                show.value = false;
            },
        });
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
                            class="relative px-4 pt-5 pb-4 overflow-hidden text-left transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:w-full sm:max-w-xl sm:p-6"
                        >
                            <h1 class="font-semibold text-gray-800">
                                Add New Product
                            </h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Lorem ipsum dolor sit, amet consectetur
                                adipisicing elit.
                            </p>

                            <form class="mt-4" @submit.prevent="save">
                                <div class="md:flex md:gap-x-6">
                                    <div class="md:w-1/2">
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
        
                                        <div class="mt-4">
                                            <InputLabel
                                                for="cost"
                                                value="cost"
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
                                                value="Expire Date"
                                            />
                                            <TextInput
                                                id="expire_date"
                                                v-model="product.expire_date"
                                                type="date"
                                                class="block w-full mt-1"
                                                required
                                            />
                                            <InputError
                                                class="mt-2"
                                                :message="product.errors.expire_date"
                                            />
                                        </div>
        
                                        <div class="mt-4">
                                            
                                        </div>
                                    </div>
    
                                    <div class="h-56 overflow-y-auto md:w-1/2 md:px-2 ">
                                        <div
                                        v-for="(unit, index) in product.units"
                                        :key="`unit-` + index"
                                        class="mt-1 mb-4 space-y-4"
                                        >
                                            <div>
                                                <InputLabel
                                                    for="unit name"
                                                    value="Unit Name"
                                                />
                                                <TextInput
                                                    v-model="unit.name"
                                                    class="w-full focus:outline-none"
                                                    placeholder="Unit eg: box"
                                                />
                                            </div>
                                            
                                            <div>
                                                <InputLabel
                                                    for="conversionFactor"
                                                    value="Unit Conversion Factor"
                                                />
                                                <TextInput
                                                    v-model="unit.conversionFactor"
                                                    class="w-full mt-1 focus:outline-none"
                                                    type="number"
                                                    min="1"
                                                    placeholder="Unit conversion factor"
                                                />
                                            </div>
    
                                            <button
                                                type="button"
                                                v-if="index == product.units.length - 1"
                                                @click="addUnit"
                                                class="px-4 py-2.5 bg-gray-100 rounded-lg w-full text-sm font-semibold"
                                            >
                                                Add Unit
                                            </button
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center mt-6 gap-x-4">
                                    <button
                                        type="button"
                                        class="px-6 w-1/2 py-2.5 text-sm font-semibold tracking-wide focus:outline-none border rounded-lg"
                                        @click="cancel"
                                    >
                                        Cancel
                                    </button>

                                    <PrimaryButton
                                        class="w-1/2 font-semibold"
                                        :class="{
                                            'opacity-25': product.processing,
                                        }"
                                        :disabled="product.processing"
                                    >
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
