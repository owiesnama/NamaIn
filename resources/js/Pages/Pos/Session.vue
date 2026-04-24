<script setup>
import { ref, computed } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import Modal from "@/Components/Modal.vue";
import CustomSelect from "@/Components/CustomSelect.vue";
import QuickAddPartyModal from "@/Components/QuickAddPartyModal.vue";
import InputLabel from "@/Components/InputLabel.vue";

const props = defineProps({
    session: Object,
    products: Array,
    customers: Array,
    session_stats: Object,
    flash: Object,
});

const page = usePage();
const search = ref('');
const cart = ref([]);
const showingCloseModal = ref(false);
const showingAddCustomerModal = ref(false);
const showingTransferModal = ref(false);
const showingUnavailableModal = ref(false);
const showingSaleCompleteModal = ref(false);
const transfersRequired = ref([]);
const unavailableProducts = ref([]);
const localCustomers = ref([...props.customers]);
const cartErrorMessage = ref('');
const completedSale = ref({
    invoiceId: null,
    total: 0,
});

const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    return props.products.filter(p =>
        p.name.toLowerCase().includes(search.value.toLowerCase())
    );
});

const addToCart = (product) => {
    const existing = cart.value.find(item => item.product_id === product.id && item.unit_id === (product.selected_unit_id || null));
    const price = product.selected_price !== undefined && product.selected_price !== '' ? product.selected_price : (product.price || product.cost || 0);
    if (existing) {
        existing.quantity++;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            price: price,
            quantity: 1,
            sale_point_qty: product.sale_point_qty,
            replenishment: product.replenishment,
            unit_id: product.selected_unit_id || null,
            units: product.units || [],
        });
    }
};

const updateItemQuantity = (item, delta) => {
    item.quantity = Math.max(1, item.quantity + delta);
};

const updateItemPrice = (item, newPrice) => {
    item.price = Math.max(0, parseFloat(newPrice) || 0);
};

const updateItemUnit = (item, unitId) => {
    item.unit_id = unitId;
    const unit = item.units.find(u => u.id === unitId);
    if (unit) {
        // You might want to adjust price based on unit here if you have unit-specific prices
        // For now we just keep the current price or let user adjust it
    }
};

const removeFromCart = (index) => {
    cart.value.splice(index, 1);
};

const total = computed(() => {
    return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0);
});

const checkoutForm = useForm({
    session_id: props.session.id,
    customer_id: '',
    items: [],
    total: 0,
    payment_method: 'cash',
    idempotency_key: '',
    acknowledge_transfers: false,
});

const onCustomerCreated = (customer) => {
    localCustomers.value.unshift(customer);
    checkoutForm.customer_id = customer.id;
};

const printLastReceipt = () => {
    if (!completedSale.value.invoiceId) {
        return;
    }

    window.open(route('invoices.print', completedSale.value.invoiceId), '_blank');
};

const checkout = () => {
    if (cart.value.length === 0) return;

    cartErrorMessage.value = '';
    checkoutForm.items = cart.value;
    checkoutForm.total = total.value;
    if (!checkoutForm.idempotency_key) {
        checkoutForm.idempotency_key = Date.now().toString();
    }

    router.post(route('pos.checkout'), checkoutForm, {
        onSuccess: (successPage) => {
            const response = successPage.props.flash?.response;

            if (response && response.requires_confirmation) {
                if (response.unavailable_products) {
                    unavailableProducts.value = response.unavailable_products;
                    showingUnavailableModal.value = true;
                } else {
                    transfersRequired.value = response.transfers_required;
                    showingTransferModal.value = true;
                }

                return;
            }

            const invoiceId = page.props.flash?.last_invoice_id || successPage.props.flash?.last_invoice_id;
            completedSale.value = {
                invoiceId: invoiceId ?? null,
                total: total.value,
            };

            cart.value = [];
            checkoutForm.reset();
            showingSaleCompleteModal.value = true;
        },
        onError: (errors) => {
            cartErrorMessage.value = props.flash?.error || errors.message || __('Error during checkout');
        }
    });
};

const confirmTransferAndCheckout = () => {
    checkoutForm.acknowledge_transfers = true;
    showingTransferModal.value = false;
    checkout();
};

const closeSessionForm = useForm({
    session_id: props.session.id,
    closing_float: '',
});

const liveVariance = computed(() => {
    const actual = parseFloat(closeSessionForm.closing_float);
    if (isNaN(actual) || closeSessionForm.closing_float === '') return null;
    return actual - (props.session_stats?.expected_closing_float ?? 0);
});

const varianceLabel = computed(() => {
    if (liveVariance.value === null) return null;
    const v = liveVariance.value;
    if (v > 0) return `+${v.toFixed(2)}`;
    return v.toFixed(2);
});

const closeSession = () => {
    closeSessionForm.post(route('pos.close'));
};
</script>

<template>
    <AppLayout :title="__('POS Session')">
        <div class="flex flex-col lg:flex-row h-[calc(100vh-120px)] gap-6">
            <!-- Products Grid -->
            <div class="flex-grow flex flex-col min-w-0">
                <div class="mb-6">
                    <div class="relative flex items-center">
                        <span class="absolute ltr:left-3 rtl:right-3 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </span>
                        <TextInput
                            v-model="search"
                            :placeholder="__('Search products...')"
                            class="w-full ltr:ps-10 rtl:pe-10 py-3 rounded-xl"
                        />
                    </div>
                </div>

                <div class="flex-grow overflow-y-auto grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 p-1 auto-rows-max custom-scrollbar">
                    <div
                        v-for="product in filteredProducts"
                        :key="product.id"
                        :disabled="product.sale_point_qty === 0 && !product.replenishment"
                        class="bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-emerald-500 transition-all text-start shadow-sm group h-fit flex flex-col"
                        :class="{'opacity-50 grayscale': product.sale_point_qty === 0 && !product.replenishment}"
                    >
                        <div @click="addToCart(product)" class="cursor-pointer w-full aspect-square bg-gray-50 dark:bg-gray-800 rounded-lg mb-3 flex items-center justify-center text-gray-400 group-hover:bg-emerald-50 dark:group-hover:bg-emerald-900/20 group-hover:text-emerald-500 transition-colors overflow-hidden relative">
                            <div v-if="product.sale_point_qty === 0 && product.replenishment" class="absolute top-2 right-2 flex items-center gap-x-1 px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[9px] font-bold uppercase tracking-wider">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                                {{ __('Transfer') }}
                            </div>
                            <div v-else-if="product.sale_point_qty === 0" class="absolute inset-0 bg-gray-900/10 flex items-center justify-center">
                                <span class="bg-gray-800/80 text-white px-2 py-1 rounded text-[10px] font-bold uppercase">{{ __('Out of Stock') }}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </div>
                        <h3 @click="addToCart(product)" class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 truncate mb-1">{{ product.name }}</h3>

                        <!-- Product Price & Unit Settings -->
                        <div class="mb-3 space-y-2">
                             <div v-if="product.units && product.units.length > 0">
                                <select v-model="product.selected_unit_id" class="w-full text-[10px] py-1 px-2 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                                    <option :value="null">{{ __('Base Unit') }}</option>
                                    <option v-for="unit in product.units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-[10px] text-gray-400">SDG</span>
                                <input
                                    type="number"
                                    v-model="product.selected_price"
                                    :placeholder="product.price"
                                    class="w-full text-xs py-1 px-2 bg-transparent border-gray-200 dark:border-gray-700 rounded-md focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                        </div>

                        <div class="flex justify-between items-center mt-auto pt-2 border-t border-gray-100 dark:border-gray-800">
                            <p class="text-sm font-bold text-emerald-600 truncate mr-2">{{ (product.selected_price !== undefined && product.selected_price !== '') ? product.selected_price : (product.price || product.cost || 0) }}</p>
                            <span v-if="product.sale_point_qty > 0" class="shrink-0 text-[10px] font-medium text-gray-400">{{ product.sale_point_qty }} {{ __('in stock') }}</span>
                            <span v-else-if="product.replenishment" class="shrink-0 text-[10px] font-medium text-amber-500">{{ __('via warehouse') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cart Sidebar -->
            <div class="w-full lg:w-96 flex flex-col bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <div class="flex items-center gap-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Cart') }}</h2>
                    </div>
                    <button @click="showingCloseModal = true" class="text-xs font-semibold text-red-500 hover:text-red-600 transition-colors uppercase tracking-wider">
                        {{ __('Close Session') }}
                    </button>
                </div>

                <div class="flex-grow overflow-y-auto p-5 space-y-4 custom-scrollbar">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center text-gray-400 py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-4 opacity-20">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <p>{{ __('Cart is empty') }}</p>
                    </div>

                    <div v-for="(item, index) in cart" :key="index" class="flex flex-col group p-3 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-lg transition-colors border-b border-gray-100 dark:border-gray-800 last:border-0" :class="{'bg-amber-50/50 border-amber-100': item.sale_point_qty < item.quantity}">
                        <div class="flex justify-between items-start mb-2">
                            <div class="min-w-0 flex-grow">
                                <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.name }}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                     <div v-if="item.units && item.units.length > 0">
                                        <select :value="item.unit_id" @change="updateItemUnit(item, $event.target.value)" class="text-[10px] py-0.5 px-1 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 rounded-md focus:ring-emerald-500 focus:border-emerald-500">
                                            <option :value="null">{{ __('Base') }}</option>
                                            <option v-for="unit in item.units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-[10px] text-gray-400">SDG</span>
                                        <input
                                            type="number"
                                            :value="item.price"
                                            @input="updateItemPrice(item, $event.target.value)"
                                            class="w-20 text-[10px] py-0.5 px-1 bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 rounded-md focus:ring-emerald-500 focus:border-emerald-500"
                                        />
                                    </div>
                                </div>
                            </div>
                            <button @click="removeFromCart(index)" class="text-gray-300 hover:text-red-500 transition-colors shrink-0 ml-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <button @click="updateItemQuantity(item, -1)" class="p-1 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-emerald-500 hover:text-white transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                    </svg>
                                </button>
                                <input
                                    type="number"
                                    v-model.number="item.quantity"
                                    class="w-12 text-center text-xs font-semibold py-1 px-0 bg-transparent border-0 focus:ring-0"
                                    min="1"
                                />
                                <button @click="updateItemQuantity(item, 1)" class="p-1 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-emerald-500 hover:text-white transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ item.quantity * item.price }} SDG</span>
                        </div>

                        <div v-if="item.sale_point_qty < item.quantity && item.replenishment" class="flex items-center gap-x-1 mt-2 p-1.5 bg-amber-50 dark:bg-amber-900/10 rounded-md">
                            <svg class="h-3 w-3 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            <span class="text-[9px] font-medium text-amber-600 uppercase tracking-tighter">
                                {{ __('Not here') }} · {{ item.replenishment.available_qty }} {{ __('at') }} {{ item.replenishment.warehouse_name }}
                            </span>
                        </div>
                        <div v-else-if="item.sale_point_qty < item.quantity" class="flex items-center gap-x-1 mt-2 p-1.5 bg-red-50 dark:bg-red-900/10 rounded-md">
                            <svg class="h-3 w-3 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span class="text-[9px] font-medium text-red-600 uppercase tracking-tighter">
                                {{ __('Out of stock everywhere') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Customer') }}</label>
                            <button @click="showingAddCustomerModal = true" class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 hover:text-emerald-700 transition-colors">
                                + {{ __('Add New') }}
                            </button>
                        </div>
                        <CustomSelect
                            v-model="checkoutForm.customer_id"
                            :options="localCustomers"
                            label="name"
                            track-by="id"
                            :placeholder="__('Walk-in Customer')"
                            class="w-full"
                        />
                    </div>

                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total') }}</span>
                        <span class="text-2xl font-bold text-emerald-600">{{ total }} SDG</span>
                    </div>

                    <PrimaryButton
                        @click="checkout"
                        class="w-full justify-center py-4 text-base rounded-xl shadow-lg transition-all duration-300"
                        :class="cart.some(i => i.sale_point_qty < i.quantity) ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-200/50 dark:shadow-none' : 'bg-emerald-600 shadow-emerald-200/50 dark:shadow-none'"
                        :disabled="cart.length === 0 || checkoutForm.processing || cart.some(i => i.sale_point_qty < i.quantity && !i.replenishment)"
                    >
                        <span v-if="cart.some(i => i.sale_point_qty < i.quantity)">{{ __('Review & Complete') }} — {{ total }} SDG</span>
                        <span v-else>{{ __('Complete Sale') }} — {{ total }} SDG</span>
                    </PrimaryButton>

                    <!-- Cart error message banner -->
                    <div v-if="cartErrorMessage" class="mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 text-sm">
                        {{ cartErrorMessage }}
                    </div>
                </div>
            </div>
        </div>

        <QuickAddPartyModal
            :show="showingAddCustomerModal"
            type="customer"
            @close="showingAddCustomerModal = false"
            @created="onCustomerCreated"
        />

        <!-- Sale complete modal -->
        <Modal :show="showingSaleCompleteModal" @close="showingSaleCompleteModal = false">
            <div class="p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Sale Completed') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Invoice') }}: <span class="font-medium text-gray-900 dark:text-white">#{{ completedSale.invoiceId }}</span>
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Total') }}: <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ completedSale.total }} SDG</span>
                    </p>
                </div>

                <div class="flex justify-end gap-x-3">
                    <button @click="showingSaleCompleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        {{ __('Close') }}
                    </button>
                    <PrimaryButton @click="printLastReceipt" class="flex items-center gap-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 12H21m-4.5 4.5h4.5v4.5M3 12h4.5m-4.5 4.5h4.5v4.5M9 12h6m-6 4.5h3" />
                        </svg>
                        {{ __('Print Receipt') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showingTransferModal" @close="showingTransferModal = false">
            <div class="p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Stock transfer required') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('The following items will be transferred before completing the sale') }}
                    </p>
                </div>

                <ul class="space-y-3 mb-6">
                    <li v-for="transfer in transfersRequired" :key="transfer.product_id" class="flex items-center justify-between text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex flex-col">
                            <span class="font-medium text-gray-900 dark:text-white">{{ transfer.product_name }}</span>
                            <span class="text-xs text-gray-500">{{ __('From') }}: {{ transfer.from_warehouse_name }}</span>
                        </div>
                        <span class="font-bold text-emerald-600">{{ transfer.quantity }} {{ __('unit(s)') }}</span>
                    </li>
                </ul>

                <div class="flex justify-end gap-x-3">
                    <button @click="showingTransferModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        {{ __('Cancel') }}
                    </button>
                    <PrimaryButton @click="confirmTransferAndCheckout" :disabled="checkoutForm.processing">
                        {{ __('Transfer & Complete Sale') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showingCloseModal" @close="showingCloseModal = false">
            <div class="p-6">
                <!-- Header -->
                <div class="flex items-center gap-x-3 mb-6">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600 dark:text-red-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Close POS Session') }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Please verify the cash in hand before closing.') }}</p>
                    </div>
                </div>

                <!-- Reconciliation summary -->
                <div v-if="session_stats" class="mb-5 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Opening Float') }}</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ session_stats.opening_float.toFixed(2) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Cash Sales This Session') }}</span>
                            <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">+ {{ session_stats.cash_sales_total.toFixed(2) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/40">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Expected Closing Float') }}</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ session_stats.expected_closing_float.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actual cash input -->
                <div class="space-y-4">
                    <div>
                        <InputLabel for="closing_float" :value="__('Actual Cash in Hand')" />
                        <TextInput
                            v-model="closeSessionForm.closing_float"
                            id="closing_float"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-full rounded-xl"
                            :placeholder="session_stats ? session_stats.expected_closing_float.toFixed(2) : '0.00'"
                        />
                    </div>

                    <!-- Live variance -->
                    <div v-if="liveVariance !== null" class="flex items-center justify-between rounded-lg px-4 py-3 transition-colors"
                        :class="[
                            liveVariance === 0 ? 'bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800' :
                            liveVariance > 0  ? 'bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800' :
                                                'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800'
                        ]"
                    >
                        <span class="text-sm font-medium"
                            :class="liveVariance < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'"
                        >
                            {{ __('Variance') }}
                        </span>
                        <span class="text-sm font-bold"
                            :class="liveVariance < 0 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400'"
                        >
                            {{ varianceLabel }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-x-3">
                    <button @click="showingCloseModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        {{ __('Cancel') }}
                    </button>
                    <PrimaryButton @click="closeSession" :disabled="closeSessionForm.processing" class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                        {{ __('Confirm & Close') }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Unavailable products modal -->
        <Modal :show="showingUnavailableModal" @close="showingUnavailableModal = false">
            <div class="p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Items Unavailable') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('The following items are unavailable and cannot be purchased') }}
                    </p>
                </div>

                <ul class="space-y-3 mb-6">
                    <li v-for="product in unavailableProducts" :key="product.product_id" class="flex items-center justify-between text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex flex-col">
                            <span class="font-medium text-gray-900 dark:text-white">{{ product.product_name }}</span>
                            <span class="text-xs text-gray-500">{{ __('Available locally') }}: {{ product.available_locally }}</span>
                        </div>
                        <span class="font-bold text-red-600 dark:text-red-400">{{ __('Needed') }}: {{ product.needed }}</span>
                    </li>
                </ul>

                <div class="flex justify-end gap-x-3">
                    <button @click="showingUnavailableModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
