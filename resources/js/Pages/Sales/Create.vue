<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import TextInput from "@/Components/TextInput.vue";
    import PurchaseProduct from "@/Models/PurchaseProduct";
    import { reactive, computed } from "vue";
    import { useForm } from "@inertiajs/vue3";

    let props = defineProps({
        storages: Object,
        products: Object,
    });

    let purchases = reactive([new PurchaseProduct()]);

    let productUnits = (id) => {
        let product = props.products.filter((product) => product.id == id)[0];
        if (!product) return;
        return product.units;
    };

    const newRow = () => {
        purchases.push(new PurchaseProduct());
    };

    const totalCost = computed(() => {
        let cost = 0;
        purchases.forEach((product) => {
            cost = product.total() + cost;
        });
        return cost;
    });

    const submit = () => {
        let form = reactive({
            total: totalCost,
            products: purchases,
        });
        useForm(form).post(route("sales.store"));
    };
</script>
<template>
    <AppLayout title="New Sales">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            {{ __("New Sales") }}
        </h2>

        <form
            class="mt-6"
            @submit.prevent="submit"
        >
            <div class="flex items-center gap-x-2">
                <h2
                    class="text-2xl font-semibold text-emerald-500"
                    v-text="totalCost + ' SDG'"
                ></h2>

                <label
                    for="totalCost"
                    class="text-sm font-medium text-gray-600"
                >
                    {{ __("Total Cost") }}
                </label>
            </div>

            <div class="mt-6 divide-y divide-gray-100">
                <div
                    v-for="(purchase, index) in purchases"
                    :key="index"
                    class="mt-6"
                >
                    <div
                        class="grid flex-1 grid-cols-1 gap-6 mt-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5"
                    >
                        <div>
                            <InputLabel
                                for="product"
                                :value="__('Product')"
                            />
                            <select
                                v-model="purchase.product"
                                class="w-full px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                name="product[]"
                            >
                                <option
                                    value=""
                                    selected
                                >
                                    {{ __("Select Product") }}
                                </option>

                                <option
                                    v-for="product in products"
                                    :key="'product-' + product.id"
                                    :value="product.id"
                                    v-text="product.name"
                                ></option>
                            </select>
                        </div>

                        <div>
                            <InputLabel
                                for="units"
                                :value="__('Units')"
                            />
                            <select
                                v-model="purchase.unit"
                                class="w-full px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                                name="units[]"
                            >
                                <option
                                    value=""
                                    selected
                                >
                                    {{ __("Unit") }}
                                </option>
                                <option
                                    v-for="unit in productUnits(
                                        purchase.product
                                    )"
                                    :key="'unit-' + unit.id"
                                    :value="unit.id"
                                    v-text="unit.name"
                                ></option>
                            </select>
                        </div>

                        <div>
                            <InputLabel
                                for="quantity"
                                :value="__('Quantity')"
                            />
                            <TextInput
                                id="quantity"
                                v-model="purchase.quantity"
                                type="number"
                                class="block w-full mt-1"
                                required
                                autofocus
                            />
                        </div>

                        <div>
                            <InputLabel
                                for="price"
                                :value="__('Price')"
                            />
                            <TextInput
                                id="price"
                                v-model="purchase.price"
                                type="number"
                                class="block w-full mt-1"
                                required
                                autofocus
                            />
                        </div>

                        <div>
                            <InputLabel
                                for="description"
                                :value="__('Description')"
                            />

                            <textarea
                                id="description"
                                v-model="purchase.description"
                                name="description"
                                class="w-full h-20 px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <button
                    class="w-full px-5 py-2.5 mt-4 text-sm tracking-wide text-gray-700 transition-colors font-bold duration-200 rounded-lg bg-gray-200 shrink-0 sm:w-auto hover:bg-gray-300 dark:hover:bg-gray-500 dark:bg-gray-600"
                    @click="newRow"
                >
                    + {{ __("Add New Row") }}
                </button>
            </div>

            <div class="mt-5 text-right md:mt-8">
                <button
                    class="w-full px-5 py-2.5 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    type="submit"
                >
                    {{ __("Sale") }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
