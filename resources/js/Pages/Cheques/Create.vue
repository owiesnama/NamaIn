<script setup>
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { useForm } from "@inertiajs/vue3";
    import TextInput from "@/Components/TextInput.vue";
    import PrimaryButton from "@/Components/PrimaryButton.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import SelectBox from "@/Shared/SelectBox.vue";

    defineProps({
        payees: Object
    });
    let cheque = useForm({
        type: 1,
        payee: {},
        bank: "",
        amount: 0.0,
        due: null
    });
    const isCredit = (cheque) => {
        return cheque.type == 1;
    };
    const submit = () => {
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
        <form
            class="max-w-6xl mx-auto"
            action="#"
            @submit.prevent="submit"
        >
            <div
                :class="
                    isCredit(cheque) ? 'border-emerald-500' : 'border-red-500'
                "
                class="p-6 bg-white border-l-4 border-dashed rounded-lg rounded-l-none shadow-md rtl:border-l-0 rtl:border-r-4 rtl:rounded-l-lg rtl:rounded-r-none shadow-gray-200"
            >
                <div class="flex items-center justify-between">
                    <div class="mb-4">
                        <InputLabel :value="__('Bank')" />
                        <input
                            v-model="cheque.bank"
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            type="text"
                            :placeholder="__('Bank')"
                        />
                    </div>
                    <div>
                        <InputLabel :value="__('Due')" />
                        <input
                            v-model="cheque.due"
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            type="date"
                            :placeholder="__('Due')"
                        />
                    </div>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-gray-800">
                    <InputLabel :value="__('Payee')" />
                    <select
                        v-model="cheque.payee"
                        class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                        name="payee"
                    >
                        <option
                            v-for="payee in payees"
                            :key="payee.id"
                            :value="payee"
                            v-text="payee.name + '(' + payee.type_string + ')'"
                        ></option>
                    </select>
                </h2>
                <div class="mt-24 sm:flex sm:items-end sm:justify-between">
                    <div>
                        <InputLabel :value="__('Status')" />
                        <SelectBox
                            v-model="cheque.type"
                            class="w-full mt-1 text-sm rounded-lg sm:w-36"
                        >
                            <option value="0" v-text="__('Debit')"></option>
                            <option value="1" v-text="__('Credit')"></option>
                        </SelectBox>
                    </div>
                    <h3
                        :class="
                            isCredit(cheque)
                                ? 'text-emerald-500'
                                : 'text-red-500'
                        "
                        class="mt-4 font-bold sm:mt-0"
                    >
                        <input
                            class="px-3 border border-gray-200 rounded focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            v-model="cheque.amount"
                            type="number"
                            :placeholder="__('Amount')"
                        />
                    </h3>
                </div>
            </div>
            <div class="mt-8 text-right rtl:text-left">
                <button
                    class="w-full px-5 py-2.5 mt-3 text-sm tracking-wide text-white transition-colors font-bold duration-200 rounded-lg sm:mt-0 bg-emerald-500 shrink-0 sm:w-auto hover:bg-emerald-600 dark:hover:bg-emerald-500 dark:bg-emerald-600"
                    type="submit"
                >
                    {{ __("Register Cheque") }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
