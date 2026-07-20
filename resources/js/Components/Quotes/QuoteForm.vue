<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import QuickAddPartyModal from "@/Components/QuickAddPartyModal.vue";
import { ref, computed } from "vue";
import { useForm, Link } from "@inertiajs/vue3";
import { useAsyncOptions } from "@/Composables/useAsyncOptions";

const props = defineProps({
    quote: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => !!props.quote);

const {
    options: customerOptions,
    loading: customersLoading,
    loadMore: loadMoreCustomers,
    onSearch: searchCustomers,
} = useAsyncOptions(route("api.customers.index"));

const {
    options: productOptions,
    loading: productsLoading,
    loadMore: loadMoreProducts,
    onSearch: searchProducts,
} = useAsyncOptions(route("api.products.index", { line_sale: 1 }));

const selectedCustomer = ref(props.quote?.customer ?? null);
const showQuickAddModal = ref(false);

const lineItems = ref(
    props.quote?.items?.length
        ? props.quote.items.map((item) => ({
              product_id: item.product_id,
              product: item.product ?? null,
              unit_id: item.unit_id,
              quantity: item.quantity,
              unit_price: parseFloat(item.unit_price),
          }))
        : [{ product_id: null, product: null, unit_id: null, quantity: 1, unit_price: 0 }]
);

const form = useForm({
    customer_id: props.quote?.customer_id ?? null,
    expires_at: props.quote?.expires_at ?? null,
    notes: props.quote?.notes ?? null,
    discount: props.quote?.discount ?? 0,
    items: lineItems.value,
});

const subtotal = computed(() =>
    lineItems.value.reduce((sum, item) => sum + item.quantity * item.unit_price, 0)
);

const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)));

const productUnits = (productId) => {
    const p = productOptions.value.find((p) => p.id == productId);
    return p?.units ?? [];
};

const addRow = () => {
    lineItems.value.push({ product_id: null, product: null, unit_id: null, quantity: 1, unit_price: 0 });
};

// CustomSelect emits the trackBy value (id), not the full object.
const onProductSelect = (index, productId) => {
    if (!productId) {
        lineItems.value[index].product_id = null;
        lineItems.value[index].product = null;
        lineItems.value[index].unit_id = null;
        lineItems.value[index].unit_price = 0;
    } else {
        const product = productOptions.value.find((p) => p.id == productId) ?? null;
        lineItems.value[index].product_id = productId;
        lineItems.value[index].product = product;
        lineItems.value[index].unit_id = product?.units?.[0]?.id ?? null;
        lineItems.value[index].unit_price = parseFloat(product?.selling_price ?? 0);
    }
};

// CustomSelect emits the trackBy value (id), not the full object.
const onCustomerSelect = (customerId) => {
    const customer = customerOptions.value.find((c) => c.id == customerId) ?? null;
    selectedCustomer.value = customer;
    form.customer_id = customerId ?? null;
};

const onCustomerCreated = (customer) => {
    selectedCustomer.value = customer;
    form.customer_id = customer.id;
    customerOptions.value.unshift(customer);
};

const submit = () => {
    form.items = lineItems.value.map((item) => ({
        product_id: item.product_id,
        unit_id: item.unit_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
    }));

    if (isEdit.value) {
        form.put(route("quotes.update", props.quote.id));
    } else {
        form.post(route("quotes.store"));
    }
};
</script>

<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center gap-3 mb-6">
            <Link
                :href="route('quotes.index')"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </Link>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ isEdit ? __("Edit Quote") : __("New Quote") }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ isEdit ? quote.number : __("Create a new price quotation for a customer") }}
                </p>
            </div>
        </div>

        <form class="flex flex-col lg:flex-row gap-6 items-start" @submit.prevent="submit">
            <!-- Main Panel -->
            <div class="flex-1 min-w-0 space-y-4">
                <!-- Customer -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-none">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __("Customer") }}</span>
                    </div>
                    <div class="max-w-sm">
                        <CustomSelect
                            :model-value="selectedCustomer"
                            :options="customerOptions"
                            label="name"
                            track-by="id"
                            :remote="true"
                            :loading="customersLoading"
                            @update:model-value="onCustomerSelect"
                            @search-change="searchCustomers"
                            @scroll-end="loadMoreCustomers"
                            :placeholder="__('Search customer...')"
                        >
                            <template #noResult>
                                <div class="p-2 space-y-2 text-center">
                                    <p class="text-sm text-gray-500">{{ __("No elements found. Consider changing the search query.") }}</p>
                                    <button
                                        type="button"
                                        @click="showQuickAddModal = true"
                                        class="text-emerald-600 hover:text-emerald-700 font-semibold text-sm"
                                    >
                                        + {{ __("Add New Customer") }}
                                    </button>
                                </div>
                            </template>
                        </CustomSelect>
                        <InputError :message="form.errors.customer_id" class="mt-1" />
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="w-7 h-7 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __("Line Items") }}</span>
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-emerald-700 bg-emerald-100 dark:text-emerald-300 dark:bg-emerald-900/40 rounded-full">
                            {{ lineItems.length }}
                        </span>
                    </div>

                    <div class="hidden md:grid md:grid-cols-12 gap-3 px-5 py-2.5 bg-gray-50 dark:bg-gray-900/50 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <div class="col-span-4">{{ __("Product") }}</div>
                        <div class="col-span-2">{{ __("Unit") }}</div>
                        <div class="col-span-2">{{ __("Qty") }}</div>
                        <div class="col-span-2">{{ __("Unit price") }}</div>
                        <div class="col-span-2 text-end pe-8">{{ __("Total") }}</div>
                    </div>

                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50" v-auto-animate>
                        <div v-for="(item, index) in lineItems" :key="index" class="px-5 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                                <div class="md:col-span-4">
                                    <label class="md:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Product") }}</label>
                                    <!-- Bookable services are excluded from this picker (line_sale filter);
                                         they are booked via the calendar. Physical goods and walk-in
                                         (requires_booking=false) services remain sellable as line items. -->
                                    <CustomSelect
                                        :model-value="item.product"
                                        :options="productOptions"
                                        :multiple="false"
                                        :close-on-select="true"
                                        :placeholder="__('Select Product')"
                                        label="name"
                                        track-by="id"
                                        :remote="true"
                                        :loading="productsLoading"
                                        @search-change="searchProducts"
                                        @scroll-end="loadMoreProducts"
                                        class="w-full"
                                        @update:model-value="(p) => onProductSelect(index, p)"
                                    />
                                    <InputError :message="form.errors[`items.${index}.product_id`]" class="mt-1" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="md:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Unit") }}</label>
                                    <CustomSelect
                                        v-model="item.unit_id"
                                        :options="productUnits(item.product_id)"
                                        :multiple="false"
                                        :close-on-select="true"
                                        :placeholder="__('Unit')"
                                        label="name"
                                        track-by="id"
                                        class="w-full"
                                        :disabled="!item.product_id"
                                    />
                                    <InputError :message="form.errors[`items.${index}.unit_id`]" class="mt-1" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="md:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Qty") }}</label>
                                    <TextInput v-model="item.quantity" type="number" inputmode="decimal" min="0.01" step="0.01" class="block w-full" required />
                                    <InputError :message="form.errors[`items.${index}.quantity`]" class="mt-1" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="md:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Unit price") }}</label>
                                    <TextInput v-model="item.unit_price" type="number" inputmode="decimal" min="0" step="0.01" class="block w-full" required />
                                    <InputError :message="form.errors[`items.${index}.unit_price`]" class="mt-1" />
                                </div>

                                <div class="md:col-span-2 flex items-center justify-between md:justify-end gap-2 md:pt-1.5">
                                    <div>
                                        <label class="md:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Total") }}</label>
                                        <span class="font-bold text-gray-900 dark:text-white tabular-nums text-sm">{{ (item.quantity * item.unit_price).toFixed(2) }}</span>
                                    </div>
                                    <button
                                        v-if="lineItems.length > 1"
                                        type="button"
                                        @click="lineItems.splice(index, 1)"
                                        class="p-1.5 text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors flex-shrink-0"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-4 bg-gray-50/30 dark:bg-gray-900/20 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="addRow"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
                        >
                            <span class="w-6 h-6 rounded-full bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            {{ __("Add Another Item") }}
                        </button>
                    </div>

                    <InputError :message="form.errors.items" class="px-5 pb-3" />
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-80 xl:w-96 flex flex-col gap-4 lg:sticky lg:top-4">
                <!-- Summary Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none overflow-hidden">
                    <div class="px-5 py-5 bg-emerald-600 dark:bg-emerald-700">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-200 mb-1">{{ __("Quote Total") }}</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-black text-white tabular-nums">{{ total.toFixed(2) }}</span>
                            <span class="text-sm font-medium text-emerald-200">{{ (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency'))) ? preferences('currency') : 'SDG' }}</span>
                        </div>
                        <div v-if="form.discount > 0" class="mt-2 text-xs text-emerald-200">
                            {{ __("Subtotal") }}: {{ subtotal.toFixed(2) }} &mdash; {{ __("Discount") }}: {{ form.discount }}
                        </div>
                    </div>
                    <div class="px-5 py-4 space-y-2.5 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ __("Subtotal") }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ subtotal.toFixed(2) }}</span>
                        </div>
                        <div v-if="form.discount > 0" class="flex items-center justify-between text-sm">
                            <span class="text-red-500">{{ __("Discount") }}</span>
                            <span class="font-semibold text-red-500 tabular-nums">-{{ form.discount }}</span>
                        </div>
                    </div>
                    <div class="px-5 py-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <span v-if="!form.processing">{{ isEdit ? __("Update Quote") : __("Create Quote") }}</span>
                            <span v-else class="inline-flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __("Processing...") }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Quote Details -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none p-5">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                        </svg>
                        {{ __("Quote Details") }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <InputLabel :value="__('Expiry date')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <input
                                v-model="form.expires_at"
                                type="date"
                                class="w-full px-3 py-2 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50"
                            />
                            <InputError :message="form.errors.expires_at" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel :value="__('Discount')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <TextInput v-model="form.discount" type="number" inputmode="decimal" min="0" step="0.01" class="block w-full" />
                            <InputError :message="form.errors.discount" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel :value="__('Notes')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-gray-900 dark:text-white text-sm resize-none"
                                :placeholder="__('Optional notes...')"
                            ></textarea>
                            <InputError :message="form.errors.notes" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <QuickAddPartyModal
            :show="showQuickAddModal"
            type="customer"
            @close="showQuickAddModal = false"
            @created="onCustomerCreated"
        />
    </div>
</template>
