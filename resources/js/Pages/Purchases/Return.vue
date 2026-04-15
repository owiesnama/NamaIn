<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import TextArea from "@/Components/TextArea.vue";
import CustomSelect from "@/Components/CustomSelect.vue";
import { ref, computed, watch } from "vue";
import { useForm, Link } from "@inertiajs/vue3";

const props = defineProps({
    invoice: Object,
    payment_methods: Object,
});

const returnItems = ref(props.invoice.transactions.map(transaction => ({
    transaction_id: transaction.id,
    product_id: transaction.product_id,
    product_name: transaction.product.name,
    quantity: transaction.quantity,
    max_quantity: transaction.quantity,
    unit_id: transaction.unit_id,
    unit_name: transaction.unit?.name || '',
    price: transaction.price,
    total: transaction.price * transaction.quantity
})));

const totalReturn = computed(() => {
    return returnItems.value.reduce((sum, item) => sum + (item.price * (item.quantity || 0)), 0);
});

const form = useForm({
    parent_invoice_id: props.invoice.id,
    inverse_reason: '',
    products: [],
    total: 0,
    refund_amount: 0,
    payment_method: 'cash',
});

const selectedPaymentMethod = ref({ id: form.payment_method, label: Object.keys(props.payment_methods).find(key => props.payment_methods[key] === form.payment_method) });

watch(totalReturn, (newTotal) => {
    form.refund_amount = newTotal;
}, { immediate: true });

const submit = () => {
    form.payment_method = selectedPaymentMethod.value?.id || 'cash';
    form.products = returnItems.value.map(item => ({
        transaction_id: item.transaction_id,
        product_id: item.product_id,
        quantity: item.quantity,
        unit_id: item.unit_id,
        price: item.price
    }));
    form.total = totalReturn.value;
    form.post(route("purchases.return.store", props.invoice.id));
};
</script>

<template>
    <AppLayout :title="__('Return Purchase')">
        <!-- Page Header -->
        <div class="flex items-center gap-4 mb-8">
            <Link
                :href="route('purchases.index')"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all duration-200"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __("Return Purchase Invoice") }} #{{ invoice.serial_number }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __("Return items to supplier") }}: <span class="font-medium text-gray-700 dark:text-gray-300">{{ invoice.invocable.name }}</span></p>
            </div>
        </div>

        <form @submit.prevent="submit" class="flex flex-col lg:flex-row gap-6 items-start">
            <!-- Main Panel -->
            <div class="flex-1 min-w-0 space-y-6">
                <!-- Products Table Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none overflow-hidden" v-auto-animate>
                    <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20">
                        <div class="w-7 h-7 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __("Returned Products") }}</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-start font-medium">{{ __("Product") }}</th>
                                    <th class="px-6 py-3 text-center font-medium">{{ __("Original Qty") }}</th>
                                    <th class="px-6 py-3 text-center font-medium">{{ __("Return Qty") }}</th>
                                    <th class="px-6 py-3 text-end font-medium">{{ __("Price") }}</th>
                                    <th class="px-6 py-3 text-end font-medium">{{ __("Total") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                <tr v-for="(item, index) in returnItems" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ item.product_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ item.unit_name }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-700 dark:text-gray-300">{{ item.max_quantity }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center">
                                            <TextInput
                                                type="number"
                                                v-model="item.quantity"
                                                min="0"
                                                :max="item.max_quantity"
                                                step="0.01"
                                                class="w-32 text-center"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-end text-gray-700 dark:text-gray-300 font-mono">{{ item.price.toFixed(2) }}</td>
                                    <td class="px-6 py-4 text-end font-bold text-gray-900 dark:text-white font-mono">{{ (item.price * item.quantity).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Return Reason Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-7 h-7 rounded-lg bg-orange-500/10 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __("Return Details") }}</span>
                    </div>
                    <div>
                        <InputLabel :value="__('Reason for Return')" required class="text-gray-700 dark:text-gray-300 mb-2" />
                        <TextArea
                            v-model="form.inverse_reason"
                            class="block w-full"
                            rows="4"
                            :placeholder="__('Explain why items are being returned to supplier...')"
                            required
                        />
                        <InputError :message="form.errors.inverse_reason" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-80 xl:w-96 flex flex-col gap-4 lg:sticky lg:top-4">
                <!-- Summary Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none overflow-hidden" v-auto-animate>
                    <div class="px-5 py-5 bg-blue-600 dark:bg-blue-700">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-200 mb-1">{{ __("Total Credit Note") }}</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-black text-white tabular-nums">{{ totalReturn.toFixed(2) }}</span>
                            <span class="text-sm font-medium text-blue-200">{{ invoice.currency }}</span>
                        </div>
                    </div>

                    <div class="px-5 py-4 space-y-2.5 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ __("Subtotal") }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ totalReturn.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <button
                            type="submit"
                            :disabled="form.processing || totalReturn <= 0"
                            class="w-full py-3 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <span v-if="!form.processing">{{ __("Complete Return") }}</span>
                            <span v-else class="inline-flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __("Processing...") }}
                            </span>
                        </button>

                        <Link
                            :href="route('purchases.index')"
                            class="w-full block text-center mt-3 py-2 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                        >
                            {{ __("Cancel") }}
                        </Link>
                    </div>
                </div>

                <!-- Refund Payment Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none p-5">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        {{ __("Refund Received") }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <InputLabel :value="__('Refund Amount')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <TextInput
                                type="number"
                                v-model="form.refund_amount"
                                min="0"
                                :max="totalReturn"
                                step="0.01"
                                class="block w-full"
                            />
                            <InputError :message="form.errors.refund_amount" class="mt-1" />
                        </div>

                        <div v-if="form.refund_amount > 0">
                            <InputLabel :value="__('Refund Method')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <CustomSelect
                                v-model="selectedPaymentMethod"
                                :options="Object.entries(payment_methods).map(([label, value]) => ({ id: value, label }))"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('Select Method')"
                                label="label"
                                track-by="id"
                                class="w-full"
                                :select-label="''"
                                :deselect-label="''"
                                :selected-label="__('Selected')"
                            >
                                <template #singleLabel="{ option }">
                                    {{ __(option.label) }}
                                </template>
                                <template #option="{ option }">
                                    {{ __(option.label) }}
                                </template>
                            </CustomSelect>
                            <InputError :message="form.errors.payment_method" class="mt-1" />
                        </div>
                    </div>
                </div>

                <!-- Previous Invoice Info -->
                <div class="bg-gray-50 dark:bg-gray-900/20 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-5">
                    <h4 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">{{ __("Original Invoice Info") }}</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">{{ __("Serial Number") }}</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">#{{ invoice.serial_number }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">{{ __("Date") }}</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ new Date(invoice.created_at).toLocaleDateString() }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">{{ __("Original Total") }}</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ Number(invoice.total).toFixed(2) }} {{ invoice.currency }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
