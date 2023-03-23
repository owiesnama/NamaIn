<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import Panel from "@/Shared/Panel.vue";
    import PurchaseProduct from "@/Models/PurchaseProduct";
    import { reactive, computed } from "vue";
    import { useForm } from "@inertiajs/vue3";
    let props = defineProps({
        storages: Object,
        products: Object,
    });
    let purchases = reactive([new PurchaseProduct()]);
    let productUnits = (id) => {
        let product = props.products.filter(
            (product) => product.id == id
        )[0];
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
        <div class="container mx-auto">
            <form @submit.prevent="submit">
                <Panel class="mt-4">
                    <div class="">
                        <div class="mb-4">
                            <label
                                for="totalCost"
                                class="font-bold mr-2"
                                >Total Cost:</label
                            >
                            <input
                                :value="totalCost"
                                readonly
                                name="totalCost"
                                class="rounded border-gray-100 outline-blue-300 flex-1"
                                placeholder="Unit Price"
                                type="number"
                            />
                        </div>
                        <div
                            v-for="(purchase, index) in purchases"
                            :key="index"
                            class="flex items-start space-x-2 mb-4"
                        >
                            <select
                                v-model="purchase.product"
                                class="rounded border-gray-100 outline-blue-300 flex-1"
                                name="product[]"
                            >
                                <option
                                    value=""
                                    selected
                                >
                                    Select Product
                                </option>
                                <option
                                    v-for="product in products"
                                    :key="'product-' + product.id"
                                    :value="product.id"
                                    v-text="product.name"
                                ></option>
                            </select>
                            <select
                                v-model="purchase.unit"
                                class="rounded border-gray-100 outline-blue-300 flex-1"
                                name="units[]"
                            >
                                <option
                                    value=""
                                    selected
                                >
                                    Unit
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
                            <input
                                v-model="purchase.quantity"
                                placeholder="Quantity"
                                name="quantity"
                                type="number"
                            />
                            <input
                                v-model="purchase.price"
                                class="rounded border-gray-100 outline-blue-300 flex-1"
                                name="price"
                                placeholder="Unit Price"
                                type="number"
                            />
                            <textarea
                                v-model="purchase.description"
                                class="rounded border-gray-100 outline-blue-300 flex-1"
                                name="description"
                                placeholder="Description"
                            ></textarea>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-bold uppercase px-4 py-3 rounded outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150 text-white bg-indigo-500 active:bg-indigo-600"
                            @click="newRow"
                        >
                            New Row
                        </button>
                    </div></Panel
                >

                <div class="text-right">
                    <button
                        type="submit"
                        class="text-xs font-bold uppercase px-4 py-3 rounded outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150 text-white bg-indigo-500 active:bg-indigo-600"
                    >
                        Sale
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
