<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";
    import TextInput from "@/Components/TextInput.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    defineProps({
        payees: Object,
    });
    let cheque = useForm({
        type: null,
        payee: {},
        amount: null,
        due: null,
    });
    let submit = () => {
        cheque.post(route("cheques.store"));
    };
</script>

<template>
    <AppLayout title="Products">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Products
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white p-2 rounded border">
                    <form @submit.prevent="submit">
                        <div class="flex space-x-2 mb-2">
                            <div>
                                <label
                                    for="type"
                                    class="font-semibold mr-2"
                                    >Cheque Type</label
                                >
                                <select
                                    id="type"
                                    v-model="cheque.type"
                                    class="w-32 border border-gray-200 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded p-1"
                                    name="type"
                                >
                                    <option value="0">Debit</option>
                                    <option value="1">Credit</option>
                                </select>
                            </div>
                            <div class="">
                                <label
                                    class="font-semibold mr-2"
                                    for="payee"
                                    >Payee</label
                                >
                                <select
                                    id="payee"
                                    v-model="cheque.payee"
                                    name="payee"
                                >
                                    <option
                                        v-for="payee in payees"
                                        :key="payee.id"
                                        :value="payee"
                                        v-text="
                                            payee.name +
                                            '(' +
                                            payee.type_string +
                                            ')'
                                        "
                                    ></option>
                                </select>
                            </div>
                            <div class="">
                                <label
                                    class="font-semibold mr-2"
                                    for="amount"
                                    >Amount</label
                                >
                                <TextInput
                                    id="amount"
                                    v-model="cheque.amount"
                                    type="number"
                                    min="0"
                                    placeholder="Amount in SDG"
                                ></TextInput>
                            </div>
                        </div>
                        <div>
                            <label
                                class="font-semibold mr-2"
                                for="due"
                                >Due</label
                            >
                            <TextInput
                                id="due"
                                v-model="cheque.due"
                                type="date"
                                min="0"
                                placeholder="Due date"
                            ></TextInput>
                        </div>
                        <PrimaryButton class="">Register Cheque</PrimaryButton>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
