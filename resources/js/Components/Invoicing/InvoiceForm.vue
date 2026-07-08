<script setup>
    import InputLabel from "@/Components/InputLabel.vue";
    import TextInput from "@/Components/TextInput.vue";
    import InputError from "@/Components/InputError.vue";
    import PurchaseProduct from "@/Models/PurchaseProduct";
    import PaymentCaptureFields from "@/Components/Invoicing/PaymentCaptureFields.vue";
    import { ref, computed } from "vue";
    import { useForm, Link } from "@inertiajs/vue3";
    import QuickAddPartyModal from "@/Components/QuickAddPartyModal.vue";
    import { useAsyncOptions } from "@/Composables/useAsyncOptions";

    const props = defineProps({
        type: {
            type: String,
            required: true,
            validator: (v) => ['sale', 'purchase'].includes(v),
        },
        mode: {
            type: String,
            default: 'create',
            validator: (v) => ['create', 'edit'].includes(v),
        },
        invoice: {
            type: Object,
            default: null,
        },
        paymentMethods: Object,
        banks: Array,
        treasuryAccounts: Array,
        prefill: {
            type: Object,
            default: null,
        },
    });

    const isSale = computed(() => props.type === 'sale');
    const isEdit = computed(() => props.mode === 'edit');

    const partyType = computed(() => isSale.value ? 'customer' : 'supplier');
    const partyModelClass = computed(() => isSale.value ? 'App\\Models\\Customer' : 'App\\Models\\Supplier');
    const partyApiRoute = computed(() => isSale.value ? route('api.customers.index') : route('api.suppliers.index'));
    const resource = computed(() => isSale.value ? 'sales' : 'purchases');
    const indexRoute = computed(() => route(`${resource.value}.index`));

    const {
        options: partyOptions,
        loading: partyLoading,
        loadMore: loadMoreParty,
        onSearch: searchParty,
    } = useAsyncOptions(partyApiRoute.value);

    const {
        options: productOptions,
        loading: productsLoading,
        loadMore: loadMoreProducts,
        onSearch: searchProduct,
    } = useAsyncOptions(route('api.products.index'));

    const lineItems = ref(
        props.prefill?.items?.length
            ? props.prefill.items.map((item) => new PurchaseProduct({
                product: item.product?.id ?? item.product_id,
                unit: item.unit_id,
                quantity: item.quantity,
                price: parseFloat(item.unit_price),
                description: item.description ?? null,
            }))
            : [new PurchaseProduct()]
    );

    const newRow = () => {
        lineItems.value.push(new PurchaseProduct());
    };

    const totalCost = computed(() => {
        return lineItems.value.reduce((sum, product) => sum + product.total(), 0);
    });

    const netTotal = computed(() => totalCost.value - Number(form.discount || 0));

    const productUnits = (id) => {
        const product = productOptions.value.find((p) => p.id == id);
        if (!product) return;
        return product.units;
    };

    const prefillParty = props.prefill?.customer ?? props.prefill?.supplier ?? null;

    const form = useForm({
        invocable: prefillParty
            ? { id: prefillParty.id, name: prefillParty.name, type: partyModelClass.value }
            : null,
        payment_method: props.prefill?.payment_method ?? 'cash',
        treasury_account_id: null,
        discount: props.prefill?.discount ?? 0,
        initial_payment_amount: 0,
        payment_reference: '',
        payment_notes: '',
        receipt: null,
        cheque_bank_id: null,
        cheque_due_date: '',
        cheque_number: '',
        from_quote_id: props.prefill?.from_quote_id ?? null,
    });

    form.transform((data) => ({
        ...data,
        total: totalCost.value,
        products: lineItems.value,
    }));

    const showQuickAddModal = ref(false);

    const selectParty = (party) => {
        return party ? { ...party, type: partyModelClass.value } : null;
    };

    const onPartyCreated = (party) => {
        form.invocable = selectParty(party);
        partyOptions.value.unshift(party);
    };

    const submit = () => {
        if (isEdit.value) {
            form.put(route(`${resource.value}.update`, props.invoice.id));
            return;
        }
        form.post(route(`${resource.value}.store`));
    };
</script>

<template>
    <div>
        <!-- Page Header -->
        <div class="flex items-center gap-3 mb-6">
            <Link
                :href="indexRoute"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </Link>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                    <template v-if="isEdit">
                        {{ isSale ? __("Edit Sales Invoice") : __("Edit Purchase Invoice") }}
                        <span v-if="invoice?.serial_number" class="ml-2 text-gray-500 dark:text-gray-400 font-normal">#{{ invoice.serial_number }}</span>
                    </template>
                    <template v-else>
                        {{ isSale ? __("New Sales Invoice") : __("New Purchase Invoice") }}
                    </template>
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="isEdit">{{ __("Update line items and totals. Payments cannot be edited.") }}</template>
                    <template v-else>{{ isSale ? __("Create a new sales invoice for a customer") : __("Create a new purchase invoice for a supplier") }}</template>
                </p>
            </div>
        </div>

        <form class="flex flex-col lg:flex-row gap-6 items-start" @submit.prevent="submit">
            <!-- Main Panel -->
            <div class="flex-1 min-w-0 space-y-4">
                <!-- Party Selector -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-none">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                            <svg v-if="isSale" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ isSale ? __("Customer") : __("Supplier") }}</span>
                    </div>
                    <div class="max-w-sm" v-auto-animate>
                        <CustomSelect
                            :model-value="form.invocable"
                            :options="partyOptions"
                            label="name"
                            track-by="id"
                            :remote="true"
                            :loading="partyLoading"
                            @update:model-value="id => { form.invocable = selectParty(partyOptions.find(p => p.id === id)) }"
                            @search-change="searchParty"
                            @scroll-end="loadMoreParty"
                            :placeholder="isSale ? __('Search customer...') : __('Search supplier...')"
                        >
                            <template #noResult>
                                <div class="p-2 space-y-2 text-center">
                                    <p class="text-sm text-gray-500">{{ __("No elements found. Consider changing the search query.") }}</p>
                                    <button
                                        type="button"
                                        @click="showQuickAddModal = true"
                                        class="text-emerald-600 hover:text-emerald-700 font-semibold text-sm"
                                    >
                                        + {{ isSale ? __("Add New Customer") : __("Add New Supplier") }}
                                    </button>
                                </div>
                            </template>
                        </CustomSelect>
                        <InputError :message="form.errors.invocable" class="mt-1" />
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

                    <div class="hidden lg:grid lg:grid-cols-12 gap-3 px-5 py-2.5 bg-gray-50 dark:bg-gray-900/50 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                        <div class="col-span-4">{{ __("Product") }}</div>
                        <div class="col-span-2">{{ __("Unit") }}</div>
                        <div class="col-span-2">{{ __("Qty") }}</div>
                        <div class="col-span-2">{{ __("Price") }}</div>
                        <div class="col-span-2 text-right pr-8">{{ __("Total") }}</div>
                    </div>

                    <div class="divide-y divide-gray-50 dark:divide-gray-700/50" v-auto-animate>
                        <div v-for="(item, index) in lineItems" :key="index" class="px-5 py-4">
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                                <div class="lg:col-span-4">
                                    <label class="lg:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Product") }}</label>
                                    <CustomSelect
                                        v-model="item.product"
                                        :options="productOptions"
                                        :multiple="false"
                                        :close-on-select="true"
                                        :placeholder="__('Select Product')"
                                        label="name"
                                        track-by="id"
                                        :remote="true"
                                        :loading="productsLoading"
                                        @search-change="searchProduct"
                                        @scroll-end="loadMoreProducts"
                                        class="w-full"
                                        @update:model-value="item.unit = null"
                                    />
                                    <InputError :message="form.errors[`products.${index}.product`]" class="mt-1" />
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="lg:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Unit") }}</label>
                                    <CustomSelect
                                        v-model="item.unit"
                                        :options="productUnits(item.product) || []"
                                        :multiple="false"
                                        :close-on-select="true"
                                        :placeholder="__('Unit')"
                                        label="name"
                                        track-by="id"
                                        class="w-full"
                                        :disabled="!item.product"
                                    />
                                    <InputError :message="form.errors[`products.${index}.unit`]" class="mt-1" />
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="lg:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Qty") }}</label>
                                    <TextInput v-model="item.quantity" type="number" inputmode="numeric" min="0.01" step="0.01" class="block w-full" required />
                                    <InputError :message="form.errors[`products.${index}.quantity`]" class="mt-1" />
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="lg:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Price") }}</label>
                                    <TextInput v-model="item.price" type="number" inputmode="decimal" min="0" step="0.01" class="block w-full" required />
                                    <InputError :message="form.errors[`products.${index}.price`]" class="mt-1" />
                                </div>

                                <div class="lg:col-span-2 flex items-center justify-between lg:justify-end gap-2 lg:pt-1.5">
                                    <div>
                                        <label class="lg:hidden text-xs font-bold uppercase tracking-wider text-gray-400 mb-1 block">{{ __("Total") }}</label>
                                        <span class="font-bold text-gray-900 dark:text-white tabular-nums text-sm">{{ item.total().toFixed(2) }}</span>
                                    </div>
                                    <button
                                        v-if="lineItems.length > 1"
                                        type="button"
                                        @click="lineItems.splice(index, 1)"
                                        class="p-2.5 min-h-11 min-w-11 flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors flex-shrink-0"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <textarea
                                    v-model="item.description"
                                    rows="1"
                                    class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all outline-none text-gray-900 dark:text-white text-sm resize-none"
                                    :placeholder="__('Description (optional)...')"
                                ></textarea>
                                <InputError :message="form.errors[`products.${index}.description`]" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-4 bg-gray-50/30 dark:bg-gray-900/20 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="newRow"
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
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-80 xl:w-96 flex flex-col gap-4 lg:sticky lg:top-4">
                <!-- Summary Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none overflow-hidden">
                    <div class="px-5 py-5 bg-emerald-600 dark:bg-emerald-700">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-200 mb-1">{{ __("Invoice Total") }}</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl font-black text-white tabular-nums">{{ netTotal.toFixed(2) }}</span>
                            <span class="text-sm font-medium text-emerald-200">{{ (preferences('currency') && /^[A-Z]{3}$/.test(preferences('currency'))) ? preferences('currency') : 'SDG' }}</span>
                        </div>
                    </div>
                    <div class="px-5 py-4 space-y-2.5 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ __("Subtotal") }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ totalCost.toFixed(2) }}</span>
                        </div>
                        <div v-if="form.discount > 0" class="flex items-center justify-between text-sm">
                            <span class="text-red-500">{{ __("Discount") }}</span>
                            <span class="font-semibold text-red-500 tabular-nums">-{{ form.discount }}</span>
                        </div>
                        <div v-if="!isEdit && form.initial_payment_amount > 0" class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ __("Paid") }}</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ form.initial_payment_amount }}</span>
                        </div>
                    </div>
                    <div class="px-5 py-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-3 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <span v-if="!form.processing">
                                <template v-if="isEdit">{{ __("Save Changes") }}</template>
                                <template v-else>{{ isSale ? __("Complete Sale") : __("Complete Purchase") }}</template>
                            </span>
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

                <!-- Payment Details -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-none p-5">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        {{ __("Payment Details") }}
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <InputLabel for="payment_method" :value="__('Payment Method')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <CustomSelect
                                v-model="form.payment_method"
                                :options="Object.entries(paymentMethods).map(([label, value]) => ({ id: value, label }))"
                                :multiple="false"
                                :close-on-select="true"
                                :placeholder="__('Select Payment Method')"
                                label="label"
                                track-by="id"
                                class="w-full"
                            >
                                <template #singleLabel="{ option }">
                                    {{ __(option?.label) }}
                                </template>
                                <template #option="{ option }">
                                    {{ __(option.label) }}
                                </template>
                            </CustomSelect>
                            <InputError :message="form.errors.payment_method" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="discount" :value="__('Discount')" class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" />
                            <TextInput id="discount" v-model="form.discount" type="number" inputmode="decimal" min="0" step="0.01" class="block w-full" />
                            <InputError :message="form.errors.discount" class="mt-1" />
                        </div>

                        <PaymentCaptureFields
                            v-if="!isEdit"
                            :form="form"
                            :is-sale="isSale"
                            :banks="banks"
                            :treasury-accounts="treasuryAccounts ?? []"
                        />
                    </div>
                </div>
            </div>
        </form>

        <QuickAddPartyModal
            :show="showQuickAddModal"
            :type="partyType"
            @close="showQuickAddModal = false"
            @created="onPartyCreated"
        />
    </div>
</template>
