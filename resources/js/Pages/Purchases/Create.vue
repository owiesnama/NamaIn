<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import TextInput from "@/Components/TextInput.vue";
    import PurchaseProduct from "@/Models/PurchaseProduct";
    import { reactive, computed } from "vue";
    import { router, useForm } from "@inertiajs/vue3";
    import { debounce } from "lodash";

    const props = defineProps({
        storages: Object,
        products: Object,
        suppliers: Array,
        payment_methods: Object
    });

    let purchases = reactive([new PurchaseProduct()]);

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

    let productUnits = (id) => {
        let product = props.products.filter((product) => product.id == id)[0];
        if (!product) return;
        return product.units;
    };

    let form = reactive({
        total: totalCost,
        products: purchases,
        invocable: null,
        payment_method: 'credit',
        discount: 0,
        initial_payment_amount: 0,
        payment_reference: '',
        payment_notes: ''
    });
    const searchSupplier = debounce(function(search) {
        router.get(route("purchases.create"), { supplier: search }, {
            preserveScroll: true,
            preserveState: true
        });
    }, 300);


    const submit = () => {
        useForm(form).post(route("purchases.store"));
    };

</script>
<template>
    <AppLayout title="New Purchase">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white">
            {{ __("New Purchase") }}
        </h2>

        <form
            class="mt-6 bg-white border-2 border-dashed rounded-lg p-4"
            @submit.prevent="submit"
        >
            <div class="flex justify-between">
                <div class="w-1/3" v-auto-animate>
                    <InputLabel
                        for="supplier"
                        :value="__('Supplier')"
                    />
                    <v-select
                        v-model="form.invocable"
                        :options="suppliers"
                        label="name"
                        track-by="id"
                        @search-change="searchSupplier"
                    />
                </div>
                <div class="ltr:text-right rtl:text-left">
                    <h2
                        class="text-2xl font-semibold text-emerald-500"
                    >
                        {{ totalCost }} <span class="text-sm font-medium uppercase">{{ (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency'))) ? preferences('currency') : 'USD' }}</span>
                    </h2>

                    <label
                        for="totalCost"
                        class="text-sm font-medium text-gray-600"
                    >
                        {{ __("Total Cost") }}
                    </label>
                </div>
            </div>

            <div class="mt-6 divide-y divide-gray-100" v-auto-animate>
                <div
                    v-for="(purchase, index) in purchases"
                    :key="index"
                    class="mt-6"
                >
                    <div
                        class="grid flex-1 grid-cols-1 gap-6 mt-6 sm:grid-cols-2 lg:border-none lg:border-0 lg:p-0 sm:border sm:border-dashed sm:p-4 sm:rounded-lg sm: 3 lg:grid-cols-5"
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

            <!-- Payment Section -->
            <div class="mt-8 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                    {{ __("Payment Details") }}
                </h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel for="payment_method" :value="__('Payment Method')" />
                        <select
                            v-model="form.payment_method"
                            id="payment_method"
                            class="w-full px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        >
                            <option
                                v-for="(value, label) in payment_methods"
                                :key="value"
                                :value="value"
                            >
                                {{ __(label) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="discount" :value="__('Discount')" />
                        <TextInput
                            id="discount"
                            v-model="form.discount"
                            type="number"
                            step="0.01"
                            class="block w-full mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel for="initial_payment" :value="__('Initial Payment')" />
                        <TextInput
                            id="initial_payment"
                            v-model="form.initial_payment_amount"
                            type="number"
                            step="0.01"
                            class="block w-full mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel for="payment_reference" :value="__('Reference')" />
                        <TextInput
                            id="payment_reference"
                            v-model="form.payment_reference"
                            type="text"
                            class="block w-full mt-1"
                            placeholder="Cheque number, etc."
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="payment_notes" :value="__('Payment Notes')" />
                        <textarea
                            id="payment_notes"
                            v-model="form.payment_notes"
                            rows="2"
                            class="w-full px-3 py-2 mt-1 border border-gray-200 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            placeholder="Additional payment notes..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-5 text-right md:mt-8">
                <button
                    class="w-full px-5 py-2.5 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    type="submit"
                >
                    {{ __("Purchase") }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
