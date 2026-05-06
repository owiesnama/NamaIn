<script setup>
import { ref, computed } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import AppLayout from "@/Layouts/AppLayout.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CustomSelect from "@/Components/CustomSelect.vue";
import QuickAddPartyModal from "@/Components/QuickAddPartyModal.vue";
import { useAsyncOptions } from "@/Composables/useAsyncOptions";

import PosProductGrid from "@/Components/Pos/PosProductGrid.vue";
import PosCheckoutModal from "@/Components/Pos/PosCheckoutModal.vue";
import PosSaleCompleteModal from "@/Components/Pos/PosSaleCompleteModal.vue";
import PosCloseSessionModal from "@/Components/Pos/PosCloseSessionModal.vue";

const props = defineProps({
    session: Object,
    initialProducts: Object,
    session_stats: Object,
    flash: Object,
});

const page = usePage();
const currency = window.preferences?.('currency') || 'SDG';
const fmt = (val) => `${Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency}`;

// ── Customer ─────────────────────────────────────────────────────────────────
const {
    options: customerOptions,
    loading: customersLoading,
    loadMore: loadMoreCustomers,
    onSearch: searchCustomer,
} = useAsyncOptions(route('api.customers.index'));
const showingAddCustomerModal = ref(false);

const onCustomerCreated = (customer) => {
    customerOptions.value.unshift(customer);
    checkoutForm.customer_id = customer.id;
};

// ── Cart ─────────────────────────────────────────────────────────────────────
const cart = ref([]);

const addToCart = (product) => {
    if (product.sale_point_qty === 0 && !product.replenishment) return;
    const existing = cart.value.find(item => item.product_id === product.id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            price: product.price || 0,
            quantity: 1,
            sale_point_qty: product.sale_point_qty,
            replenishment: product.replenishment,
            unit_id: null,
            units: product.units || [],
        });
    }
};

const updateItemQuantity = (item, delta) => { item.quantity = Math.max(1, item.quantity + delta); };
const updateItemPrice = (item, newPrice) => { item.price = Math.max(0, parseFloat(newPrice) || 0); };
const updateItemUnit = (item, unitId) => { item.unit_id = unitId || null; };
const removeFromCart = (index) => { cart.value.splice(index, 1); };

const cartSubtotal = computed(() => cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0));

// ── Discount ─────────────────────────────────────────────────────────────────
const discountValue = ref('');
const discountType = ref('percent');

const discountAmount = computed(() => {
    const v = parseFloat(discountValue.value) || 0;
    if (v <= 0) return 0;
    if (discountType.value === 'percent') return Math.min(cartSubtotal.value, (cartSubtotal.value * v) / 100);
    return Math.min(cartSubtotal.value, v);
});

const total = computed(() => Math.max(0, cartSubtotal.value - discountAmount.value));

// ── Checkout ─────────────────────────────────────────────────────────────────
const checkoutModalRef = ref(null);
const showingCheckoutModal = ref(false);
const cartErrorMessage = ref('');
const showingTransferModal = ref(false);
const showingUnavailableModal = ref(false);
const showingSaleCompleteModal = ref(false);
const transfersRequired = ref([]);
const unavailableProducts = ref([]);
const completedSale = ref({ invoiceId: null, total: 0, paymentMethod: 'cash', change: null });
const showingCloseModal = ref(false);

const checkoutForm = useForm({
    session_id: props.session.id,
    customer_id: '',
    items: [],
    total: 0,
    payment_method: 'cash',
    idempotency_key: '',
    acknowledge_transfers: false,
});

const openCheckoutModal = () => {
    if (cart.value.length === 0) return;
    checkoutModalRef.value?.reset();
    showingCheckoutModal.value = true;
};

const checkout = async ({ paymentMethod, change }) => {
    cartErrorMessage.value = '';
    checkoutForm.items = cart.value;
    checkoutForm.total = total.value;
    checkoutForm.payment_method = paymentMethod;

    if (!checkoutForm.idempotency_key) {
        checkoutForm.idempotency_key = Date.now().toString();
    }

    checkoutForm.processing = true;

    try {
        const { data } = await axios.post(route('pos.preflight'), {
            session_id: checkoutForm.session_id,
            items: checkoutForm.items,
        });

        if (data.requires_confirmation) {
            showingCheckoutModal.value = false;
            if (data.unavailable_products) {
                unavailableProducts.value = data.unavailable_products;
                showingUnavailableModal.value = true;
            } else {
                transfersRequired.value = data.transfers_required;
                showingTransferModal.value = true;
            }
            return;
        }

        proceedToCheckout(paymentMethod, change);
    } catch (error) {
        cartErrorMessage.value = error.response?.data?.message || __('Error during checkout');
    } finally {
        checkoutForm.processing = false;
    }
};

const proceedToCheckout = (paymentMethod, change) => {
    router.post(route('pos.checkout'), checkoutForm, {
        onSuccess: (successPage) => {
            const invoiceId = page.props.flash?.last_invoice_id || successPage.props.flash?.last_invoice_id;
            completedSale.value = {
                invoiceId: invoiceId ?? null,
                total: total.value,
                paymentMethod: paymentMethod || checkoutForm.payment_method,
                change: change ?? null,
            };
            cart.value = [];
            discountValue.value = '';
            checkoutForm.reset();
            showingCheckoutModal.value = false;
            showingSaleCompleteModal.value = true;
        },
        onError: (errors) => {
            cartErrorMessage.value = props.flash?.error || errors.message || __('Error during checkout');
        },
    });
};

const confirmTransferAndCheckout = () => {
    checkoutForm.acknowledge_transfers = true;
    showingTransferModal.value = false;
    proceedToCheckout(checkoutForm.payment_method, null);
};

const startNewSale = () => {
    showingSaleCompleteModal.value = false;
    checkoutForm.idempotency_key = '';
};

const printLastReceipt = () => {
    if (completedSale.value.invoiceId) {
        window.open(route('invoices.print', completedSale.value.invoiceId), '_blank');
    }
};

const paymentMethodLabels = [
    { value: 'cash', label: __('Cash') },
    { value: 'bank_transfer', label: __('Bank Transfer') },
    { value: 'credit', label: __('Credit') },
];
</script>

<template>
    <AppLayout :title="__('POS Session')">
        <div class="flex flex-col lg:flex-row h-[calc(100vh-120px)] gap-4">

            <!-- Products Grid -->
            <PosProductGrid :initial-products="initialProducts" :currency="currency" @add-to-cart="addToCart" />

            <!-- Cart Sidebar -->
            <div class="w-full lg:w-96 flex flex-col bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">

                <!-- Cart header -->
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <div class="flex items-center gap-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ __('Cart') }}</h2>
                        <span v-if="cart.length > 0" class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ cart.length }}</span>
                    </div>
                    <button @click="showingCloseModal = true" class="text-xs font-semibold text-red-500 hover:text-red-600 transition-colors uppercase tracking-wider">{{ __('Close Session') }}</button>
                </div>

                <!-- Cart items -->
                <div class="flex-grow overflow-y-auto p-4 space-y-2">
                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center text-gray-400 py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-4 opacity-20">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <p class="text-sm">{{ __('Cart is empty') }}</p>
                    </div>

                    <div v-for="(item, index) in cart" :key="index" class="flex flex-col p-3 rounded-xl border transition-colors" :class="item.sale_point_qty < item.quantity ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800' : 'bg-gray-50 dark:bg-gray-800/50 border-transparent'">
                        <div class="flex items-start justify-between gap-x-2 mb-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ item.name }}</p>
                            <button @click="removeFromCart(index)" class="text-gray-300 hover:text-red-500 transition-colors shrink-0 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-x-2 mb-2">
                            <select v-if="item.units && item.units.length > 0" :value="item.unit_id" @change="updateItemUnit(item, $event.target.value)" class="text-[10px] py-1 px-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                                <option :value="null">{{ __('Base') }}</option>
                                <option v-for="unit in item.units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                            </select>
                            <div class="flex items-center gap-x-1 flex-1">
                                <span class="text-[10px] text-gray-400 shrink-0">{{ currency }}</span>
                                <input type="number" :value="item.price" @input="updateItemPrice(item, $event.target.value)" class="w-full text-xs py-1 px-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-emerald-500 focus:border-emerald-500" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-x-1">
                                <button @click="updateItemQuantity(item, -1)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4" /></svg>
                                </button>
                                <input type="number" v-model.number="item.quantity" class="w-10 text-center text-sm font-bold py-1 px-0 bg-transparent border-0 focus:ring-0" min="1" />
                                <button @click="updateItemQuantity(item, 1)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                                </button>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ fmt(item.quantity * item.price) }}</span>
                        </div>
                        <div v-if="item.sale_point_qty < item.quantity && item.replenishment" class="flex items-center gap-x-1 mt-2 px-2 py-1.5 bg-amber-100 dark:bg-amber-900/20 rounded-lg">
                            <svg class="h-3 w-3 text-amber-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                            <span class="text-[10px] font-semibold text-amber-700 dark:text-amber-400">{{ item.replenishment.available_qty }} {{ __('at') }} {{ item.replenishment.warehouse_name }}</span>
                        </div>
                        <div v-else-if="item.sale_point_qty < item.quantity" class="flex items-center gap-x-1 mt-2 px-2 py-1.5 bg-red-50 dark:bg-red-900/10 rounded-lg">
                            <svg class="h-3 w-3 text-red-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                            <span class="text-[10px] font-semibold text-red-600 dark:text-red-400">{{ __('Out of stock everywhere') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Cart footer -->
                <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 space-y-3">
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Customer') }}</label>
                            <button @click="showingAddCustomerModal = true" class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 hover:text-emerald-700 transition-colors">+ {{ __('Add New') }}</button>
                        </div>
                        <CustomSelect v-model="checkoutForm.customer_id" :options="customerOptions" label="name" track-by="id" :remote="true" :loading="customersLoading" @search-change="searchCustomer" @scroll-end="loadMoreCustomers" :placeholder="__('Walk-in Customer')" class="w-full" />
                    </div>
                    <div v-if="cart.length > 0">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5 block">{{ __('Discount') }}</label>
                        <div class="flex items-center gap-x-2">
                            <div class="flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shrink-0">
                                <button type="button" class="px-2.5 py-1.5 text-xs font-semibold transition-colors" :class="discountType === 'percent' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-50'" @click="discountType = 'percent'">%</button>
                                <button type="button" class="px-2.5 py-1.5 text-xs font-semibold transition-colors" :class="discountType === 'flat' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 hover:bg-gray-50'" @click="discountType = 'flat'">{{ currency }}</button>
                            </div>
                            <input v-model="discountValue" type="number" min="0" :max="discountType === 'percent' ? 100 : cartSubtotal" :placeholder="discountType === 'percent' ? '0' : '0.00'" class="flex-1 text-sm px-3 py-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50" />
                            <span v-if="discountAmount > 0" class="text-xs font-semibold text-red-500 shrink-0">-{{ fmt(discountAmount) }}</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total') }}</span>
                        <div class="text-end">
                            <span v-if="discountAmount > 0" class="block text-xs line-through text-gray-400 dark:text-gray-600 ltr:text-right rtl:text-left">{{ fmt(cartSubtotal) }}</span>
                            <span class="text-2xl font-bold text-emerald-600">{{ fmt(total) }}</span>
                        </div>
                    </div>
                    <button type="button" class="w-full py-4 text-base font-semibold rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed" :class="cart.some(i => i.sale_point_qty < i.quantity) ? 'bg-amber-500 hover:bg-amber-600 text-white focus:ring-amber-500' : 'bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500'" :disabled="cart.length === 0 || checkoutForm.processing || cart.some(i => i.sale_point_qty < i.quantity && !i.replenishment)" @click="openCheckoutModal">
                        <span v-if="cart.some(i => i.sale_point_qty < i.quantity)">{{ __('Review & Complete') }} — {{ fmt(total) }}</span>
                        <span v-else>{{ __('Complete Sale') }} — {{ fmt(total) }}</span>
                    </button>
                    <div v-if="cartErrorMessage" class="p-3 rounded-lg bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-700 text-red-600 dark:text-red-400 text-sm">{{ cartErrorMessage }}</div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <QuickAddPartyModal :show="showingAddCustomerModal" type="customer" @close="showingAddCustomerModal = false" @created="onCustomerCreated" />

        <PosCheckoutModal ref="checkoutModalRef" :show="showingCheckoutModal" :total="total" :discount-amount="discountAmount" :processing="checkoutForm.processing" :currency="currency" @close="showingCheckoutModal = false" @confirm="checkout" />

        <PosSaleCompleteModal :show="showingSaleCompleteModal" :sale="completedSale" :currency="currency" :payment-methods="paymentMethodLabels" @new-sale="startNewSale" @print="printLastReceipt" />

        <!-- Transfer confirmation modal -->
        <Modal :show="showingTransferModal" @close="showingTransferModal = false">
            <div class="p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Stock transfer required') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('The following items will be transferred before completing the sale') }}</p>
                </div>
                <ul class="space-y-3 mb-6">
                    <li v-for="transfer in transfersRequired" :key="transfer.product_id" class="flex items-center justify-between text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <div class="flex flex-col">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ transfer.product_name }}</span>
                            <span class="text-xs text-gray-500">{{ __('From') }}: {{ transfer.from_warehouse_name }}</span>
                        </div>
                        <span class="font-bold text-emerald-600">{{ transfer.quantity }} {{ __('unit(s)') }}</span>
                    </li>
                </ul>
                <div class="flex justify-end gap-x-3">
                    <button @click="showingTransferModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">{{ __('Cancel') }}</button>
                    <PrimaryButton @click="confirmTransferAndCheckout" :disabled="checkoutForm.processing">{{ __('Transfer & Complete Sale') }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Unavailable products modal -->
        <Modal :show="showingUnavailableModal" @close="showingUnavailableModal = false">
            <div class="p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Items Unavailable') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('The following items are unavailable and cannot be purchased') }}</p>
                </div>
                <ul class="space-y-3 mb-6">
                    <li v-for="product in unavailableProducts" :key="product.product_id" class="flex items-center justify-between text-sm p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                        <div class="flex flex-col">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ product.product_name }}</span>
                            <span class="text-xs text-gray-500">{{ __('Available locally') }}: {{ product.available_locally }}</span>
                        </div>
                        <span class="font-bold text-red-600 dark:text-red-400">{{ __('Needed') }}: {{ product.needed }}</span>
                    </li>
                </ul>
                <div class="flex justify-end">
                    <button @click="showingUnavailableModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">{{ __('Close') }}</button>
                </div>
            </div>
        </Modal>

        <PosCloseSessionModal :show="showingCloseModal" :session-id="session.id" :session-stats="session_stats" :currency="currency" @close="showingCloseModal = false" />
    </AppLayout>
</template>
