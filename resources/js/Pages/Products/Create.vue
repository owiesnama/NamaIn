<script setup>
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import SecondaryButton from "@/Components/SecondaryButton.vue";
    import TextInput from "@/Components/TextInput.vue";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";

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
</script>
<template>
    <AppLayout title="Create a product">
        <form @submit.prevent="submit">
            <TextInput
                v-model="product.name"
                placeholder="Product name"
            ></TextInput>
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
    </AppLayout>
</template>
